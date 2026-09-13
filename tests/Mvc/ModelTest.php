<?php

namespace bbn\tests\Mvc;

use bbn\Cache;
use bbn\Db;
use bbn\Mvc;
use bbn\Mvc\Controller;
use bbn\Mvc\Model;
use PHPUnit\Framework\TestCase;
use bbn\tests\Files;
use bbn\tests\Reflectable;

class ModelTest extends TestCase
{
  use Reflectable, Files;

  protected Model $model;

  protected $db_mock;

  protected $controller_mock;

  protected $mvc_mock;

  protected $model_file_path;

  protected $info = [
    'path'      => 'path/to/model',
    'file'      => './tests/storage/models/model.php',
    'checkers'  => []
  ];

  public function getInstance()
  {
    return $this->model;
  }

  protected function setUp(): void
  {

  }

  /**
   * @param array|null $info
   */
  protected function init(array $info = null)
  {
    $this->db_mock          = \Mockery::mock(Db::class);
    $this->controller_mock  = \Mockery::mock(Controller::class);
    $this->mvc_mock         = \Mockery::mock(Mvc::class);

    $this->mvc_mock->inc = (object)['foo' => 'bar'];

    $this->model = new Model($this->db_mock, $info ?? $this->info, $this->controller_mock, $this->mvc_mock);
  }

  /**
   * @param array|null $info
   */
  protected function initWithModelFile(array $info = null)
  {
    $this->model_file_path = $this->createFile(
      'model.php',
      '<?php return ["model_key" => "model_value", "text_domain" => textdomain(null)];',
      'models'
    );
    $this->init($info);
  }

  protected function tearDown(): void
  {
    \Mockery::close();

    if ($this->model_file_path) {
      unlink($this->model_file_path);
    }
  }

  /** @test */
  public function testConstructorTestWhenModelFileExists()
  {
    $this->initWithModelFile();

    $this->assertInstanceOf(Db::class, $this->getNonPublicProperty('db'));
    $this->assertInstanceOf(Controller::class, $this->getNonPublicProperty('_ctrl'));
    $this->assertInstanceOf(Mvc::class, $this->getNonPublicProperty('_mvc'));
    $this->assertSame($this->mvc_mock->inc, $this->model->inc);
    $this->assertSame($this->info['path'], $this->getNonPublicProperty('_path'));
    $this->assertSame($this->info['file'], $this->getNonPublicProperty('_file'));
    $this->assertSame($this->info['checkers'], $this->getNonPublicProperty('_checkers'));
    $this->assertInstanceOf(Cache::class, $this->getNonPublicProperty('cache_engine'));
    $this->assertSame('bbn/Mvc/Model/', $this->getNonPublicProperty('_cache_prefix'));
  }

  /** @test */
  public function testConstructorTestWhenModelFileDoesNotExists()
  {
    $this->init();

    $this->assertInstanceOf(Db::class, $this->getNonPublicProperty('db'));
    $this->assertInstanceOf(Controller::class, $this->getNonPublicProperty('_ctrl'));
    $this->assertInstanceOf(Mvc::class, $this->getNonPublicProperty('_mvc'));
    $this->assertSame($this->mvc_mock->inc, $this->model->inc);
    $this->assertNull($this->getNonPublicProperty('_path'));
    $this->assertNull($this->getNonPublicProperty('_file'));
    $this->assertNull($this->getNonPublicProperty('_checkers'));
    $this->assertInstanceOf(Cache::class, $this->getNonPublicProperty('cache_engine'));
    $this->assertSame('bbn/Mvc/Model/', $this->getNonPublicProperty('_cache_prefix'));
  }

