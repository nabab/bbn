<?php

namespace bbn\Appui\Option;

use bbn\Str;

/**
 * The Code trait provides methods for working with options' codes.
 */
trait Code
{
  /**
   * Retrieves an option's ID from its "codes path"
   *
   * This method can handle diverse combinations of elements, such as:
   * - A code or a series of codes from the most specific to a child of the root
   * - A code or a series of codes and an id_parent where to find the last code
   * - A code alone having $this->default as parent
   *
   * @param string ...$codes The option's code(s)
   * @return string|null The ID of the option, null if not found, or false if the row cannot be found
   */
  public function fromCode(...$codes): ?string
  {
    // Check if the class is initialized and the database connection is valid.
    if ($this->check()) {
      // If the input is an array, extract its elements as separate arguments.
      while (isset($codes[0]) && \is_array($codes[0])) {
        $codes = $codes[0];
      }

      // Check if we have an option array as a parameter and return its ID directly.
      if (isset($codes[$this->fields['id']])) {
        return $codes[$this->fields['id']];
      }

      // Get the number of arguments provided.
      $num = \count($codes);

      // If no arguments are provided, return null.
      if (!$num) {
        return null;
      }

      // Check for a special case where false is accepted as id_parent for root.
      if (($num === 1) && ($codes[0] === false)) {
        return $this->default;
      }

      // If the first argument is a valid UID, check if it's an existing option ID or proceed with further checks.
      if (Str::isUid($codes[0])) {
        if ($num === 1) {
          return $codes[0];
        }

        // Perform an extra check to ensure the provided ID corresponds to its parent.
        if ($this->getIdParent($codes[0]) === $this->fromCode(...\array_slice($codes, 1))) {
          return $codes[0];
        }
      }

      // Check if the first argument is a valid alphanumeric code.
      if (empty($codes) || (!\is_string($codes[0]) && !is_numeric($codes[0]))) {
        return null;
      }

      // Handle special cases for certain codes, such as 'appui' or 'plugins'.
      if (end($codes) === 'appui') {
        $codes[] = 'plugins';
        $num++;
      }
      // Ensure that the last argument is a valid UID; otherwise, append the default value.
      if (!Str::isUid(end($codes))) {
        $codes[] = $this->default;
        $num++;
      }

      // At this stage, we need at least one code and one ID to proceed with the query.
      if ($num < 2) {
        return null;
      }

      // Extract the parent ID and true code from the arguments.
      $id_parent = array_pop($codes);
      $true_code = array_pop($codes);
      $enc_code  = $true_code ? base64_encode($true_code) : 'null';

      // Define the cache name based on the encoded code.
      $cache_name = 'get_code_' . $enc_code;

      // Check if a cached result is available for the given parent ID and cache name.
      if (($tmp = $this->cacheGet($id_parent, $cache_name))) {
        // If no more arguments are provided, return the cached result directly.
        if (!count($codes)) {
          return $tmp;
        }

        // Otherwise, append the cached result to the remaining arguments and proceed recursively.
        $codes[] = $tmp;
        return $this->fromCode(...$codes);
      }

      // Perform a database query to find an option matching the provided code and parent ID.
      $c = &$this->class_cfg;
      $f = &$this->fields;

      /** @var int|false $tmp */
      if ($tmp = $this->db->selectOne(
        $c['table'],
        $f['id'],
        [
          [$f['id_parent'], '=', $id_parent],
          [$f['code'], '=', $true_code]
        ]
      )) {
        // Cache the result for future queries.
        $this->cacheSet($id_parent, $cache_name, $tmp);
      }
      // If no direct match is found, attempt to find a magic code option that bypasses the normal matching logic.
      elseif (($tmp2 = $this->db->selectOne(
          $c['table'],
          $f['id'],
          [
            $f['id_parent'] => $id_parent,
            $f['id_alias'] => $this->getMagicOptionsTemplateId()
          ]
        ))
        && ($tmp = $this->db->selectOne(
          $c['table'],
          $f['id'],
          [
            [$f['id_parent'], '=', $tmp2],
            [$f['code'], '=', $true_code]
          ]
        ))
      ) {
        // Cache the result for future queries.
        $this->cacheSet($id_parent, $cache_name, $tmp);
      }
      // If still no match is found, attempt to follow an alias with a matching code.
      else {
        $aliases = $this->db->getColumnValues($c['table'], $f['id_alias'], [
          $f['id_parent'] => $id_parent,
          [$f['id_alias'], 'isnotnull'],
          [$f['text'], 'isnull']
        ]);
        $done = [];
        foreach ($aliases as $a) {
          if ($a && !in_array($a, $done, true)) {
            $done[] = $a;
            // Check if the alias has a matching code and cache the result if found.
            if ($this->code($a) === $true_code) {
              $this->cacheSet($id_parent, $cache_name, $tmp);
              break;
            }
          }
        }
      }

      // If a match is found, return the cached result or proceed recursively with the remaining arguments.
      if ($tmp) {
        if (\count($codes)) {
          $codes[] = $tmp;
          return $this->fromCode(...$codes);
        }

        return $tmp;
      }
    }

    return null;
  }


  /**
   * Retrieves an option's ID from its code path, starting from the root.
   *
   * @return string|null
   */
  public function fromRootCode(): ?string
  {
    // Save the default value and set it to the root for this query.
    if ($this->check()) {
      $def = $this->default;
      $this->setDefault($this->root);
      // Proceed with the query using the updated default value.
      $res = $this->fromCode(...func_get_args());
      // Restore the original default value after the query.
      $this->setDefault($def);
      return $res;
    }

    return null;
  }


  /**
   * Returns an array of options in the form id => code.
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array Options' array
   */
  public function getCodes(...$codes): array
  {
    // Check if a valid ID is provided or can be resolved from the given codes.
    if (Str::isUid($id = $this->fromCode(...$codes))) {
      $c   = &$this->fields;
      // Retrieve all options with their IDs and codes, sorted by either the 'num' or 'code' field depending on whether the parent option is sortable.
      $opt = $this->db->rselectAll($this->class_cfg['table'], [$c['id'], $c['code']], [$c['id_parent'] => $id], [($this->isSortable($id) ? $c['num'] : $c['code']) => 'ASC']);
      $res = [];
      // Iterate over the retrieved options and populate the result array with their IDs and codes.
      foreach ($opt as $r) {
        if (!empty($r[$c['code']]) && Str::isInteger($r[$c['code']])) {
          $r[$c['code']] = (int)$r[$c['code']];
        }
        $res[$r[$c['id']]] = $r[$c['code']];
      }

      return $res;
    }

    return [];
  }


  /**
   * Returns an option's code.
   *
   * @param string $id The options' ID
   * @return string|null The code value, null is none, false if option not found
   */
  public function code(string $id): ?string
  {
    // Check if a valid ID is provided and the instance is properly initialized.
    if ($this->check() && Str::isUid($id)) {
      // Retrieve the code for the given ID from the database.
      $code = $this->db->selectOne(
        $this->class_cfg['table'],
        $this->fields['code'],
        [
          $this->fields['id'] => $id
        ]
      );
      // If the retrieved code is an integer, cast it to an integer for consistency.
      if (!empty($code) && Str::isInteger($code)) {
        $code = (int)$code;
      }
      return $code;
    }

    return null;
  }
}