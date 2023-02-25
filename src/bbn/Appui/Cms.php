<?php

/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 14/04/2016
 * Time: 20:38
 */

 //the notes inserted with appui/notes have to be type 'pages'
namespace bbn\Appui;

use Exception;
use bbn\X;
use bbn\Str;
use bbn\Db;
use bbn\Models\Tts\Cache;
use bbn\Models\Cls\Db as DbCls;


class Cms extends DbCls
{
  use Cache;

  /** @var Note A Note instance. */
  protected $note;

  /** @var Option An Option instance. */
  protected $opt;

  /** @var Event En Event instance. */
  protected $event;

  /** @var Url An Url instance. */
  protected $url;

  /** @var Media A Media instance. */
  protected $media;

  /** @var array $class_cfg */
  protected $class_cfg;

  /** @var string The option's ID of the type of notes for CMS (pages) */
  protected $noteType;

  private static $_id_event;

    /**
     *
     * @param string|null $id
     * @return void
     */
  private static function _set_id_event($id)
  {
      self::$_id_event = $id;
  }

  /**
   * If a date is given for $end checks if it's after the start date.
   *
   * @param string|null $start
   * @param null|string $end
   * @return Boolean
   */
  private function _check_date(?string $start, ?string $end): bool
  {
    if (!isset($start)) {
      return false;
    }

    $start = strtotime($start);
    if (!$start) {
      throw new Exception(X::_("The end date is not valid"));
    }

    if (empty($end)) {
      return true;
    }

    $end = strtotime($end);
    if (!$end) {
      throw new Exception(X::_("The end date is not valid"));
    }

    if ($end <= $start) {
      //throw new Exception(X::_("The end date is before the start"));
      return false;
    }

    return true;
  }

  /**
   * Cms constructor.
   *
   * @param Db $db
   * @param null $notes
   * @throws Exception
   */
  public function __construct(Db $db, Note $note = null)
  {
    parent::__construct($db);
    $this->cacheInit();
    $this->event = new Event($this->db);
    $this->opt   = Option::getInstance();
    $this->url   = new Url($this->db);
    $this->media = new Medias($this->db);
    if (!self::$_id_event) {
      $id = $this->opt->fromCode('publication', 'types', 'event', 'appui');
      self::_set_id_event($id);
    }
    if (!$note) {
      $this->note = new Note($this->db);
    }
    else {
      $this->note = $note;
    }

    $this->class_cfg = X::mergeArrays(
      $this->note->getClassCfg(),
      $this->url->getClassCfg(),
      $this->event->getClassCfg()
    );
  }


  /**
   * Returns a list of the latest published articles
   *
   * @param array $filter
   * @param integer $limit
   * @param integer $start
   * @return array
   */
  public function getLatest(array $filter = [], int $limit = 20, int $start = 0): array
  {
    $cfg          = $this->getLastVersionCfg(false, true, $filter);
    $cfg['order'] = [['field' => 'bbn_events.start', 'dir' => 'DESC']];
    $cfg['limit'] = $limit;
    $cfg['start'] = $start;

    $db    =& $this->db;
    $idx   = md5(json_encode($filter));
    $total = $this->cacheGetSet(function() use (&$db, $cfg) {
      return $db->count($cfg);
    }, $idx, 'total', 20);

    return [
        'data' => $this->db->rselectAll($cfg),
        'query' => $this->db->last(),
        'total' => $total
    ];
  }


