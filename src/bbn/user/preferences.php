<?php
/**
 * @package user
 */
namespace bbn\user;
use bbn;
/**
 * A user's preference system linked to options and user classes
 *
 * A preference consists in a row with an ID_OPTION and a ID_USER, ID_GROUP, or PUBLIC.
 * The class needs a user object on which each query will be based.
 * This class must be able to:
 * - read options with the same arguments as the option class (filtered based on preference existence)
 * - retrieve preference(s) for an option
 * - write preference by adding to an existing set or setting a unique
 *
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Oct 28, 2015, 10:23:55 +0000
 * @category  Authentication
 * @license   http://opensource.org/licenses/MIT MIT
 * @version 0.1
 * @todo Groups and hotlinks features
 */

class preferences extends bbn\models\cls\db
{
  use bbn\models\tts\retriever,
      bbn\models\tts\dbconfig,
      bbn\models\tts\optional,
      bbn\models\tts\current;

  protected static
		/** @var array */
		$_defaults = [
			'table' => 'bbn_users_options',
      'tables' => [
        'user_options' => 'bbn_users_options',
        'user_options_bits' => 'bbn_users_options_bits'
      ],
			'arch' => [
			  'user_options' => [
          'id' => 'id',
          'id_option' => 'id_option',
          'num' => 'num',
          'id_user' => 'id_user',
          'id_group' => 'id_group',
          'id_alias' => 'id_alias',
          'public' => 'public',
          'id_link' => 'id_link',
          'text' => 'text',
          'cfg' => 'cfg'
        ],
        'user_options_bits' => [
          'id' => 'id',
          'id_user_option' => 'id_user_option',
          'id_parent' => 'id_parent',
          'id_option' => 'id_option',
          'num' => 'num',
          'text' => 'text',
          'cfg' => 'cfg'
        ]
			]
		];

	protected
    /** @var bbn\appui\options */
    $opt,
    /** @var bbn\user */
    $user,
		/** @var int */
		$id_user,
		/** @var int */
		$id_group;

  /**
   * Sets the user variables using a user object
   *
   * @param bbn\user $user
   * @return preferences
   */
  private function _init_user(bbn\user $user): preferences
  {
    $this->user = $user;
    $this->id_user = $this->user->get_id();
    $this->id_group = $this->user->get_group();
    return $this;
  }

  /**
   * Retrieves or confirm the ID of an option based on the same parameters as options::from_path
   *
   * @param string|null $id_option
   * @return null|string
   */
  private function _get_id_option(string $id_option = null): ?string
  {
    if ( !$id_option && !($id_option = $this->get_current()) ){
      return null;
    }
    if ( $id_option && !bbn\str::is_uid($id_option) ){
      $id_option = $this->opt->from_path(...\func_get_args());
    }
    if ( $id_option && bbn\str::is_uid($id_option) ){
      return $id_option;
    }
    return null;
  }

  /**
   * Actually inserts a row into the preferences table
   *
   * @param string $id_option
   * @param array $cfg
   * @return int
   */
  private function _insert(string $id_option, array $cfg): int
  {
    $json = ($tmp = $this->get_cfg(false, $cfg)) ? json_encode($tmp) : NULL;
    return $this->db->insert($this->class_cfg['table'], [
      $this->fields['id_option'] => $id_option,
      $this->fields['num'] => $cfg[$this->fields['num']] ?? NULL,
      $this->fields['text'] => $cfg[$this->fields['text']] ?? NULL,
      $this->fields['id_link'] => $cfg[$this->fields['id_link']] ?? NULL,
      $this->fields['id_alias'] => $cfg[$this->fields['id_alias']] ?? NULL,
      $this->fields['id_user'] => $this->id_user,
      $this->fields['cfg'] => $json
    ]);
  }

  /**
   * Returns preferences' IDs from the option's ID
   *
   * @param string $id_option
   * @param null|string $id_user
   * @param null|string $id_group
   * @return array|null
   */
  private function _retrieve_ids(string $id_option, string $id_user = null, string $id_group = null): ?array
  {
    if (!$id_user && !$id_group && isset($this->id_user, $this->id_group) ){
      $id_user = $this->id_user;
      $id_group = $this->id_group;
    }
    if ( ($id_user || $id_group) && ($id_option = $this->_get_id_option($id_option)) ){
      $cond = [
        'logic' => 'OR',
        'conditions' => []
      ];
      if ( null !== $id_user ){
        $cond['conditions'][] = [
          'field' => $this->fields['id_user'],
          'value' => $id_user
        ];
      }
      if ( null !== $id_group ){
        $cond['conditions'][] = [
          'field' => $this->fields['id_group'],
          'value' => $id_group
        ];
      }
      // Not specific to just a group or a user, so adding the public i.e. all to which the user has right
      if ( $id_user && $id_group ){
        $cond['conditions'][] = [
          'field' => $this->fields['public'],
          'value' => 1
        ];
      }
      $where = [
        'logic' => 'AND',
        'conditions' => [[
          'field' => $this->fields['id_option'],
          'value' => $id_option
        ]]
      ];
      if ( count($cond['conditions']) ){
        $where['conditions'][] = $cond;
      }
      return $this->db->get_column_values([
        'table' => $this->class_cfg['table'],
        'fields' => [$this->fields['id']],
        'where' => $where,
        'order' => [
          ['field' => $this->fields['num'], 'dir' => 'ASC'],
          ['field' => $this->fields['text'], 'dir' => 'ASC']
        ]
      ]);
    }
    return null;
  }

