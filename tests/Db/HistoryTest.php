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

  protected array $cfg = [];

  protected function setUp(): void
  {
    $this->db_mock     = \Mockery::mock(Db::class);
    $this->db_obj_mock = \Mockery::mock(Database::class);
    $this->init();
  }

  /**
   * @param array|null $config
   * @throws \Exception
   */
  private function init(?array $config = null)
  {
    $this->cfg = $config ?? [
      'arch' => [
        'options' => [
          'id'
        ]
      ],
      'tables' => [
        'options' => 'bbn_options'
      ]
    ];

    $this->db_mock->shouldReceive('getCurrent')->once()->andReturn('admin');
    $this->db_mock->shouldReceive('getForeignKeys')->once()->andReturn(['foo' => 'bar']);
    $this->db_mock->shouldReceive('setTrigger')->once()->andReturnSelf();

    $this->history = new History($this->db_mock, $this->cfg, '123', $this->db_obj_mock);
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
    $class_cfg = $this->getNonPublicProperty('class_cfg');

    $object_hash = $this->getNonPublicProperty('hash');

    $this->assertTrue(array_key_exists($object_hash, $this->getNonPublicProperty('instances')));

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

  /** @test */
  public function instantiate_an_object_with_the_same_config_does_not_added_to_the_list_of_instances()
  {
    // Instantiate another object
    $this->init();

    $this->assertTrue(count($this->getNonPublicProperty('instances')) === 1);
  }

  /** @test */
  public function instantiate_an_object_with_the_different_config_adds_to_the_list_of_instances()
  {
    // Instantiate another object with different configurations
    $this->init(['foo' => 'bar']);

    $instances    = $this->getNonPublicProperty('instances');
    $current_hash = $this->getNonPublicProperty('hash');

    $this->assertTrue(count($instances) === 2);
    $this->assertTrue(isset($instances[$current_hash]));
    $this->assertInstanceOf(History::class, $instances[$current_hash]);
    $this->assertSame($this->history, $instances[$current_hash]);
  }

  /** @test */
  public function getInstanceFromHash_static_method_returns_a_history_instance_from_the_hash_if_registered()
  {
    // Instantiate another object with different configurations
    $this->init(['foo' => 'bar']);

    $instances    = $this->getNonPublicProperty('instances');
    $current_hash = $this->getNonPublicProperty('hash');

    $this->assertTrue(isset($instances[$current_hash]));
    $this->assertSame($this->history, History::getInstanceFromHash($current_hash));
  }

  /** @test */
  public function getInstanceFromHash_static_method_returns_null_if_hash_is_not_registered()
  {
    $this->assertNull(History::getInstanceFromHash('foo'));
  }

  /** @test */
  public function getInstanceFromHash_static_method_returns_null_if_the_instance_is_not_history()
  {
    // Get the current instances and add a srdClass instance to the array.
    $current_instances        = $this->getNonPublicProperty('instances');
    $current_instances['foo'] = new \stdClass();

    // Then save it to the current instances list.
    $this->setNonPublicPropertyValue('instances', $current_instances);

    $this->assertNull(History::getInstanceFromHash('foo'));
  }

  /** @test */
  public function getHash_method_returns_the_hash_of_the_object()
  {
    $this->assertSame($this->getNonPublicProperty('hash'), $this->history->getHash());
  }
}