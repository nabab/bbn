<?php

/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 12/05/2015
 * Time: 12:53
 * Environment class manages the HTTP environment and sets up the MVC variables
 * - cli
 * - post
 * - get
 * - files
 * - params
 * - url
 * It uses the preset environment variables but can also be simulated
 */

namespace bbn\Mvc;

use bbn;
use bbn\X;

class Environment
{

  private static $_initiated = false;

  private static $_input;

  private $_has_post = false;

  /**
   * An array of strings enclosed between the slashes of the requested path
   * @var null|array
   */
  private $_params;

  /**
   * The mode of the output (doc, html, json, txt, Xml...)
   * @var null|string
   */
  private $_mode;

  /**
   * The request sent to the server to get the actual controller.
   * @var null|string
   */
  private $_url;

  /**
   * @var array $_POST
   */
  private $_post;

  /**
   * @var array $_GET
   */
  private $_get;

  /**
   * @var array $_FILES
   */
  private $_files;

  /**
   * Determines if it is sent through the command line
   * @var boolean
   */
  private $cli;

  private $new_url;


  public static function detectLanguage(): array
  {
    $httpAcceptLanguageHeader = self::_getHttpAcceptLanguageHeader();

    if ($httpAcceptLanguageHeader == null) {
      return [];
    }

    $locales = self::_getWeightedLocales($httpAcceptLanguageHeader);

    $sortedLocales = self::_sortLocalesByWeight($locales);

    return array_map(
      function ($weightedLocale) {
        return $weightedLocale['locale'];
      }, $sortedLocales
    );
  }


  public static function getInput()
  {
    return self::$_input;
  }


  /**
   * Change the output mode (content-type)
   *
   * @param $mode
   * @return string $this->_mode
   */
  public function setMode($mode)
  {
    if (Router::isMode($mode)) {
      $this->_mode = $mode;
    }

    return $this->_mode;
  }


  private function _setLocale()
  {

  }


  private function _tryLocales(array $locales): ?string
  {
    foreach ($locales as $l) {
      if (setlocale(LC_TIME, $l)) {
        return $l;
      }
    }

    return null;
  }


  private function _init()
  {
    $locales = [
      BBN_LANG . '_' . strtoupper(BBN_LANG) . '.utf8',
      BBN_LANG . '_' . strtoupper(BBN_LANG),
      BBN_LANG,
      'en_EN.utf8',
      'en-EN.utf8',
      'en_EN',
      'en-EN',
      'en_US.utf8',
      'en-US.utf8',
      'en_US',
      'en-US',
      'en',
    ];
    // When using CLI a first parameter can be used as route,
    // a second JSON encoded can be used as $this->_post
    if (php_sapi_name() === 'cli') {
      $this->_mode = 'cli';
      $this->getCli();
    }
    // Non CLI request
    else {
      if ($user_locales = self::detectLanguage()) {
        array_unshift($locales, ...$user_locales);
      }

      if (!isset($this->_post)) {
        $this->getPost();
      }

      if ($this->_has_post) {
        self::_dot_to_array($this->_post);
        /** @todo Remove the json parameter from the bbn.js functions */
        $this->setMode(BBN_DEFAULT_MODE);
      }
      elseif (\count($_FILES)) {
        $this->setMode(BBN_DEFAULT_MODE);
      }
      // If no post, assuming to be a DOM document
      else {
        $this->setMode('dom');
      }

      if (isset($_SERVER['REQUEST_URI'])) {
        $current = $_SERVER['REQUEST_URI'];
      }

      if (isset($current) && (BBN_CUR_PATH === '/' || strpos($current, BBN_CUR_PATH) !== false)
      ) {
        $url = explode("?", urldecode($current))[0];
        if (BBN_CUR_PATH === '/') {
          $this->setParams($url);
        } else {
          $this->setParams(substr($url, \strlen(BBN_CUR_PATH)));
        }
      }
    }

    if (defined('BBN_LANG') && !defined('BBN_LOCALE')) {
      if ($locale = $this->_tryLocales($locales)) {
        define('BBN_LOCALE', $locale);
      }
      else {
        throw new \Exception("Impossible to find a corresponding locale on this server for this app");
      }
    }

    $this->_url = implode('/', $this->_params ?: []);
    return $this;
  }


