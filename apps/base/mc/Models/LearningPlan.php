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
    public function create($record, $type = Constants::LEARNING_CLASS, $project_id = 0, $is_watch = 0)
    {
        if (!$is_watch) $record = $record->toArray();
        //调班范围 -- 针对多班次项目
        if (ArrayGet($record['shift_range'], 'range', [])) {
            foreach ($record['shift_range']['range'] as $k => $val) {
                $record['shift_range']['range'][$k] = $val->toArray();
            }
        }
        $record['is_watch'] = $is_watch;
        //关联学习计划
        $source_id = ArrayGet($record, 'source_id');
        $source_type = ArrayGet($record, 'source', 1);
        if ($source_id) {
            $is_related = $this->isRelatedClass($source_id, $source_type);
            if ($is_related) return self::LEARNING_PLAN_IS_RELATED_PROJECT;
        }
        //自主学习计划
        if ($type == Constants::LEARNING_AUTONOMY) $record['status'] = 2;
        if ($type == Constants::LEARNING_CLASS) $record['is_applicant'] = ArrayGet($record['app_setting'], 'is_enroll', 0);

        if (ArrayGet($record, 'learner_password', '')) $record['learner_password'] = strtolower($record['learner_password']);
        $record['start_time'] = Mongodb::getMongoDate($record['start_time'] / 1000);
        $record['end_time'] = Mongodb::getMongoDate($record['end_time'] / 1000);

        $new_data = array_merge($this->getInitData($project_id), $record);
        if ($record['learner_confirm']) $new_data['learning_status'] = self::LEARNING_UNSUBMIT;
        $new_data['type'] = $type;
        if ($project_id != 0) {
            $project_model = new LearningProject($this->app);
            $project = $project_model->isExists($project_id, ['shift_setting' => 1, 'title' => 1, 'is_self_registration' => 1, 'is_delegate_enroll' => 1, 'is_payment_voucher' => 1]);
            $new_data['project_name'] = ArrayGet($project, 'title', '');
            $new_data['shift_setting'] = ArrayGet($project, 'shift_setting', []);
            $new_data['is_self_registration'] = ArrayGet($project, 'is_self_registration', 0);
            $new_data['is_delegate_enroll'] = ArrayGet($project, 'is_delegate_enroll', 0);
            $new_data['is_payment_voucher'] = ArrayGet($project, 'is_payment_voucher', 0);
        }
        if (!$new_data['no'] && in_array($type, [Constants::LEARNING_PLAN, Constants::LEARNING_CLASS, Constants::LEARNING_TRAINING])) {
            $new_data['no'] = $this->getNo($type);
        }
        if (!ArrayGet($new_data, 'organization.id') && in_array($type, [Constants::LEARNING_PLAN, Constants::LEARNING_CLASS])) {
            $new_data['organization'] = ['id' => $new_data['creator']['department_id'], 'name' => $new_data['creator']['department_name']];
        }

        if ($type === Constants::LEARNING_TRAINING && $record["agreed_id"]) {
            $new_data['id'] = $record["agreed_id"];
            unset($new_data["agreed_id"]);
        }

        $result = $this->getMongoMasterConnection()->insert(Constants::COLL_LEARNING_PLAN, $new_data);

        //完全版班级，创建班级默认海报数据
        if (($type == Constants::LEARNING_CLASS || $type == Constants::LEARNING_PLAN) && in_array($new_data['simple_plan'], [self::CLASS_DEFAULT, self::CLASS_TRAINING])) {
            $billModel = new LearningBill($this->app);
            $billModel->createBill($new_data['id'], $type);
        }

        if ($result) {
            //班级标签
            $knowledgePoint = new KnowledgePoint($this->app);
            $knowledgePoint->calUsesByName($new_data['id'], $new_data['tags'], KnowledgePoint::POINT_CLASS);

            //如果是培训计划，则优先复制培训计划中的阶段和任务，如果培训计划没有，则再初始化阶段和任务
            $copyResult = false;
            if ($source_id && $source_type == self::SOURCE_TRAINING) {
                //复制任务分组及任务
                $group = new LearningTask($this->app);
                $copyResult = $group->copy($source_id, Constants::TRAINING_PLAN_PROGRAM, $new_data['id'], $type, 0, ['simple_plan' => LearningPlan::CLASS_DEFAULT]);
            }
            //如果是false，并且前端不是关联培训计划则创建默认阶段和任务
            if (!$copyResult) {
                //初始化阶段
                // if ($type != Constants::LEARNING_TRAINING) {
                if (!in_array($type, [Constants::LEARNING_TRAINING, Constants::LEARNING_POSITION_AUTHENTICATION])) {
                    $group = new LearningTask($this->app);
                    if (in_array($new_data['simple_plan'], [self::CLASS_DEFAULT, self::CLASS_TRAINING])) {
                        //完全版默认数据
                        $group->initializeGroup($new_data['id'], $type, $project_id);
                    } else {
                        //简化版默认数据
                        $group->initializeSimpleGroup($new_data['id'], $type, $project_id);
                    }
                }
            }

            //初始化班级学员分组
            if ($type == Constants::LEARNING_CLASS) $this->createPkGroup($new_data['id'], ['name' => '未分组', 'project_id' => 0], 0);
            //如果关联项目，调用项目接口存班级id
            if (($type == Constants::LEARNING_CLASS || $type == Constants::LEARNING_TRAINING) && $source_id) {
                $training_model = new TrainingPlan($this->app);
                $training_model->changeOpStatus($source_id, $new_data['id'], $type);
            }
            //自主学习计划--将发起人加入学习计划（学员自己发起，自己做）
            if ($type == Constants::LEARNING_AUTONOMY) {
                $learner_model = new Learner($this->app);
                $learner_model->addLearnerByGroup($new_data['id'], [], [$this->eid], $type, 1, ['learning_status' => Learner::LEARNING_STATUS_PUBLISHED]);
            }
            $this->addRelationTrainingCenter($new_data['id'], $type, ArrayGet($new_data, 'training_center.id', 0));

            //操作日志
            if (in_array($type, [Constants::LEARNING_CLASS, Constants::LEARNING_PLAN])) {
                $module = $type == Constants::LEARNING_CLASS ? 'class' : 'plan';
                $this->setNewData($new_data)
                    ->setOldData([])
                    ->setOp(self::OP_CREATE)
                    ->describe($new_data['id'], $new_data['name'], '新增')
                    ->setOpModule($module)
                    ->saveOpLog();
            }

            //旺旺新增智能班级，记录数据。
            if ($this->aid == env('WANGWANG_DATA_BACK_AID', '4702') && $type == Constants::LEARNING_CLASS) {
                $this->addLearningDataLog($new_data, self::LOG_CREATE, 1);
            }

            // 关联内训信息
            if ($type == Constants::LEARNING_CLASS && $record['inside_training']['relation_id']) {
                $relation_model = new InsideTrainingDeptTaskRelation($this->app);
                $relation_model->updateClassInfo($new_data['id'], $record['inside_training']);
            }

            //创建班级，如果有培训需求关联IDS，调用张阳阳接口
            if ($training_need_question_ids = ArrayGet($new_data, 'training_need_question_ids', [])) {
                $modelNeedsQuestionLinkedClasses = new NeedsQuestionLinkedClasses($this->app);
                $modelNeedsQuestionLinkedClasses->saveByClass($new_data['id'], $new_data['training_need_question_ids']);
            }
        }

        return $result ? $new_data['id'] : false;
    }

    /**
     * 获取学习计划列表.
     *
     * @param array $filters .
     * @param int $type .
     * @param int $project_id .
     * @param null|\Key\Records\Pagination $pg .
     * @param string $sort .
     * @param array $fields .
     * @param int $is_now 1实时计算学员人数，覆盖learner_num .
     * @return array
     */
    public function getList($filters, $type, $project_id = 0, $pg = null, $sort = [], $fields = [], $is_now = 0)
    {
        if (!$pg) {
            $pg = new Pagination();
            $pg->setPage(0)->setItemsPerPage(0);
        }
        $cond = $this->getCondition($filters, $type, $project_id);

        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, $cond,
            $pg->getOffset(),
            $pg->getItemsPerPage(),
            $sort ?: [
                'is_end' => 1,
                'status' => 1,
                'updated' => -1
            ], $fields ?: [
                'aid' => 0,
                'desc' => 0
            ]
        );


        $learner_num = 0;
        $is_redis = 0;
        if ($is_now == 1) {
            $learner_num = 1;
            $is_redis = 1;
        }
        if ($result) {
            if ($learner_num) {
                $learning_ids = array_column($result, 'id');
                $model = new Learner($this->app);
                $learners = $model->getLearningsLearnerNum($learning_ids, $type, [], $is_redis);
            }
            $emp_model = new Employee($this->app);
            $creator_list = $emp_model->getByIds(array_column($result, 'uid'), ['id' => 1, 'no' => 1, 'display' => 1, 'department_id' => 1, 'department_name' => 1]);
            $creator_list = array_column($creator_list, null, 'id');
            foreach ($result as $k => $val) {
                $creator = $creator_list[$val['uid']] ?? [];
                if ($creator) {
                    $val['display'] = $creator['display'];
                    $val['creator_no'] = $creator['no'];
                    $val['creator']['id'] = $creator['id'];
                    $val['creator']['no'] = $creator['no'];
                    $val['creator']['display'] = $creator['display'];
                    $val['creator']['department_id'] = $creator['department_id'];
                    $val['creator']['department_name'] = $creator['department_name'];
                }

                if ($learner_num) $val['learner_num'] = ArrayGet($learners, $val['id'], 0);
                if (isset($val['start_time'])) $val['start_time'] = (string)$val['start_time'];
                if (isset($val['end_time'])) $val['end_time'] = (string)$val['end_time'];
                if (isset($val['created'])) $val['created'] = (string)$val['created'];
                if (isset($val['updated'])) $val['updated'] = (string)$val['updated'];
                $result[$k] = $val;
            }
        }

        $this->getCustomFields($result);
        Utils::convertMongoDateToTimestamp($result);
        return $result ?: [];
    }

    private function getCustomFields(&$result){
        $customFieldsKV = [];
        $modelCustomFields = new CustomFields($this->app);
        $customFields = $modelCustomFields->getFields(CustomFields::MODEL_LEARNING_PLAN);
        foreach ($customFields as $customField) {
            if ($customField['def']['enabled']) {
                $customFieldsKV[$customField['def']['name']] = $customField['def']['key'];
            }
        }

        foreach ($result as $key => $value) {
            $custom = [];

            //$customKey-自定义字段名称 $customValue-自定义字段key
            foreach ($customFieldsKV as $customKey => $customValue) {
                $custom[$customKey] = '';
                if (isset($value[$customValue])) {

                    if (is_array($value[$customValue])) {
                        $value[$customValue] = $value[$customValue] ? implode(',', $value[$customValue]) : '';
                    }

                    $custom[$customKey] = $value[$customValue]; //班级的自定义字段值重新赋值到自定义字段名称上
                }
            }
            $value['custom'] = $custom;
            $result[$key] = $value;
        }
    }

    /**
     * 获取学习计划列表.(单独做一个接口，为区分审核状态对应的人数，因为LearningPlan->getList涉及地方太多，影响较大)
     *
     * @param array $filters .
     * @param int $type .
     * @param int $project_id .
     * @param null|\Key\Records\Pagination $pg .
     * @param string $sort .
     * @param array $fields .
     * @return array
     */
    public function getListByProject($filters, $type, $project_id = 0, $pg = null, $sort = [], $fields = [], $learner_num = 1)
    {
        if (!$pg) {
            $pg = new Pagination();
            $pg->setPage(0)->setItemsPerPage(0);
        }
        $cond = $this->getCondition($filters, $type, $project_id);

        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, $cond,
            $pg->getOffset(),
            $pg->getItemsPerPage(),
            $sort ?: [
                'is_end' => 1,
                'status' => 1,
                'updated' => -1
            ], $fields ?: [
                'aid' => 0,
                'desc' => 0
            ]
        );
        Utils::convertMongoDateToTimestamp($result);
        if ($result) {
            if ($learner_num) {
                $learning_ids = array_column($result, 'id');
                $model = new Learner($this->app);
                $rules['is_examine'] = -1;
                $learners = $model->getLearningsLearnerNumExamine($learning_ids, $type, $rules);
            }
            foreach ($result as $k => $val) {
                if ($learner_num) {
                    $val['learner_num'] = ArrayGet($learners, $val['id'] . '_1', 0);
                    $val['learner_num_un'] = ArrayGet($learners, $val['id'] . '_0', 0);
                }
                $result[$k] = $val;
            }
        }

        return $result ?: [];
    }

    /**
     * 无功能权限-工作台-智能班级
     *
     * @param array $filters .
     * @param int $type .
     * @param int $project_id .
     * @param null|\Key\Records\Pagination $pg .
     * @param string $sort .
     * @param array $fields .
     * @return array
     */
    public function getListByProjectPre($filters, $type, $project_id = 0, $pg = null, $sort = [], $fields = [], $learner_num = 1)
    {
        if (!$pg) {
            $pg = new Pagination();
            $pg->setPage(0)->setItemsPerPage(0);
        }
        $cond = $this->getConditionPre($filters, $type, $project_id);

        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, $cond,
            $pg->getOffset(),
            $pg->getItemsPerPage(),
            $sort ?: [
                'is_end' => 1,
                'status' => 1,
                'updated' => -1
            ], $fields ?: [
                'aid' => 0,
                'desc' => 0
            ]
        );
        Utils::convertMongoDateToTimestamp($result);
        if ($result) {
            if ($learner_num) {
                $learning_ids = array_column($result, 'id');
                $model = new Learner($this->app);
                $rules['is_examine'] = -1;
                $learners = $model->getLearningsLearnerNumExamine($learning_ids, $type, $rules);
            }
            foreach ($result as $k => $val) {
                if ($learner_num) {
                    $val['learner_num'] = ArrayGet($learners, $val['id'] . '_1', 0);
                    $val['learner_num_un'] = ArrayGet($learners, $val['id'] . '_0', 0);
                }
                $result[$k] = $val;
            }
        }

        return $result ?: [];
    }

    /**
     * 根据多班次ID，获取多班次整体项目完成度
     * @param $project_id
     * @return int
     */
    public function getListAvgRate($filters, $type, $project_id = 0, $pg = null, $sort = [], $fields = [], $learner_num = 1)
    {
        if (!$pg) {
            $pg = new Pagination();
            $pg->setPage(0)->setItemsPerPage(0);
        }
        $cond = $this->getCondition($filters, $type, $project_id);

        $result = $this->getMongoMasterConnection()->aggregate(Constants::COLL_LEARNING_PLAN, [
            ['$match' => $cond],
            ['$group' => ['_id' => null, 'rate' => ['$avg' => '$finished_rate']]]
        ]);

        return $result ? $result[0]['rate'] : 0;
    }

    public function getProjectInfo($project_id, $groups, &$project_creator, &$is_project_monitor)
    {
        $project_model = new LearningProject($this->app);
        $project = $project_model->isExists($project_id, ['creator_eid' => 1, 'monitor' => 1]);
        $project_creator = ArrayGet($project, 'creator_eid', 0);
        $is_project_monitor = 0;
        if ($project_monitor = ArrayGet($project, 'monitor', [])) {
            foreach ($project_monitor as $m_val) {
                if (($m_val['type'] == 4 && $m_val['id'] == $this->eid) || ($m_val['type'] == 3 && in_array($m_val['id'], $groups))) {
                    $is_project_monitor = 1;
                    break;
                }
            }
        }
    }

    //获取查询条件
    protected function getCondition($filters, $type = 0, $project_id = 0)
    {
        $cond = [
            'aid' => $this->aid,
            'type' => $type,
            'project_id' => $project_id,
            '$or' => [
                ['uid' => $this->eid],
                ['monitor' => ['$elemMatch' => ['id' => $this->eid, 'type' => self::APPLIED_RANGE_EMPLOYEE]], 'secret_enabled' => ['$ne' => self::SECRET_ENABLED]]
            ],
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]]
        ];
        if (is_array($type)) {
            $cond['type'] = ['$in' => $type];
        }
        if ($status = ArrayGet($filters, 'status', '')) $cond['status'] = $status;

        if (($simple_plan = ArrayGet($filters, 'simple_plan', -1)) != -1) {
            $cond['simple_plan'] = $simple_plan;
        }

        $source_detail = ArrayGet($filters, 'source_detail', '');
        $data_from = ArrayGet($filters, 'data_from', '');
        switch ($data_from) {
            case 'all_learning_include_project':
                unset($cond['$or']);
                unset($cond['project_id']);
                $cond['status'] = ['$nin' => [static::DISABLED]];
                break;
            case 'all_learning':
                unset($cond['$or']);
                $cond['status'] = ['$nin' => [static::DISABLED]];
                break;
            case 'all_published_not_end':
                unset($cond['$or']);
                unset($cond['project_id']);
                $cond['is_end'] = 0;
                $cond['status'] = self::STATUS_PUBLISHED;
                break;
            case 'all_published':
                unset($cond['$or']);
                unset($cond['project_id']);
                $cond['status'] = self::STATUS_PUBLISHED;
                break;
            case 'all_ended':
                unset($cond['$or']);
                unset($cond['project_id']);
                $cond['status'] = self::STATUS_PUBLISHED;
                $cond['is_end'] = 1;
                break;
            case 'statistics_learning':
                unset($cond['$or']);
                if ($project_id == -1) unset($cond['project_id']);
                break;
            case 'my_created':
                unset($cond['$or']);
                if ($source_detail == 'workstand') {
                    unset($cond['project_id']); //如果是工作台获取班级列表，去掉多班次条件
                }
//                unset($cond['project_id']);
                $cond['uid'] = $this->eid;
                break;
            default:
                $project_creator = 0;
                $employee_model = new Employee($this->app);
                $employee = $employee_model->view($this->eid, $this->aid, ['groups' => 1, 'roles' => 1]);
                $groups = ArrayGet($employee, 'groups', []);
                $roles = ArrayGet($employee, 'roles', []);
                $is_project_monitor = 0;
                if ($project_id) $this->getProjectInfo($project_id, $groups, $project_creator, $is_project_monitor);
                //超管、项目创建人和管理员可以查看并管理项目下所有的班级
                //旺旺：超管、项目创建人、指定角色可以查看并管理项目下所有的班级，其它只能查看并管理自己创建且作为管理员的班级
                if ($project_creator == $this->eid || ($is_project_monitor && $this->aid != env('WANGWANG_DATA_BACK_AID', '4702')) ||
                    ($this->aid == env('WANGWANG_DATA_BACK_AID', '4702') && in_array(10153, $roles))) {
                    unset($cond['$or']);
                } else if ($type != Constants::LEARNING_AUTONOMY) {
                    if ($groups) $cond['$or'][] = ['monitor' => ['$elemMatch' => ['id' => ['$in' => $groups], 'type' => self::APPLIED_RANGE_GROUP]], 'secret_enabled' => ['$ne' => self::SECRET_ENABLED]];
                    $source = ArrayGet($filters, 'source', 0); //来源 1-pc学习 0-pc管理
                    if ($project_id == -1) unset($cond['project_id']);
                    if (!$source) {
                        $permission_model = new Permission($this->app);
                        if ($permission_model->usedTrainingCenter()) { //培训中心-美团用的，可忽略
                            if (in_array($type, [Constants::LEARNING_CLASS, Constants::LEARNING_PLAN])) {
                                $treeModel = new Tree($this->app);
                                $center_ids = $treeModel->getManageableIds();
                                $cond['$or'][] = ['training_center.id' => ['$in' => $center_ids]];
                            }
                        } else {
                            $is_admin = $permission_model->isAdmin();
                            if ($is_admin) { //判断是不是超管
                                unset($cond['$or']);
                            } else if (in_array($type, [Constants::LEARNING_CLASS, Constants::LEARNING_PLAN]) && $this->aid != env('WANGWANG_DATA_BACK_AID', '4702')) { //旺旺4702不需要数据权限过滤
                                $module = Constants::PERMISSION_CLASS;
                                if ($type == Constants::LEARNING_PLAN) $module = Constants::PERMISSION_LEARNING_PLAN;
                                $is_whole_company = $permission_model->isWholeCompany($module); //数据权限是不是全公司
                                if (!$is_whole_company) {
                                    $department_ids = $permission_model->getDepartmentIds($module); //所属组织过滤
                                    $cond['$or'][] = ['organization.id' => ['$in' => $department_ids], 'secret_enabled' => ['$ne' => self::SECRET_ENABLED]];
                                } else {
                                    unset($cond['$or']);
                                }
                            }
                            if (!$is_admin && $type == Constants::LEARNING_PLAN && $this->aid == env('LEARNING_PACK_PERMISSION', 1761)) {
                                $admin_eids = $permission_model->getEidsByRoleIds();
                                $admin_eids[] = $this->eid;
                                $cond['uid'] = ['$in' => $admin_eids];
                            }
                        }
                    }
                }
        }

        if ($project_ids = ArrayGet($filters, 'project_ids', [])) $cond['project_id'] = ['$in' => $project_ids];
        if ($audit_status = ArrayGet($filters, 'audit_status', [])) {
            $cond['review_status'] = $audit_status;
        }
        if ($plan_ids = ArrayGet($filters, 'ids', [])) $cond['id'] = ['$in' => $plan_ids];
        if ($labels = ArrayGet($filters, 'labels', [])) {
            $learnings = $this->learningsByTags($type, $labels);
            $learningIds = array_column($learnings, 'id');
            $plan_ids = array_merge($learningIds, $plan_ids);
            $cond['id'] = ['$in' => $plan_ids];
        }
        if ($ignore_ids = ArrayGet($filters, 'ignore_ids', [])) $cond['id'] = ['$nin' => $ignore_ids];
        if ($keyword = ArrayGet($filters, 'keyword', '')) {
            $or = [
                ['name' => new Regex($keyword, 'i')],
                ['display' => new Regex($keyword, 'i')],
                ['no' => new Regex($keyword, 'i')],
            ];
            if (ArrayGet($cond, '$or')) {
                $cond['$and'] = [
                    ['$or' => $cond['$or']],
                    ['$or' => $or],
                ];
                unset($cond['$or']);
            } else {
                $cond['$or'] = $or;
            }
        }

        $start_time = ArrayGet($filters, 'start_time', '');
        $end_time = ArrayGet($filters, 'end_time', '');
        if ($start_time && $end_time) {
            $cond['start_time'] = [
                '$gte' => Mongodb::getMongoDate($start_time / 1000),
                '$lt' => Mongodb::getMongoDate($end_time / 1000),
            ];
        } else if ($start_time) {
            $cond['start_time'] = ['$gte' => Mongodb::getMongoDate($start_time / 1000)];
        } else if ($end_time) {
            $cond['start_time'] = ['$lt' => Mongodb::getMongoDate($end_time / 1000)];
        }

        $filter = ArrayGet($filters, 'filter', 0);
        switch ($filter) {
            case 1:
                //获取未开始学习计划的数量(已发布,当前时间<开始时间) 外派培训--未开始.
                $cond['is_end'] = 0;
                $cond['status'] = 2;
                if ($start_time) {
                    if ($start_time / 1000 < time()) $cond['start_time']['$gt'] = Mongodb::getMongoDate(time());
                } else {
                    $cond['start_time'] = ['$gt' => Mongodb::getMongoDate(time())];
                }
//                $cond['start_time'] = ['$gt' => Mongodb::getMongoDate(time())];
                break;
            case 2:
                //获取未完成学习计划的数量(已发布,开始时间<当前时间<结束时间,完成率不到100%) 外派培训--进行中.
                $cond['status'] = 2;
                $cond['is_end'] = 0;
                if ($end_time) {
                    if ($end_time / 1000 > time()) $cond['start_time']['$lte'] = Mongodb::getMongoDate(time());
                } else {
                    $cond['start_time'] = ['$lte' => Mongodb::getMongoDate(time())];
                }
//                $cond['start_time'] = ['$lte' => Mongodb::getMongoDate(time())];
                $cond['end_time'] = ['$gt' => Mongodb::getMongoDate(time())];
                if ($type != Constants::LEARNING_TRAINING) $cond['finished_rate'] = ['$lt' => 100];
                break;
            case 3:
                //获取已完成学习计划的数量(已发布,开始时间<当前时间<结束时间,完成率100%).
                $cond['status'] = 2;
                $cond['is_end'] = 0;
                if ($type == Constants::LEARNING_TRAINING) {
                    $cond['end_time'] = ['$lt' => Mongodb::getMongoDate(time())];
                } else {
                    if ($end_time) {
                        if ($end_time / 1000 > time()) $cond['start_time']['$lt'] = Mongodb::getMongoDate(time());
                    } else {
                        $cond['start_time'] = ['$lt' => Mongodb::getMongoDate(time())];
                    }
//                    $cond['start_time'] = ['$lt' => $time];
                    $cond['finished_rate'] = 100;
                }
                break;
            case 4:
                //获取已延期学习计划的数量(已发布,当前时间>结束时间,完成率<100%)  外派培训--已延期.
                $cond['status'] = 2;
                $cond['is_end'] = 0;
                $cond['end_time'] = ['$lt' => Mongodb::getMongoDate(time())];
                if ($type == Constants::LEARNING_TRAINING) {
                    $cond['is_end'] = self::NOT_END;
                } else {
                    $cond['finished_rate'] = ['$lt' => 100];
                }
                break;
            case 5:
                //获取未发布学习计划的数量  外派培训--草稿
                $cond['learning_status'] = self::LEARNING_UNPUBLISH;
                $cond['status'] = static::ENABLED;
                break;
            case 6:
                //已结束  外派培训--已结束
                $cond['is_end'] = 1;
                break;
            case 7:
                //待提交  智能班级-需要学员确认
                $cond['learner_confirm'] = 1;
                $cond['learning_status'] = self::LEARNING_UNSUBMIT;
            case 8:
                //已发布  已发布,未手动结束
                $cond['status'] = self::STATUS_PUBLISHED;
                $cond['is_end'] = self::NOT_END;
                break;
        }
        $org_id = ArrayGet($filters, 'org_id', 0);
        $org_children_included = ArrayGet($filters, 'org_children_included', 0);
        if ($org_id) {
            if ($org_children_included) {
                $dept_model = new Department($this->app);
                $depts = $dept_model->getAllChildren([$org_id], ['id' => 1], true);
                $dept_ids = array_column($depts, 'id');
                $cond['organization.id'] = ['$in' => $dept_ids];
            } else {
                $cond['organization.id'] = $org_id;
            }
        }
        if ($dept_ids = ArrayGet($filters, 'dept_ids', [])) {
            $dept_model = new Department($this->app);
            $depts = $dept_model->getAllChildren($dept_ids, ['id' => 1], true);
            $dept_ids = array_column($depts, 'id');
            $cond['organization.id'] = ['$in' => $dept_ids];
        }
        $publish_start_time = ArrayGet($filters, 'publish_start_time', '');
        $publish_end_time = ArrayGet($filters, 'publish_end_time', '');
        if ($publish_start_time && $publish_end_time) {
            $cond['publish_time'] = [
                '$gte' => Mongodb::getMongoDate($publish_start_time / 1000),
                '$lt' => Mongodb::getMongoDate($publish_end_time / 1000),
            ];
        } else if ($publish_start_time) {
            $cond['publish_time'] = ['$gte' => Mongodb::getMongoDate($publish_start_time / 1000)];
        } else if ($publish_end_time) {
            $cond['publish_time'] = ['$lt' => Mongodb::getMongoDate($publish_end_time / 1000)];
        }
        if (($cag_id = ArrayGet($filters, 'category_id', -1)) != -1) {
            if (is_array($cag_id)) {
                $cond['category.id'] = ['$in' => $cag_id];
            } else {
                $cond['category.id'] = $cag_id;
            }

        }

        // 班级来源 1班级 2计划任务管理
        if ($class_source = ArrayGet($filters, 'class_source', 0)) {
            if ($class_source == 1) {
                $or = [
                    ['inside_training' => ['$exists' => 0]],
                    ['inside_training.relation_id' => 0],
                ];
                if (ArrayGet($cond, '$or')) {
                    $cond['$and'] = [
                        ['$or' => $cond['$or']],
                        ['$or' => $or],
                    ];
                    unset($cond['$or']);
                } elseif (ArrayGet($cond, '$and')) {
                    $cond['$and'][] = ['$or' => $or];
                } else {
                    $cond['$or'] = $or;
                }
            } elseif ($class_source == 2) {
                $cond['inside_training.relation_id'] = ['$gt' => 0];
            } elseif ($class_source == 3 && !$project_id) {
                $cond['project_id'] = ['$gt' => 0];
            }

        }

        //是否开启学习提醒 0不开启 1开启
        if (($learn_notice_status = ArrayGet($filters, 'learn_notice_status', -1)) != -1) {
            $cond['learn_notice_status'] = $learn_notice_status;
        }
        //学习提醒类型 0每天 1每周x
        if (($learn_notice_type = ArrayGet($filters, 'learn_notice_type', -1)) != -1) {
            $cond['learn_notice_type'] = $learn_notice_type;
        }
        //设为每周时对应的周几 1-7:周一到周日
        if (($learn_notice_week = ArrayGet($filters, 'learn_notice_week', -1)) != -1) {
            $cond['learn_notice_week'] = $learn_notice_week;
        }
        //提醒的时间段 6-23:6点到23点
        if (($learn_notice_hour = ArrayGet($filters, 'learn_notice_hour', -1)) != -1) {
            $cond['learn_notice_hour'] = $learn_notice_hour;
        }

        //新华三特殊查询
        if (
            (env('APP_ENV') == 'development' && $this->aid == env('H3C_AID', '2005')) ||
            (env('APP_ENV') != 'development' && $this->aid == env('H3C_AID', '5642'))
        ) {
            $this->h3cCondPlanFilters($filters, $cond);
        }

        return $cond;
    }

    //获取查询条件
    protected function getConditionPre($filters, $type = 0, $project_id = 0)
    {
        $cond = [
            'aid' => $this->aid,
            'type' => $type,
//            'project_id' => $project_id,
            '$or' => [
                ['monitor' => ['$elemMatch' => ['id' => $this->eid, 'type' => self::APPLIED_RANGE_EMPLOYEE]]]
            ],
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]]
        ];
        if (is_array($type)) {
            $cond['type'] = ['$in' => $type];
        }
        if ($status = ArrayGet($filters, 'status', '')) $cond['status'] = $status;

        if (($simple_plan = ArrayGet($filters, 'simple_plan', -1)) != -1) {
            $cond['simple_plan'] = $simple_plan;
        }

        $employee_model = new Employee($this->app);
        $employee = $employee_model->view($this->eid, $this->aid, ['groups' => 1, 'roles' => 1]);
        $groups = ArrayGet($employee, 'groups', []);
        if ($groups) $cond['$or'][] = ['monitor' => ['$elemMatch' => ['id' => ['$in' => $groups], 'type' => self::APPLIED_RANGE_GROUP]]];

        if ($project_ids = ArrayGet($filters, 'project_ids', [])) $cond['project_id'] = ['$in' => $project_ids];

        if ($plan_ids = ArrayGet($filters, 'ids', [])) $cond['id'] = ['$in' => $plan_ids];
        if ($ignore_ids = ArrayGet($filters, 'ignore_ids', [])) $cond['id'] = ['$nin' => $ignore_ids];
        if ($keyword = ArrayGet($filters, 'keyword', '')) {
            $or = [
                ['name' => new Regex($keyword, 'im')],
                ['display' => new Regex($keyword, 'im')],
            ];
            if (ArrayGet($cond, '$or')) {
                $cond['$and'] = [
                    ['$or' => $cond['$or']],
                    ['$or' => $or],
                ];
                unset($cond['$or']);
            } else {
                $cond['$or'] = $or;
            }
        }

        $start_time = ArrayGet($filters, 'start_time', '');
        $end_time = ArrayGet($filters, 'end_time', '');
        if ($start_time && $end_time) {
            $cond['start_time'] = [
                '$gte' => Mongodb::getMongoDate($start_time / 1000),
                '$lt' => Mongodb::getMongoDate($end_time / 1000),
            ];
        } else if ($start_time) {
            $cond['start_time'] = ['$gte' => Mongodb::getMongoDate($start_time / 1000)];
        } else if ($end_time) {
            $cond['start_time'] = ['$lt' => Mongodb::getMongoDate($end_time / 1000)];
        }

        $filter = ArrayGet($filters, 'filter', 0);
        switch ($filter) {
            case 1:
                //获取未开始学习计划的数量(已发布,当前时间<开始时间) 外派培训--未开始.
                $cond['is_end'] = 0;
                $cond['status'] = 2;
                if ($start_time) {
                    if ($start_time / 1000 < time()) $cond['start_time']['$gt'] = Mongodb::getMongoDate(time());
                } else {
                    $cond['start_time'] = ['$gt' => Mongodb::getMongoDate(time())];
                }
//                $cond['start_time'] = ['$gt' => Mongodb::getMongoDate(time())];
                break;
            case 2:
                //获取未完成学习计划的数量(已发布,开始时间<当前时间<结束时间,完成率不到100%) 外派培训--进行中.
                $cond['status'] = 2;
                $cond['is_end'] = 0;
                if ($end_time) {
                    if ($end_time / 1000 > time()) $cond['start_time']['$lte'] = Mongodb::getMongoDate(time());
                } else {
                    $cond['start_time'] = ['$lte' => Mongodb::getMongoDate(time())];
                }
//                $cond['start_time'] = ['$lte' => Mongodb::getMongoDate(time())];
                $cond['end_time'] = ['$gt' => Mongodb::getMongoDate(time())];
                if ($type != Constants::LEARNING_TRAINING) $cond['finished_rate'] = ['$lt' => 100];
                break;
            case 3:
                //获取已完成学习计划的数量(已发布,开始时间<当前时间<结束时间,完成率100%).
                $cond['status'] = 2;
                $cond['is_end'] = 0;
                if ($type == Constants::LEARNING_TRAINING) {
                    $cond['end_time'] = ['$lt' => Mongodb::getMongoDate(time())];
                } else {
                    if ($end_time) {
                        if ($end_time / 1000 > time()) $cond['start_time']['$lt'] = Mongodb::getMongoDate(time());
                    } else {
                        $cond['start_time'] = ['$lt' => Mongodb::getMongoDate(time())];
                    }
//                    $cond['start_time'] = ['$lt' => $time];
                    $cond['finished_rate'] = 100;
                }
                break;
            case 4:
                //获取已延期学习计划的数量(已发布,当前时间>结束时间,完成率<100%)  外派培训--已延期.
                $cond['status'] = 2;
                $cond['is_end'] = 0;
                $cond['end_time'] = ['$lt' => Mongodb::getMongoDate(time())];
                if ($type == Constants::LEARNING_TRAINING) {
                    $cond['is_end'] = self::NOT_END;
                } else {
                    $cond['finished_rate'] = ['$lt' => 100];
                }
                break;
            case 5:
                //获取未发布学习计划的数量  外派培训--草稿
                $cond['learning_status'] = self::LEARNING_UNPUBLISH;
                $cond['status'] = static::ENABLED;
                break;
            case 6:
                //已结束  外派培训--已结束
                $cond['is_end'] = 1;
                break;
            case 7:
                //待提交  智能班级-需要学员确认
                $cond['learner_confirm'] = 1;
                $cond['learning_status'] = self::LEARNING_UNSUBMIT;
            case 8:
                //已发布  已发布,未手动结束
                $cond['status'] = self::STATUS_PUBLISHED;
                $cond['is_end'] = self::NOT_END;
                break;
        }
        $org_id = ArrayGet($filters, 'org_id', 0);
        $org_children_included = ArrayGet($filters, 'org_children_included', 0);
        if ($org_id) {
            if ($org_children_included) {
                $dept_model = new Department($this->app);
                $depts = $dept_model->getAllChildren([$org_id], ['id' => 1], true);
                $dept_ids = array_column($depts, 'id');
                $cond['organization.id'] = ['$in' => $dept_ids];
            } else {
                $cond['organization.id'] = $org_id;
            }
        }
        if ($dept_ids = ArrayGet($filters, 'dept_ids', [])) {
            $dept_model = new Department($this->app);
            $depts = $dept_model->getAllChildren($dept_ids, ['id' => 1], true);
            $dept_ids = array_column($depts, 'id');
            $cond['organization.id'] = ['$in' => $dept_ids];
        }
        $publish_start_time = ArrayGet($filters, 'publish_start_time', '');
        $publish_end_time = ArrayGet($filters, 'publish_end_time', '');
        if ($publish_start_time && $publish_end_time) {
            $cond['publish_time'] = [
                '$gte' => Mongodb::getMongoDate($publish_start_time / 1000),
                '$lt' => Mongodb::getMongoDate($publish_end_time / 1000),
            ];
        } else if ($publish_start_time) {
            $cond['publish_time'] = ['$gte' => Mongodb::getMongoDate($publish_start_time / 1000)];
        } else if ($publish_end_time) {
            $cond['publish_time'] = ['$lt' => Mongodb::getMongoDate($publish_end_time / 1000)];
        }
        if (($cag_id = ArrayGet($filters, 'category_id', -1)) != -1) {
            if (is_array($cag_id)) {
                $cond['category.id'] = ['$in' => $cag_id];
            } else {
                $cond['category.id'] = $cag_id;
            }

        }

        // 班级来源 1班级 2计划任务管理
        if ($class_source = ArrayGet($filters, 'class_source', 0)) {
            if ($class_source == 1) {
                $or = [
                    ['inside_training' => ['$exists' => 0]],
                    ['inside_training.relation_id' => 0],
                ];
                if (ArrayGet($cond, '$or')) {
                    $cond['$and'] = [
                        ['$or' => $cond['$or']],
                        ['$or' => $or],
                    ];
                    unset($cond['$or']);
                } elseif (ArrayGet($cond, '$and')) {
                    $cond['$and'][] = ['$or' => $or];
                } else {
                    $cond['$or'] = $or;
                }
            } elseif ($class_source == 2) {
                $cond['inside_training.relation_id'] = ['$gt' => 0];
            } elseif ($class_source == 3 && !$project_id) {
                $cond['project_id'] = ['$gt' => 0];
            }

        }

        //是否开启学习提醒 0不开启 1开启
        if (($learn_notice_status = ArrayGet($filters, 'learn_notice_status', -1)) != -1) {
            $cond['learn_notice_status'] = $learn_notice_status;
        }
        //学习提醒类型 0每天 1每周x
        if (($learn_notice_type = ArrayGet($filters, 'learn_notice_type', -1)) != -1) {
            $cond['learn_notice_type'] = $learn_notice_type;
        }
        //设为每周时对应的周几 1-7:周一到周日
        if (($learn_notice_week = ArrayGet($filters, 'learn_notice_week', -1)) != -1) {
            $cond['learn_notice_week'] = $learn_notice_week;
        }
        //提醒的时间段 6-23:6点到23点
        if (($learn_notice_hour = ArrayGet($filters, 'learn_notice_hour', -1)) != -1) {
            $cond['learn_notice_hour'] = $learn_notice_hour;
        }

        //新华三特殊查询
        if (
            (env('APP_ENV') == 'development' && $this->aid == env('H3C_AID', '2005')) ||
            (env('APP_ENV') != 'development' && $this->aid == env('H3C_AID', '5642'))
        ) {
            $this->h3cCondPlanFilters($filters, $cond);
        }

        return $cond;
    }

    private function h3cCondPlanFilters($filters, &$cond){
        if ($h3c_filed_businessType = ArrayGet($filters, 'h3c_filed_businessType', '')) {
            $cond['h3c_filed_businessType'] = new Regex($h3c_filed_businessType, 'im');
        }
        if ($h3c_filed_learningDevType = ArrayGet($filters, 'h3c_filed_learningDevType', '')) {
            $cond['h3c_filed_learningDevType'] = new Regex($h3c_filed_learningDevType, 'im');
        }
        if ($h3c_filed_projectName = ArrayGet($filters, 'h3c_filed_projectName', '')) {
            $cond['h3c_filed_projectName'] = new Regex($h3c_filed_projectName, 'im');
        }
        if ($h3c_filed_trainingState = ArrayGet($filters, 'h3c_filed_trainingState', '')) {
            $cond['h3c_filed_trainingState'] = new Regex($h3c_filed_trainingState, 'im');
        }
    }

    /**
     * 获取过滤条件下学习计划的数量.
     *
     * @param array $filters .
     * @param int $type .
     * @param int $project_id .
     * @return boolean|int
     */
    public function getTotal($filters, $type, $project_id = 0)
    {
        $cond = $this->getCondition($filters, $type, $project_id);

        return $this->getMongoMasterConnection()->count(Constants::COLL_LEARNING_PLAN, $cond);
    }

    /**
     * 无功能权限-工作台-智能班级.
     *
     * @param array $filters .
     * @param int $type .
     * @param int $project_id .
     * @return boolean|int
     */
    public function getTotalPre($filters, $type, $project_id = 0)
    {
        $cond = $this->getConditionPre($filters, $type, $project_id);

        return $this->getMongoMasterConnection()->count(Constants::COLL_LEARNING_PLAN, $cond);
    }

    /**
     * 更新学习计划.
     *
     * @param int $learning_id
     * @param int $type
     * @param array $record
     * @return boolean|int
     */
    public function update($learning_id, $record, $type,$is_record=1)
    {
        $plan = $this->isExist($learning_id);

        if (!is_array($record)) $record = $record->toArray();
        $ended = $record['end_time'];
        $record['start_time'] = Mongodb::getMongoDate($record['start_time'] / 1000);
        $record['end_time'] = Mongodb::getMongoDate($record['end_time'] / 1000);
        $new_data['learning_status'] = self::LEARNING_UNPUBLISH;
        if ($record['learner_confirm']) $new_data['learning_status'] = self::LEARNING_UNSUBMIT;
        if (ArrayGet($record, 'learner_password', '')) $record['learner_password'] = strtolower($record['learner_password']);
        $new_data = array_merge($record, [
            'id' => $learning_id,
            'last_eid' => $this->eid,
            'updated' => Mongodb::getMongoDate()
        ]);

        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'id' => (int)$learning_id,
            'type' => (int)$type,
            'aid' => $this->aid,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]]
        ], [
            '$set' => $new_data
        ]);
        if ($result) {
            $cache_key = $this->getRedisId($learning_id);
            $this->getCacheInstance()->delete($cache_key);
            $this->afterUpdateLearning($learning_id, $type, $plan, $new_data, $ended);
        }

        return $result ? $new_data['id'] : false;
    }

    public function updateNotifyTime($learning_id, $type, $update_info)
    {
        $cond = [
            'aid' => $this->aid,
            'status' => 1,
            'plan_type' => $type
        ];
        switch ($type) {
            case Constants::LEARNING_CLASS:
                $cond['learning_id'] = $learning_id;
                $cond['type'] = 8;
                break;
            case Constants::LEARNING_TRAINING:
                $cond['object_id'] = $learning_id;
                $cond['type'] = 13;
                break;
            default:
                $cond['learning_id'] = $learning_id;
                $cond['type'] = 3;
        }
        $new_data = [
            'start' => (string)$update_info['start_time'],
            'end' => (string)$update_info['end_time'],
            'title' => $update_info['learning_name'],
            'updated' => Mongodb::getMongoDate(),
        ];


        $this->getMongoMasterConnection()->update(Constants::COLL_NOTIFICATION, $cond, [
            '$set' => $new_data
        ]);
    }

    /**
     * 更新学习计划基本信息.
     *
     * @param int $learning_id .
     * @param array $record .
     * @param int $type .
     * @param [] $plan
     * @return boolean
     */
    public function updateBasicInfo($learning_id, $record, $type = Constants::LEARNING_CLASS, $plan = [])
    {
        $source_id = $record['source_id'];
        if (isset($record['shift_range']) && ArrayGet($record['shift_range'], 'range', [])) {
            foreach ($record['shift_range']['range'] as $k => $val) {
                $record['shift_range']['range'][$k] = $val->toArray();
            }
        }
        if ($type == Constants::LEARNING_CLASS) $record['is_applicant'] = ArrayGet($record['app_setting'], 'is_enroll', 0);

        $record['start_time'] = Mongodb::getMongoDate($record['start_time'] / 1000);
        $record['end_time'] = Mongodb::getMongoDate($record['end_time'] / 1000);
        if (ArrayGet($record, 'learner_password', '')) $record['learner_password'] = strtolower($record['learner_password']);
        $new_data = array_merge($record, [
            'last_eid' => $this->eid,
            'updated' => Mongodb::getMongoDate()
        ]);
        if (isset($new_data['status'])) unset($new_data['status']);

        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'id' => $learning_id,
            'type' => $type,
            'aid' => $this->aid,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]]
        ], [
            '$set' => $new_data
        ]);
        if ($result) {
            if ($type == Constants::LEARNING_CLASS) {
                //班级标签
                $knowledgePoint = new KnowledgePoint($this->app);
                $knowledgePoint->calUsesByName($new_data['id'], $new_data['tags'], KnowledgePoint::POINT_CLASS);

                $training_model = new TrainingPlan($this->app);
                $training_model->changeOpStatus($source_id, $learning_id, $type);
            }
            $cache_key = $this->getRedisId($learning_id);
            $this->getCacheInstance()->delete($cache_key);
            $this->afterUpdateLearning($learning_id, $type, $plan, $new_data);

            // 更新内训班级信息
            if ($type == Constants::LEARNING_CLASS && $new_data['inside_training']['relation_id']) {
                $relation_model = new InsideTrainingDeptTaskRelation($this->app);
                $relation_model->updateClassInfo($learning_id, $new_data['inside_training'], false);
            }

            //编辑班级，如果有培训需求关联IDS，调用张阳阳接口
            $modelNeedsQuestionLinkedClasses = new NeedsQuestionLinkedClasses($this->app);
            $modelNeedsQuestionLinkedClasses->saveByClass($learning_id, $new_data['training_need_question_ids']);
        }


        //旺旺编辑智能班级，记录数据。
        if ($this->aid == env('WANGWANG_DATA_BACK_AID', '4702') && $type == Constants::LEARNING_CLASS) {
            $update_data = $this->getMongoMasterConnection()->fetchRow(Constants::COLL_LEARNING_PLAN, [
                'id' => $learning_id,
                'type' => $type,
                'aid' => $this->aid,
                'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]]
            ]);
            $this->addLearningDataLog($update_data, self::LOG_EDIT, 2);
        }

        return $result ?: false;
    }

    protected function addLearningDataLog($new_data, $log_name, $edit_type)
    {

        $modelEmployee = new Employee($this->app);
        $employeeInfo = $modelEmployee->getById($this->eid, $this->aid, ['id' => 1, 'display' => 1, 'no' => 1]);
        $last_e_info = [
            'last_eid' => $this->eid, //操作人
            'last_name' => ArrayGet($employeeInfo, 'display', ''), //操作人
            'last_eno' => ArrayGet($employeeInfo, 'no', ''), //操作人员工工号
            'log_name' => $log_name, //操作名称
            'created' => (string)Mongodb::getMongoDate(),//数据创建时间
            'updated' => (string)Mongodb::getMongoDate()//数据创建时间
        ];

        $monitor_ids = array_column($new_data['monitor'], 'id');
        $monitor_fields = [
            'id' => 1,
            'no' => 1,
        ];
        $monitor_result = $modelEmployee->getByIds($monitor_ids, $monitor_fields);
        $monitor_infos = array_column($monitor_result, 'no', 'id');
        foreach ($new_data['monitor'] as $k => $v) {
            $new_data['monitor'][$k]['monitor_eno'] = $monitor_infos[$v['id']];
        }

        $edit_data = [
            'id' => Sequence::getSeparateId('wangwang_learning_plan_data_log_' . $this->aid, $this->aid),
            'aid' => $this->aid,
            'learning_id' => $new_data['id'], //班级ID
            'learning_no' => $new_data['no'], //班级编码
            'class_type' => $new_data['class_type'], //项目类型 0-线上培训 1-线下培训 2-O2O混合培训
            'category' => $new_data['category'], //班级分类
            'monitor' => $new_data['monitor'], //管理员
            'petition_code' => $new_data['petition_code'], //班级签呈
            'costings' => $new_data['costings'], //班级费用
            'updated' => $new_data['updated'], //班级最后修改时间
            'project_id' => $new_data['project_id'] ? $new_data['project_id'] : 0, //多班次项目ID
        ];

        if ($edit_type == 1) {
            $edit_data['handle_type'] = 1; //新增班级
        } elseif ($edit_type == 2) {
            $edit_data['handle_type'] = 2; //编辑班级
        } elseif ($edit_type == 3) {
            $edit_data['handle_type'] = 5; //删除班级
        }

        $bind = array_merge($edit_data, $last_e_info);

        $this->getMongoMasterConnection()->insert('wangwang_learning_plan_data_log_' . $this->aid, $bind);
    }

    protected function afterUpdateLearning($learning_id, $type, $plan, $new_data, $ended = '')
    {
        //操作日志
        if (in_array($type, [Constants::LEARNING_CLASS, Constants::LEARNING_PLAN])) {
            $module = $type == Constants::LEARNING_CLASS ? 'class' : 'plan';
            $this->setNewData($new_data)
                ->setOldData($plan)
                ->setOp(self::OP_UPDATE)
                ->describe($learning_id, $new_data['name'], '基本信息')
                ->setOpModule($module)
                ->saveOpLog();
        }

        $sta_offline_teaching = [];
        if ((string)$new_data['start_time'] != $plan['start_time']) {
            $sta_offline_teaching['learning_start_time'] = $new_data['start_time'];
        }
        if ((string)$new_data['end_time'] != $plan['end_time']) {
            $sta_offline_teaching['learning_end_time'] = $new_data['end_time'];
        }
        if ((string)$new_data['name'] != $plan['name']) {
            $sta_offline_teaching['learning_name'] = $new_data['name'];
        }
        $unlock_con = ArrayGet($plan, 'unlock_con', 0);

        $learner_model = new Learner($this->app);
        $course_rate = ArrayGet($plan, 'course_rate', 1);
        if ($course_rate != $new_data['course_rate']) {
//            $learner_model->learnerRateQueue($learning_id, $type);
            $learner_rate_model = new LearnerRate($this->app);
            $learner_rate_model->learningInsertLearnersRateQueue($learning_id, $type, [], 1);
        }

        if ($new_data['unlock_con'] == self::UNLOCK_STAGE_TIME_TASK_NOT_IN_ORDER && $type == Constants::LEARNING_CLASS && $unlock_con != self::UNLOCK_STAGE_TIME_TASK_NOT_IN_ORDER) {
            $task_model = new LearningTask($this->app);
            $task_model->updateGroupStartTime($learning_id, $type, $new_data['start_time']);
        }

        $history_app_setting = ArrayGet($plan, 'app_setting', []);
        $history_exam = ArrayGet($history_app_setting, 'is_exam', 0);
        $history_assess = ArrayGet($history_app_setting, 'is_assess', 0);
        $app_setting = ArrayGet($new_data, 'app_setting', []);
        $is_exam = ArrayGet($app_setting, 'is_exam', 0);
        $is_assess = ArrayGet($app_setting, 'is_assess', 0);
        $group_model = new LearningGroup($this->app);
        if ($is_exam != $history_exam) {
            $group_model->updateGroupEnabled($learning_id, LearningGroup::GROUP_TYPE_EXAM, $is_exam);
        }
        if ($is_assess != $history_assess) {
            $group_model->updateGroupEnabled($learning_id, LearningGroup::GROUP_TYPE_ASSESSMENT, $is_assess);
        }

        if (in_array($type, [Constants::LEARNING_CLASS, Constants::LEARNING_PLAN, Constants::LEARNING_TRAINING])) {
            //自动颁发证书/结业
            $graduation = 0;
            $certificate = 0;
            if (($plan['auto_graduation'] != $new_data['auto_graduation']) && $new_data['auto_graduation'] == 1) $graduation = 1;
            if (($plan['auto_certificate'] != $new_data['auto_certificate']) && $new_data['auto_certificate'] == 1) $certificate = 1;
            if ($graduation || $certificate) {
                $learner_rate_model = new LearnerRate($this->app);
                $learner_rate_model->autoWindingOrCertify($learning_id, $graduation, $certificate, $type);
            }
        }

        //更新learner表的时间
        $new_data['enroll_setting'] = ArrayGet($new_data, 'enroll_setting', []);
        $update_info = [
            'start_time' => $new_data['start_time'],
            'end_time' => $new_data['end_time'],
            'learning_name' => $new_data['name'],
            'learning_cover' => $new_data['cover'],
            'monitor' => $new_data['monitor'],
            'is_set_top' => $new_data['is_set_top'],
            'tracker' => ArrayGet($new_data, 'tracker', []),
            'need_enroll' => ArrayGet($new_data, 'is_applicant', 0),
            'need_examine' => ArrayGet($new_data, 'is_examine', 0),
            'enroll_started' => ArrayGet($new_data['enroll_setting'], 'enroll_started', ''),
            'enroll_ended' => ArrayGet($new_data['enroll_setting'], 'enroll_ended', ''),
            'enroll_range' => ArrayGet($new_data, 'applied_range', []),
            'enroll_extra_con' => ArrayGet($new_data, 'enroll_extra_con', []),
            'overtime_learn' => ArrayGet($new_data, 'settings.overtime_learn', 1),
            'enroll_auth' => ArrayGet($new_data, 'applicant_auth', 0)
        ];
        if ($status = ArrayGet($new_data, 'status', 0)) $update_info['learning_status'] = $new_data['status'];
        $learner_model->updateLearningInfo($learning_id, $update_info, $type);
        $this->updateNotifyTime($learning_id, $type, $update_info);

        if ($type == Constants::LEARNING_CLASS && $sta_offline_teaching) {
            $sta_offline_teaching_model = new StatisticsOfflineTeaching($this->app);
            $sta_offline_teaching_model->updateLearningInfo($learning_id, $sta_offline_teaching);
        }

        if ($plan['name'] != $new_data['name']) {
            $task_model = new Task($this->app);
            $task_model->updateSourceTitle($learning_id, $type, ['source_title' => $new_data['name']]);
        }
        $old_center_id = ArrayGet($plan, 'training_center.id', 0);
        $center_id = ArrayGet($new_data, 'training_center.id', 0);
        if ($old_center_id != $center_id) {
            $this->replaceRelationTrainingCenter($learning_id, $type, $old_center_id, $center_id);
        }
    }

    /**
     * 删除学习计划.
     *
     * @param int $learning_id .
     * @param int $type .
     * @param string $display .
     * @return boolean
     */
    public function remove($learning_id, $type)
    {
        $plan = $this->isExist($learning_id, ['project_id' => 1, 'job_id' => 1, 'status' => 1, 'name' => 1, 'eid' => 1, 'organization' => 1, 'monitor' => 1, 'uid' => 1, 'inside_training' => 1, 'training_need_question_ids' => 1]);

        //旺旺删除时，获取删除前的数据
        if ($this->aid == env('WANGWANG_DATA_BACK_AID', '4702') && $type == Constants::LEARNING_CLASS) {
            $delete_plan = $this->isExist($learning_id, ['id' => 1, 'no' => 1, 'class_type' => 1, 'category' => 1, 'monitor' => 1, 'petition_code' => 1, 'costings' => 1, 'updated' => 1, 'project_id' => 1]);
            $this->addLearningDataLog($delete_plan, self::LOG_DELETE, 3);
        }

        $project_id = ArrayGet($plan, 'project_id', 0);
        $job_id = ArrayGet($plan, 'job_id', 0);
        $del = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'type' => $type,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
            'id' => $learning_id
        ], [
            '$set' => [
                'last_eid' => $this->eid,
                'status' => static::DISABLED,
                'display' => isset($display) ? $display : '',
                'updated' => Mongodb::getMongoDate()
            ]
        ]);

        if ($del) {
            $learner = new Learner($this->app);
            $learner->delLearner($learning_id, $type, [], 0, 1, $project_id);

            $task_model = new LearningTask($this->app);
            $task_model->delGroup($learning_id, 0, $type);

            $class_sign = new ClassSign($this->app);
            $class_sign->remove($learning_id);

            $interaction_model = new LearningInteraction($this->app);
            $interaction_model->remove($learning_id);

            $cache_key = $this->getRedisId($learning_id);
            $this->getCacheInstance()->delete($cache_key);

            //附加分倒计时
            if ($type == Constants::LEARNING_PLAN) {
                $bonusPoints = new LearningBonusPoints($this->app);
                $bonusPoints->remove($job_id);
            }

            if ($type == Constants::LEARNING_CLASS) {
                $autonomy_detail_model = new AutonomyTaskDetail($this->app);
                $autonomy_detail_model->autonomyDelTaskQueue($learning_id, AutonomyTaskDetail::TASK_CLASS);
                $autonomy_detail_model->delLearner($learning_id, [], $type);

                $sta_offline_model = new StatisticsOfflineTeaching($this->app);
                $sta_offline_model->removeLearning($learning_id, $type);
            }
            $this->removeRelationTrainingCenter($learning_id, $type, ArrayGet($plan, 'training_center.id', 0));

            //删除日历消息流
            $this->deleteNews($learning_id, $type);

            //操作日志
            if (in_array($type, [Constants::LEARNING_CLASS, Constants::LEARNING_PLAN])) {
                $module = $type == Constants::LEARNING_CLASS ? 'class' : 'plan';
                $this->setNewData([
                    'status' => $plan['status'],
                    'last_eid' => $this->eid
                ])
                    ->setOldData($plan)
                    ->setOp(self::OP_DELETE)
                    ->describe($learning_id, $plan['name'], '删除')
                    ->setOpModule($module)
                    ->saveOpLog();
            }

            //删除班级人员pk分组
            $this->delPkGroupByClass($learning_id, 1);

            //基于面授课id,删除面授课的评估统计 surn
            $assess_model = new Assessment($this->app);
            $assess_model->removeStatDetailByOtid([], $learning_id, $type);

            //删除内训班级关联状态
            $relation_id = ArrayGet($plan, 'inside_training.relation_id', 0);
            if ($relation_id) {
                $relation_model = new InsideTrainingDeptTaskRelation($this->app);
                $relation_model->updateClassStatus($learning_id, $relation_id);
            }

            //删除班级，删除培训需求独立模块数据,调张阳阳接口
            if ($training_need_question_ids = ArrayGet($plan, 'training_need_question_ids', [])) {
                $modelNeedsQuestionLinkedClasses = new  NeedsQuestionLinkedClasses($this->app);
                $modelNeedsQuestionLinkedClasses->deleteByclassId($learning_id);
            }
        }

        return $del;
    }

    /**
     * 复制学习计划.
     *
     * @param int $learning_id .
     * @param int $type .
     * @param string $name .
     * @param array $filters .
     * @return boolean
     */
    public function copy($learning_id, $name, $type, $filters)
    {
        $learnPlan = $this->isExist($learning_id);
        if (!$learnPlan) return false;

        $employee_model = new Employee($this->app);
        $employee = $employee_model->view($this->eid);
        $newData = [
            'id' => Sequence::getSeparateId('learning_plan', $this->aid),
            'start_time' => ArrayGet($learnPlan, 'start_time') ? Mongodb::getMongoDate($learnPlan['start_time'] / 1000) : '',
            'end_time' => ArrayGet($learnPlan, 'end_time') ? Mongodb::getMongoDate($learnPlan['end_time'] / 1000) : '',
            'display' => ArrayGet($employee, 'display', ''),
            'name' => $name,
            'type' => $type,
            'is_copy' => 1,
            'copy_id' => $learning_id,
            'aid' => $this->aid,
            'uid' => $this->eid,
            'no' => $this->getNo($type),
            'finished_rate' => 0,
            'is_end' => 0,
            'status' => static::ENABLED,
            'source' => 0,
            'source_id' => 0,
            'publish_time' => '',
            'budget' => 0,
            'budget_type' => 0,
            'budget_id' => 0,
            'budget_detail_id' => 0,
            'costings' => 0,
            'actual_const' => 0,
            'actual_desc' => '',
            'wework_chatid' => '',
            'approval_status' => 0,
            'created' => Mongodb::getMongoDate(),
            'updated' => Mongodb::getMongoDate()
        ];
        $newData = array_merge($learnPlan, $newData);
        $result = $this->getMongoMasterConnection()->insert(Constants::COLL_LEARNING_PLAN, $newData);

        //旺旺新增智能班级，记录数据。
        if ($this->aid == env('WANGWANG_DATA_BACK_AID', '4702') && $type == Constants::LEARNING_CLASS) {
            $this->addLearningDataLog($newData, self::LOG_CREATE, 1);
        }

        if ($result) {
            $project_id = ArrayGet($learnPlan, 'project_id', 0);
            $this->createPkGroup($newData['id'], ['name' => '未分组', 'project_id' => 0], 0);

            if ($filters['is_interaction']) {
                //复制互动
                $interaction_model = new LearningInteraction($this->app);
                $interaction_model->copy($learning_id, $newData['id'], $type);
            }

            if ($filters['is_task']) {
                //复制任务分组及任务
                $group = new LearningTask($this->app);
                $group->copy($learning_id, $type, $newData['id'], $type, 0, $learnPlan);
            }

            if ($filters['is_task'] && $filters['is_sign'] && $type == Constants::LEARNING_CLASS) {
                //复制签到
                $modelClassSign = new ClassSign($this->app);
                $modelClassSign->copy($learning_id, $newData['id']);
            }

            if ($filters['is_learner'] && $type == Constants::LEARNING_CLASS) {
                //学员复制完后，复制学员分组，学员是异步，但除了learning_id其他信息基本没变，所以可以先复制分组信息。
                $this->copyPkGroups($learning_id, $newData['id']);

                //复制学员
                $modelLearner = new Learner($this->app);
                $learner_result = $modelLearner->getListByIds([$learning_id], $type, null, ['eid' => 1, 'group_id' => 1, 'is_elective' => 1], ['is_examine' => 1]);
                $learners = $learner_result['result'];

                $elective_learners_false = []; //必修人
                $elective_learners_true = []; //选修人
                foreach ($learners as $learner_k => $learner_v) {
                    if (!$learner_v['is_elective']) {
                        $elective_learners_false[] = $learner_v['eid'];
                    } elseif ($learner_v['is_elective'] == 1) {
                        $elective_learners_true[] = $learner_v['eid'];
                    }
                }

                if ($elective_learners_false) {
                    $result = $modelLearner->addLearnersQueue($newData['id'], [], $elective_learners_false, $type, 1, [
                        'is_elective' => 0,
                        'project_id' => $project_id,
                        'copy_pk_group' => 1,
                        'old_learning_id' => $learning_id
                    ]);
                }

                if ($elective_learners_true) {
                    $result = $modelLearner->addLearnersQueue($newData['id'], [], $elective_learners_true, $type, 1, [
                        'is_elective' => 1,
                        'project_id' => $project_id,
                        'copy_pk_group' => 1,
                        'old_learning_id' => $learning_id
                    ]);
                }

            }

            //创建班级默认海报数据
            if ($type == Constants::LEARNING_CLASS || $type == Constants::LEARNING_PLAN) {
                $billModel = new LearningBill($this->app);
                $billModel->createBill($newData['id'], $type);
            }
        }

        return $result ? $newData['id'] : 0;
    }

    /**
     * 查看学习计划是否存在.
     *
     * @param int $learning_id .
     * @param array $fields .
     * @param int $is_format_time .
     * @param int $aid .
     * @return array
     */
    public function isExist($learning_id, $fields = [], $is_format_time = 0, $aid = 0)
    {
        $aid = $aid ?: $this->aid;
        $cache_key = $this->getRedisId($learning_id, $aid);
        $cache = $this->getCacheInstance();
        $result = $cache->get($cache_key);
        if ($result) {
            $result = json_decode($result, true);
        } else {
            $result = $this->getMongoMasterConnection()->fetchRow(Constants::COLL_LEARNING_PLAN, [
                'aid' => $aid,
                'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
                'id' => (int)$learning_id
            ]);
            if ($result) {
                Utils::convertMongoDateToTimestamp($result);
                $cache->set($cache_key, $result);
            }
        }
        if ($result) {
            if ($result['type'] == Constants::LEARNING_CLASS) {
                $result['wework_chatid'] = $result['wework_chatid'] ?? '';//空字符串代表未创建群聊,不为空则是群聊ID
                $result['examine_notify']['verify_notice'] = $result['examine_notify']['verify_notice'] ?? 0;
                $result['examine_notify']['verify_result_notice'] = $result['examine_notify']['verify_result_notice'] ?? 0;
                //转正设置 -1不展示此设置项 0未开启 1已开启
                $result['learner_transfer'] = $this->getLearnerTransferSetting($result['learner_transfer'], $aid);

            }

            $result = Utils::handleFieldsReturn($result, $fields);

            if ($is_format_time) {
                if ($start_time = ArrayGet($result, 'start_time', '')) {
                    $result['start_time'] = date('m-d', $start_time);
                }
                if ($end_time = ArrayGet($result, 'end_time', '')) {
                    $result['end_time'] = date('m-d', $end_time);
                }
            }
        }

        return $result ?: [];
    }

    /**
     * 查看学习计划详情.
     *
     * @param int $learning_id .
     * @param int $type .
     * @return array
     */
    public function getById($learning_id, $type)
    {
        $result = $this->isExist($learning_id, [
            'created' => 0,
            'updated' => 0
        ]);
        if (!$result) return false;

        if ($type != 4) {
            $employee_group_model = new EmployeeGroup($this->app);
            $result['add_group_labels'] = [];
            if ($add_groups = ArrayGet($result, 'add_groups')) {
                $groups = $employee_group_model->getNameList($add_groups);
                foreach ($groups as $k => $val) {
                    $result['add_group_labels'][] = ['id' => $k, 'name' => $val];
                }
            }

            $result['del_group_labels'] = [];
            if ($del_groups = ArrayGet($result, 'del_groups')) {
                $groups = $employee_group_model->getNameList($del_groups);
                foreach ($groups as $k => $val) {
                    $result['del_group_labels'][] = ['id' => $k, 'name' => $val];
                }
            }
        }

        $modelLearner = new Learner($this->app);
        $filters_pass_review = [
            'learning_id' => $learning_id,
            'is_examine' => ['$in' => [Learner::EXAMINE_NOT_REVIEW, Learner::EXAMINE_REVIEW_PASSES]]
        ];
        $result['review_num'] = $modelLearner->getEidsCountByFilters($filters_pass_review);

        $filters_pass = [
            'learning_id' => $learning_id,
            'is_examine' => Learner::EXAMINE_REVIEW_PASSES
        ];
        $result['register_num'] = $modelLearner->getEidsCountByFilters($filters_pass);

        return $result;
    }

    //获取以id为键的学员信息
    public function getEmployeeInfo($eids)
    {
        $employee = new Employee($this->app);
        $users = $employee->filters(['eids' => $eids], null, null, ['id' => 1, 'display' => 1, 'avatar' => 1]);
        $result = [];
        if ($users) {
            foreach ($users as $k => $val) {
                $result[$val['id']] = $val;
            }
        }

        return $result;
    }

    /**
     * 更新学习进度.
     *
     * @param array $plan .
     * @return boolean
     */
    public function updateRate($plan, $aid = 0)
    {
        if (!$plan) return false;

        foreach ($plan as $k => $val) {
            $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
                'aid' => $aid ?: $this->aid,
                'id' => (int)$val['learning_id'],
                'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]]
            ], [
                '$set' => [
                    'finished_rate' => $val['rate']
                ]
            ]);
            if ($result) {
                $cache_key = $this->getRedisId($val['learning_id']);
                $this->getCacheInstance()->delete($cache_key);
            }
        }
    }

    /**
     * 更新学习进度.
     *
     * @param array $plan .
     * @return boolean
     */
    public function updateFinishedRate($learning_id, $type, $rate)
    {
        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'id' => $learning_id,
            'type' => $type,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]]
        ], [
            '$set' => [
                'finished_rate' => $rate
            ]
        ]);
        if ($result) {
            $cache_key = $this->getRedisId($learning_id);
            $this->getCacheInstance()->delete($cache_key);
        }

        return $result ?: false;
    }

    /**
     * pc端在线学习获取报名班级列表.
     *
     * @param string $keyword 班级/计划的名称
     * @param int $filter 0-全部 1-未报名 2-审核中
     * @param null|\Key\Records\Pagination $pagination
     * @return array
     */
    public function applicantList($keyword, $filter = 0, $pg = null, &$total)
    {
        if (!$pg) {
            $pg = new \Key\Records\Pagination();
            $pg->setPage(0);
            $pg->setItemsPerPage(0);
        }

        $db = $this->getMongoMasterConnection();
        $cond = $this->applicationPcCon($keyword, $filter);
        $learner_model = new Learner($this->app);
        $res = $learner_model->getLearningIds(Constants::LEARNING_CLASS, [Learner::EXAMINE_NOT_REVIEW, Learner::EXAMINE_REVIEW_PASSES, Learner::EXAMINE_REVIEW_NOT_PASS], 0, [], 0, [], null);
        $plan_ids = ArrayGet($res, 'plan_ids', []);
        $plan_examines = ArrayGet($res, 'plan_examine', []);

        $commonFilters = $this->commonFilters;
        //$enroll_type 0全部 1报名中-当前时间在报名时间范围内 2报名结束-当前时间在报名时间范围外
        $enroll_type = ArrayGet($commonFilters, 'enroll_type', 0);
        $time = time() * 1000;
        if (in_array($enroll_type, [1, 2])) {
            if ($enroll_type == 1) {
                $enroll_or = [
                    ['enroll_setting.custom_enroll_time' => 0, 'start_time' => ['$gte' => Mongodb::getMongoDate()]],
                    ['enroll_setting.custom_enroll_time' => 1, 'enroll_setting.enroll_ended' => ['$gte' => (string)$time]]
                ];
            } else {
                $enroll_or = [
                    ['enroll_setting.custom_enroll_time' => 0, 'start_time' => ['$lte' => Mongodb::getMongoDate()]],
                    ['enroll_setting.custom_enroll_time' => 1, 'enroll_setting.enroll_ended' => ['$lte' => (string)$time]]
                ];
            }

            $cond['$or'] = $enroll_or;
        }
        $total = $db->count(Constants::COLL_LEARNING_PLAN, $cond);
        $result = $db->fetchAll(Constants::COLL_LEARNING_PLAN, $cond, $pg->getOffset(), $pg->getItemsPerPage(), [
            'start_time' => -1
        ], [
            'id' => 1,
            'name' => 1,
            'display' => 1,
            'start_time' => 1,
            'end_time' => 1,
            'project_id' => 1,
            'enroll_setting' => 1,
            'allow_enroll_num' => 1,
            'official_num' => 1,
            'cover' => 1
        ]);

        //is_review 0-未报名 1-待审核 2-审核通过 3-审核不通过
        if ($result) {
            foreach ($result as $k => $val) {
                //enroll_end_status 1报名结束 0报名未结束
                $result[$k]['enroll_end_status'] = 0;
                if (!$val['enroll_setting']['custom_enroll_time']) {
                    if ((string)$val['start_time'] <= time() * 1000) {
                        $result[$k]['enroll_end_status'] = 1;
                    }
                } else {
                    if ($val['enroll_setting']['enroll_ended'] <= time() * 1000) {
                        $result[$k]['enroll_end_status'] = 1;
                    }
                }

                $filters_pass_review = [
                    'learning_id' => $val['id'],
                    'is_examine' => ['$in' => [Learner::EXAMINE_NOT_REVIEW, Learner::EXAMINE_REVIEW_PASSES]]
                ];
                $result[$k]['review_num'] = $learner_model->getEidsCountByFilters($filters_pass_review);

                $filters_pass = [
                    'learning_id' => $val['id'],
                    'is_examine' => Learner::EXAMINE_REVIEW_PASSES
                ];
                $result[$k]['register_num'] = $learner_model->getEidsCountByFilters($filters_pass);

                switch ($filter) {
                    case 1://未报名
                        $result[$k]['is_review'] = 0;//页面展示-马上报名
                        break;
                    case 2://已报名
                        $result[$k]['is_review'] = 1;//页面展示-报名详情
                        if (isset($plan_examines[$val['id']]['is_examine']) && $plan_examines[$val['id']]['is_examine'] == 1) {
                            $result[$k]['is_review'] = 2; //审核通过 展示进入项目
                        }
                        if (isset($plan_examines[$val['id']]['is_examine']) && $plan_examines[$val['id']]['is_examine'] == 2) {
                            $result[$k]['is_review'] = 3;//页面展示-报名详情
                        }
                        break;
                    default:
                        $result[$k]['is_review'] = 0;//班级ID不在我参与的里面
                        if (in_array($val['id'], $plan_ids)) {
                            if (isset($plan_examines[$val['id']]['is_examine']) && $plan_examines[$val['id']]['is_examine'] == 1) {
                                $result[$k]['is_review'] = 2;
                            }
                            if (isset($plan_examines[$val['id']]['is_examine']) && $plan_examines[$val['id']]['is_examine'] == 2) {
                                $result[$k]['is_review'] = 3;
                            }
                            if (isset($plan_examines[$val['id']]['is_examine']) && !$plan_examines[$val['id']]['is_examine']) {
                                $result[$k]['is_review'] = 1;
                            }
                        };
                }
//                switch ($filter) {
//                    case 1:
//                        $result[$k]['is_review'] = 0;
//                        break;
//                    case 2:
//                        $result[$k]['is_review'] = 1;
//                        break;
//                    default:
//                        $result[$k]['is_review'] = 0;
//                        if (in_array($val['id'], $plan_ids)) $result[$k]['is_review'] = 1;
//                }
                $result[$k]['start_time'] = (string)$val['start_time'];
                $result[$k]['end_time'] = (string)$val['end_time'];
            }
        }

        return $result ?: [];
    }

    /**
     * pc端在线学习获取报名班级列表.
     *
     * @param string $keyword 班级/计划的名称
     * @param int $examine_status -1无 0待审核 1已审核 2审核不通过
     * @param int $filter 0-全部 1-未报名 2-审核中
     * @param array $filters
     * @param null|\Key\Records\Pagination $pagination
     * @return array
     */
    public function myApplicantList($filters = [], $keyword, $examine_status = -1, $filter = 0, $pg = null, &$total, $fields = [], $learner_is_examine = [])
    {
        if (!$pg) {
            $pg = new \Key\Records\Pagination();
            $pg->setPage(0);
            $pg->setItemsPerPage(0);
        }

        $db = $this->getMongoMasterConnection();
        $cond = $this->myApplicationPcCon($keyword, $examine_status, $filter, $filters, $learner_is_examine);

        if ($finished_status = ArrayGet($filters, 'finished_status', 0)) { //状态过滤  0-全部 1-未开始 2-未完成 3-已完成 4-已延期
            switch ($finished_status) {
                case 1:
                    //获取未开始学习计划的数量(已发布,当前时间<开始时间) 外派培训--未开始.
                    $cond['is_end'] = 0;
                    $cond['status'] = 2;
                    $cond['start_time'] = ['$gt' => Mongodb::getMongoDate(time())];
                    break;
                case 2:
                    //获取未完成学习计划的数量(已发布,开始时间<当前时间<结束时间,完成率不到100%) 外派培训--进行中.
                    $cond['status'] = 2;
                    $cond['is_end'] = 0;
                    $cond['start_time'] = ['$lte' => Mongodb::getMongoDate(time())];
                    $cond['end_time'] = ['$gt' => Mongodb::getMongoDate(time())];
                    break;
                case 3:
                    //获取已完成学习计划的数量(已发布,开始时间<当前时间<结束时间,完成率100%).
                    $cond['status'] = 2;
                    $cond['is_end'] = 0;
                    $cond['start_time'] = ['$lt' => Mongodb::getMongoDate(time())];
                    $cond['finished_rate'] = 100;
                    break;
                case 4:
                    //获取已延期学习计划的数量(已发布,当前时间>结束时间,完成率<100%)  外派培训--已延期.
                    $cond['status'] = 2;
                    $cond['is_end'] = 0;
                    $cond['end_time'] = ['$lt' => Mongodb::getMongoDate(time())];
                    $cond['finished_rate'] = ['$lt' => 100];
                    break;
            }
        }

        $commonFilters = $this->commonFilters;
        //$enroll_type 0全部 1报名中-当前时间在报名时间范围内 2报名结束-当前时间在报名时间范围外
        $enroll_type = ArrayGet($commonFilters, 'enroll_type', 0);
        $time = time() * 1000;
        if (in_array($enroll_type, [1, 2])) {
            if ($enroll_type == 1) {
                $enroll_or = [
                    ['enroll_setting.custom_enroll_time' => 0, 'start_time' => ['$gte' => Mongodb::getMongoDate()]],
                    ['enroll_setting.custom_enroll_time' => 1, 'enroll_setting.enroll_ended' => ['$gte' => (string)$time]]
                ];
            } else {
                $enroll_or = [
                    ['enroll_setting.custom_enroll_time' => 0, 'start_time' => ['$lte' => Mongodb::getMongoDate()]],
                    ['enroll_setting.custom_enroll_time' => 1, 'enroll_setting.enroll_ended' => ['$lte' => (string)$time]]
                ];
            }

            $cond['$or'] = $enroll_or;
        }
        $total = $db->count(Constants::COLL_LEARNING_PLAN, $cond);
        $result = $db->fetchAll(Constants::COLL_LEARNING_PLAN, $cond, $pg->getOffset(), $pg->getItemsPerPage(), [
            'start_time' => -1
        ], $fields ?: [
            'aid' => 0,
            'desc' => 0
        ]);

        $enrolledLearningFilters = [
            'filter' => $filter,
            'is_examines' => [Learner::EXAMINE_NOT_REVIEW, Learner::EXAMINE_REVIEW_PASSES, Learner::EXAMINE_REVIEW_NOT_PASS]
        ];
        $this->enrolledLearnings($result, $enrolledLearningFilters);

        $modelLearner = new Learner($this->app);
        foreach ($result as $k => $val) {
            $filters_pass_review = [
                'learning_id' => $val['id'],
                'is_examine' => ['$in' => [Learner::EXAMINE_NOT_REVIEW, Learner::EXAMINE_REVIEW_PASSES]]
            ];
            $result[$k]['review_num'] = $modelLearner->getEidsCountByFilters($filters_pass_review);

            $filters_pass = [
                'learning_id' => $val['id'],
                'is_examine' => Learner::EXAMINE_REVIEW_PASSES
            ];
            $result[$k]['register_num'] = $modelLearner->getEidsCountByFilters($filters_pass);
        }
        return $result ?: [];
    }

    /**
     * 查询已报名的班级
     */
    public function enrolledLearnings(&$list, $listFilters = []){

        $is_examines = $listFilters['is_examines'];
        $learner_model = new Learner($this->app);
        $res = $learner_model->getLearningIds(Constants::LEARNING_CLASS, $is_examines, 0, [], 0, [], null);
        $plan_ids = ArrayGet($res, 'plan_ids', []);
        $plan_examines = ArrayGet($res, 'plan_examine', []);

        if ($list) {
            foreach ($list as $k => $val) {
                //enroll_end_status 1报名结束 0报名未结束
                $list[$k]['enroll_end_status'] = 0;
                if (!$val['enroll_setting']['custom_enroll_time']) {
                    if ((string)$val['start_time'] <= time() * 1000) {
                        $list[$k]['enroll_end_status'] = 1;
                    }
                } else {
                    if ($val['enroll_setting']['enroll_ended'] <= time() * 1000) {
                        $list[$k]['enroll_end_status'] = 1;
                    }
                }
                //is_review 0未报名 1审核中 2已通过 3审核不通过
                switch ($listFilters['filter']) {
                    case 1://未报名
                        $list[$k]['is_review'] = 0; //页面展示-马上报名
                        break;
                    case 2://已报名
                        $list[$k]['is_review'] = 1; //页面展示-报名详情
                        if (isset($plan_examines[$val['id']]['is_examine']) && $plan_examines[$val['id']]['is_examine'] == 1) {
                            $list[$k]['is_review'] = 2; //审核通过 展示进入项目
                        }
                        if (isset($plan_examines[$val['id']]['is_examine']) && $plan_examines[$val['id']]['is_examine'] == 2) { //审核不通过
                            $list[$k]['is_review'] = 3; //页面展示-报名详情
                        }
                        break;
                    default:
                        $list[$k]['is_review'] = 0;//班级ID不在我参与的里面
                        if (in_array($val['id'], $plan_ids)) {
                            if (isset($plan_examines[$val['id']]['is_examine']) && $plan_examines[$val['id']]['is_examine'] == 1) {
                                $list[$k]['is_review'] = 2;
                            }
                            if (isset($plan_examines[$val['id']]['is_examine']) && $plan_examines[$val['id']]['is_examine'] == 2) {
                                $list[$k]['is_review'] = 3;
                            }
                            if (isset($plan_examines[$val['id']]['is_examine']) && !$plan_examines[$val['id']]['is_examine']) {
                                $list[$k]['is_review'] = 1;
                            }
                        };
                }
            }
        }

        return $plan_ids;
    }

    /**
     * pc端在线学习获取报名班级列表过滤条件下的数量.
     *
     * @param string $keyword 班级/计划的名称
     * @param int $filter 0-全部 1-未报名 2-审核中
     * @return boolean|int
     */
    public function applicationFilterNums($keyword, $filter = 0)
    {
        $learner_model = new Learner($this->app);
        switch ($filter) {
            case 2:
                $learner_model->getLearningIds(Constants::LEARNING_CLASS, [Learner::EXAMINE_NOT_REVIEW, Learner::EXAMINE_REVIEW_NOT_PASS], 0, [], 0, $keyword ? ['keyword' => $keyword] : [], null, $total);
                break;
            default:
                $condition = $this->applicationPcCon($keyword, $filter);
                $total = $this->getMongoMasterConnection()->count(Constants::COLL_LEARNING_PLAN, $condition);
        }

        return $total;
    }

    //pc端班级报名的条件
    protected function applicationPcCon($keyword, $filter = 0)
    {
        $learner_model = new Learner($this->app);
        switch ($filter) {
            case 1:
                $is_examine = [Learner::EXAMINE_NOT_REVIEW, Learner::EXAMINE_REVIEW_PASSES, Learner::EXAMINE_REVIEW_NOT_PASS];//获取所有的数据（审核/未审核）
                break;
            case 2:
                $is_examine = [Learner::EXAMINE_NOT_REVIEW, Learner::EXAMINE_REVIEW_NOT_PASS];
                break;
            default:
                $is_examine = [Learner::EXAMINE_REVIEW_PASSES];//获取未审核的数据
        }
        $res = $learner_model->getLearningIds(Constants::LEARNING_CLASS, $is_examine, 0, [], 0, [], null);

        $plan_ids = ArrayGet($res, 'plan_ids', []);

        $cond = [
            'is_end' => self::NOT_END,
            'status' => 2,
            'aid' => $this->aid,
            'type' => Constants::LEARNING_CLASS
        ];
        if ($keyword) $cond['name'] = new Regex($keyword, 'im');
        //0-全部 1-未报名 2-审核中
        if ($filter == 2) {
            $cond['id'] = ['$in' => $plan_ids];
            return $cond;
        }
        $cond['is_applicant'] = 1;

        $_or = $this->getApplicantCon();
        if ($plan_ids) $cond['id'] = ['$nin' => $plan_ids];
        if (isset($cond['$or'])) {
            if (!$filter) $cond['$or'] = array_merge($cond['$or'], $_or);
            unset($cond['$or']);
        } else {
            $cond['$or'] = $_or;
        }

        return $cond;
    }

    /**
     * 我的报名班级列表-基础信息
     * @param $keyword
     * @param array $filters 可报名班级列表过滤
     * @return array
     */
    public function applicationPlan($keyword = '', $filters = [])
    {
        $cond = [
            'aid' => $this->aid,
            'status' => 2,
            'is_applicant' => 1,
            'type' => Constants::LEARNING_CLASS
        ];

        if (($project_id = ArrayGet($filters, 'project_id', -1)) != -1) $cond['project_id'] = $project_id;
        if ($source_project = ArrayGet($filters, 'source_project', 0)) $cond['project_id'] = ['$gt' => 0];
        if ($keyword) $cond['name'] = new Regex($keyword, 'im');

        $start_time = ArrayGet($filters, 'start_time', '');
        $end_time = ArrayGet($filters, 'end_time', '');

        if ($start_type = ArrayGet($filters, 'start_type', -1)) {
            //当前时间
            if ($start_type == 1) { //未开始
                $cond['start_time'] = ['$gt' => Mongodb::getMongoDate()];
            } else if ($start_type == 2) { //已开始
                $cond['start_time'] = ['$lte' => Mongodb::getMongoDate()];
            }
        }

        if ($start_time && $end_time && isset($cond['start_time'])) {
            $cond['$and'] = [
                ['start_time' => $cond['start_time']],
                ['start_time' => ['$gte' => Mongodb::getMongoDate($start_time / 1000), '$lte' => Mongodb::getMongoDate($end_time / 1000)]],
            ];
            unset($cond['start_time']);
        }

        if (($is_end = ArrayGet($filters, 'is_end', -1)) != -1) {
            $cond['is_end'] = $is_end;
        }

        if ($filters['creator'] && is_array($filters['creator'])) $cond['creator.id'] = ['$in' => $filters['creator']];

        $enroll_or = $this->getApplicantCon();
        if (isset($cond['$or'])) {
            $cond['$and'] = [
                ['$or' => $cond['$or']],
                ['$or' => $enroll_or],
            ];
            unset($cond['$or']);
        } else {
            $cond['$or'] = $enroll_or;
        }

        return $cond;
    }

    /**
     * 我的报名广场
     * @param $keyword
     * @param int $examine_status -1全部 0待审核 1已审核 2审核不通过
     * @param int $filter 0全部 1未报名 2已报名
     * @return array
     */
    protected function myApplicationPcCon($keyword = '', $examine_status = -1, $filter = 0, $filters = [], $learner_is_examine = [\App\Models\Learner::EXAMINE_NOT_REVIEW, \App\Models\Learner::EXAMINE_REVIEW_PASSES, \App\Models\Learner::EXAMINE_REVIEW_NOT_PASS])
    {

        $cond = $this->applicationPlan($keyword, $filters);

        if ($examine_status != -1) {
            $filter = 2;
            if ($examine_status == 1) {
                $learner_is_examine = [Learner::EXAMINE_REVIEW_PASSES];
            } elseif ($examine_status == 2) {
                $learner_is_examine = [Learner::EXAMINE_REVIEW_NOT_PASS];
            } elseif (!$examine_status) {
                $learner_is_examine = [Learner::EXAMINE_NOT_REVIEW];
            }
        }

        $learner_model = new Learner($this->app);
        //获取某人已报名（已报名包括 待审核 已审核 不通过）的班级ID
        $res = $learner_model->getLearningIds(Constants::LEARNING_CLASS, $learner_is_examine, 0, [], ArrayGet($filters, 'eid', 0), [], null);
        $plan_ids = ArrayGet($res, 'plan_ids', []);

        if ($filter == 1) { //未报名的
//            if (isset($cond['$or'])) unset($cond['$or']);
            if ($plan_ids) $cond['id'] = ['$nin' => $plan_ids];
        }
        if ($filter == 2) { //已报名的
//            if (isset($cond['$or'])) unset($cond['$or']);
            $cond['id'] = ['$in' => $plan_ids];
            return $cond;
        }

        return $cond;
    }

    //获取员工报名条件
    protected function getApplicantCon()
    {
        $employee_model = new Employee($this->app);
        $employee = $employee_model->view($this->eid, $this->aid, [
            'department_id' => 1,
            'position_id' => 1,
            'job_level_id' => 1,
            'groups' => 1
        ]);
        $job_level_id = ArrayGet($employee, 'job_level_id', 0);

        //获取当前员工所属部门及其以上部门
        $dept_model = new Department($this->app);
        $depts = $dept_model->getMyAndParentDepartment($employee['department_id']);
        $dept_ids = array_column($depts, 'id');

        $_cond = [
            ['applicant_auth' => 0],
            ['applicant_auth' => 1, 'applied_range' => [
                '$elemMatch' => [
                    '$or' => [
                        ['type' => self::APPLIED_RANGE_EMPLOYEE, 'id' => $this->eid],
                        ['type' => self::APPLIED_RANGE_POSITION, 'id' => ArrayGet($employee, 'position_id', 0)],
                        ['type' => self::APPLIED_RANGE_DEPARTMENT, 'id' => ArrayGet($employee, 'department_id', 0)],
                        ['type' => self::APPLIED_RANGE_DEPARTMENT, 'id' => ['$in' => $dept_ids], 'children_included' => 1],
                        ['type' => self::APPLIED_RANGE_GROUP, 'id' => ['$in' => ArrayGet($employee, 'groups', [])]],
                        ['type' => self::APPLIED_RANGE_JOB_LEVEL, 'id' => $job_level_id],
                    ]
                ]
            ]],
        ];

        return $_cond;
    }

    /**
     * h5获取学习计划列表.
     *
     * @param string $keyword .
     * @param int $filter .
     * @param int $sort
     * @param int $type
     * @param int $is_applicant
     * @param array $con eg: ['eid'=>1, 'start_time'=>'', 'end_time'=>'']
     * @param null|\Key\Records\Pagination $pg
     * @param int $total
     * @return array
     */
    public function listByLearner($keyword, $filter, $sort, $type, $is_applicant, $pg, $con = [], &$total = 0)
    {
        $eid = ArrayGet($con, 'eid', 0);
        $project_id = ArrayGet($con, 'project_id', 0);

        $learner_model = new Learner($this->app);
        if ($type != 0) $is_applicant = 1;

        //是否通过学员完成率排序
        $learner_rate_sort = 0;
        if (ArrayGet($sort, 'learning_rate')) $learner_rate_sort = 1;

        //如果是h5获取可报名的班级列表的话，要拿到该学员当前参与的所有班级，做排除，此时分页走learning_plan表
        if (!$is_applicant || $keyword) {
            $learner_pg = null;
        } else {
            $learner_pg = $pg;
            $pg = new Pagination();
            $pg = $pg->setPage(0)->setItemsPerPage(0);
        }
        $res = $learner_model->getLearningIds($type, Learner::EXAMINE_REVIEW_PASSES, $filter, $sort, $eid, $con, $learner_pg, $total);
        //如果是获取我参与的班级，没有数据，直接返回空数组
        if (!$res && $is_applicant == 1) return [];

        $plan_ids = ArrayGet($res, 'plan_ids', []);
        $plan_rate = ArrayGet($res, 'learn_rate', 0);

        $cond = [
            'aid' => $this->aid,
            'type' => $type,
            'status' => 2
        ];
        if ($plan_ids) $cond['id'] = ['$in' => $plan_ids];
        if ($project_id) $cond['project_id'] = $project_id;
        if ($keyword) $cond['name'] = new Regex($keyword, 'im');

        //获取可以报名的班级列表时的条件
        if ($type == 0 && $is_applicant == 0) {
            $cond['is_end'] = self::NOT_END;
            $cond['end_time'] = ['$gt' => Mongodb::getMongoDate()];
            $cond['is_applicant'] = 1;

            $_or = [];
            if ($this->aid != env('JINMAO_DATA_BACK_AID', '4955')) {
                $_or = $this->getApplicantCon();
            }
            if (isset($cond['$or'])) {
                $cond['$or'] = array_merge($cond['$or'], $_or, ['id' => ['$nin' => $plan_ids]]);
                unset($cond['id']);
            } else {
                if ($_or) {
                    $cond['$or'] = $_or;
                }
                $cond['id'] = ['$nin' => $plan_ids];
            }

        }

        $sortField = ['is_set_top' => -1, 'created' => -1];
        if (!$learner_rate_sort) {
            if (($class_sort = ArrayGet($sort, 'start_time', '')) != '') $sortField = array_merge(['is_set_top' => -1], $sort, $sortField);
        }

        $db = $this->getMongoMasterConnection();
        if (!$learner_pg) $total = $db->count(Constants::COLL_LEARNING_PLAN, $cond);
        $result = $db->fetchAll(Constants::COLL_LEARNING_PLAN, $cond,
            $pg->getOffset(), $pg->getItemsPerPage(),
            $sortField, [
                'id' => 1,
                'name' => 1,
                'start_time' => 1,
                'end_time' => 1,
                'is_set_top' => 1,
                'display' => 1,
                'uid' => 1,
                'status' => 1,
                'is_end' => 1,
                'allow_enroll_num' => 1,
                'official_num' => 1,
                'cover' => 1,
                'created' => 1
            ]);

        if ($result) {
            $plan_ids = array_column($result, 'id');
            $learners = $learner_model->getLearningsLearnerNum($plan_ids, $type);
            $enroll_learners = $learner_model->getLearningsLearnerNum($plan_ids, $type, ['is_examine' => -1]);
            foreach ($result as $k => $val) {
                $learner_info = ArrayGet($res['plan_examine'], $val['id'], []);
                $val['learner_num'] = ArrayGet($learners, $val['id'], 0);
                $val['enroll_learner_num'] = ArrayGet($enroll_learners, $val['id'], 0);
                $val['start_time'] = (string)$val['start_time'];
                $val['end_time'] = (string)$val['end_time'];
                $val['created'] = (string)$val['created'];
                $val['learning_cover'] = $val['cover'];
                $val['learning_rate'] = ArrayGet($learner_info, 'learning_rate', 0);
                $val['is_examine'] = ArrayGet($learner_info, 'is_examine', 1);
                $val['is_elective'] = ArrayGet($learner_info, 'is_elective', 0);
                $result[$k] = $val;
            }
            if ($learner_rate_sort) {
                $rate = array_column($result, 'learning_rate');
                $times = array_column($result, 'start_time');
                if (($is_up = ArrayGet($sort, 'learning_rate')) == 1) {
                    array_multisort($rate, SORT_ASC, $times, SORT_DESC, $result);
                } else {
                    array_multisort($rate, SORT_DESC, $times, SORT_DESC, $result);
                }
            }
        }

        return $result ?: [];
    }

    public function getByIds($plan_ids, $type = Constants::LEARNING_CLASS, $pg = null, $fields = [])
    {
        if (!$pg) {
            $pg = new \Key\Records\Pagination();
            $pg->setPage(0);
            $pg->setItemsPerPage(0);
        }

        $cond = [
            'aid' => $this->aid,
            'type' => $type,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
            'id' => ['$in' => $plan_ids]
        ];
        if ($type == -1) {
            unset($cond['type']);
        }
        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, $cond,
            $pg->getOffset(),
            $pg->getItemsPerPage(), [], $fields ?: [
                'created' => 1,
                'class_address' => 1,
                'uid' => 1,
                'id' => 1,
                'name' => 1,
                'start_time' => 1,
                'end_time' => 1,
            ]);

        return $result ?: [];
    }

    /**
     * 任务统计(以任务为单位)
     *
     * @param int $learning_id 智能班级id.
     * @param int $group_id 分组id 默认-1 全部.
     * @param int $type 0-智能班级 1-学习包 2-闯关 3-学习计划.
     * @return array
     */
    public function taskStatistics($learning_id, $group_id, $type = 0)
    {
        if (in_array($type, [Constants::LEARNING_PACK, Constants::LEARNING_COURSE_PACK, Constants::LEARNING_SPECIAL_TOPIC_PACK, Constants::LEARNING_TRAINING,
            Constants::LEARNING_POSITION, Constants::LEARNING_MAP_BUSINESS,
            Constants::LEARNING_MAP, Constants::LEARNING_APPRAISAL])) {
            $pack_model = new LearningPack($this->app);
            $plan = $pack_model->isExist($learning_id, [
                'id' => 1,
                'name' => 1
            ]);
        } else {
            $plan = $this->isExist($learning_id, [
                'id' => 1,
                'name' => 1,
                'end_time' => 1,
                'start_time' => 1,
                'finished_rate' => 1,
                'unlock_con' => 1,
                'desc' => 1,
                'type' => 1
            ]);
        }
        if (!$plan) return false;
        $task_info = $this->getTasks($learning_id, $type, $group_id);
        $plan['task_num'] = $task_info['task_num'];
        $plan['tasks'] = $task_info['tasks'];
//        $learner_model = new Learner($this->app);
//        $plan['learner_num'] = $learner_model->getClassLearner($learning_id, $type, ['group_id' => $group_id]);
        $learnerModel = new NewLearner($this->app);
        $learnerModel->setFilter([OldLearner::FILTER_FIELD_LEARNING_ID => $learning_id, OldLearner::FILTER_FIELD_TYPE => $type, OldLearner::FILTER_FIELD_GROUP_ID => $group_id])->totalLearnerByLearning();
        return $plan ?: [];
    }

    public function getTasks($learning_id, $type, $group_id = -1, $used_teaching_id = 0, $filters = [])
    {
        $learner_model = new Learner($this->app);
        $task_model = new LearningTask($this->app);

        $tasks = $task_model->getList($learning_id, $type, []);
//        if ($type == Constants::LEARNING_TEACHING) {
            $learner_teaching_model = new LearnerTeaching($this->app);
            $learned_task = $learner_teaching_model->viewTeaching($learning_id, ['learned_task' => 1], $type);
            $learned_task = array_column($learned_task['learned_task'], null, 'task_id');
//        }
        $task_num = 0;

        $task_rate = $learner_model->getTaskFinishedRate($learning_id, $type, $group_id, $used_teaching_id, $filters);
        // 小组作业进度
        $group_filters = $filters;
        $group_filters['task_type'] = Constants::TASK_GROUP_WORK;
        $group_filters['group_status'] = 1;
        $group_filters['is_group_leader'] = 1;
        $group_task_rate = $learner_model->getTaskFinishedRate($learning_id, $type, $group_id, $used_teaching_id, $group_filters);

        $teach_task = $student_task = 0;
        $finished_rate = $teaching_rate = $student_rate = 0;
        $total_rate = $fin_rate = $t_total_rate = $t_fin_rate = $s_total_rate = $s_fin_rate = 0;

        if ($tasks) {
            foreach ($tasks as $item => $group) {
                if (count($group['tasks'])) {
                    foreach ($group['tasks'] as $k => $val) {
                        if ($val['is_elective'] == 0) $task_num += 1;
                        if ($val['task_type'] == Constants::TASK_GROUP_WORK) {
                            $val['finished_rate'] = ArrayGet($group_task_rate, $val['task_id'] . '-' . $val['task_type'], 0);
                        } else {
                            $val['finished_rate'] = ArrayGet($task_rate, $val['task_id'] . '-' . $val['task_type'], 0);
                        }
                        if (in_array($val['task_type'], [Constants::TASK_OFFLINE_ASSESSMENT, Constants::TASK_LEAD_TRAINING, Constants::TASK_OFFLINE_TRAINING, Constants::TASK_TRAINING_TUTOR])) {
                            $val['is_teaching'] = 1;
                            $teach_task++;
                            $t_fin_rate += $val['finished_rate'];
                            $t_total_rate++;
                        } else {
                            $student_task++;
                            $s_fin_rate += $val['finished_rate'];
                            $s_total_rate++;
                        }
                        $fin_rate += $val['finished_rate'];
                        $total_rate++;
                        if ($learned_task && $learned_task[$val['task_id']]) {
                            $val['score'] = $val['score'] ?: $learned_task[$val['task_id']]['score'];
                            $val['finished_status'] = $val['finished_status'] ?: $learned_task[$val['task_id']]['finished_status'];
                            $val['is_finished'] = $val['is_finished'] ?: $learned_task[$val['task_id']]['is_finished'];
                            $val['finished_time'] = $val['finished_time'] ?: $learned_task[$val['task_id']]['finished_time'];
                            $val['score_used'] = $val['score_used'] ?: $learned_task[$val['task_id']]['score_used'];
                        }
                        $group['tasks'][$k] = $val;
                    }
                }
                $tasks[$item] = $group;
            }
        }
        if ($fin_rate > 0 && $total_rate > 0) {
            $finished_rate = number_format($fin_rate / $total_rate, 2);
        }
        if ($t_total_rate > 0 && $t_fin_rate > 0) {
            $teaching_rate = number_format($t_fin_rate / $t_total_rate, 2);
        }
        if ($s_total_rate > 0 && $s_fin_rate > 0) {
            $student_rate = number_format($s_fin_rate / $s_total_rate, 2);
        }
        $rate = [
            'finished_rate' => $finished_rate,
            'teaching_rate' => $teaching_rate,
            'student_rate' => $student_rate
        ];
        return [
            'tasks' => $tasks,
            'task_num' => $task_num,
            'teach_task' => $teach_task,
            'student_task' => $student_task,
            'rate' => $rate
        ];
    }

    /**
     * 查看学习计划基本信息.
     *
     * @param int $learning_id .
     * @param int $type .
     * @return array
     */
    public function getBasicInfo($learning_id, $type = 0)
    {
        $result = $this->isExist($learning_id, [
            'created' => 0,
            'updated' => 0
        ]);
        if (!$result) return false;

        if (in_array($type, [0, 4])) {
            $result['unlock_con'] = ArrayGet($result, 'unlock_con', 0);
            if ($type === Constants::LEARNING_TRAINING) {
                $result['rating_status'] = ArrayGet($result, 'rating_status', 0);
                $result['ratings'] = ArrayGet($result, 'ratings', []);
            }

            $employee_group_model = new EmployeeGroup($this->app);
            $result['add_group_labels'] = [];
            if ($add_groups = ArrayGet($result, 'add_groups')) {
                $groups = $employee_group_model->getNameList($add_groups);
                foreach ($groups as $k => $val) {
                    $result['add_group_labels'][] = ['id' => $k, 'name' => $val];
                }
            }

            $result['del_group_labels'] = [];
            if ($del_groups = ArrayGet($result, 'del_groups')) {
                $groups = $employee_group_model->getNameList($del_groups);
                foreach ($groups as $k => $val) {
                    $result['del_group_labels'][] = ['id' => $k, 'name' => $val];
                }
            }
        }

        //获取张阳阳培训需求
        if ($training_need_question_ids = ArrayGet($result, 'training_need_question_ids', [])) {
            $this->getTrainingNeedsQuestions($training_need_question_ids, $result);
        }

        return $result;
    }

    protected function getTrainingNeedsQuestions($training_need_question_ids, &$result){
        $modelTrainingNeedsQuestion = new TrainingNeedsQuestion($this->app);
        $trainingNeedsQuestionList = $modelTrainingNeedsQuestion->getSimpleMapByIds($training_need_question_ids);
        $training_need_question = [];
        foreach ($result['training_need_question_ids'] as $key => $value) {
            $item = [
                'id' => $value,
                'name' => $trainingNeedsQuestionList[$value] ?: ''
            ];
            $training_need_question[] = $item;
        }
        $result['training_need_question'] = $training_need_question;
    }

    /**
     * 删除证书调用接口(floarDeng调用).
     *
     * @param int $certificate_id 证书id
     * @return boolean|int
     */
    public function delCertificate($certificate_id)
    {
        $this->delCertify($certificate_id);
        $pack_model = new LearningPack($this->app);
        $pack_model->delCertificate($certificate_id);
    }

    //根据证书id获取班级
    public function listByCertificateId($certificate_id)
    {
        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
            'certificate.id' => $certificate_id
        ], 0, 0, [], [
            'id' => 1
        ]);

        return $result ?: [];
    }

    protected function delCertify($certificate_id)
    {
        $plans = $this->listByCertificateId($certificate_id);
        if (!$plans) return true;

        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'certificate_id' => $certificate_id,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
        ], [
            '$set' => [
                'certificate_id' => 0,
                'certificate_name' => ''
            ]
        ]);
        if ($result) {
            foreach ($plans as $val) {
                $cache_key = $this->getRedisId($val['id']);
                $this->getCacheInstance()->delete($cache_key);
            }
        }

        return $result ?: false;
    }

    /**
     * 更新证书调用接口(floarDeng调用).
     *
     * @param int $certificate_id 证书id
     * @param string $certificate_name 证书名称
     * @return boolean|int
     */
    public function updateCertificate($certificate_id, $certificate_name)
    {
        $plans = $this->listByCertificateId($certificate_id);
        if (!$plans) return true;
        $result = $this->updateCertify($certificate_id, $certificate_name);
        if ($result) {
            foreach ($plans as $val) {
                $cache_key = $this->getRedisId($val['id']);
                $this->getCacheInstance()->delete($cache_key);
            }
        }
        $pack_model = new LearningPack($this->app);
        $pack_model->updateCertify($certificate_id, $certificate_name);
    }

    protected function updateCertify($certificate_id, $certificate_name)
    {
        return $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'certificate_id' => $certificate_id,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
        ], [
            '$set' => [
                'certificate_name' => $certificate_name
            ]
        ]);
    }

    /**
     * h5获取智能班级基本信息
     *
     * @param int $learning_id .
     * @param int $type .
     * @return array
     */
    public function introduction($learning_id, $type = 0)
    {
        $result = $this->isExist($learning_id, [
            'created' => 0,
            'updated' => 0
        ]);
//        $result = $this->isExist($learning_id, [
//            'id' => 1,
//            'start_time' => 1,
//            'end_time' => 1,
//            'name' => 1,
//            'uid' => 1,
//            'desc' => 1,
//            'is_applicant' => 1,
//            'is_examine' => 1,
//            'applicant_auth' => 1,
//            'applied_range' => 1,
//            'class_type' => 1,
//            'is_organize_transport' => 1,
//            'need_applicant_extra' => 1,
//            'applicant_extra_con' => 1,
//            'is_accommodation' => 1,
//            'application_hints' => 1,
//            'enroll_setting' => 1,
//            'enroll_form' => 1,
//            'app_setting' => 1,
//            'application_start_time' => 1,
//            'application_end_time' => 1,
//        ]);

        if ($result) {
            $result['class_type'] = ArrayGet($result, 'class_type', 0);
            $result['enroll_setting'] = ArrayGet($result, 'enroll_setting', []);
            $result['enroll_form'] = ArrayGet($result, 'enroll_form', []);
            $result['application_start_time'] = ArrayGet($result['enroll_setting'], 'enroll_started', '');
            $result['application_end_time'] = ArrayGet($result['enroll_setting'], 'enroll_ended', '');
            $result['is_organize_transport'] = ArrayGet($result['enroll_form'], 'is_transport', '');
            $result['is_accommodation'] = ArrayGet($result['enroll_form'], 'is_hotel', 0);
            $allowed = ArrayGet($result, 'is_applicant', 0);
            $auth = ArrayGet($result, 'applicant_auth', 0);
            $result['need_examine'] = ArrayGet($result, 'is_examine', 0);
            $creator_id = ArrayGet($result, 'uid', 0);

            //学员报名状态
            $learner_model = new Learner($this->app);
            $learner = $learner_model->viewLearner($learning_id, $this->eid, $type, 1);
            $result['is_examine'] = ArrayGet($learner, 'is_examine', 0);
            $result['is_applicant'] = ArrayGet($learner, 'is_applicant', 0);

            if ($allowed && $creator_id != $this->eid) {
                if ($auth == 1) {

                    $applied_range = ArrayGet($result, 'applied_range', []);
                    $this->isAllowApplicant($applied_range, $keep_going);
                    if ($auth && $keep_going == 0) return self::LEARNING_PLAN_NO_PERMISSION;
                }
            } else if ($result['is_applicant'] == 0 && $auth != 0 && $creator_id != $this->eid) {
                return self::LEARNING_PLAN_NO_PERMISSION;
            }
            $result['allow_applicant'] = $allowed;

            //学员人数
            $learner_res = $learner_model->getLearningsLearnerNum([$learning_id], $type);
            $result['learner_num'] = ArrayGet($learner_res, $learning_id, 0);
        } else {
            return self::LEARNING_PLAN_NO_PERMISSION;
        }

        $modelLearner = new Learner($this->app);
        $filters_pass_review = [
            'learning_id' => $learning_id,
            'is_examine' => ['$in' => [Learner::EXAMINE_NOT_REVIEW, Learner::EXAMINE_REVIEW_PASSES]]
        ];
        $result['review_num'] = $modelLearner->getEidsCountByFilters($filters_pass_review);

        $filters_pass = [
            'learning_id' => $learning_id,
            'is_examine' => Learner::EXAMINE_REVIEW_PASSES
        ];
        $result['register_num'] = $modelLearner->getEidsCountByFilters($filters_pass);

        return $result ?: [];
    }

    //判断某人是否允许报名
    public function isAllowApplicant($applied_range, &$keep_going, $eid = 0)
    {
        $keep_going = 0;
        if ($applied_range) {
            $eid = $eid ?: $this->eid;
            $employee_model = new Employee($this->app);
            $employee = $employee_model->view($eid, $this->aid, ['position_id' => 1, 'department_id' => 1, 'groups' => 1, 'job_level_id' => 1]);
            $position_id = ArrayGet($employee, 'position_id', 0);
            $dept_id = ArrayGet($employee, 'department_id', 0);
            $group_ids = ArrayGet($employee, 'groups', 0);
            $job_level_id = ArrayGet($employee, 'job_level_id', 0);

            //获取当前员工所属部门及其以上部门
            $dept_model = new Department($this->app);
            $depts = $dept_model->getMyAndParentDepartment($employee['department_id']);
            $dept_ids = array_column($depts, 'id');

            foreach ($applied_range as $val) {
                if ($val['type'] == self::APPLIED_RANGE_EMPLOYEE && $val['id'] == $eid) {
                    $keep_going = 1;
                    break;
                }
                if ($val['type'] == self::APPLIED_RANGE_POSITION && $val['id'] == $position_id) {
                    $keep_going = 1;
                    break;
                }
                if ($val['type'] == self::APPLIED_RANGE_DEPARTMENT) {
                    if (ArrayGet($val, 'children_included', 0)) {
                        if (in_array($val['id'], $dept_ids)) {
                            $keep_going = 1;
                            break;
                        }
                    } else if ($val['id'] == $dept_id) {
                        $keep_going = 1;
                        break;
                    }
                }
                if ($val['type'] == self::APPLIED_RANGE_GROUP && in_array($val['id'], $group_ids)) {
                    $keep_going = 1;
                    break;
                }
                if ($val['type'] == self::APPLIED_RANGE_JOB_LEVEL && $val['id'] == $job_level_id) {
                    $keep_going = 1;
                    break;
                }
            }
        }
    }

    //判断某人是必修学员还是选修学员
    public function isElective($range, &$keep_going, $eid = 0, $employee = [])
    {
        $keep_going = 0;
        if ($range) {
            $eid = $eid ?: $this->eid;
            if (!$employee) {
                $employee_model = new Employee($this->app);
                $employee = $employee_model->view($eid);
            }
            $position_id = ArrayGet($employee, 'position_id', 0);
            $dept_id = ArrayGet($employee, 'department_id', 0);
            $group_ids = ArrayGet($employee, 'groups', 0);

            $dept_model = new Department($this->app);
            if ($range) {
                foreach ($range as $_item) {
                    if ($_item['type'] == self::APPLIED_RANGE_EMPLOYEE && $_item['id'] == $eid) {
                        $keep_going = 1;
                        break;
                    }
                    if ($_item['type'] == self::APPLIED_RANGE_POSITION && $_item['id'] == $position_id) {
                        $keep_going = 1;
                        break;
                    }
                    if ($_item['type'] == self::APPLIED_RANGE_DEPARTMENT) {
                        $dept_ids = [$_item['id']];
                        if (ArrayGet($_item, 'children_included', 0)) {
                            $depts = $dept_model->getMyAndParentDepartment($dept_id);
                            $dept_ids = array_merge($dept_ids, array_column($depts, 'id'));
                        }
                        if (in_array($dept_id, array_unique($dept_ids))) {
                            $keep_going = 1;
                            break;
                        }
                    }
                    if ($_item['type'] == self::APPLIED_RANGE_GROUP && in_array($_item['id'], $group_ids)) {
                        $keep_going = 1;
                        break;
                    }
                }
            }
        }
    }

    /**
     * 查看pk分组是否存在.
     *
     * @param string $name .
     * @param int $ignore_id .
     * @return array
     */
    public function pkGroupExists($learning_id, $name, $ignore_id = 0)
    {
        if ($ignore_id && !is_array($ignore_id)) $ignore_id = [$ignore_id];
        $cond = [
            'aid' => $this->aid,
            'learning_id' => $learning_id,
            'name' => $name,
            'status' => static::ENABLED
        ];
        if ($ignore_id) $cond['id'] = ['$nin' => $ignore_id];

        return $this->getMongoMasterConnection()->fetchRow(Constants::COLL_LEARNER_PK_GROUP, $cond);
    }

    /**
     * 创建pk分组.
     *
     * @param int $learning_id .
     * @param string $name .
     * @param int $group_id .
     * @param int $errno .
     * @param int $project_id .
     * @return boolean
     */
    public function createPkGroup($learning_id, $record, $group_id = -1, &$errno = 0)
    {
        $bind = [
            'id' => 0,
            'status' => 1,
            'score' => 0,
            'aid' => $this->aid,
            'eid' => $this->eid,
            'learning_id' => $learning_id,
            'created' => Mongodb::getMongoDate(),
            'updated' => Mongodb::getMongoDate()
        ];
        $bind = array_merge($bind, $record);
        if ($group_id == -1) $bind['id'] = Sequence::getSeparateId('learner_pk_group', $this->aid);
        $result = $this->getMongoMasterConnection()->insert(Constants::COLL_LEARNER_PK_GROUP, $bind);

        if ($result) {
            if ($bind['id']) {
                $learner_model = new Learner($this->app);
                $leader = $learner_model->viewLearner($learning_id, $record['group_leader']['id']);
                // 更新组长信息
                $learner_model->learnerGroupLeader($learning_id, $bind['id'], $record['group_leader']['id']);
                // 更新组员的小组名称
                $learner_model->updateLearnerGroupName($bind['id'], $record['name']);
                //更新组长小组作业进度
                if ($leader['group_id']) {//从其他组选择的组长
                    $model = new LearnerV2($this->app);
                    $model->learnerGroupChangeTaskRateQueue($learning_id, $bind['id'], [$record['group_leader']['id']]);
                }
            }
            return $bind['id'];
        } else {
            $errno = Constants::SYS_DATABASE_ERROR;
        }

        return false;
    }

    /**
     * 更新pk分组名称.
     *
     * @param int $id .
     * @param array $record .
     * @param int $errno .
     * @return boolean
     */
    public function updatePkGroup($id, $record, &$errno)
    {
        $record['updated'] = Mongodb::getMongoDate();

        if ($pkGroup = $this->getPkGroupById($id)) {

            if ($this->pkGroupExists($pkGroup['learning_id'], $record['name'], $id)) {
                throw new AppException('小组名称已被使用');
//                $errno = Constants::USER_OBJECT_OCCUPIED;
            } else {
                $learner_model = new Learner($this->app);
                $new_leader = $learner_model->viewLearner($pkGroup['learning_id'], $record['group_leader']['id']);

                $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNER_PK_GROUP, [
                    'id' => $id,
                    'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
                    'aid' => $this->aid
                ], [
                    '$set' => $record
                ]);
                if ($result) {
                    // 更新组长信息
                    $learner_model->learnerGroupLeader($pkGroup['learning_id'], $id, $record['group_leader']['id']);
                    // 更新组员的小组名称
                    $learner_model->updateLearnerGroupName($id, $record['name']);

                    //是否更换了组长
                    $group_leader_has_change = $pkGroup['group_leader']['id'] != $record['group_leader']['id'];
                    //组长是否从其他组调过来的
                    $group_leader_from_other = $new_leader['group_id'] != $id;
                    if ($group_leader_has_change) {
                        //更新小组作业的eid为新组长
                        $task_model = new Task($this->app);
                        $task_model->updateEidByGroupId($pkGroup['learning_id'], $id, $record['group_leader']['id']);

                        //从其他组调过来的(同组更换不用更新进度)
                        if ($group_leader_from_other) {
                            //更新新组长小组作业进度
                            $model = new LearnerV2($this->app);
                            $model->learnerGroupChangeTaskRateQueue($pkGroup['learning_id'], $id, [$record['group_leader']['id']]);
                        }

                    }
                }
                return $result ?: false;
            }
        } else {
            $errno = Constants::USER_OBJECT_NOT_FOUND;
        }

        return false;
    }

    /**
     * 更新pk分组分数.
     *
     * @param int $id 分组id.
     * @param int $learning_id 智能班级id.
     * @param int $is_add 是否加分 0-否 1-是.
     * @param int $errno .
     * @return boolean
     */
    public function pkGroupScore($learning_id, $id, $is_add = 1, &$errno)
    {
        if ($pkGroup = $this->getPkGroupById($id)) {

            $group_score = ArrayGet($pkGroup, 'score', 0);

            return $this->getMongoMasterConnection()->update(Constants::COLL_LEARNER_PK_GROUP, [
                'id' => $id,
                'learning_id' => $learning_id,
                'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
                'aid' => $this->aid
            ], [
                '$inc' => ['score' => 1 * $is_add],
                '$set' => ['updated' => Mongodb::getMongoDate()]
            ]);
        } else {
            $errno = Constants::USER_OBJECT_NOT_FOUND;
        }

        return false;
    }

    /**
     * 删除pk分组.
     *
     * @param int $id .
     * @param int $errno .
     * @return boolean|int
     */
    public function deletePkGroup($id = 0, &$errno = 0)
    {
        $pk_group = $this->getPkGroupById($id);
        if ($pk_group) {

            $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNER_PK_GROUP, [
                'id' => $id,
                'aid' => $this->aid
            ], [
                '$set' => [
                    'last_eid' => $this->eid,
                    'status' => static::DISABLED,
                    'updated' => Mongodb::getMongoDate()
                ]
            ]);
            if ($result) {
                $learner_model = new Learner($this->app);

                // 获取被删除小组的学员
                $pg = new Pagination();
                $pg->setPage(0)->setItemsPerPage(0);
                $group_learners = $learner_model->learnersInPkGroup($pk_group['learning_id'], $id, $pg);
                $group_eids = array_column($group_learners, 'eid');

                $learner_model->updateLearnerPkGroup($pk_group['learning_id'], 0, $id);

                //更新被删除小组的学员进度
                $v2_model = new LearnerV2($this->app);
                $v2_model->learnerGroupChangeTaskRateQueue($pk_group['learning_id'], 0, $group_eids);

                //删除已提交的小组作业
                $task_model = new Task($this->app);
                $task_model->removeByGroupIds([$id]);
            }

            return $result ? true : false;
        } else {
            $errno = Constants::USER_OBJECT_NOT_FOUND;
        }

        return false;
    }

    /**
     * 删除pk分组.
     *
     * @param int $id .
     * @param int $errno .
     * @return boolean|int
     */
    public function delPkGroupByClass($learning_id, $is_del_class = 0)
    {
        if (!is_array($learning_id)) $learning_id = [$learning_id];
        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNER_PK_GROUP, [
            'learning_id' => ['$in' => $learning_id],
            'status' => static::ENABLED,
            'aid' => $this->aid
        ], [
            '$set' => [
                'last_eid' => $this->eid,
                'status' => static::DISABLED,
                'updated' => Mongodb::getMongoDate()
            ]
        ]);
        if ($result && !$is_del_class) {
            $learner_model = new Learner($this->app);
            $learner_model->updateLearnerPkGroup($learning_id, 0);
        }

        return $result ? true : false;
    }

    /**
     * 查看pk分组详情.
     *
     * @param int $id .
     * @return array
     */
    public function getPkGroupById($id, $fields = [])
    {
        if ($id && $fields) {
            $fields['group_leader'] = 1;
            $fields['learning_id'] = 1;
        }
        $pk_group = $this->getMongoMasterConnection()->fetchRow(Constants::COLL_LEARNER_PK_GROUP, [
            'id' => $id,
            'aid' => $this->aid,
            'status' => static::ENABLED
        ], $fields);

        return $pk_group ?: [];
    }

    /**
     * 获取pk分组列表.
     *
     * @param int $learning_id 智能班级id
     * @param int $group_id 分组id
     * @param int $is_h5 是否是h5 0-不是 1-是
     * @return array
     */
    public function getPkGroups($learning_id, $group_id = -1, $is_h5 = 1, $rules = [])
    {
        $db = $this->getMongoMasterConnection();
        $condition = [
            'aid' => $this->aid,
            'learning_id' => $learning_id,
            'status' => static::ENABLED
        ];
        if ($group_id) $condition['id'] = ['$nin' => [0]];
        if ($rules) {
            foreach ($rules as $k => $val) {
                $condition[$k] = $val;
            }
        }

        $commonFilters = $this->commonFilters;
        if ($keyword = ArrayGet($commonFilters, 'keyword', '')) $condition['name'] = new Regex($keyword, 'im');

        $result = $db->fetchAll(Constants::COLL_LEARNER_PK_GROUP, $condition, 0, 0, [
            'created' => 1
        ], [
            'id' => 1,
            'learning_id' => 1,
            'name' => 1,
            'logo' => 1,
            'group_leader' => 1,
            'group_teacher' => 1,
            'score' => 1,
        ]);

        $lander_group = '未分组';
        if ($result) {
            $learner_model = new Learner($this->app);
            $group_info = $learner_model->getGroupRanking($learning_id);
            $group_info = array_column($group_info, null, 'id');
            foreach ($result as $k => $val) {
                if ($is_h5) {
                    $result[$k]['learner_num'] = 0;
                    $result[$k]['learners'] = [];
                    $learners = $learner_model->learnerNumInGroup($learning_id, $val['id']);
                    $result[$k]['learner_num'] = count($learners);
                    $result[$k]['learners'] = $learners;
                    $is_in_group = $this->getLanderGroup($learners);
                    if ($is_in_group) $lander_group = $val['name'];
                } else {
                    $result[$k]['learner_num'] = $learner_model->getLearnerNumInGroup($learning_id, $val['id']);
                }
                if (!isset($val['score'])) $result[$k]['score'] = 0;

                if (!isset($val['group_teacher'])) $result[$k]['group_teacher'] = [];
                $result[$k]['avg_rate'] = round($group_info[$val['id']]['total_rate'], 2) ?: 0;
            }
        }

        return [
            'list' => $result ? $result : [],
            'lander_group' => $lander_group
        ];
    }

    public function getPkGroupsForTask($learning_id, $task_id, $ignore_group_ids = [])
    {
        $db = $this->getMongoMasterConnection();
        $condition = [
            'aid' => $this->aid,
            'learning_id' => $learning_id,
            'status' => static::ENABLED
        ];
        if ($ignore_group_ids) $condition['id'] = ['$nin' => $ignore_group_ids];

        $result = $db->fetchAll(Constants::COLL_LEARNER_PK_GROUP, $condition, 0, 0, [
            'created' => 1
        ], [
            'id' => 1,
            'learning_id' => 1,
            'name' => 1,
            'logo' => 1,
        ]);

        // 获取已提交的小组数量
        $cond = [
            'aid' => $this->aid,
            'task_id' => $task_id,
            'source_id' => $learning_id,
            'status' => Task::STATUS_PASS
        ];
        if ($ignore_group_ids) $cond['group_id'] = ['$nin' => $ignore_group_ids];
        $others_submit = $db->fetchAll(Constants::COLL_TASK_RESULT, $cond, 0, 0, [], ['group_id' => 1]);
        $others_submit_group = array_column($others_submit, 'group_id');
        foreach ($result as $k => $v) {
            if (!in_array($v['id'], $others_submit_group)) {
                unset($result[$k]);
            }
        }

        return [
            'list' => array_values($result) ?: [],
            'others_submit' => count($others_submit_group)
        ];
    }

    //h5获取当前登陆者所在组
    protected function getLanderGroup($learners)
    {
        $res = 0;
        foreach ($learners as $k => $val) {
            if ($val['uid'] == $this->eid) {
                $res = 1;
                break;
            }
        }

        return $res;
    }

    /**
     * 获取pk排名.
     *
     * @param int $learning_id
     * @return array
     */
    public function getPkRanking($learning_id)
    {
        $groups = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNER_PK_GROUP, [
            'aid' => $this->aid,
            'id' => ['$nin' => [0]],
            'learning_id' => $learning_id,
            'status' => static::ENABLED
        ], 0, 0, [
            'created' => 1
        ], [
            'id' => 1,
            'name' => 1,
            'score' => 1,
        ]);
        $index_groups = array_column($groups, NULL, 'id');

        $result = [];
        if ($groups) {
            $learner_model = new Learner($this->app);
            $result = $learner_model->getGroupRanking($learning_id);
            foreach ($result as $k => $val) {
                $group_info = ArrayGet($index_groups, $val['id'], []);
                $val['name'] = ArrayGet($group_info, 'name', '');
                $val['score'] = ArrayGet($group_info, 'score', 0);
                $val['total_rate'] = bcdiv($val['total_rate'], 1, 2);
                $result[$k] = $val;
            }
        }

        return $result;
    }

    public function onPublish($id, $approvalStatus, $data){
        $this->log('班级发布审批流回传' . $id . ' - ' . $approvalStatus . ' - ' . json_encode($data));
        $new_data = [
            'approval_status' => $approvalStatus,
            'last_eid' => $this->eid,
        ];
        $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'id' => $id,
            'type' => $data['object_options']['type'],
            'status' => static::ENABLED
        ], [
            '$set' => array_merge($new_data, ['updated' => Mongodb::getMongoDate()])
        ]);

        $this->delRedis($id);
    }

    public function delRedis($id){
        $cache_key = $this->getRedisId($id);
        $this->getCacheInstance()->delete($cache_key);
    }

    //发布审批流回退
    public function approvalPublishBack($id, $approvalStatus, $data){
        $type = Constants::LEARNING_CLASS;
        if ($approvalStatus != 5) {
            error_log('班级发布审批流撤回' . json_encode($data) . '__id' . $id . '__approvalStatus' . $approvalStatus);
            return true;
        }
        $new_data = [
            'approval_status' => Constants::APPROVAL_STATUS_BACK,
            'last_eid' => $this->eid,
        ];
        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'id' => $id,
            'type' => $type,
            'status' => static::ENABLED
        ], [
            '$set' => array_merge($new_data, ['updated' => Mongodb::getMongoDate()])
        ]);

        $this->delRedis($id);
        return $result;
    }

    //发布走审批流
    public function approvalPublish($id, $type, $publish_approval_id, $publish_flow_data){
        $plan = $this->isExist($id, ['id'  => 1, 'name' => 1]);
        $new_data = [
            'publish_approval_id' => $publish_approval_id,
            'publish_flow_data' => $publish_flow_data,
            'approval_status' => Constants::APPROVAL_STATUS_DOING,
            'last_eid' => $this->eid,
        ];
        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'id' => $id,
            'type' => $type,
            'status' => static::ENABLED
        ], [
            '$set' => array_merge($new_data, ['updated' => Mongodb::getMongoDate()])
        ]);

        $this->delRedis($id);

        $this->triggerEvent("approvalworkflow:beign", [
            'approval_id' => $publish_approval_id,
            'object_id' => $id,
            'object_name' => ArrayGet($plan, 'name', ''),
            'flowData' => $publish_flow_data,
            'object_type' => 0,
            'object_options' => ['type' => $type],
        ], null, true, ["approvalworkflow"]);

        return $result;
    }

    /**
     * 发布智能班级.
     *
     * @param int $id .
     * @param int $type .
     * @return array
     */
    public function release($id, $type = Constants::LEARNING_CLASS, $notify = [])
    {
        $plan_info = $plan = $this->isExist($id, ['end_time' => 1, 'name' => 1, 'status' => 1, 'organization' => 1, 'last_eid' => 1, 'notify' => 1, 'publish_time' => 1, 'simple_plan' => 1]);
        if (!$plan) return true;

        if ($type == Constants::LEARNING_CLASS && $plan['simple_plan'] == self::CLASS_OFFLINE) {
            $modelLearningTask = new LearningTask($this->app);
            $simple_plan_task = $modelLearningTask->getAllLearningTask(['task_type' => Constants::TASK_OFFLINE_TEACHING, 'learning_id' => $id, 'type' => $type, 'simple_plan' => static::ENABLED]);
            if (!$simple_plan_task) return self::LEARNING_SIMPLE_NO_OFFLINE;
        }

        $publish_time = Mongodb::getMongoDate();
        $new_data = [
            'last_eid' => $this->eid,
            'notify' => $notify,
            'publish_time' => $publish_time,
            'status' => 2
        ];

        if ($type === Constants::LEARNING_TRAINING) {
            $new_data['review_status'] = 3;
        }

        $old_data = [
            'last_eid' => ArrayGet($plan, 'last_eid', 0),
            'status' => ArrayGet($plan, 'status', 1),
            'notify' => ArrayGet($plan, 'notify', []),
            'publish_time' => ArrayGet($plan, 'publish_time', ''),
        ];
        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'id' => $id,
            'aid' => $this->aid,
            'type' => $type,
            'status' => static::ENABLED
        ], [
            '$set' => array_merge($new_data, ['updated' => Mongodb::getMongoDate()])
        ]);

        if ($result) {
            $cache_key = $this->getRedisId($id);
            $this->getCacheInstance()->delete($cache_key);

            $this->updateLearningStatus($id, Learner::LEARNING_STATUS_PUBLISHED, $type, $publish_time);

            $this->learningPublishOperateTask($id, $type);

            //部门项目统计
            $organization = ArrayGet($plan, 'organization', []);
            $org_id = ArrayGet($organization, 'id', 0);
            if (in_array($type, [Constants::LEARNING_CLASS, Constants::LEARNING_PLAN]) && $org_id) {
                $sta_dept_proj_model = new StatisticsDeptProj($this->app);
                $sta_proj_type = $type == Constants::LEARNING_CLASS ? StatisticsDeptProj::TYPE_CLASS : StatisticsDeptProj::TYPE_PLAN;
                $sta_dept_proj_model->statisticsDeptProjQueue($org_id, 1, (string)$publish_time, $sta_proj_type);
            }

            //pc首页日历通知
            $learner_model = new Learner($this->app);
            $plan_info = $this->isExist($id, ['name' => 1, 'start_time' => 1, 'end_time' => 1]);
            $learners = $learner_model->isExist($id, $type, ['uid' => 1]);
            if ($learners) {
                $learner_ids = array_column($learners, 'uid');

                $this->urge($id, $type, $learner_ids, [
                    'name' => $plan_info['name'],
                    'started' => (string)$plan_info['start_time'],
                    'ended' => (string)$plan_info['end_time'],
                ]);
            }

            //自动通知
            if ($notify) {
                $notify_model = new LearningNotification($this->app);
                $notify_model->autoNotificationQueue($id, $type, [], '', $notify);
            }

            $OfflineAssessment = new OfflineAssessment($this->app);
            $OfflineAssessment->updatePublisStatus($id);

            if ($type == Constants::LEARNING_CLASS) {
                $this->triggerEvent("contribution:score:add", [
                    'aid' => $this->aid,
                    'eid' => $this->eid,
                    'action_no' => Constants::CONTRIBUTION_SCORE_CLASS_SET,
                    'relation_id' => $id,
                    'relation_title' => $plan['name'],
                ]);
            }
        }

        return $result ? true : false;
    }

    public function updateLearningStatus($learning_id, $learning_status, $type, $publish_time = '')
    {
        $learner_model = new Learner($this->app);
        $learner_model->updateLearningInfo($learning_id, [
            'learning_status' => $learning_status,
            'publish_time' => $publish_time
        ], $type, 1);

        $task_model = new LearningTask($this->app);
        $task_model->updateLearningStatus($learning_id, $type, Learner::LEARNING_STATUS_PUBLISHED);

        if ($type == Constants::LEARNING_CLASS) {
            $autonomy_detail_model = new AutonomyTaskDetail($this->app);
            $autonomy_detail_model->updateLearningInfo($learning_id, [
                'learning_status' => $learning_status
            ], $type);

            $interaction_model = new LearningInteractionDetail($this->app);
            $interaction_model->updateLearningInfo($learning_id, [
                'learning_status' => $learning_status,
                'publish_time' => $publish_time
            ], $type);

            $sign_detail_model = new ClassSignDetail($this->app);
            $sign_detail_model->updateLearningInfo($learning_id, [
                'learning_status' => $learning_status,
                'publish_time' => $publish_time
            ], $type);
        }
    }

    //推送消息流
    public function urge($learning_id, $type, $learners, $plan_info, $custom_title = '')
    {
        $model = new Notify($this->app);
        $started = ArrayGet($plan_info, 'started', '');
        $ended = ArrayGet($plan_info, 'ended', '');
        $title = ArrayGet($plan_info, 'name', '');
        $info = [
            'title' => $custom_title ? $custom_title : $title,
            'plan_type' => $type,
            'start' => $started,
            'end' => $ended,
        ];
        switch ($type) {
            case 0:
                $info['learning_id'] = $learning_id;
                $learn_type = 8;
                break;
            case 4:
                $info['object_id'] = $learning_id;
                $learn_type = 13;
                break;
            default:
                $info['learning_id'] = $learning_id;
                $learn_type = 3;
        }
        $model->push($learners, $learn_type, $info);
    }

    /**
     * 删除消息流
     * @param $learning_id
     * @return bool
     */
    public function deleteNews($learning_id, $type = 0, $eids = [])
    {
        if (!is_array($learning_id)) $learning_id = [$learning_id];
        $notify_model = new Notify($this->app);
        switch ($type) {
            case 0:
                $news_type = $notify_model::TYPE_LEARNING_PLAN;
                break;
            case 4:
                $news_type = $notify_model::TYPE_EXPATRIATE_TRAINING;
                break;
            default:
                $news_type = $notify_model::TYPE_LEARN;
        }
        foreach ($learning_id as $id) {
            $notify_model->deleteForObject($id, $news_type, $eids);

            $home_db = new HomeStream($this->app);
            $home_db->deleteForObject($id, $news_type, $this->eid);
        }

        return true;
    }

    public function listByEid($eid, $aid = 0)
    {
        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, [
            'aid' => $aid ?: $this->aid,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
            'uid' => $eid
        ], 0, 0, [], [
            'id' => 1
        ]);

        return $result ?: [];
    }

    /**
     * 更新员工信息，更新学习的创建者姓名
     *
     * @param int $eid 员工id.
     * @param int $display 员工姓名.
     */
    public function onEmployeeUpdated($eid, $aid, $info)
    {
        $learner = new Learner($this->app);
        $learner->onEmployeeUpdatedV2($eid, $aid, $info);

        $breakthrough = new Breakthrough($this->app);
        $breakthrough->onEmployeeUpdated($eid, $aid, $info);

        $learning_pack = new LearningPack($this->app);
        $learning_pack->onEmployeeUpdated($eid, $aid, $info);

        if ($display = ArrayGet($info, 'display', '')) {
            $plans = $this->listByEid($eid, $aid);
            if (!$plans) return true;
            $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
                'aid' => $aid ?: $this->aid,
                'uid' => $eid,
                'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]]
            ], [
                '$set' => [
                    'display' => $display
                ]
            ]);
            if ($result) {
                foreach ($plans as $val) {
                    $cache_key = $this->getRedisId($val['id']);
                    $this->getCacheInstance()->delete($cache_key);
                }
            }
        }
    }

    public function listByGroup($group_id, $aid = 0)
    {
        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, [
            'aid' => $aid ?: $this->aid,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
            '$or' => [
                ['add_groups' => ['$elemMatch' => ['$eq' => $group_id]]],
                ['del_groups' => ['$elemMatch' => ['$eq' => $group_id]]],
            ]
        ], 0, 0, [], [
            'id' => 1
        ]);

        return $result ?: [];
    }

    /**
     * 删除人群时调用接口
     *
     * @param int $group_id 员工id.
     * @param int $aid 员工姓名.
     * @return int
     */
    public function onGroupDeleted($group_id, $aid)
    {
        $plans = $this->listByGroup($group_id, $aid);
        if (!$plans) return true;
        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'aid' => $aid ?: $this->aid,
            '$or' => [
                ['add_groups' => ['$elemMatch' => ['$eq' => $group_id]]],
                ['del_groups' => ['$elemMatch' => ['$eq' => $group_id]]],
            ],
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]]
        ], [
            '$pull' => [
                'add_groups' => $group_id,
                'del_groups' => $group_id
            ]
        ]);
        if ($result) {
            foreach ($plans as $val) {
                $cache_key = $this->getRedisId($val['id']);
                $this->getCacheInstance()->delete($cache_key);
            }
        }

        return $result ?: false;
    }

    public function listByProject($source_id, $source_type = 1)
    {
        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
            'type' => 0,
            'source' => $source_type,
            'source_id' => $source_id
        ], 0, 0, [], [
            'id' => 1
        ]);

        return $result ?: [];
    }

    /**
     * 更新项目名称
     * @param int $source_id 项目id
     * @param string $source_name 项目名称
     * @param int $source_type 1-培训计划
     * @return bool
     */
    public function updateProjectName($source_id, $source_name, $source_type = 1)
    {
        $plans = $this->listByProject($source_id, $source_type);
        if (!$plans) return true;
        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
            'type' => 0,
            'source' => $source_type,
            'source_id' => $source_id
        ], [
            '$set' => [
                'source_name' => $source_name,
                'updated' => Mongodb::getMongoDate()
            ]
        ]);
        if ($result) {
            foreach ($plans as $val) {
                $cache_key = $this->getRedisId($val['id']);
                $this->getCacheInstance()->delete($cache_key);
            }
        }

        return $result ?: false;
    }

    /**
     * 通过项目id拿班级名称
     * @param int $source_id 项目id
     * @param int $source_type 项目类型 1-培训计划
     * @return array
     */
    public function getClassInfo($source_id, $source_type = 1)
    {
        $result = $this->getMongoMasterConnection()->fetchRow(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
            'type' => 0,
            'source' => $source_type,
            'source_id' => $source_id
        ], [
            'name' => 1
        ]);

        return $result ?: [];
    }

    /**
     * 查看项目是否已有关联的智能班级
     * @param int $source_id 项目id
     * @param int $source_type 项目类型 1-培训计划
     * @return int|boolean
     */
    public function isRelatedClass($source_id, $source_type = 1)
    {
        return $this->getMongoMasterConnection()->count(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
            'type' => 0,
            'source' => $source_type,
            'source_id' => $source_id
        ]);
    }

    /**
     * h5获取培训计划列表.
     *
     * @param string $keyword .
     * @param int $filter 0-全部 1-已报名已审核 2-已报名待审核 3-未报名
     * @param int $type 0-智能班级 3-学习计划 4-培训计划
     * @param null|\Key\Records\Pagination $pg
     * @param int $total
     * @return array
     */
    public function trainingListByLearner($keyword, $filter, $type, $pg, &$total = 0)
    {
        if (!$pg) {
            $pg = new \Key\Records\Pagination();
            $pg->setPage(0)->setItemsPerPage(0);
        }

        $cond = [
            'aid' => $this->aid,
            'type' => $type,
            'status' => 2
        ];
        if ($keyword) $cond['name'] = new Regex($keyword, 'im');

        $learner_model = new Learner($this->app);
        switch ($filter) {
            case 1:
                $is_examine = [Learner::EXAMINE_REVIEW_PASSES];
                break;
            case 2:
                $is_examine = [Learner::EXAMINE_NOT_REVIEW, Learner::EXAMINE_REVIEW_NOT_PASS];
                break;
            default:
                $is_examine = [Learner::EXAMINE_NOT_REVIEW, Learner::EXAMINE_REVIEW_PASSES, Learner::EXAMINE_REVIEW_NOT_PASS];
        }

        $res = $learner_model->getLearningIds($type, $is_examine);
        if (!$res && !in_array($filter, [0, 3])) {
            $total = 0;
            return [];
        }
        $plan_ids = ArrayGet($res, 'plan_ids', []);
        $plan_info = ArrayGet($res, 'info', []);
        //获取可以报名的班级列表时的条件
        if (in_array($filter, [0, 3])) {
            $enroll_or = $this->getApplicantCon();
            if ($filter == 0) {
                $cond['$or'] = [
                    ['$and' => [
                        ['is_applicant' => 1],
                        ['$or' => $enroll_or],
                    ]],
                    ['id' => ['$in' => $plan_ids]]
                ];
            } else {
                $cond['is_applicant'] = 1;
                $cond['$or'] = $enroll_or;
                $cond['id'] = ['$nin' => $plan_ids];
            }
        } else {
            $cond['id'] = ['$in' => $plan_ids];
        }

        $db = $this->getMongoMasterConnection();
        $total = $db->count(Constants::COLL_LEARNING_PLAN, $cond);
        $result = $db->fetchAll(Constants::COLL_LEARNING_PLAN, $cond,
            $pg->getOffset(), $pg->getItemsPerPage(),
            [
                'created' => -1
            ], [
                'id' => 1,
                'name' => 1,
                'start_time' => 1,
                'end_time' => 1,
                'display' => 1,
                'uid' => 1,
                'status' => 1,
                'is_applicant' => 1,
                'training_hours' => 1,
                'credit' => 1,
                'class_address' => 1,
                'created' => 1
            ]);

        if ($result) {
            foreach ($result as $k => $val) {
                $info = ArrayGet($plan_info, $val['id'], []);
                $result[$k]['is_applicanted'] = ArrayGet($info, 'is_applicant', 0);
                $result[$k]['is_examine'] = ArrayGet($info, 'is_examine', 0);
                $result[$k]['start_time'] = (string)$val['start_time'];
                $result[$k]['end_time'] = (string)$val['end_time'];
                $result[$k]['created'] = (string)$val['created'];
            }
        }

        return $result ?: [];
    }

    /**
     * h5获取培训计划列表过滤条件下的数量.
     *
     * @param string $keyword .
     * @param int $filter 0-全部 1-已报名已审核 2-已报名待审核 3-未报名
     * @param int $type 0-智能班级 3-学习计划 4-培训计划
     * @return boolean|int
     */
    public function trainingFilterNum($keyword, $filter, $type = 4)
    {
        $condition = [
            'aid' => $this->aid,
            'type' => $type,
            'status' => 2
        ];
        if ($keyword) $condition['name'] = new Regex($keyword, 'im');

        $learner_model = new Learner($this->app);
        switch ($filter) {
            case 1:
                $is_examine = [Learner::EXAMINE_REVIEW_PASSES];
                break;
            case 2:
                $is_examine = [Learner::EXAMINE_NOT_REVIEW, Learner::EXAMINE_REVIEW_NOT_PASS];
                break;
            default:
                $is_examine = [Learner::EXAMINE_NOT_REVIEW, Learner::EXAMINE_REVIEW_PASSES, Learner::EXAMINE_REVIEW_NOT_PASS];
        }

        $res = $learner_model->getLearningIds($type, $is_examine);
        if (!$res && !in_array($filter, [0, 3])) return 0;

        $plan_ids = ArrayGet($res, 'plan_ids', []);
        //获取可以报名的班级列表时的条件
        if (in_array($filter, [0, 3])) {
            if ($filter == 0) {
                $condition['$or'] = [
                    ['$and' => [
                        ['is_applicant' => 1],
                        ['$or' => [
                            ['applicant_auth' => 0],
                            ['appoint_employee' => ['$elemMatch' => ['$eq' => $this->eid]]]
                        ]],
                    ]],
                    ['id' => ['$in' => $plan_ids]]
                ];
            } else {
                $condition['is_applicant'] = 1;
                $condition['$or'] = [
                    ['applicant_auth' => 0],
                    ['appoint_employee' => ['$elemMatch' => ['$eq' => $this->eid]]]
                ];
                $condition['id'] = ['$nin' => $plan_ids];
            }
        } else {
            $condition['id'] = ['$in' => $plan_ids];
        }

        return $this->getMongoMasterConnection()->count(Constants::COLL_LEARNING_PLAN, $condition);
    }

    /**
     * 过滤已结束班级
     * @param array $ids 班级id
     * @return array
     */
    public function filterEndClass($ids)
    {
        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
            'type' => ['$in' => [Constants::LEARNING_CLASS, Constants::LEARNING_PLAN]],
            'is_end' => self::NOT_END,
            'id' => ['$in' => $ids]
        ], 0, 0, [], [
            'id' => 1
        ]);

        $learning_ids = [];
        if ($result) $learning_ids = array_column($result, 'id');

        return $learning_ids;
    }

    /**
     * 过滤已结束班级
     * @param array $ids 班级id
     * @return array
     */
    public function endLearningList($filters = [], $type = Constants::LEARNING_CLASS, $pg = null, $fields = [])
    {
        if (!$pg) {
            $pg = new \Key\Records\Pagination();
            $pg->setPage(0)->setItemsPerPage(0);
        }
        $aid = ArrayGet($filters, 'aid', 0);
        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, [
            'aid' => $aid ?: $this->aid,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
            'type' => $type,
            'is_end' => 1
        ], $pg->getOffset(), $pg->getItemsPerPage(), [], $fields ?: [
            'id' => 1
        ]);

        return $result ?: [];
    }

    protected function learningOver($id, $type = Constants::LEARNING_CLASS)
    {
        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
            'type' => $type,
            'is_end' => self::NOT_END,
            'id' => $id
        ], [
            '$set' => [
                'last_eid' => $this->eid,
                'is_end' => self::ENDED,
                'over_time' => Mongodb::getMongoDate(),
                'updated' => Mongodb::getMongoDate()
            ]
        ]);

        return $result ?: false;
    }

    /**
     * 结束班级
     *
     * @param int $id .
     * @param int $type .
     * @return boolean|int
     */
    public function endClass($id, $type = Constants::LEARNING_CLASS)
    {
        $result = $this->learningOver($id, $type);

        if ($result) {
            $cache_key = $this->getRedisId($id);
            $this->getCacheInstance()->delete($cache_key);

            $learner_model = new Learner($this->app);
            $learner_model->updateLearningInfo($id, [
                'learning_status' => Learner::LEARNING_STATUS_END
            ], $type);

            $task_model = new LearningTask($this->app);
            $task_model->updateLearningStatus($id, $type, Learner::LEARNING_STATUS_END);

            if (in_array($type, [Constants::LEARNING_CLASS, Constants::LEARNING_PLAN])) {
                //添加附加分queue
                $learner_model = new Learner($this->app);
                $learner_model->bonusPointsQueue($id, $type);

                //终止周期性通知
                $notification_period_model = new NotificationPeriod($this->app);
                $module_type = $type == Constants::LEARNING_CLASS ? NotificationPeriod::MODULE_TYPE_CLASS : NotificationPeriod::MODULE_TYPE_PLAN;
                $notification_period_model->stopSendByModule($id, $module_type);

                //培训台账queue
                $train_standing_book_model = new TrainStandingBook($this->app);
                $train_standing_book_model->trainStandingBookQueue($id, $type);
            } else if ($type == Constants::LEARNING_TRAINING) {
                //添加学分
                $plan = $this->isExist($id, ['credit' => 1, 'payer_type' => 1]);
                $credit = ArrayGet($plan, 'credit', 0);
                if ($credit) {
                    $this->learningTrainingCreditQueue($id, $credit);
                }

                $payer_type = ArrayGet($plan, 'payer_type', 1);
                // 统计实际费用
                if ($payer_type === 2) {
                    $training_Model = new \App\Models\Training\ExpatriateTraining($this->app);
                    $training_Model->staPayNum($id);
                    $cache_key = $this->getRedisId($id);
                    $this->getCacheInstance()->delete($cache_key);
                }
            }

            $learner_rate_model = new LearnerRate($this->app);
            $learner_rate_model->planRateQueue($id, $type);

            if ($type == Constants::LEARNING_CLASS) {
                $sta_offline_teaching_model = new StatisticsOfflineTeaching($this->app);
                $sta_offline_teaching_model->updateLearningInfo($id, ['learning_status' => Learner::LEARNING_STATUS_END]);

                $autonomy_detail_model = new AutonomyTaskDetail($this->app);
                $autonomy_detail_model->updateLearningInfo($id, [
                    'learning_status' => Learner::LEARNING_STATUS_END
                ], $type);

                $interaction_model = new LearningInteractionDetail($this->app);
                $interaction_model->updateLearningInfo($id, [
                    'learning_status' => Learner::LEARNING_STATUS_END
                ], $type);

                $sign_detail_model = new ClassSignDetail($this->app);
                $sign_detail_model->updateLearningInfo($id, [
                    'learning_status' => Learner::LEARNING_STATUS_END
                ], $type);

                $report_model = new LearningReport($this->app);
                $report_model->updateByLearning($id);

                $exists = $this->isExist($id);
                $is_point_score = $exists['app_setting']['is_point_score'];
                if ($is_point_score) {
                    $this->triggerEvent('class:point:convert', [
                        'aid' => $this->aid,
                        'learning_id' => $id,
                        'learning_type' => Constants::LEARNING_CLASS
                    ]);
                }
            }

            //评估
            $assess_model = new Assessment($this->app);
            $assess_model->createStatisticsByLearningMq($id, $type);

            //surn
            $public_model = new PublicLearningTasks($this->app);
            $public_model->endTaskMq($id, $type);

            //前n%学完的学员奖励学分
            if ($type == Constants::LEARNING_PLAN) $learner_model->LearningEndFirstRewardCreditQueue($id, $type);
        }

        return $result ?: false;
    }

    public function deleteLearningPlanCache($id, $aid = 0)
    {
        $cache_key = $this->getRedisId($id, $aid);
        $this->getCacheInstance()->delete($cache_key);
    }

    //结束外派培训加学分
    public function learningTrainingCreditQueue($learning_id, $credit)
    {
        $message = new QueueMessage($this->aid, $this->eid);
        $message->setPairs([
                'learning_id' => $learning_id,
                'credit' => $credit
            ]
        );

        $queue = new BaseQueue($this->app);
        $queue->queuePublish($queue::QUEUE_LEARNING_TRAINING_CREDIT, $message);
        return true;
    }

    /**
     * 更新签到设置
     *
     * @param float $late_hours 迟到时间设置
     * @param int $is_appointed 是否定点签到 0-否 1-是
     * @param float $distance 定点签到的距离设置
     * @return boolean|int
     */
    public function signSetting($learning_id, $late_hours, $is_appointed, $distance)
    {
        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
            'type' => Constants::LEARNING_CLASS,
            'is_end' => self::NOT_END,
            'id' => $learning_id
        ], [
            '$set' => [
                'late_hours' => $late_hours,
                'is_appointed' => $is_appointed,
                'default_distance' => $distance,
            ]
        ]);
        if ($result) {
            $cache_key = $this->getRedisId($learning_id);
            $this->getCacheInstance()->delete($cache_key);
        }

        return $result ?: false;
    }

    //查看指定学习模块儿的课程完成率是否同步
    public function planCourseRateIsSynchro($learning_id, $type = Constants::LEARNING_CLASS)
    {
        $pack_arr = [Constants::LEARNING_PACK, Constants::LEARNING_COURSE_PACK, Constants::LEARNING_SPECIAL_TOPIC_PACK, Constants::LEARNING_TEACHING, Constants::LEARNING_TRAINING];
        if (in_array($type, $pack_arr)) {
            $plan_model = new LearningPack($this->app);
            $plan = $plan_model->isExist($learning_id, ['course_rate' => 1], $type);
            if ($type == Constants::LEARNING_TEACHING) {
                $learner_model = new LearnerTeaching($this->app);
                $learner = $learner_model->viewTeaching($learning_id, ['course_rate' => 1], $type);
                if (isset($learner['course_rate'])) {
                    $plan = ['course_rate' => ArrayGet($learner, 'course_rate', 0)];
                }
            }
        } else if ($type == Constants::LEARNING_BREAKTHROUGH) {
            $plan_model = new Breakthrough($this->app);
            $plan = $plan_model->isExist($learning_id, ['course_rate' => 1]);
        } else if ($type == Constants::RETRAINING) {
            $plan_model = new RetrainingLearner($this->app);
            $plan = $plan_model->isExists($learning_id, $this->eid, ['course_rate' => 1]);
        } else {
            $plan = $this->isExist($learning_id, ['course_rate' => 1]);
        }

        return ArrayGet($plan, 'course_rate', 1);
    }

    /**
     * 获取未完成的学习计划或培训班数量
     * @param int $type 0-智能班级 1-学习包 2-闯关 3-学习计划.
     *
     * @return int
     */
    public function getUnfinishedNum($type = Constants::LEARNING_CLASS)
    {
        $learner_model = new Learner($this->app);
        $res = $learner_model->getUnfinishedPlan($type);
        $plan_ids = array_column($res, 'learning_id');

        return $this->getMongoMasterConnection()->count(Constants::COLL_LEARNING_PLAN, [
            'id' => ['$in' => $plan_ids],
            'aid' => $this->aid,
            'type' => $type,
            'is_end' => 0,
            'status' => 2
        ]);
    }

    /**
     * 获取所有学习计划或培训班数量
     *
     * @param int $type 0-智能班级 1-学习包 2-闯关 3-学习计划.
     * @return int
     */
    public function getPlanNum($type = 3)
    {
        $learner_model = new Learner($this->app);
        $res = $learner_model->getLearningIds($type, [Learner::EXAMINE_REVIEW_PASSES], 0, [], 0, $type == 3 ? ['type' => ['$in' => [1, 3]]] : []);

        $plan_ids = ArrayGet($res, 'plan_ids', []);
        $condition = [
            'id' => ['$in' => $plan_ids],
            'aid' => $this->aid,
            'type' => $type,
            'status' => 2
        ];
        if ($type == Constants::LEARNING_PLAN) return count($plan_ids);

        return $this->getMongoMasterConnection()->count(Constants::COLL_LEARNING_PLAN, $condition);
    }

    //获取我能审核的班级
    public function needExamineClass($is_manager = 0, $is_superior = 0)
    {
        $is_examine = [1];
        if ($is_manager) $is_examine[] = 2;
        if ($is_superior) $is_examine[] = 3;
        $cond = [
            'aid' => $this->aid,
            'type' => Constants::LEARNING_CLASS,
            'is_end' => self::NOT_END,
            'is_applicant' => 1,
            'is_examine' => ['$in' => $is_examine],
            '$or' => [
                ['uid' => $this->eid],
                ['monitor' => ['$elemMatch' => ['id' => $this->eid, 'type' => 0]]]
            ],
            'status' => 2
        ];
        $employee_model = new Employee($this->app);
        $employee = $employee_model->view($this->eid, $this->aid, ['groups' => 1]);
        $groups = ArrayGet($employee, 'groups', []);
        if ($groups) {
            $cond['$or'][] = ['monitor' => ['$elemMatch' => ['id' => ['$in' => $groups], 'type' => 3]]];
        }
        $result = $this->getMongoMasterConnection()->fetchAll(Constants::LEARNING_CLASS, $cond, [
            'id' => 1
        ]);

        return $result ? array_column($result, 'id') : [];
    }

    //删除项目下的所有班级
    public function delByProject($ids, $type = Constants::LEARNING_CLASS)
    {
        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
            'id' => ['$in' => $ids],
            'type' => $type
        ], [
            '$set' => [
                'last_eid' => $this->eid,
                'status' => static::DISABLED,
                'updated' => Mongodb::getMongoDate()
            ]
        ]);
        if ($result) {
            $task_model = new LearningTask($this->app);
            foreach ($ids as $id) {
                $task_model->delGroup($id, 0, $type);
            }

            $learner = new Learner($this->app);
            $learner->delLearner($ids, $type, [], 0, 2);

            $class_sign = new ClassSign($this->app);
            $class_sign->remove($ids);

            //删除日历消息流
            $this->deleteNews($ids, $type);

            //删除未发出的通知（自动、/手动）
            if ($type == Constants::LEARNING_PLAN) {
                $learning_notify = new LearningNotification($this->app);
                foreach ($ids as $id) {
                    $learning_notify->delNotifyService($id, $type);
                }
            }

            //删除班级人员pk分组
            $this->delPkGroupByClass($ids, 1);
            foreach ($ids as $id) {
                $autonomy_detail_model = new AutonomyTaskDetail($this->app);
                $autonomy_detail_model->autonomyDelTaskQueue($id, AutonomyTaskDetail::TASK_CLASS);
                $autonomy_detail_model->delLearner($id, [], $type);

                $cache_key = $this->getRedisId($id);
                $this->getCacheInstance()->delete($cache_key);
            }
            $sta_offline_model = new StatisticsOfflineTeaching($this->app);
            $sta_offline_model->removeLearning($ids, $type);
            $sta_offline_learner_model = new StatisticsOfflineTeachingLearner($this->app);
            $sta_offline_learner_model->removeLearning($ids, $type);
        }

        return $result ?: false;
    }

    /**
     * 更新学员人数.
     *
     * @param int $id 班级id.
     * @param int $num 人数.
     * @return boolean|int
     */
    public function updateLearnerNum($id, $num)
    {
        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'id' => $id,
            'aid' => $this->aid,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]]
        ], [
            '$inc' => [
                'learner_num' => $num
            ]
        ]);
        if ($result) {
            $cache_key = $this->getRedisId($id);
            $this->getCacheInstance()->delete($cache_key);
        }

        return $result ?: false;
    }

    //可调班级条件
    protected function shiftClassesFilters($project_id, $filters = [])
    {
        //获取已报名的班级（已审核 和 待审核）
        $enrolledLearningFilters = [
            'is_examines' => [Learner::EXAMINE_NOT_REVIEW, Learner::EXAMINE_REVIEW_PASSES]
        ];
        $learning_ids = $this->enrolledLearnings($list, $enrolledLearningFilters);

//        //筛选已存在的班级
//        $learning_ids = [];
//        $learner_model = new Learner($this->app);
//        $planFilters = [
//            'type' => Constants::LEARNING_CLASS,
//            'project_id' => $project_id,
//        ];
//        if ($ignore_ids) $planFilters['ignore_ids'] = $ignore_ids;
//        $classes = $learner_model->getPlanListByUid($this->eid, $planFilters, null, ['learning_id' => 1]);
//        if ($classes) $learning_ids = array_column($classes, 'learning_id');
        $employee_model = new Employee($this->app);
        $employee = $employee_model->view($this->eid, $this->aid, [
            'job_level_id' => 1,
            'department_id' => 1,
            'position_id' => 1,
            'groups' => 1,
        ], Employee::ENABLED, 1);
        $dept_model = new Department($this->app);
        $depts = $dept_model->getMyAndParentDepartment($employee['department_id']);
        $dept_ids = array_column($depts, 'id');
        $position_id = ArrayGet($employee, 'position_id', 0);
        $groups = ArrayGet($employee, 'groups', []);
        $job_level_id = ArrayGet($employee, 'job_level_id', 0);
        //0-人 1-岗位 2-部门 3-人群
        $cond = [
            'aid' => $this->aid,
            'status' => 2,
            'project_id' => $project_id,
            'is_end' => 0,
            'learner_type' => ['$nin' => [1]],
            'id' => ['$nin' => $learning_ids],
            'type' => Constants::LEARNING_CLASS,
            'is_applicant' => 1,
            '$or' => [
                ['shift_range.is_all' => 2],
                ['shift_range.is_all' => 1, 'shift_range.range' => ['$elemMatch' => ['type' => self::APPLIED_RANGE_EMPLOYEE, 'id' => $this->eid]]],
                ['shift_range.is_all' => 1, 'shift_range.range' => ['$elemMatch' => ['type' => self::APPLIED_RANGE_POSITION, 'id' => $position_id]]],
                ['shift_range.is_all' => 1, 'shift_range.range' => ['$elemMatch' => ['type' => self::APPLIED_RANGE_DEPARTMENT, 'id' => ['$in' => $dept_ids], 'children_included' => 1]]],
                ['shift_range.is_all' => 1, 'shift_range.range' => ['$elemMatch' => ['type' => self::APPLIED_RANGE_DEPARTMENT, 'id' => $employee['department_id'], 'children_included' => 0]]],
                ['shift_range.is_all' => 1, 'shift_range.range' => ['$elemMatch' => ['type' => self::APPLIED_RANGE_GROUP, 'id' => ['$in' => $groups]]]],
                ['shift_range.is_all' => 1, 'shift_range.range' => ['$elemMatch' => ['type' => self::APPLIED_RANGE_JOB_LEVEL, 'id' => $job_level_id]]],
            ]
        ];

        if ($keyword = ArrayGet($filters, 'keyword', '')) {
            $cond['name'] = new Regex($keyword, 'im');
        }
        $filter = ArrayGet($filters, 'filter', 0);
        switch ($filter) {
            case 1:
                //获取未开始学习计划的数量(已发布,当前时间<开始时间) 外派培训--未开始.
                $cond['is_end'] = 0;
                $cond['status'] = 2;
                $cond['start_time'] = ['$gt' => Mongodb::getMongoDate(time())];
                break;
            case 2:
                //获取未完成学习计划的数量(已发布,开始时间<当前时间<结束时间,完成率不到100%) 外派培训--进行中.
                $cond['status'] = 2;
                $cond['is_end'] = 0;
                $cond['start_time'] = ['$lte' => Mongodb::getMongoDate(time())];
                $cond['end_time'] = ['$gt' => Mongodb::getMongoDate(time())];
                break;
            case 3:
                //获取已完成学习计划的数量(已发布,开始时间<当前时间<结束时间,完成率100%).
                $cond['status'] = 2;
                $cond['is_end'] = 0;
                $cond['start_time'] = ['$lt' => Mongodb::getMongoDate(time())];
                $cond['finished_rate'] = 100;
                break;
            case 4:
                //获取已延期学习计划的数量(已发布,当前时间>结束时间,完成率<100%)  外派培训--已延期.
                $cond['status'] = 2;
                $cond['is_end'] = 0;
                $cond['end_time'] = ['$lt' => Mongodb::getMongoDate(time())];
                $cond['finished_rate'] = ['$lt' => 100];
                break;
            case 5:
                //获取未发布学习计划的数量  外派培训--草稿
                $cond['learning_status'] = self::LEARNING_UNPUBLISH;
                $cond['status'] = static::ENABLED;
                break;
            case 6:
                //已结束  外派培训--已结束
                $cond['is_end'] = 1;
                break;
            case 7:
                //待提交  智能班级-需要学员确认
                $cond['learner_confirm'] = 1;
                $cond['learning_status'] = self::LEARNING_UNSUBMIT;
            case 8:
                //已发布  已发布,未手动结束
                $cond['status'] = self::STATUS_PUBLISHED;
                $cond['is_end'] = self::NOT_END;
                break;
        }
        return $cond;
    }

    /**
     * 获取可调班级.
     *
     * @param array $filters
     * @param int $project_id
     * @param null|\Key\Records\Pagination $pg
     * @return array
     */
    public function shiftClasses($project_id, $filters = [], $pg = null)
    {
        if (!$pg) {
            $pg = new Pagination();
            $pg->setPage(0)->setItemsPerPage(0);
        }

        $cond = $this->shiftClassesFilters($project_id, $filters);
        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, $cond,
            $pg->getOffset(),
            $pg->getItemsPerPage());

        return $result ?: [];
    }

