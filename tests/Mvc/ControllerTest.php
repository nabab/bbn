<?php

namespace Mvc;

use bbn\Mvc;
use bbn\Mvc\Controller;
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
    'path'      => 'form',
    'file'      => './tests/storage/controllers/controller.php',
    'request'   => 'get',
    'root'      => './tests/',
    'plugin'    => 'plugin',
    'args'      => [
      'foo' => 'bar'
    ],
    'checkers'  => ''
  ];

  protected $data = [
    'controller_data' => [
      'variable_1' => 'value_1',
      'variable_2' => 'value_2'
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
    $this->mvc_mock->shouldReceive('getParams')->andReturn($this->data['params']);
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
    $this->init($this->info, false);

    $this->assertEmpty($this->controller->data);
  }


  /** @test */
  public function addAuthorizedRoute_method_adds_to_authorized_methods()
  {
    $this->mvc_mock->shouldReceive('addAuthorizedRoute')->once()->andReturn(1);

    $this->assertSame(1, $this->controller->addAuthorizedRoute('route_1'));
  }


  /** @test */
  public function isAuthorizedRoute_method_checks_if_a_route_is_authorized()
  {
    $this->mvc_mock->shouldReceive('isAuthorizedRoute')->once()->andReturnTrue();

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
    $this->mvc_mock->shouldReceive('setRoot')->once()->andReturnSelf();

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
  public function getCurrentDir_returns_the_current_controller_dir_name_if_path_exists_and_is_the_parent_dir()
  {
    // When dirname of the _$path property  is '.'
    $this->setNonPublicPropertyValue('_path', 'form');

    $this->assertSame('', $this->controller->getCurrentDir());
  }


  /** @test */
  public function getCurrentDir_returns_the_current_controller_dir_name_if_path_exists_and_is_not_the_parent_dir_withd_a_prepath_removed()
  {
    $this->setNonPublicPropertyValue('_path', 'prepath/parent/form');

    // In this case it depends on Mvc::getPrepath() so let's mock the method .
    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('prepath/');

    $this->assertSame('parent', $this->controller->getCurrentDir());
  }


  /** @test */
  public function getCurrentDir_returns_the_current_controller_dir_name_if_path_exists_and_is_not_the_parent_dir_and_no_prepath_removed()
  {
    $this->setNonPublicPropertyValue('_path', 'parent/form');

    // In this case it depends on Mvc::getPrepath() so let's mock the method .
    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('');

    $this->assertSame('parent', $this->controller->getCurrentDir());
  }


  /** @test */
  public function getCurrentDir_returns_null_if_path_does_not_exists()
  {
    $this->setNonPublicPropertyValue('_path', '');
    $this->assertNull($this->controller->getCurrentDir());
  }


  /** @test */
  public function getLocalPath_returns_the_current_controller_path_with_a_prepath_removed()
  {
    $this->setNonPublicPropertyValue('_path', 'prepath/parent/form');

    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('prepath/');

    $this->assertSame('parent/form', $this->controller->getLocalPath());
  }


  /** @test */
  public function getLocalPath_returns_the_current_controller_path_with_no_prepath_removed()
  {
    $this->setNonPublicPropertyValue('_path', 'parent/form');

    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('');

    $this->assertSame('parent/form', $this->controller->getLocalPath());
  }


  /** @test */
  public function getLocalRoute_returns_the_current_controller_route_with_prepath_removed()
  {
    $this->setNonPublicPropertyValue('_request', 'prepath/parent/form');

    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('prepath/');

    $this->assertSame('parent/form', $this->controller->getLocalRoute());
  }


  /** @test */
  public function getLocalRoute_returns_the_current_controller_route_with_no_prepath_removed()
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
    $this->mvc_mock->shouldReceive('getPrepath')->times(2)->andReturn('');
    $this->mvc_mock->shouldReceive('getRoot')->once()->andReturn($this->info['root']);

    $result = [
      'controller'  => $this->info['file'],
      'dir'         => '',
      'local_path'  => 'form',
      'local_route' => 'get',
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
  public function isCli_checks_if_the_request_is_called_from_cli_or_not()
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
    $file_stub = "<?php
    namespace foo3;
    class test3 {}
    ";

    $this->createDir('plugin_path/lib/foo3');

    $file_path = $this->createFile('test3.php', $file_stub, 'plugin_path/lib/foo3');

    $result = $this->controller->registerPluginClasses(BBN_APP_PATH . BBN_DATA_PATH . 'plugin_path/');
    $this->assertTrue(class_exists('\foo3\test3'));
    $this->assertInstanceOf(Controller::class, $result);
    unlink($file_path);
  }

  /** @test */
  public function hasBeenRerouted_method_checks_if_controller_has_been_rerouted()
  {
    $this->assertFalse($this->controller->hasBeenRerouted());

    $this->setNonPublicPropertyValue('_is_rerouted', 1);

    $this->assertTrue($this->controller->hasBeenRerouted());
  }
}
