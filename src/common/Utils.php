<?php
namespace frmichel\sparqlms\common;

use ML\JsonLD\JsonLD;
use ML\JsonLD\NQuads;
use ML\JsonLD\Processor;
use Monolog\Logger;
use Exception;

/**
 * Utility class.
 * Provides methods related to HTTP management + custom arguments extraction
 *
 * @author fmichel
 */
class Utils
{

    /**
     * Check and log the Content-Type and Accept HTTP headers
     *
     * @return array (Content-Type, Accept)
     */
    static public function getHttpHeaders()
    {
        global $context;
        $logger = $context->getLogger("Utils");

        if (array_key_exists('CONTENT_TYPE', $_SERVER)) {
            $contentType = $_SERVER['CONTENT_TYPE'];
            $logger->notice('Query HTTP header "Content-Type": ' . $contentType);
        } else {
            $logger->notice('Query HTTP header "Content-Type" undefined.');
            $contentType = "";
        }

        if (array_key_exists('HTTP_ACCEPT', $_SERVER)) {
            $accept = $_SERVER['HTTP_ACCEPT'];
            $logger->notice('Query HTTP header "Accept": ' . $accept);
        } else
            $logger->warning('Query HTTP header "Accept" undefined. Using: ' . $context->getConfigParam('default_mime_type'));

        return array(
            $contentType,
            $accept
        );
    }

    /**
     * Return an HTTP staus 400 with an error message and exit the script.
     *
     * @param string $message
     *            error message returned
     */
    static public function httpBadRequest($message)
    {
        global $context;
        $logger = $context->getLogger("Utils");

        header('Access-Control-Allow-Origin: *');
        http_response_code(400); // Bad Request
        $logger->warn($message);
        print("Erroneous request: " . $message . "\n");
        $logger->notice("--------- Done - SPARQL µS execution --------");
        exit(0);
    }

    /**
     * Return an HTTP staus 405 with an error message and exit the script.
     *
     * @param string $message
     *            error message returned
     */
    static public function httpMethodNotAllowed($message)
    {
        global $context;
        $logger = $context->getLogger("Utils");

        header('Access-Control-Allow-Origin: *');
        http_response_code(405); // Method Not Allowed
        $logger->warn($message);
        print("Erroneous request: " . $message . "\n");
        $logger->notice("--------- Done - SPARQL µS execution --------");
        exit(0);
    }

    /**
     * Return an HTTP staus 422 with an error message and exit the script.
     *
     * @see https://httpstatuses.com/422
     *
     * @param string $message
     *            error message returned
     */
    static public function httpUnprocessableEntity($message)
    {
        global $context;
        $logger = $context->getLogger("Utils");

        header('Access-Control-Allow-Origin: *');
        http_response_code(422); // Unprocessable entity
        $logger->warn($message);
        print("Invalid request: " . $message . "\n");
        $logger->notice("--------- Done - SPARQL µS execution --------");
        exit(0);
    }

