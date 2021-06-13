<?php

namespace Db;

use bbn\Appui\Database;
use bbn\Db;
use bbn\Db\History;
use PHPUnit\Framework\TestCase;
use tests\Reflectable;
use function Symfony\Component\Translation\t;

class HistoryTest extends TestCase
{
  use Reflectable;

  protected History $history;

  protected $db_mock;

  protected $db_obj_mock;

  protected array $cfg = [];

  protected $user = '7f4a2c70bcac11eba47652540000cfaa';

  protected $cuerrent_db = '614a2c70bcac11eba47652540000cfaa';

  protected $id_table = '322a2c70bcac11eba47652540000cfaa';

  protected $id_option = '777ccc70bcac11eba47652540000cfaa';

  protected $column = '144a2c70bcac11eba47652540000cfaa';

  protected $uid  = '312f2c70bcac11eba47652540000cfaa';

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
   * @param callable|null $callback // Callback to set custom mockery expectations
   * @throws \Exception
   */
  private function init(?array $config = null, ?callable $callback = null)
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

    if ($callback) {
      $callback($this->db_mock);
    } else {
      $this->db_mock->shouldReceive('getCurrent')->once()->andReturn($this->cuerrent_db);
      $this->db_mock->shouldReceive('getForeignKeys')->once()->andReturn(['foo' => 'bar']);
      $this->db_mock->shouldReceive('setTrigger')->once()->andReturnSelf();
    }

