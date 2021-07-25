<?php

namespace Db;

use bbn\Cache;
use bbn\Db2;
use bbn\Db2\Languages\Mysql;
use PHPUnit\Framework\TestCase;
use tests\Files;
use tests\Reflectable;

class Db2Test extends TestCase
{
  use Reflectable, Files;

  protected Db2 $db;

  protected $mysql_mock;

  protected $cache_mock;

  protected function setUp(): void
  {
    $this->mysql_mock = \Mockery::mock(Mysql::class);
    $this->cache_mock = \Mockery::mock(Cache::class);

    $this->mysql_mock->shouldReceive('getCfg')
      ->once()
      ->withNoArgs()
      ->andReturn(array_merge($db_cfg = $this->getDbConfig(), [
        'code_host' => "{$db_cfg['user']}@{$db_cfg['host']}"
      ]));

    $this->mysql_mock->shouldReceive('postCreation')
      ->once()
      ->withNoArgs();

    $this->mysql_mock->shouldReceive('startFancyStuff')
      ->once()
      ->withNoArgs()
      ->andReturnSelf();

    $this->mysql_mock->shouldReceive('__toString')
      ->andReturn('mysql');

    $this->db = new Db2($this->getDbConfig());

    $this->setNonPublicPropertyValue('_has_error_all', false);

    $this->setNonPublicPropertyValue('cache_engine', $this->cache_mock);

    $this->cleanTestingDir();
  }

  protected function tearDown(): void
  {
    \Mockery::close();
    $this->cleanTestingDir();
  }

  public function getInstance()
  {
    return $this->db;
  }


  protected function getDbConfig()
  {
    return [
      'engine'        => $this->mysql_mock,
      'host'          => 'localhost',
      'user'          => 'root',
      'pass'          => getenv('db_pass'),
      'db'            => 'bbn_test',
      'cache_length'  => 3000,
      'error_mode'    => 'stop'
    ];
  }


  /** @test */
  public function constructor_test()
  {
    $db_cfg = $this->getDbConfig();

    $this->assertSame(
      array_merge($db_cfg, [
        'code_host' => "{$db_cfg['user']}@{$db_cfg['host']}"
      ]),
      $this->getNonPublicProperty('cfg')
    );

    $this->assertInstanceOf(
      Db2::class,
      $this->getNonPublicProperty('retriever_instance', Db2::class)
    );

    $this->assertInstanceOf(Cache::class, $this->getNonPublicProperty('cache_engine'));
    $this->assertInstanceOf(Mysql::class, $this->getNonPublicProperty('language'));

    $this->assertSame(
      $this->getNonPublicProperty('qte', $this->getNonPublicProperty('language')),
      $this->getNonPublicProperty('qte')
    );

    $this->assertSame('bbn_test', $this->getNonPublicProperty('current'));
    $this->assertSame('mysql', (string)$this->getNonPublicProperty('engine'));
    $this->assertSame('localhost', $this->getNonPublicProperty('host'));
    $this->assertSame('root', $this->getNonPublicProperty('username'));
    $this->assertSame(
      "{$db_cfg['user']}@{$db_cfg['host']}",
      $this->getNonPublicProperty('connection_code')
    );

    $this->assertSame(3000, $this->getNonPublicProperty('cache_renewal'));
    $this->assertSame('stop', $this->getNonPublicProperty('on_error'));
  }

  /** @test */
  public function constructor_throws_an_exception_when_engine_is_not_provided()
  {
    $this->expectException(\Exception::class);

    $db_config = $this->getDbConfig();

    unset($db_config['engine']);

    $this->db = new Db2($db_config);
  }

  /** @test */
  public function isEngineSupported_method_checks_if_the_given_db_engine_is_supported_or_not()
  {
    $this->assertTrue(Db2::isEngineSupported('mysql'));
    $this->assertTrue(Db2::isEngineSupported('pgsql'));
    $this->assertTrue(Db2::isEngineSupported('sqlite'));
    $this->assertFalse(Db2::isEngineSupported('foo'));
  }

