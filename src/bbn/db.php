<?php
/**
 * @package db
 */
namespace bbn;

/**
 * Database Class
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Database
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 */

class db extends \PDO implements db\actions, db\api, db\engines
{
  use
      db\triggers,
      models\tts\retriever;

  const
      E_CONTINUE = 'continue',
    /**
     * @todo find a way to document constants
     */

      E_DIE = 'die',
      E_STOP_ALL = 'stop_all',
      E_STOP = 'stop';

  private
    /**
     * A PHPSQLParser object
     * @var \PHPSQLParser
     */
      $parser,
    /**
     * A PHPSQLCreator object
     * @var PHPSQLCreator
     */
      $creator,
    /**
     * A PHPFastCache object
     */
      $cacher,
    /**
     * The SQL engines supported by this class (needs the according language class)
     * @var array
     */
      $accepted_languages = ['mysql', 'sqlite'],
    /**
     * Connection configuration
     * @var array $cfg
     */
      $cfg = false,
    /**
     * @todo is bool or string??
     * Unique string identifier for current connection
     * @var bool
     */
      $hash,
    /**
     * @var mixed $cache
     */
      $cache = [],
    /**
     * If set to false, query will return a regular PDOStatement
     * Use stop_fancy_stuff() to set it to false
     * And use start_fancy_stuff to set it back to true
     * @var bool $fancy
     */
      $fancy = 1,
    /**
     * @var array $debug_queries
     */
      $debug_queries = [],
    /**
     * Error state of the current connection
     * @var bool $has_error
     */
      $has_error = false;

  protected
    /**
     * @var db\languages\mysql Can be other driver
     */
      $language = false,
    /**
     * @var integer $cache_renewal
     */
      $cache_renewal = 3600,
    /**
     * @var mixed $max_queries
     */
      $max_queries = 50,
    /**
     * @var mixed $last_insert_id
     */
      $last_insert_id,
    /**
     * @var mixed $last_insert_id
     */
      $id_just_inserted,
    /**
     * @var mixed $hash_contour
     */
      $hash_contour = '__BBN__',
    /**
     * @var mixed $last_prepared
     */
      $last_prepared,
    /**
     * @var array $queries
     */
      $queries = [],
    /**
     * @var string $on_error
     * Possible values:
     * *    stop: the script will go on but no further database query will be executed
     * *    die: the script will die with the error
     * *    continue: the script and further queries will be executed
     */
      $on_error = self::E_STOP_ALL;

  public
    /**
     * The quote character for table and column names
     * @var string $qte
     */
      $qte,
    /**
     * @var string $last_error
     */
      $last_error = false,
    /**
     * @var string \$last_query
     */
      $last_query,
    /**
     * @var boolean $debug
     */
      $debug = false,
    /**
     * The ODBC engine of this connection
     * @var string $engine
     */
      $engine,
    /**
     * The host of this connection
     * @var string $host
     */
      $host,
    /**
     * The currently selected database
     * @var mixed $current
     */
      $current,
    /**
     * The information that will be accessed by db\query as the current statement's options
     * @var array $last_params 
     */
      $last_params = ['sequences' => false, 'values' => false];

  private static
    /**
     * Error state of the current connection
     * @var bool $has_error_all
     */
      $has_error_all = false;
  /**
   * @var int
   */

  protected static
    /**
     * @var string $line
     */
      $line = '---------------------------------------------------------------------------------';

  private static function has_error()
  {
    self::$has_error_all = true;
  }

  /**
   *
   * @param $item string 'db_name' or 'table'
   * @param $mode string 'columns','tables' or'databases'
   * @return bool|string
   */
  private function _cache_name($item, $mode){
    $r = false;
    $h = str::encode_filename($this->host);
    switch ( $mode ){
      case 'columns':
        $r = 'bbn/db/'.$this->engine.'/'.$h.'/'.str_replace('.', '/', $this->tfn($item));
        break;
      case 'tables':
        $r = 'bbn/db/'.$this->engine.'/'.$h.'/'.($item ?: $this->db->current);
        break;
      case 'databases':
        $r = 'bbn/db/'.$this->engine.'/'.$h.'/_bbn-database';
        break;
    }
    return $r;
  }

  /**
   * Return the table's structure's array, either from the cache or from _modelize().
   *
   * @param $item
   * @param string $mode
   * @param bool $force
   * @return bool|mixed
   */
  private function _get_cache($item, $mode = 'columns', $force = false){
    $cache_name = $this->_cache_name($item, $mode);
    if ( $force && isset($this->cache[$cache_name]) ){
      unset($this->cache[$item]);
    }
    if ( !isset($this->cache[$cache_name]) ){
      $tmp = $this->cacher->get($cache_name);
      if ( !$tmp || $force ){
        switch ( $mode ){
          case 'columns':
            $keys = $this->language->get_keys($item);
            $cols = $this->language->get_columns($item);
            if ( \is_array($keys) && \is_array($cols) ){
              $tmp = [
                  'keys' => $keys['keys'],
                  'cols' => $keys['cols'],
                  'fields' => $cols
              ];
            }
            break;
          case 'tables':
            $tmp = $this->language->get_tables($item);
            break;
          case 'databases':
            $tmp = $this->language->get_databases();
            break;
        }
        if ( !\is_array($tmp) ){
          die("Error in table $item or mode $mode");
        }
        if ( $tmp ){
          $this->cacher->set($cache_name, $tmp, $this->cache_renewal);
        }
      }
      if ( $tmp ){
        $this->cache[$cache_name] = $tmp;
      }
    }
    return isset($this->cache[$cache_name]) ? $this->cache[$cache_name] : false;
  }

  /**
   *
   * @param string $type insert, insert_update, update, delete
   * @param string $table the table name
   * @param array | boolean or Where string for delete case
   * @param array | boolean $arg4 Where string or ignore
   * @return string A SQL statement or false
   */
  private function _statement($type, $table, array $keypairs=[], $arg4=[], $arg5=false){
    switch ( $type ){
      case 'insert':
        $hash = $this->make_hash('insert', $table, serialize($keypairs), $arg4);
        if ( isset($this->queries[$hash]) ){
          $sql = $this->queries[$this->queries[$hash]]['statement'];
        }
        else{
          $sql = $this->language->get_insert($table, $keypairs, $arg4);
        }
        break;
      case 'insert_update':
        $hash = $this->make_hash('insert_update', $table, serialize($keypairs));
        if ( isset($this->queries[$hash]) ){
          $sql = $this->queries[$this->queries[$hash]]['statement'];
        }
        else if ( $sql = $this->language->get_insert($table, $keypairs) ){
          $sql .= " ON DUPLICATE KEY UPDATE ";
          foreach ( $keypairs as $c ){
            $sql .= $this->escape($c)." = ?, ";
          }
          $sql = substr($sql,0,strrpos($sql,','));
        }
        break;
      case 'update':
        $hash = $this->make_hash('update', $table, serialize($keypairs), serialize($arg4['unique']));
        if ( isset($this->queries[$hash]) ){
          $sql = $this->queries[$this->queries[$hash]]['statement'];
        }
        else{
          $sql = $this->language->get_update($table, $keypairs, $arg4);
        }
        break;
      case 'delete':
        $hash = $this->make_hash('delete', $table, serialize($keypairs['unique']), $arg4);

        if ( isset($this->queries[$hash]) ){
          $sql = $this->queries[$this->queries[$hash]]['statement'];
        }
        else{
          $sql = $this->language->get_delete($table, $keypairs['final']);
         // die(var_dump($sql, $hash) );
        }
        break;
    }
    return isset($sql, $hash) ? ['sql' => $sql, 'hash' => $hash] : false;
  }

  /**
   * @todo Thomas fais ton taf!!
   *
   * @param $e
   * @return void
   */
  public function error($e)
  {
    $this->has_error = true;
    self::has_error();
    $msg = [
        self::$line,
        'Error in the page!',
        self::$line
    ];
    $b = debug_backtrace();
    foreach ( $b as $c ){
      if ( isset($c['file']) ){
        array_push($msg,'File '.$c['file'].' - Line '.$c['line']);
        array_push($msg,
            ( isset($c['class']) ?  '  Class '.$c['class'].' - ' : '' ).
            ( isset($c['function']) ?  '  Function '.$c['function'] : '' )/*.
					( isset($c['args']) ? 'Arguments: '.substr(print_r($c['args'],1),0,100) : '' )*/
        );
      }
    }
    array_push($msg,self::$line);
    if ( \is_string($e) ){
      array_push($msg, $e);
    }
    else if ( method_exists($e, "getMessage") ){
      array_push($msg, $e->getMessage());
    }
    $this->last_error = end($msg);
    array_push($msg, self::$line);
    array_push($msg, $this->last());
    array_push($msg, self::$line);
    if ( $this->last_params['values'] ){
      array_push($msg, self::$line);
      array_push($msg, 'Parameters');
      array_push($msg, self::$line);
      array_push($msg, x::get_dump($this->last_params['values']));
      array_push($msg, self::$line);
    }
    $this->log(implode(PHP_EOL, $msg));
    if ( $this->on_error === self::E_DIE ){
      die(\defined('BBN_IS_PROD') && BBN_IS_PROD ? 'Database error' : implode(PHP_EOL, $msg));
    }
  }

