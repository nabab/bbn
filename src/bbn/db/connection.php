<?php
/**
 * @package bbn\db
 */
namespace bbn\db;

use \bbn\str\text;
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

class connection extends \PDO implements actions, api, engines
{
  const E_CONTINUE = 'continue',
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
	 * @var \bbn\db\PHPSQLCreator
	 */
		$creator,
	/**
	 * The SQL engines supported by this class (needs the according language class)
	 * @var array
	 */
		$accepted_languages = ['mysql', 'sqlite'],
	/**
	 * Connection configuration
	 * @var array
	 */
		$cfg = false,
	/**
	 * APC Cache functions
	 * @var bool
	 */
		$has_apc = false,
	/**
	 * Unique string identifier for current connection
	 * @var bool
	 */
		$hash,
	/**
	 * @var mixed
	 */
		$cache = [],
	/**
	 * If set to false, query will return a regular PDOStatement
   * Use stop_fancy_stuff() to set it to false
   * And use start_fancy_stuff to set it back to true
	 * @var bool
	 */
    $fancy = 1,
	/**
	 * Error state of the current connection
	 * @var bool
	 */
    $has_error = false;
  
	protected
	/**
	 * @var \bbn\db\languages\mysql Can be other driver
	 */
		$language = false,
	/**
	 * @var integer
	 */
		$cache_renewal = 3600,
	/**
	 * @var mixed
	 */
		$max_queries = 100,
	/**
	 * @var mixed
	 */
		$last_insert_id,
	/**
	 * @var mixed
	 */
		$hash_contour = '__BBN__',
	/**
	 * @var mixed
	 */
		$last_prepared,
	/**
	 * @var array
	 */
		$queries = [],
	/**
	 * @var string
   * Possible values:
   *      stop: the script will go on but no further database query will be executed
   *      die: the script will die with the error
   *      continue: the script and further queries will be executed
	 */
		$on_error = self::E_STOP,
	/**
	 * @var bool
	 */
		$triggers_disabled = false;
	
	public
	/**
   * The quote character for table and column names
	 * @var string
	 */
		$qte,
	/**
	 * @var string
	 */
		$last_query,
	/**
	 * The ODBC engine of this connection
	 * @var string
	 */
		$engine,
	/**
	 * The host of this connection
	 * @var string
	 */
		$host,
	/**
	 * The currently selected database
	 * @var mixed
	 */
		$current,
	/**
	 * An array of functions for launching triggers on actions
	 * @var mixed
	 */
		$triggers = [
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
   * The information that will be accessed by \bbn\db\query as the current statement's options
	 * @var array
	 */
    $last_params = ['sequences' => false, 'values' => false];
	
  private static 
	/**
	 * Error state of the current connection
	 * @var bool
	 */
    $has_error_all = false;
	/**
	 * @var int
	 */

	protected static
	/**
	 * @var string
	 */
		$line='---------------------------------------------------------------------------------';
  
  private static function has_error()
  {
    self::$has_error_all = true;
  }
  
  private function _cache_name($item, $mode){
    switch ( $mode ){
      case 'columns':
        if ( $this->has_apc ){
          return "bbn/db/".$this->engine."/".$this->host."/".$item;
        }
        break;
      case 'tables':
        if ( $this->has_apc ){
          return "bbn/db/".$this->engine."/".$this->host."/".$item;
        }
        break;
      case 'databases':
        if ( $this->has_apc ){
          return "bbn/db/".$this->engine."/".$this->host;
        }
        break;
    }
    return false;
  }
  
  /**
	 * Returns the table's structure's array, either from the cache or from _modelize()
	 *
	 * @param string $table The table from which the structure is seeked
	 * @return array | false
	 */
  private function _get_cache($item, $mode='columns'){
    if ( !isset($this->cache[$item]) ){
      if ( $this->has_apc && ($cache_name = $this->_cache_name($item, $mode)) ){
        if ( apc_exists($cache_name) ){
          $tmp = apc_fetch($cache_name);
          if ( !$this->cache_renewal || ($tmp['time'] > (time() - $this->cache_renewal)) ){
            $this->cache[$item] = $tmp['data'];
          }
          else{
            apc_delete($cache_name);
          }
        }
      }
      if ( !isset($this->cache[$item]) ){
        switch ( $mode ){
          case 'columns':
            $keys = $this->language->get_keys($item);
            $cols = $this->language->get_columns($item);
            if ( is_array($keys) && is_array($cols) ){
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
        if ( !isset($tmp) ){
          die("Erreur avec la table $item ou le mode $mode");
        }
        if ( $tmp ){
          $this->cache[$item] = $tmp;
          if ( $this->has_apc ){
            apc_store($cache_name, [
              'data' => $this->cache[$item],
              'time' => time()
            ]);
          }
        }
      }
    }
    return isset($this->cache[$item]) ? $this->cache[$item] : false;
  }
	
  /**
   * 
   * @param string $type insert, insert_update, update, delete
   * @param string $table the table name
   * @param array | string $columns or Where string for delete case
   * @param string | bool $arg4 Where string or ignore
   * @return string A SQL statement or false
   */
  private function _statement($type, $table, array $keypairs=[], $arg4=[])
  {
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
          $sql = $this->language->get_update($table, $keypairs, $arg4['final']);
        }
        break;
      case 'delete':
        $hash = $this->make_hash('delete', $table, serialize($keypairs['unique']), $arg4);
        if ( isset($this->queries[$hash]) ){
          $sql = $this->queries[$this->queries[$hash]]['statement'];
        }
        else{
          $sql = $this->language->get_delete($table, $keypairs['final']);
        }
        break;
    }
    return isset($sql, $hash) ? ['sql' => $sql, 'hash' => $hash] : false;
  }
  
	/**
	 * @returns a selection query
	 */
  private function _sel($table, $fields = [], $where = [], $order = false, $limit = 100, $start = 0)
	{
    // Automatically select non deleted if history is enabled
    if ( class_exists('\\bbn\\appui\\history', false) && \bbn\appui\history::has_history($this) ){
      $hcol = \bbn\appui\history::$hcol;
      if ( !isset($where[$hcol]) ){
        $cols = array_keys($this->get_columns($table));
        if ( in_array($hcol, $cols) ){
          $where[$hcol] = 1;
        }
      }
    }
    $where = $this->where_cfg($where);
		$hash = $this->make_hash('select', $table, serialize($fields), serialize($this->get_where($where, $table)), serialize($order), $limit, $start);
		if ( isset($this->queries[$hash]) ){
			$sql = $this->queries[$this->queries[$hash]]['statement'];
		}
		else{
			$sql = $this->language->get_select($table, $fields, $where['final'], $order, $limit, $start);
		}
    $values = array_values($fields);
		if ( $sql && (
                $this->triggers_disabled ||
                $this->_trigger(
                        $table,
                        'select',
                        'before',
                        $values,
                        $where['keypair']) ) ){
      if ( count($where['values']) > 0 ){
        $r = $this->query($sql, $hash, $where['values']);
      }
      else{
        $r = $this->query($sql, $hash);
      }
      if ( $r ){
        $this->_trigger($table, 'select', 'after', $fields, $where['keypair']);
      }
      return $r;
    }
  }
  
  /**
	 * @todo Thomas fais ton taf!!
	 *
	 * @param
	 * @param
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
    if ( is_string($e) ){
      array_push($msg,'Error message: '.$e);
    }
		else if ( method_exists($e, "getMessage") ){
			array_push($msg,'Error message: '.$e->getMessage());
		}
		array_push($msg, self::$line);
		array_push($msg, $this->last());
		array_push($msg, self::$line);
    if ( $this->last_params['values'] ){
      array_push($msg, self::$line);
      array_push($msg, 'Parameters');
      array_push($msg, self::$line);
      array_push($msg, \bbn\tools::get_dump($this->last_params['values']));
      array_push($msg, self::$line);
    }
    $this->log(implode(PHP_EOL, $msg));
    if ( $this->on_error === self::E_DIE ){
      die(implode('<br>', $msg));
    }
	}

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
	private function _trigger($table, $kind, $moment, $values, $where=[])
	{
		$trig = 1;
		if ( !empty($this->triggers[$kind][$moment]) ){
      $table = $this->table_full_name($table);

      // Specific to a table
      if ( isset($this->triggers[$kind][$moment][$table]) ){

        foreach ( $this->triggers[$kind][$moment][$table] as $i => $f ){
          if ( is_callable($f) ){
            $tmp = call_user_func_array($f, [$table, $kind, $moment, $values, $where]);
            if ( !$tmp ){
              $trig = false;
            }
            if ( $trig && is_array($tmp) ){
              $trig = $tmp;
            }
          }
        }
      }
		}
		return $trig;
	}
	
  /**
   * Checks if the database is in a state ready to query
   * 
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
	 * @todo Thomas fais ton taf!!
	 *
	 * @param $cfg Mandatory: engine, db Optional: cache_length Other: see engine class
	 * @return void 
	 */
	public function __construct($cfg=[])
	{
    if ( !isset($cfg['engine']) && defined('BBN_DB_ENGINE') ){
      $cfg['engine'] = BBN_DB_ENGINE;
    }
    if ( isset($cfg['engine']) ){
      $cls = '\\bbn\\db\\languages\\'.$cfg['engine'];
      if ( !class_exists($cls) ){
        die("Sorry the engine class $cfg[engine] does not exist");
      }
      $this->language = new $cls($this);
      if ( isset($cfg['on_error']) ){
        $this->on_error = $cfg['on_error'];
      }
      if ( $cfg = $this->language->get_connection($cfg) ){
        $this->qte = $this->language->qte;
        try{
          call_user_func_array('parent::__construct', $cfg['args']);
          $this->current = $cfg['db'];
          $this->engine = $cfg['engine'];
          $this->host = isset($cfg['host']) ? $cfg['host'] : '127.0.0.1';
          $this->hash = $this->make_hash($cfg['args']);
          $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
          if ( function_exists("apc_add") ){
            $this->has_apc = 1;
          }
          if ( isset($cfg['cache_length']) ){
            $this->cache_renewal = (int)$cfg['cache_length'];
          }
          $this->start_fancy_stuff();
          // SQLite has not keys enabled by default
          $this->enable_keys();
        }
        catch ( \PDOException $e ){
          \bbn\tools::log("Impossible to create the connection for ".$cfg['engine']."/".$cfg['db'], 'db');
          die();
        }
      }
		}
	}
  
  public function log($st){
    $args = func_get_args();
    foreach ( $args as $a ){
      \bbn\tools::log($a, 'db');
    }
  }

  /**
   * Changes the error mode.
   * 
   * <code>
   * $this->db->get_error_mode();
   * </code>
   * 
   * @param string $mode The error mode: "continue", "die".
	 */
  public function set_error_mode($mode){
    $this->on_error = $mode;
  }
  
  /**
   * Gets the error mode.
   * 
   * <code>
   * $this->db->get_error_mode();
   * </code>
   * 
   * @return string
	 */
  public function get_error_mode(){
    return $this->on_error;
  }

  /**
	* Delete a specific item from the cache
	* 
	* @return \bbn\db\connection
	*/
	public function clear_cache($item, $mode)
	{
    if ( $this->has_apc ){
      $cache_name = $this->_cache_name($item, $mode);
      if ( apc_exists($cache_name) ){
        apc_delete($cache_name);
      }
    }
    return $this;
	}
  
  /**
	* @todo clear_all_cache() with $this->language->get_databases etc...
	* 
	* @return \bbn\db\connection
	*/
	public function clear_all_cache()
	{
    apc_clear_cache();
    apc_clear_cache("user");
    return $this;
	}

	/**
	 * @todo Thomas fais ton taf!!
	 *
	 * @return void
	 */
	public function stop_fancy_stuff()
	{
    $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, ['\PDOStatement']);
		$this->fancy = false;
	}

