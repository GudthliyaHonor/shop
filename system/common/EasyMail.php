<?php
/**
 * Send mail using SMTP
 */

namespace App\Common;


class EasyMail
{
    private $to;
    private $subject;
    private $message;
    private $additional_headers = null;
    private $additional_parameters = null;

    public function __construct($to, $subject, $message, $additional_headers = null, $additional_parameters = null)
    {
        global $CONFIG;

        if (isset($CONFIG->mailConfig) && is_array($CONFIG->mailConfig)) {
            foreach($CONFIG->mailConfig as $key => $val) {
                if ($key == 'smtp_server') {
                    ini_set('SMTP', $val);
                } else {
                    ini_set($key, $val);
                }
            }
        }

        $from = isset($CONFIG->mailConfig['sendmail_from']) ? $CONFIG->mailConfig['sendmail_from'] : null;
        $defaultHeaders = sprintf('From: %s' . PHP_EOL .'Reply-To: %s' . PHP_EOL . 'X-Mailer: PHP%s', $from, $to, phpversion());

        $this->to = $to;
        $this->subject = $subject;
        $this->message = $message;
        $this->additional_headers = isset($additional_headers) ? $additional_headers : $defaultHeaders;
        $this->additional_parameters = $additional_parameters;
    }

    public function send()
    {
        try {
            return mail($this->to, $this->subject, $this->message, $this->additional_headers, $this->additional_parameters);
        } catch (\Exception $ex) {
            error_log('[EasyMail] Exception: ' . $ex->getMessage());
        }

        return false;
    }
}