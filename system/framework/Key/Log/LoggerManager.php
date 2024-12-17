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
use Pimple\Container;

/**
 * Manage loggers.
 *
 * @package Key\Log
 * @author Guanghui Li <liguanghui2006@163.com>
 */
class LoggerManager
{
    const DEFAULT_KEY = 'App';
    const MONGO_KEY = 'Mongodb';
    const CONSOLE_KEY = '__CONSOLE__';
    const DUMMY_KEY = '__DUMMY__';

    /**
     * Default options.
     *
     * @var array
     */
    private static $defaults = array(
        'pattern' => '[%date][%logger][%p] %msg%newline',
        'daily' => true // only for file logger
    );

    /**
     * Filtered options.
     *
     * @var null|array
     */
    private static $options = array();

    /**
     * Instances.
     *
     * @var array
     */
    private static $instances = array();

    /** @var \Key\Interfaces\LoggerInterface */
    private static $lastInstance = null;

    /**
     * Private constructor.
     */
    private function __construct(){

    }

    /**
     * @override The class can NOT be cloned
     */
    public function __clone() {
        trigger_error('LogManager can not be cloned.', E_USER_ERROR);
    }

    /**
     * @param null $options
     * @return array|null
     */
    public static function configure($options = null)
    {
        if (empty($options) || !is_array($options)) {
            $options = array();
        }

        $options = array_replace(static::$defaults, static::$options, $options);

        return $options;
    }

    /**
     * Get default logger (File logger) instance.
     *
     * @param string $name Logger name
     * @param null|array $options File logger configuration
     * @return \Key\Interfaces\LoggerInterface
     */
    public static function getDefaultInstance($name = self::DEFAULT_KEY, $options = null)
    {
        return static::getFileInstance($name, static::configure($options));
    }

    /**
     * Get Console logger instance.
     *
     * @return \Key\Interfaces\LoggerInterface
     */
    public static function getConsoleInstance()
    {
        if (!isset(static::$instances[static::CONSOLE_KEY])) {
            static::$instances[static::CONSOLE_KEY] = new ConsoleLogger();
        }

        return static::$instances[static::CONSOLE_KEY];
    }

    /**
     * Get Database logger instance.
     *
     * @return \Key\Interfaces\LoggerInterface
     */
    public static function getDatabaseInstance()
    {
        // TODO: not implement
    }

    /**
     * Get File logger instance.
     *
     * @param string $name Logger name
     * @param null|array $options File logger configuration
     * @return \Key\Interfaces\LoggerInterface
     */
    public static function getFileInstance($name = self::DEFAULT_KEY, $options = null)
    {
        if (!isset(static::$instances[$name])) {
            static::$instances[$name] = new FileLogger($name, static::configure($options));
        }

        return static::$instances[$name];
    }

    /**
     * Get Mongodb logger instance.
     *
     * @param Container $container
     * @param string $name Logger name
     *
     * @return \Key\Interfaces\LoggerInterface
     */
    public static function getMongoInstance(Container $container, $name = self::MONGO_KEY)
    {
        if (!isset(static::$instances[$name])) {
            static::$instances[$name] = new DatabaseLogger($name, $container);
        }

        static::$lastInstance = static::$instances[$name];

        return static::$instances[$name];
    }

    /**
     * Get dummy logger instance.
     *
     * @return \Key\Interfaces\LoggerInterface
     */
    public static function getDummyInstance()
    {

        if (!isset(static::$instances[static::DUMMY_KEY])) {
            static::$instances[static::DUMMY_KEY] = new DummyLogger();
        }

        return static::$instances[static::DUMMY_KEY];
    }

    /**
     * Log a message object with the TRACE level.
     *
     * @param mixed $message message
     * @param Exception $throwable Optional throwable information to include
     *   in the logging event.
     */
    public static function trace($message, $throwable = null)
    {
        if (static::$lastInstance) {
            static::$lastInstance->trace($message, $throwable);
        }
    }

    /**
     * Log a message object with the INFO level.
     *
     * @param mixed $message message
     * @param Exception $throwable Optional throwable information to include
     *   in the logging event.
     */
    public static function info($message, $throwable = null)
    {
        if (static::$lastInstance) {
            static::$lastInstance->info($message, $throwable);
        }
    }

    /**
     * Log a message object with the DEBUG level.
     *
     * @param mixed $message message
     * @param Exception $throwable Optional throwable information to include
     *   in the logging event.
     */
    public static function debug($message, $throwable = null)
    {
        if (static::$lastInstance) {
            static::$lastInstance->debug($message, $throwable);
        }
    }

    /**
     * Log a message object with the WARN level.
     *
     * @param mixed $message message
     * @param Exception $throwable Optional throwable information to include
     *   in the logging event.
     */
    public static function warn($message, $throwable = null)
    {
        if (static::$lastInstance) {
            static::$lastInstance->warn($message, $throwable);
        }
    }

    /**
     * Log a message object with the ERROR level.
     *
     * @param mixed $message message
     * @param Exception $throwable Optional throwable information to include
     *   in the logging event.
     */
    public static function error($message, $throwable = null)
    {
        if (static::$lastInstance) {
            static::$lastInstance->error($message, $throwable);
        }
    }

    /**
     * Log a message object with the FATAL level.
     *
     * @param mixed $message message
     * @param Exception $throwable Optional throwable information to include
     *   in the logging event.
     */
    public static function fatal($message, $throwable = null)
    {
        if (static::$lastInstance) {
            static::$lastInstance->fatal($message, $throwable);
        }
    }
}