  /** @test */
  public function testConstructorTestWhenDbIsNull()
  {
    $this->controller_mock  = \Mockery::mock(Controller::class);
    $this->mvc_mock         = \Mockery::mock(Mvc::class);

    $this->mvc_mock->inc = (object)['foo' => 'bar'];

    $this->model = new Model(null, $this->info, $this->controller_mock, $this->mvc_mock);

    $this->assertNull($this->getNonPublicProperty('db'));
    $this->assertInstanceOf(Controller::class, $this->getNonPublicProperty('_ctrl'));
    $this->assertInstanceOf(Mvc::class, $this->getNonPublicProperty('_mvc'));
    $this->assertSame($this->mvc_mock->inc, $this->model->inc);
    $this->assertNull($this->getNonPublicProperty('_path'));
    $this->assertNull($this->getNonPublicProperty('_file'));
    $this->assertNull($this->getNonPublicProperty('_checkers'));
    $this->assertInstanceOf(Cache::class, $this->getNonPublicProperty('cache_engine'));
    $this->assertSame('bbn/Mvc/Model/', $this->getNonPublicProperty('_cache_prefix'));
  }

  /** @test */
  public function testConstructorThrowsAnExceptionIfThePathDoesNotExistInTheProvidedInfo()
  {
    $this->expectException(\Exception::class);

    $this->init(['file' => 'foo']);
  }

  /** @test */
  public function testCheckactionMethodChecksIfActionExistsAndNotEmptyIfSpecified()
  {
    $this->init();

    $this->model->data['res'] = true;
    $this->model->data['foo'] = 'bar';
    $this->model->data['bar'] = '';

    $this->assertTrue($this->model->checkAction(['foo'], true));
    $this->assertFalse($this->model->checkAction(['bar'], true));
    $this->assertTrue($this->model->checkAction(['bar'], false));
    $this->assertTrue($this->model->checkAction());

    unset($this->model->data['res']);

    $this->assertFalse($this->model->checkAction(['foo'], true));
    $this->assertFalse($this->model->checkAction(['bar'], true));
    $this->assertFalse($this->model->checkAction(['bar'], false));
    $this->assertFalse($this->model->checkAction());
  }

  /** @test */
  public function testIscontrolledbyChecksWhetherIfCalledFromCliOrNotWhenTheGivenPathIsValidAndTypeIsCli()
  {
    $this->init();

    $this->controller_mock->shouldReceive('getPath')
      ->once()
      ->andReturn('path/to');

    $this->mvc_mock->shouldReceive('isCli')
      ->once()
      ->andREturnTrue();

    $this->assertTrue($this->model->isControlledBy('path/to', 'cli'));
  }

  /** @test */
  public function testIscontrolledbyReturnsTrueWhenTheGivenModeIsSameAsTheCurrentOneAndTheProvidedPathIsValidAndTypeIsNotCli()
  {
    $this->init();

    $this->controller_mock->shouldReceive('getPath')
      ->once()
      ->andReturn('path/to');

    $this->controller_mock->shouldReceive('getMode')
      ->once()
      ->andReturn('private');

    $this->assertTrue($this->model->isControlledBy('path/to', 'private'));
  }

  /** @test */
  public function testIscontrolledbyReturnsFalseWhenTheGivenModeIsNotTheSameAsTheCurrentOneAndTheProvidedPathIsValidAndTypeIsNotCli()
  {
    $this->init();

    $this->controller_mock->shouldReceive('getPath')
      ->once()
      ->andReturn('path/to');

    $this->controller_mock->shouldReceive('getMode')
      ->once()
      ->andReturn('public');

    $this->assertFalse($this->model->isControlledBy('path/to', 'private'));
  }

  /** @test */
  public function testIscontrolledbyMethodReturnsFalseWhenControllerInstanceIsNull()
  {
    $this->init();

    $this->setNonPublicPropertyValue('_ctrl', null);

    $this->assertFalse($this->model->isControlledBy('path/to'));
  }

  /** @test */
  public function testIscontrolledbyMethodReturnsFalseWhenTheGivenPathIsNotValid()
  {
    $this->init();

    $this->controller_mock->shouldReceive('getPath')
      ->once()
      ->andReturnNull();

    $this->assertFalse($this->model->isControlledBy('path/to'));
  }

  /** @test */
  public function testGetcontrollerpathMethodRetrievesControllerPathIfControllerInstanceExists()
  {
    $this->init();

    $this->controller_mock->shouldReceive('getPath')
      ->once()
      ->andReturn('path/to');

    $this->assertSame('path/to', $this->model->getControllerPath());
  }

