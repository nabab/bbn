<?php

namespace Appui;

use bbn\Appui\Option;
use bbn\Cache;
use bbn\Db;
use bbn\Str;
use PHPUnit\Framework\TestCase;
use tests\Reflectable;

class OptionTest extends TestCase
{
  use Reflectable;

  protected $db_mock;

  protected $cache_mock;

  protected Option $option;

  protected array $class_cfg;

  protected string $table_index;

  protected array $arch;

  protected string $root = '12345';

  protected string $default = '54321';

  protected string $item = '634a2c70bcac11eba47652540000cfaa';

  protected string $item2 = '634a2c70bcac11eba47652540000cfbb';

  protected string $item3 = '634a2c70bcac11eba47652540000cfcc';

  protected string $item4 = '634a2c70bcac11eba47652540000cfff';

  protected string $item5 = '634a2c70bcac11eba47652540000cabf';

  public function getInstance()
  {
    return $this->option;
  }

  protected function setUp(): void
  {
    $this->setNonPublicPropertyValue('retriever_instance', null, Option::class);
    $this->setNonPublicPropertyValue('retriever_exists', false, Option::class);

    $this->cache_mock = \Mockery::mock(Cache::class);
    $this->db_mock    = \Mockery::mock(Db::class);

    $this->option  = new Option($this->db_mock, [
      'table' => 't_bbn_options',
      'tables' => [
        't_options' => 't_bbn_options'
      ],
      'arch' => [
        't_options' => [
          'id' => 't_id',
          'id_parent' => 't_id_parent',
          'id_alias' => 't_id_alias',
          'num' => 't_num',
          'text' => 't_text',
          'code' => 't_code',
          'value' => 't_value',
          'cfg' => 't_cfg'
        ]
      ]
    ]);

    $this->setNonPublicPropertyValue('engine', $this->cache_mock, Cache::class);
    $this->setNonPublicPropertyValue('is_init', 1, Cache::class);

    $this->table_index = $this->getTableIndex();
    $this->class_cfg   = $this->getClassCfg();
    $this->arch        = $this->class_cfg['arch'][$this->table_index];
  }

  protected function tearDown(): void
  {
    \Mockery::close();
  }

  protected function getClassCfg()
  {
    return $this->getNonPublicProperty('class_cfg');
  }

  protected function getTableIndex()
  {
    return $this->getNonPublicProperty('class_table_index');
  }

  protected function getCachePrefix()
  {
    return Str::encodeFilename(str_replace('\\', '/', \get_class($this->option)), true).'/';
  }

  protected function initCache()
  {
    $cache_prefix = $this->getCachePrefix();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['id'],
        [$this->arch['id_parent'] => null, $this->arch['code'] => 'root']
      )
      ->andReturn($this->root);

