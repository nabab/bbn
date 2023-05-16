<?php
namespace bbn;

use Exception;
use bbn\Db\Engines;

/**
 * Half ORM half DB management, the simplest class for data queries.
 *
 * Hello world!
 *
 * @category  Database
 * @package Bbn
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version Release: <package_version>
 * @link https://bbn.io/bbn-php/doc/class/db
 * @since Apr 4, 2011, 23:23:55 +0000
 * @todo Check for the tables and column names legality in _treat_arguments
 */
class Db implements Db\Actions
{
  use Models\Tts\Cache;
  use Models\Tts\Retriever;

  /**
   * @var Db\Languages\Sql Can be other driver
   */
  protected $language;

  /**
   * The ODBC engine of this connection
   * @var string $engine
   */
  protected $engine;





  /** @var array The database engines allowed */
  protected static $engines = [
    'mysql' => 'nf nf-dev-mysql',
    'pgsql' => 'nf nf-dev-postgresql',
    'sqlite' => 'nf nf-dev-sqllite'
  ];

  /**
   * Constructor
   *
   * ```php
   * $dbtest = new bbn\Db(['db_user' => 'test','db_engine' => 'mysql','db_host' => 'host','db_pass' => 't6pZDwRdfp4IM']);
   *  // (void)
   * ```
   * @param null|array $cfg Mandatory db_user db_engine db_host db_pass
   * @throws Exception
   */
  public function __construct(array $cfg = [])
  {
    if (!isset($cfg['engine']) && \defined('BBN_DB_ENGINE')) {
      $cfg['engine'] = BBN_DB_ENGINE;
    }

    if (isset($cfg['engine'])) {
      if ($cfg['engine'] instanceof Engines) {
        $this->language = $cfg['engine'];
      }
      else {
        $engine = $cfg['engine'];
        $cls    = '\\bbn\\Db\\Languages\\'.ucwords($engine);

        if (!class_exists($cls)) {
          throw new Exception(X::_("The database engine %s is not recognized", $engine));
        }

        $this->language = new $cls($cfg);
      }

      self::retrieverInit($this);
      $this->cacheInit();

      if ($cfg = $this->getCfg()) {
        $this->postCreation();
        $this->engine = (string)$cfg['engine'];
        $this->startFancyStuff();
      }
    }

    if (!$this->engine) {
      $connection  = $cfg['engine'] ?? 'No engine';
      $connection .= '/'.($cfg['db'] ?? 'No DB');
      $this->log(X::_("Impossible to create the connection for").' '.$connection);
      throw new Exception(X::_("Impossible to create the connection for").' '.$connection);
    }
  }


  /**
   * Closes the connection making the object unusable.
   *
   * @return void
   */
  public function close(): void
  {
    if ($this->language) {
      $this->language->close();
      $this->setErrorMode('continue');
    }
  }


  /**
   * Says if the given database engine is supported or not
   * 
   * ```php
   * X::adump(
   *   $db->isEngineSupported("mysql"), // true
   *   $db->isEngineSupported("postgre"), // false
   *   $db->isEngineSupported("sqlite"), // true
   *   $db->isEngineSupported("mssql"), // false
   *   $db->isEngineSupported("test") // false
   * );
   * ```
   * 
   * @param string $engine
   *
   * @return bool
   */
  public static function isEngineSupported(string $engine): bool
  {
    return isset(self::$engines[$engine]);
  }


  /**
   * Returns the icon (CSS class from nerd fonts) for the given db engine
   * 
   * ```php
   * echo '<i class="'.$ctrl->db->getEngineIcon("mysql").'"></i>'; // nf nf-dev-mysql
   * ```
   * 
   * @param string $engine Name of the engine
   * 
   * @return string|null
   */
  public static function getEngineIcon(string $engine): ?string
  {
    return self::$engines[$engine] ?? null;
  }

  /**
   * Return the config of the language
   * 
   * ```php
   * adump($ctrl->db->getCfg("mysql"));
   * ```
   *
   * @return array
   */
  public function getCfg(): array
  {
    return $this->language->getCfg();
  }

  /**
   * Returns the engine used by the current connection.
   * 
   * ```php
   * X::adump($ctrl->db->getEngine()); // mysql
   * ```
   * 
   * @return string|null
   */
  public function getEngine(): ?string
  {
    return $this->engine;
  }


  /**
   * Returns the host of the current connection.
   * 
   * ```php
   * X::adump($ctrl->db->getHost()); // db.m3l.co
   * ```
   * 
   * @return string|null
   */
  public function getHost(): ?string
  {
    return $this->language->getHost();
  }


  /**
   * Returns the current database selected by the current connection.
   *
   * ```php
   * X::adump($ctrl->db->getCurrent()); // dev_mk
   * ```
   * 
   * @return string|null
   */
  public function getCurrent(): ?string
  {
    return $this->language->getCurrent();
  }


  /**
   * Returns the last error, return null if there is no last error.
   *
   * ```php
   * X::adump($ctrl->db->getLastError()); // null
   * ```
   * 
   * @return string|null
   */
  public function getLastError(): ?string
  {
    return $this->language->getLastError();
  }

  /**
   * Returns true if the column name is an aggregate function
   * 
   * ```php
   * X::adump($ctrl->db->isAggregateFunction("name")); // false
   * X::adump($ctrl->db->isAggregateFunction("ID")); // true
   * ```
   * 
   * @param string $f The string to check
   * 
   * @return bool
   */
  public function isAggregateFunction(string $f): bool
  {
    $cls = '\\bbn\\Db\\languages\\'.$this->engine;
    return $cls::isAggregateFunction($f);
  }


  /**
   * Makes that echoing the connection shows its engine and host.
   * 
   * ```php
   * X::adump($ctrl->db->__toString()); // Connection mysql to db.m3l.co
   * ```
   * 
   * @return string
   */
  public function __toString()
  {
    return "Connection {$this->engine} to " . $this->getHost();
  }


  /**
   * Returns the connection code
   * 
   * ```php
   * X::adump($ctrl->db->getConnectionCode()); // dev_mk@db.m3l.co
   * ```
   * 
   * @return string
   */
  public function getConnectionCode()
  {
    return $this->language->getConnectionCode();
  }

  /**
   * Returns the last config for this connection.
   *
   * ```php
   * X::dump($db->getLastCfg());
   * // (array) INSERT INTO `db_example.table_user` (`name`) VALUES (?)
   * ```
   *
   * @return array|null
   */
  public function getLastCfg(): ?array
  {
    return $this->language->getLastCfg();
  }

  /**
   * 
   * ```php
   * X::adump($ctrl->db->getConnection()); 
   * ```
   * 
   * @param array $cfg The user's options
   * @return array|null The final configuration
   */
  public function getConnection(array $cfg = []): ?array
  {
    return $this->language->getConnection($cfg);
  }


  /****************************************************************
   *                                                              *
   *                                                              *
   *                      INTERNAL METHODS                        *
   *                                                              *
   *                                                              *
   ****************************************************************/

  /**
   * Gets the created hash.
   *
   * ```php
   * X::dump($db->getHash());
   * // (string) 3819056v431b210daf45f9b5dc2
   * ```
   * @return string
   */
  public function getHash(): string
  {
    return $this->language->getHash();
  }


  /**
   * @param array $conditions
   * @param $old_name
   * @param $new_name
   * @return array
   */
  public function replaceTableInConditions(array $conditions, $old_name, $new_name): array
  {
    return X::map(
      function ($a) use ($old_name, $new_name) {
        if (!empty($a['field'])) {
          $a['field'] = preg_replace("/(\\W|^)$old_name([\\`\\']*\\s*)\\./", '$1'.$new_name.'$2.', $a['field']);
        }

        if (!empty($a['exp'])) {
          $a['exp'] = preg_replace("/(\\W|^)$old_name([\\`\\']*\\s*)\\./", '$1'.$new_name.'$2.', $a['exp']);
        }

        return $a;
      }, $conditions, 'conditions'
    );
  }


  /**
   * @param array $where
   * @param bool  $full
   * @return array|bool
   */
  public function treatConditions(array $where, bool $full = true)
  {
    return $this->language->treatConditions($where, $full);
  }


  /**
   * @param array $cfg
   * @return array|null
   */
  public function reprocessCfg(array $cfg): ?array
  {
    return $this->language->reprocessCfg($cfg);
  }

  /**
   *
   * @param array $args
   * @param bool $force
   * @return array|null
   */
  public function processCfg(array $args, bool $force = false): ?array
  {
    return $this->language->processCfg($args, $force);
  }

  /**
   * Checks if the database is ready to process a query.
   *
   * ```php
   * X::dump($db->check());
   * // (bool)
   * ```
   * 
   * @return bool
   */
  public function check(): bool
  {
    return $this->language->check();
  }

  /**
   * Writes in data/logs/db.log.
   *
   * ```php
   * $db->$db->log('test');
   * ```
   * 
   * @param mixed $st
   * @return self
   */
  public function log($st): self
  {
    $args = \func_get_args();
    foreach ($args as $a){
      X::log($a, 'db');
    }

    return $this;
  }


  /**
   * Sets the error mode.
   *
   * ```php
   * $db->setErrorMode('continue'|'die'|'stop_all|'stop');
   * // (self)
   * ```
   *
   * @param string $mode The error mode: "continue", "die", "stop", "stop_all".
   * @return self
   */
  public function setErrorMode(string $mode): self
  {
    $this->language->setErrorMode($mode);
    return $this;
  }


