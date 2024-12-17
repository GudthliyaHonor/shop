<?php
/**
 * Send mail using SMTP
 */

namespace App\Common;

/**
 * @deprecated 1.0.0
 * @see \Key\Mail\Factory
 */
class EasyAliMail
{
    private $to;
    private $subject;
    private $message;
    private $params;
    private $keepParamName = false;
    private $urlToAnchor = false;

    private $client;
    private $request;

    /**
     * EasyAliMail constructor.
     * @param string $to To email address
     * @param string $subject Email subject
     * @param string $message Email body
     * @param array $params Email body message replacements.
     * @param bool $keepParamName if set true, the un-replaced parameter will keep, or it will replace as empty
     */
    public function __construct($to, $subject, $message, $params = array(), $keepParamName = false)
    {
        $this->to = $to;
        $this->subject = $subject;
        $this->message = $message;
        $this->params = $params;
        $this->keepParamName = $keepParamName;

        $iClientProfile = \DefaultProfile::getProfile("cn-hangzhou", env('ALI_APP_KEY'), env('ALI_APP_SECRET'));
        $this->client = new \DefaultAcsClient($iClientProfile);
        $this->request = new \Dm\Request\V20151123\SingleSendMailRequest();
        $this->request->setAccountName("admin@mail.talentyun.com");
        $this->request->setFromAlias("一点知识");
        $this->request->setAddressType(1);
        $this->request->setTagName("reg");
        $this->request->setReplyToAddress("true");
        $this->request->setToAddress($to);
        $this->request->setSubject($subject);
        $this->request->setHtmlBody($this->replaceParams($message));
    }

    public function setUrlToAnchor($urlToAnchor)
    {
        $this->urlToAnchor = !!$urlToAnchor;
    }

    protected function replaceParams($message)
    {
        $params = $this->params;
        $parsed = preg_replace_callback('/\{\{([^\}]+)\}\}/', function ($matched) use ($params) {
            if (isset($params[$matched[1]])) {
                return $params[$matched[1]];
            }

            return $this->keepParamName ? $matched[0] : ' ';
        }, $message);

        $parsed = str_replace(array("\r\n", "\r", "\n"), '<br />', $parsed);
        // Replace url to A element
        if ($this->urlToAnchor) {
            $parsed = preg_replace('(((f|ht){1}tp[s]?://)[-a-zA-Z0-9@:%_/+.~#!?&//=]+)', '<a href="\0" target="_blank">\0</a>', $parsed);
        }
        return $parsed;
    }

    /**
     *
     * Send the email.
     *
     * @param $err
     * @return bool
     */
    public function send(&$err = null)
    {
        try {
            $response = $this->client->getAcsResponse($this->request);
            //Tools::log('[EasyAliMail] Send mail success: ' . var_export($response, true));
            return true;
        } catch (ClientException $e) {
            //Tools::error('[EasyAliMail] Send mail fail: ' . $e->getMessage());
            $err = $e->getMessage();
        } catch (ServerException $e) {
            //Tools::error('[EasyAliMail] Send mail fail: ' . $e->getMessage());
            $err = $e->getMessage();
        }

        return false;
    }
}