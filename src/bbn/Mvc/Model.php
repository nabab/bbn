<?php
namespace bbn\Mvc;

use bbn\X;
use bbn\Db;
use bbn\Mvc;
use bbn\Models\Cls\Db as DbClass;
use bbn\Models\Tts\Cache;

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

class Model extends DbClass
{

  use Common;
  use Cache;

  /**
   * The file as being requested
   * @var null|string
   */
  private $_file;

  /**
   * The controller instance requesting the model
   * @var null|Controller
   */
  private $_ctrl;

  /**
   * The path as being requested
   * @var null|string
   */
  private $_path;

  /**
   * Included files
   * @var null|Controller
   */
  private $_checkers;

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

  public
    /**
     * The database connection instance
     * @var null|Db
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
   * @param null|Db $db The database object in the first call and the controller path in the calls within the class (through Add)<em>(e.g books/466565 or html/home)</em>
   * @param array $info The full path to the model's file
   * @param Controller $ctrl The parent controller
   * @param Mvc $mvc The parent MVC
   * @throws \Exception
   */
  public function __construct(null|Db $db, array $info, Controller $ctrl, Mvc $mvc)
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
        $this->_path        = $info['path'];
        $this->_file        = $info['file'];
        $this->_checkers    = $info['checkers'] ?? [];
        $this->_plugin_name = $info['plugin_name'] ?? null;
        $this->_plugin      = $info['plugin'] ?? null;
      }
    }
    else{
        $this->error("The model ". ($info['path'] ?? null) ." doesn't exist");
    }
  }

  public function getController(): Controller
  {
    return $this->_ctrl;
  }


  /**
   * @param array|null $vars
   * @param bool $check_empty
   * @return bool
   */
  public function checkAction(array|null $vars = null, bool $check_empty = false): bool
  {
    if (isset($this->data['res'], $this->data['res'])) {
      if (is_array($vars)) {
        return X::hasProps($this->data, $vars, $check_empty);
      }

      return true;
    }

    return false;
  }


  public function addController()
  {
    return $this->_ctrl->add(...\func_get_args());
  }


  /**
   * @param string $path
   * @param string $type
   * @return bool
   */
  public function isControlledBy(string $path, string $type = 'public'): bool
  {
    if ($this->_ctrl && ($this->_ctrl->getPath() === $path)) {
      if ($type === 'cli') {
        return $this->_mvc->isCli();
      }

      if ($type === $this->_ctrl->getMode()) {
        return true;
      }
    }

    return false;
  }


  /**
   * @return false|string|null
   */
  public function getControllerPath()
  {
    return $this->_ctrl ? $this->_ctrl->getPath() : false;
  }


  /**
   * @param string $var
   * @param bool $check_empty
   * @return bool
   */
  public function hasVar(string $var, bool $check_empty = false): bool
  {
    return X::hasProp($this->data, $var, $check_empty);
  }


  /**
   * @param array $vars
   * @param bool $check_empty
   * @return bool
   */
  public function hasVars(array $vars, bool $check_empty = false): bool
  {
    return X::hasProps($this->data, $vars, $check_empty);
  }


  /**
   * @param $plugin_path
   * @return self
   */
  public function registerPluginClasses($plugin_path): self
  {
    $this->_ctrl->registerPluginClasses($plugin_path);
    return $this;
  }


  /**
   * @param array|null $data
   * @return array|null
   */
  public function get(array|null $data = null): ?array
  {
    if (\is_null($data)) {
      $data = [];
    }

    $this->data = $data;
    if ($this->_plugin) {
      $router = Router::getInstance();
      if ($textDomain = $router->getLocaleDomain($this->_plugin_name)) {
        $oldTextDomain = textdomain(null);
        if ($textDomain !== $oldTextDomain) {
          textdomain($textDomain);
        }
        else {
          unset($oldTextDomain);
        }
      }
    }

    foreach ($this->_checkers as $appui_checker_file) {
      // If a checker file returns false, the controller is not processed
      // The checker file can define data and inc that can be used in the subsequent controller
      Mvc::includeModel($appui_checker_file, $this);
    }

    $res = Mvc::includeModel($this->_file, $this);
    if (!empty($oldTextDomain)) {
      textdomain($oldTextDomain);
    }

    return $res ?: null;
  }

  /**
   * This will get a the content of a file located within the controller data path.
   *
   * @return string|false
   */
  public function getContent(): ?string
  {
    return $this->_ctrl->getContent(...\func_get_args());
  }


  /**
   * This will get the model. There is no order for the arguments.
   *
   * @return array|null
   * @throws \Exception
   */
  public function getModel(): ?array
  {
    return $this->_ctrl->getModel(...\func_get_args());
  }


  public function getCustomModelGroup(string $path, string $plugin, array|null $data = null)
  {
    return $this->_ctrl->getCustomModelGroup(...\func_get_args());
  }


  public function getSubpluginModelGroup(string $path, string $plugin_from, string $plugin_for, array|null $data = null)
  {
    return $this->_ctrl->getSubpluginModelGroup(...\func_get_args());
  }


  public function getDataError($propNames, ?string $errorMsg = null, bool $checkEmpty = true): ?array
  {
    if (!$errorMsg) {
      $errorMsg = "A value must be given for the field %s";
    }

    $propNames = (array)$propNames;
    foreach ($propNames as $p) {
      if (!$this->hasData($p, $checkEmpty)) {
        return [
          'success' => false,
          'error'  => X::_($errorMsg, $p)
        ];
      }
    }

    return null;
  }


  /**
   * This will get the cached model. There is no order for the arguments.
   *
   * @return array|null
   * @throws \Exception
   */
  public function getCachedModel(): ?array
  {
    return $this->_ctrl->getCachedModel(...\func_get_args());
  }


  /**
   * Retrieves a model of a the plugin.
   *
   * @param $path
   * @param array $data
   * @param string|null $plugin
   * @param int $ttl
   * @return array|null
   */
  public function getPluginModel($path, array $data = [], string|null $plugin = null, int $ttl = 0): ?array
  {
    return $this->_ctrl->getPluginModel(...\func_get_args());
  }


  /**
   * Get a sub plugin model (a plugin inside the plugin directory of another plugin).
   *
   * @param $path
   * @param array $data
   * @param string|null $plugin
   * @param string $subplugin
   * @param int $ttl
   * @return array|null
   */
  public function getSubpluginModel($path, array $data, string|null $plugin, string $subplugin, int $ttl = 0): ?array
  {
    return $this->_ctrl->getSubpluginModel(...\func_get_args());
  }


  /**
   * Returns true if the subplugin model exists.
   *
   * @param string $path
   * @param string $plugin
   * @param string $subplugin
   * @return bool
   */
  public function hasSubpluginModel(string $path, string $plugin, string $subplugin): bool
  {
    return $this->_ctrl->hasSubpluginModel(...\func_get_args());
  }


  /**
   * Returns true if plugin exists and false otherwise.
   *
   * @return bool
   */
  public function hasPlugin(): bool
  {
    return $this->_ctrl->hasPlugin(...\func_get_args());
  }


  /**
   * Returns true if plugin exists and false otherwise.
   *
   * @return bool
   */
  public function isPlugin(): bool
  {
    return $this->_ctrl->isPlugin(...\func_get_args());
  }


  /**
   * Returns the path of the given plugin.
   *
   * @return string|null
   */
  public function pluginPath(): ?string
  {
    return $this->_ctrl->pluginPath(...\func_get_args());
  }


  /**
   * Returns the URL part of the given plugin.
   *
   * @return string|null
   */
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

      return X::hasProps($this->data, (array)$idx, $check_empty);
    }


  /**
   * Sets the data. Chainable. Should be useless as $this->data is public. Chainable.
   *
   * @param array $data
   * @return self
   */
    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }


  /**
   * Merges the existing data if there is with this one. Chainable.
   *
   * @return self
   */
    public function addData(array ...$data): self
    {
      $ar = \func_get_args();
      foreach ($data as $d){
        if (\is_array($d)) {
          $this->data = $this->hasData() ? array_merge($this->data, $d) : $d;
        }
      }

        return $this;
    }


    public function setDefaultData(array $data): self
    {
      X::extendOut($this->data, $data);
      return $this;
    }


  /**
   * Generates cache name from the given data.
   *
   * @param $data
   * @param string $spec
   * @return string|null
   */
    protected function _cache_name($data, string $spec = ''): ?string
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


  /**
   * Sets a cache from the given data.
   *
   * @param array|null $data
   * @param string $spec
   * @param int $ttl
   * @return void
   */
  public function setCache(array|null $data = null, string $spec = '', $ttl = 10)
  {
    if ($this->_path) {
      $d = $this->get($data);
      $this->cacheSet($this->_cache_name($data, $spec), '', $d, $ttl);
    }
  }


  /**
   * Deletes a cache with the given data.
   *
   * @param array|null $data
   * @param string $spec
   */
  public function deleteCache(array|null $data = null, $spec = '', string $path = '')
  {
    if ($cn = $this->_cache_name($data, $spec)) {
      if ($path) {
        $cn = 'models/' . $path . substr($cn, strlen('models/' . $this->_path));
      }

      return $this->cache_engine->deleteAll($cn, '');
    }
  }


  /**
   * Returns the cache for the given item, but if expired or absent creates it before by running the closure.
   *
   * @param array|null $data
   * @param string $spec
   * @param int $ttl
   * @return array|null
   */
  public function getFromCache(array|null $data = null, string $spec = '', int $ttl = 10)
  {
    $model =& $this;
    return $this->getSetFromCache(
      function () use (&$model, $data) {
        return $model->get($data);
      }, $data, $spec, $ttl
    );
  }


  /**
   * Returns the cache for the given item, but if expired or absent creates it before by running the provided function.
   *
   * @param \Closure $fn
   * @param array|null $data
   * @param string $spec
   * @param int $ttl
   * @return array|null
   */
  public function getSetFromCache(\Closure $fn, array|null $data = null, string $spec = '', int $ttl = 10): ?array
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
