<?php
/**
 * @package bbn\user
 */
namespace bbn\user;
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

	private static $list_fields = ['id', 'email', 'login', 'id_group'];
  
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
    $usrcls,
    $mailer = false,
    $db,
    $class_cfg = false;
  
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
      die("Forbidden to enter anything else than integer as $minutes in \bbn\user\manager\find_sessions()");
    }
  }

	/**
	 * @param object $obj A user's connection object (\bbn\user\connection or subclass)
   * @param object|false $mailer A mail object with the send method
   * 
	 */
  public function __construct(connection $obj, $mailer=false)
  {
    if ( is_object($obj) && method_exists($obj, 'get_class_cfg') ){
      if ( is_object($mailer) && method_exists($mailer, 'send') ){
        $this->mailer = $mailer;
      }
      $this->usrcls = $obj;
      $this->class_cfg = $this->usrcls->get_class_cfg();
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
    $cfg = $this->db->cfn($a['groups']['cfg'], $t['groups'], 1);
    $id_group = $this->db->cfn($a['users']['id_group'], $t['users'], 1);
    $status = $this->db->cfn($a['users']['status'], $t['users'], 1);
    $users_id = $this->db->cfn($a['users']['id'], $t['users'], 1);
    $groups = $this->db->escape($t['groups']);
    $users = $this->db->escape($t['users']);
    return $this->db->get_rows("
      SELECT $id AS `id`,
      $group AS `group`,
      $cfg AS `cfg`,
      COUNT($users_id) AS `num`
      FROM $groups
        LEFT JOIN $users
          ON $id_group = $id
          AND $status = 1
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
    if ( \bbn\str::is_integer($id) ){
      $email = $this->db->select_one($this->class_cfg['tables']['users'], $this->class_cfg['arch']['users']['email'], [$this->class_cfg['arch']['users']['id'] => $id]);
      if ( $email && \bbn\str::is_email($email) ){
        return $email;
      }
    }
    return false;
  }
  
  public function get_list($group_id = null){
    $sql = "SELECT ";
    foreach ( self::$list_fields as $f ){
      $sql .= "{$this->db->escape($this->class_cfg['tables']['users'].'.'.$this->class_cfg['arch']['users'][$f])} AS $f, ";
    }
    foreach ( $this->class_cfg['additional_fields'] as $f ){
      $sql .= "{$this->db->escape($this->class_cfg['tables']['users'].'.'.$f)}, ";
    }
    $sql .= "
      MAX({$this->db->escape($this->class_cfg['tables']['sessions'].'.'.$this->class_cfg['arch']['sessions']['last_activity'])}) AS last_activity,
      COUNT({$this->db->escape($this->class_cfg['tables']['sessions'].'.'.$this->class_cfg['arch']['sessions']['sess_id'])}) AS num_sessions
      FROM {$this->db->escape($this->class_cfg['tables']['users'])}
        JOIN {$this->db->escape($this->class_cfg['tables']['groups'])}
          ON {$this->db->escape($this->class_cfg['tables']['users'].'.'.$this->class_cfg['arch']['users']['id_group'])} = {$this->db->escape($this->class_cfg['tables']['groups'].'.'.$this->class_cfg['arch']['groups']['id'])}
        LEFT JOIN {$this->db->escape($this->class_cfg['tables']['sessions'])}
          ON {$this->db->escape($this->class_cfg['tables']['sessions'].'.'.$this->class_cfg['arch']['sessions']['id_user'])} = {$this->db->escape($this->class_cfg['tables']['users'].'.'.$this->class_cfg['arch']['users']['id'])}
      WHERE {$this->db->escape($this->class_cfg['tables']['users'].'.'.$this->class_cfg['arch']['users']['status'])} = 1
      GROUP BY {$this->db->escape($this->class_cfg['tables']['users'].'.'.$this->class_cfg['arch']['users']['id'])}
      ORDER BY {$this->db->escape($this->class_cfg['tables']['users'].'.'.$this->class_cfg['arch']['users']['login'])}";
    return $this->db->get_rows($sql);
  }

  public function get_user($id){
    if ( \bbn\str::is_integer($id) ){
      $where = [$this->class_cfg['arch']['users']['id'] => $id];
    }
    else{
      $where = [$this->class_cfg['arch']['users']['login'] => $id];
    }
    return $this->db->rselect(
      $this->class_cfg['tables']['users'],
      array_merge($this->class_cfg['arch']['users'], $this->class_cfg['additional_fields']),
      $where);
  }

  public function get_users($group_id = null){
    return $this->db->get_col_array("
      SELECT ".$this->class_cfg['arch']['users']['id']."
      FROM ".$this->class_cfg['tables']['users']."
      WHERE {$this->db->escape($this->class_cfg['tables']['users'].'.'.$this->class_cfg['arch']['users']['status'])} = 1
      AND ".$this->class_cfg['arch']['users']['id_group']." ".( $group_id ? "= ".(int)$group_id : "!= 1" )
    );
  }

  public function get_name($user, $full = true){
    if ( !is_array($user) ){
      $user = $this->get_user($user);
    }
    if ( is_array($user) ){
      return $user[$this->class_cfg['arch']['users']['login']];
    }
    return '';
  }

  /**
   * Creates a new user and returns its configuration (with the new ID)
   * 
   * @param array $cfg A configuration array
	 * @return array 
	 */
	public function add($cfg)
	{
    $fields = array_unique(array_merge(array_values($this->class_cfg['arch']['users']), $this->class_cfg['additional_fields']));
    $cfg[$this->class_cfg['arch']['users']['status']] = 1;
    $cfg[$this->class_cfg['arch']['users']['cfg']] = '{}';
    foreach ( $cfg as $k => $v ){
      if ( !in_array($k, $fields) ){
        unset($cfg[$k]);
      }
    }
    if ( isset($cfg['id']) ){
      unset($cfg['id']);
    }
    if ( \bbn\str::is_email($cfg[$this->class_cfg['arch']['users']['email']]) &&
            $this->db->insert($this->class_cfg['tables']['users'], $cfg) ){
      $cfg[$this->class_cfg['arch']['users']['id']] = $this->db->last_id();

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
	 * @return array 
	 */
	public function edit($cfg, $id_user=false)
	{
    $fields = array_unique(array_merge(array_values($this->class_cfg['arch']['users']), $this->class_cfg['additional_fields']));
    $cfg[$this->class_cfg['arch']['users']['status']] = 1;
    foreach ( $cfg as $k => $v ){
      if ( !in_array($k, $fields) ){
        unset($cfg[$k]);
      }
    }
    if ( !$id_user && isset($cfg[$this->class_cfg['arch']['users']['id']]) ){
      $id_user = $cfg[$this->class_cfg['arch']['users']['id']];
    }
    if ( $id_user && (
            !isset($cfg[$this->class_cfg['arch']['users']['email']]) ||
            \bbn\str::is_email($cfg[$this->class_cfg['arch']['users']['email']]) 
          ) ){
      $this->db->update(
        $this->class_cfg['tables']['users'],
        $cfg,
        [$this->class_cfg['arch']['users']['id'] => $id_user]);
      $cfg['id'] = $id_user;
      return $cfg;
    }
		return false;
	}
  
  /**
   * 
   * @param int $id_user User ID
   * @param int $exp Timestamp of the expiration date
   * @return \bbn\user\manager
   */
  public function make_hotlink($id_user, $message='hotlink', $exp=false){
    if ( !$this->mailer ){
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
   * @return \bbn\user\manager
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



  /**
   * @param int $id_user User ID
   * 
   * @return \bbn\user\manager
	 */
	public function deactivate($id_user){
    $update = [
      $this->class_cfg['arch']['users']['status'] => 0,
      $this->class_cfg['arch']['users']['email'] => null
    ];
    if ( $this->class_cfg['arch']['users']['email'] !== $this->class_cfg['arch']['users']['login'] ){
      $update[$this->class_cfg['arch']['users']['login']] = null;
    }

    $this->db->update($this->class_cfg['tables']['users'], $update, [
      $this->class_cfg['arch']['users']['id'] => $id_user
    ]);
    return $this;
	}

	/**
   * @param int $id_user User ID
   * 
   * @return \bbn\user\manager
	 */
	public function reactivate($id_user){
    $this->db->update($this->class_cfg['tables']['users'], [
      $this->class_cfg['arch']['users']['status'] => 1
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