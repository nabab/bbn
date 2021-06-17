<?php

namespace Mvc;

use bbn\Mvc;
use bbn\Mvc\Controller;
use Mockery;
use PHPUnit\Framework\TestCase;
use tests\Files;
use tests\Mockable;
use tests\Reflectable;

class ControllerTest extends TestCase
{

  use Mockable, Reflectable, Files;

  protected Controller $controller;

  protected $mvc_mock;

  protected $info = [
    'mode'      => 'js',
    'path'      => 'path/to/plugin',
    'file'      => './tests/storage/controllers/home.php',
    'request'   => 'get',
    'root'      => './tests/',
    'plugin'    => 'plugin',
    'args'      => [
      'foo' => 'bar'
    ],
    'checkers'  => []
  ];

  protected $data = [
    'controller_data' => [
      'variable_1' => [
        'key_1' => 'value_1',
        'key_2' => 'value_2',
      ],
      'variable_2' => [
        'key_3' => 'value_3',
        'key_4' => 'value_4',
      ],
    ],
    'post'    => ['post_key' => 'post_value'],
    'get'     => ['get_key' => 'get_value'],
    'files'   => ['file_key' => 'file_value'],
    'params'  => ['param_key' => 'param_value'],
    'url'     => 'url/'

  ];


  public function getInstance()
  {
    return $this->controller;
  }


  protected function setUp(): void
  {
    $this->mvc_mock = \Mockery::mock(Mvc::class);
    $this->setMvcMockExpectations();
    $this->init($this->info, $this->data['controller_data']);
  }


  protected function tearDown(): void
  {
    Mockery::close();
  }


  protected function init()
  {
    $this->controller = new Controller($this->mvc_mock, ...func_get_args());
  }


  protected function setMvcMockExpectations()
  {
    $this->mvc_mock->shouldReceive('getDb')->andReturnNull();
    $this->mvc_mock->shouldReceive('getPost')->andReturn($this->data['post']);
    $this->mvc_mock->shouldReceive('getGet')->andReturn($this->data['get']);
    $this->mvc_mock->shouldReceive('getFiles')->andReturn($this->data['files']);
    $this->mvc_mock->shouldReceive('getParams')->once()->andReturn($this->data['params']);
    $this->mvc_mock->shouldReceive('getUrl')->andReturn($this->data['url']);
  }


  /** @test */
  public function constructor_test_when_info_and_data_params_are_provided()
  {
    $this->assertInstanceOf(
      Mvc::class,
      $this->getNonPublicProperty('_mvc')
    );

    $this->assertSame($this->info['path'], $this->getNonPublicProperty('_path'));
    $this->assertSame($this->info['plugin'], $this->getNonPublicProperty('_plugin'));
    $this->assertSame($this->info['request'], $this->getNonPublicProperty('_request'));
    $this->assertSame($this->info['file'], $this->getNonPublicProperty('_file'));
    $this->assertSame($this->info['root'], $this->getNonPublicProperty('_root'));
    $this->assertSame($this->info['checkers'], $this->getNonPublicProperty('_checkers'));

    $this->assertSame($this->info['args'], $this->controller->arguments);
    $this->assertSame($this->info['mode'], $this->controller->mode);
    $this->assertSame($this->data['controller_data'], $this->controller->data);
    $this->assertNull($this->controller->db);
    $this->assertSame($this->mvc_mock->inc, $this->controller->inc);
    $this->assertSame($this->data['post'], $this->controller->post);
    $this->assertSame($this->data['get'], $this->controller->get);
    $this->assertSame($this->data['files'], $this->controller->files);
    $this->assertSame($this->data['params'], $this->controller->params);
    $this->assertSame($this->data['url'], $this->controller->url);
    $this->assertInstanceOf(\stdClass::class, $this->controller->obj);
  }


  /** @test */
  public function constructor_test_when_info_param_is_empty()
  {
    $this->init([], $this->data['controller_data']);

    $this->assertInstanceOf(
      Mvc::class,
      $this->getNonPublicProperty('_mvc')
    );

    $this->assertNull($this->getNonPublicProperty('_path'));
    $this->assertNull($this->getNonPublicProperty('_plugin'));
    $this->assertNull($this->getNonPublicProperty('_request'));
    $this->assertNull($this->getNonPublicProperty('_file'));
    $this->assertNull($this->getNonPublicProperty('_root'));
    $this->assertEmpty($this->getNonPublicProperty('_checkers'));

    $this->assertEmpty($this->controller->arguments);
    $this->assertNull($this->controller->mode);
    $this->assertEmpty($this->controller->data);
    $this->assertNull($this->controller->db);
    $this->assertNull($this->controller->inc);
    $this->assertEmpty($this->controller->post);
    $this->assertEmpty($this->controller->get);
    $this->assertEmpty($this->controller->files);
    $this->assertEmpty($this->controller->params);
    $this->assertTrue(!isset($this->controller->url));
    $this->assertNull($this->controller->obj);
  }


  /** @test */
  public function constructor_test_when_data_param_is_false()
  {
    $this->setMvcMockExpectations();
    $this->init($this->info, false);

    $this->assertEmpty($this->controller->data);
  }


  /** @test */
  public function addAuthorizedRoute_method_adds_to_authorized_methods()
  {
    $this->mvc_mock->shouldReceive('addAuthorizedRoute')->with('route_1')->once()->andReturn(1);

    $this->assertSame(1, $this->controller->addAuthorizedRoute('route_1'));
  }


  /** @test */
  public function isAuthorizedRoute_method_checks_if_a_route_is_authorized()
  {
    $this->mvc_mock->shouldReceive('isAuthorizedRoute')->with('route_2')->once()->andReturnTrue();

    $this->assertTrue($this->controller->isAuthorizedRoute('route_2'));
  }


  /** @test */
  public function getRoot_method_returns_the_root_of_the_application_in_the_base_url()
  {
    $this->mvc_mock->shouldReceive('getRoot')->once()->andReturn('root/');

    $this->assertSame('root/', $this->controller->getRoot());
  }


  /** @test */
  public function setRoot_method_sets_the_root_of_the_application()
  {
    $this->mvc_mock->shouldReceive('setRoot')->with('root2/')->once()->andReturnSelf();

    $this->assertInstanceOf(Controller::class, $this->controller->setRoot('root2/'));
  }


  /** @test */
  public function getUrl_method_returns_the_request_url()
  {
    // Expectation was set in the init function that execute before every test
    $this->assertSame($this->data['url'], $this->controller->getUrl());
  }


  /** @test */
  public function getPath_method_returns_the_internal_path_of_the_controller()
  {
    // Expectation was set in the init function that execute before every test
    $this->assertSame($this->info['path'], $this->controller->getPath());
  }


  /** @test */
  public function getRequest_method_returns_the_current_controller_route()
  {
    $this->assertSame($this->info['request'], $this->controller->getRequest());
  }


  /** @test */
  public function exists_method_returns_true_if_the_internal_path_of_the_controller_exists()
  {
    $this->assertTrue($this->controller->exists());
  }


  /** @test */
  public function exists_method_returns_false_if_the_internal_path_of_the_controller_does_not_exist()
  {
    $this->setNonPublicPropertyValue('_path', '');
    $this->assertFalse($this->controller->exists());
  }


  /** @test */
  public function getCurrentDir_method_returns_the_current_controller_dir_name_if_path_exists_and_is_the_parent_dir()
  {
    // When dirname of the _$path property  is '.'
    $this->setNonPublicPropertyValue('_path', 'form');

    $this->assertSame('', $this->controller->getCurrentDir());
  }


  /** @test */
  public function getCurrentDir_method_returns_the_current_controller_dir_name_if_path_exists_and_is_not_the_parent_dir_with_a_prepath_removed()
  {
    $this->setNonPublicPropertyValue('_path', 'prepath/parent/form');

    // In this case it depends on Mvc::getPrepath() so let's mock the method .
    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('prepath/');

    $this->assertSame('parent', $this->controller->getCurrentDir());
  }


