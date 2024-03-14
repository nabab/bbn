<?php
namespace bbn\Entities;

use PHPSQLParser\Test\Parser\variablesTest;

class People
{

  private
    $db,
    $options;

  static private $civs = [
    'M/MME' => 'M/MME',
    'M' => 'M',
    'MR' => 'M',
    'ML' => 'MLLE',
    'MME' => 'MME',
    'ME' => 'ME',
    'MONSIEUR' => 'M',
    'MLLE' => 'MLLE',
    'MR/ME' => 'M/MME',
    'M.' => 'M',
    'MLE' => 'MLLE',
    'MMES' => 'MMES',
    'M/MES' => 'M/MME',
    'MM' => 'MM',
    'MLL' => 'MLLE',
    'MMME' => 'MME',
    'MES' => 'MM',
    'M/ME' => 'M/MME',
    'MRME' => 'M',
    'ME/MR' => 'ME',
    'MME/M' => 'M/MME',
    'MLLES' => 'MMES',
    'MADAME' => 'MME',
    'MADEMOISELLE' => 'MLLE',
    'MM/ME' => 'M/MME',
    'MRR' => 'M',
    '*M/MME' => 'M/MME',
    'FAMILLE' => 'M/MME',
    'MS/MME' => 'M/MME',
    'MR/' => 'M',
    'MRS' => 'MM',
    'M.MME' => 'M/MME',
    'MRS/MMES' => 'MM',
    '**MME' => 'MME'
    ],
    $civilites         = [
      'M' => 'Monsieur',
      'MME' => 'Madame',
      'MLLE' => 'Mademoiselle',
      'M/MME' => 'Madame/Monsieur',
      'ME' => 'Maître',
      'MMES' => 'Mesdames',
      'MM' => 'Messieurs'
    ],
    $stes              = ['STE', 'CIE', 'SAS', 'SCI', 'SC', 'SA', 'SARL', 'EURL'];


  public function __construct(\bbn\Db $db)
  {
      $this->db      = $db;
      $this->options = \bbn\Appui\Option::getInstance();

  }


  public function fnom($s, $full = false)
  {
    if (\bbn\Str::isUid($s)) {
      $s = $this->get_info($s);
    }

    if (is_array($s) && isset($s['nom'])) {
      $st = '';
      if (!empty($s['civilite'])) {
        if ($full && isset(self::$civilites[$s['civilite']])) {
          $st .= self::$civilites[$s['civilite']].' ';
        }
        else{
          $st .= $s['civilite'].' ';
        }
      }

            $st .= $s['nom'];
      if (!empty($s['prenom'])) {
        $st .= ' '.$s['prenom'];
      }

      return $st;
    }

    return null;
  }


