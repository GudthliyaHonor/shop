<?php

namespace Key\Abstracts;

/**
 * Multile version for object.
 * @package Key\Abstracts
 */
abstract class BaseVersionRecord extends BaseRecord
{

    protected $versionFields = [
        'VCODE' => ['type' => 'auto', 'generator' => 'snowflow-string'],                     // 版本code,内部标识,字符串,唯一
        'VID' => ['type' => 'auto', 'generator' => 'sequence', 'params' => [], 
            'sequence_name' => ['prefix' => 'auto_input_', 'suffix_args' => ['VCODE']]],              // 版本ID,内部标识,int,唯一
        'VN' => ['type' => 'string'],                                                        // 版本名称,用户可自定义
        'VC' => ['type' => 'boolean', 'default' => 1, 'trueDefaultValue' => 1, 'falseDefaultValue' => 0],    // 是否当前最新（选中）版本, 0/1
    ];

    /**
     * Get fields configure.
     *
     * @return mixed|null
     */
    public function getFields()
    {
        $fields = parent::getFields();

        $this->versionFields['VID']['params']['aid'] = $this->app && $this->app->offsetExists('__CURRENT_ACCOUNT_ID__') ? $this->app['__CURRENT_ACCOUNT_ID__'] : 0;
        $this->versionFields['VID']['params']['eid'] = $this->app && $this->app->offsetExists('__CURRENT_EMPLOYEE_ID__') ? $this->app['__CURRENT_EMPLOYEE_ID__'] : 0;

        // load version fields
        $this->fields = array_merge($fields, $this->versionFields);
        return $this->fields;
    }
}