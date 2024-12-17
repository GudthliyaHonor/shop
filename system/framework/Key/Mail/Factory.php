<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2022 yidianzhishi.com
 * @version 1.0.0
 * @link http://www.yidianzhishi.com
 */

namespace Key\Mail;

use Key\Exception\AppException;

class Factory
{
    private static $instance = null;

    /**
     * Get the mail instance
     *
     * @param \Key\Container $app
     * @return \Key\Abstracts\Mail
     */
    public static function getInstance($app)
    {
        if (self::$instance == null) {
            $mailType = $app['config']['mail.default'] ?? 'smtp';
            $mailServices = $app['config']['mail.services'];
            $config = $mailServices[$mailType] ?? $mailServices['smtp'];
            if ($config) {
                $className = '\\Key\\Mail\\' . ucfirst($mailType);
                if (class_exists($className)) {
                    /** @var \Key\Abstracts\Mail $class */
                    self::$instance = new $className($config);
                }
                else {
                    throw new AppException('Class not found: ' . $className);
                }
            }
            else {
                throw new AppException('Mail config not found for service name ' . $mailType);
            }
        }
        return self::$instance;
    }

    /**
     * Get the mail instance by configure.
     *
     * @param array $config
     * @param string $mailType
     * @return \Key\Abstracts\Mail
     */
    public static function getInstanceByConfig($config, $mailType = 'smtp')
    {
        $className = '\\Key\\Mail\\' . ucfirst($mailType);
        if (class_exists($className)) {
            /** @var \Key\Abstracts\Mail $class */
            return new $className($config);
        }
        else {
            throw new AppException('Class not found: ' . $className);
        }
    }
}