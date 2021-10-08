<?php

namespace Appui;

use bbn\Appui\Search;
use bbn\Db;
use bbn\Mvc;
use bbn\User;
use bbn\Util\Timer;
use Opis\Closure\SerializableClosure;
use PHPUnit\Framework\TestCase;
use tests\Files;
use tests\Reflectable;
use tests\ReflectionHelpers;

class SearchTest extends TestCase
{
  use Reflectable, Files;

  protected Search $search;

  protected $db_mock;

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

  private $search_string = 'foo';

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
    $this->user_mock  = \Mockery::mock(User::class);

    ReflectionHelpers::setNonPublicPropertyValue(
      'retriever_instance', Db::class, $this->db_mock
    );

    ReflectionHelpers::setNonPublicPropertyValue(
      'retriever_instance', User::class, $this->user_mock
    );


    // Create plugins dirs and contents
    $this->createDir($plugin_dir = 'plugins/appui-search/src/mvc/model');
   $this->createFile('users_search.php', <<<CONTENT
<?php 
\$function = function (\$search) {
  return [
    'score' => 30,
    'cfg' => [
      'tables' => ['members'],
      'fields' => ['id', 'name'],
      'where' => ['name' => \$search]
    ],
    'alternates' => [
      [
        'where' => [['name', 'contains', \$search]],
        'score' => 10
      ]
    ]
  ];
};

return [
'myFunction' => \$function
];


CONTENT
, $plugin_dir);

    $this->createFile('profiles_search.php', <<<CONTENT
<?php 
\$function = function (\$search) {
return [
  'score' => 50,
  'regex' => '/^d+$/',
  'cfg' => [
    'tables' => ['members'],
    'fields' => ['id', 'name'],
    'where' => ['id' => \$search]
  ],
  'alternates' => [
    [
      'where' => [['id', 'contains', \$search]],
      'score' => 15
    ]
  ]
];
};

return [
'closure' => \$function
];

CONTENT
      , $plugin_dir);

    $this->createDir($plugin_dir_2 = 'plugins/appui-plugin-1/src/mvc/model');
    $this->createFile('users_search.php', <<<CONTENT
<?php 
 \$function = function () use (\$model) {
return [
  'score' => 40,
  'type' => 'url',
  'cfg' => [
    'tables' => ['members'],
    'fields' => ['id', 'name'],
    'where' => ['id' => \$model->data['search']]
  ]
];
};

return [
'func' => \$function
];

CONTENT
      , $plugin_dir_2);

