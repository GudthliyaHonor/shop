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

/**
 * Class Context
 * @package App\Common
 * @deprecated
 */
class Context extends \Key\Context
{
    /**
     * Get user display name.
     *
     * @return null|string user display name
     */
    public function getUserDisplayName()
    {
        $user = $this->getUser();
        return $user ? (isset($user['organizations'][0]['real_name']) ? $user['organizations'][0]['real_name'] : null) : null;
    }

}