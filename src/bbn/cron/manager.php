<?php
/*
 * Copyright (C) 2014 BBN
 *
 */

namespace bbn\cron;
use bbn;

/**
 * Class cron
 * @package bbn\appui
 */
class manager extends bbn\models\cls\basic {

  use common;

  /**
   * cron constructor.
   * @param bbn\mvc\controller $ctrl
   * @param array $cfg
   */
  public function __construct(bbn\db $db, array $cfg = []) {
    //if ( defined('BBN_DATA_PATH') ){
    if (bbn\mvc::get_data_path() && $db->check()) {
      // It must be called from a plugin (appui-cron actually)
      //$this->path = BBN_DATA_PATH.'plugins/appui-cron/';
      $this->path = bbn\mvc::get_data_path('appui-cron');
      $this->db = $db;
      $this->table = $this->prefix.'cron';
    }
  }

  public function check(): bool
  {
      return (bool)($this->db && $this->db->check());
  }

  /**
   * Returns the full row as an indexed array for the given CRON ID.
   * @param $id
   * @return null|array
   */
  public function get_cron(string $id): ?array
  {
    if ( $this->check() && ($data = $this->db->rselect($this->table, [], ['id' => $id])) ){
      $data['cfg'] = json_decode($data['cfg'], 1);
      return $data;
    }
    return null;
  }

  /**
   * @param $id_cron
   * @return bool
   */
  public function is_timeout($id_cron){
    if (
      $this->check()
      && ($cron = $this->get_cron($id_cron))
      && ($path = $this->get_log_path($cron))
      && is_file($path)
    ){
      [$pid, $time] = bbn\x::split(file_get_contents($path), '|');
      return (($time + $cron['cfg']['timeout']) < time());
    }
    return false;
  }

  /**
   * Writes in the given CRON row the next start time, the current as previous, and the new running status.
   * @param $id_cron
   * @return bool
   */
  public function start(string $id_cron): bool
  {
    $res = false;
    if (
        $this->check()
        && ($cron = $this->get_cron($id_cron))
    ){
      $enable = false;
      if ($this->db->is_trigger_enabled()) {
        $this->db->disable_trigger();
        $enable = true;
      }
      if ( $this->db->update($this->table, [
        'prev' => date('Y-m-d H:i:s'),
        'pid' => getmypid()
      ], [
        'id' => $id_cron,
        'pid' => null,
        'active' => 1
      ]) ){
        $res = true;
      }
      if ($enable) {
        $this->db->enable_trigger();
      }
    }
    return $res;
  }

  /**
   * Writes in the given CRON row the duration and the new finished status.
   * @param $id
   * @param string $res
   * @return bool|int
   */
  public function finish(string $id_cron, $res = ''){
    $res = false;
    if ($cron = $this->get_cron($id_cron)) {
      if (!empty($cron['cfg']['frequency'])) {
        $time = time();
        $start = date('Y-m-d H:i:s', $time);
        $next = $this->get_next_date($cron['cfg']['frequency'], strtotime($cron['next'] ?: $start));
      }
      $enable = false;
      $err_mode = $this->db->get_error_mode();
      $this->db->set_error_mode('continue');
      if ($this->db->is_trigger_enabled()) {
        $this->db->disable_trigger();
        $enable = true;
      }
      if ( $this->db->update($this->table, [
        'next' => $next ?? null,
        'pid' => null,
        'active' => isset($next) ? 1 : 0
      ], [
        'id' => $id_cron,
        'pid' => getmypid()
      ]) ){
        $res = true;
      }
      if ($err_mode !== 'continue') {
        $this->db->set_error_mode($err_mode);
      }
      if ($enable) {
        $this->db->enable_trigger();
      }
    }
    return $res;
  }

