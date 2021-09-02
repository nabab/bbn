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
	use
    bbn\Models\Tts\Dbconfig;

	protected
    $_notes,
    $_options,
		$_events;
	
	protected static  
		$default_class_cfg = [
			'table' => 'bbn_events',
			'tables' => [
				'notes' => 'bbn_notes',
				'versions' => 'bbn_notes_versions',
				'events' => 'bbn_events',
				'notes_url' => 'bbn_notes_url',
				'notes_events' => 'bbn_notes_events'
			],
			'arch' => [
				'notes' => [
					'id' => 'id',
					'id_parent' => 'id_parent',
					'id_alias' => 'id_alias',
					'id_type' => 'id_type',
					'private' => 'private',
					'locked' => 'locked',
					'pinned' => 'pinned',
					'creator' => 'creator',
					'active' => 'active'
				],
				'versions' => [
					'id_note' => 'id_note',
					'version' => 'version',
					'title' => 'title',
					'content' => 'content',
					'id_user' => 'id_user',
					'creation' => 'creation'
				],
				'events' => [
          'id' => 'id',
          'id_parent' => 'id_parent',
          'id_type' => 'id_type',
					'start' => 'start',
          'end' => 'end',
          'name' => 'name',
          'recurring' => 'recurring',
					'cfg' => 'cfg'
				],
				'notes_events' => [
					'id_note' => 'id_note',
					'id_event' => 'id_event',
				],
				'notes_url' => [
					'id_note' => 'id_note',
					'url' => 'url',
				]
			]
		];

	private static $_id_event;

  /** @var array $class_cfg */
  protected $class_cfg;

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
		if (isset($start)){
			if (!isset($end) || (($end = strtotime($end)) && ($start = strtotime($start)) && $end > $start)){
				return true;
			}
		} else {
			return true;
		}
		return false;
	}

  /**
   * Cms constructor.
   *
   * @param bbn\Db $db
   * @param null $notes
   * @throws \Exception
   */
	public function __construct(bbn\Db $db, $notes = null){
		parent::__construct($db);
		$this->_init_class_cfg();
		$this->_events = new bbn\Appui\Event($this->db);
		$this->_options = bbn\Appui\Option::getInstance();
		if (!self::$_id_event) {
			$id = $this->_options->fromCode('publication', 'event', 'appui');
			self::_set_id_event($id);
		}
		if ( $notes === null ){
			$this->_notes = new \bbn\Appui\Note($this->db);
		} else {
			$this->_notes = $notes;
		}
		
	}

  /**
   * Returns the note with its url, start and end date of publication.
   *
   * @param string $url
   * @return array
   */
	public function get(string $url): array
	{
		$res     = [];
	 	$id_note = $this->_notes->urlToId($url);

		if (!empty($id_note) && $note = $this->_notes->get($id_note)){
			$res          = $note;
			$res['url']   = $this->_notes->getUrl($id_note);
			$res['start'] = $this->getStart($id_note);
			$res['end']   = $this->getEnd($id_note);
		}
		return $res;
	}

  /**
   * Returns all the notes of type 'pages'.
   *
   * @param int $limit
   * @param int $start
   * @return array
   * @throws \Exception
   */
	public function getAll(int $limit = 50, int $start = 0): array 
	{
		$id_pages = $this->_options->fromCode('pages', 'types', 'note', 'appui');
		$pages = $this->_notes->getByType($id_pages, false, $limit, $start);

    return array_map(function($a){
      $a['is_published']  = $this->isPublished($a['id_note']);
      $a['url']           = $this->_notes->hasUrl($a['id_note']) ? $this->_notes->getUrl($a['id_note']) : '';
      $a['type']          = 'pages';
      $a['start']         = $this->getStart($a['id_note']);
      $a['end']           = $this->getEnd($a['id_note']);
      $a['files']         = $this->_notes->getMedias($a['id_note']) ?: [];

      return $a;
    }, $pages);
	}

  /**
   * Returns the number of all the notes of type 'pages'.
   *
   * @return int
   */
	public function countAll(): int
	{
		$id_pages = $this->_options->fromCode('pages', 'types', 'note', 'appui');
		return $this->_notes->countByType($id_pages);
	}

	
 /**
 * If the given url correspond to a published note returns the id.
 * 
 * @param string $url
 * @return string|null
 */
	public function getByUrl(string $url, bool $force = false):? string
	{
		if (($id_note = $this->_notes->urlToId($url)) && ($force || $this->isPublished($id_note))) {
      return $id_note;
		}

		return null;
	}
	


	/**
	 * Deletes the given note and unpublish it if published.
   *
	 * @param string $id_note
	 * @return boolean
	 */
	public function delete(string $id_note): bool
	{
		if ($note = $this->_notes->get($id_note)){
			if ($this->_notes->getUrl($id_note)){
				$this->removeUrl($id_note);
			}

			if (!empty($this->_notes->remove($note['id']))){
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
   * @throws \Exception
   */
	public function setUrl(string $id_note, string $url): ?bool
	{	
		$success = false;
		$idx     = X::find($this->getFullPublished(), ['url' => $url]);

		if ($this->_notes->get($id_note) && $idx === null ){
      $success = $this->_notes->insertOrUpdateUrl($id_note, $url);
		}
		elseif ($idx !== null) {
			throw new \Exception(X::_('The url you are trying to insert already belongs to a published note. Unpublish the note or change the url!'));
		}

		return $success;
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

		if ($this->isPublished($id_note)){
			$this->unpublish($id_note);
		}

		if ($this->_notes->get($id_note) && $this->_notes->deleteUrl($id_note)){
      $success = true;
		}
		
		return $success;
	}

  /**
   * Returns the object event of the given note.
   *
   * @param string $id_note
   * @return array|null
   */
	public function getEvent(string $id_note)
	{
		if ($id_event = $this->_notes->getEventIdFromNote($id_note)){
			if ($event = $this->db->rselect([
						'table' => $this->class_cfg['table'], 
						'fields' => [],
						'where' => [
							'conditions' => [[
								'field' => $this->fields['id'],
								'value' => $id_event
							]]], 
						]
					)
			){
				//if the event is not in bbn_notes_events it inserts the row
				$this->_notes->_insert_notes_events($id_note, $id_event);
				$event['id_note'] = $id_note;
				return $event;
			}
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

		if ($this->_check_date($cfg['start'], $cfg['end'])){
		  if ($event = $this->getEvent($id_note)) {
        if (
          (strtotime($cfg['start']) !== strtotime($event['start'])) ||
          (strtotime($cfg['end']) !== strtotime($event['end']) )
        ){
          $cfg['id_type'] = $cfg['id_type'] ?? self::$_id_event ?? null;
          return $this->_events->edit($event['id'], $cfg);
        } else {
          return true;
        }
      }
		}

		return false;
	}


	/**
	 * If an event linked to the note exists it returns the start date.
   *
	 * @param string $id_note
	 * @return string|null
	 */
	public function getStart(string $id_note): ?string
	{
		if ($event = $this->getEvent($id_note) ){
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
		if ($event = $this->getEvent($id_note) ){
			return $event[$this->class_cfg['arch']['events']['end']] ?? null;
		}
		return null;
	}


	/**
	 * Inserts in bbn_events and bbn_notes_events the information relative to the publication of the given note.
   *
	 * @param string $id_note
	 * @param array $cfg
	 * @return boolean|null
	 */
	public function setEvent(string $id_note, array $cfg = []){
	  if (!array_key_exists('start', $cfg)) {
	    return null;
    }

		if (($note = $this->_notes->get($id_note)) && ($this->_check_date($cfg['start'], $cfg['end'] ?? null))){
			if (empty($this->getEvent($id_note))){
        $fields = $this->class_cfg['arch']['events'];
				//if a type is not given it inserts the event as page
				if ($id_event = $this->_events->insert([
          $fields['name']    => $note['title'] ?? '',
          $fields['id_type'] => $cfg['id_type'] ?? self::$_id_event ?? null,
          $fields['start']   => $cfg['start'],
					$fields['end']     => $cfg['end'] ?? null
				])){
					return $this->_notes->_insert_notes_events($id_note, $id_event);
				}
			} else {
				return $this->updateEvent($id_note, $cfg);
			}
		}

		return null;
	}

	/**
	 * Returns all notes that has a link with bbn_events.
   *
	 * @return array
	 */
	public function getFull(): array
	{
		// Select all events
    $now    = strtotime(date('Y-m-d H:i:s'));
    $events = $this->db->rselectAll([
      'table' => $this->class_cfg['table'],
      'fields' => [],
      'where'  => [
        'conditions' => [
          [
            'logic' => 'OR',
            'conditions' => [
              [
                'field'     => $this->db->cfn($this->class_cfg['arch']['events']['end'], $this->class_cfg['table']),
                'operator'  => 'isnull',
              ],
              [
                'field'     => $this->db->cfn($this->class_cfg['arch']['events']['end'], $this->class_cfg['table']),
                'operator'  => '>',
                'value'     => $now
              ],
            ]
          ]
        ]
      ]
    ]);

		$res = [];
		if (!empty($events)){
			foreach ($events as $e){
				//takes events without end date or with end date > now
				if ( 
					array_key_exists($this->class_cfg['arch']['events']['start'], $e) &&
					(
					  is_null($e[$this->class_cfg['arch']['events']['end']]) ||
            (strtotime($e[$this->class_cfg['arch']['events']['end']]) > $now)
          )
				){
					// gets the note correspondent to the id_event and push it in $res
          $id_note = $this->_notes->getNoteIdFromEvent($e['id']);

					if ($id_note && $this->_notes->hasUrl($id_note)){
						$note           = $this->_notes->get($id_note);
						$note['url']    = $this->_notes->getUrl($id_note);
						$note['start']  = $e[$this->class_cfg['arch']['events']['start']];
						$note['end']    = $e[$this->class_cfg['arch']['events']['end']];
						$res[]          = $note;
					}
				}
			}
		}

		return $res;
	}


	/**
	 * Returns an array containing all the published notes.
   *
	 * @return array
	 */
	public function getFullPublished(): array
	{
    return array_filter($this->getFull(), function ($a){
      return $a['start'] !== null;
    });
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

		if ($event = $this->getEvent($id_note)){
			if ( 
				isset($event[$cfg['arch']['events']['start']]) &&
				(is_null($event[$cfg['arch']['events']['end']]) || (strtotime($event[$cfg['arch']['events']['end']]) > $now)) &&
				$this->_notes->hasUrl($id_note)
			){
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
		if ($this->_notes->get($id_note) && !$this->isPublished($id_note)){
			//if $url is given it updates the note_url
			if (!empty($cfg['url'])){
				try {
					$this->setUrl($id_note, $cfg['url']);
				}
				catch (\Exception $e){
					return [
						'error' => $e->getMessage()
					];
				}
			}
			if (!empty($this->_notes->hasUrl($id_note))){
				if (empty($this->getEvent($id_note))){
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
		if (($event = $this->getEvent($id_note)) && $this->isPublished($id_note)) {
			if ($this->updateEvent(
				$id_note, [
					'start' => null,
					'end' => null
				])
		  ){
				return $this->_notes->_remove_note_events($id_note, $event['id']);
			}
		}

		return false;
	}
	 	
}