  /**
   * Checks if the database is ready to process a query.
   *
   * ```php
   * bbn\x::dump($db->check());
   * // (bool)
   * ```
   * @return bool
   */
  public function check()
  {
    if ( isset($this->current) ){
      if ( $this->on_error === self::E_CONTINUE ){
        return 1;
      }
      if ( self::$has_error_all && ($this->on_error !== self::E_STOP_ALL) ){
        return false;
      }
      if ( $this->has_error && ($this->on_error !== self::E_STOP) ){
        return false;
      }
      return 1;
    }
    return false;
  }
  /**
   * rructor
   * @todo Thomas fais ton taf!!
   *
   * ```php
   * $dbtest = new bbn\db(['db_user' => 'test','db_engine' => 'mysql','db_host' => 'host','db_pass' => 't6pZDwRdfp4IM']);
   *  // (void)
   * ```
   * @param array $cfg Mandatory db_user db_engine db_host db_pass
   */
  public function __construct($cfg=[])
  {
    if ( !isset($cfg['engine']) && \defined('BBN_DB_ENGINE') ){
      $cfg['engine'] = BBN_DB_ENGINE;
    }
    if ( isset($cfg['engine']) ){
      $cls = '\bbn\\db\\languages\\'.$cfg['engine'];
      if ( !class_exists($cls) ){
        die("Sorry the engine class $cfg[engine] does not exist");
      }
      self::retriever_init($this);
      $this->language = new $cls($this);
      if ( isset($cfg['on_error']) ){
        $this->on_error = $cfg['on_error'];
      }
      $this->cacher = cache::get_engine();
      if ( $cfg = $this->language->get_connection($cfg) ){
        $this->qte = $this->language->qte;
        try{
          \call_user_func_array('parent::__construct', $cfg['args']);
          $this->current = $cfg['db'];
          $this->engine = $cfg['engine'];
          $this->host = isset($cfg['host']) ? $cfg['host'] : '127.0.0.1';
          $this->hash = $this->make_hash($cfg['args']);
          $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
          if ( !empty($cfg['cache_length']) ){
            $this->cache_renewal = (int)$cfg['cache_length'];
          }
          $this->start_fancy_stuff();
          // SQLite has not keys enabled by default
          $this->enable_keys();
        }
        catch ( \PDOException $e ){
          x::log("Impossible to create the connection for ".$cfg['engine']."/".$cfg['db'], 'db');
          die(\defined('bbn_IS_DEV') && BBN_IS_DEV ? var_dump($e) : 'Impossible to create the database connection');
        }
      }
    }
  }

  /**
   * Return an array with tables and fields related to the searched foreign key.
   *
   * ```php
   * bbn\x::dump($db->get_foreign_keys('id', 'table_users', 'db_example'));
   * // (Array)
   * ```
   *
   * @param string $col The column's name
   * @param string $table The table's name
   * @param string $db The database name if different from the current one
   * @return array with tables and fields related to the searched foreign key
   */
  public function get_foreign_keys(string $col, string $table, string $db = null){
    if ( !$db ){
      $db = $this->current;
    }
    $res = [];
    $model = $this->modelize();
    foreach ( $model as $tn => $m ){
      foreach ( $m['keys'] as $k => $t ){
        if ( ($t['ref_table'] === $table) &&
            ($t['ref_column'] === $col) &&
            ($t['ref_db'] === $db) &&
            (\count($t['columns']) === 1)
        ){
          if ( !isset($res[$tn]) ){
            $res[$tn] = [$t['columns'][0]];
          }
          else{
            array_push($res[$tn], $t['columns'][0]);
          }
        }
      }
    }
    return $res;
  }

  /**
   * Return the log in data/logs/db.log.
   *
   * ```php
   * $db->$db->log('test');
   * ```
   * @param string $st
   */

  public function log($st){
    $args = \func_get_args();
    foreach ( $args as $a ){
      x::log($a, 'db');
    }
  }

  /**
   * Sets the error mode.
   * @todo return data
   *
   * ```php
   * $db->set_error_mode('continue'|'die'|'stop_all|'stop');
   * // (void)
   * ```
   *
   * @param string $mode The error mode: "continue", "die", "stop", "stop_all".
   */
  public function set_error_mode($mode){
    $this->on_error = $mode;
  }

  /**
   * Gets the error mode.
   *
   * ```php
   * bbn\x::dump($db->get_error_mode());
   * // (string) stop_all
   * ```
   * @return string
   */
  public function get_error_mode(){
    return $this->on_error;
  }

  /**
   * Deletes a specific item from the cache.
   *
   * ```php
   * bbn\x::dump($db->clear_cache('db_example','tables'));
   * // (db)
   * ```
   *
   * @param string $item 'db_name' or 'table_name'
   * @param string $mode 'columns','tables' or'databases'
   * @return db
   */
  public function clear_cache($item, $mode){
    $cache_name = $this->_cache_name($item, $mode);
    if ( $this->cacher->has($cache_name) ){
      $this->cacher->delete($cache_name);
    }
    return $this;
  }

  /**
   * Clears the cache.
   *
   * @todo clear_all_cache() with $this->language->get_databases etc...
   *
   * ```php
   * bbn\x::dump($db->clear_all_cache());
   * // (db)
   * ```
   *
   * @return db
   */
  public function clear_all_cache(){
    $this->cacher->delete_all('bbn/db/'.$this->engine);
    return $this;
  }

