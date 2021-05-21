<?php

namespace tests\Mvc;

use bbn\Mvc;
use Exception;
use foo\Db;
use Locale;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use stdClass;
use tests\Mockable;
use tests\ReflectionHelpers;
use tests\storage\stubs\stub2;

class MvcTest extends TestCase
{
  use Mockable;

  /**
   * @var Mvc
   */
  protected static $mvc;

  protected static $plugin = [
      'test_plugin' => [
          'name' => 'test_plugin',
          'url'  => 'http://foo.bar',
          'path' => 'foo/bar/'
      ]
  ];


  protected function setUp(): void
  {
    $this->resetMvcInstant();
  }


  public static function setUpBeforeClass(): void
  {
    define('BBN_TEST_PATH', 'test_path/');
    self::initMvc();
  }


  protected function tearDown(): void
  {
    Mockery::close();
  }


  /**
   * @param null       $db
   * @param array|null $routes
   */
  protected static function initMvc($db = null, ?array $routes = null)
  {
    $routes = $routes ?? [
        'root' => [
            self::$plugin['test_plugin']['url'] => [
                'root' => 'TEST',
                'name' => self::$plugin['test_plugin']['name'],
                'url'  => self::$plugin['test_plugin']['url'],
                'path' => self::$plugin['test_plugin']['path']
            ]
        ]
      ];
    if (!defined('BBN_LANG')) {
      define('BBN_LANG', 'en');
    }

    self::$mvc = new Mvc($db, $routes);
  }


  /**
   * @param array|null $value
   * @return void
   * @throws ReflectionException
   */
  protected function registerPlugin(?array $value = null)
  {
    $method = ReflectionHelpers::getNonPublicMethod('registerPlugin', self::$mvc);
    $method->invoke(
            self::$mvc,
            $value ?? [
        'name' => 'test_plugin2',
        'url'  => 'http://foobar.baz',
        'path' => 'foo/baz/'
          ]
        );
  }


  /**
   * @param string $filename
   * @param string $file_content
   * @param string $dirname
   *
   * @return string
   */
  protected function createFile(string $filename, string $file_content, string $dirname)
  {
    if (!is_dir($dir = BBN_APP_PATH . BBN_DATA_PATH . $dirname)) {
      mkdir($dir);
    }

    $fp = fopen($file_path = "$dir/$filename", 'w');
    if (!empty($file_content)) {
      fputs($fp, $file_content);
    }

    fclose($fp);

    return $file_path;
  }


  /**
   * Reset the MVC instant.
   *
   * @return void
   */
  protected function resetMvcInstant()
  {
    if (self::$mvc === null) {
      return;
    }

    $reflectionClass = new ReflectionClass(self::$mvc);

    $singleton_instance = $reflectionClass->getProperty('singleton_instance');
    $singleton_instance->setAccessible(true);
    $singleton_instance->setValue(null);

    $singleton_exists = $reflectionClass->getProperty('singleton_exists');
    $singleton_exists->setAccessible(true);
    $singleton_exists->setValue(false);

    $env_reflection_class = new ReflectionClass(Mvc\Environment::class);
    $initiated            = $env_reflection_class->getProperty('_initiated');
    $initiated->setAccessible(true);
    $initiated->setValue(false);

    self::initMvc(...func_get_args());
  }


