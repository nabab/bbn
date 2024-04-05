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

namespace bbn\Appui;
use bbn;
use bbn\X;
use bbn\Str;

class Grid extends bbn\Models\Cls\Cache
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
   * @var float The last time that the count query took place
   */
  private $count_time = 0;

  /**
   * @var float The last time that the data query took place
   */
  private $query_time = 0;

  /**
   * @var bbn\Util\Timer The timer object
   */
  private $chrono;

  /**
   * @var array
   */
  private $excel = [];

  public string $sql;

  /**
   * Grid constructor.
   *
   * @param bbn\Db $db The database connection
   * @param array $post Mandatory configuration sent by the table component (client side)
   * @param string|array $cfg Original table configuration (server side)
   */
  public function __construct(bbn\Db $db, array $post, $cfg){

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
        'tables' => $cfg['tables'] ?? ($cfg['table'] ? (\is_string($cfg['table']) ? [$cfg['table']] : $cfg['table']) : null),
        'fields' => !empty($cfg['fields']) ? (array)$cfg['fields'] : [],
        'ofields' => !empty($cfg['ofields']) ? (array)$cfg['ofields'] : [],
        'order' => $post['order'] ?? ($cfg['order'] ?? []),
        'join' => $cfg['join'] ?? ($post['join'] ?? []),
        'group_by' => $cfg['group_by'] ?? [],
        'having' => !empty($post['having']) ? $post['having'] : [],
        'limit' => $post['limit'] ?? ($cfg['limit'] ?? 20),
        'start' => $post['start'] ?? 0,
        'where' => !empty($post['filters']) ? $post['filters'] : []
      ];
      if ( !empty($post['excel']) ){
        $this->excel = $post['excel'];
        if ( !empty($post['fields']) ){
          $fields = $db_cfg['fields'];
          $link = [];
          $db_cfg['fields'] = [];
          foreach ( $fields as $a => $f ){
            $field = is_string($a) ? $a : $this->db->colSimpleName($f);
            if ( in_array($field, $post['fields'], true) ){
              $link[$field] = $a;
            }
          }
          foreach ( $post['fields'] as $f ){
            if ( isset($link[$f], $fields[$link[$f]]) ){
              $db_cfg['fields'][$link[$f]] = $fields[$link[$f]];
            }
          }
        }
        if (
          isset($cfg['map'], $cfg['map']['callable']) &&
          is_callable($cfg['map']['callable'])
        ){
          $this->excel['map'] = $cfg['map'];
        }
      }
      // Adding all the fields if fields is empty
      if ( empty($db_cfg['tables']) ){
        $this->log(['NO TABLES!', $db_cfg]);
      }
      else if ( empty($db_cfg['fields']) ){
        foreach ( array_unique(array_values($db_cfg['tables'])) as $t ){
          foreach ( $this->db->getFieldsList($t) as $f ){
            if ( !\in_array($f, $db_cfg['fields'], true) ){
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
      // The (pre)having set server-side are mandatory and are added to the client-side having if any
      if ( !empty($cfg['having']) ){
        $prehaving = isset($cfg['having']['conditions']) ? $cfg['having'] : [
          'logic' => 'AND',
          'conditions' => $cfg['having']
        ];
        // They either become the where or are added as a new root condition
        $db_cfg['having'] = empty($db_cfg['having']) ? $prehaving : [
          'logic' => 'AND',
          'conditions' => [
            $db_cfg['having'],
            $prehaving
          ]
        ];
      }
      $this->cfg = $this->db->processCfg($db_cfg);
      $this->original_cfg = $db_cfg;
      if ( !empty($cfg['query']) ){
        $this->query = $cfg['query'];
      }

      // A query must exist, custom or generated
      if ( $this->check() ){
        if ( array_key_exists('observer', $cfg) && isset($cfg['observer']['request']) ){
          $this->observer = $cfg['observer'];
        }
        if ( X::hasProp($cfg, 'count') ){
          $this->count = $cfg['count'];
        }
        else{
          $db_cfg['count'] = true;
          $this->count_cfg = $this->db->processCfg($db_cfg);
          //die(X::dump($this->count_cfg));
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
    $this->chrono = new bbn\Util\Timer();
  }

  protected function fixFilters(&$cfg){
    if (!empty($cfg['group_by'])) {
      $having = $cfg['having'] ?: [];

    }
    return $cfg;
  }

  protected function fixPart($conditions, $cfg)
  {

  }

  protected function getCache(){
    return parent::cacheGet($this->cache_uid);
  }

  protected function setCache($data){
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
      $this->cacheSet($this->cache_uid, '', $data, $ttl);
    }
    return $this;
  }

  public function getQuery(): ?string
  {
    if ( $this->db->check() ){
      if ( $this->query ){
        return $this->query.PHP_EOL.
          $this->db->getWhere($this->cfg).
          $this->db->getGroupBy($this->cfg).
          $this->db->getOrder($this->cfg).
          $this->db->getLimit($this->cfg);
      }
      return $this->cfg['sql'];
    }
    return null;
  }

  public function getData(): ?array
  {
    if ( $this->check() ){
      $this->chrono->start();
      if ( $this->query ){
        $this->sql = $this->getQuery();
        $q = $this->db->query($this->sql, \array_map(function($v){
          if (Str::isUid($v)) {
            $v = hex2bin($v);
          }
          return $v;
        }, $this->db->getQueryValues($this->cfg)));
        $rows = $q->getRows();
      }
      else {
        $rows = $this->db->rselectAll($this->cfg);
        $this->sql = $this->cfg['sql'];
      }
      $this->query_time = $this->chrono->measure();
      $this->chrono->stop();
      return $rows;
    }
    return null;
  }

  public function getTotal($force = false) : ?int
  {
    if ( $this->num && !$force ){
      return $this->num;
    }
    /*
    if ( !$force && ($cache = $this->getCache()) ){
      $this->count_time = $cache['time'];
      $this->num = $cache['num'];
      return $this->num;
    }
    */
    if ( $this->count ){
      $this->chrono->start();
      if ( is_string($this->count) ){
        $this->num = $this->db->getOne(
          $this->count.PHP_EOL.
            $this->db->getWhere($this->cfg).
            $this->db->getGroupBy($this->cfg),
            \array_map(function($v){
              if (Str::isUid($v)) {
                $v = hex2bin($v);
              }
              return $v;
            }, $this->db->getQueryValues($this->cfg))
        );
      }
      else if ( is_array($this->count) ){
        $cfg = $this->count;
        $cfg['where'] = $this->cfg['where'];
        $this->num = $this->db->selectOne($cfg);
      }
      elseif (is_int($this->count)) {
        $this->num = $this->count;
      }

      $this->count_time = $this->chrono->measure();
      $this->chrono->stop();
      $this->setCache([
        'num' => $this->num,
        'time' => $this->count_time
      ]);
      return $this->num ?: 0;
    }
    else if ( $this->count_cfg ){
      //X::log($this->count_cfg, 'mirko');
      //die(X::dump($this->count_cfg));
      $this->chrono->start();
      $this->num = $this->db->selectOne($this->count_cfg);
      $this->count_time = $this->chrono->measure();
      $this->chrono->stop();
      $this->setCache([
        'num' => $this->num,
        'time' => $this->count_time
      ]);
      return $this->num ?: 0;
    }
    return null;
  }

  public function getObserver(): ?array
  {
    if ( $this->observer ){
      $obs = new bbn\Appui\Observer($this->db);
      if ( $id_obs = $obs->add($this->observer) ){
        return [
          'id' => $id_obs,
          'value' => $obs->getResult($id_obs)
        ];
      }
      return null;
    }
  }

  public function getDatatable($force = false): array
  {
    $r = [
      'data' => [],
      'total' => 0,
      'success' => true,
      'error' => false,
      'time' => []
    ];
    if ( $this->check() ){
      if ($total = $this->getTotal($force)) {
        if ( $this->cfg['start'] ){
          $r['start'] = $this->cfg['start'];
        }
        if ( $this->cfg['limit'] ){
          $r['limit'] = $this->cfg['limit'];
        }
        if ( $this->observer ){
          $r['observer'] = $this->getObserver();
        }
        $r['total'] = $total;
        $r['data'] = $this->getData();
        $r['time'] = [
          'query' => $this->query_time,
          'count' => $this->count_time
        ];
      }
      //unset($this->count_cfg['where']['conditions'][0]['time']);
      //$this->count_cfg['where']['conditions'][0]['value'] = hex2bin($this->count_cfg['where']['conditions'][0]['value']);
      //die(X::dump($this->db->selectOne($this->count_cfg), $this->db->last(), $this->count_cfg, $this->num, $this->db->last_params));
      if (!defined('BBN_IS_PROD') || (($usr = bbn\User::getInstance()) && $usr->isAdmin())) {
        $r['query'] = $this->db->last();
        $r['queryValues'] = array_map(function($a){
          if (Str::isBuid($a)) {
            return bin2hex($a);
          }

          return $a;
        }, $this->db->getLastValues());
      }
      //die(var_dump($r['query']));
    }
    if ( !$this->db->check() ){
      $r['success'] = false;
      $r['error'] = $this->db->getLastError();
    }
    return $r;
  }

  /**
   * Exports the grid's data or the given data to excel
   * @param array $data
   * @return array
   */
  public function toExcel(array $data = null, array $options = null): array
  {
    $path = X::makeStoragePath(\bbn\Mvc::getUserTmpPath()) . 'export_' . date('d-m-Y_H-i-s') . '.xlsx';
    $cfg = $this->getExcel();
    $dates = array_values(array_filter($cfg['fields'], function($c){
      return empty($c['hidden']) && (($c['type'] === 'date') || ($c['type'] === 'datetime'));
    }));
    $hidden = array_map(function($a) {
      return $a['field'];
    }, X::filter($cfg['fields'], ['hidden' => true]));
    $data = array_map(function($row) use($cfg, $dates, $hidden, $options){
      foreach ( $row as $i => &$r ){
if ($options && isset($options[$i])) {
          $r = X::getField($options[$i], ['value' => $row[$i]], 'text');
        }

        if ( \is_string($r) ){
          $r = strpos($r, '=') === 0 ? ' '.$r : $r;
        }
        if ( (($k = X::find($dates, ['field' => $i])) !== null ) ){
          if ( !empty($dates[$k]['format']) && !empty($r) ){
            $r = date($dates[$k]['format'], strtotime($r));
          }
          $r = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($r);
        }
        if (in_array($i, $hidden)) {
          unset($row[$i]);
        }
      }
      unset($r);

      return $row;
    }, $data ?: ($this->getData() ?: []));
    $cfg['fields'] = array_values(array_filter($cfg['fields'], function($c){
      return empty($c['hidden']);
    }));
    if ( X::toExcel($data, $path, true, $cfg) ){
      return ['file' => $path];
    }
    return ['error' => X::_('Error')];
  }

  /**
   * Gets the excel property
   * @return array
   */
  public function getExcel(): array
  {
   return $this->excel;
  }

  public function check(): bool
  {
    return $this->db->check() && ($this->query || $this->cfg['sql']);
  }

  public function getStart(): int
  {
    return $this->cfg['start'];
  }

  public function getLimit(): int
  {
    return $this->cfg['limit'];
  }

  public function getCfg(): array
  {
    return $this->cfg;
  }

}
