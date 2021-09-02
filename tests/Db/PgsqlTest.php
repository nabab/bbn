<?php

namespace Db;

use bbn\Cache;
use bbn\Db\Enums\Errors;
use bbn\Db\Languages\Pgsql;
use bbn\Str;
use PHPUnit\Framework\TestCase;
use tests\Reflectable;
use tests\ReflectionHelpers;

class PgsqlTest extends TestCase
{
  use Reflectable;

  protected static Pgsql $pgsql;

  protected static $real_params_default;

  protected static $default_triggers;

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
      if (empty($key)) {
        continue;
      }
      if (empty($value)) {
        $value = '';
      }
      @putenv("$key=$value");
    }

    self::$pgsql = new Pgsql(self::getDbConfig());

    self::$default_triggers = ReflectionHelpers::getNonPublicProperty(
      '_triggers', self::$pgsql
    );

    self::$real_params_default = ReflectionHelpers::getNonPublicProperty(
      'last_real_params', self::$pgsql
    );

    ReflectionHelpers::setNonPublicPropertyValue(
      'max_queries', self::$pgsql, 60000000
    );
  }

  public function getInstance()
  {
    return self::$pgsql;
  }

  protected function setUp(): void
  {
    self::$pgsql->startFancyStuff();

    $this->cache_mock = \Mockery::mock(Cache::class);
    $this->setNonPublicPropertyValue('cache_engine', $this->cache_mock);

    $this->setNonPublicPropertyValue('_has_error_all', false);
    $this->setNonPublicPropertyValue('_has_error', false);
    $this->setNonPublicPropertyValue('last_error', null);
    $this->setNonPublicPropertyValue('last_real_params', self::$real_params_default);
    $this->setNonPublicPropertyValue('last_params', self::$real_params_default);
    $this->setNonPublicPropertyValue('on_error', Errors::E_STOP);
    $this->setNonPublicPropertyValue('id_just_inserted', null);
    $this->setNonPublicPropertyValue('last_insert_id', null);
    $this->setNonPublicPropertyValue('last_query', null);
    $this->setNonPublicPropertyValue('last_real_query', null);
    $this->setNonPublicPropertyValue('current', self::getDbConfig()['db']);
    $this->setNonPublicPropertyValue('_triggers', self::$default_triggers);
    $this->setNonPublicPropertyValue('_triggers_disabled', false);
    $this->setNonPublicPropertyValue('last_cfg', []);
    $this->setNonPublicPropertyValue('cfgs', []);
    $this->setNonPublicPropertyValue('queries', []);
    $this->setNonPublicPropertyValue('list_queries', []);

    $this->dropAllTables();
    $this->dropDatabaseIfExists($this->db2);
    $this->setNonPublicPropertyValue('cache', []);
  }

  protected function tearDown(): void
  {
    \Mockery::close();
//    $this->dropAllTables();
    $this->dropDatabaseIfExists($this->db2);
  }


  protected static function getDbConfig()
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

    $obj = $pgsql ?? self::$pgsql;

    $this->dropTableIfExist($table, $obj);

    $obj->rawQuery("CREATE TABLE $table ($structure)");
  }

  protected function insertOne(string $table, array $params, ?Pgsql $pgsql = null)
  {
    $query = "INSERT INTO $table (";

    foreach ($params as $column => $value) {
      $query .= "$column,  ";
    }

    $query = rtrim($query, ', ');

    $query .= ") VALUES (";

    foreach ($params as $value) {
      $query .= "'$value', ";
    }

    $query = rtrim($query, ', ') .  ")";

    $obj = $pgsql ?? self::$pgsql;

    $obj->rawQuery(rtrim($query, ', '));
  }

  protected function insertMany(string $table, array $params, ?Pgsql $pgsql = null)
  {
    foreach ($params as $fields) {
      if (!is_array($fields)) {
        continue;
      }

      $this->insertOne($table, $fields, $pgsql);
    }
  }

  protected function assertDatabaseHas(string $table, string $field, string $value)
  {
    $record = self::$pgsql->rawQuery(
      "SELECT $field FROM $table WHERE $field = '$value'"
    );

    $this->assertTrue($record->rowCount() > 0, 'Failed asserting that database has the given values');
  }

  protected function assertDatabaseDoesNotHave(string $table, string $field, string $value)
  {
    $record = self::$pgsql->rawQuery(
      "SELECT $field FROM $table WHERE $field = '$value'"
    );

    $this->assertTrue($record->rowCount() === 0, 'Failed asserting that database does not have the given values');
  }

  protected function getTableStructure(string $table)
  {
    $this->setCacheExpectations();

    return self::$pgsql->modelize($table);
  }

  protected function createDatabase(string $database)
  {
    $this->dropDatabaseIfExists($database);

    self::$pgsql->rawQuery("CREATE DATABASE $database ENCODING 'UTF8'");
  }

  protected function dropTableIfExist(string $table, ?Pgsql $pgsql = null)
  {
    $obj = $pgsql ?? self::$pgsql;

    $obj->rawQuery("DROP TABLE IF EXISTS $table cascade");
  }

  protected function dropDatabaseIfExists(string $database)
  {
    $active_connections = self::$pgsql->rawQuery("SELECT *
                            FROM pg_stat_activity
                            WHERE datname = '$database'");

    if ($active_connections->rowCount() > 0) {
      self::$pgsql->rawQuery("SELECT pg_terminate_backend (pg_stat_activity.pid)
                            FROM pg_stat_activity
                            WHERE pg_stat_activity.datname = '$database'");
    }

    self::$pgsql->rawQuery("DROP DATABASE IF EXISTS $database");
  }

  protected function dropAllTables(?Pgsql $pgsql = null)
  {
    $obj = $pgsql ?? self::$pgsql;

    if ($tables = $obj->getTables()) {
      foreach ($tables as $table) {
        $this->dropTableIfExist($table, $obj);
      }
    }
  }

  protected function setCacheExpectations()
  {
    $this->cache_mock->shouldReceive('get')
      ->andReturnFalse();

    $this->cache_mock->shouldReceive('set')
      ->andReturnTrue();
  }

  /** @test */
  public function constructor_test()
  {
    $db_config = self::getDbConfig();

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

    $db_config = self::getDbConfig();

    $db_config['db'] = 'unknown_db';

    new Pgsql($db_config);
  }

  /** @test */
  public function constructor_throws_an_exception_when_host_is_not_provided_and_BBN_DB_HOST_is_not_defined()
  {
    $this->expectException(\Exception::class);

    $db_config = self::getDbConfig();

   unset($db_config['host']);

    new Pgsql($db_config);
  }

  /** @test */
  public function constructor_throws_an_exception_when_user_is_not_provided_and_BBN_DB_HOST_is_not_defined()
  {
    $this->expectException(\Exception::class);

    $db_config = self::getDbConfig();

    unset($db_config['user']);

    new Pgsql($db_config);
  }

  /** @test */
  public function getHost_method_returns_the_host()
  {
    $this->assertSame(self::getDbConfig()['host'], self::$pgsql->getHost());
  }

  /** @test */
  public function getConnectionCode_method_returns_connection_code()
  {
    $cfg = self::getDbConfig();

    $this->assertSame(
      "{$cfg['user']}@{$cfg['host']}",
      self::$pgsql->getConnectionCode()
    );
  }

  /** @test */
  public function getCfg_method_returns_the_config()
  {
    $this->assertSame(
      $this->getNonPublicProperty('cfg'),
      self::$pgsql->getCfg()
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
  users.id = ?
  AND name = ?
  AND (created_at IS NULL
    OR updated_at IS NULL
  )
)

RESULT;

    $this->assertSame($expected, self::$pgsql->getConditions($conditions, $cfg));
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

    $result = self::$pgsql->change($this->db2);

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
      self::$pgsql->getTables()
    );

    $this->assertSame(
      $this->db2,
      $this->getNonPublicProperty('cfg')['code_db']
    );

    self::$pgsql->change(self::getDbConfig()['db']);
  }

  /** @test */
  public function change_method_returns_false_when_the_given_database_same_as_the_current_one()
  {
    $this->assertFalse(
      self::$pgsql->change(self::getDbConfig()['db'])
    );
  }

  /** @test */
  public function change_method_returns_false_when_the_given_database_name_is_not_valid()
  {
    $this->assertFalse(
      self::$pgsql->change('new_db**')
    );
  }

  /** @test */
  public function change_method_throws_an_exception_if_the_given_database_does_not_exist()
  {
    $this->expectException(\Exception::class);

    self::$pgsql->change('unknown_db');
  }

  /** @test */
  public function createPgsqlDatabase_method_creates_a_database()
  {
    $this->dropDatabaseIfExists($this->db2);

    $method = $this->getNonPublicMethod('createPgsqlDatabase');

    $method->invoke(self::$pgsql, $this->db2);

    $this->assertTrue(in_array($this->db2, self::$pgsql->getDatabases()));
  }

  /** @test */
  public function createDatabase_method_creates_a_database()
  {
    self::$pgsql->createDatabase($this->db2);

    $this->assertTrue(in_array($this->db2, self::$pgsql->getDatabases()));
  }

  /** @test */
  public function dropDatabase_method_drops_the_given_database()
  {
    $this->createDatabase($this->db2);

    $this->assertTrue(
      self::$pgsql->dropDatabase($this->db2)
    );

    // Try and connect to the db
    $db_cfg = self::getDbConfig();
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
    $db_cfg = self::getDbConfig();
    $db_cfg['db'] = $this->db2;

    new Pgsql($db_cfg);

    $this->assertTrue(
      self::$pgsql->dropDatabase($this->db2)
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

    self::$pgsql->dropDatabase(self::getDbConfig()['db']);
  }

  /** @test */
  public function dropDatabase_method_throws_an_exception_when_the_given_name_is_not_valid()
  {
    $this->expectException(\Exception::class);

    self::$pgsql->dropDatabase('db***');
  }

  /** @test */
  public function dropDatabase_method_returns_false_if_check_method_returns_false()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertFalse(
      self::$pgsql->dropDatabase(self::getDbConfig()['db'])
    );
  }

  /** @test */
  public function createUser_method_creates_a_new_database_user()
  {
    try {
      self::$pgsql->deleteUser('new_user');
    } catch (\Exception $e) {

    }

    $this->createTable('users', function () {
        return 'id INT PRIMARY KEY';
    });

    $this->assertTrue(
      self::$pgsql->createUser('new_user', '123456')
    );

    // Connect with the new user
    $cfg = self::getDbConfig();
    $cfg['user'] = 'new_user';
    $cfg['pass'] = '123456';

    $pgsql = new Pgsql($cfg);

    $this->assertSame(
      ['users'],
      $pgsql->getTables(),
    );

    self::$pgsql->deleteUser('new_user');
  }

  /** @test */
  public function createUser_method_returns_false_when_the_given_user_is_not_a_valid_name()
  {
    $this->assertFalse(
      self::$pgsql->createUser('user***', '123')
    );
  }

  public function createUser_method_returns_false_when_the_given_password_is_not_valid()
  {
    $this->assertFalse(
      self::$pgsql->createUser('user', "123'")
    );
  }

  /**
   * @test
   * @depends createUser_method_creates_a_new_database_user
   */
  public function deleteUser_method_deletes_the_given_user_from_database()
  {
    self::$pgsql->createUser('new_user', '12345');

    $result = self::$pgsql->deleteUser('new_user');

    $this->assertTrue($result);
  }

  /** @test */
  public function deleteUser_method_returns_false_when_the_given_user_is_not_valid_name()
  {
    $this->assertFalse(
      self::$pgsql->deleteUser('user***')
    );
  }

  /** @test */
  public function getUsers_method_returns_all_current_users()
  {
    $user = self::getDbConfig()['user'];

    $this->assertTrue(
      in_array($user, self::$pgsql->getUsers())
    );

    $this->assertTrue(
      in_array($user, self::$pgsql->getUsers($user))
    );

    $this->assertEmpty(
      self::$pgsql->getUsers('foo')
    );
  }

  /** @test */
  public function getUsers_method_returns_null_when_check_method_returns_false()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull(
      self::$pgsql->getUsers()
    );
  }

  /** @test */
  public function dbSize_method_returns_the_size_of_the_current_database()
  {
    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY,
              name VARCHAR(255) UNIQUE NOT NULL';
    });

    $this->createTable('posts', function () {
      return 'id serial PRIMARY KEY,
              description TEXT NOT NULL';
    });

    $this->insertMany('users', [
      ['name' => 'John'],
      ['name' => 'Sam']
    ]);

    $this->insertMany('posts', [
      ['description' => Str::genpwd(1400, 1400)],
      ['description' => Str::genpwd(1100, 1100)],
    ]);

    $total_size = self::$pgsql->dbSize();
    $index_size = self::$pgsql->dbSize('', 'index');
    $data_size = self::$pgsql->dbSize('', 'data');

    $this->assertGreaterThan(0 ,$total_size);
    $this->assertGreaterThan(0 ,$index_size);
    $this->assertGreaterThan(0 ,$data_size);

    $this->assertNotSame($total_size, $index_size);
    $this->assertNotSame($total_size, $data_size);
    $this->assertNotSame($data_size, $index_size);
  }

  /** @test */
  public function dbSize_method_returns_the_size_of_the_given_database()
  {
    $this->createDatabase($this->db2);

    $cfg = self::getDbConfig();
    $cfg['db'] = $this->db2;

    $pgsql = new Pgsql($cfg);

    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY,
              name VARCHAR(255) UNIQUE NOT NULL';
    }, $pgsql);

    $this->createTable('posts', function () {
      return 'id serial PRIMARY KEY,
              description TEXT NOT NULL';
    }, $pgsql);

    $this->insertMany('users', [
      ['name' => 'John'],
      ['name' => 'Sam']
    ], $pgsql);

    $this->insertMany('posts', [
      ['description' => Str::genpwd(1400, 1400)],
      ['description' => Str::genpwd(1100, 1100)],
    ], $pgsql);

    $this->assertSame(0, self::$pgsql->dbSize());

    $total_size = self::$pgsql->dbSize($this->db2);
    $index_size = self::$pgsql->dbSize($this->db2, 'index');
    $data_size = self::$pgsql->dbSize($this->db2, 'data');

    $this->assertGreaterThan(0 ,$total_size);
    $this->assertGreaterThan(0 ,$index_size);
    $this->assertGreaterThan(0 ,$data_size);

    $this->assertNotSame($total_size, $index_size);
    $this->assertNotSame($total_size, $data_size);
    $this->assertNotSame($data_size, $index_size);

    $this->dropDatabaseIfExists($this->db2);
  }

  /** @test */
  public function dbSize_method_returns_zero_when_no_tables_in_database()
  {
    $this->assertSame(0, self::$pgsql->dbSize());
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

    $expected = ['users', 'roles'];
    $result   = self::$pgsql->getTables();

    sort($expected);
    sort($result);

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function tableSize_method_returns_the_size_of_the_given_table()
  {
    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY,
              username VARCHAR(255) UNIQUE NOT NULL';
    });

    $this->insertMany('users', [
      ['username' => 'John'],
      ['username' => 'Sam'],
    ]);

    $total_size = self::$pgsql->tableSize('users');
    $index_size = self::$pgsql->tableSize('users', 'index');
    $data_size  = self::$pgsql->tableSize('users', 'data');

    $this->assertGreaterThan(0, $total_size);
    $this->assertGreaterThan(0, $index_size);
    $this->assertGreaterThan(0, $data_size);

    $this->assertNotSame($total_size, $index_size);
    $this->assertNotSame($total_size, $data_size);
    $this->assertNotSame($index_size, $data_size);
  }

  /** @test */
  public function tableSize_method_throws_an_exception_when_the_given_table_does_not_exist()
  {
    $this->expectException(\Exception::class);

    self::$pgsql->tableSize('roles');
  }

  /** @test */
  public function status_method_returns_status_of_the_given_table_for_the_current_database()
  {
    $this->createTable('posts', function () {
      return 'id serial PRIMARY KEY,
              content TEXT NOT NULL';
    });

    $this->createTable('comments', function () {
      return 'content TEXT NOT NULL';
    });

    $expected_posts = [
      'schemaname' => 'public',
      'tablename' => 'posts',
      'tableowner' => self::getDbConfig()['user'],
      'tablespace' => null,
      'hasindexes' => true,
      'hasrules' => false,
      'hastriggers' => false,
      'rowsecurity' => false
    ];

    $expected_comments = [
      'schemaname' => 'public',
      'tablename' => 'comments',
      'tableowner' => self::getDbConfig()['user'],
      'tablespace' => null,
      'hasindexes' => false,
      'hasrules' => false,
      'hastriggers' => false,
      'rowsecurity' => false
    ];

    $this->assertSame($expected_comments, self::$pgsql->status('comments'));
    $this->assertSame($expected_posts, self::$pgsql->status('posts'));
  }

  /** @test */
  public function status_method_returns_status_of_the_given_table_for_the_given_database()
  {
    $this->createDatabase($this->db2);

    $cfg       = self::getDbConfig();
    $cfg['db'] = $this->db2;

    $pgsql = new Pgsql($cfg);

    $this->createTable('posts', function () {
      return 'id serial PRIMARY KEY,
              content TEXT NOT NULL';
    }, $pgsql);

    $this->createTable('comments', function () {
      return 'id serial PRIMARY KEY,
              content TEXT NOT NULL';
    }, $pgsql);

    $this->assertNull(
      self::$pgsql->status()
    );

    $result   = self::$pgsql->status('', $this->db2);
    $expected = [
      'schemaname' => 'public',
      'tablename' => 'posts',
      'tableowner' => self::getDbConfig()['user'],
      'tablespace' => null,
      'hasindexes' => true,
      'hasrules' => false,
      'hastriggers' => false,
      'rowsecurity' => false
    ];

    $this->assertSame($expected, $result);
    $this->assertNull(self::$pgsql->status());
  }

  /** @test */
  public function getUid_method_returns_a_uuid()
  {
    $result = self::$pgsql->getUid();

    $this->assertIsString($result);
    $this->assertSame(32, strlen($result));
  }

  /** @test */
  public function createTable_method_returns_a_string_of_create_table_statement_from_given_arguments()
  {
    $columns = [
      'email' => [
        'name' => 'email',
        'type' => 'varchar',
        'maxlength' => 255
      ],
      'id' => [
        'type' => 'int',

      ],
      'name' => [
        'null'=> true,
        'default' => 'NULL'
      ],
      'balance' => [
        'type' => 'numeric',
        'values' => [10, 2],
        'default' => 0
      ],
      'invalid_name***' => [
        'type' => 'text'
      ]
    ];

    $expected = "CREATE TABLE users (
email varchar(255) NOT NULL,
id int NOT NULL,
balance numeric(10,2) NOT NULL DEFAULT '0'
);";

    $this->assertSame(
      $expected,
      self::$pgsql->createTable('users', $columns)
    );
  }

  /**
   * @test
   * @depends createDatabase_method_creates_a_database
   */
  public function getDatabases_returns_database_names_as_array()
  {
    $this->dropDatabaseIfExists('bbn_test_2');

    self::$pgsql->createDatabase('bbn_test_2');

    $result = self::$pgsql->getDatabases();

    $this->assertTrue(in_array('bbn_test_2', $result));
    $this->assertTrue(in_array(self::getDbConfig()['db'], $result));

    $this->dropDatabaseIfExists('bbn_test_2');
  }

  /** @test */
  public function getDatabases_method_returns_null_when_check_method_returns_false()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull(
      self::$pgsql->getDatabases()
    );
  }

  /** @test */
  public function getTables_method_returns_table_names_of_another_database_as_array()
  {
    $this->createDatabase('another_db');

    $db_config = self::getDbConfig();
    $db_config['db'] = 'another_db';

    $new_pgsql = new Pgsql($db_config);

    $this->createTable('history', function () {
      return 'id INT PRIMARY KEY';
    }, $new_pgsql);

    $this->assertSame(
      ['history'],
      self::$pgsql->getTables('another_db')
    );

    $this->dropDatabaseIfExists('another_db');
  }

  /** @test */
  public function getTables_method_returns_null_when_check_method_returns_null()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull(
      self::$pgsql->getTables()
    );
  }

  /** @test */
  public function getColumns_method_returns_columns_configuration_of_the_given_table()
  {
    self::$pgsql->rawQuery('DROP TYPE IF EXISTS role_enum');
    self::$pgsql->rawQuery("CREATE TYPE role_enum AS ENUM ('Admin', 'Super Admin', 'User')");

    $this->createTable('users', function () {
      return 'id bigserial PRIMARY KEY,
              role_id bigint,
              username VARCHAR(255) UNIQUE NOT NULL,
              balance NUMERIC(10,2) DEFAULT 0,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              profile_id bytea,
              is_active boolean DEFAULT true,
              role role_enum DEFAULT \'User\'
              ';
    });

    $expected = [
      'id' => [
        'position' => 1,
        'type' => 'bigint',
        'udt_name' => 'int8',
        'null' => 0,
        'key' => 'PRI',
        'extra' => 'auto_increment',
        'signed' => true,
        'virtual' => false,
        'generation' => null,
        'default' => "nextval('users_id_seq'::regclass)",
        'maxlength' => 64,
        'decimals' => 0
      ],
      'role_id' => [
        'position' => 2,
        'type' => 'bigint',
        'udt_name' => 'int8',
        'null' => 1,
        'key' => null,
        'extra' => '',
        'signed' => true,
        'virtual' => false,
        'generation' => null,
        'default' => 'NULL',
        'maxlength' => 64,
        'decimals' => 0
      ],
      'username' => [
        'position' => 3,
        'type' => 'character varying',
        'udt_name' => 'varchar',
        'null' => 0,
        'key' => 'UNI',
        'extra' => '',
        'signed' => false,
        'virtual' => false,
        'generation' => null,
        'maxlength' => 255
      ],
      'balance' => [
        'position' => 4,
        'type' => 'numeric',
        'udt_name' => 'numeric',
        'null' => 1,
        'key' => null,
        'extra' => '',
        'signed' => true,
        'virtual' => false,
        'generation' => null,
        'default' => 0,
        'maxlength' => 10,
        'decimals' => 2
      ],
      'created_at' => [
        'position' => 5,
        'type' => 'timestamp without time zone',
        'udt_name' => 'timestamp',
        'null' => 1,
        'key' => null,
        'extra' => '',
        'signed' => false,
        'virtual' => false,
        'generation' => null,
        'default' => 'CURRENT_TIMESTAMP'
      ],
      'profile_id' => [
        'position' => 6,
        'type' => 'bytea',
        'udt_name' => 'bytea',
        'null' => 1,
        'key' => null,
        'extra' => '',
        'signed' => false,
        'virtual' => false,
        'generation' => null,
        'default' => 'NULL'
      ],
      'is_active' => [
        'position' => 7,
        'type' => 'boolean',
        'udt_name' => 'bool',
        'null' => 1,
        'key' => null,
        'extra' => '',
        'signed' => false,
        'virtual' => false,
        'generation' => null,
        'default' => 'true'
      ],
      'role' => [
        'position' => 8,
        'type' => 'USER-DEFINED',
        'udt_name' => 'role_enum',
        'null' => 1,
        'key' => null,
        'extra' => '',
        'signed' => false,
        'virtual' => false,
        'generation' => null,
        'default' => '\'User\'::role_enum'
      ]
    ];

    $this->assertSame(
      $expected,
      self::$pgsql->getColumns('users')
    );
  }

  /** @test */
  public function getColumns_method_returns_null_when_check_method_returns_false()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull(
      self::$pgsql->getColumns('users')
    );
  }

  /** @test */
  public function getKeys_method_returns_the_keys_of_given_table()
  {
    $this->createTable('roles', function () {
      return 'id bigserial PRIMARY KEY,
              name VARCHAR(20) NOT NULL';
    });

    $this->createTable('profiles', function () {
      return 'id bigserial PRIMARY KEY,
              name VARCHAR(20) NOT NULL';
    });

    $this->createTable('users', function () {
      return 'id bigserial PRIMARY KEY,
              username VARCHAR(200) UNIQUE NOT NULL,
              role_id bigserial,
              profile_id bigserial,
              CONSTRAINT user_role FOREIGN KEY(role_id) REFERENCES roles(id),
              CONSTRAINT user_profile FOREIGN KEY(profile_id) REFERENCES profiles(id)';
    });

    $expected = [
      'keys' => [
        'PRIMARY' => [
          'columns' => ['id'],
          'ref_db' => null,
          'ref_table' => null,
          'ref_column' => null,
          'constraint' => null,
          'update' => null,
          'delete' => null,
          'unique' => 1
        ],
        'users_username_key' => [
          'columns' => ['username'],
          'ref_db' => null,
          'ref_table' => null,
          'ref_column' => null,
          'constraint' => null,
          'update' => null,
          'delete' => null,
          'unique' => 1
        ],

        'user_role' => [
          'columns' => ['role_id'],
          'ref_db' => self::getDbConfig()['db'],
          'ref_table' => 'roles',
          'ref_column' => 'id',
          'constraint' => 'user_role',
          'update' => null,
          'delete' => null,
          'unique' => 0
        ],
        'user_profile' => [
          'columns' => ['profile_id'],
          'ref_db' => self::getDbConfig()['db'],
          'ref_table' => 'profiles',
          'ref_column' => 'id',
          'constraint' => 'user_profile',
          'update' => null,
          'delete' => null,
          'unique' => 0
        ]
      ],
      'cols' => [
        'id' => ['PRIMARY'],
        'username' => ['users_username_key'],
        'role_id' => ['user_role'],
        'profile_id' => ['user_profile']
      ]
    ];

    $result = self::$pgsql->getKeys('users');

    sort($result['keys']);
    sort($result['cols']);

    sort($expected['keys']);
    sort($expected['cols']);

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getKeys_method_returns_null_when_check_method_returns_false()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull(
      self::$pgsql->getKeys('users')
    );
  }

  /** @test */
  public function getKeys_method_returns_empty_array_when_the_given_table_is_not_a_valid_name()
  {
    $this->assertEmpty(
      self::$pgsql->getKeys('users***')
    );
  }

  /** @test */
  public function getRawCreate_method_returns_string_of_create_table_statement()
  {
    $this->createTable('users', function () {
      return 'id bigserial PRIMARY KEY,
              username VARCHAR(255) UNIQUE NOT NULL,
              id2 integer,
              balance NUMERIC(10,2) DEFAULT 0';
    });

    $expected = "CREATE TABLE users
 (
    id bigserial NOT NULL, 
    username character varying(255) NOT NULL, 
    id2 integer, 
    balance numeric(10,2) DEFAULT 0 
);";

    $this->assertSame(
      $expected,
      self::$pgsql->getRawCreate('users')
    );
    $this->dropTableIfExist('users');
    self::$pgsql->rawQuery($expected);
    $this->insertOne('users', [
      'username' => 'Sam',
      'id2' => 2,
      'balance' => 12.11
    ]);

    $this->assertDatabaseHas('users', 'id', 1);
    $this->assertDatabaseHas('users', 'username', 'Sam');
    $this->assertDatabaseHas('users', 'balance', 12.11);
    $this->assertDatabaseHas('users', 'id2', 2);
  }

  /** @test */
  public function getRawCreate_method_returns_empty_string_when_the_given_table_does_not_exist()
  {
    $this->assertSame('', self::$pgsql->getRawCreate('users_2'));
  }

  /** @test */
  public function getRawCreate_method_returns_empty_string_when_the_given_table_name_is_not_valid()
  {
    $this->assertSame('', self::$pgsql->getRawCreate('users**'));
  }

  /** @test */
  public function getCreateTable_method_returns_a_string_with_create_table_statement()
  {
    $cfg = [
      'fields' => [
        'id' => [
          'type' => 'binary'
        ],
        'username' => [
          'type' => 'varchar',
          'maxlength' => 255
        ],
        'profile_id' => [
          'type' => 'bigint',
          'maxlength' => 32
        ],
        'user_role' => [
          'type' => 'USER-DEFINED',
          'extra' => "'super_admin','admin','user'",
          'default' => 'user',
          'udt_name' => 'role',
          'USER-DEFINED' => true
        ],
        'user_permission' => [
          'type' => 'USER-DEFINED',
          'extra' => "'read','write'",
          'default' => 'NULL',
          'udt_name' => 'permission',
          'null' => true
        ],
        'balance' => [
          'type' => 'decimal',
          'maxlength' => 10,
          'decimals' => 2,
          'null' => true,
          'default' => 'NULL'
        ],
        'balance_before' => [
          'type' => 'real',
          'maxlength' => 10,
          'decimals' => 2,
          'signed' => true,
          'default' => 0
        ],
        'created_at' => [
          'type' => 'datetime',
          'default' => 'CURRENT_TIMESTAMP'
        ],
        'updated_at' => [
          'type' => 'time',
          'default' => 'CURRENT_TIME'
        ]
      ]
    ];

    self::$pgsql->rawQuery('DROP TYPE IF EXISTS role');
    self::$pgsql->rawQuery('DROP TYPE IF EXISTS permission');
    self::$pgsql->rawQuery("CREATE TYPE role AS ENUM ('super_admin', 'admin', 'user')");
    self::$pgsql->rawQuery("CREATE TYPE permission AS ENUM ('read', 'write')");

    $result   = self::$pgsql->getCreateTable('users', $cfg);
    $expected = "CREATE TABLE users (
  id bytea NOT NULL,
  username varchar(255) NOT NULL,
  profile_id bigint NOT NULL,
  user_role role NOT NULL DEFAULT 'user',
  user_permission permission DEFAULT NULL,
  balance decimal(10,2) DEFAULT NULL,
  balance_before real NOT NULL DEFAULT 0,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at time NOT NULL DEFAULT CURRENT_TIME
)";

    $this->assertSame($expected, $result);

    try {
      self::$pgsql->rawQuery($result);
    } catch (\Exception $e) {
      $error = $e->getMessage();
    }

    $this->assertTrue(!isset($error), $error ?? '');

    return $expected;
  }

  /** @test */
  public function getCreateTable_method_throws_an_exception_when_a_provided_type_is_not_valid()
  {
    $this->expectException(\Exception::class);

    $cfg = [
      'fields' => [
        'id' => [
          'type' => 'binary'
        ],
        'username' => [
          'type' => 'foo'
        ]
      ]
    ];

    self::$pgsql->getCreateTable('users', $cfg);
  }

  /** @test */
  public function getCreateTable_method_returns_a_string_with_create_table_statement_when_model_is_not_provided()
  {
    self::$pgsql->rawQuery('DROP TYPE IF EXISTS role');
    self::$pgsql->rawQuery('DROP TYPE IF EXISTS permission');
    self::$pgsql->rawQuery("CREATE TYPE role AS ENUM ('super_admin', 'admin', 'user')");
    self::$pgsql->rawQuery("CREATE TYPE permission AS ENUM ('read', 'write')");

    $query = "CREATE TABLE users (
  id bytea NOT NULL,
  username varchar(255) NOT NULL,
  user_role role NOT NULL DEFAULT 'user',
  user_permission permission DEFAULT NULL,
  balance decimal(10,2) DEFAULT NULL,
  balance_before real NOT NULL DEFAULT 0,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at time NOT NULL DEFAULT CURRENT_TIME
)";

    self::$pgsql->rawQuery($query);

    // Set expectations for the methods called on Cache class in modelize method
    $this->setCacheExpectations();

    $result = self::$pgsql->getCreateTable('users');

    $expected = "CREATE TABLE users (
  id bytea NOT NULL,
  username character varying(255) NOT NULL,
  user_role role NOT NULL DEFAULT 'user',
  user_permission permission DEFAULT NULL,
  balance numeric(10,2) DEFAULT NULL,
  balance_before real NOT NULL DEFAULT 0,
  created_at timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at time without time zone NOT NULL DEFAULT CURRENT_TIME
)";

    $this->assertSame(trim($expected), trim($result));
  }

  /** @test */
  public function getCreateKeys_method_returns_string_with_create_keys_statement()
  {
    $this->createTable('users', function () {
      return 'id serial,
              email varchar(200),
               username varchar(200)';
    });

    $cfg = [
      'keys' => [
        'primary' => [
          'unique' => true,
          'columns' => ['id']
        ],
        'email' => [
          'unique' => true,
          'columns' => ['email']
        ],
        'username' => [
          'columns' => ['username']
        ]
      ],
      'fields' => [
        'id' => [
          'key' => 'PRI'
        ]
      ]
    ];

    $result = self::$pgsql->getCreateKeys('users', $cfg);
    $expected = "ALTER TABLE users
ADD PRIMARY KEY (id),
ADD CONSTRAINT email UNIQUE (email)
";

    $this->assertSame(trim($expected), trim($result));

    try {
      self::$pgsql->rawQuery($result);
    } catch (\Exception $e) {
      $error = $e->getMessage();
    }

    $this->assertTrue(!isset($error), $error ?? '');
  }

  /** @test */
  public function getCreateKeys_method_returns_string_with_create_keys_statement_when_model_is_null()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id serial,
              email varchar(200),
               username varchar(200)';
    });

    $query = "ALTER TABLE users
