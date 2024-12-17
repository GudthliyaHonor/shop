<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */
namespace Key\Cache;


use Key\Exception\CacheException;

/**
 * Retrieves the cache instance, such as Redis, Memcached, etc.
 *
 * @package Key\Cache
 * @author Guanghui Li <liguanghui2006@163.com>
 */
class CacheManager
{
    /** @var \Key\Interfaces\Cache */
    private static $instance;

    /**
     * Retrieves the redis instance.
     *
     * @param array $config
     * @return \Key\Interfaces\Cache
     *
     * @throws CacheException
     */
    public static function getInstance($config = [])
    {
        if (self::$instance) {
            return self::$instance;
        }

        $type = null;
        if (isset($config['cache.default'])) {
            $type = $config['cache.default'];
        }

        switch ($type) {
            case 'redis':
                if (extension_loaded('redis')) {

                    $config = static::getStore('redis', $config);

                    $host = $config['host'];
                    $port = isset($config['port']) ? $config['port'] : 6379;
                    $timeout = isset($config['timeout']) ? $config['timeout'] : 0.0;
                    $auth = isset($config['password']) ? $config['password'] : null;
                    $prefix = isset($config['prefix']) && $config['prefix'] ? $config['prefix'] . ':' : null;
                    $db = isset($config['database']) && $config['database'] ? (int) $config['database'] : 0;

                    self::$instance = new Redis($host, $port, $timeout, $auth, $prefix, $db);
                } else {
                    throw new CacheException('The redis extension must be loaded for using this function!');
                }

                break;
            case 'memcache':
                throw new CacheException('Memcache object is not implemented');
                break;
            case 'memcached':
                throw new CacheException('Memcached object is not implemented');
                break;
            default:
                // Using Array to cache
                self::$instance = new ArrayCache();
        }

        return self::$instance;
    }

    protected static function getStore($name, $config = [])
    {
        if (isset($config['cache.stores'][$name]) && ($redis = $config['cache.stores'][$name])) {
            $driver = $redis['driver'];
            $connection = $redis['connection'];

            switch ($driver) {
                case 'phpredis':
                    return $config['database.connections'][$name][$connection];
                    break;
                case 'predis':
                    throw new CacheException('Predis object is not implemented');
                    break;
            }
        }
    }

    /**
     * Disable default class construct.
     */
    private function __construct()
    {
        //
    }

    /**
     * Disable class clone.
     */
    public function __clone()
    {
        throw new \RuntimeException('Cannot clone the class CacheManager.');
    }

    /**
     * Close the connection.
     */
    public function close()
    {
        if (self::$instance) {
            self::$instance->close();
        }
    }

    public static function reconnect($config = [])
    {
        self::$instance = null;
        return self::getInstance($config);
    }
}