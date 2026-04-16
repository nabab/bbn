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
   * @param null|float $from
   * @param null|string $uid
   * @return bool
   */
  public function start(string $key = 'default', ?float $from = null, ?string $uid = null): bool
  {
    if (!isset($this->_measures[$key])) {
      $this->_measures[$key] = [
        'num' => 0,
        'sum' => 0,
        $uid ? 'subs' : 'start' => $uid ? [] : ($from ?: microtime(1))
      ];
    }
    if ($uid) {
      $this->_measures[$key]['subs'][$uid] = [
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
   * @param null|string $uid
   * @return bool
   */
  public function hasStarted(string $key = 'default', ?string $uid = null): bool
  {
    $mes = $this->_measures[$key] ?? null;
    if ($uid) {
      return isset($mes['subs'][$uid]) && ($mes['subs'][$uid]['start'] > 0);
    }

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
   */
  public function stop(string $key = 'default', ?string $uid = null): float
  {
    if ($this->hasStarted($key, $uid)) {
      $this->_measures[$key]['num']++;
      $time                          = $this->measure($key, $uid);
      $this->_measures[$key]['sum'] += $time;
      if ($uid) {
        unset($this->_measures[$key]['subs'][$uid]);
      }
      else {
        unset($this->_measures[$key]['start']);
      }

      return $time;
    }

    X::log("Trying to stop a timer that hasn't been started for key $key", 'warning');
    return 0;
  }


  /**
   * @param string $key
   * @return mixed|void
   */
  public function measure(string $key = 'default', ?string $uid = null)
  {
    if ($this->hasStarted($key, $uid)) {
      if ($uid) {
        return microtime(1) - $this->_measures[$key]['subs'][$uid]['start'];
      }

      return microtime(1) - $this->_measures[$key]['start'];
    }
  }


  /**
   * @param string $key
   * @return array
   */
  public function current(string $key = 'default', ?string $uid = null): array
  {
    if (isset($this->_measures[$key])) {
      return \array_merge(
        ['current' => $this->hasStarted($key, $uid) ? $this->measure($key, $uid) : 0],
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
  public function result(string $key = 'default'): ?array
  {
    if (isset($this->_measures[$key])) {
      if (!empty($this->_measures[$key]['subs'])) {
        array_map(fn($a) => $this->hasStarted($key, $a) ? $this->stop($key, $a) : 0, array_keys($this->_measures[$key]['subs']));
      }

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

    return null;
  }


  /**
   * @return array
   * @throws \Exception
   */
  public function results(): array
  {
    return array_map(fn ($a) => $this->result($a), array_keys($this->_measures));
  }


  /**
   * @param string $key
   * @return bool
   */
  public function remove(string $key = 'default', ?string $uid = null): bool
  {
    if (isset($this->_measures[$key])) {
      if ($uid) {
        if (isset($this->_measures[$key]['subs'][$uid])) {
          unset($this->_measures[$key]['subs'][$uid]);
          return true;
        }
        return false;
      }
      unset($this->_measures[$key]);
      return true;
    }

    return false;
  }


}
