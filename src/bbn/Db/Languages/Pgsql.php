<?php
/**
 * @package db
 */
namespace bbn\Db\Languages;

use PDO;
use PDOException;
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

  /** @var string The quote character */
  public $qte = '';

  protected static $defaultCharset = 'UTF8';

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

  public static $binary_types = ['bytea', 'uuid'];

  public static $text_types = ['text', 'varchar', 'character', 'char'];

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
    'uuid',
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

  /* public static $interoperability = [
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
  ]; */

  public static $interoperability = [
    'smallserial'                 => ['sqlite' => 'integer', 'mysql' => 'smallint'],
    'serial'                      => ['sqlite' => 'integer', 'mysql' => 'int'],
    'bigserial'                   => ['sqlite' => 'integer', 'mysql' => 'bigint'],
    'smallint'                    => ['sqlite' => 'integer', 'mysql' => 'smallint'],
    'int'                         => ['sqlite' => 'integer', 'mysql' => 'int'],
    'integer'                     => ['sqlite' => 'integer', 'mysql' => 'int'],
    'bigint'                      => ['sqlite' => 'integer', 'mysql' => 'bigint'],
    'decimal'                     => ['sqlite' => 'real',    'mysql' => 'decimal'],
    'float'                       => ['sqlite' => 'real',    'mysql' => 'float'],
    'real'                        => ['sqlite' => 'real',    'mysql' => 'real'],
    'double precision'            => ['sqlite' => 'real',    'mysql' => 'double'],
    'numeric'                     => ['sqlite' => 'real',    'mysql' => 'decimal'],
    'money'                       => ['sqlite' => 'real',    'mysql' => 'decimal'],
    'bit'                         => ['sqlite' => 'bit',     'mysql' => 'bit'],
    'bit varying'                 => ['sqlite' => 'varbit',  'mysql' => 'bit'],
    'character'                   => ['sqlite' => 'text',    'mysql' => 'char'],
    'char'                        => ['sqlite' => 'text',    'mysql' => 'char'],
    'varchar'                     => ['sqlite' => 'text',    'mysql' => 'varchar'],
    'character varying'           => ['sqlite' => 'text',    'mysql' => 'varchar'],
    'bytea'                       => ['sqlite' => 'blob',    'mysql' => 'binary'],
    'text'                        => ['sqlite' => 'text',    'mysql' => 'text'],
    'date'                        => ['sqlite' => 'text',    'mysql' => 'date'],
    'time'                        => ['sqlite' => 'text',    'mysql' => 'time'],
    'timestamp'                   => ['sqlite' => 'integer', 'mysql' => 'timestamp'],
    'timestampz'                  => ['sqlite' => 'integer', 'mysql' => 'timestamp'],
    'timestamp without time zone' => ['sqlite' => 'integer', 'mysql' => 'timestamp'],
    'timestamp with time zone'    => ['sqlite' => 'integer', 'mysql' => 'timestamp'],
    'time without time zone'      => ['sqlite' => 'text',    'mysql' => 'time'],
    'time with time zone'         => ['sqlite' => 'text',    'mysql' => 'time'],
    'interval'                    => ['sqlite' => 'blob',    'mysql' => 'blob'],
    'point'                       => ['sqlite' => 'blob',    'mysql' => 'point'],
    'line'                        => ['sqlite' => 'blob',    'mysql' => 'linestring'],
    'lseg'                        => ['sqlite' => 'blob',    'mysql' => 'linestring'],
    'polygon'                     => ['sqlite' => 'blob',    'mysql' => 'polygon'],
    'box'                         => ['sqlite' => 'blob',    'mysql' => 'geometry'],
    'path'                        => ['sqlite' => 'blob',    'mysql' => 'geometry'],
    'circle'                      => ['sqlite' => 'blob',    'mysql' => 'geometry'],
    'json'                        => ['sqlite' => 'text',    'mysql' => 'json'],
    'jsonb'                       => ['sqlite' => 'text',    'mysql' => 'json'],
    'boolean'                     => ['sqlite' => 'integer', 'mysql' => 'tinyint']
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

  /**
   * Constructor
   * @param array $cfg
   */
  public function __construct(array $cfg)
  {
    if (!\extension_loaded('pdo_pgsql')) {
      throw new \Exception('The PgSql driver for PDO is not installed...');
    }

    parent::__construct($cfg);
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
        'host' => constant('BBN_DB_HOST'),
        'user' => defined('BBN_DB_USER') ? constant('BBN_DB_USER') : '',
        'pass' => defined('BBN_DB_PASS') ? constant('BBN_DB_PASS') : '',
        'db'   => defined('BBN_DATABASE') ? constant('BBN_DATABASE') : '',
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
    $cfg['args']      = ['pgsql:host=' . $cfg['host']
      . ';port=' . $cfg['port']
      . (empty($cfg['db']) ? '' : ';dbname=' . $cfg['db']),
      $cfg['user'],
      $cfg['pass'],
      [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'],
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
   * Returns the SQL statement to get the list of charsets.
   *
   * @return string
   */
  public function getCharsets(): string
  {
    return "SELECT DISTINCT " . $this->escape("pg_catalog") . ".pg_encoding_to_char(" .
      $this->escape("conforencoding") . ") AS " . $this->escape("charset") . PHP_EOL .
      "FROM " . $this->escape("pg_catalog.pg_conversion") . ";";
  }


  /**
   * Returns the SQL statement to get the list of collations.
   *
   * @return string
   */
  public function getCollations(): string
  {
    return "SELECT " . $this->escape("collname") . " AS " . $this->escape("collation") . PHP_EOL .
      "FROM " . $this->escape("pg_collation") . ";";
  }


  /**
   * Returns the SQL statement to create a database.
   *
   * @param string $database
   * @param string|null $enc
   * @param string|null $collation
   * @return string
   */
  public function getCreateDatabase(string $database, ?string $enc = null, ?string $collation = null): string
  {
    $enc = $enc ?: self::$defaultCharset;
    return "CREATE DATABASE ".$this->escape($database)." ENCODING '$enc';";
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
        throw new \Exception(X::_("Wrong database name '%s'", $database));
      }

      if ($database === $this->getCurrent()) {
        throw new \Exception(X::_('Cannot drop the currently open database!'));
      }

      try {
        $active_connections = $this->rawQuery("
          SELECT *
          FROM pg_stat_activity
          WHERE datname = '$database'"
        );

        if ($active_connections->rowCount() > 0) {
          // Close all active connections
          $this->rawQuery("
            SELECT pg_terminate_backend (pg_stat_activity.pid)
            FROM pg_stat_activity
            WHERE pg_stat_activity.datname = '$database'"
          );
        }

        if ($sql = $this->getDropDatabase($database)) {
          $this->rawQuery($sql);
        }
      }
      catch (\Exception $e) {
        return false;
      }
    }

    return $this->check();
  }


  /**
   * Returns the SQL statement to drop a database.
   *
   * @param string $database
   * @return string
   */
  /* public function getDuplicateDatabase(string $source, string $target): string
  {
    if (Str::checkName($source) && Str::checkName($target)) {
      return "CREATE DATABASE ".$this->escape($target)." WITH TEMPLATE ".$this->escape($source).";";
    }

    return '';
  } */


  /**
   * Returns the SQL statement to get the charset of a database.
   *
   * @param string $database
   * @return string
   */
  public function getCharsetDatabase(string $database): string
  {
    if (Str::checkName($database)) {
      return <<<SQL
        SELECT pg_encoding_to_char(encoding) AS charset
        FROM pg_database
        WHERE datname = '$database';
      SQL;
    }

    return '';
  }


  /**
   * Returns the SQL statement to get the collation of a database.
   *
   * @param string $database
   * @return string
   */
  public function getCollationDatabase(string $database): string
  {
    if (Str::checkName($database)) {
      return <<<SQL
        SELECT datcollate AS collation
        FROM pg_database
        WHERE datname = '$database';
      SQL;
    }

    return '';
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
  public function createUser(string $user, string $pass, string|null $db = null): bool
  {
    if (Str::checkName($user)
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
    if (Str::checkName($user)) {
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
      if (!empty($user) && Str::checkName($user)) {
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
   * Returns the SQL statement to get the collation of a table.
   *
   * @param string $table
   * @return string
   */
  public function getCollationTable(string $table): string
  {
    if (Str::checkName($table)) {
      return $this->getCollationDatabase($this->getCurrent());
    }

    return '';
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
    if (Str::checkName($table)) {

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
   * Returns true if the given table exists
   *
   * @param string $table
   * @param string $database. or currently selected if none
   * @return boolean
   */
  public function tableExists(string $table, string $database = 'public'): bool
  {
    $sql = <<<SQL
      SELECT * FROM pg_tables
      WHERE tablename = ?
      AND schemaname = ?;
    SQL;
    return (bool)$this->getRow($sql, $table, $database ?: 'public');
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
    $uid = $this->getOne("SELECT gen_random_uuid()");
    return str_replace('-', '', $uid);
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
        }, $this->fetchAllResults($r, PDO::FETCH_ASSOC)
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

    if (empty($database) || !Str::checkName($database)) {
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
      && ($t1 = $this->fetchAllResults($r, PDO::FETCH_NUM))
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
            $r[$f]['default'] = \is_null($row['column_default']) || strpos($row['column_default'], 'NULL') !== false
              ? 'NULL'
              : $row['column_default'];

            $r[$f]['defaultExpression'] = false;

             if (in_array(strtoupper($row['column_default']), ['CURRENT_TIME', 'CURRENT_DATE', 'CURRENT_TIMESTAMP', 'NOW()'])) {
               $r[$f]['defaultExpression'] = true;
             }
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
FROM
    pg_class t,
    pg_class i,
    pg_index ix,
    pg_attribute a
WHERE
    t.oid = ix.indrelid
    and i.oid = ix.indexrelid
    and a.attrelid = t.oid
    and a.attnum = ANY(ix.indkey)
    and t.relkind = 'r'
    and t.relname = '$table'
ORDER BY
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
    ccu.column_name AS foreign_column_name,
    rc.update_rule AS on_update,
    rc.delete_rule AS on_delete
FROM
    information_schema.table_constraints AS tc
    JOIN information_schema.key_column_usage AS kcu
      ON tc.constraint_name = kcu.constraint_name
      AND tc.table_schema = kcu.table_schema
    JOIN information_schema.constraint_column_usage AS ccu
      ON ccu.constraint_name = tc.constraint_name
      AND ccu.table_schema = tc.table_schema
    LEFT JOIN information_schema.referential_constraints AS rc
      ON tc.constraint_name = rc.constraint_name
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
              'update' => $constraint['on_update'] ?? null,
              'delete' => $constraint['on_delete'] ?? null,
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
        return $r->fetch(PDO::FETCH_ASSOC)['create_table'] ?? '';
      }
    }

    return '';
  }


  /**
   * @param string $table
   * @param array|null $cfg
   * @param bool $anonymize
   * @return string
   */
  public function getCreateKeys(string $table, ?array $cfg = null, bool $anonymize = false): string
  {
    $st = '';
    if (!$cfg) {
      $cfg = $this->modelize($table);
    }

    if ($cfg && !empty($cfg['keys'])) {
      if ($keys = array_filter($cfg['keys'], fn($k) => !empty($k['unique']))) {
        $st .= 'ALTER TABLE ' . $this->escape($table) . PHP_EOL;
        $last = count($keys) - 1;
        $i = 0;
        foreach ($keys as $name => $key) {
          if (isset($cfg['fields'][$key['columns'][0]])
            && ($cfg['fields'][$key['columns'][0]]['key'] === 'PRI')
          ) {
            $st .= '  ADD PRIMARY KEY';
          }
          else {
            $st .= '  ADD' . (empty($anonymize) ? (' CONSTRAINT ' . $this->escape($name)) : '') . ' UNIQUE';
          }

          $st .= ' ('.implode(
            ',',
            array_map(
              fn($a) => $this->escape($a),
              $key['columns']
            )
          ).')';
          $st .= $i === $last ? ';' : ',' . PHP_EOL;
          $i++;
        }

        return trim($st, ',' . PHP_EOL);
      }
    }

    return $st;
  }

  /**
   * @param string $table
   * @param array|null $cfg
   * @param bool $anonymize
   * @return string
   */
  public function getCreateConstraints(string $table, ?array $cfg = null, bool $anonymize = false): string
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
        $db = $this->escape($this->getCurrent());
        $st .= 'DO'.PHP_EOL.'$constraints$'.PHP_EOL.'BEGIN'.PHP_EOL;
        $i   = 0;
        foreach ($keys as $k) {
          $i++;
          $cols = implode(', ', array_map(fn($col) => $this->escape($col), $k['columns']));
          $refCols = is_array($k['ref_column']) ?
            implode(', ', array_map(fn($col) => $this->escape($col), $k['ref_column'])) :
            $this->escape($k['ref_column']);

          $st .= '  IF EXISTS (SELECT FROM information_schema.tables ' .
            "WHERE table_catalog = '" . $db . "' AND table_name = '" . $k['ref_table'] . "')".PHP_EOL. '  THEN'.PHP_EOL.
            '    ALTER TABLE ' . $this->escape($table) . PHP_EOL;

          $st .= '      ADD ' . (empty($anonymize) ? ('CONSTRAINT ' . $this->escape($k['constraint']) . ' ') : '') .
            'FOREIGN KEY (' . $cols . ') ' .
            'REFERENCES ' . $this->escape($k['ref_table']) . '(' . $refCols . ') ' .
            (!empty($k['delete']) ? ' ON DELETE ' . $k['delete'] : '') .
            (!empty($k['update']) ? ' ON UPDATE ' . $k['update'] : '') .
            ';'.PHP_EOL.'  END IF;'.PHP_EOL;
        }

        $st .= 'END'.PHP_EOL.'$constraints$;';
      }
    }

    return $st;
  }

  /**
   * Return a string for dropping a constraint.
   * @param string $table
   * @param string $constraint
   * @return string
   */
  public function getDropConstraint(string $table, string $constraint): string
  {
    return 'ALTER TABLE ' . $this->escape($table) . PHP_EOL .
      '  DROP CONSTRAINT ' . $this->escape($constraint) . ';';
  }


  /**
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
      $st   = 'ALTER TABLE ' . $this->escape($table) . PHP_EOL;
      $done = false;

      foreach ($cfg['fields'] as $name => $col) {
        if (!$done) {
          $done = true;
        } else {
          $st .= ',' . PHP_EOL;
        }

        $st .= $this->getAlterColumn($table, array_merge($col, [
          'col_name' => $name,
          'no_table_exp' => true
        ]));
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

    if ($alter_type === 'MODIFY') {
      if (!empty($cfg['new_name'])) {
        $st .= "RENAME COLUMN ";
        $st .= $this->escape($cfg['col_name']) . ' TO ' . $this->escape($cfg['new_name']);
      } else {
        $st .= "ALTER COLUMN ";
        $st .= $this->escape($cfg['col_name']) . ' TYPE ';
        $st .= $this->getColumnDefinitionStatement($cfg['col_name'], $cfg, false, true);
      }
    }
    elseif ($alter_type === 'DROP') {
      $st .= "DROP COLUMN " . $this->escape($cfg['col_name']);
    }
    else {
      $st .= $alter_type . ' COLUMN ' . $this->getColumnDefinitionStatement($cfg['col_name'], $cfg);
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
    return '';
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
   * Returns a statement for column definition.
   *
   * @param string $name
   * @param array $col
   * @param bool $includeColumnName
   * @param bool $for_alter
   * @return string
   * @throws \Exception
   */
  public function getColumnDefinitionStatement(
    string $name,
    array $cfg,
    bool $includeColumnName = true,
    bool $for_alter = false
  ): string
  {
    $st = '';
    if ($includeColumnName) {
      $st .= $this->escape($name) . ' ';
    }

    if (empty($cfg['type'])) {
      throw new \Exception(X::_('Column type is not provided'));
    }

    $col_type = $cfg['type'];

    if (array_key_exists('default', $cfg) && strpos($cfg['default'], '::') !== FALSE) {
      [$cfg['default']] = explode('::', $cfg['default']);
    }

    if ($col_type === 'USER-DEFINED') {
      if (!empty($cfg['udt_name'])) {
        $cfg['type'] = $cfg['udt_name'];
      }
      else {
        $cfg['type'] = $name;
      }
    }

    if (!in_array($col_type, self::$types)) {
      /* if (isset(self::$interoperability[$col_type])) {
        $st      .= self::$interoperability[$col_type];
        $col_type = self::$interoperability[$col_type];
      }
      else  */if ($col_type === 'USER-DEFINED') {
        $st .= $cfg['type'];
      }
      else {
        throw new \Exception(X::_("Impossible to recognize the column type")." $cfg[type]");
      }
    }
    else {
      $st .= $cfg['type'];
    }

    if (!empty($cfg['maxlength'])
      && ($col_type !== 'bytea')
      && ((in_array($col_type, self::$numeric_types)
          && in_array($col_type, self::$numeric_with_max_values))
        || !in_array($col_type, self::$numeric_types))
    ) {
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
      if ($for_alter) {
        $st .= ',' . PHP_EOL;
        $st .= 'ALTER COLUMN ' . $this->escape($name);
        $st .= ' SET NOT NULL,' . PHP_EOL;
      }
      else {
        $st .= ' NOT NULL';
      }
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
        $st .= ' DEFAULT ' . (is_numeric($cfg['default']) ? $cfg['default'] : "'".Str::escapeQuotes(trim((string)$cfg['default'], "'"))."'");
      }
    }

    if (!empty($cfg['virtual'])) {
      $st .= ' GENERATED ALWAYS AS (' . $cfg['generation'] . ') VIRTUAL';
    }

    if (!empty($cfg['position'])) {
      if (strpos($cfg['position'], 'after:') === 0) {
        $after = trim(substr($cfg['position'], 6));
        if (Str::checkName($after)) {
          $st .= ' AFTER ' . $this->escape($after);
        }
      } elseif (strtolower($cfg['position']) === 'first') {
        $st .= ' FIRST';
      }
    }

    return rtrim($st, ',' . PHP_EOL);
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
    foreach ($cfg['fields'] as $f) {
      if (isset($cfg['available_fields'][$f])) {
        $model  = $this->modelize($cfg['available_fields'][$f]);
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
    foreach ($cfg['fields'] as $f) {
      if (isset($cfg['available_fields'][$f])) {
        $model  = $this->modelize($cfg['available_fields'][$f]);
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
        $this->error("Error(bool) The column '$f' doesn't exist in '" . implode(', ', $cfg['tables']));
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