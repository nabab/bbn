<?php
namespace bbn\Util;

use bbn\Mvc;
/**
 * A few recurrent functions
 *
 *
 * These functions are basically creating a database reference and logging functions.
 * In order to implement this trait, the following private static variables should be declared:
 *	* $cli
 *	* $info = array()
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Traits
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.2r89
 */
trait Info
{
	/**
	 * Add information to the $info array
	 *
	 * @param string $st
	 * @return null
	 */
	public static function report($st)
	{
		if ( !isset(self::$cli) )
		{
			global $argv;
			self::$cli = isset($argv) ? 1 : false;
		}
		if ( self::$cli )
		{
			if ( \is_string($st) )
				echo $st."\n";
			else
				var_dump($st)."\n";
		}
		else
		{
			if ( \is_string($st) )
				array_push(self::$info,$st);
			else
				array_push(self::$info,print_r($st,true));
		}
	}
	/**
	 * Add information to the $info array
	 *
	 * @param string $st
	 * @param string $file
	 * @return null
	 */
	public static function log($st,$file='misc')
	{
		if ( is_dir(Mvc::getTmpPath() . 'logs') ){
			$log_file = Mvc::getTmpPath() . 'logs/'.$file.'.log';
			$i = debug_backtrace()[0];
			$r = "[".date('d/m/Y H:i:s')."]\t".$i['file']." - line ".$i['line']."\n";
			if ( !\is_string($st) )
				$r .= print_r($st,true);
			else
				$r .= $st;
			$r .= "\n\n";
			$s = ( file_exists($log_file) ) ? filesize($log_file) : 0;
			if ( $s > 1048576 )
			{
				file_put_contents($log_file.'.old',file_get_contents($log_file),FILE_APPEND);
				file_put_contents($log_file,$r);
			}
			else
				file_put_contents($log_file,$r,FILE_APPEND);
		}
	}
  
}
?>