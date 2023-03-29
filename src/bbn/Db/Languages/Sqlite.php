<?php
/**
 * @package db
 */
namespace bbn\Db\Languages;

use bbn;
use bbn\Str;
use bbn\X;

/**
 * Database Class
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Database
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.4
 */
class Sqlite extends Sql
{

  private $sqlite_keys_enabled = false;

  /**
   * @var array
   */
  protected array $cfg;

  /**
   * The host of this connection
   * @var string $host
   */
  protected $host;

  /** @var string The connection code as it would be stored in option */
  protected $connection_code;

  /** @var array Allowed operators */
  public static $operators = ['!=','=','<>','<','<=','>','>=','like','clike','slike','not','is','is not', 'in','between', 'not like'];

    /** @var array Numeric column types */
  public static $numeric_types = ['integer', 'real'];

  /** @var array Time and date column types don't exist in SQLite */
  public static $date_types = [];

  public static $types = [
    'integer',
    'real',
    'text',
    'blob'
  ];

  public static $interoperability = [
    'tinyint' => 'integer',
    'smallint' => 'integer',
    'mediumint' => 'integer',
    'int' => 'integer',
    'bigint' => 'integer',
    'decimal' => 'real',
    'float' => 'real',
    'double' => 'real',
    'bit' => '',
    'char' => '',
    'varchar' => 'text',
    'binary' => 'blob',
    'varbinary' => 'blob',
    'tinyblob' => 'blob',
    'blob' => 'blob',
    'mediumblob' => 'blob',
    'longblob' => 'blob',
    'tinytext' => 'text',
    'text' => 'text',
    'mediumtext' => 'text',
    'longtext' => 'text',
    'enum' => 'text',
    'set' => 'text',
    'date' => 'text',
    'time' => 'text',
    'datetime' => 'text',
    'timestamp' => 'integer',
    'year' => 'integer',
    'json' => 'text'
  ];

  public static $aggr_functions = [
    'AVG',
    'COUNT',
    'GROUP_CONCAT',
    'MAX',
    'MIN',
    'SUM',
  ];

  /** @var string The quote character */
  public $qte = '"';

  /**
   * Constructor
   * @param array $cfg
   * @throws \Exception
   */
  public function __construct(array $cfg = [])
  {
    if (!\extension_loaded('pdo_sqlite')) {
      throw new \Exception('The SQLite driver for PDO is not installed...');
    }

    $cfg = $this->getConnection($cfg);

    try {
      $this->cacheInit();
      $this->current  = $cfg['db'];
      $this->host     = $cfg['host'];
      $this->connection_code = $cfg['host'];

      $this->pdo = new \PDO(...$cfg['args']);
      $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
      $this->cfg = $cfg;
      $this->setHash($cfg['args']);

    } catch (\PDOException $e) {
      $err = X::_("Impossible to create the connection").
        " {$cfg['engine']}/Connection ". $this->getEngine()." to {$this->host} "
        .X::_("with the following error").$e->getMessage();
      throw new \Exception($err);
    }
  }

  /**
   * @param array $cfg The user's options
   * @return array|null The final configuration
   */
  public function getConnection(array $cfg = []): ?array
  {
    $cfg['engine'] = 'sqlite';

    if (!isset($cfg['db']) && \defined('BBN_DATABASE')) {
      $cfg['db'] = BBN_DATABASE;
    }

    if (empty($cfg['db']) || !\is_string($cfg['db'])) {
      throw new \Exception('Database name is not specified');
    }

    if (is_file($cfg['db'])) {
      $info        = X::pathinfo($cfg['db']);
      $cfg['host'] = $info['dirname'].DIRECTORY_SEPARATOR;
      $cfg['db']   = $info['basename'];
    }
    elseif (\defined('BBN_DATA_PATH')
      && is_dir(BBN_DATA_PATH.'db')
      && (strpos($cfg['db'], '/') === false)
    ) {
      $cfg['host'] = BBN_DATA_PATH.'db'.DIRECTORY_SEPARATOR;
      if (!is_file(BBN_DATA_PATH.'db'.DIRECTORY_SEPARATOR.$cfg['db'])
        && (strpos($cfg['db'], '.') === false)
      ) {
        $cfg['db'] .= '.sqlite';
      }
    }
    else{
      $info = X::pathinfo($cfg['db']);
      if (is_writable($info['dirname'])) {
        $cfg['host'] = $info['dirname'].DIRECTORY_SEPARATOR;
        $cfg['db']   = isset($info['extension']) ? $info['basename'] : $info['basename'].'.sqlite';
      }
    }

    if (!isset($cfg['host'])) {
      throw new \Exception('Db file could not be located');
    }

    $cfg['args'] = ['sqlite:'.$cfg['host'].$cfg['db']];
    $cfg['db']   = 'main';

    return $cfg;
  }


