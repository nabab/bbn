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
  static public $types = [
    'js' => ['js', 'coffee'],
    'css' => ['css', 'less', 'sass', 'scss'],
    'html' => ['html', 'php'],
    'lang' => ['lang']
  ];

}