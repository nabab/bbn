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

namespace bbn\Mvc;

use bbn;
use bbn\X;
use bbn\Mvc;

class View
{

  use Common;

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
   * @var null|array
   */
  private $_checkers;

  /**
   * true of the view is part of a Vue component
   * @var bool
   */
  private $_component;

  /**
   * true of the view is part of a Vue component
   * @var bool
   */
  private $_component_name;

  /**
   * The URL path to the plugin.
   * @var null|string
   */
  private $_plugin;

  /**
   * The plugin name.
   * @var null|string
   */
  private $_plugin_name;

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
   * @param array $info
   */
  public function __construct(array $info)
  {
    if (!empty($info['mode']) && Router::isMode($info['mode'])) {
      $this->_path           = $info['path'];
      $this->_ext            = $info['ext'];
      $this->_file           = $info['file'];
      $this->_checkers       = $info['checkers'] ?? [];
      $this->_lang_file      = $info['i18n'] ?? null;
      $this->_plugin         = $info['plugin'] ?? null;
      $this->_plugin_name    = $info['plugin_name'] ?? null;
      $this->_component      = $info['component'] ?? null;
      $this->_component_name = $info['component_name'] ?? null;
    }

    $this->_mvc = Mvc::getInstance();
  }


  /**
   * @return bool
   */
  public function check()
  {
    return !empty($this->_file);
  }


  /**
   * Processes the controller and checks whether it has been routed or not.
   *
   * @param array|null $data
   * @return string
   */
  public function get(?array $data=null)
  {
    if ($this->check()) {
      if (\is_null($this->_content) && is_file($this->_file)) {
        $this->_content = file_get_contents($this->_file);
      }

      if (empty($this->_content)) {
        return '';
      }

      if ($this->_checkers) {
        $st = '';
        foreach ($this->_checkers as $chk){
          if (is_file($chk)) {
            $st .= file_get_contents($chk);
          }
        }

        $this->_content = $st.$this->_content;
      }

      switch ($this->_ext){
        case 'js':
          // Language variables inclusions in the javascript files
          if (!empty($this->_lang_file) && is_file($this->_lang_file)) {
            $tmp  = json_decode(file_get_contents($this->_lang_file), true);
            $path = $this->_plugin && !$this->_component ? substr($this->_path, \strlen($this->_plugin) + 1) : $this->_path;
            //die(var_dump(count($tmp), 'components/'.$this->path.'/'.$this->path, $tmp));
            $idx = $this->_component ? 'components/' : 'mvc/';
            //die(var_dump($idx.$path, $tmp));
            //die(var_dump($idx.$path, $this->_path, $this->_plugin, $this->_component, array_keys($tmp)));
            //die(var_dump($idx.$path, $tmp));
            if ($translations = ($tmp[$idx.$path] ?? null)) {
              $json           = json_encode($translations);
              $tmp            = <<<JAVASCRIPT
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
            if (!defined('BBN_IS_DEV') || !BBN_IS_DEV) {
              $tmp = \JShrink\Minifier::minify($this->_content, ['flaggedComments' => false]);
            }
          }
          catch (\RuntimeException $e){
            \bbn\X::log([$e->getMessage(), $this->_file], 'js_shrink');
          }
          return $tmp ?: $this->_content;
        case 'css':
          return $this->_content;
        case 'less':
          if ($this->_component_name) {
            $this->_content = '@componentName: '.$this->_component_name.';'.PHP_EOL.$this->_content;
          }

          $less = new \bbn\Compilers\Less();
          return $less->compile($this->_content);
        case 'scss':
          $scss = new \ScssPhp\ScssPhp\Compiler();
          return $scss->compile($this->_content);
        case 'html':
          return empty($data) ? $this->_content : bbn\Tpl::render($this->_content, $data);
        case 'php':
          $dir = getcwd();
          /** @todo explain why */
          chdir(X::dirname($this->_file));
          if ($this->_plugin &&
              ($router = Router::getInstance()) &&
              ($textDomain = $router->getLocaleDomain($this->_plugin_name))
          ) {
            $oldTextDomain = textdomain(null);
            if ($textDomain !== $oldTextDomain) {
              textdomain($textDomain);
            }
            else {
              unset($oldTextDomain);
            }
          }

          $r = bbn\Mvc::includePhpView($this->_file, $this->_content, $data ?: []);
          if (!empty($oldTextDomain)) {
            textdomain($oldTextDomain);
          }

          chdir($dir);
          return $r;
      }
    }

    return false;
  }


}
