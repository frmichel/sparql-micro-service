<?php
namespace frmichel\sparqlms\common;

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
    private $sparqlQuery = null;

    /**
     * Client to the local RDF store and SPARQL endpoint
     *
     * @var EasyRdf_Sparql_Client
     */
    private $sparqlClient = null;

    /**
     *
     * @param integer $logLevel
     *            one of Logger::INFO, Logger::WARNING, Logger::DEBUG etc. (see Monolog\Logger.php)
     * @param string $startMessage
     *            an optional message to log once the logger is initialized
     */
    private function __construct($logLevel, $startMessage = null)
    {
        // --- Initialize the logger
        if (array_key_exists('SCRIPT_FILENAME', $_SERVER))
            $scriptName = basename($_SERVER['SCRIPT_FILENAME']);
        else
            $scriptName = basename(__FILE__);
        $handler = new RotatingFileHandler(__DIR__ . '/../../logs/sms.log', 5, $logLevel, true, 0666);
        $handler->setFormatter(new LineFormatter("[%datetime%] %level_name%: %message%\n", null, true));
        // $handler->setFormatter(new LineFormatter("[%datetime%] %level_name%: %message% %context% %extra%\n", null, true));
        $this->logger = new Logger($scriptName);
        $this->logger->pushHandler($handler);
        // $this->logger->pushProcessor(new IntrospectionProcessor($logLevel));
        $logger = $this->logger;
        $logger->notice($startMessage);
        
        // --- Read the global configuration file and check query parameters
        $this->config = Configuration::readGobalConfig();
        if ($logger->isHandling(Logger::INFO))
            $logger->info("Global configuration read from config.ini: " . print_r($this->config, TRUE));
        
        // Set default namespaces. See other existing default namespaces in EasyRdf/Namespace.php
        if (array_key_exists('namespace', $this->config))
            foreach ($this->config['namespace'] as $nsName => $nsVal) {
                if ($logger->isHandling(Logger::DEBUG))
                    $logger->debug('Adding namespace: ' . $nsName . " = " . $nsVal);
                \EasyRdf_Namespace::set($nsName, $nsVal);
            }
        
        // --- Initialize the client to the local RDF store and SPARQL endpoint
        $this->sparqlClient = new EasyRdf_Sparql_Client($this->getConfigParam('sparql_endpoint'));
        
        // --- Initialize the cache database connection (must be done after the custom config has been loaded and merged, to get the expiration time)
        if ($this->useCache())
            $this->cache = Cache::getInstance($this);
    }

    /**
     * Create and/or get singleton instance
     *
     * @param integer $logLevel
     *            a log level from Monolog\Logger
     * @param string $startMessage
     *            an optional message to log once the logger is initialized
     * @return Context
     */
    public static function getInstance($logLevel = Logger::NOTICE, $startMessage = null)
    {
        if (is_null(self::$singleton))
            self::$singleton = new Context($logLevel, $startMessage);
        
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
        if ($this->logger->isHandling(Logger::INFO))
            $this->logger->info("Service custom configuration: " . print_r($customCfg, TRUE));
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
     * Return the name of the service being called.
     * Retrived from query string parameter 'service', e.g. 'flickr/getPhotoById'
     *
     * @return string
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * Set the name of the service being called.
     *
     * @param string $serviceName
     */
    public function setService($serviceName)
    {
        $this->service = $serviceName;
    }

    /**
     * Return the URI of the service being called.
     * The URI ends with a '/'
     *
     * @example http://sms.i3s.unice.fr/sparql-ms/flickr/getPhotosByTaxonName/
     *         
     * @return string
     */
    public function getServiceUri()
    {
        return $this->getConfigParam('root_url') . "/" . $this->getService() . "/";
    }

    /**
     * Return the URI of the Service Description graph, that is returned
     * when looking up the service URI.
     *
     * @example http://sms.i3s.unice.fr/sparql-ms/flickr/getPhotosByTaxonName/ServiceDescription
     *         
     * @return string
     */
    public function getServiceDescriptionGraphUri()
    {
        return $this->getConfigParam('root_url') . "/" . $this->getService() . "/ServiceDescription";
    }

    /**
     * Return the URI of the shapes graph, if it exists
     *
     * @example http://sms.i3s.unice.fr/sparql-ms/flickr/getPhotosByTaxonName/ShapesGraph
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

    /**
     * Return the SPARQL query
     *
     * @return string
     */
    public function getSparqlQuery()
    {
        return $this->sparqlQuery;
    }

    /**
     * Set the SPARQL query
     *
     * @param
     *            string q SPARQL query
     */
    public function setSparqlQuery($q)
    {
        $this->sparqlQuery = $q;
    }
}
?>
