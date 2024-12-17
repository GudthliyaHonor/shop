<?php
require('global.php');

$app = new \Key\App(dirname(__FILE__));

// Add middlewares
$app->addMiddlewareStack('Key\\Middleware\\RestRouter')
    ->addMiddlewareStack('Key\\Middleware\\JSPage')
    ->addMiddlewareStack('Key\\Middleware\\Home')
    ->addMiddlewareStack('Key\\Middleware\\CorsAuth')
    ->addMiddlewareStack('Key\\Middleware\\Maintenance')
    ->addMiddlewareStack('Key\\Middleware\\Locale')
    ->addMiddlewareStack('Key\\Middleware\\Config')
    ->addMiddlewareStack('Key\\Middleware\\Modules');

$app->run();
