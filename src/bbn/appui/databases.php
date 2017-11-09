<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 20/02/2017
 * Time: 01:39
 */

namespace bbn\appui;

use bbn;

class databases extends bbn\models\cls\cache
{
  use bbn\models\tts\optional;

  private $o;

  public function __construct(bbn\db $db){
    parent::__construct($db);
    self::optional_init();
    $this->o = bbn\appui\options::get_instance();
  }

  /**
   * @param string $host
   * @return false|int
   */
  public function host_id(string $host = ''){
    return self::get_option_id($host ?: $this->db->host, 'hosts');
  }

  public function count_hosts(){
    if ( $id_parent = self::get_option_id('hosts') ){
      return $this->o->count($id_parent);
    }
  }

  /**
   * @return array|false
   */
  public function hosts(){
    if ( $id_parent = self::get_option_id('hosts') ){
      return array_map(function($a){
        return [
          'id' => $a['id'],
          'text' => $a['text'],
          'name' => $a['code']
        ];
      }, $this->o->code_options($id_parent));
    }
    return false;
  }

  /**
   * @return array|false
   */
  public function full_hosts(){
    if ( $id_parent = self::get_option_id('hosts') ){
      $o =& $this->o;
      return array_map(function($a) use ($o){
        $r = [
          'id' => $a['id'],
          'text' => $a['text'],
          'name' => $a['code'],
          'num_dbs' => 0,
          'num_users' => 0
        ];
        if ( $id_dbs = $o->from_code('dbs', $a['id']) ){
          $r['num_dbs'] = $o->count($id_dbs);
        }
        if ( $id_users = $o->from_code('users', $a['id']) ){
          $r['num_users'] = $o->count($id_users);
        }
        return $r;
      }, $this->o->full_options($id_parent));
    }
    return false;
  }

  /**
   * @param string $db
   * @param mixed $host
   * @return false|int
   */
  public function db_id(string $db = '', $host = ''){
    if ( !is_int($host) ){
      $host = $this->host_id($host ?: $this->db->host);
    }
    //var_dump("P{ARENT", $host, $this->o->from_code('dbs', $host));
    if ( is_int($host) && ($id_parent = $this->o->from_code('dbs', $host)) ){
      return $this->o->from_code($db, $id_parent);
    }
    return false;
  }

  public function count_dbs($host){
    if ( !is_int($host) ){
      $host = $this->host_id($host);
    }
    if ( is_int($host) && ($id_parent = $this->o->from_code('dbs', $host)) ){
      return $this->o->count($id_parent);
    }
  }

  /**
   * @param mixed $host
   * @return array|false
   */
  public function dbs($host = ''){
    if ( !\bbn\str::is_uid($host) ){
      $host = $this->host_id($host ?: $this->db->host);
    }
    if ( \bbn\str::is_uid($host) && ($id_parent = $this->o->from_code('dbs', $host)) ){
      return array_map(function($a){
        return [
          'id' => $a['id'],
          'text' => $a['text'],
          'name' => $a['code']
        ];
      }, $this->o->code_options($id_parent));
    }
    return false;
  }

  /**
   * @param mixed $host
   * @return array|false
   */
  public function full_dbs($host = ''){
    if ( !is_int($host) ){
      $host = $this->host_id($host ?: $this->db->host);
    }
    if ( is_int($host) && ($id_parent = $this->o->from_code('dbs', $host)) ){
      $o =& $this->o;
      return array_map(function($a) use ($o){
        $r = [
          'id' => $a['id'],
          'text' => $a['text'],
          'name' => $a['code'],
          'num_tables' => 0,
          'num_procedures' => 0,
          'num_functions' => 0
        ];
        if ( $id_tables = $o->from_code('tables', $a['id']) ){
          $r['num_tables'] = $o->count($id_tables);
        }
        if ( $id_procedures = $o->from_code('procedures', $a['id']) ){
          $r['num_procedures'] = $o->count($id_procedures);
        }
        if ( $id_functions = $o->from_code('functions', $a['id']) ){
          $r['num_functions'] = $o->count($id_functions);
        }
        return $r;
      }, $this->o->full_options($id_parent));
    }
    return false;
  }

