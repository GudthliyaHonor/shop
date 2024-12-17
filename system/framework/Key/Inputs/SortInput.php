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


/**
 * Class Pagination
 *
 * @package Key\Data
 * @author Guanghui Li <liguanghui2006@163.com>
 */
class SortInput extends StringInput
{

    const MAX_EXP_LIMIT = 3;

    const SORT_REGEXP = '#^([\+|\-]?)([a-zA-Z0-9_\.]+)$#';

    public static $banStrings = array('{', '}', '$ne', '$gte', '$gt', '$lt', '$lte', '$in', '$nin', '$exists', '$where', 'tojson', '==', 'db.');

    /**
     * Check if the name is safe for MongoDB script.
     * @param string $name field name
     * @return bool
     */
    public static function checkIsSafeStr($name)
    {
        return !in_array(strtolower($name), static::$banStrings);
    }

    protected function isMapped($name)
    {
        $map = $this->getMap();
        //error_log('[Sort Record]>>>>>>>>>>>>>>>>>>>map'.var_export($map, true));
        if ($map && is_array($map)) {
            return in_array($name, $map);
        }
        return true;
    }

    protected function parse()
    {
        $validValues = array();
        if ($this->value) {
            $pieces = explode(',', $this->value, self::MAX_EXP_LIMIT);
            if ($pieces) {
                foreach($pieces as $piece) {
                    $piece = trim($piece);
                    if (self::checkIsSafeStr($piece)) {
                        if (preg_match(self::SORT_REGEXP, $piece, $matches)) {
                            ///error_log('[Sort Record]>>>>>>>>>>>>>>>>>>>'.var_export($matches, true));
                            if (!$this->isMapped($matches[2])) {
                                error_log('[Sort Record]Invalid sort in Map: ' . $matches[2]);
                                return [];
                            }
                            switch($matches[1]) {
                                case '-':
                                    $validValues[$matches[2]] = -1;
                                    break;
                                default: // + or ''
                                    $validValues[$matches[2]] = 1;
                            }
                        }
                    } else {
                        error_log('[Sort Record]Invalid sort: ' . $piece);
                    }
                }
            }
        }

        return $validValues;
    }

    /**
     * Get valid value for the input.
     *
     * @return mixed|null
     */
    public function getValidValue()
    {
        if (parent::validate() === Input::VALID_CODE_SUCCESS) {
            return $this->parse();
        }

        return null;
    }

    /**
     * The __toString method allows a class to decide how it will react when it is converted to a string.
     *
     * @return string
     * @link http://php.net/manual/en/language.oop5.magic.php#language.oop5.magic.tostring
     */
    function __toString()
    {
        return (string) $this->value;
    }

}
