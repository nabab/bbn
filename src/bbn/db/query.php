<?php
/**
 * @package bbn\db
 */
namespace bbn\db;
/**
 * An extended approach of the PDOStatement object
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  	Database
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 * @todo Get the count function to work with query with "HAVING"
 */
class query extends \PDOStatement implements actions
{
	/**
	 * @var mixed
	 */
	private $db;

	/**
	 * @var mixed
	 */
	private $res;

	/**
	 * @var mixed
	 */
	private $num;

	/**
	 * @var mixed
	 */
	private $sequences;

	/**
	 * @var mixed
	 */
	private $values;


	/**
	 * @param PDO $db
	 * @param array $seq
	 * @param array $values
	 * @return void 
	 */
	protected function __construct($db, $seq, $values=array())
	{
		if ( !empty($this->queryString) )
		{
			$this->db = $db;
			$this->sequences = $seq;
			$this->values = $values;
		}
	}

	/**
	 * @param array|null $args
	 * @return void 
	 */
	public function execute($args=null)
	{
		if ( $this->res === null || $args !== null )
		{
			$this->res = 1;
			if ( is_array( $args ) ){
				try{
					return parent::execute($args);
				}
				catch ( \PDOException $e ){
					database::error($e,$this->queryString);
				}
			}
			else if ( !is_null($args) )
			{
				$args = func_get_args();
				try{
					return eval( 'return parent::execute( $args );' );
				}
				catch ( \PDOException $e ){
					database::error($e,$this->queryString);
				}
			}
			else
			{
				foreach ( $this->values as $i => $v )
				{
					if ( $v[1] == 'u' ){
						$this->bindValue($i+1,$v[0],\PDO::PARAM_INT);
					}
					else{
						$this->bindValue($i+1,$v[0],\PDO::PARAM_STR);
					}
				}
				try{
					return parent::execute();
				}
				catch ( \PDOException $e ){
					database::error($e,$this->queryString);
				}
			}
		}
		return $this->res;
	}

	/**
	 * @return void 
	 */
	public function count()
	{
		if ( $this->num === null )
		{
			$this->num = false;
			if ( isset($this->sequences['select']) || isset($this->sequences['show']) )
			{
				$sql = parser::ParseString($this->queryString)->getCountQuery();
				try
				{
					$q = $this->db->prepare($sql);
					$q->values = $this->values;
					if ( $q->execute() )
						$this->num = $q->fetchColumn();
					/* In case there is some group by that split the results, we request the full set of results */
					$n = count($q->fetchAll());
					if ( $n > $this->num && $this->num > 0 )
						$this->num = $n + 1;
				}
				catch ( \PDOException $e )
					{ database::error($e,$this->queryString); }
			}
		}
		return $this->num;
	}

	/**
	 * @return void 
	 */
	public function columnCount()
	{
		$this->execute();
		return parent::columnCount();
	}

	/**
	 * @return void 
	 */
	public function fetch($fetch_style=\PDO::FETCH_BOTH, $cursor_orientation=\PDO::FETCH_ORI_NEXT, $cursor_offset=0)
	{
		$this->execute();
		$res = parent::fetch( $fetch_style, $cursor_orientation, $cursor_offset );
		if ( is_array($res) )
		{
			foreach ( $res as $i => $r )
			{
				if ( is_string($r) && is_numeric($r) )
					$res[$i] += 0;
			}
		}
		return $res;
	}

	/**
	 * @return void 
	 */
	public function fetchAll($fetch_style=\PDO::FETCH_BOTH, $fetch_argument=false, $ctor_args=false)
	{
		$this->execute();
		if ( $ctor_args )
			$res = parent::fetchAll($fetch_style,$fetch_argument,$ctor_args);
		else if ( $fetch_argument )
			$res = parent::fetchAll($fetch_style,$fetch_argument);
		else
			$res = parent::fetchAll($fetch_style);
		if ( is_array($res) )
		{
			foreach ( $res as $i => $rs )
			{
				if ( is_array($rs) )
				{
					foreach ( $rs as $j => $r )
					{
						if ( is_string($r) && is_numeric($r) )
							$res[$i][$j] += 0;
					}
				}
			}
		}
		return $res;
	}

	/**
	 * @return void 
	 */
	public function fetchColumn($column_number=0)
	{
		$this->execute();
		$r = parent::fetchColumn($column_number);
		return $r;
	}

	/**
	 * @return void 
	 */
	public function fetchObject($class_name="stdClass", $ctor_args=array())
	{
		$this->execute();
		$res = parent::fetchObject($class_name,$ctor_args);
		foreach ( $res as $i => $rs )
		{
			if ( is_string($res[$i]) && is_numeric($res[$i]) )
				$res->$i += 0;
		}
		return $res;
	}

	/**
	 * @return void 
	 */
	public function rowCount()
	{
		$this->execute();
		return parent::rowCount();
	}

	/**
	 * @return void 
	 */
	public function getColumnMeta($column=0)
	{
		$this->execute();
		return parent::getColumnMeta($column);
	}

	/**
	 * @return void 
	 */
	public function nextRowset()
	{
		$this->execute();
		return parent::nextRowset();
	}

	/**
	 * @return void 
	 */
	public function get_row()
	{
		if ( isset($this->sequences['select']) || isset($this->sequences['show']) )
			return $this->fetch(\PDO::FETCH_ASSOC);
		return false;
	}

	/**
	 * @return void 
	 */
	public function get_rows()
	{
		if ( isset($this->sequences['select']) || isset($this->sequences['show']) )
			return $this->fetchAll(\PDO::FETCH_ASSOC);
		return false;
	}

	/**
	 * @return void 
	 */
	public function get_columns()
	{
		if ( isset($this->sequences['select']) || isset($this->sequences['show']) )
		{
			$r = array();
			$ds = $this->fetchAll(\PDO::FETCH_ASSOC);
			foreach ( $ds as $d )
			{
				foreach ( $d as $k => $v )
				{
					if ( !isset($r[$k]) )
						$r[$k] = array();
					array_push($r[$k],$v);
				}
			}
			return $r;
		}
		return false;
	}

	/**
	 * @return void 
	 */
	public function get_objects()
	{
		if ( isset($this->sequences['select']) || isset($this->sequences['show']) )
			return $this->fetchAll(\PDO::FETCH_OBJ);
		return false;
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
		if ( isset($this->sequences['select']) || isset($this->sequences['show']) )
			return $this->fetch(\PDO::FETCH_OBJ);
		return false;
	}

	/**
	 * @return void 
	 */
	public function get_irow()
	{
		if ( isset($this->sequences['select']) || isset($this->sequences['show']) )
			return $this->fetch(\PDO::FETCH_NUM);
		return false;
	}

	/**
	 * @return void 
	 */
	public function get_irows()
	{
		if ( isset($this->sequences['select']) || isset($this->sequences['show']) )
			return $this->fetchAll(\PDO::FETCH_NUM);
		return false;
	}

}
?>