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
	private
	/**
	 * A PHPSQLParser object
	 * @var \bbn\db\PHPSQLParser
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
	 * @var mixed
	 */
		$cache = [],
	/**
	 * If set to false, query will return a regular PDOStatement
   * Use stop_fancy_stuff() to set it to false
   * And use start_fancy_stuff to set it back to true
	 * @var bool
	 */
    $fancy = 1;
  
	protected
	/**
	 * @var mixed
	 */
		$language = false,
	/**
	 * @var integer
	 */
		$cache_renewal = 600,
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
		$queries = array(),
	/**
	 * @var string
   * Possible values:
   *      stop: the script will go on but no further database query will be executed
   *      die: the script will die with the error
   *      continue: the script and further queries will be executed
	 */
		$on_error = 'stop',
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
	
	protected static
	/**
	 * @var string
	 */
		$line='---------------------------------------------------------------------------------',
	/**
	 * @var int
	 */
		$errorState = 1;

  private static function hasErrorState()
  {
    self::$errorState = 0;
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
	 * @return array|false
	 */
  private function _get_cache($item, $mode='columns'){
    if ( !isset($this->cache[$item]) ){
      if ( $this->has_apc && ($cache_name = $this->_cache_name($item, $mode)) ){
        if ( apc_exists($cache_name) ){
          $tmp = apc_fetch($cache_name);
          if ( $tmp['time'] > (time() - $this->cache_renewal) ){
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
	 * @todo Thomas fais ton taf!!
	 *
	 * @param
	 * @param
	 * @return void 
	 */
	public function error($e)
	{
    self::hasErrorState();
		$msg = [
      self::$line,
      date('H:i:s d-m-Y').' - Error in the page!',
      self::$line
    ];
		$b = debug_backtrace();
		foreach ( $b as $c ){
			if ( isset($c['file']) ){
				array_push($msg,'File '.$c['file'].' - Line '.$c['line']);
				array_push($msg,
					( isset($c['class']) ?  'Class '.$c['class'].' - ' : '' ).
					( isset($c['function']) ?  'Function '.$c['function'] : '' )/*.
					( isset($c['args']) ? 'Arguments: '.substr(print_r($c['args'],1),0,100) : '' )*/
				);
			}
		}
		array_push($msg,self::$line);
    if ( is_string($e) ){
      array_push($msg,'Error message: '.$e);
    }
		if ( method_exists($e, "getMessage") ){
			array_push($msg,'Error message: '.$e->getMessage());
		}
		array_push($msg, self::$line);
		array_push($msg, $this->last());
		array_push($msg, self::$line);
		array_push($msg, print_r($this->last_params['values'], 1));
		array_push($msg, self::$line);
    $this->log($msg);
    if ( $this->on_error === 'die' ){
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
	private function _trigger($table, $kind, $moment, $values, $where = [])
	{
		$trig = 1;
		if ( !empty($this->triggers[$kind][$moment]) ){
      $table = $this->table_full_name($table);

      // Specific to a table
      if ( isset($this->triggers[$kind][$moment][$table]) ){

        foreach ( $this->triggers[$kind][$moment][$table] as $i => $f ){
          if ( is_callable($f) ){
            if ( !call_user_func_array($f, [$table, $kind, $moment, $values, $where]) ){
              $trig = false;
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
    if ( isset($this->current) && ( (self::$errorState === 1) || ($this->on_error === 'continue') ) ){
      return 1;
    }
    return false;
  }
	/**
	 * @todo Thomas fais ton taf!!
	 *
	 * @param $cfg
	 * @return void 
	 */
	public function __construct($cfg=array())
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
          $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
          if ( function_exists("apc_add") ){
            $this->has_apc = 1;
          }
          $this->start_fancy_stuff();
          // SQLite has not keys enabled by default
          $this->enable_keys();
        }
        catch ( \PDOException $e ){
          $this->log($e);
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
   * Changes the error mode
   * Modes: stop (default), die, continue
   * 
   * @param string $mode
   * @return \bbn\db\connection
	 */
  public function set_error_mode($mode){
    $this->on_error = $mode;
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
		$this->queries = array();
	}
  
	/**
	 * Escape names with the appropriate quotes (db, tables, columns, keys...)
	 * 
	 * @param string $table The table's name (escaped or not)
	 * @return string
	 */
	public function escape($item)
	{
		return $this->language->escape($item);
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
	 * Returns a table's full name i.e. database.table
	 * 
	 * @param string $table The table's name (escaped or not)
	 * @param bool $escaped If set to true the returned string will be escaped
	 * @return string | false
	 */
	public function table_full_name($table, $escaped=false)
	{
		return $this->language->table_full_name($table, $escaped);
	}
	
	/**
	 * Returns a table's simple name i.e. table
	 * 
	 * @param string $table The table's name (escaped or not)
	 * @param bool $escaped If set to true the returned string will be escaped
	 * @return string | false
	 */
  public function table_simple_name($table, $escaped=false)
  {
    return $this->language->table_simple_name($table, $escaped);
  }
  
	/**
	 * Returns a column's full name i.e. table.column
	 * 
	 * @param string $col The column's name (escaped or not)
	 * @param string $table The table's name (escaped or not)
	 * @param bool $escaped If set to true the returned string will be escaped
	 * @return string | false
	 */
  public function col_full_name($col, $table='', $escaped=false)
  {
    return $this->language->col_full_name($col, $table, $escaped);
  }

	/**
	 * Returns a column's simple name i.e. column
	 * 
	 * @param string $col The column's name (escaped or not)
	 * @param bool $escaped If set to true the returned string will be escaped
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
		$st = '';
		foreach ( $args as $a ){
			$st .= (string) $a;
		}
		return $this->hash_contour.md5($st).$this->hash_contour;
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
   * Returns a SQL query based on a configuration (??)
   * @todo Check the configuration format
	 *
   * @param array $cfg Description
	 * @return string 
	 */
	public function create_query($cfg)
	{
		if ( !isset($this->creator) ){
			$this->creator = new PHPSQLCreator();
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
			$this->parser = new PHPSQLParser();
		}
		return $this->parser->parse($cfg);
	}
	
	/**
	 * Returns the last statement used by a query for this connection
	 * @return string 
	 */
	public function last()
	{
		return $this->last_query;
	}

	/**
	 * Returns the last inserted ID
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
          else if ( count($arg) >= 3 ){
            array_push($this->last_params['values'], $arg[2]);
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
        if ( self::$errorState ){
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
	 * Returns a single value from a request based on arguments
	 * 
	 * @param string $table 
	 * @param string $field_to_get
	 * @param string|array $field_to_check
	 * @param string $value
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
    if ( $s = $this->select($table, [$field_to_get], $where, false, 1)){
      return $s->$field_to_get;
    }
    return false;
	}

	/**
	 * Returns a single value from a request based on arguments
	 *
	 * @param string $table
	 * @param string $field
	 * @param string $id
	 * @return string|false
	 */
	public function val_by_id($table, $field, $id, $col='id')
	{
    return $this->select_one($table, $field, [$col => $id]);
	}

	/**
	 * Returns an integer candidate for being a new ID in the given table
	 *
	 * @param string $table
	 * @param string $id_field
	 * @param int $min
	 * @param int $max
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
	 * Execute the given query with given vars, and extract the first cell's result
	 *
	 * @return string | false a single cell result
	 */
	public function get_one()
	{
    if ( $r = call_user_func_array([$this, 'query'], func_get_args()) ){
      return $r->fetchColumn(0);
		}
    return false;
	}

	/**
	 * Synonym of get_one (historical)
	 *
	 * @return string | false
	 */
	public function get_var()
	{
    return call_user_func_array([$this, "get_one"], func_get_args());
  }

	/**
	 * Returns a row as an array indexed with the fields' names
	 * Same arguments as query
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
	 * Returns an array of rows as arrays indexed with the fields' names
	 * Same arguments as query
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
	 * Returns a row as a numeric indexed array
   * Same arguments as query
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
	 * Returns an array of numeric indexed rows
   * Same arguments as query
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
	 * Returns an array with the first field as index and either the second field as value f there is only 2 fields or with an array of the different fields as value if there are more
   * Same arguments as query
	 *
	 * @return array|false
	 */
  public function get_key_val()
  {
    if ( $r = call_user_func_array([$this, 'query'], func_get_args()) ){
      $rows = $r->get_rows();
      if ( count($rows) > 0 ){
        // At least 2 columns
        if ( count($rows[0]) > 1 ){
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
		}
    return false;
  }

	/**
	 * Returns an array indexed on the columns in which are all the values
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
	 * Returns a single numeric array (one column)
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
	 * @todo Thomas fais ton taf!!
	 *
	 * @return void 
	 */
	public function get_object()
	{
    if ( $r = call_user_func_array([$this, 'query'], func_get_args()) ){
      return $r->get_object();
		}
    return false;
	}

	/**
	 * @return void 
	 */
	public function get_objects()
	{
    if ( $r = call_user_func_array([$this, 'query'], func_get_args()) ){
      return $r->get_objects();
		}
    return [];
	}
  
  public function where_cfg($where)
  {
    $r = [
        'fields' => [],
        'values' => [],
        'final' => [],
        'keypair' => []
    ];
    if ( is_array($where) && count($where) > 0 ){
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
      }
    }
    return $r;
  }

  private function _sel($table, $fields = array(), $where = array(), $order = false, $limit = 100, $start = 0)
	{
    $where = $this->where_cfg($where);
		$hash = $this->make_hash('select', $table, serialize($fields), $this->get_where($where, $table), serialize($order));
		if ( isset($this->queries[$hash]) ){
			$sql = $this->queries[$this->queries[$hash]]['statement'];
		}
		else{
			$sql = $this->language->get_select($table, $fields, $where['final'], $order, $limit);
		}
		if ( $sql && (
                $this->triggers_disabled ||
                $this->_trigger(
                        $table,
                        'select',
                        'before',
                        array_values($fields),
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
	 * @returns a row as an object
	 */
	public function select($table, $fields = array(), $where = array(), $order = false, $limit = 500, $start = 0)
	{
    if ( $r = $this->_sel($table, $fields, $where, $order, 1, $start) ){
      return $r->get_object();
		}
    return false;
	}
	
	/**
	 * @returns a single value
	 */
	public function select_one($table, $field, $where = array(), $order = false, $limit = 500, $start = 0)
	{
    if ( $r = $this->_sel($table, [$field], $where, $order, 1, $start) ){
      if ( $res = $r->get_row() ){
        return $res[$field];
      }
      
		}
    return false;
	}

  /**
	 * @returns a row as an indexed array
	 */
	public function rselect($table, $fields = array(), $where = array(), $order = false, $limit = 500, $start = 0)
	{
    if ( $r = $this->_sel($table, $fields, $where, $order, 1, $start) ){
      return $r->get_row();
		}
    return false;
	}
	
	/**
	 * @returns a row as a numeric array
	 */
	public function iselect($table, $fields = array(), $where = array(), $order = false, $limit = 500, $start = 0)
	{
    if ( $r = $this->_sel($table, $fields, $where, $order, 1, $start) ){
      return $r->get_irow();
		}
    return false;
	}
	
	/**
	 * @returns rows as an array of objects
	 */
	public function select_all($table, $fields = array(), $where = array(), $order = false, $limit = 500, $start = 0)
	{
    if ( $r = $this->_sel($table, $fields, $where, $order, $limit, $start) ){
      return $r->get_objects();
    }
    return [];
	}
	
	/**
	 * @returns rows as an array of indexed arrays
	 */
	public function rselect_all($table, $fields = array(), $where = array(), $order = false, $limit = 500, $start = 0)
	{
    if ( $r = $this->_sel($table, $fields, $where, $order, $limit, $start) ){
      return $r->get_rows();
    }
    return [];
	}
	
	/**
	 * @returns rows as an array of numeric arrays
	 */
	public function iselect_all($table, $fields = array(), $where = array(), $order = false, $limit = 500, $start = 0)
	{
    if ( $r = $this->_sel($table, $fields, $where, $order, $limit, $start) ){
      return $r->get_irows();
    }
    return [];
	}
	
	/**
	 * @return void 
	 */
	public function insert($table, array $values, $ignore = false)
	{
		$r = false;
    $keys = array_keys($values);
    if ( isset($keys[0]) && ($keys[0] === 0) ){
      $keys = array_keys($values[0]);
    }
    else{
      $values = [$values];
    }
		$hash = $this->make_hash('insert', $table, serialize($keys), $ignore);
		if ( isset($this->queries[$hash]) ){
			$sql = $this->queries[$this->queries[$hash]]['statement'];
		}
		else{
			$sql = $this->language->get_insert($table, $keys, $ignore);
		}
    $affected = 0;
    foreach ( $values as $vals ){
      if ( $sql && ( $this->triggers_disabled || $this->_trigger($table, 'insert', 'before', $vals) ) ){
        if ( $r = $this->query($sql, $hash, array_values($vals)) ){
          $affected += $r;
          $this->_trigger($table, 'insert', 'after', $vals);
        }
      }
    }
		return $affected;
	}
	
	/**
	 * @return void 
	 */
	public function insert_update($table, array $values)
	{
		$r = false;
		$hash = $this->make_hash('insert_update', $table, serialize(array_keys($values)));
		if ( isset($this->queries[$hash]) ){
			$sql = $this->queries[$this->queries[$hash]]['statement'];
		}
		else if ( $sql = $this->language->get_insert($table, array_keys($values)) ){
			$sql .= " ON DUPLICATE KEY UPDATE ";
			$vals = array_values($values);
			foreach ( $values as $k => $v ){
				$sql .= "`$k` = ?, ";
				array_push($vals, $v);
			}
			$sql = substr($sql,0,strrpos($sql,','));
		}
    $vals = array_merge(array_values($values),array_values($values));
		if ( $sql && ( $this->triggers_disabled || $this->_trigger($table, 'insert', 'before', $values) ) ){
      $last = $this->last_id();
      $r = $this->query($sql, $hash, $vals);
      if ( $r ){
        if ( $last !== $this->last_id() ){
          $this->_trigger($table, 'insert', 'after', $values);
        }
        else{
          $this->_trigger($table, 'update', 'after', $values, $values);
        }
      }
		}
		return $r;
	}

	/**
	 * @return void 
	 */
	public function update($table, array $val, array $where)
	{
		$r = false;
    $where = $this->where_cfg($where);
		$hash = $this->make_hash('update', $table, serialize(array_keys($val)), $this->get_where($where, $table));
		if ( isset($this->queries[$hash]) ){
			$sql = $this->queries[$this->queries[$hash]]['statement'];
		}
		else{
			$sql = $this->language->get_update($table, array_keys($val), $where['final']);
		}
		if ( $sql && ( $this->triggers_disabled || $this->_trigger($table, 'update', 'before', $val, $where['keypair']) ) ){
      $r = $this->query($sql, $hash, array_merge(array_values($val), $where['values']));
      if ( $r ){
        $this->_trigger($table, 'update', 'after', $val, $where['keypair']);
      }
		}
		return $r;
	}

	/**
	 * @return bool 
	 */
	public function delete($table, array $where, $ignore = false)
	{
		$r = false;
    $where = $this->where_cfg($where);
		$hash = $this->make_hash('delete', $table, $this->get_where($where, $table), $ignore);
		if ( isset($this->queries[$hash]) ){
			$sql = $this->queries[$this->queries[$hash]]['statement'];
		}
		else{
			$sql = $this->language->get_delete($table, $where['final'], $ignore);
		}
		if ( $sql && ( $this->triggers_disabled || $this->_trigger($table, 'delete', 'before', [], $where['keypair']) ) ){
      $r = $this->query($sql, $hash, $where['values']);
      if ( $r ){
        $this->_trigger($table, 'delete', 'after', [], $where['keypair']);
      }
		}
		return $r;
	}
	
	/**
	 * @return void 
	 */
	public function delete_ignore($table, array $where)
	{
    return $this->delete($table, $where, 1);
  }
  
	/**
	 * @return void 
	 */
	public function insert_ignore($table, array $values)
	{
		return $this->insert($table, $values, 1);
	}

	/**
	 * @param mixed $data
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
	 * @return array | false
	 */
	public function get_databases()
	{
    return $this->_get_cache('', 'databases');
	}

	/**
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
	 * @return array | false
	 */
	public function get_columns($table)
	{
    if ( $tmp = $this->_get_cache($table) ){
      return $tmp['columns'];
    }
    return false;
	}
	
	/**
	 * @return string
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
	 * @return string | false
	 */
	public function get_change($db)
	{
    return $this->language->get_change($db);
	}
	
	/**
	 * @return string
	 */
	public function get_where(array $where, $table='')
	{
    return $this->language->get_where($where, $table='');
	}
	
	/**
	 * @return string
	 */
	public function get_order($order, $table='')
	{
    return $this->language->get_order($order, $table='');
	}
	
	/**
	 * @return string
	 */
	public function get_limit($limit)
	{
    return $this->language->get_limit($limit);
	}
	
	/**
	 * @return string | false
	 */
	public function get_create($table)
	{
    return $this->language->get_create($table);
	}
	
	/**
	 * @return string | false
	 */
	public function get_delete($table, array $where, $ignore = false, $php = false)
	{
    return $this->language->get_delete($table, $where, $ignore, $php);
	}

	/**
	 * @return string
	 */
	public function get_select($table, array $fields = array(), array $where = array(), $order = array(), $limit = false, $start = 0, $php = false)
	{
    return $this->language->get_select($table, $fields, $where, $order, $limit, $start, $php);
	}
	
	/**
	 * @return string
	 */
	public function get_insert($table, array $fields = array(), $ignore = false, $php = false)
	{
    return $this->language->get_insert($table, $fields, $ignore, $php);
	}
	
	/**
	 * @return string
	 */
	public function get_update($table, array $fields = array(), array $where = array(), $php = false)
	{
    return $this->language->get_update($table, $fields, $where, $php);
	}
	
	/**
	 * @return string
	 */
	public function get_column_values($table, $field,  array $where = array(), $limit = false, $start = 0, $php = false)
	{
    $r = [];
    if ( $rows = $this->get_irows($this->language->get_column_values($table, $field, $where, $limit, $start, false)) ){
      foreach ( $rows as $row ){
        array_push($r, $row[0]);
      }
    }
    return $r;
	}
	
	/**
	 * @return string
	 */
	public function get_values_count($table, $field,  array $where = array(), $limit = false, $start = 0, $php = false)
	{
    return $this->language->get_values_count($table, $field, $where, $limit, $start, $php);
	}
  
  /**
   * @return array | false
   */
  public function get_field_values($table, $field,  array $where = array(), $limit = false, $start = 0)
  {
    if ( $r = $this->language->get_column_values($table, $field, $where, $limit, $start) ){
      if ( $d = $this->get_by_columns($r) ){
        return $d[$field];
      }
    }
  }
	
  /**
   * @return array | false
   */
	public function count_field_values($table, $field,  array $where = array(), $limit = false, $start = 0)
	{
    if ( $r = $this->language->get_values_count($table, $field, $where, $limit, $start) ){
      return $this->get_rows($r);
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
	
}
?>