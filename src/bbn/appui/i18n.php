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

  public function analyze_php(string $php): self
  {
    if ( $tmp = \Gettext\Translations::fromPhpCodeString($php, ['functions' => ['_' => 'gettext']]) ){
      $this->parser->mergeWith($tmp);
    }
    return $this;
  }

  public function analyze_js(string $js): self
  {
    if ( $tmp = \Gettext\Translations::fromJsCodeString($js, ['functions' => ['_' => 'gettext', 'bbn._' => 
      'gettext']]) ){
      $this->parser->mergeWith($tmp);
    }
    return $this;
  }

  public function analyze_json(string $js): self
  {
    if ( $tmp = \Gettext\Translations::fromJsonString($js, ['functions' => ['_' => 'gettext', 'bbn._' =>
      'gettext']]) ){
      $this->parser->mergeWith($tmp);
    }
    return $this;
  }

  public function analyze_file(string $file): self
  {
    $ext = bbn\str::file_ext($file);
    if ( \in_array($ext, self::$extensions, true) && is_file($file) ){
      $content = file_get_contents($file);
      switch ( $ext ){
        case 'php':
          $this->analyze_php($content);
          break;
        case 'js':
          $this->analyze_js($content);
          break;
        case 'json':
          $this->analyze_json($content);
          break;
      }
    }
    return $this;
  }

  public function analyse_folder(string $folder = '.', bool $deep = false): self
  {
    if (  \is_dir($folder) ){
      $files = $deep ? bbn\file\dir::scan($folder) : bbn\file\dir::get_files($folder);
      foreach ( $files as $f ){
        $this->analyze_file($f);
      }
    }
    return $this;
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