//    //可调班级总数
//    public function shiftClassesTotal($project_id, $filters)
//    {
//        $cond = $this->shiftClassesFilters($project_id, $filters);
//        $result = $this->getMongoMasterConnection()->count(Constants::COLL_LEARNING_PLAN, $cond);
//
//        return $result ?: 0;
//    }

    public function listByProjectId($project_id, $status = 0)
    {
        if (!$status) $status = ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]];
        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'type' => Constants::LEARNING_CLASS,
            'status' => $status,
            'project_id' => $project_id
        ], 0, 0, [], [
            'id' => 1
        ]);

        return $result ?: [];
    }

    //更新班级中多班次的信息
    public function updatePlanProject($learning_id, $type = Constants::LEARNING_CLASS, $new_data)
    {
        if (!$learning_id || !$new_data) return true;
        $history = $this->isExist($learning_id);
        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'id' => $learning_id,
            'type' => $type,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]]
        ], [
            '$set' => $new_data
        ]);

        if (!$result) return false;

        $cache_key = $this->getRedisId($learning_id);
        $this->getCacheInstance()->delete($cache_key);

        //操作日志
        $this->setNewData($new_data)
            ->setOldData($history)
            ->setOp(self::OP_UPDATE)
            ->describe($learning_id, $history['name'], '多班次信息修改')
            ->setOpModule('class')
            ->saveOpLog();


        if ($new_data['project_id']) {
            //修改的是多班次的对应关系
            $modelLearningGroup = new LearningGroup($this->app);
            $modelLearningGroup->setFilter(['learning_id' => $learning_id, 'type' => $type])->updateByFilters(['project_id' => $new_data['project_id']]);

            $modelLearningTask = new LearningTask($this->app);
            $modelLearningTask->setFilter(['learning_id' => $learning_id, 'type' => $type])->updateByFilters(['project_id' => $new_data['project_id']]);

            $modelNewLearner = new NewLearner($this->app);
            $modelNewLearner->setFilter([OldLearner::FILTER_FIELD_LEARNING_ID => $learning_id])->updateAppointData(['project_id' => $new_data['project_id'], 'project_name' => $new_data['project_name']]);
        }

        return true;
    }

    //更新项目
    public function updateProject($project_id, $new_data)
    {
        $plans = $this->listByProjectId($project_id);
        if (!$plans) return true;
        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'type' => Constants::LEARNING_CLASS,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
            'project_id' => $project_id
        ], [
            '$set' => $new_data
        ]);
        if ($result) {
            foreach ($plans as $val) {
                $cache_key = $this->getRedisId($val['id']);
                $this->getCacheInstance()->delete($cache_key);
            }
            $learner_model = new Learner($this->app);
            $learner_model->updateByProjectId($project_id, ['project_name' => $new_data['project_name']]);
        }

        return $result;
    }

    protected function getYesterdayTime()
    {
        $begin = mktime(0, 0, 0, date('m'), date('d') - 1, date('Y'));
        $ended = mktime(0, 0, 0, date('m'), date('d'), date('Y')) - 1;
        return [
            'start' => $begin,
            'end' => $ended
        ];
    }

    //大屏获取已发布的智能班级/学习计划数量
    public function learningNum($filters = [], $type = Constants::LEARNING_CLASS)
    {
        $cond = [
            'aid' => $this->aid,
            'status' => 2,
            'type' => $type
        ];
        $learning_status = ArrayGet($filters, 'learning_status', '');
        switch ($learning_status) {
            case 'published':
                $cond['status'] = self::STATUS_PUBLISHED;
                break;
            case 'removed':
                $cond['status'] = static::DISABLED;
                $yesterday_time = $this->getYesterdayTime();
                $cond['updated'] = [
                    '$gte' => Mongodb::getMongoDate($yesterday_time['start']),
                    '$lte' => Mongodb::getMongoDate($yesterday_time['end']),
                ];
                break;
        }
        $publish_start_time = ArrayGet($filters, 'publish_start_time', '');
        $publish_end_time = ArrayGet($filters, 'publish_end_time', '');
        if ($publish_start_time && $publish_end_time) {
            $cond['publish_time'] = [
                '$gte' => Mongodb::getMongoDate($publish_start_time),
                '$lte' => Mongodb::getMongoDate($publish_end_time)
            ];
        } else if ($publish_start_time) {
            $cond['publish_time'] = ['$gte' => Mongodb::getMongoDate($publish_start_time)];
        } else if ($publish_end_time) {
            $cond['publish_time'] = ['$lte' => Mongodb::getMongoDate($publish_end_time)];
        }
        $result = $this->getMongoMasterConnection()->count(Constants::COLL_LEARNING_PLAN, $cond);

        return $result ?: 0;
    }

    /**
     * 获取不包含某任务的学习模块儿.
     *
     * @param int $task_id
     * @param int $task_type
     * @param int $type
     * @param null|\Key\Records\Pagination $pg
     * @return array
     */
    public function notIncludeTaskPlans($task_id, $task_type = Constants::TASK_COURSE, $type = Constants::LEARNING_AUTONOMY, $pg = null)
    {
        //获取不包含该课程的计划id
        $task_model = new LearningTask($this->app);
        $learnings = $task_model->includeTaskPlans($task_id, $task_type, $type, $this->eid);
        $learning_ids = [];
        if ($learnings) $learning_ids = array_column($learnings, 'learning_id');

        if (!$pg) {
            $pg = new Pagination();
            $pg->setPage(0)->setItemsPerPage(0);
        }
        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
            'type' => $type,
            'id' => ['$nin' => $learning_ids],
            'uid' => $this->eid
        ], $pg->getOffset(), $pg->getItemsPerPage(), [], [
            'id' => 1,
            'name' => 1
        ]);

        return $result ?: [];
    }

    protected function squareListFilters($filters, $type)
    {
        //是否包含待审核数据 0-不包含 1-包含
        $is_examine = ArrayGet($filters, 'is_examine', 1);
        $filter = ArrayGet($filters, 'filter', 0);
        $learner_model = new Learner($this->app);
        $examine_val = $is_examine ? [1] : [0, 1];
        $res = $learner_model->getLearningIds($type, $examine_val, $filter, [], 0, [], null, $total);

        $plan_ids = ArrayGet($res, 'plan_ids', []);
        $ignore_ids = ArrayGet($filters, 'ignore_ids', []);
        $ids = array_merge($plan_ids, $ignore_ids);

        $time = time() * 1000;
        $cond = [
            'aid' => $this->aid,
            'type' => $type,
            'status' => 2,
            'is_applicant' => 1,
            'end_time' => ['$gt' => Mongodb::getMongoDate()],
            'application_end_time' => ['$gte' => (string)$time],
            'id' => ['$nin' => $ids]
        ];
        if ($keyword = ArrayGet($filters, 'keyword', '')) {
            $cond['name'] = new Regex($keyword, 'im');
        }

        //获取可以报名的班级列表时的条件
        if ($type == Constants::LEARNING_CLASS) {
            $cond['is_end'] = self::NOT_END;

            $enroll_or = $this->getApplicantCon();
            if (isset($cond['$or'])) {
                $cond['$or'] = array_merge($cond['$or'], $enroll_or);
            } else {
                $cond['$or'] = $enroll_or;
            }
        }

        return $cond;
    }

    /**
     * 班级广场列表.
     *
     * @param \App\Records\ClassSquareFilters $filters .
     * @param int $type .
     * @param array $sort .
     * @param array $fields .
     * @return array
     */
    public function squareList($filters, $type = Constants::LEARNING_CLASS, $pg = null, $sort = [], $fields = [])
    {
        if (!$pg) {
            $pg = new Pagination();
            $pg = $pg->setPage(0)->setItemsPerPage(0);
        }
        $cond = $this->squareListFilters($filters, $type);

        $sortField = ['is_set_top' => -1, 'created' => -1];
        if ($sort) $sortField = array_merge(['is_set_top' => -1], $sort);

        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, $cond,
            $pg->getOffset(), $pg->getItemsPerPage(),
            $sortField, $fields ?: [
                'id' => 1,
                'name' => 1,
                'start_time' => 1,
                'end_time' => 1,
                'is_set_top' => 1,
                'display' => 1,
                'uid' => 1,
                'status' => 1,
                'created' => 1,
                'learner_num' => 1
            ]);

        if ($result) {
//            $plan_ids = array_column($result, 'id');
//            $learner_model = new Learner($this->app);
//            $learners = $learner_model->getLearningsLearnerNum($plan_ids, $type);
            foreach ($result as $k => $val) {
//                $val['learner_num'] = ArrayGet($learners, $val['id'], 0);
                $val['start_time'] = (string)$val['start_time'];
                $val['end_time'] = (string)$val['end_time'];
                $val['created'] = (string)$val['created'];
                $result[$k] = $val;
            }
        }

        return $result ?: [];
    }

    /**
     * 班级广场列表.
     *
     * @param \App\Records\ClassSquareFilters $filters .
     * @param int $type .
     * @return boolean|int
     */
    public function squareTotal($filters, $type = Constants::LEARNING_CLASS)
    {
        $cond = $this->squareListFilters($filters, $type);
        $result = $this->getMongoMasterConnection()->count(Constants::COLL_LEARNING_PLAN, $cond);

        return $result ?: 0;
    }

    /**
     * 修改签到完成率规则设置.
     *
     * @param int $learning_id 班级id.
     * @param int $rate_setting 1-请假算进度 2-请假不算进度.
     * @param int $type .
     * @return boolean|int
     */
    public function updateSignRateSetting($learning_id, $rate_setting, $type = Constants::LEARNING_CLASS)
    {
        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
            'id' => $learning_id,
            'type' => $type
        ], [
            '$set' => [
                'sign_settings.rate_setting' => $rate_setting,
                'updated' => Mongodb::getMongoDate()
            ]
        ]);
        if ($result) {
            $cache_key = $this->getRedisId($learning_id);
            $this->getCacheInstance()->delete($cache_key);

            $sign_model = new ClassSign($this->app);
            $signs = $sign_model->getList($learning_id);
            if ($signs) {
                foreach ($signs as $k => $val) {
                    if ($offline_teachings = ArrayGet($val, 'offline_teachings', [])) {
                        $sign_model->signUpdateQueue($learning_id, $val['id'], $offline_teachings, 3);
                    }
                }
            }
        }

        return $result ?: [];
    }

    /**
     * 查看签到规则设置.
     *
     * @param int $learning_id 班级id.
     * @param int $type .
     * @return boolean|int
     */
    public function viewSignSettings($learning_id, $type = Constants::LEARNING_CLASS)
    {
        $result = $this->getMongoMasterConnection()->fetchRow(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
            'id' => $learning_id,
            'type' => $type
        ], [
            'sign_settings' => 1
        ]);
        if ($result) {
            $cache_key = $this->getRedisId($learning_id);
            $this->getCacheInstance()->delete($cache_key);
        }

        return ArrayGet($result, 'sign_settings', []);
    }

    /**
     * 更新报名信息.
     *
     * @param int $id 班级id.
     * @param \App\Records\LearningEnroll $record .
     * @return boolean|int
     */
    public function updateEnrollInfo($id, $record)
    {
        $history = $this->isExist($id);
        $new_data = $record->toArray();
        $new_data['last_eid'] = $this->eid;
        $new_data['updated'] = Mongodb::getMongoDate();

        $new_data['app_setting'] = $history['app_setting'];
        $new_data['app_setting']['is_enroll'] = $new_data['is_applicant'];

        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'id' => $id,
            'type' => Constants::LEARNING_CLASS,
            'aid' => $this->aid,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]]
        ], [
            '$set' => $new_data
        ]);

        if ($result) {
            $cache_key = $this->getRedisId($id);
            $this->getCacheInstance()->delete($cache_key);

            $learner_model = new Learner($this->app);

            //更新learner表的时间
            $update_info = [
                'enroll_started' => $new_data['enroll_setting']['enroll_started'],
                'enroll_ended' => $new_data['enroll_setting']['enroll_ended'],
                'enroll_range' => $new_data['applied_range'],
                'enroll_extra_con' => $new_data['enroll_extra_con'],
                'enroll_auth' => $new_data['applicant_auth'],
                'need_examine' => ArrayGet($new_data, 'is_examine', 0),
                'examine_range' => $new_data['examine_range'],
                'cancel_examine' => ArrayGet($new_data, 'cancel_examine', 0),
                'cancel_examine_range' => ArrayGet($new_data, 'cancel_examine_range', 0),
                'enroll_setting.cancel_delegate_enroll' => ArrayGet($new_data, 'enroll_setting.cancel_delegate_enroll', 0),
            ];
            $learner_model->updateLearningInfo($id, $update_info, Constants::LEARNING_CLASS);

            //操作日志
            $this->setNewData($new_data)
                ->setOldData($history)
                ->setOp(self::OP_UPDATE)
                ->describe($id, $history['name'], '报名')
                ->setOpModule('class')
                ->saveOpLog();
        }

        return $result ?: false;
    }

    /**
     * 更新班级解锁方式.
     *
     * @param int $id 班级id.
     * @param int $unlock_con .
     * @param array $unlock_detail .
     * @param int $type .
     * @return boolean|int
     */
    public function updateUnlockCon($id, $unlock_con, $unlock_detail, $type = Constants::LEARNING_CLASS, $tasks_switch = 0)
    {
        $history = $this->isExist($id, ['unlock_con' => 1, 'name' => 1, 'unlock_detail' => 1, 'last_eid' => 1, 'start_time' => 1]);
        $history_unlock_con = ArrayGet($history, 'unlock_con', self::UNLOCK_NOT_LIMIT);
        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'id' => $id,
            'type' => $type,
            'aid' => $this->aid,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]]
        ], [
            '$set' => [
                'unlock_con' => $unlock_con,
                'tasks_switch' => $tasks_switch,
                'unlock_detail' => $unlock_detail,
                'last_eid' => $this->eid,
                'updated' => Mongodb::getMongoDate()
            ]
        ]);
        if ($result) {
            $cache_key = $this->getRedisId($id);
            $this->getCacheInstance()->delete($cache_key);

            $task_model = new LearningTask($this->app);
            if (in_array($history_unlock_con, [
                    self::UNLOCK_NOT_LIMIT,//0
                    self::UNLOCK_STAGE_TASK_IN_ORDER,//2
                    self::UNLOCK_STAGE_TASK_NOT_IN_ORDER//1
                ]) && in_array($unlock_con, [
                    self::UNLOCK_STAGE_TIME_TASK_NOT_IN_ORDER,//3
                    self::UNLOCK_STAGE_TIME_TASK_IN_ORDER//4
                ])) {
                $task_model->updateGroupStartTime($id, $type, $history['start_time']);
            }
            if (in_array($history_unlock_con, [
                    self::UNLOCK_STAGE_TIME_TASK_NOT_IN_ORDER,//3
                    self::UNLOCK_STAGE_TIME_TASK_IN_ORDER//4
                ]) && in_array($unlock_con, [
                    self::UNLOCK_NOT_LIMIT,//0
                    self::UNLOCK_STAGE_TASK_IN_ORDER,//2
                    self::UNLOCK_STAGE_TASK_NOT_IN_ORDER//1
                ])) {
                $task_model->updateGroupStartTime($id, $type, '');
            }

            //操作日志
            if (in_array($type, [Constants::LEARNING_CLASS, Constants::LEARNING_PLAN])) {
                $module = $type == Constants::LEARNING_CLASS ? 'class' : 'plan';
                $this->setNewData([
                    'unlock_con' => $unlock_con,
                    'unlock_detail' => $unlock_detail,
                    'tasks_switch' => $tasks_switch,
                    'last_eid' => $this->eid
                ])
                    ->setOldData($history)
                    ->setOp(self::OP_UPDATE)
                    ->describe($id, $history['name'], '更新解锁方式')
                    ->setOpModule($module)
                    ->saveOpLog();
            }
        }

        return $result ? true : false;
    }

    //获取学习模块儿的名称
    public function getLearningNameById($id, $type)
    {
        if (in_array($type, [Constants::LEARNING_CLASS, Constants::LEARNING_PLAN, Constants::LEARNING_TRAINING])) {
            $learning = $this->isExist($id, ['name' => 1]);
        } else if ($type == Constants::LEARNING_BREAKTHROUGH) {
            $breakthrough_model = new Breakthrough($this->app);
            $learning = $breakthrough_model->isExist($id, ['name' => 1]);
        } else if ($type == Constants::RETRAINING) {
            $retraining_model = new Retraining($this->app);
            $learning = $retraining_model->isExists($id, ['title' => 1]);
        } else {
            $pack_model = new LearningPack($this->app);
            $learning = $pack_model->isExist($id, ['name' => 1]);
        }
        $title = ArrayGet($learning, 'name', 'title');

        return $title ? $title : '';
    }

    //移动端任务数量API
    public function unfinishedLearnNum($types = [])
    {

        $result = [
            'class_num' => 0,
            'training_num' => 0,
            'teaching_num' => 0,
            'plan_num' => 0,
            'appraisal_num' => 0,
            'autonomy_num' => 0,
            'course_num' => 0,
            'inspection_num' => 0,
            'retraining_num' => 0
        ];
        if (!$types) return $result;

        $learner_model = new Learner($this->app);
        foreach ($types as $type) {
            switch ($type) {
                case Constants::LEARNING_CLASS:
                    $time = Mongodb::getMongoDate(time());
                    $result['class_num'] = $learner_model->getUnfinishedPlanNum(Constants::LEARNING_CLASS, [
                        'learning_status' => Learner::LEARNING_STATUS_PUBLISHED,
                        'start_time' => ['$lte' => $time],
                        'end_time' => ['$gt' => $time]
                    ]);
                    break;
                case Constants::LEARNING_TRAINING:
                    $result['training_num'] = $learner_model->getUnfinishedPlanNum(Constants::LEARNING_TRAINING);
                    break;
                case Constants::LEARNING_PLAN:
                    $result['plan_num'] = $learner_model->getUnfinishedPlanNum(Constants::LEARNING_PLAN);
                    break;
                case Constants::LEARNING_TEACHING:
                    $result['teaching_num'] = $learner_model->getUnfinishedPlanNum(Constants::LEARNING_TEACHING);
                    break;
                case Constants::LEARNING_APPRAISAL:
                    $result['appraisal_num'] = $learner_model->getUnfinishedPlanNum(Constants::LEARNING_APPRAISAL);
                    break;
                case Constants::LEARNING_AUTONOMY:
                    //自学清单(待完成清单数量+待完成课程数量)

                    //待完成清单数量
                    $autonomy_num = $learner_model->getUnfinishedPlanNum(Constants::LEARNING_AUTONOMY, ['learning_id' => ['$nin' => [$this->getLearningAutoDefaultId()]]]);

                    //待完成课程数量
                    $task_model = new LearningTask($this->app);
                    $undone_course = $task_model->autoDefaultUndoneCoursesCount($this->getLearningAutoDefaultId());

                    $result['autonomy_num'] = $autonomy_num + $undone_course;
                    break;
                case Constants::LEARNING_AUTONOMY_PLAN:
                    //自学计划(待完成课程数量)
                    $task_model = new LearningTask($this->app);
                    $undone_course = $task_model->autoDefaultUndoneCoursesCount($this->getLearningAutoDefaultId());
                    $result['autonomy_num'] = $undone_course;
                    break;
                case Constants::LEARNING_INSPECTION:
                    $inspection_model = new LearningInspection($this->app);
                    $result['inspection_num'] = $inspection_model->myInspectionsTotal(['state' => LearningInspection::STATE_NOT_CONFIRMED]);
                    break;
                case Constants::RETRAINING:
                    $retraining_model = new RetrainingLearner($this->app);
                    $result['retraining_num'] = $retraining_model->myAuthenticationTotal(['authentication_status' => [RetrainingLearner::UNAUTHORIZED, RetrainingLearner::AUTHENTICATION_ONGOING]]);
                    break;
                case 'course':
                    $my_course_model = new MyCourse($this->app);
                    $result['course_num'] = $my_course_model->getCountByUsers();
                    break;
                case 100: //特殊 考核
                    $filters = ['list_type' => 2, 'status' => 1];
                    $videoModel = new LearningVideoAppraiseLearners($this->app);
                    $result['remote_check_num'] = $videoModel->getTotal($filters);
                    break;
            }
        }

        return $result;
    }

    //移动端任务数量API
    public function isHaveUnfinishedTask($types = [])
    {
        if (!$types) return 0;

        $learner_model = new Learner($this->app);
        foreach ($types as $type) {
            if ($type == Constants::LEARNING_CLASS) {
                //是否有未完成的班级
                $time = Mongodb::getMongoDate(time());
                $class_num = $learner_model->getUnfinishedPlanNum(Constants::LEARNING_CLASS, [
                    'learning_status' => Learner::LEARNING_STATUS_PUBLISHED,
                    'start_time' => ['$lte' => $time],
                    'end_time' => ['$gt' => $time]
                ]);
                if ($class_num) return 1;
            } else if (in_array($type, [Constants::LEARNING_PACK, Constants::LEARNING_COURSE_PACK, Constants::LEARNING_SPECIAL_TOPIC_PACK, Constants::LEARNING_PLAN, Constants::LEARNING_AUTONOMY])) {
                //学习计划/自主学习计划未完成数量
                $plan_num = $this->getMongoMasterConnection()->count(Constants::COLL_LEARNER, [
                    'aid' => $this->aid,
                    'status' => static::ENABLED,
                    'type' => ['$in' => [Constants::LEARNING_PACK, Constants::LEARNING_COURSE_PACK, Constants::LEARNING_SPECIAL_TOPIC_PACK, Constants::LEARNING_PLAN, Constants::LEARNING_AUTONOMY]],
                    'uid' => $this->eid,
                    'learning_status' => Learner::LEARNING_STATUS_PUBLISHED,
                    'learning_rate' => ['$lt' => 100],
                    'learning_enabled' => ['$nin' => [Learner::LEARNING_DISABLED]],
                    'is_examine' => 1
                ]);
                if ($plan_num) return 1;
            } else if (in_array($type, [Constants::LEARNING_TEACHING, Constants::LEARNING_APPRAISAL])) {
                //带教/鉴定未完成数量
                $teaching_num = $this->getMongoMasterConnection()->count(Constants::COLL_LEARNER, [
                    'aid' => $this->aid,
                    'status' => static::ENABLED,
                    'type' => ['$in' => [Constants::LEARNING_TEACHING, Constants::LEARNING_APPRAISAL]],
                    'uid' => $this->eid,
                    'learn_status' => ['$lt' => Learner::LEARN_ENDED],
                    'learning_status' => Learner::LEARNING_STATUS_PUBLISHED,
                    'learning_rate' => ['$lt' => 100],
                    'learning_enabled' => ['$nin' => [Learner::LEARNING_DISABLED]],
                    'is_examine' => 1
                ]);
                if ($teaching_num) return 1;
            } else if ($type == Constants::LEARNING_TRAINING) {
                //外派培训未完成数量
                $training_num = $learner_model->getUnfinishedPlanNum(Constants::LEARNING_TRAINING);
                if ($training_num) return 1;
            } else if ($type == Constants::LEARNING_INSPECTION) {
                //巡检未完成数量
                $inspection_model = new LearningInspection($this->app);
                $inspection_num = $inspection_model->myInspectionsTotal(['state' => LearningInspection::STATE_NOT_CONFIRMED]);
                if ($inspection_num) return 1;
            } else if ($type == Constants::RETRAINING) {
                //认证与再培训未完成数量
                $retraining_model = new RetrainingLearner($this->app);
                $retraining_num = $retraining_model->myAuthenticationTotal(['authentication_status' => [RetrainingLearner::UNAUTHORIZED, RetrainingLearner::AUTHENTICATION_ONGOING]]);
                if ($retraining_num) return 1;
            } else if ($type == 'course') {
                //指派的课程未完成数量
                $my_course_model = new MyCourse($this->app);
                $course_num = $my_course_model->getCountByUsers();
                if ($course_num) return 1;
            }
        }

        return 0;
    }

    /**
     * 获取学习模块儿状态.
     *
     * @param int $learning_id .
     * @param int $type .
     * @return boolean|int
     */
    public function getLearningStatus($learning_id, $type = Constants::LEARNING_CLASS)
    {
        $is_end = 0;
        if ($type == Constants::LEARNING_BREAKTHROUGH) {
            $learning_model = new Breakthrough($this->app);
            $learning = $learning_model->isExist($learning_id, ['publish_status' => 1]);
            $learning_status = ArrayGet($learning, 'publish_status', 2);
        } else if (in_array($type, [Constants::LEARNING_CLASS, Constants::LEARNING_PLAN, Constants::LEARNING_TRAINING, Constants::LEARNING_AUTONOMY])) {
            $learning = $this->isExist($learning_id, ['status' => 1, 'is_end' => 1]);
            $learning_status = ArrayGet($learning, 'status', 2);
            if (in_array($type, [Constants::LEARNING_APPRAISAL, Constants::LEARNING_TEACHING])) $is_end = ArrayGet($learning, 'is_end', 0);
        } else if (in_array($type, [Constants::LEARNING_PACK, Constants::LEARNING_COURSE_PACK, Constants::LEARNING_SPECIAL_TOPIC_PACK, Constants::LEARNING_TEACHING, Constants::LEARNING_INSPECTION, Constants::LEARNING_MAP, Constants::LEARNING_APPRAISAL])) {
            $learning_model = new LearningPack($this->app);
            $learning = $learning_model->isExist($learning_id, ['publish_status' => 1, 'is_end' => 1]);
            $learning_status = ArrayGet($learning, 'publish_status', 2);
            $is_end = ArrayGet($learning, 'is_end', 0);
        } else if ($type == Constants::RETRAINING) {
            $learning_model = new Retraining($this->app);
            $learning = $learning_model->isExists($learning_id, ['publish_status' => 1]);
            $learning_status = ArrayGet($learning, 'publish_status', 1);
            $learning_status = $learning_status ? 2 : 1;
        }

        return $is_end ? Learner::LEARNING_STATUS_END : $learning_status;
    }

    //获取今年发布的智能班级/学习计划
    public function publishedLearnings($type = Constants::LEARNING_CLASS, $year = 0)
    {
        $log_annual_model = new LearningLogAnnual($this->app);
        $time = $log_annual_model->getYearTime($year);
        $cond = [
            'aid' => $this->aid,
            'status' => 2,
            'type' => $type,
            'publish_time' => [
                '$gte' => Mongodb::getMongoDate($time['start_time']),
                '$lte' => Mongodb::getMongoDate($time['end_time'])
            ]
        ];
        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, $cond, 0, 0, [], [
            'id' => 1
        ]);

        return $result ? array_column($result, 'id') : [];
    }

    public function learningPublishOperateTask($learning_id, $type = Constants::LEARNING_CLASS)
    {
        $public_model = new PublicLearningTasks($this->app);
        $public_model->publishMq($learning_id, $type);
    }

    //获取今天发布的班级/计划数量
    public function publishedTotalGroupByOrg($rules = [], $type = Constants::LEARNING_CLASS)
    {
        $match = [
            'aid' => $this->aid,
            'status' => ['$nin' => [0, 1]],
            'type' => $type
        ];
        $publish_start_time = ArrayGet($rules, 'publish_start_time', '');
        $publish_end_time = ArrayGet($rules, 'publish_end_time', '');
        if ($publish_start_time && $publish_end_time) {
            $match['publish_time'] = [
                '$gte' => Mongodb::getMongoDate($publish_start_time),
                '$lte' => Mongodb::getMongoDate($publish_end_time)
            ];
        } else if ($publish_start_time) {
            $match['publish_time'] = ['$gte' => Mongodb::getMongoDate($publish_start_time)];
        } else if ($publish_end_time) {
            $match['publish_time'] = ['$lte' => Mongodb::getMongoDate($publish_end_time)];
        }
        $group = [
            '_id' => '$organization.id',
            'num' => ['$sum' => 1]
        ];
        $project = [
            '_id' => 0,
            'dept_id' => '$_id',
            'num' => 1
        ];
        $pipeline = [
            ['$match' => $match],
            ['$group' => $group],
            ['$project' => $project],
        ];
        $result = $this->getMongoMasterConnection()->aggregate(Constants::COLL_LEARNING_PLAN, $pipeline);

        return $result ? array_column($result, 'num', 'dept_id') : 0;
    }

    //获取今天发布的班级/计划数量
    public function removeTotalGroupByOrg($rules = [], $type = Constants::LEARNING_CLASS)
    {
        $match = [
            'aid' => $this->aid,
            'status' => static::DISABLED,
            'type' => $type,
            'publish_time' => ['$ne' => '']
        ];
        $remove_start_time = ArrayGet($rules, 'remove_start_time', '');
        $remove_end_time = ArrayGet($rules, 'remove_end_time', '');
        if ($remove_start_time && $remove_end_time) {
            $match['updated'] = [
                '$gte' => Mongodb::getMongoDate($remove_start_time),
                '$lte' => Mongodb::getMongoDate($remove_end_time)
            ];
        } else if ($remove_start_time) {
            $match['updated'] = ['$gte' => Mongodb::getMongoDate($remove_start_time)];
        } else if ($remove_end_time) {
            $match['updated'] = ['$lte' => Mongodb::getMongoDate($remove_end_time)];
        }
        $group = [
            '_id' => '$organization.id',
            'num' => ['$sum' => 1]
        ];
        $project = [
            '_id' => 0,
            'dept_id' => '$_id',
            'num' => 1
        ];
        $pipeline = [
            ['$match' => $match],
            ['$group' => $group],
            ['$project' => $project],
        ];
        $result = $this->getMongoMasterConnection()->aggregate(Constants::COLL_LEARNING_PLAN, $pipeline);

        return $result ? array_column($result, 'num', 'dept_id') : 0;
    }

    public function delLearningRedis($learning_ids)
    {
        if (!$learning_ids || !is_array($learning_ids)) return true;
        foreach ($learning_ids as $learning_id) {
            $cache_key = $this->getRedisId($learning_id);
            $this->getCacheInstance()->delete($cache_key);
        }
    }

    public function viewLearningQueue($name, $rules = [], $progress = 0)
    {
        $cond = [
            'aid' => $this->aid,
            'status' => static::ENABLED,
            'name' => $name,
            'progress' => $progress
        ];
        if ($rules) $cond = array_merge($cond, $rules);
        $result = $this->getMongoMasterConnection()->count(Constants::COLL_QUEUE, $cond);

        return $result ?: 0;
    }

    //获取我创建/管理的班级所在项目
    public function getMyManageProjIds()
    {
        $match = [
            'aid' => $this->aid,
            'status' => ['$nin' => [static::DISABLED]],
            'type' => Constants::LEARNING_CLASS,
            'project_id' => ['$nin' => [0]],
            '$or' => [
                ['uid' => $this->eid],
                ['monitor' => ['$elemMatch' => ['id' => $this->eid, 'type' => self::APPLIED_RANGE_EMPLOYEE]]]
            ],
        ];
        $group = ['_id' => '$project_id'];
        $project = ['_id' => 0, 'project_id' => '$_id'];
        $pipeline = [
            ['$match' => $match],
            ['$group' => $group],
            ['$project' => $project]
        ];
        $result = $this->getMongoMasterConnection()->aggregate(Constants::COLL_LEARNING_PLAN, $pipeline);

        return $result ? array_column($result, 'project_id') : [];
    }

    //获取今天发布的班级/计划数量
    public function learningNumGroupByOrgAndDate($type = Constants::LEARNING_CLASS)
    {
        $match = [
            'aid' => $this->aid,
            'status' => ['$nin' => [0, 1]],
            'type' => $type
        ];
        $project = [
            'date' => ['$dateToString' => ['format' => '%Y-%m-%d', 'date' => '$publish_time', 'timezone' => 'Asia/Shanghai']],
            'organization' => 1,
            'id' => 1
        ];
        $group = [
            '_id' => ['date' => '$date', 'dept_id' => '$organization.id'],
            'num' => ['$sum' => 1]
        ];
        $projectV2 = [
            '_id' => 0,
            'date' => '$_id.date',
            'dept_id' => '$_id.dept_id',
            'num' => 1
        ];
        $pipeline = [
            ['$match' => $match],
            ['$project' => $project],
            ['$group' => $group],
            ['$project' => $projectV2],
        ];
        $result = $this->getMongoMasterConnection()->aggregate(Constants::COLL_LEARNING_PLAN, $pipeline);

        return $result ?: [];
    }

    public function transListDataFrom($data_from, &$new_data_from)
    {
        if ($data_from == 'sta_dept_proj') {
            $new_data_from = 'all_published';
        } else {
            $new_data_from = $data_from;
        }
    }

    protected function confirmLearningFilters($filters)
    {
        $cond = [
            'aid' => $this->aid,
            'status' => ['$nin' => [0]],
            'type' => Constants::LEARNING_CLASS,
            'learning_status' => self::LEARNING_UNPUBLISH
        ];
        $employee_model = new Employee($this->app);
        $employee = $employee_model->view($this->eid, $this->aid, ['groups' => 1]);
        $groups = ArrayGet($employee, 'groups', []);
//        if (!$groups) return [];

        $cond['$or'] = [
            ['learner_confirm_group' => ['$elemMatch' => ['id' => ['$in' => $groups], 'type' => LearningPlan::APPLIED_RANGE_GROUP]]],
            ['learner_confirm_group' => ['$elemMatch' => ['id' => $this->eid, 'type' => LearningPlan::APPLIED_RANGE_EMPLOYEE]]]
        ];
        if ($keyword = ArrayGet($filters, 'keyword', '')) {
            $cond['name'] = new Regex($keyword, 'im');
        }
        if ($confirm_status = ArrayGet($filters, 'status', 0)) {
            $cond['status'] = $confirm_status;
        }

        return $cond;
    }

    public function confirmLearnings($filters, $pg, $fields = [], $sort = [])
    {
        $cond = $this->confirmLearningFilters($filters);
        if (!$cond) return [];

        if (!$pg) {
            $pg = new \Key\Records\Pagination();
            $pg->setPage(0)->setItemsPerPage(0);
        }
        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, $cond,
            $pg->getOffset(),
            $pg->getItemsPerPage(), $sort ?: [
                'updated' => -1
            ], [
                'id' => 1,
                'name' => 1,
                'status' => 1,
                'start_time' => 1,
                'end_time' => 1,
                'learner_confirm_deadline' => 1,
                'display' => 1,
                'learning_status' => 1
            ]);
        if ($result) {
            foreach ($result as $k => $val) {
                $val['start_time'] = (string)$val['start_time'];
                $val['end_time'] = (string)$val['end_time'];
                $result[$k] = $val;
            }
        }

        return $result ?: [];
    }

    public function confirmLearningsTotal($filters)
    {
        $cond = $this->confirmLearningFilters($filters);
        if (!$cond) return 0;
        $result = $this->getMongoMasterConnection()->count(Constants::COLL_LEARNING_PLAN, $cond);

        return $result ?: 0;
    }

    /**
     * 提交班级.
     *
     * @param int $learning_id .
     * @return boolean|int
     */
    public function submit($learning_id)
    {
        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'status' => ['$nin' => [0]],
            'type' => Constants::LEARNING_CLASS,
            'id' => $learning_id
        ], [
            '$set' => [
                'learning_status' => self::LEARNING_UNPUBLISH,
                'submit_detail' => [
                    'eid' => $this->eid,
                    'time' => time() * 1000
                ],
                'updated' => Mongodb::getMongoDate()
            ]
        ]);
        if ($result) {
            $cache_key = $this->getRedisId($learning_id);
            $this->getCacheInstance()->delete($cache_key);
            $this->updateLearningStatus($learning_id, Learner::LEARNING_STATUS_UNSUBMIT, Constants::LEARNING_CLASS);
        }

        return $result ?: false;
    }

    public function listByCagId($cag_id)
    {
        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'status' => ['$nin' => [static::DISABLED]],
            'category.id' => $cag_id
        ], 0, 0, [], [
            'id' => 1
        ]);

        return $result ?: [];
    }

    /**
     * 更新分类名称.
     *
     * @param int $cag_id .
     * @param string $cag_name .
     * @param string $cag_no .
     * @return boolean|int
     */
    public function updateCagName($cag_id, $cag_no, $cag_name)
    {
        $learnings = $this->listByCagId($cag_id);
        if (!$learnings) return true;

        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'status' => ['$nin' => [static::DISABLED]],
            'category.id' => $cag_id
        ], [
            '$set' => [
                'category.name' => $cag_name,
                'category.no' => $cag_no,
                'updated' => Mongodb::getMongoDate()
            ]
        ]);
        if ($result) {
            foreach ($learnings as $learning) {
                $cache_key = $this->getRedisId($learning['id']);
                $this->getCacheInstance()->delete($cache_key);
            }
        }

        return $result ?: false;
    }

    /**
     * 更新分类名称.
     *
     * @param int $cag_id .
     * @param string $cag_name .
     * @return boolean|int
     */
    public function removeCag($cag_id)
    {
        $learnings = $this->listByCagId($cag_id);
        if (!$learnings) return true;

        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'status' => ['$nin' => [static::DISABLED]],
            'category.id' => $cag_id
        ], [
            '$set' => [
                'category' => ['id' => 0, 'no' => 'LC000000', 'name' => '默认分类'],
                'updated' => Mongodb::getMongoDate()
            ]
        ]);
        if ($result) {
            foreach ($learnings as $learning) {
                $cache_key = $this->getRedisId($learning['id']);
                $this->getCacheInstance()->delete($cache_key);
            }
        }

        return $result ?: false;
    }

    public function getLearningDesc($type)
    {
        switch ($type) {
            case Constants::LEARNING_CLASS:
                return '智能班级';
                break;
            case Constants::LEARNING_PACK:
                return '学习包';
                break;
            case Constants::LEARNING_COURSE_PACK:
                return 'betterU';
                break;
            case Constants::LEARNING_BREAKTHROUGH:
                return '闯关';
                break;
            case Constants::LEARNING_PLAN:
                return '学习计划';
                break;
            case Constants::LEARNING_TRAINING:
                return '带教';
                break;
            case Constants::LEARNING_MAP:
                return '发展地图';
                break;
            case Constants::LEARNING_MAP_BUSINESS:
                return '业务学习地图';
                break;
            case Constants::LEARNING_APPRAISAL:
                return '鉴定';
                break;
            case Constants::LEARNING_AUTONOMY:
                return '自主学习计划';
                break;
            case Constants::LEARNING_INSPECTION:
                return '巡店';
                break;
            case Constants::RETRAINING:
                return '认证与再培训';
                break;
            case Constants::LEARNING_POSITION:
                return '岗位学习地图';
                break;
            case Constants::LEARNING_MARKETING:
                return '营销一体化';
                break;
            default:
                return '智能班级';
        }
    }

    protected function getRelationTrainingType($type, &$obj_type)
    {
        switch ($type) {
            case Constants::LEARNING_PLAN:
                $obj_type = Relationship::OBJECT_TYPE_PLAN;
                break;
            case Constants::LEARNING_BREAKTHROUGH:
                $obj_type = Relationship::OBJECT_TYPE_PASS_CAG;
                break;
            case Constants::LEARNING_TRAINING:
                $obj_type = Relationship::OBJECT_TYPE_TEACHING;
                break;
            case Constants::LEARNING_APPRAISAL:
                $obj_type = Relationship::OBJECT_TYPE_APPRAISAL;
                break;
            case Constants::LEARNING_INSPECTION:
                $obj_type = Relationship::OBJECT_TYPE_INSPECTION;
                break;
            default:
                $obj_type = Relationship::OBJECT_TYPE_CLASS;
        }
    }

    public function addRelationTrainingCenter($learning_id, $type, $center_id)
    {
        if (!$center_id || !$this->usedTrainingCenter()) return true;

        $this->getRelationTrainingType($type, $obj_type);
        $training_center_model = new Relationship($this->app);
        $training_center_model->add($center_id, $learning_id, $obj_type);
    }

    public function replaceRelationTrainingCenter($learning_id, $type, $old_center_id, $new_center_id)
    {
        if (!$this->usedTrainingCenter()) return true;

        $this->getRelationTrainingType($type, $obj_type);
        $training_center_model = new Relationship($this->app);
        $training_center_model->replace($old_center_id, $new_center_id, $learning_id, $obj_type);
    }

    public function removeRelationTrainingCenter($learning_id, $type, $center_id)
    {
        if (!$center_id || !$this->usedTrainingCenter()) return true;

        $this->getRelationTrainingType($type, $obj_type);
        $training_center_model = new Relationship($this->app);
        $training_center_model->remove($center_id, $learning_id, $obj_type);
    }

    protected function usedTrainingCenter()
    {
        $account_model = new Account($this->app);
        return $account_model->getFeature('training_center', $this->aid);
    }

    //恢复已删除的班级/计划/学习包
    public function onObjectRevert($aid, $object_id, $object_type)
    {
        // error_log('~~~~~~~~~~~~1~~~~~~~~~~~~~~aid: ' . $aid . ' object_id: ' . $object_id . ' object_type: ' . $object_type);
        if (!in_array($object_type, [Constants::OBJ_CLASS, Constants::OBJ_LEARNING_PLAN, Constants::OBJ_LEARNING_PACKAGE, Constants::OBJ_LEARNING_COURSE_PACKAGE])) return true;
        switch ($object_type) {
            case Constants::OBJ_LEARNING_PLAN:
                $type = Constants::LEARNING_PLAN;
                break;
            case Constants::OBJ_LEARNING_PACKAGE:
                $type = Constants::LEARNING_PACK;
                break;
            case Constants::OBJ_LEARNING_COURSE_PACKAGE:
                $type = Constants::LEARNING_COURSE_PACK;
                break;
            default:
                $type = Constants::LEARNING_CLASS;
        }

        if (in_array($object_type, [Constants::OBJ_LEARNING_PACKAGE, Constants::OBJ_LEARNING_COURSE_PACKAGE])) {
            $pack_model = new LearningPack($this->app);
            $updated = $pack_model->revertDeletedLearning($aid, $object_id, $type);
        } else {
            $history = $this->viewDeletedLearning($aid, $object_id, $type, [
                'is_end' => 1,
                'publish_time' => 1,
                'updated' => 1
            ]);
            $learning_status = 1;
            if ($history['publish_time']) {
                $learning_status = 2;
            } else if ($history['is_end']) {
                $learning_status = 3;
            }
            $updated = $this->revertDeletedLearning($aid, $object_id, $type, $history);
        }

        //恢复相应的阶段，任务及学员
        $group_model = new LearningGroup($this->app);
        $group_model->revertDeletedByLearningId($aid, $object_id, $type, $updated);
        $learner_model = new Learner($this->app);
        $learner_model->revertDeletedByLearningId($aid, $object_id, $type, $updated);

        //恢复班级签到/互动及对应的学员详情
        if ($object_type == Constants::OBJ_CLASS) {
            $sign_model = new ClassSign($this->app);
            $sign_model->revertDeletedByLearningId($aid, $object_id, $type, $updated);
            $interaction_model = new LearningInteraction($this->app);
            $interaction_model->revertDeletedByLearningId($aid, $object_id, $type, $updated);

            $autonomy_detail_model = new AutonomyTaskDetail($this->app);
            $autonomy_detail_model->revertDeletedByLearningId($aid, $object_id, $type, $updated);

            $sta_offline_model = new StatisticsOfflineTeaching($this->app);
            $sta_offline_model->revertDeletedByLearningId($aid, $object_id, $type, $learning_status);
            $this->revertGroupByClass($aid, $object_id, $updated);

            $this->updateRecycleStatus($aid, $object_id, $type);
        }

    }

    //重置班级的还原状态 recycle_status 0-未还原 1-还原中 2-已还原
    protected function updateRecycleStatus($aid, $learning_id, $type)
    {
        $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'aid' => $aid,
            'status' => static::DISABLED,
            'id' => $learning_id,
            'type' => $type
        ], [
            '$set' => [
                'recycle_status' => 2
            ]
        ]);
    }

    //恢复已删除的班级/计划
    protected function revertDeletedLearning($aid, $learning_id, $type, $history)
    {
        $status = 1;
        if ($history['publish_time']) $status = 2;
        $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'aid' => $aid,
            'status' => static::DISABLED,
            'id' => $learning_id,
            'type' => $type
        ], [
            '$set' => [
                'status' => $status,
                'recycle_status' => 1
            ]
        ]);

        return (string)$history['updated'] / 1000;
    }

    //查询已删除的班级/计划信息
    protected function viewDeletedLearning($aid, $learning_id, $type, $fields = [])
    {
        $history = $this->getMongoMasterConnection()->fetchRow(Constants::COLL_LEARNING_PLAN, [
            'aid' => $aid,
            'status' => static::DISABLED,
            'id' => $learning_id,
            'type' => $type
        ], $fields);

        return $history ?: [];
    }

    /**
     * 恢复删除的pk分组.
     *
     * @param int $aid .
     * @param int $learning_id .
     * @param string $updated .
     * @return boolean|int
     */
    public function revertGroupByClass($aid, $learning_id, $updated)
    {
        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNER_PK_GROUP, [
            'learning_id' => $learning_id,
            'status' => static::DISABLED,
            'aid' => $aid,
            'updated' => ['$gte' => Mongodb::getMongoDate($updated)]
        ], [
            '$set' => [
                'status' => static::ENABLED,
                'updated' => Mongodb::getMongoDate()
            ]
        ]);

        return $result ? true : false;
    }

    /**
     * 获取当前系统时间.
     *
     * @return string
     */
    public function currentTime()
    {
        list($t1, $t2) = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($t1) + floatval($t2)) * 1000);
    }

    protected function getListByProjectIdFilters($project_id, $filters = [], $inProject = 1)
    {
        $cond = [
            'aid' => $this->aid,
            'status' => 2,
            'type' => Constants::LEARNING_CLASS,
        ];
        if ($inProject == 1) {
            $cond['project_id'] = $project_id;
        }

        $start_time = ArrayGet($filters, 'start_time', '');
        $end_time = ArrayGet($filters, 'end_time', '');

        if ($start_time && $end_time) {
//            $cond['$or'] = [
//                ['start_time' => ['$gte' => $start_time, '$lte' => $end_time]],
//                ['end_time' => ['$gte' => $start_time, '$lte' => $end_time]],
//                ['start_time' => ['$gte' => $start_time], 'end_time' => ['$lte' => $end_time]],
//            ];
            $cond['start_time'] = ['$gte' => $start_time];
            $cond['end_time'] = ['$lte' => $end_time];
//            $cond['$and'] = [
//                'start_time' => ['$gte' => $start_time],
//                'end_time' => ['$lte' => $end_time]
//            ];
        } else if ($start_time) {
            $cond['start_time'] = ['$gte' => $start_time];
        } else if ($end_time) {
            $cond['start_time'] = ['$lt' => $end_time];
        }

        return $cond;
    }

    /**
     * 获取学习计划列表.
     *
     * @param array $filters .
     * @param int $project_id .
     * @param null|\Key\Records\Pagination $pg .
     * @param array $sorts .
     * @param array $fields .
     * @return array
     */
    public function getListByProjectId($project_id, $filters = [], $pg = null, $sorts = [], $fields = [], $inProject = 1)
    {
        if (!$pg) {
            $pg = new Pagination();
            $pg->setPage(0)->setItemsPerPage(0);
        }
        $cond = $this->getListByProjectIdFilters($project_id, $filters, $inProject);

        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, $cond,
            $pg->getOffset(),
            $pg->getItemsPerPage(),
            $sorts ?: [
                'finished_rate' => -1
            ], $fields ?: [
                'aid' => 0,
                'desc' => 0
            ]
        );
        if ($result) Utils::convertMongoDateToTimestamp($result);

        return $result ?: [];
    }

    public function getListByProjectIdTotal($project_id, $filters = [], $inProject = 1)
    {
        $cond = $this->getListByProjectIdFilters($project_id, $filters, $inProject);
        $result = $this->getMongoMasterConnection()->count(Constants::COLL_LEARNING_PLAN, $cond);

        return $result ?: 0;
    }

    public function getLearningDataAllFromWantWant()
    {

        $aid = $this->aid ? $this->aid : env('WANGWANG_DATA_BACK_AID', '4702');

        $cond = [
            'aid' => $aid,
            'status' => 1,
        ];

        $result = $this->getMongoMasterConnection()->fetchAll('sta_wantwant_report_all_data_back', $cond);

        if ($result) {
            foreach ($result as $key => $value) {

                $lecturer_infos = ArrayGet($value, 'lecturer', []);

                if (!$lecturer_infos) {
                    continue;
                }

                foreach ($lecturer_infos as $lecturer_info) {
                    $cond_lecturer = [
                        'aid' => $aid,
                        'id' => $lecturer_info['id']
                    ];
                    $lecturer = $this->getMongoMasterConnection()->fetchRow(Constants::COLL_COMPANY_LECTURER, $cond_lecturer, ['id' => 1, 'number' => 1, 'name' => 1, 'lec_type' => 1, 'suply_id' => 1]);

                    $suply_id = ArrayGet($lecturer, 'suply_id', 0);
                    if ($suply_id) {
                        //查询供应商信息
                        $supply_info = $this->getMongoMasterConnection()->fetchRow(Constants::COLL_SUPPLIER, ['aid' => $aid, 'id' => $suply_id, 'status' => 1], ['name' => 1]);
                        $result[$key]['lecturer']['supplier_name'] = ArrayGet($supply_info, 'name', '');//供应商名称
                        $supply_contact = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_SUPPLIER_CONTACT, ['aid' => $aid, 'supplier_id' => $suply_id, 'status' => 1], 0, 0, [], ['mobile' => 1, 'display' => 1]);
                        $result[$key]['lecturer']['supplier_contact'] = $supply_contact;
                    }
                    $result[$key]['lecturer']['id'] = ArrayGet($lecturer, 'id', 0);
                    $result[$key]['lecturer']['name'] = ArrayGet($lecturer, 'name', '');
                    $result[$key]['lecturer']['no'] = ArrayGet($lecturer, 'number', '');
                    $result[$key]['lecturer']['lec_type'] = ArrayGet($lecturer, 'lec_type', '');
                }
            }
        }

        return $result ?: [];
    }


    /**
     * @param $data_type 1多班次 2智能班级 3班级阶段 4阶段课程 5班级学员
     * @param $handle_type 数据类型 1新增 2编辑 3启用 4禁用 5删除
     * @param string $start_time 开始时间
     * @param string $end_time 结束时间
     * @param null $pg
     * @return array
     * @throws \Key\Exception\DatabaseExceptio
     *
     */
    public function getLearningLogDataFromWantWant($data_type, $handle_type, $start_time = '', $end_time = '', $pg = null, &$total)
    {

        $aid = $this->aid ? $this->aid : env('WANGWANG_DATA_BACK_AID', '4702');

        if (!$pg) {
            $pg = new Pagination();
            $pg->setPage(0)->setItemsPerPage(10);
        }

        if (!in_array($data_type, [1, 2, 3, 4, 5, 6])) {
            return [];
        }

        $match = [
            'aid' => $aid,
        ];

        if ($handle_type) $match['handle_type'] = $handle_type;
        if (!$start_time) {
            $start_time = strtotime(date("Y-m-d 00:00:00", strtotime("-1 day"))) * 1000;
//            $start_time = Mongodb::getMongoDate($start_time);
        } else {
            $start_time = $start_time . ' 00:00:00';
            $start_time = strtotime($start_time) * 1000;
//            $start_time = Mongodb::getMongoDate($start_time);
        }
        if (!$end_time) {
            $end_time = strtotime(date("Y-m-d 23:59:59", strtotime("-1 day"))) * 1000;
//            $end_time = Mongodb::getMongoDate($end_time);
        } else {
            $end_time = $end_time . ' 23:59:59';
            $end_time = strtotime($end_time) * 1000;
//            $end_time = Mongodb::getMongoDate($end_time);
        }
        $match['updated'] = ['$gte' => (string)$start_time, '$lte' => (string)$end_time];

        if ($data_type == 5) {
            $match['data_type'] = 1; //学员-班级数据
        } elseif ($data_type == 6) {
            $match['data_type'] = 2; //学员-课程数据
        }

        switch ($data_type) {
            case 1:
                $coll_database_table = 'wangwang_learning_project_data_log_' . $this->aid;
                $group = [
                    '_id' => [
                        'project_id' => '$project_id',
                        'handle_type' => '$handle_type'
                    ],
                    'data' => [
                        '$first' => '$$ROOT'
                    ]
                ];
                break;
            case 2:
                $coll_database_table = 'wangwang_learning_plan_data_log_' . $this->aid;
                $group = [
                    '_id' => [
                        'learning_id' => '$learning_id',
                        'handle_type' => '$handle_type'
                    ],
                    'data' => [
                        '$first' => '$$ROOT'
                    ]
                ];
                break;
            case 3:
                $coll_database_table = 'wangwang_learning_group_data_log_' . $this->aid;
                $group = [
                    '_id' => [
                        'learning_id' => '$learning_id',
                        'learning_type' => '$learning_type',
                        'group_id' => '$group_id',
                        'handle_type' => '$handle_type'
                    ],
                    'data' => [
                        '$first' => '$$ROOT'
                    ]
                ];
                break;
            case 4:
                $coll_database_table = 'wangwang_learning_course_data_log_' . $this->aid;
                $group = [
                    '_id' => [
                        'learning_id' => '$learning_id',
                        'learning_type' => '$learning_type',
                        'task_id' => '$task_id',
                        'handle_type' => '$handle_type'
                    ],
                    'data' => [
                        '$first' => '$$ROOT'
                    ]
                ];
                break;
            case 5:
                $coll_database_table = 'wangwang_learning_learner_data_log_' . $this->aid;
                $group = [
                    '_id' => [
                        'learning_id' => '$learning_id',
                        'learning_type' => '$learning_type',
                        'learner_eid' => '$learner_eid',
                        'handle_type' => '$handle_type'
                    ],
                    'data' => [
                        '$first' => '$$ROOT'
                    ]
                ];
                break;
            case 6:
                $coll_database_table = 'wangwang_learning_learner_data_log_' . $this->aid;
                $group = [
                    '_id' => [
                        'learning_id' => '$learning_id',
                        'learning_type' => '$learning_type',
                        'learner_eid' => '$learner_eid',
                        'handle_type' => '$handle_type'
                    ],
                    'data' => [
                        '$first' => '$$ROOT'
                    ]
                ];
                break;
            default:
                $coll_database_table = '';
                $group = [];
                break;
        }

        if (!$handle_type) unset($group['_id']['handle_type']);

        $total = $this->getMongoMasterConnection()->count($coll_database_table, $match);

        $result = $this->getMongoMasterConnection()->aggregate($coll_database_table, [
            ['$match' => $match],
//            ['$sort' => ['updated' => 1]],
            ['$group' => $group],
            ['$sort' => ['data.id' => 1]],
            ['$skip' => $pg->getOffset()],
            ['$limit' => $pg->getItemsPerPage()],
        ]);

        $new_data = [];
        if ($result) {
            foreach ($result as $key => $value) {
                //处理时间
                if ($value['data']['created']) {
                    $result[$key]['data']['created'] = date('Y-m-d H:i:s', (string)$value['data']['created'] / 1000);
                }
                if ($value['data']['updated']) {
                    $result[$key]['data']['updated'] = date('Y-m-d H:i:s', (string)$value['data']['updated'] / 1000);
                }
                if ($value['data']['train_start_time']) {
                    $result[$key]['data']['train_start_time'] = date('Y-m-d H:i:s', (string)$value['data']['train_start_time'] / 1000);
                }
                if ($value['data']['train_end_time']) {
                    $result[$key]['data']['train_end_time'] = date('Y-m-d H:i:s', (string)$value['data']['train_end_time'] / 1000);
                }
                if ($value['data']['start_time']) {
                    $result[$key]['data']['start_time'] = date('Y-m-d H:i:s', (string)$value['data']['start_time'] / 1000);
                }

                $new_data[] = $result[$key]['data'];
            }
        }

        return $new_data ?: [];
    }

    /**
     * @param $id
     * @throws \Key\Exception\DatabaseException
     */
    public function updateProjectByClassId($id)
    {
        $con = [
            'aid' => $this->aid,
            'id' => $id
        ];
        $new_data = [
            '$set' => [
                'source' => 0,
                'source_id' => 0,
                'source_name' => '',
                'updated_pro_eid' => $this->eid
            ]
        ];
        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, $con, $new_data);

        $cache_key = $this->getRedisId($id);
        $this->getCacheInstance()->delete($cache_key);

        return $result;
    }

    protected function getEnrollFilters($filters = [])
    {
        $learner_model = new Learner($this->app);
        $learning_ids = $learner_model->getLearningIdsByType('', Constants::LEARNING_CLASS);

        $cond = [
            'aid' => $this->aid,
            'status' => 2,
            'learning_id' => ['$nin' => $learning_ids],
            'type' => Constants::LEARNING_CLASS,
            'end_time' => ['$gte' => Mongodb::getMongoDate()],
            'is_applicant' => 1,
            'is_end' => 0,
            '$or' => [
                ['enroll_setting.custom_enroll_time' => 0, 'start_time' => ['$gte' => Mongodb::getMongoDate()]],
                ['enroll_setting.custom_enroll_time' => 1, 'enroll_setting.enroll_ended' => ['$gte' => time() * 1000]]
            ],
        ];
        $start_time = ArrayGet($filters, 'start_time', '');
        $end_time = ArrayGet($filters, 'end_time', '');
        if ($start_time && $end_time) {
            $started = Mongodb::getMongoDate($start_time);
            $ended = Mongodb::getMongoDate($end_time);
            $cond['$and'] = [
                ['$or' => [
                    ['start_time' => ['$gte' => $started, '$lte' => $ended]],
                    ['end_time' => ['$gte' => $started, '$lte' => $ended]],
                    ['start_time' => ['$gte' => $started], 'end_time' => ['$gte' => $ended]],
                ]],
                ['$or' => [
                    ['enroll_setting.custom_enroll_time' => 0, 'start_time' => ['$gte' => $started]],
                    ['enroll_setting.custom_enroll_time' => 1, 'enroll_setting.enroll_ended' => ['$gte' => $started]]
                ]]
            ];
        }

        return $cond;
    }

    //获取允许我报名的班级
    public function enrollLearningsCalendar($start_time, $end_time)
    {
        $filters = ['start_time' => $start_time, 'end_time' => $end_time];
        $cond = $this->getEnrollFilters($filters);
        $learnings = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, $cond, 0, 0, [], [
            'id' => 1,
            'start_time' => 1,
            'end_time' => 1
        ]);
        $result = [];
        if ($learnings) {
            foreach ($learnings as $k => $val) {
                $start_date = date('Y-m-d', $start_time);
                $end_date = date('Y-m-d', $end_time);
                if ((string)$val['start_time'] / 1000 > $start_time) $start_date = date('Y-m-d', (string)$val['start_time'] / 1000);
                if ((string)$val['end_time'] / 1000 < $end_time) $end_date = date('Y-m-d', (string)$val['end_time'] / 1000);
                //两个时间戳相差的天数
                $diff_day = floor((strtotime($end_date) - strtotime($start_date)) / 86400);
                $current_start_str = $start_time;
                $current_start_date = $start_date;

                $i = 0;
                do {
                    if ($i > 0) $current_start_str = strtotime("$current_start_date +1 days");
                    $current_start_date = date('Y-m-d', $current_start_str);
                    $current_day = date('j', $current_start_str);
                    if (!isset($result[$current_day])) $result[$current_day] = 1;
                    $i++;
                } while ($i <= $diff_day);
            }
        }
        return $result;
    }

    //获取所有的班级和计划
    public function enrollLearningsCalendarByAid($start_time, $end_time, $fields = [], $depts = [])
    {

        if (!$fields) {
            $fields = [
                'id' => 1,
                'name' => 1,
                'start_time' => 1,
                'end_time' => 1
            ];
        }
        $cond = [
            'aid' => $this->aid,
            'status' => 2,
            'type' => ['$in' => [Constants::LEARNING_CLASS, Constants::LEARNING_PLAN]],
            'is_end' => 0,
        ];

        if ($depts) {
            $cond['organization.id'] = ['$in' => $depts];
        }

        if ($start_time && $end_time) {
            $started = Mongodb::getMongoDate($start_time);
            $ended = Mongodb::getMongoDate($end_time);
            $cond['$or'] = [
                ['start_time' => ['$gte' => $started, '$lte' => $ended]],
                ['end_time' => ['$gte' => $started, '$lte' => $ended]],
                ['start_time' => ['$lte' => $started], 'end_time' => ['$gte' => $ended]],
            ];
        }

        $learnings = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, $cond, 0, 0, [], $fields);

        return $learnings;
    }

    //获取允许我报名的班级
    public function enrollLearningsCalendarList($start_time, $end_time)
    {
        $filters = ['start_time' => $start_time, 'end_time' => $end_time];
        $cond = $this->getEnrollFilters($filters);
        $enroll_list = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, $cond, 0, 0, [], [
            'id' => 1,
            'name' => 1,
            'type' => 1,
            'start_time' => 1,
            'end_time' => 1,
            'enroll_setting' => 1,
        ]);
        $result = [];
        if ($enroll_list) $this->transEnrollLearnings($enroll_list, $result);

        return $result ?: [];
    }

    protected function transEnrollLearnings($enroll_list, &$result)
    {
        foreach ($enroll_list as $val) {
            $result[] = [
                'learning_id' => $val['id'],
                'learning_name' => $val['name'],
                'is_enroll' => 1,
                'start_time' => (string)$val['start_time'],
                'end_time' => (string)$val['end_time'],
                'enroll_setting' => $val['enroll_setting'],
                'type' => $val['type']
            ];
        }
    }

    //*********智能班级积分*******************************************************************

    /**
     * 智能班级积分设置
     * @param $new_data
     * @param $learning_id
     * @return bool|int
     * @throws \Key\Exception\AppException
     * @throws \Key\Exception\DatabaseException
     */
    public function pointSet($setting, $learning_id)
    {
        $db = $this->getMongoMasterConnection();

        $detail = $this->pointSetView(0, $learning_id);

        $this->removeRedisLearningClassPointSet($learning_id);
        if ($detail) {
            $condition = [
                'aid' => $this->aid,
                'status' => Constants::STATUS_ENABLED,
                'learning_id' => $learning_id,
            ];
            $newData = [
                '$set' => [
                    'setting' => $setting,
                    'updated' => $db->getMongoDate()
                ]
            ];

            $result = $db->update(Constants::COLL_LEARNING_CLASS_POINT_SETTING, $condition, $newData);
            return $result;
        }

        $data['id'] = Sequence::getSeparateId(Constants::COLL_LEARNING_CLASS_POINT_SETTING, $this->aid);
        $data['aid'] = $this->aid;
        $data['learning_id'] = $learning_id;
        $data['status'] = Constants::STATUS_ENABLED;
        $data['created'] = $db->getMongoDate();
        $data['updated'] = $db->getMongoDate();
        $data['setting'] = $setting;

        $result = $db->insert(Constants::COLL_LEARNING_CLASS_POINT_SETTING, $data);
        return $result;
    }

    /**
     * 获取智能班级积分设置
     * @param int $aid
     * @param $learning_id
     * @return array|mixed|null
     */
    public function pointSetView($aid = 0, $learning_id)
    {
        $this->aid = $aid ? $aid : $this->aid;

        $result = $this->getSetingByLearning_id($this->aid, [
            'setting' => 1,
        ], $learning_id);
        return ArrayGet($result, 'setting', []);
    }

    /**
     * 根据 learning_id 获取智能班级积分设置
     * @param int $aid
     * @param array $fields
     * @param $learning_id
     * @return array|mixed
     * @throws \Key\Exception\DatabaseException
     */
    public function getSetingByLearning_id($aid = 0, $fields = [], $learning_id)
    {
        $this->aid = $aid ? $aid : $this->aid;
        $cache = $this->getCacheInstance();
        $cache_key = CacheKeys::LEARNING_CLASS_POINT_SETTING . ':' . $this->aid . '-' . $learning_id;

        if ($result = $cache->get($cache_key)) {
            $result = json_decode($result, true);
            if ($fields && $result) {
                $result = Utils::handleFieldsReturn($result, $fields);
            }
            return $result;
        } else {
            $db = $this->getMongoMasterConnection();
            $condition = [
                'aid' => $this->aid,
                'status' => Constants::STATUS_ENABLED,
                'learning_id' => $learning_id
            ];

            $result = $db->fetchRow(Constants::COLL_LEARNING_CLASS_POINT_SETTING, $condition);
            if ($result) {
                Utils::convertMongoDateToTimestamp($result);
//                $cache->set($cache_key, $result);
                $cache->set($cache_key, $result, 3600);
                if ($fields) {
                    $result = Utils::handleFieldsReturn($result, $fields);
                }
            }
            return $result ?: [];
        }
    }

    /**
     * 删除智能班级积分规则缓存
     * @param $learning_id
     * @return mixed
     */
    public function removeRedisLearningClassPointSet($learning_id)
    {
        $cache_key = CacheKeys::LEARNING_CLASS_POINT_SETTING . ':' . $this->aid . '-' . $learning_id;
        return $this->getCacheInstance()->delete($cache_key);
    }

    /**
     * 手动加积分
     * @param $learning_id
     * @param $eid
     * @param $point
     * @param $desc
     * @return false
     * @throws \Key\Exception\DatabaseException
     */
    public function manualAdjustment($learning_id, $eid, $point_score, $desc)
    {
        $condition = [
            'aid' => $this->aid,
            'eid' => $eid,
            'learning_id' => $learning_id,
            'status' => 1,
            'type' => 0
        ];
        $fields = [
            'eid' => 1,
            'display' => 1,
            'point_score' => 1,
        ];
        $learner_info = $this->getMongoMasterConnection()->fetchRow(Constants::COLL_LEARNER, $condition, $fields);

        $num = $point_score + $learner_info['point_score'];

        // if ($num <= 0) {
        //     return self::NOT_ENOUGH_SCORE;
        // }

        $addition = [
            'point_score' => $point_score,
            'desc' => $desc
        ];
        $action_no = 33;
        $result = $this->handleLearningClassPointAdd($learning_id, $eid, $action_no, 0, 0, $addition);

        // $result = $this->handleLearningClassPointAdd($learning_id, $eid, 1, 140041, $addition);

        return $result ?: false;
    }

    /**
     * 查看是否开启积分设置
     * @param $learning_id
     * @param array $fields
     * @return bool
     */
    public function is_open_point_set($learning_id, $fields = [])
    {
        $plan = $this->isExist($learning_id, $fields);
        return $plan['app_setting']['is_point_score'] ? true : false;

    }

    /**
     * 智能班级加积分的queue
     * @param $learning_id
     * @param $eid
     * @param $action_no
     * @return bool
     */
    public function addLearningClassPoint($learning_id, $eid, $action_no, $task_id = 0, $task_type = 0, $addition = [])
    {
        //查看智能班级是否开启积分设置
        $res = $this->is_open_point_set($learning_id, ['app_setting' => 1]);
        if ($res) {
            $message = new QueueMessage($this->aid, $this->eid);
            $params = [
                'learning_id' => $learning_id,
                'eid' => $eid,
                'action_no' => $action_no,
                'task_id' => $task_id,
                'task_type' => $task_type,
                'addition' => $addition,
            ];
            $queue = new BaseQueue($this->app);
            $id = $queue->store('queue_learning_class_point_add', $params);
            $params['q_id'] = $id;
            $message->setPairs($params);
            $queue->queuePublish('queue_learning_class_point_add', $message);
            return $id;
        }

        return true;
    }

    /**
     * 加分方法
     * 如果action_no = 29 记录一次比较 则task_id 传笔记的id
     * @param $learning_id
     * @param $eid
     * @param $action_no
     * @param int $task_id
     * @param array $addition
     * @return bool
     * @throws \Key\Exception\DatabaseException
     */
    public function handleLearningClassPointAdd($learning_id, $eid, $action_no, $task_id = 0, $task_type = 0, $addition = [])
    {
        // error_log('~~~~~~~~~~~~~~~~~~~~~~~learning_id:'.$learning_id);
        // error_log('~~~~~~~~~~~~~~~~~~~~~~~eid:'.$eid);
        // error_log('~~~~~~~~~~~~~~~~~~~~~~~action_no:'.$action_no);
        // error_log('~~~~~~~~~~~~~~~~~~~~~~~task_id:'.$task_id);

        $point_score = 0;
        $setting = [];
        $scoreSetting = $this->pointSetView($this->aid, $learning_id);
        $scoreSetting = array_column($scoreSetting, null, 'action_no');

        $modelLearningTask = new LearningTask($this->app);
        $list = $modelLearningTask->taskList($learning_id);
        $task_ids = array_column($list, 'task_id');

        //获取标题id 和 name
        $topic_info = $this->get_topic_info($action_no);

        // 必修、选修 改版
        $classPoint = new ClassPoint($this->app);
        if (in_array($action_no, [1, 2])) {
            $task_types = array_column($list, 'task_type', 'task_id');
            $real_task_type = $task_types[$task_id] ?? $task_type;
            $action_no = $classPoint->getRealActionNo($action_no, $real_task_type);
        }

        // 当前任务是否加过积分
        if ($action_no !== 33) {
            $log = $classPoint->existPointLog($learning_id, $eid, $task_id, $topic_info['topic_id'], $action_no);
            if ($log) {
                return true;
            }
        }

        if ($topic_info['topic_id'] == 7) {
            $setting = [
                "topic_id" => 7,
                "topic_name" => "手动调分",
                "name" => '手动调分',
                "action_no" => $action_no,
                "score" => $addition['point_score'],
                "num_limit" => 0,
                "is_able" => 1
            ];
            $point_score = $addition['point_score'];
        } else {
            //获取相应分数规则
            $setting = $scoreSetting[$action_no];
            $point_score = $setting['score'];
        }

        //去除不是此智能班级的任务id加分
        //1.完成一个必修任务  2.完成一个选修任务  3.完成结业考试  4.完成结业评估 5.完成全部必修任务  6.完成全部选修任务  7.参与全部互动任务  8.考试成绩达到满分（必修）  9.考试成绩达到满分的80%（必修）  10.考试成绩达到满分的60%（必修）  11.考试成绩达到满分（选修）  12.考试成绩达到满分的80%（选修）  13.考试成绩达到满分的60%（选修）    14.作业获得满分（必修）   15.作业得分达到满分的80%（必修）    16.作业得分达到满分的60%（必修）  17.作业获得满分（选修）   18.作业得分达到满分的80%（选修）    19.作业得分达到满分的60%（选修）  20.考核获得满分（必修）  21.考核得分达到满分的80%（必修）   22.考核得分达到满分的60%（必修）   23.考核获得满分（选修）  24.考核得分达到满分的80%（选修）   25.考核得分达到满分的60%（选修）   26.参与一次投票   27.参与一次调研   28.提交一次学习心得   29.参与一次提问互动     30.参与一次抽奖   31.记录一次学习笔记     32.被评为优秀作业     33.手动调分
        if (!in_array($action_no, [5, 6, 7, 26, 27, 28, 29, 30, 31, 32, 33])) {
            if (!in_array($task_id, $task_ids)) {
                return true;
            }
        }

        if ($action_no == 31) {
            $cond = [
                'aid' => $this->aid,
                'eid' => $eid,
                'related_id' => $learning_id,
                'type' => \App\Models\Note::TYPE_CLASS,
                'status' => static::ENABLED,
                'id' => $task_id,
            ];
            $note = $this->getMongoMasterConnection()->fetchRow(Constants::COLL_NOTE, $cond);
            $task_desc = $note['title'];
        } elseif ($action_no == 33) {
            $task_desc = $addition['desc'];
        } elseif (in_array($action_no, [5, 6, 7])) {
            $task_desc = '';    //任务名字，详情
        } elseif (in_array($action_no, [26, 27, 28, 29, 30])) {
            $cond = [
                'aid' => $this->aid,
                'learning_id' => $learning_id,
                'type' => Constants::LEARNING_CLASS,
                'status' => static::ENABLED,
                'task_id' => $task_id,
            ];
            if ($task_type) {
                $cond['task_type'] = $task_type;
            }
            $task_info = $this->getMongoMasterConnection()->fetchRow(Constants::COLL_LEARNING_INTERACTION, $cond);
            $task_desc = $task_info['task_name'];
        } else {
            $cond = [
                'aid' => $this->aid,
                'learning_id' => $learning_id,
                'type' => Constants::LEARNING_CLASS,
                'status' => static::ENABLED,
                'task_id' => $task_id,
            ];
            if ($task_type) {
                $cond['task_type'] = $task_type;
            }
            $task_info = $this->getMongoMasterConnection()->fetchRow(Constants::COLL_LEARNING_TASK, $cond);
            $task_desc = $task_info['task_name'];
        }

        if (isset($setting['is_able']) && $setting['is_able']) {
            $re = $this->getPointLimit($learning_id, $eid, $task_id, $task_type, $setting, $topic_info['topic_id']);
            if ($re) {
                $result = $this->addPointByEid($learning_id, $eid, $point_score);
                if ($result) {
                    $this->addPointHistory($learning_id, $eid, $task_id, $task_type, $task_desc, $setting, $point_score);//添加积分记录
                    return true;
                }
            }
        }

        return true;
    }

    /**
     * 检查加分次数限制
     * @param $learning_id
     * @param $eid
     * @param $task_id
     * @param $setting
     * @return bool
     * @throws \Key\Exception\DatabaseException
     */
    public function getPointLimit($learning_id, $eid, $task_id, $task_type, $setting, $topic_id)
    {
        $db = $this->getMongoMasterConnection();

        if (in_array($topic_id, [3, 4, 5])) {
            $condition = [
                'aid' => $this->aid,
                'eid' => $eid,
                'status' => 1,
                'learning_id' => $learning_id,
                'topic_id' => $topic_id,
            ];
        } else {
            $condition = [
                'aid' => $this->aid,
                'eid' => $eid,
                'status' => 1,
                'learning_id' => $learning_id,
                'topic_id' => $topic_id,
                'action_no' => $setting['action_no'],
            ];
        }

        $action_nos = range(40, 77);
        if ($task_id) {
            if ($setting['action_no'] != 31 && !in_array($setting['action_no'], $action_nos)) {
                $condition['task_id'] = $task_id;
            }
        }

        if ($task_type && !in_array($setting['action_no'], $action_nos)) {
            $condition['task_type'] = $task_type;
        }

        error_log('========limit_condition====' . json_encode($condition)) . PHP_EOL;
        error_log('========limit_setting====' . json_encode($setting)) . PHP_EOL;
        $count = $db->count(Constants::COLL_LEARNING_CLASS_POINT_LOG, $condition);

        if (isset($setting['num_limit']) && $setting['num_limit']) {
            return ($setting['num_limit'] <= $count) ? false : true;
        }
        return true;
    }

    /**
     * 智能班级学员加积分
     * @param $learning_id
     * @param $eid
     * @param $point_score
     * @return bool
     * @throws \Key\Exception\DatabaseException
     */
    public function addPointByEid($learning_id, $eid, $point_score)
    {
        $condition = [
            'aid' => $this->aid,
            'status' => static::ENABLED,
            'learning_id' => $learning_id,
            'eid' => $eid,
            'type' => 0,
        ];
        $newData = [
            '$inc' => ['point_score' => $point_score,],
            '$set' => ['point_score_time' => Mongodb::getMongoDate()],
        ];
        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNER, $condition, $newData);
        return $result !== false;
    }

    /**
     * 添加积分记录
     * @param $learning_id
     * @param $eid
     * @param $task_id
     * @param $task_desc
     * @param $setting
     * @param $point_score
     * @return bool
     * @throws \Key\Exception\DatabaseException
     */
    public function addPointHistory($learning_id, $eid, $task_id, $task_type, $task_desc, $setting, $point_score)
    {
        //员工信息
        $employeeModel = new Employee($this->app);
        $employee = $employeeModel->getById($eid);
        //learner 表信息
        $learnerModel = new Learner($this->app);
        $learner_info = $learnerModel->isExist($learning_id, 0, [
            'group_id' => 1,
            'group_name' => 1,
        ], [
            'eid' => $eid
        ]);

        $db = $this->getMongoMasterConnection();
        $data['id'] = Sequence::getSeparateId(Constants::COLL_LEARNING_CLASS_POINT_LOG, $this->aid);

        //员工信息
        $data['eid'] = $eid;
        $data['uuid'] = $employee['uuid'] ?? null; // employee UUID
        $data['department_id'] = $employee['department_id'] ?? 0;
        $data['department_name'] = $employee['department_name'] ?? 0;
        $data['dept_path'] = $employee['dept_path'] ?? '';
        $data['position_id'] = $employee['position_id'] ?? 0;
        $data['position_name'] = $employee['position_name'] ?? '';
        $data['enabled'] = $employee['enabled'] ?? 0;
        $data['display'] = $employee['display'] ?? '';
        $data['no'] = $employee['no'] ?? '';

        //分组信息
        $data['group_id'] = $learner_info[0]['group_id'];
        $data['group_name'] = $learner_info[0]['group_name'];

        //积分信息
        $data['aid'] = $this->aid;
        $data['learning_id'] = $learning_id;
        $data['task_id'] = $task_id;
        $data['task_type'] = $task_type;
        $data['topic_id'] = $setting['topic_id'];
        $data['topic_name'] = $setting['topic_name'];
        $data['task_desc'] = $task_desc;
        $data['action_no'] = $setting['action_no'];
        $data['action_desc'] = $setting['name'];
        $data['score'] = $point_score;
        $data['status'] = static::ENABLED;

        $data['created'] = $db->getMongoDate();
        $data['updated'] = $db->getMongoDate();

        $db->insert(Constants::COLL_LEARNING_CLASS_POINT_LOG, $data);

        return true;
    }

    /**
     * 返回标题id 和 积分
     * @param $action_no
     * @return array
     */
    public function get_topic_info($action_no)
    {
        if (in_array($action_no, [1, 2, 3, 4])) {
            return [
                'topic_id' => 1,
                'topic_name' => '基础积分',
            ];
        } elseif (in_array($action_no, [5, 6, 7])) {
            return [
                'topic_id' => 2,
                'topic_name' => '全部完成奖励',
            ];
        } elseif (in_array($action_no, [8, 9, 10, 11, 12, 13])) {
            return [
                'topic_id' => 3,
                'topic_name' => '考试奖励积分',
            ];
        } elseif (in_array($action_no, [14, 15, 16, 17, 18, 19])) {
            return [
                'topic_id' => 4,
                'topic_name' => '作业奖励积分',
            ];
        } elseif (in_array($action_no, [20, 21, 22, 23, 24, 25])) {
            return [
                'topic_id' => 5,
                'topic_name' => '线下考核奖励积分',
            ];
        } elseif (in_array($action_no, [26, 27, 28, 29, 30, 31, 32])) {
            return [
                'topic_id' => 6,
                'topic_name' => '互动任务积分',
            ];
        } else {
            return [
                'topic_id' => 7,
                'topic_name' => '手动调分',
            ];
        }
    }

    /**
     * 获取个人总积分
     * @param $learning_id
     * @param $eid
     * @return array
     * @throws \Key\Exception\DatabaseException
     */
    public function employeePoint($learning_id, $eid)
    {
        $cond = [
            'aid' => $this->aid,
            'status' => 1,
            'learning_id' => $learning_id,
            'eid' => $eid,
            'type' => 0
        ];

        $fields = ['point_score' => 1];

        $result = $this->getMongoMasterConnection()->fetchRow(Constants::COLL_LEARNER, $cond, $fields);

        return $result ? $result : ['point_score' => 0];
    }

    /**
     * 个人积分变动记录
     * @param $learning_id
     * @param $eid
     * @return array
     * @throws \Key\Exception\DatabaseException
     */
    public function pointLog($learning_id, $eid, $pg)
    {
        $cond = [
            'aid' => $this->aid,
            'status' => 1,
            'learning_id' => $learning_id,
            'eid' => $eid,
        ];

        $fields = [
            'action_desc' => 1,
            'task_desc' => 1,
            'score' => 1,
            'created' => 1,
        ];

        if ($pg) {
            $skip = $pg->getoffset();
            $limit = $pg->getItemsPerPage();
        } else {
            $skip = 0;
            $limit = 0;
        }

        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_CLASS_POINT_LOG, $cond, $skip, $limit, ['created' => -1], $fields);
        foreach ($result as $key => $val) {
            if (empty($result[$key]['task_desc'])) {
                $result[$key]['task_desc'] = '--';
            }
            $result[$key]['created'] = (string)$val['created'];
        }

        return $result ? $result : [];
    }

    /**
     * 返回积分明细日志的数量
     * @param $learning_id
     * @param $eid
     * @return int
     * @throws \Key\Exception\DatabaseException
     */
    public function get_point_log_total($learning_id, $eid)
    {
        $cond = [
            'aid' => $this->aid,
            'status' => 1,
            'learning_id' => $learning_id,
            'eid' => $eid,
        ];
        $total = $this->getMongoMasterConnection()->count(Constants::COLL_LEARNING_CLASS_POINT_LOG, $cond);

        return $total ? $total : 0;
    }

    /**
     * 导出个人积分明细queue
     * @param $learning_id
     * @param $eid
     * @return bool
     */
    public function export_point_log($learning_id, $eid)
    {
        $message = new QueueMessage($this->aid, $this->eid);
        $params['learning_id'] = $learning_id;
        $params['eid'] = $eid;

        $queue = new BaseQueue($this->app);
        $id = $queue->store('queue_export_learning_class_point_log', $params);
        $params['q_id'] = $id;
        $message->setPairs($params);
        $queue->queuePublish('queue_export_learning_class_point_log', $message);
        return $id;
    }

    /**
     * 个人排名数据
     * @param $learning_id
     * @param $group_id
     * @return array
     * @throws \Key\Exception\DatabaseException
     */
    public function learnerRanking($learning_id, $group_id)
    {
        $learner = $this->getMongoMasterConnection()->fetchRow(Constants::COLL_LEARNER, [
            'aid' => $this->aid,
            'status' => static::ENABLED,
            'type' => 0,
            'eid' => $this->eid,
            'learning_id' => (int)$learning_id,
            'group_id' => (int)$group_id,
        ], [
            'eid' => 1,
            'display' => 1,
            'group_id' => 1,
            'point_score' => 1,
        ]);

        $result = [];
        if ($learner) {
            /** @var \App\Models\Employee $employee_model */
            $employee_model = new Employee($this->app);
            $employee = $employee_model->view($this->eid);

            $result['avatar'] = ArrayGet($employee, 'avatar', '');
            $result['display'] = ArrayGet($employee, 'display', '');
            $result['point_score'] = ArrayGet($learner, 'point_score', '');

            //已超越
            $result['beyond'] = $this->learnerRankingNum($learning_id, $group_id);
            //总数
            $cond = [
                'aid' => $this->aid,
                'status' => static::ENABLED,
                'type' => 0,
                'learning_id' => $learning_id,
            ];
            if ($group_id != -1) $cond['group_id'] = $group_id;
            $total = $this->getMongoMasterConnection()->count(Constants::COLL_LEARNER, $cond);
            //当前排名
            $result['ranking'] = (int)$total - $result['beyond'];
        }

        return $result ? $result : [];
    }

    /**
     * 超过人数
     * @param $learning_id
     * @param int $group_id
     * @return int|string
     * @throws \Key\Exception\DatabaseException
     */
    public function learnerRankingNum($learning_id, $group_id = -1)
    {
        $cond = [
            'aid' => $this->aid,
            'status' => static::ENABLED,
            'type' => 0,
            'learning_id' => $learning_id,
        ];
        if ($group_id != -1) $cond['group_id'] = $group_id;
        $db = $this->getMongoMasterConnection();
        $total = $db->count(Constants::COLL_LEARNER, $cond);
        if (!$total) return 0;

        $list = $db->fetchAll(Constants::COLL_LEARNER, $cond, 0, 0, ['point_score' => -1, 'point_score_time' => 1], ['eid' => 1]);

        $rank = 0;
        foreach ($list as $key => $value) {
            if ($this->eid == $value['eid']) {
                $rank = $key + 1;
                break;
            }
        }

        return $total - $rank;
    }

    /**
     * 智能班级积分排行榜
     * @param $learning_id
     * @return array
     * @throws \Key\Exception\DatabaseException
     */
    public function pointRank($learning_id, $pg)
    {
        $cond = [
            'aid' => $this->aid,
            'status' => 1,
            'learning_id' => $learning_id,
            'type' => 0,
        ];
        $fields = [
            'eid' => 1,
            'learning_rate' => 1,
            'point_score' => 1,
        ];

        if ($pg) {
            $skip = $pg->getoffset();
            $limit = $pg->getItemsPerPage();
        } else {
            $skip = 0;
            $limit = 0;
        }

        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNER, $cond, $skip, $limit, ['point_score' => -1, 'point_score_time' => 1], $fields);
        $employee_model = new Employee($this->app);
        $list = [];
        // $i = 1;
        foreach ($result as $key => $value) {
            $info = $employee_model->view($value['eid'], 0, [
                'id' => 1,
                'no' => 1,
                'display' => 1,
                'avatar' => 1,
            ]);
            $list[$key] = [
                'eid' => $info['id'],
                'no' => $info['no'],
                'display' => $info['display'],
                'avatar' => $info['avatar'] ? $info['avatar'] : '',
                'learning_rate' => sprintf("%.2f", $value['learning_rate']),
                'point_score' => $value['point_score'] ? $value['point_score'] : 0,
                // 'rank_id' => $i,
            ];
            // $i ++;
        }
        return $list ? $list : [];
    }

    /**
     * 排行榜总数
     * @param $learning_id
     * @return int
     * @throws \Key\Exception\DatabaseException
     */
    public function getRankTotal($learning_id)
    {
        $cond = [
            'aid' => $this->aid,
            'status' => 1,
            'learning_id' => $learning_id,
            'type' => 0
        ];
        $total = $this->getMongoMasterConnection()->count(Constants::COLL_LEARNER, $cond);
        return $total ? $total : 0;
    }

    /**
     * 智能班级 积分分组排行
     * @param $learning_id
     * @param $type
     * @param $pg
     * @return array|mixed
     * @throws \Key\Exception\DatabaseException
     */
    public function getGroupRank($learning_id, $type, $pg)
    {
        $groups = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNER_PK_GROUP, [
            'aid' => $this->aid,
            'id' => ['$nin' => [0]],
            'learning_id' => $learning_id,
            'status' => static::ENABLED,
        ], 0, 0, [
            'created' => 1
        ], [
            'id' => 1,
            'name' => 1,
        ]);

        if ($groups) {
            $index_groups = array_column($groups, NULL, 'id');
            if ($pg) {
                $skip = $pg->getoffset();
                $limit = $pg->getItemsPerPage();
            } else {
                $skip = 0;
                $limit = 0;
            }

            $group_ids = array_column($groups, 'id');
            $condition = [
                'aid' => $this->aid,
                'learning_id' => $learning_id,
                'status' => static::ENABLED,
                'group_id' => ['$in' => $group_ids],
                'type' => 0
            ];
            $group = [
                '_id' => '$group_id',
                'group_emp' => ['$sum' => '$status'],
                'group_total_point' => ['$sum' => '$point_score'],
                'group_avg_point' => ['$avg' => '$point_score'],
            ];

            if ($type == 1) {
                $sort = ['group_total_point' => -1];
            } else {
                $sort = ['group_avg_point' => -1];
            }

            $pipeline = [
                ['$match' => $condition],
                ['$group' => $group],
                ['$sort' => $sort],
                ['$skip' => $skip],
                ['$limit' => $limit],
            ];

            $result = $this->getMongoMasterConnection()->aggregate(Constants::COLL_LEARNER, $pipeline, ['allowDiskUse' => true]);
            foreach ($result as $key => $value) {
                $result[$key]['id'] = $index_groups[$value['_id']]['id'];
                $result[$key]['name'] = $index_groups[$value['_id']]['name'];
                $result[$key]['group_avg_point'] = round($value['group_avg_point'], 1);
            }
        }

        return $result ? $result : [];

    }

    /**
     * 积分分组排行 总数
     * @param $learning_id
     * @return int
     * @throws \Key\Exception\DatabaseException
     */
    public function getGroupRankTotal($learning_id)
    {
        $total = $this->getMongoMasterConnection()->count(Constants::COLL_LEARNER_PK_GROUP, [
            'aid' => $this->aid,
            'id' => ['$nin' => [0]],
            'learning_id' => $learning_id,
            'status' => static::ENABLED,
        ]);

        return $total ? $total : 0;

    }

    /**
     * 组内排行榜
     * @param $learning_id
     * @param $group_id
     * @param $pg
     * @return array
     * @throws \Key\Exception\DatabaseException
     */
    public function getGroupEmpRank($learning_id, $group_id, $pg)
    {
        $cond = [
            'aid' => $this->aid,
            'status' => 1,
            'learning_id' => $learning_id,
            'group_id' => $group_id,
            'type' => 0,
        ];
        $fields = [
            'eid' => 1,
            'learning_rate' => 1,
            'point_score' => 1,
        ];

        if ($pg) {
            $skip = $pg->getoffset();
            $limit = $pg->getItemsPerPage();
        } else {
            $skip = 0;
            $limit = 0;
        }

        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNER, $cond, $skip, $limit, ['point_score' => -1, 'point_score_time' => 1], $fields);
        $emp_ids = array_column($result, 'eid');
        $employee_model = new Employee($this->app);
        $emp_list = $employee_model->getByIds($emp_ids, $fields = [
            'id' => 1,
            'no' => 1,
            'display' => 1,
            'avatar' => 1,
        ]);
        $index_emp_list = array_column($emp_list, null, 'id');

        $list = [];
        foreach ($result as $key => $value) {
            $list[$key] = [
                'eid' => $index_emp_list[$value['eid']]['id'],
                'no' => $index_emp_list[$value['eid']]['no'],
                'display' => $index_emp_list[$value['eid']]['display'],
                'avatar' => $index_emp_list[$value['eid']]['avatar'] ? $index_emp_list[$value['eid']]['avatar'] : '',
                'learning_rate' => sprintf("%.2f", $value['learning_rate']),
                'point_score' => $value['point_score'] ? $value['point_score'] : 0,
            ];
        }
        return $list ? $list : [];
    }

    /**
     * 组内排行榜总数
     * @param $learning_id
     * @param $group_id
     * @return int
     * @throws \Key\Exception\DatabaseException
     */
    public function getGroupEmpRankTotal($learning_id, $group_id)
    {
        $cond = [
            'aid' => $this->aid,
            'status' => 1,
            'learning_id' => $learning_id,
            'group_id' => $group_id,
            'type' => 0
        ];
        $total = $this->getMongoMasterConnection()->count(Constants::COLL_LEARNER, $cond);
        return $total ? $total : 0;
    }

    /**
     * 数据看板 ==》 积分统计
     * @param $learning_id
     * @param $group_id
     * @param $keyword
     * @param $pg
     * @return array
     * @throws \Key\Exception\DatabaseException
     */
    public function pointStatistics($learning_id, $group_id, $keyword, $pg)
    {
        $cond = [
            'aid' => $this->aid,
            'status' => static::ENABLED,
            'type' => 0,
            'learning_id' => $learning_id,
            'is_examine' => 1,
            'job.enabled' => Constants::STATUS_ENABLED
        ];

        //-1全部 0未分组 group_id分组id
        if ($group_id != -1) {
            $cond['group_id'] = $group_id;
        }

        if ($keyword) {
            $cond['display'] = ['$regex' => $keyword, '$options' => 'im'];
        }

        if ($pg) {
            $skip = $pg->getoffset();
            $limit = $pg->getItemsPerPage();
        } else {
            $skip = 0;
            $limit = 0;
        }

        $fields = [
            'eid' => 1,
            'group_id' => 1,
            'group_name' => 1,
            'display' => 1,
            'point_score' => 1,
            'job' => 1,
        ];
        //学员列表
        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNER, $cond, $skip, $limit, ['point_score' => -1], $fields);

        $group = [
            '_id' => [
                'eid' => '$eid',
                'topic_id' => '$topic_id'
            ],
            'topic_id' => ['$first' => '$topic_id'],
            'topic_name' => ['$last' => '$topic_name'],
            'topic_score' => ['$sum' => '$score'],
        ];

        $sort = ['topic_id' => 1];

        $project = [
            '_id' => 0,
            'topic_id' => 1,
            'topic_name' => 1,
            'topic_score' => 1,
        ];

        $arr = [
            'base_score' => 0,
            'all_rate_score' => 0,
            'exam_score' => 0,
            'task_work_score' => 0,
            'offline_assess_score' => 0,
            'interact_score' => 0,
            'manual_score' => 0,
        ];

        $employee_model = new Employee($this->app);
        $department_model = new Department($this->app);
        foreach ($result as $key => $value) {
            $result[$key]['point_score'] = $value['point_score'] ? $value['point_score'] : 0;
            if ($value['group_id'] === 0) {
                $result[$key]['group_name'] = '未分组';
            }
            $result[$key]['no'] = $value['job']['no'];
            $result[$key]['department_name'] = $value['job']['department_name'];
            $result[$key]['position_name'] = $value['job']['position_name'];
            $depts = $department_model->getMyAndParentDepartment($value['job']['department_id']);
            $depts_info = array_column($depts, 'name');
            $result[$key]['first_dept'] = $depts_info[0] ? $depts_info[0] : '';
            $result[$key]['second_dept'] = $depts_info[1] ? $depts_info[1] : '';
            $result[$key]['third_dept'] = $depts_info[2] ? $depts_info[2] : '';
            $result[$key]['fourth_dept'] = $depts_info[3] ? $depts_info[3] : '';

            //添加默认 分值 0
            $result[$key] = array_merge($result[$key], $arr);

            $match = [
                'aid' => $this->aid,
                'eid' => $value['eid'],
                'learning_id' => $learning_id,
                'status' => static::ENABLED,
            ];
            $pipeline = [
                ['$match' => $match],
                ['$group' => $group],
                ['$sort' => $sort],
                ['$project' => $project],
            ];
            $data = $this->getMongoMasterConnection()->aggregate(Constants::COLL_LEARNING_CLASS_POINT_LOG, $pipeline);
            // echo json_encode($data);die;
            // $result[$key]['topic_list'] = $data;
            foreach ($data as $k => $v) {
                switch ($v['topic_id']) {
                    case 1:
                        $result[$key]['base_score'] = $v['topic_score'];
                        break;
                    case 2:
                        $result[$key]['all_rate_score'] = $v['topic_score'];
                        break;
                    case 3:
                        $result[$key]['exam_score'] = $v['topic_score'];
                        break;
                    case 4:
                        $result[$key]['task_work_score'] = $v['topic_score'];
                        break;
                    case 5:
                        $result[$key]['offline_assess_score'] = $v['topic_score'];
                        break;
                    case 6:
                        $result[$key]['interact_score'] = $v['topic_score'];
                        break;
                    default:
                        $result[$key]['manual_score'] = $v['topic_score'];
                        break;
                }
            }
        }

        return $result ? $result : [];
    }

    /**
     * 智能班级积分统计 总数
     * @param $learning_id
     * @param $group_id
     * @param $keyword
     * @return int
     * @throws \Key\Exception\DatabaseException
     */
    public function pointPointStatisticsTotal($learning_id, $group_id, $keyword)
    {
        $cond = [
            'aid' => $this->aid,
            'status' => static::ENABLED,
            'type' => 0,
            'learning_id' => $learning_id,
            'is_examine' => 1,
            'job.enabled' => Constants::STATUS_ENABLED
        ];

        //-1全部 0未分组 group_id分组id
        if ($group_id != -1) {
            $cond['group_id'] = $group_id;
        }

        if ($keyword) {
            $cond['display'] = ['$regex' => $keyword, '$options' => 'im'];
        }

        //学员列表
        $total = $this->getMongoMasterConnection()->count(Constants::COLL_LEARNER, $cond);

        return $total ? $total : 0;
    }

    /**
     * 积分统计导出
     * @param $learning_id
     * @param $group_id
     * @param $keyword
     * @return bool
     */
    public function export_point_Statistics($learning_id, $group_id, $keyword)
    {
        $message = new QueueMessage($this->aid, $this->eid);
        $params['learning_id'] = $learning_id;
        $params['group_id'] = $group_id;
        $params['keyword'] = $keyword;

        $queue = new BaseQueue($this->app);
        $id = $queue->store('queue_export_learning_class_point_statistics', $params);
        $params['q_id'] = $id;
        $message->setPairs($params);
        $queue->queuePublish('queue_export_learning_class_point_statistics', $message);
        return $id;
    }

    /**
     * Event: `learningClassPoint:adding`.
     * @param $learning_id
     * @param $eid
     * @param $action_no
     * @param int $task_id
     * @param array $addition
     */
    public function onLearningClassPointAdding($learning_id, $eid, $action_no, $task_id = 0, $task_type = 0, $addition = [])
    {
        $this->addLearningClassPoint($learning_id, $eid, $action_no, $task_id, $task_type, $addition);
    }

