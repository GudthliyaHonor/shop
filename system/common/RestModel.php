<?php
/**
 * Rest Model for remote API.
 */

namespace App\Common;

use Key\Exception\AppException;
use Pimple\Container;


class RestModel
{

    static public $sessionToken = null;

    const CURL_TIMEOUT = 5;

    const REQ_GET = 'GET';
    const REQ_POST = 'POST';
    const REQ_PUT = 'PUT';
    const REQ_DEL = 'DELETE';

    protected static $restStatus = Constants::SYS_ERROR_DEFAULT;

    protected $app;

    public function __construct(Container $container)
    {
        $this->app = $container;
    }

    protected function generateMicroServiceHeaders($service, $source = null)
    {
        if (!$source) {
            $env = $this->app['environment'];
            /** @var \Key\Http\Request $request */
            $request = $this->app['request'];
            $uri = $request->getUri();
            $host = $uri->getHost();

            // If exists, use original source
            if ($env->get('HTTP_TALENTYUN_SOURCE') && $env->get('HTTP_TALENTYUN_SOURCE') == 'admin') {
                $source = 'admin';
            } else {
                $map = $this->app['config']['global.appMap'];
                if (isset($map[$host])) {
                    $source = $map[$host];
                }
            }
        }

        $appKeys = $this->app['config']['global.appKeys'];

        if (isset($appKeys[$service]) && isset($appKeys[$source])) {

            $salt = uniqid('TY') . '.' . time();

            return array(
                'Talentyun-Service: ' . $service,
                'Talentyun-Source: ' . $source,
                'Talentyun-Salt: ' . $salt,
                'Talentyun-Token: ' . sha1($appKeys[$service] . $appKeys[$source] . $salt)
            );
        }

        return false;
    }

    protected  function getTargetServiceFromUrl($url)
    {
        $pieces = parse_url($url);
        $host = $pieces['scheme'] . '://' . $pieces['host'] . (isset($pieces['port']) ? ':' . $pieces['port'] : '');

        $map = $this->app['config']['global.appMap'];
        if (isset($map[$host])) {
            return $map[$host];
        }

        return null;
    }

    public function getDataFromUrl($url, $params = array(), $method = self::REQ_GET)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::CURL_TIMEOUT);

//        Tools::log('[RestModel] getDataFromUrl >>> url: ' . $url);
        $service = $this->getTargetServiceFromUrl($url);
        $headers = $this->generateMicroServiceHeaders($service);
        if (!$headers) {
            $headers = [];
        }
