<?php

namespace bbn\Appui\Option\Internal;

use Exception;
use bbn\Str;
use bbn\X;

trait Parents
{
  /**
   * @return array|null
   * @throws Exception
   */
  public function siblings(): ?array
  {
    if ($id = $this->fromCode(...func_get_args())) {
      if (($id_parent = $this->getIdParent($id)) && ($full_options = $this->fullOptions($id_parent))) {
        return array_filter(
          $full_options, function ($a) use ($id) {
            return $a[$this->fields['id']] !== $id;
          }
        );
      }
    }

    return null;
  }


  /**
   * Returns an array of id_parents from the option selected to root
   *
   * ```php
   * X::dump($opt->parents(48));
   * // array [25, 12, 0]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null The array of parents' ids, an empty array if no parent (root case), and null if it can't find the option
   */
  public function parents($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $res = [];
      while (Str::isUid($id_parent = $this->getIdParent($id))){
        if (\in_array($id_parent, $res, true)) {
          break;
        }
        else{
          if ($id === $id_parent) {
            break;
          }
          else{
            $res[] = $id_parent;
            $id    = $id_parent;
          }
        }
      }

      return $res;
    }

    return null;
  }


  /**
   * Returns an array of id_parents from the selected root to the given id_option
   *
   * ```php
   * X::dump($opt->parents(48));
   * // array [0, 12, 25, 48]
   * X::dump($opt->parents(48, 12));
   * // array [12, 25, 48]
   * ```
   *
   * @param string      $id_option
   * @param string|null $id_root
   * @return array|null The array of parents' ids, an empty array if no parent (root case), and null if it can't find the option
   */
  public function sequence(string $id_option, string|null $id_root = null): ?array
  {
    if ($this->check()) {
      if (null === $id_root) {
        $id_root = $this->getRoot();
      }

      if ($this->exists($id_root) && ($parents = $this->parents($id_option))) {
        $res = [$id_option];
        foreach ($parents as $p){
          array_unshift($res, $p);
          if ($p === $id_root) {
            return $res;
          }
        }
      }
    }

    return null;
  }


  /**
   * Returns the parent option's ID
   *
   * ```php
   * X::dump($opt->getIdParent(48));
   * // (int)25
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return string|null The parent's ID, null if no parent or if option cannot be found.
   */
  public function getIdParent($code = null): ?string
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))
      && ($opt = $this->nativeOption($id))
    ) {
      return $opt[$this->fields['id_parent']];
    }

    return null;
  }


  /**
   * Returns the parent's option as {@link option()}
   *
   * ```php
   * X::hdump($opt->parent(42));
   * /*
   * array [
   *   'id' => 25,
   *   'code' => "bbn_ide",
   *   'text' => "This is BBN's IDE",
   *   'myIntProperty' => 56854,
   *   'myTextProperty' => "<h1>Hello\nWorld</h1>",
   *   'myArrayProperty' => ['value1' => 1, 'value2' => 2]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|false
   */
  public function parent($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))
        && ($id_parent = $this->getIdParent($id))
    ) {
      return $this->option($id_parent);
    }

    return null;
  }


  /**
   * Return true if row with ID $id_parent is parent at any level of row with ID $id
   *
   * ```php
   * X::dump($opt->isParent(42, 12));
   * // (bool) true
   * X::dump($opt->isParent(42, 13));
   * // (bool) false
   * ```
   *
   * @param $id
   * @param $id_parent
   * @return bool
   */
  public function isParent($id, $id_parent): bool
  {
    // Preventing infinite loop
    $done = [$id];
    if (Str::isUid($id) && Str::isUid($id_parent)) {
      while ($id = $this->getIdParent($id)){
        if ($id === $id_parent) {
          return true;
        }

        if (\in_array($id, $done, true)) {
          break;
        }

        $done[] = $id;
      }
    }

    return false;
  }


  /**
   * Gets the closest parent which has either the given id_alias or 
   * @param mixed $id
   * @param mixed $target
   * @return mixed
   */
  public function closest(string $id, string|array $target): ?string
  {
    $ids = $this->parents($id);
    if (!\is_array($target)) {
      $target = [Str::isUid($target) ? 'id_alias' : 'code' => $target];
    }

    foreach ($ids as $i) {
      $opt = $this->option($i);
      if (X::getRow([$opt], $target)) {
        return $i;
      }
    }

    return null;
  }
}