//*************************************************************************


    public function getIndexPlanInfoByIds($learning_ids, $type, $fields = [])
    {
        if (!is_array($type)) $type = [$type];
        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'status' => ['$nin' => [static::DISABLED]],
            'id' => ['$in' => $learning_ids],
            'type' => ['$in' => $type]
        ], 0, 0, [], $fields ?: [
            'id' => 1,
            'name' => 1
        ]);

//        return $result ? json_decode(json_encode(array_column($result, null, 'id')), true) : [];
        return $result ? array_column($result, null, 'id') : [];
    }

    /**
     * 考试统计，获取所有学习模块儿的名称
     * @param array $learning_ids
     * @return array
     */
    public function getAllLearningNameByIds($learning_ids)
    {
        //智能班级，学习计划
        $plan_result = $this->getIndexPlanInfoByIds($learning_ids, [
            Constants::LEARNING_PLAN, Constants::LEARNING_CLASS
        ], [
            'id' => 1,
            'name' => 1,
            'type' => 1
        ]);
        //学习包，带教，发展地图，鉴定，岗位学习地图
        $pack_model = new LearningPack($this->app);
        $pack_result = $pack_model->getIndexLearningInfoByIds($learning_ids, [
            Constants::LEARNING_PACK, Constants::LEARNING_COURSE_PACK, Constants::LEARNING_SPECIAL_TOPIC_PACK, Constants::LEARNING_TRAINING, Constants::LEARNING_MAP,
            Constants::LEARNING_APPRAISAL, Constants::LEARNING_POSITION
        ], [
            'id' => 1,
            'name' => 1,
            'type' => 1
        ]);
        //闯关
        $pass_model = new Breakthrough($this->app);
        $pass_result = $pass_model->getIndexLearningInfoByIds($learning_ids, [
            Constants::LEARNING_BREAKTHROUGH
        ], [
            'id' => 1,
            'name' => 1,
            'type' => 1
        ]);
        //认证与再培训
        $retraing_model = new Retraining($this->app);
        $retraing_result = $retraing_model->getIndexLearningInfoByIds($learning_ids, [
            Constants::RETRAINING
        ], [
            'id' => 1,
            'title' => 1,
            'type' => 1
        ]);
        //营销一体化
        $marketing_model = new MarketingUnion($this->app);
        $marketing_result = $marketing_model->getIndexLearningInfoByIds($learning_ids, [
            Constants::LEARNING_MARKETING
        ], [
            'id' => 1,
            'title' => 1,
            'type' => 1
        ]);
        //$plan_result $pack_result $pass_result $retraing_result $marketing_result
        $result = [];
        if ($plan_result) $this->transLearningIndexVal($plan_result, $result);
        if ($pack_result) $this->transLearningIndexVal($pack_result, $result);
        if ($pass_result) $this->transLearningIndexVal($pass_result, $result);
        if ($retraing_result) $this->transLearningIndexVal($retraing_result, $result);
        if ($marketing_result) $this->transLearningIndexVal($marketing_result, $result);

        return $result;
    }

    public function transLearningIndexVal(&$result, $type = -1)
    {
        if ($result) {
            foreach ($result as $k => $val) {
                if (!isset($val['name'])) $val['name'] = ArrayGet($val, 'title', '');
                if (!isset($val['type'])) $val['type'] = $type;
                $result[$k] = $val;
            }
        }
    }

    public function getIdsByDepts($type, $depts, $fields = [])
    {

        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'type' => ['$in' => $type],
            'status' => static::ENABLED,
            'organization.id' => ['$in' => $depts],
        ], 0, 0, [], $fields ?: [
            'id' => 1,
            'name' => 1
        ]);

        return $result ? array_values(array_unique(array_column($result, 'id'))) : [];
    }

    /**
     * PC学员端代报名多班次中班级列表.
     *
     * @param array $filters .
     * @param int $type .
     * @param int $project_id .
     * @param null|\Key\Records\Pagination $pg .
     * @param string $sort .
     * @param array $fields .
     * @return array
     */
    public function getProjectDelegatePlanList($project_id, $status, $is_applicant, &$total, $sort, $pg, $fields = [])
    {
        if (!$pg) {
            $pg = new Pagination();
            $pg->setPage(0)->setItemsPerPage(0);
        }

        $cond = [
            'aid' => $this->aid,
            'type' => Constants::LEARNING_CLASS,
            'project_id' => $project_id,
            'status' => $status,
            'is_applicant' => $is_applicant
        ];

        $total = $this->getMongoMasterConnection()->count(Constants::COLL_LEARNING_PLAN, $cond);

        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, $cond,
            $pg->getOffset(),
            $pg->getItemsPerPage(),
            $sort ?: [
                'is_end' => 1,
                'status' => 1,
                'updated' => -1
            ], $fields ?: [
                'aid' => 0,
                'desc' => 0
            ]
        );

        if ($result) {
            $learning_ids = array_column($result, 'id');

            $modelLearner = new Learner($this->app);

            $filters['is_examine'] = -1;

            $learning_learners = $modelLearner->getLearningsLearnerNum($learning_ids, Constants::LEARNING_CLASS, $filters, 2);

            $register_learners = $modelLearner->getLearningsLearnerNumExamine($learning_ids, Constants::LEARNING_CLASS, $filters);

            foreach ($result as $key => $value) {
                $value['start_time'] = $value['start_time'] ? (string)$value['start_time'] : '';
                $value['end_time'] = $value['end_time'] ? (string)$value['end_time'] : '';
                $value['register_num'] = ArrayGet($register_learners, $value['id'] . '_1', 0);
                $value['review_num'] = ArrayGet($learning_learners, $value['id'], 0);
                $result[$key] = $value;
            }


//            $modelLearningProject = new LearningProject($this->app);
//            //我负责的多班次中的所有条件
//            $delegates = $modelLearningProject->getPersonDelegateView($project_id);
//
//            //多班次中，满足我负责的所有条件的人数
//            $department_ids = array_column($delegates, 'department_id'); //所有条件中的部门
//
//            if ($department_ids) {
//                $scope = [];
//                foreach ($department_ids as $department_id) {
//                    $item = ['id' => $department_id, 'children_included' => 1];
//                    $scope[] = $item;
//                }
//                $modelDepartment = new Department($this->app);
//                $modelDepartment->setWithoutACL(true);
//                $department_ids = $modelDepartment->getChildrenWithScope(null, null, $scope, ['id' => 1]);
//                $department_ids = array_column($department_ids, 'id');
//            }
//
//            $position_infos = array_column($delegates, 'position');
//
//            $position_ids = [];
//            if ($position_infos) {
//                foreach ($position_infos as $pos_k => $pos_v) {
//                    foreach ($pos_v as $pos_v_k => $pos_v_v) {
//                        if ($pos_v_v['id']) {
//                            array_push($position_ids, $pos_v_v['id']);
//                        }
//
//                    }
//                }
//            }
//
//            //所有班级
//            $learning_ids = array_column($result, 'id');
//
//            $filters['is_examine'] = -1;
//            $filters['dept_id'] = $department_ids;
//            $filters['position_id'] = $position_ids;
//            $modelLearner = new Learner($this->app);
//
//
//            $review_learning_learners = $modelLearner->getLearningsLearnerNum($learning_ids, Constants::LEARNING_CLASS, $filters, 2);
//
//            $filters['is_examine'] = 1;
//            $register_learning_learners = $modelLearner->getLearningsLearnerNum($learning_ids, Constants::LEARNING_CLASS, $filters, 2);
//            foreach ($result as $key => $value) {
//                $value['start_time'] = $value['start_time'] ? (string)$value['start_time'] : '';
//                $value['end_time'] = $value['end_time'] ? (string)$value['end_time'] : '';
//                $value['register_num'] = ArrayGet($register_learning_learners, $value['id'], 0);
//                $value['review_num'] = ArrayGet($review_learning_learners, $value['id'], 0);
//                $result[$key] = $value;
//            }
        }

        return $result ?: [];
    }

    /**
     * PC学员端智能班级代报名列表
     * @param $type
     * @param $keyword
     * @param $filter
     * @param $sort
     * @param $pg
     * @return array
     * @throws \Key\Exception\DatabaseException
     */
    public function getDelegatePlanList($type = Constants::LEARNING_CLASS, $keyword, $filter, $sort, $pg, &$total)
    {
        if (!$pg) {
            $pg = new Pagination();
            $pg->setPage(0)->setItemsPerPage(0);
        }

        $cond = [
            'aid' => $this->aid,
            'type' => $type,
            'is_applicant' => 1,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]]
        ];

        //获取当前人的身份
        $employee_model = new Employee($this->app);
        $employee = $employee_model->view($this->eid, $this->aid, ['department_id' => 1, 'roles' => 1, 'groups' => 1], Employee::ENABLED, 1);
        $roles = ArrayGet($employee, 'roles', []);
        //是否是直属上级
        $is_superior = in_array(Permission::DATA_PERMISSION_DOTTED_LINE_REPORT, $roles) ? 1 : 0;
        //是否是部门经理
        $is_manager = in_array(Permission::DATA_PERMISSION_DEPARTMENT_CUSTOM, $roles) ? 1 : 0;

        //is_delegate_type 2-部门经理 3-直属上级 4指定人员 5导入人员
        $cond['$or'] = [
            [
                'is_delegate_type' => 4,
                'is_delegate_emp' => [
                    '$elemMatch' => [
                        '$or' => [
                            ['type' => self::APPLIED_RANGE_EMPLOYEE, 'id' => $this->eid],
                            ['type' => LearningPlan::APPLIED_RANGE_GROUP, 'id' => ['$in' => ArrayGet($employee, 'groups', [])]],
                        ]
                    ]
                ]
            ],
            [
                'is_delegate_type' => 5,
                'is_delegate_emp' => [
                    '$elemMatch' => ['type' => self::APPLIED_RANGE_EMPLOYEE, 'id' => $this->eid]
                ]
            ],
        ];
        if ($is_superior) {
            $cond['$or'][] = ['is_delegate_type' => 3];
        }
        if ($is_manager) {
            $cond['$or'][] = ['is_delegate_type' => 2];
        }

        switch ($filter) {
            case 1:
                //获取未开始学习计划的数量(已发布,当前时间<开始时间) 外派培训--未开始.
                $cond['is_end'] = 0;
                $cond['status'] = 2;
                $cond['start_time'] = ['$gt' => Mongodb::getMongoDate(time())];
                break;
            case 2:
                //获取未完成学习计划的数量(已发布,开始时间<当前时间<结束时间,完成率不到100%) 外派培训--进行中.
                $cond['status'] = 2;
                $cond['is_end'] = 0;
                $cond['start_time'] = ['$lte' => Mongodb::getMongoDate(time())];
                $cond['end_time'] = ['$gt' => Mongodb::getMongoDate(time())];
                $cond['finished_rate'] = ['$lt' => 100];
                break;
            case 3:
                //获取已完成学习计划的数量(已发布,开始时间<当前时间<结束时间,完成率100%).
                $cond['status'] = 2;
                $cond['is_end'] = 0;
                if ($type == Constants::LEARNING_TRAINING) {
                    $cond['end_time'] = ['$lt' => Mongodb::getMongoDate(time())];
                } else {
                    $cond['start_time'] = ['$lt' => Mongodb::getMongoDate(time())];
                    $cond['finished_rate'] = 100;
                }
                break;
            case 4:
                //获取已延期学习计划的数量(已发布,当前时间>结束时间,完成率<100%)  外派培训--已延期.
                $cond['status'] = 2;
                $cond['is_end'] = 0;
                $cond['end_time'] = ['$lt' => Mongodb::getMongoDate(time())];
                $cond['finished_rate'] = ['$lt' => 100];
                break;
            case 5:
                //获取未发布学习计划的数量  外派培训--草稿
                $cond['learning_status'] = self::LEARNING_UNPUBLISH;
                $cond['status'] = static::ENABLED;
                break;
            case 6:
                //已结束  外派培训--已结束
                $cond['is_end'] = 1;
                break;
            case 7:
                //待提交  智能班级-需要学员确认
                $cond['learner_confirm'] = 1;
                $cond['learning_status'] = self::LEARNING_UNSUBMIT;
                break;
        }
        if ($keyword) {
            $or = [
                ['name' => new Regex($keyword, 'im')],
                ['display' => new Regex($keyword, 'im')],
            ];
            if (ArrayGet($cond, '$or')) {
                $cond['$and'] = [
                    ['$or' => $cond['$or']],
                    ['$or' => $or],
                ];
                unset($cond['$or']);
            } else {
                $cond['$or'] = $or;
            }
        }

        $total = $this->getMongoMasterConnection()->count(Constants::COLL_LEARNING_PLAN, $cond);

        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, $cond,
            $pg->getOffset(),
            $pg->getItemsPerPage(),
            $sort ?: [
                'is_end' => 1,
                'status' => 1,
                'updated' => -1
            ], [
                'aid' => 0,
                'desc' => 0
            ]
        );

        if ($result) {
            //所有班级
            $learning_ids = array_column($result, 'id');

            $filters['is_examine'] = -1;
            $modelLearner = new Learner($this->app);
            $learning_learners = $modelLearner->getLearningsLearnerNum($learning_ids, Constants::LEARNING_CLASS, $filters, 2);
            $register_learners = $modelLearner->getLearningsLearnerNumExamine($learning_ids, Constants::LEARNING_CLASS, $filters);
            foreach ($result as $key => $value) {
                $value['start_time'] = $value['start_time'] ? (string)$value['start_time'] : '';
                $value['end_time'] = $value['end_time'] ? (string)$value['end_time'] : '';
                $value['register_num'] = ArrayGet($register_learners, $value['id'] . '_1', 0);
                $value['review_num'] = ArrayGet($learning_learners, $value['id'], 0);
                $result[$key] = $value;
            }
        }

        return $result ?: [];
    }

    public function clearGroupLeader($learning_id, $group_ids = [])
    {
        $cond = [
            'aid' => $this->aid,
            'learning_id' => $learning_id,
            'status' => self::ENABLED,
        ];
        if ($group_ids) {
            $cond['id'] = ['$in' => $group_ids];
        }
        $this->getMongoMasterConnection()->update(Constants::COLL_LEARNER_PK_GROUP, $cond, [
            '$set' => [
                'group_leader' => [],
                'updated' => Mongodb::getMongoDate()
            ]
        ]);
    }

    /**
     * 复制班级分组
     * @param $old_learning_id 原班级ID
     * @param $new_learning_id 新班级ID
     * @param $project_id 多班次ID
     * @param $ignore_group_ids
     * @param $aid
     * @param $eid
     * @return bool|int
     * @throws \Key\Exception\DatabaseException
     */
    public function copyPkGroups($old_learning_id, $new_learning_id, $ignore_group_ids = [0])
    {

        $cond = [
            'aid' => $this->aid,
            'learning_id' => $old_learning_id,
            'status' => self::ENABLED,
        ];

        if ($ignore_group_ids) {
            $cond['id'] = ['$nin' => $ignore_group_ids];
        }

        $groups = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNER_PK_GROUP, $cond);
        if (!$groups) return true;

        $new_groups = [];
        foreach ($groups as $group) {

            $item_pk_group = [
                'learning_id' => $new_learning_id,
                'old_group_id' => $group['id'],
                'created' => Mongodb::getMongoDate(),
                'updated' => Mongodb::getMongoDate()
            ];

            if ($group['id']) {
                $item_pk_group['id'] = Sequence::getSeparateId('learner_pk_group', $this->aid);
            }

            $item_pk_group = array_merge($group, $item_pk_group);

            $new_groups[] = $item_pk_group;
        }

        if ($new_groups) {
            return $this->getMongoMasterConnection()->batchInsert(Constants::COLL_LEARNER_PK_GROUP, $new_groups);
        }

        return true;
    }

    /**
     * 复制班级，处理原班级和新班级之间的数据对应关系
     * @param $old_learning_id
     * @param $type
     * return boolean
     * @throws \Key\Exception\DatabaseException
     */
    public function handleCopyLearningPlan($new_learning_id, $old_learning_id, $type, $eids = [])
    {

        $modelLearner = new Learner($this->app);
        $learner_total = $modelLearner->getClassLearner($old_learning_id, Constants::LEARNING_CLASS);

        $learner_cond = [
            'aid' => $this->aid,
            'status' => static::ENABLED,
            'learning_id' => $old_learning_id,
            'type' => $type,
            'is_examine' => self::ENABLED,
            'job.enabled' => self::ENABLED
        ];
        if ($eids && is_array($eids)) $learner_cond['eid'] = ['$in' => $eids];

        $limit = 100;
        $page = ceil($learner_total / $limit);
        $pg = new Pagination();
        for ($i = 1; $i <= $page; $i++) {
            $pg->setPage($i)->setItemsPerPage($limit);

            $learners = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNER, $learner_cond, $pg->getOffset(), $pg->getItemsPerPage(), ['eid' => 1, 'learning_id' => 1]);

            //处理PK分组信息
            $old_group_ids = array_values(array_unique(array_column($learners, 'group_id')));
            $group_cond = [
                'aid' => $this->aid,
                'learning_id' => $new_learning_id,
                'status' => self::ENABLED,
                'old_group_id' => ['$in' => $old_group_ids]
            ];
            $pk_groups = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNER_PK_GROUP, $group_cond, 0, 0, [], ['id' => 1, 'old_group_id' => 1, 'name' => 1]);
            $new_pk_group = [];
            foreach ($pk_groups as $pk_group) {
                if (!isset($new_pk_group[$pk_group['old_group_id']])) {
                    $new_pk_group[$pk_group['old_group_id']] = ['id' => $pk_group['id'], 'name' => $pk_group['name']];
                }
            }
            unset($pk_groups);


            $update_data = [];
            foreach ($learners as $learner_k => $learner_v) {

                $new_data = [
                    'condition' => ['aid' => $this->aid, 'status' => static::ENABLED, 'learning_id' => $new_learning_id, 'type' => $type, 'is_examine' => self::ENABLED, 'job.enabled' => self::ENABLED, 'eid' => $learner_v['eid']],
                    'new_data' => [
                        '$set' => [
                            'group_id' => $new_pk_group[$learner_v['group_id']]['id'] ?: 0,
                            'group_name' => $new_pk_group[$learner_v['group_id']]['name'] ?: '',
                            'is_group_leader' => $learner_v['is_group_leader']
                        ]
                    ]
                ];

                $update_data[] = $new_data;
            }

            $result = $this->getMongoMasterConnection()->batchUpdate(Constants::COLL_LEARNER, $update_data);
        }

    }

    /**
     * 通过learning_plan的learner_num计算所有多班次的人数
     * @param $project_ids
     * @param int $type
     * @return array
     * @throws \Key\Exception\DatabaseException
     */
    public function getLearnerNumByProjectIds($project_ids, $type = Constants::LEARNING_CLASS)
    {
        $match = [
            'aid' => $this->aid,
            'project_id' => ['$in' => $project_ids],
            'type' => $type,
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]]
        ];

        $group = ['_id' => '$project_id', 'num' => ['$sum' => '$learner_num']];

        $project = [
            '_id' => 0,
            'project_id' => '$_id',
            'num' => 1,
        ];

        $pipeline = [
            ['$match' => $match],
            ['$group' => $group],
            ['$project' => $project]
        ];
        $result = $this->getMongoMasterConnection()->aggregate(Constants::COLL_LEARNING_PLAN, $pipeline);

        return $result ?: [];
    }

    /**
     * 修改简化班任务设置
     * @param $learning_id
     * @param $type
     * @param $rules
     * @return bool
     */
    public function setSimplePlanSetting($learning_id = 0, $type = Constants::LEARNING_CLASS, $new_data = [])
    {

        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_SIMPLE_GROUP, [
            'learning_id' => (int)$learning_id,
            'type' => (int)$type,
            'aid' => $this->aid,
            'status' => static::ENABLED
        ], [
            '$set' => $new_data
        ]);

        return $result;
    }

    /**
     * 获取简化版任务设置
     * @param $learning_id
     * @param $type
     * @return array|null
     * @throws \Key\Exception\DatabaseException
     */
    public function getSimplePlanSetting($learning_id, $type = Constants::LEARNING_CLASS)
    {

        $result = $this->getMongoMasterConnection()->fetchRow(Constants::COLL_LEARNING_SIMPLE_GROUP, [
            'learning_id' => (int)$learning_id,
            'type' => (int)$type,
            'aid' => $this->aid,
            'status' => static::ENABLED
        ]);

        return $result;
    }

    //获取时间段内所有的包班级计划
    public function getSectionLearningPlan($filters = [], $start_time, $end_time)
    {
        //1.未完成的项目
        $learnerFilters = [
            'start_time' => $start_time * 1000,
            'end_time' => $end_time * 1000
        ];
        if ($type = ArrayGet($filters, 'type', Constants::LEARNING_CLASS)) $learnerFilters['type'] = $type;
        $modelLearner = new Learner($this->app);
        $learnerInfos = $modelLearner->myLearningCalendarList($learnerFilters);
        $learningIds = array_column($learnerInfos, 'learning_id');
        if (!$learningIds) return [];

        //2.未结束
        $planCond = [
            'aid' => $this->aid,
            'status' => self::STATUS_PUBLISHED,
            'id' => ['$in' => $learningIds],
            'type' => $type,
            'is_end' => ['$ne' => self::ENDED]
        ];
        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, $planCond,
            0,
            0, [], [
                'uid' => 1,
                'id' => 1,
                'name' => 1,
                'start_time' => 1,
                'end_time' => 1,
                'creator' => 1,
            ]);

        return $result;
    }

    public function getProjectIdsByClassKeyword($class_keyword)
    {
        $cond = [
            'aid' => $this->aid,
            'type' => Constants::LEARNING_CLASS,
            'project_id' => ['$gt' => 0],
            'status' => ['$in' => [self::STATUS_DRAFT, self::STATUS_PUBLISHED]],
            'name' => new Regex($class_keyword, 'im'),
        ];

        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, $cond, 0, 0, [], ['project_id' => 1]);
        return $result ? array_column($result, 'project_id') : [];
    }

    /**
     * @param $learner_transfer
     * @param $aid
     * @return int 转正设置 -1不展示此设置项 0未开启 1已开启
     */
    public function getLearnerTransferSetting($learner_transfer, $aid)
    {
        $account_model = new Account($this->app);
        $code = $account_model->getCode($aid);
        if (in_array($code, self::NEED_TRANSFER_CODE)) {
            return $learner_transfer ?: 0;
        } else {
            return -1;
        }
    }

    /**
     * 获取学员的大清单信息
     * @param $fields
     * @return array|null
     * @throws \Key\Exception\DatabaseException
     */
    public function getLearningAutoDefault($fields = [])
    {
        //获取自学计划大清单
        $default = $this->getMongoMasterConnection()->fetchRow(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'uid' => $this->eid,
            'status' => self::STATUS_PUBLISHED,
            'type' => Constants::LEARNING_AUTONOMY,
            'is_default_autonomy' => 1
        ], $fields);

        if (!$default) {
            $learning_id = $this->create(['name' => '任务·自学计划', 'is_default_autonomy' => 1], Constants::LEARNING_AUTONOMY, 0, 1);
            $default = $this->isExist($learning_id, $fields);
        }

        Utils::convertMongoDateToTimestamp($default);

        return $default;
    }

    public function getLearningAutoDefaultId()
    {
        $default = $this->getLearningAutoDefault(['id' => 1]);
        return ArrayGet($default, 'id', 0);
    }

    public function getDefaultAutonomy()
    {
        $result = $this->getMongoMasterConnection()->fetchRow(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'status' => 2,
            'type' => Constants::LEARNING_AUTONOMY,
            'uid' => $this->eid
        ], [
            'id' => 1
        ]);

        return $result ?: [];
    }

    /**
     * 爱学习单独使用
     * @return array
     * @throws \Key\Exception\DatabaseException
     */
    public function getIxuexiSimpleList()
    {
        $cond = [
            'aid' => $this->aid,
            'type' => Constants::LEARNING_CLASS,
            'project_id' => 0,
            'status' => self::STATUS_PUBLISHED,
            'is_end' => self::NOT_END,
            'start_time' => [
                '$lte' => Mongodb::getMongoDate(time()),
            ],
        ];
        return $this->getMongoMasterConnection()->fetchAll(
            Constants::COLL_LEARNING_PLAN,
            $cond,
            0,
            0,
            ['id' => -1],
            ['id' => 1, 'name' => 1]
        );
    }


    /**
     * 更新班级标签名称
     * @param $tag_name
     * @param $new_tag_name
     * @param $aid
     * @return bool
     * @throws \Key\Exception\DatabaseException
     */
    public function onKnowledgePointUpdated($tag_name, $new_tag_name, $aid = 0)
    {
        $learnings = $this->listByTagName($tag_name);
        if (!$learnings) return true;

        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'aid' => $aid ?: $this->aid,
            'status' => ['$in' => [1, 2]],
            'type' => Constants::LEARNING_CLASS,
            'tags' => ['$in' => [$tag_name]]
        ], [
            '$set' => [
                'tags.$' => $new_tag_name
            ]
        ]);
        if ($result) {
            foreach ($learnings as $learning) {
                $cache_key = $this->getRedisId($learning['id']);
                $this->getCacheInstance()->delete($cache_key);
            }
        }

        return $result !== false;
    }

    public function listByTagName($tag_name)
    {
        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'status' => ['$in' => [1, 2]],
            'type' => Constants::LEARNING_CLASS,
            'tags' => ['$in' => [$tag_name]]
        ], 0, 0, [], [
            'id' => 1
        ]);

        return $result ?: [];
    }

    /**
     * 删除标签名称.
     *
     * @param string $tag_name 标签名称
     * @return boolean
     */
    public function onKnowledgePointDeleted($tag_name)
    {
        $learnings = $this->listByTagName($tag_name);
        if (!$learnings) return true;

        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'status' => ['$in' => [1, 2]],
            'type' => Constants::LEARNING_CLASS,
            'tags' => ['$in' => [$tag_name]]
        ], [
            '$pull' => ['tags' => $tag_name],
            '$set' => [
                'updated' => Mongodb::getMongoDate()
            ]
        ]);
        if ($result) {
            foreach ($learnings as $learning) {
                $cache_key = $this->getRedisId($learning['id']);
                $this->getCacheInstance()->delete($cache_key);
            }
        }

        return $result !== false;
    }

    //张阳阳培训需求删除问题时，处理班级对应数据（所有班级有这个问题的 都删掉）
    public function trainingNeedsQuestionRemove($question_id)
    {
        $condition = [
            'aid' => $this->aid,
            'type' => Constants::LEARNING_CLASS,
            'training_need_question_ids' => $question_id,
        ];

        $list = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, $condition, 0, 0, [], ['id' => 1]);
        if (!$list) return true;
        $plan_ids = array_column($list, 'id');

        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, ['aid' => $this->aid, 'type' => Constants::LEARNING_CLASS, 'id' => ['$in' => $plan_ids]], [
            '$pull' => [
                'training_need_question_ids' => $question_id
            ],
            '$set' => [
                'updated' => Mongodb::getMongoDate()
            ]
        ]);

        foreach ($plan_ids as $plan_id) {
            $cache_key = $this->getRedisId($plan_id);
            $this->getCacheInstance()->delete($cache_key);

            //操作日志
            $module = 'class';
            $this->setNewData([])
                ->setOldData([])
                ->setOp(self::OP_UPDATE)
                ->describe($plan_id, '', 'training_need_question_ids删除问题' . $question_id)
                ->setOpModule($module)
                ->saveOpLog();
        }

        return $result ?: false;
    }

    //张阳阳培训需求编辑问题时，指定班级ID加上指定问题ID
    public function trainingNeedsQuestionEditIncr($question_id, $learning_id){
        $old_data = $this->isExist($learning_id, [
            'created' => 0,
            'updated' => 0
        ]);
        $condition = [
            'aid' => $this->aid,
            'type' => Constants::LEARNING_CLASS,
            'id' => $learning_id
        ];

        $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, $condition, [
            '$push' => [
                'training_need_question_ids' => $question_id
            ],
            '$set' => [
                'updated' => Mongodb::getMongoDate()
            ]
        ]);
        $cache_key = $this->getRedisId($learning_id);
        $this->getCacheInstance()->delete($cache_key);

        //操作日志
        $module = 'class';
        $this->setNewData([])
            ->setOldData($old_data)
            ->setOp(self::OP_UPDATE)
            ->describe($learning_id, '', 'training_need_question_ids新增' . $question_id)
            ->setOpModule($module)
            ->saveOpLog();
        return true;
    }

    //张阳阳培训需求编辑问题时，指定班级ID减去指定问题ID
    public function trainingNeedsQuestionEditDecr($question_id, $learning_id){
        $old_data = $this->isExist($learning_id, [
            'created' => 0,
            'updated' => 0
        ]);
        $condition = [
            'aid' => $this->aid,
            'type' => Constants::LEARNING_CLASS,
            'id' => $learning_id
        ];

        $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, $condition, [
            '$pull' => [
                'training_need_question_ids' => $question_id
            ],
            '$set' => [
                'updated' => Mongodb::getMongoDate()
            ]
        ]);
        $cache_key = $this->getRedisId($learning_id);
        $this->getCacheInstance()->delete($cache_key);

        //操作日志
        $module = 'class';
        $this->setNewData([])
            ->setOldData($old_data)
            ->setOp(self::OP_UPDATE)
            ->describe($learning_id, '', 'training_need_question_ids删除' . $question_id)
            ->setOpModule($module)
            ->saveOpLog();
        return true;
    }

