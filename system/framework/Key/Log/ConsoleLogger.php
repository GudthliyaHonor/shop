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

/**
 * Class ConsoleLogger
 * @package Key\Log
 */
class ConsoleLogger implements LoggerInterface
{
    /**
     * Convert to string.
     *
     * @param $message
     * @return string
     */
    protected function convertToString($message)
    {
        if (is_object($message) && in_array("__toString", get_class_methods($message))) {
            $message = strval($message->__toString());
        } else {
            $message = strval($message);
        }

        return $message;
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
        error_log('[TRACE]'.static::convertToString($message));
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
        error_log('[INFO]'.static::convertToString($message));
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
        error_log('[DEBUG]'.static::convertToString($message));
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
        error_log('[WARN]'.static::convertToString($message));
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
        error_log('[ERROR]'.static::convertToString($message));
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
        error_log('[FATAL]'.static::convertToString($message));
    }

}