  /*****************************************************************************************************************
   *                                                                                                                *
   *                                                                                                                *
   *                                               ENGINES INTERFACE                                                *
   *                                                                                                                *
   *                                                                                                                *
   *****************************************************************************************************************/

  /**
   * Actions to do once the PDO object has been created
   *
   * @return void
   */
  public function postCreation()
  {
    // Obliged to do that  if we want to use foreign keys with SQLite
    $this->enableKeys();
  }


  /**
   * Changes the current database to the given one.
   *
   * @param string $db The database name or file
   * @return string|false
   */
  public function change(string $db): bool
  {
    if (strpos($db, '.') === false) {
      $db .= '.sqlite';
    }

    $info = X::pathinfo($db);
    if (($info['filename'] !== $this->getCurrent()) && file_exists($this->host.$db) && strpos($db, $this->qte) === false) {
      $this->rawQuery("ATTACH '".$this->host.$db."' AS ".$info['filename']);
      $this->current = $info['filename'];

      return true;
    }

      return false;
  }


  /**
   * Returns a table's full name i.e. database.table
   *
   * @param string $table   The table's name (escaped or not)
   * @param bool   $escaped If set to true the returned string will be escaped
   * @return null|string
   */
  public function tableFullName(string $table, bool $escaped = false): ?string
  {
    $bits = explode('.', str_replace($this->qte, '', $table));
    if (\count($bits) === 2) {
      $db    = trim($bits[0]);
      $table = trim($bits[1]);
    }
    else {
      $db    = $this->getCurrent();
      $table = trim($bits[0]);
    }

    if (bbn\Str::checkName($table) && bbn\Str::checkName($db)) {
      if ($db === 'main') {
        return $escaped ? $this->qte.$table.$this->qte : $table;
      }

      return $escaped
        ? $this->qte.$db.$this->qte.'.'.$this->qte.$table.$this->qte
        : $db.'.'.$table;
    }

      return null;
  }


  /**
   * Returns a table's simple name i.e. table
   *
   * @param string $table   The table's name (escaped or not)
   * @param bool   $escaped If set to true the returned string will be escaped
   * @return null|string
   */
  public function tableSimpleName(string $table, bool $escaped = false): ?string
  {
    if ($table = trim($table)) {
      $bits  = explode('.', str_replace($this->qte, '', $table));
      $table = end($bits);

      if (bbn\Str::checkName($table)) {
        return $escaped ? $this->qte.$table.$this->qte : $table;
      }
    }

    return false;
  }

  /**
   * Disable foreign keys check
   *
   * @return self
   */
  public function disableKeys(): self
  {
    $this->rawQuery('PRAGMA foreign_keys = OFF;');

    return $this;
  }


  /**
   * Enable foreign keys check
   *
   * @return self
   */
  public function enableKeys(): self
  {
    $this->rawQuery('PRAGMA foreign_keys = ON;');

    return $this;
  }


  /**
   * Return databases' names as an array.
   *
   * @return null|array
   * @throws \Exception
   */
  public function getDatabases(): ?array
  {
    if (!$this->check()) {
      return null;
    }

    $x  = [];
    $fs = bbn\File\Dir::scan($this->host);
    foreach ($fs as $f){
      if (is_file($f)) {
        $x[] = X::pathinfo($f, PATHINFO_FILENAME);
      }
    }

    sort($x);
    return $x;
  }


  /**
   * Return tables' names of a database as an array.
   *
   * @param string $database Database name
   * @return null|array
   * @throws \Exception
   */
  public function getTables(string $database = ''): ?array
  {
    if (!$this->check()) {
      return null;
    }

    if (empty($database) || !bbn\Str::checkName($database)) {
      $database = $this->getCurrent() === 'main' ? '' : '"'.$this->getCurrent().'".';
    }
    elseif ($database === 'main') {
      $database = '';
    }

    $t2 = [];
    if (($r = $this->rawQuery(
      '
      SELECT "tbl_name"
      FROM '.$database.'"sqlite_master"
        WHERE type = \'table\''
    ) )
        && $t1 = $this->fetchAllResults($r, \PDO::FETCH_NUM)
    ) {
      foreach ($t1 as $t){
        if (strpos($t[0], 'sqlite') !== 0) {
          array_push($t2, $t[0]);
        }
      }
    }

    return $t2;
  }


