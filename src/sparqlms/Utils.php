<?php
namespace frmichel\sparqlms;

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
        $logger = $context->getLogger();
        
        if (array_key_exists('CONTENT_TYPE', $_SERVER)) {
            $contentType = $_SERVER['CONTENT_TYPE'];
            $logger->info('Query HTTP header "Content-Type": ' . $contentType);
        } else {
            $logger->info('Query HTTP header "Content-Type" undefined.');
            $contentType = "";
        }
        
        if (array_key_exists('HTTP_ACCEPT', $_SERVER)) {
            $accept = $_SERVER['HTTP_ACCEPT'];
            $logger->info('Query HTTP header "Accept": ' . $accept);
        } else
            $logger->notice('Query HTTP header "Accept" undefined. Using: ' . $context->getConfigParam('default_mime_type'));
        
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
        $logger = $context->getLogger();
        
        http_response_code(400); // Bad Request
        $logger->error($message);
        print("Erroneous request: " . $message . "\n");
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
        $logger = $context->getLogger();
        
        http_response_code(405); // Method Not Allowed
        $logger->error($message);
        print("Erroneous request: " . $message . "\n");
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
        $logger = $context->getLogger();
        
        http_response_code(422); // Unprocessable entity
        $logger->error($message);
        print("Invalid request: " . $message . "\n");
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
     */
    static public function translateJsonToNQuads($jsonUrl, $jsonldProfile)
    {
        global $context;
        $logger = $context->getLogger();
        $useCache = $context->useCache();
        $cache = $context->getCache();
        
        $apiResp = null;
        try {
            if ($useCache) {
                // Check if response is already in cache db
                $apiResp = $cache->read($jsonUrl);
                if ($apiResp != null && $logger->isHandling(Logger::DEBUG))
                    $logger->debug("Retrieved JSON response from cache: \n" . JsonLD::toString($apiResp));
            }
            
            if ($apiResp == null) {
                if ($logger->isHandling(Logger::DEBUG))
                    $logger->debug("JSON response not found in cache.");
                // Query the Web API
                $apiResp = self::loadJsonDocument($jsonUrl);
                if ($logger->isHandling(Logger::DEBUG))
                    $logger->debug("Web API JSON response: \n" . $apiResp);
                
                // Store the result into the cache db
                if ($useCache) {
                    $cache->write($jsonUrl, $apiResp, $context->getService());
                    if ($logger->isHandling(Logger::DEBUG))
                        $logger->debug("Stored JSON response into cache.");
                }
            }
            
            // -- Safety measures
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
            
            // Apply JSON-LD profile to the Web API response and transform the JSON-LD to RDF NQuads
            $quads = JsonLD::toRdf($apiResp, array(
                'expandContext' => $jsonldProfile
            ));
            $nquads = new NQuads();
            $serializedQuads = $nquads->serialize($quads);
            if ($logger->isHandling(Logger::DEBUG))
                $logger->debug("Web API JSON response translated into NQuads:\n" . $serializedQuads);
            
            return $serializedQuads;
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
     * @return string the result JSON content as a string
     */
    static public function loadJsonDocument($url)
    {
        global $context;
        $logger = $context->getLogger();
        
        $streamContextOptions = array(
            'method' => 'GET',
            'header' => "Accept: application/json; q=0.9, */*; q=0.1\r\n" . 
            // Some Web API require a User-Agent.
            // E.g. MusicBrainz returns error 403 if there is none.
            "User-Agent: SPARQL-Micro-Service\r\n",
            'timeout' => Processor::REMOTE_TIMEOUT,
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false
            ]
        );
        
        $jsonContext = stream_context_create(array(
            'http' => $streamContextOptions,
            'https' => $streamContextOptions
        ));
        
        if (false === ($jsonContent = @file_get_contents($url, false, $jsonContext))) {
            $logger->warning("Cannot load document " . $url);
            $jsonContent = null;
        }
        
        $headers = self::parseHttpHeaders($http_response_header);
        if ($logger->isHandling(Logger::DEBUG)) {
            $logger->debug("Web API response headers:");
            foreach ($headers as $k => $v)
                $logger->debug("   $k: $v");
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
     *            array of parameter names to read from the query string
     * @return array associative array of parameter names and values read from the query string
     */
    static public function getQueryStringArgs($args)
    {
        $result = array();
        foreach ($args as $name) {
            
            if (array_key_exists($name, $_REQUEST)) {
                if ($name != "query")
                    // Escape special chars
                    $argValue = strip_tags($_REQUEST[$name]);
                else
                    // Dp NOT escape special chars in case of the 'query' parameter that contains the SPARQL query
                    $argValue = $_REQUEST[$name];
                $result[$name] = $argValue;
            } else
                self::httpBadRequest("Query argument '" . $name . "' undefined.");
        }
        
        return $result;
    }

    /**
     * Get the Web API arguments passed to the micro-service within the SPARQL graph pattern.
     *
     * If any parameter in not found, the function returns an HTTP error 400 and exits.
     *
     * @param string $sparqlQuery
     *            the SPARQL query string
     * @return array associative array of parameter names and values
     */
    static private function getServiceCustomArgsFromSparqlQuery($sparqlQuery)
    {
        global $context;
        $logger = $context->getLogger();
        
        // --- Convert the SPARQL query to SPIN and load it into a temporary graph
        $spinInvocation = $context->getConfigParam('spin_endpoint') . '?arg=' . urlencode($sparqlQuery);
        $spinQueryGraph = $context->getConfigParam('root_url') . '/tempgraph' . uniqid("-", true);
        $query = 'LOAD <' . $spinInvocation . '> INTO GRAPH <' . $spinQueryGraph . '>';
        if ($logger->isHandling(Logger::DEBUG)) {
            $logger->debug("SPARQL query converted to SPIN: \n" . file_get_contents($spinInvocation));
            $logger->debug('Loading SPIN SPARQL query into temp graph ' . $spinQueryGraph);
        }
        $context->getSparqlClient()->update($query);
        
        // --- For each service custom argument, read its value from the SPARQL query.
        // Each argument may be provided either directly with hydra:property or by a property shape denoted by shacl:sourceShape
        
        $query = file_get_contents('resources/read_input_from_gp.sparql');
        $query = str_replace('{SpinQueryGraph}', $spinQueryGraph, $query);
        $query = str_replace('{ServiceDescription}', $context->getServiceDescriptionGraphUri(), $query);
        $query = str_replace('{ShapesGraph}', $context->getShapesGraphUri(), $query);
        
        $jsonResult = self::runSparqlSelectQuery($query);
        $result = array();
        foreach ($jsonResult as $jsonResultN) {
            $name = $jsonResultN['name']['value'];
            if (array_key_exists($name, $result)) {
                $predicate = $jsonResultN['predicate']['value'];
                Utils::httpUnprocessableEntity("Only one value is allowed for property '" . $predicate . "' (argument '" . $name . "').");
            }
            $result[$name] = $jsonResultN['argValue']['value'];
        }
        
        // Make sure we have values for all expected arguments
        foreach ($context->getConfigParam('custom_parameter') as $name)
            if (! array_key_exists($name, $result))
                $logger->notice("No triple for argument '" . $name . "' found in the SPARQL query. Will return empty response.");
        
        // Drop the temporary SPIN graph
        if ($logger->isHandling(Logger::DEBUG))
            $logger->debug("Dropping graph: <" . $spinQueryGraph . ">");
        $context->getSparqlClient()->update("DROP SILENT GRAPH <" . $spinQueryGraph . ">");
        
        return $result;
    }

    /**
     * Get the Web API arguments passed to the micro-service either as query string arguments
     * or within the SPARQL graph pattern.
     *
     * If any parameter in not found, the function returns an HTTP error 400 and exits.
     *
     * @return array associative array of parameter names and values
     */
    static public function getServiceCustomArgs()
    {
        global $context;
        
        if (! $context->getConfigParam('service_description'))
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
     * Execute a SPARQL SELECT query asking for a JSON response and return only the bindings part of the response
     *
     * @param string $query
     *            SPARQL query
     * @return array JSON document containnig only an array (possibly empty) of bindings
     * @example The returned document would typically look like this:
     *          <pre><code>
     *          [ {
     *          "book": { "type": "uri" , "value": "http://example.org/book/book6" } ,
     *          "title": { "type": "literal" , "value": "Harry Potter and the Half-Blood Prince" }
     *          },
     *          {
     *          "book": { "type": "uri" , "value": "http://example.org/book/book7" } ,
     *          "title": { "type": "literal" , "value": "Harry Potter and the Deathly Hallows" }
     *          }
     *          ]
     *          </code></pre>
     */
    static public function runSparqlSelectQuery($query)
    {
        global $context;
        $logger = $context->getLogger();
        if ($logger->isHandling(Logger::DEBUG))
            $logger->debug("Executing SPARQL query:\n" . $query);
        
        $result = $context->getSparqlClient()->queryRaw($query, "application/sparql-results+json");
        $jsonResult = json_decode($result->getBody(), true)['results']['bindings'];
        
        if ($logger->isHandling(Logger::DEBUG))
            $logger->debug("SPARQL response: " . print_r($jsonResult, true));
        return $jsonResult;
    }
}
?>