  /** @test */
  public function testGetcontrollerpathMethodReturnsFalseIfControllerInstanceDoesNotExists()
  {
    $this->init();

    $this->setNonPublicPropertyValue('_ctrl', null);

    $this->assertFalse($this->model->getControllerPath());
  }

  /** @test */
  public function testHasvarMethodChecksIfTheDataPropertyHasTheGivenKeyAndNotEmptyIfSpecified()
  {
    $this->init();

    $this->model->data['foo'] = 'bar';
    $this->model->data['bar'] = '';

    $this->assertTrue($this->model->hasVar('foo'));
    $this->assertTrue($this->model->hasVar('bar'));
    $this->assertFalse($this->model->hasVar('bar', true));
  }

  /** @test */
  public function testHasvarsMethodChecksIfTheDataPropertyHasTheGivenKeysAndNotEmptyIfSpecified()
  {
    $this->init();

    $this->model->data['foo'] = 'bar';
    $this->model->data['bar'] = '';

    $this->assertTrue($this->model->hasVars(['foo']));
    $this->assertTrue($this->model->hasVars(['foo', 'bar']));
    $this->assertFalse($this->model->hasVars(['foo', 'bar', true]));
    $this->assertFalse($this->model->hasVars(['foo', 'bar', 'baz']));
  }

  /** @test */
  public function testRegisterpluginclassesMethodRegistersTheGivenClass()
  {
    $this->init();

    $this->controller_mock->shouldReceive('registerPluginClasses')
      ->with('path/to/plugin')
      ->once()
      ->andReturnSelf();

    $this->assertInstanceOf(Model::class, $this->model->registerPluginClasses('path/to/plugin'));
  }

  /** @test */
  public function testGetMethodTestWhenPluginIsNotSet()
  {
    $this->initWithModelFile();

    $result   = $this->model->get(['foo' => 'bar']);
    /**
     * Defined in @see initWithModelFile
     */
    $expected = ['model_key' => 'model_value', "text_domain" => textdomain(null)];

    $this->assertNull($this->getNonPublicProperty('_plugin'));
    $this->assertSame($expected, $result);
    $this->assertSame(['foo' => 'bar'], $this->model->data);
  }

  /** @test */
  public function testGetMethodTestWhenPluginIsSet()
  {
    $this->initWithModelFile();

    $this->setNonPublicPropertyValue('_plugin', 'plugin_name');

    // Mock the router class
    $router_mock = \Mockery::mock(Mvc\Router::class);

    // Then swap it to the retriever instance so can expectations can be set
    $this->setNonPublicPropertyValue('retriever_instance', $router_mock, Mvc\Router::class);
    $router_mock->shouldReceive('getLocaleDomain')
      ->once()
      ->with('plugin_name')
      ->andReturn('plugin_name');

    // Let's first make sure that current textdomain is different
    $this->assertNotSame('plugin_name', textdomain(null));

    $result   = $this->model->get(['foo' => 'bar']);
    /**
     * Defined in @see initWithModelFile
     */
    $expected = ['model_key' => 'model_value', "text_domain" => 'plugin_name']; // Now textdomain should be as we expecting while model is being included.

    $this->assertSame('plugin_name', $this->getNonPublicProperty('_plugin'));
    $this->assertSame($expected, $result);
    $this->assertSame(['foo' => 'bar'], $this->model->data);
    // Now let's make sure that textdomain is back to the old one after including the model.
    $this->assertNotSame('plugin_name', textdomain(null));
  }

  /** @test */
  public function testGetMethodReturnsNullWhenTheIncludedModelHasEmptyContent()
  {
    $this->model_file_path = $this->createFile(
      'model.php',
      '<?php return [];',
      'models'
    );
    $this->init();

    $result = $this->model->get(['foo' => 'bar']);

    $this->assertNull($result);
    $this->assertSame(['foo' => 'bar'], $this->model->data);
  }

