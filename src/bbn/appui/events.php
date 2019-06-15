<?php
namespace bbn\appui;
use bbn;

class events extends bbn\models\cls\db
{

  use
    bbn\models\tts\dbconfig;

  protected static
    /** @var array */
    $_defaults = [
      'table' => 'bbn_events',
      'tables' => [
        'events' => 'bbn_events',
        'options' => 'bbn_options'
      ],
      'arch' => [
        'events' => [
          'id' => 'id',
          'id_type' => 'id_type',
					'start' => 'start',
          'end' => 'end',
          'name' => 'name',
					'cfg' => 'cfg'
        ],
        'options' => [
          'id' => 'id'
        ]
      ]
    ];

  private
    $opt,
    $usr,
    $opt_id;

  public function __construct(bbn\db $db){
    parent::__construct($db);
    $this->_init_class_cfg();
    $this->opt = bbn\appui\options::get_instance();
    $this->usr = bbn\user::get_instance();
    //$this->opt_id = $this->opt->from_root_code('events', 'appui');
  }

  public function insert(array $event): ?string
  {
    if ( 
      !empty($event[$this->fields['id_type']]) &&
      !empty($event[$this->fields['start']])
    ){
      if ( 
        !empty($event[$this->fields['cfg']]) &&
        !\bbn\str::is_json($event[$this->fields['cfg']])
      ){
        $event[$this->fields['cfg']] = json_encode($event[$this->fields['cfg']]);
      }
      if ( $this->db->insert($this->class_table, [
        $this->fields['id_type'] => $event[$this->fields['id_type']],
        $this->fields['start'] => $event[$this->fields['start']],
        $this->fields['end'] => $event[$this->fields['end']] ?? NULL,
        $this->fields['name'] => $event[$this->fields['name']] ?? NULL,
        $this->fields['cfg'] => $event[$this->fields['cfg']] ?? NULL
      ]) ){
        return $this->db->last_id();
      }
    }
    return null;
  }
  
  public function edit(string $id, array $event): ?int
  {
    if ( \bbn\str::is_uid($id) ){
      if ( array_key_exists($this->fields['id'], $event) ){
        unset($event[$this->fields['id']]);
      }
      if ( 
        !empty($event[$this->fields['cfg']]) &&
        !\bbn\str::is_json($event[$this->fields['cfg']])
      ){
        $event[$this->fields['cfg']] = json_encode($event[$this->fields['cfg']]);
      }
      return $this->db->update($this->class_table, [
        $this->fields['id_type'] => $event[$this->fields['id_type']],
        $this->fields['start'] => $event[$this->fields['start']],
        $this->fields['end'] => $event[$this->fields['end']] ?? NULL,
        $this->fields['name'] => $event[$this->fields['name']] ?? NULL,
        $this->fields['cfg'] => $event[$this->fields['cfg']] ?? NULL
      ], [
	      $this->fields['id'] => $id
      ]);
    }
    return null;
  }

  public function delete(string $id): bool
  {
    if ( \bbn\str::is_uid($id) ){
      if ( $this->db->delete($this->class_table, [$this->fields['id'] => $id]) ){
        return true;
      }
    }
    return false;
  }

  public function get(string $id): ?array
  {
    if ( \bbn\str::is_uid($id) ){
      return $this->db->rselect([
        'table' => $this->class_table,
        'fields' => [],
        'join' => [[
          'table' => $this->class_cfg['tables']['options'],
          'on' => [
            'conditions' => [[
              'field' => $this->class_cfg['arch']['options']['id'],
              'exp' => $this->fields['id_type']
            ]]
          ]
        ]],
        'where' => [
          $this->fields['id'] => $id
        ]
      ]);
    }
    return null;
  }
  
}
