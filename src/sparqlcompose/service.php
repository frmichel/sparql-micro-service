<?php
namespace frmichel\sparqlcompose;

/**
 */
require_once '../../vendor/autoload.php';

use Monolog\Logger;
use frmichel\sparqlms\common\Context;
use frmichel\sparqlms\common\Utils;
use Exception;
require_once '../common/Utils.php';
require_once '../common/Context.php';
require_once '../common/Configuration.php';

try {
    // ------------------------------------------------------------------------------------
    // --- Initializations
    // ------------------------------------------------------------------------------------

    // Init the context: read the global config.ini file & init the logger and SPARQL client
    $context = Context::getInstance(Logger::NOTICE, "--------- Starting SPARQL composer --------");
    $logger = $context->getLogger();
    $sparqlClient = $context->getSparqlClient();

    // Read HTTP headers
    list ($contentType, $accept) = Utils::getHttpHeaders();

    // Get the SPARQL query using either GET or POST methods
    $sparqlQuery = Utils::getSparqlQuery();
    if ($sparqlQuery == "")
        Utils::httpBadRequest("Empty SPARQL query.");
    $logger->notice("SPARQL query (html special chars encoded):\n" . htmlspecialchars($sparqlQuery));
    $context->setSparqlQuery($sparqlQuery);

    // --- Convert the SPARQL query to SPIN and load it into a temporary graph
    $spinInvocation = $context->getConfigParam('spin_endpoint') . '?arg=' . urlencode($sparqlQuery);
    $spinQueryGraph = $context->getConfigParam('root_url') . '/tempgraph-spin' . uniqid("-", true);
    $query = 'LOAD <' . $spinInvocation . '> INTO GRAPH <' . $spinQueryGraph . '>';
    if ($logger->isHandling(Logger::DEBUG))
        $logger->debug("SPARQL query converted to SPIN: \n" . file_get_contents($spinInvocation));
    $logger->info('Loading SPIN SPARQL query into temp graph <' . $spinQueryGraph . ">");
    $sparqlClient->update($query);

    // ------------------------------------------------------------------------------------
    // --- Matchmaking
    // ------------------------------------------------------------------------------------

    // --- Discover the services whose inputs are satisfied by the query
    $query = file_get_contents('resources/find_compatibles_services.sparql');
    $query = str_replace('{SpinQueryGraph}', $spinQueryGraph, $query);

    $logger->info('Looking for services whose inputs are satisfied by the query...');
    $jsonResult = Utils::runSparqlSelectQuery($query);
    $services = array();
    foreach ($jsonResult as $jsonResultN)
        $services[$jsonResultN['service']['value']] = $jsonResultN['shapesGraph']['value'];
    if ($logger->isHandling(Logger::INFO))
        $logger->info("Service whose inputs are satisfied by the SPARQL query: " . print_r($services, true));

    // Create a temporary graph where to store the result of the matchmaking of triples and services
    $matchmakingGraph = $context->getConfigParam('root_url') . '/tempgraph-matchmaking' . uniqid("-", true);

    // --- Prepare and exec the matchmaking query
    $query = file_get_contents('resources/matchmaking.sparql');
    $query = str_replace('{MatchmakingGraph}', $matchmakingGraph, $query);
    $query = str_replace('{SpinQueryGraph}', $spinQueryGraph, $query);
    $usingclauses = "";
    foreach ($services as $service => $shapesGraph)
        $usingclauses .= "USING <" . $service . "ServiceDescription>\n" . "USING NAMED <" . $shapesGraph . ">\n";
    $query = str_replace('{Using_Clauses}', $usingclauses, $query);
    $logger->info("Executing matchmaking query and storing result in temp graph: <" . $matchmakingGraph . ">...");
    if ($logger->isHandling(Logger::DEBUG))
        $logger->debug("Matchmaking SPARQL query: \n" . $query);

    $sparqlClient->update($query);
    if ($logger->isHandling(Logger::DEBUG)) {
        // Read the graph that we just generated - just for logging
        $result = $sparqlClient->queryRaw("CONSTRUCT WHERE { ?s ?p ?o }", "text/turtle", $namedGraphUri = $matchmakingGraph);
        $logger->debug("Matchmaking result graph: \n" . $result);
    }

    // ------------------------------------------------------------------------------------
    // --- Rewrite the client SPARQL query
    // ------------------------------------------------------------------------------------

    // -- Generate the SERVICE clauses corresponding to each SPARQL micro-service to invoke
    $genServiceClausesInvocation = $context->getConfigParam('sparql_compose_endpoint') . '?param=' . $matchmakingGraph;
    $serviceClauses = file_get_contents($genServiceClausesInvocation);

    // Repalce anything within the WHERE clause of the client SPARQL query with the SERVICE clauses
    preg_match('/(?i)where\s*\{((.|\R)*)\}[^}]*/', $sparqlQuery, $matches);
    $sparqlQueryRewritten = str_replace($matches[1], "\n\n".$serviceClauses, $sparqlQuery);
    if ($logger->isHandling(Logger::INFO))
        $logger->info("Rewritten SPARQL query: \n" . $sparqlQueryRewritten);

    // Drop the spin and matchmaking temporary graph
    $logger->info("Dropping graph: <" . $matchmakingGraph . ">");
    $sparqlClient->update("DROP SILENT GRAPH <" . $matchmakingGraph . ">");
    $logger->info("Dropping graph: <" . $spinQueryGraph . ">");
    $sparqlClient->update("DROP SILENT GRAPH <" . $spinQueryGraph . ">");

    // Run the rewritten query
    $logger->info("Executing rewritten SPARQL query...");
    $result = $sparqlClient->queryRaw($sparqlQueryRewritten, $accept);
    if ($logger->isHandling(Logger::INFO))
        foreach ($result->getHeaders() as $header => $headerVal)
            $logger->info('Received response header: ' . $header . ": " . $headerVal);

    // ------------------------------------------------------------------------------------
    // --- Return the HTTP response to the SPARQL client
    // ------------------------------------------------------------------------------------

    $logger->notice("Sending response with Content-Type: " . $result->getHeader('Content-Type'));
    header('Content-Type: ' . $result->getHeader('Content-Type'));
    header('Server: SPARQL-Micro-Service Composer');
    header('Access-Control-Allow-Origin: *');
    if ($logger->isHandling(Logger::DEBUG))
        $logger->debug("Sending response body: " . $result->getBody());
    print($result->getBody());

    $logger->notice("--------- Done - SPARQL µS composition --------");
    //
} catch (Exception $e) {
    try {
        $logger = Context::getInstance()->getLogger();
        $logger->error((string) $e . "\n");
        $logger->notice("Returning HTTP status 500.\n");
        $logger->notice("--------- Done - SPARQL µS composition --------");
    } catch (Exception $f) {
        print("Could not process the request. Error:\n" . (string) $e . "\n");
        print("Second exception caught:\n" . (string) $f . "\n");
    }
    http_response_code(500);
    print("Internal error: " . $e->getMessage() . "\n");
    exit(0);
}
?>
