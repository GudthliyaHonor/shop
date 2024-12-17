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
class DatetimeInput extends Input
{

    protected $validDatetime;

    /**
     * Get input format setting.
     *
     * @return mixed
     */
    public function getFormat()
    {
        return $this->get('format') ?: $this->getPattern();
    }

    /**
     * Check has_time property
     * @return mixed
     */
    protected function hasTime()
    {
        return $this->get('has_time');
    }

    protected function renderValue($value)
    {
        $render = $this->get('render');
        if ($render) {
            switch ($render) {
                case '\\MongoDB\\BSON\\UTCDateTime':
                    $time = strtotime($value);
                    if ($time) {
                        $value = new \MongoDB\BSON\UTCDateTime($time * 1000);
                    } else {
                        $value = null;
                    }
                break;
                case 'Datetime':
                case '\\Datetime':
                    if (is_string($value)) {
                        $value = new \DateTime($value);
                    } elseif ($value instanceof \DateTime) {
                        // do nothing
                    }
                break;
                case 'timestamp':
                    if (is_string($value)) {
                        $value = strtotime($value);
                    }
                    elseif ($value instanceof \DateTime) {
                        $value = $value->getTimestamp();
                    }
                    break;
                default:
                    if ($render && $value) {
                        try {
                            $value = date($render, (int) $value);
                        } catch (\Exception $ex) {
                            error_log('render fail: ' . $ex->getMessage());
                        }
                    }
            }
        }
        return $value;
    }

    /**
     * Format validation for the value.
     *
     * @return int
     */
    protected function formatValidate()
    {
        if ($this->get('from_excel')) {
            $this->value = $this->convertFromExcelTime($this->value, $this->hasTime(), $this->get('format'));
        }

        if ($this->value) {
            $formats = $this->getFormat();
            if (!is_array($formats)) {
                $formats = [$formats];
            }
            $matched = false;
            foreach ($formats as $format) {
                error_log('formatValidate format: ' . $format);
                if ((($date = date_create_from_format($format, $this->value)) !== false &&
                checkdate($date->format('m'), $date->format('d'), $date->format('Y')))) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return static::INVALID_CODE_FORMAT;
            }
        }
        // if ($this->value && ($format = $this->getFormat()) &&
        //     (($date = date_create_from_format($format, $this->value)) === false ||
        //         !checkdate($date->format('m'), $date->format('d'), $date->format('Y')))) {
        //     return static::INVALID_CODE_FORMAT;
        // }

        //$this->validDatetime = isset($date) ? $date : null;

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
                return $this->renderValue($default);
            }
            //return $this->validDatetime;
            return $this->renderValue($this->value);
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
        if (function_exists('GregorianToJD')) {
            if (is_numeric($date)) {
                $jd = GregorianToJD(1, 1, 1970);
                $gregorian = JDToGregorian($jd + intval($date) - 25569);
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

        return $date;
    }

}