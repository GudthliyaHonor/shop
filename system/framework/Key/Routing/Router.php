<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.2.0
 * @link http://www.keylogic.com
 */
namespace Key\Routing;


use Key\Container;
use Key\Event\Dispatcher;

class Router
{
    protected $dispatcher;

    protected $container;

    public function __construct(Dispatcher $dispatcher, Container $container = null)
    {
        $this->dispatcher = $dispatcher;
        $this->container = $container ?: new Container;
    }
}