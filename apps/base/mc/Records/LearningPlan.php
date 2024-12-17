<?php

/**
 * 创建学习计划字段验证.
 * User: wangyy
 * Date: 2017/8/17
 * Time: 14:05
 */

namespace App\Records;

use App\Models\AccountConfigure\CustomFields;
use Key\Abstracts\BaseRecord;

class LearningPlan extends BaseRecordWithCF
{

    protected $cf_name = CustomFields::MODEL_LEARNING_PLAN;

    protected $fields = [

        'no' => [
            'type' => 'string',
            'description' => '计划编号',
        ],
        'name' => [
            'required' => 1,
            'type' => 'string',
            'description' => '计划名称',
        ],
        'start_time' => [
            'type' => 'string',
            'description' => '计划开始时间',
        ],
        'end_time' => [
            'type' => 'string',
            'description' => '计划结束时间',
        ],
        'status' => [
            'type' => 'int',
            'description' => '计划的发布状态 1-草稿 2-发布',
            'default' => 1
        ],
        'project_name' => [
            'type' => 'string',
            'description' => '培训计划-项目名称',
            'default' => ''
        ],
        'source' => [
            'type' => 'int',
            'description' => '来源 1-培训计划',
            'default' => 0
        ],
        'source_id' => [
            'type' => 'int',
            'description' => '培训计划-项目id',
            'default' => 0
        ],
        'source_name' => [
            'type' => 'string',
            'description' => '培训计划-名称',
            'default' => ''
        ],
        'desc' => [
            'type' => 'string',
            'description' => '项目目标',
            'default' => ''
        ],
        'petition_code' => [
            'type' => 'string',
            'description' => '旺旺的签呈编码',
            'default' => ''
        ],
        'is_order' => [
            'type' => 'int',
            'description' => '任务顺序 0-无序 1-有序',
            'default' => 0
        ],
        'is_applicant' => [
            'type' => 'int',
            'description' => '智能班级-是否开启报名 0-否 1-是',
            'default' => 0
        ],
        'applicant_auth' => [
            'type' => 'int',
            'description' => '智能班级-报名权限 0-所有人 1-指定人',
            'default' => 0
        ],
        'limit_learner' => [
            'type' => 'int',
            'description' => '智能班级-人数限制',
            'default' => 0
        ],
        'is_examine' => [
            'type' => 'int',
            'description' => '智能班级-是否开启报名审核 0-不审核 1-审核 2-部门经理审核 3-直属上级审核',
            'default' => 0
        ],
        'unlock_con' => [
            'type' => 'int',
            'description' => '解锁条件  
            0-不限定  
            1-按阶段，阶段内任务不按顺序  (完成上一任务，下一任务立即解锁) 
            2-按阶段，阶段内任务按顺序 
            3-按阶段时间，阶段内任务不按顺序, 
            4-按阶段时间，阶段内任务按顺序, 
            5-按阶段打卡，
            6-按任务打卡',
            'default' => 0
        ],
        'unlock_detail' => [
            'type' => 'array',
            'detail' => [
                'period' => [
                    'type' => 'int',
                    'description' => '解锁周期 0-立即 1-第二天 2-下一周',
                    'default' => 0
                ],
                'date' => [
                    'type' => 'int',
                    'description' => '0-全部 1-周一/1号 2-周二/2号',
                    'default' => 0
                ],
            ],
            'default' => []
        ],
        'elective_range' => [
            'type' => 'array',
            'subtype' => 'EmployeeRange',
            'description' => '选修范围 type: 1-全公司 2-指定部门 3-指定人群 4-指定员工 5-指定岗位 6-职务',
            'default' => []
        ],
        'applied_range' => [
            'type' => 'array',
            'subtype' => 'EmployeeRange',
            'description' => '指定的报名范围 type: 1-全公司 2-指定部门 3-指定人群 4-指定员工 5-指定岗位 6-职务',
            'default' => []
        ],
        'monitor' => [
            'type' => 'array',
            'subtype' => 'EmployeeRange',
            'description' => '班级管理员 type: 1-全公司 2-指定部门 3-指定人群 4-指定员工 5-指定岗位 6-职务',
            'default' => []
        ],
        'training_outlines' => [
            'type' => 'string',
            'description' => '培训大纲',
            'default' => ''
        ],
        'class_type' => [
            'type' => 'int',
            'description' => '项目类型 0-线上培训 1-线下培训 2-O2O混合培训',
            'default' => 0
        ],
        'class_address' => [
            'type' => 'string',
            'description' => '上课地址',
            'default' => ''
        ],
        'vendors' => [
            'type' => 'string',
            'description' => '供应商',
            'default' => ''
        ],
        'credit' => [
            'type' => 'float',
            'description' => '学分',
            'default' => 0
        ],
        'training_hours' => [
            'type' => 'float',
            'description' => '培训时长',
            'default' => 0
        ],
        'is_add_group' => [
            'type' => 'int',
            'description' => '是否添加人群标签 0-否 1-是',
            'default' => 0
        ],
        'is_del_group' => [
            'type' => 'int',
            'description' => '是否减人群标签 0-否 1-是',
            'default' => 0
        ],
        'add_groups' => [
            'type' => 'array',
            'subtype' => 'int',
            'description' => '加人群标签的ids',
            'default' => []
        ],
        'del_groups' => [
            'type' => 'array',
            'subtype' => 'int',
            'description' => '减人群标签的ids',
            'default' => []
        ],
        'course_rate' => [
            'type' => 'int',
            'description' => '是否同步课程学习进度 0-否 1-是',
            'default' => 1
        ],
        'is_set_top' => [
            'type' => 'int',
            'description' => '是否置顶 0-否 1-是',
            'default' => 0
        ],
        'shift_range' => [
            'type' => 'array',
            'description' => '调班范围',
            'detail' => [
                'is_all' => ['type' => 'int', 'description' => '所有人 1-否 2-是', 'default' => 1],
                'range' => ['type' => 'array', 'subtype' => 'EmployeeRange', 'description' => '范围 [{id:1,type:0,(1-全公司 2-指定部门 3-指定人群 4-指定员工 5-指定岗位 6-职级),name:"姓名",children_included:0}]', 'default' => []]
            ]
        ],
        'application_hints' => [
            'type' => 'string',
            'description' => '报名提示',
            'default' => ''
        ],
        'enroll_extra_con' => [
            'type' => 'array',
            'description' => '智能班级-报名附加条件',
            'detail' => [
                'is_credit' => ['type' => 'int', 'description' => '是否启用学分限定 0-否 1-是'],
                'credit' => ['type' => 'float', 'description' => '学分限定值'],
                'is_certificate' => ['type' => 'int', 'description' => '是否启用证书 0-否 1-是'],
                'own_certificate_num' => ['type' => 'int', 'description' => '获得证书数量', 'default' => 0],
                'certificates' => ['type' => 'array', 'description' => '证书', 'default' => []],
                'is_exam' => ['type' => 'int', 'description' => '是否启用考试 0-否 1-是'],
                'exam' => ['type' => 'array', 'description' => '考试',
                    'detail' => [
                        'id' => ['type' => 'int', 'description' => '考试id', 'default' => 0],
                        'name' => ['type' => 'string', 'description' => '考试名称', 'default' => ''],
                    ]]
            ],
            'default' => []
        ],
        'enroll_setting' => [
            'type' => 'array',
            'description' => '报名设置',
            'detail' => [
                'cancle_enroll' => ['type' => 'int', 'description' => '是否允许取消报名 0-否 1-是', 'default' => 0],
                'cancel_deadline' => ['type' => 'string', 'description' => '取消报名截止时间', 'default' => ''],
                'alternate_setting' => ['type' => 'int', 'description' => '是否开启候补 0-否 1-是', 'default' => 0],
                'custom_enroll_time' => ['type' => 'int', 'description' => '是否自定义时间 0-否 1-是', 'default' => 0],
                'enroll_started' => ['type' => 'string', 'description' => '报名开始时间', 'default' => ''],
                'enroll_ended' => ['type' => 'string', 'description' => '报名结束时间', 'default' => ''],
            ]
        ],
        'is_enroll_form' => [
            'type' => 'int',
            'description' => '是否启用报名表单',
            'default' => 0
        ],
        'enroll_form' => [
            'type' => 'array',
            'description' => '报名表单',
            'detail' => [
                'display' => ['type' => 'array', 'description' => '姓名', 'detail' => [
                    'is_show' => ['type' => 'int', 'description' => '是否展示 0-否 1-是', 'default' => 0],
                    'must_fill' => ['type' => 'int', 'description' => '是否必填 0-否 1-是', 'default' => 0]
                ]],
                'mobile' => ['type' => 'array', 'description' => '手机号', 'detail' => [
                    'is_show' => ['type' => 'int', 'description' => '是否展示 0-否 1-是', 'default' => 0],
                    'must_fill' => ['type' => 'int', 'description' => '是否必填 0-否 1-是', 'default' => 0]
                ]],
                'position' => ['type' => 'array', 'description' => '职位', 'detail' => [
                    'is_show' => ['type' => 'int', 'description' => '是否展示 0-否 1-是', 'default' => 0],
                    'must_fill' => ['type' => 'int', 'description' => '是否必填 0-否 1-是', 'default' => 0]
                ]],
                'business_unit' => ['type' => 'array', 'description' => '业务单位', 'detail' => [
                    'is_show' => ['type' => 'int', 'description' => '是否展示 0-否 1-是', 'default' => 0],
                    'must_fill' => ['type' => 'int', 'description' => '是否必填 0-否 1-是', 'default' => 0]
                ]],
                'is_transport' => ['type' => 'int', 'description' => '是否可以安排交通 0-否 1-是', 'default' => 0],
                'is_hotel' => ['type' => 'int', 'description' => '是否可以安排住宿 0-否 1-是', 'default' => 0],
            ]
        ],
        'app_setting' => [
            'type' => 'array',
            'description' => 'h5按钮控制',
            'detail' => [
                'interaction_enabled' => ['type' => 'int', 'description' => '是否启用互动任务 0-否 1-是', 'default' => 0],
                'learn_square_enabled' => ['type' => 'int', 'description' => '是否启用学习广场 0-否 1-是', 'default' => 0],
                'ranking_enabled' => ['type' => 'int', 'description' => '是否启用排行榜 0-否 1-是', 'default' => 0],
                'learning_group_enabled' => ['type' => 'int', 'description' => '是否启用学习群 0-否 1-是', 'default' => 0],
                'notice_enabled' => ['type' => 'int', 'description' => '是否启用公告 0-否 1-是', 'default' => 0],
                'is_enroll' => ['type' => 'int', 'description' => '是否开启自主报名 0-否 1-是', 'default' => 0],
                'is_exam' => ['type' => 'int', 'description' => '是否开启结业考试 0-否 1-是', 'default' => 0],
                'is_assess' => ['type' => 'int', 'description' => '是否开启班级评估 0-否 1-是', 'default' => 0],
                'is_point_score' => ['type' => 'int', 'description' => '是否开启班级评分 0-否 1-是', 'default' => 0],
            ]
        ],
        'certificate_condition' => [
            'type' => 'array',
            'detail' => [
                'learning_rate' => ['type' => 'int', 'description' => '学习完成率 0-禁用 1-启用', 'default' => 0],
                'learning_condition' => ['type' => 'float', 'description' => '大于等于', 'default' => 0],
                'exam_result' => ['type' => 'int', 'description' => '指定考试 0-禁用 1-启用', 'default' => 0],
                'exam_condition' => ['type' => 'float', 'description' => '大于等于', 'default' => 0],
                'exam_id' => ['type' => 'int', 'description' => '指定考试ID', 'default' => 0],
                'exam_name' => ['type' => 'string', 'description' => '指定考试名称', 'default' => ''],
            ],
            'description' => '颁发证书/结业条件'
        ],
        'auto_graduation' => [
            'type' => 'int',
            'description' => '开启自动结业 0-否 1-是',
            'default' => 0
        ],
        'auto_certificate' => [
            'type' => 'int',
            'description' => '是否自动颁发证书 0-否 1-是',
            'default' => 0
        ],
        'certificate' => [
            'type' => 'array',
            'description' => '证书信息',
            'detail' => [
                'id' => ['type' => 'int', 'description' => '关联证书id', 'default' => 0],
                'name' => ['type' => 'string', 'description' => '关联证书名称', 'default' => ''],
            ]
        ],
        'evaluate_enabled' => [
            'type' => 'int',
            'description' => '开启学员考评 0-否 1-是',
            'default' => 0
        ],
        'official_num' => [
            'type' => 'int',
            'description' => '正式名额',
            'default' => 0
        ],
        'allow_enroll_num' => [
            'type' => 'int',
            'description' => '允许报名名额',
            'default' => 0
        ],
        'map' => [
            'type' => 'int',
            'description' => '1-默认样式 2-寻宝地图',
            'default' => 1
        ],
        'learner_type' => [
            'type' => 'int',
            'description' => '班级学员类型 0-普通班级 1-游客班级 2-混合班级(内部员工和游客皆可参与)',
            'enum' => [0, 1, 2],
            'default' => 0
        ],
        'learner_password' => [
            'type' => 'string',
            'description' => '游客班级密码',
            'default' => ''
        ],
        'learner_transfer' => [
            'type' => 'int',
            'default' => -1,
            'description' => '是否开启游客转正 -1不展示此配置 0不开启 1开启',
        ],
        'tourist_setting' => [
            'type' => 'array',
            'description' => '游客班级设置',
            'detail' => [
                'join_free' => ['type' => 'int', 'description' => '是否允许学员扫码自主加入班级 0-否 1-是', 'default' => 1]
            ],
            'default' => []
        ],
        'settings' => [
            'type' => 'array',
            'detail' => [
                'finished_first' => ['type' => 'float', 'default' => 0, 'description' => '最先完成学习计划占比多少'],
                'finished_first_credit' => ['type' => 'float', 'default' => 0, 'description' => '最先完成学习计划奖励学分'],
                'overtime_learn' => ['type' => 'int', 'default' => 1, 'description' => '是否允许超时学习 0-否 1-是']
            ],
            'description' => '设置'
        ],
        'organization' => [
            'type' => 'array',
            'detail' => [
                'id' => ['type' => 'int', 'description' => '部门id'],
                'name' => ['type' => 'string', 'description' => '部门名称'],
            ],
            'default' => [],
            'description' => '所属组织'
        ],
        'learner_confirm' => [
            'type' => 'int',
            'default' => 0,
            'description' => '是否开启学员确认 0-否 1-开启'
        ],
        'learner_confirm_group' => [
            'type' => 'array',
            'subtype' => 'EmployeeRange',
            'default' => [],
            'description' => '学员确认人'
        ],
        'learner_confirm_deadline' => [
            'type' => 'string',
            'default' => '',
            'description' => '学员确认截止时间'
        ],
        'category' => [
            'default' => [],
            'type' => 'array',
            'detail' => [
                'id' => ['type' => 'int', 'description' => '分类id'],
                'no' => ['type' => 'string', 'description' => '分类no'],
                'name' => ['type' => 'string', 'description' => '分类名称']
            ],
            'description' => '模板分类'
        ],

        'is_default_cover' => [
            'type' => 'int',
            'description' => '是否是默认封面 0-否 1-是',
            'default' => 1
        ],
        'cover' => [
            'type' => 'string',
            'description' => '封面'
        ],
        'training_center' => [
            'type' => 'array',
            'detail' => [
                'id' => ['type' => 'int', 'description' => '培训中心id'],
                'name' => ['type' => 'string', 'description' => '培训中心名称'],
            ],
            'default' => [],
            'description' => '培训中心'
        ],
        'training_center_dept' => [
            'type' => 'array',
            'detail' => [
                'id' => ['type' => 'int', 'description' => '部门id'],
                'name' => ['type' => 'string', 'description' => '部门名称'],
            ],
            'default' => [],
            'description' => '所属部门'
        ],
        'display_style' => [
            'type' => 'int',
            'default' => 0,
            'description' => '班级样式 0默认样式'
        ],

        // 内训额外参数
        'inside_training' => [
            'type' => 'array',
            'detail' => [
                'relation_id' => ['type' => 'int', 'default' => 0, 'description' => '任务计划关联表ID'],
                'delay_reason' => ['type' => 'string', 'default' => '', 'description' => '项目延期说明'],
            ]
        ],
        'simple_plan' => [
            'type' => 'int',
            'default' => 0,
            'enum' => [0, 1, 2],
            'description' => '0.默认班级 1.面授班 2.测训班'
        ],

        //自主学习清单,学习提醒设置
        'learn_notice_status' => [
            'type' => 'int',
            'default' => 0,
            'enum' => [0, 1],
            'description' => '是否开启学习提醒 0不开启 1开启',
        ],
        'learn_notice_type' => [
            'type' => 'int',
            'default' => 0,
            'enum' => [0, 1],
            'description' => '学习提醒类型 0每天 1每周x',
        ],
        'learn_notice_week' => [
            'type' => 'int',
            'default' => 0,
            'enum' => [1, 2, 3, 4, 5, 6, 7],
            'description' => '设为每周时对应的周几 1-7:周一到周日',
        ],
        'learn_notice_hour' => [
            'type' => 'int',
            'default' => 0,
            'description' => '提醒的时间段 6-23:6点到23点',
        ],
        'shift_location' => [
            'type' => 'string',
            'default' => '',
            'description' => '开班地点',
        ],
        'tags' => [
            'type' => 'array',
            'display' => '标签',
            'description' => '班级标签',
            'subtype' => 'string',
            'default' => [],
        ],
        'budget' => [
            'type' => 'int',
            'description' => '费用预算开启 0-关闭 1-开启',
            'enum' => [0, 1],
            'default' => 0
        ],
        'budget_type' => [
            'type' => 'int',
            'description' => '费用归属 1-培训计划 2-预算计划 3-计划外预算',
            'enum' => [1, 2, 3],
            'default' => 0
        ],
        'budget_id' => [
            'type' => 'int',
            'description' => '预算ID',
            'default' => 0
        ],
        'budget_detail_id' => [
            'type' => 'int',
            'description' => '预算明细ID',
            'default' => 0
        ],
        'costings' => [
            'type' => 'float',
            'description' => '费用预算',
            'default' => 0
        ],
        'actual_const' => [
            'type' => 'float',
            'description' => '实际费用',
            'default' => 0
        ],
        'actual_desc' => [
            'type' => 'string',
            'description' => '实际费用申报描述',
            'default' => ''
        ],
        'show_materials' => [
            'type' => 'int',
            'description' => '是否展示培训资料 0.否 1.是',
            'default' => 0
        ],
        'auto_end' => [
            'type' => 'array',
            'detail' => [
                'auto_type' => [
                    'type' => 'int',
                    'description' => '结束班级设置 0手动结束 1自动结束',
                    'default' => 0
                ],
                'end_time_type' => [
                    'type' => 'int',
                    'description' => '截止时间类型 1-班级截止时间 2指定时间',
                    'default' => 1
                ],
                'end_time' => [
                    'type' => 'string',
                    'description' => '指定时间',
                    'default' => ''
                ],
            ],
            'default' => []
        ],
        'completion_condition' => [
            'type' => 'int',
            'description' => '完成条件 1-加入算完成 2-提交材料算完成 3-提交材料且审核通过算完成',
            'enum' => [1, 2, 3],
            'default' => 1
        ],
        'completion_approval_id' => [
            'type' => 'int',
            'default' => 0,
            'description' => '审批流的id,int类型的number,for example:1'
        ],
        'completion_approval_name' => [
            'type' => 'string',
            'default' => "",
            'description' => '审批流的name'
        ],
        'completion_flow_data' => [
            'type' => 'array',
            'default' => [],
            'description' => '审批流信息'
        ],
        'enable_electronic_signature' => [
            'type' => 'int',
            'description' => '启用电子签名 0-否 1-需要',
            'enum' => [0, 1],
            'default' => 0
        ],
        'payer_type' => [
            'type' => 'int',
            'description' => '支付方 1-公司支付 2-个人支付',
            'enum' => [1, 2],
            'default' => 1
        ],
        'suppliers' => [
            'type' => 'array',
            'description' => '供应商',
            'default' => []
        ],
        'agreed_id' => [
            'type' => 'int',
            'description' => '预生成的外派培训id',
            'default' => 0
        ],
        'review_status' => [
            'type' => 'int',
            'description' => '发布审核状态 0-未审核 2 审核中 3-审核通过 4-审核拒绝',
            'default' => 0
        ],
        'training_need_question_ids' => [
            'type' => 'array',
            'description' => '培训需求关联问题IDS'
        ],
        'tracker' => [
            'type' => 'array',
            'subtype' => 'EmployeeRange',
            'description' => '跟踪人',
            'default' => []
        ],
        'tracker_range' => [
            'type' => 'int',
            'description' => '1-全员 2-数据权限范围内的学员 3-不允许添加',
            'default' => \App\Models\Stalker::STALKER_ADD_LEARNER_NONE
        ],
        'secret_enabled' => [
            'type' => 'int',
            'description' => '是否保密 1是 0否',
            'default' => 0
        ],
        'is_enabled' => [
            'type' => 'int',
            'description' => '是否启用 0-否 1-是',
            'default' => 1
        ],
    ];
}