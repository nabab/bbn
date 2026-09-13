<?php

namespace bbn\tests\Db;

use bbn\Cache;
use bbn\Db;
use bbn\Db\Enums\Errors;
use bbn\Db\Languages\Mysql;
use PHPUnit\Framework\TestCase;
use bbn\tests\Reflectable;

class DbTest extends TestCase
{
  use Reflectable;

  protected Db $db;

  protected $mysql_mock;

  protected $cache_mock;

  protected function setUp(): void
  {
    $this->mysql_mock = \Mockery::mock(Mysql::class);
    $this->cache_mock = \Mockery::mock(Cache::class);

    $this->mysql_mock->shouldReceive('getCfg')
      ->once()
      ->withNoArgs()
      ->andReturn(array_merge($db_cfg = self::getDbConfig(), [
        'code_host' => "{$db_cfg['user']}@{$db_cfg['host']}"
      ]));

    $this->mysql_mock->shouldReceive('postCreation')
      ->once()
      ->withNoArgs();

    $this->mysql_mock->shouldReceive('startFancyStuff')
      ->once()
      ->withNoArgs()
      ->andReturnSelf();

    $this->mysql_mock->shouldReceive('__toString')
      ->andReturn('mysql');

    $this->db = new Db(self::getDbConfig());

    $this->setNonPublicPropertyValue('cache_engine', $this->cache_mock);
  }

  protected function tearDown(): void
  {
    \Mockery::close();
  }

  public function getInstance()
  {
    return $this->db;
  }


  protected function getDbConfig()
  {
    return [
      'engine' => $this->mysql_mock,
      'host' => 'localhost',
      'user' => 'root',
      'db' => 'bbn_test'
    ];
  }


  /** @test */
  public function testConstructorTest()
  {
    $db_cfg = self::getDbConfig();

    $this->assertInstanceOf(
      Db::class,
      $this->getNonPublicProperty('retriever_instance', $this->db)
    );

    $this->assertInstanceOf(Cache::class, $this->getNonPublicProperty('cache_engine'));
    $this->assertInstanceOf(Mysql::class, $this->getNonPublicProperty('language'));

    $this->assertSame('mysql', (string)$this->getNonPublicProperty('engine'));
  }

  /** @test */
  public function testConstructorThrowsAnExceptionWhenEngineIsNotProvided()
  {
    $this->expectException(\Exception::class);

    $db_config = self::getDbConfig();

    unset($db_config['engine']);

    $this->db = new Db($db_config);
  }

  /** @test */
  public function testIsenginesupportedMethodChecksIfTheGivenDbEngineIsSupportedOrNot()
  {
    $this->assertTrue(Db::isEngineSupported('mysql'));
    $this->assertTrue(Db::isEngineSupported('pgsql'));
    $this->assertTrue(Db::isEngineSupported('sqlite'));
    $this->assertFalse(Db::isEngineSupported('foo'));
  }

  /** @test */
  public function testGetengineiconMethodReturnsTheIconForTheGivenDbEngine()
  {
    foreach ($this->getNonPublicProperty('engines') as $engine => $icon) {
      $this->assertSame($icon, Db::getEngineIcon($engine));
    }

    $this->assertNull(Db::getEngineIcon('foo'));
  }

  /** @test */
  public function testGetcfgMethodReturnsTheConfig()
  {
    $this->mysql_mock->shouldReceive('getCfg')
      ->once()
      ->withNoArgs()
      ->andReturn(['foo' => 'bar']);

    $this->assertSame(['foo' => 'bar'], $this->db->getCfg());
  }

  /** @test */
  public function testGetengineMethodReturnsTheEngineUsedByTheCurrentConnection()
  {
    $this->assertSame('mysql', $this->db->getEngine());
  }

  /** @test */
  public function testGethostMethodReturnsTheHostOfTheCurrentConnection()
  {
    $this->mysql_mock->shouldReceive('getHost')
      ->once()
      ->withNoArgs()
      ->andReturn(self::getDbConfig()['host']);

    $this->assertSame(self::getDbConfig()['host'], $this->db->getHost());
  }

  /** @test */
  public function testGetcurrentMethodReturnsTheCurrentDatabaseOfTheCurrentConnection()
  {
    $this->mysql_mock->shouldReceive('getCurrent')
      ->once()
      ->withNoArgs()
      ->andReturn(self::getDbConfig()['db']);

    $this->assertSame(self::getDbConfig()['db'], $this->db->getCurrent());
  }

  /** @test */
  public function testGetlasterrorMethodReturnsTheLastError()
  {
    $this->mysql_mock->shouldReceive('getLastError')
      ->once()
      ->withNoArgs()
      ->andReturn('Error');

    $this->assertSame('Error', $this->db->getLastError());
  }

  /** @test */
  public function testToStringMethodReturnsAStringWhenTheObjectIsUsedAsAString()
  {
    $db_config = self::getDbConfig();

    $this->mysql_mock->shouldReceive('getHost')
      ->once()
      ->withNoArgs()
      ->andReturn($db_config['host']);

    $this->assertSame(
      "Connection {$db_config['engine']} to {$db_config['host']}",
      (string)$this->db
    );
  }

  /** @test */
  public function testGetconnectioncodeReturnsConnectionCode()
  {
    $db_cfg = self::getDbConfig();

    $this->mysql_mock->shouldReceive('getConnectionCode')
      ->once()
      ->withNoArgs()
      ->andReturn("{$db_cfg['user']}@{$db_cfg['host']}");

    $this->assertSame(
      "{$db_cfg['user']}@{$db_cfg['host']}",
      $this->db->getConnectionCode()
    );
  }

  /** @test */
  public function testGethashMethodReturnsTheCreatedHash()
  {
    $this->mysql_mock->shouldReceive('getHash')
      ->withNoArgs()
      ->once()
      ->andReturn('3819056v431b210daf45f9b5dc2');

    $this->assertSame(
      '3819056v431b210daf45f9b5dc2',
      $this->db->getHash()
    );
  }

  /** @test */
  public function testReplacetableinconditionsMethodTest()
  {
    // TODO: How this should work?
    $data = [
      [
        'field' => 'username',
        'exp' => 'john_doe'
      ],
      [
        'field' => 'users.first_name',
        'exp' => 'profiles.first_name'
      ],
      [
        'field' => '\`users\`.\`first_name\`',
        'exp' => '\`profiles_users\`.\`first_name\`'
      ]
    ];

    $expected = [
      [
        'field' => 'username',
        'exp' => 'john_doe'
      ],
      [
        'field' => 'users.last_name',
        'exp' => 'profiles.last_name'
      ],
      [
        'field' => '\`users\`.\`last_name\`',
        'exp' => '\`profiles_users\`.\`last_name\`'
      ]
    ];
    $this->assertTrue(true);
//    $this->assertSame($expected, $this->db->replaceTableInConditions($data, 'first_name', 'last_name'));
  }

  /** @test */
  public function testTreatconditionsMethodTest()
  {
    $this->mysql_mock->shouldReceive('treatConditions')
      ->with(['foo' => 'bar'], true)
      ->once()
      ->andReturn(['foo' => 'bar2']);

    $this->assertSame(
      ['foo' => 'bar2'],
      $this->db->treatConditions(['foo' => 'bar'], true)
    );
  }

  /** @test */
  public function testReprocesscfgMethodTest()
  {
    $this->mysql_mock->shouldReceive('reprocessCfg')
      ->once()
      ->with(['foo' => 'bar'])
      ->andReturn(['foo' => 'bar2']);

    $this->assertSame(['foo' => 'bar2'], $this->db->reprocessCfg(['foo' => 'bar']));
  }

  /** @test */
  public function testProcesscfgMethodTest()
  {
    $this->mysql_mock->shouldReceive('processCfg')
      ->once()
      ->with(['foo' => 'bar'], true)
      ->andReturn(['foo' => 'bar2']);

    $this->assertSame(['foo' => 'bar2'], $this->db->processCfg(['foo' => 'bar'], true));
  }

