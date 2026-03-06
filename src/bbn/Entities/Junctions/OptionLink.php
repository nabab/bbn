<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 03/11/2014
 * Time: 16:54
 */

namespace bbn\Entities\Junctions;

use bbn\Entities\Models\EntityTable;


class OptionLink extends EntityTable
{

  protected static $default_class_cfg = [
    'table' => 'bbn_entities_options',
    'tables' => [
      'entities_options' => 'bbn_entities_options'
    ],
    'arch' => [
      'entities_options' => [
        'id' => 'id',
        'id_entity' => 'id_entity',
        'id_type' => 'id_type',
        'id_option' => 'id_option'
      ],
    ]
  ];


}
