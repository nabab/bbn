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
class Pgsql extends Sql
{

  /**
   * @var array
   */
  protected array $cfg;

  /** @var string The connection code as it would be stored in option */
  protected $connection_code;

  /**
   * The host of this connection
   * @var string $host
   */
  protected $host;

  /**
   * The username of this connection
   * @var string $host
   */
  protected $username;

  /** @var array Allowed operators */
  public static $operators = ['!=', '=', '<>', '<', '<=', '>', '>=', 'like', 'clike', 'slike', 'not', 'is', 'is not', 'in', 'between', 'not like'];

  /** @var array Numeric column types */
  public static $numeric_types = [
    'integer',
    'int',
    'smallint',
    'tinyint',
    'smallint',
    'mediumint',
    'bigint',
    'decimal',
    'numeric',
    'float',
    'double',
    'double precision',
    'real'
  ];

  protected static $numeric_with_max_values = ['decimal', 'numeric'];

  /** @var array Time and date column types */
  public static $date_types = ['date', 'time', 'datetime'];

  public static $types = [
    'smallserial',
    'serial',
    'bigserial',
    'smallint',
    'int',
    'integer',
    'bigint',
    'decimal',
    'float',
    'real',
    'double precision',
    'numeric',
    'money',
    'bit',
    'bit varying',
    'character',
    'char',
    'varchar',
    'character varying',
    'bytea',
    'text',
    'date',
    'time',
    'timestamp',
    'timestampz',
    'timestamp without time zone',
    'timestamp with time zone',
    'time without time zone',
    'time with time zone',
    'interval',
    'point',
    'line',
    'lseg',
    'polygon',
    'box',
    'path',
    'circle',
    'json',
    'jsonb',
    'boolean'
  ];

  public static $interoperability = [
    'text' => 'text',
    'tinytext' => 'text',
    'mediumtext' => 'text',
    'longtext' => 'text',
    'blob' => 'bytea',
    'binary' => 'bytea',
    'varbinary' => 'bytea',
    'mediumblob' => 'bytea',
    'longblob' => 'bytea',
    'tinyint' => 'smallint',
    'mediumint' => 'integer',
    'double' => 'double precision',
    'datetime' => 'timestamp',
    'linestring' => 'line'
  ];

  public static $aggr_functions = [
    'AVG',
    'BIT_AND',
    'BIT_OR',
    'COUNT',
    'GROUP_CONCAT',
    'MAX',
    'MIN',
    'STD',
    'STDDEV_POP',
    'STDDEV_SAMP',
    'STDDEV',
    'SUM',
    'VAR_POP',
    'VAR_SAMP',
    'VARIANCE',
  ];

  /** @var string The quote character */
  public $qte = '';
  
