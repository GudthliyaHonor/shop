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

use Key\Exception\AppException;
use Key\Routing\Routes;
use Psr\Http\Message\UriInterface;
use Key\Http\Uri;
use Key\Inputs\Input;

/**
 * Route.
 *
 * @package Key
 * @author Guanghui Li <liguanghui2006@163.com>
 */
class Route
{

    /**
     * @var string
     */
    protected $routeRule;

    /**
     * @var string
     */
    protected $routeKey;

    /**
     * Uri object.
     *
     * @var Uri
     */
    protected $uri;

    /**
     * Route settings.
     *
     * @var Collection
     */
    protected $settings;

    protected $moduleName;

    /**
     * Uri params.
     *
     * @var array
     */
    protected $uriParams = array();

    /**
     * Route construct.
     *
     * @param UriInterface $uri
     * @param string|array $settings
     * @param array $uriParams params in Uri
     * @param string $route_rule Matched route rule
     * @param string Route key, such as 'POST /user/logout'
     */
    public function __construct(UriInterface $uri, $settings, $uriParams = null, $routeRule = null, $key = null)
    {
        $this->uri = $uri;
        if (is_string($settings)) {
            $settings = array(
                'controller' => $settings
            );
        }
        $this->settings = new Collection($settings);

        if ($uriParams && is_array($uriParams)) {
            $this->uriParams = $uriParams;
        }

        $this->routeRule = $routeRule;
        $this->routeKey = $key;
    }

    /**
     * @return null|string
     */
    public function getRouteRule()
    {
        return $this->routeRule;
    }

    /**
     * Get the route key.
     * @return string
     */
    public function getRouteKey()
    {
        return $this->routeKey;
    }

    /**
     * The Uri.
     *
     * @return Uri
     */
    public function getUri()
    {
        return $this->uri;
    }

    /**
     * Get route settings.
     *
     * @return Collection
     */
    public function getSetting()
    {
        return $this->settings;
    }

    /**
     * Get the description of the route.
     *
     * @return mixed
     */
    public function getDescription()
    {
        return $this->settings->get('description');
    }

    /**
     * Get contributors of the route.
     *
     * @return string
     */
    public function getContributors()
    {
        return $this->settings->get('contributors');
    }

    /*******************************************************************************
     * Redirect
     ******************************************************************************/
    /**
     * Check if it is the redirect route.
     *
     * @return bool
     */
    public function isRedirect()
    {
        return $this->settings->has('redirect') || $this->settings->has('direct');
    }

    protected function getRedirect()
    {
        return $this->settings->get('redirect') ? $this->settings->get('redirect') : $this->settings->get('direct');
    }

