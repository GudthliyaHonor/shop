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
use Key\Interfaces\LoggerInterface;
use Logger;
use LoggerLevel;

/**
 * Log to database.
 *
 * @package Key\Log
 * @author Guanghui Li <liguanghui2006@163.com>
 */
class DatabaseLogger implements LoggerInterface
{
    const MONGODB_URL_PREFIX = 'mongodb://';
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

    /** @var \Pimple\Container */
    protected $app;

    /**
     * @var int
     */
    protected $loggerLevel = LoggerLevel::ALL;

    public function __construct($name, $app)
    {
        $this->name = $name;
        $this->app = $app;

        $logger = new Logger($name);
        $logger->setLevel(LoggerLevel::getLevelAll());

        $this->logger = $logger;

        $this->addAppender();
    }

    /**
     * 给log增加mongodb的appender
     */
    public function addAppender()
    {
        $databaseLog = new LoggerAppenderMongoDBCustom($this->name);
        $config = $this->app['config']['database.connections']['mongodb'];
        $default = $config['default'];
        $uri = "mongodb://{$default['username']}:{$default['password']}@{$default['host']}:{$default['port']}";
        $port = $config['default']['port'];
        $databaseName = $config['default']['database'];
        $collectionName = isset($config['logCollection']) ? $config['logCollection'] : 'mongodb_logs';
        $databaseLog->setHost($uri);
        $databaseLog->setPort($port);
        $databaseLog->setDatabaseName($databaseName);
        $databaseLog->setCollectionName($collectionName);
        $databaseLog->setThreshold($this->loggerLevel);
        $databaseLog->activateOptions();
        $this->logger->addAppender($databaseLog);
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