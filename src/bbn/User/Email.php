<?php

namespace bbn\User;

use Exception;
use bbn\X;
use bbn\Db;
use bbn\Str;
use bbn\User;
use bbn\User\Preferences;
use bbn\Appui\Mailbox;
use bbn\Appui\Passwords;
use bbn\Models\Cls\Basic;
use bbn\Models\Tts\Dbconfig;
use bbn\Models\Tts\Optional;

class Email extends Basic
{
  use Dbconfig;
  use Optional;

  /** @var array */
  protected static $default_class_cfg = [
    'table' => 'bbn_users_emails',
    'tables' => [
      'users_emails' => 'bbn_users_emails',
      'users_emails_aliases' => 'bbn_users_emails_aliases',
      'users_emails_recipients' => 'bbn_users_emails_recipients',
      'users_contacts' => 'bbn_users_contacts',
      'users_contacts_links' => 'bbn_users_contacts_links'
    ],
    'arch' => [
      'users_emails' => [
        'id' => 'id',
        'id_user' => 'id_user',
        'id_folder' => 'id_folder',
        'msg_uid' => 'msg_uid',
        'msg_unique_id' => 'msg_unique_id',
        'date' => 'date',
        'id_sender' => 'id_sender',
        'subject' => 'subject',
        'size' => 'size',
        'attachments' => 'attachments',
        'flags' => 'flags',
        'is_read' => 'is_read',
        'id_parent' => 'id_parent',
        'id_thread' => 'id_thread',
        'external_uids' => 'external_uids',
        'excerpt' => 'excerpt'
      ],
      'users_emails_aliases' => [
        'id_account' => 'id_account',
        'id_link' => 'id_link',
        'main' => 'main'
      ],
      'users_emails_recipients' => [
        'id_email' => 'id_email',
        'id_contact_link' => 'id_contact_link',
        'type' => 'type'
      ],
      'users_contacts' => [
        'id' => 'id',
        'id_user' => 'id_user',
        'name' => 'name',
        'blacklist' => 'blacklist',
        'cfg' => 'cfg'
      ],
      'users_contacts_links' => [
        'id' => 'id',
        'id_contact' => 'id_contact',
        'type' => 'type',
        'value' => 'value',
        'num_sent' => 'num_sent',
        'last_sent' => 'last_sent'
      ]
    ]
  ];


  /** @var array An array of connection objects */
  protected $mboxes = [];

  /** @var bbn\Appui\Option The options object */
  protected $opt;

  /** @var bbn\Appui\Passwords The passwords object */
  protected $pw;


  /**
   * Returns a list typical folder types as they are recorded in the options
   *
   * @return array
   */
  public static function getFolderTypes(): array
  {
    return self::getOptions('folders');
  }


  /**
   * Returns a list of typical email accounts types as they are recorded in the options
   *
   * @return array
   */
  public static function getAccountTypes(): array
  {
    return self::getOptions('types');
  }


  public function __construct(
    private Db $db,
    /** @var user The user object */
    protected ?User $user = null,
    /** @var preferences The preferences object */
    protected ?Preferences $pref = null
  )
  {
    self::optionalInit();
    $this->_init_class_cfg();
    if (!$this->user) {
      $this->user = User::getInstance();
    }

    if (!$this->pref) {
      $this->pref = Preferences::getInstance();
    }
  }


  public function getMailbox(string $id_account): ?Mailbox
  {
    if (!isset($this->mboxes[$id_account])) {
      $this->getAccount($id_account);
    }

    if (isset($this->mboxes[$id_account])) {
      $mb = &$this->mboxes[$id_account];
      if (!isset($mb['mailbox'])) {
        $cfg = $this->mboxes[$id_account];
        $cfg['pass'] = $this->_get_password()->userGet($id_account, $this->user);
        $mb['mailbox'] = new Mailbox($cfg);
      }

      if (isset($mb['mailbox'])) {
        return $mb['mailbox'];
      }
    }

    return null;

  }


  /**
   * Returns the list of the accounts' IDs of the current user.
   *
   * @param bool $force
   * @return array|null
   */
  public function getAccountsIds(): ?array
  {
    if ($id_accounts = self::getOptionId('accounts')) {
      return $this->pref->retrieveIds($id_accounts);
    }

    return null;
  }


  /**
   * Returns the list of the accounts of the current user.
   *
   * @param bool $force
   * @return array|null
   */
  public function getAccounts(bool $force = false): array
  {
    $res = [];
    if ($ids = $this->getAccountsIds()) {
      foreach ($ids as $id) {
        $res[] = $this->getAccount($id, $force);
      }
    }

    return $res;
  }


  public function setAccountStage(string $id_account, int $stage): bool
  {
    $a = $this->pref->get($id_account);
    if ($a) {
      $a['stage'] = $stage;
      return $this->pref->set($id_account, $a);
    }

    return false;
  }