  /**
   * Gets the error mode.
   *
   * ```php
   * X::dump($db->getErrorMode());
   * // (string) stop_all
   * ```
   * 
   * @return string
   */
  public function getErrorMode(): string
  {
    return $this->language->getErrorMode();
  }


  /**
   * Deletes a specific item from the cache.
   *
   * ```php
   * X::dump($db->clearCache('db_example','tables'));
   * // (db)
   * ```
   *
   * @param string $item 'db_name' or 'table_name'
   * @param string $mode 'columns','tables' or 'databases'
   * @return self
   */
  public function clearCache(string $item, string $mode): self
  {
    if ($this->cacheHas($item, $mode)) {
      $this->cacheDelete($item, $mode);
    }

    return $this;
  }


  /**
   * Clears the cache.
   *
   * ```php
   * X::dump($db->clearAllCache());
   * // (db)
   * ```
   *
   * @return self
   */
  public function clearAllCache(): self
  {
    $this->cacheDeleteAll();
    return $this;
  }


  /**
   * Stops fancy stuff.
   *
   * ```php
   *  $db->stopFancyStuff();
   * // (self)
   * ```
   *
   * @return self
   */
  public function stopFancyStuff(): self
  {
    if ($this->language) {
      $this->language->stopFancyStuff();
    }

    return $this;
  }


  /**
   * Starts fancy stuff.
   *
   * ```php
   * $db->startFancyStuff();
   * // (self)
   * ```
   * 
   * @return self
   */
  public function startFancyStuff(): self
  {
    if ($this->language) {
      $this->language->startFancyStuff();
    }

    return $this;
  }


  /****************************************************************
   *                                                              *
   *                                                              *
   *                          TRIGGERS                            *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Enable the triggers' functions
   *
   * ```php
   * X::adump($ctrl->db->enableTrigger()); // bbn\Db Object
   * ```
   * 
   * @return self
   */
  public function enableTrigger(): self
  {
    $this->language->enableTrigger();
    return $this;
  }


  /**
   * Disable the triggers' functions
   * 
   * ```php
   * X::adump($ctrl->db->disableTrigger());
   * ```
   * 
   * @return self
   */
  public function disableTrigger(): self
  {
    $this->language->disableTrigger();
    return $this;
  }

  /**
   * Checks if the triggers' functions are enable
   * 
   * ```php
   * X::adump($ctrl->db->isTriggerEnabled()); // true
   * ```
   *
   * @return boolean
   */
  public function isTriggerEnabled(): bool
  {
    return $this->language->isTriggerEnabled();
  }

  /**
   * Checks if the triggers' functions are disable
   * 
   * ```php
   * X::adump($ctrl->db->isTriggerEnabled()); // false
   * ```
   * 
   * @return boolean
   */
  public function isTriggerDisabled(): bool
  {
    return $this->language->isTriggerDisabled();
  }


  /**
   * Apply a function each time the methods $kind are used
   *
   * @param callable            $function
   * @param array|string|null   $kind     select|insert|update|delete
   * @param array|string|null   $moment   before|after
   * @param null|string|array   $tables   database's table(s) name(s)
   * @return self
   */
  public function setTrigger(callable $function, $kind = null, $moment = null, $tables = '*' ): self
  {
    $this->language->setTrigger($function, $kind, $moment, $tables);

    return $this;
  }


  /**
   * Returns an array 
   * 
   * ```php
   * X::adump($ctrl->db->getTriggers());
   * ```
   * @return array
   */
  public function getTriggers(): array
  {
    return $this->language->getTriggers();
  }


  /****************************************************************
   *                                                              *
   *                                                              *
   *                       STRUCTURE HELPERS                      *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * @param $tables
   * @return array
   * @throws Exception
   */
  public function getFieldsList($tables): array
  {
    return $this->language->getFieldsList($tables);
  }


  /**
   * Return an array with tables and fields related to the searched foreign key.
   *
   * ```php
   * X::dump($db->getForeignKeys('id', 'table_users', 'db_example'));
   * // (Array)
   * ```
   *
   * @param string $col The column's name
   * @param string $table The table's name
   * @param string|null $db The database name if different from the current one
   * @return array with tables and fields related to the searched foreign key
   */
  public function getForeignKeys(string $col, string $table, string $db = null): array
  {
    return $this->language->getForeignKeys($col, $table, $db);
  }


  /**
   * Return true if in the table there are fields with auto-increment.
   * Working only on mysql.
   *
   * ```php
   * X::dump($db->hasIdIncrement('table_users'));
   * // (bool) 1
   * ```
   *
   * @param string $table The table's name
   * @return bool
   */
  public function hasIdIncrement(string $table): bool
  {
    if (method_exists($this->language, 'hasIdIncrement')) {
      return $this->language->hasIdIncrement($table);
    }

    return false;
  }


  /**
   * Return the table's structure as an indexed array.
   * 
   * X::hdump($ctrl->db->modelize('my_date_2')); /*    
   * "fields": {
   *     "ID": {
   *         "position": 1,
   *         "type": "int",
   *         "null": 0,
   *         "key": null,
   *         "extra": "",
   *         "signed": true,
   *         "virtual": false,
   *         "generation": "",
   *         "maxlength": 10,
   *     },
   * 
   *
   * @param null|array|string $table The table's name
   * @param bool              $force If set to true will force the modernization to re-perform even if the cache exists
   * @return null|array
   */
  public function modelize($table = null, bool $force = false): ?array
  {
    return $this->language->modelize($table, $force);
  }

  /**
   * 
   */
  public function getColMaxLength(string $column, string $table = null): ?int
  {
    return $this->language->getColMaxLength($column, $table);
  } 

  /** 
   * Return the table's structure as an indexed array.
   * 
   * ```php
   * X::hdump($ctrl->db->fmodelize('my_date_2'));
   * ```
   * 
   * @param string $table
   * @param bool   $force
   * @return null|array
   */
  public function fmodelize(string $table = '', bool $force = false): ?array
  {
    if (method_exists($this->language, 'fmodelize')) {
      return $this->language->fmodelize($table, $force);
    }

    return null;
  }


  /**
   * find_references
   *
   * @param $column
   * @param string $db
   * @return array|bool
   *
   */
  public function findReferences($column, string $db = ''): array
  {
    if (method_exists($this->language, 'findReferences')) {
      return $this->language->findReferences($column, $db);
    }

    return [];
  }


  /**
   * find_relations
   *
   * @param $column
   * @param string $db
   * @return array|bool
   */
  public function findRelations($column, string $db = ''): ?array
  {
    return $this->language->findRelations($column, $db);
  }


  /**
   * Return primary keys of a table as a numeric array.
   *
   * ```php
   * X::dump($db-> get_primary('table_users'));
   * // (array) ["id"]
   * ```
   *
   * @param string $table The table's name
   * @return array
   */
  public function getPrimary(string $table): array
  {
    return $this->language->getPrimary($table);
  }


  /**
   * Return primary keys of a table as a string if there is a single-column unique key.
   *
   * ```php
   * X::dump($db-> getSinglePrimary('table_users'));
   * // (string) "id"
   * ```
   *
   * @param string $table The table's name
   * @return array
   */
  public function getSinglePrimary(string $table): ?string
  {
    $primaries = $this->language->getPrimary($table);
    if (count($primaries) === 1) {
      return $primaries[0];
    }

    return null;
  }


  /**
   * Return the unique primary key of the given table.
   *
   * ```php
   * X::dump($db->getUniquePrimary('table_users'));
   * // (string) id
   * ```
   *
   * @param string $table The table's name
   * @return null|string
   */
  public function getUniquePrimary(string $table): ?string
  {
    if (method_exists($this->language, 'getUniquePrimary')) {
      return $this->language->getUniquePrimary($table);
    }

    return null;
  }


  /**
   * Return the unique keys of a table as a numeric array.
   *
   * ```php
   * X::dump($db->getUniqueKeys('table_users'));
   * // (array) ["userid", "userdataid"]
   * ```
   *
   * @param string $table The table's name
   * @return array
   */
  public function getUniqueKeys(string $table): array
  {
    if (method_exists($this->language, 'getUniqueKeys')) {
      return $this->language->getUniqueKeys($table);
    }

    return [];
  }


  /**
   * Changes the charset to the given database
   * @param string $database The database's name
   * @param string $charset The charset to set
   * @param string $collation The collation to set
   */
  public function setDatabaseCharset(string $database, string $charset, string $collation): bool
  {
    if (method_exists($this->language, 'setDatabaseCharset')) {
      return $this->language->setDatabaseCharset($database, $charset, $collation);
    }
    return false;
  }


  /**
   * Changes the charset to the given table
   * @param string $table The table's name
   * @param string $charset The charset to set
   * @param string $collation The collation to set
   */
  public function setTableCharset(string $table, string $charset, string $collation): bool
  {
    if (method_exists($this->language, 'setTableCharset')) {
      return $this->language->setTableCharset($table, $charset, $collation);
    }
    return false;
  }


  /**
   * Changes the charset to the given column
   * @param string $table The table's name
   * @param string $column The column's name
   * @param string $charset The charset to set
   * @param string $collation The collation to set
   */
  public function setColumnCharset(string $table, string $column, string $charset, string $collation): bool
  {
    if (method_exists($this->language, 'setColumnCharset')) {
      return $this->language->setColumnCharset($table, $column, $charset, $collation);
    }
    return false;
  }


