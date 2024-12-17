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



use Key\Inputs\Input;

class IntTransfer extends Transfer
{
    protected $format = '/^[+|-]?\d+$/';

    /**
     * Get input handler.
     *
     * @return Input
     */
    protected function getInputClassName()
    {
        return '\\Key\\Inputs\\IntInput';
    }

    /**
     * Convert data for database.
     * @return mixed
     */
    public function convert()
    {
        if (is_string($this->value)) {
            if ($map = $this->getProperty('map')) {
                $index = array_search($this->value, $map);
                if ($index !== false) {
                    $this->value = $index;
                }
            }
        }

        return intval($this->value);
    }

    /**
     * Convert data to output.
     *
     * @return mixed
     */
    public function output()
    {
        if ($map = $this->getProperty('map')) {
            if (isset($map[intval($this->value)])) {
                return $map[intval($this->value)];
            }
        }
        return intval($this->value);
    }

    /**
     * Validate the value.
     *
     * @param mixed $valid_value Returns valid value if validated
     * @return int
     */
    public function validate(&$valid_value)
    {
        if (self::VALIDATED_SUCCESS === $this->input->validate()) {
            if (is_int($this->value)) {
                $valid_value = $this->value;
                return self::VALIDATED_SUCCESS;
            } else {
                if (!is_object($this->value)) {
                    $val = $this->value;
                    if ($this->isStrict()) {
                        if (!preg_match($this->format, $val)) {
                            // Invalid value
                            return self::VALIDATED_FAILURE;
                        }
                    }
                    $valid_value = $this->convert($this->value);
                    return self::VALIDATED_SUCCESS;
                } else {
                    return self::VALIDATED_FAILURE;
                }
            }
        }

        return self::VALIDATED_FAILURE;
    }


}