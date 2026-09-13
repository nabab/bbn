<?php
namespace bbn\Note;

use bbn\Models\Cls\Table;

final class Feature extends Table
{
  protected static $default_class_cfg = [
    'table' => 'bbn_notes_features',
    'arch' => [
      'id' => 'id',
      'id_option' => 'id_option',
      'id_note' => 'id_note',
      'id_media' => 'id_media',
      'num' => 'num',
      'cfg' => 'cfg'
    ]
  ];
}

