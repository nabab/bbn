<?php
/**
 * @package bbn\db
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
	* @return void()
	*/
	public function __construct($cfg);
	
 /**
	* Fetches the database and returns an array of several arrays of rows text-indexed
	* 
	* @params string
	* @return $this
	*/
	public function change($db);
	
 /**
	 * Returns the full name of a given table
	 *
	 * @params string 
	 * @params bool
	 * @return false|array
	 */
	public function get_full_name($table, $escaped=false);

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
	public function get_delete($table, array $where);
	
	/**
	 * Fetches the database and returns an array of objects 
	 * 
	 * @return false|array
	 */
	public function get_select($table, array $fields = array(), array $where = array(), $order, $limit, $start, $php = false);
	
	/**
	 * Fetches the database and returns an array of objects 
	 * 
	 * @return false|array
	 */
	public function get_insert($table, array $fields = array(), $ignore = false, $php = false);
	
	/**
	* Fetches the database and returns an array of objects 
	* 
	* @return false|array
	*/
	public function get_update($table, array $fields = array(), array $where = array(), $php = false);
	
	/**
	 * Fetches the database and returns an array of objects 
	 * 
	 * @return false|array
	 */
	public function create_db_index($table, $column);
	
	/**
	 * Fetches the database and returns an array of objects 
	 * 
	 * @return false|array
	 */
	public function delete_db_index($table, $column);
	
}
?>