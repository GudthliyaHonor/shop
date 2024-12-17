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


class ArrayLoader implements LoaderInterface
{
    /**
     * All the translation messages.
     *
     * @var array
     */
    protected $messages = [];

    /**
     * Load the message for the given locale.
     *
     * @param string $locale
     * @return array
     */
    public function load($locale)
    {
        if (isset($this->messages[$locale])) {
            return $this->messages[$locale];
        }

        return [];
    }

    public function setMessages($locale, array $messages)
    {
        $this->messages[$locale] = array_merge($this->messages[$locale] ?? [], $messages);

        return $this;
    }

    public function addMessage($locale, array $message)
    {
        isset($this->messages[$locale]) ?: $this->messages[$locale] = [];

        $this->messages[$locale] = array_merge($this->messages[$locale], (array) $message);
        return $this;
    }
}