  /**
   * @param string $table
   * @param mixed $db
   * @param string $host
   * @return false|int
   */
  public function table_id(string $table, $db = '', $host = ''){
    if ( !is_int($db) ){
      return $this->db_id($this->db->tsn($table), $db, $host);
    }
    if ( is_int($db) && ($id_parent = $this->o->from_code('tables', $db)) ){
      return $this->o->from_code($table, $id_parent);
    }
  }

  public function count_tables($db, $host = ''){
    if ( !is_int($db) ){
      $host = $this->db_id($db, $host);
    }
    if ( is_int($db) && ($id_parent = $this->o->from_code('tables', $db)) ){
      return $this->o->count($id_parent);
    }
  }

  /**
   * @param mixed $db
   * @param string $host
   * @return array|false
   */
  public function tables($db = '', $host = ''){
    if ( !is_int($db) ){
      $db = $this->db_id($db ?: $this->db->current, $host ?: $this->db->host);
    }
    if ( is_int($db) && ($id_parent = $this->o->from_code('tables', $db)) ){
      return array_map(function($a){
        return [
          'id' => $a['id'],
          'text' => $a['text'],
          'name' => $a['code']
        ];
      }, array_values($this->o->code_options($id_parent)));
    }
    return false;
  }

  /**
   * @param mixed $db
   * @param string $host
   * @return array|false
   */
  public function full_tables($db = '', $host = ''){
    if ( !is_int($db) ){
      $db = $this->db_id($db ?: $this->db->current, $host ?: $this->db->host);
    }
    if ( is_int($db) && ($id_parent = $this->o->from_code('tables', $db)) ){
      $o =& $this->o;
      return array_map(function($a) use ($o){
        $r = [
          'id' => $a['id'],
          'text' => $a['text'],
          'name' => $a['code'],
          'num_columns' => 0,
          'num_keys' => 0
        ];
        if ( $id_columns = $o->from_code('columns', $a['id']) ){
          $r['num_columns'] = $o->count($id_columns);
        }
        if ( $id_keys = $o->from_code('keys', $a['id']) ){
          $r['num_keys'] = $o->count($id_keys);
        }
        return $r;
      }, $this->o->full_options($id_parent));
    }
    return false;
  }

  /**
   * @param string $column
   * @param mixed $table
   * @param string $db
   * @param string $host
   * @return false|int
   */
  public function column_id(string $column, $table, $db = '', $host = ''){
    if ( is_int($table) ){
      return $this->o->from_code($this->db->csn($column), $table);
    }
    return self::get_option_id($this->db->csn($column), 'columns', $this->db->tsn($table), 'tables', $db ?: $this->db->current, 'dbs', $host ?: $this->db->host, 'hosts');
  }

  public function count_columns($table, $db = '', $host = ''){
    if ( !is_int($table) ){
      $table = $this->table_id($table, $db, $host);
    }
    if (
      is_int($table) &&
      ($id_parent = $this->o->from_code('columns', $table))
    ){
      return $this->o->count($id_parent);
    }
  }

  /**
   * @param mixed $table
   * @param string $db
   * @param string $host
   * @return array|false
   */
  public function columns($table, $db = '', $host = ''){
    if ( !is_int($table) ){
      $table = $this->table_id($this->db->tsn($table), $db, $host);
    }
    if ( is_int($table) && ($id_parent = $this->o->from_code('columns', $table)) ){
      return $this->o->options($id_parent);
    }
    return false;
  }

