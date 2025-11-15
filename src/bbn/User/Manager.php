<?php
/**
 * @package user
 */
namespace bbn\User;

use bbn\X;
use bbn\Str;
use bbn\Mvc;
use bbn\Db;
use bbn\Mail;
use bbn\User;
use bbn\User\Preferences;
use stdClass;
use Exception;

/**
 * A class for managing users
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Authentication
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.2r89
 */
class Manager
{

  protected static $admin_group;

  protected static $dev_group;

  protected $messages = [
    'creation' => [
      'subject' => "Account created",
      'link' => "",
      'text' => "
A new user has been created for you.<br>
Please click the link below in order to activate your account:<br>
%1\$s"
    ],
    'password' => [
      'subject' => "Password change",
      'link' => "",
      'text' => "
You can click the following link to change your password:<br>
%1\$s"
    ],
    'hotlink' => [
      'subject' => "Hotlink",
      'link' => "",
      'text' => "
You can click the following link to access directly your account:<br>
%1\$s"
    ]
  ];

  // 1 day
  protected $hotlink_length = 86400;

  protected $list_fields;

  /** @var User User object */
  protected $usrcls;

  /** @var Mail */
  protected $mailer;

  /** @var Db */
  protected $db;

  protected $class_cfg;


  public function getListFields()
  {
    return $this->list_fields;
  }


  public function getMailer(): Mail
  {
    if (!$this->mailer) {
      $this->mailer = $this->usrcls->getMailer();
    }

    return $this->mailer;
  }


  public function findSessions(?string $id_user=null, int $minutes = 5): array
  {
    $cfg = [
      'table' => $this->class_cfg['tables']['sessions'],
      'fields' => [],
      'where' => [
        [
          'field' => $this->class_cfg['arch']['sessions']['last_activity'],
          'operator' => '<',
          'value' => 'DATE_SUB(NOW(), INTERVAL '.$this->db->csn($this->class_cfg['sess_length'], true).' MINUTE)'
        ]
      ]
    ];
    if (!\is_null($id_user)) {
      $cfg['where'][] = [
        'field' => $this->class_cfg['arch']['sessions']['id_user'],
        'value' => $id_user
      ];
    }

    return $this->db->rselectAll($cfg);
  }


  public function destroySessions(string $id_user, int $minutes = 5): bool
  {
    $sessions = $this->findSessions($id_user, $minutes);
    //$num = count($sessions);
    foreach ($sessions as $s){
      $this->db->delete($this->class_cfg['tables']['sessions'], [$this->class_cfg['arch']['sessions']['id'] => $s['id']]);
    }

    return true;
  }


  /**
   * @param User $obj A user's connection object (\connection or subclass)
   *
   */
  public function __construct(User $obj)
  {
    if (\is_object($obj) && method_exists($obj, 'getClassCfg')) {
      $this->usrcls    = $obj;
      $this->class_cfg = $this->usrcls->getClassCfg();
      if (!$this->list_fields) {
        $this->setDefaultListFields();
      }

      $this->db = $this->usrcls->getDbInstance();
    }
  }


  public function getClassCfg(): array
  {
    return $this->class_cfg;
  }


  public function isOnline(string $id_user, int $delay = 180): bool
  {
    $a =& $this->class_cfg['arch'];
    $t =& $this->class_cfg['tables'];
    if (($max = $this->db->selectOne($t['sessions'], 'MAX('.$a['sessions']['last_activity'].')', ['id_user' => $id_user]))
        && (strtotime($max) > (time() - $delay))
    ) {
      return true;
    }

    return false;
  }


  /**
   * Returns all the users' groups - with or without admin
   * @param bool $adm
   * @return array|false
   */
  public function groups(): array
  {
    $cfg = $this->getClassCfg();
    $a             =& $cfg['arch'];
    $t             =& $cfg['tables'];
    $id            = $this->db->cfn($a['groups']['id'], $t['groups']);
    $users_id      = $this->db->cfn($a['users']['id'], $t['users'], 1);
    $db            =& $this->db;
    $fields        = \array_map(
      function ($g) use ($db, $t) {
        return $db->cfn($g, $t['groups']);
      }, \array_values($a['groups'])
    );
    $fields['num'] = "COUNT($users_id)";
    return $this->db->rselectAll(
      [
      'table' => $t['groups'],
      'fields' => $fields,
      'join' => [[
        'table' => $t['users'],
        'type' => 'left',
        'on' => [
          'conditions' => [[
            'field' => $this->db->cfn($a['users']['id_group'], $t['users']),
            'exp' => $id
          ]]
        ]
      ]],
      'group_by' => [$id]
      ]
    );
  }