  public function getAccount(string $id_account, bool $force = false): ?array
  {
    if ($force || !isset($this->mboxes[$id_account])) {
      if ($a = $this->pref->get($id_account)) {
        $this->mboxes[$id_account] = [
          'id' => $a['id'],
          'host' => $a['host'] ?? null,
          'login' => $a['login'],
          'type' => $a['type'],
          'port' => $a['port'] ?? null,
          'ssl' => $a['ssl'] ?? true,
          'folders' => null,
          'last_uid' => $a['last_uid'] ?? null,
          'last_check' => $a['last_check'] ?? null,
          'id_account' => $id_account
        ];
        $this->mboxes[$id_account]['folders'] = $this->getFolders($this->mboxes[$id_account]);
        if (!isset($a['stage'])) {
          $a['stage'] = 1;
          $this->pref->set($id_account, $a);
        }
        $this->mboxes[$id_account]['stage'] = $a['stage'];
      }
    }
    return $this->mboxes[$id_account] ?? null;
  }


  public function checkConfig($cfg): bool
  {
    if (X::hasProps($cfg, ['login', 'pass', 'type'], true)) {
      $mb = new Mailbox($cfg);
      return $mb->check();
    }
    return false;
  }


  public function updateAccount(string $id_account, array $cfg): bool
  {
    if (X::hasProps($cfg, ['login', 'pass', 'type'], true)
      && ($acc = $this->getAccount($id_account))
      && ($this->pref->setCfg(
        $id_account,
        [
          'host' => $cfg['host'] ?? null,
          'login' => $cfg['login'],
          'type' => $cfg['type'],
          'port' => $cfg['port'] ?? null,
          'ssl' => $cfg['ssl'] ?? true,
          'last_uid' => $cfg['last_uid'] ?? null,
          'last_check' => $cfg['last_check'] ?? null
        ]
      ))
    ) {
      return true;
    }

    return false;
  }


  public function deleteAccount(string $id_account): bool
  {
    return (bool)$this->pref->delete($id_account);
  }


  public function addAccount(array $cfg): string
  {
    if (!X::hasProps($cfg, ['login', 'pass', 'type'], true)) {
      throw new \Exception("Missing arguments");
    }

    if (!($id_accounts = self::getOptionId('accounts'))) {
      throw new \Exception("Impossible to find the account option");
    }

    // toGroup as this option will use different user options
    if (!($id_pref = $this->pref->addToGroup(
      $id_accounts,
      [
        'id_user' => $this->user->getId(),
        'login' => $cfg['login'],
        'type' => $cfg['type'],
        'host' => $cfg['host'] ?? null,
        'port' => $cfg['port'] ?? null,
        'ssl' => $cfg['ssl'] ?? true
      ]
    ))
    ) {
      throw new \Exception("Impossible to add the preference");
    }

    if (!$this->_get_password()->userStore($cfg['pass'], $id_pref, $this->user)) {
      throw new \Exception("Impossible to set the password");
    }

    $this->getAccount($id_pref, true);
    if (!empty($cfg['folders'])) {
      $this->syncFolders($id_pref, $cfg['folders']);
    }

    return $id_pref;
  }


  public function reset(string $id_account): bool
  {
    if (($account = $this->getAccount($id_account))
      && ($num = $this->pref->deleteBits($id_account))
    ) {
      return true;
    }

    return false;
  }


  public function createFolder(string $id_account, string $name, string $id_parent = null): bool
  {
    $mb = $this->getMailbox($id_account);
    $uid_parent = "";
    if ($id_parent) {
      $uid_parent = $this->getFolder($id_parent)['uid'];
    }
    $mboxName = $id_parent ? $uid_parent . '.' . $name : $name;
    if ($mb && $mb->createMbox($mboxName)) {
      if ($this->createFolderDb($id_account, $name, $id_parent)) {
        $this->mboxes[$id_account]['folders'] = $this->getFolders($this->mboxes[$id_account]);
        return true;
      }
    }
    return false;
  }


  public function createFolderDb(string $id_account, string $name, string $id_parent = null): bool
  {
    $types = self::getFolderTypes();

    $a = [
      'id_option' => X::getField($types, ['code' => 'folders'], 'id'),
      'text' => $name,
      'uid' => $name,
      'subscribed' => true
    ];

    if ($id_parent) {
      $uid_parent = $this->getFolder($id_parent)['uid'];
      $a['uid'] = $uid_parent . '.' . $name;
      $a['id_parent'] = $id_parent;
    }

    return (bool)$this->pref->addBit($id_account, $a);
  }


  public function renameFolder(string $id, string $name, string $id_account, string $id_parent = null): bool
  {
    $mb = $this->getMailbox($id_account);
    $uid_parent = "";
    if ($id_parent) {
      $uid_parent = $this->getFolder($id_parent)['uid'];
    }
    $mboxName = $id_parent ? $uid_parent . '.' . $name : $name;
    if ($mb && $mb->renameMbox($this->getFolder($id)['uid'], $mboxName)) {
      if ($this->renameFolderDb($id, $name, $id_account, $id_parent)) {
        return true;
      }
    }
    return false;
  }


  public function renameFolderDb(string $id, string $name, string $id_account, string $id_parent = null): bool
  {
    $a = [
      'text' => $name,
      'uid' => $name,
    ];

    if ($id_parent) {
      $uid_parent = $this->getFolder($id_parent)['uid'];
      $a['uid'] = $uid_parent . '.' . $name;
      $a['id_parent'] = $id_parent;
    }
    if ($this->pref->updateBit($id, $a)) {
      if (!$id_parent) {
        $this->pref->moveBit($id, null);
      }
      $this->mboxes[$id_account]['folders'] = $this->getFolders($this->mboxes[$id_account]);
      return true;
    };
    return false;
  }


