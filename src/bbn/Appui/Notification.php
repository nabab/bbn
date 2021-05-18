<?php

namespace bbn\Appui;

use bbn;

class Notification extends bbn\Models\Cls\Db
{
  use
    bbn\Models\Tts\Optional,
    bbn\Models\Tts\Dbconfig;

  protected static /** @var array */
    $default_class_cfg = [
      'table' => 'bbn_notifications',
      'tables' => [
        'notifications' => 'bbn_notifications',
        'content' => 'bbn_notifications_content'
      ],
      'arch' => [
        'notifications' => [
          'id' => 'id',
          'id_content' => 'id_content',
          'id_user' => 'id_user',
          'web' => 'web',
          'browser' => 'browser',
          'mail' => 'mail',
          'mobile' => 'mobile',
          'read' => 'read',
          'dt_web' => 'dt_web',
          'dt_browser' => 'dt_browser',
          'dt_mail' => 'dt_mail',
          'dt_mobile' => 'dt_mobile',
          'dt_read' => 'dt_read'
        ],
        'content' => [
          'id' => 'id',
          'id_option' => 'id_option',
          'title' => 'title',
          'content' => 'content',
          'creation' => 'creation'
        ]
      ]
    ];

  private
    $opt,
    $user,
    $pref,
    $perms,
    $cfg,
    $lastDbId,
    $lastId;


  public function __construct(bbn\Db $db)
  {
    parent::__construct($db);
    $this->_init_class_cfg(self::$default_class_cfg);
    self::optionalInit();
    $this->opt   = bbn\Appui\Option::getInstance();
    $this->user  = bbn\User::getInstance();
    $this->pref  = new bbn\User\Preferences($this->db);
    $this->perms = new bbn\User\Permissions();
  }


  public function create(string $opt_path, string $title, string $content, $perms = true, string $opt_text = '', string $cat_text = '', bool $user_excluded = false): bool
  {
    if ($list_opt = self::getOptionId('list')) {
      $ocfg  = $this->opt->getClassCfg();
      $pcfg  = $this->pref->getClassCfg();
      $users = \is_array($perms) ? $perms : [];
      $perms = \is_bool($perms) && !empty($perms) && defined('BBN_ID_PERMISSION');
      if (!($id_opt = $this->opt->fromPath($opt_path, '/', $list_opt))) {
        $bits = \explode('/', $opt_path);
        if (count($bits) === 2) {
          if ($perms) {
            // Get permissions from the current BBN_ID_PERMISSION value
            $permissions = $this->db->selectAll(
              $pcfg['table'], [
              $pcfg['arch']['user_options']['id_user'],
              $pcfg['arch']['user_options']['id_group']
              ], [$pcfg['arch']['user_options']['id_option'] => BBN_ID_PERMISSION]
            );
            $is_public   = (bool)$this->opt->getProp(BBN_ID_PERMISSION, 'public');
            $perm_parent = $this->perms->optionToPermission($list_opt, true);
          }
          $parent = $list_opt;
          foreach ($bits as $i => $code) {
            $text = ($i === 0) && !empty($cat_text) ? $cat_text : (($i === 1) && !empty($opt_text) ? $opt_text : $code);
            if (!($p = $this->opt->fromCode($code, $parent))) {
              $p = $this->opt->add(
                [
                $ocfg['arch']['options']['text'] => $text,
                  $ocfg['arch']['options']['code'] => $code,
                  $ocfg['arch']['options']['id_parent'] => $parent
                ]
              );
            }
            if ($perms) {
              if (!($pp = $this->perms->optionToPermission($p))) {
                $pp = $this->perms->optionToPermission($p, true);
                $this->opt->setProp($pp, ['public' => $is_public]);
                if (!$is_public) {
                  foreach ($permissions as $perm) {
                    $this->lastDbId = $this->db->lastId();
                    if ($this->db->insert(
                      $pcfg['table'], [
                      $pcfg['arch']['user_options']['id_option'] => $pp,
                      $pcfg['arch']['user_options']['id_user'] => $perm->{$pcfg['arch']['user_options']['id_user']},
                      $pcfg['arch']['user_options']['id_group'] => $perm->{$pcfg['arch']['user_options']['id_group']}
                      ]
                    ) ) {
                      $this->lastId = $this->db->lastId();
                    }
                    $this->db->setLastInsertId($this->lastDbId);
                  }
                }
              }
              $perm_parent = $pp;
            }
            $parent = $p;
            if ($i === 1) {
              $id_opt = $parent;
            }
          }
        }
      }

      if (bbn\Str::isUid($id_opt)) {
        if ($perms) {
          return $perms ? $this->insertByOption($title, $content, $id_opt, $user_excluded) : $this->insert($title, $content, $id_opt, $users, $user_excluded);
        }
      }
    }

    return false;
  }


