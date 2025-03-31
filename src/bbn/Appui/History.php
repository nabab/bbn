<?php

namespace bbn\Appui;

use Exception;
use bbn\X;
use bbn\Appui\Database;
use bbn\Str;
use bbn\Db;
use bbn\Models\Tts\Report;

class History
{
  use Report;

  /** @var Db The DB connection */
  private static $db;
  /** @var array A collection of DB connections  */
  private static $dbs = [];
  /** @var array A collection of DB connections  */
  private static $structures = [];
  /** @var Database The database class which collects the columns IDs */
  private static $database_obj;
  /** @var string Name of the database where the history table is */
  private static $admin_db = '';
  /** @var string User's ID  */
  private static $user;
  /** @var string Prefix of the history table */
  private static $prefix = 'bbn_';
  /** @var float The current date can be overwritten if this variable is set */
  private static $date;
  /** @var boolean Set to true once the initial configuration has been checked */
  private static $ok = false;
  /** @var boolean Setting it to false avoid execution of history triggers */
  private static $enabled = true;
  /** @var array The foregin links atytached to history UIDs' table */
  private static $links;

  /** @var boolean|string The history table's name */
  public static $table_uids = false;
  /** @var boolean|string The history table's name */
  public static $table = false;
  /** @var string The UIDs table */
  public static $uids = 'uids';
  /** @var string The history default column's name */
  public static $column = 'bbn_active';
  /** @var boolean */
  public static $is_used = false;

  /**
   * Returns the column's corresponding option's ID
   * @param $column string
   * @param $table string
   * @return null|string
   */
  public static function getIdColumn(string $column, string $table): ?string
  {
    if (
      ($db = self::_get_db()) &&
      ($full_table = $db->tfn($table)) &&
      ($database_obj = self::_get_database())
    ) {
      [$database, $table] = explode('.', $full_table);
      return $database_obj->columnId($column, $table, $database);
    }
    return false;
  }

  /**
   * Initializes
   * @param Db $db
   * @param array $cfg
   * @return void
   */
  public static function init(Db $db, array $cfg = []): void
  {
    /** @var string $hash Unique hash for this DB connection (so we don't init twice a same connection) */
    $hash = $db->getHash();
    if (!\in_array($hash, self::$dbs, true) && $db->check()) {
      // Adding the connection to the list of connections
      self::$dbs[] = $hash;
      /** @var Db db */
      self::$db = $db;
      $vars = get_class_vars(__CLASS__);
      foreach ($cfg as $cf_name => $cf_value) {
        if (array_key_exists($cf_name, $vars)) {
          self::$$cf_name = $cf_value;
        }
      }
      if (!self::$admin_db) {
        self::$admin_db = self::$db->getCurrent();
      }
      self::$table = self::$admin_db . '.' . self::$prefix . 'history';
      self::$table_uids = self::$admin_db . '.' . self::$prefix . 'history_uids';
      self::$ok = true;
      self::$is_used = true;
      self::$links = self::$db->getForeignKeys('bbn_uid', self::$prefix . 'history_uids', self::$admin_db);
      self::$db->setTrigger('\\bbn\\Appui\\History::trigger');
    }
  }

  /**
   * @return bool
   */
  public static function isInit(): bool
  {
    return self::$ok;
  }

  /**
   * @return void
   */
  public static function disable(): void
  {
    self::$enabled = false;
  }

  /**
   * @return void
   */
  public static function enable(): void
  {
    self::$enabled = true;
  }

  /**
   * @return bool
   */
  public static function isEnabled(): bool
  {
    return self::$ok && (self::$enabled === true);
  }


  /**
   * @param $d
   * @return null|float
   */
  public static function validDate($d): ?float
  {
    if (!Str::isNumber($d)) {
      $d = strtotime($d);
    }
    if (($d > 0) && Str::isNumber($d)) {
      return (float)$d;
    }
    return null;
  }

  /**
   * Checks if all history parameters are set in order to read and write into history
   * @return bool
   */
  public static function check(): bool
  {
    return
      isset(self::$user, self::$table, self::$db) &&
      self::isInit() &&
      self::_get_db();
  }

  /**
   * Returns true if the given DB connection is configured for history
   *
   * @param Db $db
   * @return bool
   */
  public static function hasHistory(Db $db): bool
  {
    $hash = $db->getHash();
    return \in_array($hash, self::$dbs, true);
  }

  /**
   * Effectively deletes a row (deletes the row, the history row and the ID row)
   *
   * @param string $id
   * @return bool
   */
  public static function delete(string $id): bool
  {
    if ($id && ($db = self::_get_db())) {
      return $db->delete(self::$table_uids, ['bbn_uid' => $id]);
    }
    return false;
  }

  /**
   * Sets the "active" column name
   *
   * @param string $column
   * @return void
   */
  public static function setColumn(string $column): void
  {
    if (Str::checkName($column)) {
      self::$column = $column;
    }
  }

  /**
   * Gets the "active" column name
   *
   * @return string the "active" column name
   */
  public static function getColumn(): string
  {
    return self::$column;
  }

  /**
   * @param $date
   * @return void
   */
  public static function setDate($date): void
  {
    // Sets the current date
    if (!Str::isNumber($date) && !($date = strtotime($date))) {
      return;
    }
    $t = time();
    // Impossible to write history in the future
    if ($date > $t) {
      $date = $t;
    }
    self::$date = $date;
  }

