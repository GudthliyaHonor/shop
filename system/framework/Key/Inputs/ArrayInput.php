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


use InvalidArgumentException;
use Key\Collection;
use Key\Exception\AppException;

/**
 * Class ArrayInput
 * @package Key\Inputs
 */
class ArrayInput extends Input implements \ArrayAccess
{

    const SUBTYPE_MIXED = 'mixed';

    /**
     * Input construct.
     *
     * @param string $name
     * @param mixed $value
     * @param array|null $inputConfig
     * @throws InvalidArgumentException
     */
    public function __construct($name, $value, $inputConfig = null, $validatedData = null, $parent = null)
    {
        if (!is_array($value)) {
            if (is_object($value) && in_array("__toString", get_class_methods($value))) {
                $value = strval($value->__toString());
            } else {
                $value = strval($value);
            }
            $oldValue = $value;
            $value = trim($value);
            if ($value !== '') {
                $value = str_replace('&quot;', '"', $value);
                $value = json_decode($value, true);
                // strict mode
                if (json_last_error() != JSON_ERROR_NONE || !is_array($value)) {
                    error_log(sprintf('Invalid string to convert to array - key: %s, value: %s, msg: %s', $name, json_encode($oldValue), json_last_error_msg()));
                    throw new InvalidArgumentException('Conversion from string to JSON failed: ' . $name);
                }
            }
        }

        parent::__construct($name, $value, $inputConfig, $validatedData, $parent);
    }

    // /**
    //  * Get input enum setting.
    //  * Array Input is not support it.
    //  *
    //  * @return null|Collection
    //  */
    // public function getEnum()
    // {
    //     return null;
    // }

    protected function getEnumX()
    {
        $enum = $this->get('enumX');
        return $enum && is_array($enum) ? $enum : false;
    }

    protected function getEnumXValue()
    {
        /** @var array $enum */
        if ($enum = $this->getEnumX()) {
            $values = [];
            foreach ($enum as $item) {
                if (is_array($item)) {
                    if (is_array($this->value) && array_search($item['id'], $this->value) !== false) {
                        $values[] = $item['id'];
                    }
                }
            }
            return $values;
        }
        return $this->value;
    }

    /**
     * Get input format setting.
     * Array Input is not support it.
     *
     * @return mixed
     */
    public function getFormat()
    {
        return null;
    }

    /**
     * Get input subtype property.
     *
     * @return string
     */
    public function getSubtype()
    {
        return $this->get('subtype') ? (string) $this->get('subtype') : static::SUBTYPE_MIXED;
    }

    public function getKeyExistsEvenEmptyValue()
    {
        $val = $this->get('key_exists_even_empty_value');
        return isset($val) ? !!$val : true;
    }

    public function getMaxLength()
    {
        return (int) $this->get('maxlength');
    }

    public function getMinLength()
    {
        $min = (int) $this->get('minlength');
        if ($min < 0) {
            error_log('[WARN] invalid minlength of array config, it should greater than or equal 0');
            $min = 0;
        }
        if ($min) {
            $max = $this->getMaxLength();
            if ($max && $min > $max) {
                error_log('[WARN] invalid minlength of array config');
                return $max;
            }
        }
        return $min;
    }

