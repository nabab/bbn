<?php

/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 14/04/2016
 * Time: 20:38.
 */

namespace bbn\Appui;

use bbn;
use bbn\Str;
use bbn\X;
use Exception;

if (!\defined('BBN_DATA_PATH')) {
  die('The constant BBN_DATA_PATH must be defined in order to use Note');
}

class Note extends bbn\Models\Cls\Db
{
  use bbn\Models\Tts\References;
  use bbn\Models\Tts\Optional;
  use bbn\Models\Tts\Dbconfig;
  use bbn\Models\Tts\Url;
  use bbn\Models\Tts\Tagger;

  private $medias;

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
      'notes_medias' => [
        'id' => 'id',
        'id_media' => 'id_media',
        'id_note' => 'id_note',
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
   * @param bbn\Db $db
   * @throws \Exception
   */
  public function __construct(bbn\Db $db, string $lang = null)
  {
    parent::__construct($db);
    $this->_init_class_cfg(self::$default_class_cfg);
    self::optionalInit();
    $this->defaultUrlType = 'note';
    $this->taggerInit(
      $this->class_cfg['tables']['notes_tags'],
      [
        'id_tag' => $this->class_cfg['arch']['notes_tags']['id_tag'],
        'id_element' => $this->class_cfg['arch']['notes_tags']['id_note']
      ]
    );
    $this->lang = $lang ?: (defined('BBN_LANG') ? BBN_LANG : 'en');
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
    bool $private = false,
    bool $locked = false,
    string $id_parent = null,
    string $id_alias = null,
    string $mime = '',
    string $lang = '',
    string $id_option = null,
    string $excerpt = ''
  ) {
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
      'excerpt'
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

    $id_note = false;

    if (($usr = bbn\User::getInstance())
      && $this->db->insert(
        $cf['table'],
        [
          $cf['arch']['notes']['id_parent'] => $cfg['id_parent'],
          $cf['arch']['notes']['id_alias'] => $cfg['id_alias'],
          $cf['arch']['notes']['id_type'] => $cfg['id_type'],
          $cf['arch']['notes']['id_option'] => $cfg['id_option'],
          $cf['arch']['notes']['private'] => !empty($cfg['private']) ? 1 : 0,
          $cf['arch']['notes']['locked'] => !empty($cfg['locked']) ? 1 : 0,
          $cf['arch']['notes']['creator'] => $usr->getId(),
          $cf['arch']['notes']['mime'] => $cfg['mime'],
          $cf['arch']['notes']['lang'] => $cfg['lang']
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
        && ($usr = bbn\User::getInstance())
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
            $cf['arch']['versions']['id_user'] => $usr->getId(),
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
  public function update(string $id, string $title, string $content, bool $private = null, bool $locked = null): ?int
  {
    $ok = null;
    if ($old = $this->db->rselect('bbn_notes', [], ['id' => $id])) {
      $ok  = 0;
      $new = [];
      if (!\is_null($private) && ($private != $old['private'])) {
        $new['private'] = $private;
      }

      if (!\is_null($locked) && ($locked != $old['locked'])) {
        $new['locked'] = $locked;
      }

      if (!empty($new)) {
        $ok = $this->db->update('bbn_notes', $new, ['id' => $id]);
      }

      if ($old_v = $this->get($id)) {
        $changed = false;
        $new_v   = [
          'title' => $old_v['title'],
          'content' => $old_v['content'],
        ];

        if ($title !== $old_v['title']) {
          $changed        = true;
          $new_v['title'] = $title;
        }

        if ($content !== $old_v['content']) {
          $changed          = true;
          $new_v['content'] = $content;
        }

        if (!empty($changed)) {
          $ok = $this->insertVersion($id, $new_v['title'], $new_v['content']);
        }
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
    if (!$this->exists($id_note)) {
      throw new \Exception(
        X::_(
          "Impossible to retrieve the note with ID %s",
          Str::isUid($id_note) ? $id_note : '[String (' . strlen($id_note) . ')]'
        )
        );
    }

    return $this->setUrl($id_note, $url);
  }



  /**
   * Returns the configuration to have the last version for each note
   *
   * @param boolean $with_content
   * @return array
   */
  public function getLastVersionCfg($with_content = false): array
  {
    $cf  = &$this->class_cfg;
    $res = [
      'tables' => [$cf['table']],
      'fields' => [
        'versions.' . $cf['arch']['versions']['id_note'],
        $cf['arch']['notes']['id_type'],
        $cf['arch']['notes']['id_option'],
        'versions.' . $cf['arch']['versions']['version'],
        'versions.' . $cf['arch']['versions']['excerpt'],
        'versions.' . $cf['arch']['versions']['title'],
        'versions.' . $cf['arch']['versions']['id_user'],
        'versions.' . $cf['arch']['versions']['creation'],
        'num_translations' => "COUNT(aliases.id)",
        'num_variants' => "COUNT(parents.id)",
        'versions.' . $cf['arch']['versions']['content']
      ],
      'join' => [[
        'table' => $cf['tables']['versions'],
        'alias' => 'versions',
        'on' => [
          'conditions' => [[
            'field' => $this->db->cfn($cf['arch']['notes']['id'], $cf['table']),
            'exp' => 'versions.' . $cf['arch']['versions']['id_note'],
          ], [
            'field' => 'versions.' . $cf['arch']['versions']['latest'],
            'value' => 1
          ]],
        ],
      ], [
        'table' => $cf['tables']['notes'],
        'alias' => 'parents',
        'type'  => 'left',
        'on' => [
          'conditions' => [[
            'field' => $this->db->cfn($cf['arch']['notes']['id'], $cf['table']),
            'exp' => 'parents.' . $cf['arch']['notes']['id_parent'],
          ]],
        ],
      ], [
        'table' => $cf['tables']['notes'],
        'alias' => 'aliases',
        'type'  => 'left',
        'on' => [
          'conditions' => [[
            'field' => $this->db->cfn($cf['arch']['notes']['id'], $cf['table']),
            'exp' => 'aliases.' . $cf['arch']['notes']['id_alias'],
          ]],
        ],
      ]],
      'where' => [
        'logic' => 'AND',
        'conditions' => [
        ]
      ],
      'group_by' => $this->db->cfn($cf['arch']['notes']['id'], $cf['table'])
    ];

    if (!$with_content) {
      array_pop($res['fields']);
    }

    return $res;
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
   * @throws \Exception
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
      $this->exists($id_note)
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
    if ($usr = bbn\User::getInstance()) {
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
          $cf['arch']['notes_medias']['id_media'] => $id_media,
          $cf['arch']['notes_medias']['id_user'] => $usr->getId(),
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
   * @throws \Exception
   */
  public function removeMedia(string $id_media, string $id_note): ?int
  {
    $cf = &$this->class_cfg;
    if (
      $this->db->selectOne($cf['tables']['medias'], $cf['arch']['medias']['id'], [$cf['arch']['medias']['id'] => $id_media])
      && $this->exists($id_note)
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
   * @param string $id_media
   * @param string $id_note
   * @param false $version
   * @return int|null
   * @throws \Exception
   */
  public function removeAllMedias(string $id_note): ?int
  {
    $cf = &$this->class_cfg;
    if (
      $this->db->selectOne($cf['tables']['medias'], $cf['arch']['medias']['id'], [$cf['arch']['medias']['id'] => $id_media])
      && $this->exists($id_note)
    ) {

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
   * @throws \Exception
   */
  public function getMedias(string $id_note, $version = false, $type = false): array
  {
    $ret   = [];
    $media = $this->getMediaInstance();
    $cf    = &$this->class_cfg;
    if ($this->exists($id_note)) {
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
   * @throws \Exception
   */
  public function hasMedias(string $id_note, $version = false, string $id_media = ''): ?bool
  {
    $cf = &$this->class_cfg;
    if ($this->exists($id_note)) {
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
   * @param array $cfg
   * @param bool $with_content
   * @return array|null
   * @throws \Exception
   */
  public function browse(array $cfg, bool $with_content = false): ?array
  {
    if (isset($cfg['limit']) && ($user = bbn\User::getInstance())) {
      /** @var bbn\Db $db */
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
          $db->cfn($this->fields['creator'], $cf['table']),
          $db->cfn($this->fields['active'], $cf['table']),
          $db->cfn($cf['arch']['versions']['version'], $cf['tables']['versions']),
          $db->cfn($cf['arch']['versions']['title'], $cf['tables']['versions']),
          $db->cfn($cf['arch']['versions']['excerpt'], $cf['tables']['versions']),
          $db->cfn($cf['arch']['versions']['id_user'], $cf['tables']['versions']),
          'creation' => 'first_version.' . $cf['arch']['versions']['creation'],
          'last_edit' => $db->cfn($cf['arch']['versions']['creation'], $cf['tables']['versions']),
          'option_name' => $db->cfn($cfo['arch']['options']['text'], $cfo['table'])
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
        'filters' => [[
          'field' => $db->cfn($cf['arch']['notes']['active'], $cf['table']),
          'operator' => '=',
          'value' => 1,
        ], [
          'field' => $db->cfn($cf['arch']['notes']['private'], $cf['table']),
          'operator' => '=',
          'value' => 0,
        ]],
        'group_by' => $db->cfn($cf['arch']['notes']['id'], $cf['table'])
      ];
      if (!empty($cfg['fields'])) {
        $grid_cfg['fields'] = bbn\X::mergeArrays($grid_cfg['fields'], $cfg['fields']);
        unset($cfg['fields']);
      }

      if (!empty($cfg['join'])) {
        $grid_cfg['join'] = bbn\X::mergeArrays($grid_cfg['join'], $cfg['join']);
        unset($cfg['join']);
      }

      if ($with_content) {
        $grid_cfg['fields']['content'] = 'last_version.'.$cf['arch']['versions']['content'];
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
    if ($user = bbn\User::getInstance()) {
      $cf  = &$this->class_cfg;
      $db  = &$this->db;
      $sql = "
      SELECT COUNT(DISTINCT {$db->cfn($cf['arch']['notes']['id'],$cf['tables']['notes'], 1)})
      FROM {$db->tsn($cf['tables']['notes'], 1)}
        JOIN {$db->tsn($cf['tables']['versions'], 1)}
          ON {$db->cfn($cf['arch']['notes']['id'],$cf['tables']['notes'], 1)} = {$db->cfn($cf['arch']['versions']['id_note'],$cf['tables']['versions'], 1)}
      WHERE {$db->cfn($cf['arch']['notes']['creator'],$cf['tables']['notes'], 1)} = ?
      OR {$db->cfn($cf['arch']['versions']['id_user'],$cf['tables']['versions'], 1)} = ?";

      return $db->getOne($sql, $user->getId(), $user->getId());
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
    if (!$this->exists($id_note)) {
      throw new Exception(_("Impossible to retrieve the note"));
    }

    $cf = &$this->class_cfg;
    return $this->getColumnValues($cf['table'], $cf['arch']['notes']['id'], [
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
    if (!$this->exists($id_note)) {
      throw new Exception(_("Impossible to retrieve the note"));
    }

    $cf = &$this->class_cfg;
    return $this->getColumnValues($cf['table'], $cf['arch']['notes']['id'], [
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
      $root = \bbn\Mvc::getDataPath('appui-note') . 'media/';
      foreach ($all as $i => $a) {
        if (bbn\Str::isJson($a['content']) && ($media_obj = $this->getMediaInstance())) {
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
    $cms   = new \bbn\Appui\Cms($this->db);
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
