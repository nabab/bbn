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
use bbn\File\Dir;
use bbn\Str;

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
          $res = \array_unique(\array_merge($this->analyzePhp($file), $this->analyzeHtml($file)));
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
   * @deprecated
   * get the num of items['text'] in original language and num translations foreach lang in configured langs (for this project uses all primaries as configured langs)
   *
   * @return void
   */
  public function getNumOptions()
  {
    /** @var  $paths takes all options with i18n property setted*/
    $paths = $this->options->findI18n(null, true);
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
   * @deprecated
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
   * @deprecated
   * Gets the option with the property i18n setted and its items
   *
   * @return void
   */
  public function getOptions()
  {
    /** @var ( array) $paths get all options having i18n property setted and its items */
    $paths = $this->options->findI18n(null, true);
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

    $ret = [
      'locale_dirs' => $locale_dirs,
      'result' => $result,
      'success' => $success,
    ];
    $this->cacheSet($id_option, 'get_translations_widget', $ret);
    return $ret;
  }


  /**
   * Gets the widgets initial data for options
   *
   * @param string $id_project
   * @param string $id_option
   * @return void
   */
  public function getOptionsTranslationsWidget(string $idPath): array
  {
    $result = [];
    $languages = [];
    if ($localeDir = $this->getLocaleDirPath($idPath)) {
      $languages  = \array_map(fn($a) => X::basename($a), Dir::getDirs($localeDir) ?: []);
      foreach ($languages as $lang) {
        $count = 0;
        $countDB = 0;
        if (\is_file("$localeDir/$lang/options.json")) {
          $options = \json_decode(\file_get_contents("$localeDir/$lang/options.json"), true);
          foreach ($options as $exp => $opt) {
            if (!empty($opt['translation'])) {
              $count++;
            }
            if (($id = $this->db->selectOne('bbn_i18n', 'id', [
                'exp' => $this->normlizeText($exp),
                'lang' => $opt['language']
              ]))
              && $this->db->selectOne('bbn_i18n_exp', 'id_exp', [
                'id_exp' => $id,
                'lang' => $lang
              ])
            ) {
              $countDB++;
            }
          }
        }
        $result[$lang] = [
          'lang' => $lang,
          'num' => !empty($options) ? count($options) : 0,
          'num_translations' => $count,
          'num_translations_db' => $countDB
        ];
      }
    }
    $ret = [
      'locale_dirs' => $languages,
      'result' => $result
    ];
    $this->cacheSet($idPath, 'get_options_translations_widget', $ret);
    return $ret;
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
          $idx = $this->getIndexValue($id_option) ?: 1;
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
   * Get an expression translation for the given language
   * @param string $expression The expression to be translated
   * @param string $originalLang The original expression's language
   * @param string $transLang the language of the translation
   * @return string|null
   */
  public function getTranslation(string $expression, string $originalLang, string $transLang): ?string
  {
    return $this->db->selectOne([
      'table' => 'bbn_i18n',
      'fields' => ['bbn_i18n_exp.expression'],
      'join' => [[
        'table' => 'bbn_i18n_exp',
        'on' => [
          'conditions' => [[
            'field' => 'bbn_i18n.id',
            'exp' => 'bbn_i18n_exp.id_exp'
          ], [
            'field' => 'bbn_i18n_exp.lang',
            'value' => $transLang
          ]]
        ]
      ]],
      'where' => [
        'bbn_i18n.exp' => $this->normlizeText($expression),
        'bbn_i18n.lang' => $originalLang
      ]
    ]);
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
          $idx = $this->getIndexValue($id_option) ?: 1;
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


  public function getTranslationsTable($id_project, $id_option): array
  {
    $ret = [];
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
        }, Dir::getDirs($locale_dir)
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
          $idx = $this->getIndexValue($id_option) ?: 1;
          $po  = $locale_dir.'/'.$lng.'/LC_MESSAGES/'.$o['text'].$idx.'.po';
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

      $ret = [
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
    $this->cacheSet($id_option, 'get_translations_table', $ret);
    return $ret;
  }


  public function getOptionsTranslationsTable(string $idPath): array
  {
    $ret = [];
    if ($localeDir = $this->getLocaleDirPath($idPath)) {
      $languages  = \array_map(fn($a) => X::basename($a), Dir::getDirs($localeDir) ?: []);
      $rows = [];
      $primaryLanguages = $this->getPrimariesLangs();
      foreach ($languages as $lang) {
        if (\is_file("$localeDir/$lang/options.json")) {
          $options = \json_decode(\file_get_contents("$localeDir/$lang/options.json"), true);
          foreach ($options as $exp => $opt) {
            $idx = X::find($rows, ['exp' => $exp]);
            if (\is_null($idx)) {
              if (!($idExp = $this->db->selectOne('bbn_i18n', 'id', [
                'exp' => $this->normlizeText($exp),
                'lang' => $opt['language']
              ]))) {
                if ($this->db->insert('bbn_i18n', [
                  'exp' => $this->normlizeText($exp),
                  'lang' => $opt['language']
                ])) {
                  $idExp = $this->db->lastId();
                }
                else {
                  $langText = X::getField($primaryLanguages, ['code' => $lang], 'text');
                  throw new \Exception(X::_('Impossible to insert the original string %s in the original language %s', $this->normlizeText($exp), $langText));
                }
              }
              if (!empty($idExp)) {
                if (!$this->db->selectOne('bbn_i18n_exp', 'id', [
                  'id_exp' => $idExp,
                  'lang' => $opt['language']
                ])) {
                  $this->insertOrUpdateTranslation($idExp, $exp, $opt['language']);
                }
                $r = [
                  'id_exp' => $idExp,
                  'exp' => $this->normlizeText($exp),
                  $opt['language'] . '_po' => $exp,
                  $opt['language'] . '_db' => $this->db->selectOne('bbn_i18n_exp', 'expression', [
                    'id_exp' => $idExp,
                    'lang' => $opt['language']
                  ]) ?: '',
                  'occurrence' => count($opt['paths']),
                  'paths' => $opt['paths']
                ];
                if ($lang !== $opt['language']) {
                  $r[$lang . '_po'] = $opt['translation'];
                  $r[$lang . '_db'] = '';
                }
                $rows[] = $r;
              }
            }
            else {
              $rows[$idx][$lang . '_po'] = $opt['translation'];
              $rows[$idx][$lang . '_db'] = $this->db->selectOne('bbn_i18n_exp', 'expression', [
                'id_exp' => $rows[$idx]['id_exp'],
                'lang' => $lang
              ]) ?: '';
            }
          }
        }
      }
      $ret = [
        //'path_source_lang' => $lang,
        'path' => ($o = $this->options->text($idPath)),
        'languages' => $languages,
        'total' => count($rows),
        'strings' => $rows,
        'id_option' => $idPath
      ];
      $this->cacheSet($idPath, 'get_options_translations_table', $ret);
    }
    return $ret;
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
   * @return string
   */
  public function getIndexPath(string $id_option): string
  {
    return $this->getLocaleDirPath($id_option).'/index.txt';
  }


  /**
   * Returns the version number contained in the index.txt file inside the folder locale or 0 if the file doesn't exists
   * @param string $idPath
   * @return int
   */
  public function getIndexValue(string $idPath): int
  {
    $indexPath = $this->getIndexPath($idPath);
    return \is_file($indexPath) ? (int)\file_get_contents($indexPath) : 0;
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


  public function generateFiles(string $idPath, array $languages = [], string $mode = 'files')
  {
    if (!\in_array($mode, ['files', 'options'], true)) {
      throw new \Exception(X::_("No valid mode %s", $mode));
    }
    // The position of locale directory
    $localeDir = $this->getLocaleDirPath($idPath);
    /** @var (array) $languages based on locale dirs found in the path */
    $currentLangs = array_map('basename', Dir::getDirs($localeDir) ?: []);
    if (empty($languages)) {
      $languages = $currentLangs;
    }
    if (empty($languages)) {
      $languages = \array_map(fn($o) => $o['code'], $this->getPrimariesLangs());
    }
    $fromAction = [];
    if (!empty($languages)) {
      if ($toRemove = \array_diff($currentLangs, $languages)) {
        foreach ($toRemove as $d) {
          \array_splice($currentLangs, \array_search($d, $currentLangs, true), 1);
          switch ($mode) {
            case 'files':
              Dir::delete("$localeDir/$d/LC_MESSAGES");
              Dir::delete("$localeDir/$d/$d.json");
              break;
            case 'options':
              Dir::delete("$localeDir/$d/options.json");
              break;
          }
          if (!Dir::getFiles("$localeDir/$d")) {
            Dir::delete("$localeDir/$d");
          }
        }
      }
      if ($toCreate = \array_diff($languages, $currentLangs)) {
        foreach ($toCreate as $d) {
          $languages[] = $d;
        }
      }
      Dir::createPath($localeDir);
      switch ($mode) {
        case 'files':
          $fromAction = $this->generateFilesPo($idPath, $languages);
          $this->generateFilesMo($idPath, $languages);
          break;
        case 'options':
          $fromAction = $this->generateFilesOptions($idPath, $languages);
          break;
      }
    }
    return \array_merge([
      'locale' => $localeDir,
      'languages' => $languages,
      'new_dir' => $toCreate,
      'ex_dir' => $toRemove,
      'path' => $this->getPathToExplore($idPath)
    ], $fromAction);
  }


  private function generateFilesPo(string $idPath, array $languages): array
  {
    /** @var string $domain The domain on which will be bound gettext */
    $domain = $this->options->text($idPath);
    // The position of locale directory
    $localeDir = $this->getLocaleDirPath($idPath);
    /** @var string $indexPath */
    $indexPath = $this->getIndexPath($idPath);
    // The version number contained in the txt file inside the folder locale
    $versionNumber = $this->getIndexValue($idPath);
    \file_put_contents($indexPath, ++$versionNumber);
    $domain .= $versionNumber;
    $parent = $this->options->parent($idPath);
    /** @var bool $json Will be true if some translations are put into a JSON file */
    $json = false;
    /** @var array $toJSON */
    $toJSON = [];
    /** @var array $data Takes all strings found in the files of this path */
    $data = $this->getTranslationsStrings($idPath, $this->getLanguage($idPath), $languages);
    if (!empty($data['res'])) {
      \clearstatcache();
      foreach ($languages as $lang) {
        /** @var string $dir The path of locale dir for this id_option foreach lang */
        $dir = "$localeDir/$lang/LC_MESSAGES";
        /** creates the path of the dirs */
        Dir::createPath($dir);
        /** @var  $po & $mo files path */
        $files = Dir::getFiles($dir);
        foreach ($files as $f) {
          $ext = Str::fileExt($f);
          if (($ext === 'po') || ($ext === 'mo')) {
            \unlink($f);
          }
        }
        // the new files
        $poFile = "$dir/$domain.po";
        //create the file at the given path
        \fopen($poFile, 'x');
        //instantiate the parser
        $fileHandler  = new \Sepia\PoParser\SourceHandler\FileSystem($poFile);
        $poParser     = new \Sepia\PoParser\Parser($fileHandler);
        $catalog      = \Sepia\PoParser\Parser::parseFile($poFile);
        $compiler     = new \Sepia\PoParser\PoCompiler();
        $headersClass = new \Sepia\PoParser\Catalog\Header();
        if ($catalog->getHeaders()) {
          //headers for new po file
          $headers = [
            "Project-Id-Version: 1",
            "Report-Msgid-Bugs-To: info@bbn.so",
            "last-Translator: BBN Solutions <support@bbn.solutions>",
            "Language-Team: ".strtoupper($lang).' <'.strtoupper($lang).'@li.org>',
            "MIME-Version: 1.0",
            "Content-Type: text/plain; charset=UTF-8",
            "Content-Transfer-Encoding: 8bit",
            "POT-Creation-Date: ".date('Y-m-d H:iO'),
            "POT-Revision-Date: ".date('Y-m-d H:iO'),
            "Language: ".$lang,
            "X-Domain: ".$domain,
            "Plural-Forms: nplurals=2; plural=n != 1;"
          ];
          //set the headers on the Catalog object
          $headersClass->setHeaders($headers);
          $catalog->addHeaders($headersClass);
        }
        $constroot = 'BBN_'.strtoupper($parent['code']).'_PATH';
        if (!defined($constroot)) {
          X::log($this->options->option($idPath));
          throw new \Exception("Impossible to find the root for option, see Misc log");
        }
        $root = constant($constroot);
        foreach ($data['res'] as $index => $r) {
          if (!$catalog->getEntry($r['original_exp'])) {
            //prepare the new entry for the Catalog
            $entry = new \Sepia\PoParser\Catalog\Entry($r['original_exp'], $r[$lang]);
            // set the reference for the entry
            if (!empty($r['path'])) {
              $entry->setReference($r['path']);
              foreach($r['path'] as $path){
                $name = '';
                $ext = Str::fileExt($path);
                if (($ext === 'js')
                  || ($ext === 'php')
                  || ($ext === 'html')
                ) {
                  $tmp = \substr($path, \strlen($root), -(\strlen($ext) + 1));
                  if (\strpos($tmp, 'components') === 0) {
                    $name = \dirname($tmp);
                  }
                  elseif (\strpos($tmp, 'mvc') === 0) {
                    if (\strpos($tmp, 'js/') === 4) {
                      $name = \preg_replace('/js\//', '', $tmp, 1);
                    }
                    else if (\strpos($tmp, 'html/') === 4) {
                      $name = \preg_replace('/html\//', '', $tmp, 1);
                    }
                  }
                  elseif ((\strpos($tmp, 'plugins') === 0) && ($root === BBN_APP_PATH)) {
                    continue;
                  }
                  elseif (\strpos($tmp, 'bbn/') === 0) {
                    $optCode = $this->options->code($idPath);
                    $tmp  = \str_replace($optCode.'/', '', \substr($tmp, 4));
                    if (\strpos($tmp, 'components') === 4) {
                      $final = \str_replace(\substr($tmp, 0,4), '', $tmp);
                      $name = \dirname($final);
                    }
                    elseif (\strpos($tmp, 'mvc') === 4) {
                      if ((\strpos($tmp, 'js/') !== 8)
                        && (\strpos($tmp, 'html/') !== 8)
                      ) {
                        continue;
                      }
                      $final = \str_replace(substr($tmp, 0, 4), '', $tmp);
                      $name  = \preg_replace(['/js\//', '/html\//'], '', $final, 1);
                    }
                  }
                  if (empty($toJSON[$lang][$name])) {
                    $toJSON[$lang][$name] = [];
                  }
                  //array of all js files found in po file
                  $toJSON[$lang][$name][$data['res'][$index]['original_exp']] = $data['res'][$index][$lang];
                }
              }
            }
            //add the prepared entry to the catalog
            $catalog->addEntry($entry);
          }
        }
        //compile the catalog
        $file = $compiler->compile($catalog);
        //save the catalog in the file
        $fileHandler->save($file);
        \clearstatcache();
        if (!empty($toJSON[$lang])) {
          $file_name = "$localeDir/$lang/$lang.json";
          Dir::createPath(dirname($file_name));
          // put the content of the array js_files in a json file
          $json = (boolean)\file_put_contents($file_name, \json_encode($toJSON[$lang], JSON_PRETTY_PRINT));
        }
      }
      \clearstatcache();
      $this->getTranslationsTable($this->id_project, $idPath);
      $this->getTranslationsWidget($this->id_project, $idPath);
    }
    return [
      'json' => $json,
      'no_strings' => empty($data['res'])
    ];
  }


  private function generateFilesOptions(string $idPath, array $languages): array
  {
    if (($localeDir = $this->getLocaleDirPath($idPath))
      && !empty($languages)
      && ($code = $this->options->code($idPath))
    ) {
      $toJSON = [];
      $options = [];
      if (($parent = $this->options->parent($idPath))
        && ($parentCode = $this->options->code($parent['id']))
      ) {
        if (($parentCode === 'lib')
          && (\strpos($code, 'appui-') === 0)
        ) {
          if ($idOpt = $this->options->fromCode(\preg_replace('/appui-/', '', $code, 1), 'appui')) {
            $options = $this->options->findI18n($idOpt);
          }
        }
      }
      if (!empty($options)) {
        foreach ($options as $opt) {
          $codePath = $this->options->getCodePath($opt['id']);
          if ($codePath) {
            $codePath = \implode('/', \array_reverse($codePath));
            foreach ($languages as $lang) {
              if (!isset($toJSON[$lang])) {
                $toJSON[$lang] = [];
              }
              $t = $this->normlizeText($opt['text']);
              if (!isset($toJSON[$lang][$t])) {
                $toJSON[$lang][$t] = [
                  'language' => $opt['language'],
                  'paths' => [$codePath],
                  'original' => $opt['text'],
                  'translation' => $this->getTranslation($t, $opt['language'], $lang) ?: ''
                ];
              }
              else if (!\in_array($codePath, $toJSON[$lang][$t]['paths'])) {
                $toJSON[$lang][$t]['paths'][] = $codePath;
              }
            }
          }
        }
      }
      foreach ($toJSON as $lang => $str) {
        Dir::createPath("$localeDir/$lang");
        \file_put_contents("$localeDir/$lang/options.json", \json_encode($str, JSON_PRETTY_PRINT));
      }
      $this->getOptionsTranslationsTable($idPath);
      $this->getOptionsTranslationsWidget($idPath);
    }
    return [
      'json' => !empty($toJSON),
      'no_strings' => empty($options)
    ];
  }


  private function generateFilesMo(string $idPath, array $languages): bool
  {
    if (($domain = $this->options->text($idPath))
      && ($localeDir = $this->getLocaleDirPath($idPath))
      && ($indexPath = $this->getIndexPath($idPath))
      && !empty($languages)
    ) {
      $versionNumber = $this->getIndexValue($indexPath) ?: 1;
      $success = true;
      foreach ($languages as $lang) {
        $file = "$localeDir/$lang/LC_MESSAGES/$domain$versionNumber.";
        if (\is_file($file.'mo')) {
          \unlink($file.'mo');
        }
        if (\is_file($file.'po')
          && ($translations = \Gettext\Translations::fromPoFile($file.'po'))
          && !$translations->toMoFile($file.'mo')
        ) {
          $success = false;
        }
      }
      return $success;
    }
    return false;
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