  /****************************************************************
   *                                                              *
   *                                                              *
   *                           UTILITIES                          *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Return a string with quotes and percent escaped.
   *
   * ```php
   * X::dump($db->escapeValue("My father's job is interesting");
   * // (string) My  father\'s  job  is  interesting
   * ```
   *
   * @param string $value The string to escape.
   * @param string $esc
   * @return string
   *
   */
  public function escapeValue(string $value, $esc = "'"): string
  {
    return str_replace(
      '%', '\\%', $esc === '"' ? Str::escapeDquotes($value) : Str::escapeSquotes($value)
    );
  }


  /**
   * Changes the value of last_insert_id (used by history).
   * @todo this function should be private
   *
   * ```php
   * X::dump($db->setLastInsertId());
   * // (db)
   * ```
   * @param mixed $id The last inserted id
   * @return self
   */
  public function setLastInsertId($id = ''): self
  {
    $this->language->setLastInsertId($id);

    return $this;
  }

  /**
   * Return the last query for this connection.
   *
   * ```php
   * X::dump($db->last());
   * // (string) INSERT INTO `db_example.table_user` (`name`) VALUES (?)
   * ```
   *
   * @return string
   */
  public function last(): ?string
  {
    return $this->language->last();
  }

  /**
   * Return the last inserted ID.
   *
   * ```php
   * X::dump($db->lastId());
   * // (int) 26
   * ```
   *
   * @return mixed
   */
  public function lastId()
  {
    return $this->language->lastId();
  }


  /**
   * Deletes all the queries recorded and returns their (ex) number.
   *
   * ```php
   * X::hdump($ctrl->db->flush()); // 9
   * ```
   * @return int
   */
  public function flush(): int
  {
    return $this->language->flush();
  }

  /**
   * Generate a new casual id based on the max number of characters of id's column structure in the given table
   *
   * ```php
   * X::dump($db->newId('table_users', 18));
   * // (int) 69991701
   * ```
   *
   * @todo Either get rid of th efunction or include the UID types
   * TODO-testing is this needed?
   * @param null|string $table The table's name.
   * @param int         $min
   * @return mixed
   */
  public function newId($table, int $min = 1)
  {
    $tab = $this->modelize($table);
    if (\count($tab['keys']['PRIMARY']['columns']) !== 1) {
      die("Error! Unique numeric primary key doesn't exist");
    }

    if (($id_field = $tab['keys']['PRIMARY']['columns'][0])
        && ($maxlength = $tab['fields'][$id_field]['maxlength'] )
        && ($maxlength > 1)
    ) {
      $max = (10 ** $maxlength) - 1;
      if ($max >= mt_getrandmax()) {
        $max = mt_getrandmax();
      }

      if (($max > $min) && ($table = $this->tfn($table, true))) {
        $i = 0;
        do {
          $id = random_int($min, $max);
          /** @todo */
          /*
          if ( strpos($tab['fields'][$id_field]['type'], 'char') !== false ){
            $id = substr(md5('bbn'.$id), 0, random_int(1, 10 ** $maxlength));
          }
          */
          $i++;
        }
        while (($i < 100) && $this->select($table, [$id_field], [$id_field => $id]));
        return $id;
      }
    }

    return null;
  }

// TODO-testing is this used?
  public function rselectRandom($table, array $fields = [], array $where = []):? array
  {
    if ($this->check() && ($num = $this->count($table, $where))) {
      $args = $this->_add_kind($this->_set_start($this->_set_limit_1(\func_get_args()), random_int(0, $num - 1)));
      if ($r = $this->_exec(...$args)) {
        return $r->getRow();
      }
    }

    return null;
  }

  // TODO-testing is this used?
  public function selectRandom($table, array $fields = [], array $where = []):? \stdClass
  {
    if ($this->check() && ($num = $this->count($table, $where))) {
      $args = $this->_add_kind($this->_set_start($this->_set_limit_1(\func_get_args()), random_int(0, $num - 1)));
      if ($r = $this->_exec(...$args)) {
        return $r->getObj();
      }
    }

    return null;
  }


  /**
   * Returns a random value fitting the requested column's type
   *
   * @todo This great function has to be done properly
   * TODO is this used?
   * @param $col
   * @param $table
   * @return mixed
   */
  public function randomValue($col, $table)
  {
    $val = null;
    if (($tab = $this->modelize($table)) && isset($tab['fields'][$col])) {
      foreach ($tab['keys'] as $key => $cfg){
        if ($cfg['unique']
            && !empty($cfg['ref_column'])
            && (\count($cfg['columns']) === 1)
            && ($col === $cfg['columns'][0])
        ) {
          return ($num = $this->count($cfg['ref_column'])) ? $this->selectOne(
            [
            'tables' [$cfg['ref_table']],
            'fields' => [$cfg['ref_column']],
            'start' => random_int(0, $num - 1)
            ]
          ) : null;
        }
      }

      switch ($tab['fields'][$col]['type']){
        case 'int':
          if (($tab['fields'][$col]['maxlength'] === 1) && !$tab['fields'][$col]['signed']) {
            $val = microtime(true) % 2 === 0 ? 1 : 0;
          }
          else {
            $max = 10 ** $tab['fields'][$col]['maxlength'] - 1;
            if ($max > mt_getrandmax()) {
              $max = mt_getrandmax();
            }

            if ($tab['fields'][$col]['signed']) {
              $max /= 2;
            }

            $min = $tab['fields'][$col]['signed'] ? -$max : 0;
            $val = random_int($min, $max);
          }
          break;
        case 'float':
        case 'double':
        case 'decimal':
          break;
        case 'varchar':
          break;
        case 'text':
          break;
        case 'date':
          break;
        case 'datetime':
          break;
        case 'timestamp':
          break;
        case 'time':
          break;
        case 'year':
          break;
        case 'blob':
          break;
        case 'binary':
          break;
        case 'varbinary':
          break;
        case 'enum':
          break;
      }
    }

    return $val;
  }


  /** Returns the number of queries 
   * 
   * ```php
   * X::hdump($ctrl->db->countQueries()); // 10
   * ```
   * 
   * @return int
   */
  public function countQueries(): int
  {
    return $this->language->countQueries();
  }


  /****************************************************************
   *                                                              *
   *                                                              *
   *                       QUERY HELPERS                          *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Executes the given query with given vars, and extracts the first cell's result.
   *
   * ```php
   * X::dump($db->getOne("SELECT name FROM table_users WHERE id>?", 138));
   * // (string) John
   * ```
   *
   * @param string query
   * @param mixed values
   * @return mixed
   */
  public function getOne()
  {
   return $this->language->getOne(...\func_get_args());
  }


  /**
   * Execute the given query with given vars, and extract the first cell's result.
   * (similar to {@link get_one()})
   *
   * ```php
   * X::dump($db->getVar("SELECT telephone FROM table_users WHERE id>?", 1));
   * // (int) 123554154
   * ```
   *
   * @param string query
   * @param mixed values
   * @return mixed
   */
  public function getVar()
  {
    return $this->getOne(...\func_get_args());
  }


  /**
   * Return an array indexed on the first field of the request.
   * The value will be an array if the request has more than two fields.
   *
   * ```php
   * X::dump($db->getKeyVal("SELECT name,id_group FROM table_users"));
   * /*
   * (array)[
   *      "John" => 1,
   *      "Michael" => 1,
   *      "Barbara" => 1
   *        ]
   *
   * X::dump($db->getKeyVal("SELECT name, surname, id FROM table_users WHERE id > 2 "));
   * /*
   * (array)[
   *         "John" => [
   *          "surname" => "Brown",
   *          "id" => 3
   *         ],
   *         "Michael" => [
   *          "surname" => "Smith",
   *          "id" => 4
   *         ]
   *        ]
   * ```
   *
   * @param string query
   * @param mixed values
   * @return null|array
   */
  public function getKeyVal(): ?array
  {
    return $this->language->getKeyVal(...\func_get_args());
  }


  /**
   * Return an array with the values of single field resulting from the query.
   *
   * ```php
   * X::dump($db->getColArray("SELECT id FROM table_users"));
   * /*
   * (array)[1, 2, 3, 4]
   * ```
   *
   * @param string query
   * @param mixed values
   * @return array
   */
  public function getColArray(): array
  {
    return $this->language->getColArray(...\func_get_args());
  }


  /****************************************************************
   *                                                              *
   *                                                              *
   *                 READ HELPERS WITH TRIGGERS                   *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Returns the first row resulting from the query as an object.
   *
   * ```php
   * X::dump($db->select('table_users', ['name', 'surname'], [['id','>','2']]));
   * /*
   * (object){
   *   "name": "John",
   *   "surname": "Smith",
   * }
   * ```
   *
   * @param string|array    $table  The table's name or a configuration array
   * @param string|array    $fields The fields' name
   * @param array           $where  The "where" condition
   * @param array | boolean $order  The "order" condition, default: false
   * @param int             $start  The "start" condition, default: 0
   * @return null|\stdClass
   */
  public function select($table, $fields = [], array $where = [], array $order = [], int $start = 0): ?\stdClass
  {
    return $this->language->select($table, $fields, $where, $order, $start);
  }