  /**
   * Stops fancy stuff.
   *
   * @todo return data --errore 500
   *
   * ```php
   *  $db->stop_fancy_stuff();
   * // (void)
   * ```
   *
   * @return void
   */
  public function stop_fancy_stuff(){
    $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, ['\PDOStatement']);
    $this->fancy = false;
  }

  /**
   * Starts fancy stuff.
   *
   * @todo return data -errore 500
   *
   * ```php
   * $db->start_fancy_stuff();
   * // (void)
   * ```
   * @return void
   */
  public function start_fancy_stuff(){
    $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, ['\bbn\\db\\query',[$this]]);
    $this->fancy = 1;
  }

  /**
   * Clear.
   *
   * @todo return data
   *
   * ```php
   * $db->clear()
   * // (void)
   * ```
   *
   * @return void
   */
  public function clear()
  {
    $this->queries = [];
  }

  /**
   * Escapes names with the appropriate quotes (db, tables, columns, keys...)
   *
   * ```php
   * bbn\x::dump($db->escape("table_users"));
   * // (string) `table_users`
   * ```
   *
   * @param string $item The name to escape.
   * @return string
   */
  public function escape($item){
    return $this->language->escape($item);
  }

  /**
   * Return a string with quotes and percent escaped.
   *
   * ```php
   * bbn\x::dump($db->escape_value("My father's job is interesting");
   * // (string) My  father\'s  job  is  interesting
   * ```
   *
   * @param string $value The string to escape.
   * @param string $esc
   * @return mixed
   *
   */
  public function escape_value($value, $esc = "'"){
    if ( \is_string($value) ){
      return str_replace('%', '\\%', $esc === '"' ?
          str::escape_dquotes($value) :
          str::escape_squotes($value));
    }
    return $value;
  }

  /**
   * Changes the value of last_insert_id (used by history).
   * @todo this function should be private
   *
   * ```php
   * bbn\x::dump($db->set_last_insert_id());
   * // (db)
   * ```
   * @param int $id The last inserted id
   * @return db
   */
  public function set_last_insert_id($id=''){
    if ( $id === '' ){
      if ( $this->id_just_inserted ){
        $id = $this->id_just_inserted;
        $this->id_just_inserted = null;
      }
      else{
        $id = $this->lastInsertId();
        if ( \is_string($id) && str::is_integer($id) ){
          $id = (int)$id;
        }
      }
    }
    else{
      $this->id_just_inserted = $id;
    }
    $this->last_insert_id = $id;
    return $this;
  }

  /**
   * Return table's full name.
   *
   * ```php
   * bbn\x::dump($db->table_full_name("table_users"));
   * // (String) db_example.table_users
   * bbn\x::dump($db->table_full_name("table_users", true));
   * // (String) `db_example`.`table_users`
   * ```
   *
   * @param string $table The table's name (escaped or not).
   * @param bool $escaped If set to true the returned string will be escaped.
   * @return string | false
   */
  public function table_full_name($table, $escaped=false){
    return $this->language->table_full_name($table, $escaped);
  }

  /**
   * Return table's full name.
   * (similar to {@link table_full_name()})
   *
   * ```php
   * \bbn\x::dump($db->tfn("table_users"));
   * // (String) db_example.table_users
   * \bbn\x::dump($db->tfn("table_users", true));
   * // (String) `db_example`.`table_users`
   * ```
   *
   * @param string $table The table's name
   * @param bool $escaped If set to true the returned string will be escaped
   * @return string | false
   */


  public function tfn($table, $escaped=false){
    return $this->table_full_name($table, $escaped);
  }

  /**
   * Return table's simple name.
   *
   * ```php
   * \bbn\x::dump($db->table_simple_name("example_db.table_users"));
   * // (string) table_users
   * \bbn\x::dump($db->table_simple_name("example.table_users", true));
   * // (string) `table_users`
   * ```
   *
   * @param string $table The table's name (escaped or not)
   * @param bool $escaped If set to true the returned string will be escaped
   * @return string | false
   */
  public function table_simple_name($table, $escaped=false){
    return $this->language->table_simple_name($table, $escaped);
  }

  /**
   * Return table's simple name.
   * (similar to {@link table_simple_name()})
   *
   * ```php
   * \bbn\x::dump($db->tsn("work_db.table_users"));
   * // (string) table_users
   * \bbn\x::dump($db->tsn("work_db.table_users", true));
   * // (string) `table_users`
   * ```
   *
   * @param $table The table's name
   * @param bool $escaped If set to true the returned string will be escaped.
   * @return false | string
   */
  public function tsn($table, $escaped=false){
    return $this->table_simple_name($table, $escaped);
  }

  /**
   * Return column's full name.
   *
   * ```php
   * \bbn\x::dump($db->col_full_name("name", "table_users"));
   * // (string)  table_users.name
   * \bbn\x::dump($db->col_full_name("name", "table_users", true));
   * // (string) \`table_users\`.\`name\`
   * ```
   *
   * @param string $col The column's name (escaped or not)
   * @param string $table The table's name (escaped or not)
   * @param bool $escaped If set to true the returned string will be escaped
   * @return string | false
   */
  public function col_full_name($col, $table='', $escaped=false){
    return $this->language->col_full_name($col, $table, $escaped);
  }

  /**
   * Return column's full name.
   * (similar to {@link col_full_name()})
   *
   * ```php
   * \bbn\x::dump($db->cfn("name", "table_users"));
   * // (string)  table_users.name
   * \bbn\x::dump($db->cfn("name", "table_users", true));
   * // (string) \`table_users\`.\`name\`
   * ```
   *
   * @param string $col The column's name (escaped or not).
   * @param string $table The table's name (escaped or not).
   * @param bool $escaped If set to true the returned string will be escaped.
   * @return string | false
   */
  public function cfn($col, $table='', $escaped = false){
    return $this->col_full_name($col, $table, $escaped);
  }

  /**
   * Return the column's simple name.
   *
   * ```php
   * \bbn\x::dump($db->col_simple_name("table_users.name"));
   * // (string) name
   * \bbn\x::dump($db->col_simple_name("table_users.name", true));
   * // (string) `name`
   * ```
   *
   * @param string $col The column's complete name (escaped or not).
   * @param bool $escaped If set to true the returned string will be escaped.
   * @return string | false
   */
  public function col_simple_name($col, $escaped = false){
    return $this->language->col_simple_name($col, $escaped);
  }

  /**
   * Return the column's simple name.
   * (similar to {@link col_simple_name()})
   *
   * ```php
   * \bbn\x::dump($db->csn("table_users.name"));
   * // (string) name
   * \bbn\x::dump($db->csn("table_users.name", true));
   * // (string) `name`
   * ```
   *
   * @param string $col The column's complete name (escaped or not)
   * @param bool $escaped If set to true the returned string will be escaped.
   * @return string | false
   */
  public function csn($col, $escaped = false){
    return $this->col_simple_name($col, $escaped);
  }

  /**
   * Makes a string that will be the id of the request.
   *
   * @return string
   *
   */
  private function make_hash()
  {
    $args = \func_get_args();
    if ( (\count($args) === 1) && \is_array($args[0]) ){
      $args = $args[0];
    }
    $st = '';
    foreach ( $args as $a ){
      $st .= serialize($a);
    }
    return $this->hash_contour.md5($st).$this->hash_contour;
  }

  /**
   * Gets the last hash created.
   * @todo chiedere e thomas se deve diventare private e se va bene la descrizione
   *
   * ```php
   * \bbn\x::dump($db->get_hash());
   * // (string) 3819056v431b210daf45f9b5dc2
   * ```
   *
   * @return string
   */
  public function get_hash()
  {
    return $this->hash;
  }


  /**
   * Return an object with all the properties of the statement and where it is carried out.
   *
   * ```php
   * \bbn\x::dump($db->add_statement('SELECT name FROM table_users'));
   * // (db)
   * ```
   *
   * @param string $statement
   * @return db
   */
  public function add_statement($statement){
    $this->last_query = $statement;
    if ( $this->debug ){
      array_push($this->debug_queries, $statement);
    }
    return $this;
  }

  /**
   * Return true if in the table there are fields with auto-increment.
   * Working only on mysql.
   *
   * ```php
   * \bbn\x::dump($db->has_id_increment('table_users'));
   * // (bool) 1
   * ```
   *
   * @todo: working only on mysql
   * @param string $table The table's name
   * @return boolean
   */
  public function has_id_increment($table){
    if ( $model = $this->modelize($table) ){
      if ( isset($model['keys']['PRIMARY']) &&
          (\count($model['keys']['PRIMARY']['columns']) === 1) &&
          ($model['fields'][$model['keys']['PRIMARY']['columns'][0]]['extra'] === 'auto_increment') ){
        return 1;
      }
    }
    return false;
  }

  /**
   * Return a SQL query based on a configuration (??)
   *
   * @todo chiedere a thomas, non siamo riusciti a provarla
   * @todo Check the configuration format
   *
   * @param array $cfg Description
   * @return string
   */
  public function create_query($cfg){
    if ( !isset($this->creator) ){
      $this->creator = new \PHPSQLParser\PHPSQLCreator();
    }
    return $this->creator->create($cfg);
  }

  /**
   * Parses a SQL query and return an array.
   *
   * @todo chiedere a thomas
   *
   * @param string $cfg
   * @return array
   */
  public function parse_query($cfg)
  {
    if ( !isset($this->parser) ){
      $this->parser = new \PHPSQLParser\PHPSQLParser();
    }
    $r = $this->parser->parse($cfg);
    if ( !count($r) ){
      return false;
    }
    if ( isset($r['BRACKET']) && (\count($r) === 1) ){
      return false;
    }
    return $r;
  }

  /**
   * Return the last query for this connection.
   *
   * ```php
   * \bbn\x::dump($db->last());
   * // (string) INSERT INTO `db_example.table_user` (`name`) VALUES (?)
   * ```
   *
   * @return string
   */
  public function last()
  {
    return $this->last_query;
  }

  /**
   * Return the last inserted ID.
   *
   * ```php
   * \bbn\x::dump($db->last_id());
   * // (int) 26
   * ```
   *
   * @return int
   */
  public function last_id()
  {
    if ( $this->last_insert_id ){
      return str::is_buid($this->last_insert_id) ? bin2hex($this->last_insert_id) : $this->last_insert_id;
    }
    return false;
  }

  public function get_uid()
  {
    //return hex2bin(str_replace('-', '', \bbn\x::make_uid()));
    return $this->language->get_uid();
  }

  public function add_uid($uid_table = 'bbn_history_uids', $uid_col = 'uid'){
    $uid = $this->get_uid();
    $this->set_last_insert_id($uid);
    return $uid;
  }

  /**
   * Adds the specs of a query to the $queries object.
   *
   * @param string $hash
   * @param string $statement
   * @param array $sequences
   * @param array $placeholders
   * @param array $options
   */
  private function add_query($hash, $statement, $sequences, $placeholders, $options)
  {
    $this->queries[$hash] = [
      'statement' => $statement,
      'sequences' => $sequences,
      'placeholders' => $placeholders,
      'options' => $options,
      'num' => 0,
      'exe_time' => 0,
      'prepared' => false
    ];
    while ( \count($this->queries) > $this->max_queries ){
      array_shift($this->queries);
    }
  }

  /**
   * Changes the database used to the given one.
   *
   * ```php
   * $db = new db();
   * x::dump($db->change('db_example'));
   * // (db)
   * ```
   *
   * @param string $db The database's name
   * @return db
   */
  public function change($db)
  {
    if ( $this->language->change($db) ){
      $this->current = $db;
    }
    return $this;
  }

  /**
   * Disables foreign keys constraints.
   *
   * ```php
   * \bbn\x::dump($db->disable_keys());
   * // (db)
   * ```
   *
   * @return db
   */
  public function disable_keys()
  {
    $this->language->disable_keys();
    return $this;
  }

  /**
   * Enables foreign keys constraints.
   *
   * ```php
   * \bbn\x::dump($db->enable_keys());
   * // (db)
   * ```
   *
   * @return db
   */
  public function enable_keys(): self
  {
    $this->language->enable_keys();
    return $this;
  }

  public function flush(){
    $num = \count($this->queries);
    $this->queries = [];
    return $num;
  }

  /**
   * Return an array with the count of values corresponding to the where conditions.
   *
   * ```php
   * \bbn\x::dump($db->stat('table_user', 'name', ['name' => '%n']));
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
   * @param string $table The table's name.
   * @param string $column The field's name.
   * @param array $where The "where" condition.
   * @param array $order The "order" condition.
   * @param int $limit The "limit" condition.
   * @param int $start The "start" condition.
   * @return array
   */
  public function stat($table, $column, $where = [], $order = [], $limit = 0, $start = 0){
    if ( $this->check() ){
      $where = $this->where_cfg($where, $table);
      $sql = 'SELECT COUNT(*) AS '.$this->qte.'num'.$this->qte.', '.
          $this->csn($column, 1).PHP_EOL.
          'FROM '.$this->tfn($table, 1).PHP_EOL.
          $this->get_where($where, $table).PHP_EOL.
          'GROUP BY '.$this->csn($column, 1).PHP_EOL.
          ( empty($order) ?
              'ORDER BY '.$this->qte.'num'.$this->qte.' DESC'
              : $this->get_order($order)
          ).PHP_EOL.
          $this->get_order($limit, $start);
      return $this->get_rows($sql, $where['final']);
    }
  }

  /**
   * Executes the original PDO query function
   * @todo far vedere a thomams perche non ho capito a che serve, prima c'era scritto return \PDO::query, però ritorna false
   *
   * ```php
   * \bbn\x::dump($db->raw_query());
   * // (bool)
   * ```
   * @return boolean
   */
  public function raw_query(){
    if ( $this->check() ){
      $args = \func_get_args();
      return \call_user_func_array('parent::query', $args);
    }
  }

  /**
   * Executes a writing statement and return the number of affected rows or return a query object for the reading * statement
   * @todo far vedere a thomams perche non funziona in lettura
   *
   * ```php
   * \bbn\x::dump($db->query("DELETE * FROM table_users WHERE id > 0"));
   * // (int) 3
   * \bbn\x::dump($db->query("SELECT * FROM table_users WHERE name = 'John"));
   * // (db)
   * ```
   *
   * @param string
   * @return int | db\query
   */
  public function query(){
    if ( $this->check() ){
      $args = \func_get_args();
      if ( !$this->fancy ){
        return \call_user_func_array('parent::query', $args);
      }
      if ( \count($args) === 1 && \is_array($args[0]) ){
        $args = $args[0];
      }

      if ( !empty($args[0]) && \is_string($args[0]) ){

        // The first argument is the statement
        $statement = trim(array_shift($args));
        $hash = $this->make_hash($statement);

        // Sending a hash as second argument from statement generating functions will bind it to the statement
        if ( isset($args[0]) && \is_string($args[0]) &&
            ( \strlen($args[0]) === ( 32 + 2*\strlen($this->hash_contour) ) ) &&
            ( strpos($args[0], $this->hash_contour) === 0 ) &&
            ( substr($args[0],-\strlen($this->hash_contour)) === $this->hash_contour ) ){
          $hash_sent = array_shift($args);
        }

        // Case where drivers are arguments
        if ( isset($args[0]) && \is_array($args[0]) && !array_key_exists(0,$args[0]) ){
          $driver_options = array_shift($args);
        }

        // Case where values are argument
        else if ( isset($args[0]) &&
            \is_array($args[0]) &&
            (\count($args) === 1) ){
          $args = $args[0];
        }
        if ( !isset($driver_options) ){
          $driver_options = [];
        }
        $this->last_params['values'] = [];
        $num_values = 0;
        foreach ( $args as $i => $arg ){
          if ( !\is_array($arg) ){
            $this->last_params['values'][] = $arg;
            $num_values++;
          }
          else if ( isset($arg[2]) ){
            $this->last_params['values'][] = $arg[2];
            $num_values++;
          }
        }
        if ( !isset($this->queries[$hash]) ){
          if ( $sequences = $this->parse_query($statement) ){
            /* Or looking for question marks */
            preg_match_all('/(\?)/', $statement, $exp);
            $this->add_query(
                $hash,
                $statement,
                $sequences,
                isset($exp[1]) && \is_array($exp[1]) ? \count($exp[1]) : 0,
                $driver_options);
            if ( isset($hash_sent) ){
              $this->queries[$hash_sent] = $hash;
            }
          }
          else if ( ($this->engine === 'sqlite') && (strpos($statement, 'PRAGMA') === 0) ){
            $sequences = ['PRAGMA' => $statement];
            $this->add_query(
                $hash,
                $statement,
                $sequences,
                0,
                $driver_options);
            if ( isset($hash_sent) ){
              $this->queries[$hash_sent] = $hash;
            }
          }
          else{
            die(\defined('bbn_IS_DEV') && BBN_IS_DEV ? "Impossible to parse the query $statement" : 'Impossible to parse the query');
          }
        }
        else if ( \is_string($this->queries[$hash]) ){
          $hash = $this->queries[$hash];
        }
        /* If the number of values is inferior to the number of placeholders we fill the values with the last given value */
        if ( $num_values < $this->queries[$hash]['placeholders'] ){
          $this->last_params['values'] = array_merge($this->last_params['values'], array_fill($num_values, $this->queries[$hash]['placeholders'] - $num_values, end($this->last_params['values'])));
          $num_values = \count($this->last_params['values']);
        }
        /* The number of values must match the number of placeholders to bind */
        if ( $num_values !== $this->queries[$hash]['placeholders'] ){
          $this->error('Incorrect arguments count (your values: '.$num_values.', in the statement: '.$this->queries[$hash]['placeholders']."\n\n".$statement."\n\n".'start of values'.print_r($this->last_params['values'], 1).'Arguments:'.print_r(\func_get_args(),1));
          exit;
        }
        $q =& $this->queries[$hash];
        $this->last_params['sequences'] = $q['sequences'];
        $this->queries[$hash]['num']++;
        if ( $q['exe_time'] === 0 ){
          $t = microtime(1);
        }
        $this->add_statement($q['statement']);
        if ( isset($q['sequences']['DROP']) || isset($q['sequences']['CREATE']) || isset($q['sequences']['ALTER']) ){
          // A voir
          //$this->clear_cache();
        }
        try{
          if ( $q['prepared'] && $this->is_write_sequence($q['sequences']) ){
            $r = $q['prepared']->init($this->last_params['values'])->execute();
          }
          else{
            if ( $this->is_write_sequence($q['sequences']) ){
              if ( $num_values === 0 ){
                $r = $this->exec($q['statement']);
              }
              else{
                $q['prepared'] = $this->prepare($q['statement'], $q['options']);
                $r = $q['prepared']->execute();
              }
              if ( isset($t) && $q['exe_time'] === 0 ){
                $q['exe_time'] = microtime(1) - $t;
              }
            }
            else{
              if ( !$q['prepared'] ){
                $q['prepared'] = $this->prepare($q['statement'], $driver_options);
                if ( isset($t) && $q['exe_time'] === 0 ){
                  $q['exe_time'] = microtime(1) - $t;
                }
              }
              else{
                $q['prepared']->init($this->last_params['values']);
              }
            }
          }
        }
        catch (\PDOException $e ){
          $this->error($e);
        }
        if ( $this->check() ){
          if ( !isset($r) ){
            return $q['prepared'];
          }
          if ( isset($q['sequences']['INSERT']) ){
            $this->set_last_insert_id();
          }
          if ( $q['prepared'] && ( isset($q['sequences']['INSERT']) || isset($q['sequences']['UPDATE']) || isset($q['sequences']['DELETE']) || isset($q['sequences']['DROP']) ) ){
            $n = $q['prepared']->rowCount();
            return $n;
          }
          return $r;
        }
      }
    }
    return false;
  }

  public function is_write_sequence($sequences){
    return isset($sequences['INSERT']) || isset($sequences['UPDATE']) || isset($sequences['DELETE']) || isset($sequences['DROP']) || isset($sequences['ALTER']) || isset($sequences['CREATE']);
  }

  /**
   * Return a single value from a request based on arguments.
   *
   * ```php
   * \bbn\x::dump($db->get_val("table_users", "surname", "name", "Julien"));
   * // (string) Smith
   * ```
   *
   * @param string $table The table's name.
   * @param string $field_to_get The field to get.
   * @param string|array $field_to_check Name of the field in which search the value.
   * @param string $value The value to check.
   * @return string | false
   */
  public function get_val($table, $field_to_get, $field_to_check='', $value=''){
    if ( \is_array($field_to_check) ){
      $where = $field_to_check;
    }
    else if ( !empty($field_to_check) && !empty($value) ){
      $where = [$field_to_check => $value];
    }
    else{
      $where = [];
    }
    if ( $s = $this->select($table, [$field_to_get], $where)){
      return $s->$field_to_get;
    }
    return false;
  }

  /**
   * Return a single value from a request based on arguments.
   *
   * ```php
   * \bbn\x::dump($db->val_by_id("table_users", "surname", "138"));
   * // (string) Smith
   * ```
   *
   * @param string $table The table's name.
   * @param string $field The field's name.
   * @param string $id The "id" value.
   * @param string $col The column's name in which search for the value, default: 'id'.
   * @return string | false
   */
  public function val_by_id($table, $field, $id, $col='id'){
    return $this->select_one($table, $field, [$col => $id]);
  }

  /**
   * Generate a new casual id based on the max number of characters of id's column structure in the given table
   *
   * ```php
   * \bbn\x::dump($db->new_id('table_users', 18));
   * // (int) 69991701
   * ```
   *
   * @param string $table The table's name.
   * @param int $min
   * @return int
   */
  public function new_id($table, $min = 1){
    $tab = $this->modelize($table);
    if ( \count($tab['keys']['PRIMARY']['columns']) !== 1 ){
      die("Error! Unique numeric primary key doesn't exist");
    }
    else if (
        ($id_field = $tab['keys']['PRIMARY']['columns'][0]) &&
        ($maxlength = $tab['fields'][$id_field]['maxlength'] )&&
        ($maxlength > 1)
    ){
      $max = (10 ** $maxlength) - 1;
      if ( $max >= mt_getrandmax() ){
        $max = mt_getrandmax();
      }
      if ( ($max > 1) && ($table = $this->tfn($table, 1)) ){
        $i = 0;
        do {
          $id = random_int(1, $max);
          /** @todo */
          /*
          if ( strpos($tab['fields'][$id_field]['type'], 'char') !== false ){
            $id = substr(md5('bbn'.$id), 0, random_int(1, 10 ** $maxlength));
          }
          */
          $i++;
        }
        while ( ($i < 100) && $this->select($table, [$id_field], [$id_field => $id]) );
        return $id;
      }
      return false;
    }
  }

  public function random_value($col, $table){
    $tab = $this->modelize($table);
    if ( isset($tab['fields'][$col]) ){
      switch ( $tab['fields'][$col]['type'] ){
        case 'int':
          if ( ($tab['fields'][$col]['maxlength'] === 1) && !$tab['fields'][$col]['signed'] ){
            $val = microtime(true) % 2 === 0 ? 1 : 0;
          }
          else {
            $max = 10 ** $tab['fields'][$col]['maxlength'] - 1;
            if ( $max > mt_getrandmax() ){
              $max = mt_getrandmax();
            }
            if ( $tab['fields'][$col]['signed'] ){
              $max = $max / 2;
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
      if ( isset($val) ){
        foreach ( $tab['keys'] as $key => $cfg ){
          if (
            $cfg['unique'] &&
            \in_array($col, $cfg['columns'], true) &&
            \is_null($cfg['ref_column']) &&
            (\count($cfg['columns']) === 1) &&
            $this->select_one($table, $col, [$col => $val])
          ){
            return $this->random_value($col, $table);
          }
        }
      }
      return $val;
    }
  }

  /**
   * Return the integer which will be the next incremented ID in the given table.
   *
   * ```php
   * \bbn\x::dump($db->next_id("table_users"));
   * // (int) 19
   * ```
   *
   * @param string $table The table's name.
   * @return int
   */
  public function next_id($table){
    $tab = $this->modelize($table);
    if ( \count($tab['keys']['PRIMARY']['columns']) !== 1 ){
      die("Error! Unique numeric primary key doesn't exist");
    }
    if ( $id_field = $tab['keys']['PRIMARY']['columns'][0] ){
      if ( $cur = (int)$this->get_one("SELECT MAX(".$this->escape($id_field).") FROM ".$this->escape($table)) ){
        return $cur + 1;
      }
      return 1;
    }
    return false;
  }

  /**
   * Return an indexed array with the first result of the query or false if there are no results.
   *
   * ```php
   * \bbn\x::dump($db->fetch("SELECT name FROM users WHERE id = 10"));
   * /* (array)
   * [
   *  "name" => "john",
   *  0 => "john",
   * ]
   * ```
   *
   * @param string $query
   * @return array | false
   */
  public function fetch($query){
    if ( $r = \call_user_func_array([$this, 'query'], \func_get_args()) ){
      return $r->fetch();
    }
    return false;
  }

  /**
   * Return an array of indexed array with all results of the query or false if there are no results.
   *
   * ```php
   * \bbn\x::dump($db->fetchAll("SELECT 'surname', 'name', 'id' FROM users WHERE name = 'john'"));
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
   * @return array | false
   */
  public function fetchAll($query){
    if ( $r = \call_user_func_array([$this, 'query'], \func_get_args()) ){
      return $r->fetchAll();
    }
    return false;
  }

  /**
   * Transposition of the original fetchColumn method, but with the query included. Return an arra or false if no result
   * @todo confusion between result's index and this->query arguments(IMPORTANT). Missing the example because the function doesn't work
   *
   * @param $query
   * @return string | false
   */
  public function fetchColumn($query, $num=0)
  {
    if ( $r = \call_user_func_array([$this, 'query'], \func_get_args()) ){
      return $r->fetchColumn(\is_int($num) ? $num : 0);
    }
    return false;
  }

  /**
   * Return an array with stdClass object or false if no result.
   *
   * ```php
   * \bbn\x::dump($db->fetchObject("SELECT * FROM table_users WHERE name = 'john'"));
   * // stdClass Object {
   *                    "id"  =>  1,
   *                    "name"  =>  "John",
   *                    "surname"  =>  "Smith",
   *                    }
   * ```
   *
   * @param string $query
   * @return stdClass
   *
   */
  public function fetchObject($query){
    if ( $r = \call_user_func_array([$this, 'query'], \func_get_args()) ){
      return $r->fetchObject();
    }
    return false;
  }

  /**
   * Executes the given query with given vars, and extracts the first cell's result.
   *
   * ```php
   * \bbn\x::dump($db->get_one("SELECT name FROM table_users WHERE id>?", 138));
   * // (string) John
   * ```
   *
   * @param string query
   * @param int
   * @return string | int | false
   */
  public function get_one(){
    if ( $r = \call_user_func_array([$this, 'query'], \func_get_args()) ){
      return $r->fetchColumn(0);
    }
    return false;
  }

  /**
   * Execute the given query with given vars, and extract the first cell's result.
   * (similar to {@link get_one()})
   *
   * ```php
   * \bbn\x::dump($db->get_var("SELECT telephone FROM table_users WHERE id>?", 1));
   * // (int) 123554154
   * ```
   *
   * @param string query
   * @param int
   * @return string | int | false
   */
  public function get_var(){
    return \call_user_func_array([$this, "get_one"], \func_get_args());
  }


  /**
   * Return the first row resulting from the query as an array indexed with the fields' name.
   *
   * ```php
   * \bbn\x::dump($db->get_row("SELECT id, name FROM table_users WHERE id > ? ", 2));;
   *
   * /* (array)[
   *        "id" => 3,
   *        "name" => "thomas",
   *        ]
   * ```
   *
   * @param string query.
   * @param int The var ? value.
   * @return array | false
   *
   */
  public function get_row(){
    if ( $r = \call_user_func_array([$this, 'query'], \func_get_args()) ){
      return $r->get_row();
    }
    return false;
  }

  /**
   * Return an array that includes indexed arrays for every row resultant from the query.
   *
   * ```php
   * \bbn\x::dump($db->get_rows("SELECT id, name FROM table_users WHERE id > ? LIMIT ?", 2));
   * /* (array)[
   *            [
   *            "id" => 3,
   *            "name" => "john",
   *            ],
   *            [
   *            "id" => 4,
   *            "name" => "barbara",
   *            ],
   *          ]
   * ```
   *
   * @param string
   * @param int The var ? value
   * @return array | false
   */
  public function get_rows(){
    if ( $r = \call_user_func_array([$this, 'query'], \func_get_args()) ){
      return $r->get_rows();
    }
    return [];
  }

  /**
   * Return a row as a numeric indexed array.
   *
   * ```php
   * \bbn\x::dump($db->get_irow("SELECT id, name, surname FROM table_users WHERE id > ?", 2));
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
  public function get_irow(){
    if ( $r = \call_user_func_array([$this, 'query'], \func_get_args()) ){
      return $r->get_irow();
    }
    return false;
  }

  /**
   * Return an array of numeric indexed rows.
   *
   * ```php
   * \bbn\x::dump($db->get_irows("SELECT id, name FROM table_users WHERE id > ? LIMIT ?", 2, 2));
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
   * @param string query
   * @param int The var ? value
   * @return array
   */
  public function get_irows()
  {
    if ( $r = \call_user_func_array([$this, 'query'], \func_get_args()) ){
      return $r->get_irows();
    }
    return [];
  }

  /**
   * Return an array indexed on the first field of the request.
   * The value will be an array if the request has more than two fields.
   *
   * ```php
   * \bbn\x::dump($db->get_key_val("SELECT name,id_group FROM table_users"));
   * /*
   * (array)[
   *      "John" => 1,
   *      "Michael" => 1,
   *      "Barbara" => 1
   *        ]
   *
   * \bbn\x::dump($db->get_key_val("SELECT name, surname, id FROM table_users WHERE id > 2 "));
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
   * @return array | false
   */
  public function get_key_val(){
    if ( $r = \call_user_func_array([$this, 'query'], \func_get_args()) ){
      $rows = $r->get_rows();
      // At least 2 columns
      if ( (\count($rows) > 0) && (\count($rows[0]) > 1) ){
        $cols = array_keys($rows[0]);
        $idx = array_shift($cols);
        $num_cols = \count($cols);
        $res = [];
        foreach ( $rows as $d ){
          $index = $d[$idx];
          unset($d[$idx]);
          $res[$index] = $num_cols > 1 ? $d : $d[$cols[0]];
        }
        return $res;
      }
    }
    return [];
  }

  /**
   * Return an array indexed on the first field of the request.
   * The value will be an array if the request has more than two fields.
   * Return the same value as "get_key_val".
   *
   * ```php
   * \bbn\x::dump($db->select_all_by_keys("table_users", ["name","id","surname"], [["id", ">", "1"]], ["id" => "ASC"]);
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
   * @param string $table The table's name
   * @param string|array $fields The fields's name
   * @param array $where The "where" condition
   * @param array|boolean $order The "order" condition
   * @param int $start The $limit condition, default: 0
   * @return array|false
   */
  public function select_all_by_keys($table, $fields = [], $where = [], $order = false, $start = 0){
    if ( $sql = $this->get_select($table, $fields, $where, $order, $start) ){
      $where = $this->where_cfg($where, $table);
      if ( \count($where['values']) > 0 ){
        return $this->get_key_val($sql, $where['values']);
      }
      else {
        return $this->get_key_val($sql);
      }
    }
    return false;
  }

  /**
   * Return an array indexed on the searched field's in which there are all the values of the column.
   *
   * ```php
   * \bbn\x::dump($db->get_by_columns("SELECT name, surname FROM table_users WHERE id > 2"));
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
   * @return array
   */
  public function get_by_columns(){
    if ( $r = \call_user_func_array([$this, 'query'], \func_get_args()) ){
      return $r->get_by_columns();
    }
    return false;
  }

  /**
   * Return an array with the values of single field resulting from the query.
   *
   * ```php
   * \bbn\x::dump($db->get_col_array("SELECT id FROM table_users"));
   * /*
   * (array)[1, 2, 3, 4]
   * ```
   *
   * @param string
   * @return array | false
   */
  public function get_col_array(){
    if ( $r = \call_user_func_array([$this, 'get_by_columns'], \func_get_args()) ){
      return array_values(current($r));
    }
    return [];
  }

  /**
   * Return the first row resulting from the query as an object (similar to {@link get_object()}).
   *
   * ```php
   * \bbn\x::dump($db->get_obj("SELECT surname FROM table_users"));
   * /*
   * (obj){
   *       "name" => "Smith"
   *       }
   * ```
   *
   * @param string
   * @return object
   */
  public function get_obj(){
    return $this->get_object(\func_get_args());
  }

  /**
   * Return the first row resulting from the query as an object.
   * Synonym of get_obj.
   *
   * ```php
   * \bbn\x::dump($db->get_object("SELECT name FROM table_users"));
   * /*
   * (obj){
   *       "name" => "John"
   *       }
   * ```
   *
   * @param string
   * @return object | false
   */
  public function get_object(){
    if ( $r = \call_user_func_array([$this, 'query'], \func_get_args()) ){
      return $r->get_object();
    }
    return false;
  }

  /**
   * Return an array of stdClass objects.
   *
   * ```php
   * \bbn\x::dump($db->get_objects("SELECT name FROM table_users"));
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
   * @param string The query
   * @return array
   */
  public function get_objects()
  {
    if ( $r = \call_user_func_array([$this, 'query'], \func_get_args()) ){
      return $r->get_objects();
    }
    return [];
  }

  /**
   * Return the number of records in the table corresponding to the $where condition (non mandatory).
   *
   * ```php
   * \bbn\x::dump($db->count('table_users', ['name' => 'John']));
   * // (int) 2
   * ```
   *
   * @param string $table The table's name
   * @param array $where The "where" condition
   * @return int
   */
  public function count($table, array $where = []){
    $where_arr = $this->where_cfg($where, $table);
    $where = $this->get_where($where_arr, $table);
    if ($table = $this->tfn($table, 1) ){
      $sql = "SELECT COUNT(*) FROM ".$table.$where;
      if ( \count($where_arr['values']) > 0 ){
        return \call_user_func_array([$this, "get_one"], array_merge([$sql], $where_arr['values']));
      }
      else{
        return $this->get_one($sql);
      }
    }
    return false;
  }

  /**
   * Return the first row resulting from the query as an object.
   *
   * ```php
   * \bbn\x::dump($db->select('table_users', ['name', 'surname'], [['id','>','2']]));
   * /*
   * (object){
   *   "name": "John",
   *   "surname": "Smith",
   * }
   * ```
   *
   * @param string $table The table's name
   * @param string|array $fields The fields' name
   * @param array $where  The "where" condition
   * @param array | boolean $order The "order" condition, default: false
   * @param int $start The "start" condition, default: 0
   * @return object|boolean
   */
  public function select($table, $fields = [], $where = [], $order = false, $start = 0)
  {
    if ( $r = $this->_sel($table, $fields, $where, $order, 1, $start) ){
      return $r->get_object();
    }
    return false;
  }

  /**
   * Return a single value
   *
   * ```php
   * \bbn\x::dump($db->select_one("tab_users", "name", [["id", ">", 1]], ["id" => "DESC"], 2));
   *  (string) 'Michael'
   * ```
   *
   * @param string $table The table's name
   * @param string $field The field's name
   * @param array $where  The "where" condition
   * @param array | boolean $order The "order" condition, default: false
   * @param int $start The "start" condition, default: 0
   * @return string | int
   */
  public function select_one($table, $field, $where = [], $order = false, $start = 0){
    if ( $r = $this->_sel($table, [$field], $where, $order, 1, $start) ){
      if ( $res = $r->get_row() ){
        return $res[$field];
      }

    }
    return false;
  }

  /**
   * Return the first row resulting from the query as an indexed array.
   *
   * ```php
   * \bbn\x::dump($db->rselect("tab_users", ["id", "name", "surname"], ["id", ">", 1], ["id" => "ASC"], 2));
   * /*
   * (array) [
   *          "id" => 4,
   *          "name" => "John",
   *          "surname" => "Smith"
   *         ]
   * ```
   *
   * @param string $table The table's name
   * @param string | array $fields The fields' name
   * @param array $where  The "where" condition
   * @param array | boolean $order The "order" condition, default: false
   * @param int $start The "start" condition, default: 0
   * @return array
   */
  public function rselect($table, $fields = [], $where = [], $order = false, $start = 0){
    if ( $r = $this->_sel($table, $fields, $where, $order, 1, $start) ){
      return $r->get_row();
    }
    return false;
  }

  /**
   * Return the first row resulting from the query as a numeric array.
   *
   * ```php
   * \bbn\x::dump($db->iselect("tab_users", ["id", "name", "surname"], [["id", ">", 1]], ["id" => "ASC"], 2));
   * /*
   * (array)[
   *          4,
   *         "Jack",
   *          "Stewart"
   *        ]
   * ```
   *
   * @param string $table The table's name
   * @param string|array $fields The fields' name
   * @param array $where  The "where" condition
   * @param array | boolean $order The "order" condition, default: false
   * @param int $start The "start" condition, default: 0
   * @return array
   */

  public function iselect($table, $fields = [], $where = [], $order = false, $start = 0){
    if ( $r = $this->_sel($table, $fields, $where, $order, 1, $start) ){
      return $r->get_irow();
    }
    return false;
  }

  /**
   * Return table's rows resulting from the query as an array of objects.
   *
   * ```php
   * \bbn\x::dump($db->select_all("tab_users", ["id", "name", "surname"],[["id", ">", 1]], ["id" => "ASC"], 2));
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
   * @param string $table The table's name
   * @param string|array $fields The fields' name
   * @param array $where  The "where" condition
   * @param array | boolean $order The "order" condition, default: false
   * @param int $limit The "limit" condition, default: 0
   * @param int $start The "start" condition, default: 0
   * @return array
   */
  public function select_all($table, $fields = [], $where = [], $order = false, $limit = 0, $start = 0)
  {
    if ( $r = $this->_sel($table, $fields, $where, $order, $limit, $start) ){
      return $r->get_objects();
    }
    return [];
  }

  /**
   * Return table's rows as an array of indexed arrays.
   *
   * ```php
   * \bbn\x::dump($db->rselect_all("tab_users", ["id", "name", "surname"], [["id", ">", 1]], ["id" => "ASC"], 2));
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
   * @param string $table The table's name
   * @param string|array $fields The fields' name
   * @param array $where  The "where" condition
   * @param array | boolean $order condition, default: false
   * @param int $limit The "limit" condition, default: 0
   * @param int $start The "start" condition, default: 0
   * @return array
   */
  public function rselect_all($table, $fields = [], $where = [], $order = false, $limit = 0, $start = 0)
  {
    if ( $r = $this->_sel($table, $fields, $where, $order, $limit, $start) ){
      return $r->get_rows();
    }
    return [];
  }

  /**
   * Return the searched rows as an array of numeric arrays.
   *
   * ```php
   * \bbn\x::dump($db->iselect_all("tab_users", ["id", "name", "surname"], [["id", ">", 1]],["id" => "ASC"],2));
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
   * @param string $table The table's name
   * @param string|array $fields The fields's name
   * @param array $where  The "where" condition
   * @param array | boolean The "order" condition, default: false
   * @param int $limit The "limit" condition, default: 0
   * @param int $start The "start" condition, default: 0
   * @return array
   */
  public function iselect_all($table, $fields = [], $where = [], $order = false, $limit = 0, $start = 0){
    if ( $r = $this->_sel($table, $fields, $where, $order, $limit, $start) ){
      return $r->get_irows();
    }
    return [];
  }

  /**
   * Return the table's structure as an indexed array.
   *
   * ```php
   * \bbn\x::dump($db->modelize("table_users"));
   * /*
   * (array) [keys] => Array ( [PRIMARY] => Array ( [columns] => Array ( [0] => userid [1] => userdataid ) [ref_db] => [ref_table] => [ref_column] => [unique] => 1 )     [table_users_userId_userdataId_info] => Array ( [columns] => Array ( [0] => userid [1] => userdataid [2] => info ) [ref_db] => [ref_table] => [ref_column] =>     [unique] => 0 ) ) [cols] => Array ( [userid] => Array ( [0] => PRIMARY [1] => table_users_userId_userdataId_info ) [userdataid] => Array ( [0] => PRIMARY [1] => table_users_userId_userdataId_info ) [info] => Array ( [0] => table_users_userId_userdataId_info ) ) [fields] => Array ( [userid] => Array ( [position] => 1 [null] => 0 [key] => PRI [default] => [extra] => [signed] => 1 [maxlength] => 11 [type] => int ) [userdataid] => Array ( [position] => 2 [null] => 0 [key] => PRI [default] => [extra] => [signed] => 1 [maxlength] => 11 [type] => int ) [info] => Array ( [position] => 3 [null] => 1 [key] => [default] => NULL [extra] => [signed] => 0 [maxlength] => 200 [type] => varchar ) )
   * ```
   *
   * @param string $table The table's name
   * @return null|array
   */
  public function modelize($table = '', $force = false): ?array
  {
    $r = [];
    $tables = false;
    if ( empty($table) || $table === '*' ){
      $tables = $this->get_tables($this->current);
    }
    else if ( \is_string($table) ){
      $tables = [$table];
    }
    else if ( \is_array($table) ){
      $tables = $table;
    }
    if ( \is_array($tables) ){
      foreach ( $tables as $t ){
        $full = $this->tfn($t);
        $r[$full] = $this->_get_cache($full, 'columns', $force);
      }
      if ( \count($r) === 1 ){
        return end($r);
      }
      return $r;
    }
    return null;
  }

  public function fmodelize($table = '', $force = false){
    if ( $res = \call_user_func_array([$this, 'modelize'], \func_get_args()) ){
      foreach ( $res['fields'] as $n => $f ){
        $res['fields'][$n]['name'] = $n;
        $res['fields'][$n]['keys'] = [];
        if ( isset($res['cols'][$n]) ){
          foreach ( $res['cols'][$n] as $key ){
            $res['fields'][$n]['keys'][$key] = $res['keys'][$key];
          }
        }
      }
      return $res['fields'];
    }
    return false;
  }

  /**
   * Return databases' names as an array.
   *
   * ```php
   * \bbn\x::dump($db->get_databases());
   * /*
   * (array)[
   *      "db_customers",
   *      "db_clients",
   *      "db_empty",
   *      "db_example",
   *      "db_mail"
   *      ]
   * ```
   *
   * @return array | false
   */
  public function get_databases(){
    return $this->_get_cache('', 'databases');
  }

  /**
   * Return tables' names of a database as an array.
   *
   * ```php
   * \bbn\x::dump($db->get_tables('db_example'));
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
   * @return array | false
   */
  public function get_tables($database=''){
    if ( empty($database) ){
      $database = $this->current;
    }
    return $this->_get_cache($database, 'tables');
  }

  /**
   * Return colums' structure of a table as an array indexed with the fields names.
   *
   * ```php
   * \bbn\x::dump($db->get_columns('table_users'));
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
   * @return array | false
   */
  public function get_columns($table){
    if ( $tmp = $this->_get_cache($table) ){
      return $tmp['fields'];
    }
    return false;
  }

  /**
   * Return the table's keys as an array indexed with the fields names.
   *
   * ```php
   * \bbn\x::dump($db->get_keys("table_users"));
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
   * @return array | false
   */
  public function get_keys($table)
  {
    if ( $tmp = $this->_get_cache($table) ){
      return [
          "keys" => $tmp['keys'],
          "cols" => $tmp['cols']
      ];
    }
    return false;
  }

  /**
   * get_change
   * @todo Bugfix non funziona
   */
  public function get_change($db){
    return $this->language->get_change($db);
  }

  /**
   * Return primary keys of a table as a numeric array.
   *
   * ```php
   * \bbn\x::dump($db-> get_primary('table_users'));
   * // (array) ["id"]
   * ```
   *
   * @param string $table The table's name
   * @return array
   */
  public function get_primary($table){
    if ( ($keys = $this->get_keys($table)) && isset($keys['keys']['PRIMARY']) ){
      return $keys['keys']['PRIMARY']['columns'];
    }
    return [];
  }

  /**
   * Return the unique primary key of the given table.
   *
   * ```php
   * \bbn\x::dump($db->get_unique_primary('table_users'));
   * // (string) id
   * ```
   *
   * @param string $table The table's name
   * @return string
   */
  public function get_unique_primary($table){
    if ( ($keys = $this->get_keys($table)) &&
        isset($keys['keys']['PRIMARY']) &&
        (\count($keys['keys']['PRIMARY']['columns']) === 1) ){
      return $keys['keys']['PRIMARY']['columns'][0];
    }
    return false;
  }

  /**
   * Return the unique keys of a table as a numeric array.
   *
   * ```php
   * \bbn\x::dump($db->get_unique_keys('table_users'));
   * // (array) ["userid", "userdataid"]
   * ```
   *
   * @param string $table The table's name
   * @return array
   */
  public function get_unique_keys($table)
  {
    $fields = [];
    if ( $ks = $this->get_keys($table) ){
      foreach ( $ks['keys'] as $k ){
        if ( $k['unique'] === 1 ){
          $fields = array_merge($fields, $k['columns']);
        }
      }
    }
    return $fields;
  }

  /**
   * where_json
   *
   * @param $prop
   * @param $value
   * @return string
   * @todo chiedere a th
   *
   */
  public function where_json($prop, $value){
    $r = [$prop => $value];
    $json = json_encode($r);
    return '%'.substr($json, 1, -1).'%';
  }

  /**
   * Return a string with 'where' conditions.
   *
   * ```php
   * \bbn\x::dump($db->get_where(['id' => 9], 'table_users'));
   * // (string) WHERE 1 AND `table_users`.`id` = ?
   * ```
   *
   * @param array $where The "where" condition
   * @param string $table The table's name
   * @return string
   */
  public function get_where(array $where, $table='', $aliases = []){
    if ( !isset($where['final'], $where['keyval'], $where['values'], $where['fields']) ){
      $where = $this->where_cfg($where, $table, $aliases);
    }
    $st = '';
    if ( \count($where['final']) > 0 ){
      if ( !\is_array($table) ){
        $table = [$table];
      }
      if ( !empty($table) ){
        foreach ( $table as $tab ){
          $m = $this->modelize($table);
          if ( !$m || \count($m['fields']) === 0 ){
            /*
            * @todo  check the fields against the table's model and the aliases
            */
            return $st;
          }
        }
      }
      $cls = '\bbn\\db\\languages\\'.$this->engine;
      $operators = $cls::$operators;
      foreach ( $where['final'] as $w ){
        // 2 parameters, we use equal
        if ( \count($w) >= 3 && \in_array(strtolower($w[1]), $operators) ){
          // 4 parameters, it's a SQL function, no escaping no binding
          if ( isset($w[3]) ){
            $st .= 'AND '.$this->escape($w[0]).' '.$w[1].' '.$w[2].' ';
          }
          // 3 parameters, the operator is second item
          else{
            $st .= 'AND '.$this->escape($w[0]).' '.$w[1].' ? ';
          }
        }
        $st .= PHP_EOL;
      }
      if ( !empty($st) ){
        $st = ' WHERE 1 '.PHP_EOL.$st;
      }
    }
    return $st;
  }

  /**
   * Get a string starting with ORDER BY with corresponding parameters to $order.
   *
   * ```php
   * \bbn\x::dump($db->get_order(['name' => 'DESC' ],'table_users'));
   * // (string) ORDER BY `name` DESC
   * ```
   *
   * @param array $order The "order" condition
   * @param string $table The table's name
   * @return string
   */
  public function get_order($order, $table=''){
    return $this->language->get_order($order, $table='');
  }

  /**
   * Get a string starting with LIMIT with corresponding parameters to $limit.
   *
   * ```php
   * \bbn\x::dump($db->get_limit(3,1));
   * // (string) LIMIT 1, 3
   * ```
   *
   * @param int $limit The "limit" condition
   * @param int $start The "start" condition, default: 0
   * @return string
   */
  public function get_limit($limit, $start = 0){
    return $this->language->get_limit($limit, $start);
  }

  /**
   * Return SQL code for table creation.
   *
   * ```php
   * \bbn\x::dump($db->get_create("table_users"));
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
   * @param string $table The table's name
   * @return string | false
   */
  public function get_create($table){
    return $this->language->get_create($table);
  }

  /**
   * Return SQL code for row(s) DELETE.
   *
   * ```php
   * \bbn\x::dump($db->get_delete('table_users',['id'=>1]));
   * // (string) DELETE FROM `db_example`.`table_users` * WHERE 1 AND `table_users`.`id` = ?
   * ```
   *
   * @param string $table The table's name
   * @param array $where The "where" condition
   * @param bool $ignore If true inserts the "ignore" condition , default: false
   * @param bool $php default: false
   * @return string | false
   */
  public function get_delete($table, array $where, $ignore = false, $php = false){
    return $this->language->get_delete($table, $where, $ignore, $php);
  }

  /**
   * Return SQL code for row(s) SELECT.
   *
   * ```php
   * \bbn\x::dump($db->get_select('table_users',['name','surname'],[['id','>', 1]], ['id'=>'DESC']));
   * /*
   * (string)
   *   SELECT
   *    `table_users`.`name`,
   *    `table_users`.`surname`
   *   FROM `db_example`.`table_users`
   *   WHERE 1
   *   AND `table_users`.`id` > ?
   * ```
   *
   * @param string $table The table's name
   * @param array $fields The fields' name
   * @param array $where The "where" condition
   * @param string | array $order The "order" condition
   * @param int|boolean $limit The "limit" condition, default: false
   * @param int $start The "start" condition, default: 0
   * @param bool $php default: false
   * @return string
   */
  public function get_select($table, array $fields = [], array $where = [], $order = [], $limit = false, $start = 0, $php = false){
    return $this->language->get_select($table, $fields, $where, $order, $limit, $start, $php);
  }

  /**
   * Return SQL code for row(s) INSERT.
   *
   * ```php
   * \bbn\x::dump($db->get_insert('table_users',['name','surname']));
   * /*
   * (string)
   *  INSERT INTO `db_example`.`table_users` (
   *              `name`, `surname`)
   *              VALUES (?, ?)
   * ```
   *
   * @param string $table The table's name
   * @param array $fields The fields' name
   * @param bool $ignore If true inserts the "ignore" condition, default: false
   * @param bool $php default: false
   * @return string
   */
  public function get_insert($table, array $fields = [], $ignore = false, $php = false){
    return $this->language->get_insert($table, $fields, $ignore, $php);
  }

  /**
   * Return SQL code for row(s) UPDATE.
   *
   * ```php
   * \bbn\x::dump($db->get_update('table_users',['name','surname']));
   * /*
   * (string)
   *    UPDATE `db_example`.`table_users`
   *    SET `table_users`.`name` = ?,
   *        `table_users`.`surname` = ?
   * ```
   *
   * @param string $table The table's name
   * @param array $fields The fields' name
   * @param array $where The "where" condition
   * @param bool $php default: false
   * @return string
   */
  public function get_update($table, array $fields = [], array $where = [], $ignore = false, $php = false){
    return $this->language->get_update($table, $fields, $where, $ignore, $php);
  }

  /**
   * Return a numeric indexed array with the values of the unique column ($field) from the selected $table
   *
   * ```php
   * \bbn\x::dump($db->get_column_values('table_users','surname',['id','>',1]));
   * /*
   * array [
   *    "Smith",
   *    "Jones",
   *    "Williams",
   *    "Taylor"
   * ]
   * ```
   *
   * @param string $table The table's name
   * @param string $field The field's name
   * @param array $where The "where" condition
   * @param int | boolean $limit The "limit" condition, default: false
   * @param int $start The "start" condition, default: 0
   * @param bool $php default: false
   * @return array
   */
  public function get_column_values($table, $field,  array $where = [], array $order = [], $limit = false, $start = 0, $php = false){
    $r = [];
    $where = $this->where_cfg($where, $table);
    if ( $rows = $this->get_irows(
        $this->language->get_column_values($table, $field, $where, $order, $limit, $start, false),
        $where['values'])
    ){
      foreach ( $rows as $row ){
        array_push($r, $row[0]);
      }
    }
    return $r;
  }

  /**
   * Return a string with the sql query to count equal values in a field of the table.
   *
   * ```php
   * \bbn\x::dump($db->get_values_count('table_users','name',['surname','=','smith']));
   * /*
   * (string)
   *   SELECT COUNT(*) AS num, `name` AS val FROM `db_example`.`table_users`
   *     GROUP BY `name`
   *     ORDER BY `name`
   * ```
   *
   * @param string $table The table's name
   * @param string $field The field's name
   * @param array $where The "where" condition
   * @param int|boolean $limit The "limit" condition, dafault: false
   * @param int $start The "start" condition, default: 0
   * @param bool $php default: false
   * @return string
   */
  public function get_values_count($table, $field,  array $where = [], $limit = false, $start = 0, $php = false){
    return $this->language->get_values_count($table, $field, $where, $limit, $start, $php);
  }

  /**
   * Return the unique values of a column of a table as a numeric indexed array.
   *
   * ```php
   * \bbn\x::dump($db->get_field_values("table_users", "surname", [['id', '>', '2']], 1, 1));
   * // (array) ["Smiths", "White"]
   * ```
   *
   * @param string $table The table's name
   * @param string $field The field's name
   * @param array $where The "where" condition
   * @param int|boolean $limit The "limit" condition, default: false
   * @param int $start The "start" condition, default: 0
   * @return array | false
   */
  public function get_field_values($table, $field,  array $where = [], $limit = false, $start = 0){
    if ( $statement = $this->language->get_column_values($table, $field, $where, [], $limit, $start) ){
      $w = $this->where_cfg($where, $table);
      $args = \count($w['values']) ? array_merge([$statement], $w['values']) : [$statement];
      $r = \call_user_func_array([$this, 'get_by_columns'], $args);
      if ( \is_array($r) ){
        return $r[$field] ?? [];
      }
    }
  }

  /**
   * Return a count of identical values in a field as array, reporting a structure type 'num' - 'val'.
   *
   * ```php
   * \bbn\x::dump($db->count_field_values('table_users','surname',[['name','=','John']]));
   * // (array) ["num" => 2, "val" => "John"]
   * ```
   *
   * @param string $table The table's name
   * @param string $field The field's name
   * @param array $where The "where" condition
   * @param int|boolean $limit The "limit" condition, default: false
   * @param int $start The "start" condition, default: 0
   * @return array | false
   */
  public function count_field_values($table, $field,  array $where = [], $limit = false, $start = 0){
    if ( $r = $this->language->get_values_count($table, $field, $where, $limit, $start) ){
      $where = $this->where_cfg($where, $table);
      return $this->get_rows($r, $where['values']);
    }
  }

  /**
   * find_references
   *
   * @todo da errore
   *
   * @param $column
   * @param string $db
   * @return array|bool
   *
   */
  public function find_references($column, $db = ''){
    $changed = false;
    if ( $db && ($db !== $this->current) ){
      $changed = $this->current;
      $this->change($db);
    }
    $column = $this->cfn($column);
    $bits = explode(".", $column);
    if ( \count($bits) === 2 ){
      array_unshift($bits, $this->current);
    }
    if ( \count($bits) !== 3 ){

      return false;
    }
    $refs = [];
    $schema = $this->modelize();
    $test = function($key) use($bits){
      return ($key['ref_db'] === $bits[0]) && ($key['ref_table'] === $bits[1]) && ($key['ref_column'] === $bits[2]);
    };
    foreach ( $schema as $table => $cfg ){
      foreach ( $cfg['keys'] as $k ){
        if ( $test($k) ){
          array_push($refs, $table.'.'.$k['columns'][0]);
        }
      }
    }
    if ( $changed ){
      $this->change($changed);
    }
    return $refs;
  }

  /**
   * find_relations
   * @todo da errore
   *
   * @param $column
   * @param string $db
   * @return array|bool
   */
  public function find_relations($column, $db = ''){
    $changed = false;
    if ( $db && ($db !== $this->current) ){
      $changed = $this->current;
      $this->change($db);
    }
    $column = $this->cfn($column);
    $bits = explode(".", $column);
    if ( \count($bits) === 2 ){
      array_unshift($bits, $this->current);
    }
    if ( \count($bits) !== 3 ){

      return false;
    }
    $table = $bits[1];
    $refs = [];
    $schema = $this->modelize();
    $test = function($key) use($bits){
      return ($key['ref_db'] === $bits[0]) && ($key['ref_table'] === $bits[1]) && ($key['ref_column'] === $bits[2]);
    };
    foreach ( $schema as $tf => $cfg ){
      $t = $this->tsn($tf);
      if ( $t !== $table ){
        foreach ( $cfg['keys'] as $k ){
          if ( $test($k) ){
            foreach ( $cfg['keys'] as $k2 ){
              // Is not the same table
              if ( !$test($k2) &&
                  // Has a reference
                  !empty($k2['ref_column']) &&
                  // A unique reference
                  (\count($k2['columns']) === 1) &&
                  // To a table with a primary
                  isset($schema[$this->tfn($k2['ref_table'])]['cols'][$k2['ref_column']]) &&
                  // which is a sole column
                  (\count($schema[$this->tfn($k2['ref_table'])]['cols'][$k2['ref_column']]) === 1) &&
                  // We retrieve the key name
                  ($key_name = $schema[$this->tfn($k2['ref_table'])]['cols'][$k2['ref_column']][0]) &&
                  // which is unique
                  !empty($schema[$this->tfn($k2['ref_table'])]['keys'][$key_name]['unique']) &&
                  // and refers to a single column
                  (\count($k['columns']) === 1)
              ){
                if ( !isset($refs[$t]) ){
                  $refs[$t] = ['column' => $k['columns'][0], 'refs' => []];
                }
                $refs[$t]['refs'][$k2['columns'][0]] = $k2['ref_table'].'.'.$k2['ref_column'];
              }
            }
          }
        }
      }
    }
    if ( $changed ){
      $this->change($changed);
    }
    return $refs;
  }

  /**
   * Creates an index on a column of the table
   *
   * @todo return data
   *
   * ```php
   * \bbn\x::dump($db->create_db_index('table_users','id_group'));
   * // (void)
   * ```
   *
   * @param string $table The table's name
   * @param string $column The column's name
   * @param boolean $unique Default false
   * @return void
   */
  public function create_db_index($table, $column, $unique = false, $length = null){
    return $this->language->create_db_index($table, $column, $unique);
  }

  /**
   * Deletes index on a column of the table.
   *
   * @todo far vedere a thomas perchè non funziona/return data
   *
   * ```php
   * \bbn\x::dump($db->delete_db_index('table_users','id_group'));
   * // (void)
   * ```
   *
   * @param string $table The table's name.
   * @param string $column The column's name.
   * @return void
   */
  public function delete_db_index($table, $column){
    return $this->language->delete_db_index($table, $column);
  }

  /**
   * Creates an user for a specific db.
   * @todo return data
   *
   * ```php
   * \bbn\x::dump($db->create_db_user('Michael','22101980','db_example'));
   * // (void)
   * ```
   *
   * @param string $user. The username
   * @param string $pass. The password
   * @param string $db. The database's name
   * @return void
   */
  public function create_db_user($user, $pass, $db){
    return $this->language->create_db_user($user, $pass, $db);
  }

  /**
   * Deletes a db user.
   *
   * @todo non mi funziona ma forse per una questione di permessi/ return data
   *
   * ```php
   * \bbn\x::dump($db->delete_db_user('Michael'));
   * // (void)
   * ```
   *
   * @param string $user. The username to delete
   * @return void
   */
  public function delete_db_user($user){
    return $this->language->delete_db_user($user);
  }

  /**
   * Return an array including privileges of a specific db_user or all db_users.
   * @todo far vedere  a th la descrizione
   *
   * ```php
   * \bbn\x::dump($db->get_users('Michael'));
   * /* (array) [
   *      "GRANT USAGE ON *.* TO 'Michael'@''",
   *       GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER ON `db_example`.* TO 'Michael'@''"
   *    ]
   * ```
   *
   * @param string $user. The user's name, without params will return all privileges of all db_users
   * @return array
   */
  public function get_users($user='', $host=''){
    return $this->language->get_users($user, $host);
  }

  public function db_size(string $database = '', string $type = ''){
    return $this->language->db_size($database, $type);
  }

  public function table_size(string $table, string $type = ''){
    return $this->language->table_size($table, $type);
  }

  public function status(string $table = '', string $database = ''){
    return $this->language->status($table, $database);
  }
}