    /**
     * Get the default redirect uri if it is a redirect route.
     *
     * @return string|null
     */
    public function getDefaultRedirectUri()
    {
        if ($this->isRedirect()) {
            $redirect = $this->getRedirect();
            if ($redirect) {
                if (is_string($redirect)) {
                    return $redirect;
                } else if (is_array($redirect)) {
                    if (isset($redirect['default'])) {
                        return $redirect['default'];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Get the un-auth redirect uri.
     *
     * @return string|null
     */
    public function getUnauthRedirectUri()
    {
        if ($this->isRedirect()) {
            $redirect = $this->settings->get('redirect');
            if ($redirect && is_array($redirect)) {
                if (isset($redirect['unauth'])) {
                    return $redirect['unauth'];
                }
            }
        }

        return null;
    }

    /**
     * Get Route method, such as 'post', 'get'.
     * @return string
     */
    public function getMethod()
    {
        return $this->settings->get('method') ? strtolower($this->settings->get('method')) : null;
    }

    /**
     * Get the controller path.
     *
     * For example:
     * <code>/a/b/c.do</code>
     * <code>/xyz.json</code>
     * or
     * <code>
     * array(
     *   'type' => 'param',
     *   'paths' => array(
     *    'type:barcode' => '/product/product/listByBarcode',
     *    'type:category' => '/product/product/listByCategory'
     *   )
     * )
     * </code>
     *
     * @return mixed
     */
    public function getControllerPath()
    {
        // TODO: should check array type
        return $this->settings->get('controller') ? $this->settings->get('controller') : $this->settings->get('realPath');
    }

    // protected function generateClassName($className)
    // {
    //     $controllerClassName = null;
    //     if (is_string($className)) {
    //         $controllerClassName = $className;
    //     } elseif (is_array($className)) {
    //         $controllerClassName = implode('\\', array_map(function($val) {
    //             return ucfirst($val);
    //         }, $className));
    //     }
    //     return $controllerClassName;
    // }

    // public function getControllerClosure()
    // {

    //     $ctrlPath = $this->getControllerPath();
    //     if (is_callable($ctrlPath)) {
    //         return $ctrlPath;
    //     }
    //     elseif ($ctrlPath) {
    //         $paths = explode('/', rtrim($ctrlPath, '/'));

    //         $actionName = array_pop($paths);

    //         if ($paths) {
    //             $className = $this->generateClassName($paths);
    //         }

    //         if ($className && class_exists($className)) {
    //             return function($method, $params, $container, $isAuth) use($className) {
    //                 //static::log('[Router] Load Controller class: '.get_class($controller));

    //                 $result = call_user_func_array(array($className($container, $isAuth), $method), $params);
    //                 $controller->setStatus($result);

    //                 return $result;
    //             };
    //         }
    //     }
    //     return false;
    // }

    /**
     * Check Auth setting for the route.
     *
     * @return bool
     */
    public function isAuth()
    {
        return !($this->settings->get('unauthorized')) && !$this->settings->get('open'); // open API Not using user auth
    }

    /**
     * Get acl setting.
     *
     * @return mixed
     */
    public function getAcl()
    {
        return $this->settings->get('acl');
    }

    /**
     * Get data acl setting.
     * @return mixed|null
     */
    public function getDataAcl()
    {
        return $this->settings->get('data_acl');
    }

    /**
     * Get inputs of the route.
     *
     * @return Collection
     */
    public function getInputs()
    {
        $inputs = $this->settings->get('inputs');
        if (is_array($inputs) && count($inputs)) {
            return new Collection($inputs);
        } else {
            return new Collection(array());
        }
    }

    /**
     * Get input keys in the settings.
     *
     * @return array
     */
    public function getInputKeys()
    {
        $inputs = $this->getInputs();
        return $inputs->all();
    }

    /**
     *  Get input by given key in the settings.
     *
     * @param $key
     * @return null|Input
     */
    public function getInput($key)
    {
        $inputs = $this->getInputs();
        $item = $inputs->get($key);
        if (is_array($item)) {
            return new Input($key, null, $item);
        } else {
            error_log('[getInput] Invalid param setting: ' . var_export($item, true));
            return null;
        }
    }

    /**
     * Get outputs configure of the route.
     *
     * @return Collection
     */
    public function getOutputs()
    {
        $outputs = $this->settings->get('outputs');
        if (is_array($outputs) && count($outputs)) {
            return new Collection($outputs);
        } else {
            return new Collection(array());
        }
    }

    /**
     * Get the suffix of the route.
     *
     * For example:
     * '/abc/xyz.json' -> 'json'
     * '/xyz.xml' -> 'xml'
     *
     * @return string
     */
    public function getSuffix()
    {
        $suffix = '';
        $path = $this->uri->getPath();
        $chips = explode('/', $path);
        // last chip
        $chip = $chips[count($chips) - 1];

        if (($pos = strrpos($chip, '.')) !== false) {
            $suffix = substr($chip, $pos + 1);
        }

        return $suffix;
    }

    /**
     * @return array
     */
    public function getUriParams()
    {
        return $this->uriParams ? $this->uriParams : array();
    }

    /**
     * Get uri value for parameter
     *
     * @param string $key
     *
     * @return mixed|null
     */
    public function getUriParam($key)
    {
        return isset($this->uriParams[$key]) ? $this->uriParams[$key] : null;
    }

    /**
     * Get support versions for the route.
     *
     * @return array|mixed
     * @throws AppException
     */
    public function getSupportVersions()
    {
        $version = $this->settings->get('version', 'v1');
        if (is_string($version)) {
            return explode(',', $version);
        }
        if (!is_array($version)) {
            throw new AppException('Invalid version setting for the route');
        }

        return $version;
    }

    /**
     * @return string
     */
    public function getView()
    {
        return $this->settings->get('view');
    }

    /**
     * Get Route open, such as 1 0.
     * @return string
     */
    public function isOpen()
    {
        return $this->settings->get('open') ? $this->settings->get('open') : 0;
    }

    public function getCacheRule()
    {
        if ($cacheRule = $this->settings->get('cache')) {
            return is_array($cacheRule) ? $cacheRule : [];
        }
        return false;
    }

    /**
     * If set frequent, then will limit this route request at each time in FREQUENT second(s).
     * @return int
     */
    public function getFrequent()
    {
        $frequent = (int) $this->settings->get('frequent');
        return $frequent >= 0 ? $frequent : 0;
    }


    public function executeDataAclCheck($app, $params = [])
    {
        $dataAcl = $this->getDataAcl();
        if ($dataAcl) {
            $pieces = explode('::', $dataAcl);
            if (count($pieces) == 1) {
                // function
            } elseif (count($pieces) == 2) {
                $className = $pieces[0];
                $method = $pieces[1];
                if (class_exists($className)) {
                    /** @var \Key\Abstracts\BaseDataAclChecker $class */
                    $class = new $className($app);
                    return call_user_func_array([$class, $method], ['params' => $params]);
                }
            }
        }
        return true;
    }


    public function executeAclCheck($app, $params = [])
    {
        $dataAcl = $this->getAcl();
        if ($dataAcl) {
            $pieces = explode('::', $dataAcl);
            if (count($pieces) == 1) {
                // function
            } elseif (count($pieces) == 2) {
                $className = $pieces[0];
                $method = $pieces[1];
                if (class_exists($className)) {
                    /** @var \Key\Abstracts\BaseDataAclChecker $class */
                    $class = new $className($app);
                    return call_user_func_array([$class, $method], ['params' => $params]);
                }
            }
        }
        return true;
    }

    /**
     * Determines if the Route is deprecated.
     * @return bool
     */
    public function isDeprecated(&$recommend = null)
    {
        $isDeprecated = $this->settings->get('deprecated') ? $this->settings->get('deprecated') == 1 : false;
        if ($isDeprecated) {
            $recommend = $this->settings->get('recommend');
        }
        return $isDeprecated;
    }

    /**
     * Determine if env setting is matching APP_ENV.
     *
     * @return boolean
     */
    public function isEnvMatch()
    {
        $envStr = $this->settings->get('env');
        // error_log('isEnvMatch: ' . $envStr);
        if ($envStr) {
            $envs = explode(',', $envStr);
            return in_array(env('APP_ENV'), $envs);
        }
        return true;
    }

    /**
     * Set disabled state.
     * @param boolean $disabled
     * @return $this
     */
    public function setDisabled($disabled) {
        $this->settings->set('disabled', !!$disabled);
        return $this;
    }

    /**
     * Get disabled state.
     * @return boolean
     */
    public function isDisabled()
    {
        return !!$this->settings->get('disabled');
    }

    public function getModuleName()
    {
        return $this->settings->get(Routes::MODULE_ROUTE_KEY);
    }
}
