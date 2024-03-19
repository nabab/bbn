<?php

namespace bbn\Entities\Junctions;

use bbn\X;
use bbn\Db;
use bbn\User;
use bbn\Entities\Entity;
use bbn\Entities\Models\Entities;
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Tts\DbConfig;

class Consultation extends DbCls
{
  use DbConfig;

  protected static $default_class_cfg = [
    'table' => 'bbn_consultations',
    'tables' => [
      'consultations' => 'bbn_entities_consultations'
    ],
    'arch' => [
      'consultations' => [
        "id_entity" => "id_entity",
        "id_user" => "id_user",
        "nb" => "nb",
        "last_time" => "last_time"
      ]
    ]
  ];


  public function __construct(Db $db, array $cfg = null)
  {
    // The database connection
    $this->db = $db;
    // Setting up the class configuration
    $this->_init_class_cfg($cfg);
  }


  public function insert(Entity $entity)
  {
    $cfg = $this->getClassCfg();
    $user = User::getInstance();
    $num = (int)$this->db->selectOne($cfg['table'], $this->fields['nb'], [
    ]);
    if ($num) {
      return (int)$this->db->update(
        $cfg['tables']['consultations'],
        [
          $cfg['arch']['consultations']['nb'] => $num + 1,
          $cfg['arch']['consultations']['last_time'] => date('Y-m-d H:i:s')
        ], [
          $cfg['arch']['consultations']['id_entity'] => $entity->getId(),
          $cfg['arch']['consultations']['id_user'] => $user->getId(),
        ]
      );
    }
    else {
      return (int)$this->db->insert(
        $cfg['tables']['consultations'],
        [
          $cfg['arch']['consultations']['id_entity'] => $entity->getId(),
          $cfg['arch']['consultations']['id_user'] => $user->getId(),
          $cfg['arch']['consultations']['nb'] => $num + 1,
          $cfg['arch']['consultations']['last_time'] => date('Y-m-d H:i:s')
        ],
      );
    }

  }
}

