<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 31/12/2014
 * Time: 15:17
 */

namespace bbn\Mvc;

use Exception;
use bbn\X;
use bbn\Str;
use bbn\Mvc;

trait Common
{
  /**
   * The MVC class from which the controller is called
   * @var Mvc
   */
  private Mvc $_mvc;

  /**
   * @var null|string If the controller is inside a plugin this property will be set to its name
   */
  private $_plugin;


  /**
   * This checks whether an argument used for getting controller, View or model - which are files - doesn't contain malicious content.
   *
   * @param string $p The request path <em>(e.g books/466565 or html/home)</em>
   * @return bool
   */
  private function checkPath()
  {
    $ar = \func_get_args();
    foreach ($ar as $a){
      $b = Str::parsePath($a, true);
      if (empty($b) && !empty($a)) {
        $this->error("The path $a is not an acceptable value");
        return false;
      }
    }

    return 1;
  }


  /**
   * @param $msg
   * @throws Exception
   */
  private function error($msg)
  {
    $msg = "Error from ".\get_class($this).": ".$msg;
    $this->log($msg, 'mvc');
    throw new Exception(X::_($msg));
  }


  /**
   * Log to a specific log with debug info
   */
  public function log(...$args)
  {
    if (Mvc::getDebug()) {
      X::log(\count($args) > 1 ? $args : $args[0], 'mvc');
    }
  }


  /**
   * Returns the path of a plugin in the data
   *
   * @param string $plugin
   * @return string|null
   */
  public function pluginDataPath(string|null $plugin = null): ?string
  {
    if ($this->_plugin || $plugin) {
      return $this->dataPath() . 'plugins/' . ($plugin ?: $this->pluginName($this->_plugin)) . '/';
    }

    return null;
  }


  /**
   * Returns the path of a plugin in the data
   *
   * @param string $plugin
   * @return string|null
   */
  public function pluginTmpPath(string|null $plugin = null): ?string
  {
    if ($this->_plugin || $plugin) {
      return $this->tmpPath().'plugins/' . ($plugin ?: $this->pluginName($this->_plugin)) . '/';
    }

    return null;
  }


  /**
   * Returns all the plugins available with their name, path and url
   * @return array|null
   */
  public function getPlugins(): ?array
  {
    return $this->_mvc->getPlugins();
  }


  /**
   * Checks whether a plugin is available
   *
   * @param string $plugin The plugin name
   * @return boolean
   */
  public function hasPlugin(string $plugin): bool
  {
    return $this->_mvc->hasPlugin($plugin);
  }


  /**
   * Checks whether a plugin exists
   *
   * @param string|null $plugin The plugin name
   * @return boolean
   */
  public function isPlugin(string|null $plugin = null): bool
  {
    return $this->_mvc->isPlugin($plugin ?: $this->pluginName($this->_plugin));
  }



  /**
   * Returns the path of a plugin from its root directory (app, lib...) based on its name
   *
   * @param string|null $plugin The plugin name
   * @param boolean $raw If true will not include `src`
   * @return string|null
   */
  public function pluginPath(string|null $plugin = null, $raw = false): ?string
  {
    return $this->_mvc->pluginPath($plugin ?: $this->pluginName($this->_plugin), $raw);
  }


  /**
   * Returns the url of a plugin based on its name
   *
   * @param string|null $plugin The plugin name
   * @return string|null
   */
  public function pluginUrl(string|null $plugin = null): ?string
  {
    return $this->_mvc->pluginUrl($plugin ?: $this->pluginName($this->_plugin));
  }


  /**
   * Returns the name of a plugin based on its path
   *
   * @param string|null $path The plugin path
   * @return string|null
   */
  public function pluginName($path = null): ?string
  {
    return $this->_mvc->pluginName($path ?: $this->_path);
  }


  public function getCookie()
  {
    return $this->_mvc->getCookie();
  }


  public function getDefault()
  {
    return $this->_mvc->getDefault();
  }

  public function getRoutes(): ?array
  {
    return $this->_mvc->getRoutes();
  }


  public function getAliases(): ?array
  {
    return $this->_mvc->getRoutes('alias');
  }


  public function getRoute(string $path, string $mode)
  {
    return $this->_mvc->getRoute($path, $mode);
  }


  public function setLocale(string $locale)
  {
    return $this->_mvc->setLocale($locale);
  }


  public function getLocale(): ?string
  {
    return $this->_mvc->getLocale();
  }


  public function isDev(): ?string
  {
    return !defined('BBN_ENV') || (constant('BBN_ENV') === 'dev');
  }


  public function isTest(): ?string
  {
    return !defined('BBN_ENV') || (constant('BBN_ENV') === 'test');
  }


  public function isProd(): ?string
  {
    return !defined('BBN_ENV') || (constant('BBN_ENV') === 'prod');
  }

  public function isNotProd(): ?string
  {
    return !defined('BBN_ENV') || (constant('BBN_ENV') !== 'prod');
  }


  public function appPath($raw = false): string
  {
    return Mvc::getAppPath($raw);
  }


  public function libPath(): string
  {
    return Mvc::getLibPath();
  }


  public function dataPath(string|null $plugin = null): string
  {
    return Mvc::getDataPath().($plugin ? 'plugins/'.$plugin.'/' : '');
  }


  public function tmpPath(string|null $plugin = null): string
  {
    return Mvc::getTmpPath($plugin);
  }


  public function logPath(string|null $plugin = null): string
  {
    return Mvc::getLogPath($plugin);
  }


  public function cachePath(string|null $plugin = null): string
  {
    return Mvc::getCachePath($plugin);
  }


  public function contentPath(string|null $plugin = null): string
  {
    return Mvc::getContentPath($plugin);
  }


  public function publicPath(): string
  {
    return Mvc::getPublicPath();
  }


  public function curPath(): string
  {
    return Mvc::getCurPath();
  }


  public function userTmpPath(string|null $id_user = null, string|null $plugin = null):? string
  {
    return Mvc::getUserTmpPath($id_user, $plugin);
  }


  public function userDataPath(string|null $id_user = null, string|null $plugin = null):? string
  {
    return Mvc::getUserDataPath($id_user, $plugin);
  }

}