  public function insert(string $title, string $content, string $id_option = null, array $users = [], bool $user_excluded = false): bool
  {
    if (\is_string($id_option) && !bbn\Str::isUid($id_option)) {
      $id_option = \array_reverse(\explode('/', $id_option));
      if (\count($id_option) === 2) {
        $id_option[] = 'list';
      }

      $id_option = self::getOptionId(...$id_option);
    }

    if (!empty($title)
        && !empty($content)
        && (\is_null($id_option)
        || bbn\Str::isUid($id_option))
    ) {
      $notification = [
        $this->class_cfg['arch']['content']['id_option'] => $id_option,
        $this->class_cfg['arch']['content']['title'] => $title,
        $this->class_cfg['arch']['content']['content'] => $content,
        $this->class_cfg['arch']['content']['creation'] => \date('Y-m-d H:i:s')
      ];
      $this->lastDbId = $this->db->lastId();
      if ($this->db->insert($this->class_cfg['tables']['content'], $notification)) {
        $id = $this->db->lastId();
        if (empty($users) && !$user_excluded) {
          $users[] = $this->user->getId();
        }

        $i               = 0;
        $current_id_user = $this->user->getId();
        foreach ($users as $u){
          if ((!$user_excluded || ($current_id_user !== $u))
              && $this->_user_has_permission($notification, $u)
          ) {
            $i += (int)$this->db->insert(
              $this->class_table, [
              $this->fields['id_content'] => $id,
              $this->fields['id_user'] => $u
              ]
            );
            $this->lastId = $this->db->lastId();
          }
        }
        $this->db->setLastInsertId($this->lastDbId);
        return (bool)$i;
      }
    }

    return false;
  }


  public function insertByOption(string $title, string $content, string $id_option, bool $user_excluded = false): bool
  {
    if (!bbn\Str::isUid($id_option)) {
      $id_option = \array_reverse(\explode('/', $id_option));
      if (\count($id_option) === 2) {
        $id_option[] = 'list';
      }

      $id_option = self::getOptionId(...$id_option);
    }

    if (bbn\Str::isUid($id_option)
        && ($ucfg = $this->user->getClassCfg())
        && ($ocfg = $this->opt->getClassCfg())
        && ($groups = $this->db->getColumnValues($ucfg['tables']['groups'], $ucfg['arch']['groups']['id'], [$ucfg['arch']['groups']['type'] => 'real']))
        && ($id_perm = $this->db->selectOne($ocfg['table'], $ocfg['arch']['options']['id'], [$ocfg['arch']['options']['code'] => 'opt'.$id_option]))
        && ($perm = $this->opt->option($id_perm))
    ) {
      $users           = [];
      $is_public       = !empty($perm['public']);
      $current_id_user = $this->user->getId();
      foreach ($groups as $group) {
        $has_perm    = $this->pref->groupHas($id_perm, $group);
        $group_users = $this->db->selectAll(
          $ucfg['table'], [], [
          $ucfg['arch']['users']['id_group'] => $group,
          $ucfg['arch']['users']['active'] => 1
          ]
        );
        foreach ($group_users as $user) {
          $id_user = $user->{$ucfg['arch']['users']['id']};
          if (!\in_array($id_user, $users, true)
              && (!$user_excluded || ($current_id_user !== $id_user))
              && ($is_public
              || $has_perm
              || $this->pref->userHas($id_perm, $id_user)
              || (!empty($user->{$ucfg['arch']['users']['admin']})
              || !empty($user->{$ucfg['arch']['users']['dev']})))
          ) {
            $users[] = $id_user;
          }
        }
      }

      if (!empty($users)) {
        return $this->insert($title, $content, $id_option, $users);
      }
    }

    return false;
  }


