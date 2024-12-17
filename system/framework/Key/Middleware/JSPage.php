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
use Key\Inputs\Input;
use Key\Inputs\InputFactory;
use Key\Route;
use Pimple\Container;
use ReflectionClass;
use Psr\Http\Message\ResponseInterface;
use Key\Abstracts\Middleware;

/**
 * Middleware JavascriptPage
 */
class JSPage extends Middleware
{

    const MODULE_ROUTE_KEY = '_m_';

    protected static $default_routes = array(
        // '/@admin/@status' => array(
        //     'controller' => '/internalAdmin/status',
        //     'inputs' => array(
        //         'token' => array(
        //             'required' => 1,
        //             'format' => '/g5xBNRIdAo6nsFEHIPMGbxV2frp1JW4U/'
        //         )
        //     )
        // )
    );

    protected function loadClasses($routeSetting, $container)
    {
        // error_log('JSPage@@@' . var_export($routeSetting, true));
        if (isset($routeSetting[self::MODULE_ROUTE_KEY]) && isset($container['modules'][$routeSetting[self::MODULE_ROUTE_KEY]])) {
            /** @var \Key\Foundation\Module $module */
            $module = $container['modules'][$routeSetting[self::MODULE_ROUTE_KEY]];
            // error_log(var_export($module, true));
            // $loader = new ClassLoader();

            // /** @deprecated */
            // $loader->addPsr4($module->getNs(), $module->getClassPath());

            // $loaderDef = $module->getClassLoaderDef();
            // if ($loaderDef) {
            //     foreach ($loaderDef as $def) {
            //         error_log('[loadClasses] def: ' . json_encode($def));
            //         $loader->addPsr4($def['ns'], $def['path']);
            //     }
            // }

            // $loader->register();
            $module->registerClasses($container);
        }
        else {
            error_log('load classes fail');
        }
    }

    /**
     * Maintenance Middleware.
     *
     * @param Container $container
     * @param Closure $next
     * @return ResponseInterface
     * @throws ServiceUnavailableException
     */
    public function __invoke(Container $container, Closure $next)
    {

        $request = $container['request'];
        /** @var \Key\Http\Response $response */
        $response = $container['response'];

        $uri = $request->getUri();

        $bathPath = $uri->getPath();

        if ($container->offsetExists('modules')) {
            $pages = [];
            foreach ($container['modules'] as $module) {
                $pageFile = $module->getPageFile();
                if (file_exists($pageFile)) {
                    $routes = include($pageFile);
                    $routes = array_map(function($item) use($module) {
                        $item[self::MODULE_ROUTE_KEY] = $module->getName();
                        return $item;
                    }, $routes);
                    $pages = array_merge($pages, $routes);
                }
            }
            $container['pages'] = $pages;
        }

        if (!$container->offsetExists('pages')) {
            return $next($container);
        }
        $jsps = $container['pages'];

        $path = $this->normalizePath($bathPath);

        if ($jsps) {

            $jsps = array_merge(self::$default_routes, $jsps);

            $appName = 'base';//$request->getAttribute('currentApp');

            foreach ($jsps as $key => $jsp) {
                $route  = null;

                $pattern = '/^'.str_replace('/', '\/', $key).'$/';
                $pattern = $this->paramMatch($pattern);
                if (preg_match($pattern, $path, $matches) > 0) {
                    array_shift($matches);
                    $this->loadClasses($jsp, $container);
                    $route = new Route($uri, $jsp, $matches, $key);
                }
                if ($route) {
                    if (isset($jsp['controller']) && ($ctrlPath = $jsp['controller'])) {
                        // $paths = explode('/', rtrim($ctrlPath, '/'));
                        // $controllerClassName = $this->getController($paths[1]);
                        // $controllerAction = $paths[2] . 'Action';

                        $res = $this->getControllerV2($ctrlPath);
                        $controllerClassName = $res[0];
                        $controllerAction = $res[1];

                        if (class_exists($controllerClassName)) {

                            $ref = new ReflectionClass($controllerClassName);
                            if ($ref->hasMethod($controllerAction)) {
                                /** @var \Key\Abstracts\Controller  $controller */
                                //$controller = $ref->newInstance();
                                $controller = new $controllerClassName;

                                $controllerClosure = function($method, $params) use($controller, $container, $jsp, $route) {
                                    $request = $container['request'];
                                    $params = $request->getQueryParams();

                                    $uriParams = $route->getUriParams();
                                    $params = array_merge($params, $uriParams);

                                    //Tools::log('[JSPage] params: ' . var_export($params, true));
                                    $newParams = array();
                                    if (isset($jsp['inputs']) && $inputs = $jsp['inputs']) {
                                        foreach($inputs as $inputKey => $input) {
                                            $paramValue = isset($params[$inputKey]) ? $params[$inputKey] : null;
                                            $inputValidator = InputFactory::getInstance($inputKey, $paramValue, $input);
                                            if ($inputValidator->validate() == Input::VALID_CODE_SUCCESS) {
                                                $newParams[$inputKey] = $inputValidator->getValidValue();
                                            } else {
                                                error_log('[JSPage] invalid input: ' . $inputKey . '--' . var_export($paramValue, true));
                                                exit('[Jsp] Invalid input!');
                                            }
                                        }
                                    }
                                    //Tools::log('[JSPage] new params: ' . var_export($newParams, true));
                                    call_user_func_array(array($controller($container), $method), $newParams);
                                };

                                $controllerClosure($controllerAction, array());

                                $scope = $controller->getOutputs();
                                if ($response->isRedirect()) {
                                    return $next($container);
                                } elseif (isset($jsp['view']) && ($viewPath = $jsp['view'])) {
                                    if (!file_exists($viewPath)) {
                                        $viewFile = APPS_PATH . DS . $appName . DS . 'mc' . DS . 'Views' . DS .$viewPath;
                                    } else {
                                        $viewFile = $viewPath;
                                    }
                                    if (file_exists($viewFile)) {
                                        include $viewFile;
                                    } else {
                                        exit('[JSPage] View file not found!');
                                    }
                                } else {

                                    header('Content-Type: application/javascript');
                                    exit(json_encode($scope));
                                }
                            } else {
                                //echo 'Action Not Found!';
                                error_log('[JSPage] Action Not Found!');
                            }

                            exit();
                        } else {
                            error_log('[JSPage::__invoke] Controller class not found: ' . $controllerClassName);
                        }

                    }
                    break;
                }
            }
        }

        return $next($container);
    }

    protected function getController($name)
    {
        if (class_exists('App\\Controllers\\'.ucfirst($name))) {
            return 'App\\Controllers\\'.ucfirst($name);
        } elseif (class_exists('Key\\Middleware\\'.ucfirst($name))) {
            return 'Key\\Middleware\\'.ucfirst($name);
        }

        return null;
    }

    protected function getControllerV2($paths)
    {
        $paths = explode('/', trim($paths, '/'));
        // last is action
        $actionName = array_pop($paths) . 'Action';
        $paths = array_map(function($item) {
            return ucfirst($item);
        }, $paths);
        $className = 'App\\Controllers\\' . implode('\\', $paths);
        return [$className, $actionName];
    }

    protected function normalizePath($path)
    {
        $pos = strrpos($path, '.', strrpos($path, '/') !== false ? strrpos($path, '/') : 0);
        $path = ($pos === false ? $path : substr($path, 0, $pos));

        return $path;
    }

    protected function paramMatch($str)
    {
        return preg_replace_callback('/\(\:([0-9a-zA-Z_]+)\)/', function ($matches) {
            return '(?<' . $matches[1] . '>[^\/]+)';
        }, $str);
    }
}