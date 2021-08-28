<?php
/**
 * @package db
 */
namespace bbn\Db;

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
interface Pdo
{

  protected $pdo;


  /**
   * Fetches the database and returns an array of a single row text-indexed
   *
   * @params
   * @return null|\PDO
   */
  public function getPdo(): ?\PDO;


  /**
   * Fetches the database and returns an array of several arrays of rows text-indexed
   *
   * @return false|array
   */
  public function getRows(): ?array;


  /**
   * Fetches the database and returns an array of a single row num-indexed
   *
   * @return false|array
   */
  public function getIrow(): ?array;


  /**
   * Fetches the database and returns an array of several arrays of rows num-indexed
   *
   * @return false|array
   */
  public function getIrows(): ?array;


  /**
   * Fetches the database and returns an array of arrays, one per column, each having each column's values
   *
   * @return false|array
   */
  public function getByColumns(): ?array;


  /**
   * Fetches the database and returns an object of a single row, alias of get_object
   *
   * @return null|\stdClass
   */
  public function getObj(): ?\stdClass;


  /**
   * Fetches the database and returns an object of a single row
   *
   * @return null|\stdClass
   */
  public function getObject(): ?\stdClass;


  /**
   * Fetches the database and returns an array of objects
   *
   * @return null|array
   */
  public function getObjects(): ?array;


}
