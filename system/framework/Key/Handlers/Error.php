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
 * Class Error
 * @package Key\Handlers
 */
class Error
{

    /**
     * @var Exception
     */
    protected $exception;

    /**
     * Default content type for output.
     *
     * @var string
     */
    protected $defaultContentType = 'application/json';

    /**
     * Valid handled content types.
     *
     * @var array
     */
    protected $validContentTypes = array(
        'application/json',
        'application/xml',
        'text/xml',
        'text/html'
    );

    protected $statusCode = Constants::SYS_ERROR_DEFAULT;

    /**
     * Error invoke.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param Exception $ex
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Key\Http\RuntimeException
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, Exception $ex)
    {
        $this->exception = $ex;
        if ($this->exception->getCode()) {
            $this->statusCode = $this->exception->getCode();
        }

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

        return $response->withStatus(200)
            ->withHeader('Content-Type', $contentType.';charset=utf-8')
            ->withBody($body);
    }

    /**
     * Get the content type.
     *
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return string
     */
    protected function getContentType(ServerRequestInterface $request, ResponseInterface $response)
    {
        if (!$response->hasHeader('Content-Type')) {
            $contentType = $this->determineContentType($request);
        } else {
            $contentType = $response->getHeader('Content-Type');
            if (is_array($contentType)) {
                $contentType = $contentType[0];
            }
            $contentTypeParts = preg_split('/\s*;\s*/', $contentType);
            if (count($contentTypeParts)) {
                $contentType = $contentTypeParts[0];
            }
        }

        return $contentType;
    }

    /**
     * @param ServerRequestInterface $request
     * @return string
     */
    protected function determineContentType(ServerRequestInterface $request)
    {
        $acceptHeader = $request->getHeaderLine('Accept');
        $selectedContentTypes = array_intersect(explode(',', $acceptHeader), $this->validContentTypes);
        if (count($selectedContentTypes)) {
            return $selectedContentTypes[0];
        }

        return $this->defaultContentType;
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
        $output = [
            'status' => $this->statusCode,
            'msg' => $this->exception->getMessage(),
            'success' => false
        ];
        if (env('DEBUG_TRACE')) {
            $output['exception_trace'] = $this->exception ? $this->exception->getTrace() : debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        }
        return json_encode($output);
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
        return '<root><status>'.$this->statusCode.'</status><msg>'.$this->exception->getMessage().'</msg></root>';
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
        //$uri = $request->getUri()->withPath('')->withQuery('')->withFragment('');
        //$homeUrl = (string)($uri->getScheme() .'://'. $uri->getHost());
        return <<<END
<html>
    <head>
        <title>Error</title>
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
        <h1>Error occur!</h1>
        <p>Code: {$this->statusCode}</p>
        <p>{$this->exception->getMessage()}</p>
    </body>
</html>
END;
    }
}