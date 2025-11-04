<?php

namespace bbn\Appui;

use utf8;
use Exception;
use bbn\Mail;
use bbn\X;
use bbn\Str;
use HTMLPurifier_Config;
use HTMLPurifier_URIScheme;
use HTMLPurifier;
use HTMLPurifier_HTML5Config;
use IMAP\Connection;
use bbn\Models\Cls\Basic;
use Generator;
use bbn\User;
use bbn\File\Dir;

/*class HTMLPurifier_URIScheme_data extends HTMLPurifier_URIScheme {

  public $default_port = null;
  public $browsable = false;
  public $hierarchical = true;

  public function validate(&$uri, $config, $context) {
    return true;
  }

}*/

class Mailbox extends Basic
{

  /**
   * @var int The default delay between each ping
   */
  private static $_default_ping_interval = 2;

  /**
   * @var array The possible address fields
   */
  private static $_dest_fields = ['to', 'from', 'cc', 'bcc', 'reply_to'];

  /**
   * @var float Last time server was pinged
   */
  private $_last_ping = 0;

  private $_htmlmsg = '';

  private $_htmlmsg_noimg = '';

  private $_plainmsg = '';

  private $_charset = '';

  private $_attachments = [];

  private $_inline_files = [];

  /**
   * @var int The minimum delay between each ping for the current connection
   */
  private $_ping_interval;

  /**
   * @var string The host address
   */
  protected $host;

  /**
   * @var string The connection type
   */
  protected $type = 'imap';

  /**
   * @var string The login
   */
  protected $login;

  /**
   * @var string The password
   */
  protected $pass;

  /**
   * @var string The current folder
   */
  protected $folder = '';

  /**
   * @var int The remote port
   */
  protected $port;

  /**
   * @var string The mailbox parameters
   */
  protected $mbParam;

  /**
   * @var string The unique hash of the mailbox
   */
  protected $hash;

  /**
   * @var string The status of the connection (should be ok)
   */
  protected $status = '';

  /**
   * @var int The last UID in the mailbox
   */
  protected $last_uid;

  /**
   * @var int The number of messages ion the current mailbox
   */
  protected $num_msg;

  /**
   * @var Connection The stream object
   */
  protected $stream;

  /**
   * @var array The mail folders
   */
  protected $folders = [];

  protected $mailer;

  protected $ssl = false;

  protected $idleStream;

  protected $idleLastTime;

  protected $idleLastCommand;

  protected $idleTag;

  protected $idleTagPrefix = 'BBN_';

  protected $idleRunning = false;

  public static function setDefaultPingInterval(int $val): void
  {
    self::$_default_ping_interval = $val;
  }


  public static function getDefaultPingInterval(int $val): int
  {
    return self::$_default_ping_interval;
  }


  public static function getDestFields(): array
  {
    return self::$_dest_fields;
  }


  public function __construct($cfg)
  {
    if (\is_array($cfg)) {
      if (!empty($cfg['type'])) {
        $this->type = $cfg['type'];
      }

      $this->host           = $cfg['host'] ?? null;
      $this->login          = $cfg['login'];
      $this->pass           = $cfg['pass'];
      $this->folder         = $cfg['dir'] ?? 'INBOX';
      $this->_ping_interval = self::$_default_ping_interval;
      $this->ssl            = !empty($cfg['ssl']);

      switch ($this->type){
        case 'hotmail':
          $this->port    = 993;
          $this->mbParam = '{imap-mail.outlook.com:' . $this->port . '/pop3/ssl}';
          break;
        case 'gmail':
          $this->port    = 993;
          $this->mbParam = '{imap.googlemail.com:' . $this->port . '/imap/ssl}';
          break;
        /*
        case 'pop':
          $this->port    = 110;
          $this->mbParam = '{' . $this->host . ':' . $this->port . '/pop3}';
          break;
        */
        case 'imap':
          if ($this->ssl) {
            $this->port    = 993;
            $this->mbParam = '{' . $this->host . ':' . $this->port . '/imap/ssl/novalidate-cert}';
          }
          else {
            $this->port    = 143;
            $this->mbParam = '{' . $this->host . ':' . $this->port . '/imap/tls/novalidate-cert}';
          }
          break;
        case 'local':
          $this->host = 'localhost';
          $this->port = 143;
          //$this->mbParam = '{' . $this->host . ':' . $this->port . '/imap/tls/novalidate-cert}';
          $this->mbParam = '{' . $this->host . ':' . $this->port . '/imap/notls}';
          break;
      }

      $this->hash = md5($this->mbParam . '-' . $this->login);
      $this->connect();
    }
  }



  /**
   *  Closes the imap stream.
   *
   */
  public function __destruct()
  {
    if ($this->stream) {
      imap_close($this->stream);
    }
  }


  public function getError(): ?string
  {
    return imap_last_error() ?: null;
  }

  public function getMailer(array $cfg = []): Mail
  {
    if (!$this->mailer) {
      $this->mailer = new Mail(X::mergeArrays($cfg, [
        'host'  => $this->host,
        'user' => $this->login,
        'pass'  => $this->pass,
        'type'  => $this->type
      ]));
    }

    return $this->mailer;
  }


  public function setPingInterval(int $val): self
  {
    $this->_ping_interval = $val;
    return $this;
  }


  public function getStatus(): string
  {
    return $this->status;
  }


  public function getHost(): string
  {
    return $this->host;
  }


  public function getFolder(): string
  {
    return $this->folder;
  }


  public function getFolders(): array
  {
    return $this->folders;
  }


  public function getLogin(): string
  {
    return $this->login;
  }


  public function getPort(): int
  {
    return $this->port;
  }


  public function getParams(): string
  {
    return $this->mbParam;
  }


  public function getHash(): string
  {
    return $this->hash;
  }


  public function getLastUid(): ?int
  {
    if (!$this->stream)
      return null;
    $msg_nums = imap_search($this->stream, 'ALL');

    if ($msg_nums) {
      $last_msg_num = max($msg_nums);
      return imap_uid($this->stream, $last_msg_num);
    }

    return null;
  }

