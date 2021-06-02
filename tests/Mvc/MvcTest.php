<?php

namespace tests\Mvc;

use bbn\Db;
use bbn\Mvc;
use Exception;
use Locale;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use tests\Files;
use tests\Reflectable;
use ReflectionClass;
use ReflectionException;
use stdClass;
use tests\Mockable;
use tests\ReflectionHelpers;
use tests\storage\stubs\stub2;

class MvcTest extends TestCase
{
  use Mockable, Reflectable, Files;

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
    $this->resetMvcInstance();
  }


  public static function setUpBeforeClass(): void
  {
    define('BBN_TEST_PATH', 'test_path/');
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
    // Convert the registerPlugin method to be accessible
    $method = ReflectionHelpers::getNonPublicMethod('registerPlugin', self::$mvc);

    // Invoke the registerPlugin method on the the mvc object using given parameters
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
   * Reset the MVC instance.
   *
   * @return void
   */
  protected function resetMvcInstance()
  {
    if (self::$mvc) {
      self::$mvc->destory();
      self::$mvc = null;
    }

    self::initMvc(...func_get_args());
  }


  /**
   * @return void
   */
  protected function constructorTest()
  {
    // Ensure plugin is registered
    $plugins = $this->getNonPublicProperty('plugins');

    $this->assertIsArray($plugins);
    $this->assertSame($plugins, self::$mvc->getPlugins());

    // Ensure paths are set
    $app_name   = $this->getNonPublicProperty('_app_name');
    $app_path   = $this->getNonPublicProperty('_app_path');
    $app_prefix = $this->getNonPublicProperty('_app_prefix');
    $cur_path   = $this->getNonPublicProperty('_cur_path');
    $lib_path   = $this->getNonPublicProperty('_lib_path');
    $data_path  = $this->getNonPublicProperty('_data_path');

    // The below constants are defined in phpunit.xml
    $this->assertSame(BBN_APP_NAME, $app_name);
    $this->assertSame(BBN_APP_PATH, $app_path);
    $this->assertSame(BBN_APP_PREFIX, $app_prefix);
    $this->assertSame('/', $cur_path);
    $this->assertSame(BBN_LIB_PATH, $lib_path);
    $this->assertSame(BBN_DATA_PATH, $data_path);

    $this->assertInstanceOf(
      Mvc\Router::class,
      $this->getNonPublicProperty('router')
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
   * @param mixed       $return_value
   * @param string|null $times
   * @return MockInterface
   * @throws ReflectionException
   */
  protected function replaceRouterInstanceWithMockery(
      $return_value = ['key' => 'value'],
      ?int $times = 1
  )
  {
    /*
      * MVC depends the Router class so we will mock it
      * And replace the `router` property in MVC with the mocked version
      * Then we set expectations that the `route` method should be called $times times
      * And return a specific value $return_value.
      *
     */
    $router_mock = $this->mockClassMethod(
      Mvc\Router::class,
      function ($mock) use ($return_value, $times) {
        $mock->shouldReceive('route')->andReturn($return_value)->times($times);
      }
    );

    // Set the router property to the mocked router class
    $this->setNonPublicPropertyValue('router', $router_mock);

    // Call the route function again so that that it uses the mocked router
    ReflectionHelpers::getNonPublicMethod('route', self::$mvc)->invoke(self::$mvc);

    return $router_mock;
  }


  /**
   * Mock the environment class and set method expectations then return the mock.
   *
   * @param string $method
   * @param $value
   * @return MockInterface
   */
  protected function mockEnvironmentClass(string $method, $value)
  {
    $env_mock = $this->mockClassMethod(Mvc\Environment::class, $method, $value);

    return $env_mock;
  }


  /** @test */
  public function constructor_test_when_db_is_null()
  {
    $this->constructorTest();
    $this->assertNull($this->getNonPublicProperty('db'));
  }


  /** @test */
  public function constructor_test_when_db_is_not_null()
  {
    $this->resetMvcInstance(new stdClass());
    $this->constructorTest();
    $this->assertNull($this->getNonPublicProperty('db'));

    // Here we will create a DB class stub
    $db_class_stub = '<?php
      namespace foo;
      class Db {
      
      }';

    // Then will create a PHP file with the stub content (DB class)
    // So that it can be used to initialize the MVC with.
    $file_path = self::createFile('Db.php', $db_class_stub, 'stubs');
    include $file_path;
    $this->resetMvcInstance(new \foo\Db());
    $this->constructorTest();
    $this->assertInstanceOf(
      \foo\Db::class,
      $this->getNonPublicProperty('db')
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
  public function mvc_instance_can_be_destroyed()
  {
    $reflectionClass = new ReflectionClass(self::$mvc);

    $singleton_instance = $reflectionClass->getProperty('singleton_instance');
    $singleton_instance->setAccessible(true);

    $singleton_exists = $reflectionClass->getProperty('singleton_exists');
    $singleton_exists->setAccessible(true);

    $env_property = $reflectionClass->getProperty('env');
    $env_property->setAccessible(true);

    $_app_name_property = $reflectionClass->getProperty('_app_name');
    $_app_name_property->setAccessible(true);

    self::$mvc->destory();

    $this->assertNull($singleton_instance->getValue(self::$mvc));
    $this->assertFalse($singleton_exists->getValue(self::$mvc));
    $this->assertNull(self::$mvc->getInstance());
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
    $this->registerPlugin();

    $plugins = $this->getNonPublicProperty('plugins');

    $this->assertIsArray($plugins);
    $this->assertSame($plugins, self::$mvc->getPlugins());
  }


  /** @test */
  public function it_checks_whether_or_not_it_has_a_plugin()
  {
    $this->registerPlugin();

    $this->assertTrue(self::$mvc->hasPlugin('test_plugin2'));
    $this->assertFalse(self::$mvc->hasPlugin('dummy_plugin'));

    $this->assertTrue(self::$mvc->isPlugin('test_plugin2'));
    $this->assertFalse(self::$mvc->isPlugin('dummy_plugin'));
  }


  /** @test */
  public function it_returns_plugin_url_if_exists_and_false_if_not()
  {
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
      $this->getNonPublicProperty('authorized_routes')
    );
  }


  /** @test */
  public function it_adds_to_forbidden_routes()
  {
    $result = self::$mvc->addForbiddenRoute('route3', 'route4');

    $this->assertEquals(2, $result);
    $this->assertSame(
      ['route3', 'route4'],
      $this->getNonPublicProperty('forbidden_routes')
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
      $this->getNonPublicProperty('db_in_controller')
    );

    Mvc::setDbInController(false);
    $this->assertFalse(
      $this->getNonPublicProperty('db_in_controller')
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
    // Mock the Router class and set expectations that the `route` method
    // Should be called once and return an array
    $router_mock = $this->mockClassMethod(Mvc\Router::class, 'route', ['key' => 'value']);

    // Then swap the `router` property in Mvc with the mocked version
    $this->setNonPublicPropertyValue('router', $router_mock);

    // Call the route function again so that it uses the mocked router
    ReflectionHelpers::getNonPublicMethod('route', self::$mvc)->invoke(self::$mvc);

    $this->assertTrue(self::$mvc->check());

    // Do it again but the value is null this time
    $this->resetMvcInstance();

    $router_mock = $this->mockClassMethod(Mvc\Router::class, 'route', null);
    $this->setNonPublicPropertyValue('router', $router_mock);

    ReflectionHelpers::getNonPublicMethod('route', self::$mvc)->invoke(self::$mvc);

    $this->assertFalse(self::$mvc->check());
  }


  /** @test */
  public function it_returns_the_file()
  {
    // Mock the Router class method and set expectations
    // That the `route` method should be called once and return an array
    $router_mock = $this->mockClassMethod(Mvc\Router::class, 'route', ['file' => 'foo']);

    // Then swap the `router` property in Mvc with the mocked version
    $this->setNonPublicPropertyValue('router', $router_mock);

    ReflectionHelpers::getNonPublicMethod('route', self::$mvc)->invoke(self::$mvc);

    $this->assertSame('foo', self::$mvc->getFile());
  }


  /** @test */
  public function it_returns_env_url_value()
  {
    // Mock the Environment class method and set expectations
    // That the `getUrl` method should be called once and return a string
    $env_mock = $this->mockEnvironmentClass('getUrl', 'http://foo.bar');

    // Then swap the `env` property in Mvc with the mocked version
    $this->setNonPublicPropertyValue('env', $env_mock);

    $this->assertSame('http://foo.bar', self::$mvc->getUrl());
  }


  /** @test */
  public function it_returns_env_request_value()
  {
    // Mock the Environment class method and set expectations
    // That the `getRequest` method should be called once and return a string
    $env_mock = $this->mockEnvironmentClass('getRequest', 'POST');

    // Then swap the `env` property in Mvc with the mocked version
    $this->setNonPublicPropertyValue('env', $env_mock);

    $this->assertSame('POST', self::$mvc->getRequest());
  }


  /** @test */
  public function it_returns_env_params_value()
  {
    // Mock the Environment class method and set expectations
    // That the `getParams` method should be called once and return an array
    $env_mock = $this->mockEnvironmentClass('getParams', ['foo' => 'bar']);

    // Then swap the `env` property in Mvc with the mocked version
    $this->setNonPublicPropertyValue('env', $env_mock);

    $this->assertSame(['foo' => 'bar'], self::$mvc->getParams());
  }


  /** @test */
  public function it_returns_env_post_value()
  {
    // Mock the Environment class method and set expectations
    // That the `getPost` method should be called once and return an array
    $env_mock = $this->mockEnvironmentClass('getPost', ['foo' => 'bar']);

    // Then swap the `env` property in Mvc with the mocked version
    $this->setNonPublicPropertyValue('env', $env_mock);

    $this->assertSame(['foo' => 'bar'], self::$mvc->getPost());
  }


  /** @test */
  public function it_returns_env_get_value()
  {
    // Mock the Environment class method and set expectations
    // That the `getGet` method should be called once and return an array
    $env_mock = $this->mockEnvironmentClass('getGet', ['foo' => 'bar']);

    // Then swap the `env` property in Mvc with the mocked version
    $this->setNonPublicPropertyValue('env', $env_mock);

    $this->assertSame(['foo' => 'bar'], self::$mvc->getGet());
  }


  /** @test */
  public function it_returns_env_files_value()
  {
    // Mock the Environment class method and set expectations
    // That the `getFiles` method should be called once and return an array
    $env_mock = $this->mockEnvironmentClass('getFiles', ['foo' => 'bar']);

    // Then swap the `env` property in Mvc with the mocked version
    $this->setNonPublicPropertyValue('env', $env_mock);

    $this->assertSame(['foo' => 'bar'], self::$mvc->getFiles());
  }


  /** @test */
  public function it_returns_env_mode_value()
  {
    // Mock the Environment class method and set expectations
    // That the `getFiles` method should be called once and return a string
    $env_mock = $this->mockEnvironmentClass('getMode', 'cli');

    // Then swap the `env` property in Mvc with the mocked version
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
    // Mock the Environment class method and set expectations
    // That the `isCli` method should be called once and return boolean
    $env_mock = $this->mockClassMethod(Mvc\Environment::class, 'isCli', true);

    // Then swap the `env` property in Mvc with the mocked version
    $this->setNonPublicPropertyValue('env', $env_mock);

    $this->assertTrue(self::$mvc->isCli());
  }


  /** @test */
  public function it_should_reroute_and_arguments_added_to_info_property_if_provided()
  {
    // Swap the `router` property in Mvc with a mocked version of Router
    $router_mock = $this->replaceRouterInstanceWithMockery();

    // Then make sure that the `reset` method is called on the Router instance
    $router_mock->shouldReceive('reset')->andReturnSelf();

    // Then make sure that the `route` method is called on the Router instance
    $router_mock->shouldReceive('route');

    // Swap the `router` property in Mvc with the modified mocked version of Router
    $this->setNonPublicPropertyValue('router', $router_mock);

    $controller_mock = Mockery::mock(Mvc\Controller::class);
    $controller_mock->shouldReceive('reset');

    // Swap the controller property with the mocked version
    $this->setNonPublicPropertyValue('controller', $controller_mock);

    self::$mvc->reroute('foo/bar', false, ['arg' => 'arg_value']);

    $info = $this->getNonPublicProperty('info');

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

    $info = $this->getNonPublicProperty('info');

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
    // Mock a View class
    $view_mock = Mockery::mock(Mvc\View::class);

    // Then add the mocked version to the list of views
    self::$mvc->addToViews('test_path', 'html', $view_mock);

    $this->assertTrue(self::$mvc->hasView('test_path', 'html'));
    $this->assertFalse(self::$mvc->hasView('dummy_path', 'html'));

    $this->assertTrue(self::$mvc->viewExists('test_path', 'html'));
    $this->assertFalse(self::$mvc->viewExists('dummy_path', 'html'));
  }


  /** @test */
  public function get_view_throws_an_exception_if_mode_not_found()
  {
    $this->expectException(Exception::class);

    self::$mvc->getView('test_path', 'unknown_mode');
  }


  /** @test */
  public function get_view_returns_content_when_a_view_exits()
  {
    // Mock the View class
    $view_mock = Mockery::mock(Mvc\View::class);

    // Then set expectations that the `check` method should be called once and return true
    $view_mock->shouldReceive('check')->andReturnTrue();

    // Set expectations that the `get` method should be called once
    // With null as arguments and return string
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
    $this->replaceRouterInstanceWithMockery(['key' => 'value'], 2);
    $this->assertTrue(self::$mvc->viewExists('test_path4', 'html'));
  }


  /** @test */
  public function it_checks_if_a_model_exists_or_not()
  {
    $this->assertFalse(self::$mvc->modelExists('test_model'));

    $this->replaceRouterInstanceWithMockery(['key' => 'value'], 2);
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
  public function getPluginFromComponent_method_returns_plugin_name_from_component_if_exists_and_null_otherwise()
  {
    $this->registerPlugin(
      $plugin = [
        'name' => 'appui',
        'url'  => 'http://foo.bar',
        'path' => 'foo/bar/baz/'
            ]
    );

    $this->assertSame($plugin, self::$mvc->getPluginFromComponent('appui-table'));
    $this->assertNull(self::$mvc->getPluginFromComponent('appui'));
    $this->assertNull(self::$mvc->getPluginFromComponent('unknown-plugin-'));
  }


  /** @test */
  public function routeComponent_method_returns_component_from_the_given_name_if_exists()
  {
    $data = [
      'js' => [
        'file'       => 'foo/bar/baz/src/components/form/form.js',
        'path'       => 'form',
        'plugin'     => 'http://foo.bar',
        'component'  => true,
        'ext'       => 'js',
        'mode'      => 'js',
        'i18n'      => 'foo/bar/baz/src/components/form/locale/en/en.json'
      ]
    ];

    // Mock the `routeComponent` method in the Router class to return the previous data.
    $router_mock = $this->replaceRouterInstanceWithMockery();
    $router_mock->shouldReceive('routeComponent')->with('appui2-form')->andReturn($data);

    $this->assertSame($data, self::$mvc->routeComponent('appui2-form'));
  }

  /** @test */
  public function routeComponent_method_returns_null_if_the_given_name_does_not_exist()
  {
    // Mock the `routeComponent` method in the Router class to return null.
    $router_mock = $this->replaceRouterInstanceWithMockery();
    $router_mock->shouldReceive('routeComponent')->andReturnNull();

    $this->assertNull(self::$mvc->routeComponent('appui2-plugin'));
  }


    /** @test */
    public function customPluginView_method_returns_content_if_custom_view_plugin_exists()
  {
    $router_mock = $this->replaceRouterInstanceWithMockery();
    $router_mock->shouldReceive('routeCustomPlugin')->andReturnNull();
    $this->assertNull(self::$mvc->customPluginView('path', 'js', [], 'plugin'));

    // getPluginView is an alias of customPluginView
    $this->assertNull(self::$mvc->getPluginView('path', 'js', [], 'plugin'));

    // The method cannot be tested when the plugin exists as it's creating a new concrete
    // View object inside which cannot be mocked.
  }

  /** @test */
  public function customPluginModel_method_returns_content_if_custom_plugin_model_exists()
  {
    $router_mock = $this->replaceRouterInstanceWithMockery();
    $router_mock->shouldReceive('routeCustomPlugin')->andReturnNull();
    $this->assertNull(
      self::$mvc->customPluginModel('path', [], new Mvc\Controller(self::$mvc, []), 'plugin')
    );

    // getPluginModel is an alias for customPluginModel
    $this->assertNull(
      self::$mvc->getPluginModel('path', [], new Mvc\Controller(self::$mvc, []), 'plugin')
    );

    /// The method cannot be tested when the plugin exists as it's creating a new concrete
    // Model object inside which cannot be mocked.
  }

  /** @test */
  public function hasSubpluginModel_method_checks_if_a_sub_plugin_model_exists()
  {
    $router_mock = $this->replaceRouterInstanceWithMockery();
    $router_mock->shouldReceive('routeSubplugin')->andReturn([
        'file'      => 'foo/bar/baz/src/appui-database/plugins/appui-dashboard/model/test.php',
        'path'      => 'poller',
        'plugin'    => 'appui-database',
        'ext'       => 'php',
        'mode'      => 'model',
        'i18n'      => 'foo/bar/baz/src/appui-database/locale/en/en.json'
      ]);

    $this->assertTrue(
      self::$mvc->hasSubpluginModel('poller', 'appui-database', 'appui-dashboard')
    );

    $this->resetMvcInstance();
    $router_mock = $this->replaceRouterInstanceWithMockery();
    $router_mock->shouldReceive('routeSubplugin')->andReturnNull();

    $this->assertFalse(
      self::$mvc->hasSubpluginModel('poller', 'appui-database', 'appui-dashboard')
    );
  }

  /** @test */
  public function subpluginModel_method_returns_a_sub_plugin_model_if_exists()
  {
    $router_mock = $this->replaceRouterInstanceWithMockery();
    $router_mock->shouldReceive('routeSubplugin')->andReturnNull();

    $this->assertNull(
      self::$mvc->subpluginModel(
        'poller',
        [],
        new Mvc\Controller(self::$mvc, []),
        'appui-database',
        'appui-dashboard'
      )
    );

    // getSubpluginModel is an alias for subpluginModel
    $this->assertNull(
      self::$mvc->getSubpluginModel(
        'poller',
        [],
        new Mvc\Controller(self::$mvc, []),
        'appui-database',
        'appui-dashboard'
      )
    );

    // The method cannot be tested when the sub-plugin exists as it's creating a new concrete
    // Model object inside which cannot be mocked.
  }

  /** @test */
  public function getModel_method_gets_the_model_if_exists_and_empty_array_otherwise()
  {
    $this->replaceRouterInstanceWithMockery(null, 2);

    $this->assertEmpty(self::$mvc->getModel('path', [], new Mvc\Controller(self::$mvc, [])));

    // The method cannot be tested when model exists as it's creating a new concrete
    // Model object inside which cannot be mocked.
  }

/** @test */
  public function getCachedModel_method_returns_the_model_in_cache_if_exists_or_save_it_otherwise()
  {
    $this->replaceRouterInstanceWithMockery(null, 2);

    $this->assertEmpty(self::$mvc->getCachedModel('path', [], new Mvc\Controller(self::$mvc, [])));

    // The method cannot be tested when model exists as it's creating a new concrete
    // Model object inside which cannot be mocked.
  }

  /** @test */
  public function addInc_method_adds_a_property_to_the_mvc_object_if_not_already_declared()
  {
    self::$mvc->addInc('foo', 'bar');

    $this->assertTrue(
      property_exists(self::$mvc->inc, 'foo')
    );

    $this->assertSame('bar', self::$mvc->inc->foo);
  }

  /** @test */
  public function addInc_method_does_not_add_a_property_to_the_mvc_if_already_declared()
  {
    self::$mvc->addInc('foo', 'bar');
    self::$mvc->addInc('foo', 'baz');

    $this->assertSame('bar', self::$mvc->inc->foo);
  }

  /** @test */
  public function process_method_returns_the_rendered_result_from_current_mvc_if_successful()
  {
    $this->replaceRouterInstanceWithMockery();

    $controller_mock = Mockery::mock(Mvc\Controller::class);
    $controller_mock->shouldReceive('process')->once()->andReturnSelf();

    // Swap the controller property with the mocked version
    $this->setNonPublicPropertyValue('controller', $controller_mock);

    self::$mvc->process();
    $this->assertIsObject(self::$mvc->obj);
    $this->assertSame('stdClass', get_class(self::$mvc->obj));
  }

  /** @test */
  public function process_method_throws_an_exception_if_info_is_not_array()
  {
    $this->expectException(\Exception::class);

    $this->setNonPublicPropertyValue('info', 'foo');
    self::$mvc->process();
  }

  /** @test */
  public function hasContent_method_returns_true_if_the_registered_controller_has_content()
  {
    $this->replaceRouterInstanceWithMockery();

    $controller_mock = Mockery::mock(Mvc\Controller::class);
    $controller_mock->shouldReceive('hasContent')->once()->andReturnTrue();

    // Swap the controller property with the mocked version
    $this->setNonPublicPropertyValue('controller', $controller_mock);

    $this->assertTrue(self::$mvc->hasContent());
  }

  /** @test */
  public function hasContent_method_returns_false_if_the_registered_controller_has_no_content()
  {
    $this->replaceRouterInstanceWithMockery();

    $controller_mock = Mockery::mock(Mvc\Controller::class);
    $controller_mock->shouldReceive('hasContent')->once()->andReturnFalse();

    // Swap the controller property with the mocked version
    $this->setNonPublicPropertyValue('controller', $controller_mock);

    $this->assertFalse(self::$mvc->hasContent());
  }

  /** @test */
  public function transform_method_test()
  {
    $this->replaceRouterInstanceWithMockery();

    $controller_mock = Mockery::mock(Mvc\Controller::class);
    $controller_mock->shouldReceive('transform')->once()->andReturnFalse();

    // Swap the controller property with the mocked version
    $this->setNonPublicPropertyValue('controller', $controller_mock);

    self::$mvc->transform(function (){});

    // The method returns void but we needed to set expectation that the
    // `transform` method will be called on the Controller object
    $this->assertTrue(true);
  }

/** @test */
  public function output_method_outputs_controller_instance_object()
  {
    // The method cannot be tested when it's successful as it's creating a new concrete
    // Output object inside which cannot be mocked.
    $this->assertTrue(true);
  }

  /** @test */
  public function output_method_throws_an_exception_if_object_property_is_not_an_object()
  {
    $this->expectException(\Exception::class);

    $this->replaceRouterInstanceWithMockery();

    $controller_mock = $this->mockClassMethod(Mvc\Controller::class, function ($mock) {
      $mock->shouldReceive('get')->once()->andReturn('output');
    });

    // Swap the controller property with the mocked version
    $this->setNonPublicPropertyValue('controller', $controller_mock);

    // Mock the `isCli` method on the Environment instance
    // So that it returns false to avoid exit() since testing is using CLI
    $env_mock = $this->mockEnvironmentClass('isCli', false);
    $this->setNonPublicPropertyValue('env', $env_mock);

    self::$mvc->output();
  }

  /** @test */
  public function getDb_method_returns_db_if_exists()
  {
    Mvc::setDbInController(true);

    // Mock the Db class
    $db_mock = Mockery::mock(Db::class);

    // Swap the `db` property with the mocked version
    ReflectionHelpers::setNonPublicPropertyValue('db', self::$mvc, $db_mock);

    $this->assertNotNull(self::$mvc->getDb());
    $this->assertSame($db_mock, self::$mvc->getDb());
  }

  /** @test */
  public function getDb_method_returns_null_if_db_is_null()
  {
    Mvc::setDbInController(true);

    $this->assertNull(self::$mvc->getDb());
  }

  /** @test */
  public function getDb_method_returns_null_if_db_in_controller_is_false()
  {
    Mvc::setDbInController(false);

    // Mock the Db class
    $db_mock = Mockery::mock(Db::class);

    // Swap the `db` property with the mocked version
    ReflectionHelpers::setNonPublicPropertyValue('db', self::$mvc, $db_mock);

    $this->assertNull(self::$mvc->getDb());
  }

  /** @test */
  public function setPrepath_method_return_true_if_not_exists_and_can_be_set()
  {
    $router_mock = $this->replaceRouterInstanceWithMockery();
    $router_mock->shouldReceive('getPrepath')->once()->andReturn('foo');
    $router_mock->shouldReceive('setPrepath')->once()->andReturnTrue();

    $env_mock = $this->mockEnvironmentClass('setPrepath', true);
    $env_mock->shouldReceive('getParams')->once()->andReturn([
      'foobar' => 'baz'
    ]);
    $this->setNonPublicPropertyValue('env', $env_mock);

    $this->assertSame(1, self::$mvc->setPrepath('bar'));
    $this->assertSame(['foobar' => 'baz'], self::$mvc->params);
  }

  /** @test */
  public function setPrepath_method_return_true_if_exists()
  {
    $router_mock = $this->replaceRouterInstanceWithMockery();
    $router_mock->shouldReceive('getPrepath')->once()->andReturn('foo');

    $this->assertSame(1, self::$mvc->setPrepath('foo'));
    $this->assertTrue(!isset(self::$mvc->params));
  }

  /** @test */
  public function setPrepath_method_throws_an_exception_if_not_exists_and_env_setPrepath_returns_false()
  {
    $this->expectException(\Exception::class);

    $router_mock = $this->replaceRouterInstanceWithMockery();
    $router_mock->shouldReceive('getPrepath')->once()->andReturn('foo');

    $env_mock = $this->mockEnvironmentClass('setPrepath', false);

    $this->setNonPublicPropertyValue('env', $env_mock);

    self::$mvc->setPrepath('bar');
  }

  /** @test */
  public function setPrepath_method_throws_an_exception_if_not_exists_and_router_setPrepath_returns_false()
  {
    $this->expectException(\Exception::class);

    $router_mock = $this->replaceRouterInstanceWithMockery();
    $router_mock->shouldReceive('getPrepath')->once()->andReturn('foo');
    $router_mock->shouldReceive('setPrepath')->once()->andReturnTrue();

    $env_mock = $this->mockEnvironmentClass('setPrepath', true);

    $this->setNonPublicPropertyValue('env', $env_mock);

    self::$mvc->setPrepath('bar');
  }

  /** @test */
  public function setPrepath_method_throws_an_exception_check_method_fails()
  {
    $this->setNonPublicPropertyValue('info', null);
    $this->expectException(\Exception::class);

    self::$mvc->setPrepath('bar');
  }

  /** @test */
  public function getPrepath_method_returns_prepath()
  {
    $router_mock = $this->replaceRouterInstanceWithMockery();
    $router_mock->shouldReceive('getPrepath')->once()->andReturn('foo');

    $this->assertSame('foo', self::$mvc->getPrepath());
  }

  /** @test */
  public function getPrepath_method_returns_empty_string_when_check_method_fails()
  {
    $this->setNonPublicPropertyValue('info', null);

    $this->assertSame('', self::$mvc->getPrepath());
  }

  /** @test */
  public function getRoutes_method_returns_routes_if_exist()
  {
    $router_mock = $this->replaceRouterInstanceWithMockery();
    $router_mock->shouldReceive('getRoutes')->once()->andReturn(['foo' => 'bar']);

    $this->assertSame('bar', self::$mvc->getRoutes('foo'));
  }

  /** @test */
  public function getRoutes_method_returns_false_routes_does_not_exist()
  {
    $router_mock = $this->replaceRouterInstanceWithMockery();
    $router_mock->shouldReceive('getRoutes')->once()->andReturn(['foo' => 'bar']);

    $this->assertFalse(self::$mvc->getRoutes('baz'));
  }

  /** @test */
  public function getRoutes_method_returns_false_when_check_method_fails()
  {
    $this->setNonPublicPropertyValue('info', null);

    $this->assertFalse(self::$mvc->getRoutes('baz'));
  }

  public function getInstance()
  {
    return self::$mvc;
  }
}
