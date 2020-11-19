<?php
/**
 * PHP version 7
 *
 * @category CDN
 * @package  BBN
 * @author   Theomas Nabet <thomas.nabet@gmail.com>
 * @license  https://opensource.org/licenses/mit-license.php MIT
 * @version  "GIT: <git_id>"
 * @link     https://www.bbn.io/bbn-php
 */
namespace bbn\cdn;

use bbn;

/**
 * Library retriever tool.
 *
 * Allows to create a whole configuratiuon based on libraries names and versions.
 *
 * You first create the object with a database connection to the CDN database:
 *
 * ```php
 * $db = new \bbn\db([
 *   'engine' => 'sqlite',
 *   'db' => BBN_CDN_DB
 * ]);
 * $lib = new \bbn\cdn\library($db, 'fr');
 * ```
 *
 * It can give you information about the given library:
 *
 * ```php
 * $info = $lib->info('moment|2.12.0')
 * // {
 * //     "name": "moment",
 * //     "fname": "moment",
 * //     "title": "Moment",
 * //     "latest": "2.12.0",
 * //     "website": "http://momentjs.com/",
 * //     "last_update": "2016-04-17 19:24:33",
 * //     "last_check": "2016-04-17 19:24:33",
 * //     "id": 146,
 * //     "version": "2.12.0",
 * //     "content": {
 * //         "files": [
 * //             "moment-with-locales.min.js",
 * //         ],
 * //         "lang": [
 * //         ],
 * //         "theme_files": [
 * //         ],
 * //     },
 * //     "internal": 0,
 * //     "prepend": [
 * //     ],
 * // }
 * ```
 *
 * Or you can add all the libraries you want:
 *
 * ```php
 * $lib->add('jquery-ui') // jQuery will also be added
 *     ->add('axios', false); // no dependency will be added here
 * ```
 *
 * Then get an array of all the files needed to be loaded:
 *
 * ```php
 * $cfg = $lib->get_config()
 * // {
 * //     "libraries": {
 * //         "axios": "v0.19.2",
 * //         "animate-css": "3.7.2",
 * //         "moment": "2.12.0",
 * //         "bbnjs": "1.0.1",
 * //         "vuejs": "v2.6.10",
 * //         "bbn-vue": "2.0.2",
 * //     },
 * //     "prepend": {
 * //         "lib/bbnjs/1.0.1/src/css/01-basic.less": [
 * //             "lib/bbnjs/1.0.1/src/css/themes/default.less",
 * //             "lib/bbnjs/1.0.1/src/css/themes/dark.less",
 * //         ],
 * //         "lib/bbnjs/1.0.1/src/css/02-background.less": [
 * //             "lib/bbnjs/1.0.1/src/css/themes/default.less",
 * //             "lib/bbnjs/1.0.1/src/css/themes/dark.less",
 * //         ],
 * //         "lib/bbnjs/1.0.1/src/css/03-text.less": [
 * //             "lib/bbnjs/1.0.1/src/css/themes/default.less",
 * //             "lib/bbnjs/1.0.1/src/css/themes/dark.less",
 * //         ],
 * //         "lib/bbnjs/1.0.1/src/css/04-border.less": [
 * //             "lib/bbnjs/1.0.1/src/css/themes/default.less",
 * //             "lib/bbnjs/1.0.1/src/css/themes/dark.less",
 * //         ],
 * //         "lib/bbnjs/1.0.1/src/css/05-padding.less": [
 * //             "lib/bbnjs/1.0.1/src/css/themes/default.less",
 * //             "lib/bbnjs/1.0.1/src/css/themes/dark.less",
 * //         ],
 * //         "lib/bbnjs/1.0.1/src/css/06-margin.less": [
 * //             "lib/bbnjs/1.0.1/src/css/themes/default.less",
 * //             "lib/bbnjs/1.0.1/src/css/themes/dark.less",
 * //         ],
 * //         "lib/bbnjs/1.0.1/src/css/07-align.less": [
 * //             "lib/bbnjs/1.0.1/src/css/themes/default.less",
 * //             "lib/bbnjs/1.0.1/src/css/themes/dark.less",
 * //         ],
 * //         "lib/bbnjs/1.0.1/src/css/08-radius.less": [
 * //             "lib/bbnjs/1.0.1/src/css/themes/default.less",
 * //             "lib/bbnjs/1.0.1/src/css/themes/dark.less",
 * //         ],
 * //         "lib/bbnjs/1.0.1/src/css/09-dimension.less": [
 * //             "lib/bbnjs/1.0.1/src/css/themes/default.less",
 * //             "lib/bbnjs/1.0.1/src/css/themes/dark.less",
 * //         ],
 * //         "lib/bbnjs/1.0.1/src/css/10-position.less": [
 * //             "lib/bbnjs/1.0.1/src/css/themes/default.less",
 * //             "lib/bbnjs/1.0.1/src/css/themes/dark.less",
 * //         ],
 * //         "lib/bbnjs/1.0.1/src/css/11-align.less": [
 * //             "lib/bbnjs/1.0.1/src/css/themes/default.less",
 * //             "lib/bbnjs/1.0.1/src/css/themes/dark.less",
 * //         ],
 * //         "lib/bbnjs/1.0.1/src/css/11-containers.less": [
 * //             "lib/bbnjs/1.0.1/src/css/themes/default.less",
 * //             "lib/bbnjs/1.0.1/src/css/themes/dark.less",
 * //         ],
 * //         "lib/bbnjs/1.0.1/src/css/12-state.less": [
 * //             "lib/bbnjs/1.0.1/src/css/themes/default.less",
 * //             "lib/bbnjs/1.0.1/src/css/themes/dark.less",
 * //         ],
 * //         "lib/bbnjs/1.0.1/src/css/13-components.less": [
 * //             "lib/bbnjs/1.0.1/src/css/themes/default.less",
 * //             "lib/bbnjs/1.0.1/src/css/themes/dark.less",
 * //         ],
 * //     },
 * //     "js": [
 * //         "lib/axios/v0.19.2/dist/axios.min.js",
 * //         "lib/moment/2.12.0/moment-with-locales.min.js",
 * //         "lib/bbnjs/1.0.1/src/bbn.js",
 * //         "lib/bbnjs/1.0.1/src/functions.js",
 * //         "lib/bbnjs/1.0.1/src/env/_def.js",
 * //         "lib/bbnjs/1.0.1/src/var/_def.js",
 * //         "lib/bbnjs/1.0.1/src/var/diacritic.js",
 * //         "lib/bbnjs/1.0.1/src/fn/_def.js",
 * //         "lib/bbnjs/1.0.1/src/fn/ajax.js",
 * //         "lib/bbnjs/1.0.1/src/fn/form.js",
 * //         "lib/bbnjs/1.0.1/src/fn/history.js",
 * //         "lib/bbnjs/1.0.1/src/fn/init.js",
 * //         "lib/bbnjs/1.0.1/src/fn/locale.js",
 * //         "lib/bbnjs/1.0.1/src/fn/misc.js",
 * //         "lib/bbnjs/1.0.1/src/fn/object.js",
 * //         "lib/bbnjs/1.0.1/src/fn/size.js",
 * //         "lib/bbnjs/1.0.1/src/fn/string.js",
 * //         "lib/bbnjs/1.0.1/src/fn/style.js",
 * //         "lib/bbnjs/1.0.1/src/fn/type.js",
 * //         "lib/vuejs/v2.6.10/dist/vue.min.js",
 * //         "lib/bbn-vue/2.0.2/src/vars.js",
 * //         "lib/bbn-vue/2.0.2/src/methods.js",
 * //         "lib/bbn-vue/2.0.2/src/mixins/basic.js",
 * //         "lib/bbn-vue/2.0.2/src/mixins/empty.js",
 * //         "lib/bbn-vue/2.0.2/src/mixins/dimensions.js",
 * //         "lib/bbn-vue/2.0.2/src/mixins/position.js",
 * //         "lib/bbn-vue/2.0.2/src/mixins/dropdown.js",
 * //         "lib/bbn-vue/2.0.2/src/mixins/keynav.js",
 * //         "lib/bbn-vue/2.0.2/src/mixins/toggle.js",
 * //         "lib/bbn-vue/2.0.2/src/mixins/localStorage.js",
 * //         "lib/bbn-vue/2.0.2/src/mixins/data.js",
 * //         "lib/bbn-vue/2.0.2/src/mixins/dataEditor.js",
 * //         "lib/bbn-vue/2.0.2/src/mixins/events.js",
 * //         "lib/bbn-vue/2.0.2/src/mixins/list.js",
 * //         "lib/bbn-vue/2.0.2/src/mixins/memory.js",
 * //         "lib/bbn-vue/2.0.2/src/mixins/input.js",
 * //         "lib/bbn-vue/2.0.2/src/mixins/resizer.js",
 * //         "lib/bbn-vue/2.0.2/src/mixins/close.js",
 * //         "lib/bbn-vue/2.0.2/src/mixins/field.js",
 * //         "lib/bbn-vue/2.0.2/src/mixins/view.js",
 * //         "lib/bbn-vue/2.0.2/src/mixins/observer.js",
 * //         "lib/bbn-vue/2.0.2/src/mixins/keepCool.js",
 * //         "lib/bbn-vue/2.0.2/src/mixins/url.js",
 * //         "lib/bbn-vue/2.0.2/src/mixins.js",
 * //         "lib/bbn-vue/2.0.2/src/defaults.js",
 * //         "lib/bbn-vue/2.0.2/src/init.js",
 * //     ],
 * //     "css": [
 * //         "lib/animate-css/3.7.2/animate.min.css",
 * //         "lib/bbnjs/1.0.1/src/css/01-basic.less",
 * //         "lib/bbnjs/1.0.1/src/css/02-background.less",
 * //         "lib/bbnjs/1.0.1/src/css/03-text.less",
 * //         "lib/bbnjs/1.0.1/src/css/04-border.less",
 * //         "lib/bbnjs/1.0.1/src/css/05-padding.less",
 * //         "lib/bbnjs/1.0.1/src/css/06-margin.less",
 * //         "lib/bbnjs/1.0.1/src/css/07-align.less",
 * //         "lib/bbnjs/1.0.1/src/css/08-radius.less",
 * //         "lib/bbnjs/1.0.1/src/css/09-dimension.less",
 * //         "lib/bbnjs/1.0.1/src/css/10-position.less",
 * //         "lib/bbnjs/1.0.1/src/css/11-align.less",
 * //         "lib/bbnjs/1.0.1/src/css/11-containers.less",
 * //         "lib/bbnjs/1.0.1/src/css/12-state.less",
 * //         "lib/bbnjs/1.0.1/src/css/13-components.less",
 * //     ],
 * // }
 * ```
 *
 * @category CDN
 * @package  BBN
 * @author   Theomas Nabet <thomas.nabet@gmail.com>
 * @license  https://opensource.org/licenses/mit-license.php MIT
 * @link     https://bbnio2.thomas.lan/bbn-php/doc/class/cdn/library
 */
