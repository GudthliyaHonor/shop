<?php

/**
 * Mongodb Collections.
 */

namespace App\Common;


use App\Models\Employee;

interface Coll
{
    //-------------------------------------------------
    // Mongodb Collections
    //-------------------------------------------------
    /**
     * Sequence structure, for example:
     * <pre>
     * {
     *   name: 'myseq',
     *   seq: 1000
     * }
     * </pre>
     * @see # key-documents repo for detail.
     */
    const COLL_SEQUENCE = 'sequence';
    /**
     * Account structure, for example:
     * <pre>
     * {
     *   id: 1000, // from sequence
     *   name: 'xxxx', // organization name
     *   status: 1, // enabled/disabled
     *   ...// other properties
     * }
     * </pre>
     */
    const COLL_ACCOUNT = 'account';

    // 公司安全规则设置
    const COLL_ACCOUNT_SECURITY_RULE = 'account_security_rule';

    const COLL_ACCOUNT_STARTUP_SCREEN = 'account_startup_screen';

    /**
     * Account plugins configure, such as 'teaching_center', 'appraisal_center', etc.
     * <pre>
     * {
     *  id: 1,
     *  aid: 1,
     *  modules: [{
     *    'module': 'micro_tok_management',
     *    'alias': 'micro_tok'
     *  }]
     * }
     * </pre>
     */
    const COLL_ACCOUNT_PLUGIN = 'account_plugin';

    /**
     * Account config collection stores the configure of import, export, etc.
     */
    const ACCOUNT_CONFIG = 'account_config';

    /**
     * User collection structure.
     * For example:
     * <pre>
     * {
     *   id: 1000, // from sequence
     *   username: 'xxxx', // login user name
     *   password: '', // login password
     *   status: 1, // enabled/disabled
     *   ...// other properties
     * }
     * </pre>=
     */
    const COLL_USER = 'user';
    const COLL_QA_USER = 'qa_user';
    /**
     * QR code login collection, it stores the qr code for scanning to login.
     * <pre>
     * {
     *   id: 'K57a9e9c052e289.03203623.L57a9e9c052e418.41291967', // unique id
     *   uid: 0, // if scan successfully, it set the user id
     *   status: 0, // if scan successfully, it set 1
     *   created: 1470753216, // timestamp, integer
     * }
     * </pre>
     */

    /** Store the sessions of the user */
    const COLL_USER_SESSION = 'user_session';
    /** Store login status logs */
    const USER_SESSION_EXTRA = 'user_session_extra';
    /** APP QR login */
    const COLL_USER_UNIQUE = 'login_unique';
    /** User Gesture Password collection */
    const COLL_USER_GESTURE = 'user_gesture';
    /** User password change log collection */
    const COLL_USER_PWD_LOG = 'user_pwd_log';
    /** 员工简要登录日志，只记录是否登录和员工状态，可更新员工状态用于统计 */
    const USER_LOGIN_ACTIVITY = 'user_login_activity';
    /** 员工登录覆盖率统计 */
    const USER_STAT_LOGIN_COVERAGE = 'stat_login_coverage';

    const COLL_COUPON = 'coupon';
    const COLL_COUPON_DETAIL = 'coupon_detail';
    const COLL_ORDER = 'orders';
    const COLL_ORDER_ITEM = 'order_item';
    const COLL_ORDER_ACCREDIT = 'order_accredit';
    const COLL_ORDER_NUMBER = 'order_number';
    const COLL_ORDER_DETAIL = 'order_detail';
    const COLL_SHOP_CART = 'shop_cart';
    const COLL_CHARGEBACK = 'chargeback';
    const COLL_SETTLE_ACCOUNT = 'settle_account';
    const COLL_WITH_POOL = 'withdrawal_pool';
    const COLL_WITHDRAWAL = 'withdrawal';
    const COLL_DISCOUNT = 'discount';
    const COLL_GENERALIZE = 'generalize';
    const COLL_DISTRIBUTION = 'distribution';
    const COLL_RELAY = 'relay';
    const COLL_DISTRIBUTION_DETAIL = 'distribution_detail';
    const COLL_COURSE_STATISTICS = 'course_statistics';
    const COLL_CLASSROOM = 'classroom';
    const COLL_ACTIVITY = 'activity';
    const COLL_ACTIVITY_USER = 'activity_user';
    const COLL_ACTIVITY_COMMENT = 'activity_comment';
    const COLL_IMAGE = 'image';
    const COLL_CHANNEL = 'channel';
    const COLL_FOLLOW = 'follow';
    const COLL_SHARE = 'share';
    const COLL_CERTIFICATION_TEMPLATE = 'certification_template';
    const COLL_CERTIFICATION_TEMPLATE_IMAGE = 'certification_template_image';
    const COLL_CERTIFICATION = 'certification';
    const COLL_CERTIFICATION_CLASSFIY = 'certification_classfiy';
    const COLL_CERTIFICATION_USER = 'certification_user';
    const COLL_CERTIFICATION_SHARE_LOG = 'certification_share_log'; // 证书分享日志
    const COLL_CERTIFICATION_USER_NO = 'certification_user_no';  //员工证书编号
    const COLL_CERTIFICATION_USER_LOG = 'certification_user_log';  //证书颁发记录
    const COLL_CERTIFICATION_AUDIT = 'certification_audit';
    const COLL_CERT_IMAGE_IMPORT_LOG = 'cert_image_import_log'; //证书图片导入记录
    const COLL_CERTIFICATION_STATISTICS = 'certification_statistics'; // 证书训练统计
    const COLL_HONOR = 'honor';
    const COLL_HONOR_USER = 'honor_user';
    const COLL_TOPIC = 'topic';
    /**
     * Course in classroom.
     */
    const COLL_COURSE = 'course';
    const COLL_COURSE_REVIEW = 'course_review';
    const COLL_COURSE_CATEGORY = 'course_category';
    const COLL_SKILLED_CATEGORY = 'skilled_category';
    const COLL_COURSE_ASSESS = 'assess';
    const COLL_COURSE_APPLICANT = 'applicant';
    const COLL_COURSE_CONSULTATION = 'consultation';
    const COLL_COURSE_CROWDED = 'course_CROWDed';
    const COLL_COURSE_RELAY = 'course_relay';
    const COLL_COURSE_CHANNEL = 'course_channel';
    const COLL_SERIES_GROUP = 'series_group';
    const COLL_GROUP_COURSE = 'group_course';
    const COLL_MY_COURSE = 'my_course';
    const COLL_VIDEO_LIVE = 'video_live';
    const COLL_VIDEO_SUBTITLE_TASK_LOG = 'video_subtitle_task_log';
    const COLL_COURSE_DOT = 'course_dot';
    const COLL_VIDEO_PPT = 'video_ppt';
    const COLL_VIDEO_PPT_SSML = 'video_ppt_ssml';
    const COLL_VIDEO_PPT_LOG = 'video_ppt_log';

    const COLL_FILE_TRANSFORM_IMAGES = 'file_transform_images';
    const COLL_FILE_TRANSFORM_IMAGES_DETAIL = 'file_transform_images_detail';
    const COLL_FILE_TRANSFORM_IMAGES_LOG = 'file_transform_images_log';
    const COLL_FILE_TRANSFORM_VIDEO = 'file_transform_video';
    const COLL_FILE_TRANSFORM_VIDEO_LOG = 'file_transform_video_log';


    // 360 评估 start
    const COLL_APPRAISAL_ELEMENT = 'appraisal_element'; // 要素
    const COLL_APPRAISAL_BEHAVIOR_LEVEL = 'appraisal_behavior_level'; // 能力等级
    const COLL_APPRAISAL_ELEMENT_BEHAVIOR_LEVEL = 'appraisal_element_behavior_level'; // 行为等级
    const COLL_APPRAISAL_ELEMENT_QUESTION = 'appraisal_element_question'; // 要素题
    const COLL_APPRAISAL_ELEMENT_COURSE = 'appraisal_element_course'; // 关联课程
    const COLL_APPRAISAL_ABILITY_MODEL = 'appraisal_ability_model'; // 能力模型
    const COLL_APPRAISAL_ABILITY_DIMENSION = 'appraisal_ability_dimension'; // 能力模型
    const COLL_APPRAISAL_QUESTIONNAIRE = 'appraisal_questionnaire'; // 问卷
    const COLL_APPRAISAL = 'appraisal'; // 评估
    const COLL_APPRAISAL_RELATION = 'appraisal_relation'; // 评估关系
    const COLL_APPRAISAL_NOTIFICATION = 'appraisal_notification'; // 评估通知总记录
    const COLL_APPRAISAL_NOTIFICATION_DETAIL = 'appraisal_notification_detail'; // 评估通知详细记录
    const COLL_APPRAISAL_ANSWER = 'appraisal_answer'; // 评估答案
    const COLL_ELEMENT_AUTH_RULE = 'element_auth_rule'; // 能力认证规则

    const COLL_ELEMENT_AUTH_EMPLOYEE_RECORD = 'element_auth_employee_record'; // 员工能力完成记录表
    const COLL_APPRAISAL_REPORT_DEPARTMENT = 'appraisal_report_department';
    const COLL_APPRAISAL_REPORT_EMPLOYEE = 'appraisal_report_employee';
    const COLL_APPRAISAL_EMPLOYEE_ABILITY = 'appraisal_employee_ability'; // 员工能力
    const COLL_APPRAISAL_VIDEO_VERIFICATION = 'appraisal_video_verification'; // 视频验证记录
    const COLL_APPRAISAL_GROUP_REPORT = 'appraisal_group_report'; // 群组报告
    const COLL_APPRAISAL_GROUP_REPORT_EMPLOYEE = 'appraisal_group_report_employee'; // 员工群组报告
    const COLL_APPRAISAL_REPORT_SETTING = 'appraisal_report_setting'; // 报告设置
    const COLL_APPRAISAL_ADDITIONAL_QUESTION = 'appraisal_additional_question'; // 问卷主观题

    //技能矩阵
    const COLL_MATRIX_SELECT_RECORD = 'matrix_select_record'; // 能力矩阵查询记录
    const COLL_MATRIX_APPRAISAL_EMPLOYEE_STATISTICS = 'matrix_appraisal_employee_statistics'; // 能力矩阵员工信息
    const COLL_MATRIX_APPRAISAL_DEPARTMENT_STATISTICS = 'matrix_appraisal_department_statistics'; // 能力矩阵部门记录
    const COLL_MATRIX_APPRAISAL_PANEL_STATISTICS = 'matrix_appraisal_panel_statistics'; //仪表盘记录

    const COLL_POSITION_CAPABILITY_MATRIX = 'position_capability_matrix'; //岗位能力矩阵
    const COLL_POSITION_CAPABILITY_MATRIX_STATISTICS = 'position_capability_matrix_statistics'; //岗位能力矩阵