  public function deleteFolder(string $id, string $id_account): bool
  {
    $mb = $this->getMailbox($id_account);
    $folder = $this->getFolder($id);
    if ($folder && $mb->deleteMbox($folder['uid'])) {
      if ($this->deleteFolderDb($id)) {
        $this->mboxes[$id_account]['folders'] = $this->getFolders($this->mboxes[$id_account]);
        return true;
      }
    }
    return false;
  }


  public function deleteFolderDb(string $id): bool
  {
    return (bool)$this->pref->deleteBit($id);
  }


  public function checkFolder(array $folder, $sync = false)
  {
    if (X::hasProp($folder, 'uid')
      && ($mb = $this->getMailbox($folder['id_account']))
      && $mb->check()
    ) {
      if ($mb->update($folder['uid'])
        && ($folders = $mb->getFolders())
        && ($res = $folders[$folder['uid']])
        && ($info = $mb->getInfoFolder($folder['uid']))
      ) {
        if (!array_key_exists('db_uid', $res)) {
          $res['db_uid'] = null;
        }

        if (($res['num_msg'] && !$folder['last_uid']) || ($folder['last_uid'] !== $res['db_uid']) || ($res['num_msg'] !== $info->Nmsgs)) {
          $id_account = $folder['id_account'];
          unset($folder['id_account']);
          $res = array_merge($folder, $res);
          $this->pref->updateBit($folder['id'], $res, true);
          $res['id_account'] = $id_account;
          $this->getAccount($id_account, true);
          if ($sync) {
            $this->syncEmails($res);
          }
        }

        return $res;
      }
    }

    return null;

  }


  public function getFolders($account, $force = false)
  {
    $acc = is_array($account) ? $account : $acc = $this->getAccount($account);
    if ($acc) {
      $types = self::getFolderTypes();
      if ($force) {
        $this->syncFolders($acc['id']);
      }

      $cfg = $this->class_cfg['arch']['users_emails'];
      $table = $this->class_cfg['tables']['users_emails'];
      return X::map(
        function ($a) use ($types, $cfg, $table) {
          if (!isset($a['uid'])) {

            //die(X::dump("NO UID", $a, debug_backtrace()));
          }

          $res = [
            'id' => $a['id'],
            'id_account' => $a['id_user_option'],
            'text' => $a['text'],
            'uid' => $a['uid'],
            'id_option' => $a['id_option'],
            'id_parent' => $a['id_parent'] ?? null,
            'type' => X::getField($types, ['id' => $a['id_option']], 'code'),
            'db_uid_max' => $this->db->selectOne(
              $table,
              'MAX(' . $this->db->csn($cfg['msg_uid'], true) . ')',
              [
                $cfg['id_folder'] => $a['id'],
                $cfg['id_user'] => $this->user->getId()
              ]
            ),
            'db_uid_min' => $this->db->selectOne(
              $table,
              'MIN(' . $this->db->csn($cfg['msg_uid'], true) . ')',
              [
                $cfg['id_folder'] => $a['id'],
                $cfg['id_user'] => $this->user->getId()
              ]
            ),
            'num_msg' => $this->db->count($table, [$cfg['id_folder'] => $a['id']]),
            'last_uid' => $a['last_uid'] ?? null,
            'last_check' => $a['last_check'] ?? null,
            'hash' => $a['hash'] ?? null,
            'subscribed' => $a['subscribed'] ?? false
          ];
          if (!empty($a['items'])) {
            $res['items'] = $a['items'];
          }

          return $res;
        },
        $this->pref->getFullBits($acc['id']),
        'items'
      );
    }

    return null;
  }

  public function flattenFolders($folders): array
  {
    $res = [];
    foreach ($folders as $f) {
      if (!empty($f['items']) && is_array($f['items']) && count($f['items'])) {
        $res = array_merge($res, $this->flattenFolders($f['items']));
      }
      $res[] = $f;
    }
    return $res;
  }

  public function getHashes(): ?array
  {
    $res = [];
    $account_hash = "";
    foreach ($this->getAccounts() as $a) {
      $res[$a['id']] = [
        'hash' => "",
        'folders' => []
      ];
      $folders = $this->flattenFolders($this->getFolders($a['id']));
      foreach ($folders as $f) {
        $res[$a['id']]['folders'][$f['id']] = $f['hash'];
        if ($f['hash']) {
          $account_hash .= $f['hash'];
        } else {
          $account_hash .= $f['id'];
        }
      }
      $res[$a['id']]['hash'] = md5($account_hash);
    }
    return $res;
  }

