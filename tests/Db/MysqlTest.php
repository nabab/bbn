<?php

namespace Db;

use bbn\Cache;
use bbn\Db2;
use bbn\Db2\Enums\Errors;
use bbn\Db2\Languages\Mysql;
use bbn\Str;
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

  protected static $connection;

  protected static $cache_mock;

  protected function setUp(): void
  {
    $this->setNonPublicPropertyValue('_has_error_all', false);
    $this->setNonPublicPropertyValue('_has_error', false);
    $this->setNonPublicPropertyValue('last_error', null);
    $this->setNonPublicPropertyValue('last_real_params', self::$real_params_default);
    $this->setNonPublicPropertyValue('on_error', Errors::E_STOP);
    $this->cleanTestingDir();
    $this->clearCache();
    $this->dropAllTables();
  }

  public static function setUpBeforeClass(): void
  {
    if (!file_exists($env_file = getcwd() . '/tests/.env.test')) {
      throw new \Exception(
        'env file does not exist, please create the file in the tests dir, @see .env.test.example'
      );
    }

    $env = file_get_contents($env_file);

    foreach (explode(PHP_EOL, $env) as $item) {
      $res = explode('=', $item);
      $key  = $res[0];
      $value = $res[1];
      putenv("$key=$value");
    }

    self::$db_mock    = \Mockery::mock(Db2::class);
    self::$cache_mock = \Mockery::mock(Cache::class);

    self::$mysql = new Mysql(self::getDbConfig());

    self::$mysql->startFancyStuff();

    self::$real_params_default = ReflectionHelpers::getNonPublicProperty(
      'last_real_params', self::$mysql
    );

    self::$connection = ReflectionHelpers::getNonPublicProperty(
      'pdo', self::$mysql
    );

    self::$connection->query("SET FOREIGN_KEY_CHECKS=0;");

    ReflectionHelpers::setNonPublicPropertyValue(
      'cache_engine', self::$mysql, self::$cache_mock
    );
  }

  protected static function getDbConfig()
  {
    return array(
      'engine'        => 'mysql',
      'host'          => getenv('db_host'),
      'user'          => getenv('db_user'),
      'pass'          => getenv('db_pass'),
      'db'            => getenv('db_name'),
      'cache_length'  => 3000,
      'on_error'      => Errors::E_STOP
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
    foreach (self::$mysql->getTables() as $table) {
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
      ->once()
      ->andReturnFalse();

    self::$cache_mock->shouldReceive('set')
      ->once()
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

    /** @test */
  public function isAggregateFunction_method_returns_true_if_the_given_name_is_aggregate_function()
  {
    $this->assertFalse(Mysql::isAggregateFunction('count'));
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
        'code_host' => "{$db_cfg['user']}@{$db_cfg['host']}"
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
  public function getSelect_method_generates_a_string_with_select_statement_from_the_given_arguments()
  {
    $cfg = [
      'tables' => [
        'users' => 'users',
        'r'     => 'roles'
      ],
      'fields' => ['id', 'username', 'unique_roles' => 'distinct role_name', 'cfg'],
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
    $expected = "SELECT LOWER(HEX(`users`.`id`)) AS `id`, `users`.`username`, DISTINCT `roles`.`role_name` AS `unique_roles`, `users`.`cfg`
FROM `$db_name`.`users`, `$db_name`.`roles` AS `r`
";

    $this->assertSame($expected, $result);
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

    self::$mysql->getCreate('users', $cfg);
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
  public function createIndex_method_created_index_for_the_givens_table_and_columns()
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
      return "`email` varchar(255) NOT NULL";
    });

    $result2 = self::$mysql->createIndex('users', 'email', false, 20);
    $model2  = $this->getTableStructure('users');

    $this->assertTrue($result2);
    $this->assertTrue(isset($model2['keys']['users_email']['unique']));
    $this->assertSame(0, $model2['keys']['users_email']['unique']);
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
   * @depends createIndex_method_created_index_for_the_givens_table_and_columns
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
  public function deleteIndex_method_returns_false_when_the_given_key_has_a_not_valid_name()
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
  public function deleteIndex_method_returns_false_when_table_full_name_cannot_be_retrieved()
  {
    $this->assertFalse(
      self::$mysql->deleteIndex('users', 'ema*ail')
    );
  }

  /** @test */
  public function createMysqlDatabase_method_creates_a_database()
  {
    $this->dropDatabaseIfExists('bbn_create_test');

    $method = $this->getNonPublicMethod('createMysqlDatabase');

    $result = $method->invoke(self::$mysql, 'bbn_create_test');

    $this->assertTrue($result);
    $this->assertTrue(in_array('bbn_create_test', self::$mysql->getDatabases()));

    $this->dropDatabaseIfExists('bbn_create_test');
  }

  /** @test */
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
    self::$connection->query("INSERT INTO users set description = '$text'");

    $this->assertTrue(self::$mysql->dbSize() > 0);
    $this->assertSame(0, self::$mysql->dbSize('', 'index'));

    $this->dropTableIfExists('users');

    // Test with a database different than the current one
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
    self::$connection->query("INSERT INTO comments set description = '$text'");

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

    self::$mysql->change(self::getDbConfig()['db']);
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
}