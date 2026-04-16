<?php
namespace bbn\Entities;

use bbn\Appui\History;
use Exception;
use BadMethodCallException;
use bbn\X;
use bbn\Str;
use bbn\Db;
use bbn\Cache;
use bbn\Entities\Models\Entities;
use bbn\Entities\Tables\Link;
use bbn\Entities\Models\Internals\EntityObjects;
use bbn\Appui\Option;
use bbn\Appui\Uauth;
use bbn\Models\Tts\Cache as CacheTts;

use function in_array;

class Entity
{
  use CacheTts;

  protected array $class_cfg;

  protected array $fields;
  protected string $table;

  protected array $props;

  protected array $where;

  protected array $records;

  protected array $info = [];

  protected ?Link $links;

  protected $easyId = null;

  protected array $objects = [];

  public function __call($method, $args)
  {
    if ($this->objects[$method] ?? null) {
      return $this->objects[$method];
    }
    
    $res = $this->entities->$method($this);
    if ($res) {
      $this->objects[$method] = $res;
      return $res;
    }
  }

  /**
   * Constructor.
   *
   * @param Db    $db
   * @param array $cfg
   * @param array $params
   */
  public function __construct(protected Db $db, protected string $id, protected Entities $entities)
  {
    $this->class_cfg = $this->entities->getClassCfg();
    $this->table = $this->class_cfg['table'];
    $this->fields = $this->class_cfg['arch']['entities'];
    $this->props = $this->class_cfg['props']['entities'];
    if ($this->fields['easy_id']) {
      if (Str::isInteger($id)) {
        $this->easyId = $id;
        if ($uid = $this->entities->selectOne($this->fields['id'], [$this->fields['easy_id'] => $id])) {
          $this->id = $uid;
        }
        else {
          throw new Exception(X::_("The entity %s does not exist", $id));
        }
      }
      elseif ($this->entities->exists($id)) {
        $this->easyId = $this->entities->selectOne($this->fields['easy_id'], [$this->fields['id'] => $id]);
        $this->id = $id;
      }
      else {
        throw new Exception(X::_("The entity %s does not exist", $id));
      }
    }
    elseif ($this->entities->exists($id)) {
      $this->id = $id;
    }
    else {
      throw new Exception(X::_("The entity does not exist"));
    }


    $this->where = [
      $this->db->cfn($this->fields['id'], $this->table) => $this->id
    ];
	}

  public function check(): bool
  {
    return (bool)$this->id;
  }

  public function getId(): string
  {
    return $this->id;
  }

  public function getEasyId(): ?string
  {
    return $this->easyId;
  }

  public function getWhere(): array
  {
    return $this->where;
  }


  public function getField(string $field, bool $force = false): string|float|int|null
  {
    if ($force || !array_key_exists($field, $this->info)) {
      if (!in_array($field, $this->fields)) {
        if (isset($this->fields['identity'])) {
          $identityCfg = array_flip($this->identity()->getClassCfg()['arch']['identities']);
          if (isset($identityCfg[$field])) {
            $identity = $this->identity()->get($this->getField($this->fields['identity']));
            X::extendOut($this->info, $identity);
            if (isset($identity[$field])) {
              return $identity[$field];
            }
          }
          else if ($this->entities->getClassCfg()['classes']['uauth']) {
            try {
              $uauth = $this->identity()->retrieveUauth($this->getField($this->fields['identity']), $field);
              return $uauth[$field];
            }
            catch (Exception $e) {
              throw new Exception(X::_("The field %s does not exist", $field));
            }
          }
        }

        throw new Exception(X::_("The field %s does not exist", $field));
      }

      $this->info[$field] = $this->db->selectOne(
        $this->class_cfg['table'],
        $field,
        $this->where
      );
    }

    return $this->info[$field];
  }


  public function getFields(array $fields, bool $force = false): array
  {
    $res = [];
    foreach ($fields as $f) {
      $res[$f] = $this->getField($f, $force);
    }

    return $res;
  }


  public function getFromTable()
  {
    $table = $this->class_cfg['tables']['entities'];

    $cfg = [
      'tables' => [$table],
      'fields' => array_values($this->getFieldsList()),
      'where' => $this->where
    ];

    $data = $this->db->rselect($cfg);
    return $data;
  }

  public function getBasicInfo(): array
  {
    $arc = $this->class_cfg['arch']['entities'];
    $fields = [$arc['id'], $arc['identity']];
    if (!empty($arc['easy_id'])) {
      $fields[] = $arc['easy_id'];
    }

    $res = $this->db->rselect(
      $this->class_cfg['table'],
      $fields,
      $this->where
    );

    if (!empty($res[$arc['identity']])) {
      $res = X::mergeArrays($this->identity()->get($res[$arc['identity']]), $res);
    }

    return $res;
  }


