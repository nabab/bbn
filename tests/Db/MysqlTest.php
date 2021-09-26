<?php

namespace Db;

use bbn\Cache;
use bbn\Db\Enums\Errors;
use bbn\Db\Languages\Mysql;
use bbn\Db\Query;
use bbn\Str;
use PHPUnit\Framework\TestCase;
use tests\Files;
use tests\Reflectable;
use tests\ReflectionHelpers;

class MysqlTest extends TestCase
{
  use Reflectable, Files;

  protected static Mysql $mysql;

  protected static $real_params_default;

  protected static $connection;

  protected static $cache_mock;

  protected static $default_triggers;

  protected function setUp(): void
  {
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
    $this->cleanTestingDir();
    $this->clearCache();
    $this->dropAllTables();
    self::$mysql->startFancyStuff();
    self::$connection->query('USE ' . self::getDbConfig()['db']);
  }

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

    self::$cache_mock = \Mockery::mock(Cache::class);

    self::createTestingDatabase();

    self::$mysql = new Mysql(self::getDbConfig());

    self::$mysql->startFancyStuff();

    self::$real_params_default = ReflectionHelpers::getNonPublicProperty(
      'last_real_params', self::$mysql
    );

    ReflectionHelpers::setNonPublicPropertyValue(
      'cache_engine', self::$mysql, self::$cache_mock
    );

    self::$default_triggers = ReflectionHelpers::getNonPublicProperty(
      '_triggers', self::$mysql
    );

