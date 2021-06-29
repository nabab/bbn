<?php

namespace Mvc;

use bbn\Mvc;
use bbn\Mvc\Router;
use PHPUnit\Framework\TestCase;
use tests\Files;
use tests\Reflectable;

class RouterTest extends TestCase
{
  use Reflectable, Files;

  protected Router $router;

  protected $mvc_mock;

  protected $app_path;

  protected $routes = [
    'plugin' => [
      'root' => 'TEST',
      'name' => 'test_plugin',
      'url'  => 'http://foo.bar',
      'path' => 'foo/bar/'
      ]
    ];


  public function getInstance()
  {
    return $this->router;
  }

  protected function setUp(): void
  {
    $this->cleanTestingDir();
    $this->init();
  }

  public function init(array $routes = null)
  {
    $this->resetRetriever();
    $this->resetKnownProperty();
    $this->setAppPath();
    $this->routerInit($routes);
  }

  public function routerInit(array $routes = null)
  {
    $this->mvc_mock = \Mockery::mock(Mvc::class);

    $this->mvc_mock->shouldReceive('appPath')
      ->once()
      ->withNoArgs()
      ->andReturn($this->app_path = $this->getTestingDirName());

    $this->router = new Router($this->mvc_mock, $routes ?? $this->routes);
  }

  protected function resetKnownProperty()
  {
    $this->setNonPublicPropertyValue(
      '_known',
      [
        'cli' => [],
        'dom' => [],
        'public' => [],
        'private' => [],
        'model' => [],
        'html' => [],
        'js' => [],
        'css' => [],
        'component' => [],
      ],
      Router::class
    );
  }

  protected function setAppPath(?string $app_path = null)
  {
    $this->setNonPublicPropertyValue('_app_path', $app_path ?? BBN_APP_PATH, Mvc::class);
  }

  protected function resetRetriever()
  {
    $this->setNonPublicPropertyValue('retriever_exists', false, Router::class);
    $this->setNonPublicPropertyValue('retriever_instance', null, Router::class);
  }

  protected function tearDown(): void
  {
    \Mockery::close();
    $this->cleanTestingDir();
  }

  /** @test */
  public function constructor_test()
  {
    $this->assertInstanceOf(Mvc::class, $this->getNonPublicProperty('_mvc'));
    $this->assertSame($this->routes, $this->getNonPublicProperty('_routes'));
    $this->assertSame($this->app_path, $this->getNonPublicProperty('_root'));
    $this->assertSame(['main' => 'main9'], $this->getNonPublicProperty('_textdomains'));
  }

  /** @test */
  public function constructor_test_when_app_path_does_not_exist()
  {
    $this->resetRetriever();
    $this->resetKnownProperty();
    $this->setAppPath('./app/');
    $this->routerInit();

    $this->assertInstanceOf(Mvc::class, $this->getNonPublicProperty('_mvc'));
    $this->assertSame($this->routes, $this->getNonPublicProperty('_routes'));
    $this->assertSame($this->app_path, $this->getNonPublicProperty('_root'));
    $this->assertSame([], $this->getNonPublicProperty('_textdomains'));
  }

  /** @test */
  public function isMode_method_checks_if_the_given_string_is_a_valid_mode()
  {
    $this->assertTrue(Router::isMode('image'));
    $this->assertTrue(Router::isMode('file'));
    $this->assertTrue(Router::isMode('cli'));
    $this->assertTrue(Router::isMode('private'));
    $this->assertTrue(Router::isMode('dom'));
    $this->assertTrue(Router::isMode('public'));
    $this->assertTrue(Router::isMode('model'));
    $this->assertTrue(Router::isMode('html'));
    $this->assertTrue(Router::isMode('js'));
    $this->assertTrue(Router::isMode('css'));

    $this->assertFalse(Router::isMode('foo'));
  }

  /** @test */
  public function parse_method_removes_trailing_slashes()
  {
    $this->assertSame('./foo/bar/baz/', Router::parse('.//foo//bar//baz//'));
  }

  /** @test */
  public function reset_method_resets_the_full_path_of_a_plugin()
  {
   $result = $this->router->reset();

    $this->assertFalse($this->getNonPublicProperty('alt_root'));
    $this->assertInstanceOf(Router::class, $result);
  }

  /** @test */
  public function setPrepath_method_sets_pre_path_when_mode_is_not_defined()
  {
    $result = $this->router->setPrepath('/prepath/');
    $this->assertTrue($result);
    $this->assertSame('/prepath/', $this->getNonPublicProperty('_prepath'));

    $result = $this->router->setPrepath('/prepath');
    $this->assertTrue($result);
    $this->assertSame('/prepath/', $this->getNonPublicProperty('_prepath'));

    $result = $this->router->setPrepath('prepath');
    $this->assertTrue($result);
    $this->assertSame('prepath/', $this->getNonPublicProperty('_prepath'));
  }

  /** @test */
  public function setPrepath_method_sets_pre_path_when_mode_defined()
  {
    $this->setNonPublicPropertyValue('_mode', 'html');

    $this->mvc_mock->shouldReceive('getUrl')
      ->once()
      ->withNoArgs()
      ->andREturn('localhost/foo');

    $this->mvc_mock->shouldReceive('getPlugins')
      ->once()
      ->withNoArgs()
      ->andReturn([
        'test_plugin' => [
          'name' => 'test_plugin',
          'url'  => 'http://foo.bar',
          'path' => 'test_path/foo/bar'
        ]
      ]);

    $result = $this->router->setPrepath('/prepath');
    $this->assertTrue($result);
    $this->assertSame('/prepath/', $this->getNonPublicProperty('_prepath'));
  }

  /** @test */
  public function setPrepath_method_throws_an_exception_when_path_is_not_valid()
  {
    $this->expectException(\Exception::class);

    $this->router = \Mockery::mock(Router::class)->makePartial();

    $this->router->shouldReceive('checkPath')
      ->with('/prepath/')
      ->andReturnFalse();

    $this->router->setPrepath('/prepath/');
  }

  /** @test */
  public function getPrepath_method_returns_the_pre_path_when_exists()
  {
    $this->setNonPublicPropertyValue('_prepath' ,'/prepath/');

    $this->assertSame('/prepath/', $this->router->getPrepath());
    $this->assertSame('/prepath', $this->router->getPrepath(0));
  }

  /** @test */
  public function getPrepath_method_returns_empty_string_if_not_exists()
  {
    $this->setNonPublicPropertyValue('_prepath' ,'');

    $this->assertSame('', $this->router->getPrepath());
  }

  /** @test */
  public function getLocaleDomain_method_returns_text_domains_for_the_given_name_or_for_the_main_if_not_given()
  {
    $this->setNonPublicPropertyValue('_textdomains', ['main' => 'main_result', 'plugin' => 'plugin_result']);

    $this->assertSame('main_result', $this->router->getLocaleDomain());
    $this->assertSame('plugin_result', $this->router->getLocaleDomain('plugin'));
  }

  /** @test */
  public function getPluginFromComponent_method_retrieves_plugin_name_from_component_name_if_any()
  {
    $this->mvc_mock->shouldReceive('getPlugins')
      ->twice()
      ->withNoArgs()
      ->andReturn([
        'test_plugin' => $plugin_1 = [
          'name' => 'test_plugin',
          'url'  => 'http://foo.bar',
          'path' => 'test_path/foo/bar/'
        ],
        'test_plugin_2' => [
          'name' => 'test_plugin_2',
          'url'  => 'http://foo.bar',
          'path' => 'test_path_2/foo/bar/'
        ]
      ]);

    $this->assertSame($plugin_1, $this->router->getPluginFromComponent('test_plugin-'));
    $this->assertNull($this->router->getPluginFromComponent('test_plugin'));

    $this->mvc_mock->shouldReceive('getPlugins')
      ->once()
      ->withNoArgs()
      ->andReturn([]);

    $this->assertNull($this->router->getPluginFromComponent('test_plugin-'));
  }

