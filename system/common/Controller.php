<?php
/**
 * Provides error codes for controllers.
 */

namespace App\Common;

use App\Models\Security\IpAllowList;
use App\Utils;
use Key\Exception\AppException;
use Key\Log\LoggerManager;
use Pimple\Container;
use Psr\Log\InvalidArgumentException;

/**
 * Class Controller
 * @package App\Common
 */
class Controller extends \Key\Abstracts\Controller implements ErrorCodes
{

    protected $statusMessageMap = self::ERROR_MESSAGES;

    static $listenerModels = array();

    protected $currentLanguage = null;

    /**
     * Controller invoke
     *
     * @param \Pimple\Container $container
     * @return $this
     */
    public function __invoke(Container $container, $isAuth = true)
    {
        Sequence::$app = $container;

        $aid = $container->offsetExists(Constants::SESSION_KEY_CURRENT_ACCOUNT_ID) ? $container->offsetGet(Constants::SESSION_KEY_CURRENT_ACCOUNT_ID) : 0;
        if ($aid) {
            $ipAllowListModel = new IpAllowList($container);
            $ipAllowListModel->mock($aid);
            if (!$ipAllowListModel->check()) {
                throw new AppException('IP is not in allowlist.');
            }
        }

        $this->setTimezoneByClient($container);

        return parent::__invoke($container, $isAuth);
    }
    public function getClientTimezone($app)
    {
        /** @var \Key\Http\Request $request */
        $request = $app ? $app['request'] : $this->app['request'];
        if ($request) {
            $tzOffset = $request->getHeaderLine('App-TZ-Offset');
            if (!is_null($tzOffset) && strlen($tzOffset) != 0) {
                return timezone_name_from_abbr('', $tzOffset * 60, false);
            }
        }
        return env('APP_DEFAULT_TIMEZONE');
    }

    public function setTimezoneByClient($app)
    {
        $timezone = $this->getClientTimezone($app);
        date_default_timezone_set($timezone);
    }
    protected function loadMessages()
    {
        $lang = $this->getCurrentLanguage();
        $lang = strtoupper($lang);
        // error_log('loadMessages lang: ' . $lang);
        switch ($lang) {
            case 'EN':
                $this->statusMessageMap = self::ERROR_MESSAGES_EN;
                break;
            case 'ZH-TW':
                $this->statusMessageMap = self::ERROR_MESSAGES_ZH_TW;
                break;
            /* 葡萄牙语 */
            case 'PT':
            case 'PT-PT':
            case 'PT-BR':
            case 'POR':
                $this->statusMessageMap = self::ERROR_MESSAGES_POR;
                break;
            case 'ZH':
            case 'ZH-CN':
            default:
                $this->statusMessageMap = self::ERROR_MESSAGES;
        }
    }

    /**
     * Get the return status message.
     *
     * @return string
     */
    public function getStatusMessage()
    {
        if ($this->statusMessage) return $this->statusMessage;
        $this->loadMessages();
        $statusCode = $this->getStatusCode();
        // exit(var_export($this->statusMessageMap, true));
        return isset($this->statusMessageMap[$statusCode]) ? $this->statusMessageMap[$statusCode] : '';
    }

    /**
     * Returns the number of items in the queried collection to be included in the result.
     *
     * @return int
     */
    public function getQueryTop()
    {
        /** @var \Key\Http\Request $request */
        $request = $this->app['request'];
        $top = (int)$request->getQueryParam('$top');

        return $top;
    }

    /**
     * Returns the number of items in the queried collection that are to be skipped and not included in the result.
     *
     * @return int
     */
    public function getQuerySkip()
    {
        /** @var \Key\Http\Request $request */
        $request = $this->app['request'];
        $skip = (int)$request->getQueryParam('$skip');

        return $skip;
    }

    /**
     * Get logged-in user info.
     *
     * @return null|array
     */
    public function getUser()
    {
        return $this->getSession(Constants::SESSION_USR_KEY, false);
    }

    /**
     * Set logged-in user info.
     *
     * @param array $user
     */
    public function setUser($user)
    {
        // if ($this->session) {
        //     return $this->session->set(Constants::SESSION_USR_KEY, $user);
        // }
        // return false;
        return $this->setSession(Constants::SESSION_USR_KEY, $user);
    }

    /**
     * Get logged-in user ID.
     *
     * @return int|null
     */
    public function getUserId()
    {
        return ArrayGet($this->getUser(), 'id');
    }

    public function getAccount()
    {
        // return $this->session ? $this->session->get(Constants::SESSION_KEY_PC_ACCOUNT) : false;
        return $this->getSession(Constants::SESSION_KEY_PC_ACCOUNT, false);
    }

    public function setAccount($account)
    {
        // return $this->session ? $this->session->set(Constants::SESSION_KEY_PC_ACCOUNT, $account) : false;
        return $this->getSession(Constants::SESSION_KEY_PC_ACCOUNT, $account);
    }

    public function getAccountId()
    {
        // return $this->app->offsetExists(\App\Common\Constants::SESSION_KEY_CURRENT_ACCOUNT_ID) ? $this->app[\App\Common\Constants::SESSION_KEY_CURRENT_ACCOUNT_ID] : 0;
        $aid = $this->getSession(Constants::SESSION_KEY_CURRENT_ACCOUNT_ID);
        return $aid ?: ArrayGet($this->getAccount(), 'id');
    }

    /**
     * Get the value of the attribute name of the user info.
     *
     * @param string $name Attribute Name
     * @return mixed|null
     */
    public function getUserValue($name)
    {
        return ArrayGet($this->getUser(), $name);
    }

    /**
     * Get the value of the attribute name of the account info.
     *
     * @param string $name Attribute Name
     * @return mixed|null
     */
    public function getAccountValue($name)
    {
        return ArrayGet($this->getAccount(), $name);
    }

    /**
     * Get current logged-in employee ID.
     *
     * @return int
     */
    public function getEmployeeId()
    {
        return $this->getSession(Constants::SESSION_KEY_CURRENT_EMPLOYEE_ID, 0);
    }

    /**
     * Log the message.
     *
     * @param string $msg
     * @param string $level one of trace/info/debug/warn/error/fatal
     */
    protected function log($msg, $level = 'debug', $appName = null)
    {
        if (!is_string($msg)) {
            $msg = json_encode($msg);
        }
        $appName = $appName ?: ($this->app->offsetExists('appName') ? $this->app['appName'] : 'base');
        $logger = LoggerManager::getFileInstance($appName);
        if (method_exists($logger, $level)) {
            $logger->{$level}('[' . get_called_class() . ']' . ($this->getAccountId() ? '#' . $this->getAccountId() . '# ' : '') . $msg);
        } else {
            throw new InvalidArgumentException('Invalid logger level: ' . $level);
        }
    }

    /**
     * @param $key
     * @param array $params
     * @return string
     * @deprecated
     * @see self::t()
     */
    protected function getLang($key, $params = [])
    {
        $lang = $this->getCurrentLanguage();
        return Utils::getLang($key, $params, $lang);
    }

    protected function t($key, $replace = [])
    {
        /** @var \Key\Translation\Translator $translator */
        $translator = $this->app['translator'];
        return $translator->get($key, $replace);
    }

    protected function getCurrentLanguage()
    {
        if (!$this->currentLanguage) {
            $this->currentLanguage = $this->app->offsetExists('locale') ? $this->app['locale'] : env('APP_LANGUAGE_DEFAULT', 'zh-CN');
        }
        return $this->currentLanguage;
    }
}
