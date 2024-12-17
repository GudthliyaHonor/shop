<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */

namespace App\Common;


use Key\Exception\AppException;
use Key\Inputs\ArrayInput;
use Key\Inputs\Input;
use Key\Inputs\InputFactory;

class BaseRecord extends \Key\Abstracts\BaseRecord
{
    protected $useRecord = false;

    /** @var Collection */
    protected $schema = null;

    /** @var BaseModel */
    protected $model = null;

    /**
     * Input construct.
     *
     * @param string $name
     * @param mixed $value
     * @param array|null $inputConfig
     * @throws InvalidArgumentException
     */
    public function __construct($name, $value, $inputConfig)
    {
        error_log('Common BaseRecord: ' . get_called_class());
        parent::__construct($name, $value, $inputConfig);
        $this->loadFields();
    }

    protected function loadFields()
    {
        $schema_name = $this->get('type');

        if ($schema_name == 'array' && $subtype = $this->get('subtype')) {
            $schema_name = lcfirst($subtype);
        } else {
            $schema_name = lcfirst($schema_name);
        }

        $inputClassName = InputFactory::getInputClass($schema_name);
        if ($inputClassName) {
            $this->useRecord = true;
        } else {
            // No DB collection Input type in this project, by lgh at 2018/04/17 14:24
            // error_log('backtrace: ' . json_encode(array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 0, 3)));
            throw new AppException('Invalid Input class: ' . $schema_name . ' <- ' . $this->name);
        }
    }

    /**
     * Check if the input is required.
     *
     * @return bool
     */
    public function isRequired()
    {
        return ArrayGet($this->config, 'required') == 1;
    }

    /**
     * @return BaseModel
     * @throws AppException
     */
    protected function getModel()
    {
        if ($this->model) {
            return $this->model;
        }

        throw new AppException('Model not found');
    }

    protected function getCollectionName()
    {
        // TODO: should defined in collection template
        return strtolower($this->get('type'));
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
        $type = ucfirst($type);
        $className = null;

        if (Input::isBaseType($type)) {
            $className = '\\Key\\Inputs\\'.$type.'Input';
        } elseif (class_exists('\\Key\\Records\\' . $type)) {
            $className = '\\Key\\Records\\' . $type;
        }

        if ($className) {
            return $className;
        }

        throw new AppException('Record class not found: ' . $type);
    }

    protected function getTypeClass($name, $value, $setting)
    {
        $type = isset($setting['type']) && $setting['type'] ? $setting['type'] : 'string';
        $type = ucfirst($type);
        $className = null;

        if (class_exists($name)) {
            $className = $name;
        } elseif (Input::isBaseType($type)) {
            $className = '\\Key\\Inputs\\'.$type.'Input';
        } elseif (class_exists('\\Key\\Records\\' . $type)) {
            $className = '\\Key\\Records\\' . $type;
        }
        error_log('++++getTypeClass++++++++' . $className);
        if ($className) {
            return new $className($name, $value, $setting);
        } else {
            if ($name) {
                // Get field type schema defined in DB
                try {
                    $fieldSchema = new Collection(lcfirst($type), 0, 0, true);
                    $fieldsDefine = $fieldSchema->getFields();
                    return new ArrayInput($name, $value, $fieldsDefine);
                } catch (\Exception $ex) {

                }
            }
        }

        throw new AppException('Record class not found: ' . $type);
    }

