<?php

define("JAVA_HOSTS", "127.0.0.1:8080");//此处端口与第五步的端口对应

define("JAVA_LOG_LEVEL", 2);

require_once "../Java.inc";//引入Java.inc

java_set_file_encoding("UTF-8");

try {
    $props = java("java.lang.System")->getProperties();
    $array = java_values($props);
    foreach($array as $k=>$v) {
        //echo "$k=>$v"; echo "<br>\n";
    }

} catch (JavaException $ex) {
    echo "An exception occured: ";
    echo $ex;
    echo "<br>\n";

}
$data = '';




$javaObcect = new Java('com.wanxiang.DESUtil');

$ss = $javaObcect->encrypt('sG18Yy6v01ZoNq9x1hk1Vg==','sG18Yy6v01ZoNq9x1hk1Vg=='); //加密
//$ss = $javaObcect->decrypt($pwd, $text); //解密

var_dump($ss->__toString());