  /**
   * @return float
   */
  public static function getDate(): ?float
  {
    return self::$date;
  }

  /**
   * @return void
   */
  public static function unsetDate(): void
  {
    self::$date = null;
  }

  /**
   * Sets the history table name
   * @param string $db_name
   * @return void
   */
  public static function setAdminDb(string $db_name): void
  {
    // Sets the history table name
    if (Str::checkName($db_name)) {
      self::$admin_db = $db_name;
      self::$table = self::$admin_db . '.' . self::$prefix . 'history';
    }
  }

  /**
   * Sets the user ID that will be used to fill the user_id field
   * @param $user
   * @return void
   */
  public static function setUser($user): void
  {
    // Sets the history table name
    if (Str::isUid($user)) {
      self::$user = $user;
    }
  }

  /**
   * Gets the user ID that is being used to fill the user_id field
   * @return null|string
   */
  public static function getUser(): ?string
  {
    return self::$user;
  }

  /**
   * @param string $table
   * @param int $start
   * @param int $limit
   * @param string|null $dir
   * @return array
   */
  public static function getAllHistory(string $table, int $start = 0, int $limit = 20, string|null $dir = null): array
  {
    if (
      ($db = self::_get_db()) &&
      ($dbc = self::_get_database()) &&
      ($id_table = $dbc->tableId($table, self::$db->getCurrent()))
    ) {
      $order = $dir && (Str::changeCase($dir, 'lower') === 'asc') ? 'ASC' : 'DESC';
      return $db->getColumnValues([
        'table' => self::$table_uids,
        'fields' => ['bbn_uid'],
        'join' => [
          [
            'table' => self::$table,
            'on' => [
              'conditions' => [[
                'field' => 'bbn_uid',
                'exp' => 'uid'
              ]]
            ]
          ]
        ],
        'where' => ['bbn_table' => $id_table],
        'order' => [[
          'field' => 'tst',
          'dir' => $order
        ]],
        'start' => $start,
        'limit' => $limit
      ]);
    }
    return [];
  }

  /**
   * @param $table
   * @param int $start
   * @param int $limit
   * @return array
   */
  public static function getLastModifiedLines(string $table, int $start = 0, int $limit = 20): array
  {
    $r = [];
    if (
      ($db = self::_get_db()) &&
      ($dbc = self::_get_database()) &&
      ($id_table = $dbc->tableId($table, self::$db->getCurrent()))
    ) {
      $tab = $db->escape(self::$table);
      $tab_uids = $db->escape(self::$table_uids);
      $uid = $db->cfn('bbn_uid', self::$table_uids, true);
      $active = $db->cfn(self::$column, self::$table_uids, true);
      $id_tab = $db->cfn('bbn_table', self::$table_uids, true);
      $line = $db->cfn('uid', self::$table, true);
      $chrono = $db->escape('tst');
      $sql = <<< MYSQL
SELECT DISTINCT($line)
FROM $tab_uids
  JOIN $tab
    ON $uid = $line
WHERE $id_tab = ? 
AND $active = 1
ORDER BY $chrono
LIMIT $start, $limit
MYSQL;
      $r = $db->getColArray($sql, hex2bin($id_table));
    }
    return $r;
  }

  /**
   * @param string $table
   * @param string $id
   * @param string|int $from_when
   * @param string|null $column
   * @return null|array
   */
  public static function getNextUpdate(string $table, string $id, string|int $from_when, string|null $column = null)
  {
    /** @todo To be redo totally with all the fields' IDs instead of the history column */
    if (
      Str::checkName($table) &&
      ($date = self::validDate($from_when)) &&
      ($db = self::_get_db()) &&
      ($dbc = self::_get_database()) &&
      ($id_table = $dbc->tableId($table))
    ) {
      $isDisabled = !self::$enabled;
      if (!$isDisabled) {
        self::disable();
      }

      $tab = $db->escape(self::$table);
      $tab_uids = $db->escape(self::$table_uids);
      $uid = $db->cfn('bbn_uid', self::$table_uids);
      $id_tab = $db->cfn('bbn_table', self::$table_uids);
      $id_col = $db->cfn('col', self::$table);
      $line = $db->cfn('uid', self::$table);
      $usr = $db->cfn('usr', self::$table);
      $chrono = $db->cfn('tst', self::$table);
      $where = [
        'logic' => 'AND',
        'conditions' => [
          [
            'field' => $uid,
            'operator' => '=',
            'value' => $line
          ],
          [
            'field' => $id_tab,
            'operator' => '=',
            'value' => $id_table
          ],
          [
            'field' => $chrono,
            'operator' => '>',
            'value' => $date
          ]
        ]
      ];

      if ($column) {
        $where['conditions'][] = [
          'field' => $id_col,
          'value' => Str::isUid($column) ? $column : $dbc->columnId($column, $id_table)
        ];
      } else if ($w = self::_getTableWhere($table)) {
        $where['conditions'][] = $w;
      }

      $res = $db->rselect([
        'tables' => [$tab_uids],
        'fields' => [
          $line,
          $id_col,
          $chrono,
          'val' => 'IFNULL(val, ref)',
          $usr
        ],
        'join' => [
          [
            'table' => $tab,
            'on' => [
              'logic' => 'AND',
              'conditions' => [[
                'field' => $uid,
                'operator' => '=',
                'exp' => $line
              ]]
            ]
          ]
        ],
        'where' => $where,
        'order' => [$chrono => 'ASC']
      ]);

      if (!$isDisabled) {
        self::enable();
      }

      return $res;
    }

    return null;
  }

