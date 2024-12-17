<?php
if (!function_exists('startsWith')) {
    function startsWith($haystack, $needle) {
        //return substr_compare ( $haystack , $needle , 0 , strlen ( $needle ) ) === 0 ;
        return (substr($haystack, 0, strlen($needle)) === $needle);
    }
}
if (!function_exists('endsWith')) {
    function endsWith($haystack, $needle) {
        //return substr_compare( $haystack, $needle, -strlen($needle), strlen($needle)) === 0;
        return substr($haystack, -strlen($needle)) === $needle;
    }
}
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}
if (!function_exists('env')) {
    /**
     * Gets the value of an environment variable.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function env($key, $default = null)
    {
        //$value = getenv(CURR_APP_ID . $key);
        $value = isset($_ENV[$key]) ? $_ENV[$key] : false;
        if ($value === false) {
            return $default;
        }

        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return;
        }

        if (strlen($value) > 1 && startsWith($value, '"') && endsWith($value, '"')) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}
if (! function_exists('tap')) {
    /**
     * Call the given Closure with the given value then return the value.
     *
     * @param  mixed  $value
     * @param  callable|null  $callback
     * @return mixed
     */
    function tap($value, $callback = null)
    {

        if (is_callable($callback)) {
            $callback($value);
        }

        return $value;
    }
}

if (! function_exists('ArrayGet')) {
    /**
     * @param $array
     * @param string|string[] $key Array key name or key path (for example: `a.b.c`)
     * @param mixed $defaultValue Return the default value when key not found or value of key in array is null
     * @param string $glue The separator character in the key, default is `.`
     * @param bool $mul If true, may return multiple values by given key
     *  for example
     *  the array: ['val' => [['a' => 1, 'b' => 2], ['a' => 3, 'b' => 4]]], 
     *  given key: 'val.a'
     *  expected result: [1, 3]
     *  ONLY for standard array
     * @return mixed
     */
    function ArrayGet($array, $key, $defaultValue = null, $glue = '.', $mul = false) {
        if (!is_array($array)) {
            return $defaultValue;
        }
    
        if ($key === null) {
            return $array;
        }
    
        $parents = $key;
        if (!is_array($parents)) {
            $parents = explode($glue, $key);
        }
        $ref = &$array;
        foreach ((array) $parents as $parent) {
            if (is_array($ref) && array_key_exists($parent, $ref)) {
                $ref = &$ref[$parent];
            }
            else {
                if ($mul) {
                    $ref = array_column($ref, $parent);
                }
                else {
                    $ref = null;
                }
            }
            if ($ref === null) {
                break;
            }
        }

        return $ref === null ? $defaultValue : $ref;
    }
}

if (! function_exists('hmacEncode')) {
    function hmacEncode($sid, $secret) {
        return str_replace('=', '', base64_encode(hash_hmac('sha256', $sid, $secret, true)));
    }
}

if (! function_exists('hmacEncodeV2')) {
    function hmacEncodeV2($sid, $secret) {
        return hash_hmac('sha256', $sid, $secret);
    }
}

if (! function_exists('hmacCheck')) {

    function hmacCheck($token, $secret, &$sid) {
        $token = str_replace(' ', '+', $token);
        if (substr($token, 0, 2) === 's:') {
            $token = substr($token, 2);
        }

        $dot_pos = strpos($token, '.');
        if ($dot_pos !== false) {

            $sections = explode('.', $token);
            if (count($sections) == 2) {
                $hmac_in = substr($token, $dot_pos + 1);

                $token = substr($token, 0, $dot_pos);

                $hmac_calc = hmacEncode($token, $secret); // str_replace('=', '', base64_encode(hash_hmac('sha256', $token, $secret, true)));

                if ($hmac_calc === $hmac_in) {
                    $sid  = $token;
                    return true;
                }
            }
            elseif (count($sections) == 3) {
                $token = $sections[0];
                $hmac_in = $sections[1];
                $ver = $sections[2]; // v1
                if ($ver == 'v1') {
                    $hmac_calc = hmacEncodeV2($token, $secret);
                }
                elseif ($ver == 'v2') {
                    $hmac_calc = bin2hex(openssl_encrypt($token, 'aes-128-cbc', $secret, OPENSSL_PKCS1_PADDING, env('LOGIN_TOKEN_ENCRYPT_IV', '75QWyIwYusltSfaD')));
                }
                if (strcmp($hmac_calc, $hmac_in) === 0) {
                    $sid = $token;
                    return true;
                }
            }
            elseif (count($sections) == 4) {
                $token = $sections[0];
                $hmac_in = $sections[1];
                $hmac_calc = hmacEncode($token, $secret); // str_replace('=', '', base64_encode(hash_hmac('sha256', $token, $secret, true)));
                if ($hmac_calc === $hmac_in) {
                    $ts = $sections[2];
                    $hmac_in2 = $sections[3];
                    $hmac_calc = hmacEncode($ts, $hmac_in); // str_replace('=', '', base64_encode(hash_hmac('sha256', $ts, $hmac_in, true)));
                    if ($hmac_calc === $hmac_in2) {
                        $sid  = $token;
                        return true;
                    } else {
                        error_log('Invalid ts');
                    }
                } else {
                    error_log('Invalid sid');
                }
            }
        }

        return false;
    }

}

