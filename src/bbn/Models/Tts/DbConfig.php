<?php
namespace bbn\Models\Tts;

use Exception;
use bbn\X;
use bbn\Models\Internal\DbConfigRegistryBuilder;

use function count;
use function is_array;

/**
 * Provides declarative database configuration handling for table classes.
 *
 * Classes using this trait are expected to define a static
 * `$default_class_cfg` property containing at least:
 *
 * - `table`  : main table name
 * - `tables` : indexed list of tables
 * - `arch`   : table structures indexed by table aliases
 *
 * The trait is responsible for:
 * - building and caching the merged class configuration
 * - exposing the main table and fields
 * - building a cache of configured table classes and their dependencies
 */
trait DbConfig
{
  use Event;

  /**
   * Tracks which classes have already had their DB config initialized.
   *
   * @var array<string, bool>
   */
  protected static array $_isInitClassCfg = [];

  /**
   * Cached map of table names to class metadata.
   *
   * Example:
   * [
   *   'bbn_members' => [
   *     'class' => '...',
   *     'cache' => true,
   *     'deps' => ['bbn_members_entities']
   *   ]
   * ]
   *
   * @var array<string, array<string, mixed>>
   */
  protected static array $dbConfigTableClasses;

  /**
   * Fields of the main table for the current class.
   *
   * @var array<string, string>
   */
  protected array $fields;

  /**
   * Fully resolved class configuration.
   *
   * @var array<string, mixed>
   */
  protected array $class_cfg;

  /**
   * Main table name for the current class.
   *
   * @var string
   */
  protected string $class_table;

  /**
   * Index of the main table in the `tables` / `arch` configuration.
   *
   * @var string
   */
  protected string $class_table_index;

  /**
   * Cached resolved configuration by class name.
   *
   * @var array<string, array<string, mixed>>
   */
  protected static array $dbConfigCfg = [];

  /**
   * Indicates whether the DB config has already been initialized for the class.
   *
   * @return bool
   */
  public static function isDbConfigInit(): bool
  {
    return !empty(self::$_isInitClassCfg[static::class]);
  }

  /**
   * Returns the raw default class configuration declared on the class.
   *
   * @return array<string, mixed>|null
   */
  public static function getDefaultClassCfg(): ?array
  {
    return static::$default_class_cfg ?? [];
  }

  /**
   * Returns the resolved class configuration for the current instance.
   *
   * @return array<string, mixed>
   */
  public function getClassCfg(): array
  {
    return $this->class_cfg;
  }

  /**
   * Returns the list of fields for the main table.
   *
   * @return array<string, string>
   */
  public function getFields(): array
  {
    return $this->fields;
  }

  /**
   * Returns the main table name for the current instance.
   *
   * @return string
   */
  public function getClassTable(): string
  {
    return $this->class_table;
  }

  /**
   * Returns the main table index for the current instance.
   *
   * @return string
   */
  public function getClassTableIndex(): string
  {
    return $this->class_table_index;
  }

  /**
   * Builds and caches the resolved class configuration for the current class.
   *
   * The configuration is built by traversing the class inheritance chain and
   * merging every available `default_class_cfg`.
   *
   * Special handling:
   * - `table_index` is automatically resolved from `tables` and `table`
   * - array field definitions are normalized into `props`
   *
   * @param array<string, mixed>|null $cfg Optional external configuration.
   * @return array<string, mixed>
   * @throws Exception If the configuration is missing or invalid.
   */
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
      throw new Exception(
        X::_(
          "The class %s is not configured properly to work with trait DbActions: no configuration available",
          static::class
        )
      );
    }

    $cfg = count($arr) === 1 ? $arr[0] : array_merge(...$arr);

    if (!isset($cfg['tables'])) {
      throw new Exception(
        X::_(
          "The class %s is not configured properly to work with trait DbActions: no tables",
          static::class
        )
      );
    }

    $table_index = array_flip($cfg['tables'])[$cfg['table']] ?? null;
    if (
      !$table_index
      || !isset($cfg['tables'], $cfg['table'], $cfg['arch'], $cfg['arch'][$table_index])
    ) {
      throw new Exception(
        X::_(
          "The class %s is not configured properly to work with trait DbActions: invalid table configuration",
          static::class
        )
      );
    }

    // We completely replace the table structure, no merge.
    $props = [];
    foreach ($cfg['arch'] as $t => &$fields) {
      if (empty($cfg['table_index']) && isset($cfg['tables'][$t]) && ($cfg['tables'][$t] === $cfg['table'])) {
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
   * Initializes the instance configuration from the static class config.
   *
   * Sets:
   * - `$this->class_cfg`
   * - `$this->fields`
   * - `$this->class_table_index`
   * - `$this->class_table`
   *
   * @return static
   */
  protected function initClassCfg(): static
  {
    if (!isset($this->class_cfg)) {
      $this->class_cfg = static::dbConfigInit();
      $cfg = $this->class_cfg;
      $this->fields = $cfg['arch'][$cfg['table_index']];
      $this->class_table_index = $cfg['table_index'];
      $this->class_table = $cfg['table'];
    }

    return $this;
  }

  /**
   * Indicates whether the current class has already been initialized.
   *
   * @return bool
   */
  public function isInitClassCfg(): bool
  {
    return static::$_isInitClassCfg[static::class] ?? false;
  }

  /**
   * Ensures that the current instance configuration has been initialized.
   *
   * @return void
   * @throws Exception If the configuration has not been initialized.
   */
  public function dbConfigCheck(): void
  {
    if (!$this->isInitClassCfg()) {
      throw new Exception(
        X::_(
          "The class %s is not configured properly has not been initiated for trait DbConfig",
          static::class
        )
      );
    }
  }

  /**
   * Returns the map of configured table classes and their dependency metadata.
   *
   * The result is built by scanning the Composer class map and collecting all
   * classes exposing a static `default_class_cfg` property and an
   * `initClassCfg()` method.
   *
   * Returned metadata may contain:
   * - `class`     : fully qualified class name
   * - `cache`     : whether the table class has row cache enabled
   * - `junctions` : declared table junctions
   * - `deps`      : reverse dependencies computed from junctions
   *
   * @return array<string, array<string, mixed>>
   * @throws Exception If the Composer class map cannot be loaded.
   */
  public static function dbConfigGetTableClasses(): array
  {
    if (isset(self::$dbConfigTableClasses)) {
      return self::$dbConfigTableClasses;
    }

    $builder = new DbConfigRegistryBuilder();
    self::$dbConfigTableClasses = $builder->getRegistry();

    return self::$dbConfigTableClasses;
  }
}
