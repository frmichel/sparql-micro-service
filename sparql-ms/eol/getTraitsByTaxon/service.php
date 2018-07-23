<?php

    use Monolog\Logger;

    // Define the service custom parameters
    $serviceParams = array("name");
    list($name) = array_values(getQueryParameters($serviceParams));

            // Optional. Only if mesures must be done
            if ($metro->isHandling(Logger::INFO)) $before = microtime(true);

    // Call another API service to get the code associated with the taxon name
    $taxonCode = getTaxonCode($name);
    $logger->info("Retrieved taxon code: ".$taxonCode);

            // Optional. Only if mesures must be done
            if ($metro->isHandling(Logger::INFO)) appendMetro($service, "API", microtime(true) - $before);

    // Build the Web API query URL
    if ($taxonCode == null) 
        // In case the first call failed, produce an empty query string for the service to be ignored
        $apiQuery = "";
    else
        $apiQuery = 'http://eol.org/api/traits/'.urlencode($taxonCode);

    // Define the cache expiration period (in seconds)
    $cacheExpirationSec = 2592000;



    /**
     * Query the Web API to get an EoL code associated with a taxon name
     *
     * @param string $taxonName
     * @return string the first code associated with that taxon name. Null if none or an error occured
     */
    function getTaxonCode($taxonName) {
        global $logger;

        $apiQuery = 'http://eol.org/api/search/1.0.json?exact=true&cache_ttl=3600&q='.urlencode($taxonName);
        $logger->info("Web API request: ".$apiQuery);

        $result = file_get_contents($apiQuery);
        if ($result !== FALSE) {
            $json = json_decode($result, true);
            if ($json != null)
                return $json['results'][0]['id'];
        }
        return null;
    }
?>