if (! function_exists('generateHmac')) {
    function generateHmac($sid, $secret, $version = null) {
        // $hmac_calc = str_replace('=', '', base64_encode(hash_hmac('sha256', $sid, $secret, true)));

        // return 's:' . $secret . '.' . $hmac_calc;

        $encodeVersion = env('APP_AUTH_TOKEN_ENCODE_VERSION', $version);
        if ($encodeVersion == 'v1') {
            $encoded = hmacEncodeV2($sid, $secret);
        }
        elseif ($encodeVersion == 'v2') {
            $encoded = bin2hex(openssl_encrypt($sid, 'aes-128-cbc', $secret, OPENSSL_PKCS1_PADDING, env('LOGIN_TOKEN_ENCRYPT_IV', '75QWyIwYusltSfaD')));
        }
        else {
            $encoded = str_replace('=', '', base64_encode(hash_hmac('sha256', $sid, $secret, true)));
        }
        return 's:' . $sid . '.' . $encoded . ($encodeVersion ? '.' . $encodeVersion : '');
    }
}

function isWeixin(){
    if ( strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ) {
        return true;
    }
    return false;
}

/**
 * @param int $len Max = 160
 * @param string $type
 * @return string
 */
function randomChars($len = 6, $type ='mix')
{
    $maxlen = 160;
    $len = intval($len);
    if($len > $maxlen) $len = $maxlen;
    switch ($type) {
        case 'int':
            $template = '0123456789012345678901234567890123456789012345678901234567890123456789012345678901234567899012345678901234567890123456789012345678990123456789012345678901234567';
            break;
        case 'lowchar':
            $template = 'abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklwxyzabcdefghijklmnopqrstuvwxyzabcdefghwxyzabcdefghijklmnopqrstuvwxyzab';
            break;
        case 'upchar':
            $template = 'ABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLWXYZABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMNOPQRSTUVWXYZABCDEFGHIJKLMN';
            break;
        case 'char':
            $template = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789MNOPQRSTUVWXYZabcdefghijk0123456789A';
            break;
        default:
            $template = 'abcdefghijklmnopqrstuvwxyz0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ~!@#$%^&*()_+-=[]{}:";<>,.?|abcdefghirtuvwxyz0123789ABCDFGHPQRSTUVWXYZ~!@#$%^&*()_+-=[]{}:";<>,.?|';
            break;
    }
    $start = mt_rand(1, ($maxlen - $len));
    $string = str_shuffle($template);

    return substr($string, $start, $len);
}

function aesEncrypt($plaintext, $cipher = 'AES-128-CBC', $key = null)
{
    $key = $key ?: env('AES_ENCRYPT_KEY', 'x' . '~HyeC4XoFOl^DuV');
    $encrypted = aesEncryptSimple($plaintext, $cipher, $key, $iv);
    $ciphertextRaw = base64_decode($encrypted);
    $hmac = hash_hmac('sha256', $ciphertextRaw, $key, true);
    return base64_encode($iv . $hmac . $ciphertextRaw);
}   

function aesEncryptSimple($plaintext, $cipher = 'AES-128-CBC', $key = null, &$iv = null)
{
    $key = $key ?: env('AES_ENCRYPT_KEY', 'x' . '~HyeC4XoFOl^DuV');
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $ciphertextRaw = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_PKCS1_PADDING, $iv);
    return base64_encode($ciphertextRaw);
}

function aesDecrypt($plaintext, $cipher = 'AES-128-CBC', $key = null)
{
    // AES-128-CBC  ->  16 bit
    $key = $key ?: env('AES_ENCRYPT_KEY', 'x' . '~HyeC4XoFOl^DuV');
    $c = base64_decode($plaintext);
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = substr($c, 0, $ivlen);
    $hmac = substr($c, $ivlen, $sha2len = 32);
    $ciphertextRaw = substr($c, $ivlen + $sha2len);
    $originalPlaintext = openssl_decrypt($ciphertextRaw, $cipher, $key, OPENSSL_PKCS1_PADDING, $iv);
    $calcmac = hash_hmac('sha256', $ciphertextRaw, $key, true);
    if (hash_equals($hmac, $calcmac))  {
        return $originalPlaintext;
    }
    return false;
}

