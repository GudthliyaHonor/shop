<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace Key\Util;


use Key\Http\Environment;
use LoggerLevel;

/**
 * Class Tools
 * @package Key\Util
 * @deprecated
 */
class Tools
{
    public static $banStrings = array('{', '}', '$ne', '$gte', '$gt', '$lt', '$lte', '$in', '$nin', '$exists', '$where', 'tojson', '==', 'db.');

    protected static $levelMap = array(
        'FATAL' => LoggerLevel::FATAL,
        'ERROR' => LoggerLevel::ERROR,
        'WARN' => LoggerLevel::WARN,
        'INFO' => LoggerLevel::INFO,
        'DEBUG' => LoggerLevel::DEBUG,
        'TRACE' => LoggerLevel::TRACE,
        'ALL' => LoggerLevel::ALL,
        'OFF' => LoggerLevel::OFF
    );

    /**
     * Log system message
     *
     * @param string $message
     * @param string $tag
     */
    public static function log($message, $tag = null)
    {
        global $CONFIG;

        $msg = $tag ? "[$tag]" . var_export($message, true) : $message;

        if (isset($CONFIG->appLogger) && $CONFIG->appLogger) {
            $CONFIG->appLogger->debug($msg);
        } elseif (isset($CONFIG->logger) && $CONFIG->logger){
            $CONFIG->logger->debug($msg);
        }
    }

    /**
     * Log system error message.
     *
     * @param string $message
     * @param string $tag
     */
    public static function error($message, $tag = null)
    {
        global $CONFIG;
        $msg = $tag ? "[$tag]" . var_export($message, true) : $message;

        if (isset($CONFIG->appLogger) && $CONFIG->appLogger) {
            $CONFIG->appLogger->error($msg);
        } elseif (isset($CONFIG->logger) && $CONFIG->logger){
            $CONFIG->logger->error($msg);
        }
    }

