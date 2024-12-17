<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */
namespace Key\Handlers;


use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Key\Constants;
use Key\Http\Body;

/**
 * Class NotFound
 * @package Key\Handlers
 */
class NotFound extends Error
{

    protected $statusCode = Constants::SYS_ROUTE_NOT_FOUND;

    /**
     * NotFound invoke.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param Exception $ex
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, Exception $ex)
    {
        $contentType = $this->getContentType($request, $response);
        switch ($contentType) {
            case 'application/json':
                $output = $this->renderJsonOutput($request, $response);
                break;
            case 'text/xml':
            case 'application/xml':
                $output = $this->renderXmlOutput($request, $response);
                break;
            case 'text/html':
                $output = $this->renderHtmlOutput($request, $response);
                break;
        }

        $body = new Body(fopen('php://temp', 'r+'));
        $body->write($output);

        return $response->withStatus(404)
                        ->withHeader('Content-Type', $contentType)
                        ->withBody($body);
    }

    /**
     * Return a response for application/json content not found.
     *
     * @param ServerRequestInterface $request The most recent Request object.
     * @param ResponseInterface $response The most recent Response object.
     * @return string
     */
    protected function renderJsonOutput(ServerRequestInterface $request, ResponseInterface $response)
    {
        return '{"status": '.$this->statusCode.', "msg": "Not found"}';
    }

    /**
     * Return a response for xml content not found.
     *
     * @param ServerRequestInterface $request The most recent Request object.
     * @param ResponseInterface $response The most recent Response object.
     * @return string
     */
    protected function renderXmlOutput(ServerRequestInterface $request, ResponseInterface $response)
    {
        return '<root><status>'.$this->statusCode.'</status><msg>Not found</msg></root>';
    }

    /**
     * Return a response for text/html content not found.
     *
     * @param ServerRequestInterface $request The most recent Request object.
     * @param ResponseInterface $response The most recent Response object.
     * @return string
     */
    protected function renderHtmlOutput(ServerRequestInterface $request, ResponseInterface $response)
    {
        $errorPage = env('PAGE_ERROR_404');
        if ($errorPage) {
            $response->withStatus(404)->withHeader('Location', $errorPage);
        } else {
            $uri = $request->getUri()->withPath('')->withQuery('')->withFragment('');
            $homeUrl = (string)($uri->getScheme() .'://'. $uri->getHost());
            return <<<END
<html>
    <head>
        <title>Page Not Found</title>
        <style>
            body{
                margin:0;
                padding:30px;
                font:12px/1.5 Helvetica,Arial,Verdana,sans-serif;
            }
            h1{
                margin:0;
                font-size:48px;
                font-weight:normal;
                line-height:48px;
            }
            strong{
                display:inline-block;
                width:65px;
            }
        </style>
    </head>
    <body>
        <h1>Page Not Found</h1>
        <p>
            The page you are looking for could not be found. Check the address bar
            to ensure your URL is spelled correctly. If all else fails, you can
            visit our home page at the link below.
        </p>
        <a href='$homeUrl'>Visit the Home Page</a>
    </body>
</html>
END;
        }

    }
}