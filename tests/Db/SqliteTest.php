<?php

namespace Db;

use bbn\Cache;
use bbn\Db2\Enums\Errors;
use bbn\Db2\Languages\Sqlite;
use PHPUnit\Framework\TestCase;
use tests\Files;
use tests\Reflectable;

class SqliteTest extends TestCase
{
  use Files, Reflectable;

  protected Sqlite $sqlite;

  protected $db_dir = 'db';

  protected $cache_mock;

  protected function setUp(): void
  {
    $this->cleanTestingDir();

    $this->createDir('db');

    $this->sqlite = new Sqlite([
      'db' => 'testing'
    ]);

    $this->cache_mock = \Mockery::mock(Cache::class);

    $this->setNonPublicPropertyValue('cache_engine', $this->cache_mock);
    $this->clearCache();

    $this->sqlite->startFancyStuff();
  }

  protected function tearDown(): void
  {
//    $this->cleanTestingDir();
    \Mockery::close();
  }

  protected function clearCache()
  {
    $this->setNonPublicPropertyValue('cache', []);
  }

  protected function setCacheExpectations()
  {
    $this->cache_mock->shouldReceive('get')
      ->andReturnFalse();

    $this->cache_mock->shouldReceive('set')
      ->andReturnTrue();
  }

  public function getInstance()
  {
    return $this->sqlite;
  }

  protected function createTable(string $table, callable $callback)
  {
    $structure = $callback();

    $this->sqlite->rawQuery("CREATE TABLE $table ($structure)");
  }

  /** @test */
  public function constructor_test_when_the_given_db_is_not_a_file_and_db_dir_exists()
  {
    $this->createDir($this->db_dir);

    $sqlite = new Sqlite([
      'db' => 'testing2'
    ]);

    $this->assertSame('main', $this->getNonPublicProperty('current', $sqlite));
    $this->assertSame($expected_host = BBN_DATA_PATH . 'db/', $this->getNonPublicProperty('connection_code', $sqlite));
    $this->assertSame($expected_host, $this->getNonPublicProperty('host', $sqlite));
    $this->assertInstanceOf(\PDO::class, $this->getNonPublicProperty('pdo', $sqlite));
    $this->assertSame($expected_cfg = [
      'db' => 'main',
      'engine' => 'sqlite',
      'host' => $expected_host,
      'args' => [
        "sqlite:{$expected_host}testing2.sqlite"
      ]
    ], $this->getNonPublicProperty('cfg', $sqlite));

    $this->assertSame(
      $this->getNonPublicMethod('makeHash', $sqlite)
      ->invoke($sqlite, $expected_cfg['args']),
      $this->getNonPublicProperty('hash', $sqlite)
    );
  }

  /** @test */
  public function constructor_test_when_the_given_db_is_a_file()
  {
    $this->createFile('db_test.sqlite', '', $this->db_dir);

    $sqlite = new Sqlite([
      'db' => ($expected_host = $this->getTestingDirName() . 'db/') . 'db_test.sqlite'
    ]);

    $this->assertSame('main', $this->getNonPublicProperty('current', $sqlite));
    $this->assertSame($expected_host, $this->getNonPublicProperty('connection_code', $sqlite));
    $this->assertSame($expected_host, $this->getNonPublicProperty('host', $sqlite));
    $this->assertInstanceOf(\PDO::class, $this->getNonPublicProperty('pdo', $sqlite));
    $this->assertSame($expected_cfg = [
      'db' => 'main',
      'engine' => 'sqlite',
      'host' => $expected_host,
      'args' => [
        "sqlite:{$expected_host}db_test.sqlite"
      ]
    ], $this->getNonPublicProperty('cfg', $sqlite));

    $this->assertSame(
      $this->getNonPublicMethod('makeHash', $sqlite)
        ->invoke($sqlite, $expected_cfg['args']),
      $this->getNonPublicProperty('hash', $sqlite)
    );
  }

