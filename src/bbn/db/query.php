<?php
/**
 * @package db
 */
namespace bbn\db;
use bbn;
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
  private static $return_sequences = ["SELECT", "SHOW", "PRAGMA", "UNION"];
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
	protected function __construct($db)
	{
		if ( !empty($this->queryString) )
		{
			$this->db = $db;
			$this->sequences = $this->db->last_params['sequences'];
			$this->values = isset($this->db->last_params['values']) ? $this->db->last_params['values'] : [];
		}
	}
  
  private function does_return(){
    foreach ( self::$return_sequences as $rs ){
      if ( isset($this->sequences[$rs]) ){
        return true;
      }
    }
    return false;
  }
	
	/**
	 * @return $this 
	 */
	public function init($values=array())
	{
		$this->values = $values;
		$this->res = null;
		$this->num = null;
		return $this;
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
					$this->db->error($e);
				}
			}
			else if ( !is_null($args) )
			{
				$args = func_get_args();
				try{
					return eval( 'return parent::execute( $args );' );
				}
				catch ( \PDOException $e ){
					$this->db->error($e);
				}
			}
			else
			{
				if ( isset($this->values) && is_array($this->values) ){
          foreach ( $this->values as $i => $v )
          {
            if ( is_int ($v) ){
              $param = \PDO::PARAM_INT;
            }
            else if ( is_bool($v) ){
              $param = \PDO::PARAM_BOOL;
            }
            else if ( is_null($v) ){
              $param = \PDO::PARAM_NULL;
            }
            else{
              $param = \PDO::PARAM_STR;
            }
            $this->bindValue($i+1, $v, $param);
          }
        }
				try{
					return parent::execute();
				}
				catch ( \PDOException $e ){
					$this->db->error($e);
				}
			}
		}
		return false;
	}

	/**
	 * @return void 
	 */
	public function count()
	{
		if ( $this->num === null )
		{
			$this->num = 0;
			if ( isset($this->sequences['SELECT']) || isset($this->sequences['UNION']) )
			{
				$s = $this->sequences;
				$queries = [];
				if ( isset($s['UNION']) ){
					foreach ( $s['UNION'] as $k => $un ){
						if ( isset($un['SELECT']) ){
							array_push($queries, $un);
						}
					}
				}
				else{
					array_push($queries, $s);
				}
				$start_value = 0;
				foreach ( $queries as $qr ){
					$qr['SELECT'] = [
						[
							'expr_type' => 'aggregate_function',
							'alias' => '',
							'base_expr' => 'COUNT',
							'sub_tree' => [
								[
									'expr_type' => 'colref',
									'base_expr' => '*'
								]
							]
						]
					];
					if ( isset($qr['ORDER']) ){
						unset($qr['ORDER']);
					}
					if ( isset($qr['LIMIT']) ){
						unset($qr['LIMIT']);
					}
					$num_values = 0;
					foreach ( $qr as $qr2 ){
						foreach ( $qr2 as $qr3 ){
							if ( isset($qr3['base_expr']) && $qr3['base_expr'] === '?' ){
								$num_values++;
							}
						}
					}
					$sql = $this->db->create_query($qr);
          $this->db->add_statement($sql);
					try
					{
						$q = $this->db->prepare($sql);
						if ( !empty($this->values) && is_array($this->values) && count($this->values) > 0 ){
							$v = $this->values;
							$q->values = array_splice($v, $start_value, $num_values);
							$start_value += $num_values;
						}
						if ( $q->execute() ){
							$this->num += (int)$q->fetchColumn();
						}
						/* In case there is some group by that split the results, we request the full set of results
							$n = count($q->fetchAll());
							if ( $n > $this->num && $this->num > 0 ){
							$this->num = $n + 1;
							}
						 */
					}
					catch ( \PDOException $e )
					{ $this->db->error($e); }
				}
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
		return bbn\str::correct_types(parent::fetch( $fetch_style, $cursor_orientation, $cursor_offset ));
	}

	/**
	 * @return void 
	 */
	public function fetchAll($fetch_style=\PDO::FETCH_BOTH, $fetch_argument=false, $ctor_args=false)
	{
		$this->execute();
		if ( $ctor_args ){
			$res = parent::fetchAll($fetch_style,$fetch_argument,$ctor_args);
    }
		else if ( $fetch_argument ){
			$res = parent::fetchAll($fetch_style,$fetch_argument);
    }
		else{
			$res = parent::fetchAll($fetch_style);
    }
    return bbn\str::correct_types($res);
	}

	/**
	 * @return void 
	 */
	public function fetchColumn($column_number=0)
	{
		$this->execute();
		return bbn\str::correct_types(parent::fetchColumn($column_number));
	}

	/**
	 * @return void 
	 */
	public function fetchObject($class_name="stdClass", $ctor_args=array())
	{
		$this->execute();
		return bbn\str::correct_types(parent::fetchObject($class_name,$ctor_args));
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
	 * @return array|boolean
	 */
	public function get_row()
	{
		if ( $this->does_return() ){
			return $this->fetch(\PDO::FETCH_ASSOC);
    }
		return false;
	}

	/**
	 * @return void 
	 */
	public function get_rows()
	{
		if ( $this->does_return() ){
			return $this->fetchAll(\PDO::FETCH_ASSOC);
    }
		return false;
	}

	/**
	 * @return array 
	 */
	public function get_by_columns()
	{
    $r = [];
		if ( $this->does_return() ){
			$ds = $this->fetchAll(\PDO::FETCH_ASSOC);
			foreach ( $ds as $d ){
				foreach ( $d as $k => $v ){
					if ( !isset($r[$k]) ){
						$r[$k] = [];
          }
					array_push($r[$k],$v);
				}
			}
    }
    return $r;
	}

	/**
	 * @return void 
	 */
	public function get_objects()
	{
		if ( $this->does_return() )
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
		if ( $this->does_return() )
			return $this->fetch(\PDO::FETCH_OBJ);
		return false;
	}

	/**
	 * @return void 
	 */
	public function get_irow()
	{
		if ( isset($this->sequences['SELECT']) || isset($this->sequences['SHOW']) )
			return $this->fetch(\PDO::FETCH_NUM);
		return false;
	}

	/**
	 * @return void 
	 */
	public function get_irows()
	{
		if ( isset($this->sequences['SELECT']) || isset($this->sequences['SHOW']) )
			return $this->fetchAll(\PDO::FETCH_NUM);
		return false;
	}

}
?>