<?php
namespace bbn\appui;

use \bbn\str\text;

class dbsync
{
	
	public static
          /**
           * @var \bbn\db\connection The DB connection
           */
          $db = false,
          $dbs = false,
          $tables = [],
          $dbs_table = 'dbsync';
  
  protected static $methods = array();

  private static
          $has_history = false,
          $default_cfg = [
            'engine' => 'sqlite',
            'host' => 'localhost',
            'db' => 'dbsync'
          ],
          $disabled = false,
          $max_retry = 5;
	
  
  final public static function __callStatic($name, $arguments)
  {
    if ( ($name === 'cbf1') || ($name === 'cbf2') ){
      return call_user_func_array(self::$methods[$name], $arguments);
    }
  }

  final public static function addMethod($name, $fn)
  {
    self::$methods[$name] = \Closure::bind($fn, NULL, __CLASS__);
  }

  final protected static function protectedMethod()
  {
    echo __METHOD__ . " was called" . PHP_EOL;
  }

  private static function log(){
    $args = func_get_args();
    foreach ( $args as $a ){
      \bbn\tools::log($a, 'dbsync');
    }
  }
  
  private static function def($dbs, $dbs_table='')
  {
    if ( empty($dbs) ){
      $dbs = self::$default_cfg;
    }
    else if ( is_string($dbs) ){
      $db = $dbs;
      $dbs = self::$default_cfg;
      $dbs['db'] = $db;
    }
    if ( !self::$dbs ){
      self::$dbs = $dbs;
    }
    if ( !empty($dbs_table) ){
      self::$dbs_table = $dbs_table;
    }
    if ( !\bbn\str\text::check_name(self::$dbs_table) ){
      self::log("Table name not allowed", self::$dbs_table);
      die("Table name not allowed");
    }
  }
	/**
	 * @return void 
	 */
	public static function init(\bbn\db\connection $db, $dbs='', $tables=[], $dbs_table='')
	{
    self::$db = $db;
    self::def($dbs, $dbs_table);
    self::$tables = $tables;
    if ( count(self::$tables) === 0 ){
      self::$tables = self::$db->get_tables();
    }
    if ( is_array(self::$tables) ){
      foreach ( self::$tables as $i => $t ){
        self::$tables[$i] = self::$db->table_full_name($t);
      }
      self::$db->set_trigger(
            '\\bbn\\appui\\dbsync::trigger',
            ['delete', 'insert'],
            'before',
            self::$tables);
      self::$db->set_trigger(
            '\\bbn\\appui\\dbsync::trigger',
            ['update', 'insert'],
            'after',
            self::$tables);
    }
	}
  
  public static function first_call()
  {
    if ( is_array(self::$dbs) ){
      self::$dbs = new \bbn\db\connection(self::$dbs);
    }
    if ( \bbn\appui\history::$is_used ){
      self::$has_history = 1;
    }
    if ( (self::$dbs->engine === 'sqlite') && !in_array(self::$dbs_table, self::$dbs->get_tables()) ){
      self::$dbs->query('CREATE TABLE "dbsync" ("id" INTEGER PRIMARY KEY  NOT NULL ,"db" TEXT NOT NULL ,"tab" TEXT NOT NULL ,"moment" DATETIME NOT NULL,"action" TEXT NOT NULL ,"rows" TEXT,"vals" TEXT,"state" INTEGER NOT NULL DEFAULT (0) );');
    }
  }
	/**
	 * Checks if the initialization has been all right
	 * @return bool
	 */
  public static function check()
  {
    return ( is_object(self::$db) && (get_class(self::$dbs) === 'bbn\db\connection') );
  }
  
  public static function disable()
  {
    self::$disabled = 1;
  }
  
  public static function enable()
  {
    self::$disabled = false;
  }
  
	/**
	 * Gets all information about a given table
	 * @return table full name
	 */
  public static function trigger($table, $kind, $moment, array $values=[], array $where=[])
  {
    self::first_call();
    $res = ['trig' => 1];
    $stable = self::$db->table_simple_name($table);
    if ( !self::$disabled && self::check() && in_array($table, self::$tables) ){
      if ( $moment === 'before' ){
        if ( $kind === 'delete' ){
          $values = self::$db->select($table, [], $where);
        }
        else if ( $kind === 'insert' ){
          if ( self::$db->has_id_increment($table) && 
                  ($pri = self::$db->get_unique_primary($table)) &&
                  empty($values[$pri]) ){
            $values[$pri] = self::$db->new_id($table);
            $res['values'] = $values;
            return $res;
          }
        }
      }
      else if ( $moment === 'after' ){
        // Case where we actually delete or restore through the $hcol column
        if ( ($kind === 'update') && self::$has_history && isset($values[\bbn\appui\history::$hcol]) ){
          if ( $values[\bbn\appui\history::$hcol] === 0 ){
            $kind = 'delete';
            $values = self::$db->select($table, [], $where);
          }
          else{
            $kind = 'insert';
            $values = self::$db->select($table, [], $where);
          }
        }
        /*
        else if ( $kind === 'insert' ){
          $values = self::$db->select($table, [], $values);
        }
         */
      }
      self::$dbs->insert(self::$dbs_table, [
        'db' => self::$db->current,
        'tab' => $stable,
        'action' => $kind,
        'moment' => date('Y-m-d H:i:s'),
        'rows' => json_encode($where),
        'vals' => json_encode($values)
      ]);
    }
    return $res;
  }
  
