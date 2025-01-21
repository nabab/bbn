<?php

namespace bbn\Appui\Option;

use bbn\Str;
use bbn\Appui\Option;

/**
 * Trait Cache provides caching functionality for options.
 */
trait Cache
{
  /**
   * A store for parameters sent to methods that utilize caching.
   *
   * @var array
   */
  private $_local_cache = [];

  /**
   * Sets the cache value for a given method and ID, with optional locale support.
   *
   * If no locale is provided, it will attempt to retrieve the translating locale for the given ID.
   *
   * @param string $id The option's ID
   * @param string $method The method name to cache
   * @param mixed $data The data to cache
   * @param string|null $locale Optional locale for caching (defaults to null)
   *
   * @return self
   */
  public function setCache(string $id, string $method, $data, ?string $locale = null): self
  {
    // If no locale is provided, attempt to retrieve the translating locale for the given ID.
    if (empty($locale)) {
      $locale = $this->getTranslatingLocale($id);
    }

    // If a locale exists, cache with locale support; otherwise, cache without locale.
    if (!empty($locale)) {
      return $this->cacheSetLocale($id, $locale, $method, $data);
    } else {
      return $this->cacheSet($id, $method, $data);
    }
  }

  /**
   * Retrieves the cached value for a given method and ID, with optional locale support.
   *
   * If no locale is provided, it will attempt to retrieve the translating locale for the given ID.
   *
   * @param string $id The option's ID
   * @param string $method The method name to retrieve from cache
   * @param string|null $locale Optional locale for caching (defaults to null)
   *
   * @return mixed
   */
  public function getCache(string $id, string $method, ?string $locale = null)
  {
    // If no locale is provided, attempt to retrieve the translating locale for the given ID.
    if (empty($locale)) {
      $locale = $this->getTranslatingLocale($id);
    }

    // If a locale exists, retrieve cache with locale support; otherwise, retrieve without locale.
    if (!empty($locale)) {
      return $this->cacheGetLocale($id, $locale, $method);
    } else {
      return $this->cacheGet($id, $method);
    }
  }

  /**
   * Deletes the options' cache for a given ID or globally, with optional deep deletion of children's caches.
   *
   * @param string|null $id The option's ID (or null for global deletion)
   * @param boolean $deep If true, also deletes children's caches
   * @param boolean $subs Used internally for recursive cache deletion without deleting the parent's cache
   *
   * @return Option
   */
  public function deleteCache(string $id = null, bool $deep = false, bool $subs = false): self
  {
    // Ensure the class is initialized and has a valid database connection before proceeding with cache deletion.
    if ($this->check()) {
      // If an ID is provided and it's a valid UID, proceed with cache deletion for that ID.
      if (Str::isUid($id)) {
        // Recursively delete caches of children if deep deletion is enabled or not deleting the parent's cache.
        if (($deep || !$subs) && ($items = $this->items($id))) {
          foreach ($items as $it) {
            $this->deleteCache($it, $deep, true);
          }
        }

        // Delete the alias's cache if it exists and not deleting the parent's cache.
        if (!$subs && ($id_alias = $this->alias($id))) {
          $this->deleteCache($id_alias, false, true);
        }

        // Delete the cache for the given ID.
        $this->cacheDelete($id);

        // If not deleting the parent's cache, also delete its cache.
        if (!$subs) {
          $this->cacheDelete($this->getIdParent($id));
        }
      } elseif (is_null($id)) {
        // Delete all caches if no ID is provided.
        $this->cacheDeleteAll();
      }
    }

    return $this;
  }
}
