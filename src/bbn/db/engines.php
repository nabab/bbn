<?php
/**
 * @package db
 */
namespace bbn\db;
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
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 */
interface engines
{	
 /**
	* Fetches the database and returns an array of several arrays of rows text-indexed
	* 
	* @params string
	* @return $this
	*/
	public function change($db);
	
	/**
	 * Returns a database item expression escaped like database, table, column, key names
	 * 
	 * @param string $item The item's name (escaped or not)
	 * @return string | false
	 */
	public function escape($item);
  
	/**
	 * Returns a table's full name i.e. database.table
	 * 
	 * @param string $table The table's name (escaped or not)
	 * @param bool $escaped If set to true the returned string will be escaped
	 * @return string | false
	 */
	public function table_full_name($table, $escaped=false);
	
	/**
	 * Returns a table's simple name i.e. table
	 * 
	 * @param string $table The table's name (escaped or not)
	 * @param bool $escaped If set to true the returned string will be escaped
	 * @return string | false
	 */
  public function table_simple_name($table, $escaped=false);
  
	/**
	 * Returns a column's full name i.e. table.column
	 * 
	 * @param string $col The column's name (escaped or not)
	 * @param string $table The table's name (escaped or not)
	 * @param bool $escaped If set to true the returned string will be escaped
	 * @return string | false
	 */
  public function col_full_name($col, $table='', $escaped=false);

	/**
	 * Returns a column's simple name i.e. column
	 * 
	 * @param string $col The column's name (escaped or not)
	 * @param bool $escaped If set to true the returned string will be escaped
	 * @return string | false
	 */
  public function col_simple_name($col, $escaped=false);

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
	public function get_databases();

	/**
	 * Fetches the database and returns an object of a single row, alias of get_object
	 *
	 * @return false|object
	 */
	public function get_tables();

	/**
	 * Fetches the database and returns an object of a single row
	 *
	 * @return false|object
	 */
	public function get_columns($table);

	/**
	 * Fetches the database and returns an array of objects 
	 *
	 * @return false|array
	 */
	public function get_keys($table);
	
 /**
	* Get a string starting with ORDER BY with corresponding parameters to $order
	*
	* @return false|array
	*/
	public function get_order($order, $table='');
	
 /**
	* Get a string starting with LIMIT with corresponding parameters to $where
	*
	* @return false|array
	*/
	public function get_limit($limit, $start = 0);
	
 /**
	* Fetches the database and returns an array of objects 
	*
	* @return false|array
	*/
	public function get_create($table);
	
	/**
	 * Fetches the database and returns an array of objects 
	 * 
	 * @return false|array
	 */
	public function get_delete($table, array $where, $ignore = false, $php = false);
	
	/**
	 * Fetches the database and returns an array of objects 
	 * 
	 * @return false|array
	 */
	public function get_select($table, array $fields = [], array $where = [], $order, $limit, $start, $php = false);
	
	/**
	 * Fetches the database and returns an array of objects 
	 * 
	 * @return false|array
	 */
	public function get_insert($table, array $fields = [], $ignore = false, $php = false);
	
	/**
	* Fetches the database and returns an array of objects 
	* 
	* @return false|array
	*/
	public function get_update($table, array $fields = [], array $where = [], $php = false);
	
	/**
	* Return an array of each values of the field $field in the table $table
	* 
	* @return false|array
	*/
	public function get_column_values($table, $field, array $where = [], array $order = [], $limit = false, $start = 0, $php = false);
	
	/**
	* Return an array of double values arrays: each value of the field $field in the table $table and the number of instances
	* 
	* @return false|array
	*/
	public function get_values_count($table, $field, array $where = [], $limit, $start, $php = false);
	
	/**
	 * Fetches the database and returns an array of objects 
	 * 
	 * @return false|array
	 */
	public function create_db_index($table, $column, $unique = false, $length = null);
	
	/**
	 * Fetches the database and returns an array of objects 
	 * 
	 * @return false|array
	 */
	public function delete_db_index($table, $column);
	
	/**
	 * Creates a database user
	 * 
	 * @return false|array
	 */
	public function create_db_user($user, $pass, $db);
	
	/**
	 * Deletes a database user
	 * 
	 * @return false|array
	 */
	public function delete_db_user($user);
  
	/**
	 * Returns an array of queries to recreate the user(s)
	 * 
	 * @return array
	 */
  public function get_users($user='', $host='');
}
?>