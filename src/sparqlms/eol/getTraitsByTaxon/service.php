<?php
/**
 * This script can be provided to complement the service config.ini file.
 *
 * It must take care of defining the global variable $apiQuery that contains the ready-to-run WebAPI query string.
 */
namespace frmichel\sparqlms;

$context = Context::getInstance();
$logger = $context->getLogger();

// Read list of the service custom arguments
$customArgs = $context->getConfigParam('custom_parameter');

// Call another API service to get the code associated with the taxon name
list ($name) = array_values(getQueryStringArgs($customArgs));
$taxonCode = getTaxonCode($name);
$logger->info("Retrieved taxon code: " . $taxonCode);

// Build the Web API query URL
if ($taxonCode == null)
    // In case the previous call failed, produce an empty query string for the service to be ignored
    $apiQuery = "";
else {
    $apiQuery = $context->getConfigParam('api_query');
    $apiQuery = str_replace('{taxonCode}', urlencode($taxonCode), $apiQuery);
}

/**
 * Query the Web API to get an EoL code associated with a taxon name
 *
 * @param string $taxonName
 * @return string the first code associated with that taxon name. Null if none or an error occured
 */
function getTaxonCode($taxonName)
{
    global $logger;
    
    $apiQuery = 'http://eol.org/api/search/1.0.json?exact=true&cache_ttl=3600&q=' . urlencode($taxonName);
    $logger->info("Web API request: " . $apiQuery);
    
    $result = file_get_contents($apiQuery);
    if ($result !== FALSE) {
        $json = json_decode($result, true);
        if ($json != null)
            return $json['results'][0]['id'];
    }
    return null;
}
?>