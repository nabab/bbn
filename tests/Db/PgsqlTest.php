<?php

namespace Db;

use bbn\Cache;
use bbn\Db2\Enums\Errors;
use bbn\Db2\Languages\Pgsql;
use PHPUnit\Framework\TestCase;
use tests\Reflectable;

class PgsqlTest extends TestCase
{
  use Reflectable;

  protected Pgsql $pgsql;

  protected $cache_mock;

  protected $db2 = 'new_db';

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
      if (empty($key) || empty($value)) {
        continue;
      }
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
    $this->dropDatabaseIfExists($this->db2);
  }

  protected function tearDown(): void
  {
    \Mockery::close();
    $this->dropAllTables();
    $this->dropDatabaseIfExists($this->db2);
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

  protected function createTable(string $table, callable $callback, ?Pgsql $pgsql = null)
  {
    $structure = $callback();

    $obj = $pgsql ?? $this->pgsql;

    $this->dropTableIfExist($table, $obj);

    $obj->rawQuery("CREATE TABLE $table ($structure)");
  }

  protected function createDatabase(string $database)
  {
    $this->dropDatabaseIfExists($database);

    $this->pgsql->rawQuery("CREATE DATABASE $database ENCODING 'UTF8'");
  }

  protected function dropTableIfExist(string $table, ?Pgsql $pgsql = null)
  {
    $obj = $pgsql ?? $this->pgsql;

    $obj->rawQuery("DROP TABLE IF EXISTS $table");
  }

  protected function dropDatabaseIfExists(string $database)
  {
    $active_connections = $this->pgsql->rawQuery("SELECT *
                            FROM pg_stat_activity
                            WHERE datname = '$database'");

    if ($active_connections->rowCount() > 0) {
      $this->pgsql->rawQuery("SELECT pg_terminate_backend (pg_stat_activity.pid)
                            FROM pg_stat_activity
                            WHERE pg_stat_activity.datname = '$database'");
    }

    $this->pgsql->rawQuery("DROP DATABASE IF EXISTS $database");
  }

  protected function dropAllTables(?Pgsql $pgsql = null)
  {
    $obj = $pgsql ?? $this->pgsql;

    foreach ($obj->getTables() as $table) {
      $this->dropTableIfExist($table, $obj);
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

  /**
   * @test
   * @depends getTables_method_returns_table_names_of_a_database_as_array
   */
  public function change_method_changes_the_current_database_to_a_new_one()
  {
    $this->createTable('user_db_1', function () {
      return 'id INT PRIMARY KEY';
    });

    $this->createDatabase($this->db2);

    $result = $this->pgsql->change($this->db2);

    $this->createTable('user_db_2', function () {
      return 'id INT PRIMARY KEY';
    });

    $this->assertTrue($result);

    $this->assertSame(
      $this->db2,
      $this->getNonPublicProperty('current')
    );

    $this->assertSame(
      ['user_db_2'],
      $this->pgsql->getTables()
    );

    $this->assertSame(
      $this->db2,
      $this->getNonPublicProperty('cfg')['code_db']
    );

    $this->pgsql->change($this->getDbConfig()['db']);
  }

  /** @test */
  public function change_method_returns_false_when_the_given_database_same_as_the_current_one()
  {
    $this->assertFalse(
      $this->pgsql->change($this->getDbConfig()['db'])
    );
  }

  /** @test */
  public function change_method_returns_false_when_the_given_database_name_is_not_valid()
  {
    $this->assertFalse(
      $this->pgsql->change('new_db**')
    );
  }

  /** @test */
  public function change_method_throws_an_exception_if_the_given_database_does_not_exist()
  {
    $this->expectException(\Exception::class);

    $this->pgsql->change('unknown_db');
  }

  /** @test */
  public function createPgsqlDatabase_method_creates_a_database()
  {
    $this->dropDatabaseIfExists($this->db2);

    $method = $this->getNonPublicMethod('createPgsqlDatabase');

    $method->invoke($this->pgsql, $this->db2);

    $this->assertTrue(in_array($this->db2, $this->pgsql->getDatabases()));
  }

  /** @test */
  public function createDatabase_method_creates_a_database()
  {
    $this->pgsql->createDatabase($this->db2);

    $this->assertTrue(in_array($this->db2, $this->pgsql->getDatabases()));
  }

  /** @test */
  public function dropDatabase_method_drops_the_given_database()
  {
    $this->createDatabase($this->db2);

    $this->assertTrue(
      $this->pgsql->dropDatabase($this->db2)
    );

    // Try and connect to the db
    $db_cfg = $this->getDbConfig();
    $db_cfg['db'] = $this->db2;

    try {
      new Pgsql($db_cfg);
    } catch (\Exception $e) {
      $error = true;
    }

    $this->assertTrue(isset($error) && $error === true);
  }

  /** @test */
  public function dropDatabase_method_drop_the_given_database_when_there_is_active_connection_to_it()
  {
    $this->createDatabase($this->db2);

    // Connect to the database
    $db_cfg = $this->getDbConfig();
    $db_cfg['db'] = $this->db2;

    new Pgsql($db_cfg);

    $this->assertTrue(
      $this->pgsql->dropDatabase($this->db2)
    );

    // Try and connect to the db again
    try {
      new Pgsql($db_cfg);
    } catch (\Exception $e) {
      $error = true;
    }

    $this->assertTrue(isset($error) && $error === true);
  }

  /** @test */
  public function dropDatabase_throws_an_exception_when_the_given_database_same_as_the_current_database()
  {
    $this->expectException(\Exception::class);

    $this->pgsql->dropDatabase($this->getDbConfig()['db']);
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

  /** @test */
  public function getTables_method_returns_table_names_of_another_database_as_array()
  {
    $this->createDatabase('another_db');

    $db_config = $this->getDbConfig();
    $db_config['db'] = 'another_db';

    $new_pgsql = new Pgsql($db_config);

    $this->createTable('history', function () {
      return 'id INT PRIMARY KEY';
    }, $new_pgsql);

    $this->assertSame(
      ['history'],
      $this->pgsql->getTables('another_db')
    );

    $this->dropDatabaseIfExists('another_db');
  }

  /**
   * @test
   * @depends createDatabase_method_creates_a_database
   */
  public function getDatabases_returns_database_names_as_array()
  {
    $this->pgsql->createDatabase('bbn_test_2');

    $result = $this->pgsql->getDatabases();

    $this->assertTrue(in_array('bbn_test_2', $result));
    $this->assertTrue(in_array($this->getDbConfig()['db'], $result));

    $this->dropDatabaseIfExists('bbn_test_2');
  }
}