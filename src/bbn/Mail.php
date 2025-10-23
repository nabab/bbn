<?php
/**
 * Class for sending mails
 *
 * This class uses PHPMailer but adds the possibility to append the sent emails to the Sent (or another) folder
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Database
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.2r89
 *
 * Here is an example with gMail
 * <code>
 * $mail = new Mail([
 *   'port' => 465,
 *   'host' => 'imap.gmail.com',
 *   'user' => 'myemail@gmail.com',
 *   'name' => 'My real name',
 *   'pass' => 'mypassword',
 *   'imap' => true,
 *   'ssl' => true
 * ]);
 * </code>
 *
 * Here is an example with classic
 * <code>
 * $mail = new Mail([
 *   'host' => 'mail.m3l.co',
 *   'user' => 'myrealemail@babna.com',
 *   'from' => 'myniceemail@babna.com',
 *   'name' => 'My real name',
 *   'pass' => 'mypassword',
 *   'imap' => true
 * ]);
 * </code>
 */

namespace bbn;

use Exception;
use bbn\X;
use bbn\Mvc;
use bbn\Models\Cls\Basic;
use PHPMailer\PHPMailer\PHPMailer;

class Mail extends Basic
{
  /**
   * The destination fields.
   *
   * @var array
   */
  private static $_dest_fields = ['to', 'cc', 'bcc'];

  /**
   * The default HTML template.
   *
   * @var string
   */
  private static $_default_template = <<<TEMPLATE
<!DOCTYPE html>
<html>
<head>
<title>{{title}}</title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
</head>
<body>
<div>{{{text}}}</div>
</body>
</html>
TEMPLATE;

  /**
   * @todo document
   * @var boolean
   */
  private static $_template_checked = false;

  /**
   * The content currently set for sending by the class.
   *
   * @var string
   */
  private static $_content = '';

  /**
   * The hash of the last content sent by the class.
   *
   * @var string
   */
  private static $_hash_content;

  /**
   * @todo document
   * 
   * @var PHPMailer
   */
  public $mailer;

  /**
   * @todo document
   * 
   * @var string
   */
  private $template;

  /**
   * @todo document
   * 
   * @var string
   */
  private $path;

  /**
   * @todo document
   * 
   * @var string
   */
  private $imap_user;

  /**
   * @todo document
   * 
   * @var string
   */
  private $imap_pass;

  /**
   * @todo document
   * 
   * @var string
   */
  private $imap_sent;

  /**
   * @todo document
   * 
   * @var string
   */
  private $imap_string;

  /**
   * @todo document
   * 
   * @var string
   */
  private $imap;

  /**
   * Sets the static variable content and hash_content with the given string with CSS transformed in inline style.
   * 
   * @param string $content
   * @return void
   */
  private static function setContent(string $content){
    $md5 = md5($content);
    if ( $md5 !== self::$_hash_content ){
      self::$_hash_content = $md5;
      //$inliner = new \TijsVerkoyen\CssToInlineStyles\CssToInlineStyles();
      //self::$_content = $inliner->convert($content);
      self::$_content = $content;
    }
  }

  /**
   * Gets once the default template in default prop or filesystem, sets props, and returns it.
   *
   * @return string
   */
  private static function getDefaultTemplate(){
    if (!self::$_template_checked) {
      self::$_template_checked = true;
      if (($dir = Mvc::getContentPath()) && file_exists($dir.'mails/template.html')) {
        self::$_default_template = file_get_contents($dir.'mails/template.html');
      }
    }
    return self::$_default_template;
  }