  /**
   * Constructor
   * @param array $cfg
   */
  public function __construct(array $cfg)
  {
    if (!\extension_loaded('pdo_pgsql')) {
      throw new \Exception('The PgSql driver for PDO is not installed...');
    }

    $cfg = $this->getConnection($cfg);

    try {
      $this->cacheInit();
      $this->current  = $cfg['db'] ?? null;
      $this->host     = $cfg['host'] ?? '127.0.0.1';
      $this->username = $cfg['user'] ?? null;
      $this->connection_code = $cfg['code_host'];

      $this->pdo = new \PDO(...$cfg['args']);
      $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
      $this->cfg = $cfg;
      $this->setHash($cfg['args']);

      if (!empty($cfg['cache_length'])) {
        $this->cache_renewal = (int)$cfg['cache_length'];
      }

      if (isset($cfg['on_error'])) {
        $this->on_error = $cfg['on_error'];
      }

      unset($cfg['pass']);
    }
    catch (\PDOException $e){
      $err = X::_("Impossible to create the connection").
        " {$cfg['engine']}/Connection ". $this->getEngine()." to {$this->host} "
        .X::_("with the following error").$e->getMessage();
      throw new \Exception($err);
    }
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

  /**
   * @param array $cfg The user's options
   * @return array|null The final configuration
   */
  public function getConnection(array $cfg = []): ?array
  {
    if (!X::hasProps($cfg, ['host', 'user'])) {
      if (!defined('BBN_DB_HOST')) {
        throw new \Exception(X::_("No DB host defined"));
      }

      $cfg = [
        'host' => BBN_DB_HOST,
        'user' => defined('BBN_DB_USER') ? BBN_DB_USER : '',
        'pass' => defined('BBN_DB_PASS') ? BBN_DB_PASS : '',
        'db'   => defined('BBN_DATABASE') ? BBN_DATABASE : '',
      ];
    }

    $cfg['engine'] = 'pgsql';

    if (empty($cfg['host'])) {
      $cfg['host'] = '127.0.0.1';
    }

    if (empty($cfg['user'])) {
      $cfg['user'] = 'root';
    }

    if (!isset($cfg['pass'])) {
      $cfg['pass'] = '';
    }

    if (empty($cfg['port']) || !is_int($cfg['port'])) {
      $cfg['port'] = 5432;
    }

    $cfg['code_db']   = $cfg['db'] ?? '';
    $cfg['code_host'] = $cfg['user'].'@'.$cfg['host'];
    $cfg['args']      = ['pgsql:host='
      .(in_array($cfg['host'], ['localhost', '127.0.0.1']) && empty($cfg['force_host']) ? gethostname() : $cfg['host'])
      .';port='.$cfg['port']
      .(empty($cfg['db']) ? '' : ';dbname=' . $cfg['db']),
      $cfg['user'],
      $cfg['pass'],
      [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'],
    ];

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
    return;
  }


  /**
   * Changes the current database to the given one.
   *
   * @param string $db The database name or file
   * @return bool
   * @throws \Exception
   */
  public function change(string $db): bool
  {
    if (($this->getCurrent() !== $db) && Str::checkName($db)) {
      $old_db = $this->getCurrent();
      // Close the current connection
      $this->pdo = null;
      // Invoke the constructor method after changing the db name.
      // pgsql does not support changing database, only by creating new connection.
      $this->cfg['db'] = $db;

      try {
        $this->__construct($this->cfg);

      } catch (\Exception $e) {
        $this->cfg['db'] = $old_db;
        $this->__construct($this->cfg);
        throw new \Exception($e->getMessage());
      }

      return true;
    }

    return false;
  }


  /**
   * Disables foreign keys check.
   *
   * @return self
   */
  public function disableKeys(): self
  {
    // PostgreSQL does not provide any direct command or function to disable the Foreign key constraints.

    return $this;
  }


  /**
   * Enables foreign keys check.
   *
   * @return self
   */
  public function enableKeys(): self
  {
    // PostgreSQL does not provide any direct command or function to enable the Foreign key constraints.

    return $this;
  }

  /**
   * Creates a database
   *
   * @param string $database
   * @param string $enc
   * @return bool
   */
  private function createPgsqlDatabase(string $database, string $enc = 'UTF8'): bool
  {
    if (bbn\Str::checkName($database, $enc)) {
      return (bool)$this->rawQuery("CREATE DATABASE $database ENCODING '$enc'");
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
    return $this->createPgsqlDatabase($database);
  }


  /**
   * Drops the given database
   *
   * @param string $database
   * @return bool
   * @throws \Exception
   */
  public function dropDatabase(string $database): bool
  {
    if ($this->check()) {
      if (!Str::checkName($database)) {
        throw new \Exception(X::_("Wrong database name")." $database");
      }

      if ($database === $this->getCurrent()) {
        throw new \Exception(X::_('Cannot drop the currently open database!'));
      }

      try {
        $active_connections = $this->rawQuery("SELECT *
                                                FROM pg_stat_activity
                                                WHERE datname = '$database'");

        if ($active_connections->rowCount() > 0) {
          // Close all active connections
          $this->rawQuery("SELECT pg_terminate_backend (pg_stat_activity.pid)
                            FROM pg_stat_activity
                            WHERE pg_stat_activity.datname = '$database'");
        }

        $this->rawQuery("DROP DATABASE IF EXISTS $database");
      }
      catch (\Exception $e) {
        return false;
      }
    }

    return $this->check();
  }


  /**
   * Creates a database user
   *
   * @param string $user
   * @param string $pass
   * @param string|null $db
   * @return bool
   * @throws \Exception
   */
  public function createUser(string $user, string $pass, string $db = null): bool
  {
    if (bbn\Str::checkName($user)
      && (strpos($pass, "'") === false)
    ) {
      return (bool)$this->rawQuery(
        <<<PGSQL
CREATE USER $user WITH PASSWORD '$pass' CREATEDB;
PGSQL
      ) &&
        (bool)$this->rawQuery(
          <<<PGSQL
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA "public" TO $user;
PGSQL

        );
    }

    return false;
  }


  /**
   * Deletes a database user
   *
   * @param string $user
   * @return bool
   * @throws \Exception
   */
  public function deleteUser(string $user): bool
  {
    if (bbn\Str::checkName($user)) {
      $this->rawQuery("REVOKE ALL PRIVILEGES ON ALL TABLES IN SCHEMA \"public\" FROM $user");
      return (bool)$this->rawQuery("DROP USER $user");
    }

    return false;
  }


  /**
   * Return an array of users.
   *
   * @param string $user
   * @param string $host
   * @return array|null
   * @throws \Exception
   */
  public function getUsers(string $user = '', string $host = ''): ?array
  {
    if ($this->check()) {
      $cond = '';
      if (!empty($user) && bbn\Str::checkName($user)) {
        $cond .= " AND user LIKE '$user' ";
      }

      $us = $this->getRows(
        <<<PSQL
SELECT usename AS user FROM pg_catalog.pg_user
WHERE 1 = 1
$cond
PSQL
      );
      $q  = [];
      foreach ($us as $u) {
        $q[] = $u['user'];
      }

      return $q;
    }

    return null;
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
      return !!$res;
    }

    return false;
  }

  /**
   * Returns the comment (or an empty string if none) for a given table.
   *
   * @param string $table The table's name
   *
   * @return string The table's comment
   */
  public function getTableComment(string $table): string
  {
    if ($table = $this->tableFullName($table)) {
      return $this->getOne(
        "SELECT obj_description(oid)
         FROM pg_class
         WHERE relkind = 'r'
         AND relname = ?",
        $table
      ) ?? '';
    }

    return '';
  }

  /**
   * Gets the size of a database
   *
   * @param string $database
   * @param string $type
   * @return int
   * @throws \Exception
   */
  public function dbSize(string $database = '', string $type = ''): int
  {
    if ($database && ($this->getCurrent() !== $database)) {
      return $this->newInstance(
        array_merge($this->cfg, ['db' => $database])
      )
        ->dbSize($database, $type);
    }

    $size = 0;

    if ($tables = $this->getTables()) {
      if ($type === 'data') {
        $function = "pg_relation_size";
      }
      elseif ($type === 'index') {
        $function = "pg_indexes_size";
      }
      else {
        $function = "pg_total_relation_size";
      }

      $query = "SELECT ";
      $args  = [];
      foreach ($tables as $table) {
        $query .= "$function(?), ";
        $args[] = $table;
      }

      $table_sizes = $this->getRow(trim($query, ', '), ...$args);

      foreach ($table_sizes as $table_size) {
        $size += $table_size;
      }
    }

    return $size;
  }


  /**
   * Gets the size of a table
   *
   * @param string $table
   * @param string $type
   * @return int
   * @throws \Exception
   */
  public function tableSize(string $table, string $type = ''): int
  {
    $size = 0;
    if (bbn\Str::checkName($table)) {

      if ($type === 'data') {
        $function = "pg_relation_size";
      }
      elseif ($type === 'index') {
        $function = "pg_indexes_size";
      }
      else {
        $function = "pg_total_relation_size";
      }

      $row = $this->getRow("SELECT $function(?)", $table);

      if (!$row) {
        throw new \Exception(X::_('Table ') . $table . X::_(' Not found'));
      }

      $size = current(array_values($row));
    }

    return $size;
  }


  /**
   * Gets the status of a table
   *
   * @param string $table
   * @param string $database
   * @return array|false|null
   * @throws \Exception
   */
  public function status(string $table = '', string $database = '')
  {
    if ($database && ($this->getCurrent() !== $database)) {
      return $this->newInstance(
        array_merge($this->cfg, ['db' => $database])
      )
        ->status($table, $database);
    }

    $query = "SELECT *
              FROM pg_catalog.pg_tables
              WHERE schemaname != 'pg_catalog' 
              AND schemaname != 'information_schema'";

    if (!empty($table)) {
      $query .= " AND tablename LIKE ?";
    }

    return $this->getRow($query, !empty($table) ? $table : []);
  }


  /**
   * Returns a UUID
   *
   * @return string|null
   */
  public function getUid(): ?string
  {
    $uid = null;
    while (!bbn\Str::isBuid(hex2bin($uid))) {
      $uid = $this->getOne("SELECT gen_random_uuid()");
      $uid = str_replace('-', '', $uid);
    }

    return $uid;
  }


  /**
   * @param $table_name
   * @param array $columns
   * @param array|null $keys
   * @param bool $with_constraints
   * @param string $charset
   * @param string $engine
   * @return string
   */
  public function createTable($table_name, array $columns, array $keys = null, bool $with_constraints = false, string $charset = 'utf8', string $engine = 'InnoDB')
  {
    $lines = [];
    $sql   = '';
    foreach ($columns as $n => $c) {
      $name = $c['name'] ?? $n;
      if (isset($c['type']) && bbn\Str::checkName($name)) {
        $st = $this->colSimpleName($name, true) . ' ' . $c['type'];
        if (!empty($c['maxlength'])) {
          $st .= '(' . $c['maxlength'] . ')';
        } elseif (!empty($c['values']) && \is_array($c['values'])) {
          $st .= '(';
          foreach ($c['values'] as $i => $v) {
            if (Str::isInteger($v)) {
              $st .= bbn\Str::escapeSquotes($v);
            }
            else {
              $st .= "'" . bbn\Str::escapeSquotes($v) . "'";
            }
            if ($i < count($c['values']) - 1) {
              $st .= ',';
            }
          }

          $st .= ')';
        }

        if (empty($c['null'])) {
          $st .= ' NOT NULL';
        }

        if (isset($c['default'])) {
          $st .= ' DEFAULT ' . ($c['default'] === 'NULL' ? 'NULL' : "'" . bbn\Str::escapeSquotes($c['default']) . "'");
        }

        $lines[] = $st;
      }
    }

    if (count($lines)) {
      $sql = 'CREATE TABLE ' . $this->tableSimpleName($table_name, true) . ' (' . PHP_EOL . implode(',' . PHP_EOL, $lines) .
        PHP_EOL . ');';
    }

    return $sql;
  }

  /****************************************************************
   *                                                              *
   *                                                              *
   *                       STRUCTURE HELPERS                      *
   *                                                              *
   *                                                              *
   ****************************************************************/

  /**
   * Return databases' names as an array.
   *
   * ```php
   * X::dump($db->getDatabases());
   * /*
   * (array)[
   *      "db_customers",
   *      "db_clients",
   *      "db_empty",
   *      "db_example",
   *      "db_mail"
   *      ]
   * ```
   *
   * @return null|array
   * @throws \Exception
   */
  public function getDatabases(): ?array
  {
    if (!$this->check()) {
      return null;
    }

    $x = [];
    if ($r = $this->rawQuery("SELECT datname FROM pg_database WHERE datistemplate = false AND datname != 'postgres'")) {
      $x = array_map(
        function ($a) {
          return $a['datname'];
        }, $this->fetchAllResults($r, \PDO::FETCH_ASSOC)
      );
      sort($x);
    }

    return $x;
  }


  /**
   * Return tables' names of a database as an array.
   *
   * ```php
   * X::dump($db->getTables('db_example'));
   * /*
   * (array) [
   *        "clients",
   *        "columns",
   *        "cron",
   *        "journal",
   *        "dbs",
   *        "examples",
   *        "history",
   *        "hosts",
   *        "keys",
   *        "mails",
   *        "medias",
   *        "notes",
   *        "medias",
   *        "versions"
   *        ]
   * ```
   *
   * @param string $database Database name
   * @return null|array
   */
  public function getTables(string $database = ''): ?array
  {
    if (!$this->check()) {
      return null;
    }

    if (empty($database) || !bbn\Str::checkName($database)) {
      $database = $this->getCurrent();
    }

    if ($database !== $this->getCurrent()) {
      return (new self (
        array_merge($this->cfg, ['db' => $database])
      ))
        ->getTables();
    }

    $t2 = [];
    $query = "SELECT table_name
              FROM information_schema.tables
              WHERE table_schema = 'public'
              AND table_type = 'BASE TABLE'";

    if (($r = $this->rawQuery($query))
      && ($t1 = $this->fetchAllResults($r, \PDO::FETCH_NUM))
    ) {
      foreach ($t1 as $t) {
        $t2[] = $t[0];
      }
    }

    return $t2;
  }


  /**
   * Returns the columns' configuration of the given table.
   *
   * ``php
   * X::dump($db->getColumns('table_users'));
   * /* (array)[
   *            "id" => [
   *              "position" => 1,
   *              "null" => 0,
   *              "key" => "PRI",
   *              "default" => null,
   *              "extra" => "auto_increment",
   *              "signed" => 0,
   *              "maxlength" => "8",
   *              "type" => "int",
   *            ],
   *           "name" => [
   *              "position" => 2,
   *              "null" => 0,
   *              "key" => null,
   *              "default" => null,
   *              "extra" => "",
   *              "signed" => 0,
   *              "maxlength" => "30",
   *              "type" => "character varying",
   *            ],
   *            "surname" => [
   *              "position" => 3,
   *              "null" => 0,
   *              "key" => null,
   *              "default" => null,
   *              "extra" => "",
   *              "signed" => 0,
   *              "maxlength" => "30",
   *              "type" => "character varying",
   *            ],
   *            "address" => [
   *              "position" => 4,
   *              "null" => 0,
   *              "key" => "UNI",
   *              "default" => null,
   *              "extra" => "",
   *              "signed" => 0,
   *              "maxlength" => "30",
   *              "type" => "varchar",
   *            ],
   *          ]
   * ```
   *
   * @param string $table The table's name
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
      $keys         = $this->getKeys($table);
      $primary_keys = $keys['keys']['PRIMARY']['columns'] ?? [];

      $unique_keys_arr = array_filter($keys['keys'] ?? [], function ($item, $key) {
        return (bool)$item['unique'] === true && $key !== 'PRIMARY';
      }, ARRAY_FILTER_USE_BOTH);

      $unique_keys = array_map(function ($item) {
        return $item['columns'][0] ?? null;
      }, array_values($unique_keys_arr));

      $sql          = <<<PGSQL
        SELECT * FROM information_schema.columns 
        WHERE table_name LIKE ?
        ORDER BY ordinal_position
PGSQL;
      if ($rows = $this->getRows($sql, $table)) {
        foreach ($rows as $row) {
          $f          = $row['column_name'];

          $r[$f]      = [
            'position' => $row['ordinal_position'],
            'type' => $row['data_type'] === 'bytea' ? 'binary' : $row['data_type'],
            'udt_name' => $row['udt_name'],
            'null' => $row['is_nullable'] === 'NO' ? 0 : 1,
            'key' => in_array($row['column_name'], $primary_keys)
              ? 'PRI'
              : (in_array($row['column_name'], $unique_keys)
                ? 'UNI'
                : null),
            'extra' => strpos($row['column_default'], 'nextval') !== false &&
                       strpos($row['data_type'], 'int') !== false
              ? 'auto_increment'
              : '',
            'signed' => $row['numeric_precision'] !== null,
            'virtual' => false,
            'generation' => $row['generation_expression'],
          ];
          if ($row['column_default'] !== null || $row['is_nullable'] === 'YES') {
            $r[$f]['default'] = \is_null($row['column_default']) ? 'NULL' : $row['column_default'];
          }

          if ($row['character_maximum_length'] !== null) {
            $r[$f]['maxlength'] = $row['character_maximum_length'];
          }
          elseif ($row['numeric_precision'] !== null && $row['numeric_scale'] !== null) {
            $r[$f]['maxlength'] = $row['numeric_precision'];
            $r[$f]['decimals']  = $row['numeric_scale'];
          }
          elseif ($row['data_type'] === 'bytea') {
            $r[$f]['maxlength'] = 16;
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
   */
  public function getKeys(string $table): ?array
  {
    if (!$this->check()) {
      return null;
    }

    $r = [];
    if ($table = $this->tableFullName($table)) {
      $indexes      = $this->getRows(<<<PGSQL
SELECT
    i.relname as index_name,
    a.attname as column_name,
    ix.indisprimary as is_primary,
	  ix.indisunique as is_unique,
    CASE 
      WHEN ix.indisprimary = true THEN 'PRIMARY'
      ELSE i.relname
    END 
        AS key_name
from
    pg_class t,
    pg_class i,
    pg_index ix,
    pg_attribute a
where
    t.oid = ix.indrelid
    and i.oid = ix.indexrelid
    and a.attrelid = t.oid
    and a.attnum = ANY(ix.indkey)
    and t.relkind = 'r'
    and t.relname = '$table'
order by
    t.relname,
    i.relname;
PGSQL
);

      $keys         = [];
      $cols         = [];
      foreach ($indexes as $index) {
        if (!isset($keys[$index['key_name']])) {
          $keys[$index['key_name']] = [
            'columns' => [$index['column_name']],
            'ref_db' => null,
            'ref_table' => null,
            'ref_column' => null,
            'constraint' => null,
            'update' => null,
            'delete' => null,
            'unique' => (int)$index['is_unique'],
          ];
        } else {
          $keys[$index['key_name']]['columns'][] = $index['column_name'];
        }

        if (!isset($cols[$index['column_name']])) {
          $cols[$index['column_name']] = [$index['key_name']];
        } else {
          $cols[$index['column_name']][] = $index['key_name'];
        }
      }

      if ($constraints = $this->getRows(<<<PGSQL
SELECT
    tc.table_schema, 
    tc.constraint_name, 
    tc.table_name, 
    kcu.column_name, 
    ccu.table_schema AS foreign_table_schema,
    ccu.table_name AS foreign_table_name,
    ccu.column_name AS foreign_column_name 
FROM 
    information_schema.table_constraints AS tc 
    JOIN information_schema.key_column_usage AS kcu
      ON tc.constraint_name = kcu.constraint_name
      AND tc.table_schema = kcu.table_schema
    JOIN information_schema.constraint_column_usage AS ccu
      ON ccu.constraint_name = tc.constraint_name
      AND ccu.table_schema = tc.table_schema
WHERE tc.constraint_type = 'FOREIGN KEY' AND tc.table_name = '$table';
PGSQL
      )) {
        foreach ($constraints as $constraint) {
          if (!isset($keys[$constraint['column_name']])) {
            $keys[$constraint['constraint_name']] = [
              'columns' => [$constraint['column_name']],
              'ref_db' => $this->getCurrent(),
              'ref_table' => $constraint['foreign_table_name'],
              'ref_column' => $constraint['foreign_column_name'],
              'constraint' => $constraint['constraint_name'],
              'update' => null,
              'delete' => null,
              'unique' => 0,
            ];
          } else {
            $keys[$constraint['key_name']]['columns'][] = $constraint['column_name'];
            $keys[$constraint['key_name']]['ref_db']    = $keys[$constraint['key_name']]['ref_table'] = $keys[$constraint['key_name']]['ref_column'] = null;
          }

          if (!isset($cols[$constraint['column_name']])) {
            $cols[$constraint['column_name']] = [$constraint['constraint_name']];
          } else {
            $cols[$constraint['column_name']][] = $constraint['constraint_name'];
          }
        }
      }

      $r['keys'] = $keys;
      $r['cols'] = $cols;
    }

    return $r;
  }


  /**
   * @param string $table The table for which to create the statement
   * @return string
   */
  public function getRawCreate(string $table): string
  {
    if ($table = $this->tableFullName($table, true)){

      if ($r = $this->rawQuery(<<<PGSQL
SELECT 'CREATE TABLE $table' || '\n' || ' (' || '\n' || '' || 
    string_agg(column_list.column_expr, ', ' || '\n' || '') || 
    '' || '\n' || ');' AS create_table
FROM ( 
  SELECT '    ' || column_name || ' ' || 
    CASE 
        WHEN STRPOS(column_default, 'nextval') > 0 AND STRPOS(data_type, 'int') > 0
        THEN REPLACE(REPLACE(data_type, 'integer', 'serial'), 'int', 'serial')
        ELSE data_type 
      END
    || 
       coalesce('(' || character_maximum_length || ')', '') || 
	   CASE 
			WHEN STRPOS(data_type, 'int') = 0 AND numeric_precision IS NOT NULL AND numeric_scale IS NOT NUll 
			THEN '(' || numeric_precision || ',' ||  numeric_scale || ')'
			ELSE ''
		END ||
       CASE 
			WHEN is_nullable = 'YES' THEN '' 
			ELSE ' NOT NULL' 
		END ||
		CASE 
			WHEN STRPOS(column_default, 'nextval') > 0 AND STRPOS(data_type, 'int') > 0
			THEN ''
			ELSE coalesce(' DEFAULT ' || column_default || ' ', '') 
		END
	 
	AS column_expr
  FROM information_schema.columns
  WHERE table_schema = 'public' AND table_name = '$table'
  ORDER BY ordinal_position) column_list;
PGSQL
      )
    ) {
        return $r->fetch(\PDO::FETCH_ASSOC)['create_table'] ?? '';
      }
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

      $st .= '  ' . $this->escape($name) . ' ';
      $col_type = $col['type'];

      if (array_key_exists('default', $col) && strpos($col['default'], '::') !== FALSE) {
        [$col['default']] = explode('::', $col['default']);
      }

      if ($col_type === 'USER-DEFINED' && !empty($col['udt_name'])) {
        $col['type'] = $col['udt_name'];
      }

      if (!in_array($col_type, self::$types)) {
        if (isset(self::$interoperability[$col_type])) {
          $st      .= self::$interoperability[$col_type];
          $col_type = self::$interoperability[$col_type];
        }
        else if ($col_type === 'USER-DEFINED') {
          $st .= $col['type'];
        }
        else {
          throw new \Exception(X::_("Impossible to recognize the column type")." $col[type]");
        }
      }
      else {
        $st .= $col['type'];
      }

      if (
        !empty($col['maxlength']) && $col_type !== 'bytea' &&
        (
          (in_array($col_type, self::$numeric_types) && in_array($col_type, self::$numeric_with_max_values))
          ||
          !in_array($col_type, self::$numeric_types)
        )
      ) {
        $st .= '(' . $col['maxlength'];
        if (!empty($col['decimals'])) {
          $st .= ',' . $col['decimals'];
        }

        $st .= ')';
      }

      if (empty($col['null'])) {
        $st .= ' NOT NULL';
      }

      if (!empty($col['virtual'])) {
        $st .= ' GENERATED ALWAYS AS (' . $col['generation'] . ') VIRTUAL';
      } elseif (array_key_exists('default', $col)) {
        $st .= ' DEFAULT ';
        if (($col['default'] === 'NULL')
          || Str::isNumber($col['default'])
          || strpos($col['default'], '(')
          || in_array(strtoupper($col['default']), ['CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP'])
        ) {
          $st .= (string)$col['default'];
        }
        else {
          $st .= "'" . trim($col['default'], "'") . "'";
        }
      }
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
  public function getCreateKeys(string $table, array $model = null): string
  {
    $st = '';
    if (!$model) {
      $model = $this->modelize($table);
    }

    if ($model && !empty($model['keys'])) {
      $st   .= 'ALTER TABLE ' . $this->escape($table) . PHP_EOL;
      $last  = count($model['keys']) - 1;

      $i     = 0;
      foreach ($model['keys'] as $name => $key) {
        if (!empty($key['unique'])
          && isset($model['fields'][$key['columns'][0]])
          && ($model['fields'][$key['columns'][0]]['key'] === 'PRI')
        ) {
          $st .= 'ADD PRIMARY KEY';
        } elseif (!empty($key['unique'])) {
          $st .= 'ADD CONSTRAINT ' . $this->escape($name) . ' UNIQUE';
        } else {
          $i++;
          continue;
        }

        $st .= ' (' . implode(
            ',', array_map(
              function ($a) {
                return $this->escape($a);
              }, $key['columns']
            )
          ) . ')';
        $st .= $i === $last ? ';' : ',' . PHP_EOL;
        $i++;
      }
    }

    return trim($st, ',' . PHP_EOL);
  }

  /**
   * Return SQL string for table creation.
   *
   * @param string $table
   * @param array|null $model
   * @return string
   * @throws \Exception
   */
  public function getCreate(string $table, array $model = null): string
  {
    $st = '';
    if (!$model) {
      $model = $this->modelize($table);
    }

    if ($st = $this->getCreateTable($table, $model)) {

      if (empty($model['keys'])) {
        return $st;
      }

      $lines = X::split($st, PHP_EOL);
      $end   = array_pop($lines);
      $st    = X::join($lines, PHP_EOL);

      $indexes = [];

      foreach ($model['keys'] as $name => $key) {
        $separator = ',' . PHP_EOL . '  ';
        if (
          !empty($key['unique']) &&
          (count($key['columns']) === 1) &&
          isset($model['fields'][$key['columns'][0]]) &&
          isset($model['fields'][$key['columns'][0]]['key']) &&
          $model['fields'][$key['columns'][0]]['key'] === 'PRI'
        ) {
          $st .= $separator . 'PRIMARY KEY';
        } elseif (!empty($key['unique'])) {
          $st .= $separator . 'CONSTRAINT ' . $this->escape($name) . ' UNIQUE';
        } elseif (!empty($key['ref_table']) && !empty($key['ref_column'])) {
          continue;
        }
        else {
          // Pgsql does not support creating normal indexes in the create table statement
          // so will return it as another sql
          $indexes[$name] = $key;
          continue;
        }

        $st   .= ' (' . implode(
            ',', array_map(
              function ($a) {
                return $this->escape($a);
              }, $key['columns']
            )
          ) . ')';
      }

      // For avoiding constraint names conflicts
      $keybase = strtolower(Str::genpwd(8, 4));
      $i       = 1;
      foreach ($model['keys'] as $name => $key) {
        if (!empty($key['ref_table']) && !empty($key['ref_column'])) {
          $st .= ',' . PHP_EOL . '  ' .
            'CONSTRAINT ' . $this->escape($keybase.$i) . ' FOREIGN KEY (' . $this->escape($key['columns'][0]) . ') ' .
            'REFERENCES ' . $this->escape($key['ref_table']) . ' (' . $this->escape($key['ref_column']) . ')' .
            (!empty($key['delete']) ? ' ON DELETE ' . $key['delete'] : '') .
            (!empty($key['update']) ? ' ON UPDATE ' . $key['update'] : '');
          $i++;
        }
      }

      $st .= PHP_EOL . $end;

      if (!empty($indexes)) {
        $st .= ';' . PHP_EOL;
        foreach ($indexes as $name => $index) {
          $st   .= 'CREATE INDEX ' . $this->escape($name) . " ON $table";
          $st   .= ' (' . implode(
              ',', array_map(
                function ($a) {
                  return $this->escape($a);
                }, $index['columns']
              )
            ) . ')';
          $st .= ";" . PHP_EOL;
        }
      }
    }

    return $st;
  }

  /**
   * Creates an index
   *
   * @param string $table
   * @param string|array $column
   * @param bool $unique
   * @param null $length
   * @return bool
   * @throws \Exception
   */
  public function createIndex(string $table, $column, bool $unique = false, $length = null): bool
  {
    $column = (array)$column;

    $name = Str::encodeFilename($table);
    if ($table = $this->tableFullName($table, true)) {
      foreach ($column as $i => $c) {
        if (!Str::checkName($c)) {
          $this->error("Illegal column $c");
        }

        $name      .= '_' . $c;
        $column[$i] = $this->escape($column[$i]);
      }

      $name = Str::cut($name, 50);
      return (bool)$this->rawQuery(
        'CREATE ' . ($unique ? 'UNIQUE ' : '') . "INDEX $name ON $table ( " .
        implode(', ', $column) . ' )'
      );
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
    if (Str::checkName($key)) {
      return (bool)$this->rawQuery("DROP INDEX $key CASCADE");
    }

    return false;
  }

  /**
   * Returns a table's full name i.e. database.table
   *
   * @param string $table The table's name (escaped or not)
   * @param bool $escaped If set to true the returned string will be escaped
   * @return string|null
   */
  public function tableFullName(string $table, bool $escaped = false): ?string
  {
    if ($full = parent::tableFullName($table, $escaped)) {
      return explode('.', $full)[1] ?? null;
    }

    return null;
  }

  public function getHexStatement(string $col_name): string
  {
    return "encode($col_name, 'hex')";
  }

  /**
   * Generates a string for the insert from a cfg array.
   * @param array $cfg The configuration array
   * @return string
   * @throws \Exception
   */
  public function getInsert(array $cfg): string
  {
    $fields_to_put = [
      'values' => [],
      'fields' => [],
    ];
    $i             = 0;
    foreach ($cfg['fields'] as $alias => $f) {
      if (isset($cfg['available_fields'][$f], $cfg['models'][$cfg['available_fields'][$f]])) {
        $model  = $cfg['models'][$cfg['available_fields'][$f]];
        $csn    = $this->colSimpleName($f);
        $is_uid = false;
        //X::hdump('---------------', $idx, $f, $tables[$idx]['model']['fields'][$csn], $args['values'],
        // $res['values'], '---------------');
        if (isset($model['fields'][$csn])) {
          $column = $model['fields'][$csn];
          if (($column['type'] === 'binary') && ($column['maxlength'] === 16)) {
            $is_uid = true;
          }

          $fields_to_put['fields'][] = $this->colSimpleName($f, true);
          $fields_to_put['values'][] = '?';
        }
      } else {
        $this->error("Error! The column '$f' doesn't exist in '" . implode(', ', $cfg['tables']));
      }

      $i++;
    }

    if (count($fields_to_put['fields']) && (count($cfg['tables']) === 1)) {
      return 'INSERT INTO ' . $this->tableFullName(current($cfg['tables']), true) . PHP_EOL .
        '(' . implode(', ', $fields_to_put['fields']) . ')' . PHP_EOL . ' VALUES (' .
        implode(', ', $fields_to_put['values']) . ')' . PHP_EOL .
        (!empty($cfg['ignore']) ? ' ON CONFLICT DO NOTHING' : '') . PHP_EOL;
    }

    return '';
  }

  /**
   * @param array $cfg The configuration array
   * @return string
   * @throws \Exception
   */
  public function getUpdate(array $cfg): string
  {
    $res           = '';
    $fields_to_put = [
      'values' => [],
      'fields' => [],
    ];
    foreach ($cfg['fields'] as $alias => $f) {
      if (isset($cfg['available_fields'][$f], $cfg['models'][$cfg['available_fields'][$f]])) {
        $model  = $cfg['models'][$cfg['available_fields'][$f]];
        $csn    = $this->colSimpleName($f);
        $is_uid = false;
        if (isset($model['fields'][$csn])) {
          $column = $model['fields'][$csn];
          if (($column['type'] === 'binary') && ($column['maxlength'] === 16)) {
            $is_uid = true;
          }

          $fields_to_put['fields'][] = $this->colSimpleName($f, true);
          $fields_to_put['values'][] = '?';
        }
      } else {
        $this->error("Error!! The column '$f' doesn't exist in '" . implode(', ', $cfg['tables']));
      }
    }

    if (count($fields_to_put['fields']) && (count($cfg['tables']) === 1)) {
      $res .= 'UPDATE ' . $this->tableFullName(current($cfg['tables']), true) . ' SET ';
      $last = count($fields_to_put['fields']) - 1;
      foreach ($fields_to_put['fields'] as $i => $f) {
        $res .= $f . ' = ' . $fields_to_put['values'][$i];
        if ($i < $last) {
          $res .= ',';
        }

        $res .= PHP_EOL;
      }
    }

    return $res;
  }

  /**
   * Return SQL code for row(s) DELETE.
   *
   * ```php
   * X::dump($db->getDelete(['tables' => 'users']);
   * // (string) DELETE FROM `db_example`.`table_users`
   * ```
   *
   * @param array $cfg The configuration array
   * @return string
   *
   */
  public function getDelete(array $cfg): string
  {
    $res = '';
    if (count($cfg['tables']) === 1) {
      $res = 'DELETE ' .
        (count($cfg['join'] ?? []) ? current($cfg['tables']) . ' ' : '') .
        'FROM ' . $this->tableFullName(current($cfg['tables']), true) . PHP_EOL;
    }

    return $res;
  }

  /**
   * Get a string starting with LIMIT with corresponding parameters to $where
   *
   * @param array $cfg
   * @return string
   */
  public function getLimit(array $cfg): string
  {
    $res = '';
    if (!empty($cfg['limit']) && Str::isInteger($cfg['limit'])) {
      $res .= 'LIMIT ' . $cfg['limit'] . (!empty($cfg['start']) && Str::isInteger($cfg['start']) ? ' OFFSET ' .  $cfg['start'] : '');
    }

    return $res;
  }

  /**
   * Returns a new instance of the class.
   * Used when changing database since pgsql does not support it.
   *
   * @param array $cfg
   * @return self
   * @throws \Exception
   */
  private function newInstance(array $cfg): self
  {
    $instance = new self($cfg);

    if ($this->_fancy) {
      $instance->startFancyStuff();
    } else {
      $instance->stopFancyStuff();
    }

    return $instance;
  }

  public function __toString()
  {
    return 'pgsql';
  }
}
