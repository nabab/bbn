<?php

/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 14/04/2016
 * Time: 20:38
 */

 //the notes inserted with appui/notes have to be type 'pages'
namespace bbn\Appui;

use bbn;
use bbn\X;

class Cms extends bbn\Models\Cls\Db
{
    /** @var Note A Note instance. */
    protected $note;

    /** @var Option An Option instance. */
    protected $opt;

    /** @var Event En Event instance. */
    protected $event;

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
      throw new \Exception(X::_("The end date is not valid"));
    }

    if (empty($end)) {
      return true;
    }

    $end = strtotime($end);
    if (!$end) {
      throw new \Exception(X::_("The end date is not valid"));
    }

    if ($end <= $start) {
      //throw new \Exception(X::_("The end date is before the start"));
      return false;
    }

    return true;
  }

  /**
   * Cms constructor.
   *
   * @param bbn\Db $db
   * @param null $notes
   * @throws \Exception
   */
  public function __construct(bbn\Db $db, Note $note = null)
  {
    parent::__construct($db);
    $this->event = new Event($this->db);
    $this->opt   = Option::getInstance();
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
        $this->event->getClassCfg()
    );
  }


  public function getLatest($limit, $start): array
  {
      $cfg                         = $this->note->getLastVersionCfg();
      $cf                          = $this->note->getClassCfg();
      $cf_ev                       = $this->event->getClassCfg();
      $cfg['fields'][]             = $cf_ev['arch']['events']['start'];
      $cfg['fields'][]             = $cf_ev['arch']['events']['end'];
      $cfg['fields']['event_type'] = $this->db->cfn($cf_ev['arch']['events']['id_type'], $cf_ev['tables']['events']);
      $cfg['fields']['event_name'] = $this->db->cfn($cf_ev['arch']['events']['name'], $cf_ev['tables']['events']);
      $cfg['join'][]               = [
          'table' => $cf['tables']['events'],
          'on' => [
              [
                  'field' => $this->db->cfn($cf['arch']['events']['id_note'], $cf['tables']['events']),
                  'exp' => $this->db->cfn($cf['arch']['notes']['id'], $cf['tables']['notes'])
              ]
          ]
      ];

      $cfg['join'][] = [
          'table' => $cf_ev['tables']['events'],
          'on' => [
              [
                  'field' => $this->db->cfn($cf['arch']['events']['id_event'], $cf['tables']['events']),
                  'exp' => $this->db->cfn($cf_ev['arch']['events']['id'], $cf_ev['tables']['events'])
              ]
          ]
      ];

      $cfg['join'][] = [
          'table' => $cf['tables']['notes_url'],
          'on' => [
              [
                  'field' => $this->db->cfn($cf['arch']['notes_url']['id_note'], $cf['tables']['notes_url']),
                  'exp' => $this->db->cfn($cf['arch']['notes']['id'], $cf['tables']['notes'])
              ]
          ]
      ];

      $cfg['join'][] = [
        'table' => $cf['tables']['url'],
        'on' => [
            [
                'field' => $this->db->cfn($cf['arch']['url']['id'], $cf['tables']['url']),
                'exp' => $this->db->cfn($cf['arch']['notes_url']['id_url'], $cf['tables']['notes_url'])
            ]
        ]
      ];

      $total        = $this->db->count($cfg);
      $cfg['where'] = [
          'conditions' => [
              [
                  'field' => 'start',
                  'operator' => '<=',
                  'exp' => 'NOW()'
              ]
          ]
      ];

      $cfg['order'] = [['field' => 'start', 'dir' => 'DESC']];
      $cfg['limit'] = $limit;
      $cfg['start'] = $start;

      return [
          'data' => $this->db->rselectAll($cfg),
          'query' => $this->db->last(),
          'total' => $total
      ];
  }

  public function getNext($limit, $start): array
  {
      $cfg                         = $this->note->getLastVersionCfg();
      $cf                          = $this->note->getClassCfg();
      $cf_ev                       = $this->event->getClassCfg();
      $cfg['fields'][]             = $cf_ev['arch']['events']['start'];
      $cfg['fields'][]             = $cf_ev['arch']['events']['end'];
      $cfg['fields']['event_type'] = $this->db->cfn($cf_ev['arch']['events']['id_type'], $cf_ev['tables']['events']);
      $cfg['fields']['event_name'] = $this->db->cfn($cf_ev['arch']['events']['name'], $cf_ev['tables']['events']);
      $cfg['join'][]               = [
          'table' => $cf['tables']['events'],
          'on' => [
              [
                  'field' => $this->db->cfn($cf['arch']['events']['id_note'], $cf['tables']['events']),
                  'exp' => $this->db->cfn($cf['arch']['notes']['id'], $cf['tables']['notes'])
              ]
          ]
      ];

      $cfg['join'][] = [
          'table' => $cf_ev['tables']['events'],
          'on' => [
              [
                  'field' => $this->db->cfn($cf['arch']['events']['id_event'], $cf['tables']['events']),
                  'exp' => $this->db->cfn($cf_ev['arch']['events']['id'], $cf_ev['tables']['events'])
              ]
          ]
      ];

      $cfg['join'][] = [
          'table' => $cf['tables']['notes_url'],
          'on' => [
              [
                  'field' => $this->db->cfn($cf['arch']['notes_url']['id_note'], $cf['tables']['notes_url']),
                  'exp' => $this->db->cfn($cf['arch']['notes']['id'], $cf['tables']['notes'])
              ]
          ]
      ];

      $cfg['join'][] = [
        'table' => $cf['tables']['url'],
        'on' => [
            [
                'field' => $this->db->cfn($cf['arch']['url']['id'], $cf['tables']['url']),
                'exp' => $this->db->cfn($cf['arch']['notes_url']['id_url'], $cf['tables']['notes_url'])
            ]
        ]
      ];

      $total        = $this->db->count($cfg);
      $cfg['where'] = [
          'conditions' => [
              [
                  'field' => 'start',
                  'operator' => '>',
                  'exp' => 'NOW()'
              ]
          ]
      ];

      $cfg['order'] = [['field' => 'start', 'dir' => 'DESC']];
      $cfg['limit'] = $limit;
      $cfg['start'] = $start;

      return [
          'data' => $this->db->rselectAll($cfg),
          'total' => $total
      ];
  }

  /**
   * Returns the note from its id, with its URL, start and end date of publication.
   *
   * @param string $id_note
   * @return array
   */
  public function get(string $id_note): array
  {
    $res = [];
    if (!empty($id_note) && ($note = $this->note->get($id_note))) {
        $res          = $note;
        $res['url']   = $this->note->getUrl($id_note);
        $res['start'] = $this->getStart($id_note);
        $res['end']   = $this->getEnd($id_note);
        $res['tags']  = $this->note->getTags($id_note);
        $res['items'] = $note['content'] ? json_decode($note['content'], true) : [];
    }

    return $res;
  }


  public function getLastVersionCfg(bool $with_content = false)
  {
    $cfg = $this->note->getLastVersionCfg($with_content);
    $cfg['fields'][]             = 'url';
    $cfg['fields'][]             = 'start';
    $cfg['fields'][]             = 'end';
    $cfg['fields']['num_medias'] = 'COUNT(' . $this->db->cfn($this->class_cfg['arch']['notes_medias']['id_note'], $this->class_cfg['tables']['notes_medias'], true) . ')';
    $cfg['where']['mime']        = 'json/bbn-cms';
    $cfg['where']['private']     = 0;
    $cfg['join'][] = [
        'table' => $this->class_cfg['tables']['notes_url'],
        'type' => 'left',
        'on' => [
            [
                'field' => $this->db->cfn($this->class_cfg['arch']['notes_url']['id_note'], $this->class_cfg['tables']['notes_url']),
                'exp' => $this->db->cfn($this->class_cfg['arch']['notes']['id'], $this->class_cfg['tables']['notes'])
            ]
        ]
    ];
    $cfg['join'][] = [
      'table' => $this->class_cfg['tables']['url'],
      'type' => 'left',
      'on' => [[
          'field' => $this->db->cfn($this->class_cfg['arch']['url']['id'], $this->class_cfg['tables']['url']),
          'exp' => $this->db->cfn($this->class_cfg['arch']['notes_url']['id_url'], $this->class_cfg['tables']['notes_url'])
      ]],
    ];

    $cfg['join'][] = [
      'table' => $this->class_cfg['tables']['url'],
      'type' => 'left',
      'on' => [
          [
              'field' => $this->db->cfn($this->class_cfg['arch']['url']['id'], $this->class_cfg['tables']['url']),
              'exp' => $this->db->cfn($this->class_cfg['arch']['notes_url']['id_url'], $this->class_cfg['tables']['notes_url'])
          ]
      ]
    ];
    $cfg['join'][] = [
        'table' => $this->class_cfg['tables']['notes_events'],
        'type' => 'left',
        'on' => [[
            'field' => $this->db->cfn($this->class_cfg['arch']['notes_events']['id_note'], $this->class_cfg['tables']['notes_events']),
            'exp' => $this->db->cfn($this->class_cfg['arch']['notes']['id'], $this->class_cfg['tables']['notes'])
        ]]
    ];
    $cfg['join'][] = [
        'table' => $this->class_cfg['tables']['events'],
        'type' => 'left',
        'on' => [[
            'field' => $this->db->cfn($this->class_cfg['arch']['notes_events']['id_event'], $this->class_cfg['tables']['notes_events']),
            'exp' => $this->db->cfn($this->class_cfg['arch']['events']['id'], $this->class_cfg['tables']['events'])
        ]]
    ];
    $cfg['join'][] = [
        'table' => $this->class_cfg['tables']['notes_medias'],
        'type' => 'left',
        'on' => [[
            'field' => $this->db->cfn($this->class_cfg['arch']['notes_medias']['id_note'], $this->class_cfg['tables']['notes_medias']),
            'exp' => $this->db->cfn($this->class_cfg['arch']['notes']['id'], $this->class_cfg['tables']['notes'])
        ]]
    ];
    $cfg['group_by'] = [$this->db->cfn($this->class_cfg['arch']['notes']['id'], $this->class_cfg['tables']['notes'])];

    return $cfg;
  }

  /**
   * Returns all the notes of type 'pages'.
   *
   * @param bool  $with_content
   * @param array $filter
   * @param int   $limit
   * @param int   $start
   * @return array
   * @throws \Exception
   */
  public function getAll(bool $with_content = false, array $filter = [], array $order = [], int $limit = 50, int $start = 0): array
  {
    $cfg = $this->getLastVersionCfg($with_content);
    $cfg['limit'] = $limit;
    $cfg['start'] = $start >= 0 ? $start : 0;
    if (!empty($filter)) {
      $cfg['having'] = $filter;
    }

    if (!empty($order)) {
        $cfg['order'] = $order;
    }

    $total = $this->db->count($cfg);
    $data  = $this->db->rselectAll($cfg);

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
   * Returns the number of all the notes of type 'pages'.
   *
   * @return int
   */
  public function countAll(): int
  {
      return $this->note->countByType($this->getNoteType());
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
      if (
              $event = $this->db->rselect([
                    'table' => $this->class_cfg['table'],
                    'fields' => [],
                    'where' => [
                        'conditions' => [[
                            'field' => $this->fields['id'],
                            'value' => $id_event
                        ]]],
                    ])
      ) {
        //if the event is not in bbn_notes_events it inserts the row
        $this->note->insertNoteEvent($id_note, $id_event);
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
        catch (\Exception $e) {
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
        $cfg = $this->class_cfg;
      if (
              $this->db->delete(
                  $this->class_cfg['tables']['notes_events'],
                  [$this->class_cfg['arch']['notes_events']['id_note'] => $id_note]
              )
      ) {
        return (bool)$this->db->delete(
            $this->class_cfg['tables']['events'],
            [$this->class_cfg['arch']['events']['id'] => $event['id']]
        );
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
   * @throws \Exception
   */
  public function setUrl(string $id_note, string $url, $ignore = false): ?bool
  {
    if ($tmp = $this->note->urlToId($url)) {
      if ($ignore && ($tmp === $id_note)) {
        return 0;
      }

      throw new \Exception(X::_('The url you are trying to insert already belongs to a published note. Unpublish the note or change the url!'));
    }

    if (!$this->note->get($id_note)) {
      throw new \Exception(X::_('Impossible to find the given note'));
    }

    return $this->note->insertOrUpdateUrl($id_note, $url);
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
      throw new \Exception(X::_("A start date is mandatory for CMS event (even null)"));
    }

    if (empty($cfg['start'])) {
      return $this->unpublish($id_note);
    }

    if (!($note = $this->note->get($id_note))) {
      throw new \Exception(X::_("The note %s does not exist", $id_note));
    }
    
    if (!$this->_check_date($cfg['start'], $cfg['end'] ?? null)) {
      throw new \Exception(X::_("The dates don't work... End before start?"));
    }

    if (empty($this->getEvent($id_note))) {
      $fields = $this->class_cfg['arch']['events'];
        //if a type is not given it inserts the event as page
      if (
        $id_event = $this->event->insert([
          $fields['name']    => $note['title'] ?? '',
          $fields['id_type'] => $cfg['id_type'] ?? self::$_id_event ?? null,
          $fields['start']   => $cfg['start'],
          $fields['end']     => $cfg['end'] ?? null
      ])) {
        return $this->note->insertNoteEvent($id_note, $id_event);
      }
      else {
        X::log([
          $fields['name']    => $note['title'] ?? '',
          $fields['id_type'] => $cfg['id_type'] ?? self::$_id_event ?? null,
          $fields['start']   => $cfg['start'],
          $fields['end']     => $cfg['end'] ?? null
        ], 'cmsss');
        throw new \Exception(X::_("Impossible to insert the event"));
      }
    }
    else {
      return $this->updateEvent($id_note, $cfg);
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
          return $this->event->edit($event['id'], $cfg);
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
  public function setContent(string $id_note, string $title, string $content): ?int
  {
      return $this->note->insertVersion($id_note, $title, $content);
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
      return $this->note->setType($id_note, $type);
  }


    /**
     * Sets content, title, start and end for the given URL.
     *
     * @param string $url
     * @param string $title
     * @param string $content
     * @param string $start
     * @param string $end
     * @param array $tags
     * @param string $id_type
     * @return bool Returns true if something has been modified.
     */
  public function set(
    string $url,
    string $title,
    string $content,
    string $start = null,
    string $end = null,
    array $tags = null,
    string $id_type = null
  ): bool
  {
    if (!($cfg = $this->getByUrl($url, true))) {
      throw new \Exception(X::_("Impossible to find the article with URL") . ' ' . $url);
    }

    $change = 0;
    if (($cfg['title'] !== $title) || ($cfg['content'] !== $content)) {
      $change += (int)$this->setContent($cfg['id_note'], $title, $content);
    }

    if (($cfg['start'] !== $start) || ($cfg['end'] !== $end)) {
      $change += (int)$this->setEvent($cfg['id_note'], [
        'start' => $start,
        'end' => $end
      ]);
    }

    if (is_array($tags)) {
      $change += $this->note->setTags($cfg['id_note'], $tags);
    }

    if ($id_type && ($cfg['id_type'] !== $id_type)) {
      $change += $this->setType($cfg['id_note'], $id_type);
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
    if ($note = $this->note->get($id_note)) {
      if ($this->note->getUrl($id_note)) {
        $this->removeUrl($id_note);
      }

      if (!empty($this->note->remove($note['id']))) {
          return true;
      }
    }
      return false;
  }
}
