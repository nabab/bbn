<?php
/**
 * @package db
 */
namespace bbn\Db\Languages;

use Exception;
use PDO;
use PDOException;
use bbn\Str;
use bbn\X;
use bbn\File\Dir;
use bbn\Appui\Option;

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

  /** @var string The quote character */
  public $qte = '"';

  /** @var array Allowed operators */
  public static $operators = ['!=','=','<>','<','<=','>','>=','like','clike','slike','not','is','is not', 'in','between', 'not like'];

    /** @var array Numeric column types */
  public static $numeric_types = ['integer', 'real'];

  /** @var array Time and date column types don't exist in SQLite */
  public static $date_types = [];

  public static $binary_types = ['blob'];

  public static $text_types = ['text'];


  public static $types = [
    'integer',
    'real',
    'text',
    'blob'
  ];

  /* public static $interoperability = [
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
  ]; */

  public static $interoperability = [
    'integer' => ['mysql' => 'int',   'pgsql' => 'integer'],
    'real'    => ['mysql' => 'float', 'pgsql' => 'real'],
    'text'    => ['mysql' => 'text',  'pgsql' => 'text'],
    'blob'    => ['mysql' => 'blob',  'pgsql' => 'bytea']
  ];

  public static $aggr_functions = [
    'AVG',
    'COUNT',
    'GROUP_CONCAT',
    'MAX',
    'MIN',
    'SUM',
  ];

  protected static $defaultCharset = 'UTF-8';

  private $sqlite_keys_enabled = false;

  /**
   * Constructor
   * @param array $cfg
   * @throws Exception
   */
  public function __construct(array $cfg = [])
  {
    if (!\extension_loaded('pdo_sqlite')) {
      throw new Exception('The SQLite driver for PDO is not installed...');
    }

    parent::__construct($cfg);
  }

  /**
   * @param array $cfg The user's options
   * @return array|null The final configuration
   */
  public function getConnection(array $cfg = []): ?array
  {
    $cfg['engine'] = 'sqlite';

    if (!isset($cfg['db']) && \defined('BBN_DATABASE')) {
      $cfg['db'] = constant('BBN_DATABASE');
    }

    if (empty($cfg['db']) || !\is_string($cfg['db'])) {
      throw new Exception('Database name is not specified');
    }

    if (is_file($cfg['db'])) {
      $info        = X::pathinfo($cfg['db']);
      $cfg['host'] = $info['dirname'].DIRECTORY_SEPARATOR;
      $cfg['db']   = $info['filename'];
    }
    elseif (\defined('BBN_DATA_PATH')
      && is_dir(constant('BBN_DATA_PATH').'db')
      && (strpos($cfg['db'], '/') === false)
    ) {
      $cfg['host'] = constant('BBN_DATA_PATH').'db'.DIRECTORY_SEPARATOR;
      if (!is_file(constant('BBN_DATA_PATH').'db'.DIRECTORY_SEPARATOR.$cfg['db'])
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

    if (!isset($cfg['host'])
      || !is_file($cfg['host'].$cfg['db'])
    ) {
      throw new Exception('Db file could not be located');
    }

    $cfg['args'] = ['sqlite:'.$cfg['host'].$cfg['db']];
    $cfg['originalDb'] = $cfg['db'];
    $cfg['originalHost'] = $cfg['host'];
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
   * Returns true if the given table exists
   *
   * @param string $table
   * @param string $database. or currently selected if none
   * @return boolean
   */
  public function tableExists(string $table, string $database = ''): bool
  {
    return (bool)$this->getRow("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
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

    if (Str::checkName($table) && Str::checkName($db)) {
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

      if (Str::checkName($table)) {
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
   * @throws Exception
   */
  public function getDatabases(): ?array
  {
    return null;
    if (!$this->check()) {
      return null;
    }

    $x  = [];
    $fs = Dir::scan($this->host);
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
   * @throws Exception
   */
  public function getTables(string $database = ''): ?array
  {
    if (!$this->check()) {
      return null;
    }

    if (empty($database) || !Str::checkName($database)) {
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
        && $t1 = $this->fetchAllResults($r, PDO::FETCH_NUM)
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
   * @throws Exception
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
   * @throws Exception
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
      return $r->fetch(PDO::FETCH_ASSOC)['sql'] ?? '';
    }

    return '';
  }


  /**
   * Returns the SQL statement to get the charset of a database.
   *
   * @param string $database
   * @return string
   */
  public function getCharsetDatabase(string $database): string
  {
    return "PRAGMA encoding";
  }

  /**
   * Returns the SQL statement to analyze the current database.
   *
   * @return string
   */
  public function getAnalyzeDatabase(): string
  {
    return "ANALYZE;";
  }


  /**
   * @param string $table
   * @param array|null $cfg
   * @param bool $anonymize
   * @return string
   * @throws Exception
   */
  public function getCreateTable(string $table, ?array $cfg = null, bool $anonymize = false): string
  {
    if (!$cfg) {
      $cfg = $this->modelize($table);
    }

    $st   = 'CREATE TABLE ' . $this->escape($table) . ' (' . PHP_EOL;
    $numFields = count($cfg['fields']);
    $i = 0;
    foreach ($cfg['fields'] as $name => $col) {
      $i++;
      $st .= '  ' . $this->getColumnDefinitionStatement($name, $col);
      if ($i < $numFields) {
        $st .= ',' . PHP_EOL;
      }
    }

    if (isset($cfg['keys']['PRIMARY'])) {
      $st .= ',' . PHP_EOL . '  PRIMARY KEY (' . X::join(
        array_map(
          function ($a) {
            return $this->escape($a);
          },
          $cfg['keys']['PRIMARY']['columns']
        ),
        ', '
      ) . ')';
    }

    if ($c = $this->getCreateConstraintsOnly($table, $cfg, $anonymize)) {
      $st .= ',' . PHP_EOL . $c;
    }

    $st .= PHP_EOL . ');';
    return $st;
  }


  /**
   * Returns the SQL statement to duplicate a table.
   * This method generates a CREATE TABLE statement for the target table based on the source table.
   * @param string $source The name of the source table.
   * @param string $target The name of the target table.
   * @param bool $withData Whether to include data in the duplication.
   * @return array|null An array of SQL statements to duplicate the table, or null if the source table does not exist.
   */
  public function getDuplicateTable(string $source, string $target, bool $withData = true): ?array
  {
    if ($sql = $this->getCreateTableRaw($source, null, true, true, true)) {
      if (is_string($sql)) {
        $sql = [$sql];
      }

      $sql[0] = str_replace(
        'CREATE TABLE '.$this->escape($source),
        'CREATE TABLE ' . $this->escape($target),
        $sql[0]
      );
      if ($withData) {
        $columns = array_map(
          fn($c) => $this->escape($c),
          array_keys(
            array_filter(
              $this->getColumns($source),
              fn($c) => empty($c['virtual'])
            )
          )
        );
        if ($columns) {
          $sql[] = "INSERT INTO " . $this->escape($target) . " (" . implode(", ", $columns) . ") SELECT " . implode(", ", $columns) . " FROM " . $this->escape($source) . ";";
        }
      }

      return $sql;
    }

    return null;
  }


  /**
   * Returns the SQL statement to get the charset of a table.
   *
   * @param string $table
   * @return string
   */
  public function getCharsetTable(string $table): string
  {
    if (Str::checkName($table)) {
      return $this->getCharsetDatabase($this->getCurrent());
    }

    return '';
  }


  /**
   * @param string $table
   * @param array|null $cfg
   * @param bool $anonymize
   * @return null|array
   * @throws Exception
   */
  public function getCreateKeys(string $table, ?array $cfg = null, bool $anonymize = false): ?array
  {
    if (!$cfg) {
      $cfg = $this->modelize($table);
    }

    if ($cfg
      && !empty($cfg['keys'])
      && ($keys = array_filter(
        $cfg['keys'],
        fn($k) => !empty($k['columns']) && empty($k['ref_table']) && empty($k['ref_column'])
      ))
    ) {
      $sql = [];
      foreach ($keys as $name => $key) {
        if ($name === 'PRIMARY') {
          continue;
        }

        $st = 'CREATE ';
        if (!empty($key['unique'])) {
          $st .= 'UNIQUE ';
        }

        $st .= 'INDEX \''.Str::escapeSquotes($name).'\' ON ' . $this->escape($table);

        $st .= ' ('.X::join(
          array_map(
            fn($a) => $this->escape($a),
            $key['columns']
          ),
          ', '
        ).')';
        $st .= ';';
        $sql[] = $st;
      }

      return $sql;
    }

    return null;
  }


  /**
   * Return SQL string for table creation.
   *
   * @param string $table
   * @param array|null $cfg
   * @param bool $createKeys
   * @param bool $createConstraints
   * @param bool $anonymize
   * @return null|array
   */
  public function getCreateTableRaw(
    string $table,
    ?array $cfg = null,
    bool $createKeys = true,
    bool $createConstraints = true,
    bool $anonymize = false
  ): ?array
  {
    if (empty($cfg)) {
      $cfg = $this->modelize($table);
    }

    if (!$createKeys || !$createConstraints) {
      foreach ($cfg['keys'] as $k => $v) {
        if (!$createKeys
          && !empty($v['columns'])
          && empty($v['ref_table'])
          && empty($v['ref_column'])
        ) {
          unset($cfg['keys'][$k]);
        }

        if (!$createConstraints
          && !empty($v['columns'])
          && !empty($v['constraint'])
          && !empty($v['ref_table'])
          && !empty($v['ref_column'])
        ) {
          unset($cfg['keys'][$k]);
        }
      }
    }

    if ($sql = $this->getCreateTable($table, $cfg, $anonymize)) {
      $sql = [$sql];
      if ($createKeys
        && ($s = $this->getCreateKeys($table, $cfg, $anonymize))
      ) {
        if (is_string($s)) {
          $s = [$s];
        }

        array_push($sql, ...$s);
      }

      return $sql;
    }

    return null;
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
   * @throws Exception
   */
  public function getAlterTable(string $table, array $cfg): string
  {
    if (empty($cfg['fields'])) {
      throw new Exception(X::_('Fields are not specified'));
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
   * @throws Exception
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

    $name = Str::encodeFilename($table);
    foreach ($column as $i => $c){
      if (!Str::checkName($c)) {
        $this->error("Illegal column $c");
      }

      $name      .= '_'.$c;
      $column[$i] = '`'.$column[$i].'`';
      if (!empty($length[$i]) && \is_int($length[$i]) && $length[$i] > 0) {
        $column[$i] .= '('.$length[$i].')';
      }
    }

    $name = Str::cut($name, 50);
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
   * @throws Exception
   */
  public function deleteIndex(string $table, string $key): bool
  {
    if (($this->tableFullName($table, 1)) && Str::checkName($key)) {
      //changed the row above because if the table has no rows query() returns 0
      //return (bool)$this->db->query("ALTER TABLE $table DROP INDEX `$key`");
      return $this->query('DROP INDEX IF EXISTS '.$key) !== false;
    }

    return false;
  }


  /**
   * Returns the SQL statement to get the list of charsets.
   *
   * @return string
   */
  public function getCharsets(): string
  {
    return "SELECT " . $this->escape("column1") . " AS ". $this->escape("charset") . PHP_EOL .
      "FROM (VALUES('UTF-8'), ('UTF-16'), ('UTF-16le'), ('UTF-16be'))";
  }


  /**
   * Returns the SQL statement to get the list of collations.
   *
   * @return string
   */
  public function getCollations(): string
  {
    return "SELECT DISTINCT " . $this->escape("name"). " AS " . $this->escape("collation") . PHP_EOL .
      "FROM " . $this->escape("pragma_collation_list") . ";";
  }


  /**
   * Creates a database
   *
   * @param string $database
   * @param string|null $enc
   * @param string|null $collation
   * @return bool
   */
  public function createDatabase(string $database, ?string $enc = null, ?string $collation = null): bool
  {
    return static::createDatabaseOnHost($database, $this->host);
  }


  /**
   * Drops the given database
   *
   * @param string $database
   * @return bool
   */
  public function dropDatabase(string $database): bool
  {
    if ($database = self::normalizeFilename($database)) {
      if ($this->host.'/'.$database === $this->cfg['originalHost'].'/'.$this->cfg['originalDb']) {
        throw new \Exception(X::_('Cannot drop the currently open database!'));
      }

      return static::dropDatabaseOnHost($database, $this->host);
    }

    return false;
  }


  /**
   * Renames the given database
   *
   * @param string $oldDatabase
   * @param string $newDatabase
   * @return bool
   */
  public function renameDatabase(string $oldDatabase, string $newDatabase): bool
  {
    if (($oldDatabase = self::normalizeFilename($oldDatabase))
      && ($newDatabase = self::normalizeFilename($newDatabase))
    ) {
      if ($this->host.'/'.$oldDatabase === $this->cfg['originalHost'].'/'.$this->cfg['originalDb']) {
        throw new \Exception(X::_('Cannot drop the currently open database!'));
      }

      return static::renameDatabaseOnHost($oldDatabase, $newDatabase, $this->host);
    }

    return false;
  }


  /**
   * Duplicates the given database
   *
   * @param string $oldDatabase
   * @param string $newDatabase
   * @param bool $withData
   * @return bool
   */
  public function duplicateDatabase(string $oldDatabase, string $newDatabase, bool $withData = true): bool
  {
    return static::duplicateDatabaseOnHost($oldDatabase, $newDatabase, $this->host);
  }


  /**
   * Creates a database user
   *
   * @param string|null $user
   * @param string|null $pass
   * @param string|null $db
   * @return bool
   */
  public function createUser(string|null $user = null, string|null $pass = null, string|null $db = null): bool
  {
    return true;
  }


  /**
   * Deletes a database user
   *
   * @param string|null $user
   * @return bool
   */
  public function deleteUser(string|null $user = null): bool
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


  public function getTableComment(string $table): string
  {
    return '';
  }


  public function dbSize(string $database = '', string $type = ''): int
  {
    if (!str_ends_with($database, '.sqlite')
        && !str_ends_with($database, '.db')
      ) {
        $database .= '.sqlite';
      }

   return @filesize($this->host . $database) ?: 0;
  }


  public function tableSize(string $table, string $type = ''): int
  {
    return $this->getOne(
      'SELECT SUM(pgsize) FROM dbstat WHERE name = ?',
      $table
    ) ?: 0;
  }


  /**
   * Gets the status of a table.
   *
   * @param string $table
   * @param string $database
   * @return array|false|null
   * @throws Exception
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
    return X::makeUid();
  }


  /**
   * @param string $table
   * @param array|null $cfg
   * @param bool $anonymize
   * @return string
   */
  public function getCreateConstraintsOnly(string $table, ?array $cfg = null, bool $anonymize = false): string
  {
    $st = '';
    if (!$cfg) {
      $cfg = $this->modelize($table);
    }

    if ($cfg && !empty($cfg['keys'])) {
      $keys = array_values(array_filter(
        $cfg['keys'],
        fn($a) => !empty($a['columns'])
          && !empty($a['constraint'])
          && !empty($a['ref_table'])
          && !empty($a['ref_column'])
      ));
      if (!empty($keys)) {
        foreach ($keys as $i =>$k) {
          $cols = implode(', ', array_map(fn($col) => $this->escape($col), $k['columns']));
          $refCols = is_array($k['ref_column']) ?
            implode(', ', array_map(fn($col) => $this->escape($col), $k['ref_column'])) :
            $this->escape($k['ref_column']);
          $st .= '  ' . (empty($anonymize) ? ('CONSTRAINT ' . $this->escape($k['constraint']) . ' ') : '') .
            'FOREIGN KEY (' . $cols . ') ' .
            'REFERENCES ' . $this->escape($k['ref_table']) . '(' . $refCols . ') ' .
            (!empty($k['delete']) ? ' ON DELETE ' . $k['delete'] : '') .
            (!empty($k['update']) ? ' ON UPDATE ' . $k['update'] : '') .
            (isset($keys[$i + 1]) ? ',' . PHP_EOL : '');
        }
      }
    }

    return $st;
  }


  /**
   * @param string $table
   * @param array|null $cfg
   * @param bool $anonymize
   * @return null|array
   */
  public function getCreateConstraints(string $table, ?array $cfg = null, bool $anonymize = false): ?array
  {
    if (!$cfg) {
      $cfg = $this->modelize($table);
    }

    if ($cfg && !empty($cfg['keys'])) {
      $keys = array_values(
        array_filter(
          $cfg['keys'],
          fn($a) => !empty($a['columns'])
            && !empty($a['constraint'])
            && !empty($a['ref_table'])
            && !empty($a['ref_column'])
        )
      );
      if (!empty($keys)) {
        $tmpTable = Str::encodeFilename('_bbntmp_'.$table);
        $sql = $this->getCreateTable($tmpTable, $cfg, $anonymize);
        if (is_string($sql)) {
          $sql = [$sql];
        }

        if ($ctSql = $this->getCreateKeys($tmpTable, $cfg, $anonymize)) {
          if (is_string($ctSql)) {
            $ctSql = [$ctSql];
          }

          array_push($sql, ...$ctSql);
        }

        $sql[] = 'INSERT INTO '.$this->escape($tmpTable).' SELECT * FROM '.$this->escape($table).';';
        $sql[] = 'DROP TABLE '.$this->escape($table).';';
        $sql[] = 'ALTER TABLE '.$this->escape($tmpTable).' RENAME TO '.$this->escape($table).';';
      }
    }

    return null;
  }


  /**
   * Returns a string for dropping a constraint.
   *
   * @param string $table
   * @param string $constraint
   * @return null|array
   */
  public function getDropConstraint(string $table, string $constraint): ?array
  {
    if ($cfg = $this->modelize($table)) {
      $cfg['keys'] = array_filter(
        $cfg['keys'],
        fn($a) => empty($a['constraint'])
          || (strtolower($a['constraint']) !== strtolower($constraint))
      );
      $tmpTable = Str::encodeFilename('_bbntmp_'.$table);
      $sql = $this->getCreateTable($tmpTable, $cfg);
      if (is_string($sql)) {
        $sql = [$sql];
      }

      $sql[] = 'INSERT INTO '.$this->escape($tmpTable).' SELECT * FROM '.$this->escape($table).';';
      $sql[] = 'DROP TABLE '.$this->escape($table).';';
      $sql[] = 'ALTER TABLE '.$this->escape($tmpTable).' RENAME TO '.$this->escape($table).';';
      return $sql;
    }

    return null;
  }


  /**
   * Return primary keys of a table as a numeric array.
   *
   * @param string $table
   * @return array
   * @throws Exception
   */
  public function getPrimary(string $table): array
  {
    if (($keys = $this->getKeys($table)) && isset($keys['keys']['PRIMARY'])) {
      return $keys['keys']['PRIMARY']['columns'];
    }

    return [];
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


  public static function getHostDatabases(string $host): array
  {
    $databases = [];
    $host = self::getHostPath($host);
    if (is_dir($host)) {
      $fs = Dir::getFiles($host, false, false, ['sqlite', 'db']);
      foreach ($fs as $f) {
        if (is_file($f)) {
          $databases[] = X::pathinfo($f, PATHINFO_FILENAME);
        }
      }
    }

    sort($databases);
    return $databases;
  }

  public static function hasHostDatabase(string $host, string $database): bool
  {
    return in_array(
      self::normalizeFilename($database),
      self::getHostDatabases($host)
    );
  }


  public static function createDatabaseOnHost(string $database, string $host): bool
  {
    if (($database = self::normalizeFilename($database))
      && ($path = self::getHostPath($host))
      && !file_exists($path.$database)
    ) {
      file_put_contents($path.$database, '');
      return file_exists($path.$database);
    }

    return false;
  }


  public static function dropDatabaseOnHost(string $database, string $host): bool
  {
    if (($database = self::normalizeFilename($database))
      && ($path = self::getHostPath($host))
      && file_exists($path.$database)
    ) {
      return unlink($path.$database);
    }

    return false;
  }


  public static function renameDatabaseOnHost(string $oldDatabase, string $newDatabase, string $host): bool
  {
    if (($oldDatabase = self::normalizeFilename($oldDatabase))
      && ($newDatabase = self::normalizeFilename($newDatabase))
      && ($oldDatabase !== $newDatabase)
      && ($path = self::getHostPath($host))
      && file_exists($path.$oldDatabase)
    ) {
      return rename($path.$oldDatabase, $path.$newDatabase);
    }

    return false;
  }


  public static function duplicateDatabaseOnHost(string $oldDatabase, string $newDatabase, string $host): bool
  {
    if (($oldDatabase = self::normalizeFilename($oldDatabase))
      && ($newDatabase = self::normalizeFilename($newDatabase))
      && ($oldDatabase !== $newDatabase)
      && ($path = self::getHostPath($host))
      && file_exists($path.$oldDatabase)
    ) {
      return copy($path.$oldDatabase, $path.$newDatabase);
    }

    return false;
  }


  public static function normalizeFilename($filename): ?string
  {
    if (Str::checkFilename($filename)) {
      if (!str_ends_with($filename, '.sqlite')
        && !str_ends_with($filename, '.db')
      ) {
        $filename .= '.sqlite';
      }

      return $filename;
    }

    return null;
  }


  public static function getHostPath(string $host): string
  {
    $path = $host;
    if (Str::isUid($host)) {
      $opt = Option::getInstance();
      if ($code = $opt->code($host)) {
        $path = $code;
      }
      else {
        throw new Exception(X::_("Host '%s' not found", $host));
      }
    }

    $pbits = X::split($path, '/');
    foreach ($pbits as &$bit) {
      if (str_starts_with($bit, 'BBN_') && defined($bit)) {
        $bit = constant($bit);
        if (str_ends_with($bit, '/')) {
          $bit = rtrim($bit, '/');
        }
      }
    }

    return X::join($pbits, '/').'/';
  }


  /**
   * Returns a statement for column definition.
   *
   * @param string $name
   * @param array $cfg
   * @param bool $includeColumnName
   * @return string
   * @throws Exception
   */
  protected function getColumnDefinitionStatement(string $name, array $cfg, bool $includeColumnName = true): string
  {
    $st = '';
    if ($includeColumnName) {
      $st .= $this->escape($name) . ' ';
    }

    if (!empty($cfg['type'])) {
      if (!in_array(strtolower($cfg['type']), self::$types)) {
        /* if (isset(self::$interoperability[strtolower($cfg['type'])])) {
          $st .= self::$interoperability[strtolower($cfg['type'])];
        } */
        // No error: no type is fine
      }
      else {
        $st .= $cfg['type'];
      }
    }

    if (!empty($cfg['maxlength'])) {
      $st .= '(' . $cfg['maxlength'];
      if (!empty($cfg['decimals'])) {
        $st .= ',' . $cfg['decimals'];
      }

      $st .= ')';
    }

    if (!empty($cfg['collation'])) {
      $st .= ' COLLATE ' . Str::encodeFilename($cfg['collation']);
    }

    if (empty($cfg['null'])) {
      $st .= ' NOT NULL';
    }

    if (array_key_exists('default', $cfg)) {
      if (is_null($cfg['default'])
        || (strtoupper((string)$cfg['default']) === 'NULL')
      ) {
        $st .= ' DEFAULT NULL';
      }
      else if (!empty($cfg['defaultExpression'])) {
        $st .= ' DEFAULT ' . (string)$cfg['default'];
      }
      else if (isset($cfg['default'])
        && ($cfg['default'] !== '')
      ) {
        $st .= " DEFAULT " . (is_numeric($cfg['default']) ? $cfg['default'] : "'".Str::escapeQuotes(trim((string)$cfg['default'], "'"))."'");
      }
    }

    if (!empty($cfg['virtual'])) {
      $st .= ' GENERATED ALWAYS AS (' . $cfg['generation'] . ') VIRTUAL';
    }

    return $st;
  }


}