class library
{
  use common;

  protected $db;

  public $libs = [];

  public $js = [];

  public $css = [];

  public $lang = 'en';

  public $theme = false;

  public $vars = [];


  /**
   * Constructor.
   *
   * ```php
   * $db = new \bbn\db([
   *   'engine' => 'sqlite',
   *   'db' => BBN_CDN_DB
   * ]);
   * $lib = new \bbn\cdn\library($db, 'fr');
   * ```
   *
   * @param bbn\db $db   The database connection
   * @param string $lang The default language for the libraries
   */
  public function __construct(bbn\db $db, $lang = 'en')
  {
    $this->db   = $db;
    $this->lang = $lang;
  }


  /**
   * Returns all the informations stored about a library.
   *
   * ```php
   * $info = $lib->info('bbn-vue');
   * // {
   * //     "name": "bbn-vue",
   * //     "fname": "bbn.vue",
   * //     "title": "bbn-vue",
   * //     "latest": "2.0.2",
   * //     "website": "https://bbn.io/bbn-vue",
   * //     "last_update": "2020-06-22 13:47:01",
   * //     "last_check": "2020-06-22 13:47:01",
   * //     "id": 210,
   * //     "version": "2.0.2",
   * //     "content": {
   * //         "files": [
   * //             "src/vars.js",
   * //             "src/methods.js",
   * //             "src/mixins/basic.js",
   * //             "src/mixins/empty.js",
   * //             "src/mixins/dimensions.js",
   * //             "src/mixins/position.js",
   * //             "src/mixins/dropdown.js",
   * //             "src/mixins/keynav.js",
   * //             "src/mixins/toggle.js",
   * //             "src/mixins/localStorage.js",
   * //             "src/mixins/data.js",
   * //             "src/mixins/dataEditor.js",
   * //             "src/mixins/events.js",
   * //             "src/mixins/list.js",
   * //             "src/mixins/memory.js",
   * //             "src/mixins/input.js",
   * //             "src/mixins/resizer.js",
   * //             "src/mixins/close.js",
   * //             "src/mixins/field.js",
   * //             "src/mixins/view.js",
   * //             "src/mixins/observer.js",
   * //             "src/mixins/keepCool.js",
   * //             "src/mixins/url.js",
   * //             "src/mixins.js",
   * //             "src/defaults.js",
   * //             "src/init.js",
   * //         ],
   * //         "lang": [
   * //         ],
   * //         "theme_files": [
   * //         ],
   * //     },
   * //     "internal": 0,
   * //     "prepend": [
   * //     ],
   * // }
   * ```
   *
   * @param string $library The name of the library, optionally followed by `|` and the version
   * @return null|array
   */
  public function info(string $library): ?array
  {
    $params = explode('|', $library);
    $lib    = array_shift($params);
    $cfg    = [
      'table' => 'libraries',
      'fields' => [
        'libraries.name', 'libraries.fname', 'libraries.title',
        'libraries.latest', 'libraries.website', 'libraries.last_update',
        'libraries.last_check', 'libraries.git', 'libraries.npm', 'libraries.mode',
        'versions.id', 'versions.content', 'versions.internal',
        'version' => 'versions.name'
      ],
      'join' => [
        [
          'table' => 'versions',
          'on' => [
            'conditions' => [
              [
                'field' => 'versions.library',
                'operator' => 'LIKE',
                'exp' => '"libraries"."name"'
              ]
            ]
          ]
        ]
      ],
      'where' => [
        'logic' => 'AND',
        'conditions' => [
          [
            'field' => 'libraries.name',
            'operator' => '=',
            'value' => $lib
          ]
        ]
      ]
    ];
    if (!empty($params[0]) && ($params[0] !== 'latest')) {
      $cfg['where']['conditions'][] = [
        'field' => 'versions.name',
        'operator' => '=',
        'value' => $params[0]
      ];
    }
    else {
      $cfg['where']['conditions'][] = [
        'field' => 'versions.name',
        'operator' => '=',
        'exp' => '"libraries"."latest"'
      ];
    }

    // We take all the info from the database
    if ($info = $this->db->rselect($cfg)) {
      // Most of the info is in the JSON field, content
      $info['content'] = json_decode($info['content']);
      // The files from which the content will be prepended to corresponding files
      $info['prepend'] = [];
      if ($info['git']) {
        if (strpos($info['git'], 'https://github.com/') === 0) {
          $info['git'] = substr($info['git'], strlen('https://github.com/'));
          if (substr($info['git'], -4) === '.git') {
            $info['git'] = substr($info['git'], 0, -4);
          }
        }
        else {
          $info['git'] = null;
        }
      }

      // If there are theme files we want to add them to the list of files
      if (!empty($info['content']->theme_files) && isset($info['content']->files)) {
        // Parameters of the library sent through the URL
        if (!empty($params[1])) {
          $ths = explode('!', $params[1]);
        } elseif (isset($info['content']->default_theme)) {
          $ths = [$info['content']->default_theme];
        }

        if (!empty($ths)) {
          foreach ($ths as $th) {
            if (!empty($info['content']->theme_prepend)) {
              foreach ($info['content']->theme_files as $tf) {
                foreach ($info['content']->files as $f) {
                  /** @todo Remove!!! */
                  if (substr($f, -4) === 'less') {
                    if (bbn\x::indexOf($tf, '%s') > -1) {
                      $info['prepend'][$f][] = sprintf(str_replace('%s', '%1$s', $tf), $th);
                    } else {
                      $info['prepend'][$f][] = $tf;
                    }
                  }
                }
              }
            } else {
              foreach ($info['content']->theme_files as $tf) {
                if (bbn\x::indexOf($tf, '%s') > -1) {
                  $info['content']->files[] = sprintf(str_replace('%s', '%1$s', $tf), $th);
                } else {
                  $info['content']->files[] = $tf;
                }
              }
            }
          }
        }
      }

      // Themes
      if (!isset($ths) && isset($info['content']->themes)) {
        if (isset($params[1], $info['content']->themes->$params[1])) {
          $info['theme'] = $params[1];
        } elseif (isset($info['content']->default_theme)) {
          $info['theme'] = $info['content']->default_theme;
        }

        if (isset($info['theme'], $info['content']->themes->{$info['theme']})) {
          if (!is_array($info['content']->themes->{$info['theme']})) {
            $info['content']->themes->$info['theme'] = [$info['content']->themes->$info['theme']];
          }

          $info['content']->files = array_merge($info['content']->files, $info['content']->themes->$info['theme']);
        }
      }

      return $info;
    }

    return null;
  }


