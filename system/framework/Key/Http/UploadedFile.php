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


use RuntimeException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Class UploadedFile
 * @package Key\Http
 * @author Guanghui Li <liguanghui2006@163.com>
 */
class UploadedFile implements UploadedFileInterface
{
    /**
     * An optional StreamInterface wrapping the file resource.
     *
     * @var StreamInterface
     */
    protected $stream;

    /**
     * The full path to the uploaded file provided by the client.
     *
     * @var string
     */
    protected $file;

    /**
     * The client-provided file name.
     *
     * @var string
     */
    protected $name;

    /**
     * The client-provided media type of the file.
     *
     * @var string
     */
    protected $type;

    /**
     * The size of the file in bytes;
     *
     * @var int
     */
    protected $size;

    /**
     * A valid PHP UPLOAD_ERR_xxx code for the file upload.
     *
     * @var int
     */
    protected $error;

    /**
     * Indicates if the uploaded file has already been moved.
     *
     * @var bool
     */
    protected $moved;

    /**
     * Parse a non-normalized, i.e. $_FILES superglobal, tree of uploaded file data.
     *
     * @param array $uploadedFiles
     * @return array
     */
    private static function parseUploadedFiles(array $uploadedFiles)
    {
        $parsed = array();

        foreach($uploadedFiles as $field => $uploadedFile) {
            if (!isset($uploadedFile['error'])) {
                if (is_array($uploadedFile)) {
                    $parsed[$field] = static::parseUploadedFiles($uploadedFile);
                }
                continue;
            }

            //error_log('[parseUploadedFiles] >>>>>field: ' . $field);
            //error_log('[parseUploadedFiles] <<<<file: ' . var_export($uploadedFile, true));

            $parsed[$field] = array();
            if (!is_array($uploadedFile['error'])) {
                $parsed[$field] = new static(
                    $uploadedFile['tmp_name'],
                    isset($uploadedFile['name']) ? $uploadedFile['name'] : null,
                    isset($uploadedFile['type']) ? $uploadedFile['type'] : null,
                    isset($uploadedFile['size']) ? $uploadedFile['size'] : null,
                    $uploadedFile['error']
                );
            } else {
                foreach($uploadedFile['error'] as $fileIdx => $error) {
                    $parsed[$field][] = new static(
                        $uploadedFile['tmp_name'][$fileIdx],
                        isset($uploadedFile['name']) ? $uploadedFile['name'][$fileIdx] : null,
                        isset($uploadedFile['type']) ? $uploadedFile['type'][$fileIdx] : null,
                        isset($uploadedFile['size']) ? $uploadedFile['size'][$fileIdx] : null,
                        $uploadedFile['error'][$fileIdx]
                    );
                }
            }
        }

        return $parsed;
    }

    /**
     * Create a normalized tree of UploadedFile instances.
     *
     * @return array
     */
    public static function createFromEnvironment()
    {
        if (isset($_FILES)) {
            return static::parseUploadedFiles($_FILES);
        }

        return array();
    }

    /**
     * Upload construct.
     *
     * @param string $file The full path to the uploaded file provided by the client.
     * @param string $name The file name.
     * @param string $type The file media type.
     * @param int $size The file size in bytes.
     * @param int $error A valid PHP UPLOAD_ERR_xxx code for the file upload.
     */
    public function __construct($file, $name = null, $type = null, $size = null, $error = UPLOAD_ERR_OK)
    {
        $this->file = $file;
        $this->name = $name;
        $this->type = $type;
        $this->size = $size;
        $this->error = $error;
    }

    /**
     * Retrieve a stream representing the uploaded file.
     *
     * This method MUST return a StreamInterface instance, representing the
     * uploaded file. The purpose of this method is to allow utilizing native PHP
     * stream functionality to manipulate the file upload, such as
     * stream_copy_to_stream() (though the result will need to be decorated in a
     * native PHP stream wrapper to work with such functions).
     *
     * If the moveTo() method has been called previously, this method MUST raise
     * an exception.
     *
     * @return StreamInterface Stream representation of the uploaded file.
     * @throws \RuntimeException in cases when no stream is available or can be
     *     created.
     */
    public function getStream()
    {
        if ($this->moved) {
            throw new \RuntimeException(sprintf('Uploaded file %1s has already been moved', $this->name));
        }
        if ($this->stream === null) {
            $this->stream = new Stream(fopen($this->file, 'r'));
        }

        return $this->stream;
    }

