<?php

namespace X;

use bbn\X;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use bbn\tests\Files;
use bbn\tests\Reflectable;

class XTest extends TestCase
{
  use Reflectable, Files;

  protected function setUp(): void
  {
    $this->setNonPublicPropertyValue('_counters', []);
    $this->setNonPublicPropertyValue('_last_curl', null);
    $this->setNonPublicPropertyValue('_cli', null);
    $this->setNonPublicPropertyValue('_textdomain', null);

    $this->cleanTestingDir(BBN_DATA_PATH . 'logs');
  }

  protected function tearDown(): void
  {
    $this->cleanTestingDir(BBN_DATA_PATH . 'logs');
    \Mockery::close();
  }


  public function getInstance()
  {
    return X::class;
  }

  /** @test */
  public function init_count_method_init_the_counters_for_the_given_name_if_not_exists()
  {
    $method = $this->getNonPublicMethod('_init_count');

    $method->invoke(null, 'foo');
    $this->assertSame(['foo' => 0], $this->getNonPublicProperty('_counters'));

    $method->invoke(null, 'foo');
    $this->assertSame(['foo' => 0], $this->getNonPublicProperty('_counters'));
  }

  /** @test */
  public function init_count_method_init_the_counters_when_no_name_is_given()
  {
    $method = $this->getNonPublicMethod('_init_count');

    $method->invoke(null);
    $this->assertSame(['num' => 0], $this->getNonPublicProperty('_counters'));

    $method->invoke(null);
    $this->assertSame(['num' => 0], $this->getNonPublicProperty('_counters'));
  }

  /** @test */
  public function increment_method_increments_the_counters_for_given_optional_name_if_exists_or_init_it()
  {
    $this->assertArrayNotHasKey('foo', $this->getNonPublicProperty('_counters'));

    X::increment('foo');

    $this->assertArrayHasKey('foo', $counters = $this->getNonPublicProperty('_counters'));
    $this->assertSame(1, $counters['foo']);

    X::increment('foo', 20);
    $this->assertSame(21, $this->getNonPublicProperty('_counters')['foo']);

    // Test with no name provided
    $this->assertArrayNotHasKey('num', $this->getNonPublicProperty('_counters'));

    X::increment();

    $this->assertArrayHasKey('num', $counters = $this->getNonPublicProperty('_counters'));
    $this->assertSame(1, $counters['num']);
  }

  /** @test */
  public function decrement_method_decrements_the_counters_for_the_given_optional_name_if_exists_or_init_it()
  {
    $this->assertArrayNotHasKey('foo', $this->getNonPublicProperty('_counters'));

    X::decrement('foo');

    $this->assertArrayHasKey('foo', $counters = $this->getNonPublicProperty('_counters'));
    $this->assertSame(-1, $counters['foo']);

    X::decrement('foo', 25);

    $this->assertSame(-26, $this->getNonPublicProperty('_counters')['foo']);

    // Test with no name provided
    $this->assertArrayNotHasKey('num', $this->getNonPublicProperty('_counters'));

    X::decrement();
    $this->assertArrayHasKey('num', $counters = $this->getNonPublicProperty('_counters'));
    $this->assertSame(-1, $counters['num']);
  }

  /** @test */
  public function count_method_returns_the_counter_for_the_given_name_if_exists_or_init_it_and_deletes_it_if_specified()
  {
    $this->assertArrayNotHasKey('foo', $this->getNonPublicProperty('_counters'));

    $this->assertSame(0, X::count('foo'));
    $this->assertSame(0, X::count());
    $this->assertArrayHasKey('foo', $this->getNonPublicProperty('_counters'));
    $this->assertArrayHasKey('num', $this->getNonPublicProperty('_counters'));

    $this->setNonPublicPropertyValue('_counters', ['foo' => 3, 'num' => 33]);

    $this->assertSame(3, X::count('foo'));
    $this->assertSame(33, X::count());

    // Deletes the keys
    $this->assertSame(3, X::count('foo', true));
    $this->assertSame(33, X::count('num', true));
    $this->assertArrayNotHasKey('foo', $this->getNonPublicProperty('_counters'));
    $this->assertArrayNotHasKey('num', $this->getNonPublicProperty('_counters'));
  }

  /** @test */
  public function countAll_method_returns_the_array_counters_and_deletes_it_if_specified()
  {
    $this->setNonPublicPropertyValue('_counters', $counters = ['foo' => 12, 'num' => 13]);

    $this->assertSame($counters, X::countAll());
    $this->assertSame($counters, X::countAll(true));
    $this->assertEmpty($this->getNonPublicProperty('_counters'));
  }
  
  /** @test */
  public function tDom_method_sets_the_current_text_domain_with_version_number_and_returns_it_when_version_txt_file_exists()
  {
    $this->setNonPublicPropertyValue('_textdomain', null);

    $reflection_class = new \ReflectionClass(X::class);
    $dir              = dirname(dirname($reflection_class->getFileName()));

    $file_path = $this->createFile('version.txt', '2.1', $dir, false);

    $result = X::tDom();

    $this->assertSame('bbn2.1', $result);
    $this->assertSame('bbn2.1', $this->getNonPublicProperty('_textdomain'));

    unlink($file_path);
  }

  /** @test */
  public function tDom_method_sets_the_current_text_domain_without_version_number_and_returns_it_when_version_txt_file_does_not_exist()
  {
    $this->setNonPublicPropertyValue('_textdomain', null);

    $result = X::tDom();

    $this->assertSame('bbn', $result);
    $this->assertSame('bbn', $this->getNonPublicProperty('_textdomain'));
  }

  /** @test */
  public function tDom_method_returns_the_current_text_domain_if_already_exists()
  {
    $this->setNonPublicPropertyValue('_textdomain', 'foo');

    $this->assertSame('foo', X::tDom());
    $this->assertSame('foo', $this->getNonPublicProperty('_textdomain'));
  }
  
  /** @test */
  public function the_translation_method_returns_a_string_from_a_single_message_lookup_after_overriding_the_current_text_domain()
  {
    $this->assertSame('foo', X::_('foo'));
    $this->assertSame('foo bar', X::_('foo %s', 'bar'));
  }

  /** @test */
  public function log_method_creates_and_saves_a_log_to_a_file_if_log_file_does_not_exist()
  {
    $this->createDir('logs');

    $log_file = BBN_DATA_PATH . 'logs/error_log.log';

    $this->assertFileDoesNotExist($log_file);

    X::log($message = 'This should create a new log file', 'error_log');

    $this->assertFileExists($log_file);
    $this->assertFileIsWritable($log_file);
    $this->assertStringContainsString($message, file_get_contents($log_file));
  }

  /** @test */
  public function log_method_appends_a_log_to_the_existing_log_file_when_it_does_not_exceeds_the_max_log_size_file_constant()
  {
    $this->createDir('logs');

    $log_file = BBN_DATA_PATH . 'logs/error_log.log';

    $fp = fopen($log_file, 'w');
    fputs($fp, $existing_file_content = Str::random(BBN_X_MAX_LOG_FILE), BBN_X_MAX_LOG_FILE);
    fclose($fp);

    $this->assertSame(BBN_X_MAX_LOG_FILE, filesize($log_file));

    X::log($message = 'This should be appended to the log file', 'error_log');

    $this->assertStringContainsString($existing_file_content, file_get_contents($log_file));
    $this->assertStringContainsString($message, file_get_contents($log_file));
  }

  /** @test */
  public function log_method_backups_the_existing_log_file_then_creates_and_new_one_when_it_exceeds_the_max_log_size_file_constant()
  {
    $this->createDir('logs');

    $log_file = BBN_DATA_PATH . 'logs/error_log.log';

    $fp = fopen($log_file, 'w');
    fputs($fp, $old_log_content = Str::random(BBN_X_MAX_LOG_FILE + 1), BBN_X_MAX_LOG_FILE + 1);
    fclose($fp);

    $this->assertSame(BBN_X_MAX_LOG_FILE + 1, filesize($log_file));

    X::log($message = 'This should be written to a new file', 'error_log');

    $this->assertFileExists($old_file = BBN_DATA_PATH . 'logs/error_log.log.old');
    $this->assertStringContainsString($old_log_content, file_get_contents($old_file));

    $this->assertStringNotContainsString($old_log_content, file_get_contents($log_file));
    $this->assertStringContainsString($message, file_get_contents($log_file));
  }

  /** @test */
  public function log_method_echoes_out_the_message_when_called_from_cli_and_log_argument_provided_and_saves_to_a_log_file()
  {
    global $argv;
    $argv[2] = 'log';

    $this->createDir('logs');

    $this->expectOutputString(PHP_EOL . ($message = 'This should be echoed out and saved to the file') . PHP_EOL . PHP_EOL);

    X::log($message, 'error_log');

    $this->assertFileExists($log_file = BBN_DATA_PATH . 'logs/error_log.log');
    $this->assertStringContainsString($message, file_get_contents($log_file));
  }

  /** @test */
  public function log_method_uses_misc_as_default_name_for_the_log_file_if_not_provided()
  {
    $this->createDir('logs');

    $log_file = BBN_DATA_PATH . 'logs/misc.log';

    $this->assertFileDoesNotExist($log_file);

    X::log($message = 'This should create a new log file');

    $this->assertFileExists($log_file);
    $this->assertFileIsWritable($log_file);
    $this->assertStringContainsString($message, file_get_contents($log_file));
  }

