<?php

namespace bbn\Appui\Tests;

use PHPUnit\Framework\TestCase;
use bbn\Appui\HistoryMistral;
use bbn\Db;
use bbn\Appui\Database;
use bbn\Cache;
use bbn\Models\Tts\Singleton;

/**
 * Unit tests for HistoryMistral class
 */
class HistoryMistralTest extends TestCase
{
    private $dbMock;
    private $databaseObjMock;
    private $cacheEngineMock;
    private $history;

    protected function setUp(): void
    {
        // Create mock objects
        $this->dbMock = $this->createMock(Db::class);
        $this->databaseObjMock = $this->createMock(Database::class);
        $this->cacheEngineMock = $this->createMock(Cache::class);

        // Initialize HistoryMistral with mocks
        $this->history = new HistoryMistral();
        $this->history->init($this->dbMock, [
            'admin_db' => 'test_db',
            'user' => 'test_user'
        ]);

        // Set up static properties that are normally set in init()
        HistoryMistral::$table_uids = 'test_db.bbn_history_uids';
        HistoryMistral::$table = 'test_db.bbn_history';
    }

    protected function tearDown(): void
    {
        Singleton::unsetInstance(HistoryMistral::class);
    }

    // Test initialization
    public function testInit()
    {
        $this->assertTrue($this->history->check());
        $this->assertEquals('test_db', HistoryMistral::$admin_db);
        $this->assertEquals('test_user', $this->history->getUser());
    }

    // Test getIdColumn
    public function testGetIdColumn()
    {
        $column = 'test_column';
        $table = 'test_table';

        $this->databaseObjMock->method('columnId')
            ->with($column, $table, 'test_db')
            ->willReturn('col_id_123');

        $this->history->setDatabaseObj($this->databaseObjMock);

        $result = $this->history->getIdColumn($column, $table);
        $this->assertEquals('col_id_123', $result);
    }

    // Test cache methods
    public function testCacheMethods()
    {
        $cacheKey = 'test_cache_key';
        $cacheData = ['key' => 'value'];

        $this->history->setCache($cacheKey, $cacheData);

        $this->cacheEngineMock->expects($this->once())
            ->method('set')
            ->with(
                $this->matchesRegularExpression('/^'.preg_quote(Str::encodeFilename(get_class($this->history), true)).'/'),
                $cacheData,
                3600
            );

        $result = $this->history->getCache($cacheKey);
        $this->assertEquals($cacheData, $result);

        $this->history->deleteCache($cacheKey);
    }

    // Test check method
    public function testCheck()
    {
        $this->assertTrue($this->history->check());

        // Test with invalid configuration
        $invalidHistory = new HistoryMistral();
        $this->assertFalse($invalidHistory->check());
    }

    // Test hasHistory method
    public function testHasHistory()
    {
        $hash = 'db_hash_123';
        $this->dbMock->method('getHash')->willReturn($hash);
        HistoryMistral::$dbs[] = $hash;

        $this->assertTrue($this->history->hasHistory($this->dbMock));

        // Test with different hash
        $otherDb = $this->createMock(Db::class);
        $otherDb->method('getHash')->willReturn('different_hash');
        $this->assertFalse($this->history->hasHistory($otherDb));
    }

    // Test delete method
    public function testDelete()
    {
        $uidToDelete = 'test_uid';

        $this->dbMock->expects($this->once())
            ->method('delete')
            ->with(HistoryMistral::$table_uids, ['bbn_uid' => $uidToDelete])
            ->willReturn(true);

        $result = $this->history->delete($uidToDelete);
        $this->assertTrue($result);
    }

    // Test column methods
    public function testColumnMethods()
    {
        $newColumnName = 'test_active_column';

        $this->history->setColumn($newColumnName);
        $this->assertEquals($newColumnName, $this->history->getColumn());
    }

    // Test date methods
    public function testDateMethods()
    {
        $testTimestamp = 1234567890;

        $this->history->setDate($testTimestamp);
        $this->assertEquals($testTimestamp, $this->history->getDate());

        $this->history->unsetDate();
        $this->assertNull($this->history->getDate());
    }

    // Test admin_db methods
    public function testAdminDbMethods()
    {
        $newDbName = 'another_test_db';

        $this->history->setAdminDb($newDbName);
        $this->assertEquals('another_test_db.bbn_history', HistoryMistral::$table);

        // Test with invalid name
        $this->history->setAdminDb('invalid#name');
        $this->assertEquals('another_test_db.bbn_history', HistoryMistral::$table);
    }

