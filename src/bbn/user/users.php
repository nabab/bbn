<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 27/02/2018
 * Time: 02:28
 */

namespace bbn\user;
use bbn;

/**
 * Class admin
 * @package bbn\user
 * Way to manipulate and access user tables without using user as argument (without auth, for CLI purpose)
 */
class users extends bbn\models\cls\db
{

  /**
   * @param $token
   * @return string
   */
  public function get_user_from_token($token_id):? string
  {
    if ( bbn\str::is_uid($token_id) ){
      return $this->db->get_one(<<<MYSQL
  SELECT bbn_users.id
  FROM bbn_users_tokens
    JOIN bbn_users_sessions
      ON bbn_users_tokens.id_session = bbn_users_sessions.id
    JOIN bbn_users
      ON bbn_users.id = bbn_users_sessions.id_user
  WHERE bbn_users_tokens.id = ?
MYSQL
        , hex2bin($token_id));
    }
    return null;
  }

  /**
   * @param $id_user
   * @return array
   */
  public function get_user_tokens($id_user):? array
  {
    if ( bbn\str::is_uid($id_user) ){
      $sql = <<<MYSQL
  SELECT bbn_users_tokens.id, bbn_users_tokens.content
  FROM bbn_users
    JOIN bbn_users_sessions
      ON bbn_users_sessions.id_user = bbn_users.id
    JOIN bbn_users_tokens
      ON bbn_users_tokens.id_session = bbn_users_sessions.id
  WHERE bbn_users.id = ?
  AND bbn_users_sessions.opened = 1
MYSQL;
      return $this->db->get_rows($sql, hex2bin($id_user));
    }
    return null;
  }

  /**
   * @return array
   */
  public function get_old_tokens(): array
  {
    $sql = <<<MYSQL
SELECT bbn_users_tokens.id, id_user
FROM bbn_users_tokens
  JOIN bbn_users_sessions
    ON bbn_users_sessions.id = id_session
WHERE `last` < (UNIX_TIMESTAMP() - 600)
MYSQL;
    return $this->db->get_rows($sql);
  }

  public function online_count(int $minutes = 2): int
  {
    if ( $this->auth ){
      return $this->db->get_one("
SELECT COUNT(DISTINCT bbn_users.id)
FROM bbn_users
	JOIN bbn_users_sessions
    ON id_user = bbn_users.id
    AND opened = 1
    AND last_activity > (NOW() - INTERVAL $minutes MINUTE)
    ");
    }
    return 0;
  }

  public function online_list(int $minutes = 2): array
  {
    if ( $this->auth ){
      return $this->db->get_col_array("
SELECT DISTINCT bbn_users.id
FROM bbn_users
	JOIN bbn_users_sessions
    ON id_user = bbn_users.id
    AND opened = 1
    AND last_activity > (NOW() - INTERVAL $minutes MINUTE)
    ");
    }
    return [];
  }


}