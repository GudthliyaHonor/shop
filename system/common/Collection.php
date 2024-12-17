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


use Key\Database\MongoManager;
use Key\Exception\AppException;
use Key\Inputs\InputFactory;

// @deprecated
class Collection
{
    /** @var string Collection template name */
    protected $name;

    protected $aid;
    protected $uid;

    protected $template;

    protected $model_name;

    /** @var array main document fields */
    protected $main = array();

    protected $load_sub_fields = false;

    /** @var \App\Collections\SubCollection[] sub document fields */
    protected $sub = array();

    /** @var \App\Collections\FieldResources */
    protected $resources;

    /**
     * Collection constructor.
     * @param string $name Main document name.
     * @param int $uid User id
     * @param int $aid Account/Organization id
     * @param bool $load_sub_fields If true, load sub document fields
     */
    public function __construct($name, $uid, $aid, $load_sub_fields = false)
    {
        $this->name = $name;
        $this->uid = $uid;
        $this->aid = $aid;
        $this->load_sub_fields = !!$load_sub_fields;

        $this->load();
    }

    /**
     * Load collection template, etc.
     *
     * @return mixed
     * @throws AppException
     */
    protected function load()
    {
        $db = MongoManager::getInstance($this->uid, $this->aid);
        $row = $db->fetchRow(Constants::COLL_TPL, array(
            'aid' => $this->aid,
            'name' => $this->name
        ));

        // Not found, use default definition.
        if (!$row) {
            $row = $db->fetchRow(Constants::COLL_TPL, array(
                'aid' => 0,
                'name' => $this->name
            ));
        }

        if ($row) {
            $this->template = $row;
            $this->model_name = isset($row['model']) && $row['model'] ? $row['model'] : $this->name;
            $this->parseFields();
            $this->parseResources();
        } else {
            throw new AppException('Collection definition not found: ' . $this->name);
        }

    }

    /**
     * Get collection display name.
     *
     * @return string|null
     */
    public function getDisplay()
    {
        if (isset($this->template['display'])) {
            return $this->template['display'];
        }

        return null;
    }

    /**
     * Fetch the related model name, such as 'job', 'competency', etc.
     *
     * @return mixed
     */
    public function getModelName()
    {
        return $this->model_name;
    }

    /**
     * Parse the fields for main/sub document.
     *
     * @return array
     */
    protected function parseFields()
    {
        $fields = $this->template['fields'];

        $main = array();
        $sub = array();
        foreach ($fields as $field) {
            if (!isset($field['subtype']) || InputFactory::isBaseType($field['subtype'])) {
                $main[] = $field;
            } else {
                if ($this->load_sub_fields) {
                    $main[] = $field;
                    $sub[$field['name']] = new SubCollection($this->name, $field, $this->uid, $this->aid, $this->load_sub_fields);
                }
            }
        }

        $this->main = $main;
        $this->sub = $sub;
    }

    protected function parseResources()
    {
        $resources = isset($this->template['resources']) && $this->template['resources'] ? $this->template['resources'] : null;

        if ($resources) {
            $this->resources = FieldResources::parse($resources, $this->uid, $this->aid);
        }
    }

    /**
     * Get main document fields.
     *
     * @return array
     */
    public function getFields()
    {
        return $this->main;
    }

    /**
     * Check if the collection have resources configure.
     *
     * @return bool
     */
    public function hasResource()
    {
        return is_array($this->resources) && count($this->resources);
    }

    /**
     * Get export fields of main document.
     *<p>导入导出不使用一个方法</p>
     * @return array
     *
     */
    public function getExportFields()
    {
        $fields = array();
        foreach ($this->main as $index => $field) {
            if (isset($field['transferrable']) && $field['transferrable'] && $field['transferrable'] != -1
                || !isset($field['transferrable'])
            ) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Get transferable fields of main document.
     *
     * @return array
     */
    public function getTransferrableFields()
    {
        $fields = array();
        foreach ($this->main as $index => $field) {
            if (isset($field['transferrable']) && $field['transferrable']
                || !isset($field['transferrable'])
            ) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Get main fields required by sub document for exporting/importing.
     *
     * @return array
     */
    public function getMainFieldsRequired()
    {
        $fields = array();
        foreach ($this->main as $index => $field) {
            if (isset($field['sub_required']) && $field['sub_required']) {
                $fields[] = $field;
            }
        }

        return $fields;
    }

    /**
     * Get sub document collection.
     *
     * @param string $name sub document name
     * @return SubCollection
     * @throws AppException
     */
    public function getSubCollection($name)
    {
        if (!$name || !is_string($name)) {
            throw new \InvalidArgumentException('Invalid parameter $name: ' . var_export($name, true));
        }

        if ($name && isset($this->sub[$name])) {
            return $this->sub[$name];
        }

        throw new AppException('Invalid sub document name: ' . $name);
    }

    /**
     * Get the fields of sub document.
     *
     * @param string $name Sub document name
     * @return SubCollection
     * @throws AppException
     */
    public function getSubFields($name)
    {
        return $this->getSubCollection($name)->getFields();
    }

    /**
     * Get resources configure.
     *
     * @return FieldResources
     */
    public function getResources()
    {
        return $this->resources;
    }

    /**
     * Get resource.
     *
     * @param string $name Resource name
     * @return FieldResource|null
     */
    public function getResource($name)
    {
        if ($this->resources && $this->resources->getResource($name)) {
            return $this->resources->getResource($name);
        }

        return null;
    }

    public function unique()
    {

    }
}