<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 14/04/2016
 * Time: 20:38
 */

namespace bbn\appui;
use bbn;

if ( !defined('BBN_DATA_PATH') ){
  die("The constant BBN_DATA_PATH must be defined in order to use note");
}

class notes extends bbn\models\cls\db
{

  use
    bbn\models\tts\references,
    bbn\models\tts\optional,
    bbn\models\tts\dbconfig;


  protected static
    /** @var array */
    $_defaults = [
      'errors' => [
        19 => 'wrong fingerprint'
      ],
      'table' => 'bbn_notes',
      'tables' => [
        'notes' => 'bbn_notes',
        'versions' => 'bbn_notes_versions',
        'medias' => 'bbn_notes_medias'
      ],
      'arch' => [
        'notes' => [
          'id' => 'id',
          'id_parent' => 'id_parent',
          'private' => 'private',
          'creator' => 'creator',
          'active' => 'active'
        ],
        'versions' => [
          'id_note' => 'id_note',
          'version' => 'version',
          'title' => 'title',
          'content' => 'content',
          'id_user' => 'id_user',
          'creation' => 'creation'
        ],
        'medias' => [
          'id_media' => 'id_media',
          'id_note' => 'id_note',
          'id_user' => 'id_user',
          'comment' => 'comment',
          'creation' => 'creation',
        ]
      ]
    ];

  public function __construct(bbn\db $db){
    parent::__construct($db);
    self::_init_class_cfg(self::$_defaults);
    self::optional_init($this);
  }

  public function add_media($id_note, $content, $title = '', $type='file', $private = false){
    if ( $this->exists($id_note) &&
      !empty($content) &&
      ($id_type = self::get_option_id($type, 'media')) &&
      ($usr = bbn\user::get_instance())
    ){
      $ok = false;
      switch ( $type ){
        case 'file':
          if ( is_file($content) ){
            $file = basename($content);
            if ( empty($title) ){
              $title = basename($content);
            }
            $ok = 1;
          }
        break;
      }
      if ( $ok ){
        $this->db->insert('bbn_medias', [
          'id_user' => $usr->get_id(),
          'type' => $id_type,
          'title' => $title,
          'content' => $file,
          'private' => $private ? 1 : 0
        ]);
        $id = $this->db->last_id();
        $this->db->insert('bbn_notes_medias', [
          'id_note' => $id_note,
          'id_media' => $id,
          'id_user' => $usr->get_id(),
          'creation' => date('Y-m-d H:i:s')
        ]);
        if ( isset($file) ){
          $path = BBN_DATA_PATH.'media/'.$id;
          bbn\file\dir::create_path($path);
          $ext = bbn\str::file_ext($content, true);
          $filename = $ext[0];
          $extension = $ext[1];
          $length = strlen($filename);
          if ( $files = bbn\file\dir::get_files(dirname($content)) ){
            foreach ( $files as $f ){
              if (
                (strlen($ext[0]) > $length) &&
                ($ext[1] === $extension) &&
                (strpos($ext[0], $filename) === 0) &&
                preg_match('/_h[\d]+/i', substr($ext[0], $length))
              ){
                rename($f, $path.DIRECTORY_SEPARATOR.$ext[0].'.'.$ext[1]);
              }
            }
          }
          rename($content, $path.DIRECTORY_SEPARATOR.$file);
        }
        return $id;
      }
    }
    return false;
  }

  public function insert($title, $content, $private = false, $locked = false, $parent = null){
    if ( $usr = bbn\user::get_instance() ){
      if ( $this->db->insert('bbn_notes', [
        'id_parent' => $parent,
        'private' => $private ? 1 : 0,
        'locked' => $locked ? 1 : 0,
        'creator' => $usr->get_id()
      ]) ){
        $id_note = $this->db->last_id();
        $this->db->insert('bbn_notes_versions', [
          'id_note' => $id_note,
          'version' => 1,
          'title' => $title,
          'content' => $content,
          'id_user' => $usr->get_id(),
          'creation' => date('Y-m-d H:i:s')
        ]);
        return $id_note;
      }
    }
    return false;
  }

  public function latest($id){
    return $this->db->get_var("SELECT MAX(version) FROM bbn_notes_versions WHERE id_note = ?", $id);
  }

  public function get($id, $version = false){
    if ( !is_int($version) ){
      $version = $this->latest($id);
    }
    if ( $res = $this->db->rselect('bbn_notes_versions', [], [
      'id_note' => $id,
      'version' => $version
    ]) ){
      if ( $medias = $this->db->get_column_values('bbn_notes_medias', 'id_media', [
        'id_note' => $id
      ]) ){
        $res['medias'] = [];
        foreach ( $medias as $m ){
          if ( $med = $this->db->rselect('bbn_medias', [], ['id' => $m]) ){
            array_push($res['medias'], $med);
          }
        }
      }
    }
    return $res;
  }

  public function browse($limit = 100, $start = 0){
    if ( $user = bbn\user::get_instance() ){
      $cf =& $this->class_cfg;
      /** @var bbn\db $db */
      $db =& $this->db;
      $sql = "
      SELECT {$db->tsn($cf['tables']['versions'], 1)}.*,
      {$db->tsn($cf['tables']['notes'], 1)}.*
      FROM {$db->tsn($cf['tables']['versions'], 1)}
        JOIN {$db->tsn($cf['tables']['notes'], 1)}
          ON {$db->cfn($cf['arch']['notes']['id'], $cf['tables']['notes'], 1)} = {$db->cfn($cf['arch']['versions']['id_note'], $cf['tables']['versions'], 1)}
      WHERE {$db->cfn($cf['arch']['notes']['creator'], $cf['tables']['notes'], 1)} = ?
      OR {$db->cfn($cf['arch']['versions']['id_user'], $cf['tables']['versions'], 1)} = ?
      GROUP BY {$db->cfn($cf['arch']['versions']['id_note'], $cf['tables']['versions'], 1)}
      ORDER BY {$db->cfn($cf['arch']['versions']['creation'], $cf['tables']['versions'], 1)} DESC
      LIMIT $start, $limit";
      return $this->db->get_rows($sql, $user->get_id(), $user->get_id());
    }
  }

  public function count(){
    if ( $user = bbn\user::get_instance() ){
      $cf =& $this->class_cfg;
      $db =& $this->db;
      $sql = "
      SELECT COUNT(DISTINCT {$db->cfn($cf['arch']['notes']['id'], $cf['tables']['notes'], 1)})
      FROM {$db->tsn($cf['tables']['notes'], 1)}
        JOIN {$db->tsn($cf['tables']['versions'], 1)}
          ON {$db->cfn($cf['arch']['notes']['id'], $cf['tables']['notes'], 1)} = {$db->cfn($cf['arch']['versions']['id_note'], $cf['tables']['versions'], 1)}
      WHERE {$db->cfn($cf['arch']['notes']['creator'], $cf['tables']['notes'], 1)} = ?
      OR {$db->cfn($cf['arch']['versions']['id_user'], $cf['tables']['versions'], 1)} = ?";
      return $db->get_one($sql, $user->get_id(), $user->get_id());
    }
  }
}