  /**
   * @return void
   */
  protected function constructorTest()
  {
    // Ensure plugin is registered
    $plugins = ReflectionHelpers::getNonPublicProperty('plugins', self::$mvc);

    $this->assertIsArray($plugins);
    $this->assertSame($plugins, self::$mvc->getPlugins());

    // Ensure paths are set
    $app_name   = ReflectionHelpers::getNonPublicProperty('_app_name', self::$mvc);
    $app_path   = ReflectionHelpers::getNonPublicProperty('_app_path', self::$mvc);
    $app_prefix = ReflectionHelpers::getNonPublicProperty('_app_prefix', self::$mvc);
    $cur_path   = ReflectionHelpers::getNonPublicProperty('_cur_path', self::$mvc);
    $lib_path   = ReflectionHelpers::getNonPublicProperty('_lib_path', self::$mvc);
    $data_path  = ReflectionHelpers::getNonPublicProperty('_data_path', self::$mvc);

    // The below constants are defined in phpunit.xml
    $this->assertSame(BBN_APP_NAME, $app_name);
    $this->assertSame(BBN_APP_PATH, $app_path);
    $this->assertSame(BBN_APP_PREFIX, $app_prefix);
    $this->assertSame('/', $cur_path);
    $this->assertSame(BBN_LIB_PATH, $lib_path);
    $this->assertSame(BBN_DATA_PATH, $data_path);

    $this->assertInstanceOf(
            Mvc\Router::class,
            ReflectionHelpers::getNonPublicProperty('router', self::$mvc)
        );

    // Ensure plugin is registered
    $this->assertSame(
            array_replace_recursive(self::$plugin, ['test_plugin' => ['path' => 'test_path/foo/bar/']]),
            self::$mvc->getPlugins()
        );

    $this->replaceRouterInstanceWithMockery();
    $this->assertTrue(self::$mvc->check());
  }


  /**
   * @param string[]    $return_value
   * @param string|null $times
   * @return MockInterface
   * @throws ReflectionException
   */
  protected function replaceRouterInstanceWithMockery(
        array $return_value = ['key' => 'value'],
        ?string $times = null
    ) {
    /*
      * Since it depends the Router class, it will be mocked to return an array
      * And ensure that the `route` method of the Router instance is called
      */
    $router_mock = $this->mockClassMethod(
            Mvc\Router::class,
            'route',
            $return_value,
            $times ?? 'once'
        );

    // Set the router property to the mocked router class
    $this->setNonPublicPropertyValue('router', $router_mock);

    // Call the route function again so that that it uses the mocked router
    ReflectionHelpers::getNonPublicMethod('route', self::$mvc)->invoke(self::$mvc);

    return $router_mock;
  }


  /**
   * @param string $name
   * @param        $value
   *
   * @return void
   */
  protected function setNonPublicPropertyValue(string $name, $value)
  {
    ReflectionHelpers::setNonPublicPropertyValue(
            $name,
            self::$mvc,
            $value
        );
  }


  /** @test */
  public function constructor_test_when_db_is_null()
  {
    $this->constructorTest();
    $this->assertNull(ReflectionHelpers::getNonPublicProperty('db', self::$mvc));
  }


  /** @test */
  public function constructor_test_when_db_is_not_null()
  {
    $this->resetMvcInstant(new stdClass());
    $this->constructorTest();
    $this->assertNull(ReflectionHelpers::getNonPublicProperty('db', self::$mvc));

    $db_class_stub = '<?php
      namespace foo;
      class Db {
      
      }';
    $file_path     = self::createFile('Db.php', $db_class_stub, 'stubs');
    include $file_path;
    $this->resetMvcInstant(new Db());
    $this->constructorTest();
    $this->assertInstanceOf(
            Db::class,
            ReflectionHelpers::getNonPublicProperty('db', self::$mvc)
        );
    unlink($file_path);
  }


  /** @test */
  public function constructor_test_when_lang_is_not_defined()
  {
    Locale::setDefault('sv');
    $this->assertSame('sv', Locale::getDefault());
  }


  /** @test */
  public function it_returns_app_name()
  {
    $this->assertSame(BBN_APP_NAME, Mvc::getAppName());
  }


  /** @test */
  public function it_returns_app_prefix()
  {
    $this->assertSame(BBN_APP_PREFIX, Mvc::getAppPrefix());
  }


  /** @test */
  public function it_returns_app_path()
  {
    $this->assertSame(BBN_APP_PATH . 'src/', Mvc::getAppPath(false));
    $this->assertSame(BBN_APP_PATH, Mvc::getAppPath(true));
  }


  /** @test */
  public function it_returns_current_path()
  {
    $this->assertSame('/', Mvc::getCurPath());
  }