  public function textValueGroups()
  {
    return $this->db->rselectAll(
      $this->class_cfg['tables']['groups'], [
        'value' => $this->class_cfg['arch']['groups']['id'],
        'text' => $this->class_cfg['arch']['groups']['group'],
      ]
    );
  }


  public function getEmail(string $id): ?string
  {
    if (Str::isUid($id)) {
      $email = $this->db->selectOne($this->class_cfg['tables']['users'], $this->class_cfg['arch']['users']['email'], [$this->class_cfg['arch']['users']['id'] => $id]);
      if ($email && Str::isEmail($email)) {
        return $email;
      }
    }

    return null;
  }


  public function getList(string|null $group_id = null): array
  {
    $db     =& $this->db;
    $arch   =& $this->class_cfg['arch'];
    $s      =& $arch['sessions'];
    $tables =& $this->class_cfg['tables'];

    if (!empty($arch['users']['username'])) {
      $sort = $arch['users']['username'];
    }
    elseif (!empty($arch['users']['login'])) {
      $sort = $arch['users']['login'];
    }
    else{
      $sort = $arch['users']['email'];
    }

    $fields = array_map(function($a) use($db) {
      return $db->cfn($a, $this->class_cfg['tables']['users']);
    }, array_values($arch['users']));
    $fields[$s['last_activity']] = "MAX($s[last_activity])";
    $fields[$s['sess_id']] = "COUNT($s[sess_id])";
    return $this->db->rselectAll([
      'tables' => [$tables['users']],
      'fields' => $fields,
      'join' => [[
        'table' => $tables['sessions'],
        'type' => 'left',
        'on' => [
          'conditions' => [[
            'field' => $db->cfn($s['id_user'], $tables['sessions']),
            'exp' => $db->cfn($arch['users']['id'], $tables['users'])
          ]]
        ]
      ], [
        'table' => $tables['groups'],
        'on' => [
          'conditions' => [[
            'field' => $db->cfn($arch['users']['id_group'], $tables['users']),
            'exp' => $db->cfn($arch['groups']['id'], $tables['groups'])
          ]]
        ]
      ]],
      'where' => [$arch['users']['active'] => 1],
      'group_by' => [$db->cfn($arch['users']['id'], $tables['users'])],
      'order' => [[
        'field' => $db->cfn($sort, $tables['users']),
        'dir' => 'ASC'
      ]]
    ]);
  }


  public function getUser(string $id): ?array
  {
    $u = $this->class_cfg['arch']['users'];
    if (Str::isUid($id)) {
      $where = [$u['id'] => $id];
    }
    else{
      $where = [$u['login'] => $id];
    }

    if ($user = $this->db->rselect(
      $this->class_cfg['tables']['users'],
      $u,
      $where
    )
    ) {
      if ($session = $this->db->rselect(
        $this->class_cfg['tables']['sessions'],
        $this->class_cfg['arch']['sessions'],
        [$this->class_cfg['arch']['sessions']['id_user'] => $user[$u['id']]],
        [$this->class_cfg['arch']['sessions']['last_activity'] => 'DESC']
      )
      ) {
        $session['id_session'] = $session['id'];
      }
      else{
        $session               = array_fill_keys(
          array_values($this->class_cfg['arch']['sessions']),
          ''
        );
        $session['id_session'] = false;
      }

      return array_merge($session, $user);
    }

    return null;
  }


  public function getGroup(string $id): ?array
  {
    $g = $this->class_cfg['arch']['groups'];
    if ($group = $this->db->rselect(
      $this->class_cfg['tables']['groups'],
      $this->class_cfg['arch']['groups'],
      [$g['id'] => $id]
    )) {
      $group[$g['cfg']] = $group[$g['cfg']] ? json_decode($group[$g['cfg']], 1) : [];
      return $group;
    }

    return null;
  }