  public function getFolder(string $id, bool $force = false): ?array
  {
    $types = self::getFolderTypes();
    $cfg = $this->class_cfg['arch']['users_emails'];
    $table = $this->class_cfg['tables']['users_emails'];
    $a = $this->pref->getBit($id);
    if ($a) {
      return [
        'id' => $a['id'],
        'id_account' => $a['id_user_option'],
        'text' => $a['text'],
        'uid' => $a['uid'],
        'id_option' => $a['id_option'],
        'type' => X::getField($types, ['id' => $a['id_option']], 'code'),
        'db_uid_max' => $this->db->selectOne(
          $table,
          'MAX(' . $this->db->csn($cfg['msg_uid'], true) . ')',
          [
            $cfg['id_folder'] => $a['id'],
            $cfg['id_user'] => $this->user->getId()
          ]
        ),
        'db_uid_min' => $this->db->selectOne(
          $table,
          'MIN(' . $this->db->csn($cfg['msg_uid'], true) . ')',
          [
            $cfg['id_folder'] => $a['id'],
            $cfg['id_user'] => $this->user->getId()
          ]
        ),
        'num_msg' => $this->db->count($table, [$cfg['id_folder'] => $a['id']]),
        'last_uid' => $a['last_uid'] ?? null,
        'last_check' => $a['last_check'] ?? null,
        'hash' => $a['hash'] ?? null
      ];
    }

    return null;
  }

  public function getNextUid(array $folder, int $uid): ?int
  {
    $mb = $this->getMailbox($folder['id_account']);
    $mb->selectFolder($folder['uid']);
    return $mb->getNextUid($uid);
  }

  public function syncEmails(array $folder, int $limit = 0): ?int

  {

    if (X::hasProps($folder, ['id', 'id_account', 'last_uid', 'uid'])) {
      $res = 0;
      $mb = $this->getMailbox($folder['id_account']);
      $info = $mb->getInfoFolder($folder['uid']);
      $mb->selectFolder($folder['uid']);

      if (!empty($folder['last_uid'])) {
        $first_uid = $mb->getFirstUid();
        $last_uid = $mb->getLastUid();
        $start = null;
        $real_end = null;

        if (isset($folder['db_uid_min']) && isset($folder['db_uid_max'])) {
          if ($folder['db_uid_min'] == $first_uid && $folder['db_uid_max'] == $last_uid) {
            return 0;
          }

          if ($folder['db_uid_max'] != $last_uid) {

            $start = $last_uid;
            $real_end = $mb->getNextUid($folder['db_uid_max']);
          } else if ($folder['db_uid_min'] != $first_uid) {

            $start = $folder['db_uid_min'];
            $real_end = $start - $limit;
            if ($real_end < 1) {
              $real_end = 1;
            }
            try {
              if ($mb->getMsgNo($real_end) < $first_uid) {
                $real_end = $first_uid;
              }
            } catch (\Exception $e) {
              $real_end = $first_uid;
            }

          }
        }
        else {

          $start = $last_uid;
          $real_end = $start - $limit;

          if ($real_end < 1) {
            $real_end = 1;
          }
          try {
            if ($mb->getMsgNo($real_end) < $first_uid) {
              $real_end = $first_uid;
            }
          } catch (\Exception $e) {
            $real_end = $first_uid;
          }
        }

        if (!$start || !$real_end) {
          X::log("start: $start, real_end: $real_end, first_uid: $first_uid, last_uid: $last_uid", 'poller_email_error');
        }

        try {
          $start = $mb->getMsgNo($start);
          $real_end = $mb->getMsgNo($real_end);
        } catch (\Exception $e) {
          $start = $last_uid;
          $real_end = $first_uid;

          if ($folder['db_uid_min'] != $first_uid) {
            $start = $folder['db_uid_min'];
          }
        }



        $end = $start;
        X::log("start: $start, real_end: $real_end, first_uid: $first_uid, last_uid: $last_uid");
        X::log("start emails listing");
        $all = $mb->getEmailsList($folder, $start, $real_end);
        X::log("end emails listing");
        if ($all) {
          //var_dump($start, $end);
          X::log($all, 'emails');
          foreach ($all as $a) {
            X::log("start insert email");
            if ($this->insertEmail($folder, $a)) {
              X::log("end insert email");
              $res++;
            } else {
              X::log("end insert email with error");
              //throw new \Exception(X::_("Impossible to insert the email with ID").' '.$a['message_id']);
              $this->log(X::_("Impossible to insert the email with ID") . ' ' . $a['message_id']);
            }
          }

          $hash = md5(json_encode(['numMsg' => $folder['num_msg'], 'lastUid' => $folder['last_uid']]));
          $this->pref->updateBit($folder['id'], [
            'last_check' => date('Y-m-d H:i:s'),
            'hash' => $hash
          ], true);
        } else {
          X::log(X::_("Impossible to get the emails for folder") . ' ' . $folder['uid'] . ' ' . X::_("from") . ' ' . $start . ' ' . X::_("to") . ' ' . $end . ' (' . $real_end . ')');
          throw new \Exception(
            X::_("Impossible to get the emails for folder")
            . ' ' . $folder['uid']
            . ' ' . X::_("from") . ' ' . $start
            . ' ' . X::_("to") . ' ' . $end
            . ' (' . $real_end . ')'
          );
        }
      }

      if ($info->Nmsgs > ($res + $folder['num_msg'])) {
        $cfg = $this->class_cfg['arch']['users_emails'];
        $table = $this->class_cfg['tables']['users_emails'];
        $num = $res + $folder['num_msg'];
        $s2 = 0;

        while ($info->Nmsgs < $num) {
          $msg = $this->db->rselect($table, [$cfg['id'], $cfg['msg_uid']], [$cfg['id_folder'] => $folder['id']], [$cfg['msg_uid'] => 'DESC'], $s2);
          if (!$mb->getMsgNo($msg['msg_uid'])) {
            if ($this->db->delete($table, [$cfg['id'] => $msg['id']])) {
              $num--;
              $s2--;
            }
          }
          $s2++;
        }
      }
      return $res;
    }
    return null;
  }

