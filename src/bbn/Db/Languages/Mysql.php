<?php
/**
 * @package db
 */
namespace bbn\Db\Languages;

use Exception;
use PDO;
use PDO\Mysql as PDOMysql;
use PDOException;
use bbn\Str;
use bbn\X;
use function count;
use function defined;
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

  /** @var string The quote character */
  public $qte = '`';

  protected static $defaultCharset = 'utf8mb4';

  protected static $defaultCollation = 'utf8mb4_general_ci';

  protected static $defaultEngine = 'InnoDB';

  /** @var array Allowed operators */
  public static $operators = ['!=', '=', '<>', '<', '<=', '>', '>=', 'like', 'clike', 'slike', 'not', 'is', 'is not', 'in', 'between', 'not like'];

  /** @var array Numeric column types */
  public static $numeric_types = ['integer', 'int', 'smallint', 'tinyint', 'mediumint', 'bigint', 'decimal', 'numeric', 'float', 'double'];

  /** @var array Time and date column types */
  public static $date_types = ['date', 'time', 'datetime'];

  public static $binary_types = ['binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob'];

  public static $text_types = ['tinytext', 'text', 'mediumtext', 'longtext', 'varchar', 'char'];

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

  /* public static $interoperability = [
    'integer' => 'int',
    'real' => 'decimal',
    'text' => 'text',
    'blob' => 'blob'
  ]; */

  public static $interoperability = [
    'tinyint'            => ['sqlite' => 'integer', 'pgsql' => 'smallint'],
    'smallint'           => ['sqlite' => 'integer', 'pgsql' => 'smallint'],
    'mediumint'          => ['sqlite' => 'integer', 'pgsql' => 'integer'],
    'int'                => ['sqlite' => 'integer', 'pgsql' => 'integer'],
    'bigint'             => ['sqlite' => 'integer', 'pgsql' => 'bigint'],
    'decimal'            => ['sqlite' => 'real',    'pgsql' => 'decimal'],
    'float'              => ['sqlite' => 'real',    'pgsql' => 'real'],
    'double'             => ['sqlite' => 'real',    'pgsql' => 'double precision'],
    'bit'                => ['sqlite' => 'numeric', 'pgsql' => 'bit'],
    'char'               => ['sqlite' => 'text',    'pgsql' => 'char'],
    'varchar'            => ['sqlite' => 'text',    'pgsql' => 'varchar'],
    'binary'             => ['sqlite' => 'blob',    'pgsql' => 'bytea'],
    'varbinary'          => ['sqlite' => 'blob',    'pgsql' => 'bytea'],
    'tinyblob'           => ['sqlite' => 'blob',    'pgsql' => 'bytea'],
    'blob'               => ['sqlite' => 'blob',    'pgsql' => 'bytea'],
    'mediumblob'         => ['sqlite' => 'blob',    'pgsql' => 'bytea'],
    'longblob'           => ['sqlite' => 'blob',    'pgsql' => 'bytea'],
    'tinytext'           => ['sqlite' => 'text',    'pgsql' => 'text'],
    'text'               => ['sqlite' => 'text',    'pgsql' => 'text'],
    'mediumtext'         => ['sqlite' => 'text',    'pgsql' => 'text'],
    'longtext'           => ['sqlite' => 'text',    'pgsql' => 'text'],
    'enum'               => ['sqlite' => 'text',    'pgsql' => 'text'],
    'set'                => ['sqlite' => 'text',    'pgsql' => 'text'],
    'date'               => ['sqlite' => 'text',    'pgsql' => 'date'],
    'time'               => ['sqlite' => 'text',    'pgsql' => 'time'],
    'datetime'           => ['sqlite' => 'text',    'pgsql' => 'timestamp'],
    'timestamp'          => ['sqlite' => 'text',    'pgsql' => 'timestamp'],
    'year'               => ['sqlite' => 'integer', 'pgsql' => 'integer'],
    'geometry'           => ['sqlite' => 'blob',    'pgsql' => 'bytea'],
    'point'              => ['sqlite' => 'blob',    'pgsql' => 'point'],
    'linestring'         => ['sqlite' => 'blob',    'pgsql' => 'line'],
    'polygon'            => ['sqlite' => 'blob',    'pgsql' => 'polygon'],
    'geometrycollection' => ['sqlite' => 'blob',    'pgsql' => 'bytea'],
    'multilinestring'    => ['sqlite' => 'blob',    'pgsql' => 'line'],
    'multipoint'         => ['sqlite' => 'blob',    'pgsql' => 'point'],
    'multipolygon'       => ['sqlite' => 'blob',    'pgsql' => 'polygon'],
    'json'               => ['sqlite' => 'text',    'pgsql' => 'json'],
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
   *
   * @param array $cfg
   * @throws Exception
   */
  public function __construct(array $cfg)
  {
    if (!\extension_loaded('pdo_mysql')) {
      throw new Exception(X::_("The MySQL driver for PDO is not installed..."));
    }

    parent::__construct($cfg);
  }

  
  /**
   * @param array $cfg The user's options
   * @return array|null The final configuration
   */
  public function getConnection(array $cfg = []): ?array
  {
    $numParams = count(array_keys($cfg));
    if (($numParams > 1) && empty($cfg['host'])) {
      throw new Exception(X::_("No DB host defined"));
    }

    if ($numParams < 2) {
      if (!defined('BBN_DB_HOST')) {
        throw new Exception(X::_("No DB host defined"));
      }

      $cfg = [
        'host' => constant('BBN_DB_HOST'),
        'user' => defined('BBN_DB_USER') ? constant('BBN_DB_USER') : '',
        'pass' => defined('BBN_DB_PASS') ? constant('BBN_DB_PASS') : '',
        'db' => defined('BBN_DATABASE') ? constant('BBN_DATABASE') : '',
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
      [PDOMysql::ATTR_INIT_COMMAND => 'SET NAMES ' . $cfg['charset']],
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
   * Returns the SQL statement to get the list of charsets.
   *
   * @return string
   */
  public function getCharsets(): string
  {
    return "SELECT DISTINCT " . $this->escape("CHARACTER_SET_NAME"). " AS " . $this->escape("charset") . PHP_EOL .
      "FROM " . $this->escape("INFORMATION_SCHEMA.character_sets") . ";";
  }


  /**
   * Returns the SQL statement to get the list of collations.
   *
   * @return string
   */
  public function getCollations(): string
  {
    return "SELECT DISTINCT " . $this->escape("COLLATION_NAME"). " AS " . $this->escape("collation") . PHP_EOL .
      "FROM " . $this->escape("INFORMATION_SCHEMA.collations") . ";";
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
    $collation = $collation ?: self::$defaultCollation;
    if ($st = parent::getCreateDatabase($database, $enc, $collation)) {
      return Str::sub($st, 0, -1)." DEFAULT CHARACTER SET '$enc' COLLATE '$collation';";
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
    if (Str::checkName($database)) {
      return <<<SQL
        SELECT default_character_set_name AS charset
        FROM information_schema.schemata
        WHERE schema_name = "$database";
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
        SELECT default_collation_name AS collation
        FROM information_schema.schemata
        WHERE schema_name = "$database";
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
   * @throws Exception
   */
  public function createUser(string $user, string $pass, string|null $db = null): bool
  {
    if (null === $db) {
      $db = $this->getCurrent();
    }

    if (($db = $this->escape($db))
      && Str::checkName($user, $db)
      && (Str::pos($pass, "'") === false)
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
   * Returns the SQL statement to rename a table.
   * This method generates an ALTER TABLE statement to rename the specified table.
   * @param string $table The current name of the table.
   * @param string $newName The new name for the table.
   * @return string The SQL statement to rename the table, or an empty string if the names are invalid.
   */
  public function getRenameTable(string $table, string $newName): string
  {
    if (Str::checkName($table)
      && Str::checkName($newName)
    ) {
      $t1 = Str::pos($table, '.') ? $this->tableFullName($table, true) : $this->tableSimpleName($table, true);
      $t2 = Str::pos($newName, '.') ? $this->tableFullName($newName, true) : $this->tableSimpleName($newName, true);
      return "RENAME TABLE $t1 TO $t2;";
    }

    return '';
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

    /**
     * @var \bbn\Db\Query $q
     */
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

    if (!empty($cur)) {
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
    $table = $this->tableSimpleName($table);
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
   * Returns true if the given table exists
   *
   * @param string $table
   * @param string $database. or currently selected if none
   * @return boolean
   */
  public function tableExists(string $table, string $database = ''): bool
  {
    $q = "SHOW tables ";
    if (!empty($database)) {
      $q .= "FROM " . $this->escape($database) . " ";
    }

    $q .= "LIKE \"$table\"";
    return (bool)$this->getRow($q);
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
            'signed' => Str::pos($row['COLUMN_TYPE'], ' unsigned') === false ? 1 : 0,
            'virtual' => $row['EXTRA'] === 'VIRTUAL GENERATED',
            'generation' => $row['GENERATION_EXPRESSION'],
            'charset' => $row['CHARACTER_SET_NAME'] ?? null,
            'collation' => $row['COLLATION_NAME'] ?? null,
          ];
          if (($row['COLUMN_DEFAULT'] !== null) || ($row['IS_NULLABLE'] === 'YES')) {
            $r[$f]['default']           = \is_null($row['COLUMN_DEFAULT']) ? 'NULL' : $row['COLUMN_DEFAULT'];
            $r[$f]['defaultExpression'] = ($row['EXTRA'] === 'DEFAULT_GENERATED')
              || (!empty($row['COLUMN_DEFAULT'])
                && ((strtolower($row['COLUMN_DEFAULT']) === 'current_timestamp')
                  || (strtolower($row['COLUMN_DEFAULT']) === 'current_timestamp()')));
          }

          if (($r[$f]['type'] === 'enum') || ($r[$f]['type'] === 'set')) {
            if (preg_match_all('/\((.*?)\)/', $row['COLUMN_TYPE'], $matches)
              && !empty($matches[1])
              && \is_string($matches[1][0])
              && ($matches[1][0][0] === "'")
            ) {
              $r[$f]['values'] = explode("','", Str::sub($matches[1][0], 1, -1));
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
        if ( Str::pos($row['Type'],'text') !== false ){
        $r[$f]['type'] = 'text';
        }
        else if ( Str::pos($row['Type'],'blob') !== false ){
        $r[$f]['type'] = 'blob';
        }
        else if ( Str::pos($row['Type'],'int(') !== false ){
        $r[$f]['type'] = 'int';
        }
        else if ( Str::pos($row['Type'],'char(') !== false ){
        $r[$f]['type'] = 'varchar';
        }
        if ( preg_match_all('/\((.*?)\)/', $row['Type'], $matches) ){
        $r[$f]['maxlength'] = (int)$matches[1][0];
        }
        if ( !isset($r[$f]['type']) ){
        $r[$f]['type'] = Str::pos($row['Type'], '(') ? Str::sub($row['Type'],0,Str::pos($row['Type'], '(')) : $row['Type'];
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
      foreach ($indexes as $index) {
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
   * @param array|null $cfg
   * @return string
   * @throws Exception
   */
  public function getCreateTable(string $table, ?array $cfg = null): string
  {
    if (!$cfg) {
      $cfg = $this->modelize($table);
    }

    if ($st = parent::getCreateTable($table, $cfg)) {
      if (empty($charset)) {
        if (!empty($cfg['charset'])) {
          $charset = $cfg['charset'];
          if (!empty($cfg['collation'])) {
            $collate = $cfg['collation'];
          }
        }
        else {
          $charset = self::$defaultCharset;
          $collate = self::$defaultCollation;
        }
      }

      $st = Str::sub($st, 0, -1)." ENGINE=".
        (!empty($cfg['engine']) ? Str::encodeFilename($cfg['engine']) : static::$defaultEngine).
        " DEFAULT CHARSET=".Str::encodeFilename($charset).
        ($collate ? " COLLATE=" . Str::encodeFilename($collate) : '').";";
      return $st;
    }

    return '';
  }


  /**
   * Returns the SQL statement to analyze a table.
   *
   * @param string $table The name of the table to analyze.
   * @return string The SQL statement to analyze the table, or an empty string if the table name is invalid.
   */
  public function getAnalyzeTable(string $table): string
  {
    if (Str::checkName($table)) {
      return "ANALYZE TABLE " . $this->tableSimpleName($table, true) . ";";
    }

    return '';
  }


  /**
   * Returns the SQL statement to get the charset of a table.
   *
   * @param string $table
   * @return string
   */
  public function getCharsetTable(string $table): string
  {
    [$db, $table] = X::split($this->tableFullName($table), '.');
    if (Str::checkName($db) && Str::checkName($table)) {
      return <<<SQL
        SELECT CCSA.`character_set_name` AS charset
        FROM information_schema.`TABLES` T,
            information_schema.`COLLATION_CHARACTER_SET_APPLICABILITY` CCSA
        WHERE CCSA.`collation_name` = T.`table_collation`
          AND T.`TABLE_SCHEMA` = "$db"
          AND T.`TABLE_NAME` = "$table";
      SQL;
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
    [$db, $table] = X::split($this->tableFullName($table), '.');
    if (Str::checkName($db) && Str::checkName($table)) {
      return <<<SQL
        SELECT `TABLE_COLLATION` as collation
        FROM `information_schema`.`TABLES`
        WHERE `TABLE_SCHEMA` = "$db"
          AND `TABLE_NAME` = "$table";
      SQL;
    }

    return '';
  }


  /**
   * Returns the SQL statement to create a column.
   * @param string $table The name of the table.
   * @param string $column The name of the column to create.
   * @param array $columnCfg The configuration for the column.
   * @return string The SQL statement to create the column, or an empty string if the parameters are invalid.
   */
  public function getCreateColumn(string $table, string $column, array $columnCfg): string
  {
    if ($st = parent::getCreateColumn($table, $column, $columnCfg)) {
      if (!empty($columnCfg['after'])
        && is_string($columnCfg['after'])
      ) {
        $st = Str::sub($st, 0, -1)." AFTER " . $this->escape($columnCfg['after']);
      }

      return $st;
    }

    return '';
  }


  /**
   * Returns the SQL statement to create the constraints.
   * @param string $table
   * @param array|null $cfg
   * @return string
   */
  public function getCreateConstraints(string $table, ?array $cfg = null, bool $anonymize = false): string
  {
    $st = '';
    if (!$cfg) {
      $cfg = $this->modelize($table);
    }

    if ($cfg && !empty($cfg['keys'])) {
      $keys = [];
      foreach ($cfg['keys'] as $a) {
        if (!empty($a['columns'])
          && !empty($a['constraint'])
          && !empty($a['ref_table'])
          && !empty($a['ref_column'])
          && is_null(X::search($keys, ['constraint' => $a['constraint']]))
        ) {
          $keys[] = $a;
        }
      }

      if ($last = count($keys)) {
        $st .= 'ALTER TABLE ' . $this->escape($table) . PHP_EOL;
        $i   = 0;
        foreach ($keys as $k) {
          $i++;
          $cols = implode(', ', array_map(fn($col) => $this->escape($col), $k['columns']));
          $refCols = is_array($k['ref_column']) ?
            implode(', ', array_map(fn($col) => $this->escape($col), $k['ref_column'])) :
            $this->escape($k['ref_column']);
          $st .= '  ADD CONSTRAINT ' . (empty($anonymize) ? ($this->escape($k['constraint']) . ' ') : '') .
            'FOREIGN KEY (' . $cols . ') ' .
            'REFERENCES ' . $this->escape($k['ref_table']) . '(' . $refCols . ') ' .
            (!empty($k['delete']) ? ' ON DELETE ' . $k['delete'] : '') .
            (!empty($k['update']) ? ' ON UPDATE ' . $k['update'] : '') .
            ($i === $last ? ';' : ',' . PHP_EOL);
        }
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
  public function getCreateKeys(string $table, ?array $cfg = null, bool $anonymize = false): string
  {
    $st = '';
    if (!$cfg) {
      $cfg = $this->modelize($table);
    }

    if ($cfg && !empty($cfg['keys'])) {
      $st .= 'ALTER TABLE ' . $this->escape($table) . PHP_EOL;
      $last = count($cfg['keys']) - 1;
      $i = 0;
      foreach ($cfg['keys'] as $name => $key) {
        $st .= '  ADD ';
        if (!empty($key['unique'])
          && isset($cfg['fields'][$key['columns'][0]])
          && isset($cfg['fields'][$key['columns'][0]]['key'])
          && ($cfg['fields'][$key['columns'][0]]['key'] === 'PRI')
        ) {
          $st .= 'PRIMARY KEY';
        } elseif (!empty($key['unique'])) {
          $st .= 'UNIQUE KEY ' . $this->escape($name);
        } else {
          $st .= 'KEY ' . $this->escape($name);
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


  public function getMoveColumn(string $table, string $column, string|null $after = null, array|null $cfg = null): ?string
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

    if (empty($cfg['type'])) {
      throw new Exception(X::_('Column type is not provided'));
    }

    if (!in_array($cfg['type'], self::$types)) {
      /* if (isset(self::$interoperability[$cfg['type']])) {
        $st .= self::$interoperability[$cfg['type']];
      }
      else { */
        throw new Exception(X::_("Impossible to recognize the column type")." $cfg[type]");
      //}
    }
    else {
      $st .= $cfg['type'];
    }

    if (($cfg['type'] === 'enum') || ($cfg['type'] === 'set')) {
      if (empty($cfg['extra'])) {
        throw new Exception(X::_("Extra field is required for")." {$cfg['type']}");
      }

      $st .= ' (' . $cfg['extra'] . ')';
    }
    elseif (!empty($cfg['maxlength'])) {
      $st .= '(' . $cfg['maxlength'];
      if (!empty($cfg['decimals'])) {
        $st .= ',' . $cfg['decimals'];
      }

      $st .= ')';
    }

    if (in_array($cfg['type'], self::$numeric_types)
      && empty($cfg['signed'])
    ) {
      $st .= ' UNSIGNED';
    }
    elseif (!empty($cfg['charset'])) {
      $st .= ' CHARACTER SET ' . Str::encodeFilename($cfg['charset']);
      if (!empty($cfg['collation'])) {
        $st .= ' COLLATE ' . Str::encodeFilename($cfg['collation']);
      }
    }

    if (empty($cfg['null']) && empty($cfg['virtual'])) {
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

    if (!empty($cfg['position'])) {
      if (Str::pos($cfg['position'], 'after:') === 0) {
        $after = trim(Str::sub($cfg['position'], 6));
        if (Str::checkName($after)) {
          $st .= ' AFTER ' . $this->escape($after);
        }
      } elseif (strtolower($cfg['position']) === 'first') {
        $st .= ' FIRST';
      }
    }

    return $st;
  }

  public function getColMaxLength(string $column, string|null $table = null): ?int
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