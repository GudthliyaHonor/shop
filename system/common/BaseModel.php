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


use InvalidArgumentException;
use Key\Database\Mongodb;
use Key\Database\MongoManager;
use Key\Exception\AppException;
use Key\Log\LoggerManager;
use Key\Queue\QueueManager;
use Pimple\Container;

class BaseModel extends \Key\Abstracts\BaseModel
{

    use OperationLogger;

    const FILTER_BETWEEN = 1;
    const FILTER_EQUAL = 2;
    const FILTER_NO_EQUAL = 3;
    const FILTER_BEFORE = 4;
    const FILTER_AFTER = 5;
    const FILTER_CONTAIN = 6;
    const FILTER_ABOVE = 7;
    const FILTER_BELOW = 8;

    const SCOPE_USER = 0;
    const SCOPE_ACCOUNT = 1;
    const SCOPE_ALL = 2;

    const OP_CREATE = 1;
    const OP_UPDATE = 2;
    const OP_DELETE = 3;
    const OP_VIEW = 4;
    const OP_IMPORT = 5;
    const OP_EXPORT = 6;
    const OP_PUBLISH = 7;
    const OP_COPY = 8;
    const OP_END = 9;
    const OP_DISABLE = 10;
    const OP_PUT_TOP = 11;
    const OP_CANCEL_TOP = 12;
    const OP_NOTIFY = 13;
    const OP_LOCK = 14;
    const OP_UNLOCK = 15;

    const OP_NAMES = [
        self::OP_CREATE => 'create',
        self::OP_UPDATE => 'update',
        self::OP_DELETE => 'delete',
        self::OP_VIEW => 'view',
        self::OP_IMPORT => 'import',
        self::OP_EXPORT => 'export',
        self::OP_PUBLISH => 'publish',
        self::OP_COPY => 'copy',
        self::OP_END => 'end',
        self::OP_PUT_TOP => 'put_top',
        self::OP_CANCEL_TOP => 'cancel_top',
        self::OP_NOTIFY => 'notify',
    ];

    protected $user;
    protected $account;
    protected $uid = 0;
    protected $aid = 0;
    protected $eid = 0;

    protected $currentLanguage;

    // 是否忽略权限过滤
    protected $withoutACL = false;

    // 忽略次要部门条件
    protected $ignoreP2nd = false;

    final public function __construct(Container $app)
    {
        Sequence::$app = $app;

        if ($app->offsetExists('__CONSOLE__')) {
            $this->aid = $app->offsetExists(Constants::SESSION_KEY_CURRENT_ACCOUNT_ID) ? $app[Constants::SESSION_KEY_CURRENT_ACCOUNT_ID] : 0;
            $this->uid = 0;
            $this->eid = $app->offsetExists(Constants::SESSION_KEY_CURRENT_EMPLOYEE_ID) ? $app[Constants::SESSION_KEY_CURRENT_EMPLOYEE_ID] : 0;
        } else {

            $isAuth = true;
            $isLogin = false;
            if ($app->offsetExists(Constants::CURRENT_ROUTE)) {
                /** @var \Key\Route $route */
                $route = $app[Constants::CURRENT_ROUTE];
                $isAuth = $route->isAuth();
                // error_log('>>>>>>isAuth>>>>>' . var_export($isAuth, true));
                // error_log('>>>>>>path>>>>>' . $route->getUri()->getPath());
                $isLogin = endsWith($route->getUri()->getPath(), '/user/login');
            }

            if ($isAuth || !$isLogin) {
                $session = $app['session'];
                $this->user = $session->get(Constants::SESSION_USR_KEY, null);
                $this->account = $session->get(Constants::SESSION_KEY_PC_ACCOUNT, null);

                $this->uid = ArrayGet($this->user, 'id', 0);
                $this->aid = $session->get(Constants::SESSION_KEY_CURRENT_ACCOUNT_ID, 0);
                $this->eid = $session->get(Constants::SESSION_KEY_CURRENT_EMPLOYEE_ID, 0);
            }
            else {
                $this->uid = 0;
                $this->aid = 0;
                $this->eid = 0;
            }
        }

        $this->setTimezoneByClient($app);

        parent::__construct($app);
    }

