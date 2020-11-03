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
          'app' => 'app',
          'read' => 'read',
          'dt_web' => 'dt_web',
          'dt_browser' => 'dt_browser',
          'dt_mail' => 'dt_mail',
          'dt_app' => 'dt_app',
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
    $cfg;

  public function __construct(bbn\db $db)
  {
    parent::__construct($db);
    self::_init_class_cfg(self::$default_class_cfg);
    self::optional_init();
    $this->opt = bbn\appui\options::get_instance();
    $this->user = bbn\user::get_instance();
    $this->pref = bbn\user\preferences::get_instance();
  }

  public function insert(string $title, string $content, string $id_option = null, array $users = []): bool
  {
    if (!empty($title) && !empty($content)) {
      $this->db->insert($this->class_cfg['tables']['content'], [
        $this->class_cfg['arch']['content']['id_option'] => $id_option,
        $this->class_cfg['arch']['content']['title'] => $title,
        $this->class_cfg['arch']['content']['content'] => $content,
        $this->class_cfg['arch']['content']['creation'] => \date('Y-m-d H:i:s')
      ]);
      $id = $this->db->last_id();
      if (empty($users)) {
        $users[] = $this->user->get_id();
      }
      $i = 0;
      foreach ( $users as $u ){
        $i += (int)$this->db->insert($this->class_table, [
          $this->fields['id_content'] => $id,
          $this->fields['id_user'] => $u
        ]);
      }
      return (bool)$i;
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

  public function read(string $id, string $id_user = null, $moment = null){
    if (!$id_user) {
      $id_user = $this->user->get_id();
    }
    if (bbn\str::is_uid($id)
      && bbn\str::is_uid($id_user)
      && !$this->db->select_one($this->class_table, $this->fields['read'], [$this->fields['id'] => $id])
    ) {
      return $this->db->update($this->class_table, [
        $this->fields['read'] => $moment ? \round((float)$moment, 4) : bbn\x::microtime()
      ], [
        $this->fields['id'] => $id
      ]);
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
      'where' => $where
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
      'where' => $where
    ]);
  }

  public function notify($notification): ?bool
  {
    if (bbn\str::is_uid($notification)) {
      $notification = $this->get($notification);
    }
    if (\is_array($notification)
      &&bbn\str::is_uid($notification[$this->fields['id']])
      && bbn\str::is_uid($notification[$this->fields['id_content']])
      && bbn\str::is_uid($notification[$this->fields['id_user']])
      && ($id_user = $notification[$this->fields['id_user']])
      && !empty($notification[$this->class_cfg['arch']['content']['title']])
      && !empty($notification[$this->class_cfg['arch']['content']['content']])
      && empty($notification[$this->fields['read']])
      && ($cfg = $this->get_cfg($id_user, $notification[$this->class_cfg['arch']['content']['id_option']]))
    ) {
      $mtime = bbn\x::microtime();
      $path = bbn\mvc::get_user_data_path($id_user, 'appui-notifications');
      $ucfg = $this->user->get_class_cfg();
      $sessions = $this->db->select_all($ucfg['tables']['sessions'], [
        $ucfg['arch']['sessions']['id'],
        $ucfg['arch']['sessions']['sess_id']
      ], [
        $ucfg['arch']['sessions']['id_user'] => $id_user,
        $ucfg['arch']['sessions']['opened'] => 1
      ]);
      // Web notification
      if (empty($notification[$this->fields['web']])) {
        foreach ($sessions as $sess) {
          $path = $path . "web/{$sess->id}/";
          if (bbn\file\dir::create_path($path) && !\is_file($path . "$mtime.json")) {
            $notification[$this->fields['web']] = $mtime;
            $notification[$this->fields['dt_web']] = date('Y-m-d H:i:s', $mtime);
            file_put_contents($path . "$mtime.json", json_encode($notification));
          }
        }
      }
      // Browser notification
      else if (empty($notification[$this->fields['browser']])) {
        foreach ($sessions as $sess) {
          $path = $path . "browser/{$sess->id}/";
          if ( bbn\file\dir::create_path($path) && !\is_file($path . "$mtime.json")) {
            $notification[$this->fields['browser']] = $mtime;
            $notification[$this->fields['dt_browser']] = date('Y-m-d H:i:s', $mtime);
            file_put_contents($path . "$mtime.json", json_encode($notification));
          }
        }
      }
      // Mail notification
      else if (empty($notification[$this->fields['mail']]) && !empty($cfg['mail'])) {
        $creation = strtotime($notification[$this->class_cfg['arc']['content']['creation']]);
        switch ($cfg['mail']) {
          case 'immediately':
            $notification[$this->fields['mail']] = $mtime;
            $this->_send_mail($id_user, [$notification]);
            break;
          case 'daily':
            if (time() > strtotime('00:00:00 +1 day', $creation)) {
              $notification[$this->fields['mail']] = $mtime;
              $this->_send_grouped_mail($notification, 'daily');
            }
            break;
          case 'default':
            if (time() > strtotime('+1 hour', $creation)) {
              $notification[$this->fields['mail']] = $mtime;
              $this->_send_grouped_mail($notification, 'default');
            }
            break;
        }
      }
      // App notification
      //else if (empty($notification[$this->fields['app']])) {}
      return $this->_update($notification[$this->fields['id']], $notification);
    }
    return null;
  }

  public function process(){
    foreach ($this->get_unread_ids() as $n) {
      $this->notify($n);
    }
  }

  private function get_cfg(string $id_user, string $id_option = null){
    $cfg_opt_id = self::get_option_id('cfg');
    // Glogal cfg
    if (empty($this->cfg)) {
      $this->cfg = $this->opt->get_value($cfg_opt_id);
    }
    $cfg = $this->cfg;
    // Get global user's preferences
    if (($cfg_pref_ids = $this->pref->retrieve_user_ids($cfg_opt_id, $id_user))
      && ($cfg_pref = $this->pref->get_cfg($cfg_pref_ids[0]))
    ) {
      $cfg = \array_merge($cfg, $cfg_pref);
    }
    // Get user's preferences of this notification
    if (bbn\str::is_uid($id_option)
      && ($not_pref_ids = $this->pref->retrieve_user_ids($id_option, $id_user))
      && ($not_pref = $this->pref->get_cfg($not_pref_ids[0]))
    ) {
      $cfg = \array_merge($cfg, $not_pref);
    }
    return $cfg;
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
          && ($field !== $f['dt_app'])
          && ($field !== $f['dt_read']);
      }));
      return (bool)$this->db->update($this->class_table, array_filter($notification, function($k) use($fields){
        return \in_array($k, $fields, true);
      }, ARRAY_FILTER_USE_KEY), [$this->fields['id'] => $id]);
    }
    return null;
  }

  private function _send_mail(string $id_user, array $notifications){
    if (($masks = new bbn\appui\masks($this->db))
      && ($templ = $masks->get_default('notifications'))
      && bbn\str::is_uid($id_user)
    ){
      $templ['title'] = str_replace('{{app_name}}', defined('BBN_SITE_TITLE') ? BBN_SITE_TITLE : BBN_CLIENT_NAME, $templ['title']);
      $rendered = bbn\tpl::render($templ['content'], [
        'user' => $this->user->get_name($id_user),
        'notifications' => $notifications
      ]);
      \bbn\x::hdump($rendered);
    }
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
            ], [
              'field' => $this->db->col_full_name($this->fields['web'], $this->class_table),
              'operator' => 'isnotnull'
            ], [
              'field' => $this->db->col_full_name($this->fields['browser'], $this->class_table),
              'operator' => 'isnotnull'
            ]]
          ]);
          break;
        case 'default':
          $notis = $this->get_unread($id_user, [
            'conditions' => [[
              'field' => $this->db->col_full_name($this->fields['mail'], $this->class_table),
              'operator' => 'isnull'
            ], [
              'field' => $this->db->col_full_name($this->fields['web'], $this->class_table),
              'operator' => 'isnotnull'
            ], [
              'field' => $this->db->col_full_name($this->fields['browser'], $this->class_table),
              'operator' => 'isnotnull'
            ]]
          ]);
          break;
      }
      $notifications = [];
      foreach ($notis as $n) {
        if (($cfg = $this->get_cfg($id_user, $n[$this->class_cfg['arch']['content']['id_option']]))
          && !empty($cfg['mail'])
          && ($cfg['mail'] === $mail_cfg)
        ) {
          $notifications[] = [
            'title' => $n[$this->class_cfg['arch']['content']['title']],
            'content' => $n[$this->class_cfg['arch']['content']['content']]
          ];
          if ($id_not !== $n[$this->fields['id']]) {
            $n[$this->fields['mail']] = $mail;
            $this->_update($n[$this->fields['id']], $n);
          }
        }
      }
      $this->_send_mail($id_user, $notifications);
    }
  }
}

