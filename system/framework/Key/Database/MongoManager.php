<?php

/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace Key\Database;

use Key\Constants;
use Key\Exception\DatabaseException;
use Pimple\Container;

/**
 * Class MongoManager
 * @package Key\Database
 */
class MongoManager
{
    protected static $instance;

    private static $globalInstance;

    /**
     * Can not use normal construct
     */
    private function __construct()
    {
    }

    /**
     * Disable clone.
     */
    public function __clone()
    {
        trigger_error('DatabaseManager can not be clone', E_USER_ERROR);
    }

    /**
     * Get Database connection instance.
     * @param \Pimple\Container $container
     * @return \Key\Database\Mongodb
     *
     * @throws DatabaseException
     */
    public static function getInstance(Container $container)
    {

        if (!isset(static::$instance)) {
            $uid = 0; //$container['session']->get(Constants::SESSION_USR_KEY, 0);
            $aid = 0; //$container['session']->get(Constants::SESSION_ACCOUNT_KEY, 0);

            $config = $container['config']['database.connections']['mongodb'];
            $uri = static::getUri($config, 'default', $db);

            static::$instance = new Mongodb($uri, $db, $uid, $aid);
        }

        return static::$instance;
    }

    /**
     * Get the global db instance.
     *
     * @param Container $container
     * @return \Key\Database\Mongodb
     */
    public static function getGlobalInstance(Container $container)
    {
        if (!isset(static::$globalInstance)) {
            $config = $container['config']['database.connections']['mongodb'];
            $uri = static::getUri($config, 'global', $db);
            static::$globalInstance = new Mongodb($uri, $db);
        }
        return static::$globalInstance;
    }

    protected static function getUri($config, $name = 'default', &$db)
    {
        $db = $config[$name]['database'];

        if (isset($config[$name]['uri']) && $config[$name]['uri']) {
            return $config[$name]['uri'];
        }

        $host = $config[$name]['host'];
        $port = $config[$name]['port'];
        $username = $config[$name]['username'];
        $password = $config[$name]['password'];

        if ($username) {
            return "mongodb://{$username}:{$password}@{$host}:{$port}/{$db}";
        } else {
            return "mongodb://{$host}:{$port}/{$db}";
        }
    }
}