  /** @test */
  public function it_returns_lib_path()
  {
    $this->assertSame(BBN_LIB_PATH, Mvc::getLibPath());
  }


  /** @test */
  public function it_returns_data_path()
  {
    $this->assertSame(BBN_DATA_PATH, Mvc::getDataPath());
    $this->assertSame(BBN_DATA_PATH . 'plugins/dummy_plugin/', Mvc::getDataPath('dummy_plugin'));
  }


  /** @test */
  public function it_returns_temp_path()
  {
    $this->assertSame(BBN_DATA_PATH . 'tmp/', Mvc::getTmpPath());
    $this->assertSame(BBN_DATA_PATH . 'tmp/dummy_plugin/', Mvc::getTmpPath('dummy_plugin'));
  }


  /** @test */
  public function it_returns_log_path()
  {
    $this->assertSame(BBN_DATA_PATH . 'logs/', Mvc::getLogPath());
    $this->assertSame(BBN_DATA_PATH . 'logs/dummy_plugin/', Mvc::getLogPath('dummy_plugin'));
  }


  /** @test */
  public function it_returns_cache_path()
  {
    $this->assertSame(BBN_DATA_PATH . 'cache/', Mvc::getCachePath());
    $this->assertSame(BBN_DATA_PATH . 'cache/dummy_plugin/', Mvc::getCachePath('dummy_plugin'));
  }


  /** @test */
  public function it_returns_content_path()
  {
    $this->assertSame(BBN_DATA_PATH . 'content/', Mvc::getContentPath());
    $this->assertSame(
            BBN_DATA_PATH . 'plugins/dummy_plugin/',
            Mvc::getContentPath('dummy_plugin')
        );
  }


  /** @test */
  public function it_register_plugins_and_returns_them_when_needed()
  {
    $this->resetMvcInstant();
    $this->registerPlugin();

    $plugins = ReflectionHelpers::getNonPublicProperty('plugins', self::$mvc);

    $this->assertIsArray($plugins);
    $this->assertSame($plugins, self::$mvc->getPlugins());
  }


  /** @test */
  public function it_checks_whether_or_not_it_has_a_plugin()
  {
    $this->resetMvcInstant();
    $this->registerPlugin();

    $this->assertTrue(self::$mvc->hasPlugin('test_plugin2'));
    $this->assertFalse(self::$mvc->hasPlugin('dummy_plugin'));

    $this->assertTrue(self::$mvc->isPlugin('test_plugin2'));
    $this->assertFalse(self::$mvc->isPlugin('dummy_plugin'));
  }


  /** @test */
  public function it_returns_plugin_url_if_exists_and_false_if_not()
  {
    $this->resetMvcInstant();
    $this->registerPlugin();

    $this->assertSame('http://foobar.baz', Mvc::getPluginUrl('test_plugin2'));
    $this->assertFalse(Mvc::getPluginUrl('dummy_plugin'));
  }


  /** @test */
  public function it_returns_the_url_part_of_a_given_plugin()
  {
    $this->registerPlugin();

    $this->assertSame('foo/baz/src/', Mvc::getPluginPath('test_plugin2'));
    $this->assertNull(Mvc::getPluginPath('dummy_plugin'));
  }


  /** @test */
  public function it_returns_plugin_name_and_false_if_not_found()
  {
    $this->registerPlugin();

    $this->assertSame('test_plugin', self::$mvc->pluginName('http://foo.bar'));
    $this->assertFalse(self::$mvc->pluginName('http://test.baz'));
  }


  /** @test */
  public function it_returns_user_temp_path_if_user_id_provided_and_user_is_not_logged_in()
  {
    $this->assertSame(BBN_DATA_PATH . 'users/1/tmp/', Mvc::getUserTmpPath('1'));
    $this->assertSame(
            BBN_DATA_PATH . 'users/1/tmp/dummy_plugin/',
            Mvc::getUserTmpPath('1', 'dummy_plugin')
        );
  }


