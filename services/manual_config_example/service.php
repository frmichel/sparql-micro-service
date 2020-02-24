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

// Read the service custom arguments
$param1 = $customArgs['param1'];
$param2 = $customArgs['param2'];

/*
 * ----------------- Your specific code --------------------
 *
 * Do whatever specific thing you may have to do here.
 *
 * For instance, call another service someService() using param2:
 */
$other_param = someService($param2);
$logger->notice("Retrieved other parameter: " . $other_param);

// ----------------------------------------------------------

// Format the Web API query URL
$apiQuery = str_replace('{param1}', urlencode($param1), $apiQuery);
$apiQuery = str_replace('{other_param}', urlencode($other_param), $apiQuery);

/*
 * This is it. Now, variable $apiQuery will be used to do the rest of the job.
 */
?>