  /**
   * Returns the columns' configuration of the given table.
   *
   * @param null|string $table The table's name
   * @return null|array
   * @throws \Exception
   */
  public function getColumns(string $table): ?array
  {
    if (!$this->check()) {
      return null;
    }

    $r = [];
    if ($table = $this->tableFullName($table)) {
      $p = 1;
      if ($rows = $this->getRows("PRAGMA table_info($table)")) {
        foreach ($rows as $row){
          $f     = $row['name'];
          $r[$f] = [
            'position' => $p++,
            'null' => $row['notnull'] == 0 ? 1 : 0,
            'key' => $row['pk'] == 1 ? 'PRI' : null,
            'default' => is_string($row['dflt_value'])
              ? rtrim(
                ltrim($row['dflt_value'], "'"),
                "'"
              )
              : $row['dflt_value'],
            // INTEGER PRIMARY KEY is a ROWID
            // https://www.sqlite.org/autoinc.html
            'extra' => $row['type'] === 'INTEGER' && $row['pk'] == 1 ? 'auto_increment' :  null,
            'maxlength' => null,
            'signed' => 1
          ];

          if ($row['dflt_value'] !== '') {
            $r[$f]['defaultExpression'] = false;
          }

          if (in_array($row['dflt_value'], ['CURRENT_TIME', 'CURRENT_DATE', 'CURRENT_TIMESTAMP'], true)) {
            $r[$f]['defaultExpression'] = true;
          }


          $type  = strtolower($row['type']);
          if (strpos($type, 'blob') !== false) {
            $r[$f]['type'] = 'BLOB';
          }
          elseif (( strpos($type, 'int') !== false ) || ( strpos($type, 'bool') !== false ) || ( strpos($type, 'timestamp') !== false )) {
            $r[$f]['type'] = 'INTEGER';

            if (strpos($type, 'unsigned') !== false) {
              $r[$f]['signed'] = 0;
            }
          }
          elseif (( strpos($type, 'floa') !== false ) || ( strpos($type, 'doub') !== false ) || ( strpos($type, 'real') !== false )) {
            $r[$f]['type'] = 'REAL';

            if (strpos($type, 'unsigned') !== false) {
              $r[$f]['signed'] = 0;
            }
          }
          elseif (( strpos($type, 'char') !== false ) || ( strpos($type, 'text') !== false )) {
            $r[$f]['type'] = 'TEXT';
          }

          if (preg_match_all('/\((.*?)\)/', $row['type'], $matches)) {
            $r[$f]['maxlength'] = (int)$matches[1][0];
          }

          if (!isset($r[$f]['type'])) {
            $r[$f]['type'] = 'TEXT';
          }
        }
      }
    }

      return $r;
  }


