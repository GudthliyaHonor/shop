<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.2.0
 * @link http://www.keylogic.com
 */
namespace Key\Queue\Connectors;


use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Rabbit
{
    /** @var AMQPStreamConnection RabbitMQ Connection instance  */
    protected $connection;

    /** @var \PhpAmqpLib\Channel\AMQPChannel RabbitMQ Channel */
    protected $channel;

    /** @var Current Queue name */
    protected $queue;

    protected $durable = true;

    protected $host = 'localhost';
    protected $port = 5672;
    protected $username = '';
    protected $password = '';
    protected $vhost = '/';

    protected $insist = false;
    protected $loginMethod = 'AMQPLAIN';
    protected $loginResponse = null;
    protected $locale = 'en_US';
    protected $connectionTimeout = 10.0;
    protected $readWriteTimeout = 0.0; // read_write_timeout must be at least 2x the heartbeat
    protected $context = null;
    protected $keepalive = false;
    protected $heartbeat = 0;

    public function __construct($host, $port, $username, $password, $queue = '', $vhost = '/', $keepalive = false, $heartbeat = 0, $readWriteTimeout = 0.0)
    {
        $this->host = $host;
        $this->port = $port;
        $this->username = $username;
        $this->password = $password;
        $this->vhost = $vhost;

        $this->keepalive = $keepalive;
        $this->heartbeat = $heartbeat;
        if ($this->heartbeat > 0 && $this->readWriteTimeout == 0) {
            $this->readWriteTimeout = 2 * $this->heartbeat;
        } else {
            $this->readWriteTimeout = $readWriteTimeout;
        }

        // $this->connection = new AMQPStreamConnection(
        //     $this->host,
        //     $this->port,
        //     $this->username,
        //     $this->password,
        //     $this->vhost,
        //     $this->insist,
        //     $this->loginMethod,
        //     $this->loginResponse,
        //     $this->locale,
        //     $this->connectionTimeout,
        //     $this->readWriteTimeout,
        //     $this->context,
        //     $this->keepalive,
        //     $this->heartbeat
        // );

        $this->tryConnect();

        if ($queue) $this->setQueue($queue);
    }

    protected function tryConnect($retry = 0)
    {
        $retry++;
        try {
            $this->connection = new AMQPStreamConnection(
                $this->host,
                $this->port,
                $this->username,
                $this->password,
                $this->vhost,
                $this->insist,
                $this->loginMethod,
                $this->loginResponse,
                $this->locale,
                $this->connectionTimeout,
                $this->readWriteTimeout,
                $this->context,
                $this->keepalive,
                $this->heartbeat
            );
        }
        catch (\Exception $ex) {
            error_log('mq connect tried: ' . $retry);
            if ($retry < 3) {
                sleep(3);
                $this->tryConnect($retry);
            }
            else {
                throw $ex;
            }
        }
        return $this->connection;
    }

    public function getHost()
    {
        return $this->host;
    }
    public function getPort()
    {
        return $this->port;
    }
    public function getUsername()
    {
        return $this->username;
    }
    public function getPassword()
    {
        return $this->password;
    }
    public function getVhost()
    {
        return $this->vhost;
    }

    public function getConnectionInfo($secure = true)
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'username' => $this->username,
            'password' => !$secure ? $this->password : '******',
            'vhost' => $this->vhost
        ];
    }

    /**
     * Get the current queue connection instance.
     *
     * @return AMQPStreamConnection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Get the queue channel.
     *
     * @return \PhpAmqpLib\Channel\AMQPChannel
     */
    public function getChannel()
    {
        if (!$this->channel) {
            $this->channel = $this->connection->channel();
        }
        return $this->channel;
    }

    /**
     * Set durable status of the queue.
     *
     * @param $durable
     */
    public function setDurability($durable)
    {
        $this->durable = !!$durable;
    }

    /**
     * Set the queue name.
     *
     * @param $name
     * @return \PhpAmqpLib\Channel\AMQPChannel
     */
    public function setQueue($name)
    {
        $this->queue = env('QUEUE_NAME_PREFIX', '')  . $name;
        $this->channel = $this->connection->channel();
        $this->channel->queue_declare($name, false, $this->durable, false, false);
        return $this->channel;
    }

    /**
     * @return AMQPStreamConnection
     * @throws \Exception
     */
    public function getStreamConnection()
    {
        return AMQPStreamConnection::create_connection([[
            'host' => $this->host,
            'port' => $this->port,
            'user' => $this->username,
            'password' => $this->password,
            'vhost' => $this->vhost,
        ]], [
            'heartbeat' => $this->heartbeat
        ]);
    }

    /**
     * Re-connect the server.
     */
    protected function reConnect()
    {
        $this->tryConnect();
        // $this->connection = new AMQPStreamConnection(
        //     $this->host,
        //     $this->port,
        //     $this->username,
        //     $this->password,
        //     $this->vhost,
        //     $this->insist,
        //     $this->loginMethod,
        //     $this->loginResponse,
        //     $this->locale,
        //     $this->connectionTimeout,
        //     $this->readWriteTimeout,
        //     $this->context,
        //     $this->keepalive,
        //     $this->heartbeat
        // );
    }

    /**
     * Publish a message.
     *
     * @param string $message
     * @param string $queue
     */
    public function publish($message = '', $queue = '')
    {
        if (!$this->channel) {
            throw new \InvalidArgumentException('Channel not inited');
        }

        if (!$this->channel->is_open()) {
            $this->reConnect();
        }

        if ($queue) {
            $this->queue = $queue;
            $this->setQueue($queue);
        }

        if (is_object($message) && method_exists($message, '__toString')) {
            $message = $message->__toString();
        } else {
            if (!is_string($message)) {
                $message = json_encode($message);
            }
        }

        $mqMessage = new AMQPMessage($message);
        $this->channel->basic_publish($mqMessage, '', $this->queue);
    }

    /**
     * Consume the queue.
     *
     * @param \Closure $callback
     * @param bool $ack
     * @return \PhpAmqpLib\Channel\AMQPChannel
     */
    public function consume(\Closure $callback, $ack = true)
    {
        if (!$this->channel) {
            throw new \InvalidArgumentException('Queue Not set');
        }

        $this->channel->basic_consume($this->queue, '', false, $ack, false, false, $callback);
        return $this->channel;
    }

    /**
     * Close the connection.
     */
    public function close()
    {
        $this->channel->close();
        $this->connection->close();
    }
}
