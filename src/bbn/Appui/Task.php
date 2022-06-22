<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 26/01/2015
 * Time: 05:45
 */

namespace bbn\Appui;
use bbn;
use bbn\X;

class Task extends bbn\Models\Cls\Db
{

  use bbn\Models\Tts\References,
      bbn\Models\Tts\Optional;

  private $columns;

  protected
    $template = false,
    $id_user,
    $is_dev,
    $user,
    $date = false;


  protected function email($id_task, $subject, $text){
    /*
    $users = array_unique(array_merge($this->get_ccs($id_task), $this->mgr->getUsers(1)));
    foreach ( $users as $u ){
      if ( ($u !== $this->id_user) && ($email = $this->mgr->getEmail($u)) ){
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

  private static function options(){
    return \bbn\Appui\Option::getInstance();
  }

  public function __construct(bbn\Db $db){
    parent::__construct($db);
    self::optionalInit();
    if ( $user = bbn\User::getInstance() ){
      $this->user = $user->getName();
      $this->id_user = $user->getId();
      $this->is_dev = $user->isDev();
      $this->mgr = new bbn\User\Manager($user);
      $this->_get_references();
      //die(var_dump(BBN_APP_PATH, $this->references));
      if ( is_dir(\bbn\Mvc::getAppPath()) && is_file(\bbn\Mvc::getAppPath().'plugins/appui-task/reference.php') ){
        $f = include(\bbn\Mvc::getAppPath().'plugins/appui-task/reference.php');
        if ( is_callable($f) ){
          $this->template = $f;
        }
      }
    }
    $this->columns = array_keys($this->db->getColumns('bbn_tasks'));
  }

  public static function catCorrespondances(){
    if ( $opt = bbn\Appui\Option::getInstance() ){
      $cats = self::getOptionsTree('cats');
      $res = [];
      $opt->map(function ($a) use (&$res){
        $res[] = [
          'value' => $a['id'],
          'text' => $a['text']
        ];
        $a['is_parent'] = !empty($a['items']);
        if ( $a['is_parent'] ){
          $a['expanded'] = true;
        }
        return $a;
      }, $cats, 1);
      X::sortBy($res, 'text', 'ASC');
      return $res;
    }
    return false;
  }

  public static function getTasksOptions(){
    if (
      ($states = self::getAppuiOptionsIds('states')) &&
      ($roles = self::getAppuiOptionsIds('roles')) &&
      ($cats = self::catCorrespondances())
    ){
      return [
        'states' => $states,
        'roles' => $roles,
        'cats' => $cats
      ];
    }
  }

  public function check(){
    return isset($this->user);
  }

  public function getTitle($id_task, $simple=false){
    if ( $title = $this->db->selectOne('bbn_tasks', 'title', ['id' => $id_task]) ){
      return (!empty($simple) ? (X::_("Task")." ") : '').$title;
    }
    return '';
  }

  public function categories(){
    return self::getOptionsTree('cats');
  }

  public function actions(){
    return self::getAppuiOptionsIds('actions');
  }

  public function states(){
    return self::getAppuiOptionsIds('states');
  }

  public function roles(){
    return self::getAppuiOptionsIds('roles');
  }

  public function idCat($code){
    return self::getOptionId($code, 'cats');
  }

  public function idAction($code){
    return self::getAppuiOptionId($code, 'actions');
  }

  public function idState($code){
    return self::getAppuiOptionId($code, 'states');
  }

  public function idRole($code){
    return self::getAppuiOptionId($code, 'roles');
  }

  public function getMine($parent = null, $order = 'priority', $dir = 'ASC', $limit = 50, $start = 0){
    return $this->getList($parent, 'opened|ongoing|holding', $this->id_user, $order, $dir, $limit, $start);
  }

  public function translateLog(array $log){
    $opt = bbn\Appui\Option::getInstance();
    $user = bbn\User::getInstance();
    if ( $opt && $user && isset($log['action'], $log['id_user']) ){
      $type = explode('_', $opt->code($log['action']));
      $action = $user->getName($this->mgr->getUser($log['id_user'])).' '.$opt->text($log['action']);

      $log['value'] = empty($log['value']) ? [] : json_decode($log['value']);
      if ( !empty($log['value']) ){
        $values = [];
        switch ( $type[0] ){
          case 'deadline':
            foreach ( $log['value'] as $v ){
              array_push($values, bbn\Date::format($v, 's'));
            }
            break;
          case 'title':
            $values = $log['value'];
            break;
          case 'comment':
            array_push($values, bbn\Str::cut($this->db->getOne("
            SELECT content
            FROM bbn_notes_versions
            WHERE id_note = ?
            ORDER BY version DESC
            LIMIT 1",
              $log['value'][0]), 80));
            break;
          case 'role':
            if ( ($user = bbn\User::getInstance()) && isset($log['value'][0], $log['value'][1]) ){
              $values[0] = $user->getName($this->mgr->getUser($log['value'][0]));
              $values[1] = $opt->text($log['value'][1]);
            }
            break;
          case 'priority':
            $values = $log['value'];
            break;
          case 'price':
          case 'approved':
            foreach ( $log['value'] as $i => $v ){
              $values[] = number_format((float)$v, 2, ',', '.');
            }
            break;
          default:
            foreach ( $log['value'] as $v ){
              array_push($values, $opt->text($v));
            }
        }
        if ( !empty($values) ){
          foreach ( $values as $i => $v ){
            $values[$i] = '<strong>'.$v.'</strong>';
          }
          array_unshift($values, $action);
          return \sprintf(...$values);
        }
      }
      return $action;
    }
    return false;
  }

  public function getLog($id){
    $logs = $this->db->rselectAll('bbn_tasks_logs', [], ['id_task' => $id], ['chrono' => 'DESC']);
    $res = [];
    foreach ( $logs as $log ){
      array_push($res, [
        'action' => $this->translateLog($log),
        'id_user' => $log['id_user'],
        'chrono' => $log['chrono']
      ]);
    }
    return $res;
  }

  public function getAllLogs($limit = 100, $start = 0){
    $logs = $this->db->rselectAll('bbn_tasks_logs', [], [], ['chrono' => 'DESC']);
    $res = [];
    foreach ( $logs as $log ){
      array_push($res, [
        'action' => $this->translateLog($log),
        'id_user' => $log['id_user'],
        'chrono' => $log['chrono']
      ]);
    }
    return $res;
  }

  public function getApprovedLog($id){
    if (
      $this->exists($id) &&
      ($action = $this->idAction('price_approved'))
    ){
      return $this->db->rselect('bbn_tasks_logs', [], [
        'id_task' => $id,
        'action' => $action
      ], ['chrono' => 'DESC']) ?: new \stdClass();
    }
    return new \stdClass();
  }

  public function getPriceLog($id){
    if (
      $this->exists($id) &&
      ($action_ins = $this->idAction('price_insert')) &&
      ($action_upd = $this->idAction('price_update'))
    ){
      return $this->db->rselect([
        'table' => 'bbn_tasks_logs',
        'where' => [
          'logic' => 'AND',
          'conditions' => [[
            'field' => 'id_task',
            'operation' => '=',
            'value' => $id
          ], [
            'logic' => 'OR',
            'conditions' => [[
              'field' => 'action',
              'operatort' => '=',
              'value' => $action_ins
            ], [
              'field' => 'action',
              'operatort' => '=',
              'value' => $action_upd
            ]]
          ]]
        ],
        'order' => [[
          'field' => 'chrono',
          'dir' => 'DESC'
        ]]
      ]) ?: new \stdClass();
    }
  }

  public function getList($parent = null, $status = 'opened|ongoing|holding', $id_user = false, $order = 'priority', $dir = 'ASC', $limit = 1000, $start = 0){
    $orders_ok = [
      'id' => 'bbn_tasks.id',
      'last' => 'last',
      'first' => 'first',
      'duration' => 'duration',
      'num_children' => 'num_children',
      'title' => 'title',
      'num_notes' => 'num_notes',
      'role' => 'role',
      'state' => 'state',
      'priority' => 'priority'
    ];
    if ( !isset($orders_ok[$order]) ||
      !bbn\Str::isInteger($limit, $start) ||
      (!\is_null($parent) && !bbn\Str::isInteger($parent))
    ){
      return false;
    }
    $dir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';
    if ( !$id_user ){
      $id_user = $this->id_user;
    }
    $where = [];
    if ( !empty($status) ){
      $statuses = [];
      $tmp = explode("|", $status);
      foreach ( $tmp as $s ){
        if ( $t = $this->idState($s) ){
          array_push($statuses, $t);
          array_push($where, "`bbn_tasks`.`state` = $t");
        }
      }
    }
    $where = \count($where) ? implode( " OR ", $where) : '';
    $sql = "
    SELECT `role`, bbn_tasks.*,
    FROM_UNIXTIME(MIN(bbn_tasks_logs.chrono)) AS `first`,
    FROM_UNIXTIME(MAX(bbn_tasks_logs.chrono)) AS `last`,
    {$this->references_select}
    COUNT(children.id) AS num_children,
    COUNT(DISTINCT bbn_tasks_notes.id_note) AS num_notes,
    MAX(bbn_tasks_logs.chrono) - MIN(bbn_tasks_logs.chrono) AS duration
    FROM bbn_tasks_roles
      JOIN bbn_tasks
        ON bbn_tasks_roles.id_task = bbn_tasks.id
      JOIN bbn_tasks_logs
        ON bbn_tasks_logs.id_task = bbn_tasks_roles.id_task
      LEFT JOIN bbn_tasks_notes
        ON bbn_tasks_notes.id_task = bbn_tasks_roles.id_task
      LEFT JOIN bbn_tasks AS children
        ON bbn_tasks_roles.id_task = children.id_parent
      {$this->references_join}
    WHERE bbn_tasks_roles.id_user = ?".
      (empty($where) ? '' : " AND ($where)")."
    AND bbn_tasks.active = 1
    AND bbn_tasks.id_alias IS NULL
    AND bbn_tasks.id_parent ".( \is_null($parent) ? "IS NULL" : "= $parent" )."
    GROUP BY bbn_tasks_roles.id_task
    LIMIT $start, $limit";

    $opt = bbn\Appui\Option::getInstance();
    $res = $this->db->getRows($sql, $id_user);
    foreach ( $res as $i => $r ){
      $res[$i]['hasChildren'] = $r['num_children'] ? true : false;
    }
    /*
    foreach ( $res as $i => $r ){
      $res[$i]['details'] = $this->info($r['id']);
    }
    */
    X::sortBy($res, $order, $dir);
    return $res;
  }

