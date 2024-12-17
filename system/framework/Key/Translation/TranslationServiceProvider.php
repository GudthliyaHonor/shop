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

use Key\Support\ServiceProvider;
use Pimple\Container;

class TranslationServiceProvider extends ServiceProvider
{

    protected function getCurrentLocale($app)
    {
        /** @var \Key\Http\Request $request */
        $request = $app['request'];
        $languages = explode(',', env('LANGUAGES', 'zh-CN,zh-TW,en,de,es,por'));// $this->app['config']['app.languages'];

        $acceptLanguage = $request->getHeaderLine('Accept-Language');
        if (in_array($acceptLanguage, $languages)) {
            $preferLang = $acceptLanguage;
        }
        else {
            preg_match_all('/([^;|^,]+(,[^;]+)*;q=[^,]+)/', $acceptLanguage, $matched);
            if ($matched) {
                // error_log('matched: ' . json_encode($matched));
                $preferLang = explode(',', explode(';', $matched[0][0])[0])[0];
            }
            else {
                $preferLang = $app['config']['global.locale'];;
            }

            if (!$preferLang || !in_array($preferLang, $languages)) {
                $preferLang = $app['config']['global.locale'];
            }
        }
        return $preferLang;
    }

    /**
     * Registers services on the given container.
     *
     * This method should only be used to configure services and parameters.
     * It should not get services.
     *
     * @param Container $app A container instance
     */
    public function register(Container $app)
    {
        $this->registerLoader($app);

        $app['locale'] = function($app) {
            return $this->getCurrentLocale($app);
        };

        /** @var Translator */
        $app['translator'] = function ($app) {
            $loader = $app['translation.loader'];
            $locale = $app['locale'];

            $translator = new Translator($loader, $locale);

            return $translator;
        };
        
    }

    protected function registerLoader(Container $app)
    {
        $app['translation.loader'] = function ($app) {
            return new ArrayLoader();
        };
    }
}