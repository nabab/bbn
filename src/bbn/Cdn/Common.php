<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 26/02/2017
 * Time: 00:59
 */

namespace bbn\Cdn;


trait Common
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
    'js' => ['js', 'ts'],
    'css' => ['css', 'less', 'sass', 'scss'],
    'html' => ['html', 'php'],
    'lang' => ['lang']
  ];

  private function _set_prefix(){
    if ( defined('BBN_SHARED_PATH') && (Str::pos(BBN_SHARED_PATH, '/') === 0) ){
      $this->prefix = Str::sub(BBN_SHARED_PATH, 1);
      $this->furl = '/'.$this->prefix;
    }
    else{
      $this->furl = BBN_URL;
      $parsed = parse_url(BBN_SHARED_PATH);
      if ( $parsed['path'] && ($parsed['path'] !== '/') ){
        $this->prefix = Str::sub($parsed['path'], 1);
        $this->furl .= $this->prefix;
      }
    }
    $this->fpath = BBN_PUBLIC.$this->prefix;
  }

}
