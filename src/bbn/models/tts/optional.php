<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 05/11/2016
 * Time: 02:53
 */

namespace bbn\models\tts;

use bbn;


trait optional
{

  private static $id_options = [];

  public static function get_id_option_category($code = null){
    if (
      ($opt = bbn\appui\options::get_instance()) &&
      (self::$id_option_root || self::$code_option_root)
    ){
      if ( !self::$id_option_root ){
        self::$id_option_root = $opt->from_code(self::$code_option_root);
      }
      if ( self::$id_option_root ){
        if ( !$code ){
          return self::$id_option_root;
        }
        else if ( isset(self::$id_options[$code]) ){
          return self::$id_options[$code];
        }
        else if ( $id = $opt->from_code($code, self::$id_option_root) ){
          self::$id_options[$code] = $id;
          return self::$id_options[$code];
        }
      }
    }
    return false;
  }

  public static function get_codes_options($code){
    if (
      ($opt = bbn\appui\options::get_instance()) &&
      ($id = self::get_id_option_category($code))
    ){
      return $opt->get_ids($id);
    }
  }

  public static function get_tree_options($code = null){
    if (
      ($opt = bbn\appui\options::get_instance()) &&
      ($id = self::get_id_option_category($code)) &&
      ($tree = $opt->full_tree($id))
    ){
      return $tree['items'];
    }
    return false;
  }

  public static function get_options($code = null){
    if (
      ($opt = bbn\appui\options::get_instance()) &&
      ($id = self::get_id_option_category($code))
    ){
      return $opt->full_options($id);
    }
    return false;
  }

  public static function get_id_option($code, $cat){
    if ( is_string($cat) && !isset(self::$id_options[$cat]) ){
      self::get_id_option_category($cat);
    }
    if (
      ($opt = bbn\appui\options::get_instance()) &&
      isset(self::$id_options[$cat])
    ){
      return $opt->from_code($code, self::$id_options[$cat]);
    }
    return false;
  }

  public static function get_text_value_options($code = null){
    if (
      ($opt = bbn\appui\options::get_instance()) &&
      ($id = self::get_id_option_category($code))
    ){
      return $opt->text_value_options($id);
    }
    return [];
  }




}