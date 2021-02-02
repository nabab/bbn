<?php

namespace bbn\Models\Obj;

use bbn;

abstract class Entity {

  private $is_checked = false;

  protected $db;

  protected $table_name;

  protected $id_field = 'id';

  public function __construct(\bbn\Db $db, $id)
  {
    if ( $this->table_name && $db->count($this->table_name, [$this->id_field => $id]) ){
      $this->is_checked = true;
      $this->db = $db;
    }
  }

  public function check(): boolean
  {
    return $this->is_checked;
  }
}