  /**
   * @param string $table
   * @param string $id
   * @param string|int $from_when
   * @param string|null $column
   * @return null|array
   */
  public static function getPrevUpdate(string $table, string $id, string|int $from_when, string|null $column = null): ?array
  {
    if (
      Str::checkName($table) &&
      ($date = self::validDate($from_when)) &&
      ($dbc = self::_get_database()) &&
      ($db = self::_get_db())
    ) {
      if ($column) {
        $where = [
          'conditions' => [
            [
              'field' => 'col',
              'value' => Str::isUid($column) ? $column : $dbc->columnId($column, $table)
            ]
          ]
        ];
      } else if ($w = self::_getTableWhere($table)) {
        $where = $w;
      }

      return $db->rselect(self::$table, [], [
        'conditions' => [
          [
            'field' => 'uid',
            'value' => $id
          ],
          $where,
          [
            'field' => 'opr',
            'value' => 'UPDATE'
          ],
          [
            'field' => 'tst',
            'operator' => '<',
            'value' => $date
          ]
        ]
      ]);
    }

    return null;
  }

  /**
   * @param string $table
   * @param $from_when
   * @param string $id
   * @param $column
   * @return bool|mixed
   */
  public static function getNextValue(string $table, string $id, $from_when, $column)
  {
    if ($r = self::getNextUpdate($table, $id, $from_when, $column)) {
      return $r['ref'] ?: $r['val'];
    }
    return false;
  }

  /**
   * @param string $table
   * @param string $id
   * @param $from_when
   * @param $column
   * @return bool|mixed
   */
  public static function getPrevValue(string $table, string $id, $from_when, $column)
  {
    if ($r = self::getPrevUpdate($table, $id, $from_when, $column)) {
      return $r['ref'] ?: $r['val'];
    }
    return false;
  }

  /**
   * @param string $table
   * @param string $id
   * @param $when
   * @param array $columns
   * @return array|null
   */
  public static function getRowBack(string $table, string $id, $when, array $columns = []): ?array
  {
    if (!($when = self::validDate($when))) {
      self::_report_error("The date $when is incorrect", __CLASS__, __LINE__);
    } else if (
      ($db = self::_get_db()) &&
      ($cfg = self::getTableCfg($table))
    ) {
      // Time is after last modification: the current is given
      $isDisabled = !self::$enabled;
      if (!$isDisabled) {
        self::disable();
      }

      if ($when >= time()) {
        $r = $db->rselect($table, $columns, [
          $cfg['primary'] => $id
        ]) ?: null;
      }
      // Time is before creation: null is given
      else if ($when < self::getCreationDate($table, $id)) {
        $r = null;
      } else {
        // No columns = All columns
        if (\count($columns) === 0) {
          $columns = array_keys($cfg['fields']);
        }
        $r = [];
        //die(var_dump($columns, $model['fields']));
        foreach ($columns as $col) {
          $tmp = null;
          if (isset($cfg['fields'][$col]['id_option'])) {
            if ($tmp = $db->rselect(self::$table, ['val', 'ref'], [
              'uid' => $id,
              'col' => $cfg['fields'][$col]['id_option'],
              'opr' => 'UPDATE',
              ['tst', '>', $when],
              ['tst' => 'ASC']
            ])) {
              $r[$col] = $tmp['ref'] ?: $tmp['val'];
            }
          }

          if (!$tmp) {
            $r[$col] = $db->selectOne($table, $col, [
              $cfg['primary'] => $id
            ]);
          }
        }
      }

      if (!$isDisabled) {
        self::enable();
      }

      return $r;
    }

    return null;
  }

  /**
   * @param $table
   * @param $id
   * @param $when
   * @param $column
   * @return bool|mixed
   */
  public static function getValBack(string $table, string $id, $when, $column)
  {
    if ($row = self::getRowBack($table, $id, $when, [$column])) {
      return $row[$column];
    }
    return false;
  }

  public static function getCreationDate(string $table, string $id): ?float
  {
    if ($res = self::getCreation($table, $id)) {
      return $res['date'];
    }
    return null;
  }

  /**
   * @param $table
   * @param $id
   * @return array|null
   */
  public static function getCreation(string $table, string $id): ?array
  {
    $r = null;
    if (
      ($db = self::_get_db()) &&
      ($cfg = self::getTableCfg($table)) &&
      ($id_col = self::getIdColumn($cfg['primary'], $table))
    ) {
      $isDisabled = !self::$enabled;
      if (!$isDisabled) {
        self::disable();
      }

      $r = $db->rselect(self::$table, ['date' => 'tst', 'user' => 'usr'], [
        'uid' => $id,
        'col' => $id_col,
        'opr' => 'INSERT'
      ], [
        'tst' => 'DESC'
      ]);

      if (!$isDisabled) {
        self::enable();
      }
    }

    return $r;
  }

  /**
   * @param string $table
   * @param string $id
   * @param null $column
   * @return float|null
   */
  public static function getLastDate(string $table, string $id, $column = null): ?float
  {
    if ($db = self::_get_db()) {
      if (
        $column &&
        ($id_col = self::getIdColumn($column, $table))
      ) {
        return self::$db->selectOne(self::$table, 'tst', [
          'uid' => $id,
          'col' => $id_col
        ], [
          'tst' => 'DESC'
        ]);
      } elseif (!$column && ($where = self::_getTableWhere($table))) {
        return $db->selectOne(self::$table, 'tst', [
          'conditions' => [
            [
              'field' => 'uid',
              'value' => $id
            ],
            $where
          ]
        ], [
          'tst' => 'DESC'
        ]);
      }
    }
    return null;
  }