  	/**
	 * @todo Thomas fais ton taf!!
	 *
	 * @return void
	 */
	public function start_fancy_stuff()
	{
    $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, ['\bbn\db\query',[$this]]);
		$this->fancy = 1;
	}

  /**
	 * @todo Thomas fais ton taf!!
	 *
	 * @return void
	 */
	public function clear()
	{
		$this->queries = [];
	}
  
	/**
	 * Escape names with the appropriate quotes (db, tables, columns, keys...)
   * 
   * <code>
   * $this->db->escape("table_users");
   * </code>
	 * 
	 * @param string $table The table's name (escaped or not).
   * 
	 * @return string
	 */
	public function escape($item)
	{
		return $this->language->escape($item);
	}
  
	/**
	 * Returns a value string ready to be put inside quotes, with quotes and percent escaped.
	 * 
   * <code>
   * $this->db->escape_value("L'infanzia di \"maria\"");
   * </code>
   * 
	 * @param string $value The string to escape.
   * 
	 * @return string | false
	 */
	public function escape_value($value, $esc = "'")
	{
    if ( is_string($value) ){
      return str_replace('%', '\\%', $esc === '"' ? 
              \bbn\str\text::escape_dquotes($value) :
              \bbn\str\text::escape_squotes($value));
    }
		return $value;
	}
  
	/**
	 * Changes the value of last_insert_id (used by history)
	 * 
	 * @param int $id The last ID inserted
	 * @return \bbn\db\connection
	 */
	public function set_last_insert_id($id)
	{
		$this->last_insert_id = $id;
    return $this;
	}
  
  /**
	 * Returns a table's full name.
   * i.e. "database.table".
   * 
   * <code>
   * $this->db->table_full_name("table_users"); //Returns work_db.table_users
   * </code>
	 * 
	 * @param string $table The table's name (escaped or not).
	 * @param bool $escaped If set to true the returned string will be escaped.
   * 
	 * @return string | false
	 */
	public function table_full_name($table, $escaped=false)
	{
		return $this->language->table_full_name($table, $escaped);
	}
	
	/**
	 * Returns a table's simple name.
   * i.e. "table".
   * 
   * <code>
   * $this->db->table_simple_name("work_db.table_users"); //Returns table_users
   * </code>
	 * 
	 * @param string $table The table's name (escaped or not).
	 * @param bool $escaped If set to true the returned string will be escaped.
   * 
	 * @return string | false
	 */
  public function table_simple_name($table, $escaped=false)
  {
    return $this->language->table_simple_name($table, $escaped);
  }
  
	/**
	 * Returns a column's full name.
   * i.e. "table.column".
   * 
   * <code>
   * $this->db->col_full_name("name", "table_users"); //Returns table_users.name
   * </code>
	 * 
	 * @param string $col The column's name (escaped or not).
	 * @param string $table The table's name (escaped or not).
	 * @param bool $escaped If set to true the returned string will be escaped.
   * 
	 * @return string | false
	 */
  public function col_full_name($col, $table='', $escaped=false)
  {
    return $this->language->col_full_name($col, $table, $escaped);
  }

	/**
	 * Returns a column's simple name.
   * i.e. "column"
   * 
   * <code>
   * $this->db->col_simple_name("table_users.name"); //Returns name
   * </code>
	 * 
	 * @param string $col The column's name (escaped or not).
	 * @param bool $escaped If set to true the returned string will be escaped.
   * 
	 * @return string | false
	 */
  public function col_simple_name($col, $escaped=false)
  {
    return $this->language->col_simple_name($col, $escaped);
  }
  
  /**
	* @todo Thomas fais ton taf!!
	* @return
	*/
	protected function make_hash()
	{
		$args = func_get_args();
    if ( (count($args) === 1) && is_array($args[0]) ){
      $args = $args[0];
    }
		$st = '';
		foreach ( $args as $a ){
			$st .= serialize($a);
		}
		return $this->hash_contour.md5($st).$this->hash_contour;
	}
  
  public function get_hash()
  {
    return $this->hash;
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
	public function set_trigger($function, $kind='', $moment='', $tables='*' )
	{
    if ( is_callable($function) ){
      $kinds = ['select', 'insert', 'update', 'delete'];
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
                  $t = $this->table_full_name($table);
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
   * Returns boolean value of auto_increment field.
   * Working only on mysql.
   * 
   * <code>
   * $this->db->has_id_increment('table_users');
   * </code>
   * 
   * @param string $table The table name.
   * 
   * @return bool
   * 
   * @todo: working only on mysql
   */
  public function has_id_increment($table){
    if ( $model = $this->modelize($table) ){
      if ( isset($model['keys']['PRIMARY']) && 
              (count($model['keys']['PRIMARY']['columns']) === 1) &&
              ($model['fields'][$model['keys']['PRIMARY']['columns'][0]]['extra'] === 'auto_increment') ){
        return 1;
      }
    }
    return false;
  }
	
 /**
   * Returns a SQL query based on a configuration (??)
   * @todo Check the configuration format
	 *
   * @param array $cfg Description
	 * @return string 
	 */
	public function create_query($cfg)
	{
		if ( !isset($this->creator) ){
			$this->creator = new \PHPSQLParser\PHPSQLCreator();
		}
		return $this->creator->create($cfg);
	}
	
	/**
	 * Parses a SQL query and returns an array
	 *
	 * @param string $cfg
	 * @return array 
	 */
	public function parse_query($cfg)
	{
		if ( !isset($this->parser) ){
			$this->parser = new \PHPSQLParser\PHPSQLParser();
		}
		return $this->parser->parse($cfg);
	}
	
	/**
	 * Returns the last statement used by a query for this connection.
   * 
   * <code>
   * $this->db->last();
   * </code>
   * 
	 * @return string 
	 */
	public function last()
	{
		return $this->last_query;
	}

	/**
	 * Returns the last inserted ID
   * 
   * <code>
   * $this->db->last_id();
   * </code>
   * 
	 * @return int
	 */
	public function last_id()
	{
		if ( $this->last_insert_id ){
			return $this->last_insert_id;
		}
		return false;
	}
	/**
	 * Adds the specs of a query to the $queries object
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
		if ( count($this->queries[$hash]) > $this->max_queries ){
			array_shift($this->queries);
		}
	}
	
	/**
	 * Use the given database
	 * 
	 * @param string $db
	 * @return \bbn\db\connection
	 */
	public function change($db)
	{
    if ( $this->language->change($db) ){
      $this->current = $db;
    }
		return $this;
	}
	
	/**
   * Disable foreign keys constraints
   * 
	 * @return \bbn\db\connection
	 */
	public function disable_keys()
	{
    $this->language->disable_keys();
		return $this;
	}

	/**
   * Enable foreign keys constraints
   * 
	 * @return \bbn\db\connection
	 */
	public function enable_keys()
	{
    $this->language->enable_keys();
		return $this;
	}
	
	/**
	 * Returns a count of identical values in a field as array, reporting a structure type 'num' - 'column_name'.
   * 
   * <code>
   * $this->db->stat(
   *  'table_users',
   *  'surname',
   *  ['id', '>', '100'],
   *  ['surname' => 'ASC'],
   *  20, 2);
   * </code>
   * 
   * @param string $table The table name.
   * @param string $column The field name.
   * @param array $where The "where" condition.
   * @param array $order The "order" condition.
   * @param int $limit The "limit" condition.
   * @param int $start The "start" condition.
   * 
	 * @return array
	 */
	public function stat($table, $column, $where = [], $order = [], $limit = 0, $start = 0)
	{
    if ( $this->check() ){
      $where = $this->where_cfg($where);
      $sql = 'SELECT COUNT(*) AS '.$this->qte.'num'.$this->qte.', '.
              $this->col_simple_name($column, 1).PHP_EOL.
              'FROM '.$this->table_full_name($table, 1).PHP_EOL.
              'GROUP BY '.$this->col_simple_name($column, 1).PHP_EOL.
              $this->get_where($where).PHP_EOL.
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
   * 
	 * @return \PDO::query
	 */
	public function raw_query()
	{
    if ( $this->check() ){
      $args = func_get_args();
      return call_user_func_array('parent::query', $args);
    }
  }
  
	/**
	 * Executes a writing statement and returns the number of affected rows or returns a query object for the reading statement
   * 
	 * @return int|\bbn\db\query
	 */
	public function query()
	{
    if ( $this->check() ){
      $args = func_get_args();
      if ( !$this->fancy ){
        return call_user_func_array('parent::query', $args);
      }
      if ( count($args) === 1 && is_array($args[0]) ){
        $args = $args[0];
      }
      
      if ( is_string($args[0]) ){
        
        // The first argument is the statement
        $statement = trim(array_shift($args));
        $hash = $this->make_hash($statement);
        
        // Sending a hash as second argument from statement generating functions will bind it to the statement
        if ( isset($args[0]) && is_string($args[0]) &&
        ( strlen($args[0]) === ( 32 + 2*strlen($this->hash_contour) ) ) &&
        ( strpos($args[0], $this->hash_contour) === 0 ) &&
        ( substr($args[0],-strlen($this->hash_contour)) === $this->hash_contour ) ){
          $hash_sent = array_shift($args);
        }

        // Case where drivers are arguments
        if ( isset($args[0]) && is_array($args[0]) && !array_key_exists(0,$args[0]) ){
          $driver_options = array_shift($args);
        }

        // Case where values are argument
        else if ( isset($args[0]) &&
                is_array($args[0]) &&
                (count($args) === 1) ){
          $args = $args[0];
        }
        if ( !isset($driver_options) ){
          $driver_options = [];
        }
        $this->last_params['values'] = [];
        $num_values = 0;
        foreach ( $args as $i => $arg ){
          if ( !is_array($arg) ){
            array_push($this->last_params['values'], $arg);
            $num_values++;
          }
          else if ( isset($arg[2]) ){
            array_push($this->last_params['values'], $arg[2]);
            $num_values++;
          }
        }
        if ( !isset($this->queries[$hash]) ){
          if ( $sequences = $this->parse_query($statement) ){
            if ( $num_values > 0 ){
              $statement = str_replace("%%",'%',$statement);
              /* Compatibility with sprintf basic expressions - to be enhanced */
              if ( preg_match_all('/(%[s|u|d])/',$statement) ){
                $statement = str_replace("'%s'",'?',$statement);
                $statement = str_replace("%s",'?',$statement);
                $statement = str_replace("%d",'?',$statement);
                $statement = str_replace("%u",'?',$statement);
              }
            }
            /* Or looking for question marks */
            preg_match_all('/(\?)/',$statement,$exp);
            $this->add_query(
                    $hash,
                    $statement,
                    $sequences,
                    isset($exp[1]) && is_array($exp[1]) ? count($exp[1]) : 0,
                    $driver_options);
            if ( isset($hash_sent) ){
              $this->queries[$hash_sent] = $hash;
            }
          }
          else if ( $this->engine === 'sqlite' && strpos($statement, 'PRAGMA') === 0 ){
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
            die("Impossible to parse the query $statement");
          }
        }
        else if ( is_string($this->queries[$hash]) ){
          $hash = $this->queries[$hash];
        }
        /* If the number of values is inferior to the number of placeholders we fill the values with the last given value */
        if ( $num_values < $this->queries[$hash]['placeholders'] ){
          $this->last_params['values'] = array_merge($this->last_params['values'], array_fill($num_values, $this->queries[$hash]['placeholders'] - $num_values, end($this->last_params['values'])));
          $num_values = count($this->last_params['values']);
        }
        /* The number of values must match the number of placeholders to bind */
        if ( $num_values !== $this->queries[$hash]['placeholders'] ){
          $this->error('Incorrect arguments count (your values: '.$num_values.', in the statement: '.$this->queries[$hash]['placeholders']."\n\n".$statement."\n\n".'start of values'.print_r($this->last_params['values'], 1).'Arguments:'.print_r(func_get_args(),1));
          exit;
        }
        $q =& $this->queries[$hash];
        $this->last_params['sequences'] = $q['sequences'];
        $this->queries[$hash]['num']++;
        if ( $q['exe_time'] === 0 ){
          $t = microtime(1);
        }
        $this->last_query = $q['statement'];
        if ( isset($q['sequences']['DROP']) || isset($q['sequences']['CREATE']) || isset($q['sequences']['ALTER']) ){
          // A voir
          //$this->clear_cache();
        }
        try{
          if ( $q['prepared'] && ( isset($q['sequences']['INSERT']) || isset($q['sequences']['UPDATE']) || isset($q['sequences']['DELETE']) || isset($q['sequences']['DROP']) || isset($q['sequences']['ALTER']) || isset($q['sequences']['CREATE']) ) ){
            $r = $q['prepared']->init($this->last_params['values'])->execute();
          }
          else{
            if ( isset($q['sequences']['INSERT']) || isset($q['sequences']['UPDATE']) || isset($q['sequences']['DELETE']) || isset($q['sequences']['DROP']) || isset($q['sequences']['ALTER']) || isset($q['sequences']['CREATE']) ){
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
            $this->last_insert_id = (int)$this->lastInsertId();
          }
          if ( $q['prepared'] && ( isset($q['sequences']['INSERT']) || isset($q['sequences']['UPDATE']) || isset($q['sequences']['DELETE']) || isset($q['sequences']['DROP']) ) ){
            return $q['prepared']->rowCount();
          }
          return $r;
        }
      }
    }
		return false;
	}

	/**
	 * Returns a single value from a request based on arguments.
	 * 
   * <code>
   * $this->db->get_val("table_users", "surname", "name", "Julien");
   * </code>
   * 
	 * @param string $table The table name.
	 * @param string $field_to_get The name of the field to get.
	 * @param string|array $field_to_check the name of the field to check.
	 * @param string $value The value to check.
   * 
	 * @return string | false
	 */
	public function get_val($table, $field_to_get, $field_to_check='', $value='')
	{
    if ( is_array($field_to_check) ){
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
	 * Returns a single value from a request based on arguments.
   * 
   * <code>
   * $this->db->val_by_id("table_users", "surname", "138");
   * </code>
	 *
	 * @param string $table The table name.
	 * @param string $field The field name.
	 * @param string $id The "id" value.
   * @param string $col The id colomn name, dafault: 'id'.
   * 
	 * @return string | false
	 */
	public function val_by_id($table, $field, $id, $col='id')
	{
    return $this->select_one($table, $field, [$col => $id]);
	}

	/**
	 * Returns an integer candidate for being a new ID in the given table.
   * 
   * <code>
   * $this->db->new_id("table_users");
   * </code>
	 *
	 * @param string $table The table name.
	 * @param string $id_field The id field name, default: 'id'.
	 * @param int $min The minimum value for new id.
	 * @param int $max The maximum value for new id.
   * 
	 * @return int | false
	 */
	public function new_id($table, $id_field='id', $min = 11111, $max = 499998999)
	{
		if ( ( $max > $min ) && text::check_name($id_field) && $table = $this->table_full_name($table,1) ){
			$id = mt_rand($min, $max);
			while ( $this->select($table, [$id_field], [$id_field => $id]) ){
				$id = mt_rand($min, $max);
			}
			return $id;
		}
		return false;
	}

	/**
	 * Transposition of the original fetch method, but with the query included. It returns an arra or false if no result
	 *
	 * @param string $query
	 * @return array|false
	 */
	public function fetch($query)
	{
    if ( $r = call_user_func_array([$this, 'query'], func_get_args()) ){
      return $r->fetch();
		}
    return false;
	}

	/**
	 * Transposition of the original fetchAll method, but with the query included. It returns an arra or false if no result
	 *
	 * @param string $query
	 * @return array | false
	 */
	public function fetchAll($query)
	{
    if ( $r = call_user_func_array([$this, 'query'], func_get_args()) ){
      return $r->fetchAll();
		}
    return false;
	}

	/**
	 * Transposition of the original fetchColumn method, but with the query included. It returns an arra or false if no result
	 *
	 * @param $query
	 * @return string | false
	 */
	public function fetchColumn($query, $num=0)
	{
    if ( !is_int($num) ){
      $num = 0;
    }
    if ( $r = call_user_func_array([$this, 'query'], func_get_args()) ){
      return $r->fetchColumn($num);
		}
    return false;
	}

	/**
	 * Transposition of the original fetchColumn method, but with the query included. It returns an arra or false if no result
	 *
	 * @param $query
	 * @return stdClass 
	 */
	public function fetchObject($query)
	{
    if ( $r = call_user_func_array([$this, 'query'], func_get_args()) ){
      return $r->fetchObject();
		}
    return false;
	}

	/**
	 * Execute the given query with given vars, and extract the first cell's result.
   * 
   * <code>
   * $this->db->get_one("SELECT name, surname FROM table_users WHERE id=?", 138);
   * </code>
	 *
	 * @return string | false
	 */
	public function get_one()
	{
    if ( $r = call_user_func_array([$this, 'query'], func_get_args()) ){
      return $r->fetchColumn(0);
		}
    return false;
	}

	/**
	 * Synonym of get_one (historical).
   * 
   * <code>
   * $this->db->get_var("SELECT name, surname FROM table_users WHERE id=?", 138);
   * </code>
	 *
	 * @return string | false
	 */
	public function get_var()
	{
    return call_user_func_array([$this, "get_one"], func_get_args());
  }

	/**
	 * Returns first row as an array indexed with the fields names.
	 * Same arguments as query.
   * Returns same value as "select".
   * 
   * <code>
   * $this->db->get_row("SELECT id, name FROM table_users WHERE id > ?", 30);
   * </code>
	 *
	 * @return array | false
	 */
	public function get_row()
	{
    if ( $r = call_user_func_array([$this, 'query'], func_get_args()) ){
      return $r->get_row();
		}
    return false;
	}

	/**
	 * Returns an array of rows as arrays indexed with the fields names.
	 * Same arguments as query.
   * Returns same value as "select_all".
   * 
   * <code>
   * $this->db->get_rows("SELECT id, name FROM table_users WHERE id > ? AND name LIKE ? ORDER BY id ASC LIMIT ?", 15, "b%", 4);
   * </code>
   * 
	 * @return array | false
	 */
	public function get_rows()
	{
    if ( $r = call_user_func_array([$this, 'query'], func_get_args()) ){
      return $r->get_rows();
		}
    return [];
	}

	/**
	 * Returns a row as a numeric indexed array.
   * Same arguments as query.
   * Returns same value as "iselect".
   * 
   * <code>
   * $this->db->get_irow("SELECT id, name, surname FROM table_users WHERE id > ?", 30);
   * </code>
	 *
	 * @return array | false
	 */
	public function get_irow()
	{
    if ( $r = call_user_func_array([$this, 'query'], func_get_args()) ){
      return $r->get_irow();
		}
    return false;
	}

	/**
	 * Returns an array of numeric indexed rows.
   * Same arguments as query.
   * 
   * <code>
   * $this->db->get_rows("SELECT id, name FROM table_users WHERE id > ? AND name LIKE ? ORDER BY id ASC LIMIT ?", 15, "b%", 4);
   * </code>
	 *
	 * @return array
	 */
	public function get_irows()
	{
    if ( $r = call_user_func_array([$this, 'query'], func_get_args()) ){
      return $r->get_irows();
		}
    return [];
	}
  
	/**
	 * Returns an array with the first field as index and either the second field as value f there is only 2 fields or with an array of the different fields as value if there are more.
   * Same arguments as query.
   * Returns same value as "select_all_by_keys".
   * 
   * <code>
   * $this->db->get_rows("SELECT id, name FROM table_users WHERE id > ?", 45);
   * </code>
	 *
	 * @return array | false
	 */
  public function get_key_val()
  {
    if ( $r = call_user_func_array([$this, 'query'], func_get_args()) ){
      $rows = $r->get_rows();
      // At least 2 columns
      if ( (count($rows) > 0) && (count($rows[0]) > 1) ){
        $cols = array_keys($rows[0]);
        $idx = array_shift($cols);
        $num_cols = count($cols);
        $res = [];
        foreach ( $rows as $d ){
          $index = $d[$idx];
          unset($d[$idx]);
          $res[$index] = $num_cols > 1 ? $d : $d[$cols[0]];
        }
        return $res;
      }
		}
    return false;
  }
  
	/**
	 * Returns an array with the first field as index and either the second field as value f there is only 2 fields or with an array of the different fields as value if there are more.
   * Same arguments as "select_all".
   * Returns same value as "get_key_val".
	 *
   * <code>
   * $this->db->select_all_by_keys(
   *  "tab_users",
   *  ["id", "name", "surname"],
   *  [
   *    ["id", "<", 138],
   *    ["name", "LIKE", "c%"] 
   *  ],
   *  ["id" => "ASC"],
   *  2);
   * </code>
   * 
   * @param string $table The table name.
   * @param string|array $fields The fields name.
   * @param array $where  The "where" condition.
   * @param string|array $order The "order" condition, default: false.
   * @param int $start The "start" condition, default: 0.
   * 
	 * @return array|false
	 */
  public function select_all_by_keys($table, $fields = [], $where = [], $order = false, $start = 0)
  {
    if ( $sql = $this->get_select($table, $fields, $where, $order, $start) ){
      $where = $this->where_cfg($where);
      $params = (count($where['values']) > 0) ? [$sql, $where['values']] : [$sql];
      return call_user_func_array([$this, 'get_key_val'], $params);
		}
    return false;
  }

	/**
	 * Returns an array indexed on the columns in which are all the values.
   * 
   * <code>
   * $this->db->get_by_columns("SELECT * FROM table_users WHERE name LIKE ?", 'b%');
   * </code>
	 *
	 * @return array
	 */
	public function get_by_columns()
	{
    if ( $r = call_user_func_array([$this, 'query'], func_get_args()) ){
      return $r->get_by_columns();
		}
    return false;
	}

  /**
	 * Returns the result of a query as a single numeric array for one single column values.
   * 
   * <code>
   * $this->db->get_col_array("SELECT name, surname FROM table_users WHERE name LIKE ?", 'b%');
   * </code>
	 *
	 * @return array | false 
	 */
	public function get_col_array()
	{
    if ( $r = call_user_func_array([$this, 'get_by_columns'], func_get_args()) ){
      return array_values(current($r));
		}
    return [];
	}

	/**
	 * @todo Thomas fais ton taf!!
	 *
	 * @return void 
	 */
	public function get_obj()
	{
		return $this->get_object(func_get_args());
	}

	/**
	 * Returns first row as an array of object.
   * Synonym of get_obj.
   * 
   * <code>
   * $this->db->get_row("SELECT id, name FROM table_users WHERE id > ?", 30);
   * </code>
	 *
	 * @return array | false
	 */
	public function get_object()
	{
    if ( $r = call_user_func_array([$this, 'query'], func_get_args()) ){
      return $r->get_object();
		}
    return false;
	}

	/**
	 * Returns an array of rows as arrays of objects.
   * 
   * <code>
   * $this->db->get_rows("SELECT id, name FROM table_users WHERE id > ? AND name LIKE ? ORDER BY id ASC LIMIT ?", 15, "b%", 4);
   * </code>
   * 
	 * @return array 
	 */
	public function get_objects()
	{
    if ( $r = call_user_func_array([$this, 'query'], func_get_args()) ){
      return $r->get_objects();
		}
    return [];
	}
  
  /**
	 * @todo Thomas fais ton taf!!
   * @param type $where
   * @return type
   */
  public function where_cfg($where)
  {
    $r = [
        'fields' => [],
        'values' => [],
        'final' => [],
        'keypair' => [],
        'unique' => []
    ];
    if ( is_array($where) && count($where) > 0 ){
      $i = 0;
      foreach ( $where as $k => $w ){
        // arrays with [ field_name, operator, value]
        if ( is_numeric($k) && is_array($w) && count($w) >= 3 ){
          array_push($r['fields'], $w[0]);
          array_push($r['values'], $w[2]);
          $r['keypair'][$w[0]] = $w[2];
          array_push($r['final'], [$w[0], $w[1], $w[2]]);
        }
        // arrays with [ field_name => value, field_name => value...] (equal assumed)
        else if ( is_string($k) ){
          array_push($r['fields'], $k);
          array_push($r['values'], $w);
          $r['keypair'][$k] = $w;
          array_push($r['final'], [$k, '=', $w]);
        }
        if ( isset($r['final'][$i]) ){
          array_push($r['unique'], [$r['final'][$i][0], $r['final'][$i][1]]);
        }
        else{
          $this->log("Incorrect where", $where);
        }
        $i++;
      }
    }
    return $r;
  }
  
  /**
   * Returns the number of records in the table corresponding to the $where parameter (non mandatory).
   * 
   * <code>
   * $this->db->count(
   *  'table_users',
   *  ['name' => 'Julien']
   * );
   * </code>
   * 
   * @param string $table The table name.
   * @param array $where The "where" condition.
   * 
   * @return int
   */
  public function count($table, array $where = []){
    $where_arr = $this->where_cfg($where);
    $where = $this->get_where($where_arr, $table);
    if ($table = $this->table_full_name($table, 1) ){
			$sql = "SELECT COUNT(*) FROM ".$table.$where;
		  if ( count($where_arr['values']) > 0 ){
        return call_user_func_array([$this, "get_one"], array_merge([$sql], $where_arr['values']));
      }
      else{
        return $this->get_one($sql);
      }
    }
    return false;
  }

  /**
   * Returns first row as an object.
   * 
   * <code>
   * $this->db->select(
   *  "tab_users",
   *  ["id", "name", "surname"],
   *  [
   *    ["id", "<", 138],
   *    ["name", "LIKE", "c%"] 
   *  ],
   *  ["id" => "ASC"],
   *  2);
   * </code>
   * 
   * @param string $table The table name.
   * @param string|array $fields The fields name.
   * @param array $where  The "where" condition.
   * @param string|array $order The "order" condition, default: false.
   * @param int $start The "start" condition, default: 0.
   * 
   * @return object|false
   */
	public function select($table, $fields = [], $where = [], $order = false, $start = 0)
	{
    if ( $r = $this->_sel($table, $fields, $where, $order, 1, $start) ){
      return $r->get_object();
		}
    return false;
	}
	
  /**
   * Returns a single value.
   * Returns same value as "get_one".
   * 
   * <code>
   * $this->db->select_one(
   *  "tab_users",
   *  ["id", "name", "surname"],
   *  [
   *    ["id", "<", 138],
   *    ["name", "LIKE", "c%"] 
   *  ],
   *  ["id" => "ASC"],
   *  2);
   * </code>
   * 
   * @param string $table The table name.
   * @param string|array $field The fields name.
   * @param array $where  The "where" condition.
   * @param string|array $order The "order" condition, default: false.
   * @param int $start The "start" condition, default: 0.
   * 
   * @return array
   */
	public function select_one($table, $field, $where = [], $order = false, $start = 0)
	{
    if ( $r = $this->_sel($table, [$field], $where, $order, 1, $start) ){
      if ( $res = $r->get_row() ){
        return $res[$field];
      }
      
		}
    return false;
	}

  /**
   * Returns a row as an indexed array.
   * 
   * <code>
   * $this->db->rselect(
   *  "tab_users",
   *  ["id", "name", "surname"],
   *  [
   *    ["id", "<", 138],
   *    ["name", "LIKE", "c%"] 
   *  ],
   *  ["id" => "ASC"],
   *  2);
   * </code>
   * 
   * @param string $table The table name.
   * @param string|array $fields The fields name.
   * @param array $where  The "where" condition.
   * @param string|array $order The "order" condition, default: false.
   * @param int $start The "start" condition, default: 0.
   * 
   * @return array
   */
	public function rselect($table, $fields = [], $where = [], $order = false, $start = 0)
	{
    if ( $r = $this->_sel($table, $fields, $where, $order, 1, $start) ){
      return $r->get_row();
		}
    return false;
	}
	
  /**
   * Returns first row as a numeric array.
   * 
   * <code>
   * $this->db->iselect(
   *  "tab_users",
   *  ["id", "name", "surname"],
   *  [
   *    ["id", "<", 138],
   *    ["name", "LIKE", "c%"] 
   *  ],
   *  ["id" => "ASC"],
   *  2);
   * </code>
   * 
   * @param string $table The table name.
   * @param string|array $fields The fields name.
   * @param array $where  The "where" condition.
   * @param string|array $order The "order" condition, default: false.
   * @param int $start The "start" condition, default: 0.
   * 
   * @return array
   */
	public function iselect($table, $fields = [], $where = [], $order = false, $start = 0)
	{
    if ( $r = $this->_sel($table, $fields, $where, $order, 1, $start) ){
      return $r->get_irow();
		}
    return false;
	}
	
  /**
   * Returns table's rows as an array of objects.
   * 

   * <code>
   * $this->db->select_all(
   *  "tab_users",
   *  ["id", "name", "surname"],
   *  [
   *    ["id", "<", 138],
   *    ["name", "LIKE", "c%"] 
   *  ],
   *  ["id" => "ASC"],
   *  15, 2);
   * </code>
   * 
   * @param string $table The table name.
   * @param string|array $fields The fields name.
   * @param array $where  The "where" condition.
   * @param string|array $order The "order" condition, default: false.
   * @param int $limit The "limit" condition, default: 0.
   * @param int $start The "start" condition, default: 0.
   * 
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
   * Returns table's rows as an array of indexed arrays.
   * 

   * <code>
   * $this->db->rselect_all(
   *  "tab_users",
   *  ["id", "name", "surname"],
   *  [
   *    ["id", "<", 138],
   *    ["name", "LIKE", "c%"] 
   *  ],
   *  ["id" => "ASC"],
   *  15, 2);
   * </code>
   * 
   * @param string $table The table name.
   * @param string|array $fields The fields name.
   * @param array $where  The "where" condition.
   * @param string|array $order The "order" condition, default: false.
   * @param int $limit The "limit" condition, default: 0.
   * @param int $start The "start" condition, default: 0.
   * 
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
   * Returns table's rows as an array of numeric arrays.
   * 
   * <code>
   * $this->db->iselect_all(
   *  "tab_users",
   *  ["id", "name", "surname"],
   *  [
   *    ["id", "<", 138],
   *    ["name", "LIKE", "c%"] 
   *  ],
   *  ["id" => "ASC"],
   *  15, 2);
   * </code>
   * 
   * @param string $table The table name.
   * @param string|array $fields The fields name.
   * @param array $where  The "where" condition.
   * @param string|array $order The "order" condition, default: false.
   * @param int $limit The "limit" condition, default: 0.
   * @param int $start The "start" condition, default: 0.
   * 
   * @return array
   */
	public function iselect_all($table, $fields = [], $where = [], $order = false, $limit = 0, $start = 0)
	{
    if ( $r = $this->_sel($table, $fields, $where, $order, $limit, $start) ){
      return $r->get_irows();
    }
    return [];
	}
	
  /**
   * Inserts row(s) in a table.
   * 
   * <code>
   * $this->db->insert(
   *  "table_users",
   *  [
   *    ["name" => "Ted"],
   *    ["surname" => "McLow"]
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
		$r = false;
    $trig = 1;
    $keys = array_keys($values);
    if ( isset($keys[0]) && ($keys[0] === 0) ){
      $keys = array_keys($values[0]);
    }
    else{
      $values = [$values];
    }
    $affected = 0;
    if ( $sql = $this->_statement('insert', $table, $keys, $ignore) ){
      foreach ( $values as $i => $vals ){
        if ( $this->triggers_disabled ){
          if ( $r = $this->query($sql['sql'], $sql['hash'], array_values($vals)) ){
            $affected += $r;
          }
        }
        else{
          // in case the trigger is called, values might be changed by the callback.
          // In this case, they will be sent back in the result array as 'values'
          // Then  the SQL statement needs to be rebuilt
          $trig = $this->_trigger($table, 'insert', 'before', $vals);
          if ( !is_array($trig) ){
            $trig = ['trig' => $trig];
          }
          if ( $trig['trig'] ){
            if ( isset($trig['values']) ){
              $vals = $trig['values'];
              if ( !($sql = $this->_statement('insert', $table, array_keys($vals), $ignore)) ){
                die($this->log(
                        "Problem with the values returned by the callback function(s)",
                        $table,
                        $vals));
              }
            }
            if ( $r = $this->query($sql['sql'], $sql['hash'], array_values($vals)) ){
              $affected += $r;
              $this->_trigger($table, 'insert', 'after', $vals);
            }
            if ( isset($trig['value']) ){
              if ( \bbn\str\text::is_integer($trig['value']) ){
                $affected += $trig['value'];
              }
              else{
                $affected = $trig['value'];
              }
            }
          }
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
	public function insert_update($table, array $values)
	{
		$r = false;
    // Twice the arguments
		if ( $sql = $this->_statement('insert_update', $table, array_keys($values)) ){
      if ( $this->triggers_disabled ){
        $vals = array_merge(array_values($values),array_values($values));
        $last = $this->last_id();
        $r = $this->query($sql['sql'], $sql['hash'], $vals);
        if ( $r ){
          if ( $last !== $this->last_id() ){
            $this->_trigger($table, 'insert', 'after', $values);
          }
          else{
            /** @todo There is a flaw here: $values is only partly the where, it should be only the primary and/or the unique keys */
            $keys = $this->get_keys($table);
            $upd = [];
            foreach ( $keys as $k ){
              if ( isset($values[$k]) ){
                $upd[$k] = $values[$k];
              }
            }
            $this->_trigger($table, 'update', 'after', $values, $upd);
          }
        }
      }
      else{
        $trig = $this->_trigger($table, 'insert', 'before', $values);
        if ( !is_array($trig) ){
          $trig = ['trig' => $trig];
        }
        if ( $trig['trig'] ){
          if ( isset($trig['values']) ){
            $values = $trig['values'];
            if ( !($sql = $this->_statement('insert_update', $table, array_keys($values))) ){
              die($this->log(
                      "Problem with the values returned by the callback function(s)",
                      $table, $values)
              );
            }
          }
          $vals = array_merge(array_values($values),array_values($values));
          $last = $this->last_id();
          $r = $this->query($sql['sql'], $sql['hash'], $vals);
          if ( $r ){
            if ( $last !== $this->last_id() ){
              $this->_trigger($table, 'insert', 'after', $values);
            }
            else{
              $this->_trigger($table, 'update', 'after', $values, $values);
            }
          }
        }
        if ( isset($trig['value']) ){
          $r = $trig['value'];
        }
      }
		}
		return $r;
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
   * 
   * @return int The number of rows updated.
   */
	public function update($table, array $values, array $where)
	{
		$r = false;
    $trig = 1;
    $where = $this->where_cfg($where);
    if ( $sql = $this->_statement('update', $table, array_keys($values), $where) ){
  		if ( $this->triggers_disabled ){
        $r = $this->query($sql['sql'], $sql['hash'], array_merge(array_values($values), $where['values']));
      }
      else if ( $trig = $this->_trigger($table, 'update', 'before', $values, $where['keypair']) ){
        if ( !is_array($trig) ){
          $trig = ['trig' => $trig];
        }
        if ( $trig['trig'] ){
          if ( isset($trig['values']) ){
            $values = $trig['values'];
            if ( !($sql = $this->_statement('update', $table, array_keys($values), $where)) ){
              die($this->log(
                      "Problem with the values returned by the callback function(s)",
                      $table, $values)
              );
            }
          }
          $r = $this->query($sql['sql'], $sql['hash'], array_merge(array_values($values), $where['values']));
          if ( $r ){
            $this->_trigger($table, 'update', 'after', $values, $where['keypair']);
          }
        }
        if ( isset($trig['value']) ){
          $r = $trig['value'];
        }
      }
		}
		return $r;
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
    $where = $this->where_cfg($where);
    if ( $sql = $this->_statement('delete', $table, $where, $ignore) ){
      if ( $this->triggers_disabled ){
        $r = $this->query($sql['sql'], $sql['hash'], $where['values']);
      }
      else if ( $trig = $this->_trigger($table, 'delete', 'before', [], $where['keypair']) ){
        if ( !is_array($trig) ){
          $trig = ['trig' => $trig];
        }
        if ( $trig['trig'] ){
          $r = $this->query($sql['sql'], $sql['hash'], $where['values']);
          if ( $r ){
            $this->_trigger($table, 'delete', 'after', [], $where['keypair']);
          }
        }
      }
      if ( isset($trig['value']) ){
        $r = $trig['value'];
      }
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

	/**
   * Returns table's structure as indexed array.
   * 
   * <code>
   * $this->db->modelize("table_users");
   * </code>
   * 
	 * @param string $table The table name.
   * 
	 * @return array | false
	 */
	public function modelize($table='')
	{
		$r = [];
		$tables = false;
		if ( empty($table) || $table === '*' ){
			$tables = $this->get_tables($this->current);
		}
		else if ( is_string($table) ){
			$tables = array($table);
		}
		else if ( is_array($table) ){
			$tables = $table;
		}
		if ( is_array($tables) ){
			foreach ( $tables as $t ){
        $full = $this->table_full_name($t);
				$r[$full] = $this->_get_cache($full);
			}
			if ( count($r) === 1 ){
				return end($r);
			}
			return $r;
		}
		return false;
	}
	
	/**
   * Returns databases names as an array.
   * 
   * <code>
   * $this->db->get_databases();
   * </code>
   * 
	 * @return array | false
	 */
	public function get_databases()
	{
    return $this->_get_cache('', 'databases');
	}

	/**
   * Returns tables names of a database as an array.
   * 
   * <code>
   * $this->db->get_tables("database");
   * </code>
   * 
   * @param string $database The database name.
   * 
	 * @return array | false
	 */
	public function get_tables($database='')
	{
    if ( empty($database) ){
      $database = $this->current;
    }
    return $this->_get_cache($database, 'tables');
	}
  
	/**
   * Returns colums's structure of a table as an array indexed with the fields names.
   * 
   * <code>
   * $this->db->get_columns("table_users");
   * </code>
   * 
   * @param string $table The table name.
   * 
	 * @return array | false
	 */
	public function get_columns($table)
	{
    if ( $tmp = $this->_get_cache($table) ){
      return $tmp['fields'];
    }
    return false;
	}
	
	/**
   * Returns keys of a table as an array indexed with the fields names.
   * 
   * <code>
   * $this->db->get_keys("table_users");
   * </code>
   * 
   * @param string $table The table name.
   * 
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
	 * @todo Bugfix
	 */
	public function get_change($db)
	{
    return $this->language->get_change($db);
	}
	
	/**
   * Returns primary keys of a table as a numeric array.
   * 
   * <code>
   * $this->db->get_primary("table_users");
   * </code>
   * 
   * @param string $table The table name.
   * 
	 * @return array
	 */
	public function get_primary($table)
	{
    if ( ($keys = $this->get_keys($table)) && isset($keys['keys']['PRIMARY']) ){
      return $keys['keys']['PRIMARY']['columns'];
    }
    return [];
	}
	
	/**
   * Returns unique primary keys of a table.
   * 
   * <code>
   * $this->db->get_unique_primary("table_users");
   * </code>
   * 
   * @param string $table The table name.
   * 
	 * @return array | false
	 */
	public function get_unique_primary($table)
	{
    if ( ($keys = $this->get_keys($table)) &&
            isset($keys['keys']['PRIMARY']) &&
            (count($keys['keys']['PRIMARY']['columns']) === 1) ){
      return $keys['keys']['PRIMARY']['columns'][0];
    }
    return false;
	}
  
  /**
   * Returns unique keys of a table as a numeric array.
   * 
   * <code>
   * $this->db->get_unique_keys("table_users");
   * </code>
   * 
   * @param string $table The table name.
   * 
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
	* Get a string starting with WHERE with corresponding parameters to $where.
 * 
 * <code>
 * $this->db->get_where(['id' => '35'], "table_users");
 * </code>
 * 
 * @param array $where The "where" condition.
 * @param string $table The table name.
	*
	* @return string
	*/
  public function get_where(array $where, $table='', $aliases = [])
  {
    if ( !isset($where['final'], $where['keypair'], $where['values'], $where['fields']) ){
      $where = $this->where_cfg($where);
    }
    $st = '';
    
		if ( count($where['final']) > 0 ){
      if ( !empty($table) ){
        $m = $this->modelize($table);
        if ( !$m || count($m['fields']) === 0 ){
          /*
           * @todo  check the fields against the table's model and the aliases
           */
          return $st;
        }
      }
      $operators = eval('return \\bbn\\db\\languages\\'.$this->engine.'::$operators;');
      foreach ( $where['final'] as $w ){
        // 2 parameters, we use equal
        if ( count($w) >= 3 && in_array(strtolower($w[1]), $operators) ){
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
        $st = ' WHERE 1'.PHP_EOL.$st;
      }
    }
    
    return $st;
  }
	
	/**
   * Get a string starting with ORDER BY with corresponding parameters to $order.
   * 
   * <code>
   * $this->db->get_order(['name'], "table_users");
   * </code>
   * 
   * @param array $order The "order" condition.
   * @param string $table The table name.
   * 
	 * @return string
	 */
	public function get_order($order, $table='')
	{
    return $this->language->get_order($order, $table='');
	}
	
	/**
   * Get a string starting with LIMIT with corresponding parameters to $limit.
   * 
   * <code>
   * $this->db->get_limit('5', '2');
   * </code>
   * 
   * @param int $limit The "limit" condition.
   * @param int $start The "start" condition, default: 0.
   * 
	 * @return string
	 */
	public function get_limit($limit, $start = 0)
	{
    return $this->language->get_limit($limit, $start);
	}
	
	/**
   * Returns SQL code for table creation.
   * 
   * <code>
   * $this->db->get_create("table_users");
   * </code>
   * 
   * @param string $table The table name.
   * 
	 * @return string | false
	 */
	public function get_create($table)
	{
    return $this->language->get_create($table);
	}
	
	/**
   * Returns SQL code for row(s) deletion.
   * 
   * <code>
   * $this->db->get_delete("table_users");
   * </code>
   * 
   * @param string $table The table name.
   * @param array $where The "where" condition.
   * @param bool $ignore If true inserts the "ignore" condition , default: false.
   * @param bool $php default: false.
   * 
	 * @return string | false
	 */
	public function get_delete($table, array $where, $ignore = false, $php = false)
	{
    return $this->language->get_delete($table, $where, $ignore, $php);
	}

  /**
   * Returns SQL code for row(s) selection.
   * 
   * <code>
   * $this->db->get_select("table_users", ['id', 'name', 'surname'], ['id', '>', '10'], 'name', 5, 2);
   * </code>
   * 
   * @param string $table The table name.
   * @param array $fields The fields name.
   * @param array $where The "where" condition.
   * @param string | array $order The "order" condition.
   * @param int $limit The "limit" condition, default: false.
   * @param int $start The "start" condition, default: 0.
   * @param bool $php default: false.
   * 
   * @return string
   */
	public function get_select($table, array $fields = [], array $where = [], $order = [], $limit = false, $start = 0, $php = false)
	{
    return $this->language->get_select($table, $fields, $where, $order, $limit, $start, $php);
	}
	
	/**
   * Returns SQL code for row(s) insertion.
   * 
   * <code>
   * $this->db->get_insert("table_users", ['name', 'surname']);
   * </code>
   * 
   * @param string $table The table name.
   * @param array $fields The fields name.
   * @param bool $ignore If true inserts the "ignore" condition, default: false.
   * @param bool $php default: false.
   * 
	 * @return string
	 */
	public function get_insert($table, array $fields = [], $ignore = false, $php = false)
	{
    return $this->language->get_insert($table, $fields, $ignore, $php);
	}
	
	/**
   * Returns SQL code for row(s) update.
   * 
   * <code>
   * $this->db->get_update("table_users", ['gender'], ['gender', '=', 'male']);
   * </code>
   * 
   * @param string $table The table name.
   * @param array $fields The fields name.
   * @param array $where The "where" condition.
   * @param bool $php default: false.
   * 
	 * @return string
	 */
	public function get_update($table, array $fields = [], array $where = [], $php = false)
	{
    return $this->language->get_update($table, $fields, $where, $php);
	}
	
	/**
   * Returns a single numeric-indexed array with the values of the unique column $field from the $table $table
   * 
   * <code>
   * $this->db->get_column_values("table_users", "surname");
   * </code>
   * 
   * @param string $table The table name.
   * @param string $field The field name.
   * @param array $where The "where" condition.
   * @param int $limit The "limit" condition, default: false.
   * @param int $start The "start" condition, default: 0.
   * @param bool $php dafault: false.
   * 
	 * @return array
	 */
	public function get_column_values($table, $field,  array $where = [], $limit = false, $start = 0, $php = false)
	{
    $r = [];
    $where = $this->where_cfg($where);
    if ( $rows = $this->get_irows($this->language->get_column_values($table, $field, $where, $limit, $start, false), $where['values']) ){
      foreach ( $rows as $row ){
        array_push($r, $row[0]);
      }
    }
    return $r;
	}
	
	/**
   * Returns a request sql string for count similar values in a field of a table.
   * 
   * <code>
   * $this->db->get_values_count("table_users", "surname", ['id', '>', '10'], 50, 2);
   * </code>
   * 
   * 
   * @param string $table The table name.
   * @param string $field The field name.
   * @param array $where The "where" condition.
   * @param int $limit The "limit" condition, dafault: false.
   * @param int $start The "start" condition, dafault: 0.
   * @param bool $php default: false.
   * 
	 * @return string
	 */
	public function get_values_count($table, $field,  array $where = [], $limit = false, $start = 0, $php = false)
	{
    return $this->language->get_values_count($table, $field, $where, $limit, $start, $php);
	}
  
  /**
   * Returns the unique values of a column of a table as a numeric indexed array.
   * 
   * <code>
   * $this->db->get_field_values("table_users", "surname", ['id', '>', '10'], 50, 2);
   * </code>
   * 
   * @param string $table The table name.
   * @param string $field The field name.
   * @param array $where The "where" condition.
   * @param int $limit The "limit" condition, default: false.
   * @param int $start The "start" condition, default: 0.
   * 
   * @return array | false
   */
  public function get_field_values($table, $field,  array $where = [], $limit = false, $start = 0)
  {
    if ( $r = $this->language->get_column_values($table, $field, $where, $limit, $start) ){
      if ( $d = $this->get_by_columns($r) ){
        return $d[$field];
      }
    }
  }
	
  /**
   * Returns a count of identical values in a field as array, reporting a structure type 'num' - 'val'.
   * 
   * <code>
   * $this->db->count_field_values("table_users", "surname", ['id', '>', '10'], 50, 2);
   * </code>
   * 
   * @param string $table The table name.
   * @param string $field The field name.
   * @param array $where The "where" condition.
   * @param int $limit The "limit" condition, default: false.
   * @param int $start The "start" condition, default: 0.
   * 
   * @return array | false
   */
	public function count_field_values($table, $field,  array $where = [], $limit = false, $start = 0)
	{
    if ( $r = $this->language->get_values_count($table, $field, $where, $limit, $start) ){
      $where = $this->where_cfg($where);
      return $this->get_rows($r, $where['values']);
    }
	}

  /**
	 * @return void 
	 */
	public function create_db_index($table, $column, $unique = false, $length = null)
	{
    return $this->language->create_db_index($table, $column, $unique);
	}
	
	/**
	 * @return void 
	 */
	public function delete_db_index($table, $column)
	{
    return $this->language->delete_db_index($table, $column);
	}
	
	/**
	 * @return void 
	 */
	public function create_db_user($user, $pass, $db)
	{
    return $this->language->create_db_user($user, $pass, $db);
	}
	
	/**
	 * @return void 
	 */
	public function delete_db_user($user)
	{
    return $this->language->delete_db_user($user);
	}

 	/**
	 * @return void 
	 */
	public function get_users($user='', $host='')
	{
    return $this->language->get_users($user, $host);
	}

}
?>