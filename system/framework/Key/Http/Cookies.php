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


use InvalidArgumentException;

/**
 * Http cookies
 *
 * @package Key\Http
 * @author Guanghui Li <liguanghui2006@163.com>
 */
class Cookies
{
    /**
     * The cookies form HTTP request.
     *
     * @var array
     */
    protected $requestCookies;

    /**
     * The cookies for HTTP response
     *
     * @var array
     */
    protected $responseCookies;

    /**
     * Default cookie properties
     *
     * @var array
     */
    protected $deafults = array(
        'value' => '',
        'domain' => null,
        'path' => null,
        'expires' => null,
        'secure' => false,
        'httponly' => false
    );

    /**
     * Create the new cookies instance.
     *
     * @param array $requestCookies
     */
    public function __construct($requestCookies = array())
    {
        $this->requestCookies = $requestCookies;
    }

    /**
     * Get request cookie.
     *
     * @param string $name The cookie name
     * @param null|mixed $default The cookie default value
     * @return mixed The cookie value if present, else default.
     */
    public function get($name, $default = null)
    {
        return isset($this->requestCookies[$name]) ? $this->requestCookies[$name] : $default;
    }

    /**
     * Set response cookie.
     *
     * @param string $name The cookie name
     * @param string|array $value The cookie value or properties
     */
    public function set($name, $value)
    {
        if (!is_array($value)) {
            $value = array(
                'value' => (string) $value
            );
        }

        $this->responseCookies[$name] = array_replace($this->deafults, $value);
    }

    /**
     * Convert to `Set-Cookie` headers.
     *
     * @return array
     */
    public function toHeaders()
    {
        $headers = array();
        foreach($this->responseCookies as $name => $properties) {
            $headers[] = $this->toHeader($name, $properties);
        }

        return $headers;
    }

    /**
     * Convert `Set-Cookie` header.
     *
     * @param string $name The cookie name
     * @param array $properties The cookie properties
     *
     * @return string
     */
    protected function toHeader($name, $properties)
    {
        $result = urlencode($name).'='.urlencode($properties['value']);

        if (isset($properties['domain'])) {
            $result .= '; domain='.$properties['domain'];
        }

        if (isset($properties['path'])) {
            $result .= '; path='.$properties['path'];
        }

        if (isset($properties['expires'])) {
            if (is_string($properties['expires'])) {
                $timestamp = strtotime($properties['expires']);
            } else {
                $timestamp = (int) $properties['expires'];
            }

            if ($timestamp != 0) {
                $result .= '; expires='.gmdate('D, d-M-Y H:i:s e', $timestamp);
            }
        }

        if (isset($properties['secure']) && $properties['secure']) {
            $result .= '; secure';
        }

        if (isset($properties['httponly']) && $properties['httponly']) {
            $result .= '; HttpOnly';
        }

        return $result;
    }

    /**
     * Parse HTTP request `Cookie:` header and extract
     * into a PHP associative array.
     *
     * @param string $header The raw HTTP request `Cookie:` header
     *
     * @return array Associative array of cookie names and values
     *
     * @throws InvalidArgumentException If the cookie data cannot be parsed.
     */
    public static function parseHeader($header)
    {
        if (is_array($header) === true) {
            $header = isset($header[0]) ? $header[0] : '';
        }

        if (is_string($header) === false) {
            throw new InvalidArgumentException('Cannot parse Cookie data. Header value must be a string.');
        }

        $header = rtrim($header, "\r\n");
        $pieces = preg_split('@\s*[;,]\s*@', $header);
        $cookies = array();

        foreach ($pieces as $cookie) {
            $cookie = explode('=', $cookie, 2);

            if (count($cookie) === 2) {
                $key = urldecode($cookie[0]);
                $value = urldecode($cookie[1]);

                if (!isset($cookies[$key])) {
                    $cookies[$key] = $value;
                }
            }
        }

        return $cookies;
    }
}