  public function getFirstUid(): ?int
  {
    if (!$this->stream)
      return null;
    $msg_nums = imap_search($this->stream, 'ALL');

    if ($msg_nums) {
      $first_msg_num = min($msg_nums);
      return imap_uid($this->stream, $first_msg_num);
    }

    return null;
  }

  public function getNextUid(int $uid): ?int
  {
    if (!$this->stream)
      return null;
    $emails = imap_search($this->stream, 'ALL');

    if ($emails) {
      $emails = array_filter($emails, function($val) use ($uid){
        return $val > $uid;
      });
      if ($emails) {
        return min($emails);
      } else {
        return $uid;
      }
    }

    return null;
  }

  public function getNumMsg(): int
  {
    return $this->num_msg;
  }


  public function getStream()
  {
    return $this->stream;
  }


  public function check(): bool
  {
    return $this->_is_connected();
  }


  /**
   * Gets IMAP essential info (Test: ok)
   *
   * @return object|bool
   */
  public function getImap()
  {
    return $this->update();
  }


  /**
   * Gets IMAP essential info (Test: ok)
   *
   * @return object|bool
   */
  public function update(string|null $dir = null)
  {
    if (($dir = $this->selectFolder($dir))
      && ($imap = imap_check($this->stream))
    ) {
      if ($imap->Nmsgs > 0) {
        $this->folders[$dir]['last_uid']   = $this->getMsgUid($imap->Nmsgs);
        $this->folders[$dir]['num_msg']    = $imap->Nmsgs;
        $this->folders[$dir]['last_check'] = microtime(true);
      }
      else {
        $this->folders[$dir]['last_uid']   = 0;
        $this->folders[$dir]['num_msg']    = 0;
        $this->folders[$dir]['last_check'] = microtime(true);
      }

      return $imap;
    }

    return false;
  }


  /**
   * Creates a mailbox (Test: ok)
   *
   * @param string $mbox Mailbox name
   * @return bool
   */
  public function createMbox($mbox)
  {
    if ($this->_is_connected()) {
      if(imap_createmailbox($this->stream, $this->mbParam. $mbox)) {
        return true;
      }
      X::log(imap_errors(), "imap");
    }

    return false;
  }


  /**
   * Deletes a mailbox (Test: ok)
   *
   * @param string $mbox Mailbox
   * @return bool
   */
  public function deleteMbox($mbox)
  {
    if ($this->_is_connected()) {
      if (imap_deletemailbox($this->stream, $this->mbParam . $mbox)) {
        return true;
      }

      X::ddump($this->getError());
    }

    return false;
  }


  /**
   * Renames a mailbox (Test: ok)
   *
   * @param string $old Old mailbox name
   * @param string $new New mailbox name
   * @return bool
   */
  public function renameMbox($old, $new)
  {
    if ($this->_is_connected()) {
      if (imap_renamemailbox($this->stream, $this->mbParam. $old, $this->mbParam. $new)) {
        return true;
      } else {
        X::ddump(imap_last_error());
      }
    } else {
      X::ddump("Not connected");
    }

    return false;
  }


  /**
   * Returns an array of all the mailboxes that you have subscribed. (Test: ok)
   *
   * @return bool|array
   */
  public function listAllSubscribed()
  {
    return $this->_list_subscribed('*');
  }


  /**
   * Returns an array containing the names of the current level mailboxes that you have subscribed. (Test: ok)
   *
   * @param string $dir Current mailbox folder
   * @return bool|array
   */
  public function listCurlevSubscribed($dir='')
  {
    return $this->_list_subscribed($dir . '%');
  }


  /**
   * Returns an array containing the full names of the all mailboxes.  (Test: ok)
   *
   * @return bool|array
   */
  public function listAllFolders()
  {
    return $this->_list_folders('*');
  }


  /**
   * Returns an array containing the full names of the current level mailboxes.  (Test: ok)
   *
   * @param string $dir Current mailbox folder
   * @return bool|array
   */
  public function listCurlevFolders($dir='')
  {
    return $this->_list_folders($dir . '%');
  }


  /**
   * Returns an array of objects for all mailboxes containing detailed mailbox information. (Test: ok)
   *
   * @return array|bool
   */
  public function getAllFolders()
  {
    return $this->_get_folders('*');
  }


  /**
   * Returns an array of objects for each current level mailboxes containing detailed mailbox information. (Test: ok)
   *
   * @param string $dir Mailbox folder
   * @return array|bool
   */
  public function getCurlevFolders($dir='')
  {
    return $this->_get_folders($dir . '%');
  }


  /**
   * Returns a sorted array containing the simple names of the all mailboxes. (Test: ok)
   *
   * @return array
   */
  public function getAllNamesFolders()
  {
    return $this->_get_names_folders('*');
  }


  /**
   * Returns a sorted array containing the simple names of the current level mailboxes. (Test: ok)
   *
   * @param string $dir Current mailbox folder
   * @return array
   */
  public function getCurlevNamesFolders($dir='')
  {
    return $this->_get_names_folders($dir . '%');
  }


  /**
   * Reopens the desired mailbox (you can give it the simple name or the full name). (Test: ok)
   * If the given name is not existing it opens the default inbox.
   *
   * @param null|string $folder Simple/full mailbox name
   * @return null|string
   */
  public function selectFolder(string|null $folder = null): ?string
  {
    if ($this->_is_connected()) {
      if (!$folder || ($this->folder === $folder)) {
        return $folder ?: $this->folder;
      }

      if (\in_array($folder, $this->getAllNamesFolders())) {
        $res = imap_reopen($this->stream, $this->mbParam . $folder);
      }
      else {
        $res = imap_reopen($this->stream, $folder);
      }

      if ($res) {
        if (!isset($this->folders[$folder])) {
          $this->folders[$folder] = [
            'last_uid' => null,
            'num_msg'  => null,
            'last_check' => null
          ];
        }

        $this->folder = $folder;
        return $folder;
      }
    }

    return null;
  }


  /**
   * Returns an object containing the current mailbox info. (Test: ok)
   *
   * @return bool|object
   */
  public function getInfoFolder(string|null $dir = null)
  {
    if ($this->_is_connected()) {
      if (!$dir || $this->selectFolder($dir)) {
        return imap_mailboxmsginfo($this->stream);
      }
    }

    return false;
  }

