<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 05/11/2016
 * Time: 18:10
 */

namespace bbn\Models\Tts;

use bbn;


trait Dbattached
{

  public function exists($id){
    return $this->db->count($this->class_table, [
      $this->class_cfg['arch'][$this->class_table]['id'] => $id
    ]) ? true : false;
  }


}