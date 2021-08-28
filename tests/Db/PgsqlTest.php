<?php

namespace Db;

use bbn\Cache;
use bbn\Db2\Enums\Errors;
use bbn\Db2\Languages\Pgsql;
use bbn\Str;
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
      if (empty($key)) {
        continue;
      }
      if (empty($value)) {
        $value = '';
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
//    $this->dropAllTables();
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

    $obj = $pgsql ?? $this->pgsql;

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

    if ($tables = $obj->getTables()) {
      foreach ($tables as $table) {
        $this->dropTableIfExist($table, $obj);
      }
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
  users.id = ?
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

  /** @test */
  public function dropDatabase_method_throws_an_exception_when_the_given_name_is_not_valid()
  {
    $this->expectException(\Exception::class);

    $this->pgsql->dropDatabase('db***');
  }

  /** @test */
  public function dropDatabase_method_returns_false_if_check_method_returns_false()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertFalse(
      $this->pgsql->dropDatabase($this->getDbConfig()['db'])
    );
  }

  /** @test */
  public function createUser_method_creates_a_new_database_user()
  {
    try {
      $this->pgsql->deleteUser('new_user');
    } catch (\Exception $e) {

    }

    $this->createTable('users', function () {
        return 'id INT PRIMARY KEY';
    });

    $this->assertTrue(
      $this->pgsql->createUser('new_user', '123456')
    );

    // Connect with the new user
    $cfg = $this->getDbConfig();
    $cfg['user'] = 'new_user';
    $cfg['pass'] = '123456';

    $pgsql = new Pgsql($cfg);

    $this->assertSame(
      ['users'],
      $pgsql->getTables(),
    );

    $this->pgsql->deleteUser('new_user');
  }

  /** @test */
  public function createUser_method_returns_false_when_the_given_user_is_not_a_valid_name()
  {
    $this->assertFalse(
      $this->pgsql->createUser('user***', '123')
    );
  }

  public function createUser_method_returns_false_when_the_given_password_is_not_valid()
  {
    $this->assertFalse(
      $this->pgsql->createUser('user', "123'")
    );
  }

  /**
   * @test
   * @depends createUser_method_creates_a_new_database_user
   */
  public function deleteUser_method_deletes_the_given_user_from_database()
  {
    $this->pgsql->createUser('new_user', '12345');

    $result = $this->pgsql->deleteUser('new_user');

    $this->assertTrue($result);
  }

  /** @test */
  public function deleteUser_method_returns_false_when_the_given_user_is_not_valid_name()
  {
    $this->assertFalse(
      $this->pgsql->deleteUser('user***')
    );
  }

  /** @test */
  public function getUsers_method_returns_all_current_users()
  {
    $user = $this->getDbConfig()['user'];

    $this->assertTrue(
      in_array($user, $this->pgsql->getUsers())
    );

    $this->assertTrue(
      in_array($user, $this->pgsql->getUsers($user))
    );

    $this->assertEmpty(
      $this->pgsql->getUsers('foo')
    );
  }

  /** @test */
  public function getUsers_method_returns_null_when_check_method_returns_false()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull(
      $this->pgsql->getUsers()
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

    $total_size = $this->pgsql->dbSize();
    $index_size = $this->pgsql->dbSize('', 'index');
    $data_size = $this->pgsql->dbSize('', 'data');

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

    $cfg = $this->getDbConfig();
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

    $this->assertSame(0, $this->pgsql->dbSize());

    $total_size = $this->pgsql->dbSize($this->db2);
    $index_size = $this->pgsql->dbSize($this->db2, 'index');
    $data_size = $this->pgsql->dbSize($this->db2, 'data');

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
    $this->assertSame(0, $this->pgsql->dbSize());
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
    $result   = $this->pgsql->getTables();

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

    $total_size = $this->pgsql->tableSize('users');
    $index_size = $this->pgsql->tableSize('users', 'index');
    $data_size  = $this->pgsql->tableSize('users', 'data');

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

    $this->pgsql->tableSize('roles');
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
      'tableowner' => $this->getDbConfig()['user'],
      'tablespace' => null,
      'hasindexes' => true,
      'hasrules' => false,
      'hastriggers' => false,
      'rowsecurity' => false
    ];

    $expected_comments = [
      'schemaname' => 'public',
      'tablename' => 'comments',
      'tableowner' => $this->getDbConfig()['user'],
      'tablespace' => null,
      'hasindexes' => false,
      'hasrules' => false,
      'hastriggers' => false,
      'rowsecurity' => false
    ];

    $this->assertSame($expected_comments, $this->pgsql->status('comments'));
    $this->assertSame($expected_posts, $this->pgsql->status('posts'));
  }

  /** @test */
  public function status_method_returns_status_of_the_given_table_for_the_given_database()
  {
    $this->createDatabase($this->db2);

    $cfg       = $this->getDbConfig();
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
      $this->pgsql->status()
    );

    $result   = $this->pgsql->status('', $this->db2);
    $expected = [
      'schemaname' => 'public',
      'tablename' => 'posts',
      'tableowner' => $this->getDbConfig()['user'],
      'tablespace' => null,
      'hasindexes' => true,
      'hasrules' => false,
      'hastriggers' => false,
      'rowsecurity' => false
    ];

    $this->assertSame($expected, $result);
    $this->assertNull($this->pgsql->status());
  }

  /** @test */
  public function getUid_method_returns_a_uuid()
  {
    $result = $this->pgsql->getUid();

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
      $this->pgsql->createTable('users', $columns)
    );
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

  /** @test */
  public function getDatabases_method_returns_null_when_check_method_returns_false()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull(
      $this->pgsql->getDatabases()
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

  /** @test */
  public function getTables_method_returns_null_when_check_method_returns_null()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull(
      $this->pgsql->getTables()
    );
  }

}