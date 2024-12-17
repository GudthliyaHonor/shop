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


use Key\Constants as KeyConstants;

/**
 * Class Constants
 * @package App\Common
 */
final class Constants extends KeyConstants implements Coll
{

    ///////////////////////////////////////////////////////////////////////////////////////////////
    ///////////////////////////////////////////////////////////////////////////////////////////////
    // Field Type
    const FIELD_TYPE_SINGLE = 1;
    const FIELD_TYPE_MULTIPLE = 2;
    const FIELD_TYPE_DATETIME = 3;
    const FIELD_TYPE_TEXT = 4;
    const FIELD_TYPE_NUMBER = 5;
    const FIELD_TYPE_ARRAY = 6; // sub document
    const FIELD_TYPE_FILE = 7;

    const SESSION_KEY_PC_ACCOUNT = '__PC_ACCOUNT__';
    /** @deprecated */
    const SESSION_KEY_PC_EMPLOYEE = '__PC_EMPLOYEE__';
    const MOCK_KEY = '__CONSOLE__';
    const SESSION_KEY_CURRENT_ACCOUNT_ID = '__CURRENT_ACCOUNT_ID__';
    const SESSION_KEY_CURRENT_EMPLOYEE_ID = '__CURRENT_EMPLOYEE_ID__';
    const SESSION_KEY_CURRENT_ACCOUNT_CODE = '__CODE__';
    const SESSION_KEY_TOKEN = '__TOKEN__';
    const SESSION_2FA_BOUND = '_2FA_';
    const CURRENT_ROUTE = '__ROUTE__';

    /** 签到登录 */
    const SESSION_MODE_CONV = '__CONV__';

    /** Credit codes */
    const EVERYDAY_FIRST_LOGIN = 1; //每日首次登录
    const EVERYDAY_SIGN_IN = 2; //每日签到
    const FINISH_PRACTICE = 3; //参与一次练习
    const LEARN_COURSE = 4; //学习一节微课
    const PASS_EXAM = 5; //通过一次考试
    const LEARN_KNOWLEDGE = 6; //学习一个知识
    const UPLOAD_KNOWLEDGE = 7; //上传一个知识
    const FINISH_LEARNING_PLAN = 8; //完成一个学习计划
    const BREAK_THOUGH = 9; //闯关通过一次
    const COMMENTS = 10; //评论一次
    const THUMBS_UP = 11; //评论被点赞
    const COLLECTION_KNOWLEDGE = 12; //上传知识被收藏
    const REPLY_ISSUE = 13; //回答问题
    const THUMBS_REPLY = 14; //回复被点赞
    const FOLLOW_ISSUE = 15; //问题被关注
    const COMPLETE_CLASS_LEARNING = 16; //完成智能班级学习
    const SCORE_EMPLOYEE_IMPORTED = 17; // 员工导入
    const SCORE_EMPTY = 18; //学分清空
    const SCORE_EMPLOYEE_APPLICATION = 19; // 员工申请
    const PK_SUCCESS = 20; //pk加分数
    const REPLY_REVIEW_ONCE = 21; //问答评论一次
    /** @deprecated */
    const SCORE_BUY_PRODUCT = 22; //购买商品减学分
    const SCORE_OFFLINE_TEACHING = 23; //学习一节面授课程
    const SCORE_VOTE = 24; //参与一次投票
    const SCORE_SURVEY = 25; //参与一次调研
    const SCORE_ASSESSMENT = 26; //参与一次评估
    const SCORE_TASK = 27; //完成一次作业
    const SCORE_EVALUATION = 28; //完成一次测评
    const SCORE_REQUIRED_COURSE = 29; //必修课程奖励
    const SCORE_ELECTIVE_COURSE = 30; //必修课程奖励
    const SCORE_REQUIRED_KNOWLEDGE = 31; //必修文档奖励
    const SCORE_ELECTIVE_KNOWLEDGE = 32; //选修文档奖励
    const SCORE_IMPORT = 33; //导入学分(将改为面授课学分导入)
    const SCORE_COURSE_MANUAL = 34; // 课程手动加学分
    const SCORE_LEARNING_TRAINING = 35; //外派培训
    const SCORE_CIRCLE_BEST_ANSWER = 36; //最佳答案
    const SCORE_OFFLINE_TEACHING_MANUAL = 37; //面授课手动加学分
    const SCORE_REAL_TIME_PK = 38; //实时pk加学分
    const SCORE_COURSE_IMPORT = 39; //导入课程学分
    const SCORE_EXAM_IMPORT = 40; //导入考试学分
    const SCORE_LEARNING_TRAINING_IMPORT = 41; //导入外派培训学分


