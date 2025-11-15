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
    protected ?Entities $entities = null,
    protected Entity|Nullall $entity = new Nullall()
  ) {
    parent::__construct($db);
    $this->initClassCfg();
  }


  public function options(): Option
  {
    return $this->entities ? $this->entities->options() : Option::getInstance();
  }

  public function getInfo($id, $id_entity = null)
  {
    $d = $this->db->rselect('bbn_addresses', [], ['id' => $id]);
    if ($d) {
      if (!empty($d['tel'])) {
        $d['tel'] = (string)$d['tel'];
      }
      $d['fadresse'] = $this->fadresse($d);
      if ($id_entity) {
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

  public function search($fn, $cp = null)
  {
    $f = $this->getClassCfg()['arch']['addresses'];
    if ($cp && is_string($fn)) {
      $fn = [$f['address'] => $fn, 'cp' => $cp];
    } else if (!is_array($fn)) {
      $fn = $this->set_address($fn);
    }

    if (!empty($fn[$f['address']]) && !empty($fn[$f['postcode']])) {
      return $this->db->selectOne('bbn_addresses', $f['id'], [
        $f['postcode'] => $fn[$f['postcode']],
        $f['address'] => $fn[$f['address']]
      ]);
    }
    return false;
  }

  public function seek($p, int $start = 0, int $limit = 100)
  {
    $f = $this->getClassCfg()['arch']['addresses'];
    if (
      is_array($p) && (!empty($p[$f['address']]) ||
        !empty($p[$f['phone']]) ||
        !empty($p[$f['postcode']]))
    ) {
      $cond = [];

      if (!empty($p[$f['address']]) && Str::len($p[$f['address']]) > 7) {
        array_push($cond, [$f['address'], 'LIKE', '%' . $p[$f['address']] . '%']);
      }
      if (!empty($p[$f['phone']]) && (Str::len($p[$f['phone']]) >= 6)) {
        array_push($cond, [$f['phone'], 'LIKE', $p[$f['phone']] . '%']);
      }
      if (!empty($p[$f['city']])) {
        array_push($cond, [$f['city'], 'LIKE', $p[$f['city']]]);
      }
      if (!empty($p[$f['postcode']])) {
        array_push($cond, [$f['postcode'], 'LIKE', $p[$f['postcode']]]);
      }
      return $this->db->getColumnValues("bbn_addresses", $f['id'], $cond, [$f['address'], $f['city']], $limit, $start);
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
  public function delete($id, $with_links = false)
  {
    if ($this->getInfo($id)) {
      $rels = $this->relations($id);
      if ($with_links || empty($rels)) {
        foreach ($rels as $k => $r) {
          //$this->db->delete('amiral_liens', ['id' => $k]);
        }
        return $this->db->delete('bbn_addresses', ['id' => $id]);
      }
    }
    return false;
  }

  public function fullSearch($p, $start = 0, $limit = 0)
  {
    $r = [];
    $res = Str::isUid($p) ? [$p] : $this->seek($p, $start, $limit);
    foreach ($res as $i => $id) {
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

  public function relations($id)
  {
    if ($this->getInfo($id)) {
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

  private function getCityCondition($ville, $percent = false)
  {
    $cdx = $this->getCedex($ville);
    $percent = $percent ? '%' : '';
    $ville_cond = "( `apst_cp`.`ville` LIKE '" . $percent . Str::escapeSquotes($ville) . $percent . "' ";
    if (Str::pos($ville, '-')) {
      $ville_comp = Str::escapeSquotes(str_replace('-', ' ', $ville));
      $ville_cond .= "OR `apst_cp`.`ville` LIKE '" . $percent . $ville_comp . $percent . "' ";
    }
    if (Str::pos($ville, ' ')) {
      $ville_comp = Str::escapeSquotes(str_replace(' ', '-', $cdx['ville']));
      $ville_cond .= "OR `apst_cp`.`ville` LIKE '" . $percent . $ville_comp . $percent . "' ";
    }
    $ville_cond .= ") ";
    return $ville_cond;
  }

  public function getCedex($ville)
  {
    $f = $this->getClassCfg()['arch']['addresses'];
    $r = [
      $f['city'] => Str::changeCase($ville),
      'has_cedex' => false,
      'num_cedex' => null
    ];
    if (!empty($r[$f['city']]) && stripos($r[$f['city']], 'Cedex')) {
      $r['has_cedex'] = 1;
      $tmp = explode("Cedex", $r[$f['city']]);
      $r[$f['city']] = trim($tmp[0]);
      if (isset($tmp[1]) && Str::isNumber(trim($tmp[1]))) {
        $r['num_cedex'] = trim($tmp[1]);
      }
    }
    return $r;
  }


  public function getCity($cp, $ville = '')
  {
    $cp = Str::getNumbers($cp);

    if (Str::len($cp) === 2) {
      $cp .= '000';
    } else if (Str::len($cp) === 4) {
      $cp = '0' . $cp;
    }

    $ville = trim($ville);
    $ville = str_replace('/', ' SUR ', $ville);
    while (Str::pos($ville, '  ')) {
      $ville = str_replace('  ', ' ', $ville);
    }

    if (empty($ville) && empty($cp)) {
      return [
        'cp' => '00000',
        'ville' => 'Inconnue'
      ];
    } else if (empty($cp) && !empty($ville)) {
      $cp = $this->db->getOne("
        SELECT `apst_cp`.`ville`, `apst_cp`.`cp`
        FROM `apst_cp`
          LEFT OUTER JOIN `bbn_addresses`
            ON `bbn_addresses`.`cp` = `apst_cp`.`cp`
        WHERE " . $this->getCityCondition($ville) . "
        GROUP BY `apst_cp`.`cp`
        ORDER BY COUNT(`bbn_addresses`.`id`) DESC
        LIMIT 1");
    } else if (empty($ville)) {
      return $this->db->getRow(
        "
          SELECT `apst_cp`.`cp`, `apst_cp`.`ville`
          FROM `apst_cp`
            LEFT OUTER JOIN `bbn_addresses`
              ON `bbn_addresses`.`ville` = `apst_cp`.`ville`
          WHERE `apst_cp`.`cp` = ?
          GROUP BY `apst_cp`.`ville`
          ORDER BY COUNT(`bbn_addresses`.`id`) DESC
          LIMIT 1",
        $cp
      );
    }
    $cdx = $this->getCedex($ville);
    $ville_cond = $this->getCityCondition($ville);

    if ($tmp = $this->db->getRow(
      "
      SELECT cp, ville
      FROM apst_cp
      WHERE cp LIKE ?
      AND cp > 0
      AND $ville_cond",
      $cp
    )) {
      return $tmp;
    }

    if ($villes = $this->db->getColArray(
      "
      SELECT ville
      FROM apst_cp
      WHERE cp = ?
      AND cp > 0",
      $cp
    )) {
      $compare = [];
      $tmp = false;
      foreach ($villes as $k => $v) {
        $cdx2 = $this->getCedex($v);
        $compare[$k] = levenshtein($cdx['ville'], $cdx2['ville']);
        if (min($compare) === $compare[$k]) {
          $tmp = $cdx2;
          $tmp['ville2'] = $v;
        }
      }
      if ((min($compare) < 3) &&
        ($cdx['has_cedex'] === $tmp['has_cedex']) &&
        ($cdx['num_cedex'] === $tmp['num_cedex'])
      ) {
        return [
          'ville' => $tmp['ville2'],
          'cp' => $cp
        ];
      } else {
        $inf = $this->db->rselect('apst_cp', [], [
          'cp' => $cp,
          'ville' => $tmp['ville2']
        ]);
        $inf['ville'] = $cdx['ville'];
        if ($cdx['has_cedex']) {
          $inf['ville'] .= ' Cedex';
          if ($cdx['num_cedex']) {
            $inf['ville'] .= ' ' . $cdx['num_cedex'];
          }
        }
        if (array_key_exists('id', $inf)) {
          unset($inf['id']);
        }
        if ($this->db->insert("apst_cp", $inf)) {
          return [
            'ville' => $inf['ville'],
            'cp' => $inf['cp']
          ];
        }
      }
    }

    $dpt = Str::sub($cp, 0, 2);
    if ($dpt == 97) {
      $dpt = Str::sub($cp, 0, 3);
    } else if ($dpt == 20) {
      $dpt = $this->db->getOne(
        "
        SELECT id_dpt
        FROM apst_cp
        WHERE ville LIKE ?
        AND id_dpt LIKE '2A'
        OR id_dpt LIKE '2B'
        LIMIT 1",
        $ville
      );
      if (!$dpt) {
        $dpt = '2B';
      }
    }

    if ($dpt) {
      $ville_cond = $this->getCityCondition($ville, 1);
      // Sinon on la rajoute
      if ($inf = $this->db->getRow(
        "
        SELECT *
        FROM apst_cp
        WHERE id_dpt LIKE ?
        AND $ville_cond
        LIMIT 1",
        $dpt
      )) {

        $inf['cp'] = $cp;
        if ($cdx['has_cedex']) {
          $inf['ville'] .= ' Cedex';
          if ($cdx['num_cedex']) {
            $inf['ville'] .= ' ' . $cdx['num_cedex'];
          }
        }
        if (array_key_exists('id', $inf)) {
          unset($inf['id']);
        }
        if ($this->db->insert("apst_cp", $inf)) {
          return [
            'ville' => $inf['ville'],
            'cp' => $inf['cp']
          ];
        }
      }
    }
    return [
      'cp' => empty($cp) ? '00000' : $cp,
      'ville' => empty($ville) ? 'Inconnue' : $ville
    ];
  }

  public function add($fn, $force = false)
  {
    $id = false;
    $f = $this->getClassCfg()['arch']['addresses'];
    $fn = $this->set_address($fn);
    if (!empty($fn['id_country'])) {
      if ($this->entities && ($fn['id_country'] === $this->options()->fromCode($this->entities->getDefaultCountry(), 'countries', 'appui', 'core'))
        && ($conf_ville = $this->getCity(empty($fn[$f['postcode']]) ? '' : $fn[$f['postcode']], empty($fn[$f['city']]) ? '' : $fn[$f['city']]))
      ) {
        $fn[$f['postcode']] = $conf_ville['cp'];
        $fn[$f['city']] = $conf_ville['ville'];
      }
      if ($force || !($id = $this->search($fn))) {
        if ($this->db->insert("bbn_addresses", $fn)) {
          $id = $this->db->lastId();
        }
      }
    }
    return $id;
  }

  public function update($id, $fn)
  {

    if ($info = $this->getInfo($id)) {
      $fields = array_keys($this->db->getColumns('bbn_addresses'));
      foreach ($fn as $k => $v) {
        if (!in_array($k, $fields)) {
          unset($fn[$k]);
        }
      }
      if (isset($info['fadresse'])) {
        unset($info['fadresse']);
      }
      //$n count the property changed between $info and $fn
      $changed = false;
      foreach ($info as $i => $val) {
        if (\array_key_exists($i, $fn) && ($val !== $fn[$i])) {
          $changed = true;
          break;
        }
      }
      if (!$changed) {
        return $id;
      } else if ((count($fn) > 0) && $this->db->update('bbn_addresses', $fn, ['id' => $id])) {
        return $id;
      }
    }
    return false;
  }

  public function set_address($fn)
  {
    $f = $this->getClassCfg()['arch']['addresses'];
    $r = [];

    if (is_array($fn)) {
      if (!is_array($fn[$f['address']])) {
        $fn[$f['address']] = explode("\n", $fn[$f['address']]);
      }
      if (is_array($fn[$f['address']])) {
        $r[$f['address']] = array_filter($fn[$f['address']], function ($ad) {
          return !empty($ad) && Str::len($ad) > 1;
        });
        if (count($r[$f['address']]) > 0) {
          // On enlève la virgule après le numéro de rue si elle y est
          $r[$f['address']] = preg_replace("#^(\\d+),#", "\$1", implode("\n", $r[$f['address']]));
        } else {
          unset($r[$f['address']]);
        }
      }
      if (isset($fn[$f['postcode']])) {
        $r[$f['postcode']] = (int) Str::getNumbers($fn[$f['postcode']]);
      }
      if (isset($fn['id_country'])) {
        $r['id_country'] = $fn['id_country'];
      }
      $r[$f['city']] = empty($fn[$f['city']]) ? '' : Str::changeCase($fn[$f['city']]);
      if (isset($fn[$f['phone']])) {
        $fn[$f['phone']] = Str::getNumbers($fn[$f['phone']]);
        if (Str::len($fn[$f['phone']]) > 10 && Str::pos($fn[$f['phone']], '33') === 0) {
          $fn[$f['phone']] = Str::sub($fn[$f['phone']], 2);
        }
        if (Str::len($fn[$f['phone']]) === 9 && Str::pos($fn[$f['phone']], '0') !== 0) {
          $fn[$f['phone']] = '0' . $fn[$f['phone']];
        }
        if (Str::len($fn[$f['phone']]) === 10) {
          $r[$f['phone']] = $fn[$f['phone']];
        }
      }
    }
    return $r;
  }

  public function fadresse($s, $with_br = 1, array $excludedCountries = []): string
  {
    if (Str::isUid($s)) {
      $s = $this->getInfo($s);
    }
    if (is_array($s)) {
      $f = $this->getClassCfg()['arch']['addresses'];
      $st = '';
      if (!empty($s[$f['address']])) {
        $st .= nl2br($s[$f['address']], false) . '<br>';
      }
      if (!empty($s[$f['postcode']])) {
        $st .= $s[$f['postcode']] . ' ';
      }
      if (!empty($s[$f['city']])) {
        $st .= $s[$f['city']];
      }
      if (!$with_br) {
        return str_replace('<br>', ', ', $st);
      }
      $excl = [];
      foreach ($excludedCountries as $c) {
        if (is_string($c)) {
          $excl[] = $this->options()->fromCode(strtoupper($c), 'countries', 'core', 'appui');
        }
      }
      if (!empty($s['id_country']) && !in_array($s['id_country'], $excl)) {
        $st .= '<br>' . $this->options()->text($s['id_country']);
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
  public function fusion($ids)
  {
    $args = is_array($ids) ? $ids : func_get_args();
    if (count($args) > 1) {
      $id = array_shift($args);
      $creation = [$this->db->selectOne('bbn_history', 'tst', [
        'uid' => $id,
        'opr' => 'INSERT'
      ])];
      foreach ($args as $a) {
        if ($fn = $this->getInfo($a)) {
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
          foreach ($links as $link) {
            $this->db->update('bbn_entities_links', ['id_address' => $id], ['id' => $link['id']]);
          }
          $this->db->query(
            "
            UPDATE bbn_history
            SET uid = ?
            WHERE uid = ?
            AND opr LIKE 'UPDATE'",
            hex2bin($id),
            hex2bin($a)
          );
          $this->db->query(
            "
            DELETE FROM bbn_history
            WHERE uid = ?",
            hex2bin($a)
          );
          $this->db->query(
            "
            DELETE FROM bbn_addresses
            WHERE id = ?",
            hex2bin($a)
          );
        }
      }
      $this->db->query(
        "
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
      $arc = &$this->class_cfg['arch']['identities'];
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
