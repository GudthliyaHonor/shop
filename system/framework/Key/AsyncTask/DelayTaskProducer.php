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


use Key\App;
use Beanstalk\Client;

class DelayTaskProducer
{
    // Default configures
    const HOST = '127.0.0.1';
    const PORT = 11300;
    const TIMEOUT = 1;

    protected $tube = 'flux';

    protected $app;

    protected $beanstalk;

    protected $jobId;

    /**
     * Get Beanstalk topic.
     */ 
    public function getTube()
    {
        return $this->tube;
    }

    /**
     * Set Beanstalk topic.
     *
     * @return $this
     */ 
    public function setTube($tube)
    {
        $this->tube = $tube;

        return $this;
    }

    /**
     * Class constructor.
     * 
     * @param string $tube Beanstalk topic
     * @param \Key\App $app
     */
    public function __construct($tube, $app = null)
    {
        $this->app = $app ?: new App();
        $this->tube = $tube;

        $host = env('DELAY_TASK_HOST', self::HOST);
        $port = env('DELAY_TASK_PORT', self::PORT);
        $timeout = env('DELAY_TASK_TIMEOUT', self::TIMEOUT);

        $this->beanstalk = new Client(compact('host', 'port', 'timeout'));
        $this->beanstalk->connect();
    }

    /**
     * Retrives the beanstalk client instance.
     * 
     * @return \Beanstalk\Client
     */
    public function getClient()
    {
        return $this->beanstalk;
    }

    /**
     * Setup the job.
     * 
     * @param array|string $body The job's body.
     * @param int $delay Delay to run the job; If 0, not wait to put job into the ready queue
     * @param int runTime Give the job time to run, for example: 60s (1 minute)
     * @param int priority
     */
    public function put($body, $delay = 0, $runTime = 60, $priority = 1)
    {
        if (is_array($body)) {
            $body['__TIMESTAMP__'] = microtime(true);
            $body = json_encode($body);
        } elseif (!is_string($body)) {
            if (is_object($body) && method_exists($body, '__toString')) {
                $body = $body->__toString();
            } else {
                throw new \Exception('Invalid job body');
            }
        }
        $this->beanstalk->useTube($this->getTube());
        $this->jobId = $this->beanstalk->put($priority, $delay, $runTime, $body);
        return $this;
    }

    /**
     * Retrieves the job ID.
     * 
     * @return int
     */
    public function getJobId()
    {
        return $this->jobId;
    }

    /**
     * Removes a job from the server entirely.
     * 
     * @param int $id The id of the job.
     * @return boolean `false` on error, `true` on success.
     */
    public function deleteJob($id)
    {
        return $this->beanstalk->delete($id);
    }

    /**
     * Disconnect the server.
     */
    public function disconnect()
    {
        $this->beanstalk->disconnect();
    }

    
}