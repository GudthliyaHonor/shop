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


use Exception;
use InvalidArgumentException;
use Key\Database\MongoManager;
use Key\Exception\AppException;

/**
 * Provides the useful methods, such retrieves auto-increment id.
 *
 * Create sequence collection:
 * <code>
 * db.createCollection("sequence")
 * db.sequence.ensureIndex({"name": 1})
 * </code>
 *
 * Sample data:
 * <code>
 * db.sequence.insert({"name": "seqName", "seq": 1000})
 * </code>
 *
 * @package App\Models
 * @author lgh<liguanghui2006@163.com>
 */
class Sequence
{

    public static $app;

    /**
     * Get the auto-increment id for collection.
     *
     * @param string $name Sequence name, such as 'organization'.
     * @param int $step Increment step
     * @param int $from
     * @return bool|int
     * @throws InvalidArgumentException
     * @throws AppException
     */
    public static function getId($name, $step = 1, $from = 1000)
    {
        if (!is_string($name) || empty($name)) {
            throw new InvalidArgumentException('Argument name is required and must be a string.');
        }
        if (!is_int($step) || $step <= 0) {
            throw new InvalidArgumentException(sprintf('Invalid step %s, it must an integer and greater than 0.', $step));
        }

        $message = sprintf('Can not retrieve the seq from `%s`', $name);
        try {
            /** @var \Key\Database\Mongodb $instantce */
            $instance = static::$app['mongodb'];
            // static::$app['logger']->debug(sprintf('[getId] %s', $instance->getUrl()));

            //$command = 'db.runCommand({"findAndModify": "'.Constants::COLL_SEQUENCE.'", "query": {"name": "'.$name.'"}, "update": {"$inc": {"seq": '.$step.'}}})';
            $command = array(
                'findAndModify' => Constants::COLL_SEQUENCE,
                'query' => array(
                    'name' => $name
                ),
                'update' => array(
                    '$inc' => array(
                        'seq' => $step
                    )
                ),
                'fields' => array(
                    'seq' => 1
                ),
                'new' => true,
                'upsert' => true
            );
            $result = $instance->execute($command);
            if ($result && count($result) && $result[0]['ok'] == 1 && isset($result[0]['value']['seq'])) {
                return (int) $result[0]['value']['seq'] + (int) $from;
            }
        } catch (Exception $ex) {
            //...
            $message = $ex->getMessage();
        }

        throw new AppException($message);
    }

    /**
     * @param string $name Sequence name
     * @param int $pad_length If the value of pad_length is negative, less than, or equal to the length of the input
     *                        string, no padding takes place, and input will be returned
     * @param int $step Increment step
     * @return string
     * @throws AppException
     */
    public static function getDatedId($name, $pad_length = 8, $step = 1)
    {
        if (!is_int($pad_length) || $pad_length <= 0) {
            throw new InvalidArgumentException(sprintf('Invalid step %s, it must an integer and greater than 0.', $pad_length));
        }

        $seq = static::getId($name, $step, 0);
        if ($seq) {
            return sprintf('%s%s', date('Ymd'), str_pad($seq, $pad_length, '0', STR_PAD_LEFT));
        }
    }

    /**
     * Get separate id each by account id.
     * For example:
     * <pre>
     *   Sequence::getSeparateId('name1', 1); // return 10000
     * </pre>
     *
     * @param string $name Sequence id
     * @param int $aid Account id
     * @param int $step Increment step
     * @param int $from From the number
     * @return int
     * @throws AppException
     * @throws \Key\Exception\DatabaseException
     */
    public static function getSeparateId($name, $aid = 0, $step = 1, $from = 10000)
    {
        if (empty($name)) {
            throw new InvalidArgumentException('Sequence name are required');
        }

        /** @var \Key\Database\Mongodb $instantce */
        $instance = static::$app['mongodb'];
        // static::$app['logger']->debug(sprintf('[getSeparateId] %s - %s', $aid, $instance->getUrl()));

        $command = array(
            'findAndModify' => Constants::COLL_SEQUENCE,
            'query' => array(
                'name' => $name,
                'aid' => $aid
            ),
            'update' => array(
                '$inc' => array(
                    'seq' => $step
                )
            ),
            'fields' => array(
                'seq' => 1
            ),
            'new' => true,
            'upsert' => true
        );

        $result = $instance->execute($command);
        if ($result && count($result) && $result[0]['ok'] == 1 && isset($result[0]['value']['seq'])) {
            return (int) $result[0]['value']['seq'] + (int) $from;
        }

        throw new AppException('Exception when get the sequence');
    }

    public static function setSeqId($seq, $name, $aid = 0)
    {
        if (empty($name)) {
            throw new InvalidArgumentException('Sequence name are required');
        }
        /** @var \Key\Database\Mongodb $instantce */
        $instance = static::$app['mongodb'];
        $cond = [
            'name' => $name,
        ];
        if ($aid) {
            $cond['aid'] = $aid;
        }
        $instance->update(Constants::COLL_SEQUENCE, $cond, [
            '$set' => [
                'seq' => $seq,
            ]
        ]);
    }
}