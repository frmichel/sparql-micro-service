<?php
namespace frmichel\sparqlms;

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use EasyRdf_Sparql_Client;
use Exception;

/**
 * Application execution context containing the configuration, logger, cache, SPARQL client
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
     * Local RDF store and SPARQL endpoint
     *
     * @var EasyRdf_Sparql_Client
     */
    private $sparqlClient = null;

    /**
     *
     * @param string $configFile
     * @param integer $logLevel
     *            one of Logger::INFO, Logger::WARNING, Logger::DEBUG etc. (see Monolog\Logger.php)
     */
    private function __construct($logLevel)
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
        $this->config = Configuration::readGobalConfig();
        if ($logger->isHandling(Logger::DEBUG))
            $logger->debug("Global configuration read from config.ini: " . print_r($this->config, TRUE));
        
        // Set default namespaces. See other existing default namespaces in EasyRdf/Namespace.php
        if (array_key_exists('namespace', $this->config))
            foreach ($this->config['namespace'] as $nsName => $nsVal) {
                if ($logger->isHandling(Logger::DEBUG))
                    $logger->debug('Adding namespace: ' . $nsName . " = " . $nsVal);
                \EasyRdf_Namespace::set($nsName, $nsVal);
            }
        
        // --- Read mandatory HTTP query string arguments
        list ($service, $querymode) = array_values(Utils::getQueryStringArgs($this->getConfigParam('parameter')));
        if ($service != '')
            $this->service = $service;
        else
            throw new Exception("Invalid configuration: empty argument 'service'.");
        
        if ($querymode != 'sparql' && $querymode != 'ld')
            throw new Exception("Invalid argument 'querymode': should be one of 'sparql' or 'lod'.");
        
        // --- Initialize the local RDF store and SPARQL endpoint
        $this->sparqlClient = new EasyRdf_Sparql_Client($this->getConfigParam('sparql_endpoint'));
        
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
     * Read the service custom configuration and merge it with the global config
     *
     * This cannot be done within the constructor because it requires the context
     * to be initialized first, notably to access the SPARQL client.
     */
    public function readCustomConfig()
    {
        $customCfg = Configuration::getCustomConfig($this);
        if ($this->logger->isHandling(Logger::DEBUG))
            $this->logger->debug("Service custom configuration: " . print_r($customCfg, TRUE));
        $this->config = array_merge($this->config, $customCfg);
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
     * Return the URI of the service being called.
     * The URI ends with a '/'
     * e.g. http://sms.i3s.unice.fr/sparql-ms/flickr/getPhotosByTaxonName/
     *
     * Note the this URI is different from the service sescription graph URI
     * that would be http://sms.i3s.unice.fr/sparql-ms/flickr/getPhotosByTaxonName/ServiceDescription
     *
     * @return string
     */
    public function getServiceUri()
    {
        return $this->getConfigParam('root_url') . "/" . $this->getService() . "/";
    }

    /**
     * Return the URI of the shapes graph, if it exists, e.g.
     * http://sms.i3s.unice.fr/sparql-ms/flickr/getPhotosByTaxonName/ShapesGraph
     *
     * @return string
     */
    public function getShapesGraphUri()
    {
        return $this->getConfigParam('root_url') . "/" . $this->getService() . "/ShapesGraph";
    }

    /**
     * Return the client to the local RDF store and SPARQL endpoint
     *
     * @return EasyRdf_Sparql_Client
     */
    public function getSparqlClient()
    {
        return $this->sparqlClient;
    }
}
?>