  /**
   * Gets the preferences which have the option's $id as id_link
   *
   * @param string $id_link
   * @return array|null
   */
  private function _get_links(string $id_link, string $id_user = null, string $id_group = null): ?array
  {
    if ( $id_link = $this->_get_id_option($id_link) ){
      $where = [
        'logic' => 'AND',
        'conditions' => [
          [
            'field' => $this->fields['id_link'],
            'operator' => '=',
            'value' => $id_link
          ]
        ]
      ];
      if ( null !== $id_user ){
        $cond[$this->fields['id_user']] = $id_user;
      }
      if ( null !== $id_group ){
        $cond[$this->fields['id_group']] = $id_group;
      }
      // Not specific
      if ( (null === $id_user) && (null === $id_group) ){
        $cond[$this->fields['public']] = 1;
      }
      $where['conditions'][] = [
        'logic' => 'OR',
        'conditions' => $cond
      ];
      return $this->db->rselect_all([
        'tables' => [$this->class_cfg['table']],
        'fields' => [$this->fields['id'], $this->fields['id_option']],
        'where' => $where,
        'order' => [$this->fields['text']]
      ]);
    }
    return null;
  }

  /**
   * @return preferences|null
   */
  public static function get_preferences(): ?preferences
  {
    return self::get_instance();
  }

  /**
   * preferences constructor.
   * @param bbn\db $db
   * @param array $cfg
   */
  public function __construct(bbn\db $db, array $cfg = []){
	  parent::__construct($db);
    $this->_init_class_cfg($cfg);
	  if ( $user = bbn\user::get_instance() ){
      $this->_init_user($user);
    }
    $this->opt = bbn\appui\options::get_instance();
    if ( $this->user && $this->opt ){
      self::retriever_init($this);
    }
	}

  /**
   * @return array
   */
  public function get_class_cfg(): array
  {
    return $this->class_cfg;
  }

  /**
   * Returns preferences' IDs from the option's ID
   *
   * @param null|string $id_option
   * @return null|array
   */
  public function retrieve_ids(string $id_option = null): ?array
  {
    return $this->_retrieve_ids($id_option, $this->id_user, $this->id_group);
  }

  /**
   * Returns preferences' IDs from the option's ID and the given user ID
   *
   * @param null|string $id_option
   * @param string $id_user
   * @return array|null
   */
  public function retrieve_user_ids(string $id_option = null, string $id_user = null): ?array
  {
    if (!$id_user) {
      $id_user = $this->id_user;
    }
    return $this->_retrieve_ids($id_option, $id_user);
  }

  /**
   * Returns preferences' IDs from the option's ID and the given group ID
   *
   * @param null|string $id_option
   * @param string $id_group
   * @return array|null
   */
  public function retrieve_group_ids(string $id_option = null, string $id_group = null): ?array
  {
    if (!$id_group) {
      $id_group = $this->id_group;
    }
    return $this->_retrieve_ids($id_option, null, $id_group);
  }

  /**
   * Returns true if the current user can access a preference, false otherwise
   *
   * @param string|null $id_option
   * @param bool $force
   * @return bool
   */
  public function has(string $id_option = null, bool $force = false): bool
  {
    if ( !$force && $this->user->is_dev() ){
      return true;
    }
    return (bool)$this->retrieve_ids($id_option);
  }

  /**
   * Checks if a user has the given preference
   *
   * @param string $id_option
   * @param string $id_user
   * @return bool
   */
  public function user_has(string $id_option, string $id_user): bool
  {
    return (bool)$this->_retrieve_ids($id_option, $id_user);
  }

  /**
   * Checks if a group has the given preference
   *
   * @param string $id_option
   * @param string $id_group
   * @return bool
   */
  public function group_has(string $id_option, string $id_group): bool
  {
    return (bool)$this->_retrieve_ids($id_option, null, $id_group);
  }

  /**
   * @return null|string
   */
  public function get_user(): ?string
  {
    return $this->id_user;
  }

  /**
   * @return null|string
   */
  public function get_group(): ?string
  {
    return $this->id_group;
  }

  /**
   * @param bbn\user $user
   * @return preferences
   */
  public function set_user(bbn\user $user): preferences
  {
    $this->_init_user($user);
    return $this;
  }

  /**
   * Sets the given group.
   * 
   * @param string $id_group
   * @return preferences
   */
  public function set_group(string $id_group): preferences
  {
    if ( \bbn\str::is_uid($id_group) ){
      $this->id_group = $id_group;
    }
    return $this;
  }

  /**
   * Gets the cfg array, normalized either from the DB or from the $cfg argument
   *
   * @param string $id
   * @param null|array $cfg
   * @return null|array
   */
  public function get_cfg(string $id = null, array $cfg = null): ?array
  {
    if (
      (null !== $cfg) ||
      ($cfg = $this->db->select_one(
        $this->class_cfg['table'],
        $this->fields['cfg'],
        [$this->fields['id'] => $id ]
      ))
    ){
      if ( bbn\str::is_json($cfg) ){
        $cfg = json_decode($cfg, 1);
      }
      if ( \is_array($cfg) ){
        $new = [];
        foreach ( $cfg as $k => $v){
          if ( !\in_array($k, $this->fields, true) ){
            $new[$k] = $v;
          }
        }
        return $new;
      }
    }
    return null;
  }

