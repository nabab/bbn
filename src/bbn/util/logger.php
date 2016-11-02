<?php
namespace bbn\util;
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
 * @since Jan 14, 2013, 23:23:55 +0000
 * @category  Traits
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.4
 */
trait logger
{
	public $reports = [];
	/**
	 * Add information to the $info array
	 *
	 * @param string $st
	 * @return null
	 */
	public function report($st)
	{
		if ( php_sapi_name() == 'cli' && empty($_SERVER['REMOTE_ADDR']) ){
			if ( is_string($st) ){
				echo $st."\n";
			}
			else{
				print_r($st,1)."\n";
			}
		}
		else{
			if ( is_string($st) ){
				array_push($this->reports,$st);
			}
			else{
				array_push($this->reports,print_r($st,true));
			}
		}
		return $this;
	}
	
	public function debug($file='misc')
	{
		$i = debug_backtrace();
		bbn\x::log(print_r($i, 1));
	}
	/**
	 * Add information to the $info array
	 *
	 * @param string $st
	 * @param string $file
	 * @return null
	 */
	public function log($st='',$file='misc')
	{
		if ( defined('BBN_DATA_PATH') && is_dir(BBN_DATA_PATH.'logs') ){
			$log_file = BBN_DATA_PATH.'logs/'.$file.'.log';
			$r = "[".date('d/m/Y H:i:s')."]\t";
			if ( empty($st) && count($this->reports) > 0 ){
				$st = implode("\n\n", $this->reports);
				$this->reports = [];
			}
			else{
				$i = debug_backtrace()[0];
				$r .= $i['file']." - line ".$i['line'];
			}
			$r .= "\n".( is_string($st) ? $st : print_r($st, true) )."\n\n";
			$s = ( file_exists($log_file) ) ? filesize($log_file) : 0;
			if ( $s > 1048576 ){
				file_put_contents($log_file.'.old',file_get_contents($log_file),FILE_APPEND);
				file_put_contents($log_file,$r);
			}
			else{
				file_put_contents($log_file,$r,FILE_APPEND);
			}
		}
		return $this;
	}
}
?>