    /**
     * Read a JSON content, apply a JSON-LD profile and
     * translate the result into NQuads
     *
     * @param string $jsonUrl
     *            the URL of the JSON document to tranform
     * @param null|string|object|array $jsonldProfile
     *            the JSON-LD profile (context)
     * @return string NQuadsd serialized as a string
     * @throws Exception in case an error occurs
     */
    static public function translateJsonToNQuads($jsonUrl, $jsonldProfile)
    {
        global $context;
        $logger = $context->getLogger("Utils");
        $useCache = $context->useCache();
        $cache = $context->getCache();

        $apiResp = null;
        try {
            $cacheHit = false;
            if ($useCache) {
                // Check if response is already in cache db
                $apiResp = $cache->read($jsonUrl);
                if ($apiResp != null) {
                    $cacheHit = true;
                    $logger->info("The JSON response was retrieved from cache.");
                }
            }

            if (! $cacheHit) {
                $logger->info("JSON response not found in cache or cache not active => will execute the Web API query.");

                // Query the Web API
                if ($context->hasConfigParam("http_header")) {
                    if ($logger->isHandling(Logger::DEBUG))
                        $logger->debug("Additional HTTP headers: " . print_r($context->getConfigParam("http_header"), true));
                    $apiResp = self::loadJsonDocument($jsonUrl, $context->getConfigParam("http_header"));
                } else
                    $apiResp = self::loadJsonDocument($jsonUrl);
            }

            if ($apiResp == null)
                throw new Exception("Web API query failed.");
            else {
                if ($logger->isHandling(Logger::DEBUG))
                    $logger->debug("Web API JSON response: \n" . $apiResp);

                // Store the result into the cache db
                if ($useCache && ! $cacheHit) {
                    $cache->write($jsonUrl, $apiResp, $context->getService());
                    $logger->info("Stored JSON response into cache.");
                }

                // ------ Sanity measures ----

                // Remove unicodecontrol characters (0000 to 001f)
                $apiResp = preg_replace("/\\\\u000./", "?", $apiResp);
                $apiResp = preg_replace("/\\\\u001./", "?", $apiResp);
                // Remove \n and \r
                $search = array(
                    '\n',
                    '\r'
                );
                $replace = array(
                    "",
                    ""
                );
                $apiResp = str_replace($search, $replace, $apiResp);

                // Remove the UTF-8 BOM if present (abnormal presence but some WebAPIs to return it)
                // http://en.wikipedia.org/wiki/Byte_order_mark#UTF-8
                $bom = pack('CCC', 0xEF, 0xBB, 0xBF);
                if (substr($apiResp, 0, 3) == $bom)
                    $apiResp = substr($apiResp, 3);

                // ------ End sanity measures ----

                // Apply JSON-LD profile to the Web API response and transform the JSON-LD to RDF NQuads
                $quads = JsonLD::toRdf($apiResp, array(
                    'expandContext' => $jsonldProfile
                ));
                $nquads = new NQuads();
                $serializedQuads = $nquads->serialize($quads);
                if ($logger->isHandling(Logger::DEBUG))
                    $logger->debug("Web API JSON response translated into NQuads:\n" . $serializedQuads);

                return $serializedQuads;
            }
        } catch (Exception $e) {
            $logger->warning((string) $e);
            throw new Exception("Cannot query the Web API or transform its response to JSON-LD.");
        }
    }

    /**
     * Read a JSON content given by its URL and return its content as a string
     *
     * @param string $url
     *            the URL of the JSON document
     * @param null|array $additionalHeaders
     *            an array of HTTP headers to send with the query
     * @return null|string the result JSON content as a string. Null in case an error occured.
     */
    static public function loadJsonDocument($url, $additionalHeaders = null)
    {
        global $context;
        $logger = $context->getLogger("Utils");

        // Build the list of HTTP headers
        $headers = array();
        $headers[] = "Accept: application/json; q=0.9, */*; q=0.1";
        $headers[] = "User-Agent: SPARQL-Micro-Service";
        if ($additionalHeaders != null)
            foreach ($additionalHeaders as $hName => $hVal)
                $headers[] = $hName . ": " . $hVal;
        if ($logger->isHandling(Logger::DEBUG))
            $logger->debug("Web API query HTTP headers: " . print_r($headers, TRUE));
        if ($logger->isHandling(Logger::DEBUG))
            $logger->debug("Executing the Web API query now...");

        $jsonContent = self::file_get_contents_curl($url, $headers);
        if ($jsonContent === false) {
            $logger->warning("Cannot load document " . $url);
            $jsonContent = null;
        } else if ($jsonContent == null || $jsonContent == "")
            // In case of HTTP 204 No Content, the API may return null although this should
            // not be considered as an error. In that case, return an empty JSON document
            $jsonContent = "{}";

        if (isset($http_response_header)) {
            $headers = self::parseHttpHeaders($http_response_header);
            if ($logger->isHandling(Logger::DEBUG)) {
                $logger->debug("Web API response headers:");
                foreach ($headers as $k => $v)
                    $logger->debug("   $k: $v");
            }
        }

        return $jsonContent;
    }

    /**
     * Parse an arrary of strings representing HTTP headers and return an associative
     * array where the key is the header name.
     * Example:
     * Header "Accept: text/html" is transformed into the key value couple: "Accept" => "text/html"
     *
     * @param array $headers
     *            arrary of strings representing HTTP headers, e.g. "Accept: text/html"
     * @return array associative array where the key is the header name
     */
    static public function parseHttpHeaders($headers)
    {
        $head = array();
        foreach ($headers as $v) {
            $t = explode(':', $v, 2);
            if (isset($t[1]))
                $head[trim($t[0])] = trim($t[1]);
            else {
                if (preg_match("#HTTP/[0-9\.]+\s+([0-9]+)#", $v, $out))
                    $head['Status'] = intval($out[1]);
            }
        }
        return $head;
    }

