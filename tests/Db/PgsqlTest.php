<?php

namespace Db;

use bbn\Cache;
use bbn\Db\Enums\Errors;
use bbn\Db\Languages\Pgsql;
use bbn\Db\Query;
use bbn\Str;
use PHPUnit\Framework\TestCase;
use tests\Files;
use tests\Reflectable;
use tests\ReflectionHelpers;

class PgsqlTest extends TestCase
{
  use Reflectable, Files;

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
      'port'          => getenv('pgsql_db_port'),
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
        'code_host' => "{$db_config['user']}@{$db_config['host']}",
        'args'      => ["pgsql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['db']}",
          $db_config['user'],
          $db_config['pass'],
          [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'],
        ]
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
        'type' => 'binary',
        'udt_name' => 'bytea',
        'null' => 1,
        'key' => null,
        'extra' => '',
        'signed' => false,
        'virtual' => false,
        'generation' => null,
        'default' => 'NULL',
        'maxlength' => 16
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

    $this->createTable('users', function () {
      return 'id int NOT NULL, username varchar NOT NULL';
    });

    $pdo = $this->getNonPublicProperty('pdo');
    $stmt = $pdo->prepare($expected);
    $stmt->execute([1, 'jdoe']);

    $this->assertDatabaseHas('users', 'username', 'jdoe');

    $this->assertSame(trim($expected), trim($result));
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
    $expected = "INSERT INTO users
(id, username)
 VALUES (?, ?)
 ON CONFLICT DO NOTHING
";

    $this->createTable('users', function () {
      return 'id int NOT NULL, username varchar UNIQUE NOT NULL';
    });

    $pdo = $this->getNonPublicProperty('pdo');
    $stmt = $pdo->prepare($expected);
    $stmt->execute([1, 'jdoe']);
    $stmt->execute([2, 'jdoe']);

    $this->assertDatabaseHas('users', 'username', 'jdoe');
    $this->assertDatabaseHas('users', 'id', 1);
    $this->assertDatabaseDoesNotHave('users', 'id', 2);

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

  /** @test */
  public function getUpdate_method_returns_string_for_update_statement_from_the_give_arguments()
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

    $result   = self::$pgsql->getUpdate($cfg);
    $expected = "UPDATE users SET id = ?,
username = ?
";

    $this->createTable('users', function () {
      return 'id int, username varchar';
    });

    $this->insertOne('users', ['id' => 1, 'username' => 'jdoe']);

    $pdo = $this->getNonPublicProperty('pdo');
    $stmt = $pdo->prepare($expected);
    $stmt->execute([1, 'jdoe2']);

    $this->assertDatabaseHas('users', 'username', 'jdoe2');

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getUpdate_method_returns_string_for_update_statement_from_the_give_arguments_and_ignore_exists()
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

    $result   = self::$pgsql->getUpdate($cfg);
    $expected = "UPDATE users SET id = ?,
username = ?
";

    $this->createTable('users', function () {
      return 'id int, username varchar UNIQUE NOT NULL';
    });

    $this->insertOne('users', ['id' => 1, 'username' => 'jdoe']);

    $pdo = $this->getNonPublicProperty('pdo');
    $stmt = $pdo->prepare($expected);
    $stmt->execute([4, 'jdoe']);

    $this->assertDatabaseHas('users', 'id', 4);
    $this->assertDatabaseDoesNotHave('users', 'id', 1);

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getUpdate_method_returns_an_empty_string_when_tables_config_has_more_than_one_table()
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

    $this->assertSame('', self::$pgsql->getUpdate($cfg));
  }

  /** @test */
  public function getUpdate_method_sets_an_error_when_a_field_does_not_exist_in_available_fields()
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