  public function getMinimalInfo(?array $fields = null): array
  {
    $arc = $this->class_cfg['arch']['entities'];
    if (!$fields) {
      $fields = [$arc['id'], $arc['identity']];
      if (!empty($arc['easy_id'])) {
        $fields[] = $arc['easy_id'];
      }
    }

    $res = $this->db->rselect(
      $this->class_cfg['table'],
      $fields,
      $this->where
    );

    if (!empty($res[$arc['identity']])) {
      $res = X::mergeArrays($this->identity()->get($res[$arc['identity']]), $res);
    }

    return $res;
  }


  protected function getFieldsList(): array
  {
    $fields = $this->fields;
    $props = $this->props;
    $db = $this->db;
    $table = $this->class_cfg['tables']['entities'];
    return array_map(function ($a) use (&$db, $table) {
      return $db->cfn($a, $table);
    }, array_filter($fields, function($k) use (&$props) {
      return !isset($props[$k]) || !array_key_exists('showable', $props[$k]) || $props[$k]['showable'];
    }, ARRAY_FILTER_USE_KEY));

  }

  public function getEntities(): Entities
  {
    return $this->entities;
  }

  public function getLink(string $linkCls): Link
  {
    return $this->entities->getLink($linkCls, $this);
  }

  public function identity(): Identity
  {
    return $this->entities->identity();
  }

  public function uauth(): Uauth
  {
    return $this->entities->uauth();
  }

  public function address(): Address
  {
    return $this->entities->address();
  }

  public function links(): Link
  {
    if (!isset($this->links)) {
      $this->links = $this->entities->getGlobalLink($this);
    }

    return $this->links;
  }
  
  public function options(): Option
  {
    return $this->entities->options();
  }
  
  public function getParent(): ?Entity
  {
    if ($this->check()
        && !empty($this->fields['id_parent'])
        && ($id_parent = $this->getField($this->fields['id_parent']))
    ) {
      return $this->entities->get($id_parent);
    }

    return null;
  }

  public function getParentInfo(): ?array
  {
    if ($parent = $this->getParent()) {
      return $parent->getMinimalInfo();
    }

    return null;
  }

  public function getSisters(): array
  {
    $res = [];
    if ($this->check()
        && !empty($this->fields['id_parent'])
        && ($id_parent = $this->getField($this->fields['id_parent']))
    ) {
      $tmp = $this->db->getColumnValues(
        $this->table,
        $this->fields['id'],
        [$this->fields['id_parent'] => $id_parent]
      );
      foreach ($tmp as $sis) {
        if ($sis !== $this->id) {
          $ent = $this->entities->get($sis);
          $res[] = $ent->getMinimalInfo();
        }
      }

    }
    
    return $res;
  }

  public function countChildren(): int
  {
    return $this->entities->count([
      $this->fields['id_parent'] => $this->id
    ]);
  }


  public function getChildren(): array
  {
    $tmp = $this->db->getColumnValues(
      $this->table,
      $this->fields['id'],
      [$this->fields['id_parent'] => $this->id]
    );
    $res = [];
    foreach ($tmp as $e) {
      if ($e !== $this->id) {
        $ent = $this->entities->get($e);
        $res[] = $ent->getMinimalInfo();
      }
    }

    return $res;
  }