    //  技能分类
    const COLL_ELEMENT_CATEGORY = 'element_category'; // 技能分类
    const COLL_APPRAISAL_ABILITY_CATEGORY = 'appraisal_ability_category'; // 能力模型分类
    // 360 评估 end


    const COLL_WATERMARK_CONFIG = 'watermark_config';


    /**
     * Complaint in liveroom
     */
    const COLL_COMPLAINT = 'complaint';

    /**
     * For liveroom in course.
     */
    const COLL_LIVEROOM = 'liveroom';
    const COLL_LIVEROOM_LOG = 'liveroom_log';
    const COLL_LIVEROOM_GIFT = 'liveroom_gift';

    const COLL_EHUATAI_LOGIN_LOG = 'ehuatai_login_log';

    /**
     * Lectures in liveroom.
     */
    const COLL_LECTURE = 'lecture';
    /**
     * Lecture read record.
     */
    const COLL_LECTURE_READ = 'lecture_read';
    /**
     * Comment in liveroom.
     */
    const COLL_COMMENT = 'comment';

    const COLL_MATERIAL = 'material';
    const COLL_STUDENT_DOWN = 'student_down_load';

    const COLL_CATEGORY = 'category';
    const COLL_RECOMMENDATION = 'recommendation';

    /**
     * 发票管理
     */
    const COLL_INVOICE = 'invoice';

    /**
     * 讲师管理
     */
    const COLL_COMPANY_LECTURER = 'company_lecturer';
    const COLL_LECTURER_YEAR_CHECK = 'lecturer_year_check';
    const COLL_LECTURER_ORDER_AUDITER = 'lecturer_order_auditer';   //讲师预约审核人
    const COLL_LECTURER_ORDER = 'lecturer_order';   //讲师预约
    const COLL_LECTURER_ORDER_TIME_RANGE = 'lecturer_order_time_range';   //讲师预约时间范围  改变需求以后不用了
    const COLL_LECTURER_ORDER_ENABLED_DAY = 'lecturer_order_enabled_day';   //讲师可预约的时间
    const COLL_LECTURER_ORDER_RANGE = 'lecturer_order_range';   //全局讲师可预约范围
    const COLL_LECTURER_ORDER_NOTIFY_DETAIL = 'lecturer_order_notify_detail';   //讲师预约发送的 信息
    const COLL_LECTURER_MAX_NEMBER = 'lecturer_max_number';
    const COLL_OFFLINE_TEACHING_LECTURER = 'lecturer_teaching_lecturer';
    const COLL_LECTURER_SCORE = 'lecturer_score';
    const COLL_LECTURER_STYLE = 'lecturer_style';
    const COLL_LECTURER_HONOR = 'lecturer_honor';
    const COLL_LECTURER_SCORE_TO_POINT = 'lecturer_to_point';
    const COLL_LECTURER_GRADE_DETAIL = 'lecturer_grade_detail'; //讲师定级详情记录
    const COLL_LECTURER_YEAR_NO_CHANGED = 'lecturer_year_no_changed'; //讲师讲分一年未变动数据
    const COLL_LECTURER_YEAR_NO_CHANGED_SETTING = 'lecturer_year_no_changed_setting'; //记录每次生成-讲师讲分一年未变动数据-的时间
    const COLL_LECTURER_AUTHENTICATED_FINISHED = 'lecturer_authenticated_finished'; //完成一次等级认证
    const COLL_LECTURER_SCORE_DETAIL = 'lecturer_score_detail'; //讲分明细
    const COLL_LECTURER_AUTH_PROJECT = 'lecturer_auth_project'; //讲师认证项目表
    const COLL_LECTURER_AUTH_PROJECT_LEARNER = 'lecturer_auth_project_learner'; //讲师认证项目成员表
    const COLL_LECTURER_AUTH_PROJECT_ENROLL = 'lecturer_auth_project_enroll'; //讲师认证项目报名表
    const COLL_LECTURER_AUTH_PROJECT_EVALUATION = 'lecturer_auth_project_evaluation'; //讲师认证项目评价设置
    const COLL_COURSE_PACK_PROJECT = 'course_pack_project'; // 成长地图
    const COLL_TRAINING_PROGRAM = 'training_program'; // 培训大纲
    const COLL_LECTURER_SCORE_ADJUSTMENT = 'lecturer_score_adjustment'; // 讲分调整计划
    const COLL_LECTURER_SCORE_ADJUSTMENT_DETAIL = 'lecturer_score_adjustment_detail'; // 讲分调整计划详情


    /**
     * 敏感词
     */
    const COLL_COMPANY_HARMONY = 'harmony';
    /**
     * For learning.
     */
    const COLL_LEARNING_DETAIL_LOG = 'learning_detail_log';
    const COLL_LEARNING_PLAN_LOG = 'learning_plan_log';
    const COLL_LEARNING_LOG = 'learning_log';
    const COLL_LEARNING_LOG_ANNUAL = 'learning_log_annual';
    const COLL_LEARNING_PLAN_LOG_ANNUAL = 'learning_plan_log_annual';
    const COLL_LEARNING_PLAN = 'learning_plan';
    const COLL_LEARNING_PROJECT = 'learning_project';
    const COLL_LEARNING_PROJECT_DELEGATE = 'learning_project_delegate'; //多班次报名设置表
    const COLL_LEARNING_INTERACTION = 'learning_interaction';
    const COLL_INTERACTION_BACKGROUND = 'interaction_background';
    const COLL_LEARNING_INTERACTION_DETAIL = 'learning_interaction_detail';
    const COLL_LEARNING_PACK = 'learning_pack';
    const COLL_LEARNING_TOPIC_PACK_SUBSCRIBE = 'learning_topic_pack_subscribe'; // 专题学习包订阅详情
//    const COLL_LEARNING_TOPIC_PACK_PV = 'learning_topic_pack_pv'; // 专题学习包浏览量
    const COLL_LEARNING_TOPIC_PACK_PV_DETAIL = 'learning_topic_pack_pv_detail'; // 专题学习包浏览量详情
    const COLL_LEARNING_COURSE_PACK_SCORE = 'learning_course_pack_score';
    const COLL_LEARNING_GROUP = 'learning_group';
    const COLL_LEARNING_SIMPLE_GROUP = 'learning_simple_group';
    const COLL_LEARNING_TASK = 'learning_task';
    const COLL_LEARNING_TEACHING = 'learning_teaching';
    const COLL_LEARNER = 'learner';
    const COLL_LEARNER_TEACHING = 'learner_teaching';
    const COLL_LEARNER_TEACHING_SETTINGS = 'learner_teaching_settings';
    const COLL_LEARNER_TEACHING_NOTIFY = 'learner_teaching_notify';
    const COLL_LOG_TEACHER_CHANGE = 'log_teacher_change';
    const COLL_LEARNER_TASK_DETAIL = 'learner_task_detail';
    const COLL_BREAKTHROUGH = 'breakthrough';
    const COLL_BREAKTHROUGH_CATEGORY = 'breakthrough_category';
    const COLL_PLAN_NOTIFICATION = 'plan_notification';
    const COLL_NOTIFY_DETAIL = 'plan_notified_detail';
    const COLL_LEARNER_PK_GROUP = 'learner_pk_group';
    const COLL_LEARNER_TASK_REMARK_LOG = 'learner_task_remark_log';
    const COLL_LEARNING_PERIOD_LOG = 'learning_period_log';
    const COLL_LEARNING_HISTORY_APPLICATION = 'learning_history_application';
    const COLL_LECTURER_SETTINGS = 'lecturer_settings'; //讲师-参数配置-级别设置和擅长类别设置
    const COLL_LECTURER_REMUNERATION = 'lecturer_remuneration'; //级别课酬 每个级别设置课酬
    const COLL_LECTURER_RATING_STANDARD = 'lecturer_rating_standard'; //讲师评级指标设置
    const COLL_LECTURER_REMU_TAB_SET = 'lecturer_remu_tab_set'; //级别课酬 1讲分/小时 2元/小时
    const COLL_LECTURER_SCORE_TAB_SET = 'lecturer_score_tab_set'; //讲分配置-配置模式
    const COLL_LECTURER_SCORE_DISPOSE = 'lecturer_score_dispose'; //讲分配置
    const COLL_CLASS_SIGN = 'class_sign';
    const COLL_CLASS_SIGN_DETAIL = 'class_sign_detail';
    const COLL_CLASS_SIGN_FORM_SETTING = 'class_sign_form_setting'; //班级签到表单配置
    const COLL_CLASS_SIGN_FORM_DETAIL = 'class_sign_form_detail'; //班级签到表单详情
    const COLL_LEARNING_ENROLL = 'learning_enroll';
    const COLL_LEARNING_CANCEL_ENROLL = 'learning_cancel_enroll';
    const COLL_LEARNING_TEACHING_STATISTIC = 'learning_teaching_statistic';     //带教,鉴定统计
    const COLL_AUTONOMY_TASK = 'autonomy_task';
    const COLL_AUTONOMY_TASK_DETAIL = 'autonomy_task_detail';
    const COLL_TASK_TEXT_FILE = 'task_text_file';
    const COLL_TEXT_FILE_DETAIL = 'text_file_detail'; //文本文档任务做答详情
    const COLL_LEARNING_REPORT = 'learning_report'; //培训报告
    const COLL_TASK_APPOINT_DETAIL = 'task_appoint_detail';
    const COLL_TOURIST = 'tourist';
    const COLL_NOTIFICATION_PERIOD = 'notification_period';
    const COLL_NOTIFICATION_TIMING = 'notification_timing';
    const COLL_STATISTICS_DEPT_PROJ = 'statistics_dept_proj';
    const COLL_TRAIN_STANDING_BOOK = 'train_standing_book';
    const COLL_LEARNING_CATEGORY = 'learning_category';
    const COLL_SCORM_LEARNING_LOG = 'scorm_learning_log';
    const COLL_SCORM_LEARNING_PLAN_LOG = 'scorm_learning_plan_log';
    const COLL_SCORM_LEARNING_DETAILS = 'scorm_learning_details';

    const COLL_STALKER = 'stalker';
    const COLL_LEARNING_BILL = 'learning_bill';
    const COLL_LEARNING_ENROLL_BILL = 'learning_enroll_bill';

    const COLL_STA_SELF_LEARNING_PLAN = 'sta_self_learning_plan';

