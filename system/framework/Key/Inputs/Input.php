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


use RuntimeException;
use InvalidArgumentException;
use Key\Collection;
use Key\Constants;
use Key\Interfaces\InputInterface;

/**
 * Class Input
 * @package Key\Inputs
 */
class Input implements InputInterface
{

    /* Validation result codes */
    const VALID_CODE_UNFINISHED = -1;

    const VALID_CODE_SUCCESS = 0;

    const VALID_CODE_UNDEFINED = 1;
    const INVALID_CODE_REQUIRED = 2;
    const INVALID_CODE_FORMAT = 3;
    const INVALID_CODE_MAP = 4;
    const INVALID_CODE_ENUM = 5;
    const INVALID_CODE_ENUM_X = 6;

    const INVALID_CODE_MIN = 10;
    const INVALID_CODE_MAX = 11;
    const INVALID_CODE_SUM = 12;

    const INVALID_CODE_MINLENGTH = 20;
    const INVALID_CODE_MAXLENGTH = 21;

    const INVALID_FILE_EXTENSION = 30;

    protected $invalidCodeMap = array(
        self::VALID_CODE_UNFINISHED => 'Unvalidated',
        self::VALID_CODE_UNDEFINED => 'Undefined',
        self::INVALID_CODE_REQUIRED => 'Required',
        self::INVALID_CODE_FORMAT => 'Invalid format',
        self::INVALID_CODE_MAP => 'Not in map setting',
        self::INVALID_CODE_MIN => 'Invalid range',
        self::INVALID_CODE_MAX => 'Invalid range',
        self::INVALID_CODE_SUM => 'Invalid sum',
        self::INVALID_CODE_MINLENGTH => 'Invalid string length',
        self::INVALID_CODE_MAXLENGTH => 'Invalid string length',
        self::INVALID_FILE_EXTENSION => 'Invalid file extension'
    );

    /**
     * Base types definition.
     *
     * @var array
     */
    static protected $baseTypes = array(
        self::TYPE_STRING, self::TYPE_INT, self::TYPE_FLOAT, self::TYPE_ARRAY, self::TYPE_DATETIME, self::TYPE_FILE,
        self::TYPE_MIXED, self::TYPE_AUTO
    );

    /**
     * Input name.
     *
     * @var string
     */
    protected $name;


    protected $originValue;

    /**
     * Input value.
     *
     * @var mixed
     */
    protected $value;

    /**
     * Input configure
     *
     * @var array
     */
    protected $config;

    /**
     * @var int
     */
    protected $validatedCode = self::VALID_CODE_UNFINISHED;

    /**
     * @var array
     */
    protected $validatedData = null;

    /**
     * @var \Pimple\Container
     */
    protected $app;

    protected $parent;

    /**
     * Input construct.
     *
     * @param string $name
     * @param mixed $value
     * @param array|null $inputConfig
     * @param array|null $validatedData Data of valiated
     * @throws InvalidArgumentException
     */
    public function __construct($name, $value, $inputConfig = null, $validatedData = null, $parent = null)
    {
        if (is_null($name) || !is_string($name) || ($name = trim($name)) === '') {
            error_log(var_export(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), true));
            throw new InvalidArgumentException('Invalid Input name: '.$name);
        }

        $this->name = $name;
        $this->value = $value;
        $this->originValue = $value;
        $this->validatedData = $validatedData;
        $this->parent = $parent;

        if (!is_array($inputConfig)) {
            $inputConfig = array();
        }