    protected function _validate()
    {
        if (isset($this->fields)) {
            foreach ($this->fields as $key => $field) {

                if (is_array($field)) {
                    $type = isset($field['type']) && $field['type'] ? lcfirst($field['type']) : 'string';
                    $this->subKey = $key;

                    if (isset($field['subtype']) && ($subtype = $field['subtype'])) {
                        if (InputFactory::isBaseType($type) && $type != 'array') {
                            throw new AppException(sprintf('Record config error: subtype `%s` can not be set in base type `%s` in the field `%s`', $subtype, $type, $field['name']));
                        }
                        $field['parent'] = $this->get('type');
                        $field['main_data'] = $this->value;

                        $className = '\\App\\Common\\BaseSubRecord';//$this->getTypeClassName($subtype);
                        $values = isset($this->value[$key]) ? $this->value[$key] : null;
                        if ($type === 'array') {
                            if (is_array($values) && count($values)) {
                                $newField = $field;
                                $newField['type'] = $newField['subtype'];
                                unset($newField['subtype']);

                                foreach($values as $idx => $value) {
                                    if (InputFactory::isBaseType($newField['type'])) {
                                        $obj = InputFactory::getInstance($key, $value, $newField);
                                    } else {
                                        /** @var \Key\Abstracts\BaseRecord $obj */
                                        $obj = new $className($key, $value, $newField);
                                    }
                                    $result = $obj->validate();
                                    if ($result !== static::VALID_CODE_SUCCESS) {
                                        $this->subKey = $key.':'.$idx.':'.(method_exists($obj, 'getInvalidSubKey') ? $obj->getInvalidSubKey() :'');
                                        return $result;
                                    }

                                    $this->_data[$key][] = $obj->getValidValue();
                                }
                            } else {
                                if (isset($field['required']) && $field['required']) {
                                    return static::INVALID_CODE_FORMAT;
                                } else {
                                    $this->_data[$key] = array();
                                }
                            }
                        } else {
                            /** @var \Key\Abstracts\BaseRecord $obj */
                            $obj = new $className($key, $values);
                            $result = $obj->validate();
                            if ($result !== static::VALID_CODE_SUCCESS) {
                                $this->subKey = $key.':'.$obj->getInvalidSubKey();
                                return $result;
                            }

                            $this->_data[$key] = $obj->getValidValue();
                        }

                    } else {
                        if (!isset($field['ignore']) || $field['ignore'] != 1) {
                            // If empty, auto-generate field does not need validate
                            if (isset($field['auto_generate']) && $field['auto_generate'] && empty($value)) {
                                // TODO: auto generate the value of the field...
                                continue;
                            }

                            //$className = $this->getTypeClassName($type, $field['name']);
                            $value = isset($this->value[$key]) ? $this->value[$key] : null;

                            $obj = $this->getTypeClass($field['name'], $value, $field);
                            $result = $obj->validate();
                            //Tools::log('[_validate] key: ' . $key . ' >>> ' . var_export($value, true));
                            if ($result !== static::VALID_CODE_SUCCESS) {
                                //Tools::log('[_validate] invalid value: ' . var_export($value, true) . '-- code: '.$result);
                                $this->validatedCode = $result;
                                return $result;
                            }

                            $this->_data[$key] = $obj->getValidValue();
                        }
                    }

                } else  {
                    throw new AppException('Invalid input setting: '.var_export($field, true));
                }
            }
        }

        return static::VALID_CODE_SUCCESS;
    }

    /**
     * @return int
     * @throws AppException
     */
    public function validate()
    {
        if ($this->useRecord) {
            $type = $this->get('type') ? lcfirst($this->get('type')) : 'string';
            if ($type == 'array') {
                if (is_array($this->value)) {
                    $subtype = $this->get('subtype') ? $this->get('subtype') : 'mixed';
                    $className = $this->getTypeClassName($subtype);
                    $record = new $className($this->name, $this->value, $this->config);
                    if ($record instanceof \App\Common\BaseRecord) {
                        throw new AppException('[Common:BaseRecord] Record instance is invalid, it must instance of \\Key\\Abstracts\\BaseRecord');
                    }
                    $result = $record->validate();
                } else {
                    if (isset($this->config['required']) && $this->config['required']) {
                        return static::INVALID_CODE_FORMAT;
                    }
                }
            } elseif (InputFactory::isBaseType($type)) {
                $class = InputFactory::getInstance($this->name, $this->value, $this->config);
                $result = $class->validate();
                if ($result === static::VALID_CODE_SUCCESS) {
                    $this->_data = $class->getValidValue();
                }
            } else {
                if (class_exists($this->get('type'))) {
                    $recordClassName = $this->get('type');
                } else {
                    $recordClassName = '\\App\\Records\\' . ucfirst($this->get('type'));
                }
                /** @var \Key\Abstracts\BaseRecord $record */
                $record = new $recordClassName($this->name, $this->value, $this->config);
                if ($record instanceof \App\Common\BaseRecord) {
                    throw new AppException('[Common:BaseRecord] Record instance is invalid, it must instance of \\Key\\Abstracts\\BaseRecord');
                }

                if ($this->isRequired() && !$this->value) {
                    error_log(sprintf('[\App\Common\BaseRecord]Record %s required', $this->name));
                    $result = static::INVALID_CODE_REQUIRED;
                } else {
                    $result = $record->validate();
                    if ($result === static::VALID_CODE_SUCCESS) {
                        $this->_data = $record->toArray();
                    }
                }
            }

        } else {
            if (($result = ArrayInput::validate()) === static::VALID_CODE_SUCCESS) {
                $result = $this->_validate();
            }

            $this->extraCheck($result);
        }

        $this->validatedCode = $result;
        return $result;
    }

