<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2020 yidianzhishi.cn
 * @version 1.0.0
 * @link https://www.yidianzhishi.cn
 */

namespace App\Common;

use App\Models\RecycleBin\Storage;
use Key\Database\Mongodb;
use TencentCloud\Dlc\V20210125\Models\Other;

/**
 * Operation logger.
 *
 * @package App\Common
 * @author Guanghui Li <liguanghui2006@163.com>
 */
trait OperationLogger
{
    protected $op = 0;

    /**
     * Module name, such as 'employee'
     */
    protected $module = '';

    protected $oldData = null;
    protected $newData = null;

    protected $aid = 0;
    protected $eid = 0;
    protected $opTime = 0;

    protected $diffFields = [];

    protected $description = [];

    protected $ignoreDiffEmpty = false;

    protected $opLogEnabled = true;

    /**
     * Set module.
     *
     * @param string $module Module name, such as 'employee', 'class', etc
     * @return $this
     */
    public function setOpModule($module)
    {
        $this->module = $module;
        return $this;
    }

    /**
     * Set op.
     *
     * @param int $op Operation type, @see OP_CREATE/OP_UPDATE/...
     * @return $this
     */
    public function setOp(int $op)
    {
        $this->op = $op;
        return $this;
    }

    /**
     * Set account ID.
     *
     * @param int $aid Account ID
     * @return $this
     */
    public function setAid($aid)
    {
        $this->aid = (int) $aid;
        return $this;
    }

    /**
     * Set Operator ID.
     *
     * @param int $eid Operator ID (Employee ID)
     * @return $this
     */
    public function setEid(int $eid)
    {
        $this->eid = $eid;
        return $this;
    }

    /**
     * Set operation time.
     *
     * @param
     */
    public function setOpTime($opTime)
    {
        $this->opTime = $opTime;
        return $this;
    }

    /**
     * Set old data.
     *
     * @param {array|null} $oldData
     * @return $this
     */
    public function setOldData($oldData)
    {
        $this->oldData = $oldData;
        return $this;
    }

    /**
     * Set new data.
     *
     * @param {array|null} $newData
     * @return $this
     */
    public function setNewData($newData)
    {
        $this->newData = $newData;
        return $this;
    }

    /**
     * Set the diff fields.
     *
     * @param array $fields such as ['no', 'display', ...]
     * @return $this
     */
    public function setDiffFields($fields)
    {
        $this->diffFields = $fields;
        return $this;
    }

    protected function getDiffFields()
    {
        if ($this->diffFields) return $this->diffFields;
        if ($this->op) {
            $name = self::OP_NAMES[$this->op] ?? null;
            if ($name) {
                $diffFieldsName = 'logger_diff_fields_' . $name;
                if ($this->$diffFieldsName) {
                    return $this->$diffFieldsName;
                }
            }
        }
        return [];
    }

    protected function filterDiffArray($array)
    {
        if (!is_array($array)) return $array;
        if ($fields = $this->getDiffFields()) {
            $newArr = [];
            foreach ($array as $key => $val) {
                if (in_array($key, $fields)) {
                    $newArr[$key] = $val;
                }
            }
            return $newArr;
        } else {
            return $array;
        }
    }

    protected function getIp()
    {
        $ip=false;
        if(!empty($_SERVER["HTTP_CLIENT_IP"])){
            $ip = $_SERVER["HTTP_CLIENT_IP"];
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
            $ips = explode (", ", $_SERVER['HTTP_X_FORWARDED_FOR']);
            if($ip){
            array_unshift($ips, $ip); $ip = FALSE;
            }
            for($i = 0; $i < count($ips); $i++){
                if (!preg_match("#^(10|172\.16|192\.168)\.#", $ips[$i])){
                    $ip = $ips[$i];
                    break;
                }
            }
        }
        return ($ip ? $ip : $_SERVER['REMOTE_ADDR']);
    }

