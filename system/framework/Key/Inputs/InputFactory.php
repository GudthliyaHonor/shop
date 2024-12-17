<?php
/**
 * Created by PhpStorm.
 * User: roy
 * Date: 2017/3/3
 * Time: 10:23
 */

namespace Key\Inputs;


class InputFactory
{
    static $namespacePrefix = array(
        '\\Key\\Inputs\\',
        '\\Key\\Records\\',
        '\\App\\Records\\'
    );

    /**
     * Add namespace for external input classes.
     *
     * @param $prefix
     */
    public static function addNamespacePrefix($prefix)
    {
        static::$namespacePrefix[] = $prefix;
    }

    /**
     * @param $name
     * @param $value
     * @param $attrs
     * @return \Key\Interfaces\InputInterface
     */
    public static function getInstance($name, $value, $attrs)
    {
        if (!is_array($attrs)) {
            $attrs = array();
        }

        $type = isset($attrs['type']) && $attrs['type'] ? strtolower($attrs['type']) : 'string';

        $className = static::getInputClass($type);

        return new $className($name, $value, $attrs);
    }

    /**
     * Get input class from module.
     * @param string $type
     * @param \Key\Container $app
     * @return false|string
     */
    protected static function getInputClassFromModule($type, $app)
    {
        if ($app->offsetExists('appName') && $app->offsetExists('modules')) {
            $moduleName = $app['appName'];
            /** @var \Key\Foundation\Module $module */
            $module = $app['modules'][$moduleName];
            return $module->getRecordClass($type);
        }
        return false;
    }

    /**
     * Get usable input class name by type.
     *
     * @param string $type Type of input configure.
     * @param bool $useDefaultIfNotFound if set true, when class not found, it will return default Input class
     * @param \Key\Container $app
     * @return null|string
     */
    public static function getInputClass($type = 'string', $useDefaultIfNotFound = false, $app = null)
    {
        $type = ucfirst($type);

        $className = $app ? self::getInputClassFromModule($type, $app) : null;
        if ($className && class_exists($className)) {
            return $className;
        }

        // If it is set as \App\Records\Example, we should check it first.
        if ($type != 'Datetime' && class_exists($type)) {
            $className = $type;
        } else {
            foreach (static::$namespacePrefix as $prefix) {
                if (class_exists($prefix . ucfirst($type). 'Input')) {
                    $className = $prefix . ucfirst($type) . 'Input';
                    break;
                } elseif (class_exists($prefix . ucfirst($type))) {
                    $className = $prefix . ucfirst($type);
                    break;
                }
            }
        }

        if (!$className && $useDefaultIfNotFound) {
            $className = '\\Key\\Inputs\\Input';
        }

        return $className;
    }

    /**
     * Check if the type is base defined.
     *
     * @param string $type
     * @return bool
     */
    public static function isBaseType($type)
    {
        return class_exists('\\Key\\Inputs\\' . ucfirst($type) . 'Input');
    }
}