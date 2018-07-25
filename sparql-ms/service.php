<?php
/**
 * This script implements the core logic of SPARQL micro-services.
 *
 */
require_once 'vendor/autoload.php';
require_once 'utils.php';
require_once 'Context.php';

use Monolog\Logger;

try {
    // Set level to Logger::INFO to activate metrology, Logger::WARNING or higher to deactivate
    $metro = initMetro(Logger::WARNING);
    if ($metro->isHandling(Logger::INFO))
        $timeStart = microtime(true);

    // Init the context: read the main config file and the service specific config file
    $context = Context::getInstance('config.ini', Logger::DEBUG);
    $logger = $context->getLogger();
    $useCache = $context->getConfigParam('use_cache');

    // Read the mandatory arguments from the HTTP query string
    list ($service, $querymode, $sparqlQuery) = array_values($context->getQueryStringArgs($context->getConfigParam('parameter')));
    $logger->info("Query parameter (with html special chars encoded) 'service': " . htmlspecialchars($service));
    $logger->info("Query parameter (with html special chars encoded) 'querymode': " . htmlspecialchars($querymode));
    $logger->info("Query parameter (with html special chars encoded) 'sparqlQuery': " . htmlspecialchars($sparqlQuery));

    // --- Check and log HTTP headers and query string
    list ($contentType, $accept) = getHttpHeaders();

    if (array_key_exists('QUERY_STRING', $_SERVER)) {
        if ($logger->isHandling(Logger::DEBUG))
            $logger->debug('Query string: ' . $_SERVER['QUERY_STRING']);
    } else
        badRequest("HTTP error, no query string provided.");

    // --- Format the Web API query string
    if (file_exists($service . '/service.php')) {
        // Web API query string will be formatted by the custom service script
        require $service . '/service.php';

        if (! isset($apiQuery))
            throw new Exception('Variable "$apiQuery" does not exist. Should haver bee set by script ' . $service . '/service.php.');
    } else {
        // Read the service-specific arguments from the HTTP query string
        $customArgs = $context->getQueryStringArgs($context->getConfigParam('custom_parameter'));

        $apiQuery = $context->getConfigParam('api_query');
        foreach ($customArgs as $parName => $parVal)
            $apiQuery = str_replace('{' . $parName . '}', urlencode($parVal), $apiQuery);
    }
    $logger->info("Web API query string: \n" . $apiQuery);

    // ------------------------------------------------------------------------------------
    // --- Call the Web API service, apply the JSON-LD profile and translate to NQuads
    // ------------------------------------------------------------------------------------

    if ($metro->isHandling(Logger::INFO))
        $before = microtime(true);

    if ($apiQuery == "") // Query string set to empty string in case an error occured.
        $serializedQuads = "";
    else
        $serializedQuads = translateJsonToNQuads($apiQuery, $service . '/profile.jsonld');

    if ($metro->isHandling(Logger::INFO))
        $apiTime = microtime(true) - $before;

    // ------------------------------------------------------------------------------------
    // --- Populate the temporary graph
    // ------------------------------------------------------------------------------------

    // URI of the temporary work graph
    $graphUri = 'http://sms.i3s.unice.fr/graph' . uniqid("-", true);

    // Create the temporary graph by clearing it
    $sparqlClient = new EasyRdf_Sparql_Client($context->getConfigParam('sparql_endpoint'));
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

    // Optional: calculate the number of triples in the temporary graph
    if ($metro->isHandling(Logger::INFO)) {
        $nbTriples = "select (count(*) as ?count) where { ?s ?p ?o }";
        $result = $sparqlClient->queryRaw($nbTriples, $accept, $namedGraphUri = $graphUri);
        $metro->info("$service; No triples; " . $result->getBody());
    }

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
    $sparqlClient = new EasyRdf_Sparql_Client($context->getConfigParam('sparql_endpoint'));
    if ($logger->isHandling(Logger::DEBUG))
        $logger->debug("Dropping graph: <" . $graphUri . ">");
    $query = "DROP SILENT GRAPH <" . $graphUri . ">\n";
    $sparqlClient->update($query);
    $logger->info("--------- Done --------");

    if ($metro->isHandling(Logger::INFO))
        appendMetro($service, "API|Total", $apiTime, microtime(true) - $timeStart);
} catch (Exception $e) {
    try {
        $logger = Context::getInstance()->getLogger();
        $logger->error((string) $e . "\n");
        $logger->info("Returning error 500.\n");
        $logger->info("--------- Done --------");
    } catch (Exception $f) {
        print("Could not process the request. Error:\n".(string)$e."\n");
        print("Second exception caught:\n".(string)$f."\n");
    }
    http_response_code(500);
    exit(0);
}
?>