  /**
   * Returns the keys of the given table.
   *
   * @param string $table The table's name
   * @return null|array
   * @throws \Exception
   */
  public function getKeys(string $table): ?array
  {
    if (!$this->check()) {
      return null;
    }

    $r = [];
    if ($full = $this->tableFullName($table)) {
      $r        = [];
      $keys     = [];
      $cols     = [];
      $database = $this->getCurrent() === 'main' ? '' : '"'.$this->getCurrent().'".';
      if ($indexes = $this->getRows('PRAGMA index_list('.$table.')')) {
        foreach ($indexes as $d){
          if ($fields = $this->getRows('PRAGMA index_info('.$database.'"'.$d['name'].'")')) {
            foreach ($fields as $d2){
              $key_name = strtolower($d['origin']) === 'pk' ? 'PRIMARY' : $d['name'];
              if (!isset($keys[$key_name])) {
                $keys[$key_name] = [
                  'columns' => [$d2['name']],
                  'ref_db' => null,
                  'ref_table' => null,
                  'ref_column' => null,
                  'constraint' => null,
                  'update' => null,
                  'delete' => null,
                  'unique' => $d['unique'] == 1 ? 1 : 0
                ];
              }
              else{
                $keys[$key_name]['columns'][] = $d2['name'];
              }

              if (!isset($cols[$d2['name']])) {
                $cols[$d2['name']] = [$key_name];
              }
              else{
                $cols[$d2['name']][] = $key_name;
              }
            }
          }
        }
      }

      // when a column is INTEGER PRIMARY KEY it doesn't show up in the query: PRAGMA index_list
      // INTEGER PRIMARY KEY considered as auto_increment: https://www.sqlite.org/autoinc.html
      if ($columns = $this->getColumns($table)) {
        $columns = array_filter($columns, function ($item) {
          return $item['extra'] === 'auto_increment' && $item['key'] === 'PRI';
        });

        foreach ($columns as $column_name => $column) {
          if (!isset($keys['PRIMARY'])) {
            $keys['PRIMARY'] = [
              'columns' => [$column_name],
              'ref_db' => null,
              'ref_table' => null,
              'ref_column' => null,
              'constraint' => null,
              'update' => null,
              'delete' => null,
              'unique' => 1
            ];
          }
          else {
            $keys['PRIMARY']['columns'][] = $column_name;
          }

          if (!isset($cols[$column_name])) {
            $cols[$column_name] = ['PRIMARY'];
          }
          else {
            $cols[$column_name][] = 'PRIMARY';
          }
        }
      }

      if ($constraints = $this->getRows("PRAGMA foreign_key_list($database\"$table\")")) {
        foreach ($constraints as $constraint) {
          $constraint_name = "{$constraint['table']}_{$constraint['from']}";
          if (empty($cols[$constraint['from']])) {
            $keys[$constraint_name] = [
              'columns' => [$constraint['from']],
              'ref_db' => $this->getCurrent(),
              'ref_table' => $constraint['table'] ?? null,
              'ref_column' => $constraint['to'] ??  null,
              'constraint' => $constraint_name,
              'update' => $constraint['on_update'] ?? null,
              'delete' => $constraint['on_delete'] ?? null,
              'unique' => 0
            ];

            $cols[$constraint['from']] = [$constraint_name];

          } else {
            foreach ($cols[$constraint['from']] as $col) {
              if (isset($keys[$col])) {
                $keys[$col]['ref_db'] = $this->getCurrent();
                $keys[$col]['ref_table'] = $constraint['table'] ?? null;
                $keys[$col]['ref_column'] = $constraint['to'] ?? null;
                $keys[$col]['constraint'] = $constraint_name;
                $keys[$col]['update'] = $constraint['on_update'] ?? null;
                $keys[$col]['delete'] =  $constraint['on_delete'] ?? null;
              }
            }
          }
        }
      }

      $r['keys'] = $keys;
      $r['cols'] = $cols;
    }

      return $r;
  }

  /**
   * Get a string starting with ORDER BY with corresponding parameters to $order.
   *
   * @param array $cfg
   * @return string
   */
  public function getOrder(array $cfg): string
  {
    $res = '';
    if (!empty($cfg['order'])) {
      foreach ($cfg['order'] as $col => $dir) {
        if (\is_array($dir) && isset($dir['field'])) {
          $col = $dir['field'];
          $dir = $dir['dir'] ?? 'ASC';
        }

        if (isset($cfg['available_fields'][$col])) {
          // If it's an alias we use the simple name
          if (isset($cfg['fields'][$col])) {
            $f = $this->colSimpleName($col, true);
          } elseif ($cfg['available_fields'][$col] === false) {
            $f = $this->escape($col);
          } else {
            $f = $this->colFullName($col, $cfg['available_fields'][$col], true);
          }

          $res .= $f.' COLLATE NOCASE '.
            (strtolower($dir) === 'desc' ? 'DESC' : 'ASC' ).','.PHP_EOL;
        }
      }

      if (!empty($res)) {
        return 'ORDER BY '.substr($res,0, Strrpos($res,',')).PHP_EOL;
      }
    }

    return $res;
  }

  /**
   * @param null|string $table The table for which to create the statement
   * @return string
   */
  public function getRawCreate(string $table): string
  {
    if (($table = $this->tableFullName($table, true))
        && ($r = $this->rawQuery("SELECT sql FROM sqlite_master WHERE name = $table"))
    ) {
      return $r->fetch(\PDO::FETCH_ASSOC)['sql'] ?? '';
    }

    return '';
  }


  /**
   * @param string $table
   * @param array|null $model
   * @return string
   * @throws \Exception
   */
  public function getCreateTable(string $table, array $model = null): string
  {
    if (!$model) {
      $model = $this->modelize($table);
    }

    $st   = 'CREATE TABLE ' . $this->escape($table) . ' (' . PHP_EOL;
    $done = false;
    foreach ($model['fields'] as $name => $col) {
      if (!$done) {
        $done = true;
      }
      else {
        $st .= ',' . PHP_EOL;
      }

      $st .= $this->getColumnDefinitionStatement($name, $col);
    }

    if (isset($model['keys']['PRIMARY'])) {
      $st .= ','.PHP_EOL.'  PRIMARY KEY ('.X::join(
        array_map(
          function ($a) {
            return $this->escape($a);
          },
          $model['keys']['PRIMARY']['columns']
        ),
        ', '
      ).')';
    }

    $st .= PHP_EOL . ')';
    return $st;
  }