    /**
     * Parse level string to LoggerLevel value
     *
     * @param string $strLevel
     * @return int
     */
    public static function parseLoggerLevel($strLevel)
    {
        $level = LoggerLevel::ALL;
        if (isset($strLevel)) {
            $loggerLevel = strtoupper($strLevel);
            $level = isset(static::$levelMap[$loggerLevel]) ? static::$levelMap[$loggerLevel] : LoggerLevel::OFF;
        }

        return $level;
    }

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
            global $CONFIG;
            return md5($pwd . $CONFIG->passwdkey);
        }
    }

    /**
     * @param int $len
     * @param string $type
     * @return string
     */
    public static function random($len=6, $type='mix')
    {
        $len = intval($len);
        if($len >90) $len = 90;
        switch ($type) {
            case 'int':
                $templet = '012345678901234567890123456789012345678901234567890123456789012345678901234567890123456789';
                break;
            case 'lowchar':
                $templet = 'abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijkl';
                break;
            case 'upchar':
                $templet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKL';
                break;
            case 'char':
                $templet = 'abcdefghijklmnopqrstuvwxyz0123456789abcdefghijklmnopqrstuvwxyzamwz0379bhklqdklg482156smyew';
                break;
            default:
                $templet = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ~!@#$%^&*()_+-=[]{}:";<>,.?|';
                break;
        }
        $start = mt_rand(1, (90-$len));
        $string = str_shuffle($templet);

        return substr($string,$start,$len);
    }

    /**
     * Get client ip.
     *
     * @return null|string
     */
    public static function getIp(&$addr)
    {
        if(getenv('HTTP_CLIENT_IP')){
            $onlineip = getenv('HTTP_CLIENT_IP');
        } elseif(getenv('HTTP_X_FORWARDED_FOR')){
            $onlineip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif(getenv('REMOTE_ADDR')){
            $onlineip = getenv('REMOTE_ADDR');
        } else{
            $onlineip = null;
        }

        if ($onlineip) {
            $addr = static::convertip($onlineip);
        }

        return $onlineip;
    }

    protected static function convertip($ip) {
        //IP数据文件路径
        $dat_path = dirname(__FILE__) . DS . 'qqwry.dat';
        //检查IP地址
        //if(!preg_match("/^d{1,3}.d{1,3}.d{1,3}.d{1,3}$/", $ip)) {
        //    return 'IP Address Error';
        //}
        //打开IP数据文件
        if(!$fd = @fopen($dat_path, 'rb')){
            return 'IP date file not exists or access denied';
        }
        //分解IP进行运算，得出整形数
        $ip = explode('.', $ip);
        $ipNum = $ip[0] * 16777216 + $ip[1] * 65536 + $ip[2] * 256 + $ip[3];
        //获取IP数据索引开始和结束位置
        $DataBegin = fread($fd, 4);
        $DataEnd = fread($fd, 4);
        $ipbegin = implode('', unpack('L', $DataBegin));
        if($ipbegin < 0) $ipbegin += pow(2, 32);
        $ipend = implode('', unpack('L', $DataEnd));
        if($ipend < 0) $ipend += pow(2, 32);
        $ipAllNum = ($ipend - $ipbegin) / 7 + 1;
        $BeginNum = 0;
        $EndNum = $ipAllNum;

        $ip1num = 0;
        $ip2num = 0;

        $ipAddr1 = '';
        $ipAddr2 = '';

        //使用二分查找法从索引记录中搜索匹配的IP记录
        while($ip1num>$ipNum || $ip2num<$ipNum) {
            $Middle= intval(($EndNum + $BeginNum) / 2);
            //偏移指针到索引位置读取4个字节
            fseek($fd, $ipbegin + 7 * $Middle);
            $ipData1 = fread($fd, 4);
            if(strlen($ipData1) < 4) {
                fclose($fd);
                return 'System Error';
            }
            //提取出来的数据转换成长整形，如果数据是负数则加上2的32次幂
            $ip1num = implode('', unpack('L', $ipData1));
            if($ip1num < 0) $ip1num += pow(2, 32);
            //提取的长整型数大于我们IP地址则修改结束位置进行下一次循环
            if($ip1num > $ipNum) {
                $EndNum = $Middle;
                continue;
            }
            //取完上一个索引后取下一个索引
            $DataSeek = fread($fd, 3);
            if(strlen($DataSeek) < 3) {
                fclose($fd);
                return 'System Error';
            }
            $DataSeek = implode('', unpack('L', $DataSeek.chr(0)));
            fseek($fd, $DataSeek);
            $ipData2 = fread($fd, 4);
            if(strlen($ipData2) < 4) {
                fclose($fd);
                return 'System Error';
            }
            $ip2num = implode('', unpack('L', $ipData2));
            if($ip2num < 0) $ip2num += pow(2, 32);
            //没找到提示未知
            if($ip2num < $ipNum) {
                if($Middle == $BeginNum) {
                    fclose($fd);
                    return 'Unknown';
                }
                $BeginNum = $Middle;
            }
        }
        $ipFlag = fread($fd, 1);
        if($ipFlag == chr(1)) {
            $ipSeek = fread($fd, 3);
            if(strlen($ipSeek) < 3) {
                fclose($fd);
                return 'System Error';
            }
            $ipSeek = implode('', unpack('L', $ipSeek.chr(0)));
            fseek($fd, $ipSeek);
            $ipFlag = fread($fd, 1);
        }
        if($ipFlag == chr(2)) {
            $AddrSeek = fread($fd, 3);
            if(strlen($AddrSeek) < 3) {
                fclose($fd);
                return 'System Error';
            }
            $ipFlag = fread($fd, 1);
            if($ipFlag == chr(2)) {
                $AddrSeek2 = fread($fd, 3);
                if(strlen($AddrSeek2) < 3) {
                    fclose($fd);
                    return 'System Error';
                }
                $AddrSeek2 = implode('', unpack('L', $AddrSeek2.chr(0)));
                fseek($fd, $AddrSeek2);
            } else {
                fseek($fd, -1, SEEK_CUR);
            }
            while(($char = fread($fd, 1)) != chr(0))
                $ipAddr2 .= $char;
            $AddrSeek = implode('', unpack('L', $AddrSeek.chr(0)));
            fseek($fd, $AddrSeek);
            while(($char = fread($fd, 1)) != chr(0))
                $ipAddr1 .= $char;
        } else {
            fseek($fd, -1, SEEK_CUR);
            while(($char = fread($fd, 1)) != chr(0))
                $ipAddr1 .= $char;
            $ipFlag = fread($fd, 1);
            if($ipFlag == chr(2)) {
                $AddrSeek2 = fread($fd, 3);
                if(strlen($AddrSeek2) < 3) {
                    fclose($fd);
                    return 'System Error';
                }
                $AddrSeek2 = implode('', unpack('L', $AddrSeek2.chr(0)));
                fseek($fd, $AddrSeek2);
            } else {
                fseek($fd, -1, SEEK_CUR);
            }
            while(($char = fread($fd, 1)) != chr(0)){
                $ipAddr2 .= $char;
            }
        }
        fclose($fd);
        //最后做相应的替换操作后返回结果
        if(preg_match('/http/i', $ipAddr2)) {
            $ipAddr2 = '';
        }
        $ipaddr = "$ipAddr1 $ipAddr2";
        $ipaddr = preg_replace('/CZ88.Net/is', '', $ipaddr);
        $ipaddr = preg_replace('/^s*/is', '', $ipaddr);
        $ipaddr = preg_replace('/s*$/is', '', $ipaddr);
        if(preg_match('/http/i', $ipaddr) || $ipaddr == '') {
            $ipaddr = 'Unknown';
        }
        $ipaddr = iconv('gbk', 'utf-8//IGNORE', $ipaddr); //转换编码，如果网页的gbk可以删除此行
        return $ipaddr;
    }
    /**
     * Get client browser type.
     *
     * @param string $version
     * @return string
     */
    public static function getBrowser(&$version){
        $browser = 'unknown';
        $agent = getenv('HTTP_USER_AGENT');
        if (!$agent) {
            return $browser;
        }

        if (stripos($agent, "Firefox/") > 0) {
            preg_match("/Firefox\/([^;)]+)+/i", $agent, $b);
            $exp[0] = "Firefox";
            $exp[1] = $b[1];  //获取火狐浏览器的版本号
        } elseif (stripos($agent, "Maxthon") > 0) {
            preg_match("/Maxthon\/([\d\.]+)/", $agent, $aoyou);
            $exp[0] = "Maxthon";
            $exp[1] = $aoyou[1];
        } elseif (stripos($agent, "MSIE") > 0) {
            preg_match("/MSIE\s+([^;)]+)+/i", $agent, $ie);
            $exp[0] = "IE";
            $exp[1] = $ie[1];  //获取IE的版本号
        } elseif (stripos($agent, "OPR") > 0) {
            preg_match("/OPR\/([\d\.]+)/", $agent, $opera);
            $exp[0] = "Opera";
            $exp[1] = $opera[1];
        } elseif(stripos($agent, "Edge") > 0) {
            //win10 Edge浏览器 添加了chrome内核标记 在判断Chrome之前匹配
            preg_match("/Edge\/([\d\.]+)/", $agent, $Edge);
            $exp[0] = "Edge";
            $exp[1] = $Edge[1];
        } elseif (stripos($agent, "Chrome") > 0) {
            preg_match("/Chrome\/([\d\.]+)/", $agent, $google);
            $exp[0] = "Chrome";
            $exp[1] = $google[1];  //获取google chrome的版本号
        } elseif(stripos($agent,'rv:')>0 && stripos($agent,'Gecko')>0){
            preg_match("/rv:([\d\.]+)/", $agent, $IE);
            $exp[0] = "IE";
            $exp[1] = $IE[1];
        } elseif(stripos($agent,'Safari') > 0 && stripos($agent,'AppleWebKit') > 0){
            preg_match("/Version\/([\d\.]+)/", $agent, $safari);
            $exp[0] = "Safari";
            $exp[1] = $safari[1];
        } elseif(stripos($agent, 'MicroMessenger') !== false) {
            preg_match("/MicroMessenger\/([\d\.]+)/", $agent, $wechat);
            $exp[0] = "WeChat";
            $exp[1] = $wechat[1];
        } else {
            $exp[0] = "unknown";
            $exp[1] = "";
        }

        $browser = $exp[0];
        $version = $exp[1];

        return $browser;
    }

    /**
     * Get client OS.
     *
     * @return string
     */
    public static function getOS(){
        $os = 'unknown';

        $agent = getenv('HTTP_USER_AGENT');
        if (!$agent) {
            return $os;
        }

        if (preg_match('/win/i', $agent) && strpos($agent, '95'))
        {
            $os = 'Windows 95';
        }
        else if (preg_match('/win 9x/i', $agent) && strpos($agent, '4.90'))
        {
            $os = 'Windows ME';
        }
        else if (preg_match('/win/i', $agent) && preg_match('/98/i', $agent))
        {
            $os = 'Windows 98';
        }
        else if (preg_match('/win/i', $agent) && preg_match('/nt 6.0/i', $agent))
        {
            $os = 'Windows Vista';
        }
        else if (preg_match('/win/i', $agent) && preg_match('/nt 6.1/i', $agent))
        {
            $os = 'Windows 7';
        }
        else if (preg_match('/win/i', $agent) && preg_match('/nt 6.2/i', $agent))
        {
            $os = 'Windows 8';
        }else if(preg_match('/win/i', $agent) && preg_match('/nt 10.0/i', $agent))
        {
            $os = 'Windows 10';#添加win10判断
        }else if (preg_match('/win/i', $agent) && preg_match('/nt 5.1/i', $agent))
        {
            $os = 'Windows XP';
        }
        else if (preg_match('/win/i', $agent) && preg_match('/nt 5/i', $agent))
        {
            $os = 'Windows 2000';
        }
        else if (preg_match('/win/i', $agent) && preg_match('/nt/i', $agent))
        {
            $os = 'Windows NT';
        }
        else if (preg_match('/win/i', $agent) && preg_match('/32/i', $agent))
        {
            $os = 'Windows 32';
        }
        else if (preg_match('/iPhone/i', $agent) || preg_match('/iPad/i', $agent))
        {
            $os = 'iOS';
        }
        else if (preg_match('/android/i', $agent))
        {
            $os = 'Android';
        }
        else if (preg_match('/linux/i', $agent))
        {
            $os = 'Linux';
        }
        else if (preg_match('/unix/i', $agent))
        {
            $os = 'Unix';
        }
        else if (preg_match('/sun/i', $agent) && preg_match('/os/i', $agent))
        {
            $os = 'SunOS';
        }
        else if (preg_match('/ibm/i', $agent) && preg_match('/os/i', $agent))
        {
            $os = 'IBM OS/2';
        }
        else if (preg_match('/Mac/i', $agent) && preg_match('/PC/i', $agent) || preg_match('/Macintosh/i', $agent))
        {
            $os = 'Macintosh';
        }
        else if (preg_match('/PowerPC/i', $agent))
        {
            $os = 'PowerPC';
        }
        else if (preg_match('/AIX/i', $agent))
        {
            $os = 'AIX';
        }
        else if (preg_match('/HPUX/i', $agent))
        {
            $os = 'HPUX';
        }
        else if (preg_match('/NetBSD/i', $agent))
        {
            $os = 'NetBSD';
        }
        else if (preg_match('/BSD/i', $agent))
        {
            $os = 'BSD';
        }
        else if (preg_match('/OSF1/i', $agent))
        {
            $os = 'OSF1';
        }
        else if (preg_match('/IRIX/i', $agent))
        {
            $os = 'IRIX';
        }
        else if (preg_match('/FreeBSD/i', $agent))
        {
            $os = 'FreeBSD';
        }
        else if (preg_match('/teleport/i', $agent))
        {
            $os = 'teleport';
        }
        else if (preg_match('/flashget/i', $agent))
        {
            $os = 'flashget';
        }
        else if (preg_match('/webzip/i', $agent))
        {
            $os = 'webzip';
        }
        else if (preg_match('/offline/i', $agent))
        {
            $os = 'offline';
        }
        else
        {
            $os = 'unknown';
        }

        return $os;
    }

    public static function generateMicroServiceHeaders($service, $source = null)
    {
        global $CONFIG;

        if (!$source) {
            $env = new Environment($_SERVER);
            $scheme = (isset($_SERVER['HTTPS']) ? 'https' : 'http');
            $host = $env->get('HTTP_HOST');
            //Tools::log('[generateMicroServiceHeaders] host: ' . $host);

            // If exists, use original source
            if ($env->get('HTTP_TALENTYUN_SOURCE') && $env->get('HTTP_TALENTYUN_SOURCE') == 'admin') {
                $source = 'admin';
            } else {
                foreach ($CONFIG->remoteServices as $key => $svc) {
                    if (strpos($svc, $scheme . '://' . $host) === 0) {
                        $source = $key;
                        break;
                    }
                }
            }
        }

        if (isset($CONFIG->appIds[$service]) && isset($CONFIG->appIds[$source])) {

            $salt = uniqid('TY') . '.' . time();

            return array(
                'Talentyun-Service: ' . $service,
                'Talentyun-Source: ' . $source,
                'Talentyun-Salt: ' . $salt,
                'Talentyun-Token: ' . sha1($CONFIG->appIds[$service] . $CONFIG->appIds[$source] . $salt)
            );
        } else {
            static::log(sprintf('[Tools::generateMicroServiceHeaders] No App ID found! Service: %s, Source: %s ', $service, $source));
        }

        return array();
    }

    /**
     * Check if the name is safe for MongoDB script.
     * @param string $name field name
     * @return bool
     */
    public static function checkIsSafeStr($name)
    {
        return !in_array(strtolower($name), static::$banStrings);
    }

    /**
     * Check if HTTPS connection
     *
     * @return bool
     */
    public static function isHttps()
    {
        if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') {
            return true;
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        } elseif (isset($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off') {
            return true;
        }

        return false;
    }
}