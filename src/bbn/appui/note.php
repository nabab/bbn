<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 14/04/2016
 * Time: 20:38
 */

namespace bbn\appui;


class note extends \bbn\objdb
{
  private static
    $medias = [],
    $id_media;

  private static function get_id_media(){
    if ( !isset(self::$id_media) && ($opt = \bbn\appui\options::get_options()) ){
      self::$id_media = $opt->from_code('media', 'bbn_notes');
    }
    return self::$id_media;
  }

  protected static function get_medias($force = false){
    if ( empty(self::$medias) || $force ){
      if ( ($opt = \bbn\appui\options::get_options()) && self::get_id_media() ){
        $tree = $opt->tree(self::$id_media);
        self::$medias = isset($tree['items']) ? $tree['items'] : false;
      }
      else{
        self::$medias = false;
      }
    }
    return self::$medias;
  }

  protected static function get_media($code, $force = false){
    if ( !isset(self::$medias[$code]) || $force ){
      self::get_medias(1);
      if ( !isset(self::$medias[$code]) ){
        self::$medias[$code] = false;
      }
    }
    return isset(self::$medias[$code]) ? self::$medias[$code] : false;
  }

  public function medias(){
    return self::get_medias();
  }

  public function id_media($code){
    return self::get_media($code);
  }

  public function insert($title, $content, $media = 'text', $private = false){

  }



}