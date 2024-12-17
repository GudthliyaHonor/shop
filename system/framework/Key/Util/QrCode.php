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
 * Class QrCode
 * @package Key\Util
 * @author Guanghui Li <liguanghui2006@163.com>
 * @deprecated
 */
class QrCode
{
    public static function create($text) {
        include LIBRARY_PATH . DS . 'phpqrcode' . DS . 'qrlib.php';

        \QRcode::png($text);
    }
}