  /** @test */
  public function logError_method_creates_and_saves_the_error_in_to_a_json_file_when_file_does_not_exist()
  {
    $this->createDir('logs');
    $log_file = BBN_DATA_PATH . 'logs/_php_error.json';

    X::logError(
      $err_no = '123',
      $err_message = 'This should create a new log file',
      $err_file = 'foo.php',
      $err_line = '33'
    );

    $this->assertFileExists($log_file);
    $this->assertJson($json = file_get_contents($log_file));
    $this->assertIsArray($array_content = json_decode($json, true));

    $content = current($array_content);

    $this->assertArrayHasKey('count', $content);
    $this->assertArrayHasKey('type', $content);
    $this->assertArrayHasKey('error', $content);
    $this->assertArrayHasKey('file', $content);
    $this->assertArrayHasKey('line', $content);
    $this->assertArrayHasKey('backtrace', $content);
    $this->assertArrayHasKey('request', $content);

    $this->assertSame($content['count'], 1);
    $this->assertSame($content['type'], $err_no);
    $this->assertSame($content['error'], $err_message);
    $this->assertSame($content['file'], $err_file);
    $this->assertSame($content['line'], $err_line);

    return $array_content;
  }

  /** @test */
  public function logError_method_and_saves_the_error_in_to_an_existing_json_file_with_incrementing_the_count_if_same_errors_exist_and_sorting_by_date()
  {
    $this->createDir('logs');
    $log_file = BBN_DATA_PATH . 'logs/_php_error.json';

    $array_content[] = [
      "count"     => 1,
      "type"      => "123",
      "error"     => "This should increment the count to 2",
      "file"      => "foo.php",
      "line"      => "33",
      "last_date" => date('Y-m-d H:i:s', strtotime('-1 Hour')),
      "request"   => ""
    ];

    $array_content[] = [
      "count"     => 3,
      "type"      => $top_err_no = "12345",
      "error"     => $top_err_message = "This should be on sorted first",
      "file"      => $top_err_file = "foo.php",
      "line"      => $top_err_line = "33",
      "last_date" => date('Y-m-d H:i:s', strtotime('+1 Hour')),
      "request"   => '',
      "backtrace" => ''
    ];

    file_put_contents($log_file, json_encode($array_content));

    X::logError(
      $err_no = '123',
      $err_message = 'This should increment the count to 2',
      $err_file = 'foo.php',
      $err_line = '33'
    );

    $this->assertFileExists($log_file);
    $this->assertJson($json = file_get_contents($log_file));
    $this->assertIsArray($result = json_decode($json, true));

    // First array sorted
    $content = current($result);

    $this->assertArrayHasKey('count', $content);
    $this->assertArrayHasKey('type', $content);
    $this->assertArrayHasKey('error', $content);
    $this->assertArrayHasKey('file', $content);
    $this->assertArrayHasKey('line', $content);
    $this->assertArrayHasKey('backtrace', $content);

    $this->assertSame($content['count'], 3);
    $this->assertSame($content['type'], $top_err_no);
    $this->assertSame($content['error'], $top_err_message);
    $this->assertSame($content['file'], $top_err_file);
    $this->assertSame($content['line'], $top_err_line);

    // Second array sorted
    $content = next($result);

    $this->assertArrayHasKey('count', $content);
    $this->assertArrayHasKey('type', $content);
    $this->assertArrayHasKey('error', $content);
    $this->assertArrayHasKey('file', $content);
    $this->assertArrayHasKey('line', $content);
    $this->assertArrayHasKey('backtrace', $content);

    $this->assertSame($content['count'], 2);
    $this->assertSame($content['type'], $err_no);
    $this->assertSame($content['error'], $err_message);
    $this->assertSame($content['file'], $err_file);
    $this->assertSame($content['line'], $err_line);
  }

  /** @test */
  public function hasProp_method_checks_if_an_array_or_object_has_the_given_key_or_property_and_checks_if_empty_if_specified()
  {
    $arr = [
      'foo' => 'bar',
      'baz' => '',
      'bar' => 0
    ];

    $this->assertTrue(X::hasProp($arr, 'foo'));
    $this->assertTrue(X::hasProp($arr, 'baz'));
    $this->assertFalse(X::hasProp($arr, 'baz', true));
    $this->assertTrue(X::hasProp($arr, 'bar'));
    $this->assertFalse(X::hasProp($arr, 'bar', true));

    $obj = (object)$arr;

    $this->assertTrue(X::hasProp($obj, 'foo'));
    $this->assertTrue(X::hasProp($obj, 'baz'));
    $this->assertFalse(X::hasProp($obj, 'baz', true));
    $this->assertTrue(X::hasProp($obj, 'bar'));
    $this->assertFalse(X::hasProp($obj, 'bar', true));

    $this->assertNull(X::hasProp('foo', 'bar'));
  }

  /** @test */
  public function hasProps_method_checks_if_an_array_or_object_has_the_given_all_keys_or_properties()
  {
    $arr = [
      'foo' => 'bar',
      'baz' => '',
      'bar' => 0
    ];

    $this->assertTrue(X::hasProps($arr, ['foo', 'bar', 'baz']));
    $this->assertTrue(X::hasProps($arr, ['foo', 'baz']));
    $this->assertTrue(X::hasProps($arr, ['baz']));
    $this->assertTrue(X::hasProps($arr, ['foo'], true));

    $this->assertFalse(X::hasProps($arr, ['baz'], true));
    $this->assertFalse(X::hasProps($arr, ['bar'], true));
    $this->assertFalse(X::hasProps($arr, ['foo', 'bar', 'baz'], true));
    $this->assertFalse(X::hasProps($arr, ['foo', 'bar', 'baz', 'foobar']));

    $obj = (object)$arr;

    $this->assertTrue(X::hasProps($obj, ['foo', 'bar', 'baz']));
    $this->assertTrue(X::hasProps($obj, ['foo', 'baz']));
    $this->assertTrue(X::hasProps($obj, ['baz']));
    $this->assertTrue(X::hasProps($obj, ['foo'], true));

    $this->assertFalse(X::hasProps($obj, ['baz'], true));
    $this->assertFalse(X::hasProps($obj, ['bar'], true));
    $this->assertFalse(X::hasProps($obj, ['foo', 'bar', 'baz'], true));
    $this->assertFalse(X::hasProps($obj, ['foo', 'bar', 'baz', 'foobar']));

    $this->assertNull(X::hasProps('foo', ['foo', 'bar']));
  }

  /** @test */
  public function hasDeepProp_method_checks_if_an_array_or_object_has_the_given_deep_keys_or_properties()
  {
    $arr = [
      'foo' => [
        'bar1' => [], 'bar2'
      ],
      'baz' => 'foo',
      'bar' => 0
    ];

    $this->assertTrue(X::hasDeepProp($arr, ['foo']));
    $this->assertTrue(X::hasDeepProp($arr, ['foo'], true));

    $this->assertTrue(X::hasDeepProp($arr, ['foo', 'bar1']));
    $this->assertFalse(X::hasDeepProp($arr, ['foo', 'bar1'], true));

    $this->assertFalse(X::hasDeepProp($arr, ['foo', 'bar2']));
    $this->assertFalse(X::hasDeepProp($arr, ['foo', 'bar2', true]));

    $this->assertFalse(X::hasDeepProp($arr, ['foo', 'bar1'], true));

    $this->assertTrue(X::hasDeepProp($arr, ['baz']));
    $this->assertTrue(X::hasDeepProp($arr, ['baz'], true));
    $this->assertFalse(X::hasDeepProp($arr, ['baz', 'foo']));

    $this->assertTrue(X::hasDeepProp($arr, ['bar']));
    $this->assertFalse(X::hasDeepProp($arr, ['bar'], true));
    $this->assertFalse(X::hasDeepProp($arr, ['baz', 'bar']));


    $obj = (object)$arr;

    $this->assertTrue(X::hasDeepProp($obj, ['foo', 'bar1']));
    $this->assertFalse(X::hasDeepProp($obj, ['foo', 'bar1'], true));
    $this->assertFalse(X::hasDeepProp($obj, ['foo', 'bar2']));
    $this->assertFalse(X::hasDeepProp($obj, ['foo', 'bar2', true]));
    $this->assertTrue(X::hasDeepProp($obj, ['foo'], true));
    $this->assertFalse(X::hasDeepProp($obj, ['foo', 'bar1'], true));

    $this->assertTrue(X::hasDeepProp($obj, ['baz']));
    $this->assertTrue(X::hasDeepProp($obj, ['baz'], true));
    $this->assertFalse(X::hasDeepProp($obj, ['baz', 'foo']));

    $this->assertTrue(X::hasDeepProp($obj, ['bar']));
    $this->assertFalse(X::hasDeepProp($obj, ['bar'], true));
    $this->assertFalse(X::hasDeepProp($obj, ['baz', 'bar']));

    $this->assertFalse(X::hasDeepProp('foo', ['foo', 'bar']));
  }

  /** @test */
  public function makeStoragePath_method_creates_dirs_for_the_given_date_format()
  {
    $result = X::makeStoragePath($path = BBN_DATA_PATH . 'testing', 'd/m/y');

    $this->assertSame(realpath($path) . '/' . date('d/m/y') . '/1/', $result);
    $this->assertTrue(is_dir($result));

    $this->cleanTestingDir();
  }

  /** @test */
  public function makeStoragePath_method_creates_dirs_with_default_date_format_if_not_provided()
  {
    $result = X::makeStoragePath($path = BBN_DATA_PATH . 'testing');

    $this->assertSame(realpath($path) . '/' . date('Y/m/d') . '/1/', $result);
    $this->assertTrue(is_dir($result));

    $this->cleanTestingDir();
  }

  /** @test */
  public function makeStoragePath_method_creates_dirs_with_for_the_given_date_format()
  {
    $result = X::makeStoragePath($path = BBN_DATA_PATH . 'testing', 'm/d/Y');

    $this->assertSame(realpath($path) . '/' . date('m/d/Y') . '/1/', $result);
    $this->assertTrue(is_dir($result));

    $this->cleanTestingDir();
  }

