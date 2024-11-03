<?php

namespace bbn\Entities;

use Exception;
use bbn\X;
use bbn\Str;
use bbn\Db;
use bbn\Appui\History;
use bbn\Models\Tts\DbActions;
use bbn\Models\Tts\DbUauth;
use bbn\Models\Cls\Db as DbCls;
use bbn\Entities\Tables\Link;
use bbn\Entities\Models\Entities;
use bbn\Models\Cls\Nullall;

/**
 * The People class represents entities in a 'bbn_identities' table
 * and provides methods to manipulate these entities, including
 * CRUD operations, search, and relation management, tailored for French civilities.
 */
class Identities extends DbCls
{
  use DbActions;
  use DbUauth;
  /**
   * The default configuration for database interaction, specifying the table and fields.
   */
  protected static $default_class_cfg = [
    'table' => 'bbn_identities',
    'tables' => [
      'identities' => 'bbn_identities',
      'uauth' => 'bbn_identities_uauth'
    ],
    'arch' => [
      'identities' => [
        'id' => 'id',
        'civility' => 'civility',
        'name' => 'name',
        'fname' => 'fname',
        'fullname' => 'fullname',
        'cfg' => 'cfg',
      ],
      'uauth' => [
        'id' => 'id',
        'id_associate' => 'id_identity',
        'id_uauth' => 'id_uauth',
        'cfg' => 'cfg'
      ]
    ],
    'uauth_system' => 'many-to-one',
    'uauth_modes' => [
      'email' => 'email',
      'phone' => 'phone'
    ],
    'uauth_phone_region' => null
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
    protected ?Entities $entities = null,
    protected Entity|Nullall $entity = new Nullall()
  )
  {
    parent::__construct($db);
    $this->initClassCfg();
    $this->dbUauthInit();
  }

  public function getRelatedEntities(string $id): array
  {
    $lnk = new Link($this->db, $this->entities);
    return array_unique(array_map(function ($a) {
      return $a['id_entity'];
    }, $lnk->getFullList(['id_identity' => $id])));
  }