  public function getGroupByCode(string $code): ?array
  {
    $g = $this->class_cfg['arch']['groups'];
    if ($group = $this->db->rselect(
      $this->class_cfg['tables']['groups'],
      $this->class_cfg['arch']['groups'],
      [$g['code'] => $code]
    )) {
      $group[$g['cfg']] = $group[$g['cfg']] ? json_decode($group[$g['cfg']], 1) : [];
      return $group;
    }

    return null;
  }


  public function getUsers($group_id = null): array
  {
    return $this->db->getColumnValues(
      $this->class_cfg['tables']['users'],
      $this->class_cfg['arch']['users']['id'],
      [
        $this->class_cfg['arch']['users']['active'] => 1,
        $this->class_cfg['arch']['users']['id_group'] => $group_id
      ]
    );
  }


  public function fullList(): array
  {
    $r = [];
    $all = $this->db->rselectAll(
      $this->class_cfg['tables']['users'],
      $this->class_cfg['arch']['users']
    );
    foreach ($all as $a){
      $r[] = [
        'value' => $a['id'],
        'text' => $this->getName($a, false),
        'id_group' => $a['id_group'],
        'active' => $a['active'] ? true : false
      ];
    }

    return $r;
  }


  public function getUserId(string $login): ?string
  {
    return $this->db->selectOne(
      $this->class_cfg['tables']['users'],
      $this->class_cfg['arch']['users']['id'],
      [
        $this->class_cfg['arch']['users']['login'] => $login
      ]
    );
  }


  public function getAdminGroup(): ?string
  {
    if (!self::$admin_group) {
      if ($res = $this->db->selectOne(
        $this->class_cfg['tables']['groups'],
        $this->class_cfg['arch']['groups']['id'],
        [
          $this->class_cfg['arch']['groups']['code'] => 'admin'
        ]
      )
      ) {
        self::setAdminGroup($res);
      }
    }

    return self::$admin_group;
  }


  public function getDevGroup(): ?string
  {
    if (!self::$dev_group) {
      if ($res = $this->db->selectOne(
        $this->class_cfg['tables']['groups'],
        $this->class_cfg['arch']['groups']['id'],
        [
          $this->class_cfg['arch']['groups']['code'] => 'dev'
        ]
      )
      ) {
        self::setDevGroup($res);
      }
    }

    return self::$dev_group;
  }


  public function getName($user, $full = true)
  {
    if (!\is_array($user)) {
      $user = $this->getUser($user);
    }

    if (\is_array($user)) {
      $idx = 'email';
      if (!empty($this->class_cfg['arch']['users']['username'])) {
        $idx = 'username';
      }
      elseif (!empty($this->class_cfg['arch']['users']['login'])) {
        $idx = 'login';
      }

      return $user[$idx];
    }

    return '';
  }


  public function getGroupType(string $id_group): ?string
  {
    $g =& $this->class_cfg['arch']['groups'];
    return $this->db->selectOne($this->class_cfg['tables']['groups'], $g['type'], [$g['id'] => $id_group]);
  }


  /**
   * Creates a new user and returns its configuration (with the new ID)
   *
   * @param array $cfg A configuration array
     * @return array|false
     */
  public function add(array $cfg): ?array
  {
    $u                 =& $this->class_cfg['arch']['users'];
    $fields            = array_unique(array_values($u));
    $cfg[$u['active']] = 1;
    $cfg[$u['cfg']]    = new stdClass();
    foreach ($cfg as $k => $v){
      if (!\in_array($k, $fields)) {
        $cfg[$u['cfg']]->$k = $v;
        unset($cfg[$k]);
      }
    }
    $cfg[$u['cfg']] = json_encode($cfg[$u['cfg']]);

    if (isset($cfg['id'])) {
      unset($cfg['id']);
    }

    if (!empty($cfg[$u['id_group']])) {
      $group = $this->getGroupType($cfg[$u['id_group']]);
      switch ($group) {
        case 'real':
          if (Str::isEmail($cfg[$u['email']])
              && $this->db->insert($this->class_cfg['tables']['users'], $cfg)
          ) {
            $cfg[$u['id']] = $this->db->lastId();
            // Envoi d'un lien
            if (!empty($this->class_cfg['arch']['hotlinks'])) {
              $this->makeHotlink($cfg[$this->class_cfg['arch']['users']['id']], 'creation');
            }

            return $cfg;
          }
          break;
        case 'api':
          $cfg[$u['email']] = null;
          $cfg[$u['login']] = null;
          if ($this->db->insert($this->class_cfg['tables']['users'], $cfg)) {
            $cfg[$u['id']] = $this->db->lastId();
            return $cfg;
          }
          break;
      }
    }

      return null;
  }


