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

/**
 * Class WebSocketServer
 * @package Key\AsyncTask
 * @deprecated
 */
class BaseWebSocketServer
{
    protected $port;
    protected $server;

    /** @var string for client validation */
    protected $token;

    /**
     * BaseWebSocketServer constructor.
     */
    public function __construct()
    {
        global $CONFIG;

        if (isset($CONFIG->webSocketServer) && isset($CONFIG->webSocketServer['port']) && $CONFIG->webSocketServer['port']) {
            $port = (int)$CONFIG->webSocketServer['port'];
        } else {
            $port = 9502;
        }

        $this->token = isset($CONFIG->webSocketServer) && isset($CONFIG->webSocketServer['token']) ? $CONFIG->webSocketServer['token'] : null;

        $this->port = $port;
        $this->server = new \Swoole\Websocket\Server('0.0.0.0', $port);

        $this->server->on('Open', function($server, $req) {
            $this->onOpen($server, $req);
        });

        $this->server->on('Message', function ($server, $frame) {
            $this->onMessage($server, $frame);
        });

        $this->server->on('Close', function ($server, $fd) {
            $this->onClose($server, $fd);
        });
    }

    /**
     * Open handler
     *
     * @param $server
     * @param $req
     */
    protected function onOpen($server, $req)
    {
        echo "connection open: ".$req->fd.PHP_EOL;
    }

    /**
     * Message handler
     *
     * @param $server
     * @param $frame
     */
    protected function onMessage($server, $frame)
    {
        echo "message: ".$frame->data.PHP_EOL;
        $server->push($frame->fd, json_encode(["hello", "world"]));
    }

    /**
     * Close handler
     *
     * @param $server
     * @param $fd
     */
    protected function onClose($server, $fd)
    {
        echo "connection close: ".$fd.PHP_EOL;
    }

    /**
     * Start the server.
     */
    public function start()
    {
        $this->server->start();
    }
}