  /** @test */
  public function testCheckMethodChecksIfTheDatabaseIsReadyToProcessAQuery()
  {
    $this->mysql_mock->shouldReceive('check')
      ->once()
      ->withNoArgs()
      ->andReturnTrue();

    $this->assertTrue($this->db->check());
  }

  /** @test */
  public function testSeterrormodeMethodSetsTheErrorMode()
  {
    $this->mysql_mock->shouldReceive('setErrorMode')
      ->once()
      ->with(Errors::E_STOP_ALL)
      ->andReturnSelf();

    $result = $this->db->setErrorMode(Errors::E_STOP_ALL);

    $this->assertInstanceOf(Db::class, $result);
  }

  /** @test */
  public function testGeterrormodeMethodReturnsTheCurrentErrorMode()
  {
    $this->mysql_mock->shouldReceive('getErrorMode')
      ->once()
      ->withNoArgs()
      ->andReturn(Errors::E_STOP);

    $this->assertSame(Errors::E_STOP, $this->db->getErrorMode());
  }

  /** @test */
  public function testClearcacheMethodDeletesASpecificItemFromCacheWhenExists()
  {
    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with('bbn/Db/foo/method_name')
      ->andReturnTrue();

    $this->cache_mock->shouldReceive('deleteAll')
      ->once()
      ->with('bbn/Db/foo/method_name')
      ->andReturnTrue();

    $result = $this->db->clearCache('foo', 'method_name');

    $this->assertInstanceOf(Db::class, $result);
  }

  /** @test */
  public function testClearcacheMethodDoesNoeDeleteASpecificItemFromCacheWhenNotExists()
  {
    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with('bbn/Db/foo/method_name')
      ->andReturnFalse();

    $this->cache_mock->shouldNotReceive('deleteAll');

    $result = $this->db->clearCache('foo', 'method_name');

    $this->assertInstanceOf(Db::class, $result);
  }

  /** @test */
  public function testClearallcacheMethodClearsAllCache()
  {
    $this->cache_mock->shouldReceive('deleteAll')
      ->once()
      ->with('bbn/Db/')
      ->andReturnTrue();

    $result = $this->db->clearAllCache();

    $this->assertInstanceOf(Db::class, $result);
  }

  /** @test */
  public function testStopfancystuffMethodCallsStopfancystuffOnLanguageClass()
  {
    $this->mysql_mock->shouldReceive('stopFancyStuff')
      ->once()
      ->withNoArgs()
      ->andReturnSelf();

    $result = $this->db->stopFancyStuff();

    $this->assertInstanceOf(Db::class, $result);
  }

  /** @test */
  public function testStartfancystuffMethodCallsStartfancystuffOnLanguageClass()
  {
    $this->mysql_mock->shouldReceive('startFancyStuff')
      ->once()
      ->withNoArgs()
      ->andReturnSelf();

    $result = $this->db->startFancyStuff();

    $this->assertInstanceOf(Db::class, $result);
  }

  /** @test */
  public function testEnabletriggerMethodEnablesTriggerFunctions()
  {
    $this->mysql_mock->shouldReceive('enableTrigger')
      ->once()
      ->withNoArgs()
      ->andReturnSelf();

    $result = $this->db->enableTrigger();

    $this->assertInstanceOf(Db::class, $result);
  }

  /** @test */
  public function testDisabletriggerMethodDisableTheTriggerFunctions()
  {
    $this->mysql_mock->shouldReceive('disableTrigger')
      ->once()
      ->withNoArgs()
      ->andReturnSelf();

    $result = $this->db->disableTrigger();

    $this->assertInstanceOf(Db::class, $result);
  }

  /** @test */
  public function testIstriggerenabledMethodChecksIfTriggerEnabled()
  {
    $this->mysql_mock->shouldReceive('isTriggerEnabled')
      ->once()
      ->withNoArgs()
      ->andREturnTrue();

    $this->assertTrue($this->db->isTriggerEnabled());
  }


  /** @test */
  public function testIstriggerdisabledMethodChecksIfTriggerDisabled()
  {
    $this->mysql_mock->shouldReceive('isTriggerDisabled')
      ->once()
      ->withNoArgs()
      ->andReturnTrue();

    $this->assertTrue($this->db->isTriggerDisabled());
  }

  /** @test */
  public function testSettriggerMethodAppliesAFunctionEachTimeTheGivenMethodsAreCalled()
  {
    $callback = function () {
    };

    $this->mysql_mock->shouldReceive('setTrigger')
      ->once()
      ->with($callback, 'select', 'after', '*')
      ->andReturnSelf();

    $result = $this->db->setTrigger($callback, 'select', 'after');

    $this->assertInstanceOf(Db::class, $result);
  }

  /** @test */
  public function testGettriggersMethodReturnsTheCurrentTriggers()
  {
    $this->mysql_mock->shouldReceive('getTriggers')
      ->once()
      ->withNoArgs()
      ->andReturn(['foo' => 'bar']);

    $this->assertSame(['foo' => 'bar'], $this->db->getTriggers());
  }

  /** @test */
  public function testGetfieldslistMethodTestReturnsAnArrayWithFieldsForTheGivenTable()
  {
    $this->mysql_mock->shouldReceive('getFieldsList')
      ->once()
      ->with('table_name')
      ->andReturn([]);

    $this->assertSame([], $this->db->getFieldsList('table_name'));
  }

  /** @test */
  public function testGetforeignkeysMethodReturnsAnArrayWithTableAndFieldsRelatedToTheSearchedForeignKet()
  {
    $this->mysql_mock->shouldReceive('getForeignKeys')
      ->once()
      ->with('col_name', 'table_name', null)
      ->andReturn([]);

    $this->assertSame([], $this->db->getForeignKeys('col_name', 'table_name'));
  }

  /** @test */
  public function testHasidincrementMethodReturnsTrueIfTheTableHasAnAutoIncrementField()
  {
    $this->mysql_mock->shouldReceive('hasIdIncrement')
      ->once()
      ->with('table_name')
      ->andReturnTrue();

    $this->assertTrue($this->db->hasIdIncrement('table_name'));
  }

  /** @test */
  public function testModelizeMethodReturnsTableStructureAsAnArray()
  {
    $this->mysql_mock->shouldReceive('modelize')
      ->once()
      ->with('table_name', false)
      ->andReturn($result = [
        'keys' => [
          'PRIMARY' => [
            'columns' => [
              'user_id'
            ]
          ]
        ]
      ]);

    $this->assertSame($result, $this->db->modelize('table_name'));
  }

  /** @test */
  public function testFmodelizeMethodTest()
  {
    $this->mysql_mock->shouldReceive('fmodelize')
      ->once()
      ->with('table_name', false)
      ->andReturn($result = [
        ['name' => 'field_1', 'keys' => []]
      ]);

    $this->assertSame($result, $this->db->fmodelize('table_name'));
  }

  /** @test */
  public function testFindreferencesMethodTest()
  {
    $this->mysql_mock->shouldReceive('findReferences')
      ->once()
      ->with('col_name', '')
      ->andReturn(['foo' => 'bar']);

    $this->assertSame(['foo' => 'bar'], $this->db->findReferences('col_name'));
  }

  /** @test */
  public function testFindrelationsMethodTest()
  {
    $this->mysql_mock->shouldReceive('findRelations')
      ->once()
      ->with('col_name', '')
      ->andReturnNull();

    $this->assertNull($this->db->findRelations('col_name'));
  }

  /** @test */
  public function testGetprimaryMethodReturnsPrimaryKeysOfTheGivenTableAsArray()
  {
    $this->mysql_mock->shouldReceive('getPrimary')
      ->once()
      ->with('table_name')
      ->andReturn(['id']);

    $this->assertSame(['id'], $this->db->getPrimary('table_name'));
  }

  /** @test */
  public function testGetuniqueprimaryMethodReturnsTheUniquePrimaryForTheGivenTable()
  {
    $this->mysql_mock->shouldReceive('getUniquePrimary')
      ->once()
      ->with('table_name')
      ->andReturn('id');

    $this->assertSame('id', $this->db->getUniquePrimary('table_name'));
  }