    const SCORE_PUBLISH_POST = 42; //问答圈发布一个帖子
    const SCORE_PUBLISH_ISSUES = 43; //问答圈发布一个问题
    const SCORE_ESSENCE_POST = 44; //帖子被评为精华贴
    const SCORE_LIVE_BROADCAST_MANUAL = 45; //参与一场直播\自定义学分、积分
    const SCORE_OFFLINE_ACHIEVEMENT = 46; //线下成绩达标一次
    const SCORE_LEARNING_PLAN_EXTRA = 47; //学习计划奖励额外学分

    const SCORE_COLLEAGUE_CIRCLE_LIKE = 48; //同事圈被点赞
    const SCORE_COLLEAGUE_CIRCLE_COMMENT = 49; //同事圈被评论


    const MEMBERSHIP_POINT_INIT = 50; // 初始化积分（从学分导入）
    const MEMBERSHIP_POINT_CONSUME = 51; // 消费积分
    const MEMBERSHIP_POINT_CLEAR = 52; // 清空积分
    const MEMBERSHIP_POINT_IMPORT = 53; //积分导入

    const SCORE_MANUAL_ADJUSTMENT = 54; //手动调整
    const MEMBERSHIP_COURSE_INCOME = 55; //讲师在线课收入

    const EVERYDAY_RANDOM_SIGN_IN = 56; //随机积分

    const UPLOAD_COURSE = 57; //上传课程

    const SPEAK_EXCHANGE_RATIO = 58; //讲分对应积分比例规则

    const LEARNING_TEACHING_END = 59; //完成一次带教（带教手动结束时给带教导师加积分）
    const CONTINUOUS_SIGN = 60; //连续签到
    const MEMBERSHIP_POINT_COURSE_MANUAL = 61; //课程手动加积分
    const MEMBERSHIP_POINT_SEND_BACK = 62; //退回积分
    const SCORE_LEARNING_PACK_EXTRA = 63; //学习包奖励额外积分
    const SCORE_APPLY_PASS = 64; //学员申请学分

    const SCORE_COURSE_LEVEL = 65; //微课根据课程等级加学分
    const SCORE_OFFLINE_TEACHING_LEVEL = 66; //面授课根据课程等级加学分


    const SCORE_VIDEO_ASSESSMENT = 67; //通过一次视频考核
    const SCORE_TASK_PLAN = 68; // 提交一个任务计划

    const SCORE_CLASS_POINT_EXCHANGE = 69; //班级积分对应积分比例规则

    const SCORE_POINT_CONTINUOUS_SIGN = 70; //连续签到周期性额外奖励

    const SCORE_LIVE_BROADCAST = 71; //参与一场直播

    const SCORE_PACK_POINT_EXCHANGE = 72; //班级积分对应积分比例规则

    const SCORE_WEI_DOU_UPLOAD = 73; //上传微抖视频
    const SCORE_WEI_DOU_APPROVED = 74; //微抖视频审核通过
    const SCORE_WEI_DOU_PRAISE = 75; //微抖视频被点赞

    const POINT_CONSUME_RECHARGE = 76; // 充值到商城
    const POINT_EXCHANGE_COURSE = 77; // 兑换课程

    const SCORE_OFFLINES = [
        self::SCORE_OFFLINE_TEACHING, // 23
        self::SCORE_LEARNING_TRAINING, // 35
        self::SCORE_OFFLINE_TEACHING_MANUAL, // 37
        self::SCORE_IMPORT, // 33
        self::SCORE_LEARNING_TRAINING_IMPORT // 41
    ];

    const PRODUCT_TYPE_PHYSICAL = 1; // 实体商品
    const PRODUCT_TYPE_VIRTUAL = 2; // 虚拟商品
    const PRODUCT_TYPE_COUESE = 3; // 购买课程

