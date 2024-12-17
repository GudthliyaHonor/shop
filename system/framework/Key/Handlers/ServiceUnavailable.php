<?php
/**
 * Created by PhpStorm.
 * User: roy
 * Date: 2016/7/6
 * Time: 9:48
 */

namespace Key\Handlers;


use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class ServiceUnavailable handler http code 503.
 *
 * @package Key\Handlers
 */
class ServiceUnavailable extends Error
{

    /**
     * ServiceUnavailable invoke.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param Exception $ex
     * @return \Psr\Http\Message\MessageInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, Exception $ex)
    {
        $errorPage = env('PAGE_ERROR_503');
        if ($errorPage) {
            return $response->withHeader('Location', $errorPage);
        } else {
            return $response->withStatus(503)
                ->withHeader('Content-Type', 'text/html');
        }

    }

}