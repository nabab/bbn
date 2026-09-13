<?php

namespace bbn\tests\Str;

use bbn\Str;
use PHPUnit\Framework\TestCase;

class StrTest extends TestCase
{

  protected $app_ids = [
    "7f4a2c70bcac11eba47652540000cfbe"
  ];


  /** @test */
  public function testCastMethodConvertsAVariableToAString()
  {
    $this->assertSame('122', Str::cast(122));
  }


  /** @test */
  public function testCastMethodConvertsArraysAndObjectsToEmptyString()
  {
    $this->assertSame('', Str::cast(['foo' => 'bar']));
    $this->assertSame('', Str::cast((object)['foo' => 'bar']));
  }


  /** @test */
  public function testChangeCaseMethodConvertsTheCaseOfString()
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
  public function testEscapeallquotesMethodsEscapesSingleAndDoubleQuotes()
  {
    $this->assertSame('foo \\\'bar\\\' \"baz\"', Str::escapeAllQuotes("foo 'bar' \"baz\""));
    $this->assertSame('foo \"bar\"', Str::escapeAllQuotes('foo "bar"'));
    $this->assertSame('foo \\\n \\\t \"bar\"', Str::escapeAllQuotes('foo \n \t "bar"'));
    $this->assertSame('foo \\\n \\\t \\\'bar\\\'', Str::escapeAllQuotes('foo \n \t \'bar\''));
  }


  /** @test */
  public function testEscapesquotesMethodEscapesSingleQuotes()
  {
    $this->assertSame("foo \'bar\'", Str::escapeSquotes("foo 'bar'"));
    $this->assertSame('foo \\\n \\\t \\\'bar\\\'', Str::escapeSquotes('foo \n \t \'bar\''));
    // The alias methods
    $this->assertSame("foo \'bar\'", Str::escapeSquote("foo 'bar'"));
    $this->assertSame("foo \'bar\'", Str::escape("foo 'bar'"));
    $this->assertSame("foo \'bar\'", Str::escapeApo("foo 'bar'"));
  }


  /** @test */
  public function testEscapedquotesMethodEscapesDoubleQuotes()
  {
    $this->assertSame('foo \"bar\"', Str::escapeDquotes('foo "bar"'));
    $this->assertSame('foo \\\n \\\t \"bar\"', Str::escapeDquotes('foo \n \t "bar"'));
    // The alias methods
    $this->assertSame('foo \"bar\"', Str::escapeQuote('foo "bar"'));
    $this->assertSame('foo \"bar\"', Str::escapeQuotes('foo "bar"'));
  }


  /** @test */
  public function testUnescapesquotesMethodUnescapesQuotedStrings()
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
  public function testCleanspacesTrimsAndRemoveExtraSpaces()
  {
    $this->assertSame(
      'Hello World !!!', Str::cleanSpaces(
        ' Hello     World
      !!!    '
      )
    );
  }


  /** @test  */
  public function testCutMethodStripsHtmlAndPhpTagsFromAString()
  {
    $string = "<h1>foo bäz</h1> Example text <b>Foobar. </b>";
    $this->assertSame('foo bäz Example...', Str::cut($string));
    $this->assertSame('foo bä...', Str::cut($string, 6));
    $this->assertSame('foo bäz Example text Foobar.', Str::cut($string, 50));
  }


  /** @test */
  public function testSanitizeMethodStripsSpecialCharacterExceptForSpecificCharacters()
  {
    $string = "foo bär 1256 - ~ , ; [ ] ( ) .+*='";
    $this->assertSame('foo bär 1256 - ~ , ; [ ] ( ) .', Str::sanitize($string));

    // Removes more than two trailing periods
    $string = "foo bär 1256 - ~ , ; [ ] ( ) .+*='...";
    $this->assertSame('foo bär 1256 - ~ , ; [ ] ( ) ', Str::sanitize($string));
  }


  /** @test */
  public function testEncodefilenameMethodReturnsACrossPlatformFilenameForAFile()
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
  public function testEncodedbnameMethodReturnsACorrectedStringForDbNaming()
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
  public function testFileextMethodReturnsTheFileExtension()
  {
    $this->assertSame('txt', Str::fileExt("/test/test.txt"));
    $this->assertSame('txt', Str::fileExt("/test/test.TXT"));

    // Returns an array of file and extension when $ar is true
    $this->assertSame(['test', 'txt'], Str::fileExt('/home/user/Desktop/test.txt', true));
  }