  public function getLastUid($folder)
  {
    $mb = $this->getMailbox($folder['id_account']);
    $mb->selectFolder($folder['uid']);
    return $mb->getLastUid();
  }

  public function getFirstUid($folder)
  {
    $mb = $this->getMailbox($folder['id_account']);
    $mb->selectFolder($folder['uid']);
    return $mb->getFirstUid();
  }


  /**
   * Returns a list of emails based on their folder.
   *
   * @param string $id_folder
   * @param array $filter
   * @param int $limit
   * @param int $start
   *
   * @return array|null
   */
  public function getList(string $id_folder, array $post): ?array
  {
    if ($ids = $this->idsFromFolder($id_folder)) {
      $cfg = $this->class_cfg['arch']['users_emails'];
      $table = $this->class_cfg['tables']['users_emails'];
      $real_filter = [
        'logic' => 'AND',
        'conditions' => [
          $cfg['id_folder'] => $ids
        ]
      ];
      if (!empty($post['filters'])) {
        if (!isset($post['filters']['conditions'])) {
          $post['filters'] = ['conditions' => $post['filters']];
        }

        if (!empty($post['filters']['conditions'])) {
          $real_filter['conditions'][] = $post['filters'];
        }
      }

      $post['filters'] = $real_filter;
      $grid = new \bbn\Appui\Grid($this->db, $post, [
        'table' => $table,
        'fields' => $cfg
      ]);


      if ($grid->check()) {
        return $grid->getDatatable();
      }
    }

    return null;
  }


  public function getLoginByEmailId($id)
  {
    $cfg = $this->class_cfg['arch']['users_emails'];
    $table = $this->class_cfg['tables']['users_emails'];
    $em = $this->db->rselect($table, $cfg, [$cfg['id'] => $id]);
    if ($em) {
      $folder = $this->getFolder($em['id_folder']);
      if ($folder
        && ($mb = $this->getAccount($folder['id_account']))) {
        return $mb;
      }
    }
    return null;
  }

  public function getEmail($id): ?array
  {
    $cfg = $this->class_cfg['arch']['users_emails'];
    $table = $this->class_cfg['tables']['users_emails'];
    $em = $this->db->rselect($table, $cfg, [$cfg['id'] => $id]);
    if ($em) {
      $folder = $this->getFolder($em['id_folder']);
      if ($folder
        && ($mb = $this->getMailbox($folder['id_account']))
        && $mb->selectFolder($folder['uid'])
        && Str::isInteger($number = $mb->getMsgNo($em['msg_uid']))
      ) {
        if ($number === 0) {
          $this->db->delete($table, [$cfg['id'] => $id]);
          return null;
        }
        $arr = $mb->getMsg($number, $id, $folder['id_account']);
        $arr['id_account'] = $folder['id_account'];
        $arr['msg_unique_id'] = $em['msg_unique_id'];
        return $arr;
      }
    }

    return null;
  }

  public function getEmailByUID($post): ?array
  {
    $cfg = $this->class_cfg['arch']['users_emails'];
    $table = $this->class_cfg['tables']['users_emails'];

    $grid = new \bbn\Appui\Grid($this->db, $post, [
      'table' => $table,
      'fields' => $cfg
    ]);

    if ($grid->check()) {
      return $grid->getDatatable();
    }

    return null;
  }

  public function getThreadId(?string $id): ?string
  {
    if ($id === null) {
      return null;
    }
    $cfg = $this->class_cfg['arch']['users_emails'];
    $table = $this->class_cfg['tables']['users_emails'];

    $email = $this->db->rselect([
      'table' => $table,
      'fields' => $cfg,
      'where' => [
        'conditions' => [
          $cfg['id'] => $id
        ]
      ]
    ]);

    while ($email['id_parent']) {
      $email = $this->db->rselect([
        'table' => $table,
        'fields' => $cfg,
        'where' => [
          'conditions' => [
            $cfg['id'] => $email['id_parent']
          ]
        ]
      ]);
    }

    return $email['thread_id'];

  }

  public function updateRead($id)
  {
    $cfg = $this->class_cfg['arch']['users_emails'];
    $table = $this->class_cfg['tables']['users_emails'];
    $this->db->update($table, [$cfg['is_read'] => 1], [$cfg['id'] => $id]);
  }

  public function syncThreads(int $limit)
  {
    $cfg = $this->class_cfg['arch']['users_emails'];
    $table = $this->class_cfg['tables']['users_emails'];

    // select all emails of the user where id_thread is null and external_id is not null
    $emails = $this->db->rselectAll([
      'table' => $table,
      'fields' => $cfg,
      'where' => [
        'logic' => 'AND',
        'conditions' => [
          [
            'field' => $cfg['id_user'],
            'value' => $this->user->getId()
          ],
        ]
      ],
      'order' => [
        'field' => $cfg['date']
      ]
    ]);

  }


