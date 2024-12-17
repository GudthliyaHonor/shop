<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace Key\Log;


use Exception;
use Logger;
use LoggerLevel;
use LoggerAppenderFile;
use LoggerLayoutPattern;
use Key\Abstracts\BaseLogger;
use Key\Interfaces\LoggerInterface;

/**
 * Class FileLogger
 * @package Key\Log
 */
class FileLogger implements LoggerInterface
{

    /**
     * File logger name.
     *
     * @var string
     */
    protected $name;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var bool
     */
    protected $daily = true;

    /**
     * @var int
     */
    protected $loggerLevel = LoggerLevel::DEBUG;

    /**
     * @var string
     */
    protected $layoutPattern = '%date [%p] %msg%newline';

    /**
     * File logger constructor.
     *
     * @param string $name Logger name
     * @param null|array $options
     */
    public function __construct($name, $options = null)
    {
        $this->name = $name;

        $this->parseOptions($options);

        $logger = new Logger($name);
        $logger->setLevel($this->getLevel());

        $this->logger = $logger;
        $this->addAppender();
    }

    protected function setThresholdLevel($envLevel)
    {
        $ref = new \ReflectionClass('\\LoggerLevel');
        $envLevel = strtoupper($envLevel);
        if ($ref->hasConstant($envLevel)) {
            $this->loggerLevel = $ref->getConstant($envLevel);
        } else {
            error_log('Property not found: ' . $envLevel);
            $this->loggerLevel = LoggerLevel::DEBUG;
        }
    }

    /**
     * @return LoggerLevel
     */
    protected function getLevel()
    {
        $envLevel = ucfirst(strtolower(env('LOG_LEVEL', 'info')));
        $method = 'getLevel' . $envLevel;
        if (method_exists('\\LoggerLevel', $method)) {
            $this->setThresholdLevel($envLevel);
            return LoggerLevel::$method();
        } else {
            return LoggerLevel::getLevelDebug();
        }
    }

    /**
     * Parse options.
     *
     * @param array|null $options
     */
    protected function parseOptions($options)
    {
        if ($options && is_array($options)) {
            if (isset($options['daily'])) {
                $this->daily = !!$options['daily'];
            }

            if (isset($options['loggerLevel']) && ($level = $options['loggerLevel'])) {
                $this->loggerLevel = $level;
            }

            if (isset($options['layoutPattern']) && ($layoutPattern = $options['layoutPattern'])) {
                $this->layoutPattern = $options['layoutPattern'];
            }

        }
    }

    /**
     * Add file appender.
     *
     * @throws \LoggerException
     */
    protected function addAppender()
    {
        $logPath = env('LOG_FOLDER', APP_LOG_PATH . DS);
        if ($this->daily) {
            $logPath .= date('Y') . DS . date('m') . DS . date('d') . DS;
        }

        // Create an appender which logs to file
        $appFile = new LoggerAppenderFile($this->name);
        $appFile->setFile($logPath . $this->name . '.log');
        $appFile->setAppend(true);
        $appFile->setThreshold($this->loggerLevel);
        $appFile->activateOptions();

        $layout = new LoggerLayoutPattern();
        $layout->setConversionPattern($this->layoutPattern);
        $layout->activateOptions();

        $appFile->setLayout($layout);

        $this->logger->addAppender($appFile);
    }

    /**
     * Log a message object with the TRACE level.
     *
     * @param mixed $message message
     * @param Exception $throwable Optional throwable information to include
     *   in the logging event.
     */
    public function trace($message, $throwable = null)
    {
        $this->logger->trace($message, $throwable);
    }

    /**
     * Log a message object with the INFO level.
     *
     * @param mixed $message message
     * @param Exception $throwable Optional throwable information to include
     *   in the logging event.
     */
    public function info($message, $throwable = null)
    {
        $this->logger->info($message, $throwable);
    }

    /**
     * Log a message object with the DEBUG level.
     *
     * @param mixed $message message
     * @param Exception $throwable Optional throwable information to include
     *   in the logging event.
     */
    public function debug($message, $throwable = null)
    {
        $this->logger->debug($message, $throwable);
    }

    /**
     * Log a message object with the WARN level.
     *
     * @param mixed $message message
     * @param Exception $throwable Optional throwable information to include
     *   in the logging event.
     */
    public function warn($message, $throwable = null)
    {
        $this->logger->warn($message, $throwable);
    }

    /**
     * Log a message object with the ERROR level.
     *
     * @param mixed $message message
     * @param Exception $throwable Optional throwable information to include
     *   in the logging event.
     */
    public function error($message, $throwable = null)
    {
        $this->logger->error($message, $throwable);
    }

    /**
     * Log a message object with the FATAL level.
     *
     * @param mixed $message message
     * @param Exception $throwable Optional throwable information to include
     *   in the logging event.
     */
    public function fatal($message, $throwable = null)
    {
        $this->logger->fatal($message, $throwable);
    }

}
