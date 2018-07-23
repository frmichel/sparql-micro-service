<?php
    /**
     * This script implements the core logic of SPARQL micro-services.
     *
     */

    require_once 'vendor/autoload.php';
    require_once 'utils.php';

    use Monolog\Logger;

    try {
        // Set global debug level here. Use WARNING or ERROR is production environment.
        $logger = initLogger(Logger::INFO);

        // Set level to Logger::INFO to activate metrology, Logger::WARNING or highier to deactivate
        $metro = initMetro(Logger::WARNING);
        if ($metro->isHandling(Logger::INFO)) $timeStart = microtime(true);

        // ------------------------------------------------------------------------------------
        // --- Read generic configuration and check query parameters
        // ------------------------------------------------------------------------------------
        $config = parse_ini_file('config.ini');
        if (! $config)
            throw new Exception("Cannot read configuration file config.ini.");
        if (! array_key_exists('sparql_endpoint', $config))
            throw new Exception("Missing configuration property 'sparql_endpoint'. Check config.ini.");
        if (! array_key_exists('default_mime_type', $config))
            throw new Exception("Missing configuration property 'default_mime_type'. Check config.ini.");
        if (! array_key_exists('parameter', $config))
            throw new Exception("Missing configuration property 'parameter'. Check config.ini.");

        // Read mandatory input parameters of the SPARQL micro service
        list($service, $querymode, $sparqlQuery) = array_values(getQueryParameters($config['parameter']));

        if ($querymode != 'sparql' && $querymode != 'ld')
            throw new Exception("Invalid parameter 'querymode': should be one of 'sparql' or 'lod'.");

        // --- Check and log HTTP headers
        list($contentType, $accept) = getHttpHeaders();
        if (array_key_exists('QUERY_STRING', $_SERVER)) {
            if ($logger->isHandling(Logger::DEBUG))
                $logger->debug('Query string: '.$_SERVER['QUERY_STRING']);
        } else
            badRequest("HTTP error, no query string provided.");

        // Set default namespaces. See other existing default namespaces in EasyRdf/Namespace.php
        if (array_key_exists('namespace', $config))
            foreach ($config['namespace'] as $nsName => $nsVal) {
                if ($logger->isHandling(Logger::DEBUG))
                    $logger->debug('Adding namespace: '.$nsName. " = ".$nsVal);
                EasyRdf_Namespace::set($nsName, $nsVal);
            }

        // ------------------------------------------------------------------------------------
        // --- Read service-specific configuration
        // ------------------------------------------------------------------------------------

        if (file_exists($service.'/service.php')) {
            // No config.ini file but script service.php instead.
            require $service.'/service.php';

            if (! isset($apiQuery))
                throw new Exception('Missing variable "$apiQuery". Check <service>/service.php.');
            if (! isset($cacheExpiresAfter))
                $cacheExpirationSec = 2592000;

            $logger->info("Web API query string: \n".$apiQuery);

        } else {
            // Read the service config.ini file
            $customConfigFile = $service.'/config.ini';
            $customConfig = parse_ini_file($customConfigFile);
            if (! $customConfig)
                throw new Exception("Cannot read configuration file ".$customConfigFile);
            if (! array_key_exists('api_query', $customConfig))
                throw new Exception("Missing configuration property 'api_query'. Check <service>/config.ini.");
            if (! array_key_exists('parameter', $customConfig))
                throw new Exception("Missing configuration property 'parameter'. Check <service>/config.ini.");

            # Read the (optional) cache expiration period
            $cacheExpirationSec = array_key_exists('cache_expires_after', $customConfig) ? $customConfig['cache_expires_after'] : 2592000;

            // Read the service-specific parameters
            $customParams = getQueryParameters($customConfig['parameter']);

            // In the Web API query string, replace placeholders {param} with their values
            $apiQuery = $customConfig['api_query'];
            foreach ($customParams as $parName => $parVal)
                $apiQuery = str_replace('{'.$parName.'}', urlencode($parVal), $apiQuery);
            $logger->info("Web API query string: \n".$apiQuery);
        }

        // ------------------------------------------------------------------------------------
        // --- Initialize the cache db client (MongoDB client)
        //     Global variables:
        //        $useCache, $cacheDb, $cacheExpiresAfter
        // ------------------------------------------------------------------------------------

        $useCache = array_key_exists('use_cache', $config) ? $config['use_cache'] : false;
        if ($useCache) {
            $cacheEndpoint = array_key_exists('cache_endpoint', $config) ? $config['cache_endpoint'] : "mongodb://localhost:27017";
            $client = new MongoDB\Client($cacheEndpoint);

            $cacheDbName = array_key_exists('cache_db_name', $config) ? $config['cache_db_name'] : "sparql_micro_service";
            $cacheDb = $client->selectCollection($cacheDbName, 'cache');

            $cacheExpiresAfter = new DateInterval('PT'.$cacheExpirationSec.'S');
        }

        // ------------------------------------------------------------------------------------
        // --- Call the Web API service, apply the JSON-LD profile and translate to NQuads
        // ------------------------------------------------------------------------------------

        if ($metro->isHandling(Logger::INFO)) $before = microtime(true);

        if ($apiQuery == "") // Query string set to empty string in case an error occured.
            $serializedQuads = "";
        else
            $serializedQuads = translateJsonToNQuads($apiQuery, $service.'/profile.jsonld');

        if ($metro->isHandling(Logger::INFO)) $apiTime = microtime(true) - $before;

        // ------------------------------------------------------------------------------------
        // --- Populate the temporary graph
        // ------------------------------------------------------------------------------------

        // URI of the temporary work graph
        $graphUri = 'http://sms.i3s.unice.fr/graph'.uniqid("-", true);

        // Create the temporary graph by clearing it
        $sparqlClient = new EasyRdf_Sparql_Client($config['sparql_endpoint']);
        if ($logger->isHandling(Logger::DEBUG))
            $logger->debug("Creating graph: <".$graphUri.">");
        $query ="CLEAR SILENT GRAPH <".$graphUri.">\n";
        $sparqlClient->update($query);

        // Insert the quads obtained from the Web API
        $query ="INSERT DATA { GRAPH <".$graphUri."> {\n".$serializedQuads."\n}}\n";
        $sparqlClient->update($query);

        // Add the triples for which this SPARQL service is meant
        $sparqlInsert = $service.'/insert.sparql';
        if ($querymode == 'sparql' && file_exists($sparqlInsert)) {
            $query = "WITH <".$graphUri.">\n".file_get_contents($sparqlInsert);
            $logger->info("SPARQL INSERT query file: ".$sparqlInsert);
            if ($logger->isHandling(Logger::DEBUG))
                $logger->debug("Generating triples with INSERT query:\n".$query);
            $sparqlClient->update($query);
        }

        // ------------------------------------------------------------------------------------
        // --- Run the SPARQL query against temporary graph
        // ------------------------------------------------------------------------------------

        if ($querymode == 'sparql') {
            $logger->info('Evaluating original SPARQL query against temporary graph...');
        } elseif ($querymode == 'ld') {
            $sparqlConstruct = $service.'/construct.sparql';
            if (file_exists($sparqlConstruct)) {
                $sparqlQuery = file_get_contents($sparqlConstruct);
                $logger->info("SPARQL CONSTRUCT query file: ".$sparqlConstruct);
                if ($logger->isHandling(Logger::DEBUG))
                    $logger->debug("Generating triples with CONSTRUCT query:\n".$sparqlQuery);
            } else
                throw new Exception("LD mode required but no SPARQL CONSTRUCT query is defined.");
        } // else: exception already thrown above

        // Optional: calculate the number of triples in the temporary graph
        if ($metro->isHandling(Logger::INFO)) {
            $nbTriples = "select (count(*) as ?count) where { ?s ?p ?o }";
            $result = $sparqlClient->queryRaw($nbTriples, $accept, $namedGraphUri = $graphUri);
            $metro->info("$service; No triples; ".$result->getBody());
        }

        // Run the query against the temporary graph
        $result = $sparqlClient->queryRaw($sparqlQuery, $accept, $namedGraphUri = $graphUri);
        if ($logger->isHandling(Logger::DEBUG))
            foreach($result->getHeaders() as $header => $headerVal)
                $logger->debug('Received response header: '.$header.": ".$headerVal);

        // ------------------------------------------------------------------------------------
        // --- Return the HTTP response to the SPARQL client
        // ------------------------------------------------------------------------------------

        $logger->info("Sending response Content-Type: ".$result->getHeader('Content-Type'));
        header('Content-Type: '.$result->getHeader('Content-Type'));
        header('Server: SPARQL-Micro-Service');
        header('Access-Control-Allow-Origin: *');
        if ($logger->isHandling(Logger::DEBUG))
            $logger->debug("Sending response body: ".$result->getBody());
        print($result->getBody());

        // Drop the temporary graph
        $sparqlClient = new EasyRdf_Sparql_Client($config['sparql_endpoint']);
        if ($logger->isHandling(Logger::DEBUG))
            $logger->debug("Dropping graph: <".$graphUri.">");
        $query ="DROP SILENT GRAPH <".$graphUri.">\n";
        $sparqlClient->update($query);
        $logger->info("--------- Done --------");

        if ($metro->isHandling(Logger::INFO))
            appendMetro($service, "API|Total", $apiTime, microtime(true) - $timeStart);

    } catch (Exception $e) {
        $logger->error((string)$e."\n");
        $logger->info("Returning error 500.\n");
        http_response_code(500);
        //print("Could not process the request. Error:\n".$e->getMessage());
        $logger->info("--------- Done --------");
        exit(0);
    }
?>