  private function transformString($string) {
    // Use the md5 hash function to generate a 32-character hexadecimal string
    $hash = md5($string);

    // Initialize an empty result variable
    $result = '';

    // Loop through the characters of the hash string
    for ($i = 0; $i < strlen($hash); $i++) {
      // Get the current character
      $char = $hash[$i];

      // Append the current character to the result string
      $result .= $char;

      // If the current position is a multiple of 4, append a dash
      if (($i + 1) % 4 == 0 && $i != 31) {
        $result .= '-';
      }
    }

    // Return the final result
    return $result;
  }


  // read this https://www.rfc-editor.org/rfc/rfc1342 to understand the utility of this function
  private function decode_encoded_words($string) {

    preg_match_all("/=\?([^?]+)\?([QqBb])\?([^?]+)\?=/", $string, $matches);
    if (!empty($matches)) {
      for ($i = 0; $i < count($matches[0]); $i++) {
        $encoding = $matches[2][$i];
        $encoded_text = $matches[3][$i];
        if (strtolower($encoding) == "q") {
          $decoded_text = quoted_printable_decode(str_replace("_", " ", $encoded_text));
        } else {
          $decoded_text = base64_decode($encoded_text);
        }
        $string = str_replace($matches[0][$i], $decoded_text, $string);
      }
    }

    return $string;
  }

  private function decode_encoded_words_array(array $array) {
    for($i = 0; $i < count($array); $i++) {
      $array[$i] = $this->decode_encoded_words($array[$i]);
    }
    return $array;
  }

  private function decode_encoded_words_deep($obj) {
    if (is_string($obj)) {
      $obj = $this->decode_encoded_words($obj);
    }
    elseif (is_object($obj)) {
      foreach ($obj as $idx => $val) {
        $obj->$idx = $this->decode_encoded_words_deep($val);
      }
    }

    return $obj;
  }

  public function getEmailsList(array $folder, int $start, int $end, bool $generator = false)
  {
    if (isset($this->folders[$folder['uid']])
      && $this->selectFolder($folder['uid'])
    ) {
      $res = [];
      function analyzeParts($parts, &$t) {
        foreach ($parts as $part) {
          if ($part->ifdisposition
            && ((strtolower($part->disposition) === 'attachment')
              || (strtolower($part->disposition) === 'inline'))
            && $part->ifparameters
            && ($name_row = X::getRow($part->parameters, ['attribute' => 'name']))
          ) {
            $a = [
              'id' => !empty($part->id) ? substr($part->id, 1, -1) : null,
              'name' => $name_row->value,
              'size' => $part->bytes,
              'type' => Str::fileExt($name_row->value) ?: strtolower($part->subtype)
            ];
            $t['attachments'][] = $a;
            if ((strtolower($part->disposition) === 'inline')) {
              $t['inline'][] = $a;
            }
          }
          elseif (!empty($part->parts)) {
            analyzeParts($part->parts, $t);
          }
          elseif ($part->subtype === 'HTML') {
            $t['is_html'] = true;
          }
        }
      }
      while ($start >= $end) {
        try {
          $msgHeaderInfo = $this->getMsgHeaderinfo($start);
          if (empty($msgHeaderInfo)) {
            $start--;
            continue;
          }

          $tmp = (array)$this->decode_encoded_words_deep($msgHeaderInfo);
          // imap decode the subject
          if (!empty($tmp['subject'])
            && is_string($tmp['subject'])
            && ($tmps = imap_mime_header_decode($tmp['subject']))
          ) {
            $tmp['subject'] = $tmps[0]->text;
          }

          // for each string in the array decode quoted printable
          foreach ($tmp as $key => $value) {
            if (is_string($value)) {
              $tmp[$key] = quoted_printable_decode($value);
            }
          }

          // to fetch the message priority
          $msg_header = $this->getMsgHeader($start);
          preg_match('/X-Priority: ([0-9])/', $msg_header, $matches);
          $priority = $matches[1] ?? 3;

          $structure = $this->getMsgStructure($start);
          if (!$tmp || !$structure) {
            $start--;
            continue;
          }

          $tmp['date_sent'] = date('Y-m-d H:i:s', strtotime($tmp['Date']));
          $tmp['date_server'] = date('Y-m-d H:i:s', strtotime($tmp['MailDate']));
          $tmp['uid'] = $this->getMsgUid($start);
          $tmp['priority'] = $priority;
          unset(
            $tmp['Date'],
            $tmp['MailDate'],
            $tmp['Subject'],
            $tmp['sender'],
            $tmp['toaddress'],
            $tmp['fromaddress'],
            $tmp['senderaddress'],
            $tmp['reply_toaddress']
          );
          foreach ($tmp as $k => $v) {
            if (is_string($v)) {
              $tmp[$k] = Str::toUtf8(trim($v));
              if (empty($v)) {
                $tmp[$k] = false;
              } elseif (Str::isNumber($v)) {
                $tmp[$k] = (int)$v;
              } elseif ($k === 'subject') {
                $tmp[$k] = $this->decode_encoded_words_deep($v);
                if (mb_detect_encoding($v) !== 'UTF-8') {
                  $tmp[$k] = mb_convert_encoding(iconv_mime_decode($v, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, "UTF-8"), "UTF-8");
                }
                if (strlen($tmp[$k]) > 1000) {
                  $tmp[$k] = Str::cut($tmp[$k], 1000);
                }
              }
            }
          }

          foreach (self::getDestFields() as $df) {
            if (!empty($tmp[$df])) {
              $ads = [];
              foreach ($tmp[$df] as $a) {
                if (isset($a->host)) {
                  $ads[] = [
                    'name' => empty($a->personal) ? null : mb_convert_encoding(iconv_mime_decode($a->personal, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, "UTF-8"), "UTF-8"),
                    'email' => strtolower($a->mailbox . '@' . $a->host),
                    'host' => $a->host
                  ];
                } else {
                  $this->log((array)$a);
                }
              }

              $tmp[$df] = $ads;
            }
          }

          $tmp['references'] = empty($tmp['references']) ? [] : X::split(substr($tmp['references'], 1, -1), '> <');
          if (!isset($tmp['subject'])) {
            $tmp['subject'] = '';
          }
          $tmp['message_id'] = isset($tmp['message_id']) ? substr($tmp['message_id'], 1, -1) : $this->transformString($tmp['uid'] ?? "" . $tmp['date_sent'] ?? "" . $tmp['subject'] ?? "") . '@bbn.so';

          $tmp['in_reply_to'] = empty($tmp['in_reply_to']) ? false : substr($tmp['in_reply_to'], 1, -1);
          $tmp['attachments'] = [];
          $tmp['inline'] = [];
          $tmp['is_html'] = false;
          if (empty($structure->parts)) {
            $tmp['is_html'] = $structure->subtype === 'HTML';
          }
          else {
            analyzeParts($structure->parts, $tmp);
          }

          $res[] = $tmp;
          $start--;
          if (!empty($generator)) {
            yield $tmp;
          }
        } catch (\Exception $e) {
          X::log([
            'error' => "An error occured when trying to get the message $start " . $e->getMessage(),
            'tmp' => $tmp,
            'start' => $start,
            'header' => $this->getMsgHeader($start),
          ], 'poller_email_error');
          $start--;
        }
      }

      return $res;
    }
  }


