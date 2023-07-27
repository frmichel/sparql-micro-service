<?php
namespace frmichel\sparqlms\common;

use Monolog\Logger;
use Exception;

/**
 * Implement the management of the cache database, MongoDB in this case.
 *
 * @author fmichel
 */
class Cache
{

    /**
     * Default cache expiration time in seconds.
     * 2592000s = 30 days
     *
     * @var integer
     */
    const CACHE_EXP_SEC = 2592000;

    /**
     *
     * @var Cache
     */
    private static $singleton = null;

    /**
     *
     * @var \Monolog\Logger
     */
    private $logger = null;

    /**
     * DB connection string with default value
     *
     * @var string
     */
    private $cacheEndpoint = "mongodb://localhost:27017";

    /**
     * DB instance name with default value
     *
     * @var string
     */
    private $cacheDbName = "sparql_micro_service";

    /**
     * MongoDB database collection
     *
     * @var \MongoDB\Collection
     */
    private $cacheDb = null;

    /**
     * Constructor method.
     *
     * @param Context $context
     */
    private function __construct($context)
    {
        $this->logger = $context->getLogger("Cache");

        if ($context->hasConfigParam('cache_endpoint'))
            $this->cacheEndpoint = $context->getConfigParam('cache_endpoint');

        if ($context->hasConfigParam('cache_db_name'))
            $this->cacheDbName = $context->getConfigParam('cache_db_name');

        // Create the database client and default collection: 'cache'
        $client = new \MongoDB\Client($this->cacheEndpoint);
        $this->cacheDb = $client->selectCollection($this->cacheDbName, 'cache');
    }

    /**
     * Create and/or get singleton instance
     *
     * @param Context $context
     * @return Cache
     */
    public static function getInstance($context)
    {
        if (is_null(self::$singleton))
            self::$singleton = new Cache($context);

        return self::$singleton;
    }

    /**
     * Generic function to write a document (payload) to the cache db with $id as an index and an expiration date
     *
     * The JSON documents stored looks like this:
     * {
     *   "hash" : "cb10dc6d18ad7fb3160bef79699f1ef704e29bcf13aafa75cfa25cb951ce89ec",
     *   "fetch_date" : "2023-06-12 00:08:35",
     *   "expires_at" : "2023-07-11 00:08:35",
     *   "payload" : "..."
     * }
     * 
     * "expires_at" is set only if a value is provided for parameter $expiresAfter. Otherwise it is empty.
     *
     * @param string $id
     *            index of the document, wil be hashed
     * @param string $document
     *            the payload to be stored in the db
     * @param string $expiresAfter
     *            duration (in seconds) after which the document expires. null means no expiration.
     */
    public function write($id, $document, $expiresAfter = null)
    {
        try {
            $now = (new \DateTime('now'));

            if ($expiresAfter == null) {
                $cacheExpiresAt = "";
            } else {
                $cacheExpiresAt = $now->add(new \DateInterval('PT' . $expiresAfter . 'S'));
                if ($this->logger->isHandling(Logger::DEBUG))
                    $this->logger->debug("Cached document expires at: " . $cacheExpiresAt->format('Y-m-d H:i:s'));
            }

            $this->cacheDb->insertOne([
                'hash' => hash("sha256", $id),
                'fetch_date' => $now->format('Y-m-d H:i:s'),
                'expires_at' => $cacheExpiresAt->format('Y-m-d H:i:s'),
                'payload' => $document
            ]);
        } catch (Exception $e) {
            $this->logger->warning("Cannot write to cache db: " . (string) $e);
        }
    }