  /**
   * Return table's rows resulting from the query as an array of objects.
   *
   * ```php
   * X::dump($db->selectAll("tab_users", ["id", "name", "surname"],[["id", ">", 1]], ["id" => "ASC"], 2));
   * /*
   * (array)[
   *        Object stdClass: df {
   *          "id" => 2,
   *          "name" => "John",
   *          "surname" => "Smith",
   *          },
   *        Object stdClass: df {
   *          "id" => 3,
   *          "name" => "Thomas",
   *          "surname" => "Jones",
   *         }
   *        ]
   * ```
   *
   * @param string|array    $table  The table's name or a configuration array
   * @param string|array    $fields The fields' name
   * @param array           $where  The "where" condition
   * @param array | boolean $order  The "order" condition, default: false
   * @param int             $limit  The "limit" condition, default: 0
   * @param int             $start  The "start" condition, default: 0
   * @return null|array
   */
  public function selectAll($table, $fields = [], array $where = [], array $order = [], int $limit = 0, int $start = 0): ?array
  {
    return $this->language->selectAll($table, $fields, $where, $order, $limit, $start);
  }


  /**
   * Return the first row resulting from the query as a numeric array.
   *
   * ```php
   * X::dump($db->iselect("tab_users", ["id", "name", "surname"], [["id", ">", 1]], ["id" => "ASC"], 2));
   * /*
   * (array)[
   *          4,
   *         "Jack",
   *          "Stewart"
   *        ]
   * ```
   *
   * @param string|array    $table  The table's name or a configuration array
   * @param string|array    $fields The fields' name
   * @param array           $where  The "where" condition
   * @param array | boolean $order  The "order" condition, default: false
   * @param int             $start  The "start" condition, default: 0
   * @return array
   */
  public function iselect($table, $fields = [], array $where = [], array $order = [], int $start = 0): ?array
  {
    return $this->language->iselect($table, $fields, $where, $order, $start);
  }


  /**
   * Return the searched rows as an array of numeric arrays.
   *
   * ```php
   * X::dump($db->iselectAll("tab_users", ["id", "name", "surname"], [["id", ">", 1]],["id" => "ASC"],2));
   * /*
   * (array)[
   *          [
   *            2,
   *            "John",
   *            "Smith",
   *          ],
   *          [
   *            3,
   *            "Thomas",
   *            "Jones",
   *          ]
   *        ]
   * ```
   *
   * @param string|array  $table  The table's name or a configuration array
   * @param string|array  $fields The fields's name
   * @param array         $where  The "where" condition
   * @param array|boolean $order The "order" condition, default: false
   * @param int           $limit  The "limit" condition, default: 0
   * @param int           $start  The "start" condition, default: 0
   * @return array
   */
  public function iselectAll($table, $fields = [], array $where = [], array $order = [], int $limit = 0, int $start = 0): ?array
  {
    return $this->language->iselectAll($table, $fields, $where, $order, $limit, $start);
  }


  /**
   * Return the first row resulting from the query as an indexed array.
   *
   * ```php
   * X::dump($db->rselect("tab_users", ["id", "name", "surname"], ["id", ">", 1], ["id" => "ASC"], 2));
   * /*
   * (array) [
   *          "id" => 4,
   *          "name" => "John",
   *          "surname" => "Smith"
   *         ]
   * ```
   *
   * @param string|array  $table  The table's name or a configuration array
   * @param string|array  $fields The fields' name
   * @param array         $where  The "where" condition
   * @param array|boolean $order  The "order" condition, default: false
   * @param int           $start  The "start" condition, default: 0
   * @return null|array
   */
  public function rselect($table, $fields = [], array $where = [], array $order = [], int $start = 0): ?array
  {
    return $this->language->rselect($table, $fields, $where, $order, $start);
  }


  /**
   * Return table's rows as an array of indexed arrays.
   *
   * ```php
   * X::dump($db->rselectAll("tab_users", ["id", "name", "surname"], [["id", ">", 1]], ["id" => "ASC"], 2));
   * /*
   * (array) [
   *          [
   *          "id" => 2,
   *          "name" => "John",
   *          "surname" => "Smith",
   *          ],
   *          [
   *          "id" => 3,
   *          "name" => "Thomas",
   *          "surname" => "Jones",
   *          ]
   *        ]
   * ```
   *
   * @param string|array    $table  The table's name or a configuration array
   * @param string|array    $fields The fields' name
   * @param array           $where  The "where" condition
   * @param array | boolean $order  condition, default: false
   * @param int             $limit  The "limit" condition, default: 0
   * @param int             $start  The "start" condition, default: 0
   * @return null|array
   */
  public function rselectAll($table, $fields = [], array $where = [], array $order = [], $limit = 0, $start = 0): ?array
  {
    return $this->language->rselectAll($table, $fields, $where, $order, $limit, $start);
  }


  /**
   * Return a single value
   *
   * ```php
   * X::dump($db->selectOne("tab_users", "name", [["id", ">", 1]], ["id" => "DESC"], 2));
   *  (string) 'Michael'
   * ```
   *
   * @param string|array    $table The table's name or a configuration array
   * @param string          $field The field's name
   * @param array           $where The "where" condition
   * @param array | boolean $order The "order" condition, default: false
   * @param int             $start The "start" condition, default: 0
   * @return mixed
   */
  public function selectOne($table, $field = null, array $where = [], array $order = [], int $start = 0)
  {
    return $this->language->selectOne($table, $field, $where, $order, $start);
  }

  // TODO-testing: is this used??
  public function selectUnion(array $union, array $fields = [], array $where = [], array $order = [], int $start = 0):? array
  {
    $cfgs = [];
    $sql  = 'SELECT ';
    if (empty($fields)) {
      $sql .= '* ';
    }
    else{
      foreach ($fields as $i => $f){
        if ($i) {
          $sql .= ', ';
        }

        $sql .= $this->csn($f, true);
      }
    }

    $sql .= ' FROM (('.PHP_EOL;
    $vals = [];
    $i    = 0;
    foreach ($union as $u){
      $cfg = $this->processCfg($this->_add_kind([$u]));
      if ($cfg && $cfg['sql']) {
        /** @todo From here needs to analyze the where array to the light of the tables' config */
        if (!empty($where)) {
          if (empty($fields)) {
            $fields = $cfg['fields'];
          }

          foreach ($fields as $k => $f){
            if (isset($cfg['available_fields'][$f])) {
              if ($cfg['available_fields'][$f] && ($t = $cfg['models'][$cfg['available_fields'][$f]])
              ) {
                throw new Exception("Impossible to create the where in union for the following request: ".PHP_EOL.$cfg['sql']);
                //die(var_dump($t['fields'][$cfg['fields'][$f] ?? $this->csn($f)]));
              }
            }
          }
        }

        if ($i) {
          $sql .= PHP_EOL.') UNION ('.PHP_EOL;
        }

        $sql .= $cfg['sql'];
        foreach ($cfg['values'] as $v){
          $vals[] = $v;
        }

        $i++;
      }
    }

    $sql .= PHP_EOL.')) AS t';
    return $this->getRows($sql, ...$vals);
    //echo nl2br($sql);
    return [];
  }


  /**
   * Return the number of records in the table corresponding to the $where condition (non mandatory).
   *
   * ```php
   * X::dump($db->count('table_users', ['name' => 'John']));
   * // (int) 2
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param array        $where The "where" condition
   * @return int
   */
  public function count($table, array $where = []): ?int
  {
    return $this->language->count($table, $where);
  }


  /**
   * Return an array indexed on the first field of the request.
   * The value will be an array if the request has more than two fields.
   * Return the same value as "get_key_val".
   *
   * ```php
   * X::dump($db->selectAllByKeys("table_users", ["name","id","surname"], [["id", ">", "1"]], ["id" => "ASC"]);
   * /*
   * (array)[
   *        "John" => [
   *          "surname" => "Brown",
   *          "id" => 3
   *          ],
   *        "Michael" => [
   *          "surname" => "Smith",
   *          "id" => 4
   *        ]
   *      ]
   * ```
   *
   * @param string|array  $table  The table's name or a configuration array
   * @param array         $fields The fields's name
   * @param array         $where  The "where" condition
   * @param array|boolean $order  The "order" condition
   * @param int           $limit  The $limit condition, default: 0
   * @param int           $start  The $limit condition, default: 0
   * @return array|false
   */
  public function selectAllByKeys($table, array $fields = [], array $where = [], array $order = [], int $limit = 0, int $start = 0): ?array
  {
    return $this->language->selectAllByKeys($table, $fields, $where, $order, $limit, $start);
  }


  /**
   * Return an array with the count of values corresponding to the where conditions.
   *
   * ```php
   * X::dump($db->stat('table_user', 'name', ['name' => '%n']));
   * /* (array)
   * [
   *  [
   *      "num" => 1,
   *      "name" => "alan",
   *  ], [
   *      "num" => 1,
   *      "name" => "karen",
   *  ],
   * ]
   * ```
   *
   * @param string|array $table  The table's name or a configuration array.
   * @param string       $column The field's name.
   * @param array        $where  The "where" condition.
   * @param array        $order  The "order" condition.
   * @return array
   */
  public function stat(string $table, string $column, array $where = [], array $order = []): ?array
  {
    return $this->language->stat($table, $column, $where, $order);
  }


  /**
   * Return the unique values of a column of a table as a numeric indexed array.
   *
   * ```php
   * X::dump($db->getFieldValues("table_users", "surname", [['id', '>', '2']]));
   * // (array) ["Smiths", "White"]
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param string|null  $field The field's name
   * @param array        $where The "where" condition
   * @param array        $order The "order" condition
   * @return array | false
   */
  public function getFieldValues($table, string $field = null, array $where = [], array $order = []): ?array
  {
    return $this->getColumnValues($table, $field, $where, $order);
  }