  /**
   *
   *
   * @param int $msgno
   */
  public function getMsg($msgno)
  {
    // input $mbox = IMAP stream, $msgno = message id
    // output all the following:
    $this->_htmlmsg     = '';
    $this->_plainmsg    = '';
    $this->_charset     = '';
    $this->_attachments = [];
    $this->_inline_files = [];



    // check if "BBN_USER_PATH . 'tmp_mail'" directory exists


    // HEADER
    $res = (array)$this->decode_encoded_words_deep($this->getMsgHeaderinfo($msgno));
    // add code here to get date, from, to, cc, subject...
    // BODY STRUCTURE

    // decode the subject
    if (!empty($res['subject'])
      && is_string($res['subject'])
      && ($tmps = imap_mime_header_decode($res['subject']))
    ) {
      $res['subject'] = $tmps[0]->text;
    }

    // decode quoted printable
    foreach ($res as $key => $value) {
      if (is_string($value)) {
        $res[$key] = quoted_printable_decode($value);
      }
    }

    $structure = $this->decode_encoded_words_deep($this->getMsgStructure($msgno));
    if (empty($structure->parts)) {  // simple
      $this->_get_msg_part($msgno, $structure, 0);  // pass 0 as part-number
    }
    else {  // multipart: cycle through each part
     // X::ddump($structure);
      foreach ($structure->parts as $partno0 => $p){
        $this->_get_msg_part($msgno, $p, $partno0 + 1);
      }
    }

    if ($res['html'] = $this->_htmlmsg) {
      // replace cid links by name
      $res['html'] = preg_replace_callback(
        '/src="cid:(.*?)"/',
        function ($m) use($msgno) {
          $res = $m[0];
          $cid = $m[1];
          // get the name of the file with the cid in inline array
          if (($att = X::getRow($this->_inline_files, ['id' => $cid]))
            && ($att = $this->getAttachments($msgno, $att['name']))
          ) {
            $ext = Str::fileExt($att['name']);
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])
              || in_array($att['type'], ['jpeg', 'png', 'gif'])
            ) {
              $type = 'image/'.($ext ?: $att['type']);
              $base64 = 'src="data:'.$type.';base64,'.base64_encode($att['data']).'"';
              if ($att) {
                $res = $base64;
              }
            }
          }

          return $res;
        },
        $res['html']
      );

      if ($res['html']) {
        $save = $res['html'];
        try {
          /*$config = HTMLPurifier_HTML5Config::createDefault();
          $config->set('URI.AllowedSchemes', [
            'http' => true,
            'https' => true,
            'mailto' => true,
            'ftp' => true,
            'nntp' => true,
            'news' => true,
            'tel' => true,
          ]);
          //\HTMLPurifier_URISchemeRegistry::instance()->register("data", new HTMLPurifier_URIScheme_data());
          $purifier    = new HTMLPurifier($config);
          $res['html'] = $purifier->purify(quoted_printable_decode($res['html']));*/
          $res['html'] = Str::toUtf8($res['html']);
        } catch (\Exception $e) {
          X::log([
            'error' => $e->getMessage(),
            'html' => $save
          ], 'htmlpurifier');
          $res['html'] = '';
        }
      }
    }

    $res['plain']      = Str::toUtf8($this->_plainmsg);
    $res['charset']    = $this->_charset;
    $res['attachment'] = $this->_attachments;
    $res['inline']     = $this->_inline_files;

    // set res to string and quoted printable decode
    foreach ($res as $k => $v) {
      if (is_string($v) && ($k !== 'html')) {
        $res[$k] = Str::toUtf8(quoted_printable_decode($v));
      }
    }

    return $res;
  }


  /**
   * Sorts the mailbox. (Test: ok)
   * Criteria can be one (and only one) of the following:
   * SORTDATE - message Date
   * SORTARRIVAL - arrival date
   * SORTFROM - mailbox in first From address
   * SORTSUBJECT - message subject
   * SORTTO - mailbox in first To address
   * SORTCC - mailbox in first cc address
   * SORTSIZE - size of message in octets
   *
   * @param string $criteria Pass it without quote or double quote
   * @param string $reverse  Set this to 1 for reverse sorting
   * @return array|bool
   */
  public function sortFolder($criteria, $reverse = 0)
  {
    if ($this->_is_connected() && !empty($criteria)) {
      return imap_sort($this->stream, constant(strtoupper($criteria)), $reverse, SE_NOPREFETCH);
    }

    return false;
  }

  public function getThreads()
  {
    if ($this->_is_connected()) {
      return imap_thread($this->stream);
    }

    return false;
  }

  /**
   * Returns an array containing the full names of the all mailboxes.  (Test: ok)
   *
   * @todo Remove
   * @return bool|array
   */
  public function listAllMboxes()
  {
    return $this->_list_mboxes('*');
  }


  /**
   * Returns an array containing the full names of the current level mailboxes.  (Test: ok)
   *
   * @todo Remove
   * @param string $dir Current mailbox folder
   * @return bool|array
   */
  public function listCurlevMboxes($dir='')
  {
    return $this->_list_mboxes($dir . '%');
  }


  /**
   * Returns an array of objects for all mailboxes containing detailed mailbox information. (Test: ok)
   *
   * @todo Remove
   * @return array|bool
   */
  public function getAllMboxes()
  {
    return $this->_get_mboxes('*');
  }


  /**
   * Returns an array of objects for each current level mailboxes containing detailed mailbox information. (Test: ok)
   *
   * @todo Remove
   * @param string $dir Mailbox folder
   * @return array|bool
   */
  public function getCurlevMboxes($dir='')
  {
    return $this->_get_mboxes($dir . '%');
  }


  /**
   * Returns a sorted array containing the simple names of the all mailboxes. (Test: ok)
   *
   * @todo Remove
   * @return array
   */
  public function getAllNamesMboxes()
  {
    return $this->_get_names_mboxes('*');
  }


  /**
   * Returns a sorted array containing the simple names of the current level mailboxes. (Test: ok)
   *
   * @todo Remove
   * @param string $dir Current mailbox folder
   * @return array
   */
  public function getCurlevNamesMboxes($dir='')
  {
    return $this->_get_names_mboxes($dir . '%');
  }


  /**
   * Reopens the desired mailbox (you can give it the simple name or the full name). (Test: ok)
   * If the given name is not existing it opens the default inbox.
   *
   * @todo Remove
   * @param string $mbox Simple/full mailbox name
   * @return bool
   */
  public function reopenMbox(string $mbox): bool
  {
    return $this->selectFolder($mbox);
  }


  /**
   * Returns an object containing the current mailbox info. (Test: ok)
   *
   * @return bool|object
   */
  public function getInfoMbox()
  {
    if ($this->_is_connected()) {
      return imap_mailboxmsginfo($this->stream);
    }

    return false;
  }


  /**
   * Sorts the mailbox. (Test: ok)
   * Criteria can be one (and only one) of the following:
   * SORTDATE - message Date
   * SORTARRIVAL - arrival date
   * SORTFROM - mailbox in first From address
   * SORTSUBJECT - message subject
   * SORTTO - mailbox in first To address
   * SORTCC - mailbox in first cc address
   * SORTSIZE - size of message in octets
   *
   * @param string $criteria Pass it without quote or double quote
   * @param string $reverse  Set this to 1 for reverse sorting
   * @return array|bool
   */
  public function sortMbox($criteria, $reverse = 0)
  {
    if ($this->_is_connected() && !empty($criteria)) {
      return imap_sort($this->stream, constant(strtoupper($criteria)), $reverse, SE_NOPREFETCH);
    }

    return false;
  }


  /**
   * Retrieves the header's message info. (Test: ok)
   *
   * @param int $msgnum
   * @return bool|object
   */
  public function getMsgHeaderinfo(int $msgnum)
  {
    if ($msgnum && $this->_is_connected()) {
      try {
        $res = imap_headerinfo($this->stream, $msgnum);
      }
      catch (\Exception $e) {
        $this->log($e->getMessage().' '.(string)$msgnum);
      }

      if (!$res) {
        X::log($msgnum, 'bad_numbers');
      }
    }

    return $res ?? null;
  }


  /**
   * Gets the UID of the message. (Test: ok)
   *
   * @param int $msgnum
   * @return bool|int
   */
  public function getMsgUid($msgnum)
  {
    if ($msgnum && $this->_is_connected()) {
      return imap_uid($this->stream, $msgnum);
    }

    return false;
  }


  /**
   * Gets the NO of the message. (Test: ok)
   *
   * @param int $msguid UID of the message
   * @return bool|int
   */
  public function getMsgNo(int $msguid)
  {
    if ($this->_is_connected()) {
      return imap_msgno($this->stream, $msguid);
    }

    return false;
  }


  /**
   * Fetches the message structure. (Test: ok)
   *
   * @param int $msgnum No of the message
   * @return bool|object
   */
  public function getMsgStructure(int $msgnum)
  {
    if ($this->_is_connected()) {
      try {
        $res = imap_fetchstructure($this->stream, $msgnum);
      }
      catch (Exception $e) {
        $this->log($e->getMessage().' '.(string)$msgnum);
      }

      return $res ?: null;
    }

    return null;
  }


  /**
   * Fetches the header of the message. (Test: ok)
   *
   * @param int      $msgnum No of the message
   * @param int|bool $uid    Set true f the msgnum is a UID
   * @return bool|string
   */
  public function getMsgHeader($msgnum, $uid = false)
  {
    if ($this->_is_connected()) {
      try {
        if ($uid) {
          $res = imap_fetchheader($this->stream, $msgnum, FT_UID);
        }

        $res = imap_fetchheader($this->stream, $msgnum);
      }
      catch (Exception $e) {
        $this->log($e->getMessage().' '.(string)$msgnum);
      }

      return $res ?: null;
    }

    return null;
  }


  /**
   * Mark the specified message for deletion from current mailbox. (Text: ok)
   *
   * @param int $uid UID of the message
   * @return bool
   */
  public function deleteMsg($uid)
  {
    if ($this->_is_connected()) {
      return imap_delete($this->stream, $this->getMsgNo($uid));
    }

    return false;
  }


  /**
   * Move the specified message to specified mailbox. (Test: ok)
   *
   * @param int    $uid    UID of the message
   * @param string $tombox Destination mailbox name
   * @return bool
   */
  public function moveMsg($uid, $tombox)
  {
    if ($this->_is_connected()) {
      return imap_mail_move($this->stream, $uid, $tombox, CP_UID);
    }

    return false;
  }


  /**
   * Deletes all messages marked for deletion by delete_msg(), move_msg() or set_flag().  (Test: ok)
   *
   * @return bool
   */
  public function expunge()
  {
    if ($this->_is_connected()) {
      return imap_expunge($this->stream);
    }

    return false;
  }


  /**
   * Fetches the body of the message. (Test: ok)
   *
   * @param int          $msgno No of the message
   * @param string|false $part  The part number
   * @return bool|string
   */
  public function getMsgBody($msgno, $part)
  {
    if ($this->_is_connected()) {
      if (empty($part)) {
        return imap_body($this->stream, $msgno);
      }

      return imap_fetchbody($this->stream, $msgno, $part);
    }

    return false;
  }


  /**
   * Sets or removes flag/s on message/s. (Test: ok)
   * The flags which you can set are \\Seen, \\Answered, \\Flagged, \\Deleted, and \\Draft. (Test: ok)
   *
   * @param string $seq    A sequence of message numbers. Ex. "2,5,6" or "2:5:6"
   * @param string $flg    The flag/s. Ex. "\\Seen \\Flagged"
   * @param bool   $remove Set this to true to remove flag/s
   * @return bool
   */
  public function setMsgFlag($seq, $flg, $remove=false)
  {
    if ($this->_is_connected()) {
      return $remove ? imap_clearflag_full($this->stream, $seq, $flg) : imap_setflag_full($this->stream, $seq, $flg);
    }

    return false;
  }


  /**
   * Search messages. (Test: ok)
   * Returns an array of UIDs.
   *
   * Arguments:
   * ALL - return all messages matching the rest of the criteria
   * ANSWERED - match messages with the \\ANSWERED flag set
   * BCC "string" - match messages with "string" in the Bcc: field
   * BEFORE "date" - match messages with Date: before "date"
   * BODY "string" - match messages with "string" in the body of the message
   * CC "string" - match messages with "string" in the Cc: field
   * DELETED - match deleted messages
   * FLAGGED - match messages with the \\FLAGGED (sometimes referred to as Important or Urgent) flag set
   * FROM "string" - match messages with "string" in the From: field
   * KEYWORD "string" - match messages with "string" as a keyword
   * NEW - match new messages
   * OLD - match old messages
   * ON "date" - match messages with Date: matching "date"
   * RECENT - match messages with the \\RECENT flag set
   * SEEN - match messages that have been read (the \\SEEN flag is set)
   * SINCE "date" - match messages with Date: after "date"
   * SUBJECT "string" - match messages with "string" in the Subject:
   * TEXT "string" - match messages with text "string"
   * TO "string" - match messages with "string" in the To:
   * UNANSWERED - match messages that have not been answered
   * UNDELETED - match messages that are not deleted
   * UNFLAGGED - match messages that are not flagged
   * UNKEYWORD "string" - match messages that do not have the keyword "string"
   * UNSEEN - match messages which have not been read yet
   *
   * @param string $criteria
   * @return array|bool
   */
  public function search($criteria)
  {
    if ($this->_is_connected()) {
      return imap_search($this->stream, $criteria, SE_UID);
    }

    return false;
  }


  /**
   *  Appends a string message to a specified mailbox. (Test: ok)
   *
   * @param string $mbox Destination mailbox name
   * @param string $msg  Message
   * @return bool
   */
  public function append($mbox, $msg)
  {
    if ($this->_is_connected()) {
      return @imap_append($this->stream, $this->mbParam. $mbox, $msg);
    }

    return false;
  }


  /**
   * Check if a mailbox exists
   *
   * @param string $name The mailbox name
   * @return bool
   */
  public function mboxExists($name)
  {
    if ($this->_is_connected() && !empty($name)) {
      $names = $this->getAllNamesFolders();
      if (!empty($names) && \in_array($name, $names)) {
        return true;
      }
    }

    return false;
  }


  public function getAttachments(int $msgNum, ?string $filename = null): ?array
  {
    if ($structure = $this->getMsgStructure($msgNum)) {
      $attachments = [];
      if (!empty($structure->parts)) {
        foreach ($structure->parts as $np => $part) {
          $this->analyzeAttachmentPart($part, $attachments, $msgNum, $np + 1, $filename);
          if (!empty($filename) && count($attachments)) {
            return $attachments[0];
          }
        }
      }

      return $attachments;
    }

    return null;
  }


  public function idle(callable $callback, int $timeout = 300): bool
  {
    $this->idleRunning = false;
    $this->idleTag = 0;
    $context = [];
    if ($this->ssl) {
      $context['ssl'] = [
        'verify_peer' => false,
        'verify_peer_name' => false,
      ];
    }

    // Establish the connection
    $this->idleStream = stream_socket_client(
      ($this->ssl ? "ssl" : "tls") . "://{$this->host}:{$this->port}",
      $errno,
      $errstr,
      $timeout,
      STREAM_CLIENT_CONNECT,
      stream_context_create($context)
    );
    if (!$this->idleStream) {
      throw new \Exception(X::_("Failed to connect: %s (%s)", $errstr, $errno));
    }

    try {
      $this->sendCommand("LOGIN {$this->login} {$this->pass}");
      $this->sendCommand("SELECT {$this->folder}");
      //$this->sendCommand("CAPABILITY", false);
      //$callback([$this->readCommandResponse(true), 'idle']);
      $this->sendCommand("IDLE", false);
      $this->idleRunning = true;
      while ($this->idleRunning) {
        try {
          //$response = $this->readCommandResponse();
          $response = $this->readCommandResponseLine();
        }
        catch (\Exception $e) {
          $errCode = $e->getCode();
          if (($errCode === 1) && $this->isIdleConnected()) {
            continue;
          }

          if (!str_contains($e->getMessage(), "connection closed")) {
            throw $e;
          }
        }

        if (($pos = strpos($response, "EXISTS")) !== false) {
          $msgn = (int)substr($response, 2, $pos - 2);

          // Check if the stream is still alive or should be considered stale
          if (!$this->_is_connected() || ($this->idleLastTime < time())) {
            $this->stopIdle();
            // Establish a new connection
            imap_close($this->stream);
            $this->connect();
            return $this->idle($callback, $timeout);
          }

          $this->idleLastTime = time() + $timeout;
          $this->selectFolder($this->folder);
          $callback($this->getMsg($msgn));
        }
      }
    }
    catch (\Exception $e) {
      $this->stopIdle();
      throw $e;
    }

    return true;
  }


  public function idle2(int $timeout = 300): bool
  {
    $this->idleRunning = false;
    $this->idleTag = 0;
    $context = [];
    if ($this->ssl) {
      $context['ssl'] = [
        'verify_peer' => false,
        'verify_peer_name' => false,
      ];
    }

    // Establish the connection
    $this->idleStream = stream_socket_client(
      ($this->ssl ? "ssl" : "tls") . "://{$this->host}:{$this->port}",
      $errno,
      $errstr,
      $timeout,
      STREAM_CLIENT_CONNECT,
      stream_context_create($context)
    );
    if (!$this->idleStream) {
      throw new \Exception(X::_("Failed to connect: %s (%s)", $errstr, $errno));
    }

    try {
      $this->sendCommand("LOGIN {$this->login} {$this->pass}");
      $this->sendCommand("SELECT {$this->folder}");
      $this->sendCommand("CAPABILITY", false);
      //$this->readCommandResponseLine();
  
      $this->sendCommand("IDLE", false);
      $user = User::getInstance();
      $path = $user->getTmpPath() . 'mailbox/' . $this->getHash() . '/';
      if (is_dir($path)) {
        Dir::delete($path, true);
      }

      sleep(1);
      if (Dir::createPath($path)) {
        
      }

      $this->idleRunning = true;
      while ($this->idleRunning) {
        try {
          //$response = $this->readCommandResponse();
          $response = $this->readCommandResponseLine();
        }
        catch (\Exception $e) {
          $errCode = $e->getCode();
          if (($errCode === 1) && $this->isIdleConnected()) {
            continue;
          }

          if (!str_contains($e->getMessage(), "connection closed")) {
            throw $e;
          }
        }

        if (($pos = strpos($response, "EXISTS")) !== false) {
          $msgn = (int)substr($response, 2, $pos - 2);

          // Check if the stream is still alive or should be considered stale
          if (!$this->_is_connected() || ($this->idleLastTime < time())) {
            $this->stopIdle();
            // Establish a new connection
            imap_close($this->stream);
            $this->connect();
            return $this->idle($callback, $timeout);
          }

          $this->idleLastTime = time() + $timeout;
          $this->selectFolder($this->folder);
          $callback($this->getMsg($msgn));
        }
      }
    }
    catch (\Exception $e) {
      $this->stopIdle();
      throw $e;
    }

    return true;
  }


  public function stopIdle()
  {
    $this->idleRunning = false;
    if (!empty($this->idleStream)) {
      fwrite($this->idleStream, "DONE\r\n");
      fclose($this->idleStream);
      $this->idleStream = null;
    }
  }


  public function getIdleStream()
  {
    return $this->idleStream;
  }


  protected function sendCommand(string $command, bool $response = true): string
  {
    $this->idleTag++;
    $this->idleLastCommand = $this->idleTagPrefix . $this->idleTag . ' ' . $command;
    fwrite($this->idleStream, $this->idleLastCommand . "\r\n");
    $this->idleLastTime = time();
    return !empty($response) ? $this->readCommandResponse() : '';
  }


  protected function readCommandResponse(bool $asArray = false): string|array
  {
    $response = [];
    while ($line = trim($this->readCommandResponseLine())) {
      $response[] = $line;
      $prefix = $this->idleTagPrefix . $this->idleTag . ' ';
      if (str_starts_with($line, $prefix)) {
        if (str_starts_with($line, $prefix . 'BAD ')
          || str_starts_with($line, $prefix . 'NO ')
        ) {
          throw new \Exception(X::_('Error response (command: %s): %s', $this->idleLastCommand, $line), 2);
        }

        if (empty($asArray)) {
          return $line;
        }
      }
    }

    return !empty($asArray) ? $response : (!empty($response) ? $response[count($response) - 1] : '');
  }


  protected function readCommandResponseLine(): string
  {
    $line = '';
    while (substr($line, -1) !== "\n") {
      $line .= fgets($this->idleStream, 1024);
    }
    /* while ((($c = fread($this->idleStream, 1)) !== false)
      && !in_array($c, ['', "\n"])
    ) {
      $line .= $c;
    } */

    if ($this->idleRunning
      && ($line === '')
      //&& (($c === false)
      //  || ($c === ''))
    ) {
      throw new \Exception(X::_('Empty response (command: %s)', $this->idleLastCommand), 1);
    }

    return $line;
  }


  protected function isIdleConnected(): bool
  {
    if (!empty($this->idleStream)) {
      try {
        $this->sendCommand("NOOP");
        return true;
      }
      catch (\Exception $e) {
        return false;
      }
    }

    return false;
  }


  protected function connect(): bool
  {
    if (isset($this->mbParam)) {
      $this->stream = imap_open($this->mbParam . $this->folder, $this->login, $this->pass);
      if ($this->stream) {
        $this->folders[$this->folder] = [
          'last_uid' => null,
          'num_msg'  => null,
          'last_check' => null
        ];
        $this->status = 'ok';
        return true;
      }
      else {
        $this->status = imap_last_error();
        return false;
      }
    }

    return false;
  }


  private function analyzeAttachmentPart($part, &$attachments, $msgNum, $partNum, $filename){
    if (!empty($part->ifdisposition)
      && !empty($part->disposition)
      && !empty($part->ifparameters)
      && ((strtolower($part->disposition) === 'attachment')
        || (strtolower($part->disposition) === 'inline'))
      && ($nameParam = X::getRow($part->parameters, ['attribute' => 'name']))
      && (empty($filename) || ($filename === $nameParam->value))
    ) {
      if ($data = $this->getMsgBody($msgNum, $partNum)) {
        $attachments[] = [
          'type' => Str::fileExt($nameParam->value) ?: strtolower($part->subtype),
          'name' => $nameParam->value,
          'size' => $part->bytes,
          'data' => $this->_get_decode_value($data, $part->encoding)
        ];
      }
    }

    if (!empty($part->parts)
      && (empty($filename) || !count($attachments))
    ) {
      foreach ($part->parts as $np2 => $p) {
        $this->analyzeAttachmentPart($p, $attachments, $msgNum, $partNum . '.' . ($np2 + 1), $filename);
        if (!empty($filename) && count($attachments)) {
          break;
        }
      }
    }
  }


  /**
   * Checks if we are connected  (Test: ok)
   *
   * @return bool
   */
  private function _is_connected()
  {
    if ($this->stream) {
      $now = microtime(true);
      if ($now - $this->_last_ping < $this->_ping_interval) {
        return true;
      }

      if (imap_ping($this->stream)) {
        $this->_last_ping = $now;
        return true;
      }
    }

    return false;
  }


  /**
   * Returns an array containing the names of the mailboxes that you have subscribed. (Test: ok)
   *
   * @param string $dir Mailbox folder
   * @return bool|array
   */
  private function _list_subscribed($dir)
  {
    if ($this->_is_connected()) {
      return imap_lsub($this->stream, $this->mbParam, $dir);
    }

    return false;
  }


  /**
   * Returns an array containing the full names of the mailboxes.  (Test: ok)
   *
   * @param string $dir Mailbox folder
   * @return bool|array
   */
  private function _list_folders($dir)
  {
    if ($this->_is_connected()) {
      return imap_list($this->stream, $this->mbParam, $dir);
    }

    return false;
  }


  /**
   * Returns an array of objects containing detailed mailboxes information. (Test: ok)
   *
   * @param string $dir Mailbox folder
   * @return array|bool
   */
  private function _get_folders($dir)
  {
    if ($this->_is_connected()) {
      return imap_getmailboxes($this->stream, $this->mbParam, $dir);
    }

    return false;
  }


  /**
   * Returns a sorted array containing the simple names of the mailboxes. (Test: ok)
   *
   * @param string $dir Mailbox folder
   * @return array
   */
  private function _get_names_folders($dir)
  {
    if ($folders = $this->_get_folders($dir)) {
      $i   = 0;
      $ret = [];
      foreach($folders as $val) {
        $name      = imap_utf7_decode($val->name);
        $name_arr  = explode('}', $name);
        $j         = \count($name_arr) - 1;
        $mbox_name = $name_arr[$j];
        if($mbox_name == "") {
          continue; // the folder itself
        }

        $ret[$i++] = $mbox_name;
      }

      sort($ret);
      return $ret;
    }

    return false;
  }


  /**
   * Decodes message. (Test: ok)
   *
   * @param string $message Messate to decode
   * @param int    $coding  Type of encoding
   * @return string
   */
  public function _get_decode_value($message, $coding)
  {
    switch ($coding) {
      case 0:
        return $message;
      case 1:
        return imap_8bit($message);
      case 2:
        return imap_binary($message);
      case 3:
        return imap_base64($message);
      case 4:
        return imap_qprint($message);
      case 5:
        return imap_base64($message);
      default:
        return $message;
    }
  }


  /**
   *
   *
   * @param $msgno
   * @param $structure
   * @param string|false $partno '1', '2', '2.1', '2.1.3', etc for multipart, 0 if simple
   */
  private function _get_msg_part($msgno, $structure, $partno)
  {
    // PARAMETERS
    // get all parameters, like charset, Filenames of attachments, etc.
    $params = [];
    if (!empty($structure->parameters)) {
      foreach ($structure->parameters as $x){
        $params[strtolower($x->attribute)] = $x->value;
      }
    }

    if (!empty($structure->dparameters)) {
      foreach ($structure->dparameters as $x){
        $params[strtolower($x->attribute)] = $x->value;
      }
    }

    // ATTACHMENT
    // Any part with a filename is an attachment,
    // so an attached text file (type 0) is not mistaken as the message.
    if (!empty($params['filename']) || !empty($params['name'])) {
      // filename may be given as 'Filename' or 'Name' or both
      if (($filename = empty($params['filename']) ? $params['name'] : $params['filename'])
        && isset($structure->ifdisposition)
        && isset($structure->disposition)
      ) {
        $a = [
          'id' => !empty($structure->id) ? substr($structure->id, 1, -1) : null,
          'type' => Str::fileExt($filename) ?: strtolower($structure->subtype),
          'name' => $filename,
          'size' => $params['size'] ?? $structure->bytes ?? 0
        ];
        if (strtolower($structure->disposition) === 'inline') {
          $this->_inline_files[] = $a;
          $this->_attachments[] = $a;
          $this->_htmlmsg .= '<a href="" cid="'. $filename . '">'. $filename . '</a>';
        }
        else if (strtolower($structure->disposition) === 'attachment') {
          $this->_attachments[] = $a;
        }
      }
    }
    else {
      // DECODE DATA
      $data = $this->getMsgBody($msgno, $partno);
      // Any part may be encoded, even plain text messages, so check everything.
      $data = $this->_get_decode_value($data, $structure->encoding);
    }

    // TEXT
    if (($structure->type === 0) && !empty($data)) {
      // Messages may be split in different parts because of inline attachments,
      // so append parts together with blank row.
      $this->_charset = $params['charset'];  // assume all parts are same charset
      if (strtolower($structure->subtype) === 'plain') {
        $this->_plainmsg .= trim(Str::toUtf8($data)).PHP_EOL;
      }
      else {
        $htmlmsg = trim(Str::toUtf8($data));

        if (!empty($htmlmsg)) {
          $body_pattern = "/<body([^>]*)>(.*)<\/body>/smi";
          preg_match($body_pattern, $htmlmsg, $body);
          if (!empty($body[2])) {
            $this->_htmlmsg .= $body[2];
          }

          $img_pattern          = "/<img([^>]+)>/smi";
          $this->_htmlmsg_noimg = preg_replace($img_pattern, '', $this->_htmlmsg);
        }
      }
    }
    // EMBEDDED MESSAGE
    // Many bounce notifications embed the original message as type 2,
    // but AOL uses type 1 (multipart), which is not handled here.
    // There are no PHP functions to parse embedded messages,
    // so this just appends the raw source to the main message.
    elseif (($structure->type === 2) && !empty($data) && strtolower($structure->subtype) === 'plain') {
      if (stripos($this->_charset, 'ISO') !== false) {
        $utfConverter     = new utf8($this->_charset);
        $this->_plainmsg .= $utfConverter->loadCharset($this->_charset) ? $utfConverter->strToUtf8(trim($data)) . PHP_EOL : $this->_plainmsg .= trim(Str::toUtf8($data)) . PHP_EOL;
      }
      else {
        $this->_plainmsg .= trim(Str::toUtf8($data)) . PHP_EOL;
      }
    }

    // SUBPART RECURSION
    if (!empty($structure->parts)) {
      foreach ($structure->parts as $partno0 => $p2){
        $this->_get_msg_part($msgno, $p2, $partno . '.' . ($partno0 + 1));  // 1.2, 1.2.1, etc.
      }
    }
  }




}
