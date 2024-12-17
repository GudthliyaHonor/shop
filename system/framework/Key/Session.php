<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace Key;

use Key\Cache\Redis as CacheRedis;
use Key\Exception\SessionException;
use Key\Session\Files;
use Key\Session\Redis as SessionRedis;

class Session
{
    protected $app;

    protected $started = false;

    public function __construct(\Pimple\Container $app)
    {
        $this->app = $app;

        $this->prepare();
    }

    protected function getSessionConfig()
    {
        $config = [];
        // $config['session.hash_function'] = $this->app['config']['session.hash_function'] ?? 'sha256';
        $config['session.name'] = $this->app['config']['session.name'];
        $config['session.gc_maxlifetime'] = $this->app['config']['session.lifetime'];
        $config['session.cookie_lifetime'] = $this->app['config']['session.lifetime'];
        //$config['session.'] = $this->app['config']['session.expire_on_close'];
        $config['session.cookie_secure'] = $this->app['config']['session.encrypt'];
        $config['session.cookie_path'] = $this->app['config']['session.path'];
        $config['session.cookie_domain'] = $this->app['config']['session.domain'];
        $config['session.cookie_httponly'] = $this->app['config']['session.http_only'];
        return $config;
    }

    protected function prepare($start = false)
    {
        $this->getSessionHandler($this->app['config']['session.connection']);

        $config = $this->getSessionConfig();

        // if ($this->app->offsetExists('__lifetime__')) {
        //     error_log('[Session] config: ' . $_SERVER['REQUEST_URI'] . ' -- ' . json_encode($config));
        // }
        // if ($this->app->offsetExists('__CURRENT_ACCOUNT_ID__')) {
        //     $aid = $this->app['__CURRENT_ACCOUNT_ID__'];
        //     error_log('[Session] aid lifetime: ' . $aid . ' -- start: ' . var_export($start, true));
        // }

        // TODO: Session Before-Start Middleware here...
        if (isset($this->app['config']['hook.beforeSessionStart'])) {
            $result = call_user_func($this->app['config']['hook.beforeSessionStart'], $this->app, $config);
            if ($result === false) {
                $this->started = true;
                return;
            }
        }

        if (!headers_sent()) {
            foreach($config as $key => $value) {
                ini_set($key, $value);
            }
        }

        if ($start) {
            $this->start(true);
        }
    }

    protected function getRedisSaveHandler($name = 'default')
    {
        $config = $this->app['config']['database.connections']['redis'];
        $host = $config[$name]['host'];
        $password = $config[$name]['password'];
        $port = $config[$name]['port'];
        $database = $config[$name]['database'];

        return "tcp://{$host}:{$port}?database={$database}&auth={$password}";
    }

    protected function getRedisHandler($name = 'default')
    {
        $config = $this->app['config']['database.connections']['redis'];
        $host = $config[$name]['host'];
        $password = $config[$name]['password'];
        $port = (int) $config[$name]['port'];
        $database = $config[$name]['database'];
        $timeout = (float) $config[$name]['timeout'];

        $cacheRedis = new CacheRedis($host, $port, $timeout, $password, $config[$name]['prefix'] . ':', $database);
        return new SessionRedis($cacheRedis->getRedis());
    }

    protected function getSessionHandler($name = 'default')
    {
        $driver = $this->app['config']['session.driver'];
        switch ($driver) {
            case 'files':
                $handler = new Files($this->app['config']['session.files'], $this->app['config']['session.lifetime']);
                break;
            case 'redis':
                // Set redis for save_handler
                try {
                    $handler = $this->getRedisHandler($name);
                } catch (\Exception $ex) {
                    if (env('SESSION_CONNECTION_FAIL_RELEGATION')) {
                        error_log('[WARN] Session exception: ' . $ex->getMessage());
                        $handler = new Files($this->app['config']['session.files'], $this->app['config']['session.lifetime']);
                    }
                    else {
                        throw new SessionException('Session Exception: ' . $ex->getMessage());
                    }
                }
                break;
            default:
                throw new \InvalidArgumentException(sprintf('Invalid session driver %s', $driver));
        }

        if (!headers_sent()) {
            session_set_save_handler($handler, true);
        }
    }

