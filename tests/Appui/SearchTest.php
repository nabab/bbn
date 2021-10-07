<?php

namespace Appui;

use bbn\Appui\Search;
use bbn\Cache;
use bbn\Db;
use bbn\Mvc;
use bbn\User;
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
use Opis\Closure\SerializableClosure;

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

\$wrapper = new SerializableClosure(\$function);

return serialize(\$wrapper);

CONTENT
, $plugin_dir);

    $this->createFile('profiles_search.php', <<<CONTENT
<?php 
use Opis\Closure\SerializableClosure;

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

\$wrapper = new SerializableClosure(\$function);

return serialize(\$wrapper);

CONTENT
      , $plugin_dir);

    $this->createDir($plugin_dir_2 = 'plugins/appui-plugin-1/src/mvc/model');
    $this->createFile('users_search.php', <<<CONTENT
<?php 
 use Opis\Closure\SerializableClosure;
 
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

\$wrapper = new SerializableClosure(\$function);

return serialize(\$wrapper);

CONTENT
      , $plugin_dir_2);

    $this->ctrl   = new Mvc\Controller(self::$mvc, []);
    $this->init();
    $this->arch   = $this->cfg['arch'];
  }

  protected function init(?string $search = null)
  {
    $this->search = new Search(
      $this->ctrl, $search ?? $this->search_string, $this->cfg
    );
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


    $this->assertSame(
      $this->getExpectedSearchCfg(), $this->getNonPublicProperty('search_cfg')
    );
  }

  /** @test */
  public function getSearchCfg_method_returns_search_config_from_cache_when_exists()
  {
    $this->cleanTestingDir(BBN_APP_PATH . BBN_DATA_PATH . 'plugins');
    $method = $this->getNonPublicMethod('getSearchCfg');

    $this->assertSame(
      $this->getExpectedSearchCfg(), $method->invoke($this->search)
    );

    // Another test with different string
    $this->init('bar');

    $this->assertSame(
      $this->getExpectedSearchCfg('bar'), $method->invoke($this->search)
    );
  }
  
  /** @test */
  public function getSearchCfg_method_returns_search_config_and_save_it_in_cache()
  {
    $method = $this->getNonPublicMethod('getSearchCfg');

    $this->assertSame(
      $this->getExpectedSearchCfg(), $method->invoke($this->search)
    );

    // Another test with different search string
    $this->init('bar');

    $this->assertSame(
      $this->getExpectedSearchCfg('bar'),
      $method->invoke($this->search)
    );
  }
}