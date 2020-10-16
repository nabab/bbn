<?php
/**
 * @package util
 */
namespace bbn\util;
/**
 * Encryption Class
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since July 11, 2013, 13:08:00 +01:00
 * @category  Utilities
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.1
 */
class timer
{
  private $measures;
  
	/**
	 * @return void 
	 */
	public function __construct()
	{
    $this->measures = [];
	}

	/**
   * Starts a timer for a given key
   * 
	 * @return void 
	 */
	public function start($key='default', $from = null)
	{
		if ( !isset($this->measures[$key]) ){
      $this->measures[$key] = [
        'num' => 0,
        'sum' => 0,
        'start' => $from ?: microtime(1)
      ];
    }
    else{
      $this->measures[$key]['start'] = $from ?: microtime(1);
    }
  }

  /**
   * Returns true is the timer has started for the given key
   * 
	 * @return bool
	 */
  public function has_started($key='default')
  {
    return
      isset($this->measures[$key], $this->measures[$key]['start']) &&
      ($this->measures[$key]['start'] > 0);
  }
  
  public function reset($key='default')
  {
    if ($this->has_started($key)) {
      $this->measures[$key] = [
        'num' => 0,
        'sum' => 0
      ];
    }
  }
  
	/**
   * Stops a timer for a given key
   * 
	 * @return int
	 */
  public function stop($key='default')
  {
    if ( $this->has_started($key) ){
      $this->measures[$key]['num']++;
      $time = $this->measure($key);
      $this->measures[$key]['sum'] += $time;
      unset($this->measures[$key]['start']);
      return $time;
    }
    else{
      die("Missing a start declaration for timer $key");
    }
  }

  public function measure($key='default'){
    if ( $this->has_started($key) ){
      return microtime(1) - $this->measures[$key]['start'];
    }
  }

  public function current($key = 'default'): array
  {
    if ( isset($this->measures[$key]) ){
      return \array_merge([
        'current' => $this->has_started($key) ? $this->measure($key) : 0
      ], $this->measures[$key]);
    }
    return [];
  }

  public function currents(): array
  {
    $currents = [];
    foreach ( $this->measures as $key => $val ){
      $currents[$key] = \array_merge([
        'current' => $this->has_started($key) ? $this->measure($key) : 0
      ], $val);
    }
    return $currents;
  }

	/**
	 * @return array
	 */
  public function result($key='default')
  {
    if ( isset($this->measures[$key]) ){
      if ( $this->has_started($key) ){
        $this->stop($key);
      }
      return [
        'num' => $this->measures[$key]['num'],
        'total' => number_format($this->measures[$key]['sum'], 10),
        'average' => number_format($this->measures[$key]['sum'] / $this->measures[$key]['num'], 10)
      ];
    }
  }
  
	/**
	 * @return array
	 */
  public function results()
  {
    $r = [];
    foreach ( $this->measures as $key => $val ){
      $r[$key] = $this->result($key);
    }
    return $r;
  }

  public function remove($key = 'default'): bool
  {
    if ( isset($this->measures[$key]) ){
      unset($this->measures[$key]);
      return true;
    }
    return false;
  }
}