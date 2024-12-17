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

use function AlibabaCloud\Client\json;

class Translator
{
    /**
     * The loader implementation.
     *
     * @var LoaderInterface
     */
    protected $loader;

    /**
     * The default locale.
     *
     * @var string
     */
    protected $locale;

    /**
     * @var
     */
    protected $loaded = [];

    /**
     * Create a new translator instance.
     *
     * @param LoaderInterface $loader
     * @param string $locale
     */
    public function __construct(LoaderInterface $loader, $locale)
    {
        $this->loader = $loader;
        $this->locale = $locale;
    }

    /**
     * Loaded the specified language group.
     *
     * @param string $locale
     */
    public function load($locale)
    {
        if ($this->isLoad($locale)) {
            return;
        }

        $lines = $this->loader->load($locale);
        $this->loaded[$locale] = $lines;
    }

    /**
     * Determine if the given group has been loaded.
     *
     * @param string $locale
     * @return bool
     */
    public function isLoad($locale)
    {
        return isset($this->loaded[$locale]);
    }

    public function get($key, array $replace = [], $locale = null)
    {
        // error_log('key: ' . $key . ' -- ' . $this->locale);
        $locale = $locale ?: $this->locale;
        $this->load($locale);
        // error_log('loaded: ' . json_encode($this->loaded));
        if ($this->loaded && isset($this->loaded[$locale]) && isset($this->loaded[$locale][$key])) {
            return $this->parse($this->loaded[$locale][$key], $replace);
        }
        return null;
    }

    protected function parse($line, $replace = []) {
        if (is_string($line)) {
            return vsprintf($line, $replace);
        } elseif (is_array($line) && count($line) > 0) {
            return $line;
        }

        return null;
    }

    /**
     * Set the default locale.
     *
     * @param $locale
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * Get the default locale being used.
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

}