<?php
/**
 * @package bbn\util
 */
namespace bbn\util;
/**
 * Encryption Class
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since July 11, 2013, 13:08:00 +01:00
 * @category  Utilities
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.1
 */
class timer
{
  private $measures;
  
	/**
	 * @return void 
	 */
	public function __construct()
	{
    $this->measures = [];
	}

	/**
	 * @return void 
	 */
	public function start($key='default')
	{
		if ( !isset($this->measures[$key]) ){
      $this->measures[$key] = [
        'num' => 0,
        'sum' => 0,
        'start' => microtime(1)
      ];
    }
    else{
      $this->measures[$key]['start'] = microtime(1);
    }
  }
  
	/**
	 * @return void 
	 */
  public function stop($key='default')
  {
    if ( isset($this->measures[$key], $this->measures[$key]['start']) ){
      $this->measures[$key]['num']++;
      $this->measures[$key]['sum'] += ( microtime(1) - $this->measures[$key]['start'] );
      unset($this->measures[$key]['start']);
    }
    else{
      die("Missing a start declaration for timer $key");
    }
  }
  
	/**
	 * @return array
	 */
  public function result($key='default')
  {
    if ( isset($this->measures[$key]) ){
      return [
        'num' => $this->measures[$key]['num'],
        'total' => $this->measures[$key]['sum'],
        'average' => $this->measures[$key]['sum'] / $this->measures[$key]['num']
      ];
    }
  }
  
	/**
	 * @return array
	 */
  public function results()
  {
    $r = [];
    foreach ( $this->measures as $key => $val ){
      $r[$key] = $this->result($key);
    }
    return $r;
  }

}
?>