<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2021 keylogic.com
 * @version 1.0.0
 * @link http://www.keylogic.com
 */

namespace Key\Foundation;


abstract class ModuleBootstrap
{
    protected $container;

    const MODULE_NAME = '__default__';

    public function __construct($container)
    {
        $this->container = $container;
        $this->container['appName'] = static::MODULE_NAME;
    }

    /**
     * The method action before bootstrap.
     *
     * @return void
     */
    abstract public function beforeBootstrap();

    /**
     * Load module classes.
     *
     * @return void
     */
    abstract public function loadClasses();

    /**
     * Load module configure.
     *
     * @return void
     */
    abstract public function loadConfigure();

    /**
     * Load module routes.
     *
     * @return array The route files
     */
    abstract public function loadRoutes();

    /**
     * Method action after bootstrap.
     *
     * @return void
     */
    abstract public function afterBootstrap();
}