  /** @test */
  public function testGetcontentMethodReturnsTheContentOfAFileLocatedWithinTheControllerDataPath()
  {
    $this->init();

    $this->controller_mock->shouldReceive('getContent')
      ->once()
      ->with('file_name')
      ->andReturn('file_content');

    $this->assertSame('file_content', $this->model->getContent('file_name'));
  }

  /** @test */
  public function testGetmodelMethodReturnsTheModelWithTheProvidedArguments()
  {
    $this->init();

    $this->controller_mock->shouldReceive('getModel')
      ->once()
      ->with('path/to', ['data_key' => 'data_value'])
      ->andReturn(['foo' => 'bar']);

    $this->assertSame(['foo' => 'bar'], $this->model->getModel('path/to', ['data_key' => 'data_value']));
  }

  /** @test */
  public function testGetcachedmodelMethodReturnsTheCachedModelWithTheProvidedArguments()
  {
    $this->init();

    $this->controller_mock->shouldReceive('getCachedModel')
      ->once()
      ->with('path/to', ['data_key' => 'data_value'])
      ->andReturn(['foo' => 'bar']);

    $this->assertSame(['foo' => 'bar'], $this->model->getCachedModel('path/to', ['data_key' => 'data_value']));
  }

  /** @test */
  public function testGetpluginmodelMethodRetrievesAModelOfThePlugin()
  {
    $this->init();

    $this->controller_mock->shouldReceive('getPluginModel')
      ->once()
      ->with('foo/bar', ['data_key' => 'data_value'], 'plugin_name', 2)
      ->andReturn(['foo' => 'bar']);

    $this->assertSame(
      ['foo' => 'bar'],
      $this->model->getPluginModel('foo/bar', ['data_key' => 'data_value'], 'plugin_name', 2)
    );
  }

  /** @test */
  public function testGetsubpluginmodelMethodReturnsASubPluginModelOfTheCurrentPlugin()
  {
    $this->init();

    $this->controller_mock->shouldReceive('getSubpluginModel')
      ->once()
      ->with('foo/bar', ['foo' => 'bar'], 'plugin_name', 'sub_plugin', 20)
      ->andReturn(['model' => ['key' => 'value']]);

    $this->assertSame(
      ['model' => ['key' => 'value']],
      $this->model->getSubpluginModel(
        'foo/bar',
        ['foo' => 'bar'],
        'plugin_name',
        'sub_plugin',
        20
      )
    );
  }

  /** @test */
  public function testHassubpluginmodelMethodReturnsTrueIfSubPluginModelExists()
  {
    $this->init();

    $this->controller_mock->shouldReceive('hasSubpluginModel')
      ->once()
      ->with('foo/bar', 'plugin_name', 'subplugin_name')
      ->andReturnTrue();

    $this->assertTrue(
      $this->model->hasSubpluginModel('foo/bar', 'plugin_name', 'subplugin_name')
    );
  }

  /** @test */
  public function testHaspluginMethodReturnsTrueIfTheGivenPluginExists()
  {
    $this->init();

    $this->controller_mock->shouldReceive('hasPlugin')
      ->once()
      ->with('plugin_name')
      ->andReturnTrue();

    $this->assertTrue($this->model->hasPlugin('plugin_name'));
  }

  /** @test */
  public function testIspluginMethodReturnsTrueIfTheGivenPluginExists()
  {
    $this->init();

    $this->controller_mock->shouldReceive('isPlugin')
      ->once()
      ->with('plugin_name')
      ->andReturnTrue();

    $this->assertTrue($this->model->isPlugin('plugin_name'));
  }

  /** @test */
  public function testPluginpathMethodReturnsThePathOfTheGivenPlugin()
  {
    $this->init();

    $this->controller_mock->shouldReceive('pluginPath')
      ->once()
      ->with('plugin_name')
      ->andReturn('path/to/plugin');

    $this->assertSame('path/to/plugin', $this->model->pluginPath('plugin_name'));
  }