    /**
     * Validate the value for input.
     *
     * @return int validation result code.
     * @throws AppException
     */
    public function validate()
    {
        if (($fixedValue = $this->getFixedValue()) && is_array($fixedValue)) {
            $this->value = $fixedValue;
            $result = static::VALID_CODE_SUCCESS;
        } else if (($result = parent::validate()) === static::VALID_CODE_SUCCESS) {
            if ($detail = $this->get('detail')) {

                $_values = array();
                foreach($detail as $field => $conf) {
                    $type = isset($conf['type']) && $conf['type'] ? $conf['type'] : 'string';

                    $className = InputFactory::getInputClass($type);
                    if (class_exists($className)) {

                        $class = new $className($field, isset($this->value[$field]) ? $this->value[$field] : null, $conf, null, $this);
                        $class->setApp($this->app);
                        $r = $class->validate();

                        if ($r !== static::VALID_CODE_SUCCESS) {
                            $result = $r;
                            break;
                        }

                        $validValue = $class->getValidValue();
                        // TODO: If want to keep 0 or other empty value to the controller, We need other attribute of
                        // TODO: the configure of the Input
                        if (!empty($validValue) || $this->getKeyExistsEvenEmptyValue()) {
                            $_values[$field] = $validValue;
                        }
                    } else {
                        throw new AppException(sprintf('Only support internal input types, but given: %s', $type));
                    }
                }

                $this->value = $_values;
            } elseif (($subtype = $this->getSubtype()) !== static::SUBTYPE_MIXED) {
                $className = InputFactory::getInputClass($subtype, false, $this->app);
                if ($className) {
                    $_values = array();
                    if (is_array($this->value)) {
                        foreach($this->value as $value) {
                            $class = new $className($this->name, $value, array(
                                'type' => strtolower($subtype),
                                'required' => $this->isRequired()
                            ), null, $this);
                            $class->setApp($this->app);
                            $r = $class->validate();
                            if ($r !== static::VALID_CODE_SUCCESS) {
                                if (method_exists($r, 'getInvalidSubKey')) {
                                    error_log('[ArrayInput] invalid key: ' . $r->getInvalidSubKey());
                                }
                                $result = $r;
                                break;
                            }

                            $_values[] = $class->getValidValue();
                        }
                    }

                    if ($this->getEnumX()) {
                        $values = $this->getEnumXValue();
                        if ($this->isRequired() && !$values) {
                            $result = static::INVALID_CODE_REQUIRED;
                        }
                        else {
                            $this->value = $values;
                        }
                    }
                    if (($min = $this->getMinLength()) && count($_values) < $min) {
                        $result = static::INVALID_CODE_MINLENGTH;
                    } elseif (($max = $this->getMaxLength()) && count($_values) > $max) {
                        $result = static::INVALID_CODE_MAXLENGTH;
                    } else {
                        $this->value = $_values;
                    }
                } else {
                    throw new AppException(sprintf('Class not found: %s', $className));
                }
            } else if ($this->value === '') {
                $this->value = array();
            }
        }

        if (isset($this->config['returnAsCollection']) && is_array($this->value)) {
            $this->value = new Collection($this->value);
        }

        $this->validatedCode = $result;
        if ($this->validatedCode == static::VALID_CODE_SUCCESS && $this->isArrayType()) {
            $this->advancedValidate($result);
        }

        return $result;
    }

    /**
     * Validate the relationship of the data.
     * For example, the property of the array, and the sum of them must be 100.
     *
     * @param int $result
     * @throws AppException
     */
    public function advancedValidate(&$result)
    {
        if ($this->validatedCode === static::VALID_CODE_SUCCESS) {
            $advancedValidator = $this->get('validator');
            if ($advancedValidator && class_exists($advancedValidator)) {
                $class = new $advancedValidator($this->value, $this->config);
                if (method_exists($class, 'validate')) {
                    $result = call_user_func(array($class, 'validate'));
                    if (!is_int($result)) {
                        throw new AppException('Custom validator must return integer value.');
                    }
                    $this->validatedCode = $result;
                }
            }
        }
    }

//    /**
//     * @param bool $recursive
//     * @return array
//     */
//    public function toArray($recursive = false)
//    {
//        $data = array();
//        if (is_array($this->value)) {
//            foreach($this->value as $key => $item) {
//                if (is_array($item)) {
//                    if (count($item)) {
//                        foreach ($item as $idx => $val) {
//                            if (is_object($val) && method_exists($val, 'toArray')) {
//                                $data[$key][$idx] = $val->toArray($recursive);
//                            } else {
//                                $data[$key][$idx] = $val;
//                            }
//                        }
//                    } else {
//                        $data[$key] = $item;
//                    }
//                } else {
//                    if ($recursive && is_object($item) && is_subclass_of($item, '\\Key\\Abstracts\\BaseRecord')) {
//                        $data[$key] = $item->toArray($recursive);
//                    } else {
//                        $data[$key] = $item;
//                    }
//                }
//            }
//        }
//        return $data;
//    }

   /**
    * Whether a offset exists
    * @link https://php.net/manual/en/arrayaccess.offsetexists.php
    * @param mixed $offset <p>
    * An offset to check for.
    * </p>
    * @return bool true on success or false on failure.
    * </p>
    * <p>
    * The return value will be casted to boolean if non-boolean was returned.
    */
   public function offsetExists($offset)
   {
       return isset($this->value[$offset]);
   }

   /**
    * Offset to retrieve
    * @link https://php.net/manual/en/arrayaccess.offsetget.php
    * @param mixed $offset <p>
    * The offset to retrieve.
    * </p>
    * @return mixed Can return all value types.
    */
   public function offsetGet($offset)
   {
       return $this->value[$offset];
   }

   /**
    * Offset to set
    * @link https://php.net/manual/en/arrayaccess.offsetset.php
    * @param mixed $offset <p>
    * The offset to assign the value to.
    * </p>
    * @param mixed $value <p>
    * The value to set.
    * </p>
    * @return void
    */
   public function offsetSet($offset, $value)
   {
       $this->value[$offset] = $value;
   }

   /**
    * Offset to unset
    * @link https://php.net/manual/en/arrayaccess.offsetunset.php
    * @param mixed $offset <p>
    * The offset to unset.
    * </p>
    * @return void
    */
   public function offsetUnset($offset)
   {
       unset($this->value[$offset]);
   }

    // public function __serialize()
    // {
    //     return $this->value;
    // }

    // public function __toString() {
    //     return json_encode($this->value);
    // }
}