function jsonAesEncrypt($plaintext, $cipher = 'AES-128-CBC', $key = null, $iv = null)
{
    $key = $key ?: env('AES_ENCRYPT_KEY', 'x' . '~HyeC4XoFOl^DuV');
    $iv = $iv ?: env('AES_ENCRYPT_IV', 'Yl#T^a6zJfYFRsqJ');
    $ciphertext_raw = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    $hmac = hash_hmac('sha256', $ciphertext_raw, $key, true);
    return base64_encode(';' . json_encode(['iv' => base64_encode($iv), 'hmac' => base64_encode($hmac), 'raw' => base64_encode($ciphertext_raw)]));
}
function jsonAesDecrypt($plaintext, $cipher = 'AES-128-CBC', $key = null, $iv = null)
{
    $key = $key ?: env('AES_ENCRYPT_KEY', 'x' . '~HyeC4XoFOl^DuV');
    $c = base64_decode($plaintext);
    $json = json_decode(ltrim($c, ';'), true);
    if ($json) {
        $iv = $iv ?: base64_decode($json['iv']);
        $hmac = base64_decode($json['hmac']);
        $ciphertextRaw = base64_decode($json['raw']);
        $originalPlaintext = openssl_decrypt($ciphertextRaw, $cipher, $key, OPENSSL_RAW_DATA, $iv);
        $calcmac = hash_hmac('sha256', $ciphertextRaw, $key, true);
        if (hash_equals($hmac, $calcmac))  {
            return $originalPlaintext;
        }
    }
    return false;
}

function sanxiaAesDecrypt($plaintext, $delimiter = null, $key = null, $iv = null, $cipher = 'AES-128-CBC')
{
    $key = $key ?: env('AES_ENCRYPT_SANXIA_KEY', 'x' . '~HyeC4XoFOl^DuV');
    $iv = $iv ?: env('AES_ENCRYPT_SANXIA_IV', 'aaaaaaaaaaaaaaaa');
    $delimiter = $delimiter ?: env('AES_ENCRYPT_SANXIA_DELIMITER', '12345678901234567890123456789012');
    $c = base64_decode($plaintext);
    if ($c) {
        $ciphertextRaw = substr($c, strlen($iv . $delimiter));
        return openssl_decrypt(base64_decode($ciphertextRaw), $cipher, $key, OPENSSL_RAW_DATA, $iv);
    }
    return false;
}


function strToBin($str)
{
    $arr = preg_split('/(?<!^)(?!$)/u', $str);
    foreach($arr as &$v){
        $temp = unpack('H*', $v);
        $v = base_convert($temp[1], 16, 2);
        unset($temp);
    }
    return join('',$arr);
}

/**
 * Recursive diff the arrays.
 *
 * @param array|null $aArray1
 * @param array|null $aArray2
 * @param bool $strict If false, ignore the empty value and the not exist key is equal
 * @return array|null
 */
