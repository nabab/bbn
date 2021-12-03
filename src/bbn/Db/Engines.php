<?php
/**
 * @package db
 */
namespace bbn\Db;

use bbn;

/**
 * DB Interface
 *
 *
 * These methods have to be implemented on both database and query.
 * Most methods usable on query should be also usable directly through database, which will create the query apply its method.
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Database
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.2r89
 */
interface Engines
{


  /*
   * @param array $cfg The user's options
   * @return array|null The final configuration
  public function getConnection(array $cfg = []): ?array;
   */


  /**
   * Actions to do once the PDO object has been created
   *
   * @return void
   */
  public function postCreation();


  /**
   * Fetches the database and returns an array of several arrays of rows text-indexed
   *
   * @param string $db
   * @return mixed
   */
  public function change(string $db);


  /**
   * Returns a database item expression escaped like database, table, column, key names
   *
   * @param string $item The item's name (escaped or not)
   * @return string | false
   */
  public function escape(string $item): string;


  /**
   * Returns a table's full name i.e. database.table
   *
   * @param string $table   The table's name (escaped or not)
   * @param bool   $escaped If set to true the returned string will be escaped
   * @return string | false
   */
  public function tableFullName(string $table, bool $escaped = false): ?string;


  /**
   * Returns a table's simple name i.e. table
   *
   * @param string $table   The table's name (escaped or not)
   * @param bool   $escaped If set to true the returned string will be escaped
   * @return string | false
   */
  public function tableSimpleName(string $table, bool $escaped = false): ?string;


  /**
   * Returns a column's full name i.e. table.column
   *
   * @param string      $col     The column's name (escaped or not)
   * @param null|string $table   The table's name (escaped or not)
   * @param bool        $escaped If set to true the returned string will be escaped
   * @return string | false
   */
  public function colFullName(string $col, ?string $table = null, bool $escaped = false);


  /**
   * Returns a column's simple name i.e. column
   *
   * @param string $col     The column's name (escaped or not)
   * @param bool   $escaped If set to true the returned string will be escaped
   * @return string | false
   */
  public function colSimpleName(string $col, bool $escaped = false);


  /**
   * @param string $table
   * @return bool
   */
  public function isTableFullName(string $table): bool;


  /**
   * @param string $col
   * @return bool
   */
  public function isColFullName(string $col): bool;


  /**
   * Disables foreign keys check.
   *
   */
  public function disableKeys();


  /**
   * Enables foreign keys check.
   *
   */
  public function enableKeys();


  /**
   * Return databases' names as an array.
   *
   * @return false|array
   */
  public function getDatabases(): ?array;


  /**
   * Return tables' names of a database as an array.
   *
   * @param string $database
   * @return null|array
   */
  public function getTables(string $database = ''): ?array;


  /**
   * Return columns' structure of a table as an array indexed with the fields names.
   *
   * @param string $table
   * @return null|array
   */
  public function getColumns(string $table): ?array;


  /**
   * Return an array that includes indexed arrays for every row resultant from the query.
   *
   * @return array|null
   */
  public function getRows(): ?array;


  /**
   * Return the first row resulting from the query as an array indexed with the fields' name.
   *
   * @return array|null
   */
  public function getRow(): ?array;

  /**
   * Return a row as a numeric indexed array.
   *
   * @return array|null
   */
  public function getIrow(): ?array;


  /**
   * Return an array of numeric indexed rows.
   *
   * @return array|null
   */
  public function getIrows(): ?array;


  /**
   * Return an array indexed on the searched field's in which there are all the values of the column.
   *
   * @return array|null
   */
  public function getByColumns(): ?array;

  /**
   * @return \stdClass|null
   */
  public function getObject(): ?\stdClass;


  /**
   * Return an array of stdClass objects.
   *
   * @return array|null
   */
  public function getObjects(): ?array;

  /**
   * Return the table's keys as an array indexed with the fields names.
   *
   * @param string $table
   * @return null|array
   */
  public function getKeys(string $table): ?array;


  /**
   * Returns a string with the conditions for any filter clause.
   *
   * @param array $conditions
   * @param array $cfg
   * @param bool $is_having
   * @param int $indent
   * @return string
   */
  public function getConditions(array $conditions, array $cfg = [], bool $is_having = false, int $indent = 0): string;


  /**
   * Creates a database
   *
   * @param string $database
   * @return bool
   */
  public function createDatabase(string $database): bool;


  /**
   * Drops a database
   *
   * @param string $database
   * @return bool
   */
  public function dropDatabase(string $database): bool;

  /**
   * Gets the size of a database
   *
   * @param string $database
   * @param string $type
   * @return int Size in bytes
   */
  public function dbSize(string $database = '', string $type = ''): int;


  /**
   * Gets the size of a table
   *
   * @param string $table
   * @param string $type
   * @return int Size in bytes
   */
  public function tableSize(string $table, string $type = ''): int;


  /**
   * Gets the status of a table
   *
   * @param string $table
   * @param string $database
   * @return mixed
   */
  public function status(string $table = '', string $database = '');


  /**
   * Returns a UUID
   *
   * @return string|null
   */
  public function getUid(): ?string;


  /**
   * Starts fancy stuff.
   *
   * @return $this
   */
  public function startFancyStuff(): self;

  /**
   * Stops fancy stuff.
   *
   * @return $this
   */
  public function stopFancyStuff(): self;

  /**
   * @param array $args
   * @param bool $force
   * @return array|null
   */
  public function processCfg(array $args, bool $force = false): ?array;

