<?php
/**
 * @package time
 */
namespace bbn;

use DateTime;
use DateTimeImmutable;
use Exception;
use bbn\Str;
/**
 * Deals with date manipulation.
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Time and Date
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.2r89
 * @todo Plenty of stuff!
 */
class Date 
{
  private static $windows = null;
  
  public static function isWindows(){
    if ( \is_null(self::$windows) ){
      self::$windows = X::isWindows();
    }
    return self::$windows;
  }
  
  public static function lastDayOfMonth($date, $format = false){
    if ( $date ){
      $m = false;
      if ( Str::isNumber($date) ){
        if ( $date <= 12 ){
          $m = $date;
          $y = date('Y');
        }
        else{
          $m = (int)date('m', $date);
          $y = date('Y', $date);
        }
      }
      else if ( $d = strtotime($date) ){
        $m = (int)date('m', $d);
        $y = date('Y', $d);
      }
      if ( $m ){
        $r = mktime(0, 0, -1, $m+1, 1, $y);
        return $format ? date($format, $r) : $r;
      }
    }
    
  }
  
  public static function validate($date, string $format = 'Y-m-d H:i:s'){
    if (!is_string($date)) {
      return false;
    }

    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) == $date;
  }
  
  public static function validateSQL($date){
    return self::validate($date, 'Y-m-d H:i:s') || self::validate($date, 'Y-m-d');
  }

  /**
   * @param string $date
   * @param string $mode
   * @return false|int|string
   */
  public static function format($date='', $mode='', ?string $locale = null)
	{
    if (!$locale && defined('BBN_LOCALE')) {
      $locale = BBN_LOCALE;
    }

    /* Formatting: idate is the timestamp, and date[0] and date[1] the SQL date and time */
		if ( empty($date) ){
			$idate = time();
    }
		else if ( is_numeric($date) ){
			$idate = $date;
    }
		else{
			$idate = strtotime($date);
    }

		if ( $idate )
		{
			/* Getting the time difference */
			$t = time();
      //if ( ($date('h') == 0) && ($date('i') == 0) && ($date('s', $ida) == 0) ){
			$h = localtime($idate, 1);
			$start_today = mktime(0, 0, 0);
			$end_today = $start_today + ( 24 * 3600 );
			$is_today = ( ($idate >= $start_today) && ($idate < $end_today) ) ? 1 : false;
			$only_date = ($mode === 'date') || ( $h['tm_hour'] + $h['tm_min'] + $h['tm_sec'] == 0 ) ? 1 : false;
			if ( $mode === 'idate' ){
        $date_ok = $idate;
      }
			else if ( $mode === 'dbdate' ){
        $date_ok = date('Y-m-d H:i:s', $idate);
      }
      else if ( $mode === 'm' ){
        $date_ok = self::intlDateFormat('MMMM', $idate, $locale);
      }
      else if ( $mode === 'my' ){
        $date_ok = self::intlDateFormat('MMMM yyyy', $idate, $locale);
      }
			else if ( $mode === 'wsdate' || $mode === 's' ){
				if ( $is_today && !$only_date ){
          $date_ok = self::intlDateFormat('kk:mm', $idate, $locale);
        }
				else{
          $date_ok = self::intlDateFormat('dd/MM/yyyy', $idate, $locale);
        }
			}
			else if ( $mode == 'r' ){
				if ( $is_today && !$only_date ){
          $date_ok = self::intlDateFormat('kk:mm', $idate, $locale);
        }
				else{
          $date_ok = self::intlDateFormat('d MMM yyyy', $idate, $locale);
        }
			}
			else if ( $mode == 'js' ){
        $date_ok = date('D M d Y H:i:s O', $idate);
			}
      else if ( ($mode === 'wdate') || ($mode === 'wdate') ){
        $date_ok = self::intlDateFormat('EEEE d MMMM yyyy', $idate, $locale);
        if ( !$only_date && ($mode !== 'notime') ){
          $date_ok .= ', '. self::intlDateFormat('kk:mm', $idate, $locale);
        }
      }
      else {
        $date_ok = self::intlDateFormat('d MMMM yyyy', $idate, $locale);
        if ( !$only_date && ($mode !== 'notime') ){
          $date_ok .= ', '. self::intlDateFormat('kk:mm', $idate, $locale);;
        }
      }
			return $date_ok;
		}
	}

  /**
   * @param $val
   * @param string|null $local
   *
   * @return string
   */
  public static function monthpickerOptions($val = '', ?string $locale = null)
  {
    $arr = [];
    if (!$locale && defined('BBN_LOCALE')) {
      $locale = BBN_LOCALE;
    }

    for ( $i = 1; $i <= 12; $i++ ) {
      $arr[$i] = self::monthName($i, $locale);
    }

    return X::buildOptions($arr, $val);
  }

  /**
   * @param $m
   * @param string|null $local
   *
   * @return false|string
   */
  public static function monthName($m, ?string $local = null)
  {
    return self::intlDateFormat('MMMM', strtotime("2012-$m-01"), $local);
  }

  /**
   * @param string $format
   * @param int $timestamp
   * @param string|null $local
   * @return false|string
   */
  public static function intlDateFormat(string $format, int $timestamp, ?string $locale = null)
  {
    if (!extension_loaded('intl')) {
      $formats_map = [
        'MMMM' => 'F', // January
        'd MMMM yyyy' => 'j F Y', // 8 December 2021
        'EEEE d MMMM yyyy' => 'l j F Y', // Wednesday 8 December 2021
        'd MMM yyyy' => 'j M Y', // 8 Dec 2021
        'MMMM yyyy' => 'F Y', // December 2021
        'dd/MM/yyyy' => 'd/m/Y', // 08/09/2021
        'kk:mm' => 'H:i' // 09:09 or 14:10
      ];

      if (array_key_exists($format, $formats_map)) {
        $format = $formats_map[$format];
      }

      return date($format, $timestamp);
    }

    if (!$locale && defined('BBN_LOCALE')) {
      $locale = BBN_LOCALE;
    }

    if (!$locale) {
      $locale = setlocale(LC_ALL, 0);
    }

    if (!$locale) {
      $locale = 'en_US';
    }

    $formatter = new \IntlDateFormatter(
      $locale,
      \IntlDateFormatter::LONG,
      \IntlDateFormatter::LONG,
      null,
      null,
      $format
    );

    return $formatter->format($timestamp);
  }

  /**
   * Gets the month's week of the given date.
   * @param string $date
   * @param string $firstweekday
   * @return int
   */
  public static function getMonthWeek(string $date, string $firstweekday = 'monday'): int
  {
    $cut = Str::sub($date, 0, 8);
    $daylen = 86400;
    $timestamp = strtotime($date);
    $first = strtotime($cut . "00");
    $elapsed = ($timestamp - $first) / $daylen;
    $weeks = 1;
    for ( $i = 1; $i <= $elapsed; $i++ ){
      $dayfind = $cut . (Str::len($i) < 2 ? '0' . $i : $i);
      $daytimestamp = strtotime($dayfind);
      $day = strtolower(date("l", $daytimestamp));
      if ( $day === strtolower($firstweekday) ){
        $weeks++;
      }
    }
    return $weeks;
  }


  public static function diff($date1, $date2, $unit = 's')
  {
    if (is_int($date1)) {
      $date1 = date('Y-m-d H:i:s', $date1);
    }
    if (is_int($date2)) {
      $date2 = date('Y-m-d H:i:s', $date2);
    }

    if (!Str::isDateSql($date1, $date2)) {
      throw new Exception(X::_("The given dates $date1 and $date2 are not valid"));
    }
    $format = 'Y-m-d';
    if (Str::len($date1) > 10) {
      $format .= ' H:i:s';
    }
    $d1 = DateTimeImmutable::createFromFormat($format, $date1);
    $d2 = DateTimeImmutable::createFromFormat($format, $date2);
    $diff = $d1->diff($d2);
    $sign = $diff->format('%R');
    $mult = $sign === '-' ? -1 : 1;
    switch ( $unit ){
      case 's':
        $res = $diff->s + ($diff->h * 60) + ($diff->days * 24 * 3600);
        break;
      case 'i':
        $res = $diff->i + ($diff->h * 60) + ($diff->days * 24 * 60);
        break;
      case 'h':
        $res = $diff->h + ($diff->days * 24);
        break;
      case 'd':
        $res = $diff->days;
        break;
      case 'm':
        $res = $diff->m + ($diff->y * 12);
        break;
      case 'y':
        $res = $diff->y;
        break;
      default:
        $res = $diff->days;
    }

    return $res * $mult;
  }

}