  public function getSlist($search, $order = 'last', $dir = 'DESC', $limit = 1000, $start = 0){
    $orders_ok = [
      'id' => 'bbn_tasks.id',
      'last' => 'last',
      'first' => 'first',
      'duration' => 'duration',
      'num_children' => 'num_children',
      'title' => 'title',
      'num_notes' => 'num_notes',
      'role' => 'role',
      'state' => 'state',
      'priority' => 'priority'
    ];
    if ( !isset($orders_ok[$order]) || !bbn\Str::isInteger($limit, $start) ){
      return false;
    }
    $dir = strtolower($dir) === 'asc' ? 'ASC' : 'DESC';
    $sql = "
    SELECT bbn_tasks.*, role,
    FROM_UNIXTIME(MAX(bbn_tasks_logs.chrono)) AS `last_action`,
    {$this->references_select}
    COUNT(children.id) AS num_children,
    COUNT(DISTINCT bbn_tasks_notes.id_note) AS num_notes,
    IF(bbn_tasks.`state`=".$this->idState('closed').", MAX(bbn_tasks_logs.chrono), UNIX_TIMESTAMP()) - MIN(bbn_tasks_logs.chrono) AS duration
    FROM bbn_tasks
      JOIN bbn_tasks_logs
        ON bbn_tasks_logs.id_task = bbn_tasks.id
      LEFT JOIN bbn_tasks_notes
        ON bbn_tasks_notes.id_task = bbn_tasks.id
      LEFT JOIN bbn_tasks_roles
        ON bbn_tasks_roles.id_task = bbn_tasks.id
        AND bbn_tasks_roles.id_user = {$this->id_user}
      LEFT JOIN bbn_notes_versions
        ON bbn_notes_versions.id_note = bbn_tasks_notes.id_note
      LEFT JOIN bbn_tasks AS children
        ON children.id = bbn_tasks.id
      {$this->references_join}
    WHERE (bbn_tasks.title LIKE ?
    OR bbn_notes_versions.content LIKE ?)
    AND bbn_tasks.active = 1
    GROUP BY bbn_tasks.id
    LIMIT $start, $limit";

    $opt = bbn\Appui\Option::getInstance();
    $res = $this->db->getRows($sql, "%$search%");
    /*
    foreach ( $res as $i => $r ){
      $res[$i]['type'] = $opt->itext($r['type']);
      $res[$i]['state'] = $opt->itext($r['state']);
      $res[$i]['role'] = $opt->itext($r['role']);
      $res[$i]['hasChildren'] = $r['num_children'] ? true : false;
    }
    foreach ( $res as $i => $r ){
      $res[$i]['details'] = $this->info($r['id']);
    }
    */
    X::sortBy($res, $order, $dir);
    return [
      'data' => $res,
      'total' => \count($res)
    ];
  }

