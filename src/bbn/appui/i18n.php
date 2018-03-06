<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 14/12/2017
 * Time: 17:34
 */

namespace bbn\appui;
use bbn;


class i18n extends bbn\models\cls\db{

  use
    bbn\models\tts\optional;

  protected static $extensions = ['js', 'json', 'php'];

  protected
    $parser,
    $translations = [];

  public function __construct(bbn\db $db){
    parent::__construct($db);
    $this->parser = new \Gettext\Translations();
  }

  public function analyze_php(string $php): array
  {
    $res = [];
    if ( $tmp = \Gettext\Translations::fromPhpCodeString($php, ['functions' => ['_' => 'gettext']]) ){
      foreach ( $tmp->getIterator() as $r => $tr ){
        $res[] = $tr->getOriginal();
      }
      $this->parser->mergeWith($tmp);
    }
    return array_unique($res);
  }

  public function analyze_js(string $js): array
  {
    $res = [];
    if ( $tmp = \Gettext\Translations::fromJsCodeString($js, ['functions' => ['_' => 'gettext', 'bbn._' =>
      'gettext']]) ){
      foreach ( $tmp->getIterator() as $r => $tr ){
        $res[] = $tr->getOriginal();
      }
      $this->parser->mergeWith($tmp);
    }
    return array_unique($res);
  }

  public function analyze_json(string $js): array
  {
    $res = [];
    if ( $tmp = \Gettext\Translations::fromJsonString($js, ['functions' => ['_' => 'gettext', 'bbn._' =>
      'gettext']]) ){
      foreach ( $tmp->getIterator() as $r => $tr ){
        $res[] = $tr->getOriginal();
      }
      $this->parser->mergeWith($tmp);
    }
    return array_unique($res);
  }

  public function analyze_html(string $js): array
  {
    $res = [];
    if ( $tmp = \Gettext\Translations::fromString($js, ['functions' => ['_' => 'gettext']]) ){
      foreach ( $tmp->getIterator() as $r => $tr ){
        $res[] = $tr->getOriginal();
      }
      $this->parser->mergeWith($tmp);
    }
    return array_unique($res);
  }

  public function analyze_file(string $file): array
  {
    $res = [];
    $ext = bbn\str::file_ext($file);
    if ( \in_array($ext, self::$extensions, true) && is_file($file) ){
      $content = file_get_contents($file);
      switch ( $ext ){
        case 'html':
          $res = $this->analyze_php($content);
          break;
        case 'php':
          $res = $this->analyze_php($content);
          break;
        case 'js':
          $res = $this->analyze_js($content);
          break;
        case 'json':
          $res = $this->analyze_json($content);
          break;
      }
    }
    return $res;
  }

  public function analyze_folder(string $folder = '.', bool $deep = false): array
  {
    $res = [];
    if (  \is_dir($folder) ){
      $files = $deep ? bbn\file\dir::scan($folder, 'file') : bbn\file\dir::get_files($folder);
      foreach ( $files as $f ){
        $words = $this->analyze_file($f);
        foreach ( $words as $word ){
          if ( !isset($res[$word]) ){
            $res[$word] = [];
          }
          if ( !in_array($f, $res[$word]) ){
            $res[$word][] = $f;
          }
        }
      }
    }
    return $res;
  }

  public function get_parser(){
    return $this->parser;
  }

  public function result(){
    foreach ( $this->parser->getIterator() as $r => $tr ){
      $this->translations[] = $tr->getOriginal();
    }
    return array_unique($this->translations);
  }

  public function update_db(){
    foreach ( $this->result() as $st ){
      $this->db->insert_ignore();
    }
  }

  public function insert(){
    foreach ( $this->result() as $st ){
      $this->db->insert_ignore();
    }
  }



}