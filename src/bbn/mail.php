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
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 *
 * This cclass
 *
 * Here is an example with gMail
 * <code>
 * $mail = new \bbn\mail([
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
 * $mail = new \bbn\mail([
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

class mail extends obj
{
  private static
    $dest_fields = ['to', 'cc', 'bcc'],
    $default_template = '<!DOCTYPE html><html><head><title>{{title}}</title><meta http-equiv="Content-Type"
content="text/html; charset=UTF-8"></head><body><div>{{{text}}}</div></body></html>',
    $content = '',
    $hash_content;

  public $mailer;

  private
    $template,
    $path,
    $imap_user,
    $imap_pass,
    $imap_sent,
    $imap_string,
    $imap;

  private static function set_content($c){
    $md5 = md5($c);
    if ( $md5 !== self::$hash_content ){
      self::$hash_content = $md5;
      $inliner = new \TijsVerkoyen\CssToInlineStyles\CssToInlineStyles();
      self::$content = $inliner->convert($c);
    }
  }

  public function __construct($cfg) {
    if ( !defined('BBN_ADMIN_EMAIL') || !defined('BBN_IS_DEV') ){
      die("You must provide the constants BBN_ADMIN_EMAIL and BBN_IS_DEV to use the mail class...");
    }
    if ( !isset($cfg['from']) && isset($cfg['user']) ){
      $cfg['from'] = $cfg['user'];
    }
    if ( !isset($cfg['host'], $cfg['from']) || !str::is_domain($cfg['host']) || !str::is_email($cfg['from'])) {
      die("A host name and a \"From\" eMail address must be provided");
    }
    $this->mailer = new \PHPMailer();
    $this->mailer->isSMTP();
    if ( !empty($cfg['ssl']) ){
      if ( is_array($cfg['ssl']) ){
        $this->mailer->SMTPOptions = ['ssl' => $cfg['ssl']];
      }
      else{
        $this->mailer->SMTPSecure = 'ssl';
      }
    }
    else{
      $this->mailer->SMTPSecure = 'tls';
    }
    $this->mailer->CharSet = isset($cfg['charset']) ? $cfg['charset'] : "UTF-8";
    // SMTP connection will not close after each email sent, reduces SMTP overhead
    $this->mailer->SMTPKeepAlive = true;
    $this->mailer->SMTPDebug = empty($cfg['debug']) ? false : 3;
    $this->mailer->Debugoutput = 'error_log';
    $this->mailer->Host = $cfg['host'];
    $this->mailer->Port = isset($cfg['port']) ? $cfg['port'] : 587;
    if ( isset($cfg['user'], $cfg['pass']) ){
      $this->mailer->SMTPAuth = true;
      $this->mailer->Username = $cfg['user'];
      $this->mailer->Password = $cfg['pass'];
      if ( !empty($cfg['imap']) ){
        $this->set_imap($cfg);
      }
    }
    $this->set_from($cfg['from'], isset($cfg['name']) ? $cfg['name'] : 0);
    $this->set_template(isset($cfg['template']) ? $cfg['template'] : self::$default_template);
  }

  public function set_imap($cfg){
    if ( !isset($cfg['imap_user'], $cfg['imap_pass']) && !isset($cfg['user'], $cfg['pass']) ){
      die("You need to provide user and password for IMAP connection");
    }
    $imap_host = isset($cfg['imap_host']) ? $cfg['imap_host'] : $cfg['host'];
    $this->imap_user = isset($cfg['imap_user']) ? $cfg['imap_user'] : $cfg['user'];
    $this->imap_pass = isset($cfg['imap_pass']) ? $cfg['imap_pass'] : $cfg['pass'];
    $this->imap_sent = isset($cfg['imap_sent']) ? $cfg['imap_sent'] : 'Sent';
    if ( isset($cfg['imap_port']) ){
      $imap_port = $cfg['imap_port'];
    }
    if ( !empty($cfg['imap_ssl']) ){
      if ( !isset($cfg['imap_port']) ) {
        $imap_port = 993;
      }
      $this->imap_string = "{".$imap_host.":".$imap_port."/ssl";
    }
    else{
      if ( !isset($cfg['imap_port']) ) {
        $imap_port = 143;
      }
      $this->imap_string = "{".$imap_host.":".$imap_port."/tls";
    }
    if ( empty($cfg['valid']) ){
      $this->imap_string .= "/novalidate-cert";
    }
    $this->imap_string .= "}";
    return $this;
  }

  public function unset_imap(){
    unset($this->imap_string, $this->imap_user, $this->imap_pass);
    return $this;
  }

  public function set_from($email, $name=null){
    if ( !str::is_email($email) ){
      die("The From eMail address is not valid");
    }
    if ( !$name ){
      $name = $email;
    }
    $this->mailer->setFrom($email, $name);
    $this->mailer->addReplyTo($email, $name);
    return $this;
  }

  public function set_template($file){
    if ( is_file($file) ){
      $this->template = file_get_contents($file);
      $this->path = dirname($file);
    }
    else if ( is_string($file) ){
      $this->template = $file;
      $this->path = BBN_DATA_PATH;
    }
    return $this;
  }

  public function get_error(){
    return $this->mailer->ErrorInfo;
  }

  public function send($cfg){
    $valid = false;
    $r = false;
    if ( BBN_IS_DEV ){
      $cfg['to'] = BBN_ADMIN_EMAIL;
      $cfg['cc'] = '';
      $cfg['bcc'] = '';
      $this->mailer->AddAddress(BBN_ADMIN_EMAIL);
      $valid = 1;
    }
    else{
      foreach ( self::$dest_fields as $dest_field ){
        if ( isset($cfg[$dest_field]) ){
          if ( !is_array($cfg[$dest_field]) ){
            $cfg[$dest_field] = array_map(function($a){
              return trim($a);
            }, explode(";", $cfg[$dest_field]));
          }
          foreach ( $cfg[$dest_field] as $dest ){
            if ( \PHPMailer::validateAddress($dest) ){
              switch ( $dest_field ){
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
            else{
              \bbn\x::log("Adresse email invalide: ".$dest);
              $valid = false;
            }
          }
        }
      }
    }
    if ( $valid ){
      $ar = [];
      if ( isset($cfg['subject']) ){
        $ar['title'] = $cfg['subject'];
      }
      else if ( isset($cfg['title']) ){
        $ar['title'] = $cfg['title'];
      }
      $this->mailer->Subject = isset($ar['title']) ? $ar['title'] : '';

      if ( !isset($cfg['text']) ){
        $cfg['text'] = '';
      }
      if ( isset($cfg['attachments']) ){
        if ( is_string($cfg['attachments']) ){
          $cfg['attachments'] = [$cfg['attachments']];
        }
        foreach ( $cfg['attachments'] as $att ){
          if ( is_file($att) ){
            $this->mailer->AddAttachment($att);
          }
        }
      }
      if ( !isset($renderer) ){
        $renderer = \bbn\tpl::renderer($this->template);
      }
      $ar['url'] = defined('BBN_URL') ? BBN_URL : '';
      $ar['text'] = $cfg['text'];
      $ar['text'] = $renderer($ar);
      self::set_content($ar['text']);

      $this->mailer->msgHTML(self::$content, $this->path, true);
      $r = $this->mailer->send();
      if ( $r && !empty($this->imap_string) ){
        $mail_string = $this->mailer->getSentMIMEMessage();
        if ( !is_resource($this->imap) ){
          $this->imap = imap_open($this->imap_string, $this->imap_user, $this->imap_pass);
        }
        if ( !is_resource($this->imap) || !imap_append($this->imap, $this->imap_string.$this->imap_sent, $mail_string, "\\Seen") ){
          $this->log(imap_last_error());
        }
      }
      if ( !$r ){
        $this->log(imap_last_error());
      }
    }
    else{
      $r = false;
    }
    $this->mailer->ClearAllRecipients();
    $this->mailer->ClearAttachments();
    return $r;
  }
}