  public function getAllRelatedIds(array $excluded = [], ?callable $filter = null): array
  {
    $res = [$this->table => [$this->getId()]];
    $checked = [$this->table];
    $ocfg = $this->options()->getClassCfg();
    $excluded[] = History::$table_uids;
    $excluded[] = $ocfg['table'];
    foreach (Entities::getEntityKeys($this->db, $this->entities) as $table => $col) {
      if ($filter && !in_array($table, $checked)) {
        $checked[] = $table;
        if (!$filter($table)) {
          $excluded[] = $table;
          continue;
        }
      }

      if (in_array($table, $excluded)) {
        continue;
      }

      $dbModel = $this->db->modelize($table);
      if ($dbModel['primary'] && (count($dbModel['primary']) === 1)) {
        $tableUids = $this->db->getColumnValues($table, 'DISTINCT '.$dbModel['primary'][0], [
          $col[0] => $this->getId(),
          [$dbModel['primary'][0], 'isnotnull'],
          [$dbModel['primary'][0] => 'ASC']
        ]);
        if (!isset($res[$table])) {
          $res[$table] = [];
        }

        array_push($res[$table], ...$tableUids);
      }
    }

    /*
    $tableCfgs = Entities::dbConfigGetTableClasses($this->db);
    foreach ($tableCfgs as $table => $cfg) {
      if (!empty($cfg['deps'])) {
        foreach ($cfg['deps'] as $dep) {
          if (isset($res[$dep], $tableCfgs[$dep]['junctions'])) {
            $junction = X::getRow($tableCfgs[$dep]['junctions'], ['table' => $table]);
            if (!$junction) {
              throw new Exception(X::_("The table %s is linked to %s through a junction but the junction configuration is missing", $table, $dep)); 
            }

            $rels = $this->db->getColumnValues($dep, $junction['field'], [
              'id' => $res[$dep]
            ], ['id' => 'ASC']);
            if (!isset($res[$table])) {
              $res[$table] = [];
            }
            array_push($res[$table], ...$rels);
          }
        }
      }
    }
      */

    foreach ($res as $table => $ids) {
      $res[$table] = array_unique($ids);
    }

    if (!empty($res['bbn_entities_links']) && !in_array('bbn_entities_links', $excluded)) {
      $identities = null;
      if (!in_array('bbn_identities', $excluded)) {
        $res['bbn_identities'] = [];
        if ($idAdmin = $this->db->selectOne($this->table, 'id_admin', ['id' => $this->getId()])) {
          $res['bbn_identities'][] = $idAdmin;
        }

        $identities =& $res['bbn_identities'];
      }

      $addresses = null;
      if (!in_array('bbn_addresses', $excluded)) {
        $res['bbn_addresses'] = [];
        $addresses =& $res['bbn_addresses'];
      }

      foreach ($res['bbn_entities_links'] as $linkId) {
        if ($link = $this->db->rselect('bbn_entities_links', ['id_identity', 'id_address'], [
          'id' => $linkId
        ])) {
          if ($link['id_identity'] && isset($identities) && !in_array($link['id_identity'], $identities)) {
            $identities[] = $link['id_identity'];
          }

          if ($link['id_address'] && isset($addresses) && !in_array($link['id_address'], $addresses)) {
            $addresses[] = $link['id_address'];
          }
        }
      }

      if (!empty($identities)) {
        foreach ($identities as $idIdentity) {
          if (!in_array('bbn_identities_links', $excluded)) {
            if ($links = $this->db->getColumnValues('bbn_identities_links', 'id', [
              'id_parent' => $idIdentity
            ], [
              'id_identity' => 'ASC'
            ])) {
              if (!isset($res['bbn_identities_links'])) {
                $res['bbn_identities_links'] = [];
              }

              array_push($res['bbn_identities_links'], ...$links);
            }

            if ($links = $this->db->getColumnValues('bbn_identities_links', 'id', [
              'id_child' => $idIdentity
            ], [
              'id_identity' => 'ASC'
            ])) {
              if (!isset($res['bbn_identities_links'])) {
                $res['bbn_identities_links'] = [];
              }

              array_push($res['bbn_identities_links'], ...$links);
            }
          }

          if (!in_array('bbn_identities_uauth', $excluded)) {
            if ($iuauths = $this->db->getColumnValues('bbn_identities_uauth', 'id', [
              'id_identity' => $idIdentity,
            ], ['id' => 'ASC'])) {
              if (!isset($res['bbn_identities_uauth'])) {
                $res['bbn_identities_uauth'] = [];
              }

              array_push($res['bbn_identities_uauth'], ...$iuauths);
            }

            if ($uauths = $this->db->getColumnValues('bbn_identities_uauth', 'DISTINCT id_uauth', [
              'id_identity' => $idIdentity,
              ['id_uauth', 'isnotnull'],
              ['id_uauth' => 'ASC']
            ])) {
              if (!isset($res['bbn_uauth'])) {
                $res['bbn_uauth'] = [];
              }

              array_push($res['bbn_uauth'], ...$uauths);
            }
          }
        }
      }
    }

    foreach ($res as $table => $ids) {
      $res[$table] = array_values(array_unique(array_filter($ids, fn ($v) => (bool)$v)));
    }

    ksort($res);
    return $res;
  }