  /**
   * Returns the dependencies from the given library version.
   *
   * ```php
   * $info = $lib->info('bbn-vue');
   * $deps = $lib->get_dependencies($info['version']);
   * bbn\x::dump($deps);
   * // [
   * //   "vuejs|v2.6.10",
   * //   "axios|v0.19.2",
   * //   "animate-css|3.7.2",
   * //   "moment|2.12.0",
   * //   "bbnjs|1.0.1",
   * // ]
   * ```
   *
   * @param string $id_version The version ID (in the database)
   * @param array  $res        The result array
   * @return array
   */
  public function get_dependencies(string $id_version, &$res = []): array
  {
    $deps = $this->db->get_col_array(
      <<<SQL
SELECT "dependencies"."id_master"
FROM "main"."dependencies"
JOIN "main"."versions"
  ON "main"."dependencies"."id_master" = "main"."versions"."id"
WHERE "dependencies"."id_slave" = ?
GROUP BY "versions"."library"
ORDER BY "versions"."internal" DESC,
"dependencies"."id_slave" ASC
SQL,
      $id_version
    );
    if (!empty($deps)) {
      foreach ($deps as $dep) {
        $d = $this->db->get_one(
          <<<SQL
SELECT "library" || "|" || "name"
FROM "main"."versions"
WHERE "versions"."id" = ?
SQL,
          $dep
        );
        if (($dep !== $id_version) && !in_array($d, $res)) {
          $this->get_dependencies($dep, $res);
          $res[] = $d;
        }
      }
    }

    return $res;
  }


