<?php
/**
 * @package bbn\appui
*/
namespace bbn\appui;

/**
 * This class builds special tables and defines databases' structure and according forms in them.
 * The built tables all have the same prefix (bbn in this example), and are called:
 * <ul>
 * <li>bbn_clients</li>
 * <li>bbn_projects</li>
 * <li>bbn_dbs</li>
 * <li>bbn_tables</li>
 * <li>bbn_columns</li>
 * <li>bbn_keys</li>
 * <li>bbn_forms</li>
 * <li>bbn_fields</li>
 * </ul>
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Dec 14, 2012, 04:23:55 +0000
 * @category  Appui
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 */

class mapper{

	private 
          $db,
          $prefix,
          $admin_db,
          $client_db;
	public 
          $schema = false,
          $auto_update = false;
  
	/**
	 * This will initialize the instance and create the tables if they don't exist in the current database
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
	
	public function get_projects($id_client=''){
		
	}
	
  public function save_config($cfg)
  {
    $copy = $cfg;
    unset($copy['elements']);
    $this->db->insert($this->prefix.'forms', [
        'id_project' => 1,
        'configuration' => serialize($cfg)
        ]);
    $id = $this->db->last_id();
    if ( isset($cfg['elements']) ){
      foreach ( $cfg['elements'] as $i => $ele ){
        $this->db->insert($this->admin_db.'.'.$this->prefix.'fields',[
            'id_form' => $id,
            'column' => $this->db->host.'.'.$this->client_db.'.'.$ele['attr']['name'],
            'title' => isset($ele['label']) ? $ele['label'] : null,
            'position' => $ele['position'],
            'configuration' => serialize($ele)
        ]);
      }
    }
  }
  
  private function get_table_form($table){
    
    if ( $this->db && ($table = $this->db->get_full_name($table)) ){

      $cfg = [];

      $table = explode(".",$table);
      if ( count($table) === 2 ){
        $database = trim($table[0]);
        $this->client_db = $database;
        $table = trim($table[1]);
        // Creates the default form configuration
        $square = new \bbn\appui\square($this->db, "apst_ui");

        if ( $sqt = $square->get_table($table) ){
          $title = $sqt->tit;
        }
        else{
          $title = false;
        }
        

        $db_info = $this->db->modelize($table);
        $i = 0;
        foreach ( $db_info['fields'] as $name => $c ){
          
          $cfg[$i] = $this->config_input($table, $name);
          $cfg[$i]['default'] = $c['default'];
          if ( isset($c['maxlength']) ){
            $cfg[$i]['attr']['maxlength'] = $c['maxlength'];
          }
          if ( isset($sqt->fields[$name]) ){
            
            $info = $sqt->fields[$name];
            if ( is_object($info) ){
              $cfg[$i]['params'] = $info->params;
              $cfg[$i]['required'] = $info->mand == 1 ? 1 : false;
              $cfg[$i]['label'] = $info->tit;
              //$cfg[$i] = $square->get_config_from_id($info->id_form,$cfg[$i]);
            }
            $cfg[$i]['table'] = $table;
          }
          $i++;
        }
      }
      return [
          "action" => ".",
          "title" => $title,
          "elements" => $cfg,
          
      ];
    }
  }


  /**
	 * Generates a whole form configuration array for a given table according to its structure and/or form configuration
	 * 
	 * @param string | integer $table The database's table or the ID of the form
	 * @return string
	 */
	public function load_config($id, $builder=null){

    if( $this->db ){
      
      if ( is_null($builder) ){
        $builder = new \bbn\html\builder();
      }
			// Looks in the db for columns corresponding to the given table
			$cond = '';

      if ( \bbn\str\text::is_number($id) && $form = $this->db->rselect(
              $this->admin_db.'.'.$this->prefix.'forms', [], ["id" => $id]) ){
        
        $cfg = unserialize($form['configuration']);
        $fields = $this->db->rselect_all($this->admin_db.'.'.$this->prefix.'fields', [], ["id_form"=>$id]);
        
        foreach ( $fields as $k => $f ){
          $fields[$k] = unserialize($f['configuration']);
        }
        $cfg['elements'] = $fields;
      }
      else if ( is_array($id) ){
        $cfg = $id;
      }
      else{
        $cfg = $this->get_table_form($id);
      } 

      if ( isset($cfg) ){
        if ( !isset($cfg['builder']) || is_string($cfg['builder']) ){
          $cfg['builder'] =& $builder;
        }
        foreach ( $cfg['elements'] as $k => $f ){
          if ( isset($cfg['elements'][$k]['data']['db']) && is_string($cfg['elements'][$k]['data']['db']) ){
            $cfg['elements'][$k]['data']['db'] =& $this->db;
          }
        }
        return $cfg;
      }
		}
		return false;
	}
	