  /**
   * Constructor.
   *
   * @param array $cfg
   */
  public function __construct($cfg = [])
  {
    if ( !\defined('BBN_ADMIN_EMAIL') || !\defined('BBN_IS_DEV') ){
      die("You must provide the constants BBN_ADMIN_EMAIL and BBN_IS_DEV to use the mail class...");
    }

    $indexes = [
      'from',
      'user',
      'pass',
      'host',
      'ssl',
      'debug',
    ];

    if (empty($cfg['from']) && !empty($cfg['user'])) {
      $cfg['from'] = $cfg['user'];
    }

    foreach ($indexes as $i) {
      if (!array_key_exists($i, $cfg) && defined('BBN_EMAIL_'.strtoupper($i))) {
        $cfg[$i] = constant('BBN_EMAIL_'.strtoupper($i));
      }
    }

    if (!PHPMailer::validateAddress($cfg['from'])) {
      X::logError(0, "A \"From\" eMail address must be provided", __FILE__, __LINE__);
      $this->error("A \"From\" eMail address must be provided");
    }

    $has_host = !empty($cfg['host']) && Str::isDomain($cfg['host']);
    $this->mailer = new PHPMailer(true);

    try {
      $this->mailer->CharSet = $cfg['charset'] ?? 'UTF-8';
      $this->mailer->Encoding = $cfg['encoding'] ?? "quoted-printable";
      //$this->mailer->AllowCharsetEncoding = false;
      if ( isset($cfg['user'], $cfg['pass']) ){
        // SMTP connection will not close after each email sent, reduces SMTP overhead
        $this->mailer->isSMTP();
        if ( !empty($cfg['ssl']) ){
          if ( \is_array($cfg['ssl']) ){
            $this->mailer->SMTPOptions = ['ssl' => $cfg['ssl']];
          }
          else{
            $this->mailer->SMTPOptions = [
              'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'verify_host' => false,
                'allow_self_signed' => false
              ]
            ];
          }
        }
        else{
          $this->mailer->SMTPSecure = 'tls';
        }

        $this->mailer->Host = $has_host ? $cfg['host'] : 'localhost';
        $this->mailer->Port = isset($cfg['port']) ? $cfg['port'] : 587;
        $this->mailer->SMTPKeepAlive = true;
        $this->mailer->SMTPDebug = empty($cfg['debug']) ? false : 3;
        $this->mailer->Debugoutput = 'error_log';
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $cfg['user'];
        $this->mailer->Password = $cfg['pass'];
        if ( !empty($cfg['imap']) ){
          $this->setImap($cfg);
        }
      }

      $this->mailer->setFrom($cfg['from'], isset($cfg['name']) ? $cfg['name'] : 0);
      if ($cfg['reply-to'] ?? false) {
        $this->mailer->addReplyTo($cfg['reply-to']);
      }

      $this->setTemplate(isset($cfg['template']) ? $cfg['template'] : self::getDefaultTemplate());
    }
    catch (Exception $e) {
      $this->log($this->mailer->ErrorInfo);
      $this->log($e->getMessage());
      $this->mailer = false;
    }
  }


  public function __destruct()
  {
    if ( $this->mailer ){
      try {
        $this->mailer->smtpClose();
      }
      catch(Exception $e) {
        $this->log("Impossible to close the connection");

      }
    }
  }

  /**
   * Sets the IMAP configuration.
   *
   * @param array $cfg The configuration
   * @return self
   */
  public function setImap(array $cfg): self
  {
    if (!isset($cfg['imap_user'], $cfg['imap_pass']) && !isset($cfg['user'], $cfg['pass'])) {
      die("You need to provide user and password for IMAP connection");
    }
    $imap_host = isset($cfg['imap_host']) ? $cfg['imap_host'] : $cfg['host'];
    $this->imap_user = isset($cfg['imap_user']) ? $cfg['imap_user'] : $cfg['user'];
    $this->imap_pass = isset($cfg['imap_pass']) ? $cfg['imap_pass'] : $cfg['pass'];
    $this->imap_sent = isset($cfg['imap_sent']) ? $cfg['imap_sent'] : 'Sent';
    if (isset($cfg['imap_port'])) {
      $imap_port = $cfg['imap_port'];
    }
    if (!empty($cfg['imap_ssl'])) {
      if (!isset($cfg['imap_port'])) {
        $imap_port = 993;
      }
      $this->imap_string = "{".$imap_host.":".$imap_port."/ssl";
    }
    else {
      if (!isset($cfg['imap_port'])) {
        $imap_port = 143;
      }
      $this->imap_string = "{".$imap_host.":".$imap_port."/tls";
    }
    if (empty($cfg['valid'])) {
      $this->imap_string .= "/novalidate-cert";
    }
    $this->imap_string .= "}";
    return $this;
  }

  /**
   * Unset the IMAP configuration
   *
   * @return self
   */
  public function unsetImap(): self
  {
    unset($this->imap_string, $this->imap_user, $this->imap_pass);
    return $this;
  }

  /**
   * Set the From header together with the replyTo.
   *
   * @param string $email
   * @param string $name
   * @return self
   */
  public function setFrom(string $email, string|null $name = null): self
  {
    if (!PHPMailer::validateAddress($email)) {
      die("The From eMail address is not valid");
    }
    if (!$name) {
      $name = $email;
    }
    $this->mailer->setFrom($email, $name);
    return $this;
  }

  public function setTemplate(string $file): self
  {
    if (is_file($file)) {
      $this->template = file_get_contents($file);
      $this->path = X::dirname($file);
    }
    else {
      $this->template = $file;
      $this->path = Mvc::getDataPath();
    }
    return $this;
  }

  public function getError(){
    return $this->mailer->ErrorInfo;
  }

  public function send($cfg){
    $valid = false;
    $r = false;

    if (!defined('BBN_IS_PROD') || !constant('BBN_IS_PROD')) {
      $cfg['to'] = constant('BBN_ADMIN_EMAIL');
      $cfg['cc'] = '';
      $cfg['bcc'] = '';
      $this->mailer->AddAddress(constant('BBN_ADMIN_EMAIL'));
      $valid = 1;
    }
    else {
      foreach (self::$_dest_fields as $dest_field) {
        if (isset($cfg[$dest_field])) {
          if (!\is_array($cfg[$dest_field])) {
            $cfg[$dest_field] = array_map(
              function ($a) {
                return trim($a);
              },
              explode(";", $cfg[$dest_field])
            );
          }

          foreach ($cfg[$dest_field] as $dest) {
            if (PHPMailer::validateAddress($dest)) {
              switch ($dest_field) {
                case "to":
                  $this->mailer->AddAddress($dest);
                  break;
                case "cc":
                  $this->mailer->AddCC($dest);
                  break;
                case "bcc":
                  $this->mailer->AddBCC($dest);
                  break;
              }
              $valid = 1;
            }
            else {
              X::log("Adresse email invalide: ".$dest);
              $valid = false;
            }
          }
        }
      }
    }
    if ($valid) {
      if (!empty($cfg['from'])) {
        $this->setFrom($cfg['from']);
      }

      if (!empty($cfg['references'])) {
        // check if each reference have chevrons around the message id and remove them
        $refs = [];
        $cfg['references'] = explode(' ', $cfg['references']);
        foreach ($cfg['references'] as $ref) {
          if (preg_match('/^<(.*)>$/', $ref, $m)) {
            $mailbox = explode('@', $m[1])[0];
            $hostname = explode('@', $m[1])[1];
            $refs[] = imap_rfc822_write_address($mailbox, $hostname, null);
          }
          else {
            $mailbox = explode('@', $ref)[0];
            $hostname = explode('@', $ref)[1];
            $refs[] = imap_rfc822_write_address($mailbox, $hostname, null);
          }
        }
        // for each ref add '<' and '>' around the message id if not present
        for ($i = 0; $i < count($refs); $i++) {
          if (!preg_match('/^<(.*)>$/', $refs[$i])) {
            $refs[$i] = '<' . $refs[$i] . '>';
          }
        }

        $cfg['references'] = implode(' ', $refs);
        $this->mailer->addCustomHeader('References:' . $cfg['references']);
      }

      if (!empty($cfg['in_reply_to'])) {
        # check if the in-reply-to have chevrons around the message id and remove them
        if (preg_match('/^<(.*)>$/', $cfg['in_reply_to'], $m)) {
          $mailbox = explode('@', $m[1])[0];
          $hostname = explode('@', $m[1])[1];
          $cfg['in_reply_to'] = imap_rfc822_write_address($mailbox, $hostname, null);
        } else {
          $mailbox = explode('@', $cfg['in_reply_to'])[0];
          $hostname = explode('@', $cfg['in_reply_to'])[1];
          $cfg['in_reply_to'] = imap_rfc822_write_address($mailbox, $hostname, null);
        }
        // add '<' and '>' around the message id if not present
        if (!preg_match('/^<(.*)>$/', $cfg['in_reply_to'])) {
          $cfg['in_reply_to'] = '<' . $cfg['in_reply_to'] . '>';
        }

        $this->mailer->AddCustomHeader('In-Reply-To:' . mb_encode_mimeheader($cfg['in_reply_to']));
      }

      $ar  = [
        'title' => $cfg['subject'] ?? ($cfg['title'] ?? '')
      ];
      $enc = mb_detect_encoding($ar['title']);
      if ($enc !== $this->mailer->CharSet) {
        $ar['title'] = mb_convert_encoding($ar['title'], $this->mailer->CharSet, $enc);
      }

      $this->mailer->Subject = $ar['title'];

      
      if (empty($cfg['text'])) {
        $ar['text'] = '';
      }
      else {
        $ar['text'] = $cfg['text'];
        $enc = mb_detect_encoding($ar['text']);
        //X::ddump($enc, $this->mailer->CharSet);
        if ($enc !== $this->mailer->CharSet) {
          $ar['text'] = mb_convert_encoding($ar['text'], $this->mailer->CharSet, $enc);
        }
      }

      if (isset($cfg['attachments'])) {
        if (\is_string($cfg['attachments'])) {
          $cfg['attachments'] = [$cfg['attachments']];
        }

        foreach ($cfg['attachments'] as $name => $att) {
          if (is_file($att)) {
            // 2nd parameter is the file's name in the mail
            $this->mailer->AddAttachment($att, is_int($name) ? '' : $name);
          }
        }
      }

      if (!isset($renderer)) {
        $renderer = Tpl::renderer($this->template);
      }

      $ar['url'] = \defined('BBN_URL') ? BBN_URL : '';
      $text = $renderer($ar);
      self::setContent($text);
      $this->mailer->msgHTML(self::$_content, $this->path);

      try {
        $r = $this->mailer->send();
      }
      catch (Exception $e) {
        $this->log($e->getMessage());
        $this->log(\imap_last_error());
      }

      if ($r && !empty($this->imap_string)) {
        $mail_string = $this->mailer->getSentMIMEMessage();
        if (!\is_resource($this->imap)
          && !($this->imap instanceof \IMAP\Connection)
        ) {
          $this->imap = \imap_open($this->imap_string, $this->imap_user, $this->imap_pass);
        }

        if ((!\is_resource($this->imap)
            && !($this->imap instanceof \IMAP\Connection))
          || !\imap_append($this->imap, $this->imap_string.$this->imap_sent, $mail_string, "\\Seen")
        ) {
          $this->log(\imap_errors());
        }
      }
    }

    $this->mailer->ClearAllRecipients();
    $this->mailer->ClearAttachments();
    return $r;
  }
}