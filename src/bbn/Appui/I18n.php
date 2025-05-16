<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 14/12/2017
 * Time: 17:34
 */

namespace bbn\Appui;

use Exception;
use bbn\User;
use bbn\Db;
use bbn\Str;
use bbn\X;
use bbn\File\Dir;
use bbn\Appui\Option;
use bbn\Appui\Project;
use bbn\Models\Tts\Optional;
use bbn\Models\Tts\DbActions;
use bbn\Models\Cls\Cache as cacheCls;
use Gettext\Translations;
use Gettext\Scanner\PhpScanner;
use Gettext\Scanner\JsScanner;
use Gettext\Loader\PoLoader;
use Gettext\Generator\MoGenerator;
use Sepia\PoParser\Parser;
use Sepia\PoParser\SourceHandler\FileSystem;
use Sepia\PoParser\PoCompiler;
use Sepia\PoParser\Catalog\Header;
use Sepia\PoParser\Catalog\Entry;

class I18n extends cacheCls
{
  use Optional;
  use DbActions;

  protected static $extensions = ['js', 'json', 'php', 'html'];

  protected $parser;

  protected $translations = [];

  protected $user;

  protected $options;

  protected $id_project;

  protected $poLoader;

  protected $moGenerator;

  protected static $hashAlgo = 'sha512';

  /** @var array $default_class_cfg */
  protected static $default_class_cfg = [
    'table' => 'bbn_i18n',
    'tables' => [
      'i18n' => 'bbn_i18n',
      'i18n_exp' => 'bbn_i18n_exp'
    ],
    'arch' => [
      'i18n' => [
        'id' => 'id',
        'exp' => 'exp',
        'lang' => 'lang',
        'hash' => 'hash'
      ],
      'i18n_exp' => [
        'id' => 'id',
        'id_exp' => 'id_exp',
        'lang' => 'lang',
        'expression' => 'expression'
      ]
    ]
  ];


