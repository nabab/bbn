<?php
/**
 * @package db
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
	public function select($table, $fields, $where, $order, $start);

	/**
	 * Fetches a given table and returns an array of a single row text-indexed
	 *
	 * @params
	 * @return false|array
	 */
	public function select_all($table, $fields, $where, $order, $start, $limit);

	/**
	 * Fetches a given table and returns an array of a single row text-indexed
	 *
	 * @params
	 * @return false|array
	 */
	public function rselect($table, $fields, $where, $order, $start);

	/**
	 * Fetches a given table and returns an array of a single row text-indexed
	 *
	 * @params
	 * @return false|array
	 */
	public function rselect_all($table, $fields, $where, $order, $start, $limit);

	/**
	 * Fetches a given table and returns an array of a single row text-indexed
	 *
	 * @param string $table The table name.
	 * @param string $field The fields name.
	 * @param array $where  The "where" condition.
	 * @param string|array $order The "order" condition, default: false.
	 * @param int $start The "start" condition, default: 0.
	 * @return false|array
	 */
	public function select_one($table, $field, $where, $order, $start);

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
}
?>