<?php

/**
 * TalentYun Chat App.
 */

namespace App;


class Utils
{

    static $langs;

    /**
     * Encrypt the password.
     *
     * @param string $pwd Password string
     * @param null|string $salt Salt string to make the encrypt password better
     * @return string Encrypted password
     */
    public static function encrypt($pwd, $salt = null)
    {
        if ($salt && is_string($salt)) {
            $key = md5($salt);
            $pwd = substr($key, 0, 16) . $pwd . substr($key, 16, 16);
            return md5($pwd);
        } else {
            return md5($pwd . env('APP_PASSKEY', '#_#_TALENT_YUN_@_@'));
        }
    }

    /**
     * @param string $session_id Session ID
     * @param string $secret
     * @return mixed
     * @deprecated {@link generateHmac()}
     */
    public static function hmcToken($session_id, $secret)
    {
        if (env('APP_AUTH_TOKEN_ENCODE_VERSION')) {
            return hmacEncodeV2($session_id, $secret);
        }
        return str_replace('=', '', base64_encode(hash_hmac('sha256', $session_id, $secret, true)));
    }

    public static function generateAuthToken($sid, $secret, $version = null)
    {
        // $hmcToken = self::hmcToken($sid, $secret);
        // $authToken = 's:' . $sid . '.' . $hmcToken;
        // if (env('APP_AUTH_TOKEN_ENCODE_VERSION')) {
        //     $authToken .= '.' . env('APP_AUTH_TOKEN_ENCODE_VERSION');
        // }
        // return $authToken;
        return generateHmac($sid, $secret, $version);
    }

    /**
     * Convert the mongo date to timestamp in the array,
     *
     * @param array $info
     * @param string|null $format Date format
     * @param bool $extra If true, use other attribute to store the value
     * @return array
     */
    public static function convertMongoDateToTimestamp(&$info, $format = null, $extra = false)
    {
        if (is_array($info)) {
            foreach ($info as $key => $value) {
                if ($value instanceof \MongoDB\BSON\UTCDatetime) {
                    /** @var \DateTime $datetime */
                    //$datetime = $value->toDateTime();
                    if ($extra) {
                        $info[$key] = $value->__toString();
                        $info['__' . $key] = $format ? $value->toDateTime()->format($format) : $value->__toString();
                    }
                    else {
                        $info[$key] = $format ? $value->toDateTime()->format($format) : $value->__toString();
                    }
                } elseif (is_array($value)) {
                    $info[$key] = static::convertMongoDateToTimestamp($value, $format, $extra);
                }
            }
        }

        return $info;
    }

    /**
     * Convert mongodb ObjectID to string.
     * @param array $info
     * @param bool $breakWhenConverted If true, when convert one Object ID, skip all left items.
     * @param bool $recursively If true, convert the Object ID recursively
     * @return array
     */
    public static function convertObjectId(&$info, $breakWhenConverted = true, $recursively = true)
    {
        if (is_array($info)) {
            foreach ($info as $key => $value) {
                if ($value instanceof \MongoDB\BSON\ObjectId) {
                    $info[$key] = $value->__toString();
                    if ($breakWhenConverted) break;
                } elseif (is_array($value) && $recursively) {
                    $info[$key] = static::convertObjectId($value);
                }
            }
        }
        return $info;
    }

    /**
     * i10n
     *
     * @param string $key
     * @param array $binds
     * @param string $lang
     * @return mixed|null
     */
    public static function getLang($key, $binds = [], $lang = 'zh-CN')
    {
        // Using apcu cache
        $cacheKey = 'LANG_' . $lang;
        if (extension_loaded('apcu') && function_exists('apcu_enabled') && apcu_enabled()) {
            // error_log('load lang from apcu');
            $res = apcu_fetch($cacheKey, $success);
            if ($res) {
                self::$langs = unserialize($res);
            }
        }

        if (!self::$langs) {
            $lang_file = dirname(dirname(__FILE__)) . DS . 'languages' . DS . $lang . '.php';
            if (!file_exists($lang_file)) {
                error_log('Language file not found: ' . $lang_file);
                // Load default lang
                $lang_file = dirname(dirname(__FILE__)) . DS . 'languages' . DS . env('APP_LANGUAGE_DEFAULT', 'zh-CN') . '.php';
            }
            self::$langs = include($lang_file);

            if (extension_loaded('apcu') && function_exists('apcu_enabled') && apcu_enabled()) {
                error_log('store lang into apcu');
                 apcu_store($cacheKey, serialize(self::$langs), env('APCU_LANG_TTL', 3600));
            }
        }

        $value = isset(self::$langs[$key]) ? self::$langs[$key] : $key;
        return vsprintf($value, $binds);
    }

