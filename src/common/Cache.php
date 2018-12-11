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
     * Cache expiration time.
     *
     * @var \DateInterval
     */
    private $cacheExpiresAfter = null;

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
        $this->logger = $context->getLogger();

        if ($context->hasConfigParam('cache_endpoint'))
            $this->cacheEndpoint = $context->getConfigParam('cache_endpoint');

        if ($context->hasConfigParam('cache_db_name'))
            $this->cacheDbName = $context->getConfigParam('cache_db_name');

        // Create the database client and default collection: 'cache'
        $client = new \MongoDB\Client($this->cacheEndpoint);
        $this->cacheDb = $client->selectCollection($this->cacheDbName, 'cache');

        // Create the date interval corresponding to the cache expiration duration
        $cacheExpirationSec = $context->hasConfigParam('cache_expires_after') ? $context->getConfigParam('cache_expires_after') : self::CACHE_EXP_SEC;
        $this->cacheExpiresAfter = new \DateInterval('PT' . $cacheExpirationSec . 'S');
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
     * Write a document (query response) to the cache db along with the query and an expiration date.
     *
     * @param string $query
     *            the Web API query. Its hash is used as a key
     * @param string $resp
     *            the Web API query response to store in the cache db
     * @param string $service
     *            the Web API service name
     */
    public function write($query, $resp, $service = null)
    {
        try {
            $expDate = (new \DateTime('now'))->add($this->cacheExpiresAfter);
            $this->cacheDb->insertOne([
                'hash' => hash("sha256", $query),
                'service' => $service,
                'expires' => $expDate->format('Y-m-d H:i:s'),
                'query' => $query,
                'payload' => $resp
            ]);
        } catch (Exception $e) {
            $this->logger->warning("Cannot write to cache db: " . (string) $e);
        }
    }

    /**
     * Try to get a document from the cache db and return it.
     * If it is found and the expiration date is passed, the document is deleted from the cache db.
     *
     * @param string $query
     *            the Web API query. Its hash is used as a key
     * @return string the cached document if found, null otherwise.
     */
    public function read($query)
    {
        $found = $this->cacheDb->findOne([
            'hash' => hash("sha256", $query)
        ]);
        if ($found != null) {
            if ((new \DateTime($found['expires'])) >= (new \DateTime('now')))
                // If the expiration date is not passed, return the document
                return $found['payload'];
            else {
                if ($this->logger->isHandling(Logger::INFO))
                    $this->logger->info("Cached document found but has expired, removing it.");
                $this->cacheDb->deleteOne([
                    'hash' => hash("sha256", $query)
                ]);
                return null;
            }
        } else
            return null;
    }
}
?>
