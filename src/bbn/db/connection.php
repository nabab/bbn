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
class connection extends \PDO implements actions
{
	private
	/**
	 * @var mixed
	 */
		$last_query,
	/**
	 * @var mixed
	 */
		$last_prepared,
	/**
	 * @var array
	 */
		$queries = array(),
	/**
	 * @var array
	 */
		$hashes = array(),
	/**
	 * @var mixed
	 */
		$last_insert_id;
	
	public
	/**
	 * @var mixed
	 */
		$current;

	private static
	/**
	 * @var mixed
	 */
		$timeout,
	/**
	 * @var string
	 */
		$line='---------------------------------------------------------------------------------',
	/**
	 * @var mixed
	 */
		$l;


	/**
	 * @return void 
	 */
	private static function setTimeout()
	{
		if ( !isset(self::$timeout) )
		{
			$max = ini_get('max_execution_time');
			if ( $max > 0 )
				self::$timeout = $max > 2 ? $max - 2 : 1;
			else
				self::$timeout = false;
		}
		return self::$timeout;
	}

	/**
	 * @return void 
	 */
	public static function error($e, $sql='')
	{
		$msg = array();
		array_push($msg,self::$line);
		array_push($msg,@date('H:i:s d-m-Y').' - Error in the page!');
		array_push($msg,self::$line);
		$b = debug_backtrace();
		foreach ( $b as $c )
		{
			if ( isset($c['file']) )
			{
				array_push($msg,'File '.$c['file'].' - Line '.$c['line']);
				array_push($msg,
					( isset($c['class']) ?  'Class '.$c['class'].' - ' : '' ).
					( isset($c['function']) ?  'Function '.$c['function'] : '' )
				);
			}
		}
		array_push($msg,self::$line);
		array_push($msg,'Error message: '.$e->getMessage());
		array_push($msg,'Request: '.$sql);
		array_push($msg,self::$line);
		if ( defined('BBN_IS_DEV') && BBN_IS_DEV ){
			echo nl2br(implode("\n",$msg));
		}
		else
		{
			if ( defined('BBN_ADMIN_EMAIL') ){
				@mail('thomas@babna.com','Error DB!',implode("\n",$msg));
			}
			if ( isset($argv) ){
				echo nl2br(implode("\n",$msg));
			}
		}
		die();
	}

	/**
	 * @return void 
	 */
	public function __construct($cfg=array())
	{
		if ( isset($cfg['user'],$cfg['pass'],$cfg['db']) )
		{
			$cfg['engine'] = isset($cfg['engine']) ? $cfg['engine'] : 'mysql';
			$cfg['host'] = isset($cfg['host']) ? $cfg['host'] : 'localhost';
		}
		else if ( isset($cfg['db'],$cfg['engine']) && $cfg['engine'] === 'sqlite' && strpos($cfg['db'],'/') === false ){
			if ( strpos($cfg['db'],'.') === false ){
				$cfg['db'] .= '.sqlite';
			}
			$cfg = array(
			'host' => '',
			'user' => '',
			'pass' => '',
			'db' => $cfg['db'],
			'engine' => 'sqlite'
			);
		}
		else if ( defined('BBN_DB_HOST') ){
			$cfg = array(
			'host' => BBN_DB_HOST,
			'user' => BBN_DB_USER,
			'pass' => BBN_DB_PASS,
			'db' => BBN_DATABASE,
			'engine' => BBN_DB_ENGINE
			);
		}
		if ( isset($cfg['host'],$cfg['user'],$cfg['pass'],$cfg['db'],$cfg['engine']) &&
		strpos($cfg['db'],'/') === false )
		{
			switch ( $cfg['engine'] )
			{
				case 'mysql':
					$params = array(
						\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
						\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
						\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
					);
					break;
				case 'mssql':
				case 'oracle':
				case 'postgre':
				case 'sqlite':
					$params = array();
					break;
			}
			if ( self::setTimeout() )
				$params[\PDO::ATTR_TIMEOUT] = self::$timeout;
			try
			{
				switch ( $cfg['engine'] )
				{
					case 'mysql':
						parent::__construct('mysql:host='.$cfg['host'].';dbname='.$cfg['db'], $cfg['user'], $cfg['pass'],$params);
						break;
					case 'sqlite';
						if ( defined('BBN_DATA_PATH') && isset($cfg['db']) && is_file(BBN_DATA_PATH.'/db/'.$cfg['db']) ){
							parent::__construct('sqlite:'.BBN_DATA_PATH.'/db/'.$cfg['db']);
						}
						break;
				}
				$this->current = $cfg['db'];
			}
			catch (\PDOException $e)
				{ self::error($e,"Connection"); }
		}
	}
	/**
	 * @return void
	 */
	public function clear()
	{
		$this->hashes = array();
	}