  /**
   * Return a count of identical values in a field as array, Reporting a structure type 'num' - 'val'.
   *
   * ```php
   * X::dump($db->countFieldValues('table_users','surname',[['name','=','John']]));
   * // (array) ["num" => 2, "val" => "John"]
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param null|string  $field The field's name
   * @param array        $where The "where" condition
   * @param array        $order The "order" condition
   * @return array|null
   */
  public function countFieldValues($table, string $field = null,  array $where = [], array $order = []): ?array
  {
    return $this->language->countFieldValues($table, $field, $where, $order);
  }


  /**
   * Return a numeric indexed array with the values of the unique column ($field) from the selected $table
   *
   * ```php
   * X::dump($db->getColumnValues('table_users','surname',['id','>',1]));
   * /*
   * array [
   *    "Smith",
   *    "Jones",
   *    "Williams",
   *    "Taylor"
   * ]
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param string|null $field The field's name
   * @param array $where The "where" condition
   * @param array $order The "order" condition
   * @param int $limit
   * @param int $start
   * @return array
   */
  public function getColumnValues($table, string $field = null,  array $where = [], array $order = [], int $limit = 0, int $start = 0): ?array
  {
    return $this->language->getColumnValues($table, $field, $where, $order, $limit, $start);
  }


  /**
   * Return a string with the sql query to count equal values in a field of the table.
   *
   * ```php
   * X::dump($db->getValuesCount('table_users','name',['surname','=','smith']));
   * /*
   * (string)
   *   SELECT COUNT(*) AS num, `name` AS val FROM `db_example`.`table_users`
   *     GROUP BY `name`
   *     ORDER BY `name`
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param string|null $field The field's name
   * @param array $where The "where" condition
   * @param array $order The "order" condition
   * @return array
   * // TODO-testing: this method stated that it will return string but actually it returns an array!
   */
  public function getValuesCount($table, string $field = null, array $where = [], array $order = []): array
  {
    return $this->countFieldValues($table, $field, $where, $order);
  }


  /****************************************************************
   *                                                              *
   *                                                              *
   *                 WRITE HELPERS WITH TRIGGERS                  *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Inserts row(s) in a table.
   *
   * <code>
   * $db->insert("table_users", [
   *    ["name" => "Ted"],
   *    ["surname" => "McLow"]
   *  ]);
   * </code>
   *
   * <code>
   * $db->insert("table_users", [
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
   * @param string|array $table The table name or the configuration array.
   * @param array|null $values The values to insert.
   * @param bool $ignore If true, controls if the row is already existing and ignores it.
   *
   * @return int Number affected rows.
   */
  public function insert($table, array $values = null, bool $ignore = false): ?int
  {
    return $this->language->insert($table, $values, $ignore);
  }


  /**
   * If not exist inserts row(s) in a table, else update.
   *
   * <code>
   * $db->insertUpdate(
   *  "table_users",
   *  [
   *    'id' => '12',
   *    'name' => 'Frank'
   *  ]
   * );
   * </code>
   *
   * @param string|array $table The table name or the configuration array.
   * @param array|null $values The values to insert.
   *
   * @return int The number of rows inserted or updated.
   */
  public function insertUpdate($table, array $values = null): ?int
  {
    return $this->language->insertUpdate($table, $values);
  }


  /**
   * Updates row(s) in a table.
   *
   * <code>
   * $db->update(
   *  "table_users",
   *  [
   *    ['name' => 'Frank'],
   *    ['surname' => 'Red']
   *  ],
   *  ['id' => '127']
   * );
   * </code>
   *
   * @param string|array $table The table name or the configuration array.
   * @param array|null $values The new value(s).
   * @param array|null $where The "where" condition.
   * @param boolean $ignore If IGNORE should be added to the statement
   *
   * @return int The number of rows updated.
   */
  public function update($table, array $values = null, array $where = null, bool $ignore = false): ?int
  {
    return $this->language->update($table, $values, $where, $ignore);
  }


  /**
   * If exist updates row(s) in a table, else ignore.
   *
   * <code>
   * $db->updateIgnore(
   *   "table_users",
   *   [
   *     ['name' => 'Frank'],
   *     ['surname' => 'Red']
   *   ],
   *   ['id' => '20']
   * );
   * </code>
   *
   * @param string|array $table The table name or the configuration array.
   * @param array|null $values
   * @param array|null $where The "where" condition.
   *
   * @return int The number of rows deleted.
   */
  public function updateIgnore($table, array $values = null, array $where = null): ?int
  {
    return $this->update($table, $values, $where, true);
  }


  /**
   * Deletes row(s) in a table.
   *
   * <code>
   * $db->delete("table_users", ['id' => '32']);
   * </code>
   *
   * @param string|array $table The table name or the configuration array.
   * @param array|null $where The "where" condition.
   * @param bool $ignore default: false.
   *
   * @return int The number of rows deleted.
   */
  public function delete($table, array $where = null, bool $ignore = false): ?int
  {
    return $this->language->delete($table, $where, $ignore);
  }


  /**
   * If exist deletes row(s) in a table, else ignore.
   *
   * <code>
   * $db->deleteIgnore(
   *  "table_users",
   *  ['id' => '20']
   * );
   * </code>
   *
   * @param string|array $table The table name or the configuration array.
   * @param array|null $where The "where" condition.
   *
   * @return int The number of rows deleted.
   */
  public function deleteIgnore($table, array $where = null): ?int
  {
    return $this->delete(\is_array($table) ? array_merge($table, ['ignore' => true]) : $table, $where, true);
  }


  /**
   * If not exist inserts row(s) in a table, else ignore.
   *
   * <code>
   * $db->insertIgnore(
   *  "table_users",
   *  [
   *    ['id' => '19', 'name' => 'Frank'],
   *    ['id' => '20', 'name' => 'Ted'],
   *  ]
   * );
   * </code>
   *
   * @param string|array $table The table name or the configuration array.
   * @param array|null $values The row(s) values.
   *
   * @return int The number of rows inserted.
   */
  public function insertIgnore($table, array $values = null): ?int
  {
    return $this->insert(\is_array($table) ? array_merge($table, ['ignore' => true]) : $table, $values, true);
  }


  /**
   * @param $table
   * @return int|null
   */
  public function truncate($table): ?int
  {
    return $this->delete($table, []);
  }


  /****************************************************************
   *                                                              *
   *                                                              *
   *                      NATIVE FUNCTIONS                        *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Return an indexed array with the first result of the query or false if there are no results.
   *
   * ```php
   * X::dump($db->fetch("SELECT name FROM users WHERE id = 10"));
   * /* (array)
   * [
   *  "name" => "john",
   *  0 => "john",
   * ]
   * ```
   *
   * @param string $query
   * @return array|false
   */
  public function fetch(string $query)
  {
    return $this->language->fetch(...\func_get_args());
  }


  /**
   * Return an array of indexed array with all results of the query or false if there are no results.
   *
   * ```php
   * X::dump($db->fetchAll("SELECT 'surname', 'name', 'id' FROM users WHERE name = 'john'"));
   * /* (array)
   *  [
   *    [
   *    "surname" => "White",
   *    0 => "White",
   *    "name" => "Michael",
   *    1 => "Michael",
   *    "id"  => 1,
   *    2 => 1,
   *    ],
   *    [
   *    "surname" => "Smith",
   *    0 => "Smith",
   *    "name" => "John",
   *    1  =>  "John",
   *    "id" => 2,
   *    2 => 2,
   *    ],
   *  ]
   * ```
   *
   * @param string $query
   * @return array|false
   */
  public function fetchAll(string $query)
  {
    return $this->language->fetchAll(...\func_get_args());
  }


  /**
   * Transposition of the original fetchColumn method, but with the query included. Return an array or false if no result
   * @todo confusion between result's index and this->query arguments(IMPORTANT). Missing the example because the function doesn't work
   *
   * @param $query
   * @param int   $num
   * @return mixed
   */
  public function fetchColumn($query, int $num = 0)
  {
    return $this->language->fetchColumn(...\func_get_args());
  }


  /**
   * Return stdClass object or false if no result.
   *
   * ```php
   * X::dump($db->fetchObject("SELECT * FROM table_users WHERE name = 'john'"));
   * // stdClass Object {
   *                    "id"  =>  1,
   *                    "name"  =>  "John",
   *                    "surname"  =>  "Smith",
   *                    }
   * ```
   *
   * @param string $query
   * @return bool|\stdClass
   */
  public function fetchObject($query)
  {
    return $this->language->fetchObject(...\func_get_args());
  }


  /**
   * Executes a writing statement and return the number of affected rows or return a query object for the reading * statement
   * @todo far vedere a thomams perche non funziona in lettura
   *
   * ```php
   * X::dump($db->query("DELETE FROM table_users WHERE name LIKE '%lucy%'"));
   * // (int) 3
   * X::dump($db->query("SELECT * FROM table_users WHERE name = 'John"));
   * // (bbn\Db\Query) Object
   * ```
   *
   * @param array|string $statement
   * @return false|int
   */
  public function query($statement)
  {
    if ($this->check()) {
      return $this->language->query(...\func_get_args());
    }
  }


  /****************************************************************
   *                                                              *
   *                                                              *
   *                          SHORTCUTS                           *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Return table's full name.
   * (similar to {@link table_full_name()})
   *
   * ```php
   * X::dump($db->tfn("table_users"));
   * // (string) work_db.table_users
   * X::dump($db->tfn("table_users", true));
   * // (string) `work_db`.`table_users`
   * ```
   *
   * @param string $table   The table's name
   * @param bool   $escaped If set to true the returned string will be escaped.
   * @return null|string
   */
  public function tfn(string $table, bool $escaped = false): ?string
  {
    return $this->tableFullName($table, $escaped);
  }