    // Test user methods
    public function testUserMethods()
    {
        $newUserId = 'another_user';

        $this->history->setUser($newUserId);
        $this->assertEquals($newUserId, $this->history->getUser());

        // Test with invalid user ID
        $this->history->setUser('invalid@user');
        $this->assertEquals($newUserId, $this->history->getUser());
    }

    // Test getAllHistory method
    public function testGetAllHistory()
    {
        $table = 'test_table';
        $start = 0;
        $limit = 20;

        $mockResult = ['uid1', 'uid2'];

        $this->databaseObjMock->method('tableId')
            ->with($table, 'test_db')
            ->willReturn('tab_id_123');

        $this->dbMock->expects($this->once())
            ->method('getColumnValues')
            ->with([
                'table' => HistoryMistral::$table_uids,
                'fields' => ['bbn_uid'],
                'join' => [
                    [
                        'table' => HistoryMistral::$table,
                        'on' => [
                            'conditions' => [[
                                'field' => 'bbn_uid',
                                'exp' => 'uid'
                            ]]
                        ]
                    ]
                ],
                'where' => ['bbn_table' => 'tab_id_123'],
                'order' => [[
                    'field' => 'tst',
                    'dir' => 'DESC'
                ]],
                'start' => $start,
                'limit' => $limit
            ])
            ->willReturn($mockResult);

        $this->history->setDatabaseObj($this->databaseObjMock);
        $result = $this->history->getAllHistory($table, $start, $limit);
        $this->assertEquals($mockResult, $result);
    }

    // Test getLastModifiedLines method
    public function testGetLastModifiedLines()
    {
        $table = 'test_table';
        $start = 0;
        $limit = 20;

        $mockResult = ['uid1', 'uid2'];

        $this->databaseObjMock->method('tableId')
            ->with($table)
            ->willReturn('tab_id_123');

        $this->dbMock->expects($this->once())
            ->method('getColArray')
            ->with(
                $this->matchesRegularExpression('/SELECT DISTINCT$uid$ FROM test_db\.bbn_history_uids JOIN test_db\.bbn_history ON bbn_uid = uid WHERE bbn_table = \? AND bbn_active = 1 ORDER BY tst LIMIT 0, 20/'),
                'tab_id_123'
            )
            ->willReturn($mockResult);

        $this->history->setDatabaseObj($this->databaseObjMock);
        $result = $this->history->getLastModifiedLines($table, $start, $limit);
        $this->assertEquals($mockResult, $result);
    }

    // Test getNextUpdate method
    public function testGetNextUpdate()
    {
        $table = 'test_table';
        $id = 'test_id';
        $fromWhen = 1234567890;
        $column = 'test_column';

        $mockResult = ['uid' => $id, 'col' => 'col_id', 'tst' => time(), 'val' => 'value'];

        $this->databaseObjMock->method('tableId')
            ->with($table)
            ->willReturn('tab_id_123');

        $this->dbMock->expects($this->once())
            ->method('rselect')
            ->with([
                'tables' => [HistoryMistral::$table_uids],
                'fields' => [
                    'uid',
                    'col',
                    'tst',
                    'val' => 'IFNULL(val, ref)',
                    'usr'
                ],
                'join' => [
                    [
                        'table' => HistoryMistral::$table,
                        'on' => [
                            'logic' => 'AND',
                            'conditions' => [[
                                'field' => 'bbn_uid',
                                'operator' => '=',
                                'exp' => 'uid'
                            ]]
                        ]
                    ]
                ],
                'where' => [
                    'logic' => 'AND',
                    'conditions' => [
                        ['field' => 'bbn_uid', 'operator' => '=', 'value' => $id],
                        ['field' => 'bbn_table', 'operator' => '=', 'value' => 'tab_id_123'],
                        ['field' => 'tst', 'operator' => '>', 'value' => $fromWhen]
                    ]
                ],
                'order' => [$chrono => 'ASC']
            ])
            ->willReturn($mockResult);

        $this->history->setDatabaseObj($this->databaseObjMock);
        $result = $this->history->getNextUpdate($table, $id, $fromWhen, $column);
        $this->assertEquals($mockResult, $result);
    }

