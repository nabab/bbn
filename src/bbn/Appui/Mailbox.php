<?php

namespace bbn\Appui;

use Exception;
use stdClass;
use bbn\Models\Cls\Basic;
use bbn\Mail;
use bbn\X;
use bbn\Str;
use bbn\Appui\Mailbox\Idle;
use bbn\Appui\Mailbox\Client;
use DOMDocument;
use DOMXPath;
use DOMNode;
use Generator;

class Mailbox extends Basic
{

  /**
   * @var int The default delay between each ping
   */
  private static $defaultPingInterval = 2;

  /**
   * @var array The possible address fields
   */
  private static $destFields = ['to', 'from', 'cc', 'bcc', 'reply_to'];

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
  private $pingInterval;

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
   * @var Connection The stream object
   */
  protected $stream = null;

  /**
   * @var array The mail folders
   */
  protected $folders = [];

  /**
   * @var array The folders that are currently in IDLE
   */
  protected $foldersIdle = [];

  /**
   * @var Mail The mailer object
   */
  protected $mailer;

  /**
   * @var bool Whether the connection is encrypted
   */
  protected $encryption = false;

  /**
   * @var bool Whether to validate the certificate
   */
  protected $validateCertificate = false;

  /**
   * @var ?Client The raw IMAP client
   */
  protected ?Client $client = null;


  public static function setDefaultPingInterval(int $val): void
  {
    self::$defaultPingInterval = $val;
  }


  public static function getDefaultPingInterval(int $val): int
  {
    return self::$defaultPingInterval;
  }


  public static function getDestFields(): array
  {
    return self::$destFields;
  }


  public function __construct($cfg)
  {
    if (\is_array($cfg)) {
      if (!empty($cfg['type'])) {
        $this->type = $cfg['type'];
      }

      $this->host = !empty($cfg['host']) ? $cfg['host'] : 'localhost';
      $this->port = !empty($cfg['port']) ? (int)$cfg['port'] : 143;
      $this->login = $cfg['login'];
      $this->pass = $cfg['pass'];
      $this->pingInterval = self::$defaultPingInterval;
      $this->encryption = !empty($cfg['encryption']);
      $this->validateCertificate = !empty($cfg['validatecert']);

      switch ($this->type) {
        case 'hotmail':
          $this->host = 'imap-mail.outlook.com';
          $this->port = !empty($cfg['port']) ? (int)$cfg['port'] : 993;
          break;
        case 'gmail':
          $this->host = 'imap.googlemail.com';
          $this->port = !empty($cfg['port']) ? (int)$cfg['port'] : 993;
          break;
      }

      $this->mbParam = '{' . $this->host . ':' . $this->port . '/imap' . ($this->encryption ? '/ssl' : '/notls') . '}';
      $this->hash = md5($this->mbParam . '-' . $this->login);
      if ($this->connect()) {
        $this->selectFolder($cfg['dir'] ?? 'INBOX');
      }
    }
  }



  /**
   *  Closes the imap stream.
   *
   */
  public function __destruct()
  {
    if ($this->client && !$this->client->isDisconnecting()) {
      $this->client->disconnect();
      $this->client = null;
    }
  }


  public function getMailer(array $cfg = [], bool $force = false): Mail
  {
    if (!$this->mailer || $force) {
      $c = [
        'host'  => $cfg['host'] ?? $this->host,
        'user' => $cfg['login'] ?? $this->login,
        'pass'  => $cfg['pass'] ?? $this->pass,
        'from'  => $cfg['from'] ?? null,
        'name'  => $cfg['name'] ?? null,
        'template' => $cfg['template'] ?? '',
      ];
      if (array_key_exists('encryption', $cfg) && is_array($cfg['encryption'])) {
        $c = X::mergeArrays($c, $cfg['encryption']);
      }
      elseif ($this->encryption) {
        $c['ssl'] = [
          'verify_peer' => $this->validateCertificate,
          'verify_peer_name' => false,
          'verify_host' => false,
          'allow_self_signed' => true
        ];
      }

      if (in_array($this->type, ['imap', 'local'], true)) {
        $c['imap'] = true;
        $c['imap_host'] = $this->host;
        $c['imap_port'] = $this->port;
        $c['imap_user'] = $this->login;
        $c['imap_pass'] = $this->pass;
        $c['imap_sent'] = !empty($cfg['imap_sent']) ? $cfg['imap_sent'] : 'Sent';
        if ($this->encryption) {
          $c['imap_ssl'] = 'ssl';
        }
      }

      $this->mailer = new Mail($c);
    }

    return $this->mailer;
  }


  public function getEncription(): bool
  {
    return $this->encryption;
  }


  public function setPingInterval(int $val): static
  {
    $this->pingInterval = $val;
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
    return '';
    return $this->mbParam;
  }


  public function getHash(): string
  {
    return $this->hash;
  }


  public function getLastUid(): ?int
  {
    $uids = $this->search('ALL');
    return !empty($uids) ? max($uids) : null;
  }

  public function getFirstUid(): ?int
  {
    $uids = $this->search('ALL');
    return !empty($uids) ? min($uids) : null;
  }

  public function getNextUid(int|string $uid): ?int
  {
    if (!Str::isNumber($uid)) {
      return null;
    }

    $uids = $this->search('UID ' . ((int)$uid + 1) . ':*');
    X::hdump('getNextUid', $uids);
    if ($uids) {
      sort($uids);
      return $uids[0] ?? (int)$uid;
    }

    return null;
  }

  public function getNumMsg(?string $dir = null): ?int
  {
    if (!($dir = $this->selectFolder($dir))) {
      return null;
    }

    try {
      $lines = $this->rawCommand(
        'STATUS ' . $this->escapeString($dir) . ' (MESSAGES)',
        true
      );
      foreach ($lines as $line) {
        if (preg_match('/^\*\s+STATUS\s+.+\s+\((.+)\)$/i', $line, $m)) {
          $pairs = preg_split('/\s+/', trim($m[1])) ?: [];
          for ($i = 0; $i < count($pairs); $i += 2) {
            $k = strtoupper($pairs[$i] ?? '');
            $v = (int)($pairs[$i + 1] ?? 0);
            if ($k === 'MESSAGES') {
              return (int)$v;
            }
          }
        }
      }

      return null;
    }
    catch (Exception $e) {
      $this->setError($e->getMessage(), $e->getCode());
      return null;
    }
  }


  public function getStream()
  {
    return $this->client?->getStreamResource() ?: null;
  }


