<?php
namespace bbn\User;

use bbn;
use bbn\X;
use bbn\Str;
use bbn\User;

class Email extends bbn\Models\Cls\Basic
{
  use bbn\Models\Tts\Dbconfig;
  use bbn\Models\Tts\Optional;

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
        'external_uids' => 'external_uids'
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

  /** @var user The user object */
  protected $user;

  /** @var preferences The preferences object */
  protected $pref;

  /** @var bbn\Appui\Option The options object */
  protected $opt;

  /** @var bbn\Appui\Passwords The passwords object */
  protected $pw;


  public static function getFolderTypes(): array
  {
    return self::getOptions('folders');
  }


  public static function getAccountTypes(): array
  {
    return self::getOptions('types');
  }


  public function __construct(bbn\Db $db, User $user = null, Preferences $preferences = null)
  {
    self::optionalInit();
    $this->_init_class_cfg();
    $this->db   = $db;
    $this->user = $user ?: bbn\User::getInstance();
    $this->pref = $preferences ?: bbn\User\Preferences::getInstance();;
  }


  public function getMailbox(string $id_account)
  {
    if (!isset($this->mboxes[$id_account])) {
      $this->getAccount($id_account);
    }

    if (isset($this->mboxes[$id_account])) {
      $mb = &$this->mboxes[$id_account];
      if (!isset($mb['mailbox'])) {
        $cfg           = $this->mboxes[$id_account];
        $cfg['pass']   = $this->_get_password()->userGet($id_account, $this->user);
        $mb['mailbox'] = new bbn\Appui\Mailbox($cfg);
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
          'last_check' => $a['last_check'] ?? null
        ];
        $this->mboxes[$id_account]['folders'] = $this->getFolders($this->mboxes[$id_account]);
      }
    }
    return $this->mboxes[$id_account] ?? null;
  }


  public function checkConfig($cfg): bool
  {
    if (X::hasProps(['login', 'pass', 'type'], true)) {
      $mb = new bbn\Appui\Mailbox($cfg);
      return $mb->check();
    }
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

    $this->createFolderDb($id, $id_parent);
  }


  public function createFolderDb(string $id_account, string $name, string $id_parent = null): bool
  {

  }


  public function renameFolder(string $id, string $name): bool
  {

    $this->renameFolderDb($id, $name);
  }


  public function renameFolderDb(string $id, string $name): bool
  {

  }


  public function deleteFolder(string $id): bool
  {
    $this->deleteFolderDb($id);
  }


  public function deleteFolderDb(string $id): bool
  {

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
      ) {
        if ($folder['last_uid'] !== $res['last_uid']) {
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

      $cfg   = $this->class_cfg['arch']['users_emails'];
      $table = $this->class_cfg['tables']['users_emails'];
      //die(X::dump($this->pref->getFullBits($acc['id'])));
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
            'type' => X::getField($types, ['id' => $a['id_option']], 'code'),
            'db_uid' => $this->db->selectOne(
              $table,
              'MAX('.$this->db->csn($cfg['msg_uid'], true).')',
              [
                $cfg['id_folder'] => $a['id'],
                $cfg['id_user'] => $this->user->getId()
              ]
            ),
            'last_uid' => $a['last_uid'] ?? null,
            'last_check' => $a['last_check'] ?? null
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


  public function getFolder(string $id, bool $force = false): ?array
  {
    $types = self::getFolderTypes();
    $cfg   = $this->class_cfg['arch']['users_emails'];
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
        'db_uid' => $this->db->selectOne(
          $table,
          'MAX('.$this->db->csn($cfg['msg_uid'], true).')',
          [
            $cfg['id_folder'] => $a['id'],
            $cfg['id_user'] => $this->user->getId()
          ]
        ),
        'last_uid' => $a['last_uid'] ?? null,
        'last_check' => $a['last_check'] ?? null
      ];
    }

    return null;
  }


  public function syncEmails(array $folder, int $limit = 0): ?int
  {
    if (X::hasProps($folder, ['id', 'id_account', 'last_uid', 'uid'])) {
      $res = 0;
      if ($folder['last_uid'] && ($folder['last_uid'] !== $folder['db_uid'])) {
        $mb = $this->getMailbox($folder['id_account']);
        if ($mb->selectFolder($folder['uid'])) {
          $start = 1;
          if (!empty($folder['db_uid'])) {
            try {
              $start = $mb->getMsgNo($folder['db_uid']);
            }
            catch (\Exception $e) {
              $start = 1;
            }
          }
          $real_end = 1;
          if (!empty($folder['last_uid'])) {
            try {
              $real_end = $mb->getMsgNo($folder['last_uid']);
            }
            catch (\Exception $e) {
              $real_end = 1;
            }
          }

          /** @todo temporary solution to avoid errors */
          if ($start === $real_end) {
            return 0;
          }

          if ($limit) {
            $real_end = min($real_end, $start + $limit);
          }

          $end      = $start;
          $num      = $real_end - $start;
          //var_dump($folder, $num, $real_end);
          while ($end <= $real_end) {
            $end = min($real_end, $start + 999);
            if ($all = $mb->getEmailsList($folder['uid'], $start, $end)) {
              $start += 1000;
              //var_dump($start, $end);
              foreach ($all as $a) {
                if ($this->insertEmail($folder, $a)) {
                  $res++;
                }
                else {
                  //throw new \Exception(X::_("Impossible to insert the email with ID").' '.$a['message_id']);
                  $this->log(X::_("Impossible to insert the email with ID").' '.$a['message_id']);
                }
              }

              if ($end === $real_end) {
                $this->pref->updateBit($folder['id'], ['last_check' => date('Y-m-d H:i:s')], true);
                break;
              }
            }
            else {
              throw new \Exception(
                X::_("Impossible to get the emails for folder")
                .' '.$folder['uid']
                .' '.X::_("from").' '.$start
                .' '.X::_("to").' '.$end
                .' ('.$real_end.')'
              );
            }
          }
        }
      }
      return $res;
    }

    return null;
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
      $cfg      = $this->class_cfg['arch']['users_emails'];
      $table    = $this->class_cfg['tables']['users_emails'];
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


  public function getEmail($id): ?array
  {
    $cfg      = $this->class_cfg['arch']['users_emails'];
    $table    = $this->class_cfg['tables']['users_emails'];
    $em = $this->db->rselect($table, $cfg, [$cfg['id'] => $id]);
    if ($em) {
      $folder = $this->getFolder($em['id_folder']);
      if ($folder
          && ($mb = $this->getMailbox($folder['id_account']))
          && $mb->selectFolder($folder['uid'])
          && ($number = $mb->getMsgNo($em['msg_uid']))
      ) {
        return $mb->getMsg($number);
      }
    }
    return null;
  }


  public function insertEmail(array $folder, array $email)
  {
    if (X::hasProps($email, ['from', 'uid'])) {
      $cfg      = $this->class_cfg['arch']['users_emails'];
      $table    = $this->class_cfg['tables']['users_emails'];
      $existing = $this->db->selectOne(
        $table,
        $cfg['id'],
        [
          $cfg['id_user'] => $this->user->getId(),
          $cfg['msg_unique_id'] => $email['message_id']
        ]
      );
      foreach (bbn\Appui\MailboX::getDestFields() as $df) {
        if (!empty($email[$df])) {
          foreach ($email[$df] as &$dest) {
            if ($id = $this->retrieveEmail($dest['email'])) {
              $sent_opt = X::getField(self::getFolderTypes(), ['code' => 'sent'], 'id');
              if ($sent_opt === $folder['id_option']) {
                $this->addSentToLink($id, Date('Y-m-d H:i:s', strtotime($email['date'])));
              }
            }
            elseif (!($id = $this->addContactFromMail($dest))) {
              throw new \Exception(X::_("Impossible to add the contact").' '.$dest['email']);
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
        if (!empty($email['in_reply_to'])) {
          $tmp = $this->db->rselect(
            $table,
            [$cfg['id'], $cfg['id_thread']],
            [
              $cfg['id_user'] => $this->user->getId(),
              $cfg['msg_unique_id'] => $email['in_reply_to']
            ]
          );
          if ($tmp) {
            $id_parent = $tmp[$cfg['id']];
            $id_thread = $tmp[$cfg['id_thread']] ?: $id_parent;
          }
        }

        //die(var_dump($email));
        $external = null;
        if (!empty($email['in_reply_to']) || !empty($email['references'])) {
          $external = [
            'in_reply_to' => $email['in_reply_to'] ?? null,
            'references'  => $email['references'] ?? null
          ];
        }

        $ar = [
          $cfg['id_user'] => $this->user->getId(),
          $cfg['id_folder'] => $folder['id'],
          $cfg['msg_uid'] => $email['uid'],
          $cfg['msg_unique_id'] => $email['message_id'],
          $cfg['date'] => date('Y-m-d H:i:s', strtotime($email['date'])),
          $cfg['id_sender'] => $id_sender,
          $cfg['subject'] => empty($email['subject']) ? '' : mb_decode_mimeheader($email['subject']),
          $cfg['size'] => $email['Size'],
          $cfg['attachments'] => empty($email['attachments']) ? null : json_encode($email['attachments']),
          $cfg['flags'] => $email['Flagged'] ?: null,
          $cfg['is_read'] => $email['Unseen'] ? 0 : 1,
          $cfg['id_parent'] => $id_parent,
          $cfg['id_thread'] => $id_thread,
          $cfg['external_uids'] => json_encode($external)
        ];
        $id = false;
        if ($existing) {
          //die(var_dump($ar));
          //$this->db->update($table, $ar, [$cfg['id'] => $existing]);
          $id = $existing;
        }
        elseif ($this->db->insert($table, $ar)) {
          $id = $this->db->lastId();
        }

        if ($id) {
          foreach (bbn\Appui\MailboX::getDestFields() as $df) {
            if (in_array($df, ['to', 'cc', 'bcc']) && !empty($email[$df])) {
              foreach ($email[$df] as $dest) {
                if (!empty($dest['id'])) {
                  $this->addLinkToMail($id, $dest['id'], $df);
                }
              }
            }
          }

          return $id;
        }
      }
    }
    $this->log($email);
    //throw new \Exception(X::_("Invalid email"));
  }


  public function addContactFromMail(array $dest, bool $blacklist = false): ?string
  {
    if (X::hasProp($dest, 'email', true)) {
      $cfg_contacts   = $this->class_cfg['arch']['users_contacts'];
      $cfg_links      = $this->class_cfg['arch']['users_contacts_links'];
      $table_contacts = $this->class_cfg['tables']['users_contacts'];
      $table_links = $this->class_cfg['tables']['users_contacts_links'];
      if ($this->db->insert($table_contacts, [
        $cfg_contacts['id_user']   => $this->user->getId(),
        $cfg_contacts['name']      => $dest['name'] ?? null,
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
    $cfg   = $this->class_cfg['arch']['users_contacts_links'];
    $table = $this->class_cfg['tables']['users_contacts_links'];
    $data  = $this->db->rselect($table, $cfg, [$cfg['id'] => $id]);
    return $data ?: null;
  }


  public function addLinkToMail(string $id_email, string $id_link, string $type): bool
  {
    $cfg   = $this->class_cfg['arch']['users_emails_recipients'];
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
      $cfg   = $this->class_cfg['arch']['users_contacts_links'];
      $table = $this->class_cfg['tables']['users_contacts_links'];
      if (!$date) {
        $date = date('Y-m-d H:i:s');
      }
      if ($link['last_sent'] && ($link['last_sent'] > $date))  {
        $date = $link['last_sent'];
      }

      return (bool)$this->db->update(
        $table,
        [
          $cfg['num_sent']  => $link[$cfg['num_sent']] + 1,
          $cfg['last_sent'] => $date
        ], [
          'id' => $id_link
        ]
      );
    }

    return false;
  }


  public function retrieveEmail(string $email)
  {
    $contacts = $this->class_cfg['tables']['users_contacts'];
    $cfg_c    = $this->class_cfg['arch']['users_contacts'];
    $links    = $this->class_cfg['tables']['users_contacts_links'];
    $cfg_l    = $this->class_cfg['arch']['users_contacts_links'];
    return $this->db->selectOne(
      [
        'tables' => [$links],
        'field'  => $this->db->cfn($cfg_l['id'], $links),
        'join'   => [
          [
            'table' => $contacts,
            'on'    => [
              [
                'field' => $cfg_l['id_contact'],
                'exp'   => $this->db->cfn($cfg_c['id'], $contacts)
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


  public function getContact(string $email, string $name, $force)
  {

  }


  public function getContacts(): array
  {
    $contacts = $this->class_cfg['tables']['users_contacts'];
    $cfg_c    = $this->class_cfg['arch']['users_contacts'];
    $links    = $this->class_cfg['tables']['users_contacts_links'];
    $cfg_l    = $this->class_cfg['arch']['users_contacts_links'];
    $rows = $this->db->rselectAll(
      [
        'tables' => [$links],
        'fields'  => [
          $this->db->cfn($cfg_l['id'], $links),
          $this->db->cfn($cfg_l['value'], $links),
          $this->db->cfn($cfg_l['id_contact'], $links),
          $this->db->cfn($cfg_l['num_sent'], $links),
          $this->db->cfn($cfg_l['last_sent'], $links),
          $this->db->cfn($cfg_c['name'], $contacts),
          $this->db->cfn($cfg_c['cfg'], $contacts),
          $this->db->cfn($cfg_c['blacklist'], $contacts),
          'sortIndex' => 'IFNULL('.$this->db->cfn($cfg_c['name'], $contacts, true).','.$this->db->cfn($cfg_l['value'], $links).')'
        ],
        'join'   => [
          [
            'table' => $contacts,
            'on'    => [
              [
                'field' => $cfg_l['id_contact'],
                'exp'   => $this->db->cfn($cfg_c['id'], $contacts)
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
          'text' => (empty($r['name']) ? '' : $r['name'].' - ').$r['value'],
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
    if ($mb = $this->getMailbox($id_account)) {
      $mbParam = $mb->getParams();
      $types   = self::getFolderTypes();

      $put_in_res = function (array $a, &$res, $prefix = '') use (&$put_in_res, $subscribed) {
        $ele = array_shift($a);
        $idx = X::find($res, ['text' => $ele]);
        if (null === $idx) {
          $idx   = count($res);
          $res[] = [
            'text' => $ele,
            'uid' => $prefix.$ele,
            'items' => [],
            'subscribed' => in_array($prefix.$ele, $subscribed)
          ];
        }

        if (count($a)) {
          $put_in_res($a, $res[$idx]['items'], $prefix.$ele.'.');
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
          }
          elseif ($r['items'] && $db[$idx]['items']) {
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
          }
          else {
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


  public function getStructure($id_account, $force)
  {

  }


  protected function idsFromFolder($id_folder): ?array
  {
    $cfg      = $this->class_cfg['arch']['users_emails'];
    $table    = $this->class_cfg['tables']['users_emails'];
    $types    = self::getFolderTypes();
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
    }
    elseif (Str::isUid($id_folder)) {
      $bit = $this->pref->getBit($id_folder);
      if (!$bit) {
        // It's not a folder but an account
        if ($pref = $this->pref->get($id_folder)) {
          // we look for inbox
        }
      }
      else {
        $ids = [$id_folder];
      }
    }
    else if ($id_folder === 'conversations') {
      $inbox = X::getRow($types, ['code' => 'inbox']);
      $sent = X::getRow($types, ['code' => 'sent']);
      $ids = [];
      $accounts = $em->getAccounts();
      foreach ($accounts as $a) {
        foreach ($em->getFolders($a['id']) as $f) {
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


  private function _get_password(): bbn\Appui\Passwords
  {
    if (!$this->pw) {
      $this->pw = new bbn\Appui\Passwords($this->db);
    }

    return $this->pw;
  }


}
