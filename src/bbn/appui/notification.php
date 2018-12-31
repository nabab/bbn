<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 19/05/2016
 * Time: 17:33
 */

namespace bbn\appui;
use bbn;


class notification extends bbn\models\cls\db
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
      'read' => 'read',
      'creation' => 'creation',
      'mail' => 'mail'
    ];


  public function insert($title, $content, array $users){
    if ( \is_string($title) && !empty($title) && \is_string($content) && !empty($content) ){
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

  public function consult($id_user = false){
    $self = false;
    if ( !$id_user && ($user = bbn\user::get_instance()) ){
      $id_user = $user->get_id();
      $self = 1;
    }
    if ( $id_user ){
      $list = $this->db->get_rows("SELECT ".self::$tc.".*, ".self::$t.".*
        FROM ".self::$t."
          JOIN ".self::$tc."
            ON id_content = {$this->db->cfn(self::$c['id'], self::$tc)}
          JOIN apst_users_sessions
            ON apst_users_sessions.id_user = {$this->db->cfn(self::$c['id_user'], self::$t)}
        WHERE {$this->db->cfn(self::$c['id_user'], self::$t)} = ?
        AND {$this->db->cfn(self::$c['sent'], self::$t)} IS NULL
        GROUP BY {$this->db->cfn(self::$c['id_user'], self::$t)}, {$this->db->cfn(self::$c['id_content'], self::$t)}
        HAVING {$this->db->cfn(self::$c['creation'], self::$tc)} >= MAX(apst_users_sessions.creation)",
        $id_user);
      if ( $self && \count($list) ){
        foreach ( $list as $l ){
          $this->db->update(self::$t, [
            self::$c['sent'] => date('Y-m-d H:i:s')
          ], [
            self::$c['id_user'] => $l[self::$c['id_user']],
            self::$c['id_content'] => $l[self::$c['id_content']]
          ]);
        }
      }
      return array_map(function($a){
        return [
          'id' => $a['id'],
          'title' => $a['title'],
          'html' => $a['content']
        ];
      }, $list);
    }
    return false;
  }

  public function get_list($id_user = false, $limit = 100, $start = 0){
    $self = false;
    if ( !$id_user && ($user = bbn\user::get_instance()) ){
      $id_user = $user->get_id();
      $self = 1;
    }
    if ( $id_user && \is_int($limit) && \is_int($start) ){
      $list = $this->db->get_rows("SELECT ".self::$tc.".*, ".self::$t.".*
        FROM ".self::$t."
          JOIN ".self::$tc."
            ON id_content = {$this->db->cfn(self::$c['id'], self::$tc)}
          JOIN apst_users_sessions
            ON apst_users_sessions.id_user = {$this->db->cfn(self::$c['id_user'], self::$t)}
        WHERE {$this->db->cfn(self::$c['id_user'], self::$t)} = ?
        GROUP BY {$this->db->cfn(self::$c['id_user'], self::$t)}, {$this->db->cfn(self::$c['id_content'], self::$t)}
        HAVING {$this->db->cfn(self::$c['creation'], self::$tc)} >= MAX(apst_users_sessions.creation)
        LIMIT $start, $limit",
        104);
      if ( $self && \count($list) ){
        foreach ( $list as $l ){
          $this->db->update(self::$t, [
            self::$c['sent'] => date('Y-m-d H:i:s')
          ], [
            self::$c['id_user'] => $l[self::$c['id_user']],
            self::$c['id_content'] => $l[self::$c['id_content']]
          ]);
        }
      }
      return $list;
    }
    return false;
  }

  public function read($id_user, $id){
    if ( !$id_user && ($user = bbn\user::get_instance()) ){
      $id_user = $user->get_id();
    }
    if ( $id_user ){
      return $this->db->update(self::$tc, [
        self::$c['sent'] => date('Y-m-d, H:i:s')
      ], [
        self::$c['id_content'] => $id,
        self::$c['id_user'] => $id_user
      ]);
    }
    return false;
  }
}

