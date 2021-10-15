<?php

namespace Appui;

use bbn\Appui\Search;
use bbn\Db;
use bbn\Mvc;
use bbn\User;
use bbn\Util\Timer;
use PHPUnit\Framework\TestCase;
use tests\MysqlDbSetup;
use tests\Files;
use tests\Reflectable;
use tests\ReflectionHelpers;

class SearchTest extends TestCase
{
  use Reflectable, Files, MysqlDbSetup;

  protected Search $search;

  protected $db_mock;

  protected ?Db $db = null;

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
        'num' => 't_num',
        'last' => 't_ast',
        'signature' => 't_signature',
        'result' => 't_result'
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
    $this->dropAllTables();
    $this->cleanTestingDir();

    $this->setNonPublicPropertyValue('functions', [], Search::class);

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
    $this->createDir($plugin_dir = 'plugins/appui-search/src/mvc/private');
    $file = $this->createFile('users_search.php', <<<CONTENT
<?php 
use bbn\Appui\Search;
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

Search::register(\$function, 'main');


CONTENT
, $plugin_dir);

    include $file;

    $file = $this->createFile('profiles_search.php', <<<CONTENT
<?php 
use bbn\Appui\Search;

\$function = function (\$search) {
return [
  'score' => 50,
  'regex' => '/^d+$/',
  'cfg' => [
    'tables' => ['members'],
    'fields' => ['id', 'name'],
   'where' => [['name', 'contains', \$search]]
  ],
  'alternates' => [
    [
      'where' => ['name' => \$search],
      'score' => 15
    ]
  ]
];
};

Search::register(\$function, 'main');

CONTENT
      , $plugin_dir);

    include $file;

    $this->createDir($plugin_dir_2 = 'plugins/appui-plugin-1/src/mvc/private');
    $file = $this->createFile('users_search.php', <<<CONTENT
<?php 
use bbn\Appui\Search;

 \$function = function (\$search) {
return [
  'score' => 40,
  'type' => 'url',
  'cfg' => [
    'tables' => ['members'],
    'fields' => ['id', 'name'],
    'where' => ['id' => \$search]
  ]
];
};

Search::register(\$function, 'appui-plugin-1');

CONTENT
      , $plugin_dir_2);

    include $file;

