<?php

namespace Key\Log;

use LoggerAppender;
use LoggerLoggingEvent;
use MongoDB\Driver\BulkWrite;
use Key\Database\Mongodb;
use MongoDB\Driver\WriteConcern;
use MongoDB\Driver\Exception\BulkWriteException;
use MongoDB\BSON\UTCDateTime;

/**
 * Created by PhpStorm.
 * User: tianshikun a88wangtian@163.com
 * Date: 2016/10/24
 * Time: 9:44
 */
class LoggerAppenderMongoDBCustom extends LoggerAppender
{

    // ******************************************
    // ** Constants                            **
    // ******************************************

    const DEFAULT_MONGODB_URL_PREFIX = 'mongodb://';
    const DEFAULT_MONGODB_HOST = 'localhost';
    const DEFAULT_MONGODB_PORT = '27017';
    const DEFAULT_MONGODB_DB_NAME = 'log4php_mongodb';
    const DEFAULT_MONGODB_COLLECTION_NAME = 'logs';
    const DEFAULT_TIMEOUT_VALUE = '3000';

    // *******************************************
    // **Configurable parameters
    // *******************************************

    private $host;
    private $port;
    private $databaseName;
    private $collectionName;
    private $timeout;

    protected $collection;
    protected $manager;

    public function __construct($name)
    {
        parent::__construct($name);
        $this->host = self::DEFAULT_MONGODB_HOST;
        $this->port = self::DEFAULT_MONGODB_PORT;
        $this->databaseName = self::DEFAULT_MONGODB_DB_NAME;
        $this->collectionName = self::DEFAULT_MONGODB_COLLECTION_NAME;
        $this->timeout = self::DEFAULT_TIMEOUT_VALUE;
    }

    /**
     * Setup db connection.
     * Based on defined options, this method connects to the database and
     * creates a {@link $collection}.
     */
    public function activateOptions()
    {
        $this->collection = new Mongodb($this->host,$this->databaseName);
    }

    /**
     * Appends a new event to the mongo database.
     *
     * @param LoggerLoggingEvent $event
     */
    public function append(LoggerLoggingEvent $event)
    {
        try {
            if ($this->collection != null) {
                $bulk = new BulkWrite();
                $value = $this->format($event);
                if(isset($value['className'])){
                    $bulk->insert($value);
                }else{
                    foreach ($value as $key => $item) {
                        $bulk->insert($item);
                    }
                }
                $writeConcern = new WriteConcern(WriteConcern::MAJORITY, 1000);
                $this->collection->getServer()->executeBulkWrite($this->databaseName . '.' . $this->collectionName, $bulk, $writeConcern);
            }
        } catch (BulkWriteException $ex) {
            $this->warn(sprintf('Error while writing to mongo collection: %s', $ex->getMessage()));
        }
    }

    /**
     * Converts the logging event into an array which can be logged to mongodb.
     *
     * @param LoggerLoggingEvent $event
     * @return array The array representation of the logging event.
     */
    protected function format(LoggerLoggingEvent $event)
    {
        $timestampSec = (int)$event->getTimestamp() * 1000;
        $document = array(
            'timestamp' => new UTCDateTime($timestampSec),
            'level' => $event->getLevel()->toString(),
            'thread' => (int)$event->getThreadName(),
            'loggerName' => $event->getLoggerName()
        );
        $locationInfo = $event->getLocationInformation();
        if ($locationInfo != null) {
            $document['fileName'] = $locationInfo->getFileName();
            $document['method'] = $locationInfo->getMethodName();
            $document['lineNumber'] = ($locationInfo->getLineNumber() == 'NA') ? 'NA' : (int)$locationInfo->getLineNumber();
            $document['className'] = $locationInfo->getClassName();
        }
        $throwableInfo = $event->getThrowableInformation();
        if ($throwableInfo != null) {
            $document['exception'] = $this->formatThrowable($throwableInfo->getThrowable());
        }
        $message = $event->getMessage();
        if(isset($message['className'])){
            $document['connected_id'] = isset($message['connected_id']) ? $message['connected_id'] : null;
            $document['uid'] = isset($message['uid']) ? $message['uid'] : null;
            $document['aid'] = isset($message['uid']) ? $message['aid'] : null;
            $document['fileName'] = isset($message['fileName']) ? $message['fileName'] : $document['fileName'];
            $document['method'] = isset($message['method']) ? $message['method'] : $document['method'];
            $document['lineNumber'] = isset($message['lineNumber']) ? $message['lineNumber'] : $document['lineNumber'];
            $document['className'] = isset($message['className']) ? $message['className'] : $document['className'];
            $document['collection'] = isset($message['collection']) ? $message['collection'] : null;
            $document['condition'] = isset($message['condition']) ? json_encode($message['condition']) : null;
            $document['data'] = isset($message['data']) ? json_encode($message['data']) : null;
            return $document;
        }
        $new_document = array();
        foreach ($message as $key => $value) {
            $document['connected_id'] = isset($value['connected_id']) ? $value['connected_id'] : null;
            $document['uid'] = isset($value['uid']) ? $value['uid'] : null;
            $document['aid'] = isset($value['uid']) ? $value['aid'] : null;
            $document['fileName'] = isset($value['fileName']) ? $value['fileName'] : $document['fileName'];
            $document['method'] = isset($value['method']) ? $value['method'] : $document['method'];
            $document['lineNumber'] = isset($value['lineNumber']) ? $value['lineNumber'] : $document['lineNumber'];
            $document['className'] = isset($value['className']) ? $value['className'] : $document['className'];
            $document['collection'] = isset($value['collection']) ? $value['collection'] : null;
            $document['condition'] = isset($value['condition']) ? json_encode($value['condition']) : null;
            $document['data'] = isset($value['data']) ? json_encode($message['data']) : null;
            $new_document[$key] = $document;
        }
        return $new_document;
    }

    /**
     * @param string $host
     */
    public function setHost($host)
    {
        $this->host = $host;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @param $port
     */
    public function setPort($port)
    {
        $this->port = $port;
    }

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @param string $databaseName
     */
    public function setDatabaseName($databaseName)
    {
        $this->databaseName = $databaseName;
    }

    /**
     * @return string
     */
    public function getDatabaseName()
    {
        return $this->databaseName;
    }

    /**
     * @param string $collectionName
     */
    public function setCollectionName($collectionName)
    {
        $this->collectionName = $collectionName;
    }

    /**
     * @return string
     */
    public function getCollectionName()
    {
        return $this->collectionName;
    }

    /**
     * @param string $timeout
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;
    }

    /**
     * @return string
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * @param boolean $closed
     */
    public function setClosed($closed)
    {
        $this->closed = $closed;
    }
}
