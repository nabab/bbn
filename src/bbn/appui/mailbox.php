<?php

namespace bbn\appui;

use bbn;
use bbn\x;
use bbn\str;

class mailbox extends bbn\models\cls\basic
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
   * @var stream The stream object
   */
  protected $stream;

  /**
   * @var string The host address
   */
  protected $folders = [];


  public static function set_default_ping_interval(int $val): void
  {
    self::$_default_ping_interval = $val;
  }


  public static function get_default_ping_interval(int $val): int
  {
    return self::$_default_ping_interval;
  }


  public static function get_dest_fields(): array
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
          if (empty($cfg['ssl'])) {
            $this->port    = 143;
            $this->mbParam = '{' . $this->host . ':' . $this->port . '/imap/tls/novalidate-cert}';
          }
          else {
            $this->port    = 993;
            $this->mbParam = '{' . $this->host . ':' . $this->port . '/imap/ssl}';
          }
          break;
        case 'local':
          $this->host = 'localhost';
          $this->port = 143;
          //$this->mbParam = '{' . $this->host . ':' . $this->port . '/imap/tls/novalidate-cert}';
          $this->mbParam = '{' . $this->host . ':' . $this->port . '/imap/notls}';
          break;
      }

      if (isset($this->mbParam)) {
        try {
          $this->stream = imap_open($this->mbParam . $this->folder, $this->login, $this->pass);
        }
        catch (\Exception $e) {
          throw new \Exception($e->getMessage());
        }

        if ($this->stream) {
          $this->folders[$this->folder] = [
            'last_uid' => null,
            'num_msg'  => null,
            'last_check' => null
          ];
          $this->status                 = 'ok';
        }
        else {
          $this->status = imap_last_error();
        }
      }
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


  public function set_ping_interval(int $val): self
  {
    $this->_ping_interval = $val;
    return $this;
  }


  public function get_status(): string
  {
    return $this->status;
  }


  public function get_host(): string
  {
    return $this->host;
  }


  public function get_folder(): string
  {
    return $this->folder;
  }


  public function get_folders(): array
  {
    return $this->folders;
  }


  public function get_login(): string
  {
    return $this->login;
  }


  public function get_port(): int
  {
    return $this->port;
  }


  public function get_params(): string
  {
    return $this->mbParam;
  }


  public function get_last_uid(): int
  {
    return $this->last_uid;
  }


  public function get_num_msg(): int
  {
    return $this->num_msg;
  }


  public function get_stream()
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
  public function get_imap()
  {
    return $this->update();
  }


  /**
   * Gets IMAP essential info (Test: ok)
   *
   * @return object|bool
   */
  public function update(string $dir = null)
  {
    if (($dir = $this->select_folder($dir))
        && ($imap = imap_check($this->stream))
    ) {
      if ($imap->Nmsgs > 0) {
        $this->folders[$dir]['last_uid']   = $this->get_msg_uid($imap->Nmsgs);
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
  public function create_mbox($mbox)
  {
    if ($this->_is_connected()) {
      return imap_createmailbox($this->stream, $this->mbParam. $mbox);
    }

    return false;
  }


  /**
   * Deletes a mailbox (Test: ok)
   *
   * @param string $mbox Mailbox
   * @return bool
   */
  public function delete_mbox($mbox)
  {
    if ($this->_is_connected()) {
      return imap_deletemailbox($this->stream, $this->mbParam . $mbox);
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
  public function rename_mbox($old, $new)
  {
    if ($this->_is_connected()) {
      return imap_renamemailbox($this->stream, $this->mbParam. $old, $this->mbParam. $new);
    }

    return false;
  }


  /**
   * Returns an array of all the mailboxes that you have subscribed. (Test: ok)
   *
   * @return bool|array
   */
  public function list_all_subscribed()
  {
    return $this->_list_subscribed('*');
  }


  /**
   * Returns an array containing the names of the current level mailboxes that you have subscribed. (Test: ok)
   *
   * @param string $dir Current mailbox folder
   * @return bool|array
   */
  public function list_curlev_subscribed($dir='')
  {
    return $this->_list_subscribed($dir . '%');
  }


  /**
   * Returns an array containing the full names of the all mailboxes.  (Test: ok)
   *
   * @return bool|array
   */
  public function list_all_folders()
  {
    return $this->_list_folders('*');
  }


  /**
   * Returns an array containing the full names of the current level mailboxes.  (Test: ok)
   *
   * @param string $dir Current mailbox folder
   * @return bool|array
   */
  public function list_curlev_folders($dir='')
  {
    return $this->_list_folders($dir . '%');
  }


  /**
   * Returns an array of objects for all mailboxes containing detailed mailbox information. (Test: ok)
   *
   * @return array|bool
   */
  public function get_all_folders()
  {
    return $this->_get_folders('*');
  }


  /**
   * Returns an array of objects for each current level mailboxes containing detailed mailbox information. (Test: ok)
   *
   * @param string $dir Mailbox folder
   * @return array|bool
   */
  public function get_curlev_folders($dir='')
  {
    return $this->_get_folders($dir . '%');
  }


  /**
   * Returns a sorted array containing the simple names of the all mailboxes. (Test: ok)
   *
   * @return array
   */
  public function get_all_names_folders()
  {
    return $this->_get_names_folders('*');
  }


  /**
   * Returns a sorted array containing the simple names of the current level mailboxes. (Test: ok)
   *
   * @param string $dir Current mailbox folder
   * @return array
   */
  public function get_curlev_names_folders($dir='')
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
  public function select_folder(string $folder = null): ?string
  {
    if ($this->_is_connected()) {
      if (!$folder || ($this->folder === $folder)) {
        return $folder ?: $this->folder;
      }

      if (\in_array($folder, $this->get_all_names_folders())) {
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
  public function get_info_folder(string $dir = null)
  {
    if ($this->_is_connected()) {
      if (!$dir || $this->select_folder($dir)) {
        return imap_mailboxmsginfo($this->stream);
      }
    }

    return false;
  }


  public function get_emails_list(string $folder, int $start, int $end)
  {
    if (isset($this->folders[$folder]) && ($end > $start) && $this->select_folder($folder)) {
      $res = [];
      while ($start <= $end) {
        $tmp = (array)$this->get_msg_headerinfo($start);
        $structure = $this->get_msg_structure($start);
        $tmp['date_sent'] = date('Y-m-d H:i:s', strtotime($tmp['Date']));
        $tmp['date_server'] = date('Y-m-d H:i:s', strtotime($tmp['MailDate']));
        $tmp['uid'] = $this->get_msg_uid($start);
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
        foreach ($tmp as $k => &$v) {
          if (is_string($v)) {
            $v = trim($v);
            if (empty($v)) {
              $v = false;
            }
            elseif (str::is_number($v)) {
              $v = (int)$v;
            }
          }

          unset($v);
        }

        foreach (self::get_dest_fields() as $df) {
          if (!empty($tmp[$df])) {
            $ads = [];
            foreach ($tmp[$df] as $a) {
              if (isset($a->host)) {
                $ads[] = [
                  'name' => $a->personal ?? null,
                  'email' => strtolower($a->mailbox.'@'.$a->host),
                  'host' => $a->host
                ];
              }
              else {
                $this->log((array)$a);
              }
            }

            $tmp[$df] = $ads;
          }
        }
        $tmp['references']  = empty($tmp['references']) ? [] : x::split(substr($tmp['references'], 1, -1), '> <');
        $tmp['message_id']  = isset($tmp['message_id']) ? substr($tmp['message_id'], 1, -1) : '';
        $tmp['in_reply_to'] = empty($tmp['in_reply_to']) ? false : substr($tmp['in_reply_to'], 1, -1);
        $tmp['attachments'] = [];
        $tmp['is_html']     = false;
        if (empty($structure->parts)) {
          $tmp['is_html'] = $structure->subtype === 'HTML';
        }
        else {
          foreach ($structure->parts as $part) {
            if ($part->ifdisposition && (strtolower($part->disposition) === 'attachment') && $part->ifparameters) {
              $tmp['attachments'][] = [
                'name' => $part->parameters[0]->value,
                'size' => $part->bytes,
                'type' => str::file_ext($part->parameters[0]->value)
              ];
            }
            elseif (!empty($part->parts)) {
              foreach ($part->parts as $p) {
                if ($p->subtype === 'HTML') {
                  $tmp['is_html'] = true;
                  break;
                }
              }
            }
            elseif ($part->subtype === 'HTML') {
              $tmp['is_html'] = true;
            }
          }
        }

        $res[] = $tmp;
        $start++;
      }

      return $res;
    }
  }


  /**
   *
   *
   * @param int $msgno
   */
  public function get_msg($msgno)
  {
    // input $mbox = IMAP stream, $msgno = message id
    // output all the following:
    $this->_htmlmsg     = '';
    $this->_plainmsg    = '';
    $this->_charset     = '';
    $this->_attachments = [];
    if (is_dir(BBN_USER_PATH . 'tmp_mail')) {
      bbn\file\dir::delete(BBN_USER_PATH . 'tmp_mail');
    }

    // HEADER
    $res = (array)$this->get_msg_headerinfo($msgno);
    // add code here to get date, from, to, cc, subject...
    // BODY STRUCTURE
    $structure = $this->get_msg_structure($msgno);
    if (!$structure->parts) {  // simple
      $this->_get_msg_part($msgno, $structure, 0);
    }
    else {  // multipart: cycle through each part
      foreach ($structure->parts as $partno0 => $p){
        $this->_get_msg_part($msgno, $p, $partno0 + 1);
      }
    }

    $res['html']       = $this->_htmlmsg;
    $res['plain']      = $this->_plainmsg;
    $res['charset']    = $this->_charset;
    $res['attachment'] = $this->_attachments;
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
  public function sort_folder($criteria, $reverse = 0)
  {
    if ($this->_is_connected() && !empty($criteria)) {
      return imap_sort($this->stream, constant(strtoupper($criteria)), $reverse, SE_NOPREFETCH);
    }

    return false;
  }


  /**
   * Returns an array containing the full names of the all mailboxes.  (Test: ok)
   *
   * @todo Remove
   * @return bool|array
   */
  public function list_all_mboxes()
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
  public function list_curlev_mboxes($dir='')
  {
    return $this->_list_mboxes($dir . '%');
  }


  /**
   * Returns an array of objects for all mailboxes containing detailed mailbox information. (Test: ok)
   *
   * @todo Remove
   * @return array|bool
   */
  public function get_all_mboxes()
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
  public function get_curlev_mboxes($dir='')
  {
    return $this->_get_mboxes($dir . '%');
  }


  /**
   * Returns a sorted array containing the simple names of the all mailboxes. (Test: ok)
   *
   * @todo Remove
   * @return array
   */
  public function get_all_names_mboxes()
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
  public function get_curlev_names_mboxes($dir='')
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
  public function reopen_mbox(string $mbox): bool
  {
    return $this->select_folder($mbox);
  }


  /**
   * Returns an object containing the current mailbox info. (Test: ok)
   *
   * @return bool|object
   */
  public function get_info_mbox()
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
  public function sort_mbox($criteria, $reverse = 0)
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
  public function get_msg_headerinfo($msgnum)
  {
    if ($this->_is_connected()) {
      return imap_header($this->stream, $msgnum);
    }

    return false;
  }


  /**
   * Gets the UID of the message. (Test: ok)
   *
   * @param int $msgnum
   * @return bool|int
   */
  public function get_msg_uid($msgnum)
  {
    if ($this->_is_connected()) {
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
  public function get_msg_no($msguid)
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
  public function get_msg_structure($msgnum)
  {
    if ($this->_is_connected()) {
      return imap_fetchstructure($this->stream, $msgnum);
    }

    return false;
  }


  /**
   * Fetches the header of the message. (Test: ok)
   *
   * @param int      $msgnum No of the message
   * @param int|bool $uid    Set true f the msgnum is a UID
   * @return bool|string
   */
  public function get_msg_header($msgnum, $uid = false)
  {
    if ($this->_is_connected()) {
      if ($uid) {
        return imap_fetchheader($this->stream, $msgnum, FT_UID);
      }

      return imap_fetchheader($this->stream, $msgnum);
    }

    return false;
  }


  /**
   * Mark the specified message for deletion from current mailbox. (Text: ok)
   *
   * @param int $uid UID of the message
   * @return bool
   */
  public function delete_msg($uid)
  {
    if ($this->_is_connected()) {
      return imap_delete($this->stream, $this->get_msg_no($uid));
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
  public function move_msg($uid, $tombox)
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
  public function get_msg_body($msgno, $part)
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
  public function set_msg_flag($seq, $flg, $remove=false)
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
  public function mbox_exists($name)
  {
    if ($this->_is_connected() && !empty($name)) {
      $names = $this->get_all_names_folders();
      if (!empty($names) && \in_array($name, $names)) {
        return true;
      }
    }

    return false;
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
      foreach($folders as $key => $val) {
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
  private function _get_decode_value($message, $coding)
  {
    if ($coding === 0) {
      $message = imap_8bit($message);
    }
    elseif ($coding === 1) {
      $message = imap_8bit($message);
    }
    elseif ($coding === 2) {
      $message = imap_binary($message);
    }
    elseif ($coding === 3) {
      $message = imap_base64($message);
    }
    elseif ($coding === 4) {
      $message = imap_qprint($message);
    }
    elseif ($coding === 5) {
      $message = imap_base64($message);
    }

    return $message;
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
    // DECODE DATA
    $data = $this->get_msg_body($msgno, $partno);
    // Any part may be encoded, even plain text messages, so check everything.
    $data = $this->_get_decode_value($data, $structure->encoding);
    // PARAMETERS
    // get all parameters, like charset, filenames of attachments, etc.
    $params = [];
    if ($structure->parameters) {
      foreach ($structure->parameters as $x){
        $params[strtolower($x->attribute)] = $x->value;
      }
    }

    if ($structure->dparameters) {
      foreach ($structure->dparameters as $x){
        $params[strtolower($x->attribute)] = $x->value;
      }
    }

    // ATTACHMENT
    // Any part with a filename is an attachment,
    // so an attached text file (type 0) is not mistaken as the message.
    if ($params['filename'] || $params['name']) {
      // filename may be given as 'Filename' or 'Name' or both
      $filename = $params['filename'] ? $params['filename'] : $params['name'];
      // filename may be encoded, so see imap_mime_header_decode()
      array_push($this->_attachments, $filename);
      file_put_contents(BBN_USER_PATH . 'tmp_mail/' . $filename, $data);
      // this is a problem if two files have same name
    }

    // TEXT
    if (($structure->type === 0) && !empty($data)) {
      // Messages may be split in different parts because of inline attachments,
      // so append parts together with blank row.
      $this->_charset = $params['charset'];  // assume all parts are same charset
      if (strtolower($structure->subtype) === 'plain') {
        if (stripos($this->_charset, 'ISO') !== false) {
          //$utfConverter     = new \utf8($this->_charset);
          //$this->_plainmsg .= $utfConverter->loadCharset($this->_charset) ? $utfConverter->strToUtf8(trim($data)) . PHP_EOL : trim($data) . PHP_EOL;
          $this->_plainmsg .= trim(utf8_encode($data)).PHP_EOL;
        }
        else {
          $this->_plainmsg .= trim($data).PHP_EOL;
        }
      }
      else {
        if (stripos($this->_charset,'ISO') !== false) {
          $this->_htmlmsg .= trim(utf8_encode($data)).PHP_EOL;
          /*
          if ($utfConverter = new utf8($this->_charset)) {
            $this->_htmlmsg .= $utfConverter->strToUtf8(trim($data)).'<br><br>';
          }
          else {
            $this->_htmlmsg .= trim($data).'<br><br>';
          }
          */
        }
        else {
          $this->_htmlmsg .= trim($data).'<br><br>';
        }

        if (!empty($this->_htmlmsg)) {
          $body_pattern = "/<body([^>]*)>(.*)<\/body>/smi";
          preg_match($body_pattern, $this->_htmlmsg, $body);
          if (!empty($body[2])) {
            $this->_htmlmsg = $body[2];
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
        $this->_plainmsg .= $utfConverter->loadCharset($this->_charset) ? $utfConverter->strToUtf8(trim($data)) . PHP_EOL : $this->_plainmsg .= trim($data) . PHP_EOL;
      }
      else {
        $this->_plainmsg .= trim($data) . PHP_EOL;
      }
    }

    // SUBPART RECURSION
    if ($structure->parts) {
      foreach ($structure->parts as $partno0 => $p2){
        $this->_get_msg_part($msgno, $p2, $partno . '.' . ($partno0 + 1));  // 1.2, 1.2.1, etc.
      }
    }
  }


}