    /**
     * Start a session.
     *
     * @param bool|true $autoCommit
     * @throws SessionException
     */
    public function start($autoCommit = true, $config = [])
    {
        /** @var \Key\Http\Request $request */
        $request = $this->app['request'];
        if ($request->getMethod() == 'OPTIONS') {
            //error_log('Skip for OPTIONS request');
            return $this;
        }
        if ($this->is_started()) {
            return $this;
        }
        // if ($config) {
        //     foreach($config as $key => $value) {
        //         ini_set($key, $value);
        //     }
        // }

        
        $defaultConfig = $this->getSessionConfig();
        $config = array_merge($defaultConfig, $config ?: []);

        if (!$this->is_started()) {

            if (isset($this->app['config']['hook.beforeSessionStarting'])) {
                $result = call_user_func($this->app['config']['hook.beforeSessionStarting'], $this->app);
                if ($result === false) {
                    return;
                }
            }
            // error_log('[Session] session config: ' . json_encode($config));
            $sessOpts = [];
            if ($config) {
                foreach ($config as $key => $val) {
                    if (startsWith($key, 'session.')) {
                        $sessOpts[substr($key, strlen('session.'))] = $val;
                    }
                }

                $aid = $this->app->offsetExists('__CURRENT_ACCOUNT_ID__') ? $this->app['__CURRENT_ACCOUNT_ID__'] : 0;
                $sessOpts['save_path'] = '/tmp' . ($aid ? '/' . $aid : '');
            }
            
            // error_log('[Session] session opts: ' . json_encode($sessOpts));
            
            if (!session_start($sessOpts)) {
                throw new SessionException('The session can not start.');
            }
            else {
                if (env('APP_ENV') == 'development') {
                    error_log('[Session] started: ' . json_encode(array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 0, 4)));
                }
            }
        }

        if ($autoCommit) $this->commit();
        return $this;
    }

    /**
     * Reset the session id.
     * 
     * @return $this
     */
    public function resetId()
    {
        $this->prepare(false);

        session_write_close();

        // Create new session id
        $newSid = sha1(mt_rand()); 
        session_id($newSid);
        ini_set('session.use_strict_mode', 0);
        session_start();
        $this->set('__created__', time());
        ini_set('session.use_strict_mode', 1);
        return $this;
    }

    protected function is_started()
    {
        return function_exists('session_status') ? (PHP_SESSION_ACTIVE == session_status()) : (!empty(session_id()));
    }

    /**
     * Close the session.
     */
    protected function commit()
    {
        //session_write_close();
    }

    /**
     * Destroy the session.
     */
    public function destroy()
    {
        $this->start(false);
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']);
        session_destroy();
        $this->commit();
    }

    /**
     * Get session item for key.
     *
     * @param string $key The data key
     * @param mixed $default The default value to return when key does not exists in the collection, default is null
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $default;
    }

    /**
     * Get all items in collection
     *
     * @return array The collection's source data
     */
    public function all()
    {
        return $_SESSION ? (array) $_SESSION : array();
    }

    /**
     * Set index's value.
     *
     * @param string $key
     * @param mixed $value
     */
    public function set($key, $value)
    {
        if ($key && isset($value)) {
            $this->start(false);
            $_SESSION[$key] = $value;
            $this->commit();
        }
    }

    /**
     * @param array $items
     */
    public function replace(array $items)
    {
        foreach($items as $key => $item) {
            $this->set($key, $item);
        }
    }

    /**
     * Remove a value from the collection
     * @param string $key The data key
     */
    public function remove($key)
    {
        if ($key) {
            if (isset($_SESSION[$key])) {
                $this->start(false);
                unset($_SESSION[$key]);
                $this->commit();
            }
        }
    }

    /**
     * Clear the collection
     */
    public function clear()
    {
        $this->start(false);
        $_SESSION = array();
        $this->commit();
    }

    /**
     * Determine key exists in the collection.
     *
     * @param string $key the data key
     * @return bool
     */
    public function has($key)
    {
        return array_key_exists($key, $_SESSION);
    }

}