  /**
   * Return table's simple name.
   * (similar to {@link table_simple_name()})
   *
   * ```php
   * X::dump($db->tsn("work_db.table_users"));
   * // (string) table_users
   * X::dump($db->tsn("work_db.table_users", true));
   * // (string) `table_users`
   * ```
   *
   * @param string $table   The table's name
   * @param bool   $escaped If set to true the returned string will be escaped.
   * @return null|string
   */
  public function tsn(string $table, bool $escaped = false): ?string
  {
    return $this->tableSimpleName($table, $escaped);
  }


  /**
   * Return column's full name.
   * (similar to {@link col_full_name()})
   *
   * ```php
   * X::dump($db->cfn("name", "table_users"));
   * // (string)  table_users.name
   * X::dump($db->cfn("name", "table_users", true));
   * // (string) \`table_users\`.\`name\`
   * ```
   *
   * @param string $col     The column's name (escaped or not).
   * @param string|null $table   The table's name (escaped or not).
   * @param bool   $escaped If set to true the returned string will be escaped.
   * @return null|string
   */
  public function cfn(string $col, ?string $table = null, bool $escaped = false): ?string
  {
    return $this->colFullName($col, $table, $escaped);
  }


  /**
   * Return the column's simple name.
   * (similar to {@link col_simple_name()})
   *
   * ```php
   * X::dump($db->csn("table_users.name"));
   * // (string) name
   * X::dump($db->csn("table_users.name", true));
   * // (string) `name`
   * ```
   *
   * @param string $col     The column's complete name (escaped or not)
   * @param bool   $escaped If set to true the returned string will be escaped.
   * @return null|string
   */
  public function csn(string $col, bool $escaped = false): ?string
  {
    return $this->colSimpleName($col, $escaped);
  }


  /****************************************************************
   *                                                              *
   *                                                              *
   *                       ENGINE INTERFACE                       *
   *                                                              *
   *                                                              *
   ****************************************************************/

  /**
   * Actions to do once the PDO object has been created
   *
   * ```php
   * X::hdump($ctrl->db->postCreation()); // null 
   * ```
   * 
   * @return void
   */
  public function postCreation()
  {
    if ($this->language && !$this->engine) {
      $this->language->postCreation();
    }
  }


  /**
   * Changes the database used to the given one.
   *
   * ```php
   * $db = new Db();
   * X::dump($db->change('db_example'));
   * // (db)
   * ```
   *
   * @param string $db The database's name
   * @return self
   */
  public function change(string $db): self
  {
    $this->language->change($db);

    return $this;
  }


  /**
   * Escapes names with the appropriate quotes (db, tables, columns, keys...)
   *
   * ```php
   * X::dump($db->escape("table_users"));
   * // (string) `table_users`
   * ```
   *
   * @param string $item The name to escape.
   * @return string
   */
  public function escape(string $item): string
  {
    return $this->language->escape($item);
  }


  /**
   * Return table's full name.
   *
   * ```php
   * X::dump($db->tableFullName("table_users"));
   * // (String) db_example.table_users
   * X::dump($db->tableFullName("table_users", true));
   * // (String) `db_example`.`table_users`
   * ```
   *
   * @param string $table   The table's name (escaped or not).
   * @param bool   $escaped If set to true the returned string will be escaped.
   * @return string | false
   */
  public function tableFullName(string $table, bool $escaped = false): ?string
  {
    return $this->language->tableFullName($table, $escaped);
  }


  /**
   * Returns true if the given string is the full name of a table ('database.table').
   *
   * ```php
   * X::hdump($ctrl->db->isTableFullName("table_users")); // true or false
   * ```
   * 
   * @param string $table The table's name
   * @return bool
   */
  public function isTableFullName(string $table): bool
  {
    return $this->language->isTableFullName($table);
  }


  /**
   * Returns true if the given string is the full name of a column ('table.column').
   *
   * ```php
   * X::hdump($ctrl->db->isColFullName("column_users")); // true or false
   * ```
   * 
   * @param string $col
   * @return bool
   */
  public function isColFullName(string $col): bool
  {
    return $this->language->isColFullName($col);
  }


  /**
   * Return table's simple name.
   *
   * ```php
   * X::dump($db->tableSimpleName("example_db.table_users"));
   * // (string) table_users
   * X::dump($db->tableSimpleName("example.table_users", true));
   * // (string) `table_users`
   * ```
   *
   * @param string $table   The table's name (escaped or not)
   * @param bool   $escaped If set to true the returned string will be escaped
   * @return string | false
   */
  public function tableSimpleName(string $table, bool $escaped = false): ?string
  {
    return $this->language->tableSimpleName($table, $escaped);
  }


  /**
   * Return column's full name.
   *
   * ```php
   * X::dump($db->colFullName("name", "table_users"));
   * // (string)  table_users.name Hello Ohohoho!!
   * X::dump($db->colFullName("name", "table_users", true));
   * // (string) \`table_users\`.\`name\`
   * ```
   *
   * @param string $col The column's name (escaped or not)
   * @param string|null $table The table's name (escaped or not)
   * @param bool $escaped If set to true the returned string will be escaped
   * @return string | false
   */
  public function colFullName(string $col, ?string $table = null, bool $escaped = false): ?string
  {
    return $this->language->colFullName($col, $table, $escaped);
  }


  /**
   * Return the column's simple name.
   *
   * ```php
   * X::dump($db->colSimpleName("table_users.name"));
   * // (string) name
   * X::dump($db->colSimpleName("table_users.name", true));
   * // (string) `name`
   * ```
   *
   * @param string $col     The column's complete name (escaped or not).
   * @param bool   $escaped If set to true the returned string will be escaped.
   * @return string | false
   */
  public function colSimpleName(string $col, bool $escaped = false): ?string
  {
    return $this->language->colSimpleName($col, $escaped);
  }


  /**
   * Disables foreign keys constraints.
   *
   * ```php
   * X::dump($db->disableKeys());
   * // (self)
   * ```
   *
   * @return self
   */
  public function disableKeys(): self
  {
    $this->language->disableKeys();
    return $this;
  }


  /**
   * Enables foreign keys constraints.
   *
   * ```php
   * X::dump($db->enableKeys());
   * // (db)
   * ```
   *
   * @return self
   */
  public function enableKeys(): self
  {
    $this->language->enableKeys();
    return $this;
  }


  /**
   * Return databases' names as an array.
   *
   * ```php
   * X::dump($db->getDatabases());
   * /*
   * (array)[
   *      "db_customers",
   *      "db_clients",
   *      "db_empty",
   *      "db_example",
   *      "db_mail"
   *      ]
   * ```
   * @return null|array
   */
  public function getDatabases(): ?array
  {
    return $this->language->getDatabases();
  }


  /**
   * Return tables' names of a database as an array.
   *
   * ```php
   * X::dump($db->getTables('db_example'));
   * /*
   * (array) [
   *        "clients",
   *        "columns",
   *        "cron",
   *        "journal",
   *        "dbs",
   *        "examples",
   *        "history",
   *        "hosts",
   *        "keys",
   *        "mails",
   *        "medias",
   *        "notes",
   *        "medias",
   *        "versions"
   *        ]
   * ```
   *
   * @param string $database Database name
   * @return null|array
   */
  public function getTables(string $database = ''): ?array
  {
    return $this->language->getTables($database);
  }


  /**
   * Return columns' structure of a table as an array indexed with the fields names.
   *
   * * ```php
   * X::dump($db->getColumns('table_users'));
   * /* (array)[
   *            "id" => [
   *              "position" => 1,
   *              "null" => 0,
   *              "key" => "PRI",
   *              "default" => null,
   *              "extra" => "auto_increment",
   *              "signed" => 0,
   *              "maxlength" => "8",
   *              "type" => "int",
   *            ],
   *           "name" => [
   *              "position" => 2,
   *              "null" => 0,
   *              "key" => null,
   *              "default" => null,
   *              "extra" => "",
   *              "signed" => 0,
   *              "maxlength" => "30",
   *              "type" => "varchar",
   *            ],
   *            "surname" => [
   *              "position" => 3,
   *              "null" => 0,
   *              "key" => null,
   *              "default" => null,
   *              "extra" => "",
   *              "signed" => 0,
   *              "maxlength" => "30",
   *              "type" => "varchar",
   *            ],
   *            "address" => [
   *              "position" => 4,
   *              "null" => 0,
   *              "key" => "UNI",
   *              "default" => null,
   *              "extra" => "",
   *              "signed" => 0,
   *              "maxlength" => "30",
   *              "type" => "varchar",
   *            ],
   *          ]
   * ```
   *
   * @param string $table The table's name
   * @return null|array
   */
  public function getColumns(string $table): ?array
  {
    return $this->language->getColumns($table);
  }


  /**
   * Return the table's keys as an array indexed with the fields names.
   *
   * ```php
   * X::dump($db->getKeys("table_users"));
   * /*
   * (array)[
   *      "keys" => [
   *        "PRIMARY" => [
   *          "columns" => [
   *            "id",
   *          ],
   *          "ref_db" => null,
   *          "ref_table" => null,
   *          "ref_column" => null,
   *          "unique" => 1,
   *        ],
   *        "number" => [
   *          "columns" => [
   *            "number",
   *          ],
   *          "ref_db" => null,
   *          "ref_table" => null,
   *          "ref_column" => null,
   *         "unique" => 1,
   *        ],
   *      ],
   *      "cols" => [
   *        "id" => [
   *          "PRIMARY",
   *        ],
   *        "number" => [
   *          "number",
   *        ],
   *      ],
   * ]
   * ```
   *
   * @param string $table The table's name
   * @return null|array
   */
  public function getKeys(string $table): ?array
  {
    return $this->language->getKeys($table);
  }


