<?php
/* 
 * Copyright (C) 2014 BBN
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace bbn\appui;

class cron extends \bbn\obj{
  
	private
          /* @var \bbn\db\connection The DB connection */
          $db = false,
          /* @var string The tables' prefix (the tables will be called ?cron and ?journal) */
          $prefix = 'bbn_',
          $primary = 'id',
          $date = false,
          $last_rows = false,
          $ok = false,
          $enabled = true;
	
  public function __construct(\bbn\mvc $mvc, $cfg = []) {
    if ( is_array($cfg) ){
      $this->mvc =& $mvc;
      if ( empty($mvc->db) ){
        die("Database mandatory in \\bbn\\appui\\cron !");
      }
      $this->db =& $mvc->db;
      $vars = get_class_vars('\\bbn\\appui\\cron');
      foreach ( $cfg as $cf_name => $cf_value ){
        if ( array_key_exists($cf_name, $vars) ){
          $this->{$cf_name} = $cf_value;
        }
      }
      $this->timer = new \bbn\util\timer();
      $this->table = $this->prefix.'cron';
      $this->jtable = $this->prefix.'cron_journal';
    }
  }
  
  public function check(){
    if ( $this->table && $this->db ){
      return 1;
    }
    return false;
  }
  
  public function get_article($id){
    if ( $this->check() ){
      $data = $this->db->rselect($this->jtable, [], ['id' => $id]);
      $data['cfg'] = json_decode($data['cfg'], 1);
      return $data;
    }
  }
  
  public function get_cron($id){
    if ( $this->check() ){
      $data = $this->db->rselect($this->table, [], ['id' => $id]);
      $data['cfg'] = json_decode($data['cfg'], 1);
      return $data;
    }
  }
  
  public function start($id_cron){
    if ( $this->check() && $this->db->insert($this->jtable, [
      'id_cron' => $id_cron,
      'start' => date('Y-m-d H:i:s')
    ]) ){
      return $this->db->last_id ();
    }
    return false;
  }
  
  public function finish($id, $res = ''){
    if ( ($article = $this->get_article($id)) &&
            ($cron = $this->get_cron($article['id_cron'])) ){
      $date = time();
      $this->db->update($this->jtable, [
          'finish' => date('Y-m-d H:i:s', $date),
          'res' => $res
        ], [
          'id' => $id
        ]);
      $this->db->update($this->table, [
        'prev' => $article['start'],
        'next' => date('Y-m-d H:i:s', $this->get_next_date($cron['cfg']['frequency']))
      ], [
        'id' => $cron['id']
      ]);
      return 1;
    }
    return false;
  }
  
  public function get_next_date($frequency, $timestamp = false){
    if ( is_string($frequency) && (strlen($frequency) >= 2) ){
      if ( !$timestamp ){
        $timestamp = time();
      }
      $letter = \bbn\str\text::change_case(substr($frequency, 0, 1), 'lower');
      $number = (int)substr($frequency, 1);
      if ( $number > 0 ){
        switch ( $letter ){
          case 'i':
            $unit = 60;
            break;
          case 'h':
            $unit = 3600;
            break;
          case 'd':
            $unit = 24*3600;
            break;
          case 'w':
            $unit = 7*24*3600;
            break;
        }
        if ( isset($unit) ){
          $r = $timestamp + ($unit * $number);
        }
        if ( $letter === 'm' ){
          $r = mktime(date('H', $timestamp), date('i', $timestamp), date('s', $timestamp), date('n', $timestamp)+$number, date('j', $timestamp), date('Y', $timestamp));
        }
        if ( $letter === 'y' ){
          $r = mktime(date('H', $timestamp), date('i', $timestamp), date('s', $timestamp), date('n', $timestamp)+$number, date('j', $timestamp), date('Y', $timestamp));
        }
        if ( isset($r) ){
          if ( $r < time() ){
            return $this->get_next_date($frequency, $r);
          }
          return $r;
        }
      }
    }
    return false;
  }
  
  public function get_next($id_cron = null){
    if ( $this->check() && ($data = $this->db->get_row("
        SELECT *
        FROM {$this->table}
        WHERE active = 1 
        AND next < NOW()".
        ( is_int($id_cron) ? " AND id_cron = $id_cron" : "" )."
        ORDER BY next ASC
        LIMIT 1")) ){
      // Dans cfg: timeout, et soit: latency, minute, hour, day of month, day of week, date
      $data['cfg'] = json_decode($data['cfg'], 1);
      return $data;
    }
  }
  
  public function is_running($id_cron){
    if ( $this->check() && is_int($id_cron) ){
      return $this->db->get_one("
        SELECT COUNT(*)
        FROM {$this->jtable}
        WHERE id_cron = ?
        AND finish IS NULL",
        $id_cron) ? true : false;
    }
  }
  
  private function get_runner($id_cron){
    if ( $this->check() && is_int($id_cron) ){
      $d = $this->db->get_row("
        SELECT *
        FROM {$this->jtable}
        WHERE id_cron = ?
        AND finish IS NULL",
        $id_cron);
      $d['cfg'] = json_decode($d['cfg'], 1);
    }
  }
  
  private function get_latency($id_journal){
    if ( $this->check() && is_int($id_journal) ){
      
    }
  }
  
  public function run($id_cron = null){
    if ( ($cron = $this->get_next($id_cron)) ){
      $ok  = 1;
      if ( $this->is_running($cron['id']) ){
        $runner = $this->get_runner($cron['id']);
        $start = strtotime($runner['start']);
        $timeout = $runner['cfg']['timeout'];
        if ( ($start + $timeout) > time() ){
          $this->alert();
        }
        $ok = false;
      }
      if ( $ok ){
        $id = $this->start($cron['id']);
        $this->timer->start();
        $output = $this->_exec($cron['file'], $cron['cfg']);
        $this->finish($id, $output);
        \bbn\tools::dump("Execution of ".$cron['file']." (Journal ID: $id) in ".$this->timer->stop()." secs", $output);
        return 1;
      }
    }
  }
  
  private function _exec($file, $data=[]){
    $this->mvc->data = $data;
    $this->obj = new \stdClass();
    ob_start();
    $this->mvc->incl($file, false);
    $output = ob_get_contents();
    ob_end_clean();
    return $output;
  }
}