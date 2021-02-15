<?php
namespace bbn\Mvc;

use bbn;

/**
 * Model View Controller Class
 *
 *
 * This class will route a request to the according model and/or view through its controller.
 * A model and a view can be automatically associated if located in the same directory branch with the same name than the controller in their respective locations
 * A view can be directly imported in the controller through this very class
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  MVC
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.2r89
 * @todo Merge the output objects and combine JS strings.
 * @todo Stop to rely only on sqlite and offer file-based or any db-based solution.
 * @todo Look into the check function and divide it
 */

class Model extends bbn\Models\Cls\Db
{

  use Common;
  use bbn\Models\Tts\Cache;

  /**
   * The file as being requested
   * @var null|string
   */
  private $_file;

  /**
   * The controller instance requesting the model
   * @var null|string
   */
  private $_ctrl;

  /**
   * The path as being requested
   * @var null|string
   */
  private $_path;

  /**
   * Included files
   * @var null|string
   */
  private $_checkers;

  public
    /**
     * The database connection instance
     * @var null|bbn\Db
     */
    $db,
    /**
     * The data model
     * @var null|array
     */
    $data,
  /**
   * An external object that can be filled after the object creation and can be used as a global with the function add_inc
   * @var \stdClass
   */
    $inc;


    /**
     * Models are always recreated and reincluded, even if they have from the same path
   * They are all created from bbn\Mvc::get_model
     *
     * @param null|bbn\Db $db   The database object in the first call and the controller path in the calls within the class (through Add)<em>(e.g books/466565 or html/home)</em>
   * @param array       $info The full path to the model's file
   * @param controller  $ctrl The parent controller
   * @param controller  $mvc  The parent MVC
     */
  public function __construct(bbn\Db $db = null, array $info, Controller $ctrl, bbn\Mvc $mvc)
  {
    if (isset($info['path']) && $this->checkPath($info['path'])) {
      if ($db) {
        parent::__construct($db);
      }

      $this->cacheInit();
      $this->_ctrl = $ctrl;
      $this->_mvc  = $mvc;
        $this->inc = &$mvc->inc;
      if (is_file($info['file'])) {
        $this->_path     = $info['path'];
        $this->_file     = $info['file'];
        $this->_checkers = $info['checkers'] ?? [];
      }
    }
    else{
        $this->error("The model $info[path] doesn't exist");
    }
  }


  public function checkAction(array $vars = null, bool $check_empty = false): bool
  {
    if (isset($this->data['res'], $this->data['res'])) {
      if (is_array($vars)) {
        return bbn\X::hasProps($this->data, $vars, $check_empty);
      }

      return true;
    }

    return false;
  }


  public function isControlledBy(string $path, string $type = 'public'): bool
  {
    if ($this->_ctrl && ($this->_ctrl->getPath() === $path)) {
      if ($type === 'cli') {
        return $this->mvc->isCli();
      }

      if ($type === $this->_ctrl->mode) {
        return true;
      }
    }

    return false;
  }


  public function getControllerPath()
  {
    return $this->_ctrl ? $this->_ctrl->getPath() : false;
  }


  public function hasVar(string $var, bool $check_empty = false): bool
  {
    return bbn\X::hasProp($this->data, $var, $check_empty);
  }


  public function hasVars(array $vars, bool $check_empty = false): bool
  {
    return bbn\X::hasProps($this->data, $vars, $check_empty);
  }


  public function registerPluginClasses($plugin_path): self
  {
    $this->_ctrl->registerPluginClasses($plugin_path);
    return $this;
  }


  public function get(array $data = null): ?array
  {
    if (\is_null($data)) {
      $data = [];
    }

    $this->data = $data;
    if ($this->_plugin) {
      $router = Router::getInstance();
      if ($textDomain = $router->getLocale($this->_plugin)) {
        $oldTextDomain = textdomain(null);
        if ($textDomain !== $oldTextDomain) {
          textdomain($textDomain);
        }
        else {
          unset($oldTextDomain);
        }
      }
    }

    $res = bbn\Mvc::includeModel($this->_file, $this);
    if (!empty($oldTextDomain)) {
      textdomain($oldTextDomain);
    }

    return $res ?: null;
  }