    const APPROVAL_STATUS_BACK = 0; //审批流回退
    const APPROVAL_STATUS_UNDO = 1; //待审批
    const APPROVAL_STATUS_DOING = 2; //审批中
    const APPROVAL_STATUS_PASS = 3; //通过
    const APPROVAL_STATUS_REJECT = 4; //不同意

    /** learning codes */
    const LEARNING_CLASS = 0;       //智能班级
    const LEARNING_PACK = 1;        //学习包
    const LEARNING_BREAKTHROUGH = 2; //闯关
    const LEARNING_PLAN = 3;        //学习任务
    const LEARNING_TRAINING = 4;    //外派培训
    const LEARNING_TEACHING = 5;    //带教
    const LEARNING_MAP = 6;         //知识学习地图
    const LEARNING_MAP_BUSINESS = 7; //业务学习地图
    const LEARNING_APPRAISAL = 8;   //鉴定
    const LEARNING_AUTONOMY = 9;    //自主学习计划
    const LEARNING_INSPECTION = 10;  //巡店
    const RETRAINING = 11;           //认证与再培训
    const LEARNING_POSITION = 12;    //岗位学习地图
    const LEARNING_MARKETING = 13;    //营销一体化
    const LEARNING_TEACHING_TEACHER = 14;    //带教导师
    const LEARNING_TEACHING_LEARNER = 15;    //带教员工
    const LEARNING_TEACHING_CC = 16;    //带教抄送人
    const LEARNING_POSITION_AUTHENTICATION = 17;    //岗位认证学习计划
    const LEARNING_REPEAT = 18;    //重复学习
    const SYMBOL_STATEMENT = 19;    //勋章报表
    const LEARNING_COURSE_PACK = 20; //betterU
    const LEARNING_TEACHING_PROJECT_END = 21; //带教结束
    const LECTURER_AUTH_PROJECT = 22; //讲师认证项目
    const LEARNING_AUTONOMY_PLAN = 23;    //默认自主学习计划
    const ENROLL_SESSIONS = 24;    //独立报名
    const COURSE_PACK_PROJECT = 25;    //成长地图-betterU
    const LEARNING_CHANGE_TEACHER_OLD = 26;    //带教更换导师-通知原导师
    const LEARNING_CHANGE_TEACHER_LEARNER = 27;    //带教更换导师-通知学员
    const LEARNING_CHANGE_TEACHER_CC = 28;    //带教更换导师-通知抄送人
    const LEARNING_CHANGE_TEACHER_NEW = 29;    //带教更换导师-通知新导师
    const TRAINING_PROGRAM = 30;    //培训大纲
    const TRAINING_PLAN_PROGRAM = 31;    //培训计划-培训大纲-通用分组任务使用
    const TASK_TEACH_ITEM = 32;           //师带徒
    const LEARNING_LEARNER_DISABLED = 33;    //带教学员离职-通知导师

    const LEARNING_DEVELOPMENT_ROADMAP = 33; // 新版发展地图

    const LEARNING_VALUE_STREAM_MAP = 34; // 价值流地图
    const LEARNING_SPECIAL_TOPIC_PACK = 35; //专题型学习包

