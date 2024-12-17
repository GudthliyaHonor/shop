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


//use Predis\Client;
use Redis as Client;
use Key\Interfaces\Cache;

/**
 * Redis cache.
 *
 * @package Key\Cache
 * @author Guanghui Li <liguanghui2006@163.com>
 */
class Redis implements Cache
{
    const DEFAULT_PORT = 6379;
    const DEFAULT_TIMEOUT = 0.0;
    const DEFAULT_AUTH = '';
    const DEFAULT_PREFIX = null;

    /**
     * Redis client object.
     *
     * @var \Redis
     */
    protected $client;

    /**
     * Redis server host
     * @var string
     */
    protected $host;

    /**
     * Redis server port
     *
     * @var int
     */
    protected $port;

    /**
     * @var float
     */
    protected $timeout;

    /**
     * Connection password
     * @var null|string
     */
    protected $auth;

    /**
     * Prefix string for each key
     * @var null|string
     */
    protected $prefix;

    /**
     * Redis Database index.
     *
     * @var int
     */
    protected $db = 0;

    /**
     * Redis constructor.
     * @param string $host Redis server host, such as 127.0.0.1
     * @param int $port Redis server port
     * @param float $timeout Connection timeout
     * @param string|null $auth Authenticate the connection using a password
     * @param string $prefix Prefix string for each key
     * @param int $db DB index
     */
    public function __construct($host, $port = self::DEFAULT_PORT, $timeout = self::DEFAULT_TIMEOUT, $auth = self::DEFAULT_AUTH, $prefix = self::DEFAULT_PREFIX, $db = 0)
    {
        //$this->client = new Client($uri, $options);

        $this->host = $host;
        $this->port = (int) $port;
        $this->timeout = (float) $timeout;
        $this->auth = $auth ? $auth : null;
        $this->prefix = $prefix ? $prefix : '';

        $this->client = new Client();
        // $this->client->open($host, $port, $timeout);
        if (version_compare(phpversion(), '7.4.0', '>=')) {
            $this->client->pconnect($host, $port, $timeout);
        } else {
            $this->client->connect($host, $port, $timeout);
        }
        if ($auth) {
            $this->client->auth($auth);
        }
        if ($this->prefix) {
            $this->client->setOption(\Redis::OPT_PREFIX, $this->prefix);
        }
        if ($db) {
            $this->db = $db;
            if (!$this->select($db)) {
                $this->db = $this->client->getDbNum();
            }
        }
    }

    /**
     * Get Redis client object.
     *
     * @return \Redis Redis client object
     */
    public function getRedis()
    {
        return $this->client;
    }

    /**
     * Get Redis client object.
     *
     * @return \Redis Redis client object
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Get the cache item for key.
     *
     * @see http://redis.io/commands/get
     *
     * @param string $key The cache key
     * @param mixed $default The default value to return if data key does not exist
     *
     * @return mixed The key's value, or the default value
     */
    public function get($key, $default = null)
    {
        $key = $this->prefix.$key;

        return $this->client->get($key) !== false ? $this->client->get($key) : $default;
    }

    /**
     * Set the cache item. If ttl is set, the key with a time to live.
     *
     * @param string $key The cache key
     * @param mixed $value The cache value
     * @param int|null $ttl A time to live for the cache, second; default 30 days
     *
     * @return self
     *
     * @exceptions \Key\Exception\CacheException
     */
    public function set($key, $value, $ttl = 2592000)
    {
        $key = $this->prefix.$key;

        if (!is_string($value)) {
            $value = json_encode($value);
        }

        $limit = env('REDIS_MAX_VALUE_LENGTH', 0);
        if ($limit && strlen($value) > $limit) {
            error_log('[WARN] value is too long for limitation: ' . $limit);
            $strict = env('REDIS_LIMIT_STRICT');
            if ($strict) {
                error_log('[WARN] Strict mode on!');
                return false;
            }
        }

        if (isset($ttl)) {
            $this->client->setex($key, $ttl, $value);
        } else {
            $this->client->set($key, $value);
        }

        return $this;
    }

    /**
     * Sets multiple key-value pairs in one atomic command.
     *
     * @param array $pairs Array Pairs: array(key => value, ...)
     * @return bool Only returns TRUE if all the keys were set
     */
    public function mset(array $pairs)
    {
        return $this->client->mset($pairs);
    }

