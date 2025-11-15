<?php
/**
 * @package str
 */
namespace bbn\Str;

use bbn\Str;
/**
 * A Class for hashes
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Strings
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.2r89
 */
class Hash 
{
	/**
	 * @var string
	 */
	private static $separator='/';

	/**
	 * @var array
	 */
	public $keys=array();

	/**
	 * @var array
	 */
	public $values=array();

	/**
	 * @var mixed
	 */
	public $hash;


	/**
	 * @return void 
	 */
	public function __construct($hash='')
	{
		if ( !empty($hash) )
		{
			if ( preg_match('#[A-z0-9_/]+#',$hash,$m) === 1 && $m[0] === $hash )
			{
				$this->hash = $hash;
				while ( Str::pos($this->hash,'//') )
					$this->hash = str_replace('//','/',$this->hash);
				if ( Str::pos($this->hash,'/') === 0 )
					$this->hash = Str::sub($this->hash,1);
				if ( Str::sub($this->hash,-1) === '/' )
					$this->hash = Str::sub($this->hash,0,-1);
			}
		}
		if ( !isset($this->hash) )
			$this->hash = '';
		$h = explode(self::$separator,$this->hash);
		$j = 0;
		for ( $i = 0; $i < ( \count($h) - 1 ); $i += 2 )
		{
			$this->keys[$j] = $h[$i];
			$this->values[$j] = $h[$i+1];
			$j++;
		}
	}

	/**
	 * @return void 
	 */
	public function add($pair, $replace=1)
	{
		if ( \is_array($pair) )
		{
			if ( isset($pair[0]) && isset($pair[1]) )
			{
				$k = $pair[0];
				$v = $pair[1];
			}
			else
			{
				$k = array_keys($pair);
				$k = $k[0];
				$v = $pair[$k];
			}
			
			if (
				preg_match('#[A-z0-9_]+#',$k,$m) === 1 &&
				$m[0] === $k &&
				preg_match('#[A-z0-9_]+#',$v,$m) === 1 &&
				$m[0] === $v
			)
			{
				if ( is_numeric($v) )
					$v += 0;
				$i = array_search($k,$this->keys);
				if ( $i === false )
				{
					array_push($this->keys,$k);
					array_push($this->values,$v);
				}
				else if ( $replace )
				{
					$this->keys[$i] = $k;
					$this->values[$i] = $v;
				}
				return $this;
			}
		}
		return false;
	}

	/**
	 * @return void 
	 */
	public function remove($key)
	{
		if ( isset($this->values[$key]) )
			unset($this->values[$key]);
		return $this;
	}

	/**
	 * @return void 
	 */
	public function get($key='')
	{
		if ( empty($key) )
			return $this->values;
		return isset($this->values[$key]) ? $this->values[$key] : false;
	}

	/**
	 * @return void 
	 */
	public function output()
	{
		$h = '';
		if ( \count($this->values) > 0 )
		{
			foreach ( $this->values as $k => $v )
				$h.= $this->keys[$k].'/'.$v.'/';
			$h = Str::sub($h,0,-1);
		}
		return $h;
	}

}
?>