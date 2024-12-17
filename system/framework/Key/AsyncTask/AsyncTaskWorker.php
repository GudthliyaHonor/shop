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
 * Class AsyncTaskWorker
 * @package Key\AsyncTask
 * @deprecated
 */
abstract class AsyncTaskWorker
{
    /**
     * Run the worker.
     *
     * @return mixed
     */
    abstract public function run();
}