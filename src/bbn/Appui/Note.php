<?php

/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 14/04/2016
 * Time: 20:38.
 */

namespace bbn\Appui;

use bbn\Db;
use bbn\Str;
use bbn\X;
use bbn\User;
use bbn\Mvc;
use Exception;
use bbn\Models\Tts\References;
use bbn\Models\Tts\Optional;
use bbn\Models\Tts\DbActions;
use bbn\Models\Tts\Url;
use bbn\Models\Tts\Tagger;
use bbn\Models\Cls\Db as DbCls;

if (!\defined('BBN_DATA_PATH')) {
  die('The constant BBN_DATA_PATH must be defined in order to use Note');
}

class Note extends DbCls
{
  use References;
  use Optional;
  use DbActions;
  use Url;
  use Tagger;

  private $medias;
  private $usr;
  private $userId;

  /** @var string The default language used */
  protected $lang;

  /** @var array */
  protected static $default_class_cfg = [
    'errors' => [
      19 => 'wrong fingerprint',
    ],
    'table' => 'bbn_notes',
    'tables' => [
      'notes' => 'bbn_notes',
      'versions' => 'bbn_notes_versions',
      'features' => 'bbn_notes_features',
      'notes_medias' => 'bbn_notes_medias',
      'medias' => 'bbn_medias',
      'notes_tags' => 'bbn_notes_tags',
      'notes_url' => 'bbn_notes_url',
      'notes_events' => 'bbn_notes_events'
    ],
    'arch' => [
      'notes' => [
        'id' => 'id',
        'id_parent' => 'id_parent',
        'id_alias' => 'id_alias',
        'id_type' => 'id_type',
        'id_option' => 'id_option',
        'mime' => 'mime',
        'lang' => 'lang',
        'private' => 'private',
        'locked' => 'locked',
        'pinned' => 'pinned',
        'important' => 'important',
        'creator' => 'creator',
        'active' => 'active',
      ],
      'versions' => [
        'id_note' => 'id_note',
        'version' => 'version',
        'latest' => 'latest',
        'title' => 'title',
        'content' => 'content',
        'excerpt' => 'excerpt',
        'id_user' => 'id_user',
        'creation' => 'creation',
      ],
      'features' => [
        'id' => 'id',
        'id_option' => 'id_option',
        'id_note' => 'id_note',
        'id_media' => 'id_media',
        'num' => 'num',
        'cfg' => 'cfg'
      ],
      'notes_medias' => [
        'id' => 'id',
        'id_note' => 'id_note',
        'version' => 'version',
        'id_media' => 'id_media',
        'id_user' => 'id_user',
        'comment' => 'comment',
        'creation' => 'creation',
        'default_media' => 'default_media'
      ],
      'medias' => [
        'id' => 'id',
        'id_user' => 'id_user',
        'type' => 'type',
        'name' => 'name',
        'title' => 'title',
        'content' => 'content',
        'private' => 'private',
      ],
      'notes_events' => [
        'id_note' => 'id_note',
        'id_event' => 'id_event',
      ],
      'notes_tags' => [
        'id_note' => 'id_note',
        'id_tag' => 'id_tag',
      ],
      'notes_url' => [
        'id_url' => 'id_url',
        'id_note' => 'id_note',
      ]
    ],
    'paths' => [
      'medias' => 'media/',
    ],
    'urlItemField' => 'id_note',
    'urlTypeValue' => 'note'
  ];

  /** @var array $class_cfg */
  protected $class_cfg;

  /**
   * Note constructor.
   *
   * @param Db $db
   * @throws Exception
   */
  public function __construct(Db $db, string $lang = null)
  {
    parent::__construct($db);
    $this->initClassCfg(self::$default_class_cfg);
    self::optionalInit();
    $this->urlType = 'note';
    $this->taggerInit(
      $this->class_cfg['tables']['notes_tags'],
      [
        'id_tag' => $this->class_cfg['arch']['notes_tags']['id_tag'],
        'id_element' => $this->class_cfg['arch']['notes_tags']['id_note']
      ]
    );
    $this->lang = $lang ?: (defined('BBN_LANG') ? BBN_LANG : 'en');
    $this->usr    = User::getInstance();
    $this->userId = $this->usr->getId() ?: $this->setExternalUser();
  }


  public function setExternalUser()
  {
    $this->userId = defined('BBN_EXTERNAL_USER_ID') ? constant('BBN_EXTERNAL_USER_ID') : null;
    return $this->userId;
  }


  public function getUserId(): ?string
  {
    return $this->userId;
  }


  public function setLang($lang): self
  {
    $this->lang = $lang;
    return $this;
  }


  public function getLang(): string
  {
    return $this->lang;
  }


  /**
   * @return Medias
   */
  public function getMediaInstance()
  {
    if (!$this->medias) {
      $this->medias = new Medias($this->db);
    }

    if ($this->medias->getUserId() !== $this->userId) {
      $this->medias->setExternalUser();
    }

    return $this->medias;
  }


  /**
   * @param $title
   * @param $content
   * @return string
   */
  public function getExcerpt($title, $content): string
  {
    $excerpt = '';
    if (!empty($title)) {
      $excerpt .= Str::html2text($title, false) . PHP_EOL . PHP_EOL;
    }

    if (!empty($content)) {
      if (Str::isJson($content)) {
        $ct = json_decode($content, true);
        foreach ($ct as $n => $c) {
          if (is_string($c) && in_array($n, ['title', 'text', 'html'])) {
            if (Str::isHTML($c)) {
              $excerpt .= Str::html2text($c, strpos($c, PHP_EOL) > 0) . PHP_EOL . PHP_EOL;
            }
            else {
              $excerpt .= $c;
            }
          } elseif (is_array($c)) {
            foreach ($c as $k => $v) {
              if (is_string($v)) {
                if (is_string($k)) {
                  $excerpt .= $k . ': ';
                }

                $excerpt .= Str::html2text($v, strpos($v, PHP_EOL) > 0) . PHP_EOL . PHP_EOL;
              }
            }
          }
        }
      }
      elseif (Str::isHTML($content)) {
        $excerpt .= Str::html2text($content);
      }
      elseif (is_string($content)) {
        $excerpt .= $content;
      }
    }

    return $excerpt;
  }


