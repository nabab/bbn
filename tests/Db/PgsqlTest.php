<?php

namespace Db;

use bbn\Cache;
use bbn\Db\Enums\Errors;
use bbn\Db\Languages\Pgsql;
use PHPUnit\Framework\TestCase;
use tests\Reflectable;
use tests\ReflectionHelpers;

class PgsqlTest extends TestCase
{
  use Reflectable;

  protected Pgsql $pgsql;

  protected $cache_mock;

  public static function setUpBeforeClass(): void
  {
    $env_file = getcwd() . '/tests/.env.test';

    if (strpos($env_file, '/tests/Db/') !== false) {
      $env_file = str_ireplace('/tests/Db/', '/', $env_file);
    }

    if (!file_exists($env_file)) {
      throw new \Exception(
        'env file does not exist, please create the file in the tests dir, @see .env.test.example'
      );
    }

    $env = file_get_contents($env_file);

    foreach (explode(PHP_EOL, $env) as $item) {
      $res = explode('=', $item);
      $key  = $res[0];
      $value = $res[1] ?? "";
      @putenv("$key=$value");
    }
  }

  public function getInstance()
  {
    return $this->pgsql;
  }

  protected function setUp(): void
  {
    $this->pgsql = new Pgsql($this->getDbConfig());

    $this->cache_mock = \Mockery::mock(Cache::class);
    $this->pgsql->startFancyStuff();
    $this->dropAllTables();
  }

  protected function tearDown(): void
  {
    \Mockery::close();
    $this->dropAllTables();
  }


  protected function getDbConfig()
  {
    return array(
      'engine'        => 'pgsql',
      'host'          => getenv('pgsql_db_host'),
      'user'          => getenv('pgsql_db_user'),
      'pass'          => getenv('pgsql_db_pass'),
      'db'            => getenv('pgsql_db_name'),
      'cache_length'  => 3000,
      'on_error'      => Errors::E_STOP,
      'force_host'    => true
    );
  }

  protected function createTable(string $table, callable $callback)
  {
    $this->dropTableIfExist($table);

    $structure = $callback();

    $this->pgsql->rawQuery("CREATE TABLE $table ($structure)");
  }

  protected function dropTableIfExist(string $table)
  {
    $this->pgsql->rawQuery("DROP TABLE IF EXISTS $table");
  }

