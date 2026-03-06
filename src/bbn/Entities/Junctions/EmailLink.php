<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 03/11/2014
 * Time: 16:54
 */

namespace bbn\Entities\Junctions;

use bbn\Entities\Models\EntityTable;


class EmailLink extends EntityTable
{

  protected static $default_class_cfg = [
    'table' => 'bbn_entities_emails',
    'tables' => [
      'entities_emails' => 'bbn_entities_emails'
    ],
    'arch' => [
      'entities_emails' => [
        'id' => 'id',
        'id_entity' => 'id_entity',
        'id_email' => 'id_email'
      ],
    ]
  ];


}