  public function __construct($url = false)
  {
    if (!self::$_initiated) {
      self::_initialize();
      $this->_init();
    }
  }


  public function setPrepath($path)
  {
    $path = X::removeEmpty(explode('/', $path));
    if (\count($path)) {
      foreach ($path as $p) {
        if ($this->_params[0] === $p) {
          array_shift($this->_params);
          $this->_url = substr($this->_url, \strlen($p) + 1);
        } else {
          die("The prepath $p doesn't seem to correspond to the current path {$this->_url}");
        }
      }
    }

    return true;
  }


  /**
   * Returns true if called from CLI/Cron, false otherwise
   *
   * @return boolean
   */
  public function isCli()
  {
    if (!isset($this->_cli)) {
      $this->_cli = X::isCli();
      if ($this->_cli) {
        $opt = getopt('', ['cli']);
        if (isset($opt['cli'])) {
          $this->_cli = 'direct';
        }
      }
    }

    return $this->_cli;
  }


  public function getUrl()
  {
    return $this->_url;
  }


  public function simulate($url, $post = false, $arguments = null)
  {
    unset($this->_params);
    $this->setParams($url . (empty($arguments) ? '' : '/' . implode('/', $arguments)));
    $this->_post = $post ?: null;
    $this->_init();
    $this->_url = $url;
  }


  public function getMode()
  {
    return $this->_mode;
  }


  public function getCli()
  {
    global $argv;
    if ($this->isCli()) {
      if ($this->isCli() === 'direct') {
        array_shift($argv);
      }

      $this->_post = [];
      if (isset($argv[1])) {
        $this->setParams($argv[1]);
        if (isset($argv[2])) {
          if (!isset($argv[3]) && \bbn\Str::isJson($argv[2])) {
            $this->_post = json_decode($argv[2], 1);
          } else {
            for ($i = 2, $iMax = \count($argv); $i < $iMax; $i++) {
              $this->_post[] = $argv[$i];
            }
          }
        }
      }

      return $this->_post;
    }
  }


  public function getGet()
  {
    if (!isset($this->_get)) {
      $this->_get = [];
      if (\count($_GET) > 0) {
        $this->_get = array_map(
          function ($a) {
            return bbn\Str::correctTypes($a);
          }, $_GET
        );
      }
    }

    return $this->_get;
  }


  public function getPost()
  {
    if (!isset($this->_post)) {
      if (self::$_input && \bbn\Str::isJson(self::$_input)) {
        $this->_post = json_decode(self::$_input, 1);
      }
      elseif (!empty($_POST)) {
        $this->_post = $_POST;
      }

      if (!$this->_post) {
        $this->_post = [];
      }
      else {
        $this->_has_post = true;
        $this->_post     = bbn\Str::correctTypes($this->_post);
        foreach ($this->_post as $k => $v) {
          if (X::indexOf($k, '_bbn_') === 0) {
            if (!defined(strtoupper(substr($k, 1)))) {
              define(strtoupper(substr($k, 1)), $v);
            }

            unset($this->_post[$k]);
          }
        }
      }
    }

    return $this->_post;
  }


  public function getFiles()
  {
    if (!isset($this->_files)) {
      $this->_files = [];
      // Rebuilding the $_FILES array into $this->_files in a more logical structure
      if (\count($_FILES) > 0) {
        // Some devices send multiple files with the same name
        $names = [];
        foreach ($_FILES as $n => $f) {
          if (\is_array($f['name'])) {
            $this->_files[$n] = [];
            foreach ($f['name'] as $i => $v) {
              while (\in_array($v, $names, true)) {
                if (!isset($j)) {
                  $j = 0;
                }

                $j++;
                $file = bbn\Str::fileExt($f['name'][$i], true);
                $v    = $file[0] . '_' . $j . '.' . $file[1];
              }

              $this->_files[$n][] = [
                'name' => $v,
                'tmp_name' => $f['tmp_name'][$i],
                'type' => $f['type'][$i],
                'error' => $f['error'][$i],
                'size' => $f['size'][$i],
              ];
              $names[]            = $v;
            }
          } else {
            $this->_files[$n] = $f;
          }
        }
      }

      /* @todo Maybe something for managing PUT requests
      else if (!empty(self::$_input) && !bbn\Str::isJson(self::$_input)) {
        $this->_files[] = [
          'name' => $v,
          'tmp_name' => $f['tmp_name'][$i],
          'type' => $f['type'][$i],
          'error' => $f['error'][$i],
          'size' => $f['size'][$i],
        ];
      }
      */
    }

    return $this->_files;
  }


