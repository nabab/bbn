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
	 * @param \bbn\db\connection $db A valid database connection
	 * @param string $prefix
	 * @return void
	 */
	public function __construct( \bbn\db\connection $db, $database = '', $prefix='bbn'){
		// Checking the prefix string
		if ( \bbn\str\text::check_name($prefix) || ($prefix === false) ){
			// The database connection
			$this->db = $db;
			$this->admin_db = empty($database) ? $this->db->current : $database;
			$this->client_db = $this->db->current;
			$this->prefix = $prefix;
			// If there's no underscore finishing the prefix we add it
      if ( !$this->prefix ){
        $this->prefix = '';
      }
			else if ( substr($this->prefix,-1) !== '_' ){
				$this->prefix .= '_';
			}
			// If there's no client table we presume none exist and we create the schemas
			if ( !in_array($this->prefix.'clients', $this->db->get_tables()) ){
				$this->create_tables();
			}
		}
    else{
      throw new Exception;
    }
	}
	
	public function get_projects($id_client=''){
		
	}
  
  public function save_config(array $cfg, $class = 'grid')
  {
    if ( isset($cfg['elements']) ){
      $copy = $cfg;
      unset($copy['elements']);
      $this->db->insert($this->prefix.'objects', [
          'id_project' => 1,
          'class' => $class,
          'configuration' => json_encode($copy)
          ]);
      $id = $this->db->last_id();
      $i = 1;
      foreach ( $cfg['elements'] as $name => $ele ){
        if ( $class === 'form' ){
          $this->db->insert($this->admin_db.'.'.$this->prefix.'fields',[
            'id_obj' => $id,
            'column' => ( isset($ele['appui']['table'], $ele['attr']['name']) ?
              $this->db->host . '.' . $ele['appui']['table'] .
              '.' . $this->db->col_simple_name($ele['attr']['name']) : null ),
            'title' => isset($ele['label']) ? $ele['label'] : null,
            'position' => isset($ele['position']) ? $ele['position'] : $i,
            'configuration' => json_encode($ele)
          ]);
        }
        else if ( $class === 'grid' ){
          $this->db->insert($this->admin_db.'.'.$this->prefix.'fields',[
            'id_obj' => $id,
            'column' => ( 
              isset($ele['fields']['args'][0], $ele['appui']['table']) ? 
                $this->db->host . '.' . $ele['appui']['table'] . '.' . $this->db->col_simple_name($ele['fields']['args'][0]) : null 
            ),
            'title' => isset($ele['columns']['title']) ? $ele['columns']['title'] : null,
            'position' => isset($ele['position']) ? $ele['position'] : $i,
            'configuration' => json_encode($ele)
          ]);
        }
        $i++;
      }
      return $id;
    }
  }
  
  public function load_config($id, $class = 'grid', $params=[])
  {
    if( $this->db ){
      if ( \bbn\str\text::is_number($id) && 
              $obj = $this->db->rselect(
                      $this->admin_db . '.' . $this->prefix . 'objects',
                      [],
                      ["id" => $id]) ){
        $cfg = \bbn\tools::to_array(json_decode($obj['configuration']));
        $cfg['class'] = $obj['class'];
        if ( empty($cfg['url']) ){
          $cfg['url'] = $id;
        }
        if ( !empty($obj['description']) ){
          $cfg['description'] = $obj['description'];
        }
        if ( !is_null($obj['table']) ){
          $cfg['table'] = $obj['table'];
        }
        $fields = $this->db->rselect_all(
                $this->admin_db . '.' . $this->prefix . 'fields',
                [],
                ["id_obj"=>$id],
                ["position" => "asc"]);
        
        foreach ( $fields as $k => $f ){
          $fields[$k] = \bbn\tools::to_array(json_decode($f['configuration']));
        }
        $cfg['elements'] = $fields;
      }
      else if ( is_array($id) ){
        $cfg = $id;
      }
      else{
        if ( $class === 'form' ){
          $cfg = $this->get_default_form_config($id);
        }
        else{
          $cfg = $this->get_default_grid_config($id, $params);
        }
      }
      return $cfg;
    }
  }
	
  /**
	 * Generates a whole form configuration array for a given table according to its structure and/or form configuration
	 * 
	 * @param string | integer $table The database's table or the ID of the form
	 * @return string
	 */
	public function load_form_config($id, $builder=null){

    if ( is_null($builder) ){
      $builder = new \bbn\html\builder();
    }

    if ( $cfg = $this->load_config($id) ){
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
		return false;
	}
  
  private function get_default_grid_config($table, $params=[]){
		if ( $this->db &&
            ($cfg = $this->db->modelize($table)) &&
            isset($cfg['keys']['PRIMARY']) &&
            count($cfg['keys']['PRIMARY']['columns']) === 1 &&
            ($table = $this->db->get_full_name($table)) ){

      $id = \bbn\str\text::genpwd();
      
      $full_cfg = [
          'id'=>$id,
          'url' => implode("/", $params),
          'primary' => $cfg['keys']['PRIMARY']['columns'][0],
          'select' => 1,
          'insert' => 1,
          'update' => 1,
          'delete' => 1,
          'elements' => []
      ];
      
      $args = $params;

      array_shift($args);
      $where = [];
      for ( $i = 0; $i < (count($args) - 1); $i++ ){
        if ( isset($cfg['fields'][$args[$i]]) ){
          array_push($where, [$args[$i], '=', $args[$i+1]]);
        }
        $i++;
      }
      
      $limit = 5;
      $i = 0;
      
      foreach ( $cfg['fields'] as $name => $f ){

        $r = $this->get_default_col_config($table, $name, $where, $params);
        $full_cfg['elements'][$i] = [];
        $grid_cfg =& $full_cfg['elements'][$i];
        
        if ( count($r['appui']) > 0 ){
          $grid_cfg['appui'] = $r['appui'];
          $grid_cfg['appui']['table'] = $table;
        }
        else{
          $grid_cfg['appui'] = [
              'table' => $table
          ];
        }

        if ( isset($r['addField']) ){
          $grid_cfg['fields'] = $r['addField'];
        }
        if ( isset($r['addColumn']) ){
          if ( $f['key'] !== 'PRI' ){
            if ( $i >= $limit ){
              $r['addColumn']['hidden'] = true;
            }
            $grid_cfg['columns'] = $r['addColumn'];
          }
        }
        $i++;
      }
      array_push($full_cfg['elements'], ['columns' => [
          'addCommandItem' => [['name' => 'edit', 'text' => 'Mod.'], ['name' => 'destroy', 'text' => 'Suppr.']],
          'title' => '&nbsp;',
          'width' => 160
      ]]);
      
      //die(print_r($full_cfg));

      return $full_cfg;
    }
  }
  
  private function get_default_col_config($table, $column, $where=[], $params=[]){
    
		// Looks in the db for columns corresponding to the given table
		if ( $this->db && \bbn\str\text::check_name($column) &&
            ($cfg = $this->db->modelize($table)) &&
            isset($cfg['fields'][$column]) ){

      $f = $cfg['fields'][$column];
      $r = [
          'appui' => []
      ];
      
      $values = false;
      $type = 'string';
      switch ( $f['type'] ){
        case 'float':
          $type = 'number';
          break;
        case 'text':
          $type = 'text';
          break;
        case 'enum':
          $type = 'text';
          break;
        case 'date':
        case 'datetime':
        case 'time':
          $type = $f['type'];
          break;
        case 'int':
          $type = $f['maxlength'] == 1 ? 'boolean' : 'number';
          break;
      }

      $r['addField'] = [
        'args' => [$column],
        'type' => $type,
        'editable' => $f['key'] === 'PRI' ? false : true,

      ];
      if ( $f['null'] ){
        $r['addField']['nullable'] = true;
      }
      if ( ($f['key'] !== 'PRI') && ($type !== 'boolean') ){
        $r['addField']['validation'] = [
            'fn' => '\\Kendo\\Data\\DataSourceSchemaModelFieldValidation',
            'required' => true              
        ];
        if ( $type === 'number' && !isset($f['keys']) ){
          $r['addField']['validation']['min'] = $f['signed'] ? (int)str_repeat('4', (int)$f['maxlength']) : 0;
          $r['addField']['validation']['max'] = $f['signed'] ? (int)str_repeat('4', (int)$f['maxlength']) : (int)str_repeat('9', (int)$f['maxlength']);
        }
      }


      $r['addColumn'] = [
          'field' => $column,
          'title' => (ucwords(str_replace('_', ' ', $column))),
          'menu' => true
      ];
      if ( $type === 'boolean' ){
        $r['addColumn']['template'] = "#= $column ? 'Oui' : 'Non' #";
      }
      // Foreign keys
      if ( isset($f['keys']) && ($type === 'number') && count($f['keys']) > 0 ){
        foreach ( $f['keys'] as $k ){
          //var_dump($k, $cfg['keys']);
          if ( isset($cfg['keys'][$k]['ref_column']) ){
            $foreign_fields = [];
            $foreign_all = $this->db->modelize($cfg['keys'][$k]['ref_table']);
            /* @var $foreign_all array */
            foreach ( $foreign_all['fields'] as $n2 => $f2 ){
              if ( $f2['type'] === 'varchar' ){
                $foreign_fields['text'] = $n2;
                break;
              }
            }
            $foreign_fields['value'] = $cfg['keys'][$k]['ref_column'];
            $cond = [];
            foreach ( $where as $w ){
              if ( isset($foreign_all['fields'][$w[0]]) ){
                array_push($cond, $w);
              }
            }

            if ( ($q = $this->db->query(
                    $this->db->get_select(
                            $cfg['keys'][$k]['ref_table'],
                            $foreign_fields,
                            $cond), array_map(function($a){
                              return is_array($a) ? end($a) : $a;
                            }, $cond))) && $q->count() < 200 ){
              $values = $q->get_rows();
            }
            else{
              $r['appui']['sql'] = $this->db->get_select(
                      $cfg['keys'][$k]['ref_table'],
                      $foreign_fields,
                      [$cfg['keys'][$k]['ref_column']=>1]);
              $url = $params;
              array_unshift($url, 'key');
              array_push($url, $k);
              $r['addColumn']['editor'] = \bbn\str\text::clean(sprintf(<<<'EOD'
                function(container, options) {
                  var ele = $('<input name="%s">');
                  ele.appendTo(container).kendoAutoComplete({
                    minLength: 3,
                    filter: "contains",
                    placeholder: "Recherche",
                    dataTextField: "text",
                    dataSource: {
                      serverFiltering: true,
                      serverPaging: true,
                      pageSize: 20,
                      transport: {
                        read: {
                          dataType: "json",
                          type: "POST",
                          url: "%s",
                          data: {
                            model: "text",
                            
                          }
                        }
                      }
                    },
                  });
                }
EOD
                    , $column,
                      implode("/", $url)), 'code');
              
            }
            $r['addField']['type'] = 'string';
            break;
          }
        }
      }
      else if ( $f['type'] === 'text' ){
        $r['addColumn']['editor'] = <<<'EOD'
          function(container, options) {
            var ele = $('<textarea name="' + options.field + '" style="min-width:500px"></textatarea');
            ele.appendTo(container).kendoEditor({});
          }
EOD;
      }
      if ( $values ){
        $r['addColumn']['values'] = $values;
      }
      else if ( ($f['type'] === 'int') && ($type === 'number') ){
        $r['addColumn']['format'] = '0';
      }
      return $r;
    }
  }

  private function get_default_form_config($table){
    
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
          
          $cfg[$i] = $this->get_default_field_config($table, $name);
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
          "attr" => [
            "action" => ".",
            "title" => $title,
          ],
          "elements" => $cfg
      ];
    }
  }
  
	/**
	 * Creates an array for configuring an instance of input for a given field in a given table
	 * 
	 * @param string $table The database's table
	 * @param string $table The table's column
	 * @return \bbn\html\input
	 */
	private function get_default_field_config($table, $column){
    
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
    apc_clear_cache();
    apc_clear_cache("user");
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
				if ( strpos($t, '.'.$this->prefix) === false ){
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