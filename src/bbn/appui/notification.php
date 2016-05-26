<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 19/05/2016
 * Time: 17:33
 */

namespace bbn\appui;


class notification extends \bbn\objdb
{
  protected static
    $t = 'bbn_notifications',
    $tc = 'bbn_notifications_content',
    $c = [
      'title' => 'title',
      'content' => 'content',
      'id' => 'id',
      'id_content' => 'id_content',
      'id_user' => 'id_user',
      'sent' => 'sent',
      'creation' => 'creation',
      'mail' => 'mail'
    ];


  public function insert($title, $content, array $users){
    if ( is_string($title) && !empty($title) && is_string($content) && !empty($content) ){
      $this->db->insert(self::$tc, [
        self::$c['title'] => $title,
        self::$c['content'] => $content,
        self::$c['creation'] => date('Y-m-d H:i:s')
      ]);
      $id = $this->db->last_id();
      $i = 0;
      foreach ( $users as $u ){
        $i += (int)$this->db->insert(self::$t, [
          self::$c['id_content'] => $id,
          self::$c['id_user'] => $u
        ]);
      }
      return $i;
    }
    return false;
  }

  public function get_notifications(){
    $sql = "SELECT ".self::$tc.".*, ".self::$t.".*
    FROM ".self::$t."
      JOIN ".self::$tc."
        ON id_content = ".self::$tc.".id
      JOIN apst_users_sessions
        ON apst_users_sessions.id_user = ".self::$t.".id_user
    WHERE sent IS NULL
    GROUP BY bbn_notifications.id_user, bbn_notifications.id_content
    HAVING creation >= MAX(last_activity)";

  }
}