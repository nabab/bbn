<?php
/**
 * @package bbn
 */
namespace bbn\Models\Cls;


use bbn\X;
use bbn\Str;
use Exception;

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

  /**
   * @var array
   */
  protected $errors = [];
  /**
   * @var false|string
   */
  protected $error = false;
  /**
   * @var false|int
   */
  protected $errorCode = false;

  protected $errorCodes = [];
  /**
   * @var boolean
   */
  protected $debug = false;
  /**
   * @var array
   */
  protected $log = [];


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


  protected function setError(string $err, $code = null)
  {
    $this->error    = $err;
    $this->errorCode = $code;
    $err = [
      'time' => time(),
      'msg' => $err
    ];
    if ($code) {
      $err['code'] = $code;
    }

    $this->errors[] = $err;
    return $this;
  }


  public function getError()
  {
    return $this->error;
  }


  public function getErrorCode()
  {
    return $this->errorCode;
  }


  public function getErrors(): array
  {
    return $this->errors;
  }


  public function log(...$args)
  {
    if ($this->isDebug()) {
      $cn = Str::encodeFilename(str_replace('\\', '_', get_class($this)));
      foreach ($args as $a){
        X::log($a, $cn);
      }
    }
  }


  /**
   * @return boolean
   */
  public function isDebug(): bool
  {
    return $this->debug || constant("BBN_IS_DEV");
  }


  /**
   * @param boolean $debug
   * @return self
   */
  public function setDebug(bool $debug): void
  {
    $this->debug = $debug;
  }
}