    public function getEnv()
    {
        if ($this->app->offsetExists('ENV_MODEL')) {
            return $this->app['ENV_MODEL'];
        }
        return false;
    }

    /**
     * Mock the account.
     *
     * @param int $aid
     * @param int $eid
     */
    public function mock($aid, $eid = 0)
    {
        $this->aid = $aid;
        $this->app['__CONSOLE__'] = 1;
        $this->app[Constants::SESSION_KEY_CURRENT_ACCOUNT_ID] = $aid;
        if ($eid && is_null($this->eid)) {
            $this->eid = $eid;
            $this->app[Constants::SESSION_KEY_CURRENT_EMPLOYEE_ID] = $eid;
        }
        return $this;
    }

    protected function getGlobalConnection()
    {
        return $this->app['mongodb_global'];
    }

    protected function getMongoMasterConnection()
    {
        $dbConnector = new DBConnector($this->app);
        if ($this->app->offsetExists(Constants::SESSION_KEY_CURRENT_ACCOUNT_ID)) {
            $dbConnector->setupById((int) $this->app[Constants::SESSION_KEY_CURRENT_ACCOUNT_ID]);
        }
        elseif ($this->aid) {
            $dbConnector->setupById($this->aid);
        }
        return $this->app['mongodb'];
    }

    /**
     * Set the withoutACL attr.
     *
     * @param bool $withoutACL
     * @return $this
     */
    public function setWithoutACL($withoutACL)
    {
        $this->withoutACL = !!$withoutACL;
        return $this;
    }

    /**
     * Set ignore p2nd state.
     * @param bool $ignoreP2nd
     * @return $this
     */
    public function setIgnoreP2nd($ignoreP2nd)
    {
        $this->ignoreP2nd = !!$ignoreP2nd;
        return $this;
    }

    /**
     * Get language.
     *
     * @return string Language key, such as 'en', 'zh-CN', 'zh-TW', etc
     */
    public function getLanguage()
    {
        return $this->app->offsetExists('locale') ? $this->app['locale'] : env('APP_LANGUAGE_DEFAULT', 'zh-CN');
    }

    public function t($key, $replace = [], $locale = null)
    {
        /** @var \Key\Translation\Translator $translator */
        $translator = $this->app['translator'];
        return $translator->get($key, $replace, $locale);
    }

    /**
     * Get lowercase language name.
     * Note: Convert charactar '-' to '_'
     * 
     * @return string
     */
    public function getLowerCaseLanguage()
    {
        return strtolower(str_replace('-', '_', $this->getLanguage()));
    }

    public function getClientTimezone($app)
    {
        /** @var \Key\Http\Request $request */
        $request = $app ? $app['request'] : $this->app['request'];
        if ($request) {
            $tzOffset = $request->getHeaderLine('App-TZ-Offset');
            if (!is_null($tzOffset) && strlen($tzOffset) != 0) {
                return timezone_name_from_abbr('', $tzOffset * 60, false);
            }
        }
        return env('APP_DEFAULT_TIMEZONE', 'Asia/Shanghai');
    }

    public function setTimezoneByClient($app)
    {
        $timezone = $this->getClientTimezone($app);
        date_default_timezone_set($timezone);
    }

    /**
     * Get REST data from URL.
     *
     * @param string $uri Micro service API, such as '/dictionary/view'
     * @param string $serviceName Micro service name, such as 'dictionary'
     * @param array $params API parameters
     * @param string $method Require method, example: GET, POST, etc
     * @return string|bool
     * @throws AppException
     */
    protected function getRestData($uri, $serviceName, $params = array(), $method = RestModel::REQ_GET)
    {
        $restModel = new RestModel($this->app);
        return $restModel->getRestData($uri, $serviceName, $params, $method);
    }

