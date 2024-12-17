<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2021 yidianzhishi.cn
 * @version 1.0.0
 * @link http://yidianzhishi.cn
 */
namespace Key\Inputs;


class RegexKeywordInput extends StringInput
{

    const FILTER_REGEX = '[\*|\.|\?|\+|\$|\^|\[|\]|\(|\)|\{|\}|\||\\|\/]';

    public static function filter($pattern)
    {
        return preg_replace_callback(self::FILTER_REGEX, function($match) {
            return '\\' . $match[0];
        }, $pattern);
    }

    public function getValidValue()
    {
        $value = parent::getValidValue();
        if ($value) {
            return self::filter($value);
        }
        return $value;
    }
}