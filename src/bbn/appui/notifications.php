<?php

namespace bbn\appui;
use bbn;

class notifications extends bbn\models\cls\db
{
  use
    bbn\models\tts\optional,
    bbn\models\tts\dbconfig;

  protected static

    /** @var array */
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
    $cfg;

  public function __construct(bbn\db $db)
  {
    parent::__construct($db);
    $this->_init_class_cfg(self::$default_class_cfg);
    self::optional_init();
    $this->opt = bbn\appui\options::get_instance();
    $this->user = bbn\user::get_instance();
    $this->pref = new bbn\user\preferences($this->db);
    $this->perms = new bbn\user\permissions();
  }

  public function create(string $opt_path, string $title, string $content, $perms = true, string $opt_text = '', string $cat_text = '', bool $user_excluded = false): bool
  {
    if ($list_opt = self::get_option_id('list')) {
      $ocfg = $this->opt->get_class_cfg();
      $pcfg = $this->pref->get_class_cfg();
      $users = \is_array($perms) ? $perms : [];
      $perms = \is_bool($perms) && !empty($perms) && defined('BBN_ID_PERMISSION');
      if (!($id_opt = $this->opt->from_path($opt_path, '/', $list_opt))) {
        $bits = \explode('/', $opt_path);
        if (count($bits) === 2) {
          if ($perms) {
            // Get permissions from the current BBN_ID_PERMISSION value
            $permissions = $this->db->select_all($pcfg['table'], [
              $pcfg['arch']['user_options']['id_user'],
              $pcfg['arch']['user_options']['id_group']
            ], [$pcfg['arch']['user_options']['id_option'] => BBN_ID_PERMISSION]);
            $is_public = (bool)$this->opt->get_prop(BBN_ID_PERMISSION, 'public');
            $perm_parent = $this->db->select_one($ocfg['table'], $ocfg['arch']['options']['id'], [$ocfg['arch']['options']['code'] => 'opt'.$list_opt]);
          }
          $parent = $list_opt;
          foreach ($bits as $i => $code) {
            $text = ($i === 0) && !empty($cat_text) ? $cat_text : (($i === 1) && !empty($opt_text) ? $opt_text : $code);
            if (!($p = $this->opt->from_code($code, $parent))) {
              $p = $this->opt->add([
                $ocfg['arch']['options']['text'] => $text,
                  $ocfg['arch']['options']['code'] => $code,
                  $ocfg['arch']['options']['id_parent'] => $parent
              ]);
            }
            if ($perms) {
              if (!($pp = $this->opt->from_code($p, $perm_parent))) {
                $pp = $this->opt->add([
                  $ocfg['arch']['options']['text'] => $text,
                  $ocfg['arch']['options']['code'] => 'opt'.$p,
                  $ocfg['arch']['options']['id_parent'] => $perm_parent,
                  $ocfg['arch']['options']['id_alias'] => $p,
                  'public' => $is_public
                ]);
                if (!$is_public) {
                  foreach ($permissions as $perm) {
                    $this->db->insert($pcfg['table'], [
                      $pcfg['arch']['user_options']['id_option'] => $pp,
                      $pcfg['arch']['user_options']['id_user'] => $perm->{$pcfg['arch']['user_options']['id_user']},
                      $pcfg['arch']['user_options']['id_group'] => $perm->{$pcfg['arch']['user_options']['id_group']}
                    ]);
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
      if (bbn\str::is_uid($id_opt)) {
        if ($perms) {
          return $perms ? $this->insert_by_option($title, $content, $id_opt, $user_excluded) : $this->insert($title, $content, $id_opt, $users, $user_excluded);
        }
      }
    }
    return false;
  }

  public function insert(string $title, string $content, string $id_option = null, array $users = [], bool $user_excluded = false): bool
  {
    if (\is_string($id_option) && !bbn\str::is_uid($id_option)) {
      $id_option = \array_reverse(\explode('/', $id_option));
      if (\count($id_option) === 2) {
        $id_option[] = 'list';
      }
      $id_option = self::get_option_id(...$id_option);
    }
    if (!empty($title)
      && !empty($content)
      && (\is_null($id_option)
        || bbn\str::is_uid($id_option))
    ) {
      $notification = [
        $this->class_cfg['arch']['content']['id_option'] => $id_option,
        $this->class_cfg['arch']['content']['title'] => $title,
        $this->class_cfg['arch']['content']['content'] => $content,
        $this->class_cfg['arch']['content']['creation'] => \date('Y-m-d H:i:s')
      ];
      if ($this->db->insert($this->class_cfg['tables']['content'], $notification)) {
        $id = $this->db->last_id();
        if (empty($users) && !$user_excluded) {
          $users[] = $this->user->get_id();
        }
        $i = 0;
        $current_id_user = $this->user->get_id();
        foreach ( $users as $u ){
          if ((!$user_excluded || ($current_id_user !== $u))
            && $this->_user_has_permission($notification, $u)
          ) {
            $i += (int)$this->db->insert($this->class_table, [
              $this->fields['id_content'] => $id,
              $this->fields['id_user'] => $u
            ]);
          }
        }
        return (bool)$i;
      }
    }
    return false;
  }

  public function insert_by_option(string $title, string $content, string $id_option, bool $user_excluded = false): bool
  {
    if (!bbn\str::is_uid($id_option)) {
      $id_option = \array_reverse(\explode('/', $id_option));
      if (\count($id_option) === 2) {
        $id_option[] = 'list';
      }
      $id_option = self::get_option_id(...$id_option);
    }
    if (bbn\str::is_uid($id_option)
      && ($ucfg = $this->user->get_class_cfg())
      && ($ocfg = $this->opt->get_class_cfg())
      && ($groups = $this->db->get_column_values($ucfg['tables']['groups'], $ucfg['arch']['groups']['id'], [$ucfg['arch']['groups']['type'] => 'real']))
      && ($id_perm = $this->db->select_one($ocfg['table'], $ocfg['arch']['options']['id'], [$ocfg['arch']['options']['code'] => 'opt'.$id_option]))
      && ($perm = $this->opt->option($id_perm))
    ) {
      $users = [];
      $is_public = !empty($perm['public']);
      $current_id_user = $this->user->get_id();
      foreach ($groups as $group) {
        $has_perm = $this->pref->group_has($id_perm, $group);
        $group_users = $this->db->select_all($ucfg['table'], [], [
          $ucfg['arch']['users']['id_group'] => $group,
          $ucfg['arch']['users']['active'] => 1
        ]);
        foreach ($group_users as $user) {
          $id_user = $user->{$ucfg['arch']['users']['id']};
          if (!\in_array($id_user, $users, true)
            && (!$user_excluded || ($current_id_user !== $id_user))
            && ($is_public
              || $has_perm
              || $this->pref->user_has($id_perm, $id_user)
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
    if (bbn\str::is_uid($id)) {
      return (bool)$this->db->delete($this->class_table, [$this->fields['id'] => $id]);
    }
    return null;
  }

  public function read($id, string $id_user = null, $moment = null): bool
  {
    if (!$id_user) {
      $id_user = $this->user->get_id();
    }
    if (bbn\str::is_uid($id_user)){
      if (\is_array($id)) {
        $todo = count($id);
        $did = 0;
        foreach ($id as $i) {
          if ($this->read($i, $id_user, $moment)) {
            $did++;
          }
        }
        return $todo === $did;
      }
      else if (bbn\str::is_uid($id)
        && !$this->db->select_one($this->class_table, $this->fields['read'], [$this->fields['id'] => $id])
      ) {
        return (bool)$this->db->update($this->class_table, [
          $this->fields['read'] => $moment ? \round((float)$moment, 4) : bbn\x::microtime()
        ], [
          $this->fields['id'] => $id
        ]);
      }
    }
    return false;
  }

  public function read_all(string $id_user = null, $moment = null): bool
  {
    if (!$id_user) {
      $id_user = $this->user->get_id();
    }
    if (bbn\str::is_uid($id_user)
      && ($unreads = $this->get_unread_ids($id_user))
    ) {
      $todo = count($unreads);
      $did = 0;
      foreach ($unreads as $id){
        $did += $this->db->update($this->class_table, [
          $this->fields['read'] => $moment ? \round((float)$moment, 4) : bbn\x::microtime()
        ], [
          $this->fields['id'] => $id
        ]);
      }
      return $todo === $did;
    }
    return false;
  }

  public function get(string $id): array
  {
    if (bbn\str::is_uid($id) && ($ucfg = $this->user->get_class_cfg())) {
      return $this->db->rselect([
        'table' => $this->class_table,
        'fields' => array_merge(array_values($this->fields), [
          $this->class_cfg['arch']['content']['id_option'],
          $this->class_cfg['arch']['content']['title'],
          $this->class_cfg['arch']['content']['content'],
          $this->class_cfg['arch']['content']['creation']
        ]),
        'join' => [[
          'table' => $this->class_cfg['tables']['content'],
          'on' => [
            'conditions' => [[
              'field' => $this->db->col_full_name($this->fields['id_content'], $this->class_table),
              'exp' => $this->db->col_full_name($this->class_cfg['arch']['content']['id'], $this->class_cfg['tables']['content'])
            ]]
          ]
        ], [
          'table' => $ucfg['table'],
          'on' => [
            'conditions' => [[
              'field' => $this->db->col_full_name($this->fields['id_user'], $this->class_table),
              'exp' => $this->db->col_full_name($ucfg['arch']['users']['id'], $ucfg['table'])
            ], [
              'field' => $this->db->col_full_name($ucfg['arch']['users']['active'], $ucfg['table']),
              'value' => 1
            ]]
          ]
        ]],
        'where' => [
          'conditions' => [[
            'field' => $this->db->col_full_name($this->fields['id'], $this->class_table),
            'value' => $id
          ]]
        ]
      ]);
    }
    return null;
  }

  public function get_unread(string $id_user = null, array $additional_where = []): array
  {
    $ucfg = $this->user->get_class_cfg();
    $where = [
      'conditions' => [[
        'field' => $this->db->col_full_name($this->fields['read'], $this->class_table),
        'operator' => 'isnull'
      ]]
    ];
    if (bbn\str::is_uid($id_user)) {
      $where['conditions'][] = [
        'field' => $this->db->col_full_name($this->fields['id_user'], $this->class_table),
        'value' => $id_user
      ];
    }
    if (!empty($additional_where)) {
      $where['conditions'][] = $additional_where;
    }
    return $this->db->rselect_all([
      'table' => $this->class_table,
      'fields' => array_merge(array_values($this->fields), [
        $this->class_cfg['arch']['content']['id_option'],
        $this->class_cfg['arch']['content']['title'],
        $this->class_cfg['arch']['content']['content'],
        $this->class_cfg['arch']['content']['creation']
      ]),
      'join' => [[
        'table' => $this->class_cfg['tables']['content'],
        'on' => [
          'conditions' => [[
            'field' => $this->db->col_full_name($this->fields['id_content'], $this->class_table),
            'exp' => $this->db->col_full_name($this->class_cfg['arch']['content']['id'], $this->class_cfg['tables']['content'])
          ]]
        ]
      ], [
        'table' => $ucfg['table'],
        'on' => [
          'conditions' => [[
            'field' => $this->db->col_full_name($this->fields['id_user'], $this->class_table),
            'exp' => $this->db->col_full_name($ucfg['arch']['users']['id'], $ucfg['table'])
          ], [
            'field' => $this->db->col_full_name($ucfg['arch']['users']['active'], $ucfg['table']),
            'value' => 1
          ]]
        ]
      ]],
      'where' => $where,
      'order_by' => [[
        'field' => $this->db->col_full_name($this->class_cfg['arch']['content']['creation'], $this->class_cfg['tables']['content']),
        'dir' => 'ASC'
      ]]
    ]);
  }

  public function get_unread_ids(string $id_user = null): array
  {
    $ucfg = $this->user->get_class_cfg();
    $where = [
      'conditions' => [[
        'field' => $this->db->col_full_name($this->fields['read'], $this->class_table),
        'operator' => 'isnull'
      ]]
    ];
    if (bbn\str::is_uid($id_user)) {
      $where['conditions'][] = [
        'field' => $this->db->col_full_name($this->fields['id_user'], $this->class_table),
        'value' => $id_user
      ];
    }
    return $this->db->get_column_values([
      'table' => $this->class_table,
      'fields' => [$this->fields['id']],
      'join' => [[
        'table' => $this->class_cfg['tables']['content'],
        'on' => [
          'conditions' => [[
            'field' => $this->db->col_full_name($this->fields['id_content'], $this->class_table),
            'exp' => $this->db->col_full_name($this->class_cfg['arch']['content']['id'], $this->class_cfg['tables']['content'])
          ]]
        ]
      ], [
        'table' => $ucfg['table'],
        'on' => [
          'conditions' => [[
            'field' => $this->db->col_full_name($this->fields['id_user'], $this->class_table),
            'exp' => $this->db->col_full_name($ucfg['arch']['users']['id'], $ucfg['table'])
          ], [
            'field' => $this->db->col_full_name($ucfg['arch']['users']['active'], $ucfg['table']),
            'value' => 1
          ]]
        ]
      ]],
      'where' => $where,
      'order_by' => [[
        'field' => $this->db->col_full_name($this->class_cfg['arch']['content']['creation'], $this->class_cfg['tables']['content']),
        'dir' => 'ASC'
      ]]
    ]);
  }

  public function get_list_by_user(string $id_user, array $data): ?array
  {
    if (bbn\str::is_uid($id_user)) {
      $ucfg = $this->user->get_class_cfg();
      $grid = new bbn\appui\grid($this->db, $data, [
        'table' => $this->class_table,
        'fields' => array_merge(array_values($this->fields), [
          $this->class_cfg['arch']['content']['id_option'],
          $this->class_cfg['arch']['content']['title'],
          $this->class_cfg['arch']['content']['content'],
          $this->class_cfg['arch']['content']['creation']
        ]),
        'join' => [[
          'table' => $this->class_cfg['tables']['content'],
          'on' => [
            'conditions' => [[
              'field' => $this->db->col_full_name($this->fields['id_content'], $this->class_table),
              'exp' => $this->db->col_full_name($this->class_cfg['arch']['content']['id'], $this->class_cfg['tables']['content'])
            ]]
          ]
        ], [
          'table' => $ucfg['table'],
          'on' => [
            'conditions' => [[
              'field' => $this->db->col_full_name($this->fields['id_user'], $this->class_table),
              'exp' => $this->db->col_full_name($ucfg['arch']['users']['id'], $ucfg['table'])
            ], [
              'field' => $this->db->col_full_name($ucfg['arch']['users']['active'], $ucfg['table']),
              'value' => 1
            ]]
          ]
        ]],
        'filters' => [
          'conditions' => [[
            'field' => $this->db->col_full_name($this->fields['id_user'], $this->class_table),
            'value' => $id_user
          ]]
        ],
        'order' => [[
          'field' => $this->db->col_full_name($this->class_cfg['arch']['content']['creation'], $this->class_cfg['tables']['content']),
          'dir' => 'DESC'
        ]]
      ]);
      if ($grid->check()){
        return $grid->get_datatable();
      }
    }
    return null;
  }

  public function cont_unread(string $id_user = null): int
  {
    return \count($this->get_unread_ids($id_user));
  }

  public function notify($notification): ?bool
  {
    if (bbn\str::is_uid($notification)) {
      $notification = $this->get($notification);
    }
    if (\is_array($notification)
      && bbn\str::is_uid($notification[$this->fields['id']])
      && bbn\str::is_uid($notification[$this->fields['id_content']])
      && bbn\str::is_uid($notification[$this->fields['id_user']])
      && ($id_user = $notification[$this->fields['id_user']])
      && !empty($notification[$this->class_cfg['arch']['content']['title']])
      && !empty($notification[$this->class_cfg['arch']['content']['content']])
      && empty($notification[$this->fields['read']])
      && ($cfg = $this->get_cfg($id_user, $notification[$this->class_cfg['arch']['content']['id_option']]))
    ) {
      $mtime = bbn\x::microtime();
      $dpath = bbn\mvc::get_user_data_path($id_user, 'appui-notifications');
      $ucfg = $this->user->get_class_cfg();
      $sessions = $this->db->select_all($ucfg['tables']['sessions'], [
        $ucfg['arch']['sessions']['id'],
        $ucfg['arch']['sessions']['sess_id']
      ], [
        $ucfg['arch']['sessions']['id_user'] => $id_user,
        $ucfg['arch']['sessions']['opened'] => 1
      ]);
      // Web notification
      if (empty($notification[$this->fields['web']])
        && !empty($cfg['web'])
        && !empty($sessions)
        && empty($notification[$this->fields['mail']])
      ) {
        foreach ($sessions as $sess) {
          $path = $dpath . "web/{$sess->id}/";
          if (bbn\file\dir::create_path($path) && !\is_file($path . "$mtime.json")) {
            $notification[$this->fields['web']] = $mtime;
            $notification[$this->fields['dt_web']] = date('Y-m-d H:i:s', $mtime);
            file_put_contents($path . "$mtime.json", json_encode($notification));
          }
        }
      }
      // Browser notification
      else if (empty($notification[$this->fields['browser']])
        && !empty($cfg['browser'])
        && !empty($sessions)
        && empty($notification[$this->fields['mail']])
      ) {
        foreach ($sessions as $sess) {
          $path = $dpath . "browser/{$sess->id}/";
          if ( bbn\file\dir::create_path($path) && !\is_file($path . "$mtime.json")) {
            $notification[$this->fields['browser']] = $mtime;
            $notification[$this->fields['dt_browser']] = date('Y-m-d H:i:s', $mtime);
            file_put_contents($path . "$mtime.json", json_encode($notification));
          }
        }
      }
      // Mail notification
      else if (empty($notification[$this->fields['mail']]) && !empty($cfg['mail'])) {
        $creation = strtotime($notification[$this->class_cfg['arch']['content']['creation']]);
        if ( ($cfg['mail'] === 'immediately')
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

  public function process(){
    foreach ($this->get_unread_ids() as $n) {
      $this->notify($n);
    }
  }

  public function get_cfg(string $id_user, string $id_option = null): ?array
  {
    if (bbn\str::is_uid($id_user)
      && ($cfg_opt_id = self::get_option_id('cfg'))
      && bbn\str::is_uid($cfg_opt_id)
    ) {
      // Glogal cfg
      if (empty($this->cfg)) {
        $this->cfg = $this->opt->get_value($cfg_opt_id);
      }
      $cfg = $this->cfg;
      // Get global user's preferences
      if ($cfg_pref = $this->pref->get_cfg_by_option($cfg_opt_id, $id_user)) {
        $cfg = \array_merge($cfg, $cfg_pref);
      }
      // Get users's preferences of the notification's category
      if (bbn\str::is_uid($id_option)
        && ($id_option_parent = $this->opt->get_id_parent($id_option))
        && bbn\str::is_uid($id_option_parent)
        && ($not_parent_pref = $this->pref->get_cfg_by_option($id_option_parent, $id_user))
      ) {
        $cfg = \array_merge($cfg, $not_parent_pref);
      }
      // Get user's preferences of this notification
      if (bbn\str::is_uid($id_option)
        && ($not_pref = $this->pref->get_cfg_by_option($id_option, $id_user))
      ) {
        $cfg = \array_merge($cfg, $not_pref);
      }
      return $cfg;
    }
    return null;
  }

  public function set_cfg(array $cfg): bool
  {
    if (!empty($cfg['id_option'])
      && isset($cfg['web'], $cfg['browser'], $cfg['mail'], $cfg['mobile'])
    ) {
      return (bool)$this->pref->update_by_option($cfg['id_option'], [
        'web' => (bool)$cfg['web'],
        'browser' => (bool)$cfg['browser'],
        'mail' => \is_string($cfg['mail']) ? $cfg['mail'] : (bool)$cfg['mail'],
        'mobile' => (bool)$cfg['mobile']
      ]);
    }
    return false;
  }

  private function _update(string $id, array $notification): ?bool
  {
    if (bbn\str::is_uid($id) && !empty($notification)) {
      if (isset($notification[$this->fields['id']])) {
        unset($notification[$this->fields['id']]);
      }
      $f = $this->fields;
      $fields = array_values(array_filter($this->fields, function($field) use($f){
        return ($field !== $f['id'])
          && ($field !== $f['dt_web'])
          && ($field !== $f['dt_browser'])
          && ($field !== $f['dt_mail'])
          && ($field !== $f['dt_mobile'])
          && ($field !== $f['dt_read']);
      }));
      return (bool)$this->db->update($this->class_table, array_filter($notification, function($k) use($fields){
        return \in_array($k, $fields, true);
      }, ARRAY_FILTER_USE_KEY), [$this->fields['id'] => $id]);
    }
    return null;
  }

  private function _send_mail(string $id_user, array $notifications): ?bool
  {
    if (($masks = new bbn\appui\masks($this->db))
      && ($templ = $masks->get_default('notifications'))
      && bbn\str::is_uid($id_user)
      && !empty($notifications)
      && ($mgr = $this->user->get_manager())
      && ($usr = $mgr->get_user($id_user))
      && ($ucfg = $this->user->get_class_cfg())
      && ($rendered = bbn\tpl::render($templ['content'], [
        'user' => $usr[$ucfg['show']] ?? '',
        'notifications' => $notifications
      ]))
      && ($email = $usr[$ucfg['arch']['users']['email']])
      && bbn\str::is_email($email)
    ){
      $templ['title'] = str_replace('{{app_name}}', defined('BBN_SITE_TITLE') ? BBN_SITE_TITLE : BBN_CLIENT_NAME, $templ['title']);
      return (bool)$this->db->insert('bbn_emails', [
        'email' => $email,
        'subject' => $templ['title'],
        'text' => $rendered
      ]);
    }
    return null;
  }

  private function _send_grouped_mail(array $notification, string $mail_cfg){
    if (($id_user = $notification[$this->fields['id_user']])
      && bbn\str::is_uid($id_user)
      && ($mail = $notification[$this->fields['mail']])
      && ($id_not = $notification[$this->fields['id']])
    ) {
      switch ($mail_cfg) {
        case 'daily':
          $notis = $this->get_unread($id_user, [
            'conditions' => [[
              'field' => 'DATE('.$this->db->col_full_name($this->class_cfg['arch']['content']['creation'], $this->class_cfg['tables']['content']).')',
              'value' => date('Y-m-d', $notification[$this->class_cfg['arch']['content']['creation']])
            ], [
              'field' => $this->db->col_full_name($this->fields['mail'], $this->class_table),
              'operator' => 'isnull'
            ]]
          ]);
          break;
        case 'immediately':
        case 'default':
          $notis = $this->get_unread($id_user, [
            'conditions' => [[
              'field' => $this->db->col_full_name($this->fields['mail'], $this->class_table),
              'operator' => 'isnull'
            ]]
          ]);
          break;
      }
      $notifications = [];
      foreach ($notis as $n) {
        if (($cfg = $this->get_cfg($id_user, $n[$this->class_cfg['arch']['content']['id_option']] ?? null))
          && !empty($cfg['mail'])
          && ($cfg['mail'] === $mail_cfg)
        ) {
          $n[$this->class_cfg['arch']['content']['creation']] = date('d/m/Y H:i', strtotime($n[$this->class_cfg['arch']['content']['creation']]));
          $notifications[] = $n;
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
      && bbn\str::is_uid($notification)
    ) {
      $notification = $this->get($notification);
    }
    if (\is_array($notification)
      && ($id_user = $id_user ?: ($notification[$this->fields['id_user']] ?? $this->user->get_id()))
      && bbn\str::is_uid($id_user)
      && ($ucfg = $this->user->get_class_cfg())
      && ($ocfg = $this->opt->get_class_cfg())
      && ($user = $this->db->select($ucfg['table'], [
        $ucfg['arch']['users']['id_group'],
        $ucfg['arch']['users']['admin'],
        $ucfg['arch']['users']['dev']
      ], [$ucfg['arch']['users']['id'] => $id_user]))
    ) {
      $id_opt = $notification[$this->class_cfg['arch']['content']['id_option']] ?? null;
      if (!empty($user->{$ucfg['arch']['users']['admin']})
        || !empty($user->{$ucfg['arch']['users']['dev']})
        || empty($id_opt)
      ) {
        return true;
      }
      if (($id_perm = $this->db->select_one($ocfg['table'], $ocfg['arch']['options']['id'], [$ocfg['arch']['options']['code'] => 'opt'.$id_opt]))
        && ($perm = $this->opt->option($id_perm))
      ) {
        if (!empty($perm['public'])) {
          return true;
        }
        return $this->pref->user_has($id_perm, $id_user)
          || $this->pref->group_has($id_perm, $user->{$ucfg['arch']['users']['id_group']});
      }
    }
    return false;
  }
}

