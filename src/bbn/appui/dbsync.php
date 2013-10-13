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
          $disabled = false;
	
  
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
  
  private static function define($dbs, $dbs_table='')
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
    self::define($dbs, $dbs_table);
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
                  !isset($values[$pri]) ){
            $values[$pri] = self::$db->new_id($table);
            $res['values'] = $values;
            return $res;
          }
        }
      }
      else if ( $moment === 'after' ){
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
        else if ( $kind === 'insert' ){
          $values = self::$db->select($table, [], $values);
        }
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
  public static function sync(\bbn\db\connection $db, $dbs='', $dbs_table=''){
    self::define($dbs, $dbs_table);
    self::first_call();
    self::disable();

    $start = ( $test = self::$dbs->get_one("
      SELECT MIN(moment)
      FROM ".self::$dbs->escape(self::$dbs_table)."
      WHERE db NOT LIKE ?
      AND state = 0",
      self::$db->current) ) ? $test : date('Y-m-d H:i:s');
    // Deleting the entries prior to this sync we produced and have been seen by the twin process
    self::$dbs->delete(self::$dbs_table, [
      ['db', 'LIKE', self::$db->current],
      ['state', '=', 1],
      ['moment', '<', $start]
    ]);
    
    // Selecting the entries inserted
    $ds = self::$dbs->rselect_all(self::$dbs_table, ['tab', 'rows', 'moment'], [
      ['db', 'NOT LIKE', self::$db->current],
      ['state', '=', 0],
      ['rows', 'NOT LIKE', '[]'],
      ['action', 'LIKE', 'insert']
    ], [
      'moment' => 'ASC',
    ]);
    // They just have to be inserted
    foreach ( $ds as $i => $d ){
      if ( isset(self::$methods['cbf1']) ){
        self::cbf1($d);
      }
      if ( self::$db->insert($d['tab'], json_decode($d['vals'], 1)) ){
        if ( isset(self::$methods['cbf2']) ){
          self::cbf2($d);
        }
        self::$dbs->update(self::$dbs_table, ["state" => 1], ["id" => $d['id']]);
      }
      else{
        self::$dbs->update(self::$dbs_table, ["state" => 5], ["id" => $d['id']]);
        self::log("Problem while syncing, check data with status 5 and ID ".$d['id']);
      }
    }

    // Selecting the entries modified and deleted in the twin DB,
    // ordered by table and rows (so the same go together)
    $ds = self::$dbs->rselect_all(self::$dbs_table, ['tab', 'rows', 'moment'], [
      ['db', 'NOT LIKE', self::$db->current],
      ['state', '=', 0],
      ['rows', 'NOT LIKE', '[]'],
      ['action', 'NOT LIKE', 'insert']
    ], [
      'tab' => 'ASC',
      'rows' => 'ASC',
      'moment' => 'ASC',
    ]);
    foreach ( $ds as $i => $d ){
      // Executing the first callback
      if ( isset(self::$methods['cbf1']) ){
        self::cbf1($d);
      }
      // Proceeding to the actions: delete is before
      if ( $d['action'] === 'delete' ){
        if ( self::$db->delete($d['tab'], json_decode($d['rows'], 1)) ){
          $deleted = 1;
        }
        else{
          self::$dbs->update(self::$dbs_table, ["state" => 5], ["id" => $d['id']]);
          self::log("Problem while syncing, check data with status 5 and ID ".$d['id']);
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
        self::log("Conflict!", $d);
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
                self::log("insert_update number 1 had a problem");
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
                self::log("insert_update had a problem");
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
          $updated = 1;
        }
        else{
          self::$dbs->update(self::$dbs_table, ["state" => 5], ["id" => $d['id']]);
          self::log("Problem while syncing, check data with status 5 and ID ".$d['id']);
        }
      }
      // Callback number 2
      if ( isset(self::$methods['cbf2']) ){
        self::cbf2($d);
      }
      self::$dbs->update(self::$dbs_table, ["state" => 1], ["id" => $d['id']]);
    }
    self::enable();
  }
}
?>