<?php
/**
 *
 * @package user
 */
namespace bbn\User;

use bbn;
use bbn\X;

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

class Preferences extends bbn\Models\Cls\Db
{
  use bbn\Models\Tts\Retriever;
  use bbn\Models\Tts\Dbconfig;
  use bbn\Models\Tts\Optional;
  use bbn\Models\Tts\Current;

  /** @var array */
  protected static $default_class_cfg = [
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

  /** @var bbn\Appui\Option */
  protected $opt;

  /** @var bbn\User */
  protected $user;

  /** @var int */
  protected $id_user;

  /** @var int */
  protected $id_group;


  /**
   * @return preferences|null
   */
  public static function getPreferences(): ?preferences
  {
    return self::getInstance();
  }


  /**
   * preferences constructor.
   * @param bbn\Db $db
   * @param array  $cfg
   */
  public function __construct(bbn\Db $db, array $cfg = [])
  {
      parent::__construct($db);
    $this->_init_class_cfg($cfg);
    if ($user = bbn\User::getInstance()) {
      $this->_init_user($user);
    }

    $this->opt = bbn\Appui\Option::getInstance();
    if ($this->user && $this->opt) {
      self::retrieverInit($this);
    }
  }


  /**
   * @return array
   */
  public function getClassCfg(): array
  {
    return $this->class_cfg;
  }


  /**
   * Returns preferences' IDs from the option's ID
   *
   * @param null|string $id_option
   * @return null|array
   */
  public function retrieveIds(string $id_option = null): ?array
  {
    return $this->_retrieve_ids($id_option, $this->id_user, $this->id_group);
  }


  /**
   * Returns preferences' IDs from the option's ID and the given user ID
   *
   * @param null|string $id_option
   * @param string      $id_user
   * @return array|null
   */
  public function retrieveUserIds(string $id_option = null, string $id_user = null): ?array
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
   * @param string      $id_group
   * @return array|null
   */
  public function retrieveGroupIds(string $id_option = null, string $id_group = null): ?array
  {
    if (!$id_group) {
      $id_group = $this->id_group;
    }

    return $this->_retrieve_ids($id_option, null, $id_group);
  }


  /**
   * Checks if the given user or the current user is authorized to access a user_option.
   *
   * @param string $id_user_option
   *
   * @return bool
   */
  public function isAuthorized(string $id_user_option)
  {
    return (bool)$this->get($id_user_option, false);
  }


  /**
   * Returns true if the current user can access a preference, false otherwise
   *
   * @param string|null $id_option
   * @param bool        $force
   * @return bool
   */
  public function has(string $id_option = null, bool $force = false): bool
  {
    if (!$force && $this->user->isDev()) {
      return true;
    }

    return (bool)$this->retrieveIds($id_option);
  }


  /**
   * Checks if a user has the given preference
   *
   * @param string $id_option
   * @param string $id_user
   * @return bool
   */
  public function userHas(string $id_option, string $id_user = null): bool
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
  public function groupHas(string $id_option, string $id_group): bool
  {
    return (bool)$this->_retrieve_ids($id_option, null, $id_group);
  }


  /**
   * @return null|string
   */
  public function getUser(): ?string
  {
    return $this->id_user;
  }


  /**
   * @return null|string
   */
  public function getGroup(): ?string
  {
    return $this->id_group;
  }


  /**
   * @param bbn\User $user
   * @return preferences
   */
  public function setUser(bbn\User $user): preferences
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
  public function setGroup(string $id_group): preferences
  {
    if (\bbn\Str::isUid($id_group)) {
      $this->id_group = $id_group;
    }

    return $this;
  }


  /**
   * Gets the cfg array, normalized either from the DB or from the $cfg argument
   *
   * @param string     $id
   * @param null|array $cfg
   * @return null|array
   */
  public function getCfg(string $id = null, array $cfg = null): ?array
  {
    if ((null !== $cfg)
        || ($cfg = $this->db->selectOne(
          $this->class_cfg['table'],
          $this->fields['cfg'],
          [$this->fields['id'] => $id ]
        ))
    ) {
      if (bbn\Str::isJson($cfg)) {
        $cfg = json_decode($cfg, 1);
      }

      if (\is_array($cfg)) {
        $new = [];
        foreach ($cfg as $k => $v){
          if (!\in_array($k, $this->fields, true)) {
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
   * @param string     $id
   * @param null|array $cfg
   * @return null|array
   */
  public function getCfgByOption(string $id_option, string $id_user = null): ?array
  {
    if (($cfg = $this->db->selectOne(
      $this->class_cfg['table'],
      $this->fields['cfg'],
      [
          $this->fields['id_option'] => $id_option,
          $this->fields['id_user'] => $id_user ?: $this->id_user,
        ]
    ))
    ) {
      if (bbn\Str::isJson($cfg)) {
        $cfg = json_decode($cfg, 1);
      }

      return $this->getCfg(false, $cfg);
    }

    return null;
  }


  /**
   * Gets the preferences which have the option's $id as id_link
   *
   * @param string $id
   * @return array|null
   */
  public function getLinks(string $id): ?array
  {
    return $this->_get_links($id, $this->id_user, $this->id_group);
  }


  /**
   * Returns the current user's preference based on the given id, his own profile and his group
   * @param string $id
   * @param bool   $with_config
   * @return array|null
   */
  public function get(string $id, bool $with_config = true): ?array
  {
    if (bbn\Str::isUid($id)) {
      $table    = $this->db->tsn($this->class_cfg['table'], true);
      $uid      = $this->db->csn($this->fields['id'], true);
      $id_user  = $this->db->csn($this->fields['id_user'], true);
      $id_group = $this->db->csn($this->fields['id_group'], true);
      $public   = $this->db->csn($this->fields['public'], true);
      if ($row = $this->db->rselect(
        [
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
        ]
      )
      ) {
        if ($with_config) {
          if (empty($row['cfg']) && !empty($row['id_alias'])) {
            //if it's the case of a shared list takes the $cfg and the text from the alias
            $alias       = $this->db->rselect(
              [
              'table' => $table,
              'fields' => ['cfg', 'text'],
              'where' => [
                'conditions' => [[
                  'field' => 'id',
                  'value' => $row['id_alias']
                ]]
              ]
              ]
            );
            $row['cfg']  = $alias['cfg'];
            $row['text'] = $alias['text'];
          }

          $cfg = $row[$this->fields['cfg']];
          unset($row[$this->fields['cfg']]);
          if ($cfg = json_decode($cfg, true)) {
            $row = bbn\X::mergeArrays($cfg, $row);
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
   * @param bool        $with_config
   * @return array|null
   */
  public function getAll(string $id_option = null, bool $with_config = true): ?array
  {
    if ($id_option = $this->_get_id_option($id_option)) {
      $farch  = $this->fields;
      $fields = [];
      foreach ($farch as $k => $f){
        $field = $this->class_table . '.' . $f;
        if ($k === 'cfg') {
          $fields[$farch['cfg']] = "IFNULL($field, aliases.$farch[cfg])";
        }
        elseif ($k === 'text') {
          $fields[$farch['text']] = "IFNULL($field, aliases.$farch[text])";
        }
        else {
          $fields[] = $field;
        }
      }

      if ($rows = $this->db->rselectAll(
        [
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
          'conditions' => [
            [
              'field' => $farch['id_option'],
              'value' => $id_option
            ], [
              'logic' => 'OR',
              'conditions' => [
                [
                  'field' => $farch['id_user'],
                  'value' => $this->id_user
                ], [
                  'field' => $farch['id_group'],
                  'value' => $this->id_group
                ], [
                  'field' => $farch['public'],
                  'value' => 1
                ]
              ]
            ]
          ]
        ]
        ]
      )
      ) {
        return $with_config ? array_map(
          function ($a) use ($farch) {
            $cfg = $a[$farch['cfg']];
            unset($a[$farch['cfg']]);
            if ($cfg = json_decode($cfg, true)) {
              $a = bbn\X::mergeArrays($cfg, $a);
            }

            return $a;
          }, $rows
        ) : $rows;
      }

      return [];
    }

    return null;
  }


  /**
   * Returns an array of the users' preferences (the current user and group are excluded) based on the given id_option
   * @param null|string $id_option
   * @param bool        $with_config
   * @return array|null
   */
  public function getAllNotMine(string $id_option = null, bool $with_config = true): ?array
  {
    if ($id_option = $this->_get_id_option($id_option)) {
      $fields = $this->fields;
      if (!$with_config) {
        unset($fields['cfg']);
      }

      if ($rows = $this->db->rselectAll(
        [
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
        ]
      )
      ) {
        return $with_config ? array_map(
          function ($a) {
            $cfg = $a['cfg'];
            unset($a['cfg']);
            if (($cfg = json_decode($cfg, true))) {
              $a = bbn\X::mergeArrays($cfg, $a);
            }

            return $a;
          }, $rows
        ) : $rows;
      }

      return [];
    }

    return null;
  }


  public function getByOption(string $id_option, bool $with_config = true): ?array
  {
    if ($id = $this->retrieveUserIds($id_option, $this->id_user)) {
      return $this->get($id[0], $with_config);
    }

    return null;
  }


  public function option(): ?array
  {
    if ($o = $this->opt->option(\func_get_args())) {
      if (($ids = $this->retrieveIds($o['id'])) && ($cfg = $this->get($ids[0]))) {
        $o = bbn\X::mergeArrays($o, $cfg);
      }

      return $o;
    }

    return null;
  }


  public function text(string $id_option)
  {
    if ($id_option = $this->_get_id_option($id_option)) {
      return $this->db->selectOne($this->class_table, $this->fields['text'], [$this->fields['id'] => $id_option]);
    }

    return null;
  }


  public function items($code)
  {
    if ($items = $this->opt->items(\func_get_args())) {
      $res = [];
      foreach ($items as $i => $it){
        $res[] = ['id' => $it, 'num' => $i + 1];
        if (($tmp = $this->get($it))
            && (isset($tmp['num']))
        ) {
          $res[$i]['num'] = $tmp['num'];
        }
      }

      \bbn\X::sortBy($res, 'num');
      return array_map(
        function ($a) {
          return $a['id'];
        }, $res
      );
    }

    return $items;
  }


  public function options($code): ?array
  {
    if ($list = $this->items(\func_get_args())) {
      $res = [];
      foreach ($list as $i => $li){
        $res[$i]          = $this->opt->option($li);
        $res[$i]['items'] = $this->get($li);
      }

      return $res;
    }

    return null;
  }


  public function fullOptions($code): ?array
  {
    if ($ops = $this->opt->fullOptions(\func_get_args())) {
      foreach ($ops as &$o){
        $o['items'] = $this->getAll($o['id']);
      }

      return $ops;
    }

    return null;
  }


  /**
   * @todo What does it do???
   */
  public function order($id_option, int $index, bool $upd = false)
  {
    $id_parent = $this->opt->getIdParent($id_option);
    if (($id_parent !== false) && $this->opt->isSortable($id_parent)) {
      $items     = $this->items($id_parent);
      $res       = [];
      $to_change = false;
      foreach ($items as $i => $it){
        $res[] = [
          $this->fields['id'] => $it,
          $this->fields['num'] => $i + 1
        ];
        if ($cfg = $this->get($it)) {
          $res[$i] = \bbn\X::mergeArrays($res[$i], $cfg);
        }

        if ($it === $id_option) {
          $to_change = $i;
        }
      }

      if ($to_change !== false) {
        if ($to_change > $index) {
          for ($i = $index; $i < $to_change; $i++){
            $res[$i][$this->fields['num']]++;
          }
        }
        elseif ($to_change < $index) {
          for ($i = $to_change + 1; $i <= $index; $i++){
            $res[$i][$this->fields['num']]--;
          }
        }

        $res[$to_change][$this->fields['num']] = $index + 1;
        foreach ($res as $i => $r){
          if ($upd) {
            $this->updateByOption($r[$this->fields['id']], $r);
          }
          else {
            $this->add($r[$this->fields['id']], $r);
          }
        }

        \bbn\X::sortBy($res, $this->fields['num']);
        return $res;
      }
    }
  }


  /**
   * Sets the permission row for the current user by the option's ID
   *
   * @param string $id_option
   * @param array  $cfg
   * @return int
   */
  public function setByOption(string $id_option, array $cfg): int
  {
    if ($id = $this->retrieveUserIds($id_option, $this->id_user)) {
      return $this->set($id[0], $cfg);
    }

    return $this->_insert($id_option, $cfg);
  }


  /**
   * Sets the permission config for the current user by the preference's ID
   *
   * @param string $id
   * @param array  $cfg
   * @return int
   */
  public function set(string $id, array $cfg = null): int
  {
    return $this->db->update(
      $this->class_cfg['table'], [
      $this->fields['cfg'] => $cfg ? json_encode($this->getCfg(false, $cfg)) : null
      ], [
      $this->fields['id'] => $id
      ]
    );
  }


  /**
   * Sets the permission row for the current user by the preference's ID
   *
   * @param string $id
   * @param array  $cfg
   * @return int
   */
  public function update(string $id, array $cfg): int
  {
    return $this->db->update(
      $this->class_cfg['table'], [
      $this->fields['text'] => $cfg[$this->fields['text']] ?? null,
      $this->fields['num'] => $cfg[$this->fields['num']] ?? null,
      $this->fields['id_link'] => $cfg[$this->fields['id_link']] ?? null,
      $this->fields['id_alias'] => $cfg[$this->fields['id_alias']] ?? null,
      $this->fields['id_user'] => $this->id_user,
      $this->fields['cfg'] => ($tmp = $this->getCfg(false, $cfg)) ? json_encode($tmp) : null
      ], [
      $this->fields['id'] => $id
      ]
    );
  }


  public function updateByOption(string $id_option, array $cfg): int
  {
    if ($id = $this->retrieveUserIds($id_option, $this->id_user)) {
      return $this->update($id[0], $cfg);
    }

    return $this->_insert($id_option, $cfg);
  }


  /**
   * Adds a new preference for the given option for the current user.
   *
   * @param null|string $id_option
   * @param array       $cfg
   * @return null|string
   */
  public function add(string $id_option = null, array $cfg): ?string
  {
    if (($id_option = $this->_get_id_option($id_option))
        && !$this->retrieveUserIds($id_option)
        && $this->_insert($id_option, $cfg)
    ) {
      return $this->db->lastId();
    }
    return null;
  }


  /**
   * Adds a new preference for the given option for the current user.
   *
   * @param null|string $id_option
   * @param array       $cfg
   * @return null|string
   */
  public function addToGroup(string $id_option = null, array $cfg): ?string
  {
    if (($id_option = $this->_get_id_option($id_option))
        && $this->_insert($id_option, $cfg)
    ) {
      return $this->db->lastId();
    }

    return null;
  }


  /**
   * Deletes the given preference
   *
   * @param $id
   * @return int|null
   */
  public function delete($id): ?int
  {
    return $this->db->delete(
      [
      'table' => $this->class_cfg['table'],
      'where' => [
        'logic' => 'AND',
        'conditions' => [
          [
            'field' => $this->fields['id'],
            'value' => $id
          ],
          [
            'logic' => 'OR',
            'conditions' => [
              [
                'field' => $this->fields['id_user'],
                'value' => $this->id_user
              ], [
                'field' => $this->fields['id_group'],
                'value' => $this->id_group
              ], [
                'field' => $this->fields['public'],
                'value' => 1
              ]
            ]
          ]
        ]
      ]
      ]
    );
  }


  /**
   * Deletes all the given or current user's permissions for the given option
   *
   * @param null|string $id_option
   * @param null|string $id_user
   * @return null|int
   */
  public function deleteUserOption(string $id_option, string $id_user = null): ?int
  {
    if ($id_option = $this->_get_id_option($id_option)) {
      return $this->db->delete(
        $this->class_cfg['table'], [
        $this->fields['id_option'] => $id_option,
        $this->fields['id_user'] => $id_user ?: $this->id_user
        ]
      );
    }

    return null;
  }


  /**
   * Deletes all the given group's permissions for the given option
   *
   * @param null|string $id_option
   * @param string      $id_group
   * @return int|null
   */
  public function deleteGroupOption(string $id_option, string $id_group): ?int
  {
    if ($id_option = $this->_get_id_option($id_option)) {
      return $this->db->delete(
        $this->class_cfg['table'], [
        $this->fields['id_option'] => $id_option,
        $this->fields['id_group'] => $id_group
        ]
      );
    }

    return null;
  }


  /**
   * Sets (or unsets) the cfg field of a given preference based on its ID
   *
   * @param string     $id
   * @param null|array $cfg
   * @return int
   */
  public function setCfg(string $id = null, array $cfg = null): int
  {
    if (null !== $cfg) {
      $cfg    = $this->getCfg(null, $cfg);
      $config = json_encode($cfg);
    }
    else{
      $config = null;
    }

    return $this->db->update(
      $this->class_cfg['table'], [
      $this->fields['cfg'] => $config
      ], [
      $this->fields['id'] => $id
      ]
    );
  }


  /**
   * Sets (or unsets) the text field of the given preference and returns the result of the executed query
   *
   * @param string      $id
   * @param null|string $text
   * @return null|int
   */
  public function setText(string $id, string $text = null): ?int
  {
    return $this->db->update(
      $this->class_cfg['table'], [
      $this->fields['text'] => $text
      ], [
      $this->fields['id'] => $id
      ]
    );
  }


  /**
   * Sets (or unsets) the id_link field of the given preference and returns the result of the executed query
   *
   * @param string $id
   * @param string $id_link
   * @return null|int
   */
  public function setLink(string $id, string $id_link = null): ?int
  {
    return $this->db->update(
      $this->class_cfg['table'], [
      $this->fields['id_link'] => $id_link
      ], [
      $this->fields['id'] => $id
      ]
    );
  }


  /**
   * Sets (or unsets) the id_link field of the given preference and returns the result of the executed query
   *
   * @param string $id_option
   * @param string $id_link
   * @return null|string The inserted or updated preference's ID
   */
  public function addLink(string $id_option, string $id_link): ?string
  {
    $id = $this->db->selectOne(
      $this->class_cfg['table'], $this->fields['id'], [
      $this->fields['id_user'] => $this->id_user,
      $this->fields['id_option'] => $id_option
      ]
    );
    if ($id) {
      if ($this->db->update(
        $this->class_cfg['table'], [
        $this->fields['id_link'] => $id_link
        ], ['id' => $id]
      )
      ) {
        return $id;
      }
    }
    elseif ($this->db->insert(
      $this->class_cfg['table'], [
      $this->fields['id_user'] => $this->id_user,
      $this->fields['id_option'] => $id_option,
      $this->fields['id_link'] => $id_link
      ]
    )
    ) {
      return $this->db->lastId();
    }

    return null;
  }


  /**
   * Returns an array
   *
   * @param string $id
   * @return array|null
   */
  public function getShared(string $id): ?array
  {
    if (bbn\Str::isUid($id)) {
      return $this->db->rselectAll(
        $this->class_table, [
        $this->fields['id'],
        $this->fields['id_user'],
        $this->fields['id_group']
        ], [
        $this->fields['id_alias'] => $id
        ]
      );
    }

    return null;
  }


  /**
   * Makes (or unmakes) the given preference public.
   *
   * @param string $id
   * @param bool   $cancel
   * @return int|null
   */
  public function makePublic(string $id, bool $cancel = false): ?int
  {
    if ($cfg = $this->get($id)) {
      return $this->db->update(
        $this->class_table, ['public' => $cancel ? 0 : 1], [
        $this->fields['id'] => $id
        ]
      );
    }

    return null;
  }


  /**
   * Shares (or unshares) the given preference to the given group.
   *
   * @param string $id
   * @param string $id_group
   * @param bool   $cancel
   * @return int|null
   */
  public function shareWithGroup(string $id, string $id_group, bool $cancel = false): ?int
  {
    if ($cfg = $this->get($id)) {
      $id_share = $this->db->selectOne(
        $this->class_table, $this->fields['id'], [
        'id_alias' => $id,
        'id_group' => $id_group
        ]
      );
      if ($cancel && $id_share) {
        return $this->db->delete($this->class_table, [$this->fields['id'] => $id_share]);
      }
      elseif (!$cancel && !$id_share) {
        return $this->db->insert(
          $this->class_table, [
          'id_option' => $cfg['id_option'],
          'id_alias' => $id,
          'id_group' => $id_group
          ]
        );
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
   * @param bool   $cancel
   * @return int|null
   */
  public function shareWithUser(string $id, string $id_user, bool $cancel = false): ?int
  {
    if ($cfg = $this->get($id)) {
      $id_share = $this->db->selectOne(
        $this->class_table, $this->fields['id'], [
        'id_alias' => $id,
        'id_user' => $id_user
        ]
      );
      if ($cancel && $id_share) {
        return $this->db->delete($this->class_table, [$this->fields['id'] => $id_share]);
      }
      elseif (!$cancel && !$id_share) {
        return $this->db->insert(
          $this->class_table, [
          'id_option' => $cfg['id_option'],
          'id_alias' => $id,
          'id_user' => $id_user
          ]
        );
      }

      return 0;
    }

    return null;
  }


  /**
   * Adds a bit to a preference
   *
   * @param string $id_user_option The preference's ID
   * @param array  $cfg            The bit's values
   * @return string|null
   */
  public function addBit(string $id_user_option, array $cfg): ?string
  {
    if (($id_user_option = $this->_get_id_option($id_user_option))
        && $this->isAuthorized($id_user_option)
        && ($c = $this->class_cfg['arch']['user_options_bits'])
    ) {
      $to_cfg = $this->getBitCfg(null, $cfg);
      if (isset($to_cfg['items'])) {
        unset($to_cfg['items']);
      }

      if (!empty($to_cfg)) {
        if (!empty($cfg[$c['cfg']])) {
          if (\bbn\Str::isJson($cfg[$c['cfg']])) {
            $cfg[$c['cfg']] = json_decode($cfg[$c['cfg']], true);
          }

          if (\is_array($cfg[$c['cfg']])) {
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

      if ($this->db->insert(
        $this->class_cfg['tables']['user_options_bits'], [
        $c['id_user_option'] => $id_user_option,
        $c['id_parent'] => $cfg[$c['id_parent']] ?? null,
        $c['id_option'] => $cfg[$c['id_option']] ?? null,
        $c['num'] => $cfg[$c['num']] ?? null,
        $c['text'] => $cfg[$c['text']] ?? '',
        $c['cfg'] => $cfg[$c['cfg']] ?? null,
        ]
      )
      ) {
        return $this->db->lastId();
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
  public function deleteBit(string $id): ?int
  {
    if (\bbn\Str::isUid($id)) {
      return $this->db->delete(
        $this->class_cfg['tables']['user_options_bits'],
        [$this->class_cfg['arch']['user_options_bits']['id'] => $id]
      );
    }

    return null;
  }


  /**
   * Deletes all bits from a preference
   *
   * @param string The bit's ID
   * @return int|null
   */
  public function deleteBits(string $id_user_option): ?int
  {
    if (\bbn\Str::isUid($id_user_option) && $this->isAuthorized($id_user_option)) {
      $i = 0;
      foreach ($this->getBits($id_user_option) as $b) {
        $i += (int)$this->deleteBit($b['id']);
      }

      return $i;
    }

    return null;
  }


  /**
   * Updates a preference's bit
   *
   * @param string                 $id The bit's ID
   * @param array The bit's values
   * @return int|null
   */
  public function updateBit(string $id, array $cfg, $merge_config = false): ?int
  {
    if (\bbn\Str::isUid($id)) {
      $c = $this->class_cfg['arch']['user_options_bits'];
      if (\array_key_exists($c['id'], $cfg)) {
        unset($cfg[$c['id']]);
      }

      if (!empty($cfg[$c['cfg']]) && \bbn\Str::isJson($cfg[$c['cfg']])) {
        $cfg[$c['cfg']] = json_decode($cfg[$c['cfg']], true);
      }

      $to_cfg = $this->getBitCfg(null, $cfg[$c['cfg']] ?? $cfg);
      if (isset($to_cfg['items'])) {
        unset($to_cfg['items']);
      }

      $update = [];
      $from_cfg = $this->getBitCfg($id);
      if (!empty($to_cfg)) {
        if ($merge_config && !empty($from_cfg)) {
          $update['cfg'] = json_encode(array_merge($from_cfg, $to_cfg));
        }
        else {
          $update['cfg'] = json_encode($to_cfg);
        }
      }
      elseif (!$merge_config) {
        $update['cfg'] = null;
      }

      if (isset($cfg[$c['id_parent']])) {
        $update[$c['id_parent']] = $cfg[$c['id_parent']];
      }
      if (isset($cfg[$c['id_option']])) {
        $update[$c['id_option']] = $cfg[$c['id_option']];
      }
      if (isset($cfg[$c['num']])) {
        $update[$c['num']] = $cfg[$c['num']];
      }
      if (isset($cfg[$c['text']])) {
        $update[$c['text']] = $cfg[$c['text']];
      }

      return count($update) ? $this->db->update(
        $this->class_cfg['tables']['user_options_bits'],
        $update,
        [$c['id'] => $id]
      ) : 0;
    }

    return null;
  }


  /**
   * Returns a single preference's bit
   *
   * @param string $id The bit's ID
   * @return array
   */
  public function getBit(string $id, bool $with_config = true): array
  {
    if (\bbn\Str::isUid($id)
        && ($bit = $this->db->rselect(
          $this->class_cfg['tables']['user_options_bits'], [], [
          $this->class_cfg['arch']['user_options_bits']['id'] => $id
          ]
        ))
    ) {
      if ($this->isAuthorized($bit['id_user_option'])) {
        if ($with_config) {
          return $this->explodeBitCfg($bit);
        }

        return $bit;
      }
    }

    return [];
  }


  /**
   * Returns the bits list of a preference
   *
   * @param string      $id        The preference's ID
   * @param null|string $id_parent The bits'parent ID
   * @return array
   */
  public function getBits(string $id_user_option, $id_parent = false, bool $with_config = true): array
  {
    if ($this->isAuthorized($id_user_option)) {
      $c     = $this->class_cfg['arch']['user_options_bits'];
      $t     = $this;
      $where = [[
        'field' => $c['id_user_option'],
        'value' => $id_user_option
      ]];
      if (\is_null($id_parent) || \bbn\Str::isUid($id_parent)) {
        $where[] = [
          'field' => $c['id_parent'],
          empty($id_parent) ? 'operator' : 'value' => $id_parent ?: 'isnull'
        ];
      }

      if (\bbn\Str::isUid($id_user_option)
          && ($bits = $this->db->rselectAll(
            [
            'table' => $this->class_cfg['tables']['user_options_bits'],
            'fields' => [],
            'where' => [
            'conditions' => $where
            ],
            'order' => [[
            'field' => $c['num'],
            'dir' => 'ASC'
            ]]
            ]
          ))
      ) {
        if (!empty($with_config)) {
          return array_map(
            function ($b) use ($t) {
              return $t->explodeBitCfg($b);
            }, $bits
          );
        }

        return $bits;
      }
    }

    return [];
  }


  /**
   * Returns the bits list of an option's id
   *
   * @param string      $id        The id_options
   * @param null|string $id_parent The bits'parent ID
   * @return array
   */
  public function getBitsByIdOption(string $id_opt, $id_parent = false, bool $with_config = true): ?array
  {
    $c     = $this->class_cfg['arch']['user_options_bits'];
    $where = [[
      'field' => $c['id_user_option'],
      'value' => $id_opt
    ]];
    if (\is_null($id_parent) || \bbn\Str::isUid($id_parent)) {
      $where[] = [
        'field' => $c['id_parent'],
        empty($id_parent) ? 'operator' : 'value' => $id_parent ?: 'isnull'
      ];
    }

    if (\bbn\Str::isUid($id_opt)
        && ($bits = $this->db->rselectAll(
          [
          'table' => $this->class_cfg['tables']['user_options_bits'],
          'fields' => [],
          'where' => $where,
          'order' => [[
          'field' => $c['num'],
          'dir' => 'ASC'
          ]]
          ]
        ))
    ) {
      $res = [];
      foreach ($bits as $bit) {
        if ($this->isAuthorized($bit['id_user_option'])) {
          $res[] = $with_config ? $this->explodeBitCfg($bit) : $bit;
        }
      }

      return $res;
    }

    return null;
  }


  /**
   * Returns the hierarchical bits list of a preference
   *
   * @param string $id_user_option The preference's ID
   * @param string $id_parent      The parent's ID of a bit. Default: null
   * @param bool   $with_config    Set it to false if you don't want the preference's cfg field values on the results.
   * @return array
   */
  public function getFullBits(string $id_user_option, string $id_parent = null, bool $with_config = true): array
  {
    if ($this->isAuthorized($id_user_option)) {
      $c = $this->class_cfg['arch']['user_options_bits'];
      $t = $this;
      return array_map(
        function ($b) use ($t, $c, $id_user_option, $with_config) {
          if (!empty($with_config)) {
            $b = $t->explodeBitCfg($b);
          }

          $b['items'] = $t->getFullBits($id_user_option, $b[$c['id']], $with_config);
          return $b;
        }, $this->db->rselectAll(
          [
          'table' => $this->class_cfg['tables']['user_options_bits'],
          'fields' => [],
          'where' => [
          'conditions' => [[
            'field' => $c['id_user_option'],
            'value' => $id_user_option
          ], [
            'field' => $c['id_parent'],
            empty($id_parent) ? 'operator' : 'value' => $id_parent ?: 'isnull'
          ]]
          ],
          'order' => [$c['num'] => 'ASC']
          ]
        )
      );
    }

    return [];
  }


  /**
   *
   */
  public function getBitsOrder(string $id_user_option): ?array
  {
    if ($this->isAuthorized($id_user_option)) {
      $tab1 = $this->class_cfg['tables']['user_options'];
      $tab2 = $this->class_cfg['tables']['user_options_bits'];
      $cfg  = $this->class_cfg['arch']['user_options'];
      $cfg2 = $this->class_cfg['arch']['user_options_bits'];
      if ($this->db->selectOne($tab1, $cfg['id_user'], ['id' => $id_user_option]) === $this->id_user) {
        return $this->db->getColumnValues($tab2, $cfg2['id_option'], [$cfg2['id_user_option'] => $id_user_option], [$cfg2['num'] => 'ASC']);
      }
    }

    return null;
  }


  /**
   * Returns a preference and its hierarchical bits list
   *
   * @param string $id          The preference's ID
   * @param bool   $with_config Set it to false if you don't want the preference's cfg field values on the results.
   */
  public function getTree(string $id, bool $with_config = true): array
  {
    if (\bbn\Str::isUid($id)
        && ($p = $this->get($id, $with_config))
    ) {
      $p['items'] = $this->getFullBits($id, null, $with_config);
      return $p;
    }

    return [];
  }


  public function explodeBitCfg($bit): array
  {
    $c = $this->class_cfg['arch']['user_options_bits'];
    if (!empty($bit[$c['cfg']])
        && ($cfg = json_decode($bit[$c['cfg']], true))
    ) {
      foreach ($cfg as $i => $v){
        if (!array_key_exists($i, $bit)) {
          $bit[$i] = $v;
        }
      }
    }

    unset($bit[$c['cfg']]);
    return $bit;
  }


  public function nextBitNum(string $id): ?int
  {
    if (\bbn\Str::isUid($id)
        && ($max = $this->db->selectOne(
          $this->class_cfg['tables']['user_options_bits'],
          'MAX(num)',
          [$this->class_cfg['arch']['user_options_bits']['id_user_option'] => $id]
        ))
    ) {
      return $max + 1;
    }

    return null;
  }


  /**
   * Gets the bit's cfg array, normalized either from the DB or from the $cfg argument
   *
   * @param string     $id
   * @param null|array $cfg
   * @return null|array
   */


  public function getBitCfg(string $id = null, array $cfg = null): ?array
  {
    if ((null !== $cfg)
        || ($cfg = $this->db->selectOne(
          $this->class_cfg['tables']['user_options_bits'],
          $this->class_cfg['arch']['user_options_bits']['cfg'],
          [$this->class_cfg['arch']['user_options_bits']['id'] => $id ]
        ))
    ) {
      $fields = array_values($this->class_cfg['arch']['user_options_bits']);
      if (bbn\Str::isJson($cfg)) {
        $cfg = json_decode($cfg, 1);
      }

      if (\is_array($cfg)) {
        $new = [];
        foreach ($cfg as $k => $v){
          if (!\in_array($k, $fields, true)) {
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
   * @param string $id  The bit's ID
   * @param int    $pos The new position
   * @return bool|null
   */
  public function orderBit(string $id, int $pos): ?bool
  {
    if (\bbn\Str::isUid($id)
        && ($cf = $this->getClassCfg())
        && ($cfg = $cf['arch']['user_options_bits'])
        && ($bit = $this->getBit($id))
        && ($old = (int)$bit[$cfg['num']])
        && !empty($pos)
        && ($old !== $pos)
        && ($bits = $this->getBits($bit[$cfg['id_user_option']], $bit[$cfg['id_parent']]))
    ) {
      $past_new = false;
      $past_old = false;
      $p        = 1;
      $changed  = 0;
      foreach ($bits as $ele){
        $upd = [];
        if ($past_old && !$past_new) {
          $upd[$cfg['num']] = $p - 1;
        }
        elseif (!$past_old && $past_new) {
          $upd[$cfg['num']] = $p + 1;
        }

        if ($id === $ele['id']) {
          $upd[$cfg['num']] = $pos;
          $past_old         = 1;
        }
        elseif ($p === $pos) {
          $upd[$cfg['num']] = $p + ($pos > $old ? -1 : 1);
          $past_new         = 1;
        }

        if (!empty($upd)) {
          $changed += $this->db->update($cf['tables']['user_options_bits'], $upd, [$cfg['id'] => $ele['id']]);
        }

        if ($past_new && $past_old) {
          break;
        }

        $p++;
      }

      return !!$changed;
    }

    return null;
  }


  public function fixBitsOrder(string $id_user_option, string $id_parent = null, $deep = false): ?int
  {
    if (\bbn\Str::isUid($id_user_option)
        && (\bbn\Str::isUid($id_parent) || \is_null($id_parent))
    ) {
      $cfg   = $this->class_cfg['arch']['user_options_bits'];
      $fixed = 0;
      foreach ($this->getBits($id_user_option, $id_parent, false) as $i => $b){
        if ($deep) {
          $fixed += $this->fixBitsOrder($id_user_option, $b[$cfg['id']], $deep);
        }

        if ($b[$cfg['num']] !== ($i + 1)) {
          $fixed += $this->db->update($this->class_cfg['tables']['user_options_bits'], [$cfg['num'] => $i + 1], [$cfg['id'] => $b[$cfg['id']]]);
        }
      }

      return $fixed;
    }

    return null;
  }


  /**
   * Moves a bit.
   *
   * @param string                          $id The bit's ID
   * @param string|null The new parent's ID
   * @return bool|null
   */
  public function moveBit(string $id, string $id_parent = null): ?bool
  {
    if (\bbn\Str::isUid($id)
        && ((\bbn\Str::isUid($id_parent) && $this->getBit($id_parent))
        || \is_null($id_parent)        )
        && ($bit = $this->getBit($id))
        && ($cf = $this->getClassCfg())
        && ($cfg = $cf['arch']['user_options_bits'])
    ) {
      $upd = [
        $cfg['id_parent'] => $id_parent,
        $cfg['num'] => $this->getMaxBitNum($bit[$cfg['id_user_option']], $id_parent, true)
      ];
      return !!$this->db->update($cf['tables']['user_options_bits'], $upd, [$cfg['id'] => $id]);
    }

    return null;
  }


  /**
   * Gets the maximum num value of the user option's bits.
   *
   * @param string      $id_user_option The user option's ID
   * @param string|null $id_parent      The parent's ID
   * @param bool        $incr           Set it to true if you want the result increased by 1
   * @return int
   */
  public function getMaxBitNum(string $id_user_option, string $id_parent = null, bool $incr = false): int
  {
    if (\bbn\Str::isUid($id_user_option)
        && (\bbn\Str::isUid($id_parent) || is_null($id_parent))
        && ($cf = $this->getClassCfg())
        && ($cfg = $cf['arch']['user_options_bits'])
    ) {
      if ($max = $this->db->selectOne(
        [
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
        ]
      )
      ) {
        $max = (int)$max;
        return $incr ? $max + 1 : $max;
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
  public function getByBit(string $id): ?array
  {
    $t =& $this;
    if (\bbn\Str::isUid($id)) {
      return $this->db->rselect(
        [
        'table' => $this->class_cfg['table'],
        'fields' => array_map(
          function ($v) use ($t) {
            return $t->db->cfn($v, $t->class_cfg['table']);
          }, array_values($this->class_cfg['arch']['user_options'])
        ),
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
        ]
      );
    }
  }


  /**
   * Gets the preference's ID from a bit ID
   *
   * @param string $id The bit's ID
   * @return string
   */
  public function getIdByBit(string $id): ?string
  {
    if (\bbn\Str::isUid($id) && ($p = $this->getByBit($id))) {
      return $this->db->selectOne(
        [
        'table' => $this->class_cfg['table'],
        'field' => $this->class_cfg['table'].'.'.$this->fields['id'],
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
        ]
      );
    }

    return null;
  }


  public function textValue(string $id_option, $id_user = null, $id_group = null):? array
  {
    if (\bbn\Str::isUid($id_option)) {
      $res = [];
      if ($ids = $this->_retrieve_ids($id_option, $id_user, $id_group)) {
        foreach ($ids as $id){
          $res[] = $this->db->rselect(
            $this->class_cfg['table'], [
            'value' => $this->fields['id'],
            'text' => $this->fields['text']
            ], ['id' => $id]
          );
        }
      }

      return $res;
    }

    return null;
  }


  /**
   * Sets the user variables using a user object
   *
   * @param bbn\User $user
   * @return preferences
   */
  private function _init_user(bbn\User $user): preferences
  {
    $this->user     = $user;
    $this->id_user  = $this->user->getId();
    $this->id_group = $this->user->getGroup();
    return $this;
  }


  /**
   * Retrieves or confirm the ID of an option based on the same parameters as Option::from_path
   *
   * @param string|null $id_option
   * @return null|string
   */
  private function _get_id_option(string $id_option = null): ?string
  {
    if (!$id_option && !($id_option = $this->getCurrent())) {
      return null;
    }

    if ($id_option && !bbn\Str::isUid($id_option)) {
      $id_option = $this->opt->fromPath(...\func_get_args());
    }

    if ($id_option && bbn\Str::isUid($id_option)) {
      return $id_option;
    }

    return null;
  }


  /**
   * Actually inserts a row into the preferences table
   *
   * @param string $id_option
   * @param array  $cfg
   * @return int
   */
  private function _insert(string $id_option, array $cfg): int
  {
    $json = ($tmp = $this->getCfg(false, $cfg)) ? json_encode($tmp) : null;
    return $this->db->insert(
      $this->class_cfg['table'], [
      $this->fields['id_option'] => $id_option,
      $this->fields['num'] => $cfg[$this->fields['num']] ?? null,
      $this->fields['text'] => $cfg[$this->fields['text']] ?? null,
      $this->fields['id_link'] => $cfg[$this->fields['id_link']] ?? null,
      $this->fields['id_alias'] => $cfg[$this->fields['id_alias']] ?? null,
      $this->fields['id_user'] => $this->id_user,
      $this->fields['cfg'] => $json
      ]
    );
  }


  /**
   * Returns preferences' IDs from the option's ID
   *
   * @param string      $id_option
   * @param null|string $id_user
   * @param null|string $id_group
   * @return array|null
   */
  private function _retrieve_ids(string $id_option, string $id_user = null, string $id_group = null): ?array
  {
    if (!$id_user && !$id_group && isset($this->id_user, $this->id_group)) {
      $id_user  = $this->id_user;
      $id_group = $this->id_group;
    }

    if (($id_user || $id_group) && ($id_option = $this->_get_id_option($id_option))) {
      $cond = [
        'logic' => 'OR',
        'conditions' => []
      ];
      if (null !== $id_user) {
        $cond['conditions'][] = [
          'field' => $this->fields['id_user'],
          'value' => $id_user
        ];
      }

      if (null !== $id_group) {
        $cond['conditions'][] = [
          'field' => $this->fields['id_group'],
          'value' => $id_group
        ];
      }

      // Not specific to just a group or a user, so adding the public i.e. all to which the user has right
      if ($id_user && $id_group) {
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
      if (count($cond['conditions'])) {
        $where['conditions'][] = $cond;
      }

      return $this->db->getColumnValues(
        [
        'table' => $this->class_cfg['table'],
        'fields' => [$this->fields['id']],
        'where' => $where,
        'order' => [
          ['field' => $this->fields['num'], 'dir' => 'ASC'],
          ['field' => $this->fields['text'], 'dir' => 'ASC']
        ]
        ]
      );
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
    if ($id_link = $this->_get_id_option($id_link)) {
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
      if (null !== $id_user) {
        $cond[$this->fields['id_user']] = $id_user;
      }

      if (null !== $id_group) {
        $cond[$this->fields['id_group']] = $id_group;
      }

      // Not specific
      if ((null === $id_user) && (null === $id_group)) {
        $cond[$this->fields['public']] = 1;
      }

      $where['conditions'][] = [
        'logic' => 'OR',
        'conditions' => $cond
      ];
      return $this->db->rselectAll(
        [
        'tables' => [$this->class_cfg['table']],
        'fields' => [$this->fields['id'], $this->fields['id_option']],
        'where' => $where,
        'order' => [$this->fields['text']]
        ]
      );
    }

    return null;
  }


}
