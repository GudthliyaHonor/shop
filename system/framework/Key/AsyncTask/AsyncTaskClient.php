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

use InvalidArgumentException;
use Key\Exception\AppException;

/**
 * Class AsyncTaskClient
 * @package Key\AsyncTask
 * @deprecated
 */
class AsyncTaskClient
{
    private $handler;

    /** @var string Socket server host */
    private $host;
    /** @var int Socket server port */
    private $port;

    /** @var resource Stream resource  */
    private $client;

    private $output = '';

    /**
     * TaskClient constructor.
     *
     * @param string $handler Task handler name
     * @param string $host Socket server host
     * @param int|null $port Socket server port, if null is set, its value is 9501.
     * @throws AppException
     */
    public function __construct($handler, $port = null, $host = '127.0.0.1')
    {
        global $CONFIG;

        if (!$port) {
            if (isset($CONFIG->taskDaemonPort) && $CONFIG->taskDaemonPort) {
                $port =(int) $CONFIG->taskDaemonPort;
            } else {
                $port = 9501;
            }
        }

        $this->handler = $handler;
        $this->host = $host;
        $this->port = $port;

        $remote_socket = 'tcp://'.$this->host.':'.$this->port;
        $this->client = stream_socket_client($remote_socket, $errno, $errstr, 1, STREAM_CLIENT_ASYNC_CONNECT);
        if ($this->client === false) {
            //Tools::log('[AsyncTaskClient] Error: '. $errno .' -' . $errstr);
            throw new AppException(sprintf('Failed to connect %s: [%s] %s', $remote_socket, $errno, $errstr));
        }
    }

    /**
     * Write to the socket.
     *
     * @param array $data
     * @return bool
     * @throws AppException
     */
    public function send($data)
    {
        if ($this->client) {
            if (!is_array($data)) {
                throw new InvalidArgumentException('Parameter data must an array.');
            }

            $data['handler'] = $this->handler;

            if (is_resource($this->client) && (feof($this->client) || fwrite($this->client, json_encode($data)))) {
                $this->output = fread($this->client, 4096);

                if (is_numeric($this->output)) {
                    //Tools::log('[AsyncTaskClient] status:'.$this->output);
                } else {
                    //Tools::log('[AsyncTaskClient] output: '.$this->output);
                }

                $this->close();

                return true;
            }

        }

        //Tools::error('[AsyncTaskClient] Not connected');
        return false;
    }

    /**
     * Get data from socket server.
     *
     * @return string
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Close the connection.
     */
    protected function close()
    {
        fclose($this->client);
    }
}