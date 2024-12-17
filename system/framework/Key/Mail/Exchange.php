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


use Key\Abstracts\Mail;

use \jamesiarmes\PhpEws\Client;
use \jamesiarmes\PhpEws\Request\CreateItemType;
use \jamesiarmes\PhpEws\Request\CreateAttachmentType;

use \jamesiarmes\PhpEws\ArrayType\ArrayOfRecipientsType;
use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfAllItemsType;
use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfAttachmentsType;
use jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseItemIdsType;
use \jamesiarmes\PhpEws\Enumeration\BodyTypeType;
use jamesiarmes\PhpEws\Enumeration\DistinguishedFolderIdNameType;
use \jamesiarmes\PhpEws\Enumeration\MessageDispositionType;
use \jamesiarmes\PhpEws\Enumeration\ResponseClassType;
use jamesiarmes\PhpEws\Request\SendItemType;
use \jamesiarmes\PhpEws\Type\BodyType;
use jamesiarmes\PhpEws\Type\DistinguishedFolderIdType;
use \jamesiarmes\PhpEws\Type\EmailAddressType;
use \jamesiarmes\PhpEws\Type\MessageType;
use \jamesiarmes\PhpEws\Type\SingleRecipientType;
use \jamesiarmes\PhpEws\Type\FileAttachmentType;
use \jamesiarmes\PhpEws\Type\ItemIdType;
use jamesiarmes\PhpEws\Type\TargetFolderIdType;
use Key\Exception\AppException;
use SplFileObject;

/**
 * Exchange implementation using PhpEws.
 * @author lgh <liguanghui@keylogic.com.cn>
 */
class Exchange extends Mail
{
    const CHARSET_ASCII = 'us-ascii';
    const CHARSET_ISO88591 = 'iso-8859-1';
    const CHARSET_UTF8 = 'utf-8';

    protected $config;

    /** @var \jamesiarmes\PhpEws\Client $client */
    protected $client;

    /** @var \jamesiarmes\PhpEws\Request\CreateItemType $request */
    protected $request;

    /** @var \jamesiarmes\PhpEws\Type\SingleRecipientType $message */
    protected $message;

    protected $hasAttachment = false;
    protected $attachments = [];

    /**
     * Which validator to use by default when validating email addresses.
     * May be a callable to inject your own validator, but there are several built-in validators.
     * The default validator uses PHP's FILTER_VALIDATE_EMAIL filter_var option.
     *
     * @see PHPMailer::validateAddress()
     *
     * @var string|callable
     */
    public static $validator = 'php';

    public function __construct($config)
    {
        $this->config = $config;
        parent::__construct($config);

        $this->client = new Client($config['host'], $config['username'], $config['password'], $config['version'] ?? Client::VERSION_2016);

        $this->request = new CreateItemType();
        $this->request->Items = new NonEmptyArrayOfAllItemsType();

        $this->request->MessageDisposition = MessageDispositionType::SAVE_ONLY;
        $this->message = new MessageType();
        $this->message->ToRecipients = new ArrayOfRecipientsType();
        $this->message->ReplyTo = new ArrayOfRecipientsType();
        $this->message->BccRecipients = new ArrayOfRecipientsType();
        $this->message->CcRecipients = new ArrayOfRecipientsType();

        $this->message->Attachments = new NonEmptyArrayOfAttachmentsType();
    }

    /**
     *
     * @param string $addr Email address
     * @param string $alias
     * @return $this
     */
    public function setFrom($addr, $alias = '')
    {
        $this->message->From = new SingleRecipientType();
        $this->message->From->Mailbox->EmailAddress = $addr;
        $this->message->From->Mailbox->Name = $alias;
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
        $recipient = new EmailAddressType();
        $recipient->Name = $alias;
        $recipient->EmailAddress = $addr;
        $this->message->ToRecipients->Mailbox[] = $recipient;
        return $this;
    }

