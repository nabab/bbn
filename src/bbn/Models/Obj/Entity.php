<?php

namespace bbn\Models\Obj;

use bbn\Db;

abstract class Entity {

  private bool $is_checked = false;

  protected Db $db;

  protected string $table_name;

  protected string $id_field = 'id';

  public function __construct(Db $db, $id)
  {
    if ( $this->table_name && $db->count($this->table_name, [$this->id_field => $id]) ){
      $this->is_checked = true;
      $this->db = $db;
    }
  }

  public function check(): bool
  {
    return $this->is_checked;
  }
}