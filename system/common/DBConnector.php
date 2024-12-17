<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace App\Common;

use Key\Constants;
use Key\Database\Mongodb;
use Key\Exception\AppException;
use Key\Filesystem\FileFactory;

class DBConnector
{

    const CODE_REGEX = '/^[a-zA-Z0-9_-]{1,16}$/';

    const ENABLED = 1;

    const COLL = '_db_proxy';

    const CODE_DEFAULT = 'default';

    //Error Message Title
    const ERR_TITLE_CODE = 'setupByCode';
    const ERR_TITLE_ID = 'setupById';
    const ERR_TITLE_CORP_ID = 'setupByCorpId';

    /** @var \Key\Container */
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    protected function onlyUseGlobal()
    {
        return env('DB_SETUP_ONLY_GLOBAL');
    }

    protected function getGlobalDBConnection()
    {
        return $this->app['db']['global'];
    }

    protected function closeGlobalDBConnection()
    {
        $this->app['db']['global'] = null;
        unset($this->app['db']['global']);
    }

    protected function encode($row)
    {
        $text = json_encode($row);
        return jsonAesEncrypt($text);
    }
    protected function decode($text)
    {
        return jsonAesDecrypt($text);
    }
    protected function saveDBCache($file, $row, $dbFileEnabled)
    {
        if ($dbFileEnabled) {
            file_put_contents($file, $this->encode($row));
        }
    }

    public function setupByCode($code = self::CODE_DEFAULT)
    {
        if ($this->onlyUseGlobal()) {
            return;
        }

        if (!$code) {
            $code = self::CODE_DEFAULT;
        }

        if (!preg_match(self::CODE_REGEX, $code)) {
            throw new AppException('Invalid account code', ErrorCodes::ACCOUNT_CODE_INVALID);
        }

        $code = strtolower($code);

        // Read db connection from cache file
        $dbCacheDir = ($this->app->offsetExists('basepath') ? $this->app['basepath'] : '/tmp') . '/data/cache/db/code/';
        $dbCacheFile = $dbCacheDir . $code;
        $dbFileEnabled = env('DBCONN_FILE') == 1 || in_array($code, explode(',', env('DBCONN_FILE_CODE', '')));
        if ($dbFileEnabled) {
            if (!file_exists($dbCacheDir)) {
                mkdir($dbCacheDir, 0777, true);
            }
            if (file_exists($dbCacheFile)) {
                $content = file_get_contents($dbCacheFile);
                $row = json_decode($this->decode($content), true);
                $this->setupByRow($row);
                return;
            }
        }

        $cacheKey = 'DBCONN:CODE:' . $code;
        /** @var \Key\Cache\Redis $cache */
        $cache = $this->app['cache'];
        $cache->select(env('REDIS_DATABASE', 0));
        if ($res = $cache->get($cacheKey)) {
            $row = json_decode($res, true);
            $this->setupByRow($row);

            $this->saveDBCache($dbCacheFile, $row, $dbFileEnabled);
            return;
        }

        if ($uri = env("DB:URI:${code}")) {
            $row = [
                'uri' => $uri,
                'database' => env("DB:DATABASE:${code}"),
            ];
            $this->setupByRow($row);
            $this->saveDBCache($dbCacheFile, $row, $dbFileEnabled);
            return;
        }

        $condition = [
            'code' => $code,
        ];
        $row = $this->setup($condition, self::ERR_TITLE_CODE);
        if ($row) {
            $rowCode = $row['code'];
            if (strcasecmp($rowCode, $code) == 0) {
                $cache->set($cacheKey, $row);
                $this->saveDBCache($dbCacheFile, $row, $dbFileEnabled);
            }
            else {
                if ($row['aid'] == 0) {
                    $cache->set($cacheKey, $row, 60);
                    $this->saveDBCache($dbCacheFile, $row, $dbFileEnabled);
                }
                else {
                    error_log('[setupByCode] unmatched code: ' . $code);
                }
            }
        }
    }

