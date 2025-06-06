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
use bbn\Models\Tts\References;
use bbn\Models\Tts\DbActions;
use bbn\Models\Tts\Url;
use bbn\Models\Tts\Tagger;
use bbn\Models\Cls\Db as DbCls;

class Medias extends DbCls
{
  use References;
  use DbActions;
  use Url;
  use Tagger;

    /** @var array */
    protected static $default_class_cfg = [
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
          'position' => 'position',
          'link'=> 'link'
        ]
      ],
      'urlItemField' => 'id_media',
      'urlTypeValue' => 'media'
    ];

  private $opt;
  private $usr;
  private $userId;
  private $opt_id;
  /** @var array $thumbsSizes Keeping the thumbs sizes in memory by type */
  private $thumbsSizes;
  protected System $fs;

  protected $path;
  protected $img_extensions = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
  protected $thumbs_sizes   = [
    [500, false],
    [250, false],
    [125, false],
    [96, false],
    [48, false]
  ];

  protected $defaultUrlType;

  /** @var array $class_cfg */
  protected $class_cfg;

  /** @var string $imageRoot */
  protected $imageRoot;

  /** @var string $fileRoot */
  protected $fileRoot;


  public function __construct(Db $db, array|null $cfg = null)
  {
    parent::__construct($db);
    $this->initClassCfg($cfg);
    $this->opt    = Option::getInstance();
    $this->usr    = User::getInstance();
    $this->opt_id = $this->opt->fromCode('media', 'note', 'appui');
    $this->fs     = new System();
    $this->defaultUrlType = 'media';
    $this->taggerInit(
      $this->class_cfg['tables']['medias_tags'],
      [
        'id_tag' => $this->class_cfg['arch']['medias_tags']['id_tag'],
        'id_element' => $this->class_cfg['arch']['medias_tags']['id_media']
      ]
    );
    $this->userId = $this->usr->getId() ?: $this->setExternalUser();
  }


  public function setExternalUser()
  {
    $this->userId = defined('BBN_EXTERNAL_USER_ID') ? BBN_EXTERNAL_USER_ID : null;
    return $this->userId;
  }


  public function getUserId(): ?string
  {
    return $this->userId;
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


  public function setFileRoot(string $root): bool
  {
    if ($root) {
      if (substr($root, -1) !== '/') {
        $root .= '/';
      }

      $this->fileRoot = $root;
      return true;
    }

    return false;
  }


  public function getImageUrl(string|null $id = null): ?string
  {
    if ($this->dbTraitExists($id)) {
      if ($url = $this->getUrl($id)) {
        return $url;
      }

      if (!$this->imageRoot) {
        $this->setImageRoot(Mvc::getPluginUrl('appui-note').'/media/image/');
      }

      return $this->imageRoot . (string)$id;
    }

    return null;
  }


  public function getFileUrl(string|null $id = null): ?string
  {
    if ($this->dbTraitExists($id)) {
      if ($url = $this->getUrl($id)) {
        return $url;
      }

      if (!$this->fileRoot) {
        $this->setFileRoot(Mvc::getPluginUrl('appui-note').'/media/download/');
      }

      return $this->fileRoot . (string)$id;
    }

    return null;
  }


  /**
   * @param string|array|null $media
   * @return string
   */
  public function getPath($media = null): string
  {
    if (is_string($media)) {
      $media = $this->getMedia($media, true);
    }

    if (!is_array($media)) {
      throw new Exception("Impossible to find the media in the database");
    }

    if (!isset($this->path)) {
      $this->path = Mvc::getDataPath('appui-note').'media';
    }

    if (!isset($media['content'], $media['content']['path'])) {
      throw new Exception("The media doesn't seem to have a path");
    }

    return $this->path . '/' . $media['content']['path'] . $media['id'] . '/' . $media['name'];
  }


  public function search($search, $cfg = [], int $limit = 20, int $start = 0): ?array
  {
    $cf = $this->getClassCfg();
    $cft = $this->taggerObject->getClassCfg();

    if (!isset($cfg['join'])) {
      $cfg['join'] = [];
    }

    $cfg['join'][] = [
      'type' => 'left',
      'table' => $cf['tables']['medias_tags'],
      'on' => [
        [
          'field' => $this->db->cfn($cf['arch']['medias_tags']['id_media'], $cf['tables']['medias_tags']),
          'exp' => $this->db->cfn($cf['arch']['medias']['id'], $cf['tables']['medias'], true)
        ]
      ]
    ];
    $cfg['join'][] = [
      'type' => 'left',
      'table' => $cft['tables']['tags'],
      'on' => [
        [
          'field' => $this->db->cfn($cf['arch']['medias_tags']['id_tag'], $cf['tables']['medias_tags']),
          'exp' => $this->db->cfn($cft['arch']['tags']['id'], $cft['tables']['tags'], true)
        ]
      ]
    ];
    if (!isset($cfg['filters'])) {
      $cfg['filters'] = [
        'logic' => 'AND',
        'conditions' => []
      ];
    }

    $filter = [
      'logic' => 'OR',
      'conditions' => []
    ];

    $filter['conditions'][] = [
      'field' => $this->db->cfn($cf['arch']['medias']['title'], $cf['tables']['medias']),
      'operator' => 'contains',
      'value' => $search
    ];
    $filter['conditions'][] = [
      'field' => $this->db->cfn($cf['arch']['medias']['name'], $cf['tables']['medias']),
      'operator' => 'contains',
      'value' => $search
    ];
    $filter['conditions'][] = [
      'field' => $this->db->cfn($cft['arch']['tags']['tag'], $cft['tables']['tags']),
      'operator' => 'contains',
      'value' => $search
    ];

    $cfg['filters']['conditions'][] = $filter;

    return $this->browse($cfg, $limit, $start);
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
    if ($this->usr) {
      $cf = $this->getClassCfg();
      $ct = $cf['arch']['medias'];
      $filters = [];
      if (isset($cfg['filters'], $cfg['filters']['conditions'])) {
        $filters = $cfg['filters']['conditions'];
        if ((count($filters) === 1) && ($filters[0]['field'] === $cf['arch']['medias']['title'])) {
          unset($cfg['filters']);
          return $this->search($filters[0]['value'], $cfg, $limit, $start);
        }
      }
      if (($pvtIdx = X::search($filters, ['field' => $ct['private']])) === null) {
        $filters[] = [
          'field' => $ct['private'],
          'value' => 0
        ];
      }
      else {
        $userIdx = X::search($filters, ['field' => $ct['id_user']]);
        $id_user = $this->userId;
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
        'fields' => array_merge($ct, ['last_mod' => "IFNULL(edited, created)"]),
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


  public function createGroup(string $text): ?string
  {
    $cf = $this->getClassCfg();
    $t = $cf['tables']['medias_groups'];
    $f = $cf['arch']['medias_groups'];
    if ($this->db->insert($t, [
      $f['text'] => Str::sanitizeHtml($text)
    ])) {
      return $this->db->lastId();
    }

    return null;
  }


  public function getGroup(string $id): string
  {
    $cf = $this->getClassCfg();
    $t = $cf['tables']['medias_groups'];
    $f = $cf['arch']['medias_groups'];
    return $this->db->selectOne($t, $f['text'], [$f['id'] => $id]);
  }


  public function renameGroup(string $id, string $text): bool
  {
    $cf = $this->getClassCfg();
    $t = $cf['tables']['medias_groups'];
    $f = $cf['arch']['medias_groups'];
    return (bool)$this->db->update($t, [
      $f['text'] => Str::sanitizeHtml($text)
    ], [
      $f['id'] => $id
    ]);
  }


  public function addToGroup(string $id_media, string $id_group, bool $addTag = false): bool
  {
    $cf = $this->getClassCfg();
    $t = $cf['tables']['medias_groups_medias'];
    $f = $cf['arch']['medias_groups_medias'];
    $order = $this->db->selectOne($t, 'position', ['id_group' => $id_group], ['position' => 'DESC']) + 1;
    $res = $this->db->insertIgnore($t, [
      $f['id_group'] => $id_group,
      $f['id_media'] => $id_media,
      $f['position'] => $order ?: null
    ]);
    if ($res && $addTag) {
      $group = $this->getGroup($id_group);
      $this->addTag($id_media, $group);
    }

    return (bool)$res;
  }


  public function removeFromGroup(string $id_media, string $id_group): bool
  {
    $cf = $this->getClassCfg();
    $t = $cf['tables']['medias_groups_medias'];
    $f = $cf['arch']['medias_groups_medias'];
    return (bool)$this->db->deleteIgnore($t, [
      $f['id_group'] => $id_group,
      $f['id_media'] => $id_media
    ]);
  }


  public function browseByGroup(string $idGroup, array $cfg = [], int $limit = 20, int $start = 0): ?array
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

    if ($res = $this->browse($cfg, $limit, $start)) {
      foreach ($res['data'] as $i => $d) {
        $media_groups_media = $this->db->rselect(
          $t,
          [],
          [
            $cf['arch']['medias_groups_medias']['id_group'] => $idGroup,
            $cf['arch']['medias_groups_medias']['id_media'] => $d[$cf['arch']['medias']['id']]
          ]
        );
        $res['data'][$i][$cf['arch']['medias_groups_medias']['position']] = $media_groups_media['position'];
        $res['data'][$i][$cf['arch']['medias_groups_medias']['link']] = $media_groups_media['link'];
      }
    }
    return $res;
  }


  /**
   * @param array $filter
   * @return int|null
   */
  public function count(array $filter = []): ?int
  {
    if ($this->usr) {
      $cf = $this->getClassCfg();
      $ct = $cf['arch']['medias'];
      if (!isset($filter[$ct['private']])) {
        $filter[$ct['private']] = 0;
      }
      elseif ($filter[$ct['private']]) {
        $filter[$ct['id_user']] = $this->userId;
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
      array|null $content = null,
      string $title = '',
      string $type = 'file',
      bool $private = false,
      string|null $description = null
  ): ?string
  {
    $cf =& $this->class_cfg;
    if (!empty($file)
        && ($id_type = $this->opt->fromCode($type, $this->opt_id))
        && ($ext = Str::fileExt($file))
    ) {
      if (($type === 'file') && $this->isImage($file)) {
        $id_type = $this->opt->fromCode('image', $this->opt_id);
      }

      $content = null;
      if (!$this->fs->isFile($file)) {
        throw new Exception(X::_("Impossible to find the file %s", $file));
      }

      if ($private) {
        if (!$this->usr->check()) {
          return null;
        }

        $root = Mvc::getUserDataPath($this->userId, 'appui-note');
      }
      else {
        $root = Mvc::getDataPath('appui-note');
      }

      $root   .= 'media/';
      $path    = X::makeStoragePath($root, '', 0, $this->fs);
      $dpath   = substr($path, strlen($root));
      $name    = normalizer_normalize(X::basename($file));
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
          $cf['arch']['medias']['id_user'] => $this->userId,
          $cf['arch']['medias']['type'] => $id_type,
          $cf['arch']['medias']['mimetype'] => $mime,
          $cf['arch']['medias']['title'] => normalizer_normalize($title),
          $cf['arch']['medias']['description'] => normalizer_normalize($description),
          $cf['arch']['medias']['name'] => $name ?: null,
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
        // Normalize the filename
        rename($path.$id.'/'.X::basename($file), $path.$id.'/'.$name);
        $newFile = $path.$id.'/'.$name;
        chmod($newFile, 0644);
        if (strpos($mime, 'image/') === 0) {
          $image = new Image($newFile, $this->fs);
          $tst = $this->getThumbsSizesByType($id_type);
          $ts =  !empty($tst) && !empty($tst['thumbs']) ? \array_map(function($t){
            return [
              empty($t['width']) ? false : $t['width'],
              empty($t['height']) ? false : $t['height'],
              X::hasProps($t, ['width', 'height', 'crop'], true) ? true : false
            ];
          }, $tst['thumbs']) : $this->thumbs_sizes;
          $image->thumbs($path.$id, $ts, '.bbn-%s');
        }
      }


      return $id;
    }

    return null;
  }


  /**
   * Returns an array of sizes rules, each corresponding to a thumbnail
   * @param string $id_type
   * @return array
   */
  public function getThumbsSizesByType(string $id_type): array
  {
    if (!isset($this->thumbsSizes[$id_type])) {
      $o = $this->opt->option($id_type);
      if (!$o) {
        throw new Exception(X::_("The given type doesn't exist"));
      }

      $this->thumbsSizes[$id_type] = $o;
    }

    return $this->thumbsSizes[$id_type];
  }


  /**
   * @param string|array $media
   * @return array
   */
  public function getThumbsSizes($media, $exists = true): array
  {
    if (is_string($media)) {
      $media = $this->getMedia($media);
    }

    if (!is_array($media)) {
      throw new Exception("Impossible to find the media in the database");
    }

    if (empty($media['file'])) {
      throw new Exception("Impossible to retrieve the path");
    }

    $cfg = $this->getThumbsSizesByType($media['type']);
    $res = [];
    foreach ($cfg['thumbs'] as $thumb) {
      $size = [
        empty($thumb['width']) ? false : $thumb['width'],
        empty($thumb['height']) ? false : $thumb['height'],
        X::hasProps($thumb, ['width', 'height', 'crop'], true) ? true : false
      ];
      if (!$exists || $this->fs->exists($this->getThumbPath($media['file'], $size))) {
        $res[] = $size;
      }
    }

    return $res;
  }

  /**
   * Returns the path to the img for the given $path and size
   * @param string $path
   * @param array $size
   */
  protected function getThumbPath(string $path, array $size, bool $if_exists = true, bool $create = false): ?string
  {
    if (count($size) && (Str::isInteger($size[0]) || Str::isInteger($size[1] ?? null))) {
      $ext = Str::fileExt($path, true);
      $file = dirname($path) . '/' . $ext[0] . '.bbn';
      // Width
      if ($size[0]) {
        $file .= '-w-' . $size[0];
      }
      // Height
      if ($size[1]) {
        $file .= '-h-' . $size[1];
      }
      // Cropped
      if ($size[0] && $size[1] && !empty($size[2])) {
        $file .= '-c';
      }

      $file .= '.' . $ext[1];

      if (!$if_exists) {
        return $file;
      }
      
      if (!$this->fs->exists($path)) {
        return null;
      }

      $exists = $this->fs->exists($file);

      if ($create && !$exists) {
        $img = new Image($path);
        if ($size[0] && ($size[0] >= $img->getWidth())) {
          symlink($path, $file);
        }
        elseif ($size[1] && ($size[1] >= $img->getHeight())) {
          symlink($path, $file);
        }
        elseif ($img->resize($size[0] ?: null, $size[1] ?: null, $size[2] ?? false)) {
          $img->save($file);
        }
      }

      if ($exists || $this->fs->exists($file)) {
        return $file;
      }
    }

    return null;
  }


  /**
   * If the thumbs files exists for this path it returns an array of the the thumbs filename
   *
   * @param $media
   * @param bool $if_exists
   * @param bool $create
   * @return array
   */
  public function getThumbsPath($media, bool $if_exists = true, bool $create = false, bool $delete = false)
  {
    $res  = [];
    if (is_string($media)) {
      $media = $this->getMedia($media, true);
    }

    if (!is_array($media)) {
      throw new Exception(X::_("The media doesn't exist"));
    }

    if (!empty($media['is_image']) && $this->fs->exists($media['file'])) {
      if ($delete) {
        $files = $this->fs->getFiles(dirname($media['file']));
        if (count($files) > 1) {
          foreach ($files as $f) {
            if ($f !== $media['file']) {
              $this->fs->delete($f);
            }
          }
        }
      }

      foreach ($media['thumbs'] as $size) {
        if (($result = $this->getThumbPath($media['file'], $size, $if_exists, $create)) !== null) {
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
  public function removeThumbs($media)
  {
    if (is_string($media)) {
      $media = $this->getMedia($media);
    }

    if (!is_array($media)) {
      throw new Exception(X::_("The media doesn't exist"));
    }

    if ($thumbs = $this->getThumbsPath($media)) {
      foreach($thumbs as $th){
        if ($this->fs->exists($th)) {
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
    if (count($size) === 1) {
      $size[] = $size[0];
    }

    if ((count($size) !== 2) || !Str::isInteger($size[0], $size[1])) {
      return null;
    }

    $ext = Str::fileExt($name, true);
    $dir = dirname($name);
    if (!empty($dir)) {
      $dir .= '/';
    }

    return $dir . $ext[0] . '_w' . $size[0] . 'h' . $size[1] . (empty($ext[1]) ? '' : '.' . $ext[1]);
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
    if (is_string($path) && $this->fs->isFile($path)) {
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
  public function getMedia(string $id, bool $details = false, ?int $width = null, ?int $height = null, bool $crop = false, bool $force = false)
  {
    $cf =& $this->class_cfg;
    if (Str::isUid($id)
        && ($link_type = $this->opt->fromCode('link', $this->opt_id))
        && ($media = $this->db->rselect($cf['table'], $cf['arch']['medias'], [$cf['arch']['medias']['id'] => $id]))
        && ($link_type !== $media[$cf['arch']['medias']['type']])
    ) {
      $this->transformMedia($media, $width, $height, $crop, $force);
      if (empty($details)) {
        return $media['file'];
      }
      else {
        $media['url'] = $this->getUrl($id);
        return $media;
      }
    }

    return null;
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
  public function update(string $id_media, string $name, string $title)
  {
    $new = [];
    //the old media
    $old  = $this->getMedia($id_media, true);
    if ($old && (($old['name'] !== $name) || ($old['title'] !== $title))) {
      $content = $old['content'];
      if (Str::isJson($content)) {
        $content = \json_decode($content, true);
      }

      if ($this->fs->exists($old['file'])) {
        $path = dirname($old['file']).'/';
        if ($old['name'] !== $name) {
          //if the media is an image has to update also the thumbs names
          if ($this->isImage($old['file'])) {
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
              $this->fs->rename($path . $t['old'], $t['new'], true);
            }
          }

          $this->fs->rename($path . $old['name'], $name, true);
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
        $old_path  = $media['file'];
        $full_path = $this->getMediaPath($id_media, $newName);
        if ($this->fs->putContents($full_path, $file_content)) {
          if ($this->isImage($full_path)) {
            $thumbs_sizes = $this->getThumbsSizes($media);
            $image = new Image($full_path);
            $this->removeThumbs($old_path);
            $image->thumbs(X::dirname($full_path), $thumbs_sizes, '.bbn-%s', true);
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
      $oldFile = $media['file'];
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
          $cf['arch']['medias']['content'] => json_encode($content),
          $cf['arch']['medias']['edited'] => date('Y-m-d H:i:s')
        ],
        [
          $cf['arch']['medias']['id'] => $id
        ]
      ) && false) {
        throw new Exception(X::_("Impossible to update the media in the database"));
      }
      $this->removeThumbs($media);
      $this->fs->delete($oldFile);
      if (strpos($mime, 'image/') === 0) {
        $tst = $this->getThumbsSizesByType($media['type']);
        $ts =  !empty($tst) && !empty($tst['thumbs']) ? \array_map(function($t){
          return [
            empty($t['width']) ? false : $t['width'],
            empty($t['height']) ? false : $t['height'],
            X::hasProps($t, ['width', 'height', 'crop'], true) ? true : false
          ];
        }, $tst['thumbs']) : $this->thumbs_sizes;
        $image = new Image($file, $this->fs);
        $image->thumbs($root . $path . $id, $ts, '.bbn-%s');
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
  public function getMediaPath($media, string|null $name = null)
  {
    if (is_string($media)) {
      $media = $this->getMedia($media);
    }

    if (!is_array($media)) {
      throw new Exception("Impossible to find the media in the database");
    }

    if (!empty($media['content'])) {
      $path = Mvc::getDataPath('appui-note').'media/'.$media['content']['path']
          . $media['id'] . '/' . ($name ? $name : $media['name']);

      return $path;
    }

    return null;
  }


  /**
   * Fixes the order of a medias group. If the group ID is not given, all groups will be fixed.
   * @param string $idGroup
   * @return int
   */
  public function fixOrder(string $idGroup = ''): int
  {
    $groups = !empty($idGroup) ? [$idGroup] : $this->db->getColumnValues($this->class_cfg['tables']['medias_groups'], $this->class_cfg['arch']['medias_groups']['id']);
    $fixed = 0;
    if (!empty($groups)) {
      $mgTable = $this->class_cfg['tables']['medias_groups'];
      $mgmTable = $this->class_cfg['tables']['medias_groups_medias'];
      $mTable = $this->class_cfg['tables']['medias'];
      $mFields = $this->class_cfg['arch']['medias'];
      $mgFields = $this->class_cfg['arch']['medias_groups'];
      $mgmFields = $this->class_cfg['arch']['medias_groups_medias'];
      $positionMgm = $this->db->cfn($mgmFields['position'], $mgmTable);
      $idMg = $this->db->cfn($mgFields['id'], $mgTable);
      $idM = $this->db->cfn($mFields['id'], $mTable);
      $idGroupMgm = $this->db->cfn($mgmFields['id_group'], $mgmTable);
      $idMediaMgm = $this->db->cfn($mgmFields['id_media'], $mgmTable);
      $createdM = $this->db->cfn($mFields['created'], $mTable);
      foreach ($groups as $group) {
        $medias = $this->db->rselectAll([
          'table' => $mgmTable,
          'fields' => [
            $idMediaMgm,
            $idGroupMgm,
            $positionMgm,
            $createdM
          ],
          'join' => [[
            'table' => $mgTable,
            'on' => [
              'conditions' => [[
                'field' => $idMg,
                'exp' => $idGroupMgm
              ]]
            ]
          ], [
            'table' => $mTable,
            'on' => [
              'conditions' => [[
                'field' => $idM,
                'exp' => $idMediaMgm
              ]]
            ]
          ]],
          'where' => [
            $idGroupMgm => $group
          ],
          'order' => [
            $positionMgm => 'ASC',
            $createdM => 'ASC'
          ]
        ]);
        $this->db->update($mgmTable, [
          $mgmFields['position'] => null
        ], [
          $mgmFields['id_group'] => $group
        ]);
        foreach ($medias as $i => $media) {
          if ($this->db->update($mgmTable, [
              $mgmFields['position'] => $i + 1
            ], [
              $mgmFields['id_group'] => $media[$mgmFields['id_group']],
              $mgmFields['id_media'] => $media[$mgmFields['id_media']]
            ])
            && \is_null($media[$mgmFields['position']])
          ) {
            $fixed++;
          }
        }
      }
    }
    return $fixed;
  }


  protected function transformMedia(array &$data, ?int $width = null, ?int $height = null, bool $crop = false, bool $force = false): void
  {
    if (!empty($data['content'])) {
      $data['content'] = json_decode($data['content'], true);
      $file = $this->getPath($data);
      $data['file']      = $file;
      $data['full_path'] = $file;
      $data['is_image']  = $this->isImage($file);
      $data['path']      = empty($data['is_image']) ? $this->getFileUrl($data['id']) : $this->getImageUrl($data['id']);
      if ($data['is_image'] && is_file($file)) {
        $img                = new Image($file);
        $data['is_thumb']   = false;
        $data['thumbs']     = $this->getThumbsSizes($data, false);
        $data['dimensions'] = [
          'w' => $img->getWidth(),
          'h' => $img->getHeight()
        ];
        if ($width || $height || ($crop && ($data['dimensions']['w'] !== $data['dimensions']['h']))) {
          $goodSize = $force ? [$width, $height, $crop] : null;
          $redirect = false;
          if (!$goodSize) {
            foreach ($data['thumbs'] as $size) {
              if (($width == $size[0])
                && ($height == $size[1])
                && ($crop == ($size[2] ?? false))
              ) {
                $goodSize = $size;
                $redirect = false;
                break;
              }
              if (((!empty($width) && ($width <= $size[0]))
                  || (!empty($height) && ($width <= $size[0])))
                && ($crop == ($size[2] ?? false))
              ) {
                $goodSize = $size;
                $redirect = true;
              }
            }
          }

          if (!$goodSize) {
            $data['redirect'] = true;
          }

          if ($goodSize && ($tmpFile = $this->getThumbPath($file, $goodSize, true, true))) {
            $data['is_thumb'] = true;
            if ($redirect) {
              $data['redirect'] = $redirect;
            }
            $data['file'] = $tmpFile;
          }
        }
      }
    }
  }
}
