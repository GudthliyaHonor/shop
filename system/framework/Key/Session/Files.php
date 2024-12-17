<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace Key\Session;


class Files implements \SessionHandlerInterface
{

    protected $files;

    protected $basePath;

    /**
     * The path where sessions should be stored.
     *
     * @var string
     */
    protected $path;

    /**
     * The number of minutes the session should be valid.
     *
     * @var int
     */
    protected $minutes;

    public function __construct($path, $minutes, $basePath = '.')
    {
        $this->basePath = $basePath;
        $this->path = $path;
        $this->minutes = $minutes;
    }

    protected function getBasePath()
    {
        return realpath($this->basePath);
    }

    /**
     * Close the session
     * @link http://php.net/manual/en/sessionhandlerinterface.close.php
     * @return bool <p>
     * The return value (usually TRUE on success, FALSE on failure).
     * Note this value is returned internally to PHP for processing.
     * </p>
     * @since 5.4.0
     */
    public function close()
    {
        return true;
    }

    /**
     * Destroy a session
     * @link http://php.net/manual/en/sessionhandlerinterface.destroy.php
     * @param string $session_id The session ID being destroyed.
     * @return bool <p>
     * The return value (usually TRUE on success, FALSE on failure).
     * Note this value is returned internally to PHP for processing.
     * </p>
     * @since 5.4.0
     */
    public function destroy($session_id)
    {
        unlink($this->path . DIRECTORY_SEPARATOR . $session_id);

        return true;
    }

    /**
     * Cleanup old sessions
     * @link http://php.net/manual/en/sessionhandlerinterface.gc.php
     * @param int $maxlifetime <p>
     * Sessions that have not updated for
     * the last maxlifetime seconds will be removed.
     * </p>
     * @return bool <p>
     * The return value (usually TRUE on success, FALSE on failure).
     * Note this value is returned internally to PHP for processing.
     * </p>
     * @since 5.4.0
     */
    public function gc($maxlifetime)
    {
        $dir = dir($this->path);

        $files = [];
        while ($file = $dir->read()) {
            if (is_file("{$this->path}/{$file}")) {
                if (filemtime("{$this->path}/{$file}") < time() - $maxlifetime) {
                    $files[] = $file;
                }
            }
        }

        foreach ($files as $file) {
            unlink($this->path . DIRECTORY_SEPARATOR . $file);
        }
    }

    /**
     * Initialize session
     * @link http://php.net/manual/en/sessionhandlerinterface.open.php
     * @param string $save_path The path where to store/retrieve the session.
     * @param string $name The session name.
     * @return bool <p>
     * The return value (usually TRUE on success, FALSE on failure).
     * Note this value is returned internally to PHP for processing.
     * </p>
     * @since 5.4.0
     */
    public function open($save_path, $name)
    {
        return true;
    }

    /**
     * Read session data
     * @link http://php.net/manual/en/sessionhandlerinterface.read.php
     * @param string $session_id The session id to read data for.
     * @return string <p>
     * Returns an encoded string of the read data.
     * If nothing was read, it must return an empty string.
     * Note this value is returned internally to PHP for processing.
     * </p>
     * @since 5.4.0
     */
    public function read($session_id)
    {
        $filePath = $this->path . DIRECTORY_SEPARATOR . $session_id;
        if (file_exists($filePath)) {
            if (filemtime($filePath) >= (microtime(true) - $this->minutes * 60 * 1000)) {
                $text = file_get_contents($filePath);
                $decoded = json_decode($text);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }
        }

        return '';
    }

    /**
     * Write session data
     * @link http://php.net/manual/en/sessionhandlerinterface.write.php
     * @param string $session_id The session id.
     * @param string $session_data <p>
     * The encoded session data. This data is the
     * result of the PHP internally encoding
     * the $_SESSION superglobal to a serialized
     * string and passing it as this parameter.
     * Please note sessions use an alternative serialization method.
     * </p>
     * @return bool <p>
     * The return value (usually TRUE on success, FALSE on failure).
     * Note this value is returned internally to PHP for processing.
     * </p>
     * @since 5.4.0
     */
    public function write($session_id, $session_data)
    {
        if (file_exists($this->getBasePath() . DIRECTORY_SEPARATOR . $this->path)) {
            file_put_contents($this->getBasePath() . DIRECTORY_SEPARATOR . $this->path . DIRECTORY_SEPARATOR . $session_id, json_encode($_SESSION), true);
        } else {
            error_log('[WARNING] No such file or directory: ' . ($this->getBasePath() . DIRECTORY_SEPARATOR . $this->path));
        }

        return true;
    }
}