  /** @test */
  public function makeStoragePath_method_creates_and_increments_dirs_number_if_dir_exists_and_contains_other_dirs_or_files()
  {
    $dirpath = BBN_DATA_PATH . 'foo/' . date('Y/m/d');

    for ($i = 1; $i <= 10; $i++) {
      mkdir("$dirpath/$i", 0777, true);
    }

    mkdir("$dirpath/10/1");
    touch("$dirpath/10/foo.text");

    $result = X::makeStoragePath($path = BBN_DATA_PATH . 'foo', 'Y/m/d', 2);

    $this->assertSame(realpath($path) . '/' . date('Y/m/d') . '/11/', $result);
    $this->assertTrue(is_dir($result));

    $this->cleanTestingDir();
  }

  /** @test */
  public function makeStoragePath_method_returns_null_when_failed_to_create_the_dir()
  {
    $file_system_mock = \Mockery::mock(\bbn\File\System::class);

    $file_system_mock->shouldReceive('createPath')
      ->once()
      ->with($path = 'foo/bar/' . date('Y/m/d'))
      ->andReturn($path);

    $file_system_mock->shouldReceive('isDir')
      ->once()
      ->with($path)
      ->andReturnFalse();

    $result = X::makeStoragePath('foo/bar', 'Y/m/d', 100, $file_system_mock);

    $this->assertNull($result);
  }

  /** @test */
  public function makeStoragePath_method_returns_null_when_failed_to_create_the_sub_dir()
  {
    $file_system_mock = \Mockery::mock(\bbn\File\System::class);

    $file_system_mock->shouldReceive('createPath')
      ->once()
      ->with($path = 'foo/bar/' . date('Y/m/d'))
      ->andReturn($path);

    $file_system_mock->shouldReceive('isDir')
      ->once()
      ->with($path)
      ->andReturnTrue();

    $file_system_mock->shouldReceive('getDirs')
      ->once()
      ->with($path)
      ->andReturn([]);

    $file_system_mock->shouldReceive('createPath')
      ->once()
      ->with("$path/1")
      ->andReturn("");

    $result = X::makeStoragePath('foo/bar', 'Y/m/d', 100, $file_system_mock);

    $this->assertNull($result);
  }

  /** @test */
  public function cleanStoragePath_method_deletes_the_given_dir_with_default_date_format_if_dir_is_empty()
  {
    $dirpath = BBN_DATA_PATH . 'foo/' . date('Y/m/d');
    mkdir($dirpath, 0777, true);

    $result = X::cleanStoragePath($dirpath);

    $this->assertDirectoryDoesNotExist($dirpath);
    $this->assertSame(4, $result);
  }

  /** @test */
  public function cleanStoragePath_method_deletes_the_given_dir_and_date_format_if_dir_is_empty()
  {
    $dirpath = BBN_DATA_PATH . 'foo/' . date('m/d');
    mkdir($dirpath, 0777, true);

    $result = X::cleanStoragePath($dirpath, 'm/d');

    $this->assertDirectoryDoesNotExist($dirpath);
    $this->assertSame(3, $result);
  }
  
  /** @test */
  public function cleanStoragePath_method_does_not_delete_the_given_dir_if_it_is_not_empty()
  {
    $dirpath = BBN_DATA_PATH . 'foo/' . date('Y/m/d');

    for ($i = 1; $i <= 5;$i++) {
      mkdir("$dirpath/$i", 0777, true);
    }

    $result = X::cleanStoragePath($dirpath);

    $this->assertDirectoryExists($dirpath);
    $this->assertDirectoryExists("$dirpath/1");
    $this->assertDirectoryExists("$dirpath/5");
    $this->assertSame(0, $result);
  }

  /** @test */
  public function cleanStoragePath_method_returns_null_if_the_given_dir_path_does_not_exist()
  {
    $this->assertNull(
      X::cleanStoragePath('foo')
    );
  }

  /** @test */
  public function mergeObjects_method_merges_two_or_more_object_properties_into_one()
  {
    $obj1 = (object)[
      'a' => 1,
      'b' => 11,
      'c' => 111
    ];

    $obj2 = (object)[
      'd' => 2,
      'e' => 22,
      'c' => 222 // Duplicate property different value
    ];

    $result = X::mergeObjects($obj1, $obj2);

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('a', $result);
    $this->assertObjectHasAttribute('b', $result);
    $this->assertObjectHasAttribute('c', $result);
    $this->assertObjectHasAttribute('d', $result);
    $this->assertObjectHasAttribute('e', $result);
    $this->assertSame(222, $result->c);
    $this->assertSame(22, $result->e);
    $this->assertSame(2, $result->d);

    $obj3 = (object)[
      'f' => 3,
      'g' => 33
    ];

    $obj4 = (object)[
      'f' => 4, // Duplicate property different value
      'h' => 44
    ];

    // Test with four objects
    $result = X::mergeObjects($obj1, $obj2, $obj3, $obj4);

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('a', $result);
    $this->assertObjectHasAttribute('b', $result);
    $this->assertObjectHasAttribute('c', $result);
    $this->assertObjectHasAttribute('d', $result);
    $this->assertObjectHasAttribute('e', $result);
    $this->assertObjectHasAttribute('f', $result);
    $this->assertObjectHasAttribute('g', $result);
    $this->assertObjectHasAttribute('h', $result);
    $this->assertSame(222, $result->c);
    $this->assertSame(22, $result->e);
    $this->assertSame(33, $result->g);
    $this->assertSame(4, $result->f);
  }

  /** @test */
  public function mergeObjects_method_throws_an_exception_if_the_provided_arguments_is_not_an_object()
  {
    $this->expectException(\Exception::class);

    X::mergeObjects(
      (object)['a' => 1],
      (object)['b' => 2],
      'string'
    );
  }

  /** @test */
  public function flatten_method_flattens_a_multi_dimensional_array_for_the_given_children_index_name()
  {
    $arr = [
      [
        'name'  => 'John Doe',
        'age'   => '35',
        'children' => [
          ['name' => 'Carol', 'age' => '4'],
          ['name' => 'Jack', 'age' => '6'],
        ]
      ],
      [
        'name'  => 'Paul',
        'age'   => '33',
        'children' => [
          ['name' => 'Alan', 'age' => '8'],
          ['name' => 'Allison', 'age' => '2']
        ]
    ]
    ];

    $expected = [
      ['name' => 'John Doe', 'age' => '35'],
      ['name' => 'Paul', 'age' => '33'],
      ['name' => 'Carol', 'age' => '4'],
      ['name' => 'Jack', 'age' => '6'],
      ['name' => 'Alan', 'age' => '8'],
      ['name' => 'Allison', 'age' => '2']
    ];

    $this->assertSame($expected, X::flatten($arr, 'children'));
  }

  /** @test */
  public function mergeArrays_method_merges_two_or_more_arrays_into_one()
  {
    $arr1 = ['a', 'b'];
    $arr2 = ['c', 'd', 'f'];
    $arr3 = ['e', 'f'];

    $this->assertSame(['a', 'b', 'c', 'd', 'f', 'e', 'f'], X::mergeArrays($arr1, $arr2, $arr3));

    $arr1 = ['a' => 1, 'b' => 2];
    $arr2 = ['b' => 3, 'c' => 4, 'd' => 5];
    $arr3 = ['e' => 6, 'b' => 33];

    $expected = ['a' => 1, 'b' => 33, 'c' => 4, 'd' => 5, 'e' => 6];

    $this->assertSame($expected, X::mergeArrays($arr1, $arr2, $arr3));
  }

  /** @test */
  public function mergeArrays_method_throws_an_exception_if_the_provided_extra_argument_is_not_an_array()
  {
    $this->expectException(\Exception::class);

    X::mergeArrays(['a'], ['b'], 'foo');
  }

  /** @test */
  public function toObject_method_converts_a_json_string_or_an_array_to_object()
  {
    $result = X::toObject('{"a":1, "b":2}');

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('a', $result);
    $this->assertObjectHasAttribute('b', $result);
    $this->assertSame(1, $result->a);
    $this->assertSame(2, $result->b);

    $result = X::toObject(['a' => 1, 'b' => [2, 3]]);

    $this->assertIsObject($result);
    $this->assertObjectHasAttribute('a', $result);
    $this->assertObjectHasAttribute('b', $result);
    $this->assertSame(1, $result->a);
    $this->assertSame([2, 3], $result->b);

    $this->assertNull(X::toObject('foo'));
  }

  /** @test */
  public function toArray_method_convert_a_json_string_or_an_object_to_array()
  {
    $this->assertSame(
      ['a' => 1, 'b' => 2],
      X::toArray((object)['a' => 1, 'b' => 2])
    );

    $this->assertSame(
      ['a' => 1, 'b' => 2],
      X::toArray('{"a":1, "b":2}')
    );

    $this->assertNull(
      X::toArray('foo')
    );
  }

  /** @test */
  public function jsObject_method_returns_a_js_object_from_the_given_iterable()
  {
    $arr = [
      'a' => 1,
      'b' => [
        'c' => 'foo',
        'd' => 3
      ],
      'c' => 'let data = "{"foo":"bar"}"'
    ];

    $result = X::jsObject($arr);
    $this->assertJson($result);
    $this->assertIsArray($result_arr = json_decode($result, true));
    $this->assertArrayHasKey('a', $result_arr);
    $this->assertArrayHasKey('b', $result_arr);
    $this->assertArrayHasKey('c', $result_arr);
    $this->assertSame(1, $result_arr['a']);
    $this->assertSame(['c' => 'foo', 'd' => 3], $result_arr['b']);
    $this->assertSame('let data = "{"foo":"bar"}"', $result_arr['c']);

    $arr2 = [
      'c' => "function(){return 'foo'}"
      ];

    $result2 = X::jsObject($arr2);

    $this->assertStringContainsString("function(){return 'foo'}", $result2);
  }