  /**
   * Gets the cfg array, normalized either from the DB or from the $cfg argument
   *
   * @param string $id
   * @param null|array $cfg
   * @return null|array
   */
  public function get_cfg_by_option(string $id_option, string $id_user = null): ?array
  {
    if (
      ($cfg = $this->db->select_one(
        $this->class_cfg['table'],
        $this->fields['cfg'],
        [
          $this->fields['id_option'] => $id_option,
          $this->fields['id_user'] => $id_user ?: $this->id_user,
        ]
      ))
    ){
      if ( bbn\str::is_json($cfg) ){
        $cfg = json_decode($cfg, 1);
      }
      return $this->get_cfg(false, $cfg);
    }
    return null;
  }

  /**
   * Gets the preferences which have the option's $id as id_link
   *
   * @param string $id
   * @return array|null
   */
  public function get_links(string $id): ?array
  {
    return $this->_get_links($id, $this->id_user, $this->id_group);
  }

  /**
   * Returns the current user's preference based on the given id, his own profile and his group's
   * @param string $id
   * @param bool $with_config
   * @return array|null
   */
  public function get(string $id, bool $with_config = true): ?array
  {
    if ( bbn\str::is_uid($id) ){
      $table = $this->db->tsn($this->class_cfg['table'], true);
      $uid = $this->db->csn($this->fields['id'], true);
      $id_user = $this->db->csn($this->fields['id_user'], true);
      $id_group = $this->db->csn($this->fields['id_group'], true);
      $public = $this->db->csn($this->fields['public'], true);
      if ( $row = $this->db->rselect([
        'table' => $table,
        'fields' => $this->fields,
        'where' => [
          'conditions' => [[
            'field' => $uid,
            'value' => $id
          ], [
            'logic' => 'OR',
            'conditions' => [[
              'field' => $id_user,
              'value' => $this->id_user
            ], [
              'field' => $id_group,
              'value' => $this->id_group
            ], [
              'field' => $public,
              'value' => 1
            ]]
          ]]
        ]
      ]) ){
        if ( $with_config ){
          $cfg = $row[$this->fields['cfg']];
          unset($row[$this->fields['cfg']]);
          if ( $cfg = json_decode($cfg, true) ){
            $row = bbn\x::merge_arrays($cfg, $row);
          }
        }
        return $row;
      }
    }
    return null;
  }

  /**
   * Returns an array of the current user's preferences based on the given id_option, his own profile and his group's
   * @param null|string $id_option
   * @param bool $with_config
   * @return array|null
   */
  public function get_all(string $id_option = null, bool $with_config = true): ?array
  {
    if ( $id_option = $this->_get_id_option($id_option) ){
      $farch = $this->fields;
      $fields = [];
      foreach ( $farch as $k => $f ){
        $field = $this->class_table . '.' . $f;
        if ( $k === 'cfg' ){
          $fields[$farch['cfg']] = "IFNULL($field, aliases.$farch[cfg])";
        }
        else if ( $k === 'text' ){
          $fields[$farch['text']] = "IFNULL($field, aliases.$farch[text])";
        }
        else {
          $fields[] = $field;
        }
      }
      if ( $rows = $this->db->rselect_all([
        'table' => $this->class_table,
        'fields' => $fields,
        'join' => [[
          'table' => $this->class_table,
          'type' => 'left',
          'alias' => 'aliases',
          'on' => [
            'conditions' => [[
              'field' => $farch['id_alias'],
              'exp' => 'aliases.id'
            ]]
          ]]
        ],
        'where' => [
          'conditions' => [[
            'field' => $farch['id_option'],
            'value' => $id_option
          ], [
            'logic' => 'OR',
            'conditions' => [[
              'field' => $farch['id_user'],
              'value' => $this->id_user
            ], [
              'field' => $farch['id_group'],
              'value' => $this->id_group
            ], [
              'field' => $farch['public'],
              'value' => 1
            ]]
          ]]
        ]
      ]) ) {
        return $with_config ? array_map(function($a) use($farch){
          $cfg = $a[$farch['cfg']];
          unset($a[$farch['cfg']]);
          if ( $cfg = json_decode($cfg, true) ){
            $a = bbn\x::merge_arrays($cfg, $a);
          }
          return $a;
        }, $rows) : $rows;
      }
      return [];
    }
    return null;
  }

  /**
   * Returns an array of the users' preferences (the current user and group are excluded) based on the given id_option
   * @param null|string $id_option
   * @param bool $with_config
   * @return array|null
   */
  public function get_all_not_mine(string $id_option = null, bool $with_config = true): ?array
  {
    if ( $id_option = $this->_get_id_option($id_option) ){
      $fields = $this->fields;
      if ( !$with_config ){
        unset($fields['cfg']);
      }
      if ( $rows = $this->db->rselect_all([
        'table' => $this->class_table,
        'fields' => $fields,
        'join' => [[
          'table' => $this->class_table,
          'type' => 'left',
          'alias' => 'aliases',
          'on' => [
            'conditions' => [[
              'field' => $this->fields['id_alias'],
              'exp' => 'aliases.id'
            ]]
          ]
        ]],
        'where' => [
          'conditions' => [[
            'field' => $this->fields['id_option'],
            'value' => $id_option
          ], [
            'field' => $this->fields['public'],
            'value' => 0
          ], [
            'logic' => 'OR',
            'conditions' => [[
              'field' => $this->fields['id_user'],
              'operator' => '!=',
              'value' => $this->id_user
            ], [
              'field' => $this->fields['id_user'],
              'operator' => 'isnull'
            ]]
          ], [
            'logic' => 'OR',
            'conditions' => [[
              'field' => $this->fields['id_group'],
              'operator' => 'neq',
              'value' => $this->id_group
            ], [
              'field' => $this->fields['id_group'],
              'operator' => 'isnull'
            ]]
          ]]
        ]
      ]) ) {
        return $with_config ? array_map(function($a){
          $cfg = $a['cfg'];
          unset($a['cfg']);
          if ( ($cfg = json_decode($cfg, true)) ){
            $a = bbn\x::merge_arrays($cfg, $a);
          }
          return $a;
        }, $rows) : $rows;
      }
      return [];
    }
    return null;
  }