  protected function dropDatabaseIfExists(string $database)
  {
    $this->pgsql->rawQuery("SELECT pg_terminate_backend (pg_stat_activity.pid)
                            FROM pg_stat_activity
                            WHERE pg_stat_activity.datname = '$database'");

    $this->pgsql->rawQuery("DROP DATABASE IF EXISTS $database");
  }

  protected function dropAllTables()
  {
    foreach ($this->pgsql->getTables() as $table) {
      $this->dropTableIfExist($table);
    }
  }

  /** @test */
  public function constructor_test()
  {
    $db_config = $this->getDbConfig();

    $this->assertInstanceOf(
      Cache::class,
      $this->getNonPublicProperty('cache_engine')
    );

    $this->assertSame(
      $this->getNonPublicProperty('current'),
      $db_config['db']
    );

    $this->assertSame(
      $this->getNonPublicProperty('host'),
      $db_config['host']
    );

    $this->assertSame(
      $this->getNonPublicProperty('username'),
      $db_config['user']
    );

    $this->assertSame(
      $this->getNonPublicProperty('connection_code'),
      "{$db_config['user']}@{$db_config['host']}"
    );

    $this->assertInstanceOf(
      \PDO::class,
      $this->getNonPublicProperty('pdo')
    );

    $this->assertSame(
      array_merge($db_config, [
        'port' => 5432,
        'code_db'   => $db_config['db'],
        'code_host' => "{$db_config['user']}@{$db_config['host']}"
      ]),
      $this->getNonPublicProperty('cfg')
    );

    $this->assertSame(3000, $this->getNonPublicProperty('cache_renewal'));
    $this->assertSame(Errors::E_STOP, $this->getNonPublicProperty('on_error'));
  }

  /** @test */
  public function constructor_throws_an_exception_when_fails_to_connect_to_database()
  {
    $this->expectException(\Exception::class);

    $db_config = $this->getDbConfig();

    $db_config['db'] = 'unknown_db';

    new Pgsql($db_config);
  }

  /** @test */
  public function constructor_throws_an_exception_when_host_is_not_provided_and_BBN_DB_HOST_is_not_defined()
  {
    $this->expectException(\Exception::class);

    $db_config = $this->getDbConfig();

   unset($db_config['host']);

    new Pgsql($db_config);
  }

  /** @test */
  public function constructor_throws_an_exception_when_user_is_not_provided_and_BBN_DB_HOST_is_not_defined()
  {
    $this->expectException(\Exception::class);

    $db_config = $this->getDbConfig();

    unset($db_config['user']);

    new Pgsql($db_config);
  }

  /** @test */
  public function getHost_method_returns_the_host()
  {
    $this->assertSame($this->getDbConfig()['host'], $this->pgsql->getHost());
  }

  /** @test */
  public function getConnectionCode_method_returns_connection_code()
  {
    $cfg = $this->getDbConfig();

    $this->assertSame(
      "{$cfg['user']}@{$cfg['host']}",
      $this->pgsql->getConnectionCode()
    );
  }

  /** @test */
  public function getCfg_method_returns_the_config()
  {
    $this->assertSame(
      $this->getNonPublicProperty('cfg'),
      $this->pgsql->getCfg()
    );
  }


  /** @test */
  public function isAggregateFunction_method_returns_true_if_the_given_name_is_aggregate_function()
  {
    $this->assertTrue(Pgsql::isAggregateFunction('count(*)'));
    $this->assertTrue(Pgsql::isAggregateFunction('COUNT(*)'));
    $this->assertTrue(Pgsql::isAggregateFunction('COUNT(id)'));
    $this->assertTrue(Pgsql::isAggregateFunction('COUNT('));
    $this->assertTrue(Pgsql::isAggregateFunction('sum(*)'));
    $this->assertTrue(Pgsql::isAggregateFunction('SUM(*)'));
    $this->assertTrue(Pgsql::isAggregateFunction('avg(*)'));
    $this->assertTrue(Pgsql::isAggregateFunction('AVG(*)'));
    $this->assertTrue(Pgsql::isAggregateFunction('min(*)'));
    $this->assertTrue(Pgsql::isAggregateFunction('MIN(*)'));
    $this->assertTrue(Pgsql::isAggregateFunction('max(*)'));
    $this->assertTrue(Pgsql::isAggregateFunction('MAX(*)'));
    $this->assertTrue(Pgsql::isAggregateFunction('GROUP_CONCAT('));
    $this->assertTrue(Pgsql::isAggregateFunction('group_concat('));

    $this->assertFalse(Pgsql::isAggregateFunction('id'));
    $this->assertFalse(Pgsql::isAggregateFunction('count'));
    $this->assertFalse(Pgsql::isAggregateFunction('min'));
    $this->assertFalse(Pgsql::isAggregateFunction('MAX'));
    $this->assertFalse(Pgsql::isAggregateFunction('avg'));
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

    $this->assertSame($expected, $this->pgsql->getConditions($conditions, $cfg));
  }

  /** @test */
  public function createPgsqlDatabase_method_creates_a_database()
  {
    $this->dropDatabaseIfExists('new_db');

    $method = $this->getNonPublicMethod('createPgsqlDatabase');

    $method->invoke($this->pgsql, 'new_db');

    $this->assertTrue(in_array('new_db', $this->pgsql->getDatabases()));

    $this->dropDatabaseIfExists('new_db');
  }

  /** @test */
  public function createDatabase_method_creates_a_database()
  {
    $this->dropDatabaseIfExists('new_db');

    $this->pgsql->createDatabase('new_db');

    $this->assertTrue(in_array('new_db', $this->pgsql->getDatabases()));

    $this->dropDatabaseIfExists('new_db');
  }

  /**
   * @test
   */
  public function getTables_method_returns_table_names_of_a_database_as_array()
  {
    $this->createTable('users', function () {
      return 'id INT PRIMARY KEY';
    });

    $this->createTable('roles', function () {
      return 'id INT PRIMARY KEY';
    });

    $this->assertSame(
      ['users', 'roles'],
      $this->pgsql->getTables()
    );
  }

  /**
   * @test
   * @depends createDatabase_method_creates_a_database
   */
  public function getDatabases_returns_database_names_as_array()
  {
    $this->dropDatabaseIfExists('bbn_test_2');

    $this->pgsql->createDatabase('bbn_test_2');

    $result = $this->pgsql->getDatabases();

    $this->assertTrue(in_array('bbn_test_2', $result));
    $this->assertTrue(in_array($this->getDbConfig()['db'], $result));

    $this->dropDatabaseIfExists('bbn_test_2');
  }
}