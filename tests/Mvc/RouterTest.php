<?php

namespace bbn\tests\Mvc;

use bbn\Mvc;
use bbn\Mvc\Router;
use PHPUnit\Framework\TestCase;
use bbn\tests\Files;
use bbn\tests\Reflectable;

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
  public function testConstructorTest()
  {
    $this->assertInstanceOf(Mvc::class, $this->getNonPublicProperty('_mvc'));
    $this->assertSame($this->routes, $this->getNonPublicProperty('_routes'));
    $this->assertSame($this->app_path, $this->getNonPublicProperty('_root'));
    $this->assertSame(['main' => 'main9'], $this->getNonPublicProperty('_textdomains'));
  }

  /** @test */
  public function testConstructorTestWhenAppPathDoesNotExist()
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
  public function testIsmodeMethodChecksIfTheGivenStringIsAValidMode()
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
  public function testParseMethodRemovesTrailingSlashes()
  {
    $this->assertSame('./foo/bar/baz/', Router::parse('.//foo//bar//baz//'));
  }

  /** @test */
  public function testResetMethodResetsTheFullPathOfAPlugin()
  {
   $result = $this->router->reset();

    $this->assertFalse($this->getNonPublicProperty('alt_root'));
    $this->assertInstanceOf(Router::class, $result);
  }

  /** @test */
  public function testSetprepathMethodSetsPrePathWhenModeIsNotDefined()
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
  public function testSetprepathMethodSetsPrePathWhenModeDefined()
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
  public function testSetprepathMethodThrowsAnExceptionWhenPathIsNotValid()
  {
    $this->expectException(\Exception::class);

    $this->router = \Mockery::mock(Router::class)->makePartial();

    $this->router->shouldReceive('checkPath')
      ->with('/prepath/')
      ->andReturnFalse();

    $this->router->setPrepath('/prepath/');
  }

  /** @test */
  public function testGetprepathMethodReturnsThePrePathWhenExists()
  {
    $this->setNonPublicPropertyValue('_prepath' ,'/prepath/');

    $this->assertSame('/prepath/', $this->router->getPrepath());
    $this->assertSame('/prepath', $this->router->getPrepath(0));
  }

  /** @test */
  public function testGetprepathMethodReturnsEmptyStringIfNotExists()
  {
    $this->setNonPublicPropertyValue('_prepath' ,'');

    $this->assertSame('', $this->router->getPrepath());
  }

  /** @test */
  public function testGetlocaledomainMethodReturnsTextDomainsForTheGivenNameOrForTheMainIfNotGiven()
  {
    $this->setNonPublicPropertyValue('_textdomains', ['main' => 'main_result', 'plugin' => 'plugin_result']);

    $this->assertSame('main_result', $this->router->getLocaleDomain());
    $this->assertSame('plugin_result', $this->router->getLocaleDomain('plugin'));
  }

  /** @test */
  public function testGetpluginfromcomponentMethodRetrievesPluginNameFromComponentNameIfAny()
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
  public function testRoutecomponentMethodReturnsRouteComponentWhenTheGivenPluginExistsAndDirExists()
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
  public function testRoutecomponentMethodReturnsNullWhenPluginExistsAndDirDoesNotExist()
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
  public function testRoutecomponentMethodReturnsRouteComponentWhenTheGivenPluginDoesNotExistAndPluginDirExists()
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
  public function testRoutecomponentMethodReturnsNullWhenTheGivenPluginDoesNotExistAndPluginDirDoesNotExist()
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
  public function testRoutecustompluginMethodReturnsCustomPluginsDataFromTheGivenArguments()
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
  public function testRoutecustompluginMethodReturnsNullWhenModeDoesNotExistInFiletypes()
  {
    $this->assertNull(
      $this->router->routeCustomPlugin('app', 'foo', 'plugin_name')
    );
  }

  /** @test */
  public function testRoutesubpluginMethodReturnsSubPluginDataFromGivenArguments()
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
  public function testRoutesubpluginMethodReturnsNullWhenModeDoesNotExistsInFiletypes()
  {
    $this->assertNull(
      $this->router->routeSubplugin('app', 'foo', 'plugin_name', 'sub_plugin_name')
    );
  }

  /** @test */
  public function testRouteMethodReturnsControllerFileInfoFromTheGivenPath()
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
  public function testRouteMethodReturnsControllerFileInfoFromTheGivenPathWithAPrePath()
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
  public function testRouteMethodReturnsModelViewInfoFromTheGivenPath()
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
  public function testRouteMethodReturnsModelViewInfoFromTheGivenPathWithAPrepath()
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
  public function testRouteMethodReturnsNullWhenModeDoesNotExists()
  {
    $this->assertNull(
      $this->router->route('app', 'foo')
    );
  }

  /** @test */
  public function testFetchdirMethodFetchesDirContentForModelAndViewsWhenDirExists()
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
  public function testFetchdirMethodFetchesDirContentForModelAndViewsWhenDirDoesNotExistAndPluginExistsAndHasAnAltRoot()
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
  public function testFetchdirMethodFetchesDirContentForModelAndViewsWhenDirDoesNotExistAndPluginDoesNotExistAndAltRootIsDefined()
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
  public function testFetchdirMethodReturnsNullWhenDirDoesNotExistAndAltRootIsNotDefined()
  {
    $this->mvc_mock->shouldReceive('getPlugins')
      ->once()
      ->withNoArgs()
      ->andReturn([]);

    $result = $this->router->fetchDir('app', 'html');

    $this->assertNull($result);
  }

  /** @test */
  public function testGetroutesMethodReturnsTheRegisteredRoutes()
  {
    $this->assertSame($this->routes, $this->router->getRoutes());
  }

  /** @test */
  public function testGetRootMethodReturnsTheFullPathInTheMvcOfTheMainApp()
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
  public function testGetRootMethodReturnsNullWhenModeDoesNotExist()
  {
    $get_root_method = $this->getNonPublicMethod('_get_root');

    $this->assertNull(
      $get_root_method->invoke($this->router, 'foo')
    );
  }

  /** @test */
  public function testGetModePathMethodReturnsTheModePath()
  {
    $method = $this->getNonPublicMethod('_get_mode_path');

    $this->assertSame('mvc/public/', $method->invoke($this->router, 'dom'));
    $this->assertSame('cli/', $method->invoke($this->router, 'cli'));
    $this->assertSame('mvc/html/', $method->invoke($this->router, 'html'));
    $this->assertSame('mvc/css/', $method->invoke($this->router, 'css'));
    $this->assertSame('mvc/model/', $method->invoke($this->router, 'model'));
  }

  /** @test */
  public function testGetModePathMethodThrowsAnExceptionWhenModeDoesNotExist()
  {
    $this->expectException(\Exception::class);

    $this->getNonPublicMethod('_get_mode_path')
      ->invoke($this->router, 'foo');
  }

  /** @test */
  public function testGetAltRootMethodReturnsFullPathInTheMvcOfAnExternalAppFromTheProvidedModeAndPath()
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
  public function testGetAltRootMethodReturnsNullWhenModeDoesNotExists()
  {
    $this->assertNull(
      $this->getNonPublicMethod('_get_alt_root')
      ->invoke($this->router, 'foo', 'app_alt_root')
    );
  }

  /** @test */
  public function testGetAltRootMethodReturnsNullWhenItsNotRegisteredInRoutes()
  {
    $this->setNonPublicPropertyValue('_routes', []);

    $this->assertNull(
      $this->getNonPublicMethod('_get_alt_root')
        ->invoke($this->router, 'html', 'app_alt_root')
    );
  }

  /** @test */
  public function testGetAltRootMethodReturnsNullWhenPathIsNotProvidedAndAltRootPropertyIsNotDefined()
  {
    $this->setNonPublicPropertyValue('alt_root', null);

    $this->assertNull(
      $this->getNonPublicMethod('_get_alt_root')
        ->invoke($this->router, 'html')
    );
  }

  /** @test */
  public function testIsAliasMethodChecksIfAPathIsPartOfAliasInTheRoutesArray()
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
  public function testGetAliasMethodReturnsTheAliasOfTheGivenPathIfItIsPartOfTheAliasInTheRoutesArray()
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
  public function testIsKnownMethodChecksIfTheGivenPathIsKnownForItsCorrespondingMode()
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
  public function testGetKnownMethodRetrievesARouteFromAGivenPathInAGivenMode()
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
  public function testSetKnownMethodSetsAndStoresAGivenRoute()
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
  public function testSetKnownMethodSetsAndStoresAGivenRouteAndAddsTheCorrespondingControllerChecker()
  {
    $testing_dir_path = $this->getTestingDirName();
    $method           = $this->getNonPublicMethod('_set_known');

    $this->createDir('mvc/public/path');
    $this->createFile('_super.php', '', 'mvc/public/path');

    $result = $method->invoke($this->router, $data = [
      'mode' => 'public',
      'path' => 'path',
      'file' => 'index.php'
    ]);

    $known = $this->getNonPublicProperty('_known');

    $data = array_merge($data, ['checkers' => ["{$testing_dir_path}mvc/public/path/_super.php"]]);

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
    $this->createFile('_super.php', '', 'mvc/public/plugin/path/to');
    $this->createFile('_super.php', '', 'plugin/path/to/src/mvc/public');

    $result = $method->invoke($this->router, $data = [
      'mode' => 'public',
      'path' => 'plugin/path/to',
      'file' => 'index.php',
      'plugin' => 'plugin'
    ]);

    $known = $this->getNonPublicProperty('_known');

    $expected = array_merge($data, [
      'checkers' => [
        "{$testing_dir_path}plugin/path/to/src/mvc/public/_super.php",
        "{$testing_dir_path}mvc/public/plugin/path/to/_super.php",
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
  public function testSetKnownMethodSetsAndStoresAGivenRouteAndAddsTheCorrespondingCssChecker()
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
  public function testSetKnownMethodSetsAndStoresAGivenRouteAndAddsTheCorrespondingModelChecker()
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
  public function testSetKnownMethodReturnsNullWhenRequiredDataAreMissing()
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
  public function testFindControllerMethodReturnsTheActualControllerFileCorrespondingToAGivenPathIfResultIsKnown()
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
  public function testFindControllerMethodReturnsTheActualControllerFileCorrespondingToAGivenPathIfAltRootDoesNotExist()
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
  public function testFindControllerMethodReturnsTheActualControllerFileCorrespondingToAGivenPathIfAltRootExists()
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
  public function testFindControllerMethodReturnsTheActualControllerFileCorrespondingToAGivenPathIfModeIsDomAndAltRootDoesNotExists()
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
  public function testFindControllerMethodReturnsTheActualControllerFileCorrespondingToAGivenPathIfModeIsDomAndAltRootExists()
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
  public function testFindControllerMethodReturnsNullWhenFileDoesNotExists()
  {
    $method = $this->getNonPublicMethod('_find_controller');

    $this->mvc_mock->shouldReceive('getPlugins')
      ->once()
      ->withNoArgs()
      ->andReturn([]);

    $this->assertNull($method->invoke($this->router, 'path/to/app', 'public'));
  }

  /** @test */
  public function testFindPluginMethodReturnsPluginInfoFromGivenPathIfExists()
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
  public function testFindPluginMethodReturnsNullIfPluginFromTheGivenPathDoesNotExists()
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
  public function testFindPluginMethodReturnsNullIfNoPluginsAreRegistered()
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
  public function testFindTranslationMethodReturnsTranslationFilePathToTheGivenPlugin()
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
  public function testFindTranslationMethodReturnsTranslationFilePathWhenNoPluginIsProvided()
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
  public function testFindTranslationMethodReturnsNullWhenNoLocaleFound()
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
  public function testFindTranslationMethodReturnsNullLocaleFileDoesNotExist()
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
  public function testGetClassicRootMethodReturnsTheFullPathInTheMvcOfTheMainApp()
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
  public function testGetClassicRootMethodReturnsNullWhenModeDoesNotExist()
  {
    $method = $this->getNonPublicMethod('_get_classic_root');

    $this->assertNull(
      $method->invoke($this->router, 'foo')
    );
  }

  /** @test */
  public function testGetPluginRootMethodReturnsPluginRootFromTheGivenModeAndPlugin()
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
  public function testGetPluginRootMethodReturnsNullWhenTheProvidedModeDoesNotExists()
  {
    $method = $this->getNonPublicMethod('_get_plugin_root');

    $this->assertNull(
      $method->invoke($this->router, 'foo', 'plugin')
    );
  }

  /** @test */
  public function testGetSubpluginRootMethodReturnsSubPluginForTheProvidedModeAndPlugin()
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
  public function testGetSubpluginRootReturnsNullWhenTheProvidedModeDoesNotExist()
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
  public function testGetCustomRootMethodReturnsCustomRootForTheGivenModeAndPlugin()
  {
    $method = $this->getNonPublicMethod('_get_custom_root');

    $result = $method->invoke($this->router, 'js', 'plugin_name');

    $this->assertSame(
      $this->getNonPublicProperty('_root') . 'plugins/plugin_name/js/',
      $result
    );
  }

  /** @test */
  public function testGetCustomRootMethodReturnsNullWhenModeDoesNotExist()
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
  public function testFindMvMethodReturnsModelViewInfoFromTheGivenPathAndModeWhenResultIsKnown()
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
  public function testFindMvMethodReturnsModelViewInfoFromTheGivenPathWhenAltRootDoesNotExist()
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
  public function testFindMvMethodReturnsModelViewInfoFromTheGivenPathWhenAltRootExists()
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
  public function testFindMvMethodReturnsNullWhenFileDoesNotExist()
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
  public function testFindMvMethodReturnsNullWhenModeDoesNotExist()
  {
    $method = $this->getNonPublicMethod('_find_mv');

    $this->assertNull(
      $method->invoke($this->router, 'path/to','foo')
    );
  }

  /** @test */
  public function testRegisterlocaledomainMethodSetsUpTheLocaleForTheGivenPlugin()
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
  public function testRegisterlocaledomainMethodSetsUpTheLocaleWhenNoPluginIsProvided()
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
  public function testRegisterlocaledomainMethodSetsUpTheLocaleForTheGivenPluginAndFileDoesNotExistButDirExists()
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
  public function testRegisterlocaledomainMethodReturnsTextDomainForTheGivenPluginWhenTextDomainAlreadyExists()
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
  public function testRegisterlocaledomainMethodReturnsNullWhenDirDoesNotExist()
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