  /** @test */
  public function testGetuniquekeysMethodReturnTheUniqueKeysOfTheGivenTableAsArray()
  {
    $this->mysql_mock->shouldReceive('getUniqueKeys')
      ->once()
      ->with('table_name')
      ->andReturn(['col_1', 'col_2']);

    $this->assertSame(['col_1', 'col_2'], $this->db->getUniqueKeys('table_name'));
  }

  /** @test */
  public function testEscapevalueMethodEscapesTheGivenString()
  {
    $this->assertSame("Foo \' bar", $this->db->escapeValue("Foo ' bar"));
    $this->assertSame('Foo \" bar', $this->db->escapeValue('Foo " bar', '"'));
  }

  /** @test */
  public function testSetlastinsertidMethodChangesTheValueOfTheLastInsertId()
  {
    $this->mysql_mock->shouldReceive('setLastInsertId')
      ->once()
      ->with(2)
      ->andReturnSelf();

    $this->assertInstanceOf(Db::class, $this->db->setLastInsertId(2));
  }

  /** @test */
  public function testLastMethodReturnsTheLastQueryForTheCurrentConnection()
  {
    $this->mysql_mock->shouldReceive('last')
      ->once()
      ->withNoArgs()
      ->andReturn($result = 'INSERT INTO `db_example.table_user` (`name`) VALUES (?)');

    $this->assertSame($result, $this->db->last());
  }

  /** @test */
  public function testLastidMethodReturnsTheLastInsertedId()
  {
    $this->mysql_mock->shouldReceive('lastId')
      ->once()
      ->withNoArgs()
      ->andReturn(12);

    $this->assertSame(12, $this->db->lastId());
  }

  /** @test */
  public function testFlushMethodDeletedAllRecordedQueriesAndReturnsTheirNumber()
  {
    $this->mysql_mock->shouldReceive('flush')
      ->once()
      ->withNoArgs()
      ->andReturn(6);

    $this->assertSame(6, $this->db->flush());
  }

  /** @test */
  public function testCountqueriesMethodReturnsNumberOfQueries()
  {
    $this->mysql_mock->shouldReceive('countQueries')
      ->once()
      ->withNoArgs()
      ->andReturn(6);

    $this->assertSame(6, $this->db->countQueries());
  }

  /** @test */
  public function testGetoneMethodExecutesTheGivenQueryAndReturnsTheFirstCellResult()
  {
    $this->mysql_mock->shouldReceive('getOne')
      ->once()
      ->with($query = 'SELECT name FROM table_users WHERE id > ?', 11)
      ->andReturn('john');

    $this->assertSame('john', $this->db->getOne($query, 11));
  }

  /** @test */
  public function testGetvarMethodExecutesTheGivenQueryAndReturnsTheFirstCellResult()
  {
    $this->mysql_mock->shouldReceive('getOne')
      ->once()
      ->with($query = 'SELECT name FROM table_users WHERE id > ?', 11)
      ->andReturn('john');

    $this->assertSame('john', $this->db->getVar($query, 11));
  }

  /** @test */
  public function testGetkeyvalMethodReturnsAnIndexedArrayOfTheFirstFieldOfTheRequest()
  {
    $this->mysql_mock->shouldReceive('getKeyVal')
      ->once()
      ->with($query = 'SELECT name,id_group FROM table_users')
      ->andReturn($result = [
        'John' => 1,
        'Michael' => 1,
        'Barbara' => 1
      ]);

    $this->assertSame($result, $this->db->getKeyVal($query));
  }

  /** @test */
  public function testGetcolarrayMethodReturnAnArrayWithTheValuesOfSingleFieldResultingFromTheQuery()
  {
    $this->mysql_mock->shouldReceive('getColArray')
      ->once()
      ->with($query = 'SELECT id FROM table_users')
      ->andReturn(['john', 'doe']);

    $this->assertSame(['john', 'doe'], $this->db->getColArray($query));
  }

  /** @test */
  public function testSelectMethodReturnsTheFirstRowResultingFromTheQueryAsObject()
  {
    $this->mysql_mock->shouldReceive('select')
      ->once()
      ->with('table_users', ['name', 'surname'], [['id', '>', '2']], [], 0)
      ->andReturn(
        $result = (object)[
          'name' => 'john',
          'lastname' => 'doe'
        ]
      );

    $this->assertSame(
      $result,
      $this->db->select('table_users', ['name', 'surname'], [['id', '>', '2']])
    );
  }

