<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.2.0
 * @link http://www.keylogic.com
 */
namespace Key\Http\Express;


use Key\Http\Environment;
use Key\Http\Uri;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class Configure implements ServiceProviderInterface
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
        // TODO: Implement register() method.

        $env = new Environment($_SERVER);
        $uri = Uri::createFromEnvironment($env);

    }
}