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


class StringTransfer extends Transfer
{
    /**
     * Get input handler.
     *
     * @return Input
     */
    protected function getInputClassName()
    {
        return '\\Key\\Inputs\\StringInput';
    }

    /**
     * Convert data to string.
     *
     * @param $value
     * @return string
     */
    protected function convertToString($value) {
        if (is_object($value) && in_array("__toString", get_class_methods($value))) {
            $value = strval($value->__toString());
        } else {
            $value = strval($value);
        }

        return trim($value);
    }

    /**
     * Convert data for database.
     * @return mixed
     */
    public function convert()
    {
        return $this->convertToString($this->value);
    }

    /**
     * Convert data to output.
     *
     * @return mixed
     */
    public function output()
    {
        return $this->convertToString($this->value);
    }
}