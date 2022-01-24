<?php
namespace frmichel\sparqlms;

/**
 * This script can be provided to complement the service config.ini file.
 *
 * It receives 3 global variables:
 * - $apiQuery contains the Web API query template. The script must set the parameters to
 * produce the ready-to-run query string.
 * - $customArgs is the set of custom arguments that have been passed to the service.
 * It is associative array where the key is the argument name,
 * and the value is an array of values for that argument
 * - $logger is provided as a convenience in case the script wants to log any information.
 */
global $apiQuery;
global $customArgs;
global $logger;

// Read the service custom argument
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

    $apiQuery = 'https://taxonomy.api.macaulaylibrary.org/v1/taxonomy?key=PUB4334626458&q=' . urlencode($taxonName);
    $logger->notice("Retrieving taxon code for name '" . $taxonName . "'. Web API request: " . $apiQuery);

    $result = file_get_contents($apiQuery);
    if ($result !== FALSE) {
        $json = json_decode($result, true);
        if ($json != null)
            return $json[0]['code'];
    }
    return null;
}
?>