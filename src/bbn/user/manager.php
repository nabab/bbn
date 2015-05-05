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

	private static $_default_permissions = ['is_admin' => 0],
          $list_fields = ['id', 'email', 'login'];
  
  protected static $permissions = [];

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

  protected $usrcls,
          $mailer = false,
          $db,
          $cfg = false;
  
  private static function set_permissions()
  {
    if ( count(self::$permissions) === 0 ){
      self::$permissions = array_merge(self::$_default_permissions, self::$permissions);
    }
  }
  
  public static function get_permissions(){
    return self::$permissions;
  }

  /**
   * Creates a magic string which will be used for hotlinks
   * The hash is stored in the database
   * The key is sent to the user
   *
   * @return array
   */
  private static function make_magic_string()
  {
    $key = self::make_fingerprint();
    return [
      'key' => $key,
      'hash' => hash('sha256', $key)
    ];
  }

  public function find_sessions($id_user=null, $minutes = 5)
  {
    if ( is_int($minutes) ){
      if ( is_null($id_user) ){
        return $this->db->get_rows("
          SELECT *
          FROM `{$this->cfg['tables']['sessions']}`
          WHERE `{$this->cfg['arch']['sessions']['last_activity']}` > DATE_SUB(?, INTERVAL {$this->cfg['sess_length']} MINUTE)",
          date('Y-m-d H:i:s'));
      }
      else{
        return $this->db->get_rows("
          SELECT *
          FROM `{$this->cfg['tables']['sessions']}`
          WHERE `{$this->cfg['arch']['sessions']['id_user']}` = ?
            AND `{$this->cfg['arch']['sessions']['last_activity']}` > DATE_SUB(?, INTERVAL {$this->cfg['sess_length']} MINUTE)",
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
  public function __construct(\bbn\user\connection &$obj, $mailer=false)
  {
    if ( is_object($obj) && method_exists($obj, 'get_class_cfg') ){
      if ( is_object($mailer) && method_exists($mailer, 'send') ){
        $this->mailer = $mailer;
      }
      $this->usrcls = $obj;
      $this->cfg = $this->usrcls->get_class_cfg();
      $this->db =& $this->usrcls->db;
      self::set_permissions();
    }
  }
  
  public function groups(){
    return $this->db->rselect_all(
      $this->cfg['tables']['groups'], [
        'id' => $this->cfg['arch']['groups']['id'],
        'group' => $this->cfg['arch']['groups']['group'],
        'cfg' => $this->cfg['arch']['groups']['cfg']
      ]);
  }
  
  public function text_value_groups(){
    return $this->db->rselect_all(
      $this->cfg['tables']['groups'], [
        'value' => $this->cfg['arch']['groups']['id'],
        'text' => $this->cfg['arch']['groups']['group'],
      ]);
  }

  public function get_email($id){
    if ( \bbn\str\text::is_integer($id) ){
      $email = $this->db->select_one($this->cfg['tables']['users'], $this->cfg['arch']['users']['email'], [$this->cfg['arch']['users']['id'] => $id]);
      if ( $email && \bbn\str\text::is_email($email) ){
        return $email;
      }
    }
    return false;
  }
  
  public function get_list(){
    
    $sql = "SELECT ";
    foreach ( self::$list_fields as $f ){
      $sql .= "{$this->db->escape($this->cfg['tables']['users'].'.'.$this->cfg['arch']['users'][$f])} AS $f, ";
    }
    foreach ( $this->cfg['additional_fields'] as $f ){
      $sql .= "{$this->db->escape($this->cfg['tables']['users'].'.'.$f)}, ";
    }
    $sql .= "
      GROUP_CONCAT(DISTINCT {$this->db->escape($this->cfg['tables']['groups'].'.'.$this->cfg['arch']['groups']['id'])} SEPARATOR ',') AS id_groups,
      MAX({$this->db->escape($this->cfg['tables']['sessions'].'.'.$this->cfg['arch']['sessions']['last_activity'])}) AS last_activity
      FROM {$this->db->escape($this->cfg['tables']['users'])}
        JOIN {$this->db->escape($this->cfg['tables']['usergroups'])}
          ON {$this->db->escape($this->cfg['tables']['usergroups'].'.'.$this->cfg['arch']['usergroups']['id_user'])} = {$this->db->escape($this->cfg['tables']['users'].'.'.$this->cfg['arch']['users']['id'])}
        JOIN {$this->db->escape($this->cfg['tables']['groups'])}
          ON {$this->db->escape($this->cfg['tables']['usergroups'].'.'.$this->cfg['arch']['usergroups']['id_group'])} = {$this->db->escape($this->cfg['tables']['groups'].'.'.$this->cfg['arch']['groups']['id'])}
        LEFT JOIN {$this->db->escape($this->cfg['tables']['sessions'])}
          ON {$this->db->escape($this->cfg['tables']['sessions'].'.'.$this->cfg['arch']['sessions']['id_user'])} = {$this->db->escape($this->cfg['tables']['users'].'.'.$this->cfg['arch']['users']['id'])}
      WHERE {$this->db->escape($this->cfg['tables']['users'].'.'.$this->cfg['arch']['users']['status'])} = 1
      GROUP BY {$this->db->escape($this->cfg['tables']['users'].'.'.$this->cfg['arch']['users']['id'])}
      ORDER BY {$this->db->escape($this->cfg['tables']['users'].'.'.$this->cfg['arch']['users']['login'])}";
    return $this->db->get_rows($sql);
  }

  public function get_user($id){
    if ( is_string($id) ){
      $where = [$this->cfg['arch']['users']['login'] => $id];
    }
    else{
      $where = [$this->cfg['arch']['users']['id'] => $id];
    }
    return $this->db->rselect(
      $this->cfg['tables']['users'],
      array_merge($this->cfg['arch']['users'], $this->cfg['additional_fields']),
      $where);
  }

  public function get_users($group_id){
    if ( \bbn\str\text::is_integer($group_id) ) {
      return $this->db->get_col_array("
        SELECT DISTINCT(".$this->cfg['arch']['usergroups']['id_user'].")
        FROM ".$this->cfg['tables']['usergroups']." AS g
          JOIN ".$this->cfg['tables']['users']." AS u
            ON u.".$this->cfg['arch']['users']['id']." = g.id_user
            AND u.".$this->cfg['arch']['users']['status']." = 1
        WHERE  ".$this->cfg['arch']['usergroups']['id_group']." = ?",
        $group_id);
    }
  }

  /**
   * Creates a new user and returns its configuration (with the new ID)
   * 
   * @param array $cfg A configuration array
	 * @return array 
	 */
	public function add($cfg)
	{
    $fields = array_unique(array_merge(array_values($this->cfg['arch']['users']), $this->cfg['additional_fields']));
    foreach ( $fields as $f ){
      $cfg[$this->cfg['arch']['users']['status']] = 1;
      $cfg[$this->cfg['arch']['users']['cfg']] = '{}';
      if ( !isset($cfg[$f]) && ($f !== $this->cfg['arch']['users']['id']) ){
        die("Field $f empty!");
      }
    }
    foreach ( $cfg as $k => $v ){
      if ( !in_array($k, $fields) ){
        unset($cfg[$k]);
      }
    }
    if ( \bbn\str\text::is_email($cfg[$this->cfg['arch']['users']['email']]) &&
            $this->db->insert($this->cfg['tables']['users'], $cfg) ){
      $cfg[$this->cfg['arch']['users']['id']] = $this->db->last_id();

      // Envoi d'un lien
      $this->make_hotlink($cfg[$this->cfg['arch']['users']['id']], 'creation');
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
    $fields = array_unique(array_merge(array_values($this->cfg['arch']['users']), $this->cfg['additional_fields']));
    $cfg[$this->cfg['arch']['users']['status']] = 1;
    foreach ( $cfg as $k => $v ){
      if ( !in_array($k, $fields) ){
        unset($cfg[$k]);
      }
    }
    if ( !$id_user && isset($cfg[$this->cfg['arch']['users']['id']]) ){
      $id_user = $cfg[$this->cfg['arch']['users']['id']];
      unset($cfg[$this->cfg['arch']['users']['id']]);
    }
    if ( $id_user && (
            !isset($cfg[$this->cfg['arch']['users']['email']]) ||
            \bbn\str\text::is_email($cfg[$this->cfg['arch']['users']['email']]) 
          ) ){
      $this->db->update(
        $this->cfg['tables']['users'],
        $cfg,
        [$this->cfg['arch']['users']['id'] => $id_user]);
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
      $hl =& $this->cfg['arch']['hotlinks'];
      // Expire existing valid hotlinks
      $this->db->update($this->cfg['tables']['hotlinks'], [
        $hl['expire'] => date('Y-m-d H:i:s')
      ],[
        [$hl['id_user'], '=', $id_user],
        [$hl['expire'], '>', date('Y-m-d H:i:s')]
      ]);
      $magic = self::make_magic_string();
      // Create hotlink
      $this->db->insert($this->cfg['tables']['hotlinks'], [
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
    $this->db->delete($this->cfg['tables']['usergroups'], [
      $this->cfg['arch']['usergroups']['id_user'] => $id_user,
    ]);
    $this->db->insert($this->cfg['tables']['usergroups'], [
      $this->cfg['arch']['usergroups']['id_user'] => $id_user,
      $this->cfg['arch']['usergroups']['id_group'] => $id_group
    ]);
    return $this;
  }
  

	/**
   * @param int $id_user User ID
   * 
   * @return \bbn\user\manager
	 */
	public function deactivate($id_user){
    $update = [
      $this->cfg['arch']['users']['status'] => 0,
      $this->cfg['arch']['users']['email'] => null
    ];
    if ( $this->cfg['arch']['users']['email'] !== $this->cfg['arch']['users']['login'] ){
      $update[$this->cfg['arch']['users']['login']] = null;
    }

    $this->db->update($this->cfg['tables']['users'], $update, [
      $this->cfg['arch']['users']['id'] => $id_user
    ]);
    return $this;
	}

	/**
   * @param int $id_user User ID
   * 
   * @return \bbn\user\manager
	 */
	public function reactivate($id_user){
    $this->db->update($this->cfg['tables']['users'], [
      $this->cfg['arch']['users']['status'] => 1
    ], [
      $this->cfg['arch']['users']['id'] => $id_user
    ]);
    return $this;
	}

	/**
	 * @return void 
	 */
  private function create_tables() {
    // @todo!!!
    $sql = "
      CREATE TABLE IF NOT EXISTS {$this->db->escape($this->cfg['tables']['users'])} (
          {$this->db->escape($this->cfg['users']['id'])} int(10) unsigned NOT NULL AUTO_INCREMENT,
        {$this->db->escape($this->cfg['users']['email'])} varchar(100) NOT NULL,".
        ( $this->cfg['users']['login'] !== $this->cfg['users']['email'] ? "
                {$this->db->escape($this->cfg['users']['login'])} varchar(35) NOT NULL," : "" )."
        {$this->db->escape($this->cfg['users']['cfg'])} text NOT NULL,
        PRIMARY KEY ({$this->db->escape($this->cfg['users']['id'])}),
        UNIQUE KEY {$this->db->escape($this->cfg['users']['email'])} ({$this->db->escape($this->cfg['users']['email'])})
      ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

      CREATE TABLE IF NOT EXISTS {$this->db->escape($this->cfg['tables']['groups'])} (
        {$this->db->escape($this->cfg['groups']['id'])} int(10) unsigned NOT NULL AUTO_INCREMENT,
        {$this->db->escape($this->cfg['groups']['group'])} varchar(100) NOT NULL,
        {$this->db->escape($this->cfg['groups']['cfg'])} text NOT NULL,
        PRIMARY KEY ({$this->db->escape($this->cfg['groups']['id'])})
      ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

      CREATE TABLE IF NOT EXISTS {$this->db->escape($this->cfg['tables']['hotlinks'])} (
        {$this->db->escape($this->cfg['hotlinks']['id'])} int(10) unsigned NOT NULL AUTO_INCREMENT,
        {$this->db->escape($this->cfg['hotlinks']['magic_string'])} varchar(64) NOT NULL,
        {$this->db->escape($this->cfg['hotlinks']['id_user'])} int(10) unsigned NOT NULL,
        {$this->db->escape($this->cfg['hotlinks']['expire'])} datetime NOT NULL,
        PRIMARY KEY ({$this->db->escape($this->cfg['hotlinks']['id'])}),
        KEY {$this->db->escape($this->cfg['hotlinks']['id_user'])} ({$this->db->escape($this->cfg['hotlinks']['id_user'])})
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

      CREATE TABLE IF NOT EXISTS {$this->db->escape($this->cfg['tables']['passwords'])} (
        {$this->db->escape($this->cfg['passwords']['id_user'])} int(10) unsigned NOT NULL,
        {$this->db->escape($this->cfg['passwords']['pass'])} varchar(128) NOT NULL,
        {$this->db->escape($this->cfg['passwords']['added'])} datetime NOT NULL,
        KEY {$this->db->escape($this->cfg['passwords']['id_user'])} ({$this->db->escape($this->cfg['passwords']['id_user'])})
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

      CREATE TABLE IF NOT EXISTS {$this->db->escape($this->cfg['tables']['sessions'])} (
        {$this->db->escape($this->cfg['sessions']['id_user'])} int(10) unsigned NOT NULL,
        {$this->db->escape($this->cfg['sessions']['sess_id'])} varchar(128) NOT NULL,
        {$this->db->escape($this->cfg['sessions']['ip_address'])} varchar(15),
        {$this->db->escape($this->cfg['sessions']['user_agent'])} varchar(255),
        {$this->db->escape($this->cfg['sessions']['auth'])} int(1) unsigned NOT NULL,
        {$this->db->escape($this->cfg['sessions']['opened'])} int(1) unsigned NOT NULL,
        {$this->db->escape($this->cfg['sessions']['last_activity'])} datetime NOT NULL,
        {$this->db->escape($this->cfg['sessions']['cfg'])} text NOT NULL,
        PRIMARY KEY ({$this->db->escape($this->cfg['sessions']['id_user'])}, {$this->db->escape($this->cfg['sessions']['sess_id'])})
        KEY {$this->db->escape($this->cfg['sessions']['id_user'])} ({$this->db->escape($this->cfg['sessions']['id_user'])}),
        KEY {$this->db->escape($this->cfg['sessions']['sess_id'])} ({$this->db->escape($this->cfg['sessions']['sess_id'])})
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

      CREATE TABLE IF NOT EXISTS {$this->db->escape($this->cfg['tables']['usergroups'])} (
        {$this->db->escape($this->cfg['usergroups']['id_group'])} int(10) unsigned NOT NULL,
        {$this->db->escape($this->cfg['usergroups']['id_user'])} int(10) unsigned NOT NULL,
        PRIMARY KEY ({$this->db->escape($this->cfg['usergroups']['id_group'])}, {$this->db->escape($this->cfg['usergroups']['id_user'])}),
        KEY {$this->db->escape($this->cfg['usergroups']['id_group'])} ({$this->db->escape($this->cfg['usergroups']['id_group'])}),
        KEY {$this->db->escape($this->cfg['usergroups']['id_user'])} ({$this->db->escape($this->cfg['usergroups']['id_user'])})
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8;


      ALTER TABLE {$this->db->escape($this->cfg['tables']['hotlinks'])}
        ADD FOREIGN KEY ({$this->db->escape($this->cfg['hotlinks']['id_user'])})
          REFERENCES {$this->db->escape($this->cfg['tables']['users'])} ({$this->db->escape($this->cfg['users']['id'])})
            ON DELETE CASCADE ON UPDATE NO ACTION;

      ALTER TABLE {$this->db->escape($this->cfg['tables']['passwords'])}
        ADD FOREIGN KEY ({$this->db->escape($this->cfg['passwords']['id_user'])})
          REFERENCES {$this->db->escape($this->cfg['tables']['users'])} ({$this->db->escape($this->cfg['users']['id'])})
            ON DELETE CASCADE ON UPDATE NO ACTION;

      ALTER TABLE {$this->db->escape($this->cfg['tables']['sessions'])}
        ADD FOREIGN KEY ({$this->db->escape($this->cfg['sessions']['id_user'])})
          REFERENCES {$this->db->escape($this->cfg['tables']['users'])} ({$this->db->escape($this->cfg['users']['id'])})
            ON DELETE CASCADE ON UPDATE NO ACTION;

      ALTER TABLE {$this->db->escape($this->cfg['tables']['usergroups'])}
        ADD FOREIGN KEY ({$this->db->escape($this->cfg['usergroups']['id_group'])})
          REFERENCES {$this->db->escape($this->cfg['tables']['groups'])} ({$this->db->escape($this->cfg['groups']['id'])})
            ON DELETE CASCADE ON UPDATE NO ACTION,
        ADD FOREIGN KEY ({$this->db->escape($this->cfg['usergroups']['id_user'])})
          REFERENCES {$this->db->escape($this->cfg['tables']['users'])} ({$this->db->escape($this->cfg['users']['id'])})
            ON DELETE CASCADE ON UPDATE NO ACTION;";
    $db->raw_query($sql);
  }

}
?>