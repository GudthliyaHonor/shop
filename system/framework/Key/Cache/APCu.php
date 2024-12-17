<?php
/**
 * Key Framework.
 * @copyright yidianzhishi.com
 */

namespace Key\Cache;


use Key\Interfaces\Cache;

/**
 * APCu in-memory key-value cache.
 *
 * @package Key\Cache
 * @author Guanghui Li <liguanghui2006@163.com>
 */
class APCu implements Cache
{
    protected $prefix = '';

    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
        return $this;
    }

   /**
     * Get the cache item for key.
     * @see https://www.php.net/manual/en/function.apcu-fetch.php
     * 
     * @param string $key The cache key
     * @param mixed $default The default value to return if data key does not exist
     *
     * @return mixed The key's value, or the default value
     */
    public function get($key, $default = null)
    {
        if (!function_exists('apcu_fetch')) return false;
        return apcu_fetch($this->prefix . $key) ?: $default;
    }

    /**
     * Set the cache item.
     * @see https://www.php.net/manual/en/function.apcu-store.php
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
        if (!function_exists('apcu_store')) return false;
        echo 'set key: ' . $key . PHP_EOL;
        $value = is_string($value) ? $value : json_encode($value);
        return apcu_store($this->prefix . $key, $value, is_null($ttl) ? 0 : $ttl);
    }

    /**
     * Increase the cache item.
     * @see https://www.php.net/manual/en/function.apcu-inc.php
     * @param string $key The cache key
     * @param int $increment Increment value
     *
     * @return mixed
     */
    public function increase($key, $increment = 1)
    {
        if (!function_exists('apcu_inc')) return false;
        return apcu_inc($this->prefix . $key, $increment);
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
        if (!function_exists('apcu_dec')) return false;
        return apcu_dec($this->prefix . $key, $decrement);
    }

    /**
     * Remove the specified key. A key is ignored if it does not exists.
     * @see https://www.php.net/manual/en/function.apcu-delete.php
     * @param string $key
     * @return mixed
     */
    public function delete($key)
    {
        if (!function_exists('apcu_delete')) return false;
        return apcu_delete($this->prefix . $key);
    }

    /**
     * Change the selected database for the current connection.
     * APCu not support.
     * 
     * @param int $index The database number to switch to
     *
     * @return bool TRUE in case of success, FALSE in case of failure.
     */
    public function select($index)
    {
        return $this;
    }

    /**
     * Verify if the specified key exists.
     * @see https://www.php.net/manual/en/function.apcu-exists.php
     * @param string $key The cache key
     *
     * @return bool
     */
    public function exists($key)
    {
        if (!function_exists('apcu_exists')) return false;
        return apcu_exists($this->prefix . $key);
    }

    /**
     * Disconnects
     */
    public function close()
    {
        return $this;
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