<?php

namespace bbn\Entities;

use Exception;
use PHPSQLParser\Test\Parser\variablesTest;
use bbn\X;
use bbn\Str;
use bbn\Db;
use bbn\Appui\History;
use bbn\Appui\Option;
use bbn\Models\Tts\Dbconfig;
use bbn\Models\Cls\Db as DbCls;

/**
 * The People class represents entities in a 'bbn_people' table
 * and provides methods to manipulate these entities, including
 * CRUD operations, search, and relation management, tailored for French civilities.
 */
class People extends DbCls
{
  use Dbconfig;

  /**
   * The default configuration for database interaction, specifying the table and fields.
   */
  protected static $default_class_cfg = [
    'table' => 'bbn_people',
    'arch' => [
      'people' => [
        'id' => 'id',
        'civility' => 'civility',
        'name' => 'name',
        'fname' => 'fname',
        'fullname' => 'fullname',
        'email' => 'email',
        'mobile' => 'mobile',
        'cfg' => 'cfg',
      ]
    ],
    'tables' => [
      'people' => 'bbn_people'
    ]
  ];


  /**
   * A mapping of alternate civility representations to standard forms.
   */
  protected static $notCfg = [];

  protected $options;

  private $tableRelations;

  /**
   * A mapping of alternate civility representations to standard forms.
   */
  protected static $civs = [
  ];
  /**
   * A list of formal civility titles in French.
   */
  protected static $civilities = [
    'M' => 'Mister',
    'Mrs' => 'Madamn',
    'Miss' => 'Miss'
  ];
  /**
   * An array of company types, useful for parsing names.
   */
  protected static $stes = [];


  /**
   * A mapping of alternate civility representations to standard forms.
   */
  public function __construct(Db $db, array $cfg = null)
  {
    parent::__construct($db);
    $this->_init_class_cfg($cfg);
    $this->options = Option::getOptions();
  }


  /**
   * Processes information for a person based on input.
   *
   * @param mixed $st Input data.
   * @param bool $email Optional email for the person.
   * @param bool $mobile Optional mobile number for the person.
   * @return mixed Processed person data.
   */
  public function parse(string $st, $email = false, $mobile = false): ?array
  {
    $arc = &$this->class_cfg['arch']['people'];
    if (!empty($st)) {
      $fn = [];
      $fn[$arc['fname']] = '';
      // Import: recherche, suppression et retour de commentaires entre parentheses
      preg_match('/\(([^\)]+)/', $st, $m);
      if (count($m) === 2) {
        $st            = substr($st, 0, strpos($st, $m[0]));
        $fn['comment'] = $m[1];
      }

      // array_values reinitializes the keys after array_filter
      $fullname = array_values(X::removeEmpty(explode(" ", $st), 1));
      if (isset($fullname[0])) {
        if (isset(self::$civs[Str::changeCase($fullname[0], 'upper')])) {
          $fn[$arc['civility']] = self::$civs[Str::changeCase($fullname[0], 'upper')];
          array_shift($fullname);
          if (isset($fullname[0], self::$civs[Str::changeCase($fullname[0], 'upper')])) {
            $fn[$arc['civility']] = 'M/MME';
            array_shift($fullname);
          }

          if (!isset($fullname[0])) {
            return null;
          }
        }

        // Cas STE
        if (isset($fullname[0]) && in_array($fullname[0], self::$stes)) {
          $fn[$arc['name']] = implode(" ", $fullname);
        } elseif ((count($fullname) === 3) && strlen($fullname[0]) <= 3) {
          $fn[$arc['name']]    = Str::changeCase(Str::changeCase($fullname[0] . ' ' . $fullname[1], 'lower'));
          $fn[$arc['fname']] = Str::changeCase(Str::changeCase($fullname[2], 'lower'));
        } elseif (count($fullname) > 2) {
          if (isset($fn[$arc['civility']])) {
            $fn[$arc['fname']] = Str::changeCase(Str::changeCase(array_pop($fullname), 'lower'));
            $fn[$arc['name']]    = Str::changeCase(Str::changeCase(implode(" ", $fullname), 'lower'));
          } else {
            $fn[$arc['name']] = Str::changeCase(Str::changeCase(implode(" ", $fullname), 'lower'));
          }
        } elseif (count($fullname) < 2 || !isset($fullname[1])) {
          $fn[$arc['name']] = $fullname[0];
        } else {
          $fn[$arc['fname']] = Str::changeCase(Str::changeCase($fullname[1], 'lower'));
          $fn[$arc['name']]    = Str::changeCase(Str::changeCase($fullname[0], 'lower'));
        }
      }

      if ($email) {
        $fn[$arc['email']] = $email;
      }

      if ($mobile) {
        $fn[$arc['mobile']] = $mobile;
      }

      return $fn;
    }

    return null;
  }

