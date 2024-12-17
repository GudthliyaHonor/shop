<?php

define("JAVA_HOSTS", "127.0.0.1:8080");

define("JAVA_LOG_LEVEL", 2);

require_once "../Java.inc";//引入Java.inc

java_set_file_encoding("UTF-8");

$key = '00000000000000000000000000000000';
$iv='00000000000000000000000000000000';

$javaObject = new Java('com.gofluent.Encryption', $key, $iv, 'AES', 'false', 'true', 'AES/CBC/PKCS5Padding');

//String text, String iv, String keyValue
$ss = $javaObject->encrypt('hello'); //加密
//$ss = javaObject->decrypt($pwd, $text); //解密

var_dump($ss->__toString());

var_dump($javaObject->decrypt($ss->__toString())->__toString());