    /**
   * Creates a new user and returns its configuration (with the new ID)
   *
   * @param array $cfg A configuration array
     * @return array|false
     */
  public function edit(array $cfg, string|null $id_user = null): ?array
  {
    $u                 =& $this->class_cfg['arch']['users'];
    $fields            = array_unique(array_values($this->class_cfg['arch']['users']));
    $cfg[$u['active']] = 1;
    if (!empty($this->class_cfg['arch']['users']['cfg'])) {
      if (empty($cfg[$this->class_cfg['arch']['users']['cfg']])) {
        $cfg[$this->class_cfg['arch']['users']['cfg']] = [];
      }
      elseif (is_string($cfg[$this->class_cfg['arch']['users']['cfg']])) {
        $cfg[$this->class_cfg['arch']['users']['cfg']] = json_decode($cfg[$this->class_cfg['arch']['users']['cfg']], true);
      }

      foreach ($cfg as $k => $v){
        if (!\in_array($k, $fields)) {
          $cfg[$this->class_cfg['arch']['users']['cfg']][$k] = $v;
          unset($cfg[$k]);
        }
      }

      $cfg[$this->class_cfg['arch']['users']['cfg']] = json_encode($cfg[$this->class_cfg['arch']['users']['cfg']]);
    }
    else {
      foreach ($cfg as $k => $v){
        if (!\in_array($k, $fields)) {
          unset($cfg[$k]);
        }
      }
    }


    if (!$id_user && isset($cfg[$u['id']])) {
      $id_user = $cfg[$u['id']];
    }

    if ($id_user && (        !isset($cfg[$this->class_cfg['arch']['users']['email']])
        || Str::isEmail($cfg[$this->class_cfg['arch']['users']['email']])        )
    ) {
      if ($this->db->update(
        $this->class_cfg['tables']['users'], $cfg, [
        $u['id'] => $id_user
        ]
      )
      ) {
        $cfg['id'] = $id_user;
        return $cfg;
      }
    }

      return null;
  }


