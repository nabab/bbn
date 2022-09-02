<?php
/**
 * @package bbn
 * @todo    create a new delegation generic function for the double underscores functions
 */

namespace bbn\Models\Cls;

use bbn\Db as dbClass;

/**
 * Basic object Class
 *
 * This class implements Basic functions and vars
 *
 * @category  GenericClasses
 * @package   BBN_Library
 * @author    Thomas Nabet <thomas.nabet@gmail.com>
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version   Release: 0.2r89
 * @link      https://bbn.io/bbn-php/doc/class/appui/api
 * @since     Apr 4, 2011, 23:23:55 +0000
 */
abstract class Db extends Basic
{
  /**
   * @var dbClass
   */
  protected $db;


  /**
   * Constructor.
   *
   * @param dbClass $db A database connection
   */
  public function __construct(dbClass $db)
  {
    $this->db = $db;
  }
}