  public function getTree($id = null, $closed = false){
    $statuses = empty($closed) ? 'opened|ongoing|holding' : false;
    $res = [];
    $all = $this->getList($id ?: null, $statuses, 5000);
    foreach ( $all as $a ){
      array_push($res, [
        'id' => $a['id'],
        'text' => $a['title'].' ('.bbn\Date::format($a['first']).'-'.bbn\Date::format($a['last']).')',
        'is_parent' => $a['num_children'] ? true : false
      ]);
    }
    return $res;
  }

  private function addNote($type, $value, $title){
    return [];
  }

  public function addLink(){
    return $this->addNote();
  }

  public function info($id, $with_comments = false){
    if ( $info = $this->db->rselect('bbn_tasks', [], ['id' => $id]) ){
      $info['first'] = $this->db->selectOne('bbn_tasks_logs', 'chrono', [
        'id_task' => $id,
        'action' => $this->idAction('insert')
      ], ['chrono' => 'ASC']);
      $info['last'] = $this->db->selectOne('bbn_tasks_logs', 'chrono', [
        'id_task' => $id,
      ], ['chrono' => 'DESC']);
      $info['roles'] = $this->infoRoles($id);
      $info['notes'] = $with_comments ? $this->getComments($id) : $this->getCommentsIds($id);
      $info['children'] = $this->getChildren($id);
      $info['aliases'] = $this->db->rselectAll('bbn_tasks', ['id', 'title'], ['id_alias' => $id, 'active' => 1]);
      $info['num_children'] = \count($info['children']);
      $info['has_children'] = !empty($info['num_children']);
      $info['reference'] = false;
      if ( $this->references ){
        foreach ( $this->references as $table => $ref ){
          foreach ( $ref['refs'] as $j => $r ){
            if ( $id_ref = $this->db->selectOne($table, $j, [$ref['column'] => $id]) ){
              $info['reference'] = $this->template === false ? $id_ref : \call_user_func($this->template, $this->db, $id_ref, $table);
              break;
            }
          }
          if ( $info['reference'] ){
            break;
          }
        }
      }
      if (!empty($info['id_parent'])) {
        $info['parent'] = $this->info($info['id_parent'], $with_comments);
      }
      return $info;
    }
  }

