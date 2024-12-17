<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace Key\Log;


use LoggerConfigurator;
use LoggerHierarchy;
use LoggerAppenderFile;
use LoggerLayoutPattern;
use LoggerLevel;

/**
 * Class BaseLoggerConfigurator
 *
 * @package Key\Log
 * @author Guanghui Li <liguanghui2006@163.com>
 */
class BaseLoggerConfigurator implements LoggerConfigurator
{

    protected static $defaults = array(
        'baseDir' => DATA_PATH,
        'pattern' => '%date %logger %p %msg%newline',
        'daily' => false,
        'loggerLevel' => 'ALL'
    );

    protected static $levelMap = array(
        'FATAL' => LoggerLevel::FATAL,
        'ERROR' => LoggerLevel::ERROR,
        'WARN' => LoggerLevel::WARN,
        'INFO' => LoggerLevel::INFO,
        'DEBUG' => LoggerLevel::DEBUG,
        'TRACE' => LoggerLevel::TRACE,
        'ALL' => LoggerLevel::ALL,
        'OFF' => LoggerLevel::OFF
    );

    /**
     * Logger name.
     *
     * @var string
     */
    protected $name;

    /**
     * Configuration.
     *
     * @var array
     */
    protected $options;

    /**
     * Logger configuration constructor.
     *
     * @param string $name
     * @param null|array $options
     */
    public function __construct($name, $options = null)
    {
        $this->name = $name;
        $this->options = $this->filterOptions($options);
    }

    /**
     * Filter the configuration options.
     *
     * @param null|array $options
     * @return array
     */
    protected function filterOptions($options)
    {
        if (empty($options) || !is_array($options)) $options = array();

        $options = array_replace(static::$defaults, $options);

        $level = LoggerLevel::ALL;
        if (isset($options['loggerLevel'])) {
            $loggerLevel = strtoupper($options['loggerLevel']);
            $level = isset(static::$levelMap[$loggerLevel]) ? static::$levelMap[$loggerLevel] : LoggerLevel::OFF;
        }
        $options['loggerLevel'] = $level;

        return $options;
    }

    /**
     * Configures log4php based on the given configuration.
     *
     * All configurators implementations must implement this interface.
     *
     * @param LoggerHierarchy $hierarchy The hierarchy on which to perform
     *        the configuration.
     * @param mixed $input Either path to the config file or the
     *        configuration as an array.
     */
    public function configure(LoggerHierarchy $hierarchy, $input = null)
    {

        $logPath = $this->options['baseDir'] . DS . 'logs' . DS;
        if ($this->options['daily']) {
            $logPath .= date('Y') . DS . date('m') . DS . date('d') . DS;
        }

        // Create an appender which logs to file
        $appFile = new LoggerAppenderFile($this->name);
        $appFile->setFile($logPath.$this->name.'.log');
        $appFile->setAppend(true);
        $appFile->setThreshold($this->options['loggerLevel']); // Logger level
        $appFile->activateOptions();

        $layout = new LoggerLayoutPattern();
        $layout->setConversionPattern($this->options['pattern']);
        $layout->activateOptions();

        $appFile->setLayout($layout);

        $root = $hierarchy->getRootLogger();
        $root->addAppender($appFile);
    }

}