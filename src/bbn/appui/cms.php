<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 14/04/2016
 * Time: 20:38
 */


//NON POSSONO ESSERE PUBBLICATE SE NON HANNO URL E NON SI PUò CAMBIARE DATA DELL'EVENTO SE NON C'è URL 
namespace bbn\appui;
use bbn;

class cms extends bbn\models\cls\db
{
	use
    bbn\models\tts\dbconfig;

	private
    $notes,
    $options,
		$events;
	
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

	public function __construct(bbn\db $db, $notes = null){
		parent::__construct($db);
		$this->_init_class_cfg();
		$this->events = new bbn\appui\events($this->db);
		$this->options = bbn\appui\options::get_instance();
		if ( $notes === null ){
			$this->notes = new bbn\appui\notes($this->db);
		}
		else{
			$this->notes = $notes;
		}
	}

	//if the note does not exists with type pages and given url it creates the note 
	//insert a note of type pages - the note will be the parent of future posts
	public function insert( string $title, string $url, string $type = 'pages', string $content = '' , string $start = null, string $end = null, string $type_event = 'PAGE',string $alias = null)
	{
		$type_page = $this->options->from_code('pages', 'types', 'notes', 'appui');
		if ( empty($this->get($title, $url))){
			$note = $this->notes->insert($title, $content, $type_page, false, false, null, $alias);
			$res = [];
			if ( empty($this->has_url($note)) ){
				if ( $this->set_url($note, $url) ){
					$res = $this->notes->get($note);
					$res['url'] = $this->get_url($note);
				}
			}
			if ( empty($this->get_event($note)) ){
				$code = $this->options->from_code($type_event, 'evenements');
				if ( $event = $this->set_event($note, [
							'name' => $title,
							'id_type' => $code,
							'start' => $start,
							'end' => $end ?: null,
						])
				){
					$res['event'] = $event;
				}
			}
			return $res;
		}
		return false;
	}
	
	public function edit( string $title, string $url, string $type = 'pages', string $content = '' , string $start = null, string $end = null, string $type_event = 'PAGE',string $alias = null)
	{
		$res = [];
		if ( $note = $this->get($title, $url) ){
			$this->notes->insert_version($note['id'], $title, $content);
			$this->set_url($note['id'], $url);
			$res = $this->notes->get($note['id']);
			$code = $this->options->from_code($type_event, 'evenements');
			if ( empty($this->get_event($note['id'])) ){
				$this->set_event($note['id'], [
					'name' => $title,
					'id_type' => $code,
					'start' => $start,
					'end' => $end,
				]);
			}
			else{
				$this->update_event($note['id'], [
					'name' => $title,
					'id_type' => $code,
					'start' => $start,
					'end' => $end,
				]);
			}
			$res['event'] = $this->get_event($note['id']);
			
		}
	}
	

