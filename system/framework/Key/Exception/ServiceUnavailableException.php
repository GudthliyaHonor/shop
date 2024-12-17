<?php
/**
 * Created by PhpStorm.
 * User: roy
 * Date: 2016/7/6
 * Time: 9:50
 */

namespace Key\Exception;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ServiceUnavailableException extends \Exception
{
    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     */
    public function __construct(ServerRequestInterface $request, ResponseInterface $response)
    {
        $this->request = $request;
        $this->response = $response;
        parent::__construct('503 Service Unavailable', 0, null);
    }
}