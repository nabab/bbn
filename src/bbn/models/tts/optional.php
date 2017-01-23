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
  protected static function optional_init(){
    if ( !self::$optional_is_init ){
      $opt = bbn\appui\options::get_instance();
      if ( !$opt ){
        die("There is no options object as needed by ".__CLASS__);
      }
      if ( !defined("BBN_APPUI") ){
        define("BBN_APPUI", $opt->from_code('appui'));
        if ( !BBN_APPUI ){
          die("Impossible to find the option appui for ".__CLASS__);
        }
      }
      $cls = end(explode("\\", __CLASS__));
      self::$option_root_id = $opt->from_code($cls, BBN_APPUI);
      if ( !self::$option_root_id ){
        die("Impossible to find the option $cls for ".__CLASS__);
      }
      self::$optional_is_init = true;
    }
  }

  public function get_option_root(){
    self::optional_init();
    return self::$option_root_id;
  }

  /**
   * Returns The option's ID of a category, i.e. direct children of option's root
   *
   * @param string $code
   * @return int|false
   */
  public static function get_option_id($code = null){
    self::optional_init();
    $opt = bbn\appui\options::get_instance();
    $args = func_get_args();
    array_push($args, self::$option_root_id);
    return $opt->from_code($args);
  }

  public static function get_options_ids($code = null){
    self::optional_init();
    $opt = bbn\appui\options::get_instance();
    $args = func_get_args();
    array_push($args, self::$option_root_id);
    return array_flip($opt->get_codes($args));
  }

  public static function get_options_tree($code = null){
    self::optional_init();
    $opt = bbn\appui\options::get_instance();
    $args = func_get_args();
    array_push($args, self::$option_root_id);
    if ( $tree = $opt->full_tree($args) ){
      return $tree['items'] ?: [];
    }
    return [];
  }

  public static function get_options($code = null){
    self::optional_init();
    $opt = bbn\appui\options::get_instance();
    $args = func_get_args();
    array_push($args, self::$option_root_id);
    return $opt->full_options($args);
  }

  public static function get_options_text_value($code = null){
    self::optional_init();
    $opt = bbn\appui\options::get_instance();
    $args = func_get_args();
    array_push($args, self::$option_root_id);
    if ( $id = $opt->from_code($args) ){
      return $opt->text_value_options($id);
    }
    return [];
  }

}