    /**
     * !!!COPY from PHPMailer!!!
     * 
     * Check that a string looks like an email address.
     * Validation patterns supported:
     * * `auto` Pick best pattern automatically;
     * * `pcre8` Use the squiloople.com pattern, requires PCRE > 8.0;
     * * `pcre` Use old PCRE implementation;
     * * `php` Use PHP built-in FILTER_VALIDATE_EMAIL;
     * * `html5` Use the pattern given by the HTML5 spec for 'email' type form input elements.
     * * `noregex` Don't use a regex: super fast, really dumb.
     * Alternatively you may pass in a callable to inject your own validator, for example:
     *
     * ```php
     * PHPMailer::validateAddress('user@example.com', function($address) {
     *     return (strpos($address, '@') !== false);
     * });
     * ```
     *
     * You can also set the PHPMailer::$validator static to a callable, allowing built-in methods to use your validator.
     *
     * @param string          $address       The email address to check
     * @param string|callable $patternselect Which pattern to use
     *
     * @return bool
     */
    public static function validateAddress($address, $patternselect = null)
    {
        if (null === $patternselect) {
            $patternselect = static::$validator;
        }
        //Don't allow strings as callables, see SECURITY.md and CVE-2021-3603
        if (is_callable($patternselect) && !is_string($patternselect)) {
            return call_user_func($patternselect, $address);
        }
        //Reject line breaks in addresses; it's valid RFC5322, but not RFC5321
        if (strpos($address, "\n") !== false || strpos($address, "\r") !== false) {
            return false;
        }
        switch ($patternselect) {
            case 'pcre': //Kept for BC
            case 'pcre8':
                /*
                 * A more complex and more permissive version of the RFC5322 regex on which FILTER_VALIDATE_EMAIL
                 * is based.
                 * In addition to the addresses allowed by filter_var, also permits:
                 *  * dotless domains: `a@b`
                 *  * comments: `1234 @ local(blah) .machine .example`
                 *  * quoted elements: `'"test blah"@example.org'`
                 *  * numeric TLDs: `a@b.123`
                 *  * unbracketed IPv4 literals: `a@192.168.0.1`
                 *  * IPv6 literals: 'first.last@[IPv6:a1::]'
                 * Not all of these will necessarily work for sending!
                 *
                 * @see       http://squiloople.com/2009/12/20/email-address-validation/
                 * @copyright 2009-2010 Michael Rushton
                 * Feel free to use and redistribute this code. But please keep this copyright notice.
                 */
                return (bool) preg_match(
                    '/^(?!(?>(?1)"?(?>\\\[ -~]|[^"])"?(?1)){255,})(?!(?>(?1)"?(?>\\\[ -~]|[^"])"?(?1)){65,}@)' .
                    '((?>(?>(?>((?>(?>(?>\x0D\x0A)?[\t ])+|(?>[\t ]*\x0D\x0A)?[\t ]+)?)(\((?>(?2)' .
                    '(?>[\x01-\x08\x0B\x0C\x0E-\'*-\[\]-\x7F]|\\\[\x00-\x7F]|(?3)))*(?2)\)))+(?2))|(?2))?)' .
                    '([!#-\'*+\/-9=?^-~-]+|"(?>(?2)(?>[\x01-\x08\x0B\x0C\x0E-!#-\[\]-\x7F]|\\\[\x00-\x7F]))*' .
                    '(?2)")(?>(?1)\.(?1)(?4))*(?1)@(?!(?1)[a-z0-9-]{64,})(?1)(?>([a-z0-9](?>[a-z0-9-]*[a-z0-9])?)' .
                    '(?>(?1)\.(?!(?1)[a-z0-9-]{64,})(?1)(?5)){0,126}|\[(?:(?>IPv6:(?>([a-f0-9]{1,4})(?>:(?6)){7}' .
                    '|(?!(?:.*[a-f0-9][:\]]){8,})((?6)(?>:(?6)){0,6})?::(?7)?))|(?>(?>IPv6:(?>(?6)(?>:(?6)){5}:' .
                    '|(?!(?:.*[a-f0-9]:){6,})(?8)?::(?>((?6)(?>:(?6)){0,4}):)?))?(25[0-5]|2[0-4][0-9]|1[0-9]{2}' .
                    '|[1-9]?[0-9])(?>\.(?9)){3}))\])(?1)$/isD',
                    $address
                );
            case 'html5':
                /*
                 * This is the pattern used in the HTML5 spec for validation of 'email' type form input elements.
                 *
                 * @see https://html.spec.whatwg.org/#e-mail-state-(type=email)
                 */
                return (bool) preg_match(
                    '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}' .
                    '[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/sD',
                    $address
                );
            case 'php':
            default:
                return filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
        }
    }

