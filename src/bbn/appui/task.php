<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 26/01/2015
 * Time: 05:45
 */

namespace bbn\appui;


class task {

  protected static
    $cats = [],
    $actions = [],
    $states = [],
    $roles = [];

  public static
    $id_cat,
    $id_action,
    $id_state,
    $id_role;

  protected
    $db,
    $options,
    $id_user,
    $user;


  private function _email($id_task, $subject, $text){
    /*
    $users = array_unique(array_merge($this->get_ccs($id_task), $this->mgr->get_users(1)));
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
    */
  }

  private static function get_id_cat(\bbn\appui\options $opt){
    if ( !isset(self::$id_cat) ){
      self::$id_cat = $opt->from_code('cats', 'bbn_tasks');
    }
    return self::$id_cat;
  }

  private static function get_id_action(\bbn\appui\options $opt){
    if ( !isset(self::$id_action) ){
      self::$id_action = $opt->from_code('actions', 'bbn_tasks');
    }
    return self::$id_action;
  }

  private static function get_id_state(\bbn\appui\options $opt){
    if ( !isset(self::$id_state) ){
      self::$id_state = $opt->from_code('states', 'bbn_tasks');
    }
    return self::$id_state;
  }

  private static function get_id_role(\bbn\appui\options $opt){
    if ( !isset(self::$id_role) ){
      self::$id_role = $opt->from_code('roles', 'bbn_tasks');
    }
    return self::$id_role;
  }

  private static function get_cats(\bbn\appui\options $opt, $force = false){
    if ( empty(self::$cats) || $force ){
      if ( self::get_id_cat($opt) ){
        $tree = $opt->tree(self::$id_cat);
        self::$cats = isset($tree['items']) ? $tree['items'] : false;
      }
      else{
        self::$cats = false;
      }
    }
    return self::$cats;
  }

  private static function get_actions(\bbn\appui\options $opt, $force = false){
    if ( empty(self::$actions) || $force ){
      if ( self::get_id_action($opt) ){
        $actions = $opt->full_options(self::$id_action);
        foreach ( $actions as $action ){
          self::$actions[$action['code']] = $action['id'];
        }
      }
      else{
        self::$actions = false;
      }
    }
    return self::$actions;
  }

  private static function get_states(\bbn\appui\options $opt, $force = false){
    if ( empty(self::$states) || $force ){
      if ( self::get_id_state($opt) ){
        $states = $opt->full_options(self::$id_state);
        foreach ( $states as $state ){
          self::$states[$state['code']] = $state['id'];
        }
      }
      else{
        self::$states = false;
      }
    }
    return self::$states;
  }

  private static function get_roles(\bbn\appui\options $opt, $force = false){
    if ( empty(self::$roles) || $force ){
      if ( self::get_id_role($opt) ){
        $roles = $opt->full_options(self::$id_role);
        foreach ( $roles as $role ){
          self::$roles[$role['code']] = $role['id'];
        }
      }
      else{
        self::$roles = false;
      }
    }
    return self::$roles;
  }

  private static function get_action(\bbn\appui\options $opt, $code, $force = false){
    if ( !isset(self::$actions[$code]) || $force ){
      self::get_actions($opt, 1);
      if ( !isset(self::$actions[$code]) ){
        self::$actions[$code] = false;
      }
    }
    return self::$actions[$code];
  }

  private static function get_state(\bbn\appui\options $opt, $code, $force = false){
    if ( !isset(self::$states[$code]) || $force ){
      self::get_states($opt, 1);
      if ( !isset(self::$states[$code]) ){
        self::$states[$code] = false;
      }
    }
    return self::$states[$code];
  }

  private static function get_role(\bbn\appui\options $opt, $code, $force = false){
    if ( !isset(self::$roles[$code]) || $force ){
      self::get_roles($opt, 1);
      if ( !isset(self::$roles[$code]) ){
        self::$roles[$code] = false;
      }
    }
    return self::$roles[$code];
  }

  public function __construct(\bbn\db $db, \bbn\user\connection $user, \bbn\appui\options $options){
    $this->db = $db;
    $this->options = $options;
    $this->id_user = $user->get_id();
    $this->user = $user->get_name();
    $this->mgr = new \bbn\user\manager($user);
  }

  public function categories(){
    return self::get_cats($this->options);
  }

  public function actions(){
    return self::get_actions($this->options);
  }

  public function states(){
    return self::get_states($this->options);
  }

  public function roles(){
    return self::get_roles($this->options);
  }

  public function id_action($code){
    return self::get_action($this->options, $code);
  }

  public function id_state($code){
    return self::get_state($this->options, $code);
  }

  public function id_role($code){
    return self::get_role($this->options, $code);
  }