  /**
   * Processes and sets information for a person based on input.
   *
   * @param array $fn Input data.
   * @param bool $email Optional email for the person.
   * @param bool $mobile Optional mobile number for the person.
   * @return mixed Processed person data.
   */
  public function set_info(array $fn): ?array
  {
    $arc = &$this->class_cfg['arch']['people'];
    if (!empty($fn)) {
      if (!isset($fn[$arc['fname']])) {
        $fn[$arc['fname']] = '';
      }

      if (!isset($fn[$arc['civility']])) {
        $fn[$arc['civility']] = empty($fn[$arc['fname']]) ? null : 'M';
      }

      if (isset($fn[$arc['email']]) && !Str::isEmail($fn[$arc['email']])) {
        unset($fn[$arc['email']]);
      }

      if (isset($fn['tel'])) {
        $fn[$arc['mobile']] = $fn['tel'];
        unset($fn['tel']);
      }

      if (isset($fn[$arc['mobile']])) {
        $fn[$arc['mobile']] = Str::getNumbers($fn[$arc['mobile']]);
        if (strlen($fn[$arc['mobile']]) > 10 && strpos($fn[$arc['mobile']], '33') === 0) {
          $fn[$arc['mobile']] = substr($fn[$arc['mobile']], 2);
        }

        /** @todo A proper phone number check system */
        if (strlen($fn[$arc['mobile']]) === 9 && strpos($fn[$arc['mobile']], '0') !== 0) {
          $fn[$arc['mobile']] = '0' . $fn[$arc['mobile']];
        }

        if (strlen($fn[$arc['mobile']]) !== 10) {
          unset($fn[$arc['mobile']]);
        }
      }

      if (!isset($fn[$arc['name']])) {
        $fn = [];
      }

      return $fn;
    }

    return null;
  }


  /**
   * Retrieves detailed information about a person by ID.
   *
   * @param mixed $id The ID of the person.
   * @return array|null Detailed information about the person.
   */
  public function get_info($id): array
  {
    $arc = &$this->class_cfg['arch']['people'];
    $d = $this->rselect($id);
    if (!$d) {
      throw new Exception(_("Impossible to find the people"));
    }
    if (!empty($d[$arc['mobile']])) {
      $d[$arc['mobile']] = (string)$d[$arc['mobile']];
    }

    if (isset($d[$arc['cfg']])) {
      $d[$arc['cfg']] = json_decode($d[$arc['cfg']], true);
      if (is_array($d[$arc['cfg']])) {
        foreach ($d[$arc['cfg']] as $i => $val) {
          if (!isset($d[$i])) {
            $d[$i] = $val;
          }
        }
      }

      unset($d[$arc['cfg']]);
    }

    return $d;
  }


