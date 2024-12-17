<?php
/**
 * Image file compresser.
 *
 * @author lgh
 * @datetime 2018/8/10 15:08
 * @copyright 2018 Yidianzhishi
 * @version 1.0.0
 */

namespace Key\Abstracts;


abstract class ImageCompressor
{

    /**
     * Compress the image.
     *
     * @param string $file File path
     * @return resource
     * @throws Exception
     */
    abstract public function compress($file);

}