  public function get_mine($status = 'opened|ongoing|suspended', $id_user = false, $limit = 20, $start = 0){
    if ( !\bbn\str::is_integer($limit, $start) ){
      return false;
    }
    if ( !$id_user ){
      $id_user = $this->id_user;
    }
    $statuses = [];
    $tmp = explode("|", $status);
    $where = [];
    foreach ( $tmp as $s ){
      if ( $t = $this->id_state($s) ){
        array_push($statuses, $t);
        array_push($where, "`state` = $t");
      }
    }
    $where = count($where) ? implode( " OR ", $where) : '';
    $sql = "
    SELECT `role`, bbn_tasks.*,
    FROM_UNIXTIME(MAX(bbn_tasks_logs.chrono)) AS last_activity,
    MAX(bbn_tasks_logs.chrono) - MIN(bbn_tasks_logs.chrono) AS duration
    FROM bbn_tasks_roles
      JOIN bbn_tasks
        ON bbn_tasks_roles.id_task = bbn_tasks.id
      JOIN bbn_tasks_logs
        ON bbn_tasks_logs.id_task = bbn_tasks_roles.id_task
    WHERE bbn_tasks_roles.id_user = ?".
      (empty($where) ? '' : " AND ($where)")."
    GROUP BY bbn_tasks_roles.id_task";
    return $this->db->get_rows($sql, $id_user);
  }

  public function info($id){
    if ( $info = $this->db->rselect('bbn_tasks', [], ['id' => $id]) ){
      $info['start'] = $this->db->select_one('bbn_tasks_actions', 'chrono', [
        'id_task' => $id,
        'action' => $this->id_action('task_ins')
      ], ['chrono' => 'DESC']);
      $info['initial'] = $this->db->select_one('bbn_tasks_actions', 'chrono', [
        'id_task' => $id,
        'action' => $this->id_action('task_ins')
      ], ['chrono' => 'ASC']);
      $info['last'] = $this->db->select_one('bbn_tasks_actions', 'chrono', [
        'id_task' => $id,
        'action' => $this->id_action('task_ins')
      ], ['chrono' => 'ASC']);
      return $info;
    }
  }

  public function full_info($id){

  }

  public function get_ccs($id){
    return $this->db->get_column_values('bbn_tasks_cc', 'id_user', ['id_task' => $id]);
  }

  public function comment($id, $text){
    if ( ($info = $this->info($id)) && $this->db->insert('bbn_tasks_comments', [
      'id_task' => $id,
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

  public function insert($title, $text, $target = false){
    $date = date('Y-m-d H:i:s');
    if ( $this->db->insert('bbn_tasks', [
      'title' => $title,
      'id_user' => $this->id_user,
      'target_date' => empty($target) ? null : $target,
      'creation_date' => $date
    ]) ){
      $id = $this->db->last_id();
      $this->db->insert('bbn_tasks_status', [
        'id_task' => $id,
        'creation_date' => $date,
        'id_user' => $this->id_user,
        'status' => 'ouvert'
      ]);
      $this->db->insert('bbn_tasks_comments', [
        'id_task' => $id,
        'creation_date' => $date,
        'id_user' => $this->id_user,
        'comment' => $text
      ]);
      $this->db->insert('bbn_tasks_cc', ['id_user' => $this->id_user, 'id_task' => $id]);
      $subject = "Nouveau bug posté par {$this->user}";
      $text = "<p>{$this->user} a posté un nouveau bug</p>".
        "<p><strong>$title</strong></p>".
        "<p>".nl2br($text)."</p>".
        "<p><em>Rendez-vous dans votre interface APST pour lui répondre</em></p>";
      $this->_email($id, $subject, $text);
      return $id;
    }
  }

  public function update($id, $title, $status, $priority, $target = null){
    if ( $info = $this->info($id) ){
      $date = date('Y-m-d H:i:s');
      $pretext = "<p>{$this->user} a procédé aux changements suivants concernant le bug<br> <strong>$title</strong>:</p>";
      $text = '';
      if ( $this->db->update("bbn_tasks", [
        'title' => $title,
        'target_date' => empty($target) ? null : $target,
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
        if ( $target !== $info['target_date'] ){
          $text .= "<p> L'objectif est passé de ".
            ( empty($info['target_date']) ? "non défini" : \bbn\date::format($info['target_date']) ).
            " à ".
            ( empty($target) ? "non défini" : \bbn\date::format($target) ).
            "</p>";
        }
      }
      if ( $status !== $info['status'] ){
        $this->db->insert('bbn_tasks_status', [
          'id_task' => $id,
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
    if ( ($info = $this->info($id)) && $this->db->delete('bbn_tasks', ['id' => $id]) ){
      $subject = "Suppression du bug $info[title]";
      $text = "<p>{$this->user} a supprimé le bug<br><strong>$info[title]</strong></p>";
      $this->_email($id, $subject, $text);
      return $id;
    }
  }

  public function up($id){
    if ( $info = $this->info($id) ){
      return $this->update($id, $info['title'], $info['status'], $info['priority']-1, $info['target_date']);
    }
  }

  public function down($id){
    if ( $info = $this->info($id) ){
      return $this->update($id, $info['title'], $info['status'], $info['priority']+1, $info['target_date']);
    }
  }

  public function subscribe($id){
    return $this->db->insert('bbn_tasks_cc', ['id_user' => $this->id_user, 'id_task' => $id]);
  }

  public function unsubscribe($id){
    return $this->db->delete('bbn_tasks_cc', ['id_user' => $this->id_user, 'id_task' => $id]);
  }
}