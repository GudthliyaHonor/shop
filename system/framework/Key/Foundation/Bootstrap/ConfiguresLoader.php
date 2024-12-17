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
use Key\Foundation\Configure;

class ConfiguresLoader
{
    /**
     * @var \Key\Foundation\Application
     */
    protected $app;

    public function bootstrap(Application $app)
    {
        $this->app = $app;

        $this->load();
    }

    protected function load()
    {
        Configure::load($this->app);
    }

}