<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2016 keylogic.com
 * @version 0.1.0
 * @link http://www.keylogic.com
 */
namespace Key\Translation;


interface LoaderInterface
{
    /**
     * Load the message for the given locale.
     *
     * @param string $locale
     * @return array
     */
    public function load($locale);
}