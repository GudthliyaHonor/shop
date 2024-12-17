<?php
/**
 * 学习计划controller.
 * User: wangyy
 * Date: 2017/8/17
 * Time: 9:54
 */

namespace App\Controllers;

use App\Common\Constants;
use App\Common\Controller;
use App\Models\Learning\SelfStudyPlan;
use App\Utils;
use App\Models\Exports\LearningPlanTaskLearner;
use App\Models\Exports\LearningProjectLearner;
use App\Models\FileClient;
use App\Models\InsideTrainingDeptTaskRelation;
use App\Models\LearningBill;
use App\Transfers\ArrayTransfer;
use const Grpc\CALL_ERROR_NOT_ON_SERVER;

class LearningPlan extends Controller
{
    const TIMEOUT = 600;

    /**
     * 创建学习计划.
     *
     * @param array $record
     * @param int $type
     * @param int $project_id
     * @return boolean|int
     */
    public function createAction($record, $type, $project_id)
    {
        /*if (in_array($type, [Constants::LEARNING_CLASS, Constants::LEARNING_PLAN])) {
            $module = 'class_manage';
            if ($type == 3) $module = 'learning_plan_create';
            $is_permission = $this->getPermission($module);
            if (!$is_permission) return static::LEARNING_NO_OPERATION_AUTH;
        }*/

        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $no = $record->getData('no');
        if ($no && $model->noExists($no, $type, ['id' => 1])) return static::LEARNING_NO_EXIST;

        $new_data = $record->toArray();

        // 判断是否允许编辑
        if ($type == Constants::LEARNING_CLASS && $new_data['inside_training']['relation_id']) {
            /** @var \App\Models\InsideTrainingDeptTaskRelation $relation_model */
            $relation_model = $this->getModel('InsideTrainingDeptTaskRelation');
            $relation = $relation_model->getById($new_data['inside_training']['relation_id']);
            if (!$relation) {
                $this->setStatusMessage('任务计划未找到');
                return Constants::SYS_ERROR_DEFAULT;
            }
            if ($relation['class_id'] && $relation['class_remove'] == 0) {
                $this->setStatusMessage('非法操作, 任务计划已实施');
                return Constants::SYS_ERROR_DEFAULT;
            }
            // 判断班级时间是否在任务时间范围内
            $date_res = $relation_model->checkStartTime($relation['task']['start_time'], $new_data['start_time']);
            if ($date_res['status'] === false) {
                $this->setStatusMessage($date_res['msg']);
                return Constants::SYS_ERROR_DEFAULT;
            }
            if ($new_data['end_time'] > $relation['task']['end_time'] && empty($new_data['inside_training']['delay_reason'])) {
                $this->setStatusMessage('请填写项目延期说明');
                return Constants::SYS_ERROR_DEFAULT;
            }
        }

        $result = $model->create($record, $type, $project_id);

        if ($result) {
            if ($result === 216) return static::LEARNING_PLAN_IS_RELATED_PROJECT;

            $this->setOutput('id', $result);
            return Constants::SYS_SUCCESS;
        }
        return Constants::SYS_ERROR_DEFAULT;
    }

    /**
     * 获取学习计划列表.
     *
     * @param string $name
     * @param string $sort
     * @param int $filter
     * @param int $type
     * @param int $total
     * @param int $source 来源 1-pc学习 0-pc管理
     * @param null|\Key\Records\Pagination $pg
     * @return array
     */
    public function listAction($name, $sort, $filter, $type, $pg, $source, $project_id, $filters, $audit_status)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $filters = $filters->toArray();
        if ($name) $filters['keyword'] = $name;
        if ($filter) $filters['filter'] = $filter;
        if ($source) $filters['source'] = $source;
        if ($audit_status) $filters['audit_status'] = $audit_status;
        $result = $model->getListByProject($filters, $type, $project_id, $pg, $sort);
        Utils::convertMongoDateToTimestamp($result);
        $total = $model->getTotal($filters, $type, $project_id);

        $this->setOutput('plan', $result);
        $this->setOutput('total', $total);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 无功能权限-工作台-智能班级.
     *
     * @param string $name
     * @param string $sort
     * @param int $filter
     * @param int $type
     * @param int $total
     * @param int $source 来源 1-pc学习 0-pc管理
     * @param null|\Key\Records\Pagination $pg
     * @return array
     */
    public function listPreAction($name, $sort, $filter, $type, $pg, $source, $project_id, $filters)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $filters = $filters->toArray();
        if ($name) $filters['keyword'] = $name;
        if ($filter) $filters['filter'] = $filter;
        if ($source) $filters['source'] = $source;
        $result = $model->getListByProjectPre($filters, $type, $project_id, $pg, $sort);
        Utils::convertMongoDateToTimestamp($result);
        $total = $model->getTotalPre($filters, $type, $project_id);

