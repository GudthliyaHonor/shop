<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace App\Common;
use Key\AsyncTask\AsyncTaskWorker;

/**
 * Class Worker
 * @package App\Common
 */
abstract class Worker extends AsyncTaskWorker
{
    protected $option;

    protected $uid;
    protected $aid;

    /** @var string next handler  */
    protected $handler;

    protected $target;
    protected $subTarget;

    public function __construct($option)
    {
        $this->option = $option;

        $this->uid = isset($option['uid']) ? (int) $option['uid'] : 0;
        $this->aid = isset($option['aid']) ? (int) $option['aid'] : 0;
        $this->handler = isset($option['handler']) && $option['handler'] ? $option['handler'] : null;

        $this->target = isset($option['target']) ? $option['target'] : null;
        $this->subTarget = isset($option['subTarget']) ? $option['subTarget'] : null;
    }

    /**
     * Goto next process if handler is defined.
     * @param array $data
     */
    protected function next($data)
    {
        if ($this->handler) {
            $client = new TaskClient($this->handler);
            $client->send($data);
        }
    }
}