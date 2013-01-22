<?php
/**
 * @package bbn\appui
*/
namespace bbn\appui;

/**
 * This class builds special tables and defines databases' structure and according forms in them
 * The built tables all have the same prefix (bbn in this example), and are called:
 * - bbn_clients
 * - bbn_projects
 * - bbn_dbs
 * - bbn_tables
 * - bbn_columns
 * - bbn_keys
 * - bbn_forms
 * - bbn_fields
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Dec 14, 2012, 04:23:55 +0000
 * @category  Appui
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 */

class builder{

	private $db, $prefix, $admin_db, $client_db;
	public $schema = false, $auto_update = 1;

	/**
	 * This will call the builder and create the tables if they don't exist in the current database
	 * 
	 * @param \bbn\db\connection $db A valid database connection
	 * @param string $prefix
	 * @return void
	 */
	public function __construct( \bbn\db\connection $db, $database = '', $prefix='bbn'){
		// Checking the prefix string
		if ( \bbn\str\text::check_name($prefix) ){
			// The database connection
			$this->db = $db;
			$this->admin_db = empty($database) ? $this->db->current : $database;
			$this->client_db = $this->db->current;
			$this->prefix = $prefix;
			// If there's no underscore finishing the prefix we add it
			if ( substr($this->prefix,-1) !== '_' ){
				$this->prefix .= '_';
			}
			// If there's no client table we presume none exist and we create the schemas
			if ( !in_array($this->prefix.'clients', $this->db->get_tables()) ){
				$this->create_tables();
			}
		}
	}
	
	private function check_table($table, $database = false){
		if ( $database ){
			$this->client_db = $database;
		}
		
	}
	