  /**
   * Returns a SQL date for the next event given a frequency and a time to count from (now if 0).
   * @param  string $frequency A string made of 1 letter (i, h, d, w, m, or y) and a number.
   * @param  int    $from_time A SQL formatted date which will be the base of the operation.
   * @return null|string
   */
  public function get_next_date(string $frequency, int $from_time = 0): ?string
  {
    if (\is_string($frequency) && (\strlen($frequency) >= 2)) {
      $letter = bbn\str::change_case(substr($frequency, 0, 1), 'lower');
      $number = (int)substr($frequency, 1);
      $letters = ['y', 'm', 'w', 'd', 'h', 'i', 's'];
      if (in_array($letter, $letters, true) && ($number > 0)) {
        $time = time();
        if (!$from_time) {
          $from_time = $time;
        }
        $year = intval(date('Y', $from_time));
        $month = intval(date('n', $from_time));
        $day = intval(date('j', $from_time));
        $hour = intval(date('G', $from_time));
        $minute = intval(date('i', $from_time));
        $second = intval(date('s', $from_time));
        $adders = [];
        foreach ($letters as $lt) {
          $adders[$lt] = 0;
        }
        $r = 0;
        $step = 0;
        if (!is_numeric($number)) {
          \bbn\x::log($number, 'next_date');
        }

        $test = mktime(
          $hour + ($letter === 'h' ? $number : 0),
          $minute + ($letter === 'i' ? $number : 0),
          $second + ($letter === 's' ? $number : 0),
          $month + ($letter === 'm' ? $number : 0),
          $day + ($letter === 'd' ? $number : ($letter === 'w' ? 7 * $number : 0)),
          $year + ($letter === 'y' ? $number : 0)
        );
        $length = $test - $from_time;
        if ($test < $time) {
          $diff = $time - $test;
          $step = floor($diff/$length);
        }
        while ($r <= $time) {
          $step++;
          if ($letter === 'w') {
            $adders['d'] = $step * 7 * $number;
          }
          else {
            $adders[$letter] = $step * $number;
          }
          $r = mktime(
            $hour + $adders['h'],
            $minute + $adders['i'],
            $second + $adders['s'],
            $month + $adders['m'],
            $day + $adders['d'],
            $year + $adders['y']
          );
        }
        if ($r) {
          return date('Y-m-d H:i:s', $r);
        }
      }
    }
    return null;
  }

  /**
   * Returns the whole row for the next CRON to be executed from now if there is any.
   * @param null $id_cron
   * @return null|array
   */
  public function get_next($id_cron = null): ?array
  {
    $conditions = [[
      'field' => 'next',
      'operator' => '<',
      'exp' => 'NOW()'
    ], [
      'field' => 'next',
      'operator' => 'isnotnull'
    ], [
      'field' => 'active',
      'value' => 1
    ]];
    if ( bbn\str::is_uid($id_cron) ){
      $conditions[] = [
        'field' => 'id',
        'value' => $id_cron
      ];
    }
    if ( 
      $this->check() &&
      ($data = $this->db->rselect([
        'table' => $this->table,
        'fields' => [],
        'where' => [
          'conditions' => $conditions
        ],
        'order' => [[
          'field' => 'priority',
          'dir' => 'ASC'
        ], [
          'field' => 'next',
          'dir' => 'ASC'
        ]]
      ]))
    ){
      // Dans cfg: timeout, et soit: latency, minute, hour, day of month, day of week, date
      $data['cfg'] = json_decode($data['cfg'], 1);
      return $data;
    }
    return null;
  }

  public function get_running_rows(): ?array
  {
    if ( $this->check() ){
      return array_map(function($a){
        $cfg = $a['cfg'] ? json_decode($a['cfg'], true) : [];
        unset($a['cfg']);
        return \bbn\x::merge_arrays($a, $cfg);
      }, $this->db->rselect_all([
        'table' => $this->table,
        'fields' => [],
        'where' => [
          'conditions' => [[
            'field' => 'active',
            'value' => 1
          ], [
            'field' => 'pid',
            'operator' => 'isnotnull'
          ]]
        ],
        'order' => [[
          'field' => 'prev',
          'dir' => 'ASC'
        ]]
      ]));
    }
    return null;
  }

  public function get_next_rows(int $limit = 10, int $sec = 0): ?array
  {
    if ( $limit === 0 ){
      $limit = 1000;
    }
    if ( $this->check() ){
      return array_map(function($a){
        $cfg = $a['cfg'] ? json_decode($a['cfg'], true) : [];
        unset($a['cfg']);
        return \bbn\x::merge_arrays($a, $cfg);
      }, $this->db->rselect_all([
        'table' => $this->table,
        'fields' => [],
        'where' => [
          'conditions' => [[
            'field' => 'active',
            'value' => 1
          ], [
            'field' => 'pid',
            'operator' => 'isnull'
          ], [
            'field' => 'next',
            'operator' => 'isnotnull'
          ], [
            'field' => 'next',
            'operator' => '<',
            'exp' => $sec ? "DATE_ADD(NOW(), INTERVAL $sec SECOND)" : 'NOW()'
          ]]
        ],
        'order' => [[
          'field' => 'priority',
          'dir' => 'ASC'
        ], [
          'field' => 'next',
          'dir' => 'ASC'
        ]],
        'limit' => $limit
      ]));
    }
    return null;
  }

