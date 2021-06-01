<?php

namespace bbn;

class ApiUser extends User
{


  /**
   * ApiUser constructor.
   *
   * @param int   $id_group The id_group value that will be checked as a condition when logging in.
   * @param Db    $db
   * @param array $params
   * @param array $cfg
   */
  public function __construct(int $id_group, Db $db, array $params = [], array $cfg = [])
  {
    self::$default_class_cfg['conditions'] = [
      'id_group' => $id_group
    ];

    parent::__construct($db, $params, $cfg);
  }


}
