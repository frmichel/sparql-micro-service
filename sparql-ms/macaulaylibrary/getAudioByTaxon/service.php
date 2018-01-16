<?php
    /**
     * Retrieve audio recordings for a given taxon
     *
     * Query mode: SPARQL query
     *
     * @param name taxon name
     */

    use Monolog\Logger;

    // Define the service custom parameters and default values
    $serviceParams = array(
        "name" => "delphinus delphis"
    );
    list($name) = array_values(getQueryParameters($serviceParams));

    // Get the code associated with the taxon name
    if ($metro->isHandling(Logger::INFO)) $before = microtime(true);
    $taxonCode = getTaxonCode($name);
    if ($metro->isHandling(Logger::INFO)) appendMetro($service, "API", microtime(true) - $before);
    $logger->info("Retrieved taxon code: ".$taxonCode);

    // Build the Web API query URL
    if ($taxonCode == null)
        // In case the first call failed, produce an empty query string for the service to be ignored
        $apiQuery = "";
    else
        $apiQuery = 'https://search.macaulaylibrary.org/catalog.json?'.
            'action=new_search&searchField=animals&sort=upload_date_desc&mediaType=a&'.
            'taxonCode='.urlencode($taxonCode);

    /**
     * Query the Web API to get a code associated with a taxon name
     *
     * @param string $taxonName
     * @return the first code associated with that taxon name. Null if none or an error occured
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