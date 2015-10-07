<?php
namespace bbn\db;


trait triggers {

  private
    /**
     * An array of functions for launching triggers on actions
     * @var mixed
     */
    $triggers = [
      'where' => [
        'before' => []
      ],
      'select' => [
        'before' => [],
        'after' => []
      ],
      'insert' => [
        'before' => [],
        'after' => []
      ],
      'update' => [
        'before' => [],
        'after' => []
      ],
      'delete' => [
        'before' => [],
        'after' => []
      ]
    ],
    /**
     * @var bool
     */
    $triggers_disabled = false;

  /**
   * Launches a function before or after
   *
   * @param $table
   * @param $kind
   * @param $moment
   * @param $values
   * @param $where
   * @return bool
   */
  private function _trigger(array $cfg){
    if ( !isset($cfg['trig']) ){
      $cfg['trig'] = 1;
    }
    if ( !isset($cfg['run']) ){
      $cfg['run'] = 1;
    }
    if ( !empty($this->triggers[$cfg['kind']][$cfg['moment']]) ){

      $table = $this->tfn(is_array($cfg['table']) ? $cfg['table'][0] : $cfg['table']);
      // Specific to a table
      if ( isset($this->triggers[$cfg['kind']][$cfg['moment']][$table]) ){
        foreach ( $this->triggers[$cfg['kind']][$cfg['moment']][$table] as $i => $f ){
          if ( is_callable($f) ){
            $cfg[$f] = call_user_func($f, $cfg);
            if ( !$cfg[$f] ){
              $cfg['run'] = false;
              $cfg['trig'] = false;
            }
            else if ( is_array($cfg[$f]) ){
              foreach ( $cfg[$f] as $k => $v ){
                if ( $k === 'trig' ) {
                  if ($cfg['trig']) {
                    $cfg['trig'] = $v;
                  }
                }
                else if ( $k === 'run' ){
                  if ( $cfg['run'] ) {
                    if (!$v || ($v > $cfg['run'])) {
                      $cfg['run'] = $v;
                    }
                  }
                }
                else{
                  $cfg[$k] = $v;
                  unset($cfg[$f][$k]);
                }
              }
            }
          }
        }
        //echo \bbn\tools::make_tree($trig);
        //echo \bbn\tools::make_tree($cfg);
      }
    }
    return $cfg;
  }

  /**
   * Enable the triggers' functions
   *
   * @return \bbn\db\connection
   */
  public function enable_trigger(){
    $this->triggers_disabled = false;
    return $this;
  }

  /**
   * Disable the triggers' functions
   *
   * @return \bbn\db\connection
   */
  public function disable_trigger(){
    $this->triggers_disabled = true;
    return $this;
  }

  /**
   * Apply a function each time the methods $kind are used
   *
   * @param callable $function
   * @param string $kind select|insert|update|delete
   * @param string $moment before|after
   * @param string|array table database's table(s) name(s)
   * @return \bbn\db\connection
   */
  public function set_trigger($function, $kind='', $moment='', $tables='*' ){
    if ( is_callable($function) ){
      $kinds = ['where', 'select', 'insert', 'update', 'delete'];
      $moments = ['before', 'after'];
      if ( empty($kind) ){
        $kind = $kinds;
      }
      else if ( !is_array($kind) ){
        $kind = [strtolower($kind)];
      }
      else{
        $kind = array_map(function($a){
          return strtolower($a);
        }, $kind);
      }
      if ( empty($moment) ){
        $moment = $moments;
      }
      else if ( !is_array($moment) ){
        $moment = [strtolower($moment)];
      }
      else{
        $moment = array_map(function($a){
          return strtolower($a);
        }, $moment);
      }
      foreach ( $kind as $k ){
        if ( in_array($k, $kinds) ){
          foreach ( $moment as $m ){
            if ( in_array($m, $moments) && isset($this->triggers[$k][$m]) ){
              if ( $tables === '*' ){
                $tables = $this->get_tables();
              }
              else if ( \bbn\str\text::check_name($tables) ){
                $tables = [$tables];
              }
              if ( is_array($tables) ){
                foreach ( $tables as $table ){
                  $t = $this->tfn($table);
                  if ( !isset($this->triggers[$k][$m][$t]) ){
                    $this->triggers[$k][$m][$t] = [];
                  }
                  array_push($this->triggers[$k][$m][$t], $function);
                }
              }
            }
          }
        }
      }
    }
    return $this;
  }

