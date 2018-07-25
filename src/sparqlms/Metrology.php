<?php
namespace frmichel\sparqlms;

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;

/**
 * Management of the metrology measures, stored in a csv file.
 *
 * Metrology traces are logged at level Logger:INFO or below.
 * Create an instance with level Logger:WARNING or higher to deactivate metrology traces.
 *
 * @author fmichel
 */
class Metrology
{

    /**
     *
     * @var Metrology
     */
    private static $singleton = null;

    /**
     *
     * @var \Monolog\Logger
     */
    private $metro = null;

    /**
     *
     * @var integer
     */
    private $level = null;

    /**
     * Set of timers that can be started, stoped and written to the csv file
     *
     * @var array
     */
    private $timers = array();

    /**
     * Initialize the logger where metrology traces will be written
     *
     * @param integer $level
     *            the level starting at which traces are written.
     *            One of Logger::INFO, Logger::WARNING, Logger::DEBUG etc. (see Monolog\Logger.php)
     */
    private function __construct($level)
    {
        if (array_key_exists('SCRIPT_FILENAME', $_SERVER))
            $scriptName = basename($_SERVER['SCRIPT_FILENAME']);
        else
            $scriptName = basename(__FILE__);

        $handler = new StreamHandler(__DIR__ . '/../../logs/metro.csv', $level, true, 0666);
        $handler->setFormatter(new LineFormatter("%message% \n"));
        $this->metro = new Logger("");
        $this->metro->pushHandler($handler);
        $this->level = $level;
    }

    /**
     * Create and/or get singleton instance
     *
     * @param integer $level
     * @return Metrology
     */
    public static function getInstance($level = Logger::INFO)
    {
        if (is_null(self::$singleton))
            self::$singleton = new Metrology($level);
        return self::$singleton;
    }

    public function isHandling($level)
    {
        return $this->metro->isHandling($level);
    }

    /**
     * Add any free measure to the metrology file in CSV format
     *
     * @param string $service
     *            name of the service invoked e.g. 'flickr/getPhotoById'
     * @param string $message
     *            free text message
     * @param float $mes
     *            measure to write
     */
    public function appendMessage($service, $message, $mes)
    {
        if ($this->metro->isHandling(Logger::INFO))
            $this->metro->info("$service; $message; $mes");
    }

    /**
     * Add time measures to the metrology file in CSV format
     *
     * @param string $service
     *            name of the service invoked e.g. 'flickr/getPhotoById'
     * @param string $message
     *            free text message
     * @param float $i1
     *            index of the first timer to write
     * @param float $i2
     *            index of the (optional) second timer to write
     */
    public function appendTimer($service, $message, $i1, $i2 = -1)
    {
        if ($this->metro->isHandling(Logger::INFO)) {
            $t1 = number_format($this->timers[$i1], 4, $dec_point = ",", $thousands_sep = "");
            $t2 = number_format($this->timers[$i2], 4, $dec_point = ",", $thousands_sep = "");

            if ($i2 == - 1)
                $this->metro->info("$service; $message; $t1");
            else
                $this->metro->info("$service; $message; $t1; $t2");
        }
    }

    public function startTimer($i)
    {
        $this->timers[$i] = microtime(true);
    }

    public function stopTimer($i)
    {
        $this->timers[$i] = microtime(true) - $this->timers[$i];
    }
}
?>
