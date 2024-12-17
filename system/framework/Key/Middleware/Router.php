<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace Key\Middleware;


use Closure;
use Composer\Autoload\ClassLoader;
use Key\Abstracts\BaseRecord;
use Key\Http\Request;
use Key\Inputs\InputFactory;
use Pimple\Container;
use ReflectionClass;
use RuntimeException;
use UnexpectedValueException;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\ResponseInterface;
use Key\Abstracts\Middleware;
use Key\Constants;
use Key\Route;
use Key\Inputs\Input;
use Key\Exception\NotFoundException;
use Key\Exception\AppException;
use Key\Routing\Routes;

/**
 * Middleware Router
 *
 * @package Key\Middleware
 * @author Guanghui Li <liguanghui2006@163.com>
 */
class Router extends Middleware
{
    const DEFAULT_ROUTE_METHOD = 'get';

    const HTTP_METHOD_GET = 'get';
    const HTTP_METHOD_POST = 'post';
    const HTTP_METHOD_PUT = 'put';

    const INPUT_MATCH_MODES = 'order,name';
    const DEFAULT_INPUT_MATCH_MODE = 'order';
    const INPUT_MATCH_MODE_NAME = 'name';


    protected $version = 'v1';

    const MODULE_ROUTE_KEY = '_m_';

    /**
     * base path.
     *
     * if prefix is defined, the API matcher should match `prefix` + path.
     * For example:
     * <code>
     *   prefix = /api
     *   Request URI <prefix>/<version>/abc.json => API /abc.json
     * </code>
     *
     * @var string
     */
    protected $prefix = '';

    /** @var \Pimple\Container */
    protected $container;

    /**
     * @var \Key\Http\Request
     */
    protected $request;

    /**
     * @var \Psr\Http\Message\ResponseInterface
     */
    protected $response;

    /**
     * The configure of routes.
     *
     * @var array
     */
    protected $routes = array();

    /**
     * The route for the request.
     *
     * @var Route
     */
    protected $route;

    /**
     * Request path.
     *
     * @var string
     */
    protected $originalPath;

    /**
     * @var string
     */
    protected $folder;

    /**
     * @var string
     */
    protected $controllerClassName;

    /**
     * @var \Key\Abstracts\Controller
     */
    protected $controller;

    /**
     * @var Closure
     */
    protected $controllerClosure;

    /**
     * @var string
     */
    protected $action;

    /**
     * @var \Key\Collection
     */
    protected $inputsConfig;

    /**
     * Outputs configure.
     *
     * @var \Key\Collection
     */
    protected $outputsConfig;

    /**
     * @var array
     */
    protected $filteredParams;


    /**
     * Invalid inputs(contain the values).
     *
     * @var array
     */
    protected $invalid = [];

    /**
     * Current output content type.
     *
     * @var string
     */
    protected $outputContentType;

    /**
     * Input match mode, default is 'order'.
     *
     * @var string
     */
    protected $inputMatchMode = self::DEFAULT_INPUT_MATCH_MODE;

    /**
     * Uri suffix and output content type matcher.
     *
     * @var array
     */
    protected $validOutputContentTypes = array(
        'do' => 'application/json',
        'json' => 'application/json',
        'xml' => 'text/xml',
        'html' => 'text/html'
    );

    /**
     * Internal input types.
     *
     * @var array
     */
    protected $internalInputTypes = array('array', 'datetime', 'file', 'float', 'int', 'string', 'mixed');