  /**
   * @returns a selection query
   */
  private function _sel($table, $fields = [], $where = [], $order = false, $limit = 100, $start = 0){
    if ( !is_array($table) ){
      $table = [$table];
    }
    $tables_fields = [];
    $tables_full = [];
    foreach ( $table as $tab ){
      $tables_fields[$tab] = array_keys($this->modelize($tab)['fields']);
      array_push($tables_full, $this->tfn($tab));
    }
    foreach ( $fields as $i => $field ){
      if ( !strpos($field, '.') ){
        $tab = [];
        foreach ( $tables_fields as $t => $f ){
          if ( in_array($field, $f) ){
            array_push($tab, $t);
          }
        }
        if ( count($tab) === 1 ){
          $fields[$i] = $this->cfn($field, $tab[0]);
        }
        else if ( count($tab) > 1 ){
          $this->error('Error! Duplicate field name, you must insert the fields with their fullname.');
        }
        else {
          $this->error("Error! The column '$field' doesn't exist in '".implode(", ", array_keys($tables_fields)));
        }
      }
    }
    $cfg = [
      'moment' => 'before',
      'kind' => 'select',
      'table' => $tables_full
    ];
    $cfg['where'] = $this->where_cfg($where, $cfg['table']);
    $cfg['hash'] = $this->make_hash(
      'select',
      serialize($cfg['table']),
      serialize($fields),
      serialize($this->get_where($cfg['where'], $cfg['table'])),
      serialize($order),
      $limit,
      $start
    );
    if ( isset($this->queries[$cfg['hash']]) ){
      $cfg['sql'] = $this->queries[$this->queries[$cfg['hash']]]['statement'];
    }
    else{
      $cfg['sql'] = $this->language->get_select($table, $fields, $cfg['where']['final'], $order, $limit, $start);
    }
    $cfg['values'] = array_values($fields);
    if ( $cfg['sql'] ){
      if ( $this->triggers_disabled ){
        if ( count($cfg['where']['values']) > 0 ){
          $r = $this->query($cfg['sql'], $cfg['hash'], $cfg['where']['values']);
        }
        else{
          $r = $this->query($cfg['sql'], $cfg['hash']);
        }
      }
      else{
        $cfg = $this->_trigger($cfg);
        $r = false;
        if ( $cfg['run'] ) {
          if ( count($cfg['where']['values']) > 0 ){
            $r = $this->query($cfg['sql'], $cfg['hash'], $cfg['where']['values']);
          }
          else {
            $r = $this->query($cfg['sql'], $cfg['hash']);
          }
        }
        if ( ($r && $cfg['trig']) || !empty($cfg['force']) ){
          $cfg['moment'] = 'after';
          $this->_trigger($cfg);
        }
      }
      return $r;
    }
  }