  public function get_by_option(string $id_option, bool $with_config = true): ?array
  {
    if ( $id = $this->retrieve_user_ids($id_option, $this->id_user) ){
      return $this->get($id[0], $with_config);
    }
    return null;
  }

  public function option(): ?array
  {
    if ( $o = $this->opt->option(\func_get_args()) ){
      if ( ($ids = $this->retrieve_ids($o['id'])) && ($cfg = $this->get($ids[0])) ){
        $o = bbn\x::merge_arrays($o, $cfg);
      }
      return $o;
    }
    return null;
  }

  public function text(string $id_option){
    if ( $id_option = $this->_get_id_option($id_option) ){
      return $this->db->select_one($this->class_table, $this->fields['text'], [$this->fields['id'] => $id_option]);
    }
    return null;
  }





  public function items($code){
    if ( $items = $this->opt->items(\func_get_args()) ){
      $res = [];
      foreach ( $items as $i => $it ){
        $res[] = ['id' => $it, 'num' => $i + 1];
        if (
          ($tmp = $this->get($it)) &&
          (isset($tmp['num']))
        ){
          $res[$i]['num'] = $tmp['num'];
        }
      }
      \bbn\x::sort_by($res, 'num');
      return array_map(function($a){
        return $a['id'];
      }, $res);
    }
    return $items;
  }

  public function options($code): ?array
  {
    if ( $list = $this->items(\func_get_args()) ){
      $res = [];
      foreach ( $list as $i => $li ){
        $res[$i] = $this->opt->option($li);
        $res[$i]['items'] = $this->get($li);
      }
      return $res;
    }
    return null;
  }

  public function full_options($code): ?array
  {
    if ( $ops = $this->opt->full_options(\func_get_args()) ){
      foreach ( $ops as &$o ){
        $o['items'] = $this->get_all($o['id']);
      }
      return $ops;
    }
    return null;
  }

  /**
   * @todo What does it do???
   */
  public function order($id_option, int $index, bool $upd = false){
    $id_parent = $this->opt->get_id_parent($id_option);
    if ( ($id_parent !== false) && $this->opt->is_sortable($id_parent) ){
      $items = $this->items($id_parent);
      $res = [];
      $to_change = false;
      foreach ( $items as $i => $it ){
        $res[] = [
          $this->fields['id'] => $it,
          $this->fields['num'] => $i + 1
        ];
        if ( $cfg = $this->get($it) ){
          $res[$i] = \bbn\x::merge_arrays($res[$i], $cfg);
        }
        if ( $it === $id_option ){
          $to_change = $i;
        }
      }
      if ( $to_change !== false ){
        if ( $to_change > $index ){
          for ( $i = $index; $i < $to_change; $i++ ){
            $res[$i][$this->fields['num']]++;
          }
        }
        else if ( $to_change < $index ){
          for ( $i = $to_change + 1; $i <= $index; $i++ ){
            $res[$i][$this->fields['num']]--;
          }
        }
        $res[$to_change][$this->fields['num']] = $index + 1;
        foreach ( $res as $i => $r ){
          if ( $upd ){
            $this->update_by_option($r[$this->fields['id']], $r);
          }
          else {
            $this->add($r[$this->fields['id']], $r);
          }
        }
        \bbn\x::sort_by($res, $this->fields['num']);
        return $res;
      }
    }
  }

  /**
   * Sets the permission row for the current user by the option's ID
   *
   * @param string $id_option
   * @param array $cfg
   * @return int
   */
  public function set_by_option(string $id_option, array $cfg): int
  {
    if ( $id = $this->retrieve_user_ids($id_option, $this->id_user) ){
      return $this->set($id[0], $cfg);
    }
    return $this->_insert($id_option, $cfg);
  }

  /**
   * Sets the permission config for the current user by the preference's ID
   *
   * @param string $id
   * @param array $cfg
   * @return int
   */
  public function set(string $id, array $cfg = null): int
  {
    return $this->db->update($this->class_cfg['table'], [
      $this->fields['cfg'] => $cfg ? json_encode($this->get_cfg(false, $cfg)) : null
    ], [
      $this->fields['id'] => $id
    ]);
  }

  /**
   * Sets the permission row for the current user by the preference's ID
   *
   * @param string $id
   * @param array $cfg
   * @return int
   */
  public function update(string $id, array $cfg): int
  {
    return $this->db->update($this->class_cfg['table'], [
      $this->fields['text'] => $cfg[$this->fields['text']] ?? NULL,
      $this->fields['num'] => $cfg[$this->fields['num']] ?? NULL,
      $this->fields['id_link'] => $cfg[$this->fields['id_link']] ?? NULL,
      $this->fields['id_alias'] => $cfg[$this->fields['id_alias']] ?? NULL,
      $this->fields['id_user'] => $this->id_user,
      $this->fields['cfg'] => ($tmp = $this->get_cfg(false, $cfg)) ? json_encode($tmp) : NULL
    ], [
      $this->fields['id'] => $id
    ]);
  }

