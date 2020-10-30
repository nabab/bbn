<?php
namespace bbn\appui;

use bbn;
use bbn\x;

/**
 * Passwords management in appui
 */
class passwords extends bbn\models\cls\db
{
  use bbn\models\tts\dbconfig;

  /** @var bbn\appui\options An options object */
  private $_o;

  /** @var array Database architecture schema */
  protected static $default_class_cfg = [
    'table' => 'bbn_passwords',
    'tables' => [
      'passwords' => 'bbn_passwords'
    ],
    'arch' => [
      'passwords' => [
        'id' => 'id',
        'id_option' => 'id_option',
        'id_user_option' => 'id_user_option',
        'password' => 'password'
      ]
    ]
  ];


  /**
   * Contructor.
   *
   * @param \bbn\db $db Database connection
   */
  public function __construct(\bbn\db $db) 
  {
    parent::__construct($db);
    $this->_init_class_cfg();
    $this->_o = options::get_instance();
  }


  /**
   * Stores a password in the database.
   *
   * @param string $password  The unencrypted password
   * @param string $id_option The option to which it is attached
   *
   * @return bool True if the action went well false otherwise
   */
  public function store(string $password, string $id_option): bool
  {
    if ($password && defined('BBN_ENCRYPTION_KEY')) {
      $arch     = &$this->class_cfg['arch']['passwords'];
      $to_store = \bbn\util\enc::crypt64($password, BBN_ENCRYPTION_KEY);
      //var_dump(base64_encode($to_store));
      if ($this->db->insert_update(
        $this->class_cfg['table'],
        [
          $arch['id_option'] => $id_option,
          $arch['password'] => $to_store
        ]
      )
      ) {
        return true;
      }
    }

    x::log('not ok');
    return false;
  }


  /**
   * Stores a password in the database for a user.
   *
   * @param string   $password The password to store
   * @param string   $id_pref  The ID in user_option_bits
   * @param bbn\user $user     A user object
   *
   * @return bool
   */
  public function user_store(string $password, string $id_pref, bbn\user $user): bool
  {
    if ($password && ($to_store = $user->crypt($password))) {
      $arch =& $this->class_cfg['arch']['passwords'];
      return (bool)$this->db->insert_update(
        $this->class_cfg['table'],
        [
          $arch['id_user_option'] => $id_pref,
          $arch['password'] => base64_encode($to_store)
        ]
      );
    }

    return false;
  }


  /**
   * Returns a password for the given option.
   *
   * @param string $id_option The option's ID
   *
   * @return string|null
   */
  public function get(string $id_option): ?string
  {
    if (defined('BBN_ENCRYPTION_KEY')) {
      $arch =& $this->class_cfg['arch']['passwords'];
      if ($password = $this->db->select_one($this->class_cfg['table'], $arch['password'], [
        $arch['id_option'] => $id_option
      ])) {
        return \bbn\util\enc::decrypt64($password, BBN_ENCRYPTION_KEY);
      }
    }
    return null;
  }


  /**
   * Returns a password for the given user's option.
   *
   * @param string   $id_pref The ID in user_option_bits
   * @param bbn\user $user    A user object
   *
   * @return string|null
   */
  public function user_get(string $id_pref, bbn\user $user): ?string
  {
    if ($user->is_auth()) {
      $arch =& $this->class_cfg['arch']['passwords'];
      if ($password = $this->db->select_one($this->class_cfg['table'], $arch['password'], [
        $arch['id_user_option'] => $id_pref
      ])) {
        return $user->decrypt(base64_decode($password));
      }
    }
    return null;
  }


  /**
   * Deletes the password for the given option.
   *
   * @param string $id_option
   *
   * @return bool
   */
  public function delete(string $id_option): bool
  {
    $arch =& $this->class_cfg['arch']['passwords'];
    return (bool)$this->db->delete($this->class_cfg['table'], [
      $arch['id_option'] => $id_option
    ]);
  }

  public function user_delete(string $id_pref, bbn\user $user)
  {
    if ($user->is_auth()) {
      $pref = \bbn\user\preferences::get_instance();
      if ($pref->is_authorized($id_pref)) {
        $arch =& $this->class_cfg['arch']['passwords'];
        return $this->db->delete($this->class_cfg['table'], [
          $arch['id_user_option'] => $id_pref
        ]);
      }
    }
  }
}
