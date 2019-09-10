<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 14/12/2017
 * Time: 17:34
 */

namespace bbn\appui;
use bbn;


class i18n extends bbn\models\cls\cache
{
  use
    bbn\models\tts\optional;

  protected static $extensions = ['js', 'json', 'php'];

  protected
    $parser,
    $translations = [],
    $user;
  
  /**
   * Initialize the class i18n
   * 
   * @param db 
   */
  public function __construct(bbn\db $db){
    parent::__construct($db);
    $this->parser = new \Gettext\Translations();
    $this->user = \bbn\user::get_instance();
  }
  /**
   * Returns the strings contained in the given php file
   *
   * @param string $file
   * @return array
   */
  public function analyze_php(string $file): array
  {
    $res = [];
    $php = file_get_contents($file);
    if ( $tmp = \Gettext\Translations::fromPhpCodeString($php, [
      'functions' => ['_' => 'gettext'],
      'file' => $file
      ]) ){
      foreach ( $tmp->getIterator() as $r => $tr ){
        $res[] = $tr->getOriginal();
      }
      $this->parser->mergeWith($tmp);
    }
    return array_unique($res);
  }

  /**
   * Returns the strings contained in the given js file
   *
   * @param string $file
   * @return array
   */
  public function analyze_js(string $file): array
  {
    $res = [];
    $js = file_get_contents($file);
    if ( $tmp = \Gettext\Translations::fromJsCodeString($js, [
      'functions' => [
        '_' => 'gettext',
        'bbn._' => 'gettext'
      ],
      'file' => $file
      ]) ){
      foreach ( $tmp->getIterator() as $r => $tr ){
        $res[] = $tr->getOriginal();
      }
      $this->parser->mergeWith($tmp);
    }
    return array_unique($res);
  }

  public function analyze_json(string $file): array
  {
    $res = [];
    $js = file_get_contents($file);
    if ( $tmp = \Gettext\Translations::fromJsonString($js, [
      'functions' => [
        '_' => 'gettext',
        'bbn._' => 'gettext'
      ],
      'file' => $file
      ]) ){
      foreach ( $tmp->getIterator() as $r => $tr ){
        $res[] = $tr->getOriginal();
      }
      $this->parser->mergeWith($tmp);
    }
    return array_unique($res);
  }

  /**
   * Returns the strings contained in the given html file
   *
   * @param string $file
   * @return array
   */
  public function analyze_html(string $file): array
  {
    $res = [];
    $js = file_get_contents($file);
    if ( $tmp = \Gettext\Translations::fromString($js, [
      'functions' => ['_' => 'gettext'],
      'file' => $file
    ]) ){
      foreach ( $tmp->getIterator() as $r => $tr ){
        $res[] = $tr->getOriginal();
      }
      $this->parser->mergeWith($tmp);
    }
    return array_unique($res);
  }

  /**
   * Returns the strings contained in the given file
   *
   * @param string $file
   * @return array
   */
  public function analyze_file(string $file): array
  {
    $res = [];
    $ext = bbn\str::file_ext($file);
    if ( \in_array($ext, self::$extensions, true) && is_file($file) ){
      switch ( $ext ){
        case 'html':
          $res = $this->analyze_php($file);
          break;
        case 'php':
          $res = $this->analyze_php($file);
          break;
        case 'js':
          $res = $this->analyze_js($file);
          break;
        /*case 'json':
          $res = $this->analyze_json($file);
          break;*/
      }
    }
    return $res;
  }

  /**
   * Returns an array containing the strings found in the given folder
   *
   * @param string $folder
   * @param boolean $deep
   * @return array
   */
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

  /**
   * Returns the parser
   *
   * @return void
   */
  public function get_parser(){
    return $this->parser;
  }

  public function result(){
    foreach ( $this->parser->getIterator() as $r => $tr ){
      $this->translations[] = $tr->getOriginal();
    }
    return array_unique($this->translations);
  }

  /**
   * get the id of the project from the id_option of a path
   *
   * @param $id_option
   * @param $projects
   * @return void
   */
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

  /**
   * Gets primaries langs from option
   *
   * @return void
   */
  public function get_primaries_langs(){
    $uid_languages =  self::get_appui_option_id('languages');
    $languages = options::get_instance()->full_tree($uid_languages);
    $primaries = array_values(array_filter($languages['items'], function($v) {
      return !empty($v['primary']);
    }));
    return $primaries;
  }



