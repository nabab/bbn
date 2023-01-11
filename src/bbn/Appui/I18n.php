<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 14/12/2017
 * Time: 17:34
 */

namespace bbn\Appui;

use bbn;
use bbn\X;

class I18n extends bbn\Models\Cls\Cache
{
  use
    bbn\Models\Tts\Optional;

  protected static $extensions = ['js', 'json', 'php', 'html'];

  protected $parser;

  protected $translations = [];

  protected $user;

  protected $options;

  protected $id_project;


  /**
   * Initialize the class I18n
   *
   * @param db
   */
  public function __construct(bbn\Db $db, string $code = null)
  {
    parent::__construct($db);
    $this->parser  = new \Gettext\Translations();
    $this->user    = \bbn\User::getInstance();
    $this->options = new \bbn\Appui\Option($db);
    if (empty($code)) {
      if (\defined('BBN_APP_NAME')) {
        $code = BBN_APP_NAME;
      }
      else {
        throw new \Exception(X::_("The project's ID/Code is mandatory"));
      }
    }

    $this->id_project = \bbn\Str::isUid($code) ? $code : $this->options->fromCode($code, 'list', 'project', 'appui');
    if (empty($this->id_project)) {
      throw new \Exception(X::_("Project's ID not found"));
    }
  }


