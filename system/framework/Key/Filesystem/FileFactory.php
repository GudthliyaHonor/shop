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


/**
 * Class FileFactory
 * @package Key\Filesystem
 */
class FileFactory
{
    const DEFAULT_MIME_TYPE = 'application/octet-stream';

    public static function startsWith($haystack, $needle)
    {
        return substr_compare ( $haystack , $needle , 0 , strlen ( $needle ) ) === 0 ;
    }

    /**
     * Get cache storage.
     *
     * @return mixed
     */
    public static function getCacheStorage()
    {
        return env('FILE_CACHE_FOLDER') ?: static::getFileStorage() . DIRECTORY_SEPARATOR . 'cache';
    }

    /**
     * Get file storage.
     *
     * @return string
     */
    public static function getFileStorage()
    {
        return rtrim(env('FILE_STORAGE_FOLDER', FILE_PATH), DIRECTORY_SEPARATOR);
    }

    /**
     * Get file instance.
     *
     * @param string $file File path
     * @return \Key\Filesystem\File
     */
    public static function getInstance($file)
    {
        $full_path = static::getFileStorage() . DS . $file;
        if (file_exists($full_path)) {
            $mime_type = mime_content_type($full_path);

            if (!$mime_type) {
                $mime_type = static::DEFAULT_MIME_TYPE;
            }

            $prefix = explode('/', $mime_type);

            $className = 'Key\\Filesystem\\'.ucfirst($prefix[0]);

            if (class_exists($className)) {
                $class = new $className($file, $mime_type);
            } else {
                $class = new File($file, $mime_type);
            }

            return $class;
        }

        throw new \InvalidArgumentException('File must be exist.');
    }

}