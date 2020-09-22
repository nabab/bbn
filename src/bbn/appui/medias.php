<?php
namespace bbn\appui;
use bbn;

class medias extends bbn\models\cls\db
{
	use
    bbn\models\tts\references,
    bbn\models\tts\dbconfig;

  protected static
    /** @var array */
    $default_class_cfg = [
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
 
  public
    $img_extensions = ['jpeg', 'jpg', 'png', 'gif'],
    $thumbs_sizes = [[60,60],[100,100],[125,125]];
	
  public function __construct(bbn\db $db){
    parent::__construct($db);
    $this->_init_class_cfg();
    $this->opt = bbn\appui\options::get_instance();
    $this->usr = bbn\user::get_instance();
    $this->opt_id = $this->opt->from_root_code('media', 'notes', 'appui');
  }

  /**
   * Adds a new media
   *
   * @param string $name
   * @param array $content
   * @param string $title
   * @param string $type
   * @param boolean $private
   * @return string|null
   */
  public function insert(string $name, array $content = null, string $title = '', string $type='file', bool $private = false): ?string
  {
    $cf =& $this->class_cfg;
    if (
      !empty($name) &&
      ($id_type = $this->opt->from_code($type, $this->opt_id))
    ){
      $content = null;
      $ok = false;
      switch ( $type ){
        case 'link':
          if ( empty($title) ){
            $title = basename($name);
          }
          $ok = 1;
        break;
        default:
          $fs = new bbn\file\system();
          if ( $fs->is_file($name) ){
            $root = $fs->create_path($private && $this->usr->check() ? 
              bbn\mvc::get_user_data_path($this->usr->get_id(), 'appui-notes').'media/' : 
              bbn\mvc::get_data_path('appui-notes').'media/');
            if ( $root ){
              $path = bbn\x::make_storage_path($root, '', 0, $fs);
              $dpath = substr($path, strlen($root) + 1);
              $file = basename($name);
              $content = [
                'path' => $dpath,
                'size' => $fs->filesize($name),
                'extension' => bbn\str::file_ext($file)
              ];
              
              if ( empty($title) ){
                $title = basename($file);
              }
              $ok = 1;
            }
          }
          break;
      }
      if ( $ok ){
        $this->db->insert($cf['table'], [
          $cf['arch']['medias']['id_user'] => $this->usr->get_id(),
          $cf['arch']['medias']['type'] => $id_type,
          $cf['arch']['medias']['title'] => $title,
          $cf['arch']['medias']['name'] => $file ?? null,
          $cf['arch']['medias']['content'] => $content ? json_encode($content) : null,
          $cf['arch']['medias']['private'] => $private ? 1 : 0
        ]);
        $id = $this->db->last_id();
        if ( isset($file) && $fs->create_path($path.$id) ){
           $fs->move(
            $name,
            $path.$id
          );
          }
        if ( $this->is_image($path.$id.'/'.basename($name)) ){
          $image = new \bbn\file\image($path.$id.'/'.basename($name), $fs);
          $image->thumbs($path.$id, $this->thumbs_sizes);
        }
        return $id;
      }
    }
    return null;
  }

  /**
   * Returns the path to the img for the given $path and size
   * @param string $path
   * * @param array $size
   */
  public function get_thumbs(string $path, array $size = [60, 60] )
  {
    $st = '';
    $ext = '.'.pathinfo($path, PATHINFO_EXTENSION);
    $name = str_replace($ext, '',basename($path));
    $_path = str_replace($name.$ext, '', $path);
    $st .= $_path.$name.'_w'.$size[0].'h'.$size[1].$ext;
    if ( file_exists($st) ){
      return $st;
    }
    return null;
  } 

  /**
   * If the thumbs files exists for this path it returns an array of the the thumbs filename
   *
   * @param string $path
   * @return void
   */
  public function get_thumbs_path(string $path)
  {
    $res = [];
    $size = [];
    if ( file_exists($path) && $this->is_image($path) ){
      foreach($this->thumbs_sizes as $size ){
        $res[] = $this->get_thumbs($path, $size);
      }
    }
    return $res;
  }

  /**
   * Remove the thums files
   *
   * @param string $path
   * @return void
   */
  public function remove_thumbs(string $path)
  {
    if ( $thumbs = $this->get_thumbs_path($path) ){
      foreach($thumbs as $th ){
        if(file_exists($th)){
          unlink($th);
        }
      }
    }
  }

  /**
   * Returns the name of the thumb file corresponding to the given name and size
   * @param string $name
   * * @param array $size
   */
  public function get_thumbs_name(string $name,array $size = [60,60])
  {
    $tmp = explode('.', $name);
    if ( $ext = '.'.$tmp[1]){
      return $tmp[0].'_w'.$size[0].'h'.$size[1].$ext;
    }
    return null;
  }
  

  /**
   * Deletes the given media
   *
   * @param string $id
   * @return void
   */
  public function delete(string $id)
  {
    if ( \bbn\str::is_uid($id) ){
      $cf =& $this->class_cfg;
      $media = $this->get_media($id, true);
      $fs = new bbn\file\system();
      
      if ($media 
          && ($path = dirname($media['file']))
          && is_file($media['file'])
          && $this->db->delete($cf['table'], [$cf['arch']['medias']['id'] => $id])
      ) {
        if ($fs->delete($path, false)) {
          bbn\x::clean_storage_path($path);
        }
        return true;
      }
    }
    return false;
  }

  /**
   * Returns true if the given path is an image
   *
   * @param string $path
   * @return boolean
   */
  public function is_image(string $path)
  {
    if ( is_string($path) ){
      $content_type = mime_content_type($path);
      if ( strpos($content_type, 'image/' ) === 0 ){
        return true;
      }
    }
    return false;
  }
  
  /**
   * Returns the object of the media
   *
   * @param string $id
   * @param boolean $details
   * @return void
   */
  public function get_media(string $id, $details = false)
  {
    $cf =& $this->class_cfg;
    $fs = new \bbn\file\system();
    if (
      \bbn\str::is_uid($id) &&
      ($link_type = $this->opt->from_code('link', $this->opt_id)) &&
      ($media = $this->db->rselect($cf['table'], [], [$cf['arch']['medias']['id'] => $id])) &&
      ($link_type !== $media[$cf['arch']['medias']['type']]) 
    ){
      $path = '';
      if ( $media['content'] ){
        $tmp = json_decode($media[$cf['arch']['medias']['content']], true);
        $media = array_merge($tmp, $media);
      }
      $media['file'] = (
        $media['private'] ?
          bbn\mvc::get_user_data_path('appui-notes') :
          bbn\mvc::get_data_path('appui-notes')
      ).'media/'.($media['path'] ?? '').$id.'/'.$media[$cf['arch']['medias']['name']];
      if ($fs->is_file($media['file']) && $this->is_image($media['file']) ){
        $media['is_image'] = true;
      }
      return empty($details) ? $media['file'] : $media;
    }
    return false;
  }

  public function zip($medias, $dest)
  {
    if ( is_string($medias) ){
      $medias = [$medias];
    }
    if ( 
      is_array($medias) &&
      \bbn\file\dir::create_path(dirname($dest)) &&
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
  /**
   * updates the title or the name or the title of the given media at the level of the file and database
   *
   * @param string $id_media
   * @param string $name
   * @param string $title
   * @return void
   */

   public function update(string $id_media,string $name,string $title)
   {
    $new = [];
    //the old media
    $fs = new bbn\file\system();
    $old = $this->get_media($id_media, true);
    $root = bbn\mvc::get_data_path('appui-notes').'media/';
    if ( $old && 
        (($old['name'] !== $name) || ($old['title'] !== $title)) 
       ){
    	$content = json_decode($old['content'], true);  
      $path = $root.$content['path'].'/';
  
      if ( $fs->exists($path.$id_media.'/'.$old['name']) ){
        if ( $old['name'] !== $name ){
          //if the media is an image has to update also the thumbs names
          if ( $this->is_image($path.$id_media.'/'.$old['name'])){
            $thumbs_names = [
              [
                'old' => $this->get_thumbs_name($old['name'], [60,60]),
                'new' =>  $this->get_thumbs_name($name, [60,60])
              ],[
                'old' => $this->get_thumbs_name($old['name'], [100,100]),
                'new' =>  $this->get_thumbs_name($name, [100,100])
              ],[
                'old' => $this->get_thumbs_name($old['name'], [125,125]),
                'new' =>  $this->get_thumbs_name($name, [125,125])
              ]
            ];
            foreach ( $thumbs_names as $t ){
              $fs->rename($path.$id_media.'/'.$t['old'], $t['new'], true);
            }
          }
          $fs->rename($path.$id_media.'/'.$old['name'], $name, true);
        }
        if ( $this->update_db($id_media, $name, $title)){
          $new = $this->get_media($id_media, true);
        }      
      }
      return $new;
    }
    return $new;
  }
  /**
   * Updates the media on the databases
   *
   * @param string $id_media
   * @param string $name
   * @param string $title
   * @param array $content
   * @return void
   */
  public function update_db(string $id_media, string $name, string $title, array $content = []){
    $fields = [
      $this->class_cfg['arch']['medias']['name'] => $name,
			$this->class_cfg['arch']['medias']['title'] => $title
    ];
    if(!empty($content)){
      $fields[$this->class_cfg['arch']['medias']['content']] = json_encode($content);
    }
    return $this->db->update([
      'table'=> $this->class_cfg['table'],
      'fields' => $fields,
			'where'=> [
        'conditions' => [[
          'field'=> $this->class_cfg['arch']['medias']['id'],
          'value' => $id_media
        ]]
      ]]
    );
  }
  
  /**
   * Updates the content of the media when it's deleted and replaced in the bbn-upload
   *
   * @param string $id_media
   * @param integer $ref
   * @param string $oldName
   * @param string $newName
   * @param string $title
   * @return void
   */
  public function update_content(string $id_media,int $ref, string $oldName, string $newName, string $title){
    $tmp_path = \bbn\mvc::get_user_tmp_path().$ref.'/'.$oldName;
    $fs = new \bbn\file\system();
    $new_media = [];
    if ( $fs->is_file($tmp_path) ){
      $file_content = file_get_contents($tmp_path);
      $root = \bbn\mvc::get_data_path('appui-notes').'media/';
      
      if ( ($media = $this->get_media($id_media, true)) ){
        $old_path = $this->get_media_path($id_media, $oldName);
        $full_path = $this->get_media_path($id_media, $newName);
        if ( $fs->put_contents($full_path, $file_content)){
          if ( $this->is_image($full_path)){
            $image = new \bbn\file\image($full_path);
            $this->remove_thumbs($old_path);
            $image->thumbs(dirname($full_path), $this->thumbs_sizes);
            $media['is_image'] = true;
					}
        }
      }
      if( $this->update_db($id_media, $newName, $title, [
        'path' => $media['path'],
        'size' => $fs->filesize($full_path),
        'extension' => pathinfo($newName, PATHINFO_EXTENSION)
      ])){
        $new_media = $this->get_media($id_media, true);
      }
    }
    return $new_media;
    
  }
  
  /**
   * Returns the path of the given id_media
   *
   * @param string $id_media
   * @param string $name
   * @return void
   */
  public function get_media_path(string $id_media, string $name = null){
    if ( $media = $this->get_media($id_media, true) ){
      $content = json_decode($media['content'], true);
      $path = bbn\mvc::get_data_path('appui-notes').'media/'.$content['path'].$id_media.'/'.($name ? $name : $media['name']);
      return $path;
    }
    return null;
  }
}