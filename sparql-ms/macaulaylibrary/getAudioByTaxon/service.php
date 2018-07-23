<?php
    /**
     * This script can be provided instead of the service config.ini file.
     * It must take care of defining the expected parameters and reading them.
     *
     * This script must define global variables:
     *   $apiQuery: the properly formatted query string
     *   $cacheExpirationSec: cache expiration period (in seconds) if cache must be used
     *
     * macaulaylibrary/getAudioByTaxon:
     *   Query mode: SPARQL query
     *   Parameter: name = taxon name
     */

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
        $apiQuery = 'https://search.macaulaylibrary.org/catalog.json?'.
            'action=new_search&searchField=animals&sort=upload_date_desc&mediaType=a&'.
            'taxonCode='.urlencode($taxonCode);

    // Define the cache expiration period (in seconds)
    $cacheExpirationSec = 2592000;



    /**
     * Query the Web API to get a code associated with a taxon name
     *
     * @param string $taxonName
     * @return string the first code associated with that taxon name. Null if none or an error occured
     */
    function getTaxonCode($taxonName) {
        global $logger;

        $apiQuery = 'https://search.macaulaylibrary.org/api/v1/find/taxon?q='.urlencode($taxonName);
        $logger->info("Web API request: ".$apiQuery);

        $result = file_get_contents($apiQuery);
        if ($result !== FALSE) {
            $json = json_decode($result, true);
            if ($json != null)
                return $json[0]['code'];
        }
        return null;
    }
?>