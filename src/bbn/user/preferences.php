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
        'user_options' => 'bbn_users_options'
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
      $id_option = \call_user_func_array([$this->opt, 'from_path'], \func_get_args());
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
		$json = ($tmp = $this->get_cfg(false, $cfg)) ? json_encode($json) : null;

    return $this->db->insert($this->class_cfg['table'], [
      'id_option' => $id_option,
      'text' => $cfg['text'] ?? null,
      'id_link' => $cfg['id_link'] ?? null,
      'id_alias' => $cfg['id_alias'] ?? null,
      'id_user' => $this->id_user,
      'cfg' => $json
    ]);
  }

  /**
   * Returns preferences' IDs from the option's ID
   *
   * @param null|string $id_option
   * @param null|string $id_user
   * @param null|string $id_group
   * @return array|null
   */
  private function _retrieve_ids(string $id_option = null, string $id_user = null, string $id_group = null): ?array
  {
    if ( ($id_user || $id_group) && ($id_option = $this->_get_id_option($id_option)) ){
      $col_id = $this->db->csn($this->fields['id'], true);
      $table = $this->db->tsn($this->class_cfg['table'], true);
      $id_opt = $this->db->csn($this->fields['id_option'], true);
      $num = $this->db->csn($this->fields['num'], true);
      $text = $this->db->csn($this->fields['text'], true);
      $user = $this->db->csn($this->fields['id_user'], true);
      $group = $this->db->csn($this->fields['id_group'], true);
      $public = $this->db->csn($this->fields['public'], true);
      $cond = [];
      $args = [$id_option];
      if ( null !== $id_user ){
        $cond[] = "$user = UNHEX(?)";
        $args[] = $id_user;
      }
      if ( null !== $id_group ){
        $cond[] = "$group = UNHEX(?)";
        $args[] = $id_group;
      }
      // Not specific
      if ( (null !== $id_user) && (null !== $id_group) ){
        $cond[] = "$public = 1";
      }
      $cond = implode(' OR ', $cond);
      $sql = <<< MYSQL
SELECT $col_id
FROM $table
WHERE $id_opt = UNHEX(?)
AND ($cond)
ORDER BY IFNULL($num, $text)
MYSQL;
      array_unshift($args, $sql);
      return \call_user_func_array([$this->db, 'get_col_array'], $args);
    }
    return null;
  }

  /**
   * Gets the preferences which have the option's $id as id_link
   *
   * @param string $id_link
   * @return array|null
   */
  private function _get_links(string $id_lnk, string $id_user = null, string $id_group = null): ?array
  {
    if ( $id_lnk = $this->_get_id_option($id_lnk) ){
      $col_id = $this->db->csn($this->fields['id'], true);
      $table = $this->db->tsn($this->class_cfg['table'], true);
      $id_opt = $this->db->csn($this->fields['id_option'], true);
      $id_link = $this->db->csn($this->fields['id_link'], true);
      $text = $this->db->csn($this->fields['text'], true);
      $user = $this->db->csn($this->fields['id_user'], true);
      $group = $this->db->csn($this->fields['id_group'], true);
      $public = $this->db->csn($this->fields['public'], true);
      $cond = [];
      $args = [$id_lnk];
      if ( null !== $id_user ){
        $cond[] = "$user = UNHEX(?)";
        $args[] = $id_user;
      }
      if ( null !== $id_group ){
        $cond[] = "$group = UNHEX(?)";
        $args[] = $id_group;
      }
      // Not specific
      if ( (null !== $id_user) && (null !== $id_group) ){
        $cond[] = "$public = 1";
      }
      $cond = implode(' OR ', $cond);
      $sql = <<< MYSQL
SELECT $col_id, $id_opt
FROM $table
WHERE $id_link = UNHEX(?)
AND ($cond)
ORDER BY $text
MYSQL;
      array_unshift($args, $sql);
      return \call_user_func_array([$this->db, 'get_rows'], $args);
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
  public function retrieve_user_ids(string $id_option = null, string $id_user): ?array
  {
    return $this->_retrieve_ids($id_option, $id_user);
  }

  /**
   * Returns preferences' IDs from the option's ID and the given group ID
   *
   * @param null|string $id_option
   * @param string $id_group
   * @return array|null
   */
  public function retrieve_group_ids(string $id_option = null, string $id_group): ?array
  {
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
    if ( !$force && $this->user->is_admin() ){
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
   * Returns the current user's preference based on the given id_option, his own profile and his group's
   * @param string $id
   * @param bool $with_config
   * @return array|null
   */
  public function get(string $id, bool $with_config = true): ?array
  {
    if ( bbn\str::is_uid($id) ){
      $cols = implode(', ', array_map(function($a){
        return $a;
      }, $this->fields));
      $table = $this->db->tsn($this->class_cfg['table'], true);
      $uid = $this->db->csn($this->fields['id'], true);
      $id_user = $this->db->csn($this->fields['id_user'], true);
      $id_group = $this->db->csn($this->fields['id_group'], true);
      $public = $this->db->csn($this->fields['public'], true);
      $sql = <<< MYSQL
SELECT $cols
FROM $table
WHERE $uid = UNHEX(?)
AND ($id_user = UNHEX(?)
OR $id_group = UNHEX(?)
OR $public = 1)
MYSQL;
      if ( $row = $this->db->get_row($sql, $id, $this->id_user, $this->id_group) ){
        $cfg = $row['cfg'];
        unset($row['cfg']);
        if ( $cfg && $with_config ){
          $row = bbn\x::merge_arrays(json_decode($cfg, true), $row);
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
      $fields = $this->fields;
      if ( !$with_config ){
        unset($fields['cfg']);
      }
      $table = $this->db->tsn($this->class_table, true);
      $cols = implode(', ', array_map(function($a) use($table){
        return "IFNULL(aliases.$a, $table.$a) AS $a";
      }, $fields));
      $id_opt = $this->db->cfn($this->fields['id_option'], $this->class_table, true);
      $id_user = $this->db->cfn($this->fields['id_user'], $this->class_table, true);
      $id_group = $this->db->cfn($this->fields['id_group'], $this->class_table, true);
      $num = $this->db->cfn($this->fields['num'], $this->class_table, true);
      $text = $this->db->cfn($this->fields['text'], $this->class_table, true);
      $id_alias = $this->db->cfn($this->fields['id_alias'], $this->class_table, true);
      $public = $this->db->cfn($this->fields['public'], $this->class_table, true);
      $sql = <<< MYSQL
SELECT $cols
FROM $table
  LEFT JOIN $table as aliases
    ON aliases.id = $id_alias
WHERE $id_opt = UNHEX(?)
AND ($id_user = UNHEX(?)
OR $id_group = UNHEX(?)
OR $public = 1)
ORDER BY IFNULL($num, $text)
MYSQL;
      if ( $rows =  $this->db->get_rows($sql, $id_option, $this->id_user, $this->id_group) ){
        return $with_config ? array_map(function($a){
          $cfg = $a['cfg'];
          unset($a['cfg']);
          if ( $cfg ){
            $a = bbn\x::merge_arrays(json_decode($cfg, true), $a);
          }
          return $a;
        }, $rows) : $rows;
      }
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
  public function order($id_option, int $index){
    $id_parent = $this->opt->get_id_parent($id_option);
    if ( ($id_parent !== false) && $this->opt->is_sortable($id_parent) ){
      $items = $this->items($id_parent);
      $res = [];
      $to_change = false;
      foreach ( $items as $i => $it ){
        $res[] = [
          'id' => $it,
          'num' => $i + 1
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
            $res[$i]['num']++;
          }
        }
        else if ( $to_change < $index ){
          for ( $i = $to_change + 1; $i <= $index; $i++ ){
            $res[$i]['num']--;
          }
        }
        $res[$to_change]['num'] = $index + 1;
        foreach ( $res as $i => $r ){
          $this->add($r['id'], $r);
        }
        \bbn\x::sort_by($res, 'num');
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
    if ( $id = $this->retrieve_ids($id_option) ){
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
      'cfg' => $cfg ? json_encode($this->get_cfg(false, $cfg)) : null
    ], [
      'id' => $id
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
      'text' => $cfg['text'] ?? null,
      'id_link' => $cfg['id_link'] ?? null,
      'id_alias' => $cfg['id_alias'] ?? null,
      'id_user' => $this->id_user,
      'cfg' => json_encode($this->get_cfg(false, $cfg))
    ], [
      'id' => $id
    ]);
  }

  /**
   * Adds a new preference for the given option for the current user.
   *
   * @param null|string $id_option
   * @param array $cfg
   * @return null|int
   */
  public function add(string $id_option = null, array $cfg): ?int
  {
    return ($id_option = $this->_get_id_option($id_option)) ?
      $this->_insert($id_option, $cfg) :
      null;
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
      return $this->db->rselect_all($this->class_table, [$this->fields['id'], $this->fields['id_user'],
        $this->fields['id_group']], [
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
}
