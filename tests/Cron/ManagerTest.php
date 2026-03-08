<?php

namespace bbn\tests\Cron;

use bbn\Appui\Notification;
use bbn\Cron\Manager;
use bbn\Db;
use bbn\Db\Enums\Errors;
use PHPUnit\Framework\TestCase;
use bbn\tests\Files;
use bbn\tests\Reflectable;

class ManagerTest extends TestCase
{
  use Reflectable, Files;

  protected Manager $manager;

  protected $db_mock;

  protected function setUp(): void
  {
    $this->db_mock = \Mockery::mock(Db::class);
    $this->cleanTestingDir();
  }

  protected function tearDown(): void
  {
    \Mockery::close();
    $this->cleanTestingDir();
  }

  public function getInstance()
  {
    return $this->manager;
  }

  protected function init()
  {
    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->manager = new Manager($this->db_mock);
  }

  protected function mockManagerClass()
  {
    $this->manager = \Mockery::mock(Manager::class)->makePartial();

    $this->setNonPublicPropertyValue('db', $this->db_mock);
    $this->setNonPublicPropertyValue('table', 'bbn_cron');
  }

  /** @test */
  public function testConstructorTest()
  {
    $this->init();

    $this->assertSame(
      \bbn\Mvc::getDataPath('appui-cron'),
      $this->getNonPublicProperty('path')
    );

    $this->assertInstanceOf(
      Db::class,
      $this->getNonPublicProperty('db')
    );

    $this->assertSame(
      'bbn_cron',
      $this->getNonPublicProperty('table')
    );
  }

  /** @test */
  public function testConstructorTestWhenDbCheckMethodReturnsFalse()
  {
    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->manager = new Manager($this->db_mock);

    $this->assertNull(
      $this->getNonPublicProperty('path')
    );

    $this->assertNull(
      $this->getNonPublicProperty('db')
    );

    $this->assertNull(
      $this->getNonPublicProperty('table')
    );
  }

