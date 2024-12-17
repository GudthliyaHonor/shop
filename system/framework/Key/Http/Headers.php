<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */
namespace Key\Http;


use Key\Collection;

/**
 * Class Headers
 * @package Key\Http
 * @author Guanghui Li <liguanghui2006@163.com>
 */
class Headers extends Collection
{
    /**
     * Special HTTP headers that do not have the "HTTP_" prefix
     *
     * @var array
     */
    protected static $special = array(
        'CONTENT_TYPE' => 1,
        'CONTENT_LENGTH' => 1,
        'PHP_AUTH_USER' => 1,
        'PHP_AUTH_PW' => 1,
        'PHP_AUTH_DIGEST' => 1,
        'AUTH_TYPE' => 1,
    );

    /**
     * Create Headers from environment.
     *
     * @param Environment $env
     * @return self
     */
    public static function createFromEnvironment(Environment $env)
    {
        $data = array();
        foreach ($env as $key => $value) {
            $key = strtoupper($key);
            if (isset(static::$special[$key]) || strpos($key, 'HTTP_') === 0) {
                if ($key !== 'HTTP_CONTENT_LENGTH') {
                    $data[$key] = $value;
                }
            }
        }

        return new static($data);
    }

    /**
     * Get all items in collection
     *
     * @return array The collection's source data
     */
    public function all()
    {
        $all = parent::all();
        $result = array();
        foreach ($all as $key => $item) {
            $result[$item['originalKey']] = $item['value'];
        }

        return $result;
    }

    /**
     * Determine key exists in the collection.
     *
     * @param string $key the data key
     * @return bool
     */
    public function has($key)
    {
        return parent::has($this->normalizeKey($key));
    }

    /**
     * Get collection item for key.
     *
     * @param string $key The data key
     * @param mixed $default The default value to return when key does not exists in the collection, default is null
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if ($this->has($key)) {
            $item = parent::get($this->normalizeKey($key), $default);
            return $item['value'];
        }

        return $default;
    }

    /**
     * Add a value into the collection
     * @param string $key
     * @param mixed $value
     */
    public function add($key, $value)
    {
        $oldValues = $this->get($key, array());
        $newValues = is_array($value) ? $value : array($value);
        $this->set($key, array_merge($oldValues, array_values($newValues)));
    }

    /**
     * Set index's value.
     *
     * @param string $key
     * @param mixed $value
     */
    public function set($key, $value)
    {
        if (!is_array($value)) {
            $value = array($value);
        }
        parent::set($this->normalizeKey($key), array(
            'value' => $value,
            'originalKey' => $key
        ));
    }

    /**
     * Remove a value from the collection.
     *
     * @param string $key The data key
     */
    public function remove($key)
    {
        parent::remove($this->normalizeKey($key));
    }


    /**
     * Normalize header name.
     *
     * @param string $key The case-insensitive header name
     * @return string Normalized header name
     */
    public function normalizeKey($key)
    {
        $key = strtr(strtolower($key), '_', '-');
        if (strpos($key, 'http-') === 0) {
            $key = substr($key, 5);
        }

        return $key;
    }
}