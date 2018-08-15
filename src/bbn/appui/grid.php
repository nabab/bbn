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
use bbn;

class grid extends bbn\models\cls\cache
{
	private
    /* @var db The DB connection */
    $cfg,
    $start,
    $limit,
    $order,
    $filters = [],
    $observer = false,
    $query,
    $table,
    $group_by,
    $join,
    $having,
    $count,
    $num = 0,
    $prefilters = [],
    $fields = [],
    $extra_fields = [],
    $cache_uid,
    $query_time = 0,
    $count_time = 0,
    $chrono,
    $data = [];


  /**
   * grid constructor.
   * @param bbn\db $db
   * @param array $post Mandatory configuration from the table component
   * @param $cfg
   */
  public function __construct(bbn\db $db, array $post, $cfg){
    parent::__construct($db);
    // Simple configuration using just a string with the table
    if ( \is_string($cfg) ){
      $cfg = ['tables' => [$cfg]];
    }
    if ( \is_array($cfg) && $this->db->check() ){
      $db_cfg = [
        'tables' => $cfg['tables'] ?? ($cfg['table'] ?? null),
        'fields' => $cfg['fields'] ?? [],
        'order' => $post['order'] ?? ($cfg['order'] ?? []),
        'join' => $cfg['join'] ?? [],
        'group_by' => $cfg['group_by'] ?? [],
        'having' => $cfg['having'] ?? [],
        'limit' => $post['limit'] ?? ($cfg['limit'] ?? 20),
        'start' => $post['start'] ?? 0,
        'where' => !empty($post['filters']) && !empty($post['filters']['conditions']) ?? []
      ];
      if ( !empty($cfg['filters']) ){
        $this->prefilters = isset($cfg['filters']['conditions']) ? $cfg['filters'] : [
          'logic' => 'AND',
          'conditions' => $cfg['filters']
        ];
        $db_cfg['where'] = empty($db_cfg['where']) ? $this->prefilters : [
          'logic' => 'AND',
          'conditions' => [
            $db_cfg['where'],
            $this->prefilters
          ]
        ];
      }

      $this->cfg = $this->db->process_cfg($db_cfg);
      // Mandatory configuration coming from the table component
      $this->start = $this->cfg['start'];
      $this->limit = $this->cfg['limit'];
      $this->filters = $this->cfg['filters'];
      $this->order = $this->cfg['order'];
      $this->group_by = $this->cfg['group_by'];
      $this->join = $this->cfg['join'];
      $this->having = $this->cfg['having'];
      if ( !empty($cfg['query']) ){
        $this->query = $cfg['query'];
      }
      else if ( !empty($this->cfg['sql']) ){
        $this->query = $this->get_full_select($this->cfg);
      }
      if ( !empty($this->query) ){
        if ( empty($cfg['fields']) ){
          $this->fields = $this->db->get_fields_list($this->cfg['tables']);
        }
        else{
          $this->fields = $cfg['fields'];
        }
        if ( array_key_exists('observer', $cfg) && isset($cfg['observer']['request']) ){
          $this->observer = $cfg['observer'];
        }
        // Additional fields (not in the result but accepted for filtering and ordering
        if ( isset($cfg['extra_fields']) ){
          $this->extra_fields = $cfg['extra_fields'];
        }
        if ( !empty($cfg['count']) ){
          $this->count = $cfg['count'];
        }
        if ( !empty($cfg['num']) ){
          $this->num = $cfg['num'];
        }
      }
    }
    $this->cache_uid = md5(serialize([
      'tables' => $this->cfg['tables'],
      'fields' => $this->cfg['fields'],
      'order' => $this->cfg['order'],
      'values' => $this->cfg['values'],
      'join' => $this->cfg['join'],
      'group_by' => $this->cfg['group_by'],
      'having' => $this->cfg['having'],
      'limit' => $this->cfg['limit'],
      'start' => $this->cfg['start'],
      'filters' => $this->cfg['filters']
    ]));
    $this->chrono = new bbn\util\timer();
  }

  protected function get_cache(){
    return parent::cache_get($this->cache_uid);
  }

