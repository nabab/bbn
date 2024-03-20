<?php

/**
 * @package user
 */

namespace bbn\User;

use bbn\Models\Cls\Basic;
use bbn\X;
use bbn\Str;
use bbn\User;
use bbn\User\Common;
use bbn\User\Implementor;
use bbn\Models\Tts\DbActions;


/**
 * A user authentication Class
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Authentication
 * @license   http://opensource.org/licenses/MIT MIT
 * @version 0.2r89
 * @todo Groups and hotlinks features
 * @todo Implement Cache for session requests' results?
 */
final class Fake extends Basic implements Implementor
{
  use Common;
  //use DbActions;

  /** @var User $realUser */
  protected $realUser;

  /**
   * User constructor.
   *
   * @param String $db
   * @param User   $usr
   */
  public function __construct($id, User $usr)
  {
    //$this->_init_class_cfg([]);
    $this->realUser = $usr;
    $this->class_cfg = $this->realUser->getClassConfig();
    $f = $this->class_cfg['arch']['users'];
    $this->db = $this->realUser->getDbInstance();
    $this->id = $id;
    $this->id_group = $this->db->selectOne($this->class_cfg['table'], $f['id_group'], [
      $f['id'] => $id
    ]);
    $this->auth = true;
  }


  public function isReset(): bool
  {
    return false;
  }


  /**
   * Returns the salt string kept in session.
   *
   * @return null|string
   */
  public function getSalt(): ?string
  {
    return '';
  }


  /**
   * Confronts the given string with the salt string kept in session.
   *
   * @return bool
   */
  public function checkSalt($salt): bool
  {
    return true;
  }


  /**
   * Returns the current user's configuration.
   *
   * @param string $attr
   * @return mixed
   */
  public function getCfg($attr = '')
  {
    return $attr ? '' : [];
  }


  /**
   * Stores or deletes data in the object for the current authenticated user.
   *
   * @param string|array $index The name of the index to set, or an associative array of key/values
   * @param mixed        $data  The data to store; if null the given index will be unset
   *
   * @return self Chainable
   */
  public function setData($index, $data = null): self
  {
    return $this;
  }


  /**
   * Returns the current configuration of this very class.
   *
   * @return array
   */
  public function getClassCfg(): array
  {
    return $this->class_cfg;
  }


  /**
   * Returns the list of tables used by the current class.
   * @return array
   */
  public function getTables(): ?array
  {
    if (!empty($this->class_cfg)) {
      return $this->class_cfg['tables'];
    }

    return null;
  }


  /**
   * Returns the list of fields of the given table, and if empty for each table.
   *
   * @param string $table
   * @return array|null
   */
  public function getFields(string $table = ''): ?array
  {
    if (!empty($this->class_cfg)) {
      if ($table) {
        return $this->class_cfg['arch'][$table] ?? null;
      }

      return $this->class_cfg['arch'];
    }

    return null;
  }


  /**
   * Changes the data in the user's table.
   *
   * @param array $d The new data
   * @return bool
   */
  public function updateInfo(array $d): bool
  {
    /*
    if ($this->checkSession()) {
      $update = [];
      foreach ($d as $key => $val) {
        if (($key !== $this->fields['id'])
          && ($key !== $this->fields['cfg'])
          && ($key !== 'auth')
          && ($key !== 'admin')
          && ($key !== 'dev')
          && ($key !== 'pass')
        ) {
          $update[$key] = $val;
        }
      }

      if (\count($update) > 0) {
        $r = (bool)$this->update($this->getId(), $update, true);
        /** @todo Why did I do this?? * /
        if ($r) {
          /** @todo WTF?? * /
          $this->setSession(['cfg' => false]);
          $this->_user_info();
        }
      }
      return $r ?? false;
    }
    */

    return false;
  }


  /**
   * Encrypts the given string to match the password.
   *
   * @param string $st
   * @return string
   */
  public function getPassword(string $st): string
  {
    return '';
  }


  /**
   * Returns true after the log in moment.
   *
   * @return bool
   */
  public function isJustLogin(): bool
  {
    return false;
  }


  /**
   * Sets the given attribute(s) in the user's session.
   *
   * @return self
   */
  public function setSession($attr): self
  {
    return $this;
  }


  /**
   * Unsets the given attribute(s) in the user's session if exists.
   *
   * @return self
   */
  public function unsetSession(): self
  {
    return $this;
  }