  /**
   * Returns the note from its id, with its URL, start and end date of publication.
   *
   * @param string $id_note
   * @return array
   */
  public function get(string $id_note, bool $with_medias = false, bool $with_content = true): array
  {
    $cacheName = md5(json_encode(func_get_args()));
    if ($res = $this->cacheGet($id_note, $cacheName)) {
      return $res;
    }
    $res = [];
    if (!empty($id_note) && ($note = $this->note->get($id_note))) {
      $res             = $note;
      $res['url']      = $this->note->getUrl($id_note);
      $res['start']    = $this->getStart($id_note);
      $res['end']      = $this->getEnd($id_note);
      $res['tags']     = $this->note->getTags($id_note);
      $res['items']    = $note['content'] ? json_decode($note['content'], true) : [];
      if (($res['id_media'] = $this->getDefaultMedia($id_note)) && !$with_medias) {
        $res['media'] = $this->getMedia($res['id_media']);
      }
      if (!$with_content) {
        unset($res['content']);
      }
      if ($with_medias) {
        $res['medias'] = $this->note->getMedias($id_note);
      }

      if ($res['mime'] === 'json/bbn-cms') {
        foreach ($res['items'] as &$item) {
          if ($item['type'] === 'container') {
            foreach ($item['items'] as &$it) {
              if ($it['type'] === 'slider') {
                if ($it['mode'] === 'features') {
                  $it['currentItems'] = array_map(
                    function($a) {
                      return [
                        'component' => "appui-note-cms-block-slider-slide",
                        'data' => $a
                      ];
                    },
                    $this->note->getFeatures($it['content'])
                  );
                }
              }
            }
          }
          else {
            if ($item['type'] === 'slider') {
              if ($item['mode'] === 'features') {
                $item['currentItems'] = array_map(
                  function($a) {
                    $a = $a['media'];
                    $a['type'] = 'img';
                    return $a;
                  },
                  $this->note->getFeatures($item['content'])
                );
            }
            }
        }
        }

      }

    }

    $this->cacheSet($id_note, $cacheName, $res);

    return $res;
  }


  public function getSEO(string $id_note): array
  {
    $seo = '';
    if ($note = $this->get($id_note, true, true)) {
      $seo = PHP_EOL . '<h2>' . $note['title'] . '</h2>' . PHP_EOL;
      if (!empty($note['items'])) {
        foreach($note['items'] as $it) {
          if (!empty($it['type']) && ($it['type'] === 'container')) {
            foreach ($it['items'] as $it2) {
              $seo .= $this->getBlockString($it2, $note);
            }
          }
          else {
            $seo .= $this->getBlockString($it, $note);
          }
        }

        $seo .= PHP_EOL;
      }
    }

    return [
      'title' => $note['title'],
      'description' => $note['excerpt'],
      'tags' => $note['tags'],
      'seo' => $seo
    ];
  }


  public function getBlockString(array $it, array $note): string
  {
    $seo = '';
    if (!empty($it['type'])) {
      $seo .= '<div>';
      switch ($it['type']) {
        case 'gallery':
        case 'carousel':
          if (!empty($it['content'])) {
            if (is_string($it['content'])) {
              $gallery = $this->media->browseByGroup($it['content'], [], 100);
              if ($gallery && !empty($gallery['data'])) {
                foreach ($gallery['data'] as $d) {
                  $tags = $this->media->getTags($d['id']);
                  $img = $d['path'];
                  if (!empty($d['thumbs'])) {
                    //$img = $this->media->getThumbsName($d['path'], [$d['thumbs'][0]]);
                  }
    
                  $seo .= '<a href="/' . $d['path'] . '">' .
                      '<img src="/' . $img . '" title="'. _("Enlarge") . ' ' . 
                      basename($d['path']).'" alt="' .
                      Str::escapeDquotes(
                        $d['title'] . ' - ' . 
                        (empty($tags) ? '' : X::join($tags, ' | ') . ' | ') .
                        X::join($note['tags'], ' | ') . ' - ' . $note['title']
                      ) . '"></a><br>' . PHP_EOL;
                }
              }
            }
          }

          break;
        case 'slider':
          if (!empty($it['content'])) {
            $features = $this->note->getFeatures($it['content'], false);
            $seo .= '<ul>';
            foreach ($features as $feature) {
              $seo .= '<li><a href="' . $feature['url'] . '">' . PHP_EOL;
              if (!empty($feature['media'])) {
                $seo .= '<img src="' . $feature['media']['path'] . '" alt="' . $feature['media']['title'] . '"><br>' . PHP_EOL;
              }

              $seo .= $feature['title'] . '<a></li>';
            }

            $seo .= '</ul>';
          }

          break;
        case 'title':
          $seo .= '<' . ($it['tag'] ?? 'h2') . '>' . $it['content'] . '</' . ($it['tag'] ?? 'h2') . '>' . PHP_EOL;
          break;
        case 'html':
          $seo .= $it['content'] . PHP_EOL;
          break;
        case 'line':
          $seo .= '<hr>' . PHP_EOL;
          break;
        case 'imagetext':
          $seo .= '<a href="/' . $it['content'] . '"><img src="/' . $it['content'] . '" alt="' .
              Str::escapeDquotes(
                $it['caption'] . ' - ' . 
                (empty($tags) ? '' : X::join($tags, ' | ') . ' | ') .
                X::join($note['tags'], ' | ') . ' - ' . $note['title']
              ) . '"></a><br>' . PHP_EOL .
              (empty($it['details']) ? '' : '<p>' . $it['details'] . '</p>' . PHP_EOL);
          if (!empty($it['details_title'])) {
            $seo .= '<caption>' . $it['details_title'] . '</caption>' . PHP_EOL;
          }
          break;
        case 'image':
          $seo .= '<a href="/' . $it['content'] . '"><img src="/' . $it['content'] . '" alt="' .
              Str::escapeDquotes(
                $it['caption'] . ' - ' . 
                (empty($tags) ? '' : X::join($tags, ' | ') . ' | ') .
                X::join($note['tags'], ' | ') . ' - ' . $note['title']
              ) . '"></a><br>' . PHP_EOL .
              (empty($it['details']) ? '' : '<p>' . $it['details'] . '</p>' . PHP_EOL);
          if (!empty($it['details_title'])) {
            $seo .= '<caption>' . $it['details_title'] . '</caption>' . PHP_EOL;
          }
          break;
        case 'video':
          break;
        default:
          X::log($it, 'unknown_types');
      }

      $seo .= '</div>';
    }

    return $seo;
  }


