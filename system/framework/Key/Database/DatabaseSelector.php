<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2019 keylogic.com
 * @version 1.0.0
 * @link http://www.keylogic.com
 */

namespace Key\Database;


use ArrayIterator;

class DatabaseSelector extends ArrayIterator
{

    protected $app;

    public function __construct($value = [], $app)
    {
        parent::__construct($value);
        $this->app = $app;
    }

    public function offsetGet($offset)
    {
        //error_log('DatabaseSelector>>>>>>>>>>>' . var_export($offset, true));

        if (isset($this->app['config']['database.' . $offset])) {
            $name = $this->app['config']['database.' . $offset];

            //error_log('######name######' . $name);

            $conn = explode('.', $name);

            $conns = isset($this->app['config']['database.connections']) ? $this->app['config']['database.connections'] : null;

            if ($conns) {
                if (count($conn) == 1) { // such as 'mongodb'
                    $defaultConn = isset($conns[$conn[0]]['default']) ? $conns[$conn[0]]['default'] : null;
                    if ($defaultConn) {

                    } else {
                        throw new \Exception('[DatabaseSelector] No default connection configure found for ' . $name);
                    }
                } elseif (count($conn) == 2) { // such as 'mongodb.global'
                    $client = isset($conns[$conn[0]]['client']) ? $conns[$conn[0]]['client'] : null;
                    $myConn = isset($conns[$conn[0]][$conn[1]]) ? $conns[$conn[0]][$conn[1]] : null;
                    if ($myConn) {
                        return DatabaseManager::getInstanceByName($name, $client, $myConn);
                    } else {
                        throw new \Exception('[DatabaseSelector] No connection configure found for ' . $name);
                    }
                }
            } else {
                throw new \Exception('[DatabaseSelector] No connections configure set');
            }
        } else {
            throw new \Exception('[DatabaseSelector] No DB configure set');
        }

        return null;
    }
}
