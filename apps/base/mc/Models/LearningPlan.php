<?php
/**
 * 学习计划model.
 * User: wangyy
 * Date: 2017/8/17
 * Time: 9:54
 */

namespace App\Models;

use App\Common\Constants;
use App\Common\BaseModel;
use App\Common\Sequence;
use App\Models\AccountConfigure\CustomFields;
use App\Models\File\UrlSignGenerater;
use App\Models\Point\ClassPoint;
use App\Models\Training\NeedsQuestionLinkedClasses;
use App\Models\Training\TrainingNeedsQuestion;
use App\Models\TrainingCenter\Relationship;
use App\Models\TrainingCenter\Tree;
use App\Models\Learner\Learner as NewLearner;
use App\Models\Learner as OldLearner;
use App\Utils;
use Key\App;
use Key\Database\Mongodb;
use Key\Exception\AppException;
use Key\Records\Pagination;
use MongoDB\BSON\Regex;
use App\Common\QueueMessage;
use App\Common\CacheKeys;

class LearningPlan extends BaseModel
{
    const CACHE_PLAN_NOTIFICATION = 'WEIKE_PLAN_NOTIFICATION';
    const LEARNING_PLAN_NO_PERMISSION = 205;
    const LEARNING_PLAN_IS_RELATED_PROJECT = 216;

    const LEARNING_SIMPLE_NO_OFFLINE = 7902;

    //0-人 1-岗位 2-部门 3-人群 4-职级
    //1-全公司 2-部门 3-人群 4-员工 5-岗位 6-职级
    const APPLIED_RANGE_EMPLOYEE = 4;
    const APPLIED_RANGE_POSITION = 5;
    const APPLIED_RANGE_DEPARTMENT = 2;
    const APPLIED_RANGE_GROUP = 3;
    const APPLIED_RANGE_JOB_LEVEL = 6;

    //解锁方式 0-不限制 1-按阶段，阶段内任务不按顺序 2-按阶段，阶段内任务按顺序
    // 3-按阶段时间，阶段内任务不按顺序 4-按阶段时间，阶段内任务按顺序
    // 5-按阶段打卡， 6-按任务打卡 7-按任务解锁 限制每日学习任务上限
    const UNLOCK_NOT_LIMIT = 0;
    const UNLOCK_STAGE_TASK_NOT_IN_ORDER = 1;
    const UNLOCK_STAGE_TASK_IN_ORDER = 2;
    const UNLOCK_STAGE_TIME_TASK_NOT_IN_ORDER = 3;
    const UNLOCK_STAGE_TIME_TASK_IN_ORDER = 4;
    const UNLOCK_STAGE_PERIOD = 5;
    const UNLOCK_TASK_PERIOD = 6;
    const UNLOCK_TASK_DAY_LEARNED_LIMIT = 7;

    //智能班级-开启报名审核 0-不审核 1-班级管理员审核 2-部门经理审核 3-直属上级审核 4指定人员审核 5多级审批
    const EXAMINE_WITHOUT = 0;
    const EXAMINE_MONITOR = 1;
    const EXAMINE_DEPT_MANAGER = 2;
    const EXAMINE_SUPERVISOR = 3;
    const EXAMINE_GROUPS_EMPLOYEE = 4;
    const EXAMINE_APPROVAL = 5;

    //是否结束 0-未结束 1-已结束
    const NOT_END = 0;
    const ENDED = 1;

    //学习模块儿状态 0-删除 1-草稿 2-发布
    const STATUS_DRAFT = 1;
    const STATUS_PUBLISHED = 2;

    //学习模块儿状态 0-删除 1-待提交 2-待发布
    const LEARNING_UNSUBMIT = 1;
    const LEARNING_UNPUBLISH = 2;

    //班级类型 0-智能班级 1-面授班 2-测训班
    const CLASS_DEFAULT = 0;
    const CLASS_OFFLINE = 1;
    const CLASS_TRAINING = 2;

    //source_type类型 1-培训计划
    const SOURCE_TRAINING = 1;

    //自动结束 0关闭 1开启
    const AUTO_TYPE_DISABLED = 0;
    const AUTO_TYPE_ENABLED = 1;

    //操作记录日志LOG
    const LOG_CREATE = '新增班级信息';
    const LOG_EDIT = '编辑班级信息';
    const LOG_DELETE = '删除班级信息';

    //保密班级
    const SECRET_DISABLED = 0;
    const SECRET_ENABLED = 1;

    const NOT_ENOUGH_SCORE = 4000; //没有足够的积分

    //获取当前登录用户的employee_id
    const NEED_TRANSFER_CODE = [
        'jasolar',
        '1234'
    ];

    protected $commonFilters = null;

    public function setCommonFilter($commonFilters)
    {
        $this->commonFilters = $commonFilters;
        return $this;
    }

