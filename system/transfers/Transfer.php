<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace App\Transfers;


use App\Common\Constants;
use Key\Inputs\Input;

abstract class Transfer
{
    const VALIDATED_SUCCESS = Input::VALID_CODE_SUCCESS;
    const VALIDATED_FAILURE = Input::VALID_CODE_UNDEFINED;

    const DATA_TYPE_INT = Constants::FIELD_DATA_TYPE_INT;
    const DATA_TYPE_FLOAT = Constants::FIELD_DATA_TYPE_FLOAT;
    const DATA_TYPE_STRING = Constants::FIELD_DATA_TYPE_STRING;
    const DATA_TYPE_DATETIME = Constants::FIELD_DATA_TYPE_DATETIME;
    const DATA_TYPE_ARRAY = Constants::FIELD_DATA_TYPE_ARRAY;
    const DATA_TYPE_OBJECT = Constants::FIELD_DATA_TYPE_OBJECT;
    const DATA_TYPE_BOOL = Constants::FIELD_DATA_TYPE_BOOL;
    const DATA_TYPE_NULL = Constants::FIELD_DATA_TYPE_NULL;
    const DATA_TYPE_REGEX = Constants::FIELD_DATA_TYPE_REGEX;

    public static $data_types = array(
        self::DATA_TYPE_INT => 'int',
        self::DATA_TYPE_FLOAT => 'float',
        self::DATA_TYPE_STRING => 'string',
        self::DATA_TYPE_DATETIME => 'datetime',
        self::DATA_TYPE_ARRAY => 'array',
        self::DATA_TYPE_OBJECT => 'object',
        self::DATA_TYPE_BOOL => 'bool',
        self::DATA_TYPE_NULL => 'null',
        self::DATA_TYPE_REGEX =>'regEx',
    );

    protected $dummyName;

    /**
     * @var
     */
    protected $value;

    /**
     * @var
     */
    protected $properties;

    protected $type;

    /** @var \Key\Inputs\Input */
    protected $input;

    /**
     * Transfer constructor.
     * @param $value
     * @param $properties
     */
    public function __construct($value, $properties)
    {
        if (is_string($value)) {
            trim($value);
        }
        if (!is_array($properties)) {
            $properties = array();
        }
        $this->setValue($value);
        $this->setProperties($properties);

        $this->dummyName = randomChars(10, 'lowchar');

        $inputClass = $this->getInputClassName();
        $this->input = new $inputClass($this->dummyName, $value, $properties);
    }

    /**
     * Get input handler.
     *
     * @return Input
     */
    protected function getInputClassName()
    {
        return '\\Key\\Inputs\\Input';
    }

    /**
     * Convert data for database.
     * @return mixed
     */
    abstract public function convert();

    /**
     * Convert data to output.
     *
     * @return mixed
     */
    abstract public function output();

    /**
     * @param $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * @param $properties
     */
    public function setProperties($properties)
    {
        $this->properties = $properties;
    }

    /**
     * Get property value.
     *
     * @param $name
     * @param mixed $default
     * @return mixed
     */
    public function getProperty($name, $default = null)
    {
        return $this->properties && isset($this->properties[$name]) ? $this->properties[$name] : $default;
    }

    /**
     * Check required property.
     *
     * @return bool
     */
    protected function isRequired()
    {
        return !!$this->getProperty('required');
    }

    /**
     * Check validation mode.
     *
     * @return bool
     */
    protected function isStrict()
    {
        return !!$this->getProperty('strict', true);
    }

    /**
     * Check if the value is empty, such as '', null or
     *
     * @return bool
     */
    protected function isEmpty()
    {
        return empty($this->value) && $this->value !== 0 && $this->value !== 0.0 && $this->value !== '0';
    }

    /**
     * Validate the value.
     *
     * @param mixed $valid_value Returns valid value if validated
     * @return int
     */
    public function validate(&$valid_value)
    {
        $valid_value = $this->value;

        return $this->input->validate();
    }

}