  /**
   * Returns a string with the conditions for any filter clause.
   *
   * @param array $conditions
   * @param array $cfg
   * @param bool $is_having
   * @param int $indent
   * @return string
   */
  public function getConditions(array $conditions, array $cfg = [], bool $is_having = false, int $indent = 0): string
  {
    return $this->language->getConditions($conditions, $cfg, $is_having, $indent);
  }


  /**
   * Return SQL code for row(s) SELECT.
   *
   * ```php
   * X::dump($db->getSelect(['tables' => ['users'],'fields' => ['id', 'name']]));
   * /*
   * (string)
   *   SELECT
   *    `table_users`.`name`,
   *    `table_users`.`surname`
   * ```
   *
   * @param array $cfg The configuration array
   * @return string
   * @throws Exception
   */
  public function getSelect(array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getSelect($cfg);
  }


  /**
   * Returns the SQL code for an INSERT statement.
   *
   * ```php
   * X::dump($db->getInsert([
   *   'tables' => ['table_users'],
   *   'fields' => ['name','surname']
   * ]));
   * /*
   * (string)
   *  INSERT INTO `db_example`.`table_users` (
   *              `name`, `surname`)
   *              VALUES (?, ?)
   * ```
   *
   * @param array $cfg The configuration array
   * @return string
   * @throws Exception
   */
  public function getInsert(array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    $cfg['kind'] = 'INSERT';
    return $this->language->getInsert($this->processCfg($cfg));
  }


  /**
   * Returns the SQL code for an UPDATE statement.
   *
   * ```php
   * X::dump($db->getUpdate([
   *   'tables' => ['table_users'],
   *   'fields' => ['name','surname']
   * ]));
   * /*
   * (string)
   *    UPDATE `db_example`.`table_users`
   *    SET `table_users`.`name` = ?,
   *        `table_users`.`surname` = ?
   * ```
   *
   * @param array $cfg The configuration array
   * @return string
   * @throws Exception
   */
  public function getUpdate(array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    $cfg['kind'] = 'UPDATE';
    return $this->language->getUpdate($this->processCfg($cfg));
  }


  /**
   * Returns the SQL code for a DELETE statement.
   *
   * ```php
   * X::dump($db->getDelete(['tables' => ['table_users']]));
   * // (string) DELETE FROM `db_example`.`table_users` * WHERE 1 AND `table_users`.`id` = ?
   * ```
   *
   * @param array $cfg The configuration array
   * @return string
   * @throws Exception
   */
  public function getDelete(array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    $cfg['kind'] = 'DELETE';
    return $this->language->getDelete($this->processCfg($cfg));
  }


  /**
   * Returns a string with the JOIN part of the query if there is, empty otherwise
   *
   * @param array $cfg
   * @return string
   * @throws Exception
   */
  public function getJoin(array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getJoin($cfg);
  }


  /**
   * Return a string with 'where' conditions.
   *
   * ```php
   * X::dump($db->getWhere(['id' => 9], 'table_users'));
   * // (string) WHERE 1 AND `table_users`.`id` = ?
   * ```
   *
   * @param array $cfg
   * @return string
   * @throws Exception
   */
  public function getWhere(array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getWhere($cfg);
  }


  /**
   * Returns a string with the GROUP BY part of the query if there is, empty otherwise
   *
   * @param array $cfg
   * @return string
   * @throws Exception
   */
  public function getGroupBy(array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getGroupBy($cfg);
  }


  /**
   * Returns a string with the HAVING part of the query if there is, empty otherwise
   *
   * @param array $cfg
   * @return string
   * @throws Exception
   */
  public function getHaving(array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getHaving($cfg);
  }


  /**
   * Get a string starting with ORDER BY with corresponding parameters to $order.
   *
   * ```php
   * X::dump($db->getOrder(['name' => 'DESC' ],'table_users'));
   * // (string) ORDER BY `name` DESC
   * ```
   *
   * @param array $cfg
   * @return string
   * @throws Exception
   */
  public function getOrder(array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getOrder($cfg);
  }


  /**
   * Get a string starting with LIMIT with corresponding parameters to $limit.
   *
   * ```php
   * X::dump($db->getLimit(['limit' => 3, 'start'  => 1]));
   * // (string) LIMIT 1, 3
   * ```
   *
   * @param array $cfg
   * @return string
   * @throws Exception
   */
  public function getLimit(array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getLimit($cfg);
  }


