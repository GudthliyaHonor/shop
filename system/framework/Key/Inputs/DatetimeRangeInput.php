<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */
namespace Key\Inputs;


use InvalidArgumentException;
use Key\Exception\AppException;
use MongoDB\BSON\UTCDateTime;

/**
 * Class ArrayInput
 * @package Key\Inputs
 */
class DatetimeRangeInput extends ArrayInput
{
    const PATTERN_DEFAULT = 'Y-m-d';

    const FORMAT_DEFAULT = 'timestamp';
    const FORMAT_DATETIME = 'datetime';
    const FORMAT_UTC = 'utc';

    protected function fromPasedDatetime($datetime)
    {

    }

    public function getFormat()
    {
        return $this->get('format') ?: self::FORMAT_DEFAULT;
    }

    /**
     * Format the value for controller.
     * @param string $value Date string, such as '2020-01-01'
     * @return \DateTime|false|int|UTCDateTime
     */
    protected function formatValue($value)
    {
        $timestamp = strtotime($value);
        switch ($this->getFormat()) {
            case self::FORMAT_DATETIME:
                return (new \DateTime())->setTimestamp($timestamp);
                break;
            case self::FORMAT_UTC:
                return new UTCDateTime($timestamp * 1000);
                break;
            case self::FORMAT_DEFAULT:
            default:
                return $timestamp;
        }
    }

    /**
     * Format validation for the value.
     *
     * @return int
     */
    protected function formatValidate()
    {
        $pattern = $this->getPattern();
        if (!$pattern) $pattern = self::PATTERN_DEFAULT;
        if (is_array($this->value)) {
            foreach ($this->value as $idx => $value) {
                $startParsed = date_parse_from_format($pattern, $value);
                if (!$startParsed || $startParsed['error_count']) {
                    return static::INVALID_CODE_FORMAT;
                }
//                $this->value[$idx] = $this->formatValue($value);
            }
        }
        return static::VALID_CODE_SUCCESS;
    }

    /**
     * Validate the value for input.
     *
     * @return int validation result code.
     * @throws AppException
     */
    public function validate()
    {

        if ($this->value) {
            if (is_array($this->value)) {
                $start = $this->value[0] ?? null;
                $end = $this->value[1] ?? null;
                if (!$start && !$end) {
                    if ($this->isRequired()) {
                        $this->validatedCode = static::INVALID_CODE_REQUIRED;
                        return static::INVALID_CODE_REQUIRED;
                    }
                } else {
                    $value = [];
                    if ($pattern = $this->getPattern()) {
                        if ($start) {
                            $startParsed = date_parse_from_format($pattern, $start);
                            if (!$startParsed || $startParsed['error_count']) {
                                $this->validatedCode = static::INVALID_CODE_FORMAT;
                                return static::INVALID_CODE_FORMAT;
                            }
                            $value[] = strtotime($startParsed['year'] .'-' . $startParsed['month'] . '-' . $startParsed['day'] . ' ' . ($startParsed['hour'] ?: '00') . ':' . ($startParsed['minute'] ?: '00') . ':'. ($startParsed['second'] ?: '00'));
                        } else {
                            $value[] = null;
                        }
                        if ($end) {
                            $endParsed = date_parse_from_format($pattern, $end);
                            if (!$endParsed || $endParsed['error_count']) {
                                $this->validatedCode = static::INVALID_CODE_FORMAT;
                                return static::INVALID_CODE_FORMAT;
                            }
                            $value[] = strtotime($endParsed['year'] .'-' . $endParsed['month'] . '-' . $endParsed['day'] . ' ' . ($endParsed['hour'] ?: '00') . ':' . ($endParsed['minute'] ?: '00') . ':'. ($endParsed['second'] ?: '00'));
                        } else {
                            $value[] = null;
                        }
                        // Start time can NOT earlier than end time
                        if ($start && $end && $start > $end) {
                            $this->validatedCode = static::INVALID_CODE_FORMAT;
                            return static::INVALID_CODE_FORMAT;
                        }
                    } else {
                        error_log('no pattern set');
                    }
                    $this->value = $value;
                }
            }
        }
        $this->validatedCode = static::VALID_CODE_SUCCESS;
        return static::VALID_CODE_SUCCESS;
    }

}