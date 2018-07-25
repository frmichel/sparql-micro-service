<?php
namespace frmichel\sparqlms;

/**
 * This script can be provided to complement the service 'config.ini' file.
 *
 * It must take care of defining the global variable $apiQuery that contains the ready-to-run Web API query string.
 */
$context = Context::getInstance();
$logger = $context->getLogger();

// Get the service custom arguments defined in config.ini: param1 and param2
$customArgs = $context->getConfigParam('custom_parameter');
list ($param1, $param2) = array_values($context->getQueryStringArgs($customArgs));

/*
 * ----------------- Your specific code --------------------
 *
 * Do whatever specific thing you may have to do here.
 *
 * For instance, call another service using param2:
 */
$other_param = someService($param2);

$logger->info("What must be done has been done...");

// ----------------------------------------------------------

// Build the Web API query URL
$apiQuery = $context->getConfigParam('api_query');
$apiQuery = str_replace('{param1}', urlencode($param1), $apiQuery);
$apiQuery = str_replace('{other_param}', urlencode($other_param), $apiQuery);

/*
 * This is it. Now variable $apiQuery will be used to do the rest of the job.
 */
?>