<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */
namespace Key\Database;


use Key\Database\Mongodb;
use InvalidArgumentException;
use Key\Exception\DatabaseException;

/**
 * Class DatabaseManager
 * @package Key\Database
 */
class DatabaseManager
{
    const VA_ID_KEY = '_VA_ID_';

    const TABLE_AREA = 'global_area';
    const CONFIG_GLOBAL = 'GLOBAL';
    const CONFIG_GLOBAL_SLAVE = 'GLOBAL_SLAVE';

    protected static $instances = array();

    protected static $areaId = 0;

    /**
     * Can not use normal construct
     */
    private function __construct() {}

    /**
     * Disable clone.
     */
    public function __clone()
    {
        trigger_error('DatabaseManager can not be clone', E_USER_ERROR);
    }

    /**
     * Get Database connection instance.
     *
     * @param string $conn Connection name.
     *
     * @return \Key\Abstracts\Database
     *
     * @throws DatabaseException
     */
    public static function getInstance($conn = 'slave')
    {
        global $CONFIG;

        if (!isset(static::$instances[$conn])) {
            if (isset($CONFIG->dbConfig[$conn]) && $config = $CONFIG->dbConfig[$conn]) {

                if (!is_array($config)) {
                    throw new DatabaseException('Invalid database configuration: '.var_export($config, true));
                }

                $dbType = isset($config['type']) && $config['type'] ? $config['type'] : static::DEFAULT_DB_TYPE;

                $instance = null;
                switch ($dbType) {
                    case 'mongo':
                    case 'oracle':
                    case 'sqlserver':
                        throw new DatabaseException(sprintf('Un-implementation database type: %s', $dbType));
                        break;
                    case 'mysql':
                        $instance = static::getMysqlInstance($config);
                        break;
                    default:
                        throw new DatabaseException(sprintf('Un-supported database type: %s', $dbType));
                }

                static::$instances[$conn] = $instance;

            } else {
                throw new DatabaseException('No database instance found: '.$conn);
            }
        }

        return static::$instances[$conn];
    }

    /**
     * Get Mysql database instance.
     *
     * @param array $config
     *
     * @return MySql
     */
    protected static function getMysqlInstance($config)
    {
        $host = isset($config['host']) ? $config['host'] : null;
        $dbname = isset($config['dbname']) ? $config['dbname'] : null;
        $username = isset($config['username']) ? $config['username'] : null;
        $password = isset($config['password']) ? $config['password'] : null;
        $charset = isset($config['charset']) ? $config['charset'] : 'utf8';

        return new MySql($host, $dbname, $username, $password, $charset);
    }

    protected static function getMongoInstance($config)
    {
        // TODO:...
    }

    protected static function getOracleInstance($config)
    {
        // TODO:...
    }

    protected static function getMsSqlInstance($config)
    {
        // TODO:...
    }

    public static function getInstanceByName($name = 'default', $client = 'mongodb', $config = [])
    {
        if (isset(self::$instances[$name])) {
            //error_log('Name exists: ' . $name);
            return self::$instances[$name];
        }

        //error_log('Name not exists: ' . $name);
        switch ($client) {
            case 'mysql':
            break;
            case 'mongodb':
            $uri = self::generateMongodbUri($config);
            self::$instances[$name] = new Mongodb($uri, $config['database']);
            break;
        }

        return self::$instances[$name];
    }

    protected static function generateMongodbUri($config)
    {
        if (isset($config['uri']) && $config['uri']) {
            return $config['uri'];
        }

        $host = $config['host'];
        $port = $config['port'];
        $username = $config['username'];
        $password = $config['password'];

        if ($username) {
            return "mongodb://{$username}:{$password}@{$host}:{$port}/{$db}";
        } else {
            return "mongodb://{$host}:{$port}/{$db}";
        }
    }

}
