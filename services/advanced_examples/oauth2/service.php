<?php
namespace frmichel\sparqlms;

use frmichel\sparqlms\common\Utils;
global $context;

$logger = $context->getLogger("mirri");

$accessToken = getAccessToken();
$logger->debug("Retrieved access token: " . $accessToken);

// Register the access token as a new http header that will be used when submitting the Web API query
$context->setConfigParam('http_header', array(
    'Authorization' => "Bearer " . $accessToken
));

// ----------------------------------------------------------

/**
 * Perform an authorization request with the client's id and secret to obtain
 * an access token that will be used later on for regular API queries
 *
 * @return string the access token to be used in subsequent queries
 */
function getAccessToken()
{
    global $context;
    global $logger;

    // Compute the Base64 encoding of the client's id and secret
    $client_id = $context->getConfigParam("client_id");
    $client_secret = $context->getConfigParam("client_secret");
    $authBase64 = base64_encode($client_id . ":" . $client_secret);
    $logger->info("Authentication data: client_id=" . $client_id . ". client_secret=" . $client_secret . ". base64=" . $authBase64);

    // Check wether a valid token is in the cache db
    $cacheKey = "mirri:" . $authBase64;
    $cached = $context->getCache()->read($cacheKey);
    if ($cached != null) {
        $logger->debug("Valid token retrieved from cache db");
        // An access token was found in the cache and it is still valid. Just return it
        return $cached;
    }

    // Set the authorization HTTP header
    $headers = array();
    $headers[] = "Authorization: Basic " . $authBase64;

    // Submit the authorization request with HTTP POST method
    // With curl this should be: curl -X POST -d "grant_type=client_credentials" -H "Authorization: Basic base64encoding" "https://webservices.bio-aware.com/mirri/connect/token"
    $url = "https://webservices.bio-aware.com/mirri/connect/token";
    $jsonContent = Utils::file_post_contents_curl($url, $post = "grant_type=client_credentials", $additionalHeaders = $headers);

    if ($jsonContent === false) {
        $logger->warning("Cannot get a response from " . $url);
        Utils::httpUnprocessableEntity("Cannot get authorization, no reponse.");
    }

    $logger->debug("Response to the authorization request: \n" . $jsonContent);
    $response = json_decode($jsonContent, true);

    if (array_key_exists('expires_in', $response) && array_key_exists('access_token', $response)) {
        $expiresIn = $response['expires_in'];
        $accessToken = $response['access_token'];

        $logger->debug("Store the newly retrieved token in the cache db");
        $context->getCache()->write($cacheKey, $accessToken, $expiresIn);
    } else {
        $accessToken = "";
        Utils::httpUnprocessableEntity("Authorization reponse does not contains either expires_in or access_token.");
    }

    return $accessToken;
}
?>