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


class FieldResources
{
    protected $uid;
    protected $aid;

    /** @var \App\Collections\FieldResource[] */
    protected $resources = array();

    public function __construct($resources = array(), $uid = 0, $aid = 0)
    {
        if (!is_array($resources)) {
            throw new \InvalidArgumentException('Invalid relationships parameter.');
        }

        foreach ($resources as $resource) {
            if ($resource instanceof FieldResource) {
                $this->resources[$resource->getName()] = $resource;
            } else {
                throw new \InvalidArgumentException('Invalid relationships parameter.');
            }
        }

        $this->uid = $uid;
        $this->aid = $aid;
    }

    public static function parse($data = array(), $uid = 0, $aid = 0)
    {
        $resources = array();
        foreach ($data as $key => $properties) {
            $resources[$key] = FieldResource::parse($key, $properties, $uid, $aid);
        }

        return new static($resources, $uid, $aid);
    }

    /**
     * Get field resource.
     *
     * @param string $name Resoruce name
     * @return FieldResource|null
     */
    public function getResource($name)
    {
        if (isset($this->resources[$name])) {
            return $this->resources[$name];
        }

        return null;
    }
}