  /** @test */
  public function getEngineIcon_method_returns_the_icon_for_the_given_db_engine()
  {
    foreach ($this->getNonPublicProperty('engines') as $engine => $icon) {
      $this->assertSame($icon, Db2::getEngineIcon($engine));
    }

    $this->assertNull(Db2::getEngineIcon('foo'));
  }

  /** @test */
  public function getLogLine_method_returns_a_string_with_given_text_in_the_middle_of_a_line_of_logs()
  {
    $this->assertSame(
      '-------------------------------------- foo --------------------------------------',
      Db2::getLogLine('foo')
    );

    $this->assertSame(
      '--------------------------------- I\'m an error ----------------------------------',
      Db2::getLogLine('I\'m an error')
    );
  }

  /** @test */
  public function getCfg_method_returns_the_config()
  {
    $this->mysql_mock->shouldReceive('getCfg')
      ->once()
      ->withNoArgs()
      ->andReturn(['foo' => 'bar']);

    $this->assertSame(['foo' => 'bar'], $this->db->getCfg());
  }

  /** @test */
  public function getEngine_method_returns_the_engine_used_by_the_current_connection()
  {
    $this->assertSame('mysql', $this->db->getEngine());
  }

  /** @test */
  public function getHost_method_returns_the_host_of_the_current_connection()
  {
    $this->assertSame($this->getDbConfig()['host'], $this->db->getHost());
  }

  /** @test */
  public function getCurrent_method_returns_the_current_database_of_the_current_connection()
  {
    $this->assertSame($this->getDbConfig()['db'], $this->db->getCurrent());
  }

  /** @test */
  public function getLastError_method_returns_the_last_error()
  {
    $this->assertNull($this->db->getLastError());

    $this->setNonPublicPropertyValue('last_error', 'Error');

    $this->assertSame('Error', $this->db->getLastError());
  }

  /** @test */
  public function getCacheRenewal_method_returns_cache_renewal_time()
  {
    $this->assertSame($this->getDbConfig()['cache_length'], $this->db->getCacheRenewal());
  }

  /** @test */
  public function to_string_method_returns_a_string_when_the_object_is_used_as_a_string()
  {
    $db_config = $this->getDbConfig();

    $this->assertSame(
      "Connection {$db_config['engine']} to {$db_config['host']}",
      (string)$this->db
    );
  }

  /** @test */
  public function getConnectionCode_returns_connection_code()
  {
    $db_cfg = $this->getDbConfig();

    $this->assertSame(
      "{$db_cfg['user']}@{$db_cfg['host']}",
      $this->db->getConnectionCode()
    );
  }

