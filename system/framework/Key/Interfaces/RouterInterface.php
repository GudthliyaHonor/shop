<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */
namespace Key\Interfaces;


/**
 * Interface RouterInterface
 * @package Key\Interfaces
 */
interface RouterInterface
{
    /**
     * Execute the router.
     * @return mixed
     */
    public function exec();
}