<?php
/**
 * Message for RabbitMQ.
 * User: lgh
 * Date: 2018/1/29
 * Time: 14:05
 */

namespace App\Common;


use Key\Collection;
class QueueMessage
{

    /** @var int Account ID */
    protected $aid;

    /** @var int Employee ID */
    protected $eid;

    /** @var Collection Extra data of message */
    protected $data;

    protected $timestamp = 0;

    protected $deliveryTag = 0;

    protected $lang = null;

    protected $appModule = null;

    protected $uuid = null;

    protected $additionalModules = [];

    /**
     * @return int
     */
    public function getDeliveryTag()
    {
        return $this->deliveryTag;
    }

    /**
     * @param int $deliveryTag
     * @return QueueMessage
     */
    public function setDeliveryTag($deliveryTag)
    {
        $this->deliveryTag = $deliveryTag;
        return $this;
    }

    /**
     * Set app module.
     * @param \Key\Foundation\Module $module
     * @return $this
     * @deprecated 1.0.0
     */
    public function setAppModule($module)
    {
        $this->appModule = $module;
        return $this;
    }

    /**
     * Get app module.
     * @return \Key\Foundation\Module|null
     * @deprecated 1.0.0
     */
    public function getAppModule()
    {
        return $this->appModule;
    }

    /**
     * Set additional modules for Q consumers.
     * @param string[] $modules
     * @return $this
     */
    public function setAdditionalModules($modules)
    {
        $this->data->set('additionalModules', $modules);
        return $this;
    }

    /**
     * Get additional modules.
     * @return string[]
     */
    public function getAdditionalModules()
    {
        return $this->data->get('additionalModules', []);
    }

    public function __construct($aid = 0, $eid = 0, $data = [])
    {
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid data, it must an array!');
        }
        $this->uuid = create_uuid();
        $this->aid = $aid;
        $this->eid = $eid;
        $this->timestamp = microtime(true);
        $this->data = new Collection($data ?: []);
    }

    /**
     * Create a message from an array.
     *
     * @param array $arr
     * @return static
     */
    public static function fromArray($arr)
    {
        if (!is_array($arr)) {
            throw new \InvalidArgumentException('Invalid data, it must an array!');
        }

        $aid = $arr['aid'] ?? 0;
        $eid = $arr['eid'] ?? 0;
        $timestamp = $arr['timestamp'] ?? 0;
        $lang = $arr['lang'] ?? env('APP_LANGUAGE_DEFAULT', 'zh-CN');
        $data = ArrayGet($arr, 'data', []);
        if (!is_array($data)) {
            $data = [];
        }

        $obj = new static($aid, $eid, $data);
        $obj->setTimestamp($timestamp);
        $obj->setLang($lang);

        if (isset($arr['app_module']) && $arr['app_module']) {
            $obj->setAppModule(unserialize($arr['app_module']));
        }
        return $obj;
    }

    /**
     * Get the account ID.
     *
     * @return int
     */
    public function getAccountId()
    {
        return $this->aid;
    }

    /**
     * Get the Employee ID.
     *
     * @return int
     */
    public function getEmployeeId()
    {
        return $this->eid;
    }

    /**
     * Set the data item.
     *
     * @param string $key
     * @param mixed $value
     * @param bool $override if true, override the item; else skip. Default is true
     * @return $this
     */
    public function set($key, $value, $override = true)
    {
        if ($this->data->has($key) && !$override) {
            return $this;
        }
        $this->data->set($key, $value);
        return $this;
    }

    /**
     * Set items for the data.
     *
     * @param array $pairs
     * @param bool $override if true, it will clear the data before set the items
     */
    public function setPairs($pairs = [], $override = true)
    {
        if ($override) {
            $this->clear();
        }
        foreach ($pairs as $key => $value) {
            $this->set($key, $value);
        }
        return $this;
    }

    /**
     * Set timestamp for the message.
     *
     * @param $timestamp
     */
    public function setTimestamp($timestamp)
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    /**
     * Get the timestamp of the message.
     *
     * @return int
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * Clear the data.
     */
    public function clear()
    {
        $this->data->clear();
        return $this;
    }

    /**
     * Get the array of the data.
     *
     * @return array
     */
    public function all()
    {
        return $this->data->all();
    }

    /**
     * Set the language.
     * 
     * @param string $lang
     * @return $this
     */
    public function setLang($lang)
    {
        // error_log('[QueueMessage] lang: ' . $lang);
        $this->lang = $lang ?: env('APP_LANGUAGE_DEFAULT', 'zh-CN');
        return $this;
    }

    /**
     * Get the language.
     * 
     * @return string
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return json_encode([
            'uuid' => $this->uuid,
            'aid' => $this->aid,
            'eid' => $this->eid,
            'timestamp' => $this->timestamp,
            'lang' => $this->lang,
            'data' => $this->all(),
            // 'app_module' => serialize($this->appModule),
            'aditionalModules' => $this->additionalModules,
        ]);
    }
}