  /**
   * get the num of items['text'] in original language and num translations foreach lang in configured langs (for this project uses all primaries as configured langs) 
   *
   * @return void
   */
  public function get_num_options(){
    /** @var  $paths takes all options with i18n property setted*/
    $paths = options::get_instance()->find_i18n();

    $data = [];
    /**
    * creates the property data_widget that will have just num of items found for the option + 1 (the text of the option parent), the * * number of strings translated and the source language indexed to the language
    */
    $primaries = $this->get_primaries_langs();
    foreach ($primaries as $p ){
      $configured_langs[] = $p['code'];
    }
    foreach ( $paths as $p => $val){
      $parent = options::get_instance()->get_id_parent($paths[$p]['id']);

      foreach ( $configured_langs as $lang ) {
        $count = 0;
        $items = $paths[$p]['items'];
        /** push the text of the option into the array of strings */
        $items[] = [
          'id' => $paths[$p]['id'],
          'text' => $paths[$p]['text'],
          'id_parent' => $parent
        ];
        foreach ( $items as $idx => $item ){
          if ( $id = $this->db->select_one('bbn_i18n', 'id', [
            'exp'=> $item['text'],
            'lang' => $paths[$p]['language']
          ])){
            if ( $this->db->select_one('bbn_i18n_exp', 'id_exp', [
              'id_exp' => $id,
              'lang' => $lang
            ]) ){
              $count ++;
            }
          }
        }
        $paths[$p]['data_widget']['result'][$lang] = [
          'num' => count($items),
          'num_translations' => $count,
          'lang' => $lang
        ];
      }
      $paths[$p]['data_widget']['locale_dirs'] = [];

      unset($paths[$p]['items']);
      $data[] = $paths[$p];
    }
    return [
      'data'=> $data
    ];
  }



  /**
   * Gets the option with the property i18n setted and its items 
   *
   * @return void
   */
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

        /* check if the opt text is in bbn_i18n and takes translations from db */
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
            //'id_user'=> $this->user->get_id(),
            //'last_modified' => date('H-m-d H:i:s')

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


  /**
   * Gets the propriety language of the option
   *
   * @param id_option
   */
  public function get_language($id_option){
    return options::get_instance()->get_prop($id_option,'language');
  }
  
  /**
   * Gets the widgets initial data
   *
   * @param string $id_project
   * @param string $id_option
   * @return void
   */
  public function get_translations_widget($id_project, $id_option)
  {
    $success = false;
    $result = [];
    $locale_dirs = [];

    if ( $id_option &&
      ($o = options::get_instance()->option($id_option)) &&
      !empty($o['language']) &&
      ($parent = options::get_instance()->parent($id_option)) &&
      defined($parent['code']) &&
      isset($o['language']) ){
        $domain = $o['text'];

        // @var $to_explore the path to explore 
        $to_explore = constant($parent['code']);
        // @var $locale_dir the path to locale dir 
        //exeption for apst_app, don't need the code
        if( (constant($parent['code']) === \bbn\mvc::get_app_path()) && (strrpos('mvc/', $o['code'], 0) === 0) ) {
          $locale_dir = $to_explore.'locale';
        }
        else{
          $locale_dir = $to_explore.$o['code'].'locale';
        }
        $domain .= is_file($locale_dir.'/index.txt') ? file_get_contents($locale_dir.'/index.txt') : '';
        // @var $dirs scans dirs existing in locale folder for this path 
        if ( is_dir($locale_dir) ){
          // @var array $languages dirs in locale folder
          $dirs = \bbn\file\dir::get_dirs($locale_dir) ?: [];
          if(!empty($dirs)){
            foreach ($dirs as $l ){
              $languages[] = basename($l);
            }
          }
        }
        $new = 0;
        $i = 0;
        // @var array the languages found in locale dir 
        if ( !empty($languages) ){
          $result = [];
          foreach ( $languages as $lng ){
            // the root to file po & mo 
            $po = $locale_dir.'/'.$lng.'/LC_MESSAGES/'.$domain.'.po';
            $mo = $locale_dir.'/'.$lng.'/LC_MESSAGES/'.$domain.'.mo';

            // if a file po already exists takes its content 
            if ( is_file($po) ){
              $fileHandler = new \Sepia\PoParser\SourceHandler\FileSystem($po);
              $poParser = new \Sepia\PoParser\Parser($fileHandler);
              $Catalog  = \Sepia\PoParser\Parser::parseFile($po);
              $num_translations = 0;
              if ( $translations = $Catalog->getEntries() ){
                foreach( $translations as $tr ){
                  if ( $tr->getMsgStr() ){
                    $num_translations ++;
                  }
                }
                $result[$lng] = [
                  'num' => count($translations),
                  'num_translations' => $num_translations,
                  'lang' => $lng,
                  'num_translations_db' => $this->count_translations_db($id_option) ? $this->count_translations_db($id_option)[$lng] : 0
                ];
              }

            }
            // if the file po for the $lng doesn't exist $result is an empty object 
            else{
              if( !empty($this->count_translations_db($id_option)[$lng]) ){
                $count_translations = $this->count_translations_db($id_option)[$lng];
              }
              else{
                $count_translations = 0;
              }
              $result[$lng] = [
                'num' => 0,
                'num_translations' => 0,
                'lang' => $lng,
                'num_translations_db' => $count_translations
              ];
            }
          }
        }
      $i++;
      $success = true;
      if ( !empty($languages) ){
        $locale_dirs = $languages;
      }
    }
    return [
      'locale_dirs' => $locale_dirs,
      'result' => $result,
      'success' => $success,
    ];
  }

