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


use Pimple\Container;

class MySqlManager extends MongoManager
{
    protected static $instance;

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

            $config = $container['config']['database.connections']['mysql'];
            $host = $config['host'];
            $port = $config['port'];
            $username = $config['username'];
            $password = $config['password'];
            $dbname = $config['database'];
            $charset = $config['charset'];

            static::$instance = new MySql($host, $dbname, $username, $password, $charset, $port);
        }

        return static::$instance;
    }

}