  public function copy(string $type, string $id, array $data): ?string
  {
    $pref = Preferences::getPreferences();
    $cfg  = $pref->getClassCfg();
    switch ($type) {
      case 'user':
        if ($src = $this->getUser($id)) {
          $data = X::mergeArrays($src, $data);
          unset($data[$cfg['arch']['users']['id']]);
          $col    = $cfg['arch']['user_options']['id_user'];
          $id_new = $this->add($data);
        }
        break;
      case 'group':
        if ($src = $this->getGroup($id)) {
          $data = X::mergeArrays($src, $data);
          unset($data[$cfg['arch']['groups']['id']]);
          $col    = $cfg['arch']['user_options']['id_group'];
          $id_new = $this->groupInsert($data);
        }
        break;
    }

    if (!empty($id_new)) {
      if ($options = $this->getOptions($type, $id)) {
        $ids = [];
        foreach ($options as $o) {
          $old_id = $o['id'];
          unset($o['id']);
          $o[$col] = $id_new;
          if ($this->db->insertIgnore($cfg['table'], $o)) {
            $ids[$old_id] = $this->db->lastId();
          }
        }

        $bids = [];
        foreach ($ids as $oid => $nid) {
          $bits = $this->db->rselectAll(
            $cfg['tables']['user_options_bits'], [], [
            $cfg['arch']['user_options_bits']['id_user_option'] => $oid,
            $cfg['arch']['user_options_bits']['id_parent'] => null
            ]
          );
          foreach ($bits as $bit) {
            $old_id = $bit[$cfg['arch']['user_options_bits']['id']];
            unset($bit[$cfg['arch']['user_options_bits']['id']]);
            $bit[$cfg['arch']['user_options_bits']['id_user_option']] = $nid;
            $this->db->insert($cfg['tables']['user_options_bits'], $bit);
            $bids[$old_id] = $this->db->lastId();
          }
        }

        $remaining = -1;
        $before    = 0;
        $done      = [];
        while ($remaining && ($before !== $remaining)) {
          if ($remaining === -1) {
            $before = 0;
          }
          else {
            $before = $remaining;
          }

          $remaining = 0;
          foreach ($ids as $oid => $nid) {
            if (in_array($nid, $done)) {
              continue;
            }

            $bits = $this->db->rselectAll(
              $cfg['tables']['user_options_bits'], [], [
              $cfg['arch']['user_options_bits']['id_user_option'] => $oid,
              [$cfg['arch']['user_options_bits']['id_parent'], 'isnotnull']
              ]
            );
            if (!count($bits)) {
              $done[] = $nid;
              continue;
            }

            foreach ($bits as $bit) {
              $old_id = $bit[$cfg['arch']['user_options_bits']['id']];
              if (isset($bids[$old_id])) {
                continue;
              }

              if (!isset($bids[$bit[$cfg['arch']['user_options_bits']['id_parent']]])) {
                $remaining++;
              }
              else {
                unset($bit[$cfg['arch']['user_options_bits']['id']]);
                $bit[$cfg['arch']['user_options_bits']['id_user_option']] = $nid;
                $bit[$cfg['arch']['user_options_bits']['id_parent']]      = $bids[$bit[$cfg['arch']['user_options_bits']['id_parent']]];
                $this->db->insert($cfg['tables']['user_options_bits'], $bit);
                $bids[$old_id] = $this->db->lastId();
              }
            }
          }
        }
      }

      return $id_new;
    }

    return null;
  }


  public function sendMail(string $id_user, string $subject, string $text, array $attachments = []): ?int
  {
    if (($usr = $this->getUser($id_user)) && $usr['email']) {
      if (!($mailer = $this->getMailer())) {
        return mail($usr['email'], $subject, $text);
        //throw new Exception(X::_("Impossible to make hotlinks without a proper mailer parameter"));
      }

      return $mailer->send(
        [
        'to' => $usr['email'],
        'subject' => $subject,
        'text' => $text,
        'attachments' => $attachments
        ]
      );
    }

    return null;
  }

  public function setPassword($id, $pass)
  {
    return (bool)$this->db->insert(
      $this->class_cfg['tables']['passwords'], [
      $this->class_cfg['arch']['passwords']['pass'] => $this->_hash($pass),
      $this->class_cfg['arch']['passwords']['id_user'] => $id,
      $this->class_cfg['arch']['passwords']['added'] => date('Y-m-d H:i:s')
      ]
    );
  }


  public function expireHotlinks($id_user): int
  {
    $hl =& $this->class_cfg['arch']['hotlinks'];
    // Expire existing valid hotlinks
    return $hl ? $this->db->update(
      $this->class_cfg['tables']['hotlinks'], [
        $hl['expire'] => date('Y-m-d H:i:s')
      ], [
        [$hl['id_user'], '=', $id_user],
        [$hl['expire'], '>', date('Y-m-d H:i:s')]
      ]
    ) : 0;
  }

  public function createHotlink($id_user, int $exp = 0): ?string
  {
    $hl =& $this->class_cfg['arch']['hotlinks'];
    if ($hl && ($usr = $this->getUser($id_user))) {
      // Expiration date
      if (!\is_int($exp) || ($exp < 1)) {
        $exp = time() + $this->hotlink_length;
      }

      $magic = $this->usrcls->makeMagicString();
      // Create hotlink
      $this->db->insert(
        $this->class_cfg['tables']['hotlinks'], [
          $hl['magic'] => $magic['hash'],
          $hl['id_user'] => $id_user,
          $hl['expire'] => date('Y-m-d H:i:s', $exp)
        ]
      );
      $id_link = $this->db->lastId();
      $url = constant('BBN_URL');
      if (Str::sub($url, -1) === '/') {
        $url = Str::sub($url, 0, -1);
      }

      $url .= constant("BBN_CUR_PATH");

      if (!empty($usr['id_group'])
        && ($group = $this->getGroup($usr['id_group']))
        && !empty($group['home'])
      ) {
        $url .= $group['home'];
      }

      return "?id=$id_link&key=$magic[key]";
    }

    return null;
  }

