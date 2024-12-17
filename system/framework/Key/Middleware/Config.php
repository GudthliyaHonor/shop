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
use Pimple\Container;
use Key\Abstracts\Middleware;
use Key\Exception\AppException;

/**
 * Middleware Config
 *
 * @package Key\Middleware
 * @author Guanghui Li <liguanghui2006@163.com>
 */
class Config extends Middleware
{
    /**
     * @param Container $container
     * @param Closure $next
     * @return mixed
     * @throws AppException
     */
    public function __invoke(Container $container, Closure $next)
    {
        if ($container->offsetExists('modules')) {
            foreach ($container['modules'] as $module) {
                $configPath = $module->getConfigureFile();
                if (file_exists($configPath)) {
                    $config = include($configPath);
                    $global = $container['config'];
                    if (is_array($config)) {
                        foreach($config as $key => $value) {
                            $global['app.' . $key] = $value;
                        }
                    }
                    // Override
                    $container->offsetSet('config', $global);
                }
            }

            return $next($container);
        }

        /** @var \Key\Http\Request $request */
        $request = $container['request'];
        $uri = $request->getUri();
        $requestHost = $uri->getHost();
        $appName = $this->checkHost($requestHost, $container);
        $container['appName'] = $appName;

        $appPath = APPS_PATH . DS . $appName . DS . 'config' . DS;

        // load app config file
        $appConfigPath = $appPath . 'app.php';
        if (file_exists($appConfigPath)) {
            $config = include($appConfigPath);
            $global = $container['config'];
            if (is_array($config)) {
                foreach($config as $key => $value) {
                    $global['app.' . $key] = $value;
                }
            }
            // Override
            $container->offsetSet('config', $global);
        }

        // load app route config
        $container['routes'] = function() use($appPath) {
            $routesConfigPath = $appPath . 'routes.php';
            if (file_exists($routesConfigPath)) {
                return include($routesConfigPath);
            } else {
                throw new AppException(sprintf('Routes not set for the app.'));
            }
        };

        $container['pages'] = function() use($appPath) {
            $pagesPath = $appPath . 'pages.php';
            if (file_exists($pagesPath)) {
                return include($pagesPath);
            } else {
                return [];
            }
        };

        // Load app classes
        $loader = new ClassLoader();
        $loader->addPsr4('App\\', APPS_PATH . DS . $appName . DS . 'mc');
        $loader->register();

        return $next($container);
    }

    /**
     * Get micro service name.
     *
     * @param string $requestHost Request home
     * @param Container $container
     * @return string
     */
    protected function checkHost($requestHost, $container)
    {
        $appMap = $container['config']['global.appMap'] ?? '';
        $appName = null;
        if ($appMap) {
            foreach ($appMap as $key => $item) {
                //error_log('[checkHost] key: ' . $key);
                $hosts = explode(',', $key);
                foreach ($hosts as $host) {
                    $host = str_replace('.', '\\.', $host);
                    $host = str_replace('*', '.', $host);
                    //error_log('[checkHost] host: ' . $host);
                    if (preg_match('/' . $host . '/', $requestHost)) {
                        $appName = $item;
                        break;
                    }
                }
                if ($appName) {
                    break;
                }
            }
        } else {
            error_log('App host map not set');
        }

        if (!$appName) {
            if ($container['config']['global.defaultApp']) {
                //error_log(sprintf('[Config.php] No host matched(%s), using defaultApp configure!', $requestHost));
                $appName = $container['config']['global.defaultApp'];
            } else {
                throw new AppException('Host not matched');
            }
        }

        return $appName;
    }
}