  public function insertEmail(array $folder, array $email)
  {
    $id = false;
    if (X::hasProps($email, ['from', 'uid'])) {
      $cfg = $this->class_cfg['arch']['users_emails'];
      $table = $this->class_cfg['tables']['users_emails'];
      $existing = $this->db->selectOne(
        $table,
        $cfg['id'],
        [
          $cfg['id_user'] => $this->user->getId(),
          $cfg['msg_unique_id'] => $email['message_id'],
          $cfg['msg_uid'] => $email['uid']
        ]
      );
      foreach (Mailbox::getDestFields() as $df) {
        if (!empty($email[$df])) {
          foreach ($email[$df] as &$dest) {
            if ($id = $this->retrieveEmail($dest['email'])) {
              $sent_opt = X::getField(self::getFolderTypes(), ['code' => 'sent'], 'id');
              if ($sent_opt === $folder['id_option']) {
                $this->addSentToLink($id, Date('Y-m-d H:i:s', strtotime($email['date'])));
              }
            } elseif (!($id = $this->addContactFromMail($dest))) {
              throw new \Exception(X::_("Impossible to add the contact") . ' ' . $dest['email']);
            }

            $dest['id'] = $id;
          }

          if ($df === 'from') {
            $id_sender = $id;
          }
        }
      }


      if (!empty($id_sender)) {
        $id_parent = null;
        $id_thread = null;

        //die(var_dump($email));
        $external = null;
        if (!empty($email['in_reply_to']) || !empty($email['references'])) {
          $external = [
            'in_reply_to' => $email['in_reply_to'] ?? null,
            'references' => $email['references'] ?? null
          ];
        }

        if ($email['priority']) {
          // if Flagged dont contains none of the priority flag, add it
          if (!str_contains($email['Flagged'], 'Highest')
            && !str_contains($email['Flagged'], 'High')
            && !str_contains($email['Flagged'], 'Normal')
            && !str_contains($email['Flagged'], 'Low')
            && !str_contains($email['Flagged'], 'Lowest')
          ) {
            switch ($email['priority']) {
              case 1:
                $email['Flagged'] .= ' Highest';
                break;
              case 2:
                $email['Flagged'] .= ' High';
                break;
              case 3:
                $email['Flagged'] .= ' Normal';
                break;
              case 4:
                $email['Flagged'] .= ' Low';
                break;
              case 5:
                $email['Flagged'] .= ' Lowest';
                break;
            }
            // trim the space if is in first position
            if (str_starts_with($email['Flagged'], ' ')) {
              $email['Flagged'] = substr($email['Flagged'], 1);
            }
          }
        }


        $ar = [
          $cfg['id_user'] => $this->user->getId(),
          $cfg['id_folder'] => $folder['id'],
          $cfg['msg_uid'] => $email['uid'],
          $cfg['msg_unique_id'] => $email['message_id'],
          $cfg['date'] => date('Y-m-d H:i:s', strtotime($email['date'])),
          $cfg['id_sender'] => $id_sender,
          $cfg['subject'] => $email['subject'] ?: '',
          $cfg['size'] => $email['Size'],
          $cfg['attachments'] => empty($email['attachments']) ? null : json_encode($email['attachments']),
          $cfg['flags'] => $email['Flagged'] ?: null,
          $cfg['is_read'] => $email['Unseen'] ? 0 : 1,
          $cfg['id_parent'] => $id_parent,
          $cfg['id_thread'] => $id_thread,
          $cfg['external_uids'] => $external ? json_encode($external) : null,
          $cfg['excerpt'] => ""
        ];

        if ($existing) {
          $id = $existing;
        } else if ($test = $this->db->insert($table, $ar)) {
          X::log(['insertEmail' => $test, 'ar' => $ar]);
          $id = $this->db->lastId();
          $mb = $this->getMailbox($folder['id_account']);
          $mb->selectFolder($folder['uid']);

          $number = $mb->getMsgNo($email['uid']);
          if ($number) {
            $msg = $mb->getMsg($number, $id, $folder['id_account']);
            if (empty($text)) {
              $text = $msg['html'];
            }
          } else {
            $text = "";
          }

          if (is_null($text)) {
            $text = "";
          }
          // update excerpt column where id is same
          $this->db->update($table, [$cfg['excerpt'] => $text], [$cfg['id'] => $id]);
          foreach (Mailbox::getDestFields() as $df) {
            if (in_array($df, ['to', 'cc', 'bcc']) && !empty($email[$df])) {
              foreach ($email[$df] as $dest) {
                if (!empty($dest['id'])) {
                  $this->addLinkToMail($id, $dest['id'], $df);
                }
              }
            }
          }
        }

      }
    }

    return $id;
  }


  public function addContactFromMail(array $dest, bool $blacklist = false): ?string
  {
    if (X::hasProp($dest, 'email', true)) {
      if (!Str::isEmail($dest['email'])) {
        return null;
      }

      $cfg_contacts = $this->class_cfg['arch']['users_contacts'];
      $cfg_links = $this->class_cfg['arch']['users_contacts_links'];
      $table_contacts = $this->class_cfg['tables']['users_contacts'];
      $table_links = $this->class_cfg['tables']['users_contacts_links'];
      if ($this->db->insert($table_contacts, [
        $cfg_contacts['id_user'] => $this->user->getId(),
        $cfg_contacts['name'] => empty($dest['name']) ? null : mb_substr($dest['name'], 0, 100),
        $cfg_contacts['blacklist'] => $blacklist ? 1 : 0
      ])) {
        $id_contact = $this->db->lastId();
        if ($this->db->insert($table_links, [
          'id_contact' => $id_contact,
          'type' => 'email',
          'value' => $dest['email']
        ])) {
          return $this->db->lastId();
        }
      }
    }

    return null;
  }


