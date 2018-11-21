<?php
namespace frmichel\sparqlms;

use Monolog\Logger;
use Exception;

/**
 * Utility class to load configuration parameters from config.ini file
 * or from service description graphs loaded in the local triple store
 *
 * @author fmichel
 */
class Configuration
{

    /**
     * Read the global config.ini file
     *
     * @return array associative array of config parameters and values
     */
    static public function readGobalConfig()
    {
        $config = parse_ini_file('config.ini');
        if (! $config)
            throw new Exception("Cannot read configuration file config/config.ini.");
        
        if (! array_key_exists('root_url', $config))
            throw new Exception("Missing configuration property 'root_url'. Check config.ini.");
        
        if (! array_key_exists('sparql_endpoint', $config))
            throw new Exception("Missing configuration property 'sparql_endpoint'. Check config.ini.");
        
        if (! array_key_exists('default_mime_type', $config))
            throw new Exception("Missing configuration property 'default_mime_type'. Check config.ini.");
        
        if (! array_key_exists('parameter', $config))
            throw new Exception("Missing configuration property 'parameter'. Check config.ini.");
        return $config;
    }

    /**
     * Read the SPARQL micro-serivce custom configuration, either from the service/config.ini file
     * or from the Service Description graph (stored in the local RDF store)
     *
     * @param Context $context
     *            initialized context
     * @return array associative array of config parameters and values
     */
    static public function getCustomConfig($context)
    {
        $logger = $context->getLogger();
        
        $customCfgFile = $context->getService() . '/config.ini';
        if (file_exists($customCfgFile)) {
            
            // --- Read the custom service configuration file

            $customCfg = parse_ini_file($customCfgFile);
            if (! $customCfg)
                throw new Exception("Configuration file " . $customCfgFile . " is invalid.");
            
            if (! array_key_exists('api_query', $customCfg))
                throw new Exception("Missing configuration property 'api_query'. Check " . $customCfgFile . ".");
            
            if (! array_key_exists('custom_parameter', $customCfg))
                $logger->warning("No configuration property 'custom_parameter' in " . $customCfgFile . ".");
            
            $customCfg['service_description'] = false;
        } else {
            
            // --- Read config parameters from the service description graph
            
            if ($logger->isHandling(Logger::DEBUG))
                $logger->debug("Cannot read custom configuration file " . $customCfgFile . ". Trying service description graph.");
            $customCfg = array();
            $customCfg['service_description'] = true;
            $serviceUri = $context->getServiceUri();
            
            // Read Web API query string and cache expiration time
            $query = file_get_contents('resources/read_custom_config.sparql');
            $query = str_replace('{serviceUri}', $serviceUri, $query);
            $jsonResult = runSparqlSelectQuery($query);
            if (sizeof($jsonResult) == 0)
                throw new Exception("No service description found for service <" . $serviceUri . ">.");
            
            $jsonResult0 = $jsonResult[0];
            $customCfg['api_query'] = $jsonResult0['apiQuery']['value'];
            
            // Variable ?expiresAfter may be unbound (optional triple pattern)
            if (array_key_exists('expiresAfter', $jsonResult0)) {
                $expVal = $jsonResult0['expiresAfter']['value'];
                if (array_key_exists('datatype', $jsonResult0['expiresAfter'])) {
                    $expType = $jsonResult0['expiresAfter']['datatype'];
                    if ($expType != "http://www.w3.org/2001/XMLSchema#duration")
                        throw new Exception("Invalid datatype for sms:cacheExpiresAfter: should be an xsd:duration.");
                    // Remove the starting 'P' and the last character
                    // @todo we assume the last character is 'S' for seconds, but that could be M, H...
                    // see https://www.w3schools.com/XML/schema_dtypes_date.asp
                    $expVal = substr($expVal, 1, strlen($expVal) - 2);
                }
                $customCfg['cache_expires_after'] = $expVal;
            }
            
            // Read the service input arguments from the Hydra mapping (possibly multiple values for variable arg)
            $query = file_get_contents('resources/read_custom_config_args.sparql');
            $query = str_replace('{serviceUri}', $serviceUri, $query);
            $jsonResult = runSparqlSelectQuery($query);
            foreach ($jsonResult as $binding) {
                $name = $binding['name']['value'];
                $customCfg['custom_parameter'][] = $name;
                
                // Variables ?argPred and ?propShape may be unbound (optional triple patterns), but one of them should be provided
                if (array_key_exists('argPred', $binding))
                    $customCfg['custom_parameter_binding'][$name]['predicate'] = $binding['predicate']['value'];
                elseif (array_key_exists('argShape', $binding))
                    $customCfg['custom_parameter_binding'][$name]['shape'] = $binding['shape']['value'];
                else
                    throw new Exception("No hydra:property nor shacl:sourceShape found for argument " . $name . " of service <" . $serviceUri . ">. Fix the service description graph.");
            }
            return $customCfg;
        }
    }
}
