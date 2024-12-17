<?php
require('global.php');

$container = new Pimple\Container([]);
$provider = new \Key\ServiceProvider();
$provider->register($container);

$container['appName'] = 'base';

$classLoader = new \Composer\Autoload\ClassLoader();
$classLoader->addPsr4('App\\', APPS_PATH . DS . 'base' . DS . 'mc');
$classLoader->register();

//===========================================================================================
function outputResult($message = 'OK', $status = 0, $data = null)
{
    $result = [
        'status' => $status,
        'message' => $message
    ];
    if (!is_null($data)) {
        $result['data'] = $data;
    }
    exit(json_encode($result, true));
}

function respond(\Key\Http\Response $response)
{
    if (!headers_sent()) {
        // Status
        header(sprintf(
            'HTTP/%s %s %s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        ));

        // Headers
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }

        //header('strict-transport-security: max-age=16070400; includeSubDomains');
        //header('x-frame-options: SAMEORIGIN');
    } else {
        //error_log('[WARNING] headers have been sent already!');
    }

    $body = $response->getBody();
    if ($body->isSeekable()) {
        $body->rewind();
    }

    exit($body->getContents());

}

function getIp()
{
    // Get real visitor IP behind CloudFlare network
    if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
        $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
        $_SERVER['HTTP_CLIENT_IP'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
    }
    $client = @$_SERVER['HTTP_CLIENT_IP'];
    $forward = @$_SERVER['HTTP_X_FORWARDED_FOR'];
    $remote = $_SERVER['REMOTE_ADDR'];

    if (filter_var($client, FILTER_VALIDATE_IP)) {
        $ip = $client;
    } elseif (filter_var($forward, FILTER_VALIDATE_IP)) {
        $ip = $forward;
    } else {
        $ip = $remote;
    }

    return $ip;
}

function validateIP($whitelist = [])
{
    if ($whitelist) {
        $ip = getIp();
        // error_log('[validateIP] ip: ' . $ip);
        return in_array($ip, $whitelist);
    }
    return true;
}

//===========================================================================================
/** @var \Key\Http\Request $request */
$request = $container['request'];
/** @var \Key\Http\Response $response */
$response = $container['response'];

$grant_type = $request->getParam('grant_type');
$access_key = $request->getParam('access_key');
$secret_key = $request->getParam('access_secret');

$origin = $request->getHeaderLine('origin');
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET,POST');
    header('Access-Control-Max-Age: 60');
    header('Access-Control-Allow-Headers: *');
}

//error_log('client credential: ' . $grant_type);

if (strcmp($grant_type, 'client_credential') !== 0) {
    //outputResult('invalid grant type', 100);
    $response->withJson([
        'errcode' => 100,
        'errmsg' => 'invalid grant type'
    ]);
} elseif (!$access_key) {
    //outputResult('invalid access key', 101);
    $response->withJson([
        'errcode' => 101,
        'errmsg' => 'invalid access key'
    ]);
} elseif (!$secret_key) {
    //outputResult('invalid secret key', 102);
    $response->withJson([
        'errcode' => 102,
        'errmsg' => 'invalid secret key'
    ]);
} else {
    /** @var \Key\Cache\Redis $cache */
    $cache = $container['cache'];
    $cache->getRedis()->select(0);
    $cacheKey = 'OPEN_API_KEY:' . $access_key . ':' . $secret_key;
    $accessTokenKeyPrefix = 'OPEN_API_ACCESS_TOKEN:' . $access_key . ':';
    $frequencyKey = 'OPEN_API_FREQUENCY:' . $access_key;

    // @see config/hook.php@openApiVerify
    $hookKey = 'OPEN_API_ACCOUNT:' . $access_key;

    if ($cache->get($cacheKey)) {
        $result = true;
    } else {
        /** @var \Key\Database\Mongodb $db */
        $db = $container['mongodb_global'];
        // // Get account by access key
        // $result = $db->fetchRow('account', [
        //     'config.api.access_key' => $access_key,
        //     'config.api.access_secret' => $secret_key
        // ], [
        //     'id' => 1,
        //     'name' => 1,
        //     'code' => 1,
        //     'config.api' => 1
        // ]);

        // if ($result) {
        //     // error_log('default: ' . json_encode($result));
        //     $cache->set($cacheKey, $result['id']);
        //     $cache->set($hookKey, [
        //         'id' => $result['id'],
        //         'code' => $result['code'],
        //         'access_secret' => ArrayGet($result, 'config.api.access_secret'),
        //         'whitelist' => ArrayGet($result, 'config.api.whitelist')
        //     ]);
        // } else {
            // Try to get from __db_open_api
            // error_log('Using __db_open_api coll');
            $result = $db->fetchRow('__db_open_api', [
                'access_key' => $access_key,
                'access_secret' => $secret_key,
                'status' => 1,
            ]);
            if ($result) {
                $cache->set($cacheKey, $result['aid']);
                $cache->set($hookKey, [
                    'id' => $result['aid'],
                    'code' => $result['code'],
                    'access_secret' => ArrayGet($result, 'access_secret'),
                    'whitelist' => ArrayGet($result, 'whitelist')
                ]);
            }
        // }
    }


    if (!$result) {
        $response->withJson([
            'errcode' => 102,
            'errmsg' => 'invalid access key'
        ]);
    } else {
        // check IP white list
        $result = @json_decode($cache->get($hookKey), true);
        $whitelist = isset($result['whitelist']) ? $result['whitelist'] : [];
        if (!validateIP($whitelist)) {
            $response->withJson([
                'errcode' => 103,
                'errmsg' => 'invalid ip, not in whitelist'
            ]);
        } else {
            if (!$cache->exists($frequencyKey)) {
                $time = time();
                $dayStart = strtotime(date('Y', $time) . '-' . date('m', $time) . '-' . date('d', $time));
                $cache->set($frequencyKey, 0, $time - $dayStart); // 1 day
            }
            //error_log('Frequency: ' . $cache->get($frequencyKey));
            $cache->increase($frequencyKey);
            $random = randomChars(8, 'mix');
            $cache->set('OPEN_API_RANDOM:' . $access_key, $random, 7300);
            $hash = hash('sha256', $random . $access_key);
            $token = hash_hmac('sha256', $hash, 'YDZS' . $secret_key);
            $cache->set($accessTokenKeyPrefix . $token, 1, 7300);

            // Log
            /** @var \Key\Database\Mongodb $db */
            $db = $container['mongodb_global'];
            $db->insert('openapi_log', [
                'aid' => $result['aid'] ?? $result['id'],
                'code' => $result['code'],
                'ip' => getIp(),
                'access_key' => $access_key,
                'token' => $token,
                'created' => \Key\Database\Mongodb::getMongoDate()
            ]);

            $response->withJson([
                'access_token' => $token,
                'expires_in' => 7200
            ]);
        }
    }
}

respond($response);
