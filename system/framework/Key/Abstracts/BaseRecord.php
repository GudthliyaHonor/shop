<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace Key\Abstracts;

use Exception;
use Key\Exception\AppException;
use Key\Inputs\ArrayInput;
use Key\Inputs\Input;
use Key\Inputs\InputFactory;

/**
 * Class BaseRecord
 * @package Key\Abstracts
 */
abstract class BaseRecord extends ArrayInput
{

    protected $app;

    protected $fields = array();

    /**
     * @var string
     * @deprecated
     */
    protected $subKey;

    static $invalidKey;

    protected $_data = array();

    protected $subState = false;

    public function setSubState($subState = false)
    {
        $this->subState = $subState;
    }

    public function setApp($app)
    {
        $this->app = $app;
        return $this;
    }

    /**
     * Get handler class name for the field.
     *
     * @param string $type Field type/subtype
     * @return null|string
     * @throws AppException
     */
    protected function getTypeClassName($type)
    {
        if (is_string($type) && strlen($type) == 0) {
            throw new AppException('Type of the Input not set in the record!');
        }

        $type = ucfirst($type);
        $className = null;
        if (InputFactory::isBaseType($type)) {
            $className = '\\Key\\Inputs\\'.$type.'Input';
        } elseif (class_exists('\\Key\\Records\\' . $type)) {
            $className = '\\Key\\Records\\' . $type;
        } elseif (class_exists($type)) {
            $className = $type;
        } else {
            if ($type && $this->app) {
                if ($this->app->offsetExists('appName')) {
                    $moduleName = $this->app['appName'];
                    // error_log('module name: ' . $moduleName);
                    if ($this->app->offsetExists('modules')) {
                        /** @var \Key\Foundation\Module $module */
                        $module = $this->app['modules'][$moduleName];
                        return $module->getRecordClass($type);
                    }
                }
            }

            if ($type == null) {
                $className = '\\App\\Common\\BaseRecord';
            } else {
                $className = '\\App\\Records\\' . $type;
            }
        }

        if ($className) {
            return $className;
        }

        throw new AppException('Record class not found: ' . $type);
    }

    protected function beforeValidate()
    {

    }

