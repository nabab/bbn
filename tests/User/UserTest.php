<?php

namespace User;

use bbn\Db;
use bbn\Mvc;
use bbn\User;
use bbn\User\Session;
use Mockery\Mock;
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
    'pass1'         => 'new_pass',
    'pass2'         => 'new_pass',
    'appui_action'  => 'init_password'
  ];

  protected $user_id = '7f4a2c70bcac11eba47652540000cfaa';

  protected $session_id = '7f4a2c70bcac11eba47652540000cfbe';

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


  protected function getConfig()
  {
    return $this->getNonPublicProperty('cfg');
  }


  protected function getSessionConfig()
  {
    return $this->getNonPublicProperty('sess_cfg');
  }


  protected function getfields()
  {
    return $this->getNonPublicProperty('fields');
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
    $db_mock->shouldReceive('lastId')->once()->andReturn($this->session_id);

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
   * @param array         $selectOneReturn
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


  protected function simpleLogin()
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
  }


  /**
   * @param array|null $data
   * @return array
   * @throws \ReflectionException
   */
  protected function loginWithSessionData(?array $data = null)
  {
    $session_data = $data ?? $this->getExpectedSession();

    $this->login(
      function ($db_mock) use ($session_data) {
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

        $db_mock->shouldReceive('update')->once()->andReturnTrue();

        // This should be called by the method _user_info that occurs in updateInfo method
        $db_mock->shouldReceive('rselect')->once()->andReturn($session_data);
      }
    );

    return $session_data;
  }


  protected function getExpectedSession()
  {
    return [
      'id'        => $this->user_id,
      'id_group'  => 1,
      'email'     => 'foobar@mail.comm',
      'name'      => 'full name',
      'username'  => 'foobar',
      'login'     => 'baz',
      'admin'     => 0,
      'dev'       => 0,
      'theme'     => 'bar',
      'cfg'       => json_encode($this->initSessionFingerPrint()),
      'active'    => 1,
      'enckey'    => 'key'
    ];
  }


  /**
   * Manually login a user.
   *
   * @param string $id
   * @param array  $sess_cfg
   * @return array
   * @throws \ReflectionException
   */
  protected function loginAs(string $id, array $sess_cfg)
  {
    // The update method that occurs in the _authenticate method when we call the logIn method
    $this->db_mock->shouldReceive('update')->once()->andReturn(1);

    // The rselect method that occurs in the _user_info method when we call the logIn method
    $this->db_mock->shouldReceive('rselect')->once()->andReturn(
      $expected_session_data = [
        'id'        => $this->user_id,
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
    $this->simpleLogin();

    $this->assertNull($this->user->getError());
    $this->assertTrue($this->user->isAuth());
  }


  /** @test */
  public function isReset_method_returns_false_if_the_request_is_not_a_password_reset()
  {
    $this->simpleLogin();

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

    $this->reset_password_post['id'] = $this->user_id;

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

    $this->reset_password_post['id'] = $this->user_id;

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

    $this->reset_password_post['id'] = $this->user_id;

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
    $expected_session_data = $this->loginAs($this->user_id, $sess_cfg);

    $actual_session_data = $this->getSessionData();

    $expected_session_data['cfg'] = json_decode($expected_session_data['cfg'], true);

    $this->assertSame($expected_session_data, $actual_session_data[$this->user_index]);
    $this->assertTrue($this->user->isAuth());
    $this->assertSame($this->user_id, $this->user->getId());
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
    $this->simpleLogin();

    $this->assertSame(BBN_DATA_PATH . "users/$this->user_id/data/", $this->user->getPath());
  }


  /** @test */
  public function getTmpDir_method_returns_the_tmp_dir_path_for_the_user()
  {
    $this->simpleLogin();

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
    $expected_session_data = $this->getExpectedSession();

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
    $this->simpleLogin();

    $this->assertFalse($this->user->updateInfo(['foo' => 'bar']));
    $this->assertFalse($this->user->updateInfo(['id' => 50]));
    $this->assertFalse($this->user->updateInfo(['auth' => 'foo']));
    $this->assertFalse($this->user->updateInfo(['pass' => 'foo']));
    $this->assertFalse($this->user->updateInfo(['cfg' => json_encode(['cfg_key' => 'cgf_value'])]));
  }


  /** @test */
  public function getPassword_method_encrypts_the_given_string()
  {
    $this->assertSame(
      $this->getNonPublicMethod('_hash')->invoke($this->user, 'foo'),
      $this->user->getPassword('foo')
    );
  }


  /** @test */
  public function isJustLogin_method_checks_if_the_user_is_logged_in()
  {
    $this->assertFalse($this->user->isJustLogin());

    $this->simpleLogin();

    $this->assertTrue($this->user->isJustLogin());
  }


  /** @test */
  public function setSession_sets_the_given_attributes_in_the_session()
  {
    $session_data = $this->loginWithSessionData();

    $session_data['cfg'] = json_decode($session_data['cfg'], true);

    $this->assertSame($session_data, $this->getSessionData()[$this->user_index]);

    $this->user->setSession(['foo' => 'bar']);

    $this->assertTrue(
      isset($this->getSessionData()[$this->user_index]['foo'])
    );
    $this->assertSame(
      $session_data = array_merge($session_data, ['foo' => 'bar']),
      $this->getSessionData()[$this->user_index]
    );

    // Should not set the session since it has numeric keys
    $this->user->setSession(['foobar', 'baz']);

    $this->assertFalse(
      isset($this->getSessionData()[$this->user_index]['foobar'])
    );
    $this->assertSame($session_data, $this->getSessionData()[$this->user_index]);

    // This should be set transformed to ['foobar' => 'baz']
    $result = $this->user->setSession('foobar', 'baz');

    $this->assertTrue(
      isset($this->getSessionData()[$this->user_index]['foobar'])
    );
    $this->assertSame(
      array_merge($session_data, ['foobar' => 'baz']),
      $this->getSessionData()[$this->user_index]
    );
    $this->assertInstanceOf(User::class, $result);

    $this->user->setSession('test');
    $this->assertFalse(isset($this->getSessionData()[$this->user_index]['test']));
  }


  /** @test */
  public function unsetSession_method_unsets_the_given_attributes_from_session_if_exists()
  {
    $session_data = $this->loginWithSessionData();

    $session_data['cfg'] = json_decode($session_data['cfg'], true);

    $this->user->setSession(['foo' => 'bar']);

    $this->assertSame(
      array_merge($session_data, ['foo' => 'bar']),
      $this->getSessionData()[$this->user_index]
    );

    $this->user->unsetSession('foo');

    $this->assertSame(
      $session_data,
      $this->getSessionData()[$this->user_index]
    );
  }


  /** @test */
  public function getSession_method_returns_session_property_from_session_user_info()
  {
    $session_data = $this->loginWithSessionData();

    $this->assertSame($session_data['email'], $this->user->getSession('email'));
    $this->assertSame($session_data['username'], $this->user->getSession('username'));
    $this->assertSame($session_data['id'], $this->user->getSession('id'));
    $this->assertSame(
      $session_data['cfg'] = json_decode($session_data['cfg'], true),
      $this->user->getSession('cfg')
    );
    $this->assertSame($session_data, $this->user->getSession());
  }


  /** @test */
  public function getOsession_method_returns_an_attribute_or_whole_session_from_session_session()
  {
    $this->loginWithSessionData();

    $session_data = $this->getSessionData()[$this->session_index];

    $this->assertNull($this->user->getOsession('email'));
    $this->assertNull($this->user->getOsession('username'));

    $this->assertSame($session_data['salt'], $this->user->getOsession('salt'));
    $this->assertSame($session_data['fingerprint'], $this->user->getOsession('fingerprint'));
    $this->assertSame($session_data['id_session'], $this->user->getOsession('id_session'));
    $this->assertSame($session_data, $this->user->getOsession());
  }


  /** @test */
  public function method_setOsession_sets_an_attribute_in_the_sessIndex_part_of_the_session()
  {
    $this->user->setOsession('foo', 'bar');
    $this->user->setOsession('baz', ['key' => 'value']);
    $this->user->setOsession('obj', (object)['key' => 'value']);

    $session_data = $this->getSessionData()[$this->session_index];

    $this->assertTrue(isset($session_data['foo']));
    $this->assertSame('bar', $session_data['foo']);

    $this->assertTrue(isset($session_data['baz']));
    $this->assertSame(['key' => 'value'], $session_data['baz']);

    $this->assertTrue(isset($session_data['obj']));
    $this->assertIsObject($session_data['obj']);
    $this->assertTrue(isset($session_data['obj']->key));
    $this->assertSame('value', $session_data['obj']->key);
  }


  /** @test */
  public function hasSession_method_checks_if_the_given_attribute_exists_in_user_session()
  {
    $this->loginWithSessionData();

    $this->assertTrue($this->user->hasSession('email'));
    $this->assertTrue($this->user->hasSession('username'));
    $this->assertTrue($this->user->hasSession('id'));
    $this->assertTrue($this->user->hasSession('theme'));
    $this->assertFalse($this->user->hasSession('salt'));
  }


  /** @test */
  public function updateActivity_updates_last_activity_for_the_session_in_database_if_logged_in()
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

        // Set expectations that the Db::update will be called twice
        // Once when authenticating and the other when updating activity
        $db_mock->shouldReceive('update')->twice()->andReturnTrue();

        $db_mock->shouldReceive('rselect')->once()->andReturnNull();
      }
    );

    $result = $this->user->updateActivity();
    $this->assertInstanceOf(User::class, $result);
  }


  /** @test */
  public function updateActivity_does_not_update_last_activity_if_not_logged_in()
  {
    $this->db_mock->shouldNotReceive('update');

    $result = $this->user->updateActivity();
    $this->assertInstanceOf(User::class, $result);
  }


  /** @test */
  public function saveSession_method_saves_the_session_config_in_database_if_the_current_time_exceends_last_renew_by_two_seconds_or_more()
  {
    $this->login(
      function ($db_mock) {
        $db_mock->shouldReceive('selectOne')->andReturn(
          json_encode(
            [
              'fingerprint' => $this->getNonPublicMethod('getPrint')->invoke(
                $this->user, $this->getSessionData()[$this->session_index]['fingerprint']
              ),
              'last_renew'  => time() - 2 // Set the time - 2 so that it's eligible to be updated
            ]
          ),
          $this->user_id,
          $this->getNonPublicMethod('_hash')->invoke($this->user, 'password')
        );

        // Set expectations that the Db::update will be called twice
        // Once when authenticating and the other when updating the session in database
        $db_mock->shouldReceive('update')->twice()->andReturnTrue();

        $db_mock->shouldReceive('rselect')->once()->andReturnNull();
      }
    );

    $result = $this->user->saveSession();

    $this->assertInstanceOf(User::class, $result);
  }


  /** @test */
  public function saveSession_method_saves_the_session_config_in_database_if_the_last_renew_does_not_exists()
  {
    $this->login(
      function ($db_mock) {
        $db_mock->shouldReceive('selectOne')->andReturn(
          json_encode(
            [
              'fingerprint' => $this->getNonPublicMethod('getPrint')->invoke(
                $this->user, $this->getSessionData()[$this->session_index]['fingerprint']
              )
            ]
          ),
          $this->user_id,
          $this->getNonPublicMethod('_hash')->invoke($this->user, 'password')
        );

        // Set expectations that the Db::update will be called twice
        // Once when authenticating and the other when updating the session in database
        $db_mock->shouldReceive('update')->twice()->andReturnTrue();

        $db_mock->shouldReceive('rselect')->once()->andReturnNull();
      }
    );

    $result = $this->user->saveSession();

    $this->assertInstanceOf(User::class, $result);
  }


  /** @test */
  public function saveSession_method_does_not_save_the_session_in_database_if_current_time_does_not_exceed_last_renew()
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

        // Set expectations that the Db::update will be called ONLY once when authenticating
        // And not called in the saveSession method
        $db_mock->shouldReceive('update')->once()->andReturnTrue();

        $db_mock->shouldReceive('rselect')->once()->andReturnNull();
      }
    );

    $result = $this->user->saveSession();

    $this->assertInstanceOf(User::class, $result);
  }


  /** @test */
  public function saveSession_method_saves_the_session_config_in_database_if_the_last_renew_is_empty()
  {
    $this->login(
      function ($db_mock) {
        $db_mock->shouldReceive('selectOne')->andReturn(
          json_encode(
            [
              'fingerprint' => $this->getNonPublicMethod('getPrint')->invoke(
                $this->user, $this->getSessionData()[$this->session_index]['fingerprint']
              ),
              'last_renew' => ''
            ]
          ),
          $this->user_id,
          $this->getNonPublicMethod('_hash')->invoke($this->user, 'password')
        );

        // Set expectations that the Db::update will be called twice
        // Once when authenticating and the other when updating the session in database
        $db_mock->shouldReceive('update')->twice()->andReturnTrue();

        $db_mock->shouldReceive('rselect')->once()->andReturnNull();
      }
    );

    $result = $this->user->saveSession();

    $this->assertInstanceOf(User::class, $result);
  }


  /** @test */
  public function saveSession_method_is_forced_to_save_the_session_config_in_database_without_considering_last_renew()
  {
    $this->login(
      function ($db_mock) {
        $db_mock->shouldReceive('selectOne')->andReturn(
          json_encode(
            [
              'fingerprint' => $this->getNonPublicMethod('getPrint')->invoke(
                $this->user, $this->getSessionData()[$this->session_index]['fingerprint']
              ),
              'last_renew' => time() // This should fail if no forcing is used
            ]
          ),
          $this->user_id,
          $this->getNonPublicMethod('_hash')->invoke($this->user, 'password')
        );

        // Set expectations that the Db::update will be called twice
        // Once when authenticating and the other when updating the session in database
        $db_mock->shouldReceive('update')->twice()->andReturnTrue();

        $db_mock->shouldReceive('rselect')->once()->andReturnNull();
      }
    );

    $result = $this->user->saveSession(true);

    $this->assertInstanceOf(User::class, $result);
  }


  /** @test */
  public function saveSession_method_does_not_save_the_session_in_database_if_not_logged_in()
  {
    $this->db_mock->shouldNotReceive('update');

    $this->assertFalse($this->user->check());
    $this->assertInstanceOf(User::class, $this->user->saveSession());
  }


  /** @test */
  public function closeSession_method_closes_the_session_in_the_database()
  {
    $expected_session_data = $this->getExpectedSession();

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
              'last_renew' => time()
            ]
          )
        );

        // Set expectations that the Db::update will be called twice
        // Once when authenticating and the other when closing the session in database
        $db_mock->shouldReceive('update')->twice()->andReturnTrue();

        // This should be called by the method _user_info that occurs in updateInfo method
        $db_mock->shouldReceive('rselect')->once()->andReturn($expected_session_data);
      }
    );
    $expected_session_data['cfg'] = json_decode($expected_session_data['cfg'], true);

    $this->assertTrue(!empty($this->getSessionData()[$this->user_index]));
    $this->assertSame($expected_session_data, $this->getSessionData()[$this->user_index]);

    $result = $this->user->closeSession();

    $this->assertTrue(empty($this->getSessionData()[$this->user_index]));
    $this->assertFalse($this->user->isAuth());
    $this->assertNull($this->user->getId());
    $this->assertNull($this->getNonPublicProperty('sess_cfg'));
    $this->assertInstanceOf(User::class, $result);
  }


  /** @test */
  public function closeSession_method_closes_the_session_in_the_database_and_unsets_data_from_session()
  {
    $expected_session_data = $this->getExpectedSession();

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
              'last_renew' => time()
            ]
          )
        );

        // Set expectations that the Db::update will be called twice
        // Once when authenticating and the other when closing the session in database
        $db_mock->shouldReceive('update')->twice()->andReturnTrue();

        // This should be called by the method _user_info that occurs in updateInfo method
        $db_mock->shouldReceive('rselect')->once()->andReturn($expected_session_data);
      }
    );
    $expected_session_data['cfg'] = json_decode($expected_session_data['cfg'], true);

    $this->assertTrue(!empty($this->getSessionData()[$this->user_index]));
    $this->assertSame($expected_session_data, $this->getSessionData()[$this->user_index]);

    $result = $this->user->closeSession(true);

    $this->assertTrue(!isset($this->getSessionData()[$this->user_index]));
    $this->assertTrue(empty($this->getSessionData()));
    $this->assertFalse($this->user->isAuth());
    $this->assertNull($this->user->getId());
    $this->assertNull($this->getNonPublicProperty('sess_cfg'));
    $this->assertInstanceOf(User::class, $result);
  }


  /** @test */
  public function checkAttempts_method_returns_true_if_no_number_of_attempts_is_recorded()
  {
    // Before logging in
    $this->assertTrue($this->user->checkAttempts());

    // Successful login
    $this->loginWithSessionData();

    $this->assertTrue($this->user->checkAttempts());
  }


  /** @test */
  public function checkAttempts_method_returns_false_if_the_max_number_of_connection_attempts_is_reached()
  {
    // Let's make a failed login
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
          'wrong_password',
        );
      }
    );
    $cfg = $this->getNonPublicProperty('cfg');
    $this->assertTrue(isset($cfg['num_attempts']));
    $this->assertTrue($cfg['num_attempts'] === 1);

    // Now let's adjust the num_attempts to be more than max_attempts
    $this->setNonPublicPropertyValue(
      'cfg',
      array_replace($cfg, ['num_attempts' => $this->getClassCgf()['max_attempts'] + 1])
    );

    $this->assertSame(
      $this->getClassCgf()['max_attempts'] + 1,
      $this->getNonPublicProperty('cfg')['num_attempts']
    );
    $this->assertNotNull($this->user->getError());
    $this->assertFalse($this->user->check());
    $this->assertFalse($this->user->checkAttempts());
  }


  /** @test */
  public function checkAttempts_method_returns_true_if_the_max_number_of_connection_attempts_is_not_reached()
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
          'wrong_password',
        );
      }
    );

    $cfg = $this->getNonPublicProperty('cfg');
    $this->assertTrue(isset($cfg['num_attempts']));
    $this->assertTrue($cfg['num_attempts'] === 1);

    $this->assertNotNull($this->user->getError());
    $this->assertFalse($this->user->check());
    $this->assertTrue($this->user->checkAttempts());
  }


  /** @test */
  public function saveCfg_method_saves_user_config_in_the_cfg_field_of_users_table()
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

        $db_mock->shouldReceive('update')->twice()->andReturnTrue();

        $db_mock->shouldReceive('rselect')->once()->andReturnNull();
      }
    );

    $this->assertInstanceOf(User::class, $this->user->saveCfg());
  }


  /** @test */
  public function saveCfg_method_does_nothing_if_not_logged_in()
  {
    $this->db_mock->shouldReceive('selectOne')->once()->andReturnNull();
    $this->db_mock->shouldReceive('insert')->once()->andReturnTrue();
    $this->db_mock->shouldReceive('lastId')->once()->andReturn($this->session_id);
    $this->db_mock->shouldNotReceive('update');

    $this->user = new User($this->db_mock);

    $this->assertInstanceOf(User::class, $this->user->saveCfg());
  }


  /** @test */
  public function setCfg_method_saves_the_provided_attributes_values_in_session_config()
  {
    $this->loginWithSessionData();

    $this->user->setCfg('foo', 'bar');
    $this->assertTrue(isset($this->getConfig()['foo']));
    $this->assertSame('bar', $this->getConfig()['foo']);

    $this->user->setCfg('bar', ['baz', 'foobar']);
    $this->assertTrue(isset($this->getConfig()['bar']));
    $this->assertSame(['baz', 'foobar'], $this->getConfig()['bar']);

    $result = $this->user->setCfg('test');
    $this->assertTrue(!isset($this->getConfig()['test']));
    $this->assertInstanceOf(User::class, $result);
  }


  /** @test */
  public function unsetCfg_method_unsets_the_provided_attributes_in_session_config()
  {
    $this->loginWithSessionData();

    $this->user->setCfg('foo', 'bar');
    $this->assertTrue(isset($this->getConfig()['foo']));
    $this->assertSame('bar', $this->getConfig()['foo']);

    $result = $this->user->unsetCfg('foo');
    $this->assertTrue(!isset($this->getConfig()['foo']));
    $this->assertInstanceOf(User::class, $result);
  }


  /** @test */
  public function refreshInfo_method_Regathers_info_from_database()
  {
    $this->db_mock->shouldReceive('selectOne')->once()->andReturnNull();
    $this->db_mock->shouldReceive('insert')->once()->andReturnTrue();
    $this->db_mock->shouldReceive('lastId')->once()->andReturn($this->session_id);
    $this->db_mock->shouldReceive('rselect')->twice()->andReturn(
      $data = [
      'id_group'  => 4,
      'cfg'       => json_encode(['foo' => 'bar']),
      'id'        => $this->user_id
      ]
    );

    $this->user = new User($this->db_mock);

    $this->setNonPublicPropertyValue('error', null);
    $this->setNonPublicPropertyValue('id', $this->user_id);

    $result = $this->user->refreshInfo();

    $data['cfg'] = json_decode($data['cfg'], true);

    $this->assertInstanceOf(User::class, $result);
    $this->assertSame(['foo' => 'bar'], $this->getConfig());
    $this->assertSame($data, $this->getSessionData()[$this->user_index]);
    $this->assertSame(4, (int)$this->user->getGroup());
  }


  /** @test */
  public function isAuth_method_checks_if_user_is_authenticated()
  {
    $this->assertFalse($this->user->isAuth());

    $this->simpleLogin();

    $this->assertTrue($this->user->isAuth());
  }


  /** @test */
  public function checkSession_method_retrieves_user_info_from_session_if_authenticated()
  {
    $this->assertFalse($this->user->checkSession());

    $this->loginWithSessionData();

    $this->assertTrue($this->user->checkSession());
    $this->assertTrue($this->user->check());
  }


  /** @test */
  public function getId_method_returns_user_id_if_there_is_no_error_and_null_otherwise()
  {
    $this->assertNull($this->user->getId());

    $this->simpleLogin();

    $this->assertSame($this->user_id, $this->user->getId());
  }


  /** @test */
  public function getGroup_method_returns_the_group_id_if_there_is_no_error_and_null_otherwise()
  {
    $this->assertNull($this->user->getGroup());

    $this->loginWithSessionData();

    $this->assertSame(1, (int)$this->user->getGroup());
  }


  /** @test */
  public function expireHotlink_method_sets_hotlink_as_expired_if_there_is_no_error()
  {
    $this->db_mock->shouldReceive('selectOne')->once()->andReturnNull();
    $this->db_mock->shouldReceive('insert')->once()->andReturnTrue();
    $this->db_mock->shouldReceive('lastId')->once()->andReturn($this->session_id);

    $this->db_mock->shouldReceive('update')->once()->andReturn(1);

    $this->user = new User($this->db_mock);

    $this->setNonPublicPropertyValue('error', null);

    $this->assertSame(1, $this->user->expireHotlink(1));
  }


  /** @test */
  public function expireHotlink_method_does_not_set_hotline_as_expired_if_there_is_an_error()
  {
    $this->db_mock->shouldReceive('selectOne')->once()->andReturnNull();
    $this->db_mock->shouldReceive('insert')->once()->andReturnTrue();
    $this->db_mock->shouldReceive('lastId')->once()->andReturn($this->session_id);

    $this->db_mock->shouldNotReceive('update');

    $this->user = new User($this->db_mock);

    $this->assertSame(0, $this->user->expireHotlink(1));
  }


  /** @test */
  public function getIdFromMagicString_retrieves_user_id_from_hotlink_magic_string()
  {
    $this->db_mock->shouldReceive('selectOne')->once()->andReturnNull();
    $this->db_mock->shouldReceive('insert')->once()->andReturnTrue();
    $this->db_mock->shouldReceive('lastId')->once()->andReturn($this->session_id);

    $this->db_mock->shouldReceive('rselect')->once()->andReturn(
      [
      $this->getClassCgf()['arch']['hotlinks']['magic'] => hash('sha256', 'foobar'),
      'id_user' => 33
      ]
    );

    $this->user = new User($this->db_mock);

    $this->assertSame(33, (int)$this->user->getIdFromMagicString(2, 'foobar'));
  }


  /** @test */
  public function getIdFromMagicString_returns_null_if_id_is_invalid()
  {
    $this->db_mock->shouldReceive('selectOne')->once()->andReturnNull();
    $this->db_mock->shouldReceive('insert')->once()->andReturnTrue();
    $this->db_mock->shouldReceive('lastId')->once()->andReturn($this->session_id);

    $this->db_mock->shouldReceive('rselect')->once()->andReturnNull();

    $this->user = new User($this->db_mock);

    $this->assertNull($this->user->getIdFromMagicString(2, 'foobar'));
  }


  /** @test */
  public function getIdFromMagicString_returns_null_if_the_ket_does_not_match()
  {
    $this->db_mock->shouldReceive('selectOne')->once()->andReturnNull();
    $this->db_mock->shouldReceive('insert')->once()->andReturnTrue();
    $this->db_mock->shouldReceive('lastId')->once()->andReturn($this->session_id);

    $this->db_mock->shouldReceive('rselect')->once()->andReturn(
      [
      $this->getClassCgf()['arch']['hotlinks']['magic'] => hash('sha256', 'foobar'),
      'id_user' => 33
      ]
    );

    $this->user = new User($this->db_mock);

    $this->assertNull($this->user->getIdFromMagicString(3, 'test'));
  }


  /** @test */
  public function isAdmin_method_returns_true_if_the_user_is_admin()
  {
    $expected_session_data          = $this->getExpectedSession();
    $expected_session_data['admin'] = 1;

    $this->loginWithSessionData($expected_session_data);

    $this->assertTrue($this->user->isAdmin());
  }


  /** @test */
  public function isAdmin_method_returns_false_if_the_user_is_not_admin()
  {
    $expected_session_data          = $this->getExpectedSession();
    $expected_session_data['admin'] = 0;

    $this->loginWithSessionData($expected_session_data);

    $this->assertFalse($this->user->isAdmin());
  }


  /** @test */
  public function isDev_method_returns_true_if_user_is_admin_but_not_a_developer()
  {
    $expected_session_data          = $this->getExpectedSession();
    $expected_session_data['admin'] = 1;
    $expected_session_data['dev']   = 01;

    $this->loginWithSessionData($expected_session_data);

    $this->assertTrue($this->user->isDev());
  }


  /** @test */
  public function isDev_method_returns_true_if_user_is_a_developer_but_not_admin()
  {
    $expected_session_data          = $this->getExpectedSession();
    $expected_session_data['admin'] = 0;
    $expected_session_data['dev']   = 1;

    $this->loginWithSessionData($expected_session_data);

    $this->assertTrue($this->user->isDev());
  }


  /** @test */
  public function isDev_method_returns_false_if_user_is_not_a_developer_nor_an_admin()
  {
    $expected_session_data          = $this->getExpectedSession();
    $expected_session_data['admin'] = 0;
    $expected_session_data['dev']   = 0;

    $this->loginWithSessionData($expected_session_data);

    $this->assertFalse($this->user->isDev());
  }


  /** @test */
  public function getManager_method_returns_a_manager_instance()
  {
    $this->assertInstanceOf(User\Manager::class, $this->user->getManager());
  }


  /** @test */
  public function check_method_checks_if_an_error_has_been_thrown_or_not()
  {
    $this->assertFalse($this->user->check());

    $this->simpleLogin();

    $this->assertTrue($this->user->check());
  }


  /** @test */
  public function logout_method_un_authenticate_reset_the_config_and_destroys_the_session()
  {
    $session_data = $this->getExpectedSession();

    $this->login(
      function ($db_mock) use ($session_data) {
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

        // This should be called by the method _user_info that occurs in updateInfo method
        $db_mock->shouldReceive('rselect')->once()->andReturn($session_data);
      }
    );

    $this->assertTrue($this->user->isAuth());

    $cfg = $this->getSessionData();
    $this->assertTrue(isset($cfg[$this->user_index]));
    $this->assertNotNull($this->getSessionConfig());

    $cfg = $cfg[$this->user_index];
    $this->assertTrue(isset($cfg['email']));

    $this->user->logout();

    $cfg = $this->getSessionData();
    $this->assertFalse($this->user->isAuth());
    $this->assertTrue(empty($cfg[$this->user_index]));
    $this->assertNull($this->user->getId());

    $this->assertNull($this->getSessionConfig());
  }


  /** @test */
  public function getMailer_method_returns_an_instance_of_the_mailer_class()
  {
    if (!defined('BBN_IS_DEV')) {
      define('BBN_IS_DEV', true);
    }

    $mailer_class = $this->getClassCgf()['mailer'];

    $this->assertInstanceOf($mailer_class, $this->user->getMailer());
  }


  /** @test */
  public function getMailer_method_throws_exception_if_the_mailer_class_does_not_exist()
  {
    $this->expectException(\Exception::class);

    $class_cfg           = $this->getClassCgf();
    $class_cfg['mailer'] = 'dummy_class';

    $this->setNonPublicPropertyValue('class_cfg', $class_cfg);

    $this->user->getMailer();
  }


  /** @test */
  public function getMailer_method_returns_the_current_mailer_if_it_exists()
  {
    $mailer             = $this->getClassCgf()['mailer'];
    $this->user->mailer = new $mailer();

    $this->assertInstanceOf($mailer, $this->user->getMailer());
  }


  /** @test */
  public function setPassword_method_returns_false_if_old_password_does_not_match_or_not_logged_in()
  {
    $this->assertFalse($this->user->setPassword('foo', 'bar'));

    $hash_method = $this->getNonPublicMethod('_hash');

    $this->db_mock->shouldReceive('selectOne')->twice()->andReturn(
      null,
      $hash_method->invoke($this->user, 'wrong_password')
    );

    $this->db_mock->shouldReceive('insert')->once()->andReturnTrue();
    $this->db_mock->shouldReceive('lastId')->once()->andReturn($this->session_id);

    $this->user = new User($this->db_mock);

    $this->setNonPublicPropertyValue('auth', true);
    $this->setNonPublicPropertyValue('id', $this->user_id);

    $this->assertFalse($this->user->setPassword('old_password', 'new_password'));
  }


  /** @test */
  public function setPassword_method_changes_the_password_after_verification_and_returns_true()
  {
    $hash_method = $this->getNonPublicMethod('_hash');

    $this->db_mock->shouldReceive('selectOne')->twice()->andReturn(
      null,
      $hash_method->invoke($this->user, 'old_password')
    );

    $this->db_mock->shouldReceive('insert')->twice()->andReturnTrue();
    $this->db_mock->shouldReceive('lastId')->once()->andReturn($this->session_id);

    $this->user = new User($this->db_mock);

    $this->setNonPublicPropertyValue('auth', true);
    $this->setNonPublicPropertyValue('id', $this->user_id);

    $this->assertTrue($this->user->setPassword('old_password', 'new_password'));
  }


  /** @test */
  public function forcePassword_method_changes_the_password_in_database_and_returns_true()
  {
    $this->db_mock->shouldReceive('selectOne')->once()->andReturnNull();
    $this->db_mock->shouldReceive('insert')->twice()->andReturnTrue();
    $this->db_mock->shouldReceive('lastId')->once()->andReturn($this->session_id);

    $this->user = new User($this->db_mock);
    $this->setNonPublicPropertyValue('id', $this->user_id);

    $this->assertTrue($this->user->forcePassword('new_password'));
  }


  /** @test */
  public function forcePassword_method_returns_false_if_not_authenticated()
  {
    $this->assertFalse($this->user->forcePassword('new_password'));
  }


  /** @test */
  public function getName_method_returns_the_full_name_of_the_current_user()
  {
    $data      = $this->loginWithSessionData();
    $class_cfg = $this->getClassCgf();

    $this->assertTrue(isset($class_cfg['show']));
    $this->assertTrue(isset($data[$class_cfg['show']]));
    $this->assertSame($data[$class_cfg['show']], $this->user->getName());
  }


  /** @test */
  public function getName_method_returns_the_full_name_of_the_given_user_if_exists()
  {
    $manager_mock = \Mockery::mock(User\Manager::class);
    $manager_mock->shouldReceive('getUser')->andReturn(['name' => 'foo']);

    $this->user = \Mockery::mock(User::class)->makePartial();

    $this->setNonPublicPropertyValue('auth', true);
    $this->setNonPublicPropertyValue('class_cfg', ['show' => 'name']);

    $this->user->shouldReceive('getManager')->andReturn($manager_mock);

    $this->assertSame('foo', $this->user->getName($this->user_id));
  }


  /** @test */
  public function getName_method_returns_null_when_not_authenticated()
  {
    $this->assertNull($this->user->getName());
    $this->assertNull($this->user->getName('foo'));
  }


  /** @test */
  public function getName_method_returns_null_when_show_key_does_not_exist_in_class_cfg_property()
  {
    $this->loginWithSessionData();

    $class_cfg = $this->getClassCgf();

    unset($class_cfg['show']);

    $this->setNonPublicPropertyValue('class_cfg', $class_cfg);

    $this->assertNull($this->user->getName());
  }


  /** @test */
  public function addToken_method_generates_and_adds_a_token_in_database()
  {
    $this->assertNull($this->user->addToken());

    $this->db_mock->shouldReceive('insert')->twice()->andReturnTrue();
    $this->db_mock->shouldReceive('lastId')->once()->andReturn($this->session_id);
    $this->db_mock->shouldReceive('selectOne')->once()->andReturnNull();

    $this->user = new User($this->db_mock);

    $this->setNonPublicPropertyValue('auth', true);

    $this->assertNotNull($result = $this->user->addToken());
    $this->assertIsString($result);
  }


  /** @test */
  public function getEmail_method_returns_the_email_of_the_current_user()
  {
    $data   = $this->loginWithSessionData();
    $fields = $this->getfields();

    $this->assertTrue(isset($fields['email']));
    $this->assertTrue(isset($data[$fields['email']]));
    $this->assertSame($data[$fields['email']], $this->user->getEmail());
  }


  /** @test */
  public function getEmail_method_returns_the_email_of_the_given_user()
  {
    $manager_mock = \Mockery::mock(User\Manager::class);
    $manager_mock->shouldReceive('getUser')->andReturn(['email' => 'foo@mail.com']);

    $this->user = \Mockery::mock(User::class)->makePartial();

    $this->setNonPublicPropertyValue('auth', true);
    $this->setNonPublicPropertyValue('fields', ['email' => 'email']);

    $this->user->shouldReceive('getManager')->andReturn($manager_mock);

    $this->assertSame('foo@mail.com', $this->user->getEmail($this->user_id));
  }


  /** @test */
  public function getEmail_method_returns_null_if_not_authenticated()
  {
    $this->assertNull($this->user->getEmail());
    $this->assertNull($this->user->getEmail($this->user_id));
  }


  /** @test */
  public function getEmail_method_returns_null_if_email_key_does_not_exist_in_field_property()
  {
    $this->loginWithSessionData();
    $fields = $this->getfields();
    unset($fields['email']);
    $this->setNonPublicPropertyValue('fields', $fields);

    $this->assertNull($this->user->getEmail());
  }


  /** @test */
  public function crypt_method_encrypts_the_given_string_when_encryption_key_is_not_defined()
  {
    $this->db_mock->shouldReceive('insert')->once()->andReturnTrue();
    $this->db_mock->shouldReceive('lastId')->once()->andReturn($this->session_id);
    $this->db_mock->shouldReceive('selectOne')->twice()->andReturn(null, 'encryption_key');

    $this->user = new User($this->db_mock);

    $this->setNonPublicPropertyValue('auth', true);

    $this->assertSame(\bbn\Util\Enc::crypt('foo', 'encryption_key'), $this->user->crypt('foo'));
  }


  /** @test */
  public function crypt_method_encrypts_the_given_string_when_encryption_key_is_defined()
  {
    $this->setNonPublicPropertyValue('auth', true);
    $this->setNonPublicPropertyValue('_encryption_key', 'encryption_key');

    $this->assertSame(\bbn\Util\Enc::crypt('foo', 'encryption_key'), $this->user->crypt('foo'));
  }


  /** @test */
  public function crypt_method_returns_null_when_not_authenticated_and_encryption_key_is_no_defined()
  {
    $this->assertNull($this->user->crypt('foo'));
  }


  /** @test */
  public function decrypt_method_decrypts_the_given_string_when_encryption_key_is_not_defined()
  {
    $this->db_mock->shouldReceive('insert')->once()->andReturnTrue();
    $this->db_mock->shouldReceive('lastId')->once()->andReturn($this->session_id);
    $this->db_mock->shouldReceive('selectOne')->twice()->andReturn(null, 'encryption_key');

    $this->user = new User($this->db_mock);

    $this->setNonPublicPropertyValue('auth', true);

    $encrypted_string = \bbn\Util\Enc::crypt('foo', 'encryption_key');

    $this->assertSame(
      \bbn\Util\Enc::decrypt($encrypted_string, 'encryption_key'),
      $this->user->decrypt($encrypted_string)
    );
  }


  /** @test */
  public function decrypt_method_decrypts_the_given_string_when_encryption_key_is_defined()
  {
    $this->setNonPublicPropertyValue('_encryption_key', 'encryption_key');

    $encrypted_string = \bbn\Util\Enc::crypt('foo', 'encryption_key');

    $this->assertSame(
      \bbn\Util\Enc::decrypt($encrypted_string, 'encryption_key'),
      $this->user->decrypt($encrypted_string)
    );
  }


  /** @test */
  public function decrypt_method_returns_null_when_not_authenticated_and_encryption_key_is_not_defined()
  {
    $this->assertNull($this->user->decrypt('foo'));
  }


  /** @test */
  public function getUser_method_returns_the_current_instance()
  {
    $this->assertInstanceOf(User::class, $this->user->getUser());
  }


  /** @test */
  public function makeFingerprint_method_generates_a_random_string_between_16_and_32_characters()
  {
    for ($i = 0; $i < 3; $i++) {
      $result = $this->user->makeFingerprint();

      $this->assertIsString($result);
      $this->assertTrue(strlen($result) >= 16 && strlen($result) <= 32);
    }
  }


  /** @test */
  public function makeMagicString_method_returns_an_array_with_a_key_and_a_magic_string()
  {
    $this->assertIsArray($result = $this->user->makeMagicString());
    $this->assertTrue(isset($result['key']));
    $this->assertTrue(isset($result['hash']));
    $this->assertIsString($result['key']);
    $this->assertIsString($result['hash']);
    $this->assertTrue(strlen($result['key']) >= 16 && strlen($result['key']) <= 32);
  }


  /** @test */
  public function isMagicString_method_checks_if_the_given_string_to_the_given_hash()
  {
    $this->assertFalse(
      $this->user->isMagicString('foo', 'foo')
    );

    $this->assertTrue(
      $this->user->isMagicString('foo', hash('sha256', 'foo'))
    );
  }


  /** @test */
  public function setError_method_sets_the_error_property_if_it_is_not_set_already()
  {
    $this->setNonPublicPropertyValue('error', null);

    $set_error_method = $this->getNonPublicMethod('setError');

    $result = $set_error_method->invoke($this->user, 1);
    $this->assertSame(1, $this->getNonPublicProperty('error'));

    $set_error_method->invoke($this->user, 12);
    $this->assertSame(1, $this->getNonPublicProperty('error'));

    $this->assertInstanceOf(User::class, $result);
  }


  /** @test */
  public function getError_method_returns_the_error_if_there_is_one()
  {
    $this->setNonPublicPropertyValue('error', 6);

    $class_cfg = $this->getClassCgf();

    $this->assertNotNull($this->user->getError());
    $this->assertTrue(isset($class_cfg['errors']));
    $this->assertTrue(is_array($class_cfg['errors']));
    $this->assertTrue(!empty($class_cfg['errors']));

    $result = $this->user->getError();

    $this->assertIsArray($result);
    $this->assertTrue(isset($result['code']));
    $this->assertTrue(isset($result['text']));
    $this->assertIsInt($result['code']);
    $this->assertIsString($result['text']);
    $this->assertSame(6, $result['code']);
    $this->assertSame($class_cfg['errors'][6], $result['text']);
  }


  /** @test */
  public function getError_method_returns_null_if_there_is_no_error()
  {
    $this->setNonPublicPropertyValue('error', null);

    $this->assertNull($this->user->getError());
  }


  /** @test */
  public function logIn_method_login_a_user_from_the_provided_id()
  {
    $this->db_mock->shouldReceive('update')->once()->andReturn(1);
    $this->db_mock->shouldReceive('rselect')->once()->andReturn($this->getExpectedSession());

    $this->user = new User($this->db_mock);

    $this->assertNotNull($this->user->getError());
    $this->assertNull($this->user->getId());
    $this->assertFalse($this->user->isAuth());

    $login_method = $this->getNonPublicMethod('logIn');
    $result       = $login_method->invoke($this->user, $this->user_id);

    $this->assertNull($this->user->getError());
    $this->assertSame($this->user_id ,$this->user->getId());
    $this->assertTrue($this->user->isAuth());

    $this->assertInstanceOf(User::class, $result);
  }


  /** @test */
  public function getPrint_method_returns_a_hash_from_user_agent_and_fingerprint()
  {
    $this->setNonPublicPropertyValue('user_agent', 'Safari macosX');
    $this->setNonPublicPropertyValue('accept_lang', 'en');

    $user_agent  = $this->getNonPublicProperty('user_agent');
    $accept_lang = $this->getNonPublicProperty('accept_lang');
    $fingerprint = $this->getSessionData()[$this->session_index]['fingerprint'];

    $get_print_method = $this->getNonPublicMethod('getPrint');

    $this->assertSame(
      sha1($user_agent . $accept_lang . $fingerprint),
      $get_print_method->invoke($this->user)
    );

    $this->assertSame(
      sha1($user_agent . $accept_lang . '2134aaa34'),
      $get_print_method->invoke($this->user, '2134aaa34')
    );
  }


  /** @test */
  public function getPrint_method_returns_null_if_fingerprint_is_not_defined()
  {
    $session_data = $this->getNonPublicProperty('data', $this->getSession());

    $this->assertTrue(isset($session_data[$this->session_index]['fingerprint']));

    unset($session_data[$this->session_index]['fingerprint']);

    $this->setNonPublicPropertyValue('data', $session_data, $this->getSession());

    $get_print_method = $this->getNonPublicMethod('getPrint');

    $this->assertNull($get_print_method->invoke($this->user));
  }


  /** @test */
  public function getIdSession_method_returns_the_database_id_for_the_session_row_if_exists()
  {
    $get_id_session_method = $this->getNonPublicMethod('getIdSession');

    $session_data = $this->getNonPublicProperty('data', $this->getSession());

    $this->assertTrue(isset($session_data[$this->session_index]['id_session']));

    $this->assertSame(
      $session_data[$this->session_index]['id_session'],
      $get_id_session_method->invoke($this->user)
    );
  }


  /** @test */
  public function getIdSession_method_returns_null_if_session_row_does_not_exists()
  {
    $get_id_session_method = $this->getNonPublicMethod('getIdSession');

    $session_data = $this->getNonPublicProperty('data', $this->getSession());

    $this->assertTrue(isset($session_data[$this->session_index]['id_session']));

    unset($session_data[$this->session_index]['id_session']);

    $this->setNonPublicPropertyValue('data', $session_data,$this->getSession());

    $this->assertNull(
      $get_id_session_method->invoke($this->user)
    );
  }

  /** @test */
  public function recordAttempt_method_increments_the_num_attempt_variable()
  {
    $record_attempt_method = $this->getNonPublicMethod('recordAttempt');

    $this->setNonPublicPropertyValue('cfg', null);

    $record_attempt_method->invoke($this->user);

    $this->assertTrue(isset($this->getConfig()['num_attempts']));
    $this->assertSame(1, $this->getConfig()['num_attempts']);

    $this->setNonPublicPropertyValue('cfg', ['num_attempts' => 4]);

    $result = $record_attempt_method->invoke($this->user);

    $this->assertTrue(isset($this->getConfig()['num_attempts']));
    $this->assertSame(5, $this->getConfig()['num_attempts']);
    $this->assertInstanceOf(User::class, $result);
  }

  /** @test */
  public function login_method_initialize_and_saves_the_session_after_authentication()
  {
    $this->db_mock->shouldReceive('update')->once()->andReturn(1);
    $this->db_mock->shouldReceive('rselect')->once()->andReturn($this->getExpectedSession());

    $this->user = new User($this->db_mock);

    $this->assertFalse($this->user->isAuth());
    $this->assertNull($this->user->getId());
    $this->assertNotNull($this->user->getError());

    $this->setNonPublicPropertyValue('error', null);

    $_login_method = $this->getNonPublicMethod('_login');
    $result        = $_login_method->invoke($this->user, $this->user_id);

    $this->assertNull($this->user->getError());
    $this->assertSame($this->user_id ,$this->user->getId());
    $this->assertTrue($this->user->isAuth());

    $this->assertInstanceOf(User::class, $result);
  }

  /** @test */
  public function _login_method_does_not_authenticate_if_there_is_an_error()
  {
    $this->db_mock->shouldReceive('selectOne')->once()->andReturn(null);
    $this->db_mock->shouldReceive('insert')->once()->andReturn(1);
    $this->db_mock->shouldReceive('lastId')->once()->andReturn($this->session_id);

    $this->user = new User($this->db_mock);

    $this->assertFalse($this->user->isAuth());
    $this->assertNull($this->user->getId());
    $this->assertNotNull($this->user->getError());

    $this->setNonPublicPropertyValue('error', 3);

    $_login_method = $this->getNonPublicMethod('_login');
    $result        = $_login_method->invoke($this->user, $this->user_id);

    $this->assertFalse($this->user->isAuth());
    $this->assertNull($this->user->getId());
    $this->assertNotNull($this->user->getError());

    $this->assertInstanceOf(User::class, $result);
  }
}
