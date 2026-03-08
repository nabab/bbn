<?php

use bbn\Date;
use PHPUnit\Framework\TestCase;

class DateTest extends TestCase
{
  /** @test */
  public function testMonthnameMethodReturnsMonthNameFromTheGivenMonthAndLocale()
  {
    $this->assertSame('janvier', Date::monthName('01', 'fr_FR'));

    $this->assertSame('januari', Date::monthName('01', 'nl_NL'));

    $this->assertSame('January', Date::monthName('01', 'en_US'));
  }

  /** @test */
  public function testMonthnameMethodReturnsMonthNameFromTheGivenMonthAndNoLocaleProvided()
  {
    $this->assertSame('January', Date::monthName('01'));
    $this->assertSame('August', Date::monthName('08'));
    $this->assertSame('August', Date::monthName('8'));
  }

  /** @test */
  public function testFormatMethodFormatsTheGivenTimeWithDateMode()
  {
    // EN Locale
    $this->assertSame(
      '8 December 2021',
      Date::format('2021-12-8', 'date', 'en_US')
    );

    // FR Locale
    $this->assertSame(
      '8 décembre 2021',
      Date::format('2021-12-8', 'date', 'fr_FR')
    );
  }

  /** @test */
  public function testFormatMethodFormatsTheGivenTimeWithIdateMode()
  {
    $this->assertSame(
      strtotime('8 December 2021'),
      Date::format(strtotime('2021-12-8'), 'idate')
    );
  }

  /** @test */
  public function testFormatMethodFormatsTheGivenTimeWithDbdateMode()
  {
    $this->assertSame(
      '2021-12-08 00:00:00',
      Date::format('8 December 2021', 'dbdate')
    );
  }

  /** @test */
  public function testFormatMethodFormatsTheGivenTimeWithMMode()
  {
    // EN locale
    $this->assertSame(
      'December',
      Date::format('8 December 2021', 'm', 'en_US')
    );

    // FR locale
    $this->assertSame(
      'décembre',
      Date::format('8 December 2021', 'm', 'fr_FR')
    );
  }

  /** @test */
  public function testFormatMethodFormatsTheGivenTimeWithMyMode()
  {
    // EN locale
    $this->assertSame(
      'December 2021',
      Date::format('8 December 2021', 'my', 'en_US')
    );

    // FR locale
    $this->assertSame(
      'décembre 2021',
      Date::format('8 December 2021', 'my', 'fr_FR')
    );
  }

  /** @test */
  public function testFormatMethodFormatsTheGivenTimeWithWsdateOrSMode()
  {
    foreach (['wsdate', 's'] as $mode) {
      // EN Locale

      // $is_today && $only_date
      $this->assertSame(
        date('d/m/Y'),
        Date::format(date('Y-m-d'), $mode, 'en_US')
      );

      // $is_today && !$only_date
      $this->assertSame(
        '14:00',
        Date::format(date('2021-12-d 14:00:00'), $mode, 'en_US')
      );

      $this->assertSame(
        '09:00',
        Date::format(date('2021-12-d 09:00:00'), $mode, 'en_US')
      );

      // !$is_today && $only_date
      $this->assertSame(
        '08/12/2021',
        Date::format('2021-12-08', $mode, 'en_US')
      );

      // !$is_today && !$only_date
      $this->assertSame(
        '08/07/2021',
        Date::format('2021-07-08 14:00:00', $mode, 'en_US')
      );

      // FR locale

      // $is_today && $only_date
      $this->assertSame(
        date('d/m/Y'),
        Date::format(date('Y-m-d'), $mode, 'fr_FR')
      );

      // $is_today && !$only_date
      $this->assertSame(
        '14:00',
        Date::format(date('Y-m-d 14:00:00'), $mode, 'fr_FR')
      );

      $this->assertSame(
        '09:00',
        Date::format(date('2021-12-d 09:00:00'), $mode, 'fr_FR')
      );

      // !$is_today && $only_date
      $this->assertSame(
        '14/08/2021',
        Date::format('2021-08-14', $mode, 'fr_FR')
      );

      // !$is_today && !$only_date
      $this->assertSame(
        '14/08/2021',
        Date::format('2021-08-14 14:00:00', $mode, 'fr_FR')
      );
    }
  }