  /** @test */
  public function user_temp_path_returns_null_if_user_id_is_not_provided()
  {
    $this->assertNull(Mvc::getUserTmpPath());
    $this->assertNull(Mvc::getUserTmpPath(null, 'test_plugin'));
  }


  /** @test */
  public function it_returns_user_data_path_if_user_id_provided_and_user_is_not_logged_in()
  {
    $this->assertSame(BBN_DATA_PATH . 'users/1/data/', Mvc::getUserDataPath('1'));
    $this->assertSame(
            BBN_DATA_PATH . 'users/1/data/dummy_plugin/',
            Mvc::getUserDataPath('1', 'dummy_plugin')
        );
  }


  /** @test */
  public function user_data_path_returns_null_if_user_id_is_not_provided()
  {
    $this->assertNull(Mvc::getUserDataPath());
    $this->assertNull(Mvc::getUserDataPath(null, 'test_plugin'));
  }


  /** @test */
  public function include_model_method_returns_false_if_file_does_not_exist()
  {
    $this->assertFalse(Mvc::includeModel('dummy.php', self::$mvc));
  }


  /** @test */
  public function include_model_method_returns_false_if_file_exists_but_not_an_object_nor_array()
  {
    $file_stub = '<?php return 333;';

    $file_path = self::createFile('file.php', $file_stub, 'stubs');
    $this->assertFalse(Mvc::includeModel($file_path, self::$mvc));
    unlink($file_path);
  }


  /** @test */
  public function include_model_method_returns_an_array_if_file_exists_and_is_an_object()
  {
    $file_stub = '<?php
         namespace tests\storage\stubs;
         class stub {
            public $property = \'test\';
         }
         return new stub();
         ';

    $file_path = self::createFile('class.php', $file_stub, 'stubs');
    $this->assertSame(['property' => 'test'], Mvc::includeModel($file_path, self::$mvc));
    unlink($file_path);
  }