    /**
     * 内容id 内容名称 内容描述.
     * 例如：班级ID 某某班级 增加了xxx。
     *
     * @param int $id
     * @param string $name
     * @param string $desc
     * @return $this
     */
    public function describe($id, $name, $desc = '', $total = 0, $other = [])
    {
        $this->description = [
            'id' => $id,
            'name' => $name,
            'desc' => $desc,
            'total' => $total,
            'other' => $other
        ];
        return $this;
    }

    /**
     * Ignore the diff result is empty, and it saves the log.
     *
     * @param bool $ignore
     * @return $this
     */
    public function setIgnoreDiffEmpty($ignore = false)
    {
        $this->ignoreDiffEmpty = $ignore;
        return $this;
    }

    /**
     * Set state to enable log operation.
     *
     * @param bool $enabled
     * @return $this
     */
    public function setOpLogEnabled($enabled = true)
    {
        $this->opLogEnabled = $enabled;
        return $this;
    }

    /**
     * Save the log.
     */
    public function saveOpLog()
    {

        if (!$this->opLogEnabled) {
            return;
        }

        if (!$this->op) {
            throw new \InvalidArgumentException('[OpLog] Missing op!');
        }
        $request = $this->app['request'];
        $jsVer = $request->getHeaderLine('App-Version');
        $jsInVer = $request->getHeaderLine('App-Internal-Version');

        $appVer = $request->getHeaderLine('App-Platform-Version');
        $appBuild = $request->getHeaderLine('App-Platform-Build');

        $uri = $request->getUri();

        $reqTime = $_SERVER['REQUEST_TIME'] ?? 0;
        $reqFloatTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? 0;
        $referer = $_SERVER['HTTP_REFERER'] ?? '';

        $newData = $this->filterDiffArray($this->newData);
        $oldData = $this->filterDiffArray($this->oldData);
        $diff = arrayRecursiveDiff($newData, $oldData, false);
        if ($diff || $this->ignoreDiffEmpty) {
            $info = [
                '__class__' => get_class($this),
                'aid' => $this->aid,
                'eid' => $this->eid,
                'description' => $this->description,
                'module' => $this->module,
                'op' => $this->op,
                'old' => $oldData,
                // 'new' => $this->newData,
                'diff' => $diff,
                'op_time' => $this->opTime ?: $reqTime,
                'req_time' => $reqFloatTime,
                'referer' => $referer,
                'ip' => $this->getIp(),
                'agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'uri' => (string) $uri,                // Request uri
                'req_method' => $request->getMethod(),
                'query' => $_SERVER['QUERY_STRING'] ?? '', // Request params, GET?
                'js_ver' => $jsVer,
                'js_in_ver' => $jsInVer,
                'app_ver' => $appVer,
                'app_build' => $appBuild,
                'created' => Mongodb::getMongoDate()
            ];
            if (env('OPERATION_LOG_SYNC')) {
                $this->app['mongodb']->insert(Coll::OPERATION_LOG, $info);
                if ($this->op == self::OP_DELETE && isset($this->description['id'])) {
                    $scope = $oldData['organization'] ?? $oldData['affiliated_dept'] ?? null;
                    $admins = $oldData['monitor'] ?? null;
                    $owner = $oldData['eid'] ?? $oldData['uid'];
                    // error_log('--1---' . json_encode($scope));
                    // error_log('--3---' . json_encode($admins));
                    // error_log('---43--' . json_encode($owner));
                    (new Storage($this->app))->setObjectId($this->description['id'])
                        ->setObjectName($this->description['name'])
                        ->setScope($scope)
                        ->setAdmins($admins)
                        ->setOwner($owner)
                        ->setObjectTypeByModule($this->module)
                        ->save();
                }
            } else {
                try {
                    $message = new QueueMessage($this->aid, $this->eid, $info);
                    $connector = $this->app['rabbitmq'];
                    $connector->setQueue(Coll::OPERATION_LOG);
                    if ($connector->publish($message)) {
                        $connector->close();
                    }
                } catch (\Exception $ex) {
                    error_log('Exception, code ' . $ex->getCode() . ' ' . $ex->getMessage());
                    error_log($ex->getTraceAsString());
                }
            }
        } else {
            error_log('[OperationLogger::save] No changes or wrong diff fields set');
        }
    }
}