    /**
     * !!!COPY from PHPMailer!!!
     * 
     * Parse and validate a string containing one or more RFC822-style comma-separated email addresses
     * of the form "display name <address>" into an array of name/address pairs.
     * Uses the imap_rfc822_parse_adrlist function if the IMAP extension is available.
     * Note that quotes in the name part are removed.
     *
     * @see http://www.andrew.cmu.edu/user/agreen1/testing/mrbs/web/Mail/RFC822.php A more careful implementation
     *
     * @param string $addrstr The address list string
     * @param bool   $useimap Whether to use the IMAP extension to parse the list
     *
     * @return array
     */
    public static function parseAddresses($addrstr, $useimap = true, $charset = self::CHARSET_ISO88591)
    {
        $addresses = [];
        if ($useimap && function_exists('imap_rfc822_parse_adrlist')) {
            //Use this built-in parser if it's available
            $list = imap_rfc822_parse_adrlist($addrstr, '');
            // Clear any potential IMAP errors to get rid of notices being thrown at end of script.
            imap_errors();
            foreach ($list as $address) {
                if (
                    '.SYNTAX-ERROR.' !== $address->host &&
                    static::validateAddress($address->mailbox . '@' . $address->host)
                ) {
                    //Decode the name part if it's present and encoded
                    if (
                        property_exists($address, 'personal') &&
                        //Check for a Mbstring constant rather than using extension_loaded, which is sometimes disabled
                        defined('MB_CASE_UPPER') &&
                        preg_match('/^=\?.*\?=$/s', $address->personal)
                    ) {
                        $origCharset = mb_internal_encoding();
                        mb_internal_encoding($charset);
                        //Undo any RFC2047-encoded spaces-as-underscores
                        $address->personal = str_replace('_', '=20', $address->personal);
                        //Decode the name
                        $address->personal = mb_decode_mimeheader($address->personal);
                        mb_internal_encoding($origCharset);
                    }

                    $addresses[] = [
                        'name' => (property_exists($address, 'personal') ? $address->personal : ''),
                        'address' => $address->mailbox . '@' . $address->host,
                    ];
                }
            }
        } else {
            //Use this simpler parser
            $list = explode(',', $addrstr);
            foreach ($list as $address) {
                $address = trim($address);
                //Is there a separate name part?
                if (strpos($address, '<') === false) {
                    //No separate name, just use the whole thing
                    if (static::validateAddress($address)) {
                        $addresses[] = [
                            'name' => '',
                            'address' => $address,
                        ];
                    }
                } else {
                    list($name, $email) = explode('<', $address);
                    $email = trim(str_replace('>', '', $email));
                    $name = trim($name);
                    if (static::validateAddress($email)) {
                        //Check for a Mbstring constant rather than using extension_loaded, which is sometimes disabled
                        //If this name is encoded, decode it
                        if (defined('MB_CASE_UPPER') && preg_match('/^=\?.*\?=$/s', $name)) {
                            $origCharset = mb_internal_encoding();
                            mb_internal_encoding($charset);
                            //Undo any RFC2047-encoded spaces-as-underscores
                            $name = str_replace('_', '=20', $name);
                            //Decode the name
                            $name = mb_decode_mimeheader($name);
                            mb_internal_encoding($origCharset);
                        }
                        $addresses[] = [
                            //Remove any surrounding quotes and spaces from the name
                            'name' => trim($name, '\'" '),
                            'address' => $email,
                        ];
                    }
                }
            }
        }

        return $addresses;
    }

    /**
     * Add recipients.
     *
     * @param array $addr For example: ['Joe Doe <doe@example.com>', 'postmaster@example.com']
     * @return $this
     */
    public function addAddresses(array $addr)
    {
        $addresses = static::parseAddresses(implode(',', $addr));
        if ($addresses) {
            foreach ($addresses as $address) {
                $recipient = new EmailAddressType();
                $recipient->Name = $address['name'] ?: explode('@', $address['address'])[0];
                $recipient->EmailAddress = $address['address'];
                $this->message->ToRecipients->Mailbox[] = $recipient;
            }
        }
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
        $recipient = new EmailAddressType();
        $recipient->Name = $alias;
        $recipient->EmailAddress = $addr;
        $this->message->ReplyTo->Mailbox[] = $recipient;
        return $this;
    }