  public function get_failed(): ?array
  {
    if ( $this->check() ){
      return array_map(function($a){
        $cfg = $a['cfg'] ? json_decode($a['cfg'], true) : [];
        unset($a['cfg']);
        return \bbn\x::merge_arrays($a, $cfg);
      }, $this->db->rselect_all([
        'table' => $this->table,
        'fields' => [],
        'where' => [
          'conditions' => [[
            'field' => 'active',
            'value' => 1
          ], [
            'field' => 'pid',
            'operator' => 'isnotnull'
          ], [
            'field' => 'next',
            'operator' => 'isnotnull'
          ], [
            'field' => 'NOW()',
            'operator' => '>',
            'exp' => "DATE_ADD(prev, INTERVAL cfg->'$.timeout' SECOND)"
          ]]
        ],
        'order' => [[
          'field' => 'priority',
          'dir' => 'ASC'
        ], [
          'field' => 'next',
          'dir' => 'ASC'
        ]]
      ]));
    }
    return null;
  }

  /**
   * @param $id_cron
   * @return bool
   */
  public function is_running(string $id_cron) {
    return (bool)( $this->check() && $this->db->count($this->table, [
      'id' => $id_cron,
      ['pid', 'isnotnull']
    ]));
  }

  /**
   * Sets the active column to 1 for the given CRON ID.
   * @param $id_cron
   * @return mixed
   */
  public function activate($id_cron){
    return $this->db->update($this->table, ['active' => 1], ['id' => $id_cron]);
  }

  /**
   * Sets the active column to 0 for the given CRON ID.
   * @param $id_cron
   * @return mixed
   */
  public function deactivate($id_cron){
    return $this->db->update($this->table, ['active' => 0], ['id' => $id_cron]);
  }

  /**
   * Sets the active column to 1 for the given CRON ID.
   * @param $id_cron
   * @return mixed
   */
  public function set_pid($id_cron, $pid){
    return $this->db->update($this->table, ['pid' => $pid], ['id' => $id_cron]);
  }

  /**
   * Sets the active column to 0 for the given CRON ID.
   * @param $id_cron
   * @return mixed
   */
  public function unset_pid($id_cron){
    return $this->db->update($this->table, ['pid' => null], ['id' => $id_cron]);
  }

  public function add($cfg): ?array
  {
    if (
      defined('BBN_PROJECT')
      && $this->check()
      && bbn\x::has_props($cfg, ['action', 'file', 'priority', 'frequency', 'timeout'], true)
    ) {
      $d = [
        'file' => $cfg['file'],
        'description' => $cfg['description'] ?? '',
        'next' => $cfg['next'] ?? date('Y-m-d H:i:s'),
        'priority' => $cfg['priority'],
        'cfg' => json_encode([
          'frequency' => $cfg['frequency'],
          'timeout' => $cfg['timeout']
        ]),
        'project' => BBN_PROJECT,
        'active' => 1
      ];
      if ( $this->db->insert($this->table, $d)) {
        $d['id'] = $this->db->last_id();
        return $d;
      }
    }
    return null;
  }

  public function add_single(string $file, int $priority = 1, int $timeout = 360)
  {
    if (defined('BBN_PROJECT') && $this->check()) {
      $d = [
        'file' => $file,
        'description' => _('One shot action'),
        'next' => date('Y-m-d H:i:s'),
        'priority' => $priority,
        'cfg' => json_encode([
          'frequency' => null,
          'timeout' => $timeout
        ]),
        'project' => BBN_PROJECT,
        'active' => 1
      ];
      if ( $this->db->insert_update($this->table, $d)) {
        $d['id'] = $this->db->last_id();
        return $d;
      }
    }
    return null;
  }

  public function edit(string $id, array $cfg): ?array
  {
    if (
      $this->check()
      && ($cron = $this->get_cron($id))
    ) {
      $d = [
        'file' => $cfg['file'] ?: $cron['file'],
        'description' => $cfg['description'] ?: $cron['description'],
        'next' => $cfg['next'] ?: $cron['next'],
        'priority' => $cfg['priority'] ?: $cron['priority'],
        'cfg' => json_encode([
          'frequency' => $cfg['frequency'] ?: $cron['frequency'],
          'timeout' => $cfg['timeout'] ?: $cron['timeout']
        ]),
        'project' => BBN_PROJECT,
        'active' => 1
      ];
      if ( $this->db->update($this->table, $d, ['id' => $id])) {
        $d['id'] = $id;
        return $d;
      }
    }
    return null;
  }

}
