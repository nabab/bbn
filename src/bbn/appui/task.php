<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 26/01/2015
 * Time: 05:45
 */

namespace bbn\appui;


class task {

  private static
    $cats = [],
    $actions = [],
    $states = [],
    $roles = [],
    $id_cat,
    $id_action,
    $id_state,
    $id_role;

  protected
    $db,
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

  private static function get_id_cat(){
    if ( !isset(self::$id_cat) && ($opt = \bbn\appui\options::get_options()) ){
      self::$id_cat = $opt->from_code('cats', 'bbn_tasks');
    }
    return self::$id_cat;
  }

  private static function get_id_action(){
    if ( !isset(self::$id_action) && ($opt = \bbn\appui\options::get_options()) ){
      self::$id_action = $opt->from_code('actions', 'bbn_tasks');
    }
    return self::$id_action;
  }

  private static function get_id_state(){
    if ( !isset(self::$id_state) && ($opt = \bbn\appui\options::get_options()) ){
      self::$id_state = $opt->from_code('states', 'bbn_tasks');
    }
    return self::$id_state;
  }

  private static function get_id_role(){
    if ( !isset(self::$id_role) && ($opt = \bbn\appui\options::get_options()) ){
      self::$id_role = $opt->from_code('roles', 'bbn_tasks');
    }
    return self::$id_role;
  }

  public static function get_cats($force = false){
    if ( empty(self::$cats) || $force ){
      if ( ($opt = \bbn\appui\options::get_options()) && self::get_id_cat() ){
        $tree = $opt->tree(self::$id_cat);
        self::$cats = isset($tree['items']) ? $tree['items'] : false;
      }
      else{
        self::$cats = false;
      }
    }
    return self::$cats;
  }

