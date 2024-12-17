<?php
/**
 * 用于检查网络状态。
 * 可在nginx配置：
 * rewrite ^/network/ping$ /ping.php last;
 */
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: X-Requested-With,Content-Type,Accept');
echo 'OK';