  public function delete(string $id): ?bool
  {
    if (bbn\Str::isUid($id)) {
      return (bool)$this->db->delete($this->class_table, [$this->fields['id'] => $id]);
    }

    return null;
  }


  public function read($id, string $id_user = null, $moment = null): bool
  {
    if (!$id_user) {
      $id_user = $this->user->getId();
    }

    if (bbn\Str::isUid($id_user)) {
      if (\is_array($id)) {
        $todo = count($id);
        $did  = 0;
        foreach ($id as $i) {
          if ($this->read($i, $id_user, $moment)) {
            $did++;
          }
        }

        return $todo === $did;
      }
      elseif (bbn\Str::isUid($id)
          && !$this->db->selectOne($this->class_table, $this->fields['read'], [$this->fields['id'] => $id])
      ) {
        return (bool)$this->db->update(
          $this->class_table, [
          $this->fields['read'] => $moment ? \round((float)$moment, 4) : bbn\X::microtime()
          ], [
          $this->fields['id'] => $id
          ]
        );
      }
    }

    return false;
  }


  public function readAll(string $id_user = null, $moment = null): bool
  {
    if (!$id_user) {
      $id_user = $this->user->getId();
    }

    if (bbn\Str::isUid($id_user)
        && ($unreads = $this->getUnreadIds($id_user))
    ) {
      $todo = count($unreads);
      $did  = 0;
      foreach ($unreads as $id){
        $did += $this->db->update(
          $this->class_table, [
          $this->fields['read'] => $moment ? \round((float)$moment, 4) : bbn\X::microtime()
          ], [
          $this->fields['id'] => $id
          ]
        );
      }

      return $todo === $did;
    }

    return false;
  }


  public function get(string $id): array
  {
    if (bbn\Str::isUid($id) && ($ucfg = $this->user->getClassCfg())) {
      return $this->db->rselect(
        [
        'table' => $this->class_table,
        'fields' => array_merge(
          array_values($this->fields), [
          $this->class_cfg['arch']['content']['id_option'],
          $this->class_cfg['arch']['content']['title'],
          $this->class_cfg['arch']['content']['content'],
          $this->class_cfg['arch']['content']['creation']
          ]
        ),
        'join' => [[
          'table' => $this->class_cfg['tables']['content'],
          'on' => [
            'conditions' => [[
              'field' => $this->db->colFullName($this->fields['id_content'], $this->class_table),
              'exp' => $this->db->colFullName($this->class_cfg['arch']['content']['id'], $this->class_cfg['tables']['content'])
            ]]
          ]
        ], [
          'table' => $ucfg['table'],
          'on' => [
            'conditions' => [[
              'field' => $this->db->colFullName($this->fields['id_user'], $this->class_table),
              'exp' => $this->db->colFullName($ucfg['arch']['users']['id'], $ucfg['table'])
            ], [
              'field' => $this->db->colFullName($ucfg['arch']['users']['active'], $ucfg['table']),
              'value' => 1
            ]]
          ]
        ]],
        'where' => [
          'conditions' => [[
            'field' => $this->db->colFullName($this->fields['id'], $this->class_table),
            'value' => $id
          ]]
        ]
        ]
      );
    }

    return null;
  }