  /** @test */
  public function testGenpwdMethodReturnsARandomPassword()
  {
    $this->assertIsString($password = Str::genpwd());
    // Default min and max
    $this->assertTrue(Str::len($password) >= 6 && Str::len($password) <= 12);

    $password = Str::genpwd(10, 3);
    $this->assertTrue(Str::len($password) >= 3 && Str::len($password) <= 10);
  }


  /** @test */
  public function testIsjsonMethodChecksIfAStringIsJson()
  {
    $this->assertTrue(Str::isJson('{"firstName": "John", "lastName": "Smith", "age": 25}'));
    $this->assertFalse(Str::isJson('foo bar'));
    $this->assertFalse(Str::isJson(['foo', 'bar']));
    $this->assertFalse(Str::isJson((object)['foo', 'bar']));
    $this->assertFalse(Str::isJson(12));
  }


  /** @test */
  public function testIsnumberMethodChecksIfItemIsANumber()
  {
    $this->assertFalse(Str::isNumber());
    $this->assertTrue(Str::isNumber(3));
    $this->assertTrue(Str::isNumber(1.3));
    $this->assertFalse(Str::isNumber('foo'));
    $this->assertFalse(Str::isNumber(['foo', 'bar']));
  }


  /** @test */
  public function testIsintegerMethodChecksIfItemIsAnInteger()
  {
    $this->assertTrue(Str::isInteger(1));
    $this->assertFalse(Str::isInteger(1.44));
    $this->assertFalse(Str::isInteger('foo bar'));
    $this->assertFalse(Str::isInteger(['foo', 'bar']));
  }


  /** @test */
  public function testIscleanpathMethodChecksIfAPathIsValid()
  {
    $this->assertTrue(Str::isCleanPath('/home/user/Images'));
    $this->assertFalse(Str::isCleanPath('../home/user/Images'));
    $this->assertFalse(Str::isCleanPath('..\\home\user\Image'));
    $this->assertFalse(Str::isCleanPath(['foo' => 'bar']));
  }


  /** @test */
  public function testIsdecimalMethodChecksIfItemIsADecimal()
  {
    $this->assertTrue(Str::isDecimal(11.4));
    $this->assertTrue(Str::isDecimal(11.40));
    $this->assertTrue(Str::isDecimal('11.4'));
    $this->assertFalse(Str::isDecimal(11));
    $this->assertFalse(Str::isDecimal('foo'));
    $this->assertFalse(Str::isDecimal(['foo' => 'bar']));
    $this->assertFalse(Str::isDecimal((object)['foo' => 'bar']));
  }


  /** @test */
  public function testIsuidMethodChecksIfAStringIsValidUid()
  {
    $this->assertTrue(Str::isUid($this->app_ids[0]));
    $this->assertFalse(Str::isUid('22e4f42122e4f4212'));
    $this->assertFalse(Str::isUid('foo'));
    $this->assertFalse(Str::isUid(['foo' => 'bar']));
  }


  /** @test */
  public function testIsbuidMethodChecksIfAStringIsAValidBinaryUid()
  {
    $this->assertTrue(Str::isBuid(hex2bin($this->app_ids[0])));
    $this->assertFalse(Str::isBuid(hex2bin($this->app_ids[0] . 'ee')));
    $this->assertFalse(Str::isBuid(hex2bin(Str::sub($this->app_ids[0], 0, -2))));
    $this->assertFalse(Str::isBuid('akvotlarpvkrlgta'));
  }


  /** @test */
  public function testIsemailMethodChecksIfAStringIsValidEmailAddress()
  {
    $this->assertTrue(Str::isEmail('foo@bar.com'));
    $this->assertFalse(Str::isEmail('foo@bar'));
    $this->assertFalse(Str::isEmail('foo.bar'));
    $this->assertFalse(Str::isEmail('foo'));
  }


