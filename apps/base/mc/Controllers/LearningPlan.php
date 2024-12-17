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
    public function createAction()
    {


        /** @var \App\Models\LearningPlan $model */
        $model = $this->getModel('LearningPlan');

        $result = $model->create();

        if ($result) {
            return Constants::SYS_SUCCESS;
        }
        return Constants::SYS_ERROR_DEFAULT;
    }
}
