<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2022 yidianzhishi.com
 * @version 1.0.0
 */

namespace Key\Foundation\Routes;


use Key\Constants;
use Key\Abstracts\Controller;

class InternalRoutes extends Controller
{
    public static function getRoutes()
    {
        return env('APP_EXPERIMENT') 
        ? [
            'GET /__system/demo' => [
                'summary' => 'Internal system demo route',
                'description' => 'ONLY for demo',
                'acl' => '',
                'data_acl' => '',
                'controller' => '/Key/Foundation/Routes/InternalRoutes/ctrlHanlder',
                'inputs' => [],
                'view' => dirname(__FILE__) . DS . 'views' . DS . 'system_demo.php',
                // 'view' => function($outputs, $statusCode, $statusMessage, &$container) {
                //     $container['response']->withHeader('Content-Type', 'text/html');
                //     error_log('========' . get_class($container));
                //     return 'Hello world!';
                // }
            ],
        ]
        :
        [];
    }

    public function ctrlHanlderAction()
    {
        return Constants::SYS_SUCCESS;
    }

}