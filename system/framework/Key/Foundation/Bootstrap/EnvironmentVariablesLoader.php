<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.2.0
 * @link http://www.keylogic.com
 */
namespace Key\Foundation\Bootstrap;


use Key\Foundation\Application;

class EnvironmentVariablesLoader
{
    /**
     * @var
     */
    protected $app;

    public function bootstrap(Application $app)
    {
        $this->app = $app;

        $this->loadEnvFile($app->getBasePath());
    }

    protected function loadEnvFile($basePath)
    {
        $envFile = $basePath . DIRECTORY_SEPARATOR . '.env';
        if (file_exists($envFile)) {
            if ($variables = parse_ini_file($envFile)) {
                foreach($variables as $key => $variable) {
                    $this->setEnvironmentVariable($key, $variable);
                }
            } else {
                $this->app ['log']->warn('Invalid env file: ' . $envFile);
            }
        }
    }

    protected function setEnvironmentVariable($key, $variable)
    {
        putenv($key . '=' . $variable);
    }
}