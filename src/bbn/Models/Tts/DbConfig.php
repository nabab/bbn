<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 05/11/2016
 * Time: 02:47
 */

namespace bbn\Models\Tts;

use Exception;
use bbn\X;
use bbn\Str;
use bbn\Cache;
use bbn\Mvc;
use bbn\Mvc\Controller;
use bbn\Mvc\Model;
use ReflectionProperty;
use function array_key_exists;
use function count;
use function is_array;

trait DbConfig
{
  use Event;
  /** @var array */
  protected static $_isInitClassCfg = [];

  protected static array $dbConfigTableClasses;

  /** @var array */
  protected $fields;

  protected $class_cfg;

  /** @var string */
  protected $class_table;

  /** @var string */
  protected $class_table_index;

  protected static array $dbConfigCfg = [];

  public static function isDbConfigInit(): bool
  {
    return !empty(self::$_isInitClassCfg[static::class]);
  }

  public static function getDefaultClassCfg(): ?array
  {
    return static::$default_class_cfg ?? [];
  }

  /**
   * Returns the class configuration.
   * 
   * @return mixed
   */
  public function getClassCfg()
  {
    return $this->class_cfg;
  }


  /**
   * Returns the fields of the main table.
   *
   * @return array
   */
  public function getFields()
  {
    return $this->fields;
  }

  public function getClassTable(): string
  {
    return $this->class_table;
  }

  public function getClassTableIndex(): string
  {
    return $this->class_table_index;
  }

  private static function dbConfigInit(?array $cfg = null): array
  {
    if (static::isDbConfigInit()) {
      return static::$dbConfigCfg[static::class];
    }

    $arr = [];
    $parent = get_parent_class(static::class);
    while ($parent && method_exists($parent, 'getDefaultClassCfg')) {
      if ($tmp = $parent::getDefaultClassCfg()) {
        array_unshift($arr, $tmp);
      }

      $parent = get_parent_class($parent);
    }

    if (isset(static::$default_class_cfg)) {
      $arr[] = static::$default_class_cfg;
    }
    
    if (!count($arr)) {
      throw new Exception(X::_("The class %s is not configured properly to work with trait DbActions: no configuration available", static::class));
    }

    $cfg = count($arr) === 1 ? $arr[0] : array_merge(...$arr);
    if (!isset($cfg['tables'])) {
      throw new Exception(X::_("The class %s is not configured properly to work with trait DbActions: no tables", static::class));
    }

    $table_index = array_flip($cfg['tables'])[$cfg['table']];
    if (!$table_index || !isset($cfg['tables'], $cfg['table'], $cfg['arch'], $cfg['arch'][$table_index])) {
      throw new Exception(X::_("The class %s is not configured properly to work with trait DbActions: invalid table configuration", static::class));
    }

    // We completely replace the table structure, no merge
    $props = [];
    foreach ($cfg['arch'] as $t => &$fields){
      if (empty($cfg['table_index']) && isset($cfg['tables'][$t]) && ($cfg['tables'][$t] === $cfg['table']))  {
        $cfg['table_index'] = $t;
      }

      foreach ($fields as $f => $it) {
        if (is_array($it)) {
          $props[$t][$f] = $it;
          $fields[$f] = $it['name'] ?? $f;
        }
      }
    }
    unset($fields);
    if (!empty($props)) {
      $cfg['props'] = $props;
    }

    static::$dbConfigCfg[static::class] = $cfg;
    static::$_isInitClassCfg[static::class] = true;
    return $cfg;
  }

  /**
   * Sets the class configuration as defined in self::default_class_cfg
   * @return $this
   */
  protected function initClassCfg()
  {
    if (!isset($this->class_cfg)) {
      $this->class_cfg = static::dbConfigInit();
      $cfg = $this->class_cfg;
      // The selection comprises the defined fields of the users table
      // Plus a bunch of user-defined additional fields in the same table
      $this->fields = $cfg['arch'][$cfg['table_index']];
      $this->class_table_index = $cfg['table_index'];
      $this->class_table = $cfg['table'];
    }

    return $this;
  }


