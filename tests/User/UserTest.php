<?php

namespace User;

use bbn\Db;
use bbn\User;
use bbn\User\Session;
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

  protected $login_post = [
    'user' => 'user',
    'pass' => 'password'
  ];

  protected $reset_password_post = [
    'key'           => 'key',
    'id'            => 1,
    'pass1'         => 'new_pass',
    'pass2'         => 'new_pass',
    'appui_action'  => 'init_password'
  ];

  protected $user_id = 1;

  protected $session_index;

  protected $user_index;

  protected function getSessionData()
  {
    return $this->getNonPublicProperty('data',  $this->getSession());
  }


  protected function getSession()
  {
    return $this->getNonPublicProperty('session');
  }


  protected function getClassCgf()
  {
    return $this->getNonPublicProperty('class_cfg');
  }


  protected function setUp(): void
  {
    if (Session::singletonExists()) {
      Session::destroyInstance();
    }

    $this->db_mock = \Mockery::mock(Db::class);
    $this->init();
  }


  protected function init()
  {
    $db_mock = \Mockery::mock(Db::class);

    $db_mock->shouldReceive('insert')->once()->andReturnTrue();
    $db_mock->shouldReceive('lastId')->once()->andReturn(1);

    // Init the User class to init a session.
    $this->user = new User($db_mock);

    $this->session_index = $this->getNonPublicProperty('sessIndex');
    $this->user_index    = $this->getNonPublicProperty('userIndex');
  }


  protected function initSessionFingerPrint()
  {
    $get_print_method = $this->getNonPublicMethod('getPrint');
    $session_data     = $this->getSessionData();

    $this->db_mock->shouldReceive('selectOne')->once()->ordered('selectOnes')->andReturn(
      json_encode(
        $cfg = [
          'fingerprint' => $get_print_method->invoke(
            $this->user, $session_data[$this->session_index]['fingerprint']
          ),
          'last_renew'  => time()
        ]
      )
    );

    return $cfg;
  }


  /**
   * @param array $conditions
   * @return User
   * @throws \ReflectionException
   */
  protected function prepareLogingIn(array $conditions = [])
  {
    $class_cfg = $this->getClassCgf();
    // This is the db mock call of checking user credentials
    $this->db_mock->shouldReceive('selectOne')->once()->ordered('selectOnes')->with(
      $class_cfg['tables']['users'],
      $class_cfg['fields']['id'],
      X::mergeArrays(
        $conditions,
        [$class_cfg['arch']['users']['active'] => 1],
        [($class_cfg['arch']['users']['login'] ?? $class_cfg['arch']['users']['email']) => $this->login_post['user']]
      )
    )->andReturn($this->user_id);

    $hash_method = $this->getNonPublicMethod('_hash');

    // This is the db mock call of fetching user's password
    // from db to compare to the entered password.
    $this->db_mock->shouldReceive('selectOne')->once()->ordered('selectOnes')->andReturn(
      $hash_method->invoke($this->user, 'password')
    );

    $this->db_mock->shouldReceive('update')->andReturnTrue();

    $this->db_mock->shouldReceive('rselect')->ordered()->once()->andReturnNull();

    $this->login_post['appui_salt'] = $this->user->getSalt();
  }


  public function getInstance()
  {
    return $this->user;
  }


  /** @test */
  public function user_can_login()
  {
    $this->initSessionFingerPrint();
    $this->prepareLogingIn();
    $this->user = new User($this->db_mock, $this->login_post);

    $this->assertNull($this->user->getError());
    $this->assertTrue($this->user->isAuth());
  }


  /** @test */
  public function isReset_method_returns_false_if_the_request_is_not_a_password_reset()
  {
    $this->initSessionFingerPrint();
    $this->prepareLogingIn();
    $this->user = new User($this->db_mock, $this->login_post);

    $this->assertFalse($this->user->isReset());
  }


  /** @test */
  public function user_can_reset_password_when_new_password_and_magic_string_are_valid()
  {
    $this->initSessionFingerPrint();
    $this->db_mock->shouldReceive('rselect')->ordered()->once()->andReturn(
      [
        'magic'   => hash('sha256', $this->reset_password_post['key']),
        'id_user' => $this->user_id
      ]
    );

    $class_cfg = $this->getClassCgf();
    // Set expectations of updating password reset link to expired
    $this->db_mock->shouldReceive('update')->once()->andReturn(1)->with(
      $class_cfg['tables']['hotlinks'],
      [$class_cfg['arch']['hotlinks']['expire'] => date('Y-m-d H:i:s')],
      [$class_cfg['arch']['hotlinks']['id'] => $this->user_id]
    );

    $hash_method = $this->getNonPublicMethod('_hash');

    // Set expectations of updating the new password
    $this->db_mock->shouldReceive('insert')->once()->andReturn(1)->with(
      $class_cfg['tables']['passwords'], [
        $class_cfg['arch']['passwords']['pass']    => $hash_method->invoke($this->user, $this->reset_password_post['pass2']),
        $class_cfg['arch']['passwords']['id_user'] => $this->user_id,
        $class_cfg['arch']['passwords']['added']   => date('Y-m-d H:i:s')]
    );

    $this->user = new User($this->db_mock, $this->reset_password_post);

    $this->assertTrue($this->user->isReset());
    $this->assertSame((string)$this->user_id, $this->user->getId());
    $this->assertNull($this->user->getError());
    $this->assertFalse($this->user->isAuth());
  }


  /** @test */
  public function user_cannot_reset_password_when_new_password_and_confirmation_does_not_match()
  {
    $this->initSessionFingerPrint();
    $this->db_mock->shouldReceive('rselect')->ordered()->once()->andReturn(
      [
        'magic'   => hash('sha256', $this->reset_password_post['key']),
        'id_user' => $this->user_id
      ]
    );
    $this->reset_password_post['pass2'] = 'not_matching_password';

    // Set expectations that updating password reset link and new password
    // Should never be called
    $this->db_mock->shouldReceive('update')->once()->never();
    $this->db_mock->shouldReceive('insert')->once()->never();

    $this->user = new User($this->db_mock, $this->reset_password_post);

    $this->assertNotNull($this->user->getError());
    $this->assertSame(7, $this->user->getError()['code']);
    $this->assertTrue($this->user->isReset());
    $this->assertFalse($this->user->isAuth());
    $this->assertNull($this->user->getId());
  }


  /** @test */
  public function user_cannot_reset_password_when_magic_string_is_not_valid()
  {
    $this->initSessionFingerPrint();
    $this->db_mock->shouldReceive('rselect')->ordered()->once()->andReturn(
      [
        'magic'   => hash('sha256', 'non-matching-key'),
        'id_user' => $this->user_id
      ]
    );

    // Set expectations that updating password reset link and new password
    // Should never be called
    $this->db_mock->shouldReceive('update')->once()->never();
    $this->db_mock->shouldReceive('insert')->once()->never();

    $this->user = new User($this->db_mock, $this->reset_password_post);

    $this->assertNotNull($this->user->getError());
    $this->assertSame(18, $this->user->getError()['code']);
    $this->assertFalse($this->user->isReset());
    $this->assertFalse($this->user->isAuth());
    $this->assertNull($this->user->getId());
  }


  /** @test */
  public function it_should_check_for_session_if_it_is_not_a_login_nor_password_reset_request()
  {
    $sess_cfg = $this->initSessionFingerPrint();
    // Initiate a new User as non logged in user first
    $this->user = new User($this->db_mock);

    $this->assertFalse($this->user->isAuth());
    $this->assertNull($this->user->getId());
    $this->assertNotNull($this->user->getError());

    // Now let's login by calling the logIn method:

    // The update method that occurs in the _authenticate method when we call the logIn method
    $this->db_mock->shouldReceive('update')->once()->andReturn(1);

    // The rselect method that occurs in the _user_info method when we call the logIn method
    $this->db_mock->shouldReceive('rselect')->once()->andReturn(
      $expected_session_data = [
      'id'        => 2,
      'id_group'  => 1,
      'email'     => 'foo@mail.com',
      'username'  => 'foobar',
      'login'     => 'baz',
      'admin'     => 0,
      'dev'       => 0,
      'theme'     => 'theme_name',
      'cfg'       => json_encode($sess_cfg),
      'active'    => 1,
      'enckey'    => 'key'
      ]
    );

    $login_method = $this->getNonPublicMethod('logIn');
    $login_method->invoke($this->user, 2);

    // The selectOne method that occurs in the _retrieve_session method
    $this->db_mock->shouldReceive('selectOne')->once()->andReturn(json_encode($sess_cfg));

    // The update method that occurs in the _authenticate method
    $this->db_mock->shouldReceive('update')->once()->andReturn(1);

    // The rselect method that occurs in the _user_info method
    $this->db_mock->shouldReceive('rselect')->once()->andReturn($expected_session_data);

    $this->user = new User($this->db_mock);

    $actual_session_data = $this->getSessionData();

    $expected_session_data['cfg'] = json_decode($expected_session_data['cfg'], true);

    $this->assertSame($expected_session_data, $actual_session_data[$this->user_index]);
    $this->assertTrue($this->user->isAuth());
    $this->assertSame(2, (int)$this->user->getId());
    $this->assertNull($this->user->getError());
  }


  /** @test */
  public function getSalt_method_returns_the_salt_saved_in_session()
  {
    $this->assertTrue(isset($_SESSION[BBN_APP_NAME][$this->session_index]['salt']));
    $this->assertSame($_SESSION[BBN_APP_NAME][$this->session_index]['salt'], $this->user->getSalt());
  }


}
