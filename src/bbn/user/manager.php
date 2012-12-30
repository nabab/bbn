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
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Authentication
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 */
class manager extends \bbn\obj 
{

	/**
	 * @return void 
	 */
	public function add($priv=1)
	{
		return false;
	}

	/**
	 * @return void 
	 */
	public function delete()
	{
	}

	/**
	 * @return void 
	 */
	public function set_privilege($level)
	{
		if ( $this->login )
		{
			$users = self::get_users();
			foreach ( $users as $key => $user )
			{
				if ( $user['login'] == $this->login )
				{
					$users[$key]['priv'] = $level;
					if ( self::set_users($users) )
						return true;
				}
			}
		}
		return false;
	}

}
?>