  public function update_by_option(string $id_option, array $cfg): int
  {
    if ( $id = $this->retrieve_user_ids($id_option, $this->id_user) ){
      return $this->update($id[0], $cfg);
    }
    return $this->_insert($id_option, $cfg);
  }

  /**
   * Adds a new preference for the given option for the current user.
   *
   * @param null|string $id_option
   * @param array $cfg
   * @return null|string
   */
  public function add(string $id_option = null, array $cfg): ?string
  {
    return (
      ($id_option = $this->_get_id_option($id_option)) &&
      $this->_insert($id_option, $cfg)
    ) ? $this->db->last_id() : null;
  }

  /**
   * Deletes the given permission
   *
   * @param $id
   * @return int|null
   */
  public function delete($id): ?int
  {
    return $this->db->delete($this->class_cfg['table'], [$this->fields['id'] => $id]);
  }

  /**
   * Deletes all the given or current user's permissions for the given option
   *
   * @param null|string $id_option
   * @param null|string $id_user
   * @return null|int
   */
  public function delete_user_option(string $id_option, string $id_user = null): ?int
  {
    if ( $id_option = $this->_get_id_option($id_option) ){
      return $this->db->delete($this->class_cfg['table'], [
        $this->fields['id_option'] => $id_option,
        $this->fields['id_user'] => $id_user ?: $this->id_user
      ]);
    }
    return null;
  }

  /**
   * Deletes all the given group's permissions for the given option
   *
   * @param null|string $id_option
   * @param string $id_group
   * @return int|null
   */
  public function delete_group_option(string $id_option, string $id_group): ?int
  {
    if ( $id_option = $this->_get_id_option($id_option) ){
      return $this->db->delete($this->class_cfg['table'], [
        $this->fields['id_option'] => $id_option,
        $this->fields['id_group'] => $id_group
      ]);
    }
    return null;
  }

  /**
   * Sets (or unsets) the cfg field of a given preference based on its ID
   *
   * @param string $id
   * @param null|array $cfg
   * @return int
   */
  public function set_cfg(string $id = null, array $cfg = null): int
  {
    if ( null !== $cfg ){
      $cfg = $this->get_cfg(null, $cfg);
      $config = json_encode($cfg);
    }
    else{
      $config = null;
    }
    return $this->db->update($this->class_cfg['table'], [
      $this->fields['cfg'] => $config
    ], [
      $this->fields['id'] => $id
    ]);
  }

  /**
   * Sets (or unsets) the text field of the given preference and returns the result of the executed query
   *
   * @param string $id
   * @param null|string $text
   * @return null|int
   */
  public function set_text(string $id, string $text = null): ?int
  {
    return $this->db->update($this->class_cfg['table'], [
      $this->fields['text'] => $text
    ], [
      $this->fields['id'] => $id
    ]);
  }

  /**
   * Sets (or unsets) the id_link field of the given preference and returns the result of the executed query
   *
   * @param string $id
   * @param string $id_link
   * @return null|int
   */
  public function set_link(string $id, string $id_link = null): ?int
  {
    return $this->db->update($this->class_cfg['table'], [
      $this->fields['id_link'] => $id_link
    ], [
      $this->fields['id'] => $id
    ]);
  }

  /**
   * Sets (or unsets) the id_link field of the given preference and returns the result of the executed query
   *
   * @param string $id_option
   * @param string $id_link
   * @return null|string The inserted or updated preference's ID
   */
  public function add_link(string $id_option, string $id_link): ?string
  {
    $id = $this->db->select_one($this->class_cfg['table'], $this->fields['id'], [
      $this->fields['id_user'] => $this->id_user,
      $this->fields['id_option'] => $id_option
    ]);
    if ( $id ){
      if ( $this->db->update($this->class_cfg['table'], [
        $this->fields['id_link'] => $id_link
      ], ['id' => $id]) ){
        return $id;
      }
    }
    else if ( $this->db->insert($this->class_cfg['table'], [
      $this->fields['id_user'] => $this->id_user,
      $this->fields['id_option'] => $id_option,
      $this->fields['id_link'] => $id_link
    ]) ){
      return $this->db->last_id();
    }
    return null;
  }

  /**
   * Returns an array
   *
   * @param string $id
   * @return array|null
   */
  public function get_shared(string $id): ?array
  {
    if ( bbn\str::is_uid($id) ){
      return $this->db->rselect_all($this->class_table, [
        $this->fields['id'],
        $this->fields['id_user'],
        $this->fields['id_group']
      ], [
        $this->fields['id_alias'] => $id
      ]);
    }
    return null;
  }

  /**
   * Makes (or unmakes) the given preference public.
   *
   * @param string $id
   * @param bool $cancel
   * @return int|null
   */
  public function make_public(string $id, bool $cancel = false): ?int
  {
    if ( $cfg = $this->get($id) ){
      return $this->db->update($this->class_table, ['public' => $cancel ? 0 : 1], [
        $this->fields['id'] => $id
      ]);
    }
    return null;
  }

