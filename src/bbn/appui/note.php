<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 14/04/2016
 * Time: 20:38
 */

namespace bbn\appui;

if ( !defined('BBN_DATA_PATH') ){
  die("The constant BBN_DATA_PATH must be defined in order to use note");
}

class note extends \bbn\objdb
{
  private static
    $media_options = [],
    $media_option_root;

  private static function get_media_option_root(){
    if ( !isset(self::$media_option_root) && ($opt = \bbn\appui\options::get_options()) ){
      self::$media_option_root = $opt->from_code('media', 'bbn_notes');
    }
    return self::$media_option_root;
  }

  protected static function get_media_options($force = false){
    if ( empty(self::$media_options) || $force ){
      if ( ($opt = \bbn\appui\options::get_options()) && self::get_media_option_root() ){
        self::$media_options = $opt->options_codes(self::$media_option_root);
      }
    }
    return self::$media_options;
  }

  protected static function get_media_option_id($code, $force = false){
    if ( !isset(self::$media_options[$code]) || $force ){
      self::get_media_options(1);
    }
    return isset(self::$media_options[$code]) ? self::$media_options[$code] : false;
  }

  public function media_options(){
    return self::get_media_options();
  }

  public function media_option_id($code){
    return self::get_media_option_id($code);
  }

  public function exists($id_note){
    return $this->db->count('bbn_notes', ['id' => $id_note]) ? true : false;
  }

  public function add_media($id_note, $content, $title, $type='file', $private = false){
    if ( $this->exists($id_note) &&
      !empty($content) &&
      $this->media_option_id($type) &&
      ($usr = \bbn\user\connection::get_user())
    ){
      $ok = false;
      switch ( $type ){
        case 'file':
          if ( is_file($content) ){
            $file = basename($content);
            $title = basename($content);
            $ok = 1;
          }
        break;
      }
      if ( $ok ){
        $this->db->insert('bbn_medias', [
          'id_user' => $usr->get_id(),
          'type' => $this->media_option_id($type),
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
          \bbn\file\dir::create_path($path);
          rename($content, $path.DIRECTORY_SEPARATOR.$file);
        }
        return $id;
      }
    }
    return false;
  }

  public function insert($title, $content, $private = false, $parent = null){
    if ( $usr = \bbn\user\connection::get_user() ){
      if ( $this->db->insert('bbn_notes', [
        'id_parent' => $parent,
        'private' => $private ? 1 : 0,
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
}