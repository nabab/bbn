<?php
/**
 * @package bbn
 */
namespace bbn\Models\Cls;


/**
 * Nullall object Class
 *
 *
 * This class implements Basic functions and vars
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Generic classes
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.2r89
 * Todo: create a new delegation generic function for the double underscores functions
 */
class Nullall
{
  /**
   * @param string $name
   * @param array  $arguments
   * @return void
   */
  public function __call($name, $arguments)
  {
    return null;
  }
}