    $this->cache_mock->shouldReceive('getSet')
      ->once()
      ->with(\Mockery::on(function ($closure) {
        return is_callable($closure) &&  $closure() === $this->root;
      }), $cache_prefix . 'root/root', 60)
      ->andReturn($this->root);

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['id'],
        [$this->arch['id_parent'] => $this->root, $this->arch['code'] => BBN_APP_NAME]
      )
      ->andReturn($this->default);

    $this->cache_mock->shouldReceive('getSet')
      ->once()
      ->with(\Mockery::on(function ($closure) {
        return is_callable($closure) && $closure() === $this->default;
      }), $cache_prefix . BBN_APP_NAME . '/' . BBN_APP_NAME, 60)
      ->andReturn($this->default);
  }

  protected function dbCheckMock()
  {
    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnTrue();
  }

  /** @test */
  public function constructor_test()
  {
    $this->setNonPublicPropertyValue('retriever_instance', null, Option::class);
    $this->setNonPublicPropertyValue('retriever_exists', false, Option::class);

    $option = new Option($db_mock = \Mockery::mock(Db::class), [
      'tables' => [
        'options' => 'bbn_options_2'
      ],
      'table' => 'bbn_options_2'
    ]);

    $this->assertSame(
      $db_mock,
      $this->getNonPublicProperty('db', $option)
    );

    $this->assertTrue(
      $this->getNonPublicProperty('retriever_exists', $option)
    );

    $this->assertSame(
      $option,
      $this->getNonPublicProperty('retriever_instance', $option)
    );

    $this->assertSame(
      'bbn_options_2',
      $this->getNonPublicProperty('class_cfg', $option)['table']
    );

    $this->assertSame(
      ['options' => 'bbn_options_2'],
      $this->getNonPublicProperty('class_cfg', $option)['tables']
    );

    $this->assertSame(
      'options',
      $this->getNonPublicProperty('class_table_index', $option)
    );

    $this->assertTrue(
      $this->getNonPublicProperty('_is_init_class_cfg', $option)
    );
  }

  /** @test */
  public function check_method_checks_if_cache_is_initialized_and_db_is_ready_to_be_queried()
  {
    $this->setNonPublicPropertyValue('is_init', true);

    $this->dbCheckMock();

    $this->assertTrue($this->option->check());
  }

  /** @test */
  public function init_method_initializes_the_cache_if_not_already_initialized()
  {
    $this->initCache();

    $this->assertTrue(
      $this->option->init()
    );

    $this->assertSame($this->root, $this->getNonPublicProperty('root'));
    $this->assertSame($this->default, $this->getNonPublicProperty('default'));
    $this->assertTrue(
      $this->getNonPublicProperty('is_init')
    );
  }

  /** @test */
  public function init_method_returns_false_when_root_could_not_retrieved()
  {
    $cache_prefix = $this->getCachePrefix();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['id'],
        [$this->arch['id_parent'] => null, $this->arch['code'] => 'root']
      )
      ->andReturn($root = 12345);

    $this->cache_mock->shouldReceive('getSet')
      ->once()
      ->with(\Mockery::on(function ($closure) use ($root) {
        return is_callable($closure) &&  $closure() === $root;
      }), $cache_prefix . 'root/root', 60)
      ->andReturnNull();

    $this->assertFalse($this->option->init());
    $this->assertNull($this->getNonPublicProperty('root'));
    $this->assertNull($this->getNonPublicProperty('default'));
    $this->assertFalse($this->getNonPublicProperty('is_init'));
  }

  /** @test */
 public function init_method_returns_true_when_it_is_already_initialized()
 {
   $this->db_mock->shouldNotReceive('selectOne');
   $this->cache_mock->shouldNotReceive('cacheGetSet');

   $this->setNonPublicPropertyValue('is_init', true);

   $this->assertTrue($this->option->init());
 }

  /** @test */
  public function deleteCache_method_deletes_the_option_cache_for_the_given_id()
  {
    $option = \Mockery::mock(Option::class)
      ->shouldAllowMockingProtectedMethods()
      ->makePartial();

    $option->shouldReceive('check')
      ->times(4)
      ->andReturnTrue();

    $option->shouldReceive('items')
      ->once()
      ->with($this->item)
      ->andReturn([$this->item2, $this->item3]);

    $option->shouldReceive('alias')
      ->once()
      ->with($this->item)
      ->andReturn($this->item4);

    $this->setNonPublicPropertyValue('cache_engine', $this->cache_mock, $option);

    $option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item)
      ->andReturn($this->item5);

    // Make sure that the cacheDelete method will be called 5 times
    // one time for each item of  (items, parent and alias).
    $option->shouldReceive('cacheDelete')
      ->once()
      ->with($this->item)
      ->andReturnTrue();

    $option->shouldReceive('cacheDelete')
      ->once()
      ->with($this->item2)
      ->andReturnTrue();

    $option->shouldReceive('cacheDelete')
      ->once()
      ->with($this->item3)
      ->andReturnTrue();

    $option->shouldReceive('cacheDelete')
      ->once()
      ->with($this->item4)
      ->andReturnTrue();

    $option->shouldReceive('cacheDelete')
      ->once()
      ->with($this->item5)
      ->andReturnTrue();

    $result = $option->deleteCache($this->item);

    $this->assertInstanceOf(Option::class, $result);
  }

  /** @test */
  public function deleteCache_method_deletes_the_option_cache_for_the_given_id_and_subs_is_true()
  {
    $option = \Mockery::mock(Option::class)
      ->shouldAllowMockingProtectedMethods()
      ->makePartial();

    $option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    // cacheDelete method only called once with the given item
    $option->shouldReceive('cacheDelete')
      ->once()
      ->with($this->item)
      ->andReturn();

    $result = $option->deleteCache($this->item, false, true);

    $this->assertInstanceOf(Option::class, $result);
  }

  /** @test */
  public function deleteCache_method_deletes_the_option_cache_for_the_given_id_and_deep_is_true()
  {
    $option = \Mockery::mock(Option::class)
      ->shouldAllowMockingProtectedMethods()
      ->makePartial();

    $option->shouldReceive('check')
      ->times(3)
      ->andReturnTrue();

    // the first call
    $option->shouldReceive('items')
      ->once()
      ->with($this->item)
      ->andReturn([$this->item2]);

    $option->shouldReceive('cacheDelete')
      ->once()
      ->with($this->item) // cacheDelete called with the original given item
      ->andReturnTrue();

    $option->shouldReceive('alias')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item)
      ->andReturn($this->item4);

    $option->shouldReceive('cacheDelete')
      ->once()
      ->with($this->item4) // cacheDelete called with the returned alias
      ->andReturnTrue();

    // the second recursive call
    $option->shouldReceive('items')
      ->once()
      ->with($this->item2)
      ->andReturn([$this->item3]);

    $option->shouldReceive('cacheDelete')
      ->once()
      ->with($this->item2) // cacheDelete called with the second retrieved item
      ->andReturnTrue();

    // the third recursive call
    $option->shouldReceive('items')
      ->once()
      ->with($this->item3)
      ->andReturnNull();

    $option->shouldReceive('cacheDelete')
      ->once()
      ->with($this->item3) // cacheDelete called with the third retrieved item
      ->andReturnTrue();

    $result = $option->deleteCache($this->item, true);

    $this->assertInstanceOf(Option::class, $result);
  }

  /** @test */
  public function deleteCache_method_deletes_the_option_cache_for_the_given_id_and_deep_is_true_and_subs_is_true()
  {
    $option = \Mockery::mock(Option::class)
      ->shouldAllowMockingProtectedMethods()
      ->makePartial();

    $option->shouldReceive('check')
      ->times(2)
      ->andReturnTrue();

    // first call
    $option->shouldReceive('items')
      ->once()
      ->with($this->item)
      ->andReturn([$this->item2]);

    $option->shouldReceive('cacheDelete')
      ->once()
      ->with($this->item)
      ->andReturnTrue();

    // second recursive call
    $option->shouldReceive('items')
      ->once()
      ->with($this->item2)
      ->andReturn([]);

    $option->shouldReceive('cacheDelete')
      ->once()
      ->with($this->item2)
      ->andReturnTrue();

    $result = $option->deleteCache($this->item, true, true);

    $this->assertInstanceOf(Option::class, $result);
  }

  /** @test */
  public function getClassCfg_method_returns_the_configuration_array()
  {
    $this->assertSame(
      $this->getNonPublicProperty('class_cfg'),
      $this->option->getClassCfg()
    );
  }

}