  /**
   * @param mixed $table
   * @param string $db
   * @param string $host
   * @return array|false
   */
  public function full_columns($table, $db = '', $host = ''){
    if ( !is_int($table) ){
      $table = $this->table_id($table, $db, $host);
    }
    if ( is_int($table) && ($id_parent = $this->o->from_code('columns', $table)) ){
      return $this->o->full_options($id_parent);
    }
    return false;
  }

  /**
   * @param mixed $key
   * @param $table
   * @param string $db
   * @param string $host
   * @return false|int
   */
  public function key_id(string $key, $table, $db = '', $host = ''){
    if ( is_int($key) ){
      return $this->o->from_code($key, $table);
    }
    return self::get_option_id($key, 'keys', $table, 'tables', $db ?: $this->db->current, 'dbs', $host ?: $this->db->host, 'hosts');
  }

  public function count_keys($table, $db = '', $host = ''){
    if ( !is_int($table) ){
      $table = $this->table_id($table, $db, $host);
    }
    if ( is_int($table) && ($id_parent = $this->o->from_code('keys', $table)) ){
      return $this->o->count($id_parent);
    }
  }

  /**
   * @param mixed $table
   * @param string $db
   * @param string $host
   * @return array|bool|false
   */
  public function keys($table, $db = '', $host = ''){
    if ( !is_int($table) ){
      $table = $this->table_id($table, $db, $host);
    }
    if (
      is_int($table) &&
      ($id_parent = $this->o->from_code('keys', $table)) &&
      ($tree = $this->o->full_tree($id_parent)) &&
      $tree['items']
    ){
      $t =& $this;
      return array_map(function($a) use($t){
        $key = [
          'name' => $a['code'],
          'unique' => $a['unique'],
          'columns' => [],
          'ref_column' => $a['id_alias'] ? $a['alias']['code'] : null,
          'ref_table' => $a['id_alias'] &&
                         ($id_table = $t->o->get_id_parent($a['alias']['id_parent'])) ?
                         $t->o->code($id_table) : null,
          'ref_db' => !empty($id_table) &&
                      ($id_db = $t->o->get_id_parent($t->o->get_id_parent($id_table))) ?
                      $t->o->code($id_db) : null
        ];
        foreach ( $a['items'] as $col ){
          $key['columns'][] = $col['code'];
        }
        return $key;
      }, $tree['items']);
    }
    return false;
  }

  /**
   * @param mixed $table
   * @param string $db
   * @param string $host
   * @return array|bool|false
   */
  public function full_keys($table, $db = '', $host = ''){
    return $this->keys($table, $db, $host);
  }

  public function import_host(string $host, bool $full = false){
    if (
      !($id_host = self::get_option_id($host, 'hosts')) &&
      $id_host = $this->o->add([
        'id_parent' => self::get_option_id('hosts'),
        'text' => $host,
        'code' => $host,
      ])
    ){
      $this->o->set_cfg($id_host, [
        'allow_children' => 1
      ]);
      if (
        $id_users = $this->o->add([
          'id_parent' => $id_host,
          'text' => _('Users'),
          'code' => 'users',
        ])
      ){
        $this->o->set_cfg($id_users, [
          'show_code' => 1,
          'show_value' => 1,
          'allow_children' => 1,

        ]);
      }
      if (
        $id_dbs = $this->o->add([
          'id_parent' => $id_host,
          'text' => _("Databases"),
          'code' => 'dbs',
        ])
      ){
        $this->o->set_cfg($id_dbs, [
          'show_code' => 1,
          'allow_children' => 1,
        ]);
      }
    }
    return $id_host;
  }

