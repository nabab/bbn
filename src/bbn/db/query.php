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
	/**
	 * @var mixed
	 */
	private $db;

  /**
   * @var mixed
   */
  private $res;

  /**
   * @var array
   */
  protected $values;

  /**
   * @var bool
   */
  protected $write;

  /**
   * @var bool
   */
  protected $structure;

  /**
   * @var bool
   */
  protected $union;

  /**
	 * @param bbn\db $db
	 */
	protected function __construct(bbn\db $db)
	{
		if ( !empty($this->queryString) )
		{
			$this->db = $db;
      $last = $this->db->get_real_last_params();
			$this->values = $last['values'] ?? [];
      $this->write = $last['write'] ?? false;
      $this->structure = $last['structure'] ?? [];
		}
	}
  
	/**
   * @param array $values
	 * @return self
	 */
	public function init(array $values = []): self
	{
		$this->values = $values;
		$this->res = null;
		return $this;
	}

	/**
	 * @param array|null $args
	 * @return bool
	 */
	public function execute($args = null): ?bool
	{
		if ( ($this->res === null) || ($args !== null) ){
			$this->res = 1;
			if ( \is_array($args) ){
				try{
					return parent::execute($args);
				}
				catch ( \PDOException $e ){
					$this->db->error($e);
				}
			}
			else if ( $args !== null ){
				$args = \func_get_args();
				try{
					return parent::execute(...$args);
				}
				catch ( \PDOException $e ){
					$this->db->error($e);
				}
			}
			else{
				if ( $this->values && \is_array($this->values) && count($this->values) ){
          foreach ( $this->values as $i => $v ){
            if ( bbn\str::is_buid($v) ){
              $this->bindValue($i+1, $v);
            }
            else{
              if ( \is_int ($v) ){
                $param = \PDO::PARAM_INT;
              }
              else if ( \is_bool($v) ){
                $param = \PDO::PARAM_BOOL;
              }
              else if ( $v === null ){
                $param = \PDO::PARAM_NULL;
              }
              else{
                $param = \PDO::PARAM_STR;
              }
              $this->bindValue($i+1, $v, $param);
            }
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
		return null;
	}

	/**
	 * @return int
	 */
	public function columnCount(): int
	{
		$this->execute();
		return parent::columnCount();
	}

  /**
   * @param int $how
   * @param int $orientation
   * @param int $offset
   * @return mixed
   */
	public function fetch($how = null, $orientation = null, $offset = null)
	{
	  if ( $how === null ){
	    $how = \PDO::FETCH_BOTH;
    }
		$this->execute();
		return bbn\str::correct_types(parent::fetch($how, $orientation, $offset));
	}

  /**
   * @param int $fetch_style
   * @param bool $fetch_argument
   * @param bool $ctor_args
   * @return bool|array
   */
	public function fetchAll($fetch_style = \PDO::FETCH_BOTH, $fetch_argument = false, $ctor_args = false)
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
   * @param int $column_number
   * @return mixed
   */
	public function fetchColumn($column_number = 0)
	{
		$this->execute();
		return bbn\str::correct_types(parent::fetchColumn($column_number));
	}

  /**
   * @param string $class_name
   * @param array $ctor_args
   * @return mixed
   */
	public function fetchObject($class_name = 'stdClass', $ctor_args = [])
	{
		$this->execute();
		return bbn\str::correct_types(parent::fetchObject($class_name,$ctor_args));
	}

	/**
	 * @return int
	 */
	public function rowCount(): int
	{
		$this->execute();
		return parent::rowCount();
	}

  /**
   * @param int $column
   * @return array
   */
	public function getColumnMeta($column=0): array
	{
		$this->execute();
		return parent::getColumnMeta($column);
	}

	/**
	 * @return bool
	 */
	public function nextRowset(): bool
	{
		$this->execute();
		return parent::nextRowset();
	}

	/**
	 * @return array|boolean
	 */
	public function get_row(): ?array
	{
    if ( !$this->write ){
			return $this->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
		return null;
	}

	/**
	 * @return null|array
	 */
	public function get_rows(): ?array
	{
    if ( !$this->write ){
			$r = $this->fetchAll(\PDO::FETCH_ASSOC);
			return $r === false ? null : $r;
    }
		return null;
	}

  /**
   * @return null|array
   */
  public function get_irow(): ?array
  {
    if ( !$this->write ){
      return $this->fetch(\PDO::FETCH_NUM) ?: null;
    }
    return null;
  }

  /**
   * @return null|array
   */
  public function get_irows(): ?array
  {
    if ( !$this->write ){
      return $this->fetchAll(\PDO::FETCH_NUM);
    }
    return null;
  }

  /**
	 * @return null|array
	 */
	public function get_by_columns(): ?array
	{
    if ( !$this->write ){
      $r = [];
			$ds = $this->fetchAll(\PDO::FETCH_ASSOC);
			foreach ( $ds as $d ){
				foreach ( $d as $k => $v ){
					if ( !isset($r[$k]) ){
						$r[$k] = [];
          }
					$r[$k][] = $v;
				}
			}
      return $r;
    }
    return null;
	}

	/**
	 * @return null|\stdClass
	 */
	public function get_obj(): ?\stdClass
	{
		return $this->get_object(...\func_get_args());
	}

	/**
	 * @return null|\stdClass
	 */
	public function get_object(): ?\stdClass
	{
    if ( !$this->write ){
      return $this->fetch(\PDO::FETCH_OBJ) ?: null;
    }
		return null;
	}

  /**
   * @return null|array
   */
  public function get_objects(): ?array
  {
    if ( !$this->write ){
      return $this->fetchAll(\PDO::FETCH_OBJ);
    }
    return null;
  }

}