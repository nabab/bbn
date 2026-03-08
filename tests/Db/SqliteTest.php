<?php

namespace bbn\tests\Db;

use bbn\Cache;
use bbn\Db\Enums\Errors;
use bbn\Db\Languages\Sqlite;
use bbn\Db\Query;
use bbn\Str;
use PHPUnit\Framework\TestCase;
use bbn\tests\Files;
use bbn\tests\Reflectable;

class SqliteTest extends TestCase
{
  use Files, Reflectable;

  protected Sqlite $sqlite;

  protected $db_dir = 'db';

  protected $cache_mock;

  protected $real_params_default;

  protected function setUp(): void
  {
    $this->cleanTestingDir();

    $this->createDir('db');

    $this->sqlite = new Sqlite([
      'db' => 'testing'
    ]);

    $this->cache_mock = \Mockery::mock(Cache::class);

    $this->setNonPublicPropertyValue('cache_engine', $this->cache_mock);
    $this->setNonPublicPropertyValue('_has_error_all', false);
    $this->real_params_default = $this->getNonPublicProperty('last_real_params');
    $this->clearCache();

    $this->sqlite->startFancyStuff();
  }

  protected function tearDown(): void
  {
    $this->cleanTestingDir();
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

  protected function getTableStructure(string $table)
  {
    $this->setCacheExpectations();

    return $this->sqlite->modelize($table);
  }

  public function getInstance()
  {
    return $this->sqlite;
  }

  protected function createTable(string $table, callable $callback)
  {
    $this->sqlite->rawQuery("DROP TABLE IF EXISTS $table");

    $structure = $callback();

    $this->sqlite->rawQuery("CREATE TABLE $table ($structure)");
  }

  protected function insertOne(string $table, array $params)
  {
    $query = "INSERT INTO `$table` (";

    foreach ($params as $column => $value) {
      $query .= "`$column`,  ";
    }

    $query = rtrim($query, ', ');

    $query .= ") VALUES (";

    foreach ($params as $column => $value) {
      $query .= "'$value', ";
    }

    $query = rtrim($query, ', ') .  ")";
    $this->sqlite->rawQuery($query);
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
    $record = $this->sqlite->query(
      "SELECT $field FROM $table WHERE $field = '$value'"
    );

    $this->assertTrue(
      count($this->sqlite->fetchAllResults($record)) > 0
    );
  }


  protected function assertDatabaseDoesNotHave(string $table, string $field, string $value)
  {
    $record = $this->sqlite->query(
      "SELECT $field FROM $table WHERE $field = '$value'"
    );

    $this->assertTrue(
      count($this->sqlite->fetchAllResults($record)) === 0
    );
  }

  /** @test */
  public function testConstructorTestWhenTheGivenDbIsNotAFileAndDbDirExists()
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
    $this->assertInstanceOf(Cache::class, $this->getNonPublicProperty('cache_engine'));
  }

  /** @test */
  public function testConstructorTestWhenTheGivenDbIsAFile()
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
  public function testConstructorTestWhenTheGivenDbIsNotAFileAndDbDirDoesNotExist()
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
        "sqlite:{$expected_host}db"
      ]
    ], $this->getNonPublicProperty('cfg', $sqlite));

    $this->assertSame(
      $this->getNonPublicMethod('makeHash', $sqlite)
        ->invoke($sqlite, $expected_cfg['args']),
      $this->getNonPublicProperty('hash', $sqlite)
    );
  }

  /** @test */
  public function testConstructorThrowsAnExceptionWhenCannotLocateDbFile()
  {
    $this->expectException(\Exception::class);

    new Sqlite([
      'db' => $this->getTestingDirName() . 'db2/db'
    ]);
  }

  /** @test */
  public function testPostcreationMethodEnablesForeignKeysWhenTheObjectIsCreated()
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
  public function testChangeMethodChangesTheCurrentDatabaseToTheGivenOne()
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
  public function testChangeMethodReturnsFalseWhenTheGivenDbIsTheSameAsTheCurrentOne()
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
  public function testChangeMethodReturnsFalseWhenTheGivenDbFileDoesNotExist()
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
  public function testChangeMethodReturnsFalseWhenTheGivenDbNameHasQuotes()
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
  public function testEscapeMethodReturnsEscapedDatabaseExpressions()
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
  public function testEscapeMethodThrowsAnExceptionWhenTheGivenNameIsNotValid()
  {
    $this->expectException(\Exception::class);

    $this->sqlite->escape('db.users**.email');
  }

  /** @test */
  public function testTablefullnameMethodReturnsTableFullName()
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
  public function testTablefullnameReturnsNullWhenTheGivenNameIsNotValid()
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
  public function testTablesimplenameMethodReturnsTableSimpleName()
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
  public function testColfullnameMethodReturnsColumnFullName()
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
  public function testColsimplenameMethodReturnsColumnSimpleName()
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
  public function testIstablefullnameMethodChecksWhetherTheGivenTableNameIsFullName()
  {
    $this->assertTrue(
      $this->sqlite->isTableFullName('db.users')
    );

    $this->assertFalse(
      $this->sqlite->isTableFullName('users')
    );
  }

  /** @test */
  public function testIscolfullnameMethodChecksWhetherTheGivenColumnNameIsFullName()
  {
    $this->assertTrue(
      $this->sqlite->isColFullName('users.email')
    );

    $this->assertFalse(
      $this->sqlite->isColFullName('email')
    );
  }

  /** @test */
  public function testDisablekeysMethodDisablesForeignKeysCheck()
  {
    $this->sqlite->disableKeys();

    $result = $this->sqlite->fetchAllResults(
      $this->sqlite->rawQuery('PRAGMA foreign_keys')
    );

    $this->assertSame('0', $result[0]['foreign_keys']);
  }

  /** @test */
  public function testEnablekeysMethodEnablesForeignKeysCheck()
  {
    $this->sqlite->enableKeys();

    $result = $this->sqlite->fetchAllResults(
      $this->sqlite->rawQuery('PRAGMA foreign_keys')
    );

    $this->assertSame('1', $result[0]['foreign_keys']);
  }

  /** @test */
  public function testGetdatabasesMethodReturnsDatabasesAsAnArray()
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
  public function testGetdatabasesMethodReturnsNullWhenNoCurrentDb()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull($this->sqlite->getDatabases());
  }

  /** @test */
  public function testGetdatabasesMethodReturnsNullWhenThereIsAnError()
  {
    $this->setNonPublicPropertyValue('_has_error', true);

    $this->assertNull($this->sqlite->getDatabases());
  }

  /** @test */
  public function testGettablesMethodReturnsTablesOfDatabaseAsAnArray()
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
  public function testGettablesMethodReturnsNullWhenNoCurrentConnection()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull(
      $this->sqlite->getTables()
    );
  }

  /** @test */
  public function testGettablesMethodReturnsNullWhenThereIsAnErrorInTheCurrentConnection()
  {
    $this->setNonPublicPropertyValue('_has_error', true);

    $this->assertNull(
      $this->sqlite->getTables()
    );
  }

  /** @test */
  public function testGetcolumnsMethodReturnsTheColumnsConfigurationForTheGivenTable()
  {
    $this->createTable('users', function () {
      return "id UNSIGNED BIGINT PRIMARY KEY,
              email VARCHAR(20) UNIQUE NOT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              parent_id BLOB(32) NOT NULL,
              active BOOL DEFAULT false,
              balance UNSIGNED FLOAT NOT NULL DEFAULT 0,
              temp_balance UNSIGNED REAL NOT NULL DEFAULT 0
              ";
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
        'defaultExpression' => false,
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
          'defaultExpression' => false,
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
          'defaultExpression' => true,
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
          'defaultExpression' => false,
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
          'defaultExpression' => false,
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
          'defaultExpression' => false,
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
          'defaultExpression' => false,
          'type' => 'REAL'
        ]
      ],
      $this->sqlite->getColumns('users')
    );
  }

  /** @test */
  public function testGetcolumnsMethodReturnsTheColumnsConfigurationForTheGivenTableWhenThereIsAnAutoIncrementColumn()
  {
    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY,
              email VARCHAR(20) UNIQUE NOT NULL
              ';
    });

    $this->assertSame(
      [
        'id' => [
          'position' => 1,
          'null' => 1,
          'key' => 'PRI',
          'default' => null,
          'extra' => 'auto_increment',
          'maxlength' => null,
          'signed' => 1,
          'defaultExpression' => false,
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
          'defaultExpression' => false,
          'type' => 'TEXT'
        ]
      ],
      $this->sqlite->getColumns('users')
    );
  }

  /** @test */
  public function testGetcolumnsMethodReturnsNullWhenTheCurrentConnectionIsNull()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull(
      $this->sqlite->getColumns('users')
    );
  }

  /** @test */
  public function testGetcolumnsMethodReturnsNullWhenThereIsAnErrorInTheCurrentConnection()
  {
    $this->setNonPublicPropertyValue('_has_error', true);

    $this->assertNull(
      $this->sqlite->getColumns('users')
    );
  }

  /** @test */
  public function testGetcolumnsMethodReturnsEmptyArrayIfTableDoesNotExist()
  {
    $this->assertEmpty(
      $this->sqlite->getColumns('users')
    );
  }

  /** @test */
  public function testGetcolumnsMethodReturnsEmptyArrayWhenTheGivenTableNameIsNotValid()
  {
    $this->assertEmpty(
      $this->sqlite->getColumns('users**')
    );
  }

  /** @test */
  public function testGetkeysMethodReturnsTheKeysOfTheGivenTable()
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
      return 'id INTEGER PRIMARY KEY,
              email VARCHAR NOT NULL,
              name CHAR NOT NULL,
              role_id BLOB(32) NOT NULL,
              profile_id BLOB(32) NULL,
              FOREIGN KEY(role_id) REFERENCES roles(id) ON UPDATE CASCADE ON DELETE RESTRICT,
              FOREIGN KEY(profile_id) REFERENCES profiles(id) ON UPDATE CASCADE ON DELETE SET NULL
              ';
    });

    $this->sqlite->rawQuery("CREATE UNIQUE INDEX 'users_email' ON users ('email')");
    $this->sqlite->rawQuery("CREATE UNIQUE INDEX 'users_role_id' ON users ('role_id')");

    $expected = [
      'keys' => [
        'users_role_id' => [
          'columns' => ['role_id'],
          'ref_db' => 'main',
          'ref_table' => 'roles',
          'ref_column' => 'id',
          'constraint' => 'roles_role_id',
          'update' => 'CASCADE',
          'delete' => 'RESTRICT',
          'unique' => 1
        ],
        'users_email' => [
          'columns' => ['email'],
          'ref_db' => null,
          'ref_table' => null,
          'ref_column' => null,
          'constraint' => null,
          'update' => null,
          'delete' => null,
          'unique' => 1
        ],
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
        'profiles_profile_id' => [
          'columns' => ['profile_id'],
          'ref_db' => 'main',
          'ref_table' => 'profiles',
          'ref_column' => 'id',
          'constraint' => 'profiles_profile_id',
          'update' => 'CASCADE',
          'delete' => 'SET NULL',
          'unique' => 0
        ]
      ],
      'cols' => [
        'role_id' => [
          'users_role_id'
        ],
        'email' => [
          'users_email'
        ],
        'id' => [
          'PRIMARY'
        ],
        'profile_id' => [
          'profiles_profile_id'
        ]
      ]
    ];

    $this->assertSame(
      $expected,
      $this->sqlite->getKeys('users')
    );
  }

  /** @test */
  public function testGetkeysMethodReturnsTheKeysOfTheGivenTableConstraintsExistButNoIndexes()
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
        'roles_role_id' => [
          'columns' => ['role_id'],
          'ref_db' => 'main',
          'ref_table' => 'roles',
          'ref_column' => 'id',
          'constraint' => 'roles_role_id',
          'update' => 'CASCADE',
          'delete' => 'RESTRICT',
          'unique' => 0
        ]
      ],
      'cols' => [
        'role_id' => [
          'roles_role_id'
        ]
      ]
    ];

    $this->assertSame(
      $expected,
      $this->sqlite->getKeys('users')
    );
  }

  /** @test */
  public function testGetkeysMethodReturnsTheKeysOfTheGivenTableWhenIndexesExistButNoConstraints()
  {
    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY,
              email VARCHAR NOT NULL UNIQUE,
              name CHAR NOT NULL
              ';
    });

    $expected = [
      'keys' => [
        'sqlite_autoindex_users_1' => [
          'columns' => ['email'],
          'ref_db' => null,
          'ref_table' => null,
          'ref_column' => null,
          'constraint' => null,
          'update' => null,
          'delete' => null,
          'unique' => 1
        ],
        'PRIMARY' => [
          'columns' => ['id'],
          'ref_db' => null,
          'ref_table' => null,
          'ref_column' => null,
          'constraint' => null,
          'update' => null,
          'delete' => null,
          'unique' => 1
        ]
      ],
      'cols' => [
        'email' => [
          'sqlite_autoindex_users_1'
        ],
        'id' => [
          'PRIMARY'
        ]
      ]
    ];

    $this->assertSame(
      $expected,
      $this->sqlite->getKeys('users')
    );
  }

  /** @test */
  public function testGetkeysMethodReturnsNullWhenCurrentConnectionIsNull()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull(
      $this->sqlite->getKeys('users')
    );
  }

  /** @test */
  public function testGetkeysMethodReturnsNullWhenThereIsAnErrorInTheCurrentConection()
  {
    $this->setNonPublicPropertyValue('_has_error', true);

    $this->assertNull(
      $this->sqlite->getKeys('users')
    );
  }

  /** @test */
  public function testGetkeysMethodReturnsEmptyArrayWhenTheGivenTableNameIsNotValid()
  {
    $this->assertEmpty(
      $this->sqlite->getKeys('users***')
    );
  }

  /** @test */
  public function testGetkeysMethodReturnsEmptyResultsWhenTheGivenTableDoesNotExist()
  {
    $this->assertSame(
      ['keys' => [], 'cols' => []],
      $this->sqlite->getKeys('users')
    );
  }

  /** @test */
  public function testGetconditionsMethodReturnsAStringWithConditionsForTheWhereOrOnOrHavingClauses()
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
  public function testGetselectMethodGeneratesAStringWithSelectStatementFromTheGivenArguments()
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
  public function testGetselectMethodGeneratesAStringWithSelectStatementWhenThereIsACount()
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
  public function testGetselectMethodGeneratesAStringWithSelectStatementWhenThereIsACountAndGroupBy()
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
  public function testGetselectMethodGeneratesAStringWithSelectStatementWhenThereIsACountAndGroupByAndHaving()
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
  public function testGetselectMethodSetsAnErrorWhenAvailableFieldsMissingAField()
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
  public function testGetinsertMethodGeneratesAStringForInsertStatementFromTheGivenArguments()
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
  public function testGetinsertMethodGeneratesAStringForInsertStatementFromTheGivenArgumentsAndIgnoreExists()
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
    $expected = 'INSERT OR IGNORE INTO "users"
("id", "username")
 VALUES (?, ?)
';

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function testGetinsertMethodReturnsAnEmptyStringWhenTablesConfigHasMoreThanOneTable()
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
  public function testGetinsertMethodSetsAnErrorWhenAFieldDoesNotExistInAvailableFields()
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
  public function testGetinsertMethodSetsAnErrorWhenAvailableTableDoesNotExistInModels()
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
  public function testGetupdateMethodReturnsStringForUpdateStatementFromTheGiveArguments()
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
  public function testGetupdateMethodReturnsStringForUpdateStatementFromTheGiveArgumentsAndIgnoreExists()
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
    $expected = 'UPDATE OR IGNORE "users" SET "id" = ?,