  public function getContent(): ?string
  {
    return $this->_ctrl->getContent(...\func_get_args());
  }


  public function getModel(): ?array
  {
    return $this->_ctrl->getModel(...\func_get_args());
  }


  public function getCachedModel(): ?array
  {
    return $this->_ctrl->getCachedModel(...\func_get_args());
  }


  public function getPluginModel($path, $data = [], string $plugin = null, $ttl = 0): ?array
  {
    return $this->_ctrl->getPluginModel(...\func_get_args());
  }


  public function getSubpluginModel($path, $data = [], string $plugin = null, string $subplugin, $ttl = 0): ?array
  {
    return $this->_ctrl->getSubpluginModel(...\func_get_args());
  }


  public function hasSubpluginModel(string $path, string $plugin, string $subplugin): bool
  {
    return $this->_ctrl->hasSubpluginModel(...\func_get_args());
  }


  public function hasPlugin(): bool
  {
    return $this->_ctrl->hasPlugin(...\func_get_args());
  }


  public function isPlugin(): bool
  {
    return $this->_ctrl->isPlugin(...\func_get_args());
  }


  public function pluginPath(): ?string
  {
    return $this->_ctrl->pluginPath(...\func_get_args());
  }


  public function pluginUrl(): ?string
  {
    return $this->_ctrl->pluginUrl(...\func_get_args());
  }


  /**
   * Adds a property to the MVC object inc if it has not been declared.
   *
   * @return self
   */
  public function addInc($name, $obj): self
  {
    $this->_mvc->addInc($name, $obj);
    return $this;
  }


  /**
     * Checks if data exists or if a specific index exists in the data
     *
     * @return bool
     */
  public function hasData($idx = null, $check_empty = false): bool
  {
    if (!\is_array($this->data)) {
      return false;
    }

    if (\is_null($idx)) {
      return !empty($this->data);
    }

    return \bbn\X::hasProps($this->data, (array)$idx, $check_empty);
  }


    /**
     * Sets the data. Chainable. Should be useless as $this->data is public. Chainable.
     *
     * @param array $data
     * @return void
     */
  public function setData(array $data): self
  {
      $this->data = $data;
      return $this;
  }


    /**
     * Merges the existing data if there is with this one. Chainable.
     *
     * @return void
     */
  public function addData(array $data): self
  {
      $ar = \func_get_args();
    foreach ($ar as $d){
      if (\is_array($d)) {
        $this->data = $this->hasData() ? array_merge($this->data,$d) : $d;
      }
    }

      return $this;
  }


  protected function _cache_name($data, $spec = ''): ?string
  {
    if ($this->_path) {
      $cn = 'models/'.$this->_path;
      if ($spec) {
        $cn .= '/'.$spec;
      }

      if ($data) {
        if (is_array($data)) {
          ksort($data);
        }

        $cn .= '/'.md5(serialize($data));
      }

      return $cn;
    }

    return null;
  }


  public function setCache(array $data = null, $spec='', $ttl = 10)
  {
    if ($this->_path) {
      $d = $this->get($data);
      $this->cacheSet($this->_cache_name($data, $spec), '', $d, $ttl);
    }
  }


  public function deleteCache(array $data = null, $spec='')
  {
    if ($cn = $this->_cache_name($data, $spec)) {
      $this->cacheDelete($cn, '');
    }
  }


  public function getFromCache(array $data = null, ?string $spec = '', $ttl = 10)
  {
    $model =& $this;
    return $this->getSetFromCache(
      function () use (&$model, $data) {
        return $model->get($data);
      }, $data, $spec, $ttl
    );
    return null;
  }


  public function getSetFromCache(\Closure $fn, array $data = null, $spec = '', $ttl = 10): ?array
  {
    if ($cn = $this->_cache_name($data, $spec)) {
      return $this->cacheGetSet($fn, $cn, '', $ttl) ?: null;
    }

    return null;
  }


  public function applyLocale($plugin)
  {
    return $this->_mvc->applyLocale($plugin);
  }


}