  /**
   * @param string $table
   * @param string $id
   * @param string $col
   * @param string $since
   * @return array
   */
  public static function getHistory(string $table, string $id, string $col = '', string $since = ''): ?array
  {
    if (
      self::check() &&
      self::isLinked($table) &&
      ($modelize = self::getTableCfg($table))
    ) {
      $pat = [
        'ins' => 'INSERT',
        'upd' => 'UPDATE',
        'res' => 'RESTORE',
        'del' => 'DELETE'
      ];
      $r = [];
      $fields = [
        'date' => 'tst',
        'user' => 'usr',
        'dt',
        'col'
      ];
      $where = [
        'uid' => $id
      ];

      if (!empty($since)) {
        if (!Str::isNumber($since)) {
          $since = strtotime($since);
        }

        $where[] = ['tst', '>=', $since];
      }

      if (!empty($col)) {
        if (!Str::isUid($col)) {
          $fields[] = $modelize['fields'][$col]['type'] === 'binary' ? 'ref' : 'val';
          $col = self::$database_obj->columnId($col, $table);
        } else {
          $idx = X::find($modelize['fields'], ['id_option' => strtolower($col)]);
          if (null === $idx) {
            throw new Exception("Impossible to find the option $col");
          }

          $fields['old'] = $modelize['fields'][$idx]['type'] === 'binary' ? 'ref' : 'val';
        }

        $where['col'] = $col;
      } else {
        $fields['old'] = 'IFNULL(' . self::$table . '.ref, ' . self::$table . '.val)';
      }

      foreach ($pat as $k => $p) {
        $where['opr'] = $p;
        if ($all = self::$db->rselectAll([
          'table' => self::$table,
          'fields' => $fields,
          'where' => $where,
          'order' => [[
            'field' => 'tst',
            'dir' => 'desc'
          ]]
        ])) {
          if ($p === 'UPDATE') {
            foreach ($all as &$a) {
              $colname = X::find($modelize['fields'], ['id_option' => $a['col']]);
              $a['field'] = $colname;
              $a['new'] = self::getValBack($table, $id, $a['date'], $colname);
            }
          }

          $r[$k] = $all;
        }
      }

      return $r;
    }

    return null;
  }

  /**
   * @param string $table
   * @param string $id
   * @return array
   */
  public static function getFullHistory(string $table, string $id, string|null $column = null): array
  {
    $res = [];
    if ($db = self::_get_db()) {
      $cfg = self::getTableCfg($table);
      $fields = [];
      foreach ($cfg['fields'] as $name => $f) {
        $fields[$f['id_option']] = $name;
      }

      $where =  [$cfg['primary'] => $id];
      if ($column) {
        $where['col'] = self::$database_obj->columnId($column, $table);
      }

      $origin = $db->rselect($table, [], $where);
      $all = $db->rselectAll(self::$table, [], ['uid' => $id], ['tst' => 'ASC']);
      while (count($all)) {
        $row = array_shift($all);
        $ele = [
          'column' => $fields[$row['col']],
          'id_column' => $row['col'],
          'date' => $row['tst'],
          'user' => $row['usr'],
          'value' => $row['ref'] ?: $row['val'],
          'operation' => $row['opr'],
        ];
        if ($row['opr'] === 'UPDATE') {
          $ele['old'] = $ele['value'];
          $next = X::getRow($all, ['col' => $row['col']]);
          $ele['new'] = $next ? ($next['ref'] ?: $next['val']) : $origin[$ele['column']];
        }

        $res[] = $ele;
      }
    }

    return $res;
  }

  public static function getColumnHistory(string $table, string $id, string $column)
  {
    return self::getFullHistory($table, $id, $column);
  }


  /**
   * Gets all information about a given table
   * @param string $table
   * @param bool $force
   * @return null|array Table's full name
   */
  public static function getTableCfg(string $table, bool $force = false): ?array
  {
    // Check history is enabled and table's name correct
    if (
      ($db = self::_get_db()) &&
      ($dbc = self::_get_database()) &&
      ($table = $db->tfn($table))
    ) {
      if ($force || !isset(self::$structures[$table])) {
        if ($model = $dbc->modelize($table)) {
          self::$structures[$table] = [
            'history' => false,
            'primary' => false,
            'primary_type' => null,
            'primary_length' => 0,
            'auto_increment' => false,
            'id' => null,
            'unique' => [],
            'fields' => []
          ];
          if (
            self::isLinked($table) &&
            isset($model['keys']['PRIMARY']) &&
            (\count($model['keys']['PRIMARY']['columns']) === 1) &&
            ($primary = $model['keys']['PRIMARY']['columns'][0]) &&
            !empty($model['fields'][$primary])
          ) {
            // Looking for the config of the table
            self::$structures[$table]['history'] = 1;
            self::$structures[$table]['primary'] = $primary;
            self::$structures[$table]['primary_type'] = $model['fields'][$primary]['type'];
            self::$structures[$table]['primary_length'] = $model['fields'][$primary]['maxlength'];
            self::$structures[$table]['auto_increment'] = isset($model['fields'][$primary]['extra']) && ($model['fields'][$primary]['extra'] === 'auto_increment');
            self::$structures[$table]['id'] = $dbc->tableId($db->tsn($table), $db->getCurrent());
            foreach ($model['keys'] as $key) {
              if (!empty($key['unique'])) {
                foreach ($key['columns'] as $col) {
                  if (($col !== $primary)
                    && !in_array($col, self::$structures[$table]['unique'], true)
                    && !empty($model['fields'][$col]['null'])
                  ) {
                    array_push(self::$structures[$table]['unique'], $col);
                  }
                }
              }
            }

            self::$structures[$table]['fields'] = array_filter($model['fields'], function ($a) {
              return isset($a['id_option']);
            });
          }
        }
      }
      // The table exists and has history
      if (isset(self::$structures[$table]) && !empty(self::$structures[$table]['history'])) {
        return self::$structures[$table];
      }
    }
    return null;
  }

