<?php

namespace bbn\Entities\Tables;

use Exception;
use bbn\Entities\Models\Entities;
use bbn\Entities\Models\EntityTable;
use bbn\Entities\Entity;
use bbn\Models\Cls\Nullall;
use bbn\Models\Tts\Optional;
use bbn\Db;
use bbn\X;

class Options extends EntityTable
{
  use Optional;

  protected static $default_class_cfg = [
    'table' => 'bbn_entities_options',
    'tables' => [
      'entities_options' => 'bbn_entities_options'
    ],
    'arch' => [
      'entities_options' => [
        'id' => 'id',
        'id_entity' => 'id_entity',
        'id_type' => 'id_type',
        'id_option' => 'id_option'
      ],
    ]
  ];
  
  public function __construct(
    Db $db, 
    protected Entities $entities,
    protected Entity|Nullall $entity = new Nullall()
  )
  {
    parent::__construct($db, $entities, $entity);
    $this->initClassCfg(self::$default_class_cfg);
    self::optionalInit(['types', 'apst_adherent', 'entity', 'appui']);
  }


  public function getTypes(): array
  {
    $res = [];
    if ($this->check()) {
      foreach (self::getOptions() as $o) {
        if (!empty($o['code'])) {
          $res[$o['code']] = $o['id'];
        }
      }
    }

    return $res;
  }

  public function add($id_entity, $id_type, $id_option): ?string
  {
    if ($this->check()) {
      $f = $this->class_cfg['arch']['entities_options'];
      if ($this->dbTraitCount(
        [
          $f['id_type'] => $id_type,
          $f['id_option'] => $id_option
        ])
      ) {
        throw new Exception(X::_("The option already exists for this entity"));
      }

      if ($this->dbTraitInsert([
          $f['id_entity'] => $id_entity,
          $f['id_type'] => $id_type,
          $f['id_option'] => $id_option
        ], true
      )) {
        return $this->db->lastId();
      }
    }

    return null;
  }


  public function remove($id_entity, $id_type, $id_option): ?int
  {
    if ($this->check()) {
      $f = $this->class_cfg['arch']['entities_options'];
      return $this->dbTraitDelete(
        [
          $f['id_entity'] => $id_entity,
          $f['id_type'] => $id_type,
          $f['id_option'] => $id_option
        ]
      );
    }

    return null;
  }


  public function edit($id_entity, string $id_type, array $options): ?int
  {
    if ($this->check()) {
      $num = 0;
      $all = $this->get($id_entity, $id_type);
      foreach ($options as $o) {
        if (!\is_string($o)) {
          throw new Exception(X::_("The options must be an array of strings"));
        }

        if (!in_array($o, $all) && $this->add($id_entity, $id_type, $o)) {
          $num++;
        }
      }

      foreach ($all as $o) {
        if (!in_array($o, $options) && $this->remove($id_entity, $id_type, $o)) {
          $num++;
        }
      }

      return $num;
    }

    return null;
  }


  public function getRows($id_entity, $id_type): ?array
  {
    if ($this->check()) {
      $f = $this->class_cfg['arch']['entities_options'];
      return $this->db->rselectAll(
        $this->class_cfg['table'],
        [],
        [
          $f['id_type'] => $id_type
        ]
      );
    }

    return null;
  }


  public function get($id_entity, $id_type): ?array
  {
    if ($this->check()) {
      $f = $this->class_cfg['arch']['entities_options'];
      return $this->dbTraitSelectValues(
        $f['id_option'],
        [
          $f['id_type'] => $id_type
        ]
      );
    }

    return null;
  }


  public function getAllByType($id_entity = null)
  {
    $res = [];
    foreach ($this->getTypes() as $k => $id) {
      $res[$k] = $this->get($id_entity ?: $this->getId(), $id);
    }

    return $res;
  }
}
