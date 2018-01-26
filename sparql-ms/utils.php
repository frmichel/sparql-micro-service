<?php
    require_once 'vendor/autoload.php';

    use Monolog\Logger;
    use Monolog\Formatter\LineFormatter;
    use Monolog\Handler\RotatingFileHandler;
    use Monolog\Handler\StreamHandler;
    use ML\JsonLD\JsonLD;
    use ML\JsonLD\NQuads;
    use ML\JsonLD\Processor;

    function initLogger($level = Logger::INFO) {
        if (array_key_exists('SCRIPT_FILENAME', $_SERVER))
            $scriptName = basename($_SERVER['SCRIPT_FILENAME']);
        else
            $scriptName = basename(__FILE__);

        $handler = new RotatingFileHandler(__DIR__.'/logs/sms.log', 5, $level, true, 0666);
        $handler->setFormatter(new LineFormatter(null, null, true));
        $logger = new Logger($scriptName);
        $logger->pushHandler($handler);

        return $logger;
    }

    function initMetro($level = Logger::INFO) {
        if (array_key_exists('SCRIPT_FILENAME', $_SERVER))
            $scriptName = basename($_SERVER['SCRIPT_FILENAME']);
        else
            $scriptName = basename(__FILE__);

        $handler = new StreamHandler(__DIR__.'/logs/metro.csv', $level, true, 0666);
        $handler->setFormatter(new LineFormatter("%message% \n"));
        $logger = new Logger("");
        $logger->pushHandler($handler);

        return $logger;
    }

    /**
     * Add time measures to the metrology file in CSV format
     *
     * @param string $service the current service name
     * @param string $message
     * @param float  $time1 first time measure is seconds
     * @param float  $time2 second time measure is seconds
     */
    function appendMetro($service, $message, $time1, $time2 = 0) {
        global $metro;
        $t1 = number_format($time1, 4, $dec_point=",", $thousands_sep="");
        $t2 = number_format($time2, 4, $dec_point=",", $thousands_sep="");

        if ($time2 ==  0)
            $metro->info("$service; $message; $t1");
        else
            $metro->info("$service; $message; $t1; $t2");
    }

    /**
     * Check and log the Content-Type and Accept HTTP headers
     *
     * @return array(Content-Type, Accept)
     */
    function getHttpHeaders() {
        global $logger, $config;

        if (array_key_exists('CONTENT_TYPE', $_SERVER)) {
            $contentType = $_SERVER['CONTENT_TYPE'];
            $logger->info('Query HTTP header "Content-Type": '.$contentType);
        } else {
            $logger->info('Query HTTP header "Content-Type" undefined.');
            $contentType = "";
        }

        if (array_key_exists('HTTP_ACCEPT', $_SERVER)) {
            $accept = $_SERVER['HTTP_ACCEPT'];
            $logger->info('Query HTTP header "Accept": '.$accept);
        } else
            $logger->warn('Query HTTP header "Accept" undefined. Using: '.$config['default_mime_type']);

        return  array($contentType, $accept);
    }

    /**
     * Return an HTTP staus 400 with an error message and exit the script.
     */
    function badRequest($message) {
        global $logger;

        http_response_code(400); // Bad Request
        $logger->error($message);
        print("Erroneous request: ".$message);
        exit(0);
    }

    /**
     * Check and return the query parameters.
     * If any expected parameter in not found (in the regular case of an HTTP/HTTPS call)
     * the script returns an HTTP error 400 and exits.
     *
     * @param array $params associative array of parameters and default values
     * @return associative array of parameters and values read from the query string
     */
    function getQueryParameters($params) {
        global $logger;

        $result = array();
        foreach ($params as $paramName => $paramDefaultValue) {

            if (array_key_exists('HTTP_HOST', $_SERVER)) {
                // The service parameters are passed in the query string
                if (array_key_exists($paramName, $_REQUEST)) {
                    $paramValue = $_REQUEST[$paramName];
                    $result[$paramName] = $paramValue;
                    $logger->info("Query parameter '".$paramName."': ".$paramValue);
                } else
                    badRequest("Query parameter '".$paramName."' undefined.");
            } else {
                // Call from command line - Use default value
                $result[$paramName] = $paramDefaultValue;
                $logger->warning("Query parameter '".$paramName."' undefined. Using '".$paramDefaultValue."'.");
            }
        }

        return $result;
    }

    /**
     * Read a JSON content, apply a JSON-LD profile and
     * translate the result into NQuads
     *
     * @param string $json the URL of the JSON document to tranform
     * @param null|string|object|array $jsonldProfile the JSON-LD profile (context)
     * @return string NQuadsd serialized as a string
     */
    function translateJsonToNQuads($json, $jsonldProfile) {
        global $logger, $useCache;

        $apiResp = null;
        try {
            if ($useCache) {
                // Check if response is already in cache db
                $apiResp = readFromCache($json);
                if ($apiResp != null) {
                    if ($logger->isHandling(Logger::DEBUG))
                        $logger->debug("Retrieved JSON response from cache: \n".JsonLD::toString($apiResp));
                }
            }

            if ($apiResp == null) {
                // Query the Web API
                $apiResp = loadJsonDocument($json);
                if ($logger->isHandling(Logger::DEBUG))
                    $logger->debug("Web API JSON response: \n".$apiResp);

                // Store the result into the cache db
                if ($useCache) {
                    writeToCache($json, $apiResp);
                    if ($logger->isHandling(Logger::DEBUG))
                        $logger->debug("Stored JSON response into cache.");
                }
            }

            // Apply JSON-LD profile to the Web API response
            $jsonldResp = JsonLD::expandJsonAsJsonld($apiResp, $jsonldProfile);
            if ($logger->isHandling(Logger::DEBUG))
                $logger->debug("Web API JSON response translated to JSON-LD: \n".JsonLD::toString($jsonldResp));

            // Transform the JSON-LD to RDF NQuads
            $quads = JsonLD::expandedToRdf($jsonldResp);
            $nquads = new NQuads();
            $serializedQuads = $nquads->serialize($quads);
            if ($logger->isHandling(Logger::DEBUG))
                $logger->debug("NQuads serialized response:\n".$serializedQuads);

            return $serializedQuads;

        } catch (Exception $e) {
            $logger->warning((string)$e."\n");
            $logger->warning("Error when querying the API/transforming its response to JSON-LD. Returning empty result.");
            return array();
        }
    }

    /**
     * Read a JSON content given by its URL
     *
     * @param string $url the URL of the JSON document
     * @return string the result JSON content as a string
     */
    function loadJsonDocument($url) {
        global $logger;

        $streamContextOptions = array(
            'method'  => 'GET',
            'header'  => "Accept: application/json; q=0.9, */*; q=0.1\r\n".
                        // Some Web API require a User-Agent.
                        // E.g. MusicBrainz returns error 403 if there is none.
                        "User-Agent: SPARQL-Micro-Service\r\n",
            'timeout' => Processor::REMOTE_TIMEOUT,
            'ssl' => [ 'verify_peer' => true, 'verify_peer_name' => true, 'allow_self_signed'=> false ]
        );

        $context = stream_context_create(array(
            'http' => $streamContextOptions,
            'https' => $streamContextOptions
        ));

        $httpHeadersOffset = 0;

        stream_context_set_params($context, array('notification' =>
            function ($code, $severity, $msg, $msgCode, $bytesTx, $bytesMax) use (
                &$remoteDocument, &$http_response_header, &$httpHeadersOffset
            ) {
                if ($code === STREAM_NOTIFY_MIME_TYPE_IS) {
                    $remoteDocument->mediaType = $msg;
                } elseif ($code === STREAM_NOTIFY_REDIRECTED) {
                    $remoteDocument->documentUrl = $msg;
                    $remoteDocument->mediaType = null;

                    $httpHeadersOffset = count($http_response_header);
                }
            }
        ));

        if (false === ($jsonContent = @file_get_contents($url, false, $context))) {
            $logger->warning("Cannot load document ".$url."\n");
            $jsonContent = null;
        }

        return $jsonContent;
    }

    function readFromCache($query) {
        global $cacheDb;
        $found = $cacheDb->findOne(['hash' => hash("sha256", $query)]);
        if ($found != null)
            return $found['payload'];
        else
            return null;
    }

    function writeToCache($query, $resp) {
        global $cacheDb, $logger;
        try {
            $cacheDb->insertOne( [ 'hash' => hash("sha256", $query), 'query' => $query, 'payload' => $resp ] );
        } catch (Exception $e) {
            $logger->warning("Cannot write to cache db: ".(string)$e."\n");
        }
    }

?>