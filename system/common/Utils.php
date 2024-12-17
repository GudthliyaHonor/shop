<?php

/**
 * Key framework.
 *
 * @package Key
 * @copyright 2022 yidianzhishi.com
 * @version 1.0.0
 * @link https://www.yidianzhishi.com
 */

namespace App\Common;

class Utils
{
    /**
     * Encrypt w/ params.
     * @param string[] $params
     * @return string
     */
    public static function paramsEncrypt($params = [])
    {
        $secret = env('PARAMS_ENCRYPT_SECRET', '*T=BwJg6>zD8Z1Jn');
        $str = '';
        foreach ($params as $val) {
            $str .= $val;
        }
        $str .= $secret;
        // error_log('-----' . $str . ' --> ' . md5($str));
        return md5($str);
    }

    /**
     * Verify the token w/ params.
     * @param string[] $params
     * @param string $token
     * @return boolean
     */
    public static function paramsVerify($params = [], $token)
    {
        $secret = env('PARAMS_ENCRYPT_SECRET', '*T=BwJg6>zD8Z1Jn');
        $str = '';
        foreach ($params as $val) {
            $str .= $val;
        }
        $str .= $secret;
        $md5 = md5($str);
        // error_log('-----' . $str . ' --> ' . $md5);
        return strcmp($md5, $token) === 0;
    }

    public static function uuid()
    {
        $chars = md5(uniqid(mt_rand(), true));
        return substr($chars, 0, 8) . '-'
            . substr($chars, 8, 4) . '-'
            . substr($chars, 12, 4) . '-'
            . substr($chars, 16, 4) . '-'
            . substr($chars, 20, 12);
    }


    public static function filterArrayKeys(&$arr)
    {
        if (is_array($arr)) {
            $index = 0;
            foreach ($arr as $key => $val) {
                $newKey = str_replace(['.', '$'], '_', $key);
                // error_log($key . ' --> ' . $newKey);
                if (strcmp($key, $newKey) !== 0 && isset($arr[$newKey])) {
                    $newKey = $newKey . '__' . $index++;
                }

                if (is_array($val)) {
                    self::filterArrayKeys($val);
                }

                unset($arr[$key]);
                $arr[$newKey] = $val;
            }
        }
    }

    /**
     * 使用公司ID设置数据库连接。
     *
     * @param \Key\Container $app
     * @param int $aid
     * @param int $eid
     * @return void
     */
    public static function mock($app, $aid, $eid = 0)
    {
        if ($aid && $app) {
            $app[Constants::MOCK_KEY] = 1;
            $app[Constants::SESSION_KEY_CURRENT_ACCOUNT_ID] = $aid;
            if ($eid) {
                $app[Constants::SESSION_KEY_CURRENT_EMPLOYEE_ID] = $eid;
            }

            if ($app->offsetExists('db_proxy_name')) {
                $app->offsetUnset('db_proxy_name');
            }
            $connector = new DBConnector($app);
            $connector->setupById($aid);
        }
    }

    /**
     * 从公司code设置数据库连接。
     * 注：必须存在公司数据才能使用该方法, 即应在{@link #mock}方法后使用（例如创建公司时）。
     *
     * @param \Key\Container $app
     * @param string $code 公司ID
     * @param integer $eid 操作者ID
     * @return void
     */
    public static function mockByCode($app, $code, $eid = 0)
    {
        if ($code && $app) {
            $connector = new DBConnector($app);
            $connector->setupByCode($code);

            // error_log('[mockByCode] url: ' . $app['mongodb']->getUrl() . ' // ' . $app['mongodb']->getDbName());
            $row = $app['mongodb']->fetchRow(Coll::COLL_ACCOUNT, ['status' => 1, 'code' => strtolower($code)], ['id' => 1]);
            error_log('[mockByCode] row: ' . json_encode($row));
            if ($row) {
                $app[Constants::MOCK_KEY] = 1;
                $app[Constants::SESSION_KEY_CURRENT_ACCOUNT_ID] = $row['id'];
                if ($eid) {
                    $app[Constants::SESSION_KEY_CURRENT_EMPLOYEE_ID] = $eid;
                }
            }
        }
    }
}