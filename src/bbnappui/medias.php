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
 
  public
    $img_extensions = ['jpeg', 'jpg', 'png', 'gif'];	
	
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
  {die()
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
              /*if ( $this->is_image($name) ){
           			
                $imageData = base64_encode($content);
                // Format the image SRC:  data:{mime};base64,{data};
                $src = 'data: '.mime_content_type($full_path).';base64,'.$imageData;

                $media['src'] = $src;
            	} */
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
        
        return $id;
      }
    }
    return null;
  }

  public function delete(string $id){
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
	public function is_image($path){
    $a = getimagesize($path);
    $image_type = $a[2];
    if (in_array($image_type , array(IMAGETYPE_GIF , IMAGETYPE_JPEG ,IMAGETYPE_PNG , IMAGETYPE_BMP))){
      return true;
    }
    return false;
  }
  
  public function get_media(string $id, $details = false){
    $cf =& $this->class_cfg;
    if (
      \bbn\str::is_uid($id) &&
      ($link_type = $this->opt->from_code('link', $this->opt_id)) &&
      ($media = $this->db->rselect($cf['table'], [], [$cf['arch']['medias']['id'] => $id])) &&
      ($link_type !== $media[$cf['arch']['medias']['type']]) //&&
      //is_file(bbn\mvc::get_data_path('appui-notes').'media/'.$id.'/'.$media[$cf['arch']['medias']['name']])
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
      return empty($details) ? $media['file'] : $media;
    }
    return false;
  }

  public function zip($medias, $dest){
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
  
  //updates the title or the name or the title of the given media
  public function update(string $id_media, string $name, string $title){
    $new = [];
    //the old media
    $fs = new bbn\file\system();
    $old = $this->get_media($id_media, true);
    $root = bbn\mvc::get_data_path('appui-notes').'media/';
   // $path = bbn\x::make_storage_path($root, '', 0, $fs);
    if ( $old && 
        (($old['name'] !== $name) || ($old['title'] !== $title)) 
       ){
    	$content = json_decode($old['content'], true);  
      $path = $root.$content['path'].'/';
  
      if ( $fs->exists($path.$id_media.'/'.$old['name']) ){
        if ( $old['name'] !== $name ){
           $fs->rename( $path.$id_media.'/'.$old['name'], $name, true); 
        }
        if ( $this->update_db($id_media, $name, $title)){
          $new = $this->get_media($id_media, true);
        }      
      }
      return $new;
    }
    return $new;
  }
  
  public function update_db($id_media,$name, $title, $content = []){
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
  
  public function update_content($id_media, $ref,$oldName, $newName, $title){
    $tmp_path = \bbn\mvc::get_user_tmp_path().$ref.'/'.$oldName;
    $fs = new \bbn\file\system();
    $new_media = [];
    if ( $fs->is_file($tmp_path) ){
      $file_content = file_get_contents($tmp_path);
		  $root = \bbn\mvc::get_data_path('appui-notes').'media/';
      if ( ($media = $this->get_media($id_media, true)) ){
      	$tmp = json_decode($media['content'], true);
        $path = $tmp['path'];
       	$full_path = $root.$path.$id_media.'/'.$newName;
        if ($fs->put_contents($full_path, $file_content)){
          $ext = pathinfo($newName, PATHINFO_EXTENSION);
          $src = '';
            
          if ( array_search($ext, $this->img_extensions) ){
            $imageData = base64_encode(file_get_contents($full_path));
						// Format the image SRC:  data:{mime};base64,{data};
            $src = 'data: '.mime_content_type($full_path).';base64,'.$imageData;
            $media['src'] = $src;
					}
        }
      }
      if( $this->update_db($id_media, $newName, $title, [
        'path' => $media['path'], 
        'size' => $fs->filesize($full_path),
        'extension' => pathinfo($newName, PATHINFO_EXTENSION)
      ])){
        $new_media = $this->get_media($id_media, true);
        if(!empty($src)){
          $new_media['src'] = $src;
        }
    	}
    }
    return $new_media;
    
  }
}