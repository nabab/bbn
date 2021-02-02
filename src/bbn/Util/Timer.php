<?php
/**
 * @package util
 */
namespace bbn\Util;

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
class Timer
{

  private $_measures;


  /**
   * @return void
   */
  public function __construct()
  {
    $this->_measures = [];
  }


  /**
   * Starts a timer for a given key
   *
   * @return void
   */
  public function start($key = 'default', $from = null)
  {
    if (!isset($this->_measures[$key])) {
      $this->_measures[$key] = [
        'num' => 0,
        'sum' => 0,
        'start' => $from ?: microtime(1)
      ];
    }
    else {
      $this->_measures[$key]['start'] = $from ?: microtime(1);
    }

    return true;
  }


  /**
   * Returns true is the timer has started for the given key
   *
   * @return bool
   */
  public function hasStarted($key = 'default')
  {
    return isset($this->_measures[$key], $this->_measures[$key]['start']) &&
      ($this->_measures[$key]['start'] > 0);
  }


  public function reset($key = 'default')
  {
    if ($this->hasStarted($key)) {
      $this->_measures[$key] = [
        'num' => 0,
        'sum' => 0
      ];
    }
  }


  /**
   * Stops a timer for a given key
   *
   * @return float
   */
  public function stop($key = 'default')
  {
    if ($this->hasStarted($key)) {
      $this->_measures[$key]['num']++;
      $time                          = $this->measure($key);
      $this->_measures[$key]['sum'] += $time;
      unset($this->_measures[$key]['start']);
      return $time;
    }

    throw new \Exception(_("Missing a start declaration for timer")." $key");
  }


  public function measure($key = 'default')
  {
    if ($this->hasStarted($key)) {
      return microtime(1) - $this->_measures[$key]['start'];
    }
  }


  public function current($key = 'default'): array
  {
    if (isset($this->_measures[$key])) {
      return \array_merge(
        ['current' => $this->hasStarted($key) ? $this->measure($key) : 0],
        $this->_measures[$key]
      );
    }

    return [];
  }


  public function currents(): array
  {
    $currents = [];
    foreach ($this->_measures as $key => $val){
      $currents[$key] = \array_merge(
        [
        'current' => $this->hasStarted($key) ? $this->measure($key) : 0
        ], $val
      );
    }

    return $currents;
  }


  /**
   * @return array
   */
  public function result($key = 'default')
  {
    if (isset($this->_measures[$key])) {
      if ($this->hasStarted($key)) {
        $this->stop($key);
      }

      return [
        'num' => $this->_measures[$key]['num'],
        'total' => number_format($this->_measures[$key]['sum'], 10),
        'average' => number_format($this->_measures[$key]['sum'] / $this->_measures[$key]['num'], 10)
      ];
    }
  }


  /**
   * @return array
   */
  public function results()
  {
    $r = [];
    foreach ($this->_measures as $key => $val){
      $r[$key] = $this->result($key);
    }

    return $r;
  }


  public function remove($key = 'default'): bool
  {
    if (isset($this->_measures[$key])) {
      unset($this->_measures[$key]);
      return true;
    }

    return false;
  }


}