function arrayRecursiveDiff($aArray1, $aArray2, $strict = true) {
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
                $aRecursiveDiff = arrayRecursiveDiff($mValue, $aArray2[$mKey]);
                if (count($aRecursiveDiff)) { $aReturn[$mKey] = $aRecursiveDiff; }
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

function sameArrays($array1, $array2)
{
    $tmp = arrayRecursiveDiff($array1, $array2);
    if ($tmp) {
        return false;
    }
    $tmp = arrayRecursiveDiff($array2, $array1);
    if ($tmp) {
        return false;
    }
    return true;
}

if (!function_exists('dd')) {
    /**
     * 参数打印
     * @param ...$params
     * @return void
     */
    function dd(...$params)
    {
        foreach ($params as $param) {
            highlight_string(var_export($param, true) . PHP_EOL . '---------------------------' . PHP_EOL);
        }
        die;
    }
}

function get_request_ip()
{
    $ip = FALSE;
    //客户端IP 或 NONE
    if(!empty($_SERVER['HTTP_CLIENT_IP'])){
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }

    //多重代理服务器下的客户端真实IP地址（可能伪造）,如果没有使用代理，此字段为空
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode (', ', $_SERVER['HTTP_X_FORWARDED_FOR']);
        if ($ip) { array_unshift($ips, $ip); $ip = FALSE; }
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

function get_module_class_with_loader_no_ref($className, $container, $prefix = '\\App\\Models\\', $loadAppModule = null)
{
    return get_module_class_with_loader($className, $container, $prefix, $loadAppModule);
}

/**
 * Get class of the app module.
 *
 * @param string $className Classname loading
 * @param \Key\Container $container
 * @param string $prefix Class namespace prefix, default: \App\Models\
 * @param string $loadAppModule Special app module name, if better performance that it should be set
 * @return closure|bool
 */
function get_module_class_with_loader(&$className, $container, $prefix = '\\App\\Models\\', $loadAppModule = null)
{
    $originClassName = $className;
    $className = $prefix . ucfirst($className);
    if (!class_exists($className)) {
        $currModelName = $container->offsetExists('current_module') ? $container['current_module']->getName() : null;
        // file_put_contents('/tmp/get_module_class_with_loader.log', 'current model name: ' . $currModelName . PHP_EOL, FILE_APPEND);
        $found = false;
        if ($container->offsetExists('modules')) {
            $modules = $container['modules'];
            if ($loadAppModule && $loadAppModule != $currModelName && isset($modules[$loadAppModule])) {
                $modules[$loadAppModule]->registerClasses($container);
                if (class_exists($className)) {
                    // error_log('1load module from app module: ' . $modules[$loadAppModule]->getName());
                    $found = true;
                }
                else {
                    $classLoaderDef = $modules[$loadAppModule]->getClassLoaderDef();
                    if ($classLoaderDef) {
                        foreach ($classLoaderDef as $def) {
                            $newClassName = $def['ns'] . 'Models\\'. ucfirst($originClassName);
                            if (class_exists($newClassName)) {
                                // error_log('2load module from app module: ' . $modules[$loadAppModule]->getName());
                                $className = $newClassName;
                                $found = true;
                                break;
                            }
                        }
                    }

                }
            }
            else {
                // try to load all modules classes to find special model
                foreach ($modules as $module) {
                    // file_put_contents('/tmp/get_module_class_with_loader.log', 'model name: ' . $module->getName() . PHP_EOL, FILE_APPEND);
                    if ($module->getName() != $currModelName) {
                        $module->registerClasses($container);
                        if (class_exists($className)) {
                            // error_log('3load module from app module: ' . $module->getName());
                            $found = true;
                            break;
                        }
                    }
                    else {
                        $classLoaderDef = $module->getClassLoaderDef();
                        if ($classLoaderDef) {
                            foreach ($classLoaderDef as $def) {
                                $newClassName = $def['ns'] . 'Models\\' . ucfirst($originClassName);
                                // file_put_contents('/tmp/get_module_class_with_loader.log', 'new class name: ' . $newClassName . PHP_EOL, FILE_APPEND);
                                if (class_exists($newClassName)) {
                                    // error_log('4load module from app module: ' . $module->getName());
                                    $className = $newClassName;
                                    $found = true;
                                    break 2;
                                }
                            }
                        }
    
                    }
                }
            }
        }
        if (!$found) {
            return false;
        }
    }
    return function($params) use($className) {
        return new $className($params);
    };
}

/**
 * Use filtered string as filename.
 * Filter some special characters.
 *
 * @param string $str
 * @param string $replacedChar
 * @return string
 */
function filter_string_to_filename($str, $replacedChar = '_')
{
    return str_replace(['/', '\\', ':', '*', '"', '<', '>', '|', '?', '%', '!', '\''], $replacedChar, $str);
}

/**
 * Make dot key splited to array attribute.
 * For example: ['a.b.c' => 1] ==> ['a' => ['b' => ['c' => 1]]]
 * 
 * @param array $props
 * @return void
 */
function dot_key_to_array(&$props = [])
{
    foreach ($props as $key => $val) {
        if (strpos($key, '.') !== false) {
            $pieces = explode('.', $key);
            $pieces = array_reverse($pieces);
            $ref = [];
            foreach ($pieces as $idx => $piece) {
                if ($idx === 0) {
                    $ref[$piece] = $val;
                }
                else {
                    $ref[$piece] = $ref;
                    unset($ref[$pieces[$idx - 1]]);
                }
            }
    
            $props = array_merge_recursive($props, $ref);
            unset($props[$key]);
        }
    }
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
function array_merge_recursive_distinct ( array &$array1, array &$array2 )
{
  $merged = $array1;

  foreach ( $array2 as $key => &$value )
  {
    if ( is_array ( $value ) && isset ( $merged [$key] ) && is_array ( $merged [$key] ) )
    {
      $merged [$key] = array_merge_recursive_distinct ( $merged [$key], $value );
    }
    else
    {
      $merged [$key] = $value;
    }
  }

  return $merged;
}

/**
 * A more inuitive way of sorting multidimensional arrays using array_msort() in just one line, you don't have to divide the original array into per-column-arrays.
 * Sample: 
 *    $arr2 = array_msort($arr1, array('name'=>SORT_DESC, 'cat'=>SORT_ASC));
 * 
 * @link https://www.php.net/manual/zh/function.array-multisort.php#91638
 * @param array $array
 * @param array $cols
 * @return array Sorted array
 */
function array_msort($array, $cols)
{
    $colarr = array();
    foreach ($cols as $col => $order) {
        $colarr[$col] = array();
        foreach ($array as $k => $row) { $colarr[$col]['_'.$k] = strtolower($row[$col]); }
    }
    $eval = 'array_multisort(';
    foreach ($cols as $col => $order) {
        $eval .= '$colarr[\''.$col.'\'],'.$order.',';
    }
    $eval = substr($eval,0,-1).');';
    eval($eval);
    $ret = array();
    foreach ($colarr as $col => $arr) {
        foreach ($arr as $k => $v) {
            $k = substr($k,1);
            if (!isset($ret[$k])) $ret[$k] = $array[$k];
            $ret[$k][$col] = $array[$k][$col];
        }
    }
    return $ret;

}

if (!function_exists('object_to_array')) {
    /**
     * Convert a object to an array.
     * 
     * @param mixed $obj
     * @return array
     */
    function object_to_array($obj)
    {
        $_arr = is_object($obj) ? get_object_vars($obj) : $obj;
        $arr = [];
        if (is_array($_arr)) {
            foreach ($_arr as $key => $val) {
                $val = (is_array($val)) || is_object($val) ? object_to_array($val) : $val;
                $arr[$key] = $val;
            }
        }
        return $arr;
    }
}

/**
 * 
 * @link https://www.php.net/manual/zh/function.apache-response-headers.php#91174
 */
if (!function_exists('apache_response_headers')) {
    function apache_response_headers () {
        $arh = array();
        $headers = headers_list();
        foreach ($headers as $header) {
            $header = explode(":", $header);
            $arh[array_shift($header)] = trim(implode(":", $header));
        }
        return $arh;
    }
}

if (!function_exists('create_uuid')) {
    /**
     * 唯一随机数uuid，用于请求的防重放攻击，每次请求唯一，不能重复使用。
     * 格式为A-B-C-D-E（A、B、C、D、E的字符位数分别为8、4、4、4、12）。
     * 例如，8d1e6a7a-f44e-40d5-aedb-fe4a1c80f434
     */
    function create_uuid($hyphen = null) {
        $uuid = '';
        if (function_exists('com_create_guid')) {
            $uuid = com_create_guid();
        }
        else {
            mt_srand((double) microtime() * 10000);
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = is_null($hyphen) ? chr(45) : $hyphen; // '-'
            $uuid .= substr($charid, 0, 8) . $hyphen
                . substr($charid, 8, 4) . $hyphen
                . substr($charid, 12, 4) . $hyphen
                . substr($charid, 16, 4) . $hyphen
                . substr($charid, 20, 12);
        }
        return $uuid;
    }
}

if (!function_exists('base64UrlEncode')) {
    function base64UrlEncode($str)
    {
        return rtrim(strtr(base64_encode($str), '+/', '-_'), '=');
    }
}

if (!function_exists('base64UrlDecode')) {
    function base64UrlDecode($data)
    {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
}

if (!function_exists('filterFilenameCharacters')) {
    /**
     * 文件名特殊字符过滤
     * @param string $filename 文件名,不带后缀
     * @return array|string|string[]
     */
    function filterFilenameCharacters($filename)
    {
        return str_replace(['/', '\\', ':', '*', '"', '<', '>', '|', '?', '+', '.', ' '], '_', $filename);
    }

    /**
 * 对数组进行分组聚合
 * @param $array
 * @param $keys
 * @return $result
 */
function array_group_by($array, $keys)
{
    if(!is_array($keys) || count($keys) == 1)
    {
        $key = is_array($keys) ? array_shift($keys) : $keys;
        return array_reduce($array, function($tmp_result, $item) use ($key)
        {
            $tmp_result[$item[$key]][] = $item;
            return $tmp_result;
        });
    }
    else
    {
        $keys = array_values($keys);
        $result = array_group_by($array, array_shift($keys));
        foreach ($result as $k=>$value)
        {
            $result[$k] = array_group_by($value, $keys);
        }
        return $result;
    }
}
}