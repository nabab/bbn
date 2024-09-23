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
use bbn\Str;

class Task extends bbn\Models\Cls\Db
{

  use bbn\Models\Tts\References,
      bbn\Models\Tts\Optional;

  private
    $columns,
    $tokensConfig;

  protected
    $noteCls,
    $template = false,
    $id_user,
    $is_dev,
    $mgr,
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
    $this->noteCls = new \bbn\Appui\Note($this->db);
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
      ($states = self::getOptionsIds('states')) &&
      ($roles = self::getOptionsIds('roles')) &&
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

  public function getTitle(string $idTask, bool $simple = false): string
  {
    if ($title = $this->db->selectOne([
      'table' => 'bbn_tasks',
      'fields' => ['bbn_notes_versions.title'],
      'join' => [[
        'table' => 'bbn_notes_versions',
        'on' => [
          'conditions' => [[
            'field' => 'bbn_notes_versions.id_note',
            'exp' => 'bbn_tasks.id_note'
          ], [
            'field' => 'bbn_notes_versions.latest',
            'value' => 1
          ]]
        ]
      ]],
      'where' => [
        'conditions' => [[
          'field' => 'bbn_tasks.id',
          'value' => $idTask
        ]]
      ]
    ])) {
      return (!empty($simple) ? (X::_("Task")." ") : '').$title;
    }
    return '';
  }

  public function setTitle(string $idTask, string $title): bool
  {
    if (($idNote = $this->getIdNote($idTask))
      && ($n = $this->noteCls->get($idNote))
      && ($title !== $n['title'])
    ) {
      return (bool)$this->noteCls->insertVersion($idNote, $title, $n['content'], $this->noteCls->getExcerpt($title, $n['content']));
    }
    return false;
  }

  public function getContent($id_task){
    return $this->db->selectOne([
      'table' => 'bbn_tasks',
      'fields' => ['bbn_notes_versions.content'],
      'join' => [[
        'table' => 'bbn_notes_versions',
        'on' => [
          'conditions' => [[
            'field' => 'bbn_notes_versions.id_note',
            'exp' => 'bbn_tasks.id_note'
          ], [
            'field' => 'bbn_notes_versions.latest',
            'value' => 1
          ]]
        ]
      ]],
      'where' => [
        'conditions' => [[
          'field' => 'bbn_tasks.id',
          'value' => $id_task
        ]]
      ]
    ]) ?: '';
  }

  public function setContent(string $idTask, string $content): bool
  {
    if (($idNote = $this->getIdNote($idTask))
      && ($n = $this->noteCls->get($idNote))
      && ($content !== $n['content'])
    ) {
      return (bool)$this->noteCls->insertVersion($idNote, $n['title'], $content, $this->noteCls->getExcerpt($n['title'], $content));
    }
    return false;
  }

  public function getType(string $idTask): ?string
  {
    return $this->db->selectOne('bbn_tasks', 'type', ['id' => $idTask]);
  }

  public function categories(){
    return self::getOptionsTree('cats');
  }

  public function actions(){
    return self::getOptionsIds('actions');
  }

  public function states(){
    return self::getOptionsIds('states');
  }

  public function roles(){
    return self::getOptionsIds('roles');
  }

  public function idCat($code){
    return self::getOptionId($code, 'cats');
  }

  public function idAction($code){
    return self::getOptionId($code, 'actions');
  }

  public function idState($code){
    return self::getOptionId($code, 'states');
  }

  public function idRole($code){
    return self::getOptionId($code, 'roles');
  }

  public function idPrivilege($code){
    return self::getOptionId($code, 'privileges');
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
          case 'content':
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

  public function getApprovedLog($id): array
  {
    if (
      $this->exists($id) &&
      ($action = $this->idAction('price_approved'))
    ){
      return $this->db->rselect('bbn_tasks_logs', [], [
        'id_task' => $id,
        'action' => $action
      ], ['chrono' => 'DESC']) ?: [];
    }
    return [];
  }

  public function getPriceLog($id): array
  {
    if ($this->exists($id)
      && ($action_ins = $this->idAction('price_insert'))
      && ($action_upd = $this->idAction('price_update'))
      && ($action_del = $this->idAction('price_delete'))
    ){
      return $this->db->rselect([
        'table' => 'bbn_tasks_logs',
        'where' => [
          'conditions' => [[
            'field' => 'id_task',
            'value' => $id
          ], [
            'logic' => 'OR',
            'conditions' => [[
              'field' => 'action',
              'value' => $action_ins
            ], [
              'field' => 'action',
              'value' => $action_upd
            ], [
              'field' => 'action',
              'value' => $action_del
            ]]
          ]]
        ],
        'order' => [[
          'field' => 'chrono',
          'dir' => 'DESC'
        ]]
      ]) ?: [];
    }
  }

  public function getPrice(string $id){
    return $this->db->selectOne('bbn_tasks', 'price', ['id' => $id]);
  }

  public function getApprovalInfo(string $id): ?array
  {
    $approved = null;
    if (!empty($this->getPrice($id))) {
      $lastChangePrice = $this->getPriceLog($id) ?: null;
      if (!empty($lastChangePrice)
        && \bbn\Str::isJson($lastChangePrice['value'])
      ) {
        $lastChangePrice['value'] = \json_decode($lastChangePrice['value'], true);
        if (\is_array($lastChangePrice['value'])) {
          $lastChangePrice['value'] = $lastChangePrice['value'][0];
        }
      }
      $approved = $this->getApprovedLog($id) ?: null;
      if (!empty($approved)
        && \bbn\Str::isJson($approved['value'])
      ) {
        $approved['value'] = \json_decode($approved['value'], true);
        if (\is_array($approved['value'])) {
          $approved['value'] = $approved['value'][0];
        }
      }
      if (!empty($lastChangePrice)
        && !empty($approved)
        && ($lastChangePrice['chrono'] > $approved['chrono'])
      ){
        $approved = null;
      }
    }
    else if ($children = $this->getChildrenIds($id)) {
      $fromChildren = [];
      foreach ($children as $child) {
        if ($c = $this->getApprovalInfo($child)) {
          $fromChildren[] = $c;
        }
      }
      if (!empty($fromChildren)) {
        $fromChildren = X::sortBy($fromChildren, 'chrono', 'desc');
        $approved = $fromChildren[0];
      }
    }
    return $approved;
  }

  public function getList($parent = null, $status = 'opened|ongoing|holding', $id_user = false, $order = 'priority', $dir = 'ASC', $limit = 1000, $start = 0){
    $orders_ok = [
      'id' => 'bbn_tasks.id',
      'last' => 'last',
      'first' => 'first',
      'duration' => 'duration',
      'num_children' => 'num_children',
      'title' => 'notevers.title',
      'content' => 'notevers.content',
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
          array_push($where, "`bbn_tasks`.`state` = 0x$t");
        }
      }
    }
    $where = \count($where) ? implode( " OR ", $where) : '';
    $sql = "
    SELECT `role`, bbn_tasks.*,
    notevers.title AS title,
    notevers.content AS content,
    FROM_UNIXTIME(MIN(bbn_tasks_logs.chrono)) AS `first`,
    FROM_UNIXTIME(MAX(bbn_tasks_logs.chrono)) AS `last`,
    {$this->references_select}
    COUNT(children.id) AS num_children,
    COUNT(DISTINCT bbn_tasks_notes.id_note) AS num_notes,
    MAX(bbn_tasks_logs.chrono) - MIN(bbn_tasks_logs.chrono) AS duration
    FROM bbn_tasks_roles
      JOIN bbn_tasks
        ON bbn_tasks_roles.id_task = bbn_tasks.id
      JOIN bbn_notes_versions AS notevers
        ON notevers.id_note = bbn_tasks.id_note
        AND notevers.latest = 1
      JOIN bbn_tasks_logs
        ON bbn_tasks_logs.id_task = bbn_tasks_roles.id_task
      LEFT JOIN bbn_tasks_notes
        ON bbn_tasks_notes.id_task = bbn_tasks_roles.id_task
        AND bbn_tasks_notes.active = 1
      LEFT JOIN bbn_tasks AS children
        ON bbn_tasks.id = children.id_parent
        AND bbn_tasks.active = 1
      {$this->references_join}
    WHERE bbn_tasks_roles.id_user = ?".
      (empty($where) ? '' : " AND ($where)")."
    AND bbn_tasks.active = 1
    AND bbn_tasks.id_alias IS NULL
    AND bbn_tasks.id_parent ".( \is_null($parent) ? "IS NULL" : "= $parent" )."
    GROUP BY bbn_tasks_roles.id_task
    LIMIT $start, $limit";

