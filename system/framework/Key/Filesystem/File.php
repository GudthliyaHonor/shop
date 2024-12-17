<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace Key\Filesystem;
use Key\Abstracts\ImageCompressor;

/**
 * Class File
 * @package Key\Filesystem
 */
class File
{
    protected $file;
    protected $mime_type;
    /** @var \Key\Abstracts\ImageCompressor */
    protected $compressor;

    public function __construct($file, $mime_type)
    {
        $this->file = $file;
        $this->mime_type = $mime_type;
    }

    /**
     * @param \Key\Abstracts\ImageCompressor $compressor
     */
    public function setCompressor($compressor)
    {
        if (is_string($compressor) && class_exists($compressor)) {
            $this->compressor = new $compressor();
        } elseif ($compressor instanceof ImageCompressor) {
            $this->compressor = $compressor;
        } else {
            error_log('Invalid compressor: ' . var_export($compressor, true));
        }
    }

    /**
     *
     */
    public function output() {
        $path = FileFactory::getFileStorage() . DS . $this->file;

        header('Content-Type:'.$this->mime_type);
        header('Content-Length:'.filesize($path));

        if (startsWith($this->mime_type, 'image/') && $this->compressor) {
            error_log('start to compress image...');
            //list($width, $height, $type, $attr) = getimagesize($path);
            $this->compressor->compress($path, $this->mime_type);
        } else {
            if (file_exists($path)) {
                $file = fopen($path, 'r');
                if (is_resource($file)) {
                    while (!feof($file)) {
                        echo fread($file, 50000);
                    }
                    fclose($file);
                }
            }
        }
    }
}