	/**
	 * Generates a whole form for a given table according to its structure and/or form configuration
	 * 
	 * @param string | integer $table The database's table or the ID of the form
	 * @return string
	 */
	public function get_form_config($id){
		$rs = $this->db->select($this->admin_db.".bbn_fields", [], ['id_form' => $id]);
		foreach ( $rs as $i => $r ){
			$rs[$i]['configuration'] = json_decode($r['configuration']);
		}
		return $rs;
	}
	public function config_form($table){
		$table = explode(".",$table);
		if ( count($table) === 2 ){
			$database = trim($table[0]);
			$table = trim($table[1]);
		}
		else{
			$database = $this->db->current;
			$table = trim($table[0]);
		}
		$this->client_db = $database;
		$r = [];
		$cfg = [];
		if( $this->db ){
			// Looks in the db for columns corresponding to the given table
			$cond = '';
			if ( is_int($table) ){
				$cond = " AND `id` = $table ";
			}
			else if ( \bbn\str\text::check_name($database, $table) ){
				$cond = " AND `{$this->prefix}projects`.`db` LIKE '{$database}' AND `{$this->prefix}fields`.`table` LIKE '$table'";
			}
			if ( !empty($cond) ){
				if ( !( $form = $this->db->get_rows("
					SELECT `{$this->prefix}fields`.*, `{$this->prefix}projects`.`id` AS `id_project`
					FROM `{$this->admin_db}`.`{$this->prefix}forms`
						JOIN `{$this->admin_db}`.`{$this->prefix}fields`
							ON `{$this->prefix}fields`.`id_form` = `{$this->prefix}forms`.`id`
						JOIN `{$this->admin_db}`.`{$this->prefix}projects`
							ON `{$this->prefix}projects`.`id` = `{$this->prefix}forms`.`id_project`
					WHERE 1 $cond 
					ORDER BY `position`")
				) ){
					// Creates the default form configuration
					$square = new \bbn\appui\square($this->db, "apst_ui");
					
					$t = $square->get_table($table);

					$r['title'] = $t->tit;
					
					$db_info = $this->db->modelize($table);
					$i = 0;
					foreach ( $db_info['fields'] as $name => $c ){
						$cfg[$i] = $this->get_input_config($table, $name);
						$cfg[$i]['default'] = $c['default'];
						if ( isset($c['maxlength']) ){
							$cfg[$i]['options']['maxlength'] = $c['maxlength'];
						}
						if ( isset($t->fields[$name]) ){
							$info = $t->fields[$name];
							if ( isset($cfg[$i]['script']) ){
								unset($cfg[$i]['script']);
							}
							if ( isset($cfg[$i]['field']) ){
								unset($cfg[$i]['field']);
							}
							$cfg[$i]['params'] = $info->params;
							$cfg[$i]['name'] = $info->name;
							$cfg[$i]['table'] = $table;
							$cfg[$i]['required'] = $info->mand;
							$cfg[$i]['label'] = $info->tit.' ('.$info->id_form.')';
							$cfg[$i]['options'] = array('db' => $this->db);
							$cfg[$i] = $square->get_config_from_id($info->id_form,$cfg[$i]);
						}
						$i++;
					}
				}
			}
		}
		return array('info' => $r, 'cfg' => $cfg);
	}
	
	/**
	 * Creates an array for configuring an instance of input for a given field in a given table
	 * 
	 * @param string $table The database's table
	 * @param string $table The table's column
	 * @return \bbn\html\input
	 */
	public function get_input_config($table, $column)
	{
		// Looks in the db for columns corresponding to the given table
		if ( $this->db && \bbn\str\text::check_name($table, $column) && $col = $this->db->get_row("
				SELECT *
				FROM `{$this->admin_db}`.`{$this->prefix}columns`
				WHERE `db` LIKE '{$this->db->current}'
				AND `table` LIKE '$table'
				AND `column` LIKE '$column'")
		){
			if ( !( $keys = $this->db->get_rows("
				SELECT *
				FROM `{$this->admin_db}`.`{$this->prefix}keys`
				WHERE `table` LIKE '$table'
				AND `column` LIKE '$column'")
			) ){
				$keys = array();
			}
			$cfg = array(
				'name' => $col['column'],
				'position' => $col['position'],
				'null' => $col['null'] ? 1 : false
			);
			
			if ( strpos($col['type'], 'enum') === 0 ){
				preg_match('|\(([^\)]+)\)|', $col['type'], $m);
				if ( isset($m[1]) ){
					$cfg['field'] = 'dropdownlist';
					$cfg['options']['dataSource'] = [];
					$data = explode(',', $m[1]);
					foreach ( $data as $d ){
						array_push($cfg['options']['dataSource'], ['value' => $d, 'text' => $d]);
					}
				}
			}
			else{
				$dec = false;
				$ref = false;
				if ( preg_match('|\(([0-9,]+)\)|', $col['type'], $m) ){
					if ( strpos($m[1], ',') ){
						$dec = explode(",", $m[1]);
						$cfg['options']['maxlength'] = (int)($dec[0] + 1);
					}
					else{
						$cfg['options']['maxlength'] = (int)$m[1];
					}
				}
				foreach ( $keys as $key ){
					if ( $key['key'] === 'PRIMARY' ){
					}
					else if ( \bbn\str\text::check_name($key['ref_db'], $key['ref_table'], $key['ref_column']) ){
						$ref = array('db'=>$key['ref_db'], 'table'=>$key['ref_table'], 'column'=>$key['ref_column']);
						break;
					}
				}
				if ( $ref ){
					$primary = $this->db->get_var("
						SELECT `column`
						FROM `{$this->admin_db}`.`{$this->prefix}keys`
						WHERE `db` LIKE '$key[ref_db]'
						AND `table` LIKE '$key[ref_table]'
						AND `key` LIKE 'PRIMARY'");
					$secondary = $this->db->get_var("
						SELECT `column`
						FROM `{$this->admin_db}`.`{$this->prefix}columns`
						WHERE `db` LIKE '$key[ref_db]'
						AND `table` LIKE '$key[ref_table]'
						AND `key` IS NULL
						ORDER BY position
						LIMIT 1");
					$cfg['field'] = 'dropdownlist';
					$cfg['options']['sql'] = "
						SELECT `$key[ref_table]`.`$primary`, `$key[ref_table]`.`$secondary`
						FROM `{$this->admin_db}`.`$key[ref_db]`.`$key[ref_table]`";
					$cfg['options']['db'] = $this->db;
				}
				else if ( strpos($col['type'], 'char') !== false ){
					$cfg['field'] = 'text';
				}
				else if ( strpos($col['type'], 'float') !== false ){
					$cfg['field'] = 'numerictextbox';
					$cfg['options']['decimals'] = isset($dec[0], $dec[1]) ? $dec[0] - $dec[1] : 0;
					$cfg['options']['format'] = $cfg['options']['decimals'] > 0 ? 'n' : 'd';
					$cfg['options']['type'] = 'number';
					$max = '';
					$max_length = $cfg['options']['maxlength'];
					if ( $cfg['options']['decimals'] > 0 ){
						$max_length -= ( $cfg['options']['decimals'] + 1 );
					}
					for ( $i = 0; $i < $max_length; $i++ ){
						$max .= '9';
					}
					$cfg['options']['max'] = ( (float)$max > (int)$max ) ? (float)$max : (int)$max;
					$cfg['options']['min'] = strpos($col['type'], 'unsigned') ? 0 : - $cfg['options']['max'];
				}
				else if ( strpos($col['type'], 'text') !== false ){
					$cfg['field'] = 'rte';
				}
				else if ( $col['type'] === 'datetime' ){
					$cfg['field'] = 'datetimepicker';
				}
				else if ( $col['type'] === 'date' ){
					$cfg['field'] = 'datepicker';
				}
				else if ( $col['type'] === 'time' ){
					$cfg['field'] = 'timepicker';
				}
				else if ( $col['type'] === 'timestamp' ){
					$cfg['field'] = 'datetimepicker';
				}
				else if ( strpos($col['type'], 'int') !== false ){
					if ( strpos($col['type'], 'unsigned') ){
						$cfg['options']['min'] = 0;
					}
					else{
						$cfg['options']['min'] = false;
					}
					$cfg['field'] = 'numerictextbox';
					$cfg['options']['decimals'] = 0;
					$cfg['options']['format'] = 'd';
					$cfg['options']['type'] = 'number';
				}
			}
			return $cfg;
		}
	}
	
	/**
	 * Empties the structure tables for a given database and refill them with the current structure
	 * 
	 * @param string $db
	 * @return void
	 */
	public function update($db=''){
		if ( empty($db) ){
			$db = $this->db->current;
		}
		if ( \bbn\str\text::check_name($db) ){
			$change = $this->db->current === $db ? false : $this->db->current;
			if ( $change ){
				$this->db->change($db);
			}
			$schema = $this->db->modelize();
			if ( $change ){
				$this->db->change($change);
			}
			$projects = [];
			$r1 = $this->db->query("SELECT * FROM `{$this->admin_db}`.`{$this->prefix}projects` WHERE `db` LIKE '$db'");
			while ( $d1 = $r1->get_row() ){
				$projects[$d1['id']] = $d1;
				$projects[$d1['id']]['forms'] = [];
				$r2 = $this->db->query("
					SELECT id
					FROM `{$this->admin_db}`.`{$this->prefix}forms`
					WHERE `id_project` = ?",
					$d1['id']);
				while ( $d2 = $r2->get_row() ){
					$projects[$d1['id']]['forms'][$d2['id']] = $this->get_form_config();
				}
			}
			$this->db->query("DELETE FROM `{$this->admin_db}`.`{$this->prefix}dbs` WHERE `db` LIKE '$db'");
			$this->db->query("INSERT INTO `{$this->admin_db}`.`{$this->prefix}dbs` (`db`) VALUES ('$db')");
			foreach ( $schema as $t => $vars ){
				if ( strpos($t, $this->prefix) !== 0 ){
					$this->db->insert($this->admin_db.'.'.$this->prefix.'tables',[
						'db' => $db,
						'table' => $t
					]);
					foreach ( $vars['fields'] as $col => $f ){
						$this->db->insert($this->admin_db.'.'.$this->prefix.'columns',[
							'db' => $db,
							'table' => $t,
							'column' => $col,
							'position' => $f['position'],
							'type' => $f['type'],
							'null' => $f['null'],
							'key' => $f['key'],
							'default' => $f['default'],
							'extra' => $f['extra']
						]);
					}
				}
			}
			foreach ( $schema as $t => $vars ){
				if ( strpos($t, $this->prefix) !== 0 ){
					foreach ( $vars['keys'] as $k => $arr ){
						$pos = 1;
						foreach ( $arr['columns'] as $c ){
							$this->db->insert($this->admin_db.'.'.$this->prefix.'keys',[
								'db' => $db,
								'table' => $t,
								'key' => $k,
								'column' => $c,
								'position' => $pos,
								'ref_db' => $arr['ref_db'],
								'ref_table' => $arr['ref_table'],
								'ref_column' => $arr['ref_column']
							]);
							$pos++;
						}
					}
				}
			}
			foreach ( $projects as $i => $p ){
				$this->db->insert($this->admin_db.'.'.$this->prefix.'projects',[
					'id' => $i,
					'id_client' => $p['id_client'],
					'db' => $p['db'],
					'name' => $p['name'],
					'config' => $p['config']]);
				foreach ( $p['forms'] as $j => $form ){
					$this->db->insert($this->admin_db.'.'.$this->prefix.'forms',[
						'id' => $j,
						'id_project' => $i
					]);
					foreach ( $form as $field ){
						$this->db->insert_ignore($this->admin_db.'.'.$this->prefix.'fields',[
							'id' => $field['id'],
							'id_form' => $j,
							'table' => $field['table'],
							'column' => $field['column'],
							'title' => $field['title'],
							'position' => $field['position'],
							'configuration' => json_encode($field['configuration'])
						]);
					}
				}
			}
		}
	}
	
	/**
	 * Creates the empty appui tables
	 * 
	 * @return void
	 */
	public function create_tables()
	{
		if ( $this->db ){
			return $this->db->query("
SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE=\"NO_AUTO_VALUE_ON_ZERO\";
SET time_zone = \"+00:00\";

DROP TABLE IF EXISTS `{$this->prefix}clients`;
CREATE TABLE IF NOT EXISTS `{$this->prefix}clients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=2 ;

DROP TABLE IF EXISTS `{$this->prefix}columns`;
CREATE TABLE IF NOT EXISTS `{$this->prefix}columns` (
  `db` varchar(50) NOT NULL,
  `table` varchar(50) NOT NULL,
  `column` varchar(50) NOT NULL,
  `position` tinyint(3) unsigned NOT NULL,
  `type` varchar(50) NOT NULL,
  `null` tinyint(1) unsigned NOT NULL,
  `key` varchar(3) DEFAULT NULL,
  `default` text,
  `extra` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`db`,`table`,`column`),
  KEY `table` (`table`),
  KEY `column` (`column`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `{$this->prefix}dbs`;
CREATE TABLE IF NOT EXISTS `{$this->prefix}dbs` (
  `id_client` int(10) unsigned DEFAULT NULL,
  `host` varchar(50) NOT NULL DEFAULT 'localhost',
  `db` varchar(50) NOT NULL,
  PRIMARY KEY (`host`,`db`),
  KEY `db` (`db`),
  KEY `host` (`host`),
  KEY `id_client` (`id_client`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `{$this->prefix}fields`;
CREATE TABLE IF NOT EXISTS `{$this->prefix}fields` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_form` int(10) unsigned NOT NULL,
  `table` varchar(50) CHARACTER SET utf8 NOT NULL,
  `column` varchar(50) CHARACTER SET utf8 NOT NULL,
  `title` varchar(100) CHARACTER SET utf8 NOT NULL,
  `position` tinyint(3) unsigned NOT NULL,
  `configuration` text CHARACTER SET utf8 NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_form` (`id_form`),
  KEY `table` (`table`),
  KEY `column` (`column`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

DROP TABLE IF EXISTS `{$this->prefix}forms`;
CREATE TABLE IF NOT EXISTS `{$this->prefix}forms` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_project` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `id_project` (`id_project`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

DROP TABLE IF EXISTS `{$this->prefix}keys`;
CREATE TABLE IF NOT EXISTS `{$this->prefix}keys` (
  `db` varchar(50) NOT NULL,
  `table` varchar(50) NOT NULL,
  `key` varchar(50) NOT NULL,
  `column` varchar(50) NOT NULL,
  `position` tinyint(3) unsigned NOT NULL,
  `ref_db` varchar(50) DEFAULT NULL,
  `ref_table` varchar(50) DEFAULT NULL,
  `ref_column` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`db`,`table`,`key`,`column`),
  KEY `table` (`table`),
  KEY `column` (`column`),
  KEY `ref_db` (`ref_db`),
  KEY `ref_table` (`ref_table`),
  KEY `ref_column` (`ref_column`),
  KEY `{$this->prefix}keys_dbs` (`db`,`table`,`column`),
  KEY `{$this->prefix}keys_ref_dbs` (`ref_db`,`ref_table`,`ref_column`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `{$this->prefix}projects`;
CREATE TABLE IF NOT EXISTS `{$this->prefix}projects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_client` int(10) unsigned NOT NULL,
  `db` varchar(50) CHARACTER SET utf8 DEFAULT NULL,
  `name` varchar(50) CHARACTER SET utf8 NOT NULL,
  `config` text CHARACTER SET utf8,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id_client_2` (`id_client`,`name`),
  KEY `db` (`db`),
  KEY `id_client` (`id_client`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=2 ;

DROP TABLE IF EXISTS `{$this->prefix}tables`;
CREATE TABLE IF NOT EXISTS `{$this->prefix}tables` (
  `db` varchar(50) NOT NULL,
  `table` varchar(50) NOT NULL,
  PRIMARY KEY (`db`,`table`),
  KEY `table` (`table`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


ALTER TABLE `{$this->prefix}columns`
  ADD CONSTRAINT `{$this->prefix}columns_ibfk_4` FOREIGN KEY (`table`) REFERENCES `{$this->prefix}tables` (`table`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `{$this->prefix}columns_ibfk_3` FOREIGN KEY (`db`) REFERENCES `{$this->prefix}dbs` (`db`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `{$this->prefix}columns_tables` FOREIGN KEY (`db`, `table`) REFERENCES `{$this->prefix}tables` (`db`, `table`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `{$this->prefix}dbs`
  ADD CONSTRAINT `{$this->prefix}dbs_clients` FOREIGN KEY (`id_client`) REFERENCES `{$this->prefix}clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

ALTER TABLE `{$this->prefix}fields`
  ADD CONSTRAINT `{$this->prefix}fields_columns` FOREIGN KEY (`column`) REFERENCES `{$this->prefix}columns` (`column`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `{$this->prefix}fields_forms` FOREIGN KEY (`id_form`) REFERENCES `{$this->prefix}forms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `{$this->prefix}fields_tables` FOREIGN KEY (`table`) REFERENCES `{$this->prefix}tables` (`table`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `{$this->prefix}forms`
  ADD CONSTRAINT `{$this->prefix}forms_projects` FOREIGN KEY (`id_project`) REFERENCES `{$this->prefix}projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `{$this->prefix}keys`
  ADD CONSTRAINT `{$this->prefix}keys_ibfk_6` FOREIGN KEY (`ref_column`) REFERENCES `{$this->prefix}columns` (`column`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `{$this->prefix}keys_dbs` FOREIGN KEY (`db`, `table`, `column`) REFERENCES `{$this->prefix}columns` (`db`, `table`, `column`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `{$this->prefix}keys_ibfk_1` FOREIGN KEY (`db`) REFERENCES `{$this->prefix}dbs` (`db`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `{$this->prefix}keys_ibfk_2` FOREIGN KEY (`table`) REFERENCES `{$this->prefix}tables` (`table`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `{$this->prefix}keys_ibfk_3` FOREIGN KEY (`column`) REFERENCES `{$this->prefix}columns` (`column`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `{$this->prefix}keys_ibfk_4` FOREIGN KEY (`ref_db`) REFERENCES `{$this->prefix}dbs` (`db`) ON DELETE CASCADE ON UPDATE CASCADE,
			ADD CONSTRAINT `{$this->prefix}keys_ibfk_5` FOREIGN KEY (`ref_table`) REFERENCES `{$this->prefix}tables` (`table`) ON DELETE CASCADE ON UPDATE CASCADE,
			ADD CONSTRAINT `{$this->prefix}keys_ref_dbs` FOREIGN KEY (`ref_db`, `ref_table`, `ref_column`) REFERENCES `{$this->prefix}columns` (`db`, `table`, `column`) ON DELETE CASCADE ON UPDATE CASCADE;
			
			ALTER TABLE `{$this->prefix}projects`
			ADD CONSTRAINT `{$this->prefix}projects_ibfk_1` FOREIGN KEY (`db`) REFERENCES `{$this->prefix}dbs` (`db`) ON DELETE CASCADE ON UPDATE CASCADE,
			ADD CONSTRAINT `{$this->prefix}projects_clients` FOREIGN KEY (`id_client`) REFERENCES `{$this->prefix}clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
			
			ALTER TABLE `{$this->prefix}tables`
			ADD CONSTRAINT `{$this->prefix}tables_dbs` FOREIGN KEY (`db`) REFERENCES `{$this->prefix}dbs` (`db`) ON DELETE CASCADE ON UPDATE CASCADE;
			SET FOREIGN_KEY_CHECKS=1;
			");
		}
	}
}
?>