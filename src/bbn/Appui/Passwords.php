<?php
namespace bbn\Appui;

use bbn;
use bbn\X;

/**
 * Passwords management in appui
 */
class Passwords extends bbn\Models\Cls\Db
{
  use bbn\Models\Tts\DbActions;

  /** @var bbn\Appui\Option An options object */
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
   * @param \bbn\Db $db Database connection
   */
  public function __construct(\bbn\Db $db) 
  {
    parent::__construct($db);
    $this->_init_class_cfg();
    $this->_o = Option::getInstance();
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
      $to_store = \bbn\Util\Enc::crypt64($password, BBN_ENCRYPTION_KEY);
      //var_dump(base64_encode($to_store));
      if ($this->db->insertUpdate(
        $this->class_cfg['table'],
        [
          $arch['id_option'] => $id_option,
          $arch['password'] => $to_store
        ]
      )
      ) {
        return true;
      }

      return false;
    }

    throw new \Exception(X::_("No passwod given or BBN_ENCRYPTION_KEY not defined"));
  }


  /**
   * Stores a password in the database for a user.
   *
   * @param string   $password The password to store
   * @param string   $id_pref  The ID in user_options
   * @param bbn\User $user     A user object
   *
   * @return bool
   */
  public function userStore(string $password, string $id_pref, bbn\User $user): bool
  {
    if (!$password) {
      throw new \Exception("No password given");
    }

    if (!($to_store = $user->crypt($password))) {
      throw new \Exception("Impossible to crypt the password");
    }

    $arch =& $this->class_cfg['arch']['passwords'];

    return (bool)$this->db->insertUpdate(
      $this->class_cfg['table'],
      [
        $arch['id_user_option'] => $id_pref,
        $arch['password'] => base64_encode($to_store)
      ]
    );

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
      if ($password = $this->db->selectOne(
        $this->class_cfg['table'],
        $arch['password'],
        [$arch['id_option'] => $id_option]
      )
      ) {
        return \bbn\Util\Enc::decrypt64($password, BBN_ENCRYPTION_KEY);
      }
    }
    return null;
  }


  /**
   * Returns a password for the given user's option.
   *
   * @param string   $id_pref The ID in user_options
   * @param bbn\User $user    A user object
   *
   * @return string|null
   */
  public function userGet(string $id_pref, bbn\User $user): ?string
  {
    if ($user->isAuth()) {
      $arch =& $this->class_cfg['arch']['passwords'];
      if ($password = $this->db->selectOne(
        $this->class_cfg['table'],
        $arch['password'],
        [$arch['id_user_option'] => $id_pref]
      )
      ) {
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
    return (bool)$this->db->delete(
      $this->class_cfg['table'],
      [$arch['id_option'] => $id_option]
    );
  }

  public function userDelete(string $id_pref, bbn\User $user)
  {
    if ($user->isAuth()) {
      $pref = \bbn\User\Preferences::getInstance();
      if ($pref->isAuthorized($id_pref)) {
        $arch =& $this->class_cfg['arch']['passwords'];
        return $this->db->delete(
          $this->class_cfg['table'], 
          [$arch['id_user_option'] => $id_pref]
        );
      }
    }
  }
}
