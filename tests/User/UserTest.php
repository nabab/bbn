<?php

namespace User;

use bbn\ApiUser;
use bbn\Db;
use bbn\User;
use bbn\X;
use PHPUnit\Framework\TestCase;
use tests\Reflectable;

class UserTest extends TestCase
{
  use Reflectable;


  protected function tearDown(): void
  {
    \Mockery::close();
  }


  protected $user;

  protected $db_mock;

  protected User\Session $session;

  protected $post = [
    'user' => 'user',
    'pass' => 'password'
  ];

  protected $user_id = 1;


  protected function setUp(): void
  {
    $db_mock = \Mockery::mock(Db::class);

    $db_mock->shouldReceive('selectOne')->andReturnNull();
    $db_mock->shouldReceive('insert')->andReturnTrue();
    $db_mock->shouldReceive('lastId')->andReturn(1);

    // Init the User class to init a session.
    $this->user    = new User($db_mock);
    $this->session = $this->getNonPublicProperty('session');

    $this->db_mock = \Mockery::mock(Db::class);
  }


  /**
   * @param array $conditions
   * @return User
   * @throws \ReflectionException
   */
  protected function prepareLogingIn(array $conditions = [])
  {
    $session_data     = $this->getNonPublicProperty('data', $this->session);
    $session_index    = $this->getNonPublicProperty('sessIndex');
    $get_print_method = $this->getNonPublicMethod('getPrint');

    $this->db_mock->shouldReceive('selectOne')->once()->ordered('selectOnes')->andReturn(
      json_encode(
        [
          'fingerprint' => $get_print_method->invoke($this->user, $session_data[$session_index]['fingerprint']),
          'last_renew'  => time()
        ]
      )
    );

    $class_cfg = $this->getNonPublicProperty('class_cfg');

    // This is the db mock call of checking user credentials
    $this->db_mock->shouldReceive('selectOne')->once()->ordered('selectOnes')->with(
      $class_cfg['tables']['users'],
      $class_cfg['fields']['id'],
      X::mergeArrays(
        $conditions,
        [$class_cfg['arch']['users']['active'] => 1],
        [($class_cfg['arch']['users']['login'] ?? $class_cfg['arch']['users']['email']) => $this->post['user']]
      )
    )->andReturn($this->user_id);

    $_crypt_method = $this->getNonPublicMethod('_crypt');

    // This is the db mock call of fetching user's password
    // from db to compare to the entered password.
    $this->db_mock->shouldReceive('selectOne')->once()->ordered('selectOnes')->andReturn(
      $_crypt_method->invoke($this->user, 'password')
    );

    $this->db_mock->shouldReceive('update')->andReturnTrue();

    $this->db_mock->shouldReceive('rselect')->ordered()->once()->andReturnNull();

    $this->post['appui_salt'] = $this->user->getSalt();
  }


  public function getInstance()
  {
    return $this->user;
  }


  /** @test */
  public function internal_user_can_login()
  {
    $this->prepareLogingIn();
    $user = new User($this->db_mock, $this->post);

    $this->assertNull($user->getError());
  }


  /** @test */
  public function api_user_can_login()
  {
    $this->prepareLogingIn(['id_group' => 2]);

    $user = new ApiUser(2, $this->db_mock, $this->post, []);

    $this->assertNull($user->getError());
  }


}
