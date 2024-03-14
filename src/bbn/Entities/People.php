<?php

namespace bbn\Entities;

use Exception;
use bbn\X;
use bbn\Str;
use bbn\Db;
use bbn\Appui\History;
use bbn\Models\Tts\DbActions;
use bbn\Models\Cls\Db as DbCls;
use bbn\Entities\Models\Entities;
use bbn\Models\Cls\Nullall;

/**
 * The People class represents entities in a 'bbn_people' table
 * and provides methods to manipulate these entities, including
 * CRUD operations, search, and relation management, tailored for French civilities.
 */
class People extends DbCls
{
  use DbActions;
  /**
   * The default configuration for database interaction, specifying the table and fields.
   */
  protected static $default_class_cfg = [
    'table' => 'bbn_people',
    'tables' => [
      'people' => 'bbn_people'
    ],
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
    ]
  ];

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
  public function __construct(
    Db $db, 
    protected Entities $entities,
    protected Entity|Nullall $entity = new Nullall()
  )
  {
    parent::__construct($db);
    $this->_init_class_cfg();
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
    if (!empty($st)) {
      $arc = &$this->class_cfg['arch']['people'];
      $fn = [];
      $fn[$arc['fname']] = '';

      // array_values reinitializes the keys after array_filter
      $nameParts = array_values(X::removeEmpty(explode(" ", $st), true));
      if (isset($nameParts[0])) {
        if (isset(self::$civs[Str::changeCase($nameParts[0], 'upper')])) {
          $fn[$arc['civility']] = self::$civs[Str::changeCase($nameParts[0], 'upper')];
          array_shift($nameParts);
          if (!isset($nameParts[0])) {
            return null;
          }
        }

        // Cas STE
        if (isset($nameParts[0]) && in_array($nameParts[0], self::$stes)) {
          $fn[$arc['name']] = implode(" ", $nameParts);
        }
        elseif ((count($nameParts) === 3) && strlen($nameParts[0]) <= 3) {
          $fn[$arc['name']]    = Str::changeCase(Str::changeCase($nameParts[0] . ' ' . $nameParts[1], 'lower'));
          $fn[$arc['fname']] = Str::changeCase(Str::changeCase($nameParts[2], 'lower'));
        }
        elseif (count($nameParts) > 2) {
          if (isset($fn[$arc['civility']])) {
            $fn[$arc['fname']] = Str::changeCase(Str::changeCase(array_pop($nameParts), 'lower'));
            $fn[$arc['name']]    = Str::changeCase(Str::changeCase(implode(" ", $nameParts), 'lower'));
          }
          else {
            $fn[$arc['name']] = Str::changeCase(Str::changeCase(implode(" ", $nameParts), 'lower'));
          }
        }
        elseif (count($nameParts) < 2 || !isset($nameParts[1])) {
          $fn[$arc['name']] = $nameParts[0];
        }
        else {
          $fn[$arc['fname']] = Str::changeCase(Str::changeCase($nameParts[1], 'lower'));
          $fn[$arc['name']]    = Str::changeCase(Str::changeCase($nameParts[0], 'lower'));
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
    $fn = $this->prepare($fn);
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
  public function getInfo($id): array
  {
    return $this->dbTraitRselect($id);
  }


  /**
   * Performs a search based on a given full name.
   *
   * @param array|string $fn The full name to search for.
   * @return string|null The ID of the person found.
   */
  public function search(array|string $fn): ?string
  {
    $arc = &$this->class_cfg['arch']['people'];
    $fn = $this->set_info(is_string($fn) ? $this->parse($fn) : $fn);
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
              ]
            ]
          ]
        ]
      ];
      if (!empty($fn[$arc['fname']])) {
        $conditions['conditions'][1]['conditions'][] = [
          'field' => $arc['fname'],
          'operator' => 'LIKE',
          'value' => $fn[$arc['fname']]
        ];
      }

      if (!empty($fn[$arc['email']]) || !empty($fn[$arc['mobile']])) {
        $tmp = [
          'logic' => 'AND',
          'conditions' => []
        ];
        if (!empty($fn[$arc['email']])) {
          $tmp['conditions'][] = [
            'logic' => 'OR',
            'conditions' => [[
              'field' => $arc['email'],
              'operator' => 'LIKE',
              'value' => $fn[$arc['email']]
            ], [
              'field' => $arc['email'],
              'operator' => 'isempty'
            ]]
          ];
        }

        if (!empty($fn[$arc['mobile']])) {
          $tmp['conditions'][] = [
            'logic' => 'OR',
            'conditions' => [[
              'field' => $arc['mobile'],
              'operator' => 'LIKE',
              'value' => $fn[$arc['mobile']]
            ], [
              'field' => $arc['mobile'],
              'operator' => 'isempty'
            ]]
          ];
        }

        $tmp['conditions'][] = $conditions;
        $conditions = $tmp;
      }
  
      return $this->dbTraitSelectOne($arc['id'], $conditions);
    }

    return null;
  }

	public function seek($p, int $start = 0, int $limit = 100){
    $arc = &$this->class_cfg['arch']['people'];
    if (!is_array($p)) {
      $p = $this->parse($p);
    }

    if (is_array($p) && (
        !empty($p[$arc['fullname']])
        || !empty($p[$arc['email']])
        || !empty($p[$arc['mobile']])
        || !empty($p[$arc['name']])
    )
    ){
      $cond = [];

      foreach ($arc as $v) {
        if ( !empty($p[$v]) ){
          array_push($cond, [$v, 'contains', $p[$v]]);
        }
      }

      return $this->dbTraitSelectValues($arc['id'], $cond, [$arc['fullname']], $limit, $start);
    }

    return false;
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
        $r[$i] = $this->getInfo($id);
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
  public function relations($id): ?array
  {
    return $this->getRelations($id);
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
      if (!empty($fn[$arc['email']]) && $this->dbTraitCount([$arc['email'] => $fn[$arc['email']]])) {
        throw new Exception(X::_("The email is already in use"));
      }

      if ($force || !$this->search($fn)) {
        $fn = $this->prepareData($fn);
        if (!empty($fn[$arc['name']])) {
          $id = $this->dbTraitInsert($fn);
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
    if ($this->getInfo($id)) {
      $fn = $this->prepareData($fn);

      if (!empty($fn)) {
        $fn[$arc['cfg']] = empty($fn[$arc['cfg']]) ? null : json_encode($fn[$arc['cfg']]);
        $ok = $this->dbTraitUpdate($id, $fn);
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
  public function fusion($ids, $main = null)
  {
    return History::fusion($ids, $this->class_cfg['table'], $this->db, $main);
  }


  protected function prepareData(array $fn): array
  {
    $arc = &$this->class_cfg['arch']['people'];

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
}