    /**
     * Move the uploaded file to a new location.
     *
     * Use this method as an alternative to move_uploaded_file(). This method is
     * guaranteed to work in both SAPI and non-SAPI environments.
     * Implementations must determine which environment they are in, and use the
     * appropriate method (move_uploaded_file(), rename(), or a stream
     * operation) to perform the operation.
     *
     * $targetPath may be an absolute path, or a relative path. If it is a
     * relative path, resolution should be the same as used by PHP's rename()
     * function.
     *
     * The original file or stream MUST be removed on completion.
     *
     * If this method is called more than once, any subsequent calls MUST raise
     * an exception.
     *
     * When used in an SAPI environment where $_FILES is populated, when writing
     * files via moveTo(), is_uploaded_file() and move_uploaded_file() SHOULD be
     * used to ensure permissions and upload status are verified correctly.
     *
     * If you wish to move to a stream, use getStream(), as SAPI operations
     * cannot guarantee writing to stream destinations.
     *
     * @see http://php.net/is_uploaded_file
     * @see http://php.net/move_uploaded_file
     * @param string $targetPath Path to which to move the uploaded file.
     * @throws \InvalidArgumentException if the $path specified is invalid.
     * @throws \RuntimeException on any error during the move operation, or on
     *     the second or subsequent call to the method.
     */
    public function moveTo($targetPath)
    {
        if ($this->error != UPLOAD_ERR_OK) {
            // throw new RuntimeException('Upload file fail');
            switch ($this->error) {
                case UPLOAD_ERR_NO_FILE:
                    throw new RuntimeException('No file sent.');
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    throw new RuntimeException('Exceeded filesize limit.');
                default:
                    throw new RuntimeException('Unknown errors.');
            }
            return;
        }

        if ($this->moved) {
            throw new RuntimeException('Uploaded file already moved');
        }

        $dir = dirname($targetPath);
        if (!file_exists($dir)) {
            // try to create the folders
            if (mkdir($dir, 0777, true) === false) {
                throw new RuntimeException('Fail to create the folder: '.$dir);
            }
        }

        if (!is_writable($dir)) {
            throw new RuntimeException('Upload target path is not writable: '.$dir);
        }

        $targetIsStream = strpos($targetPath, '://') > 0;
        if ($targetIsStream) {
            if (!copy($this->file, $targetPath)) {
                throw new RuntimeException(sprintf('Error moving uploaded file %1s to %2s', $this->name, $targetPath));
            }
            if (!unlink($this->file)) {
                throw new RuntimeException(sprintf('Error removing uploaded file %1s', $this->name));
            }
        } else {
            if (!is_uploaded_file($this->file)) {
                error_log('the message is ');
                error_log($this->file);
                throw new RuntimeException(sprintf('%1s is not a valid uploaded file', $this->name));
            }

            if (!move_uploaded_file($this->file, $targetPath)) {
                throw new RuntimeException(sprintf('Error moving uploaded file %1s to %2s', $this->name, $targetPath));
            }
        }

        $this->moved = true;
    }

    /**
     * Retrieve the file size.
     *
     * Implementations SHOULD return the value stored in the "size" key of
     * the file in the $_FILES array if available, as PHP calculates this based
     * on the actual size transmitted.
     *
     * @return int|null The file size in bytes or null if unknown.
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * Retrieve the error associated with the uploaded file.
     *
     * The return value MUST be one of PHP's UPLOAD_ERR_XXX constants.
     *
     * If the file was uploaded successfully, this method MUST return
     * UPLOAD_ERR_OK.
     *
     * Implementations SHOULD return the value stored in the "error" key of
     * the file in the $_FILES array.
     *
     * @see http://php.net/manual/en/features.file-upload.errors.php
     * @return int One of PHP's UPLOAD_ERR_XXX constants.
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Retrieve the filename sent by the client.
     *
     * Do not trust the value returned by this method. A client could send
     * a malicious filename with the intention to corrupt or hack your
     * application.
     *
     * Implementations SHOULD return the value stored in the "name" key of
     * the file in the $_FILES array.
     *
     * @return string|null The filename sent by the client or null if none
     *     was provided.
     */
    public function getClientFilename()
    {
        return $this->name;
    }

    /**
     * Retrieve the media type sent by the client.
     *
     * Do not trust the value returned by this method. A client could send
     * a malicious media type with the intention to corrupt or hack your
     * application.
     *
     * Implementations SHOULD return the value stored in the "type" key of
     * the file in the $_FILES array.
     *
     * @return string|null The media type sent by the client or null if none
     *     was provided.
     */
    public function getClientMediaType()
    {
        return $this->type;
    }
}