  /**
   * Processes information for a person based on input.
   *
   * @param mixed $st Input data.
   * @param bool $email Optional email for the person.
   * @param bool $phone Optional phone number for the person.
   * @return mixed Processed person data.
   */
  public function parse(string $st, $email = false, $phone = false): ?array
  {
    if (!empty($st)) {
      $arc = &$this->class_cfg['arch']['identities'];
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
        $fn['email'] = $email;
      }

      if ($phone) {
        $fn['phone'] = $phone;
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
  public function getInfo($id): ?array
  {
    $res = $this->dbTraitRselect($id);
    if (!empty($res)) {
      $arc = &$this->class_cfg['arch']['identities'];
      foreach ($this->class_cfg['uauth_modes'] as $mode) {
        $arr = $this->dbUauthRetrieve($id, $mode);
        if (in_array($this->class_cfg['uauth_system'], ['one-to-many', 'many-to-many'])) {
          $res[$mode] = $arr ? array_map(function($a) use ($mode) {
            return $a[$mode];
          }, $arr) : [];
        }
        else {
          $res[$mode] = $arr[$mode] ?? null;
        }
      }
    }

    return $res;
  }

  /**
   * Conducts a full search for identities records.
   *
   * @param mixed $p Search parameters or a single UID.
   * @param int $start Pagination start.
   * @param int $limit Pagination limit.
   * @return array The full search results.
   */
  /*
  public function full_search($p, int $start = 0, int $limit = 0)
  {
    $arc = &$this->class_cfg['arch']['identities'];
    $r   = [];
    $res = Str::isUid($p) ? [$p] : $this->seek($p, $start, $limit);
    if ($res) {
      foreach ($res as $i => $id) {
        $r[$i] = $this->getInfo($id);
      }
    }

    return $r;
  }
  */

  /**
   * Adds or updates a person record in the database.
   *
   * @param mixed $fn The person data to add.
   * @param bool $force Whether to forcefully add the person.
   * @return string|null The ID of the added or updated person.
   */
  public function add($fn, $force = false): ?string
  {
    $arc = &$this->class_cfg['arch']['identities'];
    $id = null;
    $uauth = [];
    foreach ($this->class_cfg['uauth_modes'] as $mode) {
      if (!empty($fn[$mode])) {
        $uauth[$mode] = $fn[$mode];
        unset($fn[$mode]);
      }
    }

    if (!empty($fn[$arc['name']])
      && ($id = $this->dbTraitInsert($fn))
    ) {
      foreach ($this->class_cfg['uauth_modes'] as $mode) {
        if (!empty($uauth[$mode])) {
          try {
            $this->dbUauthAdd($id, $uauth[$mode], $mode);
          }
          catch (Exception $e) {
            History::delete($id);
            throw $e;
          }
        }
      }
    }

    return $id;
  }

  public function search(array|string $filter, array $cols = [], array $fields = [], array $order = [], bool $strict = false, int $limit = 0, int $start = 0): array
  {
    $ccfg = $this->getClassCfg();
    $uauthCfg = self::$dbUauth->getClassCfg();
    if (is_array($filter)) {
      $finalFilter = $filter;
      if (empty($fields) && !empty($cols)) {
        $fields = $cols;
      }
    }
    else {
      $finalFilter = $this->dbTraitGetSearchFilter($filter, $cols, $strict);
    }

    if (empty($fields)) {
      $fields = array_values($ccfg['arch']['identities']);
      foreach ($ccfg['uauth_modes'] as $mode) {
        $fields[$mode] = $this->db->cfn($uauthCfg['arch']['uauth']['value'], 'uauth_' . $mode);
        if (!is_array($filter)) {
          $finalFilter['conditions'][] = [
            'field' => $this->db->cfn($uauthCfg['arch']['uauth']['value'], 'uauth_' . $mode),
            'operator' => $strict ? '=' : 'contains',
            'value' => $filter
          ];
        }
      }
    }

    $cfg = [
      'table' => $ccfg['table'],
      'join' => $this->getJoin(),
      'fields' => $fields,
      'limit' => $limit,
      'start' => $start,
      'where' => $finalFilter,
    ];

    //X::ddump($cfg);
    return $this->db->rselectAll($cfg);
  }


  /**
   * Updates a person record in the database.
   *
   * @param mixed $id The ID of the person to update.
   * @param mixed $fn The new data for the person.
   * @return string|null The ID of the updated person.
   */
  public function update($id, $fn): int
  {
    $arc = &$this->class_cfg['arch']['identities'];
    $ok = 0;
    if ($info = $this->getInfo($id)) {
      foreach ($this->class_cfg['uauth_modes'] as $mode) {
        if (($info[$mode] ?? '') !== ($fn[$mode] ?? '')) {
          if (!empty($info[$mode])) {
            $ok += (int)$this->dbUauthRemove($id, $info[$mode], $mode);
          }

          if (!empty($fn[$mode])) {
            $ok += (int)$this->dbUauthAdd($id, $fn[$mode], $mode);
          }
        }
      }

      $fn = $this->dbTraitPrepare($fn);
      if (!empty($fn)) {
        $ok += (int)$this->dbTraitUpdate($id, $fn);
      }

    }

    return $ok;
  }

  public function getTableRelations(string $table = null): array
  {
    return $this->dbTraitGetTableRelations($table);

  }
  public function getRelations($id): ?array
  {
    return $this->dbTraitGetRelations($id);
  }


  public function delete(string $id, bool $force = false): bool
  {
    return $this->dbTraitDelete($id);
  }

  public function setEmail($id, $email): ?string
  {
    $info = $this->getInfo($id);
    if ($info['email'] === $email) {
      return null;
    }

    if (!empty($info['email'])) {
      $this->dbUauthRemove($id, $info['email'], 'email');
    }

    return $this->dbUauthAdd($id, $email, 'email');
  }


  public function setPhone($id, $phone): ?string
  {
    $info = $this->getInfo($id);
    $type = array_values(
      array_filter(
        $this->class_cfg['uauth_modes'], fn($m) => in_array($m, ['phone', 'mobile', 'portable'])
      )
    )[0] ?? false;
    if ($type) {
      if ($info[$type] === $phone) {
        return null;
      }

      if (!empty($info[$type])) {
        $this->dbUauthRemove($id, $info[$type], $type);
      }

      return $this->dbUauthAdd($id, $phone, $type);
    }

    return null;
  }

  public function get(string $id): array
  {
    $arc = &$this->class_cfg['arch']['identities'];
    return $this->db->rselect(
      $this->class_cfg['table'],
      [$arc['id'], $arc['name'], $arc['fname'], $arc['civility'], $arc['fullname']],
      [$arc['id'] => $id]
    );
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


  public function retrieveUauth(string $identity, string $type): ?array
  {
    return $this->dbUauthRetrieve($identity, $type);
  }

  public function addUauth(string $identity, string $value, string $type): ?string
  {
    return $this->dbUauthAdd($identity, $value, $type);
  }
  public function removeUauth(string $identity, string $value, string $type): ?string
  {
    return $this->dbUauthRemove($identity, $value, $type);
  }

  protected function getJoin(): array
  {
    $ccfg = $this->getClassCfg();
    $uauthCfg = self::$dbUauth->getClassCfg();
    $join = [];
    foreach ($ccfg['uauth_modes'] as $mode) {
      $join[] = [
        'table' => $ccfg['tables']['uauth'],
        'alias' => 'uauth_link_' . $mode,
        'type' => 'left',
        'on' => [
          'conditions' => [[
            'field' => $this->db->cfn($ccfg['arch']['identities']['id'], $ccfg['table']),
            'operator' => '=',
            'exp' => $this->db->cfn($ccfg['arch']['uauth']['id_associate'], 'uauth_link_' . $mode)
          ]]
        ]
      ];

      $join[] = [
        'table' => $uauthCfg['table'],
        'alias' => 'uauth_' . $mode,
        'type' => 'left',
        'on' => [
          'conditions' => [[
            'field' => $this->db->cfn($ccfg['arch']['uauth']['id_uauth'], 'uauth_link_' . $mode),
            'operator' => '=',
            'exp' => $this->db->cfn($uauthCfg['arch']['uauth']['id'], 'uauth_' . $mode)
          ], [
            'field' => $this->db->cfn($uauthCfg['arch']['uauth']['typology'], 'uauth_' . $mode),
            'operator' => '=',
            'value' => self::$dbUauth->getIdTypology($mode)
          ]]
        ]
      ];
    }

    return $join;
  }

}
