<?php
namespace bbn\Appui;

use bbn;
use bbn\Db;
use bbn\Models\Cls\Db as DbModel;
use bbn\X;
use bbn\User;
use bbn\Appui\Option;
use bbn\Util\Enc;
use bbn\Models\Tts\DbActions;
use bbn\Models\Tts\LocaleDatabase;
use bbn\User\Preferences;
use Exception;

/**
 * Passwords management in appui
 */
class Passwords extends DbModel
{
  use DbActions;
  use LocaleDatabase;

  /** @var Option An options object */
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
   * @param Db $db Database connection
   */
  public function __construct(Db $db)
  {
    parent::__construct($db);
    $this->initClassCfg();
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
      $to_store = Enc::crypt64($password, BBN_ENCRYPTION_KEY);
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

    throw new Exception(X::_("No passwod given or BBN_ENCRYPTION_KEY not defined"));
  }


  /**
   * Stores a password in the database for a user.
   *
   * @param string   $password The password to store
   * @param string   $id_pref  The ID in user_options
   * @param User $user     A user object
   *
   * @return bool
   */
  public function userStore(string $password, string $id_pref, User $user): bool
  {
    if (!$password) {
      throw new Exception("No password given");
    }

    if (!($to_store = $user->crypt($password))) {
      throw new Exception("Impossible to crypt the password");
    }

    $db = $this->db;
    $pref = Preferences::getInstance();
    $currentPrefUser = $pref->getUserInstance();
    $currentPrefUserId = $currentPrefUser->getId();
    $userId = $user->getId();
    $userChanged = $userId !== $currentPrefUserId;
    if ($userChanged) {
      $pref->setUser($user);
    }

    if ($pref->isLocale($id_pref, $pref->getClassCfg()['table'])) {
      $db = $this->getLocaleDb($userId);
    }

    if ($userChanged) {
      $pref->setUser($currentPrefUser);
    }

    return (bool)$db->insertUpdate(
      $this->class_cfg['table'],
      [
        $this->fields['id_user_option'] => $id_pref,
        $this->fields['password'] => base64_encode($to_store)
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
        return Enc::decrypt64($password, BBN_ENCRYPTION_KEY);
      }
    }
    return null;
  }


  /**
   * Returns a password for the given user's option.
   *
   * @param string   $id_pref The ID in user_options
   * @param User $user    A user object
   *
   * @return string|null
   */
  public function userGet(string $id_pref, User $user): ?string
  {
    if ($user->isAuth()) {
      $db = $this->db;
      $pref = Preferences::getInstance();
      $currentPrefUser = $pref->getUserInstance();
      $currentPrefUserId = $currentPrefUser->getId();
      $userId = $user->getId();
      $userChanged = $userId !== $currentPrefUserId;
      if ($userChanged) {
        $pref->setUser($user);
      }

      if ($pref->isLocale($id_pref, $pref->getClassCfg()['table'])) {
        $this->setLocaleDb($userId);
        $db = $this->getLocaleDb();
      }

      if ($userChanged) {
        $pref->setUser($currentPrefUser);
      }

      if ($password = $db->selectOne(
        $this->class_cfg['table'],
        $this->fields['password'],
        [$this->fields['id_user_option'] => $id_pref]
      )) {
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

  public function userDelete(string $id_pref, User $user)
  {
    $res = 0;
    if ($user->isAuth()) {
      $db = $this->db;
      $pref = Preferences::getInstance();
      $currentPrefUser = $pref->getUserInstance();
      $currentPrefUserId = $currentPrefUser->getId();
      $userId = $user->getId();
      $userChanged = $userId !== $currentPrefUserId;
      if ($userChanged) {
        $pref->setUser($user);
      }

      if ($pref->isAuthorized($id_pref)) {
        if ($pref->isLocale($id_pref, $pref->getClassCfg()['table'])) {
          $this->setLocaleDb($userId);
          $db = $this->getLocaleDb();
        }

        $res =  $db->delete(
          $this->class_cfg['table'],
          [$this->fields['id_user_option'] => $id_pref]
        );
      }

      if ($userChanged) {
        $pref->setUser($currentPrefUser);
      }

    }

    return $res;
  }
}
