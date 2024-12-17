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
        'acl' => '\App\Acl\Func\LearningAcl::manageAclCheck',
        'data_acl' => '\App\Acl\Data\LearningDcl::manageDclCheck',
        'controller' => '/LearningStatistics/gwmPlanScreenData',
        'inputs' => [
            'date_type' => [
                'type' => 'int',
                'description' => '日期类型',
            ],
            'date_value' => [
                'type' => 'string',
                'description' => '日期值',
            ],
        ]
    ],

    'POST /learning/screen/pack/gwm' => [
        'description' => '长城数据大屏-学习包',
        'acl' => '\App\Acl\Func\LearningAcl::manageAclCheck',
        'data_acl' => '\App\Acl\Data\LearningDcl::manageDclCheck',
        'controller' => '/LearningStatistics/gwmPackScreenData',
        'inputs' => [
            'date_type' => [
                'type' => 'int',
                'description' => '日期类型',
            ],
            'date_value' => [
                'type' => 'string',
                'description' => '日期值',
            ],
            'category_id' => [
                'default' => 0,
                'type' => 'int',
                'description' => '分类id'
            ],
        ]
    ],
];
