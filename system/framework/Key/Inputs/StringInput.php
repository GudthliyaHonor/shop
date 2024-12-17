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
 * Class StringInput
 * @package Key\Inputs
 */
class StringInput extends Input
{
    /**
     * Input construct.
     *
     * @param string $name
     * @param mixed $value
     * @param array|null $inputConfig
     * @throws \InvalidArgumentException
     */
    public function __construct($name, $value, $inputConfig = null, $validatedData = null, $parent = null)
    {
        if (is_object($value) && in_array("__toString", get_class_methods($value))) {
            $value = strval($value->__toString());
        } else {
            if (is_array($value)) {
                error_log('Array to string conversion: ' . $name . '-' . var_export($value, true));
                $value = json_encode($value);
            } else {
                $value = strval($value);
            }
        }

        if (!is_array($inputConfig) || !array_key_exists('trim', $inputConfig) || isset($inputConfig['trim']) && $inputConfig['trim']) {
            $value = trim($value);
        }

        parent::__construct($name, $value, $inputConfig, $validatedData, $parent);
    }


    /**
     * Get max length setting for the input.
     *
     * @return int
     */
    public function getMaxLength()
    {
        $maxlength = $this->get('maxlength');
        return intval($maxlength, 10);
    }

    /**
     * Get min length setting for the input.
     *
     * @return int
     */
    public function getMinLength()
    {
        $minlength = $this->get('minlength');
        return intval($minlength, 10);
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
        return (string)$value;
    }

    /**
     * Validate the value for input.
     *
     * @return int validation result code.
     */
    public function validate()
    {
        $valid = parent::validate();
        if ($valid === static::VALID_CODE_SUCCESS) {
            if (!is_string($this->value)) {
                error_log('[WARN] invalid string: ' . var_export($this->value, true) . ' check default setting!');
            }
            $len = mb_strlen($this->value);
            if ($this->getMinLength() && $len < $this->getMinLength()) {
                return static::INVALID_CODE_MINLENGTH;
            }

            if ($this->getMaxLength() && $len > $this->getMaxLength()) {
                return static::INVALID_CODE_MAXLENGTH;
            }

            $map = $this->getMap();
            if ($map) {
                if (!in_array($this->value, $map->all())) {
                    return static::INVALID_CODE_MAP;
                }
            }
            $enum = $this->getEnum();
            if ($enum) {
                if ($this->value || $this->isRequired()) {
                    if (!in_array($this->value, $enum->all())) {
                        return static::INVALID_CODE_MAP;
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
     * @return mixed|null
     */
    public function getValidValue()
    {
        $valid = null;

        if ($this->getFixedValue()) {
            $valid = (string) $this->getFixedValue();
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