    /**
     * @deprecated Using strtotime() instead
     */
    public static function parseDatetime($timeString)
    {
        // if ($parsedStart = date_parse($timeString)) {
        //     $startStr = $parsedStart['year'] . '/' . $parsedStart['month'] . '/' . $parsedStart['day'] . ' '
        //         . ($parsedStart['hour'] ?: '00') . ':' . ($parsedStart['minute'] ?: '00') . ':' . ($parsedStart['second'] ?: '00');
        //     $time = date_create($startStr);
        //     return $time ? $time->getTimestamp() : false;
        // }
        // return false;
        return strtotime($timeString);
    }

    /**
     * Get client ip.
     * @see https://www.php.cn/php-weizijiaocheng-406174.html
     * @return null|string
     */
    public static function getIp()
    {
        $ip = FALSE;
        //客户端IP 或 NONE
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }

        //多重代理服务器下的客户端真实IP地址（可能伪造）,如果没有使用代理，此字段为空
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(', ', $_SERVER['HTTP_X_FORWARDED_FOR']);
            if ($ip) {
                array_unshift($ips, $ip);
                $ip = FALSE;
            }
            for ($i = 0; $i < count($ips); $i++) {
                if (!preg_match('#^(10│172.16│192.168).#', $ips[$i])) {
                    $ip = $ips[$i];
                    break;
                }
            }
        }

        //客户端IP 或 (最后一个)代理服务器 IP
        return ($ip ? $ip : $_SERVER['REMOTE_ADDR']);
    }

    /**
     * Get the OS of the client.
     * @param string|null $agent
     * @return string
     */
    public static function getOS($agent = null)
    {
        $agent = $agent ? $agent : (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '');

        if (preg_match('/win/i', $agent) && preg_match('/nt 6.0/i', $agent)) {
            $os = 'Windows Vista';
        } else if (preg_match('/win/i', $agent) && preg_match('/nt 6.1/i', $agent)) {
            $os = 'Windows 7';
        } else if (preg_match('/win/i', $agent) && preg_match('/nt 6.2/i', $agent)) {
            $os = 'Windows 8';
        } else if (preg_match('/win/i', $agent) && preg_match('/nt 10.0/i', $agent)) {
            $os = 'Windows 10'; #添加win10判断
        } else if (preg_match('/win/i', $agent) && preg_match('/nt 5.1/i', $agent)) {
            $os = 'Windows XP';
        } elseif (preg_match('/Android ([\d\.]+);/i', $agent, $matched)) {
            // such as: Mozilla/5.0 (Linux; Android 8.0.0; BAC-AL00 Build/HUAWEIBAC-AL00; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/66.0.3359.126 MQQBrowser/6.2 TBS/044506 Mobile Safari/537.36 MMWEBID/6311 MicroMessenger/7.0.3.1400(0x2700033B) Process/tools NetType/WIFI Language/zh_CN
            $os = 'android ' . ($matched[1] ?? 'NaN');
        } elseif (
            preg_match('/iPhone; CPU iPhone OS ([\d\_]+)/i', $agent, $matched)
            || preg_match('/iPad;.*CPU(?! iPhone) OS ([\d\_]+)/i', $agent, $matched)
            || preg_match('/iPod;.*CPU(?! iPhone) OS ([\d\_]+)/i', $agent, $matched)
        ) {
            // Such as: Mozilla/5.0 (iPhone; CPU iPhone OS 9_1 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Version/9.0 Mobile/13B143 Safari/601.1
            $os = 'iOS ' . ($matched[1] ?? 'NaN');
        } elseif (preg_match('/Macintosh/', $agent) && preg_match('/Mac OS X\s+([^\);]+)/', $agent, $matched)) {
            $os = 'Mac' . (($matched[1] ?? null) ? ' ' . $matched[1] : '');
        } else if (preg_match('/linux/i', $agent)) {
            $os = 'Linux';
        } elseif (preg_match('/PostmanRuntime/i', $agent)) {
            $os = 'Windows / Postman';
        } else if (preg_match('/unix/i', $agent)) {
            $os = 'Unix';
        } else if (preg_match('/sun/i', $agent) && preg_match('/os/i', $agent)) {
            $os = 'SunOS';
        } else if (preg_match('/ibm/i', $agent) && preg_match('/os/i', $agent)) {
            $os = 'IBM OS/2';
        } else if (preg_match('/Mac/i', $agent) && preg_match('/PC/i', $agent)) {
            $os = 'Mac';
        } else if (preg_match('/PowerPC/i', $agent)) {
            $os = 'PowerPC';
        } else if (preg_match('/AIX/i', $agent)) {
            $os = 'AIX';
        } else if (preg_match('/HPUX/i', $agent)) {
            $os = 'HPUX';
        } else if (preg_match('/NetBSD/i', $agent)) {
            $os = 'NetBSD';
        } else if (preg_match('/BSD/i', $agent)) {
            $os = 'BSD';
        } else if (preg_match('/OSF1/i', $agent)) {
            $os = 'OSF1';
        } else if (preg_match('/IRIX/i', $agent)) {
            $os = 'IRIX';
        } else if (preg_match('/FreeBSD/i', $agent)) {
            $os = 'FreeBSD';
        } else if (preg_match('/teleport/i', $agent)) {
            $os = 'teleport';
        } else if (preg_match('/flashget/i', $agent)) {
            $os = 'flashget';
        } else if (preg_match('/webzip/i', $agent)) {
            $os = 'webzip';
        } else if (preg_match('/offline/i', $agent)) {
            $os = 'offline';
        } else if (preg_match('/win/i', $agent) && preg_match('/nt 5/i', $agent)) {
            $os = 'Windows 2000';
        } else if (preg_match('/win/i', $agent) && preg_match('/nt/i', $agent)) {
            $os = 'Windows NT';
        } else if (preg_match('/win/i', $agent) && preg_match('/32/i', $agent)) {
            $os = 'Windows 32';
        } else if (preg_match('/win 9x/i', $agent) && strpos($agent, '4.90')) {
            $os = 'Windows ME';
        } else if (preg_match('/win/i', $agent) && preg_match('/98/i', $agent)) {
            $os = 'Windows 98';
        } else if (preg_match('/win/i', $agent) && strpos($agent, '95')) {
            $os = 'Windows 95';
        } else {
            $os = 'UNKNOWN';
        }
        return $os;
    }

    /**
     * Get the browser info of the client.
     *
     * @param string|null $agent
     * @return string
     */
    public static function getBrowser($agent = null)
    {
        $sys = $agent ?: (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '');
        if (stripos($sys, "Firefox/") > 0) {
            preg_match("/Firefox\/([^;)]+)+/i", $sys, $b);
            $exp[0] = 'Firefox';
            $exp[1] = $b[1] ?? 'NaN';  //获取火狐浏览器的版本号
        } elseif (stripos($sys, "Maxthon") > 0) {
            preg_match("/Maxthon\/([\d\.]+)/", $sys, $aoyou);
            $exp[0] = '傲游';
            $exp[1] = $aoyou[1] ?? 'NaN';
        } elseif (stripos($sys, "MSIE") > 0) {
            preg_match("/MSIE\s+([^;)]+)+/i", $sys, $ie);
            $exp[0] = 'IE';
            $exp[1] = $ie[1];  //获取IE的版本号
        } elseif (stripos($sys, "OPR") > 0) {
            preg_match("/OPR\/([\d\.]+)/", $sys, $opera);
            $exp[0] = 'Opera';
            $exp[1] = $opera[1] ?? 'NaN';
        } elseif (stripos($sys, "Edge") > 0) {
            //win10 Edge浏览器 添加了chrome内核标记 在判断Chrome之前匹配
            preg_match("/Edge\/([\d\.]+)/", $sys, $Edge);
            $exp[0] = 'Edge';
            $exp[1] = $Edge[1] ?? 'NaN';
        } elseif (stripos($sys, "Chrome") > 0) {
            preg_match("/Chrome\/([\d\.]+)/", $sys, $google);
            $exp[0] = 'Chrome';
            $exp[1] = $google[1] ?? 'NaN';  //获取google chrome的版本号
        } elseif (stripos($sys, 'rv:') > 0 && stripos($sys, 'Gecko') > 0) {
            preg_match("/rv:([\d\.]+)/", $sys, $IE);
            $exp[0] = 'IE';
            $exp[1] = $IE[1] ?? 'NaN';
        } elseif (stripos($sys, 'AppleWebKit/') > 0) {
            preg_match('/AppleWebKit\/([\d\.]+)/', $sys, $matched);
            $exp[0] = 'Safari';
            $exp[1] = $matched[1] ?? 'NaN';
        } else {
            $exp[0] = 'UNKNOWN';
            $exp[1] = '';
        }
        return $exp[0] . ($exp[1] ? '(' . $exp[1] . ')' : '');
    }

    /**
     * Determine if the subject is a mobile number.
     *
     * @param string $subject
     * @return int
     */
    public static function isMobile($subject)
    {
        $regex = env('REGEX_MOBILE', '#((13[0-9])|(14[5|7])|(15([0-3]|[5-9]))|(18[0,5-9]))\d{8}$#');
        return preg_match($regex, $subject);
    }

    /**
     * Get the start of the day.
     *
     * @param int $timestamp
     * @return false|int
     */
    public static function getMidnight($timestamp = 0)
    {
        if ($timestamp) {
            return strtotime(date('Y-m-d', $timestamp));
        }
        return strtotime(date('Y-m-d'));
    }

    /**
     * Get the start of next day.
     *
     * @param int $timestamp
     * @return bool|false|int
     */
    public static function getNextMidnight($timestamp = 0)
    {
        if ($ts = self::getMidnight($timestamp)) {
            return strtotime(date('Y-m-d H:i:s', $ts) . ' +1 day');
        }
        return false;
    }

    public static function getPreviousMidnight($timestamp = 0)
    {
        if ($ts = self::getMidnight($timestamp)) {
            return strtotime(date('Y-m-d H:i:s', $ts) . ' -1 day');
        }
        return false;
    }

    /**
     * Get start day of the week.
     *
     * @param int $timestamp
     * @param int $weekStartDay The start index of the week, 0-Sunday, 1-Monday, 2-Tuesday...
     * @return bool|false|int
     */
    public static function getWeekStart($timestamp = 0, $weekStartDay = 1)
    {
        $timestamp = $timestamp ? $timestamp : time();
        if ($weekStartDay > 6) {
            throw new \InvalidArgumentException('Invalid week start day');
        }
        $weeks = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        return strtotime(date('Y-m-d', strtotime("this week " . $weeks[$weekStartDay], $timestamp)));
    }

    /**
     * Get the start day of the month.
     *
     * @param int $timestamp
     * @return false|int
     */
    public static function getMonthStart($timestamp = 0)
    {
        return strtotime(date('Y-m-01'), $timestamp);
    }

    /**
     * Get the end day of the month.
     *
     * @param int $timestamp
     * @return false|int
     */
    public static function getMonthEnd($timestamp = 0)
    {
        return mktime(23, 59, 59, date('m', $timestamp), date('t', $timestamp), date('Y', $timestamp));
    }

    /**
     * Get the absolute path for uploaded file.
     *
     * @param string $filename Uploaded relative file path, for example: tmp/FL6ReA5w7ER.json
     * @return string
     */
    public static function getAbsoluteUploadPath($filename)
    {
        $ds = DIRECTORY_SEPARATOR;
        // return env('FILE_STORAGE_FOLDER', '/tmp/') . (startsWith($ds, $filename) ? substr($filename, 1) : $filename);
        return rtrim(env('FILE_STORAGE_FOLDER', '/tmp'), $ds) . $ds . ltrim($filename, $ds);
    }

    /**
     * Filter the array.
     *
     * @param array $array Array
     * @param array $fields Fields filtering
     * @return array
     */
    public static function fieldArray($array, $fields = [])
    {
        if (is_array($array)) {
            if (is_array($fields) && $fields) {
                $newArray = [];
                foreach ($fields as $name => $status) {
                    if ($status) {
                        $newArray[$name] = ArrayGet($array, $name);
                    }
                }
                if ($newArray) {
                    return $newArray;
                }
            }
        }
        return $array;
    }

    /**
     * array_diff that works with recursive arrays.
     *
     * @param array $aArray1
     * @param array $aArray2
     * @param bool $strict
     * @return array
     */
    public static function arrayRecursiveDiff($aArray1, $aArray2, $strict = true)
    {
        if (is_null($aArray1)) {
            return $aArray2;
        }
        if (is_null($aArray2)) {
            return $aArray1;
        }
        if (!is_array($aArray1) && !is_array($aArray2)) {
            error_log('[WARN] array1 or array2 is not array');
            return [];
        }
        $aReturn = array();

        foreach ($aArray1 as $mKey => $mValue) {
            if (array_key_exists($mKey, $aArray2)) {
                if (is_array($mValue)) {
                    $aRecursiveDiff = self::arrayRecursiveDiff($mValue, $aArray2[$mKey]);
                    if (count($aRecursiveDiff)) {
                        $aReturn[$mKey] = $aRecursiveDiff;
                    }
                } else {
                    if ($mValue != $aArray2[$mKey]) {
                        $aReturn[$mKey] = $mValue;
                    }
                }
            } else {
                if (!$strict && !$mValue) continue;
                $aReturn[$mKey] = $mValue;
            }
        }

        return $aReturn;
    }

    /**
     * Prepare keys.
     *
     * @param string $key
     * @return void
     */
    protected static function prepareKey(string &$key)
    {
        $key = trim($key, '. ');
        $key = rtrim($key, '.*');
        $key = empty($key) ? [] : explode('.', $key);
    }
    protected static function deleteByField(string $key, array &$arr = null)
    {

        $items = &$arr;

        static::prepareKey($key);
        $max = count($key) - 1;

        for ($index = 0; $index <= $max; $index++) {
            if ($index == $max) {
                if (isset($items[$key[$index]])) {
                    unset($items[$key[$index]]);
                    return true;
                }
            } else {
                if ($key[$index] == '*') {
                    $index++;
                    $next_key = implode('.', array_slice($key, $index));
                    $rs = true;

                    foreach ($items as &$item)
                        if (!delete($next_key, $item)) $rs = false;

                    return $rs;
                } else {
                    if (isset($items[$key[$index]])) $items = &$items[$key[$index]];
                }
            }
        }

        return false;
    }

    /**
     * Filter the result via fields.
     * @see https://pharaonic.io/package/1-general-php/4-dot-array
     * @param array $arr
     * @param array $fields for example: ['a' => 1, 'b.b1' => 1, 'b.b2' => 0], NOT support $ param, such as ['x' => '$y']
     * @return array
     */
    public static function handleFieldsReturn($arr, $fields)
    {
        if (!$fields) return $arr;
        $allUnset = true;
        foreach ($fields as $val) {
            if ($val) {
                $allUnset = false;
                break;
            }
        }
        if ($allUnset) {
            foreach ($fields as $key => $val) {
                if (isset($arr[$key])) {
                    unset($arr[$key]);
                } else {
                    static::deleteByField($key, $arr);
                }
            }
            return $arr;
        } else {
            $returned = [];
            foreach ($fields as $key => $val) {
                if ($val) {
                    if (isset($arr[$key])) {
                        $returned[$key] = $arr[$key];
                    } else {
                        $value = ArrayGet($arr, $key);
                        if (!is_null($value)) {
                            $pieces = explode('.', $key);
                            $pieces = array_reverse($pieces);
                            if (count($pieces) > 1) {
                                $tmp = [];
                                $tmp[$pieces[0]] = $value;
                                $lastKey = $pieces[count($pieces) - 1];
                                foreach ($pieces as $idx => $item) {
                                    if ($idx == 0) continue;
                                    $tmp[$item] = $tmp;
                                }
                                $returned[$lastKey] = $tmp[$lastKey];
                            } else {
                                $returned[$key] = $value;
                            }
                        }
                    }
                }
            }
            return $returned;
        }
    }

    /**
     * Download the file from the url.
     *
     * @param string $url
     * @param string $filename destination file
     * @param int $limit Limit download file size
     * @return bool
     */
    public static function download($url, $filename, $limit = 10485760)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($curl, CURLOPT_TIMEOUT, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        //curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

        curl_setopt($curl, CURLOPT_NOPROGRESS, false);
        curl_setopt($curl, CURLOPT_PROGRESSFUNCTION, function ($downloadSize, $downloaded, $uploadSize, $uploaded) use ($limit) {
            return $downloaded > $limit ? 1 : 0;
        });

        $response = curl_exec($curl);
        $info = curl_getinfo($curl);
        $httpCode = $info['http_code'] ?? 0;
        if ($httpCode == 200) {
            file_put_contents($filename, $response);
            return true;
        }
        return false;
    }

    /**
     * array_merge_recursive does indeed merge arrays, but it converts values with duplicate
     * keys to arrays rather than overwriting the value in the first array with the duplicate
     * value in the second array, as array_merge does. I.e., with array_merge_recursive,
     * this happens (documented behavior):
     *
     * array_merge_recursive(array('key' => 'org value'), array('key' => 'new value'));
     *     => array('key' => array('org value', 'new value'));
     *
     * array_merge_recursive_distinct does not change the datatypes of the values in the arrays.
     * Matching keys' values in the second array overwrite those in the first array, as is the
     * case with array_merge, i.e.:
     *
     * array_merge_recursive_distinct(array('key' => 'org value'), array('key' => 'new value'));
     *     => array('key' => array('new value'));
     *
     * Parameters are passed by reference, though only for performance reasons. They're not
     * altered by this function.
     *
     * @param array $array1
     * @param array $array2
     * @return array
     * @author Daniel <daniel (at) danielsmedegaardbuus (dot) dk>
     * @author Gabriel Sobrinho <gabriel (dot) sobrinho (at) gmail (dot) com>
     */
    public static function arrayMergeRecursiveDistinct(array &$array1, array &$array2)
    {
        $merged = $array1;

        foreach ($array2 as $key => &$value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = self::arrayMergeRecursiveDistinct($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    public static function Sbc2Dbc($str)
    {
        $arr = array(
            '０' => '0', '１' => '1', '２' => '2', '３' => '3', '４' => '4', '５' => '5', '６' => '6', '７' => '7', '８' => '8', '９' => '9',
            'Ａ' => 'A', 'Ｂ' => 'B', 'Ｃ' => 'C', 'Ｄ' => 'D', 'Ｅ' => 'E', 'Ｆ' => 'F', 'Ｇ' => 'G', 'Ｈ' => 'H', 'Ｉ' => 'I', 'Ｊ' => 'J',
            'Ｋ' => 'K', 'Ｌ' => 'L', 'Ｍ' => 'M', 'Ｎ' => 'N', 'Ｏ' => 'O', 'Ｐ' => 'P', 'Ｑ' => 'Q', 'Ｒ' => 'R', 'Ｓ' => 'S', 'Ｔ' => 'T',
            'Ｕ' => 'U', 'Ｖ' => 'V', 'Ｗ' => 'W', 'Ｘ' => 'X', 'Ｙ' => 'Y', 'Ｚ' => 'Z', 'ａ' => 'a', 'ｂ' => 'b', 'ｃ' => 'c', 'ｄ' => 'd',
            'ｅ' => 'e', 'ｆ' => 'f', 'ｇ' => 'g', 'ｈ' => 'h', 'ｉ' => 'i', 'ｊ' => 'j', 'ｋ' => 'k', 'ｌ' => 'l', 'ｍ' => 'm', 'ｎ' => 'n',
            'ｏ' => 'o', 'ｐ' => 'p', 'ｑ' => 'q', 'ｒ' => 'r', 'ｓ' => 's', 'ｔ' => 't', 'ｕ' => 'u', 'ｖ' => 'v', 'ｗ' => 'w', 'ｘ' => 'x',
            'ｙ' => 'y', 'ｚ' => 'z',
            '（' => '(', '）' => ')', '〔' => '(', '〕' => ')', '【' => '[', '】' => ']', '〖' => '[', '〗' => ']', '“' => '"', '”' => '"',
            '‘' => '\'', '’' => '\'', '｛' => '{', '｝' => '}', '《' => '<', '》' => '>', '％' => '%', '＋' => '+', '—' => '-', '－' => '-',
            '～' => '~', '：' => ':', '。' => '.', '、' => ',', '，' => ',', '、' => ',',  '；' => ';', '？' => '?', '！' => '!', '…' => '-',
            '‖' => '|', '”' => '"', '’' => '`', '‘' => '`', '｜' => '|', '〃' => '"', '　' => ' ', '×' => '*', '￣' => '~', '．' => '.', '＊' => '*',
            '＆' => '&', '＜' => '<', '＞' => '>', '＄' => '$', '＠' => '@', '＾' => '^', '＿' => '_', '＂' => '"', '￥' => '$', '＝' => '=',
            '＼' => '\\', '／' => '/'
        );
        return strtr($str, $arr);
    }

    /**
     * array_column ext.
     * @see https://www.php.net/manual/en/function.array-column.php#123045
     * @param array $array
     * @param string $columnkey
     * @param string|-1|null $indexkey
     * @return array
     */
    public static function arrayColumnExt($array, $columnkey, $indexkey = null)
    {
        $result = array();
        foreach ($array as $subarray => $value) {
            if (array_key_exists($columnkey, $value)) {
                $val = $array[$subarray][$columnkey];
            } else if ($columnkey === null) {
                $val = $value;
            } else {
                continue;
            }

            if ($indexkey === null) {
                $result[] = $val;
            } elseif ($indexkey == -1 || array_key_exists($indexkey, $value)) {
                $result[($indexkey == -1) ? $subarray : $array[$subarray][$indexkey]] = $val;
            }
        }
        return $result;
    }

    /**
     * Generate a UUID string.
     *
     * @param string|null $prefix Prefix string of the UUID
     * @return string
     */
    public static function generateUUID($prefix = '')
    {
        $chars = md5(uniqid(mt_rand(), true));
        $uuid = substr($chars, 0, 8) . '-'
            . substr($chars, 8, 4) . '-'
            . substr($chars, 12, 4) . '-'
            . substr($chars, 16, 4) . '-'
            . substr($chars, 20, 12);
        return $prefix . $uuid;
    }

    public static function isFromMoblie()
    {
        // 如果有HTTP_X_WAP_PROFILE则一定是移动设备
        if (isset($_SERVER['HTTP_X_WAP_PROFILE'])) {
            return true;
        }
        // 如果via信息含有wap则一定是移动设备
        if (isset($_SERVER['HTTP_VIA'])) {
            // 找不到为flase,否则为true
            return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
        }
        // 脑残法，判断手机发送的客户端标志,兼容性有待提高
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $clientkeywords = array(
                'nokia',
                'sony',
                'ericsson',
                'mot',
                'samsung',
                'htc',
                'sgh',
                'lg',
                'sharp',
                'sie-',
                'philips',
                'panasonic',
                'alcatel',
                'lenovo',
                'iphone',
                'ipod',
                'blackberry',
                'meizu',
                'android',
                'netfront',
                'symbian',
                'ucweb',
                'windowsce',
                'palm',
                'operamini',
                'operamobi',
                'openwave',
                'nexusone',
                'cldc',
                'midp',
                'wap',
                'mobile'
            );
            // 从HTTP_USER_AGENT中查找手机浏览器的关键字
            if (preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT']))) {
                return true;
            }
        }
        // 协议法，因为有可能不准确，放到最后判断
        if (isset($_SERVER['HTTP_ACCEPT'])) {
            // 如果只支持wml并且不支持html那一定是移动设备
            // 如果支持wml和html但是wml在html之前则是移动设备
            if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))) {
                return true;
            }
        }
        return false;
    }
}
