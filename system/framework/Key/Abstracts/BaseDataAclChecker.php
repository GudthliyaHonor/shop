<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace Key\Abstracts;


/**
 * Data ACL checker for route.
 * @package Key\Abstracts
 */
class BaseDataAclChecker
{
    protected $app;
    public function __construct($app)
    {
        $this->app = $app;
    }
}