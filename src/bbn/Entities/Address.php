<?php
namespace bbn\Entities;

use Exception;
use bbn\X;
use bbn\Str;
use bbn\Db;
use bbn\Appui\Option;
use bbn\Models\Tts\DbActions;
use bbn\Models\Cls\Db as DbCls;
use bbn\Entities\Models\Entities;
use bbn\Models\Cls\Nullall;


class Address extends DbCls
{
  use DbActions;

  /** @var array */
  protected static $default_class_cfg = [
    'table' => 'bbn_addresses',
    'tables' => [
      'addresses' => 'bbn_addresses'
    ],
    'arch' => [
      'addresses' => [
        'id' => 'id',
        'address' => 'address',
        'postcode' => 'postcode',
        'city' => 'city',
        'id_country' => 'id_country',
        'phone' => 'phone',
        'email' => 'email',
        'fulladdress' => 'fulladdress',
        'cfg' => 'cfg'
      ],
    ],
    'country' => ''
  ];

  private $tableRelations;

  /**
   * Constructor.
   *
   * @param Db    $db
   */
  public function __construct(
    Db $db, 
    protected Entities $entities,
    protected Entity|Nullall $entity = new Nullall()
  )
  {
    parent::__construct($db);
    $this->_init_class_cfg();
	}


  public function options(): Option
  {
    return $this->entities->options();
  }

	public function getInfo($id, $id_entity = null){
	  $d = $this->db->rselect('bbn_addresses', [], ['id' => $id]);
	  if ( $d ){
      if ( !empty($d['tel']) ){
        $d['tel'] = (string)$d['tel'];
      }
      if ( !empty($d['fax']) ){
        $d['fax'] = (string)$d['fax'];
      }
			$d['fadresse'] = $this->fadresse($d);
      if ( $id_entity ){
        $d['roles'] = $this->db->getColumnValues([
          'tables' => ['bbn_entities_links'],
          'fields' => ['bbn_options.text'],
          'join' => [
            [
              'table' => 'bbn_addresses',
              'on' => [
                'conditions' => [
                  [
                    'field' => 'bbn_addresses.id',
                    'operator' => 'eq',
                    'exp' => 'bbn_entities_links.id_address'
                  ]
                ],
                'logic' => 'AND'
              ]
            ],
            [
              'table' => 'bbn_options',
              'on' => [
                'conditions' => [
                  [
                    'field' => 'bbn_options.id',
                    'operator' => 'eq',
                    'exp' => 'bbn_entities_links.link_type'
                  ]
                ],
                'logic' => 'AND'
              ]
            ]
          ],
          'where' => [
            'bbn_entities_links.id_entity' => $id_entity,
            'bbn_entities_links.id_address' => $id
          ]
        ]);
      }
		}
    return $d;
	}

	public function search($fn, $cp=null){
		if ( $cp && is_string($fn) ){
			$fn = ['adresse' => $fn, 'cp' => $cp];
		}
		else if ( !is_array($fn) ){
			$fn = $this->set_adresse($fn);
		}

    if ( !empty($fn['adresse']) && !empty($fn['cp']) ){
			return $this->db->selectOne('bbn_addresses', 'id', [
			  'cp' => $fn['cp'],
			  'adresse' => $fn['adresse']
      ]);
		}
		return false;
	}

	public function seek($p, int $start = 0, int $limit = 100){
    if ( is_array($p) && ( !empty($p['adresse']) ||
        !empty($p['email']) ||
        !empty($p['tel']) ||
        !empty($p['fax']) )
    ){
      $cond = [];
      
      if ( !empty($p['email']) && Str::isEmail($p['email']) ){
        array_push($cond, ['email', 'LIKE', $p['email']]);
      }
      if ( !empty($p['adresse']) && strlen($p['adresse']) > 7 ){
        array_push($cond, ['adresse', 'LIKE', '%'.$p['adresse'].'%']);
      }
      if ( !empty($p['tel']) && (strlen($p['tel']) >= 6) ){
        array_push($cond, ['tel', 'LIKE', $p['tel'].'%']);
      }
      if ( !empty($p['ville']) ){
        array_push($cond, ['ville', 'LIKE', $p['ville']]);
      }
      if ( !empty($p['cp']) ){
        array_push($cond, ['cp', 'LIKE', $p['cp']]);
      }
      return $this->db->getColumnValues("bbn_addresses", 'id', $cond, ['adresse', 'ville'], $limit, $start);
    }
		return false;
	}

