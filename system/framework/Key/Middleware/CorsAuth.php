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
use Psr\Http\Message\ServerRequestInterface;
use Key\Abstracts\Middleware;
use Key\Exception\AppException;
use Key\Http\Environment;
use Key\Constants;

/**
 * Middleware Auth checks the permission to access the app,
 * Such as AJAX CORS(Cross-Origin Resource Sharing).
 *
 * @package Key\Middleware
 * @author Guanghui Li <liguanghui2006@163.com>
 */
class CorsAuth extends Middleware
{

    /**
     * Auth invoke.
     *
     * @param \Pimple\Container $container
     * @param Closure $next
     * @return \Psr\Http\Message\MessageInterface|ResponseInterface
     * @throws AppException
     */
    public function __invoke(Container $container, Closure $next)
    {
        // Check CORS(Cross-Origin Resource Sharing) and add allow headers

        $env = $container['environment'];
        $request = $container['request'];
        $response = $container['response'];

        $origin = $env->get('HTTP_ORIGIN');
        $uri = $request->getUri();

        $referer = $env->get('HTTP_REFERER');
        if (!$this->checkReferer($referer, $container)) {
            throw new AppException('Invalid Referer: '. $referer);
        }

        $method = $request->getMethod();
        $uri = $request->getUri();
        $port = $uri->getPort();
        $host = $uri->getScheme().'://'.$uri->getHost() . ($port != 80 && $port != 443 ? ':' . $port : '');
        // Origin is the same with host, do not need CORS configure
        if ($host != $origin) {
            if (method_exists($response, 'withHeader')) {
                // Check the configure to allow origin sites for AJAX-CORS
                if ($site = $this->checkValidCORSSites($origin, $container)) {

                    $allowMethods = $container['config']['cors.allowMethods'];
                    $allowMethods = array_map('trim', explode(',', $allowMethods));

                    if (!in_array($method, $allowMethods)) {
                        throw new AppException('Invalid CORS method: ' . $method, Constants::SYS_REQ_INVALID);
                    }

                    $response = $response->withHeader('Access-Control-Allow-Origin', $site ?: '*')
                        ->withHeader('Access-Control-Allow-Credentials', 'true')
                        ->withHeader('Access-Control-Allow-Methods', $method)
                        ->withHeader('Access-Control-Max-Age', $container['config']['cors.maxAge'])
                        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With,Content-Type,Accept' .
                            ($container['config']['cors.allowExtraHeaders'] ? ',' . $container['config']['cors.allowExtraHeaders'] : ''));

                    if ($method == 'OPTIONS') {
                        $response->withHeader('Access-Control-Allow-Methods', implode(',', $allowMethods));
                        $response->withHeader('Content-Length', 0);
                        return $response;
                    } 
                    
                } else {
                    error_log('Invalid CORS site: '.$origin);
                    // throw new AppException('Invalid CORS site: '.$origin);
                    return $response->withStatus(403);
                }
            }
        }

        // when set next_stop tag, skip left actions
        if ($container->offsetExists('next_stop')) {
            return $response;
        }

        return $next($container);
    }


    protected function checkReferer($referer, $container)
    {
        $strict = $container['config']['global.strict'];
        if ($strict) {
            $allowEmpty = $container['config']['cors.allowRefererEmpty'];
            if (empty($referer) && $allowEmpty) {
                return true;
            }

            $allowRefererHosts = $container['config']['cors.allowRefererHosts'];
            if ($allowRefererHosts) {
                if ($allowRefererHosts == '*') {
                    return true;
                }
                $allowRefererHosts = explode(',', $allowRefererHosts);
                $refererHost = parse_url($referer, PHP_URL_HOST);
                if (in_array($refererHost, $allowRefererHosts)) {
                    return true;
                } else {
                    foreach ($allowRefererHosts as $allowRefererHost) {
                        $regex = str_replace('.', '\.', $allowRefererHost);
                        $regex = str_replace('*', '.+', $regex);
                        if (preg_match('/^' . $regex . '$/', $refererHost)) {
                            return true;
                        }
                    }

                    return false;
                }
            }

        }
        return true;
    }

    /**
     * Check valid CORS sites.
     *
     * @param string $origin Origin site
     *
     * @return null|string
     * @throws AppException
     */
    public function checkValidCORSSites($origin, $container)
    {
        // error_log('[checkValidCORSSites] origin: ' . $origin);
        $strict = $container['config']['global.strict'];
        if (!$strict) {
            return '*';
        }

        // check empty origin configure
        if (!$origin) {
            if ($container['config']['cors.allowOriginEmpty']) {
                return '*';
            }
        }

        $sites = array();
        if ($container['config']['cors.allowCORSSites']) {
            if (is_string($container['config']['cors.allowCORSSites'])) {
                if ($container['config']['cors.allowCORSSites'] == '*') {
                    return $origin;
                } else {
                    $sites = explode(',', $container['config']['cors.allowCORSSites']);
                }
            } elseif (is_array($container['config']['cors.allowCORSSites'])) {
                $sites = $container['config']['cors.allowCORSSites'];
            }
        } else {
            return $origin;
        }

        if (in_array($origin, $sites)) {
            return $origin;
        } else {
            foreach ($sites as $site) {
                $regex = str_replace('.', '\.', $site);
                $regex = str_replace('*', '.+', $regex);
                $regex = str_replace('/', '\/', $regex);
                // error_log('[checkValidCORSSites] regex: ' . $regex . ' -- origin: ' . $origin);
                if (!startsWith($site, 'http://') && !startsWith($site, 'https://')) {
                    if (preg_match('/^http[s]?:\/\/' . $regex . '$/', $origin)) {
                        return $origin;
                    }
                }
                else {
                    if (preg_match('/^' . $regex . '$/', $origin)) {
                        return $origin;
                    }
                }
            }
        }

        return null;
    }
}
