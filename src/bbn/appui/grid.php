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

class grid extends bbn\models\cls\basic
{

	private
    /* @var db The DB connection */
    $db = false,
    $start,
    $limit,
    $order,
    $filters = [],
    $query,
    $table,
    $group_by,
    $count,
    $num = 0,
    $prefilters = [];


  public function __construct(bbn\db $db, array $post, $cfg){
    $this->db = $db;
    if ( is_array($post) ){
      $this->start = isset($post['start']) &&
              bbn\str::is_number($post['start']) ?
                      $post['start'] : 0;

      $this->limit = ( isset($post['limit']) &&
              bbn\str::is_number($post['limit']) ) ?
                      $post['limit'] : 20;

      $this->filters = $post['filters'] ?: [];
      $this->order = $post['order'] ?: [];
      if ( is_string($cfg) ){
        // If there is a space it is a query
        if ( strrpos($cfg, ' ') ){
          $this->query = $cfg;
        }
        else{
          if ( $this->query = $this->db->get_select($cfg) ){
            $this->table = $cfg;
          }
        }
      }
      else if ( is_array($cfg) ){
        if ( !empty($cfg['query']) ){
          $this->query = $cfg['query'];
        }
        if ( !empty($cfg['table']) ){
          $this->table = $cfg['table'];
          if ( !isset($cfg['fields']) ){
            $cols = array_keys($this->db->get_columns($this->table));
            $table = $this->table;
            $this->fields = array_combine($cols, array_map(function($a) use ($table){
              return $table.'.'.$a;
            }, $cols));
          }
          if ( !$this->query ){
            $this->query = $this->db->get_select($this->table, !empty($cfg['columns']) ? $cfg['columns'] : []);
          }
        }
        if ( $this->query ){
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
    }
  }

  public function get_select(){
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
    if ( isset($select) ){
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
    if ( $sql = $this->get_select() ){
      $q = $this->db->query($sql);
      return $q->get_rows();
    }
  }

  public function get_total($force = false){
    if ( $this->num && !$force ){
      return $this->num;
    }
    if ( $count = $this->get_count() ){
      $this->num = $this->db->get_one($count);
      return $this->num;
    }
    return 0;
  }

  public function get_datatable(){
    $r = [
      'data' => [],
      'total' => 0,
      'success' => true,
      'error' => false
    ];
    if ( $this->db->check() && ($total = $this->get_total()) ){
      $r['total'] = $total;
      $r['data'] = $this->get_data();
    }
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

  public function get_query(){
    return $this->query;
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
    if ( $this->check() && isset($this->cfg['filter']) ){
      return $this->filter($this->cfg['filter']);
    }
    return '';
  }

  public function get_field($f, $array = false){
    if ( is_array($f) && isset($f['field']) ){
      $f = $f['field'];
    }
    if ( empty($this->fields) || $array ){
      return strpos($f, '.') ? $this->db->col_full_name($f, null, $array ? false : true) : $this->db->col_simple_name($f, $array ? false : true);
    }
    else if ( isset($this->fields[$f]) ){
      return strpos($this->fields[$f], '.') ? $this->db->col_full_name($this->fields[$f], null, $array ? false : true) : $this->db->col_simple_name($this->fields[$f], $array ? false : true);
    }
    if (
      is_string($f) && (
        in_array($f, $this->fields, true) ||
        in_array($f, $this->additional_fields)
      )
    ){
      return (
        $this->table &&
        !in_array($f, $this->additional_fields)
      ) ?
        $this->db->col_full_name($f, $this->table, 1) :
        (
          strpos($f, '.') ?
            $this->db->col_full_name($f, null, 1) :
            $this->db->col_simple_name($f, 1)
        );
    }
    return false;
  }

  public function filter(array $filters = null, $array = false){
    $res = $array ? [] : '';
    if ( null === $filters ){
      $num1 = empty($this->prefilters) ? 0 : count($this->prefilters['conditions']);
      $num2 = empty($this->filters) ? 0 : count($this->filters['conditions']);
      \bbn\x::log('start', 'filters');
      if ( ($num1 || $num2) && $this->check() ){
        \bbn\x::log('num1 or num2', 'filters');
        if ( $num1 && $num2 ){
          \bbn\x::log('num1 and num2', 'filters');
          $filters = [
            'logic' => 'AND',
            'conditions' => [$this->prefilters, $this->filters]
          ];
        }
        else{
          \bbn\x::log('num1 or num2 bis', 'filters');
          $filters = $num1 ? $this->prefilters : $this->filters;
        }
      }
    }
    if ( !empty($filters['conditions']) && $this->check() ){
      $logic = isset($filters['logic']) && ($filters['logic'] === 'OR') ? 'OR' : 'AND';
      foreach ( $filters['conditions'] as $f ){
        $pre = empty($res) ? " ( " : " $logic ";
        if ( isset($f['logic']) ){
          if ( $array ){
            $res = \bbn\x::merge_arrays($res, $this->filter($f, true));
          }
          else{
            $res .= $pre.$this->filter($f);
          }
        }
        else if ( $field = $this->get_field($f, $array) ){
          if ( !$array ){
            $res .= $pre.$field." ";
          }
          switch ( $f['operator'] ){
            case 'eq':
              if ( $array ){
                array_push($res, [$field, bbn\str::is_number($f['value']) ? ' = ' : 'LIKE', $f['value']]);
              }
              else{
                $res .= bbn\str::is_number($f['value']) ? "= ".$f['value'] : "LIKE '".$this->db->escape_value($f['value'])."'";
              }
              break;

            case 'neq':
              if ( $array ){
                array_push($res, [$field, bbn\str::is_number($f['value']) ? '!=' : 'NOT LIKE', $f['value']]);
              }
              else{
                $res .= bbn\str::is_number($f['value']) ? "!= ".$f['value'] : "NOT LIKE '".$this->db->escape_value($f['value'])."'";
              }
              break;

            case 'startswith':
              if ( $array ){
                array_push($res, [$field, 'LIKE', $f['value'].'%']);
              }
              else{
                $res .= "LIKE '".$this->db->escape_value($f['value'])."%'";
              }
              break;

            case 'endswith':
              if ( $array ){
                array_push($res, [$field, 'LIKE', '%'.$f['value']]);
              }
              else{
                $res .= "LIKE '%".$this->db->escape_value($f['value'])."'";
              }
              break;

            case 'gte':
              if ( $array ){
                array_push($res, [$field, '>=', $f['value']]);
              }
              else{
                $res .= ">= '".$this->db->escape_value($f['value'])."'";
              }
              break;

            case 'gt':
              if ( $array ){
                array_push($res, [$field, '>', $f['value']]);
              }
              else{
                $res .= "> '".$this->db->escape_value($f['value'])."'";
              }
              break;

            case 'lte':
              if ( $array ){
                array_push($res, [$field, '<=', $f['value']]);
              }
              else{
                $res .= "<= '".$this->db->escape_value($f['value'])."'";
              }
              break;

            case 'lt':
              if ( $array ){
                array_push($res, [$field, '<', $f['value']]);
              }
              else{
                $res .= "< '".$this->db->escape_value($f['value'])."'";
              }
              break;

            case 'isnull':
              if ( $array ){
              }
              else{
                $res .= "IS NULL";
              }
              break;

            case 'isnotnull':
              if ( $array ){
              }
              else{
                $res .= "IS NOT NULL";
              }
              break;

            case 'isempty':
              if ( $array ){
                array_push($res, [$field, 'LIKE', '']);
              }
              else{
                $res .= "LIKE ''";
              }
              break;

            case 'isnotempty':
              if ( $array ){
                array_push($res, [$field, 'NOT LIKE', '']);
              }
              else{
                $res .= "NOT LIKE ''";
              }
              break;

            case 'doesnotcontain':
              if ( $array ){
                array_push($res, [$field, 'NOT LIKE', '%'.$f['value'].'%']);
              }
              else{
                $res .= "NOT LIKE '%".$this->db->escape_value($f['value'])."%'";
              }
              break;

            case 'contains':
            default:
              if ( $array ){
                array_push($res, [$field, 'LIKE', '%'.$f['value'].'%']);
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
