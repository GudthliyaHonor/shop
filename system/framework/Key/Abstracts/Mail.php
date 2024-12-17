<?php
/**
 * Key framework.
 *
 * @package Key
 * @copyright 2022 yidianzhishi.com
 * @version 0.1.0
 * @link http://www.yidianzhishi.com
 */

namespace Key\Abstracts;


abstract class Mail
{
    protected $host;
    protected $port;
    protected $username;
    protected $password;
    protected $secure = null;

    protected $from = null;
    protected $fromAlias = null;

    protected $addresses = [];

    public function __construct($config)
    {
        $this->host = $config['host'];
        $this->port = $config['port'];
        $this->username = $config['username'];
        $this->password = $config['password'];
    }

    /**
     * Set secure state.
     *
     * @param mixed $secure tls/ssl
     * @return $this
     */
    public function setSecure($secure)
    {
        $this->secure = $secure;
        return $this;
    }

    /**
     * Set from address.
     * @param string $addr Email address
     * @param string $alias
     * @return $this
     */
    abstract public function setFrom($from, $alias = '');

    /**
     * Add a recipient.
     *
     * @param string $addr Email address
     * @param string $alias
     * @return $this
     */
    abstract public function addAddress($addr, $alias = '');

    /**
     * Add recipients.
     *
     * @param string $addr For example: ['Joe Doe <doe@example.com>', 'postmaster@example.com']
     * @return $this
     */
    abstract public function addAddresses(array $addr);

    /**
     * ReplyTo.
     *
     * @param string $addr Email address
     * @param string $alias
     * @return $this
     */
    abstract public function addReplyTo($addr, $alias = '');

    abstract public function addCC($addr, $alias = '');

    abstract public function addBCC($addr, $alias = '');

    /**
     * The HTML Message subject.
     *
     * @param string $subject
     * @return $this
     */
    abstract public function setSubject($subject);

    /**
     * The HTML Message body.
     *
     * @param string $body
     * @return $this
     */
    abstract public function setBody($body);

    /**
     * The body in plain text for non-HTML mail clients.
     *
     * @param string $altBody
     * @return $this
     */
    abstract public function setAltBody($altBody);

    /**
     * Add attachment from a path on the filesystem.
     *
     * @param string $attachment Attachment path
     * @param string $name Overrides the attachment name
     * @return $this
     */
    abstract public function addAttachment($attachment, $name = '');

    /**
     * Reset To/Reply/CC/Bcc/Subject/Message/attachments
     */
    abstract public function reset();

    /**
     * Send the message.
     *
     * @param mixed $err
     * @return boolean
     */
    abstract public function send(&$err = null);

}