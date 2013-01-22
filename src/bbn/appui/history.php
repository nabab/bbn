<?php
namespace bbn\appui;

class history extends \bbn\db\connection
{
	
	const ACTIVE_FIELD = 'actif';
	
	private $h_hashes = array();
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
}
?>