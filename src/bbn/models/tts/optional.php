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

  protected static
    $optional_is_init = false,
    $option_root_id;

  /**
   * Returns the option's root ID for the current class based on {@link $option_root_code}
   *
   * @return false|int
   */
  protected static function optional_init($obj){
    if ( !self::$optional_is_init ){
      $opt = bbn\appui\options::get_instance();
      if ( !$opt ){
        die("There is no options object as needed by ".get_class($obj));
      }
      if ( !defined("BBN_APPUI") ){
        define("BBN_APPUI", $opt->from_code('appui'));
        if ( !BBN_APPUI ){
          die("Impossible to find the option appui for ".get_class($obj));
        }
      }
      $cls = get_class($obj);
      $cls = last(explode("\\", $cls));
      self::$option_root_id = $opt->from_code($cls, BBN_APPUI);
      if ( !self::$option_root_id ){
        die("Impossible to find the option $cls for ".get_class($obj));
      }
      self::$optional_is_init = true;
    }
  }

  public function get_option_root(){
    return self::$option_root_id;
  }

  /**
   * Returns The option's ID of a category, i.e. direct children of option's root
   *
   * @param string $code
   * @return int|false
   */
  public static function get_option_id($code = null){
    $opt = bbn\appui\options::get_instance();
    $args = func_get_args();
    array_push($args, self::$option_root_id);
    return $opt->from_code($args);
  }

  public static function get_options_ids($code = null){
    $opt = bbn\appui\options::get_instance();
    $args = func_get_args();
    array_push($args, self::$option_root_id);
    return $opt->items($args);
  }

  public static function get_options_tree($code = null){
    $opt = bbn\appui\options::get_instance();
    $args = func_get_args();
    array_push($args, self::$option_root_id);
    if ( $tree = $opt->full_tree($args) ){
      return $tree['items'] ?: [];
    }
    return [];
  }

  public static function get_options($code = null){
    $opt = bbn\appui\options::get_instance();
    $args = func_get_args();
    array_push($args, self::$option_root_id);
    return $opt->full_options($args);
  }

  public static function get_options_text_value($code = null){
    $opt = bbn\appui\options::get_instance();
    $args = func_get_args();
    array_push($args, self::$option_root_id);
    if ( $id = $opt->from_code($args) ){
      return $opt->text_value_options($id);
    }
    return [];
  }

}