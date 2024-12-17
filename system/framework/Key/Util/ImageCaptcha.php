<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace Key\Util;


/**
 * Show the image captcha.
 *
 * @package Key\Util
 * @deprecated
 */
class ImageCaptcha
{
    private $code = '';

    /**
     * Create an image captcha.
     */
    public function create()
    {
        include LIBRARY_PATH . DS . 'captcha'. DS . 'captcha.php';

        $captcha = new \ValidateCode();
        $captcha->doimg();
        $this->code = $captcha->getCode();
    }

    /**
     * Get verify code.
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

}