  public function isInitClassCfg(): bool
  {
    return static::$_isInitClassCfg[static::class] ?? false;
  }

  public function dbConfigCheck(): void
  {
    if (!$this->isInitClassCfg()) {
      throw new Exception(X::_("The class %s is not configured properly has not been initiated for trait DbConfig", get_class($this))) ;
    }
  }

  public static function dbConfigGetTableClasses(
    null|Mvc|Controller|Model $mvc = null,
  ): array {
    if (isset(self::$dbConfigTableClasses)) {
      return self::$dbConfigTableClasses;
    }

    if (!$mvc) {
      $mvc = Mvc::getInstance();
    }

    $cache = Cache::getEngine();
    if (true || !($cached = $cache->get("bbn_dbconfig_cache_init"))) {
      $res = include $mvc->libPath() . "composer/autoload_classmap.php";
      if (!$res) {
        exec(
          "cd " .
            dirname($mvc->libPath()) .
            " &&  composer dump-autoload -o && cd -",
        );
        $res = include $mvc->libPath() . "composer/autoload_classmap.php";
      }

      if (!$res) {
        throw new Exception("No way to get classes from composer");
      }

      $property = "default_class_cfg";
      $num = 0;
      $corr = [];
      $keys = array_keys($res);
      $local = X::filter($keys, fn($a) => Str::startsWith($a, constant("BBN_APP_PREFIX")));
      $bbn = X::filter($keys, fn($a) => Str::startsWith($a, "bbn\\"));
      foreach ([...$local, ...$bbn] as $cls) {
        if (!class_exists($cls)) {
          continue;
        }

        $num++;
        $cacheDone = false;
        $hasCache = false;
        $table = null;
        $ccls = $cls;
        $junctionDone = false;
        while ($ccls && (!$cacheDone || !$table)) {
          if (
            property_exists($ccls, $property) &&
            method_exists($ccls, "initClassCfg")
          ) {
            $ref = new ReflectionProperty($ccls, $property);
            if ($ref && $ref->isStatic() && ($value = $ref->getValue())) {
              if (!$cacheDone && array_key_exists("cache", $value)) {
                $cacheDone = true;
                $hasCache = (bool) $value["cache"];
              }

              if (!$table && isset($value["table"])) {
                $table = $value["table"];
                if (!array_key_exists($table, $corr)) {
                  $corr[$table] = [
                    'class' => $cls,
                    'cache' => false,
                    'junctions' => []
                  ];
                }
              }

              if ($hasCache && $table) {
                $corr[$table]['cache'] = true;
                if (!$junctionDone && isset($value['junctions']) && is_array($value['junctions'])) {
                  $junctionDone = true;
                  foreach ($value['junctions'] as $j) {
                    if (isset($j['table'], $j['field'])) {
                      $corr[$table]['junctions'][] = $j;
                    }
                  }
                }

                break;
              }
            }
          }

          $ccls = get_parent_class($ccls);
        }
      }

      ksort($corr);
      foreach ($corr as $table => $c) {
        if (count($c['junctions'])) {
          foreach ($c['junctions'] as $j) {
            if (isset($corr[$j['table']])) {
              if (!isset($corr[$j['table']]['deps'])) {
                $corr[$j['table']]['deps'] = [];
              }
              if (!in_array($table, $corr[$j['table']]['deps'])) {
                $corr[$j['table']]['deps'][] = $table;
              }
            }
          }
        }
        else {
          unset($corr[$table]['junctions']);
        }
      }
      $cached = $corr;
      $cache->set("bbn_dbconfig_cache_init", $cached, 3600);
    }

    self::$dbConfigTableClasses = $cached;
    return $cached;
  }

}