    /**
     * Router invoke.
     *
     * @param Container $container
     * @param Closure $next
     * @return ResponseInterface
     * @throws AppException
     */
    public function __invoke(Container $container, $next)
    {
        $this->container = $container;

        $request = $container['request'];
        $response = $container['response'];

        $this->request = $request;
        $this->response = $response;

        // $container['session']->start();

        //$matchModes = explode(',', self::INPUT_MATCH_MODES);

        //$routesConfig = $this->loadRouteConfig($request);
        //$routesConfig = $this->container['routes'];
        $this->prefix = $this->container['config']['global.apiPrefix'];
        //$this->inputMatchMode = isset($routesConfig['inputMatchMode']) && in_array($routesConfig['inputMatchMode'], $matchModes) ? strtolower($routesConfig['inputMatchMode']) : self::DEFAULT_INPUT_MATCH_MODE;
        //$this->routes = $this->loadRoutes(); // $this->container['routes'];
        //$regExps = isset($routesConfig['regExps']) && is_array($routesConfig['regExps']) ? $routesConfig['regExps'] : array();
        //$this->routes = array_merge($this->routes, $regExps);
        $routesObj = new Routes($this->container, $request, $this->prefix);
        // $routesObj->setPrefix($this->prefix);
        $route = $routesObj->lookup();
        // $route = $this->lookupRoute($this->request->getUri());

        if ($route && $route->isDisabled()) {
            throw new AppException($this->container['translator']->get('system:route:disabled'));
        }

        if ($route && $this->validateVersion($route)) {
            $this->route = $route;

            $this->container[\App\Common\Constants::CURRENT_ROUTE] = $route;

            // unset the routes in the container
            $this->container->offsetUnset('routes');
            $this->container->offsetUnset('pages');
            unset($this->container['routes']);
            unset($this->container['pages']);

            // if ($frequent = $route->getFrequent()) {
            //     $uri = $route->getUri();
            //     /** @var \Key\Cache\Redis $cache */
            //     $cache = $this->container['cache'];
            //     /** @var \Key\Session $session */
            //     $session = $this->container['session'];
            //     if ($user = $session->get(Constants::SESSION_USR_KEY)) {
            //         $uid = $user['id'] ?? 0;
            //         if ($uid) {
            //             $key = 'REQ:FRQ:' . md5($uri . $uid);
            //             if ($cache->get($key)) {
            //                 $this->handleException('Operation too frequent', Constants::OPERATION_TOO_FREQUENT);
            //             } else {
            //                 $cache->set($key, 1, $frequent);
            //             }
            //         }
            //     }
            // }

            if ($route->isRedirect()) {
                $redirectUri = $route->getDefaultRedirectUri();
                if (!$redirectUri) {
                    $redirectUri = '/';
                }

                $response = $response->withHeader('Location', $redirectUri);
            } else {
                $ctrlPath = $route->getControllerPath();
                if ($ctrlPath) {
                    $paths = explode('/', rtrim($ctrlPath, '/'));

                    $this->setFolder('');
                    $actionName = array_pop($paths);
                    if ($paths) $this->setControllerClassName($paths);
                    if (isset($actionName)) $this->setAction($actionName);

                    $className = $this->getControllerClassName();
                    if ($className && class_exists($className)) {
                        $controller = new $className;
                        $this->setController($controller);
                    } else {
                        $this->handleException(sprintf('Controller class not found: %s', str_replace('\\\\', '\\', $className)));
                    }

                    $this->inputsConfig = $route->getInputs();
                    $this->outputsConfig = $route->getOutputs();

                    $response = $this->exec();
                } else {
                    // Controller not found
                    $this->handleException(sprintf('Controller not found for the route %s', $route->getUri()));
                }
            }
        }
        else {
            throw new \Key\Exception\RouterInvalidException($this->container);
        }

        return $next($container);
    }

    // Avoid content override
    protected function includeRouteFile($file)
    {
        return require($file);
    }