    //学习项目统计（学习计划，学习包，智能班级）
    const COLL_STA_STATISTICS_PROJECT = 'sta_statistics_project'; //报表-项目统计
    const COLL_STA_STATISTICS_LEARNER_PROJECT = 'sta_statistics_learner_project'; //报表-项目学习统计明细
    const COLL_STA_STATISTICS_EXAM_LEARNER_PROJECT = 'sta_statistics_exam_learner_project'; //报表-学员项目考试统计
    const COLL_STA_STATISTICS_PROJECT_GATHER = 'sta_statistics_project_gather'; //报表-项目统计-项目汇总维度统计
    const COLL_STA_STATISTICS_LEARNING_DEPT = 'sta_statistics_learning_dept'; //报表-项目维度-部门统计
    const COLL_STA_STATISTICS_LEARNING_POSITION = 'sta_statistics_learning_position'; //报表-项目维度-岗位统计
    const COLL_STA_STATISTICS_EXAM_PROJECT = 'sta_statistics_exam_project'; //报表-项目考试统计-考试汇总维度统计

    //认证与再培训
    const COLL_RETRAINING_CATEGORY = 'retraining_category';
    const COLL_RETRAINING_LEVEL = 'retraining_level';
    const COLL_RETRAINING = 'retraining';
    const COLL_RETRAINING_STAGE = 'retraining_stage';
    const COLL_RETRAINING_TASK = 'retraining_task';
    const COLL_RETRAINING_LEARNER = 'retraining_learner';
    const COLL_STA_RETRAINING_LEARNER = 'sta_retraining_learner';
    const COLL_STA_RETRAINING_POSITION = 'sta_retraining_position';
    const COLL_STA_RETRAINING_DEPT = 'sta_retraining_dept';
    const COLL_RETRAINING_LEARNER_TASK = 'retraining_learner_task';
    const COLL_STATISTICS_SCREEN = 'statistics_screen';

    //巡检员
    const COLL_INSPECTOR = 'inspector';
    const COLL_INSPECTOR_RANGE = 'inspector_range';
    const COLL_INSPECTION_TARGET = 'inspection_target'; //巡检对象
    const COLL_INSPECTION_TARGET_DETAIL = 'inspection_target_detail'; //巡检对象任务做答详情

    //面授课统计
    const  COLL_STATISTICS_OFFLINE_TEACHING = 'statistics_offline_teaching';
    const  COLL_STA_OFFLINE_TEACHING_LEARNER = 'sta_offline_teaching_learner';

    //产品
    const COLL_PRODUCT = 'product';
    const COLL_PRODUCT_COURSE = 'product_course';
    const COLL_PRODUCT_PROJECT = 'product_project';
    const COLL_PRODUCT_CATEGORY = 'product_category';
    const COLL_PRODUCT_BRAND = 'product_brand';
    const COLL_PRODUCT_ATTRIBUTE = 'product_attribute';
    const COLL_PRODUCT_LABEL = 'product_label';
    const COLL_PRODUCT_IMAGE_IMPORT_LOG = 'product_image_import_log'; //产品图片导入记录
    const COLL_PRODUCT_IMAGE_SET = 'product_image_set'; //产品图片展示设置

    //营销一体化
    const COLL_MARKETING_UNION = 'marketing_union';
    const COLL_MARKETING_STAGE = 'marketing_stage';
    const COLL_MARKETING_TASK = 'marketing_task';
    const COLL_MARKETING_LEARNER = 'marketing_learner';
    const COLL_MARKETING_LEARNER_TASK = 'marketing_learner_task';

    //通知配置
    const COLL_NOTIFICATION_SETTING = 'notification_setting';

    const COLL_NOTIFICATION_LOG = 'notification_log';


    // 学时统计
    const COLL_LEARN_PER_STAT = 'learning_period_statistics';

    /**
     * score setting
     */
    const COLL_SCORE_TYPE = "score_type";
    const COLL_SCORE_APPLY = "score_apply";
    const COLL_SCORE_SETTING = 'score_setting';
    const COLL_SCORE_LOG = 'score_log';
    const COLL_SCORE_PERIOD_LOG = 'score_period_log';
    const COLL_SCORE_DEPT_STA = 'score_department_statistics';
    const COLL_MEDAL = 'medal';
    const COLL_EMPLOYEE_MEDAL = 'employee_medal';
    const COLL_EMPLOYEE_STAT = 'employee_statistics';
    const COLL_EMPLOYEE_LEARNING_LOG = 'employee_learning_log';
    const COLL_EMPLOYEE_DAILY_SCORE = 'employee_daily_score';
    const COLL_EMPLOYEE_DAILY_TIME = 'employee_daily_time';
    const COLL_EMPLOYEE_LOG = 'employee_log'; // Store employee changes

    const COLL_EMPLOYEE_MEMBERSHIP_POINT_LOG = 'membership_point_log'; // 积分membership_point_history_log(之后用的表)
    const COLL_MEMBERSHIP_POINT_SETTING = 'membership_point_setting'; // 积分设置
    const COLL_MEMBERSHIP_POINT_EXCHANGE = 'membership_point_exchange'; // 积分充值到商城记录
    const COLL_MEMBERSHIP_POINT_PERIOD_LOG = 'membership_point_period_log';


    // 员工自注册配置
    const EMPLOYEE_SELF_REGISTER_CONFIG = 'employee_self_register_config';
    // 员工自注册列表
    const EMPLOYEE_SELF_REGISTER_LIST = 'employee_self_register_list';
    // 自定义字段
    const COLL_CUSTOM_FIELDS = 'coll_custom_fields';

    /*
     * app banner setting
     */
    const COLL_BANNER = 'banner';

    const COLL_SHOP_BANNER = 'shop_banner';
    // 首页配置
    const COLL_HOMEPAGE_SETTING = 'homepage_setting';
    const COLL_HOMEPAGE_SETTING_PC = 'homepage_setting_pc';
    const COLL_HOMEPAGE_SETTING_PC_MODULE = 'homepage_setting_pc_module'; //自定义模块
    const HOMEPAGE_SETTING_PC_CONTENT = 'homepage_setting_pc_content'; //自定义模块

    //新版v4 app首页配置
    const COLL_HOMEPAGE_MODULE = 'college_homepage_module';

    const COLL_MULTI_GRAGH = 'college_multi_graph';

    // 20241120 改版logo
    const COLL_COLLEGE_LOGO = 'college_logo';


    const MT_QUICK_ENTRY = 'mt_quick_entry'; //自定义模块

    /**
     * consultation
     */
    const COLL_INFORMATION = 'information';

    /**
     * special topic
     */
    const COLL_SPECIAL_TOPIC = 'special_topic';

    /**
     * For exam
     */
    const COLL_QUESTION_BANK = 'question_bank';
    const QUESTION_BANK_AI_FILE = 'question_bank_ai_file';
    const QUESTION_BANK_AI_QUESTION = 'question_bank_ai_question';

    const COLL_PRACTICE_DIR = 'practice_dir';
    const COLL_QUESTION = 'question';
    const COLL_PAPER = 'paper';
    const COLL_EXAM = 'exam';
    const COLL_EXAM_RESULT_LOG = 'exam_result_log';
    const COLL_EXAM_DIR = 'exam_dir';
    const COLL_EXAM_MOCK_DIR = 'exam_mock_dir';
    const COLL_MY_EXAM = 'my_exam';
    const COLL_MY_EXAM_COPY = 'my_exam_copy';
    const COLL_EXAM_STATISTICS = 'exam_statistics';
    const COLL_EXAM_NOTIFICATION = 'exam_notification';
    const COLL_EXAM_CONTINUE_NOTIFICATION = 'exam_continue_notification';
    const COLL_EXAM_NOTIFIED_DETAIL = 'exam_notified_detail';
    const COLL_EXAM_QUESTION_IMPORT = 'exam_question_import';
    const COLL_EXAM_QUESTION_IMPORT_DETAIL = 'exam_question_import_detail';
    const COLL_EXAM_KNOWLEDGE_POINT = 'exam_knowledge_point';
    const COLL_EXAM_MINI_BANK = 'exam_mini_bank';
    const COLL_EXAM_SET = 'exam_set';
    const COLL_EXAM_ERROR_LOG = 'exam_error_log';
    const COLL_EXAM_ERROR_LOG_V2 = 'exam_error_log_v2';
    const COLL_EXAM_POSTER = 'exam_poster';
    const COLL_PASSRATE = 'passrate';
    const COLL_EXAM_PASSRATE = 'exam_passrate';
    const COLL_EXAM_QUESTIONS_STATISTICS = 'exam_questions_statistics';
    const COLL_EXAM_APPROVE_SKU = 'exam_approve_sku';
    const COLL_EXAM_APPROVE_QUESTION_SKU = 'exam_approve_question_sku';

    const COLL_MY_EXAM_ENROLL = 'my_exam_enroll';

    const EXAM_APPROVE_SETTING = 'exam_approve_setting';

    // employee certificate log for exam
    const EXAM_CERT_LOG = 'exam_cert_log';

    const COLL_ANSWER = 'answer';
    const COLL_PRACTICE = 'practice';
    const COLL_MISTAKE_NOTE = 'mistake_note';
    const COLL_PRACTICE_NUMBER = 'practice_number';
    const COLL_PK = 'pk';
    const COLL_PK_DETAIL = 'pk_detail';
    const COLL_PRACTICE_EMPLOYEE_STA = 'practice_employee_sta';
    const COLL_PRACTICE_BANK = 'practice_bank';
    /**
     * For vote
     */
    const COLL_VOTE = 'vote';
    const COLL_VOTE_RESULT = 'vote_result';

    /**
     * For assessment
     */
    const COLL_ASSESSMENT = 'assessment';
    const COLL_ASSESSMENT_RESULT = 'assessment_result';
    const COLL_ASSESSMENT_STATISTICS = 'assessment_statistics';
    const COLL_ASSESSMENT_STAT_DETAIL = 'assessment_stat_detail';
    const COLL_ASSESSMENT_STAT_ANSWER = 'assessment_stat_answer';
    const COLL_ASSESSMENT_TEMPLATE = 'assessment_template';
    const COLL_ASSESSMENT_TEMPLATE_STATISTICS = 'assessment_template_statistics';


    /**
     * For survey
     */
    const COLL_SURVEY = 'survey';
    const COLL_SURVEY_RESULT = 'survey_result';
    const COLL_SURVEY_STATISTICS = 'survey_statistics';
    const COLL_SURVEY_INTERSECT_STATISTICS = 'survey_intersect_statistics';
    const COLL_SURVEY_INTERSECT_DETAIL = 'survey_intersect_detail';
    const COLL_SURVEY_TEMPLATE = 'survey_template';
    const COLL_SURVEY_STATISTICS_DETAIL = 'survey_statistics_detail';
    const COLL_SURVEY_NOTIFICATION = 'survey_notification';
    const COLL_SURVEY_NOTIFY_DETAIL = 'survey_notified_detail';
    const COLL_SURVEY_RESULT_BACKUP = 'survey_result_backup';
    const COLL_SURVEY_QUESTIONS_DATA = 'survey_questions_data';

