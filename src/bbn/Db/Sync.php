<?php
namespace bbn\Db;

use bbn;
use bbn\Db;
use bbn\X;
use bbn\Str;

class Sync
{

  /**
   * @var db The current DB connection
   */
  protected static $current_connection = false;

  /**
   * @var array The sync connection information
   */
  protected static $sync_connection;

  /**
   * @var bool
   **/
  protected static $has_history = false;

  /**
   * @var array The tables to be synchronized in the current DB.
   */
  protected static $tables = [];

  /**
   * @var string The name of the sync table.
   */
  protected static $sync_table = 'dbsync';

  /** @var array */
  protected static $methods = [];

  /**
   * @var array The default configuration for the sync database.
   */
  protected static $default_cfg = [
    'engine' => 'sqlite',
    'host' => 'localhost',
    'db' => 'dbsync'
  ];

  /**
   * @var bool Remains false until the function is initiated.
   */
  private static $is_init = false;

  /**
   * @var bool
   */
  private static $is_checked;

  /**
   * @var bool
   */
  private static $disabled = false;

  /**
   * @var int The max number of times the rows is error status will be tried before abandonning.
   */
  private static $max_retry = 5;

  /**
   * @todo
   *
   * @param string $name
   * @param array  $arguments
   * @return void
   */
  final public static function __callStatic(string $name, array $arguments)
  {
    if (($name === 'cbf1') || ($name === 'cbf2')) {
      return \call_user_func_array(self::$methods[$name], $arguments);
    }
  }

  /**
   * @todo
   *
   * @param string   $name
   * @param callable $fn
   * @return void
   */
  final public static function addMethod(string $name, callable $fn): void
  {
    self::$methods[$name] = \Closure::bind($fn, null, __CLASS__);
  }

  /**
   * @todo
   *
   * @return void
   */
  private static function log(): void
  {
    $args = \func_get_args();
    foreach ($args as $a){
      X::log($a, 'dbsync');
    }
  }

  /**
   * @param bbn\Db $db
   * @param array $sync_cfg
   * @param array  $tables
   * @param string  $sync_table
   * @return void
   */
  public static function init(Db $db, array $sync_cfg = [], array $tables = [], string $sync_table = ''): void
  {
    if (self::$is_init) {
      throw new \Exception("Impossible to init twice the dbsync class");
    }
    self::$current_connection = $db;


    if (!empty($sync_table)) {
      self::$sync_table = $sync_table;
    }
    if (!Str::checkName(self::$sync_table)) {
      throw new \Exception(X::_("Table name not allowed"));
    }
    if (empty($sync_cfg)) {
      self::$sync_connection = new Db(self::$default_cfg);
    }
    elseif (isset($sync_cfg['connection'])) {
      if (is_object($sync_cfg['connection']) && (is_a($sync_cfg['connection'], '\\bbn\\Db')
          || is_subclass_of($sync_cfg['connection'], '\\bbn\\Db'))
      ) {
        self::$sync_connection = $sync_cfg['connection'];
      }
      else {
        throw new \Exception(X::_("Invalid connection given to the synchronization class"));
      }
    }
    elseif (isset($sync_cfg['engine'])) {
      if (($sync_cfg['engine'] === 'sqlite')
          || ($sync_cfg['engine'] !== self::$current_connection->getEngine())
      ) {
        self::$sync_connection = new Db($sync_cfg);
      }
      elseif (isset($sync_cfg['db']) && !isset($sync_cfg['user'])) {
        self::$sync_connection =& self::$current_connection;
        self::$sync_table = self::$sync_connection->tfn($sync_cfg['db'].'.'.self::$sync_table);
      }
    }
    elseif (isset($sync_cfg['db']) && !isset($sync_cfg['user'])) {
      self::$sync_connection =& self::$current_connection;
      self::$sync_table = self::$sync_connection->tfn($sync_cfg['db'].'.'.self::$sync_table);
    }
    self::$tables = $tables;
    self::$is_init = true;
    if (\count(self::$tables) === 0) {
      self::$tables = self::$current_connection->getTables();
    }
    if (\is_array(self::$tables)) {
      foreach (self::$tables as $i => $t){
        self::$tables[$i] = self::$current_connection->tfn($t);
      }
      self::$current_connection->setTrigger(
        '\\bbn\Db\\sync::trigger',
        ['delete', 'update', 'insert'],
        ['before', 'after'],
        self::$tables
      );
    }
  }
  