  public function import_db(string $db, $id_host, $full = false){
    if ( $id_dbs = $this->o->from_code('dbs', $id_host) ){
      if (
        !($id_db = $this->o->from_code($db, $id_dbs)) &&
        $id_db = $this->o->add([
          'id_parent' => $id_dbs,
          'text' => $db,
          'code' => $db,
        ])
      ){
        $this->o->set_cfg($id_db, [
          'allow_children' => 1,
        ]);
        if (
          $id_procedures = $this->o->add([
            'id_parent' => $id_db,
            'text' => _('Procedures'),
            'code' => 'procedures',
          ])
        ){
          $this->o->set_cfg($id_procedures, [
            'show_code' => 1,
            'show_value' => 1,
            'allow_children' => 1
          ]);
        }
        if (
          $id_functions = $this->o->add([
            'id_parent' => $id_db,
            'text' => _('Function'),
            'code' => 'functions',
          ])
        ){
          $this->o->set_cfg($id_functions, [
            'show_code' => 1,
            'show_value' => 1,
            'allow_children' => 1
          ]);
        }
        if (
          $id_tables = $this->o->add([
            'id_parent' => $id_db,
            'text' => _('Tables'),
            'code' => 'tables',
          ])
        ){
          $this->o->set_cfg($id_tables, [
            'show_code' => 1,
            'show_value' => 1,
            'allow_children' => 1
          ]);
        }
      }
      if ( $id_db && $full ){
        $tables = $this->db->get_tables();
        foreach ( $tables as $t ){
          $this->import_table($t, $id_db);
        }
      }
      return $id_db;
    }
    return false;
  }

  public function import_table(string $table, $id_db, $full = false){
    if ( $id_tables = $this->o->from_code('tables', $id_db) ){
      if (
        !($id_table = $this->o->from_code($table, $id_tables)) &&
        $id_table = $this->o->add([
          'id_parent' => $id_tables,
          'text' => $table,
          'code' => $table,
        ])
      ){
        $this->o->set_cfg($id_table, [
          'allow_children' => 1
        ]);
        if ( $id_columns = $this->o->add([
          'id_parent' => $id_table,
          'text' => _("Columns"),
          'code' => 'columns'
        ]) ){
          $this->o->set_cfg($id_columns, [
            'show_code' => 1,
            'show_value' => 1,
            'sortable' => 1
          ]);
        }
        if ( $id_keys = $this->o->add([
          'id_parent' => $id_table,
          'text' => _("Keys"),
          'code' => 'keys',
        ]) ){
          $this->o->set_cfg($id_keys, [
            'show_code' => 1,
            'show_value' => 1,
            'show_alias' => 1,
            'allow_children' => 1
          ]);
        }
      }
      else{
        $id_columns = $this->o->from_code('columns', $id_table);
        $id_keys = $this->o->from_code('keys', $id_table);
      }
      if ( $id_table && $id_columns && $id_keys && ($m = $this->db->modelize($table)) ){
        $num_cols = 0;
        $fields = [];
        foreach ( $m['fields'] as $col => $cfg ){
          if ( $opt_col = $this->o->option($col, $id_columns) ){
            $num_cols += (int)$this->o->set($opt_col['id'], \bbn\x::merge_arrays($opt_col, $cfg));
          }
          else if ( $id = $this->o->add(\bbn\x::merge_arrays($cfg, [
            'id_parent' => $id_columns,
            'text' => $col,
            'code' => $col,
            'num' => $cfg['position']
          ])) ){
            $num_cols++;
            $opt_col = $cfg;
            $opt_col['id'] = $id;
          }
          if ( $opt_col ){
            $fields[$col] = $opt_col['id'];
          }
        }
        $num_keys = 0;
        foreach ( $m['keys'] as $key => $cfg ){
          $cols = $cfg['columns'];
          unset($cfg['columns']);
          if (
            isset($cfg['ref_db'], $cfg['ref_table'], $cfg['ref_column']) &&
            ($id_alias = $this->column_id($cfg['ref_column'], $cfg['ref_table'], $cfg['ref_db']))
          ){
            $cfg['id_alias'] = $id_alias;
            unset($cfg['ref_db'], $cfg['ref_table'], $cfg['ref_column']);
          }
          if ( $opt_key = $this->o->option($key, $id_keys) ){
            $num_keys += (int)$this->o->set($opt_key['id'], \bbn\x::merge_arrays($opt_key, $cfg));
          }
          else if ( $id = $this->o->add(\bbn\x::merge_arrays($cfg, [
            'id_parent' => $id_keys,
            'text' => $key,
            'code' => $key
          ])) ){
            $this->o->set_cfg($id, [
              'show_code' => 1,
              'show_alias' => 1
            ]);
            $num_keys++;
            $opt_key = $cfg;
            $opt_key['id'] = $id;
          }
          if ( $opt_key && $cols ){
            foreach ( $cols as $col ){
              if ( isset($fields[$col]) ){
                if ( $opt = $this->o->option($col, $opt_key['id']) ){
                  $this->o->set($opt['id'], \bbn\x::merge_arrays($opt, [
                    'id_alias' => $fields[$col]
                  ]));
                }
                else{
                  $tmp = [
                    'id_parent' => $opt_key['id'],
                    'id_alias' => $fields[$col],
                    'code' => $col,
                    'text' => $col
                  ];
                  if ( $this->o->add($tmp) ){
                    $opt = $tmp;
                  }
                }
              }
            }
          }
        }
        return [
          'columns' => $num_cols,
          'keys' => $num_keys
        ];
      }
    }
    return false;
  }

