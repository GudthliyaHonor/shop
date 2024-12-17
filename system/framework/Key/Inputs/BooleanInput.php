<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2021 keylogic.com
 * @version 1.0.0
 * @link https://www.yidianzhishi.com
 */
namespace Key\Inputs;


/**
 * Class BooleanInput
 * @package Key\Inputs
 */
class BooleanInput extends Input
{
    const TRUE_DEFAULT_VALUE = true;
    const FALSE_DEFAULT_VALUE = false;

    const TRUE_STRING = 'true';
    const FALSE_STRING = 'false';

    const NUMBER_REGEXP = '/^[-]?\d+$/';

    public function getTrueDefaultValue()
    {
        if ($this->has('trueDefaultValue')) {
            return $this->get('trueDefaultValue');
        }
        return self::TRUE_DEFAULT_VALUE;
    }

    public function getFalseDefaultValue()
    {
        if ($this->has('falseDefaultValue')) {
            return $this->get('falseDefaultValue');
        }
        return self::FALSE_DEFAULT_VALUE;
    }

    /**
     * Validate the value for input.
     *
     * @return int validation result code.
     */
    public function validate()
    {
        $trueDefaultValue = $this->getTrueDefaultValue();

        if (is_null($this->value) && !is_null($this->getDefaultValue())) {
            $this->value = $this->getDefaultValue();
        }

        if (is_int($trueDefaultValue)) {
            if (preg_match(self::NUMBER_REGEXP, $this->value)) {
                $value = (int) $this->value;
                if ($value === $trueDefaultValue) {
                    $this->value = $value;
                    $this->validatedCode = static::VALID_CODE_SUCCESS;
                    return static::VALID_CODE_SUCCESS;
                }
            }
        }
        $falseDefaultValue = $this->getFalseDefaultValue();
        if (is_int($falseDefaultValue)) {
            if (preg_match(self::NUMBER_REGEXP, $this->value)) {
                $value = (int) $this->value;
                if ($value === $falseDefaultValue) {
                    $this->value = $value;
                    $this->validatedCode = static::VALID_CODE_SUCCESS;
                    return static::VALID_CODE_SUCCESS;
                }
            }
            $this->value = $falseDefaultValue;
            return static::VALID_CODE_SUCCESS;
        }

        $value = strtolower($this->value);
        if (strcmp($value, self::TRUE_STRING) == 0) {
            $this->value = true;
        } elseif (strcmp($value, self::FALSE_STRING) == 0) {
            $this->value = false;
        } else {
            $this->validatedCode = static::INVALID_CODE_FORMAT;
            return static::INVALID_CODE_FORMAT;
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
            return $this->value;
        }
        return null;
    }   
}