  /** @test */
  public function testSelectallMethodReturnsTableRowsResultingFromTheQueryAsAnArrayOfObjects()
  {
    $this->mysql_mock->shouldReceive('selectAll')
      ->once()
      ->with("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2, 0)
      ->andReturn($result = [
        (object)[
          'id' => '12',
          'name' => 'john'
        ]
      ]);

    $this->assertSame(
      $result,
      $this->db->selectAll("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2)
    );
  }

  /** @test */
  public function testIselectMethodReturnsTheFirstRowResultingFromTheQueryAsAnArray()
  {
    $this->mysql_mock->shouldReceive('iselect')
      ->once()
      ->with("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2)
      ->andReturn($result = [33, 'john']);

    $this->assertSame(
      $result,
      $this->db->iselect("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2)
    );
  }

  /** @test */
  public function testIselectallMethodReturnsTheSearchedRowsAsAnArrayOfNumericArrays()
  {
    $this->mysql_mock->shouldReceive('iselectAll')
      ->once()
      ->with("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2, 0)
      ->andReturn($result = [
        [2, 'john'],
        [12, 'smith']
      ]);

    $this->assertSame(
      $result,
      $this->db->iselectAll("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2)
    );
  }

  /** @test */
  public function testRselectMethodReturnsTheFirstRowResultingFromTheQueryAsAnIndexedArray()
  {
    $this->mysql_mock->shouldReceive('rselect')
      ->once()
      ->with("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2)
      ->andReturn($result = [
        'id' => 12,
        'name' => 'john'
      ]);

    $this->assertSame(
      $result,
      $this->db->rselect("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2)
    );
  }

  /** @test */
  public function testRselectallMethodReturnsTableRowsAsAnArrayOfIndexedArray()
  {
    $this->mysql_mock->shouldReceive('rselectAll')
      ->once()
      ->with("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2, 0)
      ->andReturn($result = [
        [2, 'john'],
        [12, 'smith']
      ]);

    $this->assertSame(
      $result,
      $this->db->rselectAll("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2)
    );
  }

  /** @test */
  public function testSelectoneMethodReturnsASingleValue()
  {
    $this->mysql_mock->shouldReceive('selectOne')
      ->once()
      ->with("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2)
      ->andReturn($result = 'john');

    $this->assertSame(
      $result,
      $this->db->selectOne("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2)
    );
  }

  /** @test */
  public function testCountMethodReturnsNumberOfRecordsInTheTableCorrespondingToTheWhereCondition()
  {
    $this->mysql_mock->shouldReceive('count')
      ->once()
      ->with('table_users', ['name' => 'John'])
      ->andReturn(12);

    $this->assertSame(
      12,
      $this->db->count('table_users', ['name' => 'John'])
    );
  }

  /** @test */
  public function testSelectallbykeysMethodReturnsAnArrayOfTheFirstFieldOfTheRequest()
  {
    $this->mysql_mock->shouldReceive('selectAllByKeys')
      ->once()
      ->with("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2, 0)
      ->andReturn($result = [
        'john' => [
          'id' => '12'
        ]
      ]);

    $this->assertSame(
      $result,
      $this->db->selectAllByKeys("table_users", ["id", "name"], [["id", ">", 1]], ["id" => "ASC"], 2)
    );
  }

  /** @test */
  public function testStatMethodReturnsAnArrayWithTheCountOfValuesCorrespondingTheWhereCondition()
  {
    $this->mysql_mock->shouldReceive('stat')
      ->once()
      ->with('table_user', 'name', ['name' => '%n'], ["id" => "ASC"])
      ->andReturn($result = [
        ['num' => 1, 'name' => 'john']
      ]);

    $this->assertSame(
      $result,
      $this->db->stat('table_user', 'name', ['name' => '%n'], ["id" => "ASC"])
    );
  }

  /** @test */
  public function testGetfieldvaluesMethodReturnsTheUniqueValuesOfAColumnAsANumericIndexedArray()
  {
    $this->mysql_mock->shouldReceive('getColumnValues')
      ->once()
      ->with("table_users", "surname", [['id', '>', '2']], ["id" => "ASC"], 0, 0)
      ->andReturn($result = ['john', 'smith']);

    $this->assertSame(
      $result,
      $this->db->getFieldValues("table_users", "surname", [['id', '>', '2']], ["id" => "ASC"])
    );
  }

  /** @test */
  public function testGetcolumnvaluesMethodReturnsANumericArrayWithTheValuesOfTheUniqueColumnForTheGivenTable()
  {
    $this->mysql_mock->shouldReceive('getColumnValues')
      ->once()
      ->with("table_users", "surname", [['id', '>', '2']], ["id" => "ASC"], 0, 0)
      ->andReturn($result = ['john', 'smith']);

    $this->assertSame(
      $result,
      $this->db->getColumnValues("table_users", "surname", [['id', '>', '2']], ["id" => "ASC"])
    );
  }

  /** @test */
  public function testCountfieldvaluesMethodReturnsCountOfIdenticalValuesInAFieldAsArray()
  {
    $this->mysql_mock->shouldReceive('countFieldValues')
      ->once()
      ->with("table_users", "surname", [['id', '>', '2']], ["id" => "ASC"])
      ->andReturn($result = ['num' => 12, 'name' => 'smith']);

    $this->assertSame(
      $result,
      $this->db->countFieldValues("table_users", "surname", [['id', '>', '2']], ["id" => "ASC"])
    );
  }

  /** @test */
//  public function testGetvaluescountMethodReturnsAStringOfTheSqlQueryToCountValuesInAFieldOfTheTable()
//  {
//    $this->mysql_mock->shouldReceive('countFieldValues')
//      ->once()
//      ->with('table_users','name', ['surname','=','smith'], [])
//      ->andReturn(
//        $result = "SELECT COUNT(*) AS num, `name` AS val FROM `db_example`.`table_users`
//                    GROUP BY `name`
//                    ORDER BY `name`"
//      );
//
//    $this->assertSame(
//      $result,
//      $this->db->getValuesCount('table_users','name', ['surname','=','smith'])
//    );
//  }

  /** @test */
  public function testInsertMethodInsertsRowsInDatabase()
  {
    $this->mysql_mock->shouldReceive('insert')
      ->with(
        "table_users", [
        ["name" => "Ted"], ["surname" => "McLow"]
      ], false
      )
      ->once()
      ->andReturn(1);

    $this->assertSame(
      1,
      $this->db->insert("table_users", [
        ["name" => "Ted"], ["surname" => "McLow"]
      ])
    );
  }

  /** @test */
  public function testInsertupdateMethodInsertNewRowIfNotExistsAndUpdateOtherwise()
  {
    $this->mysql_mock->shouldReceive('insertUpdate')
      ->once()
      ->with('table_users', ['id' => 40, 'name' => 'john'])
      ->andReturn(1);

    $this->assertSame(
      1,
      $this->db->insertUpdate('table_users', ['id' => 40, 'name' => 'john'])
    );
  }

  /** @test */
  public function testUpdateMethodUpdatedRowsInDatabase()
  {
    $this->mysql_mock->shouldReceive('update')
      ->once()
      ->with('table_users', ['name' => 'john'], ['id' => 40], false)
      ->andReturn(1);

    $this->assertSame(
      1,
      $this->db->update('table_users', ['name' => 'john'], ['id' => 40])
    );
  }

  /** @test */
  public function testUpdateignoreMethodUpdatesRowsInDatabaseIfNotExistOtherwiseIgnore()
  {
    $this->mysql_mock->shouldReceive('update')
      ->once()
      ->with('table_users', ['name' => 'john'], ['id' => 40], true)
      ->andReturn(1);

    $this->assertSame(
      1,
      $this->db->updateIgnore('table_users', ['name' => 'john'], ['id' => 40])
    );
  }

  /** @test */
  public function testDeleteMethodDeletesRowsInDatabase()
  {
    $this->mysql_mock->shouldReceive('delete')
      ->once()
      ->with('table_users', ['id' => 40], false)
      ->andReturn(1);

    $this->assertSame(
      1,
      $this->db->delete('table_users', ['id' => 40])
    );
  }

  /** @test */
  public function testDeleteignoreMethodDeletesRowsInDatabaseIfExistsOtherwiseIgnore()
  {
    $this->mysql_mock->shouldReceive('delete')
      ->once()
      ->with('table_users', ['id' => 40], true)
      ->andReturn(1);

    $this->assertSame(
      1,
      $this->db->deleteIgnore('table_users', ['id' => 40])
    );
  }

  /** @test */
  public function testInsertignoreMethodInsertsRowInDatabaseIfNotExistOthewiseIgnore()
  {
    $this->mysql_mock->shouldReceive('insert')
      ->once()
      ->with('table_users', ['id' => 40], true)
      ->andReturn(1);

    $this->assertSame(
      1,
      $this->db->insertIgnore('table_users', ['id' => 40])
    );
  }

  /** @test */
  public function testTruncateMethodDeletesAllRecordsFromDatabase()
  {
    $this->mysql_mock->shouldReceive('delete')
      ->once()
      ->with('table_users', [], false)
      ->andReturn(1);

    $this->assertSame(1, $this->db->truncate('table_users'));
  }

  /** @test */
  public function testFetchMethodReturnsAnIndexedArrayWithTheFirstResultOfQueryOrFalseIfNoResults()
  {
    $this->mysql_mock->shouldReceive('fetch')
      ->once()
      ->with($query = 'SELECT name FROM users WHERE id = 10')
      ->andReturn($result = [
        'name' => 'john',
        0 => 'john'
      ]);

    $this->assertSame($result, $this->db->fetch($query));
  }

  /** @test */
  public function testFetchallMethodReturnsAnIndexedArrayOfAllResultsOfTheQueryOfFalseIfNoResults()
  {
    $this->mysql_mock->shouldReceive('fetchAll')
      ->once()
      ->with($query = "SELECT name FROM users WHERE name = 'john'")
      ->andReturn($result = [
        ['name' => 'john', 0 => 'john'],
        ['name' => 'smith', 0 => 'smith']
      ]);

    $this->assertSame($result, $this->db->fetchAll($query));
  }

  
  /** @test */
  public function testFetchcolumnMethodReturnsASingleColumnFromTheNextRowOfAResultSet()
  {
    $this->mysql_mock->shouldReceive('fetchColumn')
      ->once()
      ->with($query = "SELECT id, name FROM users WHERE name = 'john'", 1)
      ->andReturn($result = 'john');

    $this->assertSame($result, $this->db->fetchColumn($query, 1));
  }

  
  /** @test */
  public function testFetchobjectMethod()
  {
    $this->mysql_mock->shouldReceive('fetchObject')
      ->once()
      ->with($query = "SELECT id, name FROM users WHERE name = 'john'")
      ->andReturn($result = (object)[
        'id' => 1,
        'name' => 'john'
      ]);

    $this->assertSame($result, $this->db->fetchObject($query));
  }

  /** @test */
  public function testQueryMethodExecutesAWritingStmtAndReturnTheNumberOfAffectedRowsOrReturnAQueryObjectForReadingStmts()
  {
    $this->mysql_mock->shouldReceive('query')
      ->once()
      ->with($query = "DELETE FROM users WHERE id = '12'")
      ->andReturn($result = 1);

    $this->mysql_mock->shouldReceive('check')
      ->once()
      ->withNoArgs()
      ->andReturnTrue();

    $this->assertSame($result, $this->db->query($query));
  }

  /** @test */
  public function testQueryMethodDoesExecutesAWritingStmtWhenCheckReturnsFalse()
  {
    $this->mysql_mock->shouldNotReceive('query');

    $this->mysql_mock->shouldReceive('check')
      ->once()
      ->withNoArgs()
      ->andReturn();

    $this->db->query("DELETE FROM users WHERE id = '12'");

    $this->assertTrue(true);
  }

  /** @test */
  public function testTfnMethodReturnsTableFullName()
  {
    $this->mysql_mock->shouldReceive('tableFullName')
      ->once()
      ->with('table_users', false)
      ->andReturn($result = 'db.table_users');

    $this->assertSame($result, $this->db->tfn('table_users'));
  }

  /** @test */
  public function testTsnMethodReturnsTableSimpleName()
  {
    $this->mysql_mock->shouldReceive('tableSimpleName')
      ->once()
      ->with('db.table_users', false)
      ->andReturn($result = 'table_users');

    $this->assertSame($result, $this->db->tsn('db.table_users'));
  }

  /** @test */
  public function testCfnMethodReturnsColumnFullName()
  {
    $this->mysql_mock->shouldReceive('colFullName')
      ->once()
      ->with('name', 'table_users', false)
      ->andReturn($result = 'table_users.name');

    $this->assertSame($result, $this->db->cfn('name', 'table_users'));
  }

  /** @test */
  public function testCsnMethodReturnsColumnSimpleName()
  {
    $this->mysql_mock->shouldReceive('colSimpleName')
      ->once()
      ->with('table_users.name', false)
      ->andReturn($result = 'name');

    $this->assertSame($result, $this->db->csn('table_users.name'));
  }

  /** @test */
  public function testPostcreationMethodDoesActionsOnceConnectionIsCreatedAndEngineIsNotDefinedYet()
  {
    $this->mysql_mock->shouldReceive('postCreation')
      ->once()
      ->withNoArgs();

    $this->setNonPublicPropertyValue('engine', null);

    $this->db->postCreation();
    $this->assertTrue(true);
  }

  /** @test */
  public function testPostcreationMethodDoesNotForwardTheCallToLanguageIfEngineIsDefined()
  {
    $this->mysql_mock->shouldNotReceive('postCreation');

    $this->db->postCreation();
    $this->assertTrue(true);
  }

  /** @test */
  public function testChangeMethodChangesTheDatabaseToTheGivenOne()
  {
    $this->mysql_mock->shouldNotReceive('change')
      ->once()
      ->with('bbn_test_2')
      ->andReturnTrue();

    $result = $this->db->change('bbn_test_2');

    $this->assertInstanceOf(Db::class, $result);
  }


  /** @test */
  public function testEscapeMethodEscapesNamesWithAppropriateQuotes()
  {
    $this->mysql_mock->shouldReceive('escape')
      ->once()
      ->with('table_users')
      ->andReturn($result = '`table_users`');

    $this->assertSame($result, $this->db->escape('table_users'));
  }

  /** @test */
  public function testTablefullnameMethodReturnsTableFullName()
  {
    $this->mysql_mock->shouldReceive('tableFullName')
      ->once()
      ->with('table_users', false)
      ->andReturn($result = 'db.table_users');

    $this->assertSame($result, $this->db->tableFullName('table_users'));
  }

  /** @test */
  public function testIstablefullnameMethodReturnsTrueIfTheGivenStringIsAFullNameOfATable()
  {
    $this->mysql_mock->shouldReceive('isTableFullName')
      ->once()
      ->with('db.table_users')
      ->andReturnTrue();

    $this->assertTrue($this->db->isTableFullName('db.table_users'));
  }

  /** @test */
  public function testIscolfullnameMethodReturnsTrueIfTheGivenStringIsAFullNameOfAColumn()
  {
    $this->mysql_mock->shouldReceive('isColFullName')
      ->once()
      ->with('table_users.name')
      ->andReturnTrue();

    $this->assertTrue($this->db->isColFullName('table_users.name'));
  }

  /** @test */
  public function testTablesimplenameMethodReturnsTableSimpleName()
  {
    $this->mysql_mock->shouldReceive('tableSimpleName')
      ->once()
      ->with('db.table_users', false)
      ->andReturn($result = 'table_users');

    $this->assertSame($result, $this->db->tableSimpleName('db.table_users'));
  }

  /** @test */
  public function testColfullnameMethodReturnsColumnFullName()
  {
    $this->mysql_mock->shouldReceive('colFullName')
      ->once()
      ->with('name', 'table_users', false)
      ->andReturn($result = 'table_users.name');

    $this->assertSame($result, $this->db->colFullName('name', 'table_users'));
  }

  /** @test */
  public function testColsimplenameMethodReturnsColumnSimpleName()
  {
    $this->mysql_mock->shouldReceive('colSimpleName')
      ->once()
      ->with('table_users.name', false)
      ->andReturn($result = 'name');

    $this->assertSame($result, $this->db->colSimpleName('table_users.name'));
  }

  /** @test */
  public function testDisablekeysMethodDisableForeignKeyConstraints()
  {
    $this->mysql_mock->shouldReceive('disableKeys')
      ->once()
      ->withNoArgs()
      ->andReturnSelf();

    $this->assertInstanceOf(Db::class, $this->db->disableKeys());
  }

  /** @test */
  public function testEnablekeysMethodEnableForeignKeyConstraints()
  {
    $this->mysql_mock->shouldReceive('enableKeys')
      ->once()
      ->withNoArgs()
      ->andReturnSelf();

    $this->assertInstanceOf(Db::class, $this->db->enableKeys());
  }

  /** @test */
  public function testGetdatabasesMethodReturnsDatabasesNamesAsArray()
  {
    $this->mysql_mock->shouldReceive('getDatabases')
      ->once()
      ->withNoArgs()
      ->andReturn($result = ['customers', 'mail']);

    $this->assertSame($result, $this->db->getDatabases());
  }

  /** @test */
  public function testGettablesMethodReturnsTablesNamesOfTheDatabaseAsAnArray()
  {
    $this->mysql_mock->shouldReceive('getTables')
      ->once()
      ->with('')
      ->andReturn($result = ['users', 'history']);

    $this->assertSame($result, $this->db->getTables());
  }

  /** @test */
  public function testGetcolumnsMethodReturnsColumnsStructureOfATableAsAnArrayIndexedWithFieldsNames()
  {
    $this->mysql_mock->shouldReceive('getColumns')
      ->once()
      ->with('users')
      ->andReturn($result = [
        'id' => [
          'position' => 1,
          'key' => 'PRI',
          'default' => null,
          'extra' => 'auto_increment',
          'signed' => 0,
          'maxlength' => '8',
          'type' => 'int',
        ]
      ]);

    $this->assertSame($result, $this->db->getColumns('users'));
  }

  /** @test */
  public function testGetkeysMethodReturnsTablesKeysAsAnArrayIndexedWithFieldsNames()
  {
    $this->mysql_mock->shouldReceive('getKeys')
      ->once()
      ->with('users')
      ->andReturn($result = [
        'keys' => [
          'PRIMARY' => [
            'columns' => ['id']
          ],
          'ref_db' => null,
          'ref_table' => null,
          'ref_column' => null,
          'unique' => 1
        ],
        'cols' => [
          'id' => [
            'PRIMARY'
          ]
        ]
      ]);

    $this->assertSame($result, $this->db->getKeys('users'));
  }

  /** @test */
  public function testGetconditionsMethodReturnsAStringWithTheConditionsForAnyFilterClause()
  {
    $this->mysql_mock->shouldReceive('getConditions')
      ->once()
      ->with($conditions = [
        'conditions' => [
          [
            'field' => 'id',
            'operator' => '=',
            'value' => 12
          ]
        ]
      ], [], false, 0)
      ->andReturn($result = 'id = 12');

    $this->assertSame($result, $this->db->getConditions($conditions));
  }

  /** @test */
  public function testGetselectMethodReturnsSqlStringForSelectStatement()
  {
    $cfg = [
      'tables' => ['users'],
      'fields' => ['id', 'name']
    ];

    $this->mysql_mock->shouldReceive('getSelect')
      ->once()
      ->with($cfg)
      ->andReturn($result = 'SELECT id, name FROM db.users');

    $this->assertSame($result, $this->db->getSelect($cfg));
  }

  /** @test */
  public function testGetselectMethodThrowsAnExceptionIfMethodNotFoundOnLanguageClass()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->getSelect(['foo' => 'bar']);
  }

  /** @test */
  public function testGetinsertMethodReturnsSqlStringForInsertStatement()
  {
    $cfg = [
      'tables' => ['users'],
      'fields' => ['id', 'name']
    ];

    $this->mysql_mock->shouldReceive('getInsert')
      ->once()
      ->with($cfg)
      ->andReturn($result = 'INSERT INTO db.users (id, name) VALUES (?, ?)');

    $this->mysql_mock->shouldReceive('processCfg')
      ->once()
      ->with(array_merge($cfg, ['kind' => 'INSERT']), false)
      ->andReturn($cfg);

    $this->assertSame($result, $this->db->getInsert($cfg));
  }

  /** @test */
  public function testGetinsertMethodThrowsAnExceptionIfMethodNotFoundOnLanguageClass()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->getInsert(['foo' => 'bar']);
  }

  /** @test */
  public function testGetupdateMethodReturnsSqlStringForUpdateStatement()
  {
    $cfg = [
      'tables' => ['users'],
      'fields' => ['id', 'name']
    ];

    $this->mysql_mock->shouldReceive('getUpdate')
      ->once()
      ->with($cfg)
      ->andReturn($result = 'UPDATE db.users SET id = ?, name = ?');

    $this->mysql_mock->shouldReceive('processCfg')
      ->once()
      ->with(array_merge($cfg, ['kind' => 'UPDATE']), false)
      ->andReturn($cfg);

    $this->assertSame($result, $this->db->getUpdate($cfg));
  }

  /** @test */
  public function testGetupdateMethodThrowsAnExceptionIfMethodNotFoundOnLanguageClass()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->getUpdate(['foo' => 'bar']);
  }

  /** @test */
  public function testGetdeleteMethodReturnsSqlStringForDeleteStatement()
  {
    $cfg = [
      'tables' => ['users']
    ];

    $this->mysql_mock->shouldReceive('getDelete')
      ->once()
      ->with($cfg)
      ->andReturn($result = 'DELETE FROM db.users');

    $this->mysql_mock->shouldReceive('processCfg')
      ->once()
      ->with(array_merge($cfg, ['kind' => 'DELETE']), false)
      ->andReturn($cfg);

    $this->assertSame($result, $this->db->getDelete($cfg));
  }

  /** @test */
  public function testGetdeleteMethodThrowsAnExceptionIfMethodNotFoundOnLanguageClass()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->getDelete(['foo' => 'bar']);
  }

  /** @test */
  public function testGetjoinMethodReturnsSqlStringForJoinClauseIfExistsAndEmptyOtherwise()
  {
    $cfg = [
      'join' => [
        'table' => 'users',
        'on' => [
          'conditions' => [
            [
              'field' => 'id',
              'operator' => '=',
              'value' => '1'
            ]
          ],
        ]
      ]
    ];

    $this->mysql_mock->shouldReceive('getJoin')
      ->once()
      ->with($cfg)
      ->andReturn($result = ' JOIN db.users ON id = 1');

    $this->assertSame($result, $this->db->getJoin($cfg));
  }

  /** @test */
  public function testGetjoinMethodThrowsAnExceptionIfMethodNotFoundOnLanguageClass()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->getJoin(['foo' => 'bar']);
  }

  /** @test */
  public function testGetwhereMethodReturnsSqlStringForWhereClause()
  {
    $cfg = [
      'tables' => ['users'],
      'fields' => ['id']
    ];

    $this->mysql_mock->shouldReceive('getWhere')
      ->once()
      ->with($cfg)
      ->andReturn($result = ' WHERE 1 AND id = ?');

    $this->assertSame($result, $this->db->getWhere($cfg));
  }

  /** @test */
  public function testGetwhereMethodThrowsAnExceptionIfMethodNotFoundOnLanguageClass()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->getWhere(['foo' => 'bar']);
  }

  /** @test */
  public function testGetgroupbyMethodReturnsSqlStringForGroupByClauseIfExistsAndEmptyOtherwise()
  {
    $cfg = [
      'group_by' => ['id', 'name'],
      'available_fields' => ['id', 'name']
    ];

    $this->mysql_mock->shouldReceive('getGroupBy')
      ->once()
      ->with($cfg)
      ->andReturn($result = ' GROUP BY id, name');

    $this->assertSame($result, $this->db->getGroupBy($cfg));
  }

  /** @test */
  public function testGetgroupbyMethodThrowsAnExceptionIfMethodNotFoundOnLanguageClass()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->getGroupBy(['foo' => 'bar']);
  }

  /** @test */
  public function testGethavingMethodReturnsSqlStringForHavingClauseIfExists()
  {
    $cfg = [
      'group_by' => ['id', 'name'],
      'available_fields' => ['id', 'name'],
      'having' => ['id > 12']
    ];

    $this->mysql_mock->shouldReceive('getHaving')
      ->once()
      ->with($cfg)
      ->andReturn($result = ' HAVING id > 12');

    $this->assertSame($result, $this->db->getHaving($cfg));
  }

  /** @test */
  public function testGethavingMethodThrowsAnExceptionIfMethodNotFoundOnLanguageClass()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->getHaving(['foo' => 'bar']);
  }

  /** @test */
  public function testGetorderMethodReturnsSqlStringForOrderClause()
  {
    $cfg = [
      'order' => ['id' => 'desc'],
      'available_fields' => ['id' => []],
      'fields' => ['id' => []]
    ];

    $this->mysql_mock->shouldReceive('getOrder')
      ->once()
      ->with($cfg)
      ->andReturn($result = 'ORDER BY id desc');

    $this->assertSame($result, $this->db->getOrder($cfg));
  }

  /** @test */
  public function testGetorderMethodThrowsAnExceptionIfMethodNotFoundOnLanguageClass()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->getOrder(['foo' => 'bar']);
  }

  /** @test */
  public function testGetlimitMethodReturnsSqlStringForLimitClause()
  {
    $cfg = ['limit' => 12, 'start' => 0];

    $this->mysql_mock->shouldReceive('getLimit')
      ->once()
      ->with($cfg)
      ->andReturn($result = 'LIMIT 0, 12');

    $this->assertSame($result, $this->db->getLimit($cfg));
  }

  /** @test */
  public function testGetlimitMethodThrowsAnExceptionIfMethodNotFoundOnLanguageClass()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->getLimit(['foo' => 'bar']);
  }

  /** @test */
  public function testGetcreateMethodReturnSqlStringForTableCreation()
  {
    $this->mysql_mock->shouldReceive('getCreate')
      ->once()
      ->with('users', null)
      ->andReturn($result = 'CREATE TABLE users ...');

    $this->assertSame($result, $this->db->getCreate('users'));
  }

  /** @test */
  public function testGetcreateMethodThrowsAnExceptionIfMethodNotFoundOnLanguageClass()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->getCreate('foo');
  }

  /** @test */
  public function testGetcreatetableMethodReturnSqlStringForTableCreation()
  {
    $this->mysql_mock->shouldReceive('getCreateTable')
      ->once()
      ->with('users', null)
      ->andReturn($result = 'CREATE TABLE users ...');

    $this->assertSame($result, $this->db->getCreateTable('users'));
  }

  /** @test */
  public function testGetcreatetableMethodThrowsAnExceptionIfMethodNotFoundOnLanguageClass()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->getCreateTable('foo');
  }

  /** @test */
  public function testGetcreatekeysMethodReturnsSqlStringForCreatingKeys()
  {
    $this->mysql_mock->shouldReceive('getCreateKeys')
      ->once()
      ->with('users', null)
      ->andReturn($result = 'ALTER TABLE users ADD UNIQUE KEY id');

    $this->assertSame($result, $this->db->getCreateKeys('users'));
  }

  /** @test */
  public function testGetcreatekeysMethodThrowsAnExceptionIfMethodNotFoundOnLanguageClass()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->getCreateKeys('foo');
  }