  public function import(string $table){
    $res = false;
    if ( $m = $this->db->modelize($table) ){
      $tf = explode('.', $this->db->tfn($table));
      $db = $tf[0];
      $table = $tf[1];

      if (
        ($id_host = $this->import_host($this->db->host)) &&
        ($id_db = $this->import_db($db, $id_host))
      ){
        $res = $this->import_table($table, $id_db);
      }
    }
    return $res;
  }

  public function import_all($db = ''){
    $res = false;
    if ( $tables = $this->db->get_tables($db) ){
      $res = [
        'tables' => 0,
        'columns' => 0,
        'keys' => 0
      ];
      foreach ( $tables as $t ){
        if ( $tmp = $this->import(($db ?: $this->db->current).'.'.$t) ){
          $res['tables']++;
          $res['columns'] += $tmp['columns'];
          $res['keys'] += $tmp['keys'];
        }
      }
    }
    return $res;
  }

  public function remove(string $table, $db = '', $host = ''){
    $id = $this->table_id($table, $db, $host);
    return $this->o->remove_full($id);
  }

  public function remove_all(string $db = '', $host = ''){
    $id = $this->db_id($db, $host);
    return $this->o->remove_full($id);
  }

  public function remove_host(string $host){
    $id = $this->host_id($host);
    return $this->o->remove_full($id);
  }

  /**
   * @param string $table
   * @param string $db
   * @param string $host
   * @return null|array
   */
  public function modelize(string $table = '', string $db = '', string $host = ''): ?array
  {
    if ( $mod = $this->db->modelize($table) ){
      $keys = function(&$a) use(&$table, &$db, &$host){
        if ( is_array($a['keys']) ){
          array_walk($a['keys'], function(&$w, $k) use(&$table, &$db, &$host){
            $w['id_option'] = $this->key_id($k, $table, $db, $host);
          });
        }
      };
      $fields = function(&$a) use(&$table, &$db, &$host){
        if ( is_array($a['fields']) ){
          array_walk($a['fields'], function(&$w, $k) use(&$table, &$db, &$host){
            $w['id_option'] = $this->column_id($k, $table, $db, $host);
          });
        }
      };
      if ( empty($table) ){
        array_walk($mod, function(&$w, $k) use(&$table, &$db, &$host, $keys, $fields){
          $table = $this->db->tsn($k);
          $db = substr($k, 0, strrpos($k, $table)-1);
          $w['id_option'] = $this->table_id($table, $db, $host);
          $keys($w);
          $fields($w);
        });
      }
      else {
        $keys($mod);
        $fields($mod);
      }
    }
    return $mod;
  }
}