  /**
   * Shares (or unshares) the given preference to the given group.
   *
   * @param string $id
   * @param string $id_group
   * @param bool $cancel
   * @return int|null
   */
  public function share_with_group(string $id, string $id_group, bool $cancel = false): ?int
  {
    if ( $cfg = $this->get($id) ){
      $id_share = $this->db->select_one($this->class_table, $this->fields['id'], [
        'id_alias' => $id,
        'id_group' => $id_group
      ]);
      if ( $cancel && $id_share ){
        return $this->db->delete($this->class_table, [$this->fields['id'] => $id_share]);
      }
      else if ( !$cancel && !$id_share ){
        return $this->db->insert($this->class_table, [
          'id_option' => $cfg['id_option'],
          'id_alias' => $id,
          'id_group' => $id_group
        ]);
      }
      return 0;
    }
    return null;
  }

  /**
   * Shares (or unshares) the given preference to the given user
   *
   * @param string $id
   * @param string $id_user
   * @param bool $cancel
   * @return int|null
   */
  public function share_with_user(string $id, string $id_user, bool $cancel = false): ?int
  {
    if ( $cfg = $this->get($id) ){
      $id_share = $this->db->select_one($this->class_table, $this->fields['id'], [
        'id_alias' => $id,
        'id_user' => $id_user
      ]);
      if ( $cancel && $id_share ){
        return $this->db->delete($this->class_table, [$this->fields['id'] => $id_share]);
      }
      else if ( !$cancel && !$id_share ){
        return $this->db->insert($this->class_table, [
          'id_option' => $cfg['id_option'],
          'id_alias' => $id,
          'id_user' => $id_user
        ]);
      }
      return 0;
    }
    return null;
  }

  /**
   * Adds a bit to a preference
   *
   * @param string $id_usr_opt The preference's ID
   * @param array $cfg The bit's values
   * @return string|null
   */
  public function add_bit(string $id_usr_opt, array $cfg): ?string
  {
    if (
      ($id_usr_opt = $this->_get_id_option($id_usr_opt)) &&
      ($c = $this->class_cfg['arch']['user_options_bits'])
    ){
      $to_cfg = $this->get_bit_cfg(null, $cfg);
      if ( isset($to_cfg['items']) ){
        unset($to_cfg['items']);
      }
      if ( !empty($to_cfg) ){
        if ( !empty($cfg[$c['cfg']]) ){
          if (  \bbn\str::is_json($cfg[$c['cfg']]) ){
            $cfg[$c['cfg']] = json_decode($cfg[$c['cfg']], true);
          }
          if ( \is_array($cfg[$c['cfg']]) ){
            $cfg[$c['cfg']] = array_merge($cfg[$c['cfg']], $to_cfg);
          }
          else {
            $cfg[$c['cfg']] = $to_cfg;
          }
        }
        else {
          $cfg[$c['cfg']] = $to_cfg;
        }
        $cfg[$c['cfg']] = json_encode($cfg[$c['cfg']]);
      }
      if ( $this->db->insert($this->class_cfg['tables']['user_options_bits'], [
        $c['id_user_option'] => $id_usr_opt,
        $c['id_parent'] => $cfg[$c['id_parent']] ?? NULL,
        $c['id_option'] => $cfg[$c['id_option']] ?? NULL,
        $c['num'] => $cfg[$c['num']] ?? NULL,
        $c['text'] => $cfg[$c['text']] ?? '',
        $c['cfg'] => $cfg[$c['cfg']] ?? '',
      ]) ){
        return $this->db->last_id();
      }
    }
    return null;
  }


  /**
   * Deletes a preference's bit
   *
   * @param string The bit's ID
   * @return int|null
   */
  public function delete_bit(string $id): ?int
  {
    if ( \bbn\str::is_uid($id) ){
      return $this->db->delete($this->class_cfg['tables']['user_options_bits'], [
        $this->class_cfg['arch']['user_options_bits']['id'] => $id
      ]);
    }
    return null;
  }

  /**
   * Updates a preference's bit
   *
   * @param string $id The bit's ID
   * @param array The bit's values
   * @return int|null
   */
  public function update_bit(string $id, array $cfg, $merge_config = false): ?int
  {
    if ( \bbn\str::is_uid($id) ){
      $c = $this->class_cfg['arch']['user_options_bits'];
      $fields = array_values($c);
      if ( \array_key_exists($c['id'], $cfg) ){
        unset($cfg[$c['id']]);
      }
      $to_cfg = $this->get_bit_cfg(null, $cfg);
      if ( isset($to_cfg['items']) ){
        unset($to_cfg['items']);
      }
      if ( !empty($to_cfg) ){
        if ( !empty($merge_config) && !empty($cfg[$c['cfg']]) ){
          if (  \bbn\str::is_json($cfg[$c['cfg']]) ){
            $cfg[$c['cfg']] = json_decode($cfg[$c['cfg']], true);
          }
          if ( \is_array($cfg[$c['cfg']]) ){
            $cfg[$c['cfg']] = array_merge($cfg[$c['cfg']], $to_cfg);
          }
          else {
            $cfg[$c['cfg']] = $to_cfg;
          }
        }
        else {
          $cfg[$c['cfg']] = $to_cfg;
        }
        $cfg[$c['cfg']] = json_encode($cfg[$c['cfg']]);
      }
      return $this->db->update($this->class_cfg['tables']['user_options_bits'], [
        $c['id_parent'] => $cfg[$c['id_parent']] ?? NULL,
        $c['id_option'] => $cfg[$c['id_option']] ?? NULL,
        $c['num'] => $cfg[$c['num']] ?? NULL,
        $c['text'] => $cfg[$c['text']] ?? '',
        $c['cfg'] => $cfg[$c['cfg']] ?? '',
      ], [
        $c['id'] => $id
      ]);
    }
    return null;
  }

