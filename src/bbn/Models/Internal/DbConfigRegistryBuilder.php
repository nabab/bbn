<?php

namespace bbn\Models\Internal;

use bbn\Cache;
use bbn\Mvc;
use bbn\Str;
use bbn\X;
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

      if (!empty($meta['junctions'])) {
        foreach ($meta['junctions'] as $j) {
          $registry[$table]['junctions'][] = $j;
        }
      }
    }

    ksort($registry);
    $this->buildReverseDependencies($registry);

    foreach ($registry as $table => $cfg) {
      if (empty($cfg['junctions'])) {
        unset($registry[$table]['junctions']);
      }
    }

    return $registry;
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

            if (!$table && isset($value['table']) && is_string($value['table'])) {
              $table = $value['table'];
            }

            if (
              $hasCache
              && !$junctionDone
              && isset($value['junctions'])
              && is_array($value['junctions'])
            ) {
              $junctionDone = true;
              foreach ($value['junctions'] as $j) {
                if (isset($j['table'], $j['field'])) {
                  $junctions[] = $j;
                }
              }
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
      'junctions' => $junctions
    ];
  }

  /**
   * Builds reverse dependencies (`deps`) from junction definitions.
   *
   * Example:
   * if `bbn_members_entities` has a junction to `bbn_members`,
   * then `bbn_members` gets `deps => ['bbn_members_entities']`.
   *
   * @param array<string, array<string, mixed>> $registry
   * @return void
   */
  protected function buildReverseDependencies(array &$registry): void
  {
    foreach ($registry as $table => $cfg) {
      if (empty($cfg['junctions'])) {
        continue;
      }

      foreach ($cfg['junctions'] as $j) {
        if (isset($j['table']) && isset($registry[$j['table']])) {
          if (!isset($registry[$j['table']]['deps'])) {
            $registry[$j['table']]['deps'] = [];
          }

          if (!in_array($table, $registry[$j['table']]['deps'], true)) {
            $registry[$j['table']]['deps'][] = $table;
          }
        }
      }
    }
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