    protected function extraCheck(&$result)
    {
        if ($result === static::VALID_CODE_SUCCESS) {
            $this->fieldsCheck($result);
        }
        if ($result === static::VALID_CODE_SUCCESS) {
            $this->advancedValidate($result);
        }
    }

    // TODO: Not done...
    protected function handleSubRecord($field)
    {
        $key = $field['name'];
        $values = isset($this->value[$key]) ? $this->value[$key] : null;

        $subFields = $this->schema->getSubFields($key);
        if ($subFields) {
            $type = isset($field['type']) && $field['type'] ? lcfirst($field['type']) : 'string';
            if ($type == 'array') {
                if (is_array($values)) {
                    foreach($values as $idx => $value) {

                    }
                } else {
                    if (isset($field['required']) && $field['required']) {
                        return static::INVALID_CODE_FORMAT;
                    }
                }
            } else {
                foreach ($subFields as $subField) {

                }
            }
        }
    }

    protected function fieldsCheck(&$result)
    {
        $unique_fields = array();
        // get main document unique fields
        foreach($this->fields as $field) {
            $key = $field['name'];
            $val = isset($this->value[$key]) ? $this->value[$key] : null;
            if (isset($field['unique']) && $field['unique']) {

                // TODO: check if the value is unique in the scope of the company.
                if (isset($field['auto_generate']) && $field['auto_generate'] && empty($val)) {
                    // TODO: generate the value

                } elseif (empty($val)) {
                    $result = Input::INVALID_CODE_REQUIRED;
                    return false;
                }

                $unique_fields[$key] = $val;
            } elseif(isset($field['resource']) && $field['resource'] && (!isset($field['ignore']) || $field['ignore'] != 1)) {
                if (is_null($val) || trim($val) == '') {
                    error_log('Resource attribute value is null, skip!');
                    continue;
                }
                $resource = $this->schema->getResource($field['resource']);
                $resource->reset();
                $parsed = $this->_data;
                $data = $resource->handleResource(array(
                    $key => $val,
                    '__app' => $this->app,
                ), function ($required) use($key, $parsed) {
                    if ($required) {
                        $pair = array();
                        foreach($required as $item) {
                            if (isset($parsed[$item])) {
                                $pair[$item] = $parsed[$item];
                            }
                        }

                        if (count($pair)==0) {
                            error_log('Warning: the required fields must before current field ' . $key);
                        }

                        return $pair;
                    }
                });
                //Tools::log('[BaseRecord] ' . var_export($data, true));
                if (!isset($data[$key]) || is_null($data[$key])) {
                    $result = 998; // TODO
                    //Tools::log('[BaseRecord] Check resource data fail: ' . $key);
                    return false;
                }

                $this->_data = array_merge($this->_data, $data);
            }
        }

        if (!empty($unique_fields)) {
            $rows = $this->getModel()->unique($unique_fields, $this->getCollectionName());
            $count = $rows ? count($rows) : 0;
            if ($count > 1) {
                $result = 999; // TODO
                return false;
            } elseif ($count == 1) {
                //$id = $rows[0]['id'];
            }
        }

        $result = Input::VALID_CODE_SUCCESS;
        return true;
    }

    /**
     * Only check the field type and always return success.
     */
    public function onlyTypeValidate()
    {
        if ($this->validatedCode == self::VALID_CODE_UNFINISHED) {
            $this->_validate();
        }

        $data = $this->_data;
        // add back the values not validated for data importing.
        if ($this->value && is_array($this->value)) {
            foreach ($this->value as $key => $val) {
                if (!isset($data['$key'])) {
                    $data[$key] = $val;
                }
            }
        }
        $this->_data = $data;

        //Tools::log('[onlyTypeValidate] data: ' . var_export($data, true));
        $this->validatedCode = Input::VALID_CODE_SUCCESS;
    }
}