  /** @test */
  public function testGetcreateconstraintsMethodReturnsSqlStringForCreatingConstraints()
  {
    $this->mysql_mock->shouldReceive('getCreateConstraints')
      ->once()
      ->with('users', null)
      ->andReturn($result = 'ALTER TABLE users ADD CONSTRAINT ...');

    $this->assertSame($result, $this->db->getCreateConstraints('users'));
  }

  /** @test */
  public function testGetcreateconstraintsMethodThrowsAnExceptionIfMethodNotFoundOnLanguageClass()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->getCreateConstraints('foo');
  }

  /** @test */
  public function testCreateindexMethodCreatesIndexForGivenTableAndColumn()
  {
    $this->mysql_mock->shouldReceive('createIndex')
      ->once()
      ->with('users', 'id', false, null)
      ->andReturnTrue();

    $this->assertTrue(
      $this->db->createIndex('users', 'id')
    );
  }

  /** @test */
  public function testCreateindexMethodThrowsAnExceptionIfMethodNotFoundOnLanguageClass()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->createIndex('users', 'id');
  }

  /** @test */
  public function testDeleteindexMethodDeletesIndexForGivenTableAndColumn()
  {
    $this->mysql_mock->shouldReceive('deleteIndex')
      ->once()
      ->with('users', 'id')
      ->andReturnTrue();

    $this->assertTrue(
      $this->db->deleteIndex('users', 'id')
    );
  }

  /** @test */
  public function testDeleteindexMethodThrowsAnExceptionIfMethodNotFoundOnLanguageClass()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->deleteIndex('users', 'id');
  }

  /** @test */
  public function testGetaltertableMethodReturnsSqlStringForAlterStatement()
  {
    $this->mysql_mock->shouldReceive('getAlterTable')
      ->with('user', [])
      ->once()
      ->andReturn($result = 'ALTER TABLE ...');

    $this->assertSame($result, $this->db->getAlterTable('user', []));
  }

  /** @test */
  public function testGetaltertableMethodThrowsAnExceptionIfMethodNotFoundOnLanguageClass()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->getAlterTable('users', []);
  }

  /** @test */
  public function testGetaltercolumnMethodReturnsSqlStringForAlterStatement()
  {
    $this->mysql_mock->shouldReceive('getAlterColumn')
      ->with('user', [])
      ->once()
      ->andReturn($result = 'ALTER TABLE ...');

    $this->assertSame($result, $this->db->getAlterColumn('user', []));
  }

  /** @test */
  public function testGetaltercolumnMethodThrowsAnExceptionIfMethodNotFoundOnLanguageClass()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->getAlterColumn('users', []);
  }

  /** @test */
  public function testGetalterkeyMethodReturnsSqlStringForAlterStatement()
  {
    $this->mysql_mock->shouldReceive('getAlterKey')
      ->with('user', [])
      ->once()
      ->andReturn($result = 'ALTER TABLE ...');

    $this->assertSame($result, $this->db->getAlterKey('user', []));
  }

  /** @test */
  public function testGetalterkeyMethodThrowsAnExceptionIfMethodNotFoundOnLanguageClass()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->getAlterKey('users', []);
  }

  /** @test */
  public function testAlterMethodAltersTheGivenTable()
  {
    $this->mysql_mock->shouldReceive('alter')
      ->once()
      ->with('users', [])
      ->andReturn(1);

    $this->assertSame(1, $this->db->alter('users', []));
  }

  /** @test */
  public function testCreateuserMethodCreatesADbUser()
  {
    $this->mysql_mock->shouldReceive('createUser')
      ->once()
      ->with('foo', '12345', null)
      ->andReturnTrue();

    $this->assertTrue($this->db->createUser('foo', '12345'));
  }

  /** @test */
  public function testCreateuserMethodThrowsAnExceptionIfMethodNotFoundOnLanguageClass()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->createUser('foo', '12345');
  }

  /** @test */
  public function testDeleteuserMethodDeletesADbUser()
  {
    $this->mysql_mock->shouldReceive('deleteUser')
      ->once()
      ->with('foo')
      ->andReturnTrue();

    $this->assertTrue($this->db->deleteUser('foo'));
  }

  /** @test */
  public function testDeleteuserMethodThrowsAnExceptionIfMethodNotFoundOnLanguageClass()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->deleteUser('foo');
  }

  /** @test */
  public function testGetusersMethodReturnsAnArrayOfPrivilegesForTheGivenUserOfAllUsers()
  {
    $this->mysql_mock->shouldReceive('getUsers')
      ->with('john', '')
      ->once()
      ->andReturn($result = [
        "GRANT USAGE ON *.* TO 'john'@''",
        "GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER ON `db_example`.* TO 'john'@''"
      ]);

    $this->assertSame($result, $this->db->getUsers('john'));
  }

  /** @test */
  public function testGetusersMethodThrowsAnExceptionIfMethodNotFoundOnLanguageClass()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->getUsers('john');
  }

  /** @test */
  public function testDbsizeMethodReturnsTheSizeOfTheDatabase()
  {
    $this->mysql_mock->shouldReceive('dbSize')
      ->once()
      ->with('bbn_test', '')
      ->andReturn(123);

    $this->assertSame(123, $this->db->dbSize('bbn_test'));
  }

  /** @test */
  public function testTablesizeMethodReturnsTheSizeOfTheGivenTable()
  {
    $this->mysql_mock->shouldReceive('tableSize')
      ->once()
      ->with('users', '')
      ->andReturn(123);

    $this->assertSame(123, $this->db->tableSize('users'));
  }

  /** @test */
  public function testStatusMethodReturnsTheStatusOfATable()
  {
    $this->mysql_mock->shouldReceive('status')
      ->once()
      ->with('users', '')
      ->andReturn($result = [
        'Name' => 'users',
        'Engine' => 'innoDb',
        'Version' => '10',
        'Data_length' => '1234'
      ]);

    $this->assertSame($result, $this->db->status('users'));
  }

  
  /** @test */
  public function testGetuidMethodReturnsAUid()
  {
    $this->mysql_mock->shouldReceive('getUid')
      ->once()
      ->withNoArgs()
      ->andReturn($result = '3c761f3eee4111ebb9451b05c9e00886');

    $this->assertSame($result, $this->db->getUid());
  }

  /** @test */
  public function testGetrowMethodReturnsTheFirstRowResultingFromTheQueryAsAnArrayIndexedWithFieldsName()
  {
    $this->mysql_mock->shouldReceive('getRow')
      ->once()
      ->with($query = 'SELECT id, name FROM table_users WHERE id > ?', 2)
      ->andReturn($result = ['id' => 3, 'name' => 'john']);

    $this->assertSame($result, $this->db->getRow($query, 2));
  }

  /** @test */
  public function testGetrowsMethodReturnsAnArrayOfIndexedArraysForEveryRowResultedFromTheQuery()
  {
    $this->mysql_mock->shouldReceive('getRows')
      ->once()
      ->with($query = 'SELECT id, name FROM table_users WHERE id > ?', 2)
      ->andReturn($result = [
        ['id' => 3, 'name' => 'john'],
        ['id' => 4, 'name' => 'smith'],
      ]);

    $this->assertSame($result, $this->db->getRows($query, 2));
  }

  /** @test */
  public function testGetirowMethodReturnsARowAsANumericIndexedArray()
  {
    $this->mysql_mock->shouldReceive('getIrow')
      ->once()
      ->with($query = 'SELECT id, name FROM table_users WHERE id > ?', 2)
      ->andReturn($result = [3, 'john']);

    $this->assertSame($result, $this->db->getIrow($query, 2));
  }

  /** @test */
  public function testGetirowsMethodReturnsAnArrayOfNumericIndexedRows()
  {
    $this->mysql_mock->shouldReceive('getIrows')
      ->once()
      ->with($query = 'SELECT id, name FROM table_users WHERE id > ?', 2)
      ->andReturn($result = [[3, 'john'], [4, 'smith']]);

    $this->assertSame($result, $this->db->getIrows($query, 2));
  }

  /** @test */
  public function testGetbycolumnsMethodReturnsAnArrayIndexedOnTheSearchedFieldInWhichThereAreAllTheValuesOfTheColumn()
  {
    $this->mysql_mock->shouldReceive('getByColumns')
      ->once()
      ->with($query = 'SELECT id, name FROM table_users WHERE id > ?', 2)
      ->andReturn($result = [
        'name' => [
          'john', 'smith'
        ],
        'id' => [
          '12', '13'
        ]
      ]);

    $this->assertSame($result, $this->db->getByColumns($query, 2));
  }

  /** @test */
  public function testGetobjMethodReturnsTheFirstRowResultingFromAQueryAsAnObject()
  {
    $this->mysql_mock->shouldReceive('getObject')
      ->once()
      ->with($query = 'SELECT id, name FROM users WHERE id > ?', 2)
      ->andReturn($result = (object)[
        'id' => '3',
        'name' => 'john'
      ]);

    $this->assertSame($result, $this->db->getObj($query, 2));
  }

  /** @test */
  public function testGetobjectMethodReturnsTheFirstRowResultingFromAQueryAsAnObject()
  {
    $this->mysql_mock->shouldReceive('getObject')
      ->once()
      ->with($query = 'SELECT id, name FROM users WHERE id > ?', 2)
      ->andReturn($result = (object)[
        'id' => '3',
        'name' => 'john'
      ]);

    $this->assertSame($result, $this->db->getObject($query, 2));
  }

  /** @test */
  public function testGetobjectsMethodReturnsAnArrayOfObjectsResultingFromAQuery()
  {
    $this->mysql_mock->shouldReceive('getObjects')
      ->once()
      ->with($query = 'SELECT id, name FROM users WHERE id > ?', 2)
      ->andReturn($result = [
        (object)['id' => '3', 'name' => 'john'],
        (object)['id' => '4', 'name' => 'smith'],
      ]);

    $this->assertSame($result, $this->db->getObjects($query, 2));
  }

  /** @test */
  public function testCreatedatabaseMethodCreatedADatabase()
  {
    $this->mysql_mock->shouldReceive('createDatabase')
      ->with('bbn_test_2', 'utf8mb4')
      ->once()
      ->andReturnTrue();

    $this->assertTrue($this->db->createDatabase('bbn_test_2', 'utf8mb4'));
  }

  /** @test */
  public function testDropdatabaseMethodDropsTheGivenDatabase()
  {
    $this->mysql_mock->shouldReceive('dropDatabase')
      ->with('bbn_test_2')
      ->once()
      ->andReturnTrue();

    $this->assertTrue($this->db->dropDatabase('bbn_test_2'));
  }

  /** @test */
  public function testEnablelastMethodSetsLastEnabledToTrue()
  {
    $this->mysql_mock->shouldReceive('enableLast')
      ->withNoArgs()
      ->once();

    $this->db->enableLast();

    $this->assertTrue(true);
  }

  /** @test */
  public function testEnablelastMethodDoesNotForwardTheCallToLanguageIfMethodDoesNotExist()
  {
    $this->mysql_mock->shouldNotReceive('enableLast');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->enableLast();

    $this->assertTrue(true);
  }

  /** @test */
  public function testDisablelastMethodSetsLastEnabledToFalse()
  {
    $this->mysql_mock->shouldReceive('disableLast')
      ->withNoArgs()
      ->once();

    $this->db->disableLast();

    $this->assertTrue(true);
  }

  public function testDisablelastMethodDoesNotForwardTheCallToLanguageIfMethodDoesNotExist()
  {
    $this->mysql_mock->shouldNotReceive('disableLast');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->disableLast();

    $this->assertTrue(true);
  }

  /** @test */
  public function testGetreallastparamsMethodReturnsLastRealParams()
  {
    $this->mysql_mock->shouldReceive('getRealLastParams')
      ->once()
      ->withNoArgs()
      ->andReturn($result = ['foo' => 'bar']);

    $this->assertSame($result, $this->db->getRealLastParams());
  }

  /** @test */
  public function testGetreallastparamsMethodThrowsAnExceptionIfMethodDoesNotExistInLanguageObject()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });
    $this->db->getRealLastParams();
  }

  /** @test */
  public function testReallastMethodReturnsLastQuery()
  {
    $this->mysql_mock->shouldReceive('realLast')
      ->once()
      ->withNoArgs()
      ->andReturn($result = 'SELECT * FROM users');

    $this->assertSame($result, $this->db->realLast());
  }

  /** @test */
  public function testReallastMethodThrowsAnExceptionIfMethodDoesNotExistInLanguageObject()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->realLast();
  }

  /** @test */
  public function testGetlastparamsMethodReturnsLastParams()
  {
    $this->mysql_mock->shouldReceive('getLastParams')
      ->once()
      ->withNoArgs()
      ->andReturn($result = ['foo' => 'bar']);

    $this->assertSame($result, $this->db->getLastParams());
  }

  /** @test */
  public function testGetlastparamsMethodThrowsAnExceptionIfMethodDoesNotExistInLanguageObject()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->getLastParams();
  }

  /** @test */
  public function testGetlastvaluesMethodReturnsLastParams()
  {
    $this->mysql_mock->shouldReceive('getLastValues')
      ->once()
      ->withNoArgs()
      ->andReturn($result = ['foo' => 'bar']);

    $this->assertSame($result, $this->db->getLastValues());
  }

  /** @test */
  public function testGetlastvaluesMethodThrowsAnExceptionIfMethodDoesNotExistInLanguageObject()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->getLastValues();
  }

  /** @test */
  public function testGetqueryvaluesMethodReturnsQueryValuesForTheGivenArray()
  {
    $this->mysql_mock->shouldReceive('getQueryValues')
      ->once()
      ->with([])
      ->andReturn($result = ['foo' => 'bar']);

    $this->assertSame($result, $this->db->getQueryValues([]));
  }

  /** @test */
  public function testGetqueryvaluesMethodThrowsAnExceptionIfMethodDoesNotExistInLanguageObject()
  {
    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Method not found on the language class!');

    $this->setNonPublicPropertyValue('language', new class {
    });

    $this->db->getQueryValues([]);
  }

  /** @test */
  public function testGetlastcfgMethodReturnsTheLastConfigForTheConnection()
  {
    $this->mysql_mock->shouldReceive('getLastCfg')
      ->once()
      ->withNoArgs()
      ->andReturn(['a' => 'b']);

    $this->assertSame(['a' => 'b'], $this->db->getLastCfg());
  }

  /** @test */
  public function testGetconnectionMethodReturnsConnectionConfiguration()
  {
    $this->mysql_mock->shouldReceive('getConnection')
      ->once()
      ->with(['a' => 'b'])
      ->andReturn(['c' => 'd']);

    $this->assertSame(['c' => 'd'], $this->db->getConnectionParams(['a' => 'b']));
  }

  /** @test */
  public function testRenametableMethodRenameTheGivenTableToTheGivenNewName()
  {
    $this->mysql_mock->shouldReceive('renameTable')
      ->once()
      ->with('table_name', 'new_table_name')
      ->andReturnTrue();

    $this->assertTrue($this->db->renameTable('table_name', 'new_table_name'));
  }

  /** @test */
  public function testGettablecommentMethodReturnsTheCommentForTheGivenTable()
  {
    $this->mysql_mock->shouldReceive('getTableComment')
      ->once()
      ->with('table_name')
      ->andReturn('table_comment');

    $this->assertSame('table_comment', $this->db->getTableComment('table_name'));
  }
}