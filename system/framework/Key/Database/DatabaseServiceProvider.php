<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */
namespace Key\Database;


use Key\Support\ServiceProvider;
use Pimple\Container;

class DatabaseServiceProvider extends ServiceProvider
{

    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $pimple A container instance
     */
    public function register(Container $pimple)
    {
        $pimple['mongodb'] = function ($container) {
            return MongoManager::getInstance($container);
        };

        $pimple['mysql'] = function ($app) {
            return MySqlManager::getInstance($app);
        };
    }
}