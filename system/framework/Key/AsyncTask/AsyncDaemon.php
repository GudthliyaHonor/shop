<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace Key\AsyncTask;


use swoole_server;
use swoole_lock;
use Key\Constants;

/**
 * Class AsyncDaemon
 * @package Key\AsyncTask
 * @deprecated
 */
class AsyncDaemon
{
    private $port;
    private $server;

    private $pidLock = null;

    private $handlers = array();

    public function __construct() {
        global $CONFIG;

        if (isset($CONFIG->taskDaemonPort)) {
            $port = (int) $CONFIG->taskDaemonPort;
        } else {
            $port = 9501;
        }

        if (!isset($CONFIG->async_task_handlers) || !is_array($CONFIG->async_task_handlers)) {
            echo 'Task handlers not set or invalid.' . PHP_EOL;
            $CONFIG->async_task_handlers = array();
        }
        $handlers = $CONFIG->async_task_handlers;

        $this->port = $port;
        $this->handlers = $handlers;
        $this->server = new swoole_server('127.0.0.1', $port);
        $this->server->set(array(
            'worker_num' => 8,
            'daemonize' => false,
            'task_worker_num' => 2
        ));

        $this->server->on('Start', array($this, 'onStart'));
        $this->server->on('Connect', array($this, 'onConnect'));
        $this->server->on('Receive', array($this, 'onReceive'));
        $this->server->on('Close', array($this, 'onClose'));
        $this->server->on('Shutdown', array($this, 'onShutdown'));

        $this->server->on('Task', array($this, 'onTask'));
        $this->server->on('Finish', array($this, 'onFinish'));

        $this->server->start();
    }

    public function onStart( $server ) {
        echo "Server Start in {$this->port}\n";

        // TODO: lock a pid file for daemon status checking, @see http://wiki.swoole.com/wiki/page/233.html
        //
        $this->pidLock = new swoole_lock(SWOOLE_FILELOCK, 'taskdaemon.pid');
    }

    public function onConnect( $server, $fd, $from_id ) {
        echo "Server connected: {$fd}, {$from_id}" . PHP_EOL;
        //$server->send( $fd, "Hello {$fd}!" );
    }

    public function onReceive( swoole_server $server, $fd, $from_id, $data ) {
        echo "Get Message From Client {$fd}:{$data}" . PHP_EOL;
        //$server->send($fd, $data);

        $data = json_decode($data, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($data['server_status']) && $data['server_status'] == 1) {
                $server->send($fd, json_encode($server->stats()));
            } else {
                $server->send($fd, Constants::USER_DAEMON_TASK_PROCESSING);
                $server->task($data);
            }
        } else {
            echo 'Invalid data' . PHP_EOL;
        }
    }

    public function onClose( $server, $fd, $from_id ) {
        echo "Client {$fd} close connection" . PHP_EOL;
    }

    public function onShutdown($server)
    {
        // Unlock the pid file
        if ($this->pidLock) {
            $this->pidLock->unlock();
            $this->pidLock = null;
        }

        echo 'Server shutdown!' . PHP_EOL;
    }

    /**
     * @param swoole_server $server swoole_server对象
     * @param int $task_id 任务id
     * @param int  $from_id 投递任务的worker_id
     * @param mixed $data 投递的数据
     * @return int
     */
    function onTask(swoole_server $server, $task_id, $from_id, $data) {
        //global $CONFIG;

        echo $task_id . ' Task start' . PHP_EOL;
        echo 'data: '.var_export($data, true) . PHP_EOL;

        if (is_array($data) && isset($data['handler']) && $handler = $data['handler']) {
            $pieces = explode('.', $handler);
            $handler = array_shift($pieces);

            if (count($pieces) > 0) {
                $data['handler'] = implode('.', $pieces);
            } else {
                unset($data['handler']);
            }

            if (isset($this->handlers[$handler])) {
                $handler_class = $this->handlers[$handler];
                if (class_exists($handler_class)) {
                    /** @var \App\Common\Worker $class */
                    $class = new $handler_class($data);
                    echo $task_id.' Task handler run start: '.microtime(true) . PHP_EOL;
                    $result = $class->run();
                    echo $task_id . ' Result: '.var_export($result, true) . PHP_EOL;
                    echo $task_id.' Task handler run end: '.microtime(true) . PHP_EOL;
                } else {
                    echo 'Task handler class not found: ' . $handler_class . PHP_EOL;
                }
            } else {
                echo 'Task handler class not found: ' . $handler . PHP_EOL;
            }
        } else {
            echo 'No task handler found: ' . $task_id . PHP_EOL;
        }

        return $task_id;
    }

    /**
     * @param swoole_server $server swoole_server对象
     * @param int $task_id 任务id
     * @param mixed $data 任务返回的数据
     */
    function onFinish(swoole_server $server, $task_id, $data) {
        echo $task_id . ' Task finish' . PHP_EOL;
    }
}