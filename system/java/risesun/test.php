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

$pwd = 'RISESUN';
$text = 'Avd3Eu3jwYk=aV7N6I581P3tace5MfyeSQ==';
$text = 'sysadmin';

$pwd = new Java('java.lang.String', $pwd);
$text = new Java('java.lang.String', $text);

$javaObcect = new Java('weaver.general.SecurityHelper');

$ss = $javaObcect->encrypt($pwd, $text); //加密
//$ss = $javaObcect->decrypt($pwd, $text); //解密

var_dump($ss->__toString());