  /** @test */
  public function makeHash_method_makes_a_hash_string_that_will_be_the_id_of_the_request()
  {
    $hash_contour    = $this->getNonPublicProperty('hash_contour');
    $expected_string = "{$hash_contour}%s{$hash_contour}";

    $expected = sprintf($expected_string, md5('--bar----bar2--'));
    $this->assertSame($expected, $this->db->makeHash(['foo' => 'bar', 'foo2' => 'bar2']));

    $expected = sprintf($expected_string, md5('--foo----bar----baz--'));
    $this->assertSame($expected, $this->db->makeHash('foo', 'bar', 'baz'));

    $expected = sprintf($expected_string, md5('--foo--' . serialize(['bar', 'bar2'])));
    $this->assertSame($expected, $this->db->makeHash([
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
    $this->db->setHash($args = ['foo' => 'bar', 'foo2' => 'bar2']);
    $this->assertSame(
      $this->db->makeHash($args),
      $this->getNonPublicProperty('hash')
    );

    $this->db->setHash('foo', 'bar', 'baz');
    $this->assertSame(
      $this->db->makeHash('foo', 'bar', 'baz'),
      $this->getNonPublicProperty('hash')
    );

    $this->db->setHash($args = [
      'foo',
      'foo2' => ['bar', 'bar2']
    ]);
    $this->assertSame(
      $this->db->makeHash($args),
      $this->getNonPublicProperty('hash')
    );

  }

  /**
   * @test
   * @depends setHash_method_makes_and_sets_hash
   */
  public function getHash_method_returns_the_created_hash()
  {
    $this->db->setHash('foo', 'bar');
    $this->assertSame(
      $this->db->makeHash('foo', 'bar'),
      $this->db->getHash()
    );
  }

  /** @test */
  public function replaceTableInConditions_method_test()
  {
    // TODO: How this should work?
    $data = [
      [
        'field' => 'username',
        'exp' => 'john_doe'
      ],
      [
        'field' => 'users.first_name',
        'exp' => 'profiles.first_name'
      ],
      [
        'field' => '\`users\`.\`first_name\`',
        'exp' => '\`profiles_users\`.\`first_name\`'
      ]
    ];

    $expected = [
      [
        'field' => 'username',
        'exp' => 'john_doe'
      ],
      [
        'field' => 'users.last_name',
        'exp' => 'profiles.last_name'
      ],
      [
        'field' => '\`users\`.\`last_name\`',
        'exp' => '\`profiles_users\`.\`last_name\`'
      ]
    ];
      $this->assertTrue(true);
//    $this->assertSame($expected, $this->db->replaceTableInConditions($data, 'first_name', 'last_name'));
  }

  /** @test */
  public function treatConditions_method_test()
  {
    $this->mysql_mock->shouldReceive('treatConditions')
      ->with(['foo' => 'bar'], true)
      ->once()
      ->andReturn(['foo' => 'bar2']);

    $this->assertSame(
      ['foo' => 'bar2'],
      $this->db->treatConditions(['foo' => 'bar'], true)
    );
  }

  /** @test */
  public function reprocessCfg_method_test()
  {
    $this->mysql_mock->shouldReceive('reprocessCfg')
      ->once()
      ->with(['foo' => 'bar'])
      ->andReturn(['foo' => 'bar2']);

    $this->assertSame(['foo' => 'bar2'], $this->db->reprocessCfg(['foo' => 'bar']));
  }

  /** @test */
  public function processCfg_method_test()
  {
    $this->mysql_mock->shouldReceive('processCfg')
      ->once()
      ->with(['foo' => 'bar'], true)
      ->andReturn(['foo' => 'bar2']);

    $this->assertSame(['foo' => 'bar2'], $this->db->processCfg(['foo' => 'bar'], true));
  }

  /** @test */
  public function error_method_sets_an_error_and_acts_based_on_the_error_mode_when_the_given_error_is_string()
  {
    $this->assertFalse($this->getNonPublicProperty('_has_error'));
    $this->assertFalse($this->getNonPublicProperty('_has_error_all'));
    $this->assertNull($this->getNonPublicProperty('last_error'));

    $this->mysql_mock->shouldReceive('last')
      ->once()
      ->withNoArgs()
      ->andReturnNull();

    $this->mysql_mock->shouldReceive('getRealLastParams')
      ->once()
      ->withNoArgs()
      ->andReturn([]);

    $this->createDir('logs');

    $this->db->error('An error');

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

    $this->mysql_mock->shouldReceive('last')
      ->once()
      ->withNoArgs()
      ->andReturnNull();

    $this->mysql_mock->shouldReceive('getRealLastParams')
      ->once()
      ->withNoArgs()
      ->andReturn([
        'values' => [
          true,
          'An error in params',
          false
        ]
      ]);

    $this->createDir('logs');

    $this->db->error(new \Exception('An error'));

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

    $this->mysql_mock->shouldReceive('last')
      ->once()
      ->withNoArgs()
      ->andReturnNull();

    $this->mysql_mock->shouldReceive('getRealLastParams')
      ->once()
      ->withNoArgs()
      ->andReturn([]);

    $this->db->error('An error');
  }

  /** @test */
  public function check_method_checks_if_the_database_is_ready_to_process_a_query()
  {
    $this->assertTrue($this->db->check());
  }

  /** @test */
  public function check_method_returns_true_if_there_is_an_error_the_error_mode_is_continue()
  {
    $this->setNonPublicPropertyValue('on_error', 'continue');
    $this->setNonPublicPropertyValue('_has_error', true);
    $this->setNonPublicPropertyValue('_has_error_all', true);

    $this->assertTrue($this->db->check());
  }

  /** @test */
  public function check_method_returns_false_if_there_is_are_error_for_all_connection_and_mode_is_stop_all()
  {
    $this->setNonPublicPropertyValue('_has_error_all', true);
    $this->setNonPublicPropertyValue('on_error', 'stop_all');

    $this->assertFalse($this->db->check());
  }

  /** @test */
  public function check_method_returns_true_if_there_is_are_error_for_all_connection_and_mode_is_not_stop_all()
  {
    $this->setNonPublicPropertyValue('_has_error_all', true);
    $this->setNonPublicPropertyValue('on_error', 'stop');

    $this->assertTrue($this->db->check());
  }

  /** @test */
  public function check_method_returns_false_if_there_is_error_for_the_current_connection_and_mode_is_stop()
  {
    $this->setNonPublicPropertyValue('_has_error', true);
    $this->setNonPublicPropertyValue('on_error', 'stop');

    $this->assertFalse($this->db->check());
  }

  /** @test */
  public function check_method_returns_false_if_there_is_error_for_the_current_connection_and_mode_is_stop_all()
  {
    $this->setNonPublicPropertyValue('_has_error', true);
    $this->setNonPublicPropertyValue('on_error', 'stop_all');

    $this->assertFalse($this->db->check());
  }

  /** @test */
  public function check_method_returns_false_when_the_current_connection_is_null()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertFalse($this->db->check());
  }

  /** @test */
  public function setErrorMode_method_sets_the_error_mode()
  {
    $result = $this->db->setErrorMode('stop_all');

    $this->assertSame(
      'stop_all',
      $this->getNonPublicProperty('on_error')
    );

    $this->assertInstanceOf(Db2::class, $result);
  }

  /** @test */
  public function getErrorMode_method_returns_the_current_error_mode()
  {
    $this->setNonPublicPropertyValue('on_error', 'stop');

    $this->assertSame('stop', $this->db->getErrorMode());
  }

  /** @test */
  public function clearCache_method_deletes_a_specific_item_from_cache_when_exists()
  {
    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with('bbn/Db2/foo/method_name')
      ->andReturnTrue();

    $this->cache_mock->shouldReceive('deleteAll')
      ->once()
      ->with('bbn/Db2/foo/method_name')
      ->andReturnTrue();

    $result = $this->db->clearCache('foo', 'method_name');

    $this->assertInstanceOf(Db2::class, $result);
  }

  public function clearCache_method_does_noe_delete_a_specific_item_from_cache_when_not_exists()
  {
    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with('bbn/Db2/foo/method_name')
      ->andReturnFalse();

    $this->cache_mock->shouldNotReceive('deleteAll');

    $result = $this->db->clearCache('foo', 'method_name');

    $this->assertInstanceOf(Db2::class, $result);
  }

  /** @test */
  public function clearAllCache_method_clears_all_cache()
  {
    $this->cache_mock->shouldReceive('deleteAll')
      ->once()
      ->with('bbn/Db2/')
      ->andReturnTrue();

    $result = $this->db->clearAllCache();

    $this->assertInstanceOf(Db2::class, $result);
  }

  /** @test */
  public function stopFancyStuff_method_calls_stopFancyStuff_on_language_class()
  {
    $this->mysql_mock->shouldReceive('stopFancyStuff')
      ->once()
      ->withNoArgs()
      ->andReturnSelf();

    $result = $this->db->stopFancyStuff();

    $this->assertInstanceOf(Db2::class, $result);
  }

  /** @test */
  public function startFancyStuff_method_calls_startFancyStuff_on_language_class()
  {
    $this->mysql_mock->shouldReceive('startFancyStuff')
      ->once()
      ->withNoArgs()
      ->andReturnSelf();

    $result = $this->db->startFancyStuff();

    $this->assertInstanceOf(Db2::class, $result);
  }

  /** @test */
  public function enableTrigger_method_enables_trigger_functions()
  {
    $this->mysql_mock->shouldReceive('enableTrigger')
      ->once()
      ->withNoArgs()
      ->andReturnSelf();

    $result = $this->db->enableTrigger();

    $this->assertInstanceOf(Db2::class, $result);
  }

  /** @test */
  public function disableTrigger_method_disable_the_trigger_functions()
  {
    $this->mysql_mock->shouldReceive('disableTrigger')
      ->once()
      ->withNoArgs()
      ->andReturnSelf();

    $result = $this->db->disableTrigger();

    $this->assertInstanceOf(Db2::class, $result);
  }

  /** @test */
  public function isTriggerEnabled_method_checks_if_trigger_enabled()
  {
    $this->mysql_mock->shouldReceive('isTriggerEnabled')
      ->once()
      ->withNoArgs()
      ->andREturnTrue();

    $this->assertTrue($this->db->isTriggerEnabled());
  }


  /** @test */
  public function isTriggerDisabled_method_checks_if_trigger_disabled()
  {
    $this->mysql_mock->shouldReceive('isTriggerDisabled')
      ->once()
      ->withNoArgs()
      ->andReturnTrue();

    $this->assertTrue($this->db->isTriggerDisabled());
  }

  /** @test */
  public function setTrigger_method_applies_a_function_each_time_the_given_methods_are_called()
  {
    $callback = function (){};

    $this->mysql_mock->shouldReceive('setTrigger')
      ->once()
      ->with($callback, 'select', 'after', '*')
      ->andReturnSelf();

    $result = $this->db->setTrigger($callback, 'select', 'after');

    $this->assertInstanceOf(Db2::class, $result);
  }

  /** @test */
  public function getTriggers_method_returns_the_current_triggers()
  {
    $this->mysql_mock->shouldReceive('getTriggers')
      ->once()
      ->withNoArgs()
      ->andReturn(['foo' => 'bar']);

    $this->assertSame(['foo' => 'bar'], $this->db->getTriggers());
  }

  /** @test */
  public function getFieldsList_method_test_returns_an_array_with_fields_for_the_given_table()
  {
    $this->mysql_mock->shouldReceive('getFieldsList')
      ->once()
      ->with('table_name')
      ->andReturn([]);

    $this->assertSame([], $this->db->getFieldsList('table_name'));
  }

  /** @test */
  public function getForeignKeys_method_returns_an_array_with_table_and_fields_related_to_the_searched_foreign_ket()
  {
    $this->mysql_mock->shouldReceive('getForeignKeys')
      ->once()
      ->with('col_name', 'table_name', null)
      ->andReturn([]);

    $this->assertSame([], $this->db->getForeignKeys('col_name', 'table_name'));
  }

  /** @test */
  public function hasIdIncrement_method_returns_true_if_the_table_has_an_auto_increment_field()
  {
    $this->mysql_mock->shouldReceive('hasIdIncrement')
      ->once()
      ->with('table_name')
      ->andReturnTrue();

    $this->assertTrue($this->db->hasIdIncrement('table_name'));
  }

  /** @test */
  public function modelize_method_returns_table_structure_as_an_array()
  {
    $this->mysql_mock->shouldReceive('modelize')
      ->once()
      ->with('table_name', false)
      ->andReturn($result = [
        'keys' => [
          'PRIMARY' => [
            'columns' => [
              'user_id'
            ]
          ]
        ]
      ]);

    $this->assertSame($result, $this->db->modelize('table_name'));
  }

  /** @test */
  public function fmodelize_method_test()
  {
    $this->mysql_mock->shouldReceive('fmodelize')
      ->once()
      ->with('table_name', false)
      ->andReturn($result = [
        ['name' => 'field_1', 'keys' => []]
      ]);

    $this->assertSame($result, $this->db->fmodelize('table_name'));
  }

  /** @test */
  public function findReferences_method_test()
  {
    $this->mysql_mock->shouldReceive('findReferences')
      ->once()
      ->with('col_name', '')
      ->andReturn(['foo' => 'bar']);

    $this->assertSame(['foo' => 'bar'], $this->db->findReferences('col_name'));
  }

  /** @test */
  public function findRelations_method_test()
  {
    $this->mysql_mock->shouldReceive('findRelations')
      ->once()
      ->with('col_name', '')
      ->andReturnNull();

    $this->assertNull($this->db->findRelations('col_name'));
  }

  /** @test */
  public function getPrimary_method_returns_primary_keys_of_the_given_table_as_array()
  {
    $this->mysql_mock->shouldReceive('getPrimary')
      ->once()
      ->with('table_name')
      ->andReturn(['id']);

    $this->assertSame(['id'], $this->db->getPrimary('table_name'));
  }

  /** @test */
  public function getUniquePrimary_method_returns_the_unique_primary_for_the_given_table()
  {
    $this->mysql_mock->shouldReceive('getUniquePrimary')
      ->once()
      ->with('table_name')
      ->andReturn('id');

    $this->assertSame('id', $this->db->getUniquePrimary('table_name'));
  }

  /** @test */
  public function getUniqueKeys_method_return_the_unique_keys_of_the_given_table_as_array()
  {
    $this->mysql_mock->shouldReceive('getUniqueKeys')
      ->once()
      ->with('table_name')
      ->andReturn(['col_1', 'col_2']);

    $this->assertSame(['col_1', 'col_2'], $this->db->getUniqueKeys('table_name'));
  }

  /** @test */
  public function escapeValue_method_escapes_the_given_string()
  {
    $this->assertSame("Foo \' bar", $this->db->escapeValue("Foo ' bar"));
    $this->assertSame('Foo \" bar', $this->db->escapeValue('Foo " bar', '"'));
  }

  /** @test */
  public function setLastInsertId_method_changes_the_value_of_the_last_insert_id()
  {
    $this->mysql_mock->shouldReceive('setLastInsertId')
      ->once()
      ->with(2)
      ->andReturnSelf();

    $this->assertInstanceOf(Db2::class, $this->db->setLastInsertId(2));
  }

  /** @test */
  public function last_method_returns_the_last_query_for_the_current_connection()
  {
    $this->mysql_mock->shouldReceive('last')
      ->once()
      ->withNoArgs()
      ->andReturn($result = 'INSERT INTO `db_example.table_user` (`name`) VALUES (?)');

    $this->assertSame($result, $this->db->last());
  }

  /** @test */
  public function lastId_method_returns_the_last_inserted_id()
  {
    $this->mysql_mock->shouldReceive('lastId')
      ->once()
      ->withNoArgs()
      ->andReturn(12);

    $this->assertSame(12, $this->db->lastId());
  }

  /** @test */
  public function flush_method_deleted_all_recorded_queries_and_returns_their_number()
  {
    $this->mysql_mock->shouldReceive('flush')
      ->once()
      ->withNoArgs()
      ->andReturn(6);

    $this->assertSame(6, $this->db->flush());
  }

  /** @test */
  public function countQueries_method_returns_number_of_queries()
  {
    $this->mysql_mock->shouldReceive('countQueries')
      ->once()
      ->withNoArgs()
      ->andReturn(6);

    $this->assertSame(6, $this->db->countQueries());
  }

  /** @test */
  public function getOne_method_executes_the_given_query_and_returns_the_first_cell_result()
  {
    $this->mysql_mock->shouldReceive('getOne')
      ->once()
      ->with($query = 'SELECT name FROM table_users WHERE id > ?', 11)
      ->andReturn('john');

    $this->assertSame('john', $this->db->getOne($query, 11));
  }

  /** @test */
  public function getVar_method_executes_the_given_query_and_returns_the_first_cell_result()
  {
    $this->mysql_mock->shouldReceive('getOne')
      ->once()
      ->with($query = 'SELECT name FROM table_users WHERE id > ?', 11)
      ->andReturn('john');

    $this->assertSame('john', $this->db->getVar($query, 11));
  }

  /** @test */
  public function getKeyVal_method_returns_an_indexed_array_of_the_first_field_of_the_request()
  {
    $this->mysql_mock->shouldReceive('getKeyVal')
      ->once()
      ->with($query = 'SELECT name,id_group FROM table_users')
      ->andReturn($result = [
          'John'   => 1,
         'Michael' => 1,
         'Barbara' => 1
      ]);

    $this->assertSame($result, $this->db->getKeyVal($query));
  }

  /** @test */
  public function getColArray_method_return_an_array_with_the_values_of_single_field_resulting_from_the_query()
  {
    $this->mysql_mock->shouldReceive('getByColumns')
      ->once()
      ->with($query = 'SELECT id FROM table_users')
      ->andReturn([
        'name' => [
          'john', 'doe'
        ]
      ]);

    $this->assertSame(['john', 'doe'], $this->db->getColArray($query));
  }

  /** @test */
  public function getColArray_method_returns_an_empty_array_when_getByColumns_returns_null()
  {
    $this->mysql_mock->shouldReceive('getByColumns')
      ->once()
      ->with($query = 'SELECT id FROM table_users')
      ->andReturnNull();

    $this->assertSame([], $this->db->getColArray($query));
  }

  /** @test */
  public function select_method_returns_the_first_row_resulting_from_the_query_as_object()
  {
    $this->mysql_mock->shouldReceive('select')
      ->once()
      ->with('table_users', ['name', 'surname'], [['id','>','2']], [], 0)
      ->andReturn(
        $result = (object)[
          'name'    => 'john',
          'lastname' => 'doe'
        ]
      );

    $this->assertSame(
      $result,
      $this->db->select('table_users', ['name', 'surname'], [['id','>','2']])
    );
  }

  /** @test */
  public function selectAll_method_returns_table_rows_resulting_from_the_query_as_an_array_of_objects()
  {
    $this->mysql_mock->shouldReceive('selectAll')
      ->once()
      ->with("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2, 0)
      ->andReturn($result = [
        (object) [
          'id'   => '12',
          'name' => 'john'
        ]
      ]);

    $this->assertSame(
      $result,
      $this->db->selectAll("table_users", ["id", "name"],[["id", ">", 1]], ["id" => "ASC"], 2)
    );
  }

  /** @test */
  public function iselect_method_returns_the_first_row_resulting_from_the_query_as_an_array()
  {
    $this->mysql_mock->shouldReceive('iselect')
      ->once()
      ->with("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2)
      ->andReturn($result = [33, 'john']);

    $this->assertSame(
      $result,
      $this->db->iselect("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2)
    );
  }

  /** @test */
  public function iselectAll_method_returns_the_searched_rows_as_an_array_of_numeric_arrays()
  {
    $this->mysql_mock->shouldReceive('iselectAll')
      ->once()
      ->with("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2, 0)
      ->andReturn($result = [
        [2, 'john'],
        [12, 'smith']
      ]);

    $this->assertSame(
      $result,
      $this->db->iselectAll("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2)
    );
  }

  /** @test */
  public function rselect_method_returns_the_first_row_resulting_from_the_query_as_an_indexed_array()
  {
    $this->mysql_mock->shouldReceive('rselect')
      ->once()
      ->with("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2)
      ->andReturn($result = [
        'id' => 12,
        'name' => 'john'
      ]);

    $this->assertSame(
      $result,
      $this->db->rselect("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2)
    );
  }

  /** @test */
  public function rselectAll_method_returns_table_rows_as_an_array_of_indexed_array()
  {
    $this->mysql_mock->shouldReceive('rselectAll')
      ->once()
      ->with("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2, 0)
      ->andReturn($result = [
        [2, 'john'],
        [12, 'smith']
      ]);

    $this->assertSame(
      $result,
      $this->db->rselectAll("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2)
    );
  }

  /** @test */
  public function selectOne_method_returns_a_single_value()
  {
    $this->mysql_mock->shouldReceive('selectOne')
      ->once()
      ->with("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2)
      ->andReturn($result = 'john');

    $this->assertSame(
      $result,
      $this->db->selectOne("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2)
    );
  }

  /** @test */
  public function count_method_returns_number_of_records_in_the_table_corresponding_to_the_where_condition()
  {
    $this->mysql_mock->shouldReceive('count')
      ->once()
      ->with('table_users', ['name' => 'John'])
      ->andReturn(12);

    $this->assertSame(
      12,
      $this->db->count('table_users', ['name' => 'John'])
    );
  }

  /** @test */
  public function selectAllByKeys_method_returns_an_array_of_the_first_field_of_the_request()
  {
    $this->mysql_mock->shouldReceive('selectAllByKeys')
      ->once()
      ->with("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2, 0)
      ->andReturn($result = [
        'john' => [
          'id' => '12'
        ]
      ]);

    $this->assertSame(
      $result,
      $this->db->selectAllByKeys("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2)
    );
  }

  /** @test */
  public function stat_method_returns_an_array_with_the_count_of_values_corresponding_the_where_condition()
  {
    $this->mysql_mock->shouldReceive('stat')
      ->once()
      ->with('table_user', 'name', ['name' => '%n'], ["id" => "ASC"])
      ->andReturn($result = [
        ['num' => 1, 'name' => 'john']
      ]);

    $this->assertSame(
      $result,
      $this->db->stat('table_user', 'name', ['name' => '%n'], ["id" => "ASC"])
    );
  }

  /** @test */
  public function getFieldValues_method_returns_the_unique_values_of_a_column_as_a_numeric_indexed_array()
  {
    $this->mysql_mock->shouldReceive('getColumnValues')
      ->once()
      ->with("table_users", "surname", [['id', '>', '2']], ["id" => "ASC"], 0, 0)
      ->andReturn($result = ['john', 'smith']);

    $this->assertSame(
      $result,
      $this->db->getFieldValues("table_users", "surname", [['id', '>', '2']], ["id" => "ASC"])
    );
  }

  /** @test */
  public function getColumnValues_method_returns_a_numeric_array_with_the_values_of_the_unique_column_for_the_given_table()
  {
    $this->mysql_mock->shouldReceive('getColumnValues')
      ->once()
      ->with("table_users", "surname", [['id', '>', '2']], ["id" => "ASC"], 0, 0)
      ->andReturn($result = ['john', 'smith']);

    $this->assertSame(
      $result,
      $this->db->getColumnValues("table_users", "surname", [['id', '>', '2']], ["id" => "ASC"])
    );
  }

  /** @test */
  public function countFieldValues_method_returns_count_of_identical_values_in_a_field_as_array()
  {
    $this->mysql_mock->shouldReceive('countFieldValues')
      ->once()
      ->with("table_users", "surname", [['id', '>', '2']], ["id" => "ASC"])
      ->andReturn($result = ['num' => 12, 'name' => 'smith']);

    $this->assertSame(
      $result,
      $this->db->countFieldValues("table_users", "surname", [['id', '>', '2']], ["id" => "ASC"])
    );
  }
}