  /**
   * Adds a new library to the config.
   *
   * ```php
   * // @var bbn\cdn\library $lib
   * $lib->add('jquery-ui') // jQuery will be added too
   *     ->add('axios', false); // no dependency will be added here
   * bbn\x::dump($lib->get_config());
   * // {
   * //     "libraries": {
   * //         "jquery": "3.3.1",
   * //         "jquery-ui": "1.12.1",
   * //         "axios": "v0.19.2",
   * //     },
   * //     "prepend": [
   * //     ],
   * //     "js": [
   * //         "lib/jquery/3.3.1/dist/jquery.min.js",
   * //         "lib/jquery-ui/1.12.1/jquery-ui.min.js",
   * //         "lib/axios/v0.19.2/dist/axios.min.js",
   * //     ],
   * //     "css": [
   * //         "lib/jquery-ui/1.12.1/jquery-ui.min.css",
   * //     ],
   * // }
   * ```
   *
   * @param string  $library The name of the library, optionally followed by `|` and the version
   * @param integer $has_dep Result will include or not dependencies if any
   * @return self
   */
  public function add(string $library, $has_dep = 1): self
  {
    if ($info = $this->info($library)) {
      if (!isset($this->libs[$info['name']][$info['internal']])) {
        $last = $this->db->last();
        if ($has_dep) {
          // Adding dependencies
          if (!$info['id']) {
            throw new \Exception(_("Problem adding library").' '.$library);
          }

          $dependencies = $this->get_dependencies($info['id']);
          //bbn\x::dump($dependencies, $info);
          if (!empty($dependencies)) {
            foreach ($dependencies as $dep) {
              $this->add($dep);
            }
          }
        }

        if (!isset($this->libs[$info['name']])) {
          $this->libs[$info['name']] = [];
        }

        $path                                         = 'lib/'.$info['name'].'/'.$info['version'].'/';
        $this->libs[$info['name']][$info['internal']] = [
          'version' => $info['version'],
          'prepend' => [],
          'git' => $info['git'],
          'npm' => $info['npm'],
          'mode' => $info['mode'],
          'name' => $info['name'],
          'path' => $path,
          'files' => []
        ];
        $files                                        =& $this->libs[$info['name']][$info['internal']]['files'];
        $prepend                                      =& $this->libs[$info['name']][$info['internal']]['prepend'];

        // From here, adding files (no matter the type) to $this->libs array for each library
        // Adding language files if they must be prepent
        if (($this->lang !== 'en') && isset($info['content']->lang, $info['content']->prepend_lang)) {
          foreach ($info['content']->lang as $lang) {
            $files[] = sprintf($path.$lang, $this->lang);
          }
        }

        if (isset($info['content']->files) && is_array($info['content']->files)) {
          // Adding each files - no matter the type
          foreach ($info['content']->files as $f) {
            if (isset($this->info['theme']) && strpos($f, '%s')) {
              $f = sprintf($f, $this->info['theme']);
            }

            if (isset($info['prepend'][$f])) {
              $prepend[$path.$f] = [];
              foreach ($info['prepend'][$f] as $p) {
                $prepend[$path.$f][] = $path.$p;
              }
            }

            $files[] = $path.$f;
          }
        }
        else {
          die(\bbn\x::dump("Error!", $library, $info, $last));
        }

        // Adding language files at the end (default way)
        if (($this->lang !== 'en') && isset($info['content']->lang) && !isset($info['content']->prepend_lang)) {
          if (is_string($info['content']->lang)) {
            $info['content']->lang = [$info['content']->lang];
          }

          if (is_array($info['content']->lang)) {
            foreach ($info['content']->lang as $lang) {
              array_push($files, sprintf($path.$lang, $this->lang));
            }
          }
          else {
            die("Problem with the language file for $info[name]");
          }
        }
      }
    }
    else {
      die(\bbn\x::dump($library, $this->db->last()));
    }

    return $this;
  }


