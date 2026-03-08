<?php

namespace bbn\tests\User;

use bbn\Db;
use bbn\Str;
use bbn\Mvc;
use bbn\User;
use bbn\User\Session;
use PHPUnit\Framework\TestCase;
use bbn\tests\Reflectable;

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

  protected $api_post = [
    'device_uid'   => '634a2c70bcac11eba47652540000cfaa',
    'appui_token'  => '634a2c70aaaaa2aaa47652540000cfaa'
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
    return $this->user->getClassCfg();
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
   *
   * @param callable|null $callback Callback to apply additional mockery expectations.
   * @return mixed|null
   */
  protected function sessionLogin(?callable $callback = null)
  {
    if ($callback) {
      $result = $callback($this->db_mock);
    }

    $this->login_post['appui_salt'] = $this->user->getSalt();

    $this->user = new User($this->db_mock, $this->login_post);

    return $result ?? null;
  }

  /**
   * @param array $api_post
   * @param callable|null $callback Callback to apply additional mockery expectations.
   * @throws \Exception
   */
  protected function tokenRequest(array $api_post = [], ?callable $callback = null)
  {
    if ($callback) {
      $callback($this->db_mock);
    }

    $this->api_post = array_replace($this->api_post, $api_post);

    $this->user = new User($this->db_mock, $this->api_post, ['tables' => ['api_tokens' => 'bbn_api_tokens']]);
  }

  protected function verifyTokenAndDeviceUidMethodExpectation($db_mock, $class_cfg, $return_value)
  {
    $db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        'bbn_api_tokens',
        $class_cfg['arch']['api_tokens'],
        [
          $class_cfg['arch']['api_tokens']['token']      => $this->api_post['appui_token'],
          $class_cfg['arch']['api_tokens']['device_uid'] => $this->api_post['device_uid'],
        ]
      )
      ->andReturn($return_value);
  }


  protected function simpleLogin()
  {
    $this->sessionLogin(
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

    $this->sessionLogin(
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


  /**
   * @param array|null $cfg
   * @return array
   */
  protected function getExpectedSession(?array $cfg = null): array
  {
    $cfg = $cfg ?? $this->initSessionFingerPrint();

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
      'cfg'       => json_encode($cfg),
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

  protected function replaceDbWithMockedVersion(): void
  {
    $this->setNonPublicPropertyValue('db', $this->db_mock);
  }


  public function getInstance()
  {
    return $this->user;
  }


  /** @test */
  public function testUserCanLogin()
  {
    $this->simpleLogin();

    $this->assertNull($this->user->getFullError());
    $this->assertTrue($this->user->isAuth());
  }


  /** @test */
  public function testIsresetMethodReturnsFalseIfTheRequestIsNotAPasswordReset()
  {
    $this->simpleLogin();

    $this->assertFalse($this->user->isReset());
  }


  /** @test */
  public function testUserCanResetPasswordWhenNewPasswordAndMagicStringAreValid()
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
    $this->assertNull($this->user->getFullError());
    $this->assertFalse($this->user->isAuth());
  }


  /** @test */
  public function testUserCannotResetPasswordWhenNewPasswordAndConfirmationDoesNotMatch()
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

    $this->assertNotNull($this->user->getFullError());
    $this->assertSame(7, $this->user->getFullError()['code']);
    $this->assertTrue($this->user->isReset());
    $this->assertFalse($this->user->isAuth());
    $this->assertNull($this->user->getId());
  }


  /** @test */
  public function testUserCannotResetPasswordWhenMagicStringIsNotValid()
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

    $this->assertNotNull($this->user->getFullError());
    $this->assertSame(18, $this->user->getFullError()['code']);
    $this->assertFalse($this->user->isReset());
    $this->assertFalse($this->user->isAuth());
    $this->assertNull($this->user->getId());
  }

  /** @test */
  public function testApiUserCanRequestSendingVerificationCodeToHisPhoneNumber()
  {
    $this->tokenRequest([
      'phone_number' => $phone_number = '12345',
      'appui_action' => 'send_phone_number_verification_code'
    ], function ($db_mock) use ($phone_number) {
      $class_cfg = $this->getClassCgf();

      // verifyTokenAndDeviceUid() method
      $this->verifyTokenAndDeviceUidMethodExpectation($db_mock, $class_cfg, [
        $class_cfg['arch']['api_tokens']['token']      => $this->api_post['appui_token'],
        $class_cfg['arch']['api_tokens']['device_uid'] => $this->api_post['device_uid'],
      ]);

      // findByPhoneNumber() method
      $db_mock->shouldReceive('rselect')
        ->once()
        ->with(
          $class_cfg['tables']['users'],
          $class_cfg['arch']['users'],
          [
            $class_cfg['arch']['users']['login'] => $phone_number,
          ]
        )
        ->andReturn([
          $class_cfg['arch']['users']['id'] => $this->user_id,
        ]);

      // updatePhoneVerificationCode() method
      $db_mock->shouldReceive('query')->once()->andReturn(1);
    });

    $this->assertTrue($this->user->getApiRequestOutput());
  }
  
  /** @test */
  public function testSendingVerificationCodeRequestWillThrowAnExceptionWhenTheReceivedTokenDoesNotMatchDeviceUid()
  {
    $this->expectException(\Exception::class);

    $this->tokenRequest([
      'phone_number' => '12345',
      'appui_action' => 'send_phone_number_verification_code'
    ], function ($db_mock) {
      $class_cfg = $this->getClassCgf();

      // verifyTokenAndDeviceUid() method
      $this->verifyTokenAndDeviceUidMethodExpectation($db_mock, $class_cfg, null);
    });
  }

  /** @test */
  public function testSendingVerificationCodeRequestWillThrowAnExceptionWhenPhoneNumberDoesNotExistInDb()
  {
    $this->expectException(\Exception::class);

    $this->tokenRequest([
      'phone_number' => $phone_number = '12345',
      'appui_action' => 'send_phone_number_verification_code'
    ], function ($db_mock) use ($phone_number) {
      $class_cfg = $this->getClassCgf();

      // verifyTokenAndDeviceUid() method
      $this->verifyTokenAndDeviceUidMethodExpectation($db_mock, $class_cfg, [
        $class_cfg['arch']['api_tokens']['token']      => $this->api_post['appui_token'],
        $class_cfg['arch']['api_tokens']['device_uid'] => $this->api_post['device_uid'],
      ]);

      // findByPhoneNumber() method
      $db_mock->shouldReceive('rselect')
        ->once()
        ->with(
          $class_cfg['tables']['users'],
          $class_cfg['arch']['users'],
          [
            $class_cfg['arch']['users']['login'] => $phone_number,
          ]
        )
        ->andReturnNull();
    });
  }
  
  /** @test */
  public function testApiUserCanRequestToVerifyPhoneNumberByVerificationCodeAndGetANewTokenAsAResult()
  {
    $this->tokenRequest([
      'phone_number'            => $phone_number = '12345',
      'appui_action'            => 'verify_phone_number',
      'phone_verification_code' => 'abcde'

    ], function ($db_mock) use ($phone_number) {
      $class_cfg = $this->getClassCgf();

      // verifyTokenAndDeviceUid() method
      $this->verifyTokenAndDeviceUidMethodExpectation($db_mock, $class_cfg, [
        $class_cfg['arch']['api_tokens']['token']      => $this->api_post['appui_token'],
        $class_cfg['arch']['api_tokens']['device_uid'] => $this->api_post['device_uid'],
      ]);

      // findByPhoneNumber() method
      $db_mock->shouldReceive('rselect')
        ->once()
        ->with(
          $class_cfg['tables']['users'],
          $class_cfg['arch']['users'],
          [
            $class_cfg['arch']['users']['login'] => $phone_number,
          ]
        )
        ->andReturn([
          $class_cfg['arch']['users']['id'] => $this->user_id,
          $class_cfg['arch']['users']['cfg'] => json_encode(['phone_verification_code' => 'abcde']),
        ]);

      // updatePhoneVerificationCode() method
      $db_mock->shouldReceive('query')->once()->andReturn(1);

      // Update user id and the new token in the row with the old token and device uid.
      $db_mock->shouldReceive('update')
        ->once()
        ->withAnyArgs(
          'bbn_api_tokens',
          [
            $class_cfg['arch']['api_tokens']['id_user'] => $this->user_id,
            $class_cfg['arch']['api_tokens']['token'] => $this->api_post['appui_token'],
          ]
        );
    });

    $result = json_decode($this->user->getApiRequestOutput(), true);

    $this->assertIsArray($result);
    $this->assertTrue(isset($result['token']));
    $this->assertNotSame($this->api_post['appui_token'], $result['token']);
  }

  /** @test */
  public function testVerifyingPhoneNumberWillThrowAnExceptionWhenTheReceivedTokenDoesNotMatchDeviceUid()
  {
    $this->expectException(\Exception::class);

    $this->tokenRequest([
      'phone_number'            => '12345',
      'appui_action'            => 'verify_phone_number',
      'phone_verification_code' => 'abcde'

    ], function ($db_mock) {
      $class_cfg = $this->getClassCgf();

      // verifyTokenAndDeviceUid() method
      $this->verifyTokenAndDeviceUidMethodExpectation($db_mock, $class_cfg, null);
    });
  }

  /** @test */
  public function testVerifyingPhoneNumberWillThrowAnExceptionWhenPhoneNumberDoesNotExistInDb()
  {
    $this->expectException(\Exception::class);

    $this->tokenRequest([
      'phone_number'            => $phone_number = '12345',
      'appui_action'            => 'verify_phone_number',
      'phone_verification_code' => 'abcde'

    ], function ($db_mock) use ($phone_number) {
      $class_cfg = $this->getClassCgf();

      // verifyTokenAndDeviceUid() method
      $this->verifyTokenAndDeviceUidMethodExpectation($db_mock, $class_cfg, [
        $class_cfg['arch']['api_tokens']['token']      => $this->api_post['appui_token'],
        $class_cfg['arch']['api_tokens']['device_uid'] => $this->api_post['device_uid'],
      ]);

      // findByPhoneNumber() method
      $db_mock->shouldReceive('rselect')
        ->once()
        ->with(
          $class_cfg['tables']['users'],
          $class_cfg['arch']['users'],
          [
            $class_cfg['arch']['users']['login'] => $phone_number,
          ]
        )
        ->andReturnNull();
    });
  }

  /** @test */
  public function testVerifyingPhoneNumberWillThrowAnExceptionWhenTheReturnedCfgIsNotValidJson()
  {
    $this->expectException(\Exception::class);

    $this->tokenRequest([
      'phone_number'            => $phone_number = '12345',
      'appui_action'            => 'verify_phone_number',
      'phone_verification_code' => 'abcde'

    ], function ($db_mock) use ($phone_number) {
      $class_cfg = $this->getClassCgf();

      // verifyTokenAndDeviceUid() method
      $this->verifyTokenAndDeviceUidMethodExpectation($db_mock, $class_cfg, [
        $class_cfg['arch']['api_tokens']['token']      => $this->api_post['appui_token'],
        $class_cfg['arch']['api_tokens']['device_uid'] => $this->api_post['device_uid'],
      ]);

      // findByPhoneNumber() method
      $db_mock->shouldReceive('rselect')
        ->once()
        ->with(
          $class_cfg['tables']['users'],
          $class_cfg['arch']['users'],
          [
            $class_cfg['arch']['users']['login'] => $phone_number,
          ]
        )
        ->andReturn([
          $class_cfg['arch']['users']['id'] => $this->user_id,
          $class_cfg['arch']['users']['cfg'] => 'foo',
        ]);

    });
  }

  /** @test */
  public function testVerifyingPhoneNumberWillThrowAnExceptionWhenTheReturnedCfgJsonHasNoPhoneVerificationCode()
  {
    $this->expectException(\Exception::class);

    $this->tokenRequest([
      'phone_number'            => $phone_number = '12345',
      'appui_action'            => 'verify_phone_number',
      'phone_verification_code' => 'abcde'

    ], function ($db_mock) use ($phone_number) {
      $class_cfg = $this->getClassCgf();

      // verifyTokenAndDeviceUid() method
      $this->verifyTokenAndDeviceUidMethodExpectation($db_mock, $class_cfg, [
        $class_cfg['arch']['api_tokens']['token']      => $this->api_post['appui_token'],
        $class_cfg['arch']['api_tokens']['device_uid'] => $this->api_post['device_uid'],
      ]);

      // findByPhoneNumber() method
      $db_mock->shouldReceive('rselect')
        ->once()
        ->with(
          $class_cfg['tables']['users'],
          $class_cfg['arch']['users'],
          [
            $class_cfg['arch']['users']['login'] => $phone_number,
          ]
        )
        ->andReturn([
          $class_cfg['arch']['users']['id'] => $this->user_id,
          $class_cfg['arch']['users']['cfg'] => json_encode(['foo' => 'bar']),
        ]);

    });
  }

  /** @test */
  public function testVerifyingPhoneNumberWillThrowAnExceptionWhenPhoneVerificationCodeDoesNotMatch()
  {
    $this->expectException(\Exception::class);

    $this->tokenRequest([
      'phone_number'            => $phone_number = '12345',
      'appui_action'            => 'verify_phone_number',
      'phone_verification_code' => 'abcde'

    ], function ($db_mock) use ($phone_number) {
      $class_cfg = $this->getClassCgf();

      // verifyTokenAndDeviceUid() method
      $this->verifyTokenAndDeviceUidMethodExpectation($db_mock, $class_cfg, [
        $class_cfg['arch']['api_tokens']['token']      => $this->api_post['appui_token'],
        $class_cfg['arch']['api_tokens']['device_uid'] => $this->api_post['device_uid'],
      ]);

      // findByPhoneNumber() method
      $db_mock->shouldReceive('rselect')
        ->once()
        ->with(
          $class_cfg['tables']['users'],
          $class_cfg['arch']['users'],
          [
            $class_cfg['arch']['users']['login'] => $phone_number,
          ]
        )
        ->andReturn([
          $class_cfg['arch']['users']['id'] => $this->user_id,
          $class_cfg['arch']['users']['cfg'] => json_encode(['phone_verification_code' => 'zzzzz']),
        ]);

    });
  }

  /** @test */
  public function testApiUserCanSendATokenLoginRequest()
  {
    $this->tokenRequest([], function ($db_mock) {
      $class_cfg = $this->getClassCgf();

      // verifyTokenAndDeviceUid() method
      $this->verifyTokenAndDeviceUidMethodExpectation($db_mock, $class_cfg, [
        $class_cfg['arch']['api_tokens']['token']      => $this->api_post['appui_token'],
        $class_cfg['arch']['api_tokens']['device_uid'] => $this->api_post['device_uid'],
        $class_cfg['arch']['api_tokens']['id_user']    => $this->user_id
      ]);

      $db_mock->shouldReceive('rselect')
        ->once()
        ->with(
          $class_cfg['tables']['users'],
          $class_cfg['arch']['users'],
          [
            $class_cfg['arch']['users']['id'] => $this->user_id
          ]
        )
        ->andReturn([
          $class_cfg['arch']['users']['id'] => $this->user_id
        ]);

    });

    $this->assertTrue($this->user->getApiRequestOutput());
    $this->assertSame($this->user_id, $this->user->getId());
  }

  /** @test */
  public function testVerifyingTokenLoginRequestWillThrowAnExceptionWhenTheReceivedTokenDoesNotMatchDeviceUid()
  {
    $this->expectException(\Exception::class);

    $this->tokenRequest([], function ($db_mock) {
      $class_cfg = $this->getClassCgf();

      // verifyTokenAndDeviceUid() method
      $this->verifyTokenAndDeviceUidMethodExpectation($db_mock, $class_cfg, null);
    });
  }

  /** @test */
  public function testVerifyingTokenLoginRequestWillThrowAnExceptionWhenTheTokenSavedIdDbHasANullIdUser()
  {
    $this->expectException(\Exception::class);

    $this->tokenRequest([], function ($db_mock) {
      $class_cfg = $this->getClassCgf();

      // verifyTokenAndDeviceUid() method
      $this->verifyTokenAndDeviceUidMethodExpectation($db_mock, $class_cfg, [
        $class_cfg['arch']['api_tokens']['token']      => $this->api_post['appui_token'],
        $class_cfg['arch']['api_tokens']['device_uid'] => $this->api_post['device_uid'],
        $class_cfg['arch']['api_tokens']['id_user']    => null
      ]);
    });
  }

  /** @test */
  public function testVerifyingTokenLoginRequestWillThrowAnExceptionWhenUserNotFoundInDb()
  {
    $this->expectException(\Exception::class);

    $this->tokenRequest([], function ($db_mock) {
      $class_cfg = $this->getClassCgf();

      // verifyTokenAndDeviceUid() method
      $this->verifyTokenAndDeviceUidMethodExpectation($db_mock, $class_cfg, [
        $class_cfg['arch']['api_tokens']['token']      => $this->api_post['appui_token'],
        $class_cfg['arch']['api_tokens']['device_uid'] => $this->api_post['device_uid'],
        $class_cfg['arch']['api_tokens']['id_user']    => $this->user_id
      ]);

      $db_mock->shouldReceive('rselect')
        ->once()
        ->with(
          $class_cfg['tables']['users'],
          $class_cfg['arch']['users'],
          [
            $class_cfg['arch']['users']['id'] => $this->user_id
          ]
        )
        ->andReturnNull();

    });
  }

  /** @test */
  public function testItShouldCheckForSessionIfItIsNotALoginNorPasswordResetRequest()
  {
    $sess_cfg = $this->initSessionFingerPrint();
    // Initiate a new User as non logged in user first
    $this->user = new User($this->db_mock);

    $this->assertFalse($this->user->isAuth());
    $this->assertNull($this->user->getId());
    $this->assertNotNull($this->user->getFullError());

    // Now let's manually login as a user
    $expected_session_data = $this->loginAs($this->user_id, $sess_cfg);

    $actual_session_data = $this->getSessionData();

    $expected_session_data['cfg'] = json_decode($expected_session_data['cfg'], true);

    $this->assertSame($expected_session_data, $actual_session_data[$this->user_index]);
    $this->assertTrue($this->user->isAuth());
    $this->assertSame($this->user_id, $this->user->getId());
    $this->assertNull($this->user->getFullError());
  }


  /** @test */
  public function testGetsaltMethodReturnsTheSaltSavedInSession()
  {
    $this->assertTrue(isset($_SESSION[BBN_APP_NAME][$this->session_index]['salt']));
    $this->assertSame($_SESSION[BBN_APP_NAME][$this->session_index]['salt'], $this->user->getSalt());
  }


  /** @test */
  public function testChecksaltMethodChecksTheActualSaltAgainstAGivenString()
  {
    $this->assertTrue(isset($_SESSION[BBN_APP_NAME][$this->session_index]['salt']));
    $this->assertTrue($this->user->checkSalt($_SESSION[BBN_APP_NAME][$this->session_index]['salt']));
  }


  /** @test */
  public function testGetcfgMethodReturnsTheUserCurrentConfiguration()
  {
    $this->assertNull($this->user->getCfg());

    $sess_cfg = $this->initSessionFingerPrint();

    $this->user = new User($this->db_mock);

    $expected_cfg = $this->loginAs(2, $sess_cfg);

    $this->assertSame(json_decode($expected_cfg['cfg'], true), $this->user->getCfg());
  }


  /** @test */
  public function testGetclasscfgMethodReturnsTheCurrentClassConfiguration()
  {
    $this->assertSame($this->getClassCgf(), $this->user->getClassCfg());
  }


  /** @test */
  public function testGetpathMethodReturnsTheDirPathForTheUser()
  {
    $this->simpleLogin();

    $this->assertSame(BBN_DATA_PATH . "users/$this->user_id/data/", $this->user->getPath());
  }


  /** @test */
  public function testGettmpdirMethodReturnsTheTmpDirPathForTheUser()
  {
    $this->simpleLogin();

    $this->assertSame(BBN_DATA_PATH . "users/$this->user_id/tmp/", $this->user->getTmpDir());
  }


  /** @test */
  public function testGettablesMethodReturnsTheListOfTablesUsedByTheCurrentClassOrNullIfNotFound()
  {
    $this->assertSame($expected_tables = $this->getClassCgf()['tables'], $this->user->getTables());

    $this->setNonPublicPropertyValue('class_cfg', []);

    $this->assertNull($this->user->getTables());

    $this->initSessionFingerPrint();
    $this->user = new User($this->db_mock,[], ['tables' => ['foo' => 'bar']]);

    $this->assertSame(array_merge($expected_tables, ['foo' => 'bar']), $this->user->getTables());
  }


  /** @test */
  public function testGetfieldsMethodReturnsListOfFieldsOfGivenTableOrAllTablesAndNullIfNotFound()
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
  public function testUpdateinfoMethodChangesDataInUsersTableIfDataIsValid()
  {
    $expected_session_data = $this->getExpectedSession();

    $this->sessionLogin(
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
  public function testUpdateinfoMethodDoesNotChangesDataInUsersTableIfDataIsInvalid()
  {
    $this->simpleLogin();

    $this->assertFalse($this->user->updateInfo(['foo' => 'bar']));
    $this->assertFalse($this->user->updateInfo(['id' => 50]));
    $this->assertFalse($this->user->updateInfo(['auth' => 'foo']));
    $this->assertFalse($this->user->updateInfo(['pass' => 'foo']));
    $this->assertFalse($this->user->updateInfo(['cfg' => json_encode(['cfg_key' => 'cgf_value'])]));
  }


  /** @test */
  public function testGetpasswordMethodEncryptsTheGivenString()
  {
    $this->assertSame(
      $this->getNonPublicMethod('_hash')->invoke($this->user, 'foo'),
      $this->user->getPassword('foo')
    );
  }


  /** @test */
  public function testIsjustloginMethodChecksIfTheUserIsLoggedIn()
  {
    $this->assertFalse($this->user->isJustLogin());

    $this->simpleLogin();

    $this->assertTrue($this->user->isJustLogin());
  }


  /** @test */
  public function testSetsessionSetsTheGivenAttributesInTheSession()
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
  public function testUnsetsessionMethodUnsetsTheGivenAttributesFromSessionIfExists()
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
  public function testGetsessionMethodReturnsSessionPropertyFromSessionUserInfo()
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
  public function testGetosessionMethodReturnsAnAttributeOrWholeSessionFromSessionSession()
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
  public function testMethodSetosessionSetsAnAttributeInTheSessindexPartOfTheSession()
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
  public function testHassessionMethodChecksIfTheGivenAttributeExistsInUserSession()
  {
    $this->loginWithSessionData();

    $this->assertTrue($this->user->hasSession('email'));
    $this->assertTrue($this->user->hasSession('username'));
    $this->assertTrue($this->user->hasSession('id'));
    $this->assertTrue($this->user->hasSession('theme'));
    $this->assertFalse($this->user->hasSession('salt'));
  }


  /** @test */
  public function testUpdateactivityUpdatesLastActivityForTheSessionInDatabaseIfLoggedIn()
  {
    $this->sessionLogin(
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
  public function testUpdateactivityDoesNotUpdateLastActivityIfNotLoggedIn()
  {
    $this->db_mock->shouldNotReceive('update');

    $result = $this->user->updateActivity();
    $this->assertInstanceOf(User::class, $result);
  }


  /** @test */
  public function testSavesessionMethodSavesTheSessionConfigInDatabaseIfTheCurrentTimeExceendsLastRenewByTwoSecondsOrMore()
  {
    $this->sessionLogin(
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
  public function testSavesessionMethodSavesTheSessionConfigInDatabaseIfTheLastRenewDoesNotExists()
  {
    $this->sessionLogin(
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
  public function testSavesessionMethodDoesNotSaveTheSessionInDatabaseIfCurrentTimeDoesNotExceedLastRenew()
  {
    $this->sessionLogin(
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
  public function testSavesessionMethodSavesTheSessionConfigInDatabaseIfTheLastRenewIsEmpty()
  {
    $this->sessionLogin(
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
  public function testSavesessionMethodIsForcedToSaveTheSessionConfigInDatabaseWithoutConsideringLastRenew()
  {
    $this->sessionLogin(
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
  public function testSavesessionMethodDoesNotSaveTheSessionInDatabaseIfNotLoggedIn()
  {
    $this->db_mock->shouldNotReceive('update');

    $this->assertFalse($this->user->check());
    $this->assertInstanceOf(User::class, $this->user->saveSession());
  }


  /** @test */
  public function testClosesessionMethodClosesTheSessionInTheDatabase()
  {
    $expected_session_data = $this->getExpectedSession();

    $this->sessionLogin(
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
  public function testClosesessionMethodClosesTheSessionInTheDatabaseAndUnsetsDataFromSession()
  {
    $expected_session_data = $this->getExpectedSession();

    $this->sessionLogin(
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
  public function testCheckattemptsMethodReturnsTrueIfNoNumberOfAttemptsIsRecorded()
  {
    // Before logging in
    $this->assertTrue($this->user->checkAttempts());

    // Successful login
    $this->loginWithSessionData();

    $this->assertTrue($this->user->checkAttempts());
  }


  /** @test */
  public function testCheckattemptsMethodReturnsFalseIfTheMaxNumberOfConnectionAttemptsIsReached()
  {
    // Let's make a failed login
    $this->sessionLogin(
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
    $this->assertNotNull($this->user->getFullError());
    $this->assertFalse($this->user->check());
    $this->assertFalse($this->user->checkAttempts());
  }


  /** @test */
  public function testCheckattemptsMethodReturnsTrueIfTheMaxNumberOfConnectionAttemptsIsNotReached()
  {
    $this->sessionLogin(
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

    $this->assertNotNull($this->user->getFullError());
    $this->assertFalse($this->user->check());
    $this->assertTrue($this->user->checkAttempts());
  }


  /** @test */
  public function testSavecfgMethodSavesUserConfigInTheCfgFieldOfUsersTable()
  {
    $this->sessionLogin(
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
  public function testSavecfgMethodDoesNothingIfNotLoggedIn()
  {
    $this->db_mock->shouldReceive('selectOne')->once()->andReturnNull();
    $this->db_mock->shouldReceive('insert')->once()->andReturnTrue();
    $this->db_mock->shouldReceive('lastId')->once()->andReturn($this->session_id);
    $this->db_mock->shouldNotReceive('update');

    $this->user = new User($this->db_mock);

    $this->assertInstanceOf(User::class, $this->user->saveCfg());
  }


  /** @test */
  public function testSetcfgMethodSavesTheProvidedAttributesValuesInSessionConfig()
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
  public function testUnsetcfgMethodUnsetsTheProvidedAttributesInSessionConfig()
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
  public function testRefreshinfoMethodRegathersInfoFromDatabase()
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
    $this->assertSame(4, (int)$this->user->getIdGroup());
  }


  /** @test */
  public function testIsauthMethodChecksIfUserIsAuthenticated()
  {
    $this->assertFalse($this->user->isAuth());

    $this->simpleLogin();

    $this->assertTrue($this->user->isAuth());
  }


  /** @test */
  public function testChecksessionMethodRetrievesUserInfoFromSessionIfAuthenticated()
  {
    $this->assertFalse($this->user->checkSession());

    $session_data = $this->getExpectedSession();

    $this->sessionLogin(
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
        $db_mock->shouldReceive('rselect')->twice()->andReturn($session_data);
      }
    );

    $this->setNonPublicPropertyValue('id', null);

    $this->assertTrue($this->user->checkSession());
    $this->assertTrue($this->user->check());
  }


  /** @test */
  public function testGetidMethodReturnsUserIdIfThereIsNoErrorAndNullOtherwise()
  {
    $this->assertNull($this->user->getId());

    $this->simpleLogin();

    $this->assertSame($this->user_id, $this->user->getId());
  }


  /** @test */
  public function testGetgroupMethodReturnsTheGroupIdIfThereIsNoErrorAndNullOtherwise()
  {
    $this->assertNull($this->user->getIdGroup());

    $this->loginWithSessionData();

    $this->assertSame(1, (int)$this->user->getIdGroup());
  }


  /** @test */
  public function testExpirehotlinkMethodSetsHotlinkAsExpiredIfThereIsNoError()
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
  public function testExpirehotlinkMethodDoesNotSetHotlineAsExpiredIfThereIsAnError()
  {
    $this->db_mock->shouldReceive('selectOne')->once()->andReturnNull();
    $this->db_mock->shouldReceive('insert')->once()->andReturnTrue();
    $this->db_mock->shouldReceive('lastId')->once()->andReturn($this->session_id);

    $this->db_mock->shouldNotReceive('update');

    $this->user = new User($this->db_mock);

    $this->assertSame(0, $this->user->expireHotlink(1));
  }


  /** @test */
  public function testGetidfrommagicstringRetrievesUserIdFromHotlinkMagicString()
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
  public function testGetidfrommagicstringReturnsNullIfIdIsInvalid()
  {
    $this->db_mock->shouldReceive('selectOne')->once()->andReturnNull();
    $this->db_mock->shouldReceive('insert')->once()->andReturnTrue();
    $this->db_mock->shouldReceive('lastId')->once()->andReturn($this->session_id);

    $this->db_mock->shouldReceive('rselect')->once()->andReturnNull();

    $this->user = new User($this->db_mock);

    $this->assertNull($this->user->getIdFromMagicString(2, 'foobar'));
  }


  /** @test */
  public function testGetidfrommagicstringReturnsNullIfTheKetDoesNotMatch()
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
  public function testIsadminMethodReturnsTrueIfTheUserIsAdmin()
  {
    $expected_session_data          = $this->getExpectedSession();
    $expected_session_data['admin'] = 1;

    $this->loginWithSessionData($expected_session_data);

    $this->assertTrue($this->user->isAdmin());
  }


  /** @test */
  public function testIsadminMethodReturnsFalseIfTheUserIsNotAdmin()
  {
    $expected_session_data          = $this->getExpectedSession();
    $expected_session_data['admin'] = 0;

    $this->loginWithSessionData($expected_session_data);

    $this->assertFalse($this->user->isAdmin());
  }


  /** @test */
  public function testIsdevMethodReturnsTrueIfUserIsAdminButNotADeveloper()
  {
    $expected_session_data          = $this->getExpectedSession();
    $expected_session_data['admin'] = 1;
    $expected_session_data['dev']   = 01;

    $this->loginWithSessionData($expected_session_data);

    $this->assertTrue($this->user->isDev());
  }


  /** @test */
  public function testIsdevMethodReturnsTrueIfUserIsADeveloperButNotAdmin()
  {
    $expected_session_data          = $this->getExpectedSession();
    $expected_session_data['admin'] = 0;
    $expected_session_data['dev']   = 1;

    $this->loginWithSessionData($expected_session_data);

    $this->assertTrue($this->user->isDev());
  }


  /** @test */
  public function testIsdevMethodReturnsFalseIfUserIsNotADeveloperNorAnAdmin()
  {
    $expected_session_data          = $this->getExpectedSession();
    $expected_session_data['admin'] = 0;
    $expected_session_data['dev']   = 0;

    $this->loginWithSessionData($expected_session_data);

    $this->assertFalse($this->user->isDev());
  }


  /** @test */
  public function testGetmanagerMethodReturnsAManagerInstance()
  {
    $this->assertInstanceOf(User\Manager::class, $this->user->getManager());
  }


  /** @test */
  public function testCheckMethodChecksIfAnErrorHasBeenThrownOrNot()
  {
    $this->assertFalse($this->user->check());

    $this->simpleLogin();

    $this->assertTrue($this->user->check());
  }


  /** @test */
  public function testLogoutMethodUnAuthenticateResetTheConfigAndDestroysTheSession()
  {
    $session_data = $this->getExpectedSession();

    $this->sessionLogin(
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
  public function testGetmailerMethodReturnsAnInstanceOfTheMailerClass()
  {
    if (!defined('BBN_IS_DEV')) {
      define('BBN_IS_DEV', true);
    }

    $mailer_class = $this->getClassCgf()['mailer'];

    $this->assertInstanceOf($mailer_class, $this->user->getMailer());
  }


  /** @test */
  public function testGetmailerMethodThrowsExceptionIfTheMailerClassDoesNotExist()
  {
    $this->expectException(\Exception::class);

    $class_cfg           = $this->getClassCgf();
    $class_cfg['mailer'] = 'dummy_class';

    $this->setNonPublicPropertyValue('class_cfg', $class_cfg);

    $this->user->getMailer();
  }


  /** @test */
  public function testGetmailerMethodReturnsTheCurrentMailerIfItExists()
  {
    $mailer             = $this->getClassCgf()['mailer'];
    $this->user->mailer = new $mailer();

    $this->assertInstanceOf($mailer, $this->user->getMailer());
  }


  /** @test */
  public function testSetpasswordMethodReturnsFalseIfOldPasswordDoesNotMatchOrNotLoggedIn()
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
  public function testSetpasswordMethodChangesThePasswordAfterVerificationAndReturnsTrue()
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
  public function testForcepasswordMethodChangesThePasswordInDatabaseAndReturnsTrue()
  {
    $this->db_mock->shouldReceive('selectOne')->once()->andReturnNull();
    $this->db_mock->shouldReceive('insert')->twice()->andReturnTrue();
    $this->db_mock->shouldReceive('lastId')->once()->andReturn($this->session_id);

    $this->user = new User($this->db_mock);
    $this->setNonPublicPropertyValue('id', $this->user_id);

    $this->assertTrue($this->user->forcePassword('new_password'));
  }


  /** @test */
  public function testForcepasswordMethodReturnsFalseIfNotAuthenticated()
  {
    $this->assertFalse($this->user->forcePassword('new_password'));
  }


  /** @test */
  public function testGetnameMethodReturnsTheFullNameOfTheCurrentUser()
  {
    $data      = $this->loginWithSessionData();
    $class_cfg = $this->getClassCgf();

    $this->assertTrue(isset($class_cfg['show']));
    $this->assertTrue(isset($data[$class_cfg['show']]));
    $this->assertSame($data[$class_cfg['show']], $this->user->getName());
  }


  /** @test */
  public function testGetnameMethodReturnsTheFullNameOfTheGivenUserIfExists()
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
  public function testGetnameMethodReturnsNullWhenNotAuthenticated()
  {
    $this->assertNull($this->user->getName());
    $this->assertNull($this->user->getName('foo'));
  }


  /** @test */
  public function testGetnameMethodReturnsNullWhenShowKeyDoesNotExistInClassCfgProperty()
  {
    $this->loginWithSessionData();

    $class_cfg = $this->getClassCgf();

    unset($class_cfg['show']);

    $this->setNonPublicPropertyValue('class_cfg', $class_cfg);

    $this->assertNull($this->user->getName());
  }


  /** @test */
  public function testAddtokenMethodGeneratesAndAddsATokenInDatabase()
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
  public function testGetemailMethodReturnsTheEmailOfTheCurrentUser()
  {
    $data   = $this->loginWithSessionData();
    $fields = $this->getfields();

    $this->assertTrue(isset($fields['email']));
    $this->assertTrue(isset($data[$fields['email']]));
    $this->assertSame($data[$fields['email']], $this->user->getEmail());
  }


  /** @test */
  public function testGetemailMethodReturnsTheEmailOfTheGivenUser()
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
  public function testGetemailMethodReturnsNullIfNotAuthenticated()
  {
    $this->assertNull($this->user->getEmail());
    $this->assertNull($this->user->getEmail($this->user_id));
  }


  /** @test */
  public function testGetemailMethodReturnsNullIfEmailKeyDoesNotExistInFieldProperty()
  {
    $this->loginWithSessionData();
    $fields = $this->getfields();
    unset($fields['email']);
    $this->setNonPublicPropertyValue('fields', $fields);

    $this->assertNull($this->user->getEmail());
  }


  /** @test */
  public function testCryptMethodEncryptsTheGivenStringWhenEncryptionKeyIsNotDefined()
  {
    $this->db_mock->shouldReceive('insert')->once()->andReturnTrue();
    $this->db_mock->shouldReceive('lastId')->once()->andReturn($this->session_id);
    $this->db_mock->shouldReceive('selectOne')->twice()->andReturn(null, 'encryption_key');

    $this->user = new User($this->db_mock);

    $this->setNonPublicPropertyValue('auth', true);

    $this->assertSame(\bbn\Util\Enc::crypt('foo', 'encryption_key'), $this->user->crypt('foo'));
  }


  /** @test */
  public function testCryptMethodEncryptsTheGivenStringWhenEncryptionKeyIsDefined()
  {
    $this->setNonPublicPropertyValue('auth', true);
    $this->setNonPublicPropertyValue('_encryption_key', 'encryption_key');

    $this->assertSame(\bbn\Util\Enc::crypt('foo', 'encryption_key'), $this->user->crypt('foo'));
  }


  /** @test */
  public function testCryptMethodReturnsNullWhenNotAuthenticatedAndEncryptionKeyIsNoDefined()
  {
    $this->assertNull($this->user->crypt('foo'));
  }


  /** @test */
  public function testDecryptMethodDecryptsTheGivenStringWhenEncryptionKeyIsNotDefined()
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
  public function testDecryptMethodDecryptsTheGivenStringWhenEncryptionKeyIsDefined()
  {
    $this->setNonPublicPropertyValue('_encryption_key', 'encryption_key');

    $encrypted_string = \bbn\Util\Enc::crypt('foo', 'encryption_key');

    $this->assertSame(
      \bbn\Util\Enc::decrypt($encrypted_string, 'encryption_key'),
      $this->user->decrypt($encrypted_string)
    );
  }


  /** @test */
  public function testDecryptMethodReturnsNullWhenNotAuthenticatedAndEncryptionKeyIsNotDefined()
  {
    $this->assertNull($this->user->decrypt('foo'));
  }


  /** @test */
  public function testGetuserMethodReturnsTheCurrentInstance()
  {
    $this->assertInstanceOf(User::class, $this->user->getUser());
  }


  /** @test */
  public function testMakefingerprintMethodGeneratesARandomStringBetween16And32Characters()
  {
    for ($i = 0; $i < 3; $i++) {
      $result = $this->user->makeFingerprint();

      $this->assertIsString($result);
      $this->assertTrue(Str::len($result) >= 16 && Str::len($result) <= 32);
    }
  }


  /** @test */
  public function testMakemagicstringMethodReturnsAnArrayWithAKeyAndAMagicString()
  {
    $this->assertIsArray($result = $this->user->makeMagicString());
    $this->assertTrue(isset($result['key']));
    $this->assertTrue(isset($result['hash']));
    $this->assertIsString($result['key']);
    $this->assertIsString($result['hash']);
    $this->assertTrue(Str::len($result['key']) >= 16 && Str::len($result['key']) <= 32);
  }


  /** @test */
  public function testIsmagicstringMethodChecksIfTheGivenStringToTheGivenHash()
  {
    $this->assertFalse(
      $this->user->isMagicString('foo', 'foo')
    );

    $this->assertTrue(
      $this->user->isMagicString('foo', hash('sha256', 'foo'))
    );
  }


  /** @test */
  public function testSeterrorMethodSetsTheErrorPropertyIfItIsNotSetAlready()
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
  public function testGeterrorMethodReturnsTheErrorIfThereIsOne()
  {
    $this->setNonPublicPropertyValue('error', 6);

    $class_cfg = $this->getClassCgf();

    $this->assertNotNull($this->user->getFullError());
    $this->assertTrue(isset($class_cfg['errors']));
    $this->assertTrue(is_array($class_cfg['errors']));
    $this->assertTrue(!empty($class_cfg['errors']));

    $result = $this->user->getFullError();

    $this->assertIsArray($result);
    $this->assertTrue(isset($result['code']));
    $this->assertTrue(isset($result['text']));
    $this->assertIsInt($result['code']);
    $this->assertIsString($result['text']);
    $this->assertSame(6, $result['code']);
    $this->assertSame($class_cfg['errors'][6], $result['text']);
  }


  /** @test */
  public function testGeterrorMethodReturnsNullIfThereIsNoError()
  {
    $this->setNonPublicPropertyValue('error', null);

    $this->assertNull($this->user->getFullError());
  }


  /** @test */
  public function testLoginMethodLoginAUserFromTheProvidedId()
  {
    $this->db_mock->shouldReceive('update')->once()->andReturn(1);
    $this->db_mock->shouldReceive('rselect')->once()->andReturn($this->getExpectedSession());

    $this->user = new User($this->db_mock);

    $this->assertNotNull($this->user->getFullError());
    $this->assertNull($this->user->getId());
    $this->assertFalse($this->user->isAuth());

    $login_method = $this->getNonPublicMethod('logIn');
    $result       = $login_method->invoke($this->user, $this->user_id);

    $this->assertNull($this->user->getFullError());
    $this->assertSame($this->user_id ,$this->user->getId());
    $this->assertTrue($this->user->isAuth());

    $this->assertInstanceOf(User::class, $result);
  }


  /** @test */
  public function testGetprintMethodReturnsAHashFromUserAgentAndFingerprint()
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
  public function testGetprintMethodReturnsNullIfFingerprintIsNotDefined()
  {
    $session_data = $this->getNonPublicProperty('data', $this->getSession());

    $this->assertTrue(isset($session_data[$this->session_index]['fingerprint']));

    unset($session_data[$this->session_index]['fingerprint']);

    $this->setNonPublicPropertyValue('data', $session_data, $this->getSession());

    $get_print_method = $this->getNonPublicMethod('getPrint');

    $this->assertNull($get_print_method->invoke($this->user));
  }


  /** @test */
  public function testGetsessiondbidMethodReturnsTheDatabaseIdForTheSessionRowIfExists()
  {
    $get_id_session_method = $this->getNonPublicMethod('getSessionDbId');

    $session_data = $this->getNonPublicProperty('data', $this->getSession());

    $this->assertTrue(isset($session_data[$this->session_index]['id_session']));

    $this->assertSame(
      $session_data[$this->session_index]['id_session'],
      $get_id_session_method->invoke($this->user)
    );
  }


  /** @test */
  public function testGetsessiondbidMethodReturnsNullIfSessionRowDoesNotExists()
  {
    $get_id_session_method = $this->getNonPublicMethod('getSessionDbId');

    $session_data = $this->getNonPublicProperty('data', $this->getSession());

    $this->assertTrue(isset($session_data[$this->session_index]['id_session']));

    unset($session_data[$this->session_index]['id_session']);

    $this->setNonPublicPropertyValue('data', $session_data,$this->getSession());

    $this->assertNull(
      $get_id_session_method->invoke($this->user)
    );
  }


  /** @test */
  public function testRecordattemptMethodIncrementsTheNumAttemptVariable()
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
  public function testLoginMethodInitializeAndSavesTheSessionAfterAuthentication()
  {
    $this->db_mock->shouldReceive('update')->once()->andReturn(1);
    $this->db_mock->shouldReceive('rselect')->once()->andReturn($this->getExpectedSession());

    $this->user = new User($this->db_mock);

    $this->assertFalse($this->user->isAuth());
    $this->assertNull($this->user->getId());
    $this->assertNotNull($this->user->getFullError());

    $this->setNonPublicPropertyValue('error', null);

    $_login_method = $this->getNonPublicMethod('_login');
    $result        = $_login_method->invoke($this->user, $this->user_id);

    $this->assertNull($this->user->getFullError());
    $this->assertSame($this->user_id ,$this->user->getId());
    $this->assertTrue($this->user->isAuth());

    $this->assertInstanceOf(User::class, $result);
  }


  /** @test */
  public function testLoginMethodDoesNotAuthenticateIfThereIsAnError()
  {
    $this->db_mock->shouldReceive('selectOne')->once()->andReturn(null);
    $this->db_mock->shouldReceive('insert')->once()->andReturn(1);
    $this->db_mock->shouldReceive('lastId')->once()->andReturn($this->session_id);

    $this->user = new User($this->db_mock);

    $this->assertFalse($this->user->isAuth());
    $this->assertNull($this->user->getId());
    $this->assertNotNull($this->user->getFullError());

    $this->setNonPublicPropertyValue('error', 3);

    $_login_method = $this->getNonPublicMethod('_login');
    $result        = $_login_method->invoke($this->user, $this->user_id);

    $this->assertFalse($this->user->isAuth());
    $this->assertNull($this->user->getId());
    $this->assertNotNull($this->user->getFullError());

    $this->assertInstanceOf(User::class, $result);
  }


  /** @test */
  public function testUserInfoMethodFetchesUserDataFromDatabaseAndSaveItInSession()
  {
    $this->setNonPublicPropertyValue('id', $this->user_id);
    $this->setNonPublicPropertyValue('error', null);
    $this->replaceDbWithMockedVersion();

    $fields           = $this->getNonPublicProperty('fields');
    $fields['enckey'] = 'enckey';

    $this->setNonPublicPropertyValue('fields', $fields);

    $this->assertTrue(isset($this->getNonPublicProperty('fields')['enckey']));
    $this->assertSame('enckey', $this->getNonPublicProperty('fields')['enckey']);
    $this->assertNull($this->user->getIdGroup());
    $this->assertFalse(isset($this->getSessionData()[$this->user_index]));

    $this->db_mock->shouldReceive('rselect')->once()->andReturn(
      [
      'id_group'  => 1,
      'email'     => 'foobar@mail.comm',
      ]
    );

    $user_info_method = $this->getNonPublicMethod('_user_info');
    $result           = $user_info_method->invoke($this->user);

    $this->assertTrue(!isset($this->getNonPublicProperty('fields')['enckey']));
    $this->assertSame(1, (int)$this->user->getIdGroup());
    $this->assertInstanceOf(User::class, $result);
    $this->assertTrue(isset($this->getSessionData()[$this->user_index]));
    $this->assertSame(1, $this->getSessionData()[$this->user_index]['id_group']);
    $this->assertSame('foobar@mail.comm', $this->getSessionData()[$this->user_index]['email']);
  }


  /** @test */
  public function testUserInfoMethodDoesNotFetchUserDataFromDatabaseIfTheSessionHasConfig()
  {
    $this->setNonPublicPropertyValue('id', $this->user_id);
    $this->setNonPublicPropertyValue('error', null);
    $this->replaceDbWithMockedVersion();

    $this->db_mock->shouldNotReceive('rselect');

    $this->assertNull($this->getConfig());

    $session = $this->getSession();

    $session->set(['foo' => 'bar'], $this->user_index, 'cfg');
    $session->set(3, $this->user_index, 'id_group');

    $this->assertIsArray($cfg = $this->user->getSession('cfg'));
    $this->assertIsInt($id_group = $this->user->getSession('id_group'));
    $this->assertTrue(isset($cfg['foo']));
    $this->assertSame(3, $id_group);

    $user_info_method = $this->getNonPublicMethod('_user_info');
    $result           = $user_info_method->invoke($this->user);

    $this->assertSame(['foo' => 'bar'], $this->getConfig());
    $this->assertSame(3, (int)$this->user->getIdGroup());
    $this->assertInstanceOf(User::class, $result);
  }


  /** @test */
  public function testGetEncryptionKeyMethodRetrievesAndSavesTheEcnryptionKeyFromDbIfNotDefined()
  {
    $this->setNonPublicPropertyValue('auth', true);
    $this->replaceDbWithMockedVersion();

    $this->assertNull($this->getNonPublicProperty('_encryption_key'));

    $this->db_mock->shouldReceive('selectOne')->once()->andReturn('encryption_key');

    $get_encryption_key_method = $this->getNonPublicMethod('_get_encryption_key');
    $result                    = $get_encryption_key_method->invoke($this->user);

    $this->assertSame('encryption_key', $this->getNonPublicProperty('_encryption_key'));
    $this->assertSame('encryption_key', $result);
  }


  /** @test */
  public function testGetEncryptionKeyMethodDoesNotRetrieveEncryptionKeyIfItIsAlreadyDefined()
  {
    $this->setNonPublicPropertyValue('auth', true);
    $this->setNonPublicPropertyValue('_encryption_key', 'encryption_key');
    $this->replaceDbWithMockedVersion();

    $get_encryption_key_method = $this->getNonPublicMethod('_get_encryption_key');
    $result                    = $get_encryption_key_method->invoke($this->user);

    $this->db_mock->shouldNotReceive('selectOne');

    $this->assertSame('encryption_key', $this->getNonPublicProperty('_encryption_key'));
    $this->assertSame('encryption_key', $result);
  }


  /** @test */
  public function testSessInfoMethodFetchesAllInformationAboutUserSessionIfSessionIdExistsAndIdExistsAndSessionExistsInDb()
  {
    $session = $this->getSession();
    $session->set($this->user_id, $this->user_index, 'id');

    $this->replaceDbWithMockedVersion();

    $this->db_mock->shouldReceive('rselect')->once()->andReturn(
      [
      'cfg' => json_encode(['foo' => 'bar'])
      ]
    );

    $sess_info_method = $this->getNonPublicMethod('_sess_info');
    $result           = $sess_info_method->invoke($this->user);

    $this->assertSame(['foo' => 'bar'], $this->getSessionConfig());
    $this->assertInstanceOf(User::class, $result);
  }


  /** @test */
  public function testSessInfoSetsAnErrorIfSessionIdExistsButIdNotExists()
  {
    $this->replaceDbWithMockedVersion();
    $this->setNonPublicPropertyValue('error', null);

    $this->db_mock->shouldNotReceive('rselect');

    $session_before = $this->getSessionConfig();

    $sess_info_method = $this->getNonPublicMethod('_sess_info');
    $result           = $sess_info_method->invoke($this->user);

    $this->assertSame($session_before, $this->getSessionConfig());
    $this->assertSame(14, $this->user->getFullError()['code']);
    $this->assertInstanceOf(User::class, $result);
  }


  /** @test */
  public function testSessInfoSetsAnErrorIfSessionIdNotExistsButIdExists()
  {
    $session = $this->getSession();
    $session->set($this->user_id, $this->user_index, 'id');
    $session->set(null, $this->session_index, 'id_session');

    $this->replaceDbWithMockedVersion();
    $this->setNonPublicPropertyValue('error', null);

    $this->db_mock->shouldNotReceive('rselect');

    $session_before = $this->getSessionConfig();

    $sess_info_method = $this->getNonPublicMethod('_sess_info');
    $result           = $sess_info_method->invoke($this->user);

    $this->assertSame($session_before, $this->getSessionConfig());
    $this->assertSame(14, $this->user->getFullError()['code']);
    $this->assertInstanceOf(User::class, $result);
  }


  /** @test */
  public function testSessInfoMethodInitializeANewSessionThenFetchesItIfSessionIdExistsButSessionNotExistsInDbAndNewSessionIdIsDifferrent()
  {
    $session = $this->getSession();
    $session->set($this->user_id, $this->user_index, 'id');

    $this->replaceDbWithMockedVersion();
    $this->setNonPublicPropertyValue('error', null);

    // The first call should return null => as if session_id not exists in db
    // The second call should the cfg => as of the _sess_info recursive call after generating a new session
    $this->db_mock->shouldReceive('rselect')->twice()->andReturn(
      null, [
      'cfg' => json_encode(['foo' => 'bar'])
      ]
    );
    $this->db_mock->shouldReceive('selectOne')->once()->andReturnFalse();
    $this->db_mock->shouldReceive('insert')->once()->andReturn(1);
    $this->db_mock->shouldReceive('lastId')->once()->andReturn('aaaa2c70bcac11eba47652540000cfaa');

    $session_before = $this->getSessionConfig();

    $sess_info_method = $this->getNonPublicMethod('_sess_info');
    $result           = $sess_info_method->invoke($this->user);

    $this->assertNotSame($session_before, $this->getSessionConfig());
    $this->assertSame(['foo' => 'bar'], $this->getSessionConfig());
    $this->assertNull($this->user->getFullError());
    $this->assertInstanceOf(User::class, $result);
  }


  /** @test */
  public function testCheckPasswordMethodComparesAStringWithTheHash()
  {
    $hash_method = $this->getNonPublicMethod('_hash');
    $hash        = $hash_method->invoke($this->user, 'foo');

    $check_password_method = $this->getNonPublicMethod('_check_password');

    $this->assertTrue(
      $check_password_method->invoke($this->user, 'foo', $hash)
    );

    $this->assertFalse(
      $check_password_method->invoke($this->user, 'foo', 'foo')
    );
  }


  /** @test */
  public function testHashMethodEncyptsAStringBasedOnConfiguredHashFunction()
  {
    $hash_method = $this->getNonPublicMethod('_hash');

    $this->assertSame(
      sha1('foo'),
      $hash_method->invoke($this->user, 'foo')
    );

    $class_cfg = $this->getClassCgf();

    $class_cfg['encryption'] = 'dummyMethod';

    $this->setNonPublicPropertyValue('class_cfg', $class_cfg);

    $this->assertSame(
      hash('sha256', 'foo'),
      $hash_method->invoke($this->user, 'foo')
    );

    unset($class_cfg['encryption']);

    $this->setNonPublicPropertyValue('class_cfg', $class_cfg);

    $this->assertSame(
      hash('sha256', 'foo'),
      $hash_method->invoke($this->user, 'foo')
    );
  }


  /** @test */
  public function testRetrieveSessionMethodFetchesUserInfoFromSessionIfAuthenticated()
  {
    $this->assertFalse($this->user->isAuth());

    $session = $this->getSession();
    $session->set($this->user_id, $this->user_index, 'id');

    $this->setNonPublicPropertyValue('id', null);
    $this->setNonPublicPropertyValue('error', null);
    $this->replaceDbWithMockedVersion();

    $this->db_mock->shouldReceive('rselect')->twice()->andReturn(
      $user_session = [
        'cfg' => $cfg = json_encode(
          ['fingerprint' => $this->getNonPublicMethod('getPrint')->invoke($this->user)]
        ),
        'id_group' => 66
      ]
    );

    $this->db_mock->shouldReceive('update')->twice()->andReturn(1);

    $retrieve_session_method = $this->getNonPublicMethod('_retrieve_session');
    $result                  = $retrieve_session_method->invoke($this->user);

    $this->assertTrue($this->user->isAuth());
    $this->assertTrue($this->user->check());
    $this->assertSame($this->user_id, $this->user->getId());
    $this->assertSame(66, (int)$this->user->getIdGroup());
    $this->assertSame(
      json_decode($cfg, true)['fingerprint'],
      $this->getSessionConfig()['fingerprint']
    );

    $user_session['cfg'] = json_decode($user_session['cfg'], true);

    $this->assertSame($user_session, $this->getSessionData()[$this->user_index]);
    $this->assertInstanceOf(User::class, $result);

  }


  /** @test */
  public function testInitSessionMethodCreatesUserSessionIfNotAlreadyExistsInSession()
  {
    $this->setNonPublicPropertyValue('sess_cfg', null);
    $this->setNonPublicPropertyValue('error', null);
    $this->replaceDbWithMockedVersion();

    $this->db_mock->shouldReceive('insert')->once()->andReturn(1);
    $this->db_mock->shouldReceive('lastId')->once()->andReturn($this->session_id);

    $session = $this->getSession();
    $session->set(null, $this->session_index);

    $init_session_method = $this->getNonPublicMethod('_init_session');

    $result       = $init_session_method->invoke($this->user);
    $session_data = $this->getSessionData()[$this->session_index] ?? [];

    $this->assertNotEmpty($session_data);
    $this->assertNotNull($session_cfg = $this->getNonPublicProperty('sess_cfg'));
    $this->assertNotEmpty($session_cfg['fingerprint']);
    $this->assertNotEmpty($session_cfg['last_renew']);
    $this->assertSame($this->session_id, $session_data['id_session']);
    $this->assertNotEmpty($session_data['fingerprint']);
    $this->assertTrue(isset($session_data['tokens']));
    $this->assertNotEmpty($session_data['salt']);
    $this->assertInstanceOf(User::class, $result);
    $this->assertNull($this->getNonPublicProperty('error'));
  }


  /** @test */
  public function testInitSessionMethodCreatesUserSessionIfNotAlreadyExistsInDatabaseButExistsInSession()
  {
    $this->setNonPublicPropertyValue('sess_cfg', null);
    $this->setNonPublicPropertyValue('error', null);
    $this->replaceDbWithMockedVersion();

    $this->db_mock->shouldReceive('selectOne')->once()->andReturnNull();
    $this->db_mock->shouldReceive('insert')->once()->andReturn(1);
    $this->db_mock->shouldReceive('lastId')->once()->andReturn($this->session_id);

    $session = $this->getSession();
    $session->set(null, $this->session_index, 'salt');
    $session->set(null, $this->session_index, 'fingerprint');
    $session->set(null, $this->session_index, 'tokens');

    $init_session_method = $this->getNonPublicMethod('_init_session');

    $result       = $init_session_method->invoke($this->user);
    $session_data = $this->getSessionData()[$this->session_index] ?? [];

    $this->assertNotEmpty($session_data);
    $this->assertNotNull($session_cfg = $this->getNonPublicProperty('sess_cfg'));
    $this->assertNotEmpty($session_cfg['fingerprint']);
    $this->assertNotEmpty($session_cfg['last_renew']);
    $this->assertSame($this->session_id, $session_data['id_session']);
    $this->assertNotEmpty($session_data['fingerprint']);
    $this->assertTrue(isset($session_data['tokens']));
    $this->assertNotEmpty($session_data['salt']);
    $this->assertInstanceOf(User::class, $result);
    $this->assertNull($this->getNonPublicProperty('error'));
  }


  /** @test */
  public function testInitSessionMethodGetUserSessionWhenAlreadyExistsInBothDatabaseAndSession()
  {
    $this->setNonPublicPropertyValue('sess_cfg', null);
    $this->setNonPublicPropertyValue('error', null);
    $this->replaceDbWithMockedVersion();

    $this->db_mock->shouldReceive('selectOne')->once()->andReturn(
      json_encode(
        [
        'fingerprint' => 'fingerprint',
        'last_renew' => time()
        ]
      )
    );

    $init_session_method = $this->getNonPublicMethod('_init_session');

    $result       = $init_session_method->invoke($this->user);
    $session_data = $this->getSessionData()[$this->session_index] ?? [];

    $this->assertNotEmpty($session_data);
    $this->assertNotNull($session_cfg = $this->getNonPublicProperty('sess_cfg'));
    $this->assertNotEmpty($session_cfg['fingerprint']);
    $this->assertNotEmpty($session_cfg['last_renew']);
    $this->assertSame($this->session_id, $session_data['id_session']);
    $this->assertNotEmpty($session_data['fingerprint']);
    $this->assertTrue(isset($session_data['tokens']));
    $this->assertNotEmpty($session_data['salt']);
    $this->assertInstanceOf(User::class, $result);
    $this->assertNull($this->getNonPublicProperty('error'));
  }


  /** @test */
  public function testInitSessionMethodSetsAnErrorWhenCannotInsertANewSessionInDatabase()
  {
    $this->setNonPublicPropertyValue('sess_cfg', null);
    $this->setNonPublicPropertyValue('error', null);
    $this->replaceDbWithMockedVersion();

    $this->db_mock->shouldReceive('insert')->once()->andReturn(0);

    $session = $this->getSession();
    $session->set(null, $this->session_index);

    $init_session_method = $this->getNonPublicMethod('_init_session');

    $result       = $init_session_method->invoke($this->user);
    $session_data = $this->getSessionData()[$this->session_index] ?? [];

    $this->assertEmpty($session_data);
    $this->assertNotNull($session_cfg = $this->getNonPublicProperty('sess_cfg'));
    $this->assertNotEmpty($session_cfg['fingerprint']);
    $this->assertNotEmpty($session_cfg['last_renew']);
    $this->assertFalse(
      isset(
        $session_data['id_session'],
        $session_data['fingerprint'],
        $session_data['tokens'],
        $session_data['salt']
      )
    );

    $this->assertInstanceOf(User::class, $result);
    $this->assertSame(16, $this->getNonPublicProperty('error'));
  }


  /** @test */
  public function testSetSessionMethodSetsAnAttributeInTheSessionIndex()
  {
    $session = $this->getSession();
    $session->set([], $this->session_index);

    $set_session_method = $this->getNonPublicMethod('_set_session');

    $result = $set_session_method->invoke($this->user, 'foo', 'bar');
    $set_session_method->invoke($this->user, ['foo2' => 'bar2']);

    $session_data = $this->getSessionData();

    $this->assertInstanceOf(User::class, $result);
    $this->assertTrue(isset($session_data[$this->session_index]['foo']));
    $this->assertSame('bar', $session_data[$this->session_index]['foo']);
    $this->assertInstanceOf(User::class, $result);

    $this->assertTrue(isset($session_data[$this->session_index]['foo2']));
    $this->assertSame('bar2', $session_data[$this->session_index]['foo2']);
    $this->assertInstanceOf(User::class, $result);
  }


  /** @test */
  public function testSetSessionMethodDoesNotSetAnAttributeIfSessionIndexDoesNotExist()
  {
    $session = $this->getSession();
    $session->set(null, $this->session_index);

    $set_session_method = $this->getNonPublicMethod('_set_session');

    $result = $set_session_method->invoke($this->user, 'foo');

    $this->assertInstanceOf(User::class, $result);
    $this->assertEmpty($this->getSessionData());
  }


  /** @test */
  public function testSetSessionMethodDoesNotSetAnAttributeIfArgumnetIsNonAssocArray()
  {
    $session = $this->getSession();
    $session->set(null, $this->session_index);

    $set_session_method = $this->getNonPublicMethod('_set_session');

    $result = $set_session_method->invoke($this->user, ['foo', 'bar']);

    $this->assertInstanceOf(User::class, $result);
    $this->assertEmpty($this->getSessionData());
  }


  /** @test */
  public function testGetSessionMethodGetsAndAttributeOrTheWholeSessiomPartFromSessionIndex()
  {
    $session = $this->getSession();
    $session->set(null, $this->session_index);
    $session->set('bar', $this->session_index, 'foo');
    $session->set('bar2', $this->session_index, 'foo2');

    $get_session_method = $this->getNonPublicMethod('_get_session');

    $this->assertSame(
      'bar',
      $get_session_method->invoke($this->user, 'foo')
    );

    $this->assertSame(
      'bar2',
      $get_session_method->invoke($this->user, 'foo2')
    );

    $this->assertNull($get_session_method->invoke($this->user, 'foo3'));

    $this->assertSame(
      ['foo' => 'bar', 'foo2' => 'bar2'],
      $get_session_method->invoke($this->user)
    );
  }


  /** @test */
  public function testGetSessionMethodReturnsNullIfSessionIndexDoesNotExist()
  {
    $session = $this->getSession();
    $session->set(null, $this->session_index);

    $this->assertNull(
      $this->getNonPublicMethod('_get_session')->invoke($this->user)
    );
  }


  /** @test */
  public function testCheckCredentialsMethodChecksTheCredentialOfTheUser()
  {
    $this->setNonPublicPropertyValue('error', null);
    $this->setNonPublicPropertyValue('id', null);
    $this->setNonPublicPropertyValue('auth', false);
    $this->replaceDbWithMockedVersion();

    $has_method = $this->getNonPublicMethod('_hash');

    $this->db_mock->shouldReceive('selectOne')->twice()->andReturn(
      $this->user_id,
      $has_method->invoke($this->user, 'pass')
    );

    $this->db_mock->shouldReceive('update')->once()->andReturn(1);
    $this->db_mock->shouldReceive('rselect')->once()->andReturn(
      [
      'cfg'      => json_encode(['foo' => 'bar']),
      'id_group' => 33
      ]
    );

    $class_cfg                = $this->getClassCgf();
    $check_credentials_method = $this->getNonPublicMethod('_check_credentials');

    $result = $check_credentials_method->invoke(
      $this->user, [
        $class_cfg['fields']['salt']  => $this->getSession()->get($this->session_index, 'salt'),
        $class_cfg['fields']['user']  => 'user',
        $class_cfg['fields']['pass']  => 'pass',
      ]
    );

    $this->assertTrue($result);
    $this->assertTrue($this->user->isAuth());
    $this->assertSame(['foo' => 'bar'], $this->getConfig());
    $this->assertSame(33, (int)$this->user->getIdGroup());
    $this->assertSame(
      ['cfg' => ['foo' => 'bar'], 'id_group' => 33],
      $this->getSessionData()[$this->user_index]
    );
  }


  /** @test */
  public function testCheckCredentialsMethodSetsAnErrorIfSaltNotProvided()
  {
    $this->setNonPublicPropertyValue('error', null);
    $this->setNonPublicPropertyValue('auth', false);

    $class_cfg = $this->getClassCgf();

    $check_credentials_method = $this->getNonPublicMethod('_check_credentials');

    $result = $check_credentials_method->invoke(
      $this->user, [
      $class_cfg['fields']['user'] => 'user',
      $class_cfg['fields']['pass'] => 'pass'
      ]
    );

    $this->assertFalse($result);
    $this->assertSame(11, $this->getNonPublicProperty('error'));
    $this->assertFalse($this->user->isAuth());
  }


  /** @test */
  public function testCheckCredentialsMethodSetsAnErrorAndDestroySessionIfSaltIsNotValid()
  {
    $this->setNonPublicPropertyValue('error', null);
    $this->setNonPublicPropertyValue('auth', false);

    $this->assertNotEmpty($this->getSessionData());

    $class_cfg                = $this->getClassCgf();
    $check_credentials_method = $this->getNonPublicMethod('_check_credentials');

    $result = $check_credentials_method->invoke(
      $this->user, [
      $class_cfg['fields']['user'] => 'user',
      $class_cfg['fields']['pass'] => 'pass',
      $class_cfg['fields']['salt'] => 'foo'
      ]
    );

    $this->assertFalse($result);
    $this->assertSame(17, $this->getNonPublicProperty('error'));
    $this->assertEmpty($this->getSessionData());
    $this->assertFalse($this->user->isAuth());
  }


  /** @test */
  public function testCheckCredentialsMethodSetsAnErrorIfPasswordDoesNotMatch()
  {
    $this->setNonPublicPropertyValue('error', null);
    $this->setNonPublicPropertyValue('id', null);
    $this->setNonPublicPropertyValue('auth', false);
    $this->replaceDbWithMockedVersion();

    $this->db_mock->shouldReceive('selectOne')->times(4)->andReturn(
      $this->user_id, 'pass', $this->user_id, 'pass'
    );

    $class_cfg                = $this->getClassCgf();
    $check_credentials_method = $this->getNonPublicMethod('_check_credentials');

    $result = $check_credentials_method->invoke(
      $this->user, [
        $class_cfg['fields']['salt']  => $this->getSession()->get($this->session_index, 'salt'),
        $class_cfg['fields']['user']  => 'user',
        $class_cfg['fields']['pass']  => 'pass',
      ]
    );

    $this->assertFalse($result);
    $this->assertFalse($this->user->isAuth());
    $this->assertSame(['num_attempts' => 1], $this->getConfig());
    $this->assertSame(6, $this->getNonPublicProperty('error'));

    // Test in case number of attempts is greater than max attempts
    $this->setNonPublicPropertyValue('error', null);

    $class_cfg                 = $this->getClassCgf();
    $class_cfg['max_attempts'] = 2;

    $this->setNonPublicPropertyValue('class_cfg', $class_cfg);

    $config                 = $this->getConfig();
    $config['num_attempts'] = 3;

    $this->setNonPublicPropertyValue('cfg', $config);

    $result = $check_credentials_method->invoke(
      $this->user, [
        $class_cfg['fields']['salt']  => $this->getSession()->get($this->session_index, 'salt'),
        $class_cfg['fields']['user']  => 'user',
        $class_cfg['fields']['pass']  => 'pass',
      ]
    );

    $this->assertFalse($result);
    $this->assertFalse($this->user->isAuth());
    $this->assertSame(['num_attempts' => 4], $this->getConfig());
    $this->assertSame(4, $this->getNonPublicProperty('error'));
  }


  /** @test */
  public function testCheckCredentialsMethodSetsAnErrorIfUserNotFoundInDatabase()
  {
    $this->setNonPublicPropertyValue('error', null);
    $this->setNonPublicPropertyValue('id', null);
    $this->setNonPublicPropertyValue('auth', false);
    $this->replaceDbWithMockedVersion();

    $this->db_mock->shouldReceive('selectOne')->once()->andReturnFalse();

    $class_cfg                = $this->getClassCgf();
    $check_credentials_method = $this->getNonPublicMethod('_check_credentials');

    $result = $check_credentials_method->invoke(
      $this->user, [
        $class_cfg['fields']['salt']  => $this->getSession()->get($this->session_index, 'salt'),
        $class_cfg['fields']['user']  => 'user',
        $class_cfg['fields']['pass']  => 'pass',
      ]
    );

    $this->assertFalse($result);
    $this->assertFalse($this->user->isAuth());
    $this->assertSame(6, $this->getNonPublicProperty('error'));
  }


  /** @test */
  public function testCheckCredentialsMethodSetsAnErrorIfUserAndPasswordParamsDontExist()
  {
    $this->setNonPublicPropertyValue('error', null);
    $this->setNonPublicPropertyValue('id', null);
    $this->setNonPublicPropertyValue('auth', false);

    $class_cfg                = $this->getClassCgf();
    $check_credentials_method = $this->getNonPublicMethod('_check_credentials');

    $result = $check_credentials_method->invoke(
      $this->user, [
        $class_cfg['fields']['salt']  => $this->getSession()->get($this->session_index, 'salt')
      ]
    );

    $this->assertFalse($result);
    $this->assertFalse($this->user->isAuth());
    $this->assertSame(12, $this->getNonPublicProperty('error'));
  }

  /** @test */
  public function testCheckCredentialsMethodDoesNotCheckCredentialsIfThereIsAnError()
  {
    $this->setNonPublicPropertyValue('error', 2);
    $this->setNonPublicPropertyValue('auth', false);

    $check_credentials_method = $this->getNonPublicMethod('_check_credentials');

    $result = $check_credentials_method->invoke($this->user, 'user', 'pass');

    $this->assertFalse($result);
    $this->assertSame(2, $this->getNonPublicProperty('error'));
  }

  /** @test */
  public function testInitDirMethodDefinesDirectoryPathForTheUser()
  {
    \bbn\File\Dir::delete(Mvc::getUserDataPath($this->user_id), true);
    \bbn\File\Dir::delete(Mvc::getUserTmpPath($this->user_id), true);

    $this->setNonPublicPropertyValue('id', $this->user_id);
    $this->setNonPublicPropertyValue('error', null);

    $init_dir_method = $this->getNonPublicMethod('_init_dir');

    $result = $init_dir_method->invoke($this->user);

    $this->assertInstanceOf(User::class, $result);

    $this->assertSame(
      Mvc::getUserDataPath($this->user_id),
      $path = $this->getNonPublicProperty('path')
    );

    $this->assertSame(
      Mvc::getUserTmpPath($this->user_id),
      $temp_path = $this->getNonPublicProperty('tmp_path')
    );

    $this->assertFalse(file_exists($path));
    $this->assertFalse(file_exists($temp_path));
    $this->assertTrue(defined('BBN_USER_PATH'));
  }

  /** @test */
  public function testInitDirMethodDefinesDirectoryPathForTheUserAndCreatedIt()
  {
    \bbn\File\Dir::delete(Mvc::getUserDataPath($this->user_id), true);
    \bbn\File\Dir::delete(Mvc::getUserTmpPath($this->user_id), true);

    $this->setNonPublicPropertyValue('id', $this->user_id);
    $this->setNonPublicPropertyValue('error', null);

    $init_dir_method = $this->getNonPublicMethod('_init_dir');

    $result = $init_dir_method->invoke($this->user, true);

    $this->assertInstanceOf(User::class, $result);

    $this->assertSame(
      Mvc::getUserDataPath($this->user_id),
      $path = $this->getNonPublicProperty('path')
    );

    $this->assertSame(
      Mvc::getUserTmpPath($this->user_id),
      $temp_path = $this->getNonPublicProperty('tmp_path')
    );

    $this->assertTrue(file_exists($path));
    $this->assertTrue(is_dir($path));

    \bbn\File\Dir::delete($path, true);

    $this->assertTrue(file_exists($temp_path));
    $this->assertTrue(is_dir($temp_path));

    \bbn\File\Dir::delete($temp_path, true);

    $this->assertTrue(defined('BBN_USER_PATH'));
  }

  /** @test */
  public function testInitDirMethodDoesNotDefineAPathForTheUserIfNoIdSaved()
  {
    $this->setNonPublicPropertyValue('id', null);
    $this->setNonPublicPropertyValue('error', null);

    $init_dir_method = $this->getNonPublicMethod('_init_dir');

    $result = $init_dir_method->invoke($this->user, true);

    $this->assertNull($this->getNonPublicProperty('path'));
    $this->assertNull($this->getNonPublicProperty('tmp_path'));
    $this->assertInstanceOf(User::class, $result);
  }

  /** @test */
  public function testInitDirMethodDoesNotDefineAPathForTheUserIfIdSavedButThereIsAnError()
  {
    $this->setNonPublicPropertyValue('id', $this->user_id);
    $this->setNonPublicPropertyValue('error', 3);

    $init_dir_method = $this->getNonPublicMethod('_init_dir');

    $result = $init_dir_method->invoke($this->user, true);

    $this->assertNull($this->getNonPublicProperty('path'));
    $this->assertNull($this->getNonPublicProperty('tmp_path'));
    $this->assertInstanceOf(User::class, $result);
  }

  /** @test */
  public function testAuthenticateMethodSetsTheUserAsAuthenticated()
  {
    $this->setNonPublicPropertyValue('error', null);
    $this->setNonPublicPropertyValue('id', null);
    $this->setNonPublicPropertyValue('auth', false);
    $this->replaceDbWithMockedVersion();

    $this->db_mock->shouldReceive('update')->once()->andReturn(1);

    $authenticate_method = $this->getNonPublicMethod('_authenticate');

    $result = $authenticate_method->invoke($this->user, $this->user_id);

    $this->assertTrue($this->user->isAuth());
    $this->assertNull($this->user->getFullError());
    $this->assertInstanceOf(User::class, $result);
  }

  /** @test */
  public function testAuthenticateMethodDoesNotSetTheUserAsAuthenticatedIfThereIsAnError()
  {
    $this->setNonPublicPropertyValue('error', 2);
    $this->setNonPublicPropertyValue('id', null);
    $this->setNonPublicPropertyValue('auth', false);
    $this->replaceDbWithMockedVersion();

    $this->db_mock->shouldNotReceive('update');

    $authenticate_method = $this->getNonPublicMethod('_authenticate');

    $result = $authenticate_method->invoke($this->user, $this->user_id);

    $this->assertFalse($this->user->isAuth());
    $this->assertNotNull($this->user->getFullError());
    $this->assertInstanceOf(User::class, $result);
  }
}
