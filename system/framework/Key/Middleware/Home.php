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
use Key\Constants;

/**
 * Class Home
 * @package Key\Middleware
 */
class Home extends Middleware
{

    /**
     * Home middleware.
     *
     * @param Container $container
     * @param Closure $next
     * @return \Key\Http\Response|mixed
     * @throws AppException
     */
    public function __invoke(Container $container, Closure $next)
    {
        $request = $container['request'];
        $response = $container['response'];

        $uri = $request->getUri();
        $path = $uri->getPath();

        if ($path === '' || $path === '/') {
            // @see \Key\Middleware\Config
            if (isset($container['config']['global.defaultPages']) && is_array($container['config']['global.defaultPages'])) {

                $homeConf = $container['config']['global.defaultPages']['home'];
                $loginConf = $container['config']['global.defaultPages']['login'];

                $is_logged = isset($_SESSION) && isset($_SESSION[Constants::SESSION_USR_KEY]) && $_SESSION[Constants::SESSION_USR_KEY];

                $redirectUri = null;
                if ($is_logged) {
                    $redirectUri = $homeConf;
                } else {
                    $redirectUri = $loginConf;
                }

                if (!$redirectUri) $redirectUri = $homeConf;

                if ($redirectUri) {
                    // goto the url
                    if (method_exists($response, 'withRedirect')) {
                        /** @var \Key\Http\Response $response */
                        $response = $response->withRedirect($redirectUri);
                    } else {
                        $response = $response->withHeader('Location', $redirectUri)->withStatus(302);
                    }

                    return $response;
                } else {
                    throw new AppException('Invalid redirect uri');
                }

            } else {
                throw new AppException('No defaultPages configure found');
            }
        }

        return $next($container);
    }

}