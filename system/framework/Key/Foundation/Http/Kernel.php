<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.2.0
 * @link http://www.keylogic.com
 */
namespace Key\Foundation\Http;


use Key\Foundation\Application;
use Key\Foundation\Configure;
use Key\Routing\Router;

class Kernel
{

    /**
     * @var \Key\Foundation\Application
     */
    protected $app;

    protected $router;

    /**
     * Bootstrap classes for the application.
     *
     * @var array
     */
    protected $bootstraps = [
        \Key\Foundation\Bootstrap\EnvironmentVariablesLoader::class,
        \Key\Foundation\Bootstrap\ConfiguresLoader::class
    ];

    protected $middlewares = [
        \Key\Http\Middleware\Demo::class
    ];

    protected $errorContracts = [
        'error' => \Key\Handlers\Error::class,
        'notFound' => \Key\Handlers\NotFound::class,
        'serviceUnavailable' => \Key\Handlers\ServiceUnavailable::class
    ];

    protected $errorHandlers = [];

    /**
     * Kernel constructor.
     * @param Application $app
     * @param Router $router
     */
    public function __construct(Application $app, Router $router)
    {
        $this->app = $app;
        $this->router = $router;

    }

    public function handle()
    {
        $this->bootstrap();
    }

    /**
     * Foundation boot.
     */
    protected function bootstrap()
    {
        if (is_array($this->bootstraps)) {
            foreach($this->bootstraps as $bootstrap) {
                $class = new $bootstrap;
                $class->bootstrap($this->app);
            }
        }
    }

    protected function registerErrorHandlers()
    {
        if (is_array($this->errorContracts)) {
            foreach($this->errorContracts as $key => $contract) {
                $this->errorHandlers[$key . 'Handler'] = function () use($contract) {
                    return new $contract;
                };
            }
        }
    }
}