  /** @test */
  public function getCurrentDir_method_returns_the_current_controller_dir_name_if_path_exists_and_is_not_the_parent_dir_and_no_prepath_removed()
  {
    $this->setNonPublicPropertyValue('_path', 'parent/form');

    // In this case it depends on Mvc::getPrepath() so let's mock the method .
    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('');

    $this->assertSame('parent', $this->controller->getCurrentDir());
  }


  /** @test */
  public function getCurrentDir_method_returns_null_if_path_does_not_exists()
  {
    $this->setNonPublicPropertyValue('_path', '');
    $this->assertNull($this->controller->getCurrentDir());
  }


  /** @test */
  public function getLocalPath_method_returns_the_current_controller_path_with_a_prepath_removed()
  {
    $this->setNonPublicPropertyValue('_path', 'prepath/parent/form');

    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('prepath/');

    $this->assertSame('parent/form', $this->controller->getLocalPath());
  }


  /** @test */
  public function getLocalPath_method_returns_the_current_controller_path_with_no_prepath_removed()
  {
    $this->setNonPublicPropertyValue('_path', 'parent/form');

    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('');

    $this->assertSame('parent/form', $this->controller->getLocalPath());
  }


  /** @test */
  public function getLocalRoute_method_returns_the_current_controller_route_with_prepath_removed()
  {
    $this->setNonPublicPropertyValue('_request', 'prepath/parent/form');

    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('prepath/');

    $this->assertSame('parent/form', $this->controller->getLocalRoute());
  }


  /** @test */
  public function getLocalRoute_method_returns_the_current_controller_route_with_no_prepath_removed()
  {
    $this->setNonPublicPropertyValue('_request', 'parent/form');

    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('');

    $this->assertSame('parent/form', $this->controller->getLocalRoute());
  }


    /** @test */
  public function getAll_method_returns_info_of_the_controller()
  {
    // Let's mock the methods `getPrepath` & `getRoot`
    // Since they depends on other classes and will be used in the `getAll` method
    $this->mvc_mock->shouldReceive('getPrepath')->times(3)->andReturn('');
    $this->mvc_mock->shouldReceive('getRoot')->once()->andReturn($this->info['root']);

    $result = [
      'controller'  => $this->info['file'],
      'dir'         => dirname($this->info['path']),
      'local_path'  => $this->info['path'],
      'local_route' => $this->info['request'],
      'path'        => $this->info['path'],
      'root'        => $this->info['root'],
      'request'     => $this->info['request'],
      'checkers'    => $this->info['checkers']
    ];

    $this->assertSame($result, $this->controller->getAll());
  }


  /** @test */
  public function sayRoot_method_returns_the_current_controller_root_dir()
  {
    $this->assertSame($this->info['root'], $this->controller->sayRoot());
  }


  /** @test  */
  public function getController_method_returns_current_controller_file_name()
  {
    $this->assertSame($this->info['file'], $this->controller->getController());
  }


  /** @test */
  public function getPlugin_method_returns()
  {
    $this->assertSame($this->info['plugin'], $this->controller->getPlugin());
  }


  /** @test */
  public function render_method_renders_content_using_Tpl_class_if_model_data_not_empty()
  {
    // Cannot test it in this case since it depend on `bbn\Tpl`
    // And it uses it directly in the class and it's a static method
    // So it cannot be mocked
    $this->assertTrue(true);
  }


  /** @test */
  public function render_method_return_the_view_directly_if_model_data_id_empty_and_data_property_is_empty_too()
  {
    $this->controller->data = [];
    $this->assertSame('view', $this->controller->render('view'));
  }


  /** @test */
  public function isCli_method_checks_if_the_request_is_called_from_cli_or_not()
  {
    $this->mvc_mock->shouldReceive('isCli')->once()->andReturnTrue();

    $this->assertTrue($this->controller->isCli());
  }


  /** @test */
  public function reroute_method_reroutes_a_controller_to_another_one_if_it_not_has_been_rerouted_before()
  {
    $this->mvc_mock->shouldReceive('reroute')->twice();
    $this->controller->reroute('new/path');

    $this->assertSame(['new/path'], $this->getNonPublicProperty('_reroutes'));
    $this->assertSame(1, $this->getNonPublicProperty('_is_rerouted'));

    // Reroute the same path again
    $this->controller->reroute('new/path');

    // Should be the same
    $this->assertSame(['new/path'], $this->getNonPublicProperty('_reroutes'));
    $this->assertSame(1, $this->getNonPublicProperty('_is_rerouted'));

    // Add a new different route
    $this->controller->reroute('new/path2');

    // Should be added to the list
    $this->assertSame(['new/path', 'new/path2'], $this->getNonPublicProperty('_reroutes'));
    $this->assertSame(1, $this->getNonPublicProperty('_is_rerouted'));

    // If the provided path is equals to the _path property then don't add it
    $this->controller->reroute($this->info['path']);

    // Should be the same
    $this->assertSame(['new/path', 'new/path2'], $this->getNonPublicProperty('_reroutes'));
    $this->assertSame(1, $this->getNonPublicProperty('_is_rerouted'));
  }


  /** @test */
  public function incl_method_includes_a_php_file_within_the_controller_path()
  {
    $file_stub = '<?php 
  namespace foo;
  class test {}
';
    $file_path = self::createFile('test.php',  $file_stub, 'controllers');

    // With .php file path
    $result = $this->controller->incl('test.php');
    $this->assertTrue(class_exists('\foo\test'));
    $this->assertInstanceOf(Controller::class, $result);
    unlink($file_path);

    $file_stub = '<?php 
  namespace foo2;
  class test2 {}
';
    $file_path = self::createFile('test2.php',  $file_stub, 'controllers');

    // Without .php file path
    $result = $this->controller->incl('test2');
    $this->assertTrue(class_exists('\foo2\test2'));
    $this->assertInstanceOf(Controller::class, $result);
    unlink($file_path);
  }


  /** @test */
  public function addScript_method_adds_the_given_string_to_script_property()
  {
    $result = $this->controller->addScript($script = '<script>let test = "test"</script>');

    $this->assertTrue(isset($this->controller->obj->script));
    $this->assertSame($script, $this->controller->obj->script);
    $this->assertInstanceOf(Controller::class, $result);

    $result = $this->controller->addScript($script2 = '<script>let test2 = "test2"</script>');

    $this->assertSame($script . $script2, $this->controller->obj->script);
    $this->assertInstanceOf(Controller::class, $result);
  }


  /** @test */
  public function registerPluginClasses_method_register_a_class_using_spl_autoload()
  {
    // Will create php files with classes defined to test files inclusion
    $file_stub = "<?php
    namespace foo3;
    class test3 {}
    ";

    $file_stub2 = "<?php
    namespace foo4;
    class test4 {}
    ";

    $this->createDir('plugin_path/lib/foo3');
    $this->createDir('plugin_path/lib/foo4');

    $file_path  = $this->createFile('test3.php', $file_stub, 'plugin_path/lib/foo3');
    $file_path2 = $this->createFile('test4.php', $file_stub2, 'plugin_path/lib/foo4');

    $result = $this->controller->registerPluginClasses(BBN_APP_PATH . BBN_DATA_PATH . 'plugin_path/');

    $this->assertTrue(class_exists('\foo3\test3'));
    $this->assertTrue(class_exists('\foo4\test4'));
    $this->assertInstanceOf(Controller::class, $result);

    unlink($file_path);
    unlink($file_path2);
  }