  public static function isInit()
  {
    return self::$is_init;
  }

  public static function createTable()
  {
    if (\is_array(self::$sync_connection)) {
      self::$sync_connection = new bbn\Db(self::$sync_connection);
    }
    if (class_exists('\\bbn\\Appui\\History') && bbn\Appui\History::$is_used) {
      self::$has_history = 1;
    }
    /** @todo Replace with DB functions */
    if (self::$sync_connection->getEngine() === 'sqlite') {
      self::$sync_connection->exec(
        sprintf(
          'CREATE TABLE "%s" (
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
          CREATE INDEX "state" "dbsync" ("state");',
          self::$table_sync
        )
      );
    }
    elseif (self::$sync_connection->getEngine() === 'mysql') {
      self::$sync_connection->exec(
        sprintf(
          "CREATE TABLE IF NOT EXISTS `%s` (
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
            ADD KEY `state` (`state`);",
            self::$table_sync
        )
      );
    }
  }

  /**
   * Checks if the initialization has been all right - performs it only once.
   * 
   * @return bool
   */
  public static function check(): bool
  {
    if (!isset(self::$is_checked)) {
      self::$is_checked = \is_object(self::$current_connection)
        && \is_object(self::$sync_connection)
        && self::$current_connection->check()
        && self::$sync_connection->check();
    }
    return self::$is_checked;
  }

  /**
   * Disable the sync trigger.
   *
   * @return void
   */
  public static function disable(): void
  {
    self::$disabled = true;
  }

  /**
   * Enable the sync trigger.
   *
   * @return void
   */
  public static function enable(): void
  {
    self::$disabled = false;
  }

  /**
   * Writes new rows in the sync table after a writing operation has happened.
   *
   * @param array $cfg Configuration array
   * @return array Resulting configuration
   */
  public static function trigger(array $cfg): array
  {
    /** @todo I would like to understand... */
    if (!isset($cfg['run'])) {
      $cfg['run'] = 1;
    }
    if (!isset($cfg['trig'])) {
      $cfg['run'] = 1;
    }
    if (!self::$disabled
        && self::check()
        && (count($cfg['tables']) === 1)
        && ($table = self::$current_connection->tfn(current($cfg['tables'])))
        && \in_array($table, self::$tables, true)
    ) {
      if ($cfg['moment'] === 'after') {
        // Case where we actually delete or restore through the $hcol column
        $values = [];
        if (X::hasProps($cfg, ['fields', 'values'], true)) {
          foreach ($cfg['fields'] as $i => $f) {
            $values[$f] = $cfg['values'][$i];
          }
        }
        $last_id = self::$sync_connection->lastId();
        self::$sync_connection->insert(
          self::$sync_table, [
          'db' => self::$current_connection->getCurrent(),
          'tab' => self::$current_connection->tsn($table),
          'action' => $cfg['kind'],
          'chrono' => microtime(true),
          'rows' => empty($cfg['where']) ? '[]' : X::jsonBase64Encode($cfg['where']),
          'vals' => empty($values) ? '[]' : X::jsonBase64Encode($values)
          ]
        );
        self::$sync_connection->setLastInsertId($last_id);
      }
    }
    return $cfg;
  }

  public static function callback1(callable $f)
  {
    self::addMethod('cbf1', $f);
  }

  public static function callback2(callable $f)
  {
    self::addMethod('cbf2', $f);
  }