  public function getUnread(string $id_user = null, array $additional_where = []): array
  {
    $ucfg  = $this->user->getClassCfg();
    $where = [
      'conditions' => [[
        'field' => $this->db->colFullName($this->fields['read'], $this->class_table),
        'operator' => 'isnull'
      ]]
    ];
    if (bbn\Str::isUid($id_user)) {
      $where['conditions'][] = [
        'field' => $this->db->colFullName($this->fields['id_user'], $this->class_table),
        'value' => $id_user
      ];
    }

    if (!empty($additional_where)) {
      $where['conditions'][] = $additional_where;
    }

    return $this->db->rselectAll(
      [
      'table' => $this->class_table,
      'fields' => array_merge(
        array_values($this->fields), [
        $this->class_cfg['arch']['content']['id_option'],
        $this->class_cfg['arch']['content']['title'],
        $this->class_cfg['arch']['content']['content'],
        $this->class_cfg['arch']['content']['creation']
        ]
      ),
      'join' => [[
        'table' => $this->class_cfg['tables']['content'],
        'on' => [
          'conditions' => [[
            'field' => $this->db->colFullName($this->fields['id_content'], $this->class_table),
            'exp' => $this->db->colFullName($this->class_cfg['arch']['content']['id'], $this->class_cfg['tables']['content'])
          ]]
        ]
      ], [
        'table' => $ucfg['table'],
        'on' => [
          'conditions' => [[
            'field' => $this->db->colFullName($this->fields['id_user'], $this->class_table),
            'exp' => $this->db->colFullName($ucfg['arch']['users']['id'], $ucfg['table'])
          ], [
            'field' => $this->db->colFullName($ucfg['arch']['users']['active'], $ucfg['table']),
            'value' => 1
          ]]
        ]
      ]],
      'where' => $where,
      'order_by' => [[
        'field' => $this->db->colFullName($this->class_cfg['arch']['content']['creation'], $this->class_cfg['tables']['content']),
        'dir' => 'ASC'
      ]]
      ]
    );
  }


  public function getUnreadIds(string $id_user = null): array
  {
    $ucfg  = $this->user->getClassCfg();
    $where = [
      'conditions' => [[
        'field' => $this->db->colFullName($this->fields['read'], $this->class_table),
        'operator' => 'isnull'
      ]]
    ];
    if (bbn\Str::isUid($id_user)) {
      $where['conditions'][] = [
        'field' => $this->db->colFullName($this->fields['id_user'], $this->class_table),
        'value' => $id_user
      ];
    }

    return $this->db->getColumnValues(
      [
      'table' => $this->class_table,
      'fields' => [$this->fields['id']],
      'join' => [[
        'table' => $this->class_cfg['tables']['content'],
        'on' => [
          'conditions' => [[
            'field' => $this->db->colFullName($this->fields['id_content'], $this->class_table),
            'exp' => $this->db->colFullName($this->class_cfg['arch']['content']['id'], $this->class_cfg['tables']['content'])
          ]]
        ]
      ], [
        'table' => $ucfg['table'],
        'on' => [
          'conditions' => [[
            'field' => $this->db->colFullName($this->fields['id_user'], $this->class_table),
            'exp' => $this->db->colFullName($ucfg['arch']['users']['id'], $ucfg['table'])
          ], [
            'field' => $this->db->colFullName($ucfg['arch']['users']['active'], $ucfg['table']),
            'value' => 1
          ]]
        ]
      ]],
      'where' => $where,
      'order_by' => [[
        'field' => $this->db->colFullName($this->class_cfg['arch']['content']['creation'], $this->class_cfg['tables']['content']),
        'dir' => 'ASC'
      ]]
      ]
    );
  }