  public function getChildren(string $id): array
  {
    if ($children = $this->db->rselectAll([
      'table' => 'bbn_tasks',
      'fields' => [],
      'where' => [
        'conditions' => [[
          'field' => 'id_parent',
          'value' => $id
        ], [
          'field' => 'active',
          'value' => 1
        ], [
          'logic' => 'OR',
          'conditions' => [[
            'field' => 'private',
            'value' => 0
          ], [
            'conditions' => [[
              'field' => 'private',
              'value' => 1
            ], [
              'field' => 'id_user',
              'value' => $this->id_user
            ]]
          ]]
        ]]
      ],
      'order' => [
        'creation_date' => 'DESC'
      ]
    ])) {
      foreach ($children as $i => $c) {
        $children[$i]['num_children'] = $this->db->count('bbn_tasks', ['id_parent' => $c['id'], 'active' => 1]);
        $children[$i]['roles'] = $this->infoRoles($c['id']);
      }
      return $children;
    }
    return [];
  }

  public function getState($id){
    if ( $this->exists($id) ){
      return $this->db->selectOne('bbn_tasks', 'state', ['id' => $id]);
    }
  }

  public function getCommentsIds($id_task){
    return $this->db->getColArray("
      SELECT bbn_tasks_notes.id_note
      FROM bbn_tasks_notes
        JOIN bbn_notes_versions
          ON bbn_notes_versions.id_note = bbn_tasks_notes.id_note
      WHERE id_task = ?
      GROUP BY bbn_tasks_notes.id_note
      ORDER BY MAX(bbn_notes_versions.creation)",
      hex2bin($id_task));
  }

  private function _format_where(array $cfg){
    $res = [];
    foreach ( $cfg as $i => $c ){
      if ( \is_array($c) ){
        array_push($res, $c);
      }
      else if ( ($i === 'text') || ($i === 'title') ){
        array_push($res, [$i, 'LIKE', "%$c%"]);
      }
      else{
        array_push($res, [$i, '=', $c]);
      }
    }
    return $res;
  }

