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
class Mysql extends Sql
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

  /**
   * Constructor
   *
   * @param array $cfg
   * @throws Exception
   */
  public function __construct(array $cfg)
  {
    if (!\extension_loaded('pdo_mysql')) {
      throw new Exception(X::_("The MySQL driver for PDO is not installed..."));
    }

    $cfg = $this->getConnection($cfg);

    try {
      $this->cacheInit();
      $this->current = $cfg['db'] ?? null;
      $this->host = $cfg['host'] ?? '127.0.0.1';
      $this->username = $cfg['user'] ?? null;
      $this->connection_code = $cfg['code_host'];

      $this->pdo = new PDO(...$cfg['args']);
      $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
      $this->pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);
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
    catch (PDOException $e) {
      $err = X::_("Impossible to create the connection") .
        " $cfg[engine] ".X::_("to")." {$this->host} "
        . X::_("with the following error") . " " . $e->getMessage();
        X::log($cfg);
      throw new Exception($err);
    }
  }

  
  /**
   * @param array $cfg The user's options
   * @return array|null The final configuration
   */
  public function getConnection(array $cfg = []): ?array
  {
    if (!X::hasProps($cfg, ['host', 'db'])) {
      if (!defined('BBN_DB_HOST')) {
        throw new Exception(X::_("No DB host defined"));
      }

      $cfg = [
        'host' => BBN_DB_HOST,
        'user' => defined('BBN_DB_USER') ? BBN_DB_USER : '',
        'pass' => defined('BBN_DB_PASS') ? BBN_DB_PASS : '',
        'db' => defined('BBN_DATABASE') ? BBN_DATABASE : '',
      ];
    }

    $cfg['engine'] = 'mysql';

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
      $cfg['port'] = 3306;
    }

    if (empty($cfg['charset'])) {
      $cfg['charset'] = 'utf8mb4';
    }

    $cfg['code_db'] = $cfg['db'] ?? '';
    $cfg['code_host'] = $cfg['user'] . '@' . $cfg['host'];
    $cfg['args'] = [
      'mysql:'
        . (empty($cfg['db']) ? '' : ('dbname=' . $cfg['db']). ';')
        . 'host=' . $cfg['host'] . ';'
        . 'port=' . $cfg['port'],
      $cfg['user'],
      $cfg['pass'],
      [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . $cfg['charset']],
    ];

    return $cfg;
  }

  /**
   * Returns the host of the current connection.
   *
   * @return string|null
   */
  public function getHost(): ?string
  {
    return $this->host;
  }

  /**
   * @return string
   */
  public function getConnectionCode()
  {
    return $this->connection_code;
  }

  public function getCfg(): array
  {
    return $this->cfg;
  }

  /**
   * Disables foreign keys check.
   *
   * @return self
   */
  public function disableKeys(): self
  {
    $this->rawQuery('SET FOREIGN_KEY_CHECKS=0;');

    return $this;
  }


  /**
   * Enables foreign keys check.
   *
   * @return self
   */
  public function enableKeys(): self
  {
    $this->rawQuery('SET FOREIGN_KEY_CHECKS=1;');

    return $this;
  }


  /**
   * Creates a database
   *
   * @param string $database
   * @param string $enc
   * @param string $collation
   * @return bool
   */
  private function createMysqlDatabase(string $database, string $enc = 'utf8', string $collation = 'utf8_general_ci'): bool
  {
    if (Str::checkName($database, $enc, $collation)) {
      return (bool)$this->rawQuery("CREATE DATABASE IF NOT EXISTS `$database` DEFAULT CHARACTER SET $enc COLLATE $collation;");
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
    return $this->createMysqlDatabase($database);
  }


  /**
   * Drops the given database
   *
   * @param string $database
   * @return bool
   * @throws Exception
   */
  public function dropDatabase(string $database): bool
  {
    if ($this->check()) {
      if (!Str::checkName($database)) {
        throw new Exception(X::_("Wrong database name") . " $database");
      }

      try {
        $this->rawQuery("DROP DATABASE `$database`");
      } catch (Exception $e) {
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
   * @throws Exception
   */
  public function createUser(string $user, string $pass, string $db = null): bool
  {
    if (null === $db) {
      $db = $this->getCurrent();
    }

    if (($db = $this->escape($db))
      && Str::checkName($user, $db)
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
   * @throws Exception
   */
  public function deleteUser(string $user): bool
  {
    if (Str::checkName($user)) {
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
   * @throws Exception
   */
  public function getUsers(string $user = '', string $host = ''): ?array
  {
    if ($this->check()) {
      $cond = '';
      if (!empty($user) && Str::checkName($user)) {
        $cond .= " AND  user LIKE '$user' ";
      }

      if (!empty($host) && Str::checkName($host)) {
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
      $q = [];
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
   * Renames the given table to the new given name.
   *
   * @param string $table The current table's name
   * @param string $newName The new name.
   * @return bool  True if it succeeded
   */
  public function renameTable(string $table, string $newName): bool
  {
    if ($this->check() && Str::checkName($table) && Str::checkName($newName)) {
      $t1 = strpos($table, '.') ? $this->tableFullName($table, true) : $this->tableSimpleName($table, true);
      $t2 = strpos($newName, '.') ? $this->tableFullName($newName, true) : $this->tableSimpleName($newName, true);

      $res = $this->rawQuery(sprintf("RENAME TABLE %s TO %s", $t1, $t2));
      return (bool)$res;
    }

    return false;
  }

  /**
   * Changes the charset to the given database
   * @param string $database The database's name
   * @param string $charset The charset to set
   * @param string $collation The collation to set
   */
  public function setDatabaseCharset(string $database, string $charset, string $collation): bool
  {
    if ($this->check() && Str::checkName($database, $charset, $collation)) {
      return (bool)$this->rawQuery("ALTER DATABASE `$database` CHARACTER SET = $charset COLLATE = $collation;");
    }
    return false;
  }

  /**
   * Changes the charset to the given table
   * @param string $table The table's name
   * @param string $charset The charset to set
   * @param string $collation The collation to set
   */
  public function setTableCharset(string $table, string $charset, string $collation): bool
  {
    if ($this->check() && Str::checkName($table, $charset, $collation)) {
      return (bool)$this->rawQuery("ALTER TABLE `$table` CONVERT TO CHARACTER SET $charset COLLATE $collation;");
    }
    return false;
  }

  /**
   * Changes the charset to the given column
   * @param string $table The table's name
   * @param string $column The column's name
   * @param string $charset The charset to set
   * @param string $collation The collation to set
   */
  public function setColumnCharset(string $table, string $column, string $charset, string $collation): bool
  {
    if ($this->check()
      && Str::checkName($table, $column, $charset, $collation)
      && ($modelize = $this->modelize($table))
      && !empty($modelize['fields'][$column])
      && !empty($modelize['fields'][$column]['type'])
      && ($type = \strtoupper($modelize['fields'][$column]['type']))
    ) {
      if (!empty($modelize['fields'][$column]['maxlength'])) {
        $type .= '(' . $modelize['fields'][$column]['maxlength'] . ')';
      }
      return (bool)$this->rawQuery("ALTER TABLE `$table` MODIFY `$column` $type CHARSET $charset COLLATE $collation;");
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
    if ($tmp = $this->tableFullName($table)) {
      $bits = X::split($tmp, '.');
      return $this->getOne(
          "SELECT table_comment
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE table_schema = ?
        AND table_name = ?",
          $bits[0],
          $bits[1]
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
   * @throws Exception
   */
  public function dbSize(string $database = '', string $type = ''): int
  {
    $cur = null;
    if ($database && ($this->getCurrent() !== $database)) {
      $cur = $this->getCurrent();
      $this->change($database);
    }

    $q = $this->query('SHOW TABLE STATUS');
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
   * @throws Exception
   */
  public function tableSize(string $table, string $type = ''): int
  {
    $size = 0;
    if (Str::checkName($table)) {
      $row = $this->getRow('SHOW TABLE STATUS WHERE Name LIKE ?', $table);

      if (!$row) {
        throw new Exception(X::_('Table ') . $table . X::_(' Not found'));
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
   * @throws Exception
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
    return $this->getOne("SELECT replace(uuid(),'-','')");
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
    $sql = '';
    foreach ($columns as $n => $c) {
      $name = $c['name'] ?? $n;
      if (isset($c['type']) && Str::checkName($name)) {
        $st = $this->colSimpleName($name, true) . ' ' . $c['type'];
        if (!empty($c['maxlength'])) {
          $st .= '(' . $c['maxlength'] . ')';
        } elseif (!empty($c['values']) && \is_array($c['values'])) {
          $st .= '(';
          foreach ($c['values'] as $i => $v) {
            $st .= "'" . Str::escapeSquotes($v) . "'";
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

        if (array_key_exists('default', $c)) {
          $st .= ' DEFAULT ' . ($c['default'] === 'NULL' ? 'NULL' : "'" . Str::escapeSquotes($c['default']) . "'");
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

  /**
   * Actions to do once the PDO object has been created
   *
   * @return void
   */
  public function postCreation()
  {
    return;
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
   * @throws Exception
   */
  public function getDatabases(): ?array
  {
    if (!$this->check()) {
      return null;
    }

    $x = [];
    if ($r = $this->rawQuery('SHOW DATABASES')) {
      $x = array_map(
        function ($a) {
          return $a['Database'];
        }, array_filter(
          $this->fetchAllResults($r, PDO::FETCH_ASSOC), function ($a) {
          return ($a['Database'] === 'information_schema') || ($a['Database'] === 'mysql') ? false : 1;
        }
        )
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

    $t2 = [];
    if (($r = $this->rawQuery("SHOW TABLES FROM `$database`"))
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
      $t = explode('.', $full);
      [$db, $table] = $t;
      $sql = <<<MYSQL
        SELECT *
        FROM `information_schema`.`COLUMNS`
        WHERE `TABLE_NAME` LIKE ?
        AND `TABLE_SCHEMA` LIKE ?
        ORDER BY `ORDINAL_POSITION` ASC
MYSQL;
      if ($rows = $this->getRows($sql, $table, $db)) {
        $p = 1;
        foreach ($rows as $row) {
          $f = $row['COLUMN_NAME'];
          $has_length = (stripos($row['DATA_TYPE'], 'text') === false)
            && (stripos($row['DATA_TYPE'], 'blob') === false)
            && ($row['EXTRA'] !== 'VIRTUAL GENERATED');
          $r[$f] = [
            'position' => $p++,
            'type' => $row['DATA_TYPE'],
            'null' => $row['IS_NULLABLE'] === 'NO' ? 0 : 1,
            'key' => \in_array($row['COLUMN_KEY'], ['PRI', 'UNI', 'MUL']) ? $row['COLUMN_KEY'] : null,
            'extra' => $row['EXTRA'],
            'signed' => strpos($row['COLUMN_TYPE'], ' unsigned') === false ? 1 : 0,
            'virtual' => $row['EXTRA'] === 'VIRTUAL GENERATED',
            'generation' => $row['GENERATION_EXPRESSION'],
          ];
          if (($row['COLUMN_DEFAULT'] !== null) || ($row['IS_NULLABLE'] === 'YES')) {
            $r[$f]['default']           = \is_null($row['COLUMN_DEFAULT']) ? 'NULL' : $row['COLUMN_DEFAULT'];
            $r[$f]['defaultExpression'] = $row['EXTRA'] === 'DEFAULT_GENERATED';
          }

          if (($r[$f]['type'] === 'enum') || ($r[$f]['type'] === 'set')) {
            if (preg_match_all('/\((.*?)\)/', $row['COLUMN_TYPE'], $matches)
              && !empty($matches[1])
              && \is_string($matches[1][0])
              && ($matches[1][0][0] === "'")
            ) {
              $r[$f]['values'] = explode("','", substr($matches[1][0], 1, -1));
              $r[$f]['extra'] = $matches[1][0];
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
              $r[$f]['decimals'] = (int)$matches[2][1];
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
      $t = explode('.', $full);
      [$db, $table] = $t;
      $r = [];
      $indexes = $this->getRows('SHOW INDEX FROM ' . $this->tableFullName($full, 1));
      $keys = [];
      $cols = [];
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
          $keys[$index['Key_name']]['ref_db'] = $keys[$index['Key_name']]['ref_table'] = $keys[$index['Key_name']]['ref_column'] = null;
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
   * @param string $table The table for which to create the statement
   * @return string
   */
  public function getRawCreate(string $table): string
  {
    if (($table = $this->tableFullName($table, true))
      && ($r = $this->rawQuery("SHOW CREATE TABLE $table"))
    ) {
      return $r->fetch(PDO::FETCH_ASSOC)['Create Table'];
    }

    return '';
  }

  /**
   * @param string $table
   * @param array|null $model
   * @return string
   * @throws Exception
   */
  public function getCreateTable(string $table, array $model = null): string
  {
    if (!$model) {
      $model = $this->modelize($table);
    }

    $st = 'CREATE TABLE ' . $this->escape($table) . ' (' . PHP_EOL;
    $done = false;
    foreach ($model['fields'] as $name => $col) {
      if (!$done) {
        $done = true;
      } else {
        $st .= ',' . PHP_EOL;
      }

      $st .= $this->getColumnDefinitionStatement($name, $col);
    }

    $st .= PHP_EOL . ') ENGINE=InnoDB DEFAULT CHARSET=utf8';

    return $st;
  }

  /**
   * @param string $table
   * @param array|null $model
   * @return string
   * @throws Exception
   */
  public function getCreateKeys(string $table, array $model = null): string
  {
    $st = '';
    if (!$model) {
      $model = $this->modelize($table);
    }

    if ($model && !empty($model['keys'])) {
      $st .= 'ALTER TABLE ' . $this->escape($table) . PHP_EOL;
      $last = count($model['keys']) - 1;

      $i = 0;
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
   * @throws Exception
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
      $end = array_pop($lines);
      $st = X::join($lines, PHP_EOL);

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

        $st .= ' (' . implode(
            ',', array_map(
              function ($a) {
                return $this->escape($a);
              }, $key['columns']
            )
          ) . ')';
      }

      // For avoiding constraint names conflicts
      $keybase = strtolower(Str::genpwd(8, 4));
      $i = 1;
      foreach ($model['keys'] as $name => $key) {
        if (!empty($key['ref_table']) && !empty($key['ref_column'])) {
          $st .= ',' . PHP_EOL . '  ' .
            'CONSTRAINT ' . $this->escape($keybase . $i) . ' FOREIGN KEY (' . $this->escape($key['columns'][0]) . ') ' .
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
   * Return a string for alter table sql statement.
   *
   * ```php
   * $cfg = [
   *  'fields' => [
   *    'id' => [
   *      'type' => 'binary',
   *      'maxlength' => 32
   *    ],
   *    'name' => [
   *      'type' => 'varchar',
   *      'maxlength' => 255,
   *      'alter_type' => 'modify',
   *      'new_name' => 'username',
   *      'after' => 'id'
   *    ],
   *    'balance' => [
   *      'type' => 'decimal',
   *      'maxlength' => 10,
   *      'decimals' => 2,
   *      'null' => true,
   *      'default' => 0
   *      'alter_type' => 'modify',
   *      'after' => 'id'
   *    ],
   *    'role_id' => [
   *      'alter_type' => 'drop'
   *    ]
   *  ]
   * ];
   * X::dump($db->getAlterTable('users', $cfg);
   *
   * // (string) ALTER TABLE `users`
   * // ADD `id` binary(32) NOT NULL,
   * // CHANGE COLUMN `name` `username` varchar(255) NOT NULL AFTER `id`,
   * // MODIFY `balance` decimal(10,2) UNSIGNED DEFAULT 0 AFTER `id`,
   * // DROP COLUMN `role_id`
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
      $st   = 'ALTER TABLE ' . $this->escape($table) . PHP_EOL;
      $done = false;

      foreach ($cfg['fields'] as $name => $col) {
        if (!$done) {
          $done = true;
        } else {
          $st .= ',' . PHP_EOL;
        }

        $columnMrg = [];
        try {
          $columnMrg = array_merge($col, ['col_name' => $name, 'no_table_exp' => true]);
        } catch (Exception $e) {
          throw $e;
        }

        $st .= $this->getAlterColumn($table, $columnMrg);
      }
    }

    return $st ?? '';
  }


  /**
   * Return a string for alter column statement.
   *
   * ```php
   * $cfg = [
   *  'col_name' => 'id',
   *  'type' => 'binary',
   *  'maxlength' => 32
   * ];
   * X::dump($db->getAlterColumn('users', $cfg);
   *
   * // (string) ALTER TABLE `users` ADD `id` binary(32) NOT NULL
   *
   * ```
   *
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
      $st .= "CHANGE COLUMN ";
      $st .= $this->escape($cfg['col_name']) . ' ' . $this->escape($cfg['new_name']) . ' ';
      $st .= $this->getColumnDefinitionStatement($cfg['col_name'], $cfg, false);
    }
    elseif ($alter_type === 'DROP') {
      $st .= "DROP COLUMN " . $this->escape($cfg['col_name']);
    }
    else {
      $st .= $alter_type . ' ' . $this->getColumnDefinitionStatement($cfg['col_name'], $cfg);
    }

    if ($alter_type !== 'DROP') {
      if (!empty($cfg['after']) && is_string($cfg['after'])) {
        $st .= " AFTER " . $this->escape($cfg['after']);
      }
    }

    return $st;
  }

  /**
   * Returns a string for alter keys statement.
   *
   * ```php
   * $cfg = [
   *   'keys' => [
   *    'drop' => [
   *      'primary' => [
   *      'unique' => true,
   *      'columns' => ['email']
   *       ],
   *      'unique_key' => [
   *         'unique' => true,
   *        'columns' => ['id']
   *       ],
   *      'username_key' => [
   *          'columns' => ['username']
   *        ]
   *      ],
   *     'add' => [
   *       'primary' => [
   *        'unique' => true,
   *        'columns' => ['id']
   *        ],
   *        'unique_key' => [
   *          'unique' => true,
   *          'columns' => ['email']
   *        ],
   *        'username_key' => [
   *          'columns' => ['username']
   *        ]
   *      ]
   *    ],
   *    'fields' => [
   *      'drop' => [
   *        'email' => [
   *          'key' => 'PRI'
   *          ]
   *        ],
   *      'add' => [
   *        'id' => [
   *        'key' => 'PRI'
   *        ]
   *      ]
   *   ]
   * ];
   *
   * X::dump($db->getAlterKey('users', $cfg);
   *
   * // (string)
   * // ALTER TABLE `users`
   * // DROP PRIMARY KEY,
   * // DROP  KEY `unique_key`,
   * // DROP KEY `username_key`,
   * // ADD PRIMARY KEY (`id`),
   * // ADD UNIQUE KEY `unique_key` (`email`),
   * // ADD KEY `username_key` (`username`);
   * ```
   *
   * @param string $table
   * @param array $cfg
   * @return string
   * @throws Exception
   */
  public function getAlterKey(string $table, array $cfg): string
  {
    $st = 'ALTER TABLE ' . $this->escape($table) . PHP_EOL;

    if ($cfg['keys'] && !empty($cfg['keys'])) {
      $types = ['drop', 'add'];

      foreach ($types as $type) {
        if (!empty($cfg['keys'][$type]) && is_array($cfg['keys'][$type])) {
          foreach ($cfg['keys'][$type] as $name => $key) {
            $st .= ' ' . strtoupper($type) . ' ';

            if (!empty($key['unique'])
              && isset($cfg['fields'][$type][$key['columns'][0]])
              && ($cfg['fields'][$type][$key['columns'][0]]['key'] === 'PRI')
            ) {
              $st .= 'PRIMARY KEY';
            } elseif (!empty($key['unique'])) {
              $st  .= ($type !== 'drop' ? 'UNIQUE' : '') . ' KEY ';
              $st .= $this->escape($name);
            } else {
              $st .= 'KEY ' . $this->escape($name);
            }

            if ($type !== 'drop') {
              $st .= ' (' . implode(
                  ',', array_map(
                    function ($a) {
                      return $this->escape($a);
                    }, $key['columns']
                  )
                ) . ')';
            }

            $st .= ',' . PHP_EOL;
          }
        }
      }
    }

    return rtrim($st, ',' . PHP_EOL) . ';' . PHP_EOL;
  }


  public function getMoveColumn(string $table, string $column, string $after = null, array $cfg = null): ?string
  {
    if (!$cfg) {
      $cfg = $this->modelize($table, true);
    }

    if (!$cfg) {
      throw new Exception(X::_("If the table does not exist a configuration should be provided"));
    }

    if (!$cfg['fields'][$column]) {
      throw new Exception(X::_("The column is not part of the table's structure"));
    }

    $st  = 'ALTER TABLE ' . $this->escape($table) . PHP_EOL;
    $st .= 'MODIFY COLUMN ' . $this->escape($column) . ' ';
    $st .= $this->getColumnDefinitionStatement($column, $cfg['fields'][$column], false). ' ';
    $st .= $after ? $this->escape($after) : 'FIRST';
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
   * @throws Exception
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

        $name .= '_' . $c;
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
   * @param string $table
   * @param string $key
   * @return bool
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

  /**
   * Creates the given column for the given table.
   *
   * @param string $table
   * @param string $column
   * @param array $col
   * @return bool
   * @throws Exception
   */
  public function createColumn(string $table, string $column, array $col): bool
  {
    if (($table = $this->tableFullName($table, true)) && Str::checkName($column)) {
      $column_definition = $this->getColumnDefinitionStatement($column, $col);

      if (!empty($col['after']) && is_string($col['after'])) {
        $column_definition .= " AFTER " . $this->escape($col['after']);
      }

      return (bool)$this->rawQuery("ALTER TABLE $table ADD $column_definition");
    }

    return false;
  }

  /**
   * Returns a statement for column definition.
   *
   * @param string $name
   * @param array $col
   * @param bool $include_col_name
   * @return string
   * @throws Exception
   */
  protected function getColumnDefinitionStatement(string $name, array $col, bool $include_col_name = true): string
  {
    $st = '';

    if ($include_col_name) {
      $st .= '  ' . $this->escape($name) . ' ';
    }

    if (empty($col['type'])) {
      throw new Exception(X::_('Column type is not provided'));
    }

    if (!in_array($col['type'], self::$types)) {
      if (isset(self::$interoperability[$col['type']])) {
        $st .= self::$interoperability[$col['type']];
      }
      else {
        throw new Exception(X::_("Impossible to recognize the column type")." $col[type]");
      }
    }
    else {
      $st .= $col['type'];
    }

    if (($col['type'] === 'enum') || ($col['type'] === 'set')) {
      if (empty($col['extra'])) {
        throw new Exception(X::_("Extra field is required for")." {$col['type']}");
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
    }
    elseif (array_key_exists('default', $col)) {
      if (!empty($col['defaultExpression'])) {
        $st .= ' DEFAULT ';
        if ($col['default'] === null) {
          $st .= ' NULL';
        }
        else {
          $st .= (string)$col['default'];
        }
      }
      else {
        $def = (string)$col['default'];
        if (!empty($col['default'])) {
          $st .= " DEFAULT '" . Str::escapeQuotes(trim((string)$col['default'], "'")) . "'";
        }
      }
    }


    return $st;
  }

  public function getColMaxLength(string $column, string $table = null): ?int
  {
    [$tab, $col] = X::split($this->colFullName($column, $table), '.');
    if (!$tab) {
      throw new \Exception("error: no tab");
    }
    return $this->selectOne($tab, "max(length($col))");
  }

  public function __toString()
  {
    return 'mysql';
  }
}