  protected function set_cache($data){
    $max = 600;
    if ( isset($data['time']) ){
      if ( $data['time'] < 0.01 ){
        $ttl = 3;
      }
      else if ( $data['time'] < 0.1 ){
        $ttl = 10;
      }
      else if ( $data['time'] < 0.5 ){
        $ttl = 30;
      }
      else {
        $ttl = ceil($data['time'] * 60);
      }
      if ( $ttl > $max ){
        $ttl = $max;
      }
      $this->cache_set($this->cache_uid, '', $data, $ttl);
    }
    return $this;
  }

  public function flush(){
    $this->data = [];
  }

  public function get_full_select(): ?string
  {
    if ( $this->db->check() ){
      return
        $this->db->get_select($this->cfg).
        $this->db->get_join($this->cfg).
        $this->db->get_where($this->cfg).
        $this->db->get_group_by($this->cfg).
        $this->db->get_order($this->cfg).
        $this->db->get_limit($this->cfg);
    }
    return null;
  }

  public function get_data(){
    if ( $sql = $this->get_full_select() ){
      $this->chrono->start();
      if ( $this->query === $this->cfg['sql'] ){
        $rows = $this->db->rselect_all($this->cfg);
      }
      else{
        $q = $this->db->query($this->query);
        $rows = $q->get_rows();
      }
      $this->query_time = $this->chrono->measure();
      $this->chrono->stop();
      return $rows;
    }
  }

  public function get_total($force = false){
    if ( $this->num && !$force ){
      return $this->num;
    }
    if ( !$force && ($cache = $this->get_cache()) ){
      $this->count_time = $cache['time'];
      $this->num = $cache['num'];
      return $this->num;
    }
    $this->chrono->start();
    if ( $this->count ){
      $this->num = $this->db->get_one($this->count);
    }
    else if ( $this->cfg['sql'] ){
      $this->num = $this->db->count($this->cfg);
    }
    $this->count_time = $this->chrono->measure();
    $this->chrono->stop();
    $this->set_cache([
      'num' => $this->num,
      'time' => $this->count_time
    ]);
    return $this->num ?: 0;
  }

  public function get_observer(): ?array
  {
    if ( $this->observer ){
      $obs = new bbn\appui\observer($this->db);
      if ( $id_obs = $obs->add($this->observer) ){
        return [
          'id' => $id_obs,
          'value' => $obs->get_result($id_obs)
        ];
      }
      return null;
    }
  }

  public function get_datatable(){
    $r = [
      'data' => [],
      'total' => 0,
      'success' => true,
      'error' => false,
      'time' => []
    ];
    if ( $this->db->check() && ($total = $this->get_total()) ){
      if ( $this->start ){
        $r['start'] = $this->start;
      }
      if ( $this->limit ){
        $r['limit'] = $this->limit;
      }
      if ( $this->observer ){
        $r['observer'] = $this->get_observer();
      }
      $r['total'] = $total;
      $r['data'] = $this->get_data();
      $r['time'] = [
        'query' => $this->query_time,
        'count' => $this->count_time
      ];

    }
    $r['query'] = $this->get_full_select();
    if ( !$this->db->check() ){
      $r['success'] = false;
      $r['error'] = $this->db->last_error;
    }
    return $r;
  }

  public function check()
  {
    return $this->db->check() && (null !== $this->query);
  }

  public function get_start()
  {
    return $this->start;
  }

  public function get_limit()
  {
    return $this->limit;
  }

  public function get_field($f, $array = false){
    if ( \is_array($f) && isset($f['field']) ){
      if ( !empty($f['prefilter']) ){
        return $f['field'];
      }
      $f = $f['field'];
    }
    if ( empty($this->fields) || $array ){
      return strpos($f, '.') ? $this->db->col_full_name($f, null, $array ? false : true) : $this->db->col_simple_name($f, $array ? false : true);
    }
    else if ( isset($this->fields[$f]) ){
      return $this->fields[$f];
    }
    if ( isset($this->extra_fields[$f]) ){
      return $this->extra_fields[$f];
    }
    return false;
  }

}