    self::$pgsql->getUpdate($cfg);
  }

  /** @test */
  public function getUpdate_method_sets_an_error_when_available_table_does_not_exist_in_models()
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

    self::$pgsql->getUpdate($cfg);
  }

  /** @test */
  public function getDelete_method_returns_string_for_delete_statement()
  {
    $cfg = [
      'tables' => ['users']
    ];

    $result   = self::$pgsql->getDelete($cfg);
    $expected = "DELETE FROM users
";

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getDelete_method_returns_string_for_delete_statement_and_ignore_exists()
  {
    $cfg = [
      'tables' => ['users'],
      'ignore' => true,
      'join' => []
    ];

    $result   = self::$pgsql->getDelete($cfg);
    $expected = "DELETE FROM users
";

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getDelete_method_returns_string_for_delete_statement_and_join_exists()
  {
    $cfg = [
      'tables' => ['users'],
      'join' => ['roles']
    ];

    $result   = self::$pgsql->getDelete($cfg);
    $expected = "DELETE users FROM users
";

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getDelete_method_returns_empty_string_when_tables_provided_are_more_than_one()
  {
    $cfg = [
      'tables' => ['users', 'roles']
    ];

    $this->assertSame('', self::$pgsql->getDelete($cfg));
  }

  /** @test */
  public function getJoin_method_returns_string_for_the_join_clause_in_the_query()
  {
    $cfg = [
      'join' => [
        [
          'table' => 'users',
          'on'    => [
            'conditions' => [[
              'field' => 'roles.user_id',
              'exp' => 'users.id',
              'operator'  => '='
            ]],
            'logic' => 'AND'
          ]
        ],
        [
          'table' => 'payments',
          'on'    => [
            'conditions' => [[
              'field' => 'payments.user_id',
              'exp' => 'users.id',
              'operator'  => '='
            ]],
            'logic' => 'AND'
          ]
        ]
      ]
    ];

    $result   = self::$pgsql->getJoin($cfg);
    $expected = " JOIN users
    ON roles.user_id = users.id
  JOIN payments
    ON payments.user_id = users.id
    ";

    $this->assertSame(trim($expected), trim($result));
  }

  /** @test */
  public function getJoin_method_returns_string_for_the_join_clause_in_the_query_and_type_and_alias_exists()
  {
    $cfg = [
      'join' => [
        [
          'table' => 'users',
          'type' => 'left',
          'alias' => 'u',
          'on'    => [
            'conditions' => [[
              'field' => 'roles.user_id',
              'exp' => 'users.id',
              'operator'  => '='
            ]],
            'logic' => 'AND'
          ]
        ]
      ]
    ];

    $result   = self::$pgsql->getJoin($cfg);
    $expected = " LEFT JOIN users AS u
    ON roles.user_id = users.id";

    $this->assertSame(trim($expected), trim($result));
  }

  /** @test */
  public function getJoin_method_returns_empty_string_when_configurations_are_missing()
  {
    $this->assertSame('', self::$pgsql->getJoin([]));

    $cfg = [
      'join' => [
        [
          'on'    => [
            'conditions' => [[
              'field' => 'roles.user_id',
              'exp' => 'users.id',
              'operator'  => '='
            ]],
            'logic' => 'AND'
          ]
        ]
      ]
    ];

    $this->assertSame('', self::$pgsql->getJoin($cfg));

    $cfg = [
      'join' => [
        [
          'table' => 'users',
          'on'    => [
            'conditions' => [[
              'field' => 'roles.user_id',
              'exp' => 'users.id',
              'operator'  => '='
            ]],
          ]
        ]
      ]
    ];

    $this->assertSame('', self::$pgsql->getJoin($cfg));

    $cfg = [
      'join' => [
        [
          'table' => 'users',
          'on'    => [
            'conditions' => [[
              'field' => 'roles.user_id',
              'exp' => 'users.id'
            ]],
            'logic' => 'AND'
          ]
        ]
      ]
    ];

    $this->assertSame('', self::$pgsql->getJoin($cfg));
  }

  /** @test */
  public function getWhere_method_returns_a_string_with_the_where_part_of_the_query()
  {
    $cfg = [
      'filters' => [
        'conditions' => [[
          'field' => 'roles.user_id',
          'exp' => 'users.id',
          'operator' => '='
        ], [
          'field' => 'roles.email',
          'operator' => 'like'
        ]],
        'logic' => 'AND'
      ]
    ];

    $result   = self::$pgsql->getWhere($cfg);
    $expected = "WHERE roles.user_id = users.id
AND roles.email LIKE ?";

    $this->assertSame($expected, trim($result));
  }

  /** @test */
  public function getWhere_method_returns_empty_string_when_some_of_configurations_are_missing()
  {
    $this->assertSame('', self::$pgsql->getWhere([]));

    $cfg = [
      'filters' => [
        'conditions' => [[
          'field' => 'roles.user_id',
          'exp' => 'users.id'
        ]],
        'logic' => 'AND'
      ]
    ];

    $this->assertSame('', self::$pgsql->getWhere($cfg));

    $cfg = [
      'filters' => [
        'conditions' => [[
          'operator' => '='
        ]],
        'logic' => 'AND'
      ]
    ];

    $this->assertSame('', self::$pgsql->getWhere($cfg));
  }

  /** @test */
  public function getGroupBy_method_returns_a_string_with_group_by_clause_of_the_query()
  {
    $cfg = [
      'group_by' => [
        'id', 'email', 'name'
      ],
      'available_fields' => [
        'id' => 'users',
        'name' => 'users'
      ]
    ];

    $result   = self::$pgsql->getGroupBy($cfg);
    $expected = 'GROUP BY id, name';

    $this->assertSame($expected, trim($result));
  }

  /** @test */
  public function getGroupBy_returns_empty_string_when_configurations_are_missing()
  {
    $this->assertSame('', self::$pgsql->getGroupBy(['group' => ['id']]));
    $this->assertSame('', self::$pgsql->getGroupBy([
      'group_by' => ['id']
    ]));

    $this->assertSame('', self::$pgsql->getGroupBy([
      'group_by' => ['id'],
      'available_fields' => [
        'username'
      ]
    ]));
  }

  /** @test */
  public function getGroupBy_method_sets_an_error_when_available_fields_config_missing_one_of_the_fields()
  {
    self::$pgsql->setErrorMode(Errors::E_DIE);

    $this->expectException(\Exception::class);

    self::$pgsql->getGroupBy([
      'group_by' => ['id'],
      'available_fields' => [
        'username'
      ]
    ]);
  }

  /** @test */
  public function getHaving_method_returns_a_string_for_the_having_clause_in_a_query()
  {
    $cfg = [
      'group_by' => ['id'],
      'having'   => [
        'conditions' => [[
          'field' => 'user_count',
          'value'   => 20,
          'operator' => '>='
        ]],
        'logic' => 'AND'
      ]
    ];

    $result   = self::$pgsql->getHaving($cfg);
    $expected = "HAVING 
  user_count >= ?";

    $this->assertSame($expected, trim($result));
  }

  /** @test */
  public function getHaving_method_returns_a_string_for_the_having_clause_in_a_query_and_count_exists()
  {
    $cfg = [
      'group_by' => ['id'],
      'having'   => [
        'conditions' => [[
          'field' => 'user_count',
          'value'   => 20,
          'operator' => '>='
        ]],
        'logic' => 'AND'
      ],
      'count' => true
    ];

    $result   = self::$pgsql->getHaving($cfg);
    $expected = "WHERE 
  user_count >= ?";

    $this->assertSame($expected, trim($result));
  }

  /** @test */
  public function getHaving_method_returns_empty_string_when_configuration_missing_some_items()
  {
    $this->assertSame('', self::$pgsql->getHaving([]));
    $this->assertSame('', self::$pgsql->getHaving([
      'having'   => [
        'conditions' => [[
          'field' => 'user_count',
          'value'   => 20,
          'operator' => '>='
        ]],
        'logic' => 'AND'
      ]
    ]));

    $this->assertSame('', self::$pgsql->getHaving(['group_by' => ['id']]));
    $this->assertSame('', self::$pgsql->getHaving([
      'group_by' => ['id'],
      'having'   => [
        'conditions' => [[
          'field' => 'user_count',
          'value'   => 20,
          'operator' => '>='
        ]]
      ]
    ]));

    $this->assertSame('', self::$pgsql->getHaving([
      'group_by' => ['id'],
      'having'   => [
        'conditions' => [[
          'field' => 'user_count',
          'value'   => 20,
        ]],
        'logic' => 'AND'
      ]
    ]));
  }

  /** @test */
  public function getOrder_method_returns_a_string_for_the_order_clause_in_a_query()
  {
    $cfg = [
      'order' => [
        'id' => [
          'field' => 'id_alias',
          'dir'   => 'DESC'
        ],
        'username' => [
          'field' => 'username'
        ],
        'first_name' => 'asc'
      ],
      'available_fields' => [
        'id_alias' => 'users',
        'username' => 'users',
        'first_name' => false
      ],
      'fields' => [
        'id_alias' => 'id'
      ]
    ];

    $result   = self::$pgsql->getOrder($cfg);
    $expected = 'ORDER BY id_alias DESC,
users.username ASC,
first_name ASC';

    $this->assertSame($expected, trim($result));
  }

  /** @test */
  public function getOrder_method_returns_empty_string_when_configurations_missing_some_items()
  {
    $this->assertSame('', self::$pgsql->getOrder([]));
    $this->assertSame('', self::$pgsql->getOrder([
      'order' => [
        'id' => 'asc'
      ],
      'available_fields' => [
        'username' => 'users'
      ]
    ]));
  }

  /** @test */
  public function getLimit_method_returns_a_string_wit_the_limit_clause_in_a_query()
  {
    $result   = self::$pgsql->getLimit(['limit' => 2]);
    $expected = 'LIMIT 2';

    $this->assertSame($expected, trim($result));

    $result   = self::$pgsql->getLimit(['limit' => 2, 'start' => 4]);
    $expected = 'LIMIT 2 OFFSET 4';

    $this->assertSame($expected, trim($result));
  }

  /** @test */
  public function getLimit_method_returns_empty_string_when_configurations_missing_the_limit_param()
  {
    $this->assertSame('', self::$pgsql->getLimit([]));
  }

  /** @test */
  public function getLimit_method_returns_empty_string_when_the_provided_limit_is_not_an_integer()
  {
    $this->assertSame('', self::$pgsql->getLimit(['limit' => 'foo']));
  }

  /** @test */
  public function getCreateConstraints_method_returns_string_with_create_constraints_statement()
  {
    $cfg = [
      'keys' => [
        [
          'ref_table'   => 'users',
          'ref_column'  => 'id',
          'columns'     => ['user_id'],
          'constraint'  => 'user_role'
        ],
        [
          'ref_table'   => 'users',
          'ref_column'  => 'id2',
          'columns'     => ['user_id2'],
          'constraint'  => 'user_role_2',
          'delete'      => 'CASCADE',
          'update'      => 'CASCADE'
        ]
      ]
    ];

    $result   = self::$pgsql->getCreateConstraints('roles', $cfg);
    $expected = 'ALTER TABLE roles
  ADD CONSTRAINT user_role FOREIGN KEY (user_id) REFERENCES users (id),
  ADD CONSTRAINT user_role_2 FOREIGN KEY (user_id2) REFERENCES users (id2) ON DELETE CASCADE ON UPDATE CASCADE;
';

    $this->assertSame(trim($expected), trim($result));

    return $expected;
  }

  /**
   * @test
   * @depends getCreateConstraints_method_returns_string_with_create_constraints_statement
   */
  public function getCreateConstraints_method_returns_string_with_create_constraints_statement_when_model_not_provided($query)
  {
    $this->createTable('users', function () {
      return "id int NOT NULL PRIMARY KEY,
              id2 int NOT NULL UNIQUE";
    });

    $this->createTable('roles', function () {
      return "user_id int NOT NULL,
              user_id2 int NOT NULL";
    });

    // Create the constraints from the query from the other test that this one depends on
    // So that the modelize method can get table structure
    try {
      self::$pgsql->rawQuery($query);
    } catch (\Exception $e) {
      $error = $e->getMessage();
    }

    $this->assertTrue(!isset($error), $error ?? '');

    // Set expectations for the methods called on Cache class in modelize method
    $this->setCacheExpectations();

    $result = self::$pgsql->getCreateConstraints('roles');

    $this->assertStringContainsString(
      'ALTER TABLE roles',
      trim($result)
    );

    $this->assertStringContainsString(
      'ADD CONSTRAINT user_role FOREIGN KEY (user_id) REFERENCES users (id)',
      trim($result)
    );

    $this->assertStringContainsString(
      'ADD CONSTRAINT user_role_2 FOREIGN KEY (user_id2) REFERENCES users (id2)',
      trim($result)
    );
  }

  /** @test */
  public function getCreateConstraints_method_returns_empty_string_when_configuration_missing_items()
  {
    $this->assertSame('', self::$pgsql->getCreateConstraints('roles', [
      'keys' => [
        ['ref_table' => '']
      ]
    ]));

    $this->assertSame('', self::$pgsql->getCreateConstraints('roles', [
      'keys' => [
        ['ref_table' => 'users']
      ]
    ]));

    $this->assertSame('', self::$pgsql->getCreateConstraints('roles', [
      'keys' => [
        ['ref_table' => 'users', 'columns' => ['users', 'roles']]
      ]
    ]));

    $this->assertSame('', self::$pgsql->getCreateConstraints('roles', [
      'keys' => [
        [
          'ref_table' => 'users',
          'columns'   => ['users']
        ]
      ]
    ]));

    $this->assertSame('', self::$pgsql->getCreateConstraints('roles', [
      'keys' => [
        [
          'ref_table'  => 'users',
          'columns'    => ['users'],
          'constraint' => 'user_role'
        ]
      ]
    ]));
  }

  /** @test */
  public function getCreateConstraints_method_returns_empty_string_when_model_failed_to_retrieve_table_data()
  {
    $pgsql = \Mockery::mock(Pgsql::class)->makePartial();

    $pgsql->shouldReceive('modelize')
      ->once()
      ->with('roles')
      ->andReturnNull();

    $result = $pgsql->getCreateConstraints('roles');

    $this->assertSame('', $result);
  }

  /** @test */
  public function remove_conditions_value_removes_values_from_the_given_conditions_array()
  {
    $method = $this->getNonPublicMethod('_remove_conditions_value');

    $cfg = [
      'conditions' => [
        [
          'value' => 'foo1',
          'field' => 'name'
        ],
        [
          'logic'      => 'AND',
          'conditions' => [
            [
              'value' => 'foo2',
              'field' => 'name2'
            ],
            [
              'value' => 'foo3',
              'field' => 'name3'
            ]
          ]
        ]
      ]
    ];

    $expected = [
      'hashed' => [
        'conditions' => [
          ['field' => 'name'],
          [
            'conditions' => [
              ['field' => 'name2'],
              ['field' => 'name3']
            ],
            'logic'      => 'AND'
          ]
        ]
      ],
      'values' => [
        'foo1', 'foo2', 'foo3'
      ]
    ];

    $result = $method->invoke(self::$pgsql, $cfg);

    $this->assertSame($expected, $result);

    $this->assertSame(
      ['hashed' => ['foo' => 'bar'], 'values' => []],
      $method->invoke(self::$pgsql, ['foo' => 'bar'])
    );
  }

  /** @test */
  public function findReferences_method_returns_an_array_with_foreign_key_references_for_the_given_column()
  {
    $this->createTable('users', function () {
      return 'id INT PRIMARY KEY,
              username VARCHAR(255) UNIQUE,
              created_at DATE DEFAULT NULL,
              role_id INT';
    });

    $this->createTable('roles', function () {
      return 'id INT PRIMARY KEY,
              name VARCHAR(255)';
    });

    self::$pgsql->rawQuery(
      'ALTER TABLE users ADD CONSTRAINT user_role_id
       FOREIGN KEY (role_id) REFERENCES roles (id) 
       ON UPDATE CASCADE ON DELETE RESTRICT'
    );

    $this->setCacheExpectations();

    $this->assertSame(
      ['users.role_id'],
      self::$pgsql->findReferences('roles.id')
    );

    $this->assertSame(
      [],
      self::$pgsql->findReferences('users.role_id')
    );
  }

  /** @test */
  public function findReferences_method_returns_Null_if_the_provided_column_name_does_not_have_table_name()
  {
    $this->assertNull(
      self::$pgsql->findReferences('role_id')
    );
  }

  /** @test */
  public function findReferences_method_returns_an_array_with_foreign_key_references_for_a_different_database()
  {
    $this->createDatabase('testing_db');

    $db_cfg = self::getDbConfig();
    $db_cfg['db'] = 'testing_db';

    $pgsql2 = new Pgsql($db_cfg);

    $this->createTable('users', function () {
      return 'id INT PRIMARY KEY,
              role_id INT';
    }, $pgsql2);

    $this->createTable('roles', function () {
      return 'id INT PRIMARY KEY,
              name VARCHAR(255)';
    }, $pgsql2);

    $pgsql2->rawQuery(
      'ALTER TABLE users ADD CONSTRAINT user_role_id
       FOREIGN KEY (role_id) REFERENCES roles (id) 
       ON UPDATE CASCADE ON DELETE RESTRICT'
    );

    $this->setCacheExpectations();

    $this->assertSame(
      ['users.role_id'],
      self::$pgsql->findReferences('roles.id', 'testing_db')
    );

    $this->assertSame(self::getDbConfig()['db'], self::$pgsql->getCurrent());

    $this->dropDatabaseIfExists('testing_db');
  }

  /** @test */
  public function findRelations_method_returns_an_array_of_a_table_that_has_relations_to_more_than_one_tables()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT PRIMARY KEY,
              role_id INT,
              profile_id INT';
    });

    $this->createTable('roles', function () {
      return 'id INT PRIMARY KEY,
              name VARCHAR(255)';
    });

    $this->createTable('profiles', function () {
      return 'id INT PRIMARY KEY,
              name VARCHAR(255)';
    });

    self::$pgsql->rawQuery(
      'ALTER TABLE users ADD CONSTRAINT user_role
       FOREIGN KEY (role_id) REFERENCES roles (id) 
       ON UPDATE CASCADE ON DELETE RESTRICT'
    );

    self::$pgsql->rawQuery(
      'ALTER TABLE users ADD CONSTRAINT user_profile
       FOREIGN KEY (profile_id) REFERENCES profiles (id) 
       ON UPDATE CASCADE ON DELETE RESTRICT'
    );

    $result   = self::$pgsql->findRelations('roles.id');
    $expected = [
      'users' => [
        'column' => 'role_id',
        'refs' => [
          'profile_id' => 'profiles.id'
        ]
      ]
    ];

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function findRelations_method_returns_an_array_of_a_table_that_has_relations_to_more_than_one_tables_for_different_database()
  {
    $this->createDatabase('db_testing');

    $db_cfg = self::getDbConfig();
    $db_cfg['db'] = 'db_testing';
    $pgsql2 = new Pgsql($db_cfg);

    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT PRIMARY KEY,
              role_id INT,
              profile_id INT';
    }, $pgsql2);

    $this->createTable('roles', function () {
      return 'id INT PRIMARY KEY,
              name VARCHAR(255)';
    }, $pgsql2);

    $this->createTable('profiles', function () {
      return 'id INT PRIMARY KEY,
              name VARCHAR(255)';
    }, $pgsql2);

    $pgsql2->rawQuery(
      'ALTER TABLE users ADD CONSTRAINT user_role
       FOREIGN KEY (role_id) REFERENCES roles (id) 
       ON UPDATE CASCADE ON DELETE RESTRICT'
    );

    $pgsql2->rawQuery(
      'ALTER TABLE users ADD CONSTRAINT user_profile
       FOREIGN KEY (profile_id) REFERENCES profiles (id) 
       ON UPDATE CASCADE ON DELETE RESTRICT'
    );

    $result   = self::$pgsql->findRelations('roles.id', 'db_testing');
    $expected = [
      'users' => [
        'column' => 'role_id',
        'refs' => [
          'profile_id' => 'profiles.id'
        ]
      ]
    ];

    $this->assertSame($expected, $result);

    $this->assertSame(
      self::getDbConfig()['db'],
      self::$pgsql->getCurrent()
    );

    $this->dropDatabaseIfExists('db_testing');
  }

  /** @test */
  public function findRelations_method_returns_null_when_the_given_name_does_not_has_column_name()
  {
    $this->assertNull(
      self::$pgsql->findRelations('id')
    );
  }

  /** @test */
  public function query_method_executes_a_statement_and_returns_the_affected_rows_for_writing_statements()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function() {
      return 'id serial PRIMARY KEY, name VARCHAR(255), username VARCHAR(255)';
    });

    $result = self::$pgsql->query($expected = 'INSERT INTO users (name, username) VALUES (?, ?)', 'John', 'jdoe');

    $this->assertSame(1, $result);
    $this->assertDatabaseHas('users', 'name', 'John');
    $this->assertDatabaseHas('users', 'username', 'jdoe');

    $queries = $this->getNonPublicProperty('queries');

    $this->assertIsArray($queries);
    $this->assertCount(1, $queries);

    $query = current($queries);

    $this->assertSame($expected, $query['sql']);
    $this->assertSame('INSERT', $query['kind']);

  }

  /** @test */
  public function query_method_executes_a_statement_and_returns_query_object_for_reading_statements()
  {
    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY,
              name VARCHAR(50) NOT NULL';
    });

    $this->insertMany('users', $expected_results = [
      ['name' => 'John'],
      ['name' => 'Sam']
    ]);

    $result = self::$pgsql->query($expected = 'SELECT name FROM users WHERE id >= ?', 1);

    $this->assertSame(
      $expected_results,
      self::$pgsql->fetchAllResults($result, \PDO::FETCH_ASSOC)
    );

    $this->assertInstanceOf(\PDOStatement::class, $result);
    $this->assertInstanceOf(Query::class, $result);

    $queries = $this->getNonPublicProperty('queries');

    $this->assertIsArray($queries);
    $this->assertCount(1, $queries);

    $query = current($queries);

    $this->assertSame($expected, $query['sql']);
    $this->assertSame('SELECT', $query['kind']);
  }

  /** @test */
  public function query_method_uses_the_saved_query()
  {
    $this->createTable('users', function () {
      return 'name VARCHAR(20), username VARCHAR(20)';
    });

    $this->insertMany('users', [
      ['name' => 'John', 'username' => 'jdoe'],
      ['name' => 'Sam', 'username' => 'sdoe'],
    ]);

    $result = self::$pgsql->query("SELECT username FROM users WHERE name = ?", 'John');

    $this->assertInstanceOf(\PDOStatement::class, $result);

    $this->assertSame(
      [['username' => 'jdoe']],
      self::$pgsql->fetchAllResults($result, \PDO::FETCH_ASSOC)
    );

    $result2 = self::$pgsql->query("SELECT username FROM users WHERE name = ?", 'Sam');

    $this->assertInstanceOf(\PDOStatement::class, $result2);

    $this->assertSame(
      [['username' => 'sdoe']],
      self::$pgsql->fetchAllResults($result2, \PDO::FETCH_ASSOC)
    );

    self::$pgsql->query("SELECT name FROM users WHERE username = ?", 'sdoe');

    $this->assertCount(
      2,
      $this->getNonPublicProperty('queries')
    );
  }

  /** @test */
  public function query_method_throws_an_exception_if_the_given_query_is_not_valid()
  {
    $this->expectException(\Exception::class);

    self::$pgsql->query('foo');
  }

  /** @test */
  public function query_method_sets_an_error_if_the_given_arguments_are_greater_than_query_placeholders()
  {
    $this->expectException(\Exception::class);

    self::$pgsql->setErrorMode(Errors::E_DIE);

    self::$pgsql->query('SELECT * FROM user where id = ? AND user = ?', 1, 4, 5);
  }

  /** @test */
  public function query_method_fills_the_missing_values_with_the_last_given_one_when_number_of_values_are_smaller_than_query_placeholders()
  {
    $this->createTable('users', function() {
      return 'id serial PRIMARY KEY, 
              name VARCHAR(255), 
              username VARCHAR(255)';
    });

    self::$pgsql->query('INSERT INTO users (name, username) VALUES (?, ?)', 'John');

    $this->assertDatabaseHas('users', 'name', 'John');
    $this->assertDatabaseHas('users', 'username', 'John');
  }

  /** @test */
  public function add_query_method_adds_to_queries_list_form_the_given_hash_and_arguments()
  {
    $method = $this->getNonPublicMethod('_add_query');

    $method->invokeArgs(self::$pgsql, [
      $hash = '12345',
      $stmt = 'SELECT * FROM users WHERE id = ?',
      $kind = 'SELECT',
      $placeholders = 1,
      $options = ['option_1' => 'option_1_value']
    ]);

    $queries = $this->getNonPublicProperty('queries');
    $query   = current($queries);

    $this->assertCount(1, $queries);
    $this->assertArrayHasKey('first', $query);
    $this->assertIsFloat($query['first']);
    unset($query['first']);

    $this->assertSame(
      [
        'sql' => $stmt,
        'kind' => $kind,
        'write' => false,
        'structure' => false,
        'placeholders' => $placeholders,
        'options' => $options,
        'num' => 0,
        'exe_time' => 0,
        'last' => 0,
        'prepared' => false
      ],
      $query
    );

    $list_queries = $this->getNonPublicProperty('list_queries');
    $list_query   = current($list_queries);

    $this->assertCount(1, $list_queries);
    $this->assertArrayHasKey('last', $list_query);
    $this->assertArrayHasKey('hash', $list_query);
    $this->assertSame($hash, $list_query['hash']);
  }

  /** @test */
  public function add_query_methods_removes_from_the_beginning_of_queries_list_if_max_queries_numbers_exceeded()
  {
    $method = $this->getNonPublicMethod('_add_query');

    $args = [
      'SELECT * FROM users WHERE id = ?',
      'SELECT',
      1,
      ['option_1' => 'option_1_value']
    ];

    $this->setNonPublicPropertyValue('max_queries', 2);

    $method->invoke(self::$pgsql, '123', ...$args);
    $method->invoke(self::$pgsql, '1234', ...$args);
    $method->invoke(self::$pgsql, '12345', ...$args);

    $queries = $this->getNonPublicProperty('queries');

    $this->assertCount(2, $queries);
    $this->assertArrayHasKey('1234', $queries);
    $this->assertArrayHasKey('12345', $queries);
    $this->assertArrayNotHasKey('123', $queries);

    $list_queries = $this->getNonPublicProperty('list_queries');

    $this->assertCount(2, $list_queries);

    $list_queries_hashes = array_map(function ($item) {
      return $item['hash'];
    }, $list_queries);

    $list_queries_hashes = array_flip($list_queries_hashes);

    $this->assertArrayHasKey('1234', $list_queries_hashes);
    $this->assertArrayHasKey('12345', $list_queries_hashes);
    $this->assertArrayNotHasKey('123', $list_queries_hashes);
  }

  /** @test */
  public function _remove_query_method_removes_from_the_beginning_of_queries_with_the_given_hash()
  {
    $method = $this->getNonPublicMethod('_remove_query');

    $this->setNonPublicPropertyValue('queries', [
      '123' => ['foo' => 'bar'],
      '1234' => ['foo2' => 'bar2'],
      '12345' => '123'
    ]);

    $method->invoke(self::$pgsql, '123');
    $method->invoke(self::$pgsql, '123456789');

    $this->assertSame(
      ['1234' => ['foo2' => 'bar2']],
      $this->getNonPublicProperty('queries')
    );
  }

  /** @test */
  public function update_query_updates_a_query_in_query_list_by_the_given_hash()
  {
    $this->setNonPublicPropertyValue('list_queries', [
      ['hash' => '1234', 'last' => time()],
      ['hash' => '12345', 'last' => time()],
    ]);

    $this->setNonPublicPropertyValue('queries', [
      '1234' => [
        'sql' => 'SELECT * FROM users where id = ?',
        'kind' => 'SELECT',
        'write' => false,
        'structure' => false,
        'placeholders' => 1,
        'options' => ['foo' => 'bar'],
        'num' => 0,
        'exe_time' => 0,
        'last' => 0,
        'prepared' => false
      ]
    ]);

    $this->getNonPublicMethod('_update_query')
      ->invoke(self::$pgsql, '1234');

    $queries = $this->getNonPublicProperty('queries');
    $this->assertCount(1, $queries);
    $this->assertNotSame(0, $queries['1234']['last']);

    unset($queries['1234']['last']);

    $this->assertSame(
      [
        '1234' => [
          'sql' => 'SELECT * FROM users where id = ?',
          'kind' => 'SELECT',
          'write' => false,
          'structure' => false,
          'placeholders' => 1,
          'options' => ['foo' => 'bar'],
          'num' => 1,
          'exe_time' => 0,
          'prepared' => false
        ]
      ],
      $queries
    );

    $list_queries = $this->getNonPublicProperty('list_queries');

    $this->assertCount(2, $list_queries);
    // The one with the given hash should be moved to the end of the array
    $this->assertSame(
      '1234',
      $list_queries[1]['hash']
    );
  }

  /** @test */
  public function update_query_removes_all_hashes_from_list_queries_and_queries_if_expired()
  {
    $length_queries = $this->getNonPublicProperty('length_queries') * 2;

    $this->setNonPublicPropertyValue('list_queries', [
      ['hash' => '12', 'last' => strtotime("-$length_queries Minutes")],
      ['hash' => '1234', 'last' => time()],
    ]);

    $this->setNonPublicPropertyValue('queries', [
      '1234' => [
        'last' => 0,
        'num'  => 0
      ],
      '12' => [
        'last' => 0,
        'num'  => 0
      ]
    ]);

    $this->getNonPublicMethod('_update_query')
      ->invoke(self::$pgsql, '1234');

    $queries = $this->getNonPublicProperty('queries');

    $this->assertCount(1, $queries);
    $this->assertArrayHasKey('1234', $queries);
    $this->assertArrayNotHasKey('12', $queries);

    $list_queries = $this->getNonPublicProperty('list_queries');

    $this->assertCount(1, $list_queries);
    $this->assertSame('1234', $list_queries[0]['hash']);
  }

  /** @test */
  public function update_query_throws_an_exception_when_the_given_hash_does_not_exist_in_list_queries()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Impossible to find the corresponding hash');

    $this->setNonPublicPropertyValue('list_queries', [
      ['hash' => '12', 'last' => 0]
    ]);

    $this->setNonPublicPropertyValue('queries', [
      '1234' => [
        'last' => 0,
        'num' => 0
      ]
    ]);

    $this->getNonPublicMethod('_update_query')
      ->invoke(self::$pgsql, '1234');
  }

  /** @test */
  public function update_query_throws_an_exception_when_the_given_hash_does_not_exist()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Impossible to find the query corresponding to this hash');

    $this->setNonPublicPropertyValue('queries', [
      '1234' => [
        'last' => 0,
        'num'  => 0
      ]
    ]);

    $this->getNonPublicMethod('_update_query')
      ->invoke(self::$pgsql, '123');
  }

  /** @test */
  public function get_cache_method_returns_table_structure_from_database_when_cache_does_not_exist_and_saves_it_in_cache_property()
  {
    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY,
              email VARCHAR(255) NOT NULL,
              CONSTRAINT email_unique UNIQUE(email)';
    });

    $this->setCacheExpectations();

    $result = $this->getNonPublicMethod('_get_cache')
      ->invoke(self::$pgsql, 'users');

    $expected = [
      'keys' => [
        'email_unique' => [
          'columns' => ['email'],
          'ref_db' => null,
          'ref_table' => null,
          'ref_column' => null,
          'constraint' => null,
          'update' => null,
          'delete' => null,
          'unique' => 1,
        ],
        'PRIMARY' => [
          'columns' => ['id'],
          'ref_db' => null,
          'ref_table' => null,
          'ref_column' => null,
          'constraint' => null,
          'update' => null,
          'delete' => null,
          'unique' => 1,
        ],
      ],
      'cols' => [
        'email' => ['email_unique'],
        'id' => ['PRIMARY']
      ],
      'fields' => [
        'id' => [
          'position' => 1,
          'type' => 'integer',
          'udt_name' => 'int4',
          'null' => 0,
          'key' => 'PRI',
          'extra' => 'auto_increment',
          'signed' => true,
          'virtual' => false,
          'generation' => null,
          'default' => "nextval('users_id_seq'::regclass)",
          'maxlength' => 32,
          'decimals' => 0
        ],
        'email' => [
          'position' => 2,
          'type' => 'character varying',
          'udt_name' => 'varchar',
          'null' => 0,
          'key' => 'UNI',
          'extra' => '',
          'signed' => false,
          'virtual' => false,
          'generation' => null,
          'maxlength' => 255
        ]
      ]
    ];

    $this->assertSame($expected, $result);
    $this->assertNotEmpty(
      $cache = $this->getNonPublicProperty('cache')
    );
    $this->assertSame($expected, current($cache));
  }

  /** @test */
  public function get_cache_method_returns_all_tables_names_from_database_when_cache_does_not_exist_and_saves_it_in_cache_property()
  {
    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY,
              email VARCHAR(255) UNIQUE NOT NULL';
    });

    $this->createTable('roles', function () {
      return 'id serial PRIMARY KEY';
    });

    $this->setCacheExpectations();

    $result = $this->getNonPublicMethod('_get_cache')
      ->invoke(self::$pgsql, self::getDbConfig()['db'], 'tables');

    $this->assertSame($expected = ['users', 'roles'], $result);
    $this->assertNotEmpty(
      $cache = $this->getNonPublicProperty('cache')
    );
    $this->assertSame($expected, current($cache));
  }

  /** @test */
  public function get_cache_method_returns_all_databases_names_when_cache_does_not_exist_and_saves_it_in_cache_property()
  {
    $this->setCacheExpectations();

    $result = $this->getNonPublicMethod('_get_cache')
      ->invoke(self::$pgsql, '', 'databases');

    $this->assertTrue(in_array(self::getDbConfig()['db'], $result));
    $this->assertNotEmpty(
      $cache = $this->getNonPublicProperty('cache')
    );
    $this->assertTrue(in_array(self::getDbConfig()['db'], current($cache)));
  }

  /** @test */
  public function get_cache_method_returns_table_structure_from_cache_property_when_exists()
  {
    $db_config = self::getDbConfig();

    $this->setNonPublicPropertyValue('cache', [
      "{$db_config['engine']}/{$db_config['user']}@{$db_config['host']}/users"
      => [
        'foo' => 'bar'
      ]
    ]);

    $this->assertSame(
      ['foo' => 'bar'],
      $this->getNonPublicMethod('_get_cache')->invoke(
        self::$pgsql, 'users'
      )
    );
  }

  /** @test */
  public function get_cache_method_returns_table_structure_from_cache_class_when_exists_and_does_not_exist_in_cache_property()
  {
    $cache_name = $this->getNonPublicMethod('_db_cache_name')
      ->invoke(self::$pgsql, 'users', 'columns');

    $cache_name_method = $this->getNonPublicMethod('_cache_name');

    $this->cache_mock->shouldReceive('get')
      ->with(
        $cache_name_method->invoke(self::$pgsql, $cache_name)
      )
      ->andReturn(['foo' => 'bar']);

    $result = $this->getNonPublicMethod('_get_cache')
      ->invoke(self::$pgsql, 'users');

    $this->assertSame(['foo' => 'bar'], $result);
    $this->assertNotEmpty(
      $cache = $this->getNonPublicProperty('cache')
    );
    $this->assertSame(['foo' => 'bar'], current($cache));
  }

  /** @test */
  public function get_cache_method_returns_table_structure_from_database_when_cache_exists_but_force_is_true()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function() {
      return 'id serial PRIMARY KEY';
    });

    $db_config = self::getDbConfig();

    $this->setNonPublicPropertyValue('cache', [
      "{$db_config['engine']}/{$db_config['user']}@{$db_config['host']}/{$db_config['db']}/users"
      => [
        'foo' => 'bar'
      ]
    ]);

    $result = $this->getNonPublicMethod('_get_cache')
      ->invoke(self::$pgsql, $db_config['db'], 'tables', true);

    $this->assertNotSame(['foo' => 'bar'], $result);
  }

  /** @test */
  public function get_cache_method_throws_an_exception_when_it_fails_to_retrieve_tables_names()
  {
    $this->expectException(\Exception::class);

    $this->setCacheExpectations();

    $this->setNonPublicPropertyValue('current', null);

    $this->getNonPublicMethod('_get_cache')
      ->invoke(self::$pgsql, self::getDbConfig()['db'], 'tables');
  }

  /** @test */
  public function get_cache_method_throws_an_exception_when_it_fails_to_retrieve_databases_names()
  {
    $this->expectException(\Exception::class);

    $this->setCacheExpectations();

    $this->setNonPublicPropertyValue('current', null);

    $this->getNonPublicMethod('_get_cache')
      ->invoke(self::$pgsql, '', 'databases');
  }

  /** @test */
  public function db_cache_name_returns_cache_name_of_database_structure()
  {
    $method    = $this->getNonPublicMethod('_db_cache_name');
    $db_config = self::getDbConfig();

    $this->assertSame(
      "{$db_config['engine']}/{$db_config['user']}@{$db_config['host']}/users",
      $method->invoke(self::$pgsql, 'users', 'columns')
    );

    $this->assertSame(
      "{$db_config['engine']}/{$db_config['user']}@{$db_config['host']}/table_name",
      $method->invoke(self::$pgsql, 'table_name', 'tables')
    );

    $this->assertSame(
      "{$db_config['engine']}/{$db_config['user']}@{$db_config['host']}/{$db_config['db']}",
      $method->invoke(self::$pgsql, '', 'tables')
    );

    $this->assertSame(
      "{$db_config['engine']}/{$db_config['user']}@{$db_config['host']}/_bbn-database",
      $method->invoke(self::$pgsql, '', 'databases')
    );
  }

  /** @test */
  public function modelize_method_returns_table_structure_as_an_indexed_array_for_the_given_table_name()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id bytea PRIMARY KEY,
              name VARCHAR(25) NOT NULL,
              username VARCHAR(50) UNIQUE NOT NULL,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              role_id INT NOT NULL';
    });

    $this->createTable('roles', function () {
      return 'id serial PRIMARY KEY,
              name VARCHAR(25)';
    });

    self::$pgsql->rawQuery(
      "ALTER TABLE users ADD CONSTRAINT user_role FOREIGN KEY (role_id)
       REFERENCES roles (id) ON DELETE CASCADE ON UPDATE RESTRICT"
    );

    $users_expected = [
      'keys' => [
        'PRIMARY' => [
          'columns' => ['id'],
          'ref_db'  => null,
          'ref_table'  => null,
          'ref_column'  => null,
          'constraint'  => null,
          'update'  => null,
          'delete'  => null,
          'unique'  => 1,
        ],
        'users_username_key' => [
          'columns' => ['username'],
          'ref_db' => null,
          'ref_table' => null,
          'ref_column' => null,
          'constraint' => null,
          'update' => null,
          'delete' => null,
          'unique' => 1,
        ],
        'user_role' => [
          'columns' => ['role_id'],
          'ref_db' => $db = self::getDbConfig()['db'],
          'ref_table' => 'roles',
          'ref_column' => 'id',
          'constraint' => 'user_role',
          'update'    => null,
          'delete'    => null,
          'unique'    => 0
        ]
      ],
      'cols' => [
        'id' => ['PRIMARY'],
        'username' => ['users_username_key'],
        'role_id' => ['user_role']
      ],
      'fields' => [
        'id' => [
          'position' => 1,
          'type' => 'binary',
          'udt_name' => 'bytea',
          'null'  => 0,
          'key' => 'PRI',
          'extra' => '',
          'signed' => false,
          'virtual' => false,
          'generation'  => null,
          'maxlength' => 16
        ],
        'name' => [
          'position' => 2,
          'type' => 'character varying',
          'udt_name' => 'varchar',
          'null'  => 0,
          'key' => null,
          'extra' => '',
          'signed' => false,
          'virtual' => false,
          'generation'  => null,
          'maxlength' => 25
        ],
        'username' => [
          'position' => 3,
          'type' => 'character varying',
          'udt_name' => 'varchar',
          'null'  => 0,
          'key' => 'UNI',
          'extra' => '',
          'signed' => false,
          'virtual' => false,
          'generation'  => null,
          'maxlength' => 50
        ],
        'created_at' => [
          'position' => 4,
          'type' => 'timestamp without time zone',
          'udt_name' => 'timestamp',
          'null'  => 0,
          'key' => null,
          'extra' => '',
          'signed' => FALSE,
          'virtual' => false,
          'generation'  => NULL,
          'default' => 'CURRENT_TIMESTAMP'
        ],
        'role_id' => [
          'position' => 5,
          'type' => 'integer',
          'udt_name' => 'int4',
          'null'  => 0,
          'key' => null,
          'extra' => '',
          'signed' => true,
          'virtual' => false,
          'generation' => null,
          'maxlength' => 32,
          'decimals' => 0
        ]
      ]
    ];

    $this->assertSame($users_expected, self::$pgsql->modelize('users'));

    $roles_expected = [
      'keys' => [
        'PRIMARY' => [
          'columns' => ['id'],
          'ref_db'  => null,
          'ref_table'  => null,
          'ref_column'  => null,
          'constraint'  => null,
          'update'  => null,
          'delete'  => null,
          'unique'  => 1,
        ]
      ],
      'cols' => [
        'id' => ['PRIMARY']
      ],
      'fields' => [
        'id' => [
          'position' => 1,
          'type' => 'integer',
          'udt_name' => 'int4',
          'null'  => 0,
          'key' => 'PRI',
          'extra' => 'auto_increment',
          'signed' => true,
          'virtual' => false,
          'generation'  => null,
          'default' => "nextval('roles_id_seq'::regclass)",
          'maxlength' => 32,
          'decimals' => 0,
        ],
        'name' => [
          'position' => 2,
          'type' => 'character varying',
          'udt_name' => 'varchar',
          'null'  => 1,
          'key' => null,
          'extra' => '',
          'signed' => false,
          'virtual' => false,
          'generation'  => null,
          'default' => 'NULL',
          'maxlength' => 25
        ]
      ]
    ];

    $this->assertSame($roles_expected, self::$pgsql->modelize('roles'));

    $this->assertSame(
      [
        'roles' => $roles_expected,
        'users' => $users_expected
      ],
      self::$pgsql->modelize('*')
    );
  }

  /** @test */
  public function modelize_method_does_not_get_from_cache_if_the_given_force_parameter_is_true()
  {
    $db_config = self::getDbConfig();

    $this->createTable('users', function () {
      return 'id INT';
    });

    $this->cache_mock->shouldNotReceive('cacheGet');

    $this->cache_mock->shouldReceive('set')
      ->once()
      ->with(
        Str::encodeFilename(str_replace('\\', '/', \get_class(self::$pgsql)), true).'/' .
        "pgsql/{$db_config['user']}@{$db_config['host']}/users",
        $expected = [
          'keys' => [],
          'cols' => [],
          'fields' => [
            'id' => [
              'position' => 1,
              'type' => 'integer',
              'udt_name' => 'int4',
              'null' => 1,
              'key' => null,
              'extra' => '',
              'signed' => true,
              'virtual' => false,
              'generation' => null,
              'default' => 'NULL',
              'maxlength' => 32,
              'decimals' => 0
            ]
          ]
        ],
        $this->getNonPublicProperty('cache_renewal')
      )
      ->andReturnTrue();

    $result = self::$pgsql->modelize('users', true);

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function enableTrigger_method_enables_trigger_function()
  {
    $this->setNonPublicPropertyValue('_triggers_disabled', true);

    $result = self::$pgsql->enableTrigger();

    $this->assertFalse(
      $this->getNonPublicProperty('_triggers_disabled')
    );

    $this->assertInstanceOf(Pgsql::class, $result);
  }

  /** @test */
  public function disableTrigger_method_disables_trigger_functions()
  {
    $this->setNonPublicPropertyValue('_triggers_disabled', false);

    $result = self::$pgsql->disableTrigger();

    $this->assertTrue(
      $this->getNonPublicProperty('_triggers_disabled')
    );

    $this->assertInstanceOf(Pgsql::class, $result);
  }

  /** @test */
  public function isTriggerEnabled_method_checks_if_trigger_function_is_enabled()
  {
    $this->setNonPublicPropertyValue('_triggers_disabled', false);

    $this->assertTrue(
      self::$pgsql->isTriggerEnabled()
    );
  }

  /** @test */
  public function isTriggerDisabled_method_checks_if_trigger_functions_is_disabled()
  {
    $this->setNonPublicPropertyValue('_triggers_disabled', true);

    $this->assertTrue(
      self::$pgsql->isTriggerDisabled()
    );
  }

  /** @test */
  public function setTrigger_method_register_a_callback_to_be_applied_every_time_the_methods_kind_are_used()
  {
    $default_triggers = $this->getNonPublicProperty('_triggers');

    $this->createTable('users', function () {
      return 'email varchar(255)';
    });

    $this->createTable('roles', function () {
      return 'name varchar(255)';
    });

    $expected = 'A call back function';

    $result = self::$pgsql->setTrigger(function () use ($expected) {
      return $expected;
    });

    $this->assertInstanceOf(Pgsql::class, $result);

    $triggers = $this->getNonPublicProperty('_triggers');

    $this->assertSame(
      $expected,
      $triggers['SELECT']['before']['users'][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['SELECT']['before']['roles'][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['SELECT']['after']['users'][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['SELECT']['after']['roles'][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['INSERT']['before']['users'][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['INSERT']['before']['roles'][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['INSERT']['after']['users'][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['INSERT']['after']['roles'][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['UPDATE']['before']['users'][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['UPDATE']['before']['roles'][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['UPDATE']['after']['users'][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['UPDATE']['after']['roles'][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['DELETE']['before']['users'][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['DELETE']['before']['roles'][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['DELETE']['after']['users'][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['DELETE']['after']['roles'][0]()
    );

    // Another test
    $this->setNonPublicPropertyValue('_triggers',  $default_triggers);

    self::$pgsql->setTrigger(function () use ($expected) {
      return $expected;
    }, 'insert', 'after', 'users');

    $triggers = $this->getNonPublicProperty('_triggers');

    $this->assertSame(
      $expected,
      $triggers['INSERT']['after']['users'][0]()
    );

    // Another test
    $this->setNonPublicPropertyValue('_triggers',  $default_triggers);

    self::$pgsql->setTrigger(function () use ($expected) {
      return $expected;
    }, ['insert', 'select'], ['after'], 'users');

    $triggers = $this->getNonPublicProperty('_triggers');

    $this->assertSame(
      $expected,
      $triggers['INSERT']['after']['users'][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['SELECT']['after']['users'][0]()
    );

    $this->setNonPublicPropertyValue('_triggers',  $default_triggers);
  }

  /** @test */
  public function getTriggers_method_returns_the_current_triggers()
  {
    $this->assertSame(
      $this->getNonPublicProperty('_triggers'),
      self::$pgsql->getTriggers()
    );
  }


  /** @test */
  public function trigger_method_launches_a_function_before_or_after_if_registered_and_callable()
  {
    $cfg = [
      'tables' => ['users'],
      'kind'   => 'SELECT',
      'moment' => 'after'
    ];

    $method = $this->getNonPublicMethod('_trigger');

    $this->setNonPublicPropertyValue('_triggers', [
      'SELECT' => [
        'after' => [
          'users' => [
            function ($cfg) {
              return array_merge($cfg, ['foo' => 'bar']);
            },
            function ($cfg) {
              return array_merge($cfg, ['foo2' => 'bar2']);
            }
          ]
        ]
      ]
    ]);

    $this->assertSame(
      array_merge($cfg, [
        'trig' => 1, 'run' => 1, 'foo' => 'bar', 'foo2' => 'bar2'
      ]),
      $method->invoke(self::$pgsql, $cfg)
    );
  }

  /** @test */
  public function trigger_method_does_not_launch_the_function_if_the_callback_return_falsy_result()
  {
    $cfg = [
      'tables' => ['users'],
      'kind'   => 'INSERT',
      'moment' => 'before'
    ];

    $method = $this->getNonPublicMethod('_trigger');

    $this->setNonPublicPropertyValue('_triggers', [
      'INSERT' => [
        'before' => [
          'users' => [
            function ($cfg) {
              return [];
            }
          ]
        ]
      ]
    ]);

    $this->assertSame(
      array_merge($cfg, ['trig' => false, 'run' => false]),
      $method->invoke(self::$pgsql, $cfg)
    );
  }

  /** @test */
  public function trigger_method_does_not_launch_the_function_if_table_is_not_registered()
  {
    $cfg = [
      'tables' => ['users'],
      'kind'   => 'UPDATE',
      'moment' => 'before'
    ];

    $this->setNonPublicPropertyValue('_triggers', [
      'UPDATE' => [
        'before' => [
          'roles' => [
            function ($cfg) {
              return array_merge($cfg, ['foo' => 'bar']);
            }
          ]
        ]
      ]
    ]);

    $method = $this->getNonPublicMethod('_trigger');

    $this->assertSame(
      array_merge($cfg, ['trig' => 1, 'run' => 1]),
      $method->invoke(self::$pgsql, $cfg)
    );
  }

  /** @test */
  public function trigger_method_does_not_launch_the_function_the_given_trigger_does_not_exist()
  {
    $cfg = [
      'tables' => ['users'],
      'kind'   => 'UPDATE',
      'moment' => 'before'
    ];

    $this->setNonPublicPropertyValue('_triggers', [
      'UPDATE' => [
        'after' => [
          'users' => [
            function ($cfg) {
              return array_merge($cfg, ['foo' => 'bar']);
            }
          ]
        ]
      ]
    ]);

    $method = $this->getNonPublicMethod('_trigger');

    $this->assertSame(
      array_merge($cfg, ['trig' => 1, 'run' => 1]),
      $method->invoke(self::$pgsql, $cfg)
    );
  }


  /** @test */
  public function trigger_method_does_not_launch_the_function_if_no_table_name_is_given()
  {
    $cfg = [
      'kind'   => 'UPDATE',
      'moment' => 'before'
    ];

    $method = $this->getNonPublicMethod('_trigger');

    $this->assertSame(
      array_merge($cfg, ['trig' => 1, 'run' => 1]),
      $method->invoke(self::$pgsql, $cfg)
    );
  }

  /** @test */
  public function trigger_method_returns_the_config_array_as_is_if_triggers_is_disabled_and_moment_is_after()
  {
    $this->setNonPublicPropertyValue('_triggers_disabled', true);

    $method = $this->getNonPublicMethod('_trigger');

    $this->assertSame(
      ['moment' => 'after'],
      $method->invoke(self::$pgsql, ['moment' => 'after'])
    );
  }

  /** @test */
  public function trigger_method_returns_the_config_array_adding_trig_and_run_when_triggers_is_disabled_and_moment_is_before()
  {
    $this->setNonPublicPropertyValue('_triggers_disabled', true);

    $method = $this->getNonPublicMethod('_trigger');

    $this->assertSame(
      ['moment' => 'before', 'run' => 1, 'trig' => 1],
      $method->invoke(self::$pgsql, ['moment' => 'before'])
    );
  }

  /** @test */
  public function add_kind_method_adds_the_given_type_to_the_given_args()
  {
    $method = $this->getNonPublicMethod('_add_kind');

    $this->assertSame(
      ['UPDATE','foo'],
      $method->invoke(self::$pgsql, ['foo'], 'update')
    );

    $this->assertSame(
      [['foo', 'kind' => 'SELECT']],
      $method->invoke(self::$pgsql, [['foo']])
    );

    $this->assertNull(
      $method->invoke(self::$pgsql, ['foo' => ['bar']])
    );
  }

  /** @test */
  public function add_primary_method_adds_a_random_primary_value_when_missing_from_the_given_arguments()
  {
    $method = $this->getNonPublicMethod('_add_primary');

    $cfg    = [
      'primary'      => 'id',
      'primary_type' => 'int',
      'auto_increment' => false,
      'fields' => ['id', 'name'],
      'values' => ['John'],
      'primary_length' => 4
    ];

    $method->invokeArgs(self::$pgsql, [&$cfg]);

    $this->assertCount(2, $cfg['values']);
    $this->assertIsInt($cfg['values'][0]);
    $this->assertSame(
      $cfg['values'][0],
      $this->getNonPublicProperty('id_just_inserted')
    );

    $cfg2    = [
      'primary'      => 'id',
      'primary_type' => 'binary',
      'auto_increment' => false,
      'fields' => ['id', 'name'],
      'values' => ['John'],
      'primary_length' => 16
    ];

    $method->invokeArgs(self::$pgsql, [&$cfg2]);

    $this->assertCount(2, $cfg2['values']);
    $this->assertIsString($cfg2['values'][0]);
    $this->assertSame(
      $cfg2['values'][0],
      $this->getNonPublicProperty('id_just_inserted')
    );

    $cfg3    = [
      'primary'      => 'id',
      'primary_type' => 'varchar',
      'auto_increment' => false,
      'fields' => ['id', 'name'],
      'values' => ['John'],
      'primary_length' => 16
    ];

    $method->invokeArgs(self::$pgsql, [&$cfg3]);

    $this->assertCount(1, $cfg3['values']);
  }

  /** @test */
  public function add_primary_method_does_not_adda_a_random_primary_value_if_given_arguments_does_not_match_conditions()
  {
    $method = $this->getNonPublicMethod('_add_primary');

    $cfg = $old_cfg = [
      'primary'      => 'id',
      'primary_type' => 'binary',
      'auto_increment' => false,
      'fields' => ['id', 'name'],
      'values' => ['John', 'Doe']
    ];

    $method->invokeArgs(self::$pgsql, [&$cfg]);

    $this->assertSame($old_cfg, $cfg);

    $cfg = $old_cfg = [
      'primary'      => 'id',
      'primary_type' => 'binary',
      'auto_increment' => false,
      'fields' => ['email', 'name'],
      'values' => ['John']
    ];

    $method->invokeArgs(self::$pgsql, [&$cfg]);

    $this->assertSame($old_cfg, $cfg);

    $cfg = $old_cfg = [
      'primary'      => 'id',
      'primary_type' => 'binary',
      'auto_increment' => true,
      'fields' => ['id', 'name'],
      'values' => ['John']
    ];

    $method->invokeArgs(self::$pgsql, [&$cfg]);

    $this->assertSame($old_cfg, $cfg);

    $cfg = $old_cfg = [
      'primary'      => '',
      'primary_type' => 'binary',
      'auto_increment' => false,
      'fields' => ['id', 'name'],
      'values' => ['John']
    ];

    $method->invokeArgs(self::$pgsql, [&$cfg]);

    $this->assertSame($old_cfg, $cfg);

    $cfg = $old_cfg = [
      'primary_type' => 'binary',
      'auto_increment' => false,
      'fields' => ['id', 'name'],
      'values' => ['John']
    ];

    $method->invokeArgs(self::$pgsql, [&$cfg]);

    $this->assertSame($old_cfg, $cfg);
  }

  /** @test */
  public function exec_method_insert_test()
  {
    self::$pgsql->setErrorMode(Errors::E_DIE);
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id bytea PRIMARY KEY,
              email VARCHAR(25) UNIQUE NOT NULL,
              name VARCHAR(25) NOT NULL';
    });

    $cfg = [
      'tables'  => 'users',
      'kind'    => 'INSERT',
      'fields'  => ['email' => 'john@mail.com', 'name' => 'John']
    ];

    $method = $this->getNonPublicMethod('_exec');

    $this->assertSame(1, $method->invoke(self::$pgsql, $cfg));
    $this->assertDatabaseHas('users', 'email', 'john@mail.com');
    $this->assertDatabaseHas('users', 'name', 'John');
    $this->assertSame(
      self::$pgsql->rawQuery("SELECT encode(id, 'hex') as id FROM users LIMIT 1")->fetchObject()->id,
      $this->getNonPublicProperty('last_insert_id')
    );

    $this->assertNotEmpty(
      $this->getNonPublicProperty('last_cfg')
    );
  }

  /** @test */
  public function exec_method_update_test()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id bytea PRIMARY KEY,
              email VARCHAR(25) UNIQUE NOT NULL,
              name VARCHAR(25) NOT NULL';
    });

    self::$pgsql->insert([
      'tables'  => 'users',
      'fields'  => ['email' => 'john@mail.com', 'name' => 'John']
    ]);

    self::$pgsql->insert([
      'tables'  => 'users',
      'fields'  => ['email' => 'smith@mail.com', 'name' => 'Smith']
    ]);

    $id1 = self::$pgsql->rawQuery("SELECT encode(id, 'hex') as id FROM users LIMIT 1")->fetchObject()->id;

    $cfg = [
      'tables'  => 'users',
      'kind'    => 'UPDATE',
      'fields'  => ['email' => 'john2@mail.com', 'name' => 'John Doe'],
      'where'   => ['id' => $id1]
    ];

    $method = $this->getNonPublicMethod('_exec');

    $this->assertSame(
      1,
      $method->invoke(self::$pgsql, $cfg)
    );

    $this->assertDatabaseDoesNotHave('users', 'name', 'John');
    $this->assertDatabaseHas('users', 'name', 'John Doe');
    $this->assertDatabaseHas('users', 'name', 'Smith');

    $this->assertNotEmpty(
      $this->getNonPublicProperty('last_cfg')
    );
  }

  /** @test */
  public function exec_method_delete_test()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id bytea PRIMARY KEY,
              name VARCHAR(25)';
    });

    self::$pgsql->insert([
      'tables'  => 'users',
      'fields'  => ['name' => 'John']
    ]);

    self::$pgsql->insert([
      'tables'  => 'users',
      'fields'  => ['name' => 'Sam']
    ]);

    $id1 = self::$pgsql->rawQuery("SELECT encode(id, 'hex') as id FROM users LIMIT 1")->fetchObject()->id;

    $cfg = [
      'table' => ['users'],
      'kind'  => 'delete',
      'where' => ['id' => $id1]
    ];

    $method = $this->getNonPublicMethod('_exec');

    $this->assertSame(
      1,
      $method->invoke(self::$pgsql, $cfg
      )
    );

    $this->assertDatabaseHas('users', 'name', 'Sam');
    $this->assertDatabaseDoesNotHave('users', 'name', 'John');

    $this->assertNotEmpty(
      $this->getNonPublicProperty('last_cfg')
    );
  }

  /** @test */
  public function exec_method_throws_an_exception_when_the_given_fields_has_no_values()
  {
    $this->getActualOutputForAssertion();
    $this->expectException(\Exception::class);

    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id bytea PRIMARY KEY,
              email VARCHAR(25) UNIQUE NOT NULL,
              name VARCHAR(25) NOT NULL';
    });

    $cfg = [
      'tables'  => 'users',
      'kind'    => 'INSERT',
      'fields'   => ['email', 'name']
    ];

    $method = $this->getNonPublicMethod('_exec');

    $method->invoke(self::$pgsql, $cfg);
  }

  /** @test */
  public function exec_method_select_test()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id bytea PRIMARY KEY,
              email VARCHAR(25) UNIQUE NOT NULL,
              name VARCHAR(25) NOT NULL';
    });

    self::$pgsql->insert('users', ['email' => 'john@mail.com', 'name' => 'John']);
    self::$pgsql->insert('users',['email' => 'smith@mail.com', 'name' => 'Smith']);

    $id1 = self::$pgsql->rawQuery("SELECT encode(id, 'hex') as id FROM users LIMIT 1")->fetchObject()->id;

    $method = $this->getNonPublicMethod('_exec');

    $cfg = [
      'tables'  => 'users',
      'kind'    => 'SELECT',
      'fields'  => ['email', 'name'],
      'where'   => ['id' => $id1]
    ];

    $result = $method->invoke(self::$pgsql, $cfg);

    $this->assertInstanceOf(\PDOStatement::class, $result);

    $results = self::$pgsql->fetchAllResults($result, \PDO::FETCH_ASSOC);

    $this->assertSame(
      [['email' => 'john@mail.com', 'name' => 'John']],
      $results
    );
  }

  /** @test */
  public function exec_method_test_the_after_trigger_is_running()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id bytea PRIMARY KEY,
              email VARCHAR(25) UNIQUE NOT NULL,
              name VARCHAR(25) NOT NULL';
    });

    $this->setNonPublicPropertyValue('_triggers', [
      'INSERT' => [
        'after' => [
          'users' => [
            function ($cfg) {
              return array_merge($cfg, ['run' => 'run is changed!']);
            }
          ]
        ]
      ]
    ]);

    $cfg = [
      'tables'  => 'users',
      'kind'    => 'INSERT',
      'fields'  => ['email' => 'john@mail.com', 'name' => 'John']
    ];

    $method = $this->getNonPublicMethod('_exec');

    $this->assertSame(
      'run is changed!',
      $method->invoke(self::$pgsql, $cfg)
    );
  }

  /** @test */
  public function exec_method_test_when_trigger_returns_empty_run()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY,
              email VARCHAR(25) UNIQUE NOT NULL,
              name VARCHAR(25) NOT NULL';
    });

    $this->setNonPublicPropertyValue('_triggers', [
      'INSERT' => [
        'before' => [
          'users' => [
            function ($cfg) {
              return [];
            }
          ]
        ],
        'after' => [
          'users' => [
            function ($cfg) {
              return array_merge($cfg, ['run' => 1]); // To make sure that this closure won't execute
            }
          ]
        ]
      ]
    ]);

    $method = $this->getNonPublicMethod('_exec');

    $cfg = [
      'tables'  => 'users',
      'kind'    => 'INSERT',
      'fields'  => ['email' => 'john@mail.com', 'name' => 'John']
    ];

    $this->assertFalse(
      $method->invoke(self::$pgsql, $cfg)
    );

    $this->assertDatabaseDoesNotHave('users', 'name', 'John');
    $this->assertDatabaseDoesNotHave('users', 'email', 'john@mail.com');
  }

  /** @test */
  public function exec_method_test_when_trigger_returns_empty_run_but_force_is_enabled()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY,
              email VARCHAR(25) UNIQUE NOT NULL,
              name VARCHAR(25) NOT NULL';
    });

    $this->setNonPublicPropertyValue('_triggers', [
      'INSERT' => [
        'before' => [
          'users' => [
            function ($cfg) {
              return [];
            }
          ]
        ],
        'after' => [
          'users' => [
            function ($cfg) {
              return array_merge($cfg, ['run' => 1]); // To make sure that this closure will execute since force is enabled
            }
          ]
        ]
      ]
    ]);

    $method = $this->getNonPublicMethod('_exec');

    $cfg = [
      'tables'  => 'users',
      'kind'    => 'INSERT',
      'fields'  => ['email' => 'john@mail.com', 'name' => 'John'],
      'force'   => true
    ];

    $this->assertSame(
      1,
      $method->invoke(self::$pgsql, $cfg)
    );

    $this->assertDatabaseDoesNotHave('users', 'name', 'John');
    $this->assertDatabaseDoesNotHave('users', 'email', 'john@mail.com');
  }

  /** @test */
  public function exec_method_returns_null_when_sql_has_falsy_value_from_the_returned_config_from_processCfg_method()
  {
    $pgsql = \Mockery::mock(Pgsql::class)->makePartial();

    $pgsql->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $pgsql->shouldReceive('processCfg')
      ->once()
      ->andReturn(['tables' => ['users'], 'sql' => '']);

    $this->assertNull(
      $this->getNonPublicMethod('_exec', $pgsql)
        ->invoke($pgsql)
    );
  }

  /** @test */
  public function exec_method_returns_null_when_processCfg_method_returns_nul()
  {
    $pgsql = \Mockery::mock(Pgsql::class)->makePartial();

    $pgsql->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $pgsql->shouldReceive('processCfg')
      ->once()
      ->andReturnNull();

    $this->assertNull(
      $this->getNonPublicMethod('_exec', $pgsql)
        ->invoke($pgsql)
    );
  }

  /** @test */
  public function exec_method_returns_null_when_check_method_returns_false()
  {
    $pgsql = \Mockery::mock(Pgsql::class)->makePartial();

    $pgsql->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->getNonPublicMethod('_exec', $pgsql)
        ->invoke($pgsql)
    );
  }

  /** @test */
  public function processCfg_method_processes_the_given_insert_configurations()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY,
              email VARCHAR(255) NOT NULL,
              name VARCHAR(255) NOT NULL';
    });

    $cfg = [
      'tables' => 'users',
      'kind'  => 'INSERT',
      'fields'  => ['email' => 'john@mail.com', 'name' => 'John']
    ];

    $result   = self::$pgsql->processCfg($cfg);

    $expected_sql = "INSERT INTO users
