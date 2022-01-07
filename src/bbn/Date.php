<?php
/**
 * @package time
 */
namespace bbn;
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
  
  public static function validate($date, $format = 'Y-m-d H:i:s'){
    $d = \DateTime::createFromFormat($format, $date);
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
  public static function monthpickerOptions($val = '', ?string $local = null)
  {
    $arr = [];
    for ( $i = 1; $i <= 12; $i++ ) {
      $arr[$i] = self::monthName($i, $local);
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
  public static function intlDateFormat(string $format, int $timestamp, ?string $local = null)
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

    if (!$local && defined('BBN_LOCALE')) {
      $local = BBN_LOCALE;
    }

    $formatter = new \IntlDateFormatter(
      $local,
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
    $cut = substr($date, 0, 8);
    $daylen = 86400;
    $timestamp = strtotime($date);
    $first = strtotime($cut . "00");
    $elapsed = ($timestamp - $first) / $daylen;
    $weeks = 1;
    for ( $i = 1; $i <= $elapsed; $i++ ){
      $dayfind = $cut . (strlen($i) < 2 ? '0' . $i : $i);
      $daytimestamp = strtotime($dayfind);
      $day = strtolower(date("l", $daytimestamp));
      if ( $day === strtolower($firstweekday) ){
        $weeks++;
      }
    }
    return $weeks;
  }

}
