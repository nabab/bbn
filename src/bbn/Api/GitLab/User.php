<?php
namespace bbn\Api\GitLab;

trait User
{


  /** @var string */
  protected $userURL = 'users/';

  /** @var string The current user ID */
  protected $userID;

  /**
   * Gets the users list
   * @return array
   */
  public function getUsers(): array
  {
    return $this->request($this->userURL);
  }


  /**
   * Gets a user info.
   * @param int $id The user id
   * @return array
   */
  public function getUser(int $id = null): array
  {
    return $this->request(!empty($id) ? $this->userURL . $id : 'user');
  }


  /**
   * Gets the current user ID
   * @return int
   */
  public function getUserID(): int
  {
    if (empty($this->userID)
      && ($r = $this->request('user'))
    ) {
      $this->userID = $r['id'];
    }
    return $this->userID;
  }


}