    /** learning task codes */
    const TASK_KNOWLEDGE = 1;         //知识
    const TASK_COURSE = 2;            //课程
    const TASK_EXAM = 3;              //考试
    const TASK_FACE_ACTIVITY = 4;     //面授活动
    const TASK_TEST = 5;              //调研
    const TASK_VOTE = 6;              //投票
    const TASK_WORK = 7;              //作业
    const TASK_BREAKTHROUGH = 8;      //闯关
    const TASK_OFFLINE_TEACHING = 9;  //面授课
    const TASK_ASSESS = 10;           //评估
    const TASK_QUESTION = 11;         //提问
    const TASK_OFFLINE_EXERCISE = 12; //线下练习
    const TASK_OFFLINE_ASSESSMENT = 13; //线下考核
    const TASK_GAME = 14;              //游戏
    const TASK_AUTONOMY_ONLINE = 15;   //自主在线任务
    const TASK_AUTONOMY_OFFLINE = 16;  //自主线下任务
    const TASK_TEXT_FILE = 17;         //文本文件任务
    const TASK_EXPERIENCE = 18;         //心得体会
    const TASK_FINAL_EXAM = 19;         //结业考试
    const TASK_FINAL_ASSESS = 20;       //班级评估
    const TASK_APPOINT = 21;            //指定任务
    const TASK_LIVE = 22;               //直播
    const TASK_LEAD_TRAINING = 23;      //带训
    const TASK_OFFLINE_ACHIEVEMENT = 24; //线下成绩
    const TASK_TEACHING = 25;           //带教
    const TASK_APPRAISAL = 26;          //鉴定
    const TASK_CASE = 27;               //案例
    const TASK_QA_CIRCLE = 28;          //问答圈
    const TASK_ACHIEVEMENT_PK = 29;     //业绩pk
    const TASK_COLLECT_COMPETITION = 30; //集赞大赛
    const TASK_ANSWER_COMPETITION = 31; //答题大赛
    //const TASK_AI_TRAINING = 32;        //32-33美团在线考试
    const TASK_AI_TRAINING = 34;  //智能排练
    const TASK_OFFLINE_TRAINING = 35;  //线下训练
    const TASK_EVALUTION_ONE_TO_ONE = 36;  //一对一评价
    const TASK_PHOTOGRAPH = 37;  //拍照
    const TASK_POLY_LIVE = 38;  //保利威直播
    const TASK_GROUP_WORK = 39;  //小组作业
    const TASK_TENCENT_MEETING = 40;  //腾讯会议
    const TASK_TEACHING_PROJECT = 41;   //带教任务
    const TASK_TRAINING_TUTOR = 42;      //新的带训

    const TASK_PK_POINT = 43;      //观点PK任务

    const TASK_TEXT = 44; // 文本

    const TASK_AI_TRAINING_V2 = 45;     //智能陪练2.0
    const TASK_OFFLINE_SESSION = 46; // 面授课多场次

    const TASK_LINKS = 47; // 链接

    /** course live_style codes */
    const COURSE_VOICE = 1;          //语音互动-直播
    const COURSE_SLIDE = 2;          //幻灯片+语音互动-直播
    const COURSE_AUDIO_TEXT = 3;     //音频+图文/learning/log/list/learning/log/list
    const COURSE_IMAGE_TEXT = 4;     //图文
    const COURSE_VIDEO_TEXT = 5;     //视频+图文
    const COURSE_VIDEO = 6;          //视频直播
    const COURSE_WORDS = 7;          //文字
    const COURSE_H5 = 8;             //h5
    const COURSE_DOCUMENT = 9;       //文档
    const COURSE_LINK = 10;          //链接
    const COURSE_SCORM = 11;         //scorm
    const COURSE_ZIP = 12;          //压缩

    const APP_CONFIG_HOMEPAGE = 9999; //首页模块全局模板 id
    const APP_CONFIG_DEFAULT = 10000; //首页模块全局模板 id