  public function getAllRelatedRecords(array $excluded = []): array
  {
    $res = $this->getAllRelatedIds($excluded);
    $final = [];
    $cfg = Entities::dbConfigGetTableClasses($this->db);
    $identity = $this->identity();
    $address = $this->address();
    $linkedTables = [$this->table, 'bbn_identities_uauth', ...array_keys(Entities::getEntityKeys($this->db, $this->entities))];
    foreach ($res as $table => $ids) {
      if (isset($cfg[$table])) {
        $final[$table] = [];
        if (in_array($table, $linkedTables)) {
          $obj = match(true) {
            $table === $this->table => $this->entities,
            $table === 'bbn_identities_uauth' => new $cfg[$table]['class']($this->db, $identity),
            true => $this->entities->getDbObject($table, $cfg[$table], $this->db, $this->entities, $this)
          };
          if (method_exists($obj, 'dbTraitCacheGetSet')) {
            foreach ($ids as $i => $id) {
              if ($tmp = $obj->dbTraitCacheGetSet($id)) {
                $final[$table][$id] = [
                  'state' => $obj->dbTraitCacheHash($id),
                  'data' => $tmp
                ];
              }
              else {
                X::log(X::_("The record with id %s at index %d in table %s does not exist or is unreachable through class %s", $id, $i, $table, $cfg[$table]['class']), 'missing_rows');
                //throw new Exception(X::_("The record with id %s at index %d in table %s does not exist or is unreachable through class %s", $id, $i, $table, $cfg[$table]['class']));
              }
            }
          }
        }
        elseif ($table === 'bbn_identities') {
          foreach ($ids as $id) {
            if ($d = $identity->pickOne($id, $this->getId(), true)) {
              $final[$table][$id] = [
                'state' => Cache::makeHash($d),
                'data' => $d
              ];
            }
          }
        }
        elseif ($table === 'bbn_addresses') {
          foreach ($ids as $id) {
            if ($d = $address->pickOne($id, $this->getId())) {
              $final[$table][$id] = [
                'state' => Cache::makeHash($d),
                'data' => $d
              ];
            }
          }
        }
        else {
          foreach ($ids as $id) {
            if ($d = $this->db->rselect($table, [], ['id' => $id])) {
              $final[$table][$id] = [
                'state' => Cache::makeHash($d),
                'data' => $d
              ];
            }
          }
        }
      }
    }

    /*
    foreach ($junc as $table => $cfgs) {
      foreach ($cfgs as $cfg) {
        X::ddump($cfg);
        if (isset($res[$table]) && isset($final[$cfg['table']])) {
          $obj = new $cfg['class']($this->db, Option::getInstance());
          $ids = $res[$table];
          if (!isset($final[$cfg['table']])) {
            $final[$cfg['table']] = [];
          }

          X::ddump($cfg['table'], $ids);
          foreach ($ids as $id) {
            if (!X::getRow($final[$cfg['table']], fn ($a) => $a['data']['id'] === $id)) {
              $final[$cfg['table']][] = $obj->rselect($id);
            }
          }
        }
      }
    }*/

    return $final;
  }

  public function getRecords(?string $idx = null): array
  {
    if (empty($this->records)) {
      $this->records = $this->getAllRelatedRecords();
    }

    if ($idx) {
      if (!is_array($this->records[$idx] ?? null)) {
        return [];
        X::ddump($idx, $this->records[$idx], array_keys($this->records));
        throw new Exception(X::_("The table %s is not related to the entity", $idx));
      }

      return array_values(array_map(fn($a) => $a['data'], $this->records[$idx] ?? []));
    }

    return array_map(
      fn($a) => array_values(array_map(
        fn($b) => $b['data'],
        $a ?: []
      )), $this->records);
  }

  public function cDelete(): self
  {
    $this->records = [];
    $this->db->query("UPDATE apst_adherents SET cached = NULL WHERE id = ?", hex2bin($this->getId()));
    return $this->cacheDelete($this->getId());
  }


  /**
   * Return adherent's cache.
   *
   * @param string $method
   * @return mixed
   */
  public function cGet($method = '')
  {
    return $this->cacheGet($this->getId(), $method);
  }


  /**
   * Sets adherent cache.
   *
   * @param string $method
   * @param $data
   * @return string|null
   */
  public function cSet($method, $data): ?string
  {
    if ($this->cacheSet($this->getId(), $method, $data, 0)) {
      return $this->cacheHash($this->getId(), $method);
    }

    return null;
  }


  /**
   * Checks if the given cache method exists.
   *
   * @param string $method
   *
   * @return bool
   */
  public function cHas($method = '')
  {
    if (!$this->getField('cached')) {
      return false;
    }

    return $this->cacheHas($this->getId(), $method);
  }


  public function cName($id, $method = ''): ?string
  {
    return $this->_cache_name($id, $method);
  }
}
