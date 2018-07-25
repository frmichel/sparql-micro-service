<?php
namespace frmichel\sparqlms;

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Exception;

/**
 * Application execution context containing the configuration, logger and cache.
 *
 * The constructor also checks the presence of the HTTP query string parameters
 * listed in the configuration.
 *
 * @author fmichel
 */
class Context
{

    /**
     *
     * @var Context
     */
    private static $singleton = null;

    /**
     *
     * @var \Monolog\Logger
     */
    private $logger = null;

    /**
     *
     * @var Cache
     */
    private $cache = null;

    /**
     * Configuration parameters: this includes the main config file (./config.ini)
     * as well as the custom service config file (./<service name>/config.ini)
     *
     * @var array
     */
    private $config = null;

    /**
     * Service name being called.
     * Retrived from query string parameter 'service',
     * e.g. 'flickr/getPhotoById'
     *
     * @var string
     */
    private $service = null;

    /**
     *
     * @param string $configFile
     * @param integer $logLevel
     *            one of Logger::INFO, Logger::WARNING, Logger::DEBUG etc. (see Monolog\Logger.php)
     */
    private function __construct($configFile, $logLevel)
    {
        // --- Initialize the logger
        if (array_key_exists('SCRIPT_FILENAME', $_SERVER))
            $scriptName = basename($_SERVER['SCRIPT_FILENAME']);
        else
            $scriptName = basename(__FILE__);
        $handler = new RotatingFileHandler(__DIR__ . '/../../logs/sms.log', 5, $logLevel, true, 0666);
        $handler->setFormatter(new LineFormatter(null, null, true));
        $this->logger = new Logger($scriptName);
        $this->logger->pushHandler($handler);
        $logger = $this->logger;
        $logger->info("--------- Start --------");

        // --- Read the global configuration file and check query parameters
        $this->config = parse_ini_file($configFile);
        if (! $this->config)
            throw new Exception("Cannot read configuration file config.ini.");
        if (! array_key_exists('sparql_endpoint', $this->config))
            throw new Exception("Missing configuration property 'sparql_endpoint'. Check config.ini.");
        if (! array_key_exists('default_mime_type', $this->config))
            throw new Exception("Missing configuration property 'default_mime_type'. Check config.ini.");
        if (! array_key_exists('parameter', $this->config))
            throw new Exception("Missing configuration property 'parameter'. Check config.ini.");

        // Set default namespaces. See other existing default namespaces in EasyRdf/Namespace.php
        if (array_key_exists('namespace', $this->config))
            foreach ($this->config['namespace'] as $nsName => $nsVal) {
                if ($logger->isHandling(Logger::DEBUG))
                    $logger->debug('Adding namespace: ' . $nsName . " = " . $nsVal);
                \EasyRdf_Namespace::set($nsName, $nsVal);
            }

        // --- Read mandatory HTTP query string arguments
        list ($service, $querymode, $sparqlQuery) = array_values($this->getQueryStringArgs($this->getConfigParam('parameter')));
        if ($service != '')
            $this->service = $service;
        else
            throw new Exception("Invalid configuration: empty argument 'service'.");

        if ($querymode != 'sparql' && $querymode != 'ld')
            throw new Exception("Invalid argument 'querymode': should be one of 'sparql' or 'lod'.");

        // --- Read the custom service configuration file and check query parameters
        $customCfgFile = $service . '/config.ini';
        $customCfg = parse_ini_file($customCfgFile);
        if (! $customCfg)
            throw new Exception("Cannot read custom configuration file " . $customCfgFile);
        if (! array_key_exists('api_query', $customCfg))
            throw new Exception("Missing configuration property 'api_query'. Check " . $customCfgFile . ".");
        if (! array_key_exists('custom_parameter', $customCfg))
            $logger->warning("No configuration property 'custom_parameter' in " . $customCfgFile . ".");

        // Merge the custom config with the global config
        $this->config = array_merge($this->config, $customCfg);

        // --- Initialize the cache database connection (must be done after the custom config has been loaded and merged, to get the expiration time)
        if ($this->useCache())
            $this->cache = Cache::getInstance($this);
    }

    /**
     * Create and/or get singleton instance
     *
     * @param string $configFile
     * @return Context
     */
    public static function getInstance($configFile = null, $logLevel = Logger::INFO)
    {
        if (is_null(self::$singleton)) {
            if (is_null($configFile))
                throw new Exception("Error: application context not yet initialized.");
            self::$singleton = new Context($configFile, $logLevel);
        }
        return self::$singleton;
    }

    /**
     * Wether to use the cache or not.
     * Defaults to false if not in the configuration file
     *
     * @return boolean
     */
    public function useCache()
    {
        return array_key_exists('use_cache', $this->config) ? $this->config['use_cache'] : false;
    }

    /**
     *
     * @return \Monolog\Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     *
     * @return Cache
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * Read a parameter from the configuration (generic or custom)
     *
     * @return string
     */
    public function getConfigParam($param)
    {
        return $this->config[$param];
    }

    /**
     * Check if the configuration (generic or custom) contains a parameter
     *
     * @return boolean
     */
    public function hasConfigParam($param)
    {
        return array_key_exists($param, $this->config);
    }

    /**
     * Return the service name being called.
     * Retrived from query string parameter 'service', e.g. 'flickr/getPhotoById'
     *
     * @return string
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * Check and return the HTTP query string arguments.
     * If any expected parameter in not found, the function returns an HTTP error 400 and exits.
     *
     * @param array $params
     *            array of parameter names
     * @return array associative array of parameter names and values read from the query string
     */
    public function getQueryStringArgs($params)
    {
        $result = array();
        foreach ($params as $paramName) {
            // The service parameters are passed in the query string
            if (array_key_exists($paramName, $_REQUEST)) {
                if ($paramName != "query")
                    $paramValue = strip_tags($_REQUEST[$paramName]);
                else
                    $paramValue = $_REQUEST[$paramName];
                $result[$paramName] = $paramValue;
            } else
                badRequest("Query parameter '" . $paramName . "' undefined.");
        }

        return $result;
    }
}
?>