    // Test getPrevUpdate method
    public function testGetPrevUpdate()
    {
        $table = 'test_table';
        $id = 'test_id';
        $fromWhen = 1234567890;
        $column = 'test_column';

        $mockResult = ['uid' => $id, 'col' => 'col_id', 'tst' => time(), 'val' => 'value'];

        $this->databaseObjMock->method('tableId')
            ->with($table)
            ->willReturn('tab_id_123');

        $this->dbMock->expects($this->once())
            ->method('rselect')
            ->with(HistoryMistral::$table, [], [
                'conditions' => [
                    ['field' => 'uid', 'value' => $id],
                    ['field' => 'col', 'value' => 'col_id'],
                    ['field' => 'opr', 'value' => 'UPDATE'],
                    ['field' => 'tst', 'operator' => '<', 'value' => $fromWhen]
                ]
            ])
            ->willReturn($mockResult);

        $this->history->setDatabaseObj($this->databaseObjMock);
        $result = $this->history->getPrevUpdate($table, $id, $fromWhen, $column);
        $this->assertEquals($mockResult, $result);
    }

    // Test getNextValue method
    public function testGetNextValue()
    {
        $table = 'test_table';
        $id = 'test_id';
        $fromWhen = 1234567890;
        $column = 'test_column';

        $mockUpdateResult = ['uid' => $id, 'col' => 'col_id', 'tst' => time(), 'val' => 'value'];

        $this->history->expects($this->once())
            ->method('getNextUpdate')
            ->with($table, $id, $fromWhen, $column)
            ->willReturn($mockUpdateResult);

        $result = $this->history->getNextValue($table, $id, $fromWhen, $column);
        $this->assertEquals('value', $result);
    }

    // Test getPrevValue method
    public function testGetPrevValue()
    {
        $table = 'test_table';
        $id = 'test_id';
        $fromWhen = 1234567890;
        $column = 'test_column';

        $mockUpdateResult = ['uid' => $id, 'col' => 'col_id', 'tst' => time(), 'val' => 'value'];

        $this->history->expects($this->once())
            ->method('getPrevUpdate')
            ->with($table, $id, $fromWhen, $column)
            ->willReturn($mockUpdateResult);

        $result = $this->history->getPrevValue($table, $id, $fromWhen, $column);
        $this->assertEquals('value', $result);
    }

    // Test getRowBack method
    public function testGetRowBack()
    {
        $table = 'test_table';
        $id = 'test_id';
        $when = 1234567890;
        $columns = ['col1', 'col2'];

        $mockTableCfg = [
            'fields' => [
                'col1' => ['id_option' => 'opt1'],
                'col2' => ['id_option' => 'opt2']
            ]
        ];

        $this->history->expects($this->once())
            ->method('getTableCfg')
            ->with($table)
            ->willReturn($mockTableCfg);

        $mockHistoryResult = [
            ['val' => 'value1', 'ref' => null],
            ['val' => 'value2', 'ref' => null]
        ];

        $this->dbMock->expects($this->exactly(2))
            ->method('rselect')
            ->willReturnOnConsecutiveCalls(
                $mockHistoryResult[0], // First call for col1
                $mockHistoryResult[1]  // Second call for col2
            );

        $mockCurrentValue = 'current_value';
        $this->dbMock->expects($this->once())
            ->method('selectOne')
            ->with($table, 'col2', [$mockTableCfg['primary'] => $id])
            ->willReturn($mockCurrentValue);

        $expectedResult = [
            'col1' => 'value1',
            'col2' => 'current_value'
        ];

        $result = $this->history->getRowBack($table, $id, $when, $columns);
        $this->assertEquals($expectedResult, $result);
    }

    // Test getValBack method
    public function testGetValBack()
    {
        $table = 'test_table';
        $id = 'test_id';
        $when = 1234567890;
        $column = 'col1';

        $mockRowResult = ['col1' => 'value'];

        $this->history->expects($this->once())
            ->method('getRowBack')
            ->with($table, $id, $when, [$column])
            ->willReturn($mockRowResult);

        $result = $this->history->getValBack($table, $id, $when, $column);
        $this->assertEquals('value', $result);
    }

    // Test getCreationDate method
    public function testGetCreationDate()
    {
        $table = 'test_table';
        $id = 'test_id';

        $mockCreationResult = ['date' => '2023-01-01 00:00:00', 'timestamp' => 1672531200];

        $this->history->expects($this->once())
            ->method('getCreation')
            ->with($table, $id)
            ->willReturn($mockCreationResult);

        // Test with string format
        $result = $this->history->getCreationDate($table, $id, true);
        $this->assertEquals('2023-01-01 00:00:00', $result);

        // Test with timestamp format
        $result = $this->history->getCreationDate($table, $id, false);
        $this->assertEquals(1672531200, $result);
    }