  /**
   * Creates a new note in the database
   * 
   * @param string|array $title The title or the whole config in an indexed array
   * @param string $content
   * @param string|null $type
   * @param bool $private
   * @param bool $locked
   * @param string|null $parent
   * @param string|null $alias
   * @param string $mime
   * @param string $lang
   * @param string|null $id_option
   * @return string|null
   */
  public function insert(
    $title,
    string $content = '',
    string $id_type = null,
    bool   $private = false,
    bool   $locked = false,
    string $id_parent = null,
    string $id_alias = null,
    string $mime = '',
    string $lang = '',
    string $id_option = null,
    string $excerpt = '',
    bool   $pinned = false,
    bool   $important = false
  ): ?string
  {
    $props = [
      'title',
      'content',
      'id_type',
      'private',
      'locked',
      'id_parent',
      'id_alias',
      'mime',
      'lang',
      'id_option',
      'excerpt',
      'pinned',
      'important'
    ];
    if (is_array($title)) {
      $cfg = $title;
    }
    else {
      $cfg = [];
    }

    foreach ($props as $prop) {
      if (!array_key_exists($prop, $cfg)) {
        $cfg[$prop] = $$prop;
      }
    }

    if (empty($cfg['content']) && empty($cfg['title'])) {
      return null;
    }

    if (empty($cfg['lang']) && defined('BBN_LANG')) {
      $cfg['lang'] = BBN_LANG;
    }

    $cf = &$this->class_cfg;
    if (is_null($cfg['id_type'])) {
      $cfg['id_type'] = self::getOptionId('personal', 'types');
    }

    if (!$cfg['excerpt']) {
      $cfg['excerpt'] = $this->getExcerpt($cfg['title'], $cfg['content']);
    }

    $id_note = null;

    if ($this->userId
      && $this->db->insert(
        $cf['table'],
        [
          $cf['arch']['notes']['id_parent'] => $cfg['id_parent'],
          $cf['arch']['notes']['id_alias'] => $cfg['id_alias'],
          $cf['arch']['notes']['id_type'] => $cfg['id_type'],
          $cf['arch']['notes']['id_option'] => $cfg['id_option'],
          $cf['arch']['notes']['private'] => !empty($cfg['private']) ? 1 : 0,
          $cf['arch']['notes']['locked'] => !empty($cfg['locked']) ? 1 : 0,
          $cf['arch']['notes']['creator'] => $this->userId,
          $cf['arch']['notes']['mime'] => $cfg['mime'],
          $cf['arch']['notes']['lang'] => $cfg['lang'],
          $cf['arch']['notes']['pinned'] => !empty($cfg['pinned']) ? 1 : 0,
          $cf['arch']['notes']['important'] => !empty($cfg['important']) ? 1 : 0
        ]
      )
      && ($id_note = $this->db->lastId())
    ) {
      $this->insertVersion($id_note, $cfg['title'], $cfg['content'], $cfg['excerpt']);
    }

    return $id_note;
  }


  /**
   * Adds a new version to the given note if it's different from the last, and returns the latest version.
   *
   * @param string $id_note
   * @param string $title
   * @param string $content
   * @return int|null
   */
  public function insertVersion(string $id_note, string $title = '', string $content = '', string $excerpt = ''): ?int
  {
    if ($this->check()
        && $this->userId
        && ($note = $this->get($id_note))
        && ($title || $content)
    ) {
      $cf     = &$this->class_cfg;
      $latest = $note['version'] ?? 0;
      if (!$latest 
          || ($note['content'] != $content)
          || ($note['title'] != $title)
          || ($note['excerpt'] != $excerpt)
      ) {
        $next = $latest + 1;
      }

      if (
        isset($next) && $this->db->insert(
          $cf['tables']['versions'],
          [
            $cf['arch']['versions']['id_note'] => $id_note,
            $cf['arch']['versions']['version'] => $next ?? 1,
            $cf['arch']['versions']['latest'] => 1,
            $cf['arch']['versions']['title'] => $title,
            $cf['arch']['versions']['content'] => $content,
            $cf['arch']['versions']['excerpt'] => $excerpt ?: '',
            $cf['arch']['versions']['id_user'] => $this->userId,
            $cf['arch']['versions']['creation'] => date('Y-m-d H:i:s'),
          ]
        )
      ) {
        $this->db->update(
          $cf['tables']['versions'],
          [$cf['arch']['versions']['latest'] => 0],
          [
            $cf['arch']['versions']['id_note'] => $id_note,
            ['version', '!=', $next]
          ]
        );

        return $next;
      }

      return $latest;
    }

    return null;
  }


  /**
   * @param string $id
   * @param string $title
   * @param string $content
   * @param bool|null $private
   * @param bool|null $locked
   * @return int|null
   */
  public function update(
     string $id,
     $title,
     string $content = '',
     bool   $private = false,
     bool   $locked = false,
     string $excerpt = '',
     bool   $pinned = false,
     bool   $important = false
  ): ?int
  {
    $props = [
      'title',
      'content',
      'private',
      'locked',
      'excerpt',
      'pinned',
      'important'
    ];
    if (is_array($title)) {
      $cfg = $title;
    }
    else {
      $cfg = [];
    }

    foreach ($props as $prop) {
      if (!array_key_exists($prop, $cfg)) {
        $cfg[$prop] = $$prop;
      }
    }

    if (empty($cfg['content']) && empty($cfg['title'])) {
      return null;
    }

    $ok = null;
    if ($old = $this->get($id)) {
      $ok  = 0;
      $new_note = [];
      $new_version = [];
      foreach ($props as $p) {
        if ($cfg[$p] != $old[$p]) {
          if (in_array($p, ['content', 'title', 'excerpt'])) {
            $new_version[$p] = $cfg[$p];
          }
          else {
            $new_note[$p] = $cfg[$p];
          }
        }
      }

      if (!empty($new_note)) {
        $ok = $this->db->update('bbn_notes', $new_note, ['id' => $id]);
      }

      if (!empty($new_version)) {
        $ok = $this->insertVersion($id, $cfg['title'], $cfg['content'], $cfg['excerpt']);
      }
    }

    return $ok;
  }


  /**
   * Changes the type of the note
   *
   * @param string $id_note
   * @param string $type
   * @return null|int The number of affected rows (1 if ok)
   */
  public function setType(string $id_note, string $type): int
  {
    $cf = &$this->class_cfg;
    return $this->db->update(
      $cf['tables']['notes'],
      [$cf['arch']['notes']['id_type'] => $type],
      [$cf['arch']['notes']['id'] => $id_note]
    );
  }


  /**
   * Changes the id_option of the note
   *
   * @param string $id_note
   * @param string $id_option
   * @return int The number of affected rows (1 if ok)
   */
  public function setOption(string $id_note, string $id_option): int
  {
    $cf = &$this->class_cfg;
    return $this->db->update(
      $cf['tables']['notes'],
      [$cf['arch']['notes']['id_option'] => $id_option],
      [$cf['arch']['notes']['id'] => $id_note]
    );
  }


  /**
   * @param $id
   * @return mixed
   */
  public function latest($id)
  {
    $cf = &$this->class_cfg;

    return $this->db->selectOne(
      $cf['tables']['versions'],
      'MAX(' . $cf['arch']['versions']['version'] . ')',
      [
        $cf['arch']['versions']['id_note'] => $id,
      ]
    );
  }


  public function getTitle(string $id): ?string
  {
    $cf = &$this->class_cfg;
    return $this->db->selectOne(
      $cf['tables']['versions'],
      $cf['arch']['versions']['title'],
      [$cf['arch']['versions']['id_note'] => $id],
      [$cf['arch']['versions']['version'] => 'DESC']
    );
  }


