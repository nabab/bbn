<?php
namespace bbn\Appui;

use Exception;
use bbn;
use bbn\X;
use bbn\Str;
use bbn\Db;
use bbn\Mvc;
use bbn\User;
use bbn\File\System;
use bbn\File\Image;
use bbn\Appui\Option;


class Medias extends bbn\Models\Cls\Db
{
  use bbn\Models\Tts\References;
  use bbn\Models\Tts\Dbconfig;
  use bbn\Models\Tts\Url;

  protected static
    /** @var array */
    $default_class_cfg = [
      'table' => 'bbn_medias',
      'tables' => [
        'medias' => 'bbn_medias',
        'medias_tags' => 'bbn_medias_tags',
        'medias_url' => 'bbn_medias_url',
        'medias_groups' => 'bbn_medias_groups',
        'medias_groups_medias' => 'bbn_medias_groups_medias'
      ],
      'arch' => [
        'medias' => [
          'id' => 'id',
          'id_user' => 'id_user',
          'type' => 'type',
          'mimetype' => 'mimetype',
          'name' => 'name',
          'title' => 'title',
          'description' => 'description',
          'content' => 'content',
          'private' => 'private',
          'created' => 'created',
          'edited' => 'edited',
          'editor' => 'editor'
        ],
        'medias_url' => [
          'id_media' => 'id_media',
          'id_url' => 'id_url'
        ],
        'medias_tags' => [
          'id_media' => 'id_media',
          'id_tag' => 'id_tag'
        ],
        'medias_groups' => [
          'id' => 'id',
          'id_parent' => 'id_parent',
          'text' => 'text',
          'cfg' => 'cfg'
        ],
        'medias_groups_medias' => [
          'id_media' => 'id_media',
          'id_group' => 'id_group',
          'position' => 'position'
        ]
      ],
      'urlItemField' => 'id_media',
      'urlTypeValue' => 'media'
    ];

  private $opt;
  private $usr;
  private $opt_id;

  protected $path;

  public
    $img_extensions = ['jpeg', 'jpg', 'png', 'gif'],
    $thumbs_sizes   = [
      [500, false],
      [250, false],
      [125, false],
      [96, false],
      [48, false]
    ];

  /** @var array $class_cfg */
  protected $class_cfg;

  /** @var string $imageRoot */
  protected $imageRoot;

  public function __construct(Db $db)
  {
    parent::__construct($db);
    $this->_init_class_cfg();
    $this->opt    = Option::getInstance();
    $this->usr    = User::getInstance();
    $this->opt_id = $this->opt->fromRootCode('media', 'note', 'appui');
    $this->fs     = new System();
  }

  public function setImageRoot(string $root): bool
  {
    if ($root) {
      if (substr($root, -1) !== '/') {
        $root .= '/';
      }

      $this->imageRoot = $root;
      return true;
    }

    return false;
    
  }

  public function getImageUrl(string $id = null): string
  {
    if ($this->exists($id)) {
      if ($url = $this->getUrl($id)) {
        return $url;
      }

      if (!$this->imageRoot) {
        $this->imageRoot = Mvc::getPluginUrl('appui-note').'/media/image/';
      }

      return $this->imageRoot . (string)$id;
    }
  }

  /**
   * @param string|array|null $media
   * @return string
   */
  public function getPath($media = null): string
  {
    if ($media && !is_array($media)) {
      $media = $this->getMedia($media, true);
    }

    if (!isset($this->path)) {
      $this->path = Mvc::getDataPath('appui-note').'media';
    }

    $path = $this->path;
    if ($media && $media['content'] && $media['content']['path']) {
      $path .= '/'.$media['content']['path'].$media['id']
              .'/'.$media['name'];
    }

    return $path;
  }