    ReflectionHelpers::setNonPublicPropertyValue(
      'max_queries', self::$mysql, 60000000
    );
  }

  protected static function createTestingDatabase()
  {
    try {
      $db_cfg = self::getDbConfig();

      self::$connection = new \PDO(
        "mysql:host={$db_cfg['host']};port={$db_cfg['port']};dbname={$db_cfg['db']}",
        $db_cfg['user'],
        $db_cfg['pass'],
        [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8']
      );

      self::$connection->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

      self::$connection->query("SET FOREIGN_KEY_CHECKS=0;");

      self::$connection->query("CREATE DATABASE IF NOT EXISTS {$db_cfg['db']}");

    } catch (\PDOException $e) {
      throw new \Exception("Unable to establish db connection for testing: " . $e->getMessage());
    }
  }

  protected static function getDbConfig()
  {
    return array(
      'engine'        => 'mysql',
      'host'          => getenv('db_host'),
      'user'          => getenv('db_user'),
      'pass'          => getenv('db_pass'),
      'db'            => getenv('db_name'),
      'port'          => getenv('db_port'),
      'cache_length'  => 3000,
      'on_error'      => Errors::E_STOP,
      'force_host'    => true
    );
  }

  public function getInstance()
  {
    return self::$mysql;
  }

  protected function createTable(string $table, callable $callback)
  {
    $this->dropTableIfExists($table);

    $structure = $callback();

    self::$connection->query("CREATE TABLE `$table` (
  $structure
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 collate utf8mb4_unicode_ci");
  }

  protected function dropTableIfExists(string $table)
  {
    self::$connection->query("DROP TABLE IF EXISTS $table");
  }

  protected function dropDatabaseIfExists(string $database)
  {
    self::$connection->query("DROP DATABASE IF EXISTS $database");
  }

  protected function dropAllTables()
  {
    if (!$tables = self::$mysql->getTables()) {
      return;
    }

    foreach ($tables as $table) {
      $this->dropTableIfExists($table);
    }
  }

  protected function clearCache()
  {
    $this->setNonPublicPropertyValue('cache', []);
  }

  protected function setCacheExpectations()
  {
    self::$cache_mock->shouldReceive('get')
      ->andReturnFalse();

    self::$cache_mock->shouldReceive('set')
      ->andReturnTrue();
  }

  protected function getTableStructure(string $table)
  {
    $this->setCacheExpectations();

    return self::$mysql->modelize($table);
  }

  protected function tearDown(): void
  {
    \Mockery::close();
    $this->cleanTestingDir();
  }

  protected function insertOne(string $table, array $params)
  {
    $query = "INSERT INTO `$table` SET ";

    foreach ($params as $column => $value) {
      if (is_null($value)) {
        $query .= "`$column` = NULL, ";
        continue;
      }

      $query .= "`$column` = '$value', ";
    }

    self::$connection->query(rtrim($query, ', '));
  }

  protected function insertMany(string $table, array $params)
  {
    foreach ($params as $fields) {
      if (!is_array($fields)) {
        continue;
      }

      $this->insertOne($table, $fields);
    }
  }

  protected function assertDatabaseHas(string $table, string $field, string $value)
  {
    $record = self::$connection->query(
      "SELECT $field FROM $table WHERE $field = '$value'"
    );

    $this->assertTrue($record->rowCount() > 0);
  }

  protected function assertDatabaseDoesNotHave(string $table, string $field, string $value)
  {
    $record = self::$connection->query(
      "SELECT $field FROM $table WHERE $field = '$value'"
    );

    $this->assertTrue($record->rowCount() === 0);
  }

    /** @test */
  public function isAggregateFunction_method_returns_true_if_the_given_name_is_aggregate_function()
  {
    $this->assertTrue(Mysql::isAggregateFunction('count(*)'));
    $this->assertTrue(Mysql::isAggregateFunction('COUNT(*)'));
    $this->assertTrue(Mysql::isAggregateFunction('COUNT(id)'));
    $this->assertTrue(Mysql::isAggregateFunction('COUNT('));
    $this->assertTrue(Mysql::isAggregateFunction('sum(*)'));
    $this->assertTrue(Mysql::isAggregateFunction('SUM(*)'));
    $this->assertTrue(Mysql::isAggregateFunction('avg(*)'));
    $this->assertTrue(Mysql::isAggregateFunction('AVG(*)'));
    $this->assertTrue(Mysql::isAggregateFunction('min(*)'));
    $this->assertTrue(Mysql::isAggregateFunction('MIN(*)'));
    $this->assertTrue(Mysql::isAggregateFunction('max(*)'));
    $this->assertTrue(Mysql::isAggregateFunction('MAX(*)'));
    $this->assertTrue(Mysql::isAggregateFunction('GROUP_CONCAT('));
    $this->assertTrue(Mysql::isAggregateFunction('group_concat('));

    $this->assertFalse(Mysql::isAggregateFunction('id'));
    $this->assertFalse(Mysql::isAggregateFunction('count'));
    $this->assertFalse(Mysql::isAggregateFunction('min'));
    $this->assertFalse(Mysql::isAggregateFunction('MAX'));
    $this->assertFalse(Mysql::isAggregateFunction('avg'));
  }

 /** @test */
  public function constructor_test()
  {
    $this->assertInstanceOf(\PDO::class, $this->getNonPublicProperty('pdo'));

    $db_cfg = self::getDbConfig();

    $this->assertSame(
      array_merge($db_cfg, [
        'port'      => 3306,
        'code_db'   => $db_cfg['db'],
        'code_host' => "{$db_cfg['user']}@{$db_cfg['host']}",
        'args'      => ["mysql:host={$db_cfg['host']};port={$db_cfg['port']};dbname={$db_cfg['db']}",
          $db_cfg['user'],
          $db_cfg['pass'],
          [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'],
        ]
      ]),
      $this->getNonPublicProperty('cfg')
    );

    $this->assertSame(
      "{$db_cfg['user']}@{$db_cfg['host']}",
      $this->getNonPublicProperty('connection_code')
    );

    $this->assertSame($db_cfg['db'], $this->getNonPublicProperty('current'));
    $this->assertSame($db_cfg['host'], $this->getNonPublicProperty('host'));
    $this->assertSame($db_cfg['user'], $this->getNonPublicProperty('username'));

    $this->assertSame(3000, $this->getNonPublicProperty('cache_renewal'));
    $this->assertSame(Errors::E_STOP, $this->getNonPublicProperty('on_error'));
    $this->assertInstanceOf(Cache::class, $this->getNonPublicProperty('cache_engine'));
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
        'code_db'   => $db_cfg['db'],
        'code_host' => "{$db_cfg['user']}@{$db_cfg['host']}",
        'args'      => ["mysql:host={$db_cfg['host']};port={$db_cfg['port']};dbname={$db_cfg['db']}",
          $db_cfg['user'],
          $db_cfg['pass'],
          [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'],
        ]
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
  public function getSelect_method_generates_a_string_with_select_statement_from_the_given_arguments()
  {
    $cfg = [
      'tables' => [
        'users' => 'users',
        'roles'     => 'roles'
      ],
      'fields' => ['id', 'username', 'role_name', 'cfg'],
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

    $db_name = self::getDbConfig()['db'];

    $result   = self::$mysql->getSelect($cfg);
    $expected = "SELECT LOWER(HEX(`users`.`id`)) AS `id`, `users`.`username`, `roles`.`role_name`, `users`.`cfg`
FROM `$db_name`.`users`, `$db_name`.`roles`
";
    $this->createTable('users', function () {
      return 'id binary NOT NULL PRIMARY KEY,
              username varchar(255) NOT NULL,
              cfg TEXT NOT NULL';
    });

    $this->createTable('roles', function () {
      return 'id binary PRIMARY KEY,
              role_name varchar(20) NOT NULL';
    });

    $this->assertSame($expected, $result);

    try {
      self::$mysql->rawQuery($expected);
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
    $db_name = self::getDbConfig()['db'];

    $result   = self::$mysql->getSelect($cfg);
    $expected = "SELECT COUNT(*)
FROM `$db_name`.`users`
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

    $db_name = self::getDbConfig()['db'];
    $result   = self::$mysql->getSelect($cfg);
    // Looks like it's not correct, is it intended to be like that??
    $expected = "SELECT COUNT(*) FROM ( SELECT 
FROM `$db_name`.`users`, `$db_name`.`roles`
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

    $db_name  = self::getDbConfig()['db'];
    $result   = self::$mysql->getSelect($cfg);
    // Looks like it's not correct, is it intended to be like that??
    $expected = "SELECT COUNT(*) FROM ( SELECT 
FROM `$db_name`.`users`, `$db_name`.`roles`
";

    $this->assertSame($expected, $result);
  }
  
  /** @test */
  public function getSelect_method_sets_an_error_when_available_fields_missing_a_field()
  {
    $this->expectException(\Exception::class);
    self::$mysql->setErrorMode(Errors::E_DIE);

    $cfg = [
      'tables' => ['users'],
      'fields' => ['id'],
      'available_fields' => [
        'username' => 'users'
      ]
    ];

    $this->assertSame('', self::$mysql->getSelect($cfg));
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

    $db_name  = self::getDbConfig()['db'];

    $result   = self::$mysql->getInsert($cfg);
    $expected = "INSERT INTO `$db_name`.`users`
(`id`, `username`)
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

    $db_name  = self::getDbConfig()['db'];

    $result   = self::$mysql->getInsert($cfg);
    $expected = "INSERT IGNORE INTO `$db_name`.`users`
(`id`, `username`)
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

    $this->assertSame('', self::$mysql->getInsert($cfg));
  }

  /** @test */
  public function getInsert_method_sets_an_error_when_a_field_does_not_exist_in_available_fields()
  {
    self::$mysql->setErrorMode(Errors::E_DIE);

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

    self::$mysql->getInsert($cfg);
  }

  /** @test */
  public function getInsert_method_sets_an_error_when_available_table_does_not_exist_in_models()
  {
    self::$mysql->setErrorMode(Errors::E_DIE);

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

    self::$mysql->getInsert($cfg);
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

    $db_name  = self::getDbConfig()['db'];

    $result   = self::$mysql->getUpdate($cfg);
    $expected = "UPDATE `$db_name`.`users` SET `id` = ?,
`username` = ?
";

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

    $db_name  = self::getDbConfig()['db'];

    $result   = self::$mysql->getUpdate($cfg);
    $expected = "UPDATE IGNORE `$db_name`.`users` SET `id` = ?,
`username` = ?
";

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

    $this->assertSame('', self::$mysql->getUpdate($cfg));
  }

  /** @test */
  public function getUpdate_method_sets_an_error_when_a_field_does_not_exist_in_available_fields()
  {
    self::$mysql->setErrorMode(Errors::E_DIE);

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

    self::$mysql->getUpdate($cfg);
  }

  /** @test */
  public function getUpdate_method_sets_an_error_when_available_table_does_not_exist_in_models()
  {
    self::$mysql->setErrorMode(Errors::E_DIE);

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

    self::$mysql->getUpdate($cfg);
  }

  /** @test */
  public function getDelete_method_returns_string_for_delete_statement()
  {
    $cfg = [
      'tables' => ['users']
    ];

    $db_name  = self::getDbConfig()['db'];

    $result   = self::$mysql->getDelete($cfg);
    $expected = "DELETE FROM `$db_name`.`users`
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

    $db_name  = self::getDbConfig()['db'];

    $result   = self::$mysql->getDelete($cfg);
    $expected = "DELETE IGNORE FROM `$db_name`.`users`
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

    $db_name  = self::getDbConfig()['db'];

    $result   = self::$mysql->getDelete($cfg);
    $expected = "DELETE users FROM `$db_name`.`users`
";

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getDelete_method_returns_empty_string_when_tables_provided_are_more_than_one()
  {
    $cfg = [
      'tables' => ['users', 'roles']
    ];

    $this->assertSame('', self::$mysql->getDelete($cfg));
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
    $db_name  = self::getDbConfig()['db'];

    $result   = self::$mysql->getJoin($cfg);
    $expected = " JOIN `$db_name`.`users`
    ON roles.user_id = users.id
  JOIN `$db_name`.`payments`
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
    $db_name  = self::getDbConfig()['db'];

    $result   = self::$mysql->getJoin($cfg);
    $expected = " LEFT JOIN `$db_name`.`users` AS `u`
    ON roles.user_id = users.id";

    $this->assertSame(trim($expected), trim($result));
  }

  /** @test */
  public function getJoin_method_returns_empty_string_when_configurations_are_missing()
  {
    $this->assertSame('', self::$mysql->getJoin([]));

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

    $this->assertSame('', self::$mysql->getJoin($cfg));

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

    $this->assertSame('', self::$mysql->getJoin($cfg));

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

    $this->assertSame('', self::$mysql->getJoin($cfg));
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

    $result   = self::$mysql->getWhere($cfg);
    $expected = "WHERE roles.user_id = users.id
AND roles.email LIKE ?";

    $this->assertSame($expected, trim($result));
  }

  /** @test */
  public function getWhere_method_returns_empty_string_when_some_of_configurations_are_missing()
  {
    $this->assertSame('', self::$mysql->getWhere([]));

    $cfg = [
      'filters' => [
        'conditions' => [[
          'field' => 'roles.user_id',
          'exp' => 'users.id'
        ]],
        'logic' => 'AND'
      ]
    ];

    $this->assertSame('', self::$mysql->getWhere($cfg));

    $cfg = [
      'filters' => [
        'conditions' => [[
          'operator' => '='
        ]],
        'logic' => 'AND'
      ]
    ];

    $this->assertSame('', self::$mysql->getWhere($cfg));
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

    $result   = self::$mysql->getGroupBy($cfg);
    $expected = 'GROUP BY `id`, `name`';

    $this->assertSame($expected, trim($result));
  }

  /** @test */
  public function getGroupBy_returns_empty_string_when_configurations_are_missing()
  {
    $this->assertSame('', self::$mysql->getGroupBy(['group' => ['id']]));
    $this->assertSame('', self::$mysql->getGroupBy([
      'group_by' => ['id']
    ]));

    $this->assertSame('', self::$mysql->getGroupBy([
      'group_by' => ['id'],
      'available_fields' => [
        'username'
      ]
    ]));
  }

  /** @test */
  public function getGroupBy_method_sets_an_error_when_available_fields_config_missing_one_of_the_fields()
  {
    self::$mysql->setErrorMode(Errors::E_DIE);

    $this->expectException(\Exception::class);

    self::$mysql->getGroupBy([
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

    $result   = self::$mysql->getHaving($cfg);
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

    $result   = self::$mysql->getHaving($cfg);
    $expected = "WHERE 
  user_count >= ?";

    $this->assertSame($expected, trim($result));
  }

  /** @test */
  public function getHaving_method_returns_empty_string_when_configuration_missing_some_items()
  {
    $this->assertSame('', self::$mysql->getHaving([]));
    $this->assertSame('', self::$mysql->getHaving([
      'having'   => [
        'conditions' => [[
          'field' => 'user_count',
          'value'   => 20,
          'operator' => '>='
        ]],
        'logic' => 'AND'
      ]
    ]));

    $this->assertSame('', self::$mysql->getHaving(['group_by' => ['id']]));
    $this->assertSame('', self::$mysql->getHaving([
      'group_by' => ['id'],
      'having'   => [
        'conditions' => [[
          'field' => 'user_count',
          'value'   => 20,
          'operator' => '>='
        ]]
      ]
    ]));

    $this->assertSame('', self::$mysql->getHaving([
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

    $result   = self::$mysql->getOrder($cfg);
    $expected = 'ORDER BY `id_alias` DESC,
`users`.`username` ASC,
first_name ASC';

    $this->assertSame($expected, trim($result));
  }

  /** @test */
  public function getOrder_method_returns_empty_string_when_configurations_missing_some_items()
  {
    $this->assertSame('', self::$mysql->getOrder([]));
    $this->assertSame('', self::$mysql->getOrder([
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
    $result   = self::$mysql->getLimit(['limit' => 2]);
    $expected = 'LIMIT 0, 2';

    $this->assertSame($expected, trim($result));

    $result   = self::$mysql->getLimit(['limit' => 2, 'start' => 4]);
    $expected = 'LIMIT 4, 2';

    $this->assertSame($expected, trim($result));
  }

  /** @test */
  public function getLimit_method_returns_empty_string_when_configurations_missing_the_limit_param()
  {
    $this->assertSame('', self::$mysql->getLimit([]));
  }

  /** @test */
  public function getLimit_method_returns_empty_string_when_the_provided_limit_is_not_an_integer()
  {
    $this->assertSame('', self::$mysql->getLimit(['limit' => 'foo']));
  }

  /** @test */
  public function getRawCreate_method_returns_a_string_with_create_table_statement_by_querying_database_by_table_name()
  {
    self::$connection->query($expected = 'CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    $result   = self::$mysql->getRawCreate('users');

    $this->assertSame($expected, trim($result));
  }

  /** @test */
  public function getRawCreate_method_returns_empty_string_if_failed_to_get_table_full_name()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('tableFullName')
      ->once()
      ->with('users', true)
      ->andReturnNull();

    $this->assertSame('', $mysql->getRawCreate('users'));
  }

  /** @test */
  public function getRawCreate_method_returns_empty_string_if_raw_query_failed()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('tableFullName')
      ->once()
      ->with('users', true)
      ->andReturn(self::getDbConfig()['db'] . 'users');

    $mysql->shouldReceive('rawQuery')
      ->once()
      ->andReturnFalse();

    $this->assertSame('', $mysql->getRawCreate('users'));
  }

  /** @test */
  public function getCreateTable_method_returns_a_string_with_create_table_statement()
  {
    $cfg = [
      'fields' => [
        'id' => [
          'type' => 'binary',
          'maxlength' => 32
        ],
        'username' => [
          'type' => 'varchar',
          'maxlength' => 255
        ],
        'role' => [
          'type' => 'enum',
          'extra' => "'super_admin','admin','user'",
          'default' => 'user'
        ],
        'permission' => [
          'type' => 'set',
          'extra' => "'read','write'",
          'default' => 'read'
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
        ]
      ]
    ];

    $result   = self::$mysql->getCreateTable('users', $cfg);
    $expected = "
  CREATE TABLE `users` (
  `id` binary(32) NOT NULL,
  `username` varchar(255) NOT NULL,
  `role` enum ('super_admin','admin','user') NOT NULL DEFAULT 'user',
  `permission` set ('read','write') NOT NULL DEFAULT 'read',
  `balance` decimal(10,2) UNSIGNED DEFAULT NULL,
  `balance_before` decimal(10,2) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8";

    $this->assertSame(trim($expected), trim($result));

    return $expected;
  }

  /** @test */
  public function getCreateTable_method_throws_an_exception_when_a_field_type_is_not_valid()
  {
    $this->expectException(\Exception::class);

    $cfg = [
      'fields' => [
        'balance' => [
          'type' => 'number'
        ]
      ]
    ];

    self::$mysql->getCreateTable('users', $cfg);
  }

  /** @test */
  public function getCreateTable_throws_an_exception_when_a_provided_field_is_enum_or_set_and_the_extra_field_is_not_provided()
  {
    $this->expectException(\Exception::class);

    $cfg = [
      'fields' => [
        'permission' => [
          'type' => 'set',
          'default' => 'read'
        ]
      ]
    ];

    self::$mysql->getCreateTable('users', $cfg);
  }

  /**
   * @test
   * @depends getCreateTable_method_returns_a_string_with_create_table_statement
   */
  public function getCreateTable_method_returns_a_string_with_create_table_statement_when_model_is_not_provided($query)
  {
    // Create the table from the query from the other test that this one depends on
    // So that the modelize method can get table structure
    self::$connection->query($query);

    // Set expectations for the methods called on Cache class in modelize method
    $this->setCacheExpectations();

    $result = self::$mysql->getCreateTable('users');

    $this->assertSame(trim($query), trim($result));
  }

  /** @test */
  public function getCreateKeys_method_returns_string_with_create_keys_statement()
  {
    $cfg = [
      'keys' => [
        'primary' => [
          'unique' => true,
          'columns' => ['id']
        ],
        'unique' => [
          'unique' => true,
          'columns' => ['email']
        ],
        'key' => [
          'columns' => ['username']
        ]
      ],
      'fields' => [
        'id' => [
          'key' => 'PRI'
        ]
      ]
    ];

    $result   = self::$mysql->getCreateKeys('users', $cfg);
    $expected = 'ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique` (`email`),
  ADD KEY `key` (`username`);';

    $this->assertSame(trim($expected), trim($result));

    return $expected;
  }

  /**
   * @test
   * @depends getCreateKeys_method_returns_string_with_create_keys_statement
   */
  public function getCreateKeys_method_returns_string_with_create_keys_statement_when_model_is_null($query)
  {
    $this->clearCache();

    $this->createTable('users', function () {
      return "`id` int(11) NOT NULL,
              `username` varchar(255) NOT NULL,
              `email` varchar(255) NOT NULL";
    });

    // Create the keys from the query from the other test that this one depends on
    // So that the modelize method can get table structure
    self::$connection->query($query);

    // Set expectations for the methods called on Cache class in modelize method
    self::$cache_mock->shouldReceive('get')
      ->once()
      ->andReturnFalse();

    self::$cache_mock->shouldReceive('set')
      ->once()
      ->andReturnTrue();

    $result = self::$mysql->getCreateKeys('users');

    $this->assertSame(trim($query), trim($result));
  }

  /** @test */
  public function getCreateKeys_method_returns_empty_string_when_configurations_missing_items()
  {
    $this->assertSame('', self::$mysql->getCreateKeys('users', [
      'fields' => [
        'id' => ['key' => 'PRI']
      ]
    ]));
  }

  /** @test */
  public function getCreateKeys_method_returns_empty_string_when_model_cannot_be_retrieved_from_database()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('modelize')
      ->once()
      ->with('users')
      ->andReturnNull();

    $this->assertSame('', $mysql->getCreateKeys('users'));
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

    $result   = self::$mysql->getCreateConstraints('roles', $cfg);
    $expected = 'ALTER TABLE `roles`
  ADD CONSTRAINT `user_role` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `user_role_2` FOREIGN KEY (`user_id2`) REFERENCES `users` (`id2`) ON DELETE CASCADE ON UPDATE CASCADE;
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
      return "`id` int(11) NOT NULL PRIMARY KEY,
              `id2` int(11) NOT NULL UNIQUE";
    });

    $this->createTable('roles', function () {
      return "`user_id` int(11) NOT NULL,
              `user_id2` int(11) NOT NULL";
    });

    // Create the constraints from the query from the other test that this one depends on
    // So that the modelize method can get table structure
    self::$connection->query($query);

    // Set expectations for the methods called on Cache class in modelize method
    $this->setCacheExpectations();

    $result = self::$mysql->getCreateConstraints('roles');

    $this->assertStringContainsString(
      'ALTER TABLE `roles`',
      trim($result)
    );

    $this->assertStringContainsString(
      'ADD CONSTRAINT `user_role` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)',
      trim($result)
    );

    $this->assertStringContainsString(
      'ADD CONSTRAINT `user_role_2` FOREIGN KEY (`user_id2`) REFERENCES `users` (`id2`) ON DELETE CASCADE ON UPDATE CASCADE',
      trim($result)
    );
  }

  /** @test */
  public function getCreateConstraints_method_returns_empty_string_when_configuration_missing_items()
  {
    $this->assertSame('', self::$mysql->getCreateConstraints('roles', [
      'keys' => [
        ['ref_table' => '']
      ]
    ]));

    $this->assertSame('', self::$mysql->getCreateConstraints('roles', [
      'keys' => [
        ['ref_table' => 'users']
      ]
    ]));

    $this->assertSame('', self::$mysql->getCreateConstraints('roles', [
      'keys' => [
        ['ref_table' => 'users', 'columns' => ['users', 'roles']]
      ]
    ]));

    $this->assertSame('', self::$mysql->getCreateConstraints('roles', [
      'keys' => [
        [
          'ref_table' => 'users',
          'columns'   => ['users']
        ]
      ]
    ]));

    $this->assertSame('', self::$mysql->getCreateConstraints('roles', [
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
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('modelize')
      ->once()
      ->with('roles')
      ->andReturnNull();

    $result = $mysql->getCreateConstraints('roles');

    $this->assertSame('', $result);
  }

  /** @test */
  public function getCreate_method_returns_a_string_with_create_table_statement_considering_fields_keys()
  {
    $cfg = [
      'fields' => [
        'id' => [
          'type' => 'binary',
          'maxlength' => 32,
          'key' => 'PRI'
        ],
        'email' => [
          'type' => 'varchar',
          'maxlength' => 255
        ],
        'username' => [
          'type' => 'varchar',
          'maxlength' => 255
        ]
      ],
      'keys' => [
        'primary' => [
          'unique' => true,
          'columns' => ['id']
        ],
        'unique' => [
          'unique' => true,
          'columns' => ['email']
        ],
        'key' => [
          'columns' => ['username']
        ]
      ],
    ];

    $result   = self::$mysql->getCreate('users', $cfg);
    $expected = 'CREATE TABLE `users` (
  `id` binary(32) NOT NULL,
  `email` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique` (`email`),
  KEY `key` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8
';

    $this->assertSame(trim($expected), trim($result));
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
        'unique' => [
          'unique' => true,
          'columns' => ['email'],
          'ref_table' => 'user_emails',
          'ref_column' => 'user_id'
        ]
      ]
    ];

    $this->assertStringContainsString(
      'FOREIGN KEY (`email`) REFERENCES `user_emails` (`user_id`)',
      self::$mysql->getCreate('users', $cfg)
    );
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

    $result   = self::$mysql->getCreate('users', $cfg);
    $expected = 'CREATE TABLE `users` (
  `email` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8';

    $this->assertSame(trim($expected), trim($result));
  }

  /** @test */
  public function getCreate_method_returns_empty_string_when_getCreateTable_returns_empty_string()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('getCreateTable')
      ->once()
      ->with('users', ['fields' => []])
      ->andReturn('');

    $this->assertSame('', $mysql->getCreate('users', ['fields' => []]));
  }


  /** @test */
  public function createIndex_method_creates_index_for_the_givens_table_and_columns()
  {
    $this->createTable('users', function () {
      return "`email` varchar(255) NOT NULL";
    });

    $result = self::$mysql->createIndex('users', 'email', true);
    $model = $this->getTableStructure('users');

    $this->assertTrue($result);
    $this->assertTrue(isset($model['keys']['users_email']['unique']));
    $this->assertSame(1, $model['keys']['users_email']['unique']);

    // Another test with a not unique key
    $this->clearCache();
    $this->createTable('users', function () {
      return "`email` varchar(255) NOT NULL,
              `username` varchar(20) NOT NULL";
    });

    $result2 = self::$mysql->createIndex('users', ['email', 'username'], false, 20);
    $model2  = $this->getTableStructure('users');

    $this->assertTrue($result2);
    $this->assertTrue(isset($model2['keys']['users_email_username']['unique']));
    $this->assertSame(0, $model2['keys']['users_email_username']['unique']);
  }

  /** @test */
  public function createIndex_method_throws_an_exception_when_column_has_a_not_valid_name_and_mode_is_die()
  {
    $this->expectException(\Exception::class);

    self::$mysql->setErrorMode(Errors::E_DIE);
    self::$mysql->createIndex('users', 'use*rs');
  }

  /**
   * @test
   * @depends createIndex_method_creates_index_for_the_givens_table_and_columns
   */
  public function deleteIndex_method_deletes_the_given_index()
  {
    $this->createTable('users', function () {
      return "`email` varchar(255) NOT NULL";
    });

    // Create the index
    self::$mysql->createIndex('users', 'email');

    $result = self::$mysql->deleteIndex('users', 'users_email');
    $model  = $this->getTableStructure('users');

    $this->assertTrue($result);
    $this->assertIsArray($model['keys']);
    $this->assertArrayNotHasKey('users_email', $model['keys']);
  }

  /** @test */
  public function deleteIndex_method_returns_false_when_table_full_name_cannot_be_retrieved()
  {
    $this->assertFalse(
      self::$mysql->deleteIndex('users', 'ema*ail')
    );
  }

  /**
   * @test
   * @depends getDatabases_method_returns_database_names_as_array
   */
  public function createMysqlDatabase_method_creates_a_database()
  {
    $this->dropDatabaseIfExists('bbn_create_test');

    $method = $this->getNonPublicMethod('createMysqlDatabase');

    $result = $method->invoke(self::$mysql, 'bbn_create_test');

    $this->assertTrue($result);
    $this->assertTrue(in_array('bbn_create_test', self::$mysql->getDatabases()));

    $this->dropDatabaseIfExists('bbn_create_test');
  }

  /**
   * @test
   * @depends getDatabases_method_returns_database_names_as_array
   */
  public function createDatabase_method_creates_a_database()
  {
    $this->dropDatabaseIfExists('bbn_create_test');

    $result = self::$mysql->createDatabase('bbn_create_test');

    $this->assertTrue($result);
    $this->assertTrue(
      in_array('bbn_create_test', self::$mysql->getDatabases())
    );

    $this->dropDatabaseIfExists('bbn_create_test');
  }

  /**
   * @test
   * @depends createDatabase_method_creates_a_database
   * @depends getDatabases_method_returns_database_names_as_array
   *
   */
  public function dropDatabase_method_drops_the_given_database()
  {
    self::$mysql->createDatabase('bbn_create_test');

    self::$mysql->dropDatabase('bbn_create_test');

    $this->assertFalse(
      in_array('bbn_create_test', self::$mysql->getDatabases())
    );
  }

  /** @test */
  public function createUser_method_creates_a_database_user()
  {
    $db_config = self::getDbConfig();

    self::$connection->query("DROP USER IF EXISTS 'testing_user'@'{$db_config['host']}'");

    $this->assertTrue(
      self::$mysql->createUser('testing_user', '1-239876@#pqtaA')
    );

    $users = self::$mysql->getUsers('testing_user');

    $this->assertSame(
      "GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER ON `{$db_config['db']}`.* TO `testing_user`@`{$db_config['host']}`",
      $users[1]
    );

    self::$connection->query("DROP USER IF EXISTS 'testing_user'@'{$db_config['host']}'");
  }

  /** @test */
  public function createUser_returns_false_when_the_given_user_is_not_a_valid_name()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldNotReceive('rawQuery');

    $this->assertFalse(
      $mysql->createUser('use**r', '12345', self::$mysql->getCurrent())
    );
  }

  /** @test */
  public function createUser_returns_throws_an_exception_when_the_given_db_is_not_a_valid_name()
  {
    $this->expectException(\Exception::class);

    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldNotReceive('rawQuery');

    $this->assertFalse(
      $mysql->createUser('user', '12345', 'table***')
    );
  }

  /** @test */
  public function deleteUser_method_deletes_the_given_user()
  {
    self::$mysql->createUser('testing_user', '1-239876@#pqtaA');

    $this->assertTrue(
      self::$mysql->deleteUser('testing_user')
    );

    $this->assertEmpty(
     self::$mysql->getUsers('testing_user')
    );
  }

  /** @test */
  public function deleteUser_returns_false_when_the_given_user_is_not_valid()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldNotReceive('rawQuery');

    $this->assertFalse(
      $mysql->deleteUser('user***')
    );
  }

  /**
   * @test
   * @depends createUser_method_creates_a_database_user
   */
  public function getUsers_method_returns_the_current_db_user_for_the_given_name()
  {
    // Tested in the dependable test method
    $this->assertTrue(true);
  }

  /** @test */
  public function getUsers_method_returns_all_db_users_when_no_name_is_given()
  {
    $result = self::$mysql->getUsers();

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
  }

  /** @test */
  public function dbSize_method_returns_the_size_of_the_current_or_given_database()
  {
    // Database is empty
    $this->assertSame(0, self::$mysql->dbSize());

    $this->createTable('users', function () {
      return "`description` text NOT NULL";
    });

    $text = Str::genpwd(2000, 2000);

    $this->insertOne('users', ['description' => $text]);

    $this->assertTrue(self::$mysql->dbSize() > 0);
    $this->assertSame(0, self::$mysql->dbSize('', 'index'));

    $this->dropTableIfExists('users');

    // Test with a database different from the current one
    $this->dropDatabaseIfExists('size_testing');
    self::$connection->query('CREATE DATABASE size_testing');

    $this->assertSame(0, self::$mysql->dbSize('size_testing'));
    $this->assertSame(self::getDbConfig()['db'], self::$mysql->getCurrent());

    $this->dropDatabaseIfExists('size_testing');
  }

  /** @test */
  public function tableSize_method_returns_size_for_the_given_table()
  {
    $this->createTable('comments', function () {
      return 'description text NOT NULL';
    });

    $text = Str::genpwd(5000, 4000);

    $this->insertOne('comments', ['description' => $text]);

    $this->assertTrue(self::$mysql->tableSize('comments') > 0);
    $this->assertSame(0, self::$mysql->tableSize('comments', 'index'));
  }

  /** @test */
  public function tableSize_method_throws_an_exception_when_table_not_found()
  {
    $this->expectException(\Exception::class);

    self::$mysql->tableSize('dummy_table');
  }

  /** @test */
  public function status_method_returns_the_status_of_the_given_table()
  {
    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY, text text NOT NULL';
    });

    $this->assertNull(self::$mysql->status());

    $result = self::$mysql->status('users');

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
    $this->assertSame('users', $result['Name']);
    $this->assertSame(0, $result['Index_length']);
    $this->assertSame('utf8mb4_unicode_ci', $result['Collation']);
    $this->assertSame('InnoDB', $result['Engine']);
  }

  /** @test */
  public function status_method_returns_the_status_of_the_given_table_for_a_different_database()
  {
    $this->dropDatabaseIfExists('testing_db');
    self::$connection->query("CREATE DATABASE testing_db");

    self::$connection->query('USE testing_db');
    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY, text text NOT NULL';
    });

    self::$connection->query('USE ' . self::getDbConfig()['db']);

    $result = self::$mysql->status('users', 'testing_db');

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
    $this->assertSame('users', $result['Name']);
    $this->assertSame(0, $result['Index_length']);
    $this->assertSame('utf8mb4_unicode_ci', $result['Collation']);
    $this->assertSame('InnoDB', $result['Engine']);
    $this->assertSame(self::getDbConfig()['db'], self::$mysql->getCurrent());

    $this->dropDatabaseIfExists('testing_db');
  }

  /** @test */
  public function status_method_returns_null_when_table_does_not_exist()
  {
    $this->assertNull(self::$mysql->status('unknown_table'));
  }


  /** @test */
  public function status_method_throws_an_exception_when_database_does_not_exist()
  {
    $this->expectException(\Exception::class);

    $this->assertNull(self::$mysql->status('users', 'unknown_db'));
  }

  /** @test */
  public function getUid_method_generates_a_uuid()
  {
    $result = self::$mysql->getUid();

    $this->assertIsString($result);
    $this->assertSame(32, strlen($result));
  }

  /** @test */
  public function createTable_method_returns_a_string_of_create_table_statement_from_given_arguments()
  {
    $columns = [
      'email' => [
        'name' => 'email',
        'type' => 'text',
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
        'type' => 'decimal',
        'values' => '(10,2)',
        'default' => 0
      ],
      'invalid_name***' => [
        'type' => 'text'
      ]
    ];

    $expected = 'CREATE TABLE `users` (
`email` text(255) NOT NULL,
`id` int UNSIGNED NOT NULL,
`balance` decimal NOT NULL DEFAULT \'0\'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;';

    $this->assertSame(
      $expected,
      self::$mysql->createTable('users', $columns)
    );
  }

  /** @test */
  public function escape_method_returns_an_escaped_db_expression_from_the_given_item()
  {
    $this->assertSame('`users`', self::$mysql->escape('users'));
    $this->assertSame('`db_test`.`users`', self::$mysql->escape('db_test.users'));
    $this->assertSame('`db_test`.`users`', self::$mysql->escape('db_test.`users`'));
    $this->assertSame('`db_test`.`users`', self::$mysql->escape('`db_test`.`users`'));
  }

  /** @test */
  public function escape_method_throws_an_exception_when_the_given_item_is_not_valid()
  {
    $this->expectException(\Exception::class);

    self::$mysql->escape('users***');
  }

  /** @test */
  public function tableFullName_method_returns_table_full_name()
  {
    $db = self::getDbConfig()['db'];

    $this->assertSame(
      "$db.users",
      self::$mysql->tableFullName('users')
    );

    $this->assertSame(
      "`$db`.`users`",
      self::$mysql->tableFullName('users', true)
    );

    $this->assertSame(
      "$db.users",
      self::$mysql->tableFullName("$db.users")
    );

    $this->assertSame(
      "`$db`.`users`",
      self::$mysql->tableFullName("$db.users", true)
    );

    $this->assertSame(
      "`$db`.`users`",
      self::$mysql->tableFullName("`$db`.`users`", true)
    );

    $this->assertSame(
      "$db.users",
      self::$mysql->tableFullName("$db.users.id")
    );

    $this->assertSame(
      "`$db`.`users`",
      self::$mysql->tableFullName("$db.users.id", true)
    );

    $this->assertSame(
      "`$db`.`users`",
      self::$mysql->tableFullName("`$db`.`users`.`id`", true)
    );
  }

  /** @test */
  public function tableFullName_method_returns_null_when_the_given_name_is_not_valid()
  {
    $this->assertNull(self::$mysql->tableFullName('test_db*.users'));
    $this->assertNull(self::$mysql->tableFullName('test_db.users**'));
  }

  /** @test */
  public function tableSimpleName_method_returns_table_simple_name()
  {
    $this->assertSame(
      'users',
      self::$mysql->tableSimpleName('db_test.users')
    );

    $this->assertSame(
      '`users`',
      self::$mysql->tableSimpleName('db_test.users', true)
    );

    $this->assertSame(
      'users',
      self::$mysql->tableSimpleName('`db_test`.`users`')
    );

    $this->assertSame(
      '`users`',
      self::$mysql->tableSimpleName('`db_test`.`users`', true)
    );

    $this->assertSame(
      'users',
      self::$mysql->tableSimpleName('users')
    );

    $this->assertSame(
      'users',
      self::$mysql->tableSimpleName('`users`')
    );

    $this->assertSame(
      '`users`',
      self::$mysql->tableSimpleName('`db_test`.`users`.`email`', true)
    );
  }

  /** @test */
  public function tableSimpleName_method_returns_null_when_the_given_table_name_is_not_valid()
  {
    $this->assertNull(
      self::$mysql->tableSimpleName('db_test.users**')
    );

    $this->assertNull(
      self::$mysql->tableSimpleName('')
    );
  }

  /** @test */
  public function colFullName_method_returns_column_full_name()
  {
    $this->assertSame(
      'users.email',
      self::$mysql->colFullName('email', 'users')
    );

    $this->assertSame(
      '`users`.`email`',
      self::$mysql->colFullName('email', 'users', true)
    );

    $this->assertSame(
      'users.email',
      self::$mysql->colFullName('users.email')
    );

    $this->assertSame(
      '`users`.`email`',
      self::$mysql->colFullName('users.email', null, true)
    );

    $this->assertSame(
      '`users_2`.`email`',
      self::$mysql->colFullName('users.email', 'users_2', true)
    );
  }

  /** @test */
  public function colFullName_method_returns_null_when_the_given_col_or_table_names_is_not_valid()
  {
    $this->assertNull(
      self::$mysql->colFullName('')
    );

    $this->assertNull(
      self::$mysql->colFullName('email**')
    );

    $this->assertNull(
      self::$mysql->colFullName('email', 'users**')
    );

    $this->assertNull(
      self::$mysql->colFullName('users**.email')
    );

    $this->assertNull(
      self::$mysql->colFullName('users.email**')
    );
  }

  /** @test */
  public function colSimpleName_method_returns_column_simple_name()
  {
    $this->assertSame(
      'email',
      self::$mysql->colSimpleName('users.email')
    );

    $this->assertSame(
      '`email`',
      self::$mysql->colSimpleName('users.email', true)
    );

    $this->assertSame(
      'email',
      self::$mysql->colSimpleName('db_test.users.email')
    );

    $this->assertSame(
      '`email`',
      self::$mysql->colSimpleName('db_test.users.email', true)
    );

    $this->assertSame(
      'email',
      self::$mysql->colSimpleName('email')
    );

    $this->assertSame(
      '`email`',
      self::$mysql->colSimpleName('email', true)
    );
  }

  /** @test */
  public function colSimpleName_method_returns_null_when_the_given_column_name_is_not_valid()
  {
    $this->assertNull(
      self::$mysql->colSimpleName('users.email**')
    );

    $this->assertNull(
      self::$mysql->colSimpleName('db_test.users.email**')
    );

    $this->assertNull(
      self::$mysql->colSimpleName('')
    );
  }

  /** @test */
  public function isTableFullName_method_checks_if_the_given_table_name_is_full_name()
  {
    $this->assertTrue(
      self::$mysql->isTableFullName('db.users')
    );

    $this->assertFalse(
      self::$mysql->isTableFullName('users')
    );
  }

  /** @test */
  public function isColFullName_method_checks_if_the_given_col_name_is_a_full_name()
  {
    $this->assertTrue(
      self::$mysql->isColFullName('users.email')
    );

    $this->assertFalse(
      self::$mysql->isColFullName('email')
    );
  }

  /** @test */
  public function rawQuery_method_executes_the_given_query_using_original_pdo_function()
  {
    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              username VARCHAR(255) NOT NULL';
    });

    $this->insertOne('users', ['username' => 'foo']);

    $result = self::$mysql->rawQuery("SELECT * FROM users");

    $this->assertInstanceOf(\PDOStatement::class, $result);
    $this->assertSame('foo', $result->fetchObject()->username);
  }

  /** @test */
  public function startFancyStuff_method_sets_the_query_class_as_pdo_derived_statement_class()
  {
    self::$mysql->startFancyStuff();

    $result = $this->getNonPublicProperty('pdo')->getAttribute(\PDO::ATTR_STATEMENT_CLASS);

    $this->assertIsArray($result);
    $this->assertSame(Query::class, $result[0]);
    $this->assertSame(1, $this->getNonPublicProperty('_fancy'));
  }

  /** @test */
  public function stopFancyStuff_method_sets_statement_class_to_pdo_statement()
  {
    self::$mysql->stopFancyStuff();

    $result = $this->getNonPublicProperty('pdo')->getAttribute(\PDO::ATTR_STATEMENT_CLASS);

    $this->assertIsArray($result);

    $this->assertSame(\PDOStatement::class, $result[0]);
    $this->assertFalse($this->getNonPublicProperty('_fancy'));
  }

  /** @test */
  public function processCfg_method_processes_the_given_insert_configurations()
  {
    $this->setCacheExpectations();

    $db_config = self::getDbConfig();

    $this->createTable('users', function () {
      return 'id BINARY(16) PRIMARY KEY,
              email VARCHAR(255) NOT NULL,
              name VARCHAR(255) NOT NULL';
    });

    $cfg = [
      'tables' => 'users',
      'kind'  => 'INSERT',
      'fields'  => ['email' => 'john@mail.com', 'name' => 'John']
    ];

    $result   = self::$mysql->processCfg($cfg);

    $expected_sql = "INSERT INTO `{$db_config['db']}`.`users`
(`email`, `name`, `id`)
 VALUES (?, ?, ?)";

    $this->assertSame(trim($expected_sql), trim($result['sql']));
    $this->assertTrue(in_array('id', $result['fields']));
    $this->assertSame(['john@mail.com', 'John'], $result['values']);
    $this->assertCount(3, $result['values_desc']);
    $this->assertTrue($result['generate_id']);
    $this->assertFalse($result['auto_increment']);
    $this->assertSame('id', $result['primary']);
    $this->assertSame(16, $result['primary_length']);
    $this->assertSame('binary', $result['primary_type']);
  }

  /** @test */
  public function processCfg_method_processes_the_given_update_configurations()
  {
    $db_config = self::getDbConfig();

    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              email VARCHAR(255) NOT NULL,
              name VARCHAR(255) NOT NULL';
    });

    $cfg = [
      'tables' => 'users',
      'kind'   => 'UPDATE',
      'fields' => ['email' => 'samantha@mail.com', 'name' => 'Samantha'],
      'where'  => [['email', '=', 'sam@mail.com'], ['name', '=', 'Sam']]
    ];

    $result = self::$mysql->processCfg($cfg);

    $expected_sql = "UPDATE `{$db_config['db']}`.`users` SET `email` = ?, `name` = ? WHERE  `users`.`email` = ? AND `users`.`name` = ?";

    $this->assertSame(
      $expected_sql,
      str_replace("\n", ' ', trim($result['sql']))
    );

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
        ['type' => 'varchar', 'maxlength' => 255],
        ['type' => 'varchar', 'maxlength' => 255],
        ['type' => 'varchar', 'maxlength' => 255, 'operator' => '='],
        ['type' => 'varchar', 'maxlength' => 255, 'operator' => '=']
      ],
      $result['values_desc']
    );

    $this->assertTrue($result['auto_increment']);
    $this->assertSame('id', $result['primary']);
    $this->assertSame('int', $result['primary_type']);
    $this->assertNotEmpty($result['hashed_where']['conditions']);
  }

  /** @test */
  public function processCfg_method_processes_the_given_select_configurations()
  {
    $db_config = self::getDbConfig();

    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              name VARCHAR(25) NOT NULL,
              role_id INT(11) NOT NULL';
    });

    $this->createTable('roles', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
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

    $result = self::$mysql->processCfg($cfg);

    $expected_sql = "SELECT `users`.`name` AS `user_name`, `roles`.`name` AS `role_name`
FROM `{$db_config['db']}`.`users`
  JOIN `{$db_config['db']}`.`roles`
    ON 
    `users`.`role_id` = roles.id
WHERE (
  `users`.`id` >= 1
  AND `roles`.`name` != ?
)
ORDER BY `users`.`name` DESC
LIMIT 2, 25";

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
    $db = self::getDbConfig()['db'];

    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              name VARCHAR(25) NOT NULL,
              active TINYINT(1) NOT NULL DEFAULT 1';
    });

    $cfg = [
      'tables' => 'users',
      'count'  => true,
      'group_by' => ['id'],
      'where' => ['active' => 1]
    ];

    $result = self::$mysql->processCfg($cfg);

    $expected_sql = "SELECT COUNT(*) FROM ( SELECT `users`.`id` AS `id`
FROM `$db`.`users`
WHERE 
`users`.`active` = ?
GROUP BY `id`
) AS t";

    $this->assertSame(trim($expected_sql), trim($result['sql']));
  }

  /** @test */
  public function processCfg_returns_null_when_the_given_configurations_has_same_tables()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT(11)';
    });


    $this->assertNull(
      self::$mysql->processCfg(['tables' => ['users', 'users']])
    );
  }

  /** @test */
  public function processCfg_returns_null_and_sets_an_error_when_no_hash_found()
  {
    $mysql = \Mockery::mock(Mysql::class)
      ->shouldAllowMockingProtectedMethods()
      ->makePartial();

    $mysql->shouldReceive('_treat_arguments')
      ->once()
      ->with(['foo' => 'bar'])
      ->andReturn(['foo' => 'bar']);

    $mysql->shouldReceive('error')
      ->once();

    $this->assertNull(
      $mysql->processCfg(['foo' => 'bar'])
    );
  }

  /** @test */
  public function processCfg_method_returns_previously_saved_cfg_using_hash()
  {
    $mysql = \Mockery::mock(Mysql::class)
      ->shouldAllowMockingProtectedMethods()
      ->makePartial();

    $this->setNonPublicPropertyValue('cfgs', [
      '123456' => [
        'foo2' => 'bar2'
      ]
    ], $mysql);

    $mysql->shouldReceive('_treat_arguments')
      ->once()
      ->with(['foo' => 'bar'])
      ->andReturn(['hash' => '123456']);

    $this->assertSame(
      ['foo2' => 'bar2', 'values' => [], 'where' => [], 'filters' => []],
      $mysql->processCfg(['foo' => 'bar'])
    );
  }

  /** @test */
  public function processCfg_method_returns_null_when_a_given_field_does_not_exists()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT(11)';
    });

    $cfg = [
      'tables' => 'users',
      'fields' => ['username' => 'username']
    ];

    $this->assertNull(
      self::$mysql->processCfg($cfg)
    );
  }

  /** @test */
  public function reprocessCfg_method_test()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('processCfg')
      ->once()
      ->with(['foo' => 'bar'], true)
      ->andReturn(['foo2' => 'bar2']);

    $this->assertSame(['foo2' => 'bar2'], $mysql->reprocessCfg(['foo' => 'bar']));

    // Another test

    $this->setNonPublicPropertyValue('cfgs', [
      '12345' => ['a' => 'b']
    ], $mysql);

    $mysql->shouldReceive('processCfg')
      ->once()
      ->with(
        ['foo' => 'bar', 'hash' => '12345', 'values' => ['a', 'b']],
        true
      )
      ->andReturn(['foo2' => 'bar2', 'values' => ['c', 'd']]);

    $result = $mysql->reprocessCfg([
      'bbn_db_processed' => true,
      'bbn_db_treated'   => true,
      'hash'             => '12345',
      'foo'              => 'bar',
      'values'           => ['a', 'b']
    ]);

    $this->assertSame(['foo2' => 'bar2', 'values' => ['a', 'b']], $result);

    $this->assertSame([], $this->getNonPublicProperty('cfgs', $mysql));
  }


  /** @test */
  public function parseQuery_method_parses_an_sql_and_return_an_array()
  {
    $result = self::$mysql->parseQuery(
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
      self::$mysql->parseQuery('foo')
    );
  }

  /** @test */
  public function getRealLastParams_method_returns_the_last_real_params()
  {
    $this->setNonPublicPropertyValue('last_real_params', ['foo' => 'bar']);

    $this->assertSame(
      ['foo' => 'bar'],
      self::$mysql->getRealLastParams()
    );
  }

  /** @test */
  public function realLast_method_returns_the_real_last_query()
  {
    $this->setNonPublicPropertyValue('last_real_query', 'SELECT * FROM users');

    $this->assertSame(
      'SELECT * FROM users',
      self::$mysql->realLast()
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
      self::$mysql->getLastValues()
    );

    $this->setNonPublicPropertyValue('last_params', null);

    $this->assertNull(self::$mysql->getLastValues());
  }

  /** @test */
  public function getLastParams_method_returns_the_last_params()
  {
    $this->setNonPublicPropertyValue('last_params', [
      'values' => ['foo' => 'bar']
    ]);

    $this->assertSame(
      ['values' => ['foo' => 'bar']],
      self::$mysql->getLastParams()
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

    $result   = self::$mysql->getQueryValues($cfg);
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
    $this->assertSame([], self::$mysql->getQueryValues([]));
    $this->assertSame([], self::$mysql->getQueryValues(['values' => []]));
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

    $result = $method->invoke(self::$mysql, $cfg);

    $this->assertSame($expected, $result);

    // Another test
    $this->assertSame(
      ['hashed' => ['foo' => 'bar'], 'values' => []],
      $method->invoke(self::$mysql, ['foo' => 'bar'])
    );
  }

  /** @test */
  public function enableTrigger_method_enables_trigger_function()
  {
    $this->setNonPublicPropertyValue('_triggers_disabled', true);

    $result = self::$mysql->enableTrigger();

    $this->assertFalse(
      $this->getNonPublicProperty('_triggers_disabled')
    );

    $this->assertInstanceOf(Mysql::class, $result);
  }

  /** @test */
  public function disableTrigger_method_disables_trigger_functions()
  {
    $this->setNonPublicPropertyValue('_triggers_disabled', false);

    $result = self::$mysql->disableTrigger();

    $this->assertTrue(
      $this->getNonPublicProperty('_triggers_disabled')
    );

    $this->assertInstanceOf(Mysql::class, $result);
  }

  /** @test */
  public function isTriggerEnabled_method_checks_if_trigger_function_is_enabled()
  {
    $this->setNonPublicPropertyValue('_triggers_disabled', false);

    $this->assertTrue(
      self::$mysql->isTriggerEnabled()
    );
  }

  /** @test */
  public function isTriggerDisabled_method_checks_if_trigger_functions_is_disabled()
  {
    $this->setNonPublicPropertyValue('_triggers_disabled', true);

    $this->assertTrue(
      self::$mysql->isTriggerDisabled()
    );
  }

  /** @test */
  public function setTrigger_method_register_a_callback_to_be_applied_every_time_the_methods_kind_are_used()
  {
    $db = self::getDbConfig()['db'];

    $default_triggers = $this->getNonPublicProperty('_triggers');

    $this->createTable('users', function () {
      return 'email varchar(255)';
    });

    $this->createTable('roles', function () {
      return 'name varchar(255)';
    });

    $expected = 'A call back function';

    $result = self::$mysql->setTrigger(function () use ($expected) {
      return $expected;
    });

    $this->assertInstanceOf(Mysql::class, $result);

    $triggers = $this->getNonPublicProperty('_triggers');

    $this->assertSame(
      $expected,
      $triggers['SELECT']['before']["$db.users"][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['SELECT']['before']["$db.roles"][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['SELECT']['after']["$db.users"][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['SELECT']['after']["$db.roles"][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['INSERT']['before']["$db.users"][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['INSERT']['before']["$db.roles"][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['INSERT']['after']["$db.users"][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['INSERT']['after']["$db.roles"][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['UPDATE']['before']["$db.users"][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['UPDATE']['before']["$db.roles"][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['UPDATE']['after']["$db.users"][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['UPDATE']['after']["$db.roles"][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['DELETE']['before']["$db.users"][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['DELETE']['before']["$db.roles"][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['DELETE']['after']["$db.users"][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['DELETE']['after']["$db.roles"][0]()
    );

    // Another test
    $this->setNonPublicPropertyValue('_triggers',  $default_triggers);

    self::$mysql->setTrigger(function () use ($expected) {
      return $expected;
    }, 'insert', 'after', 'users');

    $triggers = $this->getNonPublicProperty('_triggers');

    $this->assertSame(
      $expected,
      $triggers['INSERT']['after']["$db.users"][0]()
    );

    // Another test
    $this->setNonPublicPropertyValue('_triggers',  $default_triggers);

    self::$mysql->setTrigger(function () use ($expected) {
      return $expected;
    }, ['insert', 'select'], ['after'], 'users');

    $triggers = $this->getNonPublicProperty('_triggers');

    $this->assertSame(
      $expected,
      $triggers['INSERT']['after']["$db.users"][0]()
    );

    $this->assertSame(
      $expected,
      $triggers['SELECT']['after']["$db.users"][0]()
    );

    $this->setNonPublicPropertyValue('_triggers',  $default_triggers);
  }

  /** @test */
  public function getTriggers_method_returns_the_current_triggers()
  {
    $this->assertSame(
      $this->getNonPublicProperty('_triggers'),
      self::$mysql->getTriggers()
    );
  }

  /** @test */
  public function getDatabases_method_returns_database_names_as_array()
  {
    self::$connection->query('CREATE DATABASE get_database_test');

    $result = self::$mysql->getDatabases();

    $this->assertIsArray($result);

    $this->assertTrue(
      in_array(self::getDbConfig()['db'], $result)
    );

    $this->assertTrue(
      in_array('get_database_test', $result)
    );

    $this->dropDatabaseIfExists('get_database_test');
  }

  /** @test */
  public function getDatabases_returns_null_if_current_database_is_not_ready_to_process_a_query()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull($mysql->getDatabases());
  }

  /** @test */
  public function getTables_method_returns_tables_names_of_current_database_as_array()
  {
    $this->assertEmpty(self::$mysql->getTables());

    $this->createTable('users', function () {
      return 'username varchar(255)';
    });

    $this->createTable('roles', function () {
      return 'name varchar(255)';
    });

    $result = self::$mysql->getTables();

    $this->assertIsArray($result);

   $this->assertTrue(
     in_array('users', $result)
   );

    $this->assertTrue(
      in_array('roles', $result)
    );
  }

  /** @test */
  public function getTables_method_returns_tables_names_of_the_given_database_as_array()
  {
    self::$connection->query("CREATE DATABASE bbn_testing");
    self::$connection->query("USE bbn_testing");

    $this->createTable('users', function () {
      return 'username varchar(255)';
    });

    self::$connection->query("USE " . self::getDbConfig()['db']);

    $result = self::$mysql->getTables('bbn_testing');

    $this->assertIsArray($result);

    $this->assertTrue(
      in_array('users', $result)
    );

    $this->assertSame(
      $this->getNonPublicProperty('current'),
      self::getDbConfig()['db']
    );

    $this->dropDatabaseIfExists('bbn_testing');
  }

  /** @test */
  public function getTables_method_returns_null_if_the_database_is_not_ready_to_process_a_query()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $mysql->getTables()
    );
  }

  /** @test */
  public function getColumns_method_returns_column_config_of_the_given_table()
  {
    $this->assertEmpty(
      self::$mysql->getColumns('users')
    );

    $this->createTable('users', function () {
      return "id BINARY PRIMARY KEY,
              username VARCHAR(255) NOT NULL UNIQUE,
              balance DECIMAL(10, 2) UNSIGNED DEFAULT 0,
              role ENUM ('Admin', 'User') NOT NULL DEFAULT 'User'
              ";
    });

    $result = self::$mysql->getColumns('users');

    $this->assertIsArray($result);
    $this->assertArrayHasKey('id', $result);
    $this->assertSame([
      "position" => 1,
      "type" => "binary",
      "null" => 0,
      "key" => "PRI",
      "extra" => "",
      "signed" => true,
      "virtual" => false,
      "generation" => "",
      "maxlength" => 1
    ], $result['id']);

    $this->assertArrayHasKey('username', $result);
    $this->assertSame([
      "position" => 2,
      "type" => "varchar",
      "null" => 0,
      "key" => "UNI",
      "extra" => "",
      "signed" => true,
      "virtual" => false,
      "generation" => "",
      "maxlength" => 255
    ], $result['username']);

    $this->assertArrayHasKey('balance', $result);
    $this->assertSame([
      "position" => 3,
      "type" => "decimal",
      "null" => 1,
      "key" => null,
      "extra" => "",
      "signed" => false,
      "virtual" => false,
      "generation" => "",
      "default" => 0.0,
      "maxlength" => 10,
      "decimals" => 2,
    ], $result['balance']);

    $this->assertArrayHasKey('role', $result);
    $this->assertSame([
      "position" => 4,
      "type" => "enum",
      "null" => 0,
      "key" => null,
      "extra" => "'Admin','User'",
      "signed" => true,
      "virtual" => false,
      "generation" => "",
      "default" => "User",
      "values" => [
        "Admin", "User"
      ],
    ], $result['role']);
  }

  /** @test */
  public function getColumns_method_returns_null_if_the_database_is_not_ready_to_process_the_query()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $mysql->getColumns('users')
    );
  }

  /** @test */
  public function getRows_method_returns_an_array_of_indexed_arrays_for_every_row_as_a_query_result()
  {
    $this->createTable('users', function () {
      return 'username VARCHAR(255)';
    });

    $this->assertEmpty(self::$mysql->getRows("SELECT * FROM users"));

    $this->insertMany('users', [
      ['username' => 'john_doe'],
      ['username' => 'john_doe_2'],
    ]);

    $expected = [
      ['username' => 'john_doe'],
      ['username' => 'john_doe_2'],
    ];

    $this->assertSame($expected, self::$mysql->getRows("SELECT * FROM users"));
  }

  /** @test */
  public function getRows_method_returns_null_when_query_method_returns_false()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('query')
      ->once()
      ->andReturnFalse();

    $this->assertNull($mysql->getRows('SELECT * FROM users'));
  }

  /** @test */
  public function getRow_method_returns_the_first_row_resulting_from_a_query_as_array_indexed_with_field_name()
  {
    $this->createTable('users', function () {
      return 'username VARCHAR(255)';
    });

    $this->assertEmpty(self::$mysql->getRow("SELECT * FROM users"));

    $this->insertMany('users', [
      ['username' => 'john_doe'],
      ['username' => 'john_doe_2'],
    ]);

    $this->assertSame(
      ['username' => 'john_doe'],
      self::$mysql->getRow("SELECT * FROM users")
    );
  }

  /** @test */
  public function getRow_method_returns_null_when_query_method_returns_false()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('query')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $mysql->getRow('SELECT * FROM users')
    );
  }
  
  /** @test */
  public function getIrow_method_returns_the_first_raw_resulting_from_a_query_as_numeric_indexed_array()
  {
    $this->createTable('users', function () {
      return 'username VARCHAR(255)';
    });

    $this->assertEmpty(self::$mysql->getIrow("SELECT * FROM users"));

    $this->insertMany('users', [
      ['username' => 'john_doe'],
      ['username' => 'john_doe_2']
    ]);

    $this->assertSame(
      ['john_doe'],
      self::$mysql->getIrow("SELECT * FROM users")
    );
  }

  /** @test */
  public function getIrow_method_returns_null_when_query_method_returns_false()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('query')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $mysql->getIrow('SELECT * FROM users')
    );
  }

  /** @test */
  public function getIrows_method_returns_all_rows_resulting_from_a_query_as_numeric_indexed_array()
  {
    $this->createTable('users', function () {
      return 'username VARCHAR(255)';
    });

    $this->assertEmpty(self::$mysql->getIrows("SELECT * FROM users"));

    $this->insertMany('users', [
      ['username' => 'john_doe'],
      ['username' => 'john_doe_2']
    ]);

    $expected = [
      ['john_doe'],
      ['john_doe_2'],
    ];

    $this->assertSame($expected, self::$mysql->getIrows("SELECT * FROM users"));
  }

  /** @test */
  public function getIrows_method_returns_null_when_query_method_returns_false()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('query')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $mysql->getIrows('SELECT * FROM users')
    );
  }

  /** @test */
  public function getByColumns_method_returns_an_indexed_array_by_the_searched_field()
  {
    $this->createTable('users', function () {
      return 'email VARCHAR(255), username VARCHAR(255), name VARCHAR(255)';
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

    $result   = self::$mysql->getByColumns('SELECT name, email, username FROM users');
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

    $result = self::$mysql->getObject("SELECT * FROM users");

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

    $result = self::$mysql->getObjects("SELECT * FROM users");

    $this->assertIsArray($result);
    $this->assertCount(2, $result);
    $this->assertIsObject($result[0]);
    $this->assertIsObject($result[1]);
    $this->assertSame('john_doe', $result[0]->username);
    $this->assertSame('john_doe_2', $result[1]->username);
  }

  /** @test */
  public function getKeys_method_returns_the_keys_of_the_given_table()
  {
    $this->createTable('users', function () {
      return 'name VARCHAR(255)';
    });

    $this->assertEmpty(
      self::$mysql->getKeys('SELECT * FROM USERS')
    );

    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              username VARCHAR(255) UNIQUE,
              created_at DATETIME DEFAULT NULL,
              role_id INT(11)';
    });

    $this->createTable('roles', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              name VARCHAR(255)';
    });

    self::$connection->query(
      'ALTER TABLE users ADD CONSTRAINT `user_role_id`
       FOREIGN KEY (role_id) REFERENCES roles (id) 
       ON UPDATE CASCADE ON DELETE RESTRICT'
    );

    $result   = self::$mysql->getKeys('users');
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
        'username' => [
          'columns' => ['username'],
          'ref_db' => null,
          'ref_table' => null,
          'ref_column' => null,
          'constraint' => null,
          'update' => null,
          'delete' => null,
          'unique' => 1
        ],
        'user_role_id' => [
          'columns' => ['role_id'],
          'ref_db'   => self::getDbConfig()['db'],
          'ref_table' => 'roles',
          'ref_column' => 'id',
          'constraint' => 'user_role_id',
          'update' => 'CASCADE',
          'delete' => 'RESTRICT',
          'unique' => 0
        ]
      ],
      'cols' => [
        'id' => ['PRIMARY'],
        'username' => ['username'],
        'role_id' => ['user_role_id']
      ]
    ];

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getKeys_method_returns_null_if_the_database_is_not_ready_to_process_a_query()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $mysql->getKeys('SELECT * FROM USER')
    );
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
      self::$mysql->getFieldsList('users')
    );

    $this->assertSame(
      ['users.username', 'users.name', 'roles.name'],
      self::$mysql->getFieldsList(['users', 'roles'])
    );
  }

  /** @test */
  public function getFieldsList_method_throws_an_exception_when_table_not_found()
  {
    $this->expectException(\Exception::class);

    self::$mysql->getFieldsList('users');
  }

  /** @test */
  public function getForeignKeys_method_returns_an_array_of_tables_and_fields_related_to_the_given_foreign_key()
  {
    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              username VARCHAR(255) UNIQUE,
              created_at DATETIME DEFAULT NULL,
              role_id INT(11)';
    });

    $this->createTable('roles', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              name VARCHAR(255)';
    });

    self::$connection->query(
      'ALTER TABLE users ADD CONSTRAINT `user_role_id`
       FOREIGN KEY (role_id) REFERENCES roles (id) 
       ON UPDATE CASCADE ON DELETE RESTRICT'
    );

      $this->assertSame(
        [self::getDbConfig()['db'] . '.users' => ['role_id']],
        self::$mysql->getForeignKeys('id', 'roles')
      );

    $this->assertSame(
      [],
      self::$mysql->getForeignKeys('id', 'roles', 'another_db')
    );

      $this->assertSame(
        [],
        self::$mysql->getForeignKeys('role_id', 'users')
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
      self::$mysql->hasIdIncrement('roles')
    );

    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT';
    });

    $this->assertTrue(
      self::$mysql->hasIdIncrement('users')
    );
  }

  /** @test */
  public function fmodelize_method_returns_fields_structure_for_the_given_table()
  {
    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              username VARCHAR(255) UNIQUE DEFAULT NULL';
    });

    $this->setCacheExpectations();

    $result   = self::$mysql->fmodelize('users');
    $expected = [
      'id' => [
        'position'  => 1,
        'type'      => 'int',
        'null'      => 0,
        'key'       => 'PRI',
        'extra'     => 'auto_increment',
        'signed'    => true,
        'virtual'   => false,
        'generation'  => '',
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
        'type'      => 'varchar',
        'null'      => 1,
        'key'       => 'UNI',
        'extra'     => '',
        'signed'    => true,
        'virtual'   => false,
        'generation'  => '',
        'default'    => 'NULL',
        'maxlength' => 255,
        'name' => 'username',
        'keys' => [
          'username' => [
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
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('modelize')
      ->once()
      ->andReturnNull();

    $this->assertNull(
      $mysql->fmodelize('users')
    );
  }

  /** @test */
  public function findReferences_method_returns_an_array_with_foreign_key_references_for_the_given_column()
  {
    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              username VARCHAR(255) UNIQUE,
              created_at DATETIME DEFAULT NULL,
              role_id INT(11)';
    });

    $this->createTable('roles', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              name VARCHAR(255)';
    });

    self::$connection->query(
      'ALTER TABLE users ADD CONSTRAINT `user_role_id`
       FOREIGN KEY (role_id) REFERENCES roles (id) 
       ON UPDATE CASCADE ON DELETE RESTRICT'
    );

    $this->setCacheExpectations();

    $this->assertSame(
      [self::getDbConfig()['db'] . '.users.role_id'],
      self::$mysql->findReferences('roles.id')
    );

    $this->assertSame(
      [],
      self::$mysql->findReferences('users.role_id')
    );
  }

  /** @test */
  public function findReferences_method_returns_Null_if_the_provided_column_name_does_not_have_table_name()
  {
    $this->assertNull(
      self::$mysql->findReferences('role_id')
    );
  }

  /** @test */
  public function findReferences_method_returns_an_array_with_foreign_key_references_for_a_different_database()
  {
    self::$connection->query('CREATE DATABASE IF NOT EXISTS testing_db');
    self::$connection->query('use testing_db');

    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              role_id INT(11)';
    });

    $this->createTable('roles', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              name VARCHAR(255)';
    });

    self::$connection->query(
      'ALTER TABLE users ADD CONSTRAINT `user_role_id`
       FOREIGN KEY (role_id) REFERENCES roles (id) 
       ON UPDATE CASCADE ON DELETE RESTRICT'
    );

    self::$connection->query('use ' . self::getDbConfig()['db']);

    $this->setCacheExpectations();

    $this->assertSame(
      ['testing_db.users.role_id'],
      self::$mysql->findReferences('roles.id', 'testing_db')
    );

    $this->assertSame(self::getDbConfig()['db'], self::$mysql->getCurrent());

    $this->dropDatabaseIfExists('testing_db');
  }

  /** @test */
  public function findRelations_method_returns_an_array_of_a_table_that_has_relations_to_more_than_one_tables()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              role_id INT(11),
              profile_id INT(11)';
    });

    $this->createTable('roles', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              name VARCHAR(255)';
    });

    $this->createTable('profiles', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              name VARCHAR(255)';
    });

    self::$connection->query(
      'ALTER TABLE users ADD CONSTRAINT `user_role`
       FOREIGN KEY (role_id) REFERENCES roles (id) 
       ON UPDATE CASCADE ON DELETE RESTRICT'
    );

    self::$connection->query(
      'ALTER TABLE users ADD CONSTRAINT `user_profile`
       FOREIGN KEY (profile_id) REFERENCES profiles (id) 
       ON UPDATE CASCADE ON DELETE RESTRICT'
    );

    $result   = self::$mysql->findRelations('roles.id');
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
    self::$connection->query('CREATE DATABASE IF NOT EXISTS db_testing');
    self::$connection->query('use db_testing');

    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              role_id INT(11),
              profile_id INT(11)';
    });

    $this->createTable('roles', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              name VARCHAR(255)';
    });

    $this->createTable('profiles', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              name VARCHAR(255)';
    });

    self::$connection->query(
      'ALTER TABLE users ADD CONSTRAINT `user_role`
       FOREIGN KEY (role_id) REFERENCES roles (id) 
       ON UPDATE CASCADE ON DELETE RESTRICT'
    );

    self::$connection->query(
      'ALTER TABLE users ADD CONSTRAINT `user_profile`
       FOREIGN KEY (profile_id) REFERENCES profiles (id) 
       ON UPDATE CASCADE ON DELETE RESTRICT'
    );

    $result   = self::$mysql->findRelations('roles.id', 'db_testing');
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
      self::$mysql->getCurrent()
    );

    $this->dropDatabaseIfExists('db_testing');
  }

  /** @test */
  public function findRelations_method_returns_null_when_the_given_name_does_not_has_column_name()
  {
    $this->assertNull(
      self::$mysql->findRelations('id')
    );
  }

  /** @test */
  public function getPrimary_method_returns_primary_keys_of_the_given_table_as_an_array()
  {
    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT, username VARCHAR(255)';
    });

    $this->createTable('roles', function () {
      return 'name VARCHAR(255)';
    });

    $this->assertSame(
      ['id'],
      self::$mysql->getPrimary('users')
    );

    $this->assertSame(
      [],
      self::$mysql->getPrimary('roles')
    );
  }

  /** @test */
  public function getUniquePrimary_method_returns_the_unique_primary_key_of_the_given_table_as_string()
  {
    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT, username VARCHAR(255)';
    });

    $this->createTable('roles', function () {
      return 'name VARCHAR(255)';
    });

    $this->assertSame(
      'id',
      self::$mysql->getUniquePrimary('users')
    );

    $this->assertNull(
      self::$mysql->getUniquePrimary('roles')
    );
  }

  /** @test */
  public function getUniqueKeys_method_returns_the_unique_keys_of_the_given_table_as_an_array()
  {
    $this->createTable('users', function () {
      return 'username VARCHAR(22) NOT NULL, 
      email VARCHAR(100) NOT NULL,
      name VARCHAR(200) NOT NULL,
      UNIQUE INDEX (email)';
    });

    $this->assertSame(
      ['email'],
      self::$mysql->getUniqueKeys('users')
    );

    $this->createTable('users', function () {
      return 'username VARCHAR(22) NOT NULL, 
      email VARCHAR(100) NOT NULL,
      name VARCHAR(200) NOT NULL,
      UNIQUE INDEX (username, email)';
    });

    $this->assertSame(
      ['username', 'email'],
      self::$mysql->getUniqueKeys('users')
    );
  }

  /** @test */
  public function setLastInsertId_method_changes_the_value_of_last_inserted_id_for_the_given_id()
  {
    $this->setNonPublicPropertyValue('id_just_inserted', 22);
    $this->setNonPublicPropertyValue('last_insert_id', 22);

    $result = self::$mysql->setLastInsertId(44);

    $this->assertSame(
      44,
      $this->getNonPublicProperty('last_insert_id')
    );

    $this->assertSame(
      44,
      $this->getNonPublicProperty('id_just_inserted')
    );

    $this->assertInstanceOf(Mysql::class, $result);
  }

  /** @test */
  public function setLastInsertId_method_does_not_change_the_value_of_last_inserted_id_if_no_insert_query_performed()
  {
    self::$mysql->setLastInsertId();

    $this->assertNull(
      $this->getNonPublicProperty('id_just_inserted')
    );

    $this->assertSame(
      0,
      $this->getNonPublicProperty('last_insert_id')
    );
  }

  /** @test */
  public function setLastInsertId_method_changes_the_value_of_last_inserted_id_from_last_insert_query()
  {
    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT, email VARCHAR(25)';
    });

    $this->getNonPublicProperty('pdo')
      ->query("INSERT INTO users SET email = 'mail@test.com'");

    self::$mysql->setLastInsertId();

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
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT, email VARCHAR(25)';
    });

    $this->insertOne('users', ['email' => 'mail@test.com']);

    self::$mysql->setLastInsertId();

    $this->assertSame(
      333,
      $this->getNonPublicProperty('last_insert_id')
    );
  }

  /** @test */
  public function lastId_method_returns_the_last_inserted_id()
  {
    $this->setNonPublicPropertyValue('last_insert_id', 234);

    $this->assertSame(234, self::$mysql->lastId());

    $this->setNonPublicPropertyValue(
      'last_insert_id',
      hex2bin('7f4a2c70bcac11eba47652540000cfaa')
    );

    $this->assertSame(
      '7f4a2c70bcac11eba47652540000cfaa',
      self::$mysql->lastId()
    );

    $this->setNonPublicPropertyValue('last_insert_id', null);

    $this->assertFalse(self::$mysql->lastId());
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
      self::$mysql->last()
    );
  }

  /** @test */
  public function countQueries_method_returns_the_count_of_queries()
  {
    $this->setNonPublicPropertyValue('queries', ['foo' => 'bar', 'bar' => 'foo']);

    $this->assertSame(2, self::$mysql->countQueries());
  }

  /** @test */
  public function flush_method_deletes_all_the_recorded_queries_and_returns_their_count()
  {
    $this->setNonPublicPropertyValue('queries', ['foo' => 'bar', 'bar' => 'foo']);

    $result = self::$mysql->flush();

    $this->assertSame([], $this->getNonPublicProperty('queries'));
    $this->assertSame([], $this->getNonPublicProperty('list_queries'));

    $this->assertSame(2, $result);
  }

  /** @test */
  public function getOne_method_executes_the_given_query_and_extracts_the_first_column_result()
  {
    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT, 
              username VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'foo'],
      ['username' => 'bar'],
    ]);

    $this->assertSame(
      'foo',
      self::$mysql->getOne("SELECT username FROM users WHERE id = ?", 1)
    );
  }

  /** @test */
  public function getOne_method_returns_false_when_query_returns_false()
  {
    $this->assertFalse(
      self::$mysql->getOne('SELECT username FROM users WHERE id = ?', 1)
    );
  }

  /** @test */
  public function getKeyVal_method_returns_an_array_indexed_with_the_first_field_of_the_request()
  {
    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT, 
              username VARCHAR(255), 
              email VARCHAR(255), 
              name VARCHAR(255)';
    });

    $this->assertEmpty(
      self::$mysql->getKeyVal('SELECT * FROM users')
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
      self::$mysql->getKeyVal('SELECT username, name, email FROM users')
    );

    $this->assertSame(
      [
        'jdoe' => [
          'name'  => 'John Doe',
          'email' => 'jdoe@mail.com'
        ]
      ],
      self::$mysql->getKeyVal('SELECT username, name, email FROM users WHERE id = ?', 1)
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
      self::$mysql->getKeyVal('SELECT * FROM users')
    );
  }

  /** @test */
  public function getKeyVal_method_returns_null_when_query_returns_false()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('query')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $mysql->getKeyVal('SELECT * FROM users')
    );
  }

  /** @test */
  public function getColArray_method_returns_an_array_of_the_values_of_single_field_as_result_from_query()
  {
    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT, 
              username VARCHAR(255)';
    });

    $this->assertEmpty(
      self::$mysql->getColArray('SELECT id FROM users')
    );

    $this->insertMany('users', [
      ['username' => 'jdoe'],
      ['username' => 'sdoe'],
    ]);

    $this->assertSame(
      [1, 2],
      self::$mysql->getColArray('SELECT id FROM users')
    );

    $this->assertSame(
      [1, 2],
      self::$mysql->getColArray('SELECT id, username FROM users')
    );

    $this->assertSame(
      [1, 2],
      self::$mysql->getColArray('SELECT * FROM users')
    );

    $this->assertSame(
      ['jdoe', 'sdoe'],
      self::$mysql->getColArray('SELECT username FROM users')
    );

    $this->assertSame(
      ['jdoe', 'sdoe'],
      self::$mysql->getColArray('SELECT username, id FROM users')
    );
  }

  /** @test */
  public function select_method_returns_the_first_row_resulting_from_query_as_an_object()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT, 
              username VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'jdoe'],
      ['username' => 'sdoe'],
    ]);

    $result = self::$mysql->select('users', ['id', 'username']);

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('id', $result);
    $this->assertSame(1, $result->id);
    $this->assertObjectHasAttribute('username', $result);
    $this->assertSame('jdoe', $result->username);

    $result = self::$mysql->select('users', 'id');

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('id', $result);
    $this->assertSame(1, $result->id);
    $this->assertObjectNotHasAttribute('username', $result);

    $result = self::$mysql->select('users', [], ['id'], ['id' => 'DESC']);

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('id', $result);
    $this->assertSame(2, $result->id);
    $this->assertObjectHasAttribute('username', $result);
    $this->assertSame('sdoe', $result->username);

    $result = self::$mysql->select('users', [], ['id'], ['id' => 'ASC'], 1);

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('id', $result);
    $this->assertSame(2, $result->id);
    $this->assertObjectHasAttribute('username', $result);
    $this->assertSame('sdoe', $result->username);

    $this->assertNull(
      self::$mysql->select('users', [], ['id' => 33])
    );

    $this->assertNull(
      self::$mysql->select('users', [], [], [], 3)
    );
  }

  /** @test */
  public function selectAll_method_returns_table_rows_resulting_from_query_as_an_array_of_objects()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT, 
              username VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'jdoe'],
      ['username' => 'sdoe'],
    ]);

    $result = self::$mysql->selectAll('users', []);

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

    $result = self::$mysql->selectAll('users', 'username', [], ['id' => 'DESC']);

    $this->assertIsArray($result);
    $this->assertCount(2, $result);

    $this->assertIsObject($result[0]);
    $this->assertObjectHasAttribute('username', $result[0]);
    $this->assertSame('sdoe', $result[0]->username);

    $this->assertIsObject($result[1]);
    $this->assertObjectHasAttribute('username', $result[1]);
    $this->assertSame('jdoe', $result[1]->username);

    $result = self::$mysql->selectAll('users', 'username', [], ['id' => 'DESC'], 1);

    $this->assertIsArray($result);
    $this->assertCount(1, $result);

    $this->assertIsObject($result[0]);
    $this->assertObjectHasAttribute('username', $result[0]);
    $this->assertSame('sdoe', $result[0]->username);

    $this->assertSame(
      [],
      self::$mysql->selectAll('users', [], ['id' => 33])
    );

    $this->assertSame(
      [],
      self::$mysql->selectAll('users', [], [], [], 1, 3)
    );
  }

  /** @test */
  public function selectAll_method_returns_null_when_exec_method_returns_false()
  {
    $mysql = \Mockery::mock(Mysql::class)
      ->shouldAllowMockingProtectedMethods()
      ->makePartial();

    $mysql->shouldReceive('_exec')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $mysql->selectAll('user', [])
    );
  }

  /** @test */
  public function iselect_method_returns_the_first_row_resulting_from_query_as_numeric_array()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              username VARCHAR(255) UNIQUE,
              name VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'jdoe', 'name' => 'John Doe'],
      ['username' => 'sdoe', 'name' => 'Smith Doe'],
    ]);

    $this->assertSame(
      [1, 'jdoe', 'John Doe'],
      self::$mysql->iselect('users', [])
    );

    $this->assertSame(
      ['jdoe'],
      self::$mysql->iselect('users', 'username')
    );

    $this->assertSame(
      [1, 'jdoe'],
      self::$mysql->iselect('users', ['id', 'username'])
    );

    $this->assertSame(
      [2, 'sdoe'],
      self::$mysql->iselect('users', ['id', 'username'], [], [], 1)
    );

    $this->assertSame(
      [2, 'sdoe'],
      self::$mysql->iselect('users', ['id', 'username'], [],['id' => 'DESC'])
    );

    $this->assertNull(
      self::$mysql->iselect('users', [], ['id' => 44])
    );
  }

  /** @test */
  public function iselectAll_method_returns_all_results_from_query_as_an_array_of_numeric_arrays()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
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
      self::$mysql->iselectAll('users', [])
    );

    $this->assertSame(
      [
        ['jdoe'],
        ['sdoe']
      ],
      self::$mysql->iselectAll('users', 'username')
    );

    $this->assertSame(
      [
        [1, 'John Doe'],
        [2, 'Smith Doe']
      ],
      self::$mysql->iselectAll('users', ['id', 'name'])
    );

    $this->assertSame(
      [
        [2, 'Smith Doe']
      ],
      self::$mysql->iselectAll('users', ['id', 'name'], ['id' => 2])
    );

    $this->assertSame(
      [
        [2, 'Smith Doe'],
        [1, 'John Doe']
      ],
      self::$mysql->iselectAll('users', ['id', 'name'], [], ['id' => 'DESC'])
    );

    $this->assertSame(
      [
        [2, 'Smith Doe']
      ],
      self::$mysql->iselectAll('users', ['id', 'name'], [], ['id' => 'DESC'], 1)
    );

    $this->assertEmpty(
      self::$mysql->iselectAll('users', [], ['id' => 11])
    );
  }

  /** @test */
  public function iselectAll_method_returns_null_when_exec_function_returns_false()
  {
    $mysql = \Mockery::mock(Mysql::class)
      ->shouldAllowMockingProtectedMethods()
      ->makePartial();

    $mysql->shouldReceive('_exec')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $mysql->iselectAll('users', [])
    );
  }

  /** @test */
  public function rselect_method_returns_the_first_row_resulting_from_the_query_as_indexed_array()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              username VARCHAR(255) UNIQUE,
              name VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'jdoe', 'name' => 'John Doe'],
      ['username' => 'sdoe', 'name' => 'Smith Doe']
    ]);

    $this->assertSame(
      ['id' => 1, 'username' => 'jdoe', 'name' => 'John Doe'],
      self::$mysql->rselect('users', [])
    );

    $this->assertSame(
      ['id' => 2, 'username' => 'sdoe', 'name' => 'Smith Doe'],
      self::$mysql->rselect('users', [], ['id' => 2])
    );

    $this->assertSame(
      ['username' => 'sdoe'],
      self::$mysql->rselect('users', 'username', [], ['id' => 'DESC'])
    );

    $this->assertSame(
      ['id' => 2, 'username' => 'sdoe'],
      self::$mysql->rselect('users', ['id', 'username'], [], [], 1)
    );

    $this->assertNull(
      self::$mysql->rselect('users', ['id', 'username'], [], [], 3)
    );

    $this->assertNull(
      self::$mysql->rselect('users', ['id', 'username'], ['id' => 33])
    );
  }

  /** @test */
  public function rselectAll_method_returns_query_results_as_an_array_of_indexed_arrays()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
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
      self::$mysql->rselectAll('users', [])
    );

    $this->assertSame(
      [
        [ 'username' => 'jdoe'],
        ['username' => 'sdoe']
      ],
      self::$mysql->rselectAll('users', 'username')
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
      self::$mysql->rselectAll('users', ['id', 'name'], [], ['id' => 'DESC'])
    );

    $this->assertSame(
      [
        [
          'id' => 2,
          'name' => 'Smith Doe'
        ]
      ],
      self::$mysql->rselectAll('users', ['id', 'name'], [], [], 1, 1)
    );

    $this->assertEmpty(
      self::$mysql->rselectAll('users', [], ['id' => 44])
    );

    $this->assertEmpty(
      self::$mysql->rselectAll('users', [], [], [], 1, 33)
    );

    $this->createTable('test', function () {
      return 'created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              cfg JSON';
    });

    $this->insertMany('test',[
      $expected = [
        'created_at' => date('Y-m-d H:i:s', strtotime('-2 HOUR')),
        'cfg' => json_encode(['timeout' => 60])
      ],
      [
        'cfg' => json_encode(['timeout' => 120])
      ],
    ]);

    $this->assertSame(
      [$expected],
      self::$mysql->rselectAll([
        'table' => 'test',
        'fields' => [],
        'where' => [
          'conditions' => [[
            'field' => 'NOW()',
            'operator' => '>',
            'exp' => "DATE_ADD(created_at, INTERVAL cfg->'$.timeout' SECOND)"
          ]]
        ],
        'order' => [[
          'field' => 'priority',
          'dir' => 'ASC'
        ], [
          'field' => 'next',
          'dir' => 'ASC'
        ]]
      ])
    );
  }

  /** @test */
  public function selectOne_method_returns_a_single_value_from_the_given_field_name()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              username VARCHAR(255) UNIQUE,
              name VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'jdoe', 'name' => 'John Doe'],
      ['username' => 'sdoe', 'name' => 'Smith Doe']
    ]);

    $this->assertSame(
      'jdoe',
      self::$mysql->selectOne('users', 'username')
    );

    $this->assertSame(
      1,
      self::$mysql->selectOne('users')
    );

    $this->assertSame(
      'Smith Doe',
      self::$mysql->selectOne('users', 'name', ['id' => 2])
    );

    $this->assertSame(
      'Smith Doe',
      self::$mysql->selectOne('users', 'name', [], ['id' => 'DESC'])
    );

    $this->assertSame(
      'Smith Doe',
      self::$mysql->selectOne('users', 'name', [], [], 1)
    );

    $this->assertFalse(
      self::$mysql->selectOne('users', 'username', ['id' => 333])
    );

    $this->assertFalse(
      self::$mysql->selectOne('users', 'username', [], [], 44)
    );
  }

  /** @test */
  public function count_method_returns_the_number_of_records_in_the_table_for_the_given_arguments()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              username VARCHAR(25) NOT NULL UNIQUE';
    });

    $this->insertMany('users',[
      ['username' => 'jdoe'], ['username' => 'sdoe']
    ]);

    $this->assertSame(2, self::$mysql->count('users'));
    $this->assertSame(1, self::$mysql->count('users', ['username' => 'jdoe']));
    $this->assertSame(0, self::$mysql->count('users', ['id' => 22]));

    $this->assertSame(1, self::$mysql->count([
      'table' => ['users'],
      'where' => ['username' => 'sdoe']
    ]));

    $this->assertSame(1, self::$mysql->count([
      'tables' => ['users'],
      'where' => ['username' => 'sdoe']
    ]));

    $this->assertSame(2, self::$mysql->count([
      'tables' => ['users']
    ]));

    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT, 
              role_id INT(11) DEFAULT NULL';
    });

    $this->insertMany('users',[
      ['id' => 1, 'role_id' => 12],
      ['id' => 2,'role_id' => null],
      ['id' => 3,'role_id' => null]
    ]);

    $this->assertSame(1, self::$mysql->count('users', [
      ['role_id' => 'isnotnull'],
      'id' => 2
    ]));

    $this->assertSame(1, self::$mysql->count('users', [
      ['id' => 2],
      ['role_id', 'isnotnull']
    ]));

    $this->assertSame(1, self::$mysql->count('users', [
      ['role_id', 'isnotnull'],
      ['id' => 2]
    ]));

    // This does not work
    $this->assertSame(0, self::$mysql->count('users', [
      ['id', '>=', 2],
      ['role_id', 'isnotnull']
    ]));

    // Also this does not work
    $this->assertSame(0, self::$mysql->count('users', [
      'id' => 2,
      ['role_id', 'isnotnull']
    ]));

    // Also this does not work
    $this->assertSame(3, self::$mysql->count('users', [
      ['role_id' => 'isnotnull'],
      ['id' => 2]
    ]));
  }

  /** @test */
  public function count_method_returns_null_when_exec_returns_non_object()
  {
    $mysql = \Mockery::mock(Mysql::class)
      ->makePartial()
      ->shouldAllowMockingProtectedMethods();

    $mysql->shouldReceive('_exec')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $mysql->count('users')
    );
  }

  /** @test */
  public function selectAllByKeys_method_returns_an_array_indexed_with_the_first_field_of_the_request()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
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
      self::$mysql->selectAllByKeys('users')
    );

    $this->assertSame(
      [
        'jdoe' => ['id' => 1, 'name' => 'John Doe'],
        'sdoe' => ['id' => 2, 'name' => 'Smith Doe']
      ],
      self::$mysql->selectAllByKeys('users', ['username', 'id', 'name'])
    );

    $this->assertSame(
      [
        'sdoe' => ['id' => 2, 'name' => 'Smith Doe'],
        'jdoe' => ['id' => 1, 'name' => 'John Doe']
      ],
      self::$mysql->selectAllByKeys('users', ['username', 'id', 'name'], [], ['id' => 'DESC'])
    );

    $this->assertSame(
      [
        'sdoe' => ['id' => 2, 'name' => 'Smith Doe']
      ],
      self::$mysql->selectAllByKeys('users', ['username', 'id', 'name'], ['id' => '2'])
    );

    $this->assertSame(
      [
        'sdoe' => ['id' => 2, 'name' => 'Smith Doe']
      ],
      self::$mysql->selectAllByKeys('users', ['username', 'id', 'name'], [], [], 1, 1)
    );

    $this->assertEmpty(
      self::$mysql->selectAllByKeys('users', [], ['id' => 33])
    );

    $this->assertEmpty(
      self::$mysql->selectAllByKeys('users', [], [], [], 1, 33)
    );

    $this->assertSame(
      [
        'jdoe' => ['t_id' => 1, 't_name' => 'John Doe'],
        'sdoe' => ['t_id' => 2, 't_name' => 'Smith Doe']
      ],
      self::$mysql->selectAllByKeys([
        'tables' => ['users'],
        'fields' => ['t_username' => 'username', 't_id' => 'id', 't_name' => 'name']
      ])
    );

    $this->assertSame(
      [
        'jdoe' => 1,
        'sdoe' => 2
      ],
      self::$mysql->selectAllByKeys([
        'tables' => ['users'],
        'fields' => ['t_username' => 'username', 't_id' => 'id']
      ])
    );
  }

  /** @test */
  public function selectAllByKeys_method_returns_null_when_no_results_found_and_check_returns_false()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT(11)';
    });

    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull(
      self::$mysql->selectAllByKeys('users')
    );
  }

  /** @test */
  public function stat_method_returns_an_array_with_the_count_of_values_resulting_from_the_query()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
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
      self::$mysql->stat('users', 'name')
    );

    $this->assertSame(
      [
        ['name' => 'Smith Doe', 'num' => 1],
        ['name' => 'John Doe', 'num' => 3]
      ],
      self::$mysql->stat('users', 'name', [], ['name' => 'DESC'])
    );

    $this->assertSame(
      [
        ['name' => 'John Doe', 'num' => 3]
      ],
      self::$mysql->stat('users', 'name', ['name' => 'John Doe'])
    );
  }

  /** @test */
  public function stat_method_returns_null_when_check_method_returns_null()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull(self::$mysql->stat('users', 'name'));
  }

  /** @test */
  public function countFieldValues_method_returns_count_of_identical_values_in_a_field_as_array()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
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
        ['val' => 'John Doe', 'num' => 3],
        ['val' => 'Smith Doe', 'num' => 1]
      ],
      self::$mysql->countFieldValues('users', 'name')
    );

    $this->assertSame(
      [
        ['val' => 'John Doe', 'num' => 3],
        ['val' => 'Smith Doe', 'num' => 1]
      ],
      self::$mysql->countFieldValues([
        'table' => 'users',
        'fields' => ['name']
      ])
    );

    $this->assertSame(
      [
        ['val' => 'Smith Doe', 'num' => 1],
        ['val' => 'John Doe', 'num' => 3]
      ],
      self::$mysql->countFieldValues('users', 'name', [], ['name' => 'DESC'])
    );

    $this->assertSame(
      [
        ['val' => 'John Doe', 'num' => 3]
      ],
      self::$mysql->countFieldValues('users', 'name', ['name' => 'John Doe'])
    );

    $this->assertEmpty(
      self::$mysql->countFieldValues('users', 'name', ['name' => 'foo'])
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
      ['foo', 'foo2', 'foo3', 'foo4'],
      self::$mysql->getColumnValues('users', 'username')
    );

    $this->assertSame(
      ['foo', 'foo2', 'foo3', 'foo4'],
      self::$mysql->getColumnValues([
        'table' => ['users'],
        'fields' => ['username']
      ])
    );

    $this->assertSame(
      ['foo'],
      self::$mysql->getColumnValues('users', 'username', ['username' => 'foo'])
    );

    $this->assertSame(
      ['foo'],
      self::$mysql->getColumnValues('users', 'DISTINCT username', ['username' => 'foo'])
    );

    $this->assertSame(
      ['foo4', 'foo3', 'foo2', 'foo'],
      self::$mysql->getColumnValues('users', 'username', [], ['username' => 'DESC'])
    );

    $this->assertSame(
      ['foo2'],
      self::$mysql->getColumnValues('users', 'username', [], [], 1, 1)
    );

    $this->assertEmpty(
      self::$mysql->getColumnValues('users', 'username', ['username' => 'bar'])
    );

    $this->assertEmpty(
      self::$mysql->getColumnValues('users', 'username', [], [], 1, 44)
    );
  }

  /** @test */
  public function getColumnValues_returns_null_when_check_method_returns_false()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull(self::$mysql->getColumnValues('users', 'username'));
  }

  /** @test */
  public function insert_method_inserts_values_in_the_given_table_and_returns_affected_rows()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'name VARCHAR(20), email VARCHAR(20) UNIQUE';
    });

    $this->assertSame(
      2,
      self::$mysql->insert('users', [
        ['name' => 'John', 'email' => 'john@mail.com'],
        ['name' => 'Smith', 'email' => 'smith@mail.com'],
        ['name' => 'Smith', 'email' => 'smith@mail.com'],
      ], true)
    );


    $this->assertDatabaseHas('users', 'email', 'john@mail.com');
    $this->assertDatabaseHas('users', 'email', 'smith@mail.com');

    self::$connection->query('DELETE FROM users');



    $this->assertSame(
      2,
      self::$mysql->insert([
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

    self::$mysql->insert('');
  }


  /** @test */
  public function insertUpdate_method_inserts_rows_in_the_given_table_if_not_exists_otherwise_update()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'name VARCHAR(20), email VARCHAR(20) UNIQUE';
    });


    $this->assertSame(
      3,
      self::$mysql->insertUpdate('users', [
        ['name' => 'John', 'email' => 'john@mail.com'],
        ['name' => 'Smith', 'email' => 'smith@mail.com'],
        ['name' => 'Smith2', 'email' => 'smith@mail.com']
      ])
    );

    $this->assertDatabaseHas('users', 'email', 'smith@mail.com');
    $this->assertDatabaseHas('users', 'name', 'Smith2');
    $this->assertDatabaseDoesNotHave('users', 'name', 'Smith');

    self::$connection->query("DELETE FROM users");

    $this->assertSame(
      3,
      self::$mysql->insertUpdate([
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
      return 'username VARCHAR(20) UNIQUE, name VARCHAR(20)';
    });

    $this->insertOne('users', ['username' => 'jdoe', 'name' => 'John']);

    $this->assertSame(
      1,
      self::$mysql->update('users', ['name' => 'Smith'], ['username' => 'jdoe'])
    );

    $this->assertDatabaseHas('users', 'name', 'Smith');
    $this->assertDatabaseDoesNotHave('users', 'name', 'John');

    $this->assertSame(
      0,
      self::$mysql->update('users', ['name' => 'Smith'], ['username' => 'jdoe'], true)
    );

    self::$connection->query('DELETE FROM users');

    $this->insertOne('users', ['username' => 'jdoe', 'name' => 'John']);

    $this->assertSame(
      1,
      self::$mysql->update([
        'tables' => ['users'],
        'where'  => ['username' => 'jdoe'],
        'fields' => ['name'=> 'Smith']
      ])
    );

    $this->assertDatabaseHas('users', 'name', 'Smith');
    $this->assertDatabaseDoesNotHave('users', 'name', 'John2');

    $this->assertSame(
      0,
      self::$mysql->update([
        'tables' => ['users'],
        'where'  => ['username' => 'jdoe'],
        'fields' => ['name'=> 'Smith'],
        'ignore' => true
      ])
    );
  }

  /** @test */
  public function delete_method_deletes_rows_from_the_given_table()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'username VARCHAR(20) UNIQUE, name VARCHAR(20)';
    });

    $this->insertOne('users', ['username' => 'jdoe', 'name' => 'John']);

    $this->assertSame(
      1,
      self::$mysql->delete('users', ['username' => 'jdoe'])
    );

    $this->assertDatabaseDoesNotHave('users', 'name', 'John');

    $this->assertSame(
      0,
      self::$mysql->delete('users', ['username' => 'jdoe'])
    );

    $this->insertOne('users', ['username' => 'sdoe', 'name' => 'Smith']);

    $this->assertSame(
      1,
      self::$mysql->delete([
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
      self::$mysql->fetch('SELECT * FROM users')
    );

    $this->insertMany('users', [
      ['name' => 'John', 'email' => 'john@mail.com'],
      ['name' => 'Smith', 'email' => 'smith@mail.com'],
    ]);

    $this->assertSame(
      ['name' => 'John', 'John', 'email' => 'john@mail.com', 'john@mail.com'],
      self::$mysql->fetch('SELECT * FROM users')
    );

    $this->assertSame(
      ['email' => 'smith@mail.com', 'smith@mail.com'],
      self::$mysql->fetch('SELECT email FROM users WHERE name = ?', 'Smith')
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
      self::$mysql->fetchAll('SELECT * FROM users')
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
      self::$mysql->fetchAll('SELECT * FROM users ORDER BY name DESC')
    );

    $this->assertSame(
      [
        ['name' => 'Smith', 'Smith']
      ],
      self::$mysql->fetchAll('SELECT name FROM users WHERE email = "smith@mail.com"')
    );
  }

  /** @test */
  public function fetchAll_method_returns_false_when_query_method_returns_false()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('query')
      ->once()
      ->andReturnFalse();

    $this->assertFalse(
      $mysql->fetchAll('SELECT * FROM users')
    );
  }

  /** @test */
  public function fetchColumn_method_returns_a_single_column_from_the_next_row_of_result_set()
  {
    $this->createTable('users', function () {
      return 'name VARCHAR(255), email VARCHAR(255)';
    });

    $this->assertFalse(
      self::$mysql->fetchColumn('SELECT * FROM users')
    );

    $this->insertMany('users', [
      ['name' => 'John', 'email'=> 'john@mail.com'],
      ['name' => 'Smith', 'email' => 'smith@mail.com']
    ]);

    $this->assertSame(
      'John',
      self::$mysql->fetchColumn('SELECT * FROM users')
    );

    $this->assertSame(
      'john@mail.com',
      self::$mysql->fetchColumn('SELECT * FROM users', 1)
    );

    $this->assertSame(
      'smith@mail.com',
      self::$mysql->fetchColumn('SELECT * FROM users WHERE name = ?', 1, 'Smith')
    );
  }

  /** @test */
  public function fetchObject_method_returns_the_first_result_from_query_as_object_and_false_if_no_results()
  {
    $this->createTable('users', function () {
      return 'name VARCHAR(255), email VARCHAR(255)';
    });

    $this->assertFalse(
      self::$mysql->fetchObject('SELECT * FROM users')
    );

    $this->insertMany('users', [
      ['name' => 'John', 'email'=> 'john@mail.com'],
      ['name' => 'Smith', 'email' => 'smith@mail.com']
    ]);

    $result = self::$mysql->fetchObject('SELECT * FROM users');

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('name', $result);
    $this->assertObjectHasAttribute('email', $result);
    $this->assertSame('John', $result->name);
    $this->assertSame('john@mail.com', $result->email);

    $result = self::$mysql->fetchObject('SELECT * FROM users ORDER BY name DESC');

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('name', $result);
    $this->assertObjectHasAttribute('email', $result);
    $this->assertSame('Smith', $result->name);
    $this->assertSame('smith@mail.com', $result->email);
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
          self::getDbConfig()['db'] . '.users' => [
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
      $method->invoke(self::$mysql, $cfg)
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
          self::getDbConfig()['db'] . '.users' => [
            function ($cfg) {
              return [];
            }
          ]
        ]
      ]
    ]);

    $this->assertSame(
      array_merge($cfg, ['trig' => false, 'run' => false]),
      $method->invoke(self::$mysql, $cfg)
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
          self::getDbConfig()['db'] . '.roles' => [
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
      $method->invoke(self::$mysql, $cfg)
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
          self::getDbConfig()['db'] . '.users' => [
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
      $method->invoke(self::$mysql, $cfg)
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
      $method->invoke(self::$mysql, $cfg)
    );
  }

  /** @test */
  public function trigger_method_returns_the_config_array_as_is_if_triggers_is_disabled_and_moment_is_after()
  {
    $this->setNonPublicPropertyValue('_triggers_disabled', true);

    $method = $this->getNonPublicMethod('_trigger');

    $this->assertSame(
      ['moment' => 'after'],
      $method->invoke(self::$mysql, ['moment' => 'after'])
    );
  }

  /** @test */
  public function trigger_method_returns_the_config_array_adding_trig_and_run_when_triggers_is_disabled_and_moment_is_before()
  {
    $this->setNonPublicPropertyValue('_triggers_disabled', true);

    $method = $this->getNonPublicMethod('_trigger');

    $this->assertSame(
      ['moment' => 'before', 'run' => 1, 'trig' => 1],
      $method->invoke(self::$mysql, ['moment' => 'before'])
    );
  }

  /** @test */
  public function add_kind_method_adds_the_given_type_to_the_given_args()
  {
    $method = $this->getNonPublicMethod('_add_kind');

    $this->assertSame(
      ['UPDATE','foo'],
      $method->invoke(self::$mysql, ['foo'], 'update')
    );

    $this->assertSame(
      [['foo', 'kind' => 'SELECT']],
      $method->invoke(self::$mysql, [['foo']])
    );

    $this->assertNull(
      $method->invoke(self::$mysql, ['foo' => ['bar']])
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

    $method->invokeArgs(self::$mysql, [&$cfg]);

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

    $method->invokeArgs(self::$mysql, [&$cfg2]);

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

    $method->invokeArgs(self::$mysql, [&$cfg3]);

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

    $method->invokeArgs(self::$mysql, [&$cfg]);

    $this->assertSame($old_cfg, $cfg);

    $cfg = $old_cfg = [
      'primary'      => 'id',
      'primary_type' => 'binary',
      'auto_increment' => false,
      'fields' => ['email', 'name'],
      'values' => ['John']
    ];

    $method->invokeArgs(self::$mysql, [&$cfg]);

    $this->assertSame($old_cfg, $cfg);

    $cfg = $old_cfg = [
      'primary'      => 'id',
      'primary_type' => 'binary',
      'auto_increment' => true,
      'fields' => ['id', 'name'],
      'values' => ['John']
    ];

    $method->invokeArgs(self::$mysql, [&$cfg]);

    $this->assertSame($old_cfg, $cfg);

    $cfg = $old_cfg = [
      'primary'      => '',
      'primary_type' => 'binary',
      'auto_increment' => false,
      'fields' => ['id', 'name'],
      'values' => ['John']
    ];

    $method->invokeArgs(self::$mysql, [&$cfg]);

    $this->assertSame($old_cfg, $cfg);

    $cfg = $old_cfg = [
      'primary_type' => 'binary',
      'auto_increment' => false,
      'fields' => ['id', 'name'],
      'values' => ['John']
    ];

    $method->invokeArgs(self::$mysql, [&$cfg]);

    $this->assertSame($old_cfg, $cfg);
  }

  /** @test */
  public function exec_method_insert_test()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id BINARY(16) PRIMARY KEY,
              email VARCHAR(25) NOT NULL UNIQUE,
              name VARCHAR(25) NOT NULL';
    });

    $cfg = [
      'tables'  => 'users',
      'kind'    => 'INSERT',
      'fields'  => ['email' => 'john@mail.com', 'name' => 'John']
    ];

    $method = $this->getNonPublicMethod('_exec');

    $this->assertSame(1, $method->invoke(self::$mysql, $cfg));
    $this->assertDatabaseHas('users', 'email', 'john@mail.com');
    $this->assertDatabaseHas('users', 'name', 'John');

    $this->assertSame(
      bin2hex(
        self::$connection->query("SELECT id FROM users LIMIT 1")->fetchObject()->id
      ),
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
      return 'id INT(11) PRIMARY KEY,
              email VARCHAR(25) NOT NULL UNIQUE,
              name VARCHAR(25) NOT NULL';
    });

    $this->insertMany('users', [
      ['id' => 1,'email' => 'john@mail.com', 'name' => 'John'],
      ['id' => 2, 'email' => 'smith@mail.com', 'name' => 'Smith']
    ]);

    $cfg = [
      'tables'  => 'users',
      'kind'    => 'UPDATE',
      'fields'  => ['email' => 'john@mail.com', 'name' => 'John Doe'],
      'where'   => ['id' => 1]
    ];

    $method = $this->getNonPublicMethod('_exec');

    $this->assertSame(
      1,
      $method->invoke(self::$mysql, $cfg)
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
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              name VARCHAR(25)';
    });

    $this->insertMany('users', [
      ['name' => 'John'],
      ['name' => 'Sam']
    ]);

    $cfg = [
      'table' => ['users'],
      'kind'  => 'delete',
      'where' => ['id' => 1]
    ];

    $method = $this->getNonPublicMethod('_exec');

    $this->assertSame(
      1,
      $method->invoke(self::$mysql, $cfg
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
      return 'id BINARY(16) PRIMARY KEY,
              email VARCHAR(25) NOT NULL UNIQUE,
              name VARCHAR(25) NOT NULL';
    });

    $cfg = [
      'tables'  => 'users',
      'kind'    => 'INSERT',
      'fields'   => ['email', 'name']
    ];

    $method = $this->getNonPublicMethod('_exec');

    $method->invoke(self::$mysql, $cfg);
  }

  /** @test */
  public function exec_method_select_test()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY,
              email VARCHAR(25) NOT NULL UNIQUE,
              name VARCHAR(25) NOT NULL';
    });

    $this->insertMany('users', [
      ['id' => 1,'email' => 'john@mail.com', 'name' => 'John'],
      ['id' => 2, 'email' => 'smith@mail.com', 'name' => 'Smith']
    ]);

    $method = $this->getNonPublicMethod('_exec');

    $cfg = [
      'tables'  => 'users',
      'kind'    => 'SELECT',
      'fields'  => ['email', 'name']
    ];

    $result = $method->invoke(self::$mysql, $cfg);

    $this->assertInstanceOf(\PDOStatement::class, $result);

    $results = self::$mysql->fetchAllResults($result, \PDO::FETCH_ASSOC);

    $this->assertSame(
      [
        ['email' => 'john@mail.com', 'name' => 'John'],
        ['email' => 'smith@mail.com', 'name' => 'Smith']
      ],
      $results
    );
  }

  /** @test */
  public function exec_method_test_the_after_trigger_is_running()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id BINARY(16) PRIMARY KEY,
              email VARCHAR(25) NOT NULL UNIQUE,
              name VARCHAR(25) NOT NULL';
    });

    $this->setNonPublicPropertyValue('_triggers', [
      'INSERT' => [
        'after' => [
          self::getDbConfig()['db'] . '.users' => [
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
      $method->invoke(self::$mysql, $cfg)
    );
  }

  /** @test */
  public function exec_method_test_when_trigger_returns_empty_run()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY,
              email VARCHAR(25) NOT NULL UNIQUE,
              name VARCHAR(25) NOT NULL';
    });

    $this->setNonPublicPropertyValue('_triggers', [
      'INSERT' => [
        'before' => [
          self::getDbConfig()['db'] . '.users' => [
            function ($cfg) {
              return [];
            }
          ]
        ],
        'after' => [
          self::getDbConfig()['db'] . '.users' => [
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
      $method->invoke(self::$mysql, $cfg)
    );

    $this->assertDatabaseDoesNotHave('users', 'name', 'John');
    $this->assertDatabaseDoesNotHave('users', 'email', 'john@mail.com');
  }

  /** @test */
  public function exec_method_test_when_trigger_returns_empty_run_but_force_is_enabled()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY,
              email VARCHAR(25) NOT NULL UNIQUE,
              name VARCHAR(25) NOT NULL';
    });

    $this->setNonPublicPropertyValue('_triggers', [
      'INSERT' => [
        'before' => [
          self::getDbConfig()['db'] . '.users' => [
            function ($cfg) {
              return [];
            }
          ]
        ],
        'after' => [
          self::getDbConfig()['db'] . '.users' => [
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
      $method->invoke(self::$mysql, $cfg)
    );

    $this->assertDatabaseDoesNotHave('users', 'name', 'John');
    $this->assertDatabaseDoesNotHave('users', 'email', 'john@mail.com');
  }

  /** @test */
  public function exec_method_returns_null_when_sql_has_falsy_value_from_the_returned_config_from_processCfg_method()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $mysql->shouldReceive('processCfg')
      ->once()
      ->andReturn(['tables' => ['users'], 'sql' => '']);

    $this->assertNull(
      $this->getNonPublicMethod('_exec', $mysql)
      ->invoke($mysql)
    );
  }

  /** @test */
  public function exec_method_returns_null_when_processCfg_method_returns_nul()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $mysql->shouldReceive('processCfg')
      ->once()
      ->andReturnNull();

    $this->assertNull(
      $this->getNonPublicMethod('_exec', $mysql)
        ->invoke($mysql)
    );
  }

  /** @test */
  public function exec_method_returns_null_when_check_method_returns_false()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->getNonPublicMethod('_exec', $mysql)
        ->invoke($mysql)
    );
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
      'tables' => [self::getDbConfig()['db'] . ".users"],
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
    $result = $method->invoke(self::$mysql, $cfg);

    $this->assertArrayHasKey('hash', $result);
    unset($result['hash']);
    $this->assertSame($expected, $result);
  }

  /** @test */
  public function treat_arguments_method_sets_default_cfg_when_not_provided()
  {
    $result = $this->getNonPublicMethod('_treat_arguments')
      ->invoke(self::$mysql, ['tables' => ['users']]);

    $expected = [
      'kind' => 'SELECT',
      'fields' => [],
      'where' => [],
      'order' => [],
      'limit' => 0,
      'start' => 0,
      'group_by' => [],
      'having' => [],
      'tables' => [self::getDbConfig()['db'] . '.users'],
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
      'tables' => [self::getDbConfig()['db'] . '.users'],
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
      ->invoke(self::$mysql, $cfg);

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
      'tables' => [self::getDbConfig()['db'] . '.users'],
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
      ->invoke(self::$mysql, $cfg);

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
      'tables' => [self::getDbConfig()['db'] . '.users'],
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
      ->invoke(self::$mysql, $cfg);

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
      'tables' => [self::getDbConfig()['db'] . '.payments'],
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
      ->invoke(self::$mysql, $cfg);

    $this->assertArrayHasKey('hash', $result);
    unset($result['hash']);
    $this->assertSame($expected, $result);
  }

  /** @test */
  public function treat_arguments_throws_an_exceptions_if_table_is_not_provided()
  {
    $this->expectException(\Error::class);

    $this->getNonPublicMethod('_treat_arguments')
      ->invoke(self::$mysql, ['foo' => 'bar']);
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
        ->invoke(self::$mysql, $cfg)
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

    $method->invokeArgs(self::$mysql, [&$cfg]);

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

    $method->invokeArgs(self::$mysql, [&$cfg2]);

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

    $result = $method->invoke(self::$mysql, $cfg, $cfg['filters']);

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

    $result = $method->invoke(self::$mysql, $cfg, $cfg['filters']);

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
        ->invoke(self::$mysql, $cfg)
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
        ->invoke(self::$mysql, $cfg)
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
        ->invoke(self::$mysql, $cfg)
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
      ->invoke(self::$mysql, $stmt = 'SELECT * FROM users', $params = ['foo' => 'bar']);

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

    $this->assertInstanceOf(Mysql::class, $result);
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
      ->invoke(self::$mysql, $stmt = 'SELECT * FROM users', $params = ['foo' => 'bar']);

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

    $this->assertInstanceOf(Mysql::class, $result);
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
        ->invoke(self::$mysql, $cfg, 3)
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
        ->invoke(self::$mysql, $cfg, 6)
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
        ->invoke(self::$mysql, $cfg, 10)
    );
  }

  /** @test */
  public function retrieveQuery_method_retrieves_a_query_from_the_given_hash()
  {
    $this->setNonPublicPropertyValue('queries', [
      '12345' => ['foo' => 'bar'],
      '54321' => '12345'
    ]);

    $this->assertSame(['foo' => 'bar'], self::$mysql->retrieveQuery('12345'));
    $this->assertSame(['foo' => 'bar'], self::$mysql->retrieveQuery('54321'));
    $this->assertNull(self::$mysql->retrieveQuery('foo'));
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
      self::$mysql->extractFields($cfg, $conditions, $result)
    );

    $this->assertSame($expected, $result);

    $this->assertSame(
      $expected,
      self::$mysql->extractFields($cfg, $conditions['conditions'])
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
      self::$mysql->filterFilters($cfg, 'name')
    );

    $this->assertSame(
      [
        ['field' => 'name', 'operator' => '=', 'value' => 'John'],
        ['field' => 'name', 'operator' => '=', 'value' => 'Sam'],
      ],
      self::$mysql->filterFilters($cfg, 'name', '=')
    );

    $this->assertSame(
      [],
      self::$mysql->filterFilters($cfg, 'name', '!=')
    );

    $this->assertNull(
      self::$mysql->filterFilters(['table' => 'users'], 'name')
    );

    $this->assertSame(
      [],
      self::$mysql->filterFilters(['filters' => []], 'name')
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
    $this->assertSame(self::getDbConfig()['host'], self::$mysql->getHost());
  }

  /** @test */
  public function getCurrent_method_returns_the_current_database_of_the_current_connection()
  {
    $this->assertSame(self::getDbConfig()['db'], self::$mysql->getCurrent());
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
    $this->assertSame(self::getDbConfig()['db'], $this->getNonPublicProperty('current'));

    self::$connection->query('CREATE DATABASE IF NOT EXISTS bbn_test_2');

    $result = self::$mysql->change('bbn_test_2');

    $this->assertSame('bbn_test_2', $this->getNonPublicProperty('current'));
    $this->assertTrue($result);

    self::$mysql->change(self::getDbConfig()['db']);

    $this->dropDatabaseIfExists('bbn_test_2');
  }

  /** @test */
  public function change_method_does_not_change_the_database_if_language_object_fails_to_change()
  {
    $this->assertSame(self::getDbConfig()['db'], $this->getNonPublicProperty('current'));

    try {
      self::$mysql->change('bbn_test_3');
    } catch (\Exception $e) {

    }

    $this->assertSame(self::getDbConfig()['db'], $this->getNonPublicProperty('current'));
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

  /** @test */
  public function getColArray_method_return_an_array_with_the_values_of_single_field_resulting_from_the_query()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('getByColumns')
      ->once()
      ->with($query = 'SELECT id FROM table_users')
      ->andReturn([
        'name' => [
          'john', 'doe'
        ]
      ]);

    $this->assertSame(['john', 'doe'], $mysql->getColArray($query));
  }

  /** @test */
  public function getColArray_method_returns_an_empty_array_when_getByColumns_returns_null()
  {
    $mysql = \Mockery::mock(Mysql::class)->makePartial();

    $mysql->shouldReceive('getByColumns')
      ->once()
      ->with($query = 'SELECT id FROM table_users')
      ->andReturnNull();

    $this->assertSame([], $mysql->getColArray($query));
  }

  /** @test */
  public function query_method_executes_a_statement_and_returns_the_affected_rows_for_writing_statements()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function() {
      return 'name VARCHAR(255), username VARCHAR(255)';
    });

    $result = self::$mysql->query('INSERT INTO users SET name = ?, username = ?', 'John', 'jdoe');

    $this->assertSame(1, $result);
    $this->assertDatabaseHas('users', 'name', 'John');
    $this->assertDatabaseHas('users', 'username', 'jdoe');

    $queries = $this->getNonPublicProperty('queries');

    $this->assertIsArray($queries);
    $this->assertCount(1, $queries);

    $query = current($queries);

    $this->assertSame(
      'INSERT INTO users SET name = ?, username = ?',
      $query['sql']
    );
    $this->assertSame('INSERT', $query['kind']);

  }

  /** @test */
  public function query_method_executes_a_statement_and_returns_query_object_for_reading_statements()
  {
    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              name VARCHAR(50) NOT NULL';
    });

    $this->insertMany('users', [
      ['name' => 'John'],
      ['name' => 'Sam']
    ]);

    $result = self::$mysql->query('SELECT * FROM users WHERE id >= ?', 1);

    $this->assertInstanceOf(\PDOStatement::class, $result);
    $this->assertInstanceOf(Query::class, $result);

    $queries = $this->getNonPublicProperty('queries');

    $this->assertIsArray($queries);
    $this->assertCount(1, $queries);

    $query = current($queries);

    $this->assertSame(
      'SELECT * FROM users WHERE id >= ?',
      $query['sql']
    );
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

    $result = self::$mysql->query("SELECT username FROM users WHERE name = ?", 'John');

    $this->assertInstanceOf(\PDOStatement::class, $result);

    $this->assertSame(
      [['username' => 'jdoe']],
      self::$mysql->fetchAllResults($result, \PDO::FETCH_ASSOC)
    );

    $result2 = self::$mysql->query("SELECT username FROM users WHERE name = ?", 'Sam');

    $this->assertInstanceOf(\PDOStatement::class, $result2);

    $this->assertSame(
      [['username' => 'sdoe']],
      self::$mysql->fetchAllResults($result2, \PDO::FETCH_ASSOC)
    );

    self::$mysql->query("SELECT name FROM users WHERE username = ?", 'sdoe');

    $this->assertCount(
      2,
      $this->getNonPublicProperty('queries')
    );
  }

  /** @test */
  public function query_method_throws_an_exception_if_the_given_query_is_not_valid()
  {
    $this->expectException(\Exception::class);

    self::$mysql->query('foo');
  }

  /** @test */
  public function query_method_sets_an_error_if_the_given_arguments_are_greater_than_query_placeholders()
  {
    $this->expectException(\Exception::class);

    self::$mysql->setErrorMode(Errors::E_DIE);

    self::$mysql->query('SELECT * FROM user where id = ? AND user = ?', 1, 4, 5);
  }

  /** @test */
  public function query_method_fills_the_missing_values_with_the_last_given_one_when_number_of_values_are_smaller_than_query_placeholders()
  {
    $this->createTable('users', function() {
      return 'name VARCHAR(255), username VARCHAR(255)';
    });

    self::$mysql->query('INSERT INTO users SET name = ?, username = ?', 'John');

    $this->assertDatabaseHas('users', 'name', 'John');
    $this->assertDatabaseHas('users', 'username', 'John');
  }

  /** @test */
  public function add_query_method_adds_to_queries_list_form_the_given_hash_and_arguments()
  {
    $method = $this->getNonPublicMethod('_add_query');

    $method->invokeArgs(self::$mysql, [
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

    $method->invoke(self::$mysql, '123', ...$args);
    $method->invoke(self::$mysql, '1234', ...$args);
    $method->invoke(self::$mysql, '12345', ...$args);

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

    $method->invoke(self::$mysql, '123');
    $method->invoke(self::$mysql, '123456789');

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
      ->invoke(self::$mysql, '1234');

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
      ->invoke(self::$mysql, '1234');

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
      ->invoke(self::$mysql, '1234');
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
      ->invoke(self::$mysql, '123');
  }

  /** @test */
  public function get_cache_method_returns_table_structure_from_database_when_cache_does_not_exist_and_saves_it_in_cache_property()
  {
    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              email VARCHAR(255) NOT NULL UNIQUE';
    });

    $this->setCacheExpectations();

    $result = $this->getNonPublicMethod('_get_cache')
      ->invoke(self::$mysql, 'users');

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
          'unique' => 1,
        ],
        'email' => [
          'columns' => ['email'],
          'ref_db' => null,
          'ref_table' => null,
          'ref_column' => null,
          'constraint' => null,
          'update' => null,
          'delete' => null,
          'unique' => 1,
        ]
      ],
      'cols' => [
        'id' => ['PRIMARY'],
        'email' => ['email']
      ],
      'fields' => [
        'id' => [
          'position' => 1,
          'type' => 'int',
          'null' => 0,
          'key' => 'PRI',
          'extra' => 'auto_increment',
          'signed' => true,
          'virtual' => false,
          'generation' => ''
        ],
        'email' => [
          'position' => 2,
          'type' => 'varchar',
          'null' => 0,
          'key' => 'UNI',
          'extra' => '',
          'signed' => true,
          'virtual' => false,
          'generation' => '',
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
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT,
              email VARCHAR(255) NOT NULL UNIQUE';
    });

    $this->createTable('roles', function () {
      return 'id INT(11) PRIMARY KEY AUTO_INCREMENT';
    });

    $this->setCacheExpectations();

    $result = $this->getNonPublicMethod('_get_cache')
      ->invoke(self::$mysql, self::getDbConfig()['db'], 'tables');

    $this->assertSame($expected = ['roles', 'users'], $result);
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
      ->invoke(self::$mysql, '', 'databases');

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
      "{$db_config['engine']}/{$db_config['user']}@{$db_config['host']}/{$db_config['db']}/users"
       => [
         'foo' => 'bar'
      ]
    ]);

    $this->assertSame(
      ['foo' => 'bar'],
      $this->getNonPublicMethod('_get_cache')->invoke(
        self::$mysql, 'users'
      )
    );
  }

  /** @test */
  public function get_cache_method_returns_table_structure_from_cache_class_when_exists_and_does_not_exist_in_cache_property()
  {
    $cache_name = $this->getNonPublicMethod('_db_cache_name')
      ->invoke(self::$mysql, 'users', 'columns');

    $cache_name_method = $this->getNonPublicMethod('_cache_name');

    self::$cache_mock->shouldReceive('get')
      ->with(
        $cache_name_method->invoke(self::$mysql, $cache_name)
      )
      ->andReturn(['foo' => 'bar']);

    $result = $this->getNonPublicMethod('_get_cache')
      ->invoke(self::$mysql, 'users');

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
      return 'id INT(11)';
    });

    $db_config = self::getDbConfig();

    $this->setNonPublicPropertyValue('cache', [
      "{$db_config['engine']}/{$db_config['user']}@{$db_config['host']}/{$db_config['db']}/users"
      => [
        'foo' => 'bar'
      ]
    ]);

    $result = $this->getNonPublicMethod('_get_cache')
      ->invoke(self::$mysql, $db_config['db'], 'tables', true);

    $this->assertNotSame(['foo' => 'bar'], $result);
  }

  /** @test */
  public function get_cache_method_throws_an_exception_when_it_fails_to_retrieve_table_structure()
  {
    $this->expectException(\Exception::class);

    $this->setCacheExpectations();

    $this->getNonPublicMethod('_get_cache')
      ->invoke(self::$mysql, 'users');
  }

  /** @test */
  public function get_cache_method_throws_an_exception_when_it_fails_to_retrieve_tables_names()
  {
    $this->expectException(\Exception::class);

    $this->setCacheExpectations();

    $this->setNonPublicPropertyValue('current', null);

    $this->getNonPublicMethod('_get_cache')
      ->invoke(self::$mysql, self::getDbConfig()['db'], 'tables');
  }

  /** @test */
  public function get_cache_method_throws_an_exception_when_it_fails_to_retrieve_databases_names()
  {
    $this->expectException(\Exception::class);

    $this->setCacheExpectations();

    $this->setNonPublicPropertyValue('current', null);

    $this->getNonPublicMethod('_get_cache')
      ->invoke(self::$mysql, '', 'databases');
  }

  /** @test */
  public function db_cache_name_returns_cache_name_of_database_structure()
  {
    $method    = $this->getNonPublicMethod('_db_cache_name');
    $db_config = self::getDbConfig();

    $this->assertSame(
      "{$db_config['engine']}/{$db_config['user']}@{$db_config['host']}/{$db_config['db']}/users",
      $method->invoke(self::$mysql, 'users', 'columns')
    );

    $this->assertSame(
      "{$db_config['engine']}/{$db_config['user']}@{$db_config['host']}/table_name",
      $method->invoke(self::$mysql, 'table_name', 'tables')
    );

    $this->assertSame(
      "{$db_config['engine']}/{$db_config['user']}@{$db_config['host']}/{$db_config['db']}",
      $method->invoke(self::$mysql, '', 'tables')
    );

    $this->assertSame(
      "{$db_config['engine']}/{$db_config['user']}@{$db_config['host']}/_bbn-database",
      $method->invoke(self::$mysql, '', 'databases')
    );
  }


  /** @test */
  public function modelize_method_returns_table_structure_as_an_indexed_array_for_the_given_table_name()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id BINARY(16) PRIMARY KEY,
              name VARCHAR(25) NOT NULL,
              username VARCHAR(50) NOT NULL UNIQUE,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              role_id INT(11) NOT NULL';
    });

    self::$connection->query(
      "ALTER TABLE users ADD CONSTRAINT `user_role` FOREIGN KEY (`role_id`)
       REFERENCES `roles` (id) ON DELETE CASCADE ON UPDATE RESTRICT"
    );

    $this->createTable('roles', function () {
      return 'id int(11) PRIMARY KEY AUTO_INCREMENT,
              name VARCHAR(25)';
    });

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
        'username' => [
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
          'update'    => 'RESTRICT',
          'delete'    => 'CASCADE',
          'unique'    => 0
        ]
      ],
      'cols' => [
        'id' => ['PRIMARY'],
        'username' => ['username'],
        'role_id' => ['user_role']
      ],
      'fields' => [
        'id' => [
          'position' => 1,
          'type' => 'binary',
          'null'  => 0,
          'key' => 'PRI',
          'extra' => '',
          'signed' => true,
          'virtual' => false,
          'generation'  => '',
          'maxlength' => 16
        ],
        'name' => [
          'position' => 2,
          'type' => 'varchar',
          'null'  => 0,
          'key' => null,
          'extra' => '',
          'signed' => true,
          'virtual' => false,
          'generation'  => '',
          'maxlength' => 25
        ],
        'username' => [
          'position' => 3,
          'type' => 'varchar',
          'null'  => 0,
          'key' => 'UNI',
          'extra' => '',
          'signed' => true,
          'virtual' => false,
          'generation'  => '',
          'maxlength' => 50
        ],
        'created_at' => [
          'position' => 4,
          'type' => 'timestamp',
          'null'  => 0,
          'key' => null,
          'extra' => 'DEFAULT_GENERATED',
          'signed' => true,
          'virtual' => false,
          'generation'  => '',
          'default' => 'CURRENT_TIMESTAMP'
        ],
        'role_id' => [
          'position' => 5,
          'type' => 'int',
          'null'  => 0,
          'key' => 'MUL',
          'extra' => '',
          'signed' => true,
          'virtual' => false,
          'generation'  => ''
        ]
      ]
    ];

    $this->assertSame($users_expected, self::$mysql->modelize('users'));

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
          'type' => 'int',
          'null'  => 0,
          'key' => 'PRI',
          'extra' => 'auto_increment',
          'signed' => true,
          'virtual' => false,
          'generation'  => ''
        ],
        'name' => [
          'position' => 2,
          'type' => 'varchar',
          'null'  => 1,
          'key' => null,
          'extra' => '',
          'signed' => true,
          'virtual' => false,
          'generation'  => '',
          'default' => 'NULL',
          'maxlength' => 25
        ]
      ]
    ];

    $this->assertSame($roles_expected, self::$mysql->modelize('roles'));

    $this->assertSame(
      [
        "$db.roles" => $roles_expected,
        "$db.users" => $users_expected
      ],
      self::$mysql->modelize('*')
    );
  }

  /** @test */
  public function modelize_method_does_not_get_from_cache_if_the_given_force_parameter_is_true()
  {
    $db_config = self::getDbConfig();

    $this->createTable('users', function () {
      return 'id INT(11)';
    });

    self::$cache_mock->shouldNotReceive('cacheGet');

    self::$cache_mock->shouldReceive('set')
      ->once()
      ->with(
        Str::encodeFilename(str_replace('\\', '/', \get_class(self::$mysql)), true).'/' .
        "mysql/{$db_config['user']}@{$db_config['host']}/{$db_config['db']}/users",
        $expected = [
          'keys' => [],
          'cols' => [],
          'fields' => [
            'id' => [
              'position' => 1,
              'type' => 'int',
              'null' => 1,
              'key' => null,
              'extra' => '',
              'signed' => true,
              'virtual' => false,
              'generation' => '',
              'default' => 'NULL'
            ]
          ]
        ],
        $this->getNonPublicProperty('cache_renewal')
      )
      ->andReturnTrue();

    $result = self::$mysql->modelize('users', true);

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getEngine_method_returns_the_mysql_class_name()
  {
    $this->assertSame(
      'mysql',
      self::$mysql->getEngine()
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

    self::$mysql->arrangeConditions($conditions, $cfg);

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
      self::$mysql->removeVirtual($cfg)
    );
  }

  /** @test */
  public function getLastCfg_method_returns_the_last_config_for_the_connection()
  {
    $this->assertSame(
      $this->getNonPublicProperty('last_cfg'),
      self::$mysql->getLastCfg()
    );
  }

  /** @test */
  public function renameTable_method_renames_the_given_table_to_the_new_given_name()
  {
    $this->createTable('users', function () {
      return 'id INT';
    });

    $this->assertTrue(
      self::$mysql->renameTable('users', 'users2')
    );

    $tables = self::$mysql->getTables();

    $this->assertTrue(in_array('users2', $tables));
    $this->assertTrue(!in_array('users', $tables));
  }

  /** @test */
  public function renameTable_method_returns_false_when_check_method_returns_false()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertFalse(
      self::$mysql->renameTable('users', 'users2')
    );
  }

  /** @test */
  public function renameTable_method_returns_false_when_the_given_table_names_are_not_valid()
  {
    $this->assertFalse(
      self::$mysql->renameTable('users**', 'users2')
    );

    $this->assertFalse(
      self::$mysql->renameTable('users', 'users2&&')
    );

    $this->assertFalse(
      self::$mysql->renameTable('users**', 'users2**')
    );
  }

  /** @test */
  public function getTableComment_method_returns_the_comment_for_the_given_table()
  {
    self::$mysql->rawQuery("CREATE TABLE users (id INT) COMMENT 'Hello word!'");

    $this->assertSame(
      'Hello word!',
      self::$mysql->getTableComment('users')
    );
  }

  /** @test */
  public function getTableComment_method_returns_empty_string_if_the_given_table_has_no_comment()
  {
    $this->createTable('users', function () {
      return 'id INT';
    });

    $this->assertSame(
      "",
      self::$mysql->getTableComment('users')
    );
  }
}