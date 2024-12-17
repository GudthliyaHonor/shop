<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */
namespace Key;

/**
 * Defined the constant values, such as error codes.
 *
 * @package Key
 * @author Guanghui Li <liguanghui2006@163.com>
 */
class Constants
{
    //-------------------------------------------------
    // Session keys
    //-------------------------------------------------
    /** @var string Logged-in user */
    const SESSION_USR_KEY = '__USER__';

    /** @var string Account info for the logged-in user */
    const SESSION_ACCOUNT_KEY = '__ACCOUNT__';

    /** @var string Role info for the logged-in user */
    const SESSION_ROLE_KEY = '__ROLE__';

    //-------------------------------------------------
    // Status codes
    //-------------------------------------------------
    const STATUS_ENABLED = 1;
    const STATUS_DISABLED = 0;
    const STAFF_STATUS_DELETED = 2;
    const STATUS_EXCEPTIONS = 3;

    //-------------------------------------------------
    // Pagination
    //-------------------------------------------------
    /** Default items per page */
    const DEFAULT_ITEMS_PER_PAGE = 10;

    //-------------------------------------------------
    // List of system/framework level status code
    //-------------------------------------------------

    /** Success returned code */
    const SYS_SUCCESS = 0;

    //-------------------------------------------------
    // List of system/framework level error code
    //-------------------------------------------------
    /** Miscellaneous/non-specific system error */
    const SYS_ERROR_DEFAULT = -1;

    /** Internal system error */
    const SYS_INTERNAL = -2;

    /** Access control error */
    const SYS_ACL_ERROR = -3;

    /** Database error */
    const SYS_DATABASE_ERROR = -4;

    /** Invalid/unknown request */
    const SYS_REQ_INVALID = -10;

    /** Invalid/corrupt input */
    const SYS_INPUT_INVALID = -11;

    /** Incomplete/missing input */
    const SYS_INPUT_MISSING = -12;

    /** No authenticated user */
    const SYS_NO_USER = -20;

    /** Request security requirement (eg. https) not met */
    const SYS_REQ_INSECURE = -21;

    /** Request authorization failure */
    const SYS_REQ_AUTH = -22;

    /** Request authorization grant */
    const SYS_REQ_AUTH_GRANT = -23;

    /** User authorization failure */
    const SYS_PERMISSION_FAULT = -30;

    /** Router config error */
    const SYS_ROUTER_CONFIG_ERROR = -40;

    /** Route not found */
    const SYS_ROUTE_NOT_FOUND = -41;

    /** Request parameter invalid */
    const SYS_ROUTE_PARAM_ERROR = -42;

    /** Request method is not match route method */
    const SYS_ROUTE_INVALID_METHOD = -43;

    /** Action not found in a controller */
    const SYS_ROUTE_ACTION_NOT_FOUND = -44;

    /** Controller action return value must a number */
    const SYS_ROUTE_INVALID_RETURN = -45;

    /** Route method is required */
    const SYS_ROUTE_METHOD_REQUIRED = -46;

    /** Invalid request path, missing route prefix */
    const SYS_ROUTE_INVALID_PATH = -47;

    /** Route open api is invalid */
    const SYS_ROUTE_INVALID_OPEN_API = -50;

    /** Database maintenance(DB migration or transfer) */
    const SYS_DATABASE_MAINTENANCE = -99;

    //-------------------------------------------------
    // List of User custom level error code
    //-------------------------------------------------
    const USER_DEFAULT_ERROR = 1;

    /** Object not found error */
    const USER_OBJECT_NOT_FOUND = 2;

    /** Object exists */
    const USER_OBJECT_ALREADY_EXISTS = 3;

    /** Object has been used or occupied */
    const USER_OBJECT_OCCUPIED = 4;

    /** Object is disabled */
    const USER_OBJECT_DISABLED = 5;

    /** Daemon error codes */
    const USER_DAEMON_HANDLER_CONFIG_ERROR = 12;
    const USER_DAEMON_HANDLER_NOT_FOUND = 11;
    const USER_DAEMON_TASK_PROCESSING = 13;

    const USER_DAEMON_SERVER_ERROR = 14;

    const OPERATION_TOO_FREQUENT = 15;

    const OPEN_API_UNAUTHENTICATION = 50;
    const OPEN_API_AUTHENTICATION_FAILED = 51;

    /**Lecturer settings error codes*/
    const LECTURE_SETTINGS_BE_USED = 60;

    //---------------------------------------------------
    // List languages
    //---------------------------------------------------
    const LANG_DEFAULT = 'zh-CN';
    const LANG_EN = 'en';
    const LANG_ZH_TW = 'zh-TW';
    // TODO: more languages here
}
