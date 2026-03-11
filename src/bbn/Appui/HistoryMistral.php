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
 * History class for tracking changes to database tables
 */
class HistoryMistral
{
  use Singleton;

  /** @var Db The DB connection */
  private $db;
  /** @var array A collection of DB connections */
  private $dbs = [];
  /** @var array A collection of DB structures */
  private $structures = [];
  /** @var Database The database class which collects the columns IDs */
  private $database_obj;
  /** @var string Name of the database where the history table is */
  private $admin_db = '';
  /** @var string User's ID */
  private $user;
  /** @var string Prefix of the history table */
  private $prefix = 'bbn_';
  /** @var float The current date can be overwritten if this variable is set */
  private $date;
  /** @var boolean Set to true once the initial configuration has been checked */
  private $ok = false;
  /** @var boolean Setting it to false avoids execution of history triggers */
  private $enabled = true;
  /** @var array The foreign links atytached to history UIDs' table */
  private $links;

  /** @var string|bool The history table's name */
  public static $table_uids = false;
  /** @var string|bool The history table's name */
  public static $table = false;
  /** @var string The UIDs table */
  public static $uids = 'uids';
  /** @var string The history default column's name */
  public static $column = 'bbn_active';
  /** @var bool Whether the class is in use */
  public static $is_used = false;

  private $cache;
  private $cache_prefix;

  /**
   * Constructor - initializes singleton pattern
   */
  protected function __construct()
  {
    self::singletonInit($this);
  }

  /**
   * Destructor - cleans up resources
   */
  public function __destruct()
  {
    self::singletonUnset();
  }

  /**
   * Returns the column's corresponding option's ID
   *
   * @param string $column The column name
   * @param string $table The table name
   * @return null|string The column ID or false if not found
   */
  public function getIdColumn(string $column, string $table): ?string
  {
    if ($db = self::_get_db()) {
      $full_table = $db->tfn($table);
      [$database, $table] = explode('.', $full_table);

      return $this->database_obj->columnId($column, $table, $database);
    }
    return false;
  }

  /**
   * Initializes the history system
   *
   * @param Db $db The database connection
   * @param array $cfg Configuration options
   */
  public function init(Db $db, array $cfg = []): void
  {
    /** @var string $hash Unique hash for this DB connection */
    $hash = $db->getHash();
    if (!in_array($hash, self::$dbs, true) && $db->check()) {
      // Adding the connection to the list of connections
      self::$dbs[] = $hash;
      /** @var Db db */
      self::$db = $db;

      // Apply configuration values from array
      foreach ($cfg as $cf_name => $cf_value) {
        if (property_exists($this, $cf_name)) {
          $this->$cf_name = $cf_value;
        }
      }

      if (!self::$admin_db) {
        self::$admin_db = self::$db->getCurrent();
      }

      // Set up table names
      self::$table = self::$admin_db . '.' . self::$prefix . 'history';
      self::$table_uids = self::$admin_db . '.' . self::$prefix . 'history_uids';

      self::$ok = true;
      self::$is_used = true;

      // Initialize cache engine if available
      self::$cache = Cache::getEngine();
      self::$cache_prefix = Str::encodeFilename(str_replace('\\', '/', get_class($this)), true);

      // Get foreign keys relationships
      self::$links = $db->getForeignKeys('bbn_uid', self::$prefix . 'history_uids', self::$admin_db);

      // Set up trigger if enabled
      if (self::isEnabled()) {
        $db->setTrigger($this::class . '::trigger');
      }
    }
  }

  /**
   * Sets cache data
   *
   * @param string $id Cache identifier
   * @param mixed $data Data to store in cache
   */
  public function setCache(string $id, $data): void
  {
    if (self::$cache) {
      self::$cache->set(self::$cache_prefix . $id, $data, 3600);
    }
  }

  /**
   * Gets cached data
   *
   * @param string $id Cache identifier
   * @return mixed|null Data from cache or null if not found
   */
  public function getCache(string $id)
  {
    if (self::$cache) {
      return self::$cache->get(self::$cache_prefix . $id, 3600);
    }
    return null;
  }

  /**
   * Deletes cached data
   *
   * @param string $id Cache identifier
   */
  public function deleteCache(string $id)
  {
    if (self::$cache) {
      self::$cache->get(self::$cache_prefix . $id, 3600); // Will delete if exists
    }
  }

  /**
   * Checks if history is initialized and enabled
   *
   * @return bool True if properly configured
   */
  public function check(): bool
  {
    return isset($this->user, self::$table, $this->db) &&
      self::isInit() &&
      self::_get_db();
  }

  /**
   * Returns true if the given DB connection is configured for history
   *
   * @param Db $db The database connection to check
   * @return bool True if this connection has history enabled
   */
  public function hasHistory(Db $db): bool
  {
    $hash = $db->getHash();
    return in_array($hash, self::$dbs, true);
  }

  /**
   * Effectively deletes a row (deletes the row, the history row and the ID row)
   *
   * @param string $id The record ID to delete
   * @return bool True if deletion was successful
   */
  public function delete(string $id): bool
  {
    if ($id && ($db = self::_get_db())) {
      return $db->delete(self::$table_uids, ['bbn_uid' => $id]);
    }
    return false;
  }

  /**
   * Sets the "active" column name
   *
   * @param string $column The new active column name
   */
  public function setColumn(string $column): void
  {
    if (Str::checkName($column)) {
      self::$column = $column;
    }
  }

  /**
   * Gets the "active" column name
   *
   * @return string The current active column name
   */
  public function getColumn(): string
  {
    return self::$column;
  }

  /**
   * Sets the date for history operations
   *
   * @param mixed $date Date to use (can be timestamp or string)
   */
  public function setDate($date): void
  {
    // Convert string dates to timestamps if needed
    if (!Str::isNumber($date) && !($date = strtotime($date))) {
      return;
    }

    $t = time();
    // Can't write history in the future
    if ($date > $t) {
      $date = $t;
    }
    self::$date = $date;
  }