  /** @test */
  public function routeComponent_method_returns_route_component_when_the_given_plugin_exists_and_dir_exists()
  {
    $testing_dir_path = $this->getTestingDirName();

    $this->mvc_mock->shouldReceive('getPlugins')
      ->once()
      ->withNoArgs()
      ->andReturn([
        'test_plugin' => $plugin = [
          'name' => 'test_plugin',
          'url'  => 'http://foo.bar',
          'path' =>  "{$testing_dir_path}plugin_path/"
        ]
      ]);

    $this->mvc_mock->shouldReceive('getLocale')
      ->once()
      ->withNoArgs()
      ->andReturn('en');

    $this->mvc_mock->shouldReceive('pluginPath')
      ->once()
      ->with($plugin['name'], false)
      ->andReturn($plugin['path'] . 'src/');


    // Create the dir so that is_dir() returns true
    $this->createDir("plugin_path/src/components/dashboard");
    $this->createDir("plugin_path/src/locale/en");

    // Create the files so that is_file() returns true
    $this->createFile('dashboard.js', '', 'plugin_path/src/components/dashboard');
    $this->createFile('dashboard.html', '', 'plugin_path/src/components/dashboard');
    $this->createFile('dashboard.css', '', 'plugin_path/src/components/dashboard');
    $this->createFile('en.json', '', 'plugin_path/src/locale/en');

    $result   = $this->router->routeComponent('test_plugin-dashboard');
    $expected = [
      'js' => [
        'file' => "{$testing_dir_path}plugin_path/src/components/dashboard/dashboard.js",
        'path' => 'dashboard',
        'plugin' => $plugin['url'],
        'component' => true,
        'ext' => 'js',
        'mode' => 'js',
        'i18n' => "{$testing_dir_path}plugin_path/src/locale/en/en.json"
      ],
      'html'   => [
        'file' => "{$testing_dir_path}plugin_path/src/components/dashboard/dashboard.html",
        'path' => 'dashboard',
        'plugin' => $plugin['url'],
        'component' => true,
        'ext' => 'html',
        'mode' => 'html',
        'i18n' => null
      ],
      'css' => [
        'file' => "{$testing_dir_path}plugin_path/src/components/dashboard/dashboard.css",
        'path' => 'dashboard',
        'plugin' => $plugin['url'],
        'component' => true,
        'ext' => 'css',
        'mode' => 'css',
        'i18n' => null
      ]
    ];

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function routeComponent_method_returns_null_when_plugin_exists_and_dir_does_not_exist()
  {
    $testing_dir_path = $this->getTestingDirName();

    $this->mvc_mock->shouldReceive('getPlugins')
      ->once()
      ->withNoArgs()
      ->andReturn([
        'test_plugin' => [
          'name' => 'test_plugin',
          'url' => 'http://foo.bar',
          'path' => "{$testing_dir_path}plugin_path/"
        ]
      ]);

    $result = $this->router->routeComponent('test_plugin-dashboard');

    $this->assertNull($result);
  }

  /** @test */
  public function routeComponent_method_returns_route_component_when_the_given_plugin_does_not_exist_and_plugin_dir_exists()
  {
    $testing_dir_path = $this->getTestingDirName();

    // Set app path to the testing dir which is returned from appPath() method
    $this->setAppPath($testing_dir_path . 'plugin_path/');

    // Set expectation that getPlugins() method return empty array
    $this->mvc_mock->shouldReceive('getPlugins')
      ->once()
      ->withNoArgs()
      ->andReturn([]);

    $this->mvc_mock->shouldReceive('getLocale')
      ->once()
      ->withNoArgs()
      ->andReturn('en');

    $this->mvc_mock->shouldReceive('appPath')
      ->once()
      ->withNoArgs()
      ->andReturn("{$testing_dir_path}plugin_path/src/");

    // Create the dir so that is_dir() returns true
    $this->createDir("plugin_path/src/components/dashboard");
    $this->createDir("plugin_path/src/locale/en");

    // Create the files so that is_file() returns true
    $this->createFile('dashboard.js', '', 'plugin_path/src/components/dashboard');
    $this->createFile('dashboard.html', '', 'plugin_path/src/components/dashboard');
    $this->createFile('dashboard.css', '', 'plugin_path/src/components/dashboard');
    $this->createFile('en.json', '', 'plugin_path/src/locale/en');

    $result = $this->router->routeComponent('plugin-dashboard');

    $expected = [
      'js' => [
        'file' => "{$testing_dir_path}plugin_path/src/components/dashboard/dashboard.js",
        'path' => 'dashboard',
        'plugin' => null,
        'component' => true,
        'ext' => 'js',
        'mode' => 'js',
        'i18n' => "{$testing_dir_path}plugin_path/src/locale/en/en.json"
      ],
      'html'   => [
        'file' => "{$testing_dir_path}plugin_path/src/components/dashboard/dashboard.html",
        'path' => 'dashboard',
        'plugin' => null,
        'component' => true,
        'ext' => 'html',
        'mode' => 'html',
        'i18n' => null
      ],
      'css' => [
        'file' => "{$testing_dir_path}plugin_path/src/components/dashboard/dashboard.css",
        'path' => 'dashboard',
        'plugin' => null,
        'component' => true,
        'ext' => 'css',
        'mode' => 'css',
        'i18n' => null
      ]
    ];

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function routeComponent_method_returns_null_when_the_given_plugin_does_not_exist_and_plugin_dir_does_not_exist()
  {
    // Set app path to the testing dir which is returned from appPath() method
    $this->setAppPath($this->getTestingDirName() . 'plugin_path/');

    // Set expectation that getPlugins() method return empty array
    $this->mvc_mock->shouldReceive('getPlugins')
      ->once()
      ->withNoArgs()
      ->andReturn([]);

    $result = $this->router->routeComponent('plugin-dashboard');


    $this->assertNull($result);
  }

  /** @test */
  public function routeCustomPlugin_method_returns_custom_plugins_data_from_the_given_arguments()
  {
    $testing_dir_path = $this->getTestingDirName();

    $this->createDir('plugins/plugin_name/html');

    $this->createFile('app.html', '', 'plugins/plugin_name/html');
    $this->createFile('app.js', '', 'plugins/plugin_name/js');

    $result   = $this->router->routeCustomPlugin('app', 'html', 'plugin_name');
    $expected =  [
      'file' => "{$testing_dir_path}plugins/plugin_name/html/app.html",
      'path' => 'app',
      'ext' => 'html',
      'plugin' => 'plugin_name',
      'mode' => 'html',
      'i18n' => null,
    ];

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function routeCustomPlugin_method_returns_null_when_mode_does_not_exist_in_filetypes()
  {
    $this->assertNull(
      $this->router->routeCustomPlugin('app', 'foo', 'plugin_name')
    );
  }

  /** @test */
  public function routeSubplugin_method_returns_sub_plugin_data_from_given_arguments()
  {
    $testing_dir_path = $this->getTestingDirName();

    $this->mvc_mock->shouldReceive('pluginPath')
      ->twice()
      ->with('plugin_name', false)
      ->andReturn($testing_dir_path . 'plugins/plugin_name/');

    $this->mvc_mock->shouldReceive('getLocale')
      ->once()
      ->withNoArgs()
      ->andReturn('en');

    $this->createDir('plugins/plugin_name/plugins/sub_plugin_name/js');
    $this->createDir('plugins/plugin_name/locale/en');
    $this->createFile('app.js', '', 'plugins/plugin_name/plugins/sub_plugin_name/js');
    $this->createFile('en.json', '', 'plugins/plugin_name/locale/en');

    $result   = $this->router->routeSubplugin('app', 'js', 'plugin_name', 'sub_plugin_name');
    $expected = [
      'file' => "{$testing_dir_path}plugins/plugin_name/plugins/sub_plugin_name/js/app.js",
      'path' => 'app',
      'ext' => 'js',
      'plugin' => 'plugin_name',
      'mode' => 'js',
      'i18n' => "{$testing_dir_path}plugins/plugin_name/locale/en/en.json",
    ];

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function routeSubplugin_method_returns_null_when_mode_does_not_exists_in_filetypes()
  {
    $this->assertNull(
      $this->router->routeSubplugin('app', 'foo', 'plugin_name', 'sub_plugin_name')
    );
  }

  /** @test */
  public function route_method_returns_controller_file_info_from_the_given_path()
  {
    // Alter the known property so that the _find_controller returns it.
    $known = $this->getNonPublicProperty('_known');

    $known['public']['app'] = $expected = [
      "file" => "./tests/storage/plugins/plugin_name/html/app.php",
      "path" => "app",
      "root" => "root",
      "request" => "get",
      "plugin" => "plugin_name",
      "mode" => "public",
      "args" => ['foo' => 'bar']
    ];

    $this->setNonPublicPropertyValue('_known', $known);

    $result = $this->router->route('app', 'public');

    $this->assertSame('public', $this->getNonPublicProperty('_mode'));
    $this->assertSame($expected, $result);
  }

  /** @test */
  public function route_method_returns_controller_file_info_from_the_given_path_with_a_pre_path()
  {
    // Alter the known property so that the _find_controller returns it.
    $known = $this->getNonPublicProperty('_known');

    $known['public']['prepath/app'] = $expected = [
      "file" => "./tests/storage/plugins/plugin_name/html/app.php",
      "path" => "prepath/app",
      "root" => "root",
      "request" => "get",
      "plugin" => "plugin_name",
      "mode" => "public",
      "args" => ['foo' => 'bar']
    ];

    $this->setNonPublicPropertyValue('_known', $known);

    $this->setNonPublicPropertyValue('_prepath', 'prepath/');

    $result = $this->router->route('app', 'public');

    $this->assertSame('public', $this->getNonPublicProperty('_mode'));
    $this->assertSame($expected, $result);
  }

  /** @test */
  public function route_method_returns_model_view_info_from_the_given_path()
  {
    // Alter the known property so that the _find_controller returns it.
    $known = $this->getNonPublicProperty('_known');

    $known['js']['app'] = $expected = [
      'file' => "./tests/storage/plugins/plugin_name/html/app.js",
      'path' => 'app',
      'ext' => 'js',
      'plugin' => 'plugin_name',
      'mode' => 'js',
      'i18n' => "./tests/storage/plugins/plugin_name/locale/en/en.json",
    ];

    $this->setNonPublicPropertyValue('_known', $known);

    $result = $this->router->route('app', 'js');

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function route_method_returns_model_view_info_from_the_given_path_with_a_prepath()
  {
    // Alter the known property so that the _find_controller returns it.
    $known = $this->getNonPublicProperty('_known');

    $known['js']['prepath/app'] = $expected = [
      'file' => "./tests/storage/plugins/plugin_name/html/app.js",
      'path' => 'prepath/app',
      'ext' => 'js',
      'plugin' => 'plugin_name',
      'mode' => 'js',
      'i18n' => "./tests/storage/plugins/plugin_name/locale/en/en.json",
    ];

    $this->setNonPublicPropertyValue('_known', $known);

    $this->setNonPublicPropertyValue('_prepath', 'prepath/');

    $result = $this->router->route('app', 'js');

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function route_method_returns_null_when_mode_does_not_exists()
  {
    $this->assertNull(
      $this->router->route('app', 'foo')
    );
  }

  /** @test */
  public function fetchDir_method_fetches_dir_content_for_model_and_views_when_dir_exists()
  {
    $this->mvc_mock->shouldReceive('getPlugins')
      ->once()
      ->withNoArgs()
      ->andReturn([]);

    $this->createDir('mvc/html/app');
    $this->createFile('index.html', '', 'mvc/html/app');

    $result = $this->router->fetchDir('app', 'html');

    $this->assertSame(['app/index'], $result);
  }

  /** @test */
  public function fetchDir_method_fetches_dir_content_for_model_and_views_when_dir_does_not_exist_and_plugin_exists_and_has_an_alt_root()
  {
    $testing_dir_path = $this->getTestingDirName();

    $this->setNonPublicPropertyValue('_routes', [
      'root' => [
        'app' => [
          'root' => 'app',
          'name' => 'app',
          'url'  => 'app',
          'path' => "{$testing_dir_path}/app"
        ]
      ]
    ]);

    $this->mvc_mock->shouldReceive('getPlugins')
      ->once()
      ->withNoArgs()
      ->andReturn([
        'app' => [
          'name' => 'app',
          'url'  => 'app',
          'path' => "{$testing_dir_path}/app"
        ]]);

    $this->createDir('app/src/mvc/html');
    $this->createFile('index.html', '', 'app/src/mvc/html');

    $result = $this->router->fetchDir('app', 'html');

    $this->assertSame(['app/index'], $result);
  }

  /** @test */
  public function fetchDir_method_fetches_dir_content_for_model_and_views_when_dir_does_not_exist_and_plugin_does_not_exist_and_alt_root_is_defined()
  {
    $testing_dir_path = $this->getTestingDirName();

    $this->setNonPublicPropertyValue('_routes', [
      'root' => [
        'app_alt_root' => [
          'root' => 'app',
          'name' => 'app',
          'url'  => 'app',
          'path' => "{$testing_dir_path}/app"
        ]
      ]
    ]);

    $this->setNonPublicPropertyValue('alt_root', 'app_alt_root');

    $this->mvc_mock->shouldReceive('getPlugins')
      ->once()
      ->withNoArgs()
      ->andReturn([]);

    $this->createDir('app/src/mvc/html');
    $this->createFile('index.html', '', 'app/src/mvc/html');

    $result = $this->router->fetchDir('app', 'html');

    $this->assertSame(['app/index'], $result);
  }

  /** @test */
  public function fetchDir_method_returns_null_when_dir_does_not_exist_and_alt_root_is_not_defined()
  {
    $this->mvc_mock->shouldReceive('getPlugins')
      ->once()
      ->withNoArgs()
      ->andReturn([]);

    $result = $this->router->fetchDir('app', 'html');

    $this->assertNull($result);
  }

  /** @test */
  public function getRoutes_method_returns_the_registered_routes()
  {
    $this->assertSame($this->routes, $this->router->getRoutes());
  }

  /** @test */
  public function get_root_method_returns_the_full_path_in_the_mvc_of_the_main_app()
  {
    $get_root_method = $this->getNonPublicMethod('_get_root');
    $root            = $this->getNonPublicProperty('_root');

    $result = $get_root_method->invoke($this->router, 'html');
    $this->assertSame($root . 'mvc/html/', $result);

    $result = $get_root_method->invoke($this->router, 'dom');
    $this->assertSame($root . 'mvc/public/', $result);

    $result = $get_root_method->invoke($this->router, 'cli');
    $this->assertSame($root . 'cli/', $result);

    $result = $get_root_method->invoke($this->router, 'js');
    $this->assertSame($root . 'mvc/js/', $result);
  }

  /** @test */
  public function get_root_method_returns_null_when_mode_does_not_exist()
  {
    $get_root_method = $this->getNonPublicMethod('_get_root');

    $this->assertNull(
      $get_root_method->invoke($this->router, 'foo')
    );
  }

  /** @test */
  public function get_mode_path_method_returns_the_mode_path()
  {
    $method = $this->getNonPublicMethod('_get_mode_path');

    $this->assertSame('mvc/public/', $method->invoke($this->router, 'dom'));
    $this->assertSame('cli/', $method->invoke($this->router, 'cli'));
    $this->assertSame('mvc/html/', $method->invoke($this->router, 'html'));
    $this->assertSame('mvc/css/', $method->invoke($this->router, 'css'));
    $this->assertSame('mvc/model/', $method->invoke($this->router, 'model'));
  }

  /** @test */
  public function get_mode_path_method_throws_an_exception_when_mode_does_not_exist()
  {
    $this->expectException(\Exception::class);

    $this->getNonPublicMethod('_get_mode_path')
      ->invoke($this->router, 'foo');
  }

  /** @test */
  public function get_alt_root_method_returns_full_path_in_the_mvc_of_an_external_app_from_the_provided_mode_and_path()
  {
    $method = $this->getNonPublicMethod('_get_alt_root');

    $testing_dir_path = $this->getTestingDirName();

    $this->setNonPublicPropertyValue('_routes', [
      'root' => [
        'app_alt_root' => [
          'root' => 'app',
          'name' => 'app',
          'url'  => 'app',
          'path' => "$testing_dir_path/app_path"
        ]
      ]
    ]);

    $result = $method->invoke($this->router, 'html', 'app_alt_root');
    $this->assertSame($testing_dir_path . "app_path/src/mvc/html/", $result);

    $result = $method->invoke($this->router, 'js', 'app_alt_root');
    $this->assertSame($testing_dir_path . "app_path/src/mvc/js/", $result);

    $result = $method->invoke($this->router, 'model', 'app_alt_root');
    $this->assertSame($testing_dir_path . "app_path/src/mvc/model/", $result);
  }

  /** @test */
  public function get_alt_root_method_returns_null_when_mode_does_not_exists()
  {
    $this->assertNull(
      $this->getNonPublicMethod('_get_alt_root')
      ->invoke($this->router, 'foo', 'app_alt_root')
    );
  }

  /** @test */
  public function get_alt_root_method_returns_null_when_its_not_registered_in_routes()
  {
    $this->setNonPublicPropertyValue('_routes', []);

    $this->assertNull(
      $this->getNonPublicMethod('_get_alt_root')
        ->invoke($this->router, 'html', 'app_alt_root')
    );
  }

  /** @test */
  public function get_alt_root_method_returns_null_when_path_is_not_provided_and_alt_root_property_is_not_defined()
  {
    $this->setNonPublicPropertyValue('alt_root', null);

    $this->assertNull(
      $this->getNonPublicMethod('_get_alt_root')
        ->invoke($this->router, 'html')
    );
  }

  /** @test */
  public function is_alias_method_checks_if_a_path_is_part_of_alias_in_the_routes_array()
  {
    $method = $this->getNonPublicMethod('_is_alias');

    $this->setNonPublicPropertyValue('_routes', ['alias' => ['path/to' => 'foo']]);

    $this->assertSame('path/to', $method->invoke($this->router, 'path/to'));
    $this->assertSame('path/to', $method->invoke($this->router, 'path//to'));
    $this->assertSame('path/to', $method->invoke($this->router, 'path/to/app'));
    $this->assertNull($method->invoke($this->router, 'another/path/to'));
    $this->assertNull($method->invoke($this->router, '//path//to'));

    $this->setNonPublicPropertyValue('_routes', ['alias' => []]);

    $this->assertNull($method->invoke($this->router, 'path/to'));

    $this->setNonPublicPropertyValue('_routes', []);

    $this->assertNull($method->invoke($this->router, 'path/to'));
  }

  /** @test */
  public function get_alias_method_returns_the_alias_of_the_given_path_if_it_is_part_of_the_alias_in_the_routes_array()
  {
    $method = $this->getNonPublicMethod('_get_alias');

    $this->setNonPublicPropertyValue('_routes', ['alias' => ['path/to' => 'foo']]);

    $this->assertSame('foo', $method->invoke($this->router, 'path/to'));
    $this->assertSame('foo', $method->invoke($this->router, 'path//to'));
    $this->assertNull($method->invoke($this->router, 'another/path/to'));
    $this->assertNull($method->invoke($this->router, '/path/to'));

    $this->setNonPublicPropertyValue('_routes', ['alias' => ['path/to' => ['foo', 'bar']]]);

    $this->assertSame('foo', $method->invoke($this->router, 'path/to'));
    $this->assertSame('foo', $method->invoke($this->router, 'path//to'));
    $this->assertNull($method->invoke($this->router, 'another/path/to'));
    $this->assertNull($method->invoke($this->router, '//path/to'));

    $this->setNonPublicPropertyValue('_routes', []);
    $this->assertNull($method->invoke($this->router, 'another/path/to'));
    $this->assertNull($method->invoke($this->router, '//path/to'));
  }

  /** @test */
  public function is_known_method_checks_if_the_given_path_is_known_for_its_corresponding_mode()
  {
    $method = $this->getNonPublicMethod('_is_known');
    $known  = $this->getNonPublicProperty('_known');

    $known['js']['path/to/app'] = [
      'file' => "./tests/storage/plugins/plugin_name/html/app.js",
      'path' => 'path/to/app',
      'ext' => 'js',
      'plugin' => 'plugin_name',
      'mode' => 'js',
      'i18n' => "./tests/storage/plugins/plugin_name/locale/en/en.json",
    ];

    $this->setNonPublicPropertyValue('_known', $known);

    $this->assertTrue($method->invoke($this->router, 'path/to/app', 'js'));
    $this->assertFalse($method->invoke($this->router, 'path/to/another/app', 'js'));
    $this->assertFalse($method->invoke($this->router, 'path/to/app', 'html'));
    $this->assertFalse($method->invoke($this->router, 'path/to/app', 'foo'));
  }

  /** @test */
  public function get_known_method_retrieves_a_route_from_a_given_path_in_a_given_mode()
  {
    $method = $this->getNonPublicMethod('_get_known');

    $known  = $this->getNonPublicProperty('_known');

    $known['js']['path/to/app'] = [
      'file' => "./tests/storage/plugins/plugin_name/html/app.js",
      'path' => 'path/to/app',
      'ext' => 'js',
      'plugin' => 'plugin_name',
      'mode' => 'js',
      'i18n' => "./tests/storage/plugins/plugin_name/locale/en/en.json",
    ];

    $known['public'] = [
      'alias/path'     => 'path/to/public',
      'path/to/public' => [
        'file' => "./tests/storage/plugins/plugin_name/html/app.php",
        'path' => 'path/to/public',
        'ext' => 'php',
        'mode' => 'public',
      ]
    ];

    $this->setNonPublicPropertyValue('_known', $known);

    $this->assertSame(
      $known['js']['path/to/app'],
      $method->invoke($this->router, 'path/to/app', 'js')
    );

    $this->assertSame(
      $known['public']['path/to/public'],
      $method->invoke($this->router, 'alias/path', 'public')
    );

    $this->assertSame(
      $known['public']['path/to/public'],
      $method->invoke($this->router, 'path/to/public', 'public')
    );

    $this->assertNull($method->invoke($this->router, 'path/to/another/app', 'js'));
    $this->assertNull($method->invoke($this->router, 'path/to/app', 'html'));
    $this->assertNull($method->invoke($this->router, 'path/to/app', 'foo'));
  }

  /** @test */
  public function set_known_method_sets_and_stores_a_given_route()
  {
    $method = $this->getNonPublicMethod('_set_known');

    $result = $method->invoke($this->router, $data = [
      'mode' => 'html',
      'path' => 'path/to',
      'file' => 'foo.html'
    ]);

    $known = $this->getNonPublicProperty('_known');

    $this->assertSame($data, $result);
    $this->assertTrue(isset($known['html']['path/to']));
    $this->assertSame($data, $known['html']['path/to']);
  }

  /** @test */
  public function set_known_method_sets_and_stores_a_given_route_and_adds_the_corresponding_controller_checker()
  {
    $testing_dir_path = $this->getTestingDirName();
    $method           = $this->getNonPublicMethod('_set_known');

    $this->createDir('mvc/public/path');
    $this->createFile('_ctrl.php', '', 'mvc/public/path');

    $result = $method->invoke($this->router, $data = [
      'mode' => 'public',
      'path' => 'path',
      'file' => 'index.php'
    ]);

    $known = $this->getNonPublicProperty('_known');

    $data = array_merge($data, ['checkers' => ["{$testing_dir_path}mvc/public/path/_ctrl.php"]]);

    $this->assertSame($data, $result);
    $this->assertTrue(isset($known['public']['path']));
    $this->assertSame($data, $known['public']['path']);

    // With plugin provided:

    $this->setNonPublicPropertyValue('_routes', [
      'root' => [
        'plugin' => [
          'root' => 'plugin',
          'name' => 'app',
          'url'  => 'app',
          'path' => $this->getTestingDirName() . "plugin/path/to"
        ]
      ]
    ]);

    $this->createDir('mvc/public/plugin/path/to');
    $this->createDir('plugin/path/to/src/mvc/public');
    $this->createFile('_ctrl.php', '', 'mvc/public/plugin/path/to');
    $this->createFile('_ctrl.php', '', 'plugin/path/to/src/mvc/public');

    $result = $method->invoke($this->router, $data = [
      'mode' => 'public',
      'path' => 'plugin/path/to',
      'file' => 'index.php',
      'plugin' => 'plugin'
    ]);

    $known = $this->getNonPublicProperty('_known');

    $expected = array_merge($data, [
      'checkers' => [
        "{$testing_dir_path}plugin/path/to/src/mvc/public/_ctrl.php",
        "{$testing_dir_path}mvc/public/plugin/path/to/_ctrl.php",
      ]
    ]);

    $this->assertSame($expected, $result);
    $this->assertTrue(isset($known['public']['plugin/path/to']));
    $this->assertSame($expected, $known['public']['plugin/path/to']);

    // With save set to false
    $result = $method->invoke($this->router, $data, false);

    $known = $this->getNonPublicProperty('_known');

    $this->assertSame($expected, $result);
    $this->assertFalse(isset($known['public']['plugin/path/to']));
  }

  /** @test */
  public function set_known_method_sets_and_stores_a_given_route_and_adds_the_corresponding_css_checker()
  {
    $testing_dir_path = $this->getTestingDirName();
    $method           = $this->getNonPublicMethod('_set_known');

    $this->createDir('mvc/css/dir');
    $this->createFile('_mixins.less', '', 'mvc/css/dir');

    $result = $method->invoke($this->router, $data = [
      'mode' => 'css',
      'path' => 'dir/sub_dir',
      'file' => 'style.css',
      'ext'  => 'less'
    ]);

    $known = $this->getNonPublicProperty('_known');

    $data = array_merge($data, ['checkers' => ["{$testing_dir_path}mvc/css/dir/_mixins.less"]]);

    $this->assertSame($data, $result);
    $this->assertTrue(isset($known['css']['dir/sub_dir']));
    $this->assertSame($data, $known['css']['dir/sub_dir']);

    // With plugin provided:

    $this->setNonPublicPropertyValue('_routes', [
      'root' => [
        'plugin' => [
          'root' => 'plugin',
          'name' => 'app',
          'url'  => 'app',
          'path' => $this->getTestingDirName() . "plugin/path/to"
        ]
      ]
    ]);

    $this->createDir('mvc/css/plugin/path');
    $this->createDir('plugin/path/to/src/mvc/css');
    $this->createFile('_mixins.less', '', 'mvc/css/plugin/path');
    $this->createFile('_mixins.less', '', 'plugin/path/to/src/mvc/css');

    $result = $method->invoke($this->router, $data = [
      'mode' => 'css',
      'path' => 'plugin/path/to',
      'file' => 'style.css',
      'ext'  => 'less',
      'plugin' => 'plugin'
    ]);

    $known = $this->getNonPublicProperty('_known');

    $expected = array_merge($data, [
      'checkers' => [
        "{$testing_dir_path}plugin/path/to/src/mvc/css/_mixins.less",
        "{$testing_dir_path}mvc/css/plugin/path/_mixins.less",
      ]
    ]);

    $this->assertSame($expected, $result);
    $this->assertTrue(isset($known['css']['plugin/path/to']));
    $this->assertSame($expected, $known['css']['plugin/path/to']);

    // With save set to false
    $result = $method->invoke($this->router, $data, false);

    $known = $this->getNonPublicProperty('_known');

    $this->assertSame($expected, $result);
    $this->assertFalse(isset($known['css']['plugin/path/to']));
  }

  /** @test */
  public function set_known_method_sets_and_stores_a_given_route_and_adds_the_corresponding_model_checker()
  {
    $testing_dir_path = $this->getTestingDirName();
    $method           = $this->getNonPublicMethod('_set_known');

    $this->createDir('mvc/model/dir');
    $this->createFile('_model.php', '', 'mvc/model/dir');

    $result = $method->invoke($this->router, $data = [
      'mode' => 'model',
      'path' => 'dir/sub_dir',
      'file' => 'foo.php',
      'ext'  => 'php'
    ]);

    $known = $this->getNonPublicProperty('_known');

    $data = array_merge($data, ['checkers' => ["{$testing_dir_path}mvc/model/dir/_model.php"]]);

    $this->assertSame($data, $result);
    $this->assertTrue(isset($known['model']['dir/sub_dir']));
    $this->assertSame($data, $known['model']['dir/sub_dir']);

    // With plugin provided:

    $this->setNonPublicPropertyValue('_routes', [
      'root' => [
        'plugin' => [
          'root' => 'plugin',
          'name' => 'app',
          'url'  => 'app',
          'path' => $this->getTestingDirName() . "plugin/path/to"
        ]
      ]
    ]);

    $this->createDir('mvc/model/plugin/path');
    $this->createDir('plugin/path/to/src/mvc/model');
    $this->createFile('_model.php', '', 'mvc/model/plugin/path');
    $this->createFile('_model.php', '', 'plugin/path/to/src/mvc/model');

    $result = $method->invoke($this->router, $data = [
      'mode' => 'model',
      'file' => 'foo.php',
      'ext'  => 'php',
      'path' => 'plugin/path/to',
      'plugin' => 'plugin'
    ]);

    $known = $this->getNonPublicProperty('_known');

    $expected = array_merge($data, [
      'checkers' => [
        "{$testing_dir_path}plugin/path/to/src/mvc/model/_model.php",
        "{$testing_dir_path}mvc/model/plugin/path/_model.php",
      ]
    ]);

    $this->assertSame($expected, $result);
    $this->assertTrue(isset($known['model']['plugin/path/to']));
    $this->assertSame($expected, $known['model']['plugin/path/to']);

    // With save set to false
    $result = $method->invoke($this->router, $data, false);

    $known = $this->getNonPublicProperty('_known');

    $this->assertSame($expected, $result);
    $this->assertFalse(isset($known['model']['plugin/path/to']));
  }

  /** @test */
  public function set_known_method_returns_null_when_required_data_are_missing()
  {
    $method = $this->getNonPublicMethod('_set_known');

    $this->assertNull($method->invoke($this->router, ['mode' => 'html']));
    $this->assertNull($method->invoke($this->router, ['path' => 'path/to']));
    $this->assertNull($method->invoke($this->router, ['file' => 'foo.php']));
    $this->assertNull($method->invoke($this->router, ['mode' => 'html', 'path' => 'path/to']));
    $this->assertNull($method->invoke($this->router, ['mode' => 'html', 'file' => 'foo.php']));

    $this->assertNull($method->invoke($this->router, [
      'mode' => 'foo',
      'file' => 'foo.html',
      'path' => 'path/to/file'
    ]));

    $this->assertNull($method->invoke($this->router, [
      'mode' => 'html',
      'file' => 'foo.html',
      'path' => ['path/to/file']
    ]));

    $this->assertNull($method->invoke($this->router, [
      'mode' => 'html',
      'file' => ['foo.html'],
      'path' => 'path/to/file'
    ]));
  }

  /** @test */
  public function find_controller_method_returns_the_actual_controller_file_corresponding_to_a_given_path_if_result_is_known()
  {
    $method = $this->getNonPublicMethod('_find_controller');

    $known  = $this->getNonPublicProperty('_known');

    $known['js']['path/to/app'] = [
      'file' => "./tests/storage/plugins/plugin_name/html/app.js",
      'path' => 'path/to/app',
      'ext' => 'js',
      'plugin' => 'plugin_name',
      'mode' => 'js',
      'i18n' => "./tests/storage/plugins/plugin_name/locale/en/en.json",
    ];

    $this->setNonPublicPropertyValue('_known', $known);

    $result = $method->invoke($this->router, 'path/to/app', 'js');

    $this->assertSame($known['js']['path/to/app'], $result);
  }

  /** @test */
  public function find_controller_method_returns_the_actual_controller_file_corresponding_to_a_given_path_if_alt_root_does_not_exist()
  {
    $testing_dir_path = $this->getTestingDirName();
    $method           = $this->getNonPublicMethod('_find_controller');

    $this->mvc_mock->shouldReceive('getPlugins')
      ->twice()
      ->withNoArgs()
      ->andReturn([]);

    // When there's a home.php file

    $this->createDir($dir = 'mvc/public/path/to/app');
    $this->createFile('home.php', '', $dir);

    $result   = $method->invoke($this->router, 'path/to/app', 'public');
    $expected = [
      'file'      => $testing_dir_path . 'mvc/public/path/to/app/home.php',
      'path'      => 'path/to/app/home',
      'root'      => $testing_dir_path,
      'request'   => 'path/to/app',
      'mode'      => 'public',
      'plugin'    => false,
      'args'      => [],
      'checkers'  => []
    ];

    $this->assertSame($expected, $result);

    // When there's not a home.php file

    $this->createDir($dir = 'mvc/public/path/to');
    $this->createFile('app.php', '', $dir);

    $result   = $method->invoke($this->router, 'path/to/app', 'public');
    $expected = [
      'file'      => $testing_dir_path . 'mvc/public/path/to/app.php',
      'path'      => 'path/to/app',
      'root'      => $testing_dir_path,
      'request'   => 'path/to/app',
      'mode'      => 'public',
      'plugin'    => false,
      'args'      => [],
      'checkers'  => []
    ];

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function find_controller_method_returns_the_actual_controller_file_corresponding_to_a_given_path_if_alt_root_exists()
  {
    $testing_dir_path = $this->getTestingDirName();
    $method           = $this->getNonPublicMethod('_find_controller');

    $this->setNonPublicPropertyValue('_routes', [
      'root' => [
        'path/to/app' => [
          'root' => 'app',
          'name' => 'app',
          'url'  => 'app',
          'path' => "{$testing_dir_path}path/to/app"
        ]
      ]
    ]);

    $this->mvc_mock->shouldReceive('getPlugins')
      ->twice()
      ->withNoArgs()
      ->andReturn([
        'plugin' => [
          'name' => 'plugin',
          'url'  => 'path/to/app',
          'path' =>  "{$testing_dir_path}path/to/app"
        ]
      ]);

    $this->createDir('path/to/app/src/mvc/public/foo');
    $this->createFile('bar.php', '', 'path/to/app/src/mvc/public/foo');

    $result    = $method->invoke($this->router, 'path/to/app/foo/bar', 'public');
    $expected  = [
      'file'      => $testing_dir_path . 'path/to/app/src/mvc/public/foo/bar.php',
      'path'      => 'path/to/app/foo/bar',
      'root'      => $testing_dir_path . 'path/to/app/src/',
      'request'   => 'path/to/app/foo/bar',
      'mode'      => 'public',
      'plugin'    => 'path/to/app',
      'args'      => [],
      'checkers'  => []
    ];

    $this->assertSame($expected, $result);

    // When home.php exists

    $this->createDir('path/to/app/src/mvc/public/foo');
    $this->createFile('home.php', '', 'path/to/app/src/mvc/public/foo');

    $result    = $method->invoke($this->router, 'path/to/app/foo', 'public');
    $expected  = [
      'file'      => $testing_dir_path . 'path/to/app/src/mvc/public/foo/home.php',
      'path'      => 'path/to/app/foo/home',
      'root'      => $testing_dir_path . 'path/to/app/src/',
      'request'   => 'path/to/app/foo',
      'mode'      => 'public',
      'plugin'    => 'path/to/app',
      'args'      => [],
      'checkers'  => []
    ];

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function find_controller_method_returns_the_actual_controller_file_corresponding_to_a_given_path_if_mode_is_dom_and_alt_root_does_not_exists()
  {
    $testing_dir_path = $this->getTestingDirName();
    $method           = $this->getNonPublicMethod('_find_controller');

    $this->mvc_mock->shouldReceive('getPlugins')
      ->times(3)
      ->withNoArgs()
      ->andReturn([]);

    $this->createDir('mvc/public/path/to/app');
    $this->createFile('index.php', '', 'mvc/public/path/to/app');

    $result   = $method->invoke($this->router, 'path/to/app', 'dom');
    $expected = [
      'file'      => $testing_dir_path . 'mvc/public/path/to/app/index.php',
      'path'      => 'path/to/app',
      'root'      => $testing_dir_path,
      'request'   => 'path/to/app',
      'mode'      => 'dom',
      'plugin'    => false,
      'args'      => [],
      'checkers'  => []
    ];

    $this->assertSame($expected, $result);

    // When provided path is "." and file does not exists.
    $this->cleanTestingDir();

    $result = $method->invoke($this->router, '.', 'dom');

    $this->assertNull($result);

    // When provided path is "." and file exists.
    $this->createDir('mvc/public');
    $this->createFile('index.php', '', 'mvc/public');

    $result   = $method->invoke($this->router, '.', 'dom');
    $expected = [
      'file'      => $testing_dir_path . 'mvc/public/index.php',
      'path'      => '.',
      'root'      => $testing_dir_path,
      'request'   => '.',
      'mode'      => 'dom',
      'plugin'    => false,
      'args'      => [],
      'checkers'  => []
    ];

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function find_controller_method_returns_the_actual_controller_file_corresponding_to_a_given_path_if_mode_is_dom_and_alt_root_exists()
  {
    $testing_dir_path = $this->getTestingDirName();
    $method           = $this->getNonPublicMethod('_find_controller');

    $this->setNonPublicPropertyValue('_routes', [
      'root' => [
        'path/to/app' => [
          'root' => 'app',
          'name' => 'app',
          'url'  => 'app',
          'path' => "{$testing_dir_path}path/to/app"
        ]
      ]
    ]);

    $this->mvc_mock->shouldReceive('getPlugins')
      ->once()
      ->withNoArgs()
      ->andReturn([
        'plugin' => [
          'name' => 'plugin',
          'url'  => 'path/to/app',
          'path' =>  "{$testing_dir_path}path/to/app"
        ]
      ]);

    $this->createDir('path/to/app/src/mvc/public/foo/bar');
    $this->createFile('index.php', '', 'path/to/app/src/mvc/public/foo/bar');

    $result   = $method->invoke($this->router, 'path/to/app/foo/bar', 'dom');
    $expected = [
      'file'      => $testing_dir_path . 'path/to/app/src/mvc/public/foo/bar/index.php',
      'path'      => 'path/to/app/foo/bar',
      'root'      => $testing_dir_path . 'path/to/app/src/',
      'request'   => 'path/to/app/foo/bar',
      'mode'      => 'dom',
      'plugin'    => 'path/to/app',
      'args'      => [],
      'checkers'  => []
    ];

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function find_controller_method_returns_null_when_file_does_not_exists()
  {
    $method = $this->getNonPublicMethod('_find_controller');

    $this->mvc_mock->shouldReceive('getPlugins')
      ->once()
      ->withNoArgs()
      ->andReturn([]);

    $this->assertNull($method->invoke($this->router, 'path/to/app', 'public'));
  }

  /** @test */
  public function find_plugin_method_returns_plugin_info_from_given_path_if_exists()
  {
    $method = $this->getNonPublicMethod('_find_plugin');

    $this->mvc_mock->shouldReceive('getPlugins')
      ->twice()
      ->withNoArgs()
      ->andReturn([
        'plugin' => $plugin = [
          'name' => 'plugin',
          'url'  => 'path/to/plugin',
          'path' =>  "path/to/plugin"
        ]
      ]);

    $result = $method->invoke($this->router, 'path/to/plugin');
    $this->assertSame($plugin, $result);


    $result = $method->invoke($this->router, 'path/to/plugin/');
    $this->assertSame($plugin, $result);

  }

  /** @test */
  public function find_plugin_method_returns_null_if_plugin_from_the_given_path_does_not_exists()
  {
    $method = $this->getNonPublicMethod('_find_plugin');

    $this->mvc_mock->shouldReceive('getPlugins')
      ->once()
      ->withNoArgs()
      ->andReturn([
        'another_plugin' => [
          'name' => 'plugin',
          'url'  => 'path/to/another/plugin',
          'path' =>  "path/to/another/plugin"
        ]
      ]);

    $result = $method->invoke($this->router, 'path/to/plugin');

    $this->assertNull($result);
  }

  /** @test */
  public function find_plugin_method_returns_null_if_no_plugins_are_registered()
  {
    $method = $this->getNonPublicMethod('_find_plugin');

    $this->mvc_mock->shouldReceive('getPlugins')
      ->once()
      ->withNoArgs()
      ->andReturn([]);

    $result = $method->invoke($this->router, 'path/to/plugin');

    $this->assertNull($result);
  }

  /** @test */
  public function find_translation_method_returns_translation_file_path_to_the_given_plugin()
  {
    $method      = $this->getNonPublicMethod('_find_translation');
    $testing_dir = $this->getTestingDirName();

    $this->mvc_mock->shouldReceive('getLocale')
      ->once()
      ->withNoArgs()
      ->andReturn('en');

    $this->mvc_mock->shouldReceive('pluginPath')
      ->once()
      ->with('plugin', false)
      ->andReturn("{$testing_dir}path/to/plugin/");

    $this->createDir('path/to/plugin/locale/en');
    $this->createFile('en.json', '', 'path/to/plugin/locale/en');

    $result = $method->invoke($this->router, 'plugin');

    $this->assertSame("{$testing_dir}path/to/plugin/locale/en/en.json", $result);
  }

  /** @test */
  public function find_translation_method_returns_translation_file_path_when_no_plugin_is_provided()
  {
    $method      = $this->getNonPublicMethod('_find_translation');
    $testing_dir = $this->getTestingDirName();

    $this->mvc_mock->shouldReceive('getLocale')
      ->once()
      ->withNoArgs()
      ->andReturn('en');

    $this->mvc_mock->shouldReceive('appPath')
      ->once()
      ->withNoArgs()
      ->andReturn("{$testing_dir}path/to/plugin/");

    $this->createDir('path/to/plugin/locale/en');
    $this->createFile('en.json', '', 'path/to/plugin/locale/en');

    $result = $method->invoke($this->router);

    $this->assertSame("{$testing_dir}path/to/plugin/locale/en/en.json", $result);
  }

  /** @test */
  public function find_translation_method_returns_null_when_no_locale_found()
  {
    $method = $this->getNonPublicMethod('_find_translation');

    $this->mvc_mock->shouldReceive('getLocale')
      ->once()
      ->withNoArgs()
      ->andReturnNull();

    $result = $method->invoke($this->router);

    $this->assertNull($result);
  }

  /** @test */
  public function find_translation_method_returns_null_locale_file_does_not_exist()
  {
    $method = $this->getNonPublicMethod('_find_translation');

    $this->mvc_mock->shouldReceive('getLocale')
      ->once()
      ->withNoArgs()
      ->andReturn('en');

    $this->mvc_mock->shouldReceive('pluginPath')
      ->once()
      ->with('plugin', false)
      ->andReturn("path/to/plugin/");

    $result = $method->invoke($this->router, 'plugin');

    $this->assertNull($result);
  }

  /** @test */
  public function get_classic_root_method_returns_the_full_path_in_the_mvc_of_the_main_app()
  {
    $method = $this->getNonPublicMethod('_get_classic_root');
    $root   = $this->getNonPublicProperty('_root');

    $result = $method->invoke($this->router, 'html');
    $this->assertSame($root . 'mvc/html/', $result);

    $result = $method->invoke($this->router, 'dom');
    $this->assertSame($root . 'mvc/public/', $result);

    $result = $method->invoke($this->router, 'cli');
    $this->assertSame($root . 'cli/', $result);

    $result = $method->invoke($this->router, 'js');
    $this->assertSame($root . 'mvc/js/', $result);
  }

  /** @test */
  public function get_classic_root_method_returns_null_when_mode_does_not_exist()
  {
    $method = $this->getNonPublicMethod('_get_classic_root');

    $this->assertNull(
      $method->invoke($this->router, 'foo')
    );
  }

  /** @test */
  public function get_plugin_root_method_returns_plugin_root_from_the_given_mode_and_plugin()
  {
    $method = $this->getNonPublicMethod('_get_plugin_root');

    $this->mvc_mock->shouldReceive('pluginPath')
      ->once()
      ->with('plugin', false)
      ->andReturn('path/to/plugin/');

    $result = $method->invoke($this->router, 'public', 'plugin');

    $this->assertSame('path/to/plugin/mvc/public/', $result);
  }

  /** @test */
  public function get_plugin_root_method_returns_null_when_the_provided_mode_does_not_exists()
  {
    $method = $this->getNonPublicMethod('_get_plugin_root');

    $this->assertNull(
      $method->invoke($this->router, 'foo', 'plugin')
    );
  }

  /** @test */
  public function get_subplugin_root_method_returns_sub_plugin_for_the_provided_mode_and_plugin()
  {
    $method = $this->getNonPublicMethod('_get_subplugin_root');

    $this->mvc_mock->shouldReceive('pluginPath')
      ->once()
      ->with('plugin_name', false)
      ->andReturn('path/to/plugin_name/');

    $result = $method->invoke($this->router, 'html', 'plugin_name', 'sub_plugin');

    $this->assertSame('path/to/plugin_name/plugins/sub_plugin/html/', $result);
  }

  /** @test */
  public function get_subplugin_root_returns_null_when_the_provided_mode_does_not_exist()
  {
    $method = $this->getNonPublicMethod('_get_subplugin_root');

    $this->assertNull(
      $method->invoke($this->router, 'public', 'plugin_name', 'sub_plugin')
    );

    $this->assertNull(
      $method->invoke($this->router, 'private', 'plugin_name', 'sub_plugin')
    );

    $this->assertNull(
      $method->invoke($this->router, 'foo', 'plugin_name', 'sub_plugin')
    );
  }

  /** @test */
  public function get_custom_root_method_returns_custom_root_for_the_given_mode_and_plugin()
  {
    $method = $this->getNonPublicMethod('_get_custom_root');

    $result = $method->invoke($this->router, 'js', 'plugin_name');

    $this->assertSame(
      $this->getNonPublicProperty('_root') . 'plugins/plugin_name/js/',
      $result
    );
  }

  /** @test */
  public function get_custom_root_method_returns_null_when_mode_does_not_exist()
  {
    $method = $this->getNonPublicMethod('_get_custom_root');

    $this->assertNull(
      $method->invoke($this->router, 'public', 'plugin_name')
    );

    $this->assertNull(
      $method->invoke($this->router, 'private', 'plugin_name')
    );

    $this->assertNull(
      $method->invoke($this->router, 'foo', 'plugin_name')
    );
  }

  /** @test */
  public function find_mv_method_returns_model_view_info_from_the_given_path_and_mode_when_result_is_known()
  {
    $method = $this->getNonPublicMethod('_find_mv');
    $known  = $this->getNonPublicProperty('_known');

    $known['js']['path/to/app'] = [
      'file' => "./tests/storage/plugins/plugin_name/html/app.js",
      'path' => 'path/to/app',
      'ext' => 'js',
      'plugin' => 'plugin_name',
      'mode' => 'js',
      'i18n' => "./tests/storage/plugins/plugin_name/locale/en/en.json",
    ];

    $this->setNonPublicPropertyValue('_known', $known);

    $result = $method->invoke($this->router, 'path/to/app', 'js');

    $this->assertSame($known['js']['path/to/app'], $result);
  }

  /** @test */
  public function find_mv_method_returns_model_view_info_from_the_given_path_when_alt_root_does_not_exist()
  {
    $method      = $this->getNonPublicMethod('_find_mv');
    $testing_dir = $this->getTestingDirName();

    $this->mvc_mock->shouldReceive('getPlugins')
      ->once()
      ->withNoArgs()
      ->andReturn([]);

    $this->mvc_mock->shouldReceive('getLocale')
      ->once()
      ->withNoArgs()
      ->andReturnNull();

    $this->createDir('mvc/js/path/to');
    $this->createFile('app.js', '', 'mvc/js/path/to');

    $result   =  $method->invoke($this->router, 'path/to/app', 'js');
    $expected = [
      'file'    => $testing_dir . 'mvc/js/path/to/app.js',
      'path'    => 'path/to/app',
      'plugin'  => false,
      'ext'     => 'js',
      'mode'    => 'js',
      'i18n'    => null
    ];

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function find_mv_method_returns_model_view_info_from_the_given_path_when_alt_root_exists()
  {
    $method      = $this->getNonPublicMethod('_find_mv');
    $testing_dir = $this->getTestingDirName();

    $this->mvc_mock->shouldReceive('getPlugins')
      ->once()
      ->withNoArgs()
      ->andReturn([
        'plugin_name' => [
          'name' => 'plugin_name',
          'url'  => 'path/to/app',
          'path' =>  "{$testing_dir}path/to/app"
        ]
      ]);

    $this->mvc_mock->shouldReceive('getLocale')
      ->once()
      ->withNoArgs()
      ->andReturnNull();

    $this->mvc_mock->shouldReceive('pluginPath')
      ->once()
      ->with('plugin_name', false)
      ->andReturn('path/to/app/plugin');

    $this->setNonPublicPropertyValue('_routes', [
      'root' => [
        'path/to/app' => [
          'root' => 'app',
          'name' => 'plugin_name',
          'url'  => 'app',
          'path' => "{$testing_dir}path/to/app"
        ]
      ]
    ]);

    $this->createDir('mvc/js/path/to/app');
    $this->createFile('plugin_name.js', '', 'mvc/js/path/to/app');

    $result   =  $method->invoke($this->router, 'path/to/app/plugin_name', 'js');
    $expected = [
      'file'    => $testing_dir . 'mvc/js/path/to/app/plugin_name.js',
      'path'    => 'path/to/app/plugin_name',
      'plugin'  => 'path/to/app',
      'ext'     => 'js',
      'mode'    => 'js',
      'i18n'    => null
    ];

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function find_mv_method_returns_null_when_file_does_not_exist()
  {
    $method = $this->getNonPublicMethod('_find_mv');

    $this->mvc_mock->shouldReceive('getPlugins')
      ->once()
      ->withNoArgs()
      ->andReturn([]);

    $result = $method->invoke($this->router, 'path/to/app', 'js');

    $this->assertNull($result);
  }

  /** @test */
  public function find_mv_method_returns_null_when_mode_does_not_exist()
  {
    $method = $this->getNonPublicMethod('_find_mv');

    $this->assertNull(
      $method->invoke($this->router, 'path/to','foo')
    );
  }

  /** @test */
  public function registerLocaleDomain_method_sets_up_the_locale_for_the_given_plugin()
  {
    $method       = $this->getNonPublicMethod('_registerLocaleDomain');
    $testing_dir  = $this->getTestingDirName();

    $this->setNonPublicPropertyValue('_textdomains', []);
    $this->setNonPublicPropertyValue('_routes', [
      'root' => [
        'path/to/plugin' => [
          'root' => 'app',
          'name' => 'plugin_name',
          'url'  => 'app',
          'path' => "{$testing_dir}path/to/plugin/"
        ]
      ]
    ]);

    $this->createDir('path/to/plugin/src/locale');
    $this->createFile('index.txt', '44', 'path/to/plugin/src/locale');

    $result = $method->invoke($this->router, 'path/to/plugin');

    $this->assertSame('plugin_name44', $result);
    $this->assertSame(['plugin_name' => 'plugin_name44'], $this->getNonPublicProperty('_textdomains'));
  }

  /** @test */
  public function registerLocaleDomain_method_sets_up_the_locale_when_no_plugin_is_provided()
  {
    $method       = $this->getNonPublicMethod('_registerLocaleDomain');
    $testing_dir  = $this->getTestingDirName();

    // Set the app path of Mvc class to the testing dir
    $this->setNonPublicPropertyValue('_app_path', $testing_dir, Mvc::class);

    $this->setNonPublicPropertyValue('_textdomains', []);

    $this->createDir('src/locale');
    $this->createFile('index.txt', '44', 'src/locale');

    $result = $method->invoke($this->router);

    $this->assertSame('main44', $result);
    $this->assertSame(['main' => 'main44'], $this->getNonPublicProperty('_textdomains'));
  }

  /** @test */
  public function registerLocaleDomain_method_sets_up_the_locale_for_the_given_plugin_and_file_does_not_exist_but_dir_exists()
  {
    $method       = $this->getNonPublicMethod('_registerLocaleDomain');
    $testing_dir  = $this->getTestingDirName();

    $this->setNonPublicPropertyValue('_textdomains', []);
    $this->setNonPublicPropertyValue('_routes', [
      'root' => [
        'path/to/plugin' => [
          'root' => 'app',
          'name' => 'plugin_name',
          'url'  => 'app',
          'path' => "{$testing_dir}path/to/plugin/"
        ]
      ]
    ]);

    $this->createDir('path/to/plugin/src/locale');

    $result = $method->invoke($this->router, 'path/to/plugin');

    $this->assertSame('plugin_name', $result);
    $this->assertSame(['plugin_name' => 'plugin_name'], $this->getNonPublicProperty('_textdomains'));
  }

  /** @test */
  public function registerLocaleDomain_method_returns_text_domain_for_the_given_plugin_when_text_domain_already_exists()
  {
    $method       = $this->getNonPublicMethod('_registerLocaleDomain');
    $testing_dir  = $this->getTestingDirName();

    $this->setNonPublicPropertyValue('_textdomains', ['plugin_name' => 'plugin_name44']);
    $this->setNonPublicPropertyValue('_routes', [
      'root' => [
        'path/to/plugin' => [
          'root' => 'app',
          'name' => 'plugin_name',
          'url'  => 'app',
          'path' => "{$testing_dir}path/to/plugin/"
        ]
      ]
    ]);

    $this->createDir('path/to/plugin/src/locale');
    // file content is empty to ensure that it does not get it's content
    $this->createFile('index.txt', '', 'path/to/plugin/src/locale');

    $result = $method->invoke($this->router, 'path/to/plugin');

    $this->assertSame('plugin_name44', $result);
    $this->assertSame(['plugin_name' => 'plugin_name44'], $this->getNonPublicProperty('_textdomains'));
  }

  /** @test */
  public function registerLocaleDomain_method_returns_null_when_dir_does_not_exist()
  {
    $method       = $this->getNonPublicMethod('_registerLocaleDomain');
    $testing_dir  = $this->getTestingDirName();

    $this->setNonPublicPropertyValue('_textdomains', []);
    $this->setNonPublicPropertyValue('_routes', [
      'root' => [
        'path/to/plugin' => [
          'root' => 'app',
          'name' => 'plugin_name',
          'url'  => 'app',
          'path' => "{$testing_dir}path/to/plugin/"
        ]
      ]
    ]);

    $result = $method->invoke($this->router, 'path/to/plugin');

    $this->assertNull($result);
    $this->assertSame([], $this->getNonPublicProperty('_textdomains'));
  }
}