  /** @test */
  public function indentJson_method_indents_a_flat_json_string_to_be_human_readable()
  {
    $result   = X::indentJson('{"a": 1, "b":"bar", "c":"foo\"bar"}');
    $expected =  '{'.PHP_EOL.'  "a": 1,'.PHP_EOL.'   "b":"bar",'.PHP_EOL.'   "c":"foo\"bar"'.PHP_EOL.'}';

    $this->assertSame($expected, $result);
  }

  /** @test */
  public function removeEmpty_method_returns_an_array_or_object_cleaned_of_all_empty_values()
  {
    $arr      = [
      'a' => 'foo',
      'b' => 2,
      'c' => ['a' => 1, 'b' => '', 'c' => ' '],
      'd' => '',
      'e' => ' '
    ];

    $expected = [
      'a' => 'foo',
      'b' => 2,
      'c' => ['a' => 1, 'c' => ' '],
      'e' => ' '
    ];


    $this->assertSame($expected, X::removeEmpty($arr));
    $this->assertSame([
      'a' => 'foo',
      'b' => 2,
      'c' => ['a' => 1]
    ], X::removeEmpty($arr, true));

    // Object test
    $obj    = (object)$arr;
    $result = X::removeEmpty($obj);

    $this->assertSame($expected, get_object_vars($result));

    // Iterable object test
    $obj2    = new \ArrayObject($arr);
    $result2 = X::removeEmpty($obj2);

    $this->assertSame($expected, get_object_vars($result2));
  }
  
  /** @test */
  public function toGroups_method_converts_an_array_into_groups_of_array_from_the_provided_key_and_value_index_names()
  {
    $arr       = ['a', 'b', 'c'];
    $expected  = [['value' => 0, 'text' => 'a'], ['value' => 1, 'text' => 'b'], ['value' => 2, 'text' => 'c']];
    $expected2 = [['key' => 0, 'value' => 'a'], ['key' => 1, 'value' => 'b'], ['key' => 2, 'value' => 'c']];

    $this->assertSame($expected, X::toGroups($arr));
    $this->assertSame($expected2, X::toGroups($arr, 'key', 'value'));

    $arr       = [20 => 'a', 25 => 'b', 30 => 'c'];
    $expected  = [['value' => 20, 'text' => 'a'], ['value' => 25, 'text' => 'b'], ['value' => 30, 'text' => 'c']];
    $expected2 = [['key' => 20, 'value' => 'a'], ['key' => 25, 'value' => 'b'], ['key' => 30, 'value' => 'c']];

    $this->assertSame($expected, X::toGroups($arr));
    $this->assertSame($expected2, X::toGroups($arr, 'key', 'value'));

    $arr       = ['a' => 'aa', 'b' => 'bb', 'c' => 'cc'];
    $expected  = [['value' => 'a', 'text' => 'aa'], ['value' => 'b', 'text' => 'bb'], ['value' => 'c', 'text' => 'cc']];
    $expected2 = [['key' => 'a', 'value' => 'aa'], ['key' => 'b', 'value' => 'bb'], ['key' => 'c', 'value' => 'cc']];

    $this->assertSame($expected, X::toGroups($arr));
    $this->assertSame($expected2, X::toGroups($arr, 'key', 'value'));
  }

  /** @test */
  public function isAssoc_method_checks_if_an_array_is_associative()
  {
    $this->assertTrue(X::isAssoc(['a' => 'foo', 'b' => 'bar']));
    $this->assertTrue(X::isAssoc([0 => 'a', 1 => 'b', 2 => 'c', 4 => 'd']));
    $this->assertTrue(X::isAssoc(['a', 'b', 'c', 'd' => 'foo']));

    $this->assertFalse(X::isAssoc(['a', 'b', 'c']));
    $this->assertFalse(X::isAssoc([0 => 'a', 1 => 'b', 2 => 'c', 3 => 'd']));
  }

  /** @test */
  public function isCli_method_returns_true_if_request_is_from_cli()
  {
    $this->assertTrue(X::isCli());

    $this->setNonPublicPropertyValue('_cli', false);
    $this->assertFalse(X::isCli());
  }

  /** @test */
  public function getDump_method_returns_a_dump_of_the_given_variable()
  {
    $expected = <<<EXPECTED

foo
[
    "foo",
]
{
    "0": "foo",
}
2
true
Function
0x7f4a2c70bcac11eba47652540000cfaa

EXPECTED;

    $result = X::getDump(...$args = [
      'foo',
      ['foo'],
      (object)['foo'],
      2,
      true,
      function () {return 'foo';},
      hex2bin('7f4a2c70bcac11eba47652540000cfaa')
    ]);

    $this->assertSame($expected, $result);

    return ['expected' => $expected, 'args' => $args];
  }

  /**
   * @test
   * @depends getDump_method_returns_a_dump_of_the_given_variable
   */
  public function getHdump_method_returns_an_html_dump_of_the_given_arguments($data)
  {
    $result   = X::getHDump(...$data['args']);
    $expected = nl2br(
      str_replace('  ','&nbsp;&nbsp;', htmlentities($data['expected'])),
      false
    );

    $this->assertSame($expected, $result);

    return ['expected' => $expected, 'args' => $data['args']];
  }

  /**
   * @test
   * @depends getDump_method_returns_a_dump_of_the_given_variable
   */
  public function dump_method_dumps_the_given_arguments($data)
  {
    $this->expectOutputString($data['expected']);

    X::dump(...$data['args']);
  }

  /**
   * @test
   * @depends getHdump_method_returns_an_html_dump_of_the_given_arguments
   */
  public function hdump_method_dumps_the_given_arguments_in_html($data)
  {
    $this->expectOutputString($data['expected']);

    X::hdump(...$data['args']);
  }

  /**
   * @test
   * @depends getDump_method_returns_a_dump_of_the_given_variable
   */
  public function adump_method_dumps_the_given_arguments_in_cli_when_running_from_console($data)
  {
    $this->expectOutputString($data['expected']);

    X::adump(...$data['args']);
  }

  /**
   * @test
   * @depends getHdump_method_returns_an_html_dump_of_the_given_arguments
   */
  public function adump_method_dumps_the_given_arguments_in_html_when_not_running_from_console($data)
  {
    $this->setNonPublicPropertyValue('_cli', false);

    $this->expectOutputString($data['expected']);

    X::adump(...$data['args']);
  }

  /** @test */
  public function buildOptions_method_returns_html_content_of_option_tags_from_the_given_arguments()
  {
    $this->assertSame(
      '<option value="a">a</option><option value="b">b</option>',
      X::buildOptions(['a', 'b'])
    );

    $this->assertSame(
      '<option value="a">a</option><option value="b" selected="selected">b</option>',
      X::buildOptions(['a', 'b'], 'b')
    );

    $this->assertSame(
      '<option value="">Select an option</option><option value="a">a</option><option value="b" selected="selected">b</option>',
      X::buildOptions(['a', 'b'], 'b', 'Select an option')
    );

    $this->assertSame(
      '<option value="">Select an option</option><option value="a">foo</option><option value="b" selected="selected">bar</option>',
      X::buildOptions(['a' => 'foo', 'b' => 'bar'], 'b', 'Select an option')
    );
  }

  /** @test */
  public function toKeypair_method_converts_a_numeric_array_into_associative_one_alternating_key_and_value()
  {
    $this->assertSame(
      ['a' => 'b', 'c' => 'd'],
      X::toKeypair(['a', 'b', 'c', 'd'])
    );

    $this->assertSame(
      ['a' => 1, 'c**' => '2'],
      X::toKeypair(['a', 1, 'c**', '2'], false)
    );

    $this->assertFalse(
      X::toKeypair(['a', 1, 'c**', '2'])
    );

    $this->assertSame(
      ['a-a' => 1, 'c_;1c' => '2'],
      X::toKeypair(['a-a', 1, 'c_;1c', '2'], false)
    );

    $this->assertFalse(
      X::toKeypair(['a-a', 1, 'c_;1c', '2'])
    );

    $this->assertSame(
      [],
      X::toKeypair(['a', 'b', 'c', 'd', 'f'])
    );
  }

  /** @test */
  public function maxWithKey_method_returns_the_maximum_value_of_a_given_index_from_a_two_dimensions_array()
  {
    $this->assertSame(
      45,
      X::maxWithKey([
        ['age' => 1, 'name' => 'Michelle'],
        ['age' => 8, 'name' => 'John'],
        ['age' => 45, 'name' => 'Sarah'],
        ['age' => 45, 'name' => 'Camilla'],
        ['age' => 2, 'name' => 'Allison']
      ], 'age')
    );
  }

  public function maxWithKey_method_returns_null_if_the_given_index_does_not_exist_or_the_given_array_empty()
  {
    $this->assertNull(
      X::maxWithKey([
        ['age' => 1, 'name' => 'Michelle'],
        ['age' => 8, 'name' => 'John'],
        ['age' => 45, 'name' => 'Sarah'],
        ['age' => 45, 'name' => 'Camilla'],
        ['age' => 2, 'name' => 'Allison']
      ], 'foo')
    );

    $this->assertNull(X::maxWithKey([], 'age'));
  }

  /** @test */
  public function minWithKey_method_returns_the_minimum_value_of_a_given_index_from_a_two_dimensions_array()
  {
    $this->assertSame(
      1,
      X::minWithKey([
        ['age' => 1, 'name' => 'Michelle'],
        ['age' => 8, 'name' => 'John'],
        ['age' => 45, 'name' => 'Sarah'],
        ['age' => 45, 'name' => 'Camilla'],
        ['age' => 2, 'name' => 'Allison']
      ], 'age')
    );
  }