  /** @test */
  public function constructor_test_when_the_given_db_is_not_a_file_and_db_dir_does_not_exist()
  {
    $this->createDir('db2');

    $sqlite = new Sqlite([
      'db' => ($expected_host = $this->getTestingDirName() . 'db2/') . 'db'
    ]);

    $this->assertSame('main', $this->getNonPublicProperty('current', $sqlite));
    $this->assertSame($expected_host, $this->getNonPublicProperty('connection_code', $sqlite));
    $this->assertSame($expected_host, $this->getNonPublicProperty('host', $sqlite));
    $this->assertInstanceOf(\PDO::class, $this->getNonPublicProperty('pdo', $sqlite));
    $this->assertSame($expected_cfg = [
      'db' => 'main',
      'engine' => 'sqlite',
      'host' => $expected_host,
      'args' => [
        "sqlite:{$expected_host}db.sqlite"
      ]
    ], $this->getNonPublicProperty('cfg', $sqlite));

    $this->assertSame(
      $this->getNonPublicMethod('makeHash', $sqlite)
        ->invoke($sqlite, $expected_cfg['args']),
      $this->getNonPublicProperty('hash', $sqlite)
    );
  }

  /** @test */
  public function constructor_throws_an_exception_when_cannot_locate_db_file()
  {
    $this->expectException(\Exception::class);

    new Sqlite([
      'db' => $this->getTestingDirName() . 'db2/db'
    ]);
  }

  /** @test */
  public function postCreation_method_enables_foreign_keys_when_the_object_is_created()
  {
    $sqlite = \Mockery::mock(Sqlite::class)->makePartial();

    $sqlite->shouldReceive('enableKeys')
      ->once()
      ->withNoArgs()
      ->andReturnSelf();

    $sqlite->postCreation();

    $this->assertTrue(true);
  }

  /** @test */
  public function change_method_changes_the_current_database_to_the_given_one()
  {
    $this->createFile('new_db.sqlite', '', $this->db_dir);
    $this->createFile('new_db2.sqlite', '', $this->db_dir);

    $result = $this->sqlite->change('new_db');

    $this->assertTrue($result);
    $this->assertSame('new_db', $this->getNonPublicProperty('current'));
    $this->assertCount(
      2,
      $databases = $this->sqlite->rawQuery('PRAGMA database_list')->fetchAll()
    );
    $this->assertSame(
      'new_db',
      $databases[1]['name']
    );



    $result2 = $this->sqlite->change('new_db2.sqlite');

    $this->assertTrue($result2);
    $this->assertSame('new_db2', $this->getNonPublicProperty('current'));
    $this->assertCount(
      3,
      $databases = $this->sqlite->rawQuery('PRAGMA database_list')->fetchAll()
    );
    $this->assertSame(
      'new_db2',
      $databases[2]['name']
    );

  }

  /** @test */
  public function change_method_returns_false_when_the_given_db_is_the_same_as_the_current_one()
  {
    $result = $this->sqlite->change('main');

    $this->assertFalse($result);
    $this->assertSame('main', $this->getNonPublicProperty('current'));
    $this->assertCount(
      1,
      $this->sqlite->rawQuery('PRAGMA database_list')->fetchAll()
    );
  }

  /** @test */
  public function change_method_returns_false_when_the_given_db_file_does_not_exist()
  {
    $result = $this->sqlite->change('db_testing');

    $this->assertFalse($result);
    $this->assertSame('main', $this->getNonPublicProperty('current'));
    $this->assertCount(
      1,
      $this->sqlite->rawQuery('PRAGMA database_list')->fetchAll()
    );
  }

  /** @test */
  public function change_method_returns_false_when_the_given_db_name_has_quotes()
  {
    $this->createFile('"new_db".sqlite', '', $this->db_dir);

    $result = $this->sqlite->change('"new_db"');

    $this->assertFalse($result);
    $this->assertSame('main', $this->getNonPublicProperty('current'));
    $this->assertCount(
      1,
      $this->sqlite->rawQuery('PRAGMA database_list')->fetchAll()
    );
  }

  /** @test */
  public function escape_method_returns_escaped_database_expressions()
  {
    $this->assertSame(
      '"users"',
      $this->sqlite->escape('users')
    );

    $this->assertSame(
      '"db"."users"."email"',
      $this->sqlite->escape('db.users.email')
    );
  }

  /** @test */
  public function escape_method_throws_an_exception_when_the_given_name_is_not_valid()
  {
    $this->expectException(\Exception::class);

    $this->sqlite->escape('db.users**.email');
  }

