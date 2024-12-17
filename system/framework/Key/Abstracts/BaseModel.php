<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */
namespace Key\Abstracts;


use MongoId;
use Exception;
use Key\Cache\CacheManager;
use Key\Constants;
use Key\Database\DatabaseManager;
use Key\Database\MongoManager;
use MongoDB\BSON\UTCDateTime;
use Pimple\Container;

/**
 * Class BaseModel
 *
 * @package Key\Abstracts
 * @author Guanghui Li <liguanghui2006@163.com>
 */
abstract class BaseModel
{

    const ENABLED = Constants::STATUS_ENABLED;
    const DISABLED = Constants::STATUS_DISABLED;

    /** @var Container $app */
    protected $app;

    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    /**
     * Get global db connection.
     *
     * @return \Key\Database\Mongodb
     */
    protected function getGlobalDBConnection()
    {
        return $this->app['db']['global'];
    }

    protected function closeGlobalDBConnection()
    {
        $this->app['db']['global'] = null;
    }

    /**
     * Get mongodb instance.
     *
     * @return \Key\Database\Mongodb
     * @throws \Key\Exception\DatabaseException
     */
    protected function getMongoMasterConnection()
    {
        if ($this->app['mongodb'] && $this->app->offsetExists('__CURRENT_ACCOUNT_ID__') && $this->app['__CURRENT_ACCOUNT_ID__'] == 1006) {
            error_log('### db uri: ' . $this->app['mongodb']->getUrl() . ' - ' . json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)));
        }
        return $this->app['mongodb'];
    }

    /**
     * Get cache object.
     *
     * @return \Key\Interfaces\Cache|\Key\Cache\Redis
     */
    protected function getCacheInstance($index = 0)
    {
        /** @var \Key\Cache\Redis $cache */
        $cache = $this->app['cache'];
        $result = $cache->select($index ?: env('REDIS_DATABASE', 0));
        if (!$result) {
            error_log('[getCacheInstance] set db index fail');
        }
        return $cache;
    }

    /**
     * Get session redis client.
     * @return \Redis
     */
    protected function getSessionCacheInstance()
    {
        return $this->app['session.client'];
    }

    /**
     * Get MySQL instance.
     *
     * @return \Key\Database\MySql
     */
    protected function getMySQLMasterConnection()
    {
        return $this->app['mysql'];
    }
}