<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */
namespace Key\Abstracts;


use Key\Collection;
use Key\Constants;
use Pimple\Container;

/**
 * Class Controller
 *
 * This class provides the useful methods for the controller extension.
 *
 * @package Key\Abstracts
 * @author Guanghui Li <liguanghui2006@163.com>
 */
abstract class Controller
{

    protected $statusMessageMap = [];

    /**
     * Status message map.
     *
     * @var array
     */
    protected static $defaultStatusMessageMap = [
        Constants::SYS_SUCCESS => 'OK',
        Constants::SYS_ERROR_DEFAULT => 'Undefined error',
        Constants::SYS_INTERNAL => 'Internal server error',
        Constants::SYS_ACL_ERROR => 'ACL error',
        Constants::SYS_DATABASE_ERROR => 'Database error',
        Constants::SYS_REQ_INVALID => 'Invalid Request',
        Constants::SYS_INPUT_INVALID => 'Invalid input',
        Constants::SYS_INPUT_MISSING => 'Missing input',
        Constants::SYS_NO_USER => 'No user found',
        Constants::SYS_REQ_INSECURE => 'Insecure request',
        Constants::SYS_REQ_AUTH => 'Unauthorized request',
        Constants::SYS_PERMISSION_FAULT => 'No permission',
        Constants::SYS_ROUTER_CONFIG_ERROR => 'Router configure error',
        Constants::SYS_ROUTE_NOT_FOUND => 'Route not found',
        Constants::SYS_ROUTE_PARAM_ERROR => 'Bad route parameters',
        Constants::USER_DEFAULT_ERROR => 'Unknown user error',
        Constants::USER_OBJECT_NOT_FOUND => 'Object not found',
        Constants::USER_OBJECT_ALREADY_EXISTS => 'Object already exists',
        Constants::USER_OBJECT_OCCUPIED => 'Object occupied'
    ];

    /**
     * @var \Key\Session
     */
    protected $session;

    /** @var \Pimple\Container */
    protected $app;

    /** @var \Pimple\Container */
    protected $modelsContainer;

    /**
     * @var \Psr\Http\Message\ServerRequestInterface;
     */
    protected $request;

    /**
     * @var \Key\Http\Response
     */
    protected $response;

    /**
     * Output collection.
     *
     * @var Collection
     */
    protected $outputs;

    /**
     * Return status code.
     *
     * @var int
     */
    protected $statusCode;

    /**
     * Return status message.
     *
     * @var string
     */
    protected $statusMessage;

    /**
     * Controller construct.
     */
    public function __construct()
    {
        $this->outputs = new Collection([]);
        $this->statusCode = Constants::SYS_SUCCESS;
        $this->statusMessage = null;

        $this->statusMessageMap = $this->getStatusMessageMap();
    }

    protected function getStatusMessageMap()
    {
        return $this->statusMessageMap + static::$defaultStatusMessageMap;
    }

    /**
     * Controller invoke
     *
     * @param \Pimple\Container $container
     * @return $this
     */
    public function __invoke(Container $container, $isAuth = true)
    {
        if ($isAuth) {
            $this->session = $container['session'];
        }
        $this->app = $container;
        $this->request = $container['request'];
        $this->response = $container['response'];

        $this->modelsContainer = new Container([]);

        return $this;
    }

    protected function initSession()
    {
        if (!$this->session) {
            $this->session = $this->app['session'];
        }
    }

    /**
     * Get session item for key.
     *
     * @param string $key The data key
     * @param mixed $default The default value to return when key does not exists in the collection, default is null
     *
     * @return mixed
     */
    public function getSession($key, $default = null)
    {
        $this->initSession();
        return $this->session ? $this->session->get($key, $default) : null;
    }

    /**
     * Set session item.
     *
     * @param string $key
     * @param mixed $value
     */
    public function setSession($key, $value)
    {
        $this->initSession();
        if ($this->session) {
            $this->session->set($key, $value);
        }
    }

    /**
     * Remove a key from session.
     *
     * @param string $key Session key
     */
    public function removeSession($key)
    {
        $this->initSession();
        if ($this->session) {
            $this->session->remove($key);
        }
    }

    /**
     * Set cookie.
     *
     * @param string $name
     * @param string $value
     * @param string $path
     * @param null $domain
     * @param bool $secure
     * @param bool $httpOnly
     */
    public function setCookie($name, $value, $lifetime = null, $path = '/', $domain = null, $secure = false, $httpOnly = true)
    {
        $lifetime = $lifetime ?: env('SESSION_LIFETIME', 0);
        if ($lifetime) {
            $lifetime = time() + $lifetime;
        } else {
            $lifetime = strtotime( '+30 days' );
        }
        setcookie($name, $value, $lifetime, $path, $domain, $secure, $httpOnly);
    }

