<?php

namespace Db;

use bbn\Appui\Database;
use bbn\Db;
use bbn\Db\History;
use PHPUnit\Framework\TestCase;
use tests\Reflectable;

class HistoryTest extends TestCase
{
  use Reflectable;

  protected History $history;

  protected $db_mock;

  protected $db_obj_mock;

  protected function setUp(): void
  {
    $this->db_mock     = \Mockery::mock(Db::class);
    $this->db_obj_mock = \Mockery::mock(Database::class);
  }

  protected function tearDown(): void
  {
    \Mockery::close();
  }

  public function getInstance()
  {
    return $this->history;
  }

  /** @test */
  public function constructor_test()
  {
    $cfg = [
      'arch' => [
        'options' => [
          'id'
        ]
      ],
      'tables' => [
        'options' => 'bbn_options'
      ]
    ];
    $this->history = new History($this->db_mock, $cfg, '123', $this->db_obj_mock);

    $class_cfg = $this->getNonPublicProperty('class_cfg');

    $this->assertTrue(isset($class_cfg['table']));
    $this->assertSame($class_cfg['table'], $this->getNonPublicProperty('class_table'));

    $this->assertSame(
      $this->getNonPublicMethod('getHistoryTableName')->invoke($this->history),
      $this->getNonPublicProperty('class_table')
    );

    $this->assertTrue(isset($class_cfg['arch']['options']));
    $this->assertSame('id', $class_cfg['arch']['options'][0]);

    $this->assertTrue(isset($class_cfg['arch']['history_uids']['bbn_uid']));
    $this->assertSame('bbn_uid', $class_cfg['arch']['history_uids']['bbn_uid']);

    $this->assertSame(
      $this->getNonPublicMethod('getHistoryUidsColumns')->invoke($this->history)['bbn_uid'],
      'bbn_uid'
    );

    $this->assertTrue(isset($class_cfg['arch']['history']['tst']));
    $this->assertSame('tst', $class_cfg['arch']['history']['tst']);

    $this->assertSame(
      $this->getNonPublicMethod('getHistoryTableColumns')->invoke($this->history)['tst'],
      'tst'
    );
  }

}