  /**
   * Supprime un tier et tous ses liens si précisé
   * Si non précisé et que le tier a des liens, il n'est pas supprimé
   *
   * @var int $id l'ID du tiers
   * @var bool $with_links si précisé tous ses liens sont également précisés
   *
   * @return bool Succès ou pas de la suppression
   */
  public function delete($id, $with_links = false){
    if ( $this->getInfo($id) ){
      $rels = $this->relations($id);
      if ( $with_links || empty($rels) ){
        foreach ( $rels as $k => $r ){
          $this->db->delete('amiral_liens', ['id' => $k]);
        }
        return $this->db->delete('bbn_addresses', ['id' => $id]);
      }
    }
		return false;
  }

  public function fullSearch($p, $start = 0, $limit = 0){
    $r = [];
    $res = Str::isUid($p) ? [$p] : $this->seek($p, $start, $limit);
    foreach ( $res as $i => $id ){
      $relations = $this->db->getColumnValues([
        'tables' => ['bbn_entities_links'],
        'fields' => ['nom'],
        'join' => [[
          'table' => 'bbn_addresses',
          'on' => [[
            'field' => 'bbn_entities_links.id_address',
            'operator' => 'eq',
            'exp' => 'bbn_addresses.id'
          ]]
        ], [
          'table' => 'apst_adherents',
          'on' => [[
            'field' => 'bbn_entities_links.id_entity',
            'operator' => 'eq',
            'exp' => 'apst_adherents.id'
          ]]
        ]],
        'where' => [
          'id_address' => $id
        ]
      ]);
      
      $r[$i] = $this->getInfo($id);
      $r[$i]['relations'] = X::join($relations, ', ');
    }
    return $r;
  }

  public function relations($id){
    if ( $this->getInfo($id) ){
      return $this->db->selectAllByKeys([
        'tables' => ['bbn_entities_links'],
        'fields' => ['bbn_addresses.id', 'id_entity'],
        'join' => [
          [
            'table' => 'bbn_addresses',
            'on' => [
              [
                'field' => 'bbn_entities_links.id_address',
                'operator' => 'eq',
                'exp' => 'bbn_addresses.id'
              ]
            ]
          ]
        ],
        'where' => [
          'id_address' => $id
        ]
      ]);
    }
    return false;
  }

  private function getCityCondition($ville, $percent = false){
    $cdx = $this->get_cedex($ville);
    $percent = $percent ? '%' : '';
    $ville_cond = "( `apst_cp`.`ville` LIKE '".$percent.Str::escapeSquotes($ville).$percent."' ";
    if ( strpos($ville, '-') ){
      $ville_comp = Str::escapeSquotes(str_replace('-', ' ', $ville));
      $ville_cond .= "OR `apst_cp`.`ville` LIKE '".$percent.$ville_comp.$percent."' ";
    }
    if ( strpos($ville, ' ') ){
      $ville_comp = Str::escapeSquotes(str_replace(' ', '-', $cdx['ville']));
      $ville_cond .= "OR `apst_cp`.`ville` LIKE '".$percent.$ville_comp.$percent."' ";
    }
    $ville_cond .= ") ";
    return $ville_cond;
  }

