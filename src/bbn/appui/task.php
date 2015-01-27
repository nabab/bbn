<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 26/01/2015
 * Time: 05:45
 */

namespace bbn\appui;


class task {

  private $db;

  private function _email($id_bug, $subject, $text){
    $users = array_unique(array_merge($this->get_ccs($id_bug), $this->mgr->get_users(1)));
    foreach ( $users as $u ){
      if ( ($u !== $this->id_user) && ($email = $this->mgr->get_email($u)) ){
        $this->db->insert('apst_accuses', [
          'email' => $email,
          'titre' => $subject,
          'texte' => $text,
          'etat' => 'pret'
        ]);
      }
    }
  }

  public function __construct(\bbn\db\connection $db, $user){
    $this->db = $db;
    $this->id_user = $user->get_id();
    $this->user = $user->get_name();
    $this->mgr = new \bbn\user\manager($user);
  }

  public function info($id){
    return $this->db->get_row("
      SELECT bbn_bugs.*, COUNT(DISTINCT bbn_bugs_comments.id) AS `comments`, `status`, crea,
      GREATEST(crea, MAX(bbn_bugs_comments.creation_date)) AS `last_activity`,
      TIMESTAMPDIFF(
        SECOND,
        bbn_bugs.creation_date,
        IF ( status != 'résolu', NOW(), GREATEST(crea, MAX(bbn_bugs_comments.creation_date)) )
      ) AS `duration`
      FROM `bbn_bugs`
        JOIN (
              SELECT id_bug, `status`, creation_date AS crea
              FROM bbn_bugs_status
              WHERE id_bug = ?
              ORDER BY creation_date DESC
          ) AS statuses
            ON statuses.id_bug = bbn_bugs.id
        JOIN bbn_bugs_comments
          ON bbn_bugs_comments.id_bug = bbn_bugs.id
      WHERE bbn_bugs.id = ?
      GROUP BY bbn_bugs.id",
      $id);
  }

  public function full_info($id){

  }

  public function get_ccs($id){
    return $this->db->get_column_values('bbn_bugs_cc', 'id_user', ['id_bug' => $id]);
  }

  public function comment($id, $text){
    if ( ($info = $this->info($id)) && $this->db->insert('bbn_bugs_comments', [
      'id_bug' => $id,
      'creation_date' => date('Y-m-d H:i:s'),
      'id_user' => $this->id_user,
      'comment' => $text
    ]) ) {
      $id_comment = $this->db->last_id();
      $subject = 'Nouveau commentaire pour le bug '.$info['title'];
      $text = "<p>{$this->user} a écrit un nouveau message concernant le bug<br>".
        "<strong>$info[title]</strong></p>".
        "<p>".nl2br($text)."</p>".
        "<p><em>Rendez-vous dans votre interface APST pour lui répondre</em></p>";
      $this->_email($id, $subject, $text);
      return $id_comment;
    }
    return false;
  }

  public function insert($title, $text){
    $date = date('Y-m-d H:i:s');
    if ( $this->db->insert('bbn_bugs', [
      'title' => $title,
      'id_user' => $this->id_user,
      'creation_date' => $date
    ]) ){
      $id = $this->db->last_id();
      $this->db->insert('bbn_bugs_status', [
        'id_bug' => $id,
        'creation_date' => $date,
        'id_user' => $this->id_user,
        'status' => 'ouvert'
      ]);
      $this->db->insert('bbn_bugs_comments', [
        'id_bug' => $id,
        'creation_date' => $date,
        'id_user' => $this->id_user,
        'comment' => $text
      ]);
      $this->db->insert('bbn_bugs_cc', ['id_user' => $this->id_user, 'id_bug' => $id]);
      $subject = "Nouveau bug posté par {$this->user}";
      $text = "<p>{$this->user} a posté un nouveau bug</p>".
        "<p><strong>$title</strong></p>".
        "<p>".nl2br($text)."</p>".
        "<p><em>Rendez-vous dans votre interface APST pour lui répondre</em></p>";
      $this->_email($id, $subject, $text);
      return $id;
    }
  }

  public function update($id, $title, $status, $priority){
    if ( $info = $this->info($id) ){
      $date = date('Y-m-d H:i:s');
      $pretext = "<p>{$this->user} a procédé aux changements suivants concernant le bug<br> <strong>$title</strong>:</p>";
      $text = '';
      if ( $this->db->update("bbn_bugs", [
        'title' => $title,
        'priority' => $priority
      ], [
        'id' => $id
      ]) ){
        if ( $priority !== $info['priority'] ){
          $text .= "<p> La priorité est passée de $info[priority] à $priority</p>";
        }
        if ( $title !== $info['title'] ){
          $text .= "<p> L'ancien titre était $info[title]</p>";
        }
      }
      if ( $status !== $info['status'] ){
        $this->db->insert('bbn_bugs_status', [
          'id_bug' => $id,
          'creation_date' => $date,
          'id_user' => $this->id_user,
          'status' => $status
        ]);
        $text .= "<p>Le statut a été changé de $info[status] à $status</p>";
      }
      if ( !empty($text) ) {
        $subject = "Modification du bug $info[title]";
        $text = $pretext . $text . "<p><em>Rendez-vous dans l'interface APST pour répondre</em></p>";
        $this->_email($id, $subject, $text);
        return $id;
      }
    }
  }

  public function delete($id){
    if ( ($info = $this->info($id)) && $this->db->delete('bbn_bugs', ['id' => $id]) ){
      $subject = "Suppression du bug $info[title]";
      $text = "<p>{$this->user} a supprimé le bug<br><strong>$info[title]</strong></p>";
      $this->_email($id, $subject, $text);
      return $id;
    }
  }

  public function up($id){
    if ( $info = $this->info($id) ){
      return $this->update($id, $info['title'], $info['status'], $info['priority']-1);
    }
  }

  public function down($id){
    if ( $info = $this->info($id) ){
      return $this->update($id, $info['title'], $info['status'], $info['priority']+1);
    }
  }

  public function subscribe($id){
    return $this->db->insert('bbn_bugs_cc', ['id_user' => $this->id_user, 'id_bug' => $id]);
  }

  public function unsubscribe($id){
    return $this->db->delete('bbn_bugs_cc', ['id_user' => $this->id_user, 'id_bug' => $id]);
  }
}