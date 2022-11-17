<?php
namespace bbn\Db;

use bbn;
use bbn\Db;
use bbn\X;
use bbn\Str;

class Sync2 extends bbn\Models\Cls\Db
{

  use bbn\Models\Tts\Singleton,
      bbn\Models\Tts\Dbconfig,
      bbn\Models\Tts\Optional;
  /**
   * @var db The current DB connection
   */
  protected $current_connection;

  /**
   * @var array The sync connection information
   */
  protected $sync_connection;

  /**
   * @var bool
   **/
  protected $has_history = false;

  /**
   * @var array The tables to be synchronized in the current DB.
   */
  protected $tables = [];

  /**
   * @var string The name of the sync table.
   */
  protected $sync_table = 'dbsync';

  /** @var array */
  protected $methods = [];

  /**
   * @var array The default configuration for the sync database.
   */
  protected static $default_cfg = [
    'engine' => 'sqlite',
    'host' => 'localhost',
    'db' => 'dbsync'
  ];

  /**
   * @var array The synchronization database structure.
   */
  protected static $default_class_cfg = [
    'table' => 'dbsync',
    'tables' => [
      'dbsync' => 'dbsync'
    ],
    'arch' => [
      'dbsync' => [
        'id' => 'id',
        'tab' => 'tab',
        'action' => 'action',
        'rows' => 'rows',
        'vals' => 'vals'
      ]
    ]
  ];

  /**
   * @var bool Remains false until the function is initiated.
   */
  private $is_init = false;

  /**
   * @var bool
   */
  private $is_checked;

  /**
   * @var bool
   */
  private static $all_disabled = false;

  /**
   * @var bool
   */
  private $disabled = false;

  /**
   * @var int The max number of times the rows is error status will be tried before abandonning.
   */
  private $max_retry = 5;

  public function __construct(Db $db, array $tables, array $sync_cfg = null, array $arch = [])
  {
    parent::__construct($db);
    Singleton::init($this);
    $this->_init_class_cfg($arch);
    $this->opt = bbn\Appui\Option::getInstance();
    if ($this->opt) {
      self::retrieverInit($this);
    }
  }

  /**
   * @param bbn\Db $db
   * @param array $sync_cfg
   * @param array  $tables
   * @param string  $sync_table
   * @return void
   */
  public function init(db $db, array $sync_cfg = [], array $tables = [], string $sync_table = ''): void
  {
    if ($this->is_init) {
      throw new \Exception("Impossible to init twice the dbsync class");
    }
    $this->current_connection = $db;


    if (!empty($sync_table)) {
      $this->sync_table = $sync_table;
    }
    if (!Str::checkName($this->sync_table)) {
      throw new \Exception(X::_("Table name not allowed"));
    }
    if (empty($sync_cfg)) {
      $this->sync_connection = new Db(self::$default_cfg);
    }
    elseif (isset($sync_cfg['connection'])) {
      if (is_object($sync_cfg['connection']) && (is_a($sync_cfg['connection'], '\\bbn\\Db')
          || is_subclass_of($sync_cfg['connection'], '\\bbn\\Db'))
      ) {
        $this->sync_connection = $sync_cfg['connection'];
      }
      else {
        throw new \Exception(X::_("Invalid connection given to the synchronization class"));
      }
    }
    elseif (isset($sync_cfg['engine'])) {
      if (($sync_cfg['engine'] === 'sqlite')
          || ($sync_cfg['engine'] !== $this->current_connection->getEngine())
      ) {
        $this->sync_connection = new Db($sync_cfg);
      }
      elseif (isset($sync_cfg['db']) && !isset($sync_cfg['user'])) {
        $this->sync_connection =& $this->current_connection;
        $this->sync_table = $this->sync_connection->tfn($this->sync_table, $sync_cfg['db']);
      }
    }
    elseif (isset($sync_cfg['db']) && !isset($sync_cfg['user'])) {
      $this->sync_connection =& $this->current_connection;
      $this->sync_table = $this->sync_connection->tfn($this->sync_table, $sync_cfg['db']);
    }
    $this->tables = $tables;
    $this->is_init = true;
    if (\count($this->tables) === 0) {
      $this->tables = $this->current_connection->getTables();
    }
    if (\is_array($this->tables)) {
      foreach ($this->tables as $i => $t){
        $this->tables[$i] = $this->current_connection->tableFullName($t);
      }
      $this->current_connection->setTrigger(
        '\\bbn\Db\\sync::trigger',
        ['delete', 'update', 'insert'],
        ['before', 'after'],
        $this->tables
      );
    }
  }
  