  public function get_info($id, $id_adherent=0)
  {
    $d = $this->db->rselect("bbn_people", [], ['id' => $id]);
    if ($d) {
      if (!empty($d['portable'])) {
        $d['portable'] = (string)$d['portable'];
      }

      if (isset($d['cfg'])) {
        $d['cfg'] = json_decode($d['cfg'], true);
        if (is_array($d['cfg'])) {
          foreach ($d['cfg'] as $i => $val){
            $d[$i] = $val;
          }
        }

        unset($d['cfg']);
      }

      $d['fnom']  = $this->fnom($d);
      $d['ffnom'] = $this->fnom($d, 1);
      if (!isset($d['inscriptions'])) {
        $d['inscriptions'] = [];
      }

      if ($id_adherent) {
        $d['roles'] = $this->db->getColumnValues(
          [
          'tables' => ['apst_liens'],
          'fields' => ['bbn_options.text'],
          'join' => [
            [
              'table' => 'bbn_people',
              'on' => [
                'conditions' => [
                  [
                    'field' => 'bbn_people.id',
                    'operator' => 'eq',
                    'exp' => 'apst_liens.id_tiers'
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
                    'exp' => 'apst_liens.link_type'
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
          ]
        );
      }
    }

        return $d;
  }


  public function search($fn)
  {
    $fn = $this->set_info($fn);
    if (!empty($fn['fnom'])) {
      return $this->db->selectOne(
        'bbn_addresses', 'id', [
        'cp' => $fn['cp'],
        'adresse' => $fn['adresse']
        ]
      );
        $h = $this->db->getOne(
          "
				SELECT id
				FROM bbn_people
        WHERE TRIM(
          IF ( civilite IS NULL, nom, CONCAT(civilite, ' ', prenom, ' ',nom) )
        ) LIKE ?
        OR ( nom LIKE ? AND prenom LIKE ? )
				LIMIT 1",
          $fn['fnom'],
          $fn['nom'],
          $fn['prenom']
        );
        return $h;
    }

      return false;
  }


  public function seek($p, int $start = 0, int $limit = 100)
  {
    if (is_array($p) && ( !empty($p['nom'])
        || !empty($p['email'])
        || !empty($p['portable']) )
    ) {
      $cond = [];
      if (!empty($p['email']) && \bbn\Str::isEmail($p['email'])) {
        array_push($cond, ['email', 'LIKE', $p['email']]);
      }

      if (!empty($p['nom'])) {
        array_push($cond, ['nom', 'LIKE', $p['nom']]);
      }

      if (!empty($p['portable']) && (strlen($p['portable']) >= 6)) {
        if (strlen($p['portable']) !== 10) {
          array_push($cond, ['portable', 'LIKE', $p['portable'].'%']);
        }
        else{
          array_push($cond, ['portable', 'LIKE', $p['portable']]);
        }
      }

      if (!empty($p['prenom'])) {
        array_push($cond, ['prenom', 'LIKE', $p['prenom']]);
      }

      return $this->db->getColumnValues("bbn_people", 'id', $cond, ['nom', 'prenom'], $limit, $start);
    }

      return false;
  }


  public function full_search($p, int $start = 0, int $limit = 0)
  {
    $r   = [];
    $res = \bbn\Str::isUid($p) ? [$p] : $this->seek($p, $start, $limit);
    if ($res) {
    }

    foreach ($res as $i => $id){
        $relations        = $this->db->getColumnValues(
          [
          'tables' => ['apst_liens'],
          'fields' => ['apst_adherents.nom'],
          'join' => [[
            'table' => 'bbn_people',
            'on' => [[
              'field' => 'apst_liens.id_tiers',
              'exp' => 'bbn_people.id'
            ]]
          ], [
            'table' => 'apst_adherents',
            'on' => [[
              'field' => 'apst_liens.id_adherent',
              'exp' => 'apst_adherents.id'
            ]]
          ]],
          'where' => [
            'id_tiers' => $id
          ]
          ]
        );
      $r[$i]              = $this->get_info($id);
      $r[$i]['relations'] = implode($relations, ', ');
    }

    return $r;
  }


  public function relations($id)
  {
    if ($this->get_info($id)) {
      return $this->db->selectAllByKeys(
        [
        'tables' => ['apst_liens'],
        'fields' => ['bbn_people.id', 'id_adherent'],
        'join' => [[
          'table' => 'bbn_people',
          'on' => [
            'conditions' => [[
              'field' => 'apst_liens.id_tiers',
              'exp' => 'bbn_people.id'
            ]]
          ]]
        ],
        'where' => ['id_tiers' => $id]
        ]
      );
    }

    return false;
  }


  /*
   * Crée un tiers ou retourne l'ID du tiers si il existe déjà
   *
   * si $date a la valeur 1, le tiers sera ajouté même si il trouve un tiers identique
   */
  public function add($fn, $force=false)
  {
    //$id = false;
    $fields       = array_keys($this->db->getColumns('bbn_people'));
    $extra_fields = [];
    $not_cfg      = ['id_lieu', 'roles', 'id_adherent', 'fnom', 'fonctions', 'licence', 'montant', 'parts', 'effet', 'adherents', 'types_liens', 'id_option', 'suggestions', 'relations', 'ffnom', 'id_tier_ext', 'wp_group', 'is_admin'];
    $wp_group     = false;
    $id_adh       = false;
    if ($fn = $this->set_info($fn)) {
      if ($force) {
        foreach ($fn as $k => $v) {
          if (!in_array($k, $fields)) {
            //properties arriving in $fn but not to insert in cfg column of bbn_people
            if ($fn[$k] && !in_array($k, $not_cfg)) {
              if (($k !== 'inscriptions') || (!empty($v) && \bbn\Str::isEmail($fn['email']))) {
                $fn['cfg'][$k] = $fn[$k];
              }
            }

            if ($k === 'wp_group') {
              $wp_group = $v;
            }

            if ($k === 'id_adherent') {
              $id_adh = $v;
            }

            unset($fn[$k]);
          }
        }

        $fn['cfg'] = !empty($fn['cfg']) ? json_encode($fn['cfg']) : null;
        if (isset($fn['id']) && ($fn['id'] === '')) {
                    unset($fn['id']);
        }

        if ($this->db->insert("bbn_people", $fn)) {
          $id = $this->db->lastId();
          if (!empty($wp_group) && !empty($id_adh)) {
            $adh = new \apst\adherent($this->db, $id_adh);
            if ($adh->check()) {
              $adh->wps_add_contact(
                [
                'id' => $id,
                'group' => $wp_group,
                'email' => $fn['email'],
                'nom' => $this->db->selectOne('bbn_people', 'fullname', ['id' => $id])
                ]
              );
            }
          }
        }
      }

            return $id;
    }

    return false;
  }


  public function update($id, $fn)
  {
    if ($this->get_info($id)) {
      $fields   = array_keys($this->db->getColumns('bbn_people'));
      $not_cfg  = ['id_lieu', 'roles', 'id_adherent', 'fnom', 'fonctions', 'licence', 'montant', 'parts', 'effet', 'adherents', 'types_liens', 'id_option', 'suggestions', 'relations', 'ffnom', 'id_tier_ext', 'wp_group', 'is_admin'];
      $wp_group = false;
      $id_adh   = false;
      $wp       = false;
      foreach ($fn as $k => $v){
        if (!in_array($k, $fields) || ($k === 'id')) {
          if(!in_array($k, $not_cfg) && ($k !== 'id')) {
            if ($k === 'inscriptions') {
              if (!empty($v) && \bbn\Str::isEmail($fn['email'])) {
                $fn['cfg'][$k] = $v;
              }
            }
            else {
              $fn['cfg'][$k] = (string)$fn[$k];
            }
          }

          if ($k === 'wp_group') {
            $wp_group = $v;
          }

          if ($k === 'id_adherent') {
            $id_adh = $v;
          }

          unset($fn[$k]);
        }
      }

      $fn['cfg'] = !empty($fn['cfg']) ? json_encode($fn['cfg']) : null;
      if (!empty($wp_group) && !empty($id_adh)) {
        $adh = new \apst\adherent($this->db, $id_adh);
        if ($adh->check()) {
          $people = $this->db->rselect('bbn_people', ['email', 'fullname'], ['id' => $id]);
          if (!empty($fn['email']) || !empty($people['email'])) {
            if ($adh->wps_has_contact($id)) {
              $wp = $adh->wps_update_contact(
                [
                'id' => $id,
                'group' => $wp_group,
                'email' => $fn['email'] ?? $people['email'],
                'nom' => $people['fullname']
                ]
              );
            }
            else {
              $wp = $adh->wps_add_contact(
                [
                'id' => $id,
                'group' => $wp_group,
                'email' => $fn['email'] ?? $people['email'],
                'nom' => $people['fullname']
                ]
              );
            }
          }

          $adh->update_full();
        }
      }

      if (((count($fn) > 0) && $this->db->update('bbn_people', $fn, ['id' => $id]))
          || !empty($wp)
      ) {
        return $id;
      }
    }

    return false;
  }


  public function set_info($st, $email=false, $portable=false)
  {
    if (is_array($st)) {
            $fn = $st;
      if (!isset($fn['prenom']) && !isset($fn['civilite'])) {
          $st = $fn['nom'];
      }
    }
    else{
        $fn = [];
      if ($email) {
        $fn['email'] = $email;
      }

      if ($portable) {
        $fn['portable'] = $portable;
      }
    }

    if (is_string($st) && !empty($st)) {
        $fn['prenom'] = '';
        // Import: recherche, suppression et retour de commentaires entre parentheses
        preg_match('/\(([^\)]+)/', $st, $m);
      if (count($m) === 2) {
          $st            = substr($st, 0, strpos($st, $m[0]));
          $fn['comment'] = $m[1];
      }


      // array_values reinitializes the keys after array_filter
      $fullname = array_values(\bbn\X::removeEmpty(explode(" ",$st), 1));
      if (isset($fullname[0])) {
        if (isset(self::$civs[\bbn\Str::changeCase($fullname[0], 'upper')])) {
          $fn['civilite'] = self::$civs[\bbn\Str::changeCase($fullname[0], 'upper')];
          array_shift($fullname);
          // Cas M MME
          if (isset($fullname[0], self::$civs[\bbn\Str::changeCase($fullname[0], 'upper')])) {
                  $fn['civilite'] = 'M/MME';
                  array_shift($fullname);
          }

          if (!isset($fullname[0])) {
                return false;
          }
        }

          // Cas STE
        if (isset($fullname[0]) && in_array($fullname[0], self::$stes)) {
            $fn['nom'] = implode(" ", $fullname);
        }
        elseif (( count($fullname) === 3 ) && strlen($fullname[0]) <= 3) {
            $fn['nom']    = \bbn\Str::changeCase(\bbn\Str::changeCase($fullname[0].' '.$fullname[1], 'lower'));
            $fn['prenom'] = \bbn\Str::changeCase(\bbn\Str::changeCase($fullname[2], 'lower'));
        }
        elseif (count($fullname) > 2) {
          if (isset($fn['civilite'])) {
              $fn['prenom'] = \bbn\Str::changeCase(\bbn\Str::changeCase(array_pop($fullname), 'lower'));
              $fn['nom']    = \bbn\Str::changeCase(\bbn\Str::changeCase(implode(" ", $fullname), 'lower'));
          }
          else{
              $fn['nom'] = \bbn\Str::changeCase(\bbn\Str::changeCase(implode(" ", $fullname), 'lower'));
          }
        }
        elseif (count($fullname) < 2 || !isset($fullname[1])) {
            $fn['nom'] = $fullname[0];
        }
        else{
            $fn['prenom'] = \bbn\Str::changeCase(\bbn\Str::changeCase($fullname[1], 'lower'));
            $fn['nom']    = \bbn\Str::changeCase(\bbn\Str::changeCase($fullname[0], 'lower'));
        }
      }
    }

    if (is_array($fn)) {
      if (!isset($fn['prenom'])) {
          $fn['prenom'] = '';
      }

      if (!isset($fn['civilite'])) {
          $fn['civilite'] = empty($fn['prenom']) ? null : 'M';
      }

        $fn['fnom'] = $this->fnom($fn);
      if (isset($fn['email']) && !\bbn\Str::isEmail($fn['email'])) {
          unset($fn['email']);
      }

      if (isset($fn['tel'])) {
          $fn['portable'] = $fn['tel'];
          unset($fn['tel']);
      }

      if (isset($fn['portable'])) {
          $fn['portable'] = \bbn\Str::getNumbers($fn['portable']);
        if (strlen($fn['portable']) > 10 && strpos($fn['portable'], '33') === 0) {
            $fn['portable'] = substr($fn['portable'], 2);
        }

        if (strlen($fn['portable']) === 9 && strpos($fn['portable'], '0') !== 0) {
            $fn['portable'] = '0'.$fn['portable'];
        }

        if (strlen($fn['portable']) !== 10) {
            unset($fn['portable']);
        }
      }

      if (!isset($fn['nom'])) {
          $fn = [];
      }

      return $fn;
    }

      return false;
  }


  /*
   * Fusionne l'historique de différents tiers et les supprime tous sauf le premier
   *
   * @var mixed $ids Un tableau d'IDs ou une liste d'arguments
   */
  public function fusion($ids)
  {
    $args          = is_array($ids) ? $ids : func_get_args();
    $fonction_lien = $this->options->fromCode('fonction', 'LIENS');
    if (count($args) > 1) {
      $id       = array_shift($args);
      $creation = [$this->db->selectOne(
        'bbn_history', 'tst', [
        'uid' => $id,
        'opr' => 'INSERT'
        ]
      )];
      foreach ($args as $a){
        if ($fn = $this->get_info($a)) {
          $creation[]       = $this->db->getOne(
            "
            SELECT tst
            FROM bbn_history
            WHERE uid = ?
            AND opr LIKE 'INSERT'",
            hex2bin($a)
          );
          $cols             = $this->db->getFieldsList('apst_liens');
          $cols['creation'] = 'tst';
          $links            = $this->db->rselectAll(
            [
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
              'id_tiers' => $a
            ]
            ]
          );
          foreach ($links as $link){
                        $link_update = ['id_tiers' => $id];
                        // Si les liens sont de type `Fonction` on les fusionne aussi de la même manière que les tiers
            if (($link['link_type'] === $fonction_lien)
                && ($autre = $this->db->getRow(
                  "
								SELECT apst_liens.id, apst_liens.id_adherent, apst_liens.cfg, h.tst AS creation
								FROM apst_liens
									JOIN bbn_history AS h
										ON h.uid = apst_liens.id
										AND h.opr LIKE 'INSERT'
									JOIN bbn_history_uids
									  ON bbn_history_uids.bbn_uid = apst_liens.id
									  AND bbn_history_uids.bbn_active = 1
								WHERE apst_liens.id_tiers = ?
                  AND apst_liens.id_adherent = ?
                  AND apst_liens.link_type = ?",
                  hex2bin($a),
                  $link['id_adherent'],
                  hex2bin($fonction_lien)
                )                )
            ) {
                $link_cfg = json_decode($link['cfg'], 1);
                // On met la date de création la plus ancienne
              if (strtotime($autre['creation']) < strtotime($link['creation'])) {
                            $this->db->query(
                              "
									UPDATE bbn_history
									SET tst = ?
									WHERE uid = ?
									AND opr LIKE 'INSERT'",
                              $autre['creation'],
                              hex2bin($link['id'])
                            );
              }

                            $this->db->query(
                              "
								UPDATE bbn_history
								SET uid = ?
								WHERE uid = ?
								AND opr LIKE 'UPDATE'",
                              hex2bin($link['id']),
                              hex2bin($autre['id'])
                            );
                            $this->db->query(
                              "
								DELETE FROM bbn_history
								WHERE uid = ?",
                              hex2bin($autre['id'])
                            );
                            $autre_cfg = json_decode($autre['cfg'], 1);
              if ($autre_cfg && $link_cfg && isset($autre_cfg['fonctions'], $link_cfg['fonctions'])) {
                  $link_update['cfg'] = json_encode(
                    [
                      'fonctions' => array_unique(\bbn\X::mergeArrays($autre_cfg['fonctions'], $link_cfg['fonctions']))
                    ]
                  );
              }

                            $this->db->query("DELETE FROM bbn_history_uids WHERE uid = ?", hex2bin($autre['id']));
            }

                        $this->db->update('apst_liens', $link_update, ['id' => $link['id']]);
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
            DELETE FROM bbn_people
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


  /**
   * Supprime un lieu et tous ses liens si précisé
   * Si non précisé et que le lieu a des liens, il n'est pas supprimé
   *
   * @var int $id l'ID du lieu
   * @var bool $with_links si précisé tous ses liens sont également précisés
   *
   * @return bool Succès ou pas de la suppression
   */
  public function delete($id, $with_links = false)
  {
    if ($this->get_info($id)) {
      $rels = $this->relations($id);

      if ($with_links || empty($rels)) {
        foreach ($rels as $k => $r){
          $this->db->delete('apst_liens', ['id' => $k]);
          $adh = new \apst\adherent($this->db, $r);
          $adh->wps_delete_contact($id);
        }

        return $this->db->delete('bbn_people', ['id' => $id]);
      }
    }

      return false;
  }


}