  /**
   * @param type $where
   * @return type
   */
  public function where_cfg(array $w, $table = [], $values = []){
    // Checking this array is not already correctly configured to be where
    if ( isset($w['bbn_where_cfg'], $w['fields'], $w['values'], $w['final'], $w['keyval'], $w['unique']) &&
      ($w['bbn_where_cfg'] === 1)
    ){
      return $w;
    }

    // The shape of the final result
    $r = [
      'fields' => [],
      'values' => [],
      'final' => [],
      'keyval' => [],
      'unique' => [],
      'bbn_where_cfg' => 1
    ];

    if ( is_array($w) && (count($w) > 0) ){
      $tables_fields = [];
      if ( !is_array($table) ){
        $table = empty($table) ? [] : [$table];
      }
      /*
      if ( class_exists('\\bbn\\appui\\history', false) && \bbn\appui\history::has_history($this) ){
        $hcol = \bbn\appui\history::$hcol;
        $hcols = [];
      }
      */
      foreach ( $table as $tab ){
        $model = $this->modelize($tab);
        $tables_fields[$tab] = array_keys($model['fields']);
        /*
        if ( !empty($hcol) && in_array($hcol, $tables_fields[$tab]) ){
          array_push($hcols, $this->cfn($hcol, $tab));
        }
        */
      }
      /** @var int $i Numeric index */
      $i = 0;
      foreach ( $w as $k => $v ){
        // arrays with [ field_name => value, field_name => value...] (equal assumed)
        if ( is_string($k) ){
          $v = [$k, is_string($v) ? 'LIKE' : '=', $v];
        }
        if ( is_array($v) ) {
          if ( !strpos($v[0], '.') && count($table) ){
            $tab = [];
            foreach ($tables_fields as $t => $f) {
              if (in_array($v[0], $f)) {
                array_push($tab, $t);
              }
            }
            if (count($tab) === 1) {
              $v[0] = $this->cfn($v[0], $tab[0]);
            }
            else if (count($tab) > 1) {
              die('Error! Duplicate field name, you must insert the fields with their fullname.');
            }
            else {
              die(var_dump(
                "Error! The column '$v[0]' as mentioned in where doesn't exist in '".
                implode(", ", array_keys($tables_fields))."' table(s)", $v
              ));
            }
          }
          // arrays with [ field_name => value]
          if ( count($v) === 2 ){
            array_push($r['fields'], $v[0]);
            array_push($r['values'], $v[1]);
            $r['keyval'][$v[0]] = $v[1];
            array_push($r['final'], [$v[0], is_string($v[1]) ? 'LIKE' : '=', $v[1]]);
          }
          // arrays with [ field_name, operator, value]
          else if ( count($v) === 3 ){
            array_push($r['fields'], $v[0]);
            array_push($r['values'], $v[2]);
            if ( ($v[1] === '=') || !isset($r['keyval'][$v[0]]) ){
              $r['keyval'][$v[0]] = $v[2];
            }
            array_push($r['final'], [$v[0], $v[1], $v[2]]);
          }
          // arrays with [ field_name, operator, value, bool] value is a DB function/column (unescaped)
          else if ( count($v) === 4 ){
            array_push($r['fields'], $v[0]);
            array_push($r['values'], $v[2]);
            if ( ($v[1] === '=') || !isset($r['keyval'][$v[0]]) ){
              $r['keyval'][$v[0]] = $v[2];
            }
            array_push($r['final'], [$v[0], $v[1], $v[2], $v[3]]);
          }
          else{
            $this->log("Not enough argument for a where", $v);
          }
        }
        else{
          $this->log("Incorrect where", $v, $r);
        }
        array_push($r['unique'], [$r['final'][$i][0], $r['final'][$i][1]]);
        $i++;
      }
    }
    /** @todo Pass this into the history class -> possible? */
    // Automatically select non deleted if history is enabled
    if ( !empty($table) && !$this->triggers_disabled ){
      $res = $this->_trigger([
        'moment' => 'before',
        'table' => $table,
        'where' => $r,
        'kind' => 'where',
        'values' => $values
      ]);
      if ( isset($res['where']) ){
        $r = $res['where'];
      }
    }
    /*
     * !empty($hcols)
    ){
      foreach ( $hcols as $hc ){
        if ( !in_array($hc, $r['fields']) ){
          array_push($r['fields'], $hc);
          array_push($r['values'], 1);
          array_push($r['final'], [$hc, '=', 1]);
          /** @todo: Check if it is right man!
          array_push($r['unique'], [$hc, '=']);
          $r['keyval'][$hc] = 1;
        }
      }
    }
    */
    return $r;
  }