    protected function getEid()
    {
        $session = $this->app['session'];
        return $session->get(Constants::SESSION_KEY_CURRENT_EMPLOYEE_ID);
    }

    public function getRedisId($learning_id, $aid = 0, $key_name = "LEARNING_PLAN")
    {
        $aid = $aid ?: $this->aid;
        return $key_name . ':' . $aid . '_' . $learning_id;
    }

    /**
     * 查看学习计划的名称是否存在.
     *
     * @param string $name
     * @param int $learning_id
     * @param int $type
     * @return boolean
     */
    public function nameExist($name, $type = 0, $learning_id = 0)
    {
        if ($learning_id && !is_array($learning_id)) $learning_id = [$learning_id];
        $cond = [
            'name' => $name,
            'aid' => $this->aid,
            'type' => $type,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]]
        ];
        if ($learning_id) $cond['id'] = ['$nin' => $learning_id];

        $result = $this->getMongoMasterConnection()->fetchRow(Constants::COLL_LEARNING_PLAN, $cond);

        return $result ?: [];
    }

    protected function getInitData($project_id)
    {
        $employee_model = new Employee($this->app);
        $employee = $employee_model->view($this->eid, $this->aid, ['display' => 1, 'no' => 1, 'department_id' => 1, 'department_name' => 1]);

        return [
            'id' => Sequence::getSeparateId('learning_plan', $this->aid),
            'uid' => $this->eid,
            'aid' => $this->aid,
            'learner_num' => 0,
            'finished_rate' => 0,
            'source' => 0,
            'learning_status' => self::LEARNING_UNPUBLISH,
            'project_id' => $project_id,
            'is_end' => self::NOT_END,
            'late_hours' => 0, //签到迟到设置2h
            'is_appointed' => 1, //默认定点签到
            'default_distance' => 500, //默认定点签到距离
            'sign_settings' => [
                'rate_setting' => 2, //1-请假算进度 2-请假不算进度
                'late_hours' => 0, //签到迟到设置2h
                'is_appointed' => 1, //默认定点签到
                'default_distance' => 500, //默认定点签到距离
            ],
            'employee_status' => 1,
            'shift_setting' => [],
            'display' => ArrayGet($employee, 'display', ''),
            'creator_no' => ArrayGet($employee, 'no', ''),
            'creator' => [
                'id' => $this->eid,
                'no' => ArrayGet($employee, 'no', ''),
                'display' => ArrayGet($employee, 'display', ''),
                'department_id' => ArrayGet($employee, 'department_id', 0),
                'department_name' => ArrayGet($employee, 'department_name', '')
            ],
            'created' => Mongodb::getMongoDate(),
            'updated' => Mongodb::getMongoDate()
        ];
    }

    public function noExists($no, $type, $fields = [], $ignore_id = 0)
    {
        $cond = [
            'aid' => $this->aid,
            'status' => ['$nin' => [static::DISABLED]],
            'no' => $no,
            'type' => $type
        ];
        if ($ignore_id) $cond['id'] = ['$nin' => [$ignore_id]];
        $result = $this->getMongoMasterConnection()->fetchRow(Constants::COLL_LEARNING_PLAN, $cond, $fields ?: [
            'created' => 0,
            'updated' => 0,
            'no' => 0
        ]);

        return $result ?: [];
    }

    public function listByNos($nos, $type, $fields = [], $ignore_id = 0)
    {
        $cond = [
            'aid' => $this->aid,
            'status' => ['$nin' => [static::DISABLED]],
            'no' => ['$in' => $nos],
            'type' => $type
        ];
        if ($ignore_id) $cond['id'] = ['$nin' => [$ignore_id]];
        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, $cond, 0, 0, [], $fields ?: [
            'created' => 0,
            'updated' => 0,
            'no' => 0
        ]);

        return $result ?: [];
    }

    /**
     * 获取编码.
     *
     * @param int $type .
     * @return boolean|int
     */
    public function getNo($type)
    {
        switch ($type) {
            case Constants::LEARNING_PLAN:
                $prefix = 'LP';
                $seq_name = 'learning_plan_no';
                break;
            case Constants::LEARNING_CLASS:
                $prefix = 'LC';
                $seq_name = 'learning_class_no';
                break;
            case Constants::LEARNING_TRAINING:
                $prefix = 'LT';
                $seq_name = 'learning_training_no';
                break;
        }
        $no_num = Sequence::getSeparateId($seq_name, $this->aid, 1, 0);
        $len = strlen($no_num);
        if ($len < 6) {
            $zero_num = 6 - $len;
            for ($i = 0; $i < $zero_num; $i++) {
                $prefix = $prefix . '0';
            }
        }
        return $prefix . $no_num;
    }

    /**
     * 创建学习计划.
     *
     * @param array $record
     * @param int $type
     * @param int $project_id
     * @return boolean|int
     */
    public function create()
    {
        echo 'hello world';
        return true;
    }

}