  public function getParams()
  {
    return $this->_params;
  }


  public function getRequest(): ?string
  {
    if (self::$_initiated) {
      return $this->_url;
    }

    return null;
  }


  private static function _getHttpAcceptLanguageHeader(): ?string
  {
    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
      return trim($_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    return null;
  }


  private static function _getWeightedLocales($httpAcceptLanguageHeader)
  {
    if (strlen($httpAcceptLanguageHeader) == 0) {
      return [];
    }

    $weightedLocales = [];

    // We break up the string 'en-CA,ar-EG;q=0.5' along the commas,
    // and iterate over the resulting array of individual locales. Once
    // we're done, $weightedLocales should look like
    // [['locale' => 'en-CA', 'q' => 1.0], ['locale' => 'ar-EG', 'q' => 0.5]]
    foreach (explode(',', $httpAcceptLanguageHeader) as $locale) {
      // separate the locale key ("ar-EG") from its weight ("q=0.5")
      $localeParts = explode(';', $locale);

      $weightedLocale = ['locale' => $localeParts[0]];

      if (count($localeParts) == 2) {
        // explicit weight e.g. 'q=0.5'
        $weightParts = explode('=', $localeParts[1]);

        // grab the '0.5' bit and parse it to a float
        $weightedLocale['q'] = floatval($weightParts[1]);
      } else {
        // no weight given in string, ie. implicit weight of 'q=1.0'
        $weightedLocale['q'] = 1.0;
      }

      $weightedLocales[] = $weightedLocale;
    }

    return $weightedLocales;
  }

  /**
   * Sort by high to low `q` value
   */
  private static function _sortLocalesByWeight($locales)
  {
    usort($locales, function ($a, $b) {
      // usort will cast float values that we return here into integers,
      // which can mess up our sorting. So instead of subtracting the `q`,
      // values and returning the difference, we compare the `q` values and
      // explicitly return integer values.
      if ($a['q'] == $b['q']) {
        return 0;
      }

      if ($a['q'] > $b['q']) {
        return -1;
      }

      return 1;
    });

    return $locales;
  }

  private static function _initialize()
  {
    self::$_initiated = true;
    self::$_input     = file_get_contents('php://input');
  }


  private function setParams($path)
  {
    if (!isset($this->_params)) {
      $this->_params = [];
      $tmp           = explode('/', bbn\Str::parsePath($path));
      foreach ($tmp as $t) {
        if (!empty($t) || bbn\Str::isNumber($t)) {
          if (\in_array($t, bbn\Mvc::$reserved, true)) {
            $msg = X::_('The controller you are asking for contains one of these reserved words')
                .': '.implode(', ', bbn\Mvc::$reserved);
            throw new \Exception($msg);
          }

          $this->_params[] = $t;
        }
      }
    }
  }


  private static function _set_index(array $keys, array &$arr, $val)
  {
    $new_arr = &$arr;
    while (\count($keys)) {
      $var = array_shift($keys);
      if (!isset($new_arr[$var])) {
        $new_arr[$var] = \count($keys) ? [] : $val;
        $new_arr       = &$new_arr[$var];
      }
    }

    return $arr;
  }


  private static function _dot_to_array(&$val)
  {
    if (\is_array($val)) {
      $to_unset = [];
      foreach ($val as $key => $v) {
        $keys = explode('.', $key);
        if (\count($keys) > 1) {
          self::_set_index($keys, $val, $v);
          $to_unset[] = $key;
        }
      }

      foreach ($to_unset as $a) {
        unset($val[$a]);
      }
    }
  }


}
