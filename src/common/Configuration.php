<?php
namespace frmichel\sparqlms\common;

use Exception;

/**
 * Utility class to load configuration parameters from the global config.ini file,
 * and from the service's config.ini or service description graphs.
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
        $config = parse_ini_file('config.ini', false, INI_SCANNER_TYPED);
        if (! $config)
            throw new Exception("Cannot read configuration file config/config.ini.");
        
        if (! array_key_exists('version', $config))
            throw new Exception("Missing configuration property 'version'. Check config.ini.");
        
        if (! array_key_exists('root_url', $config))
            throw new Exception("Missing configuration property 'root_url'. Check config.ini.");
        
        if (! array_key_exists('services_paths', $config))
            throw new Exception("Missing configuration property 'services_paths'. Check config.ini.");
        
        foreach ($config['services_paths'] as $path)
            if (! file_exists($path))
                throw new Exception("Directoy " . $path . " does not exist. Check property 'services_paths' in config.ini.");
        
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
     * or from the Service Description graph (stored in the local RDF store).
     *
     * In addition, parameter 'service_description' is set to false in case of config.ini and to true
     * in case of Service Description graph.
     *
     * @return array associative array of config parameters and values, with additional parameter 'service_description'
     */
    static public function getCustomConfig()
    {
        global $context;
        $logger = $context->getLogger("Configuration");
        
        $customCfgFile = $context->getServicePath() . '/config.ini';
        if (file_exists($customCfgFile)) {
            
            // ---------------------------------------------------------------
            // --- Read the custom service configuration from config.ini file
            // ---------------------------------------------------------------
            
            $customCfg = parse_ini_file($customCfgFile, false, INI_SCANNER_TYPED);
            if (! $customCfg)
                throw new Exception("Configuration file " . $customCfgFile . " is invalid.");
            
            if (! array_key_exists('api_query', $customCfg))
                throw new Exception("Missing configuration property 'api_query'. Check " . $customCfgFile . ".");
            
            if (! array_key_exists('custom_parameter', $customCfg))
                $logger->warning("No configuration property 'custom_parameter' in " . $customCfgFile . ".");
            
            $customCfg['service_description'] = false;
        } else {
            // ----------------------------------------------------------------------------
            // --- Read the custom service configuration from the service description graph
            // ----------------------------------------------------------------------------
            
            $logger->info("No custom configuration file " . $customCfgFile . ". Trying service description graph...");
            $customCfg = array();
            $customCfg['service_description'] = true;
            $serviceUri = $context->getServiceUri();
            
            // --- Read Web API query string and config parameters from the service description graph
            
            // Exec the SPARQL query to read the config parameters
            $query = file_get_contents('resources/read_custom_config.sparql');
            $query = str_replace('{serviceUri}', $serviceUri, $query);
            $jsonResult = Utils::runSparqlSelectQuery($query);
            if (sizeof($jsonResult) == 0)
                throw new Exception("No service description found for service <" . $serviceUri . ">.");
            $jsonResult0 = $jsonResult[0]; // There should be no more than one result (max one value for each parameter)
                                           
            // Read the Web API query string
            $customCfg['api_query'] = $jsonResult0['apiQuery']['value'];
            
            // Read cache expiration time: variable ?expiresAfter may be unbound (optional triple pattern)
            if (array_key_exists('expiresAfter', $jsonResult0)) {
                $_configParamVal = $jsonResult0['expiresAfter']['value'];
                if (array_key_exists('datatype', $jsonResult0['expiresAfter'])) {
                    $_configParamType = $jsonResult0['expiresAfter']['datatype'];
                    if ($_configParamType != "http://www.w3.org/2001/XMLSchema#duration")
                        throw new Exception("Invalid datatype for sms:cacheExpiresAfter: should be xsd:duration.");
                    // Remove the starting 'P' and the last character
                    // @todo we assume the last character is 'S' for seconds, but that could be M, H...
                    // see https://www.w3schools.com/XML/schema_dtypes_date.asp
                    $_configParamVal = substr($_configParamVal, 1, strlen($_configParamVal) - 2);
                }
                $customCfg['cache_expires_after'] = $_configParamVal;
            }
            
            // Read the provenance information boolean: may be unbound (optional triple pattern)
            if (array_key_exists('addProvenance', $jsonResult0)) {
                $_configParamVal = $jsonResult0['addProvenance']['value'];
                if (array_key_exists('datatype', $jsonResult0['addProvenance'])) {
                    $_configParamType = $jsonResult0['addProvenance']['datatype'];
                    if ($_configParamType != "http://www.w3.org/2001/XMLSchema#boolean")
                        throw new Exception("Invalid datatype for sms:addProvenance: should be xsd:boolean.");
                }
                $customCfg['add_provenance'] = $_configParamVal == "true";
            }
            
            // --- Read optional HTTP headers
            
            $query = file_get_contents('resources/read_custom_config_httpheaders.sparql');
            $query = str_replace('{serviceUri}', $serviceUri, $query);
            $jsonResult = Utils::runSparqlSelectQuery($query);
            
            foreach ($jsonResult as $_binding) {
                $headerName = $_binding['headerName']['value'];
                $headerValue = $_binding['headerValue']['value'];
                $customCfg['http_header'][$headerName] = $headerValue;
            }
            
            // --- Read the service input arguments from the Hydra mapping
            
            $query = file_get_contents('resources/read_custom_config_args.sparql');
            $query = str_replace('{serviceUri}', $serviceUri, $query);
            $jsonResult = Utils::runSparqlSelectQuery($query);
            if (sizeof($jsonResult) == 0)
                throw new Exception("No argument mapping found for service <" . $serviceUri . ">.");
            
            foreach ($jsonResult as $_binding) {
                $_name = $_binding['name']['value'];
                $customCfg['custom_parameter'][] = $_name;
                
                // --- Variables ?predicate and ?shape may be unbound (optional triple patterns), but one of them should be provided
                if (array_key_exists('predicate', $_binding))
                    $customCfg['custom_parameter_binding'][$_name]['predicate'] = $_binding['predicate']['value'];
                elseif (array_key_exists('shape', $_binding))
                    $customCfg['custom_parameter_binding'][$_name]['shape'] = $_binding['shape']['value'];
                else
                    throw new Exception("No hydra:property nor shacl:sourceShape found for argument " . $_name . " of service <" . $serviceUri . ">. Fix the service description graph.");
                
                // --- Variables ?csvAsMulpInvoc may be unbound (optional triple patterns)
                if (array_key_exists('csvAsMulpInvoc', $_binding)) {
                    $_csvAsMulpInvoc = $_binding['csvAsMulpInvoc'];
                    
                    if (array_key_exists('datatype', $_csvAsMulpInvoc)) {
                        $_configParamType = $_csvAsMulpInvoc['datatype'];
                        if ($_configParamType != "http://www.w3.org/2001/XMLSchema#boolean")
                            throw new Exception("Invalid datatype for sms:csvAsMultipleInvocations: should be xsd:boolean.");
                    }
                    if (! array_key_exists('custom_parameter.csv_as_multiple_invocations', $customCfg))
                        $customCfg['custom_parameter.csv_as_multiple_invocations'] = array();
                    $customCfg['custom_parameter.csv_as_multiple_invocations'][$_name] = $_csvAsMulpInvoc['value'] == "true";
                }
            }
        }
        
        // --- Check and fill-in optional array 'custom_parameter.csv_as_multiple_invocations'
        
        if (! array_key_exists('custom_parameter.csv_as_multiple_invocations', $customCfg))
            // Create it if it does not exist yet
            $customCfg['custom_parameter.csv_as_multiple_invocations'] = array();
        
        foreach (array_values($customCfg['custom_parameter']) as $key)
            // Make sure all custom arguments are in array 'custom_parameter.csv_as_multiple_invocations' with default value
            if (! array_key_exists($key, $customCfg['custom_parameter.csv_as_multiple_invocations']))
                $customCfg['custom_parameter.csv_as_multiple_invocations'][$key] = false; // default value is false
        
        return $customCfg;
    }
}
?>