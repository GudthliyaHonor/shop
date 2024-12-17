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


/**
 * Class DatetimeInput
 * @package Key\Inputs
 */
class ExcelDatetimeInput extends DatetimeInput
{

    protected $converted = false;

    protected $validDatetime;

    /**
     * Check has_time property
     * @return mixed
     */
    protected function hasTime()
    {
        return $this->get('has_time', false);
    }

    /**
     * Format validation for the value.
     *
     * @return int
     */
    protected function formatValidate()
    {
        $this->value = $this->convertFromExcelTime($this->value, $this->hasTime(), $this->get('format'));
        if ($this->value && ($format = $this->getFormat()) &&
            (($date = date_create_from_format($format, $this->value)) === false ||
                !checkdate($date->format('m'), $date->format('d'), $date->format('Y')))) {
                $this->converted = true;
            return static::INVALID_CODE_FORMAT;
        }

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
            //return $this->validDatetime;
            // if (preg_match('/^\d+$/', $this->value)) { // for example: 1483200000
                $this->value = $this->renderValue($this->value);
            // }
            return $this->value;
        }

        return null;
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
        if (preg_match('#^\d+$#', $date) && class_exists('\\PhpOffice\\PhpSpreadsheet\\Shared\\Date')) {
            $date1 = new \DateTime('1990-01-01');
            $date2 = new \DateTime('9999-12-31');
            $interval = (array) $date1->diff($date2);
            var_dump($interval);
            if ($interval['days'] > $date) {
                $timezone = env('APP_DEFAULT_TIMEZONE', 'Asia/Shanghai');
                $timestamp = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp((float)$date, $timezone);
                $date = date($format, $timestamp);
            }
            else {
                $date = null;
            }
        }
        else {
            if (function_exists('gregoriantojd')) {
                if (is_numeric($date)) {
                    $jd = gregoriantojd(1, 1, 1970);
                    $gregorian = jdtogregorian($jd + intval($date) - 25569);
                    $date = explode('/', $gregorian);
                    $date_str = str_pad($date [2], 4, '0', STR_PAD_LEFT)
                        . "-" . str_pad($date [0], 2, '0', STR_PAD_LEFT)
                        . "-" . str_pad($date [1], 2, '0', STR_PAD_LEFT)
                        . ($time ? " 00:00:00" : '');
                    $date = date_create($date_str);
                    return date($format, $date->getTimestamp());
                }
            } else {
                $date = $date > 25568 ? $date + 1 : 25569;
                /*There was a bug if Converting date before 1-1-1970 (tstamp 0)*/
                $ofs = (70 * 365 + 17 + 2) * 86400;
                $date = date($format, ($date * 86400) - $ofs) . ($time ? " 00:00:00" : '');
            }
        }
        return $date;
    }

    /**
     * Validate the value for the column field.
     *
     * @return bool
     */
    public function validate()
    {
        $value = trim($this->value);

        if (strlen($this->value) == 0 && $this->isRequired()) {
            $this->validatedCode = static::INVALID_CODE_REQUIRED;
            return $this->validatedCode;
        }

        if (strlen($this->value)) {
            if (is_numeric($value)) {
                $this->validatedCode = $this->formatValidate();
            } elseif (is_string($value)) {
                $this->value = strtotime($value);
                if (!$this->value) {
                    $this->validatedCode = static::INVALID_CODE_FORMAT;
                    return $this->validatedCode;
                }
            }
        }

        $this->validatedCode = static::VALID_CODE_SUCCESS;
        return $this->validatedCode;
    }

}