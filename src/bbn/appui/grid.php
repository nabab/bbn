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
    $start,
    $limit,
    $order,
    $filters = [],
    $observer = false,
    $query,
    $table,
    $group_by,
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
    // Mandatory configuration coming from the table component
    $this->start = isset($post['start']) &&
            bbn\str::is_number($post['start']) ?
                    $post['start'] : 0;

    $this->limit = ( isset($post['limit']) &&
            bbn\str::is_number($post['limit']) ) ?
                    $post['limit'] : 20;

    $this->filters = $post['filters'] ?? [];
    $this->order = $post['order'] ?? ($cfg['order'] ?? []);
    // Simple configuration using a string
    if ( \is_string($cfg) ){
      // If there is a space it is a query
      if ( strrpos($cfg, ' ') ){
        $this->query = $cfg;
      }
      // Otherwise it is a table
      else{
        if ( $this->query = $this->db->get_query($cfg) ){
          $this->table = $cfg;
        }
      }
    }
    // Full configuration
    else if ( \is_array($cfg) ){
      if ( !empty($cfg['query']) ){
        $this->query = $cfg['query'];
      }
      if ( !empty($cfg['table']) ){
        $this->table = $cfg['table'];
        if ( empty($cfg['fields']) ){
          $cols = array_keys($this->db->get_columns($this->table));
          $table = $this->table;
          $this->fields = array_combine($cols, array_map(function($a) use ($table){
            return $table.'.'.$a;
          }, $cols));
        }
        else{
          $this->fields = $cfg['fields'];
        }
        if ( !$this->query ){
          $this->query = $this->db->get_query($this->table, !empty($cfg['columns']) ? $cfg['columns'] : []);
          if ( $i = strpos($this->query, 'WHERE 1') ){
            $this->query = substr($this->query, 0, $i);
          }
        }
      }
      if ( $this->query ){
        if ( isset($cfg['observer'], $cfg['observer']['request']) ){
          $this->observer = $cfg['observer'];
        }
        // Additional fields (not in the result but accepted for filtering and ordering
        if ( isset($cfg['extra_fields']) ){
          $this->extra_fields = $cfg['extra_fields'];
        }
        // Additional filters
        if ( !empty($cfg['filters']) ){
          $this->prefilters = isset($cfg['filters']['logic']) ? $cfg['filters'] : [
            'logic' => 'AND',
            'conditions' => $cfg['filters']
          ];
          if ( \bbn\x::is_assoc($this->prefilters['conditions']) ){
            $this->prefilters['conditions'] = [];
            foreach ( $cfg['filters'] as $col => $val ){
              if ( $val === null ){
                $this->prefilters['conditions'][] = [
                  'field' => $col,
                  'operator' => 'isnull'
                ];
              }
              else{
                $this->prefilters['conditions'][] = [
                  'field' => $col,
                  'operator' => 'eq',
                  'value' => $val
                ];
              }
            }
          }
        }
        if ( !empty($cfg['group_by']) ){
          $this->group_by = $cfg['group_by'];
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
      'filters' => $this->filters,
      'prefilters' => $this->prefilters,
      'query' => $this->query,
      'table' => $this->table,
      'group_by' => $this->group_by,
      'order' => $this->order,
      'count' => $this->count
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

  public function get_query(){
    if ( $this->check() ){
      $select = $this->query;
      if ( $where = $this->filter() ){
        $select .= ' WHERE '.$where;
      }
      if ( $this->group_by ){
        $select .= ' GROUP BY '.$this->group_by;
      }
      if ( $order = $this->order_string() ){
        $select .= ' ORDER BY '.$order;
      }
      $select .= ' LIMIT '.$this->start.', '.$this->limit;
      return $select;
    }
    return false;
  }

  public function get_count(){
    if ( $this->count ){
      $select = $this->count;
    }
    else if ( $this->table ){
      $select = 'SELECT COUNT(*) FROM '.$this->db->tsn($this->table, true);
    }
    if ( !empty($select) ){
      if ( $where = $this->filter() ){
        $select .= ' WHERE '.$where;
      }
      /** @todo Group by is only applied to a non given count query, is it ok?? */
      if ( !$this->count && $this->group_by ){
        $select .= ' GROUP BY '.$this->group_by;
      }
      return $select;
    }
    return false;
  }

  public function get_data(){
    if ( $sql = $this->get_query() ){
      $this->chrono->start();
      $q = $this->db->query($sql);
      $rows = $q->get_rows();
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
    if ( $count = $this->get_count() ){
      $this->num = $this->db->get_one($count);
      $this->count_time = $this->chrono->measure();
      $this->chrono->stop();
      $this->set_cache([
        'num' => $this->num,
        'time' => $this->count_time
      ]);
      return $this->num;
    }
    return 0;
  }

  public function get_observer():? array
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
    $r['query'] = $this->get_query();
    $r['count'] = $this->get_count();
    if ( !$this->db->check() ){
      $r['success'] = false;
      $r['error'] = $this->db->last_error;
    }
    return $r;
  }

  public function check()
  {
    return null !== $this->query;
  }

  public function get_start()
  {
    return $this->start;
  }

  public function get_limit()
  {
    return $this->limit;
  }

  public function where()
  {
    if ( isset($this->cfg['filter']) && $this->check() ){
      return $this->filter($this->cfg['filter']);
    }
    return '';
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

  public function filter(array $filters = null, $array = false){
    /** @var array|string $res */
    $res = $array ? [] : '';
    if ( null === $filters ){
      $num1 = empty($this->prefilters) ? 0 : \count($this->prefilters['conditions']);
      $num2 = empty($this->filters) ? 0 : \count($this->filters['conditions']);
      if ( $num1 ){
        $prefilters = $this->prefilters;
        array_walk($prefilters['conditions'], function(&$a){
          $a['prefilter'] = true;
        });
      }
      if ( ($num1 || $num2) && $this->check() ){
        if ( $num1 && $num2 ){
          $filters = [
            'logic' => 'AND',
            'conditions' => [$prefilters, $this->filters]
          ];
        }
        else{
          $filters = $num1 ? $prefilters : $this->filters;
        }
      }
    }
    if ( !empty($filters['conditions']) && $this->check() ){
      $logic = isset($filters['logic']) && ($filters['logic'] === 'OR') ? 'OR' : 'AND';
      foreach ( $filters['conditions'] as $f ){
        if ( isset($f['logic']) && !empty($f['conditions']) ){
          $pre = empty($res) ? " ( " : " $logic ";
          if ( $array ){
            $res = bbn\x::merge_arrays($res, $this->filter($f, true));
          }
          else if ( $tmp = $this->filter($f) ){
            $res .= $pre.$tmp;
          }
        }
        else if ( isset($f['operator']) && ($field = $this->get_field($f, $array)) ){
          $pre = empty($res) ? " ( " : " $logic ";
          if ( !$array ){
            $res .= $pre.$field." ";
          }
          if ( !array_key_exists('value', $f) ){
            $f['value'] = false;
          }
          $is_number = bbn\str::is_number($f['value']);
          $is_uid = false;
          if ( !$is_number && bbn\str::is_uid($f['value']) ){
            $is_uid = true;
          }
          switch ( $f['operator'] ){
            case 'eq':
              if ( $array ){
                $res[] = [$field, $is_number || $is_uid ? '=' : 'LIKE', $f['value']];
              }
              else{
                if ( isset($f['exp']) ){
                  $res .= '= '.$f['exp'];
                }
                else if ( $is_number ){
                  $res .= '= '.$f['value'];
                }
                else if ( $is_uid ){
                  $res .= "= UNHEX('".$f['value']."')";
                }
                else{
                  $res .= "LIKE '".$this->db->escape_value($f['value'])."'";
                }
              }
              break;

            case 'neq':
              if ( $array ){
                $res[] = [$field, $is_number || $is_uid ? '!=' : 'NOT LIKE', $f['value']];
              }
              else{
                if ( isset($f['exp']) ){
                  $res .= '!= '.$f['exp'];
                }
                else if ( $is_number ){
                  $res .= '!= '.$f['value'];
                }
                else if ( $is_uid ){
                  $res .= "!= UNHEX('".$f['value']."')";
                }
                else{
                  $res .= "NOT LIKE '".$this->db->escape_value($f['value'])."'";
                }
              }
              break;

            case 'startswith':
              if ( $array ){
                $res[] = [$field, 'LIKE', $f['value'].'%'];
              }
              else{
                $res .= "LIKE '".$this->db->escape_value($f['value'])."%'";
              }
              break;

            case 'endswith':
              if ( $array ){
                $res[] = [$field, 'LIKE', '%'.$f['value']];
              }
              else{
                $res .= "LIKE '%".$this->db->escape_value($f['value'])."'";
              }
              break;

            case 'gte':
              if ( $array ){
                $res[] = [$field, '>=', $f['value']];
              }
              else if ( isset($f['exp']) ){
                $res .= '>= '.$f['exp'];
              }
              else if ( $is_number ){
                $res .= '>= '.$f['value'];
              }
              else{
                $res .= ">= '".$this->db->escape_value($f['value'])."'";
              }
              break;

            case 'gt':
              if ( $array ){
                $res[] = [$field, '>', $f['value']];
              }
              else if ( isset($f['exp']) ){
                $res .= '> '.$f['exp'];
              }
              else if ( $is_number ){
                $res .= '> '.$f['value'];
              }
              else{
                $res .= "> '".$this->db->escape_value($f['value'])."'";
              }
              break;

            case 'lte':
              if ( $array ){
                $res[] = [$field, '<=', $f['value']];
              }
              else if ( isset($f['exp']) ){
                $res .= '<= '.$f['exp'];
              }
              else if ( $is_number ){
                $res .= '<= '.$f['value'];
              }
              else{
                $res .= "<= '".$this->db->escape_value($f['value'])."'";
              }
              break;

            case 'lt':
              if ( $array ){
                $res[] = [$field, '<', $f['value']];
              }
              else if ( isset($f['exp']) ){
                $res .= '< '.$f['exp'];
              }
              else if ( $is_number ){
                $res .= '< '.$f['value'];
              }
              else{
                $res .= "< '".$this->db->escape_value($f['value'])."'";
              }
              break;

              /** @todo Check if it is working with an array */
            case 'isnull':
              if ( $array ){
                $res[] = [$field, 'IS NULL', null];
              }
              else{
                $res .= "IS NULL";
              }
              break;

            case 'isnotnull':
              if ( $array ){
                $res[] = [$field, 'IS NOT NULL', null];
              }
              else{
                $res .= "IS NOT NULL";
              }
              break;

            case 'isempty':
              if ( $array ){
                $res[] = [$field, 'LIKE', ''];
              }
              else{
                $res .= "LIKE ''";
              }
              break;

            case 'isnotempty':
              if ( $array ){
                $res[] = [$field, 'NOT LIKE', ''];
              }
              else{
                $res .= "NOT LIKE ''";
              }
              break;

            case 'doesnotcontain':
              if ( $array ){
                $res[] = [$field, 'NOT LIKE', '%'.$f['value'].'%'];
              }
              else{
                $res .= "NOT LIKE '%".$this->db->escape_value($f['value'])."%'";
              }
              break;

            case 'contains':
            default:
              if ( $array ){
                $res[] = [$field, 'LIKE', '%'.$f['value'].'%'];
              }
              else{
                $res .= "LIKE '%".$this->db->escape_value($f['value'])."%'";
              }
              break;
          }
        }
      }
      if ( !$array && !empty($res) ){
        $res .= " ) ";
      }
    }
    return $res;
  }

  public function order_string(){
    $st = '';
    if ( !empty($this->order) ){
      foreach ( $this->order as $f ){
        if ( $field = $this->get_field($f) ){
          if ( !empty($st) ){
            $st .= ", ";
          }
          $st .= $field." ".( strtolower($f['dir']) === 'desc' ? 'DESC' : 'ASC' );
        }
      }
    }
    return $st;
  }

}