    /**
     * Delete the cookie.
     *
     * @param $name
     * @param string $path
     * @param null $domain
     * @param bool $secure
     * @param bool $httpOnly
     */
    public function deleteCookie($name, $path = '/', $domain = null, $secure = false, $httpOnly = true)
    {
        setcookie($name, '', 1, $path, $domain, $secure, $httpOnly);
    }

    /**
     * Set the output item.
     *
     * @param string $name The output item name
     * @param mixed $value The output item value
     */
    public function setOutput($name, $value)
    {
        $this->outputs->set($name, $value);
    }

    /**
     * Set the output items.
     *
     * @param array $items The output items
     * @param bool|false $reset if true, the original outputs will clear;
     * else the original will be replaced
     */
    public function setOutputs($items, $reset = false)
    {
        if (!is_array($items)) {
            $items = array($items);
        }

        if ($reset) {
            $this->outputs->clear();
        }

        $this->outputs->replace($items);
    }

    /**
     * Get the output item by name.
     *
     * @param string $name The output item name
     *
     * @return mixed
     */
    public function getOutput($name)
    {
        return $this->outputs->get($name);
    }

    /**
     * Get all output items.
     *
     * @return array
     */
    public function getOutputs()
    {
        return $this->outputs->all();
    }

    /**
     * Clear the output.
     */
    public function clearOutputs()
    {
        $this->outputs->clear();
    }

    /**
     * Set return status code and message.
     *
     * @param int $status The return status code
     * @param null|string $msg The return status message
     */
    public function setStatus($status, $msg = null)
    {
        $this->statusCode = $status;
        if (isset($msg) && !$this->statusMessage) {
            $this->statusMessage = $msg;
        }
    }

    /**
     * Set the returned message.
     *
     * @param string $msg
     */
    public function setStatusMessage($msg)
    {
        $this->statusMessage = $msg;
    }

    /**
     * Get the return status code.
     *
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * Get the return status message.
     *
     * @return string
     */
    public function getStatusMessage()
    {
        return $this->statusMessage ? $this->statusMessage : (isset($this->statusMessageMap[$this->getStatusCode()]) ? $this->statusMessageMap[$this->getStatusCode()] : '');
    }

    /**
     * Log the message (INFO).
     *
     * @param string $message
     * @deprecated
     */
    public function info($message)
    {
//        global $CONFIG;
//
//        if (isset($CONFIG->appLogger) && $CONFIG->appLogger)  {
//            $CONFIG->appLogger->info($message);
//        }
    }

    /**
     * Log the message (DEBUG).
     *
     * @param string $message
     * @deprecated
     */
    public function debug($message)
    {
//        global $CONFIG;
//
//        if (isset($CONFIG->appLogger) && $CONFIG->appLogger)  {
//            $CONFIG->appLogger->debug($message);
//        }
    }

    /**
     * Log the message (ERROR).
     *
     * @param string $message
     * @deprecated
     */
    public function error($message)
    {
//        global $CONFIG;
//
//        if (isset($CONFIG->appLogger) && $CONFIG->appLogger)  {
//            $CONFIG->appLogger->error($message);
//        }
    }


    /**
     *
     * @param string $name Model name
     * @param bool $reset
     * @param string $prefix
     * @return \App\Common\BaseModel Extended BaseModel
     */
    public function getModel($name, $reset = false, $prefix = '\\App\\Models\\', $loadAppModule = null)
    {
        // $className = $prefix . ucfirst($name);
        // if (!class_exists($className)) {
        //     $currModelName = $this->app->offsetExists('current_module') ? $this->app['current_module']->getName() : null;
        //     $found = false;
        //     if ($this->app->offsetExists('modules')) {
        //         $modules = $this->app['modules'];
        //         if ($loadAppModule && $loadAppModule != $currModelName && isset($modules[$loadAppModule])) {
        //             $modules[$loadAppModule]->registerClasses($this->app);
        //             if (class_exists($className)) {
        //                 error_log('load module from app module: ' . $modules[$loadAppModule]->getName());
        //                 $found = true;
        //             }
        //         }
        //         else {
        //             // try to load all modules classes to find special model
        //             foreach ($modules as $module) {
        //                 if ($module->getName() != $currModelName) {
        //                     $module->registerClasses($this->app);
        //                     if (class_exists($className)) {
        //                         error_log('load module from app module: ' . $module->getName());
        //                         $found = true;
        //                         break;
        //                     }
        //                 }
        //             }
        //         }
        //     }
        //     if (!$found) {
        //         throw new \InvalidArgumentException(sprintf('Model not found %s', $name));
        //     }
        // }
        $closure = get_module_class_with_loader($name, $this->app, $prefix, $loadAppModule);
        if (!$closure) {
            throw new \InvalidArgumentException(sprintf('Model not found %s', $name));
        }
        if (!isset($this->modelsContainer[$name]) || $reset) {
            $app = $this->app;
            $this->modelsContainer[$name] =  function () use($name, $app) {
                return new $name($app);
            };
        }

        return $this->modelsContainer[$name];
    }
}
