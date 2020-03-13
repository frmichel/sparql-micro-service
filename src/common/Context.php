<?php
namespace frmichel\sparqlms\common;

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;

/**
 * Service execution context containing the configuration, logger, cache, SPARQL client
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
     * Logger used in this class
     *
     * @var \Monolog\Logger
     */
    private $logger = null;

    /**
     *
     * @var array array of all named loggers used by all classes of the application
     */
    private $loggers = array();

    /**
     *
     * @var \Monolog\Handler\RotatingFileHandler
     */
    private $logHandler = null;

    /**
     * Connector to the cache DB
     *
     * @var Cache
     */
    private $cache = null;

    /**
     * Configuration parameters: this merges the main config file (./config.ini) as well as the custom
     * service configuration provided either through the service config.ini file (<service name>/config.ini)
     * or the service description graph (<service name>/ServiceDescription.ttl)
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
     * Path (absolute or relative) to the directory where the service being invoked is deployed
     *
     * @var string
     */
    private $servicePath = null;

    /**
     * Query mode: 'sparql' for regular SPARQL querying, or 'ld' for URI dereferencing
     * Retrived from query string parameter 'querymode',
     *
     * @var string
     */
    private $queryMode = null;

    /**
     * SPARQL query submitted to the SPARQL micro-service
     *
     * @var \EasyRdf\Sparql\Client
     */
    private $sparqlQuery = null;

    /**
     * Client to the local RDF store and SPARQL endpoint
     *
     * @var \EasyRdf\Sparql\Client
     */
    private $sparqlClient = null;

    /**
     * If the cache is used to retrieve Web API responses, this field contains the
     * date and time at which the document was retrieved
     *
     * @var \DateTime
     */
    private $cacheHitDateTime = null;

    /**
     * Default constructor
     *
     * @param string $startMessage
     *            an optional message to log once the logger is initialized
     */
    private function __construct($startMessage = null)
    {
        // --- Initialize the logger
        $this->logHandler = new RotatingFileHandler(__DIR__ . '/../../logs/sms.log', 5, Logger::NOTICE, true);
        $this->logHandler->setFormatter(new LineFormatter("%datetime% %extra% %channel% %level_name%: %message%\n", null, true));
        $this->logHandler->pushProcessor(function ($record) {
            $sessionId = session_id();
            $record['extra'][] = substr($sessionId, intval(strlen($sessionId) / 2));
            return $record;
        });
        $this->logger = $this->getLogger("Context");

        if ($startMessage == null)
            $this->logger->notice("--------- Starting service --------");
        else
            $this->logger->notice($startMessage);

        // --- Read the global configuration file and check query parameters
        $this->config = Configuration::readGobalConfig();

        // --- Set the log level
        $log_level = $this->getConfigParam("log_level", "NOTICE");
        switch ($log_level) {
            case "DEBUG":
                $this->logHandler->setLevel(Logger::DEBUG);
                break;
            case "INFO":
                $this->logHandler->setLevel(Logger::INFO);
                break;
            case "NOTICE":
                $this->logHandler->setLevel(Logger::NOTICE);
                break;
            case "WARNING":
                $this->logHandler->setLevel(Logger::WARNING);
                break;
            case "ERROR":
                $this->logHandler->setLevel(Logger::ERROR);
                break;
            case "CRITICAL":
                $this->logHandler->setLevel(Logger::CRITICAL);
                break;
            case "ALERT":
                $this->logHandler->setLevel(Logger::ALERT);
                break;
        }

        if ($this->logger->isHandling(Logger::INFO))
            $this->logger->info("Global configuration read from config.ini: " . Utils::print_r($this->config));

        // --- Set default namespaces. See other existing default namespaces in EasyRdf/RdfNamespace.php
        if (array_key_exists('namespace', $this->config))
            foreach ($this->config['namespace'] as $nsName => $nsVal) {
                if ($this->logger->isHandling(Logger::DEBUG))
                    $this->logger->debug('Adding namespace: ' . $nsName . " = " . $nsVal);
                \EasyRdf\RdfNamespace::set($nsName, $nsVal);
            }

        // --- Initialize the SPARQL client to talk to the SPARQL endpoint from the config
        $this->sparqlClient = new \EasyRdf\Sparql\Client($this->getConfigParam('sparql_endpoint'));
    }

    /**
     * Create and/or get singleton instance
     *
     * @param string $startMessage
     *            an optional message to log once the logger is initialized
     * @return Context
     */
    public static function getInstance($startMessage = null)
    {
        if (is_null(self::$singleton))
            self::$singleton = new Context($startMessage);

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
            $this->logger->info("Have read following service custom configuration: " . Utils::print_r($customCfg));
        $this->config = array_merge($this->config, $customCfg);
    }

    /**
     * Wether to use the cache or not.
     * Defaults to false if not in the configuration
     *
     * @return boolean
     */
    public function useCache()
    {
        return array_key_exists('use_cache', $this->config) ? $this->config['use_cache'] : false;
    }

    /**
     * Create a named logger
     *
     * @param string $logName
     *            logger name (aka. channel). Defaults to "default"
     *            
     * @return \Monolog\Logger
     */
    public function getLogger($logName = "default")
    {
        if (! array_key_exists($logName, $this->loggers)) {
            $newlogger = new Logger($logName);
            $newlogger->pushHandler($this->logHandler);
            $this->loggers[$logName] = $newlogger;
        }

        return $this->loggers[$logName];
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
     *
     * @param \frmichel\sparqlms\common\Cache $cache
     */
    public function setCache($cache)
    {
        $this->cache = $cache;
    }

    /**
     * Read a parameter from the configuration (generic or custom)
     *
     * @param string $param
     *            name of the config parameter
     * @param mixed $defaultValue
     *            optional default value to return if the parameter does not exist
     *            
     * @return string
     */
    public function getConfigParam($param, $defaultValue = null)
    {
        if (array_key_exists($param, $this->config))
            return $this->config[$param];
        else
            return $defaultValue;
    }

    /**
     * Set a parameter from the configuration (generic or custom)
     */
    public function setConfigParam($param, $value)
    {
        $this->config[$param] = $value;
    }

    /**
     * Check if the configuration (generic or custom) contains a parameter
     * and its value is not empty
     *
     * @return boolean
     */
    public function hasConfigParam($param)
    {
        return array_key_exists($param, $this->config) && $this->config[$param] != '';
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
     * Return the path (absolute or relative) to the directory where the service being invoked is deployed
     * for example '../../services/flickr/getPhotoById'
     *
     * @return string
     */
    public function getServicePath()
    {
        return $this->servicePath;
    }

    /**
     * Set the path (absolute or relative) to the directory where the service being invoked is deployed
     *
     * @param string $servicePath
     */
    public function setServicePath($servicePath)
    {
        $this->servicePath = $servicePath;
    }

    /**
     * Return the URI of the service being called.
     * The URI ends with a '/'
     *
     * @example http://example.org/flickr/getPhotosByTaxonName/
     *         
     * @return string
     */
    public function getServiceUri()
    {
        return $this->getConfigParam('root_url') . "/" . $this->getService() . "/";
    }

    /**
     *
     * @return string
     */
    public function getQueryMode()
    {
        return $this->queryMode;
    }

    /**
     *
     * @param string $queryMode
     */
    public function setQueryMode($queryMode)
    {
        $this->queryMode = $queryMode;
    }

    /**
     * Return the URI of the Service Description graph, that is returned
     * when looking up the service URI.
     *
     * @example http://example.org/flickr/getPhotosByTaxonName/ServiceDescription
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
     * @example http://example.org/flickr/getPhotosByTaxonName/ShapesGraph
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
     * @return \EasyRdf\Sparql\Client
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
     * @param string $q
     *            SPARQL query
     */
    public function setSparqlQuery($q)
    {
        $this->sparqlQuery = $q;
    }

    /**
     * Get the date and time at which the document was retrieved from the Web API
     *
     * @return \DateTime
     */
    public function getCacheHitDateTime()
    {
        return $this->cacheHitDateTime;
    }

    /**
     * Set the date and time at which the document was retrieved from the Web API
     *
     * @param \DateTime $cacheHitDateTime
     */
    public function setCacheHitDateTime($cacheHitDateTime)
    {
        $this->cacheHitDateTime = $cacheHitDateTime;
    }
}
?>
