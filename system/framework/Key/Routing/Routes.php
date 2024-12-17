<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2022 yidianzhishi.com
 * @version 1.0.0
 */

namespace Key\Routing;

use Composer\Autoload\ClassLoader;
use Key\Container;
use Key\Exception\AppException;
use Key\Filesystem\FileFactory;
use Key\Route;

class Routes
{
    const MODULE_ROUTE_KEY = '_m_';

    const ROUTE_CACHE_NAME = 'YDZS_ROUTES';

    /** @var \Key\Container $container */
    protected $container;
    /** @var \Key\Http\Request $request */
    protected $request;

    /**
     * The array of route.
     *
     * @var array
     */
    protected $routes = [];

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

    protected $version = 'v1';

    protected $requestMethod = null;
    protected $requestPathLead = null;
    protected $path;

    /**
     * Request path.
     *
     * @var string
     */
    protected $originalPath;

    public function __construct(Container $container = null, $request = null, $prefix = '')
    {
        $this->container = $container ?: new Container;
        $this->request = $request;
        $this->prefix = $prefix;

        $uri = $this->request->getUri();
        $this->requestMethod = strtoupper($this->request->getMethod());

        if (method_exists($uri, 'getBasePath')) {
            $bathPath = $uri->getBasePath();
        } else {
            $bathPath = $uri->getPath();
        }

        $originalPath = '/' . trim($bathPath.'/'.$uri->getPath(), '/');
        $this->path = $this->normalizePath($originalPath);

        if ($this->path) {
            $pathPieces = array_values(array_filter(explode('/', $this->path)));
            $this->requestPathLead = $pathPieces[0];
            if ($this->isCacheEnabled()) {
                $this->loadLeadRoutes();
            }
            else {
                $this->routes = $this->loadRoutes();
            }
        }
        else {
            error_log('Empty path: ' . $uri);
        }
    }

