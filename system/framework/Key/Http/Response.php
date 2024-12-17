<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */
namespace Key\Http;


use RuntimeException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Response
 * @package Key\Http
 * @author Guanghui Li <liguanghui2006@163.com>
 */
class Response extends Message implements ResponseInterface
{

    protected $status = 200;

    protected $reasonPhrase = '';

    /**
     * Status codes and reason phrases.
     * @see https://en.wikipedia.org/wiki/List_of_HTTP_status_codes
     *
     * @var array
     */
    protected static $messages = array(
        //Informational 1xx
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        //Successful 2xx
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        208 => 'Already Reported',
        226 => 'IM Used',
        //Redirection 3xx
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => '(Unused)',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        //Client Error 4xx
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        //Server Error 5xx
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    );

    public function __construct($status = 200, $headers = null, $body = null)
    {
        $this->status = $this->filterStatus($status);
        $this->headers = $headers ? $headers : new Headers(array());
        $this->body = $body ? $body : new Body(fopen('php://temp', 'r+'));
    }

    /**
     * Filter HTTP status code.
     *
     * @param  int $status HTTP status code.
     * @return int
     * @throws \InvalidArgumentException If an invalid HTTP status code is provided.
     */
    protected function filterStatus($status)
    {
        if (!is_integer($status) || $status<100 || $status>599) {
            throw new InvalidArgumentException('Invalid HTTP status code');
        }

        return $status;
    }

    /**
     * Json.
     *
     * Note: This method is not part of the PSR-7 standard.
     *
     * This method prepares the response object to return an HTTP Json
     * response to the client.
     *
     * @param  mixed  $data   The data
     * @param  int    $status The HTTP status code.
     * @param  int    $encodingOptions Json encoding options
     * @throws \RuntimeException
     * @return self
     */
    public function withJson($data, $status = 200, $encodingOptions = 0)
    {
        $body = $this->getBody();
        $body->rewind();
        $body->write($json = json_encode($data, $encodingOptions));

        // Ensure that the json encoding passed successfully
        if ($json === false) {
            throw new RuntimeException(json_last_error_msg(), json_last_error());
        }

        return $this->withStatus($status)->withHeader('Content-Type', 'application/json;charset=utf-8');
    }

    /**
     * Gets the response status code.
     *
     * The status code is a 3-digit integer result code of the server's attempt
     * to understand and satisfy the request.
     *
     * @return int Status code.
     */
    public function getStatusCode()
    {
        return $this->status;
    }

    /**
     * Return an instance with the specified status code and, optionally, reason phrase.
     *
     * If no reason phrase is specified, implementations MAY choose to default
     * to the RFC 7231 or IANA recommended reason phrase for the response's
     * status code.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated status and reason phrase.
     *
     * @link http://tools.ietf.org/html/rfc7231#section-6
     * @link http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     * @param int $code The 3-digit integer result code to set.
     * @param string $reasonPhrase The reason phrase to use with the
     *     provided status code; if none is provided, implementations MAY
     *     use the defaults as suggested in the HTTP specification.
     * @return self
     * @throws \InvalidArgumentException For invalid status code arguments.
     */
    public function withStatus($code, $reasonPhrase = '')
    {
        $clone = clone $this;
        $clone->status = $code;

        if ($reasonPhrase === '' && isset(static::$messages[$code])) {
            $reasonPhrase = static::$messages[$code];
        }

        if ($reasonPhrase === '') {
            throw new InvalidArgumentException('ReasonPhrase must be supplied for this code');
        }

        $this->reasonPhrase = $reasonPhrase;

        return $clone;
    }

    /**
     * Gets the response reason phrase associated with the status code.
     *
     * Because a reason phrase is not a required element in a response
     * status line, the reason phrase value MAY be null. Implementations MAY
     * choose to return the default RFC 7231 recommended reason phrase (or those
     * listed in the IANA HTTP Status Code Registry) for the response's
     * status code.
     *
     * @link http://tools.ietf.org/html/rfc7231#section-6
     * @link http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     * @return string Reason phrase; must return an empty string if none present.
     */
    public function getReasonPhrase()
    {
        if ($this->reasonPhrase) {
            return $this->reasonPhrase;
        }
        if (isset(static::$messages[$this->status])) {
            return static::$messages[$this->status];
        }
        return '';
    }


    /**
     * XML.
     *
     * @param string|array $data
     * @param int $status
     * @param string $rootNodeName
     * @return \Psr\Http\Message\MessageInterface|Message|Response
     * @throws RuntimeException
     */
    public function withXml($data, $status = 200, $rootNodeName = 'root')
    {
        $xml = static::toXml($data, $rootNodeName);

        $body = $this->body;
        $body->rewind();
        $body->write($xml);

        return $this->withStatus($status)->withHeader('Content-Type', 'text/xml;charset=utf-8');
    }

    /**
     * Make the valid xml string from the data.
     *
     * @param string|array $data
     * @param string $rootNodeName
     * @param SimpleXMLElement $xml
     * @return string
     */
    protected static function toXml($data, $rootNodeName = 'root', $xml = null) {
        // turn off compatibility mode as simple xml throws a wobbly if you don't.
        if (ini_get('zend.ze1_compatibility_mode') == 1)
        {
            ini_set ('zend.ze1_compatibility_mode', 0);
        }

        if ($xml == null)
        {
            $xml = simplexml_load_string("<?xml version='1.0' encoding='utf-8'?><$rootNodeName />");
        }

        // loop through the data passed in.
        foreach($data as $key => $value)
        {
            // no numeric keys in our xml please!
            if (is_numeric($key))
            {
                // make string key...
                $key = "unknownNode_". (string) $key;
            }

            // replace anything not alpha numeric
            $key = preg_replace('/[^a-z]/i', '', $key);

            // if there is another array found recrusively call this function
            if (is_array($value))
            {
                $node = $xml->addChild($key);
                // recrusive call.
                static::toXml($value, $rootNodeName, $node);
            }
            else
            {
                // add single node.
                $value = htmlentities($value);
                $xml->addChild($key,$value);
            }

        }
        // pass back as string. or simple xml object if you want!
        return $xml->asXML();
    }

    /**
     * Html.
     *
     * @param string $value
     * @param int $status
     * @return \Psr\Http\Message\MessageInterface|Message|Response
     */
    public function withHtml($value, $status = 200)
    {
        if (is_object($value) && in_array("__toString", get_class_methods($value))) {
            $value = strval($value->__toString());
        } else {
            $value = strval($value);
        }

        $body = $this->body;
        $body->rewind();
        $body->write($value);

        return $this->withStatus($status)->withHeader('Content-Type', 'text/html;charset=utf-8');
    }

    /**
     * Redirect response.
     *
     * @param string $url The redirect destination
     * @param int|null $status The redirect HTTP status code
     * @return Response
     */
    public function withRedirect($url, $status = null)
    {
        /** @var \Key\Http\Response $response */
        $response = $this->withHeader('Location', (string) $url);

        if (is_null($status) && $this->getStatusCode() === 200) {
            $status = 302;
        }

        if (!is_null($status)) {
            $response = $response->withStatus($status);
        }

        return $response;
    }

    /**
     * Is the response a redirect?
     *
     * @return bool
     */
    public function isRedirect()
    {
        return in_array($this->getStatusCode(), array(301, 302, 303, 307));
    }
}