<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.2.0
 * @link http://www.keylogic.com
 */
namespace Key\Http\Middleware;


use \Closure;
use Key\Abstracts\Middleware;

class Demo extends Middleware
{

    /**
     * @param Container $container
     * @param Closure $next
     * @return mixed
     */
    public function __invoke(Container $container, Closure $next)
    {
        error_log('Demo middleware called');
        return $next($container);
    }

}