  /**
   * @param string $table
   * @param array|null $model
   * @return string
   * @throws \Exception
   */
  public function getCreateKeys(string $table, ?array $model = null): string
  {
    $st = '';
    if (!$model) {
      $model = $this->modelize($table);
    }

    if ($model && !empty($model['keys'])) {
      foreach ($model['keys'] as $name => $key) {
        if ($name === 'PRIMARY') {
          continue;
        }

        $st .= 'CREATE ';
        if (!empty($key['unique'])) {
          $st .= 'UNIQUE ';
        }

        $st .= 'INDEX \''.Str::escapeSquotes($name).'\' ON ' . $this->escape($table);

        $st .= ' ('.X::join(
          array_map(
            function ($a) {
              return $this->escape($a);
            },
            $key['columns']
          ),
          ', '
        ).')';
        $st .= ';' . PHP_EOL;
      }
    }

    return $st;
  }


  /**
   * @param null|string $table The table for which to create the statement
   * @return string
   */
  public function getCreate(string $table, array $model = null): string
  {
    $st = '';
    if (!$model) {
      $model = $this->modelize($table);
    }

    if ($st = $this->getCreateTable($table, $model)) {
      $st .= ';'.PHP_EOL . $this->getCreateKeys($table, $model);
    }

    return $st;
  }


  /**
   * @param string $table
   * @param array $cfg
   * @return int
   */
  public function alter(string $table, array $cfg): int
  {
    if ($st = $this->getAlterTable($table, $cfg)) {
      // Sqlite does not support multiple alter statements in one query
      // So we will use begin a transaction then execute all queries one by one
      $this->pdo->beginTransaction();

      foreach (explode(';' . PHP_EOL, $st) as $query) {
        $this->rawQuery($query);
      }

      return (int)$this->pdo->commit();
    }

    return 0;
  }


  /**
   * Return a string for alter table sql statement.
   *
   * ```php
   * $cfg = [
   *    'fields' => [
   *      'id' => [
   *        'type' => 'binary',
   *        'maxlength' => 32
   *      ],
   *      'role' => [
   *        'type' => 'enum',
   *        'default' => 'user'
   *      ],
   *      'permission' => [
   *        'type' => 'set,
   *        'default' => 'read'
   *      ],
   *      'balance' => [
   *        'type' => 'real',
   *        'maxlength' => 10,
   *        'signed' => true,
   *        'default' => 0
   *      ],
   *      'created_at' => [
   *        'type' => 'datetime',
   *        'default' => 'CURRENT_TIMESTAMP'
   *      ],
   *      'role_id' => [
   *         'alter_type' => 'drop'
   *      ]
   *    ]
   * ];
   * X::dump($db->getAlterTable('users', $cfg));
   *
   * // (string) ALTER TABLE "users" ADD   "id" blob(32) NOT NULL;
   * // ALTER TABLE "users" ADD   "role" text NOT NULL DEFAULT "user";
   * // ALTER TABLE "users" ADD   "permission" text NOT NULL DEFAULT 'read';
   * // ALTER TABLE "users" ADD   "balance" real(10) NOT NULL DEFAULT 0;
   * // ALTER TABLE "users" ADD   "created_at" real NOT NULL DEFAULT CURRENT_TIMESTAMP;
   * // ALTER TABLE "users" DROP COLUMN "role_id";
   *
   * ```
   *
   * @param string $table
   * @param array $cfg
   * @return string
   * @throws \Exception
   */
  public function getAlterTable(string $table, array $cfg): string
  {
    if (empty($cfg['fields'])) {
      throw new \Exception(X::_('Fields are not specified'));
    }

    if ($this->check() && Str::checkName($table)) {
      $st = '';

      foreach ($cfg['fields'] as $name => $col) {
        $st .= 'ALTER TABLE ' . $this->escape($table) . ' ';

        $st .= $this->getAlterColumn($table, array_merge($col, [
          'col_name' => $name,
          'no_table_exp' => true
        ]));

        $st .= ";" . PHP_EOL;
      }
    }

    return $st ?? '';
  }


