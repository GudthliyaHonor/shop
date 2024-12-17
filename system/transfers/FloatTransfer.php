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


class FloatTransfer extends IntTransfer
{
    protected $format = '/^[+|-]?\d+(\.\d*)$/';

    /**
     * Get input handler.
     *
     * @return Input
     */
    protected function getInputClassName()
    {
        return '\\Key\\Inputs\\FloatInput';
    }

    /**
     * Convert data for database.
     * @return mixed
     */
    public function convert()
    {
        return floatval($this->value);
    }

    /**
     * Convert data to output.
     *
     * @return mixed
     */
    public function output()
    {
        return floatval($this->value);
    }

}