<?php

namespace Appui;

use bbn\Appui\History;
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
        'tbl_options' => 't_bbn_options'
      ],
      'arch' => [
        'tbl_options' => [
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

    $this->option->__construct($this->db_mock, $this->class_cfg);

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
        'tbl_options' => 'bbn_options_2'
      ],
      'table' => 'bbn_options_2',
      'arch' => [
        'tbl_options' => [
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
      ['options' => 'bbn_options', 'tbl_options' => 'bbn_options_2'],
      $this->getNonPublicProperty('class_cfg', $option)['tables']
    );

    $this->assertSame(
      'tbl_options',
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
      $this->option->fromCode([$this->arch['id'] => $this->item])
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
  public function fromCode_method_returns_null_when_no_null_is_provided()
  {
    $this->initCache();
    $this->dbCheckMock();

    $this->assertNull(
      $this->option->fromCode(null)
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
    $this->mockOptionClass();

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
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'list',
        $this->arch['text'] => 'list',
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
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'list',
        $this->arch['text'] => 'list',
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
          $this->arch['id'] => $item,
          $this->arch['code'] => 'list',
          $this->arch['text'] => 'list',
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
          $this->arch['id'] => $this->item,
          $this->arch['code'] => 'list',
          $this->arch['text'] => 'list',
          $this->arch['cfg'] => null
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
          $this->arch['id'] => $this->item,
          $this->arch['code'] => 'list',
          $this->arch['text'] => 'list',
          $this->arch['cfg'] => null
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
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'list',
        $this->arch['text'] => 'list',
        $this->arch['cfg'] => null
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
          $this->arch['id'] => $this->item,
          $this->arch['code'] => 'list',
          $this->arch['text'] => 'list',
          $this->arch['cfg'] => null
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
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'list',
        $this->arch['text'] => 'list',
        $this->arch['cfg'] => null
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

  /** @test */
  public function optionNoAlias_method_returns_an_option_full_content_as_array_without_its_values()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list', 'project'])
      ->andReturn($this->item);

    $this->option->shouldReceive('nativeOption')
      ->once()
      ->with($this->item)
      ->andReturn([
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'list',
        $this->arch['text'] => 'list',
        $this->arch['value'] => json_encode([
          'prop1' => 'value1',
          'prop2' => 'value2',
          $this->arch['text'] => 'some_text' // Should not be added
        ])
      ]);

    $this->assertSame(
      [
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'list',
        $this->arch['text'] => 'list',
        'prop1'  => 'value1',
        'prop2'  => 'value2',
      ],
      $this->option->optionNoAlias('list', 'project')
    );
  }

  /** @test */
  public function optionNoAlias_method_returns_null_when_failed_to_retrieve_nativeOption()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list', 'project'])
      ->andReturn($this->item);

    $this->option->shouldReceive('nativeOption')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertNull(
      $this->option->optionNoAlias('list', 'project')
    );
  }

  /** @test */
  public function optionNoAlias_method_returns_null_when_the_given_codes_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list', 'project'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->optionNoAlias('list', 'project')
    );
  }

  /** @test */
  public function getValue_method_returns_the_value_for_the_given_codes()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->with(['list'])
      ->once()
      ->andReturn($this->item);

    $this->option->shouldReceive('nativeOption')
      ->with($this->item)
      ->once()
      ->andReturn([
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'list',
        $this->arch['text'] => 'list',
        $this->arch['value'] => json_encode([
          'prop1' => 'value1',
          'prop2' => 'value2',
          $this->arch['text'] => 'some_text'
        ])
      ]);

    $this->assertSame(
      [
        'prop1' => 'value1',
        'prop2' => 'value2',
        $this->arch['text'] => 'some_text'
      ],
      $this->option->getValue('list'));
  }

  /** @test */
  public function getValue_method_returns_null_when_the_value_is_not_json()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('nativeOption')
      ->with($this->item)
      ->once()
      ->andReturn([
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'list',
        $this->arch['text'] => 'list',
        $this->arch['value'] => 'Hello world!'
      ]);

    $this->assertNull(
      $this->option->getValue('list')
    );
  }

  /** @test */
  public function getValue_method_returns_null_when_the_value_is_empty()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('nativeOption')
      ->with($this->item)
      ->once()
      ->andReturn([
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'list',
        $this->arch['text'] => 'list',
        $this->arch['value'] => ''
      ]);

    $this->assertNull(
      $this->option->getValue('list')
    );
  }

  /** @test */
  public function getValue_method_returns_null_when_failed_to_retrieve_native_option()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('nativeOption')
      ->with($this->item)
      ->once()
      ->andReturnNull();

    $this->assertNull(
      $this->option->getValue('list')
    );
  }

  /** @test */
  public function getValue_method_returns_null_when_the_given_codes_dont_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->getValue('list')
    );
  }

  /** @test */
  public function option_method_returns_option_full_content_as_an_array()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('nativeOption')
      ->with($this->item)
      ->once()
      ->andReturn([
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'list',
        $this->arch['text'] => 'list',
        $this->arch['id_alias'] => $this->item2,
        $this->arch['value'] => json_encode([
          'prop1' => 'value1',
          'prop2' => 'value2',
          $this->arch['text'] => 'some_text'
        ])
      ]);

    $this->option->shouldReceive('nativeOption')
      ->once()
      ->with($this->item2)
      ->andReturn([
        $this->arch['id'] => $this->item2,
        $this->arch['code'] => 'alias',
        $this->arch['text'] => 'alias',
        $this->arch['value'] => json_encode([
          'prop3' => 'value3',
          'prop4' => 'value4',
          $this->arch['text'] => 'some_text_again'
        ])
      ]);

    $this->assertSame(
      [
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'list',
        $this->arch['text'] => 'list',
        $this->arch['id_alias'] => $this->item2,
        'prop1' => 'value1',
        'prop2' => 'value2',
        'alias' => [
          $this->arch['id'] => $this->item2,
          $this->arch['code'] => 'alias',
          $this->arch['text'] => 'alias',
          'prop3' => 'value3',
          'prop4' => 'value4'
        ]
      ],
      $this->option->option('list')
    );
  }

  /** @test */
  public function option_method_throws_an_exception_when_id_alias_is_same_as_id()
  {
    $this->expectException(\Exception::class);
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('nativeOption')
      ->with($this->item)
      ->once()
      ->andReturn([
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'list',
        $this->arch['text'] => 'list',
        $this->arch['id_alias'] => $this->item
      ]);

    $this->option->shouldReceive('nativeOption')
      ->with($this->item)
      ->once()
      ->andReturn([
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'list',
        $this->arch['text'] => 'list',
        $this->arch['id_alias'] => $this->item
      ]);

    $this->option->option('list');
  }

  /** @test */
  public function option_method_does_not_add_alias_key_when_failed_to_retrieve_alias_native_option()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('nativeOption')
      ->with($this->item)
      ->once()
      ->andReturn($expected = [
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'list',
        $this->arch['text'] => 'list',
        $this->arch['id_alias'] => $this->item2
      ]);

    $this->option->shouldReceive('nativeOption')
      ->with($this->item2)
      ->once()
      ->andReturnNull();

    $this->assertSame(
      $expected,
      $this->option->option('list')
    );
  }

  /** @test */
  public function option_method_does_not_add_alias_key_when_id_alias_is_not_uid()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('nativeOption')
      ->with($this->item)
      ->once()
      ->andReturn($expected = [
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'list',
        $this->arch['text'] => 'list',
        $this->arch['id_alias'] => '12345'
      ]);

    $this->assertSame(
      $expected,
      $this->option->option('list')
    );
  }

  /** @test */
  public function option_method_returns_null_when_failed_to_retrieve_native_option_for_the_given_code()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('nativeOption')
      ->with($this->item)
      ->once()
      ->andReturnNull();

    $this->assertNull(
      $this->option->option('list')
    );
  }

  /** @test */
  public function option_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->option('list')
    );
  }

  /** @test */
  public function opAlias_method_returns_the_merge_between_an_option_and_its_alias_as_an_array()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('nativeOption')
      ->once()
      ->with($this->item)
      ->andReturn([
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'list',
        $this->arch['text'] => 'list',
        $this->arch['id_alias'] => $this->item2,
        $this->arch['value'] =>json_encode([
          'prop1' => 'value1',
          'prop2' => 'value2',
          $this->arch['text'] => 'text'
        ])
      ]);

    $this->option->shouldReceive('nativeOption')
      ->once()
      ->with($this->item2)
      ->andReturn([
        $this->arch['id'] => $this->item2,
        $this->arch['code'] => 'alias',
        $this->arch['text'] => 'alias',
        $this->arch['value'] =>json_encode([
          'prop3' => 'value3',
          'prop4' => 'value4',
          $this->arch['text'] => 'text'
        ])
      ]);

    $this->assertSame(
      [
        $this->arch['id'] => $this->item2,
        $this->arch['code'] => 'alias',
        $this->arch['text'] => 'alias',
        $this->arch['id_alias'] => $this->item2,
        'prop1' => 'value1',
        'prop2' => 'value2',
        'prop3' => 'value3',
        'prop4' => 'value4',
      ],
      $this->option->opAlias('list')
    );
  }

  /** @test */
  public function opAlias_method_throws_an_exception_when_id_alias_is_same_as_id()
  {
    $this->expectException(\Exception::class);
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('nativeOption')
      ->once()
      ->with($this->item)
      ->andReturn([
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'list',
        $this->arch['text'] => 'list',
        $this->arch['id_alias'] => $this->item
      ]);

    $this->option->shouldReceive('nativeOption')
      ->once()
      ->with($this->item)
      ->andReturn([
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'alias',
        $this->arch['text'] => 'alias'
      ]);

    $this->option->opAlias('list');
  }

  /** @test */
  public function opAlias_method_does_not_merge_option_with_its_alias_if_failed_to_retrieve_alias_native_option()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('nativeOption')
      ->once()
      ->with($this->item)
      ->andReturn([
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'list',
        $this->arch['text'] => 'list',
        $this->arch['id_alias'] => $this->item2,
        $this->arch['value'] =>json_encode([
          'prop1' => 'value1',
          'prop2' => 'value2',
          $this->arch['text'] => 'text'
        ])
      ]);

    $this->option->shouldReceive('nativeOption')
      ->once()
      ->with($this->item2)
      ->andReturnNull();

    $this->assertSame(
      [
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'list',
        $this->arch['text'] => 'list',
        $this->arch['id_alias'] => $this->item2,
        'prop1' => 'value1',
        'prop2' => 'value2',
      ],
      $this->option->opAlias('list')
    );
  }

  /** @test */
  public function opAlias_method_does_not_merge_option_with_its_alias_when_id_alias_is_not_uid()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('nativeOption')
      ->once()
      ->with($this->item)
      ->andReturn([
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'list',
        $this->arch['text'] => 'list',
        $this->arch['id_alias'] => '12345',
        $this->arch['value'] =>json_encode([
          'prop1' => 'value1',
          'prop2' => 'value2',
          $this->arch['text'] => 'text'
        ])
      ]);

    $this->assertSame(
      [
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'list',
        $this->arch['text'] => 'list',
        $this->arch['id_alias'] => '12345',
        'prop1' => 'value1',
        'prop2' => 'value2',
      ],
      $this->option->opAlias('list')
    );
  }

  /** @test */
  public function opAlias_method_returns_null_when_failed_to_retrieve_native_option_for_the_given_code()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('nativeOption')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertNull(
      $this->option->opAlias('list')
    );
  }

  /** @test */
  public function opAlias_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->opAlias('list')
    );
  }

  /** @test */
  public function options_method_returns_an_array_of_options_in_the_form_of_id_and_text()
  {
    $this->mockOptionClass();

    $this->db_mock = \Mockery::mock(Db::class)->makePartial();
    $this->setNonPublicPropertyValue(
      'language',
      \Mockery::mock(Db\Languages\Mysql::class)->makePartial(),
      $this->db_mock
    );
    $this->setNonPublicPropertyValue('db', $this->db_mock);

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

      $this->cache_mock->shouldReceive('get')
        ->once()
        ->with(
          $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/options'
        )
        ->andReturnFalse();

      $this->db_mock->shouldReceive('selectAllByKeys')
        ->once()
        ->with([
          'tables' => [$this->class_cfg['table']],
          'fields' => [
            'id' => "{$this->class_cfg['table']}.{$this->arch['id']}",
            'text' => "IFNULL(`{$this->class_cfg['table']}`.`{$this->arch['text']}`, `alias`.`{$this->arch['text']}`)"
          ],
          'join' => [
            [
              'table' => $this->class_cfg['table'],
              'alias' => 'alias',
              'type'  => 'LEFT',
              'on'    => [
                [
                  'field' => $this->class_cfg['table'].'.'.$this->arch['id_alias'],
                  'exp'   => 'alias.'.$this->arch['id']
                ]
              ]
            ]
          ],
          'where' => [$this->class_cfg['table'].'.'.$this->arch['id_parent'] => $this->item],
          'order' => ['text' => 'ASC']
        ])
        ->andReturn($expected = [
          $this->item2 => 'text1',
          $this->item3 => 'text2',
        ]);

      $this->cache_mock->shouldReceive('set')
        ->once()
        ->with(
          $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/options',
          $expected,
          0
        )
        ->andReturnTrue();

      $this->assertSame(
        $expected,
        $this->option->options('list')
      );
  }

  /** @test */
  public function options_method_returns_an_array_of_options_in_the_form_of_id_and_text_from_the_cache()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/options'
      )
      ->andReturn($expected = [
        $this->item2 => 'text1',
        $this->item3 => 'text2',
      ]);

    $this->assertSame(
      $expected,
      $this->option->options('list')
    );
  }

  /** @test */
  public function options_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->options('list')
    );
  }

  /** @test */
  public function optionsByCode_method_returns_an_array_of_children_options_in_the_form_of_code_text()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/optionsByCode'
      )
      ->andReturnFalse();

    $this->db_mock->shouldReceive('selectAllByKeys')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['code'], $this->arch['text']],
        [$this->arch['id_parent'] => $this->item],
        [$this->arch['text'] => 'ASC']
      )
      ->andReturn($expected = [
        'project1' => 'text1',
        'project2' => 'text2'
      ]);

    $this->cache_mock->shouldReceive('set')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/optionsByCode',
        $expected,
        0
      )
      ->andReturnTrue();

    $this->assertSame(
      $expected,
      $this->option->optionsByCode('list')
    );
  }

  /** @test */
  public function optionsByCode_method_returns_an_array_of_children_options_in_the_form_of_code_text_from_cache()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/optionsByCode'
      )
      ->andReturn($expected = [
        'project1' => 'text1',
        'project2' => 'text2'
      ]);

    $this->assertSame(
      $expected,
      $this->option->optionsByCode('list')
    );
  }

  /** @test */
  public function optionsByCode_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->optionsByCode('list')
    );
  }

  /** @test */
  public function textValueOptions_method_returns_an_options_children_array_of_id_and_text_in_a_defined_indexed_array()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fullOptions')
      ->times(3)
      ->with('list')
      ->andReturn([
        ['id' => $this->item2, 'text' => 'text1', 'code' => 'project1'],
        ['id' => $this->item3, 'text' => 'text2', 'code' => 'project2'],
        ['id' => $this->item4, 'text' => 'text3', 'code' => 'project3'],
      ]);

    // With cfg has not show_code
    $this->option->shouldReceive('getCfg')
      ->once()
      ->with('list')
      ->andReturn(['a' => 'b']);

    $this->assertSame(
      [
        ['text' => 'text1', 'value' => $this->item2],
        ['text' => 'text2', 'value' => $this->item3],
        ['text' => 'text3', 'value' => $this->item4],
      ],
      $this->option->textValueOptions('list')
    );

    // with cfg has show_code = false
    $this->option->shouldReceive('getCfg')
      ->once()
      ->with('list')
      ->andReturn(['show_code' => false]);

    $this->assertSame(
      [
        ['my_text' => 'text1', 'my_value' => $this->item2],
        ['my_text' => 'text2', 'my_value' => $this->item3],
        ['my_text' => 'text3', 'my_value' => $this->item4],
      ],
      $this->option->textValueOptions('list', 'my_text', 'my_value')
    );

    // with cfg show_code = true
    $this->option->shouldReceive('getCfg')
      ->once()
      ->with('list')
      ->andReturn(['show_code' => true]);


    $this->assertSame(
      [
        ['my_text' => 'text1', 'my_value' => $this->item2, 'code' => 'project1'],
        ['my_text' => 'text2', 'my_value' => $this->item3, 'code' => 'project2'],
        ['my_text' => 'text3', 'my_value' => $this->item4, 'code' => 'project3'],
      ],
      $this->option->textValueOptions('list', 'my_text', 'my_value')
    );
  }

  /** @test */
  public function textValueOptions_method_returns_empty_result_array_when_it_has_empty_options()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fullOptions')
      ->once()
      ->with('list')
      ->andReturnNull();

    $this->assertSame([], $this->option->textValueOptions('list'));

    $this->option->shouldReceive('fullOptions')
      ->once()
      ->with('list')
      ->andReturn([]);

    $this->assertSame([], $this->option->textValueOptions('list'));
  }

  /** @test */
  public function siblings_method_returns_full_options_for_the_items_with_same_parent_from_the_given_code()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with('list')
      ->andReturn($this->item);

    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item)
      ->andReturn($this->item2);

    // The parent is $this->item2
    $this->option->shouldReceive('fullOptions')
      ->once()
      ->with($this->item2)
      ->andReturn([
        0 => ['id' => $this->item, 'code' => 'project1'],
        1 => ['id' => $this->item3, 'code' => 'project2'],
        2 => ['id' => $this->item4, 'code' => 'project3'],
      ]);

    $this->assertSame(
      [
        1 => ['id' => $this->item3, 'code' => 'project2'],
        2 => ['id' => $this->item4, 'code' => 'project3'],
      ],
      $this->option->siblings('list')
    );
  }

  /** @test */
  public function siblings_method_returns_null_when_failed_to_get_the_parent_id_of_the_given_code()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with('list')
      ->andReturn($this->item);

    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertNull(
      $this->option->siblings('list')
    );
  }

  /** @test */
  public function siblings_method_returns_null_when_failed_to_get_full_options_of_the_parent()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with('list')
      ->andReturn($this->item);

    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item)
      ->andReturn($this->item);

    $this->option->shouldReceive('fullOptions')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertNull(
      $this->option->siblings('list')
    );
  }

  /** @test */
  public function siblings_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with('list')
      ->andReturnNull();

    $this->assertNull(
      $this->option->siblings('list')
    );
  }

  /** @test */
  public function fullOptions_method_returns_an_array_of_full_options_for_the_given_parent()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item)
      ->andReturn($items = [$this->item2, $this->item3]);

    $expected = [];
    foreach ($items as $item) {
      $this->option->shouldReceive('option')
        ->once()
        ->with($item)
        ->andReturn($expected[] = [
          'id' => $item,
          'code' => 'list',
          'text' => 'text'
        ]);
    }

    $this->assertSame(
      $expected,
      $this->option->fullOptions('list')
    );
  }

  /** @test */
  public function fullOptions_method_throws_an_exception_when_of_the_items_has_no_option()
  {
    $this->expectException(\Exception::class);
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item)
      ->andReturn([$this->item2, $this->item3]);

    $this->option->shouldReceive('option')
      ->once()
      ->with($this->item2)
      ->andReturn([
        'id' => $this->item2,
        'code' => 'list',
        'text' => 'text'
      ]);

    $this->option->shouldReceive('option')
      ->once()
      ->with($this->item3)
      ->andReturnNull();

    $this->option->fullOptions('list');
  }

  /** @test */
  public function fullOptions_method_returns_empty_array_when_the_given_code_has_no_items()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item)
      ->andReturn([]);

    $this->assertSame([], $this->option->fullOptions('list'));
  }

  /** @test */
  public function fullOptions_method_returns_null_when_failed_to_get_item_for_the_given_code()
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

    $this->assertNull(
      $this->option->fullOptions('list')
    );
  }

  /** @test */
  public function fullOptions_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->fullOptions('list')
    );
  }

  /** @test */
  public function fullOptionsRef_method_returns_each_individual_full_option_plus_the_children_of_options_and_aliases()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('fullOptions')
      ->once()
      ->with($this->item)
      ->andReturn($expected = [
        ['id' => $this->item2, 'code' => 'project1'],
        ['id' => $this->item3, 'code' => 'project2'],
      ]);

    $this->option->shouldReceive('getAliases')
      ->once()
      ->with($this->item)
      ->andReturn([
        ['id' => $this->item4],
        ['id' => $this->item5]
      ]);

    $this->option->shouldReceive('fullOptions')
      ->once()
      ->with($this->item4)
      ->andReturn([
        $expected[] = ['id' => '1', 'code' => 'project3'],
        $expected[] = ['id' => '2', 'code' => 'project4']
      ]);

    $this->option->shouldReceive('fullOptions')
      ->once()
      ->with($this->item5)
      ->andReturnNull();

    $this->assertSame(
      $expected,
      $this->option->fullOptionsRef('list')
    );
  }

  /** @test */
  public function fullOptionsRef_method_returns_only_aliases_options_if_failed_to_retrieve_the_give_code_full_options()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('fullOptions')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->option->shouldReceive('getAliases')
      ->once()
      ->with($this->item)
      ->andReturn([
        ['id' => $this->item2]
      ]);

    $this->option->shouldReceive('fullOptions')
      ->once()
      ->with($this->item2)
      ->andReturn($expected = [
        ['id' => '1', 'code' => 'project3']
      ]);

    $this->assertSame(
      $expected,
      $this->option->fullOptionsRef('list')
    );
  }

  /** @test */
  public function fullOptionsRef_method_does_not_return_aliases_options_if_failed_to_retrieve_aliases_for_the_given_code()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('fullOptions')
      ->once()
      ->with($this->item)
      ->andReturn($expected = [
        ['id' => $this->item2, 'code' => 'project1']
      ]);

    $this->option->shouldReceive('getAliases')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertSame(
      $expected,
      $this->option->fullOptionsRef('list')
    );
  }

  /** @test */
  public function fullOptionsRef_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->fullOptionsRef('list')
    );
  }

  /** @test */
  public function optionsRef_method_individual_option_plus_the_children_of_options_and_aliases()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('options')
      ->once()
      ->with($this->item)
      ->andReturn($expected = [
        [$this->item2 => 'text_2'],
        [$this->item3 => 'text_3'],
      ]);

    $this->option->shouldReceive('getAliases')
      ->once()
      ->with($this->item)
      ->andReturn([
        ['id' => $this->item4],
        ['id' => $this->item5]
      ]);

    $this->option->shouldReceive('options')
      ->once()
      ->with($this->item4)
      ->andReturn([
        $expected[] = [$this->item4 => 'text_4']
      ]);

    $this->option->shouldReceive('options')
      ->once()
      ->with($this->item5)
      ->andReturnNull();

    $this->assertSame(
      $expected,
      $this->option->optionsRef('list')
    );
  }

  /** @test */
  public function optionsRef_method_returns_only_aliases_options_if_failed_to_retrieve_the_give_code_full_options()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('options')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->option->shouldReceive('getAliases')
      ->once()
      ->with($this->item)
      ->andReturn([
        ['id' => $this->item2]
      ]);

    $this->option->shouldReceive('options')
      ->once()
      ->with($this->item2)
      ->andReturn($expected = [
        ['1' => 'text']
      ]);

    $this->assertSame(
      $expected,
      $this->option->optionsRef('list')
    );
  }

  /** @test */
  public function optionsRef_method_does_not_return_aliases_options_if_failed_to_retrieve_aliases_for_the_given_code()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('options')
      ->once()
      ->with($this->item)
      ->andReturn($expected = [
        [$this->item2 => 'text']
      ]);

    $this->option->shouldReceive('getAliases')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertSame(
      $expected,
      $this->option->optionsRef('list')
    );
  }

  /** @test */
  public function optionsRef_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->optionsRef('list')
    );
  }

  /** @test */
  public function itemsRef_method_returns_each_individual_options_plus_the_children_of_options_and_aliases()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item)
      ->andReturn($expected = [$this->item2, $this->item3]);

    $this->option->shouldReceive('getAliases')
      ->once()
      ->with($this->item)
      ->andReturn([
        ['id' => $this->item4],
        ['id' => $this->item5]
      ]);

    $this->option->shouldReceive('items')
      ->once()
      ->with(['id' => $this->item4])
      ->andReturn([
        $expected[] = $this->item4,
        $expected[] = $this->item5,
      ]);

    $this->option->shouldReceive('items')
      ->once()
      ->with(['id' => $this->item5])
      ->andReturnNull();

    $this->assertSame(
      $expected,
      $this->option->itemsRef('list')
    );
  }

  /** @test */
  public function itemsRef_method_returns_only_aliases_options_if_failed_to_retrieve_the_give_code_full_options()
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

    $this->option->shouldReceive('getAliases')
      ->once()
      ->with($this->item)
      ->andReturn([
        ['id' => $this->item2]
      ]);

    $this->option->shouldReceive('items')
      ->once()
      ->with(['id' => $this->item2])
      ->andReturn([
        $expected[] = $this->item3
      ]);

    $this->assertSame(
      $expected,
      $this->option->itemsRef('list')
    );
  }

  /** @test */
  public function itemsRef_method_does_not_return_aliases_options_if_failed_to_retrieve_aliases_for_the_given_code()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item)
      ->andReturn($expected = [
        $this->item2
      ]);

    $this->option->shouldReceive('getAliases')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertSame(
      $expected,
      $this->option->itemsRef('list')
    );
  }

  /** @test */
  public function itemsRef_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->itemsRef('list')
    );
  }

  /** @test */
  public function codeOptions_method_returns_an_array_of_full_options_arrays_for_the_given_parent()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item)
      ->andReturn($items = [$this->item2, $this->item3]);

    $expected = [];
    foreach ($items as $key => $item) {
      $this->option->shouldReceive('option')
        ->once()
        ->with($item)
        ->andReturn($expected["code_$key"] = [
          'id' => $item,
          'code' => "code_$key",
          'text' => "text_$key"
        ]);
    }

    $this->assertSame(
      $expected,
      $this->option->codeOptions('list')
    );
  }

  /** @test */
  public function codeOptions_method_returns_null_when_failed_to_retrieve_items()
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

    $this->assertNull(
      $this->option->codeOptions('list')
    );
  }

  /** @test */
  public function codeOptions_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->codeOptions('list')
    );
  }

  /** @test */
  public function codeIds_method_returns_an_array_of_id_arrays_for_the_given_parent()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item)
      ->andReturn($items = [$this->item2, $this->item3]);

    $expected = [];
    foreach ($items as $key => $item) {
      $this->option->shouldReceive('option')
        ->once()
        ->with($item)
        ->andReturn($option = [
          'id' => $item,
          'code' => "code_$key",
          'text' => "text_$key"
        ]);

      $expected[$option['code']] = $option['id'];
    }

    $this->assertSame(
      $expected,
      $this->option->codeIds('list')
    );
  }

  /** @test */
  public function codeIds_method_returns_null_when_failed_to_retrieve_items()
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

    $this->assertNull(
      $this->option->codeIds('list')
    );
  }

  /** @test */
  public function codeIds_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->codeIds('list')
    );
  }

  /** @test */
  public function getAliases_method_returns_aliases_for_given_code()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [],
        [$this->arch['id_alias'] => $this->item]
      )
      ->andReturn([[
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'list',
        $this->arch['text'] => 'list',
        $this->arch['value'] => json_encode([
          'prop1' => 'value1',
          'prop2' => 'value2',
          $this->arch['text'] => 'some_text'
        ])
      ]]);

    $this->assertSame(
      [[
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'list',
        $this->arch['text'] => 'list',
        'prop1' => 'value1',
        'prop2' => 'value2'
      ]],
      $this->option->getAliases('list')
    );
  }

  /** @test */
  public function getAliases_method_returns_empty_array_when_failed_to_fetch_data_from_database()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [],
        [$this->arch['id_alias'] => $this->item]
      )
      ->andReturnNull();

    $this->assertSame(
      [],
      $this->option->getAliases('list')
    );
  }

  /** @test */
  public function getAliases_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->getAliases('list')
    );
  }

  /** @test */
  public function getAliasItems_method_returns_an_array_alias_items_id_for_the_given_code()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getAliasItems'
      )
      ->andReturnFalse();

    $this->db_mock->shouldReceive('getColumnValues')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['id'],
        [$this->arch['id_alias'] => $this->item]
      )
      ->andReturn($expected = [$this->item2, $this->item3]);

    $this->cache_mock->shouldReceive('set')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getAliasItems',
        $expected,
        0
      )
      ->andReturnTrue();

    $this->assertSame(
      $expected,
      $this->option->getAliasItems('list')
    );
  }

  /** @test */
  public function getAliasItems_method_returns_an_array_alias_items_id_for_the_given_code_from_the_cache()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getAliasItems'
      )
      ->andReturn($expected = [$this->item2, $this->item3]);

    $this->assertSame(
      $expected,
      $this->option->getAliasItems('list')
    );
  }

  /** @test */
  public function getAliasItems_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->getAliasItems('list')
    );
  }

  /** @test */
  public function getAliasOptions_method_returns_an_array_of_aliases_options_for_the_given_code()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getAliasOptions'
      )
      ->andReturnFalse();

    $this->option->shouldReceive('getAliasItems')
      ->once()
      ->with($this->item)
      ->andReturn($alias_items = [$this->item2, $this->item3]);

    $expected = [];
    foreach ($alias_items as $key => $item) {
      $this->option->shouldReceive('text')
        ->once()
        ->with($item)
        ->andReturn($expected[$item] = "text_$key");
    }

    $this->cache_mock->shouldReceive('set')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getAliasOptions',
        $expected,
        0
      )
      ->andReturnTrue();

    $this->assertSame(
      $expected,
      $this->option->getAliasOptions('list')
    );
  }

  /** @test */
  public function getAliasOptions_method_returns_an_array_of_aliases_options_for_the_given_code_from_cache()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->cache_mock->shouldReceive('get')
    ->once()
    ->with(
      $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getAliasOptions'
    )
    ->andReturn($expected = [$this->item2 => 'some_text']);

    $this->assertSame(
      $expected,
      $this->option->getAliasOptions('list')
    );
  }

  /** @test */
  public function getAliasOptions_method_returns_empty_array_when_no_alias_items_found()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getAliasOptions'
      )
      ->andReturnFalse();

    $this->option->shouldReceive('getAliasItems')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->cache_mock->shouldReceive('set')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getAliasOptions',
        [],
        0
      )
      ->andReturnTrue();

    $this->assertSame(
      [],
      $this->option->getAliasOptions('list')
    );
  }

  /** @test */
  public function getAliasOptions_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->getAliasOptions('list')
    );
  }

  /** @test */
  public function getAliasFullOptions_method_returns_alias_full_options_for_the_given_id()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getAliasFullOptions'
      )
      ->andReturnFalse();

    $this->option->shouldReceive('getAliasItems')
      ->once()
      ->with($this->item)
      ->andReturn($alias_items = [$this->item2, $this->item3]);

    $expected = [];
    foreach ($alias_items as $key => $item) {
      $this->option->shouldReceive('option')
        ->once()
        ->with($item)
        ->andReturn($expected[] = [
          'id' => $item,
          'code' => "code_$key",
          'text' => "text_$key"
        ]);
    }

    $this->cache_mock->shouldReceive('set')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getAliasFullOptions',
        $expected,
        0
      )
      ->andReturnTrue();

    $this->assertSame(
      $expected,
      $this->option->getAliasFullOptions('list')
    );
  }

  /** @test */
  public function getAliasFullOptions_method_returns_alias_full_options_for_the_given_id_from_cache()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getAliasFullOptions'
      )
      ->andReturn($expected = [[
        'id' => $this->item,
        'code' => "some_code",
        'text' => "some_text"
      ]]);

    $this->assertSame(
      $expected,
      $this->option->getAliasFullOptions('list')
    );
  }

  /** @test */
  public function getAliasFullOptions_method_returns_empty_array_when_fails_to_retrieve_alias_items()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getAliasFullOptions'
      )
      ->andReturnFalse();

    $this->option->shouldReceive('getAliasItems')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->cache_mock->shouldReceive('set')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getAliasFullOptions',
        [],
        0
      )
      ->andReturnTrue();

    $this->assertSame(
      [],
      $this->option->getAliasFullOptions('list')
    );
  }

  /** @test */
  public function getAliasFullOptions_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->getAliasFullOptions('list')
    );
  }

  /** @test */
  public function fullOptionsById_method_returns_an_id_indexed_array_of_full_options_for_a_given_parent()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fullOptions')
      ->once()
      ->with(['list'])
      ->andReturn([
        $expected1 = [
          $this->arch['id'] => $this->item2,
          $this->arch['id_parent'] => $this->item,
          $this->arch['code'] => 'some_code'
        ],
        $expected2 = [
          $this->arch['id'] => $this->item3,
          $this->arch['id_parent'] => $this->item,
          $this->arch['code'] => 'some_code'
        ],
      ]);

    $this->assertSame(
      [
        $expected1[$this->arch['id']] => $expected1,
        $expected2[$this->arch['id']] => $expected2,
      ],
      $this->option->fullOptionsById('list')
    );
  }

  /** @test */
  public function fullOptionsById_method_returns_null_when_fails_to_retrieve_full_options()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fullOptions')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->fullOptionsById('list')
    );
  }

  /** @test */
  public function fullOptionsByCode_method_returns_a_code_indexed_array_of_full_options_for_the_given_parent()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fullOptions')
      ->once()
      ->with(['list'])
      ->andReturn([
        $expected1 = [
          $this->arch['id'] => $this->item2,
          $this->arch['id_parent'] => $this->item,
          $this->arch['code'] => 'code_1'
        ],
        $expected2 = [
          $this->arch['id'] => $this->item3,
          $this->arch['id_parent'] => $this->item,
          $this->arch['code'] => 'code_2'
        ]
      ]);

    $this->assertSame(
      [
        $expected1[$this->arch['code']] => $expected1,
        $expected2[$this->arch['code']] => $expected2
      ],
      $this->option->fullOptionsByCode('list')
    );
  }

  /** @test */
  public function fullOptionsByCode_method_returns_null_when_fails_to_retrieve_full_options()
  {
    $this->mockOptionClass();


    $this->option->shouldReceive('fullOptions')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->fullOptionsByCode('list')
    );
  }

  /** @test */
  public function fullOptionsCfg_method_returns_an_array_of_full_option_with_config_for_the_given_parent()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('fullOptions')
      ->once()
      ->with($this->item)
      ->andReturn($options = [
        [
          $this->arch['id'] => $this->item2,
          $this->arch['id_parent'] => $this->item,
          $this->arch['code'] => 'code_1'
        ],
        [
          $this->arch['id'] => $this->item3,
          $this->arch['id_parent'] => $this->item,
          $this->arch['code'] => 'code_2'
        ]
      ]);

    foreach ($options as $option) {
      $this->option->shouldReceive('getCfg')
        ->once()
        ->with($option[$this->arch['id']])
        ->andReturn([
          'sortable' => true
        ]);
    }


    $this->assertSame(
      [
        [
          $this->arch['id'] => $this->item2,
          $this->arch['id_parent'] => $this->item,
          $this->arch['code'] => 'code_1',
          $this->arch['cfg'] => [
             'sortable' => true
          ]
        ],
        [
          $this->arch['id'] => $this->item3,
          $this->arch['id_parent'] => $this->item,
          $this->arch['code'] => 'code_2',
          $this->arch['cfg'] => [
            'sortable' => true
          ]
        ]
      ],
      $this->option->fullOptionsCfg('list')
    );
  }

  /** @test */
  public function fullOptionsCfg_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->fullOptionsCfg('list')
    );
  }

  /** @test */
  public function soptions_method_returns_an_id_indexed_array_of_options_in_the_form_of_text_for_the_give_grandparent()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item)
      ->andReturn([$this->item2, $this->item3, $this->item4]);

    $this->option->shouldReceive('options')
      ->once()
      ->with($this->item2)
      ->andReturn($expected = [
        $this->item4 => 'text_1',
        $this->item5 => 'text_2',
      ]);

    $this->option->shouldReceive('options')
      ->once()
      ->with($this->item3)
      ->andReturnNull();

    $this->option->shouldReceive('options')
      ->once()
      ->with($this->item4)
      ->andReturn($expected2 = [
        $this->item => 'text_3',
        $this->item2 => 'text_4',
      ]);

    $this->assertSame(
      array_merge($expected, $expected2),
      $this->option->soptions('list')
    );
  }

  /** @test */
  public function soptions_method_returns_empty_array_when_no_items_found_for_the_given_code()
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

    $this->assertSame(
      [],
      $this->option->soptions('list')
    );
  }

  /** @test */
  public function soptions_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertnull(
      $this->option->soptions('list')
    );
  }

  /** @test */
  public function fullSoptions_method_returns_an_array_of_full_options_for_the_given_grand_parent()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item)
      ->andReturn([$this->item2, $this->item3, $this->item4]);

    $this->option->shouldReceive('fullOptions')
      ->once()
      ->with($this->item2)
      ->andReturn($expected1 = [
        [
          $this->arch['id'] => $this->item4,
          $this->arch['id_parent'] => $this->item,
          $this->arch['code'] => 'code_1'
        ],
        [
          $this->arch['id'] => $this->item5,
          $this->arch['id_parent'] => $this->item,
          $this->arch['code'] => 'code_2'
        ]
      ]);

    $this->option->shouldReceive('fullOptions')
      ->once()
      ->with($this->item3)
      ->andReturn($expected2 = [
        [
          $this->arch['id'] => $this->item3,
          $this->arch['id_parent'] => $this->item,
          $this->arch['code'] => 'code_3'
        ],
        [
          $this->arch['id'] => $this->item4,
          $this->arch['id_parent'] => $this->item,
          $this->arch['code'] => 'code_4'
        ]
      ]);

    $this->option->shouldReceive('fullOptions')
      ->once()
      ->with($this->item4)
      ->andReturnNull();

    $this->assertSame(
      array_merge($expected1, $expected2),
      $this->option->fullSoptions('list')
    );
  }

  /** @test */
  public function fullSoptions_method_returns_empty_array_when_no_items_found_for_the_given_code()
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

    $this->assertSame(
      [],
      $this->option->fullSoptions('list')
    );
  }

  /** @test */
  public function fullSoptions_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->fullSoptions('list')
    );
  }

  /** @test */
  public function treeIds_method_an_array_of_all_ids_found_in_a_hierarchical_structure()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->times(4)
      ->andReturnTrue();

    // First call
    $this->option->shouldReceive('exists')
      ->once()
      ->with($this->item)
      ->andReturnTrue();

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item)
      ->andReturn([$this->item2, $this->item3]);

    // Second recursive call
    $this->option->shouldReceive('exists')
      ->once()
      ->with($this->item2)
      ->andReturnTrue();

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item2)
      ->andReturnNull();

    // Third recursive call
    $this->option->shouldReceive('exists')
      ->once()
      ->with($this->item3)
      ->andReturnTrue();

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item3)
      ->andReturn([$this->item4]);

    // Fourth recursive call
    $this->option->shouldReceive('exists')
      ->once()
      ->with($this->item4)
      ->andReturnTrue();

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item4)
      ->andReturn([]);

    $this->assertSame(
      [$this->item, $this->item2, $this->item3, $this->item4],
      $this->option->treeIds($this->item)
    );
  }

  /** @test */
  public function treeIds_method_returns_null_when_the_given_id_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->option->shouldReceive('exists')
      ->once()
      ->with($this->item)
      ->andReturnFalse();

    $this->assertNull(
      $this->option->treeIds($this->item)
    );
  }

  /** @test */
  public function treeIds_method_returns_null_when_check_method_returns_false()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->option->treeIds($this->item)
    );
  }

  /** @test */
  public function nativeTree_method_returns_a_hierarchical_structure_as_stored_in_its_original_form_in_database()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('nativeOption')
      ->once()
      ->with($this->item)
      ->andReturn($expected = [
        'id' => $this->item,
        'code' => 'code_1',
        'text' => 'text_1'
      ]);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item)
      ->andReturn([$this->item2, $this->item3]);

    // Second recursive call
    $this->option->shouldReceive('fromCode')
      ->once()
      ->with([$this->item2])
      ->andReturn($this->item2);

    $this->option->shouldReceive('nativeOption')
      ->once()
      ->with($this->item2)
      ->andReturn($expected['items'][] = [
        'id' => $this->item2,
        'code' => 'code_2',
        'text' => 'text_2'
      ]);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item2)
      ->andReturn([$this->item4]);

    // Third recursive call
    $this->option->shouldReceive('fromCode')
      ->once()
      ->with([$this->item4])
      ->andReturn($this->item4);

    $this->option->shouldReceive('nativeOption')
      ->once()
      ->with($this->item4)
      ->andReturn($expected['items'][0]['items'][] = [
        'id' => $this->item4,
        'code' => 'code_4',
        'text' => 'text_4'
      ]);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item4)
      ->andReturnNull();

    // Fourth recursive call
    $this->option->shouldReceive('fromCode')
      ->once()
      ->with([$this->item3])
      ->andReturn($this->item3);

    $this->option->shouldReceive('nativeOption')
      ->once()
      ->with($this->item3)
      ->andReturn($expected['items'][] = [
        'id' => $this->item3,
        'code' => 'code_3',
        'text' => 'text_3'
      ]);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item3)
      ->andReturnNull();

    $this->assertSame(
      $expected,
      $this->option->nativeTree('list')
    );
  }

  /** @test */
  public function nativeTree_method_returns_null_when_no_native_options_found()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('nativeOption')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertNull(
      $this->option->nativeTree('list')
    );
  }

  /** @test */
  public function nativeTree_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->nativeTree('list')
    );
  }

  /** @test */
  public function tree_method_returns_a_simple_hierarchical_structure_with_just_text_and_id_and_items()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('text')
      ->once()
      ->with($this->item)
      ->andReturn('text_1');

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item)
      ->andReturn([$this->item2, $this->item3]);

    // Second recursive call
    $this->option->shouldReceive('fromCode')
      ->once()
      ->with([$this->item2])
      ->andReturn($this->item2);

    $this->option->shouldReceive('text')
      ->once()
      ->with($this->item2)
      ->andReturn('text_2');

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item2)
      ->andReturnNull();

    // Third recursive call
    $this->option->shouldReceive('fromCode')
      ->once()
      ->with([$this->item3])
      ->andReturn($this->item3);

    $this->option->shouldReceive('text')
      ->once()
      ->with($this->item3)
      ->andReturn('text_3');

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item3)
      ->andReturn([$this->item4]);

    // Fourth recursive call
    $this->option->shouldReceive('fromCode')
    ->once()
    ->with([$this->item4])
    ->andReturn($this->item4);

    $this->option->shouldReceive('text')
      ->once()
      ->with($this->item4)
      ->andReturn('text_4');

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item4)
      ->andReturnNull();

    $expected = [
      'id' => $this->item,
      'text' => 'text_1',
      'items' => [
        [
          'id' => $this->item2,
          'text' => 'text_2'
        ],
        [
          'id' => $this->item3,
          'text' => 'text_3',
          'items' => [
            [
              'id' => $this->item4,
              'text' => 'text_4'
            ]
          ]
        ]
      ]
    ];

    $this->assertSame(
      $expected,
      $this->option->tree('list')
    );
  }

  /** @test */
  public function tree_method_returns_null_when_fails_to_retrieve_text()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('text')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertNull(
      $this->option->tree('list')
    );
  }

  /** @test */
  public function tree_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->tree('list')
    );
  }

  /** @test */
  public function fullTree_method_returns_a_full_hierarchical_structure_of_options_from_a_given_option()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('option')
      ->once()
      ->with($this->item)
      ->andReturn($expected = [
        'id' => $this->item,
        'code' => 'list',
        'text' => 'some_text',
        'property' => 'value'
      ]);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item)
      ->andReturn([$this->item2, $this->item3]);

    // First recursive call
    $this->option->shouldReceive('fromCode')
      ->once()
      ->with([$this->item2])
      ->andReturn($this->item2);

    $this->option->shouldReceive('option')
      ->once()
      ->with($this->item2)
      ->andReturn($expected['items'][] = [
        'id' => $this->item2,
        'code' => 'list_2',
        'text' => 'some_text_2',
        'property' => 'value_2'
      ]);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item2)
      ->andReturn([$this->item4]);

    // Second recursive call
    $this->option->shouldReceive('fromCode')
      ->once()
      ->with([$this->item4])
      ->andReturn($this->item4);

    $this->option->shouldReceive('option')
      ->once()
      ->with($this->item4)
      ->andReturn($expected['items'][0]['items'][] = [
        $this->arch['id'] => $this->item4,
        $this->arch['code'] => 'list_4',
        $this->arch['text'] => 'some_text_4',
        'property' => 'value_4'
      ]);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item4)
      ->andReturnNull();

    // Third recursive call
    $this->option->shouldReceive('fromCode')
      ->once()
      ->with([$this->item3])
      ->andReturn($this->item3);

    $this->option->shouldReceive('option')
      ->once()
      ->with($this->item3)
      ->andReturn($expected['items'][] = [
        $this->arch['id'] => $this->item3,
        $this->arch['code'] => 'list_3',
        $this->arch['text'] => 'some_text_3',
        'property' => 'value_3'
      ]);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item3)
      ->andReturnNull();

    $this->assertSame(
      $expected,
      $this->option->fullTree('list')
    );
  }

  /** @test */
  public function fullTree_method_returns_null_when_fails_to_retrive_option_content()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('option')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertNull(
      $this->option->fullTree('list')
    );
  }

  /** @test */
  public function fullTree_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->fullTree('list')
    );
  }

  /** @test */
  public function fullTreeRef_method_returns_a_full_hierarchical_of_options_plus_aliases_from_the_given_code()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('option')
      ->once()
      ->with($this->item)
      ->andReturn($expected = [
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'code_1',
        $this->arch['text'] => 'text_1'
      ]);

    $this->option->shouldReceive('fullOptionsRef')
      ->once()
      ->with($this->item)
      ->andReturn([
        $item2_arr = [$this->arch['id'] => $this->item2, $this->arch['code'] => 'code_2'],
        $item3_arr = [$this->arch['id'] => $this->item3, $this->arch['code'] => 'code_3']
      ]);

    // First recursive call
    $this->option->shouldReceive('fromCode')
      ->once()
      ->with([$item2_arr])
      ->andReturn($this->item2);

    $this->option->shouldReceive('option')
      ->once()
      ->with($this->item2)
      ->andReturn($expected['items'][] = [
        $this->arch['id'] => $this->item2,
        $this->arch['code'] => 'code_2',
        $this->arch['text'] => 'text_2'
      ]);

    $this->option->shouldReceive('fullOptionsRef')
      ->once()
      ->with($this->item2)
      ->andReturn([
        $item4_arr = [$this->arch['id'] => $this->item4, $this->arch['code'] => 'code_4']
      ]);

    // Second recursive call
    $this->option->shouldReceive('fromCode')
      ->once()
      ->with([$item4_arr])
      ->andReturn($this->item4);

    $this->option->shouldReceive('option')
      ->once()
      ->with($this->item4)
      ->andReturn($expected['items'][0]['items'][] = [
        $this->arch['id'] => $this->item4,
        $this->arch['code'] => 'code_4',
        $this->arch['text'] => 'text_4'
      ]);

    $this->option->shouldReceive('fullOptionsRef')
      ->once()
      ->with($this->item4)
      ->andReturnNull();

    // Third recursive call
    $this->option->shouldReceive('fromCode')
      ->once()
      ->with([$item3_arr])
      ->andReturn($this->item3);

    $this->option->shouldReceive('option')
      ->once()
      ->with($this->item3)
      ->andReturn($expected['items'][] = [
        $this->arch['id'] => $this->item3,
        $this->arch['code'] => 'code_4',
        $this->arch['text'] => 'text_4'
      ]);

    $this->option->shouldReceive('fullOptionsRef')
      ->once()
      ->with($this->item3)
      ->andReturnNull();

    $this->assertSame(
      $expected,
      $this->option->fullTreeRef('list')
    );
  }

  /** @test */
  public function fullTreeRef_method_returns_null_when_fails_to_retrieve_full_option_content_for_the_given_code()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('option')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertNull(
      $this->option->fullTreeRef('list')
    );
  }

  /** @test */
  public function fullTreeRef_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->fullTreeRef('list')
    );
  }

  /** @test */
  public function getCfg_method_returns_formatted_content_of_the_cfg_column_as_an_array_first_test()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getCfg'
      )
      ->andReturnFalse();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['cfg'],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn(json_encode([
        'sortable' => true,
        'show_alias' => true,
        'show_icon' => true,
        'desc' => 'some description',
        'default_value' => 'some value'
      ]));

    $this->option->shouldReceive('parents')
      ->once()
      ->with($this->item)
      ->andReturn($parents = [$this->item2, $this->item3]);

    foreach ($parents as $parent) {
      $this->db_mock->shouldReceive('selectOne')
        ->once()
        ->with(
          $this->class_cfg['table'],
          $this->arch['cfg'],
          [$this->arch['id'] => $parent]
        )
        ->andReturn(json_encode([
          'scfg' => ['a' => 'b']
        ]));
    }

    $expected = [
      'sortable' => 1,
      'show_alias' => 1,
      'show_icon' => 1,
      'desc' => 'some description',
      'default_value' => 'some value',
      'a' => 'b',
      'inherit_from' => $this->item2,
      'frozen' => 1,
      'show_code' => 0,
      'show_value' => 0,
      'allow_children' => 0,
      'inheritance' => '',
      'permissions' => '',
      'controller' => null,
      'schema' => null,
      'form' => null
    ];

    $this->cache_mock->shouldReceive('set')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getCfg',
        $expected,
        0
      )
      ->andReturnTrue();

    $this->assertSame(
      $expected,
      $this->option->getCfg('list')
    );
  }

  /** @test */
  public function getCfg_method_returns_formatted_content_of_the_cfg_column_as_an_array_second_test()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getCfg'
      )
      ->andReturnFalse();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['cfg'],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn(json_encode([
        'sortable' => true,
        'allow_children' => true,
        'show_icon' => true,
        'permissions' => 'some permissions',
        'form' => 'some form'
      ]));

    $this->option->shouldReceive('parents')
      ->once()
      ->with($this->item)
      ->andReturn([$this->item2, $this->item3]);

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['cfg'],
        [$this->arch['id'] => $this->item3]
      )
      ->andReturn(json_encode([
        'scfg' => ['c' => 'd'],
        'inheritance' => 'cascade'
      ]));

    $expected = [
      'sortable' => 1,
      'allow_children' => 1,
      'show_icon' => 1,
      'permissions' => 'some permissions',
      'form' => 'some form',
      'c' => 'd',
      'inherit_from' => $this->item3,
      'frozen' => 1,
      'show_code' => 0,
      'show_alias' => 0,
      'show_value' => 0,
      'desc' => '',
      'inheritance' => '',
      'controller' => null,
      'schema' => null,
      'default_value' => null
    ];

    $this->cache_mock->shouldReceive('set')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getCfg',
        $expected,
        0
      )
      ->andReturnTrue();

    $this->assertSame(
      $expected,
      $this->option->getCfg('list')
    );
  }

  /** @test */
  public function getCfg_method_returns_formatted_content_of_the_cfg_column_as_an_array_third_test()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getCfg'
      )
      ->andReturnFalse();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['cfg'],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn(json_encode([
        'sortable' => false
      ]));

    $this->option->shouldReceive('parents')
      ->once()
      ->with($this->item)
      ->andReturn([$this->item2, $this->item3]);

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['cfg'],
        [$this->arch['id'] => $this->item3]
      )
      ->andReturn(json_encode([
        'scfg' => ['c' => 'd', 'inheritance' => 'children']
      ]));

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['cfg'],
        [$this->arch['id'] => $this->item2]
      )
      ->andReturn(json_encode([
        'scfg' => ['a' => 'b']
      ]));

    $expected = [
      'sortable' => 0,
      'a' => 'b',
      'inherit_from' => $this->item2,
      'frozen' => 1,
      'show_code' => 0,
      'show_alias' => 0,
      'show_value' => 0,
      'show_icon' => 0,
      'allow_children' => 0,
      'desc' => '',
      'inheritance' => '',
      'permissions' => '',
      'controller' => null,
      'schema' => null,
      'form' => null,
      'default_value' => null,
    ];

    $this->cache_mock->shouldReceive('set')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getCfg',
        $expected,
        0
      )
      ->andReturnTrue();

    $this->assertSame(
      $expected,
      $this->option->getCfg('list')
    );
  }

  /** @test */
  public function getCfg_method_returns_formatted_content_of_the_cfg_column_as_an_array_fourth_test()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getCfg'
      )
      ->andReturnFalse();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['cfg'],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn(json_encode([
        'sortable' => false
      ]));

    $this->option->shouldReceive('parents')
      ->once()
      ->with($this->item)
      ->andReturn([$this->item2, $this->item3]);

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['cfg'],
        [$this->arch['id'] => $this->item2]
      )
      ->andReturn(json_encode([
        'scfg' => ['c' => 'd', 'inheritance' => 'children']
      ]));

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['cfg'],
        [$this->arch['id'] => $this->item3]
      )
      ->andReturn(json_encode([
        'scfg' => ['a' => 'b']
      ]));

    $expected = [
      'sortable' => 0,
      'c' => 'd',
      'inheritance' => 'children',
      'inherit_from' => $this->item2,
      'frozen' => 1,
      'show_code' => 0,
      'show_alias' => 0,
      'show_value' => 0,
      'show_icon' => 0,
      'allow_children' => 0,
      'desc' => '',
      'permissions' => '',
      'controller' => null,
      'schema' => null,
      'form' => null,
      'default_value' => null,
    ];

    $this->cache_mock->shouldReceive('set')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getCfg',
        $expected,
        0
      )
      ->andReturnTrue();

    $this->assertSame(
      $expected,
      $this->option->getCfg('list')
    );
  }

  /** @test */
  public function getCfg_method_returns_formatted_content_of_the_cfg_column_as_an_array_fifth_test()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getCfg'
      )
      ->andReturnFalse();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['cfg'],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn([]);

    $this->option->shouldReceive('parents')
      ->once()
      ->with($this->item)
      ->andReturn([$this->item2, $this->item3]);

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['cfg'],
        [$this->arch['id'] => $this->item2]
      )
      ->andReturn(json_encode(['c' => 'd']));

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['cfg'],
        [$this->arch['id'] => $this->item3]
      )
      ->andReturn(json_encode(['a' => 'b']));

    $expected = [
      'show_code' => 0,
      'show_alias' => 0,
      'show_value' => 0,
      'show_icon' => 0,
      'sortable' => 0,
      'allow_children' => 0,
      'frozen' => 0,
      'desc' => '',
      'inheritance' => '',
      'permissions' => '',
      'controller' => null,
      'schema' => null,
      'form' => null,
      'default_value' => null,
    ];

    $this->cache_mock->shouldReceive('set')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getCfg',
        $expected,
        0
      )
      ->andReturnTrue();

    $this->assertSame(
      $expected,
      $this->option->getCfg('list')
    );
  }

  /** @test */
  public function getCfg_method_returns_formatted_content_of_the_cfg_column_as_an_array_sixth_test()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getCfg'
      )
      ->andReturnFalse();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['cfg'],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn([]);

    $this->option->shouldReceive('parents')
      ->once()
      ->with($this->item)
      ->andReturn([$this->item2, $this->item3]);

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['cfg'],
        [$this->arch['id'] => $this->item2]
      )
      ->andReturn(json_encode(['c' => 'd']));

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['cfg'],
        [$this->arch['id'] => $this->item3]
      )
      ->andReturn(json_encode([
        'scfg' => ['inheritance' => 'default']
      ]));

    $expected = [
      'inheritance' => 'default',
      'inherit_from' => $this->item3,
      'show_code' => 0,
      'show_alias' => 0,
      'show_value' => 0,
      'show_icon' => 0,
      'sortable' => 0,
      'allow_children' => 0,
      'frozen' => 0,
      'desc' => '',
      'permissions' => '',
      'controller' => null,
      'schema' => null,
      'form' => null,
      'default_value' => null,
    ];

    $this->cache_mock->shouldReceive('set')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getCfg',
        $expected,
        0
      )
      ->andReturnTrue();

    $this->assertSame(
      $expected,
      $this->option->getCfg('list')
    );
  }

  /** @test */
  public function getCfg_method_returns_formatted_content_of_the_cfg_column_as_an_array_from_cache()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->cache_mock->shouldReceive('get')
      ->once()
      ->with(
        $this->getCachePrefix() . $this->getUidCacheName($this->item) . '/getCfg'
      )
      ->andReturn($expected = [
        'show_code' => 0,
        'show_alias' => 0,
        'show_value' => 0,
        'show_icon' => 0,
        'sortable' => 0,
        'allow_children' => 0,
        'frozen' => 0,
        'desc' => '',
        'inheritance' => '',
        'permissions' => '',
        'controller' => null,
        'schema' => null,
        'form' => null,
        'default_value' => null,
      ]);

    $this->assertSame(
      $expected,
      $this->option->getCfg('list')
    );
  }

  /** @test */
  public function getCfg_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->getCfg('list')
    );
  }

  /** @test */
  public function getRawCfg_method_returns_raw_config_column_of_the_given_option()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['cfg'],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn($expected = "{'sortable':true, 'cascade': true}");

    $this->assertSame(
      $expected,
      $this->option->getRawCfg('list')
    );
  }

  /** @test */
  public function getRawCfg_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->getRawCfg('list')
    );
  }

  /** @test */
  public function getParentCfg_method_returns_a_formatted_content_of_the_config_column_as_array_from_the_given_option_parent()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item)
      ->andReturn($this->item2);

    $this->option->shouldReceive('getCfg')
      ->once()
      ->with($this->item2)
      ->andReturn($expected = [
        'sortable' => true,
        'cascade' => true
      ]);

    $this->assertSame(
      $expected,
      $this->option->getParentCfg('list')
    );
  }

  /** @test */
  public function getParentCfg_method_returns_null_when_fails_to_retrieve_parent_id()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertNull(
      $this->option->getParentCfg('list')
    );
  }

  /** @test */
  public function getParentCfg_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->getParentCfg('list')
    );
  }

  /** @test */
  public function parents_method_returns_an_array_of_id_parents_from_the_given_option_to_the_root()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item)
      ->andReturn($this->item2);

    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item2)
      ->andReturn($this->item3);

    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item3)
      ->andReturn($this->item4);

    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item4)
      ->andReturn($this->item2);

    $this->assertSame(
      [$this->item2, $this->item3, $this->item4],
      $this->option->parents('list')
    );
  }

  /** @test */
  public function parents_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->parent('list')
    );
  }

  /** @test */
  public function sequence_method_returns_an_array_of_id_parents_from_the_selected_root_to_the_given_id_option()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('exists')
      ->once()
      ->with($this->item4)
      ->andReturnTrue();

    $this->option->shouldReceive('parents')
      ->once()
      ->with($this->item)
      ->andReturn([$this->item2, $this->item3, $this->item4]);

    $this->assertSame(
      [$this->item4, $this->item3, $this->item2, $this->item],
      $this->option->sequence($this->item, $this->item4)
    );
  }

  /** @test */
  public function sequence_method_returns_an_array_of_id_parents_from_the_selected_root_to_the_default_id_option_if_not_provided()
  {
    $this->mockOptionClass();

    $default_root = 'c88846c3bff511e7b7d5000c29703ca2';

    $this->option->shouldReceive('exists')
      ->once()
      ->with($default_root)
      ->andReturnTrue();

    $this->option->shouldReceive('parents')
      ->once()
      ->with($this->item)
      ->andReturn([$this->item2, $this->item3, $default_root]);

    $this->assertSame(
      [$default_root, $this->item3, $this->item2, $this->item],
      $this->option->sequence($this->item)
    );
  }

  /** @test */
  public function sequence_method_returns_null_when_the_given_id_option_does_not_have_parents()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('exists')
      ->once()
      ->with($this->item4)
      ->andReturnTrue();

    $this->option->shouldReceive('parents')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertNull(
      $this->option->sequence($this->item, $this->item4)
    );
  }

  /** @test */
  public function sequence_method_returns_null_when_the_given_root_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('exists')
      ->once()
      ->with($this->item4)
      ->andReturnFalse();

    $this->assertNull(
      $this->option->sequence($this->item, $this->item4)
    );
  }

  /** @test */
  public function getIdParent_method_returns_the_parent_of_the_given_option()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('nativeOption')
      ->once()
      ->with($this->item)
      ->andReturn([
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'some code',
        $this->arch['id_parent'] => $this->item4
      ]);

    $this->assertSame(
      $this->item4,
      $this->option->getIdParent('list')
    );
  }

  /** @test */
  public function getIdParent_method_returns_null_when_fails_to_retrieve_code_full_option()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('nativeOption')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertNull(
      $this->option->getIdParent('list')
    );
  }

  /** @test */
  public function getIdParent_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->getIdParent('list')
    );
  }

  /** @test */
  public function parent_method_returns_the_parent_option()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item)
      ->andReturn($this->item2);

    $this->option->shouldReceive('option')
      ->once()
      ->with($this->item2)
      ->andReturn($expected = [
        'id' => $this->item2,
        'code' => 'list',
        'text' => 'some text'
      ]);

    $this->assertSame(
      $expected,
      $this->option->parent('list')
    );
  }

  /** @test */
  public function parent_method_returns_null_when_fails_to_retrieve_the_given_code_parent()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertNull(
      $this->option->parent('list')
    );
  }

  /** @test */
  public function parent_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->parent('list')
    );
  }

  /** @test */
  public function isParent_method_returns_true_if_row_with_the_given_id_parent_is_parent_at_any_level_of_row_with_the_given_id()
  {
    $this->mockOptionClass();

    // First loop
    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item)
      ->andReturn($this->item2);

    // Second loop
    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item2)
      ->andReturn($this->item3);

    // Third loop
    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item3)
      ->andReturn($this->item4);

    $this->assertTrue(
      $this->option->isParent($this->item, $this->item4)
    );
  }

  /** @test */
  public function isParent_method_returns_false_if_row_with_the_given_id_parent_is_not_parent_at_any_level_of_row_with_the_given_id()
  {
    $this->mockOptionClass();

    // First loop
    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item)
      ->andReturn($this->item2);

    // Second loop
    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item2)
      ->andReturn($this->item3);

    // Third loop
    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item3)
      ->andReturnNull();

    $this->assertFalse(
      $this->option->isParent($this->item, $this->item4)
    );
  }

  /** @test */
  public function isParent_method_returns_false_if_there_are_duplicate_paretns_returned()
  {
    $this->mockOptionClass();

    // First loop
    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item)
      ->andReturn($this->item2);

    // Second loop
    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item2)
      ->andReturn($this->item3);

    // Third loop
    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item3)
      ->andReturn($this->item2);

    $this->assertFalse(
      $this->option->isParent($this->item, $this->item4)
    );
  }

  /** @test */
  public function isParent_method_returns_false_when_the_given_id_is_not_uid()
  {
    $this->assertFalse(
      $this->option->isParent('1122', $this->item)
    );

    $this->assertFalse(
      $this->option->isParent($this->item, '1122')
    );

    $this->assertFalse(
      $this->option->isParent('44422', '1122')
    );
  }

  /** @test */
  public function getCodes_method_returns_an_array_of_options_if_the_form_of_id_and_code_sorted_by_num()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);


    $this->option->shouldReceive('isSortable')
      ->once()
      ->with($this->item)
      ->andReturnTrue();

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['id'], $this->arch['code']],
        [$this->arch['id_parent'] => $this->item],
        [$this->arch['num'] => 'ASC']
      )
      ->andReturn([
        [$this->arch['id'] => $this->item2, $this->arch['code'] => 'option_1'],
        [$this->arch['id'] => $this->item3, $this->arch['code'] => 'option_2'],
      ]);

    $this->assertSame(
      [
        $this->item2 => 'option_1',
        $this->item3 => 'option_2',
      ],
      $this->option->getCodes('list')
    );
  }

  /** @test */
  public function getCodes_method_returns_an_array_of_options_if_the_form_of_id_and_code_sorted_by_code()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);


    $this->option->shouldReceive('isSortable')
      ->once()
      ->with($this->item)
      ->andReturnFalse();

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['id'], $this->arch['code']],
        [$this->arch['id_parent'] => $this->item],
        [$this->arch['code'] => 'ASC']
      )
      ->andReturn([
        [$this->arch['id'] => $this->item2, $this->arch['code'] => 'option_1'],
        [$this->arch['id'] => $this->item3, $this->arch['code'] => 'option_2'],
      ]);

    $this->assertSame(
      [
        $this->item2 => 'option_1',
        $this->item3 => 'option_2',
      ],
      $this->option->getCodes('list')
    );
  }

  /** @test */
  public function getCodes_method_returns_empty_array_when_no_results_found_in_database()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('isSortable')
    ->once()
    ->with($this->item)
    ->andReturnFalse();

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['id'], $this->arch['code']],
        [$this->arch['id_parent'] => $this->item],
        [$this->arch['code'] => 'ASC']
      )
      ->andReturn([]);

    $this->assertSame(
      [],
      $this->option->getCodes('list')
    );
  }

  /** @test */
  public function getCodes_method_returns_empty_array_when_the_given_code_is_not_valid()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertSame(
      [],
      $this->option->getCodes('list')
    );
  }

  /** @test */
  public function code_method_returns_option_code()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->withNoArgs()
      ->andReturnTrue();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['code'],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn($expected = 'some_code');

    $this->assertSame(
      $expected,
      $this->option->code($this->item)
    );
  }

  /** @test */
  public function code_method_returns_null_when_the_given_id_is_not_valid()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->withNoArgs()
      ->andReturnTrue();

    $this->assertNull(
      $this->option->code('12234')
    );
  }

  /** @test */
  public function code_method_returns_null_when_check_method_returns_false()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->withNoArgs()
      ->andReturnFalse();

    $this->assertNull(
      $this->option->code('12234')
    );
  }

  /** @test */
  public function text_method_returns_option_text()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['text'],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn($expected = 'some_text');

    $this->assertSame($expected, $this->option->text('list'));
  }

  /** @test */
  public function text_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->text('list')
    );
  }

  /** @test */
  public function alias_method_returns_the_id_alias_relative_to_the_given_id_option()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->withNoArgs()
      ->andReturnTrue();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['id_alias'],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn($this->item5);

    $this->assertSame(
      $this->item5,
      $this->option->alias($this->item)
    );
  }

  /** @test */
  public function alias_method_returns_null_when_the_given_id_is_not_valid()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->withNoArgs()
      ->andReturnTrue();

    $this->assertNull(
      $this->option->alias('12345')
    );
  }

  /** @test */
  public function alias_method_returns_null_when_check_method_returns_false()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->withNoArgs()
      ->andReturnFalse();

    $this->assertNull(
      $this->option->alias($this->item5)
    );
  }

  /** @test */
  public function itext_method_returns_translation_of_an_options_text()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['text'],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn($expected = 'some_text');

    $this->assertSame($expected, $this->option->itext('list'));
  }

  /** @test */
  public function itext_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->itext('list')
    );
  }

  /** @test */
  public function count_method_returns_the_number_of_children_for_a_given_option()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->db_mock->shouldReceive('count')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['id_parent'] => $this->item]
      )
      ->andReturn(12);

    $this->assertSame(12, $this->option->count('list'));
  }

  /** @test */
  public function count_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull($this->option->count('list'));
  }

  /** @test */
  public function optionsByAlias_method_returns_an_array_of_options_based_on_their_id_alias()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('getRows')
      ->once()
      ->with(
       [$this->arch['id_alias'] => $this->item]
      )
      ->andReturn($expected = [
        ['id' => $this->item2, 'code' => 'code_2'],
        ['id' => $this->item3, 'code' => 'code_3'],
      ]);

    foreach ($expected as $item) {
      $this->option->shouldReceive('option')
        ->once()
        ->with($item)
        ->andReturn($item);
    }

    $this->assertSame(
      $expected,
      $this->option->optionsByAlias('list')
    );
  }

  /** @test */
  public function optionsByAlias_method_returns_null_when_no_records_found_for_the_given_code()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('getRows')
      ->once()
      ->with(
        [$this->arch['id_alias'] => $this->item]
      )
      ->andReturnNull();

    $this->assertNull(
      $this->option->optionsByAlias('list')
    );
  }

  /** @test */
  public function optionsByAlias_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->optionsByAlias('list')
    );
  }

  /** @test */
  public function isSortable_method_checks_if_the_given_option_is_sortable_from_its_config()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->times(3)
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('getCfg')
      ->once()
      ->with($this->item)
      ->andReturn(['sortable' => true]);

    $this->assertTrue(
      $this->option->isSortable('list')
    );

    // Another test
    $this->option->shouldReceive('getCfg')
      ->once()
      ->with($this->item)
      ->andReturn(['sortable' => false]);

    $this->assertFalse(
      $this->option->isSortable('list')
    );

    // Another test
    $this->option->shouldReceive('getCfg')
      ->once()
      ->with($this->item)
      ->andReturn(['a' => 'b']);

    $this->assertFalse(
      $this->option->isSortable('list')
    );
  }

  /** @test */
  public function isSortable_method_returns_null_when_the_given_code_does_not_exist_or_not_valid()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->isSortable('list')
    );

    // Another test
    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn('a1234ef');

    $this->assertNull(
      $this->option->isSortable('list')
    );
  }

  /** @test */
  public function getPathArray_method_returns_an_array_of_codes_for_each_option_between_id_and_root_without_root_code()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('code')
      ->once()
      ->with($this->item3)
      ->andReturn('code_1');

    // First loop
    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item3)
      ->andReturn($this->item2);

    $this->option->shouldReceive('code')
      ->once()
      ->with($this->item2)
      ->andReturn('code_2');

    // Second loop -> root's code so should not be returned
    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item2)
      ->andReturn($this->item);

    $this->option->shouldReceive('code')
      ->once()
      ->with($this->item)
      ->andReturn('code_3');

    $this->assertSame(
      ['code_2', 'code_1'],
      $this->option->getPathArray($this->item3, $this->item)
    );
  }

  /** @test */
  public function getPathArray_method_returns_an_array_of_codes_for_each_option_between_id_and_root_without_root_code_using_default_root()
  {
    $this->mockOptionClass();

    $this->setNonPublicPropertyValue('default', $this->default);

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->option->shouldReceive('code')
      ->once()
      ->with($this->item3)
      ->andReturn('code_1');

    // First loop
    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item3)
      ->andReturn($this->item2);

    $this->option->shouldReceive('code')
      ->once()
      ->with($this->item2)
      ->andReturn('code_2');

    // Second loop -> root's code so should not be returned
    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item2)
      ->andReturn($this->default);

    $this->option->shouldReceive('code')
      ->once()
      ->with($this->default)
      ->andReturn('code_3');

    $this->assertSame(
      ['code_2', 'code_1'],
      $this->option->getPathArray($this->item3)
    );
  }

  /** @test */
  public function getPathArray_method_returns_null_when_fails_to_retrieve_id_parent_at_any_point_during_the_loop()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('code')
      ->once()
      ->with($this->item3)
      ->andReturn('code_1');

    // First loop
    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item3)
      ->andReturn($this->item2);

    $this->option->shouldReceive('code')
      ->once()
      ->with($this->item2)
      ->andReturn('code_2');

    // Second loop
    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item2)
      ->andReturnNull();

    $this->assertNull(
      $this->option->getPathArray($this->item3, $this->item5)
    );
  }

  /** @test */
  public function getPathArray_method_returns_empty_array_when_the_given_id_is_same_as_the_root()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
    ->once()
    ->andReturnTrue();

    $this->setNonPublicPropertyValue('default', $this->item);

    $this->option->shouldReceive('code')
      ->once()
      ->with($this->item)
      ->andReturn('some_code');

    $this->assertSame(
      [],
      $this->option->getPathArray($this->item)
    );
  }

  /** @test */
  public function fromPath_method_returns_the_closest_id_option_from_a_path_of_codes()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    // First loop
    $this->option->shouldReceive('fromCode')
      ->once()
      ->with('list', $this->item5)
      ->andReturn($this->item2);

    // Second loop
    $this->option->shouldReceive('fromCode')
      ->once()
      ->with('appui', $this->item2)
      ->andReturn($this->item);

    $this->assertSame(
      $this->item,
      $this->option->fromPath('list,appui', ',', $this->item5)
    );
  }

  /** @test */
  public function fromPath_method_returns_the_closest_id_option_from_a_path_of_codes_using_default_as_parent()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->setNonPublicPropertyValue('default', $this->item5);

    // First loop
    $this->option->shouldReceive('fromCode')
      ->once()
      ->with('list', $this->item5)
      ->andReturn($this->item2);

    // Second loop
    $this->option->shouldReceive('fromCode')
      ->once()
      ->with('appui', $this->item2)
      ->andReturn($this->item);

    $this->assertSame(
      $this->item,
      $this->option->fromPath('list|appui')
    );
  }

  /** @test */
  public function fromPath_method_returns_null_when_fails_to_retrieve_option_from_code_at_any_point_during_the_loop()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    // First loop
    $this->option->shouldReceive('fromCode')
      ->once()
      ->with('list', $this->item5)
      ->andReturn($this->item2);

    // Second loop
    $this->option->shouldReceive('fromCode')
      ->once()
      ->with('appui', $this->item2)
      ->andReturnNull();

    $this->assertNull(
      $this->option->fromPath('list|appui', '|', $this->item5)
    );
  }

  /** @test */
  public function fromPath_method_returns_null_when_check_method_returns_false()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->option->fromPath('list|appui')
    );
  }

  /** @test */
  public function toPath_method_concatenates_the_codes_and_separator()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->option->shouldReceive('getPathArray')
      ->once()
      ->with($this->item, $this->item5)
      ->andReturn(['code_1', 'code_2']);

    $this->assertSame(
      'code_1,code_2',
      $this->option->toPath($this->item, ',', $this->item5)
    );
  }

  /** @test */
  public function toPath_method_returns_null_when_no_path_results_found()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->twice()
      ->andReturnTrue();

    $this->option->shouldReceive('getPathArray')
      ->once()
      ->andReturnNull();

    $this->assertNull(
      $this->option->toPath($this->item)
    );

    // Another test
    $this->option->shouldReceive('getPathArray')
      ->once()
      ->andReturn([]);

    $this->assertNull(
      $this->option->toPath($this->item)
    );
  }

  /** @test */
  public function toPath_method_returns_null_when_check_method_returns_false()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->option->toPath($this->item)
    );
  }

  /** @test */
  public function set_method_updates_an_option_row_without_changing_the_config_and_returns_number_of_affected_rows()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $data = [
      $this->arch['id']  => $this->item,
      $this->arch['text'] => 'some text',
      $this->arch['code'] => 'some_code',
      $this->arch['value'] => 'some_value',
      $this->arch['id_parent'] => $this->item4
    ];

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [
          $this->arch['text'] => 'some text',
          $this->arch['code'] => 'some_code',
          $this->arch['id_alias'] => null,
          $this->arch['value'] => 'some_value'
        ],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn(1);

    $this->option->shouldReceive('option')
      ->once()
      ->with($this->item4)
      ->andReturn([
        $this->arch['id'] => $this->item4, // id_parent
        $this->arch['code'] => 'parent_code'
      ]);

    $this->option->shouldReceive('exists')
      ->once()
      ->with($this->item4) // id_parent
      ->andReturnTrue();

    $this->option->shouldReceive('isSortable')
      ->once()
      ->with($this->item4) // id_parent
      ->andReturnFalse();

    $this->option->shouldReceive('deleteCache')
      ->once()
      ->with($this->item)
      ->andReturnSelf();

    $this->assertSame(
      1,
      $this->option->set($this->item, $data)
    );
  }

  /** @test */
  public function set_method_returns_zero_when_fails_to_update_in_database()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $data = [
      $this->arch['id']  => $this->item,
      $this->arch['text'] => 'some text',
      $this->arch['code'] => 'some_code',
      $this->arch['value'] => 'some_value',
      $this->arch['id_parent'] => $this->item4
    ];

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [
          $this->arch['text'] => 'some text',
          $this->arch['code'] => 'some_code',
          $this->arch['id_alias'] => null,
          $this->arch['value'] => 'some_value'
        ],
        [$this->arch['id'] => $this->item]
      )
      ->andReturnNull();

    // _prepare method expectation
    $this->option->shouldReceive('option')
      ->once()
      ->with($this->item4)
      ->andReturn([
        $this->arch['id'] => $this->item4, // id_parent
        $this->arch['code'] => 'parent_code'
      ]);

    // _prepare method expectation
    $this->option->shouldReceive('exists')
      ->once()
      ->with($this->item4) // id_parent
      ->andReturnTrue();

    // _prepare method expectation
    $this->option->shouldReceive('isSortable')
      ->once()
      ->with($this->item4) // id_parent
      ->andReturnFalse();

    $this->assertSame(
      0,
      $this->option->set($this->item, $data)
    );
  }

  /** @test */
  public function set_method_returns_null_when_check_method_returns_false()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->option->set($this->item, ['a' => 'b'])
    );
  }

  /** @test */
  public function merge_method_updates_an_option_row_by_merging_data_and_config()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $data = [
      $this->arch['id']  => $this->item,
      $this->arch['text'] => 'new_text',
      $this->arch['code'] => 'new_code',
      $this->arch['value'] => 'new_value',
      $this->arch['id_parent'] => $this->item4
    ];

    $cfg = ['key_1' => 'new_value_1', 'key_2' => 'new_value_2'];

    $this->option->shouldReceive('option')
      ->once()
      ->with($this->item)
      ->andReturn($old_data = [
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'old_code',
        $this->arch['text'] => 'old_text',
        $this->arch['code'] => 'old_code',
        $this->arch['value'] => 'old_value',
      ]);

    // _prepare method expectation
    $this->option->shouldReceive('option')
      ->once()
      ->with($this->item4)
      ->andReturn([
        $this->arch['id'] => $this->item4, // id_parent
        $this->arch['code'] => 'parent_code'
      ]);

    // _prepare method expectation
    $this->option->shouldReceive('exists')
      ->once()
      ->with($this->item4) // id_parent
      ->andReturnTrue();

    // _prepare method expectation
    $this->option->shouldReceive('isSortable')
      ->once()
      ->with($this->item4) // id_parent
      ->andReturnFalse();

    $this->option->shouldReceive('getRawCfg')
      ->once()
      ->with($this->item)
      ->andReturn(json_encode([
        'key_1' => 'old_value_1',
        'key_2' => 'old_value_2'
      ]));

    // Updating database expectation
    $update_data = array_merge($old_data, $data, [
      $this->arch['id_alias'] => null,
      $this->arch['cfg'] => json_encode($cfg)
    ]);
    unset($update_data[$this->arch['id']]);

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $update_data,
        [$this->arch['id'] => $this->item]
      )
      ->andReturn(1);

    $this->option->shouldReceive('deleteCache')
      ->once()
      ->with($this->item)
      ->andReturnSelf();

    $this->assertSame(
      1,
      $this->option->merge($this->item, $data, $cfg)
    );
  }

  /** @test */
  public function merge_method_returns_zero_when_fails_to_update_the_database()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $data = [];
    $cfg  = [];

    $this->option->shouldReceive('option')
      ->once()
      ->with($this->item)
      ->andReturn($old_data = [
        $this->arch['id'] => $this->item,
        $this->arch['code'] => 'old_code',
        $this->arch['text'] => 'old_text',
        $this->arch['code'] => 'old_code',
        $this->arch['value'] => 'old_value',
      ]);

    // Updating database expectation
    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [],
        [$this->arch['id'] => $this->item]
      )
      ->andReturnNull();

    $this->assertSame(
      0,
      $this->option->merge($this->item, $data, $cfg)
    );
  }

  /** @test */
  public function merge_method_returns_null_when_fails_to_retrieve_option_content_for_the_given_id()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->option->shouldReceive('option')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertNull(
      $this->option->merge($this->item, [], [])
    );
  }

  /** @test */
  public function merge_method_returns_null_when_check_method_returns_false()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->option->merge($this->item, [], [])
    );
  }

  /** @test */
  public function remove_method_deletes_a_row_from_the_option_table_and_cache_and_fixes_order()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with('list')
      ->andReturn($this->item);

    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item)
      ->andReturn($this->item5);

    $this->option->shouldReceive('items')
      ->with($this->item)
      ->once()
      ->andReturn([$this->item2, $this->item3]);

    // First recursive call for $this->item2
    $this->option->shouldReceive('fromCode')
      ->once()
      ->with($this->item2)
      ->andReturn($this->item2);

    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item2)
      ->andReturn($this->item4);

    $this->option->shouldReceive('items')
      ->with($this->item2)
      ->once()
      ->andReturn([$this->item5]);

    $this->option->shouldReceive('deleteCache')
      ->once()
      ->with($this->item2)
      ->andReturnSelf();

    $this->db_mock->shouldReceive('delete')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['id'] => $this->item2]
      )
      ->andReturn(1);

    $this->option->shouldReceive('isSortable')
      ->once()
      ->with($this->item4) // the parent
      ->andReturnFalse();

    // Second recursive call for $this->item5 called from the first recursive call
    $this->option->shouldReceive('fromCode')
      ->once()
      ->with($this->item5)
      ->andReturn($this->item5);

    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item5)
      ->andReturn($this->item2);

    $this->option->shouldReceive('items')
      ->with($this->item5)
      ->once()
      ->andReturnNull();

    $this->option->shouldReceive('deleteCache')
      ->once()
      ->with($this->item5)
      ->andReturnSelf();

    $this->db_mock->shouldReceive('delete')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['id'] => $this->item5]
      )
      ->andReturn(1);

    $this->option->shouldReceive('isSortable')
      ->once()
      ->with($this->item2) // the parent
      ->andReturnFalse();

    // Third recursive call for $this->item3 called from the original method call
    $this->option->shouldReceive('fromCode')
      ->once()
      ->with($this->item3)
      ->andReturn($this->item3);

    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item3)
      ->andReturn($this->item2);

    $this->option->shouldReceive('items')
      ->with($this->item3)
      ->once()
      ->andReturnNull();

    $this->option->shouldReceive('deleteCache')
      ->once()
      ->with($this->item3)
      ->andReturnSelf();

    $this->db_mock->shouldReceive('delete')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['id'] => $this->item3]
      )
      ->andReturn(1);

    $this->option->shouldReceive('isSortable')
      ->once()
      ->with($this->item2) // the parent
      ->andReturnFalse();

    // Back to original method call
    $this->option->shouldReceive('deleteCache')
      ->once()
      ->with($this->item)
      ->andReturnSelf();

    $this->db_mock->shouldReceive('delete')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn(1);

    $this->option->shouldReceive('isSortable')
      ->once()
      ->with($this->item5)
      ->andReturnTrue();

    $this->option->shouldReceive('fixOrder')
      ->once()
      ->with($this->item5)
      ->andReturnSelf();

    $this->assertSame(
      4,
      $this->option->remove('list')
    );
  }

  /** @test */
  public function remove_method_returns_false_when_id_parent_of_the_give_code_cannot_be_retrieved()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with('list')
      ->andReturn($this->item);

    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertNull(
      $this->option->remove('list')
    );
  }

  /** @test */
  public function remove_method_returns_null_when_the_given_code_is_same_as_root()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with('list')
      ->andReturn($this->root);

    $this->assertNull(
      $this->option->remove('list')
    );
  }

  /** @test */
  public function remove_method_returns_null_when_the_given_code_is_same_as_the_default()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with('list')
      ->andReturn($this->default);

    $this->assertNull(
      $this->option->remove('list')
    );
  }

  /** @test */
  public function remove_method_returns_null_when_the_given_code_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with('list')
      ->andReturnNull();

    $this->assertNull(
      $this->option->remove('list')
    );
  }

  /** @test */
  public function remove_method_returns_null_when_the_given_code_has_an_id_that_is_not_uid()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with('list')
      ->andReturn('aaaa123');

    $this->assertNull(
      $this->option->remove('list')
    );
  }

  /** @test */
  public function removeFull_method_removes_option_row_from_options_table_and_all_its_hierarchical_structure_and_deletes_the_cache()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('deleteCache')
      ->once()
      ->with($this->item)
      ->andReturnSelf();

    $this->option->shouldReceive('treeIds')
      ->once()
      ->with($this->item)
      ->andReturn($ids = [$this->item, $this->item2, $this->item3, $this->item4]);

    foreach ($ids as $id) {
      $this->db_mock->shouldReceive('delete')
        ->once()
        ->with(
          $this->class_cfg['table'],
          [$this->arch['id'] => $id]
        )
        ->andReturn(1);
    }

    $this->assertSame(
      4,
      $this->option->removeFull('list')
    );
  }

  /** @test */
  public function removeFull_method_removes_option_row_and_all_its_hierarchical_structure_from_history_table_and_deletes_the_cache()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->item);

    $this->option->shouldReceive('deleteCache')
      ->once()
      ->with($this->item)
      ->andReturnSelf();

    $this->option->shouldReceive('treeIds')
      ->once()
      ->with($this->item)
      ->andReturn($ids = [$this->item, $this->item2, $this->item3, $this->item4]);

    // History class expectations
    $this->db_mock->shouldReceive('getHash')
      ->once()
      ->andReturn('1aa2');

    $this->db_mock->shouldReceive('check')
      ->twice()
      ->andReturnTrue();

    $this->db_mock->shouldReceive('getCurrent')
      ->once()
      ->andReturn('db');

    $this->db_mock->shouldReceive('getForeignKeys')
      ->once()
      ->andReturn([$this->class_cfg['table'] => 'bbn_uid']);

    $this->db_mock->shouldReceive('setTrigger')
      ->once()
      ->andReturnSelf();

    $this->db_mock->shouldReceive('tfn')
      ->once()
      ->andReturn($this->class_cfg['table']);

    History::init($this->db_mock, []);
    History::enable();

    // Back to the removeFull method
    foreach ($ids as $id) {
      $this->db_mock->shouldReceive('delete')
        ->once()
        ->with(
          'bbn_history_uids',
          ['bbn_uid' => $id]
        )
        ->andReturn(1);
    }

    $this->assertSame(
      4,
      $this->option->removeFull('list')
    );
  }

  /** @test */
  public function removeFull_method_returns_null_when_the_given_option_is_same_as_root()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->root);

    $this->assertNull(
      $this->option->removeFull('list')
    );
  }

  /** @test */
  public function removeFull_method_returns_null_when_the_given_option_is_same_as_default()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn($this->default);

    $this->assertNull(
      $this->option->removeFull('list')
    );
  }

  /** @test */
  public function removeFull_method_returns_null_when_the_given_option_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturnNull();

    $this->assertNull(
      $this->option->removeFull('list')
    );
  }

  /** @test */
  public function removeFull_method_returns_null_when_the_given_option_has_an_id_that_is_not_uid()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('fromCode')
      ->once()
      ->with(['list'])
      ->andReturn('aaa12');

    $this->assertNull(
      $this->option->removeFull('list')
    );
  }

  /** @test */
  public function setAlias_method_sets_the_given_alias_to_the_given_option()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->db_mock->shouldReceive('updateIgnore')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['id_alias'] => $this->item5],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn(1);

    $this->option->shouldReceive('deleteCache')
      ->once()
      ->with($this->item)
      ->andReturnSelf();

    $this->assertSame(
      1,
      $this->option->setAlias($this->item, $this->item5)
    );
  }

  /** @test */
  public function setAlias_method_sets_the_alias_to_null_for_the_given_option()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->db_mock->shouldReceive('updateIgnore')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['id_alias'] => null],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn(1);

    $this->option->shouldReceive('deleteCache')
      ->once()
      ->with($this->item)
      ->andReturnSelf();

    $this->assertSame(
      1,
      $this->option->setAlias($this->item)
    );
  }

  /** @test */
  public function setAlias_method_does_not_delete_cache_if_failed_to_update_the_alias()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->db_mock->shouldReceive('updateIgnore')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['id_alias'] => null],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn(0);

    $this->option->shouldNotReceive('deleteCache');

    $this->assertSame(
      0,
      $this->option->setAlias($this->item)
    );
  }

  /** @test */
  public function setAlias_method_returns_null_when_check_method_returns_false()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->option->setAlias($this->item)
    );
  }

  /** @test */
  public function setText_method_sets_given_text_to_the_given_option()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->db_mock->shouldReceive('updateIgnore')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['text'] => 'foo'],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn(1);

    $this->option->shouldReceive('deleteCache')
      ->once()
      ->with($this->item)
      ->andReturnSelf();

    $this->assertSame(
      1,
      $this->option->setText($this->item, 'foo')
    );
  }

  /** @test */
  public function setText_method_does_not_delete_the_cache_when_fails_to_update_the_text()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->db_mock->shouldReceive('updateIgnore')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['text'] => 'foo'],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn(0);

    $this->option->shouldNotReceive('deleteCache');

    $this->assertSame(
      0,
      $this->option->setText($this->item, 'foo')
    );
  }

  /** @test */
  public function setText_method_returns_null_when_check_method_returns_false()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->option->setText($this->item, 'foo')
    );
  }

  /** @test */
  public function setCode_method_sets_the_given_code_to_the_given_option()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->db_mock->shouldReceive('updateIgnore')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['code'] => 'new_code'],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn(1);

    $this->assertSame(
      1,
      $this->option->setCode($this->item, 'new_code')
    );
  }

  /** @test */
  public function setCode_method_returns_null_when_check_method_returns_false()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->option->setCode($this->item, 'foo')
    );
  }

  /** @test */
  public function order_method_returns_the_order_of_the_given_option_and_updates_its_position()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item)
      ->andReturn($this->item5);

    $this->option->shouldReceive('isSortable')
      ->once()
      ->with($this->item5) // parent id
      ->andReturnTrue();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['num'],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn(2);

    $this->option->shouldReceive('items')
      ->once()
      ->with($this->item5) // parent
      ->andReturn([$this->item2, $this->item, $this->item3]);

    // First item $this->item2
    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['num'] => 2],
        [$this->arch['id'] => $this->item2]
      )
      ->andReturn(1);

    // Second item $this->item which the one that was the method called with
    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['num'] => 1],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn(1);

    $this->option->shouldReceive('deleteCache')
      ->once()
      ->with($this->item5, true)
      ->andReturnSelf();

    $this->option->shouldReceive('deleteCache')
      ->once()
      ->with($this->item)
      ->andReturnSelf();

    $this->assertSame(
      1,
      $this->option->order($this->item, 1)
    );
  }

  /** @test */
  public function order_method_returns_the_old_order_when_the_provided_position_is_the_same(){
    {
      $this->mockOptionClass();

      $this->option->shouldReceive('check')
        ->once()
        ->andReturnTrue();

      $this->option->shouldReceive('getIdParent')
        ->once()
        ->with($this->item)
        ->andReturn($this->item5);

      $this->option->shouldReceive('isSortable')
        ->once()
        ->with($this->item5) // parent id
        ->andReturnTrue();

      $this->db_mock->shouldReceive('selectOne')
        ->once()
        ->with(
          $this->class_cfg['table'],
          $this->arch['num'],
          [$this->arch['id'] => $this->item]
        )
        ->andReturn(2);

      $this->assertSame(
        2,
        $this->option->order($this->item, 2)
      );
    }
  }

  /** @test */
  public function order_method_returns_the_old_order_when_no_position_is_provided()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item)
      ->andReturn($this->item5);

    $this->option->shouldReceive('isSortable')
      ->once()
      ->with($this->item5) // parent id
      ->andReturnTrue();

    $this->db_mock->shouldReceive('selectOne')
      ->once()
      ->with(
        $this->class_cfg['table'],
        $this->arch['num'],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn(20);

    $this->assertSame(
      20,
      $this->option->order($this->item)
    );
  }

  /** @test */
  public function order_method_returns_null_when_parent_is_not_sortable()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item)
      ->andReturn($this->item5);

    $this->option->shouldReceive('isSortable')
      ->once()
      ->with($this->item5)
      ->andReturnFalse();

    $this->assertNull(
      $this->option->order($this->item)
    );
  }

  /** @test */
  public function order_method_returns_null_when_fails_to_retrieve_id_paren_for_the_given_id()
  {
    $this->mockOptionClass();

    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->option->shouldReceive('getIdParent')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertNull(
      $this->option->order($this->item)
    );
  }

  /** @test */
  public function order_method_returns_null_when_check_method_returns_false()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->option->order($this->item)
    );
  }

  /** @test */
  public function setProp_method_updates_the_given_option_properties_derived_from_the_value_columns()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('optionNoAlias')
      ->once()
      ->with($this->item)
      ->andReturn([
        $this->arch['id'] => $this->item,
        'prop_1' => 'old_value_1'
      ]);

    $this->option->shouldReceive('set')
      ->once()
      ->with($this->item, [
        $this->arch['id'] => $this->item,
        'prop_1' => 'new_value_1',
        'prop_2' => 'value_2'
      ])
      ->andReturn(1);

    $this->assertSame(
      1,
      $this->option->setProp($this->item, ['prop_1' => 'new_value_1', 'prop_2' => 'value_2'])
    );
  }

  /** @test */
  public function setProp_method_updates_the_given_option_properties_derived_from_the_value_columns_when_prop_value_provided_as_string()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('optionNoAlias')
      ->once()
      ->with($this->item)
      ->andReturn([
        $this->arch['id'] => $this->item,
        'prop_1' => 'old_value_1'
      ]);

    $this->option->shouldReceive('set')
      ->once()
      ->with($this->item, [
        $this->arch['id'] => $this->item,
        'prop_1' => 'new_value_1'
      ])
      ->andReturn(1);

    $this->assertSame(
      1,
      $this->option->setProp($this->item, 'prop_1', 'new_value_1')
    );
  }

  /** @test */
  public function setProp_method_returns_zero_when_the_provided_property_is_not_an_array()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('optionNoAlias')
      ->once()
      ->with($this->item)
      ->andReturn([
        $this->arch['id'] => $this->item,
        'prop_1' => 'old_value_1'
      ]);

    $this->assertSame(
      0,
      $this->option->setProp($this->item, 'foo')
    );
  }

  /** @test */
  public function setProp_method_returns_zero_when_no_values_are_changed()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('optionNoAlias')
      ->once()
      ->with($this->item)
      ->andReturn([
        $this->arch['id'] => $this->item,
        'prop_1' => 'old_value_1'
      ]);

    $this->assertSame(
      0,
      $this->option->setProp($this->item, ['prop_1' => 'old_value_1'])
    );
  }

  /** @test */
  public function setProp_method_returns_null_when_fails_to_retrieve_full_option_for_the_given_id()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('optionNoAlias')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertNull(
      $this->option->setProp($this->item, ['a' => 'b'])
    );
  }

  /** @test */
  public function setProp_method_returns_null_when_the_give_prop_or_id_are_empty()
  {
    $this->assertNull(
      $this->option->setProp($this->item, [])
    );

    $this->assertNull(
      $this->option->setProp('', 'foo')
    );

    $this->assertNull(
      $this->option->setProp($this->item, '')
    );
  }

  /** @test */
  public function getProp_method_returns_an_option_single_property()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('option')
      ->with($this->item)
      ->once()
      ->andReturn([
        $this->arch['id'] => $this->item,
        'prop_1' => 'value_1'
      ]);

    $this->assertSame(
      'value_1',
      $this->option->getProp($this->item, 'prop_1')
    );
  }

  /** @test */
  public function getProp_method_returns_null_when_the_given_property_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('option')
      ->with($this->item)
      ->once()
      ->andReturn([
        $this->arch['id'] => $this->item,
        'prop_1' => 'value_1'
      ]);

    $this->assertNull(
      $this->option->getProp($this->item, 'prop_2')
    );
  }

  /** @test */
  public function getProp_method_returns_null_when_fails_to_get_full_option_content_for_the_given_id()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('option')
      ->with($this->item)
      ->once()
      ->andReturnNull();

    $this->assertNull(
      $this->option->getProp($this->item, 'prop')
    );
  }

  /** @test */
  public function getProp_method_returns_null_when_given_id_or_prop_are_empty()
  {
    $this->assertNull(
      $this->option->getProp($this->item, '')
    );

    $this->assertNull(
      $this->option->getProp('', 'prop')
    );
  }

  /** @test */
  public function unsetProp_method_unsets_the_given_property_for_the_given_option_id()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('optionNoAlias')
      ->twice()
      ->with($this->item)
      ->andReturn([
        $this->arch['id'] => $this->item,
        'prop_1' => 'value_1',
        'prop_2' => 'value_2',
      ]);

    $this->option->shouldReceive('set')
      ->twice()
      ->with($this->item, [
        $this->arch['id'] => $this->item,
        'prop_2' => 'value_2',
      ])
      ->andReturn(1);

    $this->assertSame(
      1,
      $this->option->unsetProp($this->item, 'prop_1')
    );

    $this->assertSame(
      1,
      $this->option->unsetProp($this->item, ['prop_1', 'prop_3'])
    );
  }

  /** @test */
  public function unsetProp_method_does_not_unset_the_prop_and_returns_null_if_the_given_one_is_one_of_the_fields_or_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('optionNoAlias')
      ->once()
      ->with($this->item)
      ->andReturn([
        $this->arch['id'] => $this->item,
        'prop_1' => 'value_1',
        $this->arch['code'] => 'some_code'
      ]);

    $this->option->shouldNotReceive('set');

    $this->assertNull(
      $this->option->unsetProp($this->item, ['prop_2', $this->arch['code']])
    );
  }

  /** @test */
  public function unsetProp_method_returns_null_when_fails_to_retreive_option_content()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('optionNoAlias')
      ->once()
      ->with($this->item)
      ->andReturnNull();

    $this->assertNull(
      $this->option->unsetProp($this->item, 'prop')
    );
  }

  /** @test */
  public function unsetProp_method_returns_null_when_the_given_id_is_not_uid_or_the_given_prop_is_empty()
  {
    $this->assertNull(
      $this->option->unsetProp('123aa', 'prop')
    );

    $this->assertNull(
      $this->option->unsetProp($this->item, '')
    );

    $this->assertNull(
      $this->option->unsetProp($this->item, [])
    );

    $this->assertNull(
      $this->option->unsetProp('', [])
    );
  }

  /** @test */
  public function setCfg_method_sets_the_cfg_column_of_the_given_option_in_the_table_through_an_array_and_merge_is_enabled()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->option->shouldReceive('exists')
      ->once()
      ->with($this->item)
      ->andReturnTrue();

    // Old config
    $this->option->shouldReceive('getCfg')
      ->once()
      ->with($this->item)
      ->andReturn([
        'c' => 'e',
        'z' => 'y',
        'inheritance' => false
      ]);

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [
          $this->arch['cfg'] => json_encode([
            'c' => 'd',
            'z' => 'y',
            'inheritance' => true,
            'a' => 'b'
          ])
        ],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn(1);

    $this->option->shouldReceive('deleteCache')
      ->once()
      ->with($this->item, true)
      ->andReturnSelf();

    $this->assertSame(
      1,
      $this->option->setCfg($this->item, [
        'c' => 'd',
        'a' => 'b',
        'inherited_from' => true,
        $this->arch['id'] => $this->item,
        'permissions' => 'foo',
        'inheritance' => true
      ], true)
    );
  }

  /** @test */
  public function setCfg_method_sets_the_cfg_column_of_the_given_option_in_the_table_through_an_array_and_merge_is_disabled()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->option->shouldReceive('exists')
      ->once()
      ->with($this->item)
      ->andReturnTrue();

    $this->option->shouldNotReceive('getCfg');

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [
          $this->arch['cfg'] => json_encode([
            'c' => 'd',
            'a' => 'b',
            'permissions' => 'cascade',
            'inheritance' => true
          ])
        ],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn(1);

    $this->option->shouldReceive('deleteCache')
      ->once()
      ->with($this->item)
      ->andReturnSelf();

    $this->assertSame(
      1,
      $this->option->setCfg($this->item, [
        'c' => 'd',
        'a' => 'b',
        'permissions' => 'cascade',
        'inheritance' => true
      ])
    );
  }

  /** @test */
  public function setCfg_method_returns_null_when_the_given_id_option_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->option->shouldReceive('exists')
      ->once()
      ->with($this->item)
      ->andReturnFalse();

    $this->assertNull(
      $this->option->setCfg($this->item, [])
    );
  }

  /** @test */
  public function setCfg_method_returns_null_when_check_method_returns_false()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertNull(
      $this->option->setCfg($this->item, [])
    );
  }

  /** @test */
  public function unsetCfg_method_sets_the_config_column_to_null_for_the_given_option_id()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->option->shouldReceive('exists')
      ->once()
      ->with($this->item)
      ->andReturnTrue();

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['cfg'] => null],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn(1);

    $this->option->shouldReceive('deleteCache')
      ->once()
      ->with($this->item)
      ->andReturnSelf();

    $this->assertSame(
      1,
      $this->option->unsetCfg($this->item)
    );
  }

  /** @test */
  public function unsetCfg_method_does_not_delete_cache_when_fails_to_update_the_config_column()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->option->shouldReceive('exists')
      ->once()
      ->with($this->item)
      ->andReturnTrue();

    $this->db_mock->shouldReceive('update')
      ->once()
      ->with(
        $this->class_cfg['table'],
        [$this->arch['cfg'] => null],
        [$this->arch['id'] => $this->item]
      )
      ->andReturn(0);

    $this->option->shouldNotReceive('deleteCache');

    $this->assertSame(
      0,
      $this->option->unsetCfg($this->item)
    );
  }

  /** @test */
  public function unsetCfg_method_returns_false_when_the_given_id_option_does_not_exist()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnTrue();

    $this->option->shouldReceive('exists')
      ->once()
      ->with($this->item)
      ->andReturnFalse();

    $this->assertFalse(
      $this->option->unsetCfg($this->item)
    );
  }

  /** @test */
  public function unsetCfg_method_returns_false_when_check_method_returns_false()
  {
    $this->mockOptionClass();

    $this->option->shouldReceive('check')
      ->once()
      ->andReturnFalse();

    $this->assertFalse(
      $this->option->unsetCfg($this->item)
    );
  }
}