  /**
   * Performs a search based on a given full name.
   *
   * @param string $fn The full name to search for.
   * @return string|null The ID of the person found.
   */
  public function search($fn): ?string
  {
    $arc = &$this->class_cfg['arch']['people'];
    $fn = $this->set_info($this->parse($fn));
    if (!empty($fn[$arc['fullname']])) {
      $conditions = [
        'logic' => 'OR',
        'conditions' => [
          [
            'field' => $arc['fullname'],
            'operator' => 'contains',
            'value' => $fn[$arc['fullname']]
          ], [
            'logic' => 'AND',
            'conditions' => [
              [
                'field' => $arc[$arc['name']],
                'operator' => 'LIKE',
                'value' => $fn[$arc['name']]
              ], [
                'field' => $arc['fname'],
                'operator' => 'LIKE',
                'value' => $fn[$arc['fname']]
              ]
            ]
          ]
        ]
      ];
      if (!empty($fn[$arc['email']]) || !empty($fn[$arc['mobile']])) {
        $tmp = [
          'logic' => 'AND',
          'conditions' => []
        ];
        if (!empty($fn[$arc['email']])) {
          $tmp['conditions'][] = [
            'field' => $arc['email'],
            'operator' => 'LIKE',
            'value' => $fn[$arc['email']]
          ];
        }
        if (!empty($fn[$arc['mobile']])) {
          $tmp['conditions'][] = [
            'field' => $arc['mobile'],
            'operator' => 'LIKE',
            'value' => $fn[$arc['mobile']]
          ];
        }

        $tmp['conditions'][] = $conditions;
        $conditions = $tmp;
      }

      return $this->selectOne($arc['id'], $conditions);
    }

    return null;
  }


  /**
   * Conducts a full search for people records.
   *
   * @param mixed $p Search parameters or a single UID.
   * @param int $start Pagination start.
   * @param int $limit Pagination limit.
   * @return array The full search results.
   */
  public function full_search($p, int $start = 0, int $limit = 0)
  {
    $arc = &$this->class_cfg['arch']['people'];
    $r   = [];
    $res = Str::isUid($p) ? [$p] : $this->seek($p, $start, $limit);
    if ($res) {
      foreach ($res as $i => $id) {
        $r[$i] = $this->get_info($id);
      }
    }

    return $r;
  }


  /**
   * Retrieves relations of a person based on their ID.
   *
   * @param mixed $id The ID of the person.
   * @return mixed The relations of the person.
   */
  public function relations($id, string $table = null): ?array
  {
    $arc = &$this->class_cfg['arch']['people'];
    if ($this->get_info($id)) {
      $db =& $this->db;
      return array_values(array_filter($this->getTableRelations(), function($a) use ($db, $id) {
        return $db->count($a['table'], [$a['col'] => $id]);
      }));
    }

    return null;
  }



  /**
   * Adds or updates a person record in the database.
   *
   * @param mixed $fn The person data to add.
   * @param bool $force Whether to forcefully add the person.
   * @return string|null The ID of the added or updated person.
   */
  public function add($fn, $force = false): ?string
  {
    $arc = &$this->class_cfg['arch']['people'];
    $id = null;
    if ($fn = $this->set_info($fn)) {
      if (!empty($fn[$arc['email']]) && $this->count([$arc['email'] => $fn[$arc['email']]])) {
        throw new Exception(X::_("The email is already in use"));
      }

      if ($force || !$this->search($fn)) {
        $fn = $this->prepareData($fn);
        if (!empty($fn[$arc['name']])) {
          $id = $this->insert($fn);
        }
      }
    }

    return $id;
  }


  /**
   * Updates a person record in the database.
   *
   * @param mixed $id The ID of the person to update.
   * @param mixed $fn The new data for the person.
   * @return string|null The ID of the updated person.
   */
  public function update($id, $fn): ?int
  {
    $arc = &$this->class_cfg['arch']['people'];
    $ok = null;
    if ($this->get_info($id)) {
      $fn = $this->prepareData($fn);

      if (!empty($fn)) {
        $fn[$arc['cfg']] = empty($fn[$arc['cfg']]) ? null : json_encode($fn[$arc['cfg']]);
        $ok = $this->update($id, $fn);
      }

    }


    return $ok;
  }


