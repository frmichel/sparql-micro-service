<?php
require_once 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use ML\JsonLD\JsonLD;
use ML\JsonLD\NQuads;
use ML\JsonLD\Processor;

function initMetro($level = Logger::INFO)
{
    if (array_key_exists('SCRIPT_FILENAME', $_SERVER))
        $scriptName = basename($_SERVER['SCRIPT_FILENAME']);
    else
        $scriptName = basename(__FILE__);
    
    $handler = new StreamHandler(__DIR__ . '/logs/metro.csv', $level, true, 0666);
    $handler->setFormatter(new LineFormatter("%message% \n"));
    $logger = new Logger("");
    $logger->pushHandler($handler);
    
    return $logger;
}

/**
 * Add time measures to the metrology file in CSV format
 *
 * @param string $service
 *            the current service name
 * @param string $message
 * @param float $time1
 *            first time measure is seconds
 * @param float $time2
 *            second time measure is seconds
 */
function appendMetro($service, $message, $time1, $time2 = 0)
{
    global $metro;
    $t1 = number_format($time1, 4, $dec_point = ",", $thousands_sep = "");
    $t2 = number_format($time2, 4, $dec_point = ",", $thousands_sep = "");
    
    if ($time2 == 0)
        $metro->info("$service; $message; $t1");
    else
        $metro->info("$service; $message; $t1; $t2");
}

/**
 * Check and log the Content-Type and Accept HTTP headers
 *
 * @return array (Content-Type, Accept)
 */
function getHttpHeaders()
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
        $logger->warn('Query HTTP header "Accept" undefined. Using: ' . $context->getConfigParam('default_mime_type'));
    
    return array(
        $contentType,
        $accept);
}

/**
 * Return an HTTP staus 400 with an error message and exit the script.
 */
function badRequest($message)
{
    global $logger;
    
    http_response_code(400); // Bad Request
    $logger->error($message);
    print("Erroneous request: " . $message);
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
function translateJsonToNQuads($jsonUrl, $jsonldProfile)
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
            $apiResp = loadJsonDocument($jsonUrl);
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
            '\r');
        $replace = array(
            "",
            "");
        $apiResp = str_replace($search, $replace, $apiResp);
        
        // Apply JSON-LD profile to the Web API response and transform the JSON-LD to RDF NQuads
        $quads = JsonLD::toRdf($apiResp, array(
            'expandContext' => $jsonldProfile));
        $nquads = new NQuads();
        $serializedQuads = $nquads->serialize($quads);
        if ($logger->isHandling(Logger::DEBUG))
            $logger->debug("Web API JSON response translated into NQuads:\n" . $serializedQuads);
        
        return $serializedQuads;
    } catch (Exception $e) {
        $logger->warning((string) $e);
        $logger->warning("Error when querying the API/transforming its response to JSON-LD. Returning empty result.");
        return array();
    }
}

/**
 * Read a JSON content given by its URL and return its content as a string
 *
 * @param string $url
 *            the URL of the JSON document
 * @return string the result JSON content as a string
 */
function loadJsonDocument($url)
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
            'allow_self_signed' => false]);
    
    $jsonContext = stream_context_create(array(
        'http' => $streamContextOptions,
        'https' => $streamContextOptions));
    
    if (false === ($jsonContent = @file_get_contents($url, false, $jsonContext))) {
        $logger->warning("Cannot load document " . $url);
        $jsonContent = null;
    }
    
    $headers = parseHttpHeaders($http_response_header);
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
 *            arrary of strings representing HTTP headers
 * @return array associative array where the key is the header name
 */
function parseHttpHeaders($headers)
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

?>