  public function getListByUser(string $id_user, array $data): ?array
  {
    if (bbn\Str::isUid($id_user)) {
      $ucfg = $this->user->getClassCfg();
      $grid = new bbn\Appui\Grid(
        $this->db, $data, [
        'table' => $this->class_table,
        'fields' => array_merge(
          array_values($this->fields), [
          $this->class_cfg['arch']['content']['id_option'],
          $this->class_cfg['arch']['content']['title'],
          $this->class_cfg['arch']['content']['content'],
          $this->class_cfg['arch']['content']['creation']
          ]
        ),
        'join' => [[
          'table' => $this->class_cfg['tables']['content'],
          'on' => [
            'conditions' => [[
              'field' => $this->db->colFullName($this->fields['id_content'], $this->class_table),
              'exp' => $this->db->colFullName($this->class_cfg['arch']['content']['id'], $this->class_cfg['tables']['content'])
            ]]
          ]
        ], [
          'table' => $ucfg['table'],
          'on' => [
            'conditions' => [[
              'field' => $this->db->colFullName($this->fields['id_user'], $this->class_table),
              'exp' => $this->db->colFullName($ucfg['arch']['users']['id'], $ucfg['table'])
            ], [
              'field' => $this->db->colFullName($ucfg['arch']['users']['active'], $ucfg['table']),
              'value' => 1
            ]]
          ]
        ]],
        'filters' => [
          'conditions' => [[
            'field' => $this->db->colFullName($this->fields['id_user'], $this->class_table),
            'value' => $id_user
          ]]
        ],
        'order' => [[
          'field' => $this->db->colFullName($this->class_cfg['arch']['content']['creation'], $this->class_cfg['tables']['content']),
          'dir' => 'DESC'
        ]]
        ]
      );
      if ($grid->check()) {
        return $grid->getDatatable();
      }
    }

    return null;
  }

  public function getLastId(){
    return $this->lastId;
  }

  public function contUnread(string $id_user = null): int
  {
    return \count($this->getUnreadIds($id_user));
  }


  public function notify($notification): ?bool
  {
    if (bbn\Str::isUid($notification)) {
      $notification = $this->get($notification);
    }

    if (\is_array($notification)
        && bbn\Str::isUid($notification[$this->fields['id']])
        && bbn\Str::isUid($notification[$this->fields['id_content']])
        && bbn\Str::isUid($notification[$this->fields['id_user']])
        && ($id_user = $notification[$this->fields['id_user']])
        && !empty($notification[$this->class_cfg['arch']['content']['title']])
        && !empty($notification[$this->class_cfg['arch']['content']['content']])
        && empty($notification[$this->fields['read']])
        && ($cfg = $this->getCfg($id_user, $notification[$this->class_cfg['arch']['content']['id_option']]))
    ) {
      $mtime    = bbn\X::microtime();
      $dpath    = bbn\Mvc::getUserDataPath($id_user, 'appui-notification');
      $ucfg     = $this->user->getClassCfg();
      $sessions = $this->db->selectAll(
        $ucfg['tables']['sessions'], [
        $ucfg['arch']['sessions']['id'],
        $ucfg['arch']['sessions']['sess_id']
        ], [
        $ucfg['arch']['sessions']['id_user'] => $id_user,
        $ucfg['arch']['sessions']['opened'] => 1
        ]
      );
      // Web notification
      if (empty($notification[$this->fields['web']])
          && !empty($cfg['web'])
          && !empty($sessions)
          && empty($notification[$this->fields['mail']])
      ) {
        foreach ($sessions as $sess) {
          $path = $dpath . "web/{$sess->id}/";
          if (bbn\File\Dir::createPath($path) && !\is_file($path . "$mtime.json")) {
            $notification[$this->fields['web']]    = $mtime;
            $notification[$this->fields['dt_web']] = date('Y-m-d H:i:s', $mtime);
            file_put_contents($path . "$mtime.json", Json_encode($notification));
          }
        }
      }
      // Browser notification
      elseif (empty($notification[$this->fields['browser']])
          && !empty($cfg['browser'])
          && !empty($sessions)
          && empty($notification[$this->fields['mail']])
      ) {
        foreach ($sessions as $sess) {
          $path = $dpath . "browser/{$sess->id}/";
          if (bbn\File\Dir::createPath($path) && !\is_file($path . "$mtime.json")) {
            $notification[$this->fields['browser']]    = $mtime;
            $notification[$this->fields['dt_browser']] = date('Y-m-d H:i:s', $mtime);
            file_put_contents($path . "$mtime.json", Json_encode($notification));
          }
        }
      }
      // Mail notification
      elseif (empty($notification[$this->fields['mail']]) && !empty($cfg['mail'])) {
        $creation = strtotime($notification[$this->class_cfg['arch']['content']['creation']]);
        if (($cfg['mail'] === 'immediately')
            || (($cfg['mail'] === 'daily')
            && (time() > strtotime('00:00:00 +1 day', $creation)))
            || (($cfg['mail'] === 'default')
            && (time() > strtotime('+1 hour', $creation)))
        ) {
          $notification[$this->fields['mail']] = $mtime;
          $this->_send_grouped_mail($notification, $cfg['mail']);
        }
      }

      // App notification
      //else if (empty($notification[$this->fields['mobile']]) && !empty($cfg['mobile'])) {}
      return $this->_update($notification[$this->fields['id']], $notification);
    }

    return null;
  }


