<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace Key\Abstracts;


abstract class Validator
{
    protected $value;
    protected $properties;

    /**
     * Validator constructor.
     * @param $value
     * @param $properties
     */
    public function __construct($value, $properties)
    {
        $this->value = $value;
        $this->properties = is_array($properties) ? $properties : array();
    }

    /**
     * Validate the value.
     *
     * @return int
     */
    abstract public function validate();
}