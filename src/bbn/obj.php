<?php
/**
 * @package bbn
 */
namespace bbn;
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
class obj 
{
	protected
		/**
		 * @var false|string
		 */
		$error,
		/**
		 * @var boolean
		 */
		$is_debug = false,
		/**
		 * @var array
		 */
		$log=array();

	/**
	 * Checks whether the error property has been set (so an error happened).
	 * @return bool
	 */
	public function debug($state = 1)
	{
		$this->is_debug = $state;
	}

	/**
	 * Checks whether the error property has been set (so an error happened).
	 * @return bool
	 */
	public function test()
	{
		if ( $this->error ) {
			return false;
		}
		return true;
	}

	public function log()
  {
		if ( $this->is_debug ){
			$ar = func_get_args();
			$cn = \bbn\str\text::encode_filename(str_replace('\\', '_', get_class($this)));
			foreach ( $ar as $a ){
				\bbn\tools::log($a, $cn);
			}
		}
  }
  
  /**
	 * @param string $name
	 * @param array $arguments
	 * @return void 
	 */
	public function __call($name, $arguments)
	{
    $this->log(["Wrong method used: $name with arguments:", $arguments]);
		return $this;
	}

	/**
	 * @param string $name
	 * @param array $arguments
	 * @return void 
	public static function __callStatic($name, $arguments)
	{
    $this->log(["Wrong static method used: $name with arguments:", $arguments]);
		return false;
	}
	 */

	/**
	 * get property from delegate link.
	 *
	 * @param string $name
	 * @return void 
	public function __get($name)
	{
		return ($name === 'error') && isset($this->error) ? $this->error : false;
	}

	/**
	 * set property from delegate link.
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return void 
	public function __set($name, $value)
	{
		if ( $name === 'error' ){
			$this->error = $value;
    }
		/*
     * else if ( $name === 'log' )
			array_push(\bbn\tools::log, $value);
	}
	 */
}
