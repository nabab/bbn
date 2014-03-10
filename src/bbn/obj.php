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
	/**
	 * @var false|string
	 */
	protected $error;

	/**
	 * @var array
	 */
	protected $log=array();



	/**
	 * Checks whether the error property has been set (so an error happened).
	 * @return bool
	 */
	public function test()
	{
		if ( $this->error )
			return false;
		return true;
	}
  
  public function log()
  {
    $ar = func_get_args();
    $cn = \bbn\str\text::encode_filename(get_class($this));
    foreach ( $ar as $a ){
      \bbn\tools::log($a, $cn);
    }
  }

  /**
	 * @param string $name
	 * @param array $arguments
	 * @return void 
	 */
	public function __call($name, $arguments)
	{
		return $this;
	}

	/**
	 * @param string $name
	 * @param array $arguments
	 * @return void 
	 */
	public static function __callStatic($name, $arguments)
	{
		return false;
	}

	/**
	 * get property from delegate link.
	 *
	 * @param string $name
	 * @return void 
	 */
	public function __get($name)
	{
		if ( isset($this->$name) )
			return $this->$name;
	}

	/**
	 * set property from delegate link.
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return void 
	 */
	public function __set($name, $value)
	{
		if ( $name === 'error' && $name === false )
			$this->error = $value;
		/*
     * else if ( $name === 'log' )
			array_push(\bbn\tools::log, $value);
     */
	}
}
?>