        $this->setOutput('plan', $result);
        $this->setOutput('total', $total);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 项目是否有功能权限.
     *
     * @param string $name
     * @param string $sort
     * @param int $filter
     * @param int $type
     * @param int $total
     * @param int $source 来源 1-pc学习 0-pc管理
     * @param null|\Key\Records\Pagination $pg
     * @return array
     */
    public function permissionAction($source)
    {
        /** @var \App\Models\Permission $modelPermission */
        $modelPermission = $this->getModel('Permission');
        $is_permission = $modelPermission->haveModule($source);
        $this->setOutput('permission', $is_permission);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 根据多班次ID，获取多班次整体项目完成度
     *
     * @param string $name
     * @param string $sort
     * @param int $filter
     * @param int $type
     * @param int $total
     * @param int $source 来源 1-pc学习 0-pc管理
     * @param null|\Key\Records\Pagination $pg
     * @return array
     */
    public function listAvgRateAction($name, $sort, $filter, $type, $pg, $source, $project_id, $filters)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $filters = $filters->toArray();
        if ($name) $filters['keyword'] = $name;
        if ($filter) $filters['filter'] = $filter;
        if ($source) $filters['source'] = $source;
        $result = $model->getListAvgRate($filters, $type, $project_id, $pg, $sort);

        $this->setOutput('total_avg_rate', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 获取学习计划列表.
     *
     * @param int $project_id .
     * @param \App\Records\LearningPlanFilters $filters .
     * @param int $type .
     * @param null|\Key\Records\Pagination $pg .
     * @param string $sort .
     * @return array
     */
    public function listV2Action($project_id, $filters, $type, $pg, $sort)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $filters = $filters->toArray();
        $result = $model->getList($filters, $type, $project_id, $pg, $sort);
        $total = $model->getTotal($filters, $type, $project_id);

        $this->setOutput('plan', $result);
        $this->setOutput('total', $total);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 获取学习计划过滤条件下的数量.
     *
     * @param string $name 班级/计划的名称
     * @param int $type 0-智能班级 3-学习计划
     * @param int $source 来源 1-pc学习 0-pc管理
     * @return array
     */
    public function filterNumsAction($name, $type, $source, $project_id)
    {

        $filter = [];
        if ($source == 1) {
            /** @var \App\Models\Learner $model */
            $model = $this->getModel('Learner');
            $filter['not_start'] = $model->filtersNum($name, $type, 1);
            $filter['unfinished'] = $model->filtersNum($name, $type, 2);
            $filter['finished'] = $model->filtersNum($name, $type, 3);
            $filter['delay'] = $model->filtersNum($name, $type, 4);
            $filter['draft'] = $model->filtersNum($name, $type, 5);
            $filter['end'] = $model->filtersNum($name, $type, 6);
            $filter['total'] = $model->filtersNum($name, $type, 0);

        } else {
            $filters = [];
            if ($name) $filters['keyword'] = $name;
            /** @var \App\Models\LearningPlan $model */
            $model = $this->getModel('LearningPlan');
            $filter['not_start'] = $model->getTotal(array_merge($filters, ['filter' => 1]), $type, $project_id);
            $filter['unfinished'] = $model->getTotal(array_merge($filters, ['filter' => 2]), $type, $project_id);
            $filter['finished'] = $model->getTotal(array_merge($filters, ['filter' => 3]), $type, $project_id);
            $filter['delay'] = $model->getTotal(array_merge($filters, ['filter' => 4]), $type, $project_id);
            $filter['draft'] = $model->getTotal(array_merge($filters, ['filter' => 5]), $type, $project_id);
            $filter['end'] = $model->getTotal(array_merge($filters, ['filter' => 6]), $type, $project_id);
            $filter['total'] = $model->getTotal(array_merge($filters, ['filter' => 0]), $type, $project_id);
        }

        $this->setOutput('filter', $filter);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 更新学习计划.
     *
     * @param int $learning_id
     * @param int $type
     * @param array $record
     * @return boolean
     */
    public function updateAction($learning_id, $record, $type)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $no = $record->getData('no');
        if ($no && $model->noExists($no, $type, ['id' => 1], $learning_id)) return static::LEARNING_NO_EXIST;
        $result = $model->update($learning_id, $record, $type);

        if ($result) {
            return Constants::SYS_SUCCESS;
        }
        return Constants::SYS_ERROR_DEFAULT;
    }

    /**
     * 删除学习计划.
     * Route: `DELETE /learning/plan/(:learning_id)/type/(:type)`.
     * @param int $learning_id
     * @param int $type
     * @return boolean
     */
    public function removeAction($learning_id, $type = 0)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->remove($learning_id, $type);

        if ($result) {
            /** @var \App\Models\LearningTask $task_model */
            $task_model = $this->getModel('LearningTask');
            $task_model->remove($learning_id, $type);
            if ($type == 0) {
                $trainingPlan = new \App\Models\TrainingPlan($this->app);
                $trainingPlan->changeOpStatus(0, $learning_id, $type);
            }

            // 自学计划统计表
            if ($type == Constants::LEARNING_AUTONOMY) {
                /** @var SelfStudyPlan $selfModel */
                $selfModel = $this->getModel('Learning\\SelfStudyPlan');
                $selfModel->removeByLearningId($learning_id);
            }

            return Constants::SYS_SUCCESS;
        }
        return Constants::SYS_ERROR_DEFAULT;
    }

    //查看权限
    protected function getPermission($module)
    {
        $permission_model = $this->getModel('Permission');
        return $permission_model->haveModule($module);
    }

    /**
     * 复制学习计划.
     *
     * @param int $learning_id
     * @param int $type
     * @param string $name
     * @param array $filters
     * @return boolean
     */
    public function copyAction($learning_id, $name, $type, $filters)
    {

        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $plan = $model->getById($learning_id, $type);
        if ($plan['inside_training']['relation_id']) {
            $this->setStatusMessage('内训班级禁止复制');
            return Constants::SYS_ERROR_DEFAULT;
        }
        $filters = $filters->toArray();
        $result = $model->copy($learning_id, $name, $type, $filters);

        $this->setOutput('result', $result);
        if ($result) {
            return Constants::SYS_SUCCESS;
        }
        return Constants::SYS_ERROR_DEFAULT;
    }

    /**
     * 查看学习计划详情(带任务).
     *
     * @param int $learning_id .
     * @param int $type .
     * @return array
     */
    public function learningInfoAction($learning_id, $type = 0)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->getById($learning_id, $type);

        $this->setOutput('plan', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 查看学习计划详情（不带任务）.
     *
     * @param int $learning_id .
     * @param int $type .
     * @return array
     */
    public function viewAction($learning_id)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->isExist($learning_id, [
            'created' => 0,
            'updated' => 0,
            'aid' => 0,
        ]);
        if ($result) {
            $result['employee_status'] = ArrayGet($result, 'employee_status', 1);
        }

        $this->setOutput('detail', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * h5获取学习计划列表.
     *
     * @param string $keyword .
     * @param int $filter .
     * @param int $sort
     * @param int $type
     * @param int $is_applicant
     * @param null|\Key\Records\Pagination $pg
     * @return array
     */
    public function listByLearnerAction($project_id, $keyword, $filter, $sort, $type, $is_applicant, $pg)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $con = [];
        if ($project_id) $con['project_id'] = $project_id;
        $result = $model->listByLearner($keyword, $filter, $sort, $type, $is_applicant, $pg, $con, $total);

        $this->setOutput('plan', $result);
        $this->setOutput('total', $total);
        return Constants::SYS_SUCCESS;
    }

    /**
     * h5获取班级详情.
     *
     * @param int $learning_id .
     * @param int $type .
     * @param int $source 0-h5学习 1-pc学习. (暂不需要了，h5需求改动)
     * @return array
     */
    public function classDetailAction($learning_id, $type = Constants::LEARNING_CLASS, $source)
    {
        /** @var \App\Models\Learner $learner_model */
        $learner_model = $this->getModel('Learner');

        /** @var \App\Models\Learner\StuLearner $newLearnerModel */
        $newLearnerModel = $this->getModel('Learner\StuLearner');

        //班级详情需要校验学员权限
        if (in_array($type, [Constants::LEARNING_CLASS])) {
            $learnerExists = $learner_model->viewLearner($learning_id, 0, $type);
            if (!$learnerExists) {
                return static::LEARNING_PLAN_NO_VISIT_AUTH;
            }
        }

        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->isExist($learning_id, [
            'id' => 1,
            'name' => 1,
            'end_time' => 1,
            'start_time' => 1,
            'learner_type' => 1,
            'unlock_con' => 1,
            'unlock_detail' => 1,
            'training_hours' => 1,
            'class_address' => 1,
            'is_applicant' => 1,
            'is_watch' => 1,
            'project_id' => 1,
            'credit' => 1,
            'application_hints' => 1,
            'is_organize_transport' => 1,
            'is_accommodation' => 1,
            'enroll_setting' => 1,
            'enroll_form' => 1,
            'app_setting' => 1,
            'desc' => 1,
            'cover' => 1,
            'settings' => 1,
            'type' => 1,
            'learning_status' => 1,
            'is_end' => 1,
            'simple_plan' => 1,
            'creator' => 1,
            'monitor' => 1,
            'completion_condition' => 1,
            'payer_type' => 1,
            'is_enroll_form' => 1,
            'class_type' => 1,
        ]);
        $result['unlock_con'] = ArrayGet($result, 'unlock_con', 0);
        $enroll_setting = ArrayGet($result, 'enroll_setting', []);
        $enroll_form = ArrayGet($result, 'enroll_form', []);
        $result['application_start_time'] = ArrayGet($enroll_setting, 'enroll_started', '');
        $result['application_end_time'] = ArrayGet($enroll_setting, 'enroll_ended', '');
        $result['is_organize_transport'] = ArrayGet($enroll_form, 'is_transport', '');
        $result['is_accommodation'] = ArrayGet($enroll_form, 'is_hotel', '');

        if ($type == Constants::LEARNING_CLASS) {
            $result['project_id'] = ArrayGet($result, 'project_id', 0);
            $result['shift_setting'] = ['allow_shift' => 1];
            if ($result['project_id']) {
                /** @var \App\Models\LearningProject $project_model */
                $project_model = $this->getModel('LearningProject');
                $project = $project_model->isExists($result['project_id'], ['title' => 1, 'desc' => 1, 'shift_setting' => 1]);
                $result['project_title'] = ArrayGet($project, 'title', '');
                $result['project_desc'] = ArrayGet($project, 'desc', '');
                $result['shift_setting'] = ArrayGet($project, 'shift_setting', ['allow_shift' => 1]);
            }
            /** @var \App\Models\ClassSign $sign_model */
            $sign_model = $this->getModel('ClassSign');
            $result['sign_num'] = $sign_model->getTotal($learning_id, 1);
        }

        if ($type == Constants::LEARNING_TRAINING) {
            $result["completion_condition"] = $result["completion_condition"] ?? 1;
            $result["payer_type"] = $result["payer_type"] ?? 1;
        }

        //获取学员的完成率
        $learner = $learner_model->planBasicInfo($learning_id);
        $result['learning_rate'] = ArrayGet($learner, 'learning_rate', 0);
        //学员调班状态
        $result['shift_status'] = ArrayGet($learner, 'shift_status', \App\Models\Learner::STATUS_SHIFT_DEFAULT);
        $result['learner_num'] = $newLearnerModel->setFilter([\App\Models\Learner::FILTER_FIELD_LEARNING_ID => $learning_id, \App\Models\Learner::FILTER_FIELD_TYPE => $type])->totalLearnerByLearning();
//        $result['learner_num'] = $learner_model->getClassLearner($learning_id, $type);

        /** @var \App\Models\LearningTask $task_model */
        $task_model = $this->getModel('LearningTask');
        $result['task_num'] = $task_model->getTaskNum($learning_id, $type);

        /** @var \App\Models\Learning\LearningPlan $newModelLearningPlan */
        $newModelLearningPlan = $this->getModel('Learning\LearningPlan');
        $newModelLearningPlan->handleDetail($result);

        $this->setOutput('plan', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 任务统计(以任务为单位).
     *
     * @param int $learning_id 智能班级id.
     * @param int $group_id 分组id 默认-1 全部.
     * @param int $type 0-智能班级 1-学习包 2-闯关 3-学习计划.
     * @return array
     */
    public function taskStatisticsAction($learning_id, $group_id = -1, $type = 0)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->taskStatistics($learning_id, $group_id, $type);

        $this->setOutput('plan', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * h5获取智能班级简介
     *
     * @param int $learning_id .
     * @param int $type .
     * @return array
     */
    public function introductionAction($learning_id, $type = 0)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->introduction($learning_id, $type);

        if ($result && $result == 205) return static::LEARNING_PLAN_NO_PERMISSION;
        $this->setOutput('detail', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 智能班级签到统计.
     *
     * @param int $learning_id .
     * @return array
     */
    public function statisticAction($learning_id)
    {
        /** @var \App\Models\LearningTask $model */
        $model = $this->getModel('LearningTask');
        $result = $model->statistic($learning_id);

        $this->setOutput('list', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 查看学习计划基本信息.
     *
     * @param int $learning_id .
     * @param int $type .
     * @param int $review_status 是否需要审核状态 0-否 1-是（pc班级报名）.
     * @return array
     */
    public function getBasicInfoAction($learning_id, $type = 0, $review_status)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->getBasicInfo($learning_id, $type);

        /** @var \App\Models\Learner $learner_model */
        $learner_model = $this->getModel('Learner');

        /** @var \App\Models\Learner\Learner $newLearnerModel */
        $newLearnerModel = $this->getModel('Learner\Learner');
        if ($result) {
            if ($type === Constants::LEARNING_TRAINING) {
                $result['completion_condition'] = ArrayGet($result, 'completion_condition', 1);
                $result['payer_type'] = ArrayGet($result, 'payer_type', 1);
                $result['review_status'] = ArrayGet($result, 'review_status', 0);
                if ($result['status'] == 2) {
                    $result['review_status'] = 3;
                }
            }
            /** @var \App\Models\LearningTask $task_model */
            $task_model = $this->getModel('LearningTask');
            $result['task_num'] = $task_model->getTaskNum($learning_id, $type);
//            $result['learner_num'] = $learner_model->learnerTrackTotal($learning_id, $type);
            $result['learner_num'] = $newLearnerModel->setFilter([\App\Models\Learner::FILTER_FIELD_LEARNING_ID => $learning_id, \App\Models\Learner::FILTER_FIELD_TYPE => $type])->totalLearnerByLearning();
        }

        if ($review_status) {
            $result['is_review'] = -1;
            $detail = $learner_model->viewLearner($learning_id, $type);
            if ($detail) {
                $result['is_review'] = 0;
                $result['is_review'] = ArrayGet($detail, 'is_examine', 1);
            }
        }

        /** @var \App\Models\Learning\LearningPlan $newModelLearningPlan */
        $newModelLearningPlan = $this->getModel('Learning\LearningPlan');
        $newModelLearningPlan->handleDetail($result);
        $this->setOutput('detail', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 更新学习计划基本信息.
     *
     * @param int $learning_id .
     * @param array $record .
     * @param int $type .
     * @return boolean
     */
    public function updateBasicInfoAction($learning_id, $record, $type = 0)
    {
        $no = $record->getData('no');
        $new_data = $record->toArray(true);

        // 判断是否允许编辑
        if ($type == Constants::LEARNING_CLASS && $new_data['inside_training']['relation_id']) {
            /** @var \App\Models\InsideTrainingDeptTaskRelation $relation_model */
            $relation_model = $this->getModel('InsideTrainingDeptTaskRelation');
            $relation = $relation_model->getById($new_data['inside_training']['relation_id']);
            if (!$relation) {
                $this->setStatusMessage('任务计划未找到');
                return Constants::SYS_ERROR_DEFAULT;
            }
            if ($relation['status'] == $relation_model::STATUS_WAIT_VERIFY || $relation['status'] == $relation_model::STATUS_VERIFY_AGREE) {
                $this->setStatusMessage('内训项目审核中，禁止修改');
                return Constants::SYS_ERROR_DEFAULT;
            }
            if ($relation['class_id'] != $learning_id && $relation['class_remove'] == 0) {
                $this->setStatusMessage('非法操作，任务计划已实施');
                return Constants::SYS_ERROR_DEFAULT;
            }
            // 判断班级时间是否在任务时间范围内
            $date_res = $relation_model->checkStartTime($relation['task']['start_time'], $new_data['start_time']);
            if ($date_res['status'] === false) {
                $this->setStatusMessage($date_res['msg']);
                return Constants::SYS_ERROR_DEFAULT;
            }
            if ($new_data['end_time'] > $relation['task']['end_time'] && empty($new_data['inside_training']['delay_reason'])) {
                $this->setStatusMessage('请填写项目延期说明');
                return Constants::SYS_ERROR_DEFAULT;
            }
        }

        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        if ($no && $model->noExists($no, $type, ['id' => 1], $learning_id)) return static::LEARNING_NO_EXIST;
        $plan = $model->isExist($learning_id, ['created' => 0, 'updated' => 0]);
        if (!$plan) {
            return self::LEARNING_PLAN_NO_EXISTS;
        }
        $planTask = new \App\Models\TrainingPlanTask($this->app);

        $source_id = $new_data['source_id'];
        $old_source_id = $plan['source_id'];
        if ($source_id && $source_id != $old_source_id && $type == 0) {
            $plan_task_info = $planTask->getById($source_id);
            if (isset($plan_task_info['class_id']) && $plan_task_info['class_id'] != 0 && $plan_task_info['class_id'] != $learning_id) {
                return self::TRAINING_HAS_USE;
            }
        }
        if ($type == 0 && $old_source_id != 0 && $source_id != $old_source_id) {
            $task_info = $planTask->getById($old_source_id);
            if ($task_info && $task_info['op_status'] == $planTask::TASK_OP_STATUS_FINISHED) {
                return self::TRAINING_TASK_END;
            }
        }
        $result = $model->updateBasicInfo($learning_id, $new_data, $type, $plan);

        if ($result) {
            return Constants::SYS_SUCCESS;
        }
        return Constants::SYS_ERROR_DEFAULT;
    }

    /**
     * 获取指定人员列表.
     *
     * @param string $keyword 员工姓名搜索.
     * @param int $learning_id 培训班id.
     * @param null|\Key\Records\Pagination $pagination
     * @return array
     */
    public function appointEmployeesAction($keyword, $learning_id, $pagination)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $appoint = $model->isExist($learning_id, ['appoint_employee' => 1]);
        $result = [];

//        /** @var \App\Models\Employee $employee_model */
//        $employee_model = $this->getModel('Employee');
//        if ($eids = ArrayGet($appoint, 'appoint_employee', [])) {
//            $result = $employee_model->filters(['eids' => $eids], $keyword, $pagination, null, $total);
//        }

        /** @var \App\Models\EmployeeList $employee_model */
        $employee_model = $this->getModel('EmployeeList');
        if ($eids = ArrayGet($appoint, 'appoint_employee', [])) {
            $listFilter = [
                'ids' => $eids
            ];
            if ($keyword) $listFilter['keyword'] = $keyword;
            $result = $employee_model->find($listFilter, $pagination, $total);
//            $result = $employee_model->filters(['eids' => $eids], $keyword, $pagination, null, $total);
        }

        $this->setOutput('list', $result);
        $this->setOutput('total', $total);
        $this->setOutput('page', $pagination->getPage());
        $this->setOutput('itemsPerPage', $pagination->getItemsPerPage());
        return Constants::SYS_SUCCESS;
    }

    /**
     * 创建pk分组.
     *
     * @param int $learning_id .
     * @param string $name .
     * @return boolean
     */
    public function createPkGroupAction($learning_id, $record)
    {
        $record = $record->toArray(true);
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');

        if ($model->pkGroupExists($learning_id, $record['name'])) {
            $this->setStatusMessage('小组名称已被使用');
            return Constants::SYS_ERROR_DEFAULT;
        }
        if ($id = $model->createPkGroup($learning_id, $record, -1, $errno)) {

            $this->setOutput('id', $id);

            return Constants::SYS_SUCCESS;
        }

        return $errno === false ? Constants::SYS_ERROR_DEFAULT : $errno;
    }

    /**
     * 更新pk分组名称.
     *
     * @param int $id .
     * @param string $name .
     * @return boolean
     */
    public function updatePkGroupAction($id, $record)
    {
        $record = $record->toArray(true);

        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');

        if ($model->updatePkGroup($id, $record, $errno)) {
            return Constants::SYS_SUCCESS;
        }

        return $errno === false ? Constants::SYS_ERROR_DEFAULT : $errno;
    }

    /**
     * 更新pk分组分值.
     *
     * @param int $id 分组id.
     * @param int $learning_id 智能班级id.
     * @param int $is_add 是否加分 0-否 1-是.
     * @param int $errno .
     * @return boolean
     */
    public function pkGroupScoreAction($learning_id, $id, $is_add)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');

        if ($model->pkGroupScore($learning_id, $id, $is_add, $errno)) {
            return Constants::SYS_SUCCESS;
        }

        return $errno === false ? Constants::SYS_ERROR_DEFAULT : $errno;
    }

    /**
     * 删除pk分组.
     *
     * @param int $id .
     * @return boolean|int
     */
    public function deletePkGroupAction($id)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');

        if ($model->deletePkGroup($id, $errno)) {
            return Constants::SYS_SUCCESS;
        }

        return $errno === false ? Constants::SYS_ERROR_DEFAULT : $errno;
    }

    /**
     * 获取pk分组列表.
     *
     * @param int $learning_id 智能班级id
     * @param int $group_id 分组id
     * @param int $is_h5 是否是h5 0-不是 1-是
     * @param string $keyword
     * @return array
     */
    public function pkGroupsAction($learning_id, $group_id = 0, $is_h5 = 1, $keyword)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->setCommonFilter(['keyword' => $keyword])->getPkGroups($learning_id, $group_id, $is_h5);

        $this->setOutput('list', $result['list']);
        $this->setOutput('lander_group', $result['lander_group']);
        return Constants::SYS_SUCCESS;
    }

    public function pkGroupsForTaskAction($learning_id, $task_id, $ignore_group_ids = [])
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->getPkGroupsForTask($learning_id, $task_id, $ignore_group_ids);

        $this->setOutput('list', $result['list']);
        $this->setOutput('others_submit', $result['others_submit']);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 获取pk排名.
     *
     * @param int $learning_id
     * @return array
     */
    public function pkRankingAction($learning_id)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->getPkRanking($learning_id);

        $this->setOutput('list', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 获取我当前所在的pk分组.
     *
     * @param int $learning_id 智能班级id
     * @return string
     */
    public function myPkGroupAction($learning_id)
    {
        /** @var \App\Models\Learner $learner_model */
        $learner_model = $this->getModel('Learner');
        $learner = $learner_model->viewLearner($learning_id, 0, Constants::LEARNING_CLASS, 0, ['group_id' => 1]);
        $group_id = ArrayGet($learner, 'group_id', 0);
        if ($group_id == 0) {
            $result = '未分组';
        } else {
            /** @var \App\Models\LearningPlan $model */
            $model = $this->getModel('LearningPlan');
            $result = $model->getPkGroupById($group_id, ['name' => 1]);
            $result = ArrayGet($result, 'name', '未分组');
        }

        $this->setOutput('detail', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 智能班级发布.
     *
     * @param int $id .
     * @param int $type .
     * @return boolean
     */
    public function releaseAction($id, $type = 0, $notify, $publish_approval_id = 0, $publish_flow_data = [])
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');

        //如果开启审批流，班级发布单独走审批流
        if ($publish_approval_id) {
            $result = $model->approvalPublish($id, $type, $publish_approval_id, $publish_flow_data);
        } else {
            $result = $model->release($id, $type, $notify);
            if ($result === static::LEARNING_SIMPLE_NO_OFFLINE) return static::LEARNING_SIMPLE_NO_OFFLINE;
        }


        if ($result) {
            return Constants::SYS_SUCCESS;
        }

        return Constants::SYS_ERROR_DEFAULT;
    }

    /**
     * 获取未完成的学习计划或培训班数量(弃用)
     * @return int
     */
    public function unfinishedListAction()
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $class_num = $model->getUnfinishedNum();
        $unfinished_plan_num = $model->getUnfinishedNum(3);
        $plan_num = $model->getPlanNum(3);

        /** @var \App\Models\MyCourse $my_course_model */
        $my_course_model = $this->getModel('MyCourse');
        $course_num = $my_course_model->getCountByUsers();

        $this->setOutput('plan_num', $plan_num);
        $this->setOutput('class_num', $class_num);
        $this->setOutput('unfinished_plan_num', $unfinished_plan_num);
        $this->setOutput('course_num', $course_num);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 获取未完成的学习计划或培训班数量
     * @return int
     */
    public function unfinishedNumAction()
    {
        $num = 0;
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $class_num = $model->getUnfinishedNum();
        $num += $class_num;
        $plan_num = $model->getUnfinishedNum(3);
        $num += $plan_num;

        /** @var \App\Models\MyCourse $my_course_model */
        $my_course_model = $this->getModel('MyCourse');
        $course_num = $my_course_model->getCountByUsers();
        $num += $course_num;

        $this->setOutput('unfinished_num', $num);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 项目统计
     *
     * @param int $learning_id 班级id
     * @return int|boolean
     */
    public function projectStatisticsAction($learning_id)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->isExist($learning_id, ['name' => 1, 'finished_rate' => 1, 'type' => 1, 'is_end' => 1]);
        $type = ArrayGet($result, 'type', 0);
        $is_end = ArrayGet($result, 'is_end', 0);

//        /** @var \App\Models\Learner $learner_model */
//        $learner_model = $this->getModel('Learner');
//        $result['learner_num'] = $learner_model->getClassLearner($learning_id, $type);
        /** @var \App\Models\Learner\Learner $newLearnerModel */
        $newLearnerModel = $this->getModel('Learner\Learner');
        $result['learner_num'] = $newLearnerModel->setFilter([\App\Models\Learner::FILTER_FIELD_LEARNING_ID => $learning_id, \App\Models\Learner::FILTER_FIELD_TYPE => $type])->totalLearnerByLearning();

        if ($type == Constants::LEARNING_TRAINING) {
            $result['task_num'] = 0;
            $result['finished_learner_num'] = 0;
            if ($is_end == 1) $result['finished_learner_num'] = $result['learner_num'];
        } else {
            /** @var \App\Models\LearningTask $task_model */
            $task_model = $this->getModel('LearningTask');
            $result['task_num'] = $task_model->getTaskNum($learning_id, $type);

            $result['finished_learner_num'] = $newLearnerModel->setFilter([\App\Models\Learner::FILTER_FIELD_LEARNING_ID => $learning_id, \App\Models\Learner::FILTER_FIELD_TYPE => $type, \App\Models\Learner::FILTER_FIELD_RATE => \App\Models\Learner::RATE_STATUS_FINISHED])->totalLearnerByLearning();

//            $result['finished_learner_num'] = $learner_model->getClassLearner($learning_id, Constants::LEARNING_CLASS, [
//                'is_finished' => 1
//            ]);
        }

        $this->setOutput('detail', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * h5获取培训计划列表.
     *
     * @param string $keyword .
     * @param int $filter 0-全部 1-已报名已审核 2-已报名待审核 3-未报名
     * @param int $type 0-智能班级 3-学习计划 4-培训计划
     * @param null|\Key\Records\Pagination $pagination
     * @return array
     */
    public function trainingListByLearnerAction($keyword, $filter, $type, $pagination)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->trainingListByLearner($keyword, $filter, $type, $pagination, $total);

        $this->setOutput('list', $result);
        $this->setOutput('total', $total);
        $this->setOutput('page', $pagination->getPage());
        $this->setOutput('itemsPerPage', $pagination->getItemsPerPage());
        return Constants::SYS_SUCCESS;
    }

    /**
     * h5获取培训计划列表过滤条件下的数量.
     *
     * @param string $keyword .
     * @param int $filter 0-全部 1-已报名已审核 2-已报名待审核 3-未报名
     * @param int $type 0-智能班级 3-学习计划 4-培训计划
     * @return array
     */
    public function trainingFilterNumAction($keyword, $type)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $total = $model->trainingFilterNum($keyword, 0, $type);
        $applicant = $model->trainingFilterNum($keyword, 1, $type);
        $unexamine = $model->trainingFilterNum($keyword, 2, $type);
        $unapplicant = $model->trainingFilterNum($keyword, 3, $type);

        $this->setOutput('applicant', $applicant);
        $this->setOutput('total', $total);
        $this->setOutput('unexamine', $unexamine);
        $this->setOutput('unapplicant', $unapplicant);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 必修/选修完成概率，评分，评价人数.
     *
     * @param int $learning_id 班级/计划id
     * @param int $is_elective 0-否 1-是
     * @param int $type 0-智能班级 3-学习计划 4-培训计划
     * @return array
     */
    public function planStatisticsAction($learning_id, $is_elective, $filters, $type)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->isExist($learning_id, [
            'id' => 1,
            'name' => 1,
            'is_end' => 1,
            'start_time' => 1,
            'end_time' => 1,
            'status' => 1,
            'created' => 1,
            'finished_rate' => 1,
        ]);

        $filters = $filters->toArray();
        $source = ArrayGet($filters, 'source', '');
        /** @var \App\Models\Learner $learner_model */
        $learner_model = $this->getModel('Learner');
        $finished_rate = $learner_model->electiveFinishedRate($learning_id, $type, $is_elective, $filters);
        $result['finished_rate'] = $finished_rate;

        $this->setOutput('detail', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 结束班级
     *
     * @param int $id 班级id
     * @param int $type
     * @return boolean|int
     */
    public function endClassAction($id, $type)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $plan = $model->isExist($id, ['status' => 1]);
        if ($plan['status'] == 1) return static::LEARNING_PLAN_NOT_PUBLISHED;

        if ($model->endClass($id, $type)) {
            return Constants::SYS_SUCCESS;
        }
        return Constants::SYS_ERROR_DEFAULT;
    }

    /**
     * pc端在线学习获取报名班级列表.
     *
     * @param string $keyword 班级/计划的名称
     * @param int $filter 0-全部 1-未报名 2-审核中
     * @param int $enroll_type 0全部 1报名中 2报名结束
     * @param null|\Key\Records\Pagination $pagination
     * @return array
     */
    public function applicantListAction($keyword, $filter, $pagination, $enroll_type = 0)
    {
        $commonFilters = [
            'enroll_type' => $enroll_type
        ];
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->setCommonFilter($commonFilters)->applicantList($keyword, $filter, $pagination, $total);

        $this->setOutput('list', $result);
        $this->setOutput('total', $total);
        return Constants::SYS_SUCCESS;
    }

    /**
     * pc端在线学习获取报名班级列表.
     *
     * @param string $keyword 班级/计划的名称
     * @param int $examine_status 0待审核 1已审核 2审核不通过
     * @param int $filter 0-全部 1-未报名 2-已报名
     * @param int $enroll_type 0全部 1报名中 2报名结束
     * @param null|\Key\Records\Pagination $pagination
     * @return array
     */
    public function myApplicantListAction($keyword, $examine_status, $filter, $pagination, $enroll_type = 0)
    {
        $commonFilters = [
            'enroll_type' => $enroll_type
        ];
        //获取我已报名的所有班级ID（已报名包括 待审核 已审核 不通过）
        $learner_is_examine = [\App\Models\Learner::EXAMINE_NOT_REVIEW, \App\Models\Learner::EXAMINE_REVIEW_PASSES, \App\Models\Learner::EXAMINE_REVIEW_NOT_PASS];
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->setCommonFilter($commonFilters)->myApplicantList([], $keyword, $examine_status, $filter, $pagination, $total, [], $learner_is_examine);
        Utils::convertMongoDateToTimestamp($result);
        $this->setOutput('list', $result);
        $this->setOutput('total', $total);
        return Constants::SYS_SUCCESS;
    }

    /**
     * h5定制报名-kuka
     * @param $record
     * @param $sort
     * @param $pagination
     * @return int
     */
    public function customApplicantListAction($record, $sort, $pagination)
    {
        $record = $record->toArray(true);
        /** @var \App\Models\Learning\KukaCustomApplicant $modelKukaCustomApplicant */
        $modelKukaCustomApplicant = $this->getModel('Learning\KukaCustomApplicant');
        $result = $modelKukaCustomApplicant->customApplicantList($record, $sort, $pagination, $total);

        if ($result) $result = $modelKukaCustomApplicant->customApplicationData($result);

        $this->setOutput('list', $result);
        $this->setOutput('total', $total);
        return Constants::SYS_SUCCESS;
    }

    /**
     * h5定制报名-kuka-详情
     * @param $learning_id
     * @param $type
     * @param $pagination
     * @return int
     */
    public function customApplicantViewAction($learning_id, $task_type = Constants::TASK_OFFLINE_TEACHING, $type = Constants::LEARNING_CLASS, $pagination)
    {
        /** @var \App\Models\Learning\KukaCustomApplicant $modelKukaCustomApplicant */
        $modelKukaCustomApplicant = $this->getModel('Learning\KukaCustomApplicant');
        $result = $modelKukaCustomApplicant->customApplicantTask($learning_id, $type, $task_type);

        $this->setOutput('plan', $result['plan']); //班级详情
        $this->setOutput('tasks', $result['tasks']); //任务列表
        $this->setOutput('lecturer', $result['lecturer']); //讲师风采

        return Constants::SYS_SUCCESS;
    }

    /**
     * h5定制报名-培训统计-kuka
     * @param $record
     * @return array
     */
    public function customApplicantStatisticsAction($record)
    {
        $record = $record->toArray(true);
        /** @var \App\Models\Learning\KukaCustomApplicant $modelKukaCustomApplicant */
        $modelKukaCustomApplicant = $this->getModel('Learning\KukaCustomApplicant');
        $result = $modelKukaCustomApplicant->customApplicantStatistics($record);

        $this->setOutput('list', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * h5定制报名-培训开展场次占比-kuka
     * @param $record
     * @return array
     */
    public function customTrainingScaleAction($record)
    {
        $record = $record->toArray(true);
        /** @var \App\Models\Learning\KukaCustomApplicant $modelKukaCustomApplicant */
        $modelKukaCustomApplicant = $this->getModel('Learning\KukaCustomApplicant');
        $result = $modelKukaCustomApplicant->customTrainingScale($record);

        $this->setOutput('list', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * h5定制报名-自主报名场次占比-kuka
     * @param $record
     * @return array
     */
    public function customApplicantScaleAction($record)
    {
        $record = $record->toArray(true);
        /** @var \App\Models\Learning\KukaCustomApplicant $modelKukaCustomApplicant */
        $modelKukaCustomApplicant = $this->getModel('Learning\KukaCustomApplicant');
        $result = $modelKukaCustomApplicant->customApplicantScale($record);

        $this->setOutput('list', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 培训开展场次统计：每月培训场次总数以及该月自主报名培训场次总数
     * h5定制报名-kuka
     * @param $record
     * @return array
     */
    public function customScaleMonthPlanAction($record)
    {
        $record = $record->toArray(true);
        /** @var \App\Models\Learning\KukaCustomApplicant $modelKukaCustomApplicant */
        $modelKukaCustomApplicant = $this->getModel('Learning\KukaCustomApplicant');
        $result = $modelKukaCustomApplicant->customScaleMonthPlan($record);

        $this->setOutput('list', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 培训开展覆盖人次统计：每月培训覆盖总人次以及该月自主报名总人次
     * h5定制报名-kuka
     * @param $record
     * @return array
     */
    public function customScaleMonthLearnerAction($record)
    {
        $record = $record->toArray(true);
        /** @var \App\Models\Learning\KukaCustomApplicant $modelKukaCustomApplicant */
        $modelKukaCustomApplicant = $this->getModel('Learning\KukaCustomApplicant');
        $result = $modelKukaCustomApplicant->customScaleMonthLearner($record);

        $this->setOutput('list', $result);
        return Constants::SYS_SUCCESS;
    }

    public function getCustomStatisticsDepartmentAction(){
        /** @var \App\Models\Learning\KukaCustomApplicant $modelKukaCustomApplicant */
        $modelKukaCustomApplicant = $this->getModel('Learning\KukaCustomApplicant');
        $result = $modelKukaCustomApplicant->getCustomStatisticsDepartment();

        $this->setOutput('list', $result);
        return Constants::SYS_SUCCESS;
    }

    public function getCustomStatisticsCategoryAction(){
        /** @var \App\Models\Learning\KukaCustomApplicant $modelKukaCustomApplicant */
        $modelKukaCustomApplicant = $this->getModel('Learning\KukaCustomApplicant');
        $result = $modelKukaCustomApplicant->getCustomStatisticsCategory();

        $this->setOutput('list', $result);
        return Constants::SYS_SUCCESS;
    }//getCustomStatisticsCategory

    /**
     * 培训分类场次占比：每个分类培训开展场次占比总培训开展场次的百分比
     * h5定制报名-kuka
     * @param $record
     * @return array
     */
    public function customScaleCategoryPlanAction($record)
    {
        $record = $record->toArray(true);
        /** @var \App\Models\Learning\KukaCustomApplicant $modelKukaCustomApplicant */
        $modelKukaCustomApplicant = $this->getModel('Learning\KukaCustomApplicant');
        $result = $modelKukaCustomApplicant->customScaleCategoryPlan($record);

        $this->setOutput('list', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 培训场次和人次统计：每个分类培训开展场次以及对应场次总人次
     * h5定制报名-kuka
     * @param $record
     * @return array
     */
    public function customScaleCategoryLearnerAction($record)
    {
        $record = $record->toArray(true);
        /** @var \App\Models\Learning\KukaCustomApplicant $modelKukaCustomApplicant */
        $modelKukaCustomApplicant = $this->getModel('Learning\KukaCustomApplicant');
        $result = $modelKukaCustomApplicant->customScaleCategoryLearner($record);

        $this->setOutput('list', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * pc端在线学习获取报名班级列表过滤条件下的数量.
     *
     * @param string $keyword 班级/计划的名称
     * @return array
     */
    public function applicationFilterNumsAction($keyword)
    {

        $filter = [];
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $filter['total'] = $model->applicationFilterNums($keyword, 0);
        $filter['un_application'] = $model->applicationFilterNums($keyword, 1);
        $filter['reviewing'] = $model->applicationFilterNums($keyword, 2);

        $this->setOutput('filter', $filter);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 获取可调班级.
     *
     * @param array $filters
     * @param int $project_id
     * @param int $learning_id
     * @param int $eid
     * @param int $filter
     * @param string $keyword
     * @param null|\Key\Records\Pagination $pg
     * @return array
     */
    public function shiftClassesAction($project_id, $learning_id, $eid, $filter, $keyword, $pg)
    {

        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $learner_type = 0;//1游客 0非游客
        if ($learning_id) {
            $detail = $model->isExist($learning_id);
            $learner_type = ArrayGet($detail, 'learner_type', 0);
        }
        if (!$learner_type) {
            $filters = [
                'project_id' => $project_id,
                'finished_status' => $filter,
                'eid' => $eid ?: $this->getEmployeeId()
            ];
            //调班时，未报名列表的要包括不通过的，所以已报名班级只取（待审核和已通过）
            $learner_is_examine = [\App\Models\Learner::EXAMINE_NOT_REVIEW, \App\Models\Learner::EXAMINE_REVIEW_PASSES];
            $result = $model->myApplicantList($filters, $keyword, -1, 1, $pg, $total, [], $learner_is_examine);

            Utils::convertMongoDateToTimestamp($result);
//            $this->setOutput('list', $result);
//            $this->setOutput('total', $total);

//            $result = $model->shiftClasses($project_id, $filters, $pg);
//            Utils::convertMongoDateToTimestamp($result);
//            $total = $model->shiftClassesTotal($project_id, $filters);
        }

        $this->setOutput('list', $learner_type ? [] : $result);
        $this->setOutput('total', $learner_type ? 0 : $total);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 大屏数据（已发布学习计划，智能班级，考试，学习时长，累计人均学时时长）.
     *
     * @return array
     */
    public function screenDataAction()
    {

//        /** @var \App\Models\LearningPlan $plan_model */
//        $plan_model = $this->getModel('LearningPlan');
//        $result['published_class'] = $plan_model->learningNum(['learning_status' => 'published'], Constants::LEARNING_CLASS);
//        $result['published_plan'] = $plan_model->learningNum(['learning_status' => 'published'], Constants::LEARNING_PLAN);
//
//        /** @var \App\Models\LearningDetailLog $log_detail_model */
//        $log_detail_model = $this->getModel('LearningDetailLog');
//        $result['total_learning_time'] = $log_detail_model->learningTimeByYear();
//
//        /** @var \App\Models\Employee $employee_model */
//        $employee_model = $this->getModel('Employee');
//        $employee_model->setWithoutACL(true);
//        $employee_total = $employee_model->getTotal();
//        $result['total_avg_time'] = $employee_total ? ceil($result['total_learning_time'] / $employee_total) : 0;
//
//        /** @var \App\Models\ExamStatistics $exam_sta_model */
//        $exam_sta_model = $this->getModel('ExamStatistics');
//        $result['published_exam'] = $exam_sta_model->getPublishTotal();
//
//        /** @var \App\Models\Learner $learner_model */
//        $learner_model = $this->getModel('Learner');
//        $result['training_trips'] = $learner_model->trainingTrips();

        $result = [
            'published_class' => 0,
            'published_plan' => 0,
            'total_learning_time' => 0,
            'total_avg_time' => 0,
            'published_exam' => 0,
            'training_trips' => 0
        ];
        /** @var \App\Models\StatisticsScreen $screen_model */
        $screen_model = $this->getModel('StatisticsScreen');
        $data = $screen_model->isExists();
        if ($data) $result = $data['learning_sta'];

        $this->setOutput('total', $result);
        return Constants::SYS_SUCCESS;
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
    public function notIncludeTaskPlansAction($task_id, $task_type, $type, $pg)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->notIncludeTaskPlans($task_id, $task_type, $type, $pg);

        $this->setOutput('list', $result);
        return Constants::SYS_SUCCESS;
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
    public function squareListAction($filters, $type, $pg, $sort)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $filters = $filters->toArray();
        $result = $model->squareList($filters, $type, $pg, $sort);
        $total = $model->squareTotal($filters, $type);

        $this->setOutput('list', $result);
        $this->setOutput('total', $total);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 修改签到完成率规则设置.
     *
     * @param int $learning_id 班级id.
     * @param int $rate_setting 1-请假算进度 2-请假不算进度.
     * @param int $type .
     * @return boolean|int
     */
    public function updateSignRateSettingAction($learning_id, $rate_setting, $type)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->updateSignRateSetting($learning_id, $rate_setting, $type);

        if ($result) {
            return Constants::SYS_SUCCESS;
        }
        return Constants::SYS_ERROR_DEFAULT;
    }

    /**
     * 查看签到规则设置.
     *
     * @param int $learning_id 班级id.
     * @param int $type .
     * @return boolean|int
     */
    public function viewSignSettingsAction($learning_id, $type)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->viewSignSettings($learning_id, $type);

        $this->setOutput('detail', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 获取报名信息.
     *
     * @param int $id 班级id.
     * @return boolean|int
     */
    public function viewEnrollInfoAction($id)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->isExist($id);

        $this->setOutput('detail', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 更新报名信息.
     *
     * @param int $id 班级id.
     * @param \App\Records\LearningEnroll $record .
     * @return boolean|int
     */
    public function updateEnrollInfoAction($id, $record)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->updateEnrollInfo($id, $record);

        if ($result) {
            return Constants::SYS_SUCCESS;
        }
        return Constants::SYS_ERROR_DEFAULT;
    }

    /**
     * 获取班级解锁方式.
     *
     * @param int $id 班级id.
     * @param int $type .
     * @return array
     */
    public function viewUnlockConAction($id)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->isExist($id, [
            'id' => 1,
            'unlock_con' => 1,
            'status' => 1,
        ]);

        $this->setOutput('detail', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 更新班级解锁方式.
     *
     * @param int $id 班级id.
     * @param int $unlock_con .
     * @param int $unlock_detail .
     * @param int $type .
     * @return boolean|int
     */
    public function updateUnlockConAction($id, $unlock_con, $unlock_detail, $type = Constants::LEARNING_CLASS, $tasks_switch)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->updateUnlockCon($id, $unlock_con, $unlock_detail, $type, $tasks_switch);

        if ($result) {
            return Constants::SYS_SUCCESS;
        }
        return Constants::SYS_ERROR_DEFAULT;
    }

    /**
     * 获取公司下所有已发布未结束的学习模块儿.
     *
     * @param \App\Records\LearningPlanFilters $filters .
     * @param int $type .
     * @param \Key\Records\Pagination $pg .
     * @return array
     */
    public function publishedNotEndLearningsAction($filters, $type, $pg)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $filters = $filters->toArray();
        $filters['data_from'] = 'all_published_not_end';
        $result = $model->getList($filters, $type, 0, $pg, [], [
            'id' => 1,
            'name' => 1,
            'type' => 1,
            'start_time' => 1,
            'end_time' => 1,
            'is_end' => 1,
            'status' => 1,
            'display' => 1,
            'finished_rate' => 1,
        ]);
        $total = $model->getTotal($filters, $type, 0);

        $this->setOutput('list', $result);
        $this->setOutput('total', $total);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 获取公司下所有已发布未结束的学习模块儿过滤条件下的数量.
     *
     * @param \App\Records\LearningPlanFilters $filters .
     * @param int $type .
     * @param \Key\Records\Pagination $pg .
     * @return array
     */
    public function publishedNotEndFilterNumAction($filters, $type)
    {
        $filters = $filters->toArray();
        $filters['data_from'] = 'all_published_not_end';
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $filter['not_start'] = $model->getTotal(array_merge($filters, ['filter' => 1]), $type, 0);
        $filter['unfinished'] = $model->getTotal(array_merge($filters, ['filter' => 2]), $type, 0);
        $filter['finished'] = $model->getTotal(array_merge($filters, ['filter' => 3]), $type, 0);
        $filter['delay'] = $model->getTotal(array_merge($filters, ['filter' => 4]), $type, 0);
        $filter['total'] = $model->getTotal(array_merge($filters, ['filter' => 0]), $type, 0);

        $this->setOutput('list', $filter);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 部门项目统计获取学习计划/智能班级列表.
     *
     * @param \App\Records\LearningPlanFilters $filters .
     * @param int $type .
     * @param \Key\Records\Pagination $pg .
     * @return array
     */
    public function publishedLearningsAction($filters, $type, $pg)
    {
        $filters = $filters->toArray();
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $model->transListDataFrom($filters['data_from'], $new_data_from);
        $filters['data_from'] = $new_data_from;
        $result = $model->getList($filters, $type, 0, $pg, [], [
            'id' => 1,
            'name' => 1,
            'type' => 1,
            'start_time' => 1,
            'end_time' => 1,
            'is_end' => 1,
            'status' => 1,
            'display' => 1,
            'creator_no' => 1,
            'organization' => 1,
            'finished_rate' => 1,
            'learner_num' => 1
        ]);
        $total = $model->getTotal($filters, $type, 0);

        $this->setOutput('list', $result);
        $this->setOutput('total', $total);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 学员确认智能班级列表.
     *
     * @param \App\Records\LearningPlanFilters $filters .
     * @param \Key\Records\Pagination $pg .
     * @param array $sort .
     * @return array
     */
    public function confirmLearningsAction($filters, $pg, $sort)
    {
        $filters = $filters->toArray();
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->confirmLearnings($filters, $pg, [], $sort);
        $total = $model->confirmLearningsTotal($filters);

        $this->setOutput('list', $result);
        $this->setOutput('total', $total);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 学员确认已发布/确认中数量.
     *
     * @param \App\Records\LearningPlanFilters $filters .
     * @return array
     */
    public function confirmFilterNumAction($filters)
    {
        $filters = $filters->toArray();
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = ['total' => 0, 'confirming' => 0, 'published' => 0];
        $result['total'] = $model->confirmLearningsTotal($filters);
        $result['confirming'] = $model->confirmLearningsTotal(array_merge($filters, ['status' => 1]));
        $result['published'] = $model->confirmLearningsTotal(array_merge($filters, ['status' => 2]));

        $this->setOutput('detail', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 提交班级.
     *
     * @param int $learning_id .
     * @return boolean|int
     */
    public function submitAction($learning_id)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->submit($learning_id);

        if ($result) {
            return Constants::SYS_SUCCESS;
        }
        return Constants::SYS_ERROR_DEFAULT;
    }

    public function getBillListAction($learning_id, $type, $bill_type = LearningBill::BILL_TYPE_SHARE)
    {
        /** @var \App\Models\LearningBill $model */
        $model = $this->getModel('LearningBill');
        $result = $model->getList($learning_id, $type, $bill_type);

        $new_data = [];
        $num = 0;
        foreach ($result as $key => $value) {
            if ($value['path_type'] == 2) {
                $data_two = [
                    'id' => $value['id'],
                    'path' => $value['path'],
                    'path_type' => $value['path_type'],
                    'learning_type' => $value['learning_type'],
                    'bill_type' => $value['bill_type'],
                    'learning_id' => $value['learning_id'],
                ];
                $new_data[$num] = $data_two;
                $num++;
            } else {
                foreach ($value['initPath'] as $k => $v) {
                    $num++;
                    $new_data[$num]['id'] = $value['id'];
                    $new_data[$num]['path'] = $v['path'];
                    $new_data[$num]['path_type'] = $value['path_type'];
                    $new_data[$num]['learning_type'] = $value['learning_type'];
                    $new_data[$num]['bill_type'] = $value['bill_type'];
                    $new_data[$num]['learning_id'] = $value['learning_id'];
                }
            }
        }
        $this->setOutput('list', $new_data);
        return Constants::SYS_SUCCESS;
    }

    public function createBillAction($learning_id, $url, $type = Constants::LEARNING_CLASS, $bill_type = LearningBill::BILL_TYPE_SHARE)
    {
        /** @var \App\Models\LearningBill $model */
        $model = $this->getModel('LearningBill');
        $result = $model->createBill($learning_id, $type, 0, 2, $url, $bill_type);

        if ($result) {
            $this->setOutput('id', $result);
            return Constants::SYS_SUCCESS;
        }
        return Constants::SYS_ERROR_DEFAULT;
    }

    /**
     * 删除分享海报.
     *
     * @param int $learning_id
     * @param int $type
     * @param int $id
     * @return boolean
     */
    public function removeBillAction($learning_id, $type, $id, $bill_type = LearningBill::BILL_TYPE_SHARE)
    {
        /** @var \App\Models\LearningBill $model */
        $model = $this->getModel('LearningBill');
        $result = $model->removeBill($learning_id, $type, $id, $bill_type);

        if ($result) {
            return Constants::SYS_SUCCESS;
        }
        return Constants::SYS_ERROR_DEFAULT;
    }

    /**
     * 项目班级排名.
     *
     * @param int $project_id
     * @param \Key\Records\Pagination $pg
     * @return array
     */
    public function classRankingsAction($project_id, $pg)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $filters = [];
        $result = $model->getListByProjectId($project_id, $filters, $pg, ['finished_rate' => -1], [
            'id' => 1,
            'name' => 1,
            'finished_rate' => 1
        ]);
        $total = $model->getListByProjectIdTotal($project_id, $filters);

        $this->setOutput('list', $result);
        $this->setOutput('total', $total);
        return Constants::SYS_SUCCESS;
    }

    //***********************************************************

    /**
     * 智能班级设置积分规则
     * @param $setting
     * @param $learning_id
     * @return int
     * @throws \Key\Exception\AppException
     * @throws \Key\Exception\DatabaseException
     */
    public function pointSetAction($setting, $learning_id)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $new_data = [];
        foreach ($setting as $key => $value) {
            $new_data[] = $value->toArray();
        }
        $result = $model->pointSet($new_data, $learning_id);
        if ($result) {
            return Constants::SYS_SUCCESS;
        }
        return Constants::SYS_ERROR_DEFAULT;
    }

    /**
     * 获取智能班级积分规则
     * @param $learning_id
     * @return int
     * @throws \Key\Exception\DatabaseException
     */
    public function pointSetViewAction($learning_id)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->pointSetView(0, $learning_id);

        if ($result) {
            /** @var \App\Models\Point\ClassPoint $model */
            $classPoint = $this->getModel('Point\\ClassPoint');
            $result = $classPoint->dealClassPointSettings($result);
        }

        $this->setOutput('list', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 手动加积分
     * @param $learning_id
     * @param $eid
     * @param $point
     * @param $desc
     * @return int
     * @throws \Key\Exception\DatabaseException
     */
    public function manualAdjustmentAction($learning_id, $eid, $point_score, $desc)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->manualAdjustment($learning_id, $eid, $point_score, $desc);
        // if ($result === 4000) {
        //     return self::NOT_ENOUGH_SCORE;
        // }
        $this->setOutput('result', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 获取个人总积分
     * @param $learning_id
     * @param $eid
     * @return int
     */
    public function employeePointAction($learning_id, $eid)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->employeePoint($learning_id, $eid);
        $this->setOutput('point', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 积分变动记录
     * @param $learning_id
     * @param $eid
     * @return int
     */
    public function pointLogAction($learning_id, $eid, $pg)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->pointLog($learning_id, $eid, $pg);
        $total = $model->get_point_log_total($learning_id, $eid);
        $this->setOutput('list', $result);
        $this->setOutput('total', $total);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 导出个人积分明细 queue
     * @param $learning_id
     * @param $eid
     * @return int
     */
    public function pointLogExportAction($learning_id, $eid)
    {
        /** @var \App\Models\BaseQueue $model */
        $model = $this->getModel('BaseQueue');
        $queue = $model->getLatestQueue('queue_export_learning_class_point_log');
        if ($queue) {
            $progress = ArrayGet($queue, 'progress');
            if (in_array($progress, [$model::PROGRESS_NOT_START, $model::PROGRESS_STARTING])) {
                /** @var UTCDateTime $updated */
                $updated = ArrayGet($queue, 'updated');
                if ($updated) {
                    $ts = $updated->toDateTime()->getTimestamp();
                    if (time() - $ts <= self::TIMEOUT) {
                        return self::QUE_EXISTS;
                    } else {
                        $this->log('++++++++++++++ timeout!!!');
                    }
                }
            }
        }

        /** @var \App\Models\LearningPlan $model */
        $model_learning_plan = $this->getModel('LearningPlan');
        $q_id = $model_learning_plan->export_point_log($learning_id, $eid);
        $this->setOutputs(['q_id' => $q_id]);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 个人排名数据
     * @param $learning_id
     * @param $group_id
     * @return int
     */
    public function learnerRankingAction($learning_id, $group_id)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->learnerRanking($learning_id, $group_id);
        $this->setOutput('detail', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 智能班级积分排行榜
     * @param $learning_id
     * @return int
     * @throws \Key\Exception\DatabaseException
     */
    public function pointRankAction($learning_id, $pg)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->pointRank($learning_id, $pg);
        $total = $model->getRankTotal($learning_id);
        $this->setOutput('total', $total);
        $this->setOutput('list', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 智能班级 积分分组排行
     * @param $learning_id
     * @param $type
     * @param $pg
     * @return int
     */
    public function pointGroupRankAction($learning_id, $type, $pg)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->getGroupRank($learning_id, $type, $pg);
        $total = $model->getGroupRankTotal($learning_id);
        $this->setOutput('list', $result);
        $this->setOutput('total', $total);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 组内排行榜
     * @param $learning_id
     * @param $group_id
     * @param $pg
     * @return int
     */
    public function pointGroupEmpRankAction($learning_id, $group_id, $pg)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->getGroupEmpRank($learning_id, $group_id, $pg);
        $total = $model->getGroupEmpRankTotal($learning_id, $group_id);
        $this->setOutput('list', $result);
        $this->setOutput('total', $total);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 数据看板 ==》 积分统计
     * @param $learning_id
     * @param $group_id
     * @param $keyword
     * @param $pg
     * @return int
     */
    public function pointStatisticsAction($learning_id, $group_id, $keyword, $pg)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->pointStatistics($learning_id, $group_id, $keyword, $pg);
        $total = $model->pointPointStatisticsTotal($learning_id, $group_id, $keyword);
        $this->setOutput('list', $result);
        $this->setOutput('total', $total);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 积分统计导出
     * @param $learning_id
     * @param $group_id
     * @param $keyword
     * @return int
     */
    public function pointStatisticsExportAction($learning_id, $group_id, $keyword)
    {
        /** @var \App\Models\BaseQueue $model */
        $model = $this->getModel('BaseQueue');
        $queue = $model->getLatestQueue('queue_export_learning_class_point_statistics');
        if ($queue) {
            $progress = ArrayGet($queue, 'progress');
            if (in_array($progress, [$model::PROGRESS_NOT_START, $model::PROGRESS_STARTING])) {
                /** @var UTCDateTime $updated */
                $updated = ArrayGet($queue, 'updated');
                if ($updated) {
                    $ts = $updated->toDateTime()->getTimestamp();
                    if (time() - $ts <= self::TIMEOUT) {
                        return self::QUE_EXISTS;
                    } else {
                        $this->log('++++++++++++++ timeout!!!');
                    }
                }
            }
        }

        /** @var \App\Models\LearningPlan $model */
        $model_learning_plan = $this->getModel('LearningPlan');
        $q_id = $model_learning_plan->export_point_Statistics($learning_id, $group_id, $keyword);
        $this->setOutputs(['q_id' => $q_id]);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 考试统计，获取所有学习模块儿的名称
     * @param array $learning_ids
     * @param array $types
     * @return array
     */
    public function getIndexPlanInfoByIdsAction($learning_ids, $types = [])
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->getIndexPlanInfoByIds($learning_ids, $types ?: [
            Constants::LEARNING_PLAN, Constants::LEARNING_CLASS
        ], [
            'id' => 1,
            'name' => 1,
            'type' => 1
        ]);

        $this->setOutput('list', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 移动端获取班级样式
     * @param $keyword
     * @param $learning_id
     * @param $pagination
     * @return int
     * @throws \Key\Exception\DatabaseException
     */
    public function getStyleDisplayAction($learning_id, $type)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $display_style = $model->isExist($learning_id, [
            'display_style' => 1,
            'simple_plan' => 1
        ]);

        $this->setOutput('result', $display_style);
        return Constants::SYS_SUCCESS;
    }

    /**
     * PC学员端代报名多班次中班级列表
     *
     * @param $project_id
     * @param $status
     * @param $is_applicant
     * @param $filters
     * @param $pg
     * @return int
     */
    public function getDelegatePlanListAction($project_id, $status, $is_applicant, $sort, $pg)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->getProjectDelegatePlanList($project_id, $status, $is_applicant, $total, $sort, $pg);

        $this->setOutput('plan', $result);
        $this->setOutput('total', $total);
        return Constants::SYS_SUCCESS;
    }

    /**
     * PC学员端智能班级代报名列表
     *
     * @param $keyword
     * @param $filter
     * @param $sort
     * @param $pg
     * @return int
     */
    public function getDelegateListAction($keyword, $filter, $sort, $pg)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->getDelegatePlanList(Constants::LEARNING_CLASS, $keyword, $filter, $sort, $pg, $total);

        $this->setOutput('plan', $result);
        $this->setOutput('total', $total);
        return Constants::SYS_SUCCESS;
    }

    public function setSimplePlanSettingAction($learning_id, $type, $is_exam, $is_assessment){
        $rules = [
            'is_exam' => $is_exam ?: 0,
            'is_assessment' => $is_assessment ?: 0
        ];
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->setSimplePlanSetting($learning_id, $type, $rules);

        if ($result) {
            return Constants::SYS_SUCCESS;
        }
        return Constants::SYS_ERROR_DEFAULT;
    }

    public function getSimplePlanSettingAction($learning_id, $type){
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->getSimplePlanSetting($learning_id, $type);

        $this->setOutput('detail', $result);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 获取学员默认的自学清单id(大清单)
     * @return int
     */
    public function getAutonomyDefaultIdAction()
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $id = $model->getLearningAutoDefaultId();
        $this->setOutput('default_id', $id);
        return Constants::SYS_SUCCESS;
    }

    public function taskLearnerAction($learning_id, $task_id, $record, $pg)
    {
        $record = $record->toArray(true);

        /** @var \App\Models\LearningPlanLog $model */
        $model = $this->getModel('LearningPlanLog');
        $result = $model->getTaskLearner($learning_id, $task_id, $record, $pg);
        $total = $model->getTaskLearnerTotal($learning_id, $task_id, $record);
        $this->setOutput('list', $result);
        $this->setOutput('total', $total);
        return Constants::SYS_SUCCESS;
    }

    public function taskLearnerExportAction($learning_id, $task_id, $record)
    {
        set_time_limit(120);

        $filters = $record->toArray(true);
        $filters['learning_id'] = $learning_id;
        $filters['task_id'] = $task_id;

        /** @var \AuthProject\Models\Exports\LearningPlanTaskLearner $learnerExporter */
        $learnerExporter = new LearningPlanTaskLearner($this->app);
        $learnerExporter->setFilter($filters)->setDebug(true, 1);
        $local = $learnerExporter->export();
        if ($local) {
            $aid = $this->getAccountId();
            $prefixName = $learnerExporter->getFilenameDefinition();
            $ext = pathinfo($local, PATHINFO_EXTENSION);
            $ossFilename = $prefixName . '-' . date('YmdHis') . '.' . $ext;

            $fileClient = new FileClient($this->app);
            $accountModel = new \App\Models\Account($this->app);
            $fileConf = $accountModel->getConfigure('file');
            $startWithDir = $fileConf['startsWithDir'] ?? '';
            $res = $fileClient->upload($local, $ossFilename, [], 0, $startWithDir . 'exports/' . $aid . '/' . date('Y/m/d') . '/');
            if ($res) {
                unlink($local);
                $this->setOutputs($res);
                return Constants::SYS_SUCCESS;
            }
        }
        return Constants::SYS_ERROR_DEFAULT;
    }

    public function taskLearnerImportAction($learning_id, $task_id, $type, $file)
    {
        set_time_limit(600);
        /** @var \App\Models\LearningTaskLearnerImporter $importer */
        $importer = $this->getModel('LearningTaskLearnerImporter');
        $importer->setFilters($learning_id, $type, $task_id);
        $res = $importer->exec($file->full_name);
        $this->setOutput('valid_rows', $importer->getValidRows());
        $this->setOutput('invalid_rows', $importer->getInvalidRows());
        $this->setOutput('res', $res);
        $this->setOutput('success_total', count($importer->getValidRows()));
        $this->setOutput('error_total', count($importer->getInvalidRows()));

        return Constants::SYS_SUCCESS;
    }

    /**
     * 首页面授课培训日历
     * @return array
     */
    public function trainingOfflineTaskAction()
    {
        $now = time();
        $start_time = date('Y-m-d', $now);
        $previous_day = date('Y-m-d', strtotime('+1 day', $now));
        $end_time = date('Y-m-d', strtotime($start_time . ' +7 days'));

        $filters = [
            'started' => strtotime($start_time) * 1000,
            'ended' => strtotime($end_time) * 1000,
            'learning_statuses' => [\App\Models\Learner::LEARNING_STATUS_PUBLISHED]
        ];
        /** @var \App\Models\LearningTask $modelTask */
        $modelTask = $this->getModel('LearningTask');
        $result = $modelTask->tasksByTaskType(Constants::TASK_OFFLINE_TEACHING, $filters);
        Utils::convertMongoDateToTimestamp($result);

        $newArray = [];
        foreach ($result as $key => $value) {
            $lec_names = array_column($value['lecturers'], 'name');
            $value['lecturers'] = implode(',', $lec_names);
            $value['task_study_time'] = round($value['task_study_time'] / 60 , 2);

            //当前课的开始时间
            $rowStart = date('Y-m-d',((string)$value['start_time'] / 1000));
            //当前课的结束时间
            $rowEnd = date('Y-m-d',((string)$value['end_time'] / 1000));

            if ($rowStart < $previous_day) {
                $rowStart = $previous_day;
            }

            if ($rowEnd > $end_time) {
                $rowEnd = $end_time;
            }

            $currentDate = $rowStart;

            while ($currentDate <= $rowEnd) {
                $newArray[$currentDate][] = $value;
                $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
            }
        }
        ksort($newArray);
        $this->setOutput('list', $newArray);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 获取跟踪者的班级列表.
     * @param $type
     * @param $pg
     * @param $sort
     * @param array $filters
     * @return int
     */
    public function getPlanStalkerListAction($type, $pg, $sort, $filters = [])
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');

        $filters = $filters->toArray();
        $result = $model->getListByStalker($type, $filters, $pg, $sort);
        $total = $model->getTotalByStalker($type, $filters);

        $this->setOutput('list', $result);
        $this->setOutput('total', $total);

        return Constants::SYS_SUCCESS;
    }

    /**
     * 获取跟踪者的班级详情
     * @param $learning_id
     * @param $type
     * @param $pg
     * @param $sort
     * @param array $filters
     * @return int
     */
    public function getPlanStalkerDetailListAction($learning_id, $type, $pg, $sort, $filters = [])
    {
        $filters = $filters->toArray();
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');

        $result = $model->getDetailListByStalker($learning_id, $type, $pg, $sort, $filters, $total);

        $this->setOutput('list', $result);
        $this->setOutput('total', $total);
        return Constants::SYS_SUCCESS;
    }

    /**
     * 班级禁启用.
     *
     * @param int $id .
     * @param int $enabled 禁启用 0-否 1-是.
     * @param int $type .
     * @return boolean
     */
    public function enabledAction($id, $enabled, $type)
    {
        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');
        $result = $model->enabled($id, $enabled, $type);

        if ($result) {
            return Constants::SYS_SUCCESS;
        }
        return Constants::SYS_ERROR_DEFAULT;
    }
}