  /**
   * Returns an array containing the po files found for the id_option
   *
   * @param $id_option
   * @return void
   */
  public function get_po_files($id_option){
    if (!empty($id_option) && ($o = options::get_instance()->option($id_option)) &&
    ($parent = options::get_instance()->parent($id_option)) &&
    defined($parent['code']) ){
      $tmp = [];
      // @var  $to_explore the path to explore 
      $to_explore = constant($parent['code']).$o['code'];
      // @var  $locale_dir locale dir in the path
      $locale_dir = dirname($to_explore).'/locale';
      $dirs = \bbn\file\dir::get_dirs($locale_dir) ?: [];
      $languages = array_map(function($a){
        return basename($a);
      }, $dirs) ?: [];
      if ( !empty($languages) ){

        foreach ( $languages as $lng ){
          // the path of po and mo files 
          $idx = is_file($locale_dir.'/index.txt') ? file_get_contents($locale_dir.'/index.txt') : '';
          if ( is_file($locale_dir.'/'.$lng.'/LC_MESSAGES/'.$o['text'].$idx.'.po') ){
            $tmp[$lng]= $locale_dir.'/'.$lng.'/LC_MESSAGES/'.$o['text'].$idx.'.po';
          }

        }

      }

      return $tmp;
    }
  }
  
  /**
   * Count how many of the strings contained in po files are already in database
   *
   * @param string $id_option
   * @return void
   */
  public function count_translations_db($id_option){
    $count = [];
    $po = $this->get_po_files($id_option);
    if (!empty($po)){
      foreach ($po as $lang => $file) {
        $fileHandler = new \Sepia\PoParser\SourceHandler\FileSystem($file);
        $poParser = new \Sepia\PoParser\Parser($fileHandler);
        $Catalog  = \Sepia\PoParser\Parser::parseFile($file);
        $fromPo = $Catalog->getEntries();
        $source_language = $this->get_language($id_option);

        $count[$lang] = 0;
        foreach( $fromPo as $o ){
          if ( $exp = $o->getMsgId() ){
            $id = $this->db->select_one('bbn_i18n', 'id', ['exp' => $exp, 'lang' => $source_language]);
            if ( $string = $this->db->select_one('bbn_i18n_exp', 'expression', [
              'id_exp' => $id,
              'lang' => $lang
            ]) ){
              $count[$lang]++;
            }
          }

        }

      }

    }

    return $count;
  }