  /**
   * Sets a media as the default for the given note
   *
   * @param string $id_note
   * @param string $id_media
   * @return boolean
   */
  public function setDefaultMedia(string $id_note, string $id_media): bool
  {
    $cfg = $this->note->getClassCfg();
    if ($this->note->exists($id_note)) {
      if ($old = $this->getDefaultMedia($id_note)) {
        if ($id_media === $old) {
          return true;
        }

        $this->db->update($cfg['tables']['notes_medias'], [
          $cfg['arch']['notes_medias']['default_media'] => 0
        ], [
          $cfg['arch']['notes_medias']['id_note'] => $id_note
        ]);
      }

      if ($res = $this->note->addMediaToNote($id_media, $id_note, 1)) {
        $this->cacheDelete($id_note);
      }

      return $res;
    }

    throw new \Exception(X::_("The note doesn't exist"));
  }


  /**
   * Unsets the media as the default for the given note
   *
   * @param string $id_note
   * @return boolean
   */
  public function unsetDefaultMedia(string $id_note): bool
  {
    $cfg = $this->note->getClassCfg();
    if ($this->note->exists($id_note)) {
      if ($this->db->update($cfg['tables']['notes_medias'], [
        $cfg['arch']['notes_medias']['default_media'] => 0
      ], [
        $cfg['arch']['notes_medias']['id_note'] => $id_note
      ])) {
        $this->cacheDelete($id_note);
        return true;
      }
      return false;
    }

    throw new \Exception(X::_("The note doesn't exist"));
  }


  /**
   * Returns the default media ID for the given note
   *
   * @param [type] $id_note ID of the note
   * @return string|null
   */
  public function getDefaultMedia($id_note): ?string
  {
    $cfg = $this->note->getClassCfg();
    return $this->db->selectOne(
      $cfg['tables']['notes_medias'],
      $cfg['arch']['notes_medias']['id_media'],
      [
        $cfg['arch']['notes_medias']['id_note'] => $id_note,
        $cfg['arch']['notes_medias']['default_media'] => 1
      ]);
  }


