<?php

namespace Key\Util;

/**
 * 使用PHP Socket 编程模拟Http post和get请求
 * @link https://www.cnblogs.com/XACOOL/p/5619721.html
 * @author koma
 */
class SocketHttp
{
    private $app;
    private $sp = "\r\n"; //这里必须要写成双引号
    private $protocol = 'HTTP/1.1';
    private $requestLine = "";
    private $requestHeader = "";
    private $requestBody = "";
    private $requestInfo = "";
    private $fp = null;
    private $urlinfo = null;
    private $header = array();
    private $body = "";
    private $responseInfo = "";
    private $timeout = 0;
    private static $http = null; //Http对象单例
    private function __construct()
    {}

    /**
     * Create the instance.
     * @return SocketHttp
     */
    public static function create()
    {
        if (self::$http === null) {
            self::$http = new SocketHttp();
        }
        return self::$http;
    }
    /**
     * Init the props.
     * @param string $url
     * @param float $timeout
     * @return static
     */
    public function init($app, $url, $timeout = 0)
    {
        $this->app = $app;
        $this->timeout = $timeout;
        $this->parseUrl($url);
        $this->header['Host'] = $this->urlinfo['host'];
        return $this;
    }
    public function get($header = array(), &$errno = null, &$errmsg = null)
    {
        $this->header = array_merge($this->header, $header);
        return $this->request('GET', $errno, $errmsg);
    }
    public function post($header = array(), $body = array(), &$errno = null, &$errmsg = null)
    {
        $this->header = array_merge($this->header, $header);
        if (!empty($body)) {
            $this->body = http_build_query($body);
            $this->header['Content-Type'] = 'application/x-www-form-urlencoded';
            $this->header['Content-Length'] = strlen($this->body);
        }
        return $this->request('POST', $errno, $errmsg);
    }
    private function request($method, &$errno = null, &$errmsg = null)
    {
        error_log('request start: ' . ($start = microtime(true)));
        $header = "";
        $this->requestLine = $method . ' ' . $this->urlinfo['path'] . '?' . $this->urlinfo['query'] . ' ' . $this->protocol;
        foreach ($this->header as $key => $value) {
            $header .= $header == "" ? $key . ':' . $value : $this->sp . $key . ':' . $value;
        }
        $this->requestHeader = $header . $this->sp . $this->sp;
        $this->requestInfo = $this->requestLine . $this->sp . $this->requestHeader;
        if ($this->body != "") {
            $this->requestInfo .= $this->body;
        }
        /*
         * 注意：这里的fsockopen中的url参数形式为"www.xxx.com"
         * 不能够带"http://"这种
         */
        $port = isset($this->urlinfo['port']) ? $this->urlinfo['port'] : '80';
        $this->fp = @fsockopen($this->urlinfo['host'], $port, $errno, $errmsg, $this->timeout);
        if (!$this->fp) {
            error_log('error: ' . $errno . ' ' . $errmsg);
            return false;
        }
        stream_set_blocking($this->fp, false);
        if (fwrite($this->fp, $this->requestInfo)) {
            $str = "";
            while (!feof($this->fp)) {
                $str .= fread($this->fp, 1024);
            }
            $this->responseInfo = $str;
        }
        fclose($this->fp);
        error_log('request end: ' . ($end = microtime(true)));
        error_log('request elspsed: ' . ($end - $start));
        return $this->responseInfo;
    }
    private function parseUrl($url)
    {
        if ($uri = env('URI_REST')) {
            $uriInfo = parse_url($uri);
            $port = $uriInfo['port'];
            // error_log('callViewLog uriInfo: ' . json_encode($uriInfo));
        }
        else {
            /** @var \Key\Http\Uri $uri */
            $uri = $this->app['request']->getUri();
            $scheme = $uri->getScheme();
            $host = $uri->getHost();
            $port = $uri->getPort() ?: 80;
            $uri = "${scheme}://${host}" . ($port == 80 ? '' : ':' . $port);
        }

        if (startsWith($url, '/')) {
            $url = $uri . env('API_PREFIX', '/api/v1') . $url;
        }
        error_log('SocketHttp url: ' . $url);
        $this->urlinfo = parse_url($url);
    }
}

// // $url = "http://news.163.com/14/1102/01/AA0PFA7Q00014AED.html";
// $url = "http://localhost/httppro/post.php"; $http = SocketHttp::create()->init($url);
// /* 发送get请求
// echo $http->get(array(
//     'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.1916.153 Safari/537.36',
// ));
// */
//  /* 发送post请求 */
//  echo $http->post(array(
//         'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.1916.153 Safari/537.36',
// ), array('username'=>'发一个中文', 'age'=>22));
