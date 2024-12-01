<?php

namespace bbn\Appui\Option;

use bbn\Str;
use bbn\Appui\Option;


trait Cache
{
  /** @var array A store for parameters sent to @see from_code */
  private $_local_cache = [];

  /**
   * Sets the cache
   * @param string $id
   * @param string $method
   * @param mixed $data
   * @param string|null $locale
   * @return self
   */
  public function setCache(string $id, string $method, $data, ?string $locale = null)
  {
    if (empty($locale)) {
      $locale = $this->getTranslatingLocale($id);
    }

    if (!empty($locale)) {
      return $this->cacheSetLocale($id, $locale, $method, $data);
    }

    return $this->cacheSet($id, $method, $data);
  }


  /**
   * Gets the cache
   * @param string $id
   * @param string $method
   * @param string|null $locale
   * @return mixed
   */
  public function getCache(string $id, string $method, ?string $locale = null)
  {
    if (empty($locale)) {
      $locale = $this->getTranslatingLocale($id);
    }

    if (!empty($locale)) {
      return $this->cacheGetLocale($id, $locale, $method);
    }

    return $this->cacheGet($id, $method);
  }


  /**
   * Deletes the options' cache, specifically for an ID or globally
   * If specific, it will also destroy the cache of the parent
   *
   * ```php
   * $opt->option->deleteCache(25)
   * // This is chainable
   * // ->...
   * ```
   * @param string|null $id The option's ID
   * @param boolean $deep If sets to true, children's cache will also be deleted
   * @param boolean $subs Used internally only for deleting children's cache without their parent
   * @return Option
   */
  public function deleteCache(string $id = null, $deep = false, $subs = false): self
  {
    if ($this->check()) {
      if (Str::isUid($id)) {
        if (($deep || !$subs) && ($items = $this->items($id))) {
          foreach ($items as $it){
            $this->deleteCache($it, $deep, true);
          }
        }

        if (!$subs && ($id_alias = $this->alias($id))) {
          $this->deleteCache($id_alias, false, true);
        }

        $this->cacheDelete($id);
        if (!$subs) {
          $this->cacheDelete($this->getIdParent($id));
        }
      }
      elseif (is_null($id)) {
        $this->cacheDeleteAll();
      }
    }

    return $this;
  }


  /**
   * @param $name
   * @param $val
   */
  private function _set_local_cache($name, $val): void
  {
    $this->_local_cache[$name] = $val;
  }


  /**
   * @param $name
   * @return string|null
   */
  private function _get_local_cache($name): ?string
  {
    return isset($this->_local_cache[$name]) ? $this->_local_cache[$name] : null;
  }

}
