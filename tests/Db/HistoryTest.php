<?php

namespace Db;

use bbn\Appui\Database;
use bbn\Db;
use bbn\Db\History;
use PHPUnit\Framework\TestCase;
use tests\Reflectable;
use tests\ReflectionHelpers;

class HistoryTest extends TestCase
{
  use Reflectable;

  protected History $history;

  protected $db_mock;

  protected $db_obj_mock;

  protected array $cfg = [];

  protected $user = 'a113a123';

  protected function setUp(): void
  {
    $this->db_mock     = \Mockery::mock(Db::class);
    $this->db_obj_mock = \Mockery::mock(Database::class);

    // Reset the instances
    $reflectionClass = new \ReflectionClass(History::class);
    $property        = $reflectionClass->getProperty('instances');
    $property->setAccessible(true);
    $property->setValue([]);

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

    $this->history = new History($this->db_mock, $this->cfg, $this->user, $this->db_obj_mock);
  }

  protected function getClassConfig()
  {
    return $this->getNonPublicProperty('class_cfg');
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

  /** @test */
  public function get_db_method_returns_the_db_instance()
  {
    $method = $this->getNonPublicMethod('_get_db');

    $this->assertInstanceOf(Db::class, $method->invoke($this->history));
  }

  /** @test */
  public function get_database_method_returns_the_database_instance()
  {
    $method = $this->getNonPublicMethod('_get_database');

    $this->assertInstanceOf(Database::class, $method->invoke($this->history));
  }

  /** @test */
  public function insert_method_adds_a_row_in_history_table_when_provided_config_has_no_old_ref_param()
  {
    $this->db_mock->shouldReceive('lastId')->once()->andReturn(22);
    $this->db_mock->shouldReceive('disableLast')->once();
    $this->db_mock->shouldReceive('setLastInsertId')->once()->andReturnSelf();
    $this->db_mock->shouldReceive('enableLast')->once();

    $class_cfg = $this->getClassConfig();

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->with(
        $class_cfg['tables']['history'],
        [
          $class_cfg['arch']['history']['opr'] => 'operation',
          $class_cfg['arch']['history']['uid'] => 'line',
          $class_cfg['arch']['history']['col'] => 'column',
          $class_cfg['arch']['history']['val'] => null,
          $class_cfg['arch']['history']['ref'] => null,
          $class_cfg['arch']['history']['tst'] => 'chrono',
          $class_cfg['arch']['history']['usr'] => $this->user,

        ]
      )
      ->andReturn(1);

    $method = $this->getNonPublicMethod('_insert');

    $result =$method->invoke($this->history, [
      'column'    => 'column',
      'line'      => 'line',
      'chrono'    => 'chrono',
      'operation' => 'operation'
    ]);

    $this->assertTrue((bool)$result);
  }

  /** @test */
  public function insert_method_adds_a_row_in_history_table_when_provided_config_has_a_valid_old_ref_param()
  {
    $this->db_mock->shouldReceive('lastId')->once()->andReturn(22);
    $this->db_mock->shouldReceive('disableLast')->once();
    $this->db_mock->shouldReceive('setLastInsertId')->once()->andReturnSelf();
    $this->db_mock->shouldReceive('enableLast')->once();
    $this->db_mock->shouldReceive('count')->once()->andReturn(1);

    $class_cfg = $this->getClassConfig();

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->with(
        $class_cfg['tables']['history'],
        [
          $class_cfg['arch']['history']['opr'] => 'operation',
          $class_cfg['arch']['history']['uid'] => 'line',
          $class_cfg['arch']['history']['col'] => 'column',
          $class_cfg['arch']['history']['val'] => null,
          $class_cfg['arch']['history']['ref'] => '7f4a2c70bcac11eba47652540000cfbe',
          $class_cfg['arch']['history']['tst'] => 'chrono',
          $class_cfg['arch']['history']['usr'] => $this->user,

        ]
      )
      ->andReturn(1);

    $method = $this->getNonPublicMethod('_insert');

    $result =$method->invoke($this->history, [
      'column'    => 'column',
      'line'      => 'line',
      'chrono'    => 'chrono',
      'operation' => 'operation',
      'old'       => '7f4a2c70bcac11eba47652540000cfbe'
    ]);

    $this->assertTrue((bool)$result);
  }

  /** @test */
  public function insert_method_adds_a_row_in_history_table_when_provided_config_has_a_not_valid_old_ref_param()
  {
    $this->db_mock->shouldReceive('lastId')->once()->andReturn(22);
    $this->db_mock->shouldReceive('disableLast')->once();
    $this->db_mock->shouldReceive('setLastInsertId')->once()->andReturnSelf();
    $this->db_mock->shouldReceive('enableLast')->once();

    $class_cfg = $this->getClassConfig();

    $this->db_mock->shouldReceive('insert')
      ->once()
      ->with(
        $class_cfg['tables']['history'],
        [
          $class_cfg['arch']['history']['opr'] => 'operation',
          $class_cfg['arch']['history']['uid'] => 'line',
          $class_cfg['arch']['history']['col'] => 'column',
          $class_cfg['arch']['history']['val'] => '7f4a2c70',
          $class_cfg['arch']['history']['ref'] => null,
          $class_cfg['arch']['history']['tst'] => 'chrono',
          $class_cfg['arch']['history']['usr'] => $this->user,

        ]
      )
      ->andReturn(1);

    $method = $this->getNonPublicMethod('_insert');

    $result =$method->invoke($this->history, [
      'column'    => 'column',
      'line'      => 'line',
      'chrono'    => 'chrono',
      'operation' => 'operation',
      'old'       => '7f4a2c70'
    ]);

    $this->assertTrue((bool)$result);
  }

  /** @test */
  public function insert_method_returns_zero_when_required_config_are_not_provided()
  {
    $method = $this->getNonPublicMethod('_insert');

    $this->assertSame(0, $method->invoke($this->history, ['column' => 'column', 'line' => 'line']));
    $this->assertSame(0, $method->invoke($this->history, ['chrono' => 'chrono', 'line' => 'line']));
    $this->assertSame(0, $method->invoke($this->history, ['line' => 'line']));
    $this->assertSame(0, $method->invoke($this->history, ['chrono' => 'chrono']));
    $this->assertSame(0, $method->invoke($this->history, ['column' => 'column']));
  }

  /** @test */
  public function insert_method_throws_an_exception_if_the_user_is_not_set()
  {
    $this->expectException(\Exception::class);

    $this->setNonPublicPropertyValue('user', null);

    $this->db_mock->shouldNotReceive('lastId');
    $this->db_mock->shouldNotReceive('disableLast');
    $this->db_mock->shouldNotReceive('insert');
    $this->db_mock->shouldNotReceive('setLastInsertId');
    $this->db_mock->shouldNotReceive('enableLast');

    $method = $this->getNonPublicMethod('_insert');

    $method->invoke($this->history, [
      'column'    => 'column',
      'line'      => 'line',
      'chrono'    => 'chrono'
    ]);
  }
  
  /** @test */
  public function get_table_where_method_returns_a_string_for_the_where_in_the_query_for_the_provided_table()
  {
    $method = $this->getNonPublicMethod('_get_table_where');

    $this->db_obj_mock->shouldReceive('modelize')
      ->with('foo')
      ->once()
      ->andReturn(['fields' => [['id_option' => 'foobar']]]);

    $this->db_mock->shouldReceive('escape')->once()->andReturn('col');
    $this->db_mock->shouldReceive('escapeValue')->once()->andReturn('foobar');

    $this->assertIsString($method->invoke($this->history, 'foo'));
  }

  /** @test */
  public function get_table_where_method_returns_null_if_the_provided_table_name_is_not_valid()
  {
    $method = $this->getNonPublicMethod('_get_table_where');

    $this->assertNull($method->invoke($this->history, '%foo'));
  }

  /** @test */
  public function get_table_where_method_returns_null_if_database_model_returns_null()
  {
    $method = $this->getNonPublicMethod('_get_table_where');

    $this->db_obj_mock->shouldReceive('modelize')
      ->with('foo')
      ->once()
      ->andReturnNull();

    $this->assertNull($method->invoke($this->history, 'foo'));
  }

  /** @test */
  public function getIdColumn_method_returns_the_column_corresponding_options_id()
  {
    $this->db_mock->shouldReceive('tfn')->once()->with('table')->andReturn('db.table');
    $this->db_mock->shouldReceive('getHost')->once()->andReturn('localhost');
    $this->db_obj_mock->shouldReceive('columnId')
      ->once()
      ->with('col', 'table', 'db', 'localhost')
      ->andReturn('column_id');

    $this->assertSame('column_id', $this->history->getIdColumn('col', 'table'));
  }

  /** @test */
  public function getIdColumn_method_returns_false_when_full_table_cannot_be_retrieved()
  {
    $this->db_mock->shouldReceive('tfn')->once()->with('table')->andReturnNull();

    $this->assertFalse((bool)$this->history->getIdColumn('col', 'table'));
  }
}