  /**
   * Gets an array of medias.
   *
   * @param array $cfg
   * @param int $limit
   * @param int $start
   * @return array|null
   */
  public function browse(array $cfg, int $limit = 20, int $start = 0): ?array
  {
    if ($user = User::getInstance()) {
      $cf = $this->getClassCfg();
      $ct = $cf['arch']['medias'];
      $filters = [];
      if (isset($cfg['filters'], $cfg['filters']['conditions'])) {
        $filters = $cfg['filters']['conditions'];
      }
      if (($pvtIdx = X::find($filters, ['field' => $ct['private']])) === null) {
        $filters[] = [
          'field' => $ct['private'],
          'value' => 0
        ];
      }
      else {
        $userIdx = X::find($filters, ['field' => $ct['id_user']]);
        $id_user = $user->getId();
        if (!empty($filters[$pvtIdx]['value'])) {
          if ($userIdx === null) {
            $filters[] = [
              'field' => $ct['id_user'],
              'value' => $id_user
            ];
          }
          else {
            $filters[$userIdx]['value'] = $id_user;
          }
        }
        else if ($userIdx !== null) {
          unset($filters[$userIdx]);
        }
      }
      if (isset($cfg['filters'], $cfg['filters']['conditions'])) {
        $cfg['filters']['conditions'] = $filters;
      }
      else {
        $cfg['filters'] = [
          'logic' => 'AND',
          'conditions' => $filters
        ];
      }
      $grid = new Grid($this->db, $cfg, [
        'table' => $cf['table'],
        'fields' => [],
        'join' => $cfg['join'] ?? null,
        'limit' => $cfg['limit'] ?? $limit,
        'start' => $cfg['start'] ?? $start
      ]);
      if ($data = $grid->getDatatable()) {
        foreach ($data['data'] as &$d) {
          $this->transformMedia($d);
        }
        return $data;
      }
    }
    return null;
  }


  public function browseByGroup(string $idGroup, array $cfg, int $limit = 20, int $start = 0): ?array
  {
    $cf = $this->getClassCfg();
    $t = $cf['tables']['medias_groups_medias'];
    $cfg['join'] = [[
      'table' => $t,
      'on' => [
        'conditions' => [[
          'field' => $this->db->cfn('id_media', $t),
          'exp' => $this->db->cfn('id', $cf['tables']['medias'])
        ], [
          'field' => $this->db->cfn('id_group', $t),
          'value' => $idGroup
        ]]
      ]
    ]];
    if (empty($cfg['order']) || !\is_array($cfg['order'])) {
      $cfg['order'] = [];
    }
    $cfg['order'][] = [
      'field' => $this->db->cfn('position', $t),
      'dir' => 'ASC'
    ];
    return $this->browse($cfg, $limit, $start);
  }


  /**
   * @param array $filter
   * @return int|null
   */
  public function count(array $filter = []): ?int
  {
    if ($user = User::getInstance()) {
      $cf = $this->getClassCfg();
      $ct = $cf['arch']['medias'];
      if (!isset($filter[$ct['private']])) {
        $filter[$ct['private']] = 0;
      }
      elseif ($filter[$ct['private']]) {
        $filter[$ct['id_user']] = $user->getId();
      }

      return $this->db->count($cf['table'], $filter);
    }
    return null;
  }

  /**
   * Adds a new media
   *
   * @param string $file
   * @param array|null $content
   * @param string $title
   * @param string $type
   * @param boolean $private
   * @param string|null $description
   * @return string|null
   * @throws Exception
   */
  public function insert(
      string $file,
      array $content = null,
      string $title = '',
      string $type = 'file',
      bool $private = false,
      string $description = null
  ): ?string
  {
    $cf =& $this->class_cfg;
    if (!empty($file)
        && ($id_type = $this->opt->fromCode($type, $this->opt_id))
        && ($ext = Str::fileExt($file))
    ) {
      $content = null;
      if (!$this->fs->isFile($file)) {
        throw new Exception(X::_("Impossible to find the file %s", $file));
      }

      if ($private) {
        if (!$this->usr->check()) {
          return null;
        }

        $root = Mvc::getUserDataPath($this->usr->getId(), 'appui-note');
      }
      else {
        $root = Mvc::getDataPath('appui-note');
      }

      $root   .= 'media/';
      $path    = X::makeStoragePath($root, '', 0, $this->fs);
      $dpath   = substr($path, strlen($root));
      $name    = X::basename($file);
      $mime    = mime_content_type($file) ?: null;
      $content = [
        'path' => $dpath,
        'size' => $this->fs->filesize($file),
        'extension' => $ext
      ];
      if (empty($title)) {
        $title = trim(str_replace(['-', '_', '+'], ' ', X::basename($file, ".$ext")));
      }

      if (!$this->db->insert(
        $cf['table'],
        [
          $cf['arch']['medias']['id_user'] => $this->usr->getId() ?: BBN_EXTERNAL_USER_ID,
          $cf['arch']['medias']['type'] => $id_type,
          $cf['arch']['medias']['mimetype'] => $mime,
          $cf['arch']['medias']['title'] => $title,
          $cf['arch']['medias']['description'] => $description,
          $cf['arch']['medias']['name'] => $name ?? null,
          $cf['arch']['medias']['content'] => $content ? json_encode($content) : null,
          $cf['arch']['medias']['private'] => $private ? 1 : 0,
          $cf['arch']['medias']['created'] => date('Y-m-d H:i:s')
        ]
      )) {
        throw new Exception(X::_("Impossible to insert the media in the database"));
      }

      $id = $this->db->lastId();
      if ($this->fs->createPath($path.$id)) {
        $this->fs->move(
          $file,
          $path.$id
        );
        $new_file = $path.$id.'/'.X::basename($file);
        if (strpos($mime, 'image/') === 0) {
          $image = new Image($new_file, $this->fs);
          $image->thumbs($path.$id, $this->thumbs_sizes);
        }
      }


      return $id;
    }

    return null;
  }


