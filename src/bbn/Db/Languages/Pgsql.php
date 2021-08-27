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
  public static $numeric_types = ['integer', 'int', 'smallint', 'tinyint', 'mediumint', 'bigint', 'decimal', 'numeric', 'float', 'double'];

  /** @var array Time and date column types */
  public static $date_types = ['date', 'time', 'datetime'];

  public static $types = [
    'tinyint',
    'smallint',
    'mediumint',
    'int',
    'bigint',
    'decimal',
    'float',
    'double',
    'bit',
    'char',
    'varchar',
    'binary',
    'varbinary',
    'tinyblob',
    'blob',
    'mediumblob',
    'longblob',
    'tinytext',
    'text',
    'mediumtext',
    'longtext',
    'enum',
    'set',
    'date',
    'time',
    'datetime',
    'timestamp',
    'year',
    'geometry',
    'point',
    'linestring',
    'polygon',
    'geometrycollection',
    'multilinestring',
    'multipoint',
    'multipolygon',
    'json',
  ];

  public static $interoperability = [
    'integer' => 'int',
    'real' => 'decimal',
    'text' => 'text',
    'blob' => 'blob'
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
    $params           = ['pgsql:host='
      .(in_array($cfg['host'], ['localhost', '127.0.0.1']) && empty($cfg['force_host']) ? gethostname() : $cfg['host'])
      .';port='.$cfg['port']
      .(empty($cfg['db']) ? '' : ';dbname=' . $cfg['db']),
      $cfg['user'],
      $cfg['pass'],
      [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'],
    ];

    try {
      $this->cacheInit();
      $this->current  = $cfg['db'] ?? null;
      $this->host     = $cfg['host'] ?? '127.0.0.1';
      $this->username = $cfg['user'] ?? null;
      $this->connection_code = $cfg['code_host'];

      $this->pdo = new \PDO(...$params);
      $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
      $this->cfg = $cfg;
      $this->setHash($params);

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
   */
  public function change(string $db): bool
  {
    if (($this->getCurrent() !== $db) && Str::checkName($db)) {
      $this->rawQuery("USE `$db`");
      $this->current = $db;
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
   * @param string $enc
   * @param string $collation
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

      try {
        $this->rawQuery("DROP DATABASE `$database`");
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
    if (null === $db) {
      $db = $this->getCurrent();
    }

    if (($db = $this->escape($db))
      && bbn\Str::checkName($user, $db)
      && (strpos($pass, "'") === false)
    ) {
      return (bool)$this->rawQuery(
        <<<MYSQL
CREATE USER '$user'@'{$this->getHost()}' IDENTIFIED BY '$pass';
GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,DROP,INDEX,ALTER
ON $db . *
TO '$user'@'{$this->getHost()}';
MYSQL
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
      $host = $this->getHost();
      $this->rawQuery("REVOKE ALL PRIVILEGES ON *.* FROM '$user'@'$host'");
      return (bool)$this->rawQuery("DROP USER '$user'@'$host'");
    }

    return false;
  }


  /**
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
        $cond .= " AND  user LIKE '$user' ";
      }

      if (!empty($host) && bbn\Str::checkName($host)) {
        $cond .= " AND  host LIKE '$host' ";
      }

      $us = $this->getRows(
        <<<MYSQL
SELECT DISTINCT host, user
FROM mysql.user
WHERE 1
$cond
MYSQL
      );
      $q  = [];
      foreach ($us as $u) {
        $gs = $this->getColArray("SHOW GRANTS FOR '$u[user]'@'$u[host]'");
        foreach ($gs as $g) {
          $q[] = $g;
        }
      }

      return $q;
    }

    return null;
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
    $cur = null;
    if ($database && ($this->getCurrent() !== $database)) {
      $cur = $this->getCurrent();
      $this->change($database);
    }

    $q    = $this->query('SHOW TABLE STATUS');
    $size = 0;
    while ($row = $q->getRow()) {
      if (!$type || ($type === 'data')) {
        $size += $row['Data_length'];
      }

      if (!$type || ($type === 'index')) {
        $size += $row['Index_length'];
      }
    }

    if ($cur !== null) {
      $this->change($cur);
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
      $row = $this->getRow('SHOW TABLE STATUS WHERE Name LIKE ?', $table);

      if (!$row) {
        throw new \Exception(X::_('Table ') . $table . X::_(' Not found'));
      }

      if (!$type || (strtolower($type) === 'index')) {
        $size += $row['Index_length'];
      }

      if (!$type || (strtolower($type) === 'data')) {
        $size += $row['Data_length'];
      }
    }

    return $size;
  }


  /**
   * Gets the status of a table
   *
   * @param string $table
   * @param string $database
   * @return mixed
   * @throws \Exception
   */
  public function status(string $table = '', string $database = '')
  {
    $cur = null;
    if ($database && ($this->getCurrent() !== $database)) {
      $cur = $this->getCurrent();
      $this->change($database);
    }

    $r = $this->getRow('SHOW TABLE STATUS WHERE Name LIKE ?', $table);
    if (null !== $cur) {
      $this->change($cur);
    }

    return $r;
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
      $uid = $this->getOne("SELECT replace(uuid(),'-','')");
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
            $st .= "'" . bbn\Str::escapeSquotes($v) . "'";
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
          $st .= ' DEFAULT ' . ($c['default'] === 'NULL' ? 'NULL' : "'" . bbn\Str::escapeSquotes($c['default']) . "'");
        }

        $lines[] = $st;
      }
    }

    if (count($lines)) {
      $sql = 'CREATE TABLE ' . $this->tableSimpleName($table_name, true) . ' (' . PHP_EOL . implode(',' . PHP_EOL, $lines) .
        PHP_EOL . ') ENGINE=' . $engine . ' DEFAULT CHARSET=' . $charset . ';';
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
        }, $r->fetchAll(\PDO::FETCH_ASSOC)
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
      return (new self ($this->cfg))->getTables();
    }

    $t2 = [];
    $query = "SELECT table_name
              FROM information_schema.tables
              WHERE table_schema = 'public'
              AND table_type = 'BASE TABLE'";

    if (($r = $this->rawQuery($query))
      && ($t1 = $r->fetchAll(\PDO::FETCH_NUM))
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
   *              "type" => "varchar",
   *            ],
   *            "surname" => [
   *              "position" => 3,
   *              "null" => 0,
   *              "key" => null,
   *              "default" => null,
   *              "extra" => "",
   *              "signed" => 0,
   *              "maxlength" => "30",
   *              "type" => "varchar",
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
   * @param null|string $table The table's name
   * @return null|array
   */
  public function getColumns(string $table): ?array
  {
    if (!$this->check()) {
      return null;
    }

    $r = [];
    if ($full = $this->tableFullName($table)) {
      $t            = explode('.', $full);
      [$db, $table] = $t;
      $sql          = <<<MYSQL
        SELECT *
        FROM `information_schema`.`COLUMNS`
        WHERE `TABLE_NAME` LIKE ?
        AND `TABLE_SCHEMA` LIKE ?
        ORDER BY `ORDINAL_POSITION` ASC
MYSQL;
      if ($rows = $this->getRows($sql, $table, $db)) {
        $p = 1;
        foreach ($rows as $row) {
          $f          = $row['COLUMN_NAME'];
          $has_length = (stripos($row['DATA_TYPE'], 'text') === false)
            && (stripos($row['DATA_TYPE'], 'blob') === false)
            && ($row['EXTRA'] !== 'VIRTUAL GENERATED');
          $r[$f]      = [
            'position' => $p++,
            'type' => $row['DATA_TYPE'],
            'null' => $row['IS_NULLABLE'] === 'NO' ? 0 : 1,
            'key' => \in_array($row['COLUMN_KEY'], ['PRI', 'UNI', 'MUL']) ? $row['COLUMN_KEY'] : null,
            'extra' => $row['EXTRA'],
            'signed' => strpos($row['COLUMN_TYPE'], ' unsigned') === false,
            'virtual' => $row['EXTRA'] === 'VIRTUAL GENERATED',
            'generation' => $row['GENERATION_EXPRESSION'],
          ];
          if (($row['COLUMN_DEFAULT'] !== null) || ($row['IS_NULLABLE'] === 'YES')) {
            $r[$f]['default'] = \is_null($row['COLUMN_DEFAULT']) ? 'NULL' : $row['COLUMN_DEFAULT'];
          }

          if (($r[$f]['type'] === 'enum') || ($r[$f]['type'] === 'set')) {
            if (preg_match_all('/\((.*?)\)/', $row['COLUMN_TYPE'], $matches)
              && !empty($matches[1])
              && \is_string($matches[1][0])
              && ($matches[1][0][0] === "'")
            ) {
              $r[$f]['values'] = explode("','", substr($matches[1][0], 1, -1));
              $r[$f]['extra']  = $matches[1][0];
            } else {
              $r[$f]['values'] = [];
            }
          } elseif (preg_match_all('/\((\d+)?(?:,)|(\d+)\)/', $row['COLUMN_TYPE'], $matches)) {
            if (empty($matches[1][0])) {
              if (!empty($matches[2][0])) {
                $r[$f]['maxlength'] = (int)$matches[2][0];
              }
            } else {
              $r[$f]['maxlength'] = (int)$matches[1][0];
              $r[$f]['decimals']  = (int)$matches[2][1];
            }
          }
        }

        /*
        else{
        preg_match_all('/(.*?)\(/', $row['Type'], $real_type);
        if ( strpos($row['Type'],'text') !== false ){
        $r[$f]['type'] = 'text';
        }
        else if ( strpos($row['Type'],'blob') !== false ){
        $r[$f]['type'] = 'blob';
        }
        else if ( strpos($row['Type'],'int(') !== false ){
        $r[$f]['type'] = 'int';
        }
        else if ( strpos($row['Type'],'char(') !== false ){
        $r[$f]['type'] = 'varchar';
        }
        if ( preg_match_all('/\((.*?)\)/', $row['Type'], $matches) ){
        $r[$f]['maxlength'] = (int)$matches[1][0];
        }
        if ( !isset($r[$f]['type']) ){
        $r[$f]['type'] = strpos($row['Type'], '(') ? substr($row['Type'],0,strpos($row['Type'], '(')) : $row['Type'];
        }
        }
        */
      }
    }

    return $r;
  }


  /**
   * Returns the keys of the given table.
   * @param string $table The table's name
   * @return null|array
   */
  public function getKeys(string $table): ?array
  {
    if (!$this->check()) {
      return null;
    }

    $r = [];
    if ($full = $this->tableFullName($table)) {
      $t            = explode('.', $full);
      [$db, $table] = $t;
      $r            = [];
      $indexes      = $this->getRows('SHOW INDEX FROM ' . $this->tableFullName($full, 1));
      $keys         = [];
      $cols         = [];
      foreach ($indexes as $i => $index) {
        $a = $this->getRow(
          <<<MYSQL
SELECT `CONSTRAINT_NAME` AS `name`,
`ORDINAL_POSITION` AS `position`,
`REFERENCED_TABLE_SCHEMA` AS `ref_db`,
`REFERENCED_TABLE_NAME` AS `ref_table`,
`REFERENCED_COLUMN_NAME` AS `ref_column`
FROM `information_schema`.`KEY_COLUMN_USAGE`
WHERE `TABLE_SCHEMA` LIKE ?
AND `TABLE_NAME` LIKE ?
AND `COLUMN_NAME` LIKE ?
AND (
  `CONSTRAINT_NAME` LIKE ? OR
  (`REFERENCED_TABLE_NAME` IS NOT NULL OR `ORDINAL_POSITION` = ?)
)
ORDER BY `KEY_COLUMN_USAGE`.`REFERENCED_TABLE_NAME` DESC
LIMIT 1
MYSQL
          ,
          $db,
          $table,
          $index['Column_name'],
          $index['Key_name'],
          $index['Seq_in_index']
        );
        if ($a) {
          $b = $this->getRow(
            <<<MYSQL
          SELECT `CONSTRAINT_NAME` AS `name`,
          `UPDATE_RULE` AS `update`,
          `DELETE_RULE` AS `delete`
          FROM `information_schema`.`REFERENTIAL_CONSTRAINTS`
          WHERE `CONSTRAINT_NAME` LIKE ?
          AND `CONSTRAINT_SCHEMA` LIKE ?
          AND `TABLE_NAME` LIKE ?
          LIMIT 1
MYSQL
            ,
            $a['name'],
            $db,
            $table
          );
        } elseif (isset($b)) {
          unset($b);
        }

        if (!isset($keys[$index['Key_name']])) {
          $keys[$index['Key_name']] = [
            'columns' => [$index['Column_name']],
            'ref_db' => isset($a, $a['ref_db']) ? $a['ref_db'] : null,
            'ref_table' => isset($a, $a['ref_table']) ? $a['ref_table'] : null,
            'ref_column' => isset($a, $a['ref_column']) ? $a['ref_column'] : null,
            'constraint' => isset($b, $b['name']) ? $b['name'] : null,
            'update' => isset($b, $b['update']) ? $b['update'] : null,
            'delete' => isset($b, $b['delete']) ? $b['delete'] : null,
            'unique' => $index['Non_unique'] ? 0 : 1,
          ];
        } else {
          $keys[$index['Key_name']]['columns'][] = $index['Column_name'];
          $keys[$index['Key_name']]['ref_db']    = $keys[$index['Key_name']]['ref_table'] = $keys[$index['Key_name']]['ref_column'] = null;
        }

        if (!isset($cols[$index['Column_name']])) {
          $cols[$index['Column_name']] = [$index['Key_name']];
        } else {
          $cols[$index['Column_name']][] = $index['Key_name'];
        }
      }

      $r['keys'] = $keys;
      $r['cols'] = $cols;
    }

    return $r;
  }


  /**
   * @param null|string $table The table for which to create the statement
   * @return string
   */
  public function getRawCreate(string $table): string
  {
    if (($table = $this->tableFullName($table, true))
      && ($r = $this->rawQuery("SHOW CREATE TABLE $table"))
    ) {
      return $r->fetch(\PDO::FETCH_ASSOC)['Create Table'];
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
      if (!in_array($col['type'], self::$types)) {
        if (isset(self::$interoperability[$col['type']])) {
          $st .= self::$interoperability[$col['type']];
        }
        else {
          throw new \Exception(X::_("Impossible to recognize the column type")." $col[type]");
        }
      }
      else {
        $st .= $col['type'];
      }

      if (($col['type'] === 'enum') || ($col['type'] === 'set')) {
        if (empty($col['extra'])) {
          throw new \Exception(X::_("Extra field is required for")." {$col['type']}");
        }

        $st .= ' (' . $col['extra'] . ')';
      }
      elseif (!empty($col['maxlength'])) {
        $st .= '(' . $col['maxlength'];
        if (!empty($col['decimals'])) {
          $st .= ',' . $col['decimals'];
        }

        $st .= ')';
      }

      if (in_array($col['type'], self::$numeric_types)
        && empty($col['signed'])
      ) {
        $st .= ' UNSIGNED';
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
        } else {
          $st .= $col['default'];
        }
      }
    }

    $st .= PHP_EOL . ') ENGINE=InnoDB DEFAULT CHARSET=utf8';
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
        $st .= '  ADD ';
        if (!empty($key['unique'])
          && isset($model['fields'][$key['columns'][0]])
          && ($model['fields'][$key['columns'][0]]['key'] === 'PRI')
        ) {
          $st .= 'PRIMARY KEY';
        } elseif (!empty($key['unique'])) {
          $st .= 'UNIQUE KEY ' . $this->escape($name);
        } else {
          $st .= 'KEY ' . $this->escape($name);
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

    return $st;
  }

  /**
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

      foreach ($model['keys'] as $name => $key) {
        $st .= ',' . PHP_EOL . '  ';
        if (
          !empty($key['unique']) &&
          (count($key['columns']) === 1) &&
          isset($model['fields'][$key['columns'][0]]) &&
          isset($model['fields'][$key['columns'][0]]['key']) &&
          $model['fields'][$key['columns'][0]]['key'] === 'PRI'
        ) {
          $st .= 'PRIMARY KEY';
        } elseif (!empty($key['unique'])) {
          $st .= 'UNIQUE KEY ' . $this->escape($name);
        } else {
          $st .= 'KEY ' . $this->escape($name);
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
    }

    return $st;
  }

  /**
   * Creates an index
   *
   * @param null|string $table
   * @param string|array $column
   * @param bool $unique
   * @param null $length
   * @return bool
   * @throws \Exception
   */
  public function createIndex(string $table, $column, bool $unique = false, $length = null): bool
  {
    $column = (array)$column;
    if ($length) {
      $length = (array)$length;
    }

    $name = Str::encodeFilename($table);
    if ($table = $this->tableFullName($table, true)) {
      foreach ($column as $i => $c) {
        if (!Str::checkName($c)) {
          $this->error("Illegal column $c");
        }

        $name      .= '_' . $c;
        $column[$i] = $this->escape($column[$i]);
        if (isset($length[$i]) && \is_int($length[$i]) && $length[$i] > 0) {
          $column[$i] .= '(' . $length[$i] . ')';
        }
      }

      $name = Str::cut($name, 50);
      return (bool)$this->rawQuery(
        'CREATE ' . ($unique ? 'UNIQUE ' : '') . "INDEX `$name` ON $table ( " .
        implode(', ', $column) . ' )'
      );
    }

    return false;
  }

  /**
   * Deletes an index
   *
   * @param null|string $table
   * @param string $key
   * @return bool
   * @throws \Exception
   */
  public function deleteIndex(string $table, string $key): bool
  {
    if (($table = $this->tableFullName($table, true))
      && Str::checkName($key)
    ) {
      return (bool)$this->rawQuery("ALTER TABLE $table DROP INDEX `$key`");
    }

    return false;
  }

  public function __toString()
  {
    return 'pgsql';
  }
}