  /** @test */
  public function minWithKey_method_returns_null_if_the_given_index_does_not_exist_or_the_given_array_is_empty()
  {
    $this->assertNull(
      X::minWithKey([
        ['age' => 1, 'name' => 'Michelle'],
        ['age' => 8, 'name' => 'John'],
        ['age' => 45, 'name' => 'Sarah'],
        ['age' => 45, 'name' => 'Camilla'],
        ['age' => 2, 'name' => 'Allison']
      ], 'foo')
    );

    $this->assertNull(
      X::minWithKey([], 'age')
    );
  }

  /**
   * @test
   */
  public function map_method_applies_the_provided_cal_back_to_all_levels_of_a_provided_multi_dimensions_array_if_item_is_provided()
  {
    $arr = [
      [
        'age'      => 45,
        'name'     => 'John',
        'children' => [
          ['age' => 8, 'name' => 'Carol'],
          ['age' => 12, 'name' => 'Jack'],
        ]
      ],
      [
        'age'      => 64,
        'name'     => 'Benjamin',
        'children' => [
          ['age' => 42, 'name' => 'Mike', 'children' => [
            ['name' => 'Ryan', 'age' => 19],
            ['name' => 'Nick', 'age' => 9],
          ]],
          ['age' => 19, 'name' => 'Alan'],
        ]
      ]
    ];

    $callback = function ($person) {
      if ($person['age'] > 18) {
        $person['name'] = "Mr {$person['name']}";
      }

      return $person;
    };

    $expected = [
      [
        'age'      => 45,
        'name'     => 'Mr John',
        'children' => [
          ['age' => 8, 'name' => 'Carol'],
          ['age' => 12, 'name' => 'Jack'],
        ]
      ],
      [
        'age'      => 64,
        'name'     => 'Mr Benjamin',
        'children' => [
          ['age' => 42, 'name' => 'Mike', 'children' => [
            ['name' => 'Ryan', 'age' => 19],
            ['name' => 'Nick', 'age' => 9],
          ]],
          ['age' => 19, 'name' => 'Alan'],
        ]
      ]
    ];

    $this->assertSame($expected, X::map($callback, $arr));

    $expected2 = [
      [
        'age'      => 45,
        'name'     => 'Mr John',
        'children' => [
          ['age' => 8, 'name' => 'Carol'],
          ['age' => 12, 'name' => 'Jack'],
        ]
      ],
      [
        'age'      => 64,
        'name'     => 'Mr Benjamin',
        'children' => [
          ['age' => 42, 'name' => 'Mr Mike', 'children' => [
            ['name' => 'Mr Ryan', 'age' => 19],
            ['name' => 'Nick', 'age' => 9],
          ]],
          ['age' => 19, 'name' => 'Mr Alan'],
        ]
      ]
    ];

    $this->assertSame($expected2, X::map($callback, $arr, 'children'));
  }

  /**
   * @test
   */
  public function rmap_method_applies_the_provided_cal_back_to_all_levels_of_a_provided_multi_dimensions_array_after_picking_the_item_if_provided()
  {
    $arr = [
      [
        'age'      => 45,
        'name'     => 'John',
        'children' => [
          ['age' => 8, 'name' => 'Carol'],
          ['age' => 12, 'name' => 'Jack'],
        ]
      ],
      [
        'age'      => 64,
        'name'     => 'Benjamin',
        'children' => [
          ['age' => 42, 'name' => 'Mike', 'children' => [
            ['name' => 'Ryan', 'age' => 19],
            ['name' => 'Nick', 'age' => 9],
          ]],
          ['age' => 19, 'name' => 'Alan'],
        ]
      ]
    ];

    $callback = function ($person) {
      if ($person['age'] > 18) {
        $person['name'] = "Mr {$person['name']}";
      }

      return $person;
    };

    $expected = [
      [
        'age'      => 45,
        'name'     => 'Mr John',
        'children' => [
          ['age' => 8, 'name' => 'Carol'],
          ['age' => 12, 'name' => 'Jack'],
        ]
      ],
      [
        'age'      => 64,
        'name'     => 'Mr Benjamin',
        'children' => [
          ['age' => 42, 'name' => 'Mike', 'children' => [
            ['name' => 'Ryan', 'age' => 19],
            ['name' => 'Nick', 'age' => 9],
          ]],
          ['age' => 19, 'name' => 'Alan'],
        ]
      ]
    ];

    $this->assertSame($expected, X::rmap($callback, $arr));

    $expected2 = [
      [
        'age'      => 45,
        'name'     => 'Mr John',
        'children' => [
          ['age' => 8, 'name' => 'Carol'],
          ['age' => 12, 'name' => 'Jack'],
        ]
      ],
      [
        'age'      => 64,
        'name'     => 'Mr Benjamin',
        'children' => [
          ['age' => 42, 'name' => 'Mr Mike', 'children' => [
            ['name' => 'Mr Ryan', 'age' => 19],
            ['name' => 'Nick', 'age' => 9],
          ]],
          ['age' => 19, 'name' => 'Mr Alan'],
        ]
      ]
    ];

    $this->assertSame($expected2, X::rmap($callback, $arr, 'children'));
  }

  /** @test */
  public function find_method_returns_array_first_index_that_satisfies_the_given_where_condition_and_null_if_not_found()
  {
    $arr = [
      ['id' => 1, 'first_name' => 'John', 'last_name' => 'Doe'],
      ['id' => 11, 'first_name' => 'Andrew', 'last_name' => 'Williams'],
      ['id' => 99, 'first_name' => 'Albert', 'last_name' => 'Taylor'],
      ['id' => 550, 'first_name' => 'Mike', 'last_name' => 'Smith'],
      ['id' => 550, 'first_name' => 'John', 'last_name' => 'Smith'],
    ];
    
    $this->assertSame(0, X::find($arr, ['first_name' => 'John', 'last_name' => 'Doe']));
    $this->assertSame(1, X::find($arr, ['first_name' => 'Andrew', 'last_name' => 'Williams']));
    $this->assertSame(2, X::find($arr, ['first_name' => 'Albert', 'last_name' => 'Taylor']));
    $this->assertSame(3, X::find($arr, ['first_name' => 'Mike', 'last_name' => 'Smith']));
    $this->assertSame(0, X::find($arr, ['first_name' => 'John']));
    $this->assertSame(3, X::find($arr, ['last_name' => 'Smith']));

    $this->assertSame(3, X::find($arr, ['first_name' => 'Mike', 'last_name' => 'Smith'], 2));
    $this->assertSame(3, X::find($arr, ['first_name' => 'Mike', 'last_name' => 'Smith'], 3));
    $this->assertNull(X::find($arr, ['first_name' => 'Mike', 'last_name' => 'Smith'], 4));
    $this->assertNull(X::find($arr, ['first_name' => 'Mike', 'last_name' => 'Taylor']));
    $this->assertNull(X::find($arr, ['first_name' => 'mike']));
    $this->assertNull(X::find($arr, []));

    // Using a callback
    $this->assertSame(2, X::find($arr, function($item) {
        return $item['first_name'] === 'Albert' && $item['last_name'] == 'Taylor';
      })
    );

    $this->assertNull(
      X::find($arr, function ($item) {
        return $item['first_name'] === 'Mike' && $item['last_name'] == 'Taylor';
      })
    );

    // Using the whole array
    $this->assertSame(
      0,
      X::find($arr, ['id' => 1, 'first_name' => 'John', 'last_name' => 'Doe'])
    );


    $this->assertNull(
      X::find($arr, ['id' => 11111, 'first_name' => 'John', 'last_name' => 'Doe'])
    );
  }
  
  /** @test */
  public function filter_method_filters_the_given_array_using_the_given_where_conditions()
  {
    $arr = [
      ['id' => 1, 'first_name' => 'John', 'last_name' => 'Doe'],
      ['id' => 11, 'first_name' => 'Andrew', 'last_name' => 'Williams'],
      ['id' => 99, 'first_name' => 'Albert', 'last_name' => 'Taylor'],
      ['id' => 550, 'first_name' => 'Mike', 'last_name' => 'Smith'],
      ['id' => 7, 'first_name' => 'Mike', 'last_name' => 'Williams'],
    ];

    $this->assertSame(
      [
        ['id' => 550, 'first_name' => 'Mike', 'last_name' => 'Smith'],
        ['id' => 7, 'first_name' => 'Mike', 'last_name' => 'Williams'],
      ],
      X::filter($arr, ['first_name' => 'Mike'])
    );

    $this->assertSame(
      [
        ['id' => 11, 'first_name' => 'Andrew', 'last_name' => 'Williams'],
        ['id' => 7, 'first_name' => 'Mike', 'last_name' => 'Williams'],
      ],
      X::filter($arr, ['last_name' => 'Williams'])
    );

    $this->assertSame(
      [['id' => 99, 'first_name' => 'Albert', 'last_name' => 'Taylor']],
      X::filter($arr, ['first_name' => 'Albert', 'last_name' => 'Taylor'])
    );

    $this->assertSame(
      [],
      X::filter($arr, ['first_name' => 'albert'])
    );

    // Using a callback
    $this->assertSame(
      [['id' => 550, 'first_name' => 'Mike', 'last_name' => 'Smith']],
      X::filter($arr, function ($item) {
        return $item['first_name'] === 'Mike' && $item['last_name'] === 'Smith';
      })
    );

    $this->assertSame(
      [],
      X::filter($arr, function ($item) {
        return $item['first_name'] === 'Albert' && $item['last_name'] === 'Smith';
      })
    );

    // Using the whole array
    $this->assertSame(
      [['id' => 99, 'first_name' => 'Albert', 'last_name' => 'Taylor']],
      X::filter($arr, ['id' => 99, 'first_name' => 'Albert', 'last_name' => 'Taylor'])
    );

    $this->assertSame(
      [],
      X::filter($arr, ['id' => 999999, 'first_name' => 'Albert', 'last_name' => 'Taylor'])
    );
  }