  /**
   * Launches the query but execute the trigger functions if defined at the moments of the query
   *
   * @param array $cfg If true, controls if the row is already existing and ignores it.
   *
   * @return mixed The query's result or the value returned by the trigger
   */
  private function _exec_triggers(array $cfg){
    $cfg['moment'] = 'before';
    if ( $this->triggers_disabled ){
      $query_args = [
        $cfg['sql'],
        $cfg['hash']
      ];
      switch ( $cfg['kind'] ){
        case 'insert':
          array_push($query_args, array_values($cfg['values']));
          break;
        case 'update':
          array_push($query_args, empty($cfg['where']) ?
            array_values($cfg['values']) :
            array_merge(array_values($cfg['values']), $cfg['where']['values'])
          );
          break;
        case 'delete':
          array_push($query_args, empty($cfg['where']) ? [] : $cfg['where']['values']);
          break;
        case 'select':
          break;
      }
      return $this->query($query_args);
    }
    else if ( $cfg = $this->_trigger($cfg) ){
      if ( !is_array($cfg) ){
        $cfg = ['run' => $cfg, 'trig' => $cfg];
      }
      if ( !isset($cfg['run']) ){
        $cfg['run'] = $cfg['trig'];
      }
      if ( $cfg['run'] ) {
        $query_args = [
          $cfg['sql'],
          $cfg['hash']
        ];
        switch ( $cfg['kind'] ){
          case 'insert':
            array_push($query_args, array_values($cfg['values']));
            break;
          case 'update':
            array_push($query_args, empty($cfg['where']) ?
              array_values($cfg['values']) :
              array_merge(array_values($cfg['values']), $cfg['where']['values'])
            );
            //var_dump($cfg, $query_args);
            break;
          case 'delete':
            array_push($query_args, empty($cfg['where']) ? [] : $cfg['where']['values']);
            break;
          case 'select':
            break;
        }
        $cfg['run'] = call_user_func_array([$this, 'query'], $query_args);
        if ( isset($cfg['force']) && $cfg['force'] ){
          $cfg['trig'] = 1;
        }
        else if ( !$cfg['run'] ){
          $cfg['trig'] = false;
        }
      }
      if ( $cfg['trig'] ){
        $cfg['moment'] = 'after';
        $cfg = $this->_trigger($cfg);
      }
      if ( isset($cfg['value']) ){
        return $cfg['value'];
      }
      else if ( isset($cfg['run']) ) {
        return $cfg['run'];
      }
      else if ( isset($cfg['trig']) ) {
        return $cfg['trig'];
      }
    }
    return false;
  }

  /**
   * Inserts row(s) in a table.
   *
   * <code>
   * $this->db->insert("table_users", [
   *    ["name" => "Ted"],
   *    ["surname" => "McLow"]
   *  ]);
   * </code>
   *
   * <code>
   * $this->db->insert("table_users", [
   *    ["name" => "July"],
   *    ["surname" => "O'neill"]
   *  ], [
   *    ["name" => "Peter"],
   *    ["surname" => "Griffin"]
   *  ], [
   *    ["name" => "Marge"],
   *    ["surname" => "Simpson"]
   *  ]);
   * </code>
   *
   * @param string $table The table name.
   * @param array $values The values to insert.
   * @param bool $ignore If true, controls if the row is already existing and ignores it.
   *
   * @return int Number affected rows.
   */
  public function insert($table, array $values, $ignore = false)
  {
    $keys = array_keys($values);
    // $values is an array of arrays to insert
    if ( isset($keys[0]) && ($keys[0] === 0) ){
      $keys = array_keys($values[0]);
    }
    else{
      $values = [$values];
    }
    $affected = 0;
    if ( $sql = $this->_statement('insert', $table, $keys, $ignore) ){
      foreach ( $values as $i => $vals ){
        $r = $this->_exec_triggers([
          'table' => $table,
          'kind' => 'insert',
          'values' => $vals,
          'hash' => $sql['hash'],
          'sql' => $sql['sql'],
          'ignore' => $ignore
        ]);
        if ( is_numeric($r) ){
          $affected += $r;
        }
        else {
          return $r;
        }
      }
    }
    return $affected;
  }

