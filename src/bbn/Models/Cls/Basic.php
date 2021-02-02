<?php
/**
 * @package bbn
 */
namespace bbn\Models\Cls;


use bbn;

/**
 * Basic object Class
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
abstract class Basic
{

  protected
    /**
     * @var array
     */
    $errors = [],
    /**
     * @var false|string
     */
    $error = false,
        /**
         * @var boolean
         */
        $debug = false,
        /**
         * @var array
         */
        $log = [];


  /**
   * Checks whether the error property has been set (so an error happened).
   * @return bool
   */
  public function test()
  {
    if ($this->error) {
      return false;
    }

    return true;
  }


  /**
   * Checks whether the error property has been set (so an error happened).
   * @return bool
   */
  public function check()
  {
    if ($this->error) {
      return false;
    }

    return true;
  }


  protected function setError($err)
  {
    $this->error    = $err;
    $this->errors[] = [
      'time' => time(),
      'msg' => $err
    ];
    return $this;
  }


  public function getError()
  {
    return $this->error;
  }


  public function getErrors()
  {

  }


  public function log()
  {
    if ($this->isDebug()) {
        $ar = \func_get_args();
        $cn = bbn\Str::encodeFilename(str_replace('\\', '_', \get_class($this)));
      foreach ($ar as $a){
            bbn\X::log($a, $cn);
      }
    }
  }


  /**
   * @param string $name
   * @param array  $arguments
   * @return void
   */
  public function __call($name, $arguments)
  {
    $class = \get_class($this);
    throw new \Exception(
      sprintf(
        _("Wrong method used for the class %s: %s with the following arguments:"),
        $class,
        $name,
        implode(', ', $arguments)
      )
    );
  }


  /**
   * @return boolean
   */
  public function isDebug()
  {
    return $this->debug || (defined("BBN_IS_DEV") && BBN_IS_DEV);
  }


  /**
   * @param boolean $debug
   * @return self
   */
  public function setDebug(bool $debug)
  {
    $this->debug = $debug;
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
            array_push(bbn\X::log, $value);
    }
     */
}