    /**
     * Check and return a set of named HTTP query string arguments.
     * If any parameter in not found, the function returns an HTTP error 400 and exits.
     *
     * @param array $args
     *            array of argument names to read from the query string
     * @return array associative array where the key is the argument name,
     *         and the value is an array of values for that argument
     */
    static public function getQueryStringArgs($args)
    {
        global $context;
        $logger = $context->getLogger("Utils");

        if (array_key_exists('QUERY_STRING', $_SERVER)) {
            if ($logger->isHandling(Logger::DEBUG))
                $logger->debug('Query string: ' . $_SERVER['QUERY_STRING']);
        } else
            Utils::httpBadRequest("HTTP error, no query string provided.");

        $result = array();
        foreach ($args as $name) {
            if (array_key_exists($name, $_REQUEST)) {
                if ($name != "query")
                    // Security measure: escape special chars, html or php code
                    $argValue = strip_tags($_REQUEST[$name]);
                else
                    // Do NOT escape special chars in case of the 'query' parameter that contains the SPARQL query
                    $argValue = $_REQUEST[$name];

                // If multiple comma-separated values, return the separate values
                $result[$name] = explode(',', $argValue);
            }
        }

        return $result;
    }

    /**
     * Get the Web API arguments passed to the micro-service within the SPARQL graph pattern.
     *
     * This is achieved by a SPARQL query over the SPIN graph of the user's query, the Service Description
     * graph and the shapes graph.
     *
     * For each argument declared in the Service Description, we look for it in the user's query either
     * with its hydra:property or using the property shape denoted by shacl:sourceShape (the SD graph
     * should provide one or the other).
     *
     * If one of the expected parameters in not found, the function logs a warning and exits with an HTTP 400 error
     * returns the result anyway.
     *
     * @param string $sparqlQuery
     *            the SPARQL query
     * @return array associative array where the key is the argument name,
     *         and the value is an array of values for that argument
     */
    static private function getServiceCustomArgsFromSparqlQuery($sparqlQuery)
    {
        global $context;
        $logger = $context->getLogger("Utils");

        // --- Convert the SPARQL query to SPIN and load it into a temporary graph

        $spinInvocation = $context->getConfigParam('spin_endpoint') . '?arg=' . urlencode($sparqlQuery);
        $spinGraphUri = $context->getConfigParam('root_url') . '/tempgraph-spin' . uniqid("-", true);
        if ($logger->isHandling(Logger::DEBUG))
            $logger->debug("SPIN translation invocation: " . $spinInvocation);

        $query = 'LOAD <' . $spinInvocation . '> INTO GRAPH <' . $spinGraphUri . '>';
        if ($logger->isHandling(Logger::DEBUG))
            $logger->debug("SPARQL query converted to SPIN: \n" . file_get_contents($spinInvocation));
        $logger->info('Loading SPIN SPARQL query into temp graph ' . $spinGraphUri);
        $context->getSparqlClient()->update($query);

        // --- For each service custom argument, read its value from the SPARQL query.
        // Each argument is provided either with hydra:property or by a property shape denoted by shacl:sourceShape

        $query = file_get_contents('resources/read_input_from_gp.sparql');
        $query = str_replace('{SpinQueryGraph}', $spinGraphUri, $query);
        $query = str_replace('{ServiceDescription}', $context->getServiceDescriptionGraphUri(), $query);
        $query = str_replace('{ShapesGraph}', $context->getShapesGraphUri(), $query);

        $jsonResult = self::runSparqlSelectQuery($query);
        $result = array();
        // The response consists of mappings for 3 variables: ?argName ?predicate ?argValue
        foreach ($jsonResult as $varMapping) {
            $argName = $varMapping['argName']['value'];
            $predicate = $varMapping['predicate']['value'];
            $argValue = $varMapping['argValue']['value'];

            // Return an array of values of that variable
            $result[$argName][] = $argValue;
        }

        // Make sure we have values for all expected arguments
        foreach ($context->getConfigParam('custom_parameter_binding') as $argName => $mapping)
            if (! array_key_exists($argName, $result))
                self::httpBadRequest('No triple patterns give a value for predicate "' . $mapping['predicate'] . '" (for service argument "' . $argName . '")');

        // Drop the temporary SPIN graph
        $logger->info("Dropping graph: <" . $spinGraphUri . ">");
        $context->getSparqlClient()->update("DROP SILENT GRAPH <" . $spinGraphUri . ">");

        return $result;
    }