	/**
	 * @return string 
	 */
	public function last()
	{
		return $this->last_query;
	}

	/**
	 * @return string 
	 */
	public function last_id()
	{
		if ( $this->last_insert_id )
			return $this->last_insert_id;
		return false;
	}

	/**
	 * @return void
	 */
	public function query()
	{
		$args = func_get_args();
		if ( count($args) === 1 && is_array($args[0]) ){
			$args = $args[0];
		}
		if ( is_string($args[0]) )
		{
			// The first argument is the statement
			$statement = trim(array_shift($args));
			// Sending a hash as second argument (insert, update, delete functions) will bind it to the statement
			if ( isset($args[0]) && is_string($args[0]) && ( strlen($args[0]) === 32 ) && strpos($args[0], ' ') === false ){
				$hash = array_shift($args);
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
			$values = array();
			$num_values = 0;
			foreach ( $args as $i => $arg )
			{
				if ( !is_array($arg) ){
					array_push($values,$arg);
					$num_values++;
				}
			}
			if ( isset($hash, $this->hashes[$hash]) ){
				$statement = $this->hashes[$hash]['statement'];
				$sequences = $this->hashes[$hash]['sequences'];
				$num_placeholders = $this->hashes[$hash]['num_placeholders'];
			}
			else{
				/* parse the statement */
				$sequences = \bbn\db\parser::ParseString($statement)->getArray();
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
				$num_placeholders = isset($exp[1]) && is_array($exp[1]) ? count($exp[1]) : 0;
				if ( isset($hash) ){
					$this->hashes[$hash] = [
						'statement' => $statement,
						'sequences' => $sequences,
						'num_placeholders' => $num_placeholders
					];
				}
			}
			/* The number of values must match the number of values to bind */
			if ( $num_values !== $num_placeholders ){
				var_dump('Incorrect arguments count (your values: '.$num_values.', in the statement: '.$num_placeholders.')','options',$driver_options,'statement',$statement,'start of values',$values, 'end of values');
				exit;
			}
			try
			{
				/* record the last query - adding a full/limited history ? */
				if ( $this->last_query === $statement && $this->last_prepared && ( isset($sequences['insert']) || isset($sequences['update']) || isset($sequences['delete']) ) ){
					$r = $this->last_prepared->init($values)->execute();
				}
				else{
					$this->last_query = $statement;
					if ( isset($sequences['select']) || isset($sequences['show']) ){
						$this->setAttribute(\PDO::ATTR_STATEMENT_CLASS,array('\bbn\db\query',array(&$this,$sequences,$values)));
						$this->last_prepared = $this->prepare($statement, $driver_options);
						return $this->last_prepared;
					}
					else{
						if ( $num_values === 0 ){
							$r = $this->exec($statement);
						}
						else{
							$this->setAttribute(\PDO::ATTR_STATEMENT_CLASS,array('\bbn\db\query',array(&$this,$sequences,$values)));
							$this->last_prepared = $this->prepare($statement, $driver_options);
							$r = $this->last_prepared->execute();
						}
						if ( isset($sequences['insert']) ){
							$this->last_insert_id = $this->lastInsertId();
						}
					}
				}
				if ( $this->last_prepared && ( isset($sequences['insert']) || isset($sequences['update']) || isset($sequences['delete']) ) ){
					return $this->last_prepared->rowCount();
				}
				return $r;
			}
			catch (\PDOException $e )
				{ self::error($e,$this->last_query); }
		}
		return false;
	}

	/**
	 * @return string | false
	 */
	public function change($db)
	{
		if ( $this->current !== $db && text::check_name($db) ){
			$this->query("USE $db");
			$this->current = $db;
		}
		return $this;
	}

	/**
	 * @return string | false
	 */
	public function get_val($table, $field_to_get, $field_to_check='', $value='')
	{
		if ( text::check_name($table, $field_to_get) )
		{
			if ( empty($field_to_check) && empty($value) )
				$s = "
					SELECT `$field_to_get`
					FROM `$table`
					LIMIT 1";
			else if ( text::check_name($field_to_check) )
			{
				$val = is_int($value) ? "%u" : "'%s'";
				$s = sprintf("
					SELECT `$field_to_get`
					FROM `$table`
					WHERE `$field_to_check` = $val
					LIMIT 1",
					text::escape_apo($value));
			}
			try
				{ return $this->query($s)->fetchColumn(0); }
			catch (\PDOException $e )
				{ self::error($e,$this->last_query); }
		}
	}

	/**
	 * @return string | false 
	 */
	public function val_by_id($table, $field, $id)
	{
		return $this->get_var($table,$field,'id',$id);
	}

	/**
	 * @return int | false
	 */
	public function new_id($table, $id_field='id')
	{
		if ( text::check_name($table,$id_field) )
		{
			$r = $this->query("
				SELECT COUNT(*)
				FROM `$table`
				WHERE `$id_field` = ?
				LIMIT 1");
			do
			{
				$id = mt_rand(11111,999998999);
				$r->execute($id);
			}
			while ( $r->fetchColumn(0) > 0 );
			return $id;
		}
		return false;
	}

	/**
	 * @return array | false
	 */
	public function fetch($query)
	{
		try{
			return $this->query(func_get_args())->fetch();
		}
		catch (\PDOException $e ){
			self::error($e,$this->last_query);
		}
	}

	/**
	 * @return array | false
	 */
	public function fetchAll($query)
	{
		try{
			return $this->query(func_get_args())->fetchAll();
		}
		catch (\PDOException $e ){
			self::error($e,$this->last_query);
		}
	}

	/**
	 * @return string | false
	 */
	public function fetchColumn($query)
	{
		try{
			return $this->query(func_get_args())->fetchColumn(0);
		}
		catch (\PDOException $e ){
			self::error($e,$this->last_query);
		}
	}

	/**
	 * @return stdClass 
	 */
	public function fetchObject($query)
	{
		try{
			return $this->query(func_get_args())->fetchObject();
		}
		catch (\PDOException $e ){
			self::error($e,$this->last_query);
		}
	}

	/**
	 * @return string | false
	 */
	public function get_var()
	{
		try{
			return $this->query(func_get_args())->fetchColumn(0);
		}
		catch (\PDOException $e ){
			self::error($e,$this->last_query);
		}
	}

	/**
	 * @return array | false
	 */
	public function get_row()
	{
		try{
			return $this->query(func_get_args())->get_row();
		}
		catch (\PDOException $e ){
			self::error($e,$this->last_query);
		}
	}

	/**
	 * @return array | false
	 */
	public function get_rows()
	{
		try{
			return $this->query(func_get_args())->get_rows();
		}
		catch (\PDOException $e ){
			self::error($e,$this->last_query);
		}
	}

	/**
	 * @return array | false
	 */
	public function get_irow()
	{
		try{
			return $this->query(func_get_args())->get_irow();
		}
		catch (\PDOException $e ){
			self::error($e,$this->last_query);
		}
	}

	/**
	 * @return array | false
	 */
	public function get_irows()
	{
		try{
			return $this->query(func_get_args())->get_irows();
		}
		catch (\PDOException $e ){
			self::error($e,$this->last_query);
		}
	}

	/**
	 * @return array | false 
	 */
	public function get_columns()
	{
		try{
			return $this->query(func_get_args())->get_columns();
		}
		catch (\PDOException $e ){
			self::error($e,$this->last_query);
		}
	}

	/**
	 * @return void 
	 */
	public function get_obj()
	{
		return $this->get_object(func_get_args());
	}

	/**
	 * @return void 
	 */
	public function get_object()
	{
		try{
			return $this->query(func_get_args())->get_object();
		}
		catch (\PDOException $e ){
			self::error($e,$this->last_query);
		}
	}

	/**
	 * @return void 
	 */
	public function get_objects()
	{
		try{
			return $this->query(func_get_args())->get_objects();
		}
		catch (\PDOException $e ){
			self::error($e,$this->last_query);
		}
	}

	/**
	 * @return database chainable 
	 */
	public function disable_keys()
	{
		$this->query("SET FOREIGN_KEY_CHECKS=0;");
		return $this;
	}

	/**
	 * @return database chainable
	 */
	public function enable_keys()
	{
		$this->query("SET FOREIGN_KEY_CHECKS=1;");
		return $this;
	}
	
	/**
	 * @return void 
	 */
	public function select($table, $fields = array(), $where = array(), $order = false, $limit = 500, $start = 0)
	{
		$hash = md5('select'.$table.serialize(array_keys($fields)).serialize($where).( $order ? 1 : '0' ).$limit);
		if ( isset($this->hashes[$hash]) ){
			$sql = $this->hashes[$hash]['statement'];
		}
		else{
			$sql = $this->get_select($table, $fields, array_keys($where), $order, $limit);
		}
		if ( $sql ){
			try{
				if ( count($where) > 0 ){
					$r = $this->query($sql, $hash, array_values($where));
				}
				else{
					$r = $this->query($sql, $hash);
				}
				if ( $r ){
					return $r->get_objects();
				}
			}
			catch (\PDOException $e ){
				self::error($e,$this->last_query);
			}
		}
	}

	/**
	 * @return void 
	 */
	public function insert($table, array $values, $ignore = false)
	{
		$hash = md5('insert'.$table.serialize(array_keys($values)).$ignore);
		if ( isset($this->hashes[$hash]) ){
			$sql = $this->hashes[$hash]['statement'];
		}
		else{
			$sql = $this->get_insert($table, array_keys($values), $ignore);
		}
		if ( $sql ){
			try{
				return $this->query($sql, $hash, array_values($values));
			}
			catch (\PDOException $e ){
				self::error($e,$this->last_query);
			}
		}
	}
	
	/**
	 * @return void 
	 */
	public function insert_update($table, array $values)
	{
		$hash = md5('insert_update'.$table.serialize(array_keys($values)));
		if ( isset($this->hashes[$hash]) ){
			$sql = $this->hashes[$hash]['statement'];
			if ( $this->queries[$sql]['num_val'] === ( count($values) / 2 ) ){
				$vals = array_merge(array_values($values),array_values($values));;
			}
			else{
				$vals = array_values($values);
			}
		}
		else if ( $sql = $this->get_insert($table, array_keys($values)) ){
			$sql .= " ON DUPLICATE KEY UPDATE ";
			$vals = array_values($values);
			foreach ( $values as $k => $v ){
				$sql .= "`$k` = ?, ";
				array_push($vals, $v);
			}
			$sql = substr($sql,0,strrpos($sql,','));
		}
		if ( $sql ){
			try{
				return $this->query($sql, $hash, $vals);
			}
			catch (\PDOException $e ){
				self::error($e,$this->last_query);
			}
		}
		return false;
	}

	/**
	 * @return void 
	 */
	public function update($table, array $values, array $where)
	{
		$hash = md5('insert_update'.$table.serialize(array_keys($values)).serialize(array_keys($where)));
		if ( isset($this->hashes[$hash]) ){
			$sql = $this->hashes[$hash]['statement'];
		}
		else{
			$sql = $this->get_update($table, array_keys($values), array_keys($where));
		}
		if ( $sql ){
			try{
				return $this->query($sql, $hash, array_merge(array_values($values), array_values($where)));
			}
			catch (\PDOException $e ){
				self::error($e,$this->last_query);
			}
		}
		return false;
	}

	/**
	 * @return void 
	 */
	public function delete($table, array $where)
	{
		$hash = md5('delete'.$table.serialize(array_keys($where)));
		if ( isset($this->hashes[$hash]) ){
			$sql = $this->hashes[$hash]['statement'];
		}
		else{
			$sql = $this->get_delete($table, array_keys($where));
		}
		if ( $sql ){
			try{
				return $this->query($sql, $hash, array_values($where));
			}
			catch (\PDOException $e ){
				self::error($e,$this->last_query);
			}
		}
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
		$table = explode(".",$table);
		if ( count($table) === 2 ){
			$db = trim($table[0]);
			$table = trim($table[1]);
		}
		else{
			$db = $this->current;
			$table = trim($table[0]);
		}
		if ( empty($table) || $table === '*' ){
			$tables = $this->get_tables($db);
		}
		else if ( is_string($table) && text::check_name($table) ){
			$tables = array($table);
		}
		else if ( is_array($table) ){
			$tables = $table;
		}
		if ( is_array($tables) ){
			foreach ( $tables as $t ){
				$keys = $this->get_keys($db.'.'.$t);
				$r[$t] = array(
					'fields' => $this->get_fields($db.'.'.$t),
					'keys' => $keys['keys']
				);
				foreach ( $r[$t]['fields'] as $i => $f ){
					if ( isset($keys['cols'][$i]) ){
						$r[$t]['fields'][$i]['keys'] = $keys['cols'][$i];
					}
				}
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
		$x = array_filter($this->get_rows("SHOW DATABASES"),function($a){
			return ( $a['Database'] === 'information_schema' ) || ( $a['Database'] === 'mysql' ) ? false : 1;
		});
		sort($x);
		return $x;
	}

	/**
	 * @return array | false
	 */
	public function get_tables($database='')
	{
		$arch = array();
		if ( empty($database) || !text::check_name($database) ){
			$database = $this->current;
		}
		$t2 = array();
		if ( $t1 = $this->get_irows("SHOW TABLES FROM `$database`") ){
			foreach ( $t1 as $t ){
				array_push($t2, $t[0]);
			}
		}
		return $t2;
	}
	/**
	 * @return array | false
	 */
	public function get_fields($table)
	{
		$table = explode(".",$table);
		if ( count($table) === 2 ){
			$db = trim($table[0]);
			$table = trim($table[1]);
		}
		else{
			$db = $this->current;
			$table = trim($table[0]);
		}
		if ( text::check_name($db, $table) ){
			$rows = $this->get_rows("SHOW COLUMNS FROM `$db`.`$table`");
			$p = 1;
			foreach ( $rows as $row ){
				$f = $row['Field'];
				$r[$f] = array(
					'position' => $p++,
					'null' => $row['Null'] === 'NO' ? 0 : 1,
					'key' => in_array($row['Key'], array('PRI', 'UNI', 'MUL')) ? $row['Key'] : null,
					'default' => is_null($row['Default']) && $row['Null'] !== 'NO' ? 'NULL' : $row['Default'],
					'extra' => $row['Extra'],
					'maxlength' => 0
				);
				if ( strpos($row['Type'], 'enum') === 0 ){
					$r[$f]['type'] = 'enum';
					if ( preg_match_all('/\((.*?)\)/', $row['Type'], $matches) ){
						$r[$f]['extra'] = $matches[1][0];
					}
				}
				else{
					if ( strpos($row['Type'], 'unsigned') ){
						$r[$f]['signed'] = 0;
						$row['Type'] = trim(str_replace('unsigned','',$row['Type']));
					}
					else{
						$r[$f]['signed'] = 1;
					}
					if ( strpos($row['Type'],'text') !== false ){
						$r[$f]['type'] = 'text';
					}
					else if ( strpos($row['Type'],'blob') !== false ){
						$r[$f]['type'] = 'blob';
					}
					else if ( strpos($row['Type'],'int(') !== false ){
						$r[$f]['type'] = 'int';
					}
					else if ( strpos($row['Type'],'char(') !== false ){
						$r[$f]['type'] = 'varchar';
					}
					if ( preg_match_all('/\((.*?)\)/', $row['Type'], $matches) ){
						$r[$f]['maxlength'] = $matches[1][0];
					}
					if ( !isset($r[$f]['type']) ){
						$r[$f]['type'] = ( strpos($row['Type'], '(') ) ? substr($row['Type'],0,strpos($row['Type'], '(')) : $row['Type'];
					}
					
				}
				
			}
		}
		return $r;
	}
	
	/**
	 * @return string
	 */
	public function get_keys($table)
	{
		$table = explode(".",$table);
		if ( count($table) === 2 ){
			$db = trim($table[0]);
			$table = trim($table[1]);
		}
		else{
			$db = $this->current;
			$table = trim($table[0]);
		}
		if ( text::check_name($db, $table) ){
			$b = $this->get_rows("SHOW INDEX FROM `$db`.`$table`");
			$keys = array();
			$cols = array();
			foreach ( $b as $i => $d ){
				$a = $this->get_row("
				SELECT `ORDINAL_POSITION` as `position`,
				`REFERENCED_TABLE_SCHEMA` as `ref_db`, `REFERENCED_TABLE_NAME` as `ref_table`, `REFERENCED_COLUMN_NAME` as `ref_column`
				FROM `information_schema`.`KEY_COLUMN_USAGE`
				WHERE `TABLE_SCHEMA` LIKE '$db'
				AND `TABLE_NAME` LIKE '$table'
				AND `COLUMN_NAME` LIKE '$d[Column_name]'
				AND ( `CONSTRAINT_NAME` LIKE '$d[Key_name]' OR ORDINAL_POSITION = $d[Seq_in_index] OR 1 )
				LIMIT 1");
				
				if ( !isset($keys[$d['Key_name']]) ){
					$keys[$d['Key_name']] = array(
					'columns' => array($d['Column_name']),
					'ref_db' => $a ? $a['ref_db'] : null,
					'ref_table' => $a ? $a['ref_table'] : null,
					'ref_column' => $a ? $a['ref_column'] : null,
					'unique' => $d['Non_unique'] == 1 ? 1 : 0
					);
				}
				else{
					array_push($keys[$d['Key_name']]['columns'], $d['Column_name']);
				}
				if ( !isset($cols[$d['Column_name']]) ){
					$cols[$d['Column_name']] = array($d['Key_name']);
				}
				else{
					array_push($cols[$d['Column_name']], $d['Key_name']);
				}
			}
			return array('keys'=>$keys, 'cols'=>$cols);
		}
	}
	
	/**
	 * @return string | false
	 */
	public function get_create($table)
	{
		$table = explode(".",$table);
		if ( count($table) === 2 ){
			$db = trim($table[0]);
			$table = trim($table[1]);
		}
		else{
			$db = $this->current;
			$table = trim($table[0]);
		}
		if ( text::check_name($db, $table) && $r = $this->get_row("SHOW CREATE TABLE `$db`.`$table`") ){
			return $r['Create Table'];
		}
		return false;
	}
	
	/**
	 * @return string | false
	 */
	public function get_delete($table, array $where)
	{
		$table = explode(".",$table);
		if ( count($table) === 2 ){
			$db = trim($table[0]);
			$table = trim($table[1]);
		}
		else{
			$db = $this->current;
			$table = trim($table[0]);
		}
		if ( text::check_name($db, $table) && ( $m = $this->modelize($table) ) && count($m['fields']) > 0 && count($where) > 0 ){
			$r = "DELETE FROM `$db`.`$table` WHERE 1 ";

			foreach ( $where as $f ){
				if ( !isset($m['fields'][$f]) ){
					die("The fields to search for in get_delete don't correspond to the table");
				}
				$r .= "\nAND `$f` ";
				if ( stripos($m['fields'][$f]['type'],'int') !== false ){
					$r .= "= %u ";
				}
				else{
					$r .= "= %s ";
				}
			}
			return $r;
		}
		return false;
	}

	/**
	 * @return string
	 */
	public function get_select($table, $fields = array(), $where = array(), $order = array(), $limit = false, $start = 0, $php = false)
	{
		$table = explode(".",$table);
		if ( count($table) === 2 ){
			$db = trim($table[0]);
			$table = trim($table[1]);
		}
		else{
			$db = $this->current;
			$table = trim($table[0]);
		}
		if ( text::check_name($db, $table) && ( $m = $this->modelize($table) ) && count($m['fields']) > 0 )
		{
			$r = '';
			if ( $php ){
				$r .= '$db->query("';
			}
			$r .= "SELECT \n";
			if ( count($fields) > 0 ){
				foreach ( $fields as $k ){
					if ( !isset($m['fields'][$k]) ){
						die("The column $k doesn't exist in $table");
					}
					else{
						$r .= "`$k`,\n";
					}
				}
			}
			else{
				foreach ( array_keys($m['fields']) as $k ){
					$r .= "`$k`,\n";
				}
			}
			$r = substr($r,0,strrpos($r,','))."\nFROM `$db`.`$table`";
			if ( count($where) > 0 ){
				$r .= "\nWHERE 1 ";
				foreach ( $where as $f ){
					if ( !isset($m['fields'][$f]) ){
						die("The field $f to search for in get_select don't correspond to the table");
					}
					$r .= "\nAND `$f` ";
					if ( stripos($m['fields'][$f]['type'],'int') !== false ){
						$r .= "= %u";
					}
					else{
						$r .= "= %s";
					}
				}
			}
			$directions = ['desc', 'asc'];
			if ( is_string($order) ){
				$order = [$order];
			}
			if ( is_array($order) && count($order) > 0 ){
				$r .= "\nORDER BY ";
				foreach ( $order as $col => $direction ){
					if ( is_numeric($col) && isset($m['fields'][$direction]) ){
						$r .= "`$direction` ".( stripos($m['fields'][$direction]['type'],'date') !== false ? 'DESC' : 'ASC' ).",\n";
					}
					else if ( isset($m['fields'][$col])  ){
						$r .= "`$col` ".( strtolower($direction) === 'desc' ? 'DESC' : 'ASC' ).",\n";
					}
				}
				$r = substr($r,0,strrpos($r,','));
			}
			if ( $limit && is_numeric($limit) && is_numeric($start) ){
				$r .= "\nLIMIT $start, $limit";
			}
			if ( $php ){
				$r .= '")';
			}
			return $r;
		}
		return false;
	}
	
	/**
	 * @return string
	 */
	public function get_insert($table, $fields = array(), $ignore = false, $php = false)
	{
		$r = '';
		if ( $php ){
			$r .= '$db->query("';
		}
		$table = explode(".",$table);
		if ( count($table) === 2 ){
			$db = trim($table[0]);
			$table = trim($table[1]);
		}
		else{
			$db = $this->current;
			$table = trim($table[0]);
		}
		if ( text::check_name($db, $table) && ( $m = $this->modelize($table) ) && count($m['fields']) > 0 )
		{
			$r .= "INSERT ";
			if ( $ignore ){
				$r .= "IGNORE ";
			}
			$r .= "INTO `$db`.`$table` (\n";
			$i = 0;
			
			if ( count($fields) > 0 ){
				foreach ( $fields as $k ){
					if ( !isset($m['fields'][$k]) ){
						die("The column $k doesn't exist in $table");
					}
					else{
						$r .= "`$k`, ";
						$i++;
						if ( $i % 4 === 0 ){
							$r .= "\n";
						}
					}
				}
			}
			else{
				foreach ( array_keys($m['fields']) as $k ){
					$r .= "`$k`, ";
					$i++;
					if ( $i % 4 === 0 ){
						$r .= "\n";
					}
				}
			}
			$r = substr($r,0,strrpos($r,',')).")\nVALUES (\n";
			$i = 0;
			if ( count($fields) > 0 ){
				foreach ( $fields as $k ){
					if ( stripos($m['fields'][$k]['type'],'INT') !== false ){
						$r .= "%u, ";
					}
					else{
						$r .= "%s, ";
					}
					$i++;
					if ( $i % 4 === 0 ){
						$r .= "\n";
					}
				}
			}
			else{
				foreach ( $m['fields'] as $k => $f ){
					if ( stripos($f['type'],'INT') !== false ){
						$r .= "%u, ";
					}
					else{
						$r .= "%s, ";
					}
					$i++;
					if ( $i % 4 === 0 ){
						$r .= "\n";
					}
				}
			}
			$r = substr($r,0,strrpos($r,',')).')';
			if ( $php ){
				$r .= "\",\n";
				$i = 0;
				foreach ( array_keys($m['fields']) as $k ){
					$r .= "\$d['$k'], ";
					$i++;
					if ( $i % 4 === 0 ){
						$r .= "\n";
					}
				}
				$r = substr($r,0,strrpos($r,',')).');';
			}
			return $r;
		}
		return false;
	}
	
	/**
	 * @return string
	 */
	public function get_update($table, $fields = array(), $where = array(), $php = false)
	{
		$r = '';
		if ( $php ){
			$r .= '$db->query("';
		}
		$table = explode(".",$table);
		if ( count($table) === 2 ){
			$db = trim($table[0]);
			$table = trim($table[1]);
		}
		else{
			$db = $this->current;
			$table = trim($table[0]);
		}
		if ( text::check_name($db, $table) && ( $m = $this->modelize($table) ) && count($m['fields']) > 0 )
		{
			if ( is_string($where) ){
				$where = array($where);
			}
			$r .= "UPDATE `$db`.`$table` SET ";
			$i = 0;

			if ( count($fields) > 0 ){
				foreach ( $fields as $k ){
					if ( !isset($m['fields'][$k]) ){
						die("The column $k doesn't exist in $table");
					}
					else{
						$r .= "`$k` = ";
						if ( stripos($m['fields'][$k]['type'],'int') !== false ){
							$r .= "%u";
						}
						else{
							$r .= "%s";
						}
						$r .= ",\n";
					}
				}
			}
			else{
				foreach ( array_keys($m['fields']) as $k ){
					$r .= "`$k` = ";
					if ( stripos($m['fields'][$k]['type'],'int') !== false ){
						$r .= "%u";
					}
					else{
						$r .= "%s";
					}
					$r .= ",\n";
				}
			}

			$r = substr($r,0,strrpos($r,','))."\nWHERE 1 ";
			foreach ( $where as $f ){
				if ( !isset($m['fields'][$f]) ){
					die("The fields to search for in get_update don't correspond to the table");
				}
				$r .= "\nAND `$f` ";
				if ( stripos($m['fields'][$f]['type'],'int') !== false ){
					$r .= "= %u ";
				}
				else{
					$r .= "= %s ";
				}
			}

			if ( $php ){
				$r .= "\",\n";
				$i = 0;
				foreach ( array_keys($m['fields']) as $k ){
					if ( !in_array($k, $where) && ( count($fields) === 0 || in_array($k,$fields) ) ){
						$r .= "\$d['$k'],\n";
					}
				}
				foreach ( $where as $f ){
					$r .= "\$d['$f'],\n";
				}
				$r = substr($r,0,strrpos($r,',')).');';
			}
			return $r;
		}
		return false;
	}
	
	/**
	 * @return void 
	 */
	public function create_db_index($table, $column, $db='', $sys='mysql')
	{
		$table = explode(".",$table);
		if ( count($table) === 2 ){
			$db = trim($table[0]);
			$table = trim($table[1]);
		}
		else{
			$db = $this->current;
			$table = trim($table[0]);
		}
		if ( text::check_name($db, $table, $column) ){
			$this->query("
			ALTER TABLE `$db`.`$table`
			ADD INDEX `$column`");
		}
		return $this;
	}
	
	/**
	 * @return void 
	 */
	public function delete_db_index($table, $column, $sys='mysql')
	{
		$table = explode(".",$table);
		if ( count($table) === 2 ){
			$db = trim($table[0]);
			$table = trim($table[1]);
		}
		else{
			$db = $this->current;
			$table = trim($table[0]);
		}
		if ( text::check_name($db, $table, $column) ){
			$this->query("
				ALTER TABLE `$db`.`$table`
				DROP INDEX `$column`");
		}
		return $this;
	}
	
	/**
	 * @return void 
	 */
	public function create_db_user($user, $pass, $db, $sys='mysql', $host='localhost')
	{
		if ( text::check_name($user, $db) && strpos($pass, "'") === false ){
			$this->query("
				GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,DROP,INDEX,ALTER
				ON `$db` . *
				TO '$user'@'$host'
				IDENTIFIED BY '$pass'");
		}
	}
	
	/**
	 * @return void 
	 */
	public function delete_db_user($user, $sys='mysql')
	{
		if ( text::check_name($user) ){
			$this->query("
			REVOKE ALL PRIVILEGES ON *.* 
			FROM $user");
			$this->query("DROP USER $user");
		}
		return $this;
	}
	
}
?>