  /** @test */
  public function testIsurlMethodChecksIfAStringIsAValidUrl()
  {
    $this->assertTrue(Str::isUrl('http://foo.bar'));
    $this->assertFalse(Str::isUrl('foo.bar'));
    $this->assertFalse(Str::isUrl('foo@bar.com'));
  }


  /** @test */
  public function testIsdomainMethodChecksIfAStringIsAValidDomainName()
  {
    $this->assertTrue(Str::isDomain('foo.bar'));
    $this->assertFalse(Str::isDomain('http://foo.bar'));
  }


  /** @test */
  public function testIsipMethodChecksIfAStringIsAValidIpAddress()
  {
    $this->assertTrue(Str::isIp('198.162.0.1'));
    $this->assertTrue(Str::isIp('29e4:4068:a401:f273:dcec:af8f:c8b3:c01c'));
    $this->assertFalse(Str::isIp('foo'));
    $this->assertFalse(Str::isIp('98.162'));
    $this->assertFalse(Str::isIp('29e4:4068:a401:f273'));
  }


  /** @test */
  public function testIsdatesqlMethodChecksIfAStringIsAValidSqlDateFormat()
  {
    $this->assertTrue(Str::isDateSql('2021-05-24'));
    $this->assertTrue(Str::isDateSql('2021-05-24 23:12:23'));
    $this->assertFalse(Str::isDateSql('24/05/2021'));
    $this->assertFalse(Str::isDateSql('24/05/2021 12:11'));
    $this->assertFalse(Str::isDateSql('foo'));
  }


  /** @test */
  public function testCorrecttypesMethodReturnsTheCorrectType()
  {
    $this->assertSame(12, Str::correctTypes(12));
    $this->assertSame(12, Str::correctTypes('12'));
    $this->assertSame(-12, Str::correctTypes('-12'));
    $this->assertSame(12.6, Str::correctTypes('12.60'));
    $this->assertSame(12.605, Str::correctTypes('12.605'));
    $this->assertSame(
      $this->app_ids[0],
      Str::correctTypes(hex2bin($this->app_ids[0]))
    );
    $this->assertSame(
      json_encode(['foo' => 'bar']),
      Str::correctTypes(json_encode(['foo' => 'bar']))
    );

    $arr = Str::correctTypes(['14.60', '-14']);

    $this->assertSame(14.6, $arr[0]);
    $this->assertSame(-14, $arr[1]);

    $obj = Str::correctTypes((object)['foo' => '14.60', 'bar' => '-14']);

    $this->assertSame(14.6, $obj->foo);
    $this->assertSame(-14, $obj->bar);

  }


  /** @test */
  public function testParseurlMethodReturnsAnArrayOfTheProvidedUrlComponent()
  {
    $result = Str::parseUrl(
      'http://localhost/phpmyadmin/?db=test&table=users&server=1&target=&token=e45a102c5672b2b4fe84ae75d9148981'
    );

    $expected = [
      'scheme' => 'http',
      'host'   => 'localhost',
      'path'   => '/phpmyadmin/',
      'query'  => 'db=test&table=users&server=1&target=&token=e45a102c5672b2b4fe84ae75d9148981',
      'url'    => 'http://localhost/phpmyadmin/',
      'params' => [
        'db'     => 'test',
        'table'  => 'users',
        'server' => '1',
        'target' => '',
        'token'  => 'e45a102c5672b2b4fe84ae75d9148981',
      ]
    ];

    $this->assertSame($expected, $result);
  }


  /** @test */
  public function testParsepathMethodReplacesBackslashesToSlashes()
  {
    $this->assertSame('/home/user/Desktop', Str::parsePath('\home\user\Desktop'));
    $this->assertSame('', Str::parsePath('..\home\user\Desktop'));
    $this->assertSame('home/user/Desktop', Str::parsePath('..\home\user\Desktop', true));
  }


  /** @test */
  public function testRemoveaccentsMethodRemovesAccesntsFromCharacters()
  {
    $this->assertSame('TA¨st FA¬lA¨ A²A¨A A¹e', Str::removeAccents("TÃ¨st FÃ¬lÃ¨ Ã²Ã¨Ã Ã¹è"));
    $this->assertSame('baz', Str::removeAccents('bäz'));
    $this->assertSame('BAZ', Str::removeAccents('BÄZ'));
  }


