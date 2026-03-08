<?php

namespace bbn\tests\Mvc;

use bbn\Mvc\Router;
use bbn\Mvc\View;
use PHPUnit\Framework\TestCase;
use bbn\tests\Files;
use bbn\tests\Reflectable;

class ViewTest extends TestCase
{
  use Reflectable, Files;

  protected View $view;

  protected $info = [
    'mode'     => 'html',
    'path'     => 'path/to',
    'ext'      => 'html',
    'file'     => 'path/to/file/foo.html',
    'checkers' => ['path/to/checker/foo.less']
  ];

  /**
   * @param array|null $info
   */
  public function init(array $info = null)
  {
    $this->info['file'] = $this->getTestingDirName() . $this->info['file'];

    $this->view = new View($info ?? $this->info);
  }

  public function getInstance()
  {
    return $this->view;
  }

  protected function tearDown(): void
  {
    \Mockery::close();
  }

  /** @test */
  public function testConstructorTest()
  {
    $this->init();

    $this->assertSame(
      $this->getNonPublicProperty('_path'),
      $this->info['path']
    );

    $this->assertSame(
      $this->getNonPublicProperty('_ext'),
      $this->info['ext']
    );

    $this->assertSame(
      $this->getNonPublicProperty('_file'),
      $this->info['file']
    );

    $this->assertSame(
      $this->getNonPublicProperty('_checkers'),
      $this->info['checkers']
    );

    $this->assertNull(
      $this->getNonPublicProperty('_lang_file')
    );

    $this->assertNull(
      $this->getNonPublicProperty('_plugin')
    );

    $this->assertFalse(
      $this->getNonPublicProperty('_component')
    );

    $this->init(
      array_merge($this->info, [
        'i18n'      => $locale = 'path/to/locale/locale.json',
        'plugin'    => $plugin = 'plugin_name',
        'component' => $component = 'component_name'
      ])
    );

    $this->assertSame(
      $this->getNonPublicProperty('_lang_file'),
      $locale
    );

    $this->assertSame(
      $this->getNonPublicProperty('_plugin'),
      $plugin
    );

    $this->assertSame(
      $this->getNonPublicProperty('_component'),
      $component
    );
  }

  /** @test */
  public function testConstructorTestWhenModeDoesNotExistInAvailableModes()
  {
    $this->init(array_replace($this->info, ['mode' => 'foo']));

    $this->assertNull(
      $this->getNonPublicProperty('_file')
    );

    $this->assertNull(
      $this->getNonPublicProperty('_path')
    );

    $this->assertNull(
      $this->getNonPublicProperty('_ext')
    );

    $this->assertNull(
      $this->getNonPublicProperty('_checkers')
    );

    $this->assertNull(
      $this->getNonPublicProperty('_lang_file')
    );

    $this->assertNull(
      $this->getNonPublicProperty('_plugin')
    );

    $this->assertNull(
      $this->getNonPublicProperty('_component')
    );
  }

  /** @test */
  public function testConstructorTestWhenModeIsNotProvided()
  {
    unset($this->info['mode']);
    $this->init();

    $this->assertNull(
      $this->getNonPublicProperty('_file')
    );

    $this->assertNull(
      $this->getNonPublicProperty('_path')
    );

    $this->assertNull(
      $this->getNonPublicProperty('_ext')
    );

    $this->assertNull(
      $this->getNonPublicProperty('_checkers')
    );

    $this->assertNull(
      $this->getNonPublicProperty('_lang_file')
    );

    $this->assertNull(
      $this->getNonPublicProperty('_plugin')
    );

    $this->assertNull(
      $this->getNonPublicProperty('_component')
    );
  }

  /** @test */
  public function testCheckMethodChecksWhetherTheFilePropertyIsEmptyOrNot()
  {
    $this->init();
    $this->assertTrue($this->view->check());

    unset($this->info['mode']);
    $this->init();
    $this->assertFalse($this->view->check());
  }

  /** @test */
  public function testGetMethodReturnsEmptyStringWhenFileDoesNotExist()
  {
    $this->init();
    $this->assertSame('', $this->view->get());
  }

  /** @test */
  public function testGetMethodReturnsEmptyStringWhenFileHasEmptyContent()
  {
    $this->init();

    $this->createDir('path/to/file');
    $this->createFile('foo.html', '', 'path/to/file');

    $this->assertSame('', $this->view->get());

    $this->cleanTestingDir();
  }

  /** @test */
  public function testGetMethodDoesNotSetTheContentFromCheckersFilesIfNotExist()
  {
    $this->init();

    $this->createDir('path/to/file');
    $this->createFile('foo.html', 'html_content', 'path/to/file');

    $this->view->get();

    $this->assertSame('html_content', $this->getNonPublicProperty('_content'));

    $this->cleanTestingDir();
  }

