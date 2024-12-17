<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */
namespace Key\Http;


/**
 * Class RequestBody
 * @package Key\Http
 * @author Guanghui Li <liguanghui2006@163.com>
 */
class RequestBody extends Body
{
    /**
     * Create a new RequestBody.
     */
    public function __construct()
    {
        $stream = fopen('php://temp', 'w+');
        stream_copy_to_stream(fopen('php://input', 'r+'), $stream);
        rewind($stream);

        parent::__construct($stream);
    }
}