  /**
   * @param string $table
   * @param array $cfg
   * @return string
   * @throws \Exception
   */
  public function getAlterColumn(string $table, array $cfg): string
  {
    $alter_types = ['add', 'modify', 'drop'];

    if (!empty($cfg['alter_type']) && in_array(strtolower($cfg['alter_type']), $alter_types)) {
      $alter_type = strtoupper($cfg['alter_type']);
    }
    else {
      $alter_type = 'ADD';
    }

    $st = '';

    if (empty($cfg['no_table_exp'])) {
      $st = 'ALTER TABLE '. $this->escape($table) . PHP_EOL;
    }

    if ($alter_type === 'MODIFY' && !empty($cfg['new_name'])) {
      // Sqlite does not support modifying column types, only renaming
      $st .= "RENAME COLUMN ";
      $st .= $this->escape($cfg['col_name']) . ' TO ' . $this->escape($cfg['new_name']) . ' ';
    }
    elseif ($alter_type === 'DROP') {
      $st .= "DROP COLUMN " . $this->escape($cfg['col_name']);
    }
    else {
      $st .= $alter_type . ' ' . $this->getColumnDefinitionStatement($cfg['col_name'], $cfg);
    }

    return $st;
  }


  /**
   * @param string $table
   * @param array $cfg
   * @return string
   */
  public function getAlterKey(string $table, array $cfg): string
  {
    // Sqlite does not support altering keys
    return '';
  }


  /**
   * Creates an index
   *
   * @param null|string  $table
   * @param string|array $column
   * @param bool         $unique
   * @param null         $length
   * @return bool
   */
  public function createIndex(string $table, $column, bool $unique = false, $length = null, $order = null): bool
  {
    if (!\is_array($column)) {
      $column = [$column];
    }

    $name = bbn\Str::encodeFilename($table);
    foreach ($column as $i => $c){
      if (!bbn\Str::checkName($c)) {
        $this->error("Illegal column $c");
      }

      $name      .= '_'.$c;
      $column[$i] = '`'.$column[$i].'`';
      if (!empty($length[$i]) && \is_int($length[$i]) && $length[$i] > 0) {
        $column[$i] .= '('.$length[$i].')';
      }
    }

    $name = bbn\Str::cut($name, 50);
    if ($table = $this->tableFullName($table, 1)) {
      $query = 'CREATE '.( $unique ? 'UNIQUE ' : '' )."INDEX `$name` ON $table ( ".implode(', ', $column);
      if (($order === "ASC") || ($order === "DESC")) {
        $query .= ' '. $order .' );';
      }
      else {
        $query .= ' );';
      }

      X::log(['index', $query],'vito');
      return (bool)$this->rawQuery($query);
    }

    return false;
  }


  /**
   * Deletes an index
   *
   * @param string $table
   * @param string $key
   * @return bool
   * @throws \Exception
   */
  public function deleteIndex(string $table, string $key): bool
  {
    if (($this->tableFullName($table, 1)) && bbn\Str::checkName($key)) {
      //changed the row above because if the table has no rows query() returns 0
      //return (bool)$this->db->query("ALTER TABLE $table DROP INDEX `$key`");
      return $this->query('DROP INDEX IF EXISTS '.$key) !== false;
    }

    return false;
  }


  /**
   * Creates a database
   *
   * @param string $database
   * @return bool
   */
  public function createDatabase(string $database): bool
  {
    if (bbn\Str::checkFilename($database)) {
      if (empty(strpos($database, '.sqlite'))) {
        $database = $database.'.sqlite';
      }

      if (empty(file_exists($this->host.$database))) {
        fopen($this->host.$database, 'w');
        return file_exists($this->host.$database);
      }
    }

    return false;
  }


  /**
   * Drops the given database
   *
   * @param string $database
   * @return bool
   */
  public function dropDatabase(string $database): bool
  {
    if (bbn\Str::checkFilename($database)) {
      if (empty(strpos($database, '.sqlite'))) {
        $database = $database.'.sqlite';
      }

      if (file_exists($this->host.$database)) {
        unlink($this->host.$database);
        return !file_exists($this->host.$database);
      }
    }

    return false;
  }


  /**
   * Creates a database user
   *
   * @param string|null $user
   * @param string|null $pass
   * @param string|null $db
   * @return bool
   */
  public function createUser(string $user = null, string $pass = null, string $db = null): bool
  {
    return true;
  }


  /**
   * Deletes a database user
   *
   * @param string|null $user
   * @return bool
   */
  public function deleteUser(string $user = null): bool
  {
    return true;
  }


  /**
   * @param string $user
   * @param string $host
   * @return array|null
   */
  public function getUsers(string $user = '', string $host = ''): ?array
  {
    return [];
  }

  /**
   * Renames the given table to the new given name.
   *
   * @param string $table   The current table's name
   * @param string $newName The new name.
   * @return bool  True if it succeeded
   */
  public function renameTable(string $table, string $newName): bool
  {
    if ($this->check() && Str::checkName($table) && Str::checkName($newName)) {
      $t1 = strpos($table, '.') ? $this->tableFullName($table, true) : $this->tableSimpleName($table, true);
      $t2 = strpos($newName, '.') ? $this->tableFullName($newName, true) : $this->tableSimpleName($newName, true);

      $res = $this->rawQuery(sprintf("ALTER TABLE %s RENAME TO %s", $t1, $t2));
      return (bool)$res;
    }

    return false;
  }

