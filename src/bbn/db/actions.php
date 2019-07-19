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
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.2r89
 */
interface actions
{

	/**
	 * Fetches the database and returns an array of a single row text-indexed
	 *
	 * @params 
	 * @return false|array
	 */
	public function get_row(): ?array;

	/**
	 * Fetches the database and returns an array of several arrays of rows text-indexed
	 *
	 * @return false|array
	 */
	public function get_rows(): ?array;

	/**
	 * Fetches the database and returns an array of a single row num-indexed
	 *
	 * @return false|array
	 */
	public function get_irow(): ?array;

	/**
	 * Fetches the database and returns an array of several arrays of rows num-indexed
	 *
	 * @return false|array
	 */
	public function get_irows(): ?array;

	/**
	 * Fetches the database and returns an array of arrays, one per column, each having each column's values
	 *
	 * @return false|array
	 */
	public function get_by_columns(): ?array;

	/**
	 * Fetches the database and returns an object of a single row, alias of get_object
	 *
	 * @return null|\stdClass
	 */
	public function get_obj(): ?\stdClass;

	/**
	 * Fetches the database and returns an object of a single row
	 *
   * @return null|\stdClass
	 */
	public function get_object(): ?\stdClass;

	/**
	 * Fetches the database and returns an array of objects 
	 *
	 * @return null|array
	 */
	public function get_objects(): ?array;
}