"username" = ?
';

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function testGetupdateMethodReturnsAnEmptyStringWhenTablesConfigHasMoreThanOneTable()
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
  public function testGetupdateMethodSetsAnErrorWhenAFieldDoesNotExistInAvailableFields()
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
  public function testGetupdateMethodSetsAnErrorWhenAvailableTableDoesNotExistInModels()
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
  public function testGetdeleteMethodReturnsStringForDeleteStatement()
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
  public function testGetdeleteMethodReturnsStringForDeleteStatementAndIgnoreExists()
  {
    $cfg = [
      'tables' => ['users'],
      'ignore' => true,
      'join' => []
    ];

    $result   = $this->sqlite->getDelete($cfg);
    $expected = 'DELETE OR IGNORE FROM "users"
';

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function testGetdeleteMethodReturnsStringForDeleteStatementAndJoinExists()
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
  public function testGetdeleteMethodReturnsEmptyStringWhenTablesProvidedAreMoreThanOne()
  {
    $cfg = [
      'tables' => ['users', 'roles']
    ];

    $this->assertSame('', $this->sqlite->getDelete($cfg));
  }

  /** @test */
  public function testGetjoinMethodReturnsStringForTheJoinClauseInTheQuery()
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
  public function testGetjoinMethodReturnsStringForTheJoinClauseInTheQueryAndTypeAndAliasExists()
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
  public function testGetjoinMethodReturnsEmptyStringWhenConfigurationsAreMissing()
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
  public function testGetwhereMethodReturnsAStringWithTheWherePartOfTheQuery()
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
  public function testGetwhereMethodReturnsEmptyStringWhenSomeOfConfigurationsAreMissing()
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
  public function testGetgroupbyMethodReturnsAStringWithGroupByClauseOfTheQuery()
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
  public function testGetgroupbyReturnsEmptyStringWhenConfigurationsAreMissing()
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
  public function testGetgroupbyMethodSetsAnErrorWhenAvailableFieldsConfigMissingOneOfTheFields()
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
  public function testGethavingMethodReturnsAStringForTheHavingClauseInAQuery()
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
  public function testGethavingMethodReturnsAStringForTheHavingClauseInAQueryAndCountExists()
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
  public function testGethavingMethodReturnsEmptyStringWhenConfigurationMissingSomeItems()
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
  public function testGetorderMethodReturnsAStringForTheOrderClauseInAQuery()
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
  public function testGetorderMethodReturnsEmptyStringWhenConfigurationsMissingSomeItems()
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
  public function testGetlimitMethodReturnsAStringWitTheLimitClauseInAQuery()
  {
    $result   = $this->sqlite->getLimit(['limit' => 2]);
    $expected = 'LIMIT 0, 2';

    $this->assertSame($expected, trim($result));

    $result   = $this->sqlite->getLimit(['limit' => 2, 'start' => 4]);
    $expected = 'LIMIT 4, 2';

    $this->assertSame($expected, trim($result));
  }

  /** @test */
  public function testGetlimitMethodReturnsEmptyStringWhenConfigurationsMissingTheLimitParam()
  {
    $this->assertSame('', $this->sqlite->getLimit([]));
  }

  /** @test */
  public function testGetlimitMethodReturnsEmptyStringWhenTheProvidedLimitIsNotAnInteger()
  {
    $this->assertSame('', $this->sqlite->getLimit(['limit' => 'foo']));
  }

  /** @test */
  public function testGetrawcreateMethodReturnsAStringWithCreateTableStatementByQueryingDatabaseByTableName()
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
  public function testGetrawcreateMethodReturnsEmptyStringIfTheGivenTableNameIsNotValid()
  {
    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY AUTOINCREMENT';
    });

    $this->assertSame('', $this->sqlite->getRawCreate('users**'));
  }

  /** @test */
  public function testGetrawcreateMethodReturnsEmptyStringIfRawQueryFailed()
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
  public function testGetcreatetableMethodReturnsAStringWithCreateTableStatement()
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
        ],
        'num' => [
          'default' => 0,
          'type' => 'foo'
        ],
        'login' => [
          'null' => false
        ]
      ]
    ];

    $result   = $this->sqlite->getCreateTable('users', $cfg);

    $expected = '
  CREATE TABLE "users" (
  "id" blob(32) NOT NULL,
  "username" text(255) NOT NULL,
  "role" text NOT NULL DEFAULT \'user\',
  "permission" text NOT NULL DEFAULT \'read\',
  "balance" real(10) DEFAULT NULL,
  "balance_before" real(10) NOT NULL DEFAULT 0,
  "created_at" text NOT NULL DEFAULT CURRENT_TIMESTAMP,
  "num"  NOT NULL DEFAULT 0,
  "login"  NOT NULL
)';

    $this->assertSame(trim($expected), trim($result));

    return $expected;
  }

  /** @test */
  public function testGetcreatetableMethodReturnsAStringWithCreateTableStatementWhenModelIsNotProvided()
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
  public function testGetcreatekeysMethodReturnsStringWithCreateKeysStatement()
  {
    $cfg = [
      'keys' => [
        'id_unique' => [
          'unique' => true,
          'columns' => ['id']
        ],
        'email_unique' => [
          'unique' => true,
          'columns' => ['email']
        ],
        'username_key' => [
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
    $expected = 'CREATE UNIQUE INDEX \'id_unique\' ON "users" ("id");
CREATE UNIQUE INDEX \'email_unique\' ON "users" ("email");
CREATE INDEX \'username_key\' ON "users" ("username");';

    $this->assertSame(trim($expected), trim($result));

    return $expected;
  }

  /**
   * @test
   * @depends getCreateKeys_method_returns_string_with_create_keys_statement
   */
  public function testGetcreatekeysMethodReturnsStringWithCreateKeysStatementWhenModelIsNull($query)
  {
    $this->clearCache();

    $this->createTable('users', function () {
      return "`id` int(11) NOT NULL,
              `username` varchar(255) NOT NULL,
              `email` varchar(255) NOT NULL";
    });

    // Create the keys from the query from the other test that this one depends on
    // So that the modelize method can get table structure
    foreach (explode(';', $query) as $q) {
      if (empty($q)) {
        continue;
      }
      $this->sqlite->rawQuery($q);
    }

    // Set expectations for the methods called on Cache class in modelize method
    $this->cache_mock->shouldReceive('get')
      ->once()
      ->andReturnFalse();

    $this->cache_mock->shouldReceive('set')
      ->once()
      ->andReturnTrue();

    $result = $this->sqlite->getCreateKeys('users');
    $expected = 'CREATE INDEX \'username_key\' ON "users" ("username");
CREATE UNIQUE INDEX \'email_unique\' ON "users" ("email");
CREATE UNIQUE INDEX \'id_unique\' ON "users" ("id");';

    $this->assertSame(trim($expected), trim($result));
  }

  /** @test */
  public function testGetcreatekeysMethodReturnsEmptyStringWhenConfigurationsMissingItems()
  {
    $this->assertSame('', $this->sqlite->getCreateKeys('users', [
      'fields' => [
        'id' => ['key' => 'PRI']
      ]
    ]));
  }

  /** @test */
  public function testGetcreatekeysMethodReturnsEmptyStringWhenModelCannotBeRetrievedFromDatabase()
  {
    $sqlite = \Mockery::mock(Sqlite::class)->makePartial();

    $sqlite->shouldReceive('modelize')
      ->once()
      ->with('users')
      ->andReturnNull();

    $this->assertSame('', $sqlite->getCreateKeys('users'));
  }

  /** @test */
  public function testGetcreateMethodReturnsAStringWithCreateTableStatementConsideringFieldsKeys()
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

    $result   = $this->sqlite->getCreate('users', $cfg);
    $expected = 'CREATE TABLE "users" (
  "id" blob(32) NOT NULL,
  "email" text(255) NOT NULL,
  "username" text(255) NOT NULL
);
CREATE UNIQUE INDEX \'primary\' ON "users" ("id");
CREATE UNIQUE INDEX \'unique\' ON "users" ("email");
CREATE INDEX \'key\' ON "users" ("username");';

    $this->assertSame(trim($expected), trim($result));
  }

  /** @test */
  public function testGetcreateMethodReturnsAStringWithCreateStatementAsIsFromGetcreatetableWhenKeysIndexDoesNotExist()
  {
    $cfg = [
      'fields' => [
        'email' => [
          'type' => 'varchar',
          'maxlength' => 255
        ],
      ]
    ];

    $result   = $this->sqlite->getCreate('users', $cfg);
    $expected = 'CREATE TABLE "users" (
  "email" text(255) NOT NULL
);';

    $this->assertSame(trim($expected), trim($result));
  }

  /** @test */
  public function testGetcreateMethodReturnsEmptyStringWhenGetcreatetableReturnsEmptyString()
  {
    $sqlite = \Mockery::mock(Sqlite::class)->makePartial();

    $sqlite->shouldReceive('getCreateTable')
      ->once()
      ->with('users', ['fields' => []])
      ->andReturn('');

    $this->assertSame('', $sqlite->getCreate('users', ['fields' => []]));
  }

  /** @test */
  public function testGetcreateMethodReturnsAStringWithCreateTableStatementFromTableStructureInDb()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT PRIMARY KEY,
              email TEXT NOT NULL,
              username TEXT NOT NULL';
    });

    $this->sqlite->rawQuery('CREATE UNIQUE INDEX \'email\' ON "users" ("email")');

    $result   = $this->sqlite->getCreate('users');
    $expected = 'CREATE TABLE "users" (
  "id" INTEGER,
  "email" TEXT NOT NULL,
  "username" TEXT NOT NULL,
  PRIMARY KEY ("id")
);
CREATE UNIQUE INDEX \'email\' ON "users" ("email");
';

    $this->assertSame(trim($expected), trim($result));
  }

  /** @test */
  public function testCreateindexMethodCreatesIndexForTheGivensTableAndColumns()
  {
    $this->createTable('users', function () {
      return "`email` varchar(255) NOT NULL";
    });

    $result = $this->sqlite->createIndex('users', 'email', true);
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

    $result2 = $this->sqlite->createIndex('users', ['email', 'username'], false, 20, 'ASC');
    $model2  = $this->getTableStructure('users');

    $this->assertTrue($result2);
    $this->assertTrue(isset($model2['keys']['users_email_username']['unique']));
    $this->assertSame(0, $model2['keys']['users_email_username']['unique']);
  }

  /** @test */
  public function testCreateindexMethodSetsAnErrorWhenTheGivenColumnNameIsNotValid()
  {
    $this->expectException(\Exception::class);

    $this->sqlite->setErrorMode(Errors::E_DIE);

    $this->sqlite->createIndex('users', 'email**');
  }

  /** @test */
  public function testDeleteindexMethodDeletesTheGivenIndex()
  {
    $this->createTable('users', function () {
      return "`email` varchar(255) NOT NULL";
    });

    $this->sqlite->rawQuery("CREATE INDEX 'key' ON \"users\" (\"email\");");

    $result = $this->sqlite->deleteIndex('users', 'key');

    $model  = $this->getTableStructure('users');

    $this->assertTrue($result);
    $this->assertIsArray($model['keys']);
    $this->assertArrayNotHasKey('users_email', $model['keys']);
  }

  /** @test */
  public function testDeleteindexMethodReturnsFalseWhenTheGivenKeyIsNotAValidName()
  {
    $this->assertFalse(
      $this->sqlite->deleteIndex('users', 'key***')
    );
  }

  /** @test */
  public function testCreatedatabaseMethodCreatesTheGivenDatabase()
  {
    $result = $this->sqlite->createDatabase('test_db');

    $this->assertTrue($result);
    $this->assertFileExists($this->getNonPublicProperty('host') . 'test_db.sqlite');

    $result2 = $this->sqlite->createDatabase('test_db_2.sqlite');

    $this->assertTrue($result2);
    $this->assertFileExists($this->getNonPublicProperty('host') . 'test_db.sqlite');
  }

  /** @test */
  public function testCreatedatabaseMethodReturnsFalseWhenTheGivenDatabaseNameIsNotValid()
  {
    $this->assertFalse(
      $this->sqlite->createDatabase('test_db/')
    );

    $this->assertFalse(
      $this->sqlite->createDatabase('test_db\\')
    );
  }

  /** @test */
  public function testDropdatabaseMethodDropsTheGivenDatabase()
  {
    $this->createFile('db_testing.sqlite', '', 'db');
    $result = $this->sqlite->dropDatabase('db_testing');
    $this->assertFileDoesNotExist('db_testing.sqlite');
    $this->assertTrue($result);

    $this->createFile('db_testing.sqlite', '', 'db');
    $result2 = $this->sqlite->dropDatabase('db_testing.sqlite');
    $this->assertFileDoesNotExist('db_testing.sqlite');
    $this->assertTrue($result2);
  }

  /** @test */
  public function testDropdatabaseMethodReturnsFalseWhenTheGivenDatabaseNameIsNotValid()
  {
    $this->assertFalse(
      $this->sqlite->dropDatabase('db_testing/')
    );

    $this->assertFalse(
      $this->sqlite->dropDatabase('db_testing\\')
    );
  }

  /** @test */
  public function testDbsizeMethodReturnsDbFileSize()
  {
    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL';
    });

    $this->sqlite->rawQuery("INSERT INTO users ('name') VALUES ('John')");

    $expected_size = filesize($this->getNonPublicProperty('host').'testing.sqlite');

    $this->assertSame(
      $expected_size,
      $this->sqlite->dbSize('testing')
    );

    $this->assertSame(
      $expected_size,
      $this->sqlite->dbSize('testing.sqlite')
    );

    $this->assertSame(0, $this->sqlite->dbSize('foo'));
  }

  /** @test */
  public function testCreatetableMethodReturnsAStringOfCreateTableStatementFromGivenArguments()
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

    $expected = 'CREATE TABLE users (