  public function search(array $where = [], $sort = [], $start = 0, $num = 25){
    $where = $this->_format_where($where);
    $fields = [
      'ids' => [
        'id_parent' => 'bbn_tasks.id_parent',
        'id_user' => 'bbn_tasks.id_user',
        'state' => 'bbn_tasks.state',
        'role' => 'my_role.role',
        'type' => 'bbn_tasks.type'
      ],
      'nums' => [
        'num_notes' => 'num_notes',
        'duration' => 'duration',
        'priority' => 'bbn_tasks.priority'
      ],
      'dates' => [
        'deadline' => 'bbn_tasks.deadline',
        'creation_date' => 'creation_date',
        'last_action' => 'last_action'
      ],
      'texts' => [
        'title' => 'bbn_tasks.title',
        'text' => 'bbn_notes_versions.content'
      ],
      'users' => [
        'my_user' => '',
        'my_group' => ''
      ],
      'refs' => [
        'reference' => 'reference'
      ]
    ];
    $query = '';
    $join = '';
    $having = '';
    $order = '';
    $args1 = [];
    $args2 = [];
    foreach ( $where as $i => $w ){
      if ( isset($fields['ids'][$w[0]]) ){
        // For id_parent, no other search for now
        if ( $w[0] === 'id_parent' ){
          $query = "AND ".$fields['ids'][$w[0]]." = ? ";
          $args = [$w[2]];
          break;
        }
        else if ( \is_array($w[2]) ){
          $query .= "AND ( ";
          foreach ( $w[2] as $j => $v ){
            if ( $j ){
              $query .= " OR ";
            }
            $query .= $fields['ids'][$w[0]]." = ? ";
            array_push($args1, $v);
          }
          $query .= ") ";
        }
        else{
          $query .= " AND ".$fields['ids'][$w[0]]." $w[1] ? ";
          array_push($args1, $w[2]);
        }
      }
      else if ( isset($fields['dates'][$w[0]]) ){
        if ( strpos($w[1], 'IS ') === 0 ){
          $query .= " AND ".$fields['dates'][$w[0]]." $w[1] ";
        }
        else if ( bbn\Date::validateSQL($w[2]) ){
          if ( $w[0] !== 'deadline' ){
            $having .= " AND DATE(".$fields['dates'][$w[0]].") $w[1] ? ";
            array_push($args2, $w[2]);
          }
          else{
            $query .= " AND DATE(".$fields['dates'][$w[0]].") $w[1] ? ";
            array_push($args1, $w[2]);
          }
        }
      }
      else if ( isset($fields['nums'][$w[0]]) ){
        if ( \is_int($w[2]) ){
          $query .= " AND ".$fields['nums'][$w[0]]." $w[1] ? ";
          array_push($args1, $w[2]);
        }
      }
      else if ( isset($fields['texts'][$w[0]]) ){
        if ( !empty($w[2]) ){
          if ( $w[0] === 'title' ){
            $query .= " AND bbn_tasks.title LIKE ? ";
            array_push($args1, "%$w[2]%");
          }
          else if ( $w[0] === 'text' ){
            $query .= " AND (bbn_tasks.title LIKE ? OR bbn_notes_versions.content LIKE ?) ";
            array_push($args1, "%$w[2]%", "%$w[2]%");
            $join .= "
        LEFT JOIN bbn_tasks_notes
          ON bbn_tasks_notes.id_task = bbn_tasks.id";
          }
        }
      }
      else if ( isset($fields['users'][$w[0]]) ){
        if ( !empty($w[2]) ){
          if ( $w[0] === 'my_user' ){
            $query .= " AND user_role.id_user = ?";
            array_push($args1, hex2bin($w[2]));
            $join .= "
        JOIN bbn_tasks_roles AS user_role
          ON user_role.id_task = bbn_tasks.id";
          }
          else if ( ($w[0] === 'my_group') && ($usr = bbn\User::getInstance()) ){
            $usr_table = $usr->getTables()['users'];
            $usr_fields = $usr->getFields('users');
            $query .= " AND `".$usr_table."`.`".$usr_fields['id_group']."` = ? ";
            array_push($args1, hex2bin($w[2]));
            $join .= "
        JOIN bbn_tasks_roles AS group_role
          ON group_role.id_task = bbn_tasks.id
        JOIN `".$usr_table."`
          ON bbn_tasks_roles.id_user = `".$usr_table."`.`".$usr_fields['id']."`";
          }
        }
      }
      else if ( isset($fields['refs'][$w[0]]) ){
        if ( \is_int($w[2]) ){
          $having .= " AND ".$fields['refs'][$w[0]]." $w[1] ? ";
          array_push($args1, $w[2]);
        }
      }
    }
    foreach ( $fields as $i => $f ){
      foreach ( $f as $n => $g ){
        if ( isset($sort[$n]) ){
          $order = '`'.$n.'`'.( strtolower($sort[$n]) === 'desc' ? ' DESC' : ' ASC').', ';
        }
      }
    }
    if ( !empty($order) ){
      $order = "ORDER BY ".substr($order, 0, -2);
    }
    $args0 = [
      hex2bin($this->idState('closed')),
      hex2bin($this->id_user)
    ];
    $sql = "
      SELECT my_role.role, bbn_tasks.*,
      FROM_UNIXTIME(MAX(bbn_tasks_logs.chrono)) AS `last_action`,
      COUNT(children.id) AS num_children,
      COUNT(DISTINCT bbn_tasks_notes.id_note) AS num_notes,
      {$this->references_select}
      IF(bbn_tasks.`state` = ?, MAX(bbn_tasks_logs.chrono), UNIX_TIMESTAMP()) - MIN(bbn_tasks_logs.chrono) AS duration
      FROM bbn_tasks
        LEFT JOIN bbn_tasks_roles AS my_role
          ON my_role.id_task = bbn_tasks.id
          AND my_role.id_user = ?
        LEFT JOIN bbn_tasks_roles
          ON bbn_tasks_roles.id_task = bbn_tasks.id
        JOIN bbn_tasks_logs
          ON bbn_tasks_logs.id_task = bbn_tasks_roles.id_task
        LEFT JOIN bbn_tasks_notes
          ON bbn_tasks_notes.id_task = bbn_tasks.id
        LEFT JOIN bbn_tasks AS children
          ON bbn_tasks_roles.id_task = children.id_parent
        $join
        {$this->references_join}
      WHERE bbn_tasks.active = 1
      $query
      GROUP BY bbn_tasks.id
      HAVING 1
      $having
      $order";
    //die(X::dump($sql));
    if ( !isset($args) ){
      $args = array_merge($args0, $args1, $args2);
    }
    $data = $this->db->getRows($sql." LIMIT $start, $num", $args);
    /** @var bbn\User $user */
    $user = bbn\User::getInstance();
    foreach ( $data as $i => $d ){
      if ( $this->template ){
        if ( $d['reference'] ){
          /** @todo How do I get the t1able with the way I made the request??! */
          $data[$i]['reference'] = \call_user_func($this->template, $this->db, $d['reference'], '');
        }
      }
    }
    return [
      'data' => $data,
      'total' => $this->db->getOne("SELECT COUNT(*) FROM ($sql) AS t", $args),
      'start' => $start,
      'limit' => $num
    ];
  }

  public function searchInTask($st){
    return $this->db->rselectAll('bbn_tasks', ['id', 'title', 'creation_date'], [['title',  'LIKE', '%'.$st.'%']]);
  }

  public function fullInfo($id){

  }

  public function infoRoles($id){
    $r = [];
    if (
      ($opt = bbn\Appui\Option::getInstance()) &&
      ($roles = self::getAppuiOptions('roles'))
    ){
      $all = $this->db->rselectAll(
        'bbn_tasks_roles',
        [],
        ['id_task' => $id],
        ['role' => 'ASC']);
      $n = false;
      foreach ( $all as $a ){
        $code = X::getField($roles, ['id' => $a['role']], 'code');
        if ( $n !== $code ){
          $n = $code;
          $r[$n] = [];
        }
        array_push($r[$n], $a['id_user']);
      }
    }
    return $r;
  }

  public function hasRole($id_task, $id_user){
    if ( $opt = bbn\Appui\Option::getInstance() ){
      $r = $this->db->selectOne('bbn_tasks_roles', 'role', ['id_task' => $id_task, 'id_user' => $id_user]);
      if ( $r ){
        return $opt->code($r);
      }
    }
    return false;
  }

  public function getComments($id_task){
    if ( $this->exists($id_task) ){
      $note = new \bbn\Appui\Note($this->db);
      $ids = $this->getCommentsIds($id_task);
      $r = [];
      foreach ( $ids as $id_note ){
        array_push($r, $note->get($id_note));
      }
      return $r;
    }
    return false;
  }

