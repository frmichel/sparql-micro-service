<?php
namespace frmichel\sparqlcompose;

/**
 * Automatic composition of SPARQL micro-serivces to answer a SPARQL query.
 *
 * This script is a regular SPARQL endpoint. It first looks for services that could potentially
 * be used to answer the user query, just by looking at those whose input conditions are met.
 *
 * Then, each triple pattern is matched with one service, then each group of triple patterns is
 * turned into a SERVICE clause. Finally the user query is rewritten by replacing the WHERE
 * with the UNION of all the SERVICE clauses.
 *
 * Note that most of the logic is achieved by SPARQL query (involving LDScript extension).
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
    $context = Context::getInstance(Logger::DEBUG, "--------- Starting SPARQL composer --------");
    $logger = $context->getLogger();
    $sparqlClient = $context->getSparqlClient();
    
    if (! $context->hasConfigParam('spin_endpoint'))
        throw new Exception("Missing configuration property 'spin_endpoint'. Check config.ini.");
    if (! $context->hasConfigParam('sparql_compose_endpoint'))
        throw new Exception("Missing configuration property 'sparql_compose_endpoint'. Check config.ini.");
    if (! $context->hasConfigParam('error_on_unmatched_triples'))
        $context->setConfigParam('error_on_unmatched_triples', false);
    if (! $context->hasConfigParam('results_on_unmatched_triples'))
        $context->setConfigParam('results_on_unmatched_triples', true);
    
    // Read HTTP headers
    list ($contentType, $accept) = Utils::getHttpHeaders();
    
    // Get the SPARQL query using either GET or POST methods
    $sparqlQuery = Utils::getSparqlQuery();
    if ($sparqlQuery == "")
        Utils::httpBadRequest("Empty SPARQL query.");
    $logger->notice("SPARQL query (html special chars encoded):\n" . htmlspecialchars($sparqlQuery));
    $context->setSparqlQuery($sparqlQuery);
    
    // ------------------------------------------------------------------------------------
    // --- Convert the SPARQL query to a SPIN graph and turn blank nodes into explicit variables
    // ------------------------------------------------------------------------------------
    
    $spinInvocation = $context->getConfigParam('spin_endpoint') . '?arg=' . urlencode($sparqlQuery);
    $spinQueryGraph = $context->getConfigParam('root_url') . '/tempgraph-spin' . uniqid("-", true);
    $query = 'LOAD <' . $spinInvocation . '> INTO GRAPH <' . $spinQueryGraph . '>';
    if ($logger->isHandling(Logger::DEBUG))
        $logger->debug("SPARQL query converted to SPIN: \n" . file_get_contents($spinInvocation));
    $logger->info('Loading SPIN SPARQL query into temp graph <' . $spinQueryGraph . ">");
    $sparqlClient->update($query);
    
    // --- Create explicit variable names for terms of the SPARQL graph pattern that are simple blank nodes
    /*
     * For each BN, add a variable name only once. In particular the same BN can be used e.g. as a subject or object.
     * Therefore, it is not possible to create those variable names in a single query. Instead, we enrich the graph
     * alternatively for subject BNs, prodicate BNs and object BNs.
     *
     * Also, the var name is built from the BN identifier and a uniq id. But the same BN may be the subject or several
     * triples. So we must use the same uniq id for each use of the BN. This is why the uniq id is built here and not
     * within the SPARQL query using a rand() that would generate a new value for each use of the same BN.
     */
    $query = file_get_contents('resources/spin_query_explicit_bn.sparql');
    $query = str_replace('{SpinQueryGraph}', $spinQueryGraph, $query);
    $query = str_replace('{uniqid}', uniqid("_"), $query);
    
    $queryx = str_replace('{predicate}', "sp:subject", $query);
    $sparqlClient->update($queryx);
    $queryx = str_replace('{predicate}', "sp:predicate", $query);
    $sparqlClient->update($queryx);
    $queryx = str_replace('{predicate}', "sp:object", $query);
    $sparqlClient->update($queryx);
    
    // ------------------------------------------------------------------------------------
    // --- Discover the services whose inputs are satisfied by the query, so that only
    //     these ones will be tested in the matchmaking
    // ------------------------------------------------------------------------------------
    
    $query = file_get_contents('resources/find_compatibles_services.sparql');
    $query = str_replace('{SpinQueryGraph}', $spinQueryGraph, $query);
    
    $logger->info('Looking for services whose inputs are satisfied by the query...');
    $jsonResult = Utils::runSparqlSelectQuery($query);
    $potentialServices = array();
    foreach ($jsonResult as $jsonResultN)
        $potentialServices[$jsonResultN['service']['value']] = $jsonResultN['shapesGraph']['value'];
    if ($logger->isHandling(Logger::INFO))
        $logger->info("Services whose inputs are satisfied by the SPARQL query: " . print_r($potentialServices, true));
    
    // ------------------------------------------------------------------------------------
    // --- Find out which TPs cannot be matched by any service using the "unmatched triples" query
    // ------------------------------------------------------------------------------------
    
    $fromClauses = "";
    foreach ($potentialServices as $service => $shapesGraph)
        $fromClauses .= "FROM <" . $service . "ServiceDescription>\n" . "FROM NAMED <" . $shapesGraph . ">\n";
    
    $query = file_get_contents('resources/matchmaking_failed.sparql');
    $query = str_replace('{SpinQueryGraph}', $spinQueryGraph, $query);
    $query = str_replace('{FromServices_Clauses}', $fromClauses, $query);
    
    $logger->info("Executing unmatched triples query...");
    $jsonResult = Utils::runSparqlSelectQuery($query);
    if (sizeof($jsonResult) != 0) {
        $unmatchedTriples = "";
        foreach ($jsonResult as $jsonResultN)
            $unmatchedTriples .= "\n" . $jsonResultN['tpStr']['value'];
        $logger->warn("The following triple patterns were not matched with any service:" . $unmatchedTriples);
        if ($context->getConfigParam('error_on_unmatched_triples'))
            Utils::httpUnprocessableEntity("Some triple patterns could not be matched with any service.");
    }
    
    // ------------------------------------------------------------------------------------
    // --- Matchmaking: find out which TPs are matched by which services, and 
    //     generate a federated SPARQL query with all the micro-serivces invocations
    // ------------------------------------------------------------------------------------
    
    // Create a temporary graph where to store the result of the matchmaking of triples and services
    $matchmakingGraph = $context->getConfigParam('root_url') . '/tempgraph-matchmaking' . uniqid("-", true);
    
    $query = file_get_contents('resources/matchmaking.sparql');
    $query = str_replace('{MatchmakingGraph}', $matchmakingGraph, $query);
    $query = str_replace('{SpinQueryGraph}', $spinQueryGraph, $query);
    $usingClauses = "";
    foreach ($potentialServices as $service => $shapesGraph)
        $usingClauses .= "USING <" . $service . "ServiceDescription>\n" . "USING NAMED <" . $shapesGraph . ">\n";
    $query = str_replace('{Using_Clauses}', $usingClauses, $query);
    if ($logger->isHandling(Logger::DEBUG))
        $logger->debug("Matchmaking SPARQL query: \n" . $query);
    
    $logger->info("Executing matchmaking query and storing result in graph: <" . $matchmakingGraph . ">...");
    $sparqlClient->update($query);
    if ($logger->isHandling(Logger::DEBUG)) {
        // Read the graph that we just generated - just for logging
        $result = $sparqlClient->queryRaw("CONSTRUCT WHERE { ?s ?p ?o }", "text/turtle", $namedGraphUri = $matchmakingGraph);
        // $logger->debug("Matchmaking result graph:\n" . $result);
    }
    
    // ------------------------------------------------------------------------------------
    // --- Generate and execute the federated client SPARQL query
    // ------------------------------------------------------------------------------------
    
    // -- Generate the SERVICE clauses corresponding to each SPARQL micro-service to invoke
    $genFedQuery = $context->getConfigParam('sparql_compose_endpoint') . '?param=' . $matchmakingGraph;
    $evalFedQuery = file_get_contents($genFedQuery);
    
    $evalFedQueryGraph = $context->getConfigParam('root_url') . '/tempgraph-eval' . uniqid("-", true);
    $evalFedQuery = str_replace('{EvalFedQueryGraph}', $evalFedQueryGraph, $evalFedQuery);
    if ($logger->isHandling(Logger::DEBUG))
        $logger->debug("Federated SPARQL query: \n" . $evalFedQuery);
    $logger->info('Evaluating federated query into temporary graph <' . $evalFedQueryGraph . ">");
    $sparqlClient->update($evalFedQuery);
    
    if ($logger->isHandling(Logger::DEBUG)) {
        // Read the graph that we just generated - just for logging
        $result = $sparqlClient->queryRaw("CONSTRUCT WHERE { ?s ?p ?o }", "text/turtle", $namedGraphUri = $evalFedQueryGraph);
        // $logger->debug("Federated query result graph: \n" . $result);
    }
    
    // ------------------------------------------------------------------------------------
    // --- Evaluate the client SPARQL query agaisnt the result of the federated query
    // ------------------------------------------------------------------------------------
    
    // Run the rewritten query againt the fed query result graph
    $result = $sparqlClient->queryRaw($sparqlQuery, $accept, $namedGraphUri = $evalFedQueryGraph);
    if ($logger->isHandling(Logger::INFO))
        foreach ($result->getHeaders() as $header => $headerVal)
            $logger->info('Received response header: ' . $header . ": " . $headerVal);
    
    // Drop the temporary graphs
    $logger->info("Dropping graph: <" . $spinQueryGraph . ">");
    $sparqlClient->update("DROP SILENT GRAPH <" . $spinQueryGraph . ">");
    $logger->info("Dropping graph: <" . $matchmakingGraph . ">");
    $sparqlClient->update("DROP SILENT GRAPH <" . $matchmakingGraph . ">");
    $logger->info("Dropping graph: <" . $evalFedQueryGraph . ">");
    $sparqlClient->update("DROP SILENT GRAPH <" . $evalFedQueryGraph . ">");
    
    // ------------------------------------------------------------------------------------
    // --- Return the HTTP response to the SPARQL client
    // ------------------------------------------------------------------------------------
    
    $logger->notice("Sending response with Content-Type: " . $result->getHeader('Content-Type'));
    header('Content-Type: ' . $result->getHeader('Content-Type'));
    header('Server: SPARQL-Micro-Service Composer');
    header('Access-Control-Allow-Origin: *');
    $logger->info("Sending response body.");
    // if ($logger->isHandling(Logger::DEBUG))
    // $logger->debug("Sending response body: " . $result->getBody());
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