    /** Queues */
    const QUEUE_NOTIFICATION = 'queue_notification';
    const QUEUE_ADD_CLASS_TO_LIVE_TENCENT = 'queue_add_class_to_live_tencent';
    const QUEUE_EXAM_MY_STATUS_UPDATE = 'queue_exam_my_status_update';
    const QUEUE_ASSESSMENT_STATISTICS = 'queue_assessment_statistics';
    const QUEUE_OFFLINE_ASSESSMENT_ADD_EMPLOYEE = 'queue_offline_assessment_add_employee';
    const QUEUE_LEAD_TRAINING_ADD_EMPLOYEE = 'queue_lead_training_add_employee';
    const QUEUE_MEMBERSHIP_POINT_EMPTY = 'queue_membership_point_empty';
    const QUEUE_EXAM_QUESTION_STATISTICS = 'queue_exam_question_statistics';
    const QUEUE_WEWORK = 'queue_wework';
    const QUEUE_QUNAR = 'queue_qunar_sync';
    const QUEUE_MODEL_EVENTS = 'queue_model_events';
    const QUEUE_SMS = 'queue_sms';
    const QUEUE_EXAM_PUBLISH = 'queue_exam_publish';
    const QUEUE_EXAM_PUBLISH_V4 = 'queue_exam_publish_v4';
    const QUEUE_EXAM_PUBLISH_V3 = 'queue_exam_publish_v3';
    const QUEUE_EXAM_EMPLOYEE = 'queue_exam_employee';
    const QUEUE_EXAM_ENROLL_EMPLOYEE = 'queue_exam_enroll_employee';
    const QUEUE_EXAM_EMPLOYEE_V2 = 'queue_exam_employee_v2';
    const QUEUE_EXAM_EMPLOYEE_DYNAMIC = 'queue_exam_employee_dynamic';
    const QUEUE_EXAM_EMPLOYEE_EXPORT = 'queue_exam_employee_export';
    const QUEUE_EXAM_EMPLOYEE_PDF_EXPORT = 'queue_exam_employee_pdf_export';
    const QUEUE_EXAM_PASSRATE = 'queue_exam_passrate';
    const QUEUE_EXAM_MARKING = 'queue_exam_marking';
    const QUEUE_PRACITICE_ADD = 'queue_pracitice_add';
    const QUEUE_PRACITICE_EMPLOYEE_ADD = 'queue_pracitice_employee_add';
    const QUEUE_EXPORT = 'queue_export';
    const QUEUE_SURVEY_STATISTICS = 'queue_survey_statistics';
    const QUEUE_SURVEY_UPDATE_RESULT = 'queue_survey_update_result';
    const QUEUE_SCORE_ADD = 'queue_score_add';
    const QUEUE_MASTER_EXPORT = 'queue_master_export';
    const QUEUE_COMPETITION_GENE_DATA = 'queue_competition_gene_data';
    const QUEUE_INFORMATION_DELETE_CACHE = 'queue_information_delete_cache';
    const QUEUE_ENTERPRISE_APPRAISAL_GENE = 'queue_enterprise_appraisal_gene';
    const QUEUE_ENTERPRISE_APPRAISAL_SUBMIT = 'queue_enterprise_appraisal_submit';
    const QUEUE_EXAM_STATISTIC_FOR_CC = 'queue_exam_statistic_for_cc';
    const QUEUE_CREATE_EXAM_STATISTIC_FOR_CC = 'queue_create_exam_statistic_for_cc';


    /** Base Common Object types */
    const BASE_OBJECT_TYPE_DEFAULT = 'default';
    const BASE_OBJECT_TYPE_WIKI = 'wiki';
    const BASE_OBJECT_TYPE_MICRO_TOK = 'micro_tok';
    const BASE_OBJECT_TYPE_MOTTO = 'motto';

    /** Permission  module name */
    const PERMISSION_TEST_PAPER = 'test_paper';
    const PERMISSION_QUESTION = 'question';
    const PERMISSION_EXAM = 'exam';
    const PERMISSION_PRACTICE = 'practice';
    const PERMISSION_BREAKTHROUGH = 'breakthrough';
    const PERMISSION_LEARNING_PLAN = 'learning_plan';
    const PERMISSION_LEARNING_PACK = 'learning_pack';
    const PERMISSION_DIRECORY = 'direcory';
    const PERMISSION_COURSE_DIRECTORY = 'course_directory';
    const PERMISSION_CASE_DIRECTORY = 'case_directory';
    const PERMISSION_COURSE_AUDIT = 'course_audit';
    const PERMISSION_ROLE = 'role';
    const PERMISSION_TEACHING_TEMPLATE = 'teaching_template';
    const PERMISSION_IDENTIFY_TEMPLATE = 'identify_template';
    const PERMISSION_LEARNING_INSPECTION = 'learning_inspection';
    const PERMISSION_AI_TRAINING = 'ai_training';
    const PERMISSION_COURSE_ENTERPRISE = 'course_enterprise';
    const PERMISSION_ASSESSMENT_TEMPLATE = 'assessment_template';
    const PERMISSION_RANKING_LIST = 'ranking_list';
    const PERMISSION_CLASS = 'class';
    const PERMISSION_APPRAISAL_QUESTIONNAIRE = 'appraisal_questionnaire';
    const PERMISSION_APPRAISAL = 'appraisal';
    const PERMISSION_TEACHING_PROJECT = 'teaching_project';
    const PERMISSION_TASK_PLAN_MANAGEMENT = 'task_plan_management';
    const PERMISSION_KNOWLEDGE_BASE = 'knowledge_base';

    const QUEUE_POINT_ADD = 'queue_point_add';


    /** Question_type  module name */
    const QUESTION_SINGLE_CHOICE = 1;
    const QUESTION_MULTIPLE_CHOICE = 2;
    const QUESTION_JUDGMENT = 3;
    const QUESTION_FILL = 4;
    const QUESTION_ANSWER = 5;