    const COLL_ACCOUNT_APPLICATION = 'account_application';
    const COLL_DEPARTMENT = 'department';
    const COLL_POSITION = 'position';
    const COLL_POSITION_CATEGORY = 'position_category';
    const COLL_POSITION_COURSE = 'position_course';
    const COLL_POSITION_GRADING_EXAM = 'position_grading_exam';
    const COLL_POSITION_GRADING_EXAM_QUESTION = 'position_grading_exam_question';
    const COLL_EMPLOYEE = 'employee';
    const COLL_EMPLOYEE_GROUP = 'employee_group'; // 人群
    const COLL_EMPLOYEE_GROUP_MEMBERS = 'employee_group_members'; // 人群人员
    const COLL_EMPLOYEE_GROUP_DYNAMIC_RULE = 'employee_group_dynamic_rule'; // 动态人群规则

    const COLL_AREA = 'area';

    const COLL_BANK_ACCOUNT = 'bank_account';

    const COLL_QA_CONTRACT = 'qa_contract';
    /**
     * For Directory and Knowledge
     */

    const COLL_DIRECTORY = 'directory';
    const COLL_KNOWLEDGE = 'knowledge';
    const COLL_KNOWLEDGE_POOL = 'knowledge_pool';
    const COLL_ANSWER_SET = 'intelligence_answer_set';
    const COLL_INTELLIGENCE_CUSTOMER_SERVICE = 'intelligence_customer_service';
    const COLL_INTELLIGENCE_CHAT = 'intelligence_chat';
    const COLL_INTELLIGENCE_QUESTION = 'intelligence_question';
    const COLL_INTELLIGENCE_CUSTOMER_ANSWER = 'intelligence_customer_answer';
    const COLL_CONTENT_ROLE = 'content_role';
    const COLL_COURSE_REPOSITORY = 'course_repository';
    const COLL_COURSE_REPOSITORY_STA = 'course_repository_statistics';
    const COLL_KNOWLEDGE_STA = 'knowledge_statistics';
    const COLL_DOWNLOAD = 'download';
    const COLL_KNOWLEDGE_POINT = 'knowledge_point';
    const COLL_KNOWLEDGE_BASE = 'knowledge_base';

    //知识申请审核
    const COLL_KNOWLEDGE_APPLY = 'knowledge_apply';

    //企业版微课收藏
    const COLL_ORG_FAVORITE = 'org_favorite';
    //企业版微课评论
    const COLL_ORG_COMMENT = 'org_comment';
    const Coll_ORG_COMMENT_LIKES = 'comment_likes';
    // 消息提醒
    const COLL_NOTIFICATION = 'notification';
    const COLL_AUTO_NOTIFICATION = 'auto_notification';
    const COLL_AUTO_NOTIFICATION_DETAIL = 'auto_notification_detail';
    const COLL_SYSTEM_NOTIFICATION = 'notification_system';
    // 每日签到
    const COLL_SIGN_DAILY = 'sign_daily';
    const COLL_SIGN_DAILY_DETAIL = 'sign_daily_detail';
    const STAT_SIGN_DAILY = 'stat_sign_daily';

    //问答圈
    const COLL_EMPLOYEE_ISSUE = 'issue';        //问答提问  //article_type 1 问题 2 帖子
    const COLL_EMPLOYEE_ISSUE_TOP = 'issue_top';
    const COLL_EMPLOYEE_REPLY = 'reply';        //问答回答  问题的回复
    const COLL_EMPLOYEE_REPLY_BANNED = 'reply_banned';        //禁言
    const COLL_ISSUE_INVITE = 'issue_invite';       //问答邀请别人回答问题表
    const COLL_ISSUE_LIKE = 'issue_like';       //点赞
    const COLL_ISSUE_REPLY_COMMENT = 'issue_reply_comment';     //对回复或者帖子的评论   帖子 obj_type  0-问答 1-帖子
    const COLL_ISSUE_LABEL = 'issue_label';  //问题标签--圈子
    const COLL_CIRCLE_USER = 'circle_user';
    const COLL_ISSUE_BROWSE = 'issue_browse';   //tiezi 问题浏览记录
    const COLL_EMPLOYEE_ISSUE_DRAFT = 'issue_draft'; // 草稿

    //师傅
    const COLL_MENTORSHIP_MASTER = 'mentorship_master';
    const COLL_MENTORSHIP_SETTING = 'mentorship_setting';

    //徒弟
    const COLL_MENTORSHIP_APPRENTICE = 'mentorship_apprentice';

    //用户配置
    const COLL_ISSUE_USER_CONFIG = 'issue_user_config';

    // Versions for app
    const COLL_APP_VERSION = 'app_version';

    //意见反馈
    const COLL_APP_FEEDBACK = 'app_feedback';

    //微问团队
    const COLL_TEAM = 'team';

    //测评问卷
    const COLL_EVALUATION_QUESTIONNAIRE = 'evaluation_questionnaire';
    const COLL_EVALUATION = 'evaluation';
    const COLL_EVALUATION_RESULT = 'evaluation_result';

    //作业
    const COLL_TASK = 'task';
    const COLL_TASK_RESULT = 'task_result';
    const COLL_TASK_RESULT_REVIEW_HISTORY = 'task_result_review_history';
    const COLL_TASK_TRANSCODING = 'task_transcoding';
    const COLL_TASK_PROCESS_REVIEW = 'task_process_review';

    //培训计划
    const COLL_TRAINING_PLAN = 'training_plan';
    const COLL_TRAINING_TASK_GROUP = 'training_task_group';
    const COLL_TRAINING_TASK = 'training_task';
    const COLL_TRAINING_ATTRIBUTE = 'training_attribute';
    const COLL_TRAINING_ATTRIBUTE_OPTIONS = 'training_attribute_options';
    const COLL_TRAINING_PLAN_COURSE = 'training_plan_course';

    // 年度计划
    const COLL_TRAINING_YEAR_PLAN = 'training_year_plan';
    const COLL_TRAINING_YEAR_PLAN_ACTIVE = 'training_year_plan_active';

    //智能班级提问
    const COLL_CLASS_QUESTION = 'class_question';
    const COLL_LEARNING_CLASS_POINT_SETTING = 'learning_class_point_setting';   //智能班级积分规则表
    const COLL_LEARNING_CLASS_POINT_LOG = 'learning_class_point_log';   //智能班级积分加减日志

    // Home stream
    const COLL_HOME_STREAM = 'home_stream';

    //headline
    const COLL_HEADLINE = 'headline';
    const COLL_HEADLINE_KEYWORD = 'headline_keyword';
    const COLL_HEADLINE_ACCOUNT = 'headline_account';
    const COLL_HEADLINE_MODE_SET = 'headline_mode_set';
    const COLL_HEADLINE_CATEGORY_EMPLOYEE = 'headline_category_employee';

    //Permission
    const COLL_ROLE = 'role';
    const COLL_ROLE_CLASSIFY = 'role_classify';
    const COLL_FUNCTION_GROUP = 'function_group';
    const COLL_ROLE_EMPLOYEE_LOG = 'role_employee_log';

    // grant auth
    const AUTH_GRANT_CONFIG = 'auth_grant_config';
    const AUTH_GRANT = 'auth_grant';

    const COLL_STUDENT_MENU = 'student_menu';

    // Chat collection
    const COLL_CHAT = 'chat';
    const COLL_CHAT_READ = 'chat_read';

    // Data import contract
    const COLL_DATA_IMPORT = 'data_import';
    const COLL_DATA_IMPORT_DETAIL = 'data_import_detail';

    const COLL_ADJUSTMENT_SCORE_LOG = 'score_adjustment_log';
    const COLL_ADJUSTMENT_RECORD = 'adjustment_record';

    //Integral
    const COLL_INTEGRAL_PRODUCT = 'score_product';
    const COLL_INTEGRAL_ORDER = 'score_order';
    const COLL_INTEGRAL_ORDER_DETAIL = 'score_order_detail';
    const COLL_INTEGRAL_ADDRESS = 'score_address';


    // Statistics
    const COLL_EMPLOYEE_ACTIVITY_RATE = 'employee_activity_rate';
    // Weekly employee activity statistics
    const STAT_ACTIVITY_WEEKLY = 'stat_activity_weekly';
    const STAT_ACTIVITY_MONTHLY = 'stat_activity_monthly';

    // 部门月份统计
    const STAT_DEPARTMENT_MONTHLY = 'stat_department_monthly';
    const STAT_DEPARTMENT_DAILY = 'stat_department_daily';

    // Note
    const COLL_NOTE = 'note';

    //测验(包括文库和课程)
    const COLL_CONTENT_ASSIGNMENT = 'content_assignment';
    const COLL_CONTENT_ASSIGNMENT_RECORD = 'content_assignment_record';

    //面授
    const COLL_OFFLINE_TEACHING = 'offline_teaching';
    const COLL_OFFLINE_TEACHING_RECORD = 'offline_teaching_record';     //面授课记录
    const COLL_OFFLINE_TEACHING_RECORD_AUDIT = 'offline_teaching_record_audit';     //面授课记录
    const COLL_OFFLINE_TEACHING_SETTING = 'offline_teaching_setting';     //面授课相关设置
    const COLL_OFFLINE_TEACHING_ATTEND = 'offline_teaching_attend';     //面授课考勤设置表
    const COLL_OFFLINE_TEACHING_SESSION = 'offline_teaching_session';     //面授课场次表
    const COLL_CLASS_SESSION_SIGN_DETAIL = 'class_session_sign_detail'; //面授课场次签到表

    // Common log
    const COLL_COMMON_LOG = 'common_log';

    // Qunar sync log
    const COLL_QUNAR_LOG = 'qunar_sync_log';

    //线下练习
    const COLL_OFFLINE_EXERCISE = 'offline_exercise';
    const COLL_OFFLINE_EXERCISE_RESULT = 'offline_exercise_result';

    //线下训练
    const COLL_OFFLINE_TRAINING = 'offline_training';
    const COLL_OFFLINE_TRAINING_EMPLOYEE = 'offline_training_employee';
    const COLL_OFFLINE_TRAINING_EMPLOYEE_DURATION = 'offline_training_employee_duration';

    // 一对一评价
    const COLL_ONE_TO_ONE_EVALUATION = 'one_to_one_evaluation';
    const COLL_ONE_TO_ONE_EVALUATION_DIMENSION = 'one_to_one_evaluation_dimension';
    const COLL_ONE_TO_ONE_EVALUATION_QUESTION = 'one_to_one_evaluation_question';
    const COLL_ONE_TO_ONE_EVALUATION_ANSWER = 'one_to_one_evaluation_answer';
    const COLL_ONE_TO_ONE_EVALUATION_RELATION = 'one_to_one_evaluation_relation';
    const COLL_ONE_TO_ONE_EVALUATION_LEARNER = 'one_to_one_evaluation_learner';

