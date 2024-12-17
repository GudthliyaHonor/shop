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
use Key\Inputs\Input;

class BaseSubRecord extends BaseRecord
{
    /** @var SubCollection */
    protected $schema = null;


    protected $fields = array();
    /** @var BaseModel */
    protected $model = null;

    protected $mainFields = array();
    protected $mainData = array();

    /**
     * Input construct.
     *
     * @param string $name
     * @param mixed $value
     * @param array|null $inputConfig
     * @throws InvalidArgumentException
     */
    public function __construct($name, $value, $inputConfig = array())
    {
        parent::__construct($name, $value, $inputConfig);
    }


    protected function loadFields()
    {
        global $CONFIG;

        $schema_name = $this->get('parent');
        if (!$schema_name) {
            throw new AppException('Parent schema name not found');
        }
        $this->mainData = $this->get('main_data') ? $this->get('main_data') : array();

        $schema_name = lcfirst($schema_name);
        $key = $this->get('name');

        $user = $CONFIG->session ? $CONFIG->session->get(Constants::SESSION_USR_KEY) : null;
        $account = $CONFIG->session ? $CONFIG->session->get(Constants::SESSION_ACCOUNT_KEY) : null;
        $uid = isset($user['id']) ? (int) $user['id'] : 0;
        $aid = isset($account['id']) ? (int) $account['id'] : 0;

        $schema = new Collection($schema_name, $uid, $aid, true);
        $this->mainFields = $schema->getFields();

        $this->schema = $schema->getSubCollection($key);
        $fields = $this->schema->getFields();
        foreach ($fields as $field) {
            $this->fields[$field['name']] = $field;
        }

        $modelName = $schema->getModelName();
        $className = '\\App\\Models\\' . ucfirst($modelName);
        $this->model = new $className($uid, $aid);
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
        return strtolower($this->get('parent'));
    }

    /**
     * @return int
     * @throws AppException
     */
    public function validate()
    {
//        if (isset($this->fields)) {
//            foreach ($this->fields as $key => $field) {
//
//                if (is_array($field)) {
//                    $type = isset($field['type']) && $field['type'] ? strtolower($field['type']) : 'string';
//                    $this->subKey = $key;
//
//                    if (isset($field['subtype']) && ($subtype = $field['subtype'])) {
//
//                        $className = $this->getTypeClassName($subtype);
//                        $values = isset($this->value[$key]) ? $this->value[$key] : null;
//                        if ($type === 'array') {
//                            if (is_array($values)) {
//                                foreach($values as $idx => $value) {
//                                    /** @var \Key\Abstracts\BaseRecord $obj */
//                                    $obj = new $className($key, $value, $field);
//                                    $result = $obj->validate();
//                                    if ($result !== static::VALID_CODE_SUCCESS) {
//                                        $this->subKey = $key.':'.$idx.':'.(method_exists($obj, 'getInvalidSubKey') ? $obj->getInvalidSubKey() :'');
//                                        return $result;
//                                    }
//
//                                    $this->_data[$key][] = $obj->getValidValue();
//                                }
//                            } else {
//                                if (isset($field['required']) && $field['required']) {
//                                    return static::INVALID_CODE_FORMAT;
//                                }
//                            }
//                        } else {
//                            /** @var \Key\Abstracts\BaseRecord $obj */
//                            $obj = new $className($key, $values);
//                            $result = $obj->validate();
//                            if ($result !== static::VALID_CODE_SUCCESS) {
//                                $this->subKey = $key.':'.$obj->getInvalidSubKey();
//                                return $result;
//                            }
//
//                            $this->_data[$key] = $obj->getValidValue();
//                        }
//
//                    } else {
//                        $className = $this->getTypeClassName($type);
//                        $value = isset($this->value[$key]) ? $this->value[$key] : null;
//                        $obj = new $className($key, $value, $field);
//                        $result = $obj->validate();
//                        if ($result !== static::VALID_CODE_SUCCESS) {
//                            $this->validatedCode = $result;
//                            return $result;
//                        }
//
//                        $this->_data[$key] = $obj->getValidValue();
//                    }
//
//                } else  {
//                    throw new AppException('Invalid input setting: '.var_export($field, true));
//                }
//            }
//        }

        $result = $this->_validate();

        $this->extraCheck($result);

        $this->validatedCode = $result;

        return $result;
    }

    protected function getMainRequiredFields()
    {
        $fields = array();
        foreach ($this->mainFields as $mainField) {
            if (isset($mainField['sub_required']) && $mainField['sub_required']) {
                $fields[] = $mainField;
            }
        }

        return $fields;
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
                } else {
                    $unique_fields[$key] = $val;
                }

            } elseif(isset($field['resource']) && $field['resource'] && (!isset($field['visible']) || $field['visible'] != 0)) {
                $resource = $this->schema->getResource($field['resource']);
                $resource->reset();
                $parsed = $this->_data;
                $data = $resource->handleResource(array(
                    $key => $val
                ), function ($required) use($key, $parsed) {
                    if ($required) {
                        $pair = array();
                        foreach($required as $item) {
                            if (isset($parsed[$item])) {
                                $pair[$item] = $parsed[$item];
                            }
                        }

                        if (count($pair)==0) {
                            echo 'Warning: the required fields must before current field ' . $key;
                        }

                        return $pair;
                    }
                });

                if (!isset($data[$key]) || is_null($data[$key])) {
                    $result = 998; // TODO
                    return false;
                }

                $this->_data = array_merge($this->_data, $data);
            }
        }

        if (!empty($unique_fields)) {
            $mainRequiredFields = $this->getMainRequiredFields();

            $pre = array();
            $pairs = array();
            foreach ($mainRequiredFields as $field) {
                if (isset($this->mainData[$field['name']])) {
                    $pre[$field['name']] = $this->mainData[$field['name']];
                }
            }
            foreach($unique_fields as $key => $val) {
                $pairs[$this->get('name') .'.'.$key] = $val;
                break; // only need one unique key
            }

            $rows = $this->getModel()->unique($pairs, $this->getCollectionName(), $pre, array(
                $this->get('name') . '.$' => 1
            ));
            $count = $rows && count($rows) == 1 && isset($rows[0][$this->get('name')]) ? count($rows[0][$this->get('name')]) : 0;
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
}