<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 14/04/2016
 * Time: 20:38
 */

 //the notes inserted with appui/notes have to be type 'pages'
namespace bbn\appui;
use bbn;

class cms extends bbn\models\cls\db
{
	use
    bbn\models\tts\dbconfig;

	private
    $_notes,
    $_options,
		$_events;
	
	protected static  
		$_defaults = [
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

	private static function _set_id_event($id) {
		self::$_id_event = $id;
	}
	private function _exist_published_url(){
		
	}
	/**
	 * removes the row corresponding to the given arguments from bbn_notes_events
	 */
	private function _remove_note_events($id_note, $id_event): ?bool
	{
		return $this->db->delete(
			$this->class_cfg['tables']['notes_events'], [
				$this->class_cfg['arch']['notes_events']['id_event'] => $id_event,
				$this->class_cfg['arch']['notes_events']['id_note'] => $id_note
			]
		);
		return false;
	}

	/**
	 * if the row corresponding to the given arguments is not in the table bbn_notes_events it inserts the row
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
	public function __construct(bbn\db $db, $notes = null){
		parent::__construct($db);
		$this->_init_class_cfg();
		$this->_events = new bbn\appui\events($this->db);
		$this->_options = bbn\appui\options::get_instance();
		if (!self::$_id_event) {
			$id = $this->_options->from_code('publication', 'events', 'appui');
			self::_set_id_event($id);
		}
		if ( $notes === null ){
			$this->_notes = new bbn\appui\notes($this->db);
		}
		else{
			$this->_notes = $notes;
		}
		
	}

	/**
	 * returns the note + its URL
	 */
	public function get(string $url)
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
			$res['url'] = $this->get_url($id_note);
			$res['start'] = $this->get_start($id_note);
			$res['end'] = $this->get_end($id_note);
		}
		return $res;
	}

	/**
	 * if the argument type is not given it returns all the notes, else returns the notes of the given type
	 */
	public function get_all(int $limit = 50, int $start = 0): array 
	{
		$pages = $this->_notes->get_by_type($this->_options->from_code('pages', 'types', 'notes', 'appui'), false,$limit, $start);
		$tmp = array_map(function($a){
			$a['is_published'] = $this->is_published($a['id_note']);
			$a['url'] = $this->has_url($a['id_note']) ? $this->get_url($a['id_note']) : '';
			$a['type'] = 'pages';
			$a['start'] = $this->get_start($a['id_note']);
			$a['end'] = $this->get_end($a['id_note']);
			return $a;
		}, $pages); 
		return $tmp; 
	}

 /**
 * if the given url correspond to a published note returns the id
 */
	public function get_by_url(string $url)
	{
		if ( $id_note = $this->db->select_one([
			'table' => $this->class_cfg['tables']['notes_url'], 
			'fields' => [$this->class_cfg['arch']['notes_url']['id_note']],
			'where' => [
				'conditions' => [[
					'field' => $this->class_cfg['arch']['notes_url']['url'],
					'value' => $url
				]]
			]
		])){
			if ( $this->is_published($id_note) ){
				return $id_note;
			}
		}
		return null;
	}
	


	/**
	 * delete the given note and unpublish it if published
	 */
	public function delete(string $id_note)
	{
		if ( $note = $this->_notes->get($id_note) ){
			if ( $this->get_url($id_note) ){
				$this->remove_url($id_note);
			}
			if(
				$this->_notes->remove($note['id']) 
			){
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Returns true if the note is linked to an url
	 * 
	 */
	public function has_url(string $id_note) :?bool
	{
		if ( $this->db->select_one(
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
	 * Returns the url of the note
	 */
	public function get_url(string $id_note) :?string
	{
		if ( $this->has_url($id_note) ){
			$note = $this->_notes->get($id_note);
			return $this->db->select_one([
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
	 * 
	 * sets the url for the note if it doesn't exist or update the url and if doesn't exists a published note with that url
	 */
	public function set_url($id_note, $url): ?bool
	{	
		$success = false;
		$idx = \bbn\x::find($this->get_full_published(), ['url' => $url]);
		if ( $note = $this->_notes->get($id_note)  && empty($idx) ){
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
			}
			else {
				$success = $this->db->update(
					$this->class_cfg['tables']['notes_url'],
					[$this->class_cfg['arch']['notes_url']['url'] => $url],
					[
						$this->class_cfg['arch']['notes_url']['id_note'] => $id_note
					]
				);
				
			}
		}
		return $success;
	}

	/**
	 * removes the url corresponding to the given id_note from bbn_notes_url
	 */
	public function remove_url(string $id_note): bool
	{
		$success = false;
		if ( $this->is_published($id_note) ){
			$this->unpublish($id_note);
		} 
		if ( $note = $this->_notes->get($id_note)){
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
	 * returns the object event
	 */
	public function get_event(string $id_note)
	{
		if ( $id_event = $this->db->select_one(
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

	public function update_event($id_note, $cfg = []): ?bool
	{
		if ($this->_check_date($cfg['start'], $cfg['end'])){
			$event = $this->get_event($id_note);
			if ( 
				(strtotime($cfg['start']) !== strtotime($event['start']) ) || 
				(strtotime($cfg['end']) !== strtotime($event['end']) )
			){
				$cfg['id_type'] = self::$_id_event;
				$note = $this->_notes->get($id_note);
				return $this->_events->edit($event['id'], $cfg);
			}
			else{
				return true;
			}
		}
	}

	/**
	 * if it exists an event linked to the note it returns the start date
	 */
	public function get_start($id_note)
	{
		if ( $event = $this->get_event($id_note) ){
			return $event['start'];
		}
		return null;
	}
	

	/**
	 * if it exists an event linked to the note it returns the end date
	 */
	public function get_end($id_note)
	{
		if ( $event = $this->get_event($id_note) ){
			return $event['end'];
		}
		return null;
	}

	private function _check_date($start, $end): bool
	{
		if ( isset($start) ){
			if ( !isset($end) || strtotime($end) > strtotime($start) ){
				return true;
			}
		}
		else {
			return true;
		}
		return false;
	}
	
	public function set_event(string $id_note,array $cfg = null){
		if ( $note = $this->_notes->get($id_note) && ($this->_check_date($cfg['start'], $cfg['end']) )){
			$title = $note['title'];
			if ( empty($this->get_event($id_note)) ){
				//if a type is not given it inserts the event as page
				if ( $id_event = $this->_events->insert([
					'name' => $title,
					'id_type' => $cfg['id_type'] ?: self::$_id_event,
					'start' => $cfg['start'],
					'end' => $cfg['end'] ?: null,
				]) ){
					return $this->_insert_notes_events($id_note, $id_event);
				}
			}
			else {
				return $this->update_event($id_note, $cfg);
			}
		}
		return null;
		//add the row in notes_events
		//set url al publish
	}

	/**
	 * Returns all notes that has a link with bbn_events
	 */
	public function get_full() :?array
	{
		//select all events
		$events = $this->db->rselect_all($this->class_cfg['table']);
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
					$id_note = $this->db->select_one($this->class_cfg['tables']['notes_events'],
						$this->class_cfg['arch']['notes_events']['id_note'], [
						$this->class_cfg['arch']['notes_events']['id_event'] => $e['id']
					]);
					if ( $this->has_url($id_note)){
						$note = $this->_notes->get($id_note);
						$note['url'] = $this->get_url($id_note);
						$note['start'] = $this->get_start($id_note);
						$note['end'] = $this->get_end($id_note);
						$res[] = $note;
					}
				}
			}
		}
		return $res;
	}
	public function get_full_published(){
		if ( ($full = $this->get_full()) ){
		  return array_filter($full, function($a){
				return $a['start'] !== null;
			});
		}
		return [];
	}

	/**
	 * if the note has a corresponding event in bbn_events and the date of start is before now, and the date of end if isset is after now and the note has an url it returns true
	 * 
	 */
	public function is_published(string $id_note): ?bool
	{
		$now = strtotime(date('Y-m-d H:i:s'));
		if ( $event = $this->get_event($id_note) ){
			if ( 
				isset($event['start']) && 
				( is_null($event['end']) || (strtotime($event['end']) > $now)) &&
				$this->has_url($id_note)
			){
				return true;
			}
		}
		return false;
	}

	/**
	 * publish a note
	 */
	public function publish(string $id_note,array $cfg) :?bool
	{
		if (
			 !$this->is_published($id_note) && 
			 ( $note = $this->_notes->get($id_note) ) 
		){
			//if $url is given it updates the note_url
			if ( !empty($cfg['url'])){
				$this->set_url($id_note, $cfg['url']);
			}
			if ( !empty($this->has_url($id_note)) ){
				if ( empty($this->get_event($id_note)) ){
					return $this->set_event($id_note, [
						'start' => $cfg['start'] ?: date('Y-m-d H:i:s'), 
						'end' => $cfg['end'] ?: null,
						'id_type' => self::$_id_event ?: null
					]);
				}
				//case update
				else if ( $event = $this->get_event($id_note) ){
					return $this->update_event($id_note, [
						'start' => $cfg['start'] ?: date('Y-m-d H:i:s'), 
						'end' => $cfg['end'] ?: null,
						'id_type' => self::$_id_event ?: null
					]);
				}
			}
			else if ( empty($this->has_url($id_note)) ){
				return false;
			}
		}
		return false;
	}
	
	/**
	 * unpublish a note
	 */
	public function unpublish(string $id_note): bool
	{
		if ( 
			($event = $this->get_event($id_note)) && 
			$this->is_published($id_note) 
		){
			if ( $this->update_event(
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

