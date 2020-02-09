<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 26/02/2017
 * Time: 00:59
 */

namespace bbn\cdn;


trait common
{

  /**
   * @var string
   */
  private $prefix = '';

 /**
  * @var string
  */
 private $fpath = '';

 /**
  * @var string
  */
 private $furl = '';

  static public $types = [
    'js' => ['js', 'coffee'],
    'css' => ['css', 'less', 'sass', 'scss'],
    'html' => ['html', 'php'],
    'lang' => ['lang']
  ];

  private function set_prefix(){
    if ( defined('BBN_SHARED_PATH') && (strpos(BBN_SHARED_PATH, '/') === 0) ){
      $this->prefix = substr(BBN_SHARED_PATH, 1);
      $this->furl = '/'.$this->prefix;
    }
    else{
      $this->furl = BBN_URL;
      $parsed = parse_url(BBN_SHARED_PATH);
      if ( $parsed['path'] && ($parsed['path'] !== '/') ){
        $this->prefix = substr($parsed['path'], 1);
        $this->furl .= $this->prefix;
      }
    }
    $this->fpath = BBN_PUBLIC.$this->prefix;
  }

}