    public function setupById($id)
    {

        if ($this->onlyUseGlobal()) {
            return;
        }

        if (is_string($id)) {
            error_log('Invalid param: ' . $id);
            error_log('trace: ' . json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)));
        }

        $id = (int) $id;

        if (env('DB:' . $id . ':URI')) {
            $row = [
                'uri' => env('DB:' . $id . ':URI'),
                'database' => env('DB:' .$id . ':NAME'),
            ];
            $this->setupByRow($row);
        }
        else {

            // Read db connection from cache file
            $dbCacheDir = ($this->app->offsetExists('basepath') ? $this->app['basepath'] : '/tmp') . '/data/cache/db/id/';
            $dbCacheFile = $dbCacheDir . $id;
            $dbFileEnabled = env('DBCONN_FILE') == 1 || in_array($id, explode(',', env('DBCONN_FILE_ID', '')));
            if ($dbFileEnabled) {
                if (!file_exists($dbCacheDir)) {
                    mkdir($dbCacheDir, 0777, true);
                }
                if (file_exists($dbCacheFile)) {
                    $content = file_get_contents($dbCacheFile);
                    $row = json_decode($this->decode($content), true);
                    $this->setupByRow($row);
                    return;
                }
            }

            $cacheKey = 'DBCONN:' . $id;
            /** @var \Key\Cache\Redis $cache */
            $cache = $this->app['cache'];
            $cache->select(env('REDIS_DATABASE', 0));
            if ($res = $cache->get($cacheKey)) {
                $row = json_decode($res, true);
                $this->setupByRow($row);
                $this->saveDBCache($dbCacheFile, $row, $dbFileEnabled);
                return;
            }
            $condition = [
                'aid' => $id
            ];
            $row = $this->setup($condition, self::ERR_TITLE_ID);
            if ($row) {
                $cache->set($cacheKey, $row);
                $this->saveDBCache($dbCacheFile, $row, $dbFileEnabled);
            }
        }
    }

    public function setupByOauthCorpId($corp_id)
    {
        if ($this->onlyUseGlobal()) {
            return;
        }

        $condition = [
            'oauth_corp_id' => $corp_id
        ];
        $this->setup($condition, self::ERR_TITLE_ID);
    }

    public function setupByCorpId($corp_id, $agent_id = '')
    {
        if ($this->onlyUseGlobal()) {
            return;
        }

        $condition = [
            'corp_id' => $corp_id
        ];
        if ($agent_id) {
            $condition['agent_id'] = $agent_id;
        }
        $this->setup($condition, self::ERR_TITLE_CORP_ID);
    }

    public function setupByProxyRow(&$row) {
        if ($this->onlyUseGlobal()) {
            return;
        }

        if ($row) {

            if (isset($row['maintenance']) && $row['maintenance']) {
                $errorCode = $row['maintenance_error_code'] ?? Constants::SYS_DATABASE_MAINTENANCE;
                $errorMessage = $row['maintenance_error_message'] ?? 'DB maintenance';
                throw new AppException($errorMessage, $errorCode);
            }

            //error_log('[' . $err_title . '] custom remote db connection');
            $aid = $row['aid'];

            // override
            $envUri = env('DB:' . $aid. ':URI');
            if ($envUri) {
                $uri = $envUri;
                $db = env('DB:' . $aid . ':NAME');
            }
            else {
                $uri = $row['uri'];
                $db = $row['database'];
            }
        } else {
            $cacheKey = 'DBCONN:1';
            $cache = $this->app['cache'];
            if ($res = $cache->get($cacheKey)) {
                $row = json_decode($res, true);
            }
            else {

                // override
                $envUri = env('DB:0:URI');
                if ($envUri) {
                    $row['uri'] = $envUri;
                    $row['database'] = env('DB:0:NAME');
                }
                else {
                    $row = $this->getGlobalDBConnection()->fetchRow(self::COLL, [
                        'id' => 1,
                        'status' => static::ENABLED
                    ]);
                }
                $cache->set($cacheKey, $row);
            }

            if (isset($row['maintenance']) && $row['maintenance']) {
                $errorCode = $row['maintenance_error_code'] ?? Constants::SYS_DATABASE_MAINTENANCE;
                $errorMessage = $row['maintenance_error_message'] ?? 'DB maintenance';
                throw new AppException($errorMessage, $errorCode);
            }

            if ($row['global'] == 1) {
                //error_log('[' . $err_title . '] global connection');
                // if ($this->app->offsetExists('mongodb')) {
                //     $this->app->offsetUnset('mongodb');
                // }
                // $this->app['mongodb'] = $this->getGlobalDBConnection();
                $globalDb = true;
            } else {
                //error_log('[' . $err_title . '] custom local connection');
                $uri = $row['uri'];
                $db = $row['database'];
            }
        }

        if ($this->app->offsetExists('mongodb')) {
            /** @var \Key\Database\Mongodb $connector */
            $connector = $this->app['mongodb'];
            if ($uri == $connector->getUrl() && $db == $connector->getDbName()) {
                // error_log('DB not changed');
                return;
            }
            $this->app->offsetUnset('mongodb');
        }
        if ($globalDb) {
            $this->app['mongodb'] = $this->getGlobalDBConnection();
        }
        else {
            $this->app['mongodb'] = new Mongodb($uri, $db);
        }

        $this->app['db_proxy_name'] = $row['code'] ?: 'default';
        $this->app['api_host'] = ($row['schema'] ?? 'https://') . $row['host'];
    }

    private function setup($condition, $err_title = self::ERR_TITLE_CODE)
    {
        //error_log('[' . $err_title . '] condition: ' . var_export($condition, true));

        $globalDb = false;

        $condition['status'] = static::ENABLED;
        $row = $this->getGlobalDBConnection()->fetchRow(self::COLL, $condition);
        // if ($row) {
        //     //error_log('[' . $err_title . '] custom remote db connection');
        //     $aid = $row['aid'];
        //     $uri = $row['uri'];
        //     $db = $row['database'];
        //     if ($this->app->offsetExists('mongodb')) {
        //         $this->app->offsetUnset('mongodb');
        //     }

        //     // override
        //     $envUri = env('DB:' . $aid. ':URI');      
        //     if ($envUri) {
        //         $uri = $envUri;
        //         $db = env('DB:' . $aid . ':NAME', $db);

        //         $row['uri'] = $uri;
        //         $row['database'] = $db;
        //     }

        //     $this->app['mongodb'] = new Mongodb($uri, $db);
        //     // $this->app['db_proxy_name'] = $row['code'] ?: 'default';
        // } else {
        //     $cacheKey = 'DBCONN:1';
        //     $cache = $this->app['cache'];
        //     if ($res = $cache->get($cacheKey)) {
        //         $row = json_decode($res, true);
        //     }
        //     else {

        //         // override
        //         $envUri = env('DB:0:URI');
        //         if ($envUri) {
        //             $row['uri'] = $envUri;
        //             $row['database'] = env('DB:0:NAME');
        //         }
        //         else {
        //             $row = $this->getGlobalDBConnection()->fetchRow(self::COLL, [
        //                 'id' => 1,
        //                 'status' => static::ENABLED
        //             ]);
        //         }
        //         $cache->set($cacheKey, $row);
        //     }

        //     if ($row['global'] == 1) {
        //         //error_log('[' . $err_title . '] global connection');
        //         if ($this->app->offsetExists('mongodb')) {
        //             $this->app->offsetUnset('mongodb');
        //         }
        //         $this->app['mongodb'] = $this->getGlobalDBConnection();
        //         $globalDb = true;
        //     } else {
        //         //error_log('[' . $err_title . '] custom local connection');
        //         $uri = $row['uri'];
        //         $db = $row['database'];
        //         if ($this->app->offsetExists('mongodb')) {
        //             $this->app->offsetUnset('mongodb');
        //         }
        //         $this->app['mongodb'] = new Mongodb($uri, $db);
        //     }
        // }
        // $this->app['db_proxy_name'] = $row['code'] ?: 'default';
        // $this->app['api_host'] = ($row['schema'] ?? 'https://') . $row['host'];
        $this->setupByProxyRow($row);
        return $row;
    }

    protected function setupByRow($row)
    {
        $dbProxyName = $this->app->offsetExists('db_proxy_name') ? $this->app['db_proxy_name'] : null;
        $code = $row['code'] ?: 'default';
        if ($dbProxyName != $code) {
            if ($this->app->offsetExists('mongodb')) {
                $this->app->offsetUnset('mongodb');
            }
            $uri = $row['uri'];
            $db = $row['database'];
            $this->app['mongodb'] = new Mongodb($uri, $db);
            $this->app['db_proxy_name'] = $code;
            $this->app['api_host'] = ($row['schema'] ?? 'https://') . $row['host'];
        }
    }

}
