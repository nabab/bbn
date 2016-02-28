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
          $mail,
          $ok = false,
          $enabled = true,
          $timeout = 50;

  public function __construct(\bbn\mvc\controller $ctrl, $cfg = []) {
    if ( is_array($cfg) ){
      $this->ctrl = $ctrl;
      $this->db = $ctrl->db;
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
    if ( $this->check() && ($data = $this->db->rselect($this->jtable, [], ['id' => $id])) ){
      $data['cfg'] = json_decode($data['cfg'], 1);
      return $data;
    }
    return false;
  }

  public function get_cron($id){
    if ( $this->check() ){
      $data = $this->db->rselect($this->table, [], ['id' => $id]);
      $data['cfg'] = json_decode($data['cfg'], 1);
      return $data;
    }
  }

  public function start($id_cron){
    if ( $this->check() ) {
      $cron = $this->get_cron($id_cron);
      $start = date('Y-m-d H:i:s');
      if ($cron && $this->db->insert($this->jtable, [
          'id_cron' => $id_cron,
          'start' => $start
        ])
      ) {
        $this->db->update($this->table, [
          'prev' => $start,
          'next' => date('Y-m-d H:i:s', $this->get_next_date($cron['cfg']['frequency']))
        ], [
          'id' => $id_cron
        ]);
        $this->timer->start('cron_' . $id_cron);
        return $this->db->last_id();
      }
    }
    return false;
  }

  public function finish($id, $res = ''){
    if ( ($article = $this->get_article($id)) &&
            ($cron = $this->get_cron($article['id_cron'])) ){
      $time = $this->timer->has_started('cron_'.$article['id_cron']) ? $this->timer->stop('cron_'.$article['id_cron']): 0;
      if ( !empty($res) ){
        \bbn\x::hdump($id, $res);
        $this->db->update($this->jtable, [
          'finish' => date('Y-m-d H:i:s'),
          'duration' => $time,
          'res' => $res
        ], [
          'id' => $id
        ]);
      }
      else{
        $this->db->delete($this->jtable, ['id' => $id]);
        $prev = $this->db->rselect($this->jtable, ['res', 'id'], ['id_cron' => $article['id_cron']], ['finish' => 'DESC']);
        if ( $prev['res'] === 'error' ){
          $this->db->update($this->jtable, ['res' => 'Restarted after error'], ['id' => $prev['id']]);
        }
      }
      return $time;
    }
    return false;
  }

  public function get_next_date($frequency, $tm = false){
    if ( is_string($frequency) && (strlen($frequency) >= 2) ){
      if ( !$tm ){
        $tm = time();
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
          $r = $tm + ($unit * $number);
        }
        if ( $letter === 'm' ){
          $r = mktime(date('H', $tm), date('i', $tm), date('s', $tm), date('n', $tm)+$number, date('j', $tm), date('Y', $tm));
        }
        if ( $letter === 'y' ){
          $r = mktime(date('H', $tm), date('i', $tm), date('s', $tm), date('n', $tm)+$number, date('j', $tm), date('Y', $tm));
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
        WHERE `active` = 1
        AND `next` < ?".
        ( is_int($id_cron) ? " AND `id` = $id_cron" : "" )."
        ORDER BY `priority` ASC, `next` ASC
        LIMIT 1",
        date('Y-m-d H:i:s'))) ){
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
      return $d;
    }
  }

  public function run($id_cron){
    if ( $cron = $this->get_next($id_cron) ){
      if ( $this->is_running($cron['id']) ){
        if ( $this->is_timeout($cron['id']) ){
          $r = $this->get_runner($cron['id']);
          $this->finish($r['id'], "error");
          if ( isset($this->mail) )
            $this->mail->send([
            'to' => BBN_ADMIN_EMAIL,
            'subject' => 'CRON FAILURE',
            'text' => "Id: ".$cron['id']." - File: ".$cron['file']." - Desc: ".$cron['description']." - Start: ".$r['start']." - Server: ".( BBN_IS_DEV ? 'Dev' : 'Prod')
          ]);
        }
      }
      else {
        $id = $this->start($cron['id']);
        $output = $this->_exec($cron['file'], $cron['cfg']);
        $time = $this->finish($id, $output);
        \bbn\x::dump("Execution of ".$cron['file']." (Journal ID: $id) in $time secs", $output);
        return $time;
      }
    }
    return false;
  }

  public function run_all(){
    $time = 0;
    $done = [];
    while ( ($time < $this->timeout) &&
           ($cron = $this->get_next()) &&
           !in_array($cron['id'], $done) ){
      if ( $ctx = $this->run($cron['id']) ){
        $time += $ctx;
      }
      array_push($done, $cron['id']);
    }
    return $time;
  }

  public function is_timeout($id_cron){
    if ( $this->check() && is_int($id_cron) && $this->is_running($id_cron)){
      $c = $this->get_cron($id_cron);
      $r = $this->get_runner($id_cron);
      if ( (strtotime($r['start']) + $c['cfg']['timeout']) < time() ){
        return true;
      }
    }
    return false;
  }

  private function _exec($file, $data=[]){
    $this->ctrl->data = $data;
    $this->obj = new \stdClass();
    ob_start();
    $this->ctrl->incl($file, false);
    $output = ob_get_contents();
    ob_end_clean();
    return $output;
  }
}
