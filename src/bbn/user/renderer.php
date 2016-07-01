<?php
/**
 * @package bbn\user
 */
namespace bbn\user;
/**
 * A class for managing users
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Jun 29, 2016, 07:11:55 +0000
 * @category  Authentication
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.1b
 */
class renderer extends \bbn\objdb
{
	/**
	 * @param object $obj A user's connection object (\bbn\user\connection or subclass)
   * @param object|false $mailer A mail object with the send method
   * 
	 */
  public function __construct(\bbn\db $db){
    parent::__construct($db);
  }

  /**
   * Returns all the users' groups - with or without admin
   * @param bool $adm
   * @return array|false
   */

}