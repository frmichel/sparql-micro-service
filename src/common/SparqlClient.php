<?php
namespace frmichel\sparqlms\common;

use EasyRdf\Http;
use EasyRdf\Sparql\Client;

class SparqlClient extends Client
{

    /**
     * The query/read address of the SPARQL Endpoint
     */
    private $queryUri = null;

    private $httpClient = null;

    public function __construct($queryUri, $updateUri = null)
    {
        parent::__construct($queryUri, $updateUri);

        // HTTP client configuration with a large timemout value:
        // default is 10s but some SPARQL may need much more
        $httpConfig = array(
            'maxredirects' => 5,
            'useragent' => 'EasyRdf HTTP Client',
            'timeout' => 600
        );
        $this->httpClient = Http::getDefaultHttpClient();
        $this->httpClient->setConfig($httpConfig);

        $this->queryUri = $queryUri;
    }

    /**
     * This is a low-level version of the query() function where the Accept header is
     * provided explicitely, as well as the default and named graphs URIs.
     *
     * The result is the body of the raw HTTP response, not an EasyRdf_Sparql_Result or
     * EasyRdf_Graph like in function request().
     *
     * @param string $query
     *            The query string to be executed
     * @param string $accept
     *            Gives the value of the Accept HTTP header
     * @param string $defaultGraphUri
     *            Used as the default-graph-uri header
     * @param string $namedGraphUri
     *            Used as the named-graph-uri header
     * @param string $usingGraphUri
     *            Used as the using-graph-uri header
     * @param string $usingNamedGraphUri
     *            Used as the using-named-graph-uri header
     * @return Http\Response the raw HTTP response
     *        
     * @ignore
     */
    public function queryRaw($query, $accept, $defaultGraphUri = null, $namedGraphUri = null, $usingGraphUri = null, $usingNamedGraphUri = null)
    {
        // Add missing prefixes
        $processed_query = $this->preprocessQuery($query);

        $encodedQuery = 'query=' . urlencode($processed_query);

        $client = $this->httpClient;
        $client->resetParameters();

        // Tell the server which response formats we can parse
        $client->setHeaders('Accept', $accept);

        // In the "query via URL-encoded POST", the graph URIs are passed in the request message body
        if ($defaultGraphUri)
            $encodedQuery = $encodedQuery . '&default-graph-uri=' . urlencode($defaultGraphUri);

        if ($namedGraphUri)
            $encodedQuery = $encodedQuery . '&named-graph-uri=' . urlencode($namedGraphUri);

        if ($usingGraphUri)
            $encodedQuery = $encodedQuery . '&using-graph-uri=' . urlencode($usingGraphUri);

        if ($usingNamedGraphUri)
            $encodedQuery = $encodedQuery . '&using-named-graph-uri=' . urlencode($usingNamedGraphUri);

        $client->setUri($this->queryUri);
        $client->setMethod('POST');
        $client->setRawData($encodedQuery);
        $client->setHeaders('Content-Type', 'application/x-www-form-urlencoded');

        $response = $client->request();
        if ($response->getStatus() == 204) {
            // No content
            return $response;
        } elseif ($response->isSuccessful()) {
            return $response;
        } else
            throw new Http\Exception("HTTP request for SPARQL query failed", 0, null, $response->getBody());
    }
}

