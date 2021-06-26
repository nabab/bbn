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

    $this->assertTrue(X::hasDeepProp($arr, ['foo', 'bar1']));
    $this->assertFalse(X::hasDeepProp($arr, ['foo', 'bar1'], true));
    $this->assertFalse(X::hasDeepProp($arr, ['foo', 'bar2']));
    $this->assertFalse(X::hasDeepProp($arr, ['foo', 'bar2', true]));
    $this->assertTrue(X::hasDeepProp($arr, ['foo'], true));
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


}