(email, name)
 VALUES (?, ?)";

    try {
      self::$pgsql->query($expected_sql, 'sam@mail.com', 'sam');
    } catch (\Exception $e) {
      $error = $e->getMessage();
    }

    $this->assertSame(trim($expected_sql), trim($result['sql']));
    $this->assertTrue(!isset($error), $error ?? '');

    $this->assertSame(['john@mail.com', 'John'], $result['values']);
    $this->assertCount(2, $result['values_desc']);
    $this->assertFalse($result['generate_id']);
    $this->assertTrue($result['auto_increment']);
    $this->assertSame('id', $result['primary']);
    $this->assertSame(32, $result['primary_length']);
    $this->assertSame('integer', $result['primary_type']);
  }

  /** @test */
  public function processCfg_method_processes_the_given_update_configurations()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY,
              email VARCHAR(255) NOT NULL,
              name VARCHAR(255) NOT NULL';
    });

    $cfg = [
      'tables' => 'users',
      'kind'   => 'UPDATE',
      'fields' => ['email' => 'samantha@mail.com', 'name' => 'Samantha'],
      'where'  => [['email', '=', 'sam@mail.com'], ['name', '=', 'Sam']]
    ];

    $result = self::$pgsql->processCfg($cfg);

    $expected_sql = "UPDATE users SET email = ?, name = ? WHERE  users.email = ? AND users.name = ?";

    $this->assertSame(
      $expected_sql,
      str_replace("\n", ' ', trim($result['sql']))
    );

    try {
      self::$pgsql->query($expected_sql, 'samantha@mail.com', 'Sam', 'samantha@mail.com', 'Samantha');
    } catch (\Exception $e) {
      $error  = $e->getMessage();
    }

    $this->assertTrue(!isset($error), $error ?? '');

    $this->assertSame(
      ['samantha@mail.com', 'Samantha', 'sam@mail.com', 'Sam'],
      $result['values']
    );

    $this->assertSame(
      [
        'conditions' => [
          [
            'field' => 'email',
            'operator' => '=',
            'value' => 'sam@mail.com'
          ],
          [
            'field' => 'name',
            'operator' => '=',
            'value' => 'Sam'
          ]
        ],
        'logic' => 'AND'
      ],
      $result['filters']
    );

    $this->assertSame(
      [
        ['type' => 'character varying', 'maxlength' => 255],
        ['type' => 'character varying', 'maxlength' => 255],
        ['type' => 'character varying', 'maxlength' => 255, 'operator' => '='],
        ['type' => 'character varying', 'maxlength' => 255, 'operator' => '=']
      ],
      $result['values_desc']
    );

    $this->assertTrue($result['auto_increment']);
    $this->assertSame('id', $result['primary']);
    $this->assertSame('integer', $result['primary_type']);
    $this->assertNotEmpty($result['hashed_where']['conditions']);
  }

  /** @test */
  public function processCfg_method_processes_the_given_select_configurations()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id bigserial PRIMARY KEY,
              name VARCHAR(25) NOT NULL,
              role_id INT NOT NULL';
    });

    $this->createTable('roles', function () {
      return 'id bigserial PRIMARY KEY,
              name VARCHAR(25) NOT NULL';
    });

    $cfg = [
      'kind'   => 'SELECT',
      'table'  => 'users',
      'fields' => ['user_name' => 'users.name', 'role_name' => 'roles.name'],
      'start' => 2,
      'limit' => 25,
      'join'   => [[
        'table' => 'roles',
        'on'    => [
          'conditions' => [[
            'field' => 'users.role_id',
            'operator' => '=',
            'exp' => 'roles.id'
          ]]
        ]
      ]],
      'where' => [
        [
          'conditions' => [
            [
              'field' => 'users.id',
              'operator' => '>=',
              'exp' => 1
            ],
            [
              'field' => 'roles.name',
              'operator' => '!=',
              'value' => 'Super Admin'
            ]
          ]
        ]
      ],
      'order' => ['users.name' => 'desc']
    ];

    $result = self::$pgsql->processCfg($cfg);

    $expected_sql = "SELECT users.name AS user_name, roles.name AS role_name
