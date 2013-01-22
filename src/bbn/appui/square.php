<?php
namespace bbn\appui;

class square
{
	private $id, $site, $change = false;
	
	public function __construct(\bbn\db\connection $db, $schema)
	{
		$this->db = $db;
		if ( $this->db->current !== 'bbn' ){
			$this->change = $this->db->current;
			$this->db->change('bbn');
		}
		if ( is_int($schema) ){
			$cond = " WHERE bbn_sites.id = $schema ";
		}
		else if ( is_string($schema) ){
			$cond = " WHERE bbn_sites.bbn_db LIKE '$schema' ";
		}
		if ( $tmp = $this->db->get_row("SELECT * FROM bbn_sites $cond LIMIT 1") ){
			$this->site = new \stdClass();
			$this->id = $tmp['id'];
			foreach ( $tmp as $k => $v ){
				if ( substr($k,0,4) === 'bbn_' ){
					$k = substr($k,4);
				}
				$this->site->$k = $v;
			}
		}
		if ( $this->change ){
			$this->db->change($this->change);
		}
	}
	
	public function get_site()
	{
		return $this->site;
	}
	
	public function get_table($id)
	{
		$smenu = false;
		if ( is_int($id) ){
			$cond = " WHERE bbn_smenus.id = $id ";
		}
		else if ( is_string($id) && \bbn\str\text::check_name($id) ){
			$cond = " WHERE bbn_smenus.bbn_name LIKE '$id' ";
		}
		if ( $this->change ){
			$this->db->change('bbn');
		}
		if ( $tmp = $this->db->get_row("
			SELECT *
			FROM bbn_smenus
			$cond
			LIMIT 1") ){
			$smenu = new \stdClass();
			foreach ( $tmp as $k => $v ){
				if ( substr($k,0,4) === 'bbn_' ){
					$k = substr($k,4);
				}
				$smenu->$k = $v;
			}
			$r = $this->db->query("
				SELECT id, bbn_name
				FROM bbn_fields
				WHERE bbn_id_smenu = %u
				ORDER BY bbn_position",
				$smenu->id);
			$smenu->fields = array();
			while ( $d = $r->get_row() ){
				$smenu->fields[$d['bbn_name']] = $this->get_field($d['id']);
			}
		}
		if ( $this->change ){
			$this->db->change($this->change);
		}
		return $smenu;
	}
	
	public function get_field($id)
	{
		$f = false;
		if ( $this->change ){
			$this->db->change('bbn');
		}
		if ( is_string($id) && strpos($id, '.') && ( $x = explode('.', $id) ) && ( count($x) === 2 ) && \bbn\str\text::check_name($x[0],$x[1]) ){
			$id = $this->get_var("
				SELECT bbn_fields.id
				FROM bbn_fields
					JOIN bbn_smenus
						ON bbn_smenus.bbn_id_site = {$this->id}
						AND bbn_smenus.bbn_name LIKE '$x[0]'
				WHERE bbn_fields.bbn_name LIKE '$x[1]'
				LIMIT 1");
		}
		if ( is_int($id) ){
			$cond = " WHERE bbn_fields.id = $id ";
			if ( $tmp = $this->db->get_row("SELECT * FROM bbn_fields $cond AND bbn_id_site = %u LIMIT 1",$this->id) ){
				$f = new \stdClass();
				foreach ( $tmp as $k => $v ){
					if ( substr($k,0,4) === 'bbn_' ){
						$k = substr($k,4);
					}
					$f->$k = $v;
				}
				$f->params = array();
				$r = $this->db->query("
					SELECT bbn_value, 
					IFNULL(bbn_smenus.bbn_name,'') AS reftable,
					IFNULL(bbn_fields.bbn_name,'') AS reffield
					FROM bbn_param
						LEFT OUTER JOIN bbn_fields
							ON bbn_fields.id = bbn_value
						LEFT OUTER JOIN bbn_smenus
							ON bbn_fields.bbn_id_smenu = bbn_smenus.id
					WHERE bbn_id_field = %u
					ORDER BY bbn_param.bbn_position", $f->id);
				while ( $d = $r->get_row() ){
					array_push($f->params, empty($d['reftable']) ? $d['bbn_value'] : $d['reftable'].'.'.$d['reffield']);
				}
			}
		}
		if ( $this->change ){
			$this->db->change($this->change);
		}
		return $f;
	}
	public function get_config_from_id($id, $cfg)
	{
		$tmp = array();
		$field = '';
		if ( !is_array($cfg) ){
			$cfg = [];
		}
		switch ( $id )
		{
			case 1:
			$tmp['field'] = 'datepicker';
			break;
			
			case 2:
			// email
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'email';
			$tmp['options']['maxlength'] = 50;
			$tmp['options']['size'] = 25;
			$tmp['options']['email'] = 1;
			// param1 === 'yes ? multiple : single
			break;
			
			case 3:
			// rich text
			$tmp['field'] = 'rte';
			// Type: Text
			break;
			
			case 4:
			// price (float)
			$tmp['tag'] = 'input';
			$tmp['field'] = 'numerictextbox';
			$tmp['options']['format'] = 'n';
			$tmp['options']['maxlength'] = 15;
			$tmp['options']['size'] = 10;
			// Type float
			break;
			
			case 5:
			// relation
			// var_dump($cfg);
			$tmp['field'] = 'dropdownlist';
			$tmp['options']['type'] = 'number';
			if ( isset($cfg['options']['db'],$cfg['params'][0]) && is_object($cfg['options']['db']) ){
				$p = explode('.', $cfg['params'][0]);
				if ( count($p) === 2 ){
					$table = $p[0];
					$field = $p[1];
					$select = "`$table`.`$field`";
					$order = "`$table`.`$field`";
					if ( isset($cfg['params'][1]) ){
						$p = explode('.', $cfg['params'][1]);
						$table = $p[0];
						$field = $p[1];
						$select = "CONCAT ($select,' ',`$table`.`$field`)";
						$order .= ",`$table`.`$field`";
					}
					$cfg['options']['sql'] = "SELECT id, $select FROM $table ORDER BY $order";
				}
			}
			break;
			
			case 8:
			// rich small text
			$tmp['field'] = 'rte';
			$tmp['options']['rows'] = 3;
			$tmp['options']['cols'] = 15;
			// type Tinytext
			break;
			
			case 9:
			$tmp['field'] = 'rte';
			$tmp['options']['rows'] = 6;
			$tmp['options']['cols'] = 20;
			// type Text
			break;
			
			case 10:
			// hidden field
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'hidden';
			break;
			
			case 11:
			// checkbox
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'checkbox';
			// tinyint (0/1)
			break;
			
			case 12:
			if ( count($cfg['params']) === 2 ){
				$tmp['field'] = 'numerictextbox';
				$tmp['options']['min'] = $cfg['params'][0];
				$tmp['options']['max'] = $cfg['params'][1];
				$tmp['options']['step'] = 1;
				$tmp['options']['format'] = 'd';
			}
			break;
			
			case 16:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			$tmp['field'] = 'dropdownlist';
			$tmp['options']['dataSource'] = [];
			$a1 = explode(',', $cfg['params'][0]);
			$a2 = explode(',', $cfg['params'][1]);
			foreach ( $a1 as $i => $a ){
				if ( strpos($a, "'") === 0 ){
					$a = substr($a, 1, -1);
				}
				if ( strpos($a2[$i], "'") === 0 ){
					$a2[$i] = substr($a2[$i], 1, -1);
				}
				array_push($tmp['options']['dataSource'], array('text'=>$a, 'value'=>$a2[$i]));
			}
			break;
			
			case 18:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 20:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 24:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 25:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 27:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 28:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 29:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 30:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 32:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 33:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 35:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 36:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 39:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 40:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 41:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 42:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 46:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 47:
			$tmp['field'] = 'numerictextbox';
			if ( isset($cfg['params'][0],$cfg['params'][1]) ){
				$tmp['options']['min'] = $cfg['params'][0];
				$tmp['options']['max'] = $cfg['params'][1];
			}
			$tmp['options']['step'] = 1;
			$tmp['options']['format'] = 'd';
			break;
			
			case 49:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			$tmp['options']['url'] = 1;
			break;
			
			case 50:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 79879:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 367904:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 564643:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 564809:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 657376:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 5345344:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 5391538:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 9085466:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 29098681:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 74682674:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 113051193:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 148751228:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 196779967:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 231858415:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 261368416:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 290886418:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 436730913:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 470057584:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 494886673:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 504909832:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 560916497:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 640910535:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 673747416:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			if ( isset($cfg['params'][0]) && is_numeric($cfg['params'][0]) ){
				$tmp['options']['maxlength'] = $cfg['params'][0];
				if ( isset($cfg['params'][1]) && is_numeric($cfg['params'][1]) ){
					$tmp['options']['minlength'] = $cfg['params'][1];
				}
			}
			break;
			
			case 743318065:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 782446287:
			// signed number (int)
			$tmp['tag'] = 'input';
			$tmp['field'] = 'numerictextbox';
			$tmp['options']['format'] = 'd';
			$tmp['options']['maxlength'] = 11;
			$tmp['options']['size'] = 10;
			$tmp['options']['max'] = 2147483648;
			$tmp['options']['min'] = -2147483648;
			break;
			
			case 929232328:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 945255014:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 945255015:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 945255016:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
			case 945255017:
			$tmp['tag'] = 'input';
			$tmp['options']['type'] = 'text';
			break;
			
		}
		
		if ( isset($cfg['options'], $tmp['options']) ){
			$cfg['options'] = array_merge($tmp['options'], $cfg['options']);
		}
		return array_merge($tmp, $cfg);
	}
	
}
?>