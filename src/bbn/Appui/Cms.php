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

	private
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

	/**
	 * 
	 * @param null|string $id
	 * @return String
	 */

	private static function _set_id_event($id) {
		self::$_id_event = $id;
	}
	/**
	 * Removes the row corresponding to the given arguments from bbn_notes_events.
	 * @param string $id_note
   * @return Boolean
	 */
	private function _remove_note_events($id_note, $id_event): ?bool
	{
		return $this->db->delete(
			$this->class_cfg['tables']['notes_events'], [
				$this->class_cfg['arch']['notes_events']['id_event'] => $id_event,
				$this->class_cfg['arch']['notes_events']['id_note'] => $id_note
			]
		);
	}
	
	/**
	 * If the row corresponding to the given arguments is not in the table bbn_notes_events it inserts the row.
	 * @param string $id_note
	 * @return Boolean
	 */
	private function _insert_notes_events($id_note, $id_event): ?bool
	{ 
		if ( empty(
			$this->db->rselect($this->class_cfg['tables']['notes_events'], [], [
				$this->class_cfg['arch']['notes_events']['id_note'] => $id_note,
				$this->class_cfg['arch']['notes_events']['id_event'] => $id_event,
			])
		) ){
			return $this->db->insert(
				$this->class_cfg['tables']['notes_events'],[
					$this->class_cfg['arch']['notes_events']['id_note'] => $id_note,
					$this->class_cfg['arch']['notes_events']['id_event'] => $id_event,
				]
			);
		}
		return false;
	}
	/**
	 * If a date is given for $end checks if it's after the start date.
	 * @param date $start
	 * @param date $end
	 * @return Boolean
	 */
	private function _check_date($start, $end): bool
	{
		if ( isset($start) ){
			if ( !isset($end) || strtotime($end) > strtotime($start) ){
				return true;
			}
		} else {
			return true;
		}
		return false;
	}
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
	 * @param string $url
	 * @return Array
	 */
	public function get(string $url): array
	{
		$res = [];
	 	$id_note = $this->db->rselect([
			 'table' => $this->class_cfg['tables']['notes_url'], 
			 'fields' => [$this->class_cfg['arch']['notes_url']['id_note']],
			 'where' => [
				 'conditions' => [[
					 'field' => $this->class_cfg['arch']['notes_url']['url'],
					 'value' => $url
				 ]]
				], 
			]
		)['id_note'];
		if ( !empty($id_note) && $note = $this->_notes->get($id_note)){
			$res = $note;
			$res['url'] = $this->getUrl($id_note);
			$res['start'] = $this->getStart($id_note);
			$res['end'] = $this->getEnd($id_note);
		}
		return $res;
	}

	/**
	 * Returns all the notes of type 'pages'.
	 * @param number $limit
	 * @param number $start
	 * @return array
	 */
	public function getAll(int $limit = 50, int $start = 0): array 
	{
		$pages = $this->_notes->getByType($this->_options->fromCode('pages', 'types', 'note', 'appui'), false,$limit, $start);
		$tmp = array_map(function($a){
			$a['is_published'] = $this->isPublished($a['id_note']);
			$a['url'] = $this->hasUrl($a['id_note']) ? $this->getUrl($a['id_note']) : '';
			$a['type'] = 'pages';
			$a['start'] = $this->getStart($a['id_note']);
			$a['end'] = $this->getEnd($a['id_note']);
			$a['files'] = $this->_notes->getMedias($a['id_note']) ?: [];
			return $a;
		}, $pages); 
		return $tmp; 
	}

 /**
 * If the given url correspond to a published note returns the id.
 * 
 * @param string $url
 * @return string || null
 */
	public function getByUrl(string $url):? string
	{
		if ( $id_note = $this->db->selectOne([
			'table' => $this->class_cfg['tables']['notes_url'], 
			'fields' => [$this->class_cfg['arch']['notes_url']['id_note']],
			'where' => [
				'conditions' => [[
					'field' => $this->class_cfg['arch']['notes_url']['url'],
					'value' => $url
				]]
			]
		])){
			if ( $this->isPublished($id_note) ){
				return $id_note;
			}
		}
		return null;
	}
	


	/**
	 * Deletes the given note and unpublish it if published.
	 * @param string $id_note
	 * @return Boolean
	 */
	public function delete(string $id_note): bool
	{
		if ( $note = $this->_notes->get($id_note) ){
			if ( $this->getUrl($id_note) ){
				$this->removeUrl($id_note);
			}
			if(
				!empty($this->_notes->remove($note['id']))
			){
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Returns true if the note is linked to an url.
	 * @param string $id_note
	 * @return Boolean
	 * 
	 */
	public function hasUrl(string $id_note) :?bool
	{
		if ( $this->db->selectOne(
			$this->class_cfg['tables']['notes_url'], 
			$this->class_cfg['arch']['notes_url']['url'], 
			[
				$this->class_cfg['arch']['notes_url']['id_note'] => $id_note
			])
	  ){
			return true;
		}
		return false;
	}
	/**
	 * Returns the url of the note.
	 * @param string $id_note
	 * @return string || null
	 */
	public function getUrl(string $id_note) :?string
	{
		if ( $this->hasUrl($id_note) ){
			return $this->db->selectOne([
				'table' => $this->class_cfg['tables']['notes_url'], 
				'fields' => [$this->class_cfg['arch']['notes_url']['url']],
				'where'  => [
					'conditions'=>[[
						'field' => $this->class_cfg['arch']['notes_url']['id_note'],
						'value' => $id_note
					]]
				]	
			]);
		}
		return null;
	}
	
	/**
	 * Inserts the url for the note if it doesn't exist a published note with the same url or update the url of the given note.
	 * @param string $id_note
	 * @param string $url
	 * @return Boolean
	 */
	public function setUrl($id_note, $url): ?bool
	{	
		$success = false;
		$idx = X::find($this->getFullPublished(), ['url' => $url]);
		if ( $this->_notes->get($id_note) && !isset($idx) ){
			if ( empty($this->db->rselect([
				'table' => $this->class_cfg['tables']['notes_url'], 
				'fields' => [$this->class_cfg['arch']['notes_url']['url']],
				'where' => [
					'conditions' => [[
						'field' => $this->class_cfg['arch']['notes_url']['id_note'],
						'value' => $id_note
					]]
				]
				])['url']) 
			){
				$success = $this->db->insert(
					$this->class_cfg['tables']['notes_url'],
					[
						$this->class_cfg['arch']['notes_url']['url'] => $url,
						$this->class_cfg['arch']['notes_url']['id_note'] => $id_note
					]
				);
			}	else {
				$success = $this->db->update(
					$this->class_cfg['tables']['notes_url'],
					[$this->class_cfg['arch']['notes_url']['url'] => $url],
					[
						$this->class_cfg['arch']['notes_url']['id_note'] => $id_note
					]
				);
				
			}
		}
		elseif ( !empty($idx) ) {
			throw new \Exception(X::_('The url you are trying to insert already belongs to a published note. Unpublish the note or change the url!'));
		}
		return $success;
	}

	/**
	 * Removes the url corresponding to the given id_note from bbn_notes_url.
	 * @param string $id_note
	 * @return bool
	 */
	public function removeUrl(string $id_note): bool
	{
		$success = false;
		if ( $this->isPublished($id_note) ){
			$this->unpublish($id_note);
		} 
		if ( $this->_notes->get($id_note)){
			if ( $this->db->delete([
				'table' => $this->class_cfg['tables']['notes_url'], 
				'where' => [
					'conditions' => [[
						'field' => $this->class_cfg['arch']['notes_url']['id_note'], 
						'value' => $id_note
					]]
				]])
			){
				$success = true;
			}
		}
		
		return $success;
	}
	/**
	 * Returns the object event of the given note.
	 * @param string $id_note
	 */
	public function getEvent(string $id_note)
	{
		if ( $id_event = $this->db->selectOne(
			$this->class_cfg['tables']['notes_events'], $this->class_cfg['arch']['notes_events']['id_event'], [
				$this->class_cfg['arch']['notes_events']['id_note'] => $id_note
		])){
			if ( $event = $this->db->rselect([
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
				$this->_insert_notes_events($id_note, $id_event);
				$event['id_note'] = $id_note;
				return $event;
			}
		}	
		return null;
	}

	/**
	 * Updates the event relative to the given note.
	 * @param string $id_note
	 * @param Array $cfg
	 */
	public function updateEvent(string $id_note, array $cfg = []): ?bool
	{
		if ($this->_check_date($cfg['start'], $cfg['end'])){
			$event = $this->getEvent($id_note);
			if ( 
				(strtotime($cfg['start']) !== strtotime($event['start']) ) || 
				(strtotime($cfg['end']) !== strtotime($event['end']) )
			){
				$cfg['id_type'] = self::$_id_event;
				return $this->_events->edit($event['id'], $cfg);
			} else {
				return true;
			}
		}
		return false;
	}
	/**
	 * If it exists an event linked to the note it returns the start date.
	 * @param string $id_note
	 * @return string || null
	 */
	public function getStart($id_note): ?String
	{
		if ( $event = $this->getEvent($id_note) ){
			return $event['start'];
		}
		return null;
	}
	/**
	 * If it exists an event linked to the note it returns the end date.
	 * @param string $id_note
	 * @return string || null
	 */
	public function getEnd($id_note)
	{
		if ( $event = $this->getEvent($id_note) ){
			return $event['end'];
		}
		return null;
	}

	/**
	 * Inserts in bbn_events and bbn_notes_events the informations relative to the publication of the given note.
	 * @param string $id_note
	 * @param Array $cfg
	 * @return Boolean || null
	 */
	public function setEvent(string $id_note,array $cfg = null){
		if ( ($note = $this->_notes->get($id_note)) && ($this->_check_date($cfg['start'], $cfg['end']) )){
			$title = $note['title'];
			if ( empty($this->getEvent($id_note)) ){
				//if a type is not given it inserts the event as page
				if ( $id_event = $this->_events->insert([
					'name' => $title,
					'id_type' => self::$_id_event,
					'start' => $cfg['start'],
					'end' => $cfg['end'] ?: null,
				]) ){
					return $this->_insert_notes_events($id_note, $id_event);
				}
			} else {
				return $this->updateEvent($id_note, $cfg);
			}
		}
		return null;
	}

	/**
	 * Returns all notes that has a link with bbn_events.
	 * @return Array
	 */
	public function getFull() :?array
	{
		//select all events
		$events = $this->db->rselectAll($this->class_cfg['table']);
		$now = strtotime(date('Y-m-d H:i:s'));
		$res = [];
		if ( !empty($events) ){
			foreach ($events as $e){
				//takes events without end date of with end date > now
				if ( 
					array_key_exists($this->fields['start'], $e) && 
					( is_null($e['end']) || (strtotime($e['end']) > $now) )
				){
					//gets the note correspondant to the id_event and push it in $res
					$id_note = $this->db->selectOne($this->class_cfg['tables']['notes_events'],
						$this->class_cfg['arch']['notes_events']['id_note'], [
						$this->class_cfg['arch']['notes_events']['id_event'] => $e['id']
					]);
					if ( $this->hasUrl($id_note)){
						$note = $this->_notes->get($id_note);
						$note['url'] = $this->getUrl($id_note);
						$note['start'] = $this->getStart($id_note);
						$note['end'] = $this->getEnd($id_note);
						$res[] = $note;
					}
				}
			}
		}
		return $res;
	}
	/**
	 * Returns an array containing all the published notes.
	 * @return Array
	 */
	public function getFullPublished(): array
	{
		if ( ($full = $this->getFull()) ){
		  return array_filter($full, function($a){
				return $a['start'] !== null;
			});
		}
		return [];
	}

	/**
	 * If the note has a corresponding event in bbn_events and the date of start is before now, and the date of end if isset is after now and the note has an url it returns true
	 * @param string $id_note
	 * @return Boolean
	 */
	public function isPublished(string $id_note): ?bool
	{
		$now = strtotime(date('Y-m-d H:i:s'));
		if ( $event = $this->getEvent($id_note) ){
			if ( 
				isset($event['start']) && 
				( is_null($event['end']) || (strtotime($event['end']) > $now)) &&
				$this->hasUrl($id_note)
			){
				return true;
			}
		}
		return false;
	}

	/**
	 * Publish a note.
	 * @param string $id_note
	 * @param Array  $cfg
	 * @return Boolean
	 */
	public function publish(string $id_note,array $cfg) :?bool
	{
		if (
			 !$this->isPublished($id_note) && 
			 ( $note = $this->_notes->get($id_note) ) 
		){
			//if $url is given it updates the note_url
			if ( !empty($cfg['url'])){
				try {
					$this->setUrl($id_note, $cfg['url']);
				}
				catch ( \Exception $e ){
					return [
						'error' => $e->getMessage()
					];
				}
			}
			if ( !empty($this->hasUrl($id_note)) ){
				if ( empty($this->getEvent($id_note)) ){
					return $this->setEvent($id_note, [
						'start' => $cfg['start'] ?: date('Y-m-d H:i:s'), 
						'end' => $cfg['end'] ?: null,
						'id_type' => self::$_id_event ?: null
					]);
				} else if ( $this->getEvent($id_note) ){
					//case update
					return $this->updateEvent($id_note, [
						'start' => $cfg['start'] ?: date('Y-m-d H:i:s'), 
						'end' => $cfg['end'] ?: null,
						'id_type' => self::$_id_event ?: null
					]);
				}
			} else if ( empty($this->hasUrl($id_note)) ){
				return false;
			}
		}
		return false;
	}
	
	/**
	 * Unpublish a note.
	 * @param string $id_note
	 * @return Boolean
	 */
	public function unpublish(string $id_note): bool
	{
		if ( 
			($event = $this->getEvent($id_note)) && 
			$this->isPublished($id_note) 
		){
			if ( $this->updateEvent(
				$id_note, [
					'start' => null,
					'end' => null
				])
		  ){
				return $this->_remove_note_events($id_note, $event['id']);
			}
		}
		return false;
	}
	 	
}