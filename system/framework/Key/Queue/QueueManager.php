<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.2.0
 * @link http://www.keylogic.com
 */
namespace Key\Queue;


use Key\Queue\Connectors\Rabbit;

class QueueManager
{
    /** @var \Key\Container */
    protected $app;

    /**
     * QueueManager constructor.
     * @param \Key\Container $app
     */
    public function __construct($app)
    {
        $this->app = $app;
    }

    protected function getConnections()
    {
        return $this->app['config']['queue.connections'];
    }

    public function getDefaultConnector()
    {
        $default = $this->app['config']['queue.default'];

        return $this->getConnector($default);
    }

    public function getConnector($name)
    {
        $connections = $this->getConnections();
        $config = ArrayGet($connections, $name);
        $driver = ArrayGet($config, 'driver');
        return $this->getConnection($driver, $config);
    }

    protected function getConnection($name, $config)
    {
        switch ($name) {
            case 'sync':
                return $this->getSyncConnection($config);
                break;
            case 'stomp':
                return $this->getStompConnection($config);
                break;
            case 'rabbit':
                return $this->getRabbitConnection($config);
                break;
        }
    }

    protected function getSyncConnection()
    {

    }

    protected function getStompConnection()
    {

    }

    protected function getRabbitConnection($config = [])
    {
        $host = ArrayGet($config, 'host');
        $port = ArrayGet($config, 'port');
        $username = ArrayGet($config, 'username');
        $password = ArrayGet($config, 'password');
        $queue = ArrayGet($config, 'queue');
        $heartbeat = ArrayGet($config, 'heartbeat', 0);
        $vhost = ArrayGet($config, 'vhost', 0);
        $keepalive = ArrayGet($config, 'keepalive', 0) ? true : false;

        return new Rabbit($host, $port, $username, $password, $queue, $vhost, $keepalive, $heartbeat);
    }
}