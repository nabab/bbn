<?php

namespace bbn\Appui;

use Exception;
use bbn\X;
use bbn\Appui\Database;
use bbn\Str;
use bbn\Db;
use bbn\Cache;
use bbn\Models\Tts\Singleton;

/**
 * Class History
 *
 * Manages database history tracking functionality.
 */
class HistoryMistral
{
  use Singleton;

  /** @var Db The DB connection */
  private $db;
  /** @var array A collection of DB connections  */
  private static $dbs = [];
  /** @var array A collection of DB structures */
  private static $structures = [];
  /** @var Database The database class which collects the columns IDs */
  private $database_obj;
  /** @var string Name of the database where the history table is */
  private $admin_db = '';
  /** @var string User's ID  */
  private $user;
  /** @var string Prefix of the history table */
  private $prefix = 'bbn_';
  /** @var float The current date can be overwritten if this variable is set */
  private $date;
  /** @var boolean Set to true once the initial configuration has been checked */
  private $ok = false;
  /** @var boolean Setting it to false avoid execution of history triggers */
  private $enabled = true;
  /** @var array The foregin links attached to history UIDs' table */
  private $links;

  /** @var string|bool The history table's name */
  public $table_uids = false;
  /** @var string|bool The history table's name */
  public $table = false;
  /** @var string The UIDs table */
  public $uids = 'uids';
  /** @var string The history default column's name */
  public $column = 'bbn_active';
  /** @var boolean */
  public $is_used = false;

  private $cache;
  private $cache_prefix;

  /**
   * History constructor.
   *
   * @param Db $db
   * @param array $cfg
   */
  public function __construct(Db $db, array $cfg = [])
  {
    self::singletonInit($this);
    $this->init($db, $cfg);
  }

  /**
   * Returns the history table name.
   *
   * @return string|bool
   */
  public function getTable()
  {
    return $this->table;
  }

  /**
   * Returns the UIDs table name.
   *
   * @return string|bool
   */
  public function getTableUids()
  {
    return $this->table_uids;
  }

  /**
   * Returns the column's corresponding option's ID.
   *
   * @param string $column
   * @param string $table
   * @return null|string
   */
  public function getIdColumn(string $column, string $table): ?string
  {
    if (
      ($db = $this->_get_db()) &&
      ($full_table = $db->tfn($table)) &&
      ($database_obj = $this->_get_database())
    ) {
      [$database, $table] = explode('.', $full_table);
      return $database_obj->columnId($column, $table, $database);
    }
    return null;
  }

  /**
   * Initializes the history tracking.
   *
   * @param Db $db
   * @param array $cfg
   * @return void
   */
  public function init(Db $db, array $cfg = []): void
  {
    /** @var string $hash Unique hash for this DB connection (so we don't init twice a same connection) */
    $hash = $db->getHash();
    if (!in_array($hash, self::$dbs, true) && $db->check()) {
      // Adding the connection to the list of connections
      self::$dbs[] = $hash;
      /** @var Db db */
      $this->db = $db;
      $vars = get_class_vars(__CLASS__);
      foreach ($cfg as $cf_name => $cf_value) {
        if (array_key_exists($cf_name, $vars)) {
          $this->$cf_name = $cf_value;
        }
      }
      if (!$this->admin_db) {
        $this->admin_db = $this->db->getCurrent();
      }
      $this->table = $this->admin_db . '.' . $this->prefix . 'history';
      $this->table_uids = $this->admin_db . '.' . $this->prefix . 'history_uids';
      $this->ok = true;
      $this->is_used = true;
      $this->cache = Cache::getEngine();
      $this->cache_prefix = Str::encodeFilename(str_replace('\\', '/', self::class), true) . '/';
      $this->links = $this->db->getForeignKeys('bbn_uid', $this->prefix . 'history_uids', $this->admin_db);
      $this->db->setTrigger([$this, 'trigger']);
    }
  }

  /**
   * Sets cache data.
   *
   * @param string $id
   * @param mixed $data
   * @return void
   */
  public function setCache($id, $data): void
  {
    if ($this->cache) {
      $this->cache->set($this->cache_prefix . $id, $data, 3600);
    }
  }

  /**
   * Gets cached data.
   *
   * @param string $id
   * @return mixed|null
   */
  public function getCache($id)
  {
    if ($this->cache) {
      return $this->cache->get($this->cache_prefix . $id, 3600);
    }
    return null;
  }

  /**
   * Deletes cached data.
   *
   * @param string $id
   * @return mixed|null
   */
  public function deleteCache($id)
  {
    if ($this->cache) {
      return $this->cache->get($this->cache_prefix . $id, 3600);
    }
    return null;
  }

  /**
   * Checks if the history is initialized.
   *
   * @return bool
   */
  public function isInit(): bool
  {
    return $this->ok;
  }

  /**
   * Disables history tracking.
   *
   * @return void
   */
  public function disable(): void
  {
    $this->enabled = false;
  }

  /**
   * Enables history tracking.
   *
   * @return void
   */
  public function enable(): void
  {
    $this->enabled = true;
  }

  /**
   * Checks if history is enabled.
   *
   * @return bool
   */
  public function isEnabled(): bool
  {
    return $this->ok && ($this->enabled === true);
  }

  /**
   * Validates a timestamp.
   *
   * @param mixed $d
   * @return null|float
   */
  public function validTimestamp($d): ?float
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
   * Checks if all history parameters are set in order to read and write into history.
   *
   * @return bool
   */
  public function check(): bool
  {
    return
      isset($this->user, $this->table, $this->db) &&
      $this->isInit() &&
      $this->_get_db();
  }

  /**
   * Returns true if the given DB connection is configured for history.
   *
   * @param Db $db
   * @return bool
   */
  public function hasHistory(Db $db): bool
  {
    $hash = $db->getHash();
    return in_array($hash, self::$dbs, true);
  }