  public function process()
  {
    foreach ($this->getUnreadIds() as $n) {
      $this->notify($n);
    }
  }


  public function getCfg(string $id_user, string $id_option = null): ?array
  {
    if (bbn\Str::isUid($id_user)
        && ($cfg_opt_id = self::getOptionId('cfg'))
        && bbn\Str::isUid($cfg_opt_id)
    ) {
      // Glogal cfg
      if (empty($this->cfg)) {
        $this->cfg = $this->opt->getValue($cfg_opt_id);
      }

      $cfg = $this->cfg;
      // Get global user's preferences
      if ($cfg_pref = $this->pref->getCfgByOption($cfg_opt_id, $id_user)) {
        $cfg = \array_merge($cfg, $cfg_pref);
      }

      // Get users's preferences of the notification's category
      if (bbn\Str::isUid($id_option)
          && ($id_option_parent = $this->opt->getIdParent($id_option))
          && bbn\Str::isUid($id_option_parent)
          && ($not_parent_pref = $this->pref->getCfgByOption($id_option_parent, $id_user))
      ) {
        $cfg = \array_merge($cfg, $not_parent_pref);
      }

      // Get user's preferences of this notification
      if (bbn\Str::isUid($id_option)
          && ($not_pref = $this->pref->getCfgByOption($id_option, $id_user))
      ) {
        $cfg = \array_merge($cfg, $not_pref);
      }

      return $cfg;
    }

    return null;
  }


  public function setCfg(array $cfg): bool
  {
    if (!empty($cfg['id_option'])
        && isset($cfg['web'], $cfg['browser'], $cfg['mail'], $cfg['mobile'])
    ) {
      return (bool)$this->pref->updateByOption(
        $cfg['id_option'], [
        'web' => (bool)$cfg['web'],
        'browser' => (bool)$cfg['browser'],
        'mail' => \is_string($cfg['mail']) ? $cfg['mail'] : (bool)$cfg['mail'],
        'mobile' => (bool)$cfg['mobile']
        ]
      );
    }

    return false;
  }


  private function _update(string $id, array $notification): ?bool
  {
    if (bbn\Str::isUid($id) && !empty($notification)) {
      if (isset($notification[$this->fields['id']])) {
        unset($notification[$this->fields['id']]);
      }

      $f      = $this->fields;
      $fields = array_values(
        array_filter(
          $this->fields, function ($field) use ($f) {
            return ($field !== $f['id'])
            && ($field !== $f['dt_web'])
            && ($field !== $f['dt_browser'])
            && ($field !== $f['dt_mail'])
            && ($field !== $f['dt_mobile'])
            && ($field !== $f['dt_read']);
          }
        )
      );
      return (bool)$this->db->update(
        $this->class_table, array_filter(
          $notification, function ($k) use ($fields) {
            return \in_array($k, $fields, true);
          }, ARRAY_FILTER_USE_KEY
        ), [$this->fields['id'] => $id]
      );
    }

    return null;
  }


  private function _send_mail(string $id_user, array $notifications): ?bool
  {
    if (($masks = new Masks($this->db))
        && ($templ = $masks->getDefault('notifications'))
        && bbn\Str::isUid($id_user)
        && !empty($notifications)
        && ($mgr = $this->user->getManager())
        && ($usr = $mgr->getUser($id_user))
        && ($ucfg = $this->user->getClassCfg())
        && ($rendered = bbn\Tpl::render(
          $templ['content'], [
          'user' => $usr[$ucfg['show']] ?? '',
          'notifications' => $notifications
          ]
        ))
        && ($email = $usr[$ucfg['arch']['users']['email']])
        && bbn\Str::isEmail($email)
    ) {
      $templ['title'] = str_replace('{{app_name}}', defined('BBN_SITE_TITLE') ? BBN_SITE_TITLE : BBN_CLIENT_NAME, $templ['title']);
      $this->lastDbId = $this->db->lastId();
      $ret = (bool)$this->db->insert(
        'bbn_emails', [
        'email' => $email,
        'subject' => $templ['title'],
        'text' => $rendered
        ]
      );
      $this->db->setLastInsertId($this->lastDbId);
      return $ret;
    }

    return null;
  }


