<?php

namespace bbn\Entities;

use bbn\Models\Tts\Dbconfig;
use bbn\Models\Tts\Optional;
use bbn\Models\Cls\Db as DbCls;
use bbn\Db;
use bbn\X;

class Options extends DbCls
{
  use Dbconfig;
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
  
  public function __construct(Db $db, array $cfg = [])
  {
    parent::__construct($db);
    $this->_init_class_cfg($cfg);
    self::optionalInit(['options', 'entity', 'appui']);
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
      if ($this->db->count(
        $this->class_cfg['table'],
        [
          $this->class_cfg['arch']['entities_options']['id_entity'] => $id_entity,
          $this->class_cfg['arch']['entities_options']['id_type'] => $id_type,
          $this->class_cfg['arch']['entities_options']['id_option'] => $id_option
        ])
      ) {
        throw new \Exception(X::_("The option already exists for this entity"));
      }

      if ($this->db->insertIgnore(
        $this->class_cfg['table'],
        [
          $this->class_cfg['arch']['entities_options']['id_entity'] => $id_entity,
          $this->class_cfg['arch']['entities_options']['id_type'] => $id_type,
          $this->class_cfg['arch']['entities_options']['id_option'] => $id_option
        ]
      )) {
        return $this->db->lastId();
      }
    }

    return null;
  }


  public function remove($id_entity, $id_type, $id_option): ?int
  {
    if ($this->check()) {
      return $this->db->delete(
        $this->class_cfg['table'],
        [
          $this->class_cfg['arch']['entities_options']['id_entity'] => $id_entity,
          $this->class_cfg['arch']['entities_options']['id_type'] => $id_type,
          $this->class_cfg['arch']['entities_options']['id_option'] => $id_option
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
          throw new \Exception(X::_("The options must be an array of strings"));
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
      return $this->db->rselectAll(
        $this->class_cfg['table'],
        [],
        [
          $this->class_cfg['arch']['entities_options']['id_entity'] => $id_entity,
          $this->class_cfg['arch']['entities_options']['id_type'] => $id_type
        ]
      );
    }

    return null;
  }


  public function get($id_entity, $id_type): ?array
  {
    if ($this->check()) {
      return $this->db->getFieldValues(
        $this->class_cfg['table'],
        $this->class_cfg['arch']['entities_options']['id_option'],
        [
          $this->class_cfg['arch']['entities_options']['id_entity'] => $id_entity,
          $this->class_cfg['arch']['entities_options']['id_type'] => $id_type
        ]
      );
    }

    return null;
  }


  public function getAll($id_entity)
  {
    $res = [];
    foreach ($this->getTypes() as $k => $id) {
      $res[$k] = $this->get($id_entity, $id);
    }

    return $res;
  }
}