ADD CONSTRAINT email UNIQUE (email),
ADD PRIMARY KEY (id);";

    self::$pgsql->rawQuery($query);

    $this->assertSame(
      $query,
      self::$pgsql->getCreateKeys('users')
    );
  }

  /** @test */
  public function getCreateKeys_method_returns_empty_string_when_configurations_missing_items()
  {
    $this->assertSame('',self::$pgsql->getCreateKeys('users', [
      'fields' => [
        'id' => ['key' => 'PRI']
      ]
    ]));
  }

  /** @test */
  public function getCreateKeys_method_returns_empty_string_when_model_cannot_be_retrieved_from_database()
  {
    $pgsql = \Mockery::mock(Pgsql::class)->makePartial();

    $pgsql->shouldReceive('modelize')
      ->with('users')
      ->once()
      ->andReturnNull();

    $this->assertSame('', $pgsql->getCreateKeys('users'));
  }

  /** @test */
  public function getCreate_method_returns_sql_string_for_table_creation()
  {
    $cfg = [
      'keys' => [
        'primary' => [
          'unique' => true,
          'columns' => ['id']
        ],
        'username_unique' => [
          'unique' => true,
          'columns' => ['username']
        ],
        'created_at_index' => [
          'columns' => ['created_at']
        ],
        'updated_at_index' => [
          'columns' => ['updated_at']
        ],
        'created_updated_at_index' => [
          'columns' => ['created_at', 'updated_at']
        ]
      ],
      'fields' => [
        'id' => [
          'type' => 'binary',
          'key' => 'PRI'
        ],
        'username' => [
          'type' => 'varchar',
          'maxlength' => 255
        ],
        'user_role' => [
          'type' => 'USER-DEFINED',
          'extra' => "'super_admin','admin','user'",
          'default' => 'user',
          'udt_name' => 'role',
          'USER-DEFINED' => true
        ],
        'user_permission' => [
          'type' => 'USER-DEFINED',
          'extra' => "'read','write'",
          'default' => 'NULL',
          'udt_name' => 'permission',
          'null' => true
        ],
        'balance' => [
          'type' => 'decimal',
          'maxlength' => 10,
          'decimals' => 2,
          'null' => true,
          'default' => 'NULL'
        ],
        'balance_before' => [
          'type' => 'real',
          'maxlength' => 10,
          'decimals' => 2,
          'signed' => true,
          'default' => 0
        ],
        'created_at' => [
          'type' => 'datetime',
          'default' => 'CURRENT_TIMESTAMP'
        ],
        'updated_at' => [
          'type' => 'time',
          'default' => 'CURRENT_TIME'
        ]
      ]
    ];

    $expected = "CREATE TABLE users (
  id bytea NOT NULL,
  username varchar(255) NOT NULL,
  user_role role NOT NULL DEFAULT 'user',
  user_permission permission DEFAULT NULL,
  balance decimal(10,2) DEFAULT NULL,
  balance_before real NOT NULL DEFAULT 0,
  created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at time NOT NULL DEFAULT CURRENT_TIME,
  PRIMARY KEY (id),
  CONSTRAINT username_unique UNIQUE (username)
);
CREATE INDEX created_at_index ON users (created_at);
CREATE INDEX updated_at_index ON users (updated_at);
CREATE INDEX created_updated_at_index ON users (created_at,updated_at);
";

    self::$pgsql->rawQuery('DROP TYPE IF EXISTS role');
    self::$pgsql->rawQuery('DROP TYPE IF EXISTS permission');
    self::$pgsql->rawQuery("CREATE TYPE role AS ENUM ('super_admin', 'admin', 'user')");
    self::$pgsql->rawQuery("CREATE TYPE permission AS ENUM ('read', 'write')");

    $this->assertSame(
      $expected,
      self::$pgsql->getCreate('users', $cfg)
    );

    try {
      // Cannot execute multiple commands in to the statement so will do multiple calls
      foreach (explode(';', trim($expected)) as $stmt) {
        if (empty($stmt)) {
          continue;
        }
        self::$pgsql->rawQuery($stmt);
      }
    } catch (\Exception $e) {
      $error = $e->getMessage();
    }

    $this->assertTrue(!isset($error), $error ?? '');
  }

  /** @test */
  public function getCreate_method_returns_a_string_with_create_table_statement_when_model_is_not_provided()
  {
    $this->setCacheExpectations();

    $this->createTable('user_profiles', function () {
      return 'id serial PRIMARY KEY,
              name varchar(20) NOT NULL';
    });

    $this->createTable('users', function () {
      return "id bytea NOT NULL,
              username varchar(255) NOT NULL,
              profile_id integer NOT NULL,
              user_role role NOT NULL DEFAULT 'user',
              user_permission permission DEFAULT NULL,
              balance decimal(10,2) DEFAULT NULL,
              balance_before real NOT NULL DEFAULT 0,
              created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at time NOT NULL DEFAULT CURRENT_TIME,
              PRIMARY KEY (id),
              CONSTRAINT username_unique UNIQUE (username),
              CONSTRAINT profile_id_foreign_key FOREIGN KEY (profile_id) REFERENCES user_profiles (id)";
    });

    self::$pgsql->rawQuery('CREATE INDEX created_at_index ON users (created_at)');
    self::$pgsql->rawQuery('CREATE INDEX updated_at_index ON users (updated_at)');
    self::$pgsql->rawQuery('CREATE INDEX created_updated_at_index ON users (created_at, updated_at)');

    $expected = "CREATE TABLE users (
  id bytea NOT NULL,
  username character varying(255) NOT NULL,
  profile_id integer NOT NULL,
  user_role role NOT NULL DEFAULT 'user',
  user_permission permission DEFAULT NULL,
  balance numeric(10,2) DEFAULT NULL,
  balance_before real NOT NULL DEFAULT 0,
  created_at timestamp without time zone NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at time without time zone NOT NULL DEFAULT CURRENT_TIME,
  CONSTRAINT username_unique UNIQUE (username),
  PRIMARY KEY (id),
  CONSTRAINT some_constraint FOREIGN KEY (profile_id) REFERENCES user_profiles (id)
);
CREATE INDEX created_at_index ON users (created_at);
CREATE INDEX created_updated_at_index ON users (created_at,updated_at);
CREATE INDEX updated_at_index ON users (updated_at);
";

    $result = self::$pgsql->getCreate('users');

    $this->assertSame(
      $expected,
      preg_replace('@CONSTRAINT (.*) FOREIGN@', 'CONSTRAINT some_constraint FOREIGN', $result)
    );

    $this->dropTableIfExist('users');

    try {
      foreach (explode(';', trim($expected)) as $stmt) {
        if (empty($stmt)) {
          continue;
        }

        self::$pgsql->rawQuery($stmt);
      }

    } catch (\Exception $e) {
      $error = $e->getMessage();
    }

    $this->assertTrue(!isset($error), $error ?? '');
  }

  /** @test */
  public function getCreate_method_returns_a_string_with_create_table_statement_considering_constraints()
  {
    $cfg = [
      'fields' => [
        'email' => [
          'type' => 'varchar',
          'maxlength' => 255
        ],
      ],
      'keys' => [
        'email_unique' => [
          'unique' => true,
          'columns' => ['email'],
          'ref_table' => 'user_emails',
          'ref_column' => 'email',
          'delete' => 'CASCADE'
        ]
      ]
    ];

    $expected = "CREATE TABLE users (
  email varchar(255) NOT NULL,
  CONSTRAINT email_unique UNIQUE (email),
  CONSTRAINT r7yl421 FOREIGN KEY (email) REFERENCES user_emails (email) ON DELETE CASCADE
)";

    $this->assertStringContainsString(
      'FOREIGN KEY (email) REFERENCES user_emails (email) ON DELETE CASCADE',
      $result = self::$pgsql->getCreate('users', $cfg)
    );

    $this->createTable('user_emails', function () {
      return 'email varchar(255) UNIQUE';
    });

    try {
      self::$pgsql->rawQuery($result);
    } catch (\Exception $e) {
      $error = $e->getMessage();
    }

    $this->assertTrue(!isset($error), $error ?? '');
  }

  /** @test */
  public function getCreate_method_returns_a_string_with_create_statement_as_is_from_getCreateTable_when_keys_index_does_not_exist()
  {
    $cfg = [
      'fields' => [
        'email' => [
          'type' => 'varchar',
          'maxlength' => 255
        ],
      ]
    ];

    $expected = 'CREATE TABLE users (
  email varchar(255) NOT NULL
)';

    $this->assertSame(
      $expected,
      $result = self::$pgsql->getCreate('users', $cfg)
    );

    try {
      self::$pgsql->rawQuery($result);
    } catch (\Exception $e) {
      $error = $e->getMessage();
    }

    $this->assertTrue(!isset($error), $error ?? '');
  }

  /** @test */
  public function getCreate_method_returns_empty_string_when_getCreateTable_returns_empty_string()
  {
    $pgsql = \Mockery::mock(Pgsql::class)->makePartial();

    $pgsql->shouldReceive('getCreateTable')
      ->once()
      ->with('users', ['fields' => []])
      ->andReturn('');

    $this->assertSame('', $pgsql->getCreate('users', ['fields' => []]));
  }

  /** @test */
  public function createIndex_method_creates_an_index_for_the_given_table_and_column()
  {
    $this->createTable('users', function () {
      return 'email varchar NOT NULL';
    });

    $result = self::$pgsql->createIndex('users', 'email', true, 10);

    $model = $this->getTableStructure('users');

    $this->assertTrue($result);
    $this->assertTrue(isset($model['keys']['users_email']['unique']));
    $this->assertSame(1, $model['keys']['users_email']['unique']);

    // Another test with a not unique key
    $this->setNonPublicPropertyValue('cache', []);

    $this->createTable('users', function () {
      return 'email varchar NOT NULL,
              username varchar NOT NULL';
    });

    $result2 = self::$pgsql->createIndex('users', 'username', false, 11);

    $model2 = $this->getTableStructure('users');

    $this->assertTrue($result2);
    $this->assertTrue(isset($model2['keys']['users_username']['unique']));
    $this->assertSame(0, $model2['keys']['users_username']['unique']);
  }

  /** @test */
  public function createIndex_method_throws_an_exception_when_column_has_a_not_valid_name_and_mode_is_die()
  {
    $this->expectException(\Exception::class);

    self::$pgsql->setErrorMode(Errors::E_DIE);

    self::$pgsql->createIndex('users', 'email**&');
  }

  /** @test */
  public function createIndex_method_returns_false_when_the_given_table_name_is_not_a_valid_name()
  {
    $this->assertFalse(
      self::$pgsql->createIndex('users***&', 'email')
    );
  }

  /** @test */
  public function deleteIndex_drops_an_index_for_the_given_table_and_index_name()
  {
    $this->createTable('users', function () {
      return 'username varchar NOT NULL';
    });

    self::$pgsql->rawQuery('CREATE UNIQUE INDEX username_unique ON users(username)');

    try {
      self::$pgsql->deleteIndex('users', 'username_unique');
    } catch (\Exception $e) {
      $error = $e->getMessage();
    }

    $this->assertTrue(!isset($error), $error ?? '');
    $this->assertEmpty($this->getTableStructure('users')['keys']);
  }

  /** @test */
  public function deleteIndex_method_returns_false_when_the_given_index_name_is_not_a_valid_name()
  {
    $this->assertFalse(
      self::$pgsql->deleteIndex('users', 'username***')
    );
  }

  /** @test */
  public function newInstance_method_returns_a_new_instance_of_the_class_with_fancy_checking()
  {
    $method = $this->getNonPublicMethod('newInstance');

    $result = $method->invoke(self::$pgsql, $this->getNonPublicProperty('cfg'));

    $this->assertInstanceOf(Pgsql::class, $result);
    $this->assertSame(1, $this->getNonPublicProperty('_fancy', $result));

    $this->setNonPublicPropertyValue('_fancy', false);

    $result2 = $method->invoke(self::$pgsql, $this->getNonPublicProperty('cfg'));

    $this->assertInstanceOf(Pgsql::class, $result2);
    $this->assertSame(false, $this->getNonPublicProperty('_fancy', $result2));
  }

  /** @test */
  public function getEngine_method_returns_engines_name()
  {
    $this->assertSame('pgsql', self::$pgsql->getEngine());
  }

  /** @test */
  public function getCurrent_method_returns_the_current_database()
  {
    $this->assertSame(
      self::getDbConfig()['db'],
      self::$pgsql->getCurrent()
    );
  }

  /** @test */
  public function escape_method_returns_an_escaped_db_expression_from_the_given_item()
  {
    $this->assertSame('users', self::$pgsql->escape('users'));
    $this->assertSame('db_test.users', self::$pgsql->escape('db_test.users'));
  }

  /** @test */
  public function escape_method_throws_an_exception_when_the_given_item_is_not_valid()
  {
    $this->expectException(\Exception::class);

    self::$pgsql->escape('users**');
  }

  /** @test */
  public function tableFullName_method_returns_table_full_name()
  {
    $db = self::getDbConfig()['db'];

    $this->assertSame(
      "users",
      self::$pgsql->tableFullName('users', true)
    );

    $this->assertSame(
      "users",
      self::$pgsql->tableFullName('users', false)
    );
  }

  /** @test */
  public function tableFullName_method_returns_null_when_the_given_name_is_not_valid()
  {
    $this->assertNull(
      self::$pgsql->tableFullName('users***')
    );

    $this->assertNull(
      self::$pgsql->tableFullName('db***.users')
    );

    $this->assertNull(
      self::$pgsql->tableFullName('db.users***')
    );
  }

  /** @test */
  public function tableSimpleName_method_returns_table_simple_name()
  {
    $this->assertSame(
      'users',
      self::$pgsql->tableSimpleName('db.users', true)
    );

    $this->assertSame(
      'users',
      self::$pgsql->tableSimpleName('db.users', false)
    );

    $this->assertSame(
      'users',
      self::$pgsql->tableSimpleName('users', true)
    );

    $this->assertSame(
      'users',
      self::$pgsql->tableSimpleName('users', false)
    );

    $this->assertSame(
      'users',
      self::$pgsql->tableSimpleName('db.users.email', true)
    );

    $this->assertSame(
      'users',
      self::$pgsql->tableSimpleName('db.users.email', false)
    );
  }

  /** @test */
  public function tableSimpleName_method_returns_null_when_the_given_table_name_is_not_valid()
  {
    $this->assertNull(
      self::$pgsql->tableSimpleName('users**&')
    );


    $this->assertNull(
      self::$pgsql->tableSimpleName('db.users*&')
    );

    $this->assertNull(
      self::$pgsql->tableSimpleName('')
    );
  }

  /** @test */
  public function colFullName_method_returns_column_full_name()
  {
    $this->assertSame(
      'users.email',
      self::$pgsql->colFullName('email', 'users', true)
    );

    $this->assertSame(
      'users.email',
      self::$pgsql->colFullName('email', 'users', false)
    );

    $this->assertSame(
      'users.email',
      self::$pgsql->colFullName('users.email', null, true)
    );

    $this->assertSame(
      'users.email',
      self::$pgsql->colFullName('users.email', null, false)
    );
  }

  /** @test */
  public function colFullName_method_returns_null_when_the_given_col_or_table_names_is_not_valid()
  {
    $this->assertNull(
      self::$pgsql->colFullName('email')
    );

    $this->assertNull(
      self::$pgsql->colFullName('email**', 'users')
    );

    $this->assertNull(
      self::$pgsql->colFullName('email', 'users**')
    );

    $this->assertNull(
      self::$pgsql->colFullName('email&&', 'users**')
    );
  }

  /** @test */
  public function colSimpleName_method_returns_column_simple_name()
  {
    $this->assertSame(
      'email',
      self::$pgsql->colSimpleName('users.email', true)
    );

    $this->assertSame(
      'email',
      self::$pgsql->colSimpleName('users.email', false)
    );

    $this->assertSame(
      'email',
      self::$pgsql->colSimpleName('email', true)
    );

    $this->assertSame(
      'email',
      self::$pgsql->colSimpleName('db.users.email', true)
    );

    $this->assertSame(
      'email',
      self::$pgsql->colSimpleName('db.users.email', false)
    );
  }

  /** @test */
  public function colSimpleName_method_returns_null_when_the_given_column_name_is_not_valid()
  {
    $this->assertNull(
      self::$pgsql->colSimpleName('email**')
    );

    $this->assertNull(
      self::$pgsql->colSimpleName('users.email&&')
    );

    $this->assertNull(
      self::$pgsql->colSimpleName('db.users.email&&')
    );
  }

  /** @test */
  public function isTableFullName_method_checks_if_the_given_table_name_is_full_name()
  {
    $this->assertTrue(
      self::$pgsql->isTableFullName('db.users')
    );

    $this->assertFalse(
      self::$pgsql->isTableFullName('users')
    );
  }

  /** @test */
  public function isColFullName_method_checks_if_the_given_col_name_is_a_full_name()
  {
    $this->assertTrue(
      self::$pgsql->isColFullName('users.email')
    );

    $this->assertFalse(
      self::$pgsql->isColFullName('email')
    );
  }

  /** @test */
  public function rawQuery_method_executes_the_given_query_using_original_pdo_function()
  {
    $this->createTable('users', function () {
      return 'id BIGSERIAL PRIMARY KEY,
              username VARCHAR(255) NOT NULL';
    });

    $this->insertOne('users', ['username' => 'foo']);

    $result = self::$pgsql->rawQuery("SELECT * FROM users");

    $this->assertInstanceOf(\PDOStatement::class, $result);
    $this->assertSame('foo', $result->fetchObject()->username);
  }

  /** @test */
  public function parseQuery_method_parses_an_sql_and_return_an_array()
  {
    $result = self::$pgsql->parseQuery(
      "SELECT * FROM users WHERE created_at > now()"
    );

    $this->assertIsArray($result);
    $this->assertArrayHasKey('SELECT', $result);
    $this->assertArrayHasKey('FROM', $result);
    $this->assertArrayHasKey('WHERE', $result);

    $this->assertSame('users', $result['FROM'][0]['table']);
    $this->assertSame('created_at', $result['WHERE'][0]['base_expr']);
    $this->assertSame('>', $result['WHERE'][1]['base_expr']);
    $this->assertSame('now', $result['WHERE'][2]['base_expr']);
  }

  /** @test */
  public function parseQuery_method_returns_null_when_the_given_arg_is_not_a_query()
  {
    $this->assertNull(
     self::$pgsql->parseQuery('foo')
    );
  }

  /** @test */
  public function getQueryValues_method_returns_query_values()
  {
    $cfg = [
      'values' => [
        'id' => '7f4a2c70bcac11eba47652540000cfbe',
        'username' => 'smith66',
        'name'     => 'John Doe',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'created_at' => '2021-07-30 17:04:00',
        'updated_at' => '2021-07-30 22:04',
        'role'       => 'user'
      ],
      'values_desc' => [
        'id' => [
          'type'      => 'binary',
          'maxlength' => 16,
        ],
        'username' => [
          'type' => 'varchar',
          'maxlength' => 255,
          'operator' => 'doesnotcontain'
        ],
        'name' => [
          'type' => 'varchar',
          'operator' => 'contains'
        ],
        'first_name' => [
          'type' => 'varchar',
          'operator' => 'startswith'
        ],
        'last_name' => [
          'type' => 'varchar',
          'operator' => 'endswith'
        ],
        'created_at' => [
          'type' => 'datetime'
        ],
        'updated_at' => [
          'type' => 'datetime'
        ],
        'role' => [
          'type' => 'varchar'
        ]
      ]
    ];

    $result   = self::$pgsql->getQueryValues($cfg);
    $expected = [
      hex2bin('7f4a2c70bcac11eba47652540000cfbe'),
      "%smith66%",
      "%John Doe%",
      "John%",
      "%Doe",
      "2021-07-30 17:04:00",
      "2021-07-30 22:04%",
      "user"
    ];

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getQueryValues_method_returns_empty_array_when_the_given_configuration_has_empty_values_key()
  {
    $this->assertSame([], self::$pgsql->getQueryValues([]));
    $this->assertSame([], self::$pgsql->getQueryValues(['values' => []]));
  }

  /** @test */
  public function getFieldsList_method_returns_fields_list_for_the_given_tables()
  {
    $this->createTable('users', function () {
      return 'username VARCHAR(255), name VARCHAR(255)';
    });

    $this->createTable('roles', function () {
      return 'name VARCHAR(255)';
    });

    $this->assertSame(
      ['users.username', 'users.name'],
      self::$pgsql->getFieldsList('users')
    );

    $this->assertSame(
      ['users.username', 'users.name', 'roles.name'],
      self::$pgsql->getFieldsList(['users', 'roles'])
    );
  }

  /** @test */
  public function getFieldsList_method_throws_an_exception_when_table_not_found()
  {
    $this->expectException(\Exception::class);

    self::$pgsql->getFieldsList('users');
  }

  /** @test */
  public function getPrimary_method_returns_primary_keys_of_a_table_as_an_array()
  {
    $this->createTable('users', function () {
      return 'id bigserial PRIMARY KEY,
              username varchar NOT NULL';
    });

    $this->assertSame(['id'], self::$pgsql->getPrimary('users'));
    $this->assertSame([], self::$pgsql->getPrimary('roles'));
  }

  /** @test */
  public function getUniquePrimary_method_returns_the_unique_primary_key_of_the_given_table_as_string()
  {
    $this->createTable('users', function () {
      return 'id bigserial PRIMARY KEY,
              username varchar NOT NULL';
    });

    $this->createTable('roles', function () {
      return 'name varchar NOT NULL';
    });

    $this->assertSame(
      'id',
      self::$pgsql->getUniquePrimary('users')
    );

    $this->assertNull(
      self::$pgsql->getUniquePrimary('roles')
    );
  }

  /** @test */
  public function getUniqueKeys_method_returns_the_unique_keys_of_the_given_table_as_an_array()
  {
    $this->createTable('users', function () {
      return 'username VARCHAR(22) NOT NULL, 
      email VARCHAR(100) NOT NULL,
      name VARCHAR(200) NOT NULL';
    });

    self::$pgsql->rawQuery("CREATE UNIQUE INDEX username_unique ON users (email)");

    $this->assertSame(
      ['email'],
      self::$pgsql->getUniqueKeys('users')
    );

    $this->createTable('users', function () {
      return 'username VARCHAR(22) NOT NULL, 
      email VARCHAR(100) NOT NULL,
      name VARCHAR(200) NOT NULL';
    });

    self::$pgsql->rawQuery("CREATE UNIQUE INDEX username_email_unique ON users (username, email)");

    $this->assertSame(
      ['username', 'email'],
      self::$pgsql->getUniqueKeys('users')
    );
  }

  /** @test */
  public function getUniqueKeys_method_returns_empty_array_when_table_has_no_unique_indexes()
  {
    $this->createTable('users', function () {
      return 'username varchar NOT NULL';
    });

    $this->assertEmpty(
      self::$pgsql->getUniqueKeys('users')
    );
  }

  /** @test */
  public function arrangeConditions_method_test()
  {
    $cfg = [
      'available_fields' => [
        'users.username' => 'username',
        'users.id' => 'id',
        'name' => 'name'
      ],
      'tables' => ['users' => 'users']
    ];

    $conditions = [
      'conditions' => [[
        'field' => 'username'
      ],[
        'field' => 'name'
      ],[
        'conditions' => [[
          'field' => 'id'
        ]]
      ]]
    ];

    $expected = [
      'conditions' => [[
        'field' => 'users.username'
      ],[
        'field' => 'name'
      ],[
        'conditions' => [[
          'field' => 'users.id'
        ]]
      ]]
    ];

    self::$pgsql->arrangeConditions($conditions, $cfg);

    $this->assertSame($expected, $conditions);
  }

  /** @test */
  public function removeVirtual_method_test()
  {
    $cfg = [
      'fields' => [
        'username', 'id'
      ],
      'values' => [
        'jdoe', 1
      ],
      'available_fields' => [
        'username' => 'username'
      ],
      'models' => [
        'username' => [
          'fields' => [
            'username' => [
              'virtual' => true
            ]
          ]
        ]
      ]
    ];

    $expected = [
      'fields' => [
        'id'
      ],
      'values' => [
        1
      ],
      'available_fields' => [
        'username' => 'username'
      ],
      'models' => [
        'username' => [
          'fields' => [
            'username' => [
              'virtual' => true
            ]
          ]
        ]
      ]
    ];

    $this->assertSame(
      $expected,
      self::$pgsql->removeVirtual($cfg)
    );
  }

  /** @test */
  public function getSelect_method_generates_a_string_with_select_statement_from_the_given_arguments()
  {
    $cfg = [
      'tables' => [
        'users' => 'users',
        'roles'   => 'roles'
      ],
      'fields' => ['id', 'username', 'unique_roles' => 'role_name', 'cfg'],
      'available_fields' => [
        'id' => 'users',
        'username' => 'users',
        'role_name'  => 'roles',
        'cfg' => 'users'
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

    $this->createTable('users', function () {
      return 'id bytea NOT NULL PRIMARY KEY,
              username varchar NOT NULL,
              cfg TEXT NOT NULL';
    });

    $this->createTable('roles', function () {
      return 'id serial PRIMARY KEY,
              role_name varchar NOT NULL';
    });

    $result   = self::$pgsql->getSelect($cfg);
    $expected = "SELECT LOWER(encode(users.id, 'hex')) AS id, users.username, roles.role_name AS unique_roles, users.cfg
FROM users, roles
";

    $this->assertSame($expected, $result);

    try {
      self::$pgsql->rawQuery($expected);
    } catch (\Exception $e) {
      $error = $e->getMessage();
    }

    $this->assertTrue(!isset($error), $error ?? '');
  }

  /** @test */
  public function getSelect_method_generates_a_string_with_select_statement_when_there_is_a_count()
  {
    $cfg = [
      'tables' => ['users' => 'users'],
      'fields' => ['id', 'username'],
      'count'   => ['id'],
      'available_fields' => [
        'id' => 'users',
        'username' => 'users',
      ],
    ];

    $result   = self::$pgsql->getSelect($cfg);
    $expected = "SELECT COUNT(*)
FROM users
";

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getSelect_method_generates_a_string_with_select_statement_when_there_is_a_count_and_group_by()
  {
    $cfg = [
      'tables' => ['users' => 'users', 'roles' => 'roles'],
      'fields' => ['count_users' => 'id', 'username', 'role_name'],
      'count'   => ['id', 'role_name'],
      'group_by'  => [
        'id', 'role_name'
      ],
      'available_fields' => [
        'id' => 'users',
        'username' => 'users',
        'role_name' => 'roles'
      ],
    ];

    $result   = self::$pgsql->getSelect($cfg);
    // Looks like it's not correct, is it intended to be like that??
    $expected = "SELECT COUNT(*) FROM ( SELECT 
FROM users, roles
";

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getSelect_method_generates_a_string_with_select_statement_when_there_is_a_count_and_group_by_and_having()
  {
    $cfg = [
      'tables' => ['users' => 'users', 'roles' => 'roles'],
      'fields' => ['count_users' => 'users.id', 'users.username', 'roles.role_name'],
      'count'   => ['id', 'role_name'],
      'group_by'  => [
        'id', 'role_name'
      ],
      'available_fields' => [
        'id' => 'users',
        'username' => 'users',
        'role_name' => 'roles'
      ],
      'having' => [
        'conditions' => [
          ['field' => 'id', 'value' => '2'],
          ['field' => 'username', 'value' => 'foo'],
        ]
      ]
    ];

    $result   = self::$pgsql->getSelect($cfg);
    // Looks like it's not correct, is it intended to be like that??
    $expected = "SELECT COUNT(*) FROM ( SELECT 
FROM users, roles
";

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getSelect_method_sets_an_error_when_available_fields_missing_a_field()
  {
    $this->expectException(\Exception::class);
    self::$pgsql->setErrorMode(Errors::E_DIE);

    $cfg = [
      'tables' => ['users'],
      'fields' => ['id'],
      'available_fields' => [
        'username' => 'users'
      ]
    ];

    $this->assertSame('', self::$pgsql->getSelect($cfg));
  }

  /** @test */
  public function getInsert_method_generates_a_string_for_insert_statement_from_the_given_arguments()
  {
    $cfg = [
      'tables' => ['users'],
      'fields' => [
        'id', 'username'
      ],
      'available_fields' => [
        'id' => 'users',
        'username' => 'users'
      ],
      'models' => [
        'users' => [
          'fields' => [
            'id' => [
              'type' => 'binary',
              'key'   => 'foo',
              'maxlength' => 16,
              'null' => false
            ],
            'username' => [
              'type' => 'string'
            ],
            'name' => [
              'type' => 'string'
            ]
          ]
        ]
      ]
    ];

    $result   = self::$pgsql->getInsert($cfg);
    $expected = "INSERT INTO users
(id, username)
 VALUES (?, ?)
";

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getInsert_method_generates_a_string_for_insert_statement_from_the_given_arguments_and_ignore_exists()
  {
    $cfg = [
      'tables' => ['users'],
      'fields' => [
        'id', 'username'
      ],
      'available_fields' => [
        'id' => 'users',
        'username' => 'users'
      ],
      'models' => [
        'users' => [
          'fields' => [
            'id' => [
              'type' => 'binary',
              'key'   => 'foo',
              'maxlength' => 16,
              'null' => false
            ],
            'username' => [
              'type' => 'string'
            ],
            'name' => [
              'type' => 'string'
            ]
          ]
        ]
      ],
      'ignore' => true
    ];


    $result   = self::$pgsql->getInsert($cfg);
    $expected = "INSERT IGNORE INTO users
(id, username)
 VALUES (?, ?)
";

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getInsert_method_returns_an_empty_string_when_tables_config_has_more_than_one_table()
  {
    $cfg = [
      'tables' => ['users', 'roles'],
      'fields' => [
        'id', 'username'
      ],
      'available_fields' => [
        'id' => 'users',
        'username' => 'users'
      ],
      'models' => [
        'users' => [
          'fields' => [
            'id' => [
              'type' => 'binary',
              'key'   => 'foo',
              'maxlength' => 16,
              'null' => false
            ],
            'username' => [
              'type' => 'string'
            ],
            'name' => [
              'type' => 'string'
            ]
          ]
        ]
      ],
      'ignore' => true
    ];

    $this->assertSame('', self::$pgsql->getInsert($cfg));
  }

  /** @test */
  public function getInsert_method_sets_an_error_when_a_field_does_not_exist_in_available_fields()
  {
    self::$pgsql->setErrorMode(Errors::E_DIE);

    $this->expectException(\Exception::class);

    $cfg = [
      'tables' => ['users'],
      'fields' => [
        'id', 'username'
      ],
      'available_fields' => [
        'id' => 'users',
      ],
      'models' => [
        'users' => [
          'fields' => [
            'id' => [
              'type' => 'binary',
              'key'   => 'foo',
              'maxlength' => 16,
              'null' => false
            ],
            'username' => [
              'type' => 'string'
            ]
          ]
        ]
      ]
    ];

    self::$pgsql->getInsert($cfg);
  }

  /** @test */
  public function getInsert_method_sets_an_error_when_available_table_does_not_exist_in_models()
  {
    self::$pgsql->setErrorMode(Errors::E_DIE);

    $this->expectException(\Exception::class);

    $cfg = [
      'tables' => ['users'],
      'fields' => [
        'id', 'username'
      ],
      'available_fields' => [
        'id' => 'users',
        'username' => 'users',
      ],
      'models' => []
    ];

    self::$pgsql->getInsert($cfg);
  }
}