    $this->history = new History($this->db_mock, $this->cfg, $this->user, $this->db_obj_mock);
  }

  protected function getClassConfig()
  {
    return $this->getNonPublicProperty('class_cfg');
  }

  /**
   * @param string|null $uid
   * @return array
   */
  private function getModelizeMethodReturnValue(?string  $uid = null)
  {
    return [
      'fields' => [
        'column1' => [
          'id_option' => $uid ?? $this->uid,
          'type'      => 'binary'
        ],
        'primary_column' => [
          'extra'     => 'auto_increment',
          'type'      => 'int',
          'maxlength' => 33
        ]
      ],
      'keys' => [
        'PRIMARY' => [
          'columns' => [
            'primary_column'
          ]
        ]
      ]
    ];
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
    $this->db_obj_mock->shouldReceive('columnId')
      ->once()
      ->with('col', 'table', 'db')
      ->andReturn('column_id');

    $this->assertSame('column_id', $this->history->getIdColumn('col', 'table'));
  }

  /** @test */
  public function getIdColumn_method_returns_false_when_full_table_cannot_be_retrieved()
  {
    $this->db_mock->shouldReceive('tfn')->once()->with('table')->andReturnNull();

    $this->assertFalse((bool)$this->history->getIdColumn('col', 'table'));
  }

  /** @test */
  public function disable_method_test()
  {
    $this->setNonPublicPropertyValue('enabled', true);

    $this->history->enable();

    $this->assertTrue($this->getNonPublicProperty('enabled'));
  }

  /** @test */
  public function isEnabled_method_checks_if_enabled_is_true()
  {
    $this->setNonPublicPropertyValue('enabled', true);
    $this->assertTrue($this->history->isEnabled());

    $this->setNonPublicPropertyValue('enabled', false);
    $this->assertFalse($this->history->isEnabled());
  }

  /** @test */
  public function validDate_method_returns_a_valid_date_from_the_given_argument_or_null_if_not_valid()
  {
    $this->assertSame((float)12345, $this->history->validDate(12345));
    $this->assertSame((float)strtotime('2021-06-11'), $this->history->validDate('2021-06-11'));
    $this->assertNull($this->history->validDate(0));
    $this->assertNull($this->history->validDate('foo'));
  }

  /** @test */
  public function check_method_checks_if_all_history_parameters_are_set_in_order_to_read_write()
  {
    $this->setNonPublicPropertyValue('user', null);
    $this->assertFalse($this->history->check());

    $this->setNonPublicPropertyValue('user', $this->user);
    $this->assertTrue($this->history->check());
  }

  /** @test */
  public function delete_method_deletes_a_history_row_in_db()
  {
    $class_cfg = $this->getClassConfig();

    $this->db_mock->shouldReceive('delete')
      ->once()
      ->with(
        $class_cfg['tables']['history_uids'],
        [$class_cfg['arch']['history_uids']['bbn_uid'] => '124a23']
      )
      ->andReturn(1);

    $this->assertTrue($this->history->delete('124a23'));
    $this->assertFalse($this->history->delete(''));
  }

  /** @test */
  public function setColumn_method_sets_the_active_column_name()
  {
    $this->history->setColumn('foo');
    $this->assertSame('foo', $this->getClassConfig()['arch']['history_uids']['bbn_active']);

    $this->history->setColumn('%bar%');
    $this->assertSame('foo', $this->getClassConfig()['arch']['history_uids']['bbn_active']);
  }

  /** @test */
  public function getColumn_method_gets_the_active_column_name()
  {
    $this->history->setColumn('foo');
    $this->assertSame('foo', $this->history->getColumn());
  }

  /** @test */
  public function setDate_method_sets_the_current_date()
  {
    $this->setNonPublicPropertyValue('date', null);

    $this->history->setDate(12345);
    $this->assertSame((float)12345, $this->getNonPublicProperty('date'));

    $this->history->setDate('2021-06-11');
    $this->assertSame($expected = (float)strtotime('2021-06-11'), $this->getNonPublicProperty('date'));

    $this->history->setDate('foo');
    $this->assertSame($expected, $this->getNonPublicProperty('date'));

    $this->history->setDate(null);
    $this->assertSame($expected, $this->getNonPublicProperty('date'));

    $this->history->setDate(strtotime('+1 week'));
    $this->assertSame((float)time(), $this->getNonPublicProperty('date'));
  }

  /** @test */
  public function getDate_method_gets_the_date_property()
  {
    $this->setNonPublicPropertyValue('date', 12345);
    $this->assertSame((float)12345, $this->history->getDate());

    $this->setNonPublicPropertyValue('date', null);
    $this->assertNull($this->history->getDate());
  }

  /** @test */
  public function unsetDate_method_unsets_the_date_property()
  {
   $this->setNonPublicPropertyValue('date', (float)12345);
    $this->history->unsetDate();

    $this->assertNull($this->getNonPublicProperty('date'));
  }

  /** @test */
  public function setUser_method_sets_the_user_id_that_will_fill_the_user_id_field()
  {
    $this->setNonPublicPropertyValue('user', null);

    $this->history->setUser('123aa');
    $this->assertNull($this->getNonPublicProperty('user'));

    $this->history->setUser($this->user);
    $this->assertSame($this->user, $this->getNonPublicProperty('user'));
  }

  /** @test */
  public function getUser_method_returns_the_current_user_id()
  {
    $this->setNonPublicPropertyValue('user', $this->user);
    $this->assertSame($this->user, $this->history->getUser());
  }
  
  /** @test */
  public function getAllHistory_method_returns_an_array_of_paginated_history_results_from_db()
  {
    $this->init(null, function ($db_mock) {
      $db_mock->shouldReceive('getCurrent')->twice()->andReturn($this->cuerrent_db);
      $db_mock->shouldReceive('getForeignKeys')->once()->andReturn(['foo' => 'bar']);
      $db_mock->shouldReceive('setTrigger')->once()->andReturnSelf();
    });

    $class_cfg = $this->getClassConfig();

    $this->db_obj_mock->shouldReceive('tableId')
      ->once()
      ->with('table_name', $this->cuerrent_db)
      ->andReturn($this->id_table);

    $this->db_mock->shouldReceive('escape')
      ->twice()
      ->andReturn(
        $tab      = $class_cfg['tables']['history'],
        $tab_uids = $class_cfg['tables']['history_uids']
      );

    $this->db_mock->shouldReceive('cfn')
      ->times(4)
      ->andReturn(
        $uid    = "{$class_cfg['tables']['history_uids']}.{$class_cfg['arch']['history_uids']['bbn_uid']}",
        $id_tab = "{$class_cfg['tables']['history_uids']}.{$class_cfg['arch']['history_uids']['bbn_table']}",
        $uid2   = "{$class_cfg['tables']['history']}.{$class_cfg['arch']['history']['uid']}",
        $chrono = "{$class_cfg['tables']['history']}.{$class_cfg['arch']['history']['tst']}",
      );

    $expected_sql = <<<MYSQL
SELECT DISTINCT($uid)
FROM $tab_uids
  JOIN $tab
    ON $uid = $uid2
WHERE $id_tab = ? 
ORDER BY $chrono ASC
LIMIT 3, 20
MYSQL;

    $this->db_mock->shouldReceive('getColArray')
      ->once()
      ->with($expected_sql, hex2bin($this->id_table))
      ->andReturn(['foo' => 'bar']);

    $result = $this->history->getAllHistory('table_name', 3, 20, 'asc');

    $this->assertIsArray($result);
    $this->assertSame(['foo' => 'bar'], $result);
  }

  /** @test */
  public function getAllHistory_method_returns_an_empty_array_if_table_id_is_null()
  {
    $this->init(null, function ($db_mock) {
      $db_mock->shouldReceive('getCurrent')->twice()->andReturn($this->cuerrent_db);
      $db_mock->shouldReceive('getForeignKeys')->once()->andReturn(['foo' => 'bar']);
      $db_mock->shouldReceive('setTrigger')->once()->andReturnSelf();
    });

    $this->db_obj_mock->shouldReceive('tableId')
      ->with('table_name', $this->cuerrent_db)
      ->once()
      ->andReturnNull();

    $this->db_mock->shouldNotReceive('getColArray');

    $result = $this->history->getAllHistory('table_name');
    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /** @test */
  public function getLastModifiedLines_method_returns_an_array_of_last_modified_lines_from_db()
  {
    $this->init(null, function ($db_mock) {
      $db_mock->shouldReceive('getCurrent')->twice()->andReturn($this->cuerrent_db);
      $db_mock->shouldReceive('getForeignKeys')->once()->andReturn(['foo' => 'bar']);
      $db_mock->shouldReceive('setTrigger')->once()->andReturnSelf();
    });

    $class_cfg = $this->getClassConfig();

    $this->db_obj_mock->shouldReceive('tableId')
      ->once()
      ->with('table_name', $this->cuerrent_db)
      ->andReturn($this->id_table);

    $this->db_mock->shouldReceive('escape')
      ->times(4)
      ->andReturn(
        $tab      = $class_cfg['tables']['history'],
        $tab_uids = $class_cfg['tables']['history_uids'],
        $line     = $class_cfg['arch']['history']['uid'],
        $chrono   = $class_cfg['arch']['history']['tst']
      );

    $this->db_mock->shouldReceive('cfn')
      ->times(3)
      ->andReturn(
        $uid    = "{$class_cfg['tables']['history_uids']}.{$class_cfg['arch']['history_uids']['bbn_uid']}",
        $active = "{$class_cfg['tables']['history_uids']}.{$class_cfg['arch']['history_uids']['bbn_active']}",
        $id_tab = "{$class_cfg['tables']['history_uids']}.{$class_cfg['arch']['history_uids']['bbn_table']}",
      );

    $expected_sql = <<<MYSQL
SELECT DISTINCT($line)
FROM $tab_uids
  JOIN $tab
    ON $uid = $line
WHERE $id_tab = ? 
AND $active = 1
ORDER BY $chrono
LIMIT 5, 40
MYSQL;

    $this->db_mock->shouldReceive('getColArray')
      ->once()
      ->with($expected_sql, hex2bin($this->id_table))
      ->andReturn(['foo' => 'bar']);

    $result = $this->history->getLastModifiedLines('table_name', 5, 40);

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
  }

  /** @test */
  public function getLastModifiedLines_method_returns_an_empty_array_if_table_id_is_null()
  {
    $this->init(null, function ($db_mock) {
      $db_mock->shouldReceive('getCurrent')->twice()->andReturn($this->cuerrent_db);
      $db_mock->shouldReceive('getForeignKeys')->once()->andReturn(['foo' => 'bar']);
      $db_mock->shouldReceive('setTrigger')->once()->andReturnSelf();
    });

    $this->db_obj_mock->shouldReceive('tableId')
      ->once()
      ->with('table_name', $this->cuerrent_db)
      ->andReturnNull();

    $this->db_mock->shouldNotReceive('getColArray');

    $result = $this->history->getLastModifiedLines('table_name', 5, 40);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /** @test */
  public function getNextUpdate_method_test_with_column_input_specified()
  {
    $class_cfg = $this->getClassConfig();

    $this->db_obj_mock->shouldReceive('tableId')
      ->once()
      ->with('table_name')
      ->andReturn($this->id_table);

    $this->db_mock->shouldReceive('escape')
      ->twice()
      ->andReturn(
        $tab      = $class_cfg['tables']['history'],
        $tab_uids = $class_cfg['tables']['history_uids']
      );

    $this->db_mock->shouldReceive('cfn')
      ->times(6)
      ->andReturn(
        $uid    = "{$class_cfg['tables']['history_uids']}.{$class_cfg['arch']['history_uids']['bbn_uid']}",
        $id_tab = "{$class_cfg['tables']['history_uids']}.{$class_cfg['arch']['history_uids']['bbn_table']}",
        $id_col = "{$class_cfg['tables']['history']}.{$class_cfg['arch']['history']['col']}",
        $line   = "{$class_cfg['tables']['history']}.{$class_cfg['arch']['history']['uid']}",
        $usr    = "{$class_cfg['tables']['history']}.{$class_cfg['arch']['history']['usr']}",
        $chrono = "{$class_cfg['tables']['history']}.{$class_cfg['arch']['history']['tst']}",
      );

    $expected_where = [
      $uid => 'id_input',
      $id_tab => $this->id_table,
      [$chrono, '>', $this->history->validDate('2021-06-01')],
      $id_col => $this->column
    ];

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        [
          'tables' => [$tab_uids],
          'fields' => [
            $line,
            $id_col,
            $chrono,
            'val' => "IFNULL({$class_cfg['arch']['history']['val']}, {$class_cfg['arch']['history']['ref']})",
            $usr
          ],
          'join' => [[
            'table' => $tab,
            'on' => [
              'logic' => 'AND',
              'conditions' => [[
                'field' => $uid,
                'operator' => '=',
                'exp' => $line
              ]]
            ]]],
          'where' => $expected_where,
          'order' => [$chrono => 'ASC']
        ]
      )
      ->andReturn(['foo' => 'bar']);

    $result = $this->history->getNextUpdate('table_name', 'id_input', '2021-06-01', $this->column);

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
    $this->assertSame(['foo' => 'bar'], $result);

    // Ensure that enable and disable method are called once for each.
    $this->assertSame(1, $this->getNonPublicProperty('enabled_count'));
    $this->assertSame(1, $this->getNonPublicProperty('disabled_count'));
  }

  /** @test */
  public function getNextUpdate_method_test_with_column_input_not_specified()
  {
    $class_cfg = $this->getClassConfig();

    $this->db_obj_mock->shouldReceive('tableId')
      ->once()
      ->with('table_name')
      ->andReturn($this->id_table);

    $this->db_mock->shouldReceive('escape')
      ->twice()
      ->andReturn(
        $tab      = $class_cfg['tables']['history'],
        $tab_uids = $class_cfg['tables']['history_uids']
      );

    $this->db_mock->shouldReceive('cfn')
      ->times(6)
      ->andReturn(
        $uid    = "{$class_cfg['tables']['history_uids']}.{$class_cfg['arch']['history_uids']['bbn_uid']}",
        $id_tab = "{$class_cfg['tables']['history_uids']}.{$class_cfg['arch']['history_uids']['bbn_table']}",
        $id_col = "{$class_cfg['tables']['history']}.{$class_cfg['arch']['history']['col']}",
        $line   = "{$class_cfg['tables']['history']}.{$class_cfg['arch']['history']['uid']}",
        $usr    = "{$class_cfg['tables']['history']}.{$class_cfg['arch']['history']['usr']}",
        $chrono = "{$class_cfg['tables']['history']}.{$class_cfg['arch']['history']['tst']}",
      );

    // No action is done when the column is not specified and the _get_table_where method is called
    // so will set the expectation of the modelize method that called in_get_table_where
    // to null for now.
    $this->db_obj_mock->shouldReceive('modelize')->once()->andReturnNull();

    $expected_where = [
      $uid => 'id_input',
      $id_tab => $this->id_table,
      [$chrono, '>', $this->history->validDate('2021-06-01')]
    ];


    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        [
          'tables' => [$tab_uids],
          'fields' => [
            $line,
            $id_col,
            $chrono,
            'val' => "IFNULL({$class_cfg['arch']['history']['val']}, {$class_cfg['arch']['history']['ref']})",
            $usr
          ],
          'join' => [[
            'table' => $tab,
            'on' => [
              'logic' => 'AND',
              'conditions' => [[
                'field' => $uid,
                'operator' => '=',
                'exp' => $line
              ]]
            ]]],
          'where' => $expected_where,
          'order' => [$chrono => 'ASC']
        ]
      )
      ->andReturn(['foo' => 'bar']);

    $result = $this->history->getNextUpdate('table_name', 'id_input', '2021-06-01');

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
    $this->assertSame(['foo' => 'bar'], $result);

    // Ensure that enable and disable method are called once for each.
    $this->assertSame(1, $this->getNonPublicProperty('enabled_count'));
    $this->assertSame(1, $this->getNonPublicProperty('disabled_count'));
  }

  /** @test */
  public function getNextUpdate_method_returns_null_when_table_name_is_not_valid()
  {
    $this->assertNull($this->history->getNextUpdate('%table_name', 'id_input', '2021-06-01'));
  }

  /** @test */
  public function getNextUpdate_method_returns_null_when_date_is_not_valid()
  {
    $this->assertNull($this->history->getNextUpdate('table_name', 'id_input', 'foo'));
  }

  /** @test */
  public function getNextUpdate_method_returns_null_when_id_table_is_not_valid()
  {
    $this->db_obj_mock->shouldReceive('tableId')->with('table_name')->once()->andReturnNull();

    $this->assertNull($this->history->getNextUpdate('table_name', 'id_input', '2021-06-01'));
  }

  /** @test */
  public function getPrevUpdate_method_with_column_input_is_specified()
  {
    $class_cfg = $this->getClassConfig();

    $this->db_mock->shouldReceive('escape')
      ->times(5)
      ->andReturn(
        $tab        = $class_cfg['tables']['history'],
        $line       = $class_cfg['arch']['history']['uid'],
        $operation  = $class_cfg['arch']['history']['opr'],
        $chrono     = $class_cfg['arch']['history']['tst'],
        $col        = $class_cfg['arch']['history']['col']
      );

    $this->db_mock->shouldReceive('escapeValue')
      ->once()
      ->with($this->column)
      ->andReturn($this->column);

    $expected_where = "$col = UNHEX(\"$this->column\")";

    $expected_sql = <<< MYSQL
SELECT *
FROM $tab
WHERE $line = ?
AND ($expected_where)
AND $operation LIKE 'UPDATE'
AND $chrono < ?
ORDER BY $chrono DESC
LIMIT 1
MYSQL;

    $this->db_mock->shouldReceive('getRow')
      ->once()
      ->with($expected_sql, hex2bin($this->id_table), $this->history->validDate('2021-06-11'))
      ->andReturn(['foo' => 'bar']);

    $result = $this->history->getPrevUpdate('table_name', $this->id_table, '2021-06-11', $this->column);

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
    $this->assertSame(['foo' => 'bar'], $result);
  }

  /** @test */
  public function getPrevUpdate_method_with_column_input_not_specified()
  {
    $class_cfg = $this->getClassConfig();

    $this->db_mock->shouldReceive('escape')
      ->times(5)
      ->andReturn(
        $tab        = $class_cfg['tables']['history'],
        $line       = $class_cfg['arch']['history']['uid'],
        $operation  = $class_cfg['arch']['history']['opr'],
        $chrono     = $class_cfg['arch']['history']['tst'],
        $col        = $class_cfg['arch']['history']['col']
      );

    $this->db_mock->shouldReceive('escapeValue')
      ->once()
      ->with($this->column)
      ->andReturn($this->column);

    $this->db_obj_mock->shouldReceive('modelize')
      ->once()
      ->with('table_name')
      ->andReturn(['fields' => ['column1' => ['id_option' => $this->column]]]);

    $expected_where = "$col = UNHEX(\"$this->column\")";

    $expected_sql = <<< MYSQL
SELECT *
FROM $tab
WHERE $line = ?
AND ($expected_where)
AND $operation LIKE 'UPDATE'
AND $chrono < ?
ORDER BY $chrono DESC
LIMIT 1
MYSQL;

    $this->db_mock->shouldReceive('getRow')
      ->once()
      ->with($expected_sql, hex2bin($this->id_table), $this->history->validDate('2021-06-11'))
      ->andReturn(['foo' => 'bar']);

    $result = $this->history->getPrevUpdate('table_name', $this->id_table, '2021-06-11');

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
    $this->assertSame(['foo' => 'bar'], $result);
  }

  /** @test */
  public function getPrevUpdate_method_returns_null_when_table_name_is_not_valid()
  {
    $result = $this->history->getPrevUpdate('%table_name%', $this->id_table, '2021-06-11');

    $this->assertNull($result);
  }

  /** @test */
  public function getPrevUpdate_method_returns_null_when_date_is_not_valid()
  {
    $result = $this->history->getPrevUpdate('table_name', $this->id_table, 'foo');

    $this->assertNull($result);
  }

  /** @test */
  public function getNextValue_method_returns_the_ref_key_returned_from_getNextValue_method()
  {
    $class_cfg = $this->getClassConfig();

    // Since this method depends on the getNextUpdate
    // Which already tested in it's own test
    // We will partially mock the getNextValue method on history table here so that
    // expectation can be set on the return value of getNextValue method
    $history_partial_mock = \Mockery::mock(History::class)->makePartial();

    $history_partial_mock->shouldReceive('getNextUpdate')
      ->once()
      ->with('table_name', $this->id_table, '2021-06-11', null)
      ->andReturn([
        $class_cfg['arch']['history']['ref'] => 'ref_value',
        $class_cfg['arch']['history']['val'] => null
      ]);

    // Here we will get the class config taken from the real History table
    // then set it to the class config of the mocked History table
    $this->setNonPublicPropertyValue('class_cfg', $class_cfg, $history_partial_mock);

    $result = $history_partial_mock->getNextValue('table_name', $this->id_table, '2021-06-11', null);

    $this->assertSame('ref_value', $result);
  }

  /** @test */
  public function getNextValue_method_returns_the_val_key_returned_from_getNextValue_method()
  {
    $class_cfg = $this->getClassConfig();

    // Since this method depends on the getNextUpdate
    // Which already tested in it's own test
    // We will partially mock the getNextValue method on history table here so that
    // expectation can be set on the return value of getNextValue method
    $history_partial_mock = \Mockery::mock(History::class)->makePartial();

    $history_partial_mock->shouldReceive('getNextUpdate')
      ->once()
      ->with('table_name', $this->id_table, '2021-06-11', null)
      ->andReturn([
        $class_cfg['arch']['history']['val'] => 'val_value',
        $class_cfg['arch']['history']['ref'] => null,
      ]);

    // Here we will get the class config taken from the real History table
    // then set it to the class config of the mocked History table
    $this->setNonPublicPropertyValue('class_cfg', $class_cfg, $history_partial_mock);

    $result = $history_partial_mock->getNextValue('table_name', $this->id_table, '2021-06-11', null);

    $this->assertSame('val_value', $result);
  }

  /** @test */
  public function getNextValue_method_return_false_if_the_getNextUpdate_method_returns_null()
  {
    $history_partial_mock = \Mockery::mock(History::class)->makePartial();

    $history_partial_mock->shouldReceive('getNextUpdate')
      ->once()
      ->with('table_name', $this->id_table, '2021-06-11', null)
      ->andReturnNull();

    $result = $history_partial_mock->getNextValue('table_name', $this->id_table, '2021-06-11', null);

    $this->assertFalse($result);
  }

  /** @test */
  public function getPrevValue_method_returns_the_ref_key_returned_from_getPrevUpdate_method()
  {
    $class_cfg = $this->getClassConfig();

    $history_partial_mock = \Mockery::mock(History::class)->makePartial();

    $history_partial_mock->shouldReceive('getPrevUpdate')
      ->once()
      ->with('table_name', $this->id_table, '2021-06-11', null)
      ->andReturn([
        $class_cfg['arch']['history']['val'] => null,
        $class_cfg['arch']['history']['ref'] => 'ref_value',
      ]);

    $this->setNonPublicPropertyValue('class_cfg', $class_cfg, $history_partial_mock);

    $result = $history_partial_mock->getPrevValue('table_name', $this->id_table, '2021-06-11', null);

    $this->assertSame('ref_value', $result);
  }

  /** @test */
  public function getPrevValue_method_returns_the_val_key_returned_from_getPrevUpdate_method()
  {
    $class_cfg = $this->getClassConfig();

    $history_partial_mock = \Mockery::mock(History::class)->makePartial();

    $history_partial_mock->shouldReceive('getPrevUpdate')
      ->once()
      ->with('table_name', $this->id_table, '2021-06-11', null)
      ->andReturn([
        $class_cfg['arch']['history']['val'] => 'val_value',
        $class_cfg['arch']['history']['ref'] => null,
      ]);

    $this->setNonPublicPropertyValue('class_cfg', $class_cfg, $history_partial_mock);

    $result = $history_partial_mock->getPrevValue('table_name', $this->id_table, '2021-06-11', null);

    $this->assertSame('val_value', $result);
  }

  /** @test */
  public function getPrevValue_method_returns_false_when_the_getPrevUpdate_method_returns_null()
  {
    $history_partial_mock = \Mockery::mock(History::class)->makePartial();

    $history_partial_mock->shouldReceive('getPrevUpdate')
      ->once()
      ->with('table_name', $this->id_table, '2021-06-11', null)
      ->andReturnNull();

    $result = $history_partial_mock->getPrevValue('table_name', $this->id_table, '2021-06-11', null);

    $this->assertFalse($result);
  }

  /** @test */
  public function getRowBack_method_returns_an_array_of_one_row_if_exists_when_provided_time_is_greater_than_current_time()
  {
    $this->init(null, function ($db_mock) {
      $db_mock->shouldReceive('getCurrent')->twice()->andReturn($this->cuerrent_db);
      $db_mock->shouldReceive('getForeignKeys')->once()->andReturn(['foo' => 'bar']);
      $db_mock->shouldReceive('setTrigger')->once()->andReturnSelf();
    });

    $this->setNonPublicPropertyValue('links', ['db.table_name' => ['primary_column']]);
    $this->db_obj_mock->shouldReceive('modelize')->twice()->andReturn(
      [
        'fields' => [
          'column1' => ['id_option' => $this->uid]
        ],
      ]
      , $this->getModelizeMethodReturnValue()
    );

    $this->db_mock->shouldReceive('tfn')->twice()->andReturn('db.table_name');
    $this->db_mock->shouldReceive('tsn')->once()->andReturn('table_name');
    $this->db_obj_mock->shouldReceive('tableId')->once()->andReturn($this->id_table);

    $this->db_mock->shouldReceive('rselect')->once()->andReturn(['foo' => 'bar']);

    $result = $this->history->getRowBack(
      'table_name', $this->id_table,
      date('Y-m-d', strtotime('+2 days')),
      ['column1', 'column2']
    );

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
    $this->assertSame(['foo' => 'bar'], $result);
    $this->assertSame(1, $this->getNonPublicProperty('enabled_count'));
    $this->assertSame(1, $this->getNonPublicProperty('disabled_count'));
  }

  /** @test */
  public function getRowBack_method_returns_null_when_provided_time_is_before_creation_time()
  {
    $this->init(null, function ($db_mock) {
      $db_mock->shouldReceive('getCurrent')->twice()->andReturn($this->cuerrent_db);
      $db_mock->shouldReceive('getForeignKeys')->once()->andReturn(['foo' => 'bar']);
      $db_mock->shouldReceive('setTrigger')->once()->andReturnSelf();
    });

    $this->setNonPublicPropertyValue('links', ['db.table_name' => ['primary_column']]);
    $this->db_obj_mock->shouldReceive('modelize')->twice()->andReturn(
      [
        'fields' => [
          'column1' => ['id_option' => $this->uid]
        ],
      ]
      , $this->getModelizeMethodReturnValue()
    );

    $this->db_mock->shouldReceive('tfn')->times(4)->andReturn('db.table_name');
    $this->db_mock->shouldReceive('tsn')->once()->andReturn('table_name');
    $this->db_obj_mock->shouldReceive('tableId')->once()->andReturn($this->id_table);

    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->andReturn(
        ['date' => (float)strtotime('-1 days')]
      );

    $this->db_obj_mock->shouldReceive('columnId')
      ->once()
      ->with('primary_column', 'table_name', 'db')
      ->andReturn($this->column);

    $result = $this->history->getRowBack(
      'table_name', $this->id_table,
      date('Y-m-d', strtotime('-2 days')),
      ['column1', 'column2']
    );

    $this->assertNull($result);
    $this->assertSame(2, $this->getNonPublicProperty('enabled_count'));
    $this->assertSame(2, $this->getNonPublicProperty('disabled_count'));
  }

  /** @test */
  public function getRowBack_method_returns_an_array_of_one_row_if_exists_when_provided_time_is_after_creation_time_and_empty_columns_is_provided_and_ref_value_is_no_null_from_db()
  {
    $this->init(null, function ($db_mock) {
      $db_mock->shouldReceive('getCurrent')->twice()->andReturn($this->cuerrent_db);
      $db_mock->shouldReceive('getForeignKeys')->once()->andReturn(['foo' => 'bar']);
      $db_mock->shouldReceive('setTrigger')->once()->andReturnSelf();
    });

    $this->setNonPublicPropertyValue('links', ['db.table_name' => ['primary_column']]);
    $this->db_obj_mock->shouldReceive('modelize')->twice()->andReturn(
      [
        'fields' => [
          'column1' => ['id_option' => $this->uid]
        ],
      ]
      , $this->getModelizeMethodReturnValue()
    );

    $this->db_mock->shouldReceive('tfn')->times(4)->andReturn('db.table_name');
    $this->db_mock->shouldReceive('tsn')->once()->andReturn('table_name');
    $this->db_obj_mock->shouldReceive('tableId')->once()->andReturn($this->id_table);

    $class_cgf = $this->getClassConfig();

    $this->db_mock->shouldReceive('rselect')
      ->twice()
      ->andReturn(
        ['date' => (float)strtotime('-4 days')],
        [
          $class_cgf['arch']['history']['ref'] => 'ref_value',
          $class_cgf['arch']['history']['val'] => 'val_value',
        ]
      );

    $this->db_obj_mock->shouldReceive('columnId')
      ->once()
      ->with('primary_column', 'table_name', 'db')
      ->andReturn($this->column);

    $result = $this->history->getRowBack(
      'table_name', $this->id_table,
      date('Y-m-d', strtotime('-2 days'))
    );

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
    $this->assertSame(['column1' => 'ref_value'], $result);
    $this->assertSame(2, $this->getNonPublicProperty('enabled_count'));
    $this->assertSame(2, $this->getNonPublicProperty('disabled_count'));
  }

  /** @test */
  public function getRowBack_method_returns_an_array_of_one_row_if_exists_when_provided_time_is_after_creation_time_and_empty_columns_is_provided_and_ref_value_is_null_from_db()
  {
    $this->init(null, function ($db_mock) {
      $db_mock->shouldReceive('getCurrent')->twice()->andReturn($this->cuerrent_db);
      $db_mock->shouldReceive('getForeignKeys')->once()->andReturn(['foo' => 'bar']);
      $db_mock->shouldReceive('setTrigger')->once()->andReturnSelf();
    });

    $this->setNonPublicPropertyValue('links', ['db.table_name' => ['primary_column']]);
    $this->db_obj_mock->shouldReceive('modelize')->twice()->andReturn(
      [
        'fields' => [
          'column1' => ['id_option' => $this->uid]
        ],
      ]
      , $this->getModelizeMethodReturnValue()
    );

    $this->db_mock->shouldReceive('tfn')->times(4)->andReturn('db.table_name');
    $this->db_mock->shouldReceive('tsn')->once()->andReturn('table_name');
    $this->db_obj_mock->shouldReceive('tableId')->once()->andReturn($this->id_table);

    $class_cgf = $this->getClassConfig();

    $this->db_mock->shouldReceive('rselect')
      ->twice()
      ->andReturn(
        ['date' => (float)strtotime('-4 days')],
        [
          $class_cgf['arch']['history']['ref'] => null,
          $class_cgf['arch']['history']['val'] => 'val_value',
        ]
      );

    $this->db_obj_mock->shouldReceive('columnId')
      ->once()
      ->with('primary_column', 'table_name', 'db')
      ->andReturn($this->column);

    $result = $this->history->getRowBack(
      'table_name', $this->id_table,
      date('Y-m-d', strtotime('-2 days'))
    );

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
    $this->assertSame(['column1' => 'val_value'], $result);
  }

  /** @test */
  public function getRowBack_method_returns_an_array_of_one_row_if_exists_when_provided_time_is_after_creation_time_and_empty_columns_is_provided_and_rselect_tmp_value_is_null()
  {
    $this->init(null, function ($db_mock) {
      $db_mock->shouldReceive('getCurrent')->twice()->andReturn($this->cuerrent_db);
      $db_mock->shouldReceive('getForeignKeys')->once()->andReturn(['foo' => 'bar']);
      $db_mock->shouldReceive('setTrigger')->once()->andReturnSelf();
    });

    $this->setNonPublicPropertyValue('links', ['db.table_name' => ['primary_column']]);
    $this->db_obj_mock->shouldReceive('modelize')->twice()->andReturn(
      [
        'fields' => [
          'column1' => ['id_option' => $this->uid]
        ],
      ]
      , $this->getModelizeMethodReturnValue()
    );

    $this->db_mock->shouldReceive('tfn')->times(4)->andReturn('db.table_name');
    $this->db_mock->shouldReceive('tsn')->once()->andReturn('table_name');
    $this->db_obj_mock->shouldReceive('tableId')->once()->andReturn($this->id_table);

    $this->db_mock->shouldReceive('rselect')
      ->twice()
      ->andReturn(
        ['date' => (float)strtotime('-4 days')],
        null
      );

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with('table_name', 'column1', ['primary_column' => $this->id_table])
      ->andReturn('value_from_selectOne_method');

    $this->db_obj_mock->shouldReceive('columnId')
      ->once()
      ->with('primary_column', 'table_name', 'db')
      ->andReturn($this->column);


    $result = $this->history->getRowBack(
      'table_name', $this->id_table,
      date('Y-m-d', strtotime('-2 days'))
    );

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
    $this->assertSame(['column1' => 'value_from_selectOne_method'], $result);
  }

  /** @test */
  public function getRowBack_method_returns_an_array_of_one_row_if_exists_when_provided_time_is_after_creation_time_and_columns_not_empty_array_is_provided()
  {
    $this->init(null, function ($db_mock) {
      $db_mock->shouldReceive('getCurrent')->twice()->andReturn($this->cuerrent_db);
      $db_mock->shouldReceive('getForeignKeys')->once()->andReturn(['foo' => 'bar']);
      $db_mock->shouldReceive('setTrigger')->once()->andReturnSelf();
    });

    $this->setNonPublicPropertyValue('links', ['db.table_name' => ['primary_column']]);
    $this->db_obj_mock->shouldReceive('modelize')->twice()->andReturn(
      [
        'fields' => [
          'column1' => ['id_option' => $this->uid]
        ],
      ]
      , $this->getModelizeMethodReturnValue()
    );

    $this->db_mock->shouldReceive('tfn')->times(4)->andReturn('db.table_name');
    $this->db_mock->shouldReceive('tsn')->once()->andReturn('table_name');
    $this->db_obj_mock->shouldReceive('tableId')->once()->andReturn($this->id_table);

    $class_cgf = $this->getClassConfig();

    $this->db_mock->shouldReceive('rselect')
      ->twice()
      ->andReturn(
        ['date' => (float)strtotime('-4 days')],
        [
          $class_cgf['arch']['history']['ref'] => null,
          $class_cgf['arch']['history']['val'] => 'val_value',
        ]
      );

    $this->db_obj_mock->shouldReceive('columnId')
      ->once()
      ->with('primary_column', 'table_name', 'db')
      ->andReturn($this->column);

    $result = $this->history->getRowBack(
      'table_name', $this->id_table,
      date('Y-m-d', strtotime('-2 days')),
      ['column1']
    );

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
    $this->assertSame(['column1' => 'val_value'], $result);
    $this->assertSame(2, $this->getNonPublicProperty('enabled_count'));
    $this->assertSame(2, $this->getNonPublicProperty('disabled_count'));
  }

  /** @test */
  public function getRowBack_method_returns_null_when_modelize_method_returns_null()
  {
    $this->db_obj_mock->shouldReceive('modelize')->once()->andReturnNull();

    $this->assertNull(
      $this->history->getRowBack('table_name', $this->id_table, '2021-06-11')
    );

    $this->assertSame(0, $this->getNonPublicProperty('enabled_count'));
    $this->assertSame(0, $this->getNonPublicProperty('disabled_count'));
  }

  /** @test */
  public function getRowBack_method_returns_null_when_getTableCfg_method_returns_null()
  {
    $this->db_obj_mock->shouldReceive('modelize')->once()->andReturn(
      [
        'fields' => [
          'column1' => ['id_option' => $this->uid]
        ],
      ]
    );

    // Called from the getTableCfg() method.
    $this->db_mock->shouldReceive('tfn')->once()->andReturnNull();

    $this->assertNull(
      $this->history->getRowBack('table_name', $this->id_table, '2021-06-11')
    );
    $this->assertSame(0, $this->getNonPublicProperty('enabled_count'));
    $this->assertSame(0, $this->getNonPublicProperty('disabled_count'));
  }


  /** @test */
  public function getRowBack_throws_an_exception_when_date_is_not_valid()
  {
    $this->expectException(\Exception::class);

    $this->history->getRowBack('table_name', $this->id_table, 'foo');
    $this->assertSame(0, $this->getNonPublicProperty('enabled_count'));
    $this->assertSame(0, $this->getNonPublicProperty('disabled_count'));
  }

  /** @test */
  public function getValBack_method_returns_column_value_retrieved_from_getRowBack_method()
  {
    // Will partially mock the getRowBack method since the getValBack method is tested in isolation.
    $history_mock = \Mockery::mock(History::class)->makePartial();

    $history_mock->shouldReceive('getRowBack')
      ->once()
      ->with('table_name', $this->id_table, '2021-06-11', ['column'])
      ->andReturn(['column' => 'value']);

    $result = $history_mock->getValBack('table_name', $this->id_table, '2021-06-11', 'column');

    $this->assertSame('value', $result);
  }

  /** @test */
  public function getValBack_method_returns_false_when_column_not_exists_from_getRowBack_method()
  {
    // Will partially mock the getRowBack method since the getValBack method is tested in isolation.
    $history_mock = \Mockery::mock(History::class)->makePartial();

    $history_mock->shouldReceive('getRowBack')
      ->once()
      ->with('table_name', $this->id_table, '2021-06-11', ['another_column'])
      ->andReturn(['column' => 'value']);

    $result = $history_mock->getValBack('table_name', $this->id_table, '2021-06-11', 'another_column');

    $this->assertFalse($result);
  }

  /** @test */
  public function getValBack_method_returns_false_when_getRowBack_returns_null()
  {
    // Will partially mock the getRowBack method since the getValBack method is tested in isolation.
    $history_mock = \Mockery::mock(History::class)->makePartial();

    $history_mock->shouldReceive('getRowBack')
      ->once()
      ->with('table_name', $this->id_table, '2021-06-11', ['column'])
      ->andReturnNull();

    $result = $history_mock->getValBack('table_name', $this->id_table, '2021-06-11', 'column');

    $this->assertFalse($result);
  }

  /** @test */
  public function getCreationDate_method_returns_creation_date()
  {
    $history_partial_mock = \Mockery::mock(History::class)->makePartial();

    $history_partial_mock->shouldReceive('getCreation')
      ->once()
      ->with('table_name', $this->id_table)
      ->andReturn(['date' => $time = (float)time()]);

    $this->assertSame(
      $time,
      $history_partial_mock->getCreationDate('table_name', $this->id_table)
    );
  }

  /** @test */
  public function getCreationDate_method_returns_null_if_date_key_is_missing_returned_from_getCreation()
  {
    $history_partial_mock = \Mockery::mock(History::class)->makePartial();

    $history_partial_mock->shouldReceive('getCreation')
      ->once()
      ->with('table_name', $this->id_table)
      ->andReturn(['foo' => 'bar']);

    $this->assertNull($history_partial_mock->getCreationDate('table_name', $this->id_table));
  }

  /** @test */
  public function getCreationDate_method_returns_null_when_getCreation_returns_null()
  {
    $history_partial_mock = \Mockery::mock(History::class)->makePartial();

    $history_partial_mock->shouldReceive('getCreation')
      ->once()
      ->with('table_name', $this->id_table)
      ->andReturnNull();

    $this->assertNull($history_partial_mock->getCreationDate('table_name', $this->id_table));
  }
  
  /** @test */
  public function getCreation_method_returns_creation_date()
  {
    // first will set expectations of methods called in
    // getTableCfg() and getIdColumn() methods
    $this->db_mock->shouldReceive('tfn')
      ->times(3)
      ->andReturn('db.table_name');

    $this->db_mock->shouldReceive('tsn')
      ->once()
      ->with('db.table_name')
      ->andReturn('table_name');

    $this->db_mock->shouldReceive('getCurrent')->once()->andReturn($this->cuerrent_db);

    $this->setNonPublicPropertyValue('links', ['db.table_name' => ['primary_column']]);

    $this->db_obj_mock->shouldReceive('modelize')
      ->once()
      ->andReturn(
        $this->getModelizeMethodReturnValue()
      );

    $this->db_obj_mock->shouldReceive('tableId')
      ->once()
      ->with('table_name', $this->cuerrent_db)
      ->andReturn($this->uid);

    $this->db_obj_mock->shouldReceive('columnId')
      ->once()
      ->with('primary_column', 'table_name', 'db')
      ->andReturn($this->column);

    $class_cfg = $this->getClassConfig();

    // Set expectations of calling the Db::reselect in the method being tested
    // and that includes expectations of parameters it should be called with.
    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        $class_cfg['tables']['history'],
        [
          'date' => $class_cfg['arch']['history']['tst'],
          'user' => $class_cfg['arch']['history']['usr']
        ],
        [
          $class_cfg['arch']['history']['uid'] => $this->id_table,
          $class_cfg['arch']['history']['col'] => $this->column, // Returned from columnId() mock
          $class_cfg['arch']['history']['opr'] => 'INSERT'

        ],
        [
          $class_cfg['arch']['history']['tst'] => 'DESC'
        ]
      )
      ->andReturn($expected = ['date' => (float)time()]);


    $result = $this->history->getCreation('table_name', $this->id_table);

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
    $this->assertSame($expected, $result);
    $this->assertSame(1, $this->getNonPublicProperty('enabled_count'));
    $this->assertSame(1, $this->getNonPublicProperty('disabled_count'));
  }

  /** @test */
  public function getCreation_method_returns_null_when_getTableCfg_returns_null()
  {
    $this->db_mock->shouldReceive('tfn')->once()->andReturnNull();

    $this->assertNull(
      $this->history->getCreation('table_name', $this->id_table)
    );
    $this->assertSame(0, $this->getNonPublicProperty('enabled_count'));
    $this->assertSame(0, $this->getNonPublicProperty('disabled_count'));
  }

  /** @test */
  public function getCreation_method_returns_null_when_getIdColumn_returns_null()
  {
    // first will set expectations of methods called in
    // getTableCfg() and getIdColumn() methods
    // The third time this functions is called (in getIdColumn())
    // should return null
    $this->db_mock->shouldReceive('tfn')
      ->times(3)
      ->andReturn('db.table_name', 'db.table_name', null);

    $this->db_mock->shouldReceive('tsn')
      ->once()
      ->with('db.table_name')
      ->andReturn('table_name');

    $this->db_mock->shouldReceive('getCurrent')->once()->andReturn($this->cuerrent_db);

    $this->setNonPublicPropertyValue('links', ['db.table_name' => ['primary_column']]);

    $this->db_obj_mock->shouldReceive('modelize')
      ->once()
      ->andReturn(
        $this->getModelizeMethodReturnValue()
      );

    $this->db_obj_mock->shouldReceive('tableId')
      ->once()
      ->with('table_name', $this->cuerrent_db)
      ->andReturn($this->uid);

    $this->assertNull(
      $this->history->getCreation('table_name', $this->id_table)
    );
    $this->assertSame(0, $this->getNonPublicProperty('enabled_count'));
    $this->assertSame(0, $this->getNonPublicProperty('disabled_count'));
  }

  /** @test */
  public function getCreation_method_returns_null_when_rselect_from_db_returns_null()
  {
    // first will set expectations of methods called in
    // getTableCfg() and getIdColumn() methods
    $this->db_mock->shouldReceive('tfn')
      ->times(3)
      ->andReturn('db.table_name');

    $this->db_mock->shouldReceive('tsn')
      ->once()
      ->with('db.table_name')
      ->andReturn('table_name');

    $this->db_mock->shouldReceive('getCurrent')->once()->andReturn($this->cuerrent_db);

    $this->setNonPublicPropertyValue('links', ['db.table_name' => ['primary_column']]);

    $this->db_obj_mock->shouldReceive('modelize')
      ->once()
      ->andReturn(
        $this->getModelizeMethodReturnValue()
      );

    $this->db_obj_mock->shouldReceive('tableId')
      ->once()
      ->with('table_name', $this->cuerrent_db)
      ->andReturn($this->uid);

    $this->db_obj_mock->shouldReceive('columnId')
      ->once()
      ->with('primary_column', 'table_name', 'db')
      ->andReturn($this->column);

    $class_cfg = $this->getClassConfig();

    // Set expectations of calling the Db::reselect in the method being tested
    // and that includes expectations of parameters it should be called with.
    $this->db_mock->shouldReceive('rselect')
      ->once()
      ->with(
        $class_cfg['tables']['history'],
        [
          'date' => $class_cfg['arch']['history']['tst'],
          'user' => $class_cfg['arch']['history']['usr']
        ],
        [
          $class_cfg['arch']['history']['uid'] => $this->id_table,
          $class_cfg['arch']['history']['col'] => $this->column, // Returned from columnId() mock
          $class_cfg['arch']['history']['opr'] => 'INSERT'

        ],
        [
          $class_cfg['arch']['history']['tst'] => 'DESC'
        ]
      )
      ->andReturnNull();


    $result = $this->history->getCreation('table_name', $this->id_table);

    $this->assertNull($result);
    $this->assertSame(1, $this->getNonPublicProperty('enabled_count'));
    $this->assertSame(1, $this->getNonPublicProperty('disabled_count'));
  }

  /** @test */
  public function getLastDate_method_returns_last_date_when_column_is_specified()
  {
    $history_mock = \Mockery::mock(History::class)->makePartial();
    $history_mock->shouldReceive('getIdColumn')
      ->once()
      ->with('column_name', 'table_name')
      ->andReturn($this->column);

    $class_cfg = $this->getClassConfig();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $class_cfg['tables']['history'],
        $class_cfg['arch']['history']['tst'],
        [
          $class_cfg['arch']['history']['uid'] => $this->uid,
          $class_cfg['arch']['history']['col'] => $this->column
        ],
        [
          $class_cfg['arch']['history']['tst'] => 'DESC'
        ]
      )
      ->andReturn($expected = (float)time());

    $this->setNonPublicPropertyValue('db', $this->db_mock, $history_mock);
    $this->setNonPublicPropertyValue('class_cfg', $class_cfg, $history_mock);

    $result = $history_mock->getLastDate('table_name', $this->uid, 'column_name');

    $this->assertIsFloat($result);
    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getLastDate_method_returns_last_date_when_column_is_not_specified()
  {
    $class_cfg = $this->getClassConfig();

    $this->db_obj_mock->shouldReceive('modelize')
      ->once()
      ->andReturn(
        $this->getModelizeMethodReturnValue($this->id_option)
      );

    $this->db_mock->shouldReceive('escape')
      ->times(4)
      ->andReturn(
        $col    = $class_cfg['arch']['history']['col'],
        $tab    = $class_cfg['tables']['history'],
        $chrono = $class_cfg['arch']['history']['tst'],
        $line   = $class_cfg['arch']['history']['uid'],
      );

    $this->db_mock->shouldReceive('escapeValue')
      ->once()
      ->with($this->id_option)
      ->andReturn($this->id_option);

    $expected_where = "$col = UNHEX(\"$this->id_option\")";

    $expected_sql    = <<< MYSQL
SELECT $chrono
FROM $tab
WHERE $line = ?
AND ($expected_where)
ORDER BY $chrono DESC
MYSQL;

    $this->db_mock->shouldReceive('getOne')
      ->once()
      ->with($expected_sql, hex2bin($this->uid))
      ->andReturn($expected_result = (float)time());

    $result = $this->history->getLastDate('table_name', $this->uid);

    $this->assertIsFloat($result);
    $this->assertSame($expected_result, $result);
  }

  /** @test */
  public function getLastDate_method_returns_null_when_column_is_provided_but_id_column_is_null()
  {
    $history_mock = \Mockery::mock(History::class)->makePartial();
    $history_mock->shouldReceive('getIdColumn')
      ->once()
      ->with('column_name', 'table_name')
      ->andReturnNull();

    $this->assertNull(
      $history_mock->getLastDate('table_name', $this->uid, 'column_name')
    );
  }

  /** @test */
  public function getLastDate_method_returns_null_when_column_is_not_provided_and_get_table_where_method_returns_null()
  {
    $this->assertNull(
      $this->history->getLastDate('%table_name%', $this->uid)
    );
  }

  /** @test */
  public function getHistory_method_returns_history_from_db_when_column_is_not_specified()
  {
    $this->db_mock->shouldReceive('tfn')
      ->twice()
      ->andReturn('db.table_name');

    $this->db_mock->shouldReceive('getCurrent')->once()->andReturn($this->cuerrent_db);

    $this->setNonPublicPropertyValue('links', ['db.table_name' => ['primary_column']]);

    $this->db_obj_mock->shouldReceive('modelize')
      ->once()
      ->with('db.table_name')
      ->andReturn(
        $this->getModelizeMethodReturnValue()
      );

    $this->db_mock->shouldReceive('tsn')
      ->once()
      ->andReturn('table_name');

    $this->db_obj_mock->shouldReceive('tableId')
      ->once()
      ->with('table_name', $this->cuerrent_db)
      ->andReturn($this->id_table);

    $operations = [
      'ins' => 'INSERT',
      'upd' => 'UPDATE',
      'res' => 'RESTORE',
      'del' => 'DELETE'
    ];

    $class_cfg = $this->getClassConfig();

    $expected_fields = [
      'date' => $class_cfg['arch']['history']['tst'],
      'user' => $class_cfg['arch']['history']['usr'],
      $class_cfg['arch']['history']['col'],
      $class_cfg['arch']['history']['val'],
      $class_cfg['arch']['history']['ref']
    ];

    $expected_where  = [
      $class_cfg['arch']['history']['uid'] => $this->uid
    ];

    foreach ($operations as $key => $operation) {
      $expected_where[$class_cfg['arch']['history']['opr']] = $operation;

      $this->db_mock->shouldReceive('rselectAll')
        ->once()
        ->ordered()
        ->with(
          [
            'table' => $class_cfg['tables']['history'],
            'fields' => $expected_fields,
            'where' => [
              'conditions' => $expected_where
            ],
            'order' => [[
              'field' => $class_cfg['arch']['history']['tst'],
              'dir' => 'desc'
            ]]
          ]
        )
        ->andReturn($expected_results[$key] = ["foo_{$key}" => "bar_{$key}"]);
    }


    $result = $this->history->getHistory('table_name', $this->uid);

    $this->assertIsArray($result);
    $this->assertSame($expected_results, $result);
  }

  /** @test */
  public function getHistory_method_returns_history_from_db_when_column_is_specified_and_is_not_uid()
  {
    $this->db_mock->shouldReceive('tfn')
      ->twice()
      ->andReturn('db.table_name');

    $this->db_mock->shouldReceive('getCurrent')->once()->andReturn($this->cuerrent_db);

    $this->setNonPublicPropertyValue('links', ['db.table_name' => ['primary_column']]);

    $this->db_obj_mock->shouldReceive('modelize')
      ->once()
      ->with('db.table_name')
      ->andReturn(
        $this->getModelizeMethodReturnValue()
      );

    $this->db_mock->shouldReceive('tsn')
      ->once()
      ->andReturn('table_name');

    $this->db_obj_mock->shouldReceive('tableId')
      ->once()
      ->with('table_name', $this->cuerrent_db)
      ->andReturn($this->id_table);

    $this->db_obj_mock->shouldReceive('columnId')
      ->once()
      ->with('column1', 'table_name')
      ->andReturn($this->column);

    $operations = [
      'ins' => 'INSERT',
      'upd' => 'UPDATE',
      'res' => 'RESTORE',
      'del' => 'DELETE'
    ];

    $class_cfg = $this->getClassConfig();

    $expected_fields = [
      'date' => $class_cfg['arch']['history']['tst'],
      'user' => $class_cfg['arch']['history']['usr'],
      $class_cfg['arch']['history']['col'],
      $class_cfg['arch']['history']['ref']
    ];

    $expected_where  = [
      $class_cfg['arch']['history']['uid'] => $this->uid,
      $class_cfg['arch']['history']['col'] => $this->column // returned from columnId() mock
    ];

    foreach ($operations as $key => $operation) {
      $expected_where[$class_cfg['arch']['history']['opr']] = $operation;

      $this->db_mock->shouldReceive('rselectAll')
        ->once()
        ->ordered()
        ->with(
          [
            'table' => $class_cfg['tables']['history'],
            'fields' => $expected_fields,
            'where' => [
              'conditions' => $expected_where
            ],
            'order' => [[
              'field' => $class_cfg['arch']['history']['tst'],
              'dir' => 'desc'
            ]]
          ]
        )
        ->andReturn($expected_results[$key] = ["foo_{$key}" => "bar_{$key}"]);
    }

    $result = $this->history->getHistory('table_name', $this->uid, 'column1');

    $this->assertIsArray($result);
    $this->assertSame($expected_results, $result);
  }

  /** @test */
  public function getHistory_method_returns_history_from_db_when_column_is_specified_and_is_uid()
  {
    $this->db_mock->shouldReceive('tfn')
      ->twice()
      ->andReturn('db.table_name');

    $this->db_mock->shouldReceive('getCurrent')->once()->andReturn($this->cuerrent_db);

    $this->setNonPublicPropertyValue('links', ['db.table_name' => ['primary_column']]);

    $this->db_obj_mock->shouldReceive('modelize')
      ->once()
      ->with('db.table_name')
      ->andReturn(
        $this->getModelizeMethodReturnValue($this->column)
      );

    $this->db_mock->shouldReceive('tsn')
      ->once()
      ->andReturn('table_name');

    $this->db_obj_mock->shouldReceive('tableId')
      ->once()
      ->with('table_name', $this->cuerrent_db)
      ->andReturn($this->id_table);

    $operations = [
      'ins' => 'INSERT',
      'upd' => 'UPDATE',
      'res' => 'RESTORE',
      'del' => 'DELETE'
    ];

    $class_cfg = $this->getClassConfig();

    $expected_fields = [
      'date' => $class_cfg['arch']['history']['tst'],
      'user' => $class_cfg['arch']['history']['usr'],
      $class_cfg['arch']['history']['col'],
      $class_cfg['arch']['history']['ref']
    ];

    $expected_where  = [
      $class_cfg['arch']['history']['uid'] => $this->uid,
      $class_cfg['arch']['history']['col'] => $this->column // returned from columnId() mock
    ];

    foreach ($operations as $key => $operation) {
      $expected_where[$class_cfg['arch']['history']['opr']] = $operation;

      $this->db_mock->shouldReceive('rselectAll')
        ->once()
        ->ordered()
        ->with(
          [
            'table' => $class_cfg['tables']['history'],
            'fields' => $expected_fields,
            'where' => [
              'conditions' => $expected_where
            ],
            'order' => [[
              'field' => $class_cfg['arch']['history']['tst'],
              'dir' => 'desc'
            ]]
          ]
        )
        ->andReturn($expected_results[$key] = ["foo_{$key}" => "bar_{$key}"]);
    }

    $result = $this->history->getHistory('table_name', $this->uid, $this->column);

    $this->assertIsArray($result);
    $this->assertSame($expected_results, $result);
  }

  /** @test */
  public function getHistory_method_throws_exception_when_column_is_specified_and_is_uid_but_not_found_in_id_option_field()
  {
    $this->expectException(\Error::class);

    $this->db_mock->shouldReceive('tfn')
      ->twice()
      ->andReturn('db.table_name');

    $this->db_mock->shouldReceive('getCurrent')->once()->andReturn($this->cuerrent_db);

    $this->setNonPublicPropertyValue('links', ['db.table_name' => ['primary_column']]);

    $this->db_obj_mock->shouldReceive('modelize')
      ->once()
      ->with('db.table_name')
      ->andReturn(
        $this->getModelizeMethodReturnValue() // This insert $this->uid in the id_option field instead of $this->column
      );

    $this->db_mock->shouldReceive('tsn')
      ->once()
      ->andReturn('table_name');

    $this->db_obj_mock->shouldReceive('tableId')
      ->once()
      ->with('table_name', $this->cuerrent_db)
      ->andReturn($this->id_table);

    $result = $this->history->getHistory('table_name', $this->uid, $this->column);
  }

  /** @test */
  public function getHistory_method_returns_empty_array_when_check_returns_false()
  {
    $history_mock = \Mockery::mock(History::class)->makePartial();

    $history_mock->shouldReceive('check')->once()->andReturnFalse();

    $result = $history_mock->getHistory('table_name', $this->uid);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /** @test */
  public function getHistory_method_returns_empty_array_when_getTableCfg_returns_null()
  {
    $history_mock = \Mockery::mock(History::class)->makePartial();

    $history_mock->shouldReceive('check')->once()->andReturnTrue();
    $history_mock->shouldReceive('getTableCfg')->once()->andReturnNull();

    $result = $history_mock->getHistory('table_name', $this->uid);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /** @test */
  public function getFullHistory_method_returns_and_array_of_history()
  {
    $this->db_obj_mock->shouldReceive('modelize')
      ->once()
      ->with('table_name')
      ->andReturn(
        $this->getModelizeMethodReturnValue()
      );

    $class_cfg = $this->getClassConfig();

    $this->db_mock->shouldReceive('escape')
      ->once()
      ->with($class_cfg['arch']['history']['col'])
      ->andReturn($col = $class_cfg['arch']['history']['col']);

    $this->db_mock->shouldReceive('escape')
      ->once()
      ->with($class_cfg['tables']['history'])
      ->andReturn($tab = $class_cfg['tables']['history']);

    $this->db_mock->shouldReceive('escape')
      ->once()
      ->with($class_cfg['arch']['history']['uid'])
      ->andReturn($line = $class_cfg['arch']['history']['uid']);

    $this->db_mock->shouldReceive('escape')
      ->once()
      ->with($class_cfg['arch']['history']['tst'])
      ->andReturn($chrono = $class_cfg['arch']['history']['tst']);

    $this->db_mock->shouldReceive('escapeValue')
      ->once()
      ->with($this->uid)
      ->andReturn($this->uid);

    $expected_where = "$col = UNHEX(\"$this->uid\")";

    $expected_sql    = <<< MYSQL
SELECT *
FROM $tab
WHERE $line = ?
AND ($expected_where)
ORDER BY $chrono ASC
MYSQL;

    $this->db_mock->shouldReceive('getRows')
      ->once()
      ->with($expected_sql, hex2bin($this->uid))
      ->andReturn($expected_result = ['foo' => 'bar']);

    $result = $this->history->getFullHistory('table_name', $this->uid);

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
    $this->assertSame($expected_result, $result);
  }

  /** @test */
  public function getFullHistory_returns_empty_array_when_get_table_where_method_returns_null()
  {
    $result = $this->history->getFullHistory('%table_name%', $this->uid);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /** @test */
  public function getColumnHistory_method_returns_an_array_when_upd_key_exists_in_history()
  {
    $class_cfg = $this->getClassConfig();

    $history_mock = \Mockery::mock(History::class)->makePartial();
    $history_mock->shouldReceive('check')->once()->andReturnTrue();
    $history_mock->shouldReceive('getTableCfg')
      ->once()
      ->andReturn(
      $this->getModelizeMethodReturnValue($this->column)
    );

    $history_mock->shouldReceive('getHistory')
      ->once()
      ->andReturn([
        'upd' => [
          [
            $class_cfg['arch']['history']['val'] => 'val_value_2',
            $class_cfg['arch']['history']['ref'] => 'ref_value_2',
            'date' => '2021-06-12',
            'user' => 'user_2'
          ],
          [
            $class_cfg['arch']['history']['val'] => 'val_value_1',
            $class_cfg['arch']['history']['ref'] => 'ref_value_1',
            'date' => '2021-06-11',
            'user' => 'user_1'
          ]
        ]
      ]);

    $history_mock->shouldReceive('getCreation')
      ->once()
      ->with('table_name', $this->uid)
      ->andReturn(
        [
          'date' => $creation_date = (float)time(),
          'user' => $creation_user = $this->user
        ]
      );

    $this->setNonPublicPropertyValue('db', $this->db_mock, $history_mock);
    $this->setNonPublicPropertyValue('class_cfg', $class_cfg, $history_mock);

    $this->db_mock->shouldReceive('getPrimary')
      ->once()
      ->with('table_name')
      ->andReturn(['id']);

    $this->setNonPublicPropertyValue('links', ['db.table_name' => ['primary_column']]);

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with('table_name', 'column1', ['id' => $this->uid])
      ->andReturn('val_from_selectOne');

    $result          = $history_mock->getColumnHistory('table_name', $this->uid, $this->column);
    $expected_result = [
      [
        'date' => $creation_date,
        'val'  => 'val_value_1',
        'user' => $creation_user
      ],
      [
        'date' => '2021-06-11',
        'val'  => 'val_value_2',
        'user' => 'user_1'
      ],
      [
        'date' => '2021-06-12',
        'val'  => 'val_from_selectOne', // returned from selectOne mock
        'user' => 'user_2'
      ]
    ];

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
    $this->assertSame($expected_result, $result);
  }

  /** @test */
  public function getColumnHistory_method_returns_an_array_when_upd_key_is_empty_and_ins_key_exists_in_history()
  {
    $class_cfg = $this->getClassConfig();

    $history_mock = \Mockery::mock(History::class)->makePartial();
    $history_mock->shouldReceive('check')->once()->andReturnTrue();
    $history_mock->shouldReceive('getTableCfg')
      ->once()
      ->andReturn(
        $this->getModelizeMethodReturnValue($this->column)
      );

    $history_mock->shouldReceive('getHistory')
      ->once()
      ->andReturn([
        'ins' => [
          [
            $class_cfg['arch']['history']['val'] => 'val_value_2',
            $class_cfg['arch']['history']['ref'] => 'ref_value_2',
            'date' => '2021-06-12',
            'user' => 'user_2'
          ],
          [
            $class_cfg['arch']['history']['val'] => 'val_value_1',
            $class_cfg['arch']['history']['ref'] => 'ref_value_1',
            'date' => '2021-06-11',
            'user' => 'user_1'
          ]
        ]
      ]);

    $history_mock->shouldReceive('getCreation')
      ->once()
      ->with('table_name', $this->uid)
      ->andReturn(
        [
          'date' => (float)time(),
          'user' => $this->user
        ]
      );

    $this->setNonPublicPropertyValue('db', $this->db_mock, $history_mock);
    $this->setNonPublicPropertyValue('class_cfg', $class_cfg, $history_mock);

    $this->db_mock->shouldReceive('getPrimary')
      ->once()
      ->with('table_name')
      ->andReturn(['id']);

    $this->setNonPublicPropertyValue('links', ['db.table_name' => ['primary_column']]);

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with('table_name', 'column1', ['id' => $this->uid])
      ->andReturn('val_from_selectOne');

    $result          = $history_mock->getColumnHistory('table_name', $this->uid, $this->column);
    $expected_result = [
      [
        'date' => '2021-06-12',
        'val'  => 'val_from_selectOne',
        'user' => 'user_2'
      ]
    ];

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
    $this->assertSame($expected_result, $result);
  }

  /** @test */
  public function getColumnHistory_method_throws_an_exception_when_the_provided_column_is_not_uid_and_not_exists_in_id_option_field()
  {
    $this->expectException(\Error::class);

    $class_cfg = $this->getClassConfig();

    $history_mock = \Mockery::mock(History::class)->makePartial();
    $history_mock->shouldReceive('check')->once()->andReturnTrue();
    $history_mock->shouldReceive('getTableCfg')
      ->once()
      ->andReturn(
        $this->getModelizeMethodReturnValue($this->column)
      );

    $this->setNonPublicPropertyValue('db', $this->db_mock, $history_mock);
    $this->setNonPublicPropertyValue('class_cfg', $class_cfg, $history_mock);

    $this->db_mock->shouldReceive('getPrimary')
      ->once()
      ->with('table_name')
      ->andReturn(['id']);

    $this->setNonPublicPropertyValue('links', ['db.table_name' => ['primary_column']]);

    $result          = $history_mock->getColumnHistory('table_name', $this->uid, 'column');
    $expected_result = [
      [
        'date' => '2021-06-12',
        'val'  => 'val_from_selectOne',
        'user' => 'user_2'
      ]
    ];

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
    $this->assertSame($expected_result, $result);
  }

  /** @test */
  public function getColumnHistory_method_throws_an_exception_when_the_provided_column_is_uid_and_not_exists_in_id_option_field()
  {
    $this->expectException(\Error::class);

    $class_cfg = $this->getClassConfig();

    $history_mock = \Mockery::mock(History::class)->makePartial();
    $history_mock->shouldReceive('check')->once()->andReturnTrue();
    $history_mock->shouldReceive('getTableCfg')
      ->once()
      ->andReturn(
        $this->getModelizeMethodReturnValue($this->uid)
      );

    $this->setNonPublicPropertyValue('db', $this->db_mock, $history_mock);
    $this->setNonPublicPropertyValue('class_cfg', $class_cfg, $history_mock);

    $this->db_mock->shouldReceive('getPrimary')
      ->once()
      ->with('table_name')
      ->andReturn(['id']);

    $this->setNonPublicPropertyValue('links', ['db.table_name' => ['primary_column']]);

    $result          = $history_mock->getColumnHistory('table_name', $this->uid, $this->column);
    $expected_result = [
      [
        'date' => '2021-06-12',
        'val'  => 'val_from_selectOne',
        'user' => 'user_2'
      ]
    ];

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
    $this->assertSame($expected_result, $result);
  }

  /** @test */
  public function getColumnHistory_method_returns_an_empty_array_when_getCreation_method_returns_null()
  {
    $class_cfg = $this->getClassConfig();

    $history_mock = \Mockery::mock(History::class)->makePartial();
    $history_mock->shouldReceive('check')->once()->andReturnTrue();
    $history_mock->shouldReceive('getTableCfg')
      ->once()
      ->andReturn(
        $this->getModelizeMethodReturnValue($this->column)
      );

    $history_mock->shouldReceive('getHistory')
      ->once()
      ->andReturn([]);

    $history_mock->shouldReceive('getCreation')
      ->once()
      ->with('table_name', $this->uid)
      ->andReturnNull();

    $this->setNonPublicPropertyValue('db', $this->db_mock, $history_mock);
    $this->setNonPublicPropertyValue('class_cfg', $class_cfg, $history_mock);

    $this->db_mock->shouldReceive('getPrimary')
      ->once()
      ->with('table_name')
      ->andReturn(['id']);

    $this->setNonPublicPropertyValue('links', ['db.table_name' => ['primary_column']]);

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with('table_name', 'column1', ['id' => $this->uid])
      ->andReturn('val_from_selectOne');

    $result = $history_mock->getColumnHistory('table_name', $this->uid, $this->column);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /** @test */
  public function getColumnHistory_method_returns_an_empty_array_when_check_method_returns_null()
  {
    $history_mock = \Mockery::mock(History::class)->makePartial();
    $history_mock->shouldReceive('check')->once()->andReturnFalse();

    $result = $history_mock->getColumnHistory('table_name', $this->uid, $this->column);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /** @test */
  public function getColumnHistory_method_returns_an_empty_array_when_getPrimary_method_returns_empty_array()
  {
    $history_mock = \Mockery::mock(History::class)->makePartial();

    $this->setNonPublicPropertyValue('db', $this->db_mock, $history_mock);

    $history_mock->shouldReceive('check')->once()->andReturnTrue();
    $this->db_mock->shouldReceive('getPrimary')->once()->andReturn([]);

    $result = $history_mock->getColumnHistory('table_name', $this->uid, $this->column);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /** @test */
  public function getColumnHistory_method_returns_an_empty_array_when_getTableCfg_method_returns_null()
  {
    $history_mock = \Mockery::mock(History::class)->makePartial();

    $this->setNonPublicPropertyValue('db', $this->db_mock, $history_mock);

    $history_mock->shouldReceive('check')->once()->andReturnTrue();
    $this->db_mock->shouldReceive('getPrimary')->once()->andReturn(['id']);
    $history_mock->shouldReceive('getTableCfg')->once()->andReturnNull();

    $result = $history_mock->getColumnHistory('table_name', $this->uid, $this->column);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /** @test */
  public function getTableCfg_method_returns_all_information_of_a_given_table()
  {
    $this->db_mock->shouldReceive('tfn')
      ->once()
      ->with('table_name')
      ->andReturn('db.table_name');

    $this->db_obj_mock->shouldReceive('modelize')
      ->once()
      ->with('db.table_name')
      ->andReturn(
        [
          'fields' => [
            'column1' => [
              'id_option' => $this->uid,
              'type'      => 'binary'
            ],
            'primary_column' => [
              'extra'     => 'auto_increment',
              'type'      => 'int',
              'maxlength' => 22
            ]
          ],
          'keys' => [
            'PRIMARY' => [
              'columns' => [
                'primary_column'
              ]
            ]
          ]
        ]
      );

    $this->setNonPublicPropertyValue('links', ['db.table_name' => ['primary_column']]);

    // Called in the isLinked() method
    $this->db_mock->shouldReceive('tfn')
      ->once()
      ->with('db.table_name')
      ->andReturn('db.table_name');

    $this->db_mock->shouldReceive('tsn')
      ->once()
      ->with('db.table_name')
      ->andReturn('table_name');

    $this->db_mock->shouldReceive('getCurrent')
      ->once()
      ->andReturn($this->cuerrent_db);

    $this->db_obj_mock->shouldReceive('tableId')
      ->once()
      ->with('table_name', $this->cuerrent_db)
      ->andReturn($this->id_table);

    $result = $this->history->getTableCfg('table_name');

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);

    $structures = $this->getNonPublicProperty('structures');

    $this->assertIsArray($structures);
    $this->assertNotEmpty($structures);

    // Returned from modelize() method mock.
    $expected_structures = [
      'db.table_name' => $expected_result = [
        'history'         => 1,
        'primary'         => 'primary_column',
        'primary_type'    => 'int',
        'primary_length'  => 22,
        'auto_increment'  => true,
        'id'              => $this->id_table,
        'fields'          => [
          'column1' => [
            'id_option' => $this->uid,
            'type'      => 'binary'
          ]
        ]
      ]
    ];

    $this->assertSame($expected_structures, $structures);
    $this->assertSame($expected_result, $result);
  }

  /** @test */
  public function getTableCfg_method_returns_null_when_tfn_method_returns_null()
  {
    $this->db_mock->shouldReceive('tfn')
      ->once()
      ->with('table_name')
      ->andReturnNull();

    $this->assertNull(
      $this->history->getTableCfg('table_name')
    );
  }

  /** @test */
  public function getTableCfg_returns_the_current_table_structure_when_exists_without_calling_modelize_method()
  {
    $structures = [
      'db.table_name' => $expected_result = [
        'history'         => 1,
        'primary'         => 'primary_column',
        'primary_type'    => 'int',
        'primary_length'  => 11,
        'auto_increment'  => true,
        'id'              => $this->id_table,
        'fields'          => [
          'column2' => [
            'id_option' => $this->uid,
            'type'      => 'binary'
          ]
        ]
      ]
    ];

    $this->db_mock->shouldReceive('tfn')
      ->with('table_name')
      ->once()
      ->andReturn('db.table_name');

    $this->db_obj_mock->shouldNotReceive('modelize');

    $this->setNonPublicPropertyValue('structures', $structures);

    $result = $this->history->getTableCfg('table_name');

    $this->assertSame($expected_result, $result);
    $this->assertSame($structures, $this->getNonPublicProperty('structures'));
  }

  /** @test */
  public function getTableCfg_returns_full_information_about_the_given_table_even_if_it_is_already_saved_when_force_is_true()
  {
    $saved_structures = [
      'db.table_name' => $old_results = [
        'history'         => 1,
        'primary'         => 'primary_column',
        'primary_type'    => 'int',
        'primary_length'  => 30,
        'auto_increment'  => true,
        'id'              => $this->id_table,
        'fields'          => [
          'column' => [
            'id_option' => $this->uid,
            'type'      => 'binary'
          ]
        ]
      ]
    ];

    $this->setNonPublicPropertyValue('structures', $saved_structures);

    $this->db_mock->shouldNotReceive('tfn')
      ->once()
      ->with('table_name')
      ->andReturn('db.table_name');

    $this->db_obj_mock->shouldReceive('modelize')
      ->once()
      ->with('db.table_name')
      ->andReturn(
        [
          'fields' => [
            'another_column' => [
              'id_option' => $this->uid,
              'type'      => 'binary'
            ],
            'another_primary_column' => [
              'extra'     => 'auto_increment',
              'type'      => 'int',
              'maxlength' => 40
            ]
          ],
          'keys' => [
            'PRIMARY' => [
              'columns' => [
                'another_primary_column'
              ]
            ]
          ]
        ]
      );

    $expected_structures = [
      'db.table_name' => $expected_result = [
        'history'         => 1,
        'primary'         => 'another_primary_column',
        'primary_type'    => 'int',
        'primary_length'  => 40,
        'auto_increment'  => true,
        'id'              => $this->id_table,
        'fields'          => [
          'another_column' => [
            'id_option' => $this->uid,
            'type'      => 'binary'
          ]
        ]
      ]
    ];

    $this->setNonPublicPropertyValue('links', ['db.table_name' => ['primary_column']]);

    $this->db_mock->shouldReceive('tfn')
      ->once()
      ->with('db.table_name')
      ->andReturn('db.table_name');

    $this->db_mock->shouldReceive('tsn')
      ->once()
      ->with('db.table_name')
      ->andReturn('table_name');

    $this->db_obj_mock->shouldReceive('tableId')
      ->once()
      ->with('table_name', $this->cuerrent_db)
      ->andReturn($this->id_table);

    $this->db_mock->shouldReceive('getCurrent')
      ->once()
      ->andReturn($this->cuerrent_db);

    $result = $this->history->getTableCfg('table_name', true);

    $this->assertSame($expected_structures, $this->getNonPublicProperty('structures'));
    $this->assertNotSame($old_results, $result);
    $this->assertSame($expected_result, $result);
  }

  /** @test */
  public function getTableCfg_method_returns_null_when_modelize_method_returns_null()
  {
    $this->db_mock->shouldReceive('tfn')
      ->once()
      ->with('table_name')
      ->andReturn('db.table_name');

    $this->db_obj_mock->shouldReceive('modelize')
      ->once()
      ->with('db.table_name')
      ->andReturnNull();

    $this->assertNull(
      $this->history->getTableCfg('table_name')
    );
    $this->assertEmpty($this->getNonPublicProperty('structures'));
  }

  /** @test */
  public function getTableCfg_method_returns_null_when_modelize_method_returns_null_and_force_is_true()
  {
    $this->db_mock->shouldReceive('tfn')
      ->once()
      ->with('table_name')
      ->andReturn('db.table_name');

    $this->db_obj_mock->shouldReceive('modelize')
      ->once()
      ->with('db.table_name')
      ->andReturnNull();

    $this->assertNull(
      $this->history->getTableCfg('table_name', true)
    );
    $this->assertEmpty($this->getNonPublicProperty('structures'));
  }

  /** @test */
  public function getTableCfg_returns_null_when_isLinked_method_returns_false()
  {
    $this->db_mock->shouldReceive('tfn')
      ->once()
      ->with('table_name')
      ->andReturn('db.table_name');

    $this->db_obj_mock->shouldReceive('modelize')
      ->once()
      ->with('db.table_name')
      ->andReturn(
        $this->getModelizeMethodReturnValue()
      );

    $this->db_mock->shouldReceive('tfn')
      ->once()
      ->with('db.table_name')
      ->andReturn('db.table_name');

    $this->setNonPublicPropertyValue('links', []);

    $this->assertNull(
      $this->history->getTableCfg('table_name', true)
    );

    $this->assertSame([
      'db.table_name' => [
        'history' => false,
        'primary' => false,
        'primary_type' => null,
        'primary_length' => 0,
        'auto_increment' => false,
        'id' => null,
        'fields' => []
      ]
    ], $this->getNonPublicProperty('structures'));
  }

  /** @test */
  public function getTableCfg_returns_null_when_PRIMARY_key_does_not_exist_in_the_model_array()
  {
    $this->db_mock->shouldReceive('tfn')
      ->once()
      ->with('table_name')
      ->andReturn('db.table_name');

    $this->db_obj_mock->shouldReceive('modelize')
      ->once()
      ->with('db.table_name')
      ->andReturn([
        'keys' => []
      ]);

    $this->db_mock->shouldReceive('tfn')
      ->once()
      ->with('db.table_name')
      ->andReturn('db.table_name');

    $this->setNonPublicPropertyValue('links', ['db.table_name' => ['primary_column']]);

    $this->assertNull(
      $this->history->getTableCfg('table_name', true)
    );
  }

  /** @test */
  public function getTableCfg_returns_null_when_columns_key_in_PRIMARY_array_count_does_not_equal_to_one()
  {
    $this->db_mock->shouldReceive('tfn')
      ->once()
      ->with('table_name')
      ->andReturn('db.table_name');

    $this->db_obj_mock->shouldReceive('modelize')
      ->once()
      ->with('db.table_name')
      ->andReturn([
        'keys' => [
          'PRIMARY' => [
            'columns' => [
              'primary_column',
              'another_column',
            ]
          ]
        ]
      ]);

    $this->db_mock->shouldReceive('tfn')
      ->once()
      ->with('db.table_name')
      ->andReturn('db.table_name');

    $this->setNonPublicPropertyValue('links', ['db.table_name' => ['primary_column']]);

    $this->assertNull(
      $this->history->getTableCfg('table_name', true)
    );
  }

  /** @test */
  public function getTableCfg_returns_null_when_fields_primary_column_array_is_empty()
  {
    $this->db_mock->shouldReceive('tfn')
      ->once()
      ->with('table_name')
      ->andReturn('db.table_name');

    $this->db_obj_mock->shouldReceive('modelize')
      ->once()
      ->with('db.table_name')
      ->andReturn(
        [
          'fields' => [
            'primary_column' => [],
          ],
          'keys' => [
            'PRIMARY' => [
              'columns' => [
                'primary_column'
              ]
            ]
          ]
        ]
      );

    $this->db_mock->shouldReceive('tfn')
      ->once()
      ->with('db.table_name')
      ->andReturn('db.table_name');

    $this->setNonPublicPropertyValue('links', ['db.table_name' => ['primary_column']]);

    $this->assertNull(
      $this->history->getTableCfg('table_name', true)
    );
  }

  /** @test */
  public function getDbCfg_method_returns_information_about_all_tables_in_the_given_database()
  {
    $history_mock = \Mockery::mock(History::class)->makePartial();
    $this->setNonPublicPropertyValue('db', $this->db_mock, $history_mock);

    $this->db_mock->shouldReceive('getTables')
      ->once()
      ->with('db')
      ->andReturn([
        'table1', 'table2'
      ]);

    // The getTableCfg() method should be called twice
    // in the loop since the getTables() returned array of count two
    $history_mock->shouldReceive('getTableCfg')
      ->twice()
      ->andReturn(
        [
          'db.table1' => [
            'foo1' => 'bar1'
          ]
        ],
        [
          'db.table2' => [
            'foo2' => 'bar2'
          ]
        ]
      );


    $result = $history_mock->getDbCfg('db');

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);
    $this->assertSame([
      'table1'  => [
        'db.table1' => [
          'foo1' => 'bar1'
        ]
      ],
      'table2'  => [
        'db.table2' => [
          'foo2' => 'bar2'
        ]
      ]
    ], $result);
  }

  /** @test */
  public function getDbCfg_method_returns_empty_array_when_no_tables_found()
  {
    $this->db_mock->shouldReceive('getTables')
      ->once()
      ->with('db')
      ->andREturn([]);

    $result = $this->history->getDbCfg('db');

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /** @test */
  public function getDbCfg_method_returns_empty_array_when_getTables_returns_null()
  {
    $this->db_mock->shouldReceive('getTables')
      ->once()
      ->with('db')
      ->andReturnNull();

    $result = $this->history->getDbCfg('db');

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }
}