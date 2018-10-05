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
  /**
   * @var array The definitive DB config array
   */
  protected $cfg;
  /**
   * @var array The original DB config array
   */
  protected $original_cfg;
  /**
   * @var array The DB config for the count - if the count param is not supplied
   */
  protected $count_cfg;

  /**
   * @var bool|mixed Is an observer called
   */
  protected $observer = false;

  /**
   * @var string The SQL query when given as parameter
   */
  protected $query;

  /**
   * @var string The SQL query for counting when given as parameter
   */
  protected $count;

  /**
   * @var int The total number of rows (without limit)
   */
  private $num = 0;

  /**
   * @var string The cache UID string
   */
  private $cache_uid;

  /**
   * @var float The last time that the data query took
   */
  private $query_time = 0;

  /**
   * @var string The timer object
   */
  private $chrono;
  /**
   * Grid constructor.
   *
   * @param bbn\db $db The database connection
   * @param array $post Mandatory configuration sent by the table component (client side)
   * @param string|array $cfg Original table configuration (server side)
   */
  public function __construct(bbn\db $db, array $post, $cfg){
    // We inherit db and cacher properties
    parent::__construct($db);
    // Simple configuration using just a string with the table
    if ( \is_string($cfg) ){
      $cfg = [
        'tables' => [$cfg]
      ];
    }
    if ( \is_array($cfg) && $this->db->check() ){
      // Preparing a classic config array for DB
      $db_cfg = [
        'tables' => $cfg['tables'] ?? ($cfg['table'] ?? null),
        'fields' => $cfg['fields'] ? (array)$cfg['fields'] : [],
        'order' => $post['order'] ?? ($cfg['order'] ?? []),
        'join' => $cfg['join'] ?? [],
        'group_by' => $cfg['group_by'] ?? [],
        'having' => $cfg['having'] ?? [],
        'limit' => $post['limit'] ?? ($cfg['limit'] ?? 20),
        'start' => $post['start'] ?? 0,
        'where' => !empty($post['filters']) && !empty($post['filters']['conditions']) ? $post['filters'] : []
      ];
      // Adding all the fields if fields is empty
      if ( empty($db_cfg['fields']) ){
        foreach ( array_unique(array_values($db_cfg['tables'])) as $t ){
          foreach ( $this->db->get_fields_list($t) as $f ){
            if ( !\in_array($f, $cfg['fields'], true) ){
              $db_cfg['fields'][] = $f;
            }
          }
        }
      }
      // For the server config both properties where and filters are accepted (backward compatibility)
      if ( empty($cfg['filters']) && !empty($cfg['where']) ){
        $cfg['filters'] = $cfg['where'];
      }
      // The (pre)filters set server-side are mandatory and are added to the client-side filters if any
      if ( !empty($cfg['filters']) ){
        $prefilters = isset($cfg['filters']['conditions']) ? $cfg['filters'] : [
          'logic' => 'AND',
          'conditions' => $cfg['filters']
        ];
        // They either become the where or are added as a new root condition
        $db_cfg['where'] = empty($db_cfg['where']) ? $prefilters : [
          'logic' => 'AND',
          'conditions' => [
            $db_cfg['where'],
            $prefilters
          ]
        ];
      }
      $this->cfg = $this->db->process_cfg($db_cfg);
      $this->original_cfg = $db_cfg;
      if ( !empty($cfg['query']) ){
        $this->query = $cfg['query'];
      }
      // A query must exist, custom or generated
      if ( $this->check() ){
        if ( array_key_exists('observer', $cfg) && isset($cfg['observer']['request']) ){
          $this->observer = $cfg['observer'];
        }
        if ( !empty($cfg['count']) ){
          $this->count = $cfg['count'];
        }
        else{
          $db_cfg['count'] = true;
          $this->count_cfg = $db_cfg;
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

  public function get_query(): ?string
  {
    if ( $this->db->check() ){
      if ( $this->query ){
        return $this->query.PHP_EOL.
          $this->db->get_where($this->cfg).
          $this->db->get_group_by($this->cfg).
          $this->db->get_order($this->cfg).
          $this->db->get_limit($this->cfg);
      }
      return $this->cfg['sql'];
    }
    return null;
  }

  public function get_data(): ?array
  {
    if ( $this->check() ){
      $this->chrono->start();
      if ( $this->query ){
        $this->sql = $this->get_query();
        $q = $this->db->query($this->sql, $this->cfg['values'] ?: []);
        $rows = $q->get_rows();
      }
      else {
        $rows = $this->db->rselect_all($this->cfg);
        $this->sql = $this->cfg['sql'];
      }
      $this->query_time = $this->chrono->measure();
      $this->chrono->stop();
      return $rows;
    }
    return null;
  }

  public function get_total($force = false) : ?int
  {
    if ( $this->num && !$force ){
      return $this->num;
    }
    if ( !$force && ($cache = $this->get_cache()) ){
      $this->count_time = $cache['time'];
      $this->num = $cache['num'];
      return $this->num;
    }
    if ( $this->count ){
      $this->chrono->start();
      $this->num = $this->db->get_one(
        $this->count,
        $this->cfg['values'] ?: []
      );
      $this->count_time = $this->chrono->measure();
      $this->chrono->stop();
      $this->set_cache([
        'num' => $this->num,
        'time' => $this->count_time
      ]);
      return $this->num ?: 0;
    }
    else if ( $this->count_cfg ){
      $this->chrono->start();
      $this->num = $this->db->select_one($this->count_cfg);
      $this->count_time = $this->chrono->measure();
      $this->chrono->stop();
      $this->set_cache([
        'num' => $this->num,
        'time' => $this->count_time
      ]);
      return $this->num ?: 0;
    }
    return null;
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

  public function get_datatable(): array
  {
    $r = [
      'data' => [],
      'total' => 0,
      'success' => true,
      'error' => false,
      'time' => []
    ];
    if ( $this->check() ){
      if ( $total = $this->get_total() ){
        if ( $this->cfg['start'] ){
          $r['start'] = $this->cfg['start'];
        }
        if ( $this->cfg['limit'] ){
          $r['limit'] = $this->cfg['limit'];
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
      $r['query'] = $this->db->last();
    }
    if ( !$this->db->check() ){
      $r['success'] = false;
      $r['error'] = $this->db->last_error;
    }
    return $r;
  }

  public function check(): bool
  {
    return $this->db->check() && ($this->query || $this->cfg['sql']);
  }

  public function get_start(): int
  {
    return $this->cfg['start'];
  }

  public function get_limit(): int
  {
    return $this->cfg['limit'];
  }

  public function get_cfg(): array
  {
    return $this->cfg;
  }

}