    $this->ctrl   = new Mvc\Controller(self::$mvc, []);
    $this->search = new Search($this->ctrl, $this->cfg);
    $this->arch   = $this->cfg['arch'];
  }

  protected function tearDown(): void
  {
    \Mockery::close();
//    $this->cleanTestingDir();
  }

  /**
   * Returns the expected extracted search config throughout the tests.
   *
   * @param string|null $search
   * @return array
   */
  protected function getExpectedSearchCfg(?string $search = null)
  {
    $search = $search ?? $this->search_string;

    return [
      [
        'score' => 50,
        'regex' => '/^d+$/',
        'cfg' => [
          'tables' => ['members'],
          'fields' => ['id', 'name'],
          'where' => ['id' => $search]
        ],
        'alternates' => [
          [
            'where' => [['id', 'contains', $search]],
            'score' => 15
          ]
        ],
        'file' => BBN_DATA_PATH . "plugins/appui-search/src/mvc/model/profiles_search.php",
        'num' => 0
      ],
//      [
//        'score' => 40,
//        'type' => 'url',
//        'cfg' => [
//          'tables' => ['members'],
//          'fields' => ['id', 'name'],
//          'where' => ['id' => $search]
//        ],
//        'file' => BBN_DATA_PATH . "plugins/appui-plugin-1/src/mvc/model/users_search.php",
//        'num' => 1
//      ],
       [
        'score' => 30,
        'cfg' => [
          'tables' => ['members'],
          'fields' => ['id', 'name'],
          'where' => ['name' => $search]
        ],
        'alternates' => [
          [
            'where' => [['name', 'contains', $search]],
            'score' => 10
          ]
        ],
         'file' => BBN_DATA_PATH . "plugins/appui-search/src/mvc/model/users_search.php",
         'num' => 1
      ]
    ];
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
      'bbn/Appui/Search/',
      $this->getNonPublicProperty('_cache_prefix')
    );


    $this->assertIsArray(
      $search_cfg = $this->getNonPublicProperty('search_cfg')
    );

    $this->assertNotEmpty($search_cfg);

    foreach ($search_cfg as $item) {
      $this->assertNotFalse(@unserialize($item));
    }
  }

  /** @test */
  public function getSearchCfg_method_returns_search_config_from_cache_when_exists()
  {
    $this->cleanTestingDir(BBN_APP_PATH . BBN_DATA_PATH . 'plugins');

    $this->setNonPublicPropertyValue('search_cfg', []);

    $result = $this->getNonPublicMethod('getSearchCfg')
      ->invoke($this->search);

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);

    foreach ($result as $item) {
      $this->assertNotFalse(@unserialize($item));
    }
  }
  
  /** @test */
  public function getSearchCfg_method_returns_search_config_and_save_it_in_cache()
  {
    $this->cleanTestingDir($this->getTestingDirName() . 'cache');

    $this->setNonPublicPropertyValue('search_cfg', []);

    $cache_get_method = $this->getNonPublicMethod('cacheGet');

    $this->assertFalse(
      $cache_get_method->invoke(
        $this->search, $this->getNonPublicProperty('cfg_cache_name'), 'getSearchCfg'
      )
    );

    $result = $this->getNonPublicMethod('getSearchCfg')
      ->invoke($this->search);

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);

    foreach ($result as $item) {
      $this->assertNotFalse(@unserialize($item));
    }

   $this->assertNotFalse(
     $cache_get_method->invoke(
       $this->search,
       $this->getNonPublicProperty('cfg_cache_name'),
       'getSearchCfg'
     )
   );
  }

  /** @test */
  public function executeFunctions_method_executes_all_functions_in_the_search_cfg_using_the_given_search_string()
  {
    $method = $this->getNonPublicMethod('executeFunctions');

    $this->assertSame(
      $this->getExpectedSearchCfg(), $method->invoke($this->search, 'foo')
    );

    // Another test with different string
    $this->assertSame(
      $this->getExpectedSearchCfg('bar'),
      $method->invoke($this->search, 'bar')
    );
  }

  /** @test */
  public function get_method_launches_the_search_with_the_given_search_string_and_save_it_in_cache_for_the_user()
  {
    $this->user_mock->shouldReceive('getId')
      ->andReturn($user_id = '634a2c70bcac11eba47652540000cfaa');

//    $this->db_mock->shouldReceive('selectOne')
//      ->twice()
//      ->andReturnNull();
//
//    $this->db_mock->shouldReceive('insert')
//      ->twice()
//      ->andReturn(1);
//
//    $this->db_mock->shouldReceive('lastId')
//      ->twice()
//      ->andReturn('123');

    // Query expectations for the first method call
    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with([
        'tables' => ['members'],
        'fields' => ['id', 'name'],
        'where' => ['id' => 'foo']
      ])
      ->andReturn([
        $expected[] = ['id' => 'foo', 'name' => 'name_1']
      ]);

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with([
        'tables' => ['members'],
        'fields' => ['id', 'name'],
        'where' => ['name' => 'foo']
      ])
      ->andReturn([
        $expected[] = ['id' => 12, 'name' => 'foo']
      ]);

    $cache_get_method = $this->getNonPublicMethod('cacheGet');

    $this->assertFalse(
      $cache_get_method->invoke(
        $this->search,
        $user_id,
        $cache_name = sprintf(
          $this->getNonPublicProperty('search_cache_name'), 'foo'
        )
      )
    );

    $results = $this->search->get('foo');

    $this->assertArrayHasKey('data', $results);
    $this->assertSame($expected, $results['data']);

    // Clear the models to make sure they're not being read again
    $this->cleanTestingDir($this->getTestingDirName() . 'plugins');

    $this->assertNotFalse(
      $cache_get_method->invoke(
        $this->search, $user_id, $cache_name
      )
    );

    // Query expectations for the second method call
    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with([
        'tables' => ['members'],
        'fields' => ['id', 'name'],
        'where' => ['id' => 'foo']
      ])
      ->andReturn([
        $expected_2[] = ['id' => 'foo', 'name' => 'name_1']
      ]);

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with([
        'tables' => ['members'],
        'fields' => ['id', 'name'],
        'where' => ['name' => 'foo']
      ])
      ->andReturn([
        $expected_2[] = ['id' => 12, 'name' => 'foo']
      ]);

    $results_2 = $this->search->get('foo');

    $this->assertArrayHasKey('data', $results);
    $this->assertSame($expected_2, $results_2['data']);
  }

  /** @test */
  public function get_method_returns_the_next_step_when_query_time_limit_is_passed()
  {
    $this->user_mock->shouldReceive('getId')
      ->andReturn('634a2c70bcac11eba47652540000cfaa');

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with([
        'tables' => ['members'],
        'fields' => ['id', 'name'],
        'where' => ['id' => 'bar']
      ])
      ->andReturn([
        $expected[] = ['id' => 'bar', 'name' => 'name_1']
      ]);

    $timer_mock = \Mockery::mock(Timer::class);

    $timer_mock->shouldReceive('start')
      ->once()
      ->andReturnTrue();

    // First loop timer measure expectations to pass time limit
    $timer_mock->shouldReceive('measure')
      ->with('search')
      ->once()
      ->andReturn(
        $this->getNonPublicProperty('time_limit') + 4
      );


    $timer_mock->shouldReceive('stop')
      ->once()
      ->andReturnTrue();

    $this->setNonPublicPropertyValue('timer', $timer_mock);

    $result = $this->search->get('bar');

    $this->assertArrayHasKey('data', $result);
    $this->assertArrayHasKey('next_step', $result);

    $this->assertSame($expected, $result['data']);
    $this->assertSame(1, $result['next_step']);
  }

  /** @test */
  public function get_method_does_not_return_the_next_step_if_time_out_has_passed_but_there_is_not_other_steps_remaining()
  {
    $this->user_mock->shouldReceive('getId')
      ->andReturn('634a2c70bcac11eba47652540000cfaa');

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with([
        'tables' => ['members'],
        'fields' => ['id', 'name'],
        'where' => ['id' => 'bar']
      ])
      ->andReturn([
        $expected[] = ['id' => 'bar', 'name' => 'name_1']
      ]);

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with([
        'tables' => ['members'],
        'fields' => ['id', 'name'],
        'where' => ['name' => 'bar']
      ])
      ->andReturn([
        $expected[] = ['id' => 12, 'name' => 'bar']
      ]);

    $timer_mock = \Mockery::mock(Timer::class);

    $timer_mock->shouldReceive('start')
      ->once()
      ->andReturnTrue();

    // First loop timer measure expectations to not pass time limit
    $timer_mock->shouldReceive('measure')
      ->with('search')
      ->once()
      ->andReturn(
        $this->getNonPublicProperty('time_limit') - 4
      );

    // Second loop timer measure expectations to pass time limit but no steps remaining
    $timer_mock->shouldReceive('measure')
      ->with('search')
      ->once()
      ->andReturn(
        $this->getNonPublicProperty('time_limit') + 4
      );


    $timer_mock->shouldReceive('stop')
      ->once()
      ->andReturnTrue();

    $this->setNonPublicPropertyValue('timer', $timer_mock);

    $result = $this->search->get('bar');

    $this->assertArrayHasKey('data', $result);
    $this->assertArrayNotHasKey('next_step', $result);
    $this->assertSame($expected, $result['data']);
  }
}