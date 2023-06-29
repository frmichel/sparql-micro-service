<?php
namespace frmichel\sparqlms;

/**
 * This script can be provided to complement the service config.ini file.
 *
 * It receives 3 global variables:
 * - $apiQuery contains the Web API query template. The script must set the parameters to
 *   produce the ready-to-run query string.
 * - $customArgs is the set of custom arguments that have been passed to the service.
 *   It is an associative array where the key is the argument name,
 *   and the value is an array of values for that argument
 * - $logger is provided as a convenience in case the script wants to log any information.
 */
global $apiQuery;
global $customArgs;
global $logger;

// Read the service custom arguments
$queryTerms = $customArgs['terms'];

// Call the search API to get the ids of papers
$ids = getPapersIds($queryTerms);
$logger->notice("Retrieved paper ids: " . $ids);


// Format the Web API query URL
if ($ids == null)
    // In case the previous call failed, produce an empty query string for the service to be ignored
    $apiQuery = "";
else {
    # Turn the ids into a csv list. Max 50 ids are kept.
    $idsCsv = $ids[0];
    for ($i = 1; $i < min(count($ids), 20); $i++) {
        $idsCsv .= ',' . $ids[$i];
    }
    $apiQuery = str_replace('{pmId}', urlencode($idsCsv), $apiQuery);
}


/**
 * Query the API to get the ids of articles matching the query terms
 *
 * @param string $term
 * @return string the first code associated with that taxon name. Null if none or an error occured
 */
function getPapersIds($queryTerms)
{
    global $logger;
    
    $apiQuery = 'https://eutils.ncbi.nlm.nih.gov/entrez/eutils/esearch.fcgi?db=pubmed&retmode=json&term=' . urlencode($queryTerms);
    $logger->notice("Web API request: " . $apiQuery);
    
    $result = file_get_contents($apiQuery);
    if ($result !== FALSE) {
        $json = json_decode($result, true);
        if ($json != null)
            return $json['esearchresult']['idlist'];
    }
    return null;
}
?>