  /** @test */
  public function tableFullName_method_returns_table_full_name()
  {
    $this->assertSame(
      'users',
      $this->sqlite->tableFullName('users')
    );

    $this->assertSame(
      '"users"',
      $this->sqlite->tableFullName('users', true)
    );

    $this->setNonPublicPropertyValue('current', 'db2');

    $this->assertSame(
      'db2.users',
      $this->sqlite->tableFullName('users')
    );

    $this->assertSame(
      '"db2"."users"',
      $this->sqlite->tableFullName('users', true)
    );
  }

  /** @test */
  public function tableFullName_returns_null_when_the_given_name_is_not_valid()
  {
    $this->assertNull(
      $this->sqlite->tableFullName('users**')
    );

    $this->assertNull(
      $this->sqlite->tableFullName('db8**.users')
    );

    $this->assertNull(
      $this->sqlite->tableFullName('db.users**')
    );
  }

  /** @test */
  public function tableSimpleName_method_returns_table_simple_name()
  {
    $this->assertSame(
      'users',
      $this->sqlite->tableSimpleName('users')
    );

    $this->assertSame(
      '"users"',
      $this->sqlite->tableSimpleName('users', true)
    );

    $this->assertSame(
      'users',
      $this->sqlite->tableSimpleName('db.users')
    );

    $this->assertSame(
      '"users"',
      $this->sqlite->tableSimpleName('db.users', true)
    );
  }

  /** @test */
  public function colFullName_method_returns_column_full_name()
  {
    $this->assertSame(
      'users.email',
      $this->sqlite->colFullName('email', 'users')
    );

    $this->assertSame(
      '"users"."email"',
      $this->sqlite->colFullName('email', 'users', true)
    );

    $this->assertSame(
      '"users"."email"',
      $this->sqlite->colFullName('email', 'db.users', true)
    );

    $this->assertSame(
      '"users"."email"',
      $this->sqlite->colFullName('users.email', null, true)
    );

    $this->assertNull(
      $this->sqlite->colFullName('email')
    );
  }

  /** @test */
  public function colSimpleName_method_returns_column_simple_name()
  {
    $this->assertSame(
      'email',
      $this->sqlite->colSimpleName('users.email')
    );

    $this->assertSame(
      '"email"',
      $this->sqlite->colSimpleName('users.email', true)
    );

    $this->assertSame(
      '"email"',
      $this->sqlite->colSimpleName('email', true)
    );
  }

  /** @test */
  public function isTableFullName_method_checks_whether_the_given_table_name_is_full_name()
  {
    $this->assertTrue(
      $this->sqlite->isTableFullName('db.users')
    );

    $this->assertFalse(
      $this->sqlite->isTableFullName('users')
    );
  }

  /** @test */
  public function isColFullName_method_checks_whether_the_given_column_name_is_full_name()
  {
    $this->assertTrue(
      $this->sqlite->isColFullName('users.email')
    );

    $this->assertFalse(
      $this->sqlite->isColFullName('email')
    );
  }

  /** @test */
  public function disableKeys_method_disables_foreign_keys_check()
  {
    $this->sqlite->disableKeys();

    $result = $this->sqlite->rawQuery('PRAGMA foreign_keys')->fetchAll();

    $this->assertSame(0, $result[0]['foreign_keys']);
  }

  /** @test */
  public function enableKeys_method_enables_foreign_keys_check()
  {
    $this->sqlite->enableKeys();

    $result = $this->sqlite->rawQuery('PRAGMA foreign_keys')->fetchAll();

    $this->assertSame(1, $result[0]['foreign_keys']);
  }

  /** @test */
  public function getDatabases_method_returns_databases_as_an_array()
  {
    $this->assertSame(
      ['testing'],
      $this->sqlite->getDatabases()
    );

    $this->createFile('bbn_testing.sqlite', '', $this->db_dir);

    $host = $this->getNonPublicProperty('host');

    $this->sqlite->rawQuery("ATTACH '$host.bbn_testing' AS bbn_testing");

    $this->assertSame(
      ['bbn_testing', 'testing'],
      $this->sqlite->getDatabases()
    );
  }