    // Test getCreation method
    public function testGetCreation()
    {
        $table = 'test_table';
        $id = 'test_id';

        $mockResult = ['date' => '2023-01-01 00:00:00', 'timestamp' => 1672531200, 'user' => 'test_user'];

        $this->dbMock->expects($this->once())
            ->method('rselect')
            ->with(HistoryMistral::$table, ['date' => 'dt', 'timestamp' => 'tst', 'user' => 'usr'], [
                'uid' => $id,
                'col' => 'col_id',
                'opr' => 'INSERT'
            ], ['tst' => 'DESC'])
            ->willReturn($mockResult);

        $this->history->setDatabaseObj($this->databaseObjMock);
        $result = $this->history->getCreation($table, $id);
        $this->assertEquals($mockResult, $result);
    }

    // Test getLastDate method
    public function testGetLastDate()
    {
        $table = 'test_table';
        $id = 'test_id';

        $mockTimestamp = 1672531200;

        $this->dbMock->expects($this->once())
            ->method('selectOne')
            ->with(HistoryMistral::$table, 'tst', [
                'conditions' => [
                    ['field' => 'uid', 'value' => $id],
                    ['field' => 'col', 'value' => 'col_id']
                ]
            ], ['tst' => 'DESC'])
            ->willReturn($mockTimestamp);

        $this->history->setDatabaseObj($this->databaseObjMock);
        $result = $this->history->getLastDate($table, $id);
        $this->assertEquals($mockTimestamp, $result);
    }

    // Test getHistory method
    public function testGetHistory()
    {
        $table = 'test_table';
        $id = 'test_id';

        $mockTableCfg = [
            'fields' => [
                'col1' => ['type' => 'text', 'id_option' => 'opt1'],
                'col2' => ['type' => 'binary', 'id_option' => 'opt2']
            ]
        ];

        $this->history->expects($this->once())
            ->method('getTableCfg')
            ->with($table)
            ->willReturn($mockTableCfg);

        $mockHistoryResult = [
            'ins' => [['uid' => $id, 'col' => 'opt1', 'tst' => 1672531200]],
            'upd' => [['uid' => $id, 'col' => 'opt1', 'tst' => 1672534800, 'val' => 'new_value']],
            'res' => [],
            'del' => []
        ];

        $this->dbMock->expects($this->exactly(4))
            ->method('rselectAll')
            ->willReturnOnConsecutiveCalls(
                $mockHistoryResult['ins'], // INSERT operations
                $mockHistoryResult['upd'],  // UPDATE operations
                [],                         // RESTORE operations
                []                          // DELETE operations
            );

        $this->dbMock->expects($this->once())
            ->method('selectOne')
            ->with($table, 'col1', [$mockTableCfg['primary'] => $id])
            ->willReturn('current_value');

        $result = $this->history->getHistory($table, $id);
        $this->assertEquals($mockHistoryResult, $result);
    }

    // Test getFullHistory method
    public function testGetFullHistory()
    {
        $table = 'test_table';
        $id = 'test_id';

        $mockTableCfg = [
            'fields' => [
                'col1' => ['name' => 'col1', 'id_option' => 'opt1'],
                'col2' => ['name' => 'col2', 'id_option' => 'opt2']
            ]
        ];

        $this->history->expects($this->once())
            ->method('getTableCfg')
            ->with($table)
            ->willReturn($mockTableCfg);

        $mockCurrentRecord = [
            'col1' => 'current_value',
            'col2' => 'another_current_value'
        ];

        $this->dbMock->expects($this->once())
            ->method('rselect')
            ->with($table, [], [$mockTableCfg['primary'] => $id])
            ->willReturn($mockCurrentRecord);

        $mockHistoryRecords = [
            ['uid' => $id, 'col' => 'opt1', 'tst' => 1672531200, 'val' => 'value1'],
            ['uid' => $id, 'col' => 'opt2', 'tst' => 1672534800, 'val' => 'value2']
        ];

        $this->dbMock->expects($this->once())
            ->method('rselectAll')
            ->with(HistoryMistral::$table, [], ['uid' => $id], ['tst' => 'ASC'])
            ->willReturn($mockHistoryRecords);

        $expectedResult = [
            [
                'column' => 'col1',
                'id_column' => 'opt1',
                'date' => 1672531200,
                'user' => null, // Assuming user is not set in mock
                'value' => 'value1',
                'operation' => 'UPDATE'
            ],
            [
                'column' => 'col2',
                'id_column' => 'opt2',
                'date' => 1672534800,
                'user' => null, // Assuming user is not set in mock
                'value' => 'value2',
                'operation' => 'UPDATE'
            ]
        ];

        $result = $this->history->getFullHistory($table, $id);
        $this->assertEquals($expectedResult, $result);
    }

