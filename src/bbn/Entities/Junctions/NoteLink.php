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
use bbn\Models\Cls\Db as DbCls;
use bbn\Models\Cls\Nullall;
use bbn\Models\Tts\DbConfig;
use bbn\Entities\Models\Entities;
use bbn\Entities\Entity;
use bbn\Appui\Note;


class NoteLink extends DbCls
{
  use DbConfig;


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

  protected $id_entity;

  protected Note $note;

  public function __construct(
    Db $db,
    protected Entities $entities,
    protected Entity|Nullall $entity = new Nullall()
  )
  {
    parent::__construct($db);
    $this->_init_class_cfg($this::$default_class_cfg);
    if (!self::$_note) {
      $this->note = new Note($db);
      self::noteLinkSetNote($this->note);
    }
    else {
      $this->note = self::$_note;
    }

    if ($entity) {
      $this->id_entity = $entity->getId();
      $this->DbActionsSetFilterCfg([$this->fields['id_entity'] => $this->id_entity]);
    }
  }

  public function getEntities() {
    return $this->entities;
  }

  public function getId()
  {
    return $this->id_entity;
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
        $id_type = $this->options()->fromRootCode('adh', 'types', 'note', 'appui');
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


  public function note_sup($id){
    if ( $this->check() &&
      !empty($id)
    ){
      $note = new \bbn\Appui\Note($this->db);
      if ( $note->remove($id) ){
        return $id;
      }
    }
    return false;
  }

  public function notes_importantes(): ?array
  {
    if ( $type = $this->options()->fromCode('IMP', 'types_notes') ){
      return $this->db->rselectAll([
        'table' => 'bbn_notes',
        'fields' => [
          'bbn_notes.id',
          'bbn_notes.id_parent',
          'bbn_notes.id_alias',
          'bbn_notes.id_type',
          'bbn_notes.private',
          'bbn_notes.locked',
          'bbn_notes.pinned',
          'bbn_notes.creator',
          'bbn_notes.active',
          'first_version.creation',
          'last_version.title',
          'last_version.content',
          'last_edit' => 'last_version.creation',
          'num_replies' => 'COUNT(DISTINCT replies.id)',
          'last_reply' => 'IFNULL(MAX(replies_versions.creation), last_version.creation)',
          'users' => 'GROUP_CONCAT(DISTINCT LOWER(HEX(versions.id_user)) SEPARATOR ",")'
        ],
        'join' => [[
          'table' => 'bbn_notes_versions',
          'alias' => 'versions',
          'on' => [
            'logic' => 'AND',
            'conditions' => [[
              'field' => 'versions.id_note',
              'operator' => '=',
              'exp' => 'bbn_notes.id'
            ]]
          ]
        ], [
          'table' => 'bbn_notes_versions',
          'alias' => 'last_version',
          'on' => [
            'logic' => 'AND',
            'conditions' => [[
              'field' => 'last_version.id_note',
              'operator' => '=',
              'exp' => 'bbn_notes.id'
            ]]
          ]
        ], [
          'table' => 'bbn_notes_versions',
          'alias' => 'test_version',
          'type' => 'left',
          'on' => [
            'logic' => 'AND',
            'conditions' => [[
              'field' => 'test_version.id_note',
              'operator' => '=',
              'exp' => 'bbn_notes.id'
            ], [
              'field' => 'last_version.version',
              'operator' => '<',
              'exp' => 'test_version.version'
            ]]
          ]
        ], [
          'table' => 'bbn_notes_versions',
          'alias' => 'first_version',
          'on' => [
            'logic' => 'AND',
            'conditions' => [[
              'field' => 'first_version.id_note',
              'operator' => '=',
              'exp' => 'bbn_notes.id'
            ], [
              'field' => 'first_version.version',
              'operator' => '=',
              'value' => 1
            ]]
          ]
        ], [
          'table' => 'bbn_notes',
          'alias' => 'replies',
          'type' => 'left',
          'on' => [
            'logic' => 'AND',
            'conditions' => [[
              'field' => 'replies.id_alias',
              'operator' => '=',
              'exp' => 'bbn_notes.id'
            ], [
              'field' => 'replies.active',
              'value' => 1
            ]]
          ]
        ], [
          'table' => 'bbn_notes_versions',
          'alias' => 'replies_versions',
          'type' => 'left',
          'on' => [
            'logic' => 'AND',
            'conditions' => [[
              'field' => 'replies_versions.id_note',
              'operator' => '=',
              'exp' => 'replies.id'
            ]]
          ]
        ], [
          'table' => 'apst_adherents_notes',
          'on' => [
            'logic' => 'AND',
            'conditions' => [[
              'field' => 'apst_adherents_notes.id_note',
              'operator' => '=',
              'exp' => 'bbn_notes.id'
            ]]
          ]
        ], [
          'table' => 'apst_adherents',
          'on' => [
            'logic' => 'AND',
            'conditions' => [[
              'field' => 'apst_adherents.id',
              'operator' => '=',
              'exp' => 'apst_adherents_notes.id_entity'
            ]]
          ]
        ]],
        'where' => [[
          'field' => 'apst_adherents.id',
          'value' => $this->getId()
        ], [
          'field' => 'bbn_notes.active',
          'value' => 1
        ], [
          'field' => 'bbn_notes.id_parent',
          'operator' => 'isnull'
        ], [
          'field' => 'test_version.version',
          'operator' => 'isnull'
        ], [
          'field' => 'bbn_notes.id_alias',
          'operator' => 'isnull'
        ], [
          'field' => 'apst_adherents_notes.type',
          'value' => $type
        ]],
        'group_by' => 'bbn_notes.id',
        'order' => [[
          'field' => 'last_reply',
          'dir' => 'DESC'
        ], [
          'field' => 'last_edit',
          'dir' => 'DESC'
        ]]
      ]);
    }
    return null;
  }





} 