    /** paper_type  module name */
    const PAPER_TYPE_FIXED = 1;
    const PAPER_TYPE_RANDOM = 2;
    const PAPER_TYPE_IMPORT = 3;
    const PAPER_TYPE_RANDOM_THOUSANDS = 4;

    const QUESTION_TYPE_RADIO = 1;
    const QUESTION_TYPE_CHECKBOX = 2;
    const QUESTION_TYPE_JUDGE = 3;
    const QUESTION_TYPE_FILL = 4;
    const QUESTION_TYPE_ESSAY = 5;
    const QUESTION_TYPE_ORDER = 6;
    const QUESTION_TYPE_INDEFINITE_TERM = 7;
    const QUESTION_TYPE_PROGRAMMING = 8;


    const SERVICES_EXAM_NOTICE_DELAY = 'services_exam_notice_delay';
    const SERVICES_LEARNING_NOTICE_DELAY = 'services_learning_notice_delay';
    const SERVICES_LEARNING_BONUS_POINTS_DELAY = 'services_learning_bonus_points_delay';
    const SERVICES_RETRAINING_LEARNER_END_DELAY = 'services_retraining_learner_end_delay';
    const SERVICES_EXAM_UNIFORM_SUBMIT = 'services_exam_uniform_submit';
    const SERVICES_SURVEY_NOTICE_DELAY = 'services_survey_notice_delay';
    const SERVICES_COMPETITION_NOTICE_DELAY = 'services_competition_notice_delay';
    const SERVICES_EMPLOYEE_SOLICITUDE_DELAY = 'services_employee_solicitude_delay';
    const SERVICES_TRAINING_LOG_DELAY = 'services_training_log_delay';
    const SERVICES_NOTIFY_CENTER_DELAY = 'services_notify_center_delay';
    const SERVICES_GAEM_LOTTERY_DRAW = 'services_game_lottery_draw';
    const SERVICES_APPRAISAL_NOTIFICATION_DELAY = 'services_appraisal_notification_delay';
    const SERVICES_MESSAGE_SERVICE_DELAY = 'services_message_service_delay';
    const SERVICES_COMPETITON_ROUND_EMPLOYEE_RETRAINING_DELAY = 'services_competition_round_employee_delay';
    const SERVICES_MYCHERY_REPORT_DELAY = 'services_mychery_report_delay';

    const SERVICES_ACTION_ASSESSMENT_DELAY = 'services_action_assessment_delay';

    const BANNER_LINK = 2; //链接
    const BANNER_KNOWLEDGE = 3; //知识
    const BANNER_COURSE = 4; //文库
    const BANNER_INFORMATION = 6;  //咨询
    const BANNER_SPECIAL_TOPIC = 7; //专题
    const BANNER_EXAM = 8; //考试
    const BANNER_CLASS = 9; //班级
    const BANNER_PLAN = 10;        //学习计划
    const BANNER_BREAKTHROUGH = 11; //闯关
    const BANNER_SURVEY = 12; //调研
    const BANNER_ISSUE_LABEL = 13; //问答圈
    const BANNER_NO_LINK = 14; //不跳转
    const BANNER_COMPETITION_COURSE = 15; //微课大赛
    const BANNER_COMPETITION_ANSWER = 16; //答题大赛
    const BANNER_COMPETITION_PK = 17; //实时pk大赛
    const BANNER_COMPETITION_LIKE = 18; //集赞大赛
    const BANNER_LEARNING_PACKAGE = 19; //学习包
    const BANNER_LIVE = 20; //直播
    const BANNER_COLUMN = 21; //专栏


    const EXPORT_DOWNLOAD_LIST = [
        'queue_exam_employee_take_export' => '学员考试统计',
        'stat_employee_score' => '学分统计',
        'export_stat_employee_time' => '学时统计',
        'queue_export_course_learning' => '课程学习统计',
        'queue_export_statistics_activity' => '员工活跃度',
        'queue_export_statistics_activity_detail' => '员工活跃度明细',
        'export_stat_login_detail' => '员工登录明细统计',
        'queue_export_score_detail' => '学分明细导出',
        'queue_homework_download_file' => '作业附件导出',
        'queue_export_sta_statistics_learner_project' => '项目统计-个人维度报表导出',
        'queue_export_sta_statistics_exam_learner_project' => '项目考试统计-个人维度报表导出',
        'queue_export_sta_statistics_dept_learner' => '项目统计-部门汇总维度统计导出',
        'queue_export_sta_statistics_position_learner' => '项目统计-部门汇总维度统计导出',
        'queue_export_learning_statistics_employee' => '项目统计-个人汇总维度统计导出',
        'queue_export_statistics_category_project' => '项目统计-员工培训项目统计导出',
    ];