  /** @test */
  public function testGetMethodSetsTheContentFromCheckersFilesWhenExists()
  {
    $this->info['checkers'] = [$this->getTestingDirName() . $this->info['checkers'][0]];
    $this->init();

    $this->createDir('path/to/file');
    $this->createFile('foo.html', ' html_content', 'path/to/file');

    $this->createDir('path/to/checker');
    $this->createFile('foo.less', 'less_content', 'path/to/checker');

    $this->view->get();

    $this->assertSame('less_content html_content', $this->getNonPublicProperty('_content'));

    $this->cleanTestingDir();
  }

  /** @test */
  public function testGetMethodReturnsFalseWhenFilePropertyIsEmpty()
  {
    unset($this->info['mode']);
    $this->init();

    $this->assertFalse($this->view->get());
  }

  /** @test */
  public function testGetMethodReturnsTheContentOfTheFileWhenExtensionIsJsAndLangFileIsEmpty()
  {
    $testing_dir = $this->getTestingDirName();

    $this->createDir('path/to/file/');
    $this->createFile('foo.js', 'js_content', 'path/to/file');

    $this->init([
      'mode'     => 'js',
      'path'     => 'path/to',
      'ext'      => 'js',
      'file'     => $testing_dir . 'path/to/file/foo.js'
    ]);

    $result = $this->view->get();

    $this->assertSame('js_content', $result);
    $this->assertSame('js_content', $this->getNonPublicProperty('_content'));

    $this->cleanTestingDir();
  }

  /** @test */
  public function testGetMethodReturnsTheContentOfTheFileWhenExtensionIsJsIgnoringLangFileWhenTheFileDoesNotExist()
  {
    $testing_dir = $this->getTestingDirName();

    $this->createDir('path/to/file/');
    $this->createFile('foo.js', 'js_content', 'path/to/file');

    $this->init([
      'mode'     => 'js',
      'path'     => 'path/to',
      'ext'      => 'js',
      'file'     => $testing_dir . 'path/to/file/foo.js',
      'i18n'     => $testing_dir . 'path/to/locale/en.json'
    ]);

    $result = $this->view->get();

    $this->assertSame('js_content', $result);
    $this->assertSame('js_content', $this->getNonPublicProperty('_content'));

    $this->cleanTestingDir();
  }

  /** @test */
  public function testGetMethodReturnsTheContentOfTheFileWhenExtensionIsJsAndLangFileContent()
  {
    $testing_dir = $this->getTestingDirName();

    $this->createDir('path/to/file');
    $this->createFile('foo.js', 'js_content', 'path/to/file');

    $this->createDir('path/to/locale');
    $this->createFile('en.json', '{"mvc/path/to":{"translation_key": "translation_value"}}', 'path/to/locale');

    $this->init([
      'mode'     => 'js',
      'path'     => 'path/to',
      'ext'      => 'js',
      'file'     => $testing_dir . 'path/to/file/foo.js',
      'i18n'     => $testing_dir . 'path/to/locale/en.json'
    ]);

    $result   = $this->view->get();

    $this->assertStringContainsString('{"translation_key":"translation_value"}', $result);
    $this->assertStringContainsString('js_content', $result);

    $this->assertStringContainsString('{"translation_key":"translation_value"}', $this->getNonPublicProperty('_content'));
    $this->assertStringContainsString('js_content', $this->getNonPublicProperty('_content'));

    $this->cleanTestingDir();
  }

  /** @test */
  public function testGetMethodReturnsTheContentOfTheFileWhenExtensionIsJsAndLangFileContentIndexNotFound()
  {
    $testing_dir = $this->getTestingDirName();

    $this->createDir('path/to/file');
    $this->createFile('foo.js', 'js_content', 'path/to/file');

    $this->createDir('path/to/locale');
    $this->createFile('en.json', '{"mvc/another/path/to":{"translation_key": "translation_value"}}', 'path/to/locale');

    $this->init([
      'mode'     => 'js',
      'path'     => 'path/to',
      'ext'      => 'js',
      'file'     => $testing_dir . 'path/to/file/foo.js',
      'i18n'     => $testing_dir . 'path/to/locale/en.json'
    ]);

    $result   = $this->view->get();

    $this->assertStringNotContainsString('{"translation_key":"translation_value"}', $result);
    $this->assertSame('js_content', $result);

    $this->assertStringNotContainsString('{"translation_key":"translation_value"}', $this->getNonPublicProperty('_content'));
    $this->assertSame('js_content', $this->getNonPublicProperty('_content'));

    $this->cleanTestingDir();
  }

