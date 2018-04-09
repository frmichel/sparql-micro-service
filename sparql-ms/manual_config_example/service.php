<?php
    /**
     * This script can be provided instead of the service config.ini file
     * if you have specific treatments to perform, e.g. authentication process,
     * intermediary service call etc.
     
     * It is included by the main script, thus it does not need to import other files
     * except third-party dependencies required by your code.
     *
     * This script must simply define 2 globals variables:
     *   $apiQuery: the properly formatted query string. To do so, the script must
     *              define and read the expected parameters.
     *   $cacheExpirationSec: cache expiration period (in seconds) if cache must be used
     */

    use Monolog\Logger;
    global $logger;

    // ----------------- Your specific code --------------------
    /*
            Do whatever has to be done before here
    */
    $logger->info("What must be done has been done...");

    // ----------------- Mandatory code --------------------
    
    // Define the service parameters, e.g. param1 and param2 herebelow
    $serviceParams = array("param1", "param2");
    list($param1, $param2) = array_values(getQueryParameters($serviceParams));

    // Build the Web API query URL
    $apiQuery = 'https://api.example.org?format=json&'.
                'param1='.urlencode($param1).'&param2='.urlencode($param2);

    // Define the cache expiration period (in seconds)
    $cacheExpirationSec = 2592000;

?>