  /**
   * Merges the history of multiple person records.
   *
   * @param mixed $ids IDs of the person records to merge.
   * @return int Result of the merge operation.
   */
  public function fusion($ids)
  {
    $arc = &$this->class_cfg['arch']['people'];
    $hasHistory = History::isLinked($this->class_cfg['table']);
    if ($hasHistory) {
      History::disable();
    }

    $args = is_array($ids) ? $ids : func_get_args();
    $res = 0;
    if (count($args) > 1) {
      $id = null;
      $oldest = null;
      foreach ($args as $i => $a) {
        if ($hasHistory) {
          $tmp = History::getCreationDate($this->class_cfg['table'], $a);
          if (!$oldest || ($tmp < $oldest)) {
            $oldest = $tmp;
            $id = $a;
          }
        }
        elseif ($this->db->selectOne('apst_adherents', 'id', ['id_admin' => $a])) {
          $id = $a;
          break;
        }
      }

      if (\is_null($id)) {
        $id = array_shift($args);
      }
      else {
        \array_splice($args, array_search($id, $args), 1);
      }

      foreach ($args as $a) {
        if ($this->get_info($a)) {
          foreach ($this->getTableRelations() as $r) {
            if ($this->db->count($r['table'], [$r['col'] => $a])) {
              if (!$this->db->update($r['table'], [$r['col'] => $id], [$r['col'] => $a])) {
                $this->db->delete($r['table'], [$r['col'] => $a]);
              }
            }
          }
        }
      }

      $res = 0;
      if ($oldest) {
        $this->db->delete(
          'bbn_history',
          [
            'uid' => $id,
            'opr' => 'INSERT',
            ['tst', '>', $oldest]
          ]
        );
      }

      $num = 0;
      foreach ($this->db->getColumnValues(
        'bbn_history',
        'tst',
        [
          'uid' => $id,
          'opr' => 'DELETE'
        ],
        [
          'tst' => 'DESC'
        ]
      ) as $deltst) {
        if (
          $this->db->count(
            'bbn_history',
            [
              'uid' => $id,
              'opr' => 'RESTORE',
              ['tst', '>=', $deltst]
            ]
          ) > $num
        ) {
          $num++;
        } else {
          $this->db->delete(
            'bbn_history',
            [
              'uid' => $id,
              'opr' => 'DELETE',
              'tst' => $deltst
            ]
          );
        }
      }

      $res = 1;
    }

    History::enable();
    return $res;
  }


  /**
   * Deletes a person record and optionally all its related links.
   *
   * @param int $id The ID of the person to delete.
   * @param bool $with_links Whether to also delete related links.
   * @return bool Success or failure of the delete operation.
   */
  public function delete($id, $with_links = false)
  {
    $arc = &$this->class_cfg['arch']['people'];
    if ($this->get_info($id)) {
      $rels = $this->relations($id);
      if ($with_links || empty($rels)) {
        foreach ($rels as $r) {
          if (!empty($r['model']['null'])) {
            $this->db->update($r['table'], [$r['col'] => null], [$r['col'] => $id]);
          }
          else {
            if ($r['primary']) {
              $refs = $this->db->findReferences($r['primary'], $r['table']);
              foreach ($refs as $ref) {
                [$db, $table, $col] = X::split($ref, '.');
                if ($table !== $this->class_cfg['table']) {
                  $model = $this->db->modelize($table);
                  if (!empty($model['fields'][$col]['null'])) {
                    $this->db->update($table, [$col => null], [$col => $id]);
                  }
                  else {
                    $this->db->delete($table, [$col => $id]);
                  }
                }
              }
            }

            $this->db->delete($r['table'], [$r['col'] => $id]);
          }
        }

        $this->update($id, [$arc['email'] => null]);
        return $this->delete($id);
      }
    }

    return false;
  }


  protected function prepareData(array $fn): array
  {
    $arc = &$this->class_cfg['arch']['people'];
    foreach (self::$notCfg as $n) {
      if (array_key_exists($n, $fn)) {
        unset($fn[$n]);
      }
    }

    foreach ($fn as $k => $v) {
      if (!in_array($k, $arc)) {
        $fn[$arc['cfg']][$k] = is_array($fn[$k]) ? $fn[$k] : (string)$fn[$k];
        unset($fn[$k]);
      }

      if ($k === $arc['email']) {
        if (empty($v)) {
          $fn[$k] = null;
        }
        elseif (!Str::isEmail($v)) {
          throw new Exception(X::_("The email is not valid"));
        }
      }
    }

    $fn[$arc['cfg']] = !empty($fn[$arc['cfg']]) ? json_encode($fn[$arc['cfg']]) : null;
    if (isset($fn['id']) && ($fn['id'] === '')) {
      unset($fn['id']);
    }

    return $fn;
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
