<?php

namespace Appui;

use bbn\Appui\Search;
use bbn\Cache;
use bbn\Db;
use bbn\Mvc;
use bbn\User;
use PHPUnit\Framework\TestCase;
use tests\Files;
use tests\Reflectable;
use tests\ReflectionHelpers;

class SearchTest extends TestCase
{
  use Reflectable, Files;

  protected Search $search;

  protected $db_mock;

  protected $cache_mock;

  protected $user_mock;

  protected $ctrl = null;

  protected static $mvc = null;

  protected $cfg = [
    'table' => 't_bbn_search',
    'tables' => [
      'search' => 't_bbn_search',
      'search_results' => 't_bbn_search_results'
    ],
    'arch' => [
      'search' => [
        'id' => 't_id',
        'id_user' => 't_id_user',
        'value' => 't_value',
        'num' => 't_num',
        'last' => 't_last'
      ],
      'search_results' => [
        'id' => 't_id',
        'id_search' => 't_id_search',
        'table' => 't_table',
        'uid' => 't_uid',
        'num' => 't_num',
        'last' => 't_ast'
      ]
    ]
  ];

  protected array $arch;

  protected $search_cfg = [
    'users_search' => [
      'score' => 4,
      'cfg' => ['a' => 'b']
    ],
    'profiles_search' => [
      'score' => 6,
      'cfg' => ['c' => 'd']
    ]
  ];

  public function getInstance()
  {
    return $this->search;
  }


  protected function setUp(): void
  {
    $this->cleanTestingDir();

    if (!self::$mvc) {
      self::$mvc = new Mvc($this->db_mock, [
        'root' => [
          'appui-search' => [
            'name' => 'appui-search',
            'url'  => 'plugins/appui-search',
            'path' => $this->getTestingDirName() . 'plugins/appui-search'
          ],
          'appui-plugin-1' => [
            'name' => 'appui-plugin-1',
            'url'  => 'plugins/appui-plugin-1',
            'path' => $this->getTestingDirName() . 'plugins/appui-plugin-1'
          ],
          'appui-plugin-2' => [
            'name' => 'appui-plugin-2',
            'url'  => 'plugins/appui-plugin-2',
            'path' => $this->getTestingDirName() . 'plugins/appui-plugin-2'
          ],
        ]
      ]);
    }

    $this->db_mock    = \Mockery::mock(Db::class);
    $this->cache_mock = \Mockery::mock(Cache::class);
    $this->user_mock  = \Mockery::mock(User::class);

    ReflectionHelpers::setNonPublicPropertyValue(
      'retriever_instance', Db::class, $this->db_mock
    );

    ReflectionHelpers::setNonPublicPropertyValue(
      'retriever_instance', User::class, $this->user_mock
    );

    ReflectionHelpers::setNonPublicPropertyValue(
      'is_init', Cache::class, 1
    );

    ReflectionHelpers::setNonPublicPropertyValue(
      'engine', Cache::class, $this->cache_mock
    );

    // Create plugins dirs and contents
    $this->createDir($plugin_dir = 'plugins/appui-search/src/mvc/model');
   $this->createFile('users_search.php', <<<CONTENT
<?php return [
'score' => 4,
'cfg' => ['a' => 'b'] 
];
CONTENT
, $plugin_dir);

    $this->createFile('profiles_search.php', <<<CONTENT
<?php return [
'score' => 6,
'cfg' => ['c' => 'd'] 
];
CONTENT
      , $plugin_dir);

    $this->createDir($plugin_dir_2 = 'plugins/appui-plugin-1/src/mvc/model');
    $this->createFile('users_search.php', <<<CONTENT
<?php return [
'score' => 4,
'cfg' => ['a' => 'b'] 
];
CONTENT
      , $plugin_dir_2);

    $this->createFile('profiles_search.php', <<<CONTENT
<?php return [
'score' => 6,
'cfg' => ['c' => 'd'] 
];
CONTENT
      , $plugin_dir);

    $this->createDir($plugin_dir_2 = 'plugins/appui-plugin-2/src/mvc/model');
    $this->createFile('users_search.php', <<<CONTENT
<?php return [
'score' => 1,
'cfg' => ['a' => 'b'] 
];
CONTENT
      , $plugin_dir_2);

    $this->createFile('profiles_search.php', <<<CONTENT
<?php return [
'score' => 6,
'cfg' => ['c' => 'd'] 
];
CONTENT
      , $plugin_dir);

    // Cache expectations when calling getSearchCfg in constructor
    $this->cache_mock->shouldReceive('get')
      ->once()
      ->andReturnNull();

    $this->cache_mock->shouldReceive('set')
      ->once()
      ->andReturnTrue();

    $this->ctrl   = new Mvc\Controller(self::$mvc, []);
    $this->search = new Search($this->ctrl, $this->cfg);
    $this->arch   = $this->cfg['arch'];
  }