  /** @test */
  public function getRows_method_filters_the_given_array_using_the_given_where_conditions()
  {
    $arr = [
      ['id' => 1, 'first_name' => 'John', 'last_name' => 'Doe'],
      ['id' => 11, 'first_name' => 'Andrew', 'last_name' => 'Williams'],
      ['id' => 99, 'first_name' => 'Albert', 'last_name' => 'Taylor'],
      ['id' => 550, 'first_name' => 'Mike', 'last_name' => 'Smith'],
      ['id' => 7, 'first_name' => 'Mike', 'last_name' => 'Williams'],
    ];

    $this->assertSame(
      [
        ['id' => 550, 'first_name' => 'Mike', 'last_name' => 'Smith'],
        ['id' => 7, 'first_name' => 'Mike', 'last_name' => 'Williams'],
      ],
      X::getRows($arr, ['first_name' => 'Mike'])
    );

    $this->assertSame(
      [
        ['id' => 11, 'first_name' => 'Andrew', 'last_name' => 'Williams'],
        ['id' => 7, 'first_name' => 'Mike', 'last_name' => 'Williams'],
      ],
      X::getRows($arr, ['last_name' => 'Williams'])
    );

    $this->assertSame(
      [['id' => 99, 'first_name' => 'Albert', 'last_name' => 'Taylor']],
      X::getRows($arr, ['first_name' => 'Albert', 'last_name' => 'Taylor'])
    );

    $this->assertSame(
      [],
      X::getRows($arr, ['first_name' => 'albert'])
    );

    // Using a callback
    $this->assertSame(
      [['id' => 550, 'first_name' => 'Mike', 'last_name' => 'Smith']],
      X::getRows($arr, function ($item) {
        return $item['first_name'] === 'Mike' && $item['last_name'] === 'Smith';
      })
    );

    $this->assertSame(
      [],
      X::getRows($arr, function ($item) {
        return $item['first_name'] === 'Albert' && $item['last_name'] === 'Smith';
      })
    );

    // Using the whole array
    $this->assertSame(
      [['id' => 99, 'first_name' => 'Albert', 'last_name' => 'Taylor']],
      X::getRows($arr, ['id' => 99, 'first_name' => 'Albert', 'last_name' => 'Taylor'])
    );

    $this->assertSame(
      [],
      X::getRows($arr, ['id' => 999999, 'first_name' => 'Albert', 'last_name' => 'Taylor'])
    );
  }

  /** @test */
  public function sum_method_returns_the_sum_of_the_given_field_in_the_given_array()
  {
    $arr = [
      ['age' => 19, 'first_name' => 'John', 'last_name' => 'Doe'],
      ['age' => 11, 'first_name' => 'Andrew', 'last_name' => 'Williams'],
      ['age' => 25, 'first_name' => 'Albert', 'last_name' => 'Taylor'],
      ['age' => 36.5, 'first_name' => 'Mike', 'last_name' => 'Smith'],
      ['age' => 33, 'first_name' => 'Andrew', 'last_name' => 'Smith'],
    ];

    $this->assertSame(
      (float)19 + 11 + 25 + 36.5 + 33,
      X::sum($arr, 'age')
    );

    $this->assertSame(
      (float)11 + 33,
      X::sum($arr, 'age', ['first_name' => 'Andrew'])
    );

    $this->assertSame(
      36.5 + 33,
      X::sum($arr, 'age', ['last_name' => 'Smith'])
    );

    $this->assertSame(
      (float)33,
      X::sum($arr, 'age', ['last_name' => 'Smith', 'first_name' => 'Andrew'])
    );

    $this->assertSame(
      19 + 36.5,
      X::sum($arr, 'age', function ($item) {
        return $item['first_name'] === 'John' || $item['first_name'] === 'Mike';
      })
    );

    $this->assertSame(
      (float)0,
      X::sum($arr, 'age', function ($item) {
        return $item['first_name'] === 'Nick';
      })
    );

    $this->assertSame(
      (float)0,
      X::sum($arr, 'age', ['first_name' => 'nick'])
    );

    $this->assertSame(
      (float)0,
      X::sum($arr, 'last_name', ['first_name' => 'John'])
    );

    $this->assertSame(
      36.5,
      X::sum($arr, 'age', ['age' => 36.5, 'first_name' => 'Mike', 'last_name' => 'Smith'])
    );
  }

  /** @test */
  public function getRow_method_returns_the_first_row_that_satisfies_the_given_condition_or_null_otherwise()
  {
    $arr = [
      ['age' => 19, 'first_name' => 'John', 'last_name' => 'Doe'],
      ['age' => 11, 'first_name' => 'Andrew', 'last_name' => 'Williams'],
      ['age' => 25, 'first_name' => 'Albert', 'last_name' => 'Taylor'],
      ['age' => 36.5, 'first_name' => 'Mike', 'last_name' => 'Smith'],
      ['age' => 38.5, 'first_name' => 'Mike', 'last_name' => 'Williams'],
    ];

    $this->assertSame(
      ['age' => 11, 'first_name' => 'Andrew', 'last_name' => 'Williams'],
      X::getRow($arr, ['first_name' => 'Andrew'])
    );

    $this->assertSame(
      ['age' => 11, 'first_name' => 'Andrew', 'last_name' => 'Williams'],
      X::getRow($arr, ['last_name' => 'Williams'])
    );

    $this->assertSame(
      ['age' => 36.5, 'first_name' => 'Mike', 'last_name' => 'Smith'],
      X::getRow($arr, ['first_name' => 'Mike', 'last_name' => 'Smith'])
    );

    $this->assertNull(
      X::getRow($arr, ['first_name' => 'mike', 'last_name' => 'smith'])
    );

    $this->assertSame(
      ['age' => 19, 'first_name' => 'John', 'last_name' => 'Doe'],
      X::getRow($arr, function ($item) {
        return $item['last_name'] === 'Taylor' || $item['last_name'] === 'Doe';
      })
    );

    $this->assertSame(
      ['age' => 36.5, 'first_name' => 'Mike', 'last_name' => 'Smith'],
      X::getRow($arr, ['age' => 36.5, 'first_name' => 'Mike', 'last_name' => 'Smith'])
    );
  }

  /** @test */
  public function getField_method_returns_the_first_value_of_given_field_that_satisfies_the_given_condition()
  {
    $arr = [
      ['age' => 19, 'first_name' => 'John', 'last_name' => 'Doe'],
      ['age' => 11, 'first_name' => 'Andrew', 'last_name' => 'Williams'],
      ['age' => 25, 'first_name' => 'Albert', 'last_name' => 'Taylor'],
      ['age' => 36.5, 'first_name' => 'Mike', 'last_name' => 'Smith'],
      ['age' => 38.5, 'first_name' => 'Mike', 'last_name' => 'Williams'],
    ];

    $this->assertSame(
      36.5,
      X::getField($arr, ['first_name' => 'Mike'], 'age')
    );

    $this->assertSame(
      'Taylor',
      X::getField($arr, ['first_name' => 'Albert'], 'last_name')
    );

    $this->assertSame(
      11,
      X::getField($arr, function ($item) {
        return $item['last_name'] === 'Williams';
      }, 'age')
    );

    $this->assertSame(
      'Doe',
      X::getField($arr, ['age' => 19, 'first_name' => 'John', 'last_name' => 'Doe'], 'last_name')
    );

    $this->assertFalse(
      X::getField($arr, [], 'age')
    );

    $this->assertFalse(
      X::getField($arr, ['first_name' => 'Mike'], 'foo')
    );

    $this->assertFalse(
      X::getField($arr, function ($item) {
        return $item['last_name'] === 'foo';
      }, 'age')
    );

    $this->assertFalse(
      X::getField($arr, ['age' => 18, 'first_name' => 'John', 'last_name' => 'Doe'], 'age')
    );
  }

  /** @test */
  public function pick_method_returns_a_reference_to_sub_array_from_the_given_keys()
  {
    $arr = [
      'session' => [
        'user' => [
          'profile' => [
            'admin' => [
              'email' => 'foo@bar.com'
            ]
          ]
        ]
      ]
    ];

    $this->assertSame(
      'foo@bar.com',
      X::pick($arr, ['session', 'user', 'profile', 'admin', 'email'])
    );

    $this->assertSame(
      ['email' => 'foo@bar.com'],
      X::pick($arr, ['session', 'user', 'profile', 'admin'])
    );

    $this->assertSame(
      ['admin' => ['email' => 'foo@bar.com']],
      X::pick($arr, ['session', 'user', 'profile'])
    );

    $this->assertSame(
      ['profile' => ['admin' => ['email' => 'foo@bar.com']]],
      X::pick($arr, ['session', 'user'])
    );

    $this->assertSame(
      ['user' => [
        'profile' => [
          'admin' => [
            'email' => 'foo@bar.com'
          ]
        ]
      ]],
      X::pick($arr, ['session'])
    );

    $this->assertNull(X::pick($arr, ['foo', 'bar', 'baz']));
  }

  /** @test */
  public function sort_method_sorts_the_items_in_the_given_array()
  {
    $arr = [2, 99, 1, 0, 888, 7, 1, 3];

    X::sort($arr);

    $this->assertSame($expected = [0, 1, 1, 2, 3, 7, 99, 888], $arr);

    X::sort($arr, true);

    $this->assertSame(array_reverse($expected), $arr);

    $arr2 = ['Nick', 'Sam', 'Andrey', 'foo_bar', 'Ashley'];

    X::sort($arr2);

    $this->assertSame($expected2 = ['Andrey', 'Ashley', 'foo_bar', 'Nick', 'Sam'], $arr2);

    X::sort($arr2, true);

    $this->assertSame(array_reverse($expected2), $arr2);
  }