  public function getComment($id_task, $id_note){
    if ( $this->exists($id_task) ){
      $note = new \bbn\Appui\Note($this->db);
      return $note->get($id_note);
    }
    return false;
  }

  public function getUsers($id_task){
    return $this->db->getColumnValues('bbn_tasks_roles', 'id_user', ['id_task' => $id_task]);
  }

  public function getDeciders($id_task){
    if (
      $this->exists($id_task) &&
      ($role = $this->idRole('deciders'))
    ){
      return $this->db->getColumnValues('bbn_tasks_roles', 'id_user', [
        'id_task' => $id_task,
        'role' => $role
      ]);
    }
    return false;
  }

  public function comment($id_task, array $cfg){
    if ( $this->exists($id_task) && !empty($cfg) ){
      $note = new \bbn\Appui\Note($this->db);
      $r = $note->insert(
        (empty($cfg['title']) ? '' : $cfg['title']),
        (empty($cfg['text']) ? '' : $cfg['text']),
        \bbn\Appui\Note::getAppuiOptionId('tasks', 'types')
      );
      if ( $r ){
        $this->db->insert('bbn_tasks_notes', [
          'id_note' => $r,
          'id_task' => $id_task
        ]);
        if ( !empty($cfg['files']) ){
          $filename = '';
          $extension = '';
          $length = 0;
          foreach ( $cfg['files'] as $f ){
            $ext = \bbn\Str::fileExt($f, true);
            if (
              (\strlen($ext[0]) < $length) ||
              ($ext[1] !== $extension) ||
              (strpos($ext[0], $filename) !== 0) ||
              !preg_match('/_h[\d]+/i', substr($ext[0], $length))
            ){
              $filename = $ext[0];
              $extension = $ext[1];
              $length = \strlen($filename);
              $note->addMedia($r, $f);
            }
          }
        }
        if ( !empty($cfg['links']) ){
          foreach ( $cfg['links'] as $f ){
            $ext = \bbn\Str::fileExt($f['image'], true);
            if ( !preg_match('/_h[\d]+/i', substr($ext[0], 0)) ){
              $note->addMedia(
                $r,
                $f['image'],
                json_encode(['url' => $f['url'], 'description' => $f['desc']]),
                $f['title'],
                'link'
              );
            }
          }
        }
        $this->addLog($id_task, 'comment_insert', [$this->id_user, empty($cfg['title']) ? $cfg['text'] : $cfg['title']]);
      }
      return $r;
    }
    return false;
  }

  public function addLog($id_task, $action, array $value = []){
    if ( $this->id_user && $this->exists($id_task) ){
      $data = [
        'id_task' => $id_task,
        'id_user' => $this->id_user,
        'action' => \bbn\Str::isUid($action) ? $action : $this->idAction($action),
        'value' => empty($value) ? '' : json_encode($value),
        'chrono' => empty($this->date) ? microtime(true) : number_format((float)strtotime($this->date), 4, '.', '')
      ];
      $this->notify($data);
      return $this->db->insert('bbn_tasks_logs', $data);
    }
    return false;
  }

  public function notify(array $data){
    if ( isset($data['id_task'], $data['id_user'], $data['action']) && ($title = $this->getTitle($data['id_task'])) ){
      $text = $this->translateLog($data);
      $users = array_filter($this->getUsers($data['id_task']), function($a) use($data){
        return $a !== $data['id_user'];
      });
      $notif = new bbn\Appui\Notification($this->db);
      return $notif->insert($title, $text, null, $users);
    }
    return false;
  }

  public function exists($id_task){
    return $this->db->count('bbn_tasks', ['id' => $id_task]) ? true : false;
  }

  public function addRole($id_task, $role, $id_user = null){
    if ( $this->exists($id_task) ){
      if ( !bbn\Str::isUid($role) ){
        /*if ( substr($role, -1) !== 's' ){
          $role .= 's';
        }*/
        $role = $this->idRole($role);
      }
      if ( bbn\Str::isUid($role) && ($id_user || $this->id_user) ){
        if ( $this->db->insert('bbn_tasks_roles', [
          'id_task' => $id_task,
          'id_user' => $id_user ?: $this->id_user,
          'role' => $role
        ]) ){
          $this->addLog($id_task, 'role_insert', [$id_user ?: $this->id_user, $role]);
          return 1;
        }
      }
    }
    return 0;
  }

  public function removeRole($id_task, $id_user = null){
    if ( $this->exists($id_task) && ($id_user || $this->id_user) ){
      $role = $this->db->selectOne('bbn_tasks_roles', 'role', [
        'id_task' => $id_task,
        'id_user' => $id_user ?: $this->id_user
      ]);
      if ( $this->db->delete('bbn_tasks_roles', [
        'id_task' => $id_task,
        'id_user' => $id_user ?: $this->id_user
      ]) ){
        $this->addLog($id_task, 'role_delete', [$id_user ?: $this->id_user, $role]);
        return 1;
      }
    }
    return 0;
  }

  public function setDate($date){
    $this->date = $date;
    return $this;
  }

  public function unsetDate(){
    $this->date = false;
    return $this;
  }

  public function setUser(string $id_user){
    if ( \bbn\Str::isUid($id_user) ){
      $this->id_user = $id_user;
    }
    return $this; 
  }

  public function unsetUser(){
    if ( $user = bbn\User::getInstance() ){
      $this->id_user = $user->getId();
    }
    return $this;
  }

