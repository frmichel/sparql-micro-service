<?php
namespace frmichel\sparqlms;

/**
 * This script implements the core logic of SPARQL micro-services.
 */
require_once '../../vendor/autoload.php';

use Monolog\Logger;
use frmichel\sparqlms\common\Cache;
use frmichel\sparqlms\common\Context;
use frmichel\sparqlms\common\Metrology;
use frmichel\sparqlms\common\Utils;
use Exception;
require_once '../common/Utils.php';
require_once '../common/Context.php';
require_once '../common/Configuration.php';
require_once '../common/Metrology.php';
require_once '../common/Cache.php';

try {
    // ------------------------------------------------------------------------------------
    // --- Initializations
    // ------------------------------------------------------------------------------------
    
    // Metrology: set level to Logger::INFO to activate metrology, Logger::WARNING or higher to deactivate
    $metro = Metrology::getInstance(Logger::WARNING);
    $metro->startTimer(1);
    
    // Init the context: read the global config.ini file, init the cache, logger and SPARQL client
    $context = Context::getInstance("--------- Starting SPARQL micro-service --------");
    $logger = $context->getLogger("sparqlms\service");
    $sparqlClient = $context->getSparqlClient();
    
    // ------------------------------------------------------------------------------------
    // --- Parse the HTTP query, retrieve mandatory arguments and SPARQL query
    // ------------------------------------------------------------------------------------
    
    // --- Read the service's arguments from the query string
    $_queryStringParams = Utils::getQueryStringArgs($context->getConfigParam('parameter'));
    
    $_service = $_queryStringParams['service'][0];
    if ($_service != '')
        $context->setService($_service);
    else
        Utils::httpBadRequest("Invalid invokation: empty argument 'service'.");
    $logger->notice("Query parameter (html special chars encoded) 'service': " . htmlspecialchars($_service));
    
    // --- Set the path to the directory where the service being invoked is deployed
    foreach ($context->getConfigParam('services_paths') as $_path) {
        $_servicePath = $_path . '/' . $context->getService();
        if (file_exists($_servicePath))
            $context->setServicePath($_servicePath);
    }
    if ($context->getServicePath() == null)
        throw new Exception("Directoy not found for service " . $context->getService());
    if ($logger->isHandling(Logger::INFO))
        $logger->info("Directory where the service is deployed: " . $context->getServicePath());
    
    $_querymode = $_queryStringParams['querymode'][0];
    if ($_querymode != 'sparql' && $_querymode != 'ld')
        Utils::httpBadRequest("Invalid argument 'querymode': " . $_querymode . ". Should be one of 'sparql' or 'ld'.");
    $logger->notice("Query parameter (html special chars encoded) 'querymode': " . htmlspecialchars($_querymode));
    $context->setQueryMode($_querymode);
    
    if (array_key_exists('root_url', $_queryStringParams)) {
        $_rootUrl = $_queryStringParams['root_url'][0];
        if ($_rootUrl != '') {
            $context->setConfigParam('root_url', $_rootUrl);
            if ($logger->isHandling(Logger::INFO))
                $logger->info("Root URL overridden by query string parameter 'root_url' (html special chars encoded):  " . htmlspecialchars($_rootUrl));
        }
    }
    
    // Read HTTP headers
    list ($contentType, $accept) = Utils::getHttpHeaders();
    
    // Get the SPARQL query using either GET or POST methods
    if ($context->getQueryMode() == "sparql") {
        $_sparqlQuery = Utils::getSparqlQuery();
        if ($_sparqlQuery == "")
            Utils::httpBadRequest("Empty SPARQL query.");
        $logger->notice("Client SPARQL query (html special chars encoded):\n" . htmlspecialchars($_sparqlQuery));
    } else {
        // No SPARQL query is provided if querymode is 'ld': set a default construct query
        $_sparqlQuery = "CONSTRUCT WHERE { ?s ?p ?o }";
        $logger->notice("No SPARQL query provided in ld mode. Setting URI dereferencing query:\n" . $_sparqlQuery);
    }
    $context->setSparqlQuery($_sparqlQuery);
    
    // ------------------------------------------------------------------------------------
    // --- Read the service custom configuration (from config.ini or from the Service Description
    // graph), and init the cache DB connection
    // ------------------------------------------------------------------------------------
    
    $context->readCustomConfig();
    
    // Initialize the cache database connection
    // (must be done after the custom config has been loaded and merged, to get the expiration time)
    if ($context->useCache())
        $context->setCache(Cache::getInstance($context));
    
    // ------------------------------------------------------------------------------------
    // --- Build the Web API query string
    // ------------------------------------------------------------------------------------
    
    // Read the Web API query string template
    $apiQuery = $context->getConfigParam('api_query');
    
    // Read the values of the service custom arguments either from the HTTP query string or from the SPARQL graph pattern
    $customArgs = Utils::getServiceCustomArgs();
    if (sizeof($customArgs) != sizeof($context->getConfigParam('custom_parameter'))) {
        // In case one argument is not found in the query, do not query the API and just return an empty response
        $logger->warn("Not all service arguments were found. Expected: " . print_r($context->getConfigParam('custom_parameter'), TRUE) . "\nbut read: " . print_r($customCfg, TRUE));
        $apiQuery = "";
    } else {
        if (file_exists($context->getServicePath() . '/service.php')) {
            // Web API query string will be formatted by the custom service script
            require $context->getServicePath() . '/service.php';
        } else {
            foreach ($customArgs as $argName => $argVal)
                $apiQuery = str_replace('{' . $argName . '}', rawurlencode(implode(",", $argVal)), $apiQuery);
        }
    }
    
    if ($apiQuery != "") {
        $logger->notice("Read service custom arguments: " . print_r($customArgs, true));
        $logger->notice("Web API query string: " . $apiQuery);
    } else
        $logger->notice("Web API query was set to empty string. Will return empty response.");
    
    // ------------------------------------------------------------------------------------
    // --- Invoke the Web API and create the response graph
    // ------------------------------------------------------------------------------------
    
    // URIs of the temporary work graphs
    $apiGraphUri = $context->getConfigParam('root_url') . '/api-graph' . uniqid("-", true);
    $respGraphUri = $context->getConfigParam('root_url') . '/resp-graph' . uniqid("-", true);
    
    // Call the Web API service, apply the JSON-LD profile and translate to NQuads
    $metro->startTimer(2);
    $serializedQuads = ($apiQuery == "") ? "" : Utils::translateJsonToNQuads($apiQuery, $context->getServicePath() . '/profile.jsonld');
    $metro->stopTimer(2);
    
    // Insert the triples generated from the Web API response into the temp graph
    if ($logger->isHandling(Logger::INFO))
        $logger->info("Creating temporary graph: <" . $apiGraphUri . ">");
    $_query = "INSERT DATA { GRAPH <" . $apiGraphUri . "> {\n" . $serializedQuads . "\n}}\n";
    $sparqlClient->update($_query);
    
    // --- Create the triples for which this SPARQL service is meant
    $sparqlConstr = $context->getServicePath() . '/construct.sparql';
    if (file_exists($sparqlConstr)) {
        
        // Prepare the CONSTRUCT query to execute against the temp graph that was just created
        $logger->notice("Executing SPARQL CONSTRUCT query from file: " . $sparqlConstr);
        $_query = file_get_contents($sparqlConstr);
        
        // Reinject the service arguments into the CONSTRUCT query.
        // See doc/02-config.md#re-injecting-arguments-in-the-graph-produced-by-the-micro-service
        $sparqlVal = "";
        foreach ($customArgs as $argName => $argVal) {
            // In case there are multiple values, they are injected like "val1", "val2"...
            foreach ($argVal as $val)
                if ($sparqlVal == "")
                    $sparqlVal = '"' . $val . '"';
                else
                    $sparqlVal .= ', "' . $val . '"';
            
            $_query = str_replace('{' . $argName . '}', $sparqlVal, $_query);
            $_query = str_replace('{urlencode(' . $argName . ')}', urlencode(str_replace('"', "", $sparqlVal)), $_query);
        }
        
        if ($logger->isHandling(Logger::INFO))
            $logger->info("CONSTRUCT query:\n" . $_query);
        $constrResult = $sparqlClient->queryRaw($_query, "text/turtle", $defaultGraphUri = $apiGraphUri);
        
        // Create a new temp graph with the result of the CONSTRUCT, using an INSERT DATA query
        $prefixes = "";
        $triples = "";
        foreach (explode("\n", $constrResult->getBody()) as $line)
            if (stripos($line, '@prefix') === 0 || stripos($line, 'prefix') === 0)
                $prefixes .= $line . "\n";
            else
                $triples .= "    " . $line . "\n";
        
        $logger->info("Adding result of the CONSTRUCT query into new temporary graph: <" . $respGraphUri . ">");
        $_query = $prefixes . "\nINSERT DATA { \n  GRAPH <" . $respGraphUri . "> {\n" . $triples . "\n}}\n";
        if ($logger->isHandling(Logger::DEBUG))
            $logger->debug("Creating temporary graph: <" . $respGraphUri . "> with INSERT DATA query:\n" . $_query);
        $sparqlClient->update($_query);
    }
    
    // ------------------------------------------------------------------------------------
    // --- Optional: add provenance triples
    // ------------------------------------------------------------------------------------
    
    if ($context->getConfigParam('add_provenance', false)) {
        
        // Date time at which the SPARQL micro-service is invoked
        $now = (new \DateTime('now'));
        
        // Date time at which the Web API document was obtained from the cache, if any
        $cacheDateTime = $context->getCacheHitDateTime();
        if ($cacheDateTime == null)
            $cacheDateTime = $now;
        
        if ($context->getConfigParam('service_description'))
            $_query = file_get_contents('resources/add_provenance.sparql');
        else
            $_query = file_get_contents('resources/add_provenance_simple.sparql');
        
        $_query = str_replace('{graphUri}', $respGraphUri, $_query);
        $_query = str_replace('{serviceUri}', $context->getServiceUri(), $_query);
        $_query = str_replace('{date_time_sms_invocation}', $now->format('c'), $_query);
        $_query = str_replace('{date_time_cachehit}', $cacheDateTime->format('c'), $_query);
        $_query = str_replace('{sms_version}', $context->getConfigParam("version"), $_query);
        
        // Add the Web API query string but obfuscate the API key if any
        if (strpos($apiQuery, "apikey") !== false) {
            $apiKeyObfuscated = preg_replace('/([\?&])apikey=[^&]*/', '${1}apikey=obfuscated', $apiQuery);
            $_query = str_replace('{webapi_query_string}', $apiKeyObfuscated, $_query);
        } else if (strpos($apiQuery, "api_key") !== false) {
            $apiKeyObfuscated = preg_replace('/([\?&])api_key=[^&]*/', '${1}api_key=obfuscated', $apiQuery);
            $_query = str_replace('{webapi_query_string}', $apiKeyObfuscated, $_query);
        } else
            $_query = str_replace('{webapi_query_string}', $apiQuery, $_query);
        
        $logger->info("Adding provenance triples into graph: <" . $respGraphUri . ">");
        if ($logger->isHandling(Logger::DEBUG))
            $logger->debug("Adding provenance triples with query:\n" . $_query);
        $sparqlClient->update($_query);
    }
    
    // ------------------------------------------------------------------------------------
    // --- Optional: calculate the number of triples in the temporary graphs
    // ------------------------------------------------------------------------------------
    
    if ($metro->isHandling(Logger::INFO)) {
        $nbTriplesQuery = "SELECT (COUNT(*) AS ?count) FROM <" . $apiGraphUri . "> WHERE { ?s ?p ?o }";
        $result = $sparqlClient->queryRaw($nbTriplesQuery, "application/sparql-results+json");
        $jsonResult = json_decode($result->getBody(), true);
        $metro->appendMessage($context->getService(), "No triples generated with JSON-LD profile", $jsonResult['results']['bindings'][0]['count']['value']);
        
        $nbTriplesQuery = "SELECT (COUNT(*) AS ?count) FROM <" . $respGraphUri . "> WHERE { ?s ?p ?o }";
        $result = $sparqlClient->queryRaw($nbTriplesQuery, "application/sparql-results+json");
        $jsonResult = json_decode($result->getBody(), true);
        $metro->appendMessage($context->getService(), "No triples generated with CONSTRUCT query", $jsonResult['results']['bindings'][0]['count']['value']);
    }
    
    // ------------------------------------------------------------------------------------
    // --- Run the client's SPARQL query against the response temporary graph
    // ------------------------------------------------------------------------------------
    
    $logger->notice('Evaluating client SPARQL query against temporary graph...');
    
    $result = $sparqlClient->queryRaw($context->getSparqlQuery(), $accept, $defaultGraphUri = $respGraphUri);
    if ($logger->isHandling(Logger::INFO))
        foreach ($result->getHeaders() as $header => $headerVal)
            $logger->info('Received response header: ' . $header . ": " . $headerVal);
    
    // ------------------------------------------------------------------------------------
    // --- Return the HTTP response to the SPARQL client
    // ------------------------------------------------------------------------------------
    
    $logger->notice("Sending response with Content-Type: " . $result->getHeader('Content-Type'));
    header('Content-Type: ' . $result->getHeader('Content-Type'));
    header('Server: SPARQL-Micro-Service');
    header('Access-Control-Allow-Origin: *');
    if ($logger->isHandling(Logger::DEBUG))
        $logger->debug("Sending response body:\n" . $result->getBody());
    print($result->getBody());
    
    // Drop the temporary graphs
    $logger->info("Dropping graph: <" . $apiGraphUri . ">");
    $sparqlClient->update("DROP SILENT GRAPH <" . $apiGraphUri . ">");
    
    $logger->info("Dropping graph: <" . $respGraphUri . ">");
    $sparqlClient->update("DROP SILENT GRAPH <" . $respGraphUri . ">");
    
    $logger->notice("--------- Done - SPARQL µS execution --------");
    
    $metro->stopTimer(1);
    $metro->appendTimer($context->getService(), "Total|API", 1, 2);
} catch (Exception $e) {
    try {
        $logger = Context::getInstance()->getLogger("sparqlms\service");
        $logger->error((string) $e . "\n");
        $logger->notice("Returning HTTP status 500.\n");
        $logger->notice("--------- Done - SPARQL µS execution --------");
    } catch (Exception $f) {
        print("Could not process the request. Error:\n" . (string) $e . "\n");
        print("Second exception caught:\n" . (string) $f . "\n");
    }
    header('Access-Control-Allow-Origin: *');
    http_response_code(500);
    print("Internal error: " . $e->getMessage() . "\n");
    exit(0);
}
?>