  /**
   * If not exist inserts row(s) in a table, else update.
   *
   * <code>
   * $this->db->insert_update(
   *  "table_users",
   *  [
   *    'id' => '12',
   *    'name' => 'Frank'
   *  ]
   * );
   * </code>
   *
   * @param string $table The table name.
   * @param array $values The values to insert.
   *
   * @return int The number of rows inserted or updated.
   */
  public function insert_update($table, array $values){
    // Twice the arguments
    if ( !\bbn\tools::is_assoc($values) ){
      $res = 0;
      foreach ( $values as $v ){
        $res += $this->insert_update($table, $v);
      }
      return $res;
    }
    $table = $this->tfn($table);
    $keys = $this->get_keys($table);
    $unique = [];
    foreach ( $keys['keys'] as $k ){
      if ( $k['unique'] ){
        $i = 0;
        foreach ( $k['columns'] as $c ){
          if ( isset($values[$c]) ){
            $unique[$c] = $values[$c];
            $i++;
          }
        }
        if ( $i === count($k['columns']) ){
          if ( $update = $this->count($table, $unique) ){
            foreach ( $unique as $f => $v ){
              unset($values[$f]);
            }
            return $this->update($table, $values, $unique);
          }
        }
      }
    }
    return $this->insert($table, $values);
  }

  /**
   * Updates row(s) in a table.
   *
   * <code>
   * $this->db->update(
   *  "table_users",
   *  [
   *    ['name' => 'Frank'],
   *    ['surname' => 'Red']
   *  ],
   *  ['id' => '127']
   * );
   * </code>
   *
   * @param string $table The table name.
   * @param array $values The new value(s).
   * @param array $where The "where" condition.
   * @param boolean $ignore If IGNORE should be added to the statement
   *
   * @return int The number of rows updated.
   */
  public function update($table, array $values, array $where, $ignore=false)
  {
    $where = $this->where_cfg($where, $table, $values);
    $vals = [];
    foreach ( $values as $k => $v ){
      array_push($vals, $this->cfn($k, $table));
    }
    if ( $sql = $this->_statement('update', $table, $vals, $where, $ignore) ){
      return $this->_exec_triggers([
        'table' => $table,
        'kind' => 'update',
        'values' => $values,
        'where' => $where,
        'hash' => $sql['hash'],
        'sql' => $sql['sql'],
        'ignore' => $ignore
      ]);
    }
    return false;
  }

  /**
   * If exist delete row(s) in a table, else ignore.
   *
   * <code>
   * $this->db->delete_ignore(
   *  "table_users",
   *  ['id' => '20']
   * );
   * </code>
   *
   * @param string $table The table name.
   * @param array $where The "where" condition.
   *
   * @return int The number of rows deleted.
   */
  public function update_ignore($table, array $values, array $where)
  {
    return $this->update($table, $values, $where, 1);
  }

  /**
   * Deletes row(s) in a table.
   *
   * <code>
   * $this->db->delete("table_users", ['id' => '32']);
   * </code>
   *
   * @param string $table The table name.
   * @param array $where The "where" condition.
   * @param bool $ignore default: false.
   *
   * @return int The number of rows deleted.
   */
  public function delete($table, array $where, $ignore = false)
  {
    $r = false;
    $trig = 1;
    $where = $this->where_cfg($where, $table);
    if ( $sql = $this->_statement('delete', $table, $where, $ignore) ){
      return $this->_exec_triggers([
        'table' => $table,
        'kind' => 'delete',
        'where' => $where,
        'hash' => $sql['hash'],
        'sql' => $sql['sql'],
        'ignore' => $ignore
      ]);
    }
    return $r;
  }

  /**
   * If exist delete row(s) in a table, else ignore.
   *
   * <code>
   * $this->db->delete_ignore(
   *  "table_users",
   *  ['id' => '20']
   * );
   * </code>
   *
   * @param string $table The table name.
   * @param array $where The "where" condition.
   *
   * @return int The number of rows deleted.
   */
  public function delete_ignore($table, array $where)
  {
    return $this->delete($table, $where, 1);
  }

  /**
   * If not exist inserts row(s) in a table, else ignore.
   *
   * <code>
   * $this->db->insert_ignore(
   *  "table_users",
   *  [
   *    ['id' => '19', 'name' => 'Frank'],
   *    ['id' => '20', 'name' => 'Ted'],
   *  ]
   * );
   * </code>
   *
   * @param string $table The table name.
   * @param array $values The row(s) values.
   *
   * @return int The number of rows inserted.
   */
  public function insert_ignore($table, array $values)
  {
    return $this->insert($table, $values, 1);
  }

  public function truncate($table){
    return $this->delete($table, []);
  }

}
