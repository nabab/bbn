<?php

namespace X;

use bbn\X;
use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;
use tests\Files;
use tests\Reflectable;

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
        'c' => 2,
        'd' => 3
      ]
    ];

    $this->assertJson($result = X::jsObject($arr));
    $this->assertIsArray($result_arr = json_decode($result, true));
    $this->assertArrayHasKey('a', $result_arr);
    $this->assertArrayHasKey('b', $result_arr);
    $this->assertSame(1, $result_arr['a']);
    $this->assertSame(['c' => 2, 'd' => 3], $result_arr['b']);
  }
}