  /**
   * @param string $id
   * @param int|null $version
   * @param bool $simple
   * @return array|null
   */
  public function get(string $id, int $version = null, bool $simple = false): ?array
  {
    $cf = &$this->class_cfg;
    if (!\is_int($version)) {
      $version = $this->latest($id) ?: 1;
    }

    if ($res = $this->db->rselect(
      $cf['tables']['notes'],
      [],
      [
        $cf['arch']['notes']['id'] => $id,
      ]
    )) {
      if ($tmp = $this->db->rselect(
        $cf['tables']['versions'],
        [],
        [
          $cf['arch']['versions']['id_note'] => $id,
          $cf['arch']['versions']['version'] => $version,
        ]
      )) {
        $res = array_merge($res, $tmp);
      }

      if ($simple) {
        unset($res[$cf['arch']['versions']['content']]);
      }
      else {
        if ($medias = $this->db->getColumnValues(
          $cf['tables']['notes_medias'],
          $cf['arch']['notes_medias']['id_media'],
          [
            $cf['arch']['notes_medias']['id_note'] => $id
          ]
        )) {
          $media         = $this->getMediaInstance();
          $res['medias'] = [];
          foreach ($medias as $m) {
            $res['medias'][] = $media->getMedia($m, true);
          }
        }
      }

      return $res;
    }

    return null;
  }


  /**
   * @param string $id
   * @param int|null $version
   * @return array|null
   */
  public function getFull(string $id, int $version = null): ?array
  {
    $cf = &$this->class_cfg;
    if (!\is_int($version)) {
      $version = $this->latest($id) ?: 1;
    }

    if ($res = $this->db->rselect(
      [
        'table' => $cf['table'],
        'fields' => [
          $cf['arch']['notes']['id'],
          $cf['arch']['notes']['id_parent'],
          $cf['arch']['notes']['id_alias'],
          $cf['arch']['notes']['id_type'],
          $cf['arch']['notes']['id_option'],
          $cf['arch']['notes']['private'],
          $cf['arch']['notes']['locked'],
          $cf['arch']['notes']['pinned'],
          $cf['arch']['notes']['important'],
          $cf['arch']['versions']['version'],
          $cf['arch']['versions']['excerpt'],
          $cf['arch']['versions']['title'],
          $cf['arch']['versions']['content'],
          $cf['arch']['versions']['id_user'],
          $cf['arch']['versions']['creation'],
        ],
        'join' => [[
          'table' => $cf['tables']['versions'],
          'on' => [
            'conditions' => [[
              'field' => $cf['arch']['versions']['id_note'],
              'exp' => $cf['arch']['notes']['id'],
            ], [
              'field' => $cf['arch']['versions']['version'],
              'value' => $version,
            ]],
          ],
        ]],
        'where' => [
          'conditions' => [
            [
              'field' => $cf['arch']['notes']['id'],
              'value' => $id,
            ]
          ],
        ],
      ]
    )) {
      $res['medias'] = $this->getMedias($id, $res['version']);

      return $res;
    }

    return null;
  }


  /**
   * @param string $url
   * @param bool $full
   * @return array|null
   */
  public function urlToNote(string $url, bool $full = false): ?array
  {
    if ($id = $this->urlToId($url)) {
      if ($full) {
        return $this->getFull($id);
      } else {
        return $this->get($id);
      }
    }

    return null;
  }


  /**
   * Insert the given url to the note if has no url and update it otherwise.
   *
   * @param string $id_note
   * @param string $url
   * @return int|null
   */
  public function insertOrUpdateUrl(string $id_note, string $url)
  {
    if (!$this->dbTraitExists($id_note)) {
      throw new Exception(
        X::_(
          "Impossible to retrieve the note with ID %s",
          Str::isUid($id_note) ? $id_note : '[String (' . strlen($id_note) . ')]'
        )
        );
    }

    return $this->setUrl($id_note, $url);
  }



  /**
   * @param null $type
   * @param mixed|false $id_user
   * @param int $limit
   * @param int $start
   * @return array|false
   */
  public function getByType($type = null, $id_user = false, int $limit = 10, int $start = 0)
  {
    $db  = &$this->db;
    $cf  = &$this->class_cfg;
    $res = [];
    if (!Str::isUid($type)) {
      $type = self::getOptionId(is_null($type) ? 'personal' : $type, 'types');
    }

    if (Str::isUid($type) && is_int($limit) && is_int($start)) {
      $where = [[
        'field' => $db->cfn($cf['arch']['notes']['id_type'], $cf['table']),
        'value' => $type,
      ], [
        'field' => $db->cfn($cf['arch']['notes']['active'], $cf['table']),
        'value' => 1,
      ]];
      if (Str::isUid($id_user)) {
        $where[] = [
          'field' => $db->cfn($cf['arch']['notes']['creator'], $cf['table']),
          'value' => $id_user,
        ];
      }

      $cfg = $this->getLastVersionCfg();
      $cfg['where'] = [
        'conditions' => $where,
      ];
      $cfg['limit'] = $limit;
      $cfg['start'] = $start;
      $notes = $db->rselectAll($cfg);
      foreach ($notes as $note) {
        if ($medias = $db->getColumnValues(
          $cf['tables']['notes_medias'],
          $cf['arch']['notes_medias']['id_media'],
          [
            $cf['arch']['notes_medias']['id_note'] => $note[$cf['arch']['versions']['id_note']]
          ]
        )) {
          $note['medias'] = [];
          foreach ($medias as $m) {
            if ($med = $db->rselect($cf['tables']['medias'], [], [$cf['arch']['medias']['id'] => $m])) {
              if (Str::isJson($med[$cf['arch']['medias']['content']])) {
                $med[$cf['arch']['medias']['content']] = json_decode($med[$cf['arch']['medias']['content']]);
              }
              $note['medias'][] = $med;
            }
          }
        }

        $res[] = $note;
      }

      X::sortBy($res, $cf['arch']['versions']['creation'], 'DESC');

      return $res;
    }

    return false;
  }


  /**
   * @param string $id
   * @return array|null
   */
  public function getVersions(string $id): ?array
  {
    if (Str::isUid($id)) {
      $cf = &$this->class_cfg;

      return $this->db->rselectAll(
        [
          'table' => $cf['tables']['versions'],
          'fields' => [
            $cf['arch']['versions']['version'],
            $cf['arch']['versions']['id_user'],
            $cf['arch']['versions']['creation'],
          ],
          'where' => [
            'conditions' => [[
              'field' => $cf['arch']['versions']['id_note'],
              'value' => $id,
            ]],
          ],
          'order' => [[
            'field' => $cf['arch']['versions']['version'],
            'dir' => 'DESC',
          ]],
        ]
      );
    }

    return null;
  }