    /**
     * Log the message.
     *
     * @param string $msg
     * @param string $level one of trace/info/debug/warn/error/fatal
     */
    protected function log($msg, $level = 'debug')
    {
        $this->logV2($msg, null, $level);
    }

    /**
     * Log the message to the file.
     *
     * @param string $msg
     * @param string $logName Log file name
     * @param string $level one of trace/info/debug/warn/error/fatal
     */
    protected function logV2($msg, $logName = 'console', $level = 'debug', $traceLimit = 2)
    {
        $appName = $logName ?: ($this->app->offsetExists('appName') ? $this->app['appName'] : 'console');
        $logger = LoggerManager::getFileInstance($appName);
        if (method_exists($logger, $level)) {
            $debugInfo = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $traceLimit);
            $func = $debugInfo[$traceLimit - 1]['function'];
            $msg = sprintf('[%s][%s:%s] %s',
                $this->app && $this->app->offsetExists('request_id') ? $this->app['request_id'] : '_',
                get_called_class(),
                $func,
                is_string($msg) ? $msg : json_encode($msg)
            );
            $logger->{$level}($msg);
        } else {
            throw new InvalidArgumentException('Invalid logger level: ' . $level);
        }
    }

    /**
     * Check the value if unique.
     *
     * @param array $pairs
     * @param string $collection Mongodb collection name
     * @param array|null $pre Pre-conditions
     * @param array|null $fields Result fields
     * @return array
     */
    public function unique($pairs, $collection, $pre = null, $fields = null)
    {
        if (!is_array($pairs) || !$pairs || count($pairs) == 0) {
            throw new InvalidArgumentException('Invalid field name, it must be a non-empty array(key-value pair).');
        }

        $condition = array(
            'aid' => $this->aid,
            'status' => static::ENABLED
        );

        if ($pre && is_array($pre)) {
            $condition = array_merge($condition, $pre);
        }

        if (count($pairs) === 1) {
            $condition = array_merge($condition, $pairs);
        } else {
            $ors = array();
            foreach ($pairs as $key => $val) {
                $ors[] = array(
                    $key => $val
                );
            }

            $condition['$or'] = $ors;
        }

        if (is_array($fields) && $fields) {
            $newFields = $fields;
        } else {
            $newFields = array('_id' => 0, 'id' => 1);
        }

        $db = MongoManager::getInstance($this->app);
        return $db->fetchAll($collection, $condition, 0, 3, array(), $newFields);
    }

    /**
     * For exporting main document data.
     * @see \App\Exports\MainDocumentExport::getRows()
     *
     * @param null|\Key\Records\Pagination $pagination
     * @return array
     */
    public function getExportRows($pagination = null)
    {
        return array();
    }

    /**
     * Exports sub document data.
     * @see \App\Exports\SubDocumentExport::getRows()
     *
     * @param string $name Sub document name
     * @param null|\Key\Records\Pagination $pagination
     * @return array
     */
    public function getExportSubRows($name, $pagination = null)
    {
        return array();
    }

    /**
     * For data import.
     *
     * @param int $id
     * @param array $record
     * @return bool
     */
    public function updateDocument($id, $record)
    {
        return true;
    }

    /**
     * For data import.
     *
     * @param array $keyPairs Sub document key-value pairs for update condition
     * @param array $main Condition for main document
     * @param array $record Sub document data
     * @param string $subTarget Sub document name
     * @return bool
     */
    public function updateSubDocument($keyPairs, $main, $record, $subTarget)
    {
        if (!is_array($keyPairs) || count($keyPairs) == 0) {
            throw new InvalidArgumentException('Invalid KeyPairs, it must be a non-empty array.');
        }

        if (!is_array($main) || count($main) == 0) {
            throw new InvalidArgumentException('Invalid Records, it must be a non-empty array.');
        }

        if (!is_array($record) || count($record) == 0) {
            throw new InvalidArgumentException('Invalid Records, it must be a non-empty array.');
        }

        return true;
    }

    /**
     * Create main document.
     *
     * @param \Key\Abstracts\BaseRecord $record Main document data
     * @return bool
     * @throws AppException
     */
    public function createDocument($record)
    {
        throw new AppException('createDocument not implementation');
    }

    /**
     * For data import.
     *
     * @param array $main Condition for main document
     * @param array $record Sub document data
     * @param string $subTarget Sub document name
     * @return bool
     */
    public function createSubDocument($main, $record, $subTarget)
    {
        return true;
    }

    /**
     * Delete the data.
     *
     * @param string $collection Collection name, for example: account
     * @param array $condition For example: ['id' => 1000]
     * @param bool $absolute If true, the rows matched will be deleted from DB
     * @return bool
     */
    public function deleteByCondition($collection, $condition = [], $absolute = false)
    {
        $db = $this->getMongoMasterConnection();
        if ($absolute) {
            $db->delete($collection, $condition);
        } else {
            $db->update($collection, $condition, [
                '$set' => [
                    'status' => static::DISABLED,
                    'updated' => Mongodb::getMongoDate()
                ]
            ]);
        }
        return true;
    }

    /**
     * Validate the data defined in the resource of the collection schema.
     *
     * @param array $pieces Resource definition, for example: `employee:name`
     * @param array $params Condition parameters
     * @param array $fields Returns the fields
     * @return mixed
     */
    public function handleResource($pieces, $params, $fields)
    {
        if (!is_array($pieces) || count($pieces) == 0) {
            throw new InvalidArgumentException('[BaseModel] Invalid pieces, it must have 1 value in the array at least');
        }

        $name = $pieces[0];
        $attr = isset($pieces[1])?$pieces[1]:'';
        $condition = array(
            'aid' => $this->aid,
            'status' => static::ENABLED
        );

        $newFields = array();
        if ($fields) {
            if($attr){
                foreach ($params as $key => $val) {
                    $condition[$key] = $val;
                }
                $newFields = array(
                    $name => 1,
                    $attr => 1,
                    $attr.'.$' => 1
                );
                $newFields = array_merge($fields,$newFields);

                $data = $this->getResourcedData($condition, $newFields);
                if ($data && $data[$attr]) {

                    if ($fields) {
                        $newData = array();
                        foreach($fields as $key => $val) {
                            if(strpos($key,'.') === false){
                                $newData[$key] = $data[$key];
                            }else{
                                $arr = explode('.',$key);
                                $newData[$key] = $data[$attr][0][$arr[1]];
                            }

                        }
                        return $newData;
                    } else {
                        return $data[$attr];
                    }
                }
            }else{

                foreach ($params as $key => $val) {
                    $condition[$key] = trim($val);
                }
                foreach($fields as $key => $val) {
                    $newFields[$key] = $val;
                }
                $newFields[$name] = 1;

                $data = $this->getResourcedData($condition, $newFields);
                if ($data && isset($data[$name])) {

                    if ($fields) {
                        $newData = array();
                        foreach($fields as $key => $val) {
                            if(isset($data[$key])){
                                $newData[$key] = $data[$key];
                            }

                        }

                        return $newData;
                    } else {
                        return $data;
                    }
                }
            }
        }

        return array();
    }

    /**
     * Get the data for resource.
     *
     * @param array $condition
     * @param array $fields
     * @return array|null
     */
    protected function getResourcedData($condition, $fields)
    {
        error_log('[BaseModel] Fetch the data for resource');
        return null;
    }

    /**
     * Get the department for model object.
     *
     * @return bool
     * @throws AppException
     */
    public function getDepartmentAcl()
    {
        throw new AppException('You must override the base function.');
    }

    /**
     * For relationship update
     *
     * @param array $pairs Update data pair, such as array('name' => 'xyz')
     * @param array $props Update condition
     * @param int $scope Updating scope
     * @throws AppException
     */
    public function updateRelated($pairs, $props, $scope = self::SCOPE_ACCOUNT)
    {
        throw new AppException('You must override the base function.');
    }

    /**
     * @param string $queue
     * @param QueueMessage $message
     * @return bool
     */
    public function publishToQueue($queue, $message)
    {
        if (empty($queue)) {
            throw new InvalidArgumentException('Queue name is required');
        }

        $manager = new QueueManager($this->app);
        $connector = $manager->getDefaultConnector();
        if ($connector) {
            $connector->setQueue($queue);
            $connector->publish($message);
            $connector->close();
            return true;
        } else {
            $this->log('[BaseModel]Queue connector init fail');
        }
        return false;
    }

    /**
     * Trigger event for handling.
     *
     * @param string $eventName Event name, such as 'employee:updated'
     * @param array $params Event arguments for handling
     * @param string $callback Callback for handled
     * @param boolean $forceSync If true, use sync mode to run
     * @param string|string[] $additionalModules Load additional module to load classes
     * @return void
     */
    final public function triggerEvent($eventName, $params = [], $callback = null, $forceSync = false, $additionalModules = null)
    {

        if (!$eventName) {
            throw new InvalidArgumentException('Event is required');
        }

        try {
            $additionalEvents = [];
            $module = null;
            if ($additionalModules) {
                if (!is_array($additionalModules)) {
                    $additionalModules = [$additionalModules];
                }
                foreach ($additionalModules as $additionalModule) {
                    $bootstrapFile = APPS_PATH . DS . $additionalModule . DS . 'bootstrap.php';
                    error_log('[triggerEvent] loading ' . $bootstrapFile);
                    /** @var \Key\Foundation\Module $module */
                    $module = require($bootstrapFile);

                    $moduleEvents = include(APPS_PATH . DS . $additionalModule . DS . 'config' . DS . 'events.php');
                    if ($moduleEvents && is_array($moduleEvents)) {
                        $additionalEvents = array_merge($additionalEvents, $moduleEvents);
                    }

                    if (!$asyncMode) {
                        // load module classes
                        $module->registerClasses($container, true, true);
                    }
                }
            }

            if (!isset($this->app['config']['app.events'])) {
                if ($this->app->offsetExists('appName')) {
                    $appName = $this->app['appName'];
                    // $appConfig = include(APPS_PATH . DS . $appName . DS . 'config' . DS . 'app.php');
                    // $events = ArrayGet($appConfig, 'events');
                    $eventFile = APPS_PATH . DS . $appName . DS . 'config' . DS . 'events.php';
                    if (file_exists($eventFile)) {
                        $events = include($eventFile);
                    }
                }
                else {
                    error_log('[triggerEvent] appName not set: ' . $eventName);
                    $events = [];
                }
            } else {
                $events = $this->app['config']['app.events'];
            }
            $events = array_merge($additionalEvents, $events ?: []);

            $asyncMode = !$forceSync && env('EVENT_HANDLER_RULE') == 'ASYNC';

            $handlers = ArrayGet($events, $eventName);
            if ($handlers) {
                if ($asyncMode) {
                    $message = new QueueMessage($this->aid, $this->eid, [
                        'handlers' => $handlers,
                        'params' => $params,
                    ]);
                    if ($module) {
                        $message->setAppModule($module);
                    }
                    $message->setAdditionalModules($additionalModules);
                    $this->publishToQueue(Constants::QUEUE_MODEL_EVENTS, $message);
                } else {
                    foreach($handlers as $handler) {
                        // $this->log('[triggerEvent] event fired: ' . $handler);
                        $pieces = explode('::', $handler);
                        $className = $pieces[0];
                        $method = $pieces[1];
                        if (class_exists($className)) {
                            $class = new $className($this->app);
                            if (method_exists($class, $method)) {
                                $result = call_user_func_array(array($class, $method), $params);
                                if ($callback) {
                                    call_user_func($callback, $result, $className, $method);
                                }
                            }
                            else {
                                $this->log(sprintf('method %s not found in class %s', $method, $className));
                            }
                        }
                        else {
                            $this->log(sprintf('classname %s not found', $className));
                        }
                    }
                }
            }
        } catch (\Exception $ex) {
            error_log('[triggerEvent] Exception ' . $ex->getCode() . ' ' . $ex->getMessage());
        }
    }

    /**
     * Check if object used.
     *
     * @param string $handlerName
     * @param array $params
     * @return boolean
     */
    public function checkIfUsed($handlerName, $params = [])
    {
        if ($handlerName) {
            if (!isset($this->app['config']['app.checks'])) {
                $appName = $this->app['appName'];
                $checks = include(APPS_PATH . DS . $appName . DS . 'config' . DS . 'checks.php');
            }
            else {
                $checks = $this->app['config']['app.checks'];
            }
            if (isset($checks[$handlerName])) {
                $check = $checks[$handlerName];

                $requireModules = $check['modules'] ?? [];
                if ($requireModules) {
                    foreach ($requireModules as $moduleName) {
                        /** @var \Key\Foundation\Module $module */
                        $module = require(APPS_PATH . DS . $moduleName . DS . 'bootstrap.php');
                        // load module classes
                        $module->registerClasses($container, true, true);
                    }
                }

                $passState = $check['pass'];
                $handles = $check['handles'];
                $results = [];
                foreach ($handles as $handle) {
                    $exploded = explode('::', $handle);
                    $className = $exploded[0];
                    $method = $exploded[1];
                    if (class_exists($className)) {
                        $class = new $className($this->app);
                        if (method_exists($class, $method)) {
                            $result = call_user_func_array(array($class, $method), $params);
                            if ($passState == 1 && $result === true) {
                                return true;
                            }
                            $results[] = $result;
                        }
                        else {
                            throw new AppException(sprintf('Method %s not found in class %s', $method, $className));
                        }
                    }
                    else {
                        throw new AppException(sprintf('Class %s not found', $className));
                    }
                }
                if ($passState == 2) {
                    $filtered = array_filter($results);
                    if (count($filtered) == count($results)) {
                        return true;
                    }
                }
            }
            return false;
        }
        return false;
    }



    protected function getDBConnectionByAccountId($aid = 0)
    {
        if ($aid) {
            $connector = new DBConnector($this->app);
            $connector->setupById($aid);
        }
        return $this->getMongoMasterConnection();
    }

    /**
     * 华丽的分割线
     *
     * 示例如下
     *
     *   echo divider(1);
     *   echo partLine(1) . '├─ 读取 Excel 文件' . PHP_EOL;
     *   echo divider(1, false);
     *
     * @param integer $line_level 【0 默认：顶部】, 基于foreach【1 第一层】【2 第二层】...
     * @param bool $is_header 【true 头部】【false 底部】
     * @return string
     */
    protected function divider($line_level = 0, $is_header = true, $num = 17)
    {
        // 默认分割线
        if ($line_level == 0) {
            return $this->partLine($num, '────') . PHP_EOL;
        }

        $str = $is_header ? '┌───' : '└───';

        $header_str = $this->partLine($line_level - 1);
        $footer_str = $this->partLine($num - $line_level, '────');

        return $header_str . $str . $footer_str . PHP_EOL;
    }

    /**
     * 部分 分割线
     *
     * @param integer $line_level
     * @param string $one 循环的单元
     * @return string
     */
    protected function partLine($line_level = 0, $one = '    ')
    {
        if ($line_level == 0) {
            return '';
        }

        return $one . $this->partLine($line_level - 1, $one);
    }
}
