<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2022 yidianzhishi.com
 * @version 1.0.0
 * @link https://www.yidianzhishi.com
 */

namespace Key\Middleware;


use Closure;
use Composer\Autoload\ClassLoader;
use Pimple\Container;
use Key\Abstracts\Middleware;
use Key\Exception\AppException;
use Key\Translation\TranslationServiceProvider;
use Key\Translation\Translator;

use function AlibabaCloud\Client\json;

/**
 * Middleware Config
 *
 * @package Key\Middleware
 * @author Guanghui Li <liguanghui2006@163.com>
 */
class Locale extends Middleware
{
    /**
     * @param Container $container
     * @param Closure $next
     * @return mixed
     * @throws AppException
     */
    public function __invoke(Container $container, Closure $next)
    {
        $provider = new TranslationServiceProvider();
        $provider->register($container);

        if ($container->offsetExists('locale')) {
            $locale = $container['locale'];
        }
        else {
            $locale = $container['config']['global.locale'];
            $container['locale'] = $locale;
        }

        /** @var \Key\Translation\Translator $translator */
        $translator = $container['translator'];

        // TODO: language cache

        $localeFile = dirname(dirname(__FILE__)) . '/Locales/' . $locale . '.php';
        if (file_exists($localeFile)) {
            // $locale = $container['locale'];
            $messages = include($localeFile);
            $container['translation.loader']->setMessages($locale, $messages);
        }
        if ($container->offsetExists('modules')) {
            /** @var \Key\Foundation\Module[] $modules */
            $modules = $container['modules'];
            foreach ($modules as $module) {
                $langDir = $module->getLanguageFolder();
                $langFile = $langDir . DS . $locale . '.php';
                if (file_exists($langFile)) {
                    $messages = include($langFile);
                    $container['translation.loader']->setMessages($locale, $messages);
                }
            }
        }
        $translator->load($locale);

        return $next($container);
    }
}