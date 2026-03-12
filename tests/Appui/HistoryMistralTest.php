<?php

declare(strict_types=1);

namespace tests\Appui;

use bbn\Appui\HistoryMistral;
use bbn\Db;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

#[AllowMockObjectsWithoutExpectations]
final class HistoryMistralTest extends TestCase
{
  private function makeHistoryMock(Db $db, array $tableCfg): HistoryMistral
  {
    /** @var HistoryMistral&MockObject $history */
    $history = $this->getMockBuilder(HistoryMistral::class)
      ->disableOriginalConstructor()
      ->onlyMethods(['getTableCfg'])
      ->getMock();

    $history->method('getTableCfg')->willReturn($tableCfg);

    $this->setPrivateProperty($history, 'db', $db);
    $this->setPrivateProperty($history, 'ok', true);
    $this->setPrivateProperty($history, 'enabled', true);
    $this->setPrivateProperty($history, 'user', '1234567890abcdef1234567890abcdef');

    $history->table = 'mydb.bbn_history';
    $history->table_uids = 'mydb.bbn_history_uids';

    return $history;
  }
  private function setPrivateProperty(object $object, string $property, mixed $value): void
  {
    $ref = new ReflectionClass($object);
    while (!$ref->hasProperty($property) && ($ref = $ref->getParentClass())) {
    }

    $prop = $ref->getProperty($property);
    $prop->setValue($object, $value);
  }

  public function testDeleteDoesNotPhysicallyDeleteButSetsActiveToZero(): void
  {
    $table = 'mydb.users';
    $uid = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    $tableCfg = [
      'primary' => 'id',
      'id' => 'table_id',
      'refs' => [],
      'unique' => [],
      'fields' => [
        'id' => ['id_option' => 'col_id'],
      ],
    ];

    $db = $this->createMock(Db::class);
    $db->method('check')->willReturn(true);
    $db->method('tfn')->willReturn($table);

    $db->expects($this->once())
      ->method('update')
      ->with(
        'mydb.bbn_history_uids',
        ['bbn_active' => 0],
        ['bbn_uid' => $uid]
      )
      ->willReturn(1);

    $history = $this->makeHistoryMock($db, $tableCfg);

    $cfg = [
      'write' => true,
      'kind' => 'DELETE',
      'moment' => 'before',
      'table' => $table,
      'tables' => [$table],
      'tables_full' => [$table],
      'filters' => ['id' => $uid],
      'join' => [],
      'values_desc' => [
        ['primary' => true],
      ],
      'values' => [$uid],
      'fields' => ['id'],
    ];

    $result = $history->trigger($cfg);

    $this->assertFalse($result['run']);
    $this->assertSame(1, $result['value']);
    $this->assertSame(1, $result['trig']);
    $this->assertCount(1, $result['history']);
    $this->assertSame('DELETE', $result['history'][0]['operation']);
    $this->assertSame('col_id', $result['history'][0]['column']);
    $this->assertSame($uid, $result['history'][0]['line']);
    $this->assertArrayHasKey('old', $result['history'][0]);
    $this->assertNull($result['history'][0]['old']);
  }

  public function testUpdateStoresPreviousValueInHistory(): void
  {
    $table = 'mydb.users';
    $uid = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    $tableCfg = [
      'primary' => 'id',
      'id' => 'table_id',
      'refs' => [],
      'unique' => [],
      'fields' => [
        'id' => ['id_option' => 'col_id'],
        'name' => ['id_option' => 'col_name'],
      ],
    ];

    $db = $this->createMock(Db::class);
    $db->method('check')->willReturn(true);
    $db->method('tfn')->willReturn($table);
    $db->method('csn')->willReturnCallback(static fn(string $col): string => $col);

    $db->expects($this->once())
      ->method('rselect')
      ->with($table, ['id', 'name'], ['id' => $uid])
      ->willReturn([
        'id' => $uid,
        'name' => 'old value',
      ]);

    $history = $this->makeHistoryMock($db, $tableCfg);

    $cfg = [
      'write' => true,
      'kind' => 'UPDATE',
      'moment' => 'before',
      'table' => $table,
      'tables' => [$table],
      'tables_full' => [$table],
      'filters' => ['id' => $uid],
      'join' => [],
      'values_desc' => [
        ['primary' => true],
        [],
      ],
      'values' => [$uid, 'new value'],
      'fields' => ['id', 'name'],
    ];

    $result = $history->trigger($cfg);

    $this->assertArrayHasKey('history', $result);
    $this->assertCount(1, $result['history']);
    $this->assertSame('UPDATE', $result['history'][0]['operation']);
    $this->assertSame('col_name', $result['history'][0]['column']);
    $this->assertSame($uid, $result['history'][0]['line']);
    $this->assertSame('old value', $result['history'][0]['old']);
  }

