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

use bbn\X;

class Timer
{

  private array $_measures = [];

  /**
   * Starts a timer for a given key
   *
   * @param string $key
   * @param null $from
   * @return bool
   */
  public function start(string $key = 'default', $from = null): bool
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
   * @param string $key
   * @return bool
   */
  public function hasStarted(string $key = 'default'): bool
  {
    return isset($this->_measures[$key], $this->_measures[$key]['start']) &&
      ($this->_measures[$key]['start'] > 0);
  }


  /**
   * Resets the timer for the given key.
   *
   * @param string $key
   * @return void
   */
  public function reset(string $key = 'default')
  {
    if ($this->hasStarted($key)) {
      $this->_measures[$key] = [
        'num' => 0,
        'sum' => 0
      ];
    }
  }


  /**
   * Resets the timer for the given key.
   *
   * @return void
   */
  public function resetAll()
  {
    foreach (array_keys($this->_measures) as $k) {
      unset($this->_measures[$k]);
    }
  }


  /**
   * Stops a timer for a given key
   *
   * @param string $key
   * @return float
   * @throws \Exception
   */
  public function stop(string $key = 'default')
  {
    if ($this->hasStarted($key)) {
      $this->_measures[$key]['num']++;
      $time                          = $this->measure($key);
      $this->_measures[$key]['sum'] += $time;
      unset($this->_measures[$key]['start']);
      return $time;
    }

    throw new \Exception(X::_("Missing a start declaration for timer")." $key");
  }


  /**
   * @param string $key
   * @return mixed|void
   */
  public function measure(string $key = 'default')
  {
    if ($this->hasStarted($key)) {
      return microtime(1) - $this->_measures[$key]['start'];
    }
  }


  /**
   * @param string $key
   * @return array
   */
  public function current(string $key = 'default'): array
  {
    if (isset($this->_measures[$key])) {
      return \array_merge(
        ['current' => $this->hasStarted($key) ? $this->measure($key) : 0],
        $this->_measures[$key]
      );
    }

    return [];
  }


  /**
   * @return array
   */
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
   * @param string $key
   * @return array
   * @throws \Exception
   */
  public function result(string $key = 'default')
  {
    if (isset($this->_measures[$key])) {
      if ($this->hasStarted($key)) {
        $this->stop($key);
      }

      return [
        'num' => $this->_measures[$key]['num'],
        'total' => number_format($this->_measures[$key]['sum'], 10, '.', ''),
        'average' => number_format(
          $this->_measures[$key]['num'] != 0
            ? $this->_measures[$key]['sum'] / $this->_measures[$key]['num']
            : 0, 10, '.', ''
        )
      ];
    }
  }


  /**
   * @return array
   * @throws \Exception
   */
  public function results(): array
  {
    $r = [];
    foreach ($this->_measures as $key => $val){
      $r[$key] = $this->result($key);
    }

    return $r;
  }


  /**
   * @param string $key
   * @return bool
   */
  public function remove(string $key = 'default'): bool
  {
    if (isset($this->_measures[$key])) {
      unset($this->_measures[$key]);
      return true;
    }

    return false;
  }


}