  /**
   * @param null $type
   * @param string|false $id_user
   * @return false|mixed
   */
  public function countByType($type = null, $id_user = false)
  {
    $db = &$this->db;
    $cf = &$this->class_cfg;
    if (!Str::isUid($type)) {
      $type = self::getOptionId(is_null($type) ? 'personal' : $type, 'types');
    }

    if (Str::isUid($type)) {
      $where = [[
        'field' => $cf['arch']['notes']['active'],
        'value' => 1,
      ], [
        'field' => $cf['arch']['notes']['id_type'],
        'value' => $type,
      ]];
      if (!empty($id_user) && Str::isUid($id_user)) {
        $where[] = [
          'field' => $cf['arch']['notes']['creator'],
          'value' => $id_user,
        ];
      }

      return $db->selectOne(
        [
          'table' => $cf['table'],
          'fields' => ['COUNT(DISTINCT ' . $cf['arch']['notes']['id'] . ')'],
          'where' => [
            'conditions' => $where,
          ],
        ]
      );
    }

    return false;
  }


  /**
   * @param $id_note
   * @param string $name
   * @param array|null $content
   * @param string $title
   * @param string $type
   * @param bool $private
   * @return string|null
   * @throws Exception
   */
  public function addMedia($id_note, string $name, array $content = null, string $title = '', string $type = 'file', bool $private = false): ?string
  {
    $media = $this->getMediaInstance();

    // Case where we give also the version (i.e. not the latest)
    if (\is_array($id_note) && (count($id_note) === 2)) {
      $version = $id_note[1];
      $id_note = $id_note[0];
    } else {
      $version = $this->latest($id_note) ?: 1;
    }

    if (
      $this->dbTraitExists($id_note)
      && ($id_media = $media->insert($name, $content, $title, $type, $private))
      && $this->addMediaToNote($id_media, $id_note, $version)
    ) {
      return $id_media;
    }

    return null;
  }


  /**
   * @param string $id_media
   * @param string $id_note
   * @param int $default
   * @return int|null
   */
  public function addMediaToNote(string $id_media, string $id_note, int $default = 0): ?int
  {
    if ($this->userId) {
      $cf = &$this->class_cfg;

      if ($default) {
        $this->db->update(
          $cf['tables']['notes_medias'],
          [$cf['arch']['notes_medias']['default_media'] => 0],
          [$cf['arch']['notes_medias']['id_note'] => $id_note]
        );
      }

      return $this->db->insertUpdate(
        $cf['tables']['notes_medias'],
        [
          $cf['arch']['notes_medias']['id_note'] => $id_note,
          $cf['arch']['notes_medias']['version'] => $this->latest($id_note),
          $cf['arch']['notes_medias']['id_media'] => $id_media,
          $cf['arch']['notes_medias']['id_user'] => $this->userId,
          $cf['arch']['notes_medias']['creation'] => date('Y-m-d H:i:s'),
          $cf['arch']['notes_medias']['default_media'] => $default
        ]
      );
    }

    return null;
  }


  /**
   * Removes a row associating a given media and a given note.
   * 
   * @param string $id_media
   * @param string $id_note
   * @return int|null
   * @throws Exception
   */
  public function removeMedia(string $id_media, string $id_note): ?int
  {
    $cf = &$this->class_cfg;
    if (
      $this->db->selectOne($cf['tables']['medias'], $cf['arch']['medias']['id'], [$cf['arch']['medias']['id'] => $id_media])
      && $this->dbTraitExists($id_note)
    ) {
      return $this->db->delete($cf['tables']['notes_medias'], [
        $cf['arch']['notes_medias']['id_note'] => $id_note,
        $cf['arch']['notes_medias']['id_media'] => $id_media,
      ]);
    }

    return null;
  }


  /**
   * Removes all the rows associating medias with a given note.
   * 
   * @param string $id_note
   * @return int|null
   * @throws Exception
   */
  public function removeAllMedias(string $id_note): ?int
  {
    $cf = &$this->class_cfg;
    if ($this->dbTraitExists($id_note)) {
      return $this->db->delete($cf['tables']['notes_medias'], [
        $cf['arch']['notes_medias']['id_note'] => $id_note
      ]);
    }

    return null;
  }


  /**
   * @param string $id_note
   * @param false $version
   * @param false $type
   * @return array
   * @throws Exception
   */
  public function getMedias(string $id_note, $version = false, $type = false): array
  {
    $ret   = [];
    $media = $this->getMediaInstance();
    $cf    = &$this->class_cfg;
    if ($this->dbTraitExists($id_note)) {
      $medias = $this->db->getColumnValues(
        $cf['tables']['notes_medias'],
        $cf['arch']['notes_medias']['id_media'],
        [
          $cf['arch']['notes_medias']['id_note'] => $id_note
        ]);
      if ($medias) {
        foreach ($medias as $m) {
          $ret[] = $media->getMedia($m, true);
        }
      }
    }

    return $ret;
  }


  /**
   * @param string $id_note
   * @param false $version
   * @param string $id_media
   * @return bool|null
   * @throws Exception
   */
  public function hasMedias(string $id_note, $version = false, string $id_media = ''): ?bool
  {
    $cf = &$this->class_cfg;
    if ($this->dbTraitExists($id_note)) {
      $where = [
        $cf['arch']['notes_medias']['id_note'] => $id_note
      ];
      if (!empty($id_media) && Str::isUid($id_media)) {
        $where[$cf['arch']['notes_medias']['id_media']] = $id_media;
      }

      return (bool)$this->db->count($cf['tables']['notes_medias'], $where);
    }

    return null;
  }


