<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.2.0
 * @link http://www.keylogic.com
 */
namespace Key\Foundation;



class Configure
{
    /**
     * @var array
     */
    private static $configures = [];

    /**
     * Load the configures in configure folder of installation.
     *
     * @param Application $app
     */
    public static function load(Application $app)
    {
        //$basePath = $app->getBasePath();
        $configPath = $app->getConfigurePath();

        $dir = dir($configPath);
        while (($sDir = $dir->read()) !== false) {
            if ($sDir != '.' && $sDir != '..' && !is_dir($configPath . DIRECTORY_SEPARATOR . $sDir)) {
                $settings = include($configPath . DIRECTORY_SEPARATOR . $sDir);
                $basename = basename($sDir, '.php');
                if (is_array($settings)) {
                    foreach ($settings as $key => $value) {
                        self::$configures[$basename . '.' . $key] = $value;
                    }
                }
            }
        }
    }

    /**
     * Get the value of configure attribute.
     *
     * @param string $key
     * @param mixed|null $defaultValue
     * @return mixed|null
     */
    public static function get($key, $defaultValue = null)
    {
        return isset(self::$configures[$key]) ? self::$configures[$key] : $defaultValue;
    }

    /**
     * Set the value of configure attribute.
     *
     * @param string $key
     * @param mixed|null $value
     * @param bool $override
     */
    public static function set($key, $value, $override = false)
    {
        if (!isset(self::$configures[$key]) || isset(self::$configures[$key]) && $override) {
            self::$configures[$key] = $value;
        }
    }

}