  /** @test */
  public function getDatabases_method_returns_null_when_no_current_db()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull($this->sqlite->getDatabases());
  }

  /** @test */
  public function getDatabases_method_returns_null_when_there_is_an_error()
  {
    $this->setNonPublicPropertyValue('_has_error', true);

    $this->assertNull($this->sqlite->getDatabases());
  }

  /** @test */
  public function getTables_method_returns_tables_of_database_as_an_array()
  {
    $this->createTable('users', function () {
      return 'id INT(11) PRIMARY KEY';
    });

    $this->createTable('roles', function () {
      return 'id INT(11) PRIMARY KEY';
    });

    $this->assertSame(
      ['users', 'roles'],
      $this->sqlite->getTables()
    );

    $this->assertSame(
      ['users', 'roles'],
      $this->sqlite->getTables('main')
    );

    $this->createFile('testing_2.sqlite', '', $this->db_dir);

    $host = $this->getNonPublicProperty('host');

    $this->sqlite->rawQuery("ATTACH '{$host}testing_2.sqlite' AS testing_2");

    $this->setNonPublicPropertyValue('current', 'testing_2');

    $this->createTable('testing_2.users', function () {
      return 'id INT(11) PRIMARY KEY';
    });

    $this->assertSame(
      ['users'],
      $this->sqlite->getTables()
    );
  }

  /** @test */
  public function getTables_method_returns_null_when_no_current_connection()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull(
      $this->sqlite->getTables()
    );
  }

  /** @test */
  public function getTables_method_returns_null_when_there_is_an_error_in_the_current_connection()
  {
    $this->setNonPublicPropertyValue('_has_error', true);

    $this->assertNull(
      $this->sqlite->getTables()
    );
  }

  /** @test */
  public function getColumns_method_returns_the_columns_configuration_for_the_given_table()
  {
    $this->createTable('users', function () {
      return 'id UNSIGNED BIGINT PRIMARY KEY,
              email VARCHAR(20) UNIQUE NOT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              parent_id BLOB(32) NOT NULL,
              active BOOL DEFAULT false,
              balance UNSIGNED FLOAT NOT NULL DEFAULT 0,
              temp_balance UNSIGNED REAL NOT NULL DEFAULT 0
              ';
    });

    $this->assertSame(
      [
        'id' => [
        'position' => 1,
        'null' => 1,
        'key' => 'PRI',
        'default' => null,
        'extra' => null,
        'maxlength' => null,
        'signed' => 0,
        'type' => 'INTEGER'
      ],
        'email' => [
          'position' => 2,
          'null'  => 0,
          'key' => null,
          'default' => null,
          'extra' => null,
          'maxlength' => 20,
          'signed' => 1,
          'type' => 'TEXT'
        ],
        'created_at' => [
          'position' => 3,
          'null' => 1,
          'key' => null,
          'default' => 'CURRENT_TIMESTAMP',
          'extra' => null,
          'maxlength' => null,
          'signed' => 1,
          'type' => 'INTEGER'
        ],
        'parent_id' => [
          'position' => 4,
          'null' => 0,
          'key' => null,
          'default' => null,
          'extra' => null,
          'maxlength' => 32,
          'signed' => 1,
          'type' => 'BLOB'
        ],
        'active' => [
          'position' => 5,
          'null' => 1,
          'key' => null,
          'default' => 'false',
          'extra' => null,
          'maxlength' => null,
          'signed' => 1,
          'type' => 'INTEGER'
        ],
        'balance' => [
          'position' => 6,
          'null' => 0,
          'key' => null,
          'default' => 0,
          'extra' => null,
          'maxlength' => null,
          'signed' => 0,
          'type' => 'REAL'
        ],
        'temp_balance' => [
          'position' => 7,
          'null'  => 0,
          'key' => null,
          'default' => 0,
          'extra' => null,
          'maxlength' => null,
          'signed' => 0,
          'type' => 'REAL'
        ]
      ],
      $this->sqlite->getColumns('users')
    );
  }

  /** @test */
  public function getColumns_method_returns_null_when_the_current_connection_is_null()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull(
      $this->sqlite->getColumns('users')
    );
  }

  /** @test */
  public function getColumns_method_returns_null_when_there_is_an_error_in_the_current_connection()
  {
    $this->setNonPublicPropertyValue('_has_error', true);

    $this->assertNull(
      $this->sqlite->getColumns('users')
    );
  }

  /** @test */
  public function getColumns_method_returns_empty_array_if_table_does_not_exist()
  {
    $this->assertEmpty(
      $this->sqlite->getColumns('users')
    );
  }

  /** @test */
  public function getColumns_method_returns_empty_array_when_the_given_table_name_is_not_valid()
  {
    $this->assertEmpty(
      $this->sqlite->getColumns('users**')
    );
  }

  /** @test */
  public function getKeys_method_returns_the_keys_of_the_given_table()
  {
    $this->createTable('roles', function () {
      return 'id BLOB(32) PRIMARY KEY,
              name CHAR NOT NULL ';
    });

    $this->createTable('profiles', function () {
      return 'id BLOB(32) PRIMARY KEY,
              name CHAR NOT NULL';
    });

    $this->createTable('users', function () {
      return 'id BIGINT PRIMARY KEY,
              email VARCHAR NOT NULL UNIQUE,
              name CHAR NOT NULL,
              role_id BLOB(32) NOT NULL UNIQUE,
              profile_id BLOB(32) NULL,
              FOREIGN KEY(role_id) REFERENCES roles(id) ON UPDATE CASCADE ON DELETE RESTRICT,
              FOREIGN KEY(profile_id) REFERENCES profiles(id) ON UPDATE CASCADE ON DELETE SET NULL
              ';
    });

    $expected = [
      'keys' => [
        'sqlite_autoindex_users_3' => [
          'columns' => ['role_id'],
          'ref_db' => 'main',
          'ref_table' => 'roles',
          'ref_column' => 'id',
          'unique' => 1
        ],
        'sqlite_autoindex_users_2' => [
          'columns' => ['email'],
          'ref_db' => null,
          'ref_table' => null,
          'ref_column' => null,
          'unique' => 1
        ],
        'sqlite_autoindex_users_1' => [
          'columns' => ['id'],
          'ref_db' => null,
          'ref_table' => null,
          'ref_column' => null,
          'unique' => 1
        ],
        'constraint_profiles_profile_id' => [
          'columns' => ['profile_id'],
          'ref_db' => 'main',
          'ref_table' => 'profiles',
          'ref_column' => 'id',
          'unique' => 0
        ]
      ],
      'cols' => [
        'role_id' => [
          'sqlite_autoindex_users_3'
        ],
        'email' => [
          'sqlite_autoindex_users_2'
        ],
        'id' => [
          'sqlite_autoindex_users_1'
        ],
        'profile_id' => [
          'constraint_profiles_profile_id'
        ]
      ]
    ];

    $this->assertSame(
      $expected,
      $this->sqlite->getKeys('users')
    );
  }

  /** @test */
  public function getKeys_method_returns_the_keys_of_the_given_table_constraints_exist_but_no_indexes()
  {
    $this->createTable('roles', function () {
      return 'id BLOB(32) PRIMARY KEY,
              name CHAR NOT NULL ';
    });

    $this->createTable('users', function () {
      return 'id BIGINT,
              email VARCHAR NOT NULL,
              name CHAR NOT NULL,
              role_id BLOB(32) NOT NULL,
              FOREIGN KEY(role_id) REFERENCES roles(id) ON UPDATE CASCADE ON DELETE RESTRICT
              ';
    });

    $expected = [
      'keys' => [
        'constraint_roles_role_id' => [
          'columns' => ['role_id'],
          'ref_db' => 'main',
          'ref_table' => 'roles',
          'ref_column' => 'id',
          'unique' => 0
        ]
      ],
      'cols' => [
        'role_id' => [
          'constraint_roles_role_id'
        ]
      ]
    ];

    $this->assertSame(
      $expected,
      $this->sqlite->getKeys('users')
    );
  }

  /** @test */
  public function getKeys_method_returns_the_keys_of_the_given_table_when_indexes_exist_but_no_constraints()
  {
    $this->createTable('users', function () {
      return 'id BIGINT PRIMARY KEY,
              email VARCHAR NOT NULL UNIQUE,
              name CHAR NOT NULL
              ';
    });

    $expected = [
      'keys' => [
        'sqlite_autoindex_users_2' => [
          'columns' => ['email'],
          'ref_db' => null,
          'ref_table' => null,
          'ref_column' => null,
          'unique' => 1
        ],
        'sqlite_autoindex_users_1' => [
          'columns' => ['id'],
          'ref_db' => null,
          'ref_table' => null,
          'ref_column' => null,
          'unique' => 1
        ]
      ],
      'cols' => [
        'email' => [
          'sqlite_autoindex_users_2'
        ],
        'id' => [
          'sqlite_autoindex_users_1'
        ]
      ]
    ];

    $this->assertSame(
      $expected,
      $this->sqlite->getKeys('users')
    );
  }

  /** @test */
  public function getKeys_method_returns_null_when_current_connection_is_null()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull(
      $this->sqlite->getKeys('users')
    );
  }

  /** @test */
  public function getKeys_method_returns_null_when_there_is_an_error_in_the_current_conection()
  {
    $this->setNonPublicPropertyValue('_has_error', true);

    $this->assertNull(
      $this->sqlite->getKeys('users')
    );
  }

  /** @test */
  public function getKeys_method_returns_empty_array_when_the_given_table_name_is_not_valid()
  {
    $this->assertEmpty(
      $this->sqlite->getKeys('users***')
    );
  }

  /** @test */
  public function getKeys_method_returns_empty_results_when_the_given_table_does_not_exist()
  {
    $this->assertSame(
      ['keys' => [], 'cols' => []],
      $this->sqlite->getKeys('users')
    );
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
  "users"."id" = ?
  AND name = ?
  AND (created_at IS NULL
    OR updated_at IS NULL
  )
)

