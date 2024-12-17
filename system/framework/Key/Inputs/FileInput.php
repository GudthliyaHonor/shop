<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */
namespace Key\Inputs;


use stdClass;
use Key\Http\UploadedFile;
use Key\Exception\AppException;
use Key\Filesystem\FileFactory;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Class FileInput
 * @package Key\Inputs
 */
class FileInput extends Input
{
    const FOLDER_REGEXP = '/\{(?P<word>.+?)\}/';

    const LIMIT_REGEXP = '/^(\d+)([G|M|K|B])$/i';

    const SIZE_K = 1024;
    protected static $sizes = [
        'K' => 1024,
        'M' => 1024 * self::SIZE_K,
        'G' => 1024 * 1024 * self::SIZE_K
    ];

    /**
     * Get input enum setting.
     * Invalid for File Input.
     *
     * @return null|\Key\Collection
     */
    public function getEnum()
    {
        return null;
    }

    /**
     * Get the default value of the input.
     * Invalid for File Input.
     *
     * @return mixed
     */
    public function getDefaultValue()
    {
        return null;
    }

    /**
     * Get input fixed value.
     * Invalid for File Input.
     *
     * @return mixed
     */
    public function getFixedValue()
    {
        return null;
    }

    /**
     * Get input format setting.
     * Invalid for File Input.
     *
     * @return mixed
     */
    public function getFormat()
    {
        return null;
    }

    /**
     * Check if file extesion check is case-sensitive.
     * 
     * @return bool
     */
    public function isCaseExt()
    {
        return !!$this->get('caseExt');
    }

    /**
     * Get the limitation of the uploaded file size.
     *
     * @return string
     */
    public function getLimit()
    {
        $limit = $this->get('limit');
        if ($limit) {
            if (preg_match(self::LIMIT_REGEXP, $limit, $matched)) {
                $num = (int) $matched[1];
                if ($num) {
                    $unit = strtoupper($matched[2]);
                    return self::$sizes[$unit] * $num;
                } else {
                    error_log('ZERO size for limitation! No limit!');
                }
            }
        }
        return null;
    }

    /**
     * Get valid value for the input.
     *
     * @return mixed|null
     * @throws AppException
     */
    public function getValidValue()
    {

        $valid = parent::getValidValue();

        if ($valid instanceof UploadedFileInterface) {

            $validExtensions = $this->getValidExtensions();
            $currentExtension = $this->getExtension($valid->getClientFilename());
            if (!$this->isCaseExt()) {
                $currentExtension = strtolower($currentExtension);
            }
            if ($validExtensions) {
                if (!in_array($currentExtension, $validExtensions)) {
                    $this->validatedCode = static::INVALID_FILE_EXTENSION;

                    throw new AppException('Invalid file extension');
                }
            }

            if ($limit = $this->getLimit()) {
                if ($limit < $valid->getSize()) {
                    throw new AppException('Limitation exceeded of uploaded file');
                }
            }

            $obj = new stdClass;
            //$obj->uploadedFile = $valid;
            $obj->original_name = $valid->getClientFilename();
            $obj->type = $valid->getClientMediaType();
            $obj->size = $valid->getSize();
            $obj->error = $valid->getError();
            //$obj->folder = trim($this->getFolder(), DS);

            $randomName = $this->randomName();
            $randomFullName = $randomName.'.'.$this->getExtension($obj->original_name, $obj->type);

            $newPath = $this->getAbsoluteFolder() . $randomFullName;

            $valid->moveTo($newPath);

            $obj->name = $randomFullName;
            $obj->full_name = $newPath;
            $obj->relative_path = ($this->getFolder() ? $this->getFolder() . '/' : '') . $randomFullName;

            $max_width = (int)$this->get('max_width');
            $max_height = (int)$this->get('max_height');
            if ($max_width || $max_height) {
                $image_info = @getimagesize($newPath);

                if ($image_info) {
                    $factory = $this->getImageFactory($currentExtension);
                    if ($factory) {
                        $width = $image_info['0'];
                        $height = $image_info['1'];

                        $src = $factory($newPath);
                        $new_src = $this->resizeImage($src, $width, $height,  $max_width, $max_height);
                        if ($new_src) {
                            $cropped_name = $randomName . '_' . $max_width . 'x' . $max_height . '.png';
                            $full_name = $this->getAbsoluteFolder() . $cropped_name;
                            imagepng($new_src, $full_name);
                            imagedestroy($new_src);

                            $obj->name = $cropped_name;
                            $obj->full_name = $full_name;
                            $obj->relative_path = ($this->getFolder() ? $this->getFolder() . '/' : '') . $cropped_name;
                        }
                    }
                } else {
                    error_log(sprintf('Can not get image info from the uploaded file: %s', $newPath));
                }
            }

            return $obj;
        }

        $this->validatedCode = static::VALID_CODE_UNDEFINED;

        return null;
    }

