<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 14/04/2016
 * Time: 20:38
 */

namespace bbn\appui;
use bbn;

if ( !\defined('BBN_DATA_PATH') ){
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
    self::optional_init();
  }

  public function add_media($id_note, $name, $content = null, $title = '', $type='file', $private = false){
    if ( $this->exists($id_note) &&
      !empty($name) &&
      ($id_type = self::get_option_id($type, 'media')) &&
      ($usr = bbn\user::get_instance())
    ){
      $ok = false;
      switch ( $type ){
        case 'file':
        case 'link':
          if ( is_file($name) ){
            $file = basename($name);
            if ( empty($title) ){
              $title = basename($name);
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
          'name' => $file,
          'content' => $content,
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
          $ext = bbn\str::file_ext(basename($name), true);
          $filename = $ext[0];
          $extension = $ext[1];
          $length = \strlen($filename);
          if ( $files = bbn\file\dir::get_files(dirname($name)) ){
            foreach ( $files as $f ){
              if (
                (\strlen($ext[0]) > $length) &&
                preg_match('/_h[\d]+/i', substr($ext[0], $length))
              ){
                rename($f, $path.DIRECTORY_SEPARATOR.$ext[0].'.'.$ext[1]);
              }
            }
          }
          rename($name, $path.DIRECTORY_SEPARATOR.$file);
        }
        return $id;
      }
    }
    return false;
  }

  public function insert($title, $content, $private = false, $locked = false, $parent = null){
    if ( ($usr = bbn\user::get_instance()) &&
      $this->db->insert('bbn_notes', [
        'id_parent' => $parent,
        'private' => $private ? 1 : 0,
        'locked' => $locked ? 1 : 0,
        'creator' => $usr->get_id()
      ]) &&
      ($id_note = $this->db->last_id()) &&
      $this->insert_version($id_note, $title, $content)
    ){
      return $id_note;
    }
    return false;
  }

  public function insert_version(string $id_note, string $title, string $content){
    $latest = $this->latest($id_note);
    return ($usr = bbn\user::get_instance()) &&
      $this->db->insert('bbn_notes_versions', [
        'id_note' => $id_note,
        'version' => !empty($latest) ? $latest + 1 : 1,
        'title' => $title,
        'content' => $content,
        'id_user' => $usr->get_id(),
        'creation' => date('Y-m-d H:i:s')
      ]);
  }

  public function update(string $id, string $title, string $content, bool $private = null, bool $locked = null){
    if ( $old = $this->db->rselect('bbn_notes', [], ['id' => $id]) ){
      $ok = false;
      $new = [];
      if ( !\is_null($private) && ($private != $old['private']) ){
        $new['private'] = $private;
      }
      if ( !\is_null($locked) && ($locked != $old['locked']) ){
        $new['locked'] = $locked;
      }
      if ( !empty($new) ){
        $ok = $this->db->update('bbn_notes', $new, ['id' => $id]);
      }
      if ( $old_v = $this->get($id) ){
        $changed = false;
        $new_v = [
          'title' => $old_v['title'],
          'content' => $old_v['content']
        ];
        if ( $title !== $old_v['title'] ){
          $changed = true;
          $new_v['title'] = $title;
        }
        if ( $content !== $old_v['content'] ){
          $changed = true;
          $new_v['content'] = $content;
        }
        if ( !empty($changed) ){
          $ok = $this->insert_version($id, $new_v['title'], $new_v['content']);
        }
      }
      return !!$ok;
    }
    return false;
  }

  public function latest($id){
    return $this->db->get_var("SELECT MAX(version) FROM bbn_notes_versions WHERE id_note = ?", hex2bin($id));
  }

  public function get($id, $version = false, $simple = false){
    if ( !\is_int($version) ){
      $version = $this->latest($id);
    }
    if ( $res = $this->db->rselect('bbn_notes_versions', [], [
      'id_note' => $id,
      'version' => $version
    ]) ){
      if ( $simple ){
        unset($res['content']);
      }
      else{
        if ( $medias = $this->db->get_column_values('bbn_notes_medias', 'id_media', [
          'id_note' => $id
        ]) ){
          $res['medias'] = [];
          foreach ( $medias as $m ){
            if ( $med = $this->db->rselect('bbn_medias', [], ['id' => $m]) ){
              if ( \bbn\str::is_json($med['content']) ){
                $med['content'] = json_decode($med['content']);
              }
              array_push($res['medias'], $med);
            }
          }
        }
      }
    }
    return $res;
  }

  public function browse($cfg){
    if ( isset($cfg['limit']) && ($user = bbn\user::get_instance()) ){
      /** @var bbn\db $db */
      $db =& $this->db;
      $cf =& $this->class_cfg;
      $grid = new grid($this->db, $cfg, [
        'filters' => [
          'logic' => 'AND',
          'conditions' => [[
            'field' => $db->cfn($cf['arch']['notes']['active'], $cf['tables']['notes']),
            'operator' => 'eq',
            'value' => 1
          ], [
            'logic' => 'OR',
            'conditions' => [[
              'field' => $db->cfn($cf['arch']['notes']['private'], $cf['tables']['notes']),
              'operator' => 'eq',
              'value' => 0
            ], [
              'field' => $db->cfn($cf['arch']['notes']['creator'], $cf['tables']['notes']),
              'operator' => 'eq',
              'value' => $user->get_id()
            ], [
              'field' => $db->cfn($cf['arch']['versions']['id_user'], $cf['tables']['versions']),
              'operator' => 'eq',
              'value' => $user->get_id()
            ]]
          ]]
        ],
        'query' => "
          SELECT {$db->tsn($cf['tables']['versions'], 1)}.*,
          {$db->tsn($cf['tables']['notes'], 1)}.*
          FROM {$db->tsn($cf['tables']['versions'], 1)}
            JOIN {$db->tsn($cf['tables']['notes'], 1)}
              ON {$db->cfn($cf['arch']['notes']['id'], $cf['tables']['notes'], 1)} = {$db->cfn($cf['arch']['versions']['id_note'], $cf['tables']['versions'], 1)}",
        'count' => "
          SELECT COUNT(DISTINCT {$db->cfn($cf['arch']['notes']['id'], $cf['tables']['notes'], 1)})
          FROM {$db->tsn($cf['tables']['versions'], 1)}
            JOIN {$db->tsn($cf['tables']['notes'], 1)}
              ON {$db->cfn($cf['arch']['notes']['id'], $cf['tables']['notes'], 1)} = {$db->cfn($cf['arch']['versions']['id_note'], $cf['tables']['versions'], 1)}",
        'group_by' => $db->cfn($cf['arch']['versions']['id_note'], $cf['tables']['versions'])
      ]);
      return $grid->get_datatable();
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

  public function remove($id){
    if ( \bbn\str::is_uid($id) ){
      return $this->db->delete('bbn_notes', ['id' => $id]);
    }
    return false;
  }

}