  /**
   * Returns session property from the session's user array (userIndex).
   *
   * @param null|string The property to get
   * @return mixed
   */
  public function getSession($attr = null)
  {
    return null;
  }


  /**
   * Gets an attribute or the whole the "session" part of the session  (sessIndex).
   *
   * @param string|null $attr Name of the attribute to get.
   * @return mixed|null
   */
  public function getOsession($attr = null)
  {
    return null;
  }


  /**
   * Sets an attribute the "session" part of the session (sessIndex).
   *
   * @return self
   */
  public function setOsession(): self
  {
    return $this;
  }


  /**
   * Checks if the given attribute exists in the user's session.
   *
   * @return bool
   */
  public function hasSession($attr): bool
  {
    return true;
  }


  /**
   * Updates last activity value for the session in database.
   *
   * @return self
   */
  public function updateActivity(): self
  {
    return $this;
  }


  /**
   * Saves the session config in the database.
   *
   * @todo Use it only when needed!
   * @return self
   */
  public function saveSession(bool $force = false): self
  {
    return $this;
  }


  /**
   * Closes the session in the database.
   *
   * @param bool $with_session If true deletes also the session information
   * @return self
   */
  public function closeSession($with_session = false): self
  {
    return $this;
  }


  /**
   * Returns false if the max number of connections attempts has been reached
   * @return bool
   */
  public function checkAttempts(): bool
  {
    return true;
  }


  /**
   * Saves the user's config in the cfg field of the users' table.
   *
   * return self
   */
  public function saveCfg(): self
  {
    if ($this->check()) {
      $this->db->update(
        $this->class_cfg['tables']['users'],
        [$this->fields['cfg'] => json_encode($this->cfg)],
        [$this->fields['id'] => $this->id]
      );
    }

    return $this;
  }


  /**
   * Saves the attribute(s) values into the session config.
   *
   * return self
   */
  public function setCfg($attr): self
  {
    return $this;
  }


  /**
   * Unsets the attribute(s) in the session config.
   *
   * @param $attr
   * @return self
   */
  public function unsetCfg($attr): self
  {
    return $this;
  }


  /**
   * Regathers information from the database.
   *
   * @return self
   */
  public function refreshInfo(): self
  {
    return $this;
  }


  /**
   * Retrieves user's info from session if needed and checks if authenticated.
   *
   * @return bool
   */
  public function checkSession(): bool
  {
    return true;
  }


  /**
   * Checks whether the user is an admin or not.
   *
   * @return bool
   */
  public function isAdmin(): bool
  {
    return false;
  }


  /**
   * Checks whether the user is an (admin or developer) or not.
   *
   * @return bool
   */
  public function isDev(): bool
  {
    return false;
  }


  /**
   * Gets a bbn\User\Manager instance.
   *
   * @return User\Manager
   */
  public function getManager(): Manager
  {
    return new Manager($this->realUser);
  }


  /**
   * Change the password in the database after checking the current one.
   *
   * @param string $old_pass The current password
   * @param string $new_pass The new password
   * @return bool
   */
  public function setPassword(string $old_pass, string $new_pass): bool
  {
    return $this->forcePassword($new_pass);
  }


  /**
   * Returns the full name of the given user or the current one.
   *
   * @return string|null
   */
  public function getName($usr = null): ?string
  {
    if ($this->auth) {
      if (\is_null($usr)) {
        $usr = $this->id;
      }

      if (Str::isUid($usr)) {
        $mgr = $this->getManager();
        $usr = $mgr->getUser($usr);
      }

      if (isset($this->class_cfg['show'], $usr[$this->class_cfg['show']])) {
        return $usr[$this->class_cfg['show']];
      }
    }

    return null;
  }


  /**
   * Generates and insert a token in database.
   *
   * @return string|null
   */
  public function addToken(): ?string
  {
    return null;
  }


  /**
   * Returns the email of the given user or the current one.
   *
   * @return string|null
   */
  public function getEmail($usr = null): ?string
  {
    if ($this->auth) {
      if (\is_null($usr)) {
        $usr = $this->getSession();
      } elseif (str::isUid($usr) && ($mgr = $this->getManager())) {
        $usr = $mgr->getUser($usr);
      }

      if (isset($this->fields['email'], $usr[$this->fields['email']])) {
        return $usr[$this->fields['email']];
      }
    }

    return null;
  }


}