  /**
   * Returns the strings contained in the given php file
   *
   * @param string $file
   * @return array
   */
  public function analyzePhp(string $file): array
  {
    $res = [];
    $php = file_get_contents($file);
    if ($tmp = \Gettext\Translations::fromPhpCodeString(
      $php, [
      'functions' => ['_' => 'gettext'],
      'file' => $file
      ]
    )
    ) {
      foreach ($tmp->getIterator() as $r => $tr){
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
  public function analyzeJs(string $file): array
  {
    $res = [];
    $js  = file_get_contents($file);
    if ($tmp = \Gettext\Translations::fromJsCodeString(
      $js, [
      'functions' => [
        '_' => 'gettext',
        'bbn._' => 'gettext'
      ],
      'file' => $file
      ]
    )
    ) {
      foreach ($tmp->getIterator() as $r => $tr){
        $res[] = $tr->getOriginal();
      }

      $this->parser->mergeWith($tmp);
    }

    if (preg_match_all('/`([^`]*)`/', $js, $matches)) {
      foreach ($matches[0] as $st){
        if ($tmp = \Gettext\Translations::fromVueJsString(
          '<template>'.$st.'</template>', [
          'functions' => [
            '_' => 'gettext',
            'bbn._' => 'gettext'
          ],
          'file' => $file
          ]
        )
        ) {
          foreach ($tmp->getIterator() as $r => $tr){
            $res[] = $tr->getOriginal();
          }

          $this->parser->mergeWith($tmp);
        }
      }
    }

    /*if($file === '/home/thomas/domains/apstapp2.thomas.lan/_appui/vendor/bbn/appui-task/src/components/tab/tracker/tracker.js'){
      die(\bbn\X::hdump($res, $js));
    }*/

    return array_unique($res);
  }


  public function analyzeJson(string $file): array
  {
    $res = [];
    $js  = file_get_contents($file);
    if ($tmp = \Gettext\Translations::fromJsCodeString(
      $js, [
      'functions' => [
        '_' => 'gettext',
        'bbn._' => 'gettext'
      ],
      'file' => $file
      ]
    )
    ) {
      foreach ($tmp->getIterator() as $r => $tr){
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
  public function analyzeHtml(string $file): array
  {
    $res = [];
    $js  = file_get_contents($file);
    if ($tmp = \Gettext\Translations::fromVueJsString(
      '<template>'.$js.'</template>', [
      'functions' => [
        '_' => 'gettext',
        'bbn._' => 'gettext'
      ]
      ]
    )
    ) {
      foreach ($tmp->getIterator() as $r => $tr){
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
  public function analyzeFile(string $file): array
  {
    $res = [];
    $ext = bbn\Str::fileExt($file);
    if (\in_array($ext, self::$extensions, true) && is_file($file)) {
      switch ($ext){
        case 'html':
          $res = $this->analyzeHtml($file);
          break;
        case 'php':
          $res = $this->analyzePhp($file);
          break;
        case 'js':
          $res = $this->analyzeJs($file);
          break;
        /*case 'json':
          $res = $this->analyzeJson($file);
          break;*/
      }
    }

    return $res;
  }


  /**
   * Returns an array containing the strings found in the given folder
   *
   * @param string  $folder
   * @param boolean $deep
   * @return array
   */
  public function analyzeFolder(string $folder = '.', bool $deep = false): array
  {
    $res = [];
    if (\is_dir($folder)) {
      $files = $deep ? bbn\File\Dir::scan($folder, 'file') : bbn\File\Dir::getFiles($folder);
      foreach ($files as $f){
        $words = $this->analyzeFile($f);
        foreach ($words as $word){
          if (!isset($res[$word])) {
            $res[$word] = [];
          }

          if (!in_array($f, $res[$word])) {
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
  public function getParser()
  {
    return $this->parser;
  }


  public function result()
  {
    foreach ($this->parser->getIterator() as $r => $tr){
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
  public function getIdProject($id_option, $projects)
  {
    foreach($projects as $i => $p){
      foreach ($projects[$i]['path'] as $idx => $pa){
        if ($projects[$i]['path'][$idx]['id_option'] === $id_option) {
          return $projects[$i]['id'];
        }
      }
    }
  }


  /**
   * Gets primaries langs from option
   *
   * @return array
   */
  public function getPrimariesLangs(): array
  {
    if ($languages = $this->options->fullOptions('languages', 'i18n', 'appui')) {
      return array_values(
        array_filter(
          $languages, function ($v) {
            return !empty($v['primary']);
          }
        )
      );
    }
    return [];
  }


  /**
   * get the num of items['text'] in original language and num translations foreach lang in configured langs (for this project uses all primaries as configured langs)
   *
   * @return void
   */
  public function getNumOptions()
  {
    /** @var  $paths takes all options with i18n property setted*/
    $paths = $this->options->findI18n(true);
    $data = [];
    /**
    * creates the property data_widget that will have just num of items found for the option + 1 (the text of the option parent), the * * number of strings translated and the source language indexed to the language
    */
    $primaries = $this->getPrimariesLangs();
    foreach ($paths as $p => $val){
      $parent = $this->options->getIdParent($paths[$p]['id']);
      foreach ($primaries as $p) {
        $lang = $p['code'];
        $count = 0;
        $items = $paths[$p]['items'];
        /** push the text of the option into the array of strings */
        $items[] = [
          'id' => $paths[$p]['id'],
          'text' => $paths[$p]['text'],
          'id_parent' => $parent
        ];
        foreach ($items as $idx => $item){
          if (($id = $this->db->selectOne('bbn_i18n', 'id', [
              'exp' => $this->normlizeText($item['text']),
              'lang' => $paths[$p]['language']
            ]))
            && $this->db->selectOne('bbn_i18n_exp', 'id_exp', [
              'id_exp' => $id,
              'lang' => $lang
            ])
          ) {
            $count++;
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
      'data' => $data
    ];
  }


  /**
   * get the num of items['text'] in original language and num translations foreach lang in configured langs (for this project uses all primaries as configured langs)
   *
   * @return void
   */
  public function getNumOption($id)
  {
    /** @var  $paths takes all options with i18n property setted*/
    $paths = $this->options->findI18nOption($id);
    $data  = [];
    /**
    * creates the property data_widget that will have just num of items found for the option + 1 (the text of the option parent), the * * number of strings translated and the source language indexed to the language
    */
    $primaries = $this->getPrimariesLangs();
    foreach ($paths as $p => $val){
      $parent = $this->options->getIdParent($paths[$p]['id']);
      foreach ($primaries as $p) {
        $lang = $p['code'];
        $count = 0;
        $items = $paths[$p]['items'];
        /** push the text of the option into the array of strings */
        $items[] = [
          'id' => $paths[$p]['id'],
          'text' => $paths[$p]['text'],
          'id_parent' => $parent
        ];
        foreach ($items as $idx => $item){
          if (($id = $this->db->selectOne('bbn_i18n', 'id', [
              'exp' => $this->normlizeText($item['text']),
              'lang' => $paths[$p]['language']
            ]))
            && $this->db->selectOne('bbn_i18n_exp', 'id_exp', [
              'id_exp' => $id,
              'lang' => $lang
            ])
          ) {
            $count ++;
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
      'data' => $data
    ];
  }


  /**
   * Gets the option with the property i18n setted and its items
   *
   * @return void
   */
  public function getOptions()
  {
    /** @var ( array) $paths get all options having i18n property setted and its items */
    $paths = $this->options->findI18n(true);
    $res   = [];
    foreach ($paths as $p => $val){
      $res[$p] = [
        'text' => $paths[$p]['text'],
        'opt_language' => $paths[$p]['language'],
        'strings' => [],
        'id_option' => $paths[$p]['id']
      ];

      /** @todo AT THE MOMENT I'M NOT CONSIDERING LANGUAGES OF TRANSLATION */
      foreach ($paths[$p]['items'] as $i => $value){
        /* check if the opt text is in bbn_i18n and takes translations from db */
        if ($exp = $this->db->rselect('bbn_i18n', [
          'id',
          'exp',
          'lang'
        ], [
          'exp' => $this->normlizeText($paths[$p]['items'][$i]['text']),
          'lang' => $paths[$p]['language']
        ])) {
          if ($translated = $this->db->rselectAll('bbn_i18n_exp', [
            'id_exp',
            'expression',
            'lang'
          ], [
            'id_exp' => $exp['id']
          ])) {
            /** @var  $languages the array of languages found in db for the options*/
            $languages      = [];
            $translated_exp = '';
            foreach ($translated as $t => $trans){
              if (!in_array($translated[$t]['lang'], $translated)) {
                $languages[] = $translated[$t]['lang'];
              }

              $translated_exp = $translated[$t]['expression'];
            }

            if (!empty($languages)) {
              foreach($languages as $lang){
                $res[$p]['strings'][] = [
                  $lang => [
                    'id_exp' => $exp['id'],
                    'exp' => $exp['exp'],
                    'translation_db' => $translated_exp
                  ]
                ];
              }
            }
          }
        }
        else {
          if ($this->db->insert('bbn_i18n', [
            'exp' => $this->normlizeText($paths[$p]['items'][$i]['text']),
            'lang' => $paths[$p]['language']
          ])) {
            $id = $this->db->lastId();
            $this->db->insertIgnore('bbn_i18n_exp', [
              'id_exp' => $id,
              'expression' => $this->normlizeText($paths[$p]['items'][$i]['text']),
              'lang' => $paths[$p]['language']
            ]);
            $res[$p]['strings'][] = [
              $paths[$p]['language'] => [
                'id_exp' => $id,
                'exp' => $paths[$p]['items'][$i]['text'],
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
  public function getLanguage($id_option)
  {
    return $this->options->getProp($id_option,'language');
  }


  /**
   * Gets the widgets initial data
   *
   * @param string $id_project
   * @param string $id_option
   * @return void
   */
  public function getTranslationsWidget($id_project, $id_option)
  {
    $success     = false;
    $result      = [];
    $locale_dirs = [];

    if ($id_option
        && ($o = $this->options->option($id_option))
        && isset($o['language'])
    ) {
        // @var $to_explore the path to explore
        $to_explore = $this->getPathToExplore($id_option);
        // @var $locale_dir the path to locale dir
        $locale_dir = $this->getLocaleDirPath($id_option);
        //die(var_dump($locale_dir, $to_explore));

        //the txt file in the locale folder
        $index = $this->getIndexPath($id_option);

        //the text of the option . the number written in the $index file
        $domain = $o['text'].(is_file($index) ? file_get_contents($index) : '');
        // @var $dirs scans dirs existing in locale folder for this path
      if (is_dir($locale_dir)) {
        // @var array $languages dirs in locale folder
        $dirs = \bbn\File\Dir::getDirs($locale_dir) ?: [];
        if (!empty($dirs)) {
          foreach ($dirs as $l){
            $languages[] = X::basename($l);
          }
        }
      }

        $new = 0;
        $i   = 0;
        // @var array the languages found in locale dir
      if (!empty($languages)) {
        $result = [];
        foreach ($languages as $lng){
          // the root to file po & mo
          $po = $locale_dir.'/'.$lng.'/LC_MESSAGES/'.$domain.'.po';
          $mo = $locale_dir.'/'.$lng.'/LC_MESSAGES/'.$domain.'.mo';
          // if a file po already exists takes its content
          if (is_file($po)) {
            $num_translations = 0;
            if ($translations = $this->parsePoFile($po)) {
              foreach($translations as $tr){
                if ($tr->getMsgStr()) {
                  $num_translations ++;
                }
              }

              $result[$lng] = [
                'num' => count($translations),
                'num_translations' => $num_translations,
                'lang' => $lng,
                'num_translations_db' => $this->countTranslationsDb($id_option) ? $this->countTranslationsDb($id_option)[$lng] : 0
              ];
            }
          }
          // if the file po for the $lng doesn't exist $result is an empty object
          else{
            if(!empty($this->countTranslationsDb($id_option)[$lng])) {
              $count_translations = $this->countTranslationsDb($id_option)[$lng];
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
      if (!empty($languages)) {
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
   * Gets the widgets initial data for options
   *
   * @param string $id_project
   * @param string $id_option
   * @return void
   */
  public function getOptionsTranslationsWidget($lang)
  {
    $langs = [];
    $result = [];
    $primaries = $this->getPrimariesLangs();
    if ($options = $this->options->findI18nByLang($lang)) {
      foreach ($primaries as $p) {
        $count = 0;
        foreach ($options as $item) {
          if (($id = $this->db->selectOne('bbn_i18n', 'id', [
              'exp' => $this->normlizeText($item['text']),
              'lang' => $lang
            ]))
            && $this->db->selectOne('bbn_i18n_exp', 'id_exp', [
              'id_exp' => $id,
              'lang' => $p['code']
            ])
          ) {
            $count++;
          }
        }
        $result[$p['code']] = [
          'lang' => $p['code'],
          'num' => count($options),
          'num_translations' => $count,
          'num_translations_db' => $count
        ];
      }
    }

    return [
      'locale_dirs' => \array_map(function($p){
        return $p['code'];
      }, $primaries),
      'result' => $result
    ];
  }


  /**
   * Returns an array containing the po files found for the id_option
   *
   * @param $id_option
   * @return array
   */
  public function getPoFiles($id_option)
  {
    if (!empty($id_option) && ($o = $this->options->option($id_option))
        && ($parent = $this->options->parent($id_option))
        && defined($parent['code'])
    ) {
      $tmp = [];
      // @var  $to_explore the path to explore
      $to_explore = $this->getPathToExplore($id_option);
      // @var  $locale_dir locale dir in the path
      $locale_dir = $this->getLocaleDirPath($id_option);
      $dirs       = \bbn\File\Dir::getDirs($locale_dir) ?: [];
      $languages  = array_map(
        function ($a) {
          return X::basename($a);
        }, $dirs
      ) ?: [];
      if (!empty($languages)) {
        foreach ($languages as $lng){
          // the path of po and mo files
          $idx = is_file($locale_dir.'/index.txt') ? file_get_contents($locale_dir.'/index.txt') : '';
          if (is_file($locale_dir.'/'.$lng.'/LC_MESSAGES/'.$o['text'].$idx.'.po')) {
            $tmp[$lng] = $locale_dir.'/'.$lng.'/LC_MESSAGES/'.$o['text'].$idx.'.po';
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
  public function countTranslationsDb($id_option)
  {
    $count = [];
    $po    = $this->getPoFiles($id_option);
    if (!empty($po)) {
      foreach ($po as $lang => $file) {
        $fromPo          = $this->parsePoFile($file);
        $source_language = $this->getLanguage($id_option);

        $count[$lang] = 0;
        foreach ($fromPo as $o) {
          if (($exp = $o->getMsgId())
            && ($id = $this->db->selectOne('bbn_i18n', 'id', [
              'exp' => $this->normlizeText($exp),
              'lang' => $source_language
            ]))
            && $this->db->selectOne('bbn_i18n_exp', 'expression', [
              'id_exp' => $id,
              'lang' => $lang
            ])
          ) {
            $count[$lang]++;
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
  public function getTranslationsStrings($id_option, $source_language, $languages)
  {
    if (!empty($id_option)
        && !empty($source_language)
        // @var string $to_explore The path to explore path of mvc
        && ($to_explore = $this->getPathToExplore($id_option))
        //the position of locale dir
        && ($locale_dir = $this->getLocaleDirPath($id_option))
    ) {
      //creates the array $to_explore_dirs containing mvc, plugins e components
      if ($to_explore_dirs = bbn\File\Dir::getDirs($to_explore)) {
        $current_dirs = array_values(
          array_filter(
            $to_explore_dirs, function ($a) {
              $basename = X::basename($a);
              if(( strpos($basename, 'locale') !== 0 )
                  && ( strpos($basename, 'data') !== 0 )
                  && ( strpos($basename, '.') !== 0 )
              ) {
                return $a;
              }
            }
          )
        );
      }

      $res = [];

      //case of generate called from table
      if (empty($languages)) {
        /** @var (array) $languages based on locale dirs found in the path*/
        $languages = array_map(
          function ($a) {
            return X::basename($a);
          }, \bbn\File\Dir::getDirs($locale_dir)
        ) ?: [];
      }

      if (!empty($to_explore_dirs)) {
        foreach ($to_explore_dirs as $c){
          if ($ana = $this->analyzeFolder($c, true)) {
            foreach ($ana as $exp => $an) {
              if (!isset($res[$exp])) {
                $res[$exp] = $an;
              }
              else {
                $res[$exp] = array_merge($res[$exp], $an);
              }
            }
          }
        }
      }

      $news = [];
      $done = 0;

      foreach ($res as $r => $val){
        // for each string create a property 'path' containing the files' name in which the string is contained

        $res[$r] = ['path' => $val];

        // checks if the table bbn_i18n of db already contains the string $r for this $source_lang
        if (!($id = $this->db->selectOne('bbn_i18n', 'id', [
          'exp' => $this->normlizeText($r),
          'lang' => $source_language
        ]))) {
          // if the string $r is not in 'bbn_i18n' inserts the string
          if ($this->db->insertIgnore('bbn_i18n', [
            'exp' => $this->normlizeText($r),
            'lang' => $source_language,
          ])) {
            $id = $this->db->lastId();
          }
        }

        // create the property 'id_exp' for the string $r
        $res[$r]['id_exp'] = $id;

        // puts the string $r into the property 'original_exp' (I'll use only array_values at the end) *
        $res[$r]['original_exp'] = $r;

        // checks in 'bbn_i18n_exp' if the string $r already exist for this $source_lang
        if (!($id_exp = $this->db->selectOne('bbn_i18n_exp', 'id_exp', [
          'id_exp' => $id,
          'lang' => $source_language
        ]))) {
          // if the string $r is not in 'bbn_i18n_exp' inserts the string
          //  $done will be the number of strings found in the folder $to_explore that haven't been found in the table
          // 'bbn_i18n_exp' of db, so $done is the number of new strings inserted in in 'bbn_i18n_exp'
          $done += (int)$this->db->insertIgnore(
            'bbn_i18n_exp', [
            'id_exp' => $id,
            'lang' => $source_language,
            'expression' => $this->normlizeText($r)
            ]
          );
          //creates an array of new strings found in the folder;
          $news[] = $r;
        }

        // $languages is the array of languages existing in locale dir
        foreach ($languages as $lng){
          //  create a property indexed to the code of $lng containing the string $r from 'bbn_i18n_exp' in this $lng
          $res[$r][$lng] = (string)$this->db->selectOne('bbn_i18n_exp', 'expression', [
            'id_exp' => $id,
            'lang' => $lng
          ]);
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
  public function getTranslationsTableComplete($id_project, $id_option)
  {
    if (!empty($id_option)
        && ($o = $this->options->option($id_option))
        && ($parent = $this->options->parent($id_option))
        && defined($parent['code'])
    ) {
      // @var  $path_source_lang the property language of the id_option (the path)
      $path_source_lang = $this->options->getProp($id_option, 'language');

      // @var  $to_explore the path to explore
      $to_explore = $this->getPathToExplore($id_option);

      $locale_dir = $this->getLocaleDirPath($id_option);

      $languages = array_map(
        function ($a) {
          return X::basename($a);
        }, \bbn\File\Dir::getDirs($locale_dir)
      ) ?: [];

      $i       = 0;
      $res     = [];
      $project = new bbn\Appui\Project($this->db, $id_project);
      if (!empty($languages)) {
        $po_file = [];
        $success = false;
        foreach ($languages as $lng){
          // the path of po and mo files
          $idx = is_file($locale_dir.'/index.txt') ? file_get_contents($locale_dir.'/index.txt') : '';
          $po  = $locale_dir.'/'.$lng.'/LC_MESSAGES/'.$o['text'].$idx.'.po';
          $mo  = $locale_dir.'/'.$lng.'/LC_MESSAGES/'.$o['text'].$idx.'.mo';

          // if the file po exist takes its content
          if ($translations = $this->parsePoFile($po)) {
            foreach ($translations as $i => $t){
              // @var  $original the original expression
              $original = $t->getMsgId();

              $po_file[$i][$lng]['original'] = $original;

              // the translation of the string found in the po file
              $po_file[$i][$lng]['translations_po'] = $t->getMsgStr();

              // @var  $id takes the id of the original expression in db
              if ($id = $this->db->selectOne('bbn_i18n', 'id', [
                'exp' => $this->normlizeText($original),
                'lang' => $path_source_lang
              ])) {
                $po_file[$i][$lng]['translations_db'] = $this->db->selectOne('bbn_i18n_exp', 'expression', ['id_exp' => $id, 'lang' => $lng]);

                // the id of the string
                $po_file[$i][$lng]['id_exp'] = $id;

                // @var (array) takes $paths of files in which the string was found from the file po
                $paths = $t->getReference();

                // get the url to use it for the link to ide from the table
                foreach ($paths as $p){
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


  public function getTranslationsTable($id_project, $id_option)
  {
    if (!empty($id_option)
        && ($o = $this->options->option($id_option))
    ) {
      // @var  $path_source_lang the property language of the id_option (the path)
      //on the option the property is language, on the project i18n
      $path_source_lang = $this->options->getProp($id_option, 'language');

      // @var  $to_explore the path to explore
      $to_explore = $this->getPathToExplore($id_option);
      //the path of the locale dirs
      $locale_dir = $this->getLocaleDirPath($id_option);
      $languages  = array_map(
        function ($a) {
          return X::basename($a);
        }, bbn\File\Dir::getDirs($locale_dir)
      ) ?: [];

      $i       = 0;
      $res     = [];
      $project = new Project($this->db, $id_project);

      $errors = [];
      if (!empty($languages)) {
        $po_file = [];
        $success = false;
        foreach ($languages as $lng){
          // the path of po and mo files
          $idx = is_file($locale_dir.'/index.txt') ? file_get_contents($locale_dir.'/index.txt') : '';
          $po  = $locale_dir.'/'.$lng.'/LC_MESSAGES/'.$o['text'].$idx.'.po';
          $mo  = $locale_dir.'/'.$lng.'/LC_MESSAGES/'.$o['text'].$idx.'.mo';
          // if the file po exist takes its content
          if ($translations = $this->parsePoFile($po)) {
            foreach ($translations as $i => $t){
              // @var  $original the original expression
              $id = null;
              if ($original = stripslashes($t->getMsgId())) {
                $idx = \bbn\X::find($res, ['exp' => $original]);
                if ($idx !== null) {
                  $todo = false;
                  $row  =& $res[$idx];
                }
                else{
                  $todo = true;
                  $row  = [];
                }

                // the translation of the string found in the po file
                if (isset($row['id'])) {
                  $id = $row['id'];
                }

                // @var  $id takes the id of the original expression in db
                if (!isset($id)
                  && !($id = $this->db->selectOne('bbn_i18n', 'id', [
                    'exp' => $this->normlizeText($original),
                    'lang' => $path_source_lang
                  ]))
                ) {
                  if (!$this->db->insertIgnore('bbn_i18n', [
                    'exp' => $this->normlizeText($original),
                    'lang' => $path_source_lang
                  ])) {
                    throw new \Exception(
                      sprintf(
                        _("Impossible to insert the original string %s in the original language %s"),
                        $this->normlizeText($original),
                        $path_source_lang
                      )
                    );
                  }
                  else {
                    $id = $this->db->lastId();
                  }
                }

                if ($id) {
                  $row[$lng.'_po'] = stripslashes($t->getMsgStr());
                  $row[$lng.'_db'] = $this->db->selectOne('bbn_i18n_exp', 'expression', ['id_exp' => $id, 'lang' => $lng]);
                  if ($row[$lng.'_po'] && !$row[$lng.'_db']) {
                    if ((($row[$lng.'_db'] === false)
                        && $this->db->insert('bbn_i18n_exp', [
                          'expression' => $this->normlizeText($row[$lng.'_po']),
                          'id_exp' => $id,
                          'lang' => $lng
                        ]))
                      || $this->db->update('bbn_i18n_exp', [
                        'expression' => $this->normlizeText($row[$lng.'_po'])
                      ], [
                        'id_exp' => $id,
                        'lang' => $lng
                      ])
                    ) {
                      $row[$lng.'_db'] = $row[$lng.'_po'];
                    }
                    else{
                      throw new \Exception(
                        sprintf(
                          _("Impossible to insert or update the expression \"%s\" in %s"),
                          $row[$lng.'_po'],
                          $lng
                        )
                      );
                    }
                  }

                  if (empty($row[$lng.'_db'])) {
                    $row[$lng.'_db'] = '';
                    // die(var_dump($row[$lng.'_db']));
                  }

                  if ($todo) {
                    $row['id_exp'] = $id;
                    $row['paths']  = [];
                    $row['exp']    = $original;
                    // @var (array) takes $paths of files in which the string was found from the file po
                    $paths = $t->getReference();

                    // get the url to use it for the link to ide from the table
                    foreach ($paths as $p) {
                      $row['paths'][] = $project->realToUrl($p);
                    }

                    // the number of times the strings is found in the files of the path
                    $row['occurrence'] = count($row['paths']);
                    $res[]             = $row;
                  }
                }
                else{
                  die("Error 2");
                }
              }
            }

            $success = true;
          }
        }
      }

      return [

        'path_source_lang' => $path_source_lang,
        'path' => $o['text'],
        'success' => $success,
        'languages' => $languages,
        'total' => count(array_values($po_file)),
        'strings' => $res,
        'id_option' => $id_option,
        'errors' => $errors
      ];
    }

  }


  public function getOptionsTranslationsTable(string $lang)
  {
    if (($options = $this->options->findI18nByLang($lang))
      && ($languages = $this->getPrimariesLangs())
    ) {
      $res = [];
      $langText = X::getField($languages, ['code' => $lang], 'text');
      $languages = \array_map(fn($l) => $l['code'], $languages);
      foreach ($options as $opt) {
        $original = $this->normlizeText($opt['text']);
        if (!($id = $this->db->selectOne('bbn_i18n', 'id', [
          'exp' => $original,
          'lang' => $lang
        ]))) {
          if ($this->db->insert('bbn_i18n', [
            'exp' => $original,
            'lang' => $lang
          ])) {
            $id = $this->db->lastId();
          }
          else {
            throw new \Exception(X::_('Impossible to insert the original string %s in the original language %s', $original, $langText));
          }
        }
        if (!empty($id)) {
          if (!$this->db->selectOne('bbn_i18n_exp', 'id', [
            'id_exp' => $id,
            'lang' => $lang
          ])) {
            if (!$this->db->insert('bbn_i18n_exp', [
              'id_exp' => $id,
              'expression' => $original,
              'lang' => $lang
            ])) {
              throw new \Exception(X::_('Impossible to insert the string %s in the language %s', $original, $langText));
            }
          }
          $idx = X::find($res, ['id_exp' => $id]);
          if (\is_null($idx)) {
            $res[] = [
              'id_exp' => $id,
              'exp' => $original,
              $lang . '_db' => $original,
              $lang . '_po' => '',
              'occurrence' => 0,
              'paths' => []
            ];
            $idx = count($res) - 1;
          }
          $row =& $res[$idx];
          $row['occurence']++;
          foreach ($languages as $lng){
            $row[$lng . '_db'] = $this->db->selectOne('bbn_i18n_exp', 'expression', ['id_exp' => $id, 'lang' => $lng]) ?: '';
            $row[$lng . '_po'] = '';
          }
        }
      }

      return [
        'path_source_lang' => $lang,
        'path' => X::_('Options - %s', $langText),
        'languages' => $languages,
        'total' => count($res),
        'strings' => $res,
        'id_option' => $lang
      ];
    }

  }


  /**
   * Returns the path to explore relative to the given id_option
   * It only works if i18n class is constructed by giving the id_project
   *
   * @param string $id_option
   * @return String|null
   */
  public function getPathToExplore(string $id_option) :? String
  {
    if ($this->id_project) {
      /** @var bbn\Appui\Project */
      $project = new Project($this->db, $this->id_project);
      //the repository
      $rep = $project->repositoryById($id_option);

      //the root of this repositoryu
      $path = $project->getRootPath($rep);
      return $path;
    }

    return '';
  }


  /**
   * Returns the path of the locale dir of the given $id_option
   *
   * @param string $id_option
   * @return String
   */
  public function getLocaleDirPath(string $id_option) : String
  {
    if ($path = $this->getPathToExplore($id_option)) {
      if (substr($path, -1) !== '/') {
        $path .= '/';
      }
    }

    return $path.'locale';
  }


  /**
   * Returns the path of the file index.txt inside the locale folder
   *
   * @param string $id_option
   * @return void
   */
  public function getIndexPath(string $id_option)
  {
    return $this->getLocaleDirPath($id_option).'/index.txt';
  }


  /**
   * Inserts or updates an expression translation for the given language
   * @param string $idExp The original expression's ID
   * @param string $expression The translated expression
   * @param string $lang The translation language
   * @return bool
   */
  public function insertOrUpdateTranslation(string $idExp, string $expression, string $lang): bool
  {
    if ($id = $this->db->selectOne('bbn_i18n_exp', 'id', [
      'id_exp' => $idExp,
      'lang' => $lang
    ])) {
      if ($this->db->update('bbn_i18n_exp', ['expression' => $this->normlizeText($expression)], ['id' => $id])) {
        return true;
      }
    }
    /** INSERT in DB */
    else if ($this->db->insert('bbn_i18n_exp', [
      'expression' => $this->normlizeText($expression),
      'id_exp' => $idExp,
      'lang' => $lang
    ])) {
      return true;
    }
    return false;
  }


  /**
   * Deletes an expression translation for the give language
   * @param string $idExp The original expression's ID
   * @param string $lang The translation language
   * @return bool
   */
  public function deleteTranslation(string $idExp, string $lang): bool
  {
    return (bool)$this->db->delete('bbn_i18n_exp', [
      'id_exp' => $idExp,
      'lang' => $lang
    ]);
  }


  /**
   * Returns a normalized version of the given text
   * @param string $text
   * @return string
   */
  private function normlizeText(string $text): string
  {
    return \trim(\normalizer_normalize(stripslashes($text)));
  }


  private function parsePoFile(string $file): array
  {
    return \is_file($file) ? \Sepia\PoParser\Parser::parseFile($file)->getEntries() : [];
  }

}