FROM users
  JOIN roles
    ON 
    users.role_id = roles.id
WHERE (
  users.id >= 1
  AND roles.name != ?
)
ORDER BY users.name DESC
LIMIT 25 OFFSET 2";

    try {
      self::$pgsql->query($expected_sql, 'admin');
    } catch (\Exception $e) {
      $error = $e->getMessage();
    }

    $this->assertTrue(!isset($error), $error ?? '');

    $this->assertSame(trim($expected_sql), trim($result['sql']));

    $this->assertSame(
      [[
        'table' => 'roles',
        'on'    => [
          'conditions' => [[
            'field' => 'users.role_id',
            'operator' => '=',
            'exp' => 'roles.id'
          ]],
          'logic' => 'AND'
        ],
        'type' => 'right'
      ]],
      $result['join']
    );

    $this->assertSame(
      [
        'conditions' => [
          ['conditions' => [[
            'field' => 'users.id',
            'operator' => '>=',
            'exp' => 1
          ],[
            'field' => 'roles.name',
            'operator' => '!=',
            'value' => 'Super Admin'
          ]],
            'logic' => 'AND'
          ],
        ],
        'logic' => 'AND'
      ],
      $result['filters']
    );

    $this->assertNotEmpty($result['hashed_join']);
    $this->assertNotEmpty($result['hashed_where']['conditions']);
    $this->assertSame(
      ['users.name' => 'user_name', 'roles.name' => 'role_name'],
      $result['aliases']
    );
    $this->assertSame(['Super Admin'], $result['values']);
  }

  /** @test */
  public function processCfg_method_processes_the_given_aggregate_select_configurations()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY,
              name VARCHAR(25) NOT NULL,
              active smallint NOT NULL DEFAULT 1';
    });

    $cfg = [
      'tables' => 'users',
      'count'  => true,
      'group_by' => ['id'],
      'where' => ['active' => 1]
    ];

    $result = self::$pgsql->processCfg($cfg);

    $expected_sql = "SELECT COUNT(*) FROM ( SELECT users.id AS id
