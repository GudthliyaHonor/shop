<?php

namespace Key\Abstracts;

use Key\Database\Mongodb;

abstract class BaseVersionedModel extends BaseModel
{


    /**
     * Get DB collection name for version object.
     * @return string
     */
    abstract protected function getVersionedCollectionName();

    protected function getAid()
    {
        return $this->app && $this->app->offsetExists('__CURRENT_ACCOUNT_ID__') ? $this->app['__CURRENT_ACCOUNT_ID__'] : 0;
    }

    protected function getEid()
    {
        return $this->app && $this->app->offsetExists('__CURRENT_EMPLOYEE_ID__') ? $this->app['__CURRENT_EMPLOYEE_ID__'] : 0;
    }

    /**
     * Inserting a versioned object.
     * @return boolean
     */
    public function insertVersionedObject(BaseVersionRecord $record)
    {
        $info = $this->handleInsertVersionedObject($record);
        $result = $this->saveVersionedObject($info);
        if ($result) {
            $this->setActiveVersion($record);
            return true;
        }
        return false;
    }

    public function handleInsertVersionedObject(BaseVersionRecord $record)
    {
        return $record->toArray(true);
    }

    /**
     * Insert a versioned object to DB.
     * @param array $info object info
     * @return boolean
     */
    protected function saveVersionedObject(array $info)
    {
        $collName = $this->getVersionedCollectionName();
        $info['aid'] = $this->getAid();
        $info['status'] = 1;
        $info['created'] = Mongodb::getMongoDate();
        $info['created_by'] = $this->getEid();
        return $this->getMongoMasterConnection()->insert($collName, $info);
    }

    protected function setActiveVersion(BaseVersionRecord $record)
    {
        $collName = $this->getVersionedCollectionName();
        $cond = [
            'VCODE' => $record->getData('VCODE'),
            'status' => 1,
            'VID' => [
                '$ne' => $record->getData('VID'),
            ],
        ];
        $this->getMongoMasterConnection()->update($collName, $cond, [
            '$set' => [
                'VC' => 0,
                'updated' => Mongodb::getMongoDate(),
                'updated_by' => $this->getEid(),
            ]
        ]);
    }

    /**
     * Get versioned list condition for filtering.
     * @param string $versionCode
     * @param string $keyword
     * @return array
     */
    protected function getVersionedListCond($versionCode, $keyword = null)
    {
        $cond = [
            'aid' => $this->getAid(),
            'status' => 1,
            'VCODE' => $versionCode,
        ];
        if ($keyword) {
            $cond['VN'] = ['$regex' => $keyword, '$options' => 'i'];
        }
        return $cond;
    }

    /**
     * Get the list of versioned object.
     * @param string $versionCode Version code
     * @param int $page
     * @param int $pageSize
     * @return array|false
     */
    public function getVersionedList($versionCode, $keyword = null, $page = 0, $pageSize = 10)
    {
        $collName = $this->getVersionedCollectionName();
        $cond = $this->getVersionedListCond($versionCode, $keyword);
        return $this->getMongoMasterConnection()->fetchAll($collName, $cond, $page * $pageSize, $pageSize, ['created' => -1]);
    }

    public function getVersionedCount($versionCode, $keyword = null)
    {
        $collName = $this->getVersionedCollectionName();
        $cond = $this->getVersionedListCond($versionCode, $keyword);
        return $this->getMongoMasterConnection()->count($collName, $cond);
    }
}

