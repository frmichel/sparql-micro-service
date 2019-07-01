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
    
    // Read the service and querymode arguments
    $params = Utils::getQueryStringArgs($context->getConfigParam('parameter'));
    
    $service = $params['service'][0];
    if ($service != '')
        $context->setService($service);
    else
        throw new Exception("Invalid configuration: empty argument 'service'.");
    $logger->notice("Query parameter (html special chars encoded) 'service': " . htmlspecialchars($service));
    
    $querymode = $params['querymode'][0];
    if ($querymode != 'sparql' && $querymode != 'ld')
        throw new Exception("Invalid argument 'querymode': should be one of 'sparql' or 'ld'.");
    $logger->notice("Query parameter (html special chars encoded) 'querymode': " . htmlspecialchars($querymode));
    
    // Read HTTP headers
    list ($contentType, $accept) = Utils::getHttpHeaders();
    
    // Get the SPARQL query using either GET or POST methods
    if ($querymode == "sparql") {
        $sparqlQuery = Utils::getSparqlQuery();
        if ($sparqlQuery == "")
            Utils::httpBadRequest("Empty SPARQL query.");
        $logger->notice("Client SPARQL query (html special chars encoded):\n" . htmlspecialchars($sparqlQuery));
    } else {
        // No SPARQL query is provided if querymode is 'ld': set a default construct query
        $sparqlQuery = "CONSTRUCT WHERE { ?s ?p ?o }";
        $logger->notice("No SPARQL query provided in ld mode. Setting URI dereferencing query:\n" . $sparqlQuery);
    }
    $context->setSparqlQuery($sparqlQuery);
    
    // ------------------------------------------------------------------------------------
    // --- Read the service custom configuration (from config.ini or from the Service Description 
    //     graph (stored in the local RDF store)), and init the cache DB connection
    // ------------------------------------------------------------------------------------
    
    $context->readCustomConfig();
    
    // Initialize the cache database connection
    // (must be done after the custom config has been loaded and merged, to get the expiration time)
    if ($context->useCache())
        $context->cache = Cache::getInstance($context);
    
    // ------------------------------------------------------------------------------------
    // --- Build the Web API query string and call the service
    // ------------------------------------------------------------------------------------
    
    // Read the Web API query string template
    $apiQuery = $context->getConfigParam('api_query');
    
    // Read the service custom arguments either from the HTTP query string or from the SPARQL graph pattern
    $customArgs = Utils::getServiceCustomArgs();
    if (sizeof($customArgs) != sizeof($context->getConfigParam('custom_parameter')))
        // In case one argument is not found in the query, do not query the API and just return an empty response
        $apiQuery = "";
    else {
        if (file_exists($service . '/service.php')) {
            // Web API query string will be formatted by the custom service script
            require $service . '/service.php';
        } else {
            foreach ($customArgs as $argName => $argVal)
                $apiQuery = str_replace('{' . $argName . '}', urlencode(implode(",", $argVal)), $apiQuery);
        }
    }
    
    if ($apiQuery != "") {
        $logger->notice("Read service custom arguments: " . print_r($customArgs, true));
        $logger->notice("Web API query string: " . $apiQuery);
    } else
        $logger->notice("Web API query was set to empty string. Will return empty response.");
    
    // --- Call the Web API service, apply the JSON-LD profile and translate to NQuads
    
    $metro->startTimer(2);
    $serializedQuads = ($apiQuery == "") ? "" : Utils::translateJsonToNQuads($apiQuery, $service . '/profile.jsonld');
    $metro->stopTimer(2);
    
    // ------------------------------------------------------------------------------------
    // --- Create the temporary graph
    // ------------------------------------------------------------------------------------
    
    // URIs of the temporary work graphs
    $apiGraphUri = $context->getConfigParam('root_url') . '/api-graph' . uniqid("-", true);
    $respGraphUri = $context->getConfigParam('root_url') . '/resp-graph' . uniqid("-", true);
    
    // Insert the triples generated from the Web API response
    if ($logger->isHandling(Logger::INFO))
        $logger->info("Creating temporary graph: <" . $apiGraphUri . ">");
    $query = "INSERT DATA { GRAPH <" . $apiGraphUri . "> {\n" . $serializedQuads . "\n}}\n";
    $sparqlClient->update($query);
    
    // Add the triples for which this SPARQL service is meant
    $sparqlConstr = $service . '/construct.sparql';
    if (file_exists($sparqlConstr)) {
        
        // Execute the construct query against the temp graph that was just created
        $logger->notice("Executing SPARQL CONSTRUCT query from file: " . $sparqlConstr);
        $query = file_get_contents($sparqlConstr);
        
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
            
            $query = str_replace('{' . $argName . '}', $sparqlVal, $query);
            $query = str_replace('{urlencode(' . $argName . ')}', urlencode(str_replace('"', "", $sparqlVal)), $query);
        }
        
        if ($logger->isHandling(Logger::INFO))
            $logger->info("Generating additional triples with CONSTRUCT query:\n" . $query);
        $constrResult = $sparqlClient->queryRaw($query, "text/turtle", $defaultGraphUri = $apiGraphUri);
        
        // With the result of the construct, create a new insert data query
        $prefixes = "";
        $triples = "";
        foreach (explode("\n", $constrResult->getBody()) as $line)
            if (stripos($line, '@prefix') === 0 || stripos($line, 'prefix') === 0)
                $prefixes .= $line . "\n";
            else
                $triples .= "    " . $line . "\n";
        
        // And create a new temp graph with the result of the construct
        $logger->info("Creating temporary graph: <" . $respGraphUri . ">");
        $query = $prefixes . "\nINSERT DATA { \n  GRAPH <" . $respGraphUri . "> {\n" . $triples . "\n}}\n";
        if ($logger->isHandling(Logger::DEBUG))
            $logger->DEBUG("Creating temporary graph: <" . $respGraphUri . "> with insert data query:\n" . $query);
        $sparqlClient->update($query);
    }
    
    // Optional: calculate the number of triples in the temporary graphs
    if ($metro->isHandling(Logger::INFO)) {
        $nbTriplesQuery = "SELECT (COUNT(*) AS ?count) FROM <" . $apiGraphUri . "> WHERE { ?s ?p ?o }";
        $result = $sparqlClient->queryRaw($nbTriplesQuery, "application/sparql-results+json");
        $jsonResult = json_decode($result->getBody(), true);
        $metro->appendMessage($service, "No triples generated with JSON-LD profile", $jsonResult['results']['bindings'][0]['count']['value']);
        
        $nbTriplesQuery = "SELECT (COUNT(*) AS ?count) FROM <" . $respGraphUri . "> WHERE { ?s ?p ?o }";
        $result = $sparqlClient->queryRaw($nbTriplesQuery, "application/sparql-results+json");
        $jsonResult = json_decode($result->getBody(), true);
        $metro->appendMessage($service, "No triples generated with CONSTRUCT query", $jsonResult['results']['bindings'][0]['count']['value']);
    }
    
    // ------------------------------------------------------------------------------------
    // --- Run the client's SPARQL query against the response temporary graph
    // ------------------------------------------------------------------------------------
    
    $logger->notice('Evaluating client SPARQL query against temporary graph...');
    
    $result = $sparqlClient->queryRaw($sparqlQuery, $accept, $defaultGraphUri = $respGraphUri);
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
    $metro->appendTimer($service, "Total|API", 1, 2);
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
