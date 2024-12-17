<?php
/**
 * App bootstrap.
 */
if (extension_loaded('pinpoint_php')) {
    define('APPLICATION_ID', 'ydzs-base');
    define('APPLICATION_NAME', 'ydzs-learning-base');
    // A writable path for caching AOP code
    define('AOP_CACHE_DIR', '/../../../data/cache/');
    // Your plugins directory: All plugins must have a suffix "Plugin.php",as "CommonPlugin.php mysqlPlugin.php RPCPlugin.php"
    define('PLUGINS_DIR', __DIR__ . '/aop');                      
    // since 0.2.5+ PINPOINT_USE_CACHE = N, auto_pinpointed.php will generate Cache/* on every request. 
    define('PINPOINT_USE_CACHE','YES');
    // if (!function_exists('pinpoint_tracelimit')) {function pinpoint_tracelimit() {}}
    // if (!function_exists('pinpoint_set_context')) {function pinpoint_set_context() {}}
    // if (!function_exists('pinpoint_unique_id')) {function pinpoint_unique_id() {}}
    // if (!function_exists('pinpoint_start_time')) {function pinpoint_start_time() {}}
    // if (!function_exists('pinpoint_start_trace')) {function pinpoint_start_trace() {}}
    // if (!function_exists('pinpoint_end_trace')) {function pinpoint_end_trace() {}}
    // if (!function_exists('pinpoint_add_clue')) {function pinpoint_add_clue() {}}
    // if (!function_exists('pinpoint_add_clues')) {function pinpoint_add_clues() {}}
    // Use pinpoint-php-aop auto_pinpointed.php instead of vendor/autoload.php
    require_once dirname(dirname(dirname(__FILE__))) . '/vendor/pinpoint-apm/pinpoint-php-aop/auto_pinpointed.php';
}


$module = new \Key\Foundation\Module();
$module->setName('base')
    ->setPriority(0)
    ->addClassLoaderDef('App\\', dirname(__FILE__) . DS . 'mc')
    ->addClassPackageDef('App\\', dirname(__FILE__) . DS . 'pkg')
    ->setRecordNSPrefix('App\\Records\\')
    ->setConfigureFile(dirname(__FILE__) . DS . 'config' . DS . 'app.php')
    ->setRoutePath(dirname(__FILE__) . DS . 'config' . DS . 'routes')
    ->setPageFile(dirname(__FILE__) . DS . 'config' . DS . 'pages.php')
    ->setEventFile(dirname(__FILE__) . DS . 'config' . DS . 'events.php')
    ->setLanguageFolder(dirname(__FILE__) . DS . 'languages')
    ->addHook('after_inputs_validation', function ($app, &$params) {

        if (extension_loaded('pinpoint_php')) {
            $aop = \Pinpoint\Common\PinpointDriver::getInstance();
            $aop->start();
        }

        // Skip-check config
        if (env('HOOK_SKIP_AFTER_INPUTS_VALIDATION', 0)) return;

        /** @var \Key\Http\Request $request */
        $request = $app['request'];
        $viewModule = $request->getParam('__view_module__');
        $statePath = $request->getParam('__state_path__');

        $platform = $request->getHeaderLine('App-Platform');
        if ($platform && in_array($platform, explode(',', env('HOOK_SKIP_ATER_INPUTS_VALIDATION_PLATFORMS', 'Android,iOS')))) {
            return true;
        }

        if (isset($params['without_acl']) && $params['without_acl']) {
            $model = new \App\Models\Permission\AuthGrant($app);

            if ($viewModule) {
                $result = $model->requestAuth($viewModule);
                if ($result === true || $result['state'] == \App\Models\Permission\AuthGrant::STATE_APPROVED) {
                    return true;
                }
            }
            elseif ($statePath) {
                $result = $model->requestAuthByPath($statePath);
                if ($result === true || $result['state'] == \App\Models\Permission\AuthGrant::STATE_APPROVED) {
                    return true;
                }
            }

            // Not auth, reset without_acl params
            $params['without_acl'] = 0;
        }
        elseif (isset($params['filter']) && is_object($params['filter']) && $params['filter']->getData('without_acl')) { // employee list
            $model = new \App\Models\Permission\AuthGrant($app);

            if ($viewModule) {
                $result = $model->requestAuth($viewModule);
                if ($result === true || $result['state'] == \App\Models\Permission\AuthGrant::STATE_APPROVED) {
                    return true;
                }
            } elseif ($statePath) {
                $result = $model->requestAuthByPath($statePath);
                if ($result === true || $result['state'] == \App\Models\Permission\AuthGrant::STATE_APPROVED) {
                    return true;
                }
            }

            // Not auth, reset without_acl params
            $params['filter']->setData('without_acl', 0);
        }
    });

return $module;