  /**
   * Effectively deletes a row (deletes the row, the history row and the ID row).
   *
   * @param string $id
   * @return bool
   */
  public function delete(string $id): bool
  {
    if ($id && ($db = $this->_get_db())) {
      return $db->delete($this->table_uids, ['bbn_uid' => $id]);
    }
    return false;
  }

  /**
   * Sets the "active" column name.
   *
   * @param string $column
   * @return void
   */
  public function setColumn(string $column): void
  {
    if (Str::checkName($column)) {
      $this->column = $column;
    }
  }

  /**
   * Gets the "active" column name.
   *
   * @return string the "active" column name
   */
  public function getColumn(): string
  {
    return $this->column;
  }

  /**
   * Sets the current date for history tracking.
   *
   * @param mixed $date
   * @return void
   */
  public function setDate($date): void
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
    $this->date = $date;
  }

  /**
   * Gets the current date for history tracking.
   *
   * @return float|null
   */
  public function getDate(): ?float
  {
    return $this->date;
  }

  /**
   * Unsets the current date for history tracking.
   *
   * @return void
   */
  public function unsetDate(): void
  {
    $this->date = null;
  }

  /**
   * Sets the history table name.
   *
   * @param string $db_name
   * @return void
   */
  public function setAdminDb(string $db_name): void
  {
    // Sets the history table name
    if (Str::checkName($db_name)) {
      $this->admin_db = $db_name;
      $this->table = $this->admin_db . '.' . $this->prefix . 'history';
    }
  }

  /**
   * Sets the user ID that will be used to fill the user_id field.
   *
   * @param mixed $user
   * @return void
   */
  public function setUser($user): void
  {
    // Sets the history table name
    if (Str::isUid($user)) {
      $this->user = $user;
    }
  }

  /**
   * Gets the user ID that is being used to fill the user_id field.
   *
   * @return null|string
   */
  public function getUser(): ?string
  {
    return $this->user;
  }

  /**
   * Gets all history records for a table.
   *
   * @param string $table
   * @param int $start
   * @param int $limit
   * @param string|null $dir
   * @return array
   */
  public function getAllHistory(string $table, int $start = 0, int $limit = 20, string|null $dir = null): array
  {
    if (
      ($db = $this->_get_db()) &&
      ($dbc = $this->_get_database()) &&
      ($id_table = $dbc->tableId($table, $this->db->getCurrent()))
    ) {
      $order = $dir && (Str::changeCase($dir, 'lower') === 'asc') ? 'ASC' : 'DESC';
      return $db->getColumnValues([
        'table' => $this->table_uids,
        'fields' => ['bbn_uid'],
        'join' => [
          [
            'table' => $this->table,
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
   * Gets the last modified lines for a table.
   *
   * @param string $table
   * @param int $start
   * @param int $limit
   * @return array
   */
  public function getLastModifiedLines(string $table, int $start = 0, int $limit = 20): array
  {
    $r = [];
    if (
      ($db = $this->_get_db()) &&
      ($dbc = $this->_get_database()) &&
      ($id_table = $dbc->tableId($table, $this->db->getCurrent()))
    ) {
      $tab = $db->escape($this->table);
      $tab_uids = $db->escape($this->table_uids);
      $uid = $db->cfn('bbn_uid', $this->table_uids, true);
      $active = $db->cfn($this->column, $this->table_uids, true);
      $id_tab = $db->cfn('bbn_table', $this->table_uids, true);
      $line = $db->cfn('uid', $this->table, true);
      $chrono = $db->escape('tst');
      $sql = <<< MYSQL
SELECT DISTINCT($line)
FROM $tab_uids
  JOIN $tab
    ON $uid = $line
WHERE $id_tab = ? AND $active = 1
ORDER BY $chrono
LIMIT $start, $limit
MYSQL;
      $r = $db->getColArray($sql, hex2bin($id_table));
    }
    return $r;
  }

  /**
   * Gets the next update for a table row.
   *
   * @param string $table
   * @param string $id
   * @param string|float $from_when
   * @param string|null $column
   * @return null|array
   */
  public function getNextUpdate(string $table, string $id, string|float  $from_when, string|null $column = null)
  {
    /** @todo To be redo totally with all the fields' IDs instead of the history column */
    if (
      Str::checkName($table) &&
      ($date = $this->validTimestamp($from_when)) &&
      ($db = $this->_get_db()) &&
      ($dbc = $this->_get_database()) &&
      ($id_table = $dbc->tableId($table))
    ) {
      $isDisabled = !$this->enabled;
      if (!$isDisabled) {
        $this->disable();
      }

      $tab = $db->escape($this->table);
      $tab_uids = $db->escape($this->table_uids);
      $uid = $db->cfn('bbn_uid', $this->table_uids);
      $id_tab = $db->cfn('bbn_table', $this->table_uids);
      $id_col = $db->cfn('col', $this->table);
      $line = $db->cfn('uid', $this->table);
      $usr = $db->cfn('usr', $this->table);
      $chrono = $db->cfn('tst', $this->table);
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
      } else if ($w = $this->_getTableWhere($table)) {
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
        $this->enable();
      }

      return $res;
    }

    return null;
  }

  /**
   * Gets the previous update for a table row.
   *
   * @param string $table
   * @param string $id
   * @param string|float $from_when
   * @param string|null $column
   * @return null|array
   */
  public function getPrevUpdate(string $table, string $id, string|float $from_when, string|null $column = null): ?array
  {
    if (
      Str::checkName($table) &&
      ($date = $this->validTimestamp($from_when)) &&
      ($dbc = $this->_get_database()) &&
      ($db = $this->_get_db())
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
      } else if ($w = $this->_getTableWhere($table)) {
        $where = $w;
      }

      return $db->rselect($this->table, [], [
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
   * Gets the next value for a table row column.
   *
   * @param string $table
   * @param string $id
   * @param string|float $from_when
   * @param mixed $column
   * @return bool|mixed
   */
  public function getNextValue(string $table, string $id, string|float $from_when, $column)
  {
    if ($r = $this->getNextUpdate($table, $id, $from_when, $column)) {
      return $r['ref'] ?: $r['val'];
    }
    return false;
  }

  /**
   * Gets the previous value for a table row column.
   *
   * @param string $table
   * @param string $id
   * @param string|float $from_when
   * @param mixed $column
   * @return bool|mixed
   */
  public function getPrevValue(string $table, string $id, string|float $from_when, $column)
  {
    if ($r = $this->getPrevUpdate($table, $id, $from_when, $column)) {
      return $r['ref'] ?: $r['val'];
    }
    return false;
  }

  /**
   * Gets the row state at a specific time.
   *
   * @param string $table
   * @param string $id
   * @param string|float $when
   * @param array $columns
   * @return array|null
   */
  public function getRowBack(string $table, string $id, string|float $when, array $columns = []): ?array
  {
    if (!($when = $this->validTimestamp($when))) {
      throw new Exception("The date $when is incorrect");
    } else if (
      ($db = $this->_get_db()) &&
      ($cfg = $this->getTableCfg($table))
    ) {
      // Time is after last modification: the current is given
      $isDisabled = !$this->enabled;
      if (!$isDisabled) {
        $this->disable();
      }

      if ($when >= time()) {
        $r = $db->rselect($table, $columns, [
          $cfg['primary'] => $id
        ]) ?: null;
      }
      // Time is before creation: null is given
      else if ($when < $this->getCreationDate($table, $id)) {
        $r = null;
      } else {
        // No columns = All columns
        if (count($columns) === 0) {
          $columns = array_keys($cfg['fields']);
        }
        $r = [];
        //die(var_dump($columns, $model['fields']));
        foreach ($columns as $col) {
          $tmp = null;
          if (isset($cfg['fields'][$col]['id_option'])) {
            if ($tmp = $db->rselect($this->table, ['val', 'ref'], [
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
        $this->enable();
      }

      return $r;
    }

    return null;
  }

  /**
   * Gets the value of a column at a specific time.
   *
   * @param string $table
   * @param string $id
   * @param string|float $when
   * @param string $column
   * @return bool|mixed
   */
  public function getValBack(string $table, string $id, string|float $when, string $column)
  {
    if ($row = $this->getRowBack($table, $id, $when, [$column])) {
      return $row[$column];
    }
    return false;
  }

  /**
   * Gets the creation date of a table row.
   *
   * @param string $table
   * @param string $id
   * @param bool $asString
   * @return null|float|string
   */
  public function getCreationDate(string $table, string $id, bool $asString = false): null|float|string
  {
    if ($res = $this->getCreation($table, $id)) {
      return $asString ? $res['date'] : $res['timestamp'];
    }

    return null;
  }

  /**
   * Gets the creation information of a table row.
   *
   * @param string $table
   * @param string $id
   * @return array|null
   */
  public function getCreation(string $table, string $id): ?array
  {
    $r = null;
    if (
      ($db = $this->_get_db()) &&
      ($cfg = $this->getTableCfg($table)) &&
      ($id_col = $this->getIdColumn($cfg['primary'], $table))
    ) {
      $isDisabled = !$this->enabled;
      if (!$isDisabled) {
        $this->disable();
      }

      $r = $db->rselect($this->table, ['date' => 'dt', 'timestamp' => 'tst', 'user' => 'usr'], [
        'uid' => $id,
        'col' => $id_col,
        'opr' => 'INSERT'
      ], [
        'tst' => 'DESC'
      ]);

      if (!$isDisabled) {
        $this->enable();
      }
    }

    return $r;
  }

  /**
   * Gets the last modification date of a table row.
   *
   * @param string $table
   * @param string $id
   * @param null $column
   * @return float|null
   */
  public function getLastDate(string $table, string $id, $column = null): ?float
  {
    if ($db = $this->_get_db()) {
      if (
        $column &&
        ($id_col = $this->getIdColumn($column, $table))
      ) {
        return $this->db->selectOne($this->table, 'tst', [
          'uid' => $id,
          'col' => $id_col
        ], [
          'tst' => 'DESC'
        ]);
      } elseif (!$column && ($where = $this->_getTableWhere($table))) {
        return $db->selectOne($this->table, 'tst', [
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
   * Gets the full history of a table row.
   *
   * @param string $table
   * @param string $id
   * @param string|null $column
   * @return array
   */
  public function getFullHistory(string $table, string $id, string|null $column = null): array
  {
    $res = [];
    if ($db = $this->_get_db()) {
      $cfg = $this->getTableCfg($table);
      $fields = [];
      foreach ($cfg['fields'] as $name => $f) {
        $fields[$f['id_option']] = $name;
      }

      $where = ['uid' => $id];
      if ($column) {
        $where['col'] = $this->database_obj->columnId($column, $table);
      }

      $origin = $db->rselect($table, [], [$cfg['primary'] => $id]);
      $all = $db->rselectAll($this->table, [], $where, ['tst' => 'ASC']);
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

  /**
   * Gets the history of a specific column for a table row.
   *
   * @param string $table
   * @param string $id
   * @param string $column
   * @return array
   */
  public function getColumnHistory(string $table, string $id, string $column)
  {
    return $this->getFullHistory($table, $id, $column);
  }

  /**
   * Gets all information about a given table.
   *
   * @param string $table
   * @param bool $force
   * @return null|array Table's full name
   */
  public function getTableCfg(string $table, bool $force = false): ?array
  {
    // Check history is enabled and table's name correct
    if (
      ($db = $this->_get_db()) &&
      ($dbc = $this->_get_database()) &&
      ($table = $db->tfn($table))
    ) {
      if ($force || !isset(self::$structures[$table])) {
        if (!$force && ($data = $this->getCache($table))) {
          self::$structures[$table] = $data;
          if (!empty(self::$structures[$table]['history'])) {
            return self::$structures[$table];
          }

          return null;
        }

        if ($model = $dbc->modelize($table)) {
          [$dbName, $tableName] = X::split($table, '.');
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
            $this->isLinked($table) &&
            isset($model['keys']['PRIMARY']) &&
            (count($model['keys']['PRIMARY']['columns']) === 1) &&
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
            $refs = $db->findReferences("$tableName.$primary");
            self::$structures[$table]['refs'] = array_map(fn($a) => [
              'db' => X::split($a, '.')[0],
              'table' => X::split($a, '.')[1],
              'col' => X::split($a, '.')[2]
            ], $refs);
            foreach (self::$structures[$table]['refs'] as &$r) {
              $refCfg = $db->modelize($r['table']);
              $r['nullable'] = $refCfg['fields'][$r['col']]['null'] ?? false;
              $keys = $refCfg['cols'][$r['col']];
              foreach ($keys as $k) {
                if ((count($refCfg['keys'][$k]['columns']) === 1) && $refCfg['keys'][$k]['constraint']) {
                  $r['constraint'] = $refCfg['keys'][$k]['constraint'];
                  $r['delete'] = $refCfg['keys'][$k]['delete'] ?? null;
                  $r['update'] = $refCfg['keys'][$k]['update'] ?? null;
                  break;
                }
              }
            }

            self::$structures[$table]['constraints'] = [];
            foreach ($model['keys'] as $name => $key) {
              if (!empty($key['unique']) && ((count($key['columns']) > 1) || ($key['columns'][0] !== $primary))) {
                $toPush = [
                  'name' => $name,
                  'columns' => []
                ];

                foreach ($key['columns'] as $col) {
                  $toPush['columns'][] = [
                    'name' => $col,
                    'nullable' => empty($model['fields'][$col]['virtual']) ? !!$model['fields'][$col]['null'] : false
                  ];
                }

                array_push(self::$structures[$table]['unique'], $toPush);
              }

              if (!empty($key['ref_column']) && (count($key['columns']) === 1) && ($key['columns'][0] !== $primary)) {
                self::$structures[$table]['constraints'][$name] = [
                  'column' => $key['columns'][0],
                  'ref_table' => $key['ref_table'],
                  'ref_column' => $key['ref_column']
                ];
              }
            }

            self::$structures[$table]['fields'] = array_filter($model['fields'], function ($a) {
              return isset($a['id_option']);
            });
          }

          $this->setCache($table, self::$structures[$table]);
        }
      }
      // The table exists and has history
      if (isset(self::$structures[$table]) && !empty(self::$structures[$table]['history'])) {
        return self::$structures[$table];
      }
    }
    return null;
  }

  /**
   * Gets the configuration of all tables in a database.
   *
   * @param string|null $db
   * @param bool $force
   * @return array|null
   */
  public function getDbCfg(string|null $db = null, bool $force = false): ?array
  {
    if ($db = $this->_get_db()) {
      $res = [];
      $tables = $db->getTables($db);
      if ($tables && count($tables)) {
        foreach ($tables as $t) {
          if ($tmp = $this->getTableCfg($t, $force)) {
            $res[$t] = $tmp;
          }
        }
      }
      return $res;
    }
    return null;
  }

  /**
   * Checks if a table is linked to history tracking.
   *
   * @param string $table
   * @return bool
   */
  public function isLinked(string $table): bool
  {
    return ($db = $this->_get_db()) &&
      ($ftable = $db->tfn($table)) &&
      isset($this->links[$ftable]);
  }

  /**
   * Gets the foreign key links.
   *
   * @return array
   */
  public function getLinks()
  {
    return $this->links;
  }

  /**
   * Gets related IDs for a table row.
   *
   * @param string $id
   * @param string $table
   * @param array $relatedTables
   * @param int $depth
   * @param int $current
   * @param array $uids
   * @return array
   */
  public function getRelatedIds(
    string $id,
    string $table,
    array $relatedTables = [],
    int $depth = 2,
    int $current = 0,
    array $uids = []
  ): array {
    $uids = [$id];
    $noDirects = $relatedTables;
    $db = $this->_get_db();
    $primary = $db->getPrimary($table);
    if (count($primary) !== 1) {
      return $uids;
    }
    foreach ($db->getForeignKeys($primary[0], $table) as $tfn => $col) {
      $table = $db->tsn($tfn);
      if (($hcfg = History::getTableCfg($tfn)) && $hcfg['history']) {
        $allTables[] = $table;
        $dbModel = $db->modelize($tfn);
        $tableUids = $db->getColumnValues($tfn, $hcfg['primary'], [$col[0] => $id]);
        array_push($uids, ...$tableUids);
        if (!in_array($table, $noDirects)) {
          continue;
        }
        foreach ($dbModel['fields'] as $colName => $colCfg) {
          if (empty($colCfg['key']) || ($colCfg['key'] === 'primary')) {
            continue;
          }
          if (!empty($dbModel['cols'][$colName])) {
            foreach ($dbModel['cols'][$colName] as $keyName) {
              if (!empty($dbModel['keys'][$keyName]['ref_table']) && (count($dbModel['keys'][$keyName]['columns']) === 1)) {
                $stable = $db->tsn($dbModel['keys'][$keyName]['ref_table']);
                if (in_array($stable, [$table])) {
                  continue;
                }

                if (($shcfg = History::getTableCfg($stable)) && $shcfg['history']) {
                  $allTables[] = $stable;
                  if ($stableUids = $db->getColumnValues($table, $colName, [$hcfg['primary'] => $tableUids])) {
                    array_push($uids, ...$stableUids);
                  }
                }
                break;
              }
            }
          }
        }
      }
    }

    $uids = array_unique($uids);
    $current++;
    if ($depth > $current) {
    }

    return $uids;
  }

  /**
   * Merges multiple records into one.
   *
   * @param array $ids
   * @param string $table
   * @param Db $db
   * @param null|string $main
   * @return bool
   */
  public function fusion(array $ids, string $table, Db $db, $main = null): bool
  {
    if (!$this->check()) {
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
      $this->table_uids,
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
      $this->table_uids,
      'bbn_active',
      [
        'bbn_uid' => $source
      ]
    );

    if (!$isActive) {
      throw new Exception(X::_("Main record is deleted"));
    }

    $db->update(
      $this->table,
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

    $isDisabled = !$this->enabled;
    if (!$isDisabled) {
      $this->disable();
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
        $this->table,
        ['uid' => $source],
        [
          'uid' => $id,
          'opr' => ['UPDATE', 'RESTORE', 'DELETE']
        ]
      );
      $num += (int)$db->delete(
        $this->table_uids,
        ['bbn_uid' => $id]
      );
    }

    if ($isTriggerEnabled) {
      $db->enableTrigger();
    }

    if (!$isDisabled) {
      $this->enable();
    }

    return (bool)$num;
  }

  /**
   * Upgrades a table to support history tracking.
   *
   * @param string $table
   * @param null|string $idUser
   * @param null|int|string $date
   * @return array
   */
  public function upgrade(string $table, ?string $idUser = null, null|int|string $date = null): array
  {
    $res = ['success' => false, 'total' => 0, 'updated' => 0, 'inserted' => 0];
    if ($db = $this->_get_db()) {
      if (!$idUser) {
        $idUser = constant('BBN_EXTERNAL_USER_ID');
      }
      if (!$date) {
        $date = time();
      }

      $database = $this->database_obj;
      $structure = $db->modelize($table, true);
      $ostructure = $database->modelize($table);
      if ($ostructure['id_option']) {
        $areTriggerEnabled = $db->isTriggerEnabled();
        $this->setUser($idUser);
        $this->setDate($date);
        $dbId = $database->dbIdFromTable($ostructure['id_option']);
        $fields = [];
        $primary = null;
        if (isset($structure['keys']['PRIMARY'])) {
          $fields = $structure['keys']['PRIMARY']['columns'];
          if (count($fields) > 1) {
            try {
              $db->dropKey($table, 'PRIMARY');
            } catch (Exception $e) {
              $res['error'] = $e->getMessage();
            }

            if (empty($res['error'])) {
              $structure = $db->modelize($table, true);
              $structure['keys'] = [
                X::join($fields, '_') => [
                  'columns' => $fields,
                  'unique' => 1
                ]
              ];
              //X::ddump($db->getCreateKeys($table, $ncfg), $ncfg);
              try {
                $db->createKeys($table, $structure);
                $structure = $db->modelize($table, true);
              } catch (Exception $e) {
                $res['error'] = $e->getMessage();
              }
            }
          } else {
            $primary = $fields[0];
          }
        } else {
          foreach ($structure['keys'] as $k => $key) {
            if (!empty($key['unique'])) {
              $fields = $key['columns'];
              break;
            }
          }
        }

        if (empty($res['error'])) {
          if (!isset($primary)) {
            $primary = 'id';
          }

          $db->disableTrigger();
          $data = $db->rselectAll($table, $fields, isset($structure['keys']['PRIMARY']) ? [$primary => null] : []);
          $res['total'] = count($data);
          if ($areTriggerEnabled) {
            $db->enableTrigger();
          }

          if (!isset($structure['keys']['PRIMARY'])) {
            if (!isset($structure['fields']['id'])) {
              $db->alter($table, [
                [
                  'alter_type' => 'add',
                  'name' => 'id',
                  'type' => 'binary',
                  'maxlength' => 16,
                  'null' => true,
                  'defaultExp' => 'NULL',
                  'first' => true
                ]
              ]);
              $structure = $db->modelize($table, true);
            }
            $database->importTable($table, $dbId);
            $ostructure = $database->modelize($table);
            $db->disableTrigger();
            foreach ($data as &$d) {
              $id = X::makeUid();
              while ($db->selectOne('bbn_history_uids', 'bbn_uid', ['bbn_uid' => $id])) {
                $id = X::makeUid();
              }

              $res['updated'] += $db->update($table, ['id' => $id], $d);
              $d[$primary] = $id;
              //$db->insert
            }
            unset($d);

            $structure['fields']['id']['key'] = 'PRI';
            $structure['keys'] = [
              'PRIMARY' => [
                'columns' => ['id'],
                'unique' => 1
              ]
            ];
            try {
              $db->createKeys($table, $structure);
              $structure = $db->modelize($table, true);
              $database->importTable($table, $dbId);
              $ostructure = $database->modelize($table);
            } catch (Exception $e) {
              $res['error'] = $e->getMessage();
            }

            if ($areTriggerEnabled) {
              $db->enableTrigger();
            }
          }
        }

        if (empty($res['error']) && $structure['keys']['PRIMARY']['ref_table'] !== History::$table_uids) {
          $res['inserted'] += $this->insertUid($table, array_map(fn($d) => $d['id'], $data), true, $ostructure['fields'][$primary]['id_option']);
          $structure = $db->modelize($table, true);
          $structure['keys'] = [
            'PRIMARY' => [
              'columns' => [$primary],
              'ref_table' => self::$table_uids,
              'ref_column' => 'bbn_uid',
              'update' => "CASCADE",
              'delete' => "CASCADE",
              'unique' => 1
            ]
          ];
          try {
            $db->createConstraints($table, $structure);
            $structure = $db->modelize($table, true);
            $database->importTable($table, $dbId);
            $res['success'] = true;
          } catch (Exception $e) {
            $res['deleted'] = 0;
            foreach ($data as $d) {
              if (
                $db->deleteIgnore($this->table, ['uid' => $d[$primary]]) ||
                $db->deleteIgnore($this->table_uids, ['bbn_uid' => $d[$primary]])
              ) {
                $res['deleted']++;
              }
            }

            $res['error'] = $e->getMessage();
          }
        } else {
          $res['error'] = X::_("The table already has a primary key linked to the history table");
        }
      }
    }

    return $res;
  }

  /**
   * Inserts UID records into the history tracking tables.
   *
   * @param string $table
   * @param array|string $id
   * @param bool $withInsert
   * @param null|string $idCol
   * @return int
   */
  public function insertUid(string $table, array|string $id, bool $withInsert = true, ?string $idCol = null): int
  {
    $res = 0;
    if (($db = $this->_get_db())
      && ($dbc = $this->_get_database())
      && ($id_table = $dbc->tableId($table))
      && ($primary = $db->getPrimary($table))
      && (count($primary) === 1)
    ) {
      if (is_string($id)) {
        $id = [$id];
      }
      foreach ($id as $i) {
        $res += $db->insertIgnore($this->table_uids, [
          'bbn_uid' => $i,
          'bbn_table' => $id_table,
          'bbn_active' => 1
        ]);
      }
      if ($res && $withInsert) {
        $col = $idCol ?: $dbc->columnId($primary[0], $table);
        foreach ($id as $i) {
          $res += $db->insert($this->table, [
            'uid' => $i,
            'col' => $col,
            'opr' => 'INSERT',
            'tst' => $this->getDate(),
            'usr' => $this->getUser()
          ]);
        }
      }
    }

    return $res;
  }

  /**
   * The function used by the db trigger.
   *
   * This will basically execute the history query if it's configured for.
   *
   * @param array $cfg
   * @return array The $cfg array, modified or not
   */
  public function trigger(array $cfg): array
  {
    if (!$this->isEnabled() || !($db = $this->_get_db())) {
      return $cfg;
    }
    $tables = $cfg['tables'] ?? (array)$cfg['table'];
    // Will return false if disabled, the table doesn't exist, or doesn't have history
    if (
      ($cfg['kind'] === 'SELECT') &&
      ($cfg['moment'] === 'before') &&
      !empty($tables) &&
      !in_array($db->tfn($this->table), $cfg['tables_full'], true) &&
      !in_array($db->tfn($this->table_uids), $cfg['tables_full'], true)
    ) {
      $change = 0;
      if (!isset($cfg['history'])) {
        $cfg['history'] = [];
        $new_join = [];
        foreach ($cfg['join'] as $t) {
          $model = $db->modelize($t['table']);
          if (
            isset($model['keys']['PRIMARY']) &&
            ($model['keys']['PRIMARY']['ref_table'] === $db->tsn($this->table_uids))
          ) {
            $change++;
            if (!isset($t['join'])) {
              $t['join'] = [];
            }
            $t['join'][] = [
              'table' => $this->table_uids,
              'alias' => $db->tsn($this->table_uids) . $change,
              'on' => [
                'conditions' => [
                  [
                    'field' => $db->cfn('bbn_uid', $this->table_uids . $change),
                    'operator' => 'eq',
                    'exp' => $db->cfn($model['keys']['PRIMARY']['columns'][0], $t['alias'] ?? $t['table'], true)
                  ],
                  [
                    'field' => $db->cfn('bbn_active', $this->table_uids . $change),
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
              if ($j['table'] !== $this->table_uids) {
                $model = $db->modelize($j['table']);
                if (
                  isset($model['keys']['PRIMARY']) &&
                  ($model['keys']['PRIMARY']['ref_table'] === $db->csn($this->table_uids))
                ) {
                  $change++;
                  $t['join'][] = [
                    'table' => $this->table_uids,
                    'alias' => $db->tsn($this->table_uids) . $change,
                    'on' => [
                      'conditions' => [
                        [
                          'field' => $db->cfn('bbn_uid', $this->table_uids . $change),
                          'operator' => 'eq',
                          'exp' => $db->cfn($model['keys']['PRIMARY']['columns'][0], $t['alias'] ?? $t['table'], true)
                        ],
                        [
                          'field' => $db->cfn('bbn_active', $this->table_uids . $change),
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
              'table' => $this->table_uids,
              'alias' => $db->tsn($this->table_uids) . $change,
              'on' => [
                'conditions' => [
                  [
                    'field' => $db->cfn($this->table_uids . $change . '.bbn_uid'),
                    'operator' => 'eq',
                    'exp' => $db->cfn($model['keys']['PRIMARY']['columns'][0], is_string($alias) ? $alias : $table, true)
                  ],
                  [
                    'field' => $db->cfn($this->table_uids . $change . '.bbn_active'),
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
      ($s = $this->getTableCfg($table))
    ) {
      // This happens before the query is executed
      if ($cfg['moment'] === 'before') {
        $primary_where = false;
        $primary_defined = false;
        $primary_value = false;
        $idx1 = X::search($cfg['values_desc'], ['primary' => true]);
        if ($idx1 !== null) {
          $primary_where = $cfg['values'][$idx1];
        }
        $idx = array_search($s['primary'], $cfg['fields'], true);
        if (($idx !== false) && isset($cfg['values'][$idx])) {
          $primary_defined = empty($cfg['generate_id']) ? true : false;
          $primary_value = $cfg['values'][$idx];
        }

        switch ($cfg['kind']) {

          case 'INSERT':
            // If the primary is specified and already exists in a row in deleted state
            // (if it exists in active state, DB will return its standard error but it's not this class' problem)
            if (!$primary_defined) {
              // Checks if there is a unique value (non based on UID)
              foreach ($s['unique'] as $key) {
                $fields = [];
                $exit = false;
                foreach ($key['columns'] as $col) {
                  $col_idx = array_search($col, $cfg['fields'], true);
                  if (($col_idx === false) || is_null($cfg['values'][$col_idx])) {
                    $exit = true;
                    break;
                  } else {
                    $fields[] = [
                      'field' => $col['name'],
                      'operator' => 'eq',
                      'value' => $cfg['values'][$col_idx]
                    ];
                  }
                }
                if ($exit) {
                  continue;
                }

                $isDisabled = !$this->enabled;
                if (!$isDisabled) {
                  $this->disable();
                }

                if ($tmp = $db->selectOne([
                  'tables' => [$table],
                  'fields' => [$s['primary']],
                  'join' => [[
                    'table' => $this->table_uids,
                    'on' => [[
                      'field' => $db->cfn('bbn_uid', $this->table_uids),
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
                    $this->enable();
                  }

                  break;
                }

                if (!$isDisabled) {
                  $this->enable();
                }
              }
            }
            if (
              $primary_defined &&
              ($db->selectOne($this->table_uids, $this->column, ['bbn_uid' => $primary_value]) === 0) &&
              //($all = self::$db->rselect($table, [], [$s['primary'] => $primary_value]))
              ($all = $this->db->rselect([
                'table' => $table,
                'fields' => $cfg['fields'],
                'join' => [[
                  'table' => $this->table_uids,
                  'on' => [
                    'conditions' => [[
                      'field' => $s['primary'],
                      'exp' => 'bbn_uid'
                    ], [
                      'field' => $this->column,
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

              if ($cfg['value'] = $this->db->update($this->table_uids, ['bbn_active' => 1], [
                ['bbn_uid', '=', $primary_value]
              ])) {
                // Without this the record won't be write in bbn_history. Added by Mirko
                $cfg['trig'] = true;
                // --------
                if (count($update) > 0) {
                  $this->enable();
                  $this->db->update($table, $update, [
                    $s['primary'] => $primary_value
                  ]);
                }
                $cfg['history'][] = [
                  'operation' => 'RESTORE',
                  'column' => $s['fields'][$s['primary']]['id_option'],
                  'line' => $primary_value
                ];
                $this->db->setLastInsertId($primary_value);
              }

              if (!$isDisabled) {
                $this->enable();
              }
            } else {
              $isDisabled = !$this->enabled;
              if (!$isDisabled) {
                $this->disable();
              }

              if ($primary_defined && !$this->db->count($table, [$s['primary'] => $primary_value])) {
                $primary_defined = false;
              }
              if (!$primary_defined && $this->db->insertIgnore($this->table_uids, [
                'bbn_uid' => $primary_value,
                'bbn_table' => $s['id']
              ])) {
                $cfg['history'][] = [
                  'operation' => 'INSERT',
                  'column' => isset($s['fields'][$s['primary']]) ? $s['fields'][$s['primary']]['id_option'] : null,
                  'line' => $primary_value
                ];
                $this->db->setLastInsertId($primary_value);
              }

              if (!$isDisabled) {
                $this->enable();
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
            $tmp = [];
            foreach ($cfg['fields'] as $i => $f) {
              $tmp[$f] = $cfg['values'][$i];
            }
            if ($primary_where) {
              $fields = $cfg['fields'];
              $isDefined = false;
              foreach ($s['unique'] as $unique) {
                $isDefined = count(X::filter($unique['columns'], fn($a) => in_array($a['name'], $cfg['fields'], true))) ? $unique : false;
                if ($isDefined) {
                  foreach ($isDefined['columns'] as $a) {
                    if (!in_array($a['name'], $fields, true)) {
                      $fields[] = $a['name'];
                    }
                  }
                  break;
                }
              }

              $isDisabled = !$this->enabled;
              if (!$isDisabled) {
                $this->disable();
              }
              $row = $this->db->rselect($table, $fields, [$s['primary'] => $primary_where]);
              if ($isDefined) {
                $search = [];
                foreach ($isDefined['columns'] as $col) {
                  $search[$col['name']] = in_array($col['name'], $cfg['fields']) ? $tmp[$col['name']] : $row[$col['name']];
                  if (is_null($search[$col['name']])) {
                    $search = [];
                    break;
                  }
                }
                if (!empty($search)) {
                  $search[] = [$s['primary'], '!=', $primary_where];
                  if ($checkRow = $this->db->selectOne($table, $s['primary'], $search)) {
                    // ONLY IF DELETED OTHERWISE REGULAR DB ERROR
                    $deleted = !$this->db->selectOne($this->table_uids, $this->column, ['bbn_uid' => $checkRow]);
                    if ($deleted) {
                      if (!X::getRow($isDefined['columns'], ['nullable' => true])) {
                        throw new Exception(X::_("Impossible to update the record with primary %s from %s because a unique constraint already exists in record %s, you should make one of the unique keys columns nullable", $primary_where, $table, $checkRow));
                      } else {
                        // Should have been done on the delete action
                      }
                    }
                  }
                }
              }
              if (!$isDisabled) {
                $this->enable();
              }

              foreach ($cfg['fields'] as $i => $idx) {
                $csn = $this->db->csn($idx);
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
            else if ($ids = $this->db->getColumnValues($table, $s['primary'], $cfg['filters'])) {
              // We won't execute the after trigger
              $cfg['trig'] = false;
              // Real query's execution will be prevented
              $cfg['run'] = false;
              $cfg['value'] = 0;
              foreach ($ids as $id) {
                $cfg['value'] += $this->db->update($table, $tmp, [$s['primary'] => $id]);
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
              $ids = $this->db->getColumnValues($table, $s['primary'], $cfg['filters']);
              foreach ($ids as $id) {
                $cfg['value'] += $this->db->delete($table, [$s['primary'] => $id]);
              }
            } else {
              $isDisabled = !$this->enabled;
              if (!$isDisabled) {
                $this->disable();
              }

              $this->enable();
              foreach ($s['refs'] as $ref) {
                if (!empty($ref['constraint']) && $db->count($ref['table'], [$ref['col'] => $primary_where])) {
                  if ($ref['delete'] === 'RESTRICT') {
                    if ($ref['table'] !== $this->db->tsn($this->table)) {
                      throw new Exception(X::_(
                        "Impossible to delete the record with primary %s from %s because it is referenced in the table %s",
                        $primary_where,
                        $table,
                        $ref['table']
                      ));
                    }
                  } elseif ($ref['delete'] === 'SET NULL') {
                    $this->db->update($ref['table'], [$ref['col'] => null], [$ref['col'] => $primary_where]);
                  } elseif ($ref['delete'] === 'CASCADE') {
                    $this->db->delete($ref['table'], [$ref['col'] => $primary_where]);
                  } elseif ($ref['delete'] !== 'NO ACTION') {
                    throw new Exception(X::_("Impossible to find what to do with the record referenced in the table %s", $ref['table']));
                  }
                }
              }
              $this->disable();

              foreach ($s['unique'] as $unique) {
                foreach ($unique['columns'] as $col) {
                  if (!$col['nullable']) {
                    continue;
                  }

                  $old = $this->db->selectOne($table, $col['name'], [$s['primary'] => $primary_where]);
                  $this->db->update($table, [$col['name'] => null], [$s['primary'] => $primary_where]);
                  if (!isset($s['fields'][$col['name']])) {
                    X::log([$col['name'], $s], '_toDoHistoryStructureError');
                    continue;
                  }
                  $cfg['history'][] = [
                    'operation' => 'UPDATE',
                    'column' => $s['fields'][$col['name']]['id_option'],
                    'line' => $primary_where,
                    'old' => $old
                  ];
                }
              }

              $cfg['value'] = $this->db->update($this->table_uids, [
                'bbn_active' => 0
              ], [
                'bbn_uid' => $primary_where
              ]);
              //var_dump("HIST", $primary_where);
              if (!$isDisabled) {
                $this->enable();
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
          $this->_insert($h);
        }
        unset($cfg['history']);
      }
    }
    return $cfg;
  }

  /**
   * Returns the primary value from a configuration array.
   *
   * @param array $cfg
   * @return string|null
   */
  private function get_primary_value(array $cfg): ?string
  {
    $primary = null;
    $idx = X::search($cfg['values_desc'], ['primary' => true]);
    if ($idx !== null) {
      $primary = $cfg['values'][$idx];
    }

    if (isset($cfg['primary'], $cfg['fields'])) {
      $idx = array_search($cfg['primary'], $cfg['fields'], true);
      if (($idx !== false) && isset($cfg['values'][$idx])) {
        $primary = $cfg['values'][$idx];
      }
    }
    return $primary;
  }

  /**
   * Returns the database connection object.
   *
   * @return Db
   */
  private function _get_db(): ?Db
  {
    if ($this->db && $this->db->check()) {
      return $this->db;
    }
    return null;
  }

  /**
   * Returns an instance of the Database class.
   *
   * @return Database
   */
  private function _get_database(): ?Database
  {
    if ($this->check()) {
      if (!$this->database_obj && ($db = $this->_get_db())) {
        $this->database_obj = new Database($db);
      }
      return $this->database_obj;
    }
    return null;
  }

  /**
   * Adds a row in the history table.
   *
   * @param array $cfg
   * @return int
   */
  private function _insert(array $cfg): int
  {
    if (
      isset($cfg['column'], $cfg['line'], $cfg['chrono']) &&
      $this->check() &&
      ($db = $this->_get_db())
    ) {
      // Recording the last ID
      $id = $db->lastId();
      $db->disableLast();
      $isDisabled = !$this->enabled;
      if (!$isDisabled) {
        $this->disable();
      }

      if (!array_key_exists('old', $cfg)) {
        $cfg['ref'] = null;
        $cfg['val'] = null;
      } else if (
        Str::isUid($cfg['old']) &&
        $this->db->count($this->table_uids, ['bbn_uid' => $cfg['old']])
      ) {
        $cfg['ref'] = $cfg['old'];
        $cfg['val'] = null;
      } else {
        $cfg['ref'] = null;
        $cfg['val'] = $cfg['old'];
      }

      // New row in the history table
      if ($res = $db->insert($this->table, [
        'opr' => $cfg['operation'],
        'uid' => $cfg['line'],
        'col' => $cfg['column'],
        'val' => $cfg['val'],
        'ref' => $cfg['ref'],
        'tst' => $this->date ?: $cfg['chrono'],
        'usr' => $this->user
      ])) {
        // Set back the original last ID
        $db->setLastInsertId($id);
      }

      $db->enableLast();
      if (!$isDisabled) {
        $this->enable();
      }

      return $res;
    }
    return 0;
  }

  /**
   * Get a string for the WHERE in the query with all the columns selection.
   *
   * @param string $table
   * @return array|null
   */
  private function _getTableWhere(string $table): ?array
  {
    if (
      Str::checkName($table) &&
      ($db = $this->_get_db()) &&
      ($database_obj = $this->_get_database()) &&
      ($model = $database_obj->modelize($table))
    ) {
      $where_ar = [
        'logic' => 'OR',
        'conditions' => []
      ];
      foreach ($model['fields'] as $f) {
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