  public static function getDbCfg(string|null $db = null, bool $force = false): ?array
  {
    if ($db = self::_get_db()) {
      $res = [];
      $tables = $db->getTables($db);
      if ($tables && count($tables)) {
        foreach ($tables as $t) {
          if ($tmp = self::getTableCfg($t, $force)) {
            $res[$t] = $tmp;
          }
        }
      }
      return $res;
    }
    return null;
  }

  public static function isLinked(string $table): bool
  {
    return ($db = self::_get_db()) &&
      ($ftable = $db->tfn($table)) &&
      isset(self::$links[$ftable]);
  }

  public static function getLinks()
  {
    return self::$links;
  }


  public static function fusion(array $ids, string $table, Db $db, $main = null): bool
  {
    if (!self::check()) {
      return false;
    }

    if ($main && !in_array($main, $ids, true)) {
      $ids[] = $main;
    }

    $oldest = null;
    $oldestId = null;
    foreach ($ids as $a) {
      $tmp = History::getCreationDate($table, $a);
      if (!$oldest || ($tmp < $oldest)) {
        $oldest = $tmp;
        $oldestId = $a;
      }
    }

    if (!$main) {
      $main = $oldestId;
    }

    if (!$main) {
      throw new Exception(X::_("Impossible to find the main record"));
    }

    $idx = array_search($main, $ids);
    if ($idx !== false) {
      array_splice($ids, $idx, 1);
    }

    array_unshift($ids, $main);

    $tables = $db->rselectAll(
      self::$table_uids,
      'bbn_table',
      ['bbn_uid' => $ids]
    );

    $unique = array_unique(array_map(function ($a) {
      return $a['bbn_table'];
    }, $tables));

    if (count($unique) > 1) {
      X::log($unique);
      throw new Exception(X::_("The fusion you wanna do seems to go on different tables"));
    }

    if (count($tables) !== count($ids)) {
      throw new Exception(X::_("They are not all in the history table"));
    }

    $source = array_shift($ids);

    $isActive = $db->selectOne(
      self::$table_uids,
      'bbn_active',
      [
        'bbn_uid' => $source
      ]
    );

    if (!$isActive) {
      throw new Exception(X::_("Main record is deleted"));
    }

    $db->update(
      self::$table,
      ['tst' => $oldest],
      [
        'uid' => $ids,
        'opr' => 'INSERT'
      ]

    );

    $model = $db->modelize($table);
    $primary = $model['keys']['PRIMARY']['columns'][0];
    $refs = $db->findReferences($db->cfn($primary, $table));
    $relations = [];
    foreach ($refs as $ref) {
      [$d, $t, $c] = X::split($ref, '.');
      $relations[] = [
        'table' => $t,
        'column' => $c
      ];
    }

    $isDisabled = !self::$enabled;
    if (!$isDisabled) {
      self::disable();
    }

    if ($isTriggerEnabled = $db->isTriggerEnabled()) {
      $db->disableTrigger();
    }

    $num = 0;
    foreach ($ids as $id) {
      foreach ($relations as $ref) {
        $num += (int)$db->update(
          $ref['table'],
          [$ref['column'] => $source],
          [$ref['column'] => $id]
        );
      }

      $num += (int)$db->update(
        self::$table,
        ['uid' => $source],
        [
          'uid' => $id,
          'opr' => ['UPDATE', 'RESTORE', 'DELETE']
        ]
      );
      $num += (int)$db->delete(
        self::$table_uids,
        ['bbn_uid' => $id]
      );
    }

    if ($isTriggerEnabled) {
      $db->enableTrigger();
    }

    if (!$isDisabled) {
      self::enable();
    }

    return (bool)$num;
  }