    protected function cacheRoutes($cacheFile, $pid, $render = true)
    {
        if ($this->container->offsetExists('modules')) {
            if ($render) file_put_contents($pid, date('Y-m-d H:i:s'));
            $routes = [];
            /** @var \Key\Foundation\Module $module */
            foreach ($this->container['modules'] as $module) {
                $routePath = $module->getRoutePath();
                if (file_exists($routePath)) {
                    $handle = opendir($routePath);
                    if ($handle) {
                        while (($fl = readdir($handle)) !== false) {
                            // Skip some files
                            if (!endsWith($fl, '.php') || $fl == '.' || $fl == '..' || startsWith($fl, '__') ) {
                                continue;
                            }

                            $routeFile = $routePath . DIRECTORY_SEPARATOR . $fl;
                            if (is_file($routeFile)) {
                                $tmp = $this->includeRouteFile($routeFile);
                                if ($tmp) {
                                    foreach ($tmp as $key => $item) {
                                        $tmp[$key][self::MODULE_ROUTE_KEY] = $module->getName();
                                        unset($item['summary'], $item['description'], $item['contributors'], $item['created']);
                                        if (isset($item['inputs'])) {
                                            foreach ($item['inputs'] as $key1 => $input) {
                                                unset($item['inputs'][$key1]['description']);
                                                if (isset($input['detail'])) {
                                                    foreach ($input['detail'] as $key2 => $val) {
                                                        unset($item['inputs'][$key1]['detail'][$key2]['description']);
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                if (is_array($tmp)) {
                                    $routes = array_merge($routes, $tmp);
                                }
                            }
                        }
                    }
                }
                else {
                    error_log('route file not found: ' . $routePath);
                }
            }

            if (!$render) {
                return $routes;
            }

            $content = 'return [';
            foreach ($routes as $key => $item) {
                $content .= '\'' . $key . '\' => ' . var_export($item, true) . ',' . PHP_EOL;
            }

            $content .= '];';
            $dir = pathinfo($cacheFile, PATHINFO_DIRNAME);
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
            // 去掉空行
            $content = preg_replace("/(\r\n|\n|\r|\t)/i", '', $content);
            $content = preg_replace('/\s{2,}/', '', $content);

            $content = '<?php ' . $content;
            file_put_contents($cacheFile, $content);
            chmod($cacheFile, 0777);

            unlink($pid);
        }
        else {
            error_log('[WARN] modules not in container!');
        }
        return [];
    }

    protected function loadRoutes()
    {
        $routes = [];
        // check route cache
        $cacheFile = rtrim(env('APP_ROUTE_CACHE_PATH', '/tmp/'), '/') . DS . 'cache_routes.' . env('APP_BUILD_VERSION', date('y-m-d')) . '.php';
        $usingRouteCache = env('APP_ROUTE_CACHE_ENABLED', 0);
        $pid = $cacheFile . '.pid';
        if ($usingRouteCache 
            && !file_exists($pid)) { // Skip when cache file is rendering
            if (!file_exists($cacheFile)) {
                $this->cacheRoutes($cacheFile, $pid);
            }

            $tmp1 = include_once $cacheFile;
            if (is_array($tmp1)) {
                $routes = array_merge($routes, $tmp1);
            }

            $this->container['routes'] = $routes;
        }
        else {
            error_log('[WARN] no route cache!');
            $routes = $this->cacheRoutes($cacheFile, $pid, false);
            $this->container['routes'] = $routes;
        }

        if ($this->container->offsetExists('routes')) {
            return $this->container['routes'];
        }
        return [];
    }

    /**
     * Check if the version is valid.
     *
     * @param \Key\Route $route
     * @return bool
     * @throws AppException
     */
    protected function validateVersion($route)
    {
        if (in_array($this->version, $route->getSupportVersions())) {
            return true;
        }

        $this->handleException('Unsupported API version: ' . $this->version);
        return false;
    }

    /**
     * Check controller mode.
     *
     * @return bool
     * @deprecated, only PSR controller mode is supported
     */
    protected function usePsrControllerMode()
    {
        return true;
    }

    protected function loadClasses($routeSetting)
    {
        if (isset($routeSetting[self::MODULE_ROUTE_KEY]) && isset($this->container['modules'][$routeSetting[self::MODULE_ROUTE_KEY]])) {
            if (!$this->container->offsetExists('appName')) {
                $this->container['appName'] = $routeSetting[self::MODULE_ROUTE_KEY];
            }

            /** @var \Key\Foundation\Module $module */
            $module = $this->container['modules'][$routeSetting[self::MODULE_ROUTE_KEY]];
            $loader = new ClassLoader();
            $loader->addPsr4($module->getNs(), $module->getClassPath());

            if ($dependencies = $module->getDependencies()) {
                foreach ($dependencies as $dependency) {
                    if (isset($this->container['modules'][$dependency])) {
                        error_log('load dependency module: ' . $dependency);
                        $dependencyModel = $this->container['modules'][$dependency];
                        $loader->addPsr4($dependencyModel->getNs(), $dependencyModel->getClassPath());
                    }
                    else {
                        error_log('Module ' . $routeSetting[self::MODULE_ROUTE_KEY] . ' dependency ' . $dependency . ' not found');
                    }
                }
            }
            if ($third = $module->getThirdClasses()) {
                foreach ($third as $item) {
                    $loader->addPsr4($item['ns'], $item['classPath']);
                }
            }
            $loader->register();
        }
    }

    /**
     * Lookup the route.
     *
     * @param \Key\Http\Uri $uri
     * @return Route|null
     * @throws NotFoundException
     */
    protected function lookupRoute(UriInterface $uri)
    {
        if (method_exists($uri, 'getBasePath')) {
            $bathPath = $uri->getBasePath();
        } else {
            $bathPath = $uri->getPath();
        }

        $originalPath = '/' . trim($bathPath.'/'.$uri->getPath(), '/');
        $this->originalPath = $originalPath;

        $outputContentType = $this->determineContentType($originalPath);
        $this->outputContentType = $outputContentType;

        $path = $this->normalizePath($originalPath);
        //static::log(sprintf('[Router] Lookup the route: original path: %s, normalized path: %s', $originalPath, $path));
        $route = null;
        if (isset($this->routes[$path]) && $setting = $this->routes[$path]) {
            $this->routes = [];
            $this->loadClasses($setting);
            $route = new Route($uri, $setting, [], $path);
        } else {
            foreach ($this->routes as $key => $setting) {
                if ($key) {
                    $key = str_replace('/', '\/', $key);
                    if (preg_match('/^' . $key . '$/i', $path, $matches)) {
                        $uriParams = array();
                        if ($matches && count($matches) > 1) {
                            $uriParams = array_slice($matches, 1);
                        }
                        $this->routes = [];
                        $this->loadClasses($setting);
                        $route = new Route($uri, $setting, $uriParams);
                        break;
                    }
                }
            }
        }
        if ($route && $route->isEnvMatch()) {
            return $route;
        }
        else {
            error_log('Route not found or env not matched');
        }
        $this->routes = [];
        $this->response = $this->response->withHeader('Content-Type', $outputContentType);

        throw new NotFoundException($this->request, $this->response);
    }

    /**
     * Retrieves the output content type.
     *
     * @param string $path
     * @return string
     * @throws AppException
     * @deprecated
     */
    protected function determineContentType($path)
    {
        $pos = strrpos($path, '.', strrpos($path, '/') !== false ? strrpos($path, '/') : 0);
        $contentType = ($pos === false ? 'json' : substr($path, $pos + 1));

        $contentType = strtolower($contentType);
        if (isset($this->validOutputContentTypes[$contentType])) {
            return $this->validOutputContentTypes[$contentType];
        }

        return 'application/json';
    }

    /**
     * Retrieves the normalized path.
     *
     * @param string $path
     * @return string
     */
    protected function normalizePath($path)
    {
//        $pos = strrpos($path, '.', strrpos($path, '/') !== false ? strrpos($path, '/') : 0);
//        $path = ($pos === false ? $path : substr($path, 0, $pos));

        if ($this->prefix) {
            if (strrpos($path, $this->prefix) === 0) {
                $path = substr($path, strlen($this->prefix));
                $this->parseVersion($path);
            } else {
                $path = null;
            }
        } else {
            $this->parseVersion($path);
        }

        return $path;
    }

    protected function parseVersion(&$path)
    {
        if (preg_match('#^\/(v[0-9]+)\/#', $path, $matches)) {
            $this->version = $matches[1];
            $path = substr($path, strlen('/' . $this->version));
        } else {
            $this->handleException('No version found in the route: ' . $path);
        }
    }

    /**
     * Get controller class name.
     *
     * @return string
     */
    protected function getControllerClassName()
    {
        $setting = $this->route->getSetting();
        if (isset($this->container['modules'][$setting[self::MODULE_ROUTE_KEY]])) {
            return str_replace('\\\\', '\\', ($this->container['modules'][$setting[self::MODULE_ROUTE_KEY]]->getNs() ?? 'App\\') . 'Controllers\\'.ucfirst($this->controllerClassName));  
        }
        else {
            return $this->controllerClassName;
        }
    }

    /**
     * Set the controller class name.
     *
     * @param string|array $className
     */
    protected function setControllerClassName($className)
    {
        if (is_string($className)) {
            $this->controllerClassName = $className;
        } elseif (is_array($className)) {
            $this->controllerClassName = implode('\\', array_map(function($val) {
                return ucfirst($val);
            }, $className));
        } else {
            throw new AppException('Invalid controller class: ' . var_export($className, true));
        }
    }

    /**
     * Get the controller folder.
     *
     * @return string
     */
    protected function getFolder()
    {
        return $this->folder;
    }

    /**
     * Set controller folder.
     *
     * @param string $folder
     */
    protected function setFolder($folder)
    {
        $this->folder = $folder;
    }

    /**
     * Get the controller class.
     *
     * @return \Key\Abstracts\Controller
     */
    protected function getController()
    {
        return $this->controller;
    }

    /**
     * Set Controller class.
     *
     * @param \Key\Abstracts\Controller $controller
     */
    protected function setController($controller)
    {
        $this->controller = $controller;
        $container = $this->container;
        $isAuth = $this->route->isAuth();
        $this->controllerClosure = function($method, $params) use($controller, $container, $isAuth) {
            //static::log('[Router] Load Controller class: '.get_class($controller));

            $result = call_user_func_array(array($controller($container, $isAuth), $method), $params);
            $controller->setStatus($result);

            return $result;
        };
    }

    /**
     * Get the action of the controller.
     *
     * @return string
     */
    protected function getAction()
    {
        return $this->action;
    }

    /**
     * Set the action of the controller.
     *
     * @param string $action
     */
    protected function setAction($action)
    {
        $this->action = $action;
    }

    /**
     * Get normalized action name.
     *
     * @return null|string
     */
    protected function getNormalizedAction()
    {
        return $this->action ? $this->action . 'Action' : null;
    }

    /**
     * Get the original parameters of the request.
     *
     * @return array
     */
    protected function getParams()
    {
        if (method_exists($this->request, 'getParams')) {
            return $this->request->getParams();
        }

        return array();
    }

    /**
     * Get query parameters.
     *
     * @return array
     */
    protected function getQueryParams()
    {
        return $this->request->getQueryParams() ? $this->request->getQueryParams() : array();
    }

    /**
     * Get body parameters.
     *
     * @return array
     */
    protected function getBodyParams()
    {
        return $this->request->getParsedBody() ? $this->request->getParsedBody() : array();
    }

    protected function getInputClassFromModule($type)
    {
        if ($this->container->offsetExists('appName')) {
            $moduleName = $this->container['appName'];
            /** @var \Key\Foundation\Module $module */
            $module = $this->container['modules'][$moduleName];
            return $module->getRecordClass($type);
        }
        return false;
    }

    /**
     * Get input class name, such as StringInput, PaginationInput, etc.
     *
     * @param string $type Input type, such as string, array or pagination
     *
     * @return null|string
     */
    protected function getInputClass($type)
    {
        $type = ucfirst($type);
        $className = null;

        $this->getInputClassFromModule($type);

        if (InputFactory::isBaseType($type)) {
            $className = '\\Key\\Inputs\\' . $type . 'Input';
        } elseif (class_exists('\\Key\\Records\\' . $type)) {
            $className = '\\Key\\Records\\' . $type;
        } elseif (class_exists('\\App\\Records\\' . $type)) {
            $className = '\\App\\Records\\' . $type;
        } elseif (($className = $this->getInputClassFromModule($type)) && class_exists($className)) {
            // do nothing
            // error_log('from module: ' . $className);
        } else {
            $className = $this->container['config']['global.externalInputClass'];
        }

        return $className;
    }

    /**
     * Get filtered parameters of the request.
     *
     * @param string $method
     * @return array
     * @throws AppException
     * @throws \ReflectionException
     */
    protected function getFilteredParams($method = 'get')
    {

        if ($this->filteredParams) {
            return $this->filteredParams;
        }

        $this->filteredParams = array();

//        switch ($mode) {
//            case 1:
//                $params = $this->getQueryParams();
//                break;
//            case 2:
//                $params = $this->getBodyParams();
//                break;
//            default:
//                $params = $this->getParams();
//        }
        switch ($method) {
            case self::HTTP_METHOD_GET:
                $params = $this->getQueryParams();
                break;
            case self::HTTP_METHOD_POST:
                $params = $this->getBodyParams();
                break;
            case self::HTTP_METHOD_PUT:
            default:
                $params = $this->getParams();
        }

        $uploadedFiles = $this->request->getUploadedFiles();

        if ($this->isNameMathMode()) {
            $reflector = new ReflectionClass($this->getControllerClassName());
            //Get the parameters of a method
            $parameters = $reflector->getMethod($this->getNormalizedAction())->getParameters();
            foreach($parameters as $parameter) {
                $this->filterParam($parameter->name, $params, $uploadedFiles);
            }
        } else {
            if (is_array($params) && $this->inputsConfig) {
                foreach ($this->inputsConfig as $key => $input) {
                    $this->filterParam($key, $params, $uploadedFiles);
                }
            } else {
                if ($this->inputsConfig) {
                    foreach ($this->inputsConfig as $key => $input) {
                        $this->filteredParams[$key] = null;
                    }
                }
            }
        }

        return $this->filteredParams;
    }

    /**
     * @param $key
     * @param $params
     * @param array|null $uploadedFiles
     * @throws AppException
     */
    protected function filterParam($key, $params, $uploadedFiles = null)
    {
        if ($this->inputsConfig && isset($this->inputsConfig[$key])) {

            $input = $this->inputsConfig[$key];
            $type = isset($input['type']) && $input['type'] ? $input['type'] : 'String';
            if ($type === 'file') {
                $value = isset($uploadedFiles[$key]) ? $uploadedFiles[$key] : null;
            } else {
                if (isset($input['uriIndex'])) {
                    $uriIndex = (int) $input['uriIndex'];
                    $value = $this->route->getUriParam($uriIndex);
                    static::log('from uri: '.$value);
                } elseif ($this->route->getUriParam($key)) {
                    $value = $this->route->getUriParam($key);
                } else {
                    $value = isset($params[$key]) ? $params[$key] : null;
                }
            }

            $className = $this->getInputClass($type);
            // error_log('filterParam: ' . $key . ' -- ' . $className . ' -- ' . json_encode($value));
            if ($className) {
                /** @var \Key\Interfaces\InputInterface|callable $obj */
                $obj = new $className($key, $value, $input);
                $obj->setApp($this->container);
                $valid = $obj->validate();
                if ($valid === Input::VALID_CODE_SUCCESS) {
                    $this->filteredParams[$key] = $obj->getValidValue();
                } else {
                    //static::log('[getFilteredParams] Invalid input ' . $key . ' validation code: ' . $valid);
                    $this->invalid[] = array(
                        'name' => $key,
                        'value' => $value,
                        'code' => $valid,
                        'subKey' => method_exists($obj, 'getInvalidSubKey') ? call_user_func(array($obj, 'getInvalidSubKey')) : null,
                        'message' => $obj->getPhrase()
                    );
                    $this->filteredParams[$key] = null;
                }
            } else {
                static::log('[WARNING] Input class not found: ' . $className);
                //$this->filteredParams[$key] = null;
                throw new AppException('Input class not found for type: ' . $type);
            }
        } else {
            static::log('[WARNING] Action parameter does not have input setting.');
            $this->filteredParams[$key] = null;
        }
    }

    /**
     * Retrieves output content type.
     *
     * @return mixed
     */
    public function getOutputContentType()
    {
        return $this->response->getHeaderLine('Content-Type') ?: $this->outputContentType ?: $this->getOutputContentTypeFromRequest();
    }

    protected function getOutputContentTypeFromRequest()
    {
        return $this->validOutputContentTypes['json'];
    }

    /**
     * Check if the action exists.
     *
     * @return bool
     */
    public function hasAction()
    {
        return $this->controller ? method_exists($this->controller, $this->getNormalizedAction()) : false;
    }

    /**
     * @return bool
     */
    public function isOrderMatchMode()
    {
        return $this->container['config']['global.inputMatchMode'] === self::DEFAULT_INPUT_MATCH_MODE;
    }

    /**
     * @return bool
     */
    public function isNameMathMode()
    {
        return $this->container['config']['global.inputMatchMode'] === self::INPUT_MATCH_MODE_NAME;
    }

    /**
     * Re-organize the response by output type.
     *
     * @param mixed $data
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    protected function processResponse($data, ResponseInterface $response = null)
    {
        $response = $response ?: $this->container['response'];

        $contentType = $this->getOutputContentType();
        switch ($contentType) {
            case 'application/xml':
            case 'text/xml':
                // XML
                if (method_exists($response, 'withXml')) {
                    $response = $response->withXml($data);
                }
                break;
            case 'text/html':
                // HTML
                if (method_exists($response, 'withHtml')) {
                    $response = $response->withHtml($data);
                }
                break;
            case 'application/json':
            default:
                // JSON
                if (method_exists($response, 'withJson')) {
                    $response = $response->withJson($data);
                }
        }

        return $response;
    }

    /**
     * Validate outputs.
     *
     * @param array $outputs
     * @return array
     * @throws AppException
     */
    protected function filterOutputs($outputs)
    {
        $strictOutput = env('ROUTE_OUTPUT_STRICT');
        if ($strictOutput) {
            $validOutputs = array();
            foreach($this->outputsConfig as $key => $config) {
                $value = isset($outputs[$key]) ? $outputs[$key] : null;
                $type = isset($config['type']) && $config['type'] ? $config['type'] : 'String';

                $className = $this->getInputClass($type);
                if ($className) {
                    /** @var \Key\Interfaces\InputInterface|callable $obj */
                    $obj = new $className($key, $value, $config);
                    $valid = $obj->validate();
                    if ($valid === Input::VALID_CODE_SUCCESS) {
                        $validOutputs[$key] = $outputs[$key];
                    } else {
                        $this->handleException(sprintf('Invalid output %s', $key));
                    }
                } else {
                    $this->handleException(sprintf('Output class `%s` not found', $className));
                }

            }

            return $validOutputs;
        }

        return $outputs;
    }

    protected function isH5($app)
    {
        $request = $app['request'];
        $platform = $request->getHeaderLine('App-Platform');
        if (!empty($platform)) {
            return true;
        }

        $env = $app['environment'];
        $referer = $env->get('HTTP_REFERER');
        $refererHost = parse_url($referer, PHP_URL_HOST);

        if ($refererHost) {
            $h5URI = env('H5_URI');
            if ($h5URI) {
                $uris = explode(',', $h5URI);
                foreach ($uris as $uri) {
                    $regex = str_replace('.', '\.', $uri);
                    $regex = str_replace('*', '.+', $regex);
                    if (preg_match('/^' . $regex . '$/', $refererHost)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    protected function getSessionLifetime($app, $sid = null)
    {
        $authToken = $app['request']->getHeaderLine('Auth-Token');
        if (!$authToken || $authToken == 'null') return (int) $app['config']['session.lifetime'];

        $authToken = urldecode($authToken);
        if (substr($authToken, 0, 2) === 's:') {
            $authToken = substr($authToken, 2);
        }
        $sid = explode('.', $authToken)[0];
        if (!$sid) {
            return (int) $app['config']['session.lifetime'];
        }

        $res = $app['session.client']->get($sid);
        if ($res) {
            $arr = json_decode($res, true);
            if (isset($arr[\App\Common\Constants::SESSION_KEY_CURRENT_ACCOUNT_ID])) {
                $aid = (int) $arr[\App\Common\Constants::SESSION_KEY_CURRENT_ACCOUNT_ID];
                $dbConnector = new \App\Common\DBConnector($app);
                $dbConnector->setupById($aid);

                $inCache = false;
                $cacheKey = 'ACCOUNT:' . $aid;
                $row = $app['cache']->get($cacheKey);
                if ($row) {
                    $row = json_decode($row, true);
                    $inCache = true;
                }
                else {
                    $row = $app['mongodb']->fetchRow(\App\Common\Constants::COLL_ACCOUNT, ['id' => $aid, 'status' => 1]);
                }

                $sessionConfig = ArrayGet($row, 'config.session');
                if ($sessionConfig) {
                    $lifetimeKey = $this->isH5($app) ? 'lifetime_h5' : 'lifetime';
                    $lifetime = $sessionConfig[$lifetimeKey] ?? (int) $app['config']['session.lifetime'];

                    if ($lifetime && !$inCache) {
                        $app['cache']->set($cacheKey, $row);
                    }
                }
            }
        }
        return env('SESSION_LIFETIME', 86400);
    }

    /**
     * Execute the router.
     *
     * @return ResponseInterface|null
     * @throws AppException
     * @throws RuntimeException
     * @throws UnexpectedValueException
     */
    public function exec()
    {

        $strict = $this->container['config']['global.strict'];

        $this->response = $this->response->withHeader('Content-Type', $this->getOutputContentType());

        $routeMethod = $this->route->getMethod();
        if ($strict && empty($routeMethod)) {
            if (empty($routeMethod)) {
                $this->handleException(sprintf('No request method specified in the route: %s', $this->originalPath), Constants::SYS_ROUTE_METHOD_REQUIRED);
            } else {
                static::log(sprintf('No request method specified in the route: %s', $this->originalPath));
            }
        }

        $requestMethod = strtolower($this->request->getMethod());

        //Verify OpenApi is valid
        $is_open_api = $this->route->isOpen();
        if(isset($is_open_api) && $is_open_api){
            $this->validateOpenApi();
        }

        // OPTIONS request, don't response
        if ($requestMethod == 'options') {
            return $this->response;
        }

        $mode = 0;
        if (!is_null($routeMethod) && $routeMethod != $requestMethod) {
            if ($strict) {
                $this->handleException('Invalid request method', Constants::SYS_ROUTE_INVALID_METHOD);
            } else {
                static::log(sprintf('[WARING] Request method `%s` does not match the route request method `%s`.', $requestMethod, $routeMethod));
            }
        } else {

            $mode = $requestMethod == self::DEFAULT_ROUTE_METHOD ? 1 : 2;
        }

        $lifetime = $this->getSessionLifetime($this->container);
        $conf = $this->container['config'];
        $conf['session.lifetime'] = $lifetime;
        $conf['session.cookie_lifetime'] = $lifetime;
        $this->container->offsetSet('config', $conf);

        // /** @var \Key\Session $session */
        // $session = $this->container['session'];
        // $session->start();

        // Check authorization
        if ($this->route->isAuth()) {
            /** @var \Key\Session $session */
            $session = $this->container['session'];
            $session->start();
            
            // if (!$is_open_api) {
                if (!$session || !$session->get(Constants::SESSION_USR_KEY)) {
                    $this->handleException('Require login', Constants::SYS_REQ_AUTH);
                }
            // }
        }

        //  Check ACL

       /* if ($this->route->getAcl() && isset($this->container['config']['hook.aclCheck']) && is_callable($this->container['config']['hook.aclCheck'])) {
            if (!call_user_func($this->container['config']['hook.aclCheck'], $this->route->getAcl(), $this->container)) {
                $this->handleException('No permission', Constants::SYS_PERMISSION_FAULT);
            }
        }*/

        if ($this->hasAction()) {

            $fromCache = 0;
            if (env('ROUTER_CACHE_RULE_ENABLED') && $this->route->getCacheRule()) {
                if (isset($this->container['config']['hook.cacheRule']) && is_callable($this->container['config']['hook.cacheRule'])) {
                    $outputs = call_user_func($this->container['config']['hook.cacheRule'], 'get', $this->container, $this->route);
                    $statusCode = 0;
                    $statusMessage = 'OK';
                    $fromCache = 1;
                }
            }

            if (!isset($outputs) || !$outputs) {
                $fromCache = 0;
                $params = $this->getFilteredParams($requestMethod);


                if ($this->invalid && count($this->invalid)) {
                    static::error(sprintf('[Router] %s %s', $requestMethod, $this->route->getRouteRule()));
                    static::error(sprintf('[Router] Invalid inputs: %s', json_encode($this->invalid)));
                    // static::error(sprintf('[Router] Original inputs: %s',  json_encode($this->getParams())));
                    $inputInvalid = $this->container['translator']->get('input:invalid');
                    if (env('APP_ENV') == 'development') {
                        $inputInvalid .= ' ' . json_encode($this->invalid);
                    }
                    throw new RuntimeException($inputInvalid, Constants::SYS_ROUTE_PARAM_ERROR);
                }

                // $noPermStr = $this->container['translator']->get('system:perm:fail');
                // error_log('@@@@@@noPermStr@@@@@' . $noPermStr);
                //error_log(' acl: ' . $this->route->getAcl());
                if($acl_name = $this->route->getAcl()){
                    if(preg_match('/^[0-9a-zA-Z_]{1,}$/',$acl_name)){
                        if(isset($this->container['config']['hook.aclCheck']) && is_callable($this->container['config']['hook.aclCheck'])){
                            if (!call_user_func($this->container['config']['hook.aclCheck'], $this->route->getAcl(), $this->container)) {
                                $this->handleException($this->container['translator']->get('system:perm:fail'), Constants::SYS_PERMISSION_FAULT);
                            }
                        }
                    }else{
                        if (!$this->route->executeAclCheck($this->container, $params)) {
                            $this->handleException($this->container['translator']->get('system:perm:fail'), Constants::SYS_PERMISSION_FAULT);
                        }
                    }
                }


                //error_log('data acl: ' . $this->route->getDataAcl());
                if ($this->route->getDataAcl()) {
                    if (!$this->route->executeDataAclCheck($this->container, $params)) {
                        $this->handleException($this->container['translator']->get('system:perm:fail'), Constants::SYS_PERMISSION_FAULT);
                    }
                }

                if ($this->container->offsetExists('current_module')) {
                    /** @var \Key\Foundation\Module $module */
                    $module = $this->container['current_module'];
                    $module->callHook('after_inputs_validation', ['app' => $this->container, 'params' => &$params]);
                }
                else {
                    error_log('current_module not set');
                }

                $closure = $this->controllerClosure;
                $result = $closure($this->getNormalizedAction(), $params);
                if ($strict && (is_null($result) || !is_numeric($result))) {
                    throw new UnexpectedValueException('Controller must return numeric value.', Constants::SYS_ROUTE_INVALID_RETURN);
                }

                $statusMessage = $this->controller->getStatusMessage();

                if (!is_null($result)) {
                    $statusCode = $result;
                } else {
                    $statusCode = $this->controller->getStatusCode();
                    if (is_null($result)) {
                        $statusCode = Constants::SYS_SUCCESS;
                        static::log('[WARNING] ' . $this->controllerClassName . ' ' . $this->getNormalizedAction() . ' has not return code, use default return code.');
                    }
                }

                $outputs = $this->controller->getOutputs();
            }

            // filter outputs
            //$outputs = $this->filterOutputs($outputs);

            if (method_exists($this->controller, 'handleOutputResult')) {
                $json = call_user_func(array($this->controller, 'handleOutputResult'));
            }
            else {
                $json = array(
                    'status' => (int) $statusCode,
                    'msg' => (string) $statusMessage,
                    'success' => ((int) $statusCode) === 0
                );
    
                if ($this->route->isDeprecated($recommend)) {
                    $json['warn'] = '***THE ROUTE IS DEPRCATED!' . ($recommend ? 'See the route `' . $recommend . '`' : '') . '***';
                }
    
                if (env('APP_ENV') == 'development') {
                    $json['summary'] = $this->route->getSetting()->get('summary');
                    $json['desc'] = $this->route->getDescription();
                    $json['contributors'] = $this->route->getContributors();
                }
    
                if (!empty($outputs)) {
                    $json['data'] = $outputs;
    
                    if (!$fromCache && $json['status'] === 0 && env('ROUTER_CACHE_RULE_ENABLED') && $this->route->getCacheRule()) {
                        if (isset($this->container['config']['hook.cacheRule']) && is_callable($this->container['config']['hook.cacheRule'])) {
                            call_user_func($this->container['config']['hook.cacheRule'], 'set', $this->container, $this->route, $outputs);
                        }
                    }
                }
            }

            if (($view = $this->route->getView())) {

                if (is_callable($view)) {
                    return $this->processResponse($view($outputs, $statusCode, $statusMessage, $this->container));
                }
                elseif (file_exists($view)) {
                    (function() use($outputs, $view, $statusCode, $statusMessage){
                        $scope = $outputs;
                        include $view;
                    })();                    
                }
                else {
                    $this->response->withHeader('Content-Type', $this->validOutputContentTypes['html']);
                    return $this->processResponse($view, $this->response);
                }
            } else {
                return $this->processResponse($json, $this->response);
            }
        } else {
            $this->handleException(sprintf('Action %s not found in Controller %s', $this->getNormalizedAction(), $this->getControllerClassName()), Constants::SYS_ROUTE_ACTION_NOT_FOUND);
        }
        return null;
    }

    /**
     * @param $msg
     * @param int $code
     * @throws AppException
     */
    protected function handleException($msg, $code = Constants::SYS_ERROR_DEFAULT)
    {
        $this->response = $this->response->withHeader('Content-Type', $this->getOutputContentType());
        throw new AppException($msg, $code);
    }

    /**
     * 验证openApi是否有效
     */
    protected function validateOpenApi(){
        $api_access_key = $this->request->getHeaderLine('Api-Access-Key');
        $api_access_token = $this->request->getHeaderLine('Api-Access-Token');
        if($api_access_key && $api_access_token){
            $result = call_user_func($this->container['config']['hook.openApiVerify'], $api_access_key, $api_access_token, $this->container);
            //$secret_key = $account['config']['api']['secret_key'];
            //$account_access_token = hash_hmac("sha256", $api_access_key, $secret_key);
            if (!$result) {
                $this->handleException('Api authentication fail', Constants::OPEN_API_AUTHENTICATION_FAILED);
            }else{
                //$this->container['__CONSOLE__'] = 1;
                //$this->container[\App\Common\Constants::SESSION_KEY_CURRENT_ACCOUNT_ID] = $account['id'];
            }
        }else{
            $this->handleException('api unauthorized', Constants::OPEN_API_UNAUTHENTICATION);
        }
    }
}