  public function check(): bool
  {
    return $this->isConnected();
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
      && ($info = $this->getInfoFolder($dir))
    ) {
      $this->folders[$dir]['last_uid'] = $this->getLastUid() ?: 0;
      $this->folders[$dir]['num_msg'] = $info->Nmsgs ?? 0;
      $this->folders[$dir]['last_check'] = microtime(true);
      return $info;
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
    try {
      $this->rawCommand('CREATE ' . $this->escapeString($mbox));
      return true;
    }
    catch (Exception $e) {
      $this->status = $e->getMessage();
      $this->setError($e->getMessage(), $e->getCode());
      return false;
    }
  }


  /**
   * Deletes a mailbox (Test: ok)
   *
   * @param string $mbox Mailbox
   * @return bool
   */
  public function deleteMbox($mbox)
  {
    try {
      $this->rawCommand('DELETE ' . $this->escapeString($mbox));
      return true;
    }
    catch (Exception $e) {
      $this->status = $e->getMessage();
      $this->setError($e->getMessage(), $e->getCode());
      return false;
    }
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
    try {
      $this->rawCommand(
        'RENAME ' . $this->escapeString($old) . ' ' . $this->escapeString($new)
      );
      return true;
    }
    catch (Exception $e) {
      $this->status = $e->getMessage();
      $this->setError($e->getMessage(), $e->getCode());
      return false;
    }
  }


  /**
   * Subscribes to a folder
   *
   * @param string $folder Folder name
   * @return bool
   */
  public function subscribeFolder($folder)
  {
    try {
      $this->rawCommand('SUBSCRIBE ' . $this->escapeString($folder));
      return true;
    }
    catch (Exception $e) {
      $this->status = $e->getMessage();
      $this->setError($e->getMessage(), $e->getCode());
      return false;
    }
  }


