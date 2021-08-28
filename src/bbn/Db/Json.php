<?php
/**
 * @package db
 */
namespace bbn\Db;

use bbn;
/**
 * Database Class
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Database
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.2r89
 */
class Json //implements bbn\Db\Api
{
  private $db;
  
  public static $operators=[
    '!=',
    '=',
    '<>',
    '<',
    '<=',
    '>',
    '>=',
    'like',
    'clike',
    'slike',
    'not',
    'is',
    'is not',
    'in',
    'between',
    'not like'
  ];

  public static $numeric_types=[
    'integer',
    'int',
    'smallint',
    'tinyint',
    'mediumint',
    'bigint',
    'decimal',
    'numeric',
    'float',
    'double'
  ];

  public $qte = '`';
  
  /**
   * 
   */
  public function __construct($file)
  {
    $this->current = $file;
  }
	
}