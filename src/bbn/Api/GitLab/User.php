<?php
namespace bbn\Api\GitLab;

trait User
{

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


  /**
   * Gets the users list of the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @return array
   */
  public function getProjectUsers($project): array
  {
    return $this->request($this->projectURL . $project . '/members/all');
  }


  /**
   * Inserts an user into the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param int $user The user ID
   * @param int $role The user role ID
   * @return bool
   */
  public function insertProjectUser($project, int $user, int $role): array
  {
    return $this->post($this->projectURL . $project . '/members', [
      'user_id' => $user,
      'access_level' => $role
    ]);
  }


  /**
   * Removes an user from the given project
   * @param int|string $project ID or URL-encoded path of the project
   * @param int The user ID
   * @return bool
   */
  public function removeProjectUser($project, int $user): bool
  {
    return $this->delete($this->projectURL . $project . '/members/' . $user);
  }


}