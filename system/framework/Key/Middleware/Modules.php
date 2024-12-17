<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2021 keylogic.com
 * @version 1.0.0
 * @link http://www.keylogic.com
 */

namespace Key\Middleware;


use Closure;
use Composer\Autoload\ClassLoader;
use Pimple\Container;
use Key\Abstracts\Middleware;
use Key\Exception\AppException;

/**
 * Middleware Modules
 *
 * @package Key\Middleware
 * @author liguanghui <liguanghui@keylogic.com.cn>
 */
class Modules extends Middleware
{
    /**
     * @param Container $container
     * @param Closure $next
     * @return mixed
     * @throws AppException
     */
    public function __invoke(Container $container, Closure $next)
    {
        $specifiedModules = $container->offsetExists('__modules__') ? $container['__modules__'] : [];

        $modules = [];
        // check bootstrap in apps
        $handle = opendir(APPS_PATH);
        if ($handle) {
            while (($fl = readdir($handle)) !== false) {
                // Skip some files
                if ($fl == '.' || $fl == '..' || startsWith($fl, '__') ) {
                    continue;
                }

                $bootstrapFile = APPS_PATH . DS . $fl . DS . 'bootstrap.php';
                
                if (file_exists($bootstrapFile)) {
                    /** @var \Key\Foundation\Module $module */
                    $module = require($bootstrapFile);

                    if ($specifiedModules && !in_array($module->getName(), $specifiedModules)) continue;

                    if ($module && $module instanceof \Key\Foundation\Module) {
                        // error_log('module name: ' . $module->getName());
                        // error_log('module route path: ' . $module->getRoutePath());
                        $modules[$module->getName()] = $module;

                        // Pre-load low priority module classes
                        if ($module->getPriority() <= 0 || in_array($module->getName(), $specifiedModules)) {
                            // load module classes
                            // error_log('load app module: ' . $module->getName());
                            // $container['classloader']->addPsr4($module->getNs(), $module->getClassPath());
                            $module->registerClasses($container, true, true);
                        }

                    }
                } 
                
            }
        }

        $container['classloader']->register();

        /** @var \Key\Foundation\Module $a */
        /** @var \Key\Foundation\Module $b */
        uasort($modules, function($a, $b) {
            $pr1 = $a->getPriority();
            $pr2 = $b->getPriority();
            if ($pr1 == $pr2) return 0;
            return ($pr1 < $pr2) ? -1 : 1;
        });
        $container['modules'] = $modules;
        return $next($container);
    }
}