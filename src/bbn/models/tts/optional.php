<?php
/**
 * Implements functions for retrieving class-specific options
 *
 * User: BBN
 * Date: 05/11/2016
 * Time: 02:53
 */

namespace bbn\models\tts;

use bbn;


trait optional
{

  protected static function _treat_args(array $args, $appui = false){
    if ( (count($args) > 1) || !\bbn\str::is_uid($args[0]) ){
      self::optional_init();
      $args[] = $appui ? self::$option_appui_id : self::$option_root_id;
    }
    return $args;
  }

  protected static
    $optional_is_init = false,
    $option_appui_id,
    $option_root_id;

  /**
   * Returns the option's root ID for the current class based on {@link $option_root_code}
   *
   * @return false|int
   */
  protected static function optional_init(){
    if ( !self::$optional_is_init ){
      $opt = bbn\appui\options::get_instance();
      if ( !$opt ){
        die("There is no options object as needed by ".__CLASS__);
      }
      if ( !\defined("BBN_APPUI") ){
        \define('BBN_APPUI', $opt->from_code('appui'));
      }
      if ( !\defined("BBN_APPUI_ROOT") ){
        \define('BBN_APPUI_ROOT', $opt->from_root_code('appui'));
      }
      if ( !BBN_APPUI || !BBN_APPUI_ROOT ){
        die('Impossible to find the option appui for '.__CLASS__);
      }
      $tmp = explode('\\', __CLASS__);
      $cls = end($tmp);
      self::$option_appui_id = $opt->from_code($cls, BBN_APPUI_ROOT);
      self::$option_root_id = $opt->from_code($cls, BBN_APPUI);
      if ( !self::$option_appui_id || !self::$option_root_id ){
        if ( defined('BBN_IS_DEV') && BBN_IS_DEV ){
          die(bbn\x::hdump("Impossible to find the option $cls for ".__CLASS__));
        }
        die("Impossible to find the option $cls for ".__CLASS__);
      }
      self::$optional_is_init = true;
    }
  }

  public static function get_option_root(){
    self::optional_init();
    return self::$option_root_id;
  }

  public static function get_appui_root(){
    self::optional_init();
    return self::$option_appui_id;
  }

  /**
   * Returns The option's ID of a category, i.e. direct children of option's root
   *
   * @param string $code
   * @return int|false
   */
  public static function get_option_id(){
    return bbn\appui\options::get_instance()->from_code(...self::_treat_args(func_get_args()));
  }

  public static function get_options_ids(){
    return array_flip(array_filter(bbn\appui\options::get_instance()->get_codes(...self::_treat_args(func_get_args())), function($a){
      return $a !== null;
    }));
  }

  public static function get_options_tree(){
    return ($tree = bbn\appui\options::get_instance()->full_tree(...self::_treat_args(func_get_args()))) ?
      $tree['items'] : [];
  }

  public static function get_options(){
    return bbn\appui\options::get_instance()->full_options(...self::_treat_args(func_get_args()));
  }

  public static function get_option(){
    return bbn\appui\options::get_instance()->option(...self::_treat_args(func_get_args()));
  }

  public static function get_options_text_value(){
    return ($id = self::get_option_id(...func_get_args())) ?
      bbn\appui\options::get_instance()->text_value_options($id): [];
  }

  /**
   * Returns The option's ID of a category, i.e. direct children of option's root
   *
   * @param string $code
   * @return int|false
   */
  public static function get_appui_option_id(){
    return bbn\appui\options::get_instance()->from_code(...self::_treat_args(func_get_args(), true));
  }

  public static function get_appui_options_ids(){
    return array_flip(array_filter(bbn\appui\options::get_instance()->get_codes(...self::_treat_args(func_get_args(), true)), function($a){
      return $a !== null;
    }));
  }

  public static function get_appui_options_tree(){
    return ($tree = bbn\appui\options::get_instance()->full_tree(...self::_treat_args(func_get_args(), true)) ) ?
      $tree['items'] : [];
  }

  public static function get_appui_options(){
    return bbn\appui\options::get_instance()->full_options(...self::_treat_args(func_get_args(), true));
  }

  public static function get_appui_option(){
    return bbn\appui\options::get_instance()->option(...self::_treat_args(func_get_args(), true));
  }

  public static function get_appui_options_text_value(){
    return ($id = self::get_appui_option_id(...func_get_args())) ?
      bbn\appui\options::get_instance()->text_value_options($id) : [];
  }

}