  /** @test */
  public function control_method_encloses_controller_inclusion()
  {
    // Will create a plugin file with a class defined to test plugin class is registered
    $file_stub = "<?php
    namespace foo5;
    class test5 {}
    ";

    $this->createDir('plugin_path/lib/foo5');
    $file_path = $this->createFile('test5.php', $file_stub, 'plugin_path/lib/foo5');

    // Here will create a controller file to test it's inclusion
    $file2_stub = "<?php
    namespace foo6;
    class controllerTest {}
    ";

    $ctrl_path = self::createFile(basename("{$this->info['file']}"),  $file2_stub, 'controllers');

    $this->mvc_mock->shouldReceive('pluginName')->with($this->info['plugin'])->andReturn('plugin_name');
    $this->mvc_mock->shouldReceive('pluginPath')->with('plugin_name', false)->andReturn(BBN_APP_PATH . BBN_DATA_PATH . 'plugin_path/');
    $this->mvc_mock->shouldReceive('isCli')->andReturnFalse();

    $method = $this->getNonPublicMethod('control', $this->controller);
    $method->invoke($this->controller);

    $this->assertTrue(class_exists('\foo5\test5'));
    $this->assertTrue(class_exists('\foo6\controllerTest'));

    unlink($file_path);
    unlink($ctrl_path);
  }


  /** @test */
  public function control_method_returns_false_when_a_checker_file_returns_false()
  {
    $this->mvc_mock->shouldReceive('pluginName')->with($this->info['plugin'])->andReturn('plugin_name');
    $this->mvc_mock->shouldReceive('pluginPath')->with('plugin_name', false)->andReturn(BBN_APP_PATH . BBN_DATA_PATH . 'plugin_path/');
    $this->mvc_mock->shouldReceive('isCli')->andReturnFalse();

    $method = $this->getNonPublicMethod('control', $this->controller);

    // set file checker
    $this->setNonPublicPropertyValue('_checkers', ['file_not_exist.php']);

    // Here no php file will be created so that the include $bbn_inc_file returns false
    // And will silent the method call because when include retunrs false it issue a E_WARNING message
    $result = @$method->invoke($this->controller);

    $this->assertFalse($result);
  }


  /** @test */
  public function control_method_does_not_include_when_its_been_already_controlled_and_the_file_property_is_truthy()
  {
    $this->setNonPublicPropertyValue('_is_controlled', 1);
    $this->setNonPublicPropertyValue('_file', 'path/to/file');

    $method = $this->getNonPublicMethod('control', $this->controller);
    $result = $method->invoke($this->controller);

    $this->assertTrue(!isset($this->obj->content));
    $this->assertTrue($result);
  }


  /** @test */
  public function process_method_launches_the_controller()
  {
    // The process method calls the control method
    // So let's mock the Mvc calls inside it like we did in the previous two tests.
    $this->mvc_mock->shouldReceive('pluginName')->with($this->info['plugin'])->once()->andReturn('plugin_name');
    $this->mvc_mock->shouldReceive('pluginPath')->with('plugin_name', false)->once()->andReturn(BBN_APP_PATH . BBN_DATA_PATH . 'plugin_path/');
    $this->mvc_mock->shouldReceive('isCli')->once()->andReturnFalse();

    // Mock the router class so that the getLocaleDomain return a string that we can test.
    $router_mock = $this->mockClassMethod(
      Mvc\Router::class, function ($mock) {
        $mock->shouldReceive('getLocaleDomain')->once()->andReturn('custom');
      }
    );

    $this->setNonPublicPropertyValue('retriever_instance', $router_mock, Mvc\Router::class);

    $result = @$this->controller->process();

    $this->assertInstanceOf(Controller::class, $result);
  }


  /** @test */
  public function hasBeenRerouted_method_checks_if_controller_has_been_rerouted()
  {
    $this->assertFalse($this->controller->hasBeenRerouted());

    $this->setNonPublicPropertyValue('_is_rerouted', 1);

    $this->assertTrue($this->controller->hasBeenRerouted());
  }


