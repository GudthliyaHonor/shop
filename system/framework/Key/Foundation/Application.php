<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.2.0
 * @link http://www.keylogic.com
 */
namespace Key\Foundation;


use \Closure;
use Key\Container;
use Key\Event\EventServiceProvider;
use Key\Log\LogServiceProvider;
use Key\Translation\TranslationServiceProvider;

class Application extends Container
{
    /**
     * The framework version.
     */
    const VERSION = '0.2.0';

    /**
     * The base path of the framework installation.
     *
     * @var string
     */
    protected $basePath;

    /**
     * Indicates if the application has "booted".
     *
     * @var bool
     */
    protected $booted = false;

    /**
     * Application constructor.
     * @param null $basePath
     */
    public function __construct($basePath = null)
    {
        parent::__construct([]);

        if ($basePath) {
            $this->setBasePath($basePath);
        }

        //register_shutdown_function(array($this, 'appShutdown'));

        $this->registerCoreServices();
        $this->registerBaseServices();
    }

    /**
     * Returns the framework version.
     *
     * @return string
     */
    public function getVersion()
    {
        return static::VERSION;
    }

    /**
     * Set the base path of the framework installation.
     *
     * @param string $basePath
     * @return $this
     */
    protected function setBasePath($basePath)
    {
        $this->basePath = rtrim($basePath, '\/');

        return $this;
    }

    /**
     * Get the base path of the framework installation.
     *
     * @return string
     */
    public function getBasePath()
    {
        return $this->basePath;
    }

    /**
     * Get the path of micro services.
     *
     * @return string
     */
    public function getExpressesPath()
    {
        return $this->getBasePath() . DIRECTORY_SEPARATOR . 'apps';
    }

    /**
     * Get the data path for storing files,logs, etc.
     *
     * @return string
     */
    public function getDataPath()
    {
        return $this->getBasePath() . DIRECTORY_SEPARATOR . 'data';
    }

    public function getFilesPath()
    {
        return $this->getDataPath() . DIRECTORY_SEPARATOR . 'files';
    }

    public function getLogPath()
    {
        return $this->getDataPath() . DIRECTORY_SEPARATOR . 'logs';
    }

    public function getConfigurePath()
    {
        return $this->getBasePath() . DIRECTORY_SEPARATOR . 'config';
    }

    /**
     * Register all of the base service providers.
     */
    protected function registerBaseServices()
    {
        $this->register(new EventServiceProvider(), []);
        $this->register(new LogServiceProvider(), []);
    }

    /**
     * Register all of the core service providers.
     */
    protected function registerCoreServices()
    {
        $this->register(new TranslationServiceProvider(), []);
    }

    /**
     * Boot the application service providers.
     */
    public function boot()
    {
        if ($this->booted) {
            return;
        }

        array_walk($this->serviceProviders, function($provider) {
            $this->bootProvider($provider);
        });

        $this->booted = true;
    }

    /**
     * Boot a service provider.
     *
     * @param \Key\Support\ServiceProvider $provider
     * @return mixed
     */
    protected function bootProvider($provider)
    {
        if (method_exists($provider, 'boot')) {
            return call_user_func_array([$provider, 'boot'], []);
        }
    }

    public function beforeLoadingEnvironment()
    {

    }

    public function afterLoadedEnvironment(Closure $callback)
    {
        $this['events']->listen('bootstrapping:' . EnvironmentVariablesLoader::class, $callback);
    }

    public function fireAppCallbacks($callbacks)
    {
        foreach ($callbacks as $callback) {
            call_user_func($callback, $this);
        }
    }
}