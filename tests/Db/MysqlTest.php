<?php

namespace Db;

use bbn\Db2;
use bbn\Db2\Enums\Errors;
use bbn\Db2\Languages\Mysql;
use PHPUnit\Framework\TestCase;
use tests\Files;
use tests\Reflectable;
use tests\ReflectionHelpers;

class MysqlTest extends TestCase
{
  use Reflectable, Files;

  protected static Mysql $mysql;

  protected static $db_mock;

  protected static $real_params_default;

  protected function setUp(): void
  {
    $this->setNonPublicPropertyValue('_has_error_all', false);
    $this->setNonPublicPropertyValue('_has_error', false);
    $this->setNonPublicPropertyValue('last_error', null);
    $this->setNonPublicPropertyValue('last_real_params', self::$real_params_default);
    $this->cleanTestingDir();
  }

  public static function setUpBeforeClass(): void
  {
    if (!file_exists($env_file = getcwd() . '/tests/.env.test')) {
      throw new \Exception('env file does not exist');
    }

    $env = file_get_contents($env_file);

    foreach (explode(PHP_EOL, $env) as $item) {
      $res = explode('=', $item);
      $key  = $res[0];
      $value = $res[1];
      putenv("$key=$value");
    }

    self::$db_mock = \Mockery::mock(Db2::class);

    self::$mysql = new Mysql(self::getDbConfig());

    self::$real_params_default = ReflectionHelpers::getNonPublicProperty(
      'last_real_params', self::$mysql
    );
  }

  protected static function getDbConfig()
  {
    return array(
      'engine'        => 'mysql',
      'host'          => 'localhost',
      'user'          => 'root',
      'pass'          => getenv('db_pass'),
      'db'            => 'bbn_test',
      'cache_length'  => 3000,
      'on_error'      => Errors::E_STOP
    );
  }

  public function getInstance()
  {
    return self::$mysql;
  }


  protected function tearDown(): void
  {
    \Mockery::close();
    $this->cleanTestingDir();
  }

 /** @test */
  public function constructor_test()
  {
    $this->assertInstanceOf(\PDO::class, $this->getNonPublicProperty('pdo'));

    $db_cfg = self::getDbConfig();

    $this->assertSame(
      array_merge($db_cfg, [
        'port'      => 3306,
        'code_db'   => 'bbn_test',
        'code_host' => "{$db_cfg['user']}@{$db_cfg['host']}"
      ]),
      $this->getNonPublicProperty('cfg')
    );

    $this->assertSame(
      "{$db_cfg['user']}@{$db_cfg['host']}",
      $this->getNonPublicProperty('connection_code')
    );

    $this->assertSame('bbn_test', $this->getNonPublicProperty('current'));
    $this->assertSame('localhost', $this->getNonPublicProperty('host'));
    $this->assertSame('root', $this->getNonPublicProperty('username'));

    $this->assertSame(3000, $this->getNonPublicProperty('cache_renewal'));
    $this->assertSame(Errors::E_STOP, $this->getNonPublicProperty('on_error'));
  }

  /** @test */
  public function makeHash_method_makes_a_hash_string_that_will_be_the_id_of_the_request()
  {
    $hash_contour    = $this->getNonPublicProperty('hash_contour');
    $expected_string = "{$hash_contour}%s{$hash_contour}";

    $method = $this->getNonPublicMethod('makeHash');

    $expected = sprintf($expected_string, md5('--bar----bar2--'));
    $this->assertSame(
      $expected,
      $method->invoke(self::$mysql, ['foo' => 'bar', 'foo2' => 'bar2'])
    );

    $expected = sprintf($expected_string, md5('--foo----bar----baz--'));
    $this->assertSame(
      $expected,
      $method->invoke(self::$mysql, 'foo', 'bar', 'baz')
    );

    $expected = sprintf($expected_string, md5('--foo--' . serialize(['bar', 'bar2'])));
    $this->assertSame($expected, $method->invoke(self::$mysql,[
      'foo',
      'foo2' => ['bar', 'bar2']
    ]));
  }

