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

abstract class DelayTaskConsumer
{
    // Default configures
    const HOST = '127.0.0.1';
    const PORT = 11300;
    const TIMEOUT = 1;
    const TUBE = 'flux';

    protected $app;

    /** @var \Beanstalk\Client */
    protected $beanstalk;

    /**
     * Check if the env is CLI.
     * 
     * @return boolean
     */
    public static function isCLI()
    {
        return defined('STDIN') || empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0;
    }

    /**
     * Class constructor.
     * @param string $tube Beanstalk topic
     * @param \Key\App $app App instance
     */
    public function __construct($tube = self::TUBE, $app = null)
    {

        if (!self::isCLI()) {
            throw new \Exception('The consumer can ONLY run in the CLI mode.');
        }

        $this->app = $app ?: new App();

        $host = env('DELAY_TASK_HOST', self::HOST);
        $port = env('DELAY_TASK_PORT', self::PORT);
        $timeout = env('DELAY_TASK_TIMEOUT', self::TIMEOUT);

        $this->beanstalk = new Client(compact('host', 'port', 'timeout'));
        $this->beanstalk->connect();
        $this->beanstalk->watch($tube);
        echo '[*] Listen ' . $host . ':' . $port . '@' . $tube. PHP_EOL;
    }

    /**
     * Consume the job.
     * 
     * @param int $jobId
     * @param array $body
     * @return bool
     */
    abstract protected function consume($jobId, $body);

    /**
     * Handle the job.
     */
    public function handle()
    {
        while (true) {
            usleep(200000); // sleep 0.2s
            $job = $this->beanstalk->reserve(); // Block until job is available.
            
            $jobId = $job['id'];
            $originBody = $job['body'];

            $body = json_decode($originBody, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $result = $this->consume($jobId, $body);
                if (is_bool($result)) {
                    if ($result) {
                        $this->beanstalk->delete($jobId);
                    } else {
                        $this->beanstalk->bury($jobId);
                    }
                } else {
                    echo '[!] The result that consume method returns is not bool type!' . PHP_EOL;
                }
            } else {
                // echo '[!] Invalid job body!' . PHP_EOL;
                // echo '[!] Job body: ' . var_export($originBody, true) . PHP_EOL;
            }
        }

        $this->beanstalk->disconnect();
    }
}