    $this->ctrl   = new Mvc\Controller(self::$mvc, []);
    $this->search = new Search($this->ctrl, $this->cfg);
    $this->arch   = $this->cfg['arch'];
  }

  protected function setUpDb()
  {
    self::parseEnvFile();
    self::createTestingDatabase();

    $this->db = new Db(self::getDbConfig());

    $this->setNonPublicPropertyValue('db', $this->db );

    $this->createTable($this->cfg['table'], function () {
      return "
      {$this->arch['search']['id']} BINARY(16) PRIMARY KEY,
      {$this->arch['search']['id_user']} BINARY(16),
      {$this->arch['search']['value']} VARCHAR(255),
      {$this->arch['search']['num']} INT(11) DEFAULT 0,
      {$this->arch['search']['last']} INT(11) DEFAULT 0
      ";
    });

    $this->createTable($this->cfg['tables']['search_results'], function () {
      return "
      {$this->arch['search_results']['id']} BINARY(16) PRIMARY KEY,
      {$this->arch['search_results']['id_search']} BINARY(16),
      {$this->arch['search_results']['num']} INT(11) DEFAULT 0,
      {$this->arch['search_results']['last']} INT(11) DEFAULT 0,
      {$this->arch['search_results']['signature']} VARCHAR(255),
      {$this->arch['search_results']['result']} TEXT
      ";
    });
  }

  protected function dropAllTables()
  {
    if (!$this->db) {
      return;
    }

    if (!$tables = $this->db->getTables()) {
      return;
    }

    foreach ($tables as $table) {
      $this->dropTableIfExists($table);
    }
  }

  protected function tearDown(): void
  {
    \Mockery::close();
//    $this->cleanTestingDir();
//    $this->dropAllTables();
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
          'where' => [['name', 'contains', $search]]
        ],
        'alternates' => [
          [
            'where' => ['name' => $search],
            'score' => 15
          ]
        ],
        'file' => getcwd() . '/' . BBN_DATA_PATH . 'plugins/appui-search/src/mvc/private/profiles_search.php',
        'signature' => '7eef0763b91ab6d06d80a854a4eb3413',
        'num' => 0
      ],
      [
        'score' => 40,
        'type' => 'url',
        'cfg' => [
          'tables' => ['members'],
          'fields' => ['id', 'name'],
          'where' => ['id' => $search]
        ],
        'file' => getcwd() . '/' . BBN_DATA_PATH . 'plugins/appui-plugin-1/src/mvc/private/users_search.php',
        'signature' => '7453ef8d3c131eda97cc8200243391c4',
        'num' => 1
      ],
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
         'file' => getcwd() . '/' . BBN_DATA_PATH . 'plugins/appui-search/src/mvc/private/users_search.php',
         'signature' => 'd608a60ab1788be218cc7424bc6e1128',
         'num' => 2
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

    foreach ($search_cfg as $items) {
      $this->assertIsArray($items);

      foreach ($items as $item) {
        $this->assertNotFalse(@unserialize($item['fn']));
        $this->assertNotEmpty($item['file']);
        $this->assertNotEmpty($item['signature']);
      }
    }
  }

  /** @test */
  public function getSearchCfg_method_returns_search_config_from_cache_when_exists()
  {
    $this->setNonPublicPropertyValue('functions', [], Search::class);

    $this->setNonPublicPropertyValue('search_cfg', []);

    $result = $this->getNonPublicMethod('getSearchCfg')
      ->invoke($this->search);

    $this->assertIsArray($result);
    $this->assertNotEmpty($result);

    foreach ($result as $items) {
      $this->assertIsArray($items);

      foreach ($items as $item) {
        $this->assertNotFalse(@unserialize($item['fn']));
        $this->assertNotEmpty($item['file']);
        $this->assertNotEmpty($item['signature']);
      }
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

    foreach ($result as $items) {
      $this->assertIsArray($items);

      foreach ($items as $item) {
        $this->assertNotFalse(@unserialize($item['fn']));
        $this->assertNotEmpty($item['file']);
        $this->assertNotEmpty($item['signature']);
      }
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

    $this->db_mock->shouldReceive('selectOne')
      ->andReturnNull();

    $this->db_mock->shouldReceive('insert')
      ->andReturn(1);

    $this->db_mock->shouldReceive('lastId')
      ->andReturn('123');

    // Query expectations for the first method call
    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with([
        'tables' => ['members'],
        'fields' => ['id', 'name'],
        'where' => [['name', 'contains', 'foo']]
      ])
      ->andReturn([
        $expected[] = ['id' => 12, 'name' => 'foo']
      ]);

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with([
        'tables' => ['members'],
        'fields' => ['id', 'name'],
        'where' => ['id' => 'foo']
      ])
      ->andReturnNull();

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with([
        'tables' => ['members'],
        'fields' => ['id', 'name'],
        'where' => ['name' => 'foo']
      ])
      ->andReturn([
        $expected[] = ['id' => 33, 'name' => 'foo']
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
        'where' => [['name', 'contains', 'foo']]
      ])
      ->andReturn([
        $expected_2[] = ['id' => 12, 'name' => 'foo']
      ]);

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with([
        'tables' => ['members'],
        'fields' => ['id', 'name'],
        'where' => ['id' => 'foo']
      ])
      ->andReturnNull();

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with([
        'tables' => ['members'],
        'fields' => ['id', 'name'],
        'where' => ['name' => 'foo']
      ])
      ->andReturn([
        $expected_2[] = ['id' => 333, 'name' => 'foo']
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
        'where' => [['name', 'contains', 'bar']]
      ])
      ->andReturn([
        $expected[] = ['id' => 12, 'name' => 'bar']
      ]);

    $this->db_mock->shouldReceive('selectOne')
      ->andReturnNull();

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
  public function get_method_does_not_return_the_next_step_if_time_out_has_passed_but_there_is_no_other_steps_remaining()
  {
    $this->user_mock->shouldReceive('getId')
      ->andReturn('634a2c70bcac11eba47652540000cfaa');

    $this->db_mock->shouldReceive('selectOne')
      ->andReturnNull();

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with([
        'tables' => ['members'],
        'fields' => ['id', 'name'],
        'where' => [['name', 'contains', 'bar']]
      ])
      ->andReturn([
        $expected[] = ['id' => 343, 'name' => 'bar']
      ]);

    $this->db_mock->shouldReceive('rselectAll')
      ->once()
      ->with([
        'tables' => ['members'],
        'fields' => ['id', 'name'],
        'where' => ['id' => 'bar']
      ])
      ->andReturnNull();

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

    // Second loop timer measure expectations to not pass time limit
    $timer_mock->shouldReceive('measure')
      ->with('search')
      ->once()
      ->andReturn(
        $this->getNonPublicProperty('time_limit') - 2
      );

    // Third loop timer measure expectations to pass time limit but no steps remaining
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

  /** @test */
  public function get_method_launches_the_search_with_the_given_search_string_and_there_were_previous_similar_search_found()
  {
    // We will use a real database here for the test
    $this->setUpDb();

    $this->user_mock->shouldReceive('getId')
      ->andReturn($user_id = '634a2c70bcac11eba47652540000cfaa');

    $this->createTable('members', function () {
      return 'id BINARY(16) PRIMARY KEY,
              name VARCHAR(255)';
    });

    $this->db->insert('members', [
      $member4 = ['id' => 'bbbb005e2b4711ecae0499726ed0cb89', 'name' => 'foo3'],
      $member1 = ['id' => 'f94718882b4611ecae0499726ed0cb89', 'name' => 'foo'], // previously saved
      $member2 = ['id' => '5618d0f62b4711ecae0499726ed0cb89', 'name' => 'bar'],
      $member3 = ['id' => '789b005e2b4711ecae0499726ed0cb89', 'name' => 'foo'], // previously saved
    ]);

    $this->db->insert($this->cfg['table'], [
      [
        $this->arch['search']['id_user'] => $user_id,
        $this->arch['search']['value'] => 'fo'
      ]
    ]);

    $id_search = $this->db->lastId();
    $signature = $this->getExpectedSearchCfg()[0]['signature'];

    $this->db->insert($this->cfg['tables']['search_results'], [
      [
        $this->arch['search_results']['id_search'] => $id_search,
        $this->arch['search_results']['signature'] => $signature,
        $this->arch['search_results']['result'] => json_encode($member1)
      ],
      [
        $this->arch['search_results']['id_search'] => $id_search,
        $this->arch['search_results']['signature'] => $signature,
        $this->arch['search_results']['result'] => json_encode($member3)
      ]
    ]);


    $results = $this->search->get('fo');

    $this->assertArrayHasKey('data', $results);
    $this->assertSame([$member1, $member3, $member4], $results['data']);
    dump($results['data']);
  }

  /** @test */
  public function register_method_registers_a_function_in_the_functions_static_property()
  {
    $this->setNonPublicPropertyValue('functions', []);

    Search::register($fn1 = function ($search) {
      return [
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
        ]
      ];
    }, 'main');

    Search::register($fn2 = function ($search) {
      return [
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
        ]
      ];
    }, 'main');

    $functions = $this->getNonPublicProperty('functions', Search::class)['main'];

    $this->assertIsArray($functions);
    $this->assertArrayHasKey(0, $functions);
    $this->assertArrayHasKey(1, $functions);
    $this->assertArrayHasKey('fn', $functions[0]);
    $this->assertArrayHasKey('fn', $functions[1]);

    $this->assertSame($fn1, $functions[0]['fn']);
    $this->assertSame($fn2, $functions[1]['fn']);
  }

  /** @test */
  public function register_method_creates_a_signature_of_the_closure_result_and_it_should_match_with_other_call_to_same_closure()
  {
    $this->setNonPublicPropertyValue('functions', []);

    Search::register(function ($search) {
      return [
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
        ]
      ];
    }, 'main');

    Search::register(function ($search) {
      return [
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
        ]
      ];
    }, 'main');

    $functions = $this->getNonPublicProperty('functions', Search::class)['main'];

    $this->assertIsArray($functions);
    $this->assertArrayHasKey(0, $functions);
    $this->assertArrayHasKey(1, $functions);

    $this->assertSame(
      $functions[0]['signature'], $functions[1]['signature']
    );
  }
}