  /**
   * Returns the strings contained in the given path
   *
   * @param $id_option
   * @param $source_language
   * @param $languages
   * @return void
   */
  public function get_translations_strings($id_option, $source_language, $languages){

    if (
      !empty($id_option) &&
      !empty($source_language) &&
      ($o = options::get_instance()->option($id_option)) &&
      ($parent = options::get_instance()->option($o['id_parent'])) &&
      \defined($parent['code'])
    ){
      // @var string $to_explore The path to explore path of mvc 
      $to_explore = \constant($parent['code']).($o['code'] === '/' ? '' : $o['code']);

      $current_path = \constant($parent['code']);
      if ( constant($parent['code']) === \bbn\mvc::get_app_path() ){
        $current_dirs = bbn\file\dir::get_dirs( constant($parent['code']).($o['code'] === '/' ? '' : $o['code']));
			}

      //all dirs contained in current if not in vendor
      if ( constant($parent['code']) === BBN_LIB_PATH ){
        //case of plugins
        $src =  mb_substr($current_path.$o['code'], 0,-4);
        $current_dirs = bbn\file\dir::get_dirs($src);

      }
      else if ( constant($parent['code']) === BBN_CDN_PATH ){
        //case javascript and styles project apst
        $current_dirs[] =  constant($parent['code']).$o['code'];
      }

      $to_explore_dirs = [];

      //creates the array $to_explore_dirs containing mvc, plugins e components
      if ( !empty($current_dirs) ){
        foreach ($current_dirs as $key => $value) {
          if( constant($parent['code']) === \bbn\mvc::get_app_path() ){
            if (( strpos($value, 'locale') !== 0 ) && ( strpos($value, 'data') !== 0 ) && ( strpos($value, '.') !== 0 )){
						  $to_explore_dirs = $current_dirs;
            }
          }

          if ( constant($parent['code']) === BBN_CDN_PATH ){
            $to_explore_dirs[] = $current_dirs;
          }
          else if ( $parent['code'] === 'BBN_LIB_PATH'  ){
            //case of plugins appui
            $to_explore_dirs[] = $current_dirs[$key];
          }
        }
      }
      // @var  $locale_dir  the root of locale dir for this id_option
      $locale_dir = dirname($to_explore).'/locale';

      // @var $dirs scans dirs contained in locale folder of this path
      
      $res = [];
      //case of generate called from table
      if ( empty($languages) ){
        /** @var (array) $languages based on locale dirs found in the path*/
        $languages = array_map(function($a){
          return basename($a);
        }, \bbn\file\dir::get_dirs($locale_dir)) ?: [];
      }

      if ( !empty($to_explore_dirs) && ($parent['code'] !== 'BBN_CDN_PATH' )){

        foreach ( $to_explore_dirs as $c ){
					$res[] = $this->analyze_folder($c, true);

				}
      }
      else if( ( $parent['code'] === 'BBN_CDN_PATH' ) && (!empty($current_dirs)) ){
        foreach($current_dirs as $c ){
          $res[] = $this->analyze_folder($c, true);
        }
      }

      //all strings found in the different dirs $to_explore_dirs, merge all index of $res
      if(!empty($res)){
         $res = array_merge(...$res);
      }

      $news = [];
      $done = 0;

      foreach ( $res as $r => $val ){
        // for each string create a property 'path' containing the files' name in which the string is contained 

        $res[$r] = ['path' => $val];

        // checks if the table bbn_i18n of db already contains the string $r for this $source_lang 
        if ( !($id = $this->db->select_one('bbn_i18n', 'id', [
          'exp' => $r,
          'lang' => $source_language
        ])) ){
          // if the string $r is not in 'bbn_i18n' inserts the string 
          $this->db->insert_ignore('bbn_i18n', [
            'exp' => $r,
            'lang' => $source_language,
          ]);
          $id = $this->db->last_id();

        }
        // create the property 'id_exp' for the string $r 
        $res[$r]['id_exp'] = $id;

        // puts the string $r into the property 'original_exp' (I'll use only array_values at the end) *
        $res[$r]['original_exp'] = $r;

        // checks in 'bbn_i18n_exp' if the string $r already exist for this $source_lang 
        if( !( $id_exp = $this->db->select_one('bbn_i18n_exp', 'id_exp', [
          'id_exp' => $id,
          'lang' => $source_language
        ]) ) ){

          // if the string $r is not in 'bbn_i18n_exp' inserts the string
          //  $done will be the number of strings found in the folder $to_explore that haven't been found in the table
          // 'bbn_i18n_exp' of db, so $done is the number of new strings inserted in in 'bbn_i18n_exp'
          $done += (int)$this->db->insert_ignore('bbn_i18n_exp', [
            'id_exp' => $id,
            'lang' => $source_language,
            'expression' => $r
          ]);
          //creates an array of new strings found in the folder;
          $news[] = $r;
        }
        // $languages is the array of languages existing in locale dir
        foreach ( $languages as $lng ){
          //  create a property indexed to the code of $lng containing the string $r from 'bbn_i18n_exp' in this $lng 
          $res[$r][$lng] = (string)$this->db->select_one(
            'bbn_i18n_exp',
              'expression',
            [
              'id_exp' => $id,
              'lang' => $lng
            ]
          );
        }
      }
      return [
        'news' => $news,
        'id_option' => $id_option,
        'res' => array_values($res),
        'done' => $done,
        'languages' => $languages,
        'path' => $to_explore,
        'success' => true
      ];
    }
  }

