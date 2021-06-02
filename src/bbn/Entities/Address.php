<?php
namespace bbn\Entities;

use bbn\X;
use bbn\Models\Tts\Dbconfig;

class Address
{

  use Dbconfig;

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
        'pc' => 'pc',
        'city' => 'city',
        'phone' => 'phone',
        'email' => 'email',
        'fulladdress' => 'fulladdress',
        'cfg' => 'cfg'
      ],
    ]
  ];

  /**
   * Constructor.
   *
   * @param db    $db
   * @param array $cfg
   * @param array $params
   */
  public function __construct(Db $db, array $cfg = null)
  {
    // The database connection
    $this->db = $db;
    // Setting up the class configuration
    $this->_init_class_cfg($cfg);
    $this->options = \bbn\Appui\Option::getInstance();eejk

	}

	public function getInfo($id){
	  $d = $this->db->rselect('bbn_addresses', [], ['id' => $id]);
	  if ( $d ){
      if ( !empty($d['tel']) ){
        $d['tel'] = (string)$d['tel'];
      }
      if ( !empty($d['fax']) ){
        $d['fax'] = (string)$d['fax'];
      }
			$d['fadresse'] = $this->fadresse($d);
      if ( $id_adherent ){
        $d['roles'] = $this->db->getColumnValues([
          'tables' => ['apst_liens'],
          'fields' => ['bbn_options.text'],
          'join' => [
            [
              'table' => 'bbn_addresses',
              'on' => [
                'conditions' => [
                  [
                    'field' => 'bbn_addresses.id',
                    'operator' => 'eq',
                    'exp' => 'apst_liens.id_lieu'
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
                    'exp' => 'apst_liens.type_lien'
                  ]
                ],
                'logic' => 'AND'
              ]
            ]
          ],
          'where' => [
            'apst_liens.id_adherent' => $id_adherent,
            'apst_liens.id_lieu' => $id
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
      
      if ( !empty($p['email']) && \bbn\Str::isEmail($p['email']) ){
        array_push($cond, ['email', 'LIKE', $p['email']]);
      }
      if ( !empty($p['adresse']) && strlen($p['adresse']) > 7 ){
        array_push($cond, ['adresse', 'LIKE', '%'.$p['adresse'].'%']);
      }
      if ( !empty($p['tel']) && (strlen($p['tel']) >= 6) ){
        if ( strlen($p['tel']) !== 10 ){
          array_push($cond, ['tel', 'LIKE', $p['tel'].'%']);
        }
        else{
          array_push($cond, ['tel', 'LIKE', $p['tel']]);
        }
      }
      if ( !empty($p['fax']) && (strlen($p['fax']) >= 6) ){
        if ( strlen($p['fax']) !== 10 ){
          array_push($cond, ['fax', 'LIKE', $p['fax'].'%']);
        }
        else{
          array_push($cond, ['fax', 'LIKE', $p['fax']]);
        }
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
    if ( $this->get_info($id) ){
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
    $res = \bbn\Str::isUid($p) ? [$p] : $this->seek($p, $start, $limit);
    return $r;
  }

  public function relations($id){
  }

  public function add($src, $force = false)
  {
    $cfg =  $this->getClassCfg();
    $fds =& $cfg['arch']['addresses'];
    if ()
    $id = false;
    $ville = false;
    $fn = $this->set_address($fn);	
    if ( !empty($fn['id_country']) ){
      if ( ($fn['id_country'] === $this->options->fromCode('FR', 'countries')) && $conf_ville = $this->get_ville(empty($fn['cp']) ? '' : $fn['cp'], empty($fn['ville']) ? '' : $fn['ville']) ){
        $fn['cp'] = $conf_ville['cp'];
        $fn['ville'] = $conf_ville['ville'];
      }
      if ( $force || !($id = $this->search($fn)) ){
        if ( $this->db->insert("bbn_addresses", $fn) ){
          $id = $this->db->lastId();
        }
      }
    }
    return $id;
	}

	public function update($id, $fn)
	{
    
    if ( $info = $this->get_info($id) ){
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

  public function fadresse($s, $with_br = 1){
    if (\bbn\Str::isUid($s) ){
      $s = $this->get_info($s);
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
        if ( $fn = $this->get_info($a) ){
          $creation[] = $this->db->selectOne('bbn_history', 'tst', [
            'uid' => $a
          ]);
          $cols = $this->db->getFieldsList('apst_liens');
          $cols['creation'] = 'tst';
          $links = $this->db->rselectAll([
            'tables' => ['apst_liens'],
            'fields' => $cols,
            'join' => [
              [
                'table' => 'bbn_history',
                'on' => [
                  'conditions' => [
                    [
                      'field' => 'bbn_history.uid',
                      'operator' => 'eq',
                      'exp' => 'apst_liens.id'
                    ]
                  ],
                  'logic' => 'AND'
                ]
              ]
            ],
            'where' => [
              'id_lieu' => $a
            ]
          ]);
          foreach ( $links as $link ){
            $this->db->update('apst_liens', ['id_lieu' => $id], ['id' => $link['id']]);
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
}