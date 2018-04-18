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
    $translations = [],
    $user;

  public function __construct(bbn\db $db){
    parent::__construct($db);
    $this->parser = new \Gettext\Translations();
    $this->user = \bbn\user::get_instance();
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

//get the id of the project from the id_option of a path
  public function get_id_project($id_option, $projects){
    foreach( $projects as $i => $p ){
      foreach ( $projects[$i]['path'] as $idx => $pa ){
        if ( $projects[$i]['path'][$idx]['id_option'] === $id_option ){
          return $projects[$i]['id'];
        }
      }
    }
  }

  public function update_db(){
    foreach ( $this->result() as $st ){
      $this->db->insert_ignore();
    }
  }

  public function insert(){
    foreach ( $this->result() as $st ){
      $this->db->insert();
    }
  }

  //get primaries langs from option
  public function get_primaries_langs(){
    $uid_languages =  options::get_instance()->from_code('languages', 'i18n', 'appui');
    $languages = options::get_instance()->full_tree($uid_languages);
    $primaries = array_values(array_filter($languages['items'], function($v) {
      return isset($v['primary']) && ($v['primary'] == '1');
    }));
    return $primaries;
  }

  /** gets the option with the property i18n setted and its items */
  public function get_options(){
    /** @var ( array) $paths get all options having i18n property setted and its items */
    $paths = options::get_instance()->find_i18n();
    $res = [];
    foreach ( $paths as $p => $val ){
      $res[$p] = [
        'text'=> $paths[$p]['text'],
        'opt_language' => $paths[$p]['language'],
        'strings' => [],
        'id_option' => $paths[$p]['id']
      ];

      /** @todo AT THE MOMENT I'M NOT CONSIDERING LANGUAGES OF TRANSLATION */
      foreach ($paths[$p]['items'] as $i => $value){

        /** check if the opt text is in bbn_i18n and takes translations from db */
        if ( $exp = $this->db->rselect('bbn_i18n',['id', 'exp', 'lang'] , [
          'exp' => $paths[$p]['items'][$i]['text'],
          'lang' => $paths[$p]['language']
          ]
        ) ){

          $translated = $this->db->rselect_all('bbn_i18n_exp', ['id_exp', 'expression', 'lang'],  ['id_exp' => $exp['id'] ]);
          if ( !empty($translated) ){
          /** @var  $languages the array of languages found in db for the options*/
            $languages = [];
            $translated_exp = '';


            foreach ($translated as $t => $trans){
              if ( !in_array($translated[$t]['lang'], $translated) ){
                $languages[] = $translated[$t]['lang'];
              }
              $translated_exp = $translated[$t]['expression'];
            }
            if ( !empty($languages) ){
              foreach($languages as $lang){
                $res[$p]['strings'][] = [
                  $lang => [
                    'id_exp' => $exp['id'],
                    'original' => $exp['exp'],
                    'translation_db' => $translated_exp
                  ]
                ];
              }
            }
            }
        }
        else {
          if ( $this->db->insert('bbn_i18n', [
            'exp' => $paths[$p]['items'][$i]['text'],
            'lang' =>  $paths[$p]['language'],
            'id_user'=> $this->user->get_id(),
            'last_modified' => date('H-m-d H:i:s')

          ]) ){
            $id = $this->db->last_id();
            $this->db->insert_ignore(
              'bbn_i18n_exp', [
                'id_exp' => $id,
                'expression'=> $paths[$p]['items'][$i]['text'],
                'lang' => $paths[$p]['language']
              ]
            );
            $res[$p]['strings'][] = [
              $paths[$p]['language'] => [
                'id_exp' => $id,
                'original' => $paths[$p]['items'][$i]['text'],
                'translation_db' => $paths[$p]['items'][$i]['text']
              ]
            ];
          };


        }

      }
    }
    return $res;
  }
}