  /**
   * Returns the informations relative to traslation of the given $id_option of a $id_project. The data is formatted to be shown in a table
   *
   * @param string $id_project
   * @param string $id_option
   * @return void
   */
  public function get_translations_table($id_project, $id_option){
    if ( !empty($id_option) &&
      ($o = options::get_instance()->option($id_option)) &&
      ($parent = options::get_instance()->parent($id_option)) &&
      defined($parent['code']) ){

      // @var  $path_source_lang the property language of the id_option (the path) 
      $path_source_lang = options::get_instance()->get_prop($id_option, 'language');

      // @var  $to_explore the path to explore 
      $to_explore = constant($parent['code']);

       //exeption for apst_app, don't need the code
      if( (constant($parent['code']) === \bbn\mvc::get_app_path()) && (strrpos('mvc/', $o['code'], 0) === 0) ) {
        $locale_dir = $to_explore.'locale';
      }
      else{
        $locale_dir = $to_explore.$o['code'].'locale';
      }
      $languages = array_map(function($a){
        return basename($a);
      }, \bbn\file\dir::get_dirs($locale_dir)) ?: [];
      $i = 0;
      $res = [];
      $project = new bbn\appui\project($this->db, $id_project);
      if ( !empty($languages) ){
        $po_file = [];
        $success = false;
        foreach ( $languages as $lng ){
          // the path of po and mo files 
          $idx = is_file($locale_dir.'/index.txt') ? file_get_contents($locale_dir.'/index.txt') : '';
          $po = $locale_dir.'/'.$lng.'/LC_MESSAGES/'.$o['text'].$idx.'.po';
          $mo = $locale_dir.'/'.$lng.'/LC_MESSAGES/'.$o['text'].$idx.'.mo';
          
          // if the file po exist takes its content 
          if (file_exists($po)){
            $fileHandler = new \Sepia\PoParser\SourceHandler\FileSystem($po);
            $poParser = new \Sepia\PoParser\Parser($fileHandler);
            $Catalog  = \Sepia\PoParser\Parser::parseFile($po);

            if ( !empty( $translations = $Catalog->getEntries() ) ){
              foreach ($translations as $i => $t ){

                // @var  $original the original expression 
                $original = $t->getMsgId();

                $po_file[$i][$lng]['original'] = $original;

                // the translation of the string found in the po file 
                $po_file[$i][$lng]['translations_po'] =  $t->getMsgStr();


                // @var  $id takes the id of the original expression in db 
                if ( $id = $this->db->select_one('bbn_i18n',
                  'id',
                  [
                    'exp' => $original,
                    'lang' => $path_source_lang
                  ]) ){
                  $po_file[$i][$lng]['translations_db'] = $this->db->select_one('bbn_i18n_exp', 'expression', ['id_exp' => $id, 'lang' => $lng]);

                  // the id of the string 
                  $po_file[$i][$lng]['id_exp'] = $id;

                  // @var (array) takes $paths of files in which the string was found from the file po 
                  $paths = $t->getReference();
                   
                  // get the url to use it for the link to ide from the table 
                  foreach ( $paths as $p ){
                    $po_file[$i][$lng]['paths'][] = $project->real_to_url_i18n($p);
                  }
                  // the number of times the strings is found in the files of the path  
                  $po_file[$i][$lng]['occurrence'] = !empty($po_file[$i][$path_source_lang]) ? count($po_file[$i][$path_source_lang]['paths']) : 0;

                };
              }
              
              $success = true;
            }
          }
        }
      }
      
      return [
        'path_source_lang' => $path_source_lang,
        'path' => $o['text'],
        'success' => $success,
        'languages' => $languages,
        'total' => count(array_values($po_file)),
        'strings' => array_values($po_file),
        'id_option' => $id_option,
      ];
    }

  }

}
