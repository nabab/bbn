<?php

namespace bbn\Appui\Option;

use bbn\Str;

trait Cfg
{
  /**
   * Returns a formatted content of the cfg column as an array
   * Checks if the parent option has inheritance and sets array accordingly
   * Parent rules will be applied if with the following inheritance values:
   * - 'children': if the option is the direct parent
   * - 'cascade': any level of parenthood
   *
   * ```php
   * X::dump($opt->getCfg(25));
   * /*
   * array [
   *   'sortable' => true,
   *   'cascade' => true,
   *   'id_alias' => null,
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null The formatted array or false if the option cannot be found
   */
  public function getCfg($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      if ($tmp = $this->cacheGet($id, __FUNCTION__)) {
        return $tmp;
      }

      $c   =& $this->class_cfg;
      $f   =& $this->fields;
      $cfg = $this->db->selectOne($c['table'], $f['cfg'], [$f['id'] => $id]);
      $cfg = Str::isJson($cfg) ? json_decode($cfg, true) : [];
      $perm = $cfg['permissions'] ?? false;
      // Looking for parent with inheritance
      $parents = array_reverse($this->parents($id));
      $last    = \count($parents) - 1;
      foreach ($parents as $i => $p){
        $parent_cfg = $this->db->selectOne($c['table'], $f['cfg'], [$f['id'] => $p]);
        $parent_cfg = Str::isJson($parent_cfg) ? json_decode($parent_cfg, true) : [];
        if (!empty($parent_cfg['scfg']) && ($i === $last)) {
          $cfg                 = array_merge((array)$cfg, $parent_cfg['scfg']);
          $cfg['inherit_from'] = $p;
          $cfg['frozen']       = 1;
          break;
        }

        if (!empty($parent_cfg['inheritance']) || !empty($parent_cfg['scfg']['inheritance'])) {
          if (
              (($i === $last)
              && (
              (($parent_cfg['inheritance'] ?? null) === 'children')
              || (!empty($parent_cfg['scfg']) && (($parent_cfg['scfg']['inheritance'] ?? null) === 'children')))
              )
              || (
              (($parent_cfg['inheritance'] ?? null) === 'cascade')
              || (!empty($parent_cfg['scfg']) && (($parent_cfg['scfg']['inheritance'] ?? null) === 'cascade'))
            )
          ) {
            // Keeping in the option cfg properties which don't exist in the parent
            $cfg                 = array_merge((array)$cfg, $parent_cfg['scfg'] ?? $parent_cfg);
            $cfg['inherit_from'] = $p;
            $cfg['frozen']       = 1;
            break;
          }
          elseif (!count($cfg)
              && ((($parent_cfg['inheritance'] ?? null) === 'default')
              || (!empty($parent_cfg['scfg']) && (($parent_cfg['scfg']['inheritance'] ?? null) === 'default'))              )
          ) {
            $cfg                 = $parent_cfg['scfg'] ?? $parent_cfg;
            $cfg['inherit_from'] = $p;
          }
        }
      }

      if ($perm) {
        $cfg['permissions'] = $perm;
      }

      $mandatories = ['show_code', 'show_alias', 'show_value', 'show_icon', 'sortable', 'allow_children', 'frozen'];
      foreach ($mandatories as $m){
        $cfg[$m] = empty($cfg[$m]) ? 0 : 1;
      }

      $mandatories = ['desc', 'inheritance', 'permissions', 'i18n', 'i18n_inheritance'];
      foreach ($mandatories as $m){
        $cfg[$m] = empty($cfg[$m]) ? '' : $cfg[$m];
      }

      $mandatories = ['controller', 'schema', 'form', 'default_value'];
      foreach ($mandatories as $m){
        $cfg[$m] = empty($cfg[$m]) ? null : $cfg[$m];
      }

      $this->cacheSet($id, __FUNCTION__, $cfg);
      return $cfg;
    }

    return null;
  }


  /**
   * Returns the raw content of the cfg column for the given option.
   *
   * ```php
   * X::dump($opt->getRawCfg(25));
   * // (string) "{'sortable':true, 'cascade': true}"
   *
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return string|null The formatted array or null if the option cannot be found
   */
  public function getRawCfg($code = null): ?string
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $c =& $this->class_cfg;
      $f =& $this->fields;
      return $this->db->selectOne($c['table'], $f['cfg'], [$f['id'] => $id]);
    }

    return null;
  }


  /**
   * Returns a formatted content of the cfg column as an array from the option's parent
   *
   * ```php
   * X::dump($opt->getParentCfg(42));
   * /*
   * [
   *   'sortable' => true,
   *   'cascade' => true,
   *   'id_alias' => null,
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null config or null if the option cannot be found
   */
  public function getParentCfg($code = null): ?array
  {
    if ($id = $this->fromCode(\func_get_args())) {
      if ($id_parent = $this->getIdParent($id)) {
        return $this->getCfg($id_parent);
      }
    }

    return null;
  }


  /**
   * Tells if an option has its config set as sortable or no
   *
   * ```php
   * X::dump($opt->isSortable(12));
   * // (bool) false
   * X::dump($opt->isSortable(21));
   * // (bool) true
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return bool
   */
  public function isSortable($code = null): ?bool
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $cfg = $this->getCfg($id);
      return !empty($cfg['sortable']);
    }

    return null;
  }


  public function getSchema($id): ?array
  {
    if ($cfg = $this->getCfg($id)) {
      if (!empty($cfg['schema']) && is_string($cfg['schema'])) {
        return json_decode($cfg['schema'], true);
      }
    }

    return null;
  }
}