    /**
     * Get saved folder for upload file.
     *
     * @return string
     */
    public function getFolder()
    {
        $folder = $this->get('folder');
        if (!$folder) {
            error_log('[FileInput][WARN] upload folder not set');
        }
        if ($folder == '/tmp') {
            error_log('[FileInput][WARN] Files uploaded to this directory are temporary and will be deleted later! Don NOT save this path to your data!');
        }
        return preg_replace_callback(static::FOLDER_REGEXP, function($matches){
            $match = $matches[1];
            eval("\$match=$match;");
            return $match;
        }, $folder);
    }

    /**
     * Get the absolute folder.
     * 
     * @return string
     */
    protected function getAbsoluteFolder()
    {
        $fileStorage = FileFactory::getFileStorage();
        return rtrim($fileStorage, DS) . DS .($this->getFolder() ? trim($this->getFolder(), DS) . DS : '');
    }

    /**
     * Get the valid file extensions in the setting.
     *
     * @return string[]
     */
    public function getValidExtensions()
    {
        $exts = $this->get('exts');
        if (is_string($exts)) {
            $exts = trim($exts);
            return explode(',', $exts);
        } elseif (is_array($exts) && $exts) {
            return $exts;
        }

        return null;
    }

    /**
     * Check if it needs to create thumbnails.
     *
     * @return bool
     */
    public function needThumbnails()
    {
        return !!($this->get('thumbnails'));
    }

    protected function getExtension($name, $type = null)
    {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        if (!$ext && $type) {
            switch ($type) {
                case 'audio/mpeg':
                    $ext = 'mp3';
                    break;
            }
        }
        return $ext;
    }

    protected function randomName($length = 10)
    {
        $hash = 'F';
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz';
        $max = strlen($chars) - 1;
        mt_srand((double)microtime() * 1000000);
        for ($i = 0; $i < $length; $i++) {
            $hash .= $chars[mt_rand(0, $max)];
        }

        return $hash;
    }

    protected function canImageResize($ext)
    {
        return in_array($ext, array(
            'png', 'jpg', 'jpeg', 'bmp', 'gif'
        ));
    }

    protected function getImageFactory($ext)
    {
        $factory = null;
        switch ($ext) {
            case 'png':
                $factory = 'imagecreatefrompng';
                break;
            case 'jpg':
            case 'jpeg':
                $factory = 'imagecreatefromjpeg';
                break;
            case 'gif':
                $factory = 'imagecreatefromgif';
                break;
            case 'bmp':
                $factory = 'imagecreatefromwbmp';
                break;
        }

        return $factory;
    }

    protected function resizeImage($source, $width, $height, $max_width, $max_height)
    {
        $original_width = $width;
        $original_height = $height;

        if(($max_width && $width > $max_width) || ($max_height && $height > $max_height)) {
            $resize_width_tag = false;
            $resize_height_tag = false;

            if ($max_width && $width > $max_width) {
                $width_ratio = $max_width / $width;
                $resize_width_tag = true;
            }

            if ($max_height && $height > $max_height) {
                $height_ratio = $max_height / $height;
                $resize_height_tag = true;
            }

            if ($resize_width_tag && $resize_height_tag) {
                if ($width_ratio < $height_ratio)
                    $ratio = $width_ratio;
                else
                    $ratio = $height_ratio;
            }

            if ($resize_width_tag && !$resize_height_tag)
                $ratio = $width_ratio;
            if ($resize_height_tag && !$resize_width_tag)
                $ratio = $height_ratio;

            $width = $width * $ratio;
            $height = $height * $ratio;

            if (function_exists('imagecopyresampled')) {
                $new_source = imagecreatetruecolor($width, $height);
                imagecopyresampled($new_source, $source, 0, 0, 0, 0, $width, $height, $original_width, $original_height);
            } else {
                $new_source = imagecreate($width, $height);
                imagecopyresized($new_source, $source, 0, 0, 0, 0, $width, $height, $original_width, $original_height);
            }

            return $new_source;
        }

        return null;
    }
}