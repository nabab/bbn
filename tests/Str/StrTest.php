<?php

namespace Str;

use bbn\Str;
use PHPUnit\Framework\TestCase;

class StrTest extends TestCase
{


  /** @test */
  public function cast_method_converts_a_variable_to_a_string()
  {
    $this->assertSame('122', Str::cast(122));
  }


  /** @test */
  public function cast_method_converts_arrays_and_objects_to_empty_string()
  {
    $this->assertSame('', Str::cast(['foo' => 'bar']));
    $this->assertSame('', Str::cast((object)['foo' => 'bar']));
  }


  /** @test */
  public function change_case_method_converts_the_case_of_string()
  {
    // Loser case
    $this->assertSame('foo bar', Str::changeCase('FOO BAR', 'lower'));
    $this->assertSame('foo bar', Str::changeCase('Foo Bar', 'lower'));

    // Upper case
    $this->assertSame('FOO BAR', Str::changeCase('foo bar', 'upper'));
    $this->assertSame('FOO BAR', Str::changeCase('fOO bAR', 'upper'));

    // Title case
    $this->assertSame('Foo Bar', Str::changeCase('foo bar'));
    $this->assertSame('Foo Bar', Str::changeCase('fOO bAR'));

    // Multibyte strings too
    $this->assertSame('bäz', Str::changeCase('BäZ','lower'));
    $this->assertSame('BÄZ', Str::changeCase('Bäz','upper'));
    $this->assertSame('Bäz', Str::changeCase('bÄZ'));
  }


  /** @test */
  public function escapeAllQuotes_methods_escapes_single_and_double_quotes()
  {
    $this->assertSame('foo \\\'bar\\\' \"baz\"', Str::escapeAllQuotes("foo 'bar' \"baz\""));
    $this->assertSame('foo \"bar\"', Str::escapeAllQuotes('foo "bar"'));
    $this->assertSame('foo \\\n \\\t \"bar\"', Str::escapeAllQuotes('foo \n \t "bar"'));
    $this->assertSame('foo \\\n \\\t \\\'bar\\\'', Str::escapeAllQuotes('foo \n \t \'bar\''));
  }


  /** @test */
  public function escapeSquotes_method_escapes_single_quotes()
  {
    $this->assertSame("foo \'bar\'", Str::escapeSquotes("foo 'bar'"));
    $this->assertSame('foo \\\n \\\t \\\'bar\\\'', Str::escapeSquotes('foo \n \t \'bar\''));
    // The alias methods
    $this->assertSame("foo \'bar\'", Str::escapeSquote("foo 'bar'"));
    $this->assertSame("foo \'bar\'", Str::escape("foo 'bar'"));
    $this->assertSame("foo \'bar\'", Str::escapeApo("foo 'bar'"));
  }


  /** @test */
  public function escapeDquotes_method_escapes_double_quotes()
  {
    $this->assertSame('foo \"bar\"', Str::escapeDquotes('foo "bar"'));
    $this->assertSame('foo \\\n \\\t \"bar\"', Str::escapeDquotes('foo \n \t "bar"'));
    // The alias methods
    $this->assertSame('foo \"bar\"', Str::escapeQuote('foo "bar"'));
    $this->assertSame('foo \"bar\"', Str::escapeQuotes('foo "bar"'));
  }


  /** @test */
  public function unescapesquotes_method_unescapes_quoted_strings()
  {
    $this->assertSame('foo "bar"', Str::unescapeSquote('foo \"bar\"'));
    $this->assertSame('foo \n \t "bar"', Str::unescapeSquote('foo \\\n \\\t \"bar\"'));
    $this->assertSame("foo 'bar' \"baz\"", Str::unescapeSquote('foo \'bar\' \"baz\"'));

    // The alias method
    $this->assertSame('foo "bar"', Str::unescapeSquotes('foo \"bar\"'));
    $this->assertSame('foo \n \t "bar"', Str::unescapeSquotes('foo \\\n \\\t \"bar\"'));
    $this->assertSame("foo 'bar' \"baz\"", Str::unescapeSquotes('foo \'bar\' \"baz\"'));
  }


