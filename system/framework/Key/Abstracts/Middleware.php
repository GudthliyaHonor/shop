<?php
/**
 * Created by PhpStorm.
 * User: roy
 * Date: 2016/7/7
 * Time: 11:08
 */

namespace Key\Abstracts;


/**
 * Class Middleware
 * @package Key\Abstracts
 */
abstract class Middleware
{

    /**
     * Log the message.
     *
     * @param string $message
     */
    public static function log($message)
    {
        if (!is_string($message)) {
            $message = var_export($message, true);
        }

        error_log('[Middleware] ' . $message);
    }

    /**
     * Log error message.
     *
     * @param string $message
     */
    public static function error($message)
    {
        if (!is_string($message)) {
            $message = var_export($message, true);
        }

        error_log('[Middleware] ' . $message);
    }
}