  /** @test */
  public function testPluginurlMethodReturnsTheUrlPartOfTheGivenPlugin()
  {
    $this->init();

    $this->controller_mock->shouldReceive('pluginUrl')
      ->once()
      ->with('plugin_name')
      ->andReturn('http://foobar.baz');

    $this->assertSame('http://foobar.baz', $this->model->pluginUrl('plugin_name'));
  }

  /** @test */
  public function testAddincMethodAddsAPropertyToTheMvcObjectInc()
  {
    $this->init();

    $this->mvc_mock->shouldReceive('addInc')
      ->once()
      ->with('foo', $obj = (object)['bar' => 'baz']);

    $this->assertInstanceOf(Model::class, $this->model->addInc('foo', $obj));
  }

  /** @test */
  public function testHasdataMethodChecksIfDataExistsOrIfASpecificIndexExistsInTheDataAndChecksIfEmptyWhenSpecified()
  {
    $this->init();

    $this->model->data['foo'] = 'bar';
    $this->model->data['baz'] = '';

    $this->assertTrue($this->model->hasData('foo'));
    $this->assertTrue($this->model->hasData('baz'));
    $this->assertFalse($this->model->hasData('baz', true));
  }

  /** @test */
  public function testHasdataMethodReturnsFalseIfTheDataPropertyIsNotAnArray()
  {
    $this->init();

    $this->model->data = 'foo';

    $this->assertFalse($this->model->hasData('foo'));
  }

  /** @test */
  public function testHasdataMethodChecksIfTheDataPropertyIsEmptyOrNotWhenTheGivenIndexIsNull()
  {
    $this->init();

    $this->model->data['foo'] = 'bar';

    $this->assertTrue($this->model->hasData());

    unset($this->model->data['foo']);

    $this->assertFalse($this->model->hasData());
  }

  /** @test */
  public function testSetdataMethodSetsTheData()
  {
    $this->init();

    $result = $this->model->setData(['foo' => 'bar']);

    $this->assertInstanceOf(Model::class, $result);
    $this->assertSame(['foo' => 'bar'], $this->model->data);
  }

  /** @test */
  public function testAdddataMethodMergesTheGivenDataWithTheCurrentOneIfExists()
  {
    $this->init();

    $this->model->addData(['foo' => 'bar']);
    $this->assertSame(['foo' => 'bar'], $this->model->data);

    $result = $this->model->addData(['bar' => 'baz']);
    $this->assertSame(['foo' => 'bar', 'bar' => 'baz'], $this->model->data);
    $this->assertInstanceOf(Model::class, $result);
  }

  /** @test */
  public function testAdddataMethodDoesNotMergeTheGivenDataWithTheCurrentOneIfTheGivenDataIsNotAnArray()
  {
    $this->init();

    $this->model->data['foo'] = 'bar';

    $result = $this->model->addData(['bar' => 'baz'], 'foo');

    $this->assertSame(['foo' => 'bar', 'bar' => 'baz'], $this->model->data);
    $this->assertInstanceOf(Model::class, $result);
  }

  /** @test */
  public function testCacheNameMethodGeneratesCacheNameFromTheGivenDataAndOptionallySpec()
  {
    $this->initWithModelFile();

    $method = $this->getNonPublicMethod('_cache_name');

    $result   = $method->invoke($this->model, 'foo', 'spec');
    $expected = 'models/' . $this->getNonPublicProperty('_path') .
      '/spec/' . md5(serialize('foo'));

    $this->assertSame($expected, $result);

    $result   = $method->invoke($this->model, 'foo');
    $expected = 'models/' . $this->getNonPublicProperty('_path') . '/' .
      md5(serialize('foo'));

    $this->assertSame($expected, $result);

    $result   = $method->invoke($this->model, ['foo' => 'bar'], 'baz');
    $expected = 'models/' . $this->getNonPublicProperty('_path') . '/baz/' .
      md5(serialize(['foo' => 'bar']));

    $this->assertSame($expected, $result);

    $result   = $method->invoke($this->model, ['foo' => 'bar']);
    $expected = 'models/' . $this->getNonPublicProperty('_path') . '/' .
      md5(serialize(['foo' => 'bar']));

    $this->assertSame($expected, $result);
  }

