<?php

namespace Db;

use bbn\Db2;
use bbn\Db2\Languages\Mysql;
use PHPUnit\Framework\TestCase;
use tests\Reflectable;

class MysqlTest extends TestCase
{
  use Reflectable;

  protected static Mysql $mysql;

  protected static $db_mock;

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
  }

  protected static function getDbConfig()
  {
    return [
      'engine'        => 'mysql',
      'host'          => 'localhost',
      'user'          => 'root',
      'pass'          => getenv('db_pass'),
      'db'            => 'bbn_test',
      'cache_length'  => 3000,
      'error_mode'    => 'stop'
    ];
  }

  public function getInstance()
  {
    return self::$mysql;
  }


  protected function tearDown(): void
  {
    \Mockery::close();
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
  }
}