  /**
   * @param string|array $media
   * @return array
   */
  public function getThumbsSizes($media): array
  {
    if (!is_array($media)) {
      $media = $this->getMedia($media);
    }

    if (!is_array($media)) {
      throw new Exception("Impossible to find the media in the database");
    }

    if (!($path = $this->getPath($media))) {
      throw new Exception("Impossible to retrieve the path");
    }
    
    $list = $this->fs->getFiles(X::dirname($path));
    if (count($list) > 1) {
      $sizes = [];
      foreach ($list as $l) {
        preg_match('/.*\_w([0-9]*)\.[a-zA-Z]*$/', $l, $m);
        if (!empty($m) && !empty($m[1])) {
          $sizes[] = $m[1];
        }
      }
      sort($sizes);
      return $sizes;
    }

    return [];
  }

  /**
   * Returns the path to the img for the given $path and size
   * @param string $path
   * @param array $size
   */
  public function getThumbPath(string $path, array $size = [60, 60], $if_exists = true)
  {
    if (isset($size[0], $size[1]) && (Str::isInteger($size[0]) || Str::isInteger($size[1]))) {
      $ext = '.'.Str::fileExt($path);
      $file = substr($path, 0, - strlen($ext));
      if ($size[0] && Str::isInteger($size[0])) {
        $file .= '_w'.$size[0];
      }

      if ($size[1] && Str::isInteger($size[1])) {
        $file .= '_h'.$size[1];
      }

      $file .= $ext;

      if (!$if_exists || file_exists($file)) {
        return $file;
      }
    }

    return null;
  }


  /**
   * If the thumbs files exists for this path it returns an array of the the thumbs filename
   *
   * @param string $path
   * @return array
   */
  public function getThumbsPath(string $path)
  {
    $res  = [];

    if (file_exists($path) && $this->isImage($path)) {
      foreach($this->thumbs_sizes as $size){
        if (($result = $this->getThumbPath($path, $size)) !== null) {
          $res[] = $result;
        }
      }
    }

    return $res;
  }


  /**
   * Remove the thumbs files
   *
   * @param string $path
   * @return void
   */
  public function removeThumbs(string $path)
  {
    if ($thumbs = $this->getThumbsPath($path)) {
      foreach($thumbs as $th){
        if(file_exists($th)) {
          unlink($th);
        }
      }
    }
  }


  /**
   * Returns the name of the thumb file corresponding to the given name and size
   * @param string $name
   * @param array $size
   */
  public function getThumbsName(string $name,array $size = [60,60])
  {
    $tmp = explode('.', $name);
    if (isset($tmp[1]) && $ext = '.'.$tmp[1]) {
      return $tmp[0].'_w'.$size[0].'h'.$size[1].$ext;
    }

    return null;
  }