  /** @test */
  public function testCheckMethodReturnsTrueWhenDbCheckMethodReturnsTrue()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->assertTrue($this->manager->check());
  }

  /** @test */
  public function testCheckMethodReturnsFalseWhenDbCheckMethodReturnsFalse()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertFalse($this->manager->check());
  }

  /** @test */
  public function testCheckMethodReturnsFalseWhenDbInstanceIsNotSet()
  {
    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->manager = new Manager($this->db_mock);

    $this->assertFalse($this->manager->check());
  }

  /** @test */
  public function testGetcronMethodReturnsFullRowAsAnIndexedArrayForTheGivenCronId()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        $this->getNonPublicProperty('table'),
        [],
        ['id' => '12345']
      )
      ->andReturn([
        'id' => '12345', 'cfg' => json_encode(['a' => 'b'])
      ]);

    $this->assertSame(
      ['id' => '12345', 'cfg' =>['a' => 'b']],
      $this->manager->getCron('12345')
    );
  }

  /** @test */
  public function testGetcronMethodReturnsNullWhenTheGivenIdDoesNotExist()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        $this->getNonPublicProperty('table'),
        [],
        ['id' => '12345']
      )
      ->andReturnNull();

    $this->assertNull(
      $this->manager->getCron('12345')
    );
  }

  /** @test */
  public function testGetcronMethodReturnsNullWhenCheckMethodReturnsFalse()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->manager->getCron('12345')
    );
  }

  /** @test */
  public function testIstimeoutMethodReturnsTrueOfTheGivenCronIdIsTimedOut()
  {
    $this->manager = \Mockery::mock(Manager::class)->makePartial();

    $this->manager->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->manager->shouldReceive('getCron')
      ->once()
      ->with('12345')
      ->andReturn($cron = [
        'id' => '12345',
        'cfg' => ['timeout' => 20000]
      ]);

    $this->manager->shouldReceive('getLogPath')
      ->once()
      ->with($cron)
      ->andReturn(BBN_APP_PATH . BBN_DATA_PATH . 'path/to/log/12345');

   $this->createDir($log = 'path/to/log');
   $this->createFile('12345', '444|' . time(), $log);

   $this->assertFalse(
     $this->manager->isTimeout('12345')
   );
  }

  /** @test */
  public function testIstimeoutMethodReturnsFalseIdTheGivenCronIdIsNotTimedOut()
  {
    $this->manager = \Mockery::mock(Manager::class)->makePartial();

    $this->manager->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->manager->shouldReceive('getCron')
      ->once()
      ->with('12345')
      ->andReturn($cron = [
        'id' => '12345',
        'cfg' => ['timeout' => 20000]
      ]);

    $this->manager->shouldReceive('getLogPath')
      ->once()
      ->with($cron)
      ->andReturn(BBN_APP_PATH . BBN_DATA_PATH . 'path/to/log/12345');

    $this->createDir($log = 'path/to/log');
    $this->createFile('12345', '444|' . strtotime('-12 HOUR'), $log);

    $this->assertTrue(
      $this->manager->isTimeout('12345')
    );
  }

  /** @test */
  public function testIstimeoutMethodReturnsNullWhenTheLogFileCannotBeFound()
  {
    $this->manager = \Mockery::mock(Manager::class)->makePartial();

    $this->manager->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->manager->shouldReceive('getCron')
      ->once()
      ->with('12345')
      ->andReturn($cron = [
        'id' => '12345',
        'cfg' => ['timeout' => 20000]
      ]);

    $this->manager->shouldReceive('getLogPath')
      ->once()
      ->with($cron)
      ->andReturn(BBN_APP_PATH . BBN_DATA_PATH . 'path/to/log/12345');

    $this->createDir($log = 'path/to/log');
    $this->createFile('another_file', '444|' . time(), $log);

    $this->assertFalse(
      $this->manager->isTimeout('12345')
    );
  }

  /** @test */
  public function testIstimeoutMethodReturnsFalseWhenFailsToGetLogPath()
  {
    $this->manager = \Mockery::mock(Manager::class)->makePartial();

    $this->manager->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->manager->shouldReceive('getCron')
      ->once()
      ->with('12345')
      ->andReturn($cron = [
        'id' => '12345',
        'cfg' => ['timeout' => 20000]
      ]);

    $this->manager->shouldReceive('getLogPath')
      ->once()
      ->with($cron)
      ->andReturnNull();

    $this->assertFalse(
      $this->manager->isTimeout('12345')
    );
  }

  /** @test */
  public function testIstimeoutMethodReturnsFalseWhenFailsToGetCronFromTheGivenId()
  {
    $this->manager = \Mockery::mock(Manager::class)->makePartial();

    $this->manager->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->manager->shouldReceive('getCron')
      ->once()
      ->with('12345')
      ->andReturnNull();

    $this->assertFalse(
      $this->manager->isTimeout('12345')
    );
  }

  /** @test */
  public function testIstimeoutMethodReturnsFalseWhenCheckMethodReturnsFalse()
  {
    $this->manager = \Mockery::mock(Manager::class)->makePartial();

    $this->manager->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertFalse(
      $this->manager->isTimeout('12345')
    );
  }

  /** @test */
  public function testStartMethodUpdatesStartTimeAndRunningStatusForTheGivenCron()
  {
    $this->mockManagerClass();

    $this->manager->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->manager->shouldReceive('getCron')
      ->once()
      ->with('12345')
      ->andReturn([
        'id' => '12345',
        'cfg' => json_encode(['a' => 'b'])
      ]);

    $this->db_mock->shouldReceive('isTriggerEnabled')
      ->once()
      ->andReturnTrue();

    $this->db_mock->shouldReceive('disableTrigger')
      ->once()
      ->andReturnSelf();

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $this->getNonPublicProperty('table'),
        [
          'prev' => date('Y-m-d H:i:s'),
          'pid' => getmypid()
        ],
        [
          'id' => '12345',
          'pid' => null,
          'active' => 1
        ]
      )
      ->andReturn(1);

    $this->db_mock->shouldReceive('enableTrigger')
      ->once()
      ->andReturnSelf();

    $this->assertTrue(
      $this->manager->start('12345')
    );
  }

  /** @test */
  public function testStartMethodReturnsFalseWhenFailsToUpdateTheGivenCron()
  {
    $this->mockManagerClass();

    $this->manager->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->manager->shouldReceive('getCron')
      ->once()
      ->with('12345')
      ->andReturn([
        'id' => '12345'
      ]);

    $this->db_mock->shouldReceive('isTriggerEnabled')
      ->once()
      ->andReturnFalse();

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $this->getNonPublicProperty('table'),
        [
          'prev' => date('Y-m-d H:i:s'),
          'pid' => getmypid()
        ],
        [
          'id' => '12345',
          'pid' => null,
          'active' => 1
        ]
      )
      ->andReturn(0);

    $this->assertFalse(
      $this->manager->start('12345')
    );
  }

  /** @test */
  public function testStartMethodReturnsFalseWhenCheckMethodReturnsFalse()
  {
    $this->mockManagerClass();

    $this->manager->shouldReceive('start')
      ->once()
      ->andReturnFalse();

    $this->assertFalse(
      $this->manager->start('12345')
    );
  }

  /** @test */
  public function testFinishMethodUpdatesTheDurationAndNewFinishedStatus()
  {
    $this->mockManagerClass();

    $this->manager->shouldReceive('getCron')
      ->once()
      ->with('12345')
      ->andReturn([
        'id' => '12345',
        'cfg' => ['frequency' => 'd2'],
        'next' =>  $next = date('Y-m-d H:i:s')
      ]);

    $this->manager->shouldReceive('getNextDate')
      ->once()
      ->with('d2', strtotime($next))
      ->andReturn($next_date = date('Y-m-d H:i:s', strtotime('+2 DAYS')));

    $this->db_mock->shouldReceive('getErrorMode')
      ->once()
      ->andReturn(Errors::E_STOP);

    $this->db_mock->shouldReceive('setErrorMode')
      ->once()
      ->with(Errors::E_CONTINUE)
      ->andReturnSelf();

    $this->db_mock->shouldReceive('isTriggerEnabled')
      ->once()
      ->andReturnTrue();

    $this->db_mock->shouldReceive('disableTrigger')
      ->once()
      ->andReturnSelf();

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $this->getNonPublicProperty('table'),
        [
          'next' => $next_date,
          'pid' => null,
          'active' => 1
        ],
        [
          'id' => '12345',
          'pid' => getmypid()
        ]
      )
      ->andReturn(1);

    $this->db_mock->shouldReceive('setErrorMode')
      ->once()
      ->with(Errors::E_STOP)
      ->andReturnSelf();

    $this->db_mock->shouldReceive('enableTrigger')
      ->once()
      ->andReturnSelf();

    $this->assertTrue(
      $this->manager->finish('12345')
    );
  }

  /** @test */
  public function testFinishMethodUpdatesTheDurationAndNewFinishedStatusWhenFrequencyDoesNotExistInCronCfg()
  {
    $this->mockManagerClass();

    $this->manager->shouldReceive('getCron')
      ->once()
      ->with('12345')
      ->andReturn([
        'id' => '12345',
        'cfg' => ['a' => 'b'],
        'next' => date('Y-m-d H:i:s')
      ]);

    $this->db_mock->shouldReceive('getErrorMode')
      ->once()
      ->andReturn(Errors::E_CONTINUE);

    $this->db_mock->shouldReceive('setErrorMode')
      ->once()
      ->with(Errors::E_CONTINUE)
      ->andReturnSelf();

    $this->db_mock->shouldReceive('isTriggerEnabled')
      ->once()
      ->andReturnFalse();

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $this->getNonPublicProperty('table'),
        [
          'next' => null,
          'pid' => null,
          'active' => 0
        ],
        [
          'id' => '12345',
          'pid' => getmypid()
        ]
      )
      ->andReturn(1);

    $this->assertTrue(
      $this->manager->finish('12345')
    );
  }

  /** @test */
  public function testFinishMethodUpdatesTheDurationAndNewFinishedStatusWhenTheGivenCronHasNoNextTime()
  {
    $this->mockManagerClass();

    $this->manager->shouldReceive('getCron')
      ->once()
      ->with('12345')
      ->andReturn([
        'id' => '12345',
        'cfg' => ['frequency' => 'd2'],
        'next' =>  null
      ]);

    $this->manager->shouldReceive('getNextDate')
      ->once()
      ->with('d2', time())
      ->andReturn($next_date = date('Y-m-d H:i:s', strtotime('+2 DAYS')));

    $this->db_mock->shouldReceive('getErrorMode')
      ->once()
      ->andReturn(Errors::E_CONTINUE);

    $this->db_mock->shouldReceive('setErrorMode')
      ->once()
      ->with(Errors::E_CONTINUE)
      ->andReturnSelf();

    $this->db_mock->shouldReceive('isTriggerEnabled')
      ->once()
      ->andReturnFalse();

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $this->getNonPublicProperty('table'),
        [
          'next' => $next_date,
          'pid' => null,
          'active' => 1
        ],
        [
          'id' => '12345',
          'pid' => getmypid()
        ]
      )
      ->andReturn(1);

    $this->assertTrue(
      $this->manager->finish('12345')
    );
  }

  /** @test */
  public function testFinishMethodReturnsFalseWhenFailsToUpdateTheGivenCron()
  {
    $this->mockManagerClass();

    $this->manager->shouldReceive('getCron')
      ->once()
      ->with('12345')
      ->andReturn([
        'id' => '12345',
        'cfg' => ['a' => 'b'],
        'next' => date('Y-m-d H:i:s')
      ]);

    $this->db_mock->shouldReceive('getErrorMode')
      ->once()
      ->andReturn(Errors::E_CONTINUE);

    $this->db_mock->shouldReceive('setErrorMode')
      ->once()
      ->with(Errors::E_CONTINUE)
      ->andReturnSelf();

    $this->db_mock->shouldReceive('isTriggerEnabled')
      ->once()
      ->andReturnFalse();

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $this->getNonPublicProperty('table'),
        [
          'next' => null,
          'pid' => null,
          'active' => 0
        ],
        [
          'id' => '12345',
          'pid' => getmypid()
        ]
      )
      ->andReturn(0);

    $this->assertFalse(
      $this->manager->finish('12345')
    );
  }

  /** @test */
  public function testFinishMethodReturnsFalseWhenTheGivenCronDoesNotExist()
  {
    $this->mockManagerClass();

    $this->manager->shouldReceive('getCron')
      ->once()
      ->with('12345')
      ->andReturnNull();

    $this->assertFalse(
      $this->manager->finish('12345')
    );
  }

  /** @test */
  public function testGetnextdateMethodReturnsDateForTheNextEventGivenFrequencyAndATime()
  {
    $this->init();

    $this->assertSame(
      date('Y-m-d H:i:s', strtotime('+60 Second')),
      $this->manager->getNextDate('s60')
    );

    $this->assertSame(
      date('Y-m-d H:i:s', strtotime('+60 Minute')),
      $this->manager->getNextDate('i60')
    );

    $this->assertSame(
      date('Y-m-d H:i:s', strtotime('+26 Hour')),
      $this->manager->getNextDate('H2', strtotime('+1 Day'))
    );

    $this->assertSame(
      date('Y-m-d H:i:s', strtotime('+3 Day')),
      $this->manager->getNextDate('d2', strtotime('+1 Day'))
    );

    $this->assertSame(
      date('Y-m-d H:i:s', strtotime('+1 Month')),
      $this->manager->getNextDate('M1')
    );

    $this->assertSame(
      date('Y-m-d H:i:s', strtotime('+1 Year')),
      $this->manager->getNextDate('Y1')
    );

    $this->assertSame(
      date('Y-m-d H:i:s', strtotime('+2 Hour')),
      $this->manager->getNextDate('H2', strtotime('-1 Day'))
    );

    $this->assertSame(
      date('Y-m-d H:i:s', strtotime('+1 Day')),
      $this->manager->getNextDate('d4', strtotime('-3 Day'))
    );
  }

  /** @test */
  public function testGetnextdateMethodReturnsNullWhenTheGivenFrequencyIsNotValid()
  {
    $this->init();

    $this->assertNull(
      $this->manager->getNextDate('h')
    );

    $this->assertNull(
      $this->manager->getNextDate('1')
    );

    $this->assertNull(
      $this->manager->getNextDate('z123')
    );

    $this->assertNull(
      $this->manager->getNextDate('m0')
    );
  }

  /** @test */
  public function testGetnextMethodReturnsTheWholeRowForTheNextCronToBeExecutedFromNowIfThereIsAnyFromTheGivenUid()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with([
        'table' => $this->getNonPublicProperty('table'),
        'fields' => [],
        'where' => [
          'conditions' => [[
            'field' => 'next',
            'operator' => '<',
            'exp' => 'NOW()'
          ], [
            'field' => 'next',
            'operator' => 'isnotnull'
          ], [
            'field' => 'active',
            'value' => 1
          ], [
            'field' => 'id',
            'value' => $id_cron = '634a2c70bcac11eba47652540000cfaa'
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
      ->andReturn([
        'id' => $id_cron,
        'cfg' => json_encode(['a' => 'b'])
      ]);

    $this->assertSame(
      ['id' => $id_cron, 'cfg' => ['a' => 'b']],
      $this->manager->getNext($id_cron)
    );
  }

  /** @test */
  public function testGetnextMethodReturnsTheWholeRowForTheNextCronToBeExecutedFromNowIfThereIsAnyFromWhenNoIdProvided()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with([
        'table' => $this->getNonPublicProperty('table'),
        'fields' => [],
        'where' => [
          'conditions' => [[
            'field' => 'next',
            'operator' => '<',
            'exp' => 'NOW()'
          ], [
            'field' => 'next',
            'operator' => 'isnotnull'
          ], [
            'field' => 'active',
            'value' => 1
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
      ->andReturn([
        'id' => '12345',
        'cfg' => json_encode(['a' => 'b'])
      ]);

    $this->assertSame(
      ['id' => '12345', 'cfg' => ['a' => 'b']],
      $this->manager->getNext()
    );
  }

  /** @test */
  public function testGetnextMethodReturnsNullWhenNoResultsFound()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->twice()
      ->andReturnTrue();

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->andReturnNull();

    $this->assertNull(
      $this->manager->getNext()
    );

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->andReturn([]);

    $this->assertNull(
      $this->manager->getNext()
    );
  }

  /** @test */
  public function testGetnextMethodReturnsNullWhenCheckMethodReturnsFalse()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->manager->getNext()
    );
  }

  /** @test */
  public function testGetrunningrowsMethodReturnsAllRowsForRunningCron()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with(
        [
          'table' => $this->getNonPublicProperty('table'),
          'fields' => [],
          'where' => [
            'conditions' => [[
              'field' => 'active',
              'value' => 1
            ], [
              'field' => 'pid',
              'operator' => 'isnotnull'
            ]]
          ],
          'order' => [[
            'field' => 'prev',
            'dir' => 'ASC'
          ]]
        ]
      )
      ->andReturn([
        ['id' => '123', 'cfg' => json_encode(['a' => 'b'])],
        ['id' => '12345', 'cfg' => json_encode(['c' => 'd'])],
        ['id' => '321', 'cfg' => null],
      ]);

    $this->assertSame(
      [
        ['id' => '123', 'a' => 'b'],
        ['id' => '12345', 'c' => 'd'],
        ['id' => '321'],
      ],
      $this->manager->getRunningRows()
    );
  }

  /** @test */
  public function testGetrunningrowsMethodReturnsNullWhenCheckMethodReturnsFalse()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->manager->getRunningRows()
    );
  }

  /** @test */
  public function testGetnextrowsMethodReturnRowsForScheduledCronThatShouldRun()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->twice()
      ->andReturnTrue();

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with(
        [
          'table' => $this->getNonPublicProperty('table'),
          'fields' => [],
          'where' => [
            'conditions' => [[
              'field' => 'active',
              'value' => 1
            ], [
              'field' => 'pid',
              'operator' => 'isnull'
            ], [
              'field' => 'next',
              'operator' => 'isnotnull'
            ], [
              'field' => 'next',
              'operator' => '<',
              'exp' => 'NOW()'
            ]]
          ],
          'order' => [[
            'field' => 'priority',
            'dir' => 'ASC'
          ], [
            'field' => 'next',
            'dir' => 'ASC'
          ]],
          'limit' => 10
        ]
      )
      ->andReturn([
        ['id' => '123', 'cfg' => json_encode(['a' => 'b'])],
        ['id' => '321', 'cfg' => null],
      ]);

    $this->assertSame(
      [
        ['id' => '123', 'a' => 'b'],
        ['id' => '321'],
      ],
      $this->manager->getNextRows()
    );

    // Another test with different given parameters
    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with(
        [
          'table' => $this->getNonPublicProperty('table'),
          'fields' => [],
          'where' => [
            'conditions' => [[
              'field' => 'active',
              'value' => 1
            ], [
              'field' => 'pid',
              'operator' => 'isnull'
            ], [
              'field' => 'next',
              'operator' => 'isnotnull'
            ], [
              'field' => 'next',
              'operator' => '<',
              'exp' => 'DATE_ADD(NOW(), INTERVAL 4 SECOND)'
            ]]
          ],
          'order' => [[
            'field' => 'priority',
            'dir' => 'ASC'
          ], [
            'field' => 'next',
            'dir' => 'ASC'
          ]],
          'limit' => 1000
        ]
      )
      ->andReturn([
        ['id' => '123', 'cfg' => json_encode(['a' => 'b'])],
        ['id' => '321', 'cfg' => null],
      ]);

    $this->assertSame(
      [
        ['id' => '123', 'a' => 'b'],
        ['id' => '321'],
      ],
      $this->manager->getNextRows(0, 4)
    );
  }

  /** @test */
  public function testGetnextrowsMethodReturnsNullWhenCheckMethodReturnsFalse()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->manager->getNextRows()
    );
  }

  /** @test */
  public function testGetfailedMethodReturnsAllRowsForFailedCron()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with(
        [
          'table' => $this->getNonPublicProperty('table'),
          'fields' => [],
          'where' => [
            'conditions' => [[
              'field' => 'active',
              'value' => 1
            ], [
              'field' => 'pid',
              'operator' => 'isnotnull'
            ], [
              'field' => 'next',
              'operator' => 'isnotnull'
            ], [
              'field' => 'NOW()',
              'operator' => '>',
              'exp' => "DATE_ADD(prev, INTERVAL cfg->'$.timeout' SECOND)"
            ]]
          ],
          'order' => [[
            'field' => 'priority',
            'dir' => 'ASC'
          ], [
            'field' => 'next',
            'dir' => 'ASC'
          ]]
        ]
      )
      ->andReturn([
        ['id' => '123', 'cfg' => json_encode(['a' => 'b'])],
        ['id' => '123', 'cfg' => null],
      ]);

    $this->assertSame(
      [
        ['id' => '123', 'a' => 'b'],
        ['id' => '123'],
      ],
      $this->manager->getFailed()
    );
  }

  /** @test */
  public function testGetfailedMethodReturnsNullWhenCheckMethodReturnsFalse()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->manager->getFailed()
    );
  }

  /** @test */
  public function testNotifyfailedMethodInsertsIntoNotificationTableAndUpdateCronNotificationFieldWhenFailed()
  {
    $this->mockManagerClass();

    $this->manager->shouldReceive('getFailed')
      ->once()
      ->withNoArgs()
      ->andReturn([
        ['id' => '123', 'notification' => time(), 'file' => 'file_1'],
        ['id' => '321', 'notification' => null, 'file' => 'file_2'],
      ]);

    $notification_mock = \Mockery::mock(Notification::class);

    $notification_mock->shouldReceive('insertByOption')
      ->once()
      ->with(
        'CRON task failed',
        'The task file_2 failed.',
        'cron/task_failed',
        true
      )
      ->andReturnTrue();

    $this->db_mock->shouldReceive('update')
      ->once()
      ->andReturn(1);

    $this->manager->notifyFailed($notification_mock);

    $this->assertTrue(true);
  }

  /** @test */
  public function testNotifyfailedMethodDoesNotTheNotificationFieldOfFailsToInsertToNotificationTable()
  {
    $this->mockManagerClass();

    $this->manager->shouldReceive('getFailed')
      ->once()
      ->withNoArgs()
      ->andReturn([
        ['id' => '321', 'notification' => null, 'file' => 'file_2'],
      ]);

    $notification_mock = \Mockery::mock(Notification::class);

    $notification_mock->shouldReceive('insertByOption')
      ->once()
      ->with(
        'CRON task failed',
        'The task file_2 failed.',
        'cron/task_failed',
        true
      )
      ->andReturnFalse();

    $this->db_mock->shouldNotReceive('update');

    $this->manager->notifyFailed($notification_mock);

    $this->assertTrue(true);
  }

  /** @test */
  public function testIsrunningMethodChecksWhetherTheGivenCronIdIsRunningOrNot()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->twice()
      ->andReturnTrue();

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $this->getNonPublicProperty('table'),
        [['id' => '123'], ['pid', 'isnotnull']]
      )
      ->andReturn(1);

    $this->assertTrue(
      $this->manager->isRunning('123')
    );

    // Another test
    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $this->getNonPublicProperty('table'),
        [['id' => '123'], ['pid', 'isnotnull']]
      )
      ->andReturn(0);

    $this->assertFalse(
      $this->manager->isRunning('123')
    );
  }

  /** @test */
  public function testIsrunningMethodReturnsFalseWhenCheckMethodReturnsFalse()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertFalse(
      $this->manager->isRunning('123')
    );
  }

  /** @test */
  public function testActivateMethodSetsTheActiveFieldToOneForTheGivenCronId()
  {
    $this->init();

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $this->getNonPublicProperty('table'),
        ['active' => 1],
        ['id' => '123']
      )
      ->andReturn(1);

    $this->assertSame(1, $this->manager->activate('123'));
  }

  /** @test */
  public function testDeactivateMethodSetsTheActiveFieldToZeroForTheGivenCronId()
  {
    $this->init();

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $this->getNonPublicProperty('table'),
        ['active' => 0],
        ['id' => '123']
      )
      ->andReturn(1);

    $this->assertSame(1, $this->manager->deactivate('123'));
  }

  /** @test */
  public function testSetpidMethodSetsThePidFieldToTheGivenValueForTheGivenCronId()
  {
    $this->init();

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $this->getNonPublicProperty('table'),
        ['pid' => 'pid_value'],
        ['id' => '123']
      )
      ->andReturn(1);

      $this->assertSame(1, $this->manager->setPid('123', 'pid_value'));
  }

  /** @test */
  public function testUnsetpidMethodSetsThePidAndNotificationFieldsToNullForTheGivenCronId()
  {
    $this->init();

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $this->getNonPublicProperty('table'),
        ['pid' => null, 'notification' => null],
        ['id' => '123']
      )
      ->andReturn(1);

    $this->assertSame(1, $this->manager->unsetPid('123'));
  }

  /** @test */
  public function testAddMethodAddsANewRowToCronTablesFromGivenData()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->with(
        $this->getNonPublicProperty('table'), $expected = [
          'file' => 'file_value',
          'description' => '',
          'next' => date('Y-m-d H:i:s'),
          'priority' => 'priority_value',
          'cfg' => json_encode([
            'frequency' => 'frequency_value',
            'timeout' => 'timeout_value'
          ]),
          'active' => 1
        ]
      )
      ->andReturn(1);

    $this->db_mock->shouldReceive('lastId')
      ->once()
      ->andReturn('123');

    $cfg = [
      'file' => 'file_value',
      'priority' => 'priority_value',
      'frequency' => 'frequency_value',
      'timeout' => 'timeout_value'
    ];

    $this->assertSame(
      array_merge($expected, ['id' => '123']),
      $this->manager->add($cfg)
    );
  }

  /** @test */
  public function testAddMethodReturnsNullWhenFailsToInsertANewCron()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->with(
        $this->getNonPublicProperty('table'), [
        'file' => 'file_value',
        'description' => 'description_value',
        'next' => '2021-09-26 00:00:00',
        'priority' => 'priority_value',
        'cfg' => json_encode([
          'frequency' => 'frequency_value',
          'timeout' => 'timeout_value'
        ]),
        'active' => 1
      ]
      )
      ->andReturnNull();

    $this->db_mock->shouldNotReceive('lastId');

    $cfg = [
      'file' => 'file_value',
      'priority' => 'priority_value',
      'frequency' => 'frequency_value',
      'timeout' => 'timeout_value',
      'description' => 'description_value',
      'next' => '2021-09-26 00:00:00'
    ];

    $this->assertNull(
      $this->manager->add($cfg)
    );
  }

  /** @test */
  public function testAddMethodReturnsNullTheGivenDataMissesSomeParameters()
  {
    $this->init();

    $this->db_mock->shouldNotReceive('check')
      ->times(4)
      ->andReturnTrue();

    $this->assertNull(
      $this->manager->add([
        'file' => 'file_value',
        'priority' => 'priority_value',
        'frequency' => 'frequency_value'
      ])
    );

    $this->assertNull(
      $this->manager->add([
        'file' => 'file_value',
        'priority' => 'priority_value'
      ])
    );

    $this->assertNull(
      $this->manager->add([
        'file' => '',
        'priority' => '',
        'frequency' => '',
        'timeout' => '',
      ])
    );

    $this->assertNull(
      $this->manager->add([])
    );
  }

  /** @test */
  public function testAddMethodRetursNullWhenCheckMethodReturnsFalse()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->manager->add([
        'file' => 'file_value',
        'priority' => 'priority_value',
        'frequency' => 'frequency_value',
        'timeout' => 'timeout_value'
      ])
    );
  }

  /** @test */
  public function testEditMethodUpdatesTheGivenCronIdWithTheGivenValues()
  {
    $this->mockManagerClass();

    $this->manager->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->manager->shouldReceive('getCron')
      ->once()
      ->with('123')
      ->andReturn([
        'file' => 'old_file',
        'description' => 'old_description',
        'next' => 'old_next',
        'priority' => 'old_priority',
        'frequency' => 'old_frequency',
        'timeout' => 'old_timeout'
      ]);

    $cfg = [
      'file' => 'new_file',
      'description' => 'new_description',
      'next' => 'new_next',
      'priority' => 'new_priority',
      'frequency' => 'new_frequency',
      'timeout' => 'new_timeout'
    ];

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $this->getNonPublicProperty('table'),
        $expected = [
          'file' => 'new_file',
          'description' => 'new_description',
          'next' => 'new_next',
          'priority' => 'new_priority',
          'cfg' => json_encode([
            'frequency' => 'new_frequency',
            'timeout' => 'new_timeout'
          ]),
          'active' => 1
      ],
      ['id' => '123'])
      ->andReturn(1);

    $this->assertSame(
      array_merge($expected, ['id' => '123']),
      $this->manager->edit('123', $cfg)
    );
  }

  /** @test */
  public function testEditMethodReturnsNullWhenFailsToUpdateTheGivenCronId()
  {
    $this->mockManagerClass();

    $this->manager->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->manager->shouldReceive('getCron')
      ->with('123')
      ->once()
      ->andReturn([
        'file' => 'old_file',
        'description' => 'old_description',
        'next' => 'old_next',
        'priority' => 'old_priority',
        'frequency' => 'old_frequency',
        'timeout' => 'old_timeout'
      ]);

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $this->getNonPublicProperty('table'),
        [
          'file' => 'old_file',
          'description' => 'old_description',
          'next' => 'old_next',
          'priority' => 'old_priority',
          'cfg' => json_encode([
            'frequency' => 'old_frequency',
            'timeout' => 'old_timeout'
          ]),
          'active' => 1
        ],
        ['id' => '123']
      )
      ->andReturnNull();

    $this->assertNull(
      $this->manager->edit('123', [])
    );
  }

  /** @test */
  public function testEditMethodReturnsNullWhenTheGivenIdDoesNotExist()
  {
    $this->mockManagerClass();

    $this->manager->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->manager->shouldReceive('getCron')
      ->once()
      ->with('123')
      ->andReturnNull();

    $this->assertNull(
      $this->manager->edit('123', [])
    );
  }

  /** @test */
  public function testEditMethodReturnsNullWhenCheckMethodReturnsFalse()
  {
    $this->init();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->manager->edit('123', [])
    );
  }
}