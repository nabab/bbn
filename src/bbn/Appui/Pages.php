<?php
namespace bbn\Appui;
use bbn;

class Pages extends bbn\Models\Cls\Db
{

  use
    bbn\Models\Tts\References,
    bbn\Models\Tts\DbActions;


  protected static
    /** @var array */
    $default_class_cfg = [
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
    $this->setError($err);
    die($this->getError());
  }

  private function getIdNote($url){
    return $this->db->selectOne($this->class_cfg['table'], $this->class_cfg['arch']['pages']['id_note'], [
      $this->class_cfg['arch']['pages']['url'] => $url
    ]);
  }

  public function __construct(bbn\Db $db){
    parent::__construct($db);
    self::_init_class_cfg(self::$default_class_cfg);
    $this->opt = bbn\Appui\Option::getInstance();
    $this->opt_id = $this->opt->fromRootCode('pages', 'types', 'note', 'appui');
    $this->notes = new bbn\Appui\Note($this->db);
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
    if ( $id_note = $this->getIdNote($url) ){
      return $this->notes->insertVersion($id_note, $title, $content);
    }
  }

  public function delete($url){
    if ( $id_note = $this->getIdNote($url) ){
      return $this->notes->remove($id_note);
    }
  }

  public function get($url, $version = false){
    if ( $id_note = $this->getIdNote($url) ){
      return $this->notes->get($id_note, $version );
    }
  }
  
}
