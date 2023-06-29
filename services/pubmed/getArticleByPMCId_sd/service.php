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

// Read the service custom arguments and remove the "PMC" header
$pmcId = $customArgs['pmcId'];
$pmcIdNumeric = str_replace("PMC", "", $pmcId);

// Format the Web API query URL
$apiQuery = str_replace('{pmcId}', urlencode($pmcIdNumeric), $apiQuery);
?>