  /**
   * Returns the configuration to have the last version for each note
   *
   * @param boolean $with_content
   * @return array
   */
  public function getLastVersionCfg($with_content = false): array
  {
    
      $db       = &$this->db;
      $cf       = &$this->class_cfg;
      $opt      = Option::getInstance();
      $cfo      = $opt->getClassCfg();
      $grid_cfg = [
        'table' => $cf['table'],
        'fields' => [
          $db->cfn($this->fields['id'], $cf['table']),
          $db->cfn($this->fields['id_parent'], $cf['table']),
          $db->cfn($this->fields['id_alias'], $cf['table']),
          $db->cfn($this->fields['id_type'], $cf['table']),
          $db->cfn($this->fields['id_option'], $cf['table']),
          $db->cfn($this->fields['mime'], $cf['table']),
          $db->cfn($this->fields['lang'], $cf['table']),
          $db->cfn($this->fields['private'], $cf['table']),
          $db->cfn($this->fields['locked'], $cf['table']),
          $db->cfn($this->fields['pinned'], $cf['table']),
          $db->cfn($this->fields['important'], $cf['table']),
          $db->cfn($this->fields['creator'], $cf['table']),
          $db->cfn($this->fields['active'], $cf['table']),
          $db->cfn($cf['arch']['versions']['id_note'], $cf['tables']['versions']),
          $db->cfn($cf['arch']['versions']['version'], $cf['tables']['versions']),
          $db->cfn($cf['arch']['versions']['title'], $cf['tables']['versions']),
          $db->cfn($cf['arch']['versions']['excerpt'], $cf['tables']['versions']),
          $db->cfn($cf['arch']['versions']['id_user'], $cf['tables']['versions']),
          'num_translations' => "COUNT(aliases.id)",
          'num_variants' => "COUNT(parents.id)",
          'num_aliases' => "COUNT(aliases.id)",
          'num_parents' => "COUNT(parents.id)",
          'num_replies' => "COUNT(replies.id)",
          'creation' => 'first_version.' . $cf['arch']['versions']['creation'],
          'last_edit' => $db->cfn($cf['arch']['versions']['creation'], $cf['tables']['versions']),
          'last_reply' => 'IFNULL(MAX(replies_versions.' . $cf['arch']['versions']['creation'] . '), ' . $db->cfn($cf['arch']['versions']['creation'], $cf['tables']['versions']) . ')',
          'option_name' => $db->cfn($cfo['arch']['options']['text'], $cfo['table']),
          'users' => 'GROUP_CONCAT(DISTINCT LOWER(HEX(' . $db->cfn($cf['arch']['versions']['id_user'], $cf['tables']['versions']) . ')) SEPARATOR ",")'
        ],
        'join' => [[
          'table' => $cf['tables']['versions'],
          'on' => [
            'logic' => 'AND',
            'conditions' => [[
              'field' => $db->cfn($cf['arch']['versions']['id_note'], $cf['tables']['versions']),
              'operator' => '=',
              'exp' => $db->cfn($this->fields['id'], $cf['table'])
            ], [
              'field' => $db->cfn($cf['arch']['versions']['latest'], $cf['tables']['versions']),
              'operator' => '=',
              'value' => 1
            ]],
          ],
        ], [
          'table' => $cf['tables']['versions'],
          'alias' => 'first_version',
          'on' => [
            'logic' => 'AND',
            'conditions' => [[
              'field' => 'first_version.' . $cf['arch']['versions']['id_note'],
              'operator' => '=',
              'exp' => $db->cfn($cf['arch']['notes']['id'], $cf['table']),
            ], [
              'field' => 'first_version.' . $cf['arch']['versions']['version'],
              'operator' => '=',
              'value' => 1,
          ]],
        ],
      ], [
        'table' => $cf['tables']['notes'],
        'alias' => 'parents',
        'type'  => 'left',
        'on' => [
          'conditions' => [[
            'field' => $db->cfn($cf['arch']['notes']['id'], $cf['table']),
            'exp' => 'parents.' . $cf['arch']['notes']['id_parent'],
          ]],
        ],
      ], [
        'table' => $cf['tables']['notes'],
        'alias' => 'aliases',
        'type'  => 'left',
        'on' => [
          'conditions' => [[
            'field' => $db->cfn($cf['arch']['notes']['id'], $cf['table']),
            'exp' => 'aliases.' . $cf['arch']['notes']['id_alias'],
          ]],
        ],
      ], [
        'table' => $cf['tables']['notes'],
        'alias' => 'replies',
        'type' => 'left',
        'on' => [
          'logic' => 'AND',
          'conditions' => [[
            'field' => 'replies.' . $cf['arch']['notes']['id_alias'],
            'exp' => $db->cfn($cf['arch']['notes']['id'], $cf['table']),
          ], [
            'field' => 'replies.' . $cf['arch']['notes']['active'],
            'value' => 1
          ]]
        ]
      ], [
        'table' => $cf['tables']['versions'],
        'alias' => 'replies_versions',
        'type' => 'left',
        'on' => [
          'logic' => 'AND',
          'conditions' => [[
            'field' => 'replies_versions.' . $cf['arch']['versions']['id_note'],
            'operator' => '=',
            'exp' => 'replies.' . $cf['arch']['notes']['id']
          ]]
        ]
      ], [
        'table' => $cfo['tables']['options'],
        'type' => 'left',
        'on' => [
          'logic' => 'AND',
          'conditions' => [[
            'field' => $db->cfn($cf['arch']['notes']['id_option'], $cf['table']),
            'operator' => '=',
            'exp' => $db->cfn($cfo['arch']['options']['id'], $cfo['tables']['options'], true),
          ]],
        ],
      ]],
      'where' => [
        'logic' => 'AND',
        'conditions' => []
      ],
      'group_by' => $db->cfn($cf['arch']['notes']['id'], $cf['table'])
    ];
    if ($with_content) {
      $grid_cfg['fields']['content'] = $db->cfn($cf['arch']['versions']['content'], $cf['tables']['versions']);
    }

    return $grid_cfg;
  }


  /**
   * @param array $cfg
   * @param bool $with_content
   * @return array|null
   * @throws Exception
   */
  public function browse(array $cfg, bool $with_content = false, bool $private = false, string $id_type = null, bool $pinned = null): ?array
  {
    if (isset($cfg['limit']) && $this->userId) {
      /** @var Db $db */
      $db       = &$this->db;
      $cf       = &$this->class_cfg;
      $grid_cfg = $this->getLastVersionCfg($with_content);
      unset($grid_cfg['where']);
      $grid_cfg['filters'] = [[
          'field' => $db->cfn($cf['arch']['notes']['active'], $cf['table']),
          'value' => 1,
        ]];

      if ($private) {
        $grid_cfg['filters'][] = [
          'field' => $db->cfn($cf['arch']['notes']['private'], $cf['table']),
          'value' => 1
        ];
        $grid_cfg['filters'][] = [
          'field' => $db->cfn($cf['arch']['notes']['creator'], $cf['table']),
          'value' => $this->userId
        ];
      }
      else {
        $grid_cfg['filters'][] = [
          'field' => $db->cfn($cf['arch']['notes']['private'], $cf['table']),
          'value' => 0
        ];
      }
      if ($id_type) {
        $grid_cfg['filters'][] = [
          'field' => $db->cfn($cf['arch']['notes']['id_type'], $cf['table']),
          'value' => $id_type
        ];
      }
      if (!is_null($pinned)) {
        $grid_cfg['filters'][] = [
          'field' => $db->cfn($cf['arch']['notes']['pinned'], $cf['table']),
          'value' => $pinned
        ];
      }
      if (!empty($cfg['fields'])) {
        $grid_cfg['fields'] = X::mergeArrays($grid_cfg['fields'], $cfg['fields']);
        unset($cfg['fields']);
      }

      if (!empty($cfg['join'])) {
        $grid_cfg['join'] = X::mergeArrays($grid_cfg['join'], $cfg['join']);
        unset($cfg['join']);
      }

      
      $grid = new Grid($this->db, $cfg, $grid_cfg);

      return $grid->getDatatable();
    }
  }


  /**
   * @return false|mixed
   */
  public function count()
  {
    if ($this->userId) {
      $cf  = &$this->class_cfg;
      $db  = &$this->db;
      return $this->db->count([
        'tables' => $cf['table'],
        'join' => [[
          'table' => $cf['tables']['versions'],
          'on' => [
            'conditions' => [[
              'field' => $db->cfn($cf['arch']['versions']['id_note'], $cf['tables']['versions']),
              'exp' => $db->cfn($cf['arch']['notes']['id'], $cf['table'])
            ]],
          ],
        ]],
        'where' => [
          'logic' => 'AND',
          'conditions' => [[
            'field' => 'latest',
            'value' => 1
          ], [
            'logic' => 'OR',
            'conditions' => [[
              'field' => $db->cfn($cf['arch']['notes']['creator'], $cf['table']),
              'value' => $this->userId
            ], [
              'field' => $db->cfn($cf['arch']['versions']['id_user'], $cf['tables']['versions']),
              'value' => $this->userId
            ]]
          ]]
        ]
      ]);
    }

    return null;
  }


