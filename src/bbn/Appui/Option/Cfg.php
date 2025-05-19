<?php

namespace bbn\Appui\Option;

use bbn\Str;

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

    // Check if the ID is valid and if the result is cached.
    if (Str::isUid($id) && ($tmp = $this->getCache($id, __FUNCTION__))) {
      return $tmp;
    }

    // Get references to class configuration and fields.
    $c   = &$this->class_cfg;
    $f   = &$this->fields;

    $id_alias = $this->db->selectOne($c['table'], $f['id_alias'], [$f['id'] => $id]);
    if ($id_alias && $this->hasTemplate($id)) {
      $cfg = $this->db->selectOne($c['table'], $f['cfg'], [$f['id'] => $id_alias]);
      $id = $id_alias;
    }
    else {
      // Retrieve the cfg value from the database.
      $cfg = $this->db->selectOne($c['table'], $f['cfg'], [$f['id'] => $id]);
    }

    // Decode the JSON string to an array if possible, otherwise initialize as empty array.
    $cfg = Str::isJson($cfg) ? json_decode($cfg, true) : [];

    // Check for permissions and store them in the config array.
    $perm = $cfg['permissions'] ?? false;

    // Look for parent options with inheritance.
    $parents = array_reverse($this->parents($id));
    $last    = count($parents) - 1;

    // Iterate through the parents to find one with inheritance.
    foreach ($parents as $i => $p) {
      // Retrieve the config of the parent option.
      $parent_cfg = $this->db->selectOne($c['table'], $f['cfg'], [$f['id'] => $p]);

      // Decode the JSON string to an array if possible, otherwise initialize as empty array.
      $parent_cfg = Str::isJson($parent_cfg) ? json_decode($parent_cfg, true) : [];

      // Check for inheritance in the parent's config or scfg.
      if (!empty($parent_cfg['scfg']) && ($i === $last)) {
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
          (($i === $last)
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
    $mandatories = ['controller', 'schema', 'form', 'default_value'];
    foreach ($mandatories as $m) {
      $cfg[$m] = empty($cfg[$m]) ? null : $cfg[$m];
    }

    // Cache the result and return it.
    $this->setCache($id, __FUNCTION__, $cfg);
    return $cfg;
  }


  /**
   * Returns the raw content of the cfg column for the given option.
   *
   * @param mixed ...$codes Any option(s) accepted by fromCode()
   * @return string|null The raw cfg value or null if the option cannot be found
   */
  public function getRawCfg(...$codes): ?string
  {
    // Get the ID of the option from its code.
    $id = $this->fromCode($codes);

    // Check if the ID is valid and retrieve the raw cfg value from the database.
    if (Str::isUid($id)) {
      $c = &$this->class_cfg;
      $f = &$this->fields;
      return $this->db->selectOne($c['table'], $f['cfg'], [$f['id'] => $id]);
    }

    return null;
  }


  /**
   * Returns a formatted content of the cfg column as an array from the option's parent.
   *
   * @param mixed ...$codes Any option(s) accepted by fromCode()
   * @return array|null The formatted config or null if the option cannot be found
   */
  public function getParentCfg(...$codes): ?array
  {
    // Get the ID of the option from its code.
    $id = $this->fromCode($codes);

    // Check if the ID is valid and retrieve the parent's config.
    if ($id && ($id_parent = $this->getIdParent($id))) {
      return $this->getCfg($id_parent);
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