  public function insert(array $cfg){
    if ( isset($cfg['title'], $cfg['type']) ){
      if ( $this->db->insert('bbn_tasks', [
        'title' => $cfg['title'],
        'type' => $cfg['type'],
        'priority' => $cfg['priority'] ?? 5,
        'id_parent' => $cfg['id_parent'] ?? NULL,
        'id_alias' => $cfg['id_alias'] ?? NULL,
        'deadline' => $cfg['deadline'] ?? NULL,
        'id_user' => $this->id_user ?: NULL,
        'state' => $cfg['state'] ?? $this->idState('opened'),
        'creation_date' => $this->date ?: date('Y-m-d H:i:s'),
        'private' => $cfg['private'] ?? 0,
        'cfg' => \json_encode(['widgets' => []])
      ]) ){
        $id = $this->db->lastId();
        $this->addLog($id, 'insert');
        $this->addRole($id, 'managers');
        /*
        $subject = "Nouveau bug posté par {$this->user}";
        $text = "<p>{$this->user} a posté un nouveau bug</p>".
          "<p><strong>$title</strong></p>".
          "<p>".nl2br($text)."</p>".
          "<p><em>Rendez-vous dans votre interface APST pour lui répondre</em></p>";
        $this->email($id, $subject, $text);
        */
        return $id;
      }
    }
    return false;
  }

  public function update($id_task, $prop, $value){
    if ( $this->exists($id_task) ){
      $ok = false;
      if ( $prop === 'deadline' ){
        $prev = $this->db->selectOne('bbn_tasks', 'deadline', ['id' => $id_task]);
        if ( !$prev && $value ){
          $this->addLog($id_task, 'deadline_insert', [$value]);
          $ok = 1;
        }
        else if ( $prev && !$value ){
          $this->addLog($id_task, 'deadline_delete', [$value]);
          $ok = 1;
        }
        if ( $prev && $value && ($prev !== $value) ){
          $this->addLog($id_task, 'deadline_update', [$prev, $value]);
          $ok = 1;
        }
      }
      else if ( $prop === 'price' ){
        $prev = $this->db->selectOne('bbn_tasks', 'price', ['id' => $id_task]);
        if ( !$prev && $value ){
          $this->addLog($id_task, 'price_insert', [$value]);
          $ok = 1;
        }
        else if ( $prev && !$value ){
          $this->addLog($id_task, 'price_delete', [$prev]);
          $ok = 1;
        }
        if ( $prev && $value && ($prev !== $value) ){
          $this->addLog($id_task, 'price_update', [$prev, $value]);
          $ok = 1;
        }
      }
      else if ( $prop === 'state' ){
        $states = $this->states();
        switch ( $value ){
          case $states['closed']:
            $ok = 1;
            $this->addLog($id_task, 'task_close');
            $this->stopAllTracks($id_task);
            break;
          case $states['holding']:
            $ok = 1;
            $this->addLog($id_task, 'task_hold');
            $this->stopAllTracks($id_task);
            break;
          case $states['ongoing']:
            $ok = 1;
            $this->addLog($id_task, 'task_start');
            break;
          case $states['opened']:
            $ok = 1;
            $this->addLog($id_task, 'task_reopen');
            break;
          case $states['unapproved']:
            $this->addLog($id_task, 'task_unapproved');
            $this->stopAllTracks($id_task);
            $ok = 1;
            break;
        }
      }
      else if ( $prev = $this->db->selectOne('bbn_tasks', $prop, ['id' => $id_task]) ){
        $ok = 1;
        $this->addLog($id_task, $prop.'_update', [$prev, $value]);
      }
      if ( $ok ){
        return $this->db->update('bbn_tasks', [$prop => $value], ['id' => $id_task]);
      }
    }
    return false;
  }

  public function delete($id){
    if ( 
      ($info = $this->info($id)) &&
      $this->db->update('bbn_tasks', ['active' => 0], ['id' => $id]) 
    ){
      $this->addLog($id, 'delete');
      /* $subject = "Suppression du bug $info[title]";
      $text = "<p>{$this->user} a supprimé le bug<br><strong>$info[title]</strong></p>";
      $this->email($id, $subject, $text); */
      return $id;
    }
  }

  public function approve($id){
    if (
      $this->exists($id) &&
      ($info = $this->db->select('bbn_tasks', ['state', 'price'], ['id' => $id])) &&
      ($unapproved = $this->idState('unapproved')) &&
      ($deciders = $this->getDeciders($id)) &&
      in_array($this->id_user, $deciders)
    ){
      return ($info->state === $unapproved) && $this->addLog($id, 'price_approved', [$info->price]);
    }
    return false;
  }

  public function up($id){
    if ( $info = $this->info($id) ){
      return $this->update($id, $info['title'], $info['status'], $info['priority']-1, $info['deadline']);
    }
  }

  public function down($id){
    if ( $info = $this->info($id) ){
      return $this->update($id, $info['title'], $info['status'], $info['priority']+1, $info['deadline']);
    }
  }

  public function subscribe($id){
    return $this->db->insert('bbn_tasks_cc', ['id_user' => $this->id_user, 'id_task' => $id]);
  }

  public function unsubscribe($id){
    return $this->db->delete('bbn_tasks_cc', ['id_user' => $this->id_user, 'id_task' => $id]);
  }

