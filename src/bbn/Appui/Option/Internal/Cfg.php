<?php

namespace bbn\Appui\Option\Internal;

use bbn\Str;
use bbn\X;
use Exception;

/**
 * This trait provides configuration-related functionality for the Options class.
 */
trait Cfg
{

  /**
   * Returns a formatted content of the cfg column as an array.
   * Checks if the parent option has inheritance and sets array accordingly.
   *
   * The following inheritance values are supported:
   * - 'children': if the option is the direct parent
   * - 'cascade': any level of parenthood
   *
   * @param mixed ...$codes Any option(s) accepted by fromCode()
   * @return array|null The formatted array or null if the option cannot be found
   */
  public function getCfg(...$codes): ?array
  {
    // Get the ID of the option from its code.
    $id = $this->fromCode($codes);

    if (!Str::isUid($id)) {
      // If the ID is not valid, return null.
      throw new Exception(X::_("Invalid option ID"));
    }

    // Check if the ID is valid and if the result is cached.
    if ($tmp = $this->getCache($id, __FUNCTION__)) {
      return $tmp;
    }

    // Get references to class configuration and fields.
    $c   = &$this->class_cfg;
    $f   = &$this->fields;

    $id_alias = $this->db->selectOne($c['table'], $f['id_alias'], [$f['id'] => $id]);
    if ($id_alias && $this->hasTemplate($id)) {
      $cfg = $this->getRawCfg($id_alias);
      $id = $id_alias;
    }
    else {
      // Retrieve the cfg value from the database.
      $cfg = $this->getRawCfg($id);
    }

    // Check for permissions and store them in the config array.
    $perm = $cfg['permissions'] ?? false;

    // Look for parent options with inheritance.
    $rparents = $this->parents($id);

    $parents = array_reverse($rparents);
    $last    = end($parents);

    // Iterate through the parents to find one with inheritance.
    foreach ($parents as $i => $p) {
      if ($i < 2) {
        // Skip the first two parents as they are not relevant for inheritance.
        continue;
      }
      // Retrieve the config of the parent option.
      $parent_cfg = $this->getRawCfg($p);

      // Check for inheritance in the parent's config or scfg.
      if (!empty($parent_cfg['scfg']) && ($p === $last)) {
        // Merge the current config with the parent's scfg and set inherit_from and frozen.
        $cfg                 = array_merge((array)$cfg, $parent_cfg['scfg']);
        $cfg['inherit_from'] = $p;
        $cfg['frozen']       = 1;
        break;
      }

      // Check for inheritance in the parent's config or scfg.
      if (!empty($parent_cfg['inheritance']) || !empty($parent_cfg['scfg']['inheritance'])) {
        // Check if the parent is a direct parent and its inheritance value is 'children' or 'cascade'.
        if (
          (($p === $last)
            && (
              (($parent_cfg['inheritance'] ?? null) === 'children')
              || (!empty($parent_cfg['scfg']) && (($parent_cfg['scfg']['inheritance'] ?? null) === 'children'))
            )
          )
          || (
            (($parent_cfg['inheritance'] ?? null) === 'cascade')
            || (!empty($parent_cfg['scfg']) && (($parent_cfg['scfg']['inheritance'] ?? null) === 'cascade'))
          )
        ) {
          // Merge the current config with the parent's scfg or config, and set inherit_from and frozen.
          $cfg                 = array_merge((array)$cfg, $parent_cfg['scfg'] ?? $parent_cfg);
          $cfg['inherit_from'] = $p;
          $cfg['frozen']       = 1;
          break;
        }
        // If the current config is empty and the parent's inheritance value is 'default', use the parent's scfg or config.
        elseif (
          !count($cfg)
          && ((($parent_cfg['inheritance'] ?? null) === 'default')
            || (!empty($parent_cfg['scfg']) && (($parent_cfg['scfg']['inheritance'] ?? null) === 'default')))
        ) {
          $cfg                 = $parent_cfg['scfg'] ?? $parent_cfg;
          $cfg['inherit_from'] = $p;
        }
      }
    }

    if (empty($cfg) && ($id_alias = $this->getIdAlias($id)) && $this->isInTemplate($id_alias)) {
      $cfg['inherit_from'] = $id_alias;
      $cfg['frozen']       = 1;
    }

    if ($cfg && !empty($cfg['inherit_from'])) {
      $cfg['inherit_from_text'] = $this->text($cfg['inherit_from']);
    }

    // Restore permissions if they were present initially.
    if ($perm) {
      $cfg['permissions'] = $perm;
    }

    // Set default values for mandatory fields.
    $mandatories = ['show_code', 'show_value', 'show_icon', 'sortable', 'allow_children', 'frozen'];
    foreach ($mandatories as $m) {
      $cfg[$m] = empty($cfg[$m]) ? 0 : 1;
    }

    // Set default values for fields that should be strings.
    $mandatories = ['desc', 'inheritance', 'relations', 'permissions', 'i18n', 'i18n_inheritance'];
    foreach ($mandatories as $m) {
      $cfg[$m] = empty($cfg[$m]) ? '' : $cfg[$m];
    }

    // Set default values for fields that should be null.
    $mandatories = ['controller', 'schema', 'form', 'default_value', 'id_root_alias'];
    foreach ($mandatories as $m) {
      $cfg[$m] = empty($cfg[$m]) ? null : $cfg[$m];
    }

    $cfg['root_alias'] = null;
    if ($this->root && $this->default && !empty($cfg['id_root_alias'])) {
      $cfg['root_alias'] = $this->option($cfg['id_root_alias']);
      if (!empty($cfg['root_alias']['num_children'])
          && ($items = $this->items($cfg['id_root_alias']))
      ) {
        $cfg['root_alias']['last_level'] = true;
        foreach ($items as $item) {
          if ($this->items($item)) {
            $cfg['root_alias']['last_level'] = false;
            break;
          }
        }

        if ($cfg['root_alias']['last_level']
            && ($last_level_children = $this->fullOptions($cfg['id_root_alias']))
        ) {
          X::sortBy($last_level_children, 'text');
          $cfg['root_alias']['last_level_children'] = $last_level_children;
        }
      }
    }

    // Cache the result and return it.
    $this->setCache($id, __FUNCTION__, $cfg);
    return $cfg;
  }


