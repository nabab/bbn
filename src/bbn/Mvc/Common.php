<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 31/12/2014
 * Time: 15:17
 */

namespace bbn\Mvc;

use bbn;
use bbn\X;
use bbn\Str;
use bbn\Mvc;

trait Common
{

  /**
   * The MVC class from which the controller is called
   * @var bbn\Mvc
   */
  private $_mvc;

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
   * @throws \Exception
   */
  private function error($msg)
  {
    $msg = "Error from ".\get_class($this).": ".$msg;
    $this->log($msg, 'mvc');
    throw new \Exception(X::_($msg));
  }


  public function log()
  {
    if (Mvc::getDebug()) {
      $ar = \func_get_args();
      X::log(\count($ar) > 1 ? $ar : $ar[0], 'mvc');
    }
  }


  public function pluginDataPath($plugin = null): ?string
  {
    if (($this->_plugin || $plugin) && \defined('BBN_DATA_PATH')) {
      return BBN_DATA_PATH.'plugins/'.$this->pluginName($plugin ?: $this->_plugin).'/';
    }

    return null;
  }


  public function getPlugins()
  {
    return $this->_mvc->getPlugins();
  }


  public function hasPlugin($plugin)
  {
    return $this->_mvc->hasPlugin($plugin);
  }


  public function isPlugin($plugin = null)
  {
    return $this->_mvc->isPlugin($plugin ?: $this->pluginName($this->_plugin));
  }


  public function pluginPath($plugin = null, $raw = false)
  {
    return $this->_mvc->pluginPath($plugin ?: $this->pluginName($this->_plugin), $raw);
  }


  public function pluginUrl($plugin = null)
  {
    return $this->_mvc->pluginUrl($plugin ?: $this->pluginName($this->_plugin));
  }


  public function pluginName($path = null)
  {
    return $this->_mvc->pluginName($path ?: $this->_path);
  }


  public function getCookie()
  {
    return $this->_mvc->getCookie();
  }


  public function getRoutes()
  {
    return $this->_mvc->getRoutes();
  }


  public function getAliases()
  {
    return $this->_mvc->getRoutes('alias');
  }


  public function getRoute($path, $mode, $root = null)
  {
    return $this->_mvc->getRoute($path, $mode, $root);
  }


  public function setLocale(string $locale)
  {
    return $this->_mvc->setLocale($locale);
  }


  public function getLocale(): ?string
  {
    return $this->_mvc->getLocale();
  }


  public function appPath($raw = false): string
  {
    return Mvc::getAppPath($raw);
  }


  public function libPath(): string
  {
    return Mvc::getLibPath();
  }


  public function dataPath(string $plugin = null): string
  {
    return Mvc::getDataPath().($plugin ? 'plugins/'.$plugin.'/' : '');
  }


  public function tmpPath(string $plugin = null): string
  {
    return Mvc::getTmpPath($plugin);
  }


  public function logPath(string $plugin = null): string
  {
    return Mvc::getLogPath($plugin);
  }


  public function cachePath(string $plugin = null): string
  {
    return Mvc::getCachePath($plugin);
  }


  public function contentPath(string $plugin = null): string
  {
    return Mvc::getContentPath($plugin);
  }


  public function userTmpPath(string $id_user = null, string $plugin = null):? string
  {
    return Mvc::getUserTmpPath($id_user, $plugin);
  }


  public function userDataPath(string $id_user = null, string $plugin = null):? string
  {
    return Mvc::getUserDataPath($id_user, $plugin);
  }

}
