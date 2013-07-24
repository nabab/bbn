<?php
namespace bbn\appui;

use \bbn\str\text;

class dbsync
{
	
	private static
          /**
           * @var \bbn\db\connection The DB connection
           */
          $db = false,
          $dbs = false,
          $tables = [],
          $dbs_table = 'dbsync',
          $actions_triggered = ['insert', 'update', 'delete'],
          $default_cfg = [
            'engine' => 'sqlite',
            'host' => 'localhost',
            'db' => 'dbsync'
          ],
          $disabled = false;
	
  
  private static function define($dbs, $dbs_table='')
  {
    if ( empty($dbs) ){
      $dbs = self::$default_cfg;
    }
    if ( is_string($dbs) ){
      $dbs = [ 
        'engine' => 'sqlite',
        'host' => 'localhost',
        'db' => $dbs
      ];
    }
    self::$dbs = $dbs;
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
    self::$db->set_trigger(
            '\\bbn\\appui\\dbsync::trigger',
            self::$actions_triggered,
            'after',
            $tables);
	}
  
  public static function first_call()
  {
    if ( is_array(self::$dbs) ){
      self::$dbs = new \bbn\db\connection(self::$dbs);
    }
    self::$dbs->clear_cache();
    if ( (self::$dbs->engine === 'sqlite') && !in_array(self::$dbs_table, self::$dbs->get_tables()) ){
      self::$dbs->query('CREATE TABLE "dbsync" ("id" INTEGER PRIMARY KEY  NOT NULL ,"db" TEXT NOT NULL ,"table" TEXT NOT NULL ,"date" DATETIME NOT NULL,"action" TEXT NOT NULL ,"where" TEXT,"values" TEXT,"state" INTEGER NOT NULL DEFAULT (0) );');
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
    if ( !self::$disabled && ($moment === 'after') && self::check() && in_array($table, self::$tables) && in_array($kind, self::$actions_triggered) ){
      self::$dbs->insert(self::$dbs_table, [
        'db' => self::$db->current,
        'table' => $table,
        'action' => $kind,
        'date' => date('Y-m-d H:i:s'),
        'where' => json_encode($where),
        'values' => json_encode($values)
      ]);
    }
    return 1;
  }
  
  public static function sync(\bbn\db\connection $db, $dbs='', $dbs_table=''){
    self::define($dbs, $dbs_table);
    self::first_call();
    self::disable();
    $r = self::$dbs->query(
            self::$dbs->get_select(
                    self::$dbs_table, [], [
                      ['db', '!=', self::$db->current],
                      ['state', '<', "2"]
                    ],['date' => 'DESC']),
            self::$db->current,
            2);
    while ( $d = $r->get_row() ){
      var_dump($d);
    }
    self::enable();
  }
	
}
?>