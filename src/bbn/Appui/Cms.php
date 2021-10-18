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
	public function __construct(bbn\Db $db, Note $note = null)
	{
		parent::__construct($db);
		$this->event = new Event($this->db);
		$this->opt = Option::getInstance();
		if (!self::$_id_event) {
			$id = $this->opt->fromCode('publication', 'event', 'appui');
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
		$cfg = $this->note->getLastVersionCfg();
		$cf = $this->note->getClassCfg();
		$cf_ev = $this->event->getClassCfg();
		$cfg['fields'][] = $cf_ev['arch']['events']['start'];
		$cfg['fields'][] = $cf_ev['arch']['events']['end'];
		$cfg['fields']['event_type'] = $this->db->cfn($cf_ev['arch']['events']['id_type'], $cf_ev['tables']['events']);
		$cfg['fields']['event_name'] = $this->db->cfn($cf_ev['arch']['events']['name'], $cf_ev['tables']['events']);
		$cfg['join'][] = [
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
			'table' => $cf['tables']['url'],
			'on' => [
				[
					'field' => $this->db->cfn($cf['arch']['url']['id_note'], $cf['tables']['url']),
					'exp' => $this->db->cfn($cf['arch']['notes']['id'], $cf['tables']['notes'])
				]
			]
		];

		$total = $this->db->count($cfg);
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
		$cfg = $this->note->getLastVersionCfg();
		$cf = $this->note->getClassCfg();
		$cf_ev = $this->event->getClassCfg();
		$cfg['fields'][] = $cf_ev['arch']['events']['start'];
		$cfg['fields'][] = $cf_ev['arch']['events']['end'];
		$cfg['fields']['event_type'] = $this->db->cfn($cf_ev['arch']['events']['id_type'], $cf_ev['tables']['events']);
		$cfg['fields']['event_name'] = $this->db->cfn($cf_ev['arch']['events']['name'], $cf_ev['tables']['events']);
		$cfg['join'][] = [
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
			'table' => $cf['tables']['url'],
			'on' => [
				[
					'field' => $this->db->cfn($cf['arch']['url']['id_note'], $cf['tables']['url']),
					'exp' => $this->db->cfn($cf['arch']['notes']['id'], $cf['tables']['notes'])
				]
			]
		];

		$total = $this->db->count($cfg);
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
   * Returns the note with its url, start and end date of publication.
   *
   * @param string $url
   * @return array
   */
	public function get(string $url): array
	{
		$res     = [];
	 	$id_note = $this->note->urlToId($url);

		if (!empty($id_note) && $note = $this->note->get($id_note)){
			$res          = $note;
			$res['url']   = $this->note->getUrl($id_note);
			$res['start'] = $this->getStart($id_note);
			$res['end']   = $this->getEnd($id_note);
		}
		return $res;
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
		$cfg = $this->note->getLastVersionCfg();
		if (!$with_content) {
			array_pop($cfg['fields']);
		}

		$cfg['limit'] = $limit;
		$cfg['start'] = $start >= 0 ? $start : 0;
		$cfg['fields'][] = 'url';
		$cfg['fields'][] = 'start';
		$cfg['fields'][] = 'end';
		$cfg['fields']['num_medias'] = 'COUNT('.$this->db->cfn($this->class_cfg['arch']['notes_medias']['id_note'], $this->class_cfg['tables']['notes_medias'], true).')';
		$cfg['where']['mime'] = 'json/bbn-cms';
		$cfg['where']['private'] = 0;
		if (!empty($filter)) {
			$cfg['having'] = $filter;
		}

		if (!empty($order)) {
			$cfg['order'] = $order;
		}

		$cfg['join'][] = [
			'table' => $this->class_cfg['tables']['url'],
			'type' => 'left',
			'on' => [[
				'field' => $this->db->cfn($this->class_cfg['arch']['url']['id_note'], $this->class_cfg['tables']['url']),
				'exp' => $this->db->cfn($this->class_cfg['arch']['notes']['id'], $this->class_cfg['tables']['notes'])
			]],
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

		$total = $this->db->count($cfg);
    $data = $this->db->rselectAll($cfg);

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
	public function getByUrl(string $url, bool $force = false):? string
	{
		if (($id_note = $this->note->urlToId($url)) && ($force || $this->isPublished($id_note))) {
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
		if ($note = $this->note->get($id_note)){
			if ($this->note->getUrl($id_note)){
				$this->removeUrl($id_note);
			}

			if (!empty($this->note->remove($note['id']))){
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

		if ($this->note->get($id_note) && $idx === null ){
      $success = $this->note->insertOrUpdateUrl($id_note, $url);
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

		if ($this->note->get($id_note) && $this->note->deleteUrl($id_note)){
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
		if ($id_event = $this->note->getEventIdFromNote($id_note)){
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
				$this->note->_insert_notes_events($id_note, $id_event);
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
          return $this->event->edit($event['id'], $cfg);
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

		if (($note = $this->note->get($id_note)) && ($this->_check_date($cfg['start'], $cfg['end'] ?? null))){
			if (empty($this->getEvent($id_note))){
        $fields = $this->class_cfg['arch']['events'];
				//if a type is not given it inserts the event as page
				if ($id_event = $this->event->insert([
          $fields['name']    => $note['title'] ?? '',
          $fields['id_type'] => $cfg['id_type'] ?? self::$_id_event ?? null,
          $fields['start']   => $cfg['start'],
					$fields['end']     => $cfg['end'] ?? null
				])){
					return $this->note->_insert_notes_events($id_note, $id_event);
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
          $id_note = $this->note->getNoteIdFromEvent($e['id']);

					if ($id_note && $this->note->hasUrl($id_note)){
						$note           = $this->note->get($id_note);
						$note['url']    = $this->note->getUrl($id_note);
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
				$this->note->hasUrl($id_note)
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
		if ($this->note->get($id_note) && !$this->isPublished($id_note)){
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
			if (!empty($this->note->hasUrl($id_note))){
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
				return $this->note->_remove_note_events($id_note, $event['id']);
			}
		}

		return false;
	}
	 	
}