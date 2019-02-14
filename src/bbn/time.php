<?php
/**
 *
 */
namespace bbn;


/**
 * Class dealing with date manipulation
 * examples: test/loredana/time
 * 
 * @copyright BBN Solutions
 * @category  Time and Date
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 */
class time extends models\cls\basic
{
  private $time;
  private $interval;
  
  public function __construct($t)
  {
    $this->time = new \dateTime($t);
  }

  /**
   * return the property $this->time
   *
   * @return void
   */
  public function get_time()
  {
    return $this->time;
  }

  /**
   * Define the property $interval instantiating the given $interval to the class DateInterval 
   * 
   *
   * @param string $interval allowed http://php.net/manual/en/dateinterval.construct.php
   * @return void
   */
  private function set_interval(string $interval)
  {
    //http://php.net/manual/en/dateinterval.construct.php */
    $this->interval = new \DateInterval($interval);
  }

  /**
   * Return the property $interval if it is set
   * 
   * @param string $interval
   * @return void
   */
  private function get_interval(string $interval = '')
  {
    if ( !empty($interval) ){
      $this->interval = new \DateInterval($interval);
      return $this->interval;
    }
    else {
      return $this->interval;
    }
  }

  /**
   * return the date in the given $format of in 'Y-m-d H:i:s' format if no argument is given to the function
   *
   * @param string $format
   * @return void
   */
  public function format($format = '')
  {
    if ( !empty($format) ){
      return $this->time->format($format);
    }
    else { 
      return $this->time->format('Y-m-d H:i:s');
    }
  }
  private function get_year()
  {
    return $this->time->format('Y');
  }
  
  private function get_month()
  {
    return $this->time->format('m');
  }

  /**
   * Compares two dates
   *
   * @param [String|Object] $date the string of the date to compare or an object of this class
   * @param [String] $comparator allowed comparators '>','>=', '<','<=', '='
   * @return Boolean
   */
  public function compare($date, $comparator)
  {
    //check if the argument $date is an instance of this class
    if ( $date instanceof $this){
      $tmp = $date;
    }
    else {
      $tmp = new \bbn\time($date);
    }
    switch ( $comparator ){
      case $comparator === '>':  
        return $this->get_time() > $tmp->get_time(); 
        break;
      case $comparator === '>=':  
        return $this->get_time() >= $tmp->get_time(); 
        break;
      case $comparator === '<':  
        return $this->get_time() < $tmp->get_time(); 
        break;
      case $comparator === '<=':  
        return $this->get_time() <= $tmp->get_time(); 
        break;
      case $comparator === '=':  
        return $this->get_time() == $tmp->get_time(); 
        break;
    }
  }

  /**
   * Return if $this->time is before of the given $date
   *
   * @param [String|Object] $date the string of the date to compare or an object of this class
   * @return boolean
   */
  public function is_before($date)
  {
    //check if the argument $date is an instance of this class
    if ( $date instanceof $this){
      $tmp = $date;
    }
    else {
      $tmp = new \bbn\time($date);
    }
    return $this->get_time() < $tmp->get_time(); 
  }

  /**
   * Return if $this->time is after of the given $date
   *
   * @param [String|Object] $date the string of the date to compare or an object of this class
   * @return boolean
   */

  public function is_after($date)
  {
    //check if the argument $date is an instance of this class
    if ( $date instanceof $this){
      $tmp = $date;
    }
    else {
      $tmp = new \bbn\time($date);
    }
    return $this->get_time() > $tmp->get_time(); 
  }

  /**
   * Return if $this->time is the same of the given $date
   *
   * @param [String|Object] $date the string of the date to compare or an object of this class
   * @return boolean
   */
  
  public function is_same($date)
  {
    //check if the argument $date is an instance of this class
    if ( $date instanceof $this){
      $tmp = $date;
    }
    else {
      $tmp = new \bbn\time($date);
    }
    return $this->get_time() == $tmp->get_time(); 
  }


  /**
   * Add an the given $interval to $this->time and return a reference to the original object
   * If the argument $format is not given it returns the sql format 'Y-m-d H:i:s'
   * 
   * @param string $interval
   * @param string $format optional
   * @return void
   */
  public function add(string $interval, $format = ''){
    $tmp  = new \bbn\time($this->format());
    $tmp->time->add($this->get_interval($interval));
    return $tmp->format($format);
  }

  /**
   * Subtract the given $interval to $this->time and return a reference to the original object
   * If the argument $format is not given it returns the sql format 'Y-m-d H:i:s'
   * 
   * @param string $interval
   * @param string $format optional
   * @return void
   */
  public function sub(string $interval, $format = ''){
    $tmp  = new \bbn\time($this->format());
    $tmp->time->sub($this->get_interval($interval));
    return $tmp->format($format);
  }

  /**
   * Return a reference to $this->time modified of the $modif
   *
   * @param [type] $modif
   * @param string $format
   * @return void
   */
  public function modif($modif, $format = '')
  {
    $tmp = new \bbn\time($this->format());
    $tmp->time->modify($modif);
    return $tmp->format($format);
  }

  /**
   * return the end of the month of $this->time
   *
   * @return Number
   */
  public function end_of_month(){
    $m = $this->get_month();
    $y = $this->get_year();
    return cal_days_in_month(CAL_GREGORIAN, $m, $y);
  }
}
?>