  /**
   * The function used by the db trigger
   * This will basically execute the history query if it's configured for.
   *
   * @param array $cfg
   * @internal param string "table" The table for which the history is called
   * @internal param string "kind" The type of action: select|update|insert|delete
   * @internal param string "moment" The moment according to the db action: before|after
   * @internal param array "values" key/value array of fields names and fields values selected/inserted/updated
   * @internal param array "where" key/value array of fields names and fields values identifying the row
   * @return array The $cfg array, modified or not
   *
   */
  public static function trigger(array $cfg): array
  {
    if (!self::isEnabled() || !($db = self::_get_db())) {
      return $cfg;
    }
    $tables = $cfg['tables'] ?? (array)$cfg['table'];
    // Will return false if disabled, the table doesn't exist, or doesn't have history
    if (
      ($cfg['kind'] === 'SELECT') &&
      ($cfg['moment'] === 'before') &&
      !empty($cfg['tables']) &&
      !in_array($db->tfn(self::$table), $cfg['tables_full'], true) &&
      !in_array($db->tfn(self::$table_uids), $cfg['tables_full'], true)
    ) {
      $change = 0;
      if (!isset($cfg['history'])) {
        $cfg['history'] = [];
        $new_join = [];
        foreach ($cfg['join'] as $t) {
          $model = $db->modelize($t['table']);
          if (
            isset($model['keys']['PRIMARY']) &&
            ($model['keys']['PRIMARY']['ref_table'] === $db->tsn(self::$table_uids))
          ) {
            $change++;
            if (!isset($t['join'])) {
              $t['join'] = [];
            }
            $t['join'][] = [
              'table' => self::$table_uids,
              'alias' => $db->tsn(self::$table_uids) . $change,
              'on' => [
                'conditions' => [
                  [
                    'field' => $db->cfn('bbn_uid', self::$table_uids . $change),
                    'operator' => 'eq',
                    'exp' => $db->cfn($model['keys']['PRIMARY']['columns'][0], $t['alias'] ?? $t['table'], true)
                  ],
                  [
                    'field' => $db->cfn('bbn_active', self::$table_uids . $change),
                    'operator' => '=',
                    'exp' => '1'
                  ]
                ],
                'logic' => 'AND'
              ]
            ];
          }

          if (!empty($t['join'])) {
            foreach ($t['join'] as $j) {
              if ($j['table'] !== self::$table_uids) {
                $model = $db->modelize($j['table']);
                if (
                  isset($model['keys']['PRIMARY']) &&
                  ($model['keys']['PRIMARY']['ref_table'] === $db->csn(self::$table_uids))
                ) {
                  $change++;
                  $t['join'][] = [
                    'table' => self::$table_uids,
                    'alias' => $db->tsn(self::$table_uids) . $change,
                    'on' => [
                      'conditions' => [
                        [
                          'field' => $db->cfn('bbn_uid', self::$table_uids . $change),
                          'operator' => 'eq',
                          'exp' => $db->cfn($model['keys']['PRIMARY']['columns'][0], $t['alias'] ?? $t['table'], true)
                        ],
                        [
                          'field' => $db->cfn('bbn_active', self::$table_uids . $change),
                          'operator' => '=',
                          'exp' => '1'
                        ]
                      ],
                      'logic' => 'AND'
                    ]
                  ];
                }
              }
            }
          }

          $new_join[] = $t;
        }

        foreach ($cfg['tables'] as $alias => $table) {
          $model = $db->modelize($table);
          if (
            isset($model['keys']['PRIMARY']['ref_table']) &&
            ($db->tfn($model['keys']['PRIMARY']['ref_db'] . '.' . $model['keys']['PRIMARY']['ref_table']) === self::$table_uids)
          ) {
            $change++;
            $new_join[] = [
              'table' => self::$table_uids,
              'alias' => $db->tsn(self::$table_uids) . $change,
              'on' => [
                'conditions' => [
                  [
                    'field' => $db->cfn(self::$table_uids . $change . '.bbn_uid'),
                    'operator' => 'eq',
                    'exp' => $db->cfn($model['keys']['PRIMARY']['columns'][0], \is_string($alias) ? $alias : $table, true)
                  ],
                  [
                    'field' => $db->cfn(self::$table_uids . $change . '.bbn_active'),
                    'operator' => '=',
                    'exp' => '1'
                  ]
                ],
                'logic' => 'AND'
              ]
            ];
          }
        }
        if ($change) {
          $cfg['join'] = $new_join;
          $cfg['where'] = $cfg['filters'];
          $cfg = $db->reprocessCfg($cfg);
        }
      }
    }

    if (
      $cfg['write'] &&
      ($table = $db->tfn(current($tables))) &&
      ($s = self::getTableCfg($table))
    ) {
      // This happens before the query is executed
      if ($cfg['moment'] === 'before') {

        $primary_where = false;
        $primary_defined = false;
        $primary_value = false;
        $idx1 = X::find($cfg['values_desc'], ['primary' => true]);
        if ($idx1 !== null) {
          $primary_where = $cfg['values'][$idx1];
        }
        $idx = array_search($s['primary'], $cfg['fields'], true);
        if (($idx !== false) && isset($cfg['values'][$idx])) {
          $primary_defined = $cfg['generate_id'] ? false : true;
          $primary_value = $cfg['values'][$idx];
        }

        switch ($cfg['kind']) {

          case 'INSERT':
            // If the primary is specified and already exists in a row in deleted state
            // (if it exists in active state, DB will return its standard error but it's not this class' problem)
            if (!$primary_defined) {
              // Checks if there is a unique value (non based on UID)
              $modelize = $db->modelize($table);
              $keys = $modelize['keys'];
              unset($keys['PRIMARY']);
              foreach ($keys as $key) {
                if (!empty($key['unique']) && !empty($key['columns'])) {
                  $fields = [];
                  $exit = false;
                  foreach ($key['columns'] as $col) {
                    $col_idx = array_search($col, $cfg['fields'], true);
                    if (($col_idx === false) || \is_null($cfg['values'][$col_idx])) {
                      $exit = true;
                      break;
                    } else {
                      $fields[] = [
                        'field' => $col,
                        'operator' => 'eq',
                        'value' => $cfg['values'][$col_idx]
                      ];
                    }
                  }
                  if ($exit) {
                    continue;
                  }

                  $isDisabled = !self::$enabled;
                  if (!$isDisabled) {
                    self::disable();
                  }

                  if ($tmp = $db->selectOne([
                    'tables' => [$table],
                    'fields' => [$s['primary']],
                    'join' => [[
                      'table' => self::$table_uids,
                      'on' => [[
                        'field' => $db->cfn('bbn_uid', self::$table_uids),
                        'operator' => 'eq',
                        'exp' => $db->cfn($s['primary'], $table, true)
                      ]]
                    ]],
                    'where' => [
                      'conditions' => $fields,
                      'logic' => 'AND'
                    ]
                  ])) {
                    $primary_value = $tmp;
                    $primary_defined = true;
                    if (!$isDisabled) {
                      self::enable();
                    }

                    break;
                  }

                  if (!$isDisabled) {
                    self::enable();
                  }
                }
              }
            }
            if (
              $primary_defined &&
              ($db->selectOne(self::$table_uids, self::$column, ['bbn_uid' => $primary_value]) === 0) &&
              //($all = self::$db->rselect($table, [], [$s['primary'] => $primary_value]))
              ($all = self::$db->rselect([
                'table' => $table,
                'fields' => $cfg['fields'],
                'join' => [[
                  'table' => self::$table_uids,
                  'on' => [
                    'conditions' => [[
                      'field' => $s['primary'],
                      'exp' => 'bbn_uid'
                    ], [
                      'field' => self::$column,
                      'value' => 0
                    ]]
                  ]
                ]],
                'where' => [
                  'conditions' => [[
                    'field' => $s['primary'],
                    'value' => $primary_value
                  ]]
                ]
              ]))
            ) {
              // We won't execute the after trigger
              $cfg['trig'] = false;
              // Real query's execution will be prevented
              $cfg['run'] = false;
              $cfg['value'] = 0;
              /** @var array $update The values to be updated */
              $update = [];
              // We update each element which needs to (the new ones different from the old, and the old ones different from the default)
              foreach ($all as $k => $v) {
                if ($k !== $s['primary']) {
                  $idx = array_search($k, $cfg['fields'], true);
                  if ($idx !== false) {
                    if ($v !== $cfg['values'][$idx]) {
                      $update[$k] = $cfg['values'][$idx];
                    }
                  } else if ($v !== $s['fields'][$k]['default']) {
                    $update[$k] = $s['fields'][$k]['default'];
                  }
                }
              }

              if ($cfg['value'] = self::$db->update(self::$table_uids, ['bbn_active' => 1], [
                ['bbn_uid', '=', $primary_value]
              ])) {
                // Without this the record won't be write in bbn_history. Added by Mirko 
                $cfg['trig'] = true;
                // --------
                if (\count($update) > 0) {
                  self::enable();
                  self::$db->update($table, $update, [
                    $s['primary'] => $primary_value
                  ]);
                }
                $cfg['history'][] = [
                  'operation' => 'RESTORE',
                  'column' => $s['fields'][$s['primary']]['id_option'],
                  'line' => $primary_value
                ];
                self::$db->setLastInsertId($primary_value);
              }

              if (!$isDisabled) {
                self::enable();
              }
            } else {
              $isDisabled = !self::$enabled;
              if (!$isDisabled) {
                self::disable();
              }

              if ($primary_defined && !self::$db->count($table, [$s['primary'] => $primary_value])) {
                $primary_defined = false;
              }
              if (!$primary_defined && self::$db->insertIgnore(self::$table_uids, [
                'bbn_uid' => $primary_value,
                'bbn_table' => $s['id']
              ])) {
                $cfg['history'][] = [
                  'operation' => 'INSERT',
                  'column' => isset($s['fields'][$s['primary']]) ? $s['fields'][$s['primary']]['id_option'] : null,
                  'line' => $primary_value
                ];
                self::$db->setLastInsertId($primary_value);
              }

              if (!$isDisabled) {
                self::enable();
              }
            }
            break;
          case 'UPDATE':

            // ********** CHANGED BY MIRKO *************

            /*if ( $primary_defined ){
              $where = [$s['primary'] => $primary_value];
              // If the only update regards the history field
              $row = self::$db->rselect($table, array_keys($cfg['fields']), $where);
              $time = microtime(true);
              foreach ( $cfg['values'] as $k => $v ){
                if (
                  ($row[$k] !== $v) &&
                  isset($s['fields'][$k])
                ){
                  $cfg['history'][] = [
                    'operation' => 'UPDATE',
                    'column' => $s['fields'][$k]['id_option'],
                    'line' => $primary_value,
                    'old' => $row[$k],
                    'chrono' => $time
                  ];
                }
              }
            }*/
            if (
              $primary_where &&
              ($row = self::$db->rselect($table, $cfg['fields'], [$s['primary'] => $primary_where]))
            ) {
              foreach ($cfg['fields'] as $i => $idx) {
                $csn = self::$db->csn($idx);
                if (
                  array_key_exists($csn, $s['fields']) &&
                  ($row[$csn] !== $cfg['values'][$i])
                ) {
                  $cfg['history'][] = [
                    'operation' => 'UPDATE',
                    'column' => $s['fields'][$csn]['id_option'],
                    'line' => $primary_where,
                    'old' => $row[$csn]
                  ];
                }
              }
            }
            // Case where the primary is not defined, we'll update each primary instead
            else if ($ids = self::$db->getColumnValues($table, $s['primary'], $cfg['filters'])) {
              // We won't execute the after trigger
              $cfg['trig'] = false;
              // Real query's execution will be prevented
              $cfg['run'] = false;
              $cfg['value'] = 0;

              $tmp = [];
              foreach ($cfg['fields'] as $i => $f) {
                $tmp[$f] = $cfg['values'][$i];
              }
              foreach ($ids as $id) {
                $cfg['value'] += self::$db->update($table, $tmp, [$s['primary'] => $id]);
              }

              // ****************************************

            }
            break;

            // Nothing is really deleted, the hcol is just set to 0
          case 'DELETE':
            // We won't execute the after trigger
            $cfg['trig'] = false;
            // Real query's execution will be prevented
            $cfg['run'] = false;
            $cfg['value'] = 0;
            // Case where the primary is not defined, we'll delete based on each primary instead
            if (!$primary_where) {
              $ids = self::$db->getColumnValues($table, $s['primary'], $cfg['filters']);
              foreach ($ids as $id) {
                $cfg['value'] += self::$db->delete($table, [$s['primary'] => $id]);
              }
            } else {
              $isDisabled = !self::$enabled;
              if (!$isDisabled) {
                self::disable();
              }

              foreach ($s['unique'] as $un) {
                $old = self::$db->selectOne($table, $un, [$s['primary'] => $primary_where]);
                self::$db->update($table, [$un => null], [$s['primary'] => $primary_where]);
                if (!isset($s['fields'][$un])) {
                  X::log([$un, $s], '_toDoHistoryStructureError');
                  continue;
                }
                $cfg['history'][] = [
                  'operation' => 'UPDATE',
                  'column' => $s['fields'][$un]['id_option'],
                  'line' => $primary_where,
                  'old' => $old
                ];
              }

              $cfg['value'] = self::$db->update(self::$table_uids, [
                'bbn_active' => 0
              ], [
                'bbn_uid' => $primary_where
              ]);
              //var_dump("HIST", $primary_where);
              if (!$isDisabled) {
                self::enable();
              }

              if ($cfg['value']) {
                $cfg['trig'] = 1;
                // And we insert into the history table
                $cfg['history'][] = [
                  'operation' => 'DELETE',
                  'column' => $s['fields'][$s['primary']]['id_option'],
                  'line' => $primary_where,
                  'old' => NULL
                ];
              }
            }
            break;
        }
      } else if (
        ($cfg['moment'] === 'after') &&
        isset($cfg['history'])
      ) {
        $time = microtime(true);
        foreach ($cfg['history'] as $h) {
          $h['chrono'] = $time;
          self::_insert($h);
        }
        unset($cfg['history']);
      }
    }
    return $cfg;
  }