//    //张阳阳培训需求编辑问题时，处理班级对应数据
//    public function trainingNeedsQuestionEdit($question_id){
//        //当前问题对应所有的班级ID
//        $cond = [
//            'aid' => $this->aid,
//            'status' => self::ENABLED,
//            'question_id' => $question_id
//        ];
//        $plans = $this->getMongoMasterConnection()->fetchAll(NeedsQuestionLinkedClasses::TABLE, $cond, 0, 0, ['class_id' => 1], ['class_id' => 1]);
//        $planIds = array_column($plans, 'class_id');
//
//        //每个班级实时所有的问题ID
//        $match = [
//            'aid' => $this->aid,
//            'status' => self::ENABLED,
//            'class_id' => ['$in' => $planIds]
//        ];
//        $group = [
//            '_id' => '$class_id',
//            'question_ids' => ['$push' => '$question_id']
//        ];
//        $pipeline = [
//            ['$match' => $match],
//            ['$group' => $group]
//        ];
//        $list = $this->getMongoMasterConnection()->aggregate(NeedsQuestionLinkedClasses::TABLE, $pipeline);
//        if (!$list) return false;
//        //更新每个班级所有的问题ID
//        foreach ($list as $key => $value) {
//            $updateCond = [
//                'aid' => $this->aid,
//                'type' => Constants::LEARNING_CLASS,
//                'id' => $value['_id'],
//            ];
//            $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, $updateCond, [
//                '$set' => [
//                    'training_need_question_ids' => $value['question_ids']
//                ]
//            ]);
//
//            $cache_key = $this->getRedisId($value['_id']);
//            $this->getCacheInstance()->delete($cache_key);
//        }
//        return true;
//    }

    /**
     * 获取追踪人班级列表.
     *
     * @param array $filters
     * @param array $sort
     * @param int $type
     * @param array $fields
     * @param null|\Key\Records\Pagination $pg
     * @return array
     */
    public function getListByStalker($type = Constants::LEARNING_CLASS, $filters = [], $pg = null, $sort = [], $fields = [])
    {
        if (!$pg) {
            $pg = new Pagination();
            $pg->setPage(0)->setItemsPerPage(0);
        }

        $cond = $this->handleFiltersByStalker($type, $filters);

        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, $cond,
            $pg->getOffset(),
            $pg->getItemsPerPage(),
            $sort ?: [
                'order' => 1,
                'updated' => -1
            ], $fields ?: [
                'aid' => 0,
                'uid' => 0,
                'desc' => 0,
                'updated' => 0
            ]
        );
        Utils::convertMongoDateToTimestamp($result);

        $learning_ids = array_column($result, 'id');
        $model = new Learner($this->app);
        $rules['is_examine'] = -1;
        $learners = $model->getLearningsLearnerNumExamine($learning_ids, $type, $rules);
        foreach ($result as $k => $val) {
            $val['learner_num'] = ArrayGet($learners, $val['id'] . '_1', 0);
            $val['learner_num_un'] = ArrayGet($learners, $val['id'] . '_0', 0);
            $result[$k] = $val;
        }

        return $result ?: [];
    }

    /**
     * 获取学习包列表数量.
     *
     * @param array $filters
     * @param array $sort
     * @param int $type
     * @param null|\Key\Records\Pagination $pg
     * @return int
     */
    public function getTotalByStalker($type = Constants::LEARNING_PACK, $filters = [])
    {
        $cond = $this->handleFiltersByStalker($type, $filters);
        $result = $this->getMongoMasterConnection()->count(Constants::COLL_LEARNING_PLAN, $cond);

        return $result ?: 0;
    }

    //列表过滤条件
    protected function handleFiltersByStalker($type = Constants::LEARNING_CLASS, $filters = [])
    {
        $eid = $this->eid;

        $filters['data_from'] = 'all_published';
        $filters['status'] = self::STATUS_PUBLISHED;
        $cond = $this->getCondition($filters, $type, 0);

        $cond['$or'] = [
            ['tracker' => ['$elemMatch' => ['type' => 4, 'id' => $eid]]], //指定人员
        ];

        //管理员人群过滤
        $employee_model = new Employee($this->app);
        $employee = $employee_model->view($eid, $this->aid, [
            'job_level_id' => 1,
            'department_id' => 1,
            'position_id' => 1,
            'groups' => 1,
        ], Employee::ENABLED, 1);
        $groups = ArrayGet($employee, 'groups', []);
        if ($groups) $cond['$or'][] = ['tracker' => ['$elemMatch' => ['id' => ['$in' => $groups], 'type' => 3]]]; //人群

        return $cond;
    }

    /**
     * 获取跟踪者的班级详情.
     * @param $learning_id
     * @param int $type
     * @param $pg
     * @param $sort
     * @param array $filters
     * @param $total
     * @return array
     */
    public function getDetailListByStalker($learning_id, $type, $pg, $sort, $filters = [], &$total)
    {
        $sort = ['learning_rate' => -1];

        $fields = [
            'uid' => 1,
            'display' => 1,
            'learning_rate' => 1,
            'learning_status' => 1,
            'is_tourist' => 1,
            'real_period' => 1,
            'valid_period' => 1,
            'start_time' => 1,
            'end_time' => 1,
            'finished_time' => 1,
            'learned_task' => 1
        ];

        $filters['source'] = 'statistics_learning';
        $filters['is_elective_task'] = 0;

        if ($filters['finished_status'] == 1) {
            $filters['rate'] = 1;
        } elseif ($filters['finished_status'] == 2) {
            $filters['rate'] = 2;
        }
        unset($filters['finished_status']);

        $learnerModel = new Learner($this->app);
        $total = $learnerModel->learnerTrackTotal($learning_id, $type, $filters);

        $learner_list = $learnerModel->learnerTrack($learning_id, $type, $filters, $sort, $pg, $fields);
        if (!$learner_list) return [];
        return $learnerModel->listAddLearnedTask($learner_list, $learning_id, $type, $filters);

    }

    public function learningsByTags($type = Constants::LEARNING_CLASS, $tags = []){
        if (!$tags) return [];
        $cond = [
            'aid' => $this->aid,
            'status' => self::STATUS_PUBLISHED,
            'type' => $type,
            'tags' => ['$in' => $tags]
        ];
        $result = $this->getMongoMasterConnection()->fetchAll(Constants::COLL_LEARNING_PLAN, $cond, 0, 0, [], ['id' => 1]);
        return  $result;
    }

    /**
     * 班级禁用/启用.
     *
     * @param int $id .
     * @param int $enabled 禁启用 0-否 1-是.
     * @param int $type .
     * @return boolean
     */
    public function enabled($id, $enabled, $type = Constants::LEARNING_CLASS)
    {
        $detail = $this->isExist($id);
        if (!$detail) return false;

        $result = $this->getMongoMasterConnection()->update(Constants::COLL_LEARNING_PLAN, [
            'aid' => $this->aid,
            'id' => $id,
            'type' => $type
        ], [
            '$set' => [
                'is_enabled' => $enabled,
                'updated' => Mongodb::getMongoDate()
            ]
        ]);

        if (!$result) return false;

        $cache_key = $this->getRedisId($id);
        $this->getCacheInstance()->delete($cache_key);

        $learner_model = new Learner($this->app);
        $learner_model->updateLearningInfo($id, ['learning_enabled' => $enabled], $type);

        return true;
    }
}
