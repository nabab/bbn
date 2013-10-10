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
            ['delete'],
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
    var_dump($table, $kind, $moment);
    self::first_call();
    $stable = self::$db->table_simple_name($table);
    if ( !self::$disabled && self::check() && in_array($table, self::$tables) ){
      if ( $moment === 'before' ){
        if ( $kind === 'delete' ){
          \bbn\tools::dump(self::$db->count($table, $where), self::$db->select($table, [], $where), self::$db->count($table, $where), self::$db->get_primary($table));
          $values = self::$db->select($table, [], $where);
        }
        else if ( $kind === 'insert' ){
          /*
          \bbn\tools::dump(self::$db->count($table, $where), self::$db->select($table, [], $where), self::$db->count($table, $where), self::$db->get_primary($table));
          $values = self::$db->select($table, [], $where);
           * 
           */
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
    return 1;
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
  // Deleting the rows from this DB which have status = 1
  public static function sync(\bbn\db\connection $db, $dbs='', $dbs_table=''){
    self::define($dbs, $dbs_table);
    self::first_call();
    self::disable();
    
    $r = self::$dbs->query(
      self::$dbs->get_select(
        self::$dbs_table, [], [
          ['db', '!=', self::$db->current],
          ['state', '=', "0"]
        ],[
          'rows' => 'ASC',
          'moment' => 'ASC'
        ]),
        self::$db->current,
        2);
    $todo = [];
    
    while ( $d = $r->get_row() ){
      if ( isset(self::$methods['cbf1']) ){
        self::cbf1($d);
      }
      switch ( $d['action'] ){
        case "insert":
          self::$db->insert($d['tab'], json_decode($d['vals'], 1));
          break;
        case "update":
          // If it has been deleted after by the current db user
          $is_deleted = self::$dbs->rselect(self::$dbs_table, [], [
            ['db', '=', self::$db->current],
            ['tab', '=', $d['tab']],
            ['action', '=', 'delete'],
            ['rows', '=', $d['rows']],
            ['moment', '>', $d['moment']]
          ]);
          // We undelete 
          $other_updates = self::$dbs->get_rows(
                  self::$dbs->get_select(
                          self::$dbs_table, [], [
                            ['db', '=', self::$db->current],
                            ['tab', '=', $d['tab']],
                            ['action', '=', 'update'],
                            ['rows', '=', $d['rows']],
                            ['state', '=', 1]]
                  ),
                  self::$db->current,
                  $d['tab'],
                  'update',
                  $d['rows'],
                  1);
          \bbn\tools::dump(self::$db->update($d['tab'], json_decode($d['vals'], 1), json_decode($d['rows'], 1)));
          break;
        case "delete":
          \bbn\tools::dump(self::$db->delete($d['tab'], json_decode($d['rows'], 1)));
          break;
      }
      if ( isset(self::$methods['cbf2']) ){
        self::cbf2($d);
      }
      self::$dbs->update(self::$dbs_table, ["state" => 1], ["id" => $d['id']]);
    }
    self::enable();
  }
}
?>