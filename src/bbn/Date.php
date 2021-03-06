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
  public static function format($date='', $mode='')
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
		$is_windows = strtoupper(substr(PHP_OS, 0, 3)) == 'WIN' ? true : false;
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
        $date_ok = $is_windows ? strftime("%m", $idate) : strftime("%B", $idate);
      }
      else if ( $mode === 'my' ){
        $date_ok = $is_windows ? strftime("%m %Y", $idate) : strftime("%B %Y", $idate);
      }
			else if ( $mode === 'wsdate' || $mode === 's' ){
				if ( $is_today && !$only_date ){
          $date_ok = strftime('%H:%M', $idate);
        }
				else{
          $date_ok = $is_windows ? strftime('%d/%m/%y', $idate) : strftime('%x', $idate);
        }
			}
			else if ( $mode == 'r' ){
				if ( $is_today && !$only_date ){
          $date_ok = strftime('%R', $idate);
        }
				else{
          $date_ok = $is_windows ? utf8_encode(strftime('%#d %b %Y', $idate)) : strftime('%e %b %Y', $idate);
        }
			}
			else if ( $mode == 'js' ){
        $date_ok = date('D M d Y H:i:s O', $idate);
			}
      else if ( ($mode === 'wdate') || ($mode === 'wdate') ){
        $date_ok = $is_windows ? utf8_encode(strftime('%A %#d %B %Y', $idate)) : strftime('%A %e %B %Y', $idate);
        if ( !$only_date && ($mode !== 'notime') ){
          $date_ok .= ', '.strftime('%H:%M', $idate);
        }
      }
      else {
        $date_ok = $is_windows ? utf8_encode(strftime('%#d %B %Y', $idate)) : strftime('%e %B %Y', $idate);
        if ( !$only_date && ($mode !== 'notime') ){
          $date_ok .= ', '.strftime('%H:%M', $idate);
        }
      }
			return $date_ok;
		}
	}
  
  public static function monthpickerOptions($val='')
  {
    $arr = [];
    for ( $i = 1; $i <= 12; $i++ ){
      $arr[$i] = self::monthName($i);
    }
    return X::buildOptions($arr, $val);
  }
  
  public static function monthName($m){
    return self::isWindows() ? strftime("%m", strtotime("2012-$m-01")) : strftime("%B", strtotime("2012-$m-01"));
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
?>