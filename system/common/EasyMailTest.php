<?php
/**
 * Created by PhpStorm.
 * User: roy
 * Date: 2017/2/23
 * Time: 10:07
 */
require dirname(dirname(dirname(__FILE__))) . '/global.php';

$easyMail = new \App\Common\EasyMail('157185350@qq.com', 'Test mail using EasyMail class,hahahaha', 'This is email body!!!!');
if ($easyMail->send()) {
    echo 'Send successfully';
} else {
    echo 'Send fail';
}