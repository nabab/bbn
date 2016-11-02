<?php
/**
 * @package user
 */
namespace bbn\user;
use bbn;
/**
 * A class for managing users
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Authentication
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 */
class manager
{

  protected $messages = [
    'creation' => [
      'subject' => "Account created",
      'link' => "",
      'text' => "
A new user has been created for you.<br>
Please click the link below in order to activate your account:<br>
%1\$s"
    ],
    'password' => [
      'subject' => "Password change",
      'link' => "",
      'text' => "
You can click the following link to change your password:<br>
%1\$s"
    ],
    'hotlink' => [
      'subject' => "Hotlink",
      'link' => "",
      'text' => "
You can click the following link to access directly your account:<br>
%1\$s"
    ]
  ],
  // 1 day
  $hotlink_length = 86400;

  protected
    $list_fields,
    $usrcls,
    $mailer = false,
    $db,
    $class_cfg = false;

  protected function set_default_list_fields(){
    $fields = $this->class_cfg['arch']['users'];
    unset($fields['id'], $fields['active'], $fields['cfg']);
    $this->list_fields = [];
    foreach ( $fields as $n => $f ){
      if ( !in_array($f, $this->list_fields) ){
        $this->list_fields[$n] = $f;
      }
    }
  }

  public function get_list_fields(){
    return $this->list_fields;
  }

  public function get_mailer(){
    if ( !$this->mailer ){
      $this->mailer = $this->usrcls->get_mailer();
    }
    return $this->mailer;
  }
  
  public function find_sessions($id_user=null, $minutes = 5)
  {
    if ( is_int($minutes) ){
      if ( is_null($id_user) ){
        return $this->db->get_rows("
          SELECT *
          FROM `{$this->class_cfg['tables']['sessions']}`
          WHERE `{$this->class_cfg['arch']['sessions']['last_activity']}` > DATE_SUB(?, INTERVAL {$this->class_cfg['sess_length']} MINUTE)",
          date('Y-m-d H:i:s'));
      }
      else{
        return $this->db->get_rows("
          SELECT *
          FROM `{$this->class_cfg['tables']['sessions']}`
          WHERE `{$this->class_cfg['arch']['sessions']['id_user']}` = ?
            AND `{$this->class_cfg['arch']['sessions']['last_activity']}` > DATE_SUB(?, INTERVAL {$this->class_cfg['sess_length']} MINUTE)",
          $id_user,
          date('Y-m-d H:i:s'));
      }
    }
    else{
      die("Forbidden to enter anything else than integer as $minutes in manager::find_sessions()");
    }
  }

	/**
	 * @param object $obj A user's connection object (\connection or subclass)
   * @param object|false $mailer A mail object with the send method
   * 
	 */
  public function __construct(bbn\user $obj)
  {
    if ( is_object($obj) && method_exists($obj, 'get_class_cfg') ){
      $this->usrcls = $obj;
      $this->class_cfg = $this->usrcls->get_class_cfg();
      if ( !$this->list_fields ){
        $this->set_default_list_fields();
      }
      $this->db =& $this->usrcls->db;
    }
  }

