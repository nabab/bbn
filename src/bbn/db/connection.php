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
	 * @var mixed
	 */
		$max_queries = 100,
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
	 * @var mixed
	 */
		$structures = [],
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
	 * @var mixed
	 */
		$last_insert_id,
	/**
	 * An array of functions for launching triggers on actions
	 * @var mixed
	 */
		$triggers,
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
		$msg = array();
		array_push($msg,self::$line);
		array_push($msg, date('H:i:s d-m-Y').' - Error in the page!');
		array_push($msg,self::$line);
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
		array_push($msg,self::$line);
		array_push($msg, print_r($this->last_params, 1));
		array_push($msg,self::$line);
		if ( defined('BBN_IS_DEV') && BBN_IS_DEV ){
      if ( defined('BBN_DATA_PATH') && is_dir(BBN_DATA_PATH.'logs') ){
  			file_put_contents(BBN_DATA_PATH.'logs/db.log', implode("\n",$msg)."\n\n", FILE_APPEND);
      }
      else{
        die(nl2br(implode("\n",$msg)));
      }
		}
		else
		{
			if ( defined('BBN_ADMIN_EMAIL') ){
				mail(BBN_ADMIN_EMAIL, 'Error DB!', implode("\n",$msg));
			}
			if ( isset($argv) ){
				echo implode("\n",$msg);
			}
			else if ( !defined("BBN_IS_DEV") || constant("BBN_IS_DEV") !== false ){
				die();
			}
		}
	}

	/**
	 * @todo Thomas fais ton taf!!
	 * 
	 * @param $table
	 * @param $kind
	 * @param $moment
	 * @param $ values
	 * @param $where
	 * @return bool
	 */
	private function launch_triggers($table, $kind, $moment, $values, $where = [])
	{
		$trig = 1;
		if ( isset($this->triggers[$kind][$moment]) ){
      if ( is_callable($this->triggers[$kind][$moment]) ){
        $f =& $this->triggers[$kind][$moment];
      }
      else if ( is_array($this->triggers[$kind][$moment]) && isset($this->triggers[$kind][$moment][$table]) && is_callable($this->triggers[$kind][$moment][$table]) ){
        $f =& $this->triggers[$kind][$moment][$table];
      }
      if ( isset($f) ){
        if ( !call_user_func_array($f, [$table, $kind, $moment, $values, $where]) ){
          $trig = false;
        }
      }
		}
		return $trig;
	}
	
  /**
   * Checks if the database if in a state ready to query
   * 
   * @return bool 
   */
  private function check()
  {
    if ( isset($this->current) && self::$errorState === 1 ){
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
      if ( $cfg = $this->language->get_connection($cfg) ){
        $this->qte = $this->language->qte;
        try{
          call_user_func_array('parent::__construct', $cfg['args']);
          $this->current = $cfg['db'];
          $this->engine = $cfg['engine'];
          $this->host = isset($cfg['host']) ? $cfg['host'] : 'localhost';
          $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
          $this->start_fancy_stuff();
          $this->enable_keys();
        }
        catch ( \PDOException $e )
          { $this->error($e); }
      }
		}
	}
  
  /**
	* @todo Thomas a toi de jouer!
	* 
	* @param
	* @return
	*/
	protected function log($st)
	{
		if ( defined('BBN_IS_DEV') && defined('BBN_DATA_PATH') && BBN_IS_DEV ){
			file_put_contents(BBN_DATA_PATH.'logs/db.log', $st."\n\n", FILE_APPEND);
		}
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
  * @todo Thomas c'est a toi!!
	*
	* @param $function
	* @param $kind
	* @param $moment
	* @param table
	* @return bool
	*/
	public function set_trigger($function, $kind='', $moment='', $table='' )
	{
		$r = false;
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
            if ( in_array($m, $moments) ){
              if ( empty($table) ){
                $this->triggers[$k][$m] = $function;
              }
              else{
                if ( !is_array($this->triggers[$k][$m]) ){
                  $this->triggers[$k][$m] = [];
                }
                $this->triggers[$k][$m][$table] = $function;
              }
            }
          }
        }
      }
		}
		return $r;
	}
	
 /**
   * @todo Thomas fais ton taf!!
	 *
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
	 * @todo Thomas fais ton taf!!
	 *
	 * @param $cfg
	 * @return string 
	 */
	public function parse_query($cfg)
	{
		if ( !isset($this->parser) ){
			$this->parser = new PHPSQLParser();
		}
		return $this->parser->parse($cfg);
	}
	
	/**
	 * @todo Thomas fais ton taf!!
	 * @return string 
	 */
	public function last()
	{
		return $this->last_query;
	}

	/**
	 * @todo Thomas fais ton taf!!
	 * @return string 
	 */
	public function last_id()
	{
		if ( $this->last_insert_id ){
			return $this->last_insert_id;
		}
		return false;
	}
	/**
	 * @todo Thomas fais ton taf!!
	 *
	 * @param $hash
	 * @param $ statement
	 * @param $ sequences
	 * @param $placeholders
	 * @param $ options
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
	 * @todo Thomas fais ton taf!!
	 * 
	 * @param $db
	 * @return string | false
	 */
	public function change($db)
	{
    if ( $this->language->change($db) ){
      $this->current = $db;
    }
		return $this;
	}
	
	/**
	 * @return database chainable 
	 */
	public function disable_keys()
	{
    $this->language->disable_keys();
		return $this;
	}

	/**
	 * @return database chainable
	 */
	public function enable_keys()
	{
    $this->language->enable_keys();
		return $this;
	}
	
	/**
	 * @todo Thomas faut bosser maintenant!!
	 * 
	 * @param $table
	 * @param $escaped
	 * @return string | false
	 */
	public function get_full_name($table, $escaped=false)
	{
		return $this->language->get_full_name($table, $escaped);
	}
	
	/**
	 * Execute the parent query function
	 * @return void
	 */
	public function raw_query()
	{
    if ( $this->check() ){
      $args = func_get_args();
      return call_user_func_array('parent::query', $args);
    }
  }
  
	/**
	 * @todo Thomas fais ton taf!!
	 * @return void
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
        else if ( isset($args[0]) && is_array($args[0]) ){
          $args = $args[0];
        }
        if ( !isset($driver_options) ){
          $driver_options = array();
        }
        $this->last_params['values'] = array();
        $num_values = 0;
        foreach ( $args as $i => $arg ){
          if ( !is_array($arg) ){
            array_push($this->last_params['values'], $arg);
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
          else{
            die("Impossible to parse the query $statement");
          }
        }
        else if ( is_string($this->queries[$hash]) ){
          $hash = $this->queries[$hash];
        }
        /* The number of values must match the number of values to bind */
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
        try{
          if ( $q['prepared'] && ( isset($q['sequences']['INSERT']) || isset($q['sequences']['UPDATE']) || isset($q['sequences']['DELETE']) || isset($q['sequences']['DROP']) ) ){
            $r = $q['prepared']->init($this->last_params['values'])->execute();
          }
          else{
            if ( isset($q['sequences']['SELECT']) || isset($q['sequences']['SHOW']) || isset($q['sequences']['UNION']) ){
              if ( !$q['prepared'] ){
                $q['prepared'] = $this->prepare($q['statement'], $driver_options);
                if ( isset($t) && $q['exe_time'] === 0 ){
                  $q['exe_time'] = microtime(1) - $t;
                }
              }
              else if ( $num_values > 0 ){
                $q['prepared']->init($this->last_params['values']);
              }
              return $q['prepared'];
            }
            else{
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
          }
          if ( isset($q['sequences']['INSERT']) ){
            $this->last_insert_id = $this->lastInsertId();
          }
          if ( $q['prepared'] && ( isset($q['sequences']['INSERT']) || isset($q['sequences']['UPDATE']) || isset($q['sequences']['DELETE']) || isset($q['sequences']['DROP']) ) ){
            return $q['prepared']->rowCount();
          }
          return $r;
        }
        catch (\PDOException $e )
          { $this->error($e); }
      }
    }
		return false;
	}

	/**
	 * @todo Thomas fais ton taf!!
	 * 
	 * @param $table
	 * @param $field_to_get
	 * @param $field_to_check
	 * @param $ value
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
	 * @todo Thomas fais ton taf!!
	 *
	 * @param $table
	 * @param $ field
	 * @param $id
	 * @return string | false 
	 */
	public function val_by_id($table, $field, $id, $col='id')
	{
    if ( $s = $this->select($table, [$field], [$col => $id]) ){
      return $s->$col;
    }
    return false;
	}

	/**
	 * @todo Thomas fais ton taf!!
	 *
	 * @param $table
	 * @param $id_field
	 * @param $min
	 * @param $max
	 * @return int | false
	 */
	public function new_id($table, $id_field='id', $min = 11111, $max = 499998999)
	{
		if ( ( $max > $min ) && text::check_name($id_field) && $table = $this->get_full_name($table,1) ){
			$id = mt_rand($min, $max);
			while ( $this->select($table, [$id_field], [$id_field => $id]) ){
				$id = mt_rand($min, $max);
			}
			return $id;
		}
		return false;
	}

	/**
	 * @todo Thomas fais ton taf!!
	 *
	 * @param $query
	 * @return array | false
	 */
	public function fetch($query)
	{
    if ( $r = $this->query(func_get_args()) ){
      return $r->fetch();
		}
    return false;
	}

	/**
	 * @todo Thomas fais ton taf!!
	 *
	 * @param $query
	 * @return array | false
	 */
	public function fetchAll($query)
	{
    if ( $r = $this->query(func_get_args()) ){
      return $r->fetchAll();
		}
    return false;
	}

	/**
	 * \PDOStatement::fetchColumn
	 *
	 * @param $query
	 * @return string | false
	 */
	public function fetchColumn($query, $num=0)
	{
    if ( !is_int($num) ){
      $num = 0;
    }
    if ( $r = $this->query(func_get_args()) ){
      return $r->fetchColumn($num);
		}
    return false;
	}

	/**
	 * \PDOStatement::fetchObject
	 *
	 * @param $query
	 * @return stdClass 
	 */
	public function fetchObject($query)
	{
    if ( $r = $this->query(func_get_args()) ){
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
    if ( $r = $this->query(func_get_args()) ){
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
    return $this->get_one(func_get_args());
  }

	/**
	 * @todo Thomas fais ton taf!!
	 *
	 * @return array | false
	 */
	public function get_row()
	{
    if ( $r = $this->query(func_get_args()) ){
      return $r->get_row();
		}
    return false;
	}

	/**
	 * @todo Thomas fais ton taf!!
	 *
	 * @return array | false
	 */
	public function get_rows()
	{
    if ( $r = $this->query(func_get_args()) ){
      return $r->get_rows();
		}
    return false;
	}

	/**
	 * @todo Thomas fais ton taf!!
	 *
	 * @return array | false
	 */
	public function get_irow()
	{
    if ( $r = $this->query(func_get_args()) ){
      return $r->get_irow();
		}
    return false;
	}

	/**
	 * @todo Thomas fais ton taf!!
	 *
	 * @return array | false
	 */
	public function get_irows()
	{
    if ( $r = $this->query(func_get_args()) ){
      return $r->get_irows();
		}
    return false;
	}

	/**
	 * @todo Thomas fais ton taf!!
	 *
	 * @return array | false 
	 */
	public function get_by_columns()
	{
    if ( $r = $this->query(func_get_args()) ){
      return $r->get_by_columns();
		}
    return false;
	}

	/**
	 * @todo Thomas fais ton taf!!
	 *
	 * @return array | false 
	 */
	public function get_array($table, $fields = array(), $where = array(), $order = false, $limit = 500, $start = 0)
	{
    $rows = $this->iselect_all($table, $fields = array(), $where = array(), $order = false, $limit = 500, $start = 0);
    $r = [];
    foreach ( $rows as $row ){
      if ( count($row) === 1 ){
        array_push($r, $row[0]);
      }
      else{
        $r[$row[0]] = $row[1];
      }
    }
    return $r;
	}

	/**
	 * @todo Thomas fais ton taf!!
	 *
	 * @return array | false 
	 */
	public function get_col_array()
	{
    if ( $r = $this->get_by_columns(func_get_args()) ){
      return array_values(array_map(function($a){
        return current($a);
      }, $r));
		}
    return false;
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
    if ( $r = $this->query(func_get_args()) ){
      return $r->get_object();
		}
    return false;
	}

	/**
	 * @return void 
	 */
	public function get_objects()
	{
    if ( $r = $this->query(func_get_args()) ){
      return $r->get_objects();
		}
    return false;
	}

	/**
	 * @returns a row as an object
	 */
	public function select($table, $fields = array(), $where = array(), $order = false, $limit = 500, $start = 0)
	{
		$hash = $this->make_hash('select', $table, serialize($fields), serialize(array_keys($where)), ( $order ? 1 : '0' ), $limit);
		if ( isset($this->queries[$hash]) ){
			$sql = $this->queries[$this->queries[$hash]]['statement'];
		}
		else{
			$sql = $this->language->get_select($table, $fields, array_keys($where), $order, $limit);
		}
		if ( $sql && ( $this->triggers_disabled || $this->launch_triggers($table, 'select', 'before', $fields, $where) ) ){
      if ( count($where) > 0 ){
        $r = $this->query($sql, $hash, array_values($where));
      }
      else{
        $r = $this->query($sql, $hash);
      }
      if ( $r ){
        $this->launch_triggers($table, 'select', 'after', $fields, $where);
        return $r->get_object();
      }
		}
	}
	
	/**
	 * @returns a row as a numeric array
	 */
	public function iselect($table, $fields = array(), $where = array(), $order = false, $limit = 500, $start = 0)
	{
		$hash = $this->make_hash('select', $table, serialize($fields), serialize(array_keys($where)), ( $order ? 1 : '0' ), $limit);
		if ( isset($this->queries[$hash]) ){
			$sql = $this->queries[$this->queries[$hash]]['statement'];
		}
		else{
			$sql = $this->language->get_select($table, $fields, array_keys($where), $order, $limit);
		}
		if ( $sql && ( $this->triggers_disabled || $this->launch_triggers($table, 'select', 'before', $fields, $where) ) ){
      if ( count($where) > 0 ){
        $r = $this->query($sql, $hash, array_values($where));
      }
      else{
        $r = $this->query($sql, $hash);
      }
      if ( $r ){
        $this->launch_triggers($table, 'select', 'after', $fields, $where);
        return $r->get_irow();
      }
		}
	}
	
	/**
	 * @returns rows as an array of objects
	 */
	public function select_all($table, $fields = array(), $where = array(), $order = false, $limit = 500, $start = 0)
	{
		$hash = $this->make_hash('select', $table, serialize($fields), serialize(array_keys($where)), ( $order ? 1 : '0' ), $limit);
		if ( isset($this->queries[$hash]) ){
			$sql = $this->queries[$this->queries[$hash]]['statement'];
		}
		else{
			$sql = $this->language->get_select($table, $fields, array_keys($where), $order, $limit);
		}
		if ( $sql && ( $this->triggers_disabled || $this->launch_triggers($table, 'select', 'before', $fields, $where) ) ){
      if ( count($where) > 0 ){
        $r = $this->query($sql, $hash, array_values($where));
      }
      else{
        $r = $this->query($sql, $hash);
      }
      if ( $r ){
        $this->launch_triggers($table, 'select', 'after', $fields, $where);
        return $r->get_objects();
      }
		}
	}
	
	/**
	 * @returns rows as an array of numeric arrays
	 */
	public function iselect_all($table, $fields = array(), $where = array(), $order = false, $limit = 500, $start = 0)
	{
		$hash = $this->make_hash('select', $table, serialize($fields), serialize(array_keys($where)), ( $order ? 1 : '0' ), $limit);
		if ( isset($this->queries[$hash]) ){
			$sql = $this->queries[$this->queries[$hash]]['statement'];
		}
		else{
			$sql = $this->language->get_select($table, $fields, array_keys($where), $order, $limit);
		}
		if ( $sql && ( $this->triggers_disabled || $this->launch_triggers($table, 'select', 'before', $fields, $where) ) ){
      if ( count($where) > 0 ){
        $r = $this->query($sql, $hash, array_values($where));
      }
      else{
        $r = $this->query($sql, $hash);
      }
      if ( $r ){
        $this->launch_triggers($table, 'select', 'after', $fields, $where);
        return $r->get_irows();
      }
		}
	}
	
	/**
	 * @return void 
	 */
	public function insert($table, array $values, $ignore = false)
	{
		$r = false;
		$hash = $this->make_hash('insert', $table, serialize(array_keys($values)), $ignore);
		if ( isset($this->queries[$hash]) ){
			$sql = $this->queries[$this->queries[$hash]]['statement'];
		}
		else{
			$sql = $this->language->get_insert($table, array_keys($values), $ignore);
		}
		if ( $sql && ( $this->triggers_disabled || $this->launch_triggers($table, 'insert', 'before', $values) ) ){
      $r = $this->query($sql, $hash, array_values($values));
      if ( $r ){
        $this->launch_triggers($table, 'insert', 'after', $values);
      }
		}
		return $r;
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
			if ( $this->queries[$this->queries[$hash]]['num_val'] === ( count($values) / 2 ) ){
				$vals = array_merge(array_values($values),array_values($values));;
			}
			else{
				$vals = array_values($values);
			}
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
		if ( $sql && ( $this->triggers_disabled || $this->launch_triggers($table, 'insert', 'before', $values) ) ){
      $last = $this->last_id();
      $r = $this->query($sql, $hash, $vals);
      if ( $r ){
        if ( $last !== $this->last_id() ){
          $this->launch_triggers($table, 'insert', 'after', $values);
        }
        else{
          $this->launch_triggers($table, 'update', 'after', $values, $values);
        }
      }
		}
		return $r;
	}

	/**
	 * @return void 
	 */
	public function update($table, array $values, array $where)
	{
		$r = false;
		$hash = $this->make_hash('insert_update', $table, serialize(array_keys($values)), serialize(array_keys($where)));
		if ( isset($this->queries[$hash]) ){
			$sql = $this->queries[$this->queries[$hash]]['statement'];
		}
		else{
			$sql = $this->language->get_update($table, array_keys($values), array_keys($where));
		}
		if ( $sql && ( $this->triggers_disabled || $this->launch_triggers($table, 'update', 'before', $values, $where) ) ){
      $r = $this->query($sql, $hash, array_merge(array_values($values), array_values($where)));
      if ( $r ){
        $this->launch_triggers($table, 'update', 'after', $values, $where);
      }
		}
		return $r;
	}

	/**
	 * @return bool 
	 */
	public function delete($table, array $where)
	{
		$r = false;
		$hash = $this->make_hash('delete', $table, serialize(array_keys($where)));
		if ( isset($this->queries[$hash]) ){
			$sql = $this->queries[$this->queries[$hash]]['statement'];
		}
		else{
			$sql = $this->language->get_delete($table, array_keys($where));
		}
		if ( $sql && ( $this->triggers_disabled || $this->launch_triggers($table, 'delete', 'before', [], $where) ) ){
      $r = $this->query($sql, $hash, array_values($where));
      if ( $r ){
        $this->launch_triggers($table, 'delete', 'after', [], $where);
      }
		}
		return $r;
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
		$r = array();
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
				$full = $this->get_full_name($t);
				if ( !isset($this->structures[$full]) ){
					$keys = $this->get_keys($full);
					$this->structures[$full] = [
						'fields' => $this->get_columns($full),
						'keys' => $keys['keys']
					];
					foreach ( $this->structures[$full]['fields'] as $i => $f ){
						if ( isset($keys['cols'][$i]) ){
							$this->structures[$full]['fields'][$i]['keys'] = $keys['cols'][$i];
						}
					}
				}
				$r[$full] = $this->structures[$full];
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
    return $this->language->get_databases();
	}

	/**
	 * @return array | false
	 */
	public function get_tables($database='')
	{
    return $this->language->get_tables($database);
	}
	/**
	 * @return array | false
	 */
	public function get_columns($table)
	{
    return $this->language->get_columns($table);
	}
	
	/**
	 * @return string
	 */
	public function get_keys($table)
	{
    return $this->language->get_keys($table);
	}
	
	/**
	 * @return string | false
	 */
	public function get_change($db)
	{
    return $this->language->get_change($db);
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
	public function get_delete($table, array $where)
	{
    return $this->language->get_delete($table, $where);
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