  /** @test */
  public function clean_method_cleans_a_string_based_on_configurations()
  {
    $string = 'foo      bar
    bäz';

    $this->assertSame('foo bar\n bäz', Str::clean($string, 'all'));

    $string = "foo bar 
    
    
    
    bäz";

    $this->assertSame(
      'foo bar
    bäz', Str::clean($string, '2nl')
    );

    $this->assertSame('foo bar bäz', Str::clean($string, 'html'));

  }


  /** @test  */
  public function cut_method_strips_html_and_php_tags_from_a_string()
  {
    $string = "<h1>foo bäz</h1> Example text <b>Foobar. </b>";
    $this->assertSame('foo bäz Example...', Str::cut($string));
    $this->assertSame('foo bä...', Str::cut($string, 6));
    $this->assertSame('foo bäz Example text Foobar.', Str::cut($string, 50));
  }


  /** @test */
  public function sanitize_method_strips_special_character_except_for_specific_characters()
  {
    $string = "foo bär 1256 - ~ , ; [ ] ( ) .+*='";
    $this->assertSame('foo bär 1256 - ~ , ; [ ] ( ) .', Str::sanitize($string));

    // Removes more than two trailing periods
    $string = "foo bär 1256 - ~ , ; [ ] ( ) .+*='...";
    $this->assertSame('foo bär 1256 - ~ , ; [ ] ( ) ', Str::sanitize($string));
  }


  /** @test */
  public function encodeFilename_method_returns_a_cross_platform_filename_for_a_file()
  {
    $string = 'test" "file/,1 è..txt';
    // Remove accents and non allowed characters like quotes and dots
    $this->assertSame('test_file_1_e.txt', Str::encodeFilename($string));

    $string = 'foo bar baz..html';
    // Actual file extensions override the $extension parameter
    $this->assertSame('foo_bar_baz.html', Str::encodeFilename($string));

    $string = 'foo/bar baz..html';
    //  Slashes should be authorized in the string if $is_path is true
    $this->assertSame('foo/bar_baz.html', Str::encodeFilename($string, true));
    // String length taken into consideration
    $this->assertSame('foo/b.html', Str::encodeFilename($string, 5, true));
  }


  /** @test */
  public function encodeDbname_method_returns_a_corrected_string_for_db_naming()
  {
    $this->assertSame(
      'my_database_name_test_plus',
      Str::encodeDbname('my.database_name ? test  :,; !plus')
    );

    $this->assertSame(
      'my_database_name_test_e_plus',
      Str::encodeDbname('my.database_name ? test_è  :,; !plus')
    );
  }


  /** @test */
  public function fileExt_method_returns_the_file_extension()
  {
    $this->assertSame('txt', Str::fileExt("/test/test.txt"));
    $this->assertSame('txt', Str::fileExt("/test/test.TXT"));

    // Returns an array of file and extension when $ar is true
    $this->assertSame(['test', 'txt'], Str::fileExt('/home/user/Desktop/test.txt', true));
  }


  /** @test */
  public function genpwd_method_returns_a_random_password()
  {
    $this->assertIsString($password = Str::genpwd());
    // Default min and max
    $this->assertTrue(strlen($password) >= 6 && strlen($password) <= 12);

    $password = Str::genpwd(10, 3);
    $this->assertTrue(strlen($password) >= 3 && strlen($password) <= 10);
  }

/** @test */
  public function isJson_method_checks_if_a_string_is_json()
  {
    $this->assertTrue(Str::isJson('{"firstName": "John", "lastName": "Smith", "age": 25}'));
    $this->assertFalse(Str::isJson('foo bar'));
    $this->assertFalse(Str::isJson(['foo', 'bar']));
    $this->assertFalse(Str::isJson((object)['foo', 'bar']));
    $this->assertFalse(Str::isJson(12));
  }

  /** @test */
  public function isNumber_method_checks_if_item_is_a_number()
  {
    $this->assertFalse(Str::isNumber());
    $this->assertTrue(Str::isNumber(3));
    $this->assertTrue(Str::isNumber(1.3));
    $this->assertFalse(Str::isNumber('foo'));
    $this->assertFalse(Str::isNumber(['foo', 'bar']));
  }

  /** @test */
  public function isInteger_method_checks_if_item_is_an_integer()
  {
    $this->assertTrue(Str::isInteger(1));
    $this->assertFalse(Str::isInteger(1.44));
    $this->assertFalse(Str::isInteger('foo bar'));
    $this->assertFalse(Str::isInteger(['foo', 'bar']));
  }
}