  /**
   * Returns an array of IDs of the notes which are aliases of the given ID.
   *
   * @param string $id_note
   * @return array
   */
  public function getAliases(string $id_note): array
  {
    if (!$this->dbTraitExists($id_note)) {
      throw new Exception(_("Impossible to retrieve the note"));
    }

    $cf = &$this->class_cfg;
    return $this->db->getColumnValues($cf['table'], $cf['arch']['notes']['id'], [
      $cf['arch']['notes']['id_alias'] => $id_note
    ]);
  }


  /**
   * Returns an array of IDs of the notes which are children of the given ID.
   *
   * @param string $id_note
   * @return array
   */
  public function getChildren(string $id_note): array
  {
    if (!$this->dbTraitExists($id_note)) {
      throw new Exception(_("Impossible to retrieve the note"));
    }

    $cf = &$this->class_cfg;
    return $this->db->getColumnValues($cf['table'], $cf['arch']['notes']['id'], [
      $cf['arch']['notes']['id_parent'] => $id_note
    ]);
  }


  /**
   * @param string $id   The note's uid
   * @param bool   $keep Set it to true if you want change active property to 0 instead of delete the row from db
   *
   * @return false|null|int
   */
  public function remove(string $id, $keep = false)
  {
    if (Str::isUid($id)) {
      $cf = &$this->class_cfg;
      if (empty($keep)) {
        $this->removeAllMedias($id);
        $this->removeTags($id);
        foreach ($this->getAliases($id) as $id_alias) {
          $this->remove($id_alias);
        }
  
        foreach ($this->getChildren($id) as $id_child) {
          $this->remove($id_child);
        }

        $this->db->delete($cf['tables']['versions'], [$cf['arch']['versions']['id_note'] => $id]);
        return $this->db->delete($cf['table'], [$cf['arch']['notes']['id'] => $id]);
      }
      else {
        return $this->db->update($cf['table'], [$cf['arch']['notes']['active'] => 0], [$cf['arch']['notes']['id'] => $id]);
      }
    }

    return false;
  }


  /**
   * @param string $id
   * @param int|null $version
   * @param bool|null $private
   * @return string|null
   */
  public function copy(string $id, int $version = null, bool $private = null): ?string
  {
    if ($note = $this->getFull($id, $version)) {
      if ($private === null) {
        $private = $note['private'];
      }

      $id_note = $this->insert($note['title'], $note['content'], $note['type'], $private);
      foreach ($note['medias'] as $m) {
        $this->addMediaToNote($m['id'], $id, $note['version']);
      }

      return $id_note;
    }

    return null;
  }


  /**
   * Selects from db all medias that have the property content not null and a correspondent existing file.
   *
   * @param int $start
   * @param int $limit
   * @return array
   */
  public function getMediasNotes(int $start = 0, int $limit): array
  {
    $res = [];
    $cf  = &$this->class_cfg;
    $all = $this->db->rselectAll(
      [
        'table' => $cf['tables']['medias'],
        'fields' => $cf['arch']['medias'],
        'where' => [
          'conditions' => [[
            'field' => $cf['arch']['medias']['private'],
            'value' => 0,
          ], [
            'field' => $cf['arch']['medias']['content'],
            'operator' => 'isnotnull',
          ]],
        ],
        'start' => $start,
        'limit' => $limit,
      ]
    );
    if (!empty($all)) {
      $root = Mvc::getDataPath('appui-note') . 'media/';
      foreach ($all as $i => $a) {
        if (Str::isJson($a['content']) && ($media_obj = $this->getMediaInstance())) {
          $content   = json_decode($a['content'], true);
          $path      = $root . $content['path'] . '/';
          $full_path = $path . $a['id'] . '/' . $a['name'];
          if (file_exists($full_path)) {
            $all[$i]['notes'] = $this->getMediaNotes($a['id']);
            //if the media is an image it takes the thumb 60, 60 for src
            if ($media_obj->isImage($full_path) && ($thumb = $media_obj->getThumbs($full_path))) {
              $all[$i]['is_image'] = true;
            }

            $res[] = $all[$i];
          }
        }
      }
    }

    return $res;
  }


  /**
   * returns all the notes linked to the media.
   *
   * @param string $id_media
   * @return array
   */
  public function getMediaNotes(string $id_media)
  {
    $notes = [];
    $cms   = new Cms($this->db);
    $ids   = $this->db->rselectAll(
      $this->class_cfg['tables']['notes_medias']
      [
        $this->class_cfg['arch']['notes_medias']['id_note']
      ],
      [
        $this->class_cfg['arch']['notes_medias']['id_media'] => $id_media,
      ]
    );

    if (!empty($ids)) {
      foreach ($ids as $i) {
        $tmp                 = $this->get($i['id_note']);
        $tmp['is_published'] = $cms->isPublished($i['id_note']);
        $notes[]             = $tmp;
        //return $notes;
      }
    }

    return $notes;
  }

  /**
   * Returns event id for the given note.
   *
   * @param string $id_note
   * @return false|mixed
   */
  public function getEventIdFromNote(string $id_note)
  {
    return $this->db->selectOne(
      $this->class_cfg['tables']['notes_events'],
      $this->class_cfg['arch']['notes_events']['id_event'],
      [
        $this->class_cfg['arch']['notes_events']['id_note'] => $id_note
      ]
    );
  }

  /**
   * Returns note id for the given event.
   *
   * @param string $id_event
   * @return false|mixed
   */
  public function getNoteIdFromEvent(string $id_event)
  {
    return $this->db->selectOne(
      $this->class_cfg['tables']['notes_events'],
      $this->class_cfg['arch']['notes_events']['id_note'],
      [
        $this->class_cfg['arch']['notes_events']['id_event'] => $id_event
      ]
    );
  }


  /**
   * Removes the row corresponding to the given arguments from bbn_notes_events.
   *
   * @param string $id_note
   * @param string $id_event
   * @return bool
   */
  public function removeNoteEvent(string $id_note, string $id_event): bool
  {
    return $this->_remove_note_event($id_note, $id_event);
  }


