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
    $f =& $this->fields;
    if (bbn\x::has_props($event, [$f['id_type'], $f['start']], true)) {
      if ( 
        !empty($event[$f['cfg']]) &&
        !\bbn\str::is_json($event[$f['cfg']])
      ){
        $event[$f['cfg']] = json_encode($event[$f['cfg']]);
      }
      if ( $this->db->insert($this->class_table, [
        $f['id_type'] => $event[$f['id_type']],
        $f['start'] => $event[$f['start']],
        $f['end'] => $event[$f['end']] ?? NULL,
        $f['name'] => $event[$f['name']] ?? NULL,
        $f['cfg'] => $event[$f['cfg']] ?? NULL
      ]) ){
        return $this->db->last_id();
      }
    }
    return null;
  }
  
  public function edit(string $id, array $event): ?int
  {
    if ( \bbn\str::is_uid($id) ){
      $f =& $this->fields;
      if ( array_key_exists($f['id'], $event) ){
        unset($event[$f['id']]);
      }
      if ( 
        !empty($event[$f['cfg']]) &&
        !\bbn\str::is_json($event[$f['cfg']])
      ){
        $event[$f['cfg']] = json_encode($event[$f['cfg']]);
      }
      return $this->db->update($this->class_table, [
        $f['id_type'] => $event[$f['id_type']],
        $f['start'] => $event[$f['start']],
        $f['end'] => $event[$f['end']] ?? NULL,
        $f['name'] => $event[$f['name']] ?? NULL,
        $f['cfg'] => $event[$f['cfg']] ?? NULL
      ], [
	      $f['id'] => $id
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
      $t =& $this;
      return $this->db->rselect([
        'table' => $this->class_table,
        'fields' => array_map(function($f) use($t){
          return $this->db->col_full_name($f, $t->class_table);
        }, $this->fields),
        'join' => [[
          'table' => $this->class_cfg['tables']['options'],
          'on' => [
            'conditions' => [[
              'field' => $this->db->col_full_name($this->class_cfg['arch']['options']['id'], $this->class_cfg['tables']['options']),
              'exp' => $this->fields['id_type']
            ]]
          ]
        ]],
        'where' => [
          $this->db->col_full_name($this->fields['id'], $this->class_table) => $id
        ]
      ]);
    }
    return null;
  }
  
}
