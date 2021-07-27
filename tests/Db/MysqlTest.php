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
    $this->setNonPublicPropertyValue('on_error', Errors::E_STOP);
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

  //  /** @test */
//  public function isAggregateFunction_method_returns_true_if_the_given_name_is_aggregate_function()
//  {
//    $this->assertTrue(Mysql::isAggregateFunction('count'));
//  }

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
  public function constructor_throws_an_exception_when_fails_to_connect_to_database()
  {
    $this->expectException(\Exception::class);

    $db_config = self::getDbConfig();

    $db_config['db'] = 'bbn_test_dummy';

    new Mysql($db_config);
  }

  /** @test */
  public function constructor_throws_an_exception_when_host_is_not_provided_and_BBN_DB_HOST_is_not_defined()
  {
    $this->expectException(\Exception::class);

    $db_config = self::getDbConfig();

    unset($db_config['host']);

    new Mysql($db_config);
  }

  /** @test */
  public function constructor_throws_an_exception_when_user_is_not_provided_and_BBN_DB_HOST_is_not_defined()
  {
    $this->expectException(\Exception::class);

    $db_config = self::getDbConfig();

    unset($db_config['user']);

    new Mysql($db_config);
  }

  /** @test */
  public function getHost_method_returns_the_host()
  {
    $this->assertSame(self::getDbConfig()['host'], self::$mysql->getHost());
  }

  /** @test */
  public function getConnectionCode_method_returns_connection_code()
  {
    $cfg = self::getDbConfig();

    $this->assertSame(
      "{$cfg['user']}@{$cfg['host']}",
      self::$mysql->getConnectionCode()
    );
  }

  /** @test */
  public function getCfg_method_returns_the_config()
  {
    $db_cfg = self::getDbConfig();

    $this->assertSame(
      array_merge($db_cfg, [
        'port'      => 3306,
        'code_db'   => 'bbn_test',
        'code_host' => "{$db_cfg['user']}@{$db_cfg['host']}"
      ]),
      self::$mysql->getCfg()
    );
  }

  /** @test */
  public function disableKeys_method_disables_foreign_keys_check()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('rawQuery')
      ->once()
      ->with('SET FOREIGN_KEY_CHECKS=0;');

    $this->assertInstanceOf(Mysql::class, $mysql->disableKeys());
  }

  /** @test */
  public function enableKeys_method_enables_foreign_keys_check()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('rawQuery')
      ->once()
      ->with('SET FOREIGN_KEY_CHECKS=1;');

    $this->assertInstanceOf(Mysql::class, $mysql->enableKeys());
  }
  
  /** @test */
  public function getConditions_method_returns_a_string_with_conditions_for_the_where_or_on_or_having_clauses()
  {
    $conditions = [
      'conditions' => [
        [
          'conditions' => [
            [
              'field'     => 'id',
              'operator'  => '=',
              'value'     => 'aaaa2c70aaaaa2aaa47652540000aaaa'
            ],
            [
              'field' => 'name',
              'value' => 'john',
              'operator'  => '=',
            ],
            [
              'logic' => 'OR',
              'conditions' => [
                [
                  'field' => 'created_at',
                  'operator' => 'isnull'
                ], [
                  'field' => 'updated_at',
                  'operator' => 'isnull'
                ]
              ]
            ]
          ],
          'logic' => 'AND'
        ]
      ],
      'logic' => 'AND',
    ];

    $cfg = [
      'available_fields' => [
        'id' => 'users'
      ],
      'models' => [
        'users' => [
          'fields' => [
            'id' => [
              'type' => 'binary',
              'key'   => 'foo',
              'maxlength' => 16,
              'null' => false
            ]
          ]
        ]
      ]
    ];

    $expected = <<<RESULT
(
  `users`.`id` = ?
  AND name = ?
  AND (created_at IS NULL
    OR updated_at IS NULL
  )
)

RESULT;

      $this->assertSame($expected, self::$mysql->getConditions($conditions, $cfg));
  }

  /** @test */
  public function createIndex_method_created_index_for_the_givens_table_and_columns()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $expected_query = "CREATE UNIQUE INDEX `users_email` ON `bbn_test`.`users` ( `email` )";

    $mysql->shouldReceive('query')
      ->once()
      ->with($expected_query)
      ->andReturnTrue();

    $mysql->shouldReceive('getCurrent')
      ->once()
      ->withNoArgs()
      ->andReturn(self::$mysql->getCurrent());

    $result = $mysql->createIndex('users', 'email', true);

    $this->assertTrue($result);

    // Another test
    $expected_query2 = "CREATE INDEX `users_email` ON `bbn_test`.`users` ( `email`(20) )";

    $mysql->shouldReceive('query')
      ->once()
      ->with($expected_query2)
      ->andReturnTrue();

    $mysql->shouldReceive('getCurrent')
      ->once()
      ->withNoArgs()
      ->andReturn(self::$mysql->getCurrent());

    $result2 = $mysql->createIndex('users', 'email', false, 20);

    $this->assertTrue($result2);
  }

  /** @test */
  public function createIndex_method_throws_an_exception_when_column_has_a_not_valid_name_and_mode_id_die()
  {
    $this->expectException(\Exception::class);

    self::$mysql->setErrorMode(Errors::E_DIE);
    self::$mysql->createIndex('users', 'use*rs');
  }

  /** @test */
  public function deleteIndex_method_deletes_the_given_index()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $expected_query = "ALTER TABLE `bbn_test`.`users` DROP INDEX `email`";

    $mysql->shouldReceive('query')
      ->once()
      ->with($expected_query)
      ->andReturnTrue();

    $mysql->shouldReceive('getCurrent')
      ->once()
      ->withNoArgs()
      ->andReturn(self::$mysql->getCurrent());

    $result = $mysql->deleteIndex('users', 'email');

    $this->assertTrue($result);
  }

  /** @test */
  public function dropIndex_method_returns_false_when_the_given_key_has_a_not_valid_name()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('tableFullName')
      ->once()
      ->with('users' ,true)
      ->andReturnNull();

    $this->assertFalse(
      $mysql->deleteIndex('users', 'email')
    );
  }
  /** @test */
  public function dropIndex_method_returns_false_when_table_full_name_cannot_be_retrieved()
  {
    $this->assertFalse(
      self::$mysql->deleteIndex('users', 'ema*ail')
    );
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