  public function savePostIt(array $cfg): ?array
  {
    if (empty($cfg['text'])) {
      throw new Exception(X::_("Impossible to create an empty post-it"));
    }

    if (!X::hasProps($cfg, ['bcolor', 'fcolor'], true)) {
      throw new Exception(X::_("Impossible to create a post-it without setting a color"));
    }

    $id_postIt = self::getOptionId('postit', 'types');
    if (!$id_postIt) {
      throw new Exception(X::_("Impossible to find the post-it option"));
    }

    if (empty($cfg['id'])) {
      if ($id_note = $this->insert([
        'title' => $cfg['title'] ?? '',
        'content' => json_encode([
          'text' => Str::sanitizeHtml($cfg['text']),
          'bcolor' => $cfg['bcolor'],
          'fcolor' => $cfg['fcolor']
        ]),
        'id_type' => $id_postIt,
        'pinned'  => $cfg['pinned'] ?? 0,
        'private' => 1,
        'excerpt' => Str::html2text($cfg['text']),
        'mime' => 'json/bbn-postit'
      ])) {
        return $this->getPostIt($id_note);
      }
    }
    elseif (
      ($postit = $this->getPostIt($cfg['id'])) && 
      $this->update(
        $cfg['id'],
        $cfg['title'] ?? '',
        json_encode([
          'text' => Str::sanitizeHtml($cfg['text']),
          'bcolor' => $cfg['bcolor'],
          'fcolor' => $cfg['fcolor'],
        ]),
        1,
        $postit['locked'],
        Str::html2text($cfg['text']),
        $cfg['pinned']
      )
    ) {
      return $this->getpostIt($cfg['id']);
    }

    return null;
  }

  public function getPostIt(string $id): ?array
  {
    if ($note = $this->get($id)) {
      if (!Str::isJson($note['content'])) {
        throw new Exception(X::_("The content of the post-it should be of type JSON"));
      }

      $cfg =json_decode($note['content'], true);
      $note = array_merge($note, $cfg);
      unset($note['content']);
      return $note;
    }

    return null;
  }


  public function getPostIts(int $limit = 25, int $start = 0, $only_pinned = false): ?array
  {
    $id_postIt = self::getOptionId('postit', 'types');
    $res = $this->browse(['limit' => $limit, 'start' => $start], true, true, $id_postIt, $only_pinned ?: null);
    if ( $res ){
      return array_map(function($a) {
        if (Str::isJson($a['content'])) {
          return array_merge($a, json_decode($a['content'], true));
        }

        unset($a['content']);
        return $a;
      }, $res['data']);
    }

    return null;
  }

  /**
   * If the row corresponding to the given arguments is not in the table bbn_notes_events it inserts the row.
   *
   * @param string $id_note
   * @param string $id_event
   * @return bool
   */
  public function insertNoteEvent(string $id_note, string $id_event): bool
  {
    return $this->_insert_note_event($id_note, $id_event);
  }


  /**
   * Creates a new element for the given feature (= id_option)
   *
   * @param string $id_option
   * @param string $id_note
   * @param string|null $id_media
   * @param integer|null $num
   * @param array|null $cfg
   * @return string|null
   */
  public function addFeature(string $id_option, string $id_note, string $id_media = null, int $num = null, array $cfg = null): ?array
  {
    $id_option = $this->getFeatureOption($id_option);
    $dbCfg     = $this->getClassCfg();
    $table     = $dbCfg['tables']['features'];
    $cols      = $dbCfg['arch']['features'];
    if ($num === 0) {
      $num = ((int)$this->db->selectOne($table, 'MAX(num)', [$cols['id_option'] => $id_option])) + 1;
    }
   	if ($id = $this->db->selectOne($table, $cols['id'], [
      $cols['id_option'] => $id_option,
      $cols['id_note'] => $id_note
    ])) {
      $this->db->delete($table, [
        $cols['id'] => $id
      ]);
    }

    $media = null;
    $data = [
      $cols['id_option'] => $id_option,
      $cols['id_note'] => $id_note,
      $cols['id_media'] => $id_media,
      $cols['num'] => $num,
      $cols['cfg'] => $cfg ? json_encode($cfg) : null
    ];

    if (empty($id_media)) {
      if ($medias = $this->getMedias($id_note)) {
        $media    = $medias[0];
        $id_media = $media['id'];
      }
    }
    else {
      $media = $this->getMedia($id_media);
    }

    if ($media && !$data[$cols['id_media']]) {
      $data[$cols['id_media']] = $media['id'];
    }

    if ($this->db->insert($table, $data)) {
      $data['id'] = $this->db->lastId();
      return [
        'data'  => $data,
        'media' => $media
      ];
    }

    return null;
  }


  /**
   * Gets a full feature element
   *
   * @param string $id
   * @param bool $full
   * @return array|null
   */
  public function getFeature(string $id, bool $full = true): ?array
  {
    $dbCfg  = $this->getClassCfg();
    $table  = $dbCfg['tables']['features'];
    $cols   = $dbCfg['arch']['features'];
    if ($res = $this->db->rselect($table, $cols, [$cols['id'] => $id])) {
      if (!empty($res['cfg'])) {
        $res['cfg'] = json_decode($res['cfg'], true);
      }

      $res['title'] = $this->getTitle($res['id_note']);
      $res['url']   = $this->getUrl($res['id_note']);
      if ($res['id_media']) {
        $media = $this->getMediaInstance();
        $res['media'] = $media->getMedia($res['id_media'], true);
      }

      if ($full) {
        $cms = new Cms($this->db);
        $res = X::mergeArrays($cms->get($res['id_note'], false, false), $res);
      }

      return $res;
    }

    return null;
  }


  /**
   * Removes an element from a feature
   *
   * @param string $id
   * @return integer
   */
  public function removeFeature(string $id): int
  {
    $dbCfg = $this->getClassCfg();
    $table = $dbCfg['tables']['features'];
    $cols  = $dbCfg['arch']['features'];
    $res = 0;
    if ($feat = $this->getFeature($id)) {
      $res = $this->db->delete($table, [$cols['id'] => $id]);
      $this->fixFeatureOrder($feat['id_option']);
    }

    return $res;
  }


  /**
   * Changes the media for the given geature element
   *
   * @param string $id
   * @param string|null $id_media
   * @return integer
   */
  public function setFeatureMedia(string $id, string $id_media = null): int
  {
    $dbCfg = $this->getClassCfg();
    $table = $dbCfg['tables']['features'];
    $cols  = $dbCfg['arch']['features'];
    return $this->db->update($table, [
      $cols['id_media'] => $id_media
    ], [
      $cols['id'] => $id
    ]);
  }


  /**
   * Changes the order number (= num) for the given feature element
   *
   * @param string $id
   * @param integer $num
   * @return integer
   */
  public function setFeatureOrder(string $id, int $num): int
  {
    $dbCfg = $this->getClassCfg();
    $table = $dbCfg['tables']['features'];
    $cols  = $dbCfg['arch']['features'];
    $res = 0;
    if ($feat = $this->getFeature($id)) {
      if ($feat['num'] > $num) {
        $res = $this->db->update($table, [
          'num' => [null, '`num` + 1']
        ], [
          'id_option' => $feat['id_option'],
          ['id', '!=', $id],
          ['num', '>=', $num],
          ['num', '<', $feat['num']]
        ]);
      }
      elseif ($feat['num'] < $num) {
        $res = $this->db->update($table, [
          'num' => [null, '`num` - 1']
        ], [
          'id_option' => $feat['id_option'],
          ['id', '!=', $id],
          ['num', '>', $feat['num']],
          ['num', '<=', $num]
        ]);
      }
      $this->log($this->db->last(), $this->db->getLastValues());
      $this->log($res);
      
      $res = $this->db->update($table, [$cols['num'] => $num], [$cols['id'] => $id]);
      /*
      if ($res) {
        $this->fixFeatureOrder($feat['id_option']);
      }
      */
    }

    return $res;
  }