  /**
   * @test
   * @depends makeHash_method_makes_a_hash_string_that_will_be_the_id_of_the_request
   */
  public function setHash_method_makes_and_sets_hash()
  {
    $set_hash_method = $this->getNonPublicMethod('setHash');
    $make_hash_method = $this->getNonPublicMethod('makeHash');

    $set_hash_method->invoke(self::$mysql, $args = ['foo' => 'bar', 'foo2' => 'bar2']);
    $this->assertSame(
      $make_hash_method->invoke(self::$mysql, $args),
      $this->getNonPublicProperty('hash')
    );


    $set_hash_method->invoke(self::$mysql, 'foo', 'bar', 'baz');
    $this->assertSame(
      $make_hash_method->invoke(self::$mysql, 'foo', 'bar', 'baz'),
      $this->getNonPublicProperty('hash')
    );

    $set_hash_method->invoke(self::$mysql, $args = [
      'foo',
      'foo2' => ['bar', 'bar2']
    ]);
    $this->assertSame(
      $make_hash_method->invoke(self::$mysql, $args),
      $this->getNonPublicProperty('hash')
    );

  }

  /**
   * @test
   * @depends setHash_method_makes_and_sets_hash
   */
  public function getHash_method_returns_the_created_hash()
  {
    $set_hash_method = $this->getNonPublicMethod('setHash');
    $make_hash_method = $this->getNonPublicMethod('makeHash');

    $set_hash_method->invoke(self::$mysql, 'foo', 'bar');

    $this->assertSame(
      $make_hash_method->invoke(self::$mysql, 'foo', 'bar'),
      self::$mysql->getHash()
    );
  }

  /** @test */
  public function error_method_sets_an_error_and_acts_based_on_the_error_mode_when_the_given_error_is_string()
  {
    $this->assertFalse($this->getNonPublicProperty('_has_error'));
    $this->assertFalse($this->getNonPublicProperty('_has_error_all'));
    $this->assertNull($this->getNonPublicProperty('last_error'));

    $this->createDir('logs');

    self::$mysql->error('An error');

    $this->assertTrue($this->getNonPublicProperty('_has_error'));
    $this->assertTrue($this->getNonPublicProperty('_has_error_all'));
    $this->assertSame('An error', $this->getNonPublicProperty('last_error'));
    $this->assertFileExists($log_file = $this->getTestingDirName() . 'logs/db.log');
    $this->assertStringContainsString('An error', file_get_contents($log_file));
  }

  /** @test */
  public function error_method_sets_an_error_and_acts_based_on_the_error_mode_when_the_given_error_an_exception()
  {
    $this->assertFalse($this->getNonPublicProperty('_has_error'));
    $this->assertFalse($this->getNonPublicProperty('_has_error_all'));
    $this->assertNull($this->getNonPublicProperty('last_error'));

    $this->createDir('logs');

    $this->setNonPublicPropertyValue('last_real_params', [
      'values' => [true, false, 'An error in params']
    ]);

    self::$mysql->error(new \Exception('An error'));

    $this->assertTrue($this->getNonPublicProperty('_has_error'));
    $this->assertTrue($this->getNonPublicProperty('_has_error_all'));
    $this->assertSame(
      'An error',
      $this->getNonPublicProperty('last_error')
    );
    $this->assertFileExists($log_file = $this->getTestingDirName() . 'logs/db.log');
    $this->assertStringContainsString(
      'An error',
      $log_file_contents = file_get_contents($log_file)
    );
    $this->assertStringContainsString('VALUES', $log_file_contents);
    $this->assertStringContainsString('An error in params', $log_file_contents);
    $this->assertStringContainsString('TRUE', $log_file_contents);
    $this->assertStringContainsString('FALSE', $log_file_contents);
  }

  /** @test */
  public function error_method_should_throw_an_exception_when_mode_is_to_die()
  {
    $this->expectException(\Exception::class);
    $this->setNonPublicPropertyValue('on_error', 'die');

    self::$mysql->error('An error');
  }

  /** @test */
  public function check_method_checks_if_the_database_is_ready_to_process_a_query()
  {
    $this->assertTrue(self::$mysql->check());
  }

  /** @test */
  public function check_method_returns_true_if_there_is_an_error_the_error_mode_is_continue()
  {
    $this->setNonPublicPropertyValue('on_error', 'continue');
    $this->setNonPublicPropertyValue('_has_error', true);
    $this->setNonPublicPropertyValue('_has_error_all', true);

    $this->assertTrue(self::$mysql->check());
  }

  /** @test */
  public function check_method_returns_false_if_there_is_are_error_for_all_connection_and_mode_is_stop_all()
  {
    $this->setNonPublicPropertyValue('_has_error_all', true);
    $this->setNonPublicPropertyValue('on_error', 'stop_all');

    $this->assertFalse(self::$mysql->check());
  }