  protected function tearDown(): void
  {
    \Mockery::close();
//    $this->cleanTestingDir();
  }


  /** @test */
  public function constructor_test()
  {
    $this->assertSame(
      $this->db_mock,
      $this->getNonPublicProperty('db')
    );

    $this->assertSame(
      $this->cfg,
      $this->search->getClassCfg()
    );

    $this->assertSame(
      $this->cache_mock,
      $this->getNonPublicProperty('cache_engine')
    );

    $this->assertSame(
      'bbn/Appui/Search/',
      $this->getNonPublicProperty('_cache_prefix')
    );

    $this->assertSame([
      [
        'path' => BBN_DATA_PATH . "plugins/appui-search/src/mvc/model/profiles_search.php",
        'score' => $this->search_cfg['profiles_search']['score'],
        'cfg' => $this->search_cfg['profiles_search']['cfg']
      ],
      [
        'path' => BBN_DATA_PATH . "plugins/appui-search/src/mvc/model/users_search.php",
        'score' => $this->search_cfg['users_search']['score'],
        'cfg' => $this->search_cfg['users_search']['cfg']
      ]
    ], $this->getNonPublicProperty('search_cfg'));
  }

  /** @test */
  public function getSearchCfg_method_returns_search_config_from_cache_when_exists()
  {
    $method = $this->getNonPublicMethod('getSearchCfg');

    $this->cache_mock->shouldReceive('get')
      ->with(
        $this->getNonPublicProperty('_cache_prefix')
        . $this->getNonPublicProperty('cache_name')
        . '/getSearchCfg'
      )
      ->andReturn($expected = [[
        'score' => 2,
        'cfg' => ['a' => 'b']
      ]]);

    $this->assertSame(
      $expected,
      $method->invoke($this->search)
    );
  }
  
  /** @test */
  public function getSearchCfg_method_returns_search_config_and_save_it_in_cache()
  {
    $method = $this->getNonPublicMethod('getSearchCfg');

    $this->cache_mock->shouldReceive('get')
      ->once()
      ->andReturnNull();

    $expected = [
      [
        'path' => BBN_DATA_PATH . "plugins/appui-search/src/mvc/model/profiles_search.php",
        'score' => $this->search_cfg['profiles_search']['score'],
        'cfg' => $this->search_cfg['profiles_search']['cfg']
      ],
      [
        'path' => BBN_DATA_PATH . "plugins/appui-search/src/mvc/model/users_search.php",
        'score' => $this->search_cfg['users_search']['score'],
        'cfg' => $this->search_cfg['users_search']['cfg']
      ]
    ];

    $this->cache_mock->shouldReceive('set')
      ->once()
      ->with(
        $this->getNonPublicProperty('_cache_prefix')
        . $this->getNonPublicProperty('cache_name')
        . '/getSearchCfg',
        $expected,
        0
      )
      ->andReturnTrue();


    $this->assertSame(
      $expected,
      $method->invoke($this->search)
    );
  }
}