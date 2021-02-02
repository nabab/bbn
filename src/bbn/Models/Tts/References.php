<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 05/11/2016
 * Time: 02:39
 */

namespace bbn\Models\Tts;

use bbn;


trait References
{
  private
    $references,
    $references_select = '',
    $references_join = '';

  protected $db;

  private function _get_references(){
    if ( \is_null($this->references) ){
      if ( $refs = $this->db->findRelations('bbn_tasks.id') ){
        $this->references = array_filter($refs, function($a, $k){
          return strpos($k, 'bbn_tasks') !== 0;
        }, ARRAY_FILTER_USE_BOTH);
      }
      if ( empty($this->references) ){
        $this->references = false;
      }
      else{
        foreach ( $this->references as $table => $ref ){
          foreach ( $ref['refs'] as $j => $r ){
            $this->references_select = empty($this->references_select) ?
              $this->db->cfn($j, $table, 1) :
              "IFNULL(".$this->references_select.", ".$this->db->cfn($j, $table, 1).")";

            $this->references_join .= "LEFT JOIN ".$this->db->tfn($table, 1).PHP_EOL.
              "ON ".$this->db->cfn($ref['column'], $table, 1)." = bbn_tasks.id".PHP_EOL;
          }
        }
        if ( !empty($this->references_select) ){
          $this->references_select .= " AS reference,".PHP_EOL;
        }
      }
    }
    return $this->references;
  }

  public function getReferences(){
    $this->_get_references();
    return [
      'select' => $this->references_select,
      'join' => $this->references_join
    ];
  }

}