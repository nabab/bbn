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


class NoteLink extends EntityJunction
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



  private static Note $_note;

  protected Note $note;

  public function __construct(
    Db $db,
    protected Entities $entities,
    protected Entity|Nullall $entity = new Nullall()
  )
  {
    parent::__construct($db, $entities, $entity);
    $this->initClassCfg(static::$default_class_cfg);
    if (!isset(self::$_note)) {
      $this->note = new Note($db);
      self::noteLinkSetNote($this->note);
    }
    else {
      $this->note = self::$_note;
    }

    if ($entity) {
      $this->id_entity = $entity->getId();
      $this->dbTraitSetFilterCfg([$this->fields['id_entity'] => $this->id_entity]);
    }
  }

  private static function noteLinkSetNote(Note $note)
  {
    self::$_note = $note;
  }

  protected function NoteLinkGetRequestCfg(string|null $id_parent = null, string|null $id_alias = null): array
  {
    $dbCfg = $this->note->getLastVersionCfg(false);
    $linkCfg = $this->getClassCfg();
    $noteCfg = $this->note->getClassCfg();
    $entityCfg = $this->entities->getClassCfg();
    $dbCfg['join'][] = [
      'table' => $linkCfg['table'],
      'on' => [
        [
          'field' => $this->db->cfn($linkCfg['arch']['entities_notes']['id_note'], $linkCfg['tables']['entities_notes']),
          'exp' => $this->db->cfn($noteCfg['arch']['notes']['id'], $noteCfg['table']),
        ]
      ]
    ];
    $dbCfg['join'][] = [
      'table' => $entityCfg['table'],
      'on' => [
        [
          'field' => $this->db->cfn($entityCfg['arch']['entities']['id'], $entityCfg['table']),
          'exp' => $this->db->cfn($linkCfg['arch']['entities_notes']['id_entity'], $linkCfg['tables']['entities_notes'])
        ]
      ]
    ];

    $dbCfg['where'] = [];

    if ($this->id_entity) {
      $dbCfg['where'][] = [
        'field' => $this->db->cfn($linkCfg['arch']['entities_notes']['id_entity'], $linkCfg['tables']['entities_notes']),
        'value' => $this->id_entity
      ];
    }

    $dbCfg['where'][] = [
      'field' => $this->db->cfn($noteCfg['arch']['notes']['active'], $noteCfg['table']),
      'value' => 1
    ];

    $parent = ['field' => $this->db->cfn($noteCfg['arch']['notes']['id_parent'], $noteCfg['table'])];
    if ($id_parent) {
      $parent['value'] = $id_parent;
    }
    else {
      $parent['operator'] = 'isnull';
    }
    $dbCfg['where'][] = $parent;

    $alias = ['field' => $this->db->cfn($noteCfg['arch']['notes']['id_alias'], $noteCfg['table'])];
    if ($id_alias) {
      $alias['value'] = $id_alias;
    }
    else {
      $alias['operator'] = 'isnull';
    }
    $dbCfg['where'][] = $alias;

    return $dbCfg;
  }

}