    /**
     * Undocumented function
     *
     * @param [type] $prefix
     * @return $this
     * @deprecated 1.0.0
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
        return $this;
    }

    protected function isProd()
    {
        return env('APP_ENV') == 'production';
    }

    protected function leadRoutes($routes, $render = false)
    {
        $leadRoutes = [];
        $renderRoutes = [];
        foreach ($routes as $key => $item) {
            $pieces = array_values(array_filter(explode(' ', $key)));
            $method = strtoupper($pieces[0]);
            $lead = array_values(array_filter(explode('/', $pieces[1])))[0];
            $leadRoutes[$method .' ' . $lead][$key] = $item;
            $renderRoutes[$lead][$method .' ' . $lead][$key] = $item;
        }
        if ($render) {
            // output to each file
            $dir = $this->getLeadCacheDir();
            $pid = $dir . DIRECTORY_SEPARATOR . 'PID';
            file_put_contents($pid, time());
            foreach ($renderRoutes as $lead => $items) {
                $file = $dir . DIRECTORY_SEPARATOR . $lead;
                file_put_contents($file, serialize($items));
            }
            file_put_contents($dir . DIRECTORY_SEPARATOR . 'DONE', time());
            unlink($pid);
        }

        return $leadRoutes;
    }

    protected function getCacheDir(&$usingRouteCache = 0)
    {
        $usingRouteCache = env('APP_ROUTE_CACHE_ENABLED', 0);
        $cacheDir = rtrim(env('APP_ROUTE_CACHE_PATH', FileFactory::getCacheStorage()), '/');
        // $cacheDir = realpath($cacheDir);
        if (!file_exists($cacheDir)) {
            if (!mkdir($cacheDir, 0777, true)) {
                error_log('Fail to create route cache dir: ' . $cacheDir . ', force using /tmp');
                // $usingRouteCache = 0;
                $cacheDir = '/tmp';
            }
        }
        if (!is_writable($cacheDir)) {
            error_log('The cache folder is not writable, disable route cache!');
            $usingRouteCache = 0;
        }
        return $cacheDir;
    }

    protected function isCacheEnabled()
    {
        return env('APP_ROUTE_CACHE_ENABLED', 0);
    }

    protected function getCacheVerion()
    {
        return (env('APP_ROUTE_CACHE_DAILY', 1) ? date('y-m-d') : env('APP_BUILD_VERSION', date('y-m-d')));
    }

    protected function getLeadCacheDir()
    {
        $dir = $this->getCacheDir() . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . $this->getCacheVerion();
        if (!file_exists($dir)) {
            if (!mkdir($dir, 0777, true)) {
                error_log('Fail to create dir ' . $dir);
                $dir = '/tmp';
            }
        }
        $dir = realpath($dir);
        if (!is_writable($dir)) {
            error_log('Not writable: ' . $dir);
            $dir = realpath('/tmp');
        }
        return $dir;
    }

    protected function loadRoutes()
    {
        $routes = [];
        // check route cache
        $cacheDir = $this->getCacheDir($usingRouteCache);
        $cacheFile = $cacheDir . DS . 'cache_routes.' . (env('APP_ROUTE_CACHE_DAILY', 1) ? date('y-m-d') : env('APP_BUILD_VERSION', date('y-m-d')));
        // error_log('route cache: ' . $cacheFile);
        $pid = $cacheFile . '.pid';
        if ($usingRouteCache 
            && !file_exists($pid)) { // Skip when cache file is rendering
            if (!file_exists($cacheFile)) {
                $this->cacheRoutes($cacheFile, $pid);
            }

            $routes = null;
            if (extension_loaded('apcu') && function_exists('apcu_enabled') && apcu_enabled()) {
                // error_log('1111*** Read from apcu ***');
                $res = apcu_fetch(self::ROUTE_CACHE_NAME, $success);
                if ($res) {
                    $routes = json_decode($res, true);
                    if (json_last_error() != JSON_ERROR_NONE) {
                        error_log('Error: ' . json_last_error_msg());
                    }
                }
                else {
                    // error_log('1 no data from apcu cache: ' . $apcuKey);
                }
            }
            
            if (is_null($routes)) {
                // $tmp1 = include_once $cacheFile;
                // error_log('loading cache file...');
                $tmp1 = unserialize(file_get_contents($cacheFile));
                if (is_array($tmp1)) {
                    $routes = array_merge($routes ?: [], $tmp1);
                    // error_log('Load routes: ' . count($routes));
                    $routes = $this->leadRoutes($routes);
                    if (extension_loaded('apcu') && function_exists('apcu_enabled') && apcu_enabled()) {
                        // error_log('*** store to apcu ***');
                        apcu_store(self::ROUTE_CACHE_NAME, json_encode($routes));
                    }
                }
                else {
                    error_log('unserialize route cache file fail!');
                }
            }

            // $this->container['routes'] = $routes;
        }
        else {
            // error_log('[WARN] no route cache!');
            $routes = $this->cacheRoutes($cacheFile, $pid, false);
            $routes = $this->leadRoutes($routes);
            // $this->container['routes'] = $routes;
        }
        // if ($this->container->offsetExists('routes')) {
        //     return $this->container['routes'];
        // }

        return $routes;
    }

    protected function loadLeadRoutes()
    {
        if (!$this->requestPathLead) return;

        $apcuKey = self::ROUTE_CACHE_NAME . '_' . $this->requestPathLead;
        // error_log('key: ' . $apcuKey);
        if (extension_loaded('apcu') && function_exists('apcu_enabled') && apcu_enabled()) {
            // error_log('*** Read from apcu ***');
            $res = apcu_fetch($apcuKey, $success);
            if ($res) {
                $this->routes = json_decode($res, true);
                if (json_last_error() != JSON_ERROR_NONE) {
                    error_log('Error: ' . json_last_error_msg());
                }
            }
            else {
                // error_log('2 no data from apcu cache: ' . $apcuKey);
            }
        }
        if ($this->routes) return;

        $pidFile = $this->getLeadCacheDir() . DIRECTORY_SEPARATOR . 'PID';
        $doneFile = $this->getLeadCacheDir() . DIRECTORY_SEPARATOR . 'DONE';

        if (file_exists($pidFile)) {
            // error_log('Route file renderring...');
            // error_log('Loading route from origin file...');
            $this->routes = $this->cacheRoutes('/tmp/' . uniqid('ydzs-routes-'), '/tmp/' . uniqid('ydzs-routes-') . '.pid', false);
        }
        elseif (!file_exists($doneFile)) {
            // error_log('load all rotues...');
            $allRoutes = $this->cacheRoutes('/tmp/' . uniqid('ydzs-routes-'), '/tmp/' . uniqid('ydzs-routes-') . '.pid', false);
            $this->leadRoutes($allRoutes, true);
            $allRoutes = null;
        }

        if (!$this->routes) {
            $file = $this->getLeadCacheDir() . DIRECTORY_SEPARATOR . $this->requestPathLead;
            if (file_exists($file)) {
                $content = file_get_contents($file);
                $this->routes = unserialize($content);
                if (extension_loaded('apcu') && function_exists('apcu_enabled') && apcu_enabled()) {
                    // error_log('*** store to apcu ***');
                    apcu_store($apcuKey, json_encode($this->routes));
                }
            }
        }
    }

    // Avoid content override
    protected function includeRouteFile($file)
    {
        return require($file);
    }

    protected function cacheRoutes($cacheFile, $pid, $render = true)
    {
        $routes = [];
        // load internal routes
        $routes = \Key\Foundation\Routes\InternalRoutes::getRoutes();

        if ($this->container->offsetExists('modules')) {
            if ($render) file_put_contents($pid, date('Y-m-d H:i:s'));
            $isProd = (env('APP_ENV') == 'production');
            // $routes = [];
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
                                    foreach ($tmp as $key => &$item) {
                                        $item[self::MODULE_ROUTE_KEY] = $module->getName();
                                        // clean the routes
                                        if ($isProd) {
                                            unset($item['summary'], $item['description'], $item['contributors'], $item['created']);
                                            if (isset($item['inputs'])) {
                                                foreach ($item['inputs'] as $key1 => &$input) {
                                                    // unset($item['inputs'][$key1]['description'], $item['inputs'][$key1]['display'], $item['inputs'][$key1]['sample']);
                                                    unset($input['description'], $input['display'], $input['sample']);
                                                    if (isset($input['detail'])) {
                                                        foreach ($input['detail'] as $key2 => &$val) {
                                                            // unset($item['inputs'][$key1]['detail'][$key2]['description'], $item['inputs'][$key1]['detail'][$key2]['display'], $item['inputs'][$key1]['detail'][$key2]['sample']);
                                                            unset($val['description'], $val['display'], $val['sample']);
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                        // $tmp[$key] = $item;
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

            // $content = 'return [';
            // foreach ($routes as $key => $item) {
            //     $content .= '\'' . $key . '\' => ' . var_export($item, true) . ',' . PHP_EOL;
            // }

            // $content .= '];';
            // $dir = pathinfo($cacheFile, PATHINFO_DIRNAME);
            // if (!file_exists($dir)) {
            //     mkdir($dir, 0777, true);
            // }
            // // 去掉空行
            // $content = preg_replace("/(\r\n|\n|\r|\t)/i", '', $content);
            // $content = preg_replace('/\s{2,}/', '', $content);

            // $content = '<?php ' . $content;
            // error_log('serialize routes...');
            $content = serialize($routes);
            file_put_contents($cacheFile, $content);
            chmod($cacheFile, 0777);

            unlink($pid);
        }
        else {
            error_log('[WARN] modules not in container!');
        }
        return [];
    }

    protected function parseVersion(&$path)
    {
        if (preg_match('#^\/(v[0-9]+)\/#', $path, $matches)) {
            $this->version = $matches[1];
            $path = substr($path, strlen('/' . $this->version));
        } else {
            throw new AppException('No version found in the route: ' . $path);
        }
    }

    /**
     * Retrieves the normalized path.
     *
     * @param string $path
     * @return string
     */
    protected function normalizePath($path)
    {
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

    protected function loadClasses($routeSetting)
    {
        if (isset($routeSetting[self::MODULE_ROUTE_KEY]) && isset($this->container['modules'][$routeSetting[self::MODULE_ROUTE_KEY]])) {
            if (!$this->container->offsetExists('appName')) {
                $this->container['appName'] = $routeSetting[self::MODULE_ROUTE_KEY];
            }

            /** @var \Key\Foundation\Module $module */
            $module = $this->container['modules'][$routeSetting[self::MODULE_ROUTE_KEY]];
            $this->container['current_module'] = $module;

            $module->registerClasses($this->container);

            // $loader = new ClassLoader();

            // /** @deprecated */
            // if ($module->getNs()) {
            //     $loader->addPsr4($module->getNs(), $module->getClassPath());                
            // }

            // // Loading classes
            // $loaderDef = $module->getClassLoaderDef();
            // if ($loaderDef) {
            //     foreach ($loaderDef as $def) {
            //         error_log('[loadClasses] def: ' . json_encode($def));
            //         $loader->addPsr4($def['ns'], $def['path']);
            //     }
            // }

            // if ($dependencies = $module->getDependencies()) {
            //     foreach ($dependencies as $dependency) {
            //         if (isset($this->container['modules'][$dependency])) {
            //             error_log('load dependency module: ' . $dependency);
            //             $dependencyModel = $this->container['modules'][$dependency];
            //             $loader->addPsr4($dependencyModel->getNs(), $dependencyModel->getClassPath());
            //         }
            //         else {
            //             error_log('Module ' . $routeSetting[self::MODULE_ROUTE_KEY] . ' dependency ' . $dependency . ' not found');
            //         }
            //     }
            // }
            // if ($third = $module->getThirdClasses()) {
            //     foreach ($third as $item) {
            //         $loader->addPsr4($item['ns'], $item['classPath']);
            //     }
            // }
            // $loader->register();
        }
    }

    protected function paramMatch($str)
    {
        $matchResult = preg_replace_callback('/\(\:([0-9a-zA-Z_]+)\)/', function ($matches) {
            return '(?<' . $matches[1] . '>[^\/]+)';
        }, $str);
        if (strcmp($matchResult, $str) === 0) {
            $matchResult = preg_replace_callback('/\{([0-9a-zA-Z_]+)\}/', function ($matches) {
                error_log('matched: ' . json_encode($matches));
                return '(?<' . $matches[1] . '>[^\/]+)';
            }, $str);
        }
        return $matchResult;
    }

    /**
     * Determines if the route is disabled in the file .disallow-routes
     */
    protected function isDisable($route) {
        // load disallow list
        $basepath = $this->container['basepath'];
        $file = $basepath . DIRECTORY_SEPARATOR . '.disallow-routes';
        $routeKey = $route->getRouteKey();
        // error_log('disallow routes file: ' . $file);
        // error_log('route key: ' . $routeKey);
        if (file_exists($file)) {
            $fp = fopen($file, 'r');
            if ($fp) {
                while (($line = fgets($fp)) !== false) {
                    // error_log('----------line: ' . $line);
                    $line = trim($line);
                    if (strcmp($routeKey, $line) == 0) {
                        return true;
                    }
                }
                fclose($fp);
            }
        }
        return false;
    }

    /**
     * Lookup the route.
     *
     * @param \Key\Http\Request $request
     * @return \Key\Route|false
     */
    public function lookup()
    {
        $uri = $this->request->getUri();
        // $method = strtoupper($this->request->getMethod());

        // if (method_exists($uri, 'getBasePath')) {
        //     $bathPath = $uri->getBasePath();
        // } else {
        //     $bathPath = $uri->getPath();
        // }

        // $originalPath = '/' . trim($bathPath.'/'.$uri->getPath(), '/');
        // $path = $this->normalizePath($originalPath);
        // if (!$path) {
        //     error_log('[lookup] Empty path');
        //     return false;
        // }

        // $route = null;
        $routes = $this->routes;

        // $pathPieces = array_values(array_filter(explode('/', $path)));
        // $pathLead = $pathPieces[0];

        $method = strtoupper($this->requestMethod);
        $pathLead = $this->requestPathLead;
        $path = $this->path;

        $matchingLeadKey = $method . ' ' . $pathLead; // for example: GET abc

        if (isset($routes[$matchingLeadKey])) {
            $matchRoutes = $routes[$matchingLeadKey];
            $matchingKey = $method . ' ' . $path;
            if (isset($matchRoutes[$matchingKey]) && $setting = $matchRoutes[$matchingKey]) {
                $setting['method'] = $method;
                $this->routes = [];
                $this->loadClasses($setting);
                $route = new Route($uri, $setting, [], $path, $matchingKey);
            }
            else {
                // error_log('regex matching: ' . $path);
                foreach ($matchRoutes as $key => $setting)  {
                    if ($key) {
                        if (!startsWith($key, $method)) continue;
                        $key = array_values(array_filter(explode(' ', $key)))[1];
                        // error_log('key: ' . $key);
                        $pattern = '/'.str_replace('/', '\/', $key).'$/';
                        // error_log($path . ' - ' . $pattern);
                        $pattern = $this->paramMatch($pattern);
                        if (preg_match($pattern, $path, $matches) > 0) {
                            $setting['method'] = $method;
                            $uriParams = array();
                            if ($matches && count($matches) > 1) {
                                $uriParams = array_slice($matches, 1);
                            }
                            $this->routes = [];
                            $this->loadClasses($setting);
                            $route = new Route($uri, $setting, $uriParams, null, $key);
                            break;
                        }
                    }
                }
            }
        }
        else {
            // error_log('not matched lead: ' . $matchingLeadKey . ' path: ' . $path);
        }

        if ($route) {
            if ($this->isDisable($route)) {
                $route->setDisabled(true);
            }
            if ($route->isEnvMatch()) {
                return $route;
            }
        }

        return false;
    }
}