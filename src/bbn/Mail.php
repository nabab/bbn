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

use PHPMailer\PHPMailer\PHPMailer;

class Mail extends Models\Cls\Basic
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
      $inliner = new \TijsVerkoyen\CssToInlineStyles\CssToInlineStyles();
      self::$_content = $inliner->convert($content);
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
      if (($dir = \bbn\Mvc::getContentPath()) && file_exists($dir.'mails/template.html')) {
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
    if ( !isset($cfg['from']) && isset($cfg['user']) ){
      $cfg['from'] = $cfg['user'];
    }
    if (!isset($cfg['from'])) {
      $cfg['from'] = BBN_ADMIN_EMAIL;
    }
    if (!PHPMailer::validateAddress($cfg['from'])) {
      die(dgettext(X::tDom(), "A \"From\" eMail address must be provided"));
    }
    $has_host = !empty($cfg['host']) && Str::isDomain($cfg['host']);
    $this->mailer = new PHPMailer(true);
    try {
      $this->mailer->CharSet = isset($cfg['charset']) ? $cfg['charset'] : "UTF-8";
      if ( isset($cfg['user'], $cfg['pass']) ){
        // SMTP connection will not close after each email sent, reduces SMTP overhead
        $this->mailer->isSMTP();
        if ( !empty($cfg['ssl']) ){
          if ( \is_array($cfg['ssl']) ){
            $this->mailer->SMTPOptions = ['ssl' => $cfg['ssl']];
          }
          else{
            $this->mailer->SMTPOptions = [
              'verify_peer' => false,
              'verify_peer_name' => false,
              'verify_host' => false,
              'allow_self_signed' => false
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
      $this->setFrom($cfg['from'], isset($cfg['name']) ? $cfg['name'] : 0);
      $this->setTemplate(isset($cfg['template']) ? $cfg['template'] : self::getDefaultTemplate());
    }
    catch (\Exception $e) {
      $this->log($this->mailer->ErrorInfo);
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
  public function setFrom(string $email, string $name = null): self
  {
    if (!PHPMailer::validateAddress($email)) {
      die("The From eMail address is not valid");
    }
    if (!$name) {
      $name = $email;
    }
    $this->mailer->setFrom($email, $name);
    $this->mailer->addReplyTo($email, $name);
    return $this;
  }

  public function setTemplate(string $file): self
  {
    if (is_file($file)) {
      $this->template = file_get_contents($file);
      $this->path = dirname($file);
    }
    else {
      $this->template = $file;
      $this->path = BBN_DATA_PATH;
    }
    return $this;
  }

  public function getError(){
    return $this->mailer->ErrorInfo;
  }

  public function send($cfg){
    $valid = false;
    $r = false;
    if (!defined('BBN_IS_PROD') || !BBN_IS_PROD) {
      $cfg['to'] = BBN_ADMIN_EMAIL;
      $cfg['cc'] = '';
      $cfg['bcc'] = '';
      $this->mailer->AddAddress(BBN_ADMIN_EMAIL);
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
      $ar = [];
      $this->mailer->Subject = $ar['title'] = $cfg['subject'] ?? ($cfg['title'] ?? '');
      if (!isset($cfg['text'])) {
        $cfg['text'] = '';
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
      $ar['text'] = $cfg['text'];
      $ar['text'] = $renderer($ar);
      self::setContent($ar['text']);
      $this->mailer->msgHTML(self::$_content, $this->path, true);
      $r = $this->mailer->send();
      if ($r && !empty($this->imap_string)) {
        $mail_string = $this->mailer->getSentMIMEMessage();
        if (!is_resource($this->imap)) {
          $this->imap = \imap_open($this->imap_string, $this->imap_user, $this->imap_pass);
        }
        if (!is_resource($this->imap) || !\imap_append($this->imap, $this->imap_string.$this->imap_sent, $mail_string, "\\Seen")) {
          $this->log(\imap_errors());
        }
      }
      if (!$r) {
        $this->log(\imap_last_error());
      }
    }
    $this->mailer->ClearAllRecipients();
    $this->mailer->ClearAttachments();
    return $r;
  }
}