  public function ping($id){
    return $this->addLog($id, 'task_ping');
  }

  public function startTrack($id_task, $id_user = false){
    if (
      !$this->getActiveTrack($id_user) &&
      ($ongoing = $this->idState('ongoing')) &&
      ($ongoing === $this->getState($id_task)) &&
      ($role = $this->hasRole($id_task, $id_user ?: $this->id_user)) &&
      (($role === 'managers') || ($role === 'workers'))
    ){
      return $this->db->insert('bbn_tasks_sessions', [
        'id_task' => $id_task,
        'id_user' => $id_user ?: $this->id_user,
        'start' => date('Y-m-d H:i:s')
      ]);
    }
    return false;
  }

  /**
   * Stops a track.
   *
   * @param  string  $id_task The task's ID
   * @param  boolean|string $message The message to attach to track (optional)
   * @param  boolean|string $id_user The track's user. If you give 'false', it will use the current user
   * @return boolean
   */
  public function stopTrack($id_task, $message = false, $id_user = false){
    $ok = false;
    $now = time();
    if (
      ($active_track = $this->getActiveTrack($id_user)) &&
      ($active_track['id_task'] === $id_task)
    ){
      $ok = true;
      if (
        !empty($message) &&
        !($id_note = $this->comment($id_task, [
          'title' => X::_('Report tracker').' '.date('d M Y H:i', strtotime($active_track['start'])).' - '.date('d M Y H:i', $now),
          'text' => $message
        ]))
      ){
        $ok = false;
      }
      if ( $ok ){
        $ok = $this->db->update('bbn_tasks_sessions', [
          'length' => $now - strtotime($active_track['start']),
          'id_note' => $id_note ?: NULL
        ], [
          'id' => $active_track['id']
        ]);
      }
    }
    return (bool)$ok;
  }

  public function stopAllTracks($id){
    $this->db->query("
      UPDATE bbn_tasks_sessions
      SET `length` = TO_SECONDS(NOW())-TO_SECONDS(start)
      WHERE id_task = ?
        AND `length` IS NULL",
      hex2bin($id)
    );
    return $this->db->getOne("
      SELECT COUNT(*)
      FROM bbn_tasks_sessions
      WHERE id_task = ?
        AND `length` IS NULL",
      hex2bin($id)
    ) === 0;
  }

  public function getActiveTrack($id_user = false){
    return $this->db->getRow("
      SELECT *
      FROM bbn_tasks_sessions
      WHERE id_user = ?
        AND length IS NULL",
      $id_user ? hex2bin($id_user) : hex2bin($this->id_user)
    );
  }

  public function getTrack($id_task){
    return $this->db->getRow("
      SELECT *
      FROM bbn_tasks_sessions
      WHERE id_user = ?
        AND id_task = ?
        AND length IS NULL",
      hex2bin($this->id_user),
      hex2bin($id_task)
    );
  }

  public function getTracks($id_task){
    return $this->db->getRows("
      SELECT id_user, SUM(length) AS total_time, COUNT(id_note) as num_notes
      FROM bbn_tasks_sessions
      WHERE id_task = ?
      GROUP BY id_user",
      hex2bin($id_task)
    );
  }

  public function getTasksTracks($id_user){
    if (
      ($manager = $this->idRole('managers')) &&
      ($worker = $this->idRole('workers')) &&
      ($ongoing = $this->idState('ongoing'))
    ){
      return $this->db->getRows("
        SELECT bbn_tasks.*
        FROM bbn_tasks
        	JOIN bbn_tasks_roles
        		ON bbn_tasks_roles.id_task = bbn_tasks.id
        		AND bbn_tasks_roles.id_user = ?
        		AND (
              bbn_tasks_roles.role = ?
        			OR bbn_tasks_roles.role = ?
        		)
        WHERE bbn_tasks.active = 1
          AND bbn_tasks.state = ?
        GROUP BY bbn_tasks.id",
        hex2bin($id_user),
        hex2bin($manager),
        hex2bin($worker),
        hex2bin($ongoing)
      );
    }
  }

  public function getInvoice($id_task){
    if ( $id_invoice = $this->db->selectOne('bbn_tasks_invoices', 'id_invoice', ['id_task' => $id_task]) ){
      return $this->db->rselect('bbn_invoices', [], ['id' => $id_invoice]);
    }
    return false;
  }

  public function getCfg(string $id): array
  {
    if ($cfg = $this->db->selectOne('bbn_tasks', 'cfg', ['id' => $id])) {
      return \json_decode($cfg, true);
    }
    return [];
  }

  public function setCfg(string $id, array $cfg): bool
  {
    return (bool)$this->db->update('bbn_tasks', ['cfg' => \json_encode($cfg)], ['id' => $id]);
  }

  public function addWidget(string $id, string $code): bool
  {
    return $this->toggleWidget($id, $code);
  }

  public function removeWidget(string $id, string $code): bool
  {
    return $this->toggleWidget($id, $code, false);
  }

  private function toggleWidget(string $id, string $code, bool $state = true): bool
  {
    $cfg = $this->getCfg($id);
    if (!isset($cfg['widgets'])) {
      $cfg['widgets'] = [];
    }
    $cfg['widgets'][$code] = empty($state) ? 0 : 1;
    return $this->setCfg($id, $cfg);
  }

}