  /** @test */
  public function getJs_method_gets_a_js_view_from_a_path_to_file_encapsulated_in_an_anonymous_function()
  {
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->andReturn('let string = "foobar";')
      ->with('view', 'js', $data = ['data_1' => 'value_1', 'data_2' => 'value_2']);

    $expected = '<script>(function(){
let data = {
    "data_1": "value_1",
    "data_2": "value_2"
};let string = "foobar";
})();</script>';

    $result = $this->controller->getJs('view', $data);
    $this->assertSame($expected, $result);

    // Test it again with no path provided this time
    // Notice that the Mvc::getView will be called with first parameter to the _path property
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->andReturn('let string = "foobar";')
      ->with($this->info['path'], 'js', $data);

    $result2 = $this->controller->getJs($data);
    $this->assertSame($expected, $result2);

  }


  /** @test */
  public function getJs_method_gets_a_js_view_from_a_path_to_file_not_encapsulated_in_an_anonymous_function()
  {
    // Notice the Mvc::getView should be called with the third param default to the data property
    // Since we set the data to null in Controller::getJs()
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->andReturn('let string = "foobar";')
      ->with('view', 'js', $this->data['controller_data']);

    $result = $this->controller->getJs('view', null,false);

    $expected = '<script>let string = "foobar";</script>';

    $this->assertSame($expected, $result);
  }


  /** @test */
  public function getJs_method_returns_false_when_mvc_getView_returns_empty_string()
  {
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->andReturn('')
      ->with('view', 'js', $data = ['data_1' => 'value_1', 'data_2' => 'value_2']);

    $result = $this->controller->getJs('view', $data);

    $this->assertFalse($result);
  }


  /** @test */
  public function getJsGroup_method_gets_a_js_view_from_a_path_to_dir_encapsulated_in_an_anonymous_function()
  {
    $this->mvc_mock->shouldReceive('fetchDir')->once()->andReturn(['path/to/file']);
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->andReturn('let string = "foobar";')
      ->with($path = 'path/to/file', 'js', $data = ['data_1' => 'value_1', 'data_2' => 'value_2']);

    $result = $this->controller->getJsGroup($path, $data, true);

    $expected = '<script>(function($){
let data = {
    "data_1": "value_1",
    "data_2": "value_2"
};let string = "foobar";
})();</script>';

    $this->assertSame($expected, $result);
  }


  /** @test */
  public function getJsGroup_method_gets_a_js_view_from_an_array_of_files_not_encapsulated_in_an_anonymous_function()
  {
    // Here the Mvc::fetchDir is not called like it did in the previous test since it's an array of files.
    // And the Mvc::getView will be called twice in the array loop as array count is two.
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->andReturn('let string = "foobar";')
      ->with($file1 = 'file1', 'js', $data = ['data_1' => 'value_1', 'data_2' => 'value_2']);

    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->andReturn('let string2 = "foobar2";')
      ->with($file2 = 'file2', 'js', $data = ['data_1' => 'value_1', 'data_2' => 'value_2']);

    $result = $this->controller->getJsGroup([$file1, $file2], $data, true);

    $expected = '<script>(function($){
let data = {
    "data_1": "value_1",
    "data_2": "value_2"
};let string = "foobar";let string2 = "foobar2";
})();</script>';

    $this->assertSame($expected, $result);
  }


  /** @test */
  public function getJsGroup_method_throws_an_exception_when_it_fails_to_fetch_files_from_a_dir()
  {
    $this->expectException(\Exception::class);
    $this->mvc_mock->shouldReceive('fetchDir')
      ->once()
      ->with($path = 'path/to/dir/that/not/exist', 'js')
      ->andReturnNull();

    $this->controller->getJsGroup($path);
  }


  /** @test */
  public function getJsGroup_method_throws_an_exception_when_the_dir_is_empty()
  {
    $this->expectException(\Exception::class);
    $this->mvc_mock->shouldReceive('fetchDir')
      ->once()
      ->andReturn([])
      ->with($path = 'path/to/empty/dir', 'js');

    $this->controller->getJsGroup($path);
  }

  /** @test */
  public function getViewGroup_method_returns_a_view_from_a_dir_path()
  {
    $this->mvc_mock->shouldReceive('fetchDir')
      ->andReturn(['file1', 'file2', 'file3'])
      ->with($path = 'path/to/dir', 'html');
    // Mvc::getView will be called three times in the array loop as array count is three.
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->andReturn($view1 = 'view_content_1 ')
      ->with('file1', 'html', $this->data['controller_data']);

    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->andReturn($view2 = 'view_content_2 ')
      ->with('file2', 'html', $this->data['controller_data']);

    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->andReturn($view3 = 'view_content_3')
      ->with('file3', 'html', $this->data['controller_data']);

    $result   = $this->controller->getViewGroup($path);

    $this->assertSame($view1 . $view2 . $view3, $result);
  }

  /** @test */
  public function getViewGroup_method_returns_a_view_from_an_array_of_files()
  {
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->andReturn($view1 = 'view_content_1 ')
      ->with($file1 = 'file1', 'html', $this->data['controller_data']);

    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->andReturn($view2 = 'view_content_2')
      ->with($file2 = 'file2', 'html', $this->data['controller_data']);

    $result   = $this->controller->getViewGroup([$file1, $file2]);

    $this->assertSame($view1 . $view2, $result);
  }

  /** @test */
  public function getViewGroup_method_throws_an_exception_if_it_fails_to_fetch_files_from_dir()
  {
    $this->expectException(\Exception::class);

    $this->mvc_mock->shouldReceive('fetchDir')
      ->once()
      ->andReturnNull()
      ->with($path = 'path/to/dir/that/not/exist', 'html');

    $this->controller->getViewGroup($path);
  }

  /** @test */
  public function getViewGroup_method_throws_an_exception_when_dir_is_empty()
  {
    $this->expectException(\Exception::class);

    $this->mvc_mock->shouldReceive('fetchDir')
      ->once()
      ->with($path = 'path/to/an/empty/dir', 'html')
      ->andReturn([]);

    $this->controller->getViewGroup($path);
  }

  /** @test */
  public function getCss_method_returns_a_css_encapsulated_in_scoped_style_tag()
  {
    // This method cannot be tested in this case as it depend on \CssMin::minify($r)
    // Which is not injected and a static method so  cannot be tested
    $this->assertTrue(true);
  }

  /** @test */
  public function getCss_method_returns_false_when_it_cannot_get_the_view()
  {
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->with($path = 'path/to/css/file/that/not/exist', 'css', $this->data['controller_data'])
      ->andReturnFalse();

    $this->assertFalse($this->controller->getCss($path));
  }

  /** @test */
  public function getLess_method_returns_a_compiled_less_view_encapsulated_in_a_style_tag()
  {
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->with($path = 'path/to/file', 'css', $this->data['controller_data'])
      ->andReturn($view = 'less_view');

    $this->assertSame($view, $this->controller->getLess($path));
  }

  /** @test */
  public function getLess_method_returns_false_when_it_cannot_get_the_view()
  {
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->with($path = 'path/to/file/that/not/exist', 'css', $this->data['controller_data'])
      ->andReturnFalse();

    $this->assertFalse($this->controller->getLess($path));
  }

  /** @test */
  public function addCss_method_will_add_a_css_view_to_the_output_object_if_it_has_content()
  {
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->with($path = 'path/to/file', 'css', $this->data['controller_data'])
      ->andReturn('css_view');

    // This method depends on getCss method which cannot be tested as stated previously

    $this->assertInstanceOf(Controller::class, $this->controller->addCss($path));
  }

  /** @test */
  public function addCss_method_will_not_add_a_css_view_to_the_output_object_if_has_no_content()
  {
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->with($path = 'path/to/file/that/not/exist', 'css', $this->data['controller_data'])
      ->andReturnFalse();

    $result = $this->controller->addCss($path);

    $this->assertFalse(isset($this->controller->obj->css));
    $this->assertInstanceOf(Controller::class, $result);
  }

  /** @test */
  public function addLess_method_will_add_a_less_view_to_the_output_object_if_it_has_content()
  {
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->with($path = 'path/to/file1', 'css', $this->data['controller_data'])
      ->andReturn($view1 = 'less_view_1');

    $result = $this->controller->addLess($path);

    $this->assertTrue(isset($this->controller->obj->css));
    $this->assertSame($view1, $this->controller->obj->css);
    $this->assertInstanceOf(Controller::class, $result);

    // Add another one
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->with($path = 'path/to/file2', 'css', $this->data['controller_data'])
      ->andReturn($view2 = ' less_view_2');

    $result = $this->controller->addLess($path);
    $this->assertSame($view1 . $view2, $this->controller->obj->css);
    $this->assertInstanceOf(Controller::class, $result);

  }

  /** @test */
  public function addLess_method_will_not_add_a_less_view_to_the_output_object_if_it_has_no_content()
  {
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->with($path = 'path/to/file/that/not/exist', 'css', $this->data['controller_data'])
      ->andReturnFalse();

    $result = $this->controller->addLess($path);

    $this->assertFalse(isset($this->controller->obj->css));
    $this->assertInstanceOf(Controller::class, $result);
  }

  /** @test */
  public function addJs_method_will_add_a_js_view_from_a_file_path_to_the_output_object()
  {
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->with($path = 'path/to/file1', 'js', true)
      ->andReturn($view1 = 'js_view_1 ');

    $result = $this->controller->addJs($path, true);

    $this->assertTrue(isset($this->controller->obj->script));
    $this->assertSame($view1, $this->controller->obj->script);
    $this->assertInstanceOf(Controller::class, $result);

    // Add another one but this time with php variable to test the retrieveVar method
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->with($path = 'path/to/file2', 'js', $this->data['controller_data']['variable_1'])
      ->andReturn($view2 = 'js_view_2');

    $result2 = $this->controller->addJs($path, '$variable_1');

    $this->assertSame($view1 . $view2, $this->controller->obj->script);
    $this->assertInstanceOf(Controller::class, $result2);

    // Add another one without providing a path but only data
    // Default path should be used internally so let's set that expectation
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->with($this->info['path'], 'js', $data = ['custom_variable' => 'custom_value'])
      ->andReturn($view3 = 'js_view_3');

    $result3 = $this->controller->addJs($data);

    $this->assertSame($view1 . $view2 . $view3, $this->controller->obj->script);
    $this->assertInstanceOf(Controller::class, $result3);

    // Add another one with providing only a boolean argument
    // Default path and default controller data should be used internally so let's set that expectation
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->with($this->info['path'], 'js', $this->data['controller_data'])
      ->andReturn($view4 = 'js_view_4');

    $result3 = $this->controller->addJs(true);

    $this->assertSame($view1 . $view2 . $view3 . $view4, $this->controller->obj->script);
    $this->assertInstanceOf(Controller::class, $result3);
  }

  /** @test */
  public function addJsGroup_method_will_add_a_js_view_from_a_directory_to_the_output_object()
  {
    $this->mvc_mock->shouldReceive('fetchDir')
      ->once()
      ->with($path = 'path/to/dir', 'js')
      ->andReturn(['file1', 'file2']);

    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->with('file1', 'js', [])
      ->andReturn($view1 = 'js_view_1');

    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->with('file2', 'js', [])
      ->andReturn($view2 = 'js_view_2');

    $result = $this->controller->addJsGroup($path);

    $this->assertTrue(isset($this->controller->obj->script));
    $this->assertSame($view1 . $view2, $this->controller->obj->script);
    $this->assertInstanceOf(Controller::class, $result);
  }

  /** @test */
  public function addJsGroup_method_will_add_a_js_view_from_an_array_of_files_to_output_object()
  {
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->with('file1', 'js', $data = ['variable' => 'value'])
      ->andReturn($view1 = 'js_view_1');

    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->with('file2', 'js', $data)
      ->andReturn($view2 = 'js_view_2');

    $result = $this->controller->addJsGroup( ['file1', 'file2'], $data);

    $this->assertTrue(isset($this->controller->obj->script));
    $this->assertSame($view1 . $view2, $this->controller->obj->script);
    $this->assertInstanceOf(Controller::class, $result);
  }

  /** @test */
  public function addJsGroup_method_will_throw_an_exception_if_it_fails_to_fetch_files_from_dir()
  {
    $this->expectException(\Exception::class);

    $this->mvc_mock->shouldReceive('fetchDir')
      ->once()
      ->with($path = 'path/to/dir/that/not/exist', 'js')
      ->andReturnNull();

    $result = $this->controller->addJsGroup($path);

    $this->assertInstanceOf(Controller::class, $result);
  }

  /** @test */
  public function addJsGroup_method_will_throw_an_exception_if_the_files_array_are_empty()
  {
    $this->expectException(\Exception::class);

    $result = $this->controller->addJsGroup([]);

    $this->assertInstanceOf(Controller::class, $result);
  }

  /** @test */
  public function setObj_method_will_add_to_the_output_object_from_an_array()
  {
    $result = $this->controller->setObj(['key_1' => 'value_1', 'key_2' => 'value_2']);

    $this->assertSame('value_1', $this->controller->obj->key_1);
    $this->assertSame('value_2', $this->controller->obj->key_2);
    $this->assertInstanceOf(Controller::class, $result);
  }

  /** @test */
  public function setUrl_method_will_set_the_url_in_the_output_object()
  {
    $result = $this->controller->setUrl($url = 'path/to');

    $this->assertTrue(isset($this->controller->obj->url));
    $this->assertSame($url, $this->controller->obj->url);
    $this->assertInstanceOf(Controller::class, $result);

    $this->controller->setUrl($url = 'new/path/to');

    $this->assertSame($url, $this->controller->obj->url);

  }

  /** @test */
  public function setTitle_method_sets_the_title_on_the_output_object()
  {
    $result = $this->controller->setTitle('foo');

    $this->assertTrue(isset($this->controller->obj->title));
    $this->assertSame('foo', $this->controller->obj->title);
    $this->assertInstanceOf(Controller::class, $result);

    $this->controller->setTitle('bar');

    $this->assertSame('bar', $this->controller->obj->title);
  }

  /** @test */
  public function setIcon_method_sets_the_icon_on_the_output_object()
  {
    $result = $this->controller->setIcon('icon_1');

    $this->assertTrue(isset($this->controller->obj->icon));
    $this->assertSame('icon_1', $this->controller->obj->icon);
    $this->assertInstanceOf(Controller::class, $result);

    $this->controller->setIcon('icon_2');

    $this->assertSame('icon_2', $this->controller->obj->icon);
  }

  /** @test */
  public function setColor_method_sets_background_and_font_colors_on_the_output_object()
  {
    $result = $this->controller->setColor('red', 'blue');

    $this->assertTrue(isset($this->controller->obj->bcolor));
    $this->assertTrue(isset($this->controller->obj->fcolor));
    $this->assertSame('red', $this->controller->obj->bcolor);
    $this->assertSame('blue', $this->controller->obj->fcolor);
    $this->assertInstanceOf(Controller::class, $result);

    $this->controller->setColor(null, null);

    $this->assertSame('red', $this->controller->obj->bcolor);
    $this->assertSame('blue', $this->controller->obj->fcolor);
  }

  /** @test */
  public function routeComponent_method_returns_component_from_the_given_name_if_exists()
  {
    $this->mvc_mock->shouldReceive('routeComponent')
      ->once()
      ->with('component_name')
      ->andReturn($data = [
        'js' => [
          'file'       => 'foo/bar/baz/src/components/form/form.js',
          'path'       => 'form',
          'plugin'     => 'http://foo.bar',
          'component'  => true,
          'ext'       => 'js',
          'mode'      => 'js',
          'i18n'      => 'foo/bar/baz/src/components/form/locale/en/en.json'
        ]
      ]);


    $this->assertSame($data, $this->controller->routeComponent('component_name'));
  }

  /** @test */
  public function getComponent_method_returns_a_component_with_content_from_a_given_name()
  {
    // Cannot test this method in this case since it depends on the View class
    // and initialize it in the method itself
    $this->assertTrue(true);
  }

  /** @test */
  public function getComponent_method_returns_null_if_the_returned_component_has_no_js_in_it()
  {
    $this->mvc_mock->shouldReceive('routeComponent')
      ->once()
      ->with('component_with_no_js')
      ->andReturn([
        'css' => [
          'file'       => 'foo/bar/baz/src/components/form/form.js',
          'path'       => 'form',
          'plugin'     => 'http://foo.bar',
          'component'  => true,
          'ext'       => 'js',
          'mode'      => 'js',
          'i18n'      => 'foo/bar/baz/src/components/form/locale/en/en.json'
        ]
      ]);

    $this->assertNull($this->controller->getComponent('component_with_no_js'));
  }

  /** @test */
  public function getComponent_method_returns_null_if_a_component_cannot_be_found()
  {
    $this->mvc_mock->shouldReceive('routeComponent')
      ->once()
      ->with('not_found_component')
      ->andReturnNull();

    $this->assertNull($this->controller->getComponent('not_found_component'));
  }

  /** @test */
  public function jsData_method_sets_the_output_object_data_property_from_an_array()
  {
    $result = $this->controller->jsData($data = ['key_1' => 'value_1', 'key_2' => 'value_2']);

    $this->assertTrue(isset($this->controller->obj->data));
    $this->assertSame($data, $this->controller->obj->data);
    $this->assertInstanceOf(Controller::class, $result);
  }

  /** @test */
  public function jsData_method_dont_sets_the_output_object_data_property_if_the_array_is_not_assoc()
  {
    $result = $this->controller->jsData(['value_1', 'value_2']);

    $this->assertTrue(!isset($this->controller->obj->data->key_1));
    $this->assertTrue(!isset($this->controller->obj->data->key_2));
    $this->assertInstanceOf(Controller::class, $result);
  }

  /** @test */
  public function jsData_method_adds_to_the_output_object_data_property_if_already_exists()
  {
    $this->controller->obj->data = $existing_data = ['existing_key' => 'existing_value'];

    $result = $this->controller->jsData($data = ['key_1' => 'value_1', 'key_2' => 'value_2']);

    $this->assertSame(array_merge($existing_data, $data), $this->controller->obj->data);
    $this->assertInstanceOf(Controller::class, $result);
  }

  /** @test */
  public function getArguments_method_parses_arguments_from_an_array()
  {
    $method = $this->getNonPublicMethod('getArguments');

    $this->assertSame(
      [
        'path' => $this->info['path'],
        'data' => $this->data['controller_data'],
        'die'  => true
      ],
      $method->invoke($this->controller, [])
    );

    $this->assertSame(
      [
        'data' => [
          'key_1' => 'value_1',
          'key_2' => 'value_2'
        ],
        'path' => $this->info['path'],
        'die' => true
      ],
      $method->invoke($this->controller, ['$variable_1'])
    );

    $this->assertSame(
      [
        'path' => 'custom_path',
        'data' => $this->data['controller_data'],
        'die' => true
      ],
      $method->invoke($this->controller, ['custom_path'])
    );

    $this->assertSame(
      [
        'mode' => 'html',
        'path' => $this->info['path'],
        'data' => $this->data['controller_data'],
        'die' => true
      ],
      $method->invoke($this->controller, ['html'])
    );

    $this->assertSame(
      [
        'data' => [
          'custom_variable' => 'custom_value'
        ],
        'path' => $this->info['path'],
        'die' => true
      ],
      $method->invoke($this->controller, [['custom_variable' => 'custom_value']])
    );

    $this->assertSame(
      [
        'die'  => false,
        'data' => [
          'custom_variable' => 'custom_value'
        ],
        'mode' => 'html',
        'path' => $this->info['path'],
      ],
      $method->invoke($this->controller, ['html', false, ['custom_variable' => 'custom_value']])
    );

    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('');

    $this->assertSame(
      [
        'path' => 'path/to/new_path',
        'data' => $this->data['controller_data'],
        'die'  => true,
      ],
      $method->invoke($this->controller, ['./new_path'])
    );

    $this->controller->mode = 'dom';

    $this->assertSame(
      [
        'mode' => 'html',
        'path' => $this->info['path'] . '/index',
        'data' => $this->data['controller_data'],
        'die'  => true,
      ],
      $method->invoke($this->controller, ['html'])
    );
  }

  /** @test */
  public function getView_method_will_get_a_view()
  {
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->with(
        $this->info['path'],
        'html',
        $this->data['controller_data']
      )
      ->andReturn('view');

    $result = $this->controller->getView('html');

    $this->assertSame('view', $result);
  }

  /** @test */
  public function getView_method_will_get_a_html_view_if_mode_is_not_specified()
  {
    $this->mvc_mock->shouldReceive('getView')
    ->once()
    ->with(
      $this->info['path'],
      'html',
      $this->data['controller_data']
    )
    ->andReturn('view');

    $result = $this->controller->getView(false);

    $this->assertSame('view', $result);
  }

  /** @test */
  public function getExternalView_method_gets_a_view_from_different_root()
  {
    $this->mvc_mock->shouldReceive('getExternalView')
      ->once()
      ->with('foo/bar', 'html', ['foo' => 'bar'])
      ->andReturn('view_content');

    $result = $this->controller->getExternalView('foo/bar', 'html', ['foo' => 'bar']);

    $this->assertSame('view_content', $result);
  }

  /** @test */
  public function customPluginView_method_retrieves_a_view_from_custom_plugin()
  {
    $this->mvc_mock->shouldReceive('customPluginView')
      ->once()
      ->with('foo/bar', 'html', ['foo' => 'bar'], 'custom_plugin')
      ->andReturn('view_content');

    $result = $this->controller->customPluginView('foo/bar', 'html', ['foo' => 'bar'], 'custom_plugin');

    $this->assertSame('view_content', $result);
  }

  /** @test */
  public function customPluginView_method_retrieves_a_view_from_current_plugin_if_plugin_is_not_provided()
  {
    $this->mvc_mock->shouldReceive('customPluginView')
      ->once()
      ->with('foo/bar', 'html', ['foo' => 'bar'], $this->getNonPublicProperty('_plugin'))
      ->andReturn('view_content');

    $result = $this->controller->customPluginView('foo/bar', 'html', ['foo' => 'bar']);

    $this->assertSame('view_content', $result);
  }

  /** @test */
  public function customPluginView_method_returns_null_when_plugin_is_not_set()
  {
    $this->setNonPublicPropertyValue('_plugin', null);

    $this->mvc_mock->shouldNotReceive('customPluginView');

    $result = $this->controller->customPluginView('foo/bar', 'html', ['foo' => 'bar']);

    $this->assertNull($result);
  }

  /** @test */
  public function getPluginView_method_retrieves_a_view()
  {
    $this->mvc_mock->shouldReceive('getPluginView')
      ->once()
      ->with('foo/bar', 'html', ['foo' => 'bar'], $this->getNonPublicProperty('_plugin'))
      ->andReturn('view_content');

    $result = $this->controller->getPluginView('foo/bar', 'html', ['foo' => 'bar']);

    $this->assertSame('view_content', $result);
  }

  /** @test */
  public function getPluginViews_method_returns_an_array_of_views()
  {
    $path   = 'foo/bar';
    $data   = ['foo' => 'bar'];
    $plugin = $this->getNonPublicProperty('_plugin');

    $this->mvc_mock->shouldReceive('getPluginView')
      ->times(3)
      ->andReturn('html_content', 'css_content', 'js_content');

    $result   = $this->controller->getPluginViews($path, $data);
    $expected = [
      'html'  => 'html_content',
      'css'   => 'css_content',
      'js'    => 'js_content'
    ];

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function getPluginModel_method_returns_a_mode_of_the_provided_plugin()
  {
   $this->mvc_mock->shouldReceive('getPluginModel')
     ->once()
     ->with(
       'foo/bar',
       ['foo' => 'bar'],
       $this->controller,
       'custom_plugin',
       2
     )
     ->andReturn(['model' => ['key' => 'value']]
     );

    $result = $this->controller->getPluginModel(
      'foo/bar',
      ['foo' => 'bar'],
      'custom_plugin',
      2
    );

    $this->assertSame(['model' => ['key' => 'value']], $result);
  }

  /** @test */
  public function getPluginModel_method_returns_a_mode_of_the_current_plugin_if_no_plugin_provided()
  {
    $this->mvc_mock->shouldReceive('getPluginModel')
      ->once()
      ->with(
        'foo/bar',
        ['foo' => 'bar'],
        $this->controller,
        $this->getNonPublicProperty('_plugin'),
        2
      )
      ->andReturn(['model' => ['key' => 'value']]
      );

    $result = $this->controller->getPluginModel(
      'foo/bar',
      ['foo' => 'bar'],
      null,
      2
    );

    $this->assertSame(['model' => ['key' => 'value']], $result);
  }

  /** @test */
  public function getSubpluginModel_method_returns_a_sub_plugin_model()
  {
    $this->mvc_mock->shouldReceive('getSubpluginModel')
      ->once()
      ->with(
        'foo/bar',
        ['foo' => 'bar'],
        $this->controller,
        'parent_plugin',
        'sub_plugin',
        2
      )
      ->andReturn(['model' => ['key' => 'value']]);

    $result = $this->controller->getSubpluginModel(
      'foo/bar',
      ['foo' => 'bar'],
      'parent_plugin',
      'sub_plugin',
      2
    );

    $this->assertSame(['model' => ['key' => 'value']], $result);
  }

  /** @test */
  public function getSubpluginModel_method_returns_a_sub_plugin_model_of_the_current_plugin_if_no_plugin_provided()
  {
    $this->mvc_mock->shouldReceive('getSubpluginModel')
      ->once()
      ->with(
        'foo/bar',
        ['foo' => 'bar'],
        $this->controller,
        $this->getNonPublicProperty('_plugin'),
        'sub_plugin',
        20
      )
      ->andReturn(['model' => ['key' => 'value']]);

    $result = $this->controller->getSubpluginModel(
      'foo/bar',
      ['foo' => 'bar'],
      null,
      'sub_plugin',
      20
    );

    $this->assertSame(['model' => ['key' => 'value']], $result);
  }

  /** @test */
  public function hasSubpluginModel_method_returns_true_if_the_sub_plugin_model_exists()
  {
    $this->mvc_mock->shouldReceive('hasSubpluginModel')
      ->once()
      ->with('foo/bar', 'plugin', 'sub_plugin')
      ->andReturnTrue();

    $result = $this->controller->hasSubpluginModel('foo/bar', 'plugin', 'sub_plugin');

    $this->assertTrue($result);
  }

  /** @test */
  public function retrieveVar_method_()
  {
    $retrieve_var_method = $this->getNonPublicMethod('retrieveVar');

    $this->assertSame(
      $this->data['controller_data']['variable_2'],
      $retrieve_var_method->invoke($this->controller, '$variable_2')
    );

    $this->assertFalse($retrieve_var_method->invoke($this->controller, '$variable_3'));
    $this->assertFalse($retrieve_var_method->invoke($this->controller, 'variable_2'));
  }

  /** @test */
  public function action_method_merges_post_data_and_result_data_with_the_current_data_and_sets_the_output_object()
  {
    $this->mvc_mock->shouldReceive('getModel')->once()->andReturn(['foo' => 'bar']);

    $this->controller->action();

   $this->assertSame(
    array_merge(
      array_merge($this->data['controller_data'], ['res' => ['success' => false]]),
      $this->data['post']
    ),
     $this->controller->data
   );

   $this->assertIsObject($this->controller->obj);
   $this->assertTrue(isset($this->controller->obj->foo));
   $this->assertSame('bar', $this->controller->obj->foo);
  }

  /** @test */
  public function action_method_returns_a_default_result_if_get_model_fails()
  {
    $this->controller = Mockery::mock(Controller::class)->makePartial();
    $this->controller->shouldReceive('getModel')->once()->andReturnFalse();

    $this->controller->action();

    $this->assertSame(['res' => ['success' => false]], $this->controller->data);
  }
  
  /** @test */
  public function cachedAction_method_merges_post_data_and_result_data_with_the_current_data_and_sets_the_output_object()
  {
    $expected_data = array_merge(
      array_merge($this->data['controller_data'], ['res' => ['success' => false]]),
      $this->data['post']
    );

    $this->mvc_mock->shouldReceive('getCachedModel')
      ->once()
      ->with($this->controller->getPath(), $expected_data, $this->controller ,11)
      ->andReturn(['foo' => 'bar']);

    $this->controller->cachedAction(11);

    $this->assertSame($expected_data, $this->controller->data);
    $this->assertTrue(isset($this->controller->obj->foo));
    $this->assertSame('bar', $this->controller->obj->foo);
  }
  
  /** @test */
  public function combo_method_compiles_and_echoes_all_the_views_with_the_given_data()
  {
    $this->mvc_mock->shouldReceive('getRoute')
      ->once()
      ->andReturnTrue();

    $this->mvc_mock->shouldReceive('getModel')
      ->once()
      ->with(
        $this->info['path'] . '/' . basename($this->info['file'], '.php'),
        \bbn\X::mergeArrays($this->controller->post, $this->controller->data),
        $this->controller
      )
      ->andReturn(['foo' => 'bar']);

    $this->mvc_mock->shouldReceive('getView')
      ->times(3)
      ->andReturn('css_view', 'js_view', 'html_view');

    $this->controller->combo('example');

    $this->assertTrue(isset($this->controller->obj->css));
    $this->assertSame('css_view', $this->controller->obj->css);

    $this->assertTrue(isset($this->controller->obj->script));
    $this->assertSame('js_view', $this->controller->obj->script);

    $this->assertTrue(isset($this->controller->obj->title));
    $this->assertSame('example', $this->controller->obj->title);

    $this->assertTrue(isset($this->controller->data['foo']));
    $this->assertSame('bar', $this->controller->data['foo']);
  }

  /** @test */
  public function getContent_method_returns_the_content_of_a_file_located_within_the_data_path()
  {
    $file_path = self::createFile('test.txt',  'Hello world!', 'controllers');
    $file_path = str_replace(BBN_APP_PATH . BBN_DATA_PATH, '',$file_path);

    $this->assertSame('Hello world!', $this->controller->getContent($file_path));

    unlink(BBN_APP_PATH . BBN_DATA_PATH . $file_path);
  }

  /** @test */
  public function getContent_method_returns_false_if_path_is_not_valid()
  {
    $this->assertFalse($this->controller->getContent('foo/bar'));
  }

  /** @test */
  public function getDir_method_returns_the_path_to_the_directory_of_the_current_controller()
  {
    $this->assertSame(
      $this->getNonPublicProperty('_dir'),
      $this->controller->getDir()
    );
  }

  /** @test */
  public function getPrepath_method_returns_pre_path_from_mvc_object()
  {
    $this->mvc_mock->shouldReceive('getPrepath')
      ->once()
      ->andReturn('foo');

    $this->assertSame('foo', $this->controller->getPrepath());
  }

  /** @test */
  public function getPrepath_method_returns_empty_string_if_path_property_is_empty()
  {
    $this->setNonPublicPropertyValue('_path', '');

    $this->assertSame('', $this->controller->getPrepath());
  }

  /** @test */
  public function setPrepath_method_sets_the_prepath()
  {
    $this->mvc_mock->shouldReceive('setPrepath')
      ->once()
      ->with('foo/bar')
      ->andReturn(1);

    $this->mvc_mock->shouldReceive('getParams')
      ->once()
      ->andReturn(['foo' => 'bar']);

    $this->assertNotSame(
      ['foo' => 'bar'],
      $this->getNonPublicProperty('params')
    );

    $this->assertInstanceOf(Controller::class, $this->controller->setPrepath('foo/bar'));

    $this->assertSame(
      ['foo' => 'bar'],
      $this->getNonPublicProperty('params')
    );
  }

  /** @test */
  public function setPrepath_method_throws_an_exception_when_the_path_property_is_empty()
  {
    $this->expectException(\Exception::class);

    $this->setNonPublicPropertyValue('_path', '');

    $this->controller->setPrepath('foo/bar');
  }

  /** @test */
  public function setPrepath_method_throws_an_exception_when_setPrepath_on_mvc_object_throws_exception()
  {
    $this->expectException(\Exception::class);

    $this->mvc_mock->shouldReceive('setPrepath')
      ->once()
      ->with('foo/bar')
      ->andThrows(\Exception::class);

    $this->controller->setPrepath('foo/bar');
  }

  /** @test */
  public function getModel_method_returns_the_model_using_controller_data_and_path_when_no_arguments_provided()
  {
    $this->mvc_mock->shouldReceive('getModel')
      ->once()
      ->with(
        // Here is expectation of the arguments used to call Mvc::getModel()
        $this->getNonPublicProperty('_path'),
        $this->getNonPublicProperty('data'),
        $this->controller
      )
      ->andReturn(['foo' => 'bar']);

   $this->assertSame(['foo' => 'bar'], $this->controller->getModel());
  }

  /** @test */
  public function getModel_method_returns_the_model_using_the_provided_path()
  {
    $this->mvc_mock->shouldReceive('getModel')
      ->once()
      ->with(
        'custom/path/to',
        $this->getNonPublicProperty('data'),
        $this->controller
      )
      ->andReturn(['foo' => 'bar']);

    $this->assertSame(['foo' => 'bar'], $this->controller->getModel('custom/path/to'));
  }

  /** @test */
  public function getModel_method_returns_the_model_using_the_provided_data()
  {
    $this->mvc_mock->shouldReceive('getModel')
      ->once()
      ->with(
        $this->getNonPublicProperty('_path'),
        ['data_key' => 'data_value'],
        $this->controller
      )
      ->andReturn(['foo' => 'bar']);

    $this->assertSame(['foo' => 'bar'], $this->controller->getModel(['data_key' => 'data_value']));
  }

  /** @test */
  public function getModel_method_returns_the_model_using_the_provided_path_and_data()
  {
    $this->mvc_mock->shouldReceive('getModel')
      ->once()
      ->with(
        'custom/path/to',
        ['data_key' => 'data_value'],
        $this->controller
      )
      ->andReturn(['foo' => 'bar']);

    $this->assertSame(
      ['foo' => 'bar'],
      $this->controller->getModel('custom/path/to', ['data_key' => 'data_value'])
    );
  }

  /** @test */
  public function getModel_method_returns_the_model_when_path_is_not_provided_and_mode_is_dom()
  {
    $this->setNonPublicPropertyValue('mode', 'dom');

    $this->mvc_mock->shouldReceive('getModel')
      ->once()
      ->with(
        $this->getNonPublicProperty('_path') . '/index',
        $this->getNonPublicProperty('data'),
        $this->controller
      )
      ->andReturn(['foo' => 'bar']);

    $this->assertSame(
      ['foo' => 'bar'],
      $this->controller->getModel()
    );
  }

  /** @test */
  public function getModel_method_returns_the_model_when_path_is_provided_and_it_starts_with_a_dot_and_a_backslash()
  {
    $this->mvc_mock->shouldReceive('getmodel')
      ->once()
      ->with(
        dirname($this->getNonPublicProperty('_path')) . '/custom_path',
        $this->getNonPublicProperty('data'),
        $this->controller
      )
      ->andReturn(['foo' => 'bar']);

    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('');


    $this->assertSame(
      ['foo' => 'bar'],
      $this->controller->getModel('./custom_path')
    );
  }

  /** @test */
  public function getModel_method_returns_the_model_when_the_returned_model_is_an_object()
  {
    $this->mvc_mock->shouldReceive('getmodel')
      ->once()
      ->with(
        $this->getNonPublicProperty('_path'),
        $this->getNonPublicProperty('data'),
        $this->controller
      )
      ->andReturn((object)['foo' => 'bar']);


    $this->assertSame(
      ['foo' => 'bar'],
      $this->controller->getModel()
    );
  }

  /** @test */
  public function getModel_method_throws_an_exception_when_true_is_provided_and_returned_model_is_not_an_array()
  {
    $this->expectException(\Exception::class);

    $this->mvc_mock->shouldReceive('getModel')
      ->once()
      ->andReturn('foo');

    $this->controller->getModel(true);
  }

  /** @test */
  public function getModel_method_returns_an_empty_array_when_false_is_provided_and_returned_model_is_not_an_array()
  {
    $this->mvc_mock->shouldReceive('getModel')
      ->once()
      ->andReturn('foo');

    $result = $this->controller->getModel(false);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /** @test */
  public function getCachedModel_method_returns_the_cached_model_using_controller_data_and_path_and_zero_ttl_when_no_arguments_provided()
  {
    $this->mvc_mock->shouldReceive('getCachedModel')
      ->once()
      ->with(
        $this->getNonPublicProperty('_path'),
        $this->getNonPublicProperty('data'),
        $this->controller,
        0
      )
      ->andReturn(['foo' => 'bar']);

    $this->assertSame(['foo' => 'bar'], $this->controller->getCachedModel());
  }

  /** @test */
  public function getCachedModel_method_returns_the_cached_model_using_the_provided_path()
  {
    $this->mvc_mock->shouldReceive('getCachedModel')
      ->once()
      ->with(
        'custom/path/to',
        $this->getNonPublicProperty('data'),
        $this->controller,
        0
      )
      ->andReturn(['foo' => 'bar']);

    $this->assertSame(['foo' => 'bar'], $this->controller->getCachedModel('custom/path/to'));
  }

  /** @test */
  public function getCachedModel_method_returns_the_cached_model_using_the_provided_data()
  {
    $this->mvc_mock->shouldReceive('getCachedModel')
      ->once()
      ->with(
        $this->getNonPublicProperty('_path'),
        ['data_key' => 'data_value'],
        $this->controller,
        0
      )
      ->andReturn(['foo' => 'bar']);

    $this->assertSame(['foo' => 'bar'], $this->controller->getCachedModel(['data_key' => 'data_value']));
  }

  /** @test */
  public function getCachedModel_method_returns_the_cached_model_using_the_ttl()
  {
    $this->mvc_mock->shouldReceive('getCachedModel')
      ->once()
      ->with(
        $this->getNonPublicProperty('_path'),
        $this->getNonPublicProperty('data'),
        $this->controller,
        222
      )
      ->andReturn(['foo' => 'bar']);

    $this->assertSame(['foo' => 'bar'], $this->controller->getCachedModel(222));
  }

  /** @test */
  public function getCachedModel_method_returns_the_model_using_the_provided_path_and_data_and_ttl()
  {
    $this->mvc_mock->shouldReceive('getCachedModel')
      ->once()
      ->with(
        'custom/path/to',
        ['data_key' => 'data_value'],
        $this->controller,
        333
      )
      ->andReturn(['foo' => 'bar']);

    $this->assertSame(
      ['foo' => 'bar'],
      $this->controller->getCachedModel('custom/path/to', ['data_key' => 'data_value'], 333)
    );
  }

  /** @test */
  public function getCachedModel_method_returns_the_cached_model_when_path_is_provided_and_it_starts_with_a_dot_and_a_backslash()
  {
    $this->mvc_mock->shouldReceive('getCachedModel')
      ->once()
      ->with(
        dirname($this->getNonPublicProperty('_path')) . '/custom_path',
        $this->getNonPublicProperty('data'),
        $this->controller,
        0
      )
      ->andReturn(['foo' => 'bar']);

    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('');


    $this->assertSame(
      ['foo' => 'bar'],
      $this->controller->getCachedModel('./custom_path')
    );
  }

  /** @test */
  public function getCachedModel_method_returns_the_cached_model_when_the_returned_model_is_an_object()
  {
    $this->mvc_mock->shouldReceive('getCachedModel')
      ->once()
      ->with(
        $this->getNonPublicProperty('_path'),
        $this->getNonPublicProperty('data'),
        $this->controller,
        0
      )
      ->andReturn((object)['foo' => 'bar']);


    $this->assertSame(
      ['foo' => 'bar'],
      $this->controller->getCachedModel()
    );
  }

  /** @test */
  public function getCachedModel_method_throws_an_exception_when_true_is_provided_and_returned_cached_model_is_not_an_array()
  {
    $this->expectException(\Exception::class);

    $this->mvc_mock->shouldReceive('getCachedModel')
      ->once()
      ->andReturn('foo');

    $this->controller->getCachedModel(true);
  }

  /** @test */
  public function getCachedModel_method_returns_an_empty_array_when_false_is_provided_and_returned_cached_model_is_not_an_array()
  {
    $this->mvc_mock->shouldReceive('getCachedModel')
      ->once()
      ->andReturn('foo');

    $result = $this->controller->getCachedModel(false);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /** @test */
  public function deleteCachedModel_method_will_delete_the_cached_model_using_controller_data_and_path_when_no_arguments_provided()
  {
    $this->mvc_mock->shouldReceive('deleteCachedModel')
      ->once()
      ->with(
        $this->getNonPublicProperty('_path'),
        $this->getNonPublicProperty('data'),
        $this->controller
      );

    $this->controller->deleteCachedModel();

    $this->assertTrue(true);
  }

  /** @test */
  public function deleteCachedModel_method_will_delete_the_cached_model_using_the_provided_path()
  {
    $this->mvc_mock->shouldReceive('deleteCachedModel')
      ->once()
      ->with(
        'custom/path',
        $this->getNonPublicProperty('data'),
        $this->controller
      );

    $this->controller->deleteCachedModel('custom/path');

    $this->assertTrue(true);
  }

  /** @test */
  public function deleteCachedModel_method_will_delete_the_cached_model_using_the_provided_data()
  {
    $this->mvc_mock->shouldReceive('deleteCachedModel')
      ->once()
      ->with(
        $this->getNonPublicProperty('_path'),
        ['foo' => 'bar'],
        $this->controller
      );

    $this->controller->deleteCachedModel(['foo' => 'bar']);

    $this->assertTrue(true);
  }

  /** @test */
  public function deleteCachedModel_method_will_delete_the_cached_model_using_the_provided_path_and_data()
  {
    $this->mvc_mock->shouldReceive('deleteCachedModel')
      ->once()
      ->with(
        'custom/path',
        ['foo' => 'bar'],
        $this->controller
      );

    $this->controller->deleteCachedModel(['foo' => 'bar'], 'custom/path');

    $this->assertTrue(true);
  }

  /** @test */
  public function deleteCachedModel_method_will_delete_the_cached_model_when_path_is_provided_and_it_starts_with_a_dot_and_a_backslash()
  {
    $this->mvc_mock->shouldReceive('deleteCachedModel')
      ->once()
      ->with(
        dirname($this->getNonPublicProperty('_path')) . '/custom_path',
        $this->getNonPublicProperty('data'),
        $this->controller
      );

    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('');

    $this->controller->deleteCachedModel('./custom_path');

    $this->assertTrue(true);
  }
}