  public static function get_actions($force = false){
    if ( empty(self::$actions) || $force ){
      if ( ($opt = \bbn\appui\options::get_options()) && self::get_id_action() ){
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

  public static function get_states($force = false){
    if ( empty(self::$states) || $force ){
      if ( ($opt = \bbn\appui\options::get_options()) && self::get_id_state() ){
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

  public static function get_roles($force = false){
    if ( empty(self::$roles) || $force ){
      if ( ($opt = \bbn\appui\options::get_options()) && self::get_id_role() ){
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

  public static function get_cat($code, $force = false){
    if ( !isset(self::$cats[$code]) || $force ){
      self::get_cats(1);
      if ( !isset(self::$cats[$code]) ){
        self::$cats[$code] = false;
      }
    }
    return isset(self::$cats[$code]) ? self::$cats[$code] : false;
  }

  public static function get_action($code, $force = false){
    if ( !isset(self::$actions[$code]) || $force ){
      self::get_actions(1);
      if ( !isset(self::$actions[$code]) ){
        self::$actions[$code] = false;
      }
    }
    return isset(self::$actions[$code]) ? self::$actions[$code] : false;
  }

  public static function get_state($code, $force = false){
    if ( !isset(self::$states[$code]) || $force ){
      self::get_states(1);
      if ( !isset(self::$states[$code]) ){
        self::$states[$code] = false;
      }
    }
    return isset(self::$states[$code]) ? self::$states[$code] : false;
  }

  public static function get_role($code, $force = false){
    if ( !isset(self::$roles[$code]) || $force ){
      self::get_roles(1);
      if ( !isset(self::$roles[$code]) ){
        self::$roles[$code] = false;
      }
    }
    return isset(self::$roles[$code]) ? self::$roles[$code] : false;
  }

  public static function cat_correspondances(){
    if ( $opt = \bbn\appui\options::get_options() ){
      $cats = self::get_cats();
      $res = [];
      $opt->map(function ($a) use (&$res){
        array_push($res, [
          'value' => $a['id'],
          'text' => $a['text']
        ]);
        $a['is_parent'] = !empty($a['items']);
        if ( $a['is_parent'] ){
          $a['expanded'] = true;
        }
        return $a;
      }, $cats, 1);
      return $res;
    }
    return false;
  }

  public static function get_options(){
    if ( $opt = \bbn\appui\options::get_options() ){
      return [
        'states' => $opt->text_value_options(self::get_id_state()),
        'roles' => $opt->text_value_options(self::get_id_role()),
        'cats' => self::cat_correspondances()
      ];
    }
  }

  public function check(){
    return isset($this->user);
  }

  public function __construct(\bbn\db $db){
    $this->db = $db;
    if ( $user = \bbn\user\connection::get_user() ){
      $this->user = $user->get_name();
      $this->user_id = $user->get_id();
      $this->mgr = new \bbn\user\manager($user);
    }
    $this->columns = array_keys($this->db->get_columns('bbn_tasks'));
  }

  public function categories(){
    return self::get_cats();
  }

  public function actions(){
    return self::get_actions();
  }

  public function states(){
    return self::get_states();
  }

  public function roles(){
    return self::get_roles();
  }

  public function id_cat($code){
    return self::get_cat($code);
  }

  public function id_action($code){
    return self::get_action($code);
  }

  public function id_state($code){
    return self::get_state($code);
  }

  public function id_role($code){
    return self::get_role($code);
  }

  public function get_mine($parent = null, $order = 'priority', $dir = 'ASC', $limit = 50, $start = 0){
    return $this->get_list($parent, 'opened|ongoing|holding', $this->user_id, $order, $dir, $limit, $start);
  }

  public function get_list($parent = null, $status = 'opened|ongoing|holding', $id_user = false, $order = 'priority', $dir = 'ASC', $limit = 500, $start = 0){
    $orders_ok = [
      'id' => 'bbn_tasks.id',
      'last' => 'last',
      'first' => 'first',
      'duration' => 'duration',
      'num_children' => 'num_children',
      'title' => 'title',
      'num_attachments' => 'num_attachments',
      'role' => 'role',
      'state' => 'state',
      'priority' => 'priority'
    ];
    if ( !isset($orders_ok[$order]) ||
      !\bbn\str::is_integer($limit, $start) ||
      (!is_null($parent) && !\bbn\str::is_integer($parent))
    ){
      return false;
    }
    $dir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';
    if ( !$id_user ){
      $id_user = $this->user_id;
    }
    $statuses = [];
    $tmp = explode("|", $status);
    $where = [];
    foreach ( $tmp as $s ){
      if ( $t = $this->id_state($s) ){
        array_push($statuses, $t);
        array_push($where, "`bbn_tasks`.`state` = $t");
      }
    }
    $where = count($where) ? implode( " OR ", $where) : '';
    $sql = "
    SELECT `role`, bbn_tasks.*,
    FROM_UNIXTIME(MIN(bbn_tasks_logs.chrono)) AS `first`,
    FROM_UNIXTIME(MAX(bbn_tasks_logs.chrono)) AS `last`,
    COUNT(children.id) AS num_children,
    MAX(bbn_tasks_logs.chrono) - MIN(bbn_tasks_logs.chrono) AS duration
    FROM bbn_tasks_roles
      JOIN bbn_tasks
        ON bbn_tasks_roles.id_task = bbn_tasks.id
      JOIN bbn_tasks_logs
        ON bbn_tasks_logs.id_task = bbn_tasks_roles.id_task
      LEFT JOIN bbn_tasks AS children
        ON bbn_tasks_roles.id_task = children.id_parent
    WHERE bbn_tasks_roles.id_user = ?".
      (empty($where) ? '' : " AND ($where)")."
    AND bbn_tasks.active = 1 
    AND bbn_tasks.id_alias IS NULL
    AND bbn_tasks.id_parent ".( is_null($parent) ? "IS NULL" : "= $parent" )." 
    GROUP BY bbn_tasks_roles.id_task
    LIMIT $start, $limit";

    $opt = \bbn\appui\options::get_options();
    $res = $this->db->get_rows($sql, $id_user);
    foreach ( $res as $i => $r ){
      $res[$i]['type'] = $opt->itext($r['type']);
      $res[$i]['state'] = $opt->itext($r['state']);
      $res[$i]['role'] = $opt->itext($r['role']);
      $res[$i]['hasChildren'] = $r['num_children'] ? true : false;
    }
    /*
    foreach ( $res as $i => $r ){
      $res[$i]['details'] = $this->info($r['id']);
    }
    */
    \bbn\x::sort_by($res, $order, $dir);
    return $res;
  }

  private function add_attachment($type, $value, $title){

  }

  public function add_link(){
    return $this->add_attachment();
  }

  public function info($id){
    if ( $info = $this->db->rselect('bbn_tasks', [], ['id' => $id]) ){

      $info['first'] = $this->db->select_one('bbn_tasks_logs', 'chrono', [
        'id_task' => $id,
        'action' => $this->id_action('insert')
      ], ['chrono' => 'ASC']);

      $info['last'] = $this->db->select_one('bbn_tasks_logs', 'chrono', [
        'id_task' => $id,
      ], ['chrono' => 'DESC']);
      $info['roles'] = $this->db->rselect_all('bbn_tasks_roles', [], ['id_task' => $id]);
      $info['attachments'] = $this->db->get_column_values('bbn_tasks_attachments', 'id_note', ['id_task' => $id]);
      $info['children'] = $this->db->rselect_all('bbn_tasks', [], ['id_parent' => $id, 'active' => 1]);
      $info['aliases'] = $this->db->rselect_all('bbn_tasks', ['id', 'title'], ['id_alias' => $id, 'active' => 1]);
      $info['num_children'] = count($info['children']);
      if ( $info['num_children'] ){
        $info['has_children'] = 1;
        foreach ( $info['children'] as $i => $c ){
          $info['children'][$i]['num_children'] = $this->db->count('bbn_tasks', ['id_parent' => $c['id'], 'active' => 1]);
        }
      }
      else{
        $info['has_children'] = false;
      }
      return $info;
    }
  }

  public function search($st){
    $res = [];
    $res1 = $this->search_in_task($st);
    return $res1;
  }

  public function search_in_task($st){
    return $this->db->rselect_all('bbn_tasks', ['id', 'title', 'creation_date'], [['title',  'LIKE', '%'.$st.'%']]);
  }

  public function full_info($id){

  }

  public function info_roles($id){
    return $this->db->rselect_all('bbn_tasks_roles', 'id_user', ['id_task' => $id]);
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

  public function insert($title, $target = false){
    $date = date('Y-m-d H:i:s');
    if ( $this->db->insert('bbn_tasks', [
      'title' => $title,
      'id_user' => $this->id_user,
      'state' => $this->id_state('opened'),
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