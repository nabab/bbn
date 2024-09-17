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


  



  public function notes(int $start = null, int $limit = null){
    $r = [];
    if ( $this->check() ){
      //$args = func_get_args();
      //$cond = " WHERE apst_adherents_notes.id_entity = {$this->getId()} AND apst_notes.actif = 1 ";
      $limit = is_integer($start) && is_integer($limit) ? " LIMIT $start, $limit" : '';
      $r = $this->db->getRows("
        SELECT bbn_notes.id, apst_adherents_notes.type AS id_type_note, bbn_notes.private AS confidentiel, 
          GROUP_CONCAT(HEX(bbn_notes_versions.id_user) SEPARATOR ',') AS users,
          (SELECT content
            FROM bbn_notes_versions
            WHERE id_note = bbn_notes.id
            ORDER BY creation DESC
            LIMIT 1
          ) AS texte,
          (SELECT creation
            FROM bbn_notes_versions
            WHERE id_note = bbn_notes.id
            ORDER BY creation DESC
            LIMIT 1
          ) AS chrono
        FROM bbn_notes_versions
          JOIN bbn_notes
            ON bbn_notes_versions.id_note = bbn_notes.id
            AND bbn_notes.active = 1
          JOIN apst_adherents_notes
            ON apst_adherents_notes.id_note = bbn_notes_versions.id_note
          JOIN apst_adherents
            ON apst_adherents.id = apst_adherents_notes.id_entity
        WHERE apst_adherents_notes.id_entity = ?
        GROUP BY bbn_notes.id
        ORDER BY chrono DESC
        $limit",
        $this->getId()
      );
    }
    return $r;
  }

  public function notes_compte(){
    if ( $this->check() ){
      return $this->db->getOne("
        SELECT COUNT(apst_adherents_notes.id_note)
        FROM apst_adherents_notes
          JOIN bbn_notes
            ON apst_adherents_notes.id_note = bbn_notes.id
            AND bbn_notes.active = 1
        WHERE apst_adherents_notes.id_entity = ?",
        $this->getId());
    }
  }

  public function note_maj($cfg){
    if ( $this->check() ){
      if ( is_string($cfg) ){
        $cfg = ['texte' => $cfg];
      }
      if ( !empty($cfg['texte']) ){
        $note = new \bbn\Appui\Note($this->db);
        $id_type = $this->options()->fromCode('adh', 'types', 'note', 'appui');
        $type = $cfg['id_type_note'] ?: $this->options()->fromCode('OBS', 'types_notes');
        $confidentiel = empty($cfg['confidentiel']) ? 0 : 1;
        $locked = empty($cfg['locked']) ? 0 : 1;
        if ( empty($cfg['id']) &&
          ($id = $note->insert(
            '',
            $cfg['texte'],
            $id_type,
            $confidentiel,
            $locked
          )) &&
          $this->db->insert('apst_adherents_notes', [
            'id_entity' => $this->getId(),
            'id_note' => $id,
            'type' => $type
          ])
        ){
          return $id;
        }
        else if ( !empty($cfg['id']) ){
          $ok = $note->update($cfg['id'], '', $cfg['texte'], $confidentiel, $locked);
          $ct = $type !== $this->db->selectOne('apst_adherents_notes', 'type', [
              'id_entity' => $this->getId(),
              'id_note' => $cfg['id']
            ]);
          $ok2 = $this->db->update('apst_adherents_notes', [
            'type' => $type
          ], [
            'id_entity' => $this->getId(),
            'id_note' => $cfg['id']
          ]);
          if ( (!empty($ok) && empty($ct)) || (!empty($ct) && !empty($ok2)) ){
            return true;
          }
        }
      }
    }
    return false;
  }


  public function note_sup($id): int
  {
    if ( $this->check() &&
      !empty($id)
    ){
      $note = new Note($this->db);
      if ($note->remove($id)) {
        return 1;
      }
    }
    return false;
  }

  protected function NoteLinkGetRequestCfg(string $id_parent = null, string $id_alias = null): array
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