  public static function callback1(\Closure $f)
  {
    self::addMethod('cbf1', $f);
  }
  
  public static function callback2(\Closure $f)
  {
    self::addMethod('cbf2', $f);
  }
  
  // Looking at the rows from the other DB with status = 0 and setting them to 1
  // Comparing the new rows with the ones from this DB
  // Deleting the rows from this DB which have state = 1
  public static function sync(\bbn\db\connection $db, $dbs='', $dbs_table='', $num_try = 0){

    if ( !$num_try ){
      self::def($dbs, $dbs_table);
      self::first_call();
      self::disable();
      $mode_db = self::$db->get_error_mode();
      $mode_dbs = self::$dbs->get_error_mode();
      self::$db->set_error_mode("continue");
      self::$dbs->set_error_mode("continue");
    }
    
    $num_try++;
    
    $to_log = [
      'deleted_sync' => 0,
      'deleted_real' => 0,
      'updated_sync' => 0,
      'updated_real' => 0,
      'inserted_sync' => 0,
      'inserted_real' => 0,
      'num_problems' => 0,
      'problems' => []
    ];
    
    
    $retry = false;

    $start = ( $test = self::$dbs->get_one("
      SELECT MIN(moment)
      FROM ".self::$dbs->escape(self::$dbs_table)."
      WHERE db NOT LIKE ?
      AND state = 0",
      self::$db->current) ) ? $test : date('Y-m-d H:i:s');
    // Deleting the entries prior to this sync we produced and have been seen by the twin process
    $to_log['deleted_sync'] = self::$dbs->delete(self::$dbs_table, [
      ['db', 'LIKE', self::$db->current],
      ['state', '=', 1],
      ['moment', '<', $start]
    ]);
    
    // Selecting the entries inserted
    $ds = self::$dbs->rselect_all(self::$dbs_table, ['id', 'tab', 'vals', 'moment'], [
      ['db', 'NOT LIKE', self::$db->current],
      ['state', '=', 0],
      ['action', 'LIKE', 'insert']
    ], [
      'moment' => 'ASC',
      'id' => 'ASC'
    ]);
    // They just have to be inserted
    foreach ( $ds as $i => $d ){
      if ( isset(self::$methods['cbf1']) ){
        self::cbf1($d);
      }
      $vals = json_decode($d['vals'], 1);
      if ( !is_array($vals) ){
        $to_log['num_problems']++;
        array_push($to_log['problems'], "Hey, look urgently at the row $d[id]!");
      }
      else if ( self::$db->insert($d['tab'], $vals) ){
        if ( isset(self::$methods['cbf2']) ){
          self::cbf2($d);
        }
        $to_log['inserted_sync']++;
        self::$dbs->update(self::$dbs_table, ["state" => 1], ["id" => $d['id']]);
      }
      else if ( self::$db->select($d['tab'], [], $vals) ){
        self::$dbs->update(self::$dbs_table, ["state" => 1], ["id" => $d['id']]);
      }
      else{
        if ( $num_try > self::$max_retry ){
          $to_log['num_problems']++;
          array_push($to_log['problems'], "Problem while syncing (insert), check data with status 5 and ID ".$d['id']);
          self::$dbs->update(self::$dbs_table, ["state" => 5], ["id" => $d['id']]);
        }
        $retry = 1;
      }
    }
    

    // Selecting the entries modified and deleted in the twin DB,
    // ordered by table and rows (so the same go together)
    $ds = self::$dbs->rselect_all(self::$dbs_table, ['id', 'tab', 'action', 'rows', 'vals', 'moment'], [
      ['db', 'NOT LIKE', self::$db->current],
      ['state', '=', 0],
      ['rows', 'NOT LIKE', '[]'],
      ['action', 'NOT LIKE', 'insert']
    ], [
      'tab' => 'ASC',
      'rows' => 'ASC',
      'moment' => 'ASC',
      'id' => 'ASC'
    ]);
    foreach ( $ds as $i => $d ){
      // Executing the first callback
      if ( isset(self::$methods['cbf1']) ){
        self::cbf1($d);
      }
      // Proceeding to the actions: delete is before
      if ( $d['action'] === 'delete' ){
        if ( self::$db->delete($d['tab'], json_decode($d['rows'], 1)) ){
          self::$dbs->update(self::$dbs_table, ["state" => 1], ["id" => $d['id']]);
          $to_log['deleted_real']++;
        }
        else if ( !self::$db->select($d['tab'], [], json_decode($d['rows'], 1)) ){
          self::$dbs->update(self::$dbs_table, ["state" => 1], ["id" => $d['id']]);
        }
        else{
          if ( $num_try > self::$max_retry ){
            self::$dbs->update(self::$dbs_table, ["state" => 5], ["id" => $d['id']]);
            $to_log['num_problems']++;
            array_push($to_log['problems'], "Problem while syncing (delete), check data with status 5 and ID ".$d['id']);
          }
          $retry = 1;
        }
      }
      // Checking if there is another change done to this record and when in the twin DB
      $next_time = (
              isset($ds[$i+1]) &&
              ($ds[$i+1]['tab'] === $d['tab']) &&
              ($ds[$i+1]['rows'] === $d['rows'])
            ) ? $ds[$i+1]['moment'] : date('Y-m-d H:i:s');
      // Looking for the actions done on this specific record in our database
      // between the twin change and the next (or now if there is no other change)
      $each = self::$dbs->rselect_all(self::$dbs_table, 
        ['id', 'moment', 'action', 'vals'], [
          ['db', 'LIKE', self::$db->current],
          ['tab', 'LIKE', $d['tab']],
          ['rows', 'LIKE', $d['rows']],
          ['moment', '>=', $d['moment']],
          ['moment', '<', $next_time],
        ]);
      if ( count($each) > 0 ){
        $to_log['num_problems']++;
        array_push($to_log['problems'], "Conflict!", $d);
        foreach ( $each as $i => $e ){
          // If it's deleted locally and updated on the twin we restore
          if ( $e['action'] === 'delete' ){
            if ( $d['action'] === 'update' ){
              if ( !(self::$db->insert_update(
                      $d['tab'], 
                      \bbn\tools::merge_arrays(
                              json_decode($e['vals'], 1),
                              json_decode($d['vals'], 1)
                      )
              )) ){
                $to_log['num_problems']++;
                array_push($to_log['problems'], "insert_update number 1 had a problem");
              }
            }
          }
          // If it's updated locally and deleted in the twin we restore
          else if ( $e['action'] === 'update' ){
            if ( $d['action'] === 'delete' ){
              if ( !(self::$db->insert_update(
                      $d['tab'], 
                      \bbn\tools::merge_arrays(
                              json_decode($d['vals'], 1),
                              json_decode($e['vals'], 1)
                      )
              )) ){
                $to_log['num_problems']++;
                array_push($to_log['problems'], "insert_update had a problem");
              }
            }
          // If it's updated locally and in the twin we merge the values for the update
            else if ( $d['action'] === 'update' ){
              $d['vals'] = json_encode(
                      \bbn\tools::merge_arrays(
                              json_decode($d['vals'], 1),
                              json_decode($e['vals'], 1)
                      ));
            }
          }
        }
      }
      // Proceeding to the actions update is after in case we needed to restore
      if ( $d['action'] === 'update' ){
        if ( self::$db->update($d['tab'], json_decode($d['vals'], 1), json_decode($d['rows'], 1)) ){
          self::$dbs->update(self::$dbs_table, ["state" => 1], ["id" => $d['id']]);
          $to_log['updated_real']++;
        }
        else if ( self::$db->select($d['tab'], [], \bbn\tools::merge_arrays(json_decode($d['rows'], 1), json_decode($d['vals'], 1))) ){
          self::$dbs->update(self::$dbs_table, ["state" => 1], ["id" => $d['id']]);
        }
        else{
          if ( $num_try > self::$max_retry ){
            self::$dbs->update(self::$dbs_table, ["state" => 5], ["id" => $d['id']]);
            $to_log['num_problems']++;
            array_push($to_log['problems'], "Problem while syncing (update), check data with status 5 and ID ".$d['id']);
          }
          $retry = 1;
        }
      }
      // Callback number 2
      if ( isset(self::$methods['cbf2']) ){
        self::cbf2($d);
      }
    }
    
    
    $res = [];
    foreach ( $to_log as $k => $v ){
      if ( !empty($v) ){
        $res[$k] = $v;
      }
    }
    if ( $retry && ( $num_try <= self::$max_retry ) ){
      $res = \bbn\tools::merge_arrays($res, self::sync($db, $dbs, $dbs_table, $num_try));
    }
    else{
      self::$db->set_error_mode($mode_db);
      self::$dbs->set_error_mode($mode_dbs);
      self::enable();
    }
    return $res;
  }
}
?>