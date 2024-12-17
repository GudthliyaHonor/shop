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


use Key\Interfaces\Cache;

/**
 * TODO: Memcache
 *
 * @package Key\Cache
 * @author Guanghui Li <liguanghui2006@163.com>
 */
class Memcache implements Cache
{
    /**
     * Get the cache item for key.
     *
     * @param string $key The cache key
     * @param mixed $default The default value to return if data key does not exist
     *
     * @return mixed The key's value, or the default value
     */
    public function get($key, $default = null)
    {
        // TODO: Implement get() method.
    }

    /**
     * Set the cache item.
     *
     * @param string $key The cache key
     * @param mixed $value The cache value
     * @param int|null $ttl A time to live for the cache
     *
     * @return self
     *
     * @exceptions \Key\Exception\CacheException
     */
    public function set($key, $value, $ttl = null)
    {
        // TODO: Implement set() method.
    }

    /**
     * Increase the cache item.
     *
     * @param string $key The cache key
     * @param int $increment Increment value
     *
     * @return mixed
     */
    public function increase($key, $increment = 1)
    {
        // TODO: Implement increase() method.
    }

    /**
     * Decrease the cache item.
     *
     * @param string $key The cache key
     * @param int $decrement decrement value
     *
     * @return mixed
     */
    public function decrease($key, $decrement = 1)
    {
        // TODO: Implement decrease() method.
    }

    /**
     * Remove the specified key. A key is ignored if it does not exists.
     *
     * @param string $key
     * @return mixed
     */
    public function delete($key)
    {
        // TODO: Implement delete() method.
    }

    /**
     * Change the selected database for the current connection.
     *
     * @param int $index The database number to switch to
     *
     * @return bool TRUE in case of success, FALSE in case of failure.
     */
    public function select($index)
    {
        // TODO: Implement select() method.
    }

    /**
     * Verify if the specified key exists.
     *
     * @param string $key The cache key
     *
     * @return bool
     */
    public function exists($key)
    {
        // TODO: Implement exists() method.
    }

    /**
     * Disconnects
     */
    public function close()
    {
        // TODO: Implement close() method.
    }

    /**
     * Returns the values of all specified keys.
     *
     * @param array $keys
     * @return array
     */
    public function mget(array $keys)
    {
        // TODO: Implement mget() method.
    }

    /**
     * Sets multiple key-value pairs in one atomic command.
     *
     * @param array $pairs Array Pairs: array(key => value, ...)
     * @return bool Only returns TRUE if all the keys were set
     */
    public function mset(array $pairs)
    {
        // TODO: Implement mset() method.
    }

    /**
     * Get ttl of key in the cache
     *
     * @param string $key
     * @return int
     */
    public function ttl($key)
    {
        // TODO: Implement mset() method.
    }

    /**
     * Set expire time for the key.
     * 
     * @param string $key
     * @param int $ttl
     */
    public function expire($key, $ttl)
    {
        // TODO: Implement mset() method.
    }
}