    $opt = bbn\Appui\Option::getInstance();
    $res = $this->db->getRows($sql, hex2bin($id_user));
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
      'title' => 'notevers.title',
      'content' => 'notevers.content',
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
    notevers.title AS title,
    notevers.content AS content,
    FROM_UNIXTIME(MAX(bbn_tasks_logs.chrono)) AS `last_action`,
    {$this->references_select}
    COUNT(children.id) AS num_children,
    COUNT(DISTINCT bbn_tasks_notes.id_note) AS num_notes,
    IF(bbn_tasks.`state`=".$this->idState('closed').", MAX(bbn_tasks_logs.chrono), UNIX_TIMESTAMP()) - MIN(bbn_tasks_logs.chrono) AS duration
    FROM bbn_tasks
      JOIN bbn_notes_versions AS notevers
        ON notevers.id_note = bbn_tasks.id_note
        AND notevers.latest = 1
      JOIN bbn_tasks_logs
        ON bbn_tasks_logs.id_task = bbn_tasks.id
      LEFT JOIN bbn_tasks_notes
        ON bbn_tasks_notes.id_task = bbn_tasks.id
      LEFT JOIN bbn_tasks_roles
        ON bbn_tasks_roles.id_task = bbn_tasks.id
        AND bbn_tasks_roles.id_user = {$this->id_user}
      LEFT JOIN bbn_notes_versions
        ON bbn_notes_versions.id_note = bbn_tasks_notes.id_note
        AND bbn_tasks_notes.active =1
      LEFT JOIN bbn_tasks AS children
        ON children.id = bbn_tasks.id
        AND children.active = 1
      {$this->references_join}
    WHERE (notevers.title LIKE ?
    OR notevers.content LIKE ?
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
    return $this->addNote(null, null, null);
  }