	/**
	 * returns the object of the note
	 */
	public function get(string $title, string $url)
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
		if ( !empty($id_note) && $note = $this->notes->get($id_note)){
			$res = $note;
			$res['url'] = $this->get_url($id_note);
		}
		return $res;
	}

	/**
	 * if the argument type is not given it returns all the notes, else returns the notes of the given type
	 */
	function get_all(): array 
	{
		$pages = $this->notes->get_by_type($this->options->from_code('pages', 'types', 'notes', 'appui'));
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
	 * returns the id_note from the url and the type of the note ('post', 'page')
	 */
	function get_by_url(string $url)
	{
		return $this->db->select_one([
			'table' => $this->class_cfg['tables']['notes_url'], 
			'fields' => [$this->class_cfg['arch']['notes_url']['id_note']],
			'where' => [
				'conditions' => [[
					'field' => $this->class_cfg['arch']['notes_url']['url'],
					'value' => $url
				]]
			]
		]);
	}
	


	/**
	 * delete the given note and unpublish it if published
	 */
	public function delete(string $id_note)
	{
		if ( $note = $this->notes->get($id_note) ){
			$this->unpublish($note['id']);
			if(
				$this->remove_url($note['id']) &&
				$this->notes->remove($note['id']) 
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
			$note = $this->notes->get($id_note);
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
	 * sets the url for the note if it doesn't exist or update the url
	 */
	public function set_url($id_note, $url): ?bool
	{	
		$success = false;
		if($note = $this->notes->get($id_note)){
			if ( empty($this->db->rselect([
				'table' => $this->class_cfg['tables']['notes_url'], 
				'fields' => [$this->class_cfg['arch']['notes_url']['url']],
				'where' => [
					'conditions' => [[
						'field' => $this->class_cfg['arch']['notes_url']['id_note'],
						'value' => $id_note
					]]
				]
				])['url']
				)
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
			return $success;
		}
		
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
		if ( $note = $this->notes->get($id_note)){
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
				$this->insert_notes_events($id_note, $id_event);
				$event['id_note'] = $id_note;
				return $event;
			}
		}	
		return null;
	}

	public function update_event($id_note, $cfg = []): ?bool
	{
		$note = $this->notes->get($id_note);
		$event = $this->get_event($id_note);
		return $this->db->update(
			$this->class_cfg['table'],
			[ 
				$this->fields['id_parent'] => ((!empty($cfg['id_parent']) && ($cfg['id_parent'] !== $event['id_parent']) ) ? $cfg['id_parent'] : $event['id_parent']),
				$this->fields['id_type'] => ((!empty($cfg['id_type']) && ($cfg['id_type'] !== $event['id_type']) ) ? $cfg['id_type'] : $event['id_type']),
				$this->fields['start'] => ((!empty($cfg['start']) && ($cfg['start'] !== $event['start']) ) ? $cfg['start'] : $event['start']),
				$this->fields['end'] => ((!empty($cfg['end']) && ($cfg['end'] !== $event['end']) ) ? $cfg['end'] : $event['end'])
			],
			[
				$this->fields['id'] => $event['id']
			]	
		);
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

	public function set_event(string $id_note,array $cfg = null){
		if ( $note = $this->notes->get($id_note) ){
			$title = $note['title'];
			if ( empty($this->get_event($id_note)) ){
				//if a type is not given it inserts the event as page
				$type_event = $this->options->from_code('PAGE', 'evenements');
				if ( $id_event = $this->events->insert([
					'name' => $title,
					'id_type' => $cfg['id_type'] ?: $type_event,
					'start' => $cfg['start'],
					'end' => $cfg['end'] ?: null,
				]) ){
					return $this->insert_notes_events($id_note, $id_event);
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
	 * Returns published notes
	 */
	public function get_full_published() :?array
	{
		//select all events
		$events = $this->db->rselect_all($this->class_cfg['table']);
		$now = strtotime(date('Y-m-d H:i:s'));
		$res = [];
		if ( !empty($events) ){
			foreach ($events as $e){
				//takes events without end date of with end date > now
				if ( 
					isset($e['start']) && 
					( is_null($e['end']) || (strtotime($e['end']) > $now) )
				){
					//gets the note correspondant to the id_event and push it in $res
					$id_note = $this->db->select_one($this->class_cfg['tables']['notes_events'],
						$this->class_cfg['arch']['notes_events']['id_note'], [
						$this->class_cfg['arch']['notes_events']['id_event'] => $e['id']
					]);
					if ( $this->has_url($id_note)){
						$res[] = $this->notes->get($id_note);
					}
				}
			}
		}
		return $res;
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
	 * removes the row corresponding to the given arguments from bbn_notes_events
	 */
	public function remove_note_events($id_note, $id_event): ?bool
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
	public function insert_notes_events($id_note, $id_event): ?bool
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
	 * publish a note
	 */
	public function publish(string $id_note, string $url = '', $start = null, $end = null, string $type_event = null, $id_parent = null) :?bool
	{
		if (
			 !$this->is_published($id_note) && 
			 ( $note = $this->notes->get($id_note) ) 
		){
			//if $url is given it updates the note_url
			if ( !empty($url)){
				$this->set_url($id_note, $url);
			}
			if ( !empty($this->has_url($id_note)) ){
				$url = $this->get_url('id_note');
				if ( empty($this->get_event($id_note)) ){
					return $this->set_event($id_note, [
						'start' => $start ?: date('Y-m-d H:i:s'), 
						'end' => $end ?: null,
						'id_type' => $type_event ?: null
					]);
				}
				//case update
				else if ( $event = $this->get_event($id_note) ){
					return $this->db->update([
						$this->class_cfg['table'],
						[
							$this->fields['id_parent'] => (!empty($id_parent) && ($id_parent !== $event['id_parent']) ) ? $id_parent : $event['id_parent'],
							$this->fields['id_type'] => (!empty($type_event) && ($type_event !== $event['id_type']) ) ? $type_event : $event['id_type'],
							$this->fields['start'] => (!empty($start) && ($start !== $event['start']) ) ? $start : $event['start'],
						],
						[
							$this->fields['id'] => $event['id']
						]	
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

			if ( $this->db->update(
				$this->class_cfg['table'], 
				[
					$this->fields['start'] => null,
					$this->fields['end'] => null
				],
				[
					$this->fields['id'] => $event['id']
				])
		  ){
				return $this->remove_note_events($id_note, $event['id']);
			}
		}
		return false;
	}
	


	public function change_event_type(){
		
	}
	 	
}