  public function testDeleteNullifiesNullableUniqueColumnBeforeSoftDelete(): void
  {
    $table = 'mydb.users';
    $uid = 'cccccccccccccccccccccccccccccccc';
    $email = 'deleted@example.com';

    $tableCfg = [
      'primary' => 'id',
      'id' => 'table_id',
      'refs' => [],
      'unique' => [
        [
          'name' => 'uniq_email',
          'columns' => [
            [
              'name' => 'email',
              'nullable' => true,
            ],
          ],
        ],
      ],
      'fields' => [
        'id' => ['id_option' => 'col_id'],
        'email' => [
          'id_option' => 'col_email',
          'default' => null,
        ],
      ],
    ];

    $db = $this->createMock(Db::class);
    $db->method('check')->willReturn(true);
    $db->method('tfn')->willReturn($table);

    $db->expects($this->once())
      ->method('selectOne')
      ->with($table, 'email', ['id' => $uid])
      ->willReturn($email);

    $db->expects($this->exactly(2))
      ->method('update')
      ->willReturnCallback(function (string $tbl, array $set, array $where) use ($table, $uid) {
        if (($tbl === $table) && ($set === ['email' => null]) && ($where === ['id' => $uid])) {
          return 1;
        }

        if (($tbl === 'mydb.bbn_history_uids') && ($set === ['bbn_active' => 0]) && ($where === ['bbn_uid' => $uid])) {
          return 1;
        }

        self::fail('Unexpected update call');
      });

    $history = $this->makeHistoryMock($db, $tableCfg);

    $cfg = [
      'write' => true,
      'kind' => 'DELETE',
      'moment' => 'before',
      'table' => $table,
      'tables' => [$table],
      'tables_full' => [$table],
      'filters' => ['id' => $uid],
      'join' => [],
      'values_desc' => [
        ['primary' => true],
      ],
      'values' => [$uid],
      'fields' => ['id'],
    ];

    $result = $history->trigger($cfg);

    $this->assertFalse($result['run']);
    $this->assertSame(1, $result['value']);
    $this->assertSame('UPDATE', $result['history'][0]['operation']);
    $this->assertSame('col_email', $result['history'][0]['column']);
    $this->assertSame($email, $result['history'][0]['old']);

    $this->assertSame('DELETE', $result['history'][1]['operation']);
    $this->assertSame('col_id', $result['history'][1]['column']);
    $this->assertSame($uid, $result['history'][1]['line']);
  }

  public function testInsertRestoresDeletedRowWhenUniqueKeyExistsAndIsNotNullable(): void
  {
    $table = 'mydb.users';
    $uid = 'dddddddddddddddddddddddddddddddd';
    $email = 'restored@example.com';

    $tableCfg = [
      'primary' => 'id',
      'id' => 'table_id',
      'refs' => [],
      'unique' => [
        [
          'name' => 'uniq_email',
          'columns' => [
            [
              'name' => 'email',
              'nullable' => false,
            ],
          ],
        ],
      ],
      'fields' => [
        'id' => ['id_option' => 'col_id'],
        'email' => ['id_option' => 'col_email', 'default' => null],
        'name' => ['id_option' => 'col_name', 'default' => ''],
      ],
    ];

    $db = $this->createMock(Db::class);
    $db->method('check')->willReturn(true);
    $db->method('tfn')->willReturn($table);
    $db->method('setLastInsertId')->with($uid);

    $db->expects($this->exactly(2))
      ->method('selectOne')
      ->willReturnCallback(function ($arg1, $arg2 = null, $arg3 = null) use ($uid) {
        if (is_array($arg1)) {
          return $uid;
        }

        if (($arg1 === 'mydb.bbn_history_uids') && ($arg2 === 'bbn_active') && ($arg3 === ['bbn_uid' => $uid])) {
          return 0;
        }

        self::fail('Unexpected selectOne call');
      });

    $db->expects($this->once())
      ->method('rselect')
      ->with($this->callback(function (array $cfg) use ($table, $uid) {
        return $cfg['table'] === $table
          && $cfg['fields'] === ['email', 'name']
          && $cfg['where']['conditions'][0]['field'] === 'id'
          && $cfg['where']['conditions'][0]['value'] === $uid;
      }))
      ->willReturn([
        'email' => $email,
        'name' => 'Old name',
      ]);

    $db->expects($this->exactly(2))
      ->method('update')
      ->willReturnCallback(function (string $tbl, array $set, array $where) use ($table, $uid) {
        if (($tbl === 'mydb.bbn_history_uids') && ($set === ['bbn_active' => 1]) && ($where === [['bbn_uid', '=', $uid]])) {
          return 1;
        }

        if (($tbl === $table) && ($set === ['name' => 'New name']) && ($where === ['id' => $uid])) {
          return 1;
        }

        self::fail('Unexpected update call');
      });

    $history = $this->makeHistoryMock($db, $tableCfg);

    $cfg = [
      'write' => true,
      'kind' => 'INSERT',
      'moment' => 'before',
      'table' => $table,
      'tables' => [$table],
      'tables_full' => [$table],
      'filters' => [],
      'join' => [],
      'values_desc' => [
        [],
        [],
      ],
      'values' => [$email, 'New name'],
      'fields' => ['email', 'name'],
    ];

    $result = $history->trigger($cfg);

    $this->assertFalse($result['run'] ?? true);
    $this->assertTrue($result['trig']);
    $this->assertSame(1, $result['value']);
    $this->assertCount(1, $result['history']);
    $this->assertSame('RESTORE', $result['history'][0]['operation']);
    $this->assertSame('col_id', $result['history'][0]['column']);
    $this->assertSame($uid, $result['history'][0]['line']);
  }
}