  public function getLink($id): ?array
  {
    $cfg = $this->class_cfg['arch']['users_contacts_links'];
    $table = $this->class_cfg['tables']['users_contacts_links'];
    $data = $this->db->rselect($table, $cfg, [$cfg['id'] => $id]);
    return $data ?: null;
  }


  public function addLinkToMail(string $id_email, string $id_link, string $type): bool
  {
    $cfg = $this->class_cfg['arch']['users_emails_recipients'];
    $table = $this->class_cfg['tables']['users_emails_recipients'];
    return (bool)$this->db->insertIgnore(
      $table,
      [
        $cfg['id_email'] => $id_email,
        $cfg['id_contact_link'] => $id_link,
        $cfg['type'] => $type
      ]
    );

  }


  public function addSentToLink(string $id_link, string $date = null): bool
  {
    if ($link = $this->getLink($id_link)) {
      $cfg = $this->class_cfg['arch']['users_contacts_links'];
      $table = $this->class_cfg['tables']['users_contacts_links'];
      if (!$date) {
        $date = date('Y-m-d H:i:s');
      }
      if ($link['last_sent'] && ($link['last_sent'] > $date)) {
        $date = $link['last_sent'];
      }

      return (bool)$this->db->update(
        $table,
        [
          $cfg['num_sent'] => $link[$cfg['num_sent']] + 1,
          $cfg['last_sent'] => $date
        ], [
          'id' => $id_link
        ]
      );
    }

    return false;
  }


  public function retrieveEmail(string $email): ?string
  {
    if (Str::isEmail($email)) {
      $contacts = $this->class_cfg['tables']['users_contacts'];
      $cfg_c = $this->class_cfg['arch']['users_contacts'];
      $links = $this->class_cfg['tables']['users_contacts_links'];
      $cfg_l = $this->class_cfg['arch']['users_contacts_links'];
      return $this->db->selectOne(
        [
          'tables' => [$links],
          'field' => $this->db->cfn($cfg_l['id'], $links),
          'join' => [
            [
              'table' => $contacts,
              'on' => [
                [
                  'field' => $cfg_l['id_contact'],
                  'exp' => $this->db->cfn($cfg_c['id'], $contacts)
                ]
              ]

            ]
          ],
          'where' => [
            'value' => $email,
            'id_user' => $this->user->getId(),
            'type' => 'email'
          ]
        ]
      );
    }

    return null;
  }


  public function getContact(string $email, string $name, $force)
  {

  }


  public function getContacts(): array
  {
    $contacts = $this->class_cfg['tables']['users_contacts'];
    $cfg_c = $this->class_cfg['arch']['users_contacts'];
    $links = $this->class_cfg['tables']['users_contacts_links'];
    $cfg_l = $this->class_cfg['arch']['users_contacts_links'];
    $rows = $this->db->rselectAll(
      [
        'tables' => [$links],
        'fields' => [
          $this->db->cfn($cfg_l['id'], $links),
          $this->db->cfn($cfg_l['value'], $links),
          $this->db->cfn($cfg_l['id_contact'], $links),
          $this->db->cfn($cfg_l['num_sent'], $links),
          $this->db->cfn($cfg_l['last_sent'], $links),
          $this->db->cfn($cfg_c['name'], $contacts),
          $this->db->cfn($cfg_c['cfg'], $contacts),
          $this->db->cfn($cfg_c['blacklist'], $contacts),
          'sortIndex' => 'IFNULL(' . $this->db->cfn($cfg_c['name'], $contacts, true) . ',' . $this->db->cfn($cfg_l['value'], $links) . ')'
        ],
        'join' => [
          [
            'table' => $contacts,
            'on' => [
              [
                'field' => $cfg_l['id_contact'],
                'exp' => $this->db->cfn($cfg_c['id'], $contacts)
              ]
            ]

          ]
        ],
        'where' => [
          'id_user' => $this->user->getId(),
          'type' => 'email'
        ],
        'order' => [
          'sortIndex' => 'ASC'
        ]
      ]
    );
    $res = [];
    if ($rows) {
      foreach ($rows as $r) {
        $res[] = [
          'value' => $r['id'],
          'text' => (empty($r['name']) ? '' : $r['name'] . ' - ') . $r['value'],
          'cfg' => empty($r['cfg']) ? [] : json_decode($r['cfg'], true),
          'id_contact' => $r['id_contact'],
          'num_sent' => $r['num_sent'],
          'last_sent' => $r['last_sent'],
          'blacklist' => $r['blacklist']
        ];
      }
    }

    return $res;
  }


