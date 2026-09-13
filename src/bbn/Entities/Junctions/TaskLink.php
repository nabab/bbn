<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 03/11/2014
 * Time: 16:54
 */

namespace bbn\Entities\Junctions;

use bbn\Entities\Models\EntityTable;


class TaskLink extends EntityTable
{

  protected static $default_class_cfg = [
    'table' => 'bbn_entities_tasks',
    'tables' => [
      'entities_tasks' => 'bbn_entities_tasks'
    ],
    'arch' => [
      'entities_tasks' => [
        'id' => 'id',
        'id_entity' => 'id_entity',
        'id_task' => 'id_task'
      ],
    ]
  ];


}
