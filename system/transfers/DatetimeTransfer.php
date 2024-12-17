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


use MongoDB\BSON\UTCDateTime;

class DatetimeTransfer extends Transfer
{
    /**
     * Get input handler.
     *
     * @return Input
     */
    protected function getInputClassName()
    {
        return '\\Key\\Inputs\\DatetimeInput';
    }

    public function input()
    {
        $timestamp = strtotime($this->value);
        return  (!$timestamp)? new UTCDateTime(time() * 1000): new UTCDateTime($timestamp * 1000);
    }
    /**
     * Convert data for database.
     * @return \MongoDB\BSON\UTCDateTime
     */
    public function convert()
    {
        $timestamp = strtotime($this->value);
        return  (!$timestamp)? new UTCDateTime(time() * 1000): new UTCDateTime($timestamp * 1000);
    }

    /**
     * Convert data to output.
     * @return false|string
     */
    public function output()
    {
        if (is_string($this->value)) {
            if ($this->value && !preg_match('#^\d+$#', $this->value)) {
                $date = date_create($this->value);
                if (!$date) {
                    //Tools::error('[DatetimeTransfer] create date fail: ' . var_export($this->value, true));
                    //Tools::error('[DatetimeTransfer] use default date');
                    $date = date_create();
                }
                $value = $date->getTimestamp();
            } else {
                $value = (string)$this->value / 1000;
            }
        } else if ($this->value instanceof \MongoDB\BSON\UTCDateTime) {
            $date = $this->value->toDateTime();
            $value = $date->getTimestamp();
        } else {
            error_log('[DatetimeTransfer] Invalid datetime value: ' . $this->getProperty('name') . '>>'  . var_export($this->value, true));
            $value = 0;
        }

        if ($this->getProperty('format')) {
            return date($this->getProperty('format'), $value);
        } else {
            return date('Y-m-d', $value);
        }

    }

    /**
     * Check has_time property
     * @return mixed
     */
    protected function hasTime()
    {
        return $this->getProperty('has_time', false);
    }

    /**
     * Check excel_time property.
     *
     * @return bool
     */
    protected function fromExcelTime()
    {
        return !!$this->getProperty('excel_time', true);
    }

    /**
     * Convert datetime from excel time.
     *
     * @param string $date Excel time string
     * @param bool $time
     * @param string $format Date format
     * @return array|int|string
     */
    protected function convertFromExcelTime($date, $time = false, $format = 'Y/m/d')
    {
        if (function_exists('GregorianToJD')) {
            if (is_numeric($date)) {
                $jd = GregorianToJD(1, 1, 1970);
                $gregorian = JDToGregorian($jd + intval($date) - 25569);
                $date = explode('/', $gregorian);
                $date_str = str_pad($date [2], 4, '0', STR_PAD_LEFT)
                    . "-" . str_pad($date [0], 2, '0', STR_PAD_LEFT)
                    . "-" . str_pad($date [1], 2, '0', STR_PAD_LEFT)
                    . ($time ? " 00:00:00" : '');
                return $date_str;
            }
        } else {
            $date = $date > 25568 ? $date + 1 : 25569;
            /*There was a bug if Converting date before 1-1-1970 (tstamp 0)*/
            $ofs = (70 * 365 + 17 + 2) * 86400;
            $date = date($format, ($date * 86400) - $ofs) . ($time ? " 00:00:00" : '');
        }

        return $date;
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

            if (is_string($valid_value)) {
                $val = $valid_value;
                if ($this->fromExcelTime()) {
                    $val = $this->convertFromExcelTime($val, $this->hasTime());
                }
                if ($val) {
                    $val = date_create($val);
                    if ($val) {
                        $valid_value = new UTCDateTime($val->getTimestamp() * 1000);
                    } else {
                        // Invalid datetime string
                        return self::VALIDATED_FAILURE;
                    }
                } else {
                    $valid_value = null;
                }
            } else {
                // Converted value
                if ($this->value instanceof UTCDateTime) {
                    $valid_value = $this->value;
                } else {
                    // Invalid data type
                    return self::VALIDATED_FAILURE;
                }
            }

        }

        return self::VALIDATED_SUCCESS;
    }
}