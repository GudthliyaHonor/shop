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




$javaObcect = new Java('com.cert.util.DecryptUtil');

$ss = $javaObcect->decrypt('L3ROvEA-ImKoNbYdwRtWealRzxexjhf_QZzu_Rmyv5qhf35daGnvx3cKCc5av41NKZBVX-7_TXVkgwQ53ncWsH5hC31bEdfQr3WnsQZoJ4B1P23U7x3tOVBysiGUpQD2S9vZyIpPqKUiF038mRFQpbJe7uH7IlaR4k1jX_rMF3IBm9XUFZqVdWLzvYcb-6YPg7mYMejrkqbNimF-PJY8JdYgUYmXYSacoXr5D0medmB3pUQnj4YdMoxLaDTpI4zwKsEYTN43SVORKqScFxkKTpmb-myRR_198XstmQRiraggcUnJ4E8_2EyMlrxRGKx3pASMWjlIHMwULLD6KZrXdg'); //加密
//$ss = $javaObcect->decrypt($pwd, $text); //解密

var_dump($ss->__toString());




