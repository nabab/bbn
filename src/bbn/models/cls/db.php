<?php
/**
 * @package bbn
 */
namespace bbn\models\cls;
use bbn;
/**
 * Basic object Class
 *
 *
 * This class implements basic functions and vars
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Generic classes
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 * Todo: create a new delegation generic function for the double underscores functions
 */
class db extends basic
{
	protected
		/**
		 * @var db
		 */
		$db;

	public function __construct(bbn\db $db){
		$this->db = $db;
	}

}