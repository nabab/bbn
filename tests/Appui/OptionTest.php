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
        'options' => 't_bbn_options'
      ],
      'arch' => [
        'options' => [
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

  protected function getUidCacheName(string $uid)
  {
    return \bbn\Str::isUid($uid) ? substr($uid, 0, 3).'/'.substr($uid, 3, 3).'/'.substr($uid, 6) : $uid;
  }

  protected function mockOptionClass()
  {
    $this->option = \Mockery::mock(Option::class)
      ->shouldAllowMockingProtectedMethods()
      ->makePartial();

    $this->setNonPublicPropertyValue('class_cfg', $this->class_cfg);
    $this->setNonPublicPropertyValue('db', $this->db_mock);

    $this->getNonPublicMethod('cacheInit')
      ->invoke($this->option);
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

  protected function dbCheckMock(int $times = 1)
  {
    $this->db_mock->shouldReceive('check')
      ->times($times)
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

  /** @test */
  public function fromCode_method_returns_option_id_from_given_codes()
  {
    $this->initCache();
    $this->dbCheckMock(3);

    $cache_prefix = $this->getCachePrefix();

    $this->cache_mock->shouldReceive('get')
      ->andReturnNull();

    /*
     * First method call for 'appui' and default parent
     */
    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['id'],
        [
          [$this->arch['id_parent'], '=', $this->default],
          [$this->arch['code'], '=', 'appui']
        ]
      )
      ->andReturn($this->item);

    $this->cache_mock->shouldReceive('set')
      ->with(
        "{$cache_prefix}{$this->default}/get_code_" . base64_encode('appui'),
        $this->item,
        0
      )
      ->once()
      ->andReturnTrue();

    /*
     * Second recursive method call for 'project' and id from the previous call $this->item
     */
    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['id'],
        [
          [$this->arch['id_parent'], '=', $this->item],
          [$this->arch['code'], '=', 'project']
        ]
      )
      ->andReturn($this->item2);

    $this->cache_mock->shouldReceive('set')
      ->with(
        "{$cache_prefix}".($this->getUidCacheName($this->item))."/get_code_" . base64_encode('project'),
        $this->item2,
        0
      )
      ->once()
      ->andReturnTrue();

    /*
     * Third recursive method call for 'list' and id from the previous call $this->item2
     */
    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['id'],
        [
          [$this->arch['id_parent'], '=', $this->item2],
          [$this->arch['code'], '=', 'list']
        ]
      )
      ->andReturn($this->item3);

    $this->cache_mock->shouldReceive('set')
      ->with(
        "{$cache_prefix}".($this->getUidCacheName($this->item2))."/get_code_" . base64_encode('list'),
        $this->item3,
        0
      )
      ->once()
      ->andReturnTrue();

    // The result should be the id returned from the last recursive call for 'list' and id from previous call for 'project'
    $this->assertSame(
      $this->item3,
      $this->option->fromCode('list', 'project', 'appui')
    );
  }

  /** @test */
  public function fromCode_method_returns_option_id_from_given_codes_when_there_is_a_cache_set()
  {
    $this->initCache();
    $this->dbCheckMock(3);

    $cache_prefix = $this->getCachePrefix();

    /*
     * First method call for 'appui' and default parent
     */
    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        "{$cache_prefix}{$this->default}/get_code_" . base64_encode('appui')
      )
      ->andReturn($this->item);

    /*
     * Second recursive method call for 'project' and id from the previous call $this->item2
     */
    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        "{$cache_prefix}".($this->getUidCacheName($this->item))."/get_code_" . base64_encode('project')
      )
      ->andReturn($this->item2);

    /*
     * Third recursive method call for 'list' and id from the previous call $this->item3
     */
    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        "{$cache_prefix}".($this->getUidCacheName($this->item2))."/get_code_" . base64_encode('list')
      )
      ->andReturn($this->item3);

    // The result should be the id returned from the last recursive call for 'list' and id from previous call for 'project'
    $this->assertSame(
      $this->item3,
      $this->option->fromCode('list', 'project', 'appui')
    );
  }

  /** @test */
  public function fromCode_method_returns_option_id_from_the_given_codes_and_a_parent_id_to_find_the_last_code()
  {
    $this->initCache();
    $this->dbCheckMock(2);

    $cache_prefix = $this->getCachePrefix();

    $this->cache_mock->shouldReceive('get')
      ->andReturnNull();

    /*
     * First method call for 'project' and parent id $this->item
     */
    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['id'],
        [
          [$this->arch['id_parent'], '=', $this->item],
          [$this->arch['code'], '=', 'project']
        ]
      )
      ->andReturn($this->item2);

    $this->cache_mock->shouldReceive('set')
      ->once()
      ->with(
        "$cache_prefix" . ($this->getUidCacheName($this->item)) . '/get_code_' . base64_encode('project'),
        $this->item2,
        0
      )
      ->andReturnTrue();

    /*
     * Second recursive method call for 'list' and id from the previous call $this->item2
     */
    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['id'],
        [
          [$this->arch['id_parent'], '=', $this->item2],
          [$this->arch['code'], '=', 'list']
        ]
      )
      ->andReturn($this->item3);

    $this->cache_mock->shouldReceive('set')
      ->once()
      ->with(
        "$cache_prefix" . ($this->getUidCacheName($this->item2)) . '/get_code_' . base64_encode('list'),
        $this->item3,
        0
      )
      ->andReturnTrue();

    // The result should be the id returned from the last recursive call for 'list' and id from previous call for 'project'
    $this->assertSame(
      $this->item3,
      $this->option->fromCode('list', 'project', $this->item)
    );
  }

  /** @test */
  public function fromCode_method_returns_option_id_from_the_given_codes_and_a_parent_id_to_find_the_last_code_when_there_is_a_cache_set()
  {
    $this->initCache();
    $this->dbCheckMock(2);

    $cache_prefix = $this->getCachePrefix();

    /*
    * First method call for 'project' and parent id $this->item
    */
    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        $cache_prefix . $this->getUidCacheName($this->item) . '/get_code_' . base64_encode('project')
      )
      ->andReturn($this->item2);

    /*
     * Second recursive method call for 'list' and id from the previous call $this->item2
     */
    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        $cache_prefix . $this->getUidCacheName($this->item2) . '/get_code_' . base64_encode('list')
      )
      ->andReturn($this->item3);

    $this->assertSame(
      $this->item3,
      $this->option->fromCode('list', 'project', $this->item)
    );
  }

  /** @test */
  public function fromCode_method_returns_option_id_only_from_the_given_code_having_default_as_parent()
  {
    $this->initCache();
    $this->dbCheckMock();

    $this->cache_mock->shouldReceive('get')
      ->andReturnNull();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['id'],
        [
          [$this->arch['id_parent'], '=', $this->default],
          [$this->arch['code'], '=', 'list']
        ]
      )
      ->andReturn($this->item2);

    $this->cache_mock->shouldReceive('set')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->default) . '/get_code_' . base64_encode('list'),
        $this->item2,
        0
      )
      ->andReturnTrue();

    $this->assertSame(
      $this->item2,
      $this->option->fromCode('list')
    );
  }

  /** @test */
  public function fromCode_method_returns_option_id_only_from_the_given_code_and_there_is_a_cache_set()
  {
    $this->initCache();
    $this->dbCheckMock();

    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->default) . '/get_code_' . base64_encode('list')
      )
      ->andReturn($this->item2);

    $this->assertSame(
      $this->item2,
      $this->option->fromCode('list')
    );
  }

  /** @test */
  public function fromCode_method_returns_option_id_from_given_array_of_codes()
  {
    $this->initCache();
    $this->dbCheckMock(3);

    $cache_prefix = $this->getCachePrefix();

    $this->cache_mock->shouldReceive('get')
      ->andReturnNull();

    /*
     * First method call for 'appui' and default parent
     */
    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['id'],
        [
          [$this->arch['id_parent'], '=', $this->default],
          [$this->arch['code'], '=', 'appui']
        ]
      )
      ->andReturn($this->item);

    $this->cache_mock->shouldReceive('set')
      ->with(
        "{$cache_prefix}{$this->default}/get_code_" . base64_encode('appui'),
        $this->item,
        0
      )
      ->once()
      ->andReturnTrue();

    /*
     * Second recursive method call for 'project' and id from the previous call $this->item
     */
    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['id'],
        [
          [$this->arch['id_parent'], '=', $this->item],
          [$this->arch['code'], '=', 'project']
        ]
      )
      ->andReturn($this->item2);

    $this->cache_mock->shouldReceive('set')
      ->with(
        "{$cache_prefix}".($this->getUidCacheName($this->item))."/get_code_" . base64_encode('project'),
        $this->item2,
        0
      )
      ->once()
      ->andReturnTrue();

    /*
     * Third recursive method call for 'list' and id from the previous call $this->item2
     */
    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['id'],
        [
          [$this->arch['id_parent'], '=', $this->item2],
          [$this->arch['code'], '=', 'list']
        ]
      )
      ->andReturn($this->item3);

    $this->cache_mock->shouldReceive('set')
      ->with(
        "{$cache_prefix}".($this->getUidCacheName($this->item2))."/get_code_" . base64_encode('list'),
        $this->item3,
        0
      )
      ->once()
      ->andReturnTrue();

    // The result should be the id returned from the last recursive call for 'list' and id from previous call for 'project'
    $this->assertSame(
      $this->item3,
      $this->option->fromCode(['list', 'project', 'appui'])
    );
  }

  /** @test */
  public function fromCode_method_returns_option_id_from_given_array_of_codes_when_there_is_a_cache_set()
  {
    $this->initCache();
    $this->dbCheckMock(3);

    $cache_prefix = $this->getCachePrefix();

    /*
     * First method call for 'appui' and default parent
     */
    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        "{$cache_prefix}{$this->default}/get_code_" . base64_encode('appui')
      )
      ->andReturn($this->item);

    /*
     * Second recursive method call for 'project' and id from the previous call $this->item2
     */
    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        "{$cache_prefix}".($this->getUidCacheName($this->item))."/get_code_" . base64_encode('project')
      )
      ->andReturn($this->item2);

    /*
     * Third recursive method call for 'list' and id from the previous call $this->item3
     */
    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        "{$cache_prefix}".($this->getUidCacheName($this->item2))."/get_code_" . base64_encode('list')
      )
      ->andReturn($this->item3);

    // The result should be the id returned from the last recursive call for 'list' and id from previous call for 'project'
    $this->assertSame(
      $this->item3,
      $this->option->fromCode(['list', 'project', 'appui'])
    );
  }

  /** @test */
  public function fromCode_method_returns_the_id_when_it_s_given_as_param()
  {
    $this->initCache();
    $this->dbCheckMock();

    $this->assertSame(
      $this->item,
      $this->option->fromCode(['id' => $this->item])
    );
  }

  /** @test */
  public function fromCode_method_returns_null_when_no_arguments_are_provided()
  {
    $this->initCache();
    $this->dbCheckMock();

    $this->assertNull(
      $this->option->fromCode()
    );
  }

  /** @test */
  public function fromCode_method_returns_the_default_parent_when_false_is_provided()
  {
    $this->initCache();
    $this->dbCheckMock();

    $this->assertSame(
      $this->default,
      $this->option->fromCode(false)
    );
  }

  /** @test */
  public function fromCode_method_returns_the_given_id_if_it_is_uid_and_one_argument()
  {
    $this->initCache();
    $this->dbCheckMock();

    $this->assertSame(
      $this->item,
      $this->option->fromCode($this->item)
    );
  }

  /** @test */
  public function fromCode_method_returns_the_given_id_if_it_is_uid_and_its_corresponding_parent_id_is_provided()
  {
    $this->option = \Mockery::mock(Option::class)->makePartial();

    $this->option->shouldReceive('check')
      ->andReturnTrue();

    $this->option->shouldReceive('getIdParent')
      ->once()
      ->andReturn($this->item2);

    $this->assertSame(
      $this->item,
      $this->option->fromCode($this->item, $this->item2)
    );
  }

  /** @test */
  public function fromCode_method_returns_null_when_the_provided_argument_is_not_alpha_numeric()
  {
    $this->initCache();
    $this->dbCheckMock();

    $this->assertNull(
      $this->option->fromCode((object)['foo' => 'bar'])
    );
  }

  /** @test */
  public function fromCode_method_returns_null_when_check_method_returns_false()
  {
    $this->initCache();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->option->fromCode('list', 'projects')
    );
  }

  /** @test */
  public function fromRootCode_method_returns_option_id_using_root_id_instead_of_default()
  {
    $this->option = \Mockery::mock(Option::class)->makePartial();

    $this->setNonPublicPropertyValue('root', $this->root);
    $this->setNonPublicPropertyValue('default', $this->default);

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->option->shouldReceive('setDefault')
      ->once()
      ->with($this->root)
      ->andReturnSelf();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with($this->item)
      ->andReturn($this->item2);

    $this->option->shouldReceive('setDefault')
      ->once()
      ->with($this->default)
      ->andReturnSelf();

    $this->assertSame(
      $this->item2,
      $this->option->fromRootCode($this->item)
    );
  }

  /** @test */
  public function fromRootCode_method_returns_null_when_check_method_returns_false()
  {
    $this->initCache();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->option->fromRootCode($this->item)
    );
  }

  /** @test */
  public function setValue_method_sets_the_given_value_for_the_given_id()
  {
    $this->initCache();
    $this->dbCheckMock();

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn(1);

    $this->cache_mock->shouldReceive('deleteAll')
      ->once()
      ->with($this->getCachePrefix() . $this->getUidCacheName($this->item))
      ->andReturnTrue();

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['value'] => json_encode(['a' => 'b'])],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn(1);

    $this->assertSame(1, $this->option->setValue(['a' => 'b'], $this->item));
  }

  /** @test */
  public function setValue_method_returns_null_when_check_method_returns_false()
  {
    $this->initCache();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->option->setValue(['a' => 'b'], $this->item)
    );
  }

  /** @test */
  public function setValue_method_returns_null_when_the_given_id_does_not_exist()
  {
    $this->initCache();
    $this->dbCheckMock();

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn(0);

    $this->assertNull(
      $this->option->setValue(['a' => 'b'], $this->item)
    );
  }

  /** @test */
  public function getRoot_method_returns_the_id_of_the_root_option()
  {
    $this->initCache();
    $this->dbCheckMock();

    $this->assertSame(
      $this->root,
      $this->option->getRoot()
    );
  }

  /** @test */
  public function getRoot_method_returns_null_when_check_method_returns_false()
  {
    $this->initCache();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->option->getRoot()
    );
  }

  /** @test */
  public function getDefault_method_returns_the_id_of_the_default_option()
  {
    $this->initCache();
    $this->dbCheckMock();

    $this->assertSame(
      $this->default,
      $this->option->getDefault()
    );
  }

  /** @test */
  public function getDefault_method_returns_null_when_check_method_returns_false()
  {
    $this->initCache();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->option->getDefault()
    );
  }

  /** @test */
  public function setDefault_method_sets_the_default_id_from_the_given_one_if_exists()
  {
    $this->initCache();
    $this->dbCheckMock();

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['id'] => '9999']
      )
      ->andReturn(1);

    $this->assertInstanceOf(
      Option::class,
      $this->option->setDefault('9999')
    );

    $this->assertSame(
      '9999',
      $this->getNonPublicProperty('default')
    );
  }

  /** @test */
  public function setDefault_method_does_not_set_the_default_id_if_not_exists()
  {
    $this->initCache();
    $this->dbCheckMock();

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['id'] => '9999']
      )
      ->andReturn(0);

    $this->assertInstanceOf(
      Option::class,
      $this->option->setDefault('9999')
    );

    $this->assertSame(
      $this->default,
      $this->getNonPublicProperty('default')
    );
  }

  /** @test */
  public function setDefault_method_does_not_set_the_default_id_if_check_method_returns_false()
  {
    $this->initCache();

    $this->db_mock->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertInstanceOf(
      Option::class,
      $this->option->setDefault('1111')
    );

    $this->assertSame(
      $this->default,
      $this->getNonPublicProperty('default')
    );
  }

  /** @test */
  public function items_method_returns_an_array_of_children_id_of_the_given_option_sorted_by_order()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list', 'project'])
      ->andReturn($this->item);

    $this->option->shouldReceive('cacheGet')
      ->once()
      ->with($this->item, 'items')
      ->andReturnFalse();

    $this->option->shouldReceive('getCfg')
      ->once()
      ->with($this->item)
      ->andReturn(['sortable' => true]);

    $this->db_mock->shouldReceive('getColumnValues')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['id'],
        [$this->arch['id_parent'] => $this->item],
        [
          $this->arch['num'] => 'ASC',
          $this->arch['text'] => 'ASC',
          $this->arch['code'] => 'ASC',
          $this->arch['id'] => 'ASC',
        ]
      )
      ->andReturn($expected = [$this->item2, $this->item3]);

    $this->cache_mock->shouldReceive('set')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/items',
        $expected,
        0
      )
      ->andReturnTrue();


    $this->assertSame(
      $expected,
      $this->option->items('list', 'project')
    );
  }

  /** @test */
  public function items_method_returns_an_array_of_children_id_of_the_given_option_sorted_by_text()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list', 'project'])
      ->andReturn($this->item);

    $this->option->shouldReceive('cacheGet')
      ->once()
      ->with($this->item, 'items')
      ->andReturnFalse();

    $this->option->shouldReceive('getCfg')
      ->once()
      ->with($this->item)
      ->andReturn([]);

    $this->option->shouldReceive('exists')
      ->once()
      ->with($this->item)
      ->andReturnTrue();

    $this->db_mock->shouldReceive('getColumnValues')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['id'],
        [$this->arch['id_parent'] => $this->item],
        [
          $this->arch['text'] => 'ASC',
          $this->arch['code'] => 'ASC',
          $this->arch['id'] => 'ASC',
        ]
      )
      ->andReturn($expected = [$this->item2, $this->item3]);

    $this->cache_mock->shouldReceive('set')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/items',
        $expected,
        0
      )
      ->andReturnTrue();


    $this->assertSame(
      $expected,
      $this->option->items('list', 'project')
    );
  }

  /** @test */
  public function items_method_returns_null_when_the_given_id_does_not_exist_and_does_not_have_any_config()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list', 'project'])
      ->andReturn($this->item);

    $this->option->shouldReceive('cacheGet')
      ->once()
      ->with($this->item, 'items')
      ->andReturnFalse();

    $this->option->shouldReceive('getCfg')
      ->once()
      ->with($this->item)
      ->andReturn([]);

    $this->option->shouldReceive('exists')
      ->once()
      ->with($this->item)
      ->andReturnFalse();

    $this->assertNull(
      $this->option->items('list', 'project')
    );
  }

  /** @test */
  public function items_method_returns_null_when_retrieved_id_is_not_uid()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list', 'project'])
      ->andReturn('12345');

    $this->assertNull(
      $this->option->items('list', 'project')
    );
  }

  /** @test */
  public function items_method_returns_null_when_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list', 'project'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->items('list', 'project')
    );
  }

  /** @test */
  public function items_method_returns_an_array_of_children_id_of_the_given_option_from_the_cache()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list', 'project'])
      ->andReturn($this->item);

    $this->option->shouldReceive('cacheGet')
      ->once()
      ->with($this->item, 'items')
      ->andReturn($expected = [$this->item2, $this->item3]);

    $this->assertSame($expected, $this->option->items('list', 'project'));
  }

  /** @test */
  public function nativeOption_method_returns_an_option_row_in_its_original_form_in_database()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list', 'project'])
      ->andReturn($this->item);

    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/nativeOption'
      )
      ->andReturnFalse();

    $this->db_mock->shouldReceive('tsn')
      ->once()
      ->with($this->class_cfg['table'])
      ->andREturn($this->class_cfg['table']);

    $this->db_mock->shouldReceive('cfn')
      ->once()
      ->with($this->arch['id'] ,$this->class_cfg['table'])
      ->andREturn($cfn = "{$this->class_cfg['table']}.{$this->arch['id']}");

    $this->option->shouldReceive('getRow')
      ->once()
      ->with([$cfn => $this->item])
      ->andReturn($expected = [
        'id' => $this->item,
        'code' => 'list',
        'text' => 'list',
      ]);

    $this->cache_mock->shouldReceive('set')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/nativeOption',
        $expected,
        0
      )
      ->andReturnTrue();

    $this->assertSame(
      $expected,
      $this->option->nativeOption('list', 'project')
    );
  }

  /** @test */
  public function nativeOption_method_returns_an_option_row_in_its_original_form_in_database_from_cache()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list', 'project'])
      ->andReturn($this->item);

    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/nativeOption'
      )
      ->andReturn($expected = [
        'id' => $this->item,
        'code' => 'list',
        'text' => 'list',
      ]);

    $this->assertSame(
      $expected,
      $this->option->nativeOption('list', 'project')
    );
  }

  /** @test */
  public function nativeOption_method_returns_null_when_fails_to_retrieve_option_row_from_database()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list', 'project'])
      ->andReturn($this->item);

    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/nativeOption'
      )
      ->andReturnFalse();

    $this->db_mock->shouldReceive('tsn')
      ->once()
      ->with($this->class_cfg['table'])
      ->andREturn($this->class_cfg['table']);

    $this->db_mock->shouldReceive('cfn')
      ->once()
      ->with($this->arch['id'] ,$this->class_cfg['table'])
      ->andREturn($cfn = "{$this->class_cfg['table']}.{$this->arch['id']}");

    $this->option->shouldReceive('getRow')
      ->once()
      ->with([$cfn => $this->item])
      ->andReturnNull();

    $this->assertNull(
      $this->option->nativeOption('list', 'project')
    );
  }

  /** @test */
  public function nativeOption_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list', 'project'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->nativeOption('list', 'project')
    );
  }

  /** @test */
  public function nativeOptions_returns_option_rows_in_its_original_form_in_database_for_the_given_code_childs()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item)
      ->andReturn($result = [$this->item2, $this->item3]);

    $expected = [];

    foreach ($result as $item) {
      $this->option->shouldReceive('nativeOption')
        ->once()
        ->with($item)
        ->andReturn($expected[] = [
          'id' => $item,
          'code' => 'list',
          'text' => 'list',
        ]);
    }

    $this->assertSame($expected, $this->option->nativeOptions('list'));
  }

  /** @test */
  public function nativeOptions_method_returns_an_empty_array_when_the_given_code_has_no_items()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertSame([], $this->option->nativeOptions('list'));
  }

  /** @test */
  public function nativeOptions_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->nativeOptions('list')
    );
  }

  /** @test */
  public function rawOption_method_returns_an_option_row_in_its_original_form_in_database_including_cfg()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

      $this->db_mock->shouldReceive('rselect')
        ->once()
        ->with(
          $this->class_cfg['table'], [], [$this->arch['id'] => $this->item]
        )
        ->andReturn($expected = [
          'id' => $this->item,
          'code' => 'list',
          'text' => 'list',
          'cfg' => null
        ]);

      $this->assertSame(
        $expected,
        $this->option->rawOption('list')
      );
  }

  /** @test */
  public function rawOption_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->rawOption('list')
    );
  }

  /** @test */
  public function rawOptions_method_returns_option_items_as_stored_in_database_in_its_original_form()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item)
      ->andREturn($result = [$this->item2, $this->item3]);

    $expected = [];
    foreach ($result as $item) {
      $this->db_mock->shouldReceive('rselect')
        ->once()
        ->with(
          $this->class_cfg['table'], [], [$this->arch['id'] => $item]
        )
        ->andREturn($expected[] = [
          'id' => $item,
          'code' => 'list',
          'text' => 'list',
          'cfg' => null
        ]);
    }

    $this->assertSame(
      $expected,
      $this->option->rawOptions('list')
    );
  }

  /** @test */
  public function rawOptions_method_returns_empty_array_when_the_given_code_does_not_have_items()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertSame([], $this->option->rawOptions('list'));
  }

  /** @test */
  public function rawOptions_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->rawOptions('list')
    );
  }

  /** @test */
  public function rawTree_method_returns_an_option_tree_structure_as_stored_in_database_with_its_items()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('rawOption')
      ->once()
      ->with($this->item)
      ->andReturn($expected = [
        'id' => $this->item,
        'code' => 'list',
        'text' => 'list',
        'cfg' => null
      ]);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item)
      ->andReturn($result = [$this->item2, $this->item3]);

    foreach ($result as $item) {
      // Recursive calls
      $this->option->shouldReceive('fromCode')
        ->once()
        ->with([$item])
        ->andReturn($item);

      $this->option->shouldReceive('rawOption')
        ->once()
        ->with($item)
        ->andReturn($expected['items'][] = [
          'id' => $item,
          'code' => 'list',
          'text' => 'list',
          'cfg' => null
        ]);

      $this->option->shouldReceive('items')
        ->once()
        ->with($item)
        ->andReturnNull();
    }

    $this->assertSame(
      $expected,
      $this->option->rawTree('list')
    );
  }

  /** @test */
  public function rawTree_method_returns_an_option_tree_structure_as_stored_in_database_with_no_items_if_it_does_not_have_any()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('rawOption')
      ->once()
      ->with($this->item)
      ->andReturn($expected = [
        'id' => $this->item,
        'code' => 'list',
        'text' => 'list',
        'cfg' => null
      ]);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertSame(
      $expected,
      $this->option->rawTree('list')
    );
  }

  /** @test */
  public function rawTree_method_returns_null_when_it_fails_to_get_the_raw_option_for_the_given_code()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('rawOption')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertNull(
      $this->option->rawTree('list')
    );
  }

  /** @test */
  public function rawTree_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->rawTree('list')
    );
  }
}