<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 03/11/2014
 * Time: 16:54
 */

namespace bbn\Entities\Junctions;

use bbn\Db;
use bbn\X;
use bbn\Models\Cls\Nullall;
use bbn\Entities\Models\IdentityTable;
use bbn\Entities\Entity;
use bbn\Appui\Note;


class UauthLink extends IdentityTable
{

  protected static $default_class_cfg = [
    'table' => 'bbn_identities_uauth',
    'tables' => [
      'identities_uauth' => 'bbn_identities_uauth'
    ],
    'arch' => [
      'identities_uauth' => [
        'id' => 'id',
        'id_identity' => 'id_identity',
        'id_uauth' => 'id_uauth'
      ],
    ]
  ];


}
