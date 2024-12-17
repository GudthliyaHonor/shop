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
 * Class MixedInput
 * @package Key\Inputs
 */
class MixedInput extends Input
{


    /**
     * Get max length setting for the input.
     *
     * @return int
     */
    public function getMaxLength()
    {
        return null;
    }

    /**
     * Get min length setting for the input.
     *
     * @return int
     */
    public function getMinLength()
    {
        return null;
    }

    /**
     * Get fixed value setting for the input.
     *
     * @return string
     */
    public function getFixedValue()
    {
        $value = parent::getFixedValue();
        if ($value == null) $value = '';
        return $value;
    }

    /**
     * Get valid value for the input.
     *
     * @return mixed|null
     */
    public function getValidValue()
    {
        $valid = null;

        if ($this->getFixedValue()) {
            $valid = $this->getFixedValue();
        } else {
            $code = $this->validate();

            switch($code) {
                case static::INVALID_CODE_REQUIRED:
                    if ($this->getDefaultValue()) {
                        $valid = $this->getDefaultValue();
                    }
                    break;
                case static::VALID_CODE_SUCCESS:
                    $valid = $this->value;
                    break;
                default:
                    //...
            }
        }

        return $valid;
    }

}