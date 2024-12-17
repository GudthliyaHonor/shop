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


use Key\Exception\AppException;
use Key\Inputs\InputFactory;

class SubCollection extends Collection
{
    const NAME_REGEX = '#^(?P<name>[a-zA-Z0-9_]+)(?P<chip>\[\S*\])?$#';

    protected $parent;

    protected $properties;

    protected $isArray = false;

    /**
     * SubCollection constructor.
     * @param string $parent Parent document name
     * @param int $properties The properties defined in parent
     * @param int $uid
     * @param bool $aid
     * @param bool $load_sub_fields
     * @throws AppException
     */
    public function __construct($parent, $properties, $uid, $aid, $load_sub_fields = false)
    {
        $this->parent = $parent;
        $this->properties = $properties;

        $this->uid = $uid;
        $this->aid = $aid;

        if (isset($properties['subtype']) && $subTemplate = $properties['subtype']) {
            preg_match(static::NAME_REGEX, $subTemplate, $matches);
            if ($matches && count($matches) > 1) {
                $this->name = lcfirst($matches['name']);
                if (isset($matches['chip'])) {
                    $this->isArray = true;
                }
            }
        } else {
            throw new AppException('Invalid sub document definition.');
        }

        if (!InputFactory::isBaseType($this->name)) {
            parent::load();
        }
    }

}