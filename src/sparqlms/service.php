<?php
namespace frmichel\sparqlms;

/**
 * This script implements the core logic of SPARQL micro-services.
 */
require_once '../../vendor/autoload.php';

use Monolog\Logger;
use Exception;
require_once 'utils.php';
require_once 'Context.php';
require_once 'Configuration.php';
require_once 'Metrology.php';
require_once 'Cache.php';

try {
    // ------------------------------------------------------------------------------------
    // --- Initializations
    // ------------------------------------------------------------------------------------
    
    // Metrology: set level to Logger::INFO to activate metrology, Logger::WARNING or higher to deactivate
    $metro = Metrology::getInstance(Logger::WARNING);
    $metro->startTimer(1);
    
    // Init the context: read the global and service-specific config.ini files, init the cache and logger
    $context = Context::getInstance(Logger::INFO);
    $logger = $context->getLogger();
    $useCache = $context->getConfigParam('use_cache');
    
    // ------------------------------------------------------------------------------------
    // --- Parse the HTTP query, retrieve arguments
    // ------------------------------------------------------------------------------------
    
    // Read HTTP headers
    list ($contentType, $accept) = getHttpHeaders();
    
    // Read the mandatory arguments from the HTTP query string
    if (array_key_exists('QUERY_STRING', $_SERVER)) {
        if ($logger->isHandling(Logger::DEBUG))
            $logger->debug('Query string: ' . $_SERVER['QUERY_STRING']);
    } else
        httpBadRequest("HTTP error, no query string provided.");
    
    list ($service, $querymode) = array_values(getQueryStringArgs($context->getConfigParam('parameter')));
    $logger->info("Query parameter (html special chars encoded) 'service': " . htmlspecialchars($service));
    $logger->info("Query parameter (html special chars encoded) 'querymode': " . htmlspecialchars($querymode));
    
    // Get the SPARQL query using either GET or POST methods
    $sparqlQuery = getSparqlQuery();
    if ($sparqlQuery == "" && $querymode == 'sparql')
        httpBadRequest("Empty SPARQL query.");
    else
        $logger->info("SPARQL query (html special chars encoded): " . htmlspecialchars($sparqlQuery));
    
    // ------------------------------------------------------------------------------------
    // --- Build the Web API query string and call the service
    // ------------------------------------------------------------------------------------
    
    // Format the Web API query string
    if (file_exists($service . '/service.php')) {
        // Web API query string will be formatted by the custom service script
        require $service . '/service.php';
        if (! isset($apiQuery))
            throw new Exception('Variable "$apiQuery" does not exist. Should haver been set by script ' . $service . '/service.php.');
    } else {
        // Read the service-specific arguments from the HTTP query string
        $customArgs = getQueryStringArgs($context->getConfigParam('custom_parameter'));
        if ($logger->isHandling(Logger::DEBUG))
            $logger->debug("Service arguments: " . print_r($customArgs, TRUE));
        
        $apiQuery = $context->getConfigParam('api_query');
        foreach ($customArgs as $parName => $parVal)
            $apiQuery = str_replace('{' . $parName . '}', urlencode($parVal), $apiQuery);
    }
    $logger->info("Web API query string: \n" . $apiQuery);
    
    // Call the Web API service, apply the JSON-LD profile and translate to NQuads
    $metro->startTimer(2);
    if ($apiQuery == "") // Query string set to empty string in case an error occured.
        $serializedQuads = "";
    else
        $serializedQuads = translateJsonToNQuads($apiQuery, $service . '/profile.jsonld');
    $metro->stopTimer(2);
    
    // ------------------------------------------------------------------------------------
    // --- Populate the temporary graph
    // ------------------------------------------------------------------------------------
    
    // URI of the temporary work graph
    $graphUri = $context->getConfigParam('root_url') . '/tempgraph' . uniqid("-", true);
    
    // Create the temporary graph by clearing it
    $sparqlClient = $context->getSparqlClient();
    if ($logger->isHandling(Logger::DEBUG))
        $logger->debug("Creating graph: <" . $graphUri . ">");
    $query = "CLEAR SILENT GRAPH <" . $graphUri . ">\n";
    $sparqlClient->update($query);
    
    // Insert the quads obtained from the Web API
    $query = "INSERT DATA { GRAPH <" . $graphUri . "> {\n" . $serializedQuads . "\n}}\n";
    $sparqlClient->update($query);
    
    // Add the triples for which this SPARQL service is meant
    $sparqlInsert = $service . '/insert.sparql';
    if ($querymode == 'sparql' && file_exists($sparqlInsert)) {
        $query = "WITH <" . $graphUri . ">\n" . file_get_contents($sparqlInsert);
        $logger->info("SPARQL INSERT query file: " . $sparqlInsert);
        if ($logger->isHandling(Logger::DEBUG))
            $logger->debug("Generating triples with INSERT query:\n" . $query);
        $sparqlClient->update($query);
    }
    
    // Optional: calculate the number of triples in the temporary graph
    if ($metro->isHandling(Logger::INFO)) {
        $nbTriplesQuery = "select (count(*) as ?count) where { ?s ?p ?o }";
        $result = $sparqlClient->queryRaw($nbTriplesQuery, "application/sparql-results+json", $namedGraphUri = $graphUri);
        $jsonResult = json_decode($result->getBody(), true);
        $metro->appendMessage($service, "No triples", $jsonResult['results']['bindings'][0]['count']['value']);
    }
    
    // ------------------------------------------------------------------------------------
    // --- Run the SPARQL query against temporary graph
    // ------------------------------------------------------------------------------------
    
    if ($querymode == 'sparql') {
        $logger->info('Evaluating SPARQL query against temporary graph...');
    } elseif ($querymode == 'ld') {
        $sparqlConstruct = $service . '/construct.sparql';
        if (file_exists($sparqlConstruct)) {
            $sparqlQuery = file_get_contents($sparqlConstruct);
            $logger->info("SPARQL CONSTRUCT query file: " . $sparqlConstruct);
            if ($logger->isHandling(Logger::DEBUG))
                $logger->debug("Generating triples with CONSTRUCT query:\n" . $sparqlQuery);
        } else
            throw new Exception("LD mode required but no SPARQL CONSTRUCT query is defined.");
    } // else: exception already thrown above
      
    // Run the query against the temporary graph
    $result = $sparqlClient->queryRaw($sparqlQuery, $accept, $namedGraphUri = $graphUri);
    if ($logger->isHandling(Logger::DEBUG))
        foreach ($result->getHeaders() as $header => $headerVal)
            $logger->debug('Received response header: ' . $header . ": " . $headerVal);
    
    // ------------------------------------------------------------------------------------
    // --- Return the HTTP response to the SPARQL client
    // ------------------------------------------------------------------------------------
    
    $logger->info("Sending response Content-Type: " . $result->getHeader('Content-Type'));
    header('Content-Type: ' . $result->getHeader('Content-Type'));
    header('Server: SPARQL-Micro-Service');
    header('Access-Control-Allow-Origin: *');
    if ($logger->isHandling(Logger::DEBUG))
        $logger->debug("Sending response body: " . $result->getBody());
    print($result->getBody());
    
    // Drop the temporary graph
    if ($logger->isHandling(Logger::DEBUG))
        $logger->debug("Dropping graph: <" . $graphUri . ">");
    $query = "DROP SILENT GRAPH <" . $graphUri . ">\n";
    $sparqlClient->update($query);
    $logger->info("--------- Done --------");
    
    $metro->stopTimer(1);
    $metro->appendTimer($service, "Total|API", 1, 2);
} catch (Exception $e) {
    try {
        $logger = Context::getInstance()->getLogger();
        $logger->error((string) $e . "\n");
        $logger->info("Returning error 500.\n");
        $logger->info("--------- Done --------");
    } catch (Exception $f) {
        print("Could not process the request. Error:\n" . (string) $e . "\n");
        print("Second exception caught:\n" . (string) $f . "\n");
    }
    http_response_code(500);
    exit(0);
}
?>