  /** @test */
  public function testChecknameMethodChecksIfAStringCompliesWithSqlNamingConvention()
  {
    $this->assertTrue(Str::checkName('foobar'));
    $this->assertTrue(Str::checkName('foo_bar'));
    $this->assertTrue(Str::checkName('foo_bar_12'));
    $this->assertFalse(Str::checkName('foo bar'));
    $this->assertFalse(Str::checkName(2));
    $this->assertFalse(Str::checkName(['foo' => 'bar']));
  }


  /** @test */
  public function testCheckfilenameMethodChecksIfAStringDoesNotContainAFilesystemPath()
  {
    $this->assertTrue(Str::checkFilename('foo'));
    $this->assertFalse(Str::checkFilename('foo/'));
    $this->assertFalse(Str::checkFilename('foo\\'));
    $this->assertFalse(Str::checkFilename('foo/bar'));
    $this->assertFalse(Str::checkFilename('foo\bar'));
    $this->assertFalse(Str::checkFilename('../foo'));
    $this->assertFalse(Str::checkFilename('foo/../bar/.../baz/'));
    $this->assertFalse(Str::checkFilename('..'));
    $this->assertFalse(Str::checkFilename('.'));
    $this->assertFalse(Str::checkFilename(2));
    $this->assertFalse(Str::checkFilename(['foo' => 'bar']));
  }


  /** @test */
  public function testCheckpathMethodChecksIfEveryBitOfAStringDoesNotContainAFilesystemPath()
  {
    $this->assertTrue(Str::checkPath('foo'));
    $this->assertTrue(Str::checkPath('foo/bar'));
    $this->assertFalse(Str::checkPath('foo/'));
    $this->assertFalse(Str::checkPath('foo\\'));
    $this->assertFalse(Str::checkPath('foo/../bar'));
    $this->assertFalse(Str::checkFilename('foo/../bar/.../baz/'));
    $this->assertFalse(Str::checkPath('.'));
    $this->assertFalse(Str::checkPath('..'));
    $this->assertFalse(Str::checkPath(2));
    $this->assertFalse(Str::checkPath(['foo' => 'bar']));
  }


  /** @test */
  public function testHasslashMethodChecksIfASlashOrBackslashIsPresent()
  {
    $this->assertTrue(Str::hasSlash('foo/bar'));
    $this->assertTrue(Str::hasSlash('foo\bar'));
    $this->assertTrue(Str::hasSlash('foo\\'));
    $this->assertTrue(Str::hasSlash('foo/'));
    $this->assertFalse(Str::hasSlash('foo_bar'));
    $this->assertFalse(Str::hasSlash(2));
  }


  /** @test */
  public function testGetnumbersMethodExtractsAllDigitsFromAString()
  {
    $this->assertSame('123', Str::getNumbers('foo 12 bar 3'));
    $this->assertSame('', Str::getNumbers('foo bar'));
  }


  /** @test */
  public function testMakereadableMethod()
  {
    $object = (object)[
      'foo' => 'bar',
      'baz' => (object)[
        'bar' => 'foo',
        'baz' => '',
        'arr' => [1, 2, 3]
      ]
    ];

    $this->assertSame(
      ['foo' => 'bar', 'baz' => ['bar' => 'foo', 'baz' => '', 'arr' => [1, 2, 3]]],
      Str::makeReadable($object)
    );

    $arr = [
      'object' => $object,
      'timer'  => new \bbn\Util\Timer(),
      'foo'    => 'bar',
      'baz'    => 2
    ];

    $this->assertSame(
      [
      'object' => [
        'foo'  => 'bar',
        'baz'  => [
          'bar' => 'foo',
          'baz' => '',
          'arr' => [1, 2, 3]
        ]
      ],
      'timer'  => 'bbn\Util\Timer',
      'foo'    => 'bar',
      'baz'    => 2
      ], Str::makeReadable($arr)
    );
  }