    // Test getColumnHistory method (alias for getFullHistory)
    public function testGetColumnHistory()
    {
        $table = 'test_table';
        $id = 'test_id';
        $column = 'col1';

        $mockResult = ['history_data'];

        $this->history->expects($this->once())
            ->method('getFullHistory')
            ->with($table, $id, $column)
            ->willReturn($mockResult);

        $result = $this->history->getColumnHistory($table, $id, $column);
        $this->assertEquals($mockResult, $result);
    }

    // Test getTableCfg method
    public function testGetTableCfg()
    {
        $table = 'test_table';

        $mockModelizeResult = [
            'keys' => [
                'PRIMARY' => ['columns' => ['id']]
            ],
            'fields' => [
                'id' => ['type' => 'binary', 'maxlength' => 16, 'null' => false],
                'col1' => ['type' => 'text', 'id_option' => 'opt1']
            ]
        ];

        $this->databaseObjMock->method('modelize')
            ->with($table)
            ->willReturn($mockModelizeResult);

        $this->dbMock->expects($this->once())
            ->method('getForeignKeys')
            ->with('bbn_uid', 'bbn_history_uids', 'test_db')
            ->willReturn(['test_table' => ['id']]);

        $expectedStructure = [
            'history' => true,
            'primary' => 'id',
            'primary_type' => 'binary',
            'primary_length' => 16,
            'auto_increment' => false,
            'fields' => [
                'col1' => ['type' => 'text', 'id_option' => 'opt1']
            ]
        ];

        $result = $this->history->getTableCfg($table);
        $this->assertEquals($expectedStructure, $result);
    }

    // Test getDbCfg method
    public function testGetDbCfg()
    {
        $tables = ['test_table1', 'test_table2'];

        $mockTableCfg1 = ['history' => true];
        $mockTableCfg2 = ['history' => false];

        $this->dbMock->expects($this->once())
            ->method('getTables')
            ->with('test_db')
            ->willReturn($tables);

        $this->history->expects($this->exactly(2))
            ->method('getTableCfg')
            ->willReturnOnConsecutiveCalls(
                $mockTableCfg1,
                $mockTableCfg2
            );

        $expectedResult = [
            'test_table1' => $mockTableCfg1,
            'test_table2' => null // Since history is false, it should return null
        ];

        $result = $this->history->getDbCfg('test_db');
        $this->assertEquals($expectedResult, $result);
    }

    // Test isLinked method
    public function testIsLinked()
    {
        $table = 'test_table';

        $mockLinks = [
            'test_db.test_table' => ['id']
        ];

        HistoryMistral::$links = $mockLinks;

        $this->assertTrue($this->history->isLinked($table));

        // Test with unlinked table
        $unlinkedTable = 'another_test_table';
        $this->assertFalse($this->history->isLinked($unlinkedTable));
    }

    // Test getLinks method
    public function testGetLinks()
    {
        $mockLinks = [
            'test_db.test_table' => ['id']
        ];

        HistoryMistral::$links = $mockLinks;

        $result = $this->history->getLinks();
        $this->assertEquals($mockLinks, $result);
    }

    // Test getRelatedIds method
    public function testGetRelatedIds()
    {
        $id = 'test_id';
        $table = 'test_table';

        $mockForeignKeys = [
            'test_db.related_table1' => ['foreign_col'],
            'test_db.related_table2' => ['another_foreign_col']
        ];

        $this->dbMock->expects($this->once())
            ->method('getForeignKeys')
            ->with('id', $table)
            ->willReturn($mockForeignKeys);

        $mockTableCfg1 = [
            'history' => true,
            'primary' => 'id'
        ];

        $mockTableCfg2 = [
            'history' => false
        ];

        $this->history->expects($this->exactly(2))
            ->method('getTableCfg')
            ->willReturnOnConsecutiveCalls(
                $mockTableCfg1,
                $mockTableCfg2
            );

        $expectedResult = [$id, 'related_id1', 'related_id2'];

        $result = $this->history->getRelatedIds($id, $table);
        $this->assertEquals($expectedResult, $result);
    }

