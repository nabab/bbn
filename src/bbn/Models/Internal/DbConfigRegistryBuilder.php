<?php

namespace bbn\Models\Internal;

use bbn\Cache;
use bbn\Mvc;
use bbn\Str;
use bbn\X;
use bbn\Db;
use Exception;
use ReflectionProperty;

use function array_key_exists;
use function dirname;
use function in_array;
use function is_array;
use function ksort;

/**
 * Builds and caches the registry of DB-configured table classes.
 *
 * The registry is created by scanning the Composer classmap and looking for
 * classes exposing a static `default_class_cfg` property and an
 * `initClassCfg()` method.
 *
 * Returned structure example:
 *
 * [
 *   'bbn_members' => [
 *     'class' => 'apst\\Adherents\\Tables\\Members',
 *     'cache' => true,
 *     'deps' => [
 *       'bbn_members_entities'
 *     ]
 *   ],
 *   'bbn_members_entities' => [
 *     'class' => 'apst\\Adherents\\Tables\\MembersEntities',
 *     'cache' => true,
 *     'junctions' => [
 *       [
 *         'table' => 'bbn_members',
 *         'field' => 'id_member'
 *       ]
 *     ]
 *   ]
 * ]
 */
class DbConfigRegistryBuilder
{
  /**
   * Cache key storing the built registry.
   *
   * @var string
   */
  protected string $cacheKey = 'bbn_dbconfig_cache_init';

  /**
   * Cache TTL in seconds.
   *
   * @var int
   */
  protected int $cacheTtl = 3600;

  /**
   * In-memory registry cache for the current process.
   *
   * @var array<string, array<string, mixed>>|null
   */
  protected ?array $registry = null;

  /**
   * Constructor.
   *
   * @param object|null $cacheEngine Optional cache engine. If omitted, the default engine is used.
   */
  public function __construct(
    protected ?Db $db,
    protected ?object $cacheEngine = null
  )
  {
    if ($this->cacheEngine === null) {
      $this->cacheEngine = Cache::getEngine();
    }
  }

  /**
   * Returns the registry, using in-memory cache first, then shared cache.
   *
   * @param bool $force Whether to force a rebuild.
   * @return array<string, array<string, mixed>>
   * @throws Exception
   */
  public function getRegistry(bool $force = false): array
  {
    if (!$force && ($this->registry !== null)) {
      return $this->registry;
    }

    if (!$force && ($cached = $this->getCachedRegistry())) {
      $this->registry = $cached;
      return $this->registry;
    }

    $this->registry = $this->buildRegistry();
    $this->setCachedRegistry($this->registry);

    return $this->registry;
  }

  /**
   * Forces rebuilding the registry and returns it.
   *
   * @return array<string, array<string, mixed>>
   * @throws Exception
   */
  public function rebuildRegistry(): array
  {
    return $this->getRegistry(true);
  }

  /**
   * Clears the registry from in-memory cache and shared cache.
   *
   * @return bool
   */
  public function clearCache(): bool
  {
    $this->registry = null;

    if (method_exists($this->cacheEngine, 'delete')) {
      return (bool)$this->cacheEngine->delete($this->cacheKey);
    }

    if (method_exists($this->cacheEngine, 'del')) {
      return (bool)$this->cacheEngine->del($this->cacheKey);
    }

    if (method_exists($this->cacheEngine, 'set')) {
      return (bool)$this->cacheEngine->set($this->cacheKey, null, 1);
    }

    return false;
  }

  /**
   * Builds the full registry from the Composer classmap.
   *
   * @return array<string, array<string, mixed>>
   * @throws Exception
   */
  protected function buildRegistry(): array
  {
    $classmap = $this->loadClassmap();
    $registry = [];

    foreach ($this->getCandidateClasses($classmap) as $cls) {
      if (!$meta = $this->extractClassMeta($cls)) {
        continue;
      }

      $table = $meta['table'];

      if (!isset($registry[$table])) {
        $registry[$table] = [
          'class' => $cls,
          'cache' => false,
          'junctions' => []
        ];
      }

      if ($meta['cache']) {
        $registry[$table]['cache'] = true;
      }

      if (!empty($meta['primary'])) {
        $registry[$table]['primary'] = $meta['primary'];
      }

      if (!empty($meta['junctions'])) {
        $registry[$table]['junctions'] = $this->normalizeJunctions($meta['junctions']);
      }
    }

    ksort($registry);
    foreach ($registry as $table => $cfg) {
      if (empty($cfg['junctions'])) {
        unset($registry[$table]['junctions']);
      }
      else {
        $this->ensureJunctionTablesInRegistry($registry, $cfg['junctions']);
      }
    }

    $this->buildReverseDependencies($registry);

    return $registry;
  }