  /**
   * Returns a database query configuration getting the latest note version
   *
   * @param boolean $with_content
   * @param boolean $published
   * @param array $filter
   * @return array
   */
  public function getLastVersionCfg(bool $with_content = false, bool $published = true, array $filter = []): array
  {
    $cfg                          = $this->note->getLastVersionCfg($with_content);
    $cfg['fields'][]              = 'url';
    $cfg['fields'][]              = 'start';
    $cfg['fields'][]              = 'end';
    $cfg['fields']['num_medias']  = 'COUNT(' . $this->db->cfn($this->class_cfg['arch']['notes_medias']['id_note'], $this->class_cfg['tables']['notes_medias'], true) . ')';
    $cfg['fields']['id_media']    = 'default_medias.id_media';
    $cfg['where']['conditions'][] = [
      'field' => 'mime',
      'value' => 'json/bbn-cms'
    ];
    $cfg['where']['conditions'][] = [
      'field' => 'private',
      'value' => 0
    ];

    if ($published) {
      $cfg['where']['conditions'][] = [
        'field' => 'bbn_events.start',
        'operator' => '<=',
        'value' => date('Y-m-d H:i:s')
      ];
      $cfg['where']['conditions'][] = [
        'logic' => 'OR',
        'conditions' => [
          [
            'field' => 'bbn_events.end',
            'operator' => '>=',
            'value' => date('Y-m-d H:i:s')
          ], [
            'field' => 'bbn_events.end',
            'operator' => 'isnull',
          ]
        ]
      ];
    }

    if (!empty($filter)) {
      if (!isset($filter['conditions'])) {
        $filter = [
          'conditions' => $filter
        ];
      }

      $cfg['where']['conditions'][] = $filter;
    }

    $cfg['join'][] = [
        'table' => $this->class_cfg['tables']['notes_url'],
        'type' => 'left',
        'on' => [[
            'field' => $this->db->cfn($this->class_cfg['arch']['notes_url']['id_note'], $this->class_cfg['tables']['notes_url']),
            'exp' => $this->db->cfn($this->class_cfg['arch']['notes']['id'], $this->class_cfg['tables']['notes'])
        ]]
    ];
    $cfg['join'][] = [
      'table' => $this->class_cfg['tables']['url'],
      'type' => 'left',
      'on' => [[
          'field' => $this->db->cfn($this->class_cfg['arch']['url']['id'], $this->class_cfg['tables']['url']),
          'exp' => $this->db->cfn($this->class_cfg['arch']['notes_url']['id_url'], $this->class_cfg['tables']['notes_url'])
      ]],
    ];

    $cfg['join'][]   = [
      'table' => $this->class_cfg['tables']['notes_events'],
      'type' => 'left',
      'on' => [[
          'field' => $this->db->cfn($this->class_cfg['arch']['notes_events']['id_note'], $this->class_cfg['tables']['notes_events']),
          'exp' => $this->db->cfn($this->class_cfg['arch']['notes']['id'], $this->class_cfg['tables']['notes'])
      ]]
    ];
    $cfg['join'][]   = [
      'table' => $this->class_cfg['tables']['events'],
      'type' => 'left',
      'on' => [[
          'field' => $this->db->cfn($this->class_cfg['arch']['notes_events']['id_event'], $this->class_cfg['tables']['notes_events']),
          'exp' => $this->db->cfn($this->class_cfg['arch']['events']['id'], $this->class_cfg['tables']['events'])
      ]]
    ];
    $cfg['join'][]   = [
      'table' => $this->class_cfg['tables']['notes_medias'],
      'type' => 'left',
      'on' => [[
          'field' => $this->db->cfn($this->class_cfg['arch']['notes_medias']['id_note'], $this->class_cfg['tables']['notes_medias']),
          'exp' => $this->db->cfn($this->class_cfg['arch']['notes']['id'], $this->class_cfg['tables']['notes'])
      ]]
    ];
    $cfg['join'][]   = [
      'table' => $this->class_cfg['tables']['notes_medias'],
      'alias' => 'default_medias',
      'type' => 'left',
      'on' => [[
          'field' => $this->db->cfn($this->class_cfg['arch']['notes_medias']['id_note'], 'default_medias'),
          'exp' => $this->db->cfn($this->class_cfg['arch']['notes']['id'], $this->class_cfg['tables']['notes'])
        ], [
          'field' => $this->db->cfn($this->class_cfg['arch']['notes_medias']['default_media'], 'default_medias'),
          'value' => 1
      ]]
    ];
    $cfg['group_by'] = [$this->db->cfn($this->class_cfg['arch']['notes']['id'], $this->class_cfg['tables']['notes'])];

    return $cfg;
  }