        $this->config = $inputConfig;
        //parent::__construct($inputConfig);
        // date_default_timezone_set(env('APP_DEFAULT_TIMEZONE', 'Asia/Shanghai'));
    }

    /**
     * Set container.
     *
     * @param \Key\Container $app
     * @return $this
     */
    public function setApp($app)
    {
        $this->app = $app;
        return $this;
    }

    /**
     * Get configure value.
     *
     * @param string $name Input configure name.
     * @return mixed|null
     */
    public function __get($name)
    {
        return isset($this->config[$name]) ? $this->config[$name] : null;
    }

    /**
     * Get configure value.
     *
     * @param string $name Input configure name.
     * @return mixed|null
     */
    public function get($name)
    {
        return $this->__get($name);
    }

    /**
     * Check if the name exists in the input configure.
     *
     * @param string $name Input configure name
     * @return bool
     */
    public function has($name)
    {
        return array_key_exists($name, $this->config);
    }
    /**
     * Get input name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Check if the input is required.
     *
     * @return bool
     */
    public function isRequired()
    {
        $required = $this->get('required');
        if (is_bool($required)) {
            return $required;
        } else if (is_string($required)) {
            $required = strtolower($required);
            return $required == 'true';
        } else {
            return !!$required;
        }
    }

    public function setRequired($required)
    {
        $this->config['required'] = $required ? 1 : 0;
        return $this;
    }

    /**
     * Get input description.
     *
     * @return mixed
     */
    public function getDescription()
    {
        return $this->get('description');
    }

    /**
     * Get input type.
     *
     * @return string
     */
    public function getType()
    {
        $type = $this->get('type');
        return $type && is_string($type) ? strtolower($type) : static::TYPE_STRING;
    }

    /**
     * Get input enum setting.
     *
     * @return null|Collection
     */
    public function getEnum()
    {
        $enum = $this->get('enum');

        if ($this->isEmpty($enum)) {
            return null;
        }

        if (is_string($enum)) {
            $enums = explode(',', $enum);
            $tmp = array();
            foreach ($enums as $item) {
                $tmp[] = trim($item);
            }
            $enum = $tmp;
        } elseif (!is_array($enum)) {
            //error_log('[getEnum] Invalid enums: ' . var_export($enum, true));
            $enum = array($enum);
        }
        return new Collection($enum);
    }

    /**
     * Get the map of the input
     *
     * @return Collection|null
     */
    public function getMap()
    {
        $map = $this->get('map');
        if ($this->isEmpty($map)) {
            return null;
        }

        if (is_string($map)) {
            $enums = explode(',', $map);
            $tmp = array();
            foreach ($enums as $item) {
                $tmp[] = trim($item);
            }
            $map = $tmp;
        } elseif (!is_array($map)) {
            $map = array($map);
        }
        return new Collection($map);
    }

    public function setMap($map)
    {
        $this->config['map'] = $map;
        return $this;
    }

    /**
     * Get the default value of the input
     *
     * @return mixed
     */
    public function getDefaultValue()
    {
        return $this->get('default');
    }

    public function setDefaultValue($value)
    {
        $this->config['default'] = $value;
        return $this;
    }

    /**
     * Get input fixed value.
     *
     * @return mixed
     */
    public function getFixedValue()
    {
        return $this->get('fixedValue');
    }

    /**
     * Get input format setting.
     *
     * @return mixed
     */
    public function getFormat()
    {
        return $this->get('format');
    }

    public function setFormat($format)
    {
        $this->config['format'] = $format;
        return $this;
    }

    /**
     * Set valiation pattern.
     *
     * @param string $pattern
     * @return $this
     */
    public function setPattern($pattern)
    {
        $this->config['pattern'] = $pattern;
        return $this;
    }

    public function getPattern()
    {
        return $this->get('pattern');
    }

    public function setRender($render)
    {
        $this->config['render'] = $render;
        return $this;
    }

    public function getRender()
    {
        return $this->get('render');
    }

    /**
     * Check the input type.
     *
     * @param string $type
     * @return mixed
     * @throws RuntimeException
     */
    public function isType($type)
    {
        if (is_string($type)) {
            $type = ucfirst(strtolower($type));
            $method = 'is' . $type . 'Type';
            if (method_exists($this, $method)) {
                return $this->$method();
            }
        }
        throw new RuntimeException('Type should be a string', Constants::SYS_INTERNAL);
    }

    /**
     * Check if the input type is String.
     *
     * @return bool
     */
    public function isStringType()
    {
        return static::TYPE_STRING == $this->getType();
    }

    /**
     * Check if the input type is Int.
     *
     * @return bool
     */
    public function isIntType()
    {
        return static::TYPE_INT == $this->getType();
    }

    /**
     * Check if the input type is Float.
     *
     * @return bool
     */
    public function isFloatType()
    {
        return static::TYPE_FLOAT == $this->getType();
    }

    /**
     * Check if the input type is Array.
     *
     * @return bool
     */
    public function isArrayType()
    {
        return static::TYPE_ARRAY == $this->getType();
    }

    /**
     * Check if the input type is Pagination.
     *
     * @return bool
     */
