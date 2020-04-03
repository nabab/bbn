<?php
namespace bbn\appui;

use bbn;

class dbsync
{

  /**
   * @var db The DB connection
   */
	public static $db = false;
  public static $dbs = false;
  public static $has_history = false;
  public static $tables = [];
  public static $dbs_table = 'dbsync';

  protected static $methods = [];

  private static $_is_init = false;
  private static $_is_checked;
  private static $default_cfg = [
    'engine' => 'sqlite',
    'host' => 'localhost',
    'db' => 'dbsync'
  ];
  private static $disabled = false;
  private static $max_retry = 5;


  final public static function __callStatic($name, $arguments)
  {
    if ( ($name === 'cbf1') || ($name === 'cbf2') ){
      return \call_user_func_array(self::$methods[$name], $arguments);
    }
  }

  final public static function addMethod($name, $fn){
    self::$methods[$name] = \Closure::bind($fn, NULL, __CLASS__);
  }

  final protected static function protectedMethod(){
    echo __METHOD__ . " was called" . PHP_EOL;
  }

  private static function log(){
    $args = \func_get_args();
    foreach ( $args as $a ){
      bbn\x::log($a, 'dbsync');
    }
  }

  private static function def($dbs, $dbs_table=''){
    if ( empty($dbs) ){
      $dbs = self::$default_cfg;
    }
    else if ( \is_string($dbs) ){
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
    if ( !bbn\str::check_name(self::$dbs_table) ){
      self::log("Table name not allowed", self::$dbs_table);
      die("Table name not allowed");
    }
  }

  /**
   * @param bbn\db $db
   * @param string $dbs
   * @param array $tables
   * @param string $dbs_table
   * @return void
   */
	public static function init(bbn\db $db, $dbs='', $tables=[], $dbs_table=''){
    self::$db = $db;
    self::def($dbs, $dbs_table);
    self::$tables = $tables;
    self::$_is_init = true;
    if ( \count(self::$tables) === 0 ){
      self::$tables = self::$db->get_tables();
    }
    if ( \is_array(self::$tables) ){
      foreach ( self::$tables as $i => $t ){
        self::$tables[$i] = self::$db->table_full_name($t);
      }
      self::$db->set_trigger(
            '\\bbn\appui\\dbsync::trigger',
            ['delete', 'update', 'insert'],
            ['before', 'after'],
            self::$tables);
    }
  }
  
  public static function is_init()
  {
    return self::$_is_init;
  }

  public static function first_call(){
    if ( \is_array(self::$dbs) ){
      self::$dbs = new bbn\db(self::$dbs);
    }
    if ( class_exists('\\bbn\\appui\\history') && bbn\appui\history::$is_used ){
      self::$has_history = 1;
    }
    /** @todo Replace with DB functions */
    if ( (self::$dbs->engine === 'sqlite') && !\in_array(self::$dbs_table, self::$dbs->get_tables()) ){
      self::$dbs->exec(<<<MYSQL
        CREATE TABLE "dbsync" (
          "id" INTEGER PRIMARY KEY  NOT NULL ,
          "db" TEXT NOT NULL ,
          "tab" TEXT NOT NULL ,
          "chrono" REAL NOT NULL,
          "action" TEXT NOT NULL,
          "rows" TEXT,"vals" TEXT,
          "state" INTEGER NOT NULL DEFAULT (0)
        );
        CREATE INDEX "db" "dbsync" ("db");
        CREATE INDEX "tab" "dbsync" ("tab");
        CREATE INDEX "chrono" "dbsync" ("chrono");
        CREATE INDEX "action" "dbsync" ("action");
        CREATE INDEX "state" "dbsync" ("state");
MYSQL
      );
    }
    else if ( (self::$dbs->engine === 'mysql') && !\in_array(self::$dbs_table, self::$dbs->get_tables()) ){
      self::$dbs->exec(<<<MYSQL
        CREATE TABLE IF NOT EXISTS `dbsync` (
          `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `db` varchar(50) NOT NULL,
          `tab` varchar(50) NOT NULL,
          `chrono` decimal(14,4) unsigned NOT NULL,
          `action` varchar(20) NOT NULL,
          `rows` text,
          `vals` longtext,
          `state` int(10) NOT NULL DEFAULT '0'
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
        ALTER TABLE `dbsync`
          ADD PRIMARY KEY (`id`),
          ADD KEY `db` (`db`),
          ADD KEY `tab` (`tab`),
          ADD KEY `chrono` (`chrono`),
          ADD KEY `action` (`action`),
          ADD KEY `state` (`state`);
MYSQL
      );
    }
  }
	/**
	 * Checks if the initialization has been all right
	 * @return bool
	 */
  public static function check(){
    if (!isset(self::$_is_checked)) {
      self::$_is_checked = \is_object(self::$db) && \is_object(self::$dbs) && self::$db->check() && self::$dbs->check();
    }
    return self::$_is_checked;
  }

  public static function disable(){
    self::$disabled = 1;
  }

  public static function enable(){
    self::$disabled = false;
  }

	/**
	 * Gets all information about a given table
   *
   * @param array $cfg Configuration array
	 * @return array Resulting configuration
	 */
  public static function trigger(array $cfg){
    self::first_call();
    if ( !isset($cfg['run']) ){
      $cfg['run'] = 1;
    }
    if ( !isset($cfg['trig']) ){
      $cfg['run'] = 1;
    }
    if ( !self::$disabled &&
      self::check() &&
      (count($cfg['tables']) === 1) &&
      ($table = self::$db->tfn(current($cfg['tables']))) &&
      \in_array($table, self::$tables, true)
    ){
      if ( $cfg['moment'] === 'after' ){
        // Case where we actually delete or restore through the $hcol column
        $values = [];
        if ( !empty($cfg['fields']) && !empty($cfg['values']) ){
          foreach ( $cfg['fields'] as $i => $f ){
            $values[$f] = $cfg['values'][$i];
          }
        }
        self::$dbs->insert(self::$dbs_table, [
          'db' => self::$db->current,
          'tab' => self::$db->tsn($table),
          'action' => $cfg['kind'],
          'chrono' => microtime(true),
          'rows' => empty($cfg['where']) ? '[]' : bbn\x::json_base64_encode($cfg['where']),
          'vals' => empty($values) ? '[]' : bbn\x::json_base64_encode($values)
        ]);
      }
    }
    return $cfg;
  }

  public static function callback1(\Closure $f){
    self::addMethod('cbf1', $f);
  }

  public static function callback2(\Closure $f){
    self::addMethod('cbf2', $f);
  }

  public static function delete_completed(float $start = null)
  {
    if (!self::is_init()) {
      die("DB sync is not initiated");
    }
    if (!$start
        || !($start = self::$dbs->select_one(self::$dbs_table, 'MIN(chrono)', [
          ['db', 'NOT LIKE', self::$db->current],
          'state' => 0
        ]))
    ) {
      $start = time();
    }
    // Deleting the entries prior to this sync we produced and have been seen by the twin process
    return self::$dbs->delete(self::$dbs_table, [
      'db' => self::$db->current,
      'state'=> 1,
      ['chrono', '<', $start]
    ]);
  }

  // Looking at the rows from the other DB with status = 0 and setting them to 1
  // Comparing the new rows with the ones from this DB
  // Deleting the rows from this DB which have state = 1
  public static function sync(bbn\db $db, $dbs='', $dbs_table='', $num_try = 0)
  {
    if (!self::is_init()) {
      die("DB sync is not initiated");
    }
    self::disable();
    $mode_db = self::$db->get_error_mode();
    $mode_dbs = self::$dbs->get_error_mode();
    self::$db->set_error_mode("continue");
    self::$dbs->set_error_mode("continue");
    if ( !$num_try ){
      self::def($dbs, $dbs_table);
      self::first_call();
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

    $to_log['deleted_sync'] = self::delete_completed();

    $retry = false;

    // Selecting the entries inserted
    $ds = self::$dbs->rselect_all(self::$dbs_table, ['id', 'tab', 'vals', 'chrono'], [
      ['db', '!=', self::$db->current],
      ['state', '=', 0],
      ['action', 'LIKE', 'INSERT']
    ], [
      'chrono' => 'ASC',
      'id' => 'ASC'
    ]);
    // They just have to be inserted
    foreach ( $ds as $i => $d ){
      if ( isset(self::$methods['cbf1']) ){
        self::cbf1($d);
      }
      $vals = \bbn\x::json_base64_decode($d['vals']);
      if ( !\is_array($vals) ){
        $to_log['num_problems']++;
        $to_log['problems'][] = "Hey, look urgently at the row $d[id]!";
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
          $to_log['problems'][] = "Problem while syncing (insert), check data with status 5 and ID ".$d['id'];
          self::$dbs->update(self::$dbs_table, ["state" => 5], ["id" => $d['id']]);
        }
        $retry = 1;
      }
    }


    // Selecting the entries modified and deleted in the twin DB,
    // ordered by table and rows (so the same go together)
    $ds = self::$dbs->rselect_all(self::$dbs_table, ['id', 'tab', 'action', 'rows', 'vals', 'chrono'], [
      ['db', '!=', self::$db->current],
      ['state', '=', 0],
      ['rows', '!=', '[]'],
      ['action', '!=', 'insert']
    ], [
      'tab' => 'ASC',
      'rows' => 'ASC',
      'chrono' => 'ASC',
      'id' => 'ASC'
    ]);
    foreach ( $ds as $i => $d ){
      // Executing the first callback
      $d['rows'] = bbn\x::json_base64_decode($d['rows']);
      $d['vals'] = bbn\x::json_base64_decode($d['vals']);
      if ( isset(self::$methods['cbf1']) ){
        self::cbf1($d);
      }
      // Proceeding to the actions: delete is before
      if ( strtolower($d['action']) === 'delete' ){
        if ( self::$db->delete($d['tab'], $d['rows']) ){
          self::$dbs->update(self::$dbs_table, ["state" => 1], ["id" => $d['id']]);
          $to_log['deleted_real']++;
        }
        else if ( !self::$db->select($d['tab'], [], $d['rows']) ){
          self::$dbs->update(self::$dbs_table, ["state" => 1], ["id" => $d['id']]);
        }
        else{
          if ( $num_try > self::$max_retry ){
            self::$dbs->update(self::$dbs_table, ["state" => 5], ["id" => $d['id']]);
            $to_log['num_problems']++;
            $to_log['problems'][] = "Problem while syncing (delete), check data with status 5 and ID ".$d['id'];
          }
          $retry = 1;
        }
      }
      // Checking if there is another change done to this record and when in the twin DB
      $next_time = (
              isset($ds[$i+1]) &&
              ($ds[$i+1]['tab'] === $d['tab']) &&
              ($ds[$i+1]['rows'] === $d['rows'])
            ) ? $ds[$i+1]['chrono'] : microtime();
      // Looking for the actions done on this specific record in our database
      // between the twin change and the next (or now if there is no other change)
      $each = self::$dbs->rselect_all(self::$dbs_table, ['id', 'chrono', 'action', 'vals'], [
        ['db', '=', self::$db->current],
        ['tab', '=', $d['tab']],
        ['rows', '=', $d['rows']],
        ['chrono', '>=', $d['chrono']],
        ['chrono', '<', $next_time],
      ]);
      if ( \count($each) > 0 ){
        $to_log['num_problems']++;
        $to_log['problems'][] = "Conflict!";
        $to_log['problems'][] = $d;
        foreach ( $each as $e ){
          $e['vals'] = bbn\x::json_base64_decode($e['vals']);
          // If it's deleted locally and updated on the twin we restore
          if ( strtolower($e['action']) === 'delete' ){
            if ( strtolower($d['action']) === 'update' ){
              if ( !self::$db->insert_update(
                      $d['tab'],
                      bbn\x::merge_arrays(
                        $e['vals'],
                        $d['vals']
                      ))
              ){
                $to_log['num_problems']++;
                $to_log['problems'][] = "insert_update number 1 had a problem";
              }
            }
          }
          // If it's updated locally and deleted in the twin we restore
          else if ( strtolower($e['action']) === 'update' ){
            if ( strtolower($d['action']) === 'delete' ){
              if ( !self::$db->insert_update($d['tab'], bbn\x::merge_arrays($d['vals'], $e['vals'])) ){
                $to_log['num_problems']++;
                $to_log['problems'][] = "insert_update had a problem";
              }
            }
          // If it's updated locally and in the twin we merge the values for the update
            else if ( strtolower($d['action']) === 'update' ){
              $d['vals'] = bbn\x::merge_arrays($d['vals'], $e['vals']);
            }
          }
        }
      }
      // Proceeding to the actions update is after in case we needed to restore
      if ( strtolower($d['action']) === 'update' ){
        \bbn\x::log(bbn\x::merge_arrays($d['rows'], $d['vals']), 'synct');
        if ( self::$db->update($d['tab'], $d['vals'], $d['rows']) ){
          self::$dbs->update(self::$dbs_table, ["state" => 1], ["id" => $d['id']]);
          $to_log['updated_real']++;
        }
        elseif ( self::$db->count($d['tab'], bbn\x::merge_arrays($d['rows'], $d['vals'])) ){
          self::$dbs->update(self::$dbs_table, ["state" => 1], ["id" => $d['id']]);
        }
        else{
          if ( $num_try > self::$max_retry ){
            self::$dbs->update(self::$dbs_table, ["state" => 5], ["id" => $d['id']]);
            $to_log['num_problems']++;
            $to_log['problems'][] = "Problem while syncing (update), check data with status 5 and ID ".$d['id'];
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
    self::$db->set_error_mode($mode_db);
    self::$dbs->set_error_mode($mode_dbs);
    self::enable();
    if ( $retry && ( $num_try <= self::$max_retry ) ){
      $res = bbn\x::merge_arrays($res, self::sync($db, $dbs, $dbs_table, $num_try));
    }
    return $res;
  }

}