    /**
     * Validate the value for input.
     *
     * @return int validation result code.
     * @throws AppException
     */
    public function validate()
    {
        $this->beforeValidate();
        if (($result = Input::validate()) === static::VALID_CODE_SUCCESS) {
            if (!$this->isRequired() && $this->isEmpty($this->value)) {
                if (Input::isBaseType($this->get('type') ?: Input::TYPE_STRING)) {
                    $this->_data = $this->value;
                } else {
                    $className = $this->getTypeClassName($this->get('type'));
                    if ($className) {
                        /** @var \Key\Abstracts\BaseRecord $obj */
                        $obj = new $className($this->name, $this->value, [], $this->_data, $this);
                        if (!method_exists($obj, 'getFields')) {
                            throw new Exception('Method getFields not found! Type: ' . $this->get('type'));
                        }
                        if (method_exists($obj, 'setApp')) {
                            $obj->setApp($this->app);
                        }
                        $fields = $obj->getFields();

                        if ($fields && is_array($fields)) {
                            $values = [];
                            foreach ($fields as $key => $field) {
                                if (isset($field['default'])) {
                                    $values[$key] = $field['default'];
                                }
                            }
                            if ($values) {
                                $this->_data = $values;
                            } else {
                                $this->_data = null;
                            }
                        } else {
                            $this->_data = null;
                        }
                    } else {
                        $this->_data = null;
                    }
                }
                $this->advancedValidate($result);
                return static::VALID_CODE_SUCCESS;
            }
            $fields = $this->getFields();
            if ($fields) {
                foreach ($fields as $key => $field) {
                    if (is_array($field)) {
                        $type = isset($field['type']) && $field['type'] ? lcfirst($field['type']) : 'string';

                        if (isset($field['subtype']) && ($subtype = $field['subtype'])) {

                            $className = $this->getTypeClassName($subtype);
                            $values = isset($this->value[$key]) ? $this->value[$key] : null;
                            if ($type === 'array') {
                                if (isset($field['mode']) && $field['mode'] == 'array-multiple-x') { // TODO: maybe tricky
                                    $obj = new ArrayInput($key, $values, $field, $this->_data, $this);
                                    if (method_exists($obj, 'setApp')) {
                                        $obj->setApp($this->app);
                                    }
                                    $result = $obj->validate();
                                    if ($result !== static::VALID_CODE_SUCCESS) {
                                        self::$invalidKey = $key;
                                        return $result;
                                    }
                                    $this->_data[$key] = $obj->getValidValue();
                                }
                                else {
                                    if (is_array($values)) {
                                        // Fixed by lgh at 2018/01/26 14:01
                                        // Record configure:
                                        // 'a' => [
                                        //      'type' => 'array',
                                        //      'subtype' => 'int',
                                        //      'default' => [],
                                        //      'key_exists_even_empty_value' => 1
                                        // ]
                                        // Data: {a:[]}
                                        if (empty($values)) {
                                            if (isset($field['default'])) {
                                                $result = static::VALID_CODE_SUCCESS;
                                                $this->_data[$key] = $field['default'];
                                            }
                                        } else {
                                            foreach($values as $idx => $value) {
                                                // error_log($key . '::' . $idx . '---------' . $className);
                                                /** @var \Key\Abstracts\BaseRecord $obj */
                                                $obj = new $className($key . '::' . $idx, $value, $field, null, $this);
                                                if (method_exists($obj, 'setApp')) {
                                                    $obj->setApp($this->app);
                                                }
                                                $result = $obj->validate();
                                                if ($result !== static::VALID_CODE_SUCCESS) {
                                                    self::$invalidKey = $key.':'.$idx.':'.(method_exists($obj, 'getInvalidSubKey') ? $obj->getInvalidSubKey() :'');
                                                    return $result;
                                                }
    
                                                $this->_data[$key][] = $obj->getValidValue();
                                            }
                                        }
                                    } else {
                                        if (isset($field['required']) && $field['required']) {
                                            return static::INVALID_CODE_FORMAT;
                                        }
                                        // Add default value support, by lgh at 2018/04/27 19:47
                                        if (isset($field['default']) && $field['default']) {
                                            $this->_data[$key] = $field['default'];
                                        }
                                    }
                                }
                            } else {
                                /** @var \Key\Abstracts\BaseRecord $obj */
                                $obj = new $className($key, $values, $field, $this->_data, $this);
                                if (method_exists($obj, 'setApp')) {
                                    $obj->setApp($this->app);
                                }
                                $result = $obj->validate();
                                if ($result !== static::VALID_CODE_SUCCESS) {
                                    self::$invalidKey = $key.':'.$obj->getInvalidSubKey();
                                    return $result;
                                }

                                $this->_data[$key] = $obj->getValidValue();
                            }

                        } else {
                            $className = $this->getTypeClassName($type);
                            // fixme: unique attribute with auto_generate/prefix, if the original value is empty, the value must auto generated
                            $value = isset($this->value[$key]) ? $this->value[$key] : null;
                            $obj = new $className($key, $value, $field, $this->_data, $this);
                            if (method_exists($obj, 'setApp')) {
                                $obj->setApp($this->app);
                            }
                            $result = $obj->validate();
                            if ($result !== static::VALID_CODE_SUCCESS) {
                                self::$invalidKey = $key;
                                $this->validatedCode = $result;
                                return $result;
                            }

                            $this->_data[$key] = $obj->getValidValue();
                        }

                    } else  {
                        throw new AppException('Invalid input setting: '.var_export($field, true));
                    }
                }
            }
        }
        $this->advancedValidate($result);
        $this->validatedCode = $result;

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function advancedValidate(&$result)
    {
        $advancedValidator = $this->get('validator');
        if ($advancedValidator && class_exists($advancedValidator)) {
            /** @var \Key\Abstracts\Validator $class */
            $class = new $advancedValidator($this->_data, $this->config);
            if (method_exists($class, 'validate')) {
                $result = call_user_func(array($class, 'validate'));
                if (!is_int($result)) {
                    throw new AppException('Custom validator must return integer value.');
                }
                $this->validatedCode = $result;
            }
        }
    }

    /**
     * Get sub key contains invalid value.
     *
     * @return mixed
     */
    public function getInvalidSubKey()
    {
        return self::$invalidKey;
    }

    /**
     * Get valid value for the input.
     *
     * @return self|null
     */
    public function getValidValue()
    {
        if ($this->validatedCode === static::VALID_CODE_UNFINISHED) {
            $this->validate();
        }

        if ($this->validatedCode === static::VALID_CODE_SUCCESS) {
            return $this;
        }

        return null;
    }

    /**
     * Get the value of given name.
     *
     * @param string $name
     * @param null|mixed $defaultValue
     * @return mixed
     */
    public function getData($name, $defaultValue = null)
    {
        $value = isset($this->_data[$name]) ? $this->_data[$name] : $defaultValue;
        if ($value instanceof BaseRecord) {
            return $value->toArray();
        }

        return $value;
    }

    /**
     * Set value for the attribute.
     *
     * @param string $name Attribute name
     * @param mixed $value Attribute value
     */
    public function setData($name, $value)
    {
        $this->_data[$name] = $value;
    }

    /**
     *
     * @return array
     */
    public function toArray($recursive = false)
    {
        $data = array();
        if (is_array($this->_data)) {
            foreach($this->_data as $key => $item) {
                if (is_array($item)) {
                    if (count($item)) {
                        foreach ($item as $idx => $val) {
                            if (is_object($val) && method_exists($val, 'toArray')) {
                                $data[$key][$idx] = $val->toArray($recursive);
                            } else {
                                $data[$key][$idx] = $val;
                            }
                        }
                    } else {
                        $data[$key] = $item;
                    }
                } else {
                    if ($recursive && is_object($item) && is_subclass_of($item, '\\Key\\Abstracts\\BaseRecord')) {
                        $data[$key] = $item->toArray($recursive);
                    } else {
                        $data[$key] = $item;
                    }
                }
            }
        }
        return $data;
    }

    /**
     * Get fields configure.
     *
     * @return mixed|null
     */
    public function getFields()
    {
        $fields = $this->fields ?: [];
        if (method_exists($this, 'loadExteralFields')) {
            error_log('calling loadExteralFields...');
            // $fields = array_merge(call_user_func(array($this, 'loadExteralFields')) ?: [], $fields);
            $fields = array_merge($fields, call_user_func(array($this, 'loadExteralFields'), $this->app) ?: []);
        }
        // error_log('BaseRecord getFields: ' . json_encode($fields));
        // file_put_contents('/tmp/fields-' . time() . '.json', json_encode($fields) . PHP_EOL, FILE_APPEND);
        return $fields;
    }

    /**
     * toString func.
     *
     * @return string
     */
    public function __toString()
    {
        return json_encode($this->toArray(true));
    }

}