  /** @test */
  public function include_model_method_returns_an_array_if_file_exists_and_is_an_array()
  {
    $file_stub = '<?php
        return [\'key\' => \'value\'];
         ';

    $file_path = self::createFile('class.php', $file_stub, 'stubs');
    $this->assertSame(['key' => 'value'], Mvc::includeModel($file_path, self::$mvc));
    unlink($file_path);
  }


  /** @test */
  public function get_cookie_returns_the_cookie_if_exists()
  {
    $_COOKIE[BBN_APP_NAME] = json_encode(['value' => 'foo']);
    $this->assertSame('foo', self::$mvc->getCookie());
    unset($_COOKIE[BBN_APP_NAME]);
  }


  /** @test */
  public function get_cookie_returns_false_if_not_exists()
  {
    $this->assertFalse(self::$mvc->getCookie());
  }


  /** @test */
  public function it_adds_to_authorized_routes()
  {
    $result = self::$mvc->addAuthorizedRoute('route1', 'route2');

    $this->assertEquals(2, $result);
    $this->assertSame(
            ['route1', 'route2'],
            ReflectionHelpers::getNonPublicProperty('authorized_routes', self::$mvc)
        );
  }


  /** @test */
  public function it_adds_to_forbidden_routes()
  {
    $result = self::$mvc->addForbiddenRoute('route3', 'route4');

    $this->assertEquals(2, $result);
    $this->assertSame(
            ['route3', 'route4'],
            ReflectionHelpers::getNonPublicProperty('forbidden_routes', self::$mvc)
        );
  }


  /** @test */
  public function authorized_route_returns_true_if_the_route_exists_and_false_otherwise()
  {
    self::$mvc->addAuthorizedRoute('route5', 'route6');

    $this->assertTrue(self::$mvc->isAuthorizedRoute('route5'));
    $this->assertTrue(self::$mvc->isAuthorizedRoute('route6'));
    $this->assertFalse(self::$mvc->isAuthorizedRoute('route7'));
  }


  /** @test */
  public function it_returns_false_if_route_has_a_wild_card_and_belongs_to_forbidden_routes()
  {
    self::$mvc->addAuthorizedRoute('route8*');
    self::$mvc->addForbiddenRoute('route8');

    self::$mvc->addAuthorizedRoute('*');
    self::$mvc->addForbiddenRoute('route9');

    self::$mvc->addAuthorizedRoute('route10*');
    self::$mvc->addForbiddenRoute('route10*');

    $this->assertFalse(self::$mvc->isAuthorizedRoute('route8'));
    $this->assertFalse(self::$mvc->isAuthorizedRoute('route9'));
    $this->assertFalse(self::$mvc->isAuthorizedRoute('route10'));
  }


  /** @test */
  public function it_sets_the_root_and_get_root_returns_it()
  {
    self::$mvc->setRoot('root');
    $this->assertSame('root/', self::$mvc->getRoot());

    self::$mvc->setRoot('root/');
    $this->assertSame('root/', self::$mvc->getRoot());
  }


  /** @test */
  public function it_executes_a_php_view()
  {
    // TODO: the $bbn_inc_file parameter is never used in side the method!
    $result = Mvc::includePhpView('', '<?php echo $variable;', ['variable' => 'value']);

    $this->assertSame('value', $result);
  }


  /** @test */
  public function it_does_not_execute_a_php_view_and_returns_empty_string_if_content_is_empty()
  {
    $result = Mvc::includePhpView('', '', ['variable' => 'value']);

    $this->assertSame('', $result);
  }


  /** @test */
  public function it_sets_db_in_controller()
  {
    Mvc::setDbInController(true);
    $this->assertTrue(
            ReflectionHelpers::getNonPublicProperty('db_in_controller', self::$mvc)
        );

    Mvc::setDbInController(false);
    $this->assertFalse(
            ReflectionHelpers::getNonPublicProperty('db_in_controller', self::$mvc)
        );
  }


  /** @test */
  public function it_change_debug_state_and_returns_it_with_debug_method()
  {
    Mvc::debug(0);
    $this->assertFalse(Mvc::getDebug());

    Mvc::debug(1);
    $this->assertTrue(Mvc::getDebug());
  }


  /** @test
   * @throws ReflectionException
   */
  public function it_checks_whether_a_corresponding_file_has_been_found_or_not()
  {
    $router_mock = $this->mockClassMethod(Mvc\Router::class, 'route', ['key' => 'value']);
    $this->setNonPublicPropertyValue('router', $router_mock);

    // Call the route function again so that that it uses the mocked router
    ReflectionHelpers::getNonPublicMethod('route', self::$mvc)->invoke(self::$mvc);

    $this->assertTrue(self::$mvc->check());

    // Do it again but the value is null this time
    $this->resetMvcInstant();

    $router_mock = $this->mockClassMethod(Mvc\Router::class, 'route', null);
    $this->setNonPublicPropertyValue('router', $router_mock);

    ReflectionHelpers::getNonPublicMethod('route', self::$mvc)->invoke(self::$mvc);

    $this->assertFalse(self::$mvc->check());
  }


  /** @test */
  public function it_returns_the_file()
  {
    $router_mock = $this->mockClassMethod(Mvc\Router::class, 'route', ['file' => 'foo']);
    $this->setNonPublicPropertyValue('router', $router_mock);

    ReflectionHelpers::getNonPublicMethod('route', self::$mvc)->invoke(self::$mvc);

    $this->assertSame('foo', self::$mvc->getFile());
  }


  /** @test */
  public function it_returns_env_url_value()
  {
    // Mock the Environment class method
    $env_mock = $this->mockClassMethod(Mvc\Environment::class, 'getUrl', 'http://foo.bar');
    $this->setNonPublicPropertyValue('env', $env_mock);

    $this->assertSame('http://foo.bar', self::$mvc->getUrl());
  }


  /** @test */
  public function it_returns_env_request_value()
  {
    $env_mock = $this->mockClassMethod(Mvc\Environment::class, 'getRequest', 'POST');
    $this->setNonPublicPropertyValue('env', $env_mock);

    $this->assertSame('POST', self::$mvc->getRequest());
  }


  /** @test */
  public function it_returns_env_params_value()
  {
    $env_mock = $this->mockClassMethod(Mvc\Environment::class, 'getParams', ['foo' => 'bar']);
    $this->setNonPublicPropertyValue('env', $env_mock);

    $this->assertSame(['foo' => 'bar'], self::$mvc->getParams());
  }


  /** @test */
  public function it_returns_env_post_value()
  {
    $env_mock = $this->mockClassMethod(Mvc\Environment::class, 'getPost', ['foo' => 'bar']);
    $this->setNonPublicPropertyValue('env', $env_mock);

    $this->assertSame(['foo' => 'bar'], self::$mvc->getPost());
  }


  /** @test */
  public function it_returns_env_get_value()
  {
    $env_mock = $this->mockClassMethod(Mvc\Environment::class, 'getGet', ['foo' => 'bar']);
    $this->setNonPublicPropertyValue('env', $env_mock);

    $this->assertSame(['foo' => 'bar'], self::$mvc->getGet());
  }


  /** @test */
  public function it_returns_env_files_value()
  {
    $env_mock = $this->mockClassMethod(Mvc\Environment::class, 'getFiles', ['foo' => 'bar']);
    $this->setNonPublicPropertyValue('env', $env_mock);

    $this->assertSame(['foo' => 'bar'], self::$mvc->getFiles());
  }


  /** @test */
  public function it_returns_env_mode_value()
  {
    $env_mock = $this->mockClassMethod(Mvc\Environment::class, 'getMode', 'cli');
    $this->setNonPublicPropertyValue('env', $env_mock);

    $this->assertSame('cli', self::$mvc->getMode());
  }


  /** @test */
  public function it_sets_env_mode_value()
  {
    self::$mvc->setMode('public');
    $this->assertSame('public', self::$mvc->getMode());
  }


  /** @test */
  public function it_check_whether_is_called_cli_or_not()
  {
    $env_mock = $this->mockClassMethod(Mvc\Environment::class, 'isCli', true);
    $this->setNonPublicPropertyValue('env', $env_mock);

    $this->assertTrue(self::$mvc->isCli());
  }


  /** @test */
  public function it_should_reroute_and_arguments_added_to_info_property_if_provided()
  {
    $router_mock = $this->replaceRouterInstanceWithMockery();

    // Make sure that the `reset` method is called on the Router instance
    $router_mock->shouldReceive('reset')->andReturnSelf();

    // Make sure that the `route` method is called on the Router instance
    $router_mock->shouldReceive('route');

    $this->setNonPublicPropertyValue('router', $router_mock);

    self::$mvc->process();
    self::$mvc->reroute('foo/bar', false, ['arg' => 'arg_value']);

    $info = ReflectionHelpers::getNonPublicProperty('info', self::$mvc);

    $this->assertTrue(self::$mvc->check());
    $this->assertSame('foo/bar', self::$mvc->getUrl());
    $this->assertSame('foo/bar', self::$mvc->getRequest());
    $this->assertSame(['foo', 'bar', 'arg_value'], self::$mvc->getParams());
    $this->assertSame(
            [
        'args' => [
        'arg' => 'arg_value'
        ]
          ],
            $info
        );

    // Reroute again with arguments is false
    self::$mvc->reroute('foo/baz', false, false);

    $this->assertSame('foo/baz', self::$mvc->getUrl());
    $this->assertSame('foo/baz', self::$mvc->getRequest());
    $this->assertSame(['foo', 'baz'], self::$mvc->getParams());

    $info = ReflectionHelpers::getNonPublicProperty('info', self::$mvc);

    $this->assertSame(
            [
        'args' => false
          ],
            $info
        );
  }


  /** @test */
  public function it_will_throw_an_exception_when_reroute_and_controller_is_not_set()
  {
    $this->expectException(Exception::class);

    $router_mock = $this->replaceRouterInstanceWithMockery();

    $router_mock->shouldReceive('reset')->andReturnSelf();

    $router_mock->shouldReceive('route');

    $this->setNonPublicPropertyValue('router', $router_mock);

    self::$mvc->reroute('foo/bar');
  }


  /** @test */
  public function it_can_add_and_check_for_a_view()
  {
    $view_mock = Mockery::mock(Mvc\View::class);
    self::$mvc->addToViews('test_path', 'html', $view_mock);

    $this->assertTrue(self::$mvc->hasView('test_path', 'html'));
    $this->assertFalse(self::$mvc->hasView('dummy_path', 'html'));

    $this->assertTrue(self::$mvc->viewExists('test_path', 'html'));
    $this->assertFalse(self::$mvc->viewExists('dummy_path', 'html'));
  }


  /** @test */
  public function get_view_throws_an_exception_if_mode_no_found()
  {
    $this->expectException(Exception::class);

    self::$mvc->getView('test_path', 'unknown_mode');
  }


  /** @test */
  public function get_view_returns_content_when_a_view_exits()
  {
    $view_mock = Mockery::mock(Mvc\View::class);

    $view_mock->shouldReceive('check')->andReturnTrue();
    $view_mock->shouldReceive('get')->with(null)->andReturn('content');

    self::$mvc->addToViews('test_path2', 'html', $view_mock);
    $result = self::$mvc->getView('test_path2', 'html');

    $this->assertSame('content', $result);
  }


  /** @test */
  public function get_view_return_empty_string_when_a_view_does_not_exist()
  {
    $this->assertSame('', self::$mvc->getView('unknown_path', 'html'));
  }


  /** @test */
  public function it_checks_whether_a_view_exists_or_not()
  {
    $view_mock = Mockery::mock(Mvc\View::class);
    self::$mvc->addToViews('test_path3', 'html', $view_mock);

    $this->assertTrue(self::$mvc->viewExists('test_path3', 'html'));
    $this->assertFalse(self::$mvc->viewExists('dummy_path', 'html'));

    // This to ensure that when a view doesn't exist in the loaded views
    // It can return true if the `$this->router->route` returns true
    $this->replaceRouterInstanceWithMockery(['key' => 'value'], 'twice');
    $this->assertTrue(self::$mvc->viewExists('test_path4', 'html'));
  }


  /** @test */
  public function it_checks_if_a_model_exists_or_not()
  {
    $this->assertFalse(self::$mvc->modelExists('test_model'));

    $this->replaceRouterInstanceWithMockery(['key' => 'value'], 'twice');
    $this->assertTrue(self::$mvc->modelExists('test_model'));
  }


  /** @test */
  public function get_external_view_throws_exception_when_mode_not_found_and_path_is_parseable()
  {
    $this->expectException(Exception::class);

    self::$mvc->getExternalView('foo/bar', 'dummy_mode');
  }


  /** @test */
  public function get_external_view_returns_content_if_view_exists()
  {
    $view_mock = Mockery::mock(Mvc\View::class);
    $view_mock->shouldReceive('check')->andReturn(true);
    $view_mock->shouldReceive('get')->andReturn('view_content');

    self::$mvc->addToViews('foo/bar/test', 'html', $view_mock);
    $result = self::$mvc->getExternalView('foo/bar/test');

    $this->assertSame('view_content', $result);
  }


  /** @test */
  public function get_external_view_returns_empty_string_when_view_does_not_exist()
  {
    $this->assertSame('', self::$mvc->getExternalView('dummy_path'));
  }


  /** @test */
  public function it_returns_plugin_from_component()
  {
    $this->resetMvcInstant();
    $this->registerPlugin(
            $plugin = [
        'name' => 'plugin',
        'url'  => 'http://foo.bar',
        'path' => 'foo/bar/baz'
          ]
        );

    $this->assertSame($plugin, self::$mvc->getPluginFromComponent('plugin-'));
  }
}
