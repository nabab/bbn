<?php

namespace User;

use bbn\Db;
use bbn\Mvc;
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


  protected User $user;

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

    Mvc::initPath();
    $this->init();
  }


  protected function init()
  {
    $db_mock = \Mockery::mock(Db::class);

    $db_mock->shouldReceive('insert')->once()->andReturnTrue();
    $db_mock->shouldReceive('lastId')->once()->andReturn(1);

    // Init the User class to init a session.
    $this->user = new User($db_mock);
    \Mockery::close();

    $this->session_index = $this->getNonPublicProperty('sessIndex');
    $this->user_index    = $this->getNonPublicProperty('userIndex');

    $this->db_mock = \Mockery::mock(Db::class);
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
   * Callback to apply additional mockery expectations.
   *
   * @param array $selectOneReturn
   * @param callable|null $callback
   */
  protected function login(?callable $callback = null)
  {
    if ($callback) {
      $callback($this->db_mock);
    }

    $this->login_post['appui_salt'] = $this->user->getSalt();

    $this->user = new User($this->db_mock, $this->login_post);
  }


  /**
   * Manually login a user.
   *
   * @param int   $id
   * @param array $sess_cfg
   * @return array
   * @throws \ReflectionException
   */
  protected function loginAs(int $id, array $sess_cfg)
  {
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
    $login_method->invoke($this->user, $id);

    return $expected_session_data;
  }


  public function getInstance()
  {
    return $this->user;
  }


  /** @test */
  public function user_can_login()
  {
    $this->login(
      function ($db_mock) {
        $db_mock->shouldReceive('selectOne')->andReturn(
          json_encode(
            [
              'fingerprint' => $this->getNonPublicMethod('getPrint')->invoke(
                $this->user, $this->getSessionData()[$this->session_index]['fingerprint']
              ),
              'last_renew'  => time()
            ]
          ),
          $this->user_id,
          $this->getNonPublicMethod('_hash')->invoke($this->user, 'password')
        );

        $db_mock->shouldReceive('update')->once()->andReturnTrue();

        $db_mock->shouldReceive('rselect')->once()->andReturnNull();
      }
    );

    $this->assertNull($this->user->getError());
    $this->assertTrue($this->user->isAuth());
  }


  /** @test */
  public function isReset_method_returns_false_if_the_request_is_not_a_password_reset()
  {
    $this->login( function ($db_mock) {
      $db_mock->shouldReceive('selectOne')->andReturn(
        json_encode(
          [
            'fingerprint' => $this->getNonPublicMethod('getPrint')->invoke(
              $this->user, $this->getSessionData()[$this->session_index]['fingerprint']
            ),
            'last_renew'  => time()
          ]
        ),
        $this->user_id,
        $this->getNonPublicMethod('_hash')->invoke($this->user, 'password')
      );

      $db_mock->shouldReceive('update')->once()->andReturnTrue();

      $db_mock->shouldReceive('rselect')->once()->andReturnNull();
    });

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

    // Now let's manually login as a user
    $expected_session_data = $this->loginAs(2, $sess_cfg);

    // The selectOne method that occurs in the _retrieve_session method
    $this->db_mock->shouldReceive('selectOne')->once()->andReturn(json_encode($sess_cfg));

    // The update method that occurs in the _authenticate method
    $this->db_mock->shouldReceive('update')->once()->andReturn(1);

    // The rselect method that occurs in the _user_info method
    $this->db_mock->shouldReceive('rselect')->once()->andReturn($expected_session_data);

    // Init the Class again so the checkSession method could run.
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


  /** @test */
  public function checkSalt_method_checks_the_actual_salt_against_a_given_string()
  {
    $this->assertTrue(isset($_SESSION[BBN_APP_NAME][$this->session_index]['salt']));
    $this->assertTrue($this->user->checkSalt($_SESSION[BBN_APP_NAME][$this->session_index]['salt']));
  }


  /** @test */
  public function getCfg_method_returns_the_user_current_configuration()
  {
    $this->assertNull($this->user->getCfg());

    $sess_cfg = $this->initSessionFingerPrint();

    $this->user = new User($this->db_mock);

    $expected_cfg = $this->loginAs(2, $sess_cfg);

    $this->assertSame(json_decode($expected_cfg['cfg'], true), $this->user->getCfg());
  }


  /** @test */
  public function getClassCfg_method_returns_the_current_class_configuration()
  {
    $this->assertSame($this->getClassCgf(), $this->user->getClassCfg());
  }


  /** @test */
  public function getPath_method_returns_the_dir_path_for_the_user()
  {
    $this->login( function ($db_mock) {
      $db_mock->shouldReceive('selectOne')->andReturn(
        json_encode(
          [
            'fingerprint' => $this->getNonPublicMethod('getPrint')->invoke(
              $this->user, $this->getSessionData()[$this->session_index]['fingerprint']
            ),
            'last_renew'  => time()
          ]
        ),
        $this->user_id,
        $this->getNonPublicMethod('_hash')->invoke($this->user, 'password')
      );

      $db_mock->shouldReceive('update')->once()->andReturnTrue();

      $db_mock->shouldReceive('rselect')->once()->andReturnNull();
    });

    $this->assertSame(BBN_DATA_PATH . "users/$this->user_id/data/", $this->user->getPath());
  }


  /** @test */
  public function getTmpDir_method_returns_the_tmp_dir_path_for_the_user()
  {
    $this->login(
      function ($db_mock) {
        $db_mock->shouldReceive('selectOne')->andReturn(
          json_encode(
            [
              'fingerprint' => $this->getNonPublicMethod('getPrint')->invoke(
                $this->user, $this->getSessionData()[$this->session_index]['fingerprint']
              ),
              'last_renew'  => time()
            ]
          ),
          $this->user_id,
          $this->getNonPublicMethod('_hash')->invoke($this->user, 'password')
        );

        $db_mock->shouldReceive('update')->once()->andReturnTrue();

        $db_mock->shouldReceive('rselect')->once()->andReturnNull();
      }
    );

    $this->assertSame(BBN_DATA_PATH . "users/$this->user_id/tmp/", $this->user->getTmpDir());
  }


  /** @test */
  public function getTables_method_returns_the_list_of_tables_used_by_the_current_class_or_null_if_not_found()
  {
    $this->assertSame($expected_tables = $this->getClassCgf()['tables'], $this->user->getTables());

    $this->setNonPublicPropertyValue('class_cfg', []);

    $this->assertNull($this->user->getTables());

    $this->initSessionFingerPrint();
    $this->user = new User($this->db_mock,[], ['tables' => ['foo' => 'bar']]);

    $this->assertSame(array_merge($expected_tables, ['foo' => 'bar']), $this->user->getTables());
  }


  /** @test */
  public function getFields_method_returns_list_of_fields_of_given_table_or_all_tables_and_null_if_not_found()
  {
    $this->assertSame($this->getClassCgf()['arch']['users'], $this->user->getFields('users'));
    $this->assertSame($this->getClassCgf()['arch']['groups'], $this->user->getFields('groups'));
    $this->assertNull($this->user->getFields('foo'));

    $this->assertSame($this->getClassCgf()['arch'], $this->user->getFields());

    $this->setNonPublicPropertyValue('class_cfg', []);

    $this->assertNull($this->user->getFields('users'));
    $this->assertNull($this->user->getFields());

    $this->initSessionFingerPrint();
    $this->user = new User(
      $this->db_mock,[], [
      'tables' => ['foo' => 'bar'],
      'arch'   => ['foo' => ['field1', 'field2']]
      ]
    );

    $this->assertSame(['field1', 'field2'], $this->user->getFields('foo'));
    $this->assertSame($this->getClassCgf()['arch'], $this->user->getFields());
  }


  /** @test */
  public function updateInfo_method_changes_data_in_users_table_if_data_is_valid()
  {
    $class_cfg = $this->getClassCgf();

    $expected_session_data = [
      'id'        => 2,
      'id_group'  => 1,
      'email'     => 'foobar@mail.comm',
      'username'  => 'foobar',
      'login'     => 'baz',
      'admin'     => 0,
      'dev'       => 0,
      'theme'     => 'bar',
      'cfg'       => json_encode($this->initSessionFingerPrint()),
      'active'    => 1,
      'enckey'    => 'key'
    ];

    $this->login(
      function ($db_mock) use ($expected_session_data) {
        $db_mock->shouldReceive('selectOne')->andReturn(
          $this->user_id,
          $this->getNonPublicMethod('_hash')->invoke($this->user, 'password'),
          json_encode(
            [
              'fingerprint' => $this->getNonPublicMethod('getPrint')->invoke(
                $this->user, $this->getSessionData()[$this->session_index]['fingerprint']
              ),
              'last_renew'  => time()
            ]
          )
        );

        $db_mock->shouldReceive('update')->twice()->andReturnTrue();

        $db_mock->shouldReceive('rselect')->once()->andReturnNull();

        // This should be called by the method _user_info that occurs in updateInfo method
        $db_mock->shouldReceive('rselect')->once()->andReturn($expected_session_data);
      }
    );

    $expected_session_data['cfg'] = json_decode($expected_session_data['cfg'], true);

    $this->assertTrue($this->user->updateInfo(['email' => 'foobar@mail.com', 'theme' => 'bar']));
    $this->assertTrue(isset($this->getSessionData()[$this->user_index]));
    $this->assertSame($expected_session_data, $this->getSessionData()[$this->user_index]);

  }


  /** @test */
  public function updateInfo_method_does_not_changes_data_in_users_table_if_data_is_invalid()
  {
    $this->login(
      function ($db_mock) {
        $db_mock->shouldReceive('selectOne')->andReturn(
          json_encode(
            [
              'fingerprint' => $this->getNonPublicMethod('getPrint')->invoke(
                $this->user, $this->getSessionData()[$this->session_index]['fingerprint']
              ),
              'last_renew'  => time()
            ]
          ),
          $this->user_id,
          $this->getNonPublicMethod('_hash')->invoke($this->user, 'password')
        );

        $db_mock->shouldReceive('update')->once()->andReturnTrue();
        
        $db_mock->shouldReceive('rselect')->once()->andReturnNull();
      }
    );

    $this->assertFalse($this->user->updateInfo(['foo' => 'bar']));
    $this->assertFalse($this->user->updateInfo(['id' => 50]));
    $this->assertFalse($this->user->updateInfo(['auth' => 'foo']));
    $this->assertFalse($this->user->updateInfo(['pass' => 'foo']));
    $this->assertFalse($this->user->updateInfo(['cfg' => json_encode(['cfg_key' => 'cgf_value'])]));
  }


}
