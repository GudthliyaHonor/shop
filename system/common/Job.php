<?php
/**
 * Job class which execute a task list upon a item queue with shared memory capability.
 * User: lgh
 * Date: 2018/3/31
 * Time: 9:58
 */

namespace App\Common;


class Job
{
    const
        CACHE_PREFIX = 'KEY/',
        WORKER_NUMBER = 10;

    private
        $queues = [],
        $tasks = [],
        $pids = [],
        $workerNumbers,
        $length,
        $prefix,
        $id;

    /**
     * Job constructor.
     *
     * @param array $queue
     * @param int $workNumber
     */
    public function __construct(array $queue, $workNumber = self::WORKER_NUMBER)
    {
        $count = count($queue);
        $this->length = ceil($count / $workNumber);
        $this->queues = array_chunk($queue, $this->length);
        $this->setWorkerNumber($workNumber);
        $this->prefix = self::CACHE_PREFIX . microtime(true) . '/';
    }

    public function setWorkerNumber($workNumber)
    {
        $this->workerNumbers = $workNumber;
        return $this;
    }

    public function __get($key)
    {
        return apc_fetch($this->prefix . $key);
    }

    public function __set($key, $value)
    {
        apc_store($this->prefix . $key, $value);
    }

    public function add(\Closure $task)
    {
        $this->tasks[] = $task->bindTo($this, $this);
        return $this;
    }

    public function run(\Closure $task = null)
    {
        if (isset($task)) {
            $this->add($task);
        }

        $i = 0;
        do {
            $queue = isset($this->queues[$i]) ? $this->queues[$i] : null;
            $i++;
            $pid = pcntl_fork();
            $this->id = $i;

            if ($pid == -1) {
                die('Can NOT fork!');
            } elseif ($pid !== 0) {
                // main
                $this->pids[$pid] = $pid;
            } else {
                // child
                foreach ($this->tasks as $task) {
                    $task($queue);
                }

                exit(0);
            }
        } while($i < $this->workerNumbers);

        // main
        do {
            $pid = pcntl_wait($status);
            error_log('main pid: ' . $pid);
            unset($this->pids[$pid]);
        } while (count($this->pids));
    }
}