  /**
   * Removes all the order numbers from the given feature elements
   *
   * @param string $id_option
   * @return integer
   */
  public function unsetFeatureOrder(string $id_option): int
  {
    $id_option = $this->getFeatureOption($id_option);
    $dbCfg     = $this->getClassCfg();
    $table     = $dbCfg['tables']['features'];
    $cols      = $dbCfg['arch']['features'];
    return $this->db->update($table, [
      $cols['num'] => null
    ], [
      $cols['id_option'] => $id_option
    ]);
  }


  /**
   * Fix the order for all the elements of the given feature
   *
   * @param string $id_option
   * @return boolean
   */
  public function fixFeatureOrder(string $id_option): bool
  {
    $id_option = $this->getFeatureOption($id_option);
    $option    = $this->getOption($id_option);
    $dbCfg     = $this->getClassCfg();
    $table     = $dbCfg['tables']['features'];
    $cols      = $dbCfg['arch']['features'];
    $res       = 0;
    $is_null   = ($option['orderMode'] ?? '') !== 'manual';
    foreach ($this->getFeatureList($id_option) as $i => $d) {
      if ($is_null) {
        if (!empty($d['num'])) {
          $res += (int)$this->db->update($table, [$cols['num'] => null], [$cols['id'] => $d['id']]);
        }
      }
      else {
        if ($d['num'] !== ($i + 1)) {
          $res += (int)$this->db->update($table, [$cols['num'] => $i + 1], [$cols['id'] => $d['id']]);
        }
      }
    }

    return (bool)$res;
  }


  /**
   * Returns the given/requested id_option, from the code if it's not a UID
   *
   * @param string $id_option
   * @return string
   */
  public function getFeatureOption(string $id_option): string
  {
    if (!Str::isUid($id_option)) {
      $id_option = $this->getOptionId($id_option, 'features');
      if (!$id_option) {
        throw new Exception(X::_("Impossible to determine the feature %s", $id_option));
      }
    }

    return $id_option;
  }


  /**
   * Returns a list of the elements for the given feature, with only id and num
   *
   * @param string $id_option
   * @return array
   */
  public function getFeatureList(string $id_option): array
  {
    $id_option = $this->getFeatureOption($id_option);
    $dbCfg = $this->getClassCfg();
    $table = $dbCfg['tables']['features'];
    $cols  = $dbCfg['arch']['features'];
    return $this->db->rselectAll($table, [$cols['id'], $cols['num']], [$cols['id_option'] => $id_option]) ?: [];
  }


  /**
   * Changes the config of a feature element
   *
   * @param string $id
   * @param array|null $cfg
   * @return integer
   */
  public function setFeatureCfg(string $id, array $cfg = null): int
  {
    $dbCfg = $this->getClassCfg();
    $table = $dbCfg['tables']['features'];
    $cols  = $dbCfg['arch']['features'];
    return $this->db->update($table, [
      $cols['cfg'] => $cfg ? json_encode($cfg) : null
    ], [
      $cols['id'] => $id
    ]);
  }


  /**
   * Gets all the elements, and their details, for the given feature
   *
   * @param string $id_option
   * @param bool $full
   * @return array
   */
  public function getFeatures(string $id_option, bool $full = true): array
  {
    $res = [];
    foreach ($this->getFeatureList($id_option) as $d) {
      $res[] = $this->getFeature($d['id'], $full);
    }

    $option = $this->getOption($id_option);
    $mode = $option['orderMode'] ?? 'random';
    switch ($mode) {
      case "random":
        shuffle($res);
        break;
      case "latest":
        X::sortBy($res, 'start', 'desc');
        break;
      case "first":
        X::sortBy($res, 'start', 'asc');
        break;
      case "manual":
        X::sortBy($res, 'num', 'asc');
        break;
    }
    return $res;
  }

  /**
   * Pins the given note
   * @param string $id The note ID
   * @return bool
   */
  public function pin(string $id): bool
  {
    return (bool)$this->db->update($this->class_table, [$this->fields['pinned'] => 1], [$this->fields['id'] => $id]);
  }

  /**
   * Unpins the given note
   * @param string $id The note ID
   * @return bool
   */
  public function unpin(string $id): bool
  {
    return (bool)$this->db->update($this->class_table, [$this->fields['pinned'] => 0], [$this->fields['id'] => $id]);
  }


  /**
   * Set the given note as important
   * @param string $id The note ID
   * @return bool
   */
  public function setImportant(string $id): bool
  {
    return (bool)$this->db->update($this->class_table, [$this->fields['important'] => 1], [$this->fields['id'] => $id]);
  }


  /**
   * Unset the given note as important
   * @param string $id The note ID
   * @return bool
   */
  public function unsetImportant(string $id): bool
  {
    return (bool)$this->db->update($this->class_table, [$this->fields['important'] => 0], [$this->fields['id'] => $id]);
  }


  /**
   * If a date is given for $end checks if it's after the start date.
   *
   * @param string|null $start
   * @param string|null $end
   * @return bool
   */
  private function _check_date(?string $start, ?string $end): bool
  {
    if (isset($start)) {
      if (!isset($end) || (($end = strtotime($end)) && ($start = strtotime($start)) && $end > $start)) {
        return true;
      }
    } else {
      return true;
    }
    return false;
  }


  /**
   * Removes the row corresponding to the given arguments from bbn_notes_events.
   *
   * @param string $id_note
   * @param string $id_event
   * @return bool
   */
  private function _remove_note_event(string $id_note, string $id_event): bool
  {
    return (bool)$this->db->delete(
      $this->class_cfg['tables']['notes_events'],
      [
        $this->class_cfg['arch']['notes_events']['id_event'] => $id_event,
        $this->class_cfg['arch']['notes_events']['id_note'] => $id_note,
      ]
    );
  }


  /**
   * If the row corresponding to the given arguments is not in the table bbn_notes_events it inserts the row.
   *
   * @param string $id_note
   * @param string $id_event
   * @return bool
   */
  private function _insert_note_event(string $id_note, string $id_event): bool
  {
    if (!$this->db->count(
      $this->class_cfg['tables']['notes_events'],
      [
        $this->class_cfg['arch']['notes_events']['id_note'] => $id_note,
        $this->class_cfg['arch']['notes_events']['id_event'] => $id_event
      ]
    )) {
      return (bool)$this->db->insert(
        $this->class_cfg['tables']['notes_events'],
        [
          $this->class_cfg['arch']['notes_events']['id_note'] => $id_note,
          $this->class_cfg['arch']['notes_events']['id_event'] => $id_event,
        ]
      );
    }

    return false;
  }
}
