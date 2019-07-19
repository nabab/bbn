<?php
/**
 * @package db
 */
namespace bbn\db;
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
interface engines
{
  /**
   * Constructor
   * @param bbn\db $db
   */
  //public function __construct(bbn\db $db = null);

  /**
   * @param array $cfg The user's options
   * @return array|null The final configuration
   */
  public function get_connection(array $cfg = []): ?array;
  /**
   * Actions to do once the PDO object has been created
   *
   * @return void
   */
  public function post_creation();
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
	 * @param string $table The table's name (escaped or not)
	 * @param bool $escaped If set to true the returned string will be escaped
	 * @return string | false
	 */
	public function table_full_name(string $table, bool $escaped = false): ?string;
	
	/**
	 * Returns a table's simple name i.e. table
	 * 
	 * @param string $table The table's name (escaped or not)
	 * @param bool $escaped If set to true the returned string will be escaped
	 * @return string | false
	 */
  public function table_simple_name(string $table, bool $escaped = false): ?string;
  
	/**
	 * Returns a column's full name i.e. table.column
	 * 
	 * @param string $col The column's name (escaped or not)
	 * @param null|string $table The table's name (escaped or not)
	 * @param bool $escaped If set to true the returned string will be escaped
	 * @return string | false
	 */
  public function col_full_name(string $col, $table = null, $escaped = false);

	/**
	 * Returns a column's simple name i.e. column
	 * 
	 * @param string $col The column's name (escaped or not)
	 * @param bool $escaped If set to true the returned string will be escaped
	 * @return string | false
	 */
  public function col_simple_name(string $col, bool $escaped = false);

  /**
   * @param string $table
   * @return bool
   */
  public function is_table_full_name(string $table): bool;

  /**
   * @param string $col
   * @return bool
   */
  public function is_col_full_name(string $col): bool;

	/**
	 * Fetches the database and returns an array of a single row num-indexed
	 *
	 * @return false|array
	 */
	public function disable_keys();

	/**
	 * Fetches the database and returns an array of several arrays of rows num-indexed
	 *
	 * @return false|array
	 */
	public function enable_keys();

	/**
	 * Fetches the database and returns an array of arrays, one per column, each having each column's values
	 *
	 * @return false|array
	 */
	public function get_databases(): ?array;

	/**
	 * Fetches the database and returns an object of a single row, alias of get_object
	 *
   * @return null|array
	 */
	public function get_tables(): ?array;

	/**
	 * Fetches the database and returns an object of a single row
	 *
   * @param string $table
	 * @return null|array
	 */
	public function get_columns(string $table): ?array;

	/**
	 * Fetches the database and returns an array of objects 
	 *
   * @param string $table
   * @return null|array
	 */
	public function get_keys(string $table): ?array;

  /**
   * Returns a string with the conditions for the ON, WHERE, or HAVING part of the query if there is, empty otherwise
   *
   * @param array $conditions
   * @param array $cfg
   * @param bool $is_having
   * @return string
   */
  public function get_conditions(array $conditions, array $cfg = [], bool $is_having = false): string;

  /**
   * Generates a string starting with SELECT ... FROM with corresponding parameters
   *
   * @param array $cfg The configuration array
   * @return string
   */
  public function get_select(array $cfg): string;

  /**
   * Fetches the database and returns an array of objects
   *
   * @param array $cfg The configuration array
   * @return false|array
   */
  public function get_insert(array $cfg): string;

  /**
   * Fetches the database and returns an array of objects
   *
   * @param array $cfg The configuration array
   * @return false|array
   */
  public function get_update(array $cfg): string;

  /**
   * Returns the SQL code for a DELETE statement.
   *
   * @param array $cfg The configuration array
   * @return string
   */
  public function get_delete(array $cfg): string;

  /**
   * Returns a string with the JOIN part of the query if there is, empty otherwise
   *
   * @param array $cfg
   * @return string
   */
  public function get_join(array $cfg): string;

  /**
   * Returns a string with the JOIN part of the query if there is, empty otherwise
   *
   * @param array $cfg
   * @return string
   */
  public function get_where(array $cfg): string;

  /**
   * Returns a string with the GROUP BY part of the query if there is, empty otherwise
   *
   * @param array $cfg
   * @return string
   */
  public function get_group_by(array $cfg): string;

  /**
   * Returns a string with the HAVING part of the query if there is, empty otherwise
   *
   * @param array $cfg
   * @return string
   */
  public function get_having(array $cfg): string;

  /**
   * Get a string starting with ORDER BY with corresponding parameters to $order
   *
   * @param array $cfg
   * @return string
   */
  public function get_order(array $cfg): string;

  /**
   * Get a string starting with LIMIT with corresponding parameters to $where
 	 *
   * @param array $cfg
   * @return string
   */
	public function get_limit(array $cfg): string;
	
 /**
	* Fetches the database and returns an array of objects 
	*
  * @param string $table The table for which to create the statement
	* @return string
	*/
	public function get_create(string $table): string;

	/**
   * Creates an index
   *
   * @param string $table
   * @param string|array $column
   * @param bool $unique
   * @param null $length
   * @return bool
	 */
	public function create_index(string $table, $column, bool $unique = false, $length = null): bool;
	
	/**
	 * Deletes an index
	 *
   * @param string $table
   * @param string $key
   * @return bool
	 */
	public function delete_index(string $table, string $key): bool;

  /**
   * Creates a database user
   *
   * @param string $user
   * @param string $pass
   * @param string $db
   * @return bool
   */
	public function create_user(string $user, string $pass, string $db = null): bool;

  /**
   * Deletes a database user
   *
   * @param string $user
   * @return bool
   */
	public function delete_user(string $user): bool;

  /**
   * Returns an array of queries to recreate the user(s)
   *
   * @param string $user
   * @param string $host
   * @return array
   */
  public function get_users(string $user = '', string $host = ''): ?array;

  /**
   * Gets the size of a database
   *
   * @param string $database
   * @param string $type
   * @return int Size in bytes
   */
  public function db_size(string $database = '', string $type = ''): int;

  /**
   * Gets the size of a table
   *
   * @param string $table
   * @param string $type
   * @return int Size in bytes
   */
  public function table_size(string $table, string $type = ''): int;

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
   * @return string
   */
  public function get_uid(): string;

}
