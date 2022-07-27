<?php
namespace bbn\Entities;

use bbn\Db;
use bbn\Models\Tts\Dbconfig;

class Entity
{

  use Dbconfig;

  /** @var array */
  protected static $default_class_cfg = [
    'table' => 'bbn_entities',
    'tables' => [
      'entities' => 'bbn_entities'
    ],
    'arch' => [
      'entities' => [
        'id' => 'id',
        'name' => 'name'
      ],
    ],
  ];

  /**
   * Constructor.
   *
   * @param Db    $db
   * @param array $cfg
   * @param array $params
   */
  public function __construct(Db $db, array $cfg = null)
  {
    // The database connection
    $this->db = $db;
    // Setting up the class configuration
    $this->_init_class_cfg($cfg);

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


  public function fullSearch($p, $start = 0, $limit = 0){
    $r = [];
    $res = \bbn\Str::isUid($p) ? [$p] : $this->seek($p, $start, $limit);
    return $r;
  }


  public function relations($id){
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