  /**
   * Returns the whole information about the given media, including urls and tags
   *
   * @param string $id_media
   * @param boolean $with_notes If true returns a list of the notes associated with the media
   * @return array|null
   */
  public function getMedia(string $id_media, $with_notes = false): ?array
  {
    if ($media = $this->media->getMedia($id_media, true)) {
      $media['urls'] = $this->media->getUrls($id_media);
      $media['tags'] = $this->media->getTags($id_media);
      if ($with_notes) {

      }

      return $media;
    }

    return null;
  }


  /**
   * Returns all the notes of type 'pages'.
   *
   * @param bool   $with_content
   * @param array  $filter
   * @param array  $order
   * @param int    $limit
   * @param int    $start
   * @param string $type
   * @return array
   * @throws Exception
   */
  public function getAll(bool $with_content = false, array $filter = [], array $order = [], int $limit = 50, int $start = 0, string $type = null, bool $published = false): array
  {
    $cfg       = $this->getLastVersionCfg($with_content, $published, $filter);
    $type_cond = [];
    foreach ($this->getTypes() as $t) {
      if (!$type || ($type === $t['value'])) {
        $type_cond[] = [
          'field' => 'bbn_notes.id_type',
          'value' => $t['value']
        ];
      }
    }
    if (empty($type_cond)) {
      if (!($opt = Note::getOptionsObject()->option($type))) {
        throw new \Exception(X::_("Impossible to find a type %s", $type));
      }

      $type_cond[] = [
        'field' => 'bbn_notes.id_type',
        'value' => $type
      ];
    }

    $cfg['where']['conditions'][] = [
      'logic' => 'OR',
      'conditions' => $type_cond
    ];

    $cfg['limit'] = $limit;
    $cfg['start'] = $start >= 0 ? $start : 0;

    if (!empty($order)) {
      $cfg['order'] = $order;
    }

    $db    =& $this->db;
    $idx   = md5(json_encode($filter));
    $total = $this->cacheGetSet(function() use (&$db, $cfg) {
      return $db->count($cfg);
    }, $idx, 'total', 20);
    $data  = $this->db->rselectAll($cfg);
    foreach ($data as &$d) {
      $d['front_img'] = $d['id_media'] ? $this->media->getMedia($d['id_media'], true) : null;

    }

    return [
      'query' => $this->db->last(),
      'data' => $data,
      'total' => $total
    ];
  }


  /**
   * Returns the 'pages' note type ID from options, which should always be the type of the CMS notes.
   *
   * @return string
   */
  public function getNoteType(): string
  {
    if (!$this->noteType) {
        $this->noteType = $this->opt->fromCode('pages', 'types', 'note', 'appui');
    }

      return $this->noteType;
  }


  /**
   * If the given url correspond to a published note returns the id.
   *
   * @param string $url
   * @return string|null
   */
  public function getByUrl(string $url, bool $force = false): ?array
  {
    if (($id_note = $this->note->urlToId($url)) && ($force || $this->isPublished($id_note))) {
      return $this->get($id_note);
    }

    return null;
  }



  /**
   * Returns the object event of the given note.
   *
   * @param string $id_note
   * @return array|null
   */
  public function getEvent(string $id_note)
  {
    if ($id_event = $this->note->getEventIdFromNote($id_note)) {
      $event = $this->event->get($id_event);
      if (!$event) {
        /** @todo temporary */
        $this->db->update('bbn_history_uids', ['bbn_active' => 1], ['bbn_uid' => $id_event]);
        $event = $this->event->get($id_event);
      }
      if ($event) {
        $event['id_note'] = $id_note;
        return $event;
      }
    }

    return null;
  }