RESULT;

    $this->assertSame($expected, $this->sqlite->getConditions($conditions, $cfg));
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

    $result   = $this->sqlite->getSelect($cfg);
    $expected = 'SELECT LOWER(HEX("users"."id")) AS "id", "users"."username", DISTINCT "roles"."role_name" AS "unique_roles", "users"."cfg"
FROM "users", "roles" AS "r"
';

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

    $result   = $this->sqlite->getSelect($cfg);
    $expected = 'SELECT COUNT(*)
FROM "users"
';

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

    $result   = $this->sqlite->getSelect($cfg);

    $expected = 'SELECT COUNT(*) FROM ( SELECT 
FROM "users", "roles"
';

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

    $result   = $this->sqlite->getSelect($cfg);
    $expected = 'SELECT COUNT(*) FROM ( SELECT 
FROM "users", "roles"
';

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getSelect_method_sets_an_error_when_available_fields_missing_a_field()
  {
    $this->expectException(\Exception::class);
    $this->sqlite->setErrorMode(Errors::E_DIE);

    $cfg = [
      'tables' => ['users'],
      'fields' => ['id'],
      'available_fields' => [
        'username' => 'users'
      ]
    ];

    $this->assertSame('', $this->sqlite->getSelect($cfg));
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

    $result   = $this->sqlite->getInsert($cfg);
    $expected = 'INSERT INTO "users"
("id", "username")
 VALUES (?, ?)
';

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

    $result   = $this->sqlite->getInsert($cfg);
    $expected = 'INSERT IGNORE INTO "users"
("id", "username")
 VALUES (?, ?)
';

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

    $this->assertSame('', $this->sqlite->getInsert($cfg));
  }

  /** @test */
  public function getInsert_method_sets_an_error_when_a_field_does_not_exist_in_available_fields()
  {
    $this->sqlite->setErrorMode(Errors::E_DIE);

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

    $this->sqlite->getInsert($cfg);
  }

  /** @test */
  public function getInsert_method_sets_an_error_when_available_table_does_not_exist_in_models()
  {
    $this->sqlite->setErrorMode(Errors::E_DIE);

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

    $this->sqlite->getInsert($cfg);
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

    $result   = $this->sqlite->getUpdate($cfg);
    $expected = 'UPDATE "users" SET "id" = ?,
"username" = ?
';

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

    $result   = $this->sqlite->getUpdate($cfg);
    $expected = 'UPDATE IGNORE "users" SET "id" = ?,
"username" = ?
';

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

    $this->assertSame('', $this->sqlite->getUpdate($cfg));
  }

  /** @test */
  public function getUpdate_method_sets_an_error_when_a_field_does_not_exist_in_available_fields()
  {
    $this->sqlite->setErrorMode(Errors::E_DIE);

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

    $this->sqlite->getUpdate($cfg);
  }

  /** @test */
  public function getUpdate_method_sets_an_error_when_available_table_does_not_exist_in_models()
  {
    $this->sqlite->setErrorMode(Errors::E_DIE);

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

    $this->sqlite->getUpdate($cfg);
  }

  /** @test */
  public function getDelete_method_returns_string_for_delete_statement()
  {
    $cfg = [
      'tables' => ['users']
    ];

    $result   = $this->sqlite->getDelete($cfg);
    $expected = 'DELETE FROM "users"
';

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

    $result   = $this->sqlite->getDelete($cfg);
    $expected = 'DELETE IGNORE FROM "users"
';

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getDelete_method_returns_string_for_delete_statement_and_join_exists()
  {
    $cfg = [
      'tables' => ['users'],
      'join' => ['roles']
    ];

    $result   = $this->sqlite->getDelete($cfg);
    $expected = 'DELETE users FROM "users"
';

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getDelete_method_returns_empty_string_when_tables_provided_are_more_than_one()
  {
    $cfg = [
      'tables' => ['users', 'roles']
    ];

    $this->assertSame('', $this->sqlite->getDelete($cfg));
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

    $result   = $this->sqlite->getJoin($cfg);
    $expected = ' JOIN "users"
    ON roles.user_id = users.id
  JOIN "payments"
    ON payments.user_id = users.id
    ';

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

    $result   = $this->sqlite->getJoin($cfg);
    $expected = ' LEFT JOIN "users" AS "u"
    ON roles.user_id = users.id';

    $this->assertSame(trim($expected), trim($result));
  }

  /** @test */
  public function getJoin_method_returns_empty_string_when_configurations_are_missing()
  {
    $this->assertSame('', $this->sqlite->getJoin([]));

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

    $this->assertSame('', $this->sqlite->getJoin($cfg));

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

    $this->assertSame('', $this->sqlite->getJoin($cfg));

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

    $this->assertSame('', $this->sqlite->getJoin($cfg));
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

    $result   = $this->sqlite->getWhere($cfg);
    $expected = "WHERE roles.user_id = users.id
AND roles.email LIKE ?";

    $this->assertSame($expected, trim($result));
  }

  /** @test */
  public function getWhere_method_returns_empty_string_when_some_of_configurations_are_missing()
  {
    $this->assertSame('', $this->sqlite->getWhere([]));

    $cfg = [
      'filters' => [
        'conditions' => [[
          'field' => 'roles.user_id',
          'exp' => 'users.id'
        ]],
        'logic' => 'AND'
      ]
    ];

    $this->assertSame('', $this->sqlite->getWhere($cfg));

    $cfg = [
      'filters' => [
        'conditions' => [[
          'operator' => '='
        ]],
        'logic' => 'AND'
      ]
    ];

    $this->assertSame('', $this->sqlite->getWhere($cfg));
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

    $result   = $this->sqlite->getGroupBy($cfg);
    $expected = 'GROUP BY "id", "name"';

    $this->assertSame($expected, trim($result));
  }

  /** @test */
  public function getGroupBy_returns_empty_string_when_configurations_are_missing()
  {
    $this->assertSame('', $this->sqlite->getGroupBy(['group' => ['id']]));
    $this->assertSame('', $this->sqlite->getGroupBy([
      'group_by' => ['id']
    ]));

    $this->assertSame('', $this->sqlite->getGroupBy([
      'group_by' => ['id'],
      'available_fields' => [
        'username'
      ]
    ]));
  }

  /** @test */
  public function getGroupBy_method_sets_an_error_when_available_fields_config_missing_one_of_the_fields()
  {
    $this->sqlite->setErrorMode(Errors::E_DIE);

    $this->expectException(\Exception::class);

    $this->sqlite->getGroupBy([
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

    $result   = $this->sqlite->getHaving($cfg);
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

    $result   = $this->sqlite->getHaving($cfg);
    $expected = "WHERE 
  user_count >= ?";

    $this->assertSame($expected, trim($result));
  }

  /** @test */
  public function getHaving_method_returns_empty_string_when_configuration_missing_some_items()
  {
    $this->assertSame('', $this->sqlite->getHaving([]));
    $this->assertSame('', $this->sqlite->getHaving([
      'having'   => [
        'conditions' => [[
          'field' => 'user_count',
          'value'   => 20,
          'operator' => '>='
        ]],
        'logic' => 'AND'
      ]
    ]));

    $this->assertSame('', $this->sqlite->getHaving(['group_by' => ['id']]));
    $this->assertSame('', $this->sqlite->getHaving([
      'group_by' => ['id'],
      'having'   => [
        'conditions' => [[
          'field' => 'user_count',
          'value'   => 20,
          'operator' => '>='
        ]]
      ]
    ]));

    $this->assertSame('', $this->sqlite->getHaving([
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

    $result   = $this->sqlite->getOrder($cfg);
    $expected = 'ORDER BY "id_alias" COLLATE NOCASE DESC,
"users"."username" COLLATE NOCASE ASC,
"first_name" COLLATE NOCASE ASC';

    $this->assertSame($expected, trim($result));
  }

  /** @test */
  public function getOrder_method_returns_empty_string_when_configurations_missing_some_items()
  {
    $this->assertSame('', $this->sqlite->getOrder([]));
    $this->assertSame('', $this->sqlite->getOrder([
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
    $result   = $this->sqlite->getLimit(['limit' => 2]);
    $expected = 'LIMIT 0, 2';

    $this->assertSame($expected, trim($result));

    $result   = $this->sqlite->getLimit(['limit' => 2, 'start' => 4]);
    $expected = 'LIMIT 4, 2';

    $this->assertSame($expected, trim($result));
  }

  /** @test */
  public function getLimit_method_returns_empty_string_when_configurations_missing_the_limit_param()
  {
    $this->assertSame('', $this->sqlite->getLimit([]));
  }

  /** @test */
  public function getLimit_method_returns_empty_string_when_the_provided_limit_is_not_an_integer()
  {
    $this->assertSame('', $this->sqlite->getLimit(['limit' => 'foo']));
  }

  /** @test */
  public function getRawCreate_method_returns_a_string_with_create_table_statement_by_querying_database_by_table_name()
  {
    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY AUTOINCREMENT';
    });

    $result = $this->sqlite->getRawCreate('users');

    $this->assertSame(
      'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT)',
      trim($result)
    );
  }

  /** @test */
  public function getRawCreate_method_returns_empty_string_if_the_given_table_name_is_not_valid()
  {
    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY AUTOINCREMENT';
    });

    $this->assertSame('', $this->sqlite->getRawCreate('users**'));
  }

  /** @test */
  public function getRawCreate_method_returns_empty_string_if_raw_query_failed()
  {
    $sqlite = \Mockery::mock(Sqlite::class)->makePartial();

    $sqlite->shouldReceive('tableFullName')
      ->once()
      ->with('users', true)
      ->andReturn('users');

    $sqlite->shouldReceive('rawQuery')
      ->once()
      ->andReturnFalse();

    $this->assertSame('', $sqlite->getRawCreate('users'));
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

    $result   = $this->sqlite->getCreateTable('users', $cfg);

    $expected = '
  CREATE TABLE "users" (
  "id" blob NOT NULL,
  "username" text NOT NULL,
  "role" text NOT NULL DEFAULT \'user\',
  "permission" text NOT NULL DEFAULT \'read\',
  "balance" real DEFAULT NULL,
  "balance_before" real NOT NULL DEFAULT 0,
  "created_at" text NOT NULL DEFAULT CURRENT_TIMESTAMP
)';

    $this->assertSame(trim($expected), trim($result));

    return $expected;
  }

  /** @test */
  public function getCreateTable_method_returns_a_string_with_create_table_statement_when_model_is_not_provided()
  {
    $query = '
  CREATE TABLE "users" (
  "id" BLOB NOT NULL,
  "username" TEXT NOT NULL,
  "role" TEXT NOT NULL DEFAULT \'user\',
  "permission" TEXT NOT NULL DEFAULT \'read\',
  "balance" REAL DEFAULT NULL,
  "balance_before" REAL NOT NULL DEFAULT 0,
  "created_at" TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)';
    $this->sqlite->rawQuery($query);

    // Set expectations for the methods called on Cache class in modelize method
    $this->setCacheExpectations();

    $result = $this->sqlite->getCreateTable('users');

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

    $result   = $this->sqlite->getCreateKeys('users', $cfg);
    $expected = 'CREATE UNIQUE INDEX \'primary\' ON "users" ("id");
CREATE UNIQUE INDEX \'unique\' ON "users" ("email");
CREATE INDEX \'key\' ON "users" ("username");';

    $this->assertSame(trim($expected), trim($result));

    return $expected;
  }
}