  /** @test */
  public function testFormatMethodFormatsTheGivenTimeWithRMode()
  {
    // EN Locale

    // $is_today && $only_date
    $this->assertSame(
      date('d M Y'),
      Date::format(date('Y-m-d'), 'r', 'en_US')
    );

    // $is_today && !$only_date
    $this->assertSame(
      '14:00',
      Date::format(date('Y-m-d 14:00:00'), 'r', 'en_US')
    );

    // !$is_today && $only_date
    $this->assertSame(
      '8 Dec 2021',
      Date::format('2021-12-08', 'r')
    );

    // !$is_today && !$only_date
    $this->assertSame(
      '8 Dec 2021',
      Date::format('2021-12-08 14:00:00', 'r')
    );

    // FR Locale

    // $is_today && $only_date
    $this->assertSame(
      date('d') . ' déc. 2021',
      Date::format(date('Y-m-d'), 'r', 'fr_FR')
    );

    // $is_today && !$only_date
    $this->assertSame(
      '14:00',
      Date::format(date('Y-m-d 14:00:00'), 'r', 'fr_FR')
    );

    // !$is_today && $only_date
    $this->assertSame(
      '8 déc. 2021',
      Date::format('2021-12-08', 'r', 'fr_FR')
    );

    // !$is_today && !$only_date
    $this->assertSame(
      '8 déc. 2021',
      Date::format('2021-12-08 14:00:00', 'r', 'fr_FR')
    );
  }

  /** @test */
  public function testFormatMethodFormatsTheGivenTimeWithJsMode()
  {
    $this->assertSame(
      'Wed Dec 08 2021 14:12:12 +0000',
      Date::format('2021-12-08 14:12:12', 'js')
    );
  }

  /** @test */
  public function testFormatMethodFormatsTheGivenTimeWithWdateMode()
  {
    // EN Locale

    // $only_date
    $this->assertSame(
      'Wednesday 8 December 2021',
      Date::format(date('2021-12-08'), 'wdate')
    );

    // !$only_date
    $this->assertSame(
      'Wednesday 8 December 2021, 14:00',
      Date::format(date('2021-12-08 14:00:00'), 'wdate', 'en_UK')
    );


    // FR Locale

    // $only_date
    $this->assertSame(
      'mercredi 8 décembre 2021',
      Date::format(date('2021-12-8'), 'wdate', 'fr_FR')
    );

    // !$only_date
    $this->assertSame(
      'mercredi 8 décembre 2021, 14:00',
      Date::format(date('2021-12-8 14:00:00'), 'wdate', 'fr_FR')
    );
  }

  /** @test */
  public function testFormatMethodFormatsTheGivenTimeWithNotimeMode()
  {
    // EN Locale

    // $only_date
    $this->assertSame(
      '8 December 2021',
      Date::format(date('2021-12-8'), 'notime')
    );

    // !$only_date
    $this->assertSame(
      '8 December 2021',
      Date::format(date('2021-12-8 14:00:00'), 'notime')
    );


    // FR Locale

    // $only_date
    $this->assertSame(
      '8 décembre 2021',
      Date::format(date('2021-12-8'), 'notime', 'fr_FR')
    );

    // !$only_date
    $this->assertSame(
      '8 décembre 2021',
      Date::format(date('2021-12-8 14:00:00'), 'notime', 'fr_FR')
    );
  }

  /** @test */
  public function testFormatMethodFormatsTheGivenTimeWithNoModeProvided()
  {
    // EN Locale

    // $only_date
    $this->assertSame(
      '8 December 2021',
      Date::format(date('2021-12-8'))
    );

    // !$only_date
    $this->assertSame(
      '8 December 2021, 14:00',
      Date::format(date('2021-12-8 14:00:00'))
    );


    // FR Locale

    // $only_date
    $this->assertSame(
      '8 décembre 2021',
      Date::format(date('2021-12-8'), '', 'fr_FR')
    );

    // !$only_date
    $this->assertSame(
      '8 décembre 2021, 14:00',
      Date::format(date('2021-12-8 14:00:00'), '', 'fr_FR')
    );
  }

  /** @test */
  public function testMonthnameMethodReturnsMonthNameFromTheGivenMonthAndNoLocaleProvidedAndBbnLocaleIsDefined()
  {
    if (!defined('BBN_LOCALE')) {
      define('BBN_LOCALE', 'fr_FR');
      $this->assertSame('janvier', Date::monthName('01'));
      $this->assertSame('juin', Date::monthName('06'));
      $this->assertSame('septembre', Date::monthName('9'));
      $this->assertSame('décembre', Date::monthName('12'));
    }

    $this->assertTrue(true);
  }

  /** @test */
  public function testFormatMethodFormatsTheGivenTimeWithDateModeAndBbnLocaleDefined()
  {
    if (!defined('BBN_LOCALE')) {
      define('BBN_LOCALE', 'fr_FR');
    }

    if (BBN_LOCALE !== 'fr_FR') {
      $this->assertTrue(true);
      return;
    }

    $this->assertSame(
      '8 décembre 2021',
      Date::format('2021-12-8', 'date')
    );
  }

