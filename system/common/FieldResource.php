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

class FieldResource
{
    protected static $regexp = '#(?P<modelName>[a-zA-Z][a-zA-Z0-9]*)(:(?P<path>[a-zA-Z][^\(]*))?(\((?P<uri>[^\(\)]*)\))?#';

    protected $uid;
    protected $aid;

    protected $name;
    protected $resource;
    protected $relationship;
    protected $required;

    protected $modelName;
    protected $pieces;
    protected $restUri;
    /** @var \App\Common\BaseModel */
    protected $model;

    protected $data = null;

    public function __construct($name, $resource, $relationship, $required = array(), $uid = 0, $aid = 0)
    {
        $this->name = $name;
        $this->resource = $resource;
        $this->relationship = $relationship;
        $this->required = $required;

        $this->uid = $uid;
        $this->aid = $aid;

        $this->parseResource();
    }

    public static function parse($name, $properties, $uid = 0, $aid = 0)
    {
        $resource = isset($properties['resource'])
                && $properties['resource']
                && is_string($properties['resource'])
                ? $properties['resource'] : null;
        $relationship = isset($properties['relationship']) && $properties['relationship'] ? $properties['relationship'] : null;

        if (!$resource || !$relationship) {
            throw new \InvalidArgumentException('Invalid resource or relationship');
        }

        $required = array();
        if (isset($properties['required']) && $properties['required'] && is_array($properties['required'])) {
            $required = $properties['required'];
        }

        return new static($name, $resource, $relationship, $required, $uid, $aid);
    }

    protected function parseResource()
    {
        preg_match(static::$regexp, $this->resource, $matches);

        //var_dump($matches);

        if ($matches && isset($matches['modelName'])) {
            $this->modelName = $matches['modelName'];
            $piece = isset($matches['path']) && $matches['path'] ? $matches['path'] : null;
            if ($piece) {
                $this->pieces = explode('/', $piece);
            }
            $this->restUri = isset($matches['uri']) && $matches['uri'] ? $matches['uri'] : null;
            $modelClassName = '\\App\\Models\\'.ucfirst($this->modelName);
            if (class_exists($modelClassName)) {
                /** @var \App\Common\BaseModel $class */
                $this->model = new $modelClassName($this->uid, $this->aid);
            } else {
                //throw new AppException('Class not found: '.$modelClassName);
            }
        }
    }

    /**
     * Get resource name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    protected function getRelationship($name) {
        if ($this->relationship && isset($this->relationship[$name])) {
            return $this->relationship[$name];
        }

        return $name;
    }

    /**
     * Get resource data.
     *
     * @param array $params key/value pair
     * @param callable $requiredHandler
     * @return array
     */
    public function handleResource($params = array(), $requiredHandler = null)
    {
        if (is_null($this->data)) {
            $newParams = array();
            foreach($params as $key=>$val) {
                $newParams[$this->getRelationship($key)] = $val;
            }

            if ($requiredHandler && is_callable($requiredHandler)) {
                $requirePair = $requiredHandler($this->required);
                if (is_array($requirePair)) {
                    $newParams = array_merge($newParams, $requirePair);
                }
            }

            $fields = array();
            foreach ($this->relationship as $item) {
                $fields[$item] = 1;
            }

            if ($this->model) {
                $data =  $this->model->handleResource($this->pieces, $newParams, $fields);
            } else {
                $restModel = new RestModel($params['__app']);
                // Using REST API to handle resource
                $data = $restModel->handleResource($this->modelName, array(
                    'pieces' => $this->pieces,
                    'params' => $newParams,
                    'fields' => $fields
                ));
            }


            // map to key
            $newData = array();
            foreach ($this->relationship as $key => $item) {
                $newData[$key] = isset($data[$item]) ? $data[$item] : null;
            }

            $this->data = $newData;
        }

        return $this->data;
    }

    public function reset()
    {
        $this->data = null;
    }
}