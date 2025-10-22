<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 27/02/2018
 * Time: 02:28
 */

namespace bbn\User;
use bbn;

/**
 * Class admin
 * @package bbn\User
 * Way to manipulate and access user tables without using user as argument (without auth, for CLI purpose)
 */
class Users extends bbn\Models\Cls\Db
{

  public function onlineCount(int $minutes = 2): int
  {
      return $this->db->getOne("
SELECT COUNT(DISTINCT bbn_users.id)
FROM bbn_users
	JOIN bbn_users_sessions
    ON id_user = bbn_users.id
    AND opened = 1
    AND last_activity > (NOW() - INTERVAL $minutes MINUTE)");
  }

  public function onlineList(int $minutes = 2): array
  {
    return $this->db->getColArray("
SELECT DISTINCT bbn_users.id
FROM bbn_users
	JOIN bbn_users_sessions
    ON id_user = bbn_users.id
    AND opened = 1
    AND last_activity > (NOW() - INTERVAL $minutes MINUTE)");
  }

  public function fullOnlineList(int $minutes = 2): array
  {
    $res = [];
    if ( $users = $this->db->getRows("
SELECT bbn_users.*
FROM bbn_users
	JOIN bbn_users_sessions
    ON id_user = bbn_users.id
    AND opened = 1
    AND last_activity > (NOW() - INTERVAL $minutes MINUTE)
    GROUP BY bbn_users.id")
    ){
      foreach ( $users as $user ){
        $res[] = [
          'id' => $user['id'],
          'name' => $user['nom']
        ];
      }
    }
    return $res;
  }
}