  public function getCity($cp, $ville='') {
    $cp = Str::getNumbers($cp);

    if ( strlen($cp) === 2 ){
      $cp .= '000';
    }
    else if ( strlen($cp) === 4 ){
      $cp = '0'.$cp;
    }

    $ville = trim($ville);
    $ville = str_replace('/', ' SUR ', $ville);
    while ( strpos($ville, '  ') ){
      $ville = str_replace('  ', ' ', $ville);
    }

    if ( empty($ville) && empty($cp) ){
      return [
        'cp' => '00000',
        'ville' => 'Inconnue'
      ];
    }
    else if ( empty($cp) && !empty($ville) ){
      $cp = $this->db->getOne("
        SELECT `apst_cp`.`ville`, `apst_cp`.`cp`
        FROM `apst_cp`
          LEFT OUTER JOIN `bbn_addresses`
            ON `bbn_addresses`.`cp` = `apst_cp`.`cp`
        WHERE ".$this->getCityCondition($ville)."
        GROUP BY `apst_cp`.`cp`
        ORDER BY COUNT(`bbn_addresses`.`id`) DESC
        LIMIT 1");
    }
    else if ( empty($ville) ){
      return $this->db->getRow("
          SELECT `apst_cp`.`cp`, `apst_cp`.`ville`
          FROM `apst_cp`
            LEFT OUTER JOIN `bbn_addresses`
              ON `bbn_addresses`.`ville` = `apst_cp`.`ville`
          WHERE `apst_cp`.`cp` = ?
          GROUP BY `apst_cp`.`ville`
          ORDER BY COUNT(`bbn_addresses`.`id`) DESC
          LIMIT 1",
          $cp);
    }
    $cdx = $this->get_cedex($ville);
    $ville_cond = $this->getCityCondition($ville);

    if ( $tmp = $this->db->getRow("
      SELECT cp, ville
      FROM apst_cp
      WHERE cp LIKE ?
      AND cp > 0
      AND $ville_cond",
      $cp) ){
      return $tmp;
    }
    
    if ( $villes = $this->db->getColArray("
      SELECT ville
      FROM apst_cp
      WHERE cp = ?
      AND cp > 0",
      $cp)
    ){
      $compare = [];
      $tmp = false;
      foreach ( $villes as $k => $v ){
        $cdx2 = $this->get_cedex($v);
        $compare[$k] = levenshtein($cdx['ville'], $cdx2['ville']);
        if ( min($compare) === $compare[$k] ){
          $tmp = $cdx2;
          $tmp['ville2'] = $v;
        }
      }
      if ( (min($compare) < 3) &&
              ($cdx['has_cedex'] === $tmp['has_cedex']) &&
              ($cdx['num_cedex'] === $tmp['num_cedex']) ){
        return [
          'ville' => $tmp['ville2'],
          'cp' => $cp
        ];
      }
      else{
        $inf = $this->db->rselect('apst_cp', [], [
          'cp' => $cp,
          'ville' => $tmp['ville2']
        ]);
        $inf['ville'] = $cdx['ville'];
        if ( $cdx['has_cedex'] ){
          $inf['ville'] .= ' Cedex';
          if ( $cdx['num_cedex'] ){
            $inf['ville'] .= ' '.$cdx['num_cedex'];
          }
        }
        if (array_key_exists('id', $inf)) {
          unset($inf['id']);
        }
        if ( $this->db->insert("apst_cp", $inf) ){
          return [
            'ville' => $inf['ville'],
            'cp' => $inf['cp']
          ];
        }
      }
    }

    $dpt = substr($cp, 0, 2);
    if ( $dpt == 97 ){
      $dpt = substr($cp, 0, 3);
    }
    else if ( $dpt == 20 ){
      $dpt = $this->db->getOne("
        SELECT id_dpt
        FROM apst_cp
        WHERE ville LIKE ?
        AND id_dpt LIKE '2A'
        OR id_dpt LIKE '2B'
        LIMIT 1",
        $ville);
      if ( !$dpt ){
        $dpt = '2B';
      }
    }
    
    if ( $dpt ){
      $ville_cond = $this->getCityCondition($ville, 1);
      // Sinon on la rajoute
      if ( $inf = $this->db->getRow("
        SELECT *
        FROM apst_cp
        WHERE id_dpt LIKE ?
        AND $ville_cond
        LIMIT 1",
        $dpt) ){

        $inf['cp'] = $cp;
        if ( $cdx['has_cedex'] ){
          $inf['ville'] .= ' Cedex';
          if ( $cdx['num_cedex'] ){
            $inf['ville'] .= ' '.$cdx['num_cedex'];
          }
        }
        if (array_key_exists('id', $inf)) {
          unset($inf['id']);
        }
        if ( $this->db->insert("apst_cp", $inf) ){
          return [
            'ville' => $inf['ville'],
            'cp' => $inf['cp']
          ];
        }
      }
    }
    return [
      'cp' => emptY($cp) ? '00000' : $cp,
      'ville' => empty($ville) ? 'Inconnue' : $ville
    ];
  }

  public function add($fn, $force = false){
    $id = false;
    $fn = $this->set_address($fn);
    if (!empty($fn['id_country'])) {
      if (($fn['id_country'] === $this->options()->fromCode('FR', 'countries'))
        && ($conf_ville = $this->get_ville(empty($fn['cp']) ? '' : $fn['cp'], empty($fn['ville']) ? '' : $fn['ville']))
      ) {
        $fn['cp'] = $conf_ville['cp'];
        $fn['ville'] = $conf_ville['ville'];
      }
      if ($force || !($id = $this->search($fn))) {
        if ( $this->db->insert("bbn_addresses", $fn) ){
          $id = $this->db->lastId();
        }
      }
    }
    return $id;
	}

	public function update($id, $fn)
	{
    
    if ( $info = $this->getInfo($id) ){
      $fields = array_keys($this->db->getColumns('bbn_addresses'));
      foreach ( $fn as $k => $v ){
        if ( !in_array($k, $fields) ){
          unset($fn[$k]);
        }
      }
      if ( isset($info['fadresse']) ){
          unset($info['fadresse']);
      }
      //$n count the property changed between $info and $fn
      $changed = false;
      foreach ( $info as $i => $val ){
        if ( \array_key_exists($i, $fn) && ($val !== $fn[$i]) ){
          $changed = true;
          break;
        }
      }
      if ( !$changed ){
        return $id;
      }
      else if ( (count($fn) > 0) && $this->db->update('bbn_addresses', $fn, ['id' => $id]) ){
        return $id;
      }
		}
    return false;
	}

	public function set_address($fn){
    $r = [];
    
		if ( is_array($fn) ){
			if ( !is_array($fn['adresse']) ){
				$fn['adresse'] = explode("\n", $fn['adresse']);
			}
      if ( is_array($fn['adresse']) ){
        $r['adresse'] = array_filter($fn['adresse'], function($ad){
          return !empty($ad) && strlen($ad) > 1;
        });
        if ( count($r['adresse']) > 0 ){
          // On enlève la virgule après le numéro de rue si elle y est
          $r['adresse'] = preg_replace("#^(\\d+),#", "\$1", implode("\n", $r['adresse']));
        }
        else{
          unset($r['adresse']);
        }
      }
      if ( isset($fn['cp']) ){
  			$r['cp'] = (int) Str::getNumbers($fn['cp']);
      }
      if ( isset($fn['id_country']) ){
  			$r['id_country'] = $fn['id_country'];
      }
      $r['ville'] = empty($fn['ville']) ? '' : Str::changeCase($fn['ville']);
      if ( isset($fn['tel']) ){
        $fn['tel'] = Str::getNumbers($fn['tel']);
        if ( strlen($fn['tel']) > 10 && strpos($fn['tel'], '33') === 0 ){
          $fn['tel'] = substr($fn['tel'], 2);
        }
        if ( strlen($fn['tel']) === 9 && strpos($fn['tel'], '0') !== 0 ){
          $fn['tel'] = '0'.$fn['tel'];
        }
        if ( strlen($fn['tel']) === 10 ){
          $r['tel'] = $fn['tel'];
        }
      }
      if ( isset($fn['fax']) ){
        $fn['fax'] = Str::getNumbers($fn['fax']);
        if ( strlen($fn['fax']) > 10 && strpos($fn['fax'], '33') === 0 ){
          $fn['fax'] = substr($fn['fax'], 2);
        }
        if ( strlen($fn['fax']) === 9 && strpos($fn['fax'], '0') !== 0 ){
          $fn['fax'] = '0'.$fn['fax'];
        }
        if ( strlen($fn['fax']) === 10 ){
          $r['fax'] = $fn['fax'];
        }
      }
      if ( isset($fn['email']) && Str::isEmail($fn['email']) ){
        $r['email'] = $fn['email'];
      }
		}
		return $r;
	}

  public function fadresse($s, $with_br = 1){
    if (Str::isUid($s) ){
      $s = $this->getInfo($s);
    }
    if ( is_array($s) ){
      $st = '';
      if ( !empty($s['adresse']) ){
        $st .= nl2br($s['adresse'], false).'<br>';
      }
      if ( !empty($s['cp']) ){
        $st .= $s['cp'].' ';
      }
      if ( !empty($s['ville']) ){
        $st .= $s['ville'];
      }
      if ( !$with_br ){
        return str_replace('<br>', ', ', $st);
      }
      if ( !empty($s['id_country']) && ($s['id_country'] !== $this->options()->fromCode('FR', 'countries')) ){
        $st .= '<br>(' .$this->options()->text($s['id_country']). ')' ;
      }
      
      return $st;
    }
    
    return '';
  }

  /*
   * Fusionne l'historique de différents lieux et les supprime tous sauf le premier
   *
   * @var mixed $ids Un tableau d'IDs ou une liste d'arguments
   */
  public function fusion($ids){
    $args = is_array($ids) ? $ids : func_get_args();
    if ( count($args) > 1 ){
      $id = array_shift($args);
      $creation = [$this->db->selectOne('bbn_history', 'tst', [
        'uid' => $id,
        'opr' => 'INSERT'
      ])];
      foreach ( $args as $a ){
        if ( $fn = $this->getInfo($a) ){
          $creation[] = $this->db->selectOne('bbn_history', 'tst', [
            'uid' => $a
          ]);
          $cols = $this->db->getFieldsList('bbn_entities_links');
          $cols['creation'] = 'tst';
          $links = $this->db->rselectAll([
            'tables' => ['bbn_entities_links'],
            'fields' => $cols,
            'join' => [
              [
                'table' => 'bbn_history',
                'on' => [
                  'conditions' => [
                    [
                      'field' => 'bbn_history.uid',
                      'operator' => 'eq',
                      'exp' => 'bbn_entities_links.id'
                    ]
                  ],
                  'logic' => 'AND'
                ]
              ]
            ],
            'where' => [
              'id_address' => $a
            ]
          ]);
          foreach ( $links as $link ){
            $this->db->update('bbn_entities_links', ['id_address' => $id], ['id' => $link['id']]);
          }
          $this->db->query("
            UPDATE bbn_history
            SET uid = ?
            WHERE uid = ?
            AND opr LIKE 'UPDATE'",
            hex2bin($id),
            hex2bin($a)
          );
          $this->db->query("
            DELETE FROM bbn_history
            WHERE uid = ?",
            hex2bin($a)
          );
          $this->db->query("
            DELETE FROM bbn_addresses
            WHERE id = ?",
            hex2bin($a)
          );
        }
      }
      $this->db->query("
        UPDATE bbn_history
        SET tst = ?
        WHERE uid = ?
        AND opr LIKE 'INSERT'",
        min($creation),
        hex2bin($id)
      );
    }
    return 1;
  }


  private function getTableRelations(): array
  {
    if (!isset($this->tableRelations)) {
      $arc = &$this->class_cfg['arch']['people'];
      $this->tableRelations = [];
      $refs = $this->db->findReferences($this->db->cfn($arc['id'], $this->class_cfg['table']));
      foreach ($refs as $ref) {
        [$db, $table, $col] = X::split($ref, '.');
        $model = $this->db->modelize($table);
        $this->tableRelations[] = [
          'db' => $db,
          'table' => $table,
          'primary' => isset($model['keys']['PRIMARY']) && (count($model['keys']['PRIMARY']['columns']) === 1) ? $model['keys']['PRIMARY']['columns'][0] : null,
          'col' => $col,
          'model' => $model
        ];
      }
    }

    return $this->tableRelations;
  }

}
