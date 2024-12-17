<?php

define("JAVA_HOSTS", "127.0.0.1:8080");

define("JAVA_LOG_LEVEL", 2);

require_once "../Java.inc";//引入Java.inc

java_set_file_encoding("UTF-8");

$key='transfer';

$secretKey = '0123456789abcd0123456789';

$javaObcect = new Java('com.wynca.Common3DESUtil');

//String text, String iv, String keyValue
$ss = $javaObcect->encode3DES("123", $key, $secretKey); //加密
//$ss = $javaObcect->decrypt($pwd, $text); //解密

var_dump($ss->__toString());