    /**
     * Retrieve a document written with write() from the cache db and return it.
     *
     * If it is found and the expiration date is passed, the document is deleted from the cache db, and null is returned.
     *
     * @param string $query
     *            the Web API query. Its hash is used as a key
     * @return string the cached document if found, null otherwise.
     */
    public function read($id)
    {
        $hash = hash("sha256", $id);
        $found = $this->cacheDb->findOne([
            'hash' => $hash
        ]);
        
        if ($found != null) {

            // No expiration date: return the payload
            if ($found['expires_at'] == "") {
                return $found['payload'];
            }

            // Otherwise, check the expiration date
            $cacheExpiresAt = new \DateTime($found['expires_at']);
            if ($this->logger->isHandling(Logger::DEBUG))
                $this->logger->debug("Cached document expires at: " . $cacheExpiresAt->format('Y-m-d H:i:s'));

            if ($cacheExpiresAt >= (new \DateTime('now'))) {
                // If the expiration date is not passed, the document can be returned as is
                return $found['payload'];
            } else {
                // If the expiration date is passed, remove the document from the cache and return null = no cache hit
                if ($this->logger->isHandling(Logger::INFO))
                    $this->logger->info("Cached document found but has expired, removing it.");
                $this->cacheDb->deleteOne([
                    'hash' => $hash
                ]);
                return null;
            }
        } else
            return null;
    }

    /**
     * Write a response from a query to an API into the cache db along with the query and the date it was obtained,
     * and optionally the serice name.
     *
     * The JSON documents stored looks like this:
     * {
     *   "hash" : "cb10dc6d18ad7fb3160bef79699f1ef704e29bcf13aafa75cfa25cb951ce89ec",
     *   "service" : "gbif/getOccurrencesByName_sd",
     *   "fetch_date" : "2023-06-12 00:08:35",
     *   "query" : "http://api.gbif.org/v1/occurrence/search?q=Delphinapterus%20leucas&limit=5000",
     *   "payload" : "..."
     * }
     *
     * @param string $query
     *            the Web API query. Its hash is used as a key
     * @param string $resp
     *            the Web API query response to store in the cache db
     * @param string $service
     *            the Web API service name
     */
    public function writeApiResponse($query, $resp, $service = null)
    {
        try {
            $now = (new \DateTime('now'));
            $this->cacheDb->insertOne([
                'hash' => hash("sha256", $query),
                'service' => $service,
                'fetch_date' => $now->format('Y-m-d H:i:s'),
                'query' => $query,
                'payload' => $resp
            ]);
        } catch (Exception $e) {
            $this->logger->warning("Cannot write to cache db: " . (string) $e);
        }
    }

    /**
     * Retrieve a document written with writeApiResponse() from the cache db and return it.
     *
     * If it is found and the expiration date is passed, the document is deleted from the cache db.
     *
     * @param string $query
     *            the Web API query. Its hash is used as a key
     * @return string the cached document if found, null otherwise.
     */
    public function readApiResponse($query)
    {
        $hash = hash("sha256", $query);
        $found = $this->cacheDb->findOne([
            'hash' => $hash
        ]);
        if ($found != null) {

            // Create the date interval corresponding to the cache expiration duration
            $context = Context::getInstance();
            $fetchDate = new \DateTime($found['fetch_date']);
            $cacheExpiresAt = $fetchDate->add(new \DateInterval('PT' . $context->getConfigParam('cache_expires_after', self::CACHE_EXP_SEC) . 'S'));

            if ($this->logger->isHandling(Logger::DEBUG))
                $this->logger->debug("Cached document expires at: " . $cacheExpiresAt->format('Y-m-d H:i:s'));

            if ($cacheExpiresAt >= (new \DateTime('now'))) {
                // If the expiration date is not passed, the document can be returned as is

                // Save the date and time at which the document was fetched (for provenance needs)
                $context->setCacheHitDateTime($fetchDate);

                return $found['payload'];
            } else {
                // If the expiration date is passed, remove the document from the cache and return null = no cache hit
                if ($this->logger->isHandling(Logger::INFO))
                    $this->logger->info("Cached document found but has expired, removing it.");
                $this->cacheDb->deleteOne([
                    'hash' => $hash
                ]);
                return null;
            }
        } else
            return null;
    }
}
?>