    /**
     * Get the service custom arguments passed to the micro-service either as
     * query string arguments or within the SPARQL graph pattern.
     *
     * If the service is invoked with query mode 'ld', then the arguments are expected to be
     * passed on the query string, not in a SPARQL query (since there is no SPARQL query in the
     * 'ld' query mode).
     *
     * If any parameter in not found, the function returns an HTTP error 400 and exits.
     *
     * @return array associative array where the key is the argument name,
     *         and the value is an array of values for that argument
     */
    static public function getServiceCustomArgs()
    {
        global $context;

        if (! $context->getConfigParam('service_description') || $context->getQueryMode() == 'ld')
            return self::getQueryStringArgs($context->getConfigParam('custom_parameter'));
        else
            return self::getServiceCustomArgsFromSparqlQuery($context->getSparqlQuery());
    }

    /**
     * Retrieve the SPARQL query following the 3 possible ways defined in the SPARQL protocol
     *
     * @see https://www.w3.org/TR/2013/REC-sparql11-protocol-20130321/#query-operation
     * @return string the SPARQL query
     */
    static public function getSparqlQuery()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        switch ($method) {
            case 'GET':
                {
                    if (array_key_exists('query', $_GET))
                        $sparqlQuery = $_GET['query'];
                    else
                        self::httpBadRequest("SPARQL query with HTTP GET method but no 'query' argument.");
                    break;
                }
            case 'POST':
                {
                    if (array_key_exists('CONTENT_TYPE', $_SERVER))
                        $contentType = $_SERVER['CONTENT_TYPE'];
                    else
                        self::httpBadRequest("SPARQL query with HTTP POST method but no 'Content-Type' header.");

                    switch ($contentType) {
                        case 'application/x-www-form-urlencoded':
                            if (array_key_exists('query', $_POST))
                                $sparqlQuery = $_POST['query'];
                            else
                                self::httpBadRequest("SPARQL query with HTTP POST method and Content-Type' application/x-www-form-urlencoded' but no 'query' argument.");
                            break;
                        case 'application/sparql-query':
                            $sparqlQuery = file_get_contents('php://input');
                            break;
                        default:
                            self::httpBadRequest("SPARQL query with HTTP POST method but unexpected 'Content-Type': " . $contentType);
                    }
                    break;
                }
            default:
                self::httpMethodNotAllowed("Unsupported HTTP method " . $method);
        }
        return $sparqlQuery;
    }

    /**
     * Execute a SPARQL SELECT query asking for a SPARQL JSON result
     * and return only the bindings part of the response
     *
     * @param string $query
     *            SPARQL query
     * @return array JSON document containnig only the array (possibly empty) of bindings
     * @example The returned document would typically look like this:
     *          <pre><code>
     *          [
     *          {
     *          "book" : { "type": "uri", "value": "http://example.org/book/book6" } ,
     *          "title": { "type": "literal", "value": "Harry Potter and the Half-Blood Prince" }
     *          },
     *          {
     *          "book" : { "type": "uri" , "value": "http://example.org/book/book7" } ,
     *          "title": { "type": "literal" , "value": "Harry Potter and the Deathly Hallows" }
     *          }
     *          ]
     *          </code></pre>
     */
    static public function runSparqlSelectQuery($query)
    {
        global $context;
        $logger = $context->getLogger("Utils");
        if ($logger->isHandling(Logger::DEBUG))
            $logger->debug("Executing SPARQL query:\n" . $query);

        $result = $context->getSparqlClient()->queryRaw($query, "application/sparql-results+json");
        $jsonResult = json_decode($result->getBody(), true)['results']['bindings'];

        if ($logger->isHandling(Logger::DEBUG))
            $logger->debug("SPARQL response: " . print_r($jsonResult, true));
        return $jsonResult;
    }

    /**
     * Pretty-print a variable.
     * This replaces simple native print_r function that
     * does not properly display boolean values
     *
     * @param mixed $arg
     *            any type of variable to pretty-print
     * @return string pretty print-out of the variable value
     */
    static public function print_r($arg)
    {
        return str_replace("'", "", var_export($arg, true));
    }

    static public function dumpGraph($graphUri)
    {
        global $context;
        $logger = $context->getLogger("Utils");
        $result = $context->getSparqlClient()->queryRaw("construct where {?s ?p ?o}", "text/turtle", $graphUri);
        $logger->debug($result);
    }

    /**
     * Unwind the array of arguments that were passed to the SPARQL micro-service.
     * (named after the MongoDB unwind function: https://docs.mongodb.com/manual/reference/operator/aggregation/unwind/).
     *
     * "Unwind" means that when an argument has more than one value, this function will generate
     * one array of arguments for each of these values. This is repeated for all arguments, resulting
     * in possibly many arrays being generated for all the combinations of all the values.
     *
     * Wether two values ['v1','v2'] should entail 2 separate arrays or be merged in a CSV value depends
     * on the service's configuration parameter "custom_parameter.pass_multiple_values_as_csv":
     * if true, the values are passed as csv; if false, the values entail the creation of several arrays.
     *
     * @param array $args
     *            associative array of custom arguments passed to the SPARQL micro-service
     *            either using the HTTP parameteter method or through the SPARQL graph pattern.
     * @return array array of arrays, each one contains a combination of the arguments' values
     * @example If $args = <pre>[p1 => [v1], p2 => [v21, v22]]</pre> and
     *          array <code>custom_parameter.pass_multiple_values_as_csv</code> = <pre>[p1 => true, p2 => false]</pre>
     *          then the result shall be:
     *          <pre>[[p1 => v1, p2 => v21], [p1 => v1, p2 => v22]]</pre>
     */
    static public function unwindArgumentValues($args)
    {
        global $context;
        $passMultipleValuesAsCsv = $context->getConfigParam('custom_parameter.pass_multiple_values_as_csv');

        $_results = array();
        if (sizeof($args) == 0)
            return $_results;

        // Process the first element of the array
        reset($args); // point to first element
        $argName = key($args);
        $argVals = $args[$argName];

        if ($passMultipleValuesAsCsv[$argName])
            // Create one new array with the comma-separated list of values
            $_results[][$argName] = implode(",", $argVals);
        else
            foreach ($argVals as $singleVal)
                // Create a new array for each value
                $_results[][$argName] = $singleVal;

        // Proceed with the remaining elements (starting at the 2nd element)
        if (sizeof($args) == 1)
            return $_results;

        $_subResults = Utils::unwindArgumentValues(array_slice($args, 1));
        $_newResults = array();
        foreach ($_results as $_results1)
            foreach ($_subResults as $_results2)
                $_newResults[] = array_merge($_results1, $_results2);

        return $_newResults;
    }

    /**
     * Equivalent of the file_get_contents function but using curl
     * @param string $url
     * @param array $additionalHeaders array of header formatted as strings like "Accept: text/html"
     * @return string
     */
    static public function file_get_contents_curl($url, $additionalHeaders = null)
    {
        global $context;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPGET, TRUE);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, Processor::REMOTE_TIMEOUT);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        if ($additionalHeaders != null)
            curl_setopt($ch, CURLOPT_HTTPHEADER, $additionalHeaders);

        if ($context->hasConfigParam('proxy.host')) {
            curl_setopt($ch, CURLOPT_PROXY, $context->getConfigParam('proxy.host'));
            if ($context->hasConfigParam('proxy.port'))
                curl_setopt($ch, CURLOPT_PROXYPORT, $context->getConfigParam('proxy.port'));

            // Add the proxy authentication
            if ($context->hasConfigParam('proxy.user') && $context->hasConfigParam('proxy.password')) {
                curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC);
                $auth = base64_encode($context->getConfigParam('proxy.user') . ':' . $context->getConfigParam('proxy.password'));
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $auth);
            }
        }

        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
}
?>
