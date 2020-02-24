<?php
namespace frmichel\sparqlms;

/**
 * This script can be provided to complement the service config.ini file.
 *
 * It receives 3 global variables:
 * - $customArgs is the set of custom arguments that have been passed to the service.
 * - $apiQuery contains the Web API query template. The script must set the parameters to  
 *   produce the ready-to-run query string.
 * - $logger is provided as a convenience in case the script wants to log any information.
 */
global $apiQuery;
global $customArgs;
global $logger;

// Read the service custom arguments. There should be only one name
$name = $customArgs['name'];

// Call another API service to get the code associated with the taxon name
$taxonCode = getTaxonCode($name);
$logger->notice("Retrieved taxon code: " . $taxonCode);

// Format the Web API query URL
if ($taxonCode == null)
    // In case the previous call failed, produce an empty query string for the service to be ignored
    $apiQuery = "";
else
    $apiQuery = str_replace('{taxonCode}', urlencode($taxonCode), $apiQuery);

/**
 * Query the Web API to get a code associated with a taxon name
 *
 * @param string $taxonName
 * @return string the first code associated with that taxon name. Null if none or an error occured
 */
function getTaxonCode($taxonName)
{
    global $logger;
    
    $apiQuery = 'https://search.macaulaylibrary.org/api/v1/find/taxon?q=' . urlencode($taxonName);
    $logger->notice("Web API request: " . $apiQuery);
    
    $result = file_get_contents($apiQuery);
    if ($result !== FALSE) {
        $json = json_decode($result, true);
        if ($json != null)
            return $json[0]['code'];
    }
    return null;
}
?>