  /**
   * Returns the raw content of the cfg column for the given option.
   *
   * @param mixed ...$codes Any option(s) accepted by fromCode()
   * @return array The raw cfg value or null if the option cannot be found
   */
  public function getRawCfg(...$codes): array
  {
    // Get the ID of the option from its code.
    $id = $this->fromCode($codes);

    // Check if the ID is valid and retrieve the raw cfg value from the database.
    if (Str::isUid($id)) {
      $c = &$this->class_cfg;
      $f = &$this->fields;
      if ($json = $this->db->selectOne($c['table'], $f['cfg'], [$f['id'] => $id])) {
        $res = json_decode($json, true);
        $change = false;
        if (!empty($res['schema'])) {
          if (is_string($res['schema'])) {
            $res['schema'] = json_decode($res['schema'], true);
            $change = true;
          }
          foreach ($res['schema'] as &$v) {
            if (!empty($v['title']) && empty($v['label'])) {
              $v['label'] = $v['title'];
              unset($v['title']);
              $change = true;
            }
          }
          unset($v);
        }
        if (!empty($res['scfg']) && !empty($res['scfg']['schema'])) {
          if (is_string($res['scfg']['schema'])) {
            $res['scfg']['schema'] = json_decode($res['scfg']['schema'], true);
            $change = true;
          }
          foreach ($res['scfg']['schema'] as &$v) {
            if (!empty($v['title']) && empty($v['label'])) {
              $v['label'] = $v['title'];
              unset($v['title']);
              $change = true;
            }
          }
          unset($v);
        }

        if ($change) {
          X::log($id, 'correct-options-schema');
          $this->setCfg($id, $res);
        }

        return $res;
      }
    }

    return [];
  }