  /**
   * Initialize the class I18n
   *
   * @param db
   */
  public function __construct(Db $db, string|null $code = null)
  {
    parent::__construct($db);
    $this->initClassCfg();
    $this->user    = User::getInstance();
    $this->options = Option::getInstance();
    if (empty($code)) {
      if (\defined('BBN_APP_NAME')) {
        $code = CONSTANT('BBN_APP_NAME');
      }
      else {
        throw new Exception(X::_("The project's ID/Code is mandatory"));
      }
    }

    $this->parser  = Translations::create($code);
    $this->poLoader = new PoLoader();
    $this->moGenerator = new MoGenerator();
    $this->options->preventI18n();
    $this->id_project = Str::isUid($code) ? $code : $this->options->fromCode($code, 'list', 'project', 'appui');
    $this->options->preventI18n(false);
    if (empty($this->id_project)) {
      throw new Exception(X::_("Project's ID not found for code %s", $code));
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
    $domain = $this->parser->getDomain();
    $parser = Translations::create($domain);
    $scanner = new PhpScanner($parser);
    $scanner->setDefaultDomain($domain);
    $scanner->setFunctions([
      '_' => 'gettext'
    ]);
    try {
      $scanner->scanFile($file);
    }
    catch (Exception $e) {
      X::log([
        'method' => 'analyzePhp',
        'file' => $file,
        'error' => $e->getMessage(),
      ], 'i18n');
    }

    foreach ($parser->getIterator() as $tr){
      $res[] = $tr->getOriginal();
    }

    $res = array_unique($res);
    if (!empty($res)) {
      $this->parser = $this->parser->mergeWith($parser);
    }

    return $res;
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
    $domain = $this->parser->getDomain();
    $parser = Translations::create($domain);
    $scanner = new JsScanner($parser);
    $scanner->setDefaultDomain($domain);
    $scanner->setFunctions([
      '_' => 'gettext',
      'bbn._' => 'gettext'
    ]);
    $code = file_get_contents($file);
    try {
      $scanner->scanFile($file);
    }
    catch (Exception $e) {
      X::log([
        'method' => 'analyzeJs',
        'file' => $file,
        'error' => $e->getMessage(),
      ], 'i18n');
    }

    if (preg_match_all('/`([^`]*)`/', $code, $matches)) {
      foreach ($matches[0] as $c){
        try {
          $scanner->scanString($c, $file);
        }
        catch (Exception $e) {
          X::log([
            'method' => 'analyzeJs',
            'file' => $file,
            'error' => $e->getMessage(),
          ], 'i18n');
        }
      }
    }

    foreach ($parser->getIterator() as $tr){
      $res[] = $tr->getOriginal();
    }

    $res = array_unique($res);
    if (!empty($res)) {
      $this->parser = $this->parser->mergeWith($parser);
    }

    return $res;
  }


  public function analyzeJson(string $file): array
  {
    $res = [];
    $domain = $this->parser->getDomain();
    $parser = Translations::create($domain);
    $scanner = new JsScanner($parser);
    $scanner->setDefaultDomain($domain);
    $scanner->setFunctions([
      '_' => 'gettext',
      'bbn._' => 'gettext'
    ]);
    try {
      $scanner->scanFile($file);
    }
    catch (Exception $e) {
      X::log([
        'method' => 'analyzeJson',
        'file' => $file,
        'error' => $e->getMessage(),
      ], 'i18n');
    }

    foreach ($parser->getIterator() as $tr){
      $res[] = $tr->getOriginal();
    }

    $res = array_unique($res);
    if (!empty($res)) {
      $this->parser = $this->parser->mergeWith($parser);
    }

    return $res;
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
    $code = file_get_contents($file);
    if (!empty($code)) {
      $code = trim($code);
      if ((Str::fileExt($file) === 'php')
        && str_starts_with($code, '<?php')
      ) {
        return $res;
      }

      $domain = $this->parser->getDomain();
      $parser = Translations::create($domain);
      $scanner = new PhpScanner($parser);
      $scanner->setDefaultDomain($domain);
      $scanner->setFunctions([
        '_' => 'gettext',
        'bbn._' => 'gettext'
      ]);
      try {
        //$scanner->scanString('<template>'.$code.'</template>', $file);
        $scanner->scanString($code, $file);
      }
      catch (Exception $e) {
        X::log([
          'method' => 'analyzeHtml',
          'file' => $file,
          'error' => $e->getMessage(),
        ], 'i18n');
      }

      foreach ($parser->getIterator() as $tr){
        $res[] = $tr->getOriginal();
      }

      $res = array_unique($res);
      if (!empty($res)) {
        $this->parser = $this->parser->mergeWith($parser);
      }
    }

    return $res;
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
    $ext = Str::fileExt($file);
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
      $files = $deep ? Dir::scan($folder, 'file') : Dir::getFiles($folder);
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
    foreach ($this->parser->getIterator() as $tr){
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
  public function getPrimariesLangs(bool $onlyCodes = false): array
  {
    if ($languages = $this->options->fullOptions('languages', 'i18n', 'appui')) {
      $res = array_values(
        array_filter(
          $languages, function ($v) {
            return !empty($v['primary']);
          }
        )
      );
      return $onlyCodes ? \array_map(fn($l) => $l['code'], $res) : $res;
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
    /** @var array $paths takes all options with i18n property setted*/
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
          if (($id = $this->getId($item['text'], $paths[$p]['language']))
            && $this->hasTranslation($id, $lang)
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
    /** @var array $paths takes all options with i18n property setted*/
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
        foreach ($items as $item){
          if (($id = $this->getId($item['text'], $paths[$p]['language']))
            && $this->hasTranslation($id, $lang)
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
    /** @var array $paths get all options having i18n property setted and its items */
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
        if ($exp = $this->get($paths[$p]['items'][$i]['text'], $paths[$p]['language'])) {
          if ($translated = $this->getTranslations($exp['id'])) {
            /** @var array $languages the array of languages found in db for the options*/
            $languages      = [];
            $translated_exp = '';
            foreach ($translated as $trans){
              if (!in_array($trans['lang'], $translated)) {
                $languages[] = $trans['lang'];
              }

              $translated_exp = $trans['expression'];
            }

            if (!empty($languages)) {
              foreach ($languages as $lang){
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
        else if ($id = $this->insert($paths[$p]['items'][$i]['text'], $paths[$p]['language'])) {
          $this->insertTranslation($id, $paths[$p]['language'], $paths[$p]['items'][$i]['text']);
          $res[$p]['strings'][] = [
            $paths[$p]['language'] => [
              'id_exp' => $id,
              'exp' => $paths[$p]['items'][$i]['text'],
              'translation_db' => $paths[$p]['items'][$i]['text']
            ]
          ];
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
   * @param string $idOption
   * @return array
   */
  public function getTranslationsWidget(string $idOption): array
  {
    $result = [];
    $localeDirs = [];
    if (($o = $this->options->option($idOption))
      && !empty($o['language'])
    ) {
      // @var $localeDir the path to locale dir
      $localeDir = $this->getLocaleDirPath($idOption);
      //the txt file in the locale folder
      $index = $this->getIndexPath($idOption);
      //the text of the option . the number written in the $index file
      $domain = $o['text'].(is_file($index) ? file_get_contents($index) : '');
      // @var array $languages dirs in locale folder
      $languages = [];
      if (is_dir($localeDir)) {
        // @var $dirs scans dirs existing in locale folder for this path
        $dirs = Dir::getDirs($localeDir) ?: [];
        if (!empty($dirs)) {
          foreach ($dirs as $l){
            $languages[] = X::basename($l);
          }
        }
      }

      if (!empty($languages)) {
        foreach ($languages as $lng){
          // the root to file po & mo
          $po = $localeDir.'/'.$lng.'/LC_MESSAGES/'.$domain.'.po';
          // if a file po already exists takes its content
          if (is_file($po)) {
            $localeDirs[] = $lng;
            $numTranslations = 0;
            if ($translations = $this->parsePoFile($po)) {
              foreach ($translations as $tr) {
                if ($tr->getMsgStr()) {
                  $numTranslations++;
                }
              }

              $result[$lng] = [
                'num' => count($translations),
                'num_translations' => $numTranslations,
                'lang' => $lng,
                'num_translations_db' => $this->countTranslationsDb($idOption) ? $this->countTranslationsDb($idOption)[$lng] : 0
              ];
            }
          }
          else {
            $countTranslations = 0;
            if ($ctd = $this->countTranslationsDb($idOption)) {
              $countTranslations = $ctd[$lng] ?? 0;
            }

            $result[$lng] = [
              'num' => 0,
              'num_translations' => 0,
              'lang' => $lng,
              'num_translations_db' => $countTranslations
            ];
          }
        }
      }
    }

    $ret = [
      'locale_dirs' => $localeDirs,
      'result' => $result
    ];
    $this->cacheSet($idOption, 'get_translations_widget', $ret);
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
      $languages  = $this->getPrimariesLangs(true);
      foreach ($languages as $lang) {
        $count = 0;
        $countDB = 0;
        if (\is_file("$localeDir/$lang/options.json")) {
          $options = \json_decode(\file_get_contents("$localeDir/$lang/options.json"), true);
          foreach ($options as $exp => $opt) {
            if (!empty($opt['translation'])) {
              $count++;
            }
            if (($id = $this->getId($exp, $opt['language']))
              && $this->hasTranslation($id, $lang)
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
      // @var  $locale_dir locale dir in the path
      $locale_dir = $this->getLocaleDirPath($id_option);
      $dirs       = Dir::getDirs($locale_dir) ?: [];
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
   * @param string $idOption
   * @return array
   */
  public function countTranslationsDb(string $idOption): array
  {
    $count = [];
    $po = $this->getPoFiles($idOption);
    $sourceLanguage = $this->getLanguage($idOption);
    if (!empty($po)) {
      foreach ($po as $lang => $file) {
        $fromPo = $this->parsePoFile($file);
        $count[$lang] = 0;
        foreach ($fromPo as $o) {
          if (($exp = $o->getMsgId())
            && ($id = $this->getIdByHash($this->hashText($exp), $sourceLanguage))
            && $this->hasTranslation($id, $lang)
          ) {
            $count[$lang]++;
          }
        }
      }
    }

    return $count;
  }


  public function get(string $idOrExp, ?string $lang = null): ?array
  {
    if (Str::isUid($idOrExp)) {
      return $this->db->rselect($this->class_table, [], [
        $this->fields['id'] => $idOrExp
      ]);
    }

    if (!empty($lang)) {
      return $this->db->rselect($this->class_table, [], [
        $this->fields['hash'] => $this->hashText($idOrExp),
        $this->fields['lang'] => $lang
      ]);
    }

    return null;
  }


  public function getId(string $exp, string $lang): ?string
  {
    return $this->getIdByHash($this->hashText($exp), $lang);
  }


  public function getIdByHash(string $hash, string $lang): ?string
  {
    return $this->db->selectOne($this->class_table, $this->fields['id'], [
      $this->fields['hash'] => $hash,
      $this->fields['lang'] => $lang
    ]);
  }


  public function hasTranslation(string $idExp, string $lang): bool
  {
    $clsCfg = $this->getClassCfg();
    return (bool)$this->db->count($clsCfg['tables']['i18n_exp'], [
      $clsCfg['arch']['i18n_exp']['id_exp'] => $idExp,
      $clsCfg['arch']['i18n_exp']['lang'] => $lang
    ]);
  }


  /**
   * Get an expression translation for the given language
   * @param string $expression The expression to be translated
   * @param string $originalLang The original expression's language
   * @param string $transLang the language of the translation
   * @return string|null
   */
  public function getTranslation(string $idExpOrExp, ?string $originalLang = null, string $transLang): ?string
  {
    $clsCfg = $this->getClassCfg();
    if (Str::isUid($idExpOrExp)) {
      return $this->db->selectOne([
        'table' => $clsCfg['tables']['i18n_exp'],
        'fields' => [$clsCfg['arch']['i18n_exp']['expression']],
        'where' => [
          $clsCfg['arch']['i18n_exp']['id_exp'] => $idExpOrExp,
          $clsCfg['arch']['i18n_exp']['lang'] => $transLang
        ]
      ]) ?: null;
    }

    return $this->db->selectOne([
      'table' => $this->class_table,
      'fields' => [$this->db->cfn($clsCfg['arch']['i18n_exp']['expression'], $clsCfg['tables']['i18n_exp'])],
      'join' => [[
        'table' => $clsCfg['tables']['i18n_exp'],
        'on' => [
          'conditions' => [[
            'field' => $this->db->cfn($this->fields['id'], $this->class_table),
            'exp' => $this->db->cfn($clsCfg['arch']['i18n_exp']['id_exp'], $clsCfg['tables']['i18n_exp'])
          ], [
            'field' => $this->db->cfn($clsCfg['arch']['i18n_exp']['lang'], $clsCfg['tables']['i18n_exp']),
            'value' => $transLang
          ]]
        ]
      ]],
      'where' => [
        $this->db->cfn($this->fields['hash'], $this->class_table) => $this->hashText($idExpOrExp),
        $this->db->cfn($this->fields['lang'], $this->class_table) => $originalLang
      ]
    ]) ?: null;
  }


  public function getTranslationId(string $idExp, string $lang): ?string
  {
    $clsCfg = $this->getClassCfg();
    return $this->db->selectOne($clsCfg['tables']['i18n_exp'], $clsCfg['arch']['i18n_exp']['id'], [
      $clsCfg['arch']['i18n_exp']['id_exp'] => $idExp,
      $clsCfg['arch']['i18n_exp']['lang'] => $lang
    ]);
  }


  public function getTranslations(string $idExp): ?array
  {
    $clsCfg = $this->getClassCfg();
    return $this->db->rselectAll($clsCfg['tables']['i18n_exp'], [], [
      $clsCfg['arch']['i18n_exp']['id_exp'] => $idExp
    ]);
  }


  public function getNumTranslations(string $idExp, ?string $originalLocale = ''): int
  {
    if (!Str::isUid($idExp) && !empty($originalLocale)) {
      $idExp = $this->getId($idExp, $originalLocale);
    }

    if (Str::isUid($idExp)) {
      $clsCfg = $this->getClassCfg();
      return $this->db->count([
        'table' => $clsCfg['tables']['i18n_exp'],
        'fields' => [],
        'where' => [
          'conditions' => [[
            'field' => $clsCfg['arch']['i18n_exp']['id_exp'],
            'value' => $idExp
          ], [
            'field' => $clsCfg['arch']['i18n_exp']['expression'],
            'operator' => 'isnotnull'
          ]]
        ]
      ]);
    }

    return 0;
  }


  public function insert(string $exp, string $lang): ?string
  {
    if ($this->db->insert($this->class_table, [
      $this->fields['exp'] => $this->normlizeText($exp),
      $this->fields['lang'] => $lang
    ])) {
      return $this->db->lastId();
    }

    return null;
  }


  public function insertTranslation(string $idExp, string $lang, string $translation): int
  {
    $clsCfg = $this->getClassCfg();
    return (int)$this->db->insertIgnore($clsCfg['tables']['i18n_exp'], [
      $clsCfg['arch']['i18n_exp']['id_exp'] => $idExp,
      $clsCfg['arch']['i18n_exp']['lang'] => $lang,
      $clsCfg['arch']['i18n_exp']['expression'] => $this->normlizeText($translation)
    ]);
  }


  public function updateTranslation(string $idExp, string $lang, string $translation): int
  {
    $clsCfg = $this->getClassCfg();
    return (int)$this->db->update($clsCfg['tables']['i18n_exp'], [
      $clsCfg['arch']['i18n_exp']['expression'] => $this->normlizeText($translation)
    ], [
      $clsCfg['arch']['i18n_exp']['id_exp'] => $idExp,
      $clsCfg['arch']['i18n_exp']['lang'] => $lang
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
      if ($to_explore_dirs = Dir::getDirs($to_explore)) {
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
          }, Dir::getDirs($locale_dir)
        ) ?: [];
      }

      if (!empty($current_dirs)) {
        foreach ($current_dirs as $c){
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
        if (!($id = $this->getId($r, $source_language))) {
          // if the string $r is not in 'bbn_i18n' inserts the string
          $id = $this->insert($r, $source_language);
        }

        // create the property 'id_exp' for the string $r
        $res[$r]['id_exp'] = $id;

        // puts the string $r into the property 'original_exp' (I'll use only array_values at the end) *
        $res[$r]['original_exp'] = $r;

        // checks in 'bbn_i18n_exp' if the string $r already exist for this $source_lang
        if (!$this->hasTranslation($id, $source_language)) {
          // if the string $r is not in 'bbn_i18n_exp' inserts the string
          //  $done will be the number of strings found in the folder $to_explore that haven't been found in the table
          // 'bbn_i18n_exp' of db, so $done is the number of new strings inserted in in 'bbn_i18n_exp'
          $done += $this->insertTranslation($id, $source_language, $r);
          //creates an array of new strings found in the folder;
          $news[] = $r;
        }

        // $languages is the array of languages existing in locale dir
        foreach ($languages as $lng){
          //  create a property indexed to the code of $lng containing the string $r from 'bbn_i18n_exp' in this $lng
          $res[$r][$lng] = (string)$this->getTranslation($id, null, $lng) ?: '';
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
      // @var string $path_source_lang the property language of the id_option (the path)
      $path_source_lang = $this->options->getProp($id_option, 'language');

      $locale_dir = $this->getLocaleDirPath($id_option);

      $languages = array_map(
        function ($a) {
          return X::basename($a);
        }, Dir::getDirs($locale_dir)
      ) ?: [];

      $i       = 0;
      $res     = [];
      $project = new Project($this->db, $id_project);
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
              if ($id = $this->getId($original ,$path_source_lang)) {
                $po_file[$i][$lng]['translations_db'] = $this->getTranslation($id, null, $lng);

                // the id of the string
                $po_file[$i][$lng]['id_exp'] = $id;

                // @var (array) takes $paths of files in which the string was found from the file po
                $paths = $t->getReference();

                // get the url to use it for the link to ide from the table
                foreach ($paths as $p){
                  $po_file[$i][$lng]['paths'][] = $project->realToUrl($p);
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
      // @var string $path_source_lang the property language of the id_option (the path) on the option
      $path_source_lang = $this->options->getProp($id_option, 'language');
      //the path of the locale dirs
      $locale_dir = $this->getLocaleDirPath($id_option);
      $languages = array_map(fn($a) => X::basename($a), Dir::getDirs($locale_dir)) ?: [];
      $res = [];
      $project = new Project($this->db, $id_project);
      $errors = [];
      if (!empty($languages)) {
        $po_file = [];
        $success = false;
        foreach ($languages as $lng) {
          // the path of po and mo files
          $index = $this->getIndexValue($id_option) ?: 1;
          $po = $locale_dir.'/'.$lng.'/LC_MESSAGES/'.$o['text'].$index.'.po';
          // if the file po exist takes its content
          if ($translations = $this->parsePoFile($po)) {
            foreach ($translations as $t) {
              $id = null;
              // @var string $original the original expression
              if ($original = stripslashes($t->getMsgId())) {
                $idx = X::find($res, ['exp' => $original]);
                if (is_null($idx)) {
                  $todo = true;
                  $row  = [];
                }
                else {
                  $todo = false;
                  $row =& $res[$idx];
                }

                // the translation of the string found in the po file
                if (isset($row['id_exp'])) {
                  $id = $row['id_exp'];
                }

                // @var  $id takes the id of the original expression in db
                if (!isset($id)
                  && !($id = $this->getId($original, $path_source_lang))
                ) {
                  $id = $this->insert($original, $path_source_lang);
                  if (!$id) {
                    throw new Exception(
                      sprintf(
                        _("Impossible to insert the original string << %s >> in the original language %s"),
                        $this->normlizeText($original),
                        $path_source_lang
                      )
                    );
                  }
                }

                if ($id) {
                  $row[$lng.'_po'] = stripslashes($t->getMsgStr());
                  $row[$lng.'_db'] = $this->getTranslation($id, null, $lng) ?: '';
                  if (!empty($row[$lng.'_po']) && empty($row[$lng.'_db'])) {
                    if ($this->insertTranslation($id, $lng, $row[$lng.'_po'])) {
                      $row[$lng.'_db'] = $row[$lng.'_po'];
                    }
                    else {
                      throw new Exception(
                      sprintf(
                        _("Impossible to insert the expression \"%s\" in %s"),
                        $row[$lng.'_po'],
                        $lng
                      )
                    );
                    }
                  }

                  if ($todo) {
                    $row['id_exp'] = $id;
                    $row['paths'] = [];
                    $row['exp'] = $original;
                    // @var array takes $paths of files in which the string was found from the file po
                    $paths = $t->getReference();

                    // get the url to use it for the link to ide from the table
                    foreach ($paths as $p) {
                      $row['paths'][] = $project->realToUrl($p);
                    }

                    // the number of times the strings is found in the files of the path
                    $row['occurrence'] = count($row['paths']);
                    $res[] = $row;
                  }
                }
                else {
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
              if (!($idExp = $this->getId($exp, $opt['language']))) {
                $idExp = $this->insert($exp, $opt['language']);
                if (empty($idExp)) {
                  $langText = X::getField($primaryLanguages, ['code' => $lang], 'text');
                  throw new Exception(X::_('Impossible to insert the original string %s in the original language %s', $this->normlizeText($exp), $langText));
                }
              }

              if (!empty($idExp)) {
                if (!$this->hasTranslation($idExp, $opt['language'])) {
                  $this->insertOrUpdateTranslation($idExp, $exp, $opt['language']);
                }

                if (!empty($opt['translation'])
                  && !$this->hasTranslation($idExp, $lang)
                ) {
                  $this->insertOrUpdateTranslation($idExp, $opt['translation'], $lang);
                }

                $r = [
                  'id_exp' => $idExp,
                  'exp' => $this->normlizeText($exp),
                  $opt['language'] . '_po' => $exp,
                  $opt['language'] . '_db' => $this->getTranslation($idExp, null, $opt['language']) ?: '',
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
              if (($idExp = $this->getId($exp, $opt['language']))) {
                if (!empty($opt['translation'])
                  && !$this->hasTranslation($idExp, $lang)
                ) {
                  $this->insertOrUpdateTranslation($idExp, $opt['translation'], $lang);
                }
              }
              $rows[$idx][$lang . '_po'] = $opt['translation'];
              $rows[$idx][$lang . '_db'] = $this->getTranslation($rows[$idx]['id_exp'], null, $lang) ?: '';
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
      /** @var Project */
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
    if ($this->hasTranslation($idExp, $lang)) {
      if ($this->updateTranslation($idExp, $lang, $expression)) {
        return true;
      }
    }
    else if ($this->insertTranslation($idExp, $lang, $expression)) {
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
    $clsCfg = $this->getClassCfg();
    return (bool)$this->db->delete($clsCfg['tables']['i18n_exp'], [
      $clsCfg['arch']['i18n_exp']['id_exp'] => $idExp,
      $clsCfg['arch']['i18n_exp']['lang'] => $lang
    ]);
  }


  public function generateFiles(string $idPath, array $languages = [], string $mode = 'files')
  {
    if (!\in_array($mode, ['files', 'options'], true)) {
      throw new Exception(X::_("No valid mode %s", $mode));
    }
    // The position of locale directory
    $localeDir = $this->getLocaleDirPath($idPath);
    /** @var (array) $languages based on locale dirs found in the path */
    $currentLangs = array_map('basename', Dir::getDirs($localeDir) ?: []);
    if (empty($languages)) {
      $languages = $currentLangs;
    }
    if (empty($languages)) {
      $languages = $this->getPrimariesLangs(true);
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
          $this->importFromFilesOptions($idPath, $languages);
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


  public function hashText(string $exp): string
  {
    return hash(static::$hashAlgo, $this->normlizeText($exp));
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
        $fileHandler  = new FileSystem($poFile);
        $poParser     = new Parser($fileHandler);
        $catalog      = Parser::parseFile($poFile);
        $compiler     = new PoCompiler();
        $headersClass = new Header();
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
          throw new Exception("Impossible to find the root for option, see Misc log");
        }
        $root = constant($constroot);
        foreach ($data['res'] as $index => $r) {
          if (!$catalog->getEntry($r['original_exp'])) {
            //prepare the new entry for the Catalog
            $entry = new Entry($r['original_exp'], $r[$lang]);
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
      $this->getTranslationsWidget($idPath);
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
        else if (($parentCode === 'app')
          && ($code === 'main')
          && ($allOptions = $this->options->fullOptions(false))
        ) {
          foreach ($allOptions as $o) {
            if ($o['code'] === 'appui') {
              $idAlias = $this->options->fromCode('plugin', 'list', 'templates', 'option', 'appui');
              if ($appuiOptions = $this->options->fullOptions($o['id'])) {
                foreach ($appuiOptions as $ao) {
                  if ($ao['id_alias'] !== $idAlias) {
                    $options = X::mergeArrays($options, $this->options->findI18n($ao['id']));
                  }
                }
              }
            }
            else {
              $options = X::mergeArrays($options, $this->options->findI18n($o['id']));
            }
          }
        }
      }
      if (!empty($options)) {
        foreach ($options as $opt) {
          $codePath = $this->options->getCodePath($opt['id']);
          $text = $this->options->rawText($opt['id']);
          if ($codePath && !empty($text)) {
            $codePath = \implode('/', \array_reverse($codePath));
            foreach ($languages as $lang) {
              if (!isset($toJSON[$lang])) {
                $toJSON[$lang] = [];
              }
              $t = $this->normlizeText($text);
              if (!isset($toJSON[$lang][$t])) {
                $toJSON[$lang][$t] = [
                  'language' => $opt['language'],
                  'paths' => [$codePath],
                  'original' => $t,
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


  private function importFromFilesOptions(string $idPath, array $languages): bool
  {
    if (($localeDir = $this->getLocaleDirPath($idPath))
      && !empty($languages)
    ){
      $imported = 0;
      foreach ($languages as $lang) {
        if (\is_file("$localeDir/$lang/options.json")
          && ($translations = \json_decode(\file_get_contents("$localeDir/$lang/options.json"), true))
        ) {
          foreach ($translations as $trans) {
            if (!empty($trans['original']) && !empty($trans['language'])) {
              if (!$idExp = $this->getId($trans['original'], $trans['language'])) {
                $idExp = $this->insert($trans['original'], $trans['language']);
              }

              if (!empty($idExp)) {
                if (!$this->hasTranslation($idExp, $trans['language'])) {
                  $imported += $this->insertTranslation($idExp, $trans['language'], $trans['original']);
                }

                if (!empty($trans['translation'])
                  && !$this->hasTranslation($idExp, $lang)
                ) {
                  $imported += $this->insertTranslation($idExp, $lang, $trans['translation']);
                }
              }
            }
          }
        }
      }
      return !empty($imported);
    }
    return false;
  }


  private function generateFilesMo(string $idPath, array $languages): bool
  {
    if (($domain = $this->options->text($idPath))
      && ($localeDir = $this->getLocaleDirPath($idPath))
      && ($indexPath = $this->getIndexPath($idPath))
      && !empty($languages)
    ) {
      $versionNumber = $this->getIndexValue($idPath) ?: 1;
      $success = true;
      foreach ($languages as $lang) {
        $file = "$localeDir/$lang/LC_MESSAGES/$domain$versionNumber.";
        if (\is_file($file.'mo')) {
          \unlink($file.'mo');
        }
        if (\is_file($file.'po')
          && ($translations = $this->poLoader->loadFile($file.'po'))
          && !$this->moGenerator->generateFile($translations, $file.'mo')
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
    return \is_file($file) ? Parser::parseFile($file)->getEntries() : [];
  }

}