  /** @test */
  public function testFormatMethodFormatsTheGivenTimeWithMModeAndBbnLocaleDefined()
  {
    if (!defined('BBN_LOCALE')) {
      define('BBN_LOCALE', 'fr_FR');
    }

    if (BBN_LOCALE !== 'fr_FR') {
      $this->assertTrue(true);
      return;
    }

    $this->assertSame(
      'décembre',
      Date::format('8 December 2021', 'm')
    );
  }

  /** @test */
  public function testFormatMethodFormatsTheGivenTimeWithMyModeAndBbnLocaleDefined()
  {
    if (!defined('BBN_LOCALE')) {
      define('BBN_LOCALE', 'fr_FR');
    }

    if (BBN_LOCALE !== 'fr_FR') {
      $this->assertTrue(true);
      return;
    }

    $this->assertSame(
      'décembre 2021',
      Date::format('8 December 2021', 'my')
    );
  }

  /** @test */
  public function testFormatMethodFormatsTheGivenTimeWithWsdateOrSModeAndBbnLocaleDefined()
  {
    foreach (['wsdate', 's'] as $mode) {
      if (!defined('BBN_LOCALE')) {
        define('BBN_LOCALE', 'fr_FR');
      }

      if (BBN_LOCALE !== 'fr_FR') {
        $this->assertTrue(true);
        return;
      }

      // $is_today && $only_date
      $this->assertSame(
        date('d/m/Y'),
        Date::format(date('Y-m-d'), $mode)
      );

      // $is_today && !$only_date
      $this->assertSame(
        '14:00',
        Date::format(date('Y-m-d 14:00:00'), $mode)
      );

      $this->assertSame(
        '09:00',
        Date::format(date('2021-12-d 09:00:00'), $mode)
      );

      // !$is_today && $only_date
      $this->assertSame(
        '14/08/2021',
        Date::format('2021-08-14', $mode)
      );

      // !$is_today && !$only_date
      $this->assertSame(
        '14/08/2021',
        Date::format('2021-08-14 14:00:00', $mode)
      );
    }
  }

  /** @test */
  public function testFormatMethodFormatsTheGivenTimeWithRModeAndBbnLocaleDefined()
  {
    if (!defined('BBN_LOCALE')) {
      define('BBN_LOCALE', 'fr_FR');
    }

    if (BBN_LOCALE !== 'fr_FR') {
      $this->assertTrue(true);
      return;
    }

    // $is_today && $only_date
    $this->assertSame(
      date('d') . ' déc. 2021',
      Date::format(date('Y-m-d'), 'r')
    );

    // $is_today && !$only_date
    $this->assertSame(
      '14:00',
      Date::format(date('Y-m-d 14:00:00'), 'r')
    );

    // !$is_today && $only_date
    $this->assertSame(
      '8 déc. 2021',
      Date::format('2021-12-08', 'r')
    );

    // !$is_today && !$only_date
    $this->assertSame(
      '8 déc. 2021',
      Date::format('2021-12-08 14:00:00', 'r')
    );
  }


  /** @test */
  public function testFormatMethodFormatsTheGivenTimeWithWdateModeBbnLocaleDefined()
  {
    if (!defined('BBN_LOCALE')) {
      define('BBN_LOCALE', 'fr_FR');
    }

    if (BBN_LOCALE !== 'fr_FR') {
      $this->assertTrue(true);
      return;
    }

    // $only_date
    $this->assertSame(
      'mercredi 8 décembre 2021',
      Date::format(date('2021-12-8'), 'wdate')
    );

    // !$only_date
    $this->assertSame(
      'mercredi 8 décembre 2021, 14:00',
      Date::format(date('2021-12-8 14:00:00'), 'wdate')
    );
  }

  /** @test */
  public function testFormatMethodFormatsTheGivenTimeWithNotimeModeBbnLocaleDefined()
  {
    if (!defined('BBN_LOCALE')) {
      define('BBN_LOCALE', 'fr_FR');
    }

    if (BBN_LOCALE !== 'fr_FR') {
      $this->assertTrue(true);
      return;
    }

    // $only_date
    $this->assertSame(
      '8 décembre 2021',
      Date::format(date('2021-12-8'), 'notime')
    );

    // !$only_date
    $this->assertSame(
      '8 décembre 2021',
      Date::format(date('2021-12-8 14:00:00'), 'notime')
    );
  }

  /** @test */
  public function testFormatMethodFormatsTheGivenTimeWithNoModeProvidedBbnLocaleDefined()
  {
    if (!defined('BBN_LOCALE')) {
      define('BBN_LOCALE', 'fr_FR');
    }

    if (BBN_LOCALE !== 'fr_FR') {
      $this->assertTrue(true);
      return;
    }

    // $only_date
    $this->assertSame(
      '8 décembre 2021',
      Date::format(date('2021-12-8'))
    );

    // !$only_date
    $this->assertSame(
      '8 décembre 2021, 14:00',
      Date::format(date('2021-12-8 14:00:00'))
    );
  }
}