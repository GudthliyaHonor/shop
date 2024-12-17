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
use Pimple\Container;
use Psr\Http\Message\ResponseInterface;
use Key\Abstracts\Middleware;
use Key\Exception\ServiceUnavailableException;

/**
 * Class Maintenance
 * @package Key\Middleware
 */
class Maintenance extends Middleware
{

    /**
     * Maintenance Middleware.
     *
     * @param Container $container
     * @param Closure $next
     * @return ResponseInterface
     * @throws ServiceUnavailableException
     */
    public function __invoke(Container $container, Closure $next)
    {
        $envState = strtolower(env('APP_ENV', ''));
        if ($envState == 'maintenance') {
            $code = env('ENV_MAINTENANCE_CODE', -503);
            $msg = env('ENV_MAINTENANCE_MESSAGE');
            $startTime = env('ENV_MAINTENANCE_START_TIME');
            $endTime = env('ENV_MAINTENANCE_END_TIME');
            $ignoreHosts = env('ENV_MAINTENANCE_IGNORE_HOSTS');

            if ($ignoreHosts) {
                $ignoreHosts = explode(',', $ignoreHosts);
                $env = $container['environment'];
                $origin = $env->get('HTTP_ORIGIN');
                error_log('[Maintenance] origin: ' . $origin);
                foreach ($ignoreHosts as $host) {
                    if (strcasecmp($host, $origin) == 0) {
                        error_log('[Maintenance] match ignore!');
                        return $next($container);
                    }
                }
            }

            

            // next_stop state
            $state = 0;
            if ($code && $msg) {

                if ($startTime) {
                    $now = time();
                    $startTime = strtotime($startTime);
                    $endTime = strtotime($endTime);
                    if ($startTime < $now) {
                        if ($endTime > $now || !$endTime) {
                            $state = 1;
                        }
                    }
                }
                else {
                    $state = 1;
                }

                if ($state) {
                    /** @var \Key\Http\Response $response */
                    $response = $container['response'];
                    $response->withJson([
                        'status' => (int) $code,
                        'msg' => $msg,
                    ]);

                    $container['next_stop'] = 1;
                }
            }
            else {
                throw new ServiceUnavailableException($container['request'], $container['response']);
            }
        }
        return $next($container);
    }

}