  /**
   * Deletes the given media
   *
   * @param string $id
   * @return bool
   */
  public function delete(string $id)
  {
    if (Str::isUid($id)) {
      $cf    =& $this->class_cfg;
      $media =  $this->getMedia($id, true);

      if ($media
          && ($path = X::dirname($media['file']))
          && is_file($media['file'])
          && $this->db->delete($cf['table'], [$cf['arch']['medias']['id'] => $id])
      ) {
        if ($this->fs->delete($path, false)) {
          X::cleanStoragePath($path);
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
  public function isImage(string $path)
  {
    if (is_string($path) && is_file($path)) {
      $content_type = mime_content_type($path);
      if (strpos($content_type, 'image/') === 0) {
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
   * @param int|null $width
   * @return array|false|string
   */
  public function getMedia(string $id, bool $details = false, int $width = null)
  {
    $cf =& $this->class_cfg;
    if (Str::isUid($id)
        && ($link_type = $this->opt->fromCode('link', $this->opt_id))
        && ($media = $this->db->rselect($cf['table'], $cf['arch']['medias'], [$cf['arch']['medias']['id'] => $id]))
        && ($link_type !== $media[$cf['arch']['medias']['type']])
    ) {
      $this->transformMedia($media, $width);

      return empty($details) ? $media['file'] : $media;
    }

    return false;
  }


  /**
   * @param $medias
   * @param $dest
   * @return bool
   */
  public function zip($medias, $dest)
  {
    if (is_string($medias)) {
      $medias = [$medias];
    }

    if (is_array($medias)
        && $this->fs->createPath(X::dirname($dest))
        && ($zip = new \ZipArchive())
        && ((        is_file($dest)
        && ($zip->open($dest, \ZipArchive::OVERWRITE) === true))
        || ($zip->open($dest, \ZipArchive::CREATE) === true)        )
    ) {
      foreach ($medias as $media){
        if ($file = $this->getMedia($media)) {
          $zip->addFile($file, X::basename($file));
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
   * @return array|false|string
   */
  public function update(string $id_media,string $name,string $title)
  {
    $new = [];
    //the old media
    $old  = $this->getMedia($id_media, true);
    $root = Mvc::getDataPath('appui-note').'media/';
    if ($old
        && (($old['name'] !== $name) || ($old['title'] !== $title))
    ) {
        $content = json_decode($old['content'], true);
        $path    = $root.$content['path'].'/';

      if ($this->fs->exists($path.$id_media.'/'.$old['name'])) {
        if ($old['name'] !== $name) {
          //if the media is an image has to update also the thumbs names
          if ($this->isImage($path.$id_media.'/'.$old['name'])) {
            $thumbs_names = [
              [
                'old' => $this->getThumbsName($old['name'], [60,60]),
                'new' => $this->getThumbsName($name, [60,60])
              ],[
                'old' => $this->getThumbsName($old['name'], [100,100]),
                'new' => $this->getThumbsName($name, [100,100])
              ],[
                'old' => $this->getThumbsName($old['name'], [125,125]),
                'new' => $this->getThumbsName($name, [125,125])
              ]
            ];
            foreach ($thumbs_names as $t){
              $this->fs->rename($path.$id_media.'/'.$t['old'], $t['new'], true);
            }
          }

          $this->fs->rename($path.$id_media.'/'.$old['name'], $name, true);
        }

        if ($this->updateDb($id_media, $name, $title)) {
          $new = $this->getMedia($id_media, true);
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
   * @param array  $content
   * @return int|null
   */
  public function updateDb(string $id_media, string $name, string $title, array $content = [])
  {
    $fields = [
      $this->class_cfg['arch']['medias']['name'] => $name,
            $this->class_cfg['arch']['medias']['title'] => $title
    ];
    if(!empty($content)) {
      $fields[$this->class_cfg['arch']['medias']['content']] = json_encode($content);
    }

    return $this->db->update(
      [
      'table' => $this->class_cfg['table'],
      'fields' => $fields,
          'where' => [
            'conditions' => [[
              'field' => $this->class_cfg['arch']['medias']['id'],
              'value' => $id_media
            ]]
          ]
      ]
    );
  }


  /**
   * Updates the content of the media when it's deleted and replaced in the bbn-upload
   *
   * @param string  $id_media
   * @param integer $ref
   * @param string  $oldName
   * @param string  $newName
   * @param string  $title
   * @return array|false|string
   */
  public function updateContent(string $id_media, int $ref, string $oldName, string $newName, string $title)
  {
    $tmp_path  = Mvc::getUserTmpPath().$ref.'/'.$oldName;
    $new_media = [];
    if ($this->fs->isFile($tmp_path)) {
      $file_content = file_get_contents($tmp_path);

      if (($media = $this->getMedia($id_media, true))) {
        $old_path  = $this->getMediaPath($id_media, $oldName);
        $full_path = $this->getMediaPath($id_media, $newName);
        if ($this->fs->putContents($full_path, $file_content)) {
          if ($this->isImage($full_path)) {
            $image = new Image($full_path);
            $this->removeThumbs($old_path);
            $image->thumbs(X::dirname($full_path), $this->thumbs_sizes, '_%s', true);
            $media['is_image'] = true;
          }
        }

        if($this->updateDb(
          $id_media, $newName, $title, [
            'path' => $media['path'],
            'size' => $this->fs->filesize($full_path),
            'extension' => X::pathinfo($full_path, PATHINFO_EXTENSION)
          ]
        )
        ) {
          $new_media = $this->getMedia($id_media, true);
        }
      }
    }

    return $new_media;

  }


  /**
   * Replaces the content of the media
   * @param string $id
   * @param string $file
   * @return array|false
   */
  public function replaceContent(string $id, string $file)
  {
    if (!$this->fs->isFile($file)) {
      throw new Exception(X::_("Impossible to find the file %s", $file));
    }
    if (($ext = Str::fileExt($file))
      && ($media = $this->getMedia($id, true))
    ) {
      $cf      =& $this->class_cfg;
      $root    = Mvc::getDataPath('appui-note') . 'media/';
      $path    = $media[$cf['arch']['medias']['content']]['path'];
      $oldFile = $root . $path . $media[$cf['arch']['medias']['name']];
      $name    = X::basename($file);
      $title   = $media[$cf['arch']['medias']['title']];
      $mime    = mime_content_type($file) ?: null;
      $content = [
        'path' => $path,
        'size' => $this->fs->filesize($file),
        'extension' => $ext
      ];
      if (empty($title)) {
        $title = trim(str_replace(['-', '_', '+'], ' ', X::basename($file, ".$ext")));
      }
      if (!$this->db->update(
        $cf['table'],
        [
          $cf['arch']['medias']['mimetype'] => $mime,
          $cf['arch']['medias']['title'] => $title,
          $cf['arch']['medias']['name'] => $name,
          $cf['arch']['medias']['content'] => json_encode($content)
        ],
        [
          $cf['arch']['medias']['id'] => $id
        ]
      )) {
        throw new Exception(X::_("Impossible to update the media in the database"));
      }
      $this->removeThumbs($oldFile);
      $this->fs->delete($oldFile);
      if (strpos($mime, 'image/') === 0) {
        $image = new Image($file, $this->fs);
        $image->thumbs($root . $path . $id, $this->thumbs_sizes);
      }
      $this->fs->move(
        $file,
        $root . $path . $id
      );
      return $this->getMedia($id, true);
    }
    return false;
  }


  /**
   * Sets the title of the media
   * @param string $id
   * @param string $title
   * return bool
   */
  public function setTitle(string $id, string $title): bool
  {
    $cf =& $this->class_cfg;
    return (bool)$this->db->update(
      $cf['table'],
      [
        $cf['arch']['medias']['title'] => $title
      ],
      [
        $cf['arch']['medias']['id'] => $id
      ]
    );
  }


  /**
   * Sets the description of the media
   * @param string $id
   * @param string $description
   * return bool
   */
  public function setDescription(string $id, string $description): bool
  {
    $cf =& $this->class_cfg;
    return (bool)$this->db->update(
      $cf['table'],
      [
        $cf['arch']['medias']['description'] => $description
      ],
      [
        $cf['arch']['medias']['id'] => $id
      ]
    );
  }


  /**
   * Returns the path of the given id_media
   *
   * @param string $id_media
   * @param string|null $name
   * @return string|null
   */
  public function getMediaPath(string $id_media, string $name = null)
  {
    if ($media = $this->getMedia($id_media, true) && !empty($media['content'])) {
      $path    = bbn\Mvc::getDataPath('appui-note').'media/'.$media['content']['path'].$id_media.'/'.($name ? $name : $media['name']);
      return $path;
    }

    return null;
  }


  protected function transformMedia(array &$data, int $width = null): void
  {
    $data['is_image'] = false;

    if (!empty($data['content'])) {
      $data['content'] = json_decode($data['content'], true);
      $full_path = $this->getPath($data);
      $data['file'] = $full_path;
      $data['full_path'] = $this->getThumbPath($full_path);
      $data['path'] = $this->getImageUrl($data['id']);
      $data['is_image'] = $this->isImage($full_path);
      if ($data['is_image']) {
        $d['thumbs'] = $this->getThumbsSizes($data);
        if ($width) {
          foreach ($d['thumbs'] as $size) {
            if ($size >= $width) {
              $dot = strrpos($data['file'], '.');
              $tmpFile = substr($data['file'], 0, $dot) . '_w' . $size .
                strtolower(substr($data['file'], $dot));
              if ($this->fs->isFile($tmpFile) && $this->isImage($tmpFile)) {
                $data['file'] = $tmpFile;
              }
              break;
            }
          }
        }
      }
    }
  }

}