  /**
   * Returns the database connection object.
   *
   * @return Db
   */
  private static function _get_db(): ?Db
  {
    if (self::$db && self::$db->check()) {
      return self::$db;
    }
    return null;
  }

  /**
   * Returns an instance of the Appui\Database class.
   *
   * @return Database
   */
  private static function _get_database(): ?Database
  {
    if (self::check()) {
      if (!self::$database_obj && ($db = self::_get_db())) {
        self::$database_obj = new Database($db);
      }
      return self::$database_obj;
    }
    return null;
  }

  /**
   * Adds a row in the history table
   * 
   * @param array $cfg
   * @return int
   */
  private static function _insert(array $cfg): int
  {
    if (
      isset($cfg['column'], $cfg['line'], $cfg['chrono']) &&
      self::check() &&
      ($db = self::_get_db())
    ) {
      // Recording the last ID
      $id = $db->lastId();
      $db->disableLast();
      $isDisabled = !self::$enabled;
      if (!$isDisabled) {
        self::disable();
      }

      if (!array_key_exists('old', $cfg)) {
        $cfg['ref'] = null;
        $cfg['val'] = null;
      } else if (
        Str::isUid($cfg['old']) &&
        self::$db->count(self::$table_uids, ['bbn_uid' => $cfg['old']])
      ) {
        $cfg['ref'] = $cfg['old'];
        $cfg['val'] = null;
      } else {
        $cfg['ref'] = null;
        $cfg['val'] = $cfg['old'];
      }

      // New row in the history table
      if ($res = $db->insert(self::$table, [
        'opr' => $cfg['operation'],
        'uid' => $cfg['line'],
        'col' => $cfg['column'],
        'val' => $cfg['val'],
        'ref' => $cfg['ref'],
        'tst' => self::$date ?: $cfg['chrono'],
        'usr' => self::$user
      ])) {
        // Set back the original last ID
        $db->setLastInsertId($id);
      }

      $db->enableLast();
      if (!$isDisabled) {
        self::enable();
      }

      return $res;
    }
    return 0;
  }

  /**
   * Get a string for the WHERE in the query with all the columns selection
   * @param string $table
   * @return string|null
   */
  private static function _getTableWhere(string $table): ?array
  {
    if (
      Str::checkName($table) &&
      ($db = self::_get_db()) &&
      ($database_obj = self::_get_database()) &&
      ($model = $database_obj->modelize($table))
    ) {
      $where_ar = [
        'logic' => 'OR',
        'conditions' => []
      ];
      foreach ($model['fields'] as $k => $f) {
        if (!empty($f['id_option'])) {
          $where_ar['conditions'][] = [
            'field' => 'col',
            'operator' => '=',
            'value' => $f['id_option']
          ];
        }
      }

      return $where_ar;
    }

    return null;
  }
}
