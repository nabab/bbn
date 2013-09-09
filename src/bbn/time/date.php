<?php
/**
 * @package bbn\time
 */
namespace bbn\time;
/**
 * Class dealing with date manipulation
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Time and Date
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 * @todo Plenty of stuff!
 */
class date 
{

	/**
	 * @return void 
	 */
	public static function format($date='', $mode='wdate')
	{
		/* Formatting: idate is the timestamp, and date[0] and date[1] the SQL date and time */
		if ( empty($date) )
			$idate = time();
		else if ( is_numeric($date) )
			$idate = $date;
		else 
			$idate = strtotime($date);
		$is_windows = strtoupper(substr(PHP_OS, 0, 3)) == 'WIN' ? true : false;
		if ( $idate )
		{
			/* Getting the time difference */
			$t = time();
			$h = localtime($t,1);
			$d = mktime($h['tm_hour'],$h['tm_min'],$h['tm_sec'],$h['tm_mon']+1,$h['tm_mday'],$h['tm_year']+1900) - $t;
			$start_today = mktime(0,0,0) + $d;
			$end_today = $start_today + ( 24 * 3600 );
			$is_today = ( $idate >= $start_today && $idate < $end_today ) ? 1 : false;
			$only_date = ( $h['tm_hour'] + $h['tm_min'] + $h['tm_sec'] == 0 ) ? 1 : false;
			if ( $mode === 'idate' )
				$date_ok = $idate;
			else if ( $mode === 'dbdate' )
				$date_ok = date('Y-m-d H:i:s',$idate);
      else if ( $mode === 'm' ){
        $date_ok = $is_windows ? strftime("%m", $idate) : strftime("%B", $idate);
      }
      else if ( $mode === 'my' ){
        $date_ok = $is_windows ? strftime("%m %Y", $idate) : strftime("%B %Y", $idate);
      }
			else if ( $mode === 'wsdate' || $mode === 's' )
			{
				if ( $is_today && !$only_date )
					$date_ok = strftime('%H:%M',$idate);
				else
					$date_ok = $is_windows ? strftime('%d/%m/%y',$idate) : strftime('%D',$idate);
			}
			else if ( $mode == 'r' )
			{
				if ( $is_today && !$only_date )
					$date_ok = strftime('%R',$idate);
				else
					$date_ok = $is_windows ? utf8_encode(strftime('%#d %b %Y',$idate)) : strftime('%e %b %Y',$idate);
			}
			else /*  wdate */
			{
				$date_ok = $is_windows ? utf8_encode(strftime('%A %#d %B %Y',$idate)) : strftime('%A %e %B %Y',$idate);
				if ( !$only_date )
					$date_ok .= ', '.strftime('%H:%M',$idate);
			}
			return $date_ok;
		}
	}
  
  public static function monthpicker_options($val='')
  {
    $arr = [];
    for ( $i = 1; $i <= 12; $i++ ){
      $arr[$i] = $is_windows ? strftime("%m", strtotime("2012-$i-01")) : strftime("%B", strtotime("2012-$i-01"));
    }
    return \bbn\tools::build_options($arr, $val);
  }

}
?>