  public function getTableComment(string $table): string
  {
    return '';
  }


  public function dbSize(string $database = '', string $type = ''): int
  {
    if (empty(strpos($database, '.sqlite'))) {
      $database = $database.'.sqlite';
    }

   return @filesize($this->host . $database) ?: 0;
  }


  public function tableSize(string $table, string $type = ''): int
  {
    return 0;
  }


  /**
   * Gets the status of a table.
   *
   * @param string $table
   * @param string $database
   * @return array|false|null
   * @throws \Exception
   */
  public function status(string $table = '', string $database = '')
  {
    $cur = null;
    if ($database && ($this->getCurrent() !== $database)) {
      $cur = $this->getCurrent();
      $this->change($database);
    }

    $r = $this->getRow('SELECT * FROM dbstat WHERE name LIKE ?', $table);
    if (null !== $cur) {
      $this->change($cur);
    }

    return $r;
  }


  /**
   * @return string
   */
  public function getUid(): string
  {
    return bbn\X::makeUid();
  }


  /**
   * @param $table_name
   * @param array $columns
   * @param array|null $keys
   * @param bool $with_constraints
   * @param string $charset
   * @return string
   */
  public function createTable($table_name, array $columns, array $keys = null, bool $with_constraints = false, string $charset = 'UTF-8')
  {
    $lines = [];
    $sql   = '';
    foreach ($columns as $n => $c){
      $name = $c['name'] ?? $n;
      if (isset($c['type']) && bbn\Str::checkName($name)) {
        $st = $this->colSimpleName($name, true).' '.$c['type'];
        if (!empty($c['maxlength'])) {
          $st .= '('.$c['maxlength'].')';
        }
        elseif (!empty($c['values']) && \is_array($c['values'])) {
          $st .= '(';
          foreach ($c['values'] as $i => $v){
            $st .= "'".bbn\Str::escapeSquotes($v)."'";
            if ($i < count($c['values']) - 1) {
              $st .= ',';
            }
          }

          $st .= ')';
        }

        if ((strpos($c['type'], 'int') !== false) && empty($c['signed'])) {
          $st .= ' UNSIGNED';
        }

        if (empty($c['null'])) {
          $st .= ' NOT NULL';
        }

        if (isset($c['default'])) {
          $st .= ' DEFAULT '.($c['default'] === 'NULL' ? 'NULL' : "'".bbn\Str::escapeSquotes($c['default'])."'");
        }

        $lines[] = $st;
      }
    }

    if (count($lines)) {
      $sql = 'CREATE TABLE '.$this->tableSimpleName($table_name, false).' ('.PHP_EOL.implode(','.PHP_EOL, $lines).
        PHP_EOL.'); PRAGMA encoding='.$this->qte.$charset.$this->qte.';';
    }

    return $sql;
  }


  public function createTableSqlite($table_name, array $columns, array $keys = null, bool $with_constraints = false, string $charset = 'UTF-8')
  {
    $str = $this->createTable($table_name, $columns, $keys, $with_constraints, $charset);
    if ($str !== '') {
      return (bool)$this->rawQuery($str);
    }

    return false;
  }


  /**
   * @param string $table
   * @param array|null $model
   * @return string
   * @throws \Exception
   * TODO-testing: ALTER TABLE ADD CONSTRAINT is not supported:
   * https://www.sqlite.org/omitted.html
   */
  public function getCreateConstraints(string $table, array $model = null): string
  {
    $st = '';
    if (!empty($model)) {
      if ($last = count($model)) {
        $st .= 'ALTER TABLE '.$this->escape($table).PHP_EOL;
        $i   = 0;

        if (!is_array($model[0])) {
          $constraints[] = $model;
        }
        else{
          $constraints = $model;
        }

        foreach ($constraints as $name => $key) {
          X::log($key, 'vito');
          $i++;
          $st .= '  ADD '.
            'CONSTRAINT '.$this->escape($key['constraint']).
            (!empty($key['foreign_key']) ? ' FOREIGN KEY ('.$this->escape($key['columns'][0]).') ' : '').
            (!empty($key['unique']) ? ' UNIQUE ('.$this->escape($key['ref_table'].'_'.$key['columns'][0]).') ' : '').
            (!empty($key['primary_key']) ? ' PRIMARY KEY ('.$this->escape($key['ref_table'].'_'.$key['columns'][0]).') ' : '').
            'REFERENCES '.$this->escape($key['ref_table']).'('.$this->escape($key['columns'][0]).') '.
            ($key['delete'] ? ' ON DELETE '.$key['delete'] : '').
            ($key['update'] ? ' ON UPDATE '.$key['update'] : '').
            ($i === $last ? ';' : ','.PHP_EOL);
        }
      }
    }

    return $st;
  }