    public function addCC($addr, $alias = '')
    {
        $recipient = new EmailAddressType();
        $recipient->Name = $alias;
        $recipient->EmailAddress = $addr;
        $this->message->CcRecipients->Mailbox[] = $recipient;
        return $this;
    }

    public function addBCC($addr, $alias = '')
    {
        $recipient = new EmailAddressType();
        $recipient->Name = $alias;
        $recipient->EmailAddress = $addr;
        $this->message->BccRecipients->Mailbox[] = $recipient;
        return $this;
    }

    public function setSubject($subject)
    {
        $this->message->Subject = $subject;
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
        $this->message->Body = new BodyType();
        $bodyType = $this->config['body_type'] ?? BodyTypeType::HTML;
        if ($bodyType !== BodyTypeType::TEXT && $bodyType !== BodyTypeType::HTML) {
            $bodyType = BodyTypeType::HTML;
        }
        $this->message->Body->BodyType = $bodyType;
        if ($bodyType == BodyTypeType::TEXT) {
            $this->message->Body->_ = $body;
        }
        else {
            $this->message->Body->_ = <<<BODY
<!doctype html>
<html>
    <head></head>
    <body>$body</body>
</html>
BODY;
        }
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
        $file = new SplFileObject($attachment);
        $finfo = finfo_open();

        $attachmentObj = new FileAttachmentType();
        $attachmentObj->Content = $file->openFile('r')->fread($file->getSize());
        $attachmentObj->Name = $name ?: $file->getBasename();
        $attachmentObj->ContentType = finfo_file($finfo, $attachment);
        $this->attachments[] = $attachmentObj;

        $this->message->Attachments->FileAttachment[] = $attachmentObj;

        $this->hasAttachment = true;

        return $this;
    }

    /**
     * Reset To/Reply/CC/Bcc/attachments
     */
    public function reset()
    {
        // reset request
        $this->request = new CreateItemType();
        $this->request->Items = new NonEmptyArrayOfAllItemsType();

        // reset message
        $this->message = new MessageType();
        $this->message->ToRecipients = new ArrayOfRecipientsType();
        $this->message->ReplyTo = new ArrayOfRecipientsType();
        $this->message->BccRecipients = new ArrayOfRecipientsType();
        $this->message->CcRecipients = new ArrayOfRecipientsType();
        $this->message->Attachments = new NonEmptyArrayOfAttachmentsType();
    }

    public function send(&$err = null)
    {
        $this->request->MessageDisposition = MessageDispositionType::SEND_AND_SAVE_COPY;
        $this->request->Items->Message[] = $this->message;

        $response = $this->client->CreateItem($this->request);
        if ($response) {
            // Iterate over the results, printing any error messages or message ids.
            $response_messages = $response->ResponseMessages->CreateItemResponseMessage;
            foreach ($response_messages as $response_message) {
                if ($response_message->ResponseClass != ResponseClassType::SUCCESS) {
                    $code = $response_message->ResponseCode;
                    $message = $response_message->MessageText;
                    // fwrite(STDERR, "Message failed to create with \"$code: $message\"\n");
                    error_log('err1: ' . $message);
                    $err = [
                        'step' => 1,
                        'code' => $code,
                        'msg' => $message,
                    ];
                    return false;
                }

                // Iterate over the created messages, printing the id for each.
                foreach ($response_message->Items->Message as $item) {
                    // $output = '- Id: ' . $item->ItemId->Id . "\n";
                    // $output .= '- Change key: ' . $item->ItemId->ChangeKey . "\n";
                    // fwrite(STDOUT, "Message created successfully.\n$output");
                    $id = $item->ItemId->Id;
                    $changeKey = $item->ItemId->ChangeKey;
                    break;
                }
            }
        }

        error_log('id: ' . $id . ' - changekey: ' . $changeKey);
        return [
            'Id' => $id,
            'ChangeKey' => $changeKey
        ];
    }
}