FROM users
WHERE 
users.active = ?
GROUP BY id
) AS t";

    $this->assertSame(trim($expected_sql), trim($result['sql']));

    try {
      self::$pgsql->query($expected_sql, 1);
    } catch (\Exception $e) {
      $error = $e->getMessage();
    }

    $this->assertTrue(!isset($error), $error ?? '');
  }

  /** @test */
  public function processCfg_returns_null_when_the_given_configurations_has_same_tables()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT';
    });


    $this->assertNull(
      self::$pgsql->processCfg(['tables' => ['users', 'users']])
    );
  }

  /** @test */
  public function processCfg_returns_null_and_sets_an_error_when_no_hash_found()
  {
    $pgsql = \Mockery::mock(Pgsql::class)
      ->shouldAllowMockingProtectedMethods()
      ->makePartial();

    $pgsql->shouldReceive('_treat_arguments')
      ->once()
      ->with(['foo' => 'bar'])
      ->andReturn(['foo' => 'bar']);

    $pgsql->shouldReceive('error')
      ->once();

    $this->assertNull(
      $pgsql->processCfg(['foo' => 'bar'])
    );
  }

  /** @test */
  public function processCfg_method_returns_previously_saved_cfg_using_hash()
  {
    $pgsql = \Mockery::mock(Pgsql::class)
      ->shouldAllowMockingProtectedMethods()
      ->makePartial();

    $this->setNonPublicPropertyValue('cfgs', [
      '123456' => [
        'foo2' => 'bar2'
      ]
    ], $pgsql);

    $pgsql->shouldReceive('_treat_arguments')
      ->once()
      ->with(['foo' => 'bar'])
      ->andReturn(['hash' => '123456']);

    $this->assertSame(
      ['foo2' => 'bar2', 'values' => [], 'where' => [], 'filters' => []],
      $pgsql->processCfg(['foo' => 'bar'])
    );
  }

  /** @test */
  public function processCfg_method_returns_null_when_a_given_field_does_not_exists()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT';
    });

    $cfg = [
      'tables' => 'users',
      'fields' => ['username' => 'username']
    ];

    $this->assertNull(
      self::$pgsql->processCfg($cfg)
    );
  }

  /** @test */
  public function reprocessCfg_method_test()
  {
    $pgsql = \Mockery::mock(Pgsql::class)->makePartial();

    $pgsql->shouldReceive('processCfg')
      ->once()
      ->with(['foo' => 'bar'], true)
      ->andReturn(['foo2' => 'bar2']);

    $this->assertSame(['foo2' => 'bar2'], $pgsql->reprocessCfg(['foo' => 'bar']));

    // Another test

    $this->setNonPublicPropertyValue('cfgs', [
      '12345' => ['a' => 'b']
    ], $pgsql);

    $pgsql->shouldReceive('processCfg')
      ->once()
      ->with(
        ['foo' => 'bar', 'hash' => '12345', 'values' => ['a', 'b']],
        true
      )
      ->andReturn(['foo2' => 'bar2', 'values' => ['c', 'd']]);

    $result = $pgsql->reprocessCfg([
      'bbn_db_processed' => true,
      'bbn_db_treated'   => true,
      'hash'             => '12345',
      'foo'              => 'bar',
      'values'           => ['a', 'b']
    ]);

    $this->assertSame(['foo2' => 'bar2', 'values' => ['a', 'b']], $result);

    $this->assertSame([], $this->getNonPublicProperty('cfgs', $pgsql));
  }

  /** @test */
  public function treat_arguments_method_normalizes_arguments_by_making_it_a_uniform_array()
  {
    $cfg = [
      'kind'   => 'SELECT',
      'table'  => 'users',
      'fields' => ['users.name', 'users.username'],
      'join'   => [
        [
          'table' => 'roles',
          'on'    => [[
            'conditions' => [[
              'field' => 'users.role_id',
              'operator' => '=',
              'exp' => 'role.id'
            ]]
          ]]
        ]
      ],
      'where' => [[
        'conditions' => [[
          'field' => 'users.id',
          'operator' => '>=',
          'value' => 1
        ]]
      ]],
      'order' => ['users.name' => 'desc']
    ];

    $expected = [
      'kind' => 'SELECT',
      'fields' => ['users.name', 'users.username'],
      'where' => [[
        'conditions' => [[
          'field' => 'users.id',
          'operator' => '>=',
          'value' => 1
        ]]
      ]],
      'order' => ['users.name' => 'desc'],
      'limit' => 0,
      'start' => 0,
      'group_by' => [],
      'having' => [],
      'join' => [[
        'table' => 'roles',
        'on' => [
          'conditions' => [[
            'conditions' => [[
              'field' => 'users.role_id',
              'operator' => '=',
              'exp' => 'role.id'
            ]],
            'logic' => 'AND'
          ]],
          'logic' => 'AND'
        ],
        'type' => 'right'
      ]],
      'tables' => ['users'],
      'aliases' => [],
      'values' => [1],
      'filters' => [
        'conditions' => [[
          'conditions' => [[
            'field' => 'users.id',
            'operator' => '>=',
            'value' => 1
          ]],
          'logic' => 'AND'
        ]],
        'logic' => 'AND'
      ],
      'hashed_join' => [[
        'conditions' => [[
          'conditions' => [[
            'exp' => 'role.id',
            'field' => 'users.role_id',
            'operator' => '='
          ]],
          'logic' => 'AND'
        ]],
        'logic' => 'AND'
      ]],
      'hashed_where' => [
        'conditions' => [[
          'conditions' => [[
            'field' => 'users.id',
            'operator' => '>='
          ]],
          'logic' => 'AND'
        ]],
        'logic' => 'AND'
      ],
      'hashed_having' => [],
      'bbn_db_treated' => true,
      'write' => false,
      'ignore' => false,
      'count' => false,
    ];

    $method = $this->getNonPublicMethod('_treat_arguments');
    $result = $method->invoke(self::$pgsql, $cfg);

    $this->assertArrayHasKey('hash', $result);
    unset($result['hash']);
    $this->assertSame($expected, $result);
  }

  /** @test */
  public function treat_arguments_method_sets_default_cfg_when_not_provided()
  {
    $result = $this->getNonPublicMethod('_treat_arguments')
      ->invoke(self::$pgsql, ['tables' => ['users']]);

    $expected = [
      'kind' => 'SELECT',
      'fields' => [],
      'where' => [],
      'order' => [],
      'limit' => 0,
      'start' => 0,
      'group_by' => [],
      'having' => [],
      'tables' => ['users'],
      'aliases' => [],
      'values' => [],
      'filters' => [
        'conditions' => [],
        'logic' => 'AND'
      ],
      'join' => [],
      'hashed_join' => [],
      'hashed_where' => [
        'conditions' => [],
        'logic' => 'AND'
      ],
      'hashed_having' => [],
      'bbn_db_treated' => true,
      'write' => false,
      'ignore' => false,
      'count' => false
    ];

    $this->assertArrayHasKey('hash', $result);
    unset($result['hash']);
    $this->assertSame($expected, $result);
  }

  /** @test */
  public function treat_arguments_method_handle_arguments_when_the_given_config_is_a_numeric_array()
  {
    $cfg = [
      [[
        'SELECT',
        'users',
        ['username', 'name'],
        [['id', '>=', 4]],
        ['id' => 'desc'],
        4,
        9
      ]]
    ];

    $expected = [
      'kind' => 'SELECT',
      'fields' => ['username', 'name'],
      'where' => [['id', '>=', 4]],
      'order' => ['id' => 'desc'],
      'limit' => 4,
      'start' => 9,
      'group_by' => [],
      'having' => [],
      'tables' => ['users'],
      'aliases' => [],
      'values' => [4],
      'filters' => [
        'conditions' => [[
          'field' => 'id',
          'operator' => '>=',
          'value' => 4
        ]],
        'logic' => 'AND'
      ],
      'join' => [],
      'hashed_join' => [],
      'hashed_where' => [
        'conditions' => [[
          'field' => 'id',
          'operator' => '>='
        ]],
        'logic' => 'AND'
      ],
      'hashed_having' => [],
      'bbn_db_treated' => true,
      'write' => false,
      'ignore' => false,
      'count' => false
    ];

    $result = $this->getNonPublicMethod('_treat_arguments')
      ->invoke(self::$pgsql, $cfg);

    $this->assertArrayHasKey('hash', $result);
    unset($result['hash']);
    $this->assertSame($expected, $result);
  }

  /** @test */
  public function treat_arguments_method_test_different_conditional_logic()
  {
    $cfg = [
      'tables' => 'users', // should be converted to array
      'fields' => 'username', // should be converted to array
      'group_by' => 'id', // should be converted to array
      'where' => 'id > 9', // should be converted to an empty array
      'order' => 'id', // should be converted to ['id' => 'ASC]
      'limit' => 'aaa', // should be unset if not integer
      'start' => 'bbb', // should be unset if not integer
      'join'  => ['foo'] // should be converted to empty array
    ];

    $expected = [
      'kind' => 'SELECT',
      'fields' => ['username'],
      'where' => [],
      'order' => ['id' => 'ASC'],
      'group_by' => ['id'],
      'having' => [],
      'tables' => ['users'],
      'join' => [],
      'aliases' => [],
      'values' => [],
      'filters' => [
        'conditions' => [],
        'logic' => 'AND'
      ],
      'hashed_join' => [],
      'hashed_where' => [
        'conditions' => [],
        'logic' => 'AND'
      ],
      'hashed_having' => [],
      'bbn_db_treated' => true,
      'write' => false,
      'ignore' => false,
      'count' => false
    ];

    $result = $this->getNonPublicMethod('_treat_arguments')
      ->invoke(self::$pgsql, $cfg);

    $this->assertArrayHasKey('hash', $result);
    unset($result['hash']);
    $this->assertSame($expected, $result);
  }

  /** @test */
  public function treat_arguments_method_testing_handling_join_arguments()
  {
    $cfg = [
      'tables' => ['users'],
      'join' => [
        'roles' => [
          'table' => '', // Empty table will use the index 'users' for it
          'on' => [
            'conditions' => [[
              'field' => 'users.role_id',
              'exp' => 'roles.id'
            ]]
          ],
          'type' => 'left'
        ],
        'profiles_alias' => [
          'table' => 'profiles',
          'alias' => '', // Empty alias will use the index 'roles_alias' for it
          'on' => [
            'conditions' => [[
              'field' => 'users.profile_id',
              'exp' => 'profiles.id',
              'operator' => '='
            ]]
          ],
        ]
      ]
    ];

    $expected = [
      'kind' => 'SELECT',
      'fields' => [],
      'where' => [],
      'order' => [],
      'limit' => 0,
      'start' => 0,
      'group_by' => [],
      'having' => [],
      'tables' => ['users'],
      'join' => [
        [
          'table' => 'roles',
          'on' => [
            'conditions' => [[
              'field' => 'users.role_id',
              'exp' => 'roles.id',
              'operator' => 'eq'
            ]],
            'logic' => 'AND'
          ],
          'type' => 'left'
        ],
        [
          'table' => 'profiles',
          'alias' => 'profiles_alias',
          'on' => [
            'conditions' => [[
              'field' => 'users.profile_id',
              'exp' => 'profiles.id',
              'operator' => '='
            ]],
            'logic' => 'AND'
          ],
          'type' => 'right'
        ]
      ],
      'aliases' => [],
      'values' => [],
      'filters' => [
        'conditions' => [],
        'logic' => 'AND'
      ],
      'hashed_join' => [[
        'conditions' => [[
          'exp' => 'roles.id',
          'field' => 'users.role_id',
          'operator' => 'eq'
        ]],
        'logic' => 'AND'
      ],[
        'conditions' => [[
          'exp' => 'profiles.id',
          'field' => 'users.profile_id',
          'operator' => '='
        ]],
        'logic' => 'AND'
      ]],
      'hashed_where' => [
        'conditions' => [],
        'logic' => 'AND'
      ],
      'hashed_having' => [],
      'bbn_db_treated' => true,
      'write' => false,
      'ignore' => false,
      'count' => false
    ];

    $result = $this->getNonPublicMethod('_treat_arguments')
      ->invoke(self::$pgsql, $cfg);

    $this->assertArrayHasKey('hash', $result);
    unset($result['hash']);
    $this->assertSame($expected, $result);
  }

  /** @test */
  public function treat_arguments_method_testing_having()
  {
    $cfg = [
      'tables' => 'payments',
      'fields' => [
        'sum' => 'SUM(*)',
        'user_id'
      ],
      'group_by' => ['user_id'],
      'having' => [
        'conditions' => [[
          'field' => 'sum',
          'operator' => '>',
          'value' => 24000
        ]]
      ]
    ];

    $expected = [
      'kind' => 'SELECT',
      'fields' => ['sum' => 'SUM(*)', 'user_id'],
      'where' => [],
      'order' => [],
      'limit' => 0,
      'start' => 0,
      'group_by' => ['user_id'],
      'having' => [
        'conditions' => [[
          'conditions' => [[
            'field' => 'sum',
            'operator' => '>',
            'value' => 24000
          ]],
          'logic' => 'AND'
        ]],
        'logic' => 'AND'
      ],
      'tables' => ['payments'],
      'aliases' => [
        'SUM(*)' => 'sum'
      ],
      'values' => [24000],
      'filters' => [
        'conditions' => [],
        'logic' => 'AND'
      ],
      'join' => [],
      'hashed_join' => [],
      'hashed_where' => [
        'conditions' => [],
        'logic' => 'AND'
      ],
      'hashed_having' => [
        'conditions' => [[
          'conditions' => [[
            'field' => 'sum',
            'operator' => '>'
          ]],
          'logic' => 'AND'
        ]],
        'logic' => 'AND'
      ],
      'bbn_db_treated' => true,
      'write' => false,
      'ignore' => false,
      'count' => false
    ];

    $result = $this->getNonPublicMethod('_treat_arguments')
      ->invoke(self::$pgsql, $cfg);

    $this->assertArrayHasKey('hash', $result);
    unset($result['hash']);
    $this->assertSame($expected, $result);
  }

  /** @test */
  public function treat_arguments_throws_an_exceptions_if_table_is_not_provided()
  {
    $this->expectException(\Error::class);

    $this->getNonPublicMethod('_treat_arguments')
      ->invoke(self::$pgsql, ['foo' => 'bar']);
  }

  /** @test */
  public function treat_arguments_method_returns_the_given_cfg_as_is_if_bbn_db_treated_exists()
  {
    $cfg = [
      'bbn_db_treated' => true,
      'tables' => ['users']
    ];

    $this->assertSame(
      $cfg,
      $this->getNonPublicMethod('_treat_arguments')
        ->invoke(self::$pgsql, $cfg)
    );
  }

  /** @test */
  public function adapt_filters_method_test()
  {
    $cfg = [
      'filters' => [
        'conditions' => [],
        'logic' => 'AND'
      ],
      'having' => [
        'conditions' => [[
          'field' => 'sum',
          'operator' => '>',
          'value' => 230
        ]]
      ]
    ];

    $expected = [
      'filters' => [
        'logic' => 'AND',
        'conditions' => []
      ],
      'having' => [
        'logic' => 'AND',
        'conditions' => [[
          'conditions' => [[
            'field' => 'sum',
            'operator' => '>',
            'value' => 230
          ]]
        ],
          []
        ],
      ]
    ];

    $method = $this->getNonPublicMethod('_adapt_filters');

    $method->invokeArgs(self::$pgsql, [&$cfg]);

    $this->assertSame($expected, $cfg);

    $cfg2 = [
      'filters' => [
        'conditions' => [[
          'field' => 'id',
          'operator' => '=',
          'value' => 33
        ]],
        'logic' => 'AND'
      ],
      'having' => []
    ];

    $expected2 = [
      'filters' => [
        'logic' => 'AND',
        'conditions' => [[
          'field' => 'id',
          'operator' => '=',
          'value' => 33
        ]]
      ],
      'having' => []
    ];

    $method->invokeArgs(self::$pgsql, [&$cfg2]);

    $this->assertSame($expected2, $cfg2);
  }

  /** @test */
  public function adapt_bit_method_test_when_there_is_an_aggregate_function()
  {
    $cfg = [
      'fields' => ['sum' => 'SUM(*)', 'max' => 'MAX(*)'],
      'filters' => [
        'conditions' => [[
          'field' => 'id',
          'operator' => '>',
          'value' => 33
        ], [
          'field' => 'sum',
          'operator' => '>',
          'value' => 44
        ],[
          'field' => 'AVG(*)',
          'operator' => '>',
          'value' => 55
        ],[
          'logic' => 'AND',
          'conditions' => [[
            'field' => 'id',
            'operator' => '>',
            'exp' => 'max'
          ],[
            'field' => 'id',
            'operator' => '<',
            'exp' => 'MIN(*)'
          ],[
            'field' => 'id',
            'operator' => '<',
            'value' => 99
          ]]
        ]],
        'logic' => 'AND'
      ],
      'having' => []
    ];

    $expected = [
      $filters = [
        'logic' => 'AND',
        'conditions' => [[
          'field' => 'id',
          'operator' => '>',
          'value' => 33
        ],[
          'logic' => 'AND',
          'conditions' => [[
            'field' => 'id',
            'operator' => '>',
            'exp' => 'max'
          ],[
            'field' => 'id',
            'operator' => '<',
            'exp' => 'MIN(*)'
          ],[
            'field' => 'id',
            'operator' => '<',
            'value' => 99
          ]]
        ]]
      ],
      $having = [
        'logic' => 'AND',
        'conditions' => [[
          'field' => 'sum',
          'operator' => '>',
          'value' => 44
        ],[
          'field' => 'AVG(*)',
          'operator' => '>',
          'value' => 55
        ],[
          'field' => 'id',
          'operator' => '>',
          'exp' => 'max'
        ],[
          'field' => 'id',
          'operator' => '<',
          'exp' => 'MIN(*)'
        ]]
      ]
    ];

    $method = $this->getNonPublicMethod('_adapt_bit');

    $result = $method->invoke(self::$pgsql, $cfg, $cfg['filters']);

    $this->assertSame([$filters, $having], $result);
  }

  /** @test */
  public function adapt_bit_method_test_when_there_is_no_an_aggregate_function()
  {
    $cfg = [
      'fields' => ['user_full_name' => 'name'],
      'filters' => [
        'conditions' => [[
          'field' => 'id',
          'operator' => '>',
          'value' => 33
        ], [
          'field' => 'user_full_name',
          'operator' => '!=',
          'value' => 'John'
        ]],
        'logic' => 'AND'
      ],
      'having' => []
    ];

    $expected = [
      [
        'logic' => 'AND',
        'conditions' => [[
          'field' => 'id',
          'operator' => '>',
          'value' => 33
        ],[
          'field' => 'user_full_name',
          'operator' => '!=',
          'value' => 'John'
        ]]
      ],
      []
    ];

    $method = $this->getNonPublicMethod('_adapt_bit');

    $result = $method->invoke(self::$pgsql, $cfg, $cfg['filters']);

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function set_limit_1_method_test_when_the_given_argument_is_an_array_of_array()
  {
    $cfg = [[
      'table' => 'users',
    ]];

    $expected = [[
      'table' => 'users',
      'limit' => 1
    ]];

    $this->assertSame(
      $expected,
      $this->getNonPublicMethod('_set_limit_1')
        ->invoke(self::$pgsql, $cfg)
    );
  }

  /** @test */
  public function set_limit_1_method_test_when_given_args_is_a_numeric_array_with_only_table_name()
  {
    $cfg      = ['users'];
    $expected = [
      'users', // Table name
      [], // Fields
      [], // Where
      [], // Order
      1, // Limit
      0 // Start
    ];

    $this->assertSame(
      $expected,
      $this->getNonPublicMethod('_set_limit_1')
        ->invoke(self::$pgsql, $cfg)
    );
  }

  /** @test */
  public function set_limit_1_method_test_when_the_given_argument_is_a_numeric_array()
  {
    $cfg      = [
      'users',
      ['username', 'name'],
      [['id', '>', 9]],
      ['name' => 'desc'],
      3,
      1
    ];

    $expected = [
      'users', // Table name
      ['username', 'name'], // Fields
      [['id', '>', 9]], // Where
      ['name' => 'desc'], // Order
      3, // Limit
      3 // Start
    ];

    $this->assertSame(
      $expected,
      $this->getNonPublicMethod('_set_limit_1')
        ->invoke(self::$pgsql, $cfg)
    );
  }

  /** @test */
  public function addStatement_method_adds_query_statement_and_parameters_when_last_enabled_is_true()
  {
    $this->assertNull(
      $this->getNonPublicProperty('last_real_query')
    );

    $this->assertSame(
      self::$real_params_default,
      $this->getNonPublicProperty('last_real_params')
    );

    $this->assertTrue(
      $this->getNonPublicProperty('_last_enabled')
    );

    $this->assertNull(
      $this->getNonPublicProperty('last_query')
    );

    $this->assertSame(
      self::$real_params_default,
      $this->getNonPublicProperty('last_params')
    );


    $result = $this->getNonPublicMethod('addStatement')
      ->invoke(self::$pgsql, $stmt = 'SELECT * FROM users', $params = ['foo' => 'bar']);

    $this->assertSame(
      $stmt,
      $this->getNonPublicProperty('last_real_query')
    );

    $this->assertSame(
      $params,
      $this->getNonPublicProperty('last_real_params')
    );

    $this->assertSame(
      $stmt,
      $this->getNonPublicProperty('last_query')
    );

    $this->assertSame(
      $params,
      $this->getNonPublicProperty('last_params')
    );

    $this->assertInstanceOf(Pgsql::class, $result);
  }

  /** @test */
  public function addStatement_method_adds_query_statement_and_parameters_when_last_enabled_is_false()
  {
    $this->assertNull(
      $this->getNonPublicProperty('last_real_query')
    );

    $this->assertSame(
      self::$real_params_default,
      $this->getNonPublicProperty('last_real_params')
    );

    $this->assertNull(
      $this->getNonPublicProperty('last_query')
    );

    $this->assertSame(
      self::$real_params_default,
      $this->getNonPublicProperty('last_params')
    );

    $this->setNonPublicPropertyValue('_last_enabled', false);

    $result = $this->getNonPublicMethod('addStatement')
      ->invoke(self::$pgsql, $stmt = 'SELECT * FROM users', $params = ['foo' => 'bar']);

    $this->assertSame(
      $stmt,
      $this->getNonPublicProperty('last_real_query')
    );

    $this->assertSame(
      $params,
      $this->getNonPublicProperty('last_real_params')
    );

    $this->assertNull(
      $this->getNonPublicProperty('last_query')
    );

    $this->assertSame(
      self::$real_params_default,
      $this->getNonPublicProperty('last_params')
    );

    $this->assertInstanceOf(Pgsql::class, $result);
  }

  /** @test */
  public function getRealLastParams_method_returns_the_last_real_params()
  {
    $this->setNonPublicPropertyValue('last_real_params', ['foo' => 'bar']);

    $this->assertSame(
      ['foo' => 'bar'],
      self::$pgsql->getRealLastParams()
    );
  }

  /** @test */
  public function realLast_method_returns_the_real_last_query()
  {
    $this->setNonPublicPropertyValue('last_real_query', 'SELECT * FROM users');

    $this->assertSame(
      'SELECT * FROM users',
      self::$pgsql->realLast()
    );
  }

  /** @test */
  public function getLastValues_method_returns_the_last_params_values()
  {
    $this->setNonPublicPropertyValue('last_params', [
      'values' => ['foo' => 'bar']
    ]);

    $this->assertSame(
      ['foo' => 'bar'],
      self::$pgsql->getLastValues()
    );

    $this->setNonPublicPropertyValue('last_params', null);

    $this->assertNull(self::$pgsql->getLastValues());
  }

  /** @test */
  public function getLastParams_method_returns_the_last_params()
  {
    $this->setNonPublicPropertyValue('last_params', [
      'values' => ['foo' => 'bar']
    ]);

    $this->assertSame(
      ['values' => ['foo' => 'bar']],
      self::$pgsql->getLastParams()
    );
  }

  /** @test */
  public function setLastInsertId_method_changes_the_value_of_last_inserted_id_for_the_given_id()
  {
    $this->setNonPublicPropertyValue('id_just_inserted', 22);
    $this->setNonPublicPropertyValue('last_insert_id', 22);

    $result = self::$pgsql->setLastInsertId(44);

    $this->assertSame(
      44,
      $this->getNonPublicProperty('last_insert_id')
    );

    $this->assertSame(
      44,
      $this->getNonPublicProperty('id_just_inserted')
    );

    $this->assertInstanceOf(Pgsql::class, $result);
  }

  /** @test */
  public function setLastInsertId_method_changes_the_value_of_last_inserted_id_from_last_insert_query()
  {
    $this->createTable('users', function () {
      return 'id bigserial PRIMARY KEY, email VARCHAR(25)';
    });

    $this->insertOne('users', ['email' => 'mail@test.com']);

    self::$pgsql->setLastInsertId();

    $this->assertSame(
      1,
      $this->getNonPublicProperty('last_insert_id')
    );

    $this->assertNull(
      $this->getNonPublicProperty('id_just_inserted')
    );
  }

  /** @test */
  public function setLastInsertId_method_changes_the_value_of_last_inserted_id_from_id_just_inserted_property_when_not_null_ignoring_last_query()
  {
    $this->setNonPublicPropertyValue('id_just_inserted', 333);

    $this->createTable('users', function () {
      return 'id smallserial PRIMARY KEY, email VARCHAR(25)';
    });

    $this->insertOne('users', ['email' => 'mail@test.com']);

    self::$pgsql->setLastInsertId();

    $this->assertSame(
      333,
      $this->getNonPublicProperty('last_insert_id')
    );
  }

  /** @test */
  public function lastId_method_returns_the_last_inserted_id()
  {
    $this->setNonPublicPropertyValue('last_insert_id', 234);

    $this->assertSame(234, self::$pgsql->lastId());

    $this->setNonPublicPropertyValue(
      'last_insert_id',
      hex2bin('7f4a2c70bcac11eba47652540000cfaa')
    );

    $this->assertSame(
      '7f4a2c70bcac11eba47652540000cfaa',
      self::$pgsql->lastId()
    );

    $this->setNonPublicPropertyValue('last_insert_id', null);

    $this->assertFalse(self::$pgsql->lastId());
  }

  /** @test */
  public function last_method_returns_the_last_for_the_current_connection()
  {
    $this->setNonPublicPropertyValue(
      'last_query',
      'SELECT * FROM users'
    );

    $this->assertSame(
      'SELECT * FROM users',
      self::$pgsql->last()
    );
  }

  /** @test */
  public function countQueries_method_returns_the_count_of_queries()
  {
    $this->setNonPublicPropertyValue('queries', ['foo' => 'bar', 'bar' => 'foo']);

    $this->assertSame(2, self::$pgsql->countQueries());
  }

  /** @test */
  public function flush_method_deletes_all_the_recorded_queries_and_returns_their_count()
  {
    $this->setNonPublicPropertyValue('queries', ['foo' => 'bar', 'bar' => 'foo']);

    $result = self::$pgsql->flush();

    $this->assertSame([], $this->getNonPublicProperty('queries'));
    $this->assertSame([], $this->getNonPublicProperty('list_queries'));

    $this->assertSame(2, $result);
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
      $method->invoke(self::$pgsql, ['foo' => 'bar', 'foo2' => 'bar2'])
    );

    $expected = sprintf($expected_string, md5('--foo----bar----baz--'));
    $this->assertSame(
      $expected,
      $method->invoke(self::$pgsql, 'foo', 'bar', 'baz')
    );

    $expected = sprintf($expected_string, md5('--foo--' . serialize(['bar', 'bar2'])));
    $this->assertSame($expected, $method->invoke(self::$pgsql,[
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

    $set_hash_method->invoke(self::$pgsql, $args = ['foo' => 'bar', 'foo2' => 'bar2']);
    $this->assertSame(
      $make_hash_method->invoke(self::$pgsql, $args),
      $this->getNonPublicProperty('hash')
    );


    $set_hash_method->invoke(self::$pgsql, 'foo', 'bar', 'baz');
    $this->assertSame(
      $make_hash_method->invoke(self::$pgsql, 'foo', 'bar', 'baz'),
      $this->getNonPublicProperty('hash')
    );

    $set_hash_method->invoke(self::$pgsql, $args = [
      'foo',
      'foo2' => ['bar', 'bar2']
    ]);
    $this->assertSame(
      $make_hash_method->invoke(self::$pgsql, $args),
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

    $set_hash_method->invoke(self::$pgsql, 'foo', 'bar');

    $this->assertSame(
      $make_hash_method->invoke(self::$pgsql, 'foo', 'bar'),
      self::$pgsql->getHash()
    );
  }

  /** @test */
  public function error_method_sets_an_error_and_acts_based_on_the_error_mode_when_the_given_error_is_string()
  {
    $this->assertFalse($this->getNonPublicProperty('_has_error'));
    $this->assertFalse($this->getNonPublicProperty('_has_error_all'));
    $this->assertNull($this->getNonPublicProperty('last_error'));

    $this->createDir('logs');

    self::$pgsql->error('An error');

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

    self::$pgsql->error(new \Exception('An error'));

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

    self::$pgsql->error('An error');
  }

  /** @test */
  public function check_method_checks_if_the_database_is_ready_to_process_a_query()
  {
    $this->assertTrue(self::$pgsql->check());
  }

  /** @test */
  public function check_method_returns_true_if_there_is_an_error_the_error_mode_is_continue()
  {
    $this->setNonPublicPropertyValue('on_error', 'continue');
    $this->setNonPublicPropertyValue('_has_error', true);
    $this->setNonPublicPropertyValue('_has_error_all', true);

    $this->assertTrue(self::$pgsql->check());
  }

  /** @test */
  public function check_method_returns_false_if_there_is_are_error_for_all_connection_and_mode_is_stop_all()
  {
    $this->setNonPublicPropertyValue('_has_error_all', true);
    $this->setNonPublicPropertyValue('on_error', 'stop_all');

    $this->assertFalse(self::$pgsql->check());
  }

  /** @test */
  public function check_method_returns_true_if_there_is_are_error_for_all_connection_and_mode_is_not_stop_all()
  {
    $this->setNonPublicPropertyValue('_has_error_all', true);
    $this->setNonPublicPropertyValue('on_error', 'stop');

    $this->assertTrue(self::$pgsql->check());
  }

  /** @test */
  public function check_method_returns_false_if_there_is_error_for_the_current_connection_and_mode_is_stop()
  {
    $this->setNonPublicPropertyValue('_has_error', true);
    $this->setNonPublicPropertyValue('on_error', 'stop');

    $this->assertFalse(self::$pgsql->check());
  }

  /** @test */
  public function check_method_returns_false_if_there_is_error_for_the_current_connection_and_mode_is_stop_all()
  {
    $this->setNonPublicPropertyValue('_has_error', true);
    $this->setNonPublicPropertyValue('on_error', 'stop_all');

    $this->assertFalse(self::$pgsql->check());
  }

  /** @test */
  public function check_method_returns_false_when_the_current_connection_is_null()
  {
    $old_current = $this->getNonPublicProperty('current');

    $this->setNonPublicPropertyValue('current', null);

    $this->assertFalse(self::$pgsql->check());

    $this->setNonPublicPropertyValue('current', $old_current);
  }

  /** @test */
  public function setErrorMode_method_sets_the_error_mode()
  {
    $result = self::$pgsql->setErrorMode('stop_all');

    $this->assertSame(
      'stop_all',
      $this->getNonPublicProperty('on_error')
    );

    $this->assertInstanceOf(Pgsql::class, $result);
  }

  /** @test */
  public function getErrorMode_method_returns_the_current_error_mode()
  {
    $this->setNonPublicPropertyValue('on_error', 'stop');

    $this->assertSame(Errors::E_STOP, self::$pgsql->getErrorMode());
  }

  /** @test */
  public function getLogLine_method_returns_a_string_with_given_text_in_the_middle_of_a_line_of_logs()
  {
    $this->assertSame(
      '-------------------------------------- foo --------------------------------------',
      self::$pgsql::getLogLine('foo')
    );

    $this->assertSame(
      '--------------------------------- I\'m an error ----------------------------------',
      self::$pgsql::getLogLine('I\'m an error')
    );
  }

  /** @test */
  public function startFancyStuff_method_sets_the_query_class_as_pdo_derived_statement_class()
  {
    self::$pgsql->startFancyStuff();

    $result = $this->getNonPublicProperty('pdo')->getAttribute(\PDO::ATTR_STATEMENT_CLASS);

    $this->assertIsArray($result);
    $this->assertSame(Query::class, $result[0]);
    $this->assertSame(1, $this->getNonPublicProperty('_fancy'));
  }

  /** @test */
  public function stopFancyStuff_method_sets_statement_class_to_pdo_statement()
  {
    self::$pgsql->stopFancyStuff();

    $result = $this->getNonPublicProperty('pdo')->getAttribute(\PDO::ATTR_STATEMENT_CLASS);

    $this->assertIsArray($result);

    $this->assertSame(\PDOStatement::class, $result[0]);
    $this->assertFalse($this->getNonPublicProperty('_fancy'));
  }

  /** @test */
  public function set_start_method_test_when_the_given_argument_is_an_array_of_array()
  {
    $cfg = [[
      'table' => 'users'
    ]];

    $expected = [[
      'table' => 'users',
      'start' => 3
    ]];

    $this->assertSame(
      $expected,
      $this->getNonPublicMethod('_set_start')
        ->invoke(self::$pgsql, $cfg, 3)
    );
  }

  /** @test */
  public function set_start_method_test_when_given_args_is_a_numeric_array_with_only_table_name()
  {
    $cfg      = ['users'];
    $expected = [
      'users', // Table name
      [], // Fields
      [], // Where
      [], // Order
      1, // Limit
      6 // Start
    ];

    $this->assertSame(
      $expected,
      $this->getNonPublicMethod('_set_start')
        ->invoke(self::$pgsql, $cfg, 6)
    );
  }

  /** @test */
  public function set_start_method_test_when_the_given_argument_is_a_numeric_array()
  {
    $cfg      = [
      'users',
      ['username', 'name'],
      [['id', '>', 9]],
      ['name' => 'desc'],
      3,
      1
    ];

    $expected = [
      'users', // Table name
      ['username', 'name'], // Fields
      [['id', '>', 9]], // Where
      ['name' => 'desc'], // Order
      3, // Limit
      10 // Start
    ];

    $this->assertSame(
      $expected,
      $this->getNonPublicMethod('_set_start')
        ->invoke(self::$pgsql, $cfg, 10)
    );
  }

  /** @test */
  public function retrieveQuery_method_retrieves_a_query_from_the_given_hash()
  {
    $this->setNonPublicPropertyValue('queries', [
      '12345' => ['foo' => 'bar'],
      '54321' => '12345'
    ]);

    $this->assertSame(['foo' => 'bar'], self::$pgsql->retrieveQuery('12345'));
    $this->assertSame(['foo' => 'bar'], self::$pgsql->retrieveQuery('54321'));
    $this->assertNull(self::$pgsql->retrieveQuery('foo'));
  }

  /** @test */
  public function extractFields_method_test()
  {
    $cfg = [
      'available_fields' => [
        'users.id'   => 'users',
        'role_id'    => '',
        'profile_id' => 'profiles',
        'payments.card_id' => 'payments'
      ]
    ];

    $conditions = [
      'conditions' => [[
        'field'     => 'users.id',
        'operator'  => '>',
        'value'     => 10
      ],[
        'field'     => 'role_id',
        'operator'  => '=',
        'exp'       => 'roles.id'
      ],[
        'field'     => 'profiles.id',
        'operator'  => '=',
        'exp'       => 'profile_id'
      ],[
        'field'     => 'permissions.id',
        'operator'  => '=',
        'exp'       => 'permission_id'
      ],[
        'conditions' => [[
          'field'     => 'cards.id',
          'operator'  => '=',
          'exp'       =>  'payments.card_id'
        ]]
      ]]
    ];

    $expected = ['users.id', 'role_id', 'profiles.profile_id', 'payments.card_id'];
    $result   = [];

    $this->assertSame(
      $expected,
      self::$pgsql->extractFields($cfg, $conditions, $result)
    );

    $this->assertSame($expected, $result);

    $this->assertSame(
      $expected,
      self::$pgsql->extractFields($cfg, $conditions['conditions'])
    );
  }

  /** @test */
  public function filterFilters_method_returns_an_array_of_specific_filters_added_to_the_existing_ones()
  {
    $cfg = [
      'filters' => [
        'conditions' => [[
          'field' => 'name',
          'operator' => '=',
          'value' => 'John'
        ],[
          'field' => 'username',
          'operator' => '=',
          'value' => 'jdoe'
        ],[
          'conditions' => [[
            'field' => 'name',
            'operator' => '=',
            'value' => 'Sam'
          ],[
            'field' => 'username',
            'operator' => '=',
            'value' => 'sdoe'
          ]]
        ]]
      ]
    ];

    $this->assertSame(
      [
        ['field' => 'name', 'operator' => '=', 'value' => 'John'],
        ['field' => 'name', 'operator' => '=', 'value' => 'Sam'],
      ],
      self::$pgsql->filterFilters($cfg, 'name')
    );

    $this->assertSame(
      [
        ['field' => 'name', 'operator' => '=', 'value' => 'John'],
        ['field' => 'name', 'operator' => '=', 'value' => 'Sam'],
      ],
      self::$pgsql->filterFilters($cfg, 'name', '=')
    );

    $this->assertSame(
      [],
      self::$pgsql->filterFilters($cfg, 'name', '!=')
    );

    $this->assertNull(
      self::$pgsql->filterFilters(['table' => 'users'], 'name')
    );

    $this->assertSame(
      [],
      self::$pgsql->filterFilters(['filters' => []], 'name')
    );
  }

  /** @test */
  public function getOne_method_executes_the_given_query_and_extracts_the_first_column_result()
  {
    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY, 
              username VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'foo'],
      ['username' => 'bar'],
    ]);

    $this->assertSame(
      'foo',
      self::$pgsql->getOne("SELECT username FROM users WHERE id = ?", 1)
    );
  }

  /** @test */
  public function getOne_method_returns_false_when_query_returns_false()
  {
    $this->assertFalse(
      self::$pgsql->getOne('SELECT username FROM users WHERE id = ?', 1)
    );
  }

  /** @test */
  public function getKeyVal_method_returns_an_array_indexed_with_the_first_field_of_the_request()
  {
    $this->createTable('users', function () {
      return 'id smallserial PRIMARY KEY, 
              username VARCHAR(255), 
              email VARCHAR(255), 
              name VARCHAR(255)';
    });

    $this->assertEmpty(
      self::$pgsql->getKeyVal('SELECT * FROM users')
    );

    $this->insertMany('users', [
      [
        'username' => 'jdoe',
        'name'     => 'John Doe',
        'email'    => 'jdoe@mail.com'
      ],
      [
        'username' => 'sdoe',
        'name'     => 'Smith Doe',
        'email'    => 'sdoe@mail.com'
      ]
    ]);


    $this->assertSame(
      [
        'jdoe' => [
          'name'  => 'John Doe',
          'email' => 'jdoe@mail.com'
        ],
        'sdoe' => [
          'name'  => 'Smith Doe',
          'email' => 'sdoe@mail.com'
        ],
      ],
      self::$pgsql->getKeyVal('SELECT username, name, email FROM users')
    );

    $this->assertSame(
      [
        'jdoe' => [
          'name'  => 'John Doe',
          'email' => 'jdoe@mail.com'
        ]
      ],
      self::$pgsql->getKeyVal('SELECT username, name, email FROM users WHERE id = ?', 1)
    );

    $this->assertSame(
      [
        1 => [
          'username' => 'jdoe',
          'email' => 'jdoe@mail.com',
          'name' => 'John Doe'
        ],
        2 => [
          'username' => 'sdoe',
          'email' => 'sdoe@mail.com',
          'name' => 'Smith Doe'
        ]
      ],
      self::$pgsql->getKeyVal('SELECT * FROM users')
    );
  }

  /** @test */
  public function getKeyVal_method_returns_null_when_query_returns_false()
  {
    $pgsql = \Mockery::mock(Pgsql::class)->makePartial();

    $pgsql->shouldReceive('query')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $pgsql->getKeyVal('SELECT * FROM users')
    );
  }

  /** @test */
  public function getColArray_method_returns_an_array_of_the_values_of_single_field_as_result_from_query()
  {
    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY, 
              username VARCHAR(255)';
    });

    $this->assertEmpty(
      self::$pgsql->getColArray('SELECT id FROM users')
    );

    $this->insertMany('users', [
      ['username' => 'jdoe'],
      ['username' => 'sdoe'],
    ]);

    $this->assertSame(
      [1, 2],
      self::$pgsql->getColArray('SELECT id FROM users')
    );

    $this->assertSame(
      [1, 2],
      self::$pgsql->getColArray('SELECT id, username FROM users')
    );

    $this->assertSame(
      [1, 2],
      self::$pgsql->getColArray('SELECT * FROM users')
    );

    $this->assertSame(
      ['jdoe', 'sdoe'],
      self::$pgsql->getColArray('SELECT username FROM users')
    );

    $this->assertSame(
      ['jdoe', 'sdoe'],
      self::$pgsql->getColArray('SELECT username, id FROM users')
    );
  }

  /** @test */
  public function select_method_returns_the_first_row_resulting_from_query_as_an_object()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id bytea PRIMARY KEY, 
              username VARCHAR(255)';
    });

    self::$pgsql->insert('users', ['username' => 'jdoe']);
    self::$pgsql->insert('users', ['username' => 'sdoe']);

    $result = self::$pgsql->select('users', ['id', 'username']);

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('id', $result);
    $this->assertIsString($result->id);
    $this->assertObjectHasAttribute('username', $result);
    $this->assertSame('jdoe', $result->username);

    $result = self::$pgsql->select('users', 'id');

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('id', $result);
    $this->assertIsString($result->id);
    $this->assertObjectNotHasAttribute('username', $result);

    $result = self::$pgsql->select('users', [], [], ['username' => 'DESC']);

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('id', $result);
    $this->assertIsString($result->id);
    $this->assertObjectHasAttribute('username', $result);
    $this->assertSame('sdoe', $result->username);

    $result = self::$pgsql->select('users', [], ['id'], ['username' => 'ASC'], 1);

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('id', $result);
    $this->assertIsString($result->id);
    $this->assertObjectHasAttribute('username', $result);
    $this->assertSame('sdoe', $result->username);

    $this->assertNull(
      self::$pgsql->select('users', [], ['id' => 33])
    );

    $this->assertNull(
      self::$pgsql->select('users', [], [], [], 3)
    );
  }

  /** @test */
  public function selectAll_method_returns_table_rows_resulting_from_query_as_an_array_of_objects()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY, 
              username VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'jdoe'],
      ['username' => 'sdoe'],
    ]);

    $result = self::$pgsql->selectAll('users', []);

    $this->assertIsArray($result);
    $this->assertCount(2, $result);

    $this->assertIsObject($result[0]);
    $this->assertObjectHasAttribute('id', $result[0]);
    $this->assertObjectHasAttribute('username', $result[0]);
    $this->assertSame(1, $result[0]->id);
    $this->assertSame('jdoe', $result[0]->username);

    $this->assertIsObject($result[1]);
    $this->assertObjectHasAttribute('id', $result[1]);
    $this->assertObjectHasAttribute('username', $result[1]);
    $this->assertSame(2, $result[1]->id);
    $this->assertSame('sdoe', $result[1]->username);

    $result = self::$pgsql->selectAll('users', 'username', [], ['id' => 'DESC']);

    $this->assertIsArray($result);
    $this->assertCount(2, $result);

    $this->assertIsObject($result[0]);
    $this->assertObjectHasAttribute('username', $result[0]);
    $this->assertSame('sdoe', $result[0]->username);

    $this->assertIsObject($result[1]);
    $this->assertObjectHasAttribute('username', $result[1]);
    $this->assertSame('jdoe', $result[1]->username);

    $result = self::$pgsql->selectAll('users', 'username', [], ['id' => 'DESC'], 1);

    $this->assertIsArray($result);
    $this->assertCount(1, $result);

    $this->assertIsObject($result[0]);
    $this->assertObjectHasAttribute('username', $result[0]);
    $this->assertSame('sdoe', $result[0]->username);

    $this->assertSame(
      [],
      self::$pgsql->selectAll('users', [], ['id' => 33])
    );

    $this->assertSame(
      [],
      self::$pgsql->selectAll('users', [], [], [], 1, 3)
    );
  }

  /** @test */
  public function selectAll_method_returns_null_when_exec_method_returns_false()
  {
    $pgsql = \Mockery::mock(Pgsql::class)
      ->shouldAllowMockingProtectedMethods()
      ->makePartial();

    $pgsql->shouldReceive('_exec')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $pgsql->selectAll('user', [])
    );
  }

  /** @test */
  public function iselect_method_returns_the_first_row_resulting_from_query_as_numeric_array()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY,
              username VARCHAR(255) UNIQUE,
              name VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'jdoe', 'name' => 'John Doe'],
      ['username' => 'sdoe', 'name' => 'Smith Doe'],
    ]);

    $this->assertSame(
      [1, 'jdoe', 'John Doe'],
      self::$pgsql->iselect('users', [])
    );

    $this->assertSame(
      ['jdoe'],
      self::$pgsql->iselect('users', 'username')
    );

    $this->assertSame(
      [1, 'jdoe'],
      self::$pgsql->iselect('users', ['id', 'username'])
    );

    $this->assertSame(
      [2, 'sdoe'],
      self::$pgsql->iselect('users', ['id', 'username'], [], [], 1)
    );

    $this->assertSame(
      [2, 'sdoe'],
      self::$pgsql->iselect('users', ['id', 'username'], [],['id' => 'DESC'])
    );

    $this->assertNull(
      self::$pgsql->iselect('users', [], ['id' => 44])
    );
  }

  /** @test */
  public function iselectAll_method_returns_all_results_from_query_as_an_array_of_numeric_arrays()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY,
              username VARCHAR(255) UNIQUE,
              name VARCHAR(255) NOT NULL';
    });

    $this->insertMany('users', [
      ['username' => 'jdoe', 'name' => 'John Doe'],
      ['username' => 'sdoe', 'name' => 'Smith Doe']
    ]);

    $this->assertSame(
      [
        [1, 'jdoe', 'John Doe'],
        [2, 'sdoe', 'Smith Doe']
      ],
      self::$pgsql->iselectAll('users', [])
    );

    $this->assertSame(
      [
        ['jdoe'],
        ['sdoe']
      ],
      self::$pgsql->iselectAll('users', 'username')
    );

    $this->assertSame(
      [
        [1, 'John Doe'],
        [2, 'Smith Doe']
      ],
      self::$pgsql->iselectAll('users', ['id', 'name'])
    );

    $this->assertSame(
      [
        [2, 'Smith Doe']
      ],
      self::$pgsql->iselectAll('users', ['id', 'name'], ['id' => 2])
    );

    $this->assertSame(
      [
        [2, 'Smith Doe'],
        [1, 'John Doe']
      ],
      self::$pgsql->iselectAll('users', ['id', 'name'], [], ['id' => 'DESC'])
    );

    $this->assertSame(
      [
        [2, 'Smith Doe']
      ],
      self::$pgsql->iselectAll('users', ['id', 'name'], [], ['id' => 'DESC'], 1)
    );

    $this->assertEmpty(
      self::$pgsql->iselectAll('users', [], ['id' => 11])
    );
  }

  /** @test */
  public function iselectAll_method_returns_null_when_exec_function_returns_false()
  {
    $pgsql = \Mockery::mock(Pgsql::class)
      ->shouldAllowMockingProtectedMethods()
      ->makePartial();

    $pgsql->shouldReceive('_exec')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $pgsql->iselectAll('users', [])
    );
  }

  /** @test */
  public function rselect_method_returns_the_first_row_resulting_from_the_query_as_indexed_array()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY,
              username VARCHAR(255) UNIQUE,
              name VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'jdoe', 'name' => 'John Doe'],
      ['username' => 'sdoe', 'name' => 'Smith Doe']
    ]);

    $this->assertSame(
      ['id' => 1, 'username' => 'jdoe', 'name' => 'John Doe'],
      self::$pgsql->rselect('users', [])
    );

    $this->assertSame(
      ['id' => 2, 'username' => 'sdoe', 'name' => 'Smith Doe'],
      self::$pgsql->rselect('users', [], ['id' => 2])
    );

    $this->assertSame(
      ['username' => 'sdoe'],
      self::$pgsql->rselect('users', 'username', [], ['id' => 'DESC'])
    );

    $this->assertSame(
      ['id' => 2, 'username' => 'sdoe'],
      self::$pgsql->rselect('users', ['id', 'username'], [], [], 1)
    );

    $this->assertNull(
      self::$pgsql->rselect('users', ['id', 'username'], [], [], 3)
    );

    $this->assertNull(
      self::$pgsql->rselect('users', ['id', 'username'], ['id' => 33])
    );
  }

  /** @test */
  public function rselectAll_method_returns_query_results_as_an_array_of_indexed_arrays()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY,
              username VARCHAR(255) UNIQUE,
              name VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'jdoe', 'name' => 'John Doe'],
      ['username' => 'sdoe', 'name' => 'Smith Doe']
    ]);

    $this->assertSame(
      [
        [
          'id' => 1,
          'username' => 'jdoe',
          'name' => 'John Doe'
        ],
        [
          'id' => 2,
          'username' => 'sdoe',
          'name' => 'Smith Doe'
        ]
      ],
      self::$pgsql->rselectAll('users', [])
    );

    $this->assertSame(
      [
        [ 'username' => 'jdoe'],
        ['username' => 'sdoe']
      ],
      self::$pgsql->rselectAll('users', 'username')
    );

    $this->assertSame(
      [
        [
          'id' => 2,
          'name' => 'Smith Doe'
        ],
        [
          'id' => 1,
          'name' => 'John Doe'
        ]
      ],
      self::$pgsql->rselectAll('users', ['id', 'name'], [], ['id' => 'DESC'])
    );

    $this->assertSame(
      [
        [
          'id' => 2,
          'name' => 'Smith Doe'
        ]
      ],
      self::$pgsql->rselectAll('users', ['id', 'name'], [], [], 1, 1)
    );

    $this->assertEmpty(
      self::$pgsql->rselectAll('users', [], ['id' => 44])
    );

    $this->assertEmpty(
      self::$pgsql->rselectAll('users', [], [], [], 1, 33)
    );
  }

  /** @test */
  public function selectOne_method_returns_a_single_value_from_the_given_field_name()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id bigserial PRIMARY KEY,
              username VARCHAR(255) UNIQUE,
              name VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'jdoe', 'name' => 'John Doe'],
      ['username' => 'sdoe', 'name' => 'Smith Doe']
    ]);

    $this->assertSame(
      'jdoe',
      self::$pgsql->selectOne('users', 'username')
    );

    $this->assertSame(
      1,
      self::$pgsql->selectOne('users')
    );

    $this->assertSame(
      'Smith Doe',
      self::$pgsql->selectOne('users', 'name', ['id' => 2])
    );

    $this->assertSame(
      'Smith Doe',
      self::$pgsql->selectOne('users', 'name', [], ['id' => 'DESC'])
    );

    $this->assertSame(
      'Smith Doe',
      self::$pgsql->selectOne('users', 'name', [], [], 1)
    );

    $this->assertFalse(
      self::$pgsql->selectOne('users', 'username', ['id' => 333])
    );

    $this->assertFalse(
      self::$pgsql->selectOne('users', 'username', [], [], 44)
    );
  }

  /** @test */
  public function count_method_returns_the_number_of_records_in_the_table_for_the_given_arguments()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY,
              username VARCHAR(25) UNIQUE NOT NULL';
    });

    $this->insertMany('users',[
      ['username' => 'jdoe'], ['username' => 'sdoe']
    ]);

    $this->assertSame(2, self::$pgsql->count('users'));
    $this->assertSame(1, self::$pgsql->count('users', ['username' => 'jdoe']));
    $this->assertSame(0, self::$pgsql->count('users', ['id' => 22]));

    $this->assertSame(1, self::$pgsql->count([
      'table' => ['users'],
      'where' => ['username' => 'sdoe']
    ]));

    $this->assertSame(1, self::$pgsql->count([
      'tables' => ['users'],
      'where' => ['username' => 'sdoe']
    ]));

    $this->assertSame(2, self::$pgsql->count([
      'tables' => ['users']
    ]));
  }

  /** @test */
  public function count_method_returns_null_when_exec_returns_non_object()
  {
    $pgsql = \Mockery::mock(Pgsql::class)
      ->makePartial()
      ->shouldAllowMockingProtectedMethods();

    $pgsql->shouldReceive('_exec')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $pgsql->count('users')
    );
  }

  /** @test */
  public function selectAllByKeys_method_returns_an_array_indexed_with_the_first_field_of_the_request()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY,
              username VARCHAR(255) UNIQUE,
              name VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'jdoe', 'name' => 'John Doe'],
      ['username' => 'sdoe', 'name' => 'Smith Doe']
    ]);

    $this->assertSame(
      [
        1 => ['username' => 'jdoe', 'name' => 'John Doe'],
        2 => ['username' => 'sdoe', 'name' => 'Smith Doe']
      ],
      self::$pgsql->selectAllByKeys('users')
    );

    $this->assertSame(
      [
        'jdoe' => ['id' => 1, 'name' => 'John Doe'],
        'sdoe' => ['id' => 2, 'name' => 'Smith Doe']
      ],
      self::$pgsql->selectAllByKeys('users', ['username', 'id', 'name'])
    );

    $this->assertSame(
      [
        'sdoe' => ['id' => 2, 'name' => 'Smith Doe'],
        'jdoe' => ['id' => 1, 'name' => 'John Doe']
      ],
      self::$pgsql->selectAllByKeys('users', ['username', 'id', 'name'], [], ['id' => 'DESC'])
    );

    $this->assertSame(
      [
        'sdoe' => ['id' => 2, 'name' => 'Smith Doe']
      ],
      self::$pgsql->selectAllByKeys('users', ['username', 'id', 'name'], ['id' => 2])
    );

    $this->assertSame(
      [
        'sdoe' => ['id' => 2, 'name' => 'Smith Doe']
      ],
      self::$pgsql->selectAllByKeys('users', ['username', 'id', 'name'], [], [], 1, 1)
    );

    $this->assertEmpty(
      self::$pgsql->selectAllByKeys('users', [], ['id' => 33])
    );

    $this->assertEmpty(
      self::$pgsql->selectAllByKeys('users', [], [], [], 1, 33)
    );
  }

  /** @test */
  public function selectAllByKeys_method_returns_null_when_no_results_found_and_check_returns_false()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT';
    });

    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull(
      self::$pgsql->selectAllByKeys('users')
    );
  }

  /** @test */
  public function stat_method_returns_an_array_with_the_count_of_values_resulting_from_the_query()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id smallserial PRIMARY KEY,
              username VARCHAR(255) UNIQUE,
              name VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'jdoe', 'name' => 'John Doe'],
      ['username' => 'jdoe2', 'name' => 'John Doe'],
      ['username' => 'jdoe3', 'name' => 'John Doe'],
      ['username' => 'sdoe', 'name' => 'Smith Doe']
    ]);

    $this->assertSame(
      [
        ['name' => 'John Doe', 'num' => 3],
        ['name' => 'Smith Doe', 'num' => 1]
      ],
      self::$pgsql->stat('users', 'name')
    );

    $this->assertSame(
      [
        ['name' => 'Smith Doe', 'num' => 1],
        ['name' => 'John Doe', 'num' => 3]
      ],
      self::$pgsql->stat('users', 'name', [], ['name' => 'DESC'])
    );

    $this->assertSame(
      [
        ['name' => 'John Doe', 'num' => 3]
      ],
      self::$pgsql->stat('users', 'name', ['name' => 'John Doe'])
    );
  }

  /** @test */
  public function stat_method_returns_null_when_check_method_returns_null()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull(self::$pgsql->stat('users', 'name'));
  }

  /** @test */
  public function countFieldValues_method_returns_count_of_identical_values_in_a_field_as_array()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY,
              name VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['name' => 'John Doe'],
      ['name' => 'John Doe'],
      ['name' => 'John Doe'],
      ['name' => 'Smith Doe']
    ]);

    $this->assertSame(
      [
        ['val' => 'Smith Doe', 'num' => 1],
        ['val' => 'John Doe', 'num' => 3]
      ],
      self::$pgsql->countFieldValues('users', 'name')
    );

    $this->assertSame(
      [
        ['val' => 'Smith Doe', 'num' => 1],
        ['val' => 'John Doe', 'num' => 3]
      ],
      self::$pgsql->countFieldValues([
        'table' => 'users',
        'fields' => ['name']
      ])
    );

    $this->assertSame(
      [
        ['val' => 'Smith Doe', 'num' => 1],
        ['val' => 'John Doe', 'num' => 3]
      ],
      self::$pgsql->countFieldValues('users', 'name', [], ['name' => 'DESC'])
    );

    $this->assertSame(
      [
        ['val' => 'John Doe', 'num' => 3]
      ],
      self::$pgsql->countFieldValues('users', 'name', ['name' => 'John Doe'])
    );

    $this->assertEmpty(
      self::$pgsql->countFieldValues('users', 'name', ['name' => 'foo'])
    );
  }

  /** @test */
  public function getColumnValues_method_numeric_indexed_array_with_values_of_unique_column()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'username VARCHAR(20)';
    });

    $this->insertMany('users',[
      ['username' => 'foo'],
      ['username' => 'foo'],
      ['username' => 'foo2'],
      ['username' => 'foo3'],
      ['username' => 'foo4'],
      ['username' => 'foo4'],
      ['username' => 'foo4'],
    ]);

    $this->assertSame(
      ['foo2', 'foo', 'foo4', 'foo3'],
      self::$pgsql->getColumnValues('users', 'username')
    );

    $this->assertSame(
      ['foo2', 'foo', 'foo4', 'foo3'],
      self::$pgsql->getColumnValues([
        'table' => ['users'],
        'fields' => ['username']
      ])
    );

    $this->assertSame(
      ['foo'],
      self::$pgsql->getColumnValues('users', 'username', ['username' => 'foo'])
    );

    $this->assertSame(
      ['foo'],
      self::$pgsql->getColumnValues('users', 'DISTINCT username', ['username' => 'foo'])
    );

    $this->assertSame(
      ['foo4', 'foo3', 'foo2', 'foo'],
      self::$pgsql->getColumnValues('users', 'username', [], ['username' => 'DESC'])
    );

    $this->assertSame(
      ['foo'],
      self::$pgsql->getColumnValues('users', 'username', [], [], 1, 1)
    );

    $this->assertEmpty(
      self::$pgsql->getColumnValues('users', 'username', ['username' => 'bar'])
    );

    $this->assertEmpty(
      self::$pgsql->getColumnValues('users', 'username', [], [], 1, 44)
    );
  }

  /** @test */
  public function getColumnValues_returns_null_when_check_method_returns_false()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull(self::$pgsql->getColumnValues('users', 'username'));
  }

  /** @test */
  public function insert_method_inserts_values_in_the_given_table_and_returns_affected_rows()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id bytea PRIMARY KEY, 
              name VARCHAR(20), 
              email VARCHAR(20) UNIQUE';
    });

    $this->assertSame(
      2,
      self::$pgsql->insert('users', [
        ['name' => 'John', 'email' => 'john@mail.com'],
        ['name' => 'Smith', 'email' => 'smith@mail.com'],
        ['name' => 'Smith', 'email' => 'smith@mail.com'],
      ], true)
    );


    $this->assertDatabaseHas('users', 'email', 'john@mail.com');
    $this->assertDatabaseHas('users', 'email', 'smith@mail.com');

    self::$pgsql->rawQuery('DELETE FROM users');

    $this->assertSame(
      2,
      self::$pgsql->insert([
        'tables' => ['users'],
        'values' => [
          ['name' => 'John', 'email' => 'john@mail.com'],
          ['name' => 'Smith', 'email' => 'smith@mail.com'],
          ['name' => 'Smith', 'email' => 'smith@mail.com']
        ],
        'ignore' => true
      ])
    );

    $this->assertDatabaseHas('users', 'email', 'john@mail.com');
    $this->assertDatabaseHas('users', 'email', 'smith@mail.com');
  }

  /** @test */
  public function insert_method_throws_an_exception_when_table_name_is_empty()
  {
    $this->expectException(\Exception::class);

    self::$pgsql->insert('');
  }

  /** @test */
  public function insertUpdate_method_inserts_rows_in_the_given_table_if_not_exists_otherwise_update()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id bytea PRIMARY KEY,
              name VARCHAR(20), 
              email VARCHAR(20) UNIQUE';
    });


    $this->assertSame(
      3,
      self::$pgsql->insertUpdate('users', [
        ['name' => 'John', 'email' => 'john@mail.com'],
        ['name' => 'Smith', 'email' => 'smith@mail.com'],
        ['name' => 'Smith2', 'email' => 'smith@mail.com']
      ])
    );

    $this->assertDatabaseHas('users', 'email', 'smith@mail.com');
    $this->assertDatabaseHas('users', 'name', 'Smith2');
    $this->assertDatabaseDoesNotHave('users', 'name', 'Smith');

    self::$pgsql->query("DELETE FROM users");

    $this->assertSame(
      3,
      self::$pgsql->insertUpdate([
        'tables' => ['users'],
        'values' => [
          ['name' => 'John', 'email' => 'john@mail.com'],
          ['name' => 'Smith', 'email' => 'smith@mail.com'],
          ['name' => 'Smith2', 'email' => 'smith@mail.com']
        ]
      ])
    );

    $this->assertDatabaseHas('users', 'email', 'smith@mail.com');
    $this->assertDatabaseHas('users', 'name', 'Smith2');
    $this->assertDatabaseDoesNotHave('users', 'name', 'Smith');
  }

  /** @test */
  public function update_method_updates_rows_in_the_given_table()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY,
              username VARCHAR(20) UNIQUE,
              name VARCHAR(20)';
    });

    $this->insertOne('users', ['username' => 'jdoe', 'name' => 'John']);

    $this->assertSame(
      1,
      self::$pgsql->update('users', ['name' => 'Smith'], ['username' => 'jdoe'])
    );

    $this->assertDatabaseHas('users', 'name', 'Smith');
    $this->assertDatabaseDoesNotHave('users', 'name', 'John');

    $this->assertSame(
      1,
      self::$pgsql->update('users', ['name' => 'Smith'], ['username' => 'jdoe'], true)
    );

    self::$pgsql->rawQuery('DELETE FROM users');

    $this->insertOne('users', ['username' => 'jdoe', 'name' => 'John']);

    $this->assertSame(
      1,
      self::$pgsql->update([
        'tables' => ['users'],
        'where'  => ['username' => 'jdoe'],
        'fields' => ['name'=> 'Smith']
      ])
    );

    $this->assertSame(
      1,
      self::$pgsql->update([
        'tables' => ['users'],
        'where'  => ['username' => 'jdoe'],
        'fields' => ['name'=> 'Smith'],
        'ignore' => true
      ])
    );

    $this->assertDatabaseHas('users', 'name', 'Smith');
    $this->assertDatabaseDoesNotHave('users', 'name', 'John2');
  }

  /** @test */
  public function delete_method_deletes_rows_from_the_given_table()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'username VARCHAR(20) UNIQUE, 
              name VARCHAR(20)';
    });

    $this->insertOne('users', ['username' => 'jdoe', 'name' => 'John']);

    $this->assertSame(
      1,
      self::$pgsql->delete('users', ['username' => 'jdoe'])
    );

    $this->assertDatabaseDoesNotHave('users', 'name', 'John');

    $this->assertSame(
      0,
      self::$pgsql->delete('users', ['username' => 'jdoe'])
    );

    $this->insertOne('users', ['username' => 'sdoe', 'name' => 'Smith']);

    $this->assertSame(
      1,
      self::$pgsql->delete([
        'tables' => ['users'],
        'where'  => ['username' => 'sdoe']
      ])
    );

    $this->assertDatabaseDoesNotHave('users', 'name', 'Smith');
  }

  /** @test */
  public function fetch_method_returns_the_first_result_of_the_query_as_indexed_array_and_false_if_no_results()
  {
    $this->createTable('users', function () {
      return 'name VARCHAR(255), email VARCHAR(255)';
    });

    $this->assertFalse(
      self::$pgsql->fetch('SELECT * FROM users')
    );

    $this->insertMany('users', [
      ['name' => 'John', 'email' => 'john@mail.com'],
      ['name' => 'Smith', 'email' => 'smith@mail.com'],
    ]);

    $this->assertSame(
      ['name' => 'John', 'John', 'email' => 'john@mail.com', 'john@mail.com'],
      self::$pgsql->fetch('SELECT * FROM users')
    );

    $this->assertSame(
      ['email' => 'smith@mail.com', 'smith@mail.com'],
      self::$pgsql->fetch('SELECT email FROM users WHERE name = ?', 'Smith')
    );
  }

  /** @test */
  public function fetchAll_method_returns_an_array_of_indexed_arrays_for_all_query_result_and_empty_array_if_no_results()
  {
    $this->createTable('users', function () {
      return 'name VARCHAR(255), email VARCHAR(255)';
    });

    $this->assertSame(
      [],
      self::$pgsql->fetchAll('SELECT * FROM users')
    );

    $this->insertMany('users', [
      ['name' => 'John', 'email' => 'john@mail.com'],
      ['name' => 'Smith', 'email' => 'smith@mail.com'],
    ]);

    $this->assertSame(
      [
        ['name' => 'Smith', 'Smith', 'email' => 'smith@mail.com', 'smith@mail.com'],
        ['name' => 'John', 'John', 'email' => 'john@mail.com', 'john@mail.com']
      ],
      self::$pgsql->fetchAll('SELECT * FROM users ORDER BY name DESC')
    );

    $this->assertSame(
      [
        ['name' => 'Smith', 'Smith']
      ],
      self::$pgsql->fetchAll("SELECT name FROM users WHERE email = 'smith@mail.com'")
    );
  }

  /** @test */
  public function fetchAll_method_returns_false_when_query_method_returns_false()
  {
    $pgsql = \Mockery::mock(Pgsql::class)->makePartial();

    $pgsql->shouldReceive('query')
      ->once()
      ->andReturnFalse();

    $this->assertFalse(
      $pgsql->fetchAll('SELECT * FROM users')
    );
  }

  /** @test */
  public function fetchColumn_method_returns_a_single_column_from_the_next_row_of_result_set()
  {
    $this->createTable('users', function () {
      return 'name VARCHAR(255), email VARCHAR(255)';
    });

    $this->assertFalse(
      self::$pgsql->fetchColumn('SELECT * FROM users')
    );

    $this->insertMany('users', [
      ['name' => 'John', 'email'=> 'john@mail.com'],
      ['name' => 'Smith', 'email' => 'smith@mail.com']
    ]);

    $this->assertSame(
      'John',
      self::$pgsql->fetchColumn('SELECT * FROM users')
    );

    $this->assertSame(
      'john@mail.com',
      self::$pgsql->fetchColumn('SELECT * FROM users', 1)
    );

    $this->assertSame(
      'smith@mail.com',
      self::$pgsql->fetchColumn('SELECT * FROM users WHERE name = ?', 1, 'Smith')
    );
  }

  /** @test */
  public function fetchObject_method_returns_the_first_result_from_query_as_object_and_false_if_no_results()
  {
     $this->createTable('users', function () {
      return 'name VARCHAR(255), email VARCHAR(255)';
    });

    $this->assertFalse(
      self::$pgsql->fetchObject('SELECT * FROM users')
    );

    $this->insertMany('users', [
      ['name' => 'John', 'email'=> 'john@mail.com'],
      ['name' => 'Smith', 'email' => 'smith@mail.com']
    ]);

    $result = self::$pgsql->fetchObject('SELECT * FROM users');

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('name', $result);
    $this->assertObjectHasAttribute('email', $result);
    $this->assertSame('John', $result->name);
    $this->assertSame('john@mail.com', $result->email);

    $result = self::$pgsql->fetchObject('SELECT * FROM users ORDER BY name DESC');

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('name', $result);
    $this->assertObjectHasAttribute('email', $result);
    $this->assertSame('Smith', $result->name);
    $this->assertSame('smith@mail.com', $result->email);
  }

  /** @test */
  public function getRows_method_returns_an_array_of_indexed_arrays_for_every_row_as_a_query_result()
  {
    $this->createTable('users', function () {
      return 'username VARCHAR(255)';
    });

    $this->assertEmpty(self::$pgsql->getRows("SELECT * FROM users"));

    $this->insertMany('users', [
      ['username' => 'john_doe'],
      ['username' => 'john_doe_2'],
    ]);

    $expected = [
      ['username' => 'john_doe'],
      ['username' => 'john_doe_2'],
    ];

    $this->assertSame($expected, self::$pgsql->getRows("SELECT * FROM users"));
  }

  /** @test */
  public function getRows_method_returns_null_when_query_method_returns_false()
  {
    $pgsql = \Mockery::mock(Pgsql::class)->makePartial();

    $pgsql->shouldReceive('query')
      ->once()
      ->andReturnFalse();

    $this->assertNull($pgsql->getRows('SELECT * FROM users'));
  }

  /** @test */
  public function getRow_method_returns_the_first_row_resulting_from_a_query_as_array_indexed_with_field_name()
  {
    $this->createTable('users', function () {
      return 'username VARCHAR(255)';
    });

    $this->assertEmpty(self::$pgsql->getRow("SELECT * FROM users"));

    $this->insertMany('users', [
      ['username' => 'john_doe'],
      ['username' => 'john_doe_2'],
    ]);

    $this->assertSame(
      ['username' => 'john_doe'],
      self::$pgsql->getRow("SELECT * FROM users")
    );
  }

  /** @test */
  public function getRow_method_returns_null_when_query_method_returns_false()
  {
    $pgsql = \Mockery::mock(Pgsql::class)->makePartial();

    $pgsql->shouldReceive('query')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $pgsql->getRow('SELECT * FROM users')
    );
  }

  /** @test */
  public function getIrow_method_returns_the_first_raw_resulting_from_a_query_as_numeric_indexed_array()
  {
    $this->createTable('users', function () {
      return 'username VARCHAR(255)';
    });

    $this->assertEmpty(self::$pgsql->getIrow("SELECT * FROM users"));

    $this->insertMany('users', [
      ['username' => 'john_doe'],
      ['username' => 'john_doe_2']
    ]);

    $this->assertSame(
      ['john_doe'],
      self::$pgsql->getIrow("SELECT * FROM users")
    );
  }

  /** @test */
  public function getIrow_method_returns_null_when_query_method_returns_false()
  {
    $pgsql = \Mockery::mock(Pgsql::class)->makePartial();

    $pgsql->shouldReceive('query')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $pgsql->getIrow('SELECT * FROM users')
    );
  }

  /** @test */
  public function getIrows_method_returns_all_rows_resulting_from_a_query_as_numeric_indexed_array()
  {
    $this->createTable('users', function () {
      return 'username VARCHAR(255)';
    });

    $this->assertEmpty(self::$pgsql->getIrows("SELECT * FROM users"));

    $this->insertMany('users', [
      ['username' => 'john_doe'],
      ['username' => 'john_doe_2']
    ]);

    $expected = [
      ['john_doe'],
      ['john_doe_2'],
    ];

    $this->assertSame($expected, self::$pgsql->getIrows("SELECT * FROM users"));
  }

  /** @test */
  public function getIrows_method_returns_null_when_query_method_returns_false()
  {
    $pgsql = \Mockery::mock(Pgsql::class)->makePartial();

    $pgsql->shouldReceive('query')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $pgsql->getIrows('SELECT * FROM users')
    );
  }

  /** @test */
  public function getByColumns_method_returns_an_indexed_array_by_the_searched_field()
  {
    $this->createTable('users', function () {
      return 'email VARCHAR(255), 
              username VARCHAR(255), 
              name VARCHAR(255)';
    });

    $this->insertMany('users', [
      [
        'name'  => 'john',
        'email' => 'jdoe@example.com',
        'username' => 'jdoe',
      ],
      [
        'name'  => 'smith',
        'email' => 'sdoe@example.com',
        'username' => 'sdoe',
      ],
    ]);

    $result   = self::$pgsql->getByColumns('SELECT name, email, username FROM users');
    $expected = [
      'name'     => ['john', 'smith'],
      'email'    => ['jdoe@example.com', 'sdoe@example.com'],
      'username' => ['jdoe', 'sdoe']
    ];

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getObject_method_returns_the_first_row_resulting_from_a_query_as_object()
  {
    $this->createTable('users', function () {
      return 'username VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'john_doe'],
      ['username' => 'john_doe_2']
    ]);

    $result = self::$pgsql->getObject("SELECT * FROM users");

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('username', $result);
    $this->assertSame('john_doe', $result->username);
  }

  /** @test */
  public function getObjects_method_returns_an_array_of_objects_resulting_from_a_query()
  {
    $this->createTable('users', function () {
      return 'username VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'john_doe'],
      ['username' => 'john_doe_2']
    ]);

    $result = self::$pgsql->getObjects("SELECT * FROM users");

    $this->assertIsArray($result);
    $this->assertCount(2, $result);
    $this->assertIsObject($result[0]);
    $this->assertIsObject($result[1]);
    $this->assertSame('john_doe', $result[0]->username);
    $this->assertSame('john_doe_2', $result[1]->username);
  }

  /** @test */
  public function getForeignKeys_method_returns_an_array_of_tables_and_fields_related_to_the_given_foreign_key()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY,
              username VARCHAR(255) UNIQUE,
              created_at DATE DEFAULT NULL,
              role_id INT';
    });

    $this->createTable('roles', function () {
      return 'id serial PRIMARY KEY,
              name VARCHAR(255)';
    });

    self::$pgsql->rawquery(
      'ALTER TABLE users ADD CONSTRAINT user_role_id
       FOREIGN KEY (role_id) REFERENCES roles (id) 
       ON UPDATE CASCADE ON DELETE RESTRICT'
    );

    $this->assertSame(
      ['users' => ['role_id']],
      self::$pgsql->getForeignKeys('id', 'roles')
    );

    $this->assertSame(
      [],
      self::$pgsql->getForeignKeys('id', 'roles', 'another_db')
    );

    $this->assertSame(
      [],
      self::$pgsql->getForeignKeys('role_id', 'users')
    );
  }

  /** @test */
  public function hasIdIncrement_method_returns_true_if_the_given_table_has_auto_increment_fields()
  {
    $this->setCacheExpectations();

    $this->createTable('roles', function () {
      return 'username VARCHAR(255)';
    });

    $this->assertFalse(
      self::$pgsql->hasIdIncrement('roles')
    );

    $this->createTable('users', function () {
      return 'id bigserial PRIMARY KEY';
    });

    $this->assertTrue(
      self::$pgsql->hasIdIncrement('users')
    );
  }

  /** @test */
  public function fmodelize_method_returns_fields_structure_for_the_given_table()
  {
    $this->createTable('users', function () {
      return 'id serial PRIMARY KEY,
              username VARCHAR(255) UNIQUE DEFAULT NULL';
    });

    $this->setCacheExpectations();

    $result   = self::$pgsql->fmodelize('users');
    $expected = [
      'id' => [
        'position'  => 1,
        'type'      => 'integer',
        'udt_name' => 'int4',
        'null'      => 0,
        'key'       => 'PRI',
        'extra'     => 'auto_increment',
        'signed'    => true,
        'virtual'   => false,
        'generation'  => null,
        'default' => "nextval('users_id_seq'::regclass)",
        'maxlength' => 32,
        'decimals' => 0,
        'name' => 'id',
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
        ]
      ],
      'username' => [
        'position'  => 2,
        'type'      => 'character varying',
        'udt_name' => 'varchar',
        'null'      => 1,
        'key'       => 'UNI',
        'extra'     => '',
        'signed'    => false,
        'virtual'   => false,
        'generation'  => null,
        'default'    => 'NULL::character varying',
        'maxlength' => 255,
        'name' => 'username',
        'keys' => [
          'users_username_key' => [
            'columns' => ['username'],
            'ref_db' => null,
            'ref_table' => null,
            'ref_column' => null,
            'constraint' => null,
            'update' => null,
            'delete' => null,
            'unique' => 1
          ]
        ]
      ]
    ];

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function fmodelize_method_returns_null_when_modelize_returns_null()
  {
    $pgsql = \Mockery::mock(Pgsql::class)->makePartial();

    $pgsql->shouldReceive('modelize')
      ->once()
      ->andReturnNull();

    $this->assertNull(
      $pgsql->fmodelize('users')
    );
  }

  /** @test */
  public function fetchAllResults_method_uses_the_overridden_fetchAll_method_on_the_class_when_exists()
  {
    $class = new class extends \PDOStatement {
      public function _fetchAll()
      {
        return 'From the overridden method!';
      }
    };

    $this->assertSame(
      'From the overridden method!',
      self::$pgsql->fetchAllResults($class)
    );
  }

  /** @test */
  public function getLastCfg_method_returns_the_last_config_for_the_connection()
  {
    $this->assertSame(
      $this->getNonPublicProperty('last_cfg'),
      self::$pgsql->getLastCfg()
    );
  }

  /** @test */
  public function renameTable_method_renames_the_given_table_to_the_new_given_name()
  {
    $this->createTable('users', function () {
      return 'id INT';
    });

    $this->assertTrue(
      self::$pgsql->renameTable('users', 'users2')
    );

    $tables = self::$pgsql->getTables();

    $this->assertTrue(in_array('users2', $tables));
    $this->assertTrue(!in_array('users', $tables));
  }

  /** @test */
  public function renameTable_method_returns_false_when_check_method_returns_false()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertFalse(
      self::$pgsql->renameTable('users', 'users2')
    );
  }

  /** @test */
  public function renameTable_method_returns_false_when_the_given_table_names_are_not_valid()
  {
    $this->assertFalse(
      self::$pgsql->renameTable('users**', 'users2')
    );

    $this->assertFalse(
      self::$pgsql->renameTable('users', 'users2**')
    );

    $this->assertFalse(
      self::$pgsql->renameTable('users&&', 'users2**')
    );
  }

  /** @test */
  public function getTableComment_method_returns_the_comment_for_the_given_table()
  {
    $this->createTable('users', function () {
      return 'id serial';
    });

    self::$pgsql->rawQuery("COMMENT ON TABLE users IS 'Hello world!'");

    $this->assertSame(
      'Hello world!',
      self::$pgsql->getTableComment('users')
    );
  }

  /** @test */
  public function getTableComment_method_returns_empty_string_if_the_given_table_has_no_comment()
  {
    $this->createTable('users', function () {
      return 'id serial';
    });

    $this->assertSame(
      '',
      self::$pgsql->getTableComment('users')
    );
  }

  /** @test */
  public function createColumn_method_creates_the_given_column_for_the_given_table()
  {
    $this->createTable('users', function () {
      return 'id INT';
    });

    $this->assertTrue(
      self::$pgsql->createColumn('users', 'username', [
        'type' => 'varchar',
        'null' => false,
        'after' => 'id',
        'maxlength' => 255
      ])
    );

    $this->assertTrue(
      self::$pgsql->createColumn('users', 'created_at', [
        'type' => 'timestamp',
        'null' => false,
        'after' => 'id',
        'default' => 'CURRENT_TIMESTAMP',
      ])
    );

    $this->assertTrue(
      self::$pgsql->createColumn('users', 'balance', [
        'type' => 'decimal',
        'null' => false,
        'default' => 0,
        'maxlength' => 10,
        'decimals' => 2,
        'signed' => true
      ])
    );

    $structure = $this->getTableStructure('users');

    $this->assertArrayHasKey('username', $structure = $structure['fields']);
    $this->assertSame([
      'position' => 2,
      'type' => 'character varying',
      'udt_name' => 'varchar',
      'null' => 0,
      'key' => null,
      'extra' => '',
      'signed' => false,
      'virtual' => false,
      'generation' => null,
      'maxlength' => 255
    ], $structure['username']);

    $this->assertArrayHasKey('created_at', $structure);
    $this->assertSame([
      'position' => 3,
      'type' => 'timestamp without time zone',
      'udt_name' => 'timestamp',
      'null' => 0,
      'key' => null,
      'extra' => '',
      'signed' => false,
      'virtual' => false,
      'generation' => null,
      'default' => 'CURRENT_TIMESTAMP'
    ], $structure['created_at']);

    $this->assertArrayHasKey('balance', $structure);
    $this->assertSame([
      'position' => 4,
      'type' => 'numeric',
      'udt_name' => 'numeric',
      'null' => 0,
      'key' => null,
      'extra' => '',
      'signed' => true,
      'virtual' => false,
      'generation' => null,
      'default' => 0,
      'maxlength' => 10,
      'decimals' => 2
    ], $structure['balance']);
  }

  /** @test */
  public function createColumn_method_returns_false_when_the_given_column_is_not_a_valid_name()
  {
    $this->assertFalse(
      self::$pgsql->createColumn('users', 'username**', [])
    );
  }

  /** @test */
  public function createColumn_method_throws_an_exception_when_a_field_type_is_not_valid()
  {
    $this->expectException(\Exception::class);

    self::$pgsql->createColumn('users', 'balance', ['type' => 'number']);
  }

  /** @test */
  public function dropColumn_method_drops_the_given_column_for_the_given_table()
  {
    $this->createTable('users', function () {
      return 'id INT, username VARCHAR(20), name VARCHAR(2)';
    });

    $this->assertTrue(
      self::$pgsql->dropColumn('users', 'username')
    );

    $this->assertTrue(
      self::$pgsql->dropColumn('users', 'name')
    );

    $structure = $this->getTableStructure('users')['fields'];

    $this->assertArrayNotHasKey('username', $structure);
    $this->assertArrayNotHasKey('name', $structure);
  }

  /** @test */
  public function dropColumn_method_returns_false_when_the_given_column_is_a_not_valid_name()
  {
    $this->assertFalse(
      self::$pgsql->dropColumn('users', 'id**')
    );
  }

  /** @test */
  public function getColumnDefinitionStatement_method_returns_sql_statement_of_column_definition()
  {
    $method = $this->getNonPublicMethod('getColumnDefinitionStatement');

    $cols = [
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
    ];

    $expected = [
      'id' => 'id bytea NOT NULL',
      'username' => 'username varchar(255) NOT NULL',
      'profile_id' => "profile_id bigint NOT NULL",
      'user_role' => "user_role role NOT NULL DEFAULT 'user'",
      'user_permission' => 'user_permission permission DEFAULT NULL',
      'balance_before' => 'balance_before real NOT NULL DEFAULT 0',
      'balance' => 'balance decimal(10,2) DEFAULT NULL',
      'created_at' => 'created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP',
      'updated_at' => 'updated_at time NOT NULL DEFAULT CURRENT_TIME'
    ];

    foreach ($cols as $col_name => $col) {
      $this->assertSame(
        $expected[$col_name],
        trim($method->invoke(self::$pgsql, $col_name, $col))
      );
    }
  }

  /** @test */
  public function getColumnDefinitionStatement_method_throws_an_exception_when_column_type_is_not_provided()
  {
    $this->expectException(\Exception::class);

    $this->getNonPublicMethod('getColumnDefinitionStatement')
      ->invoke(self::$pgsql, 'username', ['maxlength' => 32]);
  }

  /** @test */
  public function getColumnDefinitionStatement_method_throws_an_exception_when_a_field_type_is_not_valid()
  {
    $this->expectException(\Exception::class);

    $this->getNonPublicMethod('getColumnDefinitionStatement')
      ->invoke(self::$pgsql, 'balance', ['type' => 'number']);
  }

  /** @test */
  public function getAlterTable_method_returns_sql_string_for_alter_statement()
  {
    $cfg = [
      'fields' => [
        'id' => [
          'type' => 'binary',
          'maxlength' => 32
        ],
        'role' => [
          'type' => 'USER-DEFINED',
          'extra' => "'super_admin','admin','user'",
          'default' => 'user'
        ],
        'name' => [
          'type' => 'varchar',
          'maxlength' => 255,
          'alter_type' => 'modify',
          'null' => true
        ],
        'permission' => [
          'type' => 'USER-DEFINED',
          'extra' => "'read','write'",
          'default' => 'read'
        ],
        'balance' => [
          'type' => 'decimal',
          'maxlength' => 10,
          'decimals' => 2,
          'null' => true,
          'default' => 'NULL',
          'alter_type' => 'modify'
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
        'role_id' => [
          'alter_type' => 'drop'
        ]
      ]
    ];

    $expected = <<<SQL
ALTER TABLE users
ADD COLUMN   id bytea NOT NULL,
ADD COLUMN   role role NOT NULL DEFAULT 'user',
ALTER COLUMN name TYPE varchar(255),
ADD COLUMN   permission permission NOT NULL DEFAULT 'read',
ALTER COLUMN balance TYPE decimal(10,2),
ALTER COLUMN balance SET DEFAULT NULL,
ADD COLUMN   balance_before real NOT NULL DEFAULT 0,
ADD COLUMN   created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
DROP COLUMN role_id
SQL;


    $this->assertSame(
      $expected, self::$pgsql->getAlterTable('users', $cfg)
    );
  }

  /** @test */
  public function getAlterTable_method_returns_empty_string_when_the_given_table_name_is_not_valid()
  {
    $this->assertSame('', self::$pgsql->getAlterTable('user**', ['fields' => ['a' => 'b']]));
  }

  /** @test */
  public function getAlterTable_method_returns_empty_string_when_check_method_returns_false()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertSame('', self::$pgsql->getAlterTable('users', ['fields' => ['a' => 'b']]));
  }

  /** @test */
  public function alter_method_alters_the_given_cfg_for_the_given_table()
  {
    $this->createTable('users', function () {
      return 'balance int NOT NULL,
              role_id INT DEFAULT 0,
              name TEXT';
    });

    $cfg = [
      'fields' => [
        'id' => [
          'type' => 'binary',
          'maxlength' => 32
        ],
        'role' => [
          'type' => 'USER-DEFINED',
          'extra' => "'super_admin','admin','user'",
          'default' => 'user'
        ],
        'name' => [
          'type' => 'varchar',
          'maxlength' => 255,
          'alter_type' => 'modify',
          'null' => true
        ],
        'permission' => [
          'type' => 'USER-DEFINED',
          'extra' => "'read','write'",
          'default' => 'read'
        ],
        'balance' => [
          'type' => 'decimal',
          'maxlength' => 10,
          'decimals' => 2,
          'null' => true,
          'default' => 'NULL',
          'alter_type' => 'modify'
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
        'role_id' => [
          'alter_type' => 'drop'
        ]
      ]
    ];

    self::$pgsql->rawQuery('DROP TYPE IF EXISTS role');
    self::$pgsql->rawQuery('DROP TYPE IF EXISTS permission');
    self::$pgsql->rawQuery("CREATE TYPE role AS ENUM ('super_admin', 'admin', 'user')");
    self::$pgsql->rawQuery("CREATE TYPE permission AS ENUM ('read', 'write')");

    $this->assertSame(
      1, self::$pgsql->alter('users', $cfg)
    );

    $structure = $this->getTableStructure('users')['fields'];

    $this->assertArrayHasKey('id', $structure);
    $this->assertArrayHasKey('balance', $structure);
    $this->assertArrayHasKey('role', $structure);
    $this->assertArrayHasKey('name', $structure);
    $this->assertArrayHasKey('permission', $structure);
    $this->assertArrayHasKey('balance_before', $structure);
    $this->assertArrayHasKey('created_at', $structure);
    $this->assertArrayNotHasKey('role_id', $structure);

    $this->assertSame('numeric', $structure['balance']['type']);
    $this->assertSame(10, $structure['balance']['maxlength']);
    $this->assertSame(2, $structure['balance']['decimals']);
    $this->assertSame(1, $structure['balance']['position']);

    $this->assertSame('character varying', $structure['name']['type']);
    $this->assertSame(255, $structure['name']['maxlength']);
    $this->assertSame(1, $structure['name']['null']);
    $this->assertSame(3, $structure['name']['position']);

    $this->assertSame('binary', $structure['id']['type']);
    $this->assertSame(16, $structure['id']['maxlength']);
    $this->assertSame(0, $structure['id']['null']);
    $this->assertSame(4, $structure['id']['position']);

    $this->assertSame('USER-DEFINED', $structure['role']['type']);
    $this->assertSame('role', $structure['role']['udt_name']);
    $this->assertSame("'user'::role", $structure['role']['default']);
    $this->assertSame(5, $structure['role']['position']);

    $this->assertSame('USER-DEFINED', $structure['permission']['type']);
    $this->assertSame('permission', $structure['permission']['udt_name']);
    $this->assertSame("'read'::permission", $structure['permission']['default']);
    $this->assertSame(0, $structure['permission']['null']);
    $this->assertSame(6, $structure['permission']['position']);

    $this->assertSame('real', $structure['balance_before']['type']);
    $this->assertSame(7, $structure['balance_before']['position']);
    $this->assertSame(0, $structure['balance_before']['null']);
    $this->assertSame(0, $structure['balance_before']['default']);
    $this->assertSame(true, $structure['balance_before']['signed']);

    $this->assertSame('timestamp without time zone', $structure['created_at']['type']);
    $this->assertSame('CURRENT_TIMESTAMP', $structure['created_at']['default']);
    $this->assertSame(8, $structure['created_at']['position']);
    $this->assertSame(0, $structure['created_at']['null']);
  }
}