  /**
   *
   * @param string $id_user User ID
   * @param string $message Type of the message
   * @param int    $exp     Timestamp of the expiration date
   * @return manager
   */
  public function makeHotlink(string $id_user, string $message = 'hotlink', $exp = null, string|null $url = null): self
  {
    if (!isset($this->messages[$message]) || empty($this->messages[$message]['link'])) {
      switch ($message)
      {
        case 'hotlink':
          if ($path = Mvc::getPluginUrl('appui-usergroup')) {
            $this->messages[$message]['link'] = ($url ?: BBN_URL).$path.'/main/profile';
          }
          break;
        case 'creation':
          if ($path = Mvc::getPluginUrl('appui-core')) {
            $this->messages[$message]['link'] = ($url ?: BBN_URL).$path.'/login/%s';
          }
          break;
        case 'password':
          if ($path = Mvc::getPluginUrl('appui-core')) {
            $this->messages[$message]['link'] = ($url ?: BBN_URL).$path.'/login/%s';
          }
          break;
      }

      if (empty($this->messages[$message]['link'])) {
        throw new Exception(X::_("Impossible to make hotlinks without a link configured"));
      }
    }

    if ($this->getUser($id_user)) {
      $this->expireHotlinks($id_user);
      $link = $this->createHotlink($id_user);
      $this->sendMail(
        $id_user,
        $this->messages[$message]['subject'],
        sprintf($this->messages[$message]['text'], sprintf($this->messages[$message]['link'], $link))
      );
    }
    else{
      X::log("User $id_user not found");
      throw new Exception(X::_('User not found'));
    }

    return $this;
  }


  /**
   *
   * @param int $id_user  User ID
   * @param int $id_group Group ID
   * @return manager
   */
  public function setUniqueGroup(string $id_user, string $id_group): bool
  {
    return (bool)$this->db->update(
      $this->class_cfg['tables']['users'], [
      $this->class_cfg['arch']['users']['id_group'] => $id_group
      ], [
      $this->class_cfg['arch']['users']['id'] => $id_user
      ]
    );
  }


  public function userHasOption(string $id_user, string $id_option, bool $with_group = true): bool
  {
    if ($with_group && $user = $this->getUser($id_user)) {
      $id_group = $user[$this->class_cfg['arch']['users']['id_group']];
      if ($this->groupHasOption($id_group, $id_option)) {
        return true;
      }
    }

    if ($pref = Preferences::getPreferences()) {
      if ($cfg = $pref->getClassCfg()) {
        return $this->db->count(
          $cfg['table'], [
          $cfg['arch']['user_options']['id_option'] => $id_option,
          $cfg['arch']['user_options']['id_user'] => $id_user
          ]
        ) ? true : false;
      }
    }

    return false;
  }


  public function groupHasOption(string $id_group, string $id_option): bool
  {
    if (($pref = Preferences::getPreferences())
        && ($cfg = $pref->getClassCfg())
    ) {
      return $this->db->count(
        $cfg['table'], [
        $cfg['arch']['user_options']['id_option'] => $id_option,
        $cfg['arch']['user_options']['id_group'] => $id_group
        ]
      ) ? true : false;
    }

    return false;
  }


  public function getOptions(string $type, string $id): ?array
  {
    if (($pref = Preferences::getPreferences())
        && ($cfg = $pref->getClassCfg())
    ) {
      if (stripos($type,  'group') === 0) {
        return $this->db->rselectAll(
          $cfg['table'], [], [
          $cfg['arch']['user_options']['id_group'] => $id
          ]
        );
      }
      elseif (stripos($type, 'user') === 0) {
        return $this->db->rselectAll(
          $cfg['table'], [], [
          $cfg['arch']['user_options']['id_user'] => $id
          ]
        );
      }
    }

    return null;
  }


