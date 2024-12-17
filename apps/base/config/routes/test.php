<?php
/**
 * 学习模块儿routes.
 * User: wangyy
 * Date: 2018/7/18
 * Time: 15:40
 */

return [
    'POST /learning/screen/plan/gwm' => [
        'description' => '长城数据大屏-智能班级',
//        'acl' => '\App\Acl\Func\LearningAcl::manageAclCheck',
//        'data_acl' => '\App\Acl\Data\LearningDcl::manageDclCheck',
        'controller' => '/LearningPlan/create',
        'inputs' => [
            'record' => [
                'type' => 'array',
                'description' => '',
            ],
            'type' => [
                'type' => 'int',
                'description' => '',
            ],
            'project_id' => [
                'type' => 'int',
                'description' => '',
            ],
        ]
    ],
];
