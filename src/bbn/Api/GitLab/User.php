<?php
namespace bbn\Api\GitLab;

trait User
{


  /** @var string */
  protected $userURL = 'users/';

  /**
   * Get the users list
   * @return array
   */
  public function getUsers(): array
  {
    return $this->request($this->host . $this->userURL);
  }


  /**
   * Get a user info.
   * @param int $id The user id
   * @return array
   */
  public function getUser(int $id = null): array
  {
    return $this->request($this->host . !empty($id) ? $this->userURL . $id : 'user');
  }


}