  /**
   * Returns a single preference's bit
   *
   * @param string $id The bit's ID
   * @return array
   */
  public function get_bit(string $id, bool $with_config = true): array
  {
    if (
      \bbn\str::is_uid($id) &&
      ($bit = $this->db->rselect($this->class_cfg['tables']['user_options_bits'], [], [
        $this->class_cfg['arch']['user_options_bits']['id'] => $id
      ]))
    ){
      if ( !empty($with_config) ){
        return $this->explode_bit_cfg($bit);
      }
      return $bit;
    }
    return [];
  }

  /**
   * Returns the bits list of a preference
   *
   * @param string $id The preference's ID
   * @param null|string $id_parent The bits'parent ID
   * @return array
   */
  public function get_bits(string $id_usr_opt, $id_parent = false, bool $with_config = true): array
  {
    $c = $this->class_cfg['arch']['user_options_bits'];
    $t = $this;
    $where = [
      $c['id_user_option'] => $id_usr_opt
    ];
    if ( is_null($id_parent) || \bbn\str::is_uid($id_parent) ){
      $where[$c['id_parent']] = $id_parent;
    }
    if (
      \bbn\str::is_uid($id_usr_opt) &&
      ($bits = $this->db->rselect_all($this->class_cfg['tables']['user_options_bits'], [], $where, [$c['num'] => 'ASC']))
    ){
      if ( !empty($with_config) ){
        return array_map(function($b) use($t){
          return $t->explode_bit_cfg($b);
        }, $bits);
      }
      return $bits;
    }
    return [];
  }

  /**
   * Returns the hierarchical bits list of a preference
   *
   * @param string $id_usr_opt The preference's ID
   * @param string $id_parent The parent's ID of a bit. Default: null
   * @param bool $with_config Set it to false if you don't want the preference's cfg field values on the results.
   * @return array
   */
  public function get_full_bits(string $id_usr_opt, string $id_parent = null, bool $with_config = true): array
  {
    if ( \bbn\str::is_uid($id_usr_opt) ){
      $c = $this->class_cfg['arch']['user_options_bits'];
      $t = $this;
      return array_map(function($b) use($t, $c, $id_usr_opt, $with_config){
        if ( !empty($with_config) ){
          $b = $t->explode_bit_cfg($b);
        }
        $b['items'] = $t->get_full_bits($id_usr_opt, $b[$c['id']], $with_config);
        return $b;
      }, $this->db->rselect_all([
        'table' => $this->class_cfg['tables']['user_options_bits'],
        'fields' => [],
        'where' => [
          'conditions' => [[
            'field' => $c['id_user_option'],
            'value' => $id_usr_opt
          ], [
            'field' => $c['id_parent'],
            empty($id_parent) ? 'operator' : 'value' => $id_parent ?: 'isnull'
          ]]
        ],
        'order' => [$c['num'] => 'ASC']
      ]));
    }
    return [];
  }

  /**
   * Returns a preference and its hierarchical bits list
   *
   * @param string $id The preference's ID
   * @param bool $with_config Set it to false if you don't want the preference's cfg field values on the results.
   */
  public function get_tree(string $id, bool $with_config = true): array
  {
    if (
      \bbn\str::is_uid($id) &&
      ($p = $this->get($id, $with_config))
    ){
      $p['items'] = $this->get_full_bits($id, null, $with_config);
      return $p;
    }
    return [];
  }

  public function explode_bit_cfg($bit): array
  {
    $c = $this->class_cfg['arch']['user_options_bits'];
    if (
      !empty($bit[$c['cfg']]) &&
      ($cfg = json_decode($bit[$c['cfg']], true))
    ){
      foreach ( $cfg as $i => $v ){
        if ( !array_key_exists($i, $bit) ){
          $bit[$i] = $v;
        }
      }
    }
    unset($bit[$c['cfg']]);
    return $bit;
  }

  public function next_bit_num(string $id): ?int
  {
    if (
      \bbn\str::is_uid($id) &&
      ($max = $this->db->select_one(
        $this->class_cfg['tables']['user_options_bits'],
        'MAX(num)',
        [$this->class_cfg['arch']['user_options_bits']['id_user_option'] => $id]
      ))
    ){
      return $max+1;
    }
    return null;
  }

  /**
   * Gets the bit's cfg array, normalized either from the DB or from the $cfg argument
   *
   * @param string $id
   * @param null|array $cfg
   * @return null|array
   */

  public function get_bit_cfg(string $id = null, array $cfg = null): ?array
  {
    if (
      (null !== $cfg) ||
      ($cfg = $this->db->select_one(
        $this->class_cfg['tables']['user_options_bits'],
        $this->class_cfg['arch']['user_options_bits']['cfg'],
        [$this->class_cfg['arch']['user_options_bits']['id'] => $id ]
      ))
    ){
      $fields = array_values($this->class_cfg['arch']['user_options_bits']);
      if ( bbn\str::is_json($cfg) ){
        $cfg = json_decode($cfg, 1);
      }
      if ( \is_array($cfg) ){
        $new = [];
        foreach ( $cfg as $k => $v){
          if ( !\in_array($k, $fields, true) ){
            $new[$k] = $v;
          }
        }
        return $new;
      }
    }
    return null;
  }

