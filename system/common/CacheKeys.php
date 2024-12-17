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

class CacheKeys
{
    const EXAM_RIGHT_ANSWER_VALIDITY = 60 * 60 * 24 * 30;//
    const HP_SCORE_RANKING_VALIDITY = 600;//

    const EXAM = 'EXAM';
    const EXAM_RIGHT_ANSWER = 'EXAM_RIGHT_ANSWER'; //正确答案
    const EXAM_ANSWER = 'EXAM_ANSWER'; //学员答案
    const EXAM_ANSWER_YACE = 'EXAM_ANSWER_YACE'; //学员答案
    const EXAM_MY_UNFINISHED = 'EXAM_MY_UNFINISHED'; //未完成的考试
    const EXAM_MY = 'EXAM_MY'; // 我的考试详情

    const EXAM_MY_LAST_BUT_ONE = 'EXAM_MY_LAST_BUT_ONE'; // 倒数第二次有效的考试

    const SCORE_SETTING_V2 = 'SCORE_SETTING_V2'; // 学分设置

    const APP_HOMEPAGE_FUNCTION_MODULE = 'APP_HOMEPAGE_FUNCTION_MODULE'; //首页功能模块redis

    const HP_CONTENT = 'HP_CONTENT'; //首页整块内容
    const HP_CONTENT_STATUS = 'HP_CONTENT_STATUS'; //首页整块内容

    const HOMEPAGE_SET = 'HOMEPAGE_SET'; //首页模块设置
    const HOMEPAGE_COLUMN = 'HOMEPAGE_COLUMN'; //首页专栏

    const PRACTICE_LIST = 'PRACTICE_LIST'; //练习库列表

    const HP_SCORE_RANKING = 'HP_SCORE_RANKING'; //首页学分排行榜
    const HP_HONOR = 'HP_HONOR'; //首页荣誉墙

    const SYMBOL_PERSONAL_STATISTICS = 'SYMBOL_PERSONAL_STATISTICS'; //学习档案处勋章相关信息缓存，时效8小时

    const APP_CONFIG_TMP_ENABLES = 'APP_CONFIG_TMP_ENABLES'; //所有启用的首页模板
    const APP_CONFIG_TMP_MEMBER = 'APP_CONFIG_TMP_MEMBER'; //个人使用的首页模板

    const EXAM_SET = 'EXAM_SET';

    const APP_MAIN = 'APP_MAIN'; //APP 我的页面图标
    const APP_MODULE = 'A_M:'; // 模块
    const APP_DISCOVERY = 'A_DCV:'; // 发现

    const HOMEWORK = 'HOMEWORK'; //作业

    const EXAM_PUBLISH_STATUS = 'EXAM_PUBLISH_STATUS';

    // 笔记
    const NOTE = 'NOTE:';

    // 用户
    const USER = 'USR:';
    const USR_PWD_SMS = 'USR_PWD_SMS:'; // 短信验证码
    const USR_MY_PWD_SMS = 'USR_MY_PWD_SMS:';
    const USR_RESET_PW_SMS = 'USR_RESET_PW_SMS:'; // 重置密码

    const RANKING_LIST = 'RANKING_LIST';

    const HOMEPAGE_SET_PC = 'HOMEPAGE_SET_PC';
    const HOMEPAGE_SET_PC_CONTENT = 'HOMEPAGE_SET_PC_CONTENT';


    // 飞书
    const FEISHU_ACCESS_TOKEN = 'FEISHU_ACCESS_TOKEN:';
    const FEISHU_STATE = 'FEISHU_STATE:';


    const WORKBENCH_NUM = 'WORKBENCH_NUM';
    const MEMBERSHIP_POINT_SETTING = 'MEMBERSHIP_POINT_SETTING'; // 学分设置

    // Employee self-register @see \App\Models\Employee\SelfRegisterConfig
    const EMP_SR = 'EMP_SR:';

    // Employee
    const EMP_ITEM_KEY = 'WEIKE_EMP:';

    // Employee Total number
    const EMP_TOTAL_A = 'EMP_TOTAL_A:'; // all
    const EMP_TOTAL_E = 'EMP_TOTAL_E:'; // enabled
    const EMP_TOTAL_D = 'EMP_TOTAL_D:'; // disabled

    // Employee total learning time
    const EMP_TLT = 'EMP_TLT:';

    // Department Keys
    const DEPT_PATH_NAMES = 'DEPT_PN:';
    const DEPT_PATH_NAMES_TTL = 600;

    const DEPT = 'DEPT:'; // department item
    const DEPT_R = 'DEPT_R:'; // department root

    const DATA_PERM_DEPT = 'DATA_PERM_DEPT';

    // 用于微抖上一条/下一条的列表缓存
    const MICROTOK_NEXT_PREV_LIST = 'MT_NP:';
    const MICROTOK_MY_NEXT_PREV_LIST = 'MT_M_NP:';

    // Training center
    const TRAINING_CENTER_ITEM = 'TCI:';
    const TRAINING_CENTER_MANAGABLE_COND = 'TC_MC:'; // Training center managable condition

    // OSS-S3-Storage sign configure
    const OSS_S3_SIGN_CONF = 'OSS_S3_SC';


    const MT_QUICK_ENTRY = 'MT_QUICK_ENTRY';

    // Short link
    const SHORTLINK = 'SL:';

    // Employee dictionary
    const DICT_EMPLOYEE = 'DICT_EMP:';

    const MEMBERSHIP_POINT_LOG = 'MEMBERSHIP_POINT_LOG:';

    const LEARNING_CLASS_POINT_SETTING = 'LEARNING_CLASS_POINT_SETTING'; //智能班级积分规则

    // AI Scenario
    const AI_SCENARIO_ITEM = 'ai_si:';
    const AI_SCENARIO_PROCESS = 'ai_sp:';

    // Custom fields
    const CUSTOM_FIELDS_ITEM = 'cci:';
    const CUSTOM_FIELDS_ITEM_BY_KEY = 'cci_k:';
    const CUSTOM_FIELDS_MODULE = 'ccm:';

    //岗位分组下技能
    const POSITION_MAP_GROUPED = 'position_map_grouped';
    const POSITION_MAP_CERTIFICATION = 'position_map_certification';

    //技能矩阵==查询记录/技能获取详情
    const MATRIX_SELECT_RECORD = 'matrix_select_record';
    const ALL_EMP_CERTIFICATIONS = 'all_emp_certifications';
    const ALL_EMP_INFO = 'all_emp_info';
    const POSITION_ELEMENT = 'position_element';

}
