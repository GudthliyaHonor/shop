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


use Key\Http\Headers;
use Key\Http\Request;
use Key\Http\Response;
use Key\Http\Environment;
use Key\Log\LoggerManager;
use Key\Cache\CacheManager;
use \Key\Queue\QueueManager;
use Key\Database\MongoManager;
use Key\Database\MySqlManager;
use Key\Database\DatabaseSelector;
use Pimple\ServiceProviderInterface;
use Composer\Autoload\ClassLoader;

class ServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param \Pimple\Container $pimple A container instance
     */
    public function register(\Pimple\Container $pimple)
    {
        $pimple['env'] = $this->loadEnvironment();
        $pimple['config'] = $this->loadConfigure();

        $pimple['classloader'] = new ClassLoader();

        $pimple['environment'] = $pimple->factory(function() {
            return new Environment($_SERVER);
        });

        if (!isset($pimple['logger'])) {
            $pimple['logger'] = function ($container) {
                $options = [];
                $options['daily'] = $container['config']['global.log'] == 'daily';
                return LoggerManager::getFileInstance('app', $options);
            };
        }

        if (!isset($pimple['dblogger'])) {
            $pimple['dblogger'] = function ($container) {
                return LoggerManager::getMongoInstance($container);
            };
        }

        if (!isset($pimple['request'])) {
            $pimple['request'] = function ($container) {
                return Request::createFromEnvironment($container['environment']);
            };
        }

        if (!isset($pimple['response'])) {
            $pimple['response'] = function ($container) {
                $headers = new Headers(['Content-Type' => env('RESPONSE_DEFAULT_CONTENT_TYPE', 'application/json')]);
                $response = new Response(200, $headers);

                return $response->withProtocolVersion('1.1');
            };
        }

        $pimple['session'] = function ($container) {
            return new Session($container);
        };

        $pimple['session.client'] = function ($container) {
            $name = 'session';
            $config = $container['config']['database.connections']['redis'];
            $host = $config[$name]['host'];
            $password = $config[$name]['password'];
            $port = (int) $config[$name]['port'];
            $database = $config[$name]['database'];
            $timeout = (float) $config[$name]['timeout'];
            $client = new \Key\Cache\Redis($host, $port, $timeout, $password, $config[$name]['prefix'] ? $config[$name]['prefix'] . ':' : null, $database);
            $redis = $client->getRedis();
            $redis->setOption(\Redis::OPT_PREFIX, $config[$name]['prefix'] . ':');
            return $redis;
        };

        $pimple['mongodb'] = function ($container) {
            return MongoManager::getInstance($container);
        };

        $pimple['mongodb_global'] = function($container) {
            return MongoManager::getGlobalInstance($container);
        };

        $pimple['db'] = function($container) {
            return new DatabaseSelector([], $container);
        };

        $pimple['cache'] = function ($app) {
            $config = $app['config'];
            return CacheManager::getInstance($config);
        };

        $pimple['mysql'] = function ($app) {
            return MySqlManager::getInstance($app);
        };

        if (!isset($pimple['rabbitmq'])) {
            $pimple['rabbitmq'] = function ($container) {
                $manager = new QueueManager($container);
                $connector = $manager->getDefaultConnector();
                return $connector;
            };
        }

        $pimple['req_id'] = md5(microtime());
    }

    /**
     * Get base path of the app.
     *
     * @return bool|string
     */
    protected function getBasePath()
    {
        return APPLICATION_PATH;
    }

    protected function getEnvArray()
    {
        $basePath = $this->getBasePath();
        $envFile = $basePath . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($envFile)) {
            return parse_ini_file($envFile);
        }
        else {
            if (file_exists($envFile . '.php')) {
                $envVal = include($envFile . '.php');
                if (is_array($envVal)) {
                    return $envVal;
                }
                elseif (is_string($envVal)) {
                    return parse_ini_string($envVal);
                }
            }
        }
        return null;
    }

    /**
     * Load environment settings.
     *
     * @return array|bool
     */
    protected function loadEnvironment()
    {
        $env = [];
        $arr = $this->getEnvArray();
        if (is_array($arr)) {
            foreach ($arr as $key => $value) {
                $env[$key] = $value;
                //putenv(CURR_APP_ID . $key . '=' . $value); // putenv not support Chinese value
                $_ENV[$key] = $value;
            }
        } else {
            error_log('[WARNING] Invalid env file');
        }
        return $env;
    }

    /**
     * Load configures.
     *
     * @return array
     */
    protected function loadConfigure()
    {
        $basePath = $this->getBasePath();
        //error_log('basepath: ' . $basePath);
        $globalConfigPath = $basePath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR;

        $configure = [];
        $dir = dir($globalConfigPath);
        while (($sDir = $dir->read()) !== false) {
            if ($sDir != '.' && $sDir != '..' && !is_dir($globalConfigPath . $sDir)) {
                $settings = include($globalConfigPath . $sDir);
                $basename = basename($sDir, '.php');
                if (is_array($settings)) {
                    foreach ($settings as $key => $value) {
                        $configure[$basename . '.' . $key] = $value;
                    }
                }
            }
        }

        return $configure;
    }

}
