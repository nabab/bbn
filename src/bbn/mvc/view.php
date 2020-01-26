<?php
/**
 * Copyright (C) 2014 BBN
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace bbn\mvc;

use bbn;

class view{

  use common;


  /**
   * The full path to the view file
   * @var null|string
   */
  private $_file;
  /**
   * The local path for the view file
   * @var null|string
   */
  private $_path;
  /**
   * The file's extension
   * @var null|string
   */
  private $_ext;
  /**
   * Included files (only for LESS)
   * @var null|string
   */
  private $_checkers;
  /**
   * true of the view is part of a Vue component
   * @var bool
   */
  private $_component;
  /**
   * A JSON file for adding language to javascript.
   * @var null|string
   */
  private $_lang_file;
  /**
   * The content the view file.
   * @var null|string
   */
  private $_content;

  /**
   * This will call the initial build a new instance. It should be called only once from within the script. All subsequent calls to controllers should be done through $this->add($path).
   *
   * @param object | string $db The database object in the first call and the controller path in the calls within the class (through Add)<em>(e.g books/466565 or html/home)</em>
   * @param string | object $parent The parent controller</em>
   * @return bool
   */
  public function __construct(array $info)
  {
    if ( router::is_mode($info['mode']) ){
      $this->_path = $info['path'];
      $this->_ext = $info['ext'];
      $this->_file = $info['file'];
      $this->_checkers = $info['checkers'] ?? [];
      $this->_lang_file = $info['i18n'] ?? null;
      $this->_plugin = $info['plugin'] ?? null;
      $this->_component = $info['component'] ?? false;
    }
  }

  public function check(){
    return !empty($this->_file);
  }

  /**
   * Processes the controller and checks whether it has been routed or not.
   *
   * @return bool
   */
  public function get(array $data=null)
  {
    if ( $this->check() ){
      if ( \is_null($this->_content) ){
        $this->_content = file_get_contents($this->_file);
      }
      if ( empty($this->_content) ){
        return '';
      }
      if ( $this->_checkers ){
        $st = '';
        foreach ( $this->_checkers as $chk ){
          $st .= file_get_contents($chk);
        }
        $this->_content = $st.$this->_content;
      }
      switch ( $this->_ext ){
        case 'js':
          // Language variables inclusions in the javascript files
          if ( !empty($this->_lang_file) ){
            $tmp = json_decode(file_get_contents($this->_lang_file), true);
            $path = $this->_plugin && !$this->_component ? substr($this->_path, \strlen($this->_plugin) + 1) : $this->_path;
            //die(var_dump(count($tmp), 'components/'.$this->path.'/'.$this->path, $tmp));
            $idx = $this->_component ? 'components/' : 'mvc/';
            //die(var_dump($idx.$path, $this->_path, $this->_plugin, $this->_component, array_keys($tmp)));
            if ( $translations = ($tmp[$idx.$path] ?? null) ){
              $json = json_encode($translations);
              $tmp = <<<JAVASCRIPT
((data) => {
  bbn.fn.autoExtend("lng", $json)
})();
JAVASCRIPT;
              $this->_content = $tmp.$this->_content;
            }
            unset($tmp, $translations, $json);
          }
          $tmp = false;
          try {
            if (!defined('BBN_IS_DEV') || BBN_IS_DEV) {
              $tmp = \JShrink\Minifier::minify($this->_content, ['flaggedComments' => false]);
            }
          }
          catch ( \RuntimeException $e ){
            \bbn\x::log([$e->getMessage(), $this->_file], 'js_shrink');
          }
          return $tmp ?: $this->_content;
        case 'coffee':
          return $this->_content;
        case 'css':
          return $this->_content;
        case 'less':
          $less = new \lessc();
          return $less->compile($this->_content);
        case 'scss':
          $scss = new \Leafo\ScssPhp\Compiler();
          return $scss->compile($this->_content);
        case 'html':
          return empty($data) ? $this->_content : bbn\tpl::render($this->_content, $data);
        case 'php':
          $dir = getcwd();
          chdir(dirname($this->_file));
          if ( $this->_plugin ){
            $router = router::get_instance();
            $router->apply_locale($this->_plugin);
          }
          $r = bbn\mvc::include_php_view($this->_file, $this->_content, $data ?: []);
          chdir($dir);
          return $r;
      }
    }
    return false;
  }
}
