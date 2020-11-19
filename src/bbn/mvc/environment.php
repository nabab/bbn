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

namespace bbn\mvc;

use bbn;


class environment
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
   * The mode of the output (doc, html, json, txt, xml...)
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

  private static function _initialize()
  {
    self::$_initiated = true;
    self::$_input = file_get_contents('php://input');
  }

  private function set_params($path)
  {
    if (!isset($this->_params)) {
      $this->_params = [];
      $tmp = explode('/', bbn\str::parse_path($path));
      foreach ($tmp as $t) {
        if (!empty($t) || bbn\str::is_number($t)) {
          if (\in_array($t, bbn\mvc::$reserved, true)) {
            $msg = _('The controller you are asking for contains one of these reserved words')
                .': '.implode(', ', bbn\mvc::$reserved);
            throw new \Exception($msg);
          }

          $this->_params[] = $t;
        }
      }
    }
  }

  public static function get_input()
  {
    return self::$_input;
  }

  /**
   * Change the output mode (content-type)
   *
   * @param $mode
   * @return string $this->_mode
   */
  public function set_mode($mode)
  {
    if (router::is_mode($mode)) {
      $this->_mode = $mode;
    }
    return $this->_mode;
  }

  private function _init()
  {
    // When using CLI a first parameter can be used as route,
    // a second JSON encoded can be used as $this->_post
    if (php_sapi_name() === 'cli') {
      $this->_mode = 'cli';
      $this->get_cli();
    }
    // Non CLI request
    else {
      if (!isset($this->_post)) {
        $this->get_post();
      }
      if ($this->_has_post) {
        self::_dot_to_array($this->_post);
        /** @todo Remove the json parameter from the bbn.js functions */
        $this->set_mode(BBN_DEFAULT_MODE);
      }
      else if (\count($_FILES)) {
        $this->set_mode(BBN_DEFAULT_MODE);
      }
      // If no post, assuming to be a DOM document
      else {
        $this->set_mode('dom');
      }
      if (isset($_SERVER['REQUEST_URI'])) {
        $current = $_SERVER['REQUEST_URI'];
      }
      if (
        isset($current) && (BBN_CUR_PATH === '/' || strpos($current, BBN_CUR_PATH) !== false)
      ) {
        $url = explode("?", urldecode($current))[0];
        if (BBN_CUR_PATH === '/') {
          $this->set_params($url);
        } else {
          $this->set_params(substr($url, \strlen(BBN_CUR_PATH)));
        }
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

  public function set_prepath($path)
  {
    $path = bbn\x::remove_empty(explode('/', $path));
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
  public function is_cli()
  {
    if (!isset($this->_cli)) {
      $this->_cli = bbn\x::is_cli();
      if ($this->_cli) {
        $opt = getopt('', ['cli']);
        if (isset($opt['cli'])) {
          $this->_cli = 'direct';
        }
      }
    }
    return $this->_cli;
  }

  public function get_url()
  {
    return $this->_url;
  }

  public function simulate($url, $post = false, $arguments = null)
  {
    unset($this->_params);
    $this->set_params($url . (empty($arguments) ? '' : '/' . implode('/', $arguments)));
    $this->_post = $post ?: null;
    $this->_init();
    $this->_url = $url;
  }

  public function get_mode()
  {
    return $this->_mode;
  }

  public function get_cli()
  {
    global $argv;
    if ($this->is_cli()) {
      if ($this->is_cli() === 'direct') {
        array_shift($argv);
      }
      $this->_post = [];
      if (isset($argv[1])) {
        $this->set_params($argv[1]);
        if (isset($argv[2])) {
          if (!isset($argv[3]) && \bbn\str::is_json($argv[2])) {
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

  public function get_get()
  {
    if (!isset($this->_get)) {
      $this->_get = [];
      if (\count($_GET) > 0) {
        $this->_get = array_map(function ($a) {
          return bbn\str::correct_types($a);
        }, $_GET);
      }
    }
    return $this->_get;
  }

  private static function _set_index(array $keys, array &$arr, $val)
  {
    $new_arr = &$arr;
    while (\count($keys)) {
      $var = array_shift($keys);
      if (!isset($new_arr[$var])) {
        $new_arr[$var] = \count($keys) ? [] : $val;
        $new_arr = &$new_arr[$var];
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

  public function get_post()
  {
    if (!isset($this->_post)) {
      if (self::$_input && \bbn\str::is_json(self::$_input)) {
        $this->_post = json_decode(self::$_input, 1);
      } else if (!empty($_POST)) {
        $this->_post = $_POST;
      }
      if (!$this->_post) {
        $this->_post = [];
      } else {
        $this->_has_post = true;
        $this->_post = bbn\str::correct_types($this->_post);
        foreach ($this->_post as $k => $v) {
          if (\bbn\x::indexOf($k, '_bbn_') === 0) {
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

  public function get_files()
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
                $file = bbn\str::file_ext($f['name'][$i], true);
                $v = $file[0] . '_' . $j . '.' . $file[1];
              }
              $this->_files[$n][] = [
                'name' => $v,
                'tmp_name' => $f['tmp_name'][$i],
                'type' => $f['type'][$i],
                'error' => $f['error'][$i],
                'size' => $f['size'][$i],
              ];
              $names[] = $v;
            }
          } else {
            $this->_files[$n] = $f;
          }
        }
      }
      /* @todo Maybe something for managing PUT requests
      else if (!empty(self::$_input) && !bbn\str::is_json(self::$_input)) {
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

  public function get_params()
  {
    return $this->_params;
  }

  public function get_request(): ?string
  {
    if (self::$_initiated) {
      return $this->_url;
    }
    return null;
  }
}