  /**
   * Ensures that every table referenced in junctions exists in the registry.
   *
   * If a table is not class-backed, a minimal registry entry is created
   * using DB metadata.
   *
   * This is recursive and will also inspect nested subjunctions.
   *
   * @param array $registry
   * @param array $junctions
   * @return void
   */
  protected function ensureJunctionTablesInRegistry(
    array &$registry,
    array $junctions
  ): void {
    foreach ($junctions as $junction) {
      if (empty($junction['table']) || !is_string($junction['table'])) {
        continue;
      }

      $table = $junction['table'];

      if (!isset($registry[$table])) {
        $registry[$table] = $this->makeVirtualRegistryEntry($table);
      }
      else {
        if (empty($registry[$table]['primary'])) {
          $registry[$table]['primary'] = $this->getTablePrimary($table);
        }

        if (!isset($registry[$table]['cache'])) {
          $registry[$table]['cache'] = false;
        }

        if (!isset($registry[$table]['junctions'])) {
          $registry[$table]['junctions'] = [];
        }
      }

      if (!empty($junction['junctions']) && is_array($junction['junctions'])) {
        $this->ensureJunctionTablesInRegistry($registry, $junction['junctions']);
      }
    }
  }

  /**
   * Creates a minimal registry entry for a table that has no mapped class.
   *
   * @param string $table
   * @return array<string, mixed>
   */
  protected function makeVirtualRegistryEntry(string $table): array
  {
    return [
      'class' => null,
      'cache' => false,
      'primary' => $this->getTablePrimary($table),
      'junctions' => []
    ];
  }

  /**
   * Returns the primary key columns for a table.
   *
   * Falls back to ['id'] if the DB model does not expose a primary key.
   *
   * @param string $table
   * @return array<int, string>
   */
  protected function getTablePrimary(string $table): array
  {
    try {
      $model = $this->db->modelize($table);

      if (!empty($model['keys']['PRIMARY']['columns'])) {
        return array_values($model['keys']['PRIMARY']['columns']);
      }

      if (!empty($model['primary']) && is_array($model['primary'])) {
        return array_values($model['primary']);
      }
    }
    catch (\Throwable) {
    }

    return ['id'];
  }

  /**
   * Loads the Composer classmap.
   *
   * @return array<string, string>
   * @throws Exception
   */
  protected function loadClassmap(): array
  {
    $path = Mvc::getLibPath() . 'composer/autoload_classmap.php';
    $res = include $path;

    if (!$res) {
      exec(
        'cd ' .
        dirname(Mvc::getLibPath()) .
        ' && composer dump-autoload -o && cd -'
      );
      $res = include $path;
    }

    if (!$res || !is_array($res)) {
      throw new Exception('No way to get classes from composer');
    }

    return $res;
  }

  /**
   * Filters classmap entries down to relevant candidate classes.
   *
   * @param array<string, string> $classmap
   * @return array<int, string>
   */
  protected function getCandidateClasses(array $classmap): array
  {
    $keys = array_keys($classmap);
    $local = X::filter($keys, fn($a) => Str::startsWith($a, constant('BBN_APP_PREFIX')));
    $bbn = X::filter($keys, fn($a) => Str::startsWith($a, 'bbn\\'));

    return [...$local, ...$bbn];
  }

  /**
   * Normalizes junction definitions recursively.
   *
   * @param array $junctions
   * @return array
   */
  protected function normalizeJunctions(array $junctions): array
  {
    $result = [];

    foreach ($junctions as $j) {
      if (empty($j['table']) || empty($j['field'])) {
        continue;
      }

      $normalized = [
        'table' => $j['table'],
        'field' => $j['field']
      ];

      foreach (['property', 'filter', 'fields', 'mode'] as $opt) {
        if (array_key_exists($opt, $j)) {
          $normalized[$opt] = $j[$opt];
        }
      }

      if (!empty($j['junctions']) && is_array($j['junctions'])) {
        $normalized['junctions'] = $this->normalizeJunctions($j['junctions']);
      }

      $result[] = $normalized;
    }

    return $result;
  }