//    public function isPaginationType()
//    {
//        return static::TYPE_PAGINATION == $this->getType();
//    }

    /**
     * Check if the input type is File.
     *
     * @return bool
     */
    public function isFileType()
    {
        return static::TYPE_FILE == $this->getType();
    }

    /**
     * Check if the input type is Datetime.
     *
     * @return bool
     */
    public function isDatetimeType()
    {
        return static::TYPE_DATETIME == $this->getType();
    }

    /**
     * Check if the value is empty.
     *
     * @param string $str
     * @return bool
     */
    protected function isEmpty($str)
    {
        return !isset($str) || is_null($str) || is_string($str) && $str === '' || is_array($str) && count($str) == 0;
    }

    /**
     * Format validation for the value.
     *
     * @return int
     */
    protected function formatValidate()
    {
        if ($format = $this->getFormat()) {
            if (($this->isRequired() || strlen($this->value)) && preg_match($format, $this->value) == 0) {
                return static::INVALID_CODE_FORMAT;
            }
        }

        if ($pattern = $this->getPattern()) {
            if (($this->isRequired() || strlen($this->value)) && preg_match($pattern, $this->value) == 0) {
                return static::INVALID_CODE_FORMAT;
            }
        }

        return static::VALID_CODE_SUCCESS;
    }

    /**
     * Validate the value for input.
     *
     * @return int validation result code.
     */
    public function validate()
    {
        if ($this->getFixedValue()) {
            return static::VALID_CODE_SUCCESS;
        }
        if ($this->isRequired()) {
            if (!$this->getDefaultValue()) {
                if ($this->isEmpty($this->value)) {
                    return static::INVALID_CODE_REQUIRED;
                }
            }
        } elseif ($this->getDefaultValue() && $this->isEmpty($this->value)) {
            $this->value = $this->getDefaultValue();
        }
        if (($result = $this->formatValidate()) !== static::VALID_CODE_SUCCESS) {
            return $result;
        }

        if ($map = $this->getMap()) {
            if ($map->count() > 0) {
                if (array_search($this->value, $map->all()) === false
                && array_search(intval($this->value), $map->keys()) === false) {// if match the key of map for data import
                    return static::INVALID_CODE_MAP;
                }
            } else {
                //error_log('[WARNING] Invalid enum value for '.$this->name);
            }
        }

        $this->validatedCode = static::VALID_CODE_SUCCESS;

        return static::VALID_CODE_SUCCESS;
    }

    /**
     * Get valid value for the input.
     *
     * @return mixed|null
     */
    public function getValidValue()
    {
        if ($this->validatedCode === static::VALID_CODE_UNFINISHED) {
            $this->validate();
        }

        if ($this->validatedCode === static::VALID_CODE_SUCCESS) {
            if ($this->isEmpty($this->value) && $default = $this->getDefaultValue()) {
                return $default;
            }
            return $this->value;
        }

        return null;
    }

    /**
     * `getValidValue' alias.
     *
     * @return mixed|null
     */
    public function getValue()
    {
        return $this->getValidValue();
    }

    /**
     * Get original value.
     *
     * @return mixed
     */
    public function getOriginalValue()
    {
        return $this->originValue;
    }

    /**
     * Get invalid code phrase.
     *
     * @return string
     */
    public function getPhrase()
    {
        if ($this->validatedCode !== static::VALID_CODE_SUCCESS) {
            return isset($this->invalidCodeMap[$this->validatedCode]) ? $this->invalidCodeMap[$this->validatedCode] : '';
        }

        return '';
    }

    /**
     * Check if current input type is base type.
     *
     * @param $type
     * @return bool
     */
    static public function isBaseType($type)
    {
        if ($type) {
            $type = strtolower($type);
            return in_array($type, static::$baseTypes);
        }

        return false;
    }

    public static function isInternalType($type)
    {
        if (strcasecmp($type, 'pagination') === 0) return true;
        $className = '\\Key\\Inputs\\' . ucfirst($type) . 'Input';
        if (class_exists($className)) {
            return true;
        }
        return false;
    }

    /**
     * Convert the value to match the input type.
     *
     * @return mixed
     */
    public function convert()
    {
        return $this->value;
    }

    public function getContract()
    {
        return $this->get('contract');
    }

    public function setContract($contract)
    {
        $this->config['contract'] = $contract;
        return $this;
    }

    /**
     * Determine if the value is valid for the column field.
     *
     * @return bool
     */
    public function isValid()
    {
        return $this->validatedCode === self::VALID_CODE_SUCCESS;
    }

    /**
     * Get the validated code.
     *
     * @return int
     */
    public function getResultCode()
    {
        return $this->validatedCode;
    }
}