  /**
   * @param string $table
   * @param array|null $model
   * @return bool
   * @throws \Exception
   */
  public function createConstraintsSqlite(string $table, array $model = null): bool
  {
    $str = $this->getCreateConstraints($table,  $model);
    if ($str !== '') {
      return (bool)$this->rawQuery($str);
    }

    return false;
  }

  /**
   * Return primary keys of a table as a numeric array.
   *
   * @param string $table
   * @return array
   * @throws \Exception
   */
  public function getPrimary(string $table): array
  {
    if (($keys = $this->getKeys($table)) && isset($keys['keys']['PRIMARY'])) {
      return $keys['keys']['PRIMARY']['columns'];
    }

    return [];
  }


  /**
   * @param string $table
   * @param string $column
   * @param array $col
   * @return bool
   * @throws \Exception
   */
  public function createColumn(string $table, string $column, array $col): bool
  {
    if (($table = $this->tableFullName($table, true)) && Str::checkName($column)) {
      $column_definition = $this->getColumnDefinitionStatement($column, $col);

      return (bool)$this->rawQuery("ALTER TABLE $table ADD $column_definition");
    }

    return false;
  }

  /**
   * Returns a statement for column definition.
   *
   * @param string $name
   * @param array $col
   * @return string
   * @throws \Exception
   */
  protected function getColumnDefinitionStatement(string $name, array $col): string
  {
    $st = '  ' . $this->escape($name) . ' ';

    if (!empty($col['type'])) {
      if (!in_array(strtolower($col['type']), self::$types)) {
        if (isset(self::$interoperability[strtolower($col['type'])])) {
          $st .= self::$interoperability[strtolower($col['type'])];
        }
        // No error: no type is fine
      }
      else {
        $st .= $col['type'];
      }
    }

    if (!empty($col['maxlength'])) {
      $st .= '('.$col['maxlength'].')';
    }

    if (empty($col['null'])) {
      $st .= ' NOT NULL';
    }

    if (array_key_exists('default', $col) && $col['default'] !== null) {
      $st .= ' DEFAULT ';
      if (($col['default'] === 'NULL')
        || bbn\Str::isNumber($col['default'])
        || strpos($col['default'], '(')
        || in_array(strtoupper($col['default']), ['CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP'])
      ) {
        $st .= (string)$col['default'];
      }
      else {
        $st .= "'" . trim($col['default'], "'") . "'";
      }
    }

    return $st;
  }

  public function getCfg(): array
  {
    return $this->cfg;
  }

  public function getHost(): ?string
  {
    return $this->host;
  }

  public function getConnectionCode()
  {
    return $this->connection_code;
  }

  public function __toString()
  {
    return 'sqlite';
  }

  private function correctTypes(array $data, array $cfg): array
  {
    foreach ($data as $c => $v) {
      if (!empty($cfg[$c])
        && !empty($cfg[$c]['type'])
      ) {
        $type = \strtolower($cfg[$c]['type']);
        $nullable = !empty($cfg[$c]['nullable']);

        // Integer
        if (\str_contains($type, 'int')) {
          if (($v === '') && $nullable) {
            $data[$c] = null;
          }
          else {
            $int = (int)$v;
            if (($int < PHP_INT_MAX) && ($int > -PHP_INT_MAX)) {
              $data[$c] = $int;
            }
            else {
              $data[$c] = (string)$v;
            }
          }
        }

        // Decimal
        elseif (($type === 'decimal')
          || ($type === 'float')
          || ($type === 'real')
        ) {
          if (($v === '') && $nullable) {
            $data[$c] = null;
          }
          else {
            $data[$c] = (float)$v;
          }
        }

        // Text
        elseif (\str_contains($type, 'char')
          || \str_contains($type, 'text')
        ) {
          if (empty($v) && $nullable) {
            $data[$c] = null;
          }
          elseif (Str::isJson($v)
            &&  strpos($v, '": ')
            && ($json = \json_decode($v))
          ) {
            $data[$c] = \json_encode($json);
          }
          else {
            $data[$c] = \normalizer_normalize(trim(trim($v, " "), "\t"));
          }
        }
      }
    }

    return $data;
  }
}