    //我的背包
    const COLL_MYBAG = 'mybag';

    //线下考核
    const COLL_OFFLINE_ASSESSMENT = 'offline_assessment';
    const COLL_OFFLINE_ASSESSMENT_RESULT = 'offline_assessment_result';
    const COLL_OFFLINE_ASSESSMENT_COPY = 'offline_assessment_result_copy';
    const COLL_OFFLINE_ASSESSMENT_AUDIT_LOG = 'offline_assessment_audit_log';
    const COLL_OFFLINE_ASSESSMENT_OPTION = 'offline_assessment_option';

    //带训
    const COLL_LEAD_TRAINING = 'lead_training';
    const COLL_LEAD_TRAINING_RESULT = 'lead_training_result';

    // 带教项目
    const COLL_TEACHING_PROJECT = 'teaching_project';

    // Base object collection
    const COLL_BASE_OBJECT = 'base_object';
    const COLL_BASE_COMMENT = 'base_object_comment';
    const COLL_BASE_THUMB = 'base_object_thumb';
    const BASE_OBJECT_CATEGORY = 'base_object_category';

    //课程/文档/或其他对象评价
    const COLL_ESTIMATE = 'estimate';
    //第三方平台
    const COLL_THIRD_PLATFORM = 'third_platform';
    const COLL_THIRD_PLATFORM_RECORD = 'third_platform_record';

    const COLL_XN_COURSE = 'xn_course';

    // Data export via Queue
    const COLL_QUEUE_EXPORT = 'queue_export';
    const COLL_QUEUE = 'queue';

    // Game
    const COLL_GAME = 'game';
    const COLL_GAME_PRIZE = 'game_prize';
    const COLL_GAME_PEOPLE = 'game_people'; // 参加游戏的人员
    const COLL_GAME_WON = 'game_won'; // 获奖列表

    //sync
    const COLL_OPEN_SYNC_DEPARTMENT = 'open_sync_department'; //同步的部门
    const COLL_OPEN_SYNC_EMPLOYEE = 'open_sync_employee'; //同步的员工
    const COLL_OPEN_SYNC_POSITION = 'open_sync_position'; //同步的岗位

    //标签管理
    const COLL_LABEL = 'label'; //标签
    const COLL_LABEL_DIR = 'label_dir'; //标签目录  不用了
    const COLL_LABEL_USER = 'label_user'; //用户--标签
    const COLL_LABEL_USER_CURR = 'label_user_curr'; //用户-课程

    //角色岗位地图
    const COLL_KNOWLEDGE_PLATE = 'role_knowledge_plate'; //知识板块
    const COLL_TRAIN_ROLE = 'role_train_role'; //培训角色
    const COLL_TRAIN_STAGE = 'role_train_stage'; //培训阶段
    const COLL_ROLE_MAP_EMP = 'role_map_emp'; //人员表
    const COLL_ROLE_MAP_CURR = 'role_map_curr'; //课程表

    const COLL_LEARNING_DIREATORY = 'learning_directory'; //学习目录

    /** 众筹 */
    const COLL_FUNDING_BOOKING = 'funding_booking'; //拼团信息
    const COLL_FUNDING_MEMBER = 'funding_member'; //拼团成员
    const COLL_FUNDING_PROPOSER = 'funding_proposer'; //拼团管理-我想讲成员
    const COLL_FUNDING_COMMENT = 'funding_comment'; //评论
    const COLL_FUNDING_OFFER = 'funding_offer'; //请缨
    const COLL_FUNDING_REMINDER = 'funding_reminder'; //进入我想听列表是否提示
    const COLL_FUNDING_APPLY = 'funding_apply'; //我想讲报名人员表
    const COLL_FUNDING_SETTING = 'funding_setting'; //众筹设置

    /** 金币 */
    const COLL_COIN_ADJUST = 'coin_adjust'; //金币调整记录
    const COLL_COIN_ADJUST_DETAIL = 'coin_adjust_detail'; //金币调整详情
    const COLL_COIN_BILL = 'coin_bill'; //金币账单
    const COLL_COIN_TOTAL = 'coin_total'; //金币总计
    const COLL_COIN_LOG = 'coin_log'; //金币log

    const COLL_TIP = 'tip';

    // App Discovery configure @deprecated @see APP_DISCOVERY
    const COLL_APP_DISCOVER = 'app_discover';

    // App Home Config
    const COLL_APP_HOME_CONFIG = 'app_home_config';
    const COLL_APP_HOME_CONFIG_INDEX = 'app_home_config_index';

    // App Home Config
    const COLL_APP_MAIN = 'app_main';

    // App Config
    const COLL_APP_CONFIG = 'app_config';
    const COLL_APP_CONFIG_MEMBER = 'app_config_member';

    // Supplier Collection
    const COLL_SUPPLIER = 'supplier';
    const COLL_SUPPLIER_CONTACT = 'supplier_contact';
    const COLL_SUPPLIER_CATEGORY = 'supplier_category';

    // Live Collection
    const COLL_LIVE = 'live';
    const COLL_LIVE_CHAT = 'live_chat';
    const COLL_LIVE_MEMBER = 'live_member';
    const COLL_LIVE_LECTURER = 'live_lecturer';

    // Live Collection
    const COLL_LIVE_TENCENT = 'live_tencent';
    const COLL_LIVE_TENCENT_MEMBER = 'live_tencent_member';


    // Vhall直播
    const COLL_VHALL_ROOM = 'vhall_room';
    const COLL_VHALL_DOCUMENT = 'vhall_document';
    const COLL_VHALL_VIEWER = 'vhall_viewer';
    const COLL_VHALL_LOG = 'vhall_log';

    // Notice 公告
    const COLL_NOTICE = 'notice';

    // Job level
    const COLL_JOB_LEVEL = 'job_level';

    // 班级公告
    const COLL_CLASS_NOTICE = 'class_notice';
    const COLL_CLASS_NOTICE_SEE_RECORD = 'class_notice_see_record';

    // Cloud Convert @see https://cloudconvert.com
    const COLL_CLOUD_CONVERT_PROCESS = 'cloud_convert_process';

    // 案例库
    const COLL_CASE_STORE = 'case_store';
    const COLL_CASE_ATTACHMENT = 'case_attachment';
    const COLL_CASE_MEMBER = 'case_member';
    const COLL_CASE_DIRECTORY = 'case_directory';
    const COLL_CASE_DIRECTORY_MEMBER = 'case_directory_member';
    const COLL_CASE_STAGE = 'case_stage';
    const COLL_CASE_TEMPLATE = 'case_template';
    const COLL_CASE_TEMPLATE_STAGE = 'case_template_stage';
    const COLL_CASE_TASK = 'case_task';
    const COLL_CASE_STORE_LIKE = 'case_store_like'; // 案例点赞表
    const COLL_CASE_STORE_COLLECT = 'case_store_collect'; // 案例收藏表
    const COLL_CASE_CUSTOMER_POINT = 'case_customer_point'; // 客户行业标签

    //app_tasks
    const COLL_APP_TASKS = 'app_tasks';

    // 人脸识别
    const COLL_FACE_MATCHING = 'face_matching';
    const COLL_FACE_MATCHING_LOG = 'face_matching_log'; // 日志

    //审批流
    const COLL_APPROVAL_CATEGORY = 'approval_category';
    const COLL_APPROVAL_PROCESS = 'approval_process';
    const COLL_APPROVAL_LEVEL = 'approval_level';
    const COLL_APPROVAL_APPROVER = 'approval_approver';

    //勋章管理
    const COLL_SYMBOL = 'symbol';
    const COLL_SYMBOL_LEVEL = 'symbol_level';
    const COLL_SYMBOL_LOG = 'symbol_log';
    const COLL_SYMBOL_LEVEL_LOG = 'symbol_level_log';
    const COLL_SYMBOL_CONDITION = 'symbol_condition';
    const COLL_SYMBOL_RECEIVER = 'symbol_receiver';
    const COLL_SYMBOL_MEMBER = 'symbol_member';

    //竞赛
    const COLL_COMPETITION_JS_NO = 'competition_js_no';
    const COLL_COMPETITION = 'competition';
    const COLL_COMPETITION_ROUND = 'competition_round';
    const COLL_COMPETITION_ROUND_EMPLOYEE = 'competition_round_employee';   //轮次-报名表
    const COLL_COMPETITION_STATISTIC = 'competition_statistic';
    const COLL_COMPETITION_APPLY = 'competition_statistic_apply';       //竞赛报名-报名设置
    const COLL_COMPETITION_APPLY_EMPLOYEE = 'competition_statistic_apply_employee';       //竞赛报名人员
    const COLL_COMPETITION_APPLY_COMMENT = 'competition_apply_comment';       //竞赛报名评论
    const COLL_COMPETITION_APPLY_COMMENT_LIKE = 'competition_apply_comment_like';       //竞赛报名评论点赞
    const COLL_COMPETITION_VOTE = 'competition_statistic_vote';       //竞赛投票
    const COLL_COMPETITION_LOG = 'competition_log';       //竞赛历史
    const COLL_COMPETITION_NOTIFICATION = 'competition_notification';
    const COLL_COMPETITION_NOTIFY_DETAIL = 'competition_notified_detail';

    //业绩大赛
    const COLL_COMPETITION_ACHIEVEMENT = 'competition_achievement'; //业绩大赛
    const COLL_COMPETITION_ACHIEVEMENT_GROUP = 'competition_achievement_group'; //业绩大赛战队
    const COLL_COMPETITION_ACHIEVEMENT_HELP = 'competition_achievement_group_help'; //业绩大赛战队助力
    const COLL_COMPETITION_ACHIEVEMENT_GROUP_EMP = 'competition_achievement_group_emp'; //业绩大赛战队中的人员
    const COLL_COMPETITION_ACHIEVEMENT_GROUP_BATTLE = 'competition_achievement_group_battle'; //业绩大赛pk对组
    const COLL_COMPETITION_ACHIEVEMENT_DETAIL = 'competition_achievement_detail'; //业绩大赛业绩提交详情

    //实时pk
    const COLL_PK_REAL_TIME = 'pk_real_time';
    const COLL_PK_REAL_TIME_RESULT = 'pk_real_time_result';