  /**
   * Creates the configuration and returns it.
   *
   * ```php
   * // @var bbn\cdn\library $lib
   * $lib->add('jquery-ui|latest|black');
   * $cfg = $lib->get_config()
   * bbn\x::dump($cfg);
   * // {
   * //     "libraries": {
   * //         "jquery": "3.3.1",
   * //         "jquery-ui": "1.12.1",
   * //     },
   * //     "prepend": [
   * //     ],
   * //     "js": [
   * //         "lib/jquery/3.3.1/dist/jquery.min.js",
   * //         "lib/jquery-ui/1.12.1/jquery-ui.min.js",
   * //         "lib/jquery-ui/1.12.1/i18n/datepicker-fr.js",
   * //     ],
   * //     "css": [
   * //         "lib/jquery-ui/1.12.1/jquery-ui.min.css",
   * //         "lib/jquery-ui/1.12.1/jquery-ui.theme.min.css",
   * //         "lib/jquery-ui/1.12.1/themes/black/jquery-ui.min.css",
   * //         "lib/jquery-ui/1.12.1/themes/black/theme.css",
   * //     ],
   * // }
   * ```
   *
   * @return array
   */
  public function get_config()
  {
    $res = [
      'libraries' => [],
      'prepend' => [],
      'includes' => []
    ];
    foreach ($this->libs as $lib_name => $lib) {
      ksort($lib);
      $lib                         = current($lib);
      $res['libraries'][$lib_name] = (string)$lib['version'];
      if (isset($lib['prepend'])) {
        $res['prepend'] = array_merge($res['prepend'], $lib['prepend']);
      }

      $inc = $lib;
      foreach ($lib['files'] as $f) {
        $ext = bbn\str::file_ext($f);
        foreach (self::$types as $type => $extensions) {
          if (in_array($ext, $extensions)) {
            if (!isset($res[$type])) {
              $res[$type] = [];
              $inc[$type] = [];
            }

            if (!in_array($f, $res[$type])) {
              $res[$type][] = $f;
              $inc[$type][] = substr($f, strlen($inc['path']));
            }
          }
        }
      }

      unset($inc['files']);
      $res['includes'][] = $inc;
    }

    return $res;
  }


}
