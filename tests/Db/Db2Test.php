<?php

namespace Db;

use bbn\Cache;
use bbn\Db\Engines;
use bbn\Db2;
use bbn\Db2\Languages\Mysql;
use PHPUnit\Framework\TestCase;
use tests\Reflectable;

class Db2Test extends TestCase
{
  use Reflectable;

  protected Db2 $db;

  protected $mysql_mock;

  protected function setUp(): void
  {
    $this->mysql_mock = \Mockery::mock(Mysql::class);

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

    $this->db = new Db2($this->getDbConfig());
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
}