    const COLL_POSITION_COMPETENCY_CATEGORY = 'position_competency_category'; // 岗位胜任力指标
    const COLL_POSITION_COMPETENCY = 'position_competency';  // 岗位胜任力指标
    const COLL_POSITION_AUTHENTICATION = 'position_authentication';  // 岗位认证
    const COLL_POSITION_AUTH_MAP = 'position_auth_map';  // 岗位认证地图
    const COLL_POSITION_SKILL_CATEGORY = 'position_skill_category';  // 岗位认证技能分组
    const COLL_POSITION_SKILL = 'position_skill';  // 岗位认证技能
    const COLL_ELEMENT_AUTH_DETAIL = 'element_auth_detail';  // 员工获取技能

    //OAuth 授权
    const COLL_OAUTH_CLIENT = 'oauth_client';
    const COLL_OAUTH_CLIENT_LOG = 'oauth_client_log';
    const COLL_OAUTH_INFO = 'oauth_info';

    //同事圈
    const COLL_COLLEAGE_CIRCLE_INFO = 'colleage_circle_info';
    const COLL_COLLEAGE_CIRCLE_REPLY = 'colleage_circle_reply';

    //course ranking
    const COLL_COURSE_RANKING = 'course_ranking';

    // 搜索记录
    const COLL_SEARCH_LOG = 'search_log';
    // 搜索条件
    const SEARCH_CRITERIA = 'search_criteria';

    //学习心得
    const COLL_EXPERIENCE = 'experience';
    const COLL_EXPERIENCE_DETAIL = 'experience_detail';

    //拍照
    const COLL_PHOTOGRAPH = 'photograph';
    const COLL_PHOTOGRAPH_DETAIL = 'photograph_detail';
    const COLL_PHOTOGRAPH_DETAIL_SUPPORT = 'photograph_detail_support';

    //智能陪练
    const COLL_AI_TRAINING = 'ai_training';
    const COLL_AI_TRAINING_ROUND = 'ai_training_round';
    const COLL_AI_TRAINING_QUESTION = 'ai_training_question';
    const COLL_AI_TRAINING_STUDENT = 'ai_training_student';
    const COLL_AI_TRAINING_ROUNG_STUDENTT = 'ai_training_round_student';
    const COLL_AI_TRAINING_ROUNG_STUD_QUESTION = 'ai_training_round_stud_question';
    const COLL_AI_TRAINING_CATEGORY = 'ai_training_category';

    //智能陪练 v2
    const COLL_SPEECHCRAFT_TRAINING = 'speechcraft_training';
    const COLL_SPEECHCRAFT_TRAINING_CATEGORY = 'speechcraft_training_category';
    const COLL_SPEECHCRAFT_TRAINING_ROBOT = 'speechcraft_training_robot';
    const COLL_SPEECHCRAFT_TRAINING_SCENE = 'speechcraft_training_scene';
    const COLL_SPEECHCRAFT_TRAINING_STUDENT_SETTING = 'speechcraft_training_student_setting';
    const COLL_SPEECHCRAFT_TRAINING_STUDENT = 'speechcraft_training_student';
    const COLL_SPEECHCRAFT_TRAINING_SCENE_STUDENTT = 'speechcraft_training_scene_student';
    const COLL_SPEECHCRAFT_TRAINING_SCENE_STUDENT_TALK = 'speechcraft_training_scene_student_talk';

    const COLL_WEIXIN_TO_TEXT = 'weixin_to_text'; //微信语音转换文子


    // 情景模拟
    const AI_SCENARIO = 'ai_scenario';
    const AI_SCENARIO_PROCESS = 'ai_scenario_process'; // 内容
    const AI_SCENARIO_PROCESS_STEP = 'ai_scenario_process_step'; // 详情
    const AI_SCENARIO_PROCESS_SUMMARY = 'ai_scenario_process_summary'; // 统计摘要
    const AI_SCENARIO_PROCESS_DETAIL = 'ai_scenario_process_detail'; // 统计详情

    //展示互动直播
    const COLL_GEN_LIVE = 'gen_live';
    const COLL_GEN_LIVE_MEMBER = 'gen_live_member';
    const COLL_GLOBAL_GEN_LIVE = '_db_gen_live';
    const COLL_GEN_LIVE_V2 = 'gen_live_v2';
    const COLL_GEN_LIVE_MEMBER_V2 = 'gen_live_member_v2';
    const COLL_GLOBAL_GEN_LIVE_V2 = '_db_gen_live_v2';


    const COLL_LIVE_HONG_SHAN_MEMBER = 'live_hong_shan_member';

    //快报
    const COLL_NEWS = 'news';
    const COLL_NEWS_MEMBER = 'news_member';
    const COLL_NEWS_STAGE = 'news_stage';
    const COLL_NEWS_STAGE_CONT = 'news_stage_cont';

    //首页公告
    const COLL_HOMEPAGE_ANNOUNCEMENT = 'homepage_announcement';
    const COLL_HOMEPAGE_ANNOUNCEMENT_NOTIP = 'homepage_announcement_notip';

    //活动
    const COLL_EVENT = 'event';

    //活动成员
    const COLL_EVENT_MEMBER = 'event_member';

    //活动-抽奖
    const COLL_EVENT_LUCK_DRAW = 'event_luck_draw';     //抽奖表
    const COLL_EVENT_LUCK_DRAW_LEVEl = 'event_luck_draw_prize_level';       //抽奖级别表
    const COLL_EVENT_LUCK_DRAW_PRIZE_EID = 'event_luck_draw_prize_eid';     //奖品获取人员表
    const COLL_EVENT_LUCK_DRAW_PRIZE = 'event_luck_draw_prize';     //奖品表
    const COLL_EVENT_LUCK_DRAW_MARK = 'event_luck_draw_mark';     //抽奖时，页面停留在那个奖品那里


    //活动签到
    const COLL_EVENT_SIGN = 'event_sign';

    //微信网站应用授权登录用户
    const COLL_WECHAT_SNS_USER = 'wechat_sns_user';

    // 个人年终报告
    const YEAR_END_REPORT = 'year_end_report';

    //首页展示模块
    const COLL_APP_HOMEPAGE_FUNCTION_MODULE = 'app_homepage_function_module';

    // 员工每天的学习时长，获得学分等
    // @see bin\services\StatisticsEmployee.php
    const STAT_EMPLOYEE_DAILY_LOG = 'stat_emp_daily_log';

    //通知中心
    const COLL_NOTIFY_CENTER = 'notify_center';
    const COLL_NOTIFY_TARGET = 'notify_target';

    //专栏
    const COLL_COLUMN = 'column';

    //我要出题
    const COLL_QUESTION_ASK = 'question_ask';
    const COLL_COLUMN_ARTICLE = 'column_article';
    const COLL_COLUMN_ARTICLE_READ = 'column_article_read';
    const COLL_COLUMN_REPLY = 'column_reply';
    const COLL_COLUMN_SUBSCRIBE = 'column_subscribe';
    const COLL_COLUMN_DEFAULT_COMMENTS = 'column_default_comments';

    //带教分组和带教模板的中间表
    const COLL_LEARNING_TEMPLATE_GROUP = 'learning_template_group'; //  带教/鉴定 模板分组
    const COLL_TEMPLATE_GROUP = 'template_group'; //不用了

    const COLL_EMPLOYEE_SOLICITUDE_RULE = 'employee_solicitude_rule';
    const COLL_EMPLOYEE_SOLICITUDE_CARD = 'employee_solicitude_card';
    const COLL_EMPLOYEE_SOLICITUDE_BLESSING = 'employee_solicitude_blessing';
    const COLL_EMPLOYEE_SOLICITUDE_COMMENT = 'employee_solicitude_comment';
    const COLL_EMPLOYEE_SOLICITUDE = 'employee_solicitude';
    const COLL_EMPLOYEE_SOLICITUDE_NOTIFICATION = 'employee_solicitude_notification';
    const COLL_EMPLOYEE_SOLICITUDE_NOTIFY_DETAIL = 'employee_solicitude_notify_detail';

    const APP_MODULE = 'app_module'; // App模块配置表
    const APP_MODULE_CONTENT = 'app_module_content'; // app模块关联内容表

    const APP_DISCOVERY = 'app_discovery'; // 发现（新）


    //线下成绩
    const COLL_OFFLINE_ACHIEVEMENT = 'offline_achievement';
    const COLL_OFFLINE_ACHIEVEMENT_RESULT = 'offline_achievement_result';

    //培训日志
    const COLL_TRAINING_LOG = 'training_log';
    const COLL_TRAINING_LOG_EMPLOYEE = 'training_log_employee';
    const COLL_TRAINING_LOG_RESULT = 'training_log_result';
    const COLL_TRAINING_LOG_AUDIT_HISTORY = 'training_log_audit_history';
    const COLL_TRAINING_LOG_NOTIFICATION = 'training_log_notification';
    const COLL_TRAINING_LOG_NOTIFY_DETAIL = 'training_log_notified_detail';


    //积分导入
    const COLL_MEMBERSHIP_POINT_ADJUSTMENT_LOG = 'membership_point_adjustment_log';

    //巡检-问题
    const COLL_INSPECTION_QUESTION_CATEGORY = 'inspection_question_category';
    const COLL_INSPECTION_QUESTION = 'inspection_question';
    const COLL_STA_INSPECTION_RESULT = 'sta_inspection_result';
    const COLL_INSPECTION_QUESTION_NOTIFY = 'inspection_question_notify'; //企业微信 通知表

    //合同
    const COLL_CONTRACT_CATEGORY = 'contract_category';
    const COLL_CONTRACT = 'contract';

    const COLL_RANKING_LIST = 'ranking_list';

    const COLL_REPORT_DOWNLOAD_EXPORT = 'report_download_export';

    //培训风采
    const COLL_TRAIN_STYLE = 'train_style';
    const COLL_TRAIN_STYLE_HOMEPAGE = 'train_style_homepage';  //train_id  priority
    const COLL_APP_DOWNLOAD_EXPLAIN_IMG = 'app_download_explain_img';


    // 操作日志
    const OPERATION_LOG = 'operation_log';

    //专题
    const COLL_SPECIAL_SUBJECT = 'special_subject';
    const COLL_SPECIAL_SUBJECT_GROUP = 'special_subject_group';
    const COLL_SPECIAL_SUBJECT_CONTENT = 'special_subject_content';
    const COLL_SPECIAL_SUBJECT_DETAIL = 'special_subject_detail';
    const COLL_SPECIAL_SUBJECT_BROWSE = 'special_subject_browse';

    const COLL_DAILY_CLOCK = 'daily_colck';
    const COLL_DAILY_CLOCK_EMPLOYEE = 'daily_colck_employee';
    const COLL_DAILY_CLOCK_DETAil = 'daily_colck_detail';
    const COLL_DAILY_CLOCK_TASKS_EVERYDAY = 'daily_colck_tasks_everyday';