  /**
   * @param array $cfg
   * @return array|null
   */
  public function reprocessCfg(array $cfg): ?array;

  /**
   * Changes the value of last inserted id.
   *
   * @param string $id
   * @return $this
   */
  public function setLastInsertId($id = ''): self;

  /**
   * Return the last inserted ID.
   *
   * @return mixed
   */
  public function lastId();

  /**
   * Return the last query for this connection.
   *
   * @return string|null
   */
  public function last(): ?string;

  /**
   * Return the table's structure as an indexed array.
   *
   * @param null $table
   * @param bool $force
   * @return array|null
   */
  public function modelize($table = null, bool $force = false): ?array;

  /**
   * @param array $cfg
   * @return array
   */
  public function getQueryValues(array $cfg): array;

  /**
   * @param array $where
   * @param bool  $full
   * @return array|bool
   */
  public function treatConditions(array $where, bool $full = true);

  /**
   * Enable the triggers' functions
   *
   * @return self
   */
  public function enableTrigger(): self;

  /**
   * Disable the triggers' functions
   *
   * @return $this
   */
  public function disableTrigger(): self;

  /**
   * @return bool
   */
  public function isTriggerEnabled(): bool;

  /**
   * @return bool
   */
  public function isTriggerDisabled(): bool;

  /**
   * @param callable $function
   * @param array|string|null $kind
   * @param array|string|null $moment
   * @param null|string|array $tables
   * @return self
   */
  public function setTrigger(callable $function, $kind = null, $moment = null, $tables = '*' ): self;

  /**
   * @return array
   */
  public function getTriggers(): array;

  /**
   * @param $tables
   * @return array
   */
  public function getFieldsList($tables): array;

  /**
   * @param string $col
   * @param string $table
   * @param string|null $db
   * @return array
   */
  public function getForeignKeys(string $col, string $table, string $db = null): array;

  /**
   * find_relations
   *
   * @param $column
   * @param string $db
   * @return array|bool
   */
  public function findRelations($column, string $db = ''): ?array;

  /**
   * Return primary keys of a table as a numeric array.
   *
   * @param string $table The table's name
   * @return array
   */
  public function getPrimary(string $table): array;

  /**
   * Deletes all the queries recorded and returns their (ex) number.
   *
   * @return int
   */
  public function flush(): int;

  /**
   * @return int
   */
  public function countQueries(): int;

  /**
   * @param $statement
   * @return mixed
   */
  public function query($statement);

  /**
   * Executes the given query with given vars, and extracts the first cell's result.
   *
   * @return mixed
   */
  public function getOne();

  /**
   * Return an array indexed on the first field of the request.
   * The value will be an array if the request has more than two fields.
   *
   * @return array|null
   */
  public function getKeyVal(): ?array;

  /**
   * Return an array with the values of single field resulting from the query.
   *
   * @param string query
   * @param mixed values
   * @return array
   */
  public function getColArray(): array;

  /**
   * Return a count of identical values in a field as array, Reporting a structure type 'num' - 'val'.
   *
   * @param $table
   * @param string|null $field
   * @param array $where
   * @param array $order
   * @return array|null
   */
  public function countFieldValues($table, string $field = null,  array $where = [], array $order = []): ?array;

  /**
   * Return a numeric indexed array with the values of the unique column ($field) from the selected $table
   *
   * ```php
   * X::dump($db->getColumnValues('table_users','surname',['id','>',1]));
   * /*
   * array [
   *    "Smith",
   *    "Jones",
   *    "Williams",
   *    "Taylor"
   * ]
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param string|null $field The field's name
   * @param array $where The "where" condition
   * @param array $order The "order" condition
   * @param int $limit
   * @param int $start
   * @return array
   */
  public function getColumnValues($table, string $field = null,  array $where = [], array $order = [], int $limit = 0, int $start = 0): ?array;

  /**
   * Return an indexed array with the first result of the query or false if there are no results.
   *
   * @param string $query
   * @return array|false
   */
  public function fetch(string $query);

  /**
   * Return an array of indexed array with all results of the query or false if there are no results.
   *
   * @param string $query
   * @return array|false
   */
  public function fetchAll(string $query);

  /**
   * @param $query
   * @param int $num
   * @return mixed
   */
  public function fetchColumn($query, int $num = 0);

  /**
   * @param $query
   * @return bool|\stdClass
   */
  public function fetchObject($query);


  /**
   * @return array
   */
  public function getCfg(): array;

  /**
   * Gets the created hash.
   *
   * @return string
   */
  public function getHash(): string;

  /**
   * Checks if the database is ready to process a query.
   * @return bool
   */
  public function check(): bool;

  /**
   * Sets the error mode.
   *
   * @param string $mode
   */
  public function setErrorMode(string $mode);

  /**
   * @return string
   */
  public function getErrorMode(): string;

  /**
   * Returns the last error.
   *
   * @return string|null
   */
  public function getLastError(): ?string;

  /**
   * Returns the current database selected by the current connection.
   *
   * @return string|null
   */
  public function getCurrent(): ?string;

  /**
   * Returns the host of the current connection.
   *
   * @return string|null
   */
  public function getHost(): ?string;

  /**
   * @return string
   */
  public function getConnectionCode();

  /**
   * Return the last config for this connection.
   *
   * @return array|null
   */
  public function getLastCfg(): ?array;

  /**
   * @param array $cfg The user's options
   * @return array|null The final configuration
   */
  public function getConnection(array $cfg = []): ?array;
}