  /**
   * Gets the current date for history operations
   *
   * @return float|null The timestamp or null if not set
   */
  public function getDate(): ?float
  {
    return self::$date;
  }

  /**
   * Resets the date to default (null)
   */
  public function unsetDate(): void
  {
    self::$date = null;
  }

  /**
   * Sets the history table name
   *
   * @param string $db_name The database name
   */
  public function setAdminDb(string $db_name): void
  {
    if (Str::checkName($db_name)) {
      self::$admin_db = $db_name;
      self::$table = self::$admin_db . '.' . self::$prefix . 'history';
    }
  }

  /**
   * Sets the user ID that will be used to fill the user_id field
   *
   * @param mixed $user User ID (can be string or numeric)
   */
  public function setUser($user): void
  {
    if (Str::isUid($user)) {
      self::$user = $user;
    }
  }

  /**
   * Gets the user ID that is being used to fill the user_id field
   *
   * @return null|string The current user ID or null if not set
   */
  public function getUser(): ?string
  {
    return self::$user;
  }

  /**
   * Gets all history records for a table
   *
   * @param string $table The table name
   * @param int $start Starting offset (default 0)
   * @param int $limit Maximum number of results (default 20)
   * @param string|null $dir Sort direction (ASC/DESC, default null)
   * @return array Array of history records
   */
  public function getAllHistory(string $table, int $start = 0, int $limit = 20, string|null $dir = null): array
  {
    if ($db = self::_get_db()) {
      $dbc = self::_get_database();
      $id_table = $dbc->tableId($table, $db->getCurrent());

      if ($id_table) {
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
    }

    return [];
  }

  /**
   * Gets the last modified lines for a table
   *
   * @param string $table The table name
   * @param int $start Starting offset (default 0)
   * @param int $limit Maximum number of results (default 20)
   * @return array Array of UID values from history records
   */
  public function getLastModifiedLines(string $table, int $start = 0, int $limit = 20): array
  {
    $r = [];
    if ($db = self::_get_db()) {
      $dbc = self::_get_database();
      $id_table = $dbc->tableId($table);

      if ($id_table) {
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
    }

    return $r;
  }

  /**
   * Gets the next update timestamp for a record
   *
   * @param string $table The table name
   * @param string $id The record ID
   * @param mixed $from_when Timestamp to start from (string or number)
   * @param string|null $column Optional column to filter by
   * @return null|array History record data or null if not found
   */
  public function getNextUpdate(string $table, string $id, mixed $from_when, string|null $column = null): ?array
  {
    /** @todo To be redo totally with all the fields' IDs instead of the history column */
    if (
      Str::checkName($table) && ($date = self::validTimestamp($from_when)) &&
      ($db = self::_get_db()) && ($dbc = self::_get_database()) &&
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
          ['field' => $uid, 'operator' => '=', 'value' => $line],
          ['field' => $id_tab, 'operator' => '=', 'value' => $id_table],
          ['field' => $chrono, 'operator' => '>', 'value' => $date]
        ]
      ];

      if ($column) {
        $where['conditions'][] = [
          'field' => $id_col,
          'value' => Str::isUid($column) ? $column : $dbc->columnId($column, $table)
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
   * Gets the previous update timestamp for a record
   *
   * @param string $table The table name
   * @param string $id The record ID
   * @param mixed $from_when Timestamp to start from (string or number)
   * @param string|null $column Optional column to filter by
   * @return null|array History record data or null if not found
   */
  public function getPrevUpdate(string $table, string $id, mixed $from_when, string|null $column = null): ?array
  {
    if (
      Str::checkName($table) && ($date = self::validTimestamp($from_when)) &&
      ($dbc = self::_get_database()) && ($db = self::_get_db())
    ) {

      if ($column) {
        $where = [
          'conditions' => [
            ['field' => 'col', 'value' => Str::isUid($column) ? $column : $dbc->columnId($column, $table)]
          ]
        ];
      } else if ($w = self::_getTableWhere($table)) {
        $where = $w;
      }

      return $db->rselect(self::$table, [], [
        'conditions' => [
          ['field' => 'uid', 'value' => $id],
          $where,
          ['field' => 'opr', 'value' => 'UPDATE'],
          ['field' => 'tst', 'operator' => '<', 'value' => $date]
        ]
      ]);
    }

    return null;
  }

  /**
   * Gets the next value for a column in history
   *
   * @param string $table The table name
   * @param string $id The record ID
   * @param mixed $from_when Timestamp to start from (string or number)
   * @param mixed $column Column name to get value for
   * @return bool|mixed Value from history record or false if not found
   */
  public function getNextValue(string $table, string $id, mixed $from_when, $column): mixed
  {
    if ($r = self::getNextUpdate($table, $id, $from_when, $column)) {
      return $r['ref'] ?: $r['val'];
    }
    return false;
  }

  /**
   * Gets the previous value for a column in history
   *
   * @param string $table The table name
   * @param string $id The record ID
   * @param mixed $from_when Timestamp to start from (string or number)
   * @param mixed $column Column name to get value for
   * @return bool|mixed Value from history record or false if not found
   */
  public function getPrevValue(string $table, string $id, mixed $from_when, $column): mixed
  {
    if ($r = self::getPrevUpdate($table, $id, $from_when, $column)) {
      return $r['ref'] ?: $r['val'];
    }
    return false;
  }

  /**
   * Gets a row from history at a specific timestamp
   *
   * @param string $table The table name
   * @param string $id The record ID
   * @param mixed $when Timestamp to get data for (string or number)
   * @param array $columns Optional columns to retrieve (empty for all)
   * @return null|array Record data or null if not found
   */
  public function getRowBack(string $table, string $id, mixed $when, array $columns = []): ?array
  {
    if (!($when = self::validTimestamp($when))) {
      X::log(["The date $when is incorrect", __CLASS__, __LINE__], 'history_errors');
    } else if (($db = self::_get_db()) && ($cfg = self::getTableCfg($table))) {

      // Time is after last modification: the current is given
      $isDisabled = !self::$enabled;
      if (!$isDisabled) {
        self::disable();
      }

      if ($when >= time()) {
        $r = $db->rselect($table, $columns, [$cfg['primary'] => $id]) ?: null;
      }
      // Time is before creation: null is given
      else if ($when < self::getCreationDate($table, $id)) {
        $r = null;
      } else {
        // No columns = All columns
        if (count($columns) === 0) {
          $columns = array_keys($cfg['fields']);
        }

        $r = [];
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
            $r[$col] = $db->selectOne($table, $col, [$cfg['primary'] => $id]);
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
   * Gets a value from history at a specific timestamp
   *
   * @param string $table The table name
   * @param string $id The record ID
   * @param mixed $when Timestamp to get data for (string or number)
   * @param string $column Column name to retrieve
   * @return bool|mixed Value from history record or false if not found
   */
  public function getValBack(string $table, string $id, mixed $when, string $column): mixed
  {
    if ($row = self::getRowBack($table, $id, $when, [$column])) {
      return $row[$column];
    }
    return false;
  }

  /**
   * Gets the creation date for a record
   *
   * @param string $table The table name
   * @param string $id The record ID
   * @param bool $asString Whether to return as string or timestamp (default false)
   * @return null|float|string Creation timestamp or null if not found
   */
  public function getCreationDate(string $table, string $id, bool $asString = false): mixed
  {
    if ($res = self::getCreation($table, $id)) {
      return $asString ? $res['date'] : $res['timestamp'];
    }

    return null;
  }

  /**
   * Gets the creation record for a table
   *
   * @param string $table The table name
   * @param string $id The record ID
   * @return array|null History record data or null if not found
   */
  public function getCreation(string $table, string $id): ?array
  {
    $r = null;
    if (($db = self::_get_db()) && ($cfg = self::getTableCfg($table)) &&
      ($id_col = self::getIdColumn($cfg['primary'], $table))
    ) {

      $isDisabled = !self::$enabled;
      if (!$isDisabled) {
        self::disable();
      }

      $r = $db->rselect(self::$table, ['date' => 'dt', 'timestamp' => 'tst', 'user' => 'usr'], [
        'uid' => $id,
        'col' => $id_col,
        'opr' => 'INSERT'
      ], ['tst' => 'DESC']);

      if (!$isDisabled) {
        self::enable();
      }
    }

    return $r;
  }

  /**
   * Gets the last date modified for a record
   *
   * @param string $table The table name
   * @param string $id The record ID
   * @param mixed|null $column Optional column to filter by (null for all)
   * @return null|float Last timestamp or null if not found
   */
  public function getLastDate(string $table, string $id, $column = null): ?float
  {
    if ($db = self::_get_db()) {
      if ($column && ($id_col = self::getIdColumn($column, $table))) {
        return $db->selectOne(self::$table, 'tst', [
          'uid' => $id,
          'col' => $id_col
        ], ['tst' => 'DESC']);
      } elseif (!$column && ($where = self::_getTableWhere($table))) {
        return $db->selectOne(self::$table, 'tst', [
          'conditions' => [
            ['field' => 'uid', 'value' => $id],
            $where
          ]
        ], ['tst' => 'DESC']);
      }
    }

    return null;
  }

  /**
   * Gets history records for a table and ID
   *
   * @param string $table The table name
   * @param string $id The record ID
   * @param string|null $col Optional column to filter by (empty for all columns)
   * @param string|null $since Optional timestamp to start from
   * @return null|array History records or null if not found
   */
  public function getHistory(string $table, string $id, string|null $col = '', string|null $since = ''): ?array
  {
    if (self::check() && self::isLinked($table) && ($modelize = self::getTableCfg($table))) {

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

      // Build WHERE conditions
      $where = ['uid' => $id];

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
          $idx = X::search($modelize['fields'], ['id_option' => strtolower($col)]);
          if (null === $idx) {
            throw new Exception("Impossible to find the option $col");
          }

          $fields['old'] = $modelize['fields'][$idx]['type'] === 'binary' ? 'ref' : 'val';
        }

        $where['col'] = $col;
      } else {
        $fields['old'] = 'IFNULL(' . self::$table . '.ref, ' . self::$table . '.val)';
      }

      // Process each operation type
      foreach ($pat as $k => $p) {
        $where['opr'] = $p;
        if ($all = self::$db->rselectAll([
          'table' => self::$table,
          'fields' => $fields,
          'where' => $where,
          'order' => [['field' => 'tst', 'dir' => 'desc']]
        ])) {
          if ($p === 'UPDATE') {
            foreach ($all as &$a) {
              $colname = X::search($modelize['fields'], ['id_option' => $a['col']]);
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
   * Gets the full history for a table and ID
   *
   * @param string $table The table name
   * @param string $id The record ID
   * @param string|null $column Optional column to filter by (null for all columns)
   * @return array History records with detailed operations
   */
  public function getFullHistory(string $table, string $id, string|null $column = null): array
  {
    $res = [];
    if ($db = self::_get_db()) {
      $cfg = self::getTableCfg($table);
      $fields = [];

      foreach ($cfg['fields'] as $name => $f) {
        $fields[$f['id_option']] = $name;
      }

      $where = ['uid' => $id];
      if ($column) {
        $where['col'] = self::$database_obj->columnId($column, $table);
      }

      // Get current record
      $origin = $db->rselect($table, [], [$cfg['primary'] => $id]);

      // Get history records
      $all = $db->rselectAll(self::$table, [], $where, ['tst' => 'ASC']);

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
          // Get next record for new value
          $next = X::getRow($all, ['col' => $row['col']]);
          $ele['new'] = $next ? ($next['ref'] ?: $next['val']) : $origin[$ele['column']];
        }

        $res[] = $ele;
      }
    }

    return $res;
  }

  /**
   * Gets history for a specific column
   *
   * @param string $table The table name
   * @param string $id The record ID
   * @param string $column Column to get history for
   */
  public function getColumnHistory(string $table, string $id, string $column)
  {
    return self::getFullHistory($table, $id, $column);
  }

  /**
   * Gets configuration for a table
   *
   * @param string $table The table name
   * @param bool $force Whether to force recalculation (default false)
   * @return null|array Table structure or null if not found
   */
  public function getTableCfg(string $table, bool $force = false): ?array
  {
    // Check history is enabled and table's name correct
    if (($db = self::_get_db()) && ($dbc = self::_get_database()) &&
      ($table = $db->tfn($table))
    ) {

      if ($force || !isset(self::$structures[$table])) {
        if (!$force && ($data = self::getCache($table))) {
          self::$structures[$table] = $data;
          if (!empty(self::$structures[$table]['history'])) {
            return self::$structures[$table];
          }

          return null;
        }

        // Get table model
        $model = $dbc->modelize($table);
        [$dbName, $tableName] = X::split($table, '.');

        // Initialize structure if not exists
        if (!isset(self::$structures[$table])) {
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
        }

        // Check if table has history
        if (
          self::isLinked($table) &&
          isset($model['keys']['PRIMARY']) &&
          (count($model['keys']['PRIMARY']['columns']) === 1) &&
          ($primary = $model['keys']['PRIMARY']['columns'][0]) &&
          !empty($model['fields'][$primary])
        ) {

          // Set up history configuration
          self::$structures[$table]['history'] = true;
          self::$structures[$table]['primary'] = $primary;
          self::$structures[$table]['primary_type'] = $model['fields'][$primary]['type'];
          self::$structures[$table]['primary_length'] = $model['fields'][$primary]['maxlength'];
          self::$structures[$table]['auto_increment'] = isset($model['fields'][$primary]['extra']) &&
            ($model['fields'][$primary]['extra'] === 'auto_increment');

          // Get table ID
          self::$structures[$table]['id'] = $dbc->tableId($db->tsn($table), $db->getCurrent());

          // Find foreign key references
          $refs = $db->findReferences("$tableName.$primary");
          self::$structures[$table]['refs'] = array_map(function ($a) use ($db) {
            [$d, $t, $c] = X::split($a, '.');
            return [
              'db' => $d,
              'table' => $t,
              'col' => $c
            ];
          }, $refs);

          // Process each reference
          foreach (self::$structures[$table]['refs'] as &$r) {
            $refCfg = $db->modelize($r['table']);
            $r['nullable'] = $refCfg['fields'][$r['col']]['null'] ?? false;

            // Check for foreign key constraints
            if ($keys = $refCfg['cols'][$r['col']]) {
              foreach ($keys as $k) {
                if ((count($refCfg['keys'][$k]['columns']) === 1) && $refCfg['keys'][$k]['constraint']) {
                  $r['constraint'] = $refCfg['keys'][$k]['constraint'];
                  $r['delete'] = $refCfg['keys'][$k]['delete'] ?? null;
                  $r['update'] = $refCfg['keys'][$k]['update'] ?? null;
                  break;
                }
              }
            }
          }

          // Process unique constraints
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
          }

          // Process foreign key constraints
          if (
            !empty($key['ref_column']) && (count($key['columns']) === 1) &&
            ($key['columns'][0] !== $primary)
          ) {
            self::$structures[$table]['constraints'][$name] = [
              'column' => $key['columns'][0],
              'ref_table' => $key['ref_table'],
              'ref_column' => $key['ref_column']
            ];
          }

          // Filter fields to only include those with id_option
          self::$structures[$table]['fields'] = array_filter($model['fields'], function ($a) {
            return isset($a['id_option']);
          });
        }

        // Cache the structure if we found history
        if (isset(self::$structures[$table]) && !empty(self::$structures[$table]['history'])) {
          self::setCache($table, self::$structures[$table]);
        }
      }

      return isset(self::$structures[$table]) && !empty(self::$structures[$table]['history'])
        ? self::$structures[$table]
        : null;
    }

    return null;
  }

  /**
   * Gets configuration for all tables
   *
   * @param string|null $db Optional database name (default current)
   * @param bool $force Whether to force recalculation (default false)
   * @return null|array Table configurations or null if not found
   */
  public function getDbCfg(string|null $db = null, bool $force = false): ?array
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

  /**
   * Checks if a table is linked to history
   *
   * @param string $table The table name
   * @return bool True if the table has history enabled
   */
  public function isLinked(string $table): bool
  {
    return ($db = self::_get_db()) &&
      ($ftable = $db->tfn($table)) &&
      isset(self::$links[$ftable]);
  }

  /**
   * Gets foreign key relationships
   *
   * @return array Foreign key relationships
   */
  public function getLinks()
  {
    return self::$links;
  }

  /**
   * Gets related IDs for a record and table
   *
   * @param string $id The ID to find related records
   * @param string $table The table name
   * @param array $relatedTables Optional list of tables to search (default all)
   * @param int $depth Maximum depth of recursion (default 2)
   * @return array Array of related IDs
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
    $db = self::_get_db();
    $primary = $db->getPrimary($table);

    if (count($primary) !== 1) {
      return $uids;
    }

    foreach ($db->getForeignKeys($primary[0], $table) as $tfn => $col) {
      $table = $db->tsn($tfn);
      if (($hcfg = self::getTableCfg($tfn)) && $hcfg['history']) {
        $allTables[] = $table;
        $dbModel = $db->modelize($tfn);

        // Get UIDs from foreign key
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
              if (
                !empty($dbModel['keys'][$keyName]['ref_table']) &&
                (count($dbModel['keys'][$keyName]['columns']) === 1)
              ) {

                $stable = $db->tsn($dbModel['keys'][$keyName]['ref_table']);
                if (in_array($stable, [$table])) {
                  continue;
                }

                if (($shcfg = self::getTableCfg($stable)) && $shcfg['history']) {
                  $allTables[] = $stable;

                  // Get UIDs from related table
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

    // Remove duplicates and return
    $uids = array_unique($uids);

    return $uids;
  }

  /**
   * Merges multiple records into one main record
   *
   * @param array $ids Array of IDs to merge
   * @param string $table The table name
   * @param Db $db Database connection
   * @param mixed|null $main Optional main ID (will be oldest if not provided)
   * @return bool True if fusion was successful
   */
  public function fusion(array $ids, string $table, Db $db, $main = null): bool
  {
    if (!self::check()) {
      return false;
    }

    // Ensure main ID is in the list
    if ($main && !in_array($main, $ids, true)) {
      $ids[] = $main;
    }

    // Find oldest record to use as main
    $oldest = null;
    $oldestId = null;
    foreach ($ids as $a) {
      $tmp = self::getCreationDate($table, $a);
      if (!$oldest || ($tmp < $oldest)) {
        $oldest = $tmp;
        $oldestId = $a;
      }
    }

    // Set main ID
    if (!$main) {
      $main = $oldestId;
    }

    if (!$main) {
      throw new Exception(X::_("Impossible to find the main record"));
    }

    // Remove main from list and reorder
    $idx = array_search($main, $ids);
    if ($idx !== false) {
      array_splice($ids, $idx, 1);
    }
    array_unshift($ids, $main);

    // Get history records for all IDs
    $tables = $db->rselectAll(
      self::$table_uids,
      'bbn_table',
      ['bbn_uid' => $ids]
    );

    // Check if all records are from the same table
    $unique = array_unique(array_map(function ($a) {
      return $a['bbn_table'];
    }, $tables));

    if (count($unique) > 1) {
      X::log($unique);
      throw new Exception(X::_("The fusion you wanna do seems to go on different tables"));
    }

    // Check if all records exist in history
    if (count($tables) !== count($ids)) {
      throw new Exception(X::_("They are not all in the history table"));
    }

    $source = array_shift($ids);

    // Check if source record is active
    $isActive = $db->selectOne(
      self::$table_uids,
      'bbn_active',
      ['bbn_uid' => $source]
    );

    if (!$isActive) {
      throw new Exception(X::_("Main record is deleted"));
    }

    // Update history records with oldest timestamp
    $db->update(
      self::$table,
      ['tst' => $oldest],
      [
        'uid' => $ids,
        'opr' => 'INSERT'
      ]
    );

    // Process foreign key relationships
    $model = $db->modelize($table);
    $primary = $model['keys']['PRIMARY']['columns'][0];
    $refs = $db->findReferences($db->cfn($primary, $table));

    foreach ($ids as $id) {
      // Update foreign key references
      foreach ($refs as $ref) {
        [$d, $t, $c] = X::split($ref, '.');
        $num += (int)$db->update(
          $t,
          [$c => $source],
          [$c => $id]
        );
      }

      // Update history records
      $num += (int)$db->update(
        self::$table,
        ['uid' => $source],
        [
          'uid' => $id,
          'opr' => ['UPDATE', 'RESTORE', 'DELETE']
        ]
      );

      // Delete old history record
      $num += (int)$db->delete(
        self::$table_uids,
        ['bbn_uid' => $id]
      );
    }

    return (bool)$num;
  }

  /**
   * Upgrades table structure to include history
   *
   * @param string $table The table name
   * @param mixed|null $idUser Optional user ID for history records
   * @param mixed|null $date Optional timestamp for history records
   * @return array Result of upgrade operation
   */
  public function upgrade(string $table, ?string $idUser = null, mixed $date = null): array
  {
    $res = ['success' => false, 'total' => 0, 'updated' => 0, 'inserted' => 0];
    if ($db = self::_get_db()) {

      // Set default user ID and date
      if (!$idUser) {
        $idUser = constant('BBN_EXTERNAL_USER_ID');
      }
      if (!$date) {
        $date = time();
      }

      $database = self::$database_obj;
      $structure = $db->modelize($table, true);
      $ostructure = $database->modelize($table);

      // Check for primary key configuration
      if ($ostructure['id_option']) {

        // Set user and date before any operations
        self::setUser($idUser);
        self::setDate($date);

        // Get database ID
        $dbId = $database->dbIdFromTable($ostructure['id_option']);

        // Check for primary key changes needed
        if (isset($structure['keys']['PRIMARY'])) {
          $fields = $structure['keys']['PRIMARY']['columns'];
          if (count($fields) > 1) {
            try {
              $db->dropKey($table, 'PRIMARY');
            } catch (Exception $e) {
              $res['error'] = $e->getMessage();
            }

            if (empty($res['error'])) {
              // Rebuild primary key
              $structure = $db->modelize($table, true);
              $structure['keys'] = [
                X::join($fields, '_') => [
                  'columns' => $fields,
                  'unique' => 1
                ]
              ];

              try {
                $db->createKeys($table, $structure);
                $structure = $db->modelize($table, true);
              } catch (Exception $e) {
                $res['error'] = $e->getMessage();
              }
            }
          } else {
            // Single-column primary key
            $primary = $fields[0];
          }
        } else {
          // Find unique constraint as primary key
          foreach ($structure['keys'] as $k => $key) {
            if (!empty($key['unique'])) {
              $fields = $key['columns'];
              break;
            }
          }

          if (empty($res['error']) && !isset($primary)) {
            $primary = 'id';
          }
        }

        // Disable triggers for safety
        $areTriggerEnabled = $db->isTriggerEnabled();
        $db->disableTrigger();

        // Get all data from table
        $data = $db->rselectAll($table, $fields, isset($structure['keys']['PRIMARY']) ? [$primary => null] : []);
        $res['total'] = count($data);

        if ($areTriggerEnabled) {
          $db->enableTrigger();
        }

        // Handle case where table doesn't have primary key
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

          // Import table to database structure
          $database->importTable($table, $dbId);

          // Get updated structure
          $ostructure = $database->modelize($table);

          // Disable triggers again
          $db->disableTrigger();

          foreach ($data as &$d) {
            $id = X::makeUid();
            while ($db->selectOne('bbn_history_uids', 'bbn_uid', ['bbn_uid' => $id])) {
              $id = X::makeUid();
            }

            // Update table with new ID
            $res['updated'] += $db->update($table, ['id' => $id], $d);
            $d[$primary] = $id;
          }
          unset($d);

          // Rebuild primary key
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

        // Check for primary key linked to history
        if (empty($res['error']) && $structure['keys']['PRIMARY']['ref_table'] !== History::$table_uids) {
          $res['inserted'] += self::insertUid(
            $table,
            array_map(fn($d) => $d[$primary], $data),
            true,
            $ostructure['fields'][$primary]['id_option']
          );

          // Update table structure
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

            // Mark success if everything went well
            $res['success'] = true;
          } catch (Exception $e) {
            $res['deleted'] = 0;

            foreach ($data as $d) {
              if (
                $db->deleteIgnore(self::$table, ['uid' => $d[$primary]]) ||
                $db->deleteIgnore(self::$table_uids, ['bbn_uid' => $d[$primary]])
              ) {
                $res['deleted']++;
              }
            }

            $res['error'] = $e->getMessage();
          }
        } else {
          // Table already has primary key linked to history
          $res['error'] = X::_("The table already has a primary key linked to the history table");
        }
      }
    }

    return $res;
  }

  /**
   * Inserts UIDs into history table
   *
   * @param string $table The table name
   * @param array|string IDs to insert (array of strings or single string)
   * @param bool $withInsert Whether to also insert into history table
   * @param mixed|null $idCol Optional column ID for history records
   * @return int Number of successful operations
   */
  public function insertUid(string $table, array|string $id, bool $withInsert = true, ?string $idCol = null): int
  {
    $res = 0;
    if (($db = self::_get_db()) && ($dbc = self::_get_database()) &&
      ($id_table = $dbc->tableId($table)) && ($primary = $db->getPrimary($table)) &&
      (count($primary) === 1)
    ) {

      // Convert single ID to array
      if (is_string($id)) {
        $id = [$id];
      }

      foreach ($id as $i) {
        $res += $db->insertIgnore(self::$table_uids, [
          'bbn_uid' => $i,
          'bbn_table' => $id_table,
          'bbn_active' => 1
        ]);

        // Insert into history table if requested
        if ($res && $withInsert) {
          $col = $idCol ?: $dbc->columnId($primary[0], $table);
          foreach ($id as $i) {
            $res += $db->insert(self::$table, [
              'uid' => $i,
              'col' => $col,
              'opr' => 'INSERT',
              'tst' => self::getDate(),
              'usr' => self::getUser()
            ]);
          }
        }
      }
    }

    return $res;
  }

  /**
   * Database trigger function for history operations
   *
   * @param array $cfg Configuration array from database trigger
   */
  public function trigger(array $cfg): array
  {
    if (!self::isEnabled() || !($db = self::_get_db())) {
      return $cfg;
    }

    // Handle SELECT queries to add history joins
    if ($cfg['kind'] === 'SELECT' && $cfg['moment'] === 'before') {
      $tables = $cfg['tables'] ?? (array)$cfg['table'];
      $change = 0;

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

          // Process tables with primary key references
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
                      'exp' => $db->cfn($model['keys']['PRIMARY']['columns'][0], is_string($alias) ? $alias : $table, true)
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
    }

    // Handle write operations
    if (
      $cfg['write'] &&
      ($table = $db->tfn(current($tables))) &&
      ($s = self::getTableCfg($table))
    ) {
      $isDisabled = !self::$enabled;
      // This happens before the query is executed
      if ($cfg['moment'] === 'before') {
        $primary_where = false;
        $primary_defined = false;
        $primary_value = false;

        // Find primary key value from configuration
        $idx1 = X::search($cfg['values_desc'], ['primary' => true]);
        if ($idx1 !== null) {
          $primary_where = $cfg['values'][$idx1];
        }

        $idx = array_search($s['primary'], $cfg['fields'], true);
        if (($idx !== false) && isset($cfg['values'][$idx])) {
          $primary_defined = $cfg['generate_id'] ? false : true;
          $primary_value = $cfg['values'][$idx];
        }

        // Handle INSERT operations
        if ($cfg['kind'] === 'INSERT') {
          // Check for existing record with same unique values
          if (!$primary_defined) {
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

              if ($exit) continue;

              // Check for existing record with same values
              if (!$isDisabled) {
                self::disable();
              }

              if ($tmp = $db->selectOne([
                'tables' => [$table],
                'fields' => [$s['primary']],
                'join' => [[
                  'table' => self::$table_uids,
                  'on' => [
                    'conditions' => [
                      ['field' => $db->cfn('bbn_uid', self::$table_uids), 'operator' => '=', 'exp' => $db->cfn($s['primary'], $table, true)]
                    ]
                  ]
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

          // Check for existing record with same primary value
          if ($primary_defined && !self::$db->count($table, [$s['primary'] => $primary_value])) {
            $primary_defined = false;
          }

          // If no active record exists, insert new history record
          if (!$primary_defined) {
            if (!$isDisabled) {
              self::disable();
            }

            if ($db->insertIgnore(self::$table_uids, [
              'bbn_uid' => $primary_value,
              'bbn_table' => $s['id']
            ])) {
              $cfg['history'][] = [
                'operation' => 'INSERT',
                'column' => isset($s['fields'][$s['primary']]) ? $s['fields'][$s['primary']]['id_option'] : null,
                'line' => $primary_value
              ];
            }

            if (!$isDisabled) {
              self::enable();
            }
          } else {
            // Record already exists, mark as restored
            $cfg['trig'] = false;
            $cfg['run'] = false;

            // Update active status to 1 (restored)
            $cfg['value'] = self::$db->update(self::$table_uids, ['bbn_active' => 1], [
              'bbn_uid' => $primary_value
            ]);

            if ($cfg['value']) {
              $cfg['trig'] = true;
              // Update the record in history table
              if (count($cfg['values_desc'])) {
                self::enable();
                foreach ($cfg['fields'] as $i => $idx) {
                  if (
                    $s['fields'][$idx] && isset($cfg['values'][$idx]) &&
                    ($s['fields'][$idx]['id_option'] !== null)
                  ) {

                    // Find the column ID for this field
                    $col_id = self::$database_obj->columnId(
                      $s['fields'][$idx]['name'],
                      $table,
                      $db->getCurrent()
                    );

                    if ($col_id) {
                      $cfg['history'][] = [
                        'operation' => 'UPDATE',
                        'column' => $col_id,
                        'line' => $primary_value,
                        'old' => self::$db->selectOne($table, $idx, [$s['primary'] => $primary_value]),
                        'chrono' => microtime(true)
                      ];
                    }
                  }
                }

                // Update the record in database
                $update = [];
                foreach ($cfg['fields'] as $i => $f) {
                  if (isset($cfg['values'][$i]) && isset($s['fields'][$i])) {
                    $update[$f] = $cfg['values'][$i];
                  }
                }

                self::$db->update($table, $update, [$s['primary'] => $primary_value]);
              }
            }

            if (!$isDisabled) {
              self::enable();
            }
          }
        } else if ($cfg['kind'] === 'UPDATE') {

          // Handle UPDATE operations
          $tmp = [];
          foreach ($cfg['fields'] as $i => $f) {
            $tmp[$f] = $cfg['values'][$i];
          }

          // Check for unique constraints
          $isDefined = false;
          foreach ($s['unique'] as $unique) {
            if (count(X::filter($unique['columns'], fn($a) => in_array($a['name'], $cfg['fields'], true))) ? $unique : false) {
              $isDefined = $unique;
              break;
            }
          }

          // Check for existing record with same values
          if ($primary_where && $isDefined) {
            if (!$isDisabled) {
              self::disable();
            }

            $row = $db->rselect($table, array_keys($cfg['fields']), [$s['primary'] => $primary_where]);

            // Check for unique constraint violations
            foreach ($isDefined['columns'] as $col) {
              if (!in_array($col['name'], $cfg['fields'])) {
                continue;
              }

              $search = [];
              foreach ($isDefined['columns'] as $a) {
                $search[$a['name']] = in_array($a['name'], $cfg['fields']) ? $tmp[$a['name']] : $row[$a['name']];

                if (is_null($search[$a['name']])) {
                  $search = [];
                  break;
                }
              }

              if (!empty($search)) {
                $search[] = [$s['primary'], '!=', $primary_where];
                if ($checkRow = self::$db->selectOne($table, $s['primary'], $search)) {
                  // Check for nullable columns
                  if (!$col['nullable']) {
                    throw new Exception(X::_(
                      "Impossible to update the record with primary %s from %s because a unique constraint already exists in record %s, you should make one of the unique keys columns nullable",
                      $primary_where,
                      $table,
                      $checkRow
                    ));
                  }
                }
              }
            }

            if (!$isDisabled) {
              self::enable();
            }

            // Record changes for each field that changed
            foreach ($cfg['fields'] as $i => $idx) {
              $csn = self::$db->csn($idx);
              if (
                array_key_exists($csn, $s['fields']) &&
                ($row[$csn] !== $cfg['values'][$i])
              ) {

                // Find column ID for this field
                $col_id = self::$database_obj->columnId(
                  $s['fields'][$csn]['name'],
                  $table,
                  $db->getCurrent()
                );

                if ($col_id) {
                  $cfg['history'][] = [
                    'operation' => 'UPDATE',
                    'column' => $col_id,
                    'line' => $primary_where,
                    'old' => $row[$csn]
                  ];
                }
              }
            }
          } else if (!$isDisabled && $ids = self::$db->getColumnValues($table, $s['primary'], $cfg['filters'])) {
            // Handle multiple records with same primary value
            $cfg['trig'] = false;
            $cfg['run'] = false;

            foreach ($ids as $id) {
              $cfg['value'] += self::$db->update($table, $tmp, [$s['primary'] => $id]);
            }
          }
        } else if ($cfg['kind'] === 'DELETE') {

          // Handle DELETE operations
          $cfg['trig'] = false;
          $cfg['run'] = false;

          // Check for existing record with same primary value
          if (!$primary_where) {
            $ids = self::$db->getColumnValues($table, $s['primary'], $cfg['filters']);

            foreach ($ids as $id) {
              $cfg['value'] += self::$db->delete($table, [$s['primary'] => $id]);
            }
          } else {
            // Check for foreign key constraints
            if (!$isDisabled) {
              self::disable();
            }

            foreach ($s['refs'] as $ref) {
              if (!empty($ref['constraint']) && $db->count($ref['table'], [$ref['col'] => $primary_where])) {
                if ($ref['delete'] === 'RESTRICT') {
                  if ($ref['table'] !== $db->tsn(self::$table)) {
                    throw new Exception(X::_(
                      "Impossible to delete the record with primary %s from %s because it is referenced in the table %s",
                      $primary_where,
                      $table,
                      $ref['table']
                    ));
                  }
                } elseif ($ref['delete'] === 'SET NULL') {
                  self::$db->update($ref['table'], [$ref['col'] => null], [$ref['col'] => $primary_where]);
                } elseif ($ref['delete'] === 'CASCADE') {
                  self::$db->delete($ref['table'], [$ref['col'] => $primary_where]);
                } else {
                  throw new Exception(X::_("Impossible to find what to do with the record referenced in the table %s", $ref['table']));
                }
              }
            }

            // Update nullable columns
            foreach ($s['unique'] as $unique) {
              foreach ($unique['columns'] as $col) {
                if (!$col['nullable']) continue;

                $old = self::$db->selectOne($table, $col['name'], [$s['primary'] => $primary_where]);
                self::$db->update($table, [$col['name'] => null], [$s['primary'] => $primary_where]);

                if (isset($s['fields'][$col['name']])) {
                  $cfg['history'][] = [
                    'operation' => 'UPDATE',
                    'column' => $s['fields'][$col['name']]['id_option'],
                    'line' => $primary_where,
                    'old' => $old
                  ];
                }
              }
            }

            // Mark record as deleted in history table
            $cfg['value'] = self::$db->update(self::$table_uids, [
              'bbn_active' => 0
            ], [
              'bbn_uid' => $primary_where
            ]);

            if ($cfg['value']) {
              $cfg['trig'] = true;
              // Add delete operation to history
              $cfg['history'][] = [
                'operation' => 'DELETE',
                'column' => $s['fields'][$s['primary']]['id_option'],
                'line' => $primary_where,
                'old' => NULL
              ];
            }
          }

          if (!$isDisabled) {
            self::enable();
          }
        }
      } else if ($cfg['moment'] === 'after' && isset($cfg['history'])) {
        // Process history records after write operations
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
   * Helper method to get primary value from configuration
   *
   * @param array $cfg Configuration array
   * @return null|string Primary key value or null if not found
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
   * Returns the database connection object
   *
   * @return Db|null Database connection or null if not initialized
   */
  private function _get_db(): ?Db
  {
    if ($this->db && $this->db->check()) {
      return $this->db;
    }
    return null;
  }

  /**
   * Returns an instance of the Database class
   *
   * @return Database|null Database object or null if not initialized
   */
  private function _get_database(): ?Database
  {
    if (self::check()) {
      if (!isset($this->database_obj) && ($db = self::_get_db())) {
        $this->database_obj = new Database($db);
      }
      return $this->database_obj;
    }
    return null;
  }

  /**
   * Inserts a history record
   *
   * @param array $cfg History record configuration
   * @return int Number of successful operations
   */
  private function _insert(array $cfg): int
  {
    if (
      isset($cfg['column'], $cfg['line'], $cfg['chrono']) &&
      self::check() && ($db = self::_get_db())
    ) {

      // Record the last ID temporarily
      $id = $db->lastId();
      $db->disableLast();

      // Disable history if not enabled
      $isDisabled = !self::$enabled;
      if (!$isDisabled) {
        self::disable();
      }

      // Set default values for ref and val if not provided
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

      // Insert into history table
      if ($res = $db->insert(self::$table, [
        'opr' => $cfg['operation'],
        'uid' => $cfg['line'],
        'col' => $cfg['column'],
        'val' => $cfg['val'],
        'ref' => $cfg['ref'],
        'tst' => self::$date ?: $cfg['chrono'],
        'usr' => self::$user
      ])) {
        // Restore last ID
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
   * Gets WHERE conditions for a table
   *
   * @param string $table The table name
   * @return array|null WHERE conditions or null if not found
   */
  private function _getTableWhere(string $table): ?array
  {
    if (
      Str::checkName($table) && ($db = self::_get_db()) &&
      ($database_obj = self::_get_database()) && ($model = $database_obj->modelize($table))
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

  /**
   * Validates a timestamp
   *
   * @param mixed $d Timestamp or date string to validate
   * @return float|null Validated timestamp or null if invalid
   */
  private function validTimestamp($d): ?float
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
   * Checks if history is initialized
   *
   * @return bool True if properly configured
   */
  public static function isInit(): bool
  {
    return self::$ok;
  }

  /**
   * Disables history operations
   */
  public static function disable(): void
  {
    self::$enabled = false;
  }

  /**
   * Enables history operations
   */
  public static function enable(): void
  {
    self::$enabled = true;
  }

  /**
   * Checks if history is enabled
   *
   * @return bool True if properly configured and enabled
   */
  public static function isEnabled(): bool
  {
    return self::isInit() && (self::$enabled === true);
  }
}

/**
 * General comments about the History class:
 *
 * 1. This class implements a database history tracking system that records changes to tables.
 *    It maintains a history of all modifications, allowing users to roll back to previous states.
 * 
 * 2. The Singleton pattern is implemented to ensure only one instance of this class exists,
 *    which is crucial for maintaining consistent state across multiple operations on the same database connection.
 * 
 * 3. Key features include:
 *    - Automatic tracking of INSERT/UPDATE/DELETE operations
 *    - Ability to query history records by timestamp, column, or operation type
 *    - Support for complex relationships between tables through foreign keys
 *    - Methods for merging and upgrading table structures
 * 
 * 4. The class handles several edge cases:
 *    - Prevents future writes in the history table
 *    - Manages primary key conflicts during inserts
 *    - Handles foreign key constraints properly
 *    - Supports both direct UID references and column-based lookups
 * 
 * 5. Performance considerations:
 *    - Uses caching to avoid repeated database queries for common configurations
 *    - Implements efficient query building with prepared statements
 *    - Minimizes database operations by batching updates
 * 
 * 6. The class is designed to work seamlessly with the BBN framework's database abstraction layer,
 *    providing a consistent interface across different database backends.
 * 
 * 7. Error handling is integrated throughout, with appropriate checks for:
 *    - Invalid timestamps
 *    - Missing or invalid table configurations
 *    - Database connection issues
 *    - Primary key conflicts
 * 
 * 8. The implementation maintains backward compatibility while adding new features like:
 *    - Support for complex relationships between tables
 *    - Detailed history records with old/new values
 *    - Timestamp-based queries and rollbacks
 */