  /**
   * Returns a formatted content of the cfg column as an array from the option's parent.
   *
   * @param mixed ...$codes Any option(s) accepted by fromCode()
   * @return array|null The formatted config or null if the option cannot be found
   */
  public function getApplicableCfg(...$codes): ?array
  {
    // Get the ID of the option from its code.
    $id = $this->fromCode($codes);

    if (!Str::isUid($id)) {
      // If the ID is not valid, return null.
      throw new Exception(X::_("Invalid option ID"));
    }

    // Check if the result is cached.
    if ($tmp = $this->getCache($id, __FUNCTION__)) {
      return $tmp;
    }

    // Check if the ID is valid and retrieve the parent's config.
    if ($id && ($id_parent = $this->getIdParent($id))) {
      // Get references to class configuration and fields.
      $c   = &$this->class_cfg;
      $f   = &$this->fields;
      
      $itemCfg = $this->db->selectOne($c['table'], $f['cfg'], [$f['id'] => $id]);
      $itemCfg = Str::isJson($itemCfg) ? json_decode($itemCfg, true) : [];
      $id_alias = $this->alias($id_parent);
      $inherit = false;
      if ($id_alias && $this->hasTemplate($id_parent)) {
        $cfg = $this->db->selectOne($c['table'], $f['cfg'], [$f['id'] => $id_alias]);
        $id_parent = $id_alias;
        $inherit = $id_alias;
      }
      else {
        // Retrieve the cfg value from the database.
        $cfg = $this->db->selectOne($c['table'], $f['cfg'], [$f['id'] => $id_parent]);
        $inherit = $id_parent;
      }

      // Decode the JSON string to an array if possible, otherwise initialize as empty array.
      $cfg = Str::isJson($cfg) ? json_decode($cfg, true) : [];

      if ($id === '24307e41648d11eab7ec525400007196') {
        //X::ddump("cfg", $cfg, "id", $id, "umherit", $inherit, "id_parent", $id_parent, "id_alias", $id_alias, "itemCfg", $itemCfg);
      }

      // Check for permissions and store them in the config array.
      $perm = isset($itemCfg['permissions']) && ($itemCfg['permissions'] === 'single') ? true : ($cfg['permissions'] ?? false);
      // Look for parent options with inheritance.
      $parents = array_reverse($this->parents($id_parent));
      $lastIdx = count($parents) - 1;
      $last    = end($parents);
      $parent_cfg = $this->db->selectOne($c['table'], $f['cfg'], [$f['id'] => $last]);
      // Decode the JSON string to an array if possible, otherwise initialize as empty array.
      $parent_cfg = Str::isJson($parent_cfg) ? json_decode($parent_cfg, true) : [];
      // Check for inheritance in the parent's config or scfg.
      if (!empty($parent_cfg['scfg'])) {
        // Merge the current config with the parent's scfg and set inherit_from and frozen.
        $inherit       = $last;
        $cfg           = array_merge((array)$cfg, $parent_cfg['scfg']);
        $cfg['frozen'] = 1;
      }
      else {
        // Iterate through the parents to find one with inheritance.
        foreach ($parents as $i => $p) {
          // Retrieve the config of the parent option.
          $parent_cfg = $this->db->selectOne($c['table'], $f['cfg'], [$f['id'] => $p]);
          // Decode the JSON string to an array if possible, otherwise initialize as empty array.
          $parent_cfg = Str::isJson($parent_cfg) ? json_decode($parent_cfg, true) : [];
          // Check for inheritance in the parent's config or scfg.
          if (!empty($parent_cfg['inheritance']) || !empty($parent_cfg['scfg']['inheritance'])) {
            // Check if the parent is a direct parent and its inheritance value is 'children' or 'cascade'.
            if (
              (($i === ($lastIdx-1))
                && (
                  (($parent_cfg['inheritance'] ?? null) === 'children')
                  || (!empty($parent_cfg['scfg']) && (($parent_cfg['scfg']['inheritance'] ?? null) === 'children'))
                )
              )
              || (
                (($parent_cfg['inheritance'] ?? null) === 'cascade')
                || (!empty($parent_cfg['scfg']) && (($parent_cfg['scfg']['inheritance'] ?? null) === 'cascade'))
              )
            ) {
              // Merge the current config with the parent's scfg or config, and set inherit_from and frozen.
              $inherit       = $p;
              $cfg           = array_merge((array)$cfg, $parent_cfg['scfg'] ?? $parent_cfg);
              $cfg['frozen'] = 1;
              break;
            }
            // If the current config is empty and the parent's inheritance value is 'default', use the parent's scfg or config.
            elseif (
              empty($cfg)
              && ((($parent_cfg['inheritance'] ?? null) === 'default')
                || (!empty($parent_cfg['scfg']) && (($parent_cfg['scfg']['inheritance'] ?? null) === 'default')))
            ) {
              $cfg                 = $parent_cfg['scfg'] ?? $parent_cfg;
              $inherit = $p;
            }
          }
        }
      }

      if ($inherit) {
        $cfg['inherit_from'] = $inherit;
        $cfg['inherit_from_text'] = $this->text($cfg['inherit_from']);
      }

      // Restore permissions if they were present initially.
      if ($perm) {
        $cfg['permissions'] = $perm;
      }

      // Set default values for mandatory fields.
      $mandatories = ['show_code', 'show_value', 'show_icon', 'sortable', 'allow_children', 'frozen'];
      foreach ($mandatories as $m) {
        $cfg[$m] = empty($cfg[$m]) ? 0 : 1;
      }

      // Set default values for fields that should be strings.
      $mandatories = ['desc', 'inheritance', 'relations', 'permissions', 'i18n', 'i18n_inheritance'];
      foreach ($mandatories as $m) {
        $cfg[$m] = empty($cfg[$m]) ? '' : $cfg[$m];
      }

      // Set default values for fields that should be null.
      $mandatories = ['controller', 'schema', 'form', 'default_value', 'id_root_alias'];
      foreach ($mandatories as $m) {
        $cfg[$m] = empty($cfg[$m]) ? null : $cfg[$m];
      }

      $cfg['root_alias'] = null;
      if ($this->root && $this->default && !empty($cfg['id_root_alias'])) {
        $cfg['root_alias'] = $this->option($cfg['id_root_alias']);
        if (!empty($cfg['root_alias']['num_children'])
            && ($items = $this->items($cfg['id_root_alias']))
        ) {
          $cfg['root_alias']['last_level'] = true;
          foreach ($items as $item) {
            if ($this->items($item)) {
              $cfg['root_alias']['last_level'] = false;
              break;
            }
          }

          if ($cfg['root_alias']['last_level']
              && ($last_level_children = $this->fullOptions($cfg['id_root_alias']))
          ) {
            X::sortBy($last_level_children, 'text');
            $cfg['root_alias']['last_level_children'] = $last_level_children;
          }
        }
      }

        // Cache the result and return it.
      $this->setCache($id, __FUNCTION__, $cfg);
      return $cfg;
    }

    return null;
  }


  /**
   * Tells if an option has its config set as sortable or no
   *
   * @param mixed ...$codes Any option(s) accepted by fromCode()
   * @return bool|null Whether the option is sortable or null if the option cannot be found
   */
  public function isSortable(...$codes): ?bool
  {
    // Get the ID of the option from its code.
    $id = $this->fromCode($codes);

    // Check if the ID is valid and retrieve the config to check for sortability.
    if (Str::isUid($id)) {
      $cfg = $this->getCfg($id);
      return !empty($cfg['sortable']);
    }

    return null;
  }


  /**
   * Retrieves the schema of an option.
   *
   * @param string $id The ID of the option
   * @return array|null The schema or null if it cannot be found
   */
  public function getSchema(string $id): ?array
  {
    // Retrieve the config to check for a schema.
    if ($cfg = $this->getCfg($id)) {
      // Check if a schema is defined and decode it from JSON.
      if (!empty($cfg['schema'])) {
        if (is_string($cfg['schema'])) {
          return json_decode($cfg['schema'], true);
        }

        return $cfg['schema'];
      }
    }

    return null;
  }
}