  /**
   * Return SQL code for table creation.
   *
   * ```php
   * X::dump($db->getCreate("table_users"));
   * /*
   * (string)
   *    CREATE TABLE `table_users` (
   *      `userid` int(11) NOT NULL,
   *      `userdataid` int(11) NOT NULL,
   *      `info` char(200) DEFAULT NULL,
   *       PRIMARY KEY (`userid`,`userdataid`),
   *       KEY `table_users_userId_userdataId_info` (`userid`,`userdataid`,`info`)
   *    ) ENGINE=InnoDB DEFAULT CHARSET=utf8
   *
   * ```
   * 
   * @param string $table The table's name
   * @return string | false
   * @throws Exception
   */
  public function getCreate(string $table, array $model = null): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getCreate($table, $model);
  }


  /**
   * @param string $table
   * @param array|null $model
   * @return string
   * @throws Exception
   */
  public function getCreateTable(string $table, array $model = null): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getCreateTable($table, $model);
  }


  /**
   * @param string $table
   * @param array|null $model
   * @return string
   * @throws Exception
   */
  public function getCreateKeys(string $table, array $model = null): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getCreateKeys($table, $model);
  }


  /**
   * @param string $table
   * @param array|null $model
   * @return string
   * @throws Exception
   */
  public function getCreateConstraints(string $table, array $model = null): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getCreateConstraints($table, $model);
  }

  /**
   * Creates an index on one or more column(s) of the table
   *
   * @param string $table
   * @param string|array $column
   * @param bool $unique
   * @param null $length
   * @return bool
   * @throws Exception
   * @todo return data
   *
   * ```php
   * X::dump($db->createIndex('table_users','id_group'));
   * // (bool) true
   * ```
   *
   */
  public function createIndex(string $table, $column, bool $unique = false, $length = null): bool
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->createIndex($table, $column, $unique, $length);
  }


  /**
   * Deletes index on a column of the table.
   *
   * @param string $table The table's name.
   * @param string $key The key's name.
   * @return bool
   * @throws Exception
   * @todo far vedere a thomas perchè non funziona/return data
   *
   * ```php
   * X::dump($db->deleteIndex('table_users','id_group'));
   * // (bool) true
   * ```
   *
   */
  public function deleteIndex(string $table, string $key): bool
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->deleteIndex($table, $key);
  }


  /**
   * @param string $table
   * @param array $cfg
   * @return string
   * @throws Exception
   */
  public function getAlterTable(string $table, array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getAlterTable($table, $cfg);
  }


  /**
   * @param string $table
   * @param array $cfg
   * @return string
   * @throws Exception
   */
  public function getAlterColumn(string $table, array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getAlterColumn($table, $cfg);
  }


  /**
   * @param string $table
   * @param array $cfg
   * @return string
   * @throws Exception
   */
  public function getAlterKey(string $table, array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getAlterKey($table, $cfg);
  }


  /**
   * @param string $table
   * @param array $cfg
   * @return int
   * @throws Exception
   */
  public function alter(string $table, array $cfg): int
  {
    if (method_exists($this->language, 'alter')) {
      return $this->language->alter($table, $cfg);
    }

    if ($st = $this->language->getAlterTable($table, $cfg)) {
      return (int)$this->language->rawQuery($st);
    }

    return 0;
  }


  /**
   * Moves the given column's position within a table.
   *
   * @param string $table
   * @param string $column
   * @param array $cfg
   * @param string|null $after
   * @return integer
   */
  public function moveColumn(string $table, string $column, array $cfg, string $after = null): int
  {
    $this->ensureLanguageMethodExists('getMoveColumn');

    if ($st = $this->language->getMoveColumn($table, $column, $cfg, $after)) {
      return (int)$this->language->rawQuery($st);
    }

    return 0;
  }


  /**
   * Creates a user for a specific db.
   * @todo return data
   *
   * ```php
   * X::dump($db->createUser('Michael','22101980','db_example'));
   * // (bool) true
   * ```
   *
   * @param string|null $user
   * @param string|null $pass
   * @param string|null $db
   * @return bool
   * @throws Exception
   */
  public function createUser(string $user = null, string $pass = null, string $db = null): bool
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->createUser($user, $pass, $db);
  }


  /**
   * Deletes a db user.
   *
   * @todo non mi funziona ma forse per una questione di permessi/ return data
   *
   * ```php
   * X::dump($db->deleteUser('Michael'));
   * // (bool) true
   * ```
   *
   * @param string $user
   * @return bool
   * @throws Exception
   */
  public function deleteUser(string $user): bool
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->deleteUser($user);
  }


  /**
   * Return an array including privileges of a specific db_user or all db_users.
   * @param string $user . The user's name, without params will return all privileges of all db_users
   * @param string $host . The host
   * @return array
   * @throws Exception
   * @todo far vedere  a th la descrizione
   *
   * ```php
   * X::dump($db->getUsers('Michael'));
   * /* (array) [
   *      "GRANT USAGE ON *.* TO 'Michael'@''",
   *       GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER ON `db_example`.* TO 'Michael'@''"
   *    ]
   * ```
   *
   */
  public function getUsers(string $user = '', string $host = ''): ?array
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getUsers($user, $host);
  }


  /**
   * Renames the given table to the new given name.
   *
   * @param string $table   The current table's name
   * @param string $newName The new name.
   * @return bool  True if it succeeded
   */
  public function renameTable(string $table, string $newName): bool
  {
    return $this->language->renameTable($table, $newName);
  }

  /**
   * Returns the comment (or an empty string if none) for a given table.
   *
   * @param string $table The table's name
   *
   * @return string The table's comment
   */
  public function getTableComment(string $table): string
  {
    return $this->language->getTableComment($table);
  }

  /**
   * Gets the size of a database
   *
   * @param string $database
   * @param string $type
   * @return int
   */
  public function dbSize(string $database = '', string $type = ''): int
  {
    return $this->language->dbSize($database, $type);
  }


  /**
   * Gets the size of a table
   *
   * @param string $table
   * @param string $type
   * @return int
   */
  public function tableSize(string $table, string $type = ''): int
  {
    return $this->language->tableSize($table, $type);
  }


  /**
   * Gets the status of a table
   *
   * @param string $table
   * @param string $database
   * @return mixed
   */
  public function status(string $table = '', string $database = '')
  {
    return $this->language->status($table, $database);
  }


  /**
   * Returns a UUID
   *
   * @return string|null
   */
  public function getUid(): ?string
  {
    return $this->language->getUid();
  }


  /****************************************************************
   *                                                              *
   *                                                              *
   *                        ACTIONS INTERFACE                     *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Return the first row resulting from the query as an array indexed with the fields' name.
   *
   * ```php
   * X::dump($db->getRow("SELECT id, name FROM table_users WHERE id > ? ", 2));;
   *
   * /* (array)[
   *        "id" => 3,
   *        "name" => "thomas",
   *        ]
   * ```
   *
   * @param string query.
   * @param int The var ? value.
   * @return array|false
   *
   */
  public function getRow(): ?array
  {
    return $this->language->getRow(...\func_get_args());
  }


  /**
   * Return an array that includes indexed arrays for every row resultant from the query.
   *
   * @param string
   * @param int The var ? value
   * @return array|false
   */
  public function getRows(): ?array
  {
    return $this->language->getRows(...\func_get_args());
  }


  /**
   * Return a row as a numeric indexed array.
   *
   * ```php
   * X::dump($db->getIrow("SELECT id, name, surname FROM table_users WHERE id > ?", 2));
   * /* (array) [
   *              3,
   *              "john",
   *              "brown",
   *             ]
   * ```
   *
   * @param string query
   * @param int The var ? value
   * @return array | false
   */
  public function getIrow(): ?array
  {
    return $this->language->getIrow(...\func_get_args());
  }


  /**
   * Return an array of numeric indexed rows.
   *
   * ```php
   * X::dump($db->getIrows("SELECT id, name FROM table_users WHERE id > ? LIMIT ?", 2, 2));
   * /*
   * (array)[
   *         [
   *          3,
   *         "john"
   *         ],
   *         [
   *         4,
   *         "barbara"
   *        ]
   *       ]
   * ```
   *
   * @return null|array
   */
  public function getIrows(): ?array
  {
    return $this->language->getIrows(...\func_get_args());
  }


  /**
   * Return an array indexed on the searched field's in which there are all the values of the column.
   *
   * ```php
   * X::dump($db->getByColumns("SELECT name, surname FROM table_users WHERE id > 2"));
   * /*
   * (array) [
   *      "name" => [
   *       "John",
   *       "Michael"
   *      ],
   *      "surname" => [
   *        "Brown",
   *        "Smith"
   *      ]
   *     ]
   * ```
   *
   * @param string query
   * @return null|array
   */
  public function getByColumns(): ?array
  {
    return $this->language->getByColumns(...\func_get_args());
  }


  /**
   * Return the first row resulting from the query as an object (similar to {@link getObject()}).
   *
   * ```php
   * X::dump($db->getObj("SELECT surname FROM table_users"));
   * /*
   * (obj){
   *       "name" => "Smith"
   *       }
   * ```
   *
   * @return null|\stdClass
   */
  public function getObj(): ?\stdClass
  {
    return $this->getObject(...\func_get_args());
  }


  /**
   * Return the first row resulting from the query as an object.
   * Synonym of get_obj.
   *
   * ```php
   * X::dump($db->getObject("SELECT name FROM table_users"));
   * /*
   * (obj){
   *       "name" => "John"
   *       }
   * ```
   *
   * @return null|\stdClass
   */
  public function getObject(): ?\stdClass
  {
    return $this->language->getObject(...\func_get_args());
  }


  /**
   * Return an array of stdClass objects.
   *
   * ```php
   * X::dump($db->getObjects("SELECT name FROM table_users"));
   *
   * /*
   * (array) [
   *          Object stdClass: df {
   *            "name" => "John",
   *          },
   *          Object stdClass: df {
   *            "name" => "Michael",
   *          },
   *          Object stdClass: df {
   *            "name" => "Thomas",
   *          },
   *          Object stdClass: df {
   *            "name" => "William",
   *          },
   *          Object stdClass: df {
   *            "name" => "Jake",
   *          },
   *         ]
   * ```
   *
   * @return null|array
   */
  public function getObjects(): ?array
  {
    return $this->language->getObjects(...\func_get_args());
  }


  /**
   * Creates a database
   *
   * @param string $database
   * @return bool
   */
  public function createDatabase(string $database): bool
  {
    return $this->language->createDatabase(...\func_get_args());
  }


  /**
   * Returns true if the given table exists
   *
   * @param string $table
   * @param string $database. or currently selected if none
   * @return boolean
   */
  public function tableExists(string $table, string $database = ''): bool
  {
    return $this->language->tableExists($table, $database);
  }


  /**
   * Drops the given database
   *
   * @param string $database
   * @return bool
   */
  public function dropDatabase(string $database): bool
  {
    return $this->language->dropDatabase($database);
  }


  /**
   * Drops the given table, in the current database if none given
   *
   * @param string $database
   * @return bool
   */
  public function dropTable(string $table, string $database = ''): bool
  {
    return $this->language->dropTable($table, $database);
  }


  /**
   * @return void
   */
  public function enableLast()
  {
    if (method_exists($this->language, __FUNCTION__)) {
      $this->language->enableLast();
    }
  }


  /**
   * @return void
   */
  public function disableLast()
  {
    if (method_exists($this->language, __FUNCTION__)) {
      $this->language->disableLast();
    }
  }

  /**
   * @return array|null
   * @throws Exception
   */
  public function getRealLastParams(): ?array
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getRealLastParams();
  }


  /**
   * @return string|null
   * @throws Exception
   */
  public function realLast(): ?string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->realLast();
  }


  /**
   * @return array|null
   * @throws Exception
   */
  public function getLastParams(): ?array
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getLastParams();
  }


  /**
   * @return array|null
   * @throws Exception
   */
  public function getLastValues(): ?array
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getLastValues();
  }


  public function getQuery(string $type, array $cfg): string
  {
    if (in_array(strtoupper($type), ['INSERT', 'UPDATE', 'DELETE', 'SELECT'])) {
      $cfg['kind'] = $type;
      $cfg = $this->processCfg($cfg);
      return $cfg['sql'] ?: '';
    }

    throw new Exception(X::_("Impossible to make a query of type %s", $type));
  }


  /**
   * @param array $cfg
   * @return array
   * @throws Exception
   */
  public function getQueryValues(array $cfg): array
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getQueryValues($cfg);
  }


  /**
   * Creates a simplified array for options from a table model
   *
   * @param [type] $table_name
   * @param string $database
   * @return array
   */
  public function export4Option($table_name, $database = ''): array
  {
    if ($database) {
      $table_name = $database . '.' . $table_name; 
    }

    $structure = $this->modelize($table_name);
    foreach ($structure['keys'] as $k => &$m) {
      unset($m['ref_db'], $m['constraint']);
      if (empty($m['ref_table'])) {
        unset($m['ref_table'], $m['ref_column'], $m['delete'], $m['update']);
      }
    }
    foreach ($structure['fields'] as $k => &$f) {
      unset($f['position']);
      if (!in_array($f['type'], ['decimal', 'float', 'double'])) {
        unset($f['decimals']);
      }
      if (!in_array($f['type'], $this->language->getNumericTypes())) {
        unset($f['signed']);
      }
      if (empty($f['defaultExpression']) && is_null($f['default'])) {
        unset($f['default'], $f['defaultExpression']);
      }
      if (empty($f['extra'])) {
        unset($f['extra']);
      }
      if (empty($f['key'])) {
        unset($f['key']);
      }
      if (empty($f['virtual'])) {
        unset($f['virtual']);
      }
      if (empty($f['generation'])) {
        unset($f['generation']);
      }
    }

    return $structure;
  }


  /**
   * Throws ans exception if language class method does not exist.
   *
   * @param string $method
   * @throws Exception
   */
  private function ensureLanguageMethodExists(string $method)
  {
    if (!method_exists($this->language, $method)) {
      throw new Exception(X::_('Method %s not found on the language %s class!', $method, $this->engine));
    }
  }
}