  /**
   * Unsubscribes from a folder
   *
   * @param string $folder Folder name
   * @return bool
   */
  public function unsubscribeFolder($folder)
  {
    try {
      $this->rawCommand('UNSUBSCRIBE ' . $this->escapeString($folder));
      return true;
    }
    catch (Exception $e) {
      $this->status = $e->getMessage();
      $this->setError($e->getMessage(), $e->getCode());
      return false;
    }
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
  public function listCurlevSubscribed(string $dir = '')
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
  public function listCurlevFolders(string $dir = '')
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
  public function getCurlevFolders(string $dir = '')
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
  public function getCurlevNamesFolders(string $dir = '')
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
  public function selectFolder(?string $folder = null): ?string
  {
    if (empty($folder) || ($this->folder === $folder)) {
      return $folder ?: $this->folder;
    }

    try {
      $this->rawCommand('SELECT ' . $this->escapeString($folder));
      if (!isset($this->folders[$folder])) {
        $this->folders[$folder] = [
          'last_uid' => null,
          'num_msg' => null,
          'last_check' => null
        ];
      }

      $this->folder = $folder;
      return $folder;
    }
    catch (Exception $e) {
      $this->status = $e->getMessage();
      $this->setError($e->getMessage(), $e->getCode());
      return null;
    }
  }


  /**
   * Returns an object containing the current mailbox info.
   *
   * @return stdClass|null
   */
  public function getInfoFolder(?string $dir = null): ?stdClass
  {
    if (!($dir = $this->selectFolder($dir))) {
      return null;
    }

    try {
      $info = new stdClass();
      $info->Date = date('d-M-Y H:i:s O');
      $info->Driver = 'bbn';
      $info->Mailbox = $dir;
      $info->Nmsgs = 0;
      $info->Recent = 0;
      $info->Unread = 0;
      $info->Deleted = 0;
      $info->Size = 0;
      $hasSize = $this->getClient()->hasCapability('STATUS=SIZE');
      $lines = $this->rawCommand(
        'STATUS ' . $this->escapeString($dir) . ' (MESSAGES RECENT UIDNEXT UIDVALIDITY UNSEEN' . ($hasSize ? ' SIZE' : '') . ')',
        true
      );
      if (!$hasSize) {
        if ($this->getClient()->hasCapability('QUOTA')) {
          $quota = $this->rawCommand('GETQUOTA ' . $this->escapeString($dir));
          if (preg_match('/STORAGE\s+(\d+)/i', $quota, $m)) {
            $info->Size = (int)$m[1];
          }
        }
        else {
          $sizes = $this->rawCommand('FETCH 1:* (RFC822.SIZE)', true);
          foreach ($sizes as $s) {
            if (preg_match('/^\*\s+\d+\s+FETCH\s+\((?:.*\s)?RFC822\.SIZE\s+(\d+)/i', $s, $m)) {
              $info->Size += (int)$m[1];
            }
          }
        }
      }

      foreach ($lines as $line) {
        if (preg_match('/^\*\s+STATUS\s+.+\s+\((.+)\)$/i', $line, $m)) {
          $pairs = preg_split('/\s+/', trim($m[1])) ?: [];
          for ($i = 0; $i < count($pairs); $i += 2) {
            $k = strtoupper($pairs[$i] ?? '');
            $v = (int)($pairs[$i + 1] ?? 0);
            if ($k === 'MESSAGES') {
              $info->Nmsgs = $v;
            }
            elseif ($k === 'RECENT') {
              $info->Recent = $v;
            }
            elseif ($k === 'UNSEEN') {
              $info->Unread = $v;
            }
            elseif ($k === 'SIZE') {
              $info->Size = $v;
            }
          }
        }
      }

      return $info;
    }
    catch (Exception $e) {
      $this->status = $e->getMessage();
      $this->setError($e->getMessage(), $e->getCode());
      return null;
    }
  }

  public function getEmailsList(string $folder, int $start, int $end): ?Generator
  {
    if (isset($this->folders[$folder])
      && $this->selectFolder($folder)
    ) {
      $res = [];
      while ($start >= $end) {
        try {
          $tmp = $this->getMsgBySeq($start);
          if (!$tmp) {
            $start--;
            continue;
          }

          $res[] = $tmp;
          $start--;
          yield $tmp;
        }
        catch (Exception $e) {
          X::log([
            'error' => "An error occured when trying to get the message $start " . $e->getMessage(),
            'start' => $start,
          ], 'poller_email_error');
          $start--;
        }
      }

      return $res;
    }

    return null;
  }


  /**
   *
   *
   * @param int $msgno
   */
  public function getMsg($msgno)
  {
    $this->_htmlmsg = '';
    $this->_plainmsg = '';
    $this->_charset = '';
    $this->_attachments = [];
    $this->_inline_files = [];

    $uid = $this->getMsgUid($msgno);
    if (!$uid) {
      return null;
    }

    return $this->getMsgByUid($uid);
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
    try {
      $lines = $this->rawCommand(
        'SORT (' . strtoupper($criteria) . ') UTF-8 ALL',
        true
      );

      foreach ($lines as $line) {
        if (preg_match('/^\*\s+SORT\s*(.*)$/i', $line, $m)) {
          $list = trim($m[1]);
          if ($list === '') {
            return [];
          }

          $res = array_map('intval', preg_split('/\s+/', $list) ?: []);
          return $reverse ? array_reverse($res) : $res;
        }
      }

      return [];
    }
    catch (Exception $e) {
      return false;
    }
  }

  public function getThreads()
  {
    try {
      $lines = $this->rawCommand(
        'THREAD REFERENCES UTF-8 ALL',
        true
      );
      return $lines;
    }
    catch (Exception $e) {
      return false;
    }
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
    return (bool)$this->selectFolder($mbox);
  }


  /**
   * Returns an object containing the current mailbox info. (Test: ok)
   *
   * @return bool|object
   */
  public function getInfoMbox()
  {
    return $this->getInfoFolder();
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
    return $this->sortFolder($criteria, $reverse);
  }


  /**
   * Retrieves the header's message info.
   *
   * @param int|string $msgUid
   * @return object|null
   */
  public function getMsgHeaderinfo(int|string $msgUid): ?object
  {
    if (!Str::isNumber($msgUid)) {
      return null;
    }

    $raw = $this->getMsgHeader($msgUid, true);
    if (!$raw) {
      return null;
    }

    if ($parsed = $this->parseHeaderInfo($raw)) {
      $parsed->size = $this->getMsgSize($msgUid, true);
    }

    return $parsed;
  }


  /**
   * Gets the UID of the message.
   *
   * @param int|string $msgNo No of the message
   * @return int|null
   */
  public function getMsgUid(int|string $msgNo): ?int
  {
    if (Str::isNumber($msgNo)) {
      try {
        $lines = $this->rawCommand(
          'FETCH ' . (int)$msgNo . ' (UID)',
          true
        );

        foreach ($lines as $line) {
          if (preg_match('/UID\s+(\d+)/i', $line, $m)) {
            return (int)$m[1];
          }
        }
      }
      catch (Exception $e) {}
    }

    return null;
  }


  /**
   * Gets the NO of the message.
   *
   * @param int|string $msgUid UID of the message
   * @return int|null
   */
  public function getMsgNo(int|string $msgUid): ?int
  {
    if (!Str::isNumber($msgUid)) {
      return null;
    }

    $uids = $this->search('ALL');
    if (!$uids) {
      return null;
    }

    $uids = array_values($uids);
    $idx = array_search((int)$msgUid, $uids, true);
    return ($idx === false) ? null : ($idx + 1);
  }


  /**
   * Fetches the message structure. (Test: ok)
   *
   * @param int $msgnum No of the message
   * @return bool|object
   */
  public function getMsgStructure(int $msgnum)
  {
    $uid = $this->getMsgUid($msgnum);
    if (!$uid) {
      return null;
    }

    try {
      $lines = $this->rawCommand(
        'UID FETCH ' . (int)$uid . ' (BODYSTRUCTURE)',
        true
      );

      $raw = implode("\n", $lines);
      return $this->parseBodyStructureFromFetch($raw);
    }
    catch (Exception $e) {
      return null;
    }
  }


  /**
   * Fetches the header of the message. (Test: ok)
   *
   * @param int      $msgnum No of the message
   * @param int|bool $uid    Set true f the msgnum is a UID
   * @return bool|string
   */
  public function getMsgHeader(int $msgnum, bool $uid = false): ?string
  {
    try {
      $lines = $this->rawCommand(
        ($uid ? 'UID FETCH ' : 'FETCH ') . (int)$msgnum . ' (BODY.PEEK[HEADER])',
        true
      );

      return $this->extractLiteralBlock($lines);
    }
    catch (Exception $e) {
      return null;
    }
  }


  public function getMsgOverview(int $msgnum, bool $uid = false): ?stdClass
  {
    try {
      $lines = $this->rawCommand(
        ($uid ? 'UID FETCH ' : 'FETCH ') . (int)$msgnum . ' (FLAGS INTERNALDATE RFC822.SIZE ENVELOPE UID)',
        true
      );

      $obj = new stdClass();
      $raw = implode("\n", $lines);

      if (preg_match('/FLAGS\s+\(([^)]*)\)/i', $raw, $m)) {
        $flags = preg_split('/\s+/', trim($m[1])) ?: [];
        $obj->flags = implode(' ', $flags);
        foreach (['seen', 'answered', 'flagged', 'deleted', 'draft', 'recent'] as $f) {
          $imapFlag = '\\' . ucfirst($f);
          $obj->$f = in_array($imapFlag, $flags, true);
        }
      }

      if (preg_match('/RFC822\.SIZE\s+(\d+)/i', $raw, $m)) {
        $obj->size = (int)$m[1];
      }

      if (preg_match('/UID\s+(\d+)/i', $raw, $m)) {
        $obj->uid = (int)$m[1];
      }

      return $obj;
    }
    catch (Exception $e) {
      $this->setError($e->getMessage(), $e->getCode());
      return null;
    }
  }


  /**
   * Mark the specified message for deletion from current mailbox. (Text: ok)
   *
   * @param int $uid UID of the message
   * @return bool
   */
  public function deleteMsg($uid): bool
  {
    return (bool)$this->setMsgFlag((string)$uid, '\\Deleted', false, true);
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
    try {
      $this->rawCommand('UID MOVE ' . (int)$uid . ' ' . $this->escapeString($tombox));
      return true;
    }
    catch (Exception $e) {
      try {
        $this->rawCommand('UID COPY ' . (int)$uid . ' ' . $this->escapeString($tombox));
        $this->setMsgFlag((string)$uid, '\\Deleted', false, true);
        return true;
      }
      catch (Exception $e2) {
        $this->status = $e2->getMessage();
        $this->setError($e2->getMessage(), $e2->getCode());
        return false;
      }
    }
  }


  /**
   * Deletes all messages marked for deletion by delete_msg(), move_msg() or set_flag().  (Test: ok)
   *
   * @return bool
   */
  public function expunge(): bool
  {
    try {
      $this->rawCommand('EXPUNGE');
      return true;
    }
    catch (Exception $e) {
      $this->status = $e->getMessage();
      $this->setError($e->getMessage(), $e->getCode());
      return false;
    }
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
    $uid = $this->getMsgUid((int)$msgno);
    if (!$uid) {
      return false;
    }

    try {
      $section = empty($part) ? 'TEXT' : $part;
      $lines = $this->rawCommand(
        'UID FETCH ' . (int)$uid . ' (BODY.PEEK[' . $section . '])',
        true
      );

      return $this->extractLiteralBlock($lines);
    }
    catch (Exception $e) {
      $this->setError($e->getMessage(), $e->getCode());
      return false;
    }
  }


  /**
   * Gets the flags of the message.
   * @param int $msgno No of the message
   * @return array|null
   */
  public function getMsgFlags(int $msgno): ?array
  {
    $flags = null;
    if ($overview = $this->getMsgOverview($msgno)) {
      $flags = [];
      $toCheck = ['seen', 'answered', 'flagged', 'deleted', 'draft', 'recent'];
      foreach ($toCheck as $flag) {
        if (!empty($overview->$flag)) {
          $flags[] = '\\' . ucfirst($flag);
        }
      }

      if (!empty($overview->flags)) {
        $keywords = preg_split('/\s+/', trim($overview->flags)) ?: [];
        foreach ($keywords as $kw) {
          if (!in_array(strtolower(ltrim($kw, '\\')), $toCheck, true)) {
            $flags[] = $kw;
          }
        }
      }
    }

    return $flags ?: null;
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
  public function setMsgFlag($seq, $flg, $remove = false, bool $asUid = false)
  {
    try {
      $cmd = ($asUid ? 'UID ' : '') . 'STORE ' . $seq . ' ' . ($remove ? '-FLAGS.SILENT ' : '+FLAGS.SILENT ') . '(' . $flg . ')';
      $this->rawCommand($cmd);
      return true;
    }
    catch (Exception $e) {
      $this->status = $e->getMessage();
      return false;
    }
  }


  public function getMsgPriority(int $msgno): ?int
  {
    $priority = null;
    if ($msgHeader = $this->getMsgHeader($msgno)) {
      // X-Priority
      if (preg_match('/^X-Priority:\s*(\d)/mi', $msgHeader, $m)) {
        $priority = (int)$m[1];
      }

      // Importance
      if (is_null($priority) &&
        preg_match('/^Importance:\s*(high|normal|low)/mi', $msgHeader, $m)
      ) {
        $map = ['high' => 1, 'normal' => 3, 'low' => 5];
        $priority = $map[strtolower($m[1])] ?? null;
      }

      // Priority
      if (is_null($priority)
        && preg_match('/^Priority:\s*(urgent|normal|non-urgent)/mi', $msgHeader, $m)
      ) {
        $map = ['urgent' => 1, 'normal' => 3, 'non-urgent' => 5];
        $priority = $map[strtolower($m[1])] ?? null;
      }
    }

    return $priority;
  }


  public function getMsgSize(int $msgno, bool $uid = false): ?int
  {
    if ($overview = $this->getMsgOverview($msgno, $uid)) {
      return $overview->size ?? null;
    }

    return null;
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
    try {
      $lines = $this->rawCommand(
        'UID SEARCH ' . $criteria,
        true
      );

      foreach ($lines as $line) {
        if (preg_match('/^\*\s+SEARCH\s*(.*)$/i', $line, $m)) {
          $list = trim($m[1]);
          if ($list === '') {
            return [];
          }

          $uids = array_map('intval', preg_split('/\s+/', $list) ?: []);
          sort($uids);
          return $uids;
        }
      }
    }
    catch (Exception $e) {
      $this->status = $e->getMessage();
      $this->setError($e->getMessage(), $e->getCode());
    }

    return false;
  }


  /**
   *  Appends a string message to a specified mailbox. (Test: ok)
   *
   * @param string $mbox Destination mailbox name
   * @param string $msg  Message
   * @param string|null $opt  Options string
   * @return bool
   */
  public function append(string $mbox, string $msg, ?string $opt = null)
  {
    try {
      $cmd = 'APPEND ' . $this->escapeString($mbox);
      if (!empty($opt)) {
        $cmd .= ' (' . trim($opt) . ')';
      }

      $cmd .= ' {' . strlen($msg) . '}';
      $this->rawCommand($cmd, false, false, false);
      $client = $this->getClient();
      $resp = $client->readCommandResponseLine();
      if (preg_match('/^\+\s/', $resp ?? '')) {
        $client->write($msg, false);
        $client->write("\r\n", false, true);
        $client->readCommandResponse($client->getLastTag(), true);
        return true;
      }

      $this->getClient()->closeCommunication();
      return false;
    }
    catch (Exception $e) {
      $this->getClient()->closeCommunication();
      $this->status = $e->getMessage();
      $this->setError($e->getMessage(), $e->getCode());
      return false;
    }
  }


  /**
   * Check if a mailbox exists
   *
   * @param string $name The mailbox name
   * @return bool
   */
  public function mboxExists($name)
  {
    if (!empty($name)) {
      $names = $this->getAllNamesFolders();
      if (!empty($names) && \in_array($name, $names, true)) {
        return true;
      }
    }

    return false;
  }


  public function getAttachments(int $msgNum, ?string $filename = null): ?array
  {
    $msg = $this->getMsg($msgNum);
    if (!$msg || empty($msg['attachment'])) {
      return null;
    }

    $attachments = [];
    foreach ($msg['attachment'] as $a) {
      if ((($a['name'] ?? null) === $filename)
        || empty($filename)
      ) {
        $data = $this->getAttachmentDataByPart($msgNum, $a['part'] ?? null, $a['encoding'] ?? 0);
        if ($data !== null) {
          if (!empty($filename)) {
            if ($filename === ($a['name'] ?? null)) {
              return [
                'type' => $a['type'] ?? '',
                'name' => $a['name'] ?? '',
                'size' => $a['size'] ?? 0,
                'data' => $data
              ];
            }

            continue;
          }
          else {
            $attachments[] = [
              'type' => $a['type'] ?? '',
              'name' => $a['name'] ?? '',
              'size' => $a['size'] ?? 0,
              'data' => $data
            ];
          }
        }
      }
    }

    return $attachments;
  }


  /**
   * Starts an IDLE connection to the mailbox for a specific folder.
   * @param string $folderUid The UID of the folder to monitor
   * @param callable $callback The callback function to execute when new messages arrive
   * @param int|null $timeout The timeout in seconds for the IDLE connection
   * @return void
   */
  public function idle(string $folderUid, callable $callback, ?int $timeout = null)
  {
    $this->stopIdle($folderUid);
    $this->foldersIdle[$folderUid] = new Idle(
      $this->getHost(),
      $this->getPort(),
      $this->encryption,
      $this->login,
      $this->pass,
      $folderUid,
      $callback,
      $timeout ?: 0
    );
    $this->foldersIdle[$folderUid]->idle();
  }


  /**
   * Stops the IDLE connection for a specific folder.
   * @param string $folderUid The UID of the folder to stop monitoring
   * @return bool True if the IDLE connection was stopped, false if it was not running
   */
  public function stopIdle(string $folderUid): bool
  {
    if (!empty($this->foldersIdle[$folderUid])) {
      return $this->foldersIdle[$folderUid]->stopIdle();
    }

    return false;
  }


  /**
   * Checks if the IDLE connection is running for a specific folder.
   * @param string $folderUid The UID of the folder to check
   * @return bool True if the IDLE connection is running, false otherwise
   */
  public function isIdleRunning(string $folderUid): bool
  {
    if (!empty($this->foldersIdle[$folderUid])) {
      return $this->foldersIdle[$folderUid]->isRunning();
    }

    return false;
  }


  public function connect(): bool
  {
    try {
      $this->getClient()->connect();
      if (!empty($this->folder)) {
        $this->selectFolder($this->folder);
      }

      $this->status = 'ok';
      return true;
    }
    catch (Exception $e) {
      $this->status = $e->getMessage();
      $this->setError($e->getMessage(), $e->getCode());
      return false;
    }
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


  /* public function _get_decode_value($message, $coding)
  {
    switch ((int)$coding) {
      case 0:
        return $message;
      case 1:
        return quoted_printable_decode($message);
      case 2:
        return base64_decode($message);
      case 3:
        return base64_decode($message);
      case 4:
        return quoted_printable_decode($message);
      case 5:
        return base64_decode($message);
      default:
        return $message;
    }
  } */


  /**
   * Splits the quoted part from the reply in an email HTML content.
   */
  public function splitQuoteFromEmail(string $html): ?array
  {
    $html = trim($html);
    if (empty($html)) {
      return null;
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($dom);
    $outerHTML = fn(DOMNode $node) => $dom->saveHTML($node);

    /** -------------------------------
     *  1) Strong HTML markers
     *  ------------------------------- */
    $queries = [[
      '//div[contains(concat(" ", normalize-space(@class), " "), " gmail_quote ")]',
      'gmail_quote_div'
    ], [
      '//blockquote[contains(concat(" ", normalize-space(@class), " "), " gmail_quote ")]',
      'gmail_quote_blockquote'
    ], [
      '//blockquote[translate(@type,"CITE","cite")="cite"]',
      'blockquote_type_cite'
    ], [
      '//div[contains(concat(" ", normalize-space(@class), " "), " moz-cite-prefix ")]',
      'moz_cite_prefix'
    ]];

    foreach ($queries as [$q, $method]) {
      $nodes = $xpath->query($q);
      if ($nodes && $nodes->length > 0) {
        $quoteNode = $nodes->item(0);
        $quoteHtml = $outerHTML($quoteNode);
        $replyDom = $this->cloneDomDocument($dom);
        $this->removeFirstNodeByOuterHTML($replyDom, $quoteHtml);
        return [
          'text' => trim($replyDom->saveHTML()),
          'quote' => $quoteHtml,
          'method' => $method,
        ];
      }
    }

    /** -------------------------------
     *  2) Textual fallback (multi-lang)
     *  ------------------------------- */
    $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $patterns = [
      // ---------- English ----------
      '/\ROn\s.+?\swrote:\R/i',
      '/\R-{2,}\s*Original Message\s*-{2,}\R/i',
      '/\RFrom:\s.+\R/i',
      '/\RSent:\s.+\R/i',
      '/\RSubject:\s.+\R/i',

      // ---------- Italian ----------
      '/\RIl\s.+?\sha scritto:\R/i',
      '/\R-{2,}\s*Messaggio originale\s*-{2,}\R/i',
      '/\RDa:\s.+\R/i',
      '/\RInviato:\s.+\R/i',
      '/\ROggetto:\s.+\R/i',

      // ---------- French ----------
      '/\RLe\s.+?\sa écrit\s*:\R/i',
      '/\R-{2,}\s*Message d\'origine\s*-{2,}\R/i',
      '/\RDe\s*:\s.+\R/i',
      '/\REnvoyé\s*:\s.+\R/i',
      '/\RObjet\s*:\s.+\R/i',

      // ---------- Spanish ----------
      '/\REl\s.+?\sescribió\s*:\R/i',
      '/\R-{2,}\s*Mensaje original\s*-{2,}\R/i',
      '/\RDe\s*:\s.+\R/i',
      '/\REnviado\s*:\s.+\R/i',
      '/\RAsunto\s*:\s.+\R/i',

      // ---------- German ----------
      '/\RAm\s.+?\schrieb\s.+?\s*:\R/i',
      '/\R-{2,}\s*Ursprüngliche Nachricht\s*-{2,}\R/i',
      '/\RVon\s*:\s.+\R/i',
      '/\RGesendet\s*:\s.+\R/i',
      '/\RBetreff\s*:\s.+\R/i',

      // ---------- Russian ----------
      '/\R.+?\sнаписал\(а\)\s*:\R/iu',
      '/\R-{2,}\s*Исходное сообщение\s*-{2,}\R/iu',
      '/\RОт\s*:\s.+\R/iu',
      '/\RОтправлено\s*:\s.+\R/iu',
      '/\RТема\s*:\s.+\R/iu',
    ];

    foreach ($patterns as $p) {
      if (preg_match($p, $text, $m, PREG_OFFSET_CAPTURE)) {
        $needle = trim($m[0][0]);
        $htmlPos = mb_stripos($html, $needle);
        if ($htmlPos !== false) {
          return [
            'text' => trim(mb_substr($html, 0, $htmlPos)),
            'quote' => trim(mb_substr($html, $htmlPos)),
            'method' => 'text_separator',
            'separator_regex' => $p,
          ];
        }

        return [
          'text' => $html,
          'quote' => '',
          'method' => 'text_separator_found_but_not_split_in_html',
          'separator_regex' => $p,
        ];
      }
    }

    /** -------------------------------
     *  3) Last resort: first blockquote
     *  ------------------------------- */
    $bq = $xpath->query('//blockquote');
    if ($bq && $bq->length > 0) {
      $quoteHtml = $outerHTML($bq->item(0));
      $replyDom = $this->cloneDomDocument($dom);
      $this->removeFirstNodeByOuterHTML($replyDom, $quoteHtml);

      return [
        'text' => trim($replyDom->saveHTML()),
        'quote' => $quoteHtml,
        'method' => 'first_blockquote',
      ];
    }

    return [
      'text' => $html,
      'quote' => '',
      'method' => 'no_quote_detected',
    ];
  }


  /**
   * Returns a Client instance for executing raw IMAP commands.
   * @return Client
   */
  public function getClient(): Client
  {
    if (!$this->client) {
      $this->client = new Client(
        $this->host,
        $this->port,
        $this->encryption,
        $this->login,
        $this->pass
      );
    }

    if (!$this->client->isCommunicating() && !$this->client->isConnected()) {
      $this->client->connect();
    }

    return $this->client;
  }


  /**
   * Executes a raw IMAP command and returns the response as a string or an array.
   * @param string $command The raw IMAP command to execute
   * @param bool $response Set to true to return the server response, false to return an empty string
   * @param bool $allResponse Set to true to return the full response as an array, false to return only the last line as a string
   * @return string|array The response from the IMAP server
   */
  public function rawCommand(
    string $command,
    bool $allResponse = false,
    bool $response = true,
    bool $closeCommunication = false
  ): string|array
  {
    return $this->getClient()->sendCommand(
      $command,
      $allResponse,
      $response,
      $closeCommunication
    );
  }


  /**
   * Returns the HIGHESTMODSEQ of the folder, or null if not supported by the server.
   * @param string $folderUid The UID of the folder
   * @return int|null The HIGHESTMODSEQ value or null if not supported
   */
  public function getFolderHighestmodseq(string $folderUid): ?int
  {
    if (($client = $this->getClient())
      && $client->hasCapability('CONDSTORE')
    ) {
      $res = $this->rawCommand('STATUS ' . $this->escapeString($folderUid) . ' (HIGHESTMODSEQ)');
      if (preg_match('/HIGHESTMODSEQ\s+(\d+)/i', $res, $m)) {
        return (int)$m[1];
      }
    }

    return null;
  }

  /**
   * Returns the messsages UIDs changed since lastModseq.
   * @param string $folderUid The UID of the folder
   * @param int $lastModseq The last known MODSEQ value
   * @return array An array of UIDs that have changed since lastModseq
   * @note This method requires the server to support CONDSTORE and may not work on
   */
  public function getMsgUidsChangedSinceModseq(string $folderUid, int $lastModseq): array
  {
    if (($client = $this->getClient())
      && $client->hasCapability('CONDSTORE')
    ) {
      $this->rawCommand('SELECT ' . $this->escapeString($folderUid) . ' (CONDSTORE)');
      $res = $this->rawCommand('UID SEARCH MODSEQ ' . max(1, $lastModseq + 1));
      if (preg_match('/^\*\s+SEARCH\s*(.*)$/mi', $res, $m)) {
        $list = trim($m[1]);
        if ($list === '') {
          return [];
        }

        $uids = preg_split('/\s+/', $list) ?: [];
        $uids = array_values(array_unique(array_map('intval', $uids)));
        sort($uids);
        return $uids;
      }
    }

    return [];
  }


  private function escapeString(string $str): string
  {
    return Client::escapeString($str);
  }

  private function getMsgBySeq(int $seq): ?array
  {
    $uid = $this->getMsgUid($seq);
    return $uid ? $this->getMsgByUid($uid) : null;
  }

  private function getMsgByUid(int $uid): ?array
  {
    $headers = $this->getMsgHeaderinfo($uid);
    if (!$headers) {
      return null;
    }
    $msg = (array)$this->decode_encoded_words_deep($headers);
    /* foreach ($msg as $key => $value) {
      if (is_string($value)) {
        $msg[$key] = quoted_printable_decode($value);
      }
    } */

    $msg['priority'] = $this->getMsgPriority($this->getMsgNo($uid)) ?: 3;
    $msg['flags'] = $this->getMsgFlags($this->getMsgNo($uid));
    $msg['uid'] = $uid;
    $msg['date_sent'] = !empty($msg['date']) ? date('Y-m-d H:i:s', strtotime($msg['date'])) : null;
    $msg['date_server'] = $msg['date_sent'];

    foreach (self::getDestFields() as $df) {
      if (!empty($msg[$df]) && is_array($msg[$df])) {
        $ads = [];
        foreach ($msg[$df] as $a) {
          if (!empty($a['email'])) {
            $ads[] = [
              'name' => $a['name'] ?? null,
              'email' => strtolower($a['email']),
              'host' => Str::parsePath($a['email'])['extension'] ?? null
            ];
          }
        }
        $msg[$df] = $ads;
      }
    }

    $msg['references'] = empty($msg['references'])
      ? []
      : (preg_split('/\s+/', trim(str_replace(['<', '>'], '', $msg['references']))) ?: []);

    if (!isset($msg['subject'])) {
      $msg['subject'] = '';
    }

    $msg['message_id'] = !empty($msg['message_id'])
      ? trim($msg['message_id'], '<>')
      : $this->transformString(($msg['uid'] ?? '') . ($msg['date_sent'] ?? '') . ($msg['subject'] ?? '')) . '@bbn.solutions';

    $msg['in_reply_to'] = empty($msg['in_reply_to']) ? false : trim($msg['in_reply_to'], '<>');

    $fullRaw = $this->getFullMessageByUid($uid);
    $parsedMime = $this->parseMimeMessage($fullRaw);

    $msg['html'] = $parsedMime['html'] ?? '';
    $msg['plain'] = $parsedMime['plain'] ?? '';
    $msg['charset'] = $parsedMime['charset'] ?? '';
    //$msg['attachment'] = $parsedMime['attachments'] ?? [];
    $msg['attachment'] = [];
    //$msg['inline'] = $parsedMime['inline'] ?? [];
    $msg['inline'] = [];
    $msg['is_html'] = !empty($msg['html']);

    return $msg;
  }

  private function getFullMessageByUid(int $uid): ?string
  {
    try {
      $lines = $this->rawCommand(
        'UID FETCH ' . $uid . ' (BODY.PEEK[])',
        true
      );

      return $this->extractLiteralBlock($lines);
    }
    catch (Exception $e) {
      return null;
    }
  }

  private function getAttachmentDataByPart(int $msgNum, ?string $part, int $encoding): ?string
  {
    if (!$part) {
      return null;
    }

    $body = $this->getMsgBody($msgNum, $part);
    if ($body === false || $body === null) {
      return null;
    }

    return $this->_get_decode_value($body, $encoding);
  }

  private function extractLiteralBlock(array $lines): ?string
  {
    $capture = false;
    $buffer = [];

    foreach ($lines as $line) {
      if ($capture) {
        if (preg_match('/^BBN_\d+\s+(OK|NO|BAD)/i', $line)) {
          break;
        }

        if ($line === ')') {
          continue;
        }

        $buffer[] = $line;
      }
      elseif (preg_match('/\{(\d+)\}$/', $line)) {
        $capture = true;
      }
    }

    if (!$buffer) {
      return null;
    }

    return implode("\n", $buffer);
  }

  private function parseHeaderInfo(string $raw): object
  {
    $headers = $this->parseHeaders($raw);
    $res = new stdClass();

    foreach ($headers as $k => $v) {
      $lk = strtolower($k);
      switch ($lk) {
        case 'subject':
          $res->subject = $this->decodeMimeHeaderValue($v);
          break;
        case 'date':
          $res->date = $v;
          $res->Date = $v;
          break;
        case 'message-id':
          $res->message_id = $v;
          break;
        case 'references':
          $res->references = $v;
          break;
        case 'in-reply-to':
          $res->in_reply_to = $v;
          break;
        case 'from':
          $res->from = $this->parseAddressList($v);
          $res->fromaddress = $v;
          break;
        case 'to':
          $res->to = $this->parseAddressList($v);
          $res->toaddress = $v;
          break;
        case 'cc':
          $res->cc = $this->parseAddressList($v);
          break;
        case 'bcc':
          $res->bcc = $this->parseAddressList($v);
          break;
        case 'reply-to':
          $res->reply_to = $this->parseAddressList($v);
          $res->reply_toaddress = $v;
          break;
        default:
          $res->{$lk} = $v;
      }
    }

    return $res;
  }

  private function parseHeaders(string $raw): array
  {
    $raw = str_replace("\r\n", "\n", $raw);
    $lines = explode("\n", $raw);
    $headers = [];
    $current = null;

    foreach ($lines as $line) {
      if (preg_match('/^\s+/', $line) && $current !== null) {
        $headers[$current] .= ' ' . trim($line);
        continue;
      }

      if (preg_match('/^([^:]+):(.*)$/', $line, $m)) {
        $current = trim($m[1]);
        $headers[$current] = trim($m[2]);
      }
    }

    return $headers;
  }

  private function parseAddressList(string $value): array
  {
    $res = [];
    $parts = preg_split('/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', $value) ?: [];

    foreach ($parts as $p) {
      $p = trim($p);
      if (preg_match('/^(.*?)<([^>]+)>$/', $p, $m)) {
        $name = trim($m[1], " \t\n\r\0\x0B\"");
        $email = trim($m[2]);
        $res[] = [
          'name' => $name ? $this->decodeMimeHeaderValue($name) : null,
          'email' => $email
        ];
      }
      elseif (filter_var($p, FILTER_VALIDATE_EMAIL)) {
        $res[] = [
          'name' => null,
          'email' => $p
        ];
      }
    }

    return $res;
  }

  private function decodeMimeHeaderValue(string $value): string
  {
    if (function_exists('iconv_mime_decode')) {
      $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
      if ($decoded !== false) {
        return $decoded;
      }
    }

    return $this->decode_encoded_words($value);
  }

  private function parseBodyStructureFromFetch(string $raw): ?object
  {
    if (preg_match('/BODYSTRUCTURE\s+(.+)\)$/is', $raw, $m)) {
      $o = new stdClass();
      $o->raw = trim($m[1]);
      return $o;
    }

    return null;
  }

  private function parseMimeMessage(?string $raw): array
  {
    if (!$raw) {
      return [
        'html' => '',
        'plain' => '',
        'charset' => '',
        'attachments' => [],
        'inline' => []
      ];
    }

    [$headerText, $body] = preg_split("/\R\R/", $raw, 2) + [null, ''];
    $headers = $this->parseHeaders($headerText ?? '');
    $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? 'text/plain';
    $encoding = $headers['Content-Transfer-Encoding'] ?? $headers['content-transfer-encoding'] ?? '';
    $disposition = $headers['Content-Disposition'] ?? $headers['content-disposition'] ?? '';
    $typeInfo = $this->parseContentType($contentType);
    $charset = $typeInfo['charset'] ?? '';
    $boundary = $typeInfo['boundary'] ?? null;
    $mime = strtolower($typeInfo['mime'] ?? 'text/plain');
    $result = [
      'html' => '',
      'plain' => '',
      'charset' => $charset,
      'attachments' => [],
      'inline' => []
    ];

    if ($boundary && str_starts_with($mime, 'multipart/')) {
      $parts = $this->splitMultipartBody($body, $boundary);
      foreach ($parts as $idx => $partRaw) {
        $part = $this->parseMimeMessage($partRaw);

        if (!empty($part['html'])) {
          $result['html'] .= $part['html'];
        }

        if (!empty($part['plain'])) {
          $result['plain'] .= $part['plain'];
        }

        if (!empty($part['charset']) && empty($result['charset'])) {
          $result['charset'] = $part['charset'];
        }

        if (!empty($part['attachments'])) {
          $result['attachments'] = array_merge($result['attachments'], $part['attachments']);
        }

        if (!empty($part['inline'])) {
          $result['inline'] = array_merge($result['inline'], $part['inline']);
        }
      }

      return $result;
    }

    $decodedBody = $this->decodeBodyByEncoding($body, $encoding);
    $filename = $this->extractFilenameFromHeaders($headers);
    $cid = $headers['Content-ID'] ?? $headers['content-id'] ?? null;
    if ($filename) {
      $att = [
        'id' => $cid ? trim($cid, '<>') : null,
        'type' => Str::fileExt($filename) ?: $this->mimeToSubtype($mime),
        'name' => $filename,
        'size' => strlen($decodedBody),
        'data' => $decodedBody,
        'encoding' => $this->mapEncodingNameToInt($encoding),
        'part' => null
      ];
      $result['attachments'][] = $att;
      if (stripos($disposition, 'inline') !== false) {
        $result['inline'][] = $att;
      }

      return $result;
    }

    if ($mime === 'text/html') {
      $result['html'] = Str::toUtf8($decodedBody);
    }
    elseif ($mime === 'text/plain') {
      $result['plain'] = Str::toUtf8($decodedBody);
    }

    return $result;
  }

  private function parseContentType(string $contentType): array
  {
    $parts = array_map('trim', explode(';', $contentType));
    $mime = strtolower(array_shift($parts) ?: 'text/plain');
    $res = ['mime' => $mime];
    foreach ($parts as $p) {
      if (preg_match('/^([^=]+)=(.*)$/', $p, $m)) {
        $k = strtolower(trim($m[1]));
        $v = trim($m[2], " \t\n\r\0\x0B\"");
        $res[$k] = $v;
      }
    }

    return $res;
  }

  private function splitMultipartBody(string $body, string $boundary): array
  {
    $boundaryLine = '--' . $boundary;
    $endBoundaryLine = '--' . $boundary . '--';
    $lines = preg_split("/\R/", $body) ?: [];
    $parts = [];
    $current = [];
    $inside = false;

    foreach ($lines as $line) {
      if ($line === $boundaryLine) {
        if ($inside && $current) {
          $parts[] = implode("\r\n", $current);
          $current = [];
        }
        $inside = true;
        continue;
      }

      if ($line === $endBoundaryLine) {
        if ($inside && $current) {
          $parts[] = implode("\r\n", $current);
        }
        break;
      }

      if ($inside) {
        $current[] = $line;
      }
    }

    return $parts;
  }

  private function decodeBodyByEncoding(string $body, string $encoding): string
  {
    $enc = strtolower(trim($encoding));
    return match ($enc) {
      'base64' => base64_decode($body) ?: '',
      'quoted-printable' => quoted_printable_decode($body),
      default => $body,
    };
  }

  private function extractFilenameFromHeaders(array $headers): ?string
  {
    foreach (['Content-Disposition', 'content-disposition', 'Content-Type', 'content-type'] as $key) {
      if (!empty($headers[$key])) {
        if (preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $headers[$key], $m)) {
          return rawurldecode($m[1]);
        }

        if (preg_match('/name="?([^";]+)"?/i', $headers[$key], $m)) {
          return $this->decodeMimeHeaderValue($m[1]);
        }
      }
    }

    return null;
  }

  private function mimeToSubtype(string $mime): string
  {
    $parts = explode('/', $mime);
    return strtolower($parts[1] ?? 'bin');
  }

  private function mapEncodingNameToInt(string $encoding): int
  {
    return match (strtolower(trim($encoding))) {
      '7bit' => 0,
      '8bit' => 1,
      'binary' => 2,
      'base64' => 3,
      'quoted-printable' => 4,
      default => 0,
    };
  }

  private function transformString($string)
  {
    $hash = md5($string);
    $result = '';
    for ($i = 0; $i < strlen($hash); $i++) {
      $char = $hash[$i];
      $result .= $char;
      if (($i + 1) % 4 == 0 && $i != 31) {
        $result .= '-';
      }
    }

    return $result;
  }


  private function decode_encoded_words($string)
  {

    preg_match_all("/=\?([^?]+)\?([QqBb])\?([^?]+)\?=/", $string, $matches);
    if (!empty($matches)) {
      for ($i = 0; $i < count($matches[0]); $i++) {
        $encoding = $matches[2][$i];
        $encoded_text = $matches[3][$i];
        if (strtolower($encoding) == "q") {
          $decoded_text = quoted_printable_decode(Str::replace("_", " ", $encoded_text));
        }
        else {
          $decoded_text = base64_decode($encoded_text);
        }
        $string = Str::replace($matches[0][$i], $decoded_text, $string);
      }
    }

    return $string;
  }

  private function decode_encoded_words_array(array $array)
  {
    for ($i = 0; $i < count($array); $i++) {
      $array[$i] = $this->decode_encoded_words($array[$i]);
    }
    return $array;
  }

  private function decode_encoded_words_deep($obj)
  {
    if (is_string($obj)) {
      $obj = $this->decode_encoded_words($obj);
    }
    elseif (is_object($obj)) {
      foreach ($obj as $idx => $val) {
        $obj->$idx = $this->decode_encoded_words_deep($val);
      }
    }
    elseif (is_array($obj)) {
      foreach ($obj as $idx => $val) {
        $obj[$idx] = $this->decode_encoded_words_deep($val);
      }
    }

    return $obj;
  }


  /**
   * Checks if we are connected  (Test: ok)
   *
   * @return bool
   */
  private function isConnected()
  {
    if ($this->client) {
      $now = microtime(true);
      if ($now - $this->_last_ping < $this->pingInterval) {
        return true;
      }

      if ($this->client->isConnected()) {
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
    try {
      $lines = $this->rawCommand(
        'LSUB "" ' . $this->escapeString($dir),
        true
      );

      $res = [];
      foreach ($lines as $line) {
        if (preg_match('/^\*\s+LSUB\s+\([^\)]*\)\s+"([^"]*)"\s+(.+)$/i', $line, $m)) {
          $res[] = trim($m[2], '"');
        }
      }

      return $res;
    }
    catch (Exception $e) {
      $this->setError($e->getMessage(), $e->getCode());
      return false;
    }
  }


  /**
   * Returns an array containing the full names of the mailboxes.  (Test: ok)
   *
   * @param string $dir Mailbox folder
   * @return bool|array
   */
  private function _list_folders($dir)
  {
    try {
      $lines = $this->rawCommand(
        'LIST "" ' . $this->escapeString($dir),
        true
      );

      $res = [];
      foreach ($lines as $line) {
        if (preg_match('/^\*\s+LIST\s+\([^\)]*\)\s+"([^"]*)"\s+(.+)$/i', $line, $m)) {
          $res[] = trim($m[2], '"');
        }
      }

      return $res;
    }
    catch (Exception $e) {
      $this->setError($e->getMessage(), $e->getCode());
      return false;
    }
  }


  /**
   * Returns an array of objects containing detailed mailboxes information. (Test: ok)
   *
   * @param string $dir Mailbox folder
   * @return array|bool
   */
  private function _get_folders($dir)
  {
    try {
      $lines = $this->rawCommand(
        'LIST "" ' . $this->escapeString($dir),
        true
      );

      $res = [];
      foreach ($lines as $line) {
        if (preg_match('/^\*\s+LIST\s+\(([^)]*)\)\s+"([^"]*)"\s+(.+)$/i', $line, $m)) {
          $o = new stdClass();
          $o->attributes = trim($m[1]);
          $o->delimiter = $m[2];
          $o->name = trim($m[3], '"');
          $res[] = $o;
        }
      }

      return $res;
    }
    catch (Exception $e) {
      $this->setError($e->getMessage(), $e->getCode());
      return false;
    }
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
      $ret = [];
      foreach ($folders as $val) {
        $mbox_name = $val->name;
        if ($mbox_name === "") {
          continue;
        }

        $ret[] = $mbox_name;
      }

      sort($ret);
      return $ret;
    }

    return false;
  }


  /**
   * Clones a DOMDocument
   */
  private function cloneDomDocument(DOMDocument $dom): DOMDocument
  {
    $clone = new DOMDocument();
    $clone->loadHTML($dom->saveHTML(), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    return $clone;
  }


  /**
   * Removes the first node matching the given outer HTML from the DOMDocument
   */
  private function removeFirstNodeByOuterHTML(DOMDocument $dom, string $targetOuterHtml): void
  {
    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query('//*');
    if (!$nodes) {
      return;
    }

    foreach ($nodes as $node) {
      if ($dom->saveHTML($node) === $targetOuterHtml) {
        $node->parentNode?->removeChild($node);
        return;
      }
    }
  }
}