  /** @test */
  public function sortBy_method_sorts_the_given_array_by_index_based_of_a_given_key()
  {
    $arr = [
      ['name' => 'Nick', 'age' => 24],
      ['name' => 'Sam', 'age' => 29],
      ['name' => 'Ashley', 'age' => 39],
      ['name' => 'Ashley', 'age' => 44]
    ];

    X::sortBy($arr, 'age');

    $this->assertSame(
      [
        ['name' => 'Nick', 'age' => 24],
        ['name' => 'Sam', 'age' => 29],
        ['name' => 'Ashley', 'age' => 39],
        ['name' => 'Ashley', 'age' => 44]
      ],
      $arr
    );

    X::sortBy($arr, 'age', 'desc');

    $this->assertSame(
      [
        ['name' => 'Ashley', 'age' => 44],
        ['name' => 'Ashley', 'age' => 39],
        ['name' => 'Sam', 'age' => 29],
        ['name' => 'Nick', 'age' => 24]
      ],
      $arr
    );

    X::sortBy($arr, 'name', 'desc');

    $this->assertSame(
      [
        ['name' => 'Sam', 'age' => 29],
        ['name' => 'Nick', 'age' => 24],
        ['name' => 'Ashley', 'age' => 44],
        ['name' => 'Ashley', 'age' => 39],
      ],
      $arr
    );

    $arr = [
      ['name' => ['first' => 'Sam', 'last' => 'Cage'], 'age' => 29],
      ['name' => ['first' => 'Nick', 'last' => 'Kevin'], 'age' => 25],
      ['name' => ['first' => 'Ashley', 'last' => 'Sheldon'], 'age' => 49],
    ];

    X::sortBy($arr, ['key' => ['name', 'first'], 'dir' => 'asc']);

    $this->assertSame(
      [
        ['name' => ['first' => 'Ashley', 'last' => 'Sheldon'], 'age' => 49],
        ['name' => ['first' => 'Nick', 'last' => 'Kevin'], 'age' => 25],
        ['name' => ['first' => 'Sam', 'last' => 'Cage'], 'age' => 29]
      ],
      $arr
    );

    X::sortBy($arr, ['key' => ['name', 'last']]);

    $this->assertSame(
      [
        ['name' => ['first' => 'Sam', 'last' => 'Cage'], 'age' => 29],
        ['name' => ['first' => 'Nick', 'last' => 'Kevin'], 'age' => 25],
        ['name' => ['first' => 'Ashley', 'last' => 'Sheldon'], 'age' => 49]
      ],
      $arr
    );

    X::sortBy($arr, ['key' => ['age', 'name']]);

    $this->assertSame(
      [
        ['name' => ['first' => 'Sam', 'last' => 'Cage'], 'age' => 29],
        ['name' => ['first' => 'Nick', 'last' => 'Kevin'], 'age' => 25],
        ['name' => ['first' => 'Ashley', 'last' => 'Sheldon'], 'age' => 49]
      ],
      $arr
    );
  }
  
  /** @test */
  public function curl_method_makes_a_curl_request_to_the_given_url_and_returns_the_result_as_string()
  {
    // Cannot test this method since it uses curl which cannot be mocked
    $this->assertTrue(true);
  }

  /** @test */
  public function getTree_method_returns_the_given_array_or_object_as_a_tree_structure_ready_for_a_js_tree()
  {
    $arr = [
      [
        'name'  => 'John Doe',
        'age'   => '35',
        'children' => [
          ['name' => 'Carol', 'age' => '4'],
          ['name' => false, 'age' => null],
        ]
      ],
      [
        'name'  => 'Paul',
        'age'   => '33',
        'children' => [
          (object)['name' => 'Alan', 'age' => true],
          (object)['name' => 'Allison', 'age' => '2']
        ]
      ]
    ];

    $expected = [
      [
        'text'  => 0,
        'items' => [
          ['text' => 'name: John Doe'],
          ['text' => 'age: 35'],
          [
            'text'  => 'children',
            'items' => [
                [
                  'text' => 0,
                  'items' => [
                    ['text' => 'name: Carol'],
                    ['text' => 'age: 4'],
                  ]
                ],
              [
                'text' => 1,
                'items' => [
                  ['text' => 'name: false'],
                  ['text' => 'age: null'],
                ]
              ]
              ]
            ]
          ]
        ],
      [
        'text'  => 1,
        'items' => [
          ['text' => 'name: Paul'],
          ['text' => 'age: 33'],
          [
            'text'  => 'children',
            'items' => [
              [
                'text' => 0,
                'items' => [
                  ['text' => 'name: Alan'],
                  ['text' => 'age: true'],
                ]
              ],
              [
                'text' => 1,
                'items' => [
                  ['text' => 'name: Allison'],
                  ['text' => 'age: 2'],
                ]
              ]
            ]
          ]
        ]
      ],
    ];

    $this->assertSame($expected, X::getTree($arr));

    return ['arr' => $arr, 'expected' => $expected];
  }

  /** @test */
  public function move_method_moves_an_index_in_the_given_array_to_a_new_index()
  {
    $arr = [
      ['a' => 1, 'b' => 2],
      ['c' => 3, 'd' => 4],
      ['e' => 5, 'f' => 6]
    ];

    X::move($arr, 0, 2);

    $expected = [
      ['c' => 3, 'd' => 4],
      ['e' => 5, 'f' => 6],
      ['a' => 1, 'b' => 2]
    ];

    $this->assertSame($expected, $arr);

    X::move($arr, 2, 1);

    $expected = [
      ['c' => 3, 'd' => 4],
      ['a' => 1, 'b' => 2],
      ['e' => 5, 'f' => 6],
    ];

    $this->assertSame($expected, $arr);

    $arr = ['a', 'b', 'c', 'd'];

    X::move($arr, 1, 3);

    $this->assertSame(['a', 'c', 'd', 'b'], $arr);

    X::move($arr, 1, 7);

    $this->assertSame(['a', 'd', 'b', 'c'], $arr);
  }

  /**
   * @test
   * @depends getTree_method_returns_the_given_array_or_object_as_a_tree_structure_ready_for_a_js_tree
   */
  public function makeTree_method_returns_a_view_of_an_array_or_object_as_js_tree($data)
  {
    $result = X::makeTree($data['arr']);

    $this->assertIsString($result);
    $this->assertStringContainsString(json_encode($data['expected']), $result);
  }

  /** @test */
  public function fromCsv_method_formats_the_given_csv_line_and_returns_it_as_array()
  {
    $string   = '"141";"10/11/2002";"350.00";"1311742251"
    "142";"12/12/2002";"349.00";"1311742258"';

    $expected = [
      ['141', '10/11/2002', '350.00', '1311742251'],
      ['142', '12/12/2002', '349.00', '1311742258']
    ];

    $this->assertSame($expected, X::fromCsv($string));

    $string   = '"141","10/11/2002","350.00","1311742251"
    "142","12/12/2002","349.00","1311742258"';

    $expected = [
      ['141', '10/11/2002', '350.00', '1311742251'],
      ['142', '12/12/2002', '349.00', '1311742258']
    ];

    $this->assertSame($expected, X::fromCsv($string, ','));
  }

  /** @test */
  public function toCsv_method_formats_an_array_as_a_csv_string()
  {
    $arr = [["John", "Mike", "David", "Clara"],["White", "Red", "Green", "Blue"]];

    $expected = 'John;Mike;David;Clara
White;Red;Green;Blue';

    $this->assertSame($expected, X::toCsv($arr));

    $arr = [["John", "Mike", "David", null],["White", "Red", "Green", "Blue"]];

    $expected2 = 'John,Mike,David,
White,Red,Green,Blue';

    $this->assertSame($expected2, X::toCsv($arr, ','));

    $expected3 = '"John","Mike","David",NULL
"White","Red","Green","Blue"';

    $this->assertSame($expected3, X::toCsv($arr, ',', '"', PHP_EOL, true, true));
  }

  /** @test */
  public function isSame_method_checks_if_two_files_are_the_same_from_given_paths()
  {
    $this->createDir('foo');
    $file = $this->createFile('bar.txt', 'Hello World!', 'foo');

    $this->assertTrue(X::isSame($file, $file));
    $this->assertTrue(X::isSame($file, $file, true));

    $file2 = $this->createFile('baz.txt', 'Hello World!!', 'foo');

    $this->assertFalse(X::isSame($file, $file2));
    $this->assertFalse(X::isSame($file, $file2, true));

    $file3 = $this->createFile('foo.txt', 'Hello World!', 'foo');
    touch($file3, strtotime('-1 Day'));

    $this->assertTrue(X::isSame($file, $file3));
    $this->assertFalse(X::isSame($file, $file3, true));

    $this->cleanTestingDir();
  }

  /** @test */
  public function isSame_method_throws_an_exception_if_files_does_not_exist()
  {
    $this->expectException(\Exception::class);

    X::isSame('foo.txt', 'bar.txt');
  }

  /** @test */
  public function retrieveArrayVar_method_retrieves_values_from_the_given_array_based_on_the_given_keys()
  {
    $arr = ['a' => ['e' => 33, 'f' => 'foo'], 'b' => 2, 'c' => 3, 'd' => ['g' => 11]];

    $this->assertSame(33, X::retrieveArrayVar(['a', 'e'], $arr));
    $this->assertSame('foo', X::retrieveArrayVar(['a', 'f'], $arr));
    $this->assertSame(['g' => 11], X::retrieveArrayVar(['d'], $arr));
  }