"email" text(255) NOT NULL,
"id" int UNSIGNED NOT NULL,
"balance" decimal NOT NULL DEFAULT \'0\'
); PRAGMA encoding="UTF-8";';

    $this->assertSame(
      $expected,
      $this->sqlite->createTable('users', $columns)
    );
  }

  /** @test */
  public function testGetcreateconstraintsMethodReturnsAStringOfCreateConstraintsStatement()
  {
    $model = [[
      'constraint' => 'user_role',
      'foreign_key' => true,
      'unique' => false,
      'primary_key' => false,
      'columns' => ['role_id'],
      'delete' => 'CASCADE',
      'update' => 'RESTRICT',
      'ref_table' => 'roles'
    ],[
      'constraint' => 'user_profile',
      'unique' => true,
      'columns' => ['profile_id'],
      'delete' => 'CASCADE',
      'update' => 'CASCADE',
      'ref_table' => 'profiles'
    ],[
      'constraint' => 'user_profile',
      'primary_key' => true,
      'columns' => ['profile_id'],
      'delete' => 'CASCADE',
      'update' => 'CASCADE',
      'ref_table' => 'profiles'
    ]];

    $expected = 'ALTER TABLE "users"
  ADD CONSTRAINT "user_role" FOREIGN KEY ("role_id") REFERENCES "roles"("role_id")  ON DELETE CASCADE ON UPDATE RESTRICT,
  ADD CONSTRAINT "user_profile" UNIQUE ("profiles_profile_id") REFERENCES "profiles"("profile_id")  ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT "user_profile" PRIMARY KEY ("profiles_profile_id") REFERENCES "profiles"("profile_id")  ON DELETE CASCADE ON UPDATE CASCADE;';

    $this->assertSame(
      $expected,
      $this->sqlite->getCreateConstraints('users', $model)
    );
  }

  /** @test */
  public function testGetqueryvaluesMethodReturnsQueryValues()
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

    $result   = $this->sqlite->getQueryValues($cfg);
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
  public function testGetqueryvaluesMethodReturnsEmptyArrayWhenTheGivenConfigurationHasEmptyValuesKey()
  {
    $this->assertSame([], $this->sqlite->getQueryValues([]));
    $this->assertSame([], $this->sqlite->getQueryValues(['values' => []]));
  }

  /** @test */
  public function testGetfieldslistMethodReturnsFieldsListForTheGivenTables()
  {
    $this->createTable('users', function () {
      return 'username TEXT, name TEXT';
    });

    $this->createTable('roles', function () {
      return 'name TEXT';
    });

    $this->assertSame(
      ['users.username', 'users.name'],
      $this->sqlite->getFieldsList('users')
    );

    $this->assertSame(
      ['users.username', 'users.name', 'roles.name'],
      $this->sqlite->getFieldsList(['users', 'roles'])
    );
  }

  /** @test */
  public function testGetfieldslistMethodThrowsAnExceptionWhenTableNotFound()
  {
    $this->expectException(\Exception::class);

    $this->sqlite->getFieldsList('users');
  }

  /** @test */
  public function testGetprimaryMethodReturnsPrimaryKeysOfTheGivenTableAsAnArray()
  {
    $this->createTable('users', function () {
      return 'id BIGINT PRIMARY KEY, username TEXT UNIQUE';
    });

    $this->createTable('roles', function () {
      return 'name TEXT';
    });

    $this->assertSame(
      ['id'],
      $this->sqlite->getPrimary('users')
    );

    $this->assertSame(
      [],
      $this->sqlite->getPrimary('roles')
    );
  }

  /** @test */
  public function testGetuniqueprimaryMethodReturnsTheUniquePrimaryKeyOfTheGivenTableAsString()
  {
    $this->createTable('users', function () {
      return 'id BIGINT PRIMARY KEY, username TEXT';
    });

    $this->createTable('roles', function () {
      return 'name TEXT';
    });

    $this->assertSame(
      'id',
      $this->sqlite->getUniquePrimary('users')
    );

    $this->assertNull(
      $this->sqlite->getUniquePrimary('roles')
    );
  }

  /** @test */
  public function testGetuniquekeysMethodReturnsTheUniqueKeysOfTheGivenTableAsAnArray()
  {
    $this->createTable('users', function () {
      return 'username VARCHAR(22) NOT NULL, 
      email VARCHAR(100) NOT NULL,
      name VARCHAR(200) NOT NULL,
      UNIQUE (email)';
    });

    $this->assertSame(
      ['email'],
      $this->sqlite->getUniqueKeys('users')
    );

    $this->createTable('users', function () {
      return 'username VARCHAR(22) NOT NULL, 
      email VARCHAR(100) NOT NULL,
      name VARCHAR(200) NOT NULL,
      UNIQUE (username, email)';
    });

    $this->assertSame(
      ['username', 'email'],
      $this->sqlite->getUniqueKeys('users')
    );
  }

  /** @test */
  public function testGetcfgMethodReturnsTheConfig()
  {
    $this->assertSame(
      $this->getNonPublicProperty('cfg'),
      $this->sqlite->getCfg()
    );
  }

  /** @test */
  public function testGethostMethodReturnsTheHost()
  {
    $this->assertSame(
      $this->getNonPublicProperty('host'),
      $this->sqlite->getHost()
    );
  }

  /** @test */
  public function testGetconnectioncodeMethodReturnsConnectionCode()
  {
    $this->assertSame(
      $this->getNonPublicProperty('connection_code'),
      $this->sqlite->getConnectionCode()
    );
  }

  /** @test */
  public function testIsaggregatefunctionMethodReturnsTrueIfTheGivenNameIsAggregateFunction()
  {
    $this->assertTrue(Sqlite::isAggregateFunction('count(*)'));
    $this->assertTrue(Sqlite::isAggregateFunction('COUNT(*)'));
    $this->assertTrue(Sqlite::isAggregateFunction('COUNT(id)'));
    $this->assertTrue(Sqlite::isAggregateFunction('COUNT('));
    $this->assertTrue(Sqlite::isAggregateFunction('sum(*)'));
    $this->assertTrue(Sqlite::isAggregateFunction('SUM(*)'));
    $this->assertTrue(Sqlite::isAggregateFunction('avg(*)'));
    $this->assertTrue(Sqlite::isAggregateFunction('AVG(*)'));
    $this->assertTrue(Sqlite::isAggregateFunction('min(*)'));
    $this->assertTrue(Sqlite::isAggregateFunction('MIN(*)'));
    $this->assertTrue(Sqlite::isAggregateFunction('max(*)'));
    $this->assertTrue(Sqlite::isAggregateFunction('MAX(*)'));
    $this->assertTrue(Sqlite::isAggregateFunction('GROUP_CONCAT('));
    $this->assertTrue(Sqlite::isAggregateFunction('group_concat('));

    $this->assertFalse(Sqlite::isAggregateFunction('id'));
    $this->assertFalse(Sqlite::isAggregateFunction('count'));
    $this->assertFalse(Sqlite::isAggregateFunction('min'));
    $this->assertFalse(Sqlite::isAggregateFunction('MAX'));
    $this->assertFalse(Sqlite::isAggregateFunction('avg'));
  }

  /** @test */
  public function testGetengineMethodReturnsEnginesName()
  {
    $this->assertSame('sqlite', $this->sqlite->getEngine());
  }

  /** @test */
  public function testGetcurrentMethodReturnsTheCurrentDatabaseOfTheCurrentConnection()
  {
    $this->assertSame('main', $this->sqlite->getCurrent());
  }

  /** @test */
  public function testRawqueryMethodExecutesTheGivenQueryUsingOriginalPdoFunction()
  {
    $this->createTable('users', function () {
      return 'id BIGINT PRIMARY KEY,
              username TEXT NOT NULL';
    });

    $this->insertOne('users', ['username' => 'foo']);

    $result = $this->sqlite->rawQuery("SELECT * FROM users");

    $this->assertInstanceOf(\PDOStatement::class, $result);
    $this->assertSame('foo', $result->fetchObject()->username);
  }

  /** @test */
  public function testParsequeryMethodParsesAnSqlAndReturnAnArray()
  {
    $result = $this->sqlite->parseQuery(
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
  public function testParsequeryMethodReturnsNullWhenTheGivenArgIsNotAQuery()
  {
    $this->assertNull(
      $this->sqlite->parseQuery('foo')
    );
  }

 /** @test */
  public function testArrangeconditionsMethodTest()
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

    $this->sqlite->arrangeConditions($conditions, $cfg);

    $this->assertSame($expected, $conditions);
  }

  /** @test */
  public function testRemovevirtualMethodTest()
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
      $this->sqlite->removeVirtual($cfg)
    );
  }

  /** @test */
  public function testFindreferencesMethodReturnsAnArrayWithForeignKeyReferencesForTheGivenColumn()
  {
    $this->createTable('roles', function () {
      return 'id BIGINT PRIMARY KEY,
              name VARCHAR(255)';
    });

    $this->createTable('users', function () {
      return 'id BIGINT PRIMARY KEY,
              username TEXT UNIQUE,
              created_at DATETIME DEFAULT NULL,
              role_id BIGINT,
              FOREIGN KEY(role_id) REFERENCES roles(id) ON UPDATE CASCADE ON DELETE RESTRICT';
    });

    $this->setCacheExpectations();

    $this->assertSame(
      ['users.role_id'],
      $this->sqlite->findReferences('roles.id')
    );

    $this->assertSame(
      [],
      $this->sqlite->findReferences('users.role_id')
    );
  }

  /** @test */
  public function testFindreferencesMethodReturnsNullIfTheProvidedColumnNameDoesNotHaveTableName()
  {
    $this->assertNull(
      $this->sqlite->findReferences('role_id')
    );
  }

  /** @test */
  public function testFindrelationsMethodReturnsAnArrayOfATableThatHasRelationsToMoreThanOneTables()
  {
    $this->setCacheExpectations();

    $this->createTable('roles', function () {
      return 'id BIGINT PRIMARY KEY,
              name TEXT';
    });

    $this->createTable('profiles', function () {
      return 'id BIGINT PRIMARY KEY,
              name TEXT';
    });

    $this->createTable('users', function () {
      return 'id BIGINT PRIMARY KEY,
              role_id BIGINT,
              profile_id BIGINT,
              FOREIGN KEY (role_id) REFERENCES roles (id) ON UPDATE CASCADE ON DELETE RESTRICT,
              FOREIGN KEY (profile_id) REFERENCES profiles (id) ON UPDATE CASCADE ON DELETE RESTRICT';
    });


    $result   = $this->sqlite->findRelations('roles.id');
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
  public function testFindrelationsMethodReturnsNullWhenTheGivenNameDoesNotHasTableName()
  {
    $this->assertNull(
      $this->sqlite->findRelations('id')
    );
  }

  /** @test */
  public function testAddQueryMethodsRemovesFromTheBeginningOfQueriesListIfMaxQueriesNumbersExceeded()
  {
    $method = $this->getNonPublicMethod('_add_query');

    $args = [
      'SELECT * FROM users WHERE id = ?',
      'SELECT',
      1,
      ['option_1' => 'option_1_value']
    ];

    $this->setNonPublicPropertyValue('max_queries', 2);

    $method->invoke($this->sqlite, '123', ...$args);
    $method->invoke($this->sqlite, '1234', ...$args);
    $method->invoke($this->sqlite, '12345', ...$args);

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
  public function testRemoveQueryMethodRemovesFromTheBeginningOfQueriesWithTheGivenHash()
  {
    $method = $this->getNonPublicMethod('_remove_query');

    $this->setNonPublicPropertyValue('queries', [
      '123' => ['foo' => 'bar'],
      '1234' => ['foo2' => 'bar2'],
      '12345' => '123'
    ]);

    $method->invoke($this->sqlite, '123');
    $method->invoke($this->sqlite, '123456789');

    $this->assertSame(
      ['1234' => ['foo2' => 'bar2']],
      $this->getNonPublicProperty('queries')
    );
  }

  /** @test */
  public function testUpdateQueryUpdatesAQueryInQueryListByTheGivenHash()
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
      ->invoke($this->sqlite, '1234');

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
  public function testUpdateQueryRemovesAllHashesFromListQueriesAndQueriesIfExpired()
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
      ->invoke($this->sqlite, '1234');

    $queries = $this->getNonPublicProperty('queries');

    $this->assertCount(1, $queries);
    $this->assertArrayHasKey('1234', $queries);
    $this->assertArrayNotHasKey('12', $queries);

    $list_queries = $this->getNonPublicProperty('list_queries');

    $this->assertCount(1, $list_queries);
    $this->assertSame('1234', $list_queries[0]['hash']);
  }

  /** @test */
  public function testUpdateQueryThrowsAnExceptionWhenTheGivenHashDoesNotExistInListQueries()
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
      ->invoke($this->sqlite, '1234');
  }

  /** @test */
  public function testUpdateQueryThrowsAnExceptionWhenTheGivenHashDoesNotExist()
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
      ->invoke($this->sqlite, '123');
  }

  /** @test */
  public function testGetCacheMethodReturnsTableStructureFromDatabaseWhenCacheDoesNotExistAndSavesItInCacheProperty()
  {
    $this->createTable('users', function () {
      return 'id BIGINT NOT NULL PRIMARY KEY,
              email CHAR(20) NOT NULL';
    });

    $this->sqlite->rawQuery("CREATE UNIQUE INDEX 'users_email' ON users (email)");

    $this->setCacheExpectations();

    $result = $this->getNonPublicMethod('_get_cache')
      ->invoke($this->sqlite, 'users');

    $expected = [
      'keys' => [
        'users_email' => [
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
        ]
      ],
      'cols' => [
        'email' => ['users_email'],
        'id' => ['PRIMARY']
      ],
      'fields' => [
        'id' => [
          'position' => 1,
          'null' => 0,
          'key' => 'PRI',
          'default' => null,
          'extra' => null,
          'maxlength' => null,
          'signed' => 1,
          'defaultExpression' => false,
          'type' => 'INTEGER'
        ],
        'email' => [
          'position' => 2,
          'null' => 0,
          'key' => null,
          'default' => null,
          'extra' => null,
          'maxlength' => 20,
          'signed' => 1,
          'defaultExpression' => false,
          'type' => 'TEXT'
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
  public function testGetCacheMethodReturnsAllTablesNamesFromDatabaseWhenCacheDoesNotExistAndSavesItInCacheProperty()
  {
    $this->createTable('users', function () {
      return 'id BIGINT PRIMARY KEY,
              email VARCHAR(255) NOT NULL UNIQUE';
    });

    $this->createTable('roles', function () {
      return 'id BIGINT PRIMARY KEY';
    });

    $this->setCacheExpectations();

    $result = $this->getNonPublicMethod('_get_cache')
      ->invoke($this->sqlite, 'main', 'tables');

    $this->assertSame($expected = ['users', 'roles'], $result);
    $this->assertNotEmpty(
      $cache = $this->getNonPublicProperty('cache')
    );
    $this->assertSame($expected, current($cache));
  }

  /** @test */
  public function testGetCacheMethodReturnsAllDatabasesNamesWhenCacheDoesNotExistAndSavesItInCacheProperty()
  {
    $this->setCacheExpectations();

    $result = $this->getNonPublicMethod('_get_cache')
      ->invoke($this->sqlite, '', 'databases');

    $this->assertTrue(in_array('testing', $result));
    $this->assertNotEmpty(
      $cache = $this->getNonPublicProperty('cache')
    );
    $this->assertTrue(in_array('testing', current($cache)));
  }

  /** @test */
  public function testGetCacheMethodReturnsTableStructureFromCachePropertyWhenExists()
  {
    $this->setNonPublicPropertyValue('cache', [
      'sqlite/' . md5(BBN_DATA_PATH . 'db/') . '/users'
      => [
        'foo' => 'bar'
      ]
    ]);

    $this->assertSame(
      ['foo' => 'bar'],
      $this->getNonPublicMethod('_get_cache')->invoke(
       $this->sqlite, 'users'
      )
    );
  }

  /** @test */
  public function testGetCacheMethodReturnsTableStructureFromCacheClassWhenExistsAndDoesNotExistInCacheProperty()
  {
    $cache_name = $this->getNonPublicMethod('_db_cache_name')
      ->invoke($this->sqlite, 'users', 'columns');

    $cache_name_method = $this->getNonPublicMethod('_cache_name');


    $this->cache_mock->shouldReceive('get')
      ->with(
        $cache_name_method->invoke($this->sqlite, $cache_name)
      )
      ->andReturn(['foo' => 'bar']);

    $result = $this->getNonPublicMethod('_get_cache')
      ->invoke($this->sqlite, 'users');

    $this->assertSame(['foo' => 'bar'], $result);
    $this->assertNotEmpty(
      $cache = $this->getNonPublicProperty('cache')
    );
    $this->assertSame(['foo' => 'bar'], current($cache));
  }

  /** @test */
  public function testGetCacheMethodReturnsTableStructureFromDatabaseWhenCacheExistsButForceIsTrue()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function() {
      return 'id INT(11)';
    });

    $this->setNonPublicPropertyValue('cache', [
      'sqlite/' . md5(BBN_DATA_PATH . 'db/'.dirname('main')) . '/users'
      => [
        'foo' => 'bar'
      ]
    ]);

    $result = $this->getNonPublicMethod('_get_cache')
      ->invoke($this->sqlite, 'main', 'tables', true);

    $this->assertNotSame(['foo' => 'bar'], $result);
  }

  /** @test */
  public function testGetCacheMethodThrowsReturnsEmptyResultsWhenItFailsToRetrieveTableStructure()
  {
    $this->setCacheExpectations();

    $result = $this->getNonPublicMethod('_get_cache')
      ->invoke($this->sqlite, 'history');

    $this->assertSame(
      ['keys' => [], 'cols' => [], 'fields' => []],
      $result
    );
  }

  /** @test */
  public function testGetCacheMethodThrowsAnExceptionWhenItFailsToRetrieveTablesNames()
  {
    $this->expectException(\Exception::class);

    $this->setCacheExpectations();

    $this->setNonPublicPropertyValue('current', null);

    $this->getNonPublicMethod('_get_cache')
      ->invoke($this->sqlite, 'main', 'tables');
  }

  /** @test */
  public function testGetCacheMethodThrowsAnExceptionWhenItFailsToRetrieveDatabasesNames()
  {
    $this->expectException(\Exception::class);

    $this->setCacheExpectations();

    $this->setNonPublicPropertyValue('current', null);

    $this->getNonPublicMethod('_get_cache')
      ->invoke($this->sqlite, '', 'databases');
  }

  /** @test */
  public function testDbCacheNameReturnsCacheNameOfDatabaseStructure()
  {
    $method = $this->getNonPublicMethod('_db_cache_name');

    $this->assertSame(
      'sqlite/' . md5(BBN_DATA_PATH . 'db/') . '/users',
      $method->invoke($this->sqlite, 'users', 'columns')
    );

    $this->assertSame(
      'sqlite/' . md5(BBN_DATA_PATH . 'db/') . '/table_name',
      $method->invoke($this->sqlite, 'table_name', 'tables')
    );

    $this->assertSame(
      'sqlite/' . md5(BBN_DATA_PATH . 'db/') . '/main',
      $method->invoke($this->sqlite, '', 'tables')
    );

    $this->assertSame(
      'sqlite/' . md5(BBN_DATA_PATH . 'db/') . '/_bbn-database',
      $method->invoke($this->sqlite, '', 'databases')
    );
  }

  /** @test */
  public function testModelizeMethodReturnsTableStructureAsAnIndexedArrayForTheGivenTableName()
  {
    $this->setCacheExpectations();

    $this->createTable('roles', function () {
      return 'id BIGINT PRIMARY KEY NOT NULL,
              name VARCHAR(25)';
    });

    $this->createTable('users', function () {
      return 'id BINARY(16) PRIMARY KEY NOT NULL,
              name CHAR(25) NOT NULL,
              username CHAR(50) NOT NULL,
              created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              role_id BIGINT NOT NULL,
              CONSTRAINT `user_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (id) ON DELETE CASCADE ON UPDATE RESTRICT';
    });

    $this->sqlite->rawQuery('CREATE UNIQUE INDEX username ON users (username)');


    $users_expected = [
      'keys' => [
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
        'roles_role_id' => [
          'columns' => ['role_id'],
          'ref_db' => 'main',
          'ref_table' => 'roles',
          'ref_column' => 'id',
          'constraint' => 'roles_role_id',
          'update'    => 'RESTRICT',
          'delete'    => 'CASCADE',
          'unique'    => 0
        ]
      ],
      'cols' => [
        'username' => ['username'],
        'id' => ['PRIMARY'],
        'role_id' => ['roles_role_id']
      ],
      'fields' => [
        'id' => [
          'position' => 1,
          'null'  => 0,
          'key' => 'PRI',
          'default' => null,
          'extra' => null,
          'maxlength' => 16,
          'signed' => 1,
          'defaultExpression' => false,
          'type' => 'TEXT',
        ],
        'name' => [
          'position' => 2,
          'null'  => 0,
          'key' => null,
          'default' => null,
          'extra' => null,
          'maxlength' => 25,
          'signed' => 1,
          'defaultExpression' => false,
          'type' => 'TEXT',
        ],
        'username' => [
          'position' => 3,
          'null'  => 0,
          'key' => null,
          'default' => null,
          'extra' => null,
          'maxlength' => 50,
          'signed' => 1,
          'defaultExpression' => false,
          'type' => 'TEXT',
        ],
        'created_at' => [
          'position' => 4,
          'null'  => 0,
          'key' => null,
          'default' => 'CURRENT_TIMESTAMP',
          'extra' => null,
          'maxlength' => null,
          'signed' => 1,
          'defaultExpression' => true,
          'type' => 'INTEGER',
        ],
        'role_id' => [
          'position' => 5,
          'null'  => 0,
          'key' => null,
          'default' => null,
          'extra' => null,
          'maxlength' => null,
          'signed' => 1,
          'defaultExpression' => false,
          'type' => 'INTEGER',

        ]
      ]
    ];

    $this->assertSame($users_expected, $this->sqlite->modelize('users'));

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
          'null'  => 0,
          'key' => 'PRI',
          'default' => null,
          'extra' => null,
          'maxlength' => null,
          'signed' => 1,
          'defaultExpression' => false,
          'type' => 'INTEGER',
        ],
        'name' => [
          'position' => 2,
          'null'  => 1,
          'key' => null,
          'default' => null,
          'extra' => null,
          'maxlength' => 25,
          'signed' => 1,
          'defaultExpression' => false,
          'type' => 'TEXT',
        ]
      ]
    ];

    $this->assertSame($roles_expected, $this->sqlite->modelize('roles'));

    $this->assertSame(
      [
        "roles" => $roles_expected,
        "users" => $users_expected
      ],
      $this->sqlite->modelize('*')
    );
  }

  /** @test */
  public function testModelizeMethodDoesNotGetFromCacheIfTheGivenForceParameterIsTrue()
  {
    $this->createTable('users', function () {
      return 'id INT(11)';
    });

    $this->cache_mock->shouldNotReceive('cacheGet');

    $this->cache_mock->shouldReceive('set')
      ->once()
      ->with(
        Str::encodeFilename(str_replace('\\', '/', \get_class($this->sqlite)), true).'/' .
        'sqlite/' . md5(BBN_DATA_PATH . 'db/') . '/users',
        $expected = [
          'keys' => [],
          'cols' => [],
          'fields' => [
            'id' => [
              'position' => 1,
              'null' => 1,
              'key' => null,
              'default' => null,
              'extra' => null,
              'maxlength' => 11,
              'signed' => 1,
              'defaultExpression' => false,
              'type' => 'INTEGER'
            ]
          ],
        ],
        $this->getNonPublicProperty('cache_renewal')
      )
      ->andReturnTrue();

    $result = $this->sqlite->modelize('users', true);

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function testEnabletriggerMethodEnablesTriggerFunction()
  {
    $this->setNonPublicPropertyValue('_triggers_disabled', true);

    $result = $this->sqlite->enableTrigger();

    $this->assertFalse(
      $this->getNonPublicProperty('_triggers_disabled')
    );

    $this->assertInstanceOf(Sqlite::class, $result);
  }

  /** @test */
  public function testDisabletriggerMethodDisablesTriggerFunctions()
  {
    $this->setNonPublicPropertyValue('_triggers_disabled', false);

    $result = $this->sqlite->disableTrigger();

    $this->assertTrue(
      $this->getNonPublicProperty('_triggers_disabled')
    );

    $this->assertInstanceOf(Sqlite::class, $result);
  }

  /** @test */
  public function testIstriggerenabledMethodChecksIfTriggerFunctionIsEnabled()
  {
    $this->setNonPublicPropertyValue('_triggers_disabled', false);

    $this->assertTrue(
      $this->sqlite->isTriggerEnabled()
    );
  }

  /** @test */
  public function testIstriggerdisabledMethodChecksIfTriggerFunctionsIsDisabled()
  {
    $this->setNonPublicPropertyValue('_triggers_disabled', true);

    $this->assertTrue(
      $this->sqlite->isTriggerDisabled()
    );
  }

  /** @test */
  public function testSettriggerMethodRegisterACallbackToBeAppliedEveryTimeTheMethodsKindAreUsed()
  {
    $default_triggers = $this->getNonPublicProperty('_triggers');

    $this->createTable('users', function () {
      return 'email TEXT';
    });

    $this->createTable('roles', function () {
      return 'name TEXT';
    });

    $expected = 'A call back function';

    $result = $this->sqlite->setTrigger(function () use ($expected) {
      return $expected;
    });

    $this->assertInstanceOf(Sqlite::class, $result);

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

    $this->sqlite->setTrigger(function () use ($expected) {
      return $expected;
    }, 'insert', 'after', 'users');

    $triggers = $this->getNonPublicProperty('_triggers');

    $this->assertSame(
      $expected,
      $triggers['INSERT']['after']['users'][0]()
    );

    // Another test
    $this->setNonPublicPropertyValue('_triggers',  $default_triggers);

    $this->sqlite->setTrigger(function () use ($expected) {
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
  public function testGettriggersMethodReturnsTheCurrentTriggers()
  {
    $this->assertSame(
      $this->getNonPublicProperty('_triggers'),
      $this->sqlite->getTriggers()
    );
  }

  /** @test */
  public function testAddKindMethodAddsTheGivenTypeToTheGivenArgs()
  {
    $method = $this->getNonPublicMethod('_add_kind');

    $this->assertSame(
      ['UPDATE','foo'],
      $method->invoke($this->sqlite, ['foo'], 'update')
    );

    $this->assertSame(
      [['foo', 'kind' => 'SELECT']],
      $method->invoke($this->sqlite, [['foo']])
    );

    $this->assertNull(
      $method->invoke($this->sqlite, ['foo' => ['bar']])
    );
  }

  /** @test */
  public function testTriggerMethodLaunchesAFunctionBeforeOrAfterIfRegisteredAndCallable()
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
      $method->invoke($this->sqlite, $cfg)
    );
  }

  /** @test */
  public function testTriggerMethodDoesNotLaunchTheFunctionIfTheCallbackReturnFalsyResult()
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
      $method->invoke($this->sqlite, $cfg)
    );
  }

  /** @test */
  public function testTriggerMethodDoesNotLaunchTheFunctionIfTableIsNotRegistered()
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
      $method->invoke($this->sqlite, $cfg)
    );
  }

  /** @test */
  public function testTriggerMethodDoesNotLaunchTheFunctionTheGivenTriggerDoesNotExist()
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
      $method->invoke($this->sqlite, $cfg)
    );
  }


  /** @test */
  public function testTriggerMethodDoesNotLaunchTheFunctionIfNoTableNameIsGiven()
  {
    $cfg = [
      'kind'   => 'UPDATE',
      'moment' => 'before'
    ];

    $method = $this->getNonPublicMethod('_trigger');

    $this->assertSame(
      array_merge($cfg, ['trig' => 1, 'run' => 1]),
      $method->invoke($this->sqlite, $cfg)
    );
  }

  /** @test */
  public function testTriggerMethodReturnsTheConfigArrayAsIsIfTriggersIsDisabledAndMomentIsAfter()
  {
    $this->setNonPublicPropertyValue('_triggers_disabled', true);

    $method = $this->getNonPublicMethod('_trigger');

    $this->assertSame(
      ['moment' => 'after'],
      $method->invoke($this->sqlite, ['moment' => 'after'])
    );
  }

  /** @test */
  public function testTriggerMethodReturnsTheConfigArrayAddingTrigAndRunWhenTriggersIsDisabledAndMomentIsBefore()
  {
    $this->setNonPublicPropertyValue('_triggers_disabled', true);

    $method = $this->getNonPublicMethod('_trigger');

    $this->assertSame(
      ['moment' => 'before', 'run' => 1, 'trig' => 1],
      $method->invoke($this->sqlite, ['moment' => 'before'])
    );
  }

  /** @test */
  public function testAddPrimaryMethodAddsARandomPrimaryValueWhenMissingFromTheGivenArguments()
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

    $method->invokeArgs($this->sqlite, [&$cfg]);

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

    $method->invokeArgs($this->sqlite, [&$cfg2]);

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

    $method->invokeArgs($this->sqlite, [&$cfg3]);

    $this->assertCount(1, $cfg3['values']);
  }

  /** @test */
  public function testAddPrimaryMethodDoesNotAddaARandomPrimaryValueIfGivenArgumentsDoesNotMatchConditions()
  {
    $method = $this->getNonPublicMethod('_add_primary');

    $cfg = $old_cfg = [
      'primary'      => 'id',
      'primary_type' => 'binary',
      'auto_increment' => false,
      'fields' => ['id', 'name'],
      'values' => ['John', 'Doe']
    ];

    $method->invokeArgs($this->sqlite, [&$cfg]);

    $this->assertSame($old_cfg, $cfg);

    $cfg = $old_cfg = [
      'primary'      => 'id',
      'primary_type' => 'binary',
      'auto_increment' => false,
      'fields' => ['email', 'name'],
      'values' => ['John']
    ];

    $method->invokeArgs($this->sqlite, [&$cfg]);

    $this->assertSame($old_cfg, $cfg);

    $cfg = $old_cfg = [
      'primary'      => 'id',
      'primary_type' => 'binary',
      'auto_increment' => true,
      'fields' => ['id', 'name'],
      'values' => ['John']
    ];

    $method->invokeArgs($this->sqlite, [&$cfg]);

    $this->assertSame($old_cfg, $cfg);

    $cfg = $old_cfg = [
      'primary'      => '',
      'primary_type' => 'binary',
      'auto_increment' => false,
      'fields' => ['id', 'name'],
      'values' => ['John']
    ];

    $method->invokeArgs($this->sqlite, [&$cfg]);

    $this->assertSame($old_cfg, $cfg);

    $cfg = $old_cfg = [
      'primary_type' => 'binary',
      'auto_increment' => false,
      'fields' => ['id', 'name'],
      'values' => ['John']
    ];

    $method->invokeArgs($this->sqlite, [&$cfg]);

    $this->assertSame($old_cfg, $cfg);
  }

  /** @test */
  public function testExecMethodInsertTest()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY AUTOINCREMENT,
              email TEXT UNIQUE NOT NULL,
              name TEXT NOT NULL';
    });

    $cfg = [
      'tables'  => 'users',
      'kind'    => 'INSERT',
      'fields'  => ['email' => 'john@mail.com', 'name' => 'John']
    ];

    $method = $this->getNonPublicMethod('_exec');

    $this->assertSame(1, $method->invoke($this->sqlite, $cfg));

    $this->assertDatabaseHas('users', 'email', 'john@mail.com');
    $this->assertDatabaseHas('users', 'name', 'John');

    $this->assertSame(
      $this->sqlite->query("SELECT id FROM users LIMIT 1")->fetchObject()->id,
      $this->getNonPublicProperty('last_insert_id')
    );

    $this->assertNotEmpty(
      $this->getNonPublicProperty('last_cfg')
    );
  }

  /** @test */
  public function testExecMethodUpdateTest()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY AUTOINCREMENT,
              email VARCHAR(25) UNIQUE NOT NULL,
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
      $method->invoke($this->sqlite, $cfg)
    );

    $this->assertDatabaseDoesNotHave('users', 'name', 'John');
    $this->assertDatabaseHas('users', 'name', 'John Doe');
    $this->assertDatabaseHas('users', 'name', 'Smith');

    $this->assertNotEmpty(
      $this->getNonPublicProperty('last_cfg')
    );
  }

  /** @test */
  public function testExecMethodDeleteTest()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY AUTOINCREMENT,
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
      $method->invoke($this->sqlite, $cfg)
    );

    $this->assertDatabaseHas('users', 'name', 'Sam');
    $this->assertDatabaseDoesNotHave('users', 'name', 'John');

    $this->assertNotEmpty(
      $this->getNonPublicProperty('last_cfg')
    );
  }

  /** @test */
  public function testExecMethodThrowsAnExceptionWhenTheGivenFieldsHasNoValues()
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

    $method->invoke($this->sqlite, $cfg);
  }

  /** @test */
  public function testExecMethodSelectTest()
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

    $result = $method->invoke($this->sqlite, $cfg);

    $this->assertInstanceOf(\PDOStatement::class, $result);

    $results = $this->sqlite->fetchAllResults($result, \PDO::FETCH_ASSOC);

    $this->assertSame(
      [
        ['email' => 'john@mail.com', 'name' => 'John'],
        ['email' => 'smith@mail.com', 'name' => 'Smith']
      ],
      $results
    );
  }

  /** @test */
  public function testExecMethodTestTheAfterTriggerIsRunning()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY AUTOINCREMENT,
              email VARCHAR(25) NOT NULL UNIQUE,
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
      $method->invoke($this->sqlite, $cfg)
    );
  }

  /** @test */
  public function testExecMethodTestWhenTriggerReturnsEmptyRun()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY AUTOINCREMENT,
              email VARCHAR(25) NOT NULL UNIQUE,
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
      $method->invoke($this->sqlite, $cfg)
    );

    $this->assertDatabaseDoesNotHave('users', 'name', 'John');
    $this->assertDatabaseDoesNotHave('users', 'email', 'john@mail.com');
  }

  /** @test */
  public function testExecMethodTestWhenTriggerReturnsEmptyRunButForceIsEnabled()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY AUTOINCREMENT,
              email VARCHAR(25) NOT NULL UNIQUE,
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
      $method->invoke($this->sqlite, $cfg)
    );

    $this->assertDatabaseDoesNotHave('users', 'name', 'John');
    $this->assertDatabaseDoesNotHave('users', 'email', 'john@mail.com');
  }

  /** @test */
  public function testExecMethodReturnsNullWhenSqlHasFalsyValueFromTheReturnedConfigFromProcesscfgMethod()
  {
    $sqlite = \Mockery::mock(Sqlite::class)->makePartial();

    $sqlite->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $sqlite->shouldReceive('processCfg')
      ->once()
      ->andReturn(['tables' => ['users'], 'sql' => '']);

    $this->assertNull(
      $this->getNonPublicMethod('_exec', $sqlite)
        ->invoke($sqlite)
    );
  }

  /** @test */
  public function testExecMethodReturnsNullWhenProcesscfgMethodReturnsNul()
  {
    $sqlite = \Mockery::mock(Sqlite::class)->makePartial();

    $sqlite->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $sqlite->shouldReceive('processCfg')
      ->once()
      ->andReturnNull();

    $this->assertNull(
      $this->getNonPublicMethod('_exec', $sqlite)
        ->invoke($sqlite)
    );
  }

  /** @test */
  public function testExecMethodReturnsNullWhenCheckMethodReturnsFalse()
  {
    $sqlite = \Mockery::mock(Sqlite::class)->makePartial();

    $sqlite->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->getNonPublicMethod('_exec', $sqlite)
        ->invoke($sqlite)
    );
  }

  /** @test */
  public function testProcesscfgMethodProcessesTheGivenInsertConfigurations()
  {
    $this->setCacheExpectations();

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

    $result   = $this->sqlite->processCfg($cfg);

    $expected_sql = 'INSERT INTO "users"
("email", "name", "id")
 VALUES (?, ?, ?)';

    $this->assertSame(trim($expected_sql), trim($result['sql']));
    $this->assertTrue(in_array('id', $result['fields']));
    $this->assertSame(['john@mail.com', 'John'], $result['values']);
    $this->assertCount(3, $result['values_desc']);
    $this->assertTrue($result['generate_id']);
    $this->assertFalse($result['auto_increment']);
    $this->assertSame('id', $result['primary']);
    $this->assertSame(16, $result['primary_length']);
    $this->assertSame('TEXT', $result['primary_type']);
  }

  /** @test */
  public function testProcesscfgMethodProcessesTheGivenUpdateConfigurations()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id BIGINT PRIMARY KEY,
              email VARCHAR(255) UNIQUE NOT NULL,
              name VARCHAR(255) NOT NULL';
    });

    $cfg = [
      'tables' => 'users',
      'kind'   => 'UPDATE',
      'fields' => ['email' => 'samantha@mail.com', 'name' => 'Samantha'],
      'where'  => [['email', '=', 'sam@mail.com'], ['name', '=', 'Sam']]
    ];

    $result = $this->sqlite->processCfg($cfg);

    $expected_sql = 'UPDATE "users" SET "email" = ?, "name" = ? WHERE  "users"."email" = ? AND "users"."name" = ?';

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
        ['type' => 'TEXT', 'maxlength' => 255],
        ['type' => 'TEXT', 'maxlength' => 255],
        ['type' => 'TEXT', 'maxlength' => 255, 'operator' => '='],
        ['type' => 'TEXT', 'maxlength' => 255, 'operator' => '=']
      ],
      $result['values_desc']
    );

    $this->assertFalse($result['auto_increment']);
    $this->assertSame('id', $result['primary']);
    $this->assertSame('INTEGER', $result['primary_type']);
    $this->assertNotEmpty($result['hashed_where']['conditions']);
  }

  /** @test */
  public function testProcesscfgMethodProcessesTheGivenSelectConfigurations()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY AUTOINCREMENT,
              name VARCHAR(25) NOT NULL,
              role_id INT(11) NOT NULL';
    });

    $this->createTable('roles', function () {
      return 'id INTEGER PRIMARY KEY AUTOINCREMENT,
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

    $result = $this->sqlite->processCfg($cfg);

    $expected_sql = 'SELECT "users"."name" AS "user_name", "roles"."name" AS "role_name"
FROM "users"
  JOIN "roles"
    ON 
    "users"."role_id" = roles.id
WHERE (
  "users"."id" >= 1
  AND "roles"."name" != ?
)
ORDER BY "users"."name" COLLATE NOCASE DESC
LIMIT 2, 25';

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
  public function testProcesscfgMethodProcessesTheGivenAggregateSelectConfigurations()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY,
              name VARCHAR(25) NOT NULL,
              active TINYINT(1) NOT NULL DEFAULT 1';
    });

    $cfg = [
      'tables' => 'users',
      'count'  => true,
      'group_by' => ['id'],
      'where' => ['active' => 1]
    ];

    $result = $this->sqlite->processCfg($cfg);

    $expected_sql = 'SELECT COUNT(*) FROM ( SELECT "users"."id" AS "id"
FROM "users"
WHERE 
"users"."active" = ?
GROUP BY "id"
) AS t';

    $this->assertSame(trim($expected_sql), trim($result['sql']));
  }

  /** @test */
  public function testProcesscfgReturnsNullWhenTheGivenConfigurationsHasSameTables()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id BIGINT';
    });


    $this->assertNull(
      $this->sqlite->processCfg(['tables' => ['users', 'users']])
    );
  }

  /** @test */
  public function testProcesscfgReturnsNullAndSetsAnErrorWhenNoHashFound()
  {
    $sqlite = \Mockery::mock(Sqlite::class)
      ->shouldAllowMockingProtectedMethods()
      ->makePartial();

    $sqlite->shouldReceive('_treat_arguments')
      ->once()
      ->with(['foo' => 'bar'])
      ->andReturn(['foo' => 'bar']);

    $sqlite->shouldReceive('error')
      ->once();

    $this->assertNull(
      $sqlite->processCfg(['foo' => 'bar'])
    );
  }

  /** @test */
  public function testProcesscfgMethodReturnsPreviouslySavedCfgUsingHash()
  {
    $sqlite = \Mockery::mock(Sqlite::class)
      ->shouldAllowMockingProtectedMethods()
      ->makePartial();

    $this->setNonPublicPropertyValue('cfgs', [
      '123456' => [
        'foo2' => 'bar2'
      ]
    ], $sqlite);

    $sqlite->shouldReceive('_treat_arguments')
      ->once()
      ->with(['foo' => 'bar'])
      ->andReturn(['hash' => '123456']);

    $this->assertSame(
      ['foo2' => 'bar2', 'values' => [], 'where' => [], 'filters' => []],
      $sqlite->processCfg(['foo' => 'bar'])
    );
  }

  /** @test */
  public function testProcesscfgMethodReturnsNullWhenAGivenFieldDoesNotExists()
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
      $this->sqlite->processCfg($cfg)
    );
  }

  /** @test */
  public function testReprocesscfgMethodTest()
  {
    $sqlite = \Mockery::mock(Sqlite::class)->makePartial();

    $sqlite->shouldReceive('processCfg')
      ->once()
      ->with(['foo' => 'bar'], true)
      ->andReturn(['foo2' => 'bar2']);

    $this->assertSame(['foo2' => 'bar2'], $sqlite->reprocessCfg(['foo' => 'bar']));

    // Another test

    $this->setNonPublicPropertyValue('cfgs', [
      '12345' => ['a' => 'b']
    ], $sqlite);

    $sqlite->shouldReceive('processCfg')
      ->once()
      ->with(
        ['foo' => 'bar', 'hash' => '12345', 'values' => ['a', 'b']],
        true
      )
      ->andReturn(['foo2' => 'bar2', 'values' => ['c', 'd']]);

    $result = $sqlite->reprocessCfg([
      'bbn_db_processed' => true,
      'bbn_db_treated'   => true,
      'hash'             => '12345',
      'foo'              => 'bar',
      'values'           => ['a', 'b']
    ]);

    $this->assertSame(['foo2' => 'bar2', 'values' => ['a', 'b']], $result);

    $this->assertSame([], $this->getNonPublicProperty('cfgs', $sqlite));
  }

  /** @test */
  public function testTreatArgumentsMethodNormalizesArgumentsByMakingItAUniformArray()
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
    $result = $method->invoke($this->sqlite, $cfg);

    $this->assertArrayHasKey('hash', $result);
    unset($result['hash']);
    $this->assertSame($expected, $result);
  }

  /** @test */
  public function testTreatArgumentsMethodSetsDefaultCfgWhenNotProvided()
  {
    $result = $this->getNonPublicMethod('_treat_arguments')
      ->invoke($this->sqlite, ['tables' => ['users']]);

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
  public function testTreatArgumentsMethodHandleArgumentsWhenTheGivenConfigIsANumericArray()
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
      ->invoke($this->sqlite, $cfg);

    $this->assertArrayHasKey('hash', $result);
    unset($result['hash']);
    $this->assertSame($expected, $result);
  }

  /** @test */
  public function testTreatArgumentsMethodTestDifferentConditionalLogic()
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
      ->invoke($this->sqlite, $cfg);

    $this->assertArrayHasKey('hash', $result);
    unset($result['hash']);
    $this->assertSame($expected, $result);
  }

  /** @test */
  public function testTreatArgumentsMethodTestingHandlingJoinArguments()
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
      ->invoke($this->sqlite, $cfg);

    $this->assertArrayHasKey('hash', $result);
    unset($result['hash']);
    $this->assertSame($expected, $result);
  }

  /** @test */
  public function testTreatArgumentsMethodTestingHaving()
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
      ->invoke($this->sqlite, $cfg);

    $this->assertArrayHasKey('hash', $result);
    unset($result['hash']);
    $this->assertSame($expected, $result);
  }

  /** @test */
  public function testTreatArgumentsThrowsAnExceptionsIfTableIsNotProvided()
  {
    $this->expectException(\Error::class);

    $this->getNonPublicMethod('_treat_arguments')
      ->invoke($this->sqlite, ['foo' => 'bar']);
  }

  /** @test */
  public function testTreatArgumentsMethodReturnsTheGivenCfgAsIsIfBbnDbTreatedExists()
  {
    $cfg = [
      'bbn_db_treated' => true,
      'tables' => ['users']
    ];

    $this->assertSame(
      $cfg,
      $this->getNonPublicMethod('_treat_arguments')
        ->invoke($this->sqlite, $cfg)
    );
  }

  /** @test */
  public function testAdaptFiltersMethodTest()
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

    $method->invokeArgs($this->sqlite, [&$cfg]);

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

    $method->invokeArgs($this->sqlite, [&$cfg2]);

    $this->assertSame($expected2, $cfg2);
  }

  /** @test */
  public function testAdaptBitMethodTestWhenThereIsAnAggregateFunction()
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

    $result = $method->invoke($this->sqlite, $cfg, $cfg['filters']);

    $this->assertSame([$filters, $having], $result);
  }

  /** @test */
  public function testAdaptBitMethodTestWhenThereIsNoAnAggregateFunction()
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

    $result = $method->invoke($this->sqlite, $cfg, $cfg['filters']);

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function testSetLimit1MethodTestWhenTheGivenArgumentIsAnArrayOfArray()
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
        ->invoke($this->sqlite, $cfg)
    );

  }

  /** @test */
  public function testSetLimit1MethodTestWhenGivenArgsIsANumericArrayWithOnlyTableName()
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
        ->invoke($this->sqlite, $cfg)
    );
  }

  /** @test */
  public function testSetLimit1MethodTestWhenTheGivenArgumentIsANumericArray()
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
        ->invoke($this->sqlite, $cfg)
    );
  }

  /** @test */
  public function testAddstatementMethodAddsQueryStatementAndParametersWhenLastEnabledIsTrue()
  {
    $this->assertNull(
      $this->getNonPublicProperty('last_real_query')
    );

    $this->assertSame(
      $this->real_params_default,
      $this->getNonPublicProperty('last_real_params')
    );

    $this->assertTrue(
      $this->getNonPublicProperty('_last_enabled')
    );

    $this->assertNull(
      $this->getNonPublicProperty('last_query')
    );

    $this->assertSame(
      $this->real_params_default,
      $this->getNonPublicProperty('last_params')
    );


    $result = $this->getNonPublicMethod('addStatement')
      ->invoke($this->sqlite, $stmt = 'SELECT * FROM users', $params = ['foo' => 'bar']);

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

    $this->assertInstanceOf(Sqlite::class, $result);
  }

  /** @test */
  public function testAddstatementMethodAddsQueryStatementAndParametersWhenLastEnabledIsFalse()
  {
    $this->assertNull(
      $this->getNonPublicProperty('last_real_query')
    );

    $this->assertSame(
      $this->real_params_default,
      $this->getNonPublicProperty('last_real_params')
    );

    $this->assertNull(
      $this->getNonPublicProperty('last_query')
    );

    $this->assertSame(
      $this->real_params_default,
      $this->getNonPublicProperty('last_params')
    );

    $this->setNonPublicPropertyValue('_last_enabled', false);

    $result = $this->getNonPublicMethod('addStatement')
      ->invoke($this->sqlite, $stmt = 'SELECT * FROM users', $params = ['foo' => 'bar']);

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
      $this->real_params_default,
      $this->getNonPublicProperty('last_params')
    );

    $this->assertInstanceOf(Sqlite::class, $result);
  }

  /** @test */
  public function testGetreallastparamsMethodReturnsTheLastRealParams()
  {
    $this->setNonPublicPropertyValue('last_real_params', ['foo' => 'bar']);

    $this->assertSame(
      ['foo' => 'bar'],
      $this->sqlite->getRealLastParams()
    );
  }

  /** @test */
  public function testReallastMethodReturnsTheRealLastQuery()
  {
    $this->setNonPublicPropertyValue('last_real_query', 'SELECT * FROM users');

    $this->assertSame(
      'SELECT * FROM users',
      $this->sqlite->realLast()
    );
  }

  /** @test */
  public function testGetlastvaluesMethodReturnsTheLastParamsValues()
  {
    $this->setNonPublicPropertyValue('last_params', [
      'values' => ['foo' => 'bar']
    ]);

    $this->assertSame(
      ['foo' => 'bar'],
      $this->sqlite->getLastValues()
    );

    $this->setNonPublicPropertyValue('last_params', null);

    $this->assertNull($this->sqlite->getLastValues());
  }

  /** @test */
  public function testGetlastparamsMethodReturnsTheLastParams()
  {
    $this->setNonPublicPropertyValue('last_params', [
      'values' => ['foo' => 'bar']
    ]);

    $this->assertSame(
      ['values' => ['foo' => 'bar']],
      $this->sqlite->getLastParams()
    );
  }

  /** @test */
  public function testSetlastinsertidMethodChangesTheValueOfLastInsertedIdForTheGivenId()
  {
    $this->setNonPublicPropertyValue('id_just_inserted', 22);
    $this->setNonPublicPropertyValue('last_insert_id', 22);

    $result = $this->sqlite->setLastInsertId(44);

    $this->assertSame(
      44,
      $this->getNonPublicProperty('last_insert_id')
    );

    $this->assertSame(
      44,
      $this->getNonPublicProperty('id_just_inserted')
    );

    $this->assertInstanceOf(Sqlite::class, $result);
  }

  /** @test */
  public function testSetlastinsertidMethodDoesNotChangeTheValueOfLastInsertedIdIfNoInsertQueryPerformed()
  {
    $this->sqlite->setLastInsertId();

    $this->assertNull(
      $this->getNonPublicProperty('id_just_inserted')
    );

    $this->assertSame(
      0,
      $this->getNonPublicProperty('last_insert_id')
    );
  }

  /** @test */
  public function testSetlastinsertidMethodChangesTheValueOfLastInsertedIdFromLastInsertQuery()
  {
    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY, email VARCHAR(25)';
    });

    $this->insertOne('users', ['email' => 'mail@test.com']);

    $this->sqlite->setLastInsertId();

    $this->assertSame(
      1,
      $this->getNonPublicProperty('last_insert_id')
    );

    $this->assertNull(
      $this->getNonPublicProperty('id_just_inserted')
    );
  }

  /** @test */
  public function testSetlastinsertidMethodChangesTheValueOfLastInsertedIdFromIdJustInsertedPropertyWhenNotNullIgnoringLastQuery()
  {
    $this->setNonPublicPropertyValue('id_just_inserted', 333);

    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY, email VARCHAR(25)';
    });

    $this->insertOne('users', ['email' => 'mail@test.com']);

    $this->sqlite->setLastInsertId();

    $this->assertSame(
      333,
      $this->getNonPublicProperty('last_insert_id')
    );
  }

  /** @test */
  public function testLastidMethodReturnsTheLastInsertedId()
  {
    $this->setNonPublicPropertyValue('last_insert_id', 234);

    $this->assertSame(234, $this->sqlite->lastId());

    $this->setNonPublicPropertyValue(
      'last_insert_id',
      hex2bin('7f4a2c70bcac11eba47652540000cfaa')
    );

    $this->assertSame(
      '7f4a2c70bcac11eba47652540000cfaa',
      $this->sqlite->lastId()
    );

    $this->setNonPublicPropertyValue('last_insert_id', null);

    $this->assertFalse($this->sqlite->lastId());
  }

  /** @test */
  public function testLastMethodReturnsTheLastForTheCurrentConnection()
  {
    $this->setNonPublicPropertyValue(
      'last_query',
      'SELECT * FROM users'
    );

    $this->assertSame(
      'SELECT * FROM users',
      $this->sqlite->last()
    );
  }

  /** @test */
  public function testCountqueriesMethodReturnsTheCountOfQueries()
  {
    $this->setNonPublicPropertyValue('queries', ['foo' => 'bar', 'bar' => 'foo']);

    $this->assertSame(2, $this->sqlite->countQueries());
  }

  /** @test */
  public function testFlushMethodDeletesAllTheRecordedQueriesAndReturnsTheirCount()
  {
    $this->setNonPublicPropertyValue('queries', ['foo' => 'bar', 'bar' => 'foo']);

    $result = $this->sqlite->flush();

    $this->assertSame([], $this->getNonPublicProperty('queries'));
    $this->assertSame([], $this->getNonPublicProperty('list_queries'));

    $this->assertSame(2, $result);
  }

  /** @test */
  public function testMakehashMethodMakesAHashStringThatWillBeTheIdOfTheRequest()
  {
    $hash_contour    = $this->getNonPublicProperty('hash_contour');
    $expected_string = "{$hash_contour}%s{$hash_contour}";

    $method = $this->getNonPublicMethod('makeHash');

    $expected = sprintf($expected_string, md5('--bar----bar2--'));
    $this->assertSame(
      $expected,
      $method->invoke($this->sqlite, ['foo' => 'bar', 'foo2' => 'bar2'])
    );

    $expected = sprintf($expected_string, md5('--foo----bar----baz--'));
    $this->assertSame(
      $expected,
      $method->invoke($this->sqlite, 'foo', 'bar', 'baz')
    );

    $expected = sprintf($expected_string, md5('--foo--' . serialize(['bar', 'bar2'])));
    $this->assertSame($expected, $method->invoke($this->sqlite,[
      'foo',
      'foo2' => ['bar', 'bar2']
    ]));
  }

  /**
   * @test
   * @depends makeHash_method_makes_a_hash_string_that_will_be_the_id_of_the_request
   */
  public function testSethashMethodMakesAndSetsHash()
  {
    $set_hash_method = $this->getNonPublicMethod('setHash');
    $make_hash_method = $this->getNonPublicMethod('makeHash');

    $set_hash_method->invoke($this->sqlite, $args = ['foo' => 'bar', 'foo2' => 'bar2']);
    $this->assertSame(
      $make_hash_method->invoke($this->sqlite, $args),
      $this->getNonPublicProperty('hash')
    );


    $set_hash_method->invoke($this->sqlite, 'foo', 'bar', 'baz');
    $this->assertSame(
      $make_hash_method->invoke($this->sqlite, 'foo', 'bar', 'baz'),
      $this->getNonPublicProperty('hash')
    );

    $set_hash_method->invoke($this->sqlite, $args = [
      'foo',
      'foo2' => ['bar', 'bar2']
    ]);
    $this->assertSame(
      $make_hash_method->invoke($this->sqlite, $args),
      $this->getNonPublicProperty('hash')
    );

  }

  /**
   * @test
   * @depends setHash_method_makes_and_sets_hash
   */
  public function testGethashMethodReturnsTheCreatedHash()
  {
    $set_hash_method = $this->getNonPublicMethod('setHash');
    $make_hash_method = $this->getNonPublicMethod('makeHash');

    $set_hash_method->invoke($this->sqlite, 'foo', 'bar');

    $this->assertSame(
      $make_hash_method->invoke($this->sqlite, 'foo', 'bar'),
      $this->sqlite->getHash()
    );
  }

  /** @test */
  public function testStartfancystuffMethodSetsTheQueryClassAsPdoDerivedStatementClass()
  {
    $this->sqlite->startFancyStuff();

    $result = $this->getNonPublicProperty('pdo')->getAttribute(\PDO::ATTR_STATEMENT_CLASS);

    $this->assertIsArray($result);
    $this->assertSame(Query::class, $result[0]);
    $this->assertSame(1, $this->getNonPublicProperty('_fancy'));
  }

  /** @test */
  public function testStopfancystuffMethodSetsStatementClassToPdoStatement()
  {
    $this->sqlite->stopFancyStuff();

    $result = $this->getNonPublicProperty('pdo')->getAttribute(\PDO::ATTR_STATEMENT_CLASS);

    $this->assertIsArray($result);

    $this->assertSame(\PDOStatement::class, $result[0]);
    $this->assertFalse($this->getNonPublicProperty('_fancy'));
  }

  /** @test */
  public function testSetStartMethodTestWhenTheGivenArgumentIsAnArrayOfArray()
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
        ->invoke($this->sqlite, $cfg, 3)
    );
  }

  /** @test */
  public function testSetStartMethodTestWhenGivenArgsIsANumericArrayWithOnlyTableName()
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
        ->invoke($this->sqlite, $cfg, 6)
    );
  }

  /** @test */
  public function testSetStartMethodTestWhenTheGivenArgumentIsANumericArray()
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
        ->invoke($this->sqlite, $cfg, 10)
    );
  }

  /** @test */
  public function testRetrievequeryMethodRetrievesAQueryFromTheGivenHash()
  {
    $this->setNonPublicPropertyValue('queries', [
      '12345' => ['foo' => 'bar'],
      '54321' => '12345'
    ]);

    $this->assertSame(['foo' => 'bar'], $this->sqlite->retrieveQuery('12345'));
    $this->assertSame(['foo' => 'bar'], $this->sqlite->retrieveQuery('54321'));
    $this->assertNull($this->sqlite->retrieveQuery('foo'));
  }

  /** @test */
  public function testExtractfieldsMethodTest()
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
      $this->sqlite->extractFields($cfg, $conditions, $result)
    );

    $this->assertSame($expected, $result);

    $this->assertSame(
      $expected,
      $this->sqlite->extractFields($cfg, $conditions['conditions'])
    );
  }

  /** @test */
  public function testFilterfiltersMethodReturnsAnArrayOfSpecificFiltersAddedToTheExistingOnes()
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
      $this->sqlite->filterFilters($cfg, 'name')
    );

    $this->assertSame(
      [
        ['field' => 'name', 'operator' => '=', 'value' => 'John'],
        ['field' => 'name', 'operator' => '=', 'value' => 'Sam'],
      ],
      $this->sqlite->filterFilters($cfg, 'name', '=')
    );

    $this->assertSame(
      [],
      $this->sqlite->filterFilters($cfg, 'name', '!=')
    );

    $this->assertNull(
      $this->sqlite->filterFilters(['table' => 'users'], 'name')
    );

    $this->assertSame(
      [],
      $this->sqlite->filterFilters(['filters' => []], 'name')
    );
  }

  /** @test */
  public function testGetoneMethodExecutesTheGivenQueryAndExtractsTheFirstColumnResult()
  {
    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY, 
              username VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'foo'],
      ['username' => 'bar'],
    ]);

    $this->assertSame(
      'foo',
      $this->sqlite->getOne("SELECT username FROM users WHERE id = ?", 1)
    );
  }

  /** @test */
  public function testGetoneMethodReturnsFalseWhenQueryReturnsFalse()
  {
    $this->assertFalse(
      $this->sqlite->getOne('SELECT username FROM users WHERE id = ?', 1)
    );
  }

  /** @test */
  public function testGetkeyvalMethodReturnsAnArrayIndexedWithTheFirstFieldOfTheRequest()
  {
    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY, 
              username VARCHAR(255), 
              email VARCHAR(255), 
              name VARCHAR(255)';
    });

    $this->assertEmpty(
      $this->sqlite->getKeyVal('SELECT * FROM users')
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
      $this->sqlite->getKeyVal('SELECT username, name, email FROM users')
    );

    $this->assertSame(
      [
        'jdoe' => [
          'name'  => 'John Doe',
          'email' => 'jdoe@mail.com'
        ]
      ],
      $this->sqlite->getKeyVal('SELECT username, name, email FROM users WHERE id = ?', 1)
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
      $this->sqlite->getKeyVal('SELECT * FROM users')
    );
  }

  /** @test */
  public function testGetkeyvalMethodReturnsNullWhenQueryReturnsFalse()
  {
    $sqlite = \Mockery::mock(Sqlite::class)->makePartial();

    $sqlite->shouldReceive('query')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $sqlite->getKeyVal('SELECT * FROM users')
    );
  }

  /** @test */
  public function testGetcolarrayMethodReturnsAnArrayOfTheValuesOfSingleFieldAsResultFromQuery()
  {
    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY, 
              username VARCHAR(255)';
    });

    $this->assertEmpty(
      $this->sqlite->getColArray('SELECT id FROM users')
    );

    $this->insertMany('users', [
      ['username' => 'jdoe'],
      ['username' => 'sdoe'],
    ]);

    $this->assertSame(
      [1, 2],
      $this->sqlite->getColArray('SELECT id FROM users')
    );

    $this->assertSame(
      [1, 2],
      $this->sqlite->getColArray('SELECT id, username FROM users')
    );

    $this->assertSame(
      [1, 2],
      $this->sqlite->getColArray('SELECT * FROM users')
    );

    $this->assertSame(
      ['jdoe', 'sdoe'],
      $this->sqlite->getColArray('SELECT username FROM users')
    );

    $this->assertSame(
      ['jdoe', 'sdoe'],
      $this->sqlite->getColArray('SELECT username, id FROM users')
    );
  }

  /** @test */
  public function testSelectMethodReturnsTheFirstRowResultingFromQueryAsAnObject()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY, 
              username VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'jdoe'],
      ['username' => 'sdoe'],
    ]);

    $result = $this->sqlite->select('users', ['id', 'username']);

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('id', $result);
    $this->assertSame(1, $result->id);
    $this->assertObjectHasAttribute('username', $result);
    $this->assertSame('jdoe', $result->username);

    $result = $this->sqlite->select('users', 'id');

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('id', $result);
    $this->assertSame(1, $result->id);
    $this->assertObjectNotHasAttribute('username', $result);

    $result = $this->sqlite->select('users', [], ['id'], ['id' => 'DESC']);

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('id', $result);
    $this->assertSame(2, $result->id);
    $this->assertObjectHasAttribute('username', $result);
    $this->assertSame('sdoe', $result->username);

    $result = $this->sqlite->select('users', [], ['id'], ['id' => 'ASC'], 1);

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('id', $result);
    $this->assertSame(2, $result->id);
    $this->assertObjectHasAttribute('username', $result);
    $this->assertSame('sdoe', $result->username);

    $this->assertNull(
      $this->sqlite->select('users', [], ['id' => 33])
    );

    $this->assertNull(
      $this->sqlite->select('users', [], [], [], 3)
    );
  }

  /** @test */
  public function testSelectallMethodReturnsTableRowsResultingFromQueryAsAnArrayOfObjects()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY, 
              username VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'jdoe'],
      ['username' => 'sdoe'],
    ]);

    $result = $this->sqlite->selectAll('users', []);

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

    $result = $this->sqlite->selectAll('users', 'username', [], ['id' => 'DESC']);

    $this->assertIsArray($result);
    $this->assertCount(2, $result);

    $this->assertIsObject($result[0]);
    $this->assertObjectHasAttribute('username', $result[0]);
    $this->assertSame('sdoe', $result[0]->username);

    $this->assertIsObject($result[1]);
    $this->assertObjectHasAttribute('username', $result[1]);
    $this->assertSame('jdoe', $result[1]->username);

    $result = $this->sqlite->selectAll('users', 'username', [], ['id' => 'DESC'], 1);

    $this->assertIsArray($result);
    $this->assertCount(1, $result);

    $this->assertIsObject($result[0]);
    $this->assertObjectHasAttribute('username', $result[0]);
    $this->assertSame('sdoe', $result[0]->username);

    $this->assertSame(
      [],
      $this->sqlite->selectAll('users', [], ['id' => 33])
    );

    $this->assertSame(
      [],
      $this->sqlite->selectAll('users', [], [], [], 1, 3)
    );
  }

  /** @test */
  public function testSelectallMethodReturnsNullWhenExecMethodReturnsFalse()
  {
    $sqlite = \Mockery::mock(Sqlite::class)
      ->shouldAllowMockingProtectedMethods()
      ->makePartial();

    $sqlite->shouldReceive('_exec')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $sqlite->selectAll('user', [])
    );
  }

  /** @test */
  public function testIselectMethodReturnsTheFirstRowResultingFromQueryAsNumericArray()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY,
              username VARCHAR(255) UNIQUE,
              name VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'jdoe', 'name' => 'John Doe'],
      ['username' => 'sdoe', 'name' => 'Smith Doe'],
    ]);

    $this->assertSame(
      [1, 'jdoe', 'John Doe'],
      $this->sqlite->iselect('users', [])
    );

    $this->assertSame(
      ['jdoe'],
      $this->sqlite->iselect('users', 'username')
    );

    $this->assertSame(
      [1, 'jdoe'],
      $this->sqlite->iselect('users', ['id', 'username'])
    );

    $this->assertSame(
      [2, 'sdoe'],
      $this->sqlite->iselect('users', ['id', 'username'], [], [], 1)
    );

    $this->assertSame(
      [2, 'sdoe'],
      $this->sqlite->iselect('users', ['id', 'username'], [],['id' => 'DESC'])
    );

    $this->assertNull(
      $this->sqlite->iselect('users', [], ['id' => 44])
    );
  }

  /** @test */
  public function testIselectallMethodReturnsAllResultsFromQueryAsAnArrayOfNumericArrays()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY,
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
      $this->sqlite->iselectAll('users', [])
    );

    $this->assertSame(
      [
        ['jdoe'],
        ['sdoe']
      ],
      $this->sqlite->iselectAll('users', 'username')
    );

    $this->assertSame(
      [
        [1, 'John Doe'],
        [2, 'Smith Doe']
      ],
      $this->sqlite->iselectAll('users', ['id', 'name'])
    );

    $this->assertSame(
      [
        [2, 'Smith Doe']
      ],
      $this->sqlite->iselectAll('users', ['id', 'name'], ['id' => 2])
    );

    $this->assertSame(
      [
        [2, 'Smith Doe'],
        [1, 'John Doe']
      ],
      $this->sqlite->iselectAll('users', ['id', 'name'], [], ['id' => 'DESC'])
    );

    $this->assertSame(
      [
        [2, 'Smith Doe']
      ],
      $this->sqlite->iselectAll('users', ['id', 'name'], [], ['id' => 'DESC'], 1)
    );

    $this->assertEmpty(
      $this->sqlite->iselectAll('users', [], ['id' => 11])
    );
  }

  /** @test */
  public function testIselectallMethodReturnsNullWhenExecFunctionReturnsFalse()
  {
    $sqlite = \Mockery::mock(Sqlite::class)
      ->shouldAllowMockingProtectedMethods()
      ->makePartial();

    $sqlite->shouldReceive('_exec')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $sqlite->iselectAll('users', [])
    );
  }

  /** @test */
  public function testRselectMethodReturnsTheFirstRowResultingFromTheQueryAsIndexedArray()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY,
              username VARCHAR(255) UNIQUE,
              name VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'jdoe', 'name' => 'John Doe'],
      ['username' => 'sdoe', 'name' => 'Smith Doe']
    ]);

    $this->assertSame(
      ['id' => 1, 'username' => 'jdoe', 'name' => 'John Doe'],
      $this->sqlite->rselect('users', [])
    );

    $this->assertSame(
      ['id' => 2, 'username' => 'sdoe', 'name' => 'Smith Doe'],
      $this->sqlite->rselect('users', [], ['id' => 2])
    );

    $this->assertSame(
      ['username' => 'sdoe'],
      $this->sqlite->rselect('users', 'username', [], ['id' => 'DESC'])
    );

    $this->assertSame(
      ['id' => 2, 'username' => 'sdoe'],
      $this->sqlite->rselect('users', ['id', 'username'], [], [], 1)
    );

    $this->assertNull(
      $this->sqlite->rselect('users', ['id', 'username'], [], [], 3)
    );

    $this->assertNull(
      $this->sqlite->rselect('users', ['id', 'username'], ['id' => 33])
    );
  }

  /** @test */
  public function testRselectallMethodReturnsQueryResultsAsAnArrayOfIndexedArrays()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY,
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
      $this->sqlite->rselectAll('users', [])
    );

    $this->assertSame(
      [
        [ 'username' => 'jdoe'],
        ['username' => 'sdoe']
      ],
      $this->sqlite->rselectAll('users', 'username')
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
      $this->sqlite->rselectAll('users', ['id', 'name'], [], ['id' => 'DESC'])
    );

    $this->assertSame(
      [
        [
          'id' => 2,
          'name' => 'Smith Doe'
        ]
      ],
      $this->sqlite->rselectAll('users', ['id', 'name'], [], [], 1, 1)
    );

    $this->assertEmpty(
      $this->sqlite->rselectAll('users', [], ['id' => 44])
    );

    $this->assertEmpty(
      $this->sqlite->rselectAll('users', [], [], [], 1, 33)
    );
  }

  /** @test */
  public function testSelectoneMethodReturnsASingleValueFromTheGivenFieldName()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY,
              username VARCHAR(255) UNIQUE,
              name VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'jdoe', 'name' => 'John Doe'],
      ['username' => 'sdoe', 'name' => 'Smith Doe']
    ]);

    $this->assertSame(
      'jdoe',
      $this->sqlite->selectOne('users', 'username')
    );

    $this->assertSame(
      1,
      $this->sqlite->selectOne('users')
    );

    $this->assertSame(
      'Smith Doe',
      $this->sqlite->selectOne('users', 'name', ['id' => 2])
    );

    $this->assertSame(
      'Smith Doe',
      $this->sqlite->selectOne('users', 'name', [], ['id' => 'DESC'])
    );

    $this->assertSame(
      'Smith Doe',
      $this->sqlite->selectOne('users', 'name', [], [], 1)
    );

    $this->assertFalse(
      $this->sqlite->selectOne('users', 'username', ['id' => 333])
    );

    $this->assertFalse(
      $this->sqlite->selectOne('users', 'username', [], [], 44)
    );
  }

  /** @test */
  public function testCountMethodReturnsTheNumberOfRecordsInTheTableForTheGivenArguments()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY,
              username VARCHAR(25) UNIQUE NOT NULL';
    });

    $this->insertMany('users',[
      ['username' => 'jdoe'], ['username' => 'sdoe']
    ]);

    $this->assertSame(2, $this->sqlite->count('users'));
    $this->assertSame(1, $this->sqlite->count('users', ['username' => 'jdoe']));
    $this->assertSame(0, $this->sqlite->count('users', ['id' => 22]));

    $this->assertSame(1, $this->sqlite->count([
      'table' => ['users'],
      'where' => ['username' => 'sdoe']
    ]));

    $this->assertSame(1, $this->sqlite->count([
      'tables' => ['users'],
      'where' => ['username' => 'sdoe']
    ]));

    $this->assertSame(2, $this->sqlite->count([
      'tables' => ['users']
    ]));
  }

  /** @test */
  public function testCountMethodReturnsNullWhenExecReturnsNonObject()
  {
    $sqlite = \Mockery::mock(Sqlite::class)
      ->makePartial()
      ->shouldAllowMockingProtectedMethods();

    $sqlite->shouldReceive('_exec')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $sqlite->count('users')
    );
  }

  /** @test */
  public function testSelectallbykeysMethodReturnsAnArrayIndexedWithTheFirstFieldOfTheRequest()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY,
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
      $this->sqlite->selectAllByKeys('users')
    );

    $this->assertSame(
      [
        'jdoe' => ['id' => 1, 'name' => 'John Doe'],
        'sdoe' => ['id' => 2, 'name' => 'Smith Doe']
      ],
      $this->sqlite->selectAllByKeys('users', ['username', 'id', 'name'])
    );

    $this->assertSame(
      [
        'sdoe' => ['id' => 2, 'name' => 'Smith Doe'],
        'jdoe' => ['id' => 1, 'name' => 'John Doe']
      ],
      $this->sqlite->selectAllByKeys('users', ['username', 'id', 'name'], [], ['id' => 'DESC'])
    );

    $this->assertSame(
      [
        'sdoe' => ['id' => 2, 'name' => 'Smith Doe']
      ],
      $this->sqlite->selectAllByKeys('users', ['username', 'id', 'name'], ['id' => '2'])
    );

    $this->assertSame(
      [
        'sdoe' => ['id' => 2, 'name' => 'Smith Doe']
      ],
      $this->sqlite->selectAllByKeys('users', ['username', 'id', 'name'], [], [], 1, 1)
    );

    $this->assertEmpty(
      $this->sqlite->selectAllByKeys('users', [], ['id' => 33])
    );

    $this->assertEmpty(
      $this->sqlite->selectAllByKeys('users', [], [], [], 1, 33)
    );
  }

  /** @test */
  public function testSelectallbykeysMethodReturnsNullWhenNoResultsFoundAndCheckReturnsFalse()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INT(11)';
    });

    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull(
      $this->sqlite->selectAllByKeys('users')
    );
  }

  /** @test */
  public function testStatMethodReturnsAnArrayWithTheCountOfValuesResultingFromTheQuery()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY,
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
      $this->sqlite->stat('users', 'name')
    );

    $this->assertSame(
      [
        ['name' => 'Smith Doe', 'num' => 1],
        ['name' => 'John Doe', 'num' => 3]
      ],
      $this->sqlite->stat('users', 'name', [], ['name' => 'DESC'])
    );

    $this->assertSame(
      [
        ['name' => 'John Doe', 'num' => 3]
      ],
      $this->sqlite->stat('users', 'name', ['name' => 'John Doe'])
    );
  }

  /** @test */
  public function testStatMethodReturnsNullWhenCheckMethodReturnsNull()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull($this->sqlite->stat('users', 'name'));
  }

  /** @test */
  public function testCountfieldvaluesMethodReturnsCountOfIdenticalValuesInAFieldAsArray()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY,
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
      $this->sqlite->countFieldValues('users', 'name')
    );

    $this->assertSame(
      [
        ['val' => 'John Doe', 'num' => 3],
        ['val' => 'Smith Doe', 'num' => 1]
      ],
      $this->sqlite->countFieldValues([
        'table' => 'users',
        'fields' => ['name']
      ])
    );

    $this->assertSame(
      [
        ['val' => 'Smith Doe', 'num' => 1],
        ['val' => 'John Doe', 'num' => 3]
      ],
      $this->sqlite->countFieldValues('users', 'name', [], ['name' => 'DESC'])
    );

    $this->assertSame(
      [
        ['val' => 'John Doe', 'num' => 3]
      ],
      $this->sqlite->countFieldValues('users', 'name', ['name' => 'John Doe'])
    );

    $this->assertEmpty(
      $this->sqlite->countFieldValues('users', 'name', ['name' => 'foo'])
    );
  }

  /** @test */
  public function testGetcolumnvaluesMethodNumericIndexedArrayWithValuesOfUniqueColumn()
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
      $this->sqlite->getColumnValues('users', 'username')
    );

    $this->assertSame(
      ['foo', 'foo2', 'foo3', 'foo4'],
      $this->sqlite->getColumnValues([
        'table' => ['users'],
        'fields' => ['username']
      ])
    );

    $this->assertSame(
      ['foo'],
      $this->sqlite->getColumnValues('users', 'username', ['username' => 'foo'])
    );

    $this->assertSame(
      ['foo'],
      $this->sqlite->getColumnValues('users', 'DISTINCT username', ['username' => 'foo'])
    );

    $this->assertSame(
      ['foo4', 'foo3', 'foo2', 'foo'],
      $this->sqlite->getColumnValues('users', 'username', [], ['username' => 'DESC'])
    );

    $this->assertSame(
      ['foo2'],
      $this->sqlite->getColumnValues('users', 'username', [], [], 1, 1)
    );

    $this->assertEmpty(
      $this->sqlite->getColumnValues('users', 'username', ['username' => 'bar'])
    );

    $this->assertEmpty(
      $this->sqlite->getColumnValues('users', 'username', [], [], 1, 44)
    );
  }

  /** @test */
  public function testGetcolumnvaluesReturnsNullWhenCheckMethodReturnsFalse()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertNull($this->sqlite->getColumnValues('users', 'username'));
  }

  /** @test */
  public function testInsertMethodInsertsValuesInTheGivenTableAndReturnsAffectedRows()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'name VARCHAR(20), email VARCHAR(20) UNIQUE';
    });

    $this->assertSame(
      2,
      $this->sqlite->insert('users', [
        ['name' => 'John', 'email' => 'john@mail.com'],
        ['name' => 'Smith', 'email' => 'smith@mail.com'],
        ['name' => 'Smith', 'email' => 'smith@mail.com'],
      ], true)
    );

    $this->assertDatabaseHas('users', 'email', 'john@mail.com');
    $this->assertDatabaseHas('users', 'email', 'smith@mail.com');

    $this->sqlite->query('DELETE FROM users');



    $this->assertSame(
      2,
      $this->sqlite->insert([
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
  public function testInsertMethodThrowsAnExceptionWhenTableNameIsEmpty()
  {
    $this->expectException(\Exception::class);

    $this->sqlite->insert('');
  }

  /** @test */
  public function testInsertupdateMethodInsertsRowsInTheGivenTableIfNotExistsOtherwiseUpdate()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'name VARCHAR(20), email VARCHAR(20) UNIQUE';
    });


    $this->assertSame(
      3,
      $this->sqlite->insertUpdate('users', [
        ['name' => 'John', 'email' => 'john@mail.com'],
        ['name' => 'Smith', 'email' => 'smith@mail.com'],
        ['name' => 'Smith2', 'email' => 'smith@mail.com']
      ])
    );

    $this->assertDatabaseHas('users', 'email', 'smith@mail.com');
    $this->assertDatabaseHas('users', 'name', 'Smith2');
    $this->assertDatabaseDoesNotHave('users', 'name', 'Smith');

    $this->sqlite->query("DELETE FROM users");

    $this->assertSame(
      3,
      $this->sqlite->insertUpdate([
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
  public function testUpdateMethodUpdatesRowsInTheGivenTable()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'username VARCHAR(20) UNIQUE, name VARCHAR(20)';
    });

    $this->insertOne('users', ['username' => 'jdoe', 'name' => 'John']);
    $this->insertOne('users', ['username' => 'sdoe', 'name' => 'Sam']);

    $this->assertSame(
      1,
      $this->sqlite->update('users', ['name' => 'Smith'], ['username' => 'jdoe'])
    );

    $this->assertDatabaseHas('users', 'name', 'Smith');
    $this->assertDatabaseDoesNotHave('users', 'name', 'John');

    $this->assertSame(
      0,
      $this->sqlite->update('users', ['username' => 'sdoe'], ['username' => 'jdoe'], true)
    );

    $this->sqlite->query('DELETE FROM users');

    $this->insertOne('users', ['username' => 'jdoe', 'name' => 'John']);
    $this->insertOne('users', ['username' => 'sdoe', 'name' => 'Sam']);

    $this->assertSame(
      1,
      $this->sqlite->update([
        'tables' => ['users'],
        'where'  => ['username' => 'jdoe'],
        'fields' => ['name'=> 'Smith']
      ])
    );

    $this->assertDatabaseHas('users', 'name', 'Smith');
    $this->assertDatabaseDoesNotHave('users', 'name', 'John2');

    $this->assertSame(
      0,
      $this->sqlite->update([
        'tables' => ['users'],
        'where'  => ['username' => 'jdoe'],
        'fields' => ['username'=> 'sdoe'],
        'ignore' => true
      ])
    );
  }

  /** @test */
  public function testDeleteMethodDeletesRowsFromTheGivenTable()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'username VARCHAR(20) UNIQUE, name VARCHAR(20)';
    });

    $this->insertOne('users', ['username' => 'jdoe', 'name' => 'John']);

    $this->assertSame(
      1,
      $this->sqlite->delete('users', ['username' => 'jdoe'])
    );

    $this->assertDatabaseDoesNotHave('users', 'name', 'John');

    $this->assertSame(
      0,
      $this->sqlite->delete('users', ['username' => 'jdoe'])
    );

    $this->insertOne('users', ['username' => 'sdoe', 'name' => 'Smith']);

    $this->assertSame(
      1,
      $this->sqlite->delete([
        'tables' => ['users'],
        'where'  => ['username' => 'sdoe']
      ])
    );

    $this->assertDatabaseDoesNotHave('users', 'name', 'Smith');
  }

  /** @test */
  public function testFetchMethodReturnsTheFirstResultOfTheQueryAsIndexedArrayAndFalseIfNoResults()
  {
    $this->createTable('users', function () {
      return 'name VARCHAR(255), email VARCHAR(255)';
    });

    $this->assertFalse(
      $this->sqlite->fetch('SELECT * FROM users')
    );

    $this->insertMany('users', [
      ['name' => 'John', 'email' => 'john@mail.com'],
      ['name' => 'Smith', 'email' => 'smith@mail.com'],
    ]);

    $this->assertSame(
      ['name' => 'John', 'John', 'email' => 'john@mail.com', 'john@mail.com'],
      $this->sqlite->fetch('SELECT * FROM users')
    );

    $this->assertSame(
      ['email' => 'smith@mail.com', 'smith@mail.com'],
      $this->sqlite->fetch('SELECT email FROM users WHERE name = ?', 'Smith')
    );
  }

  /** @test */
  public function testFetchallMethodReturnsAnArrayOfIndexedArraysForAllQueryResultAndEmptyArrayIfNoResults()
  {
    $this->createTable('users', function () {
      return 'name VARCHAR(255), email VARCHAR(255)';
    });

    $this->assertSame(
      [],
      $this->sqlite->fetchAll('SELECT * FROM users')
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
      $this->sqlite->fetchAll('SELECT * FROM users ORDER BY name DESC')
    );

    $this->assertSame(
      [
        ['name' => 'Smith', 'Smith']
      ],
      $this->sqlite->fetchAll('SELECT name FROM users WHERE email = "smith@mail.com"')
    );
  }

  /** @test */
  public function testFetchallMethodReturnsFalseWhenQueryMethodReturnsFalse()
  {
    $sqlite = \Mockery::mock(Sqlite::class)->makePartial();

    $sqlite->shouldReceive('query')
      ->once()
      ->andReturnFalse();

    $this->assertFalse(
      $sqlite->fetchAll('SELECT * FROM users')
    );
  }

  /** @test */
  public function testFetchcolumnMethodReturnsASingleColumnFromTheNextRowOfResultSet()
  {
    $this->createTable('users', function () {
      return 'name VARCHAR(255), email VARCHAR(255)';
    });

    $this->assertFalse(
      $this->sqlite->fetchColumn('SELECT * FROM users')
    );

    $this->insertMany('users', [
      ['name' => 'John', 'email'=> 'john@mail.com'],
      ['name' => 'Smith', 'email' => 'smith@mail.com']
    ]);

    $this->assertSame(
      'John',
      $this->sqlite->fetchColumn('SELECT * FROM users')
    );

    $this->assertSame(
      'john@mail.com',
      $this->sqlite->fetchColumn('SELECT * FROM users', 1)
    );

    $this->assertSame(
      'smith@mail.com',
      $this->sqlite->fetchColumn('SELECT * FROM users WHERE name = ?', 1, 'Smith')
    );
  }

  /** @test */
  public function testFetchobjectMethodReturnsTheFirstResultFromQueryAsObjectAndFalseIfNoResults()
  {
    $this->createTable('users', function () {
      return 'name VARCHAR(255), email VARCHAR(255)';
    });

    $this->assertFalse(
      $this->sqlite->fetchObject('SELECT * FROM users')
    );

    $this->insertMany('users', [
      ['name' => 'John', 'email'=> 'john@mail.com'],
      ['name' => 'Smith', 'email' => 'smith@mail.com']
    ]);

    $result = $this->sqlite->fetchObject('SELECT * FROM users');

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('name', $result);
    $this->assertObjectHasAttribute('email', $result);
    $this->assertSame('John', $result->name);
    $this->assertSame('john@mail.com', $result->email);

    $result = $this->sqlite->fetchObject('SELECT * FROM users ORDER BY name DESC');

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('name', $result);
    $this->assertObjectHasAttribute('email', $result);
    $this->assertSame('Smith', $result->name);
    $this->assertSame('smith@mail.com', $result->email);
  }

  /** @test */
  public function testGetrowsMethodReturnsAnArrayOfIndexedArraysForEveryRowAsAQueryResult()
  {
    $this->createTable('users', function () {
      return 'username VARCHAR(255)';
    });

    $this->assertEmpty($this->sqlite->getRows("SELECT * FROM users"));

    $this->insertMany('users', [
      ['username' => 'john_doe'],
      ['username' => 'john_doe_2'],
    ]);

    $expected = [
      ['username' => 'john_doe'],
      ['username' => 'john_doe_2'],
    ];

    $this->assertSame($expected, $this->sqlite->getRows("SELECT * FROM users"));
  }

  /** @test */
  public function testGetrowsMethodReturnsNullWhenQueryMethodReturnsFalse()
  {
    $sqlite = \Mockery::mock(Sqlite::class)->makePartial();

    $sqlite->shouldReceive('query')
      ->once()
      ->andReturnFalse();

    $this->assertNull($sqlite->getRows('SELECT * FROM users'));
  }

  /** @test */
  public function testGetrowMethodReturnsTheFirstRowResultingFromAQueryAsArrayIndexedWithFieldName()
  {
    $this->createTable('users', function () {
      return 'username VARCHAR(255)';
    });

    $this->assertEmpty($this->sqlite->getRow("SELECT * FROM users"));

    $this->insertMany('users', [
      ['username' => 'john_doe'],
      ['username' => 'john_doe_2'],
    ]);

    $this->assertSame(
      ['username' => 'john_doe'],
      $this->sqlite->getRow("SELECT * FROM users")
    );
  }

  /** @test */
  public function testGetrowMethodReturnsNullWhenQueryMethodReturnsFalse()
  {
    $sqlite = \Mockery::mock(Sqlite::class)->makePartial();

    $sqlite->shouldReceive('query')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $sqlite->getRow('SELECT * FROM users')
    );
  }

  /** @test */
  public function testGetirowMethodReturnsTheFirstRawResultingFromAQueryAsNumericIndexedArray()
  {
    $this->createTable('users', function () {
      return 'username VARCHAR(255)';
    });

    $this->assertEmpty($this->sqlite->getIrow("SELECT * FROM users"));

    $this->insertMany('users', [
      ['username' => 'john_doe'],
      ['username' => 'john_doe_2']
    ]);

    $this->assertSame(
      ['john_doe'],
      $this->sqlite->getIrow("SELECT * FROM users")
    );
  }

  /** @test */
  public function testGetirowMethodReturnsNullWhenQueryMethodReturnsFalse()
  {
    $sqlite = \Mockery::mock(Sqlite::class)->makePartial();

    $sqlite->shouldReceive('query')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $sqlite->getIrow('SELECT * FROM users')
    );
  }

  /** @test */
  public function testGetirowsMethodReturnsAllRowsResultingFromAQueryAsNumericIndexedArray()
  {
    $this->createTable('users', function () {
      return 'username VARCHAR(255)';
    });

    $this->assertEmpty($this->sqlite->getIrows("SELECT * FROM users"));

    $this->insertMany('users', [
      ['username' => 'john_doe'],
      ['username' => 'john_doe_2']
    ]);

    $expected = [
      ['john_doe'],
      ['john_doe_2'],
    ];

    $this->assertSame($expected, $this->sqlite->getIrows("SELECT * FROM users"));
  }

  /** @test */
  public function testGetirowsMethodReturnsNullWhenQueryMethodReturnsFalse()
  {
    $sqlite = \Mockery::mock(Sqlite::class)->makePartial();

    $sqlite->shouldReceive('query')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $sqlite->getIrows('SELECT * FROM users')
    );
  }

  /** @test */
  public function testGetbycolumnsMethodReturnsAnIndexedArrayByTheSearchedField()
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

    $result   = $this->sqlite->getByColumns('SELECT name, email, username FROM users');
    $expected = [
      'name'     => ['john', 'smith'],
      'email'    => ['jdoe@example.com', 'sdoe@example.com'],
      'username' => ['jdoe', 'sdoe']
    ];

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function testGetobjectMethodReturnsTheFirstRowResultingFromAQueryAsObject()
  {
    $this->createTable('users', function () {
      return 'username VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'john_doe'],
      ['username' => 'john_doe_2']
    ]);

    $result = $this->sqlite->getObject("SELECT * FROM users");

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('username', $result);
    $this->assertSame('john_doe', $result->username);
  }

  /** @test */
  public function testGetobjectsMethodReturnsAnArrayOfObjectsResultingFromAQuery()
  {
    $this->createTable('users', function () {
      return 'username VARCHAR(255)';
    });

    $this->insertMany('users', [
      ['username' => 'john_doe'],
      ['username' => 'john_doe_2']
    ]);

    $result = $this->sqlite->getObjects("SELECT * FROM users");

    $this->assertIsArray($result);
    $this->assertCount(2, $result);
    $this->assertIsObject($result[0]);
    $this->assertIsObject($result[1]);
    $this->assertSame('john_doe', $result[0]->username);
    $this->assertSame('john_doe_2', $result[1]->username);
  }

  /** @test */
  public function testGetforeignkeysMethodReturnsAnArrayOfTablesAndFieldsRelatedToTheGivenForeignKey()
  {
    $this->setCacheExpectations();

    $this->createTable('roles', function () {
      return 'id INTEGER PRIMARY KEY,
              name VARCHAR(255)';
    });

    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY,
              username VARCHAR(255) UNIQUE,
              created_at DATETIME DEFAULT NULL,
              role_id INTEGER,
              CONSTRAINT user_role_id FOREIGN KEY (role_id) REFERENCES roles (id) ON UPDATE CASCADE ON DELETE RESTRICT';
    });

    $this->assertSame(
      ['users' => ['role_id']],
      $this->sqlite->getForeignKeys('id', 'roles')
    );

    $this->assertSame(
      [],
      $this->sqlite->getForeignKeys('id', 'roles', 'another_db')
    );

    $this->assertSame(
      [],
      $this->sqlite->getForeignKeys('role_id', 'users')
    );
  }

  /** @test */
  public function testHasidincrementMethodReturnsTrueIfTheGivenTableHasAutoIncrementFields()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY AUTOINCREMENT, id2 BIGINT';
    });

    $this->assertTrue(
      $this->sqlite->hasIdIncrement('users')
    );
  }

  /** @test */
  public function testHasidincrementMethodReturnsFalseIfTheGivenTableHasAutoIncrementFields()
  {
    $this->setCacheExpectations();

    $this->createTable('users', function () {
      return 'username VARCHAR(255)';
    });

    $this->assertFalse(
      $this->sqlite->hasIdIncrement('users')
    );
  }

  /** @test */
  public function testFmodelizeMethodReturnsFieldsStructureForTheGivenTable()
  {
    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY,
              username VARCHAR(255) DEFAULT NULL';
    });

    $this->sqlite->query("CREATE UNIQUE INDEX username ON users(username)");

    $this->setCacheExpectations();

    $result   = $this->sqlite->fmodelize('users');
    $expected = [
      'id' => [
        'position'  => 1,
        'null'      => 1,
        'key'       => 'PRI',
        'default'       => null,
        'extra'     => 'auto_increment',
        'maxlength'     => null,
        'signed'    => 1,
        'defaultExpression' => false,
        'type'      => 'INTEGER',
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
        'null'      => 1,
        'key'       => null,
        'default'    => 'NULL',
        'extra'     => null,
        'maxlength' => 255,
        'signed'    => 1,
        'defaultExpression' => false,
        'type'      => 'TEXT',
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
  public function testFmodelizeMethodReturnsNullWhenModelizeReturnsNull()
  {
    $sqlite = \Mockery::mock(Sqlite::class)->makePartial();

    $sqlite->shouldReceive('modelize')
      ->once()
      ->andReturnNull();

    $this->assertNull(
      $sqlite->fmodelize('users')
    );
  }

  /** @test */
  public function testErrorMethodSetsAnErrorAndActsBasedOnTheErrorModeWhenTheGivenErrorIsString()
  {
    $this->assertFalse($this->getNonPublicProperty('_has_error'));
    $this->assertFalse($this->getNonPublicProperty('_has_error_all'));
    $this->assertNull($this->getNonPublicProperty('last_error'));

    $this->createDir('logs');

    $this->sqlite->error('An error');

    $this->assertTrue($this->getNonPublicProperty('_has_error'));
    $this->assertTrue($this->getNonPublicProperty('_has_error_all'));
    $this->assertSame('An error', $this->getNonPublicProperty('last_error'));
    $this->assertFileExists($log_file = $this->getTestingDirName() . 'logs/db.log');
    $this->assertStringContainsString('An error', file_get_contents($log_file));
  }

  /** @test */
  public function testErrorMethodSetsAnErrorAndActsBasedOnTheErrorModeWhenTheGivenErrorAnException()
  {
    $this->assertFalse($this->getNonPublicProperty('_has_error'));
    $this->assertFalse($this->getNonPublicProperty('_has_error_all'));
    $this->assertNull($this->getNonPublicProperty('last_error'));

    $this->createDir('logs');

    $this->setNonPublicPropertyValue('last_real_params', [
      'values' => [true, false, 'An error in params']
    ]);

    $this->sqlite->error(new \Exception('An error'));

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
  public function testErrorMethodShouldThrowAnExceptionWhenModeIsToDie()
  {
    $this->expectException(\Exception::class);
    $this->setNonPublicPropertyValue('on_error', 'die');

    $this->sqlite->error('An error');
  }

  /** @test */
  public function testCheckMethodChecksIfTheDatabaseIsReadyToProcessAQuery()
  {
    $this->assertTrue($this->sqlite->check());
  }

  /** @test */
  public function testCheckMethodReturnsTrueIfThereIsAnErrorTheErrorModeIsContinue()
  {
    $this->setNonPublicPropertyValue('on_error', 'continue');
    $this->setNonPublicPropertyValue('_has_error', true);
    $this->setNonPublicPropertyValue('_has_error_all', true);

    $this->assertTrue($this->sqlite->check());
  }

  /** @test */
  public function testCheckMethodReturnsFalseIfThereIsAreErrorForAllConnectionAndModeIsStopAll()
  {
    $this->setNonPublicPropertyValue('_has_error_all', true);
    $this->setNonPublicPropertyValue('on_error', 'stop_all');

    $this->assertFalse($this->sqlite->check());
  }

  /** @test */
  public function testCheckMethodReturnsTrueIfThereIsAreErrorForAllConnectionAndModeIsNotStopAll()
  {
    $this->setNonPublicPropertyValue('_has_error_all', true);
    $this->setNonPublicPropertyValue('on_error', 'stop');

    $this->assertTrue($this->sqlite->check());
  }

  /** @test */
  public function testCheckMethodReturnsFalseIfThereIsErrorForTheCurrentConnectionAndModeIsStop()
  {
    $this->setNonPublicPropertyValue('_has_error', true);
    $this->setNonPublicPropertyValue('on_error', 'stop');

    $this->assertFalse($this->sqlite->check());
  }

  /** @test */
  public function testCheckMethodReturnsFalseIfThereIsErrorForTheCurrentConnectionAndModeIsStopAll()
  {
    $this->setNonPublicPropertyValue('_has_error', true);
    $this->setNonPublicPropertyValue('on_error', 'stop_all');

    $this->assertFalse($this->sqlite->check());
  }

  /** @test */
  public function testCheckMethodReturnsFalseWhenTheCurrentConnectionIsNull()
  {
    $old_current = $this->getNonPublicProperty('current');

    $this->setNonPublicPropertyValue('current', null);

    $this->assertFalse($this->sqlite->check());

    $this->setNonPublicPropertyValue('current', $old_current);
  }

  /** @test */
  public function testSeterrormodeMethodSetsTheErrorMode()
  {
    $result = $this->sqlite->setErrorMode('stop_all');

    $this->assertSame(
      'stop_all',
      $this->getNonPublicProperty('on_error')
    );

    $this->assertInstanceOf(Sqlite::class, $result);
  }

  /** @test */
  public function testGeterrormodeMethodReturnsTheCurrentErrorMode()
  {
    $this->setNonPublicPropertyValue('on_error', 'stop');

    $this->assertSame(Errors::E_STOP, $this->sqlite->getErrorMode());
  }

  /** @test */
  public function testGetloglineMethodReturnsAStringWithGivenTextInTheMiddleOfALineOfLogs()
  {
    $this->assertSame(
      '-------------------------------------- foo --------------------------------------',
      Sqlite::getLogLine('foo')
    );

    $this->assertSame(
      '--------------------------------- I\'m an error ----------------------------------',
      Sqlite::getLogLine('I\'m an error')
    );
  }

  /** @test */
  public function testGetlasterrorMethodReturnsTheLastError()
  {
    $this->assertNull($this->sqlite->getLastError());

    $this->setNonPublicPropertyValue('last_error', 'Error');

    $this->assertSame('Error', $this->sqlite->getLastError());
  }

  /** @test */
  public function testSetHasErrorAllSetsErrorsOnAllConnectionsToTrue()
  {
    $this->setNonPublicPropertyValue('_has_error_all', false);

    $method = $this->getNonPublicMethod('_set_has_error_all');
    $method->invoke($this->sqlite);

    $this->assertTrue($this->getNonPublicProperty('_has_error_all'));
  }


  /** @test */
  public function testQueryMethodExecutesAStatementAndReturnsQueryObjectForReadingStatements()
  {
    $this->createTable('users', function () {
      return 'id INTEGER PRIMARY KEY,
              name VARCHAR(50) NOT NULL';
    });

    $this->insertMany('users', [
      ['name' => 'John'],
      ['name' => 'Sam']
    ]);

    $result = $this->sqlite->query('SELECT * FROM users WHERE id >= ?', 1);

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
  public function testQueryMethodUsesTheSavedQuery()
  {
    $this->createTable('users', function () {
      return 'name VARCHAR(20), username VARCHAR(20)';
    });

    $this->insertMany('users', [
      ['name' => 'John', 'username' => 'jdoe'],
      ['name' => 'Sam', 'username' => 'sdoe'],
    ]);

    $result = $this->sqlite->query("SELECT username FROM users WHERE name = ?", 'John');

    $this->assertInstanceOf(\PDOStatement::class, $result);

    $this->assertSame(
      [['username' => 'jdoe']],
      $this->sqlite->fetchAllResults($result, \PDO::FETCH_ASSOC)

    );

    $result2 = $this->sqlite->query("SELECT username FROM users WHERE name = ?", 'Sam');

    $this->assertInstanceOf(\PDOStatement::class, $result2);

    $this->assertSame(
      [['username' => 'sdoe']],
      $this->sqlite->fetchAllResults($result2, \PDO::FETCH_ASSOC)
    );

    $this->sqlite->query("SELECT name FROM users WHERE username = ?", 'sdoe');

    $this->assertCount(
      2,
      $this->getNonPublicProperty('queries')
    );
  }

  /** @test */
  public function testQueryMethodThrowsAnExceptionIfTheGivenQueryIsNotValid()
  {
    $this->expectException(\Exception::class);

    $this->sqlite->query('foo');
  }

  /** @test */
  public function testQueryMethodSetsAnErrorIfTheGivenArgumentsAreGreaterThanQueryPlaceholders()
  {
    $this->expectException(\Exception::class);

    $this->sqlite->setErrorMode(Errors::E_DIE);

    $this->sqlite->query('SELECT * FROM user where id = ? AND user = ?', 1, 4, 5);
  }

  /** @test */
  public function testQueryMethodFillsTheMissingValuesWithTheLastGivenOneWhenNumberOfValuesAreSmallerThanQueryPlaceholders()
  {
    $this->createTable('users', function() {
      return 'name VARCHAR(255), username VARCHAR(255)';
    });

    $this->sqlite->query('INSERT INTO users (name, username) VALUES (?, ?)', 'John');

    $this->assertDatabaseHas('users', 'name', 'John');
    $this->assertDatabaseHas('users', 'username', 'John');
  }

  /** @test */
  public function testGetlastcfgMethodReturnsTheLastConfigForTheConnection()
  {
    $this->assertSame(
      $this->getNonPublicProperty('last_cfg'),
      $this->sqlite->getLastCfg()
    );
  }

  /** @test */
  public function testRenametableMethodRenamesTheGivenTableToTheNewGivenName()
  {
    $this->createTable('users', function () {
      return 'id INT';
    });

    $this->assertTrue(
      $this->sqlite->renameTable('users', 'users2')
    );

    $tables = $this->sqlite->getTables();

    $this->assertTrue(in_array('users2', $tables));
    $this->assertTrue(!in_array('users', $tables));
  }

  /** @test */
  public function testRenametableMethodReturnsFalseWhenCheckMethodReturnsFalse()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertFalse(
      $this->sqlite->renameTable('users', 'users')
    );
  }

  /** @test */
  public function testRenametableMethodReturnsFalseWhenTheGivenTableNamesAreNotValid()
  {
    $this->assertFalse(
      $this->sqlite->renameTable('users**', 'users2')
    );

    $this->assertFalse(
      $this->sqlite->renameTable('users', 'users2&&')
    );

    $this->assertFalse(
      $this->sqlite->renameTable('users**', 'users2&&')
    );
  }

  /** @test */
  public function testCreatecolumnMethodCreatesTheGivenColumnForTheGivenTable()
  {
    $this->createTable('users', function () {
      return 'id INT';
    });

    $this->assertTrue(
      $this->sqlite->createColumn('users', 'username', [
        'type' => 'varchar',
        'null' => false,
        'after' => 'id',
        'maxlength' => 255
      ])
    );

    $this->assertTrue(
      $this->sqlite->createColumn('users', 'created_at', [
        'type' => 'timestamp',
        'null' => false,
        'after' => 'id',
        'default' => 'CURRENT_TIMESTAMP',
      ])
    );

    $this->assertTrue(
      $this->sqlite->createColumn('users', 'balance', [
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
      'null' => 0,
      'key' => null,
      'default' => null,
      'extra' => null,
      'maxlength' => 255,
      'signed' => 1,
      'defaultExpression' => false,
      'type' => 'TEXT',
    ], $structure['username']);

    $this->assertArrayHasKey('created_at', $structure);
    $this->assertSame([
      'position' => 3,
      'null' => 0,
      'key' => null,
      'default' => 'CURRENT_TIMESTAMP',
      'extra' => null,
      'maxlength' => null,
      'signed' => 1,
      'defaultExpression' => true,
      'type' => 'INTEGER'
    ], $structure['created_at']);

    $this->assertArrayHasKey('balance', $structure);
    $this->assertSame([
      'position' => 4,
      'null' => 0,
      'key' => null,
      'default' => 0,
      'extra' => null,
      'maxlength' => 10,
      'signed' => 1,
      'defaultExpression' => false,
      'type' => 'REAL'
    ], $structure['balance']);
  }

  /** @test */
  public function testCreatecolumnMethodReturnsFalseWhenTheGivenColumnIsNotAValidName()
  {
    $this->assertFalse(
      $this->sqlite->createColumn('users', 'username**', [])
    );
  }

  /** @test */
  public function testDropcolumnMethodDropsTheGivenColumnForTheGivenTable()
  {
    $this->createTable('users', function () {
      return 'id INT, username VARCHAR(20), name VARCHAR(2)';
    });

    $this->assertTrue(
      $this->sqlite->dropColumn('users', 'username')
    );

    $this->assertTrue(
      $this->sqlite->dropColumn('users', 'name')
    );

    $structure = $this->getTableStructure('users')['fields'];

    $this->assertArrayNotHasKey('username', $structure);
    $this->assertArrayNotHasKey('name', $structure);
  }

  /** @test */
  public function testDropcolumnMethodReturnsFalseWhenTheGivenColumnIsANotValidName()
  {
    $this->assertFalse(
      $this->sqlite->dropColumn('users', 'id**')
    );
  }

  /** @test */
  public function testGetcolumndefinitionstatementMethodReturnsSqlStatementOfColumnDefinition()
  {
    $method = $this->getNonPublicMethod('getColumnDefinitionStatement');

    $cols = [
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
      ],
      'num' => [
        'default' => 0,
        'type' => 'foo'
      ],
      'login' => [
        'null' => false
      ]
    ];

    $expected = [
      'id' => '"id" blob(32) NOT NULL',
      'username' => '"username" text(255) NOT NULL',
      'role' => '"role" text NOT NULL DEFAULT \'user\'',
      'permission' => '"permission" text NOT NULL DEFAULT \'read\'',
      'balance' => '"balance" real(10) DEFAULT NULL',
      'balance_before' => '"balance_before" real(10) NOT NULL DEFAULT 0',
      'created_at' => '"created_at" text NOT NULL DEFAULT CURRENT_TIMESTAMP',
      'num' => '"num"  NOT NULL DEFAULT 0',
      'login' => '"login"  NOT NULL'
    ];

    foreach ($cols as $col_name => $col) {
      $this->assertSame(
        $expected[$col_name],
        trim($method->invoke($this->sqlite, $col_name, $col))
      );
    }
  }

  /** @test */
  public function testGetaltertableMethodReturnsSqlStringForAlterStatement()
  {
    $cfg = [
      'fields' => [
        'id' => [
          'type' => 'binary',
          'maxlength' => 32
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
ALTER TABLE "users" ADD   "id" blob(32) NOT NULL;
ALTER TABLE "users" ADD   "role" text NOT NULL DEFAULT 'user';
ALTER TABLE "users" ADD   "permission" text NOT NULL DEFAULT 'read';
ALTER TABLE "users" ADD   "balance" real(10) NOT NULL DEFAULT 0;
ALTER TABLE "users" ADD   "created_at" text NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE "users" DROP COLUMN "role_id";

SQL;


    $this->assertSame(
      $expected, $this->sqlite->getAlterTable('users', $cfg)
    );
  }

  /** @test */
  public function testGetaltertableMethodReturnsEmptyStringWhenTheGivenTableNameIsNotValid()
  {
    $this->assertSame('', $this->sqlite->getAlterTable('user**', ['fields' => ['a' => 'b']]));
  }

  /** @test */
  public function testGetaltertableMethodReturnsEmptyStringWhenCheckMethodReturnsFalse()
  {
    $this->setNonPublicPropertyValue('current', null);

    $this->assertSame('', $this->sqlite->getAlterTable('users', ['fields' => ['a' => 'b']]));
  }

  /** @test */
  public function testGetaltertableMethodThrowsAnExceptionIfTheFieldsPropertyIsMissing()
  {
    $this->expectException(\Exception::class);
    $this->sqlite->getAlterTable('users', ['a' => 'b']);
  }

  /** @test */
  public function testAlterMethodAltersTheGivenCfgForTheGivenTable()
  {
    $this->createTable('users', function () {
      return 'balance int(11) NOT NULL,
              role_id INT(11) DEFAULT 0,
              name TEXT';
    });

    $cfg = [
      'fields' => [
        'id' => [
          'type' => 'binary',
          'maxlength' => 32
        ],
        'role' => [
          'type' => 'enum',
          'extra' => "'super_admin','admin','user'",
          'default' => 'user'
        ],
        'name' => [
          'alter_type' => 'modify',
          'new_name' => 'username'
        ],
        'permission' => [
          'type' => 'set',
          'extra' => "'read','write'",
          'default' => 'read'
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

    $this->assertSame(
      1, $this->sqlite->alter('users', $cfg)
    );

    $structure = $this->getTableStructure('users')['fields'];

    $this->assertArrayHasKey('id', $structure);
    $this->assertArrayHasKey('role', $structure);
    $this->assertArrayHasKey('permission', $structure);
    $this->assertArrayHasKey('balance_before', $structure);
    $this->assertArrayHasKey('created_at', $structure);
    $this->assertArrayNotHasKey('name', $structure);
    $this->assertArrayNotHasKey('role_id', $structure);

    $this->assertSame('BLOB', $structure['id']['type']);
    $this->assertSame(32, $structure['id']['maxlength']);
    $this->assertSame(0, $structure['id']['null']);
    $this->assertSame(3, $structure['id']['position']);

    $this->assertSame('TEXT', $structure['role']['type']);
    $this->assertSame('user', $structure['role']['default']);
    $this->assertSame(4, $structure['role']['position']);

    $this->assertSame('TEXT', $structure['permission']['type']);
    $this->assertSame('read', $structure['permission']['default']);
    $this->assertSame(0, $structure['permission']['null']);
    $this->assertSame(5, $structure['permission']['position']);

    $this->assertSame('REAL', $structure['balance_before']['type']);
    $this->assertSame(6, $structure['balance_before']['position']);
    $this->assertSame(0, $structure['balance_before']['null']);
    $this->assertSame(0, $structure['balance_before']['default']);
    $this->assertSame(1, $structure['balance_before']['signed']);
    $this->assertSame(10, $structure['balance_before']['maxlength']);

    $this->assertSame('TEXT', $structure['created_at']['type']);
    $this->assertSame('CURRENT_TIMESTAMP', $structure['created_at']['default']);
    $this->assertSame(7, $structure['created_at']['position']);
    $this->assertSame(0, $structure['created_at']['null']);
  }

  /** @test */
  public function testGetaltercolumnMethodReturnsSqlStringForAlterColumn()
  {
    $this->assertSame(
      'ALTER TABLE "users"
ADD   "id" blob(32)',
      $this->sqlite->getAlterColumn('users', [
        'col_name' => 'id',
        'type' => 'binary',
        'maxlength' => 32,
        'null' => true
      ])
    );

    $this->assertSame(
      'ALTER TABLE "users"
RENAME COLUMN "name" TO "username" ',
      $this->sqlite->getAlterColumn('users', [
        'col_name' => 'name',
        'alter_type' => 'modify',
        'new_name' => 'username',
      ])
    );


    $this->assertSame(
      'ALTER TABLE "users"
ADD   "balance" real(10) NOT NULL DEFAULT 0',
      $this->sqlite->getAlterColumn('users', [
        'col_name' => 'balance',
        'type' => 'real',
        'maxlength' => 10,
        'decimals' => 2,
        'signed' => true,
        'default' => 0
      ])
    );

    $this->assertSame(
      'ALTER TABLE "users"
ADD   "role" text NOT NULL DEFAULT \'user\'',
      $this->sqlite->getAlterColumn('users', [
        'col_name' => 'role',
        'type' => 'enum',
        'extra' => "'super_admin','admin','user'",
        'default' => 'user'
      ])
    );


    $this->assertSame(
      'ALTER TABLE "users"
DROP COLUMN "name"',
      $this->sqlite->getAlterColumn('users', [
        'col_name' => 'name',
        'alter_type' => 'drop'
      ])
    );
  }
}