  /**
   * If an event linked to the note exists it returns the start date.
   *
   * @param string $id_note
   * @return string|null
   */
  public function getStart(string $id_note): ?string
  {
    if ($event = $this->getEvent($id_note)) {
        return $event[$this->class_cfg['arch']['events']['start']] ?? null;
    }

      return null;
  }


  /**
   * If  an event linked to the note exists it returns the end date.
   *
   * @param string $id_note
   * @return string|null
   */
  public function getEnd(string $id_note)
  {
    if ($event = $this->getEvent($id_note)) {
        return $event[$this->class_cfg['arch']['events']['end']] ?? null;
    }
      return null;
  }


  /**
   * If the note has a corresponding event in bbn_events and the date of start is before now,
   * and the date of end if isset is after now and the note has an url it returns true
   *
   * @param string $id_note
   * @return boolean
   */
  public function isPublished(string $id_note): bool
  {
    $now = strtotime(date('Y-m-d H:i:s'));
    $cfg = $this->class_cfg;

    if ($event = $this->getEvent($id_note)) {
      if (
            isset($event[$cfg['arch']['events']['start']]) &&
            (is_null($event[$cfg['arch']['events']['end']]) || (strtotime($event[$cfg['arch']['events']['end']]) > $now)) &&
            $this->note->hasUrl($id_note)
      ) {
        return true;
      }
    }

    return false;
  }