  public function syncFolders(string $id_account, array $subscribed = [])
  {
    // get Mailbox account
    if ($mb = $this->getMailbox($id_account)) {
      // get the parameter (host and port)
      $mbParam = $mb->getParams();
      // get the option 'folders'
      $types = self::getFolderTypes();

      $put_in_res = function (array $a, &$res, $prefix = '') use (&$put_in_res, $subscribed) {
        // set the first value of $a in $ele and remove it in the array
        $ele = array_shift($a);
        // search if res contain an array with 'text' => $ele and return the index or null instead
        $idx = X::find($res, ['text' => $ele]);

        if (null === $idx) {
          // count number of element in array (useless ?)
          $idx = count($res);
          // add $ele in the res array
          $res[] = [
            'text' => $ele,
            'uid' => $prefix . $ele,
            'items' => [],
            'subscribed' => in_array($prefix . $ele, $subscribed)
          ];
        }
        if (count($a)) {
          $put_in_res($a, $res[$idx]['items'], $prefix . $ele . '.');
        }
      };

      $compare = function (
        array $real,
        array $db,
        array &$res = null,
              $id_parent = null
      ) use (&$compare): array {
        if (!$res) {
          $res = ['add' => [], 'delete' => []];
        }

        foreach ($real as $r) {
          $idx = X::find($db, ['text' => $r['text']]);
          if (null === $idx) {
            if ($id_parent) {
              $r['id_parent'] = $id_parent;
            }

            $res['add'][] = $r;
          } elseif ($r['items'] && $db[$idx]['items']) {
            $compare($r['items'], $db[$idx]['items'], $res, $db[$idx]['id']);
          }
        }

        foreach ($db as $r) {
          $idx = X::find($real, ['text' => $r['text']]);
          if (null === $idx) {
            $res['delete'][] = $r;
          }
        }

        return $res;
      };

      $pref = $this->pref;

      $import = function (array $to_add, $id_parent = null) use ($id_account, &$pref, &$import, &$types) {
        foreach ($to_add as $a) {
          if ($id_parent) {
            $a['id_parent'] = $id_parent;
            $a['id_option'] = X::getField($types, ['code' => 'folders'], 'id');
          } else {
            foreach ($types as $type) {
              if (!empty($type['names'])) {
                if (in_array($a['text'], $type['names'], true)) {
                  $a['id_option'] = $type['id'];
                  break;
                }
              }
            }

            if (!isset($a['id_option'])) {
              $a['id_option'] = X::getField($types, ['code' => 'folders'], 'id');
            }
          }

          if ($id_bit = $pref->addBit($id_account, $a)) {
            if (!empty($a['items'])) {
              $import($a['items'], $id_bit);
            }
          }
        }
      };

      $res = [];
      $all = $mb->listAllFolders();
      foreach ($all as $dir) {
        $tmp = str_replace($mbParam, '', $dir);
        $bits = X::split($tmp, '.');
        $put_in_res($bits, $res);
      }

      // We have a tree
      $db_tree = $this->pref->getFullBits($id_account);

      $result = $compare($res, $db_tree);

      $import($result['add']);
      return ['real' => $res, 'db' => $db_tree, 'compare' => $result];
    }

    return null;
  }


  public function send(string $id_account, array $cfg): int
  {
    if ($mb = $this->getMailbox($id_account)) {
      $fields = ['to', 'cc', 'bcc'];
      $num = 0;
      $dest = [];
      /*
                foreach ($fields as $field) {
                  $dest[$field] = [];
                  if (!empty($cfg[$field])) {
                    foreach ($cfg[$field] as $d) {
                      if (Str::isEmail($d)) {
                        $dest[$field][] = $d;
                        $num++;
                      }
                    }
                  }
                }
                */

      if (!empty($cfg['title']) || !empty($cfg['text'])) {
        $mailer = $mb->getMailer();
        return $mailer->send($cfg);
      }
    }

    throw new \Exception(X::_("Impossible to find the mailbox"));
  }


  public function getStructure($id_account, $force)
  {

  }


  protected function idsFromFolder($id_folder): ?array
  {
    $cfg = $this->class_cfg['arch']['users_emails'];
    $table = $this->class_cfg['tables']['users_emails'];
    $types = self::getFolderTypes();
    if ($common_folder = X::getRow($types, ['id' => $id_folder])) {
      $ids = [];
      $accounts = $this->getAccounts();
      foreach ($accounts as $a) {
        foreach ($this->getFolders($a['id']) as $f) {
          if ($f['id_option'] === $common_folder['id']) {
            $ids[] = $f['id'];
          }
        }
      }
    } elseif (Str::isUid($id_folder)) {
      $bit = $this->pref->getBit($id_folder);
      if (!$bit) {
        // It's not a folder but an account
        if ($pref = $this->pref->get($id_folder)) {
          // we look for inbox
        }
      } else {
        $ids = [$id_folder];
      }
    } else if ($id_folder === 'conversations') {
      $inbox = X::getRow($types, ['code' => 'inbox']);
      $sent = X::getRow($types, ['code' => 'sent']);
      $ids = [];
      $accounts = $this->getAccounts();
      foreach ($accounts as $a) {
        foreach ($this->getFolders($a['id']) as $f) {
          if (($f['id_option'] === $inbox['id']) || ($f['id_option'] === $sent['id'])) {
            $ids[] = $f['id'];
          }
        }
      }
    }

    if (!empty($ids)) {
      return $ids;
    }

    return null;
  }


  private function _get_password(): Passwords
  {
    if (!$this->pw) {
      $this->pw = new Passwords($this->db);
    }

    return $this->pw;
  }


}

