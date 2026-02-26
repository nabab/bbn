<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 03/11/2014
 * Time: 16:54
 */

namespace bbn\Entities\Junctions;

use bbn\Db;
use bbn\X;
use bbn\Models\Cls\Nullall;
use bbn\Models\Tts\DbJunction;
use bbn\Entities\Models\Entities;
use bbn\Entities\Models\EntityJunction;
use bbn\Entities\Entity;
use bbn\Appui\Note;


class UauthLink extends EntityJunction
{

  protected static $default_class_cfg = [
    'table' => 'bbn_entities_notes',
    'tables' => [
      'entities_notes' => 'bbn_entities_notes'
    ],
    'arch' => [
      'entities_notes' => [
        'id_entity' => 'id_entity',
        'id_note' => 'id_note',
        'type' => 'type',
        'id_email' => 'id_email'
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
    $this->initClassCfg(static::$default_class_cfg);
    if ($entity) {
      $this->id_entity = $entity->getId();
      $this->dbTraitSetFilterCfg([$this->fields['id_entity'] => $this->id_entity]);
    }
  }

}