    const COLL_MAIL_MINUTELY = 'mail_minutely';

    //字典
    const COLL_DICTIONARY = 'dictionary';

    // 企业测评相关
    const COLL_ENTERPRISE_MANAGE = 'enterprise_manage';
    const COLL_ENTERPRISE_ELEMENT_MANAGE = 'enterprise_element_manage';
    const COLL_ENTERPRISE_ELEMENT_QUESTION = 'enterprise_element_question';
    const COLL_ENTERPRISE_ABILITY_MODEL = 'enterprise_ability_model';
    const COLL_ENTERPRISE_DIMENSION_EL_RELATION = 'enterprise_dimension_el_relation';
    const COLL_ENTERPRISE_QUESTIONNAIRE = 'enterprise_questionnaire';
    const COLL_ENTERPRISE_APPRAISAL = 'enterprise_appraisal';
    const COLL_ENTERPRISE_APPRAISAL_STATISTIC = 'enterprise_appraisal_statistic';
    const COLL_ENTERPRISE_APPRAISAL_DETAIL = 'enterprise_appraisal_detail';
    const COLL_ENTERPRISE_QUESTIONNAIRE_QUESTIONS = 'enterprise_questionnaire_questions';
    const COLL_ENTERPRISE_APPRAISAL_REPORT = 'enterprise_appraisal_report';
    const COLL_ENTERPRISE_DIFFERENT_APPRAISAL_REPORT = 'enterprise_different_appraisal_report';
    const COLL_ENTERPRISE_COMPLEX_APPRAISAL_REPORT = 'enterprise_complex_appraisal_report';
    const COLL_ENTERPRISE_GENERATE_REPORT = 'enterprise_generate_report';

    // 微信公众号关联表
    const COLL_EMPLOYEE_WECHAT = 'employee_wechat';
    const COLL_WECHAT_SEND_RECORD = 'wechat_public_send_record';


    // 员工学习数据导入统计表
    const COLL_LEARNING_STATISTICS_EMPLOYEE_DATA = 'learning_statistics_employee_data';
    // 课程学习记录数据导入统计表
    const COLL_LEARNING_STATISTICS_COURSE_RECORD_DATA = 'learning_statistics_course_record_data';
    // 考试记录数据导入统计表
    const COLL_LEARNING_STATISTICS_EXAM_RECORD_DATA = 'learning_statistics_exam_record_data';

    // OSS/S3配置，用于签名
    const OSS_S3_SIGN_CONF = 'oss_s3_sign_conf';

    // 版本通知表
    const COLL_VERSION_NOTICE = 'version_notice';

    // 版本通知标记已读未读列表
    const COLL_VERSION_NOTICE_MARK_LIST = 'version_notice_mark_list';
    // 培训中心
    const TRAINING_CENTER = 'training_center';
    // 培训中心关联对象
    const TRAINING_CENTER_RELATIONSHIP = 'training_center_relationship';
    // 短链接
    const SHORT_LINK = 'short_link';


    //培训协议
    const TRAIN_AGREEMENT = 'train_greement';


    // 回收站
    const RECYCLE_BIN = 'recycle_bin';
    // 音视频课程弹题
    const LEARNING_PRACTICE_POINT = 'learning_practice_point';
    const LEARNING_COURSE_FACE_MATCHING = 'LEARNING_course_face_matching';

    //错题本
    const WRONG_BOOK = 'wrong_book';

    // 数据字典
    const SYSTEM_DICTIONARY = 'system_dictionary';

    //H5弹窗记录
    const COLL_POPUP = 'popup';
    // 乐享日志
    const LEXIANG_LOG = 'lexiang_log';
    const LEXIANG_COLUMN = 'lexiang_column';

    //岗位序列
    const COLL_POSITION_SEQUENCE_CLASSIFY = 'position_sequence_classify';
    const COLL_POSITION_SEQUENCE = 'position_sequence';
    const COLL_POSITION_SEQUENCE_LINE = 'position_sequence_line';

    //考评设置
    const COLL_GRADE_SETTING = 'grade_setting';

    //年度学习时长设置
    const COLL_LEARNER_ANNUAL_HOURS = 'learner_annual_hours';

    //操作指引分类
    const COLL_GUIDANCE_CLASSIFY = 'guidance_classify';
    const COLL_GUIDANCE_INSTRUCTIONS = 'guidance_instructions';

    // 任务计划管理
    const COLL_INSIDE_TRAINING_PLAN = 'inside_training_plan'; //计划表
    const COLL_INSIDE_TRAINING_PLAN_TASK = 'inside_training_plan_task'; //任务表
    const COLL_INSIDE_TRAINING_PLAN_DEPT = 'inside_training_plan_department'; //实施部门表
    const COLL_INSIDE_TRAINING_DEPT_TASK_RELATION = 'inside_training_dept_task_relation'; //部门任务关联表
    const COLL_INSIDE_TRAINING_CLASS_DELAY_LOGS = 'inside_training_class_delay_logs'; //班级延期记录

    const COLL_TASK_LEARNER_REVIEWER_RELATION = 'task_learner_reviewer_relation'; //任务学员和审批人关系表

    const COLL_INSIDE_TRAINING_TTL = 60 * 60 * 8;

    const COLL_LEARNING_VIDEO_APPRAISE = 'learning_video_appraise'; //视频考核计划表
    const COLL_LEARNING_VIDEO_APPRAISE_LEARNERS = 'learning_video_appraise_learners'; //视频考核计划学员表
    const COLL_LEARNING_VIDEO_APPRAISE_LEARNERS_HISTORY = 'learning_video_appraise_learners_history'; //视频考核计划学员考核历史
    const COLL_LEARNING_VIDEO_APPRAISE_LEARNER_QUESTIONS = 'learning_video_appraise_learner_questions'; //学员试题表
    const COLL_LEARNING_VIDEO_APPRAISE_DELAY_LOGS = 'learning_video_appraise_delay_logs'; //补考核申请记录
    const COLL_AGORA_REQUEST_LOGS = 'agora_request_logs'; //声网请求日志
    const COLL_AGORA_NOTIFY_LOGS = 'agora_notify_logs'; //声网通知回调日志
    const COLL_AGORA_RECORDING_LOGS = 'agora_recording_logs'; //声网录制错误日志

    // 课程购买记录
    const COLL_COURSE_PAYMENT_LOGS = 'course_payment_log';

    //代办数据传输表
    const COLL_AGENT_SYNC = 'agent_sync';

    //定时脚本的最新执行时间
    const COLL_SERVICES_EXECUTE_RECORD = 'services_execute_record';

    //在线有效学时统计几排名
    const COLL_VALID_PERIODS_RANK = 'valid_periods_rank';

    // 自定义封面
    const COLL_CUSTOM_COVER = 'custom_cover';

    // 成长地图betterU
    const COLL_STUDY_LINE = 'study_line';
    const COLL_STUDY_LINE_STAGE = 'study_line_stage';

    const COLL_NOTIFY_TARGET_IMPORT = 'notify_target_import';
    const COLL_NOTIFY_TARGET_IMPORT_DETAILS = 'notify_target_import_details';

    const COLL_JASOLAR_OPENAPI_LOG = 'jasolar_openapi_log';

    // 顾家/森马预约中心
    const COLL_PREORDER_TYPE = 'preorder_type'; //被预约人类型
    const COLL_PREORDER_CATEGORY = 'preorder_category'; //被预约人擅长类别
    const COLL_PREORDER_TEACHER = 'preorder_teacher'; //被预约人
    const COLL_PREORDER_TEACHER_TYPES = 'preorder_teacher_types'; //被预约人的类型关联
    const COLL_PREORDER_TEACHER_CATEGORIES = 'preorder_teacher_categories'; //被预约人的类别关联
    const COLL_PREORDER_TEACHER_TIMES = 'preorder_teacher_times'; //被预约人时间设置
    const COLL_PREORDER_CONFIG = 'preorder_config'; //预约配置
    const COLL_PREORDER_LEARNER = 'preorder_learner'; //预约人

    // 贡献值
    const COLL_CONTRIBUTION = 'contribution';
    const COLL_CONTRIBUTION_RULE = 'contribution_rule';
    const COLL_CONTRIBUTION_SCORE = 'contribution_score';
    const COLL_CONTRIBUTION_SCORE_LOG = 'contribution_score_log';


    //教具管理
    const COLL_CONSUMABLE = 'consumable';
    const COLL_CONSUMABLE_APPLY = 'consumable_apply'; //消耗品申请

    const COLL_CONSUMABLE_WAREHOUSE = 'consumable_warehouse'; //消耗品入库

    const COLL_OCCUPANCY_APPLY = 'occupancy_apply'; //教具申请单

    const COLL_TRAINING_AID_OCCUPANCY = "training_aid_occupancy"; //占有型教具

    const COLL_TRAINING_AID_APPLY = "training_aid_apply"; //教具申请

    const COLL_TRAINING_AID_CATEGORY = 'training_aid_category'; //教具分类


    //交互式海报
    const COLL_POSTER = 'poster'; //海报信息
    const COLL_POSTER_CONTENT = 'poster_content'; //海报内容


    //竞赛活动
    const COLL_COMPETITION_ACTIVITY = 'competition_activity'; //竞赛活动
    const COLL_COMPETITION_ACTIVITY_APPLY = 'competition_activity_apply'; //竞赛活动报名设置
    const COLL_COMPETITION_ACTIVITY_ROUND = 'competition_activity_round'; //竞赛活动
    const COLL_COMPETITION_ACTIVITY_APPLY_EMPLOYEE = 'competition_activity_apply_employee'; //竞赛报名员工
    const COLL_COMPETITION_ACTIVITY_EMPLOYEE = 'competition_activity_employee'; //竞赛员工
    const COLL_COMPETITION_ACTIVITY_VOTE = 'competition_activity_vote';       //竞赛投票



    //预算管理
    const COLL_BUDGET = 'budget'; //预算
    const COLL_BUDGET_APPLY = 'budget_apply'; //预算申请
    const COLL_BUDGET_TYPE = 'budget_type'; //类型管理


    //作业附件
    const COLL_TASK_ZIP_DOWNLOAD = 'task_zip_download';

    // 设计图片背景
    const COLL_DESIGN_PICTURE_BACKGROUND = 'design_picture_background';
    const COLL_DESIGN_PICTURE_DETAIL = 'design_picture_detail';

    const COLL_EMPLOYEE_SIGNATURE = 'employee_signature'; // 签名