  /**
   * Orders a bit.
   * 
   * @param string $id The bit's ID
   * @param int $pos The new position
   * @return bool|null
   */
  public function order_bit(string $id, int $pos): ?bool
  {
    if ( 
      \bbn\str::is_uid($id) &&
      ($cf = $this->get_class_cfg()) &&
      ($cfg = $cf['arch']['user_options_bits']) &&
      ($bit = $this->get_bit($id)) &&
      ($old = (int)$bit[$cfg['num']]) &&
      !empty($pos) &&
      ($old !== $pos) &&
      ($bits = $this->get_bits($bit[$cfg['id_user_option']], $bit[$cfg['id_parent']] ?: false))
    ){    
      $past_new = false;
      $past_old = false;
      $p = 1;
      $changed = 0;
      foreach ( $bits as $ele ){
        $upd = [];
        if ( $past_old && !$past_new ){
          $upd[$cfg['num']] = $p-1;
        }
        else if ( !$past_old && $past_new ){
          $upd[$cfg['num']] = $p+1;
        }
        if ( $id === $ele['id'] ){
          $upd[$cfg['num']] = $pos;
          $past_old = 1;
        }
        else if ( $p === $pos ){
          $upd[$cfg['num']] = $p + ($pos > $old ? -1 : 1);
          $past_new = 1;
        }
        if ( !empty($upd) ){
          $changed += $this->db->update($cf['tables']['user_options_bits'], $upd, [$cfg['id'] => $ele['id']]);
        }
        if ( $past_new && $past_old ){
          break;
        }
        $p++;
      }
      return !!$changed;
    }
    return null;
  }

  /**
   * Moves a bit.
   * 
   * @param string $id The bit's ID
   * @param string|null The new parent's ID
   * @return bool|null
   */
  public function move_bit(string $id, string $id_parent = null): ?bool
  { 
    if ( 
      \bbn\str::is_uid($id) && 
      (
        (\bbn\str::is_uid($id_parent) && $this->get_bit($id_parent)) || 
        \is_null($id_parent)
      ) &&
      ($bit = $this->get_bit($id)) &&
      ($cf = $this->get_class_cfg()) &&
      ($cfg =  $cf['arch']['user_options_bits'])
    ){
      $upd = [
        $cfg['id_parent'] => $id_parent,
        $cfg['num'] => $this->get_max_bit_num($bit[$cfg['id_user_option']], $id_parent, true)
      ];
      return !!$this->db->update($cf['tables']['user_options_bits'], $upd, [$cfg['id'] => $id]);
    }
    return null;
  }

  /**
   * Gets the maximum num value of the user option's bits.
   * 
   * @param string $id_user_option The user option's ID
   * @param string|null $id_parent The parent's ID
   * @param bool $incr Set it to true if you want the result increased by 1
   * @return int 
   */
  public function get_max_bit_num(string $id_user_option, string $id_parent = null, bool $incr = false): int
  {
    if ( 
      \bbn\str::is_uid($id_user_option) &&
      (\bbn\str::is_uid($id_parent) || is_null($id_parent)) &&
      ($cf = $this->get_class_cfg()) &&
      ($cfg =  $cf['arch']['user_options_bits'])
    ){
      if ( $max = $this->db->select_one([
        'table' => $cf['tables']['user_options_bits'], 
        'fields' => ["MAX($cfg[num])"],
        'where' => [
          'conditions' => [[
            'field' => $cfg['id_user_option'],
            'value' => $id_user_option
          ], [
            'field' => $cfg['id_parent'],
            empty($id_parent) ? 'operator' : 'value' => $id_parent ?: 'isnull'
          ]]
        ]
      ]) ){
        $max = (int)$max;
        return $incr ? $max+1 : $max;
      }
      return 0;
    }
  }




  /**
   *  Gets a preference row from a bit ID
   *
   * @param string $id The bit's ID
   * @return array
   */
  public function get_by_bit(string $id): ?array
  {
    $t =& $this;
    if ( \bbn\str::is_uid($id) ){
      return $this->db->rselect([
        'table' => $this->class_cfg['table'],
        'fields' => array_map(function($v) use($t){
          return $this->class_cfg['table'].'.'.$v;
        }, array_values($this->class_cfg['arch']['user_options'])),
        'join' => [[
          'table' => $this->class_cfg['tables']['user_options_bits'],
          'on' => [
            'conditions' => [[
              'field' => $this->class_cfg['arch']['user_options_bits']['id_user_option'],
              'exp' => $this->class_cfg['table'].'.'.$this->fields['id']
            ]]
          ]
        ]],
        'where' => [
          $this->class_cfg['tables']['user_options_bits'].'.'.$this->class_cfg['arch']['user_options_bits']['id'] => $id
        ]
      ]);
    }
  }

  /**
   * Gets the preference's ID from a bit ID
   *
   * @param string $id The bit's ID
   * @return string
   */
  public function get_id_by_bit(string $id): ?string
  {
    if ( \bbn\str::is_uid($id) && ($p = $this->get_by_bit($id)) ){
      return $p[$this->fields['id']];
    }
    return null;
  }

  public function text_value(string $id_option, $id_user = null, $id_group = null):? array
  {
    if ( \bbn\str::is_uid($id_option) ){
      $res = [];
      if ($ids = $this->_retrieve_ids($id_option, $id_user, $id_group)){
        foreach ($ids as $id){
          $res[] = $this->db->rselect($this->class_cfg['table'], [
            'value' => $this->fields['id'],
            'text' => $this->fields['text']
          ], ['id' => $id]);
        }
      }
      return $res;
    }
    return null;
  }
}