  private function _send_grouped_mail(array $notification, string $mail_cfg)
  {
    if (($id_user = $notification[$this->fields['id_user']])
        && bbn\Str::isUid($id_user)
        && ($mail = $notification[$this->fields['mail']])
        && ($id_not = $notification[$this->fields['id']])
    ) {
      switch ($mail_cfg) {
        case 'daily':
          $notis = $this->getUnread(
            $id_user, [
            'conditions' => [[
              'field' => 'DATE('.$this->db->colFullName($this->class_cfg['arch']['content']['creation'], $this->class_cfg['tables']['content']).')',
              'value' => date('Y-m-d', $notification[$this->class_cfg['arch']['content']['creation']])
            ], [
              'field' => $this->db->colFullName($this->fields['mail'], $this->class_table),
              'operator' => 'isnull'
            ]]
            ]
          );
          break;
        case 'immediately':
        case 'default':
          $notis = $this->getUnread(
            $id_user, [
            'conditions' => [[
              'field' => $this->db->colFullName($this->fields['mail'], $this->class_table),
              'operator' => 'isnull'
            ]]
            ]
          );
          break;
      }

      $notifications = [];
      foreach ($notis as $n) {
        if (($cfg = $this->getCfg($id_user, $n[$this->class_cfg['arch']['content']['id_option']] ?? null))
            && !empty($cfg['mail'])
            && ($cfg['mail'] === $mail_cfg)
        ) {
          $n[$this->class_cfg['arch']['content']['creation']] = date('d/m/Y H:i', strtotime($n[$this->class_cfg['arch']['content']['creation']]));
          $notifications[]                                    = $n;
          if ($id_not !== $n[$this->fields['id']]) {
            $n[$this->fields['mail']] = $mail;
            $this->_update($n[$this->fields['id']], $n);
          }
        }
      }

      $this->_send_mail($id_user, $notifications);
    }
  }


  public function _user_has_permission($notification, string $id_user = null): bool
  {
    if (!\is_array($notification)
        && \is_string($notification)
        && bbn\Str::isUid($notification)
    ) {
      $notification = $this->get($notification);
    }

    if (\is_array($notification)
        && ($id_user = $id_user ?: ($notification[$this->fields['id_user']] ?? $this->user->getId()))
        && bbn\Str::isUid($id_user)
        && ($ucfg = $this->user->getClassCfg())
        && ($ocfg = $this->opt->getClassCfg())
        && ($user = $this->db->select(
          $ucfg['table'], [
          $ucfg['arch']['users']['id_group'],
          $ucfg['arch']['users']['admin'],
          $ucfg['arch']['users']['dev']
          ], [$ucfg['arch']['users']['id'] => $id_user]
        ))
    ) {
      $id_opt = $notification[$this->class_cfg['arch']['content']['id_option']] ?? null;
      if (!empty($user->{$ucfg['arch']['users']['admin']})
          || !empty($user->{$ucfg['arch']['users']['dev']})
          || empty($id_opt)
      ) {
        return true;
      }

      if (($id_perm = $this->db->selectOne($ocfg['table'], $ocfg['arch']['options']['id'], [$ocfg['arch']['options']['code'] => 'opt'.$id_opt]))
          && ($perm = $this->opt->option($id_perm))
      ) {
        if (!empty($perm['public'])) {
          return true;
        }

        return $this->pref->userHas($id_perm, $id_user)
          || $this->pref->groupHas($id_perm, $user->{$ucfg['arch']['users']['id_group']});
      }
    }

    return false;
  }


}
