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
use bbn\Appui\Mailbox\Parser;
use bbn\Appui\Mailbox\Encoder;
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

  private static $flags = [
    'seen' => '\\Seen',
    'answered' => '\\Answered',
    'flagged' => '\\Flagged',
    'deleted' => '\\Deleted',
    'draft' => '\\Draft',
    'recent' => '\\Recent'
  ];

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
   * @var Parser The mailbox parser
   */
  private $parser;

  /**
   * @var Encoder The mailbox encoder
   */
  private $encoder;

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


  public function __construct(array $cfg)
  {
    if (is_array($cfg) && !empty($cfg)) {
      $this->parser = new Parser();
      $this->encoder = new Encoder();
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
    return $this->_listSubscribed('*');
  }


  /**
   * Returns an array containing the names of the current level mailboxes that you have subscribed. (Test: ok)
   *
   * @param string $dir Current mailbox folder
   * @return bool|array
   */
  public function listCurlevSubscribed(string $dir = '')
  {
    return $this->_listSubscribed($dir . '%');
  }


  /**
   * Returns an array containing the full names of the all mailboxes.  (Test: ok)
   *
   * @return bool|array
   */
  public function listAllFolders()
  {
    return $this->_listFolders('*');
  }


  /**
   * Returns an array containing the full names of the current level mailboxes.  (Test: ok)
   *
   * @param string $dir Current mailbox folder
   * @return bool|array
   */
  public function listCurlevFolders(string $dir = '')
  {
    return $this->_listFolders($dir . '%');
  }


  /**
   * Returns an array of objects for all mailboxes containing detailed mailbox information. (Test: ok)
   *
   * @return array|bool
   */
  public function getAllFolders()
  {
    return $this->_getFolders('*');
  }


  /**
   * Returns an array of objects for each current level mailboxes containing detailed mailbox information. (Test: ok)
   *
   * @param string $dir Mailbox folder
   * @return array|bool
   */
  public function getCurlevFolders(string $dir = '')
  {
    return $this->_getFolders($dir . '%');
  }


  /**
   * Returns a sorted array containing the simple names of the all mailboxes. (Test: ok)
   *
   * @return array
   */
  public function getAllNamesFolders()
  {
    return $this->_getNamesFolders('*');
  }


  /**
   * Returns a sorted array containing the simple names of the current level mailboxes. (Test: ok)
   *
   * @param string $dir Current mailbox folder
   * @return array
   */
  public function getCurlevNamesFolders(string $dir = '')
  {
    return $this->_getNamesFolders($dir . '%');
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
          $tmp = $this->getMsgBySeqOrUid($start, false);
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
  public function getMsg(string|int $msgno, bool $uid = false)
  {
    $this->_htmlmsg = '';
    $this->_plainmsg = '';
    $this->_charset = '';
    $this->_attachments = [];
    $this->_inline_files = [];
    return $this->getMsgBySeqOrUid((int)$msgno, $uid);
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

    if ($parsed = $this->parser->headerInfo($raw)) {
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
  public function getMsgStructure(int $msgnum, bool $uid = false): ?object
  {
    try {
      $lines = $this->rawCommand(
        ($uid ? "UID " : "") . "FETCH $msgnum (BODYSTRUCTURE)",
        true
      );

      $raw = implode("\n", $lines);
      return $this->parser->bodyStructureFromFetch($raw);
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

      return $this->parser->extractLiteralBlock($lines);
    }
    catch (Exception $e) {
      return null;
    }
  }


  public function getMsgOverview(int $msgnum, bool $uid = false, ?string $raw = null): ?stdClass
  {
    try {
      if (is_null($raw)) {
        $lines = $this->rawCommand(
          ($uid ? 'UID FETCH ' : 'FETCH ') . (int)$msgnum . ' (FLAGS INTERNALDATE RFC822.SIZE ENVELOPE UID)',
          true
        );
        $raw = implode("\n", $lines);
      }

      $obj = new stdClass();
      $flags = $this->parser->flags($raw);
      $obj->flags = implode(' ', $flags);
      foreach (self::$flags as $f => $imapFlag) {
        $obj->$f = in_array($imapFlag, $flags, true);
      }

      $obj->size = $this->parser->size($raw);
      $obj->uid = $this->parser->uid($raw);

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
    return (bool)$this->setMsgFlag((string)$uid, self::$flags['deleted'], false, true);
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
        $this->setMsgFlag((string)$uid, self::$flags['deleted'], false, true);
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

      return $this->parser->extractLiteralBlock($lines);
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
  public function getMsgFlags(int|array $msgno, bool $uid = false, ?string $raw = null): ?array
  {
    $multi = true;
    if (!is_array($msgno)) {
      $msgno = [(int)$msgno];
      $multi = false;
    }

    $msgno = array_filter(
      array_values(array_unique(array_map('intval', $msgno))),
      fn($n) => $n > 0
    );
    if (empty($msgno)) {
      return null;
    }

    $flags = [];
    try {
      if (is_null($raw)) {
        $command = (!empty($uid) ? 'UID FETCH ' : 'FETCH ') . implode(',', $msgno) . ' (FLAGS)';
        $lines = $this->rawCommand($command, true);
        $raw = implode("\n", $lines);
      }

      $lines = preg_split('/\R/', $raw) ?: [];
      foreach ($lines as $line) {
        if (!preg_match('/^\*\s+(\d+)\s+FETCH\s+\(/i', $line, $messageMatch)) {
          continue;
        }

        $messageNumber = (int)$messageMatch[1];
        if (!preg_match('/\bFLAGS\s+\(([^)]*)\)/i', $line, $flagsMatch)) {
          continue;
        }

        $flagsString = trim($flagsMatch[1]);
        $mflags = $flagsString === ''
            ? []
            : preg_split('/\s+/', $flagsString);
        if ($mflags === false) {
          $mflags = [];
        }

        $mflags = array_values(array_unique(array_map('trim', $mflags)));
        $key = $uid ? false : $messageNumber;
        if (preg_match('/\bUID\s+(\d+)/i', $line, $uidMatch)) {
          $key = (int)$uidMatch[1];
        }

        if ($key !== false) {
          $flags[$key] = $mflags;
        }
      }
    }
    catch (Exception $e) {
      $this->setError($e->getMessage(), $e->getCode());
      return null;
    }

    if (!empty($flags) && !$multi) {
      $flags = reset($flags);
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


  public function getMsgPriority(int $msgno, bool $uid = false, ?string $msgHeader = null): ?int
  {
    $priority = null;
    if (is_null($msgHeader)) {
      $msgHeader = $this->getMsgHeader($msgno, $uid);
    }

    if (!empty($msgHeader)) {
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


  public function getMsgSize(int $msgno, bool $uid = false, ?string $raw = null): ?int
  {
    if ($overview = $this->getMsgOverview($msgno, $uid, $raw)) {
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
   * Splits the quoted part from the reply in an email HTML content.
   */
  public function splitQuoteFromEmail(string $html): ?array
  {
    return $this->parser->quote($html);
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

  private function getMsgBySeqOrUid(int $msgno, bool $uid = false): ?array
  {
    try {
      $lines = $this->rawCommand(
        ($uid ? "UID " : "") . "FETCH $msgno (FLAGS INTERNALDATE RFC822.SIZE ENVELOPE UID BODY.PEEK[])",
        true
      );
      $raw = $this->parser->extractLiteralBlock($lines);
      $headers = $this->parser->headerInfo(preg_split("/\R\R/", $raw, 2)[0] ?? '');
      if (!$headers) {
        return null;
      }

      $msg = (array)$this->encoder->decodeEncodedWordsDeep($headers);
      $msg['priority'] = $this->getMsgPriority($msgno, false, $lines[0]) ?: 3;
      $msg['flags'] = $this->getMsgFlags($msgno, false, $lines[0]) ?: [];
      $msg['uid'] = $this->parser->uid($lines[0]);
      $msg['size'] = $this->parser->size($lines[0]);
      $msg['date_sent'] = !empty($msg['date'])
        ? date('Y-m-d H:i:s', strtotime($msg['date']))
        : null;
      $msg['date_server'] = $msg['date_sent'];
      if (!isset($msg['subject'])) {
        $msg['subject'] = '';
      }

      $msg['references'] = $this->parser->references($msg['references']);
      $msg['message_id'] = !empty($msg['message_id'])
        ? trim($msg['message_id'], '<>')
        : $this->transformString(($msg['uid'] ?? '') . ($msg['date_sent'] ?? '') . ($msg['subject'] ?? '')) . '@bbn.solutions';
      $msg['in_reply_to'] = empty($msg['in_reply_to']) ? false : trim($msg['in_reply_to'], '<>');
      $parsedMime = $this->parser->mimeMessage($raw);
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

    return $this->encoder->getDecodedValue($body, $encoding);
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
  private function _listSubscribed($dir)
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
  private function _listFolders($dir)
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
  private function _getFolders($dir)
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
  private function _getNamesFolders($dir)
  {
    if ($folders = $this->_getFolders($dir)) {
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

}
