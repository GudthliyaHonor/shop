<?php
/**
 * Created by PhpStorm.
 * User: a88wa
 * Date: 2017/1/22
 * Time: 14:04
 */

namespace App\Transfers;


class ArrayTransfer extends Transfer
{

    public function convert()
    {
        return $this->value;
    }

    /**
     * Convert data to output.
     *
     * @return mixed
     */
    public function output()
    {
        return $this->value;
    }

    public function validate(&$valid_value)
    {
        $this->value = $valid_value;
        if (is_array($valid_value)) {
            return self::VALIDATED_SUCCESS;
        } else {
            $value = str_replace('&quot;', '"', $valid_value);
            $value = json_decode($value, true);
            if (is_array($value)) {
                return self::VALIDATED_SUCCESS;
            }
        }
        return self::VALIDATED_FAILURE;
    }
}