  public function userInsertOption(string $id_user, string $id_option): bool
  {
    if (($pref = Preferences::getPreferences())
        && ($cfg = $pref->getClassCfg())
    ) {
      return (bool)$this->db->insertIgnore(
        $cfg['table'], [
        $cfg['arch']['user_options']['id_option'] => $id_option,
        $cfg['arch']['user_options']['id_user'] => $id_user
        ]
      );
    }

    return false;
  }


  public function groupInsertOption(string $id_group, string $id_option): bool
  {
    if (($pref = Preferences::getPreferences())
        && ($cfg = $pref->getClassCfg())
    ) {
      return (bool)$this->db->insertIgnore(
        $cfg['table'], [
        $cfg['arch']['user_options']['id_option'] => $id_option,
        $cfg['arch']['user_options']['id_group'] => $id_group
        ]
      );
    }

    return false;
  }


  public function userDeleteOption(string $id_user, string $id_option): bool
  {
    if (($pref = Preferences::getPreferences())
        && ($cfg = $pref->getClassCfg())
    ) {
      return (bool)$this->db->deleteIgnore(
        $cfg['table'], [
        $cfg['arch']['user_options']['id_option'] => $id_option,
        $cfg['arch']['user_options']['id_user'] => $id_user
        ]
      );
    }

    return false;
  }


  public function groupDeleteOption(string $id_group, string $id_option): bool
  {
    if (($pref = Preferences::getPreferences())
        && ($cfg = $pref->getClassCfg())
    ) {
      return (bool)$this->db->deleteIgnore(
        $cfg['table'], [
        $cfg['arch']['user_options']['id_option'] => $id_option,
        $cfg['arch']['user_options']['id_group'] => $id_group
        ]
      );
    }

    return false;
  }


  public function groupNumUsers(string $id_group): int
  {
    $u =& $this->class_cfg['arch']['users'];
    return $this->db->count(
      $this->class_cfg['tables']['users'], [
      $u['id_group'] => $id_group
      ]
    );
  }


  public function groupInsert(array $data): ?string
  {
    $g = $this->class_cfg['arch']['groups'];
    if (isset($data[$g['group']])) {
      if (!empty($data[$g['cfg']]) && is_array($data[$g['cfg']])) {
        $data[$g['cfg']] = json_encode($data[$g['cfg']]);
      }

      if ($this->db->insert(
        $this->class_cfg['tables']['groups'],
        [
          $g['group'] => $data[$g['group']],
          $g['code'] => $data[$g['code']] ?? null,
          $g['cfg'] => !empty($g['cfg']) && !empty($data[$g['cfg']]) ? $data[$g['cfg']] : '{}'
        ]
      )
      ) {
        return $this->db->lastId();
      }
    }

    return null;
  }


  public function groupEdit(string $id, array $data): bool
  {
    $g = $this->class_cfg['arch']['groups'];
    if (!empty($data[$g['group']])) {
      if (!empty($data[$g['cfg']]) && is_array($data[$g['cfg']])) {
        $data[$g['cfg']] = json_encode($data[$g['cfg']]);
      }

      return (bool)$this->db->update(
        $this->class_cfg['tables']['groups'],
        [
          $g['group'] => $data[$g['group']],
          $g['code'] => $data[$g['code']] ?? null,
          $g['cfg'] => !empty($g['cfg']) && !empty($data[$g['cfg']]) ? $data[$g['cfg']] : '{}'
        ],
        [$g['id'] => $id]
      );
    }

    return false;
  }


  public function groupRename(string $id, string $name): bool
  {
    $g = $this->class_cfg['arch']['groups'];
    return (bool)$this->db->update(
      $this->class_cfg['tables']['groups'], [
      $g['group'] => $name
      ], [
      $g['id'] => $id
      ]
    );
  }


  public function groupSetCfg(string $id, array $cfg): bool
  {
    $g = $this->class_cfg['arch']['groups'];
    return (bool)$this->db->update(
      $this->class_cfg['tables']['groups'], [
      $g['cfg'] => $cfg ?: '{}'
      ], [
      $g['id'] => $id
      ]
    );
  }