    // Test fusion method
    public function testFusion()
    {
        $ids = ['id1', 'id2'];
        $table = 'test_table';
        $mainId = 'id1';

        $mockCreationDates = [
            'id1' => 1672531200,
            'id2' => 1672534800
        ];

        $this->history->expects($this->exactly(2))
            ->method('getCreationDate')
            ->willReturnMap([
                [$table, 'id1', false] => $mockCreationDates['id1'],
                [$table, 'id2', false] => $mockCreationDates['id2']
            ]);

        $this->dbMock->expects($this->once())
            ->method('rselectAll')
            ->with(HistoryMistral::$table_uids, 'bbn_table', ['bbn_uid' => $ids])
            ->willReturn([['bbn_table' => 'tab_id']]);

        $this->dbMock->expects($this->once())
            ->method('update')
            ->with(HistoryMistral::$table, ['tst' => $mockCreationDates['id1']], [
                'uid' => ['id2'],
                'opr' => 'INSERT'
            ])
            ->willReturn(true);

        $result = $this->history->fusion($ids, $table, $this->dbMock, $mainId);
        $this->assertTrue($result);
    }

    // Test upgrade method
    public function testUpgrade()
    {
        $table = 'test_table';

        $mockStructure = [
            'keys' => [
                'PRIMARY' => ['columns' => ['id']]
            ],
            'fields' => [
                'id' => ['type' => 'binary', 'maxlength' => 16, 'null' => false],
                'col1' => ['type' => 'text']
            ]
        ];

        $this->dbMock->expects($this->once())
            ->method('modelize')
            ->with($table, true)
            ->willReturn($mockStructure);

        $expectedResult = [
            'success' => true,
            'total' => 0,
            'updated' => 0,
            'inserted' => 1
        ];

        $result = $this->history->upgrade($table);
        $this->assertEquals($expectedResult, $result);
    }

    // Test insertUid method
    public function testInsertUid()
    {
        $table = 'test_table';
        $ids = ['id1', 'id2'];

        $mockTableId = 'tab_id';

        $this->databaseObjMock->method('tableId')
            ->with($table)
            ->willReturn($mockTableId);

        $this->dbMock->expects($this->exactly(2))
            ->method('insertIgnore')
            ->willReturn(true);

        $result = $this->history->insertUid($table, $ids);
        $this->assertEquals(2, $result);
    }

    // Test trigger method
    public function testTrigger()
    {
        $cfg = [
            'kind' => 'INSERT',
            'moment' => 'before',
            'tables' => ['test_table'],
            'values_desc' => [['primary' => true]],
            'values' => ['id1']
        ];

        $mockTableCfg = [
            'history' => true,
            'primary' => 'id'
        ];

        $this->dbMock->expects($this->once())
            ->method('modelize')
            ->with('test_table')
            ->willReturn([
                'keys' => ['PRIMARY' => ['columns' => ['id'], 'ref_table' => HistoryMistral::$table_uids]]
            ]);

        $this->history->expects($this->once())
            ->method('getTableCfg')
            ->with('test_db.test_table')
            ->willReturn($mockTableCfg);

        $result = $this->history->trigger($cfg);
        $this->assertArrayHasKey('history', $result);
    }

    // Test private methods
    public function testPrivateMethods()
    {
        $dbMock = $this->createMock(Db::class);
        $dbMock->method('check')->willReturn(true);

        $this->history->setDb($dbMock);

        $result = $this->invokeMethod($this->history, '_get_db');
        $this->assertEquals($dbMock, $result);

        // Test _insert method
        $cfg = [
            'column' => 'col_id',
            'line' => 'test_id',
            'chrono' => time(),
            'operation' => 'UPDATE'
        ];

        $this->dbMock->expects($this->once())
            ->method('insert')
            ->willReturn(true);

        $result = $this->invokeMethod($this->history, '_insert', [$cfg]);
        $this->assertEquals(1, $result);
    }

    // Helper method to invoke private methods
    protected function invokeMethod(&$object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    // Test static methods
    public function testStaticMethods()
    {
        HistoryMistral::disable();
        $this->assertFalse(HistoryMistral::isEnabled());

        HistoryMistral::enable();
        $this->assertTrue(HistoryMistral::isEnabled());
    }
}