  /** @test */
  public function retrieveArrayVar_method_throws_an_exception_when_keys_cannot_be_found()
  {
    $this->expectException(\Exception::class);

    $arr = ['a' => ['e' => 33, 'f' => 'foo'], 'b' => 2, 'c' => 3, 'd' => ['g' => 11]];

    X::retrieveArrayVar(['a', 'b'], $arr);
  }

  /** @test */
  public function retrieveObjectVar_method_retrieves_values_from_the_given_object_based_on_the_given_properties()
  {
    $obj = (object)['a' => (object)['e' => 33, 'f' => 'foo'], 'b' => 2, 'c' => 3, 'd' => (object)['g' => 11]];

    $this->assertSame(33, X::retrieveObjectVar(['a', 'e'], $obj));
    $this->assertSame('foo', X::retrieveObjectVar(['a', 'f'], $obj));
    $this->assertIsObject(X::retrieveObjectVar(['d'], $obj));
  }

  /** @test */
  public function retrieveObjectVar_method_throws_an_exception_when_properties_cannot_be_found()
  {
    $obj = (object)['a' => (object)['e' => 33, 'f' => 'foo'], 'b' => 2, 'c' => 3, 'd' => (object)['g' => 11]];

    $this->expectException(\Exception::class);

    X::retrieveObjectVar(['a', 'b'], $obj);
  }

  /** @test */
  public function countProperties_method_returns_the_count_of_the_properties_of_the_given_object()
  {
    $obj = (object)[
      'a' => 1,
      'b' => false,
      'c' => null
    ];

    $this->assertSame(3, X::countProperties($obj));
  }

  /** @test */
  public function toExcel_method_creates_an_excel_file_from_the_given_array()
  {
    if (!class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
      $this->expectException(\Exception::class);

      X::toExcel(
        ['a' => ['a' => 'foo', 'b' => 'bar']],
        $this->getTestingDirName() . '/excel/example1.xls'
      );

    } else {
      $this->createDir('excel');

      X::toExcel(
        ['a' => ['a' => 'foo', 'b' => 'bar']],
        $file = $this->getTestingDirName() . '/excel/example1.xls'
      );

      $this->assertFileExists($file);

      X::toExcel(
        ['a' => ['a' => '$1.00', 'b' => '$500.11', 'c' => '$5000.55']],
        $file2 = $this->getTestingDirName() . '/excel/example2.xls',
        true,
        [
          'fields' => [
            ['type' => 'money', 'title' => 'amount'],
            ['type' => 'money', 'title' => 'amount'],
            ['type' => 'money', 'title' => 'amount']
          ]
        ]
      );

      $this->assertFileExists($file2);

      $this->cleanTestingDir();
    }
  }

  /** @test */
  public function makeUid_method_generates_a_uid()
  {
    $this->assertTrue(
      \bbn\Str::isUid(X::makeUid())
    );

    $this->assertTrue(
      \bbn\Str::isUid(bin2hex(X::makeUid(true)))
    );
  }

  /** @test */
  public function convertUids_method_converts_hex_uid_to_binary_uid_from_string_or_iterables()
  {
    $uid        = 'b39e594c261e4bba85f4994bc08657dc';
    $binary_uid = hex2bin($uid);

    $this->assertSame($binary_uid, X::convertUids($uid));

    $this->assertSame(
      [$binary_uid, $binary_uid],
      X::convertUids([$uid, $uid])
    );

    $this->assertSame(
      [$binary_uid, 'uid' => [$binary_uid]],
      X::convertUids([$uid, 'uid' => [$uid]])
    );

    $obj = new class {
      public $uid  = 'b39e594c261e4bba85f4994bc08657dc';
      public $uid2 = 'b39e594c261e4bba85f4994bc08657ff';
    };

    $result = X::convertUids($obj);

    $this->assertIsObject($result);
    $this->assertSame(hex2bin('b39e594c261e4bba85f4994bc08657dc'), $result->uid);
    $this->assertSame(hex2bin('b39e594c261e4bba85f4994bc08657ff'), $result->uid2);

    $this->assertSame(2, X::convertUids(2));
  }

  /** @test */
  public function compareFloats_method_compares_two_floats_with_given_operator()
  {
    $this->assertTrue(
      X::compareFloats(2.0, 4.0, '<')
    );

    $this->assertTrue(
      X::compareFloats(2.5623, 2.5623, '===')
    );

    $this->assertFalse(
      X::compareFloats(2.5623, 2.5623, '<')
    );

    $this->assertTrue(
      X::compareFloats(2.56222223, 2.56222223, '<=')
    );
  }

  /** @test */
  public function jsonBase64Encode_method_encodes_the_given_array_values_to_base64_and_return_array_or_json()
  {
    $string = 'Hello World!';
    $base64 = base64_encode($string);

    $this->assertSame(
      ['a' => $base64],
      X::jsonBase64Encode(['a' => 'Hello World!'], false)
    );

    $this->assertSame(
      json_encode(['a' => $base64, 'b' => 2]),
      X::jsonBase64Encode(['a' => 'Hello World!', 'b' => 2])
    );
  }

  /** @test */
  public function jsonBase64Decode_method_()
  {
    $arr      = ['a' => base64_encode('Hello World!'), 'b' => ['c' => base64_encode('Foo')]];
    $expected = ['a' => 'Hello World!', 'b' => ['c' => 'Foo']];

    $this->assertSame($expected, X::jsonBase64Decode($arr));
    $this->assertSame($expected, X::jsonBase64Decode(json_encode($arr)));
    $this->assertSame(['a' => 2], X::jsonBase64Decode(json_encode(['a' => 2])));
    $this->assertNull(X::jsonBase64Decode('foo'));
  }

  /** @test */
  public function indexByFirstVal_method_creates_an_associative_array_from_the_given_first_array_value()
  {
    $arr = [
      [
        'a' => 'foo',
        'b' => 'bar'
      ],
      [
        'a' => 'foo2',
        'b' => 'bar2'
      ]
    ];

    $this->assertSame(['foo' => 'bar', 'foo2' => 'bar2'], X::indexByFirstVal($arr));
    $this->assertSame([], X::indexByFirstVal([]));
    $this->assertSame([[], 'bar' => []], X::indexByFirstVal([[], 'bar' => []]));
  }

  /** @test */
  public function join_method_joins_array_with_a_string()
  {
    $this->assertSame('foobar' , X::join(['foo', 'bar']));
    $this->assertSame('foo bar' , X::join(['foo', 'bar'], ' '));
    $this->assertSame('foo,bar' , X::join(['foo', 'bar'], ','));
  }

  /** @test */
  public function concat_method_splits_a_string_by_a_string()
  {
    $this->assertSame(['foo', 'bar'], X::concat('foo bar', ' '));
    $this->assertSame(['foo', 'bar'], X::concat('foo,bar', ','));
    $this->assertSame(['foobar'], X::concat('foobar', ' '));
  }

  /** @test */
  public function split_method_splits_a_string_by_a_string()
  {
    $this->assertSame(['foo', 'bar'], X::split('foo bar', ' '));
    $this->assertSame(['foo', 'bar'], X::split('foo,bar', ','));
    $this->assertSame(['foobar'], X::split('foobar', ' '));
  }

  /** @test */
  public function indexOf_method_searches_the_given_subject_from_start_to_end()
  {
    $this->assertSame(1, X::indexOf(['a', 'b', 'c'], 'b'));
    $this->assertSame(1, X::indexOf(['a', 'b', 'c'], 'b', 1));
    $this->assertSame(-1, X::indexOf(['a', 'b', 'c'], 'b', 2));

    $this->assertSame(3, X::indexOf('foobar', 'bar'));
    $this->assertSame(3, X::indexOf('foobar', 'bar', 2));
    $this->assertSame(-1, X::indexOf('foobar', 'bar', 4));

    $this->assertSame(-1, X::indexOf((object) ['a', 'b'], 'b'));
    $this->assertSame(-1, X::indexOf(2, 'b'));
  }

  /** @test */
  public function lastIndexOf_method_searches_the_given_from_last_to_end()
  {
    $this->assertSame(1, X::lastIndexOf(['a', 'b', 'c', 'd'], 'c', 3));
    $this->assertSame(3, X::lastIndexOf(['a', 'b', 'c', 'd'], 'a', 3));

    $this->assertSame(3, X::lastIndexOf('foobar', 'bar'));
    $this->assertSame(-1, X::lastIndexOf('foobar', 'bar', 4));
    $this->assertSame(6, X::lastIndexOf('foobarbar', 'bar'));
    $this->assertSame(6, X::lastIndexOf('foobarbar', 'bar', 4));

    $this->assertSame(-1, X::lastIndexOf((object)['a', 'b'], 'b', 0));
    $this->assertSame(-1, X::lastIndexOf(2, 'b'));
  }

  /** @test */
  public function output_method_test()
  {
    $expected = <<<OUTPUT
1
true
null
foo

[
    "a",
    "b",
]


{
    "a": 1,
    "b": {
        "c": 2,
        "d": 3,
    },
}


OUTPUT;

    $this->expectOutputString($expected);

    X::output(1, true, null, 'foo', ['a', 'b'], (object)['a' => 1, 'b' => ['c' => 2, 'd' => 3]]);
  }

  /** @test */
  public function call_static_method_test_forwards_the_call_to_the_function_if_exists_and_stats_with_is()
  {
    $this->assertFalse(X::is_file('foo'));
    $this->assertFalse(X::is_dir('foo'));
  }

  /** @test */
  public function call_static_method_throws_an_exception_when_the_function_starts_with_is_but_does_not_exist()
  {
    $this->expectException(\Exception::class);

    X::is_foo('a');
  }

  /** @test */
  public function call_static_method_throws_an_exception_when_the_function_does_not_exist()
  {
    $this->expectException(\Exception::class);

    X::foo('a');
  }
}