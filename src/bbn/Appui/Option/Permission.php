<?php

namespace bbn\Appui\Option;

use bbn\Str;

trait Permission
{
  /**
   * Checks whether an option has _permissions_ in its parent cfg
   *
   * ```php
   * X::dump($opt->hasPermission('bbn_ide'));
   * // (bool) true
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return bool
   */
  public function hasPermission($code = null)
  {
    if (Str::isUid($p = $this->getIdParent(\func_get_args()))) {
      $cfg = $this->getCfg($p);
      return !empty($cfg['permissions']);
    }

    return false;
  }


  /**
   * Returns an array of _permissions_ from origin $id
   *
   * ```php
   * X::dump($opt->findPermissions());
   * /* Returns a full tree of permissions for all options
   * array []
   * ```
   *
   * @todo Returned comments to add
   * @param int|null $id   The origin's ID
   * @param boolean  $deep If set to true the children will also be searched
   * @return array|null An array of permissions if there are, null otherwise
   */
  public function findPermissions($id = null, $deep = false)
  {
    if ($this->check()) {
      if (\is_null($id)) {
        $id = $this->default;
      }

      $cfg = $this->getCfg($id);
      if (!empty($cfg['permissions'])) {
        $perms = [];
        if ($opts  = $this->fullOptionsCfg($id)) {
          foreach ($opts as $opt){
            $o = [
              'icon' => $opt[$this->fields['cfg']]['icon'] ?? 'nf nf-fa-cog',
              'text' => $this->getTranslation($opt[$this->fields['id']]) ?: $opt[$this->fields['text']],
              'id' => $opt[$this->fields['id']]
            ];
            if ($deep && !empty($opt[$this->fields['cfg']]['permissions'])) {
              $o['items'] = $this->findPermissions($opt[$this->fields['id']], true);
            }

            $perms[] = $o;
          }
        }

        return $perms;
      }
    }

    return null;
  }

}