  public function groupDelete(string $id): bool
  {
    $g = $this->class_cfg['arch']['groups'];
    if ($this->groupNumUsers($id)) {
      /** @todo Error management */
      throw new Exception(X::_("Impossible to delete this group as it has users"));
    }

    return (bool)$this->db->delete(
      $this->class_cfg['tables']['groups'], [
      $g['id'] => $id
      ]
    );
  }


  /**
   * @param int $id_user User ID
   *
   * @return int|false Update result
     */
  public function deactivate(string $id_user): bool
  {
    $update = [
    $this->class_cfg['arch']['users']['active'] => 0,
    $this->class_cfg['arch']['users']['email'] => null,
    ];
    if (!empty($this->class_cfg['arch']['users']['login'])) {
      $update[$this->class_cfg['arch']['users']['login']] = null;
    }

    if ($this->db->update(
      $this->class_cfg['tables']['users'], $update, [
      $this->class_cfg['arch']['users']['id'] => $id_user
      ]
    )
    ) {
      // Deleting existing sessions
      $this->db->delete($this->class_cfg['tables']['sessions'], [$this->class_cfg['arch']['sessions']['id_user'] => $id_user]);
      return true;
    }

    return false;
  }


  /**
   * @param int $id_user User ID
   *
   * @return manager
   */
  public function reactivate(string $id_user): bool
  {
    return (bool)$this->db->update(
      $this->class_cfg['tables']['users'], [
      $this->class_cfg['arch']['users']['active'] => 1
      ], [
      $this->class_cfg['arch']['users']['id'] => $id_user
      ]
    );
  }


  public function addPermission(string $id_perm, string|null $id_user = null, string|null $id_group = null, int $public = 0): bool
  {
    if (!$id_group && !$id_user && !$public) {
      throw new Exception("No paraneters!");
    }

    if (!($pref = Preferences::getInstance())) {
      throw new Exception("No User\Preferences instance!");
    }

    if (!($prefCfg = $pref->getClassCfg())) {
      throw new Exception("No User\Preferences cfg!");
    }


    return (bool)$this->db->insertIgnore(
      $prefCfg['tables']['user_options'],
      [
        $prefCfg['arch']['user_options']['id_option'] => $id_perm,
        $prefCfg['arch']['user_options']['id_user'] => $id_user,
        $prefCfg['arch']['user_options']['id_group'] => $id_group,
        $prefCfg['arch']['user_options']['public'] => $public
      ]
    );
  }


  public function removePermission(string $id_perm, string|null $id_user = null, string|null $id_group = null, int $public = 0): bool
  {
    if (!$id_group && !$id_user && !$public) {
      throw new Exception("No paraneters!");
    }

    if (!($pref = Preferences::getInstance())) {
      throw new Exception("No User\Preferences instance!");
    }

    if (!($prefCfg = $pref->getClassCfg())) {
      throw new Exception("No User\Preferences cfg!");
    }

    return (bool)$this->db->deleteIgnore(
      $prefCfg['tables']['user_options'],
      [
        $prefCfg['arch']['user_options']['id_option'] => $id_perm,
        $prefCfg['arch']['user_options']['id_user'] => $id_user,
        $prefCfg['arch']['user_options']['id_group'] => $id_group,
        $prefCfg['arch']['user_options']['public'] => $public
      ]
    );
  }


  public function createPermission(string $path)
  {

    return false;
  }


  public function deletePermission(string $id_perm): bool
  {

    return false;
  }


  protected function setDefaultListFields()
  {
    $fields = $this->class_cfg['arch']['users'];
    unset($fields['id'], $fields['active'], $fields['cfg']);
    $this->list_fields = [];
    foreach ($fields as $n => $f){
      if (!\in_array($f, $this->list_fields)) {
        $this->list_fields[$n] = $f;
      }
    }
  }


  protected static function setAdminGroup($id)
  {
    self::$admin_group = $id;
  }


  protected static function setDevGroup($id)
  {
    self::$dev_group = $id;
  }


  /**
  * Use the configured hash function to encrypt a password string.
  *
  * @param string $st The string to crypt
  * @return string
  */
  private function _hash(string $st): string
  {
    if (!function_exists($this->class_cfg['encryption'])) {
      $this->class_cfg['encryption'] = 'sha256';
    }

    return $this->class_cfg['encryption']($st);
  }


}
