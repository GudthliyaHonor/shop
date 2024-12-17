<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2022 yidianzhishi.com
 * @version 1.0.0
 * @link http://www.yidianzhishi.com
 */

namespace Key\Mail;


use Exception;
use Key\Abstracts\Mail;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * SMTP implementation using PHPMailer.
 * @author lgh <liguanghui@keylogic.com.cn>
 */
class Smtp extends Mail
{
    protected $config;
    protected $mail;

    public function __construct($config)
    {
        $this->config = $config;
        parent::__construct($config);
        $this->mail = new PHPMailer(true);
        $this->mail->isSMTP();
        $this->mail->Host = $this->host ?: env('SMTP_HOST');
        $this->mail->Port = $this->port ?: env('SMTP_PORT');
        $this->mail->Username = $this->username ?: env('SMTP_ACCOUNT');
        $this->mail->Password = $this->password ?: env('SMTP_PASSWORD');
        $this->mail->isHTML(!!env('SMTP_HTML'));
        $this->mail->CharSet = env('SMTP_CHARSET', 'UTF-8');
        $this->mail->SMTPDebug = env('SMTP_DEBUG', 0);
    }

    public function setSecure($secure, $ignoreValidation = true)
    {
        if (is_int($secure) && $secure) { // old logic
            $this->mail->SMTPSecure = 'tls';
        } else {
            if ($secure) {
                $this->mail->SMTPAutoTLS = false;
                $secure = strtolower($secure);
                switch ($secure) {
                    case 'tls':
                        $this->mail->SMTPSecure = 'tls';
                        break;
                    case 'ssl':
                    default:
                        $this->mail->SMTPSecure = 'ssl';
                }
            }
        }
        if ($ignoreValidation) {
            $this->mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
        }
        return $this;
    }

    /**
     * Set debug level.
     *
     * @param int $level
     * @return $this
     */
    public function setDebug($level = 1)
    {
        $this->mail->SMTPDebug = $level;
        return $this;
    }

    /**
     * Set debugger.
     * 
     * @param Callable|\Psr\Log\LoggerInterface $debugger
     * @return $this
     */
    public function setDebugger($debugger)
    {
        $this->mail->Debugoutput = $debugger;
        return $this;
    }

    /**
     * Set timeout for connectiong host.
     * 
     * @param int $timeout Connect timeout (Second)
     * @return $this
     */
    public function setTimeout($timeout) {
        $this->mail->Timeout = $timeout;
        return $this;
    }


    /**
     * Set the authentication.
     * 
     * @param string $username
     * @param string $password
     * @return $this
     */
    public function setAuthentication($username, $password)
    {
        $this->mail->Username = $username;
        $this->mail->Password = $password;
        if ($username) {
            $this->mail->SMTPAuth = true;
        } else {
            $this->mail->SMTPAuth = false;
        }
        return $this;
    }

    /**
     *
     * @param string $addr Email address
     * @param string $alias
     * @return $this
     */
    public function setFrom($addr, $alias = '')
    {
        $this->mail->setFrom($addr, $alias);
        return $this;
    }

    /**
     * Add a recipient.
     *
     * @param string $addr Email address
     * @param string $alias
     * @return $this
     */
    public function addAddress($addr, $alias = '')
    {
        $this->mail->addAddress($addr, $alias);
        return $this;
    }

    /**
     * Add recipients.
     *
     * @param array $addr For example: ['Joe Doe <doe@example.com>', 'postmaster@example.com']
     * @return $this
     */
    public function addAddresses(array $addr)
    {
        $this->mail->addAddress(implode(',', $addr));
        return $this;
    }

    /**
     * ReplyTo.
     *
     * @param string $addr Email address
     * @param string $alias
     * @return $this
     */
    public function addReplyTo($addr, $alias = '')
    {
        if ($addr) {
            $this->mail->addReplyTo($addr, $alias);
        }
        return $this;
    }

    public function addCC($addr, $alias = '')
    {
        $this->mail->addCC($addr);
        return $this;
    }

    public function addBCC($addr, $alias = '')
    {
        $this->mail->addBCC($addr);
        return $this;
    }

    public function setSubject($subject)
    {
        $this->mail->Subject = $subject;
        return $this;
    }

    /**
     * The HTML Message body.
     *
     * @param string $body
     * @return $this
     */
    public function setBody($body)
    {
        $this->mail->Body = $body;
        return $this;
    }

    /**
     * The body in plain text for non-HTML mail clients.
     *
     * @param string $altBody
     * @return $this
     */
    public function setAltBody($altBody)
    {
        $this->mail->AltBody = $altBody;
        return $this;
    }

    /**
     * Add attachment from a path on the filesystem.
     *
     * @param string $attachment Attachment path
     * @param string $name Overrides the attachment name
     * @return $this
     */
    public function addAttachment($attachment, $name = '')
    {
        $this->mail->addAttachment($attachment, $name);
        return $this;
    }

    /**
     * Reset To/Reply/CC/Bcc/attachments
     */
    public function reset()
    {
        $this->mail->clearAllRecipients();
        $this->mail->clearAttachments();
        //$this->mail = new static($this->mail->Host, $this->mail->Port, $this->mail->Username, $this->mail->Password, $this->mail->SMTPSecure);
        return $this;
    }

    public function send(&$err = null)
    {
        try {
            return $this->mail->send();
        } catch (Exception $ex) {
            error_log('Message could not be sent. Mailer Error: ' . $this->mail->ErrorInfo);
            error_log('host: ' . $this->host);
            error_log('port: ' . $this->port);
            $err = $this->mail->ErrorInfo;
        }
        return false;
    }
}