    //带训
    const COLL_TRAINING_TUTORING = 'training_tutoring';
    const COLL_TRAINING_TUTORING_STAGE = 'training_tutoring_stage';
    const COLL_TRAINING_TUTORING_TASK = 'training_tutoring_task';
    const COLL_TRAINING_TUTORING_LEARNER = 'training_tutoring_learner';
    const COLL_TRAINING_TUTORING_LEARNER_TASK = 'training_tutoring_learner_task';
    const COLL_TRAINING_TUTORING_RESOURCE = 'training_tutoring_resource';

    //报名
    const COLL_ENROLL = 'enroll';
    const COLL_ENROLL_SETTINGS = 'enroll_settings';
    const COLL_ENROLL_SESSIONS = 'enroll_sessions';
    const COLL_ENROLL_LEARNER = 'enroll_learner';
    const COLL_ENROLL_RECORD = 'enroll_record';
    const COLL_ENROLL_ANNEX = 'enroll_annex';
    const COLL_ENROLL_REPLACE_APPLICANT = 'enroll_replace_applicant';

    // VR全景图
    const COLL_PANORAMA = 'panorama'; // VR全景图
    const COLL_PANORAMA_IMG = 'panorama_img'; // VR全景图-图片
    const COLL_PANORAMA_HOTSPOT = 'panorama_hotspot'; // VR全景图-热点
    // 外部积分（目前只有学习包用）
    const COLL_RELATED_POINT_RULE = 'related_point_rule';   //积分规则表
    const COLL_RELATED_POINT_LOG = 'related_point_log';   //积分加减日志

    const COLL_YONYOU_ID = 'yonyou_id';

    //场景库
    const COLL_SCENE_CATEGORY = 'scene_category';
    const COLL_SCENE = 'scene';
    const COLL_SCENE_TALK = 'scene_talk';
    const COLL_SCENE_ANSWER = 'scene_answer';
    const COLL_SCENE_KEYWORD = 'scene_keyword';

    //智能班级培训资料
    const COLL_LEARNING_MATERIALS = 'learning_materials';

    // 关键词库
    const COLL_KEYWORDS_CATEGORY = 'keywords_category';
    const COLL_KEYWORDS = 'keywords';

    // AI助理
    const COLL_AI_ASSISTANT = 'ai_assistant';
    const COLL_AI_ASSISTANT_CONTENT = 'ai_assistant_content';
    const COLL_AI_ASSISTANT_CHAT = 'ai_assistant_chat';
    const COLL_AI_ASSISTANT_QA = 'ai_assistant_qa';
    const COLL_AI_ASSISTANT_QUESTION = 'ai_assistant_question';
    const COLL_AI_ASSISTANT_SELF_TEST_LOG = 'ai_assistant_self_test_log';
    const COLL_AI_ASSISTANT_SELF_TEST_CHAT = 'ai_assistant_self_test_chat';
    const COLL_AI_ASSISTANT_SELF_TEST_CALLBACK = 'ai_assistant_self_test_callback';

    // 素材(material 表名被占用)
    const COLL_MATERIAL_DIRECTORY = 'material_directory';
    const COLL_MATERIAL_BASE = 'material_base';
    const COLL_MATERIAL_DOWNLOAD_LOG = 'material_base_download_log';
    const COLL_MATERIAL_CITED_LOG = 'material_base_cited_log';

    //教室管理
    const COLL_CLASS_ROOM = 'class_room';
    const COLL_CLASSROOM_BOOKING_SETTING = 'classroom_booking_setting';
    const COLL_CLASSROOM_BOOKING = 'classroom_booking';
    const COLL_CLASSROOM_BOOKING_BLACKLIST = 'classroom_booking_blacklist';



    // 奇瑞运营报告
    const COLL_MYCHERY_REPORT = 'mychery_report';
    const COLL_MYCHERY_REPORT_NOTIFICATION = 'mychery_report_notification';
    const COLL_MYCHERY_REPORT_NOTIFICATION_DETAIL = 'mychery_report_notification_detail';
    const COLL_MYCHERY_REPORT_TRAINING_TRANSCRIPTS = 'mychery_report_training_transcripts';

    // 知识点库(knowledge_points 表名被占用)
    const COLL_LANGUAGE_POINTS = 'language_points';
    const COLL_LANGUAGE_POINTS_CATEGORY = 'language_points_category';
    const COLL_LANGUAGE_POINTS_CITED_LOG = 'language_points_cited_log';

    // 教材库
    const COLL_TEACHING_MATERIAL = 'teaching_material';
    const COLL_TEACHING_MATERIAL_ATTACHMENT = 'teaching_material_attachment';
    const COLL_TEACHING_MATERIAL_CITED_LOG = 'teaching_material_cited_log';


    //师带徒
    const COLL_TEACHING_ITEM = 'teaching_item';

    const COLL_TEACHING_ITEM_STUDENT = 'teaching_item_student';

    // 外派培训附件表（通用）
    const COLL_GENERIC_ATTACHMENT = 'generic_attachment';

    //观点pk
    const COLL_VIEWPOINT = 'viewpoint';

    const COLL_VIEWPOINT_STUDENT_DETAIL = 'viewpoint_student_detail';

    const COLL_VIEWPOINT_DETAIL_LIKE = 'viewpoint_detail_like';

    //图书管理
    const COLL_BOOK_DIRECTORY = 'book_directory'; //图书目录
    const COLL_BOOK = 'book'; //图书

    // 培训需求
    const COLL_TRAINING_NEEDS_CATEGORY = 'training_needs_category';
    const COLL_TRAINING_NEEDS = 'training_needs';
    const COLL_TRAINING_NEEDS_QUESTION = 'training_needs_question';
    const COLL_TRAINING_NEEDS_QUESTION_CLASSES = 'training_needs_question_classes';
    const COLL_TRAINING_NEEDS_ASKS = 'training_needs_asks';

    // 岗位认证指标
    const COLL_JOB_COMPETENCY_INDICATORS = 'position_competency_indicators';
    const COLL_JOB_COMPETENCY_INDICATOR_GROUP = 'position_competency_indicator_group';
    const COLL_JOB_COMPETENCY_INDICATOR_SKILL = 'position_competency_indicator_skill';
    const COLL_JOB_COMPETENCY_INDICATOR_RECORD = 'position_competency_indicator_record';
    const COLL_JOB_COMPETENCY_INDICATOR_RECORD_DETAIL = 'position_competency_indicator_record_detail';

    // 互动课程-统计
    const COLL_COURSE_INTERACTION_STATISTICS = 'course_interaction_statistics';
    // 互动课程-讨论
    const COLL_DISCUSSION = 'discussion'; // 讨论
    const COLL_DISCUSSION_REPLY = 'discussion_reply'; // 回复
    const COLL_DISCUSSION_REPLY_LIKE = 'discussion_reply_like'; // 点赞
    // 互动课程-词云
    const COLL_WORD_CLOUDS = 'word_clouds';
    const COLL_WORD_CLOUDS_ANSWER = 'word_clouds_answer';
    // 互动课程-测验
    const COLL_CONTENT_ASSIGNMENT_INTERACTION = 'content_assignment_interactioin';

    // 语音转文字
    const NLP_LOG = 'nlp_log';


    //训后行动评估
    const COLL_ACTION_ASSESSMENT = 'action_assessment'; //训后评估
    const COLL_ACTION_ASSESSMENT_CLASS = 'action_assessment_class'; //训后评估关联班级
    const COLL_ACTION_ASSESSMENT_LEARNER = 'action_assessment_learner';   // 学员
    const COLL_ACTION_ASSESSMENT_LEARNER_ITEM = 'action_assessment_learner_item'; // 行动项
    const COLL_ACTION_ASSESSMENT_LEARNER_DRAFT = 'action_assessment_learner_draft'; // 行动项草稿
    const COLL_ACTION_ASSESSMENT_LEARNER_NOTIFY_LOG = 'action_assessment_learner_notify_log'; // 行动项学员评价时间通知log


    const COLL_COMMON_LINKS = 'common_links';    // 链接
    const COLL_COMMON_LINKS_RESULT = 'common_links_result';    // 链接


    const COLL_AI_CASE_ASSISTANT = 'ai_case_assistant'; // 案例助手
    const COLL_AI_CASE_ASSISTANT_CHAT = 'ai_case_assistant_chat'; // 案例助手对话
    const COLL_AI_CASE_ASSISTANT_CHAT_DETAIL = 'ai_case_assistant_chat_detail'; // 案例助手对话详情
    const COLL_AI_CASE_ASSISTANT_CHAT_CASE = 'ai_case_assistant_chat_case'; // 案例助手-生成案例


    // 训练部数据汇总-九毛九
    const COLL_TRAIN_DATA = 'train_data';
    const COLL_TRAIN_DATA_HEADER = 'train_data_header';
    const COLL_TRAIN_DATA_RECORD = 'train_data_record';

    // 考试看板
    const COLL_EXAM_BOARD_DEPARTMENT = 'exam_board_department';  // 临时部门表
    const COLL_EXAM_BOARD_RECORD = 'exam_board_record';     //考试
    const COLL_EXAM_BOARD_RECORD_DETAIL = 'exam_board_record_detail';   //考试记录
    const COLL_EXAM_BOARD_DEPARTMENT_STATISTIC = 'exam_board_department_statistic';  // 临时部门表

    //通知黑名单
    const COLL_NOTIFY_BLACKLIST = 'notify_blacklist';

    // scorm 课程学习时间
    const COLL_SCORM_COURSE_LEARNING_TIME = 'scorm_course_learning_time';

    // 商品兑换范围
    const COLL_POINT_GOODS_EXCHANGE_RANGE = 'point_goods_exchange_range';

    const COLL_VOICE_COPY_ENGINE_CONFIG = 'voice_copy_engine_config'; //声音复刻配置表
    const COLL_VOICE_COPY_ENGINE = 'voice_copy_engine'; //创建声音复刻信息表
    const COLL_VOICE_COPY_ENGINE_LOG = 'voice_copy_engine_log'; //声音复刻日志记录表

    // AI生成PPT
    const COLL_AI_PPT = 'ai_ppt';
    const COLL_AI_PPT_OUTLINE = 'ai_ppt_outline';
    const COLL_AI_PPT_DETAIL = 'ai_ppt_detail';
    const COLL_AI_PPT_LOG = 'ai_ppt_log';

    // 指派任务
    const COLL_ASSIGN_TASKS = 'assign_tasks';
    const COLL_ASSIGN_TASKS_DETAIL = 'assign_tasks_detail';
    const COLL_ASSIGN_TASKS_LIKE = 'assign_tasks_like'; // 点赞
    const COLL_ASSIGN_TASKS_WRITE = 'assign_tasks_write'; // 心得体会
    const COLL_ASSIGN_TASKS_COMMENT = 'assign_tasks_comment'; // 评论
    const COLL_ASSIGN_TASKS_EXPLAIN_HISTORY = 'assign_tasks_explain_history'; // 评论
}