  /**
   * Returns all the users' groups - with or without admin
   * @param bool $adm
   * @return array|false
   */
  public function groups($adm=false){
    $a =& $this->class_cfg['arch'];
    $t =& $this->class_cfg['tables'];
    $id = $this->db->cfn($a['groups']['id'], $t['groups'], 1);
    $group = $this->db->cfn($a['groups']['group'], $t['groups'], 1);
    $id_group = $this->db->cfn($a['users']['id_group'], $t['users'], 1);
    $active = $this->db->cfn($a['users']['active'], $t['users'], 1);
    $users_id = $this->db->cfn($a['users']['id'], $t['users'], 1);
    $groups = $this->db->escape($t['groups']);
    $users = $this->db->escape($t['users']);
    return $this->db->get_rows("
      SELECT $id, $group,
      COUNT($users_id) AS `num`
      FROM $groups
        LEFT JOIN $users
          ON $id_group = $id
          AND $active = 1
          ".( $adm ? '' : "WHERE $id > 1" )."
      GROUP BY $id");
  }

  public function text_value_groups(){
    return $this->db->rselect_all(
      $this->class_cfg['tables']['groups'], [
        'value' => $this->class_cfg['arch']['groups']['id'],
        'text' => $this->class_cfg['arch']['groups']['group'],
      ]);
  }

  public function get_email($id){
    if ( bbn\str::is_integer($id) ){
      $email = $this->db->select_one($this->class_cfg['tables']['users'], $this->class_cfg['arch']['users']['email'], [$this->class_cfg['arch']['users']['id'] => $id]);
      if ( $email && bbn\str::is_email($email) ){
        return $email;
      }
    }
    return false;
  }
  
  public function get_list($group_id = null){
    $db =& $this->db;
    $arch =& $this->class_cfg['arch'];
    $s =& $arch['sessions'];
    $tables =& $this->class_cfg['tables'];

    if ( !empty($arch['users']['username']) ){
      $sort = $arch['users']['username'];
    }
    else if ( !empty($arch['users']['login']) ){
      $sort = $arch['users']['login'];
    }
    else{
      $sort = $arch['users']['email'];
    }

    $sql = "SELECT ";
    $done = [];
    foreach ( $arch['users'] as $n => $f ){
      if ( !in_array($f, $done) ){
        $sql .= $db->cfn($f, $tables['users'], 1).', ';
        array_push($done, $f);
      }
    }
    $sql .= "
      MAX({$db->cfn($s['last_activity'], $tables['sessions'], 1)}) AS {$db->csn($s['last_activity'], 1)},
      COUNT({$db->cfn($s['sess_id'], $tables['sessions'])}) AS {$db->csn($s['sess_id'], 1)}
      FROM {$db->escape($tables['users'])}
        JOIN {$db->tsn($tables['groups'], 1)}
          ON {$db->cfn($arch['users']['id_group'], $tables['users'], 1)} = {$db->cfn($arch['groups']['id'], $tables['groups'], 1)}
        LEFT JOIN {$db->tsn($tables['sessions'])}
          ON {$db->cfn($s['id_user'], $tables['sessions'], 1)} = {$db->cfn($arch['users']['id'], $tables['users'], 1)}
      WHERE {$db->cfn($arch['users']['active'], $tables['users'], 1)} = 1
      GROUP BY {$db->cfn($arch['users']['id'], $tables['users'], 1)}
      ORDER BY {$db->cfn($sort, $tables['users'], 1)}";

    return $db->get_rows($sql);
  }

  public function get_user($id){
    $u = $this->class_cfg['arch']['users'];
    if ( bbn\str::is_integer($id) ){
      $where = [$u['id'] => $id];
    }
    else{
      $where = [$u['login'] => $id];
    }
    if ( $user = $this->db->rselect(
      $this->class_cfg['tables']['users'],
      array_values($this->class_cfg['arch']['users']),
      $where)
    ){
      if ( $session = $this->db->rselect(
        $this->class_cfg['tables']['sessions'],
        array_values($this->class_cfg['arch']['sessions']),
        [$this->class_cfg['arch']['sessions']['id_user'] => $user[$u['id']]],
        [$this->class_cfg['arch']['sessions']['last_activity'] => 'DESC']
      ) ){
        $session['id_session'] = $session['id'];
      }
      else{
        $session = array_fill_keys(
          array_values($this->class_cfg['arch']['sessions']),
          '');
        $session['id_session'] = false;
      }
      return array_merge($session, $user);
    }
    return false;
  }

  public function get_group($id){
    $g = $this->class_cfg['arch']['groups'];
    if ( $group = $this->db->rselect($this->class_cfg['tables']['groups'], [], [
      $g['id'] => $id
    ]) ){
      $group[$g['cfg']] = group[$g['cfg']] ? json_decode(group[$g['cfg']], 1) : [];
      return $group;
    }
    return false;
  }

  public function get_users($group_id = null){
    return $this->db->get_col_array("
      SELECT ".$this->class_cfg['arch']['users']['id']."
      FROM ".$this->class_cfg['tables']['users']."
      WHERE {$this->db->escape($this->class_cfg['tables']['users'].'.'.$this->class_cfg['arch']['users']['active'])} = 1
      AND ".$this->class_cfg['arch']['users']['id_group']." ".( $group_id ? "= ".(int)$group_id : "!= 1" )
    );
  }

  public function get_name($user, $full = true){
    if ( !is_array($user) ){
      $user = $this->get_user($user);
    }
    if ( is_array($user) ){
      $idx = 'email';
      if ( !empty($this->class_cfg['arch']['users']['username']) ){
        $idx = 'username';
      }
      else if ( !empty($this->class_cfg['arch']['users']['login']) ){
        $idx = 'login';
      }
      return $user[$this->class_cfg['arch']['users'][$idx]];
    }
    return '';
  }

  /**
   * Creates a new user and returns its configuration (with the new ID)
   * 
   * @param array $cfg A configuration array
	 * @return array|false
	 */
	public function add($cfg)
	{
	  $u =& $this->class_cfg['arch']['users'];
    $fields = array_unique(array_values($u));
    $cfg[$u['active']] = 1;
    $cfg[$u['cfg']] = '{}';
    foreach ( $cfg as $k => $v ){
      if ( !in_array($k, $fields) ){
        unset($cfg[$k]);
      }
    }
    if ( isset($cfg['id']) ){
      unset($cfg['id']);
    }
    if (
      bbn\str::is_email($cfg[$u['email']]) &&
            $this->db->insert($this->class_cfg['tables']['users'], $cfg)
    ){
      $cfg[$u['id']] = $this->db->last_id();

      // Envoi d'un lien
      $this->make_hotlink($cfg[$this->class_cfg['arch']['users']['id']], 'creation');
      return $cfg;
    }
		return false;
	}
  
	/**
   * Creates a new user and returns its configuration (with the new ID)
   * 
   * @param array $cfg A configuration array
	 * @return array|false
	 */
	public function edit($cfg, $id_user=false)
	{
	  $u =& $this->class_cfg['arch']['users'];
    $fields = array_unique(array_values($this->class_cfg['arch']['users']));
    $cfg[$u['active']] = 1;
    foreach ( $cfg as $k => $v ){
      if ( !in_array($k, $fields) ){
        unset($cfg[$k]);
      }
    }
    if ( !$id_user && isset($cfg[$u['id']]) ){
      $id_user = $cfg[$u['id']];
    }
    if ( $id_user && (
            !isset($cfg[$this->class_cfg['arch']['users']['email']]) ||
            bbn\str::is_email($cfg[$this->class_cfg['arch']['users']['email']])
          )
    ){
      if ( $this->db->update($this->class_cfg['tables']['users'], $cfg, [
        $u['id'] => $id_user
      ]) ){
        $cfg['id'] = $id_user;
        return $cfg;
      }
    }
		return false;
	}
  
  /**
   *
   * @param int $id_user User ID
   * @param string $message Type of the message
   * @param int $exp Timestamp of the expiration date
   * @return manager
   */
  public function make_hotlink($id_user, $message='hotlink', $exp=null){
    if ( !$this->get_mailer() ){
      die("Impossible to make hotlinks without a proper mailer parameter");
    }
    if ( !isset($this->messages[$message]) || empty($this->messages[$message]['link']) ){
      die("Impossible to make hotlinks without a link configured");
    }
    if ( $usr = $this->get_user($id_user) ){
      // Expiration date
      if ( !is_int($exp) || ($exp < 1) ){
        $exp = time() + $this->hotlink_length;
      }
      $hl =& $this->class_cfg['arch']['hotlinks'];
      // Expire existing valid hotlinks
      $this->db->update($this->class_cfg['tables']['hotlinks'], [
        $hl['expire'] => date('Y-m-d H:i:s')
      ],[
        [$hl['id_user'], '=', $id_user],
        [$hl['expire'], '>', date('Y-m-d H:i:s')]
      ]);
      $magic = $this->usrcls->make_magic_string();
      // Create hotlink
      $this->db->insert($this->class_cfg['tables']['hotlinks'], [
        $hl['magic_string'] => $magic['hash'],
        $hl['id_user'] => $id_user,
        $hl['expire'] => date('Y-m-d H:i:s', $exp)
      ]);
      $id_link = $this->db->last_id();
      $link = "?id=$id_link&key=".$magic['key'];
      $this->mailer->send([
        'to' => $usr['email'],
        'subject' => $this->messages[$message]['subject'],
        'text' => sprintf($this->messages[$message]['text'],
                sprintf($this->messages[$message]['link'], $link))
      ]);
    }
    else{
      die("User $id_user not found");
    }
    return $this;
  }
  
  /**
   * 
   * @param int $id_user User ID
   * @param int $id_group Group ID
   * @return manager
   */
  public function set_unique_group($id_user, $id_group){
    $this->db->update($this->class_cfg['tables']['users'], [
      $this->class_cfg['arch']['users']['id_group'] => $id_group
    ], [
      $this->class_cfg['arch']['users']['id'] => $id_user
    ]);
    return $this;
  }

  public function user_has_option($id_user, $id_option, $with_group = true){
    if ( $with_group && $user = $this->get_user($id_user) ){
      $id_group = $user[$this->class_cfg['arch']['users']['id_group']];
      if ( $this->group_has_option($id_group, $id_option) ){
        return true;
      }
    }
    if ( $pref = preferences::get_preferences() ){
      if ( $cfg = $pref->get_class_cfg() ){
        return $this->db->count($cfg['table'], [
          $cfg['cols']['id_option'] => $id_option,
          $cfg['cols']['id_user'] => $id_user
        ]) ? true : false;
      }
    }
    return false;
  }

  public function group_has_option($id_group, $id_option){
    if (
      ($pref = preferences::get_preferences()) &&
      ($cfg = $pref->get_class_cfg())
    ){
      return $this->db->count($cfg['table'], [
        $cfg['cols']['id_option'] => $id_option,
        $cfg['cols']['id_group'] => $id_group
      ]) ? true : false;
    }
    return false;
  }

  public function user_insert_option($id_user, $id_option){
    if (
      ($pref = preferences::get_preferences()) &&
      ($cfg = $pref->get_class_cfg())
    ){
      return $this->db->insert_ignore($cfg['table'], [
        $cfg['cols']['id_option'] => $id_option,
        $cfg['cols']['id_user'] => $id_user
      ]);
    }
    return false;
  }

  public function group_insert_option($id_group, $id_option){
    if (
      ($pref = preferences::get_preferences()) &&
      ($cfg = $pref->get_class_cfg())
    ){
      return $this->db->insert_ignore($cfg['table'], [
        $cfg['cols']['id_option'] => $id_option,
        $cfg['cols']['id_group'] => $id_group
      ]);
    }
    return false;
  }

  public function user_delete_option($id_user, $id_option){
    if (
      ($pref = preferences::get_preferences()) &&
      ($cfg = $pref->get_class_cfg())
    ){
      return $this->db->delete_ignore($cfg['table'], [
        $cfg['cols']['id_option'] => $id_option,
        $cfg['cols']['id_user'] => $id_user
      ]);
    }
    return false;
  }

  public function group_delete_option($id_group, $id_option){
    if (
      ($pref = preferences::get_preferences()) &&
      ($cfg = $pref->get_class_cfg())
    ){
      return $this->db->delete_ignore($cfg['table'], [
        $cfg['cols']['id_option'] => $id_option,
        $cfg['cols']['id_group'] => $id_group
      ]);
    }
    return false;
  }

  public function group_num_users($id_group){
    $u =& $this->class_cfg['arch']['users'];
    return $this->db->count($this->class_cfg['tables']['users'], [
      $u['id_group'] => $id_group,
      $u['active'] => 1
    ]);
  }

  public function group_insert($data){
    $g = $this->class_cfg['arch']['groups'];
    if ( isset($data[$g['group']]) ){
      if ( $this->db->insert($this->class_cfg['tables']['groups'], [
        $g['group'] => $data[$g['group']],
        $g['cfg'] => !empty($g['cfg']) && !empty($data[$g['cfg']]) ? $data[$g['cfg']] : '{}'
      ]) ){
        return $this->db->last_id();
      }
    }
    return false;
  }

  public function group_rename($id, $name){
    $g = $this->class_cfg['arch']['groups'];
    return $this->db->update($this->class_cfg['tables']['groups'], [
      $g['group'] => $name
    ], [
      $g['id'] => $id
    ]);
  }

  public function group_set_cfg($id, $cfg){
    $g = $this->class_cfg['arch']['groups'];
    return $this->db->update($this->class_cfg['tables']['groups'], [
      $g['cfg'] => $cfg ?: '{}'
    ], [
      $g['id'] => $id
    ]);
  }

  public function group_delete($id){
    $g = $this->class_cfg['arch']['groups'];
    if ( $this->group_num_users($id) ){
      /** @todo Error management */
      die("This group has users...");
    }
    return $this->db->delete($this->class_cfg['tables']['groups'], [
      $g['id'] => $id
    ]);
  }

  /**
   * @param int $id_user User ID
   * 
   * @return int|false Update result
	 */
	public function deactivate($id_user){
    $update = [
      $this->class_cfg['arch']['users']['active'] => 0,
      $this->class_cfg['arch']['users']['email'] => null,
    ];
    if ( !empty($this->class_cfg['arch']['users']['login']) ){
      $update[$this->class_cfg['arch']['users']['login']] = null;
    }

    return $this->db->update($this->class_cfg['tables']['users'], $update, [
      $this->class_cfg['arch']['users']['id'] => $id_user
    ]);
	}

	/**
   * @param int $id_user User ID
   * 
   * @return manager
	 */
	public function reactivate($id_user){
    $this->db->update($this->class_cfg['tables']['users'], [
      $this->class_cfg['arch']['users']['active'] => 1
    ], [
      $this->class_cfg['arch']['users']['id'] => $id_user
    ]);
    return $this;
	}

	/**
	 * @return void 
	 */
  private function create_tables() {
    // @todo!!!
    $sql = "
      CREATE TABLE IF NOT EXISTS {$this->db->escape($this->class_cfg['tables']['users'])} (
          {$this->db->escape($this->class_cfg['users']['id'])} int(10) unsigned NOT NULL AUTO_INCREMENT,
        {$this->db->escape($this->class_cfg['users']['email'])} varchar(100) NOT NULL,".
        ( $this->class_cfg['users']['login'] !== $this->class_cfg['users']['email'] ? "
                {$this->db->escape($this->class_cfg['users']['login'])} varchar(35) NOT NULL," : "" )."
        {$this->db->escape($this->class_cfg['users']['cfg'])} text NOT NULL,
        PRIMARY KEY ({$this->db->escape($this->class_cfg['users']['id'])}),
        UNIQUE KEY {$this->db->escape($this->class_cfg['users']['email'])} ({$this->db->escape($this->class_cfg['users']['email'])})
      ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

      CREATE TABLE IF NOT EXISTS {$this->db->escape($this->class_cfg['tables']['groups'])} (
        {$this->db->escape($this->class_cfg['groups']['id'])} int(10) unsigned NOT NULL AUTO_INCREMENT,
        {$this->db->escape($this->class_cfg['groups']['group'])} varchar(100) NOT NULL,
        {$this->db->escape($this->class_cfg['groups']['cfg'])} text NOT NULL,
        PRIMARY KEY ({$this->db->escape($this->class_cfg['groups']['id'])})
      ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

      CREATE TABLE IF NOT EXISTS {$this->db->escape($this->class_cfg['tables']['hotlinks'])} (
        {$this->db->escape($this->class_cfg['hotlinks']['id'])} int(10) unsigned NOT NULL AUTO_INCREMENT,
        {$this->db->escape($this->class_cfg['hotlinks']['magic_string'])} varchar(64) NOT NULL,
        {$this->db->escape($this->class_cfg['hotlinks']['id_user'])} int(10) unsigned NOT NULL,
        {$this->db->escape($this->class_cfg['hotlinks']['expire'])} datetime NOT NULL,
        PRIMARY KEY ({$this->db->escape($this->class_cfg['hotlinks']['id'])}),
        KEY {$this->db->escape($this->class_cfg['hotlinks']['id_user'])} ({$this->db->escape($this->class_cfg['hotlinks']['id_user'])})
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

      CREATE TABLE IF NOT EXISTS {$this->db->escape($this->class_cfg['tables']['passwords'])} (
        {$this->db->escape($this->class_cfg['passwords']['id_user'])} int(10) unsigned NOT NULL,
        {$this->db->escape($this->class_cfg['passwords']['pass'])} varchar(128) NOT NULL,
        {$this->db->escape($this->class_cfg['passwords']['added'])} datetime NOT NULL,
        KEY {$this->db->escape($this->class_cfg['passwords']['id_user'])} ({$this->db->escape($this->class_cfg['passwords']['id_user'])})
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

      CREATE TABLE IF NOT EXISTS {$this->db->escape($this->class_cfg['tables']['sessions'])} (
        {$this->db->escape($this->class_cfg['sessions']['id_user'])} int(10) unsigned NOT NULL,
        {$this->db->escape($this->class_cfg['sessions']['sess_id'])} varchar(128) NOT NULL,
        {$this->db->escape($this->class_cfg['sessions']['ip_address'])} varchar(15),
        {$this->db->escape($this->class_cfg['sessions']['user_agent'])} varchar(255),
        {$this->db->escape($this->class_cfg['sessions']['auth'])} int(1) unsigned NOT NULL,
        {$this->db->escape($this->class_cfg['sessions']['opened'])} int(1) unsigned NOT NULL,
        {$this->db->escape($this->class_cfg['sessions']['last_activity'])} datetime NOT NULL,
        {$this->db->escape($this->class_cfg['sessions']['cfg'])} text NOT NULL,
        PRIMARY KEY ({$this->db->escape($this->class_cfg['sessions']['id_user'])}, {$this->db->escape($this->class_cfg['sessions']['sess_id'])})
        KEY {$this->db->escape($this->class_cfg['sessions']['id_user'])} ({$this->db->escape($this->class_cfg['sessions']['id_user'])}),
        KEY {$this->db->escape($this->class_cfg['sessions']['sess_id'])} ({$this->db->escape($this->class_cfg['sessions']['sess_id'])})
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

      ALTER TABLE {$this->db->escape($this->class_cfg['tables']['hotlinks'])}
        ADD FOREIGN KEY ({$this->db->escape($this->class_cfg['hotlinks']['id_user'])})
          REFERENCES {$this->db->escape($this->class_cfg['tables']['users'])} ({$this->db->escape($this->class_cfg['users']['id'])})
            ON DELETE CASCADE ON UPDATE NO ACTION;

      ALTER TABLE {$this->db->escape($this->class_cfg['tables']['passwords'])}
        ADD FOREIGN KEY ({$this->db->escape($this->class_cfg['passwords']['id_user'])})
          REFERENCES {$this->db->escape($this->class_cfg['tables']['users'])} ({$this->db->escape($this->class_cfg['users']['id'])})
            ON DELETE CASCADE ON UPDATE NO ACTION;

      ALTER TABLE {$this->db->escape($this->class_cfg['tables']['sessions'])}
        ADD FOREIGN KEY ({$this->db->escape($this->class_cfg['sessions']['id_user'])})
          REFERENCES {$this->db->escape($this->class_cfg['tables']['users'])} ({$this->db->escape($this->class_cfg['users']['id'])})
            ON DELETE CASCADE ON UPDATE NO ACTION;

      ALTER TABLE {$this->db->escape($this->class_cfg['tables']['users'])}
        ADD FOREIGN KEY ({$this->db->escape($this->class_cfg['users']['id_group'])})
          REFERENCES {$this->db->escape($this->class_cfg['tables']['groups'])} ({$this->db->escape($this->class_cfg['groups']['id'])})
            ON DELETE CASCADE ON UPDATE NO ACTION;";
    $db->raw_query($sql);
  }

}