	/**
	 * Creates an array for configuring an instance of input for a given field in a given table
	 * 
	 * @param string $table The database's table
	 * @param string $table The table's column
	 * @return \bbn\html\input
	 */
	public function config_input($table, $column){
    
		// Looks in the db for columns corresponding to the given table
		if ( $this->db && \bbn\str\text::check_name($column) && ($table_cfg = $this->db->modelize($table)) && isset($table_cfg['fields'][$column]) ){
      $col = $table_cfg['fields'][$column];
			$full_name = explode(".", $this->db->get_full_name($table))[1].'.'.$column;
      $cfg = [
        'attr' => [
          'name' => $full_name,
        ],
        'position' => $col['position'],
				'null' => $col['null'] ? 1 : false
			];

      if ( strpos($col['type'], 'enum') === 0 ){
				preg_match_all("/'((?:[^']|\\\\.)*)'/", $col['extra'], $m);
				if ( isset($m[1]) ){
					$cfg['field'] = 'dropdown';
					$cfg['widget'] = [
              'options' => [
                  'dataSource' => $m[1]
              ]
          ];
				}
			}
      else{
				$dec = false;
				$ref = false;
        if ( isset($col['keys']) ){
          foreach ( $col['keys'] as $k ){
            $key = $table_cfg['keys'][$k];
            if ( $k === 'PRIMARY' ){
            }
            else if ( \bbn\str\text::check_name($key['ref_db'], $key['ref_table'], $key['ref_column']) ){
              $ref = [
                  'db' => $key['ref_db'],
                  'table' => $key['ref_table'],
                  'column' => $key['ref_column']
              ];
              break;
            }
          }
        }
				if ( is_array($ref) && $ref_table_cfg = $this->db->modelize($ref['table']) ){
          // Arguments for select
          $cols = [$ref['column']];
          foreach ( $ref_table_cfg['fields'] as $name => $def ){
            if ( ($def['type'] === 'varchar') || ($def['type'] === 'text') ){
              $cols = [
                  "value" => $ref['column'],
                  "label" => $name
              ];
              break;
            }
          }
          $cfg['data']['sql'] = $this->db->get_select($ref['table'], $cols);
					$cfg['data']['db'] = $this->db;
					$cfg['field'] = 'dropdown';
				}
				else if ( strpos($col['type'], 'char') !== false ){
					$cfg['field'] = 'text';
				}
				else if ( strpos($col['type'], 'float') !== false ){
					$cfg['field'] = 'numeric';
          $dec = explode(",", $col['maxlength']);
          if ( isset($dec[0], $dec[1]) ){
            $cfg['widget']['options']['decimals'] = (int)$dec[1];
          }
          $cfg['attr']['maxlength'] = isset($cfg['widget']['options']['decimals']) ? (int)($col['maxlength'] + 1) : (int)$col['maxlength'];
					$cfg['widget']['options']['format'] = isset($cfg['widget']['options']['decimals']) ? '#' : 'n';
					$cfg['attr']['type'] = 'number';
          $cfg['widget']['options']['step'] = 10/pow(10, $cfg['widget']['options']['decimals']+1);
					$max = '';
          $max_length = $cfg['attr']['maxlength'];
          if ( isset($cfg['options']['decimals']) ){
            $max_length -= $cfg['options']['decimals'];
          }
					for ( $i = 0; $i < $max_length; $i++ ){
						$max .= '9';
					}
          $max = (int)$max;
					$cfg['widget']['options']['max'] = ( (float)$max > (int)$max ) ? (float)$max : (int)$max;
					$cfg['widget']['options']['min'] = $col['signed'] ? - $cfg['widget']['options']['max'] : 0;
				}
				else if ( strpos($col['type'], 'text') !== false ){
					$cfg['field'] = 'editor';
				}
				else if ( $col['type'] === 'datetime' ){
					$cfg['field'] = 'datetime';
				}
				else if ( $col['type'] === 'date' ){
					$cfg['field'] = 'date';
				}
				else if ( $col['type'] === 'time' ){
					$cfg['field'] = 'time';
				}
				else if ( $col['type'] === 'timestamp' ){
					$cfg['field'] = 'datetime';
				}
				else if ( strpos($col['type'], 'int') !== false ){
          if ( $col['maxlength'] == 1 ){
            $cfg['field'] = 'checkbox';
          }
					else{
            if ( strpos($col['type'], 'unsigned') ){
  						$cfg['widget']['options']['min'] = 0;
            }
            else{
              $cfg['widget']['options']['min'] = false;
            }
            $cfg['field'] = 'numeric';
            $cfg['widget']['options']['decimals'] = 0;
            $cfg['widget']['options']['format'] = 'd';
            $cfg['attr']['type'] = 'number';
					}
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
      /*
			$projects = [];
			$r1 = $this->db->query("SELECT *
			FROM `{$this->admin_db}`.`{$this->prefix}projects`
			WHERE `db` LIKE 'localhost.$db'");
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
       * 
       */
			$this->db->raw_query("
        INSERT IGNORE INTO `{$this->admin_db}`.`{$this->prefix}dbs`
        (`id`, `db`)
        VALUES
        ('{$this->db->host}.$db', '$db')");
			
			foreach ( $schema as $t => $vars ){
				if ( strpos($t, '.'.$this->prefix) === false ){
          $tmp = explode(".", $t);
          $db = $tmp[0];
          $table = $tmp[1];
					$this->db->insert_update($this->admin_db.'.'.$this->prefix.'tables',[
						'id' => 'localhost.'.$t,
						'db' => 'localhost.'.$db,
						'table' => $table
					]);
          foreach ( $vars['fields'] as $col => $f ){
    				$config = new \stdClass();
						if ( strpos($t, 'apst_') === 0 && ( $col !== 'id' && $col !== 'last_mod' && $col !== 'id_user' && $col !== 'history' ) ){
							$config->history = 1;
						}
						if ( isset($f['default']) ){
							$config->default = $f['default'];
						}
						if ( !empty($f['extra']) ){
							$config->extra = $f['extra'];
						}
						if ( isset($f['signed']) && $f['signed'] == 1 ){
							$config->signed = 1;
						}
						if ( isset($f['null']) && $f['null'] == '1' ){
							$config->null = 1;
						}
						if ( isset($f['maxlength']) && $f['maxlength'] > 0 ){
							$config->maxlength = (int)$f['maxlength'];
						}
						if ( isset($f['keys']) ){
							$config->keys = [];
							foreach ( $f['keys'] as $key ){
								$config->keys[$key] = $vars['keys'][$key];
							}
						}
						$this->db->insert_update($this->admin_db.'.'.$this->prefix.'columns',[
							'id' => 'localhost.'.$t.'.'.$col,
							'table' => 'localhost.'.$t,
							'column' => $col,
							'position' => $f['position'],
							'type' => $f['type'],
							'null' => $f['null'],
							'key' => $f['key'],
							'config' => json_encode($config)
						]);
					}
				}
			}
			foreach ( $schema as $t => $vars ){
				if ( strpos($t, $this->prefix.'.') === false ){
					foreach ( $vars['keys'] as $k => $arr ){
						$pos = 1;
						foreach ( $arr['columns'] as $c ){
							$this->db->insert_update($this->admin_db.'.'.$this->prefix.'keys',[
								'id' => 'localhost.'.$t.'.'.$c.'.'.$k,
								'key' => $k,
								'column' => 'localhost.'.$t.'.'.$c,
								'position' => $pos,
								'ref_column' => is_null($arr['ref_column']) ? null : 'localhost.'.$arr['ref_db'].'.'.$arr['ref_table'].'.'.$arr['ref_column']
							]);
							$pos++;
						}
					}
				}
			}
      /*
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
							'column' => $field['column'],
							'title' => $field['title'],
							'position' => $field['position'],
							'configuration' => json_encode($field['configuration'])
						]);
					}
				}
			}
       * 
       */
		}
	}
	
	/**
	 * Creates the empty appui tables
	 * 
	 * @return void
	 */
	public function create_tables(){
		if ( $this->db ){
      if ( !in_array($this->prefix.'tables', $this->db->get_tables()) ){
        $this->db->disable_keys();
        return $this->db->query("
        -- DROP TABLE IF EXISTS `{$this->prefix}clients`;
        CREATE TABLE IF NOT EXISTS `{$this->prefix}clients` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `nom` varchar(100) NOT NULL,
        PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

        -- DROP TABLE IF EXISTS `{$this->prefix}columns`;
        CREATE TABLE IF NOT EXISTS `{$this->prefix}columns` (
        `id` varchar(180) NOT NULL,
        `table` varchar(130) NOT NULL,
        `column` varchar(49) NOT NULL,
        `position` tinyint(3) unsigned NOT NULL,
        `type` varchar(50) NOT NULL,
        `null` tinyint(1) unsigned NOT NULL,
        `key` varchar(3) DEFAULT NULL,
        `default` text,
        `config` text,
        PRIMARY KEY (`id`),
        UNIQUE KEY `table` (`table`,`column`),
        UNIQUE KEY `table_2` (`table`,`position`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        -- DROP TABLE IF EXISTS `{$this->prefix}dbs`;
        CREATE TABLE IF NOT EXISTS `{$this->prefix}dbs` (
        `id` varchar(80) NOT NULL,
        `id_client` int(10) unsigned DEFAULT NULL,
        `host` varchar(49) NOT NULL DEFAULT 'localhost',
        `db` varchar(30) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `host_db` (`host`,`db`),
        KEY `db` (`db`),
        KEY `id_client` (`id_client`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        -- DROP TABLE IF EXISTS `{$this->prefix}fields`;
        CREATE TABLE IF NOT EXISTS `{$this->prefix}fields` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `id_form` int(10) unsigned NOT NULL,
        `column` varchar(180) CHARACTER SET utf8 NOT NULL,
        `title` varchar(100) CHARACTER SET utf8 NOT NULL,
        `position` tinyint(3) unsigned NOT NULL,
        `configuration` text CHARACTER SET utf8 NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `id_form_column` (`id_form`,`column`),
        KEY `id_form` (`id_form`),
        KEY `column` (`column`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

        -- DROP TABLE IF EXISTS `{$this->prefix}forms`;
        CREATE TABLE IF NOT EXISTS `{$this->prefix}forms` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `id_project` int(10) unsigned NOT NULL,
        `configuration` text CHARACTER SET utf8 NOT NULL,
        PRIMARY KEY (`id`),
        KEY `id_project` (`id_project`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

        -- DROP TABLE IF EXISTS `{$this->prefix}history`;
        CREATE TABLE IF NOT EXISTS `{$this->prefix}history` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `operation` enum('INSERT','UPDATE','DELETE') NOT NULL,
        `line` int(10) unsigned NOT NULL,
        `column` varchar(180) NOT NULL,
        `old` text DEFAULT NULL,
        `last_mod` datetime NOT NULL,
        `id_user` int(10) unsigned NOT NULL,
        PRIMARY KEY (`id`),
        KEY `id_user` (`id_user`),
        KEY `column` (`column`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=52 ;

        -- DROP TABLE IF EXISTS `{$this->prefix}keys`;
        CREATE TABLE IF NOT EXISTS `{$this->prefix}keys` (
        `id` varchar(230) NOT NULL,
        `key` varchar(49) NOT NULL,
        `column` varchar(180) NOT NULL,
        `position` tinyint(3) unsigned NOT NULL,
        `ref_column` varchar(180) DEFAULT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `key` (`key`,`column`),
        KEY `ref_column` (`ref_column`),
        KEY `column` (`column`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

        -- DROP TABLE IF EXISTS `{$this->prefix}projects`;
        CREATE TABLE IF NOT EXISTS `{$this->prefix}projects` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `id_client` int(10) unsigned NOT NULL,
        `db` varchar(80) CHARACTER SET utf8 DEFAULT NULL,
        `name` varchar(50) CHARACTER SET utf8 NOT NULL,
        `config` text CHARACTER SET utf8,
        PRIMARY KEY (`id`),
        UNIQUE KEY `id_client_2` (`id_client`,`name`),
        KEY `db` (`db`),
        KEY `id_client` (`id_client`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;

        -- DROP TABLE IF EXISTS `{$this->prefix}tables`;
        CREATE TABLE IF NOT EXISTS `{$this->prefix}tables` (
        `id` varchar(130) NOT NULL,
        `db` varchar(80) NOT NULL,
        `table` varchar(49) NOT NULL,
        PRIMARY KEY (`id`),
        UNIQUE KEY `db_table` (`db`,`table`),
        KEY `table` (`table`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8;


        ALTER TABLE `{$this->prefix}columns`
        ADD CONSTRAINT `{$this->prefix}columns_ibfk_1` FOREIGN KEY (`table`) REFERENCES `{$this->prefix}tables` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

        ALTER TABLE `{$this->prefix}dbs`
        ADD CONSTRAINT `{$this->prefix}dbs_ibfk_1` FOREIGN KEY (`id_client`) REFERENCES `{$this->prefix}clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

        ALTER TABLE `{$this->prefix}history`
        ADD CONSTRAINT `{$this->prefix}history_ibfk_1` FOREIGN KEY (`id_user`) REFERENCES `apst_utilisateurs` (`id`) ON UPDATE NO ACTION,
        ADD CONSTRAINT `{$this->prefix}history_ibfk_2` FOREIGN KEY (`column`) REFERENCES `{$this->prefix}columns` (`id`) ON UPDATE CASCADE;

        ALTER TABLE `{$this->prefix}keys`
        ADD CONSTRAINT `{$this->prefix}keys_ibfk_1` FOREIGN KEY (`column`) REFERENCES `{$this->prefix}columns` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        ADD CONSTRAINT `{$this->prefix}keys_ibfk_2` FOREIGN KEY (`ref_column`) REFERENCES `{$this->prefix}columns` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

        ALTER TABLE `{$this->prefix}projects`
        ADD CONSTRAINT `{$this->prefix}projects_ibfk_2` FOREIGN KEY (`db`) REFERENCES `{$this->prefix}dbs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        ADD CONSTRAINT `{$this->prefix}projects_ibfk_1` FOREIGN KEY (`id_client`) REFERENCES `{$this->prefix}clients` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

        ALTER TABLE `{$this->prefix}tables`
        ADD CONSTRAINT `{$this->prefix}tables_ibfk_1` FOREIGN KEY (`db`) REFERENCES `{$this->prefix}dbs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
        ");
        $this->db->enable_keys();
      }
		}
	}
}
?>