  /** @test */
  public function testExportMethodReturnsAVariableInAModeUsableByPhp()
  {
    $object = (object)[
      'foo' => 'bar',
      'baz' => (object)[
        'bar'  => 'foo',
        'baz'  => '',
        'arr'  => [1, 2, 3],
        'buid' => hex2bin($this->app_ids[0]),
        'timer' => new \bbn\Util\Timer(),
        'obj'   => (object)['foo' => 'bar']
      ]
    ];

    $this->assertSame(
      '{
    "foo": "bar",
    "baz": {
        "bar": "foo",
        "arr": [
            1,
            2,
            3,
        ],
        "buid": 0x' . $this->app_ids[0] . ',
        "timer": Object bbn\Util\Timer,
        "obj": {
            "foo": "bar",
        },
    },
}', Str::export($object,true)
    );

    $this->assertSame(
      '{
    "foo": "bar",
    "baz": {
        "bar": "foo",
        "baz": "",
        "arr": [
            1,
            2,
            3,
        ],
        "buid": 0x' . $this->app_ids[0] . ',
        "timer": Object bbn\Util\Timer,
        "obj": {
            "foo": "bar",
        },
    },
}', Str::export($object,false)
    );

    $this->assertSame(
      '{
    "foo": "bar",
    "baz": "",
}',
      Str::export(['foo' => 'bar', 'baz' => ''])
    );

    $this->assertSame(
      '{
    "foo": "bar",
}',
      Str::export(['foo' => 'bar', 'baz' => ''], true)
    );
  }


  /** @test */
  public function testReplaceonceMethodReplacesPartOfAString()
  {
    $this->assertSame('bar', Str::replaceOnce('foo ', '', 'foo bar'));
    $this->assertSame('foo baz', Str::replaceOnce('bar', 'baz', 'foo bar'));
    $this->assertSame('foo bar', Str::replaceOnce('baz ', '', 'foo bar'));
  }


  /** @test */
  public function testRemovecommentsMethodRemovesCommentFromAString()
  {
    $this->assertSame('', Str::removeComments("<!--this is a comment-->"));
    $this->assertSame('', Str::removeComments("//this is a comment"));
    $this->assertSame('', Str::removeComments("/** this is a comment */"));
    $this->assertSame('', Str::removeComments("/* this is a comment */"));

    $this->assertSame(
      '', Str::removeComments(
        "// this is a comment
    // this is a second comment"
      )
    );

    $this->assertSame(
      'foobar', Str::removeComments(
        "// this is a comment
    foobar"
      )
    );

    $this->assertSame(
      'foobar', Str::removeComments(
        "// this is a comment
    foobar
    // this is a second comment"
      )
    );

    $this->assertSame(
      'foobar', Str::removeComments(
        "/** this is a comment */
    foobar
    /* this is a second comment */
    <!--this is a comment-->"
      )
    );
  }


  /** @test */
  public function testSaysizeMethodConvertsBytesToAnotherUnit()
  {
    $this->assertSame('46.57 G', Str::saySize(50000000000, 'G'));
    $this->assertSame('46.57 G', Str::saySize(50000000000, 'g'));
    $this->assertSame('1024 B', Str::saySize(1024, 'B'));
    $this->assertSame('1048576 B', Str::saySize(1048576, 'B'));
    $this->assertSame('1.00 M', Str::saySize(1048576, 'M'));
    $this->assertSame('1 M', Str::saySize(1048576, 'M', 0));
    $this->assertSame('1.00 G', Str::saySize(1024 * 1024 * 1024, 'G'));
    $this->assertSame('1.00 T', Str::saySize(1024 * 1024 * 1024 * 1024, 'T'));
    $this->assertSame('0.000977 G', Str::saySize(1048576, 'G', 6));
    $this->assertSame('0.000001 T', Str::saySize(1048576, 'T', 6));
    $this->assertSame('0.000001 T', Str::saySize(1048576, 't', 6));
  }


  /** @test */
  public function testSaysizeMethodThrowsAnExceptionWhenUnitIsInvalid()
  {
    $this->expectException(\Exception::class);
    Str::saySize(1048576, 'GG');
  }


  /** @test */
  public function testConvertsizeMethodConvertsSizeFromUnitToAnother()
  {
    $this->assertSame('1073741824B', Str::convertSize(1, 'GB', 'B'));
    $this->assertSame('1048576MB', Str::convertSize(1024, 'GB', 'MB'));
    $this->assertSame('1048576MB', Str::convertSize(1024, 'G', 'M'));

    $this->assertSame('1MB', Str::convertSize(1048576, 'B', 'MB'));
    $this->assertSame('1MB', Str::convertSize(1048576, 'B', 'M'));
    $this->assertSame('1GB', Str::convertSize(1024 * 1024 * 1024, 'B', 'GB'));
    $this->assertSame('1GB', Str::convertSize(1024 * 1024 * 1024, 'B', 'G'));

    $this->assertSame('1024GB', Str::convertSize(1048576, 'MB', 'GB'));
    $this->assertSame('0.47684TB', Str::convertSize(500000, 'MB', 'TB', 5));

    $this->assertSame('1048576MB', Str::convertSize(1073741824, 'KB', 'MB'));
    $this->assertSame('1TB', Str::convertSize(1073741824, 'KB', 'TB'));
    $this->assertSame('1099511627776B', Str::convertSize(1073741824, 'KB', 'B'));

    $this->assertSame('1048576MB', Str::convertSize(1, 'TB', 'MB'));
    $this->assertSame('1024GB', Str::convertSize(1, 'TB', 'GB'));
    $this->assertSame('1024GB', Str::convertSize(1, 'T', 'G'));
  }


  /** @test */
  public function testConvertsizeMethodThrowsExceptionIfOriginalUnitIsInvalid()
  {
    $this->expectException(\Exception::class);

    Str::convertSize(1, 'AA', 'GB');
  }


  /** @test */
  public function testConvertsizeMethodThrowsExceptionIfDestinationUnitIsInvalid()
  {
    $this->expectException(\Exception::class);

    Str::convertSize(1, 'MB', 'AA');
  }


  /** @test */
  public function testCheckjsonMethodChecksIfAStringIsAValidJson()
  {
    $this->assertTrue(Str::checkJson(json_encode(['foo' => 'bar'])));
    $this->assertFalse(Str::checkJson('foo'));
    $this->assertIsString(Str::checkJson('bar', true));
    $this->assertSame("Syntax error, malformed JSON", Str::checkJson('bar', true));
  }


  /** @test */
  public function testAsvarMethodPlacesQuotesAroundAString()
  {
    $this->assertSame('"foo"', Str::asVar("foo"));
    $this->assertSame("'foo'", Str::asVar("foo", "'"));

    $this->assertSame('"foo\'bar"', Str::asVar("foo'bar"));
    $this->assertSame('"foo\"bar"', Str::asVar("foo\"bar"));

    $this->assertSame("'foo\'bar'", Str::asVar("foo'bar", "'"));
    $this->assertSame("'foo\'bar'", Str::asVar("foo'bar", "'"));
  }


  /** @test */
  public function testMarkdown2htmlMethodTransformsMarkdownToHtml()
  {
    $this->assertSame('<h1>foo</h1>', Str::markdown2html("# foo"));
    $this->assertSame('<h2>foo</h2>', Str::markdown2html("## foo"));
    $this->assertSame('## foo', Str::markdown2html("## foo", true));
    $this->assertSame('<p><strong>foo</strong></p>', Str::markdown2html("**foo**"));
    $this->assertSame('<strong>foo</strong>', Str::markdown2html("**foo**", true));
  }


  /** @test */
  public function testTocamelMethodConvertAStringToCamelCase()
  {
    $this->assertSame('fooBar', Str::toCamel('foo bar'));
    $this->assertSame('fooBarBaz', Str::toCamel('fOo BaR bAz'));
    $this->assertSame('foobar', Str::toCamel('FOOBAR'));
  }


  /** @test */
  public function testHtml2textMethodConvertsHtmlToTextReplacingParagraphsAndNewLines()
  {
    $this->assertSame(
      'foo bar
baz',
      Str::html2text('<h1>foo bar</h1><br>baz')
    );

    $this->assertSame('foo bar', Str::html2text('<h1>foo bar</h1><br>', false));
    $this->assertSame('foo bar baz', Str::html2text('<h1>foo bar</h1> <p>baz</p><br>', false));
  }


  /** @test */
  public function testText2htmlConvertsTextToHtmlReplacingNewLines()
  {
    $this->assertSame('<p>foo<br> bar</p>', Str::text2html("foo\n bar"));
    $this->assertSame('foo<br> bar', Str::text2html("foo\n bar", false));
  }


}
