<?php
namespace bbn\appui;
use bbn;

if ( !\defined('BBN_DATA_PATH') ){
  die("The constant BBN_DATA_PATH must be defined in order to use medias");
}

class medias extends bbn\models\cls\db
{

  use
    bbn\models\tts\references,
    bbn\models\tts\dbconfig;


  protected static
    /** @var array */
    $_defaults = [
      'table' => 'bbn_medias',
      'tables' => [
        'medias' => 'bbn_medias'
      ],
      'arch' => [
        'medias' => [
          'id' => 'id',
          'id_user' => 'id_user',
					'type' => 'type',
          'name' => 'name',
          'title' => 'title',
					'content' => 'content',
          'private' => 'private'
        ]
      ]
    ];

  private
    $opt,
    $usr,
    $opt_id;

  public function __construct(bbn\db $db){
    parent::__construct($db);
    self::_init_class_cfg(self::$_defaults);
    $this->opt = bbn\appui\options::get_instance();
    $this->usr = bbn\user::get_instance();
    $this->opt_id = $this->opt->from_code('media', 'notes', 'appui');
  }

  public function insert($name, $content = null, $title = '', $type='file', $private = false){
    $cf =& $this->class_cfg;
    if ( !empty($name) &&
      ($id_type = $this->opt->from_code($type, $this->opt_id))
    ){
      $ok = false;
      switch ( $type ){
        case 'link':
          if ( empty($title) ){
            $title = basename($name);
          }
          $ok = 1;
        break;
        default:
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
        $this->db->insert($cf['table'], [
          $cf['arch']['medias']['id_user'] => $this->usr->get_id(),
          $cf['arch']['medias']['type'] => $id_type,
          $cf['arch']['medias']['title'] => $title,
          $cf['arch']['medias']['name'] => $file ?? '',
          $cf['arch']['medias']['content'] => $content,
          $cf['arch']['medias']['private'] => $private ? 1 : 0
        ]);
        $id = $this->db->last_id();
        if ( isset($file) ){
          rename(
            $name,
            bbn\file\dir::create_path(BBN_DATA_PATH.'media/'.$id).'/'.$file
          );
        }
        return $id;
      }
    }
    return false;
  }

  public function delete(string $id){
    if ( \bbn\str::is_uid($id) ){
      $cf =& $this->class_cfg;
      if ( $this->db->delete($cf['table'], [$cf['arch']['medias']['id'] => $id]) ){
        if ( is_dir(BBN_DATA_PATH.'media/'.$id) ){
          return \bbn\file\dir::delete(BBN_DATA_PATH.'media/'.$id);
        }
        return true;
      }
    }
    return false;
  }

  public function get_media(string $id){
    $cf =& $this->class_cfg;
    if (
      \bbn\str::is_uid($id) &&
      ($link_type = $this->opt->from_code('link', $this->opt_id)) &&
      ($media = $this->db->rselect($cf['table'], [], [$cf['arch']['medias']['id'] => $id])) &&
      ($link_type !== $media[$cf['arch']['medias']['type']]) &&
      is_file(BBN_DATA_PATH.'media/'.$id.'/'.$media[$cf['arch']['medias']['name']])
    ){
      return BBN_DATA_PATH.'media/'.$id.'/'.$media[$cf['arch']['medias']['name']];
    }
    return false;
  }

  public function zip($medias, $dest){
    if ( is_string($medias) ){
      $medias = [$medias];
    }
    if ( 
      is_array($medias) &&
      ($zip = new \ZipArchive()) &&
      (
        (
          is_file($dest) &&
          ($zip->open($dest, \ZipArchive::OVERWRITE) === true)
        ) ||
        ($zip->open($dest, \ZipArchive::CREATE) === true)
      )
    ){
      foreach ( $medias as $media ){
        if ( $file = $this->get_media($media) ){
          $zip->addFile($file, basename($file));
        }
      }
      return $zip->close();
    }
    return false;
  }
}