    /**
     * Increase the cache item.
     *
     * @see http://redis.io/commands/incr
     * @see http://redis.io/commands/incrby
     *
     * @param string $key The cache key
     * @param int $increment Increment value
     *
     * @return mixed
     */
    public function increase($key, $increment = 1)
    {
        $key = $this->prefix.$key;

        $increment = abs((int) $increment);
        if ($increment <= 1) {
            $this->client->incr($key);
        } else {
            $this->client->incrby($key, $increment);
        }

        return $this;
    }

    /**
     * Decrease the cache item.
     *
     * @see http://redis.io/commands/decr
     * @see http://redis.io/commands/decrby
     *
     * @param string $key The cache key
     * @param int $decrement decrement value
     *
     * @return mixed
     */
    public function decrease($key, $decrement = 1)
    {
        $key = $this->prefix.$key;

        $decrement = abs((int) $decrement);

        if ($decrement <= 1) {
            $this->client->decr($key);
        } else {
            $this->client->decrby($key, $decrement);
        }

        return $this;
    }

    /**
     * Remove the specified key. A key is ignored if it does not exists.
     *
     * @see http://redis.io/commands/del
     *
     * @param string|array $key
     * @return mixed
     */
    public function delete($key)
    {
        if (is_array($key)) {
            $tmp = array();
            foreach ($key as $item) {
                $tmp[] = $this->prefix . $item;
            }
            $key = $tmp;

            if (count($key) > env('REDIS_DEBUG_DEL_COUNT', 1000)) {
                error_log('[debug]keys to be deleted in one time: ' . json_encode(array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 0, 3)));
                $chunks = array_chunk($key, 1000);
                foreach($chunks as $chunk) {
                    $this->client->del($chunk);
                }
                return $this;
            }
        } else {
            $key = $this->prefix.$key;
        }


        $this->client->del($key);

        return $this;
    }

    /**
     * Change the selected database for the current connection.
     *
     * @param int $index The database number to switch to
     * @return bool TRUE in case of success, FALSE in case of failure.
     */
    public function select($index)
    {
        if ($this->db != $index) {
            error_log('[WARN] redis db index: ' . $this->db . ' -> ' . $index);
            error_log('[WARN] trace: ' . var_export(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10), true));
        }
        if ($this->client->select($index)) {
            $this->db = $index;
            return true;
        }

        return false;
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
        $key = $this->prefix.$key;
        return $this->client->exists($key);
    }

    /**
     * Publish messages to channels. Warning: this function will probably change in the future.
     *
     * @param string $channel a channel to publish to
     * @param string $msg string
     */
    public function publish($channel, $msg)
    {
        $this->client->publish($channel, $msg);
    }

    /**
     * Subscribe to channels.
     *
     * @param array             $channels an array of channels to subscribe to
     * @param string | array    $callback either a string or an array($instance, 'method_name').
     * The callback function receives 3 parameters: the redis instance, the channel name, and the message.
     */
    public function subscribe(array $channels, $callback)
    {
        $this->client->subscribe($channels, $callback);
    }

    /**
     * Returns the time to live left for a given key, in seconds. If the key doesn't exist, FALSE is returned.
     *
     * @param   string  $key
     * @return  int     the time left to live in seconds.
     */
    public function ttl($key)
    {
        return $this->client->ttl($key);
    }

    /**
     * Disconnects from the Redis instance, except when pconnect is used.
     */
    public function close()
    {
        $this->client->close();
    }

    /**
     * Returns the values of all specified keys.
     *
     * @param array $keys
     * @return array
     */
    public function mget(array $keys)
    {
        if (count($keys) === 0) {
            return [];
        }
        return $this->client->mget($keys);
    }

    /**
     * Set expire time for the key.
     * 
     * @param string $key
     * @param int $ttl
     */
    public function expire($key, $ttl)
    {
        return $this->client->expire($key, $ttl);
    }

    public function __toString()
    {
        return json_encode([
            'host' => $this->host,
            'port' => $this->port,
            'prefix' => $this->prefix,
            'db' => $this->db,
        ]);
    }
}