//        Tools::log('[RestModel] getDataFromUrl headers >>> ' . var_export($headers,true));

        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            if (version_compare(phpversion(), '5.6') >= 0) {
                curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        } else {
            $headers[] = 'Content-Type: application/json; charset=utf-8';
            $pieces = parse_url($url);
            $query = '';
            if ($params) {
                if (isset($pieces['query']) && ($query = $pieces['query'])) {
                    $query .= '&' . static::encodeArrayParams($params);
                } else {
                    $query .= static::encodeArrayParams($params);
                }
            }
            $url = $pieces['scheme'] . '://' . $pieces['host'] . (isset($pieces['port']) ? ':' . $pieces['port'] : '')
                . (isset($pieces['path']) ? $pieces['path'] : '')
                . ($query ? '?' . $query : '');
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $ssl = substr($url, 0, 8) == 'https://' ? true : false;
        if ($ssl) {
            // TODO: we should verify the ssl cert...
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        error_log('[getDataFromUrl] url: ' . $url);

        curl_setopt($ch, CURLOPT_URL, $url);
        $resource = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

//        Tools::log(sprintf('[RestModel] %s returned: %s', $url, $resource));
        if ($httpcode >= 200 && $httpcode < 300) {
            return $resource;
        } else {
            throw new AppException(sprintf('[RestModel]  Code %s', $httpcode));
        }

        return false;
    }

    /**
     * Get REST data from URL.
     *
     * @param string $uri Micro service API, such as '/dictionary/view'
     * @param string $serviceName Micro service name, such as 'dictionary'
     * @param array $params API parameters
     * @param string $method Require method, example: GET, POST, etc
     * @param string $version App version
     * @param array $headers
     * @param string $host Service Host, such as 'api.yidianzhishi.cn'; If set, the host from service name is ignored
     * @return string|bool
     * @throws AppException
     */
    public function getRestData($uri, $serviceName, $params = array(), $method = self::REQ_GET, $version = 'v1', $headers = [], $host = null)
    {
        //self::$sessionToken = self::$sessionToken ? self::$sessionToken : session_id();
        $session = $this->app['session'];

        $map = $this->app['config']['global.appMap'];

        if (!isset($host) || !$host) {
            if (!in_array($serviceName, $map)) {
                $matched = null;
                foreach($map as $key => $val) {
                    if ($serviceName == $val) {
                        $matched  = $key;
                        break;
                    }
                }
                if ($matched) {
                    $host = explode(',', $matched)[0];
                } else {
                    throw new AppException('Remote service not found: ' . $serviceName);
                }
            } else {
                $host = array_search($serviceName, $map);
            }
        }
        error_log('[getRestData] host: ' . $host);

        /** @var \Key\Http\Request $request */
        $request = $this->app['request'];
        $uriObj = $request->getUri();
        $prefix = $uriObj->getScheme() . '://' . $host . $this->app['config']['global.apiPrefix'] . '/' . $version;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::CURL_TIMEOUT);

        $service_headers = $this->generateMicroServiceHeaders($serviceName);
        if ($service_headers === false) {
            $service_headers = [];
        }
        $headers = array_merge($headers, $service_headers);

        $token = $session->get(Constants::SESSION_KEY_TOKEN);
        if ($token) {
            $headers[] = 'Auth-Token: ' . $token;
        }

        // If admin mode, aid is required
        $isAdmin = false;
        if (is_array($headers)) {
            foreach ($headers as $header) {
                if (preg_match('/Talentyun-Source: admin/', $header)) {
                    $isAdmin = true;
                    break;
                }
            }
        }

        if ($isAdmin) {
            if (!isset($params['aid'])) {
                $account = $session ? $session->get(Constants::SESSION_ACCOUNT_KEY) : null;
                $params['aid'] = $account && isset($account['id']) ? $account['id'] : 0;
            }
        }

//        if ($method == 'POST') {
//            curl_setopt($ch, CURLOPT_POST, 1);
//            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
//        } else {
//            $headers[] = 'Content-Type: application/json; charset=utf-8';
//            $uri .= '?' . static::encodeArrayParams($params);
//        }

        switch ($method) {
            case 'GET':
                $headers[] = 'Content-Type: application/json; charset=utf-8';
                $uri .= '?' . static::encodeArrayParams($params);
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $url = $prefix . $uri;
        error_log('url: ' . $url);
        error_log('headers: ' .var_export($headers, true));
        curl_setopt($ch, CURLOPT_URL, $url);
        $resource = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpcode >= 200 && $httpcode < 300) {
            $data = json_decode($resource, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (isset($data['status']) && $data['status'] === Constants::SYS_SUCCESS) {
                    static::$restStatus = Constants::SYS_SUCCESS;
                    return isset($data['data']) ? $data['data'] : null;
                } else {
                    static::$restStatus = isset($data['status']) ? (int) $data['status'] : Constants::SYS_ERROR_DEFAULT;
//                    Tools::log(sprintf('[RestModel] Invalid returned status %s (%s) for %s', isset($data['status']) ? $data['status'] : -1, isset($data['msg']) ? $data['msg'] : '', $uri));
                }
            } else {
                throw new AppException(sprintf('[RestModel] Invalid format of returned data: %s', $resource));
            }
        } else {
            throw new AppException(sprintf('[RestModel] Remote server `%s` unusable, Code %s, curl errno %s - %s', $url, $httpcode, $errno, $error));
        }

        return false;
    }

    /**
     * Get the last rest call result status.
     *
     * @return int
     */
    public static function getLastStatus()
    {
        return static::$restStatus;
    }

    /**
     * Encoded the array parameters.
     *
     * @param array $args
     * @return bool|string
     */
    protected static function encodeArrayParams($args)
    {
        if (!is_array($args)) return false;
        $c = 0;
        $out = '';
        foreach ($args as $name => $value) {
            if ($c++ != 0) $out .= '&';
            $out .= urlencode("$name") . '=';
            if (is_array($value) || is_object($value)) {
                $out .= urlencode(serialize($value));
            } else {
                $out .= urlencode($value);
            }
        }
        return $out;
    }

    public function handleResource($target, $params)
    {
        $newParams = array();
        $serviceName = self::convertTargetToService($target, $newParams);
        $params = array_merge($newParams, $params);
        return $this->getRestData('/' . strtolower($serviceName) . '/resource', $serviceName, $params);
    }

    protected static function convertTargetToService($target,&$newParams=null)
    {
        $serviceName = 'base';
        // TODO: add some configure to control it
        switch($target) {
            case 'position':
            case 'job':
            case 'employee':
            case 'competency':
                $newParams = array('model'=>$target);
                break;
            case 'department':
                $newParams = array('model'=>$target);
                break;
            case 'activityRelation':
                $serviceName = 'talentTest';
                break;
            case 'talentReview':
                $serviceName = 'talentReview';
                break;
            case 'performance':
                $serviceName = 'performance';
                break;
            default:$serviceName = $target;

        }
        return $serviceName;
    }

    public function unique($pairs, $target, $pre = null, $fields = null)
    {
        error_log('[RestModel:unique] target: '.$target);
        $serviceName = self::convertTargetToService($target);
        return $this->getRestData('/rest/unique', $serviceName, array(
            'target' => $target,
            'pairs' => $pairs,
            'pre' => $pre,
            'fields' => $fields
        ));
    }

    public function createMainEvent($target, $record)
    {
        $serviceName = self::convertTargetToService($target);
        return $this->getRestData('/rest/model/create', $serviceName, array(
            'record' => $record,
            'target' => $target
        ), RestModel::REQ_POST);
    }

    public function createSubEvent($target, $subTarget, $record, $main)
    {
        $serviceName = self::convertTargetToService($target);
        return $this->getRestData('/rest/model/create', $serviceName, array(
            'record' => $record,
            'main' => $main,
            'target' => $target,
            'subTarget' => $subTarget
        ), RestModel::REQ_POST);
    }

    public function updateMainEvent($target, $id, $record)
    {
        $serviceName = self::convertTargetToService($target);
        return $this->getRestData('/rest/model/update', $serviceName, array(
            'id' => $id,
            'record' => $record,
            'target' => $target
        ), RestModel::REQ_POST);
    }

    public function updateSubEvent($target, $subTarget, $keyPairs, $record, $main)
    {
        $serviceName = self::convertTargetToService($target);
        return $this->getRestData('/rest/model/update', $serviceName, array(
            'record' => $record,
            'main' => $main,
            'pairs' => $keyPairs,
            'target' => $target,
            'subTarget' => $subTarget
        ), RestModel::REQ_POST);
    }
}