  /** @test */
  public function testGetMethodReturnsTheContentOfTheFileWhenExtensionInCss()
  {
    $testing_dir = $this->getTestingDirName();

    $this->createDir('path/to/file/');
    $this->createFile('foo.css', 'css_content', 'path/to/file');

    $this->init([
      'mode'     => 'css',
      'path'     => 'path/to',
      'ext'      => 'css',
      'file'     => $testing_dir . 'path/to/file/foo.css'
    ]);

    $result = $this->view->get();

    $this->assertSame('css_content', $result);
    $this->assertSame('css_content', $this->getNonPublicProperty('_content'));

    $this->cleanTestingDir();
  }

  /** @test */
  public function testGetMethodReturnsTheContentOfTheFileWhenTheExtensionIsLess()
  {
    $testing_dir = $this->getTestingDirName();

    $this->createDir('path/to/file/');
    $this->createFile('foo.less', 'less_content', 'path/to/file');

    $this->init([
      'mode'     => 'css',
      'path'     => 'path/to',
      'ext'      => 'less',
      'file'     => $testing_dir . 'path/to/file/foo.less'
    ]);

    try {
      $this->view->get();
    } catch (\Exception $e) {

    }

    $this->assertSame('less_content', $this->getNonPublicProperty('_content'));

    $this->cleanTestingDir();
  }

  /** @test */
  public function testGetMethodReturnsTheContentOfTheFileWhenTheExtensionIsScss()
  {
    $testing_dir = $this->getTestingDirName();

    $this->createDir('path/to/file/');
    $this->createFile('foo.scss', 'scss_content', 'path/to/file');

    $this->init([
      'mode'     => 'css',
      'path'     => 'path/to',
      'ext'      => 'scss',
      'file'     => $testing_dir . 'path/to/file/foo.scss'
    ]);

    try {
      $this->view->get();
    } catch (\Exception $e) {

    } catch (\Error $e) {

    }

    $this->assertSame('scss_content', $this->getNonPublicProperty('_content'));

    $this->cleanTestingDir();
  }

  /** @test */
  public function testGetMethodReturnsTheContentOfTheFileWhenTheExtensionIsHtml()
  {
    $testing_dir = $this->getTestingDirName();

    $this->createDir('path/to/file/');
    $this->createFile('foo.html', 'html_content', 'path/to/file');

    $this->init([
      'mode'     => 'html',
      'path'     => 'path/to',
      'ext'      => 'html',
      'file'     => $testing_dir . 'path/to/file/foo.html'
    ]);

    $result = $this->view->get();

    $this->assertSame('html_content', $result);
    $this->assertSame('html_content', $this->getNonPublicProperty('_content'));

    $this->cleanTestingDir();
  }

  /** @test */
  public function testGetMethodReturnsTheContentOfTheFileWhenTheExtensionIsPhp()
  {
    $testing_dir = $this->getTestingDirName();

    $this->createDir('path/to/file/');
    $this->createFile('foo.php', '<?php echo "php_content" ;?>', 'path/to/file');

    $this->init([
      'mode'     => 'model',
      'path'     => 'path/to',
      'ext'      => 'php',
      'file'     => $testing_dir . 'path/to/file/foo.php'
    ]);

    $result = $this->view->get();

    $this->assertSame('php_content', $result);
    $this->assertSame('<?php echo "php_content" ;?>', $this->getNonPublicProperty('_content'));

    $this->cleanTestingDir();
  }

  /** @test */
  public function testGetMethodReturnsTheContentOfTheFileWhenTheExtensionIsPhpWhenPluginExists()
  {
    $testing_dir = $this->getTestingDirName();

    $router_mock = \Mockery::mock(Router::class);
    $this->setNonPublicPropertyValue('retriever_instance', $router_mock, Router::class);

    $this->createDir('path/to/file/');
    $this->createFile('foo.php', '<?php echo textdomain(null) ;?>', 'path/to/file');

    $this->init([
      'mode'     => 'model',
      'path'     => 'path/to',
      'ext'      => 'php',
      'file'     => $testing_dir . 'path/to/file/foo.php',
      'plugin'   => 'plugin_name'
    ]);

    $router_mock->shouldReceive('getLocaleDomain')
      ->once()
      ->with('plugin_name')
      ->andReturn('en');

    $result = $this->view->get();

    // Text domain should changed ti english when the file is evaluated
    $this->assertSame('en', $result);
    // Text should be changed back to default after the file is evaluated
    $this->assertNotSame('en', textdomain(null));
    $this->assertSame('<?php echo textdomain(null) ;?>', $this->getNonPublicProperty('_content'));

    $this->cleanTestingDir();
  }
}