  /** @test */
  public function check_method_returns_true_if_there_is_are_error_for_all_connection_and_mode_is_not_stop_all()
  {
    $this->setNonPublicPropertyValue('_has_error_all', true);
    $this->setNonPublicPropertyValue('on_error', 'stop');

    $this->assertTrue(self::$mysql->check());
  }

  /** @test */
  public function check_method_returns_false_if_there_is_error_for_the_current_connection_and_mode_is_stop()
  {
    $this->setNonPublicPropertyValue('_has_error', true);
    $this->setNonPublicPropertyValue('on_error', 'stop');

    $this->assertFalse(self::$mysql->check());
  }

  /** @test */
  public function check_method_returns_false_if_there_is_error_for_the_current_connection_and_mode_is_stop_all()
  {
    $this->setNonPublicPropertyValue('_has_error', true);
    $this->setNonPublicPropertyValue('on_error', 'stop_all');

    $this->assertFalse(self::$mysql->check());
  }

  /** @test */
  public function check_method_returns_false_when_the_current_connection_is_null()
  {
    $old_current = $this->getNonPublicProperty('current');

    $this->setNonPublicPropertyValue('current', null);

    $this->assertFalse(self::$mysql->check());

    $this->setNonPublicPropertyValue('current', $old_current);
  }

  /** @test */
  public function setErrorMode_method_sets_the_error_mode()
  {
    $result = self::$mysql->setErrorMode('stop_all');

    $this->assertSame(
      'stop_all',
      $this->getNonPublicProperty('on_error')
    );

    $this->assertInstanceOf(Mysql::class, $result);
  }

  /** @test */
  public function getErrorMode_method_returns_the_current_error_mode()
  {
    $this->setNonPublicPropertyValue('on_error', 'stop');

    $this->assertSame(Errors::E_STOP, self::$mysql->getErrorMode());
  }

  /** @test */
  public function getLogLine_method_returns_a_string_with_given_text_in_the_middle_of_a_line_of_logs()
  {
    $this->assertSame(
      '-------------------------------------- foo --------------------------------------',
      self::$mysql::getLogLine('foo')
    );

    $this->assertSame(
      '--------------------------------- I\'m an error ----------------------------------',
      self::$mysql::getLogLine('I\'m an error')
    );
  }

  /** @test */
  public function getHost_method_returns_the_host_of_the_current_connection()
  {
    $this->assertSame($this->getDbConfig()['host'], self::$mysql->getHost());
  }

  /** @test */
  public function getCurrent_method_returns_the_current_database_of_the_current_connection()
  {
    $this->assertSame($this->getDbConfig()['db'], self::$mysql->getCurrent());
  }

  /** @test */
  public function getLastError_method_returns_the_last_error()
  {
    $this->assertNull(self::$mysql->getLastError());

    $this->setNonPublicPropertyValue('last_error', 'Error');

    $this->assertSame('Error', self::$mysql->getLastError());
  }

  /** @test */
  public function change_method_changes_the_database_to_the_given_one()
  {
    $this->assertSame($this->getDbConfig()['db'], $this->getNonPublicProperty('current'));

    $result = self::$mysql->change('bbn_test_2');

    $this->assertSame('bbn_test_2', $this->getNonPublicProperty('current'));
    $this->assertTrue($result);

    self::$mysql->change('bbn_test');
  }

  /** @test */
  public function change_method_does_not_change_the_database_if_language_object_fails_to_change()
  {
    $this->assertSame($this->getDbConfig()['db'], $this->getNonPublicProperty('current'));

    try {
      self::$mysql->change('bbn_test_3');
    } catch (\Exception $e) {

    }

    $this->assertSame($this->getDbConfig()['db'], $this->getNonPublicProperty('current'));
  }

  /** @test */
  public function set_has_error_all_sets_errors_on_all_connections_to_true()
  {
    $this->setNonPublicPropertyValue('_has_error_all', false);

    $method = $this->getNonPublicMethod('_set_has_error_all');
    $method->invoke(self::$mysql);

    $this->assertTrue($this->getNonPublicProperty('_has_error_all'));
  }

  /** @test */
  public function getEngine_method_returns_engines_name()
  {
    $this->assertSame('mysql', self::$mysql->getEngine());
  }
}