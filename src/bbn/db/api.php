<?php
/**
 * @package bbn\db
 */
namespace bbn\db;
/**
 * DB API
 *
 *
 * These methods have to be implemented on the database and another class .
 * Most methods usable on query should be also usable directly through database, which will create the query apply its method.
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Database
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 */
interface api
{

	/**
	 * Fetches a given table and returns an array of a single row text-indexed
	 *
	 * @params 
	 * @return false|array
	 */
	public function select($table, $fields, $where, $order, $limit, $start);

	/**
	 * Inserts/Updates rows in the a given table
	 *
	 * @return int
	 */
	public function insert($table, array $values, $ignore);

	/**
	 * Inserts/Updates rows in the a given table
	 *
	 * @return int
	 */
	public function insert_update($table, array $values);

	/**
	 * Updates rows in the a given table
	 *
	 * @return int
	 */
	public function update($table, array $values, array $where);

	/**
	 * Deletes rows in the a given table
	 *
	 * @return int
	 */
	public function delete($table, array $where);
	
	/**
	 * Inserts ignore rows in the a given table
		*
	 * @return int
	 */
	public function insert_ignore($table, array $values);
	/**
	 * Fetches a given table and returns an array of a single row text-indexed
		*
	 * @params 
	 * @return false|array
	 */
	public function get_select($table, array $fields, array $where, $order, $limit, $start, $php);
	
	/**
	 * Inserts/Updates rows in the a given table
		*
	 * @return int
	 */
	public function get_insert($table, array $values, $ignore);
	
	
	/**
	 * Updates rows in the a given table
		*
	 * @return int
	 */
	public function get_update($table, array $fields, array $where, $php);
	
	/**
	 * Deletes rows in the a given table
		*
	 * @return int
	 */
	public function get_delete($table, array $where);
		
}
?>