  public static function deleteCompleted(float $start = null)
  {
    if (!self::isInit()) {
      die("DB sync is not initiated");
    }
    if (!$start
        || !($start = self::$sync_connection->selectOne(
          self::$sync_table, 'MIN(chrono)', [
          ['db', 'NOT LIKE', self::$current_connection->getCurrent()],
          'state' => 0
          ]
        ))
    ) {
      $start = time();
    }
    // Deleting the entries prior to this sync we produced and have been seen by the twin process
    return self::$sync_connection->delete(
      self::$sync_table, [
      'db' => self::$current_connection->getCurrent(),
      'state'=> 1,
      ['chrono', '<', $start]
      ]
    );
  }

  public static function currentRowCfg($row): array
  {

  }

  public static function destRowCfg($row): array
  {

  }

  // Looking at the rows from the other DB with status = 0 and setting them to 1
  // Comparing the new rows with the ones from this DB
  // Deleting the rows from this DB which have state = 1
  public static function sync(bbn\Db $db, $dbs='', $sync_table='', $num_try = 0)
  {
    if (!self::isInit()) {
      die("DB sync is not initiated");
    }
    self::disable();
    $mode_db = self::$current_connection->getErrorMode();
    $mode_dbs = self::$sync_connection->getErrorMode();
    self::$current_connection->setErrorMode("continue");
    self::$sync_connection->setErrorMode("continue");

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

    $to_log['deleted_sync'] = self::deleteCompleted();

    $retry = false;

    // Selecting the entries inserted
    $ds = self::$sync_connection->rselectAll(
      self::$sync_table, ['id', 'tab', 'vals', 'chrono'], [
      ['db', '!=', self::$current_connection->getCurrent()],
      ['state', '=', 0],
      ['action', 'LIKE', 'INSERT']
      ], [
      'chrono' => 'ASC',
      'id' => 'ASC'
      ]
    );
    // They just have to be inserted
    foreach ($ds as $i => $d){
      if (isset(self::$methods['cbf1'])) {
        self::cbf1($d);
      }
      $vals = X::jsonBase64Decode($d['vals']);
      if (!\is_array($vals)) {
        $to_log['num_problems']++;
        $to_log['problems'][] = "Hey, look urgently at the row $d[id]!";
      }
      elseif (self::$current_connection->insert($d['tab'], $vals)) {
        if (isset(self::$methods['cbf2'])) {
          self::cbf2($d);
        }
        $to_log['inserted_sync']++;
        self::$sync_connection->update(self::$sync_table, ["state" => 1], ["id" => $d['id']]);
      }
      elseif (self::$current_connection->select($d['tab'], [], $vals)) {
        self::$sync_connection->update(self::$sync_table, ["state" => 1], ["id" => $d['id']]);
      }
      else{
        if ($num_try > self::$max_retry) {
          $to_log['num_problems']++;
          $to_log['problems'][] = "Problem while syncing (insert), check data with status 5 and ID ".$d['id'];
          self::$sync_connection->update(self::$sync_table, ["state" => 5], ["id" => $d['id']]);
        }
        $retry = 1;
      }
    }


    // Selecting the entries modified and deleted in the twin DB,
    // ordered by table and rows (so the same go together)
    $ds = self::$sync_connection->rselectAll(
      self::$sync_table, ['id', 'tab', 'action', 'rows', 'vals', 'chrono'], [
      ['db', '!=', self::$current_connection->getCurrent()],
      ['state', '=', 0],
      ['rows', '!=', '[]'],
      ['action', '!=', 'insert']
      ], [
      'tab' => 'ASC',
      'rows' => 'ASC',
      'chrono' => 'ASC',
      'id' => 'ASC'
      ]
    );
    foreach ($ds as $i => $d){
      // Executing the first callback
      $d['rows'] = X::jsonBase64Decode($d['rows']);
      $d['vals'] = X::jsonBase64Decode($d['vals']);
      if (isset(self::$methods['cbf1'])) {
        self::cbf1($d);
      }
      // Proceeding to the actions: delete is before
      if (strtolower($d['action']) === 'delete') {
        if (self::$current_connection->delete($d['tab'], $d['rows'])) {
          self::$sync_connection->update(self::$sync_table, ["state" => 1], ["id" => $d['id']]);
          $to_log['deleted_real']++;
        }
        elseif (!self::$current_connection->select($d['tab'], [], $d['rows'])) {
          self::$sync_connection->update(self::$sync_table, ["state" => 1], ["id" => $d['id']]);
        }
        else{
          if ($num_try > self::$max_retry) {
            self::$sync_connection->update(self::$sync_table, ["state" => 5], ["id" => $d['id']]);
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
      $each = self::$sync_connection->rselectAll(
        self::$sync_table, ['id', 'chrono', 'action', 'vals'], [
        ['db', '=', self::$current_connection->getCurrent()],
        ['tab', '=', $d['tab']],
        ['rows', '=', $d['rows']],
        ['chrono', '>=', $d['chrono']],
        ['chrono', '<', $next_time],
        ]
      );
      if (\count($each) > 0) {
        $to_log['num_problems']++;
        $to_log['problems'][] = "Conflict!";
        $to_log['problems'][] = $d;
        foreach ($each as $e){
          $e['vals'] = X::jsonBase64Decode($e['vals']);
          // If it's deleted locally and updated on the twin we restore
          if (strtolower($e['action']) === 'delete') {
            if (strtolower($d['action']) === 'update') {
              if (!self::$current_connection->insertUpdate(
                $d['tab'],
                X::mergeArrays(
                  $e['vals'],
                  $d['vals']
                )
              )
              ) {
                $to_log['num_problems']++;
                $to_log['problems'][] = "insert_update number 1 had a problem";
              }
            }
          }
          // If it's updated locally and deleted in the twin we restore
          elseif (strtolower($e['action']) === 'update') {
            if (strtolower($d['action']) === 'delete') {
              if (!self::$current_connection->insertUpdate($d['tab'], X::mergeArrays($d['vals'], $e['vals']))) {
                $to_log['num_problems']++;
                $to_log['problems'][] = "insert_update had a problem";
              }
            }
            // If it's updated locally and in the twin we merge the values for the update
            elseif (strtolower($d['action']) === 'update') {
              $d['vals'] = X::mergeArrays($d['vals'], $e['vals']);
            }
          }
        }
      }
      // Proceeding to the actions update is after in case we needed to restore
      if (strtolower($d['action']) === 'update') {
        X::log(X::mergeArrays($d['rows'], $d['vals']), 'synct');
        if (self::$current_connection->update($d['tab'], $d['vals'], $d['rows'])) {
          self::$sync_connection->update(self::$sync_table, ["state" => 1], ["id" => $d['id']]);
          $to_log['updated_real']++;
        }
        elseif (self::$current_connection->count($d['tab'], X::mergeArrays($d['rows'], $d['vals']))) {
          self::$sync_connection->update(self::$sync_table, ["state" => 1], ["id" => $d['id']]);
        }
        else{
          if ($num_try > self::$max_retry) {
            self::$sync_connection->update(self::$sync_table, ["state" => 5], ["id" => $d['id']]);
            $to_log['num_problems']++;
            $to_log['problems'][] = "Problem while syncing (update), check data with status 5 and ID ".$d['id'];
          }
          $retry = 1;
        }
      }
      // Callback number 2
      if (isset(self::$methods['cbf2'])) {
        self::cbf2($d);
      }
    }


    $res = [];
    foreach ($to_log as $k => $v){
      if (!empty($v)) {
        $res[$k] = $v;
      }
    }
    self::$current_connection->setErrorMode($mode_db);
    self::$sync_connection->setErrorMode($mode_dbs);
    self::enable();
    if ($retry && ( $num_try <= self::$max_retry )) {
      $res = X::mergeArrays($res, self::sync($db, $dbs, $sync_table, $num_try));
    }
    return $res;
  }
}
