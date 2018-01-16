<?php
    require_once 'vendor/autoload.php';

    use Monolog\Logger;
    use Monolog\Formatter\LineFormatter;
    use Monolog\Handler\RotatingFileHandler;
    use Monolog\Handler\StreamHandler;
    use ML\JsonLD\JsonLD;
    use ML\JsonLD\NQuads;

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
     * @param string|object|array $json the JSON document to tranform
     * @param null|string|object|array $jsonldProfile the JSON-LD profile (context)
     * @return string NQuadsd serialized as a string
     */
    function translateJsonToNQuads($json, $jsonldProfile) {
        global $logger;

        try {
            // Call the service and apply JSON-LD profile to the JSON response
            $apiResp = JsonLD::expandJsonAsJsonld($json, $jsonldProfile);
            if ($logger->isHandling(Logger::DEBUG))
                $logger->debug("JSON response: \n".JsonLD::toString($apiResp));
        } catch (Exception $e) {
            $logger->warning((string)$e."\n");
            $logger->warning("Error when querying the API or when transforming its response into JSON-LD. Returning empty result.");
            $apiResp = array();
        }

        // Transform the JSON-LD to RDF NQuads
        $quads = JsonLD::expandedToRdf($apiResp);
        $nquads = new NQuads();
        $serializedQuads = $nquads->serialize($quads);
        if ($logger->isHandling(Logger::DEBUG))
            $logger->debug("NQuads serialized response:\n".$serializedQuads);

        return $serializedQuads;
    }

?>