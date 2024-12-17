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
 * Class IntInput
 * @package Key\Inputs
 */
class IntInput extends Input
{
    const NUMBER_REGEXP = '/^[-]?\d+$/';

    /**
     * Get min value of the input setting.
     *
     * @return int|null
     */
    public function getMin()
    {
        if ($this->has('min')) {
            $min = $this->get('min');
            if (is_numeric($min)) {
                return intval($min, 10);
            }
        }

        return null;
    }

    /**
     * Get max value of the input setting.
     *
     * @return int|null
     */
    public function getMax()
    {
        if ($this->has('max')) {
            $max = $this->get('max');
            if (is_numeric($max)) {
                return intval($max, 10);
            }
        }

        return null;
    }

    /**
     * Get fixed value of the input setting.
     *
     * @return int|null
     */
    public function getFixedValue()
    {
        $fixedValue = parent::getFixedValue();
        if ($fixedValue) {
            return intval($fixedValue, 10);
        }

        return null;
    }

    /**
     * Get map value.
     *
     * @return mixed|null
     */
    protected function getMapValue()
    {
        $map = $this->get('map');
        if (is_array($map)) {
            return array_search($this->value, $map, true);
        }
        return false;
    }

    protected function hasEnum()
    {
        return $this->get('enum') ? is_array($this->get('enum')) : false;
    }

    protected function getEnumValue()
    {
        $enum = $this->get('enum');
        if (is_array($enum)) {
            $value = (int) $this->value;
            if (array_search($value, $enum, true) !== false) {
                return $value;
            }

            if (!$this->get('strict')) {
                return $value;
            }
        }
        return false;
    }

    /**
     * @return array|false
     */
    protected function getEnumX()
    {
        return $this->get('enumX');
    }

    protected function getEnumXValue()
    {
        if ($enum = $this->getEnumX()) {
            if ($enum && is_array($enum)) {
                foreach ($enum as $item) {
                    if (is_array($item)) {
                        if ($item['id'] == $this->value) {
                            return $item['id'];
                        }
                    }
                }
            }
        }
        return $this->value;
    }

    /**
     * Validate the value for input.
     *
     * @return int validation result code.
     */
    public function validate()
    {
        $valid = parent::validate();
        if ($valid == static::VALID_CODE_SUCCESS) {

            $fixedValue = $this->getFixedValue();
            if (!$this->isEmpty($fixedValue)) {
                $this->value = intval($fixedValue);
            } else {
                if (is_array($this->value) || is_object($this->value)) {
                    return static::VALID_CODE_UNFINISHED;
                } else {
                    if (($val = $this->getMapValue()) !== false) {
                        $this->value = $val;
                    } else {
                        if ($this->hasEnum()) {
                            
                            if (($val = $this->getEnumValue()) === false) {
                                return static::INVALID_CODE_ENUM;
                            } else {
                                $this->value = $val;
                            }
                        }

                        // check enumX
                        if ($this->getEnumX()) {
                            if (($val = $this->getEnumXValue()) === false) {
                                return static::INVALID_CODE_ENUM_X;
                            }
                            else {
                                $this->value = $val;
                            }
                        }

                        if (!$this->isEmpty($this->value) && !preg_match(static::NUMBER_REGEXP, (string)$this->value)) {
                            return static::INVALID_CODE_FORMAT;
                        }

                        // if (PHP_INT_MAX === (int) $this->value) {
                        //     error_log('Max numeric!');
                        //     $this->value = PHP_INT_MAX;
                        // }

                        if ($this->getMin() && $this->value < $this->getMin()) {
                            return static::INVALID_CODE_MIN;
                        }

                        if ($this->getMax() && $this->value > $this->getMax()) {
                            return static::INVALID_CODE_MAX;
                        }
                    }
                }
            }
        }

        $this->validatedCode = $valid;

        return $valid;
    }

    /**
     * Get valid value for the input.
     *
     * @return int
     */
    public function getValidValue()
    {
        return (int) parent::getValidValue();
    }

    /**
     * Convert the value to match the input type.
     *
     * @return mixed
     */
    public function convert()
    {
        return intval($this->value);
    }


}