  /**
   * Publish a note.
   *
   * @param string $id_note
   * @param array  $cfg
   * @return bool|null|array
   */
  public function publish(string $id_note, array $cfg)
  {
    if ($this->note->get($id_note) && !$this->isPublished($id_note)) {
        //if $url is given it updates the note_url
      if (!empty($cfg['url'])) {
        try {
            $this->setUrl($id_note, $cfg['url']);
        }
        catch (Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
      }
      if (!empty($this->note->hasUrl($id_note))) {
        if (empty($this->getEvent($id_note))) {
          return $this->setEvent($id_note, [
              'start'   => $cfg['start'] ?? date('Y-m-d H:i:s'),
              'end'     => $cfg['end'] ?? null,
              'id_type' => $cfg['id_type'] ?? self::$_id_event ?? null
          ]);
        } else {
            //case update
            return $this->updateEvent($id_note, [
                'start'   => $cfg['start'] ?? date('Y-m-d H:i:s'),
                'end'     => $cfg['end'] ?? null,
                'id_type' => $cfg['id_type'] ?? self::$_id_event ?? null
            ]);
        }
      } else {
          return false;
      }
    }

      return false;
  }

    /**
     * Unpublish a note.
   *
     * @param string $id_note
     * @return boolean
     */
  public function unpublish(string $id_note): bool
  {
    if ($event = $this->getEvent($id_note)) {
      
      if ($this->db->delete($this->class_cfg['tables']['notes_events'], [
          $this->class_cfg['arch']['notes_events']['id_note'] => $id_note
        ])
        && $this->db->delete($this->class_cfg['tables']['events'], [
          $this->class_cfg['arch']['events']['id'] => $event['id']
        ])
      ) {
        $this->cacheDelete($id_note);
        return true;
      }
    }

      return false;
  }


    /**
   * Inserts the url for the note if it doesn't exist a published note with the same url or update the url of the given note.
   *
   * @param string $id_note
   * @param string $url
   * @return Boolean
   * @throws Exception
   */
  public function setUrl(string $id_note, string $url, $ignore = false): ?bool
  {
    if ($tmp = $this->note->urlToId($url)) {
      if ($ignore && ($tmp === $id_note)) {
        return 0;
      }

      throw new Exception(X::_('The url you are trying to insert already belongs to a published note. Unpublish the note or change the url!'));
    }

    if (!$this->note->get($id_note)) {
      throw new Exception(X::_('Impossible to find the given note'));
    }

    if ($res = $this->note->insertOrUpdateUrl($id_note, $url)) {
      $this->cacheDelete($id_note);
    }
    return $res;
  }


    /**
     * Removes the url corresponding to the given id_note from bbn_notes_url.
   *
     * @param string $id_note
     * @return bool
     */
  public function removeUrl(string $id_note): bool
  {
      $success = false;

    if ($this->isPublished($id_note)) {
        $this->unpublish($id_note);
    }

    if ($this->note->get($id_note) && $this->note->deleteUrl($id_note)) {
      $this->cacheDelete($id_note);
      $success = true;
    }

      return $success;
  }


    /**
     * Inserts in bbn_events and bbn_notes_events the information relative to the publication of the given note.
   *
     * @param string $id_note
     * @param array $cfg
     * @return boolean|null
     */
  public function setEvent(string $id_note, array $cfg = [])
  {
    if (!array_key_exists('start', $cfg)) {
      throw new Exception(X::_("A start date is mandatory for CMS event (even null)"));
    }

    if (empty($cfg['start'])) {
      return $this->unpublish($id_note);
    }

    if (!($note = $this->note->get($id_note))) {
      throw new Exception(X::_("The note %s does not exist", $id_note));
    }
    
    if (!$this->_check_date($cfg['start'], $cfg['end'] ?? null)) {
      throw new Exception(X::_("The dates don't work... End before start?"));
    }

    if (empty($this->getEvent($id_note))) {
      $fields = $this->class_cfg['arch']['events'];
        //if a type is not given it inserts the event as page
      if ($id_event = $this->event->insert([
          $fields['name']    => $note['title'] ?? '',
          $fields['id_type'] => $cfg['id_type'] ?? self::$_id_event ?? null,
          $fields['start']   => $cfg['start'],
          $fields['end']     => $cfg['end'] ?? null
        ])
      ) {
        if ($res = $this->note->insertNoteEvent($id_note, $id_event)) {
          $this->cacheDelete($id_note);
        }
        return $res;
      }
      else {
        X::log([
          $fields['name']    => $note['title'] ?? '',
          $fields['id_type'] => $cfg['id_type'] ?? self::$_id_event ?? null,
          $fields['start']   => $cfg['start'],
          $fields['end']     => $cfg['end'] ?? null
        ], 'cmsss');
        throw new Exception(X::_("Impossible to insert the event"));
      }
    }
    else if ($this->updateEvent($id_note, $cfg)) {
      $this->cacheDelete($id_note);
      return true;
    }

    return null;
  }


    /**
     * Updates the event relative to the given note.
   *
     * @param string $id_note
     * @param array $cfg
     */
  public function updateEvent(string $id_note, array $cfg = []): ?bool
  {
    if (!array_key_exists('start', $cfg) || !array_key_exists('end', $cfg)) {
      return false;
    }

    if ($this->_check_date($cfg['start'], $cfg['end'])) {
      if ($event = $this->getEvent($id_note)) {
        if (
            (strtotime($cfg['start']) !== strtotime($event['start'])) ||
            (strtotime($cfg['end']) !== strtotime($event['end']) )
        ) {
          $cfg['id_type'] = $cfg['id_type'] ?? self::$_id_event ?? null;
          if ($res = $this->event->edit($event['id'], $cfg)) {
            $this->cacheDelete($id_note);
          }
          return $res;
        } else {
          return true;
        }
      }
    }

      return false;
  }


  /**
   * Adds a new version to the given note with the new content
   *
   * @param string $id_note
   * @param string $title
   * @param string $content
   * @return null|int The number of affected rows (1 if ok)
   */
  public function setContent(string $id_note, string $title, string $content, string $excerpt = ''): ?int
  {
      if ($res = $this->note->insertVersion($id_note, $title, $content, $excerpt)) {
        $this->cacheDelete($id_note);
      }
      return $res;
  }


  /**
   * Changes the type of the note
   *
   * @param string $id_note
   * @param string $type
   * @return int The number of affected rows (1 if ok)
   */
  public function setType(string $id_note, string $type): int
  {
    if ($res = $this->note->setType($id_note, $type)) {
      $this->cacheDelete($id_note);
    }
    return $res;
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
    if ($res = $this->note->setOption($id_note, $id_option)) {
      $this->cacheDelete($id_note);
    }
    return $res;
  }


  /**
   * Sets content, title, start and end for the given URL.
   *
   * @param string $url
   * @param string $title
   * @param string $content
   * @param string $excerpt
   * @param string $start
   * @param string $end
   * @param array $tags
   * @param string $id_type
   * @param string $id_media
   * @return bool Returns true if something has been modified.
   */
  public function set(
    $url,
    string $title = '',
    string $content = '',
    string $excerpt = '',
    string $start = null,
    string $end = null,
    array $tags = null,
    string $id_type = null,
    string $id_media = null,
    string $id_option = null
  ): bool
  {
    if (is_array($url)) {
      $tmp = $url;
      foreach ($tmp as $k => $v) {
        $$k = $v;
      }
    }

    if (!is_string($url) || empty($url)) {
      throw new Exception(X::_("The CMS article MUST have a URL"));
    }

    if ($note = $this->getByUrl($url, true)){
      $id_note = $note['id'];
    }

    if (!empty($id_note)) {
      $cfg = $this->get($id_note);
    }
    elseif (!($cfg = $this->getByUrl($url, true))) {
      throw new Exception(X::_("Impossible to find the article with URL") . ' ' . $url);
    }

    $change = 0;
    if (($cfg['title'] !== $title) || ($cfg['content'] !== $content) || ($cfg['excerpt'] !== $excerpt)) {
      $change += (int)$this->setContent($cfg['id_note'], $title, $content, $excerpt);
    }

    if (($cfg['start'] !== $start) || ($cfg['end'] !== $end)) {
      $change += (int)$this->setEvent($cfg['id_note'], [
        'start' => $start,
        'end' => $end
      ]);
    }

    if (is_array($tags)) {
      $change += (int)$this->note->setTags($cfg['id_note'], $tags);
    }

    if ($id_type && ($cfg['id_type'] !== $id_type)) {
      $change += (int)$this->setType($cfg['id_note'], $id_type);
    }

    if ($id_option && ($cfg['id_option'] !== $id_option)) {
      $change += (int)$this->setOption($cfg['id_note'], $id_option);
    }

    if ($id_media !== $cfg['id_media']) {
      if ($id_media) {
        $change += (int)$this->setDefaultMedia($id_note, $id_media);
      }
      else {
        $change += (int)$this->unsetDefaultMedia($id_note);
      }
    }

    if ($change) {
      $this->cacheDelete($cfg['id_note']);
    }

    return $change ? true : false;
  }



  /**
   * Deletes the given note and unpublish it if published.
   *
   * @param string $id_note
   * @return boolean
   */
  public function delete(string $id_note): bool
  {
    if ($this->note->exists($id_note)) {
      if ($this->note->getUrl($id_note)) {
        $this->removeUrl($id_note);
      }

      foreach ($this->note->getAliases($id_note) as $id_alias) {
        if ($this->note->getUrl($id_alias)) {
          $this->removeUrl($id_alias);
        }
      }

      foreach ($this->note->getChildren($id_note) as $id_child) {
        if ($this->note->getUrl($id_child)) {
          $this->removeUrl($id_child);
        }
      }

      if (!empty($this->note->remove($id_note))) {
        $this->cacheDelete($id_note);
        return true;
      }
    }

    return false;
  }


  public function getTypes(): array
  {
    $o =& $this;
    return $this->cacheGetSet(
      function () use (&$o) {
        $id_cms = $o->opt->fromCode('bbn-cms', 'editors', 'note', 'appui');
        $arr    = [];
        foreach ($o->opt->fullOptions('types', 'note', 'appui') as $op) {
          if ($op['id_alias'] === $id_cms) {
            unset($op['alias']);
            $arr[] = array_merge($op, [
              'text' => $op['text'],
              'value' => $op['id'],
              'prefix' => $op['prefix'] ?? ''
            ]);
          }
        }

        return $arr;
      },
      'types',
      '',
      0
    );
  }

  public function clearCache(string $idNote): bool
  {
    return !$this->cacheDelete($idNote)->cacheHas($idNote);
  }

}
