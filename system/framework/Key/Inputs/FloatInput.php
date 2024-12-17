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
 * Class FloatInput
 * @package Key\Inputs
 */
class FloatInput extends IntInput
{

    const NUMBER_REGEXP = '/^[-]?(\d+|\d+\.\d*|0\.\d*[1-9]\d*|0?\.0+|0)$/';

    /**
     * Get min value of input setting.
     *
     * @return float|null
     */
    public function getMin()
    {
        if ($this->has('min')) {
            $min = $this->get('min');
            if (is_numeric($min)) {
                return floatval($min);
            }
        }

        return null;
    }

    /**
     * Get max value of input setting.
     *
     * @return float|null
     */
    public function getMax()
    {
        if ($this->has('max')) {
            $max = $this->get('max');
            if (is_numeric($max)) {
                return floatval($max);
            }
        }

        return null;
    }

    /**
     * Get fixed value of input setting.
     *
     * @return bool|float
     */
    public function getFixedValue()
    {
        $fixedValue = parent::getFixedValue();
        if (is_numeric($fixedValue)) {
            return floatval($fixedValue);
        } else {
            //error_log('[getFixedValue] Invalid fixed float value: ' . var_export($fixedValue, true));
            return false;
        }
    }

    /**
     * Get valid value for the input.
     *
     * @return float
     */
    public function getValidValue()
    {
        if ($this->validatedCode === static::VALID_CODE_UNFINISHED) {
            $this->validate();
        }

        if ($this->validatedCode === static::VALID_CODE_SUCCESS) {
            if ($this->isEmpty($this->value) && $default = $this->getDefaultValue()) {
                return (float) $default;
            }
            return floatval($this->value);
        }

        return 0;
    }

    /**
     * Validate the value for input.
     *
     * @return int validation result code.
     */
    public function validate()
    {
        if (is_array($this->value) || is_object($this->value)) {
            return static::VALID_CODE_UNFINISHED;
        }

        if (($val = $this->getMapValue()) !== false) {
            $this->value = $val;
        }

        if (!$this->isEmpty($this->value) && !preg_match(static::NUMBER_REGEXP, (string)$this->value)) {
            return static::INVALID_CODE_FORMAT;
        }


        if ($this->getMin() && $this->value < $this->getMin()) {
            return static::INVALID_CODE_MIN;
        }

        if ($this->getMax() && $this->value > $this->getMax()) {
            return static::INVALID_CODE_MAX;
        }

        $this->validatedCode = static::VALID_CODE_SUCCESS;

        return static::VALID_CODE_SUCCESS;
    }


}