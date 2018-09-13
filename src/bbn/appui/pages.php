<?php
namespace bbn\appui;
use bbn;

class pages extends bbn\models\cls\db
{

  use
    bbn\models\tts\references,
    bbn\models\tts\dbconfig;


  protected static
    /** @var array */
    $_defaults = [
      'table' => 'bbn_notes_pages',
      'tables' => [
        'pages' => 'bbn_notes_pages'
      ],
      'arch' => [
        'pages' => [
          'url' => 'url',
          'id_note' => 'id_note'
        ]
      ],
      'errors' => [
        1 => 'Note inserting',
        2 => 'Page inserting'
      ]
    ];

  private
    $opt,
    $opt_id,
    $notes;

  private function error($err){
    $this->set_error($err);
    die($this->get_error());
  }

  private function get_id_note($url){
    return $this->db->select_one($this->class_cfg['table'], $this->class_cfg['arch']['pages']['id_note'], [
      $this->class_cfg['arch']['pages']['url'] => $url
    ]);
  }

  public function __construct(bbn\db $db){
    parent::__construct($db);
    self::_init_class_cfg(self::$_defaults);
    $this->opt = bbn\appui\options::get_instance();
    $this->opt_id = $this->opt->from_code('pages', 'types', 'notes', 'appui');
    $this->notes = new bbn\appui\notes($this->db);
  }

  public function insert($url, $content, $title = ''){
    if ( $id_note = $this->notes->insert($title, $content, $this->opt_id) ){
      if ( !$this->db->insert($this->class_cfg['table'], [
        $this->class_cfg['arch']['pages']['url'] => $url,
        $this->class_cfg['arch']['pages']['id_note'] => $id_note
      ]) ){
        $this->error($this->class_cfg['errors'][2]);
      }
      return true;
    }
    else {
      $this->error($this->class_cfg['errors'][1]);
    }
  }

  public function update($url, $content, $title = ''){
    if ( $id_note = $this->get_id_note($url) ){
      return $this->notes->insert_version($id_note, $title, $content);
    }
  }

  public function delete($url){
    if ( $id_note = $this->get_id_note($url) ){
      return $this->notes->remove($id_note);
    }
  }

  public function get($url, $version = false){
    if ( $id_note = $this->get_id_note($url) ){
      return $this->notes->get($id_note, $version );
    }
  }
  
}
