<?php

namespace bbn\tests\Mvc;

use bbn\Mvc;
use bbn\Mvc\Controller;
use Mockery;
use PHPUnit\Framework\TestCase;
use bbn\tests\Files;
use bbn\tests\Mockable;
use bbn\tests\Reflectable;

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
      'foo', 'bar'
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

  protected function init()
  {
    $this->controller = new Controller($this->mvc_mock, ...func_get_args());
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

  protected function setMvcMockExpectations()
  {
    $this->mvc_mock->shouldReceive('getDb')->andReturnNull();
    $this->mvc_mock->shouldReceive('getPost')->andReturn($this->data['post']);
    $this->mvc_mock->shouldReceive('getGet')->andReturn($this->data['get']);
    $this->mvc_mock->shouldReceive('getFiles')->andReturn($this->data['files']);
    $this->mvc_mock->shouldReceive('getParams')->once()->andReturn($this->data['params']);
    $this->mvc_mock->shouldReceive('getUrl')->andReturn($this->data['url']);
    $this->mvc_mock->shouldReceive('getRequest')->andReturn($this->data['url']);
    $this->mvc_mock->shouldReceive('getPrepath')->andReturn('');
  }


  /** @test */
  public function testConstructorTestWhenInfoAndDataParamsAreProvided()
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
  public function testConstructorTestWhenInfoParamIsEmpty()
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
  public function testConstructorTestWhenDataParamIsFalse()
  {
    $this->setMvcMockExpectations();
    $this->init($this->info, false);

    $this->assertEmpty($this->controller->data);
  }


  /** @test */
  public function testAddauthorizedrouteMethodAddsToAuthorizedMethods()
  {
    $this->mvc_mock->shouldReceive('addAuthorizedRoute')->with('route_1')->once()->andReturn(1);

    $this->assertSame(1, $this->controller->addAuthorizedRoute('route_1'));
  }


  /** @test */
  public function testIsauthorizedrouteMethodChecksIfARouteIsAuthorized()
  {
    $this->mvc_mock->shouldReceive('isAuthorizedRoute')->with('route_2')->once()->andReturnTrue();

    $this->assertTrue($this->controller->isAuthorizedRoute('route_2'));
  }


  /** @test */
  public function testGetrootMethodReturnsTheRootOfTheApplicationInTheBaseUrl()
  {
    $this->mvc_mock->shouldReceive('getRoot')->once()->andReturn('root/');

    $this->assertSame('root/', $this->controller->getRoot());
  }


  /** @test */
  public function testSetrootMethodSetsTheRootOfTheApplication()
  {
    $this->mvc_mock->shouldReceive('setRoot')->with('root2/')->once()->andReturnSelf();

    $this->assertInstanceOf(Controller::class, $this->controller->setRoot('root2/'));
  }


  /** @test */
  public function testGeturlMethodReturnsTheRequestUrl()
  {
    // Expectation was set in the init function that execute before every test
    $this->assertSame($this->data['url'], $this->controller->getUrl());
  }


  /** @test */
  public function testGetpathMethodReturnsTheInternalPathOfTheController()
  {
    // Expectation was set in the init function that execute before every test
    $this->assertSame($this->info['path'], $this->controller->getPath());
  }


  /** @test */
  public function testGetrequestMethodReturnsTheCurrentControllerRoute()
  {
    $this->assertSame($this->info['request'], $this->controller->getRequest());
  }


  /** @test */
  public function testExistsMethodReturnsTrueIfTheInternalPathOfTheControllerExists()
  {
    $this->assertTrue($this->controller->exists());
  }


  /** @test */
  public function testExistsMethodReturnsFalseIfTheInternalPathOfTheControllerDoesNotExist()
  {
    $this->setNonPublicPropertyValue('_path', '');
    $this->assertFalse($this->controller->exists());
  }


  /** @test */
  public function testGetcurrentdirMethodReturnsTheCurrentControllerDirNameIfPathExistsAndIsTheParentDir()
  {
    // When dirname of the _$path property  is '.'
    $this->setNonPublicPropertyValue('_path', 'form');

    $this->assertSame('', $this->controller->getCurrentDir());
  }


  /** @test */
  public function testGetcurrentdirMethodReturnsTheCurrentControllerDirNameIfPathExistsAndIsNotTheParentDirWithAPrepathRemoved()
  {
    $this->setNonPublicPropertyValue('_path', 'prepath/parent/form');

    // In this case it depends on Mvc::getPrepath() so let's mock the method .
    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('prepath/');

    $this->assertSame('parent', $this->controller->getCurrentDir());
  }


  /** @test */
  public function testGetcurrentdirMethodReturnsTheCurrentControllerDirNameIfPathExistsAndIsNotTheParentDirAndNoPrepathRemoved()
  {
    $this->setNonPublicPropertyValue('_path', 'parent/form');

    // In this case it depends on Mvc::getPrepath() so let's mock the method .
    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('');

    $this->assertSame('parent', $this->controller->getCurrentDir());
  }


  /** @test */
  public function testGetcurrentdirMethodReturnsNullIfPathDoesNotExists()
  {
    $this->setNonPublicPropertyValue('_path', '');
    $this->assertNull($this->controller->getCurrentDir());
  }


  /** @test */
  public function testGetlocalpathMethodReturnsTheCurrentControllerPathWithAPrepathRemoved()
  {
    $this->setNonPublicPropertyValue('_path', 'prepath/parent/form');

    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('prepath/');

    $this->assertSame('parent/form', $this->controller->getLocalPath());
  }


  /** @test */
  public function testGetlocalpathMethodReturnsTheCurrentControllerPathWithNoPrepathRemoved()
  {
    $this->setNonPublicPropertyValue('_path', 'parent/form');

    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('');

    $this->assertSame('parent/form', $this->controller->getLocalPath());
  }


  /** @test */
  public function testGetlocalrouteMethodReturnsTheCurrentControllerRouteWithPrepathRemoved()
  {
    $this->setNonPublicPropertyValue('_request', 'prepath/parent/form');

    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('prepath/');

    $this->assertSame('parent/form', $this->controller->getLocalRoute());
  }


  /** @test */
  public function testGetlocalrouteMethodReturnsTheCurrentControllerRouteWithNoPrepathRemoved()
  {
    $this->setNonPublicPropertyValue('_request', 'parent/form');

    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('');

    $this->assertSame('parent/form', $this->controller->getLocalRoute());
  }


    /** @test */
  public function testGetallMethodReturnsInfoOfTheController()
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
  public function testSayrootMethodReturnsTheCurrentControllerRootDir()
  {
    $this->assertSame($this->info['root'], $this->controller->sayRoot());
  }


  /** @test  */
  public function testGetcontrollerMethodReturnsCurrentControllerFileName()
  {
    $this->assertSame($this->info['file'], $this->controller->getController());
  }


  /** @test */
  public function testGetpluginMethodReturns()
  {
    $this->assertSame($this->info['plugin'], $this->controller->getPlugin());
  }


  /** @test */
  public function testRenderMethodRendersContentUsingTplClassIfModelDataNotEmpty()
  {
    // Cannot test it in this case since it depend on `bbn\Tpl`
    // And it uses it directly in the class and it's a static method
    // So it cannot be mocked
    $this->assertTrue(true);
  }


  /** @test */
  public function testRenderMethodReturnTheViewDirectlyIfModelDataIdEmptyAndDataPropertyIsEmptyToo()
  {
    $this->controller->data = [];
    $this->assertSame('view', $this->controller->render('view'));
  }


  /** @test */
  public function testIscliMethodChecksIfTheRequestIsCalledFromCliOrNot()
  {
    $this->mvc_mock->shouldReceive('isCli')->once()->andReturnTrue();

    $this->assertTrue($this->controller->isCli());
  }


  /** @test */
  public function testRerouteMethodReroutesAControllerToAnotherOneIfItNotHasBeenReroutedBefore()
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
  public function testInclMethodIncludesAPhpFileWithinTheControllerPath()
  {
    $file_stub = '<?php 
  namespace bbn\tests\foo;
  class test {}
';
    $file_path = self::createFile('test.php',  $file_stub, 'controllers');

    // With .php file path
    $result = $this->controller->incl('test.php');
    $this->assertTrue(class_exists('\foo\test'));
    $this->assertInstanceOf(Controller::class, $result);
    unlink($file_path);

    $file_stub = '<?php 
  namespace bbn\tests\foo2;
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
  public function testAddscriptMethodAddsTheGivenStringToScriptProperty()
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
  public function testRegisterpluginclassesMethodRegisterAClassUsingSplAutoload()
  {
    // Will create php files with classes defined to test files inclusion
    $file_stub = "<?php
    namespace bbn\tests\foo3;
    class test3 {}
    ";

    $file_stub2 = "<?php
    namespace bbn\tests\foo4;
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
  public function testControlMethodEnclosesControllerInclusion()
  {
    // Will create a plugin file with a class defined to test plugin class is registered
    $file_stub = "<?php
    namespace bbn\tests\foo5;
    class test5 {}
    ";

    $this->createDir('plugin_path/lib/foo5');
    $file_path = $this->createFile('test5.php', $file_stub, 'plugin_path/lib/foo5');

    // Here will create a controller file to test it's inclusion
    $file2_stub = "<?php
    echo 'html_content';
    ";

    $ctrl_path = self::createFile(basename("{$this->info['file']}"),  $file2_stub, 'controllers');

    $this->mvc_mock->shouldReceive('pluginName')->with($this->info['plugin'])->andReturn('plugin_name');
    $this->mvc_mock->shouldReceive('pluginPath')->with('plugin_name', false)->andReturn(BBN_APP_PATH . BBN_DATA_PATH . 'plugin_path/');
    $this->mvc_mock->shouldReceive('isCli')->andReturnFalse();

    $method = $this->getNonPublicMethod('control', $this->controller);
    $method->invoke($this->controller);

    $this->assertTrue(isset($this->controller->obj->content));
    $this->assertSame('html_content', $this->controller->obj->content);
    $this->assertTrue(class_exists('\foo5\test5'));

    unlink($file_path);
    unlink($ctrl_path);
  }


  /** @test */
  public function testControlMethodReturnsFalseWhenACheckerFileReturnsFalse()
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
  public function testControlMethodDoesNotIncludeWhenItsBeenAlreadyControlledAndTheFilePropertyIsTruthy()
  {
    $this->setNonPublicPropertyValue('_is_controlled', 1);
    $this->setNonPublicPropertyValue('_file', 'path/to/file');

    $method = $this->getNonPublicMethod('control', $this->controller);
    $result = $method->invoke($this->controller);

    $this->assertTrue(!isset($this->obj->content));
    $this->assertTrue($result);
  }


  /** @test */
  public function testProcessMethodLaunchesTheController()
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
  public function testHasbeenreroutedMethodChecksIfControllerHasBeenRerouted()
  {
    $this->assertFalse($this->controller->hasBeenRerouted());

    $this->setNonPublicPropertyValue('_is_rerouted', 1);

    $this->assertTrue($this->controller->hasBeenRerouted());
  }


  /** @test */
  public function testGetjsMethodGetsAJsViewFromAPathToFileEncapsulatedInAnAnonymousFunction()
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
  public function testGetjsMethodGetsAJsViewFromAPathToFileNotEncapsulatedInAnAnonymousFunction()
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
  public function testGetjsMethodReturnsFalseWhenMvcGetviewReturnsEmptyString()
  {
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->andReturn('')
      ->with('view', 'js', $data = ['data_1' => 'value_1', 'data_2' => 'value_2']);

    $result = $this->controller->getJs('view', $data);

    $this->assertFalse($result);
  }


  /** @test */
  public function testGetjsgroupMethodGetsAJsViewFromAPathToDirEncapsulatedInAnAnonymousFunction()
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
  public function testGetjsgroupMethodGetsAJsViewFromAnArrayOfFilesNotEncapsulatedInAnAnonymousFunction()
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
  public function testGetjsgroupMethodThrowsAnExceptionWhenItFailsToFetchFilesFromADir()
  {
    $this->expectException(\Exception::class);
    $this->mvc_mock->shouldReceive('fetchDir')
      ->once()
      ->with($path = 'path/to/dir/that/not/exist', 'js')
      ->andReturnNull();

    $this->controller->getJsGroup($path);
  }


  /** @test */
  public function testGetjsgroupMethodThrowsAnExceptionWhenTheDirIsEmpty()
  {
    $this->expectException(\Exception::class);
    $this->mvc_mock->shouldReceive('fetchDir')
      ->once()
      ->andReturn([])
      ->with($path = 'path/to/empty/dir', 'js');

    $this->controller->getJsGroup($path);
  }

  /** @test */
  public function testGetviewgroupMethodReturnsAViewFromADirPath()
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
  public function testGetviewgroupMethodReturnsAViewFromAnArrayOfFiles()
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
  public function testGetviewgroupMethodThrowsAnExceptionIfItFailsToFetchFilesFromDir()
  {
    $this->expectException(\Exception::class);

    $this->mvc_mock->shouldReceive('fetchDir')
      ->once()
      ->andReturnNull()
      ->with($path = 'path/to/dir/that/not/exist', 'html');

    $this->controller->getViewGroup($path);
  }

  /** @test */
  public function testGetviewgroupMethodThrowsAnExceptionWhenDirIsEmpty()
  {
    $this->expectException(\Exception::class);

    $this->mvc_mock->shouldReceive('fetchDir')
      ->once()
      ->with($path = 'path/to/an/empty/dir', 'html')
      ->andReturn([]);

    $this->controller->getViewGroup($path);
  }

  /** @test */
  public function testGetcssMethodReturnsACssEncapsulatedInScopedStyleTag()
  {
    // This method cannot be tested in this case as it depend on \CssMin::minify($r)
    // Which is not injected and a static method so  cannot be tested
    $this->assertTrue(true);
  }

  /** @test */
  public function testGetcssMethodReturnsFalseWhenItCannotGetTheView()
  {
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->with($path = 'path/to/css/file/that/not/exist', 'css', $this->data['controller_data'])
      ->andReturnFalse();

    $this->assertFalse($this->controller->getCss($path));
  }

  /** @test */
  public function testGetlessMethodReturnsACompiledLessViewEncapsulatedInAStyleTag()
  {
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->with($path = 'path/to/file', 'css', $this->data['controller_data'])
      ->andReturn($view = 'less_view');

    $this->assertSame($view, $this->controller->getLess($path));
  }

  /** @test */
  public function testGetlessMethodReturnsFalseWhenItCannotGetTheView()
  {
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->with($path = 'path/to/file/that/not/exist', 'css', $this->data['controller_data'])
      ->andReturnFalse();

    $this->assertFalse($this->controller->getLess($path));
  }

  /** @test */
  public function testAddcssMethodWillAddACssViewToTheOutputObjectIfItHasContent()
  {
    $this->mvc_mock->shouldReceive('getView')
      ->once()
      ->with($path = 'path/to/file', 'css', $this->data['controller_data'])
      ->andReturn('css_view');

    // This method depends on getCss method which cannot be tested as stated previously

    $this->assertInstanceOf(Controller::class, $this->controller->addCss($path));
  }

  /** @test */
  public function testAddcssMethodWillNotAddACssViewToTheOutputObjectIfHasNoContent()
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
  public function testAddlessMethodWillAddALessViewToTheOutputObjectIfItHasContent()
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
  public function testAddlessMethodWillNotAddALessViewToTheOutputObjectIfItHasNoContent()
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
  public function testAddjsMethodWillAddAJsViewFromAFilePathToTheOutputObject()
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
  public function testAddjsgroupMethodWillAddAJsViewFromADirectoryToTheOutputObject()
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
  public function testAddjsgroupMethodWillAddAJsViewFromAnArrayOfFilesToOutputObject()
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
  public function testAddjsgroupMethodWillThrowAnExceptionIfItFailsToFetchFilesFromDir()
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
  public function testAddjsgroupMethodWillThrowAnExceptionIfTheFilesArrayAreEmpty()
  {
    $this->expectException(\Exception::class);

    $result = $this->controller->addJsGroup([]);

    $this->assertInstanceOf(Controller::class, $result);
  }

  /** @test */
  public function testSetobjMethodWillAddToTheOutputObjectFromAnArray()
  {
    $result = $this->controller->setObj(['key_1' => 'value_1', 'key_2' => 'value_2']);

    $this->assertSame('value_1', $this->controller->obj->key_1);
    $this->assertSame('value_2', $this->controller->obj->key_2);
    $this->assertInstanceOf(Controller::class, $result);
  }

  /** @test */
  public function testSeturlMethodWillSetTheUrlInTheOutputObject()
  {
    $result = $this->controller->setUrl($url = 'path/to');

    $this->assertTrue(isset($this->controller->obj->url));
    $this->assertSame($url, $this->controller->obj->url);
    $this->assertInstanceOf(Controller::class, $result);

    $this->controller->setUrl($url = 'new/path/to');

    $this->assertSame($url, $this->controller->obj->url);

  }

  /** @test */
  public function testSettitleMethodSetsTheTitleOnTheOutputObject()
  {
    $result = $this->controller->setTitle('foo');

    $this->assertTrue(isset($this->controller->obj->title));
    $this->assertSame('foo', $this->controller->obj->title);
    $this->assertInstanceOf(Controller::class, $result);

    $this->controller->setTitle('bar');

    $this->assertSame('bar', $this->controller->obj->title);
  }

  /** @test */
  public function testSeticonMethodSetsTheIconOnTheOutputObject()
  {
    $result = $this->controller->setIcon('icon_1');

    $this->assertTrue(isset($this->controller->obj->icon));
    $this->assertSame('icon_1', $this->controller->obj->icon);
    $this->assertInstanceOf(Controller::class, $result);

    $this->controller->setIcon('icon_2');

    $this->assertSame('icon_2', $this->controller->obj->icon);
  }

  /** @test */
  public function testSetcolorMethodSetsBackgroundAndFontColorsOnTheOutputObject()
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
  public function testRoutecomponentMethodReturnsComponentFromTheGivenNameIfExists()
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
  public function testGetcomponentMethodReturnsAComponentWithContentFromAGivenName()
  {
    // Cannot test this method in this case since it depends on the View class
    // and initialize it in the method itself
    $this->assertTrue(true);
  }

  /** @test */
  public function testGetcomponentMethodReturnsNullIfTheReturnedComponentHasNoJsInIt()
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
  public function testGetcomponentMethodReturnsNullIfAComponentCannotBeFound()
  {
    $this->mvc_mock->shouldReceive('routeComponent')
      ->once()
      ->with('not_found_component')
      ->andReturnNull();

    $this->assertNull($this->controller->getComponent('not_found_component'));
  }

  /** @test */
  public function testJsdataMethodSetsTheOutputObjectDataPropertyFromAnArray()
  {
    $result = $this->controller->jsData($data = ['key_1' => 'value_1', 'key_2' => 'value_2']);

    $this->assertTrue(isset($this->controller->obj->data));
    $this->assertSame($data, $this->controller->obj->data);
    $this->assertInstanceOf(Controller::class, $result);
  }

  /** @test */
  public function testJsdataMethodDontSetsTheOutputObjectDataPropertyIfTheArrayIsNotAssoc()
  {
    $result = $this->controller->jsData(['value_1', 'value_2']);

    $this->assertTrue(!isset($this->controller->obj->data->key_1));
    $this->assertTrue(!isset($this->controller->obj->data->key_2));
    $this->assertInstanceOf(Controller::class, $result);
  }

  /** @test */
  public function testJsdataMethodAddsToTheOutputObjectDataPropertyIfAlreadyExists()
  {
    $this->controller->obj->data = $existing_data = ['existing_key' => 'existing_value'];

    $result = $this->controller->jsData($data = ['key_1' => 'value_1', 'key_2' => 'value_2']);

    $this->assertSame(array_merge($existing_data, $data), $this->controller->obj->data);
    $this->assertInstanceOf(Controller::class, $result);
  }

  /** @test */
  public function testGetargumentsMethodParsesArgumentsFromAnArray()
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
  public function testGetviewMethodWillGetAView()
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
  public function testGetviewMethodWillGetAHtmlViewIfModeIsNotSpecified()
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
  public function testGetexternalviewMethodGetsAViewFromDifferentRoot()
  {
    $this->mvc_mock->shouldReceive('getExternalView')
      ->once()
      ->with('foo/bar', 'html', ['foo' => 'bar'])
      ->andReturn('view_content');

    $result = $this->controller->getExternalView('foo/bar', 'html', ['foo' => 'bar']);

    $this->assertSame('view_content', $result);
  }

  /** @test */
  public function testCustompluginviewMethodRetrievesAViewFromCustomPlugin()
  {
    $this->mvc_mock->shouldReceive('customPluginView')
      ->once()
      ->with('foo/bar', 'html', ['foo' => 'bar'], 'custom_plugin')
      ->andReturn('view_content');

    $result = $this->controller->customPluginView('foo/bar', 'html', ['foo' => 'bar'], 'custom_plugin');

    $this->assertSame('view_content', $result);
  }

  /** @test */
  public function testCustompluginviewMethodRetrievesAViewFromCurrentPluginIfPluginIsNotProvided()
  {
    $this->mvc_mock->shouldReceive('customPluginView')
      ->once()
      ->with('foo/bar', 'html', ['foo' => 'bar'], $this->getNonPublicProperty('_plugin'))
      ->andReturn('view_content');

    $result = $this->controller->customPluginView('foo/bar', 'html', ['foo' => 'bar']);

    $this->assertSame('view_content', $result);
  }

  /** @test */
  public function testCustompluginviewMethodReturnsNullWhenPluginIsNotSet()
  {
    $this->setNonPublicPropertyValue('_plugin', null);

    $this->mvc_mock->shouldNotReceive('customPluginView');

    $result = $this->controller->customPluginView('foo/bar', 'html', ['foo' => 'bar']);

    $this->assertNull($result);
  }

  /** @test */
  public function testGetpluginviewMethodRetrievesAView()
  {
    $this->mvc_mock->shouldReceive('getPluginView')
      ->once()
      ->with('foo/bar', 'html', ['foo' => 'bar'], $this->getNonPublicProperty('_plugin'))
      ->andReturn('view_content');

    $result = $this->controller->getPluginView('foo/bar', 'html', ['foo' => 'bar']);

    $this->assertSame('view_content', $result);
  }

  /** @test */
  public function testGetpluginviewsMethodReturnsAnArrayOfViews()
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
  public function testGetpluginmodelMethodReturnsAModeOfTheProvidedPlugin()
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
  public function testGetpluginmodelMethodReturnsAModeOfTheCurrentPluginIfNoPluginProvided()
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
  public function testGetsubpluginmodelMethodReturnsASubPluginModel()
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
  public function testGetsubpluginmodelMethodReturnsASubPluginModelOfTheCurrentPluginIfNoPluginProvided()
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
  public function testHassubpluginmodelMethodReturnsTrueIfTheSubPluginModelExists()
  {
    $this->mvc_mock->shouldReceive('hasSubpluginModel')
      ->once()
      ->with('foo/bar', 'plugin', 'sub_plugin')
      ->andReturnTrue();

    $result = $this->controller->hasSubpluginModel('foo/bar', 'plugin', 'sub_plugin');

    $this->assertTrue($result);
  }

  /** @test */
  public function testRetrievevarMethod()
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
  public function testActionMethodMergesPostDataAndResultDataWithTheCurrentDataAndSetsTheOutputObject()
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
  public function testActionMethodReturnsADefaultResultIfGetModelFails()
  {
    $this->controller = Mockery::mock(Controller::class)->makePartial();
    $this->controller->shouldReceive('getModel')->once()->andReturnFalse();

    $this->controller->action();

    $this->assertSame(['res' => ['success' => false]], $this->controller->data);
  }
  
  /** @test */
  public function testCachedactionMethodMergesPostDataAndResultDataWithTheCurrentDataAndSetsTheOutputObject()
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
  public function testComboMethodCompilesAndEchoesAllTheViewsWithTheGivenData()
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
  public function testGetcontentMethodReturnsTheContentOfAFileLocatedWithinTheDataPath()
  {
    $file_path = self::createFile('test.txt',  'Hello world!', 'controllers');
    $file_path = str_replace(BBN_APP_PATH . BBN_DATA_PATH, '',$file_path);

    $this->assertSame('Hello world!', $this->controller->getContent($file_path));

    unlink(BBN_APP_PATH . BBN_DATA_PATH . $file_path);
  }

  /** @test */
  public function testGetcontentMethodReturnsFalseIfPathIsNotValid()
  {
    $this->assertFalse($this->controller->getContent('foo/bar'));
  }

  /** @test */
  public function testGetdirMethodReturnsThePathToTheDirectoryOfTheCurrentController()
  {
    $this->assertSame(
      $this->getNonPublicProperty('_dir'),
      $this->controller->getDir()
    );
  }

  /** @test */
  public function testGetprepathMethodReturnsPrePathFromMvcObject()
  {
    $this->mvc_mock->shouldReceive('getPrepath')
      ->once()
      ->andReturn('foo');

    $this->assertSame('foo', $this->controller->getPrepath());
  }

  /** @test */
  public function testGetprepathMethodReturnsEmptyStringIfPathPropertyIsEmpty()
  {
    $this->setNonPublicPropertyValue('_path', '');

    $this->assertSame('', $this->controller->getPrepath());
  }

  /** @test */
  public function testSetprepathMethodSetsThePrepath()
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
  public function testSetprepathMethodThrowsAnExceptionWhenThePathPropertyIsEmpty()
  {
    $this->expectException(\Exception::class);

    $this->setNonPublicPropertyValue('_path', '');

    $this->controller->setPrepath('foo/bar');
  }

  /** @test */
  public function testSetprepathMethodThrowsAnExceptionWhenSetprepathOnMvcObjectThrowsException()
  {
    $this->expectException(\Exception::class);

    $this->mvc_mock->shouldReceive('setPrepath')
      ->once()
      ->with('foo/bar')
      ->andThrows(\Exception::class);

    $this->controller->setPrepath('foo/bar');
  }

  /** @test */
  public function testGetmodelMethodReturnsTheModelUsingControllerDataAndPathWhenNoArgumentsProvided()
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
  public function testGetmodelMethodReturnsTheModelUsingTheProvidedPath()
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
  public function testGetmodelMethodReturnsTheModelUsingTheProvidedData()
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
  public function testGetmodelMethodReturnsTheModelUsingTheProvidedPathAndData()
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
  public function testGetmodelMethodReturnsTheModelWhenPathIsNotProvidedAndModeIsDom()
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
  public function testGetmodelMethodReturnsTheModelWhenPathIsProvidedAndItStartsWithADotAndABackslash()
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
  public function testGetmodelMethodReturnsTheModelWhenTheReturnedModelIsAnObject()
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
  public function testGetmodelMethodThrowsAnExceptionWhenTrueIsProvidedAndReturnedModelIsNotAnArray()
  {
    $this->expectException(\Exception::class);

    $this->mvc_mock->shouldReceive('getModel')
      ->once()
      ->andReturn('foo');

    $this->controller->getModel(true);
  }

  /** @test */
  public function testGetmodelMethodReturnsAnEmptyArrayWhenFalseIsProvidedAndReturnedModelIsNotAnArray()
  {
    $this->mvc_mock->shouldReceive('getModel')
      ->once()
      ->andReturn('foo');

    $result = $this->controller->getModel(false);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /** @test */
  public function testGetcachedmodelMethodReturnsTheCachedModelUsingControllerDataAndPathAndZeroTtlWhenNoArgumentsProvided()
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
  public function testGetcachedmodelMethodReturnsTheCachedModelUsingTheProvidedPath()
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
  public function testGetcachedmodelMethodReturnsTheCachedModelUsingTheProvidedData()
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
  public function testGetcachedmodelMethodReturnsTheCachedModelUsingTheTtl()
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
  public function testGetcachedmodelMethodReturnsTheModelUsingTheProvidedPathAndDataAndTtl()
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
  public function testGetcachedmodelMethodReturnsTheCachedModelWhenPathIsProvidedAndItStartsWithADotAndABackslash()
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
  public function testGetcachedmodelMethodReturnsTheCachedModelWhenTheReturnedModelIsAnObject()
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
  public function testGetcachedmodelMethodThrowsAnExceptionWhenTrueIsProvidedAndReturnedCachedModelIsNotAnArray()
  {
    $this->expectException(\Exception::class);

    $this->mvc_mock->shouldReceive('getCachedModel')
      ->once()
      ->andReturn('foo');

    $this->controller->getCachedModel(true);
  }

  /** @test */
  public function testGetcachedmodelMethodReturnsAnEmptyArrayWhenFalseIsProvidedAndReturnedCachedModelIsNotAnArray()
  {
    $this->mvc_mock->shouldReceive('getCachedModel')
      ->once()
      ->andReturn('foo');

    $result = $this->controller->getCachedModel(false);

    $this->assertIsArray($result);
    $this->assertEmpty($result);
  }

  /** @test */
  public function testDeletecachedmodelMethodWillDeleteTheCachedModelUsingControllerDataAndPathWhenNoArgumentsProvided()
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
  public function testDeletecachedmodelMethodWillDeleteTheCachedModelUsingTheProvidedPath()
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
  public function testDeletecachedmodelMethodWillDeleteTheCachedModelUsingTheProvidedData()
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
  public function testDeletecachedmodelMethodWillDeleteTheCachedModelUsingTheProvidedPathAndData()
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
  public function testDeletecachedmodelMethodWillDeleteTheCachedModelWhenPathIsProvidedAndItStartsWithADotAndABackslash()
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

  /** @test */
  public function testSetcachedmodelMethodSetsTheCachedModelUsingControllerPathAndDataIfNoArgumentsProvided()
  {
    $this->mvc_mock->shouldReceive('setCachedModel')
      ->once()
      ->with(
        $this->getNonPublicProperty('_path'),
        $this->getNonPublicProperty('data'),
        $this->controller,
        10
      );

    $this->assertInstanceOf(Controller::class, $this->controller->setCachedModel());
  }

  /** @test */
  public function testSetcachedmodelMethodSetsTheCachedModelUsingTheProvidedPath()
  {
    $this->mvc_mock->shouldReceive('setCachedModel')
      ->once()
      ->with(
        'custom/path',
        $this->getNonPublicProperty('data'),
        $this->controller,
        10
      );

    $this->assertInstanceOf(Controller::class, $this->controller->setCachedModel('custom/path'));
  }

  /** @test */
  public function testSetcachedmodelMethodSetsTheCachedModelUsingTheProvidedData()
  {
    $this->mvc_mock->shouldReceive('setCachedModel')
      ->once()
      ->with(
        $this->getNonPublicProperty('_path'),
        ['foo' => 'bar'],
        $this->controller,
        10
      );

    $this->assertInstanceOf(Controller::class, $this->controller->setCachedModel(['foo' => 'bar']));
  }

  /** @test */
  public function testSetcachedmodelMethodSetsTheCachedModelUsingTheProvidedTtl()
  {
    $this->mvc_mock->shouldReceive('setCachedModel')
      ->once()
      ->with(
        $this->getNonPublicProperty('_path'),
        $this->getNonPublicProperty('data'),
        $this->controller,
        333
      );

    $this->assertInstanceOf(Controller::class, $this->controller->setCachedModel(333));
  }

  /** @test */
  public function testSetcachedmodelMethodSetsTheCachedModelUsingTheProvidedPathAndDataAndTtl()
  {
    $this->mvc_mock->shouldReceive('setCachedModel')
      ->once()
      ->with(
        'custom/path',
        ['foo' => 'bar'],
        $this->controller,
        333
      );

    $this->assertInstanceOf(
      Controller::class,
      $this->controller->setCachedModel('custom/path', ['foo' => 'bar'], 333)
    );
  }

  /** @test */
  public function testSetcachedmodelMethodSetsTheCachedModelWhenPathIsProvidedAndItStartsWithADotAndABackslash()
  {
    $this->mvc_mock->shouldReceive('setCachedModel')
      ->once()
      ->with(
        dirname($this->getNonPublicProperty('_path')) . '/custom_path',
        $this->getNonPublicProperty('data'),
        $this->controller,
        10
      );

    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('');

    $this->assertInstanceOf(Controller::class, $this->controller->setCachedModel('./custom_path'));
  }

  /** @test */
  public function testGetobjectmodelMethodReturnsTheModel()
  {
    $this->mvc_mock->shouldReceive('getModel')
      ->once()
      ->andReturn(['foo' => 'bar']);

    $result = $this->controller->getObjectModel();

    $this->assertIsObject($result);
    $this->assertTrue(isset($result->foo));
    $this->assertSame('bar', $result->foo);
  }

  /** @test */
  public function testGetobjectmodelMethodReturnsTheCachedModel()
  {
    $this->mvc_mock->shouldReceive('getCachedModel')
      ->once()
      ->andReturn(['foo' => 'bar']);

    $result = $this->controller->getObjectModel(333);

    $this->assertIsObject($result);
    $this->assertTrue(isset($result->foo));
    $this->assertSame('bar', $result->foo);
  }

  /** @test */
  public function testGetobjectmodelMethodReturnsAnEmptyStdclassObjectWhenModelResultIsEmpty()
  {
    $this->mvc_mock->shouldReceive('getModel')
      ->once()
      ->andReturn([]);

    $result = $this->controller->getObjectModel();

    $this->assertInstanceOf(\stdClass::class, $result);
    $this->assertFalse(isset($result->foo));
  }

  /** @test */
  public function testGetobjectmodelMethodReturnsAnEmptyStdclassObjectWhenCachedModelResultIsEmpty()
  {
    $this->mvc_mock->shouldReceive('getCachedModel')
      ->once()
      ->andReturn([]);

    $result = $this->controller->getObjectModel(222);

    $this->assertInstanceOf(\stdClass::class, $result);
    $this->assertFalse(isset($result->foo));
  }

  /** @test */
  public function testGetobjectmodelMethodThrowsAnExceptionWhenTheModelResultIsNotAnArrayAndTrueIsProvided()
  {
    $this->expectException(\Exception::class);

    $this->mvc_mock->shouldReceive('getModel')
      ->once()
      ->andReturn('foo');

    $this->controller->getObjectModel(true);
  }

  /** @test */
  public function testGetobjectmodelMethodThrowsAnExceptionWhenTheCachedModelResultIsNotAnArrayAndTrueIsProvided()
  {
    $this->expectException(\Exception::class);

    $this->mvc_mock->shouldReceive('getCachedModel')
      ->once()
      ->andReturn('foo');

    $this->controller->getObjectModel(111, true);
  }

  /** @test */
  public function testGetobjectmodelMethodReturnsAnEmptyStdclassObjectWhenTheModelResultIsNotAnArrayAndFalseIsProvided()
  {
    $this->mvc_mock->shouldReceive('getModel')
      ->once()
      ->andReturn('foo');

    $result = $this->controller->getObjectModel(false);

    $this->assertInstanceOf(\stdClass::class, $result);
    $this->assertFalse(isset($result->foo));
  }

  /** @test */
  public function testGetobjectmodelMethodReturnsAnEmptyStdclassObjectWhenTheCachedModelResultIsNotAnArrayAndFalseIsProvided()
  {
    $this->mvc_mock->shouldReceive('getCachedModel')
      ->once()
      ->andReturn('foo');

    $result = $this->controller->getObjectModel(444, false);

    $this->assertInstanceOf(\stdClass::class, $result);
    $this->assertFalse(isset($result->foo));
  }

  /** @test */
  public function testGetobjectmodelMethodReturnsNullWhenTheModelResultIsNotAnObject()
  {
    $this->controller = Mockery::mock(Controller::class)->makePartial();

    $this->controller->shouldReceive('getModel')
      ->once()
      ->andReturn(['foo', 'bar']);

    $this->assertNull($this->controller->getObjectModel());
  }

  /** @test */
  public function testGetobjectmodelMethodReturnsNullWhenTheCachedModelResultIsNotAnObject()
  {
    $this->controller = Mockery::mock(Controller::class)->makePartial();

    $this->controller->shouldReceive('getCachedModel')
      ->once()
      ->andReturn(['foo', 'bar']);

    $this->assertNull($this->controller->getObjectModel(12));
  }

  /** @test */
  public function testAddincMethodAddsAPropertyToTheMvcObjectIncIfItHasNotBeenDeclared()
  {
    $this->mvc_mock->shouldReceive('addInc')
      ->once()
      ->with('name', $obj = (object)['foo' => 'bar']);

    $this->assertInstanceOf(Controller::class, $this->controller->addInc('name', $obj));
  }

  /** @test */
  public function testHasargumentsMethodTest()
  {
    $this->setNonPublicPropertyValue('arguments', ['foo', 'bar']);
    $arguments = $this->getNonPublicProperty('arguments');

    $this->assertIsArray($this->getNonPublicProperty('arguments'));
    $this->assertCount(2, $arguments);

    $this->assertTrue($this->controller->hasArguments(1));
    $this->assertTrue($this->controller->hasArguments(2));
    $this->assertFalse($this->controller->hasArguments(3));
    $this->assertFalse($this->controller->hasArguments(11));
  }

  /* @test */
  public function testGetMethodReturnsTheOutputObject()
  {
    $this->assertSame(
      $this->controller->obj,
      $this->controller->get()
    );
  }

  /** @test */
  public function testTransformMethodTransformsTheOutputObjectUsingACallback()
  {
    $this->controller->obj->foo = 'bar';

    $this->controller->transform(function ($obj) {
      $obj->foo = 'baz';

      return $obj;
    });

    $this->assertSame('baz', $this->controller->obj->foo);
  }

  /** @test */
  public function testHasdataMethodChecksIfDataExistsOrASpecificIndexExists()
  {
    $this->controller->data = [
      'foo'       => 'bar',
      'empty_key' => ''
    ];

    $this->assertTrue($this->controller->hasData('foo'));
    $this->assertFalse($this->controller->hasData(['foo', 'baz']));
    $this->assertFalse($this->controller->hasData('baz'));
  }

  /** @test */
  public function testHasdataMethodChecksIfTheGivenIndexAndNotEmpty()
  {
    $this->controller->data = [
      'empty_key' => ''
    ];

    $this->assertFalse($this->controller->hasData('empty_key', true));
    $this->assertTrue($this->controller->hasData('empty_key'));
  }

  /** @test */
  public function testHasdataMethodReturnsFalseWhenDataIsEmpty()
  {
    $this->controller->data = [];

    $this->assertFalse($this->controller->hasData('foo'));
  }

  /** @test */
  public function testHasdataMethodChecksIfDataIsEmptyOrNotWhenTheProvidedIndexIsNull()
  {
    $this->controller->data = ['foo' => 'bar'];
    $this->assertTrue($this->controller->hasData(null));

    $this->controller->data = [];
    $this->assertFalse($this->controller->hasData(null));
  }

  /** @test */
  public function testHascontentMethodChecksIfTheObjectHasAnyHtmlContent()
  {
    $this->controller->obj->content = 'html_content';
    $this->assertTrue($this->controller->hasContent());

    $this->controller->obj->content = '';
    $this->assertFalse($this->controller->hasContent());

    unset($this->controller->obj->content);
    $this->assertFalse($this->controller->hasContent());

    $this->controller->obj = ['foo' => 'bar'];
    $this->assertFalse($this->controller->hasContent());

    $this->controller->obj = 'foo';
    $this->assertFalse($this->controller->hasContent());
  }

  /** @test */
  public function testGetrenderedMethodReturnsTheRenderedResultsFromTheCurrentMvcIfSuccessfullyProcessed()
  {
    $this->controller->obj->content = 'html_content';
    $this->assertSame('html_content', $this->controller->getRendered());
  }

  /** @test */
  public function testGetrenderedMethodReturnsTheRenderedFalseIfTheHtmlContentDoesNotExists()
  {
    unset($this->controller->obj->content);
    $this->assertFalse($this->controller->getRendered());
  }

  /** @test */
  public function testGetmodeMethodReturnTheCurrentMode()
  {
    $this->assertSame($this->getNonPublicProperty('mode'), $this->controller->getMode());
  }

  /** @test */
  public function testSetmodeMethodSetsTheCurrentMode()
  {
    $this->mvc_mock->shouldReceive('setMode')
      ->once()
      ->with('css')
      ->andReturn('css');

    $this->assertInstanceOf(Controller::class, $this->controller->setMode('css'));
    $this->assertSame('css', $this->getNonPublicProperty('mode'));
  }

  /** @test */
  public function testSetmodeMethodDoesNotSetTheCurrentModeIfModeDoesNotExist()
  {
    $this->mvc_mock->shouldReceive('setMode')
      ->once()
      ->with('unknown_mode')
      ->andReturnNull();

    $this->assertInstanceOf(Controller::class, $this->controller->setMode('unknown_mode'));
    $this->assertNotSame('unknown_mode', $this->getNonPublicProperty('mode'));
  }

  /** @test */
  public function testGetscriptMethodReturnsTheRenderedScriptResultFormTheCurrentMvcIfSuccessfullyProcessed()
  {
    $this->controller->obj->script = 'script_content';
    $this->assertSame('script_content', $this->controller->getScript());
  }

  /** @test */
  public function testGetscriptMethodReturnsAnEmptyStringIfTheScriptContentDoesNotExists()
  {
    unset($this->controller->obj->script);
    $this->assertSame('', $this->controller->getScript());
  }

  /** @test */
  public function testSetdataMethodSetsTheData()
  {
    $this->controller->data = [];

    $result = $this->controller->setData(['foo' => 'bar']);

    $this->assertSame(['foo' => 'bar'], $this->controller->data);
    $this->assertInstanceOf(Controller::class, $result);
  }

  /** @test */
  public function testAdddataMethodMergesTheGivenDataWithTheExistingOne()
  {
    $this->controller->data  = [];

    $result = $this->controller->addData(['foo' => 'bar']);

    $this->assertInstanceOf(Controller::class, $result);
    $this->assertSame(['foo' => 'bar'], $this->controller->data);

    $this->controller->addData(['foo2' => 'bar2']);
    $this->assertSame(
      ['foo' => 'bar', 'foo2' => 'bar2'],
      $this->controller->data
    );

    $this->controller->addData(['foo3' => 'bar3'], ['foo4', 'bar4']);
    $this->assertSame(
      ['foo' => 'bar', 'foo2' => 'bar2', 'foo3' => 'bar3', 'foo4', 'bar4'],
      $this->controller->data
    );
  }

  /** @test */
  public function testAdddataMethodDoesNotMergeTheGivenDataWithTheExistingOneIfItIsNotAnArray()
  {
    $this->controller->data  = [];

    $result = $this->controller->addData(['foo3' => 'bar3'], 'string', (object)['key' => 'value'], 123);

    $this->assertInstanceOf(Controller::class, $result);
    $this->assertSame(['foo3' => 'bar3'], $this->controller->data);
  }

  /** @test */
  public function testAddMethodReturnsANewControllerInstanceWithTheGivenArgumentsAndModeIsPublic()
  {
    $this->mvc_mock->shouldReceive('getRoute')
      ->once()
      ->with('new/path', 'public')
      ->andReturn($new_info = [
        'mode'      => 'css',
        'path'      => 'path/to/plugin',
        'file'      => './tests/storage/controllers/home.php',
        'request'   => 'get',
        'root'      => './tests/',
        'plugin'    => 'new_plugin',
        'args'      => [
          'foo', 'bar'
        ],
        'checkers'  => []
      ]);

    // The process method calls the control method
    // So let's mock the Mvc calls inside it like we did in the previous two tests.
    $this->mvc_mock->shouldReceive('pluginName')->with($new_info['plugin'])->once()->andReturn('plugin_name');
    $this->mvc_mock->shouldReceive('pluginPath')->with('plugin_name', false)->once()->andReturn(BBN_APP_PATH . BBN_DATA_PATH . 'plugin_path/');
    $this->mvc_mock->shouldReceive('isCli')->once()->andReturnFalse();

    $this->mvc_mock->shouldReceive('getParams')->once()->andReturn(['foo2' => 'bar2']);

    $router_mock = $this->mockClassMethod(
      Mvc\Router::class, function ($mock) {
      $mock->shouldReceive('getLocaleDomain')->once()->andReturn('custom');
    }
    );

    $file_stub = '<?php 
  echo "html_content";
';
    $file_path = $this->createFile('home.php', $file_stub, 'controllers');

    $this->setNonPublicPropertyValue('retriever_instance', $router_mock, Mvc\Router::class);

    $result = $this->controller->add('new/path', ['foo' => 'bar']);

    $this->assertInstanceOf(Controller::class, $result);
    $this->assertSame(['foo' => 'bar'], $result->data);
    $this->assertTrue(isset($result->obj->content));
    $this->assertSame('html_content', $result->obj->content);
    unlink($file_path);
  }

  /** @test */
  public function testAddMethodReturnsANewControllerInstanceWithTheGivenArgumentsAndModeIsPrivate()
  {
    $this->mvc_mock->shouldReceive('getRoute')
      ->once()
      ->with('new/path', 'private')
      ->andReturn($new_info = [
        'mode'      => 'css',
        'path'      => 'path/to/plugin',
        'file'      => './tests/storage/controllers/home.php',
        'request'   => 'get',
        'root'      => './tests/',
        'plugin'    => 'new_plugin',
        'args'      => [
          'foo', 'bar'
        ],
        'checkers'  => []
      ]);

    /**
     * Here will set expectation for calling the control() method on the new instance of the Controller class.
     *
     * @see control_method_encloses_controller_inclusion
     */
    $this->mvc_mock->shouldReceive('pluginName')->with($new_info['plugin'])->once()->andReturn('plugin_name');
    $this->mvc_mock->shouldReceive('pluginPath')->with('plugin_name', false)->once()->andReturn(BBN_APP_PATH . BBN_DATA_PATH . 'plugin_path/');
    $this->mvc_mock->shouldReceive('isCli')->once()->andReturnFalse();

    $this->mvc_mock->shouldReceive('getParams')->once()->andReturn(['foo2' => 'bar2']);

    $router_mock = $this->mockClassMethod(
      Mvc\Router::class, function ($mock) {
      $mock->shouldReceive('getLocaleDomain')->once()->andReturn('custom');
    }
    );

    $file_stub = '<?php 
  echo "html_content";
';
    $file_path = $this->createFile('home.php', $file_stub, 'controllers');

    $this->setNonPublicPropertyValue('retriever_instance', $router_mock, Mvc\Router::class);

    // Call the method being tested
    $result = $this->controller->add('new/path', ['foo' => 'bar'], true);

    $this->assertInstanceOf(Controller::class, $result);
    $this->assertSame(['foo' => 'bar'], $result->data);
    $this->assertTrue(isset($result->obj->content));
    $this->assertSame('html_content', $result->obj->content);
    unlink($file_path);
  }

  /** @test */
  public function testAddMethodReturnsANewControllerInstanceWithTheGivenArgumentsWithAlteringThePathIfItContainsADotAndBackslashAtTheBeginning()
  {
    $this->mvc_mock->shouldReceive('getRoute')
      ->once()
      ->with('path/to/new/path', 'public')
      ->andReturn($new_info = [
        'mode'      => 'css',
        'path'      => 'path/to/plugin',
        'file'      => './tests/storage/controllers/home.php',
        'request'   => 'get',
        'root'      => './tests/',
        'plugin'    => 'new_plugin',
        'args'      => [
          'foo', 'bar'
        ],
        'checkers'  => []
      ]);

    /**
     * Here will set expectation for calling the control() method on the new instance of the Controller class.
     *
     * @see control_method_encloses_controller_inclusion
     */
    $this->mvc_mock->shouldReceive('pluginName')->with($new_info['plugin'])->once()->andReturn('plugin_name');
    $this->mvc_mock->shouldReceive('pluginPath')->with('plugin_name', false)->once()->andReturn(BBN_APP_PATH . BBN_DATA_PATH . 'plugin_path/');
    $this->mvc_mock->shouldReceive('isCli')->once()->andReturnFalse();

    $this->mvc_mock->shouldReceive('getParams')->once()->andReturn(['foo2' => 'bar2']);

    // Mock the router class so that the getLocaleDomain return a string that we can test.
    $router_mock = $this->mockClassMethod(
      Mvc\Router::class, function ($mock) {
      $mock->shouldReceive('getLocaleDomain')->once()->andReturn('custom');
    }
    );

    $file_stub = '<?php 
  echo "html_content";
';
    $file_path = $this->createFile('home.php', $file_stub, 'controllers');

    $this->setNonPublicPropertyValue('retriever_instance', $router_mock, Mvc\Router::class);

    // Set expectation for calling the getPrepath() method when calling the getCurrentDir() method
    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('');

    // Call the method being tested
    $result = $this->controller->add('./new/path', ['foo' => 'bar']);

    $this->assertInstanceOf(Controller::class, $result);
    $this->assertSame(['foo' => 'bar'], $result->data);
    $this->assertTrue(isset($result->obj->content));
    $this->assertSame('html_content', $result->obj->content);
    unlink($file_path);
  }

  /** @test */
  public function testAddMethodReturnsFalseIfGetrouteOnMvcObjectReturnsNull()
  {
    $this->mvc_mock->shouldReceive('getRoute')
      ->once()
      ->with('new/path', 'public')
      ->andReturnNull();

    $this->assertFalse($this->controller->add('new/path'));
  }

  /** @test */
  public function testAddtoobjMethodCreatesANewControllerInstanceWithTheGivenArgumentsAndMergesItsObjectWithTheExistingOneAndModeIsPublic()
  {
    $this->controller->obj->css = 'css_content';

    $this->mvc_mock->shouldReceive('getRoute')
      ->once()
      ->with('new/path', 'public')
      ->andReturn($new_info = [
        'mode'      => 'css',
        'path'      => 'path/to/plugin',
        'file'      => './tests/storage/controllers/home.php',
        'request'   => 'get',
        'root'      => './tests/',
        'plugin'    => 'new_plugin',
        'args'      => [
          'foo', 'bar'
        ],
        'checkers'  => []
      ]);

    /**
     * Here will set expectation for calling the control() method on the new instance of the Controller class.
     *
     * @see control_method_encloses_controller_inclusion
     */
    $this->mvc_mock->shouldReceive('pluginName')->with($new_info['plugin'])->once()->andReturn('plugin_name');
    $this->mvc_mock->shouldReceive('pluginPath')->with('plugin_name', false)->once()->andReturn(BBN_APP_PATH . BBN_DATA_PATH . 'plugin_path/');
    $this->mvc_mock->shouldReceive('isCli')->once()->andReturnFalse();

    $this->mvc_mock->shouldReceive('getParams')->once()->andReturn(['foo2' => 'bar2']);

    // Mock the router class so that the getLocaleDomain return a string that we can test.
    $router_mock = $this->mockClassMethod(
      Mvc\Router::class, function ($mock) {
      $mock->shouldReceive('getLocaleDomain')->once()->andReturn('custom');
    }
    );

    $file_stub = '<?php 
  echo  "html_content";
';
    $file_path = $this->createFile('home.php', $file_stub, 'controllers');

    $this->setNonPublicPropertyValue('retriever_instance', $router_mock, Mvc\Router::class);

    $result = $this->controller->addToObj('new/path', ['foo' => 'bar']);

    $this->assertInstanceof(Controller::class, $result);

    $this->assertTrue(isset($this->controller->obj->css));
    $this->assertSame('css_content' ,$this->controller->obj->css);

    $this->assertTrue(isset($this->controller->obj->content));
    $this->assertSame('html_content', $this->controller->obj->content);

    unlink($file_path);
  }

  /** @test */
  public function testAddtoobjMethodCreatesANewControllerInstanceWithTheGivenArgumentsAndMergesItsObjectWithTheExistingOneAndModeIsPrivate()
  {
    $this->controller->obj->css = 'css_content';

    $this->mvc_mock->shouldReceive('getRoute')
      ->once()
      ->with('new/path', 'private')
      ->andReturn($new_info = [
        'mode'      => 'css',
        'path'      => 'path/to/plugin',
        'file'      => './tests/storage/controllers/home.php',
        'request'   => 'get',
        'root'      => './tests/',
        'plugin'    => 'new_plugin',
        'args'      => [
          'foo', 'bar'
        ],
        'checkers'  => []
      ]);

    /**
     * Here will set expectation for calling the control() method on the new instance of the Controller class.
     *
     * @see control_method_encloses_controller_inclusion
     */
    $this->mvc_mock->shouldReceive('pluginName')->with($new_info['plugin'])->once()->andReturn('plugin_name');
    $this->mvc_mock->shouldReceive('pluginPath')->with('plugin_name', false)->once()->andReturn(BBN_APP_PATH . BBN_DATA_PATH . 'plugin_path/');
    $this->mvc_mock->shouldReceive('isCli')->once()->andReturnFalse();

    $this->mvc_mock->shouldReceive('getParams')->once()->andReturn(['foo2' => 'bar2']);

    // Mock the router class so that the getLocaleDomain return a string that we can test.
    $router_mock = $this->mockClassMethod(
      Mvc\Router::class, function ($mock) {
      $mock->shouldReceive('getLocaleDomain')->once()->andReturn('custom');
    }
    );

    $file_stub = '<?php 
  echo  "html_content";
';
    $file_path = $this->createFile('home.php', $file_stub, 'controllers');

    $this->setNonPublicPropertyValue('retriever_instance', $router_mock, Mvc\Router::class);

    $result = $this->controller->addToObj('new/path', ['foo' => 'bar'], true);

    $this->assertInstanceof(Controller::class, $result);

    $this->assertTrue(isset($this->controller->obj->css));
    $this->assertSame('css_content' ,$this->controller->obj->css);

    $this->assertTrue(isset($this->controller->obj->content));
    $this->assertSame('html_content', $this->controller->obj->content);

    unlink($file_path);
  }

  /** @test */
  public function testAddtoobjMethodCreatesANewControllerInstancewithTheGivenArgumentsAndMergesItsObjectWithTheExistingOneWithAlteringThePathIfItContainsADotAndBackslashAtTheBeginning()
  {
    $this->controller->obj->css = 'css_content';

    $this->mvc_mock->shouldReceive('getRoute')
      ->once()
      ->with('path/to/new/path', 'public')
      ->andReturn($new_info = [
        'mode'      => 'css',
        'path'      => 'path/to/plugin',
        'file'      => './tests/storage/controllers/home.php',
        'request'   => 'get',
        'root'      => './tests/',
        'plugin'    => 'new_plugin',
        'args'      => [
          'foo', 'bar'
        ],
        'checkers'  => []
      ]);

    /**
     * Here will set expectation for calling the control() method on the new instance of the Controller class.
     *
     * @see control_method_encloses_controller_inclusion
     */
    $this->mvc_mock->shouldReceive('pluginName')->with($new_info['plugin'])->once()->andReturn('plugin_name');
    $this->mvc_mock->shouldReceive('pluginPath')->with('plugin_name', false)->once()->andReturn(BBN_APP_PATH . BBN_DATA_PATH . 'plugin_path/');
    $this->mvc_mock->shouldReceive('isCli')->once()->andReturnFalse();

    $this->mvc_mock->shouldReceive('getParams')->once()->andReturn(['foo2' => 'bar2']);

    // Mock the router class so that the getLocaleDomain return a string that we can test.
    $router_mock = $this->mockClassMethod(
      Mvc\Router::class, function ($mock) {
      $mock->shouldReceive('getLocaleDomain')->once()->andReturn('custom');
    }
    );

    $file_stub = '<?php 
  echo  "html_content";
';
    $file_path = $this->createFile('home.php', $file_stub, 'controllers');

    $this->setNonPublicPropertyValue('retriever_instance', $router_mock, Mvc\Router::class);

    $this->mvc_mock->shouldReceive('getPrepath')->once()->andReturn('');

    $result = $this->controller->addToObj('./new/path', ['foo' => 'bar']);

    $this->assertInstanceof(Controller::class, $result);

    $this->assertTrue(isset($this->controller->obj->css));
    $this->assertSame('css_content' ,$this->controller->obj->css);

    $this->assertTrue(isset($this->controller->obj->content));
    $this->assertSame('html_content', $this->controller->obj->content);

    unlink($file_path);
  }

  /** @test */
  public function testAddtoobjMethodThrowsAnExceptionWhenGetrouteOnMvcObjectReturnsNull()
  {
    $this->expectException(\Error::class);

    $this->mvc_mock->shouldReceive('getRoute')
      ->once()
      ->with('new/path', 'public')
      ->andReturnNull();

    $this->controller->addToObj('new/path');
  }

  /** @test */
  public function testGetresultMethodReturnsTheOutputObject()
  {
    $this->assertSame(
      $this->controller->obj,
      $this->controller->getResult()
    );
  }

  /** @test */
  public function testViewexistsMethodChecksWhetherTheGivenViewExsitsOrNot()
  {
    $this->mvc_mock->shouldReceive('viewExists')
      ->once()
      ->with('view/path', 'css')
      ->andReturnTrue();

    $this->assertTrue($this->controller->viewExists('view/path', 'css'));
  }

  /** @test */
  public function testModelexistsMethodChecksWhetherTheGivenModelExistsOrNot()
  {
    $this->mvc_mock->shouldReceive('modelExists')
      ->once()
      ->with('model/path')
      ->andReturnTrue();

    $this->assertTrue($this->controller->modelExists('model/path'));
  }

  /** @test */
  public function testIncludecontrollerMethodIncludesAControllerAndReturnsBooleanWhenCalledFromCli()
  {
    $this->mvc_mock->shouldReceive('isCli')->andReturnTrue();

    $file_stub = '<?php 
  echo  "content";
';
    $file_path = $this->createFile('home.php', $file_stub, 'controllers');

    $result = Controller::includeController($this->getNonPublicProperty('_file'), $this->controller);

    $this->assertTrue((bool)$result);

    unlink($file_path);
  }

  /** @test */
  public function testIncludecontrollerIncludesAControllerAndReturnsItsContentWhenNotCalledFromCli()
  {
    $this->mvc_mock->shouldReceive('isCli')->andReturnFalse();

    $file_stub = '<?php 
  echo  "content";
';
    $file_path = $this->createFile('home.php', $file_stub, 'controllers');

    $result = Controller::includeController($this->getNonPublicProperty('_file'), $this->controller);

    $this->assertSame('content', $result);

    unlink($file_path);
  }

  /** @test */
  public function testIncludecontrollerIncludesAControllerAndReturnsBooleanWhenNotCalledFromCliAndIsSuperIsTrue()
  {
    $this->mvc_mock->shouldReceive('isCli')->andReturnFalse();

    $file_stub = '<?php 
  echo  "content";
';
    $file_path = $this->createFile('home.php', $file_stub, 'controllers');

    $result = Controller::includeController($this->getNonPublicProperty('_file'), $this->controller, true);

    $this->assertTrue($result);

    unlink($file_path);
  }
}