  /**
   * Extracts table metadata from a class and its parents.
   *
   * The scan stops once both:
   * - a table is found
   * - cache information has been determined
   *
   * Junctions are collected only when cache is enabled on the resolved config.
   *
   * @param string $cls
   * @return array<string, mixed>|null
   */
  protected function extractClassMeta(string $cls): ?array
  {
    if (!class_exists($cls)) {
      return null;
    }

    $property = 'default_class_cfg';
    $cacheDone = false;
    $hasCache = false;
    $table = null;
    $primary = null;
    $junctionDone = false;
    $junctions = [];
    $current = $cls;

    while ($current && (!$cacheDone || !$table)) {
      if (
        property_exists($current, $property)
        && method_exists($current, 'initClassCfg')
      ) {
        $ref = new ReflectionProperty($current, $property);
        if ($ref->isStatic()) {
          $value = $ref->getValue();

          if (is_array($value)) {
            if (!$cacheDone && array_key_exists('cache', $value)) {
              $cacheDone = true;
              $hasCache = (bool)$value['cache'];
            }

            if (!$table && !empty($value['table']) && is_string($value['table'])) {
              $table = $value['table'];
            }

            if (!$primary && !empty($table)) {
              try {
                if ($this->db->tableExists($table)) {
                  $model = $this->db->modelize($table);
                  if (!empty($model['keys']['PRIMARY']['columns'])) {
                    $primary = $model['keys']['PRIMARY']['columns'];
                  }
                  elseif (!empty($model['primary'])) {
                    $primary = $model['primary'];
                  }
                }
              }
              catch (\Throwable) {
              }
            }

            if (
              $hasCache
              && !$junctionDone
              && !empty($value['junctions'])
              && is_array($value['junctions'])
            ) {
              $junctionDone = true;
              $junctions = $value['junctions'];
            }

            if ($hasCache && $table) {
              break;
            }
          }
        }
      }

      $current = get_parent_class($current);
    }

    if (!$table) {
      return null;
    }

    return [
      'table' => $table,
      'cache' => $hasCache,
      'primary' => $primary ?: ['id'],
      'junctions' => $junctions
    ];
  }

  /**
   * Builds reverse dependencies recursively from junction definitions.
   *
   * A direct junction:
   *   source -> target
   *
   * creates:
   *   target['deps'][] = [
   *     'table' => source,
   *     'field' => junction.field
   *   ]
   *
   * Nested junctions also create reverse dependencies, keeping the root source table.
   *
   * @param array $registry
   * @return void
   */
  protected function buildReverseDependencies(array &$registry): void
  {
    foreach ($registry as $sourceTable => $cfg) {
      if (empty($cfg['junctions'])) {
        continue;
      }

      $this->appendReverseDependencies(
        $registry,
        $sourceTable,
        $cfg['junctions']
      );
    }
  }

  /**
   * Recursively appends reverse dependencies for a source table.
   *
   * @param array  $registry
   * @param string $sourceTable
   * @param array  $junctions
   * @return void
   */
  protected function appendReverseDependencies(
    array &$registry,
    string $sourceTable,
    array $junctions
  ): void {
    foreach ($junctions as $junction) {
      if (empty($junction['table']) || empty($junction['field'])) {
        continue;
      }

      $targetTable = $junction['table'];

      if (isset($registry[$targetTable])) {
        if (!isset($registry[$targetTable]['deps'])) {
          $registry[$targetTable]['deps'] = [];
        }

        $dep = [
          'table' => $sourceTable,
          'field' => $junction['field']
        ];

        if (!$this->hasDependency($registry[$targetTable]['deps'], $dep)) {
          $registry[$targetTable]['deps'][] = $dep;
        }
      }

      if (!empty($junction['junctions']) && is_array($junction['junctions'])) {
        $this->appendReverseDependencies(
          $registry,
          $sourceTable,
          $junction['junctions']
        );
      }
    }
  }

  /**
   * Checks whether a dependency already exists.
   *
   * @param array $deps
   * @param array $needle
   * @return bool
   */
  protected function hasDependency(array $deps, array $needle): bool
  {
    foreach ($deps as $dep) {
      if (
        isset($dep['table'], $dep['field']) &&
        $dep['table'] === $needle['table'] &&
        $dep['field'] === $needle['field']
      ) {
        return true;
      }
    }

    return false;
  }

  /**
   * Reads the registry from the shared cache.
   *
   * @return array<string, array<string, mixed>>|null
   */
  protected function getCachedRegistry(): ?array
  {
    if (!method_exists($this->cacheEngine, 'get')) {
      return null;
    }

    $cached = $this->cacheEngine->get($this->cacheKey);
    return is_array($cached) ? $cached : null;
  }

  /**
   * Stores the registry in the shared cache.
   *
   * @param array<string, array<string, mixed>> $registry
   * @return void
   */
  protected function setCachedRegistry(array $registry): void
  {
    if (method_exists($this->cacheEngine, 'set')) {
      $this->cacheEngine->set($this->cacheKey, $registry, $this->cacheTtl);
    }
  }
}
