<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.2.0
 * @link http://www.keylogic.com
 */

namespace Key\Session;


use Pimple\Container;
use Pimple\ServiceProviderInterface;

class SessionServiceProvider implements ServiceProviderInterface
{

    public function register(Container $pimple)
    {
        return function () use ($pimple) {

        };
    }
}