  public function info(string $id, bool $withComments = false, bool $withChildren = true): ?array
  {
    if ($info = $this->db->rselect('bbn_tasks', [], ['id' => $id])) {
      $info['title'] = $this->getTitle($id);
      $info['content'] = $this->getContent($id);
      $info['first'] = $this->db->selectOne('bbn_tasks_logs', 'chrono', [
        'id_task' => $id,
        'action' => $this->idAction('insert')
      ], ['chrono' => 'ASC']);
      $info['last'] = $this->db->selectOne('bbn_tasks_logs', 'chrono', [
        'id_task' => $id,
      ], ['chrono' => 'DESC']);
      $info['last_action'] = !empty($info['last']) ? date('Y-m-d H:i:s', $info['last']) : $info['creation_date'];
      $info['roles'] = $this->infoRoles($id);
      $roleCode = $this->hasRole($id, $this->id_user);
      $info['role'] = !empty($roleCode) ? $this->idRole($roleCode) : null;
      $info['notes'] = $withComments ? $this->getComments($id) : $this->getCommentsIds($id);
      $info['children'] = $withChildren ? $this->getChildren($id) : $this->getChildrenIds($id);
      $info['children_price'] = $this->getChildrenPrices($id);
      $info['children_noprice'] = $this->getChildrenNoPrice($id);
      $info['num_children_noprice'] = count($info['children_noprice']);
      $info['parent_has_price'] = $this->parentHasPrice($id, true);
      $info['parent_unapproved'] = $this->parentIsUnapproved($id, true);
      $info['approved'] = $this->getApprovalInfo($id);
      $info['aliases'] = $this->db->rselectAll([
        'table' => 'bbn_tasks',
        'fields' => [
          'bbn_tasks.id',
          'bbn_notes_versions.title'
        ],
        'join' => [[
          'table' => 'bbn_notes_versions',
          'on' => [
            'conditions' => [[
              'field' => 'bbn_notes_versions.id_note',
              'exp' => 'bbn_tasks.id_note'
            ], [
              'field' => 'bbn_notes_versions.latest',
              'value' => 1
            ]]
          ]
        ]],
        'where' => [
          'bbn_tasks.id_alias' => $id,
          'bbn_tasks.active' => 1
        ]
      ]);
      $info['num_notes'] = \count($info['notes']);
      $info['num_children'] = \count($info['children']);
      $info['has_children'] = !empty($info['num_children']);
      $info['reference'] = null;
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
        $info['parent'] = $this->info($info['id_parent'], $withComments, false);
      }

      $info['tokens_category'] = $this->getTokensCategory($id);
      return $info;
    }
    return null;
  }

  public function getChildrenIds(string $id, bool $includeDeleted = false): ?array
  {
    $where = [[
      'field' => 'id_parent',
      'value' => $id
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
    ]];
    if (!$includeDeleted) {
      $where[] = [
        'field' => 'active',
        'value' => 1
      ];
    }
    return $this->db->getColumnValues([
      'table' => 'bbn_tasks',
      'fields' => ['id'],
      'where' => [
        'conditions' => $where
      ],
      'order' => [
        'creation_date' => 'DESC'
      ]
    ]);
  }

  public function getChildren(string $id, bool $includeDeleted = false): array
  {
    if ($children = $this->getChildrenIds($id, $includeDeleted)) {
      $t = $this;
      return \array_map(function($cid) use($t){
        return $t->info($cid);
      }, $children);
    }
    return [];
  }

  public function getChildrenPrices(string $id, bool $deep = true): float
  {
    $total = 0;
    if ($children = $this->getChildrenIds($id)) {
      foreach ($children as $child) {
        if ($p = $this->getPrice($child)){
          $total += $p;
        }
        else if ($deep && ($subChildren = $this->getChildrenIds($child))) {
          foreach ($subChildren as $sc) {
            if ($scp = $this->getPrice($sc)) {
              $total += $scp;
            }
            else if ($this->getChildrenIds($sc)) {
              $total += $this->getChildrenPrices($sc);
            }
          }
        }
      }
    }
    return $total;
  }

  public function getChildrenNoPrice(string $id, bool $deep = true): array
  {
    $res = [];
    if (!$this->getPrice($id)
      && ($children = $this->getChildrenIds($id))
    ) {
      $hasPrice = false;
      foreach ($children as $child) {
        if ($this->getPrice($child) || $this->getChildrenPrices($child, $deep)) {
          $hasPrice = true;
        }
      }
      if ($hasPrice) {
        foreach ($children as $child) {
          if (!$deep) {
            if (!$this->getPrice($child)) {
              $res[] = $child;
            }
          }
          else {
            if (!$this->getPrice($child) && !$this->getChildrenPrices($child)) {
              $res[] = $child;
            }
          }
        }
      }
    }
    return $res;
  }

  public function getUnapprovedChildrenIds(string $id): ?array
  {
    if (!($idState = $this->idState('unapproved'))) {
      throw new \Exception(X::_('No state found with the code unapproved'));
    }
    return $this->db->getColumnValues('bbn_tasks', 'id', [
      'id_parent' => $id,
      'state' => $idState,
      'active' => 1
    ]);
  }

  public function parentHasPrice(string $id, bool $top = false)
  {
    if ($idParent = $this->getIdParent($id)) {
      if ($this->getPrice($idParent)) {
        return true;
      }
      if ($top) {
        return $this->parentHasPrice($idParent, true);
      }
    }
    return false;
  }

  public function parentIsUnapproved(string $id, bool $top = false)
  {
    if ($idParent = $this->getIdParent($id)) {
      if ($this->idState('unapproved') === $this->getState($idParent)) {
        return true;
      }
      if ($top) {
        return $this->parentIsUnapproved($idParent, true);
      }
    }
    return false;
  }

  public function getState($id){
    if ( $this->exists($id) ){
      return $this->db->selectOne('bbn_tasks', 'state', ['id' => $id]);
    }
  }

  public function getIdParent(string $id): ?string
  {
    return $this->db->selectOne('bbn_tasks', 'id_parent', ['id' => $id]);
  }

  public function getIdRoot(string $id): ?string
  {
    if ($idParent = $this->getIdParent($id)) {
      return $this->getIdParent($idParent) ?: $idParent;
    }
    return $idParent;
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
      if (\is_array($c)) {
        array_push($res, $c);
      }
      else if (($i === 'text')
        || ($i === 'title')
        || ($i === 'content')
      ) {
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
        'title' => 'bbn_notes_versions.title',
        'content' => 'bbn_notes_versions.content',
        'text' => 'notever.content'
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
            $query .= " AND bbn_notes_versions.title LIKE ? AND bbn_notes_versions.latest = 1 ";
            array_push($args1, "%$w[2]%");
          }
          else if ( $w[0] === 'content' ){
            $query .= " AND bbn_notes_versions.content LIKE ? AND bbn_notes_versions.latest = 1 ";
            array_push($args1, "%$w[2]%");
          }
          else if ( $w[0] === 'text' ){
            $query .= " AND ((bbn_notes_versions.title LIKE ? OR bbn_notes_versions.content LIKE ?) AND bbn_notes_versions.latest = 1 ";
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
        if (\is_int($w[2]) || Str::isUid($w[2])) {
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
      SELECT my_role.role,
      bbn_tasks.*,
      bbn_notes_versions.title,
      bbn_notes_versions.content,
      FROM_UNIXTIME(MAX(bbn_tasks_logs.chrono)) AS `last_action`,
      COUNT(children.id) AS num_children,
      COUNT(DISTINCT bbn_tasks_notes.id_note) AS num_notes,
      {$this->references_select}
      IF(bbn_tasks.`state` = ?, MAX(bbn_tasks_logs.chrono), UNIX_TIMESTAMP()) - MIN(bbn_tasks_logs.chrono) AS duration
      FROM bbn_tasks
        JOIN bbn_notes_versions
          ON bbn_notes_versions.id_note = bbn_tasks.id_note
          AND bbn_notes_versions.latest = 1
        LEFT JOIN bbn_tasks_roles AS my_role
          ON my_role.id_task = bbn_tasks.id
          AND my_role.id_user = ?
        LEFT JOIN bbn_tasks_roles
          ON bbn_tasks_roles.id_task = bbn_tasks.id
        JOIN bbn_tasks_logs
          ON bbn_tasks_logs.id_task = bbn_tasks_roles.id_task
        LEFT JOIN bbn_tasks_notes
          ON bbn_tasks_notes.id_task = bbn_tasks.id
          AND bbn_tasks_notes.active = 1
        LEFT JOIN bbn_tasks AS children
          ON bbn_tasks.id = children.id_parent
          AND children.active = 1
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

    if (!empty($num)) {
      $sql .= " LIMIT $start, $num";
    }

    $data = $this->db->getRows($sql, $args);
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
      'total' => !empty($num) ? $this->db->getOne("SELECT COUNT(*) FROM ($sql) AS t", $args) : count($data),
      'start' => $start,
      'limit' => $num
    ];
  }

  public function searchInTask($st){
    return $this->db->rselectAll([
      'table' => 'bbn_tasks',
      'fields' => [
        'bbn_tasks.id',
        'bbn_notes_versions.title',
        'bbn_notes_versions.content',
        'bbn_tasks.creation_date'
      ],
      'join' => [[
        'table' => 'bbn_notes_versions',
        'on' => [
          'conditions' => [[
            'field' => 'bbn_notes_versions.id_note',
            'exp' => 'bbn_tasks.id_note'
          ], [
            'field' => 'bbn_notes_versions.latest',
            'value' => 1
          ]]
        ]
      ]],
      'where' => [
        'conditions' => [[
          'field' => 'bbn_tasks.active',
          'value' => 1
        ], [
          'logic' => 'OR',
          'conditions' => [[
            'field' => 'bbn_notes_versions.title',
            'operator' => 'contains',
            'value' => $st
          ], [
            'field' => 'bbn_notes_versions.content',
            'operator' => 'contains',
            'value' => $st
          ]]
        ]]
      ]
    ]);
  }

  public function fullInfo($id){

  }

  public function infoRoles($id){
    $r = [];
    if ($roles = self::getOptions('roles')) {
      $userCfg = bbn\User::getInstance()->getClassCfg();
      $optCfg = bbn\Appui\Option::getInstance()->getClassCfg();
      $all = $this->db->rselectAll([
        'table' => 'bbn_tasks_roles',
        'fields' => [],
        'join' => [[
          'table' => $optCfg['table'],
          'on' => [
            'conditions' => [[
              'field' => $this->db->cfn($optCfg['arch']['options']['id'], $optCfg['table']),
              'exp' => 'bbn_tasks_roles.role'
            ]]
          ]
        ], [
          'table' => $userCfg['table'],
          'on' => [
            'conditions' => [[
              'field' => $this->db->cfn($userCfg['arch']['users']['id'], $userCfg['table']),
              'exp' => 'bbn_tasks_roles.id_user'
            ], [
              'field' => $this->db->cfn($userCfg['arch']['users']['active'], $userCfg['table']),
              'value' => 1
            ]]
          ]
        ]],
        'where' => ['id_task' => $id],
        'order' => [$this->db->cfn($userCfg['arch']['users']['username'], $userCfg['table']) => 'asc']
      ]);
      foreach ( $all as $a ){
        $code = X::getField($roles, ['id' => $a['role']], 'code');
        if (!isset($r[$code])) {
          $r[$code] = [];
        }
        $r[$code][] = $a['id_user'];;
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

  public function getDeciders(string $idTask, bool $top = false): array
  {
    if ($this->exists($idTask)
      && ($role = $this->idRole('deciders'))
    ){
      $deciders = $this->db->getColumnValues('bbn_tasks_roles', 'id_user', [
        'id_task' => $idTask,
        'role' => $role
      ]) ?: [];
      if ($top && ($idParent = $this->getIdParent($idTask))) {
        $deciders = X::mergeArrays($deciders, $this->getDeciders($idParent, true));
      }
      return \array_unique($deciders);
    }
    return [];
  }

  public function comment($id_task, array $cfg){
    if ( $this->exists($id_task) && !empty($cfg) ){
      $note = new \bbn\Appui\Note($this->db);
      $r = $note->insert(
        (empty($cfg['title']) ? '' : $cfg['title']),
        (empty($cfg['text']) ? '' : $cfg['text']),
        \bbn\Appui\Note::getOptionId('tasks', 'types')
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
                ['url' => $f['url'], 'description' => $f['desc']],
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
      //$this->notify($data);
      return $this->db->insert('bbn_tasks_logs', $data);
    }
    return false;
  }

  public function notify(array $data){
    if ( isset($data['id_task'], $data['id_user'], $data['action']) && ($title = $this->getTitle($data['id_task'])) ){
      $text = $this->translateLog($data);
      $users = \array_values(\array_filter($this->getUsers($data['id_task']), function($a) use($data){
        return $a !== $data['id_user'];
      }));
      if (!empty($users)) {
        $notif = new bbn\Appui\Notification($this->db);
        return $notif->insert($title, $text, null, $users, true);
      }
    }
    return false;
  }

  public function exists($id_task){
    return $this->db->count('bbn_tasks', ['id' => $id_task]) ? true : false;
  }

  public function isDeleted(string $idTask): bool
  {
    return $this->exists($idTask)
      && !$this->db->selectOne('bbn_tasks', 'active', ['id' => $idTask]);
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

  public function insert(array $cfg, bool $addRole = true){
    if (($opt = bbn\Appui\Option::getInstance())
      && ($idType = $opt->fromCode('tasks', 'types', 'note', 'appui'))
      && isset($cfg['title'], $cfg['type'])
      && ($idNote = $this->noteCls->insert($cfg['title'], $cfg['content'] ?? '', $idType))
    ) {
      $creationDate = $this->date ?: date('Y-m-d H:i:s');
      $max = $this->db->selectOne('bbn_tasks', 'MAX(easy_id)', ['YEAR(creation_date)' => date('Y', strtotime($creationDate))]);
      $easyId = !empty($max) ? $max + 1 : 1;
      if ( $this->db->insert('bbn_tasks', [
        'id_note' => $idNote,
        'type' => $cfg['type'],
        'priority' => !empty($cfg['priority']) ? $cfg['priority'] : 3,
        'id_parent' => !empty($cfg['id_parent']) ? $cfg['id_parent'] : null,
        'id_alias' => !empty($cfg['id_alias']) ? $cfg['id_alias'] : null,
        'deadline' => !empty($cfg['deadline']) ? $cfg['deadline'] : null,
        'id_user' => $this->id_user ?: null,
        'state' => !empty($cfg['state']) ? $cfg['state'] : $this->idState('opened'),
        'creation_date' => $creationDate,
        'easy_id' => $easyId,
        'private' => !empty($cfg['private']) ? 1 : 0
      ]) ){
        $id = $this->db->lastId();
        $this->addLog($id, 'insert');
        if ($addRole) {
          $this->addRole($id, 'managers');
        }
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

  public function update($idTask, $prop, $value){
    if ( $this->exists($idTask) ){
      $ok = false;
      $toDelete = false;
      $states = $this->states();
      switch ($prop) {
        case 'deadline':
          $prev = $this->db->selectOne('bbn_tasks', 'deadline', ['id' => $idTask]);
          if ( !$prev && $value ){
            $this->addLog($idTask, 'deadline_insert', [$value]);
            $ok = 1;
          }
          else if ( $prev && !$value ){
            $this->addLog($idTask, 'deadline_delete', [$value]);
            $ok = 1;
          }
          if ( $prev && $value && ($prev !== $value) ){
            $this->addLog($idTask, 'deadline_update', [$prev, $value]);
            $ok = 1;
          }
          break;
        case 'price':
          $prev = $this->db->selectOne('bbn_tasks', 'price', ['id' => $idTask]);
          if ( !$prev && $value ){
            $this->addLog($idTask, 'price_insert', [$value]);
            $ok = 1;
          }
          else if ( $prev && !$value ){
            $this->addLog($idTask, 'price_delete', [$prev]);
            $ok = 1;
          }
          if ( $prev && $value && ($prev !== $value) ){
            $this->addLog($idTask, 'price_update', [$prev, $value]);
            $ok = 1;
          }
          if ($ok) {
            $oldState = $this->getState($idTask);
          }
          break;
        case 'state':
          switch ( $value ){
            case $states['closed']:
              $ok = 1;
              $this->addLog($idTask, 'task_close');
              $this->stopAllTracks($idTask);
              break;
            case $states['holding']:
              $ok = 1;
              $this->addLog($idTask, 'task_hold');
              $this->stopAllTracks($idTask);
              break;
            case $states['ongoing']:
              $ok = 1;
              $this->addLog($idTask, 'task_start');
              break;
            case $states['opened']:
              $ok = 1;
              $this->addLog($idTask, 'task_reopen');
              break;
            case $states['unapproved']:
              $this->addLog($idTask, 'task_unapproved');
              $this->stopAllTracks($idTask);
              $ok = 1;
              break;
            case $states['canceled']:
              $this->addLog($idTask, 'task_cancel');
              $this->stopAllTracks($idTask);
              $ok = 1;
              break;
            case $states['deleted']:
              $this->stopAllTracks($idTask);
              $ok = 1;
              $toDelete = 1;
              break;
          }
          break;
        case 'title':
        case 'content':
          if (($idNote = $this->getIdNote($idTask))
            && ($n = $this->noteCls->get($idNote))
          ) {
            $title = $n['title'];
            $content = $n['content'];
            $log = '_update';
            $vals = [];
            if (($prop === 'title')
              && ($title !== $value)
            ) {
              $vals = [$title, $value];
              $title = $value;
            }
            else if (($prop === 'content')
              && ($content !== $value)
            ) {
              $prev = $content;
              if (empty($prev)) {
                $log = '_insert';
                $vals = [$value];
              }
              else if (empty($value)) {
                $log = '_delete';
              }
              else {
                $vals = [$content, $value];
              }
              $content = $value;
            }
            if ($this->noteCls->insertVersion($idNote, $title, $content, $this->noteCls->getExcerpt($title, $content))) {
              $this->addLog($idTask, $prop.$log, $vals);
              return true;
            }
          }
          break;
        default:
          if ( $prev = $this->db->selectOne('bbn_tasks', $prop, ['id' => $idTask]) ){
            $ok = 1;
            $this->addLog($idTask, $prop.'_update', [$prev, $value]);
          }
          break;
      }
      if ($ok && $this->db->update('bbn_tasks', [$prop => $value], ['id' => $idTask])) {
        if ($prop === 'price') {
          if (($idParent = $this->getIdParent($idTask))) {
            if (!empty($value)
              && ($this->getState($idParent) !== $states['unapproved'])
            ) {
              $this->update($idParent, 'state', $states['unapproved']);
            }
            else if (empty($value)
              && ($this->getState($idParent) === $states['unapproved'])
              && !$this->getUnapprovedChildrenIds($idParent)
            ) {
              $this->update($idParent, 'state', $states['opened']);
            }
          }
          $this->update($idTask, 'state', empty($value) ? $states['opened'] : $states['unapproved']);
        }
        if ($prop === 'state') {
          switch ($value) {
            case $states['unapproved']:
              if (!!$this->getPrice($idTask) && ($children = $this->getChildrenIds($idTask))) {
                foreach ($children as $child) {
                  $s = $this->getState($child);
                  if (($s !== $states['unapproved'])
                    && ($s !== $states['closed'])
                  ) {
                    $this->update($child, 'state', $states['unapproved']);
                  }
                }
              }
              if (($idParent = $this->getIdParent($idTask))
                && ($this->getState($idParent) !== $states['unapproved'])
              ) {
                $this->update($idParent, 'state', $states['unapproved']);
              }
              break;

            case $states['opened']:
              if ($children = $this->getUnapprovedChildrenIds($idTask)) {
                foreach ($children as $child) {
                  $this->update($child, 'state', $states['opened']);
                }
              }
              if (($idParent = $this->getIdParent($idTask))
                && ($this->getState($idParent) === $states['unapproved'])
                && !$this->getUnapprovedChildrenIds($idParent)
              ) {
                $this->update($idParent, 'state', $states['opened']);
              }
              break;

            case $states['canceled']:
            case $states['deleted']:
              if ($children = $this->getChildrenIds($idTask)) {
                foreach ($children as $child) {
                  $this->update($child, 'state', $value);
                }
              }
              break;
          }
        }
        if ($toDelete) {
          return $this->delete($idTask);
        }
        return true;
      }
    }
    return false;
  }

  public function delete($id): bool
  {
    return (bool)$this->db->update('bbn_tasks', ['active' => 0], ['id' => $id])
      && $this->addLog($id, 'delete');
  }

  public function approve(string $id, bool $approveChildren = true, bool $approveParent = true){
    if ($this->exists($id)
      && ($perm = \bbn\User\Permissions::getInstance())
      && ($currentState = $this->getState($id))
      && ($unapproved = $this->idState('unapproved'))
      && ($currentState === $unapproved)
      && ((($deciders = $this->getDeciders($id, true))
          && \in_array($this->id_user, $deciders))
        || (($idFinancialManager = $this->idPrivilege('financial_manager'))
          && ($idPermFM = $perm->optionToPermission($idFinancialManager))
          && $perm->has($idPermFM))
      )
    ){
      $price = $this->getPrice($id);
      if (empty($price)) {
        $price = $this->getChildrenPrices($id);
      }
      if (!($opened = $this->idState('opened'))) {
        throw new \Exception(X::_('No state found with the code opened'));
      }
      if ($approveChildren
        && ($children = $this->getUnapprovedChildrenIds($id))
      ) {
        foreach ($children as $child) {
          $this->approve($child, true, false);
        }
      }
      if (!$this->getUnapprovedChildrenIds($id)
        && (($this->getState($id) === $this->idState('opened'))
          || $this->update($id, 'state', $opened))
      ) {
        if (!empty($price)) {
          $this->addLog($id, 'price_approved', [$price]);
        }
        if ($approveParent
          && ($parent = $this->getIdParent($id))
          && ($this->getState($parent) === $unapproved)
          && !$this->getUnapprovedChildrenIds($parent)
        ) {
          $this->approve($parent, false);
        }
        return true;
      }
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

  public function startTrack(string $idTask, $idUser = false){
    if (!$this->getActiveTrack($idUser)
      && ($ongoing = $this->idState('ongoing'))
      && ($ongoing === $this->getState($idTask))
      && ($role = $this->hasRole($idTask, $idUser ?: $this->id_user))
      && (($role === 'managers') || ($role === 'workers'))
    ){
      $start = date('Y-m-d H:i:s');
      $tokens = null;
      if ($this->isTokensActive()
        && $this->getTokensCategory($idTask)
        && ($tokensCfg = $this->getTokensCfg())
        && !empty($tokensCfg['step'])
      ) {
        $type = !empty($tokensCfg['checkType']) ? $this->getType($idTask) : null;
        $lastTrack = $this->getLastStoppedTrack($idUser, $type);
        if (!empty($lastTrack)) {
          $d = strtotime($start) - strtotime($lastTrack['end']);
          if ($d < $tokensCfg['step']) {
            $this->db->update('bbn_tasks_sessions', [
              'length' => $lastTrack['length'] + $d
            ], [
              'id' => $lastTrack['id']
            ]);
            $lastTokens = $this->calcTokensRaw($lastTrack['id'], true);
            $this->db->update('bbn_tasks_sessions', [
              'tokens' => floor($lastTokens)
            ], [
              'id' => $lastTrack['id']
            ]);
            if ($lastTokens - floor($lastTokens)) {
              $tokens = 1;
            }
          }
        }
      }

      return $this->db->insert('bbn_tasks_sessions', [
        'id_task' => $idTask,
        'id_user' => $idUser ?: $this->id_user,
        'start' => $start,
        'tokens' => $tokens
      ]);
    }

    return false;
  }

  /**
   * Stops a track.
   *
   * @param  string  $idTask The task's ID
   * @param  boolean|string $message The message to attach to track (optional)
   * @param  boolean|string $idUser The track's user. If you give 'false', it will use the current user
   * @return boolean
   */
  public function stopTrack($idTask, $message = false, $idUser = false){
    $ok = false;
    $now = time();
    if (($activeTrack = $this->getActiveTrack($idUser))
      && ($activeTrack['id_task'] === $idTask)
    ) {
      $ok = true;
      if (!empty($message)
        && !($idNote = $this->comment($idTask, [
          'title' => X::_('Report tracker').' '.date('d M Y H:i', strtotime($activeTrack['start'])).' - '.date('d M Y H:i', $now),
          'text' => $message
        ]))
      ) {
        $ok = false;
      }

      if ($ok) {
        $length = $now - strtotime($activeTrack['start']);
        $tokens = null;
        if ($this->isTokensActive()
          && $this->getTokensCategory($idTask)
        ) {
          $tokens = ceil($this->calcTokens(
            $activeTrack['id'],
            $activeTrack['tokens'] === 1,
            $activeTrack['start'],
            date('Y-m-d H:i:s', $now)
          ));
        }

        $ok = $this->db->update('bbn_tasks_sessions', [
          'length' => $length,
          'tokens' => $tokens,
          'id_note' => !empty($idNote) ? $idNote : NULL
        ], [
          'id' => $activeTrack['id']
        ]);
      }
    }

    return (bool)$ok;
  }

  public function editTrack(string $id, string $start, string $end, ?string $message = null): bool
  {
    if (strtotime($end) < strtotime($start)) {
      throw new \Exception(X::_('The end date must be greater than the start date'));
    }

    $currentData = $this->getTrack($id);
    $idNote = $currentData['id_note'];
    $ok = true;
    // Message
    if (empty($message) && !empty($idNote)) {
      $this->noteCls->remove($idNote, true);
      $idNote = null;
    }
    else if (!empty($message)) {
      $title = X::_(
        'Report tracker %s - %s',
        date('d M Y H:i', strtotime($start)),
        date('d M Y H:i', strtotime($end))
      );
      if (!empty($idNote)) {
        $oldMessage = $this->noteCls->get($idNote);
        if (($oldMessage['content'] !== $message)
          || ($oldMessage['title'] !== $title)
        ) {
          $ok = $this->noteCls->update($idNote, $title, $message);
        }
      }
      else {
        $idNote = $this->comment($currentData['id_task'], [
          'title' => $title,
          'text' => $message
        ]);
        $ok = !empty($idNote);
      }
    }

    if ($ok
      && (($currentData['start'] !== $start)
        || ($currentData['end'] !== $end)
        || ($currentData['id_note'] !== $idNote))
    ) {
      $ok = (bool)$this->db->update('bbn_tasks_sessions', [
        'id_note' => !empty($idNote) ? $idNote : null,
        'start' => $start,
        'length' => strtotime($end) - strtotime($start)
      ], [
        'id' => $id
      ]);

      if (!empty($ok)
        && $this->isTokensActive()
        && $this->getTokensCategory($currentData['id_task'])
      ) {
        $tokens = $this->calcTokens($id, true, $start, $end);
        if ($tokens !== $currentData['tokens']) {
          $ok = (bool)$this->db->update('bbn_tasks_sessions', [
            'tokens' => $tokens
          ], [
            'id' => $id
          ]);
        }

        // Tokens
        $this->checkTokens($id, $currentData['start'], $currentData['end']);
        $this->checkTokens($id, $start, $end);
      }

    }

    return $ok;
  }

  public function deleteTrack(string $id): bool
  {
    if ($track = $this->getTrack($id)) {
      if ($track['id_user'] !== $this->id_user) {
        return false;
      }

      // Message
      if ($idNote = $this->getTrackIdNote($id)) {
        $this->noteCls->remove($idNote, true);
        $this->addLog($track['id_task'], 'comment_delete');
      }

      // Tokens
      if ($this->isTokensActive()
        && $this->getTokensCategory($track['id_task'])
      ) {
        $this->db->update('bbn_tasks_sessions', [
          'start' => '0000-00-00 00:00:00',
          'length' => null,
        ], [
          'id' => $id
        ]);
        $this->checkTokens($id, $track['start'], $track['end']);
      }

      return (bool)$this->db->delete('bbn_tasks_sessions', ['id' => $id]);
    }

    return true;
  }

  public function stopAllTracks($id){
    if ($this->isTokensActive()
      && $this->getTokensCategory($id)
    ) {
      if ($tracks = $this->db->getRows("
        SELECT id, start
        FROM bbn_tasks_sessions
        WHERE id_task = ?
          AND `length` IS NULL",
        hex2bin($id)
      )) {
        $now = time();
        foreach ($tracks as $track) {
          $this->db->query("
            UPDATE bbn_tasks_sessions
            SET `length` = ?
            WHERE id = ?
              AND `length` IS NULL",
            hex2bin($track['id']),
            $now - strtotime($track['start'])
          );
          $this->checkTokens($track['id']);
        }
      }
    }
    else {
      $this->db->query("
        UPDATE bbn_tasks_sessions
        SET `length` = TO_SECONDS(NOW())-TO_SECONDS(start)
        WHERE id_task = ?
          AND `length` IS NULL",
        hex2bin($id)
      );
    }

    return $this->db->getOne("
      SELECT COUNT(*)
      FROM bbn_tasks_sessions
      WHERE id_task = ?
        AND `length` IS NULL",
      hex2bin($id)
    ) === 0;
  }

  /**
   * Switch the tracker from a task to another.
   *
   * @param  string  $idTask The current task's ID
   * @param  string  $idNewTask The new task's ID
   * @param  boolean|string $message The message to attach to track (optional)
   * @param  boolean|string $idUser The track's user. If you give 'false', it will use the current user
   * @return boolean
   */
  public function switchTracker($idTask, $idNewTask, $message = false, $idUser = false): bool
  {
    if ($this->stopTrack($idTask, $message, $idUser)) {
      sleep(1);
      return $this->startTrack($idNewTask, $idUser);
    }

    return false;
  }

  public function getActiveTrack($id_user = false, ?string $idTask = null): ?array
  {
    $where = [
      'id_user' => $id_user ?: $this->id_user,
      'length' => null
    ];
    if (!empty($idTask)) {
      $where['id_task'] = $idTask;
    }

    return $this->db->rselect('bbn_tasks_sessions', [], $where);
  }

  public function getLastStoppedTrack(?string $idUser = null, ?string $taskType = null): ?array
  {
    return $this->db->rselect([
      'table' => 'bbn_tasks_sessions',
      'fields' => [
        'bbn_tasks_sessions.id',
        'bbn_tasks_sessions.end',
        'bbn_tasks_sessions.length',
        'bbn_tasks_sessions.tokens',
        'bbn_tasks.type'
      ],
      'join' => [[
        'table' => 'bbn_tasks',
        'on' => [[
          'field' => 'bbn_tasks.id',
          'exp' => 'bbn_tasks_sessions.id_task'
        ]]
      ]],
      'where' => [[
        'field' => 'bbn_tasks_sessions.id_user',
        'value' => $idUser ?: $this->id_user
      ], [
        'field' => 'bbn_tasks.active' ,
        'value' => 1
      ], [
        'field' => 'bbn_tasks_sessions.end',
        'operator' => 'isnotnull'
      ], [
        'field' => 'bbn_tasks.type',
        !empty($taskType) ? 'value' : 'operator' => !empty($taskType) ? $taskType : 'isnotnull',
      ]],
      'order' => [
        'bbn_tasks_sessions.end' => 'DESC'
      ],
    ]);
  }

  public function getTrack(string $id): ?array
  {
    return $this->db->rselect('bbn_tasks_sessions', [], ['id' => $id]);
  }

  public function getTracks($id_task){
    return $this->db->getRows("
      SELECT id_user, SUM(length) AS total_time, COUNT(id_note) as num_notes, SUM(tokens) as total_tokens
      FROM bbn_tasks_sessions
      WHERE id_task = ?
      GROUP BY id_user",
      hex2bin($id_task)
    );
  }

  public function getTracksByDates(string $start, string $end, ?string $idUser = null): ?array
  {
    return $this->db->rselectAll([
      'table' => 'bbn_tasks_sessions',
      'fields' => $this->db->getFieldsList('bbn_tasks_sessions'),
      'join' => [[
        'table' => 'bbn_tasks',
        'on' => [[
          'field' => 'bbn_tasks.id',
          'exp' => 'bbn_tasks_sessions.id_task'
        ]]
      ]],
      'where' => [
        'conditions' => [[
          'field' => 'bbn_tasks_sessions.start',
          'operator' => '<',
          'value' => $end
        ], [
          'field' => 'bbn_tasks_sessions.end',
          'operator' => '>',
          'value' => $start
        ], [
          'field' => 'bbn_tasks_sessions.id_user',
          'value' => $idUser ?: $this->id_user
        ], [
          'field' => 'bbn_tasks_sessions.length',
          'operator' => 'isnotnull'
        ], [
          'logic' => 'OR',
          'conditions' => [[
            'field' => 'bbn_tasks_sessions.start',
            'operator' => '>=',
            'value' => $start
          ], [
            'field' => 'bbn_tasks_sessions.end',
            'operator' => '<=',
            'value' => $end
          ]]
        ]]
      ],
      'order' => [
        'bbn_tasks_sessions.start' => 'ASC'
      ]
    ]);
  }

  public function getTrackStart(string $idTrack): ?string
  {
    return $this->db->selectOne('bbn_tasks_sessions', 'start', ['id' => $idTrack]);
  }

  public function getTrackEnd(string $idTrack): ?string
  {
    return $this->db->selectOne('bbn_tasks_sessions', 'end', ['id' => $idTrack]);
  }

  public function getTrackLength(string $idTrack): ?string
  {
    return $this->db->selectOne('bbn_tasks_sessions', 'length', ['id' => $idTrack]);
  }

  public function getTrackIdNote(string $idTrack): ?string
  {
    return $this->db->selectOne('bbn_tasks_sessions', 'id_note', ['id' => $idTrack]);
  }

  public function getTrackNote(string $idTrack): ?array
  {
    if ($idNote = $this->getTrackIdNote($idTrack)) {
      return $this->noteCls->get($idNote);
    }

    return null;
  }

  public function getTasksTracks(?string $idUser = null){
    if (
      ($manager = $this->idRole('managers')) &&
      ($worker = $this->idRole('workers')) &&
      ($ongoing = $this->idState('ongoing'))
    ){
      return $this->db->getRows("
        SELECT bbn_tasks.*, bbn_notes_versions.title, bbn_notes_versions.content
        FROM bbn_tasks
          JOIN bbn_notes_versions
            ON bbn_notes_versions.id_note = bbn_tasks.id_note
            AND bbn_notes_versions.latest = 1
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
        hex2bin($idUser ?: $this->id_user),
        hex2bin($manager),
        hex2bin($worker),
        hex2bin($ongoing)
      );
    }
  }

  public function moveTrack(string $idTrack, string $idTask): bool
  {
    if ($track = $this->getTrack($idTrack)) {
      if ($track['id_user'] !== $this->id_user) {
        return false;
      }

      if ($this->db->update('bbn_tasks_sessions', ['id_task' => $idTask], ['id' => $idTrack])) {
        if (!empty($track['id_note'])) {
          if ($this->db->update('bbn_tasks_notes', ['id_task' => $idTask], [
            'id_note' => $track['id_note'],
            'id_task' => $track['id_task']
          ])) {
            $this->addLog($track['id_task'], 'comment_delete');
            $this->addLog($idTask, 'comment_insert');
          }
        }

        return true;
      }
    }

    return false;
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

  public function isTokensActive(): bool
  {
    if ($cfg = $this->getTokensCfg()) {
      return !empty($cfg['enabled']);
    }

    return false;
  }

  public function getTokensCfg(): ?array
  {
    if (empty($this->tokensConfig)) {
      $this->tokensConfig = self::getOption('config', 'tokens');
    }

    return $this->tokensConfig;
  }

  public function getTokensBillingPeriod(): ?array
  {
    if ($cfg = $this->getTokensCfg()) {
      return $cfg['billing'];
    }

    return null;
  }

  public function getTokensCurrentBillingPeriod(): ?array
  {
    if (($billingPeriod = $this->getTokensBillingPeriod())
      && !empty($billingPeriod['day'])
      && !empty($billingPeriod['month'])
      && !empty($billingPeriod['months'])
    ) {
      $currentDate = time();
      $startPeriod = strtotime(date('Y-').$billingPeriod['month'].'-'.$billingPeriod['day']);
      $n = 12 / $billingPeriod['months'];
      $start = $startPeriod;
      for ($i = 1; $i <= $n; $i++) {
        $end = strtotime(date('Y-m-d', $startPeriod).' +'.$i.' month');
        if ((date('Y-m-d H:i:s', $currentDate) >= date('Y-m-d 00:00:00', $start))
          && (date('Y-m-d H:i:s', $currentDate) <= date('Y-m-d 23:59:59', $end))
        ) {
          return [
            'start' => date('Y-m-d 00:00:00', $start),
            'end' => date('Y-m-d 23:59:59', $end)
          ];
        }

        $start = $end;
      }
    }

    return null;
  }

  public function getTokensCurrent(?string $type = null, ?string $idUser = null): ?array
  {
    if (($tokenYear = $this->getTokensYear($type))
      && ($currentBillingPeriod = $this->getTokensCurrentBillingPeriod())
      && ($billingPeriod = $this->getTokensBillingPeriod())
    ) {
      $ret = [];
      $where = [[
        'field' => 'bbn_tasks_sessions.start',
        'operator' => '>=',
        'value' => $currentBillingPeriod['start']
      ], [
        'field' => 'bbn_tasks_sessions.start',
        'operator' => '<=',
        'value' => $currentBillingPeriod['end']
      ], [
        'field' => 'bbn_tasks_sessions.length',
        'operator' => 'isnotnull'
      ]];
      if (!empty($idUser)) {
        $where[] = [
          'field' => 'bbn_tasks_sessions.id_user',
          'value' => $idUser
        ];
      }

      foreach ($tokenYear as $code => $tokens) {
        $idOptionCat = self::getOptionId($code, self::getTokensCategoriesId());
        $used = $this->db->selectOne([
          'table' => 'bbn_tasks_sessions',
          'fields' => 'SUM(bbn_tasks_sessions.tokens)',
          'join' => [[
            'table' => 'bbn_tasks',
            'on' => [
              'conditions' => [[
                'field' => 'bbn_tasks_sessions.id_task',
                'exp' => 'bbn_tasks.id'
              ]]
            ]
          ], [
            'table' => 'bbn_options',
            'on' => [
              'conditions' => [[
                'field' => 'bbn_tasks.type',
                'exp' => 'bbn_options.id'
              ]]
            ]
          ]],
          'where' => [
            'conditions' => X::mergeArrays($where, [[
              'field' => 'bbn_options.id_alias',
              'value' => $idOptionCat
            ]])
          ]
        ]) ?: 0;
        $total = floor($tokens / (!empty($billingPeriod['months']) ? 12 / $billingPeriod['months'] : 1));
        $ret[$code] = [
          'total' => $total,
          'used' => $used,
          'available' => $total - $used,
          'totalYear' => $tokens
        ];
      }

      return !empty($ret) ? (!empty($type) ? \array_values($ret)[0] : $ret) : null;
    }

    return null;
  }

  public function getTokensYear(?string $type = null): ?array
  {
    if (!empty($type)) {
      if (!Str::isUid($type)) {
        $type = self::getOption($type, self::getTokensCategoriesId());
      }

      if ($opt = self::getOption($type)) {
        return [
          $opt['code'] => !empty($opt['tokensYear']) ? $opt['tokensYear'] : 0
        ];
      }
    }
    elseif ($cats = $this->getTokensCategories()) {
      $ret = [];
      foreach ($cats as $c) {
        $ret[$c['code']] = !empty($c['tokensYear']) ? $c['tokensYear'] : 0;
      }

      return $ret;
    }

    return null;
  }

  public function getTokens(string $idTask): ?int
  {
    return $this->db->selectOne([
      'table' => 'bbn_tasks_sessions',
      'fields' => ['SUM(tokens)'],
      'where' => [[
        'field' => 'id_task',
        'value' => $idTask
      ], [
        'field' => 'length',
        'operator' => 'isnotnull'
      ]]
    ]);
  }

  public function getTokensCategory(string $idTask): ?string
  {
    if ($cats = $this->getTokensCategories()) {
      $w = [];
      foreach ($cats as $c) {
        $w[] = [
          'field' => 'bbn_options.id_alias',
          'value' => $c['id']
        ];
      }
      return $this->db->selectOne([
        'table' => 'bbn_tasks',
        'fields' => ['bbn_options.id_alias'],
        'join' => [[
          'table' => 'bbn_options',
          'on' => [
            'conditions' => [[
              'field' => 'bbn_tasks.type',
              'exp' => 'bbn_options.id'
            ]]
          ]
        ]],
        'where' => [
          'conditions' => [[
            'field' => 'bbn_tasks.id',
            'value' => $idTask
          ], [
            'logic' => 'OR',
            'conditions' => $w
          ]]
        ]
      ]);
    }

    return null;
  }

  public function getTokensCategories(): ?array
  {
    return self::getOptions(self::getTokensCategoriesId());
  }

  public function getTrackTokens(string $idTrack): ?int
  {
    return $this->db->selectOne('bbn_tasks_sessions', 'tokens', ['id' => $idTrack]);
  }

  public function hasTrackNextLink(string $idTrack): bool
  {
    if ($track = $this->getTrack($idTrack)) {
      return (bool)$this->db->selectOne([
        'table' => 'bbn_tasks_sessions',
        'fields' => ['bbn_tasks_sessions.id'],
        'join' => [[
          'table' => 'bbn_tasks',
          'on' => [[
            'field' => 'bbn_tasks_sessions.id_task',
            'exp' => 'bbn_tasks.id'
          ]]
        ]],
        'where' => [
          'bbn_tasks_sessions.start' => $track['end'],
          'bbn_tasks_sessions.id_user' => $track['id_user'],
          'bbn_tasks.active' => 1
        ]
      ]);
    }

    return false;
  }

  public function hasTrackPrevLink(string $idTrack): bool
  {
    if ($track = $this->getTrack($idTrack)) {
      return (bool)$this->db->selectOne([
        'table' => 'bbn_tasks_sessions',
        'fields' => ['bbn_tasks_sessions.id'],
        'join' => [[
          'table' => 'bbn_tasks',
          'on' => [[
            'field' => 'bbn_tasks_sessions.id_task',
            'exp' => 'bbn_tasks.id'
          ]]
        ]],
        'where' => [
          'bbn_tasks_sessions.end' => $track['start'],
          'bbn_tasks_sessions.id_user' => $track['id_user'],
          'bbn_tasks.active' => 1
        ]
      ]);
    }

    return false;
  }

  public function calcTokens(string $idTrack, bool $includeLinked = false, ?string $start = null, ?string $end = null): ?int
  {
    $tokens = $this->calcTokensRaw($idTrack, $includeLinked, $start, $end);
    return \is_null($tokens) ? null : (int)($this->hasTrackNextLink($idTrack) ? floor($tokens) : ceil($tokens));
  }

  private function calcTokensRaw(string $idTrack, bool $includeLinked = false, ?string $start = null, ?string $end = null): ?float
  {
    $tokens = null;
    if ($this->isTokensActive()
      && ($tokensCfg = $this->getTokensCfg())
      && !empty($tokensCfg['step'])
      && ((($track = $this->getTrack($idTrack))
          && $this->getTokensCategory($track['id_task']))
        || (!empty($start) && !empty($end)))
    ) {
      $start = $start ?: $track['start'];
      $end = $end ?: $track['end'];
      $length = strtotime($end) - strtotime($start);
      $tokens = $length / $tokensCfg['step'];
      if (!empty($includeLinked)) {
        $tokens += $this->calcLinkedTokens($idTrack, $start);
      }
    }

    return $tokens;
  }

  private function calcLinkedTokens(string $idTrack, ?string $start = null): float
  {
    $tokens = 0;
    if ($this->isTokensActive()
      && ($tokensCfg = $this->getTokensCfg())
      && ($track = $this->getTrack($idTrack))
      && $this->getTokensCategory($track['id_task'])
    ) {
      $start = $start ?: $track['start'];
      while (!empty($start)
        && ($t = $this->db->select([
          'table' => 'bbn_tasks_sessions',
          'fields' => [
            'bbn_tasks_sessions.start',
            'bbn_tasks_sessions.end',
            'bbn_tasks_sessions.length'
          ],
          'join' => [[
            'table' => 'bbn_tasks',
            'on' => [[
              'field' => 'bbn_tasks_sessions.id_task',
              'exp' => 'bbn_tasks.id'
            ]]
          ]],
          'where' => [
            'bbn_tasks_sessions.end' => $start,
            'bbn_tasks_sessions.id_user' => $track['id_user'],
            'bbn_tasks.active' => 1
          ]
        ]))
      ) {
        $start = $t->start;
        $tok = $t->length / $tokensCfg['step'];
        if ($tt = $tok - floor($tok)) {
          $tokens += $tt;
        }
      }
    }

    return $tokens - floor($tokens);
  }

  private function checkTokens(string $idTrack, ?string $start = null, ?string $end = null)
  {
    if ($this->isTokensActive()
      && ($linkedTracks = $this->getLinkedTracks($idTrack, $start, $end))
    ) {
      foreach( $linkedTracks as $lt) {
        $tokens = $this->calcTokens($lt['id'], true);
        if (($tokens !== $lt['tokens'])
          && !$this->db->update('bbn_tasks_sessions', [
            'tokens' => $tokens
          ], [
            'id' => $lt['id']
          ])
        ) {
          throw new \Exception(X::_('Error while updating tokens, trackID: %s, oldTokens: %s, newTokens: %s', $lt['id'], (string)$lt['tokens'], (string)$tokens));
        }
      }
    }
  }

  private function getLinkedTracks(string $idTrack, ?string $start = null, ?string $end = null): array
  {
    $ret = [];
    $current = $this->getTrack($idTrack);
    $end = $end ?: $current['end'];
    $start = $start ?: $current['start'];
    if ($prev = $this->db->rselect([
      'table' => 'bbn_tasks_sessions',
      'fields' => $this->db->getFieldsList('bbn_tasks_sessions'),
      'join' => [[
        'table' => 'bbn_tasks',
        'on' => [[
          'field' => 'bbn_tasks_sessions.id_task',
          'exp' => 'bbn_tasks.id'
        ]]
      ]],
      'where' => [[
        'field' => 'bbn_tasks_sessions.end',
        'value' => $start
      ], [
        'field' => 'bbn_tasks_sessions.id_user',
        'value' => $current['id_user']
      ], [
        'field' => 'bbn_tasks_sessions.id',
        'operator' => '!=',
        'value' => $idTrack
      ], [
        'field' => 'bbn_tasks.active',
        'value' => 1
      ]]
    ])) {
      $ret[] = $prev;
    }

    while (!empty($end)
      && ($track = $this->db->rselect([
        'table' => 'bbn_tasks_sessions',
        'fields' => $this->db->getFieldsList('bbn_tasks_sessions'),
        'join' => [[
          'table' => 'bbn_tasks',
          'on' => [[
            'field' => 'bbn_tasks_sessions.id_task',
            'exp' => 'bbn_tasks.id'
          ]]
        ]],
        'where' => [[
          'field' => 'bbn_tasks_sessions.start',
          'value' => $end
        ], [
          'field' => 'bbn_tasks_sessions.id_user',
          'value' => $current['id_user']
        ], [
          'field' => 'bbn_tasks_sessions.id',
          'operator' => '!=',
          'value' => $idTrack
        ], [
          'field' => 'bbn_tasks.active',
          'value' => 1
        ]]
      ]))
    ) {
      $ret[] = $track;
      $end = $track['end'];
    }

    return $ret;
  }

  private static function getTokensCategoriesId(): ?string
  {
    return self::getOptionId('cats', 'tokens');
  }

  private function getIdNote(string $id): ?string
  {
    return $this->db->selectOne('bbn_tasks', 'id_note', ['id' => $id]);
  }

}