    //编号前缀
    const PREFIX_QUESTION_BANK = 'TK'; //题库
    const PREFIX_QUESTION = 'ST'; //试题
    const PREFIX_PAPER = 'SJ'; //试卷
    const PREFIX_EXAM = 'KS'; //考试

    //app首页模块配置相关
    const MODULE_DEFAULT_SHOW_NUM = 2; //首页模块默认展示条数
    //1.自定义 2.微课中心 3.企业资讯 4.产品库 5.案例库(暂时没有上线) 6.同事圈 7.讲师中心 8.微问 9.学分排行榜  10.荣誉墙 11.最新课程 12.每日一课 13.快报 14.专题 15.AI智能课
    //可以设置展示条数的模块
    const MODULES_SET_SHOW_NUM = [
        1,
        2,
        3,
        11,
        7,
        13,
        4,
        10,
        15,
        16,
        17,
        20,
        30,
        40,
        21,
        66,
        68
    ];

    // Object types
    const OBJ_CLASS = 1; // 智能班级
    const OBJ_LEARNING_PLAN = 2; // 学习计划
    const OBJ_LEARNING_PACKAGE = 3; // 学习包
    const OBJ_LEARNING_PROJECT = 4; // 多班次项目
    const OBJ_RETRAINING = 5; // 认证与再培训
    const OBJ_LEARNING_COURSE_PACKAGE = 6; // betteru


    /** contribution */
    const QUEUE_CONTRIBUTION_SCORE_ADD = 'queue_contribution_score_add';

    const CONTRIBUTION_SCORE_UPLOAD_COURSE = 1; //上传一门课程且通过审核 （课时的单位是小时）
    const CONTRIBUTION_SCORE_UPLOAD_CASE = 2; //上传个案例且审核通过
    const CONTRIBUTION_SCORE_RELEASE_JOB = 3; //完成一次作业发布
    const CONTRIBUTION_SCORE_PACK_SET = 4; //发起一次学习包布置
    const CONTRIBUTION_SCORE_CLASS_SET = 5; //发起一次智能班级布置
    const CONTRIBUTION_SCORE_REVIEW_JOB = 6; //完成一次作业批阅
    const CONTRIBUTION_SCORE_EVALUATE_SUBORDINATE = 7; //完成线下考核对下属的评价
    const CONTRIBUTION_SCORE_TRAINING_PROGRAM = 8; //提交一次培训计划且通过审核
    const CONTRIBUTION_SCORE_TEACHING = 9; //每发起一次带教
    const CONTRIBUTION_SCORE_OFFLINE_TEACHING = 10; //完成一门面授课的授课
    const CONTRIBUTION_SCORE_EVALUATE_EMPLOYEE = 11; //录入一次对员工的评价
    const CONTRIBUTION_SCORE_COMMENT_POST = 12; //问答圈评论一次帖子
    const CONTRIBUTION_SCORE_RELEASE_POST = 13; //问答圈发布一次帖子

    /**
     * related point system
     */
    const QUEUE_RELATED_POINT_ADD = 'queue_related_point_add';
    const QUEUE_EXPORT_RELATED_POINT_STATISTICS = 'queue_export_related_point_statistics';
    const QUEUE_RELATED_POINT_IMPORT = 'queue_related_point_import';
    const QUEUE_RELATED_POINT_CONFIRM_IMPORT = 'queue_related_point_confirm_import';

    /** Selector range */
    const RANGE_TYPE_COMPANY = 1; // company
    const RANGE_TYPE_DEPARTMENT = 2; // department
    const RANGE_TYPE_GROUP = 3; // group
    const RANGE_TYPE_EMPLOYEE = 4; // employee
    const RANGE_TYPE_POSITION = 5; // position

    const RANGE_TYPE_JOBLEVEL = 6; // job level

    const QUEUE_AI_ASSISTANT = 'queue_ai_assistant';
}