  public function isInit()
  {
    return $this->is_init;
  }

  public function createTable()
  {
    if (\is_array($this->sync_connection)) {
      $this->sync_connection = new bbn\Db($this->sync_connection);
    }
    if (class_exists('\\bbn\\Appui\\History') && bbn\Appui\History::$is_used) {
      $this->has_history = 1;
    }
    /** @todo Replace with DB functions */
    if ($this->sync_connection->getEngine() === 'sqlite') {
      $this->sync_connection->exec(
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
          $this->table_sync
        )
      );
    }
    elseif ($this->sync_connection->getEngine() === 'mysql') {
      $this->sync_connection->exec(
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
            $this->table_sync
        )
      );
    }
  }

  /**
   * Checks if the initialization has been all right - performs it only once.
   * 
   * @return bool
   */
  public function check(): bool
  {
    if (!isset($this->is_checked)) {
      $this->is_checked = \is_object($this->current_connection)
        && \is_object($this->sync_connection)
        && $this->current_connection->check()
        && $this->sync_connection->check();
    }
    return $this->is_checked;
  }

  /**
   * Disable the sync trigger.
   *
   * @return void
   */
  public function disable(): void
  {
    $this->disabled = true;
  }

  /**
   * Enable the sync trigger.
   *
   * @return void
   */
  public function enable(): void
  {
    $this->disabled = false;
  }

  /**
   * Writes new rows in the sync table after a writing operation has happened.
   *
   * @param array $cfg Configuration array
   * @return array Resulting configuration
   */
  public function trigger(array $cfg): array
  {
    /** @todo I would like to understand... */
    if (!isset($cfg['run'])) {
      $cfg['run'] = 1;
    }
    if (!isset($cfg['trig'])) {
      $cfg['run'] = 1;
    }
    if (!$this->disabled
        && $this->check()
        && (count($cfg['tables']) === 1)
        && ($table = $this->current_connection->tfn(current($cfg['tables'])))
        && \in_array($table, $this->tables, true)
    ) {
      if ($cfg['moment'] === 'after') {
        // Case where we actually delete or restore through the $hcol column
        $values = [];
        if (X::hasProps($cfg, ['fields', 'values'], true)) {
          foreach ($cfg['fields'] as $i => $f) {
            $values[$f] = $cfg['values'][$i];
          }
        }
        $this->sync_connection->insert(
          $this->sync_table, [
          'db' => $this->current_connection->getCurrent(),
          'tab' => $this->current_connection->tsn($table),
          'action' => $cfg['kind'],
          'chrono' => microtime(true),
          'rows' => empty($cfg['where']) ? '[]' : X::jsonBase64Encode($cfg['where']),
          'vals' => empty($values) ? '[]' : X::jsonBase64Encode($values)
          ]
        );
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

  public function deleteCompleted(float $start = null)
  {
    if (!self::isInit()) {
      die("DB sync is not initiated");
    }
    if (!$start
        || !($start = $this->sync_connection->selectOne(
          $this->sync_table, 'MIN(chrono)', [
          ['db', 'NOT LIKE', $this->current_connection->getCurrent()],
          'state' => 0
          ]
        ))
    ) {
      $start = time();
    }
    // Deleting the entries prior to this sync we produced and have been seen by the twin process
    return $this->sync_connection->delete(
      $this->sync_table, [
      'db' => $this->current_connection->getCurrent(),
      'state'=> 1,
      ['chrono', '<', $start]
      ]
    );
  }

  public function currentRowCfg($row): array
  {

  }

  public function destRowCfg($row): array
  {

  }

  // Looking at the rows from the other DB with status = 0 and setting them to 1
  // Comparing the new rows with the ones from this DB
  // Deleting the rows from this DB which have state = 1
  public function sync(bbn\Db $db, $dbs='', $sync_table='', $num_try = 0)
  {
    if (!self::isInit()) {
      die("DB sync is not initiated");
    }
    self::disable();
    $mode_db = $this->current_connection->getErrorMode();
    $mode_dbs = $this->sync_connection->getErrorMode();
    $this->current_connection->setErrorMode("continue");
    $this->sync_connection->setErrorMode("continue");

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
    $ds = $this->sync_connection->rselectAll(
      $this->sync_table, ['id', 'tab', 'vals', 'chrono'], [
      ['db', '!=', $this->current_connection->getCurrent()],
      ['state', '=', 0],
      ['action', 'LIKE', 'INSERT']
      ], [
      'chrono' => 'ASC',
      'id' => 'ASC'
      ]
    );
    // They just have to be inserted
    foreach ($ds as $i => $d){
      if (isset($this->methods['cbf1'])) {
        self::cbf1($d);
      }
      $vals = X::jsonBase64Decode($d['vals']);
      if (!\is_array($vals)) {
        $to_log['num_problems']++;
        $to_log['problems'][] = "Hey, look urgently at the row $d[id]!";
      }
      elseif ($this->current_connection->insert($d['tab'], $vals)) {
        if (isset($this->methods['cbf2'])) {
          self::cbf2($d);
        }
        $to_log['inserted_sync']++;
        $this->sync_connection->update($this->sync_table, ["state" => 1], ["id" => $d['id']]);
      }
      elseif ($this->current_connection->select($d['tab'], [], $vals)) {
        $this->sync_connection->update($this->sync_table, ["state" => 1], ["id" => $d['id']]);
      }
      else{
        if ($num_try > $this->max_retry) {
          $to_log['num_problems']++;
          $to_log['problems'][] = "Problem while syncing (insert), check data with status 5 and ID ".$d['id'];
          $this->sync_connection->update($this->sync_table, ["state" => 5], ["id" => $d['id']]);
        }
        $retry = 1;
      }
    }


    // Selecting the entries modified and deleted in the twin DB,
    // ordered by table and rows (so the same go together)
    $ds = $this->sync_connection->rselectAll(
      $this->sync_table, ['id', 'tab', 'action', 'rows', 'vals', 'chrono'], [
      ['db', '!=', $this->current_connection->getCurrent()],
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
      if (isset($this->methods['cbf1'])) {
        self::cbf1($d);
      }
      // Proceeding to the actions: delete is before
      if (strtolower($d['action']) === 'delete') {
        if ($this->current_connection->delete($d['tab'], $d['rows'])) {
          $this->sync_connection->update($this->sync_table, ["state" => 1], ["id" => $d['id']]);
          $to_log['deleted_real']++;
        }
        elseif (!$this->current_connection->select($d['tab'], [], $d['rows'])) {
          $this->sync_connection->update($this->sync_table, ["state" => 1], ["id" => $d['id']]);
        }
        else{
          if ($num_try > $this->max_retry) {
            $this->sync_connection->update($this->sync_table, ["state" => 5], ["id" => $d['id']]);
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
      $each = $this->sync_connection->rselectAll(
        $this->sync_table, ['id', 'chrono', 'action', 'vals'], [
        ['db', '=', $this->current_connection->getCurrent()],
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
              if (!$this->current_connection->insertUpdate(
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
              if (!$this->current_connection->insertUpdate($d['tab'], X::mergeArrays($d['vals'], $e['vals']))) {
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
        if ($this->current_connection->update($d['tab'], $d['vals'], $d['rows'])) {
          $this->sync_connection->update($this->sync_table, ["state" => 1], ["id" => $d['id']]);
          $to_log['updated_real']++;
        }
        elseif ($this->current_connection->count($d['tab'], X::mergeArrays($d['rows'], $d['vals']))) {
          $this->sync_connection->update($this->sync_table, ["state" => 1], ["id" => $d['id']]);
        }
        else{
          if ($num_try > $this->max_retry) {
            $this->sync_connection->update($this->sync_table, ["state" => 5], ["id" => $d['id']]);
            $to_log['num_problems']++;
            $to_log['problems'][] = "Problem while syncing (update), check data with status 5 and ID ".$d['id'];
          }
          $retry = 1;
        }
      }
      // Callback number 2
      if (isset($this->methods['cbf2'])) {
        self::cbf2($d);
      }
    }


    $res = [];
    foreach ($to_log as $k => $v){
      if (!empty($v)) {
        $res[$k] = $v;
      }
    }
    $this->current_connection->setErrorMode($mode_db);
    $this->sync_connection->setErrorMode($mode_dbs);
    self::enable();
    if ($retry && ( $num_try <= $this->max_retry )) {
      $res = X::mergeArrays($res, self::sync($db, $dbs, $sync_table, $num_try));
    }
    return $res;
  }
}