  /** @test  */
  public function testCacheNameMethodReturnsNullWhenThePathPropertyIsNotSet()
  {
    // Init the model object without a file will not set the path property
    $this->init();

    $this->assertNull(
      $this->getNonPublicMethod('_cache_name')->invoke($this->model, 'foo')
    );
  }

  /** @test */
  public function testSetcacheMethodSetsTheCacheFromTheGivenData()
  {
    $this->initWithModelFile();

    $cache_name_method = $this->getNonPublicMethod('_cache_name');

    // Swap cache instance with a mocked version
    $cache_mock = \Mockery::mock(Cache::class);
    $this->setNonPublicPropertyValue('cache_engine', $cache_mock);

    // Set expectation that the Cache::set() should be called with expected arguments
    $cache_mock->shouldReceive('set')
      ->once()
      ->with(
        $cache_name_method->invoke(
          $this->model,
          $cache_name_method->invoke($this->model, ['foo' => 'bar'], 'spec')
        ),
        $this->model->get(['foo' => 'bar']),
        123
      )
      ->andReturnTrue();

    $this->model->setCache(['foo' => 'bar'], 'spec', 123);

    // There's nothing to assert
    $this->assertTrue(true);
  }

  /** @test */
  public function testDeletecacheMethodDeletesACacheWithTheGivenData()
  {
    $this->initWithModelFile();

    $cache_mock = \Mockery::mock(Cache::class);

    $this->setNonPublicPropertyValue('cache_engine', $cache_mock);

    $cache_name_method = $this->getNonPublicMethod('_cache_name');

    $cache_mock->shouldReceive('deleteAll')
      ->once()
      ->with(
        $cache_name_method->invoke(
          $this->model,
          $cache_name_method->invoke($this->model, ['foo' => 'bar'], 'spec'),
          ''
        )
      )
      ->andReturn(1);

    $this->model->deleteCache(['foo' => 'bar'], 'spec');

    // There's nothing to assert
    $this->assertTrue(true);
  }

  /** @test */
  public function testGetfromcacheMethodReturnsTheCacheForGivenItemAndCreatesItIfExpired()
  {
    $this->initWithModelFile();

    $cache_mock = \Mockery::mock(Cache::class);

    $this->setNonPublicPropertyValue('cache_engine', $cache_mock);

    $cache_mock->shouldReceive('getSet')
      ->once()
      ->andReturn(['cached_item_1', 'cached_item_2']);

    $result = $this->model->getFromCache(['foo' => 'bar'], 'spec', 123);

    $this->assertSame(['cached_item_1', 'cached_item_2'], $result);
  }

  /** @test */
  public function testGetfromcacheMethodReturnsNullIfItFailsToGenerateCacheName()
  {
    $this->init();

    $result = $this->model->getFromCache(['foo' => 'bar'], 'spec', 123);

    $this->assertNull($result);
  }

  /** @test */
  public function testGetsetfromcacheMethodReturnsTheCacheForGivenItemAndCreatesItIfExpired()
  {
    $this->initWithModelFile();

    $cache_mock = \Mockery::mock(Cache::class);

    $this->setNonPublicPropertyValue('cache_engine', $cache_mock);

    $cache_mock->shouldReceive('getSet')
      ->once()
      ->andReturn(['cached_item_1', 'cached_item_2']);

    $result = $this->model->getSetFromCache(
      function () {
        return $this->model->get(['foo' => 'bar']);
      },
      ['foo' => 'bar'],
      'spec',
      123
    );

    $this->assertSame(['cached_item_1', 'cached_item_2'], $result);
  }

  /** @test */
  public function testGetsetfromcacheMethodReturnsNullIfItFailsToGenerateCacheName()
  {
    $this->init();

    $result = $this->model->getSetFromCache(
      function () {
        return $this->model->get(['foo' => 'bar']);
      },
      ['foo' => 'bar'],
      'spec',
      123
    );

    $this->assertNull($result);
  }
}