<?php

namespace bbn\Entities\Tables;

use bbn\Db;
use bbn\X;
use bbn\Entities\Models\Entities;
use bbn\Models\Tts\DbActions;
use bbn\Entities\Models\EntityTable;

class Follower extends EntityTable
{
  use DbActions;

  protected static $default_class_cfg = [
    'table' => 'bbn_followers',
    'tables' => [
      'followers' => 'bbn_followers'
    ],
    'arch' => [
      'followers' => [
        'id' => 'id',
        'id_entity' => 'id_entity',
        'id_user' => 'id_user'
      ],
    ]
  ];

  /**
   * Returns an array of users id following the adherent.
   *
   * @return array|null
   */
  public function followedBy(): ?array
  {
    if ($this->check()) {
      return $this->dbTraitSelectValues(
        $this->fields['id_user'],
        []
      );
    }

    return null;
  }


  /**
   * Adds a user to the adherent's followers and returns true on success.
   *
   * @param string $id_user
   * @return boolean|null
   */
  public function addFollower(string $id_user): ?bool
  {
    if ($this->check()) {
      return (bool)$this->db->insertIgnore(
        $this->class_cfg['table'], [
          $this->fields['id_entity'] => $this->getId(),
          $this->fields['id_user'] => $id_user
        ]
      );
    }

    return null;
  }


  /**
   * Removes a user from the adherent's followers and returns true on success.
   *
   * @param string $id_user
   * @return boolean|null
   */
  public function removeFollower($id_user): ?bool
  {
    if ($this->check()) {
      return (bool)$this->db->deleteIgnore(
        $this->class_cfg['table'], [
          $this->fields['id_entity'] => $this->getId(),
          $this->fields['id_user'] => $id_user
        ]
      );
    }

    return null;
  }



}