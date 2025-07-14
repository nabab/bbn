<?php

namespace bbn\Appui\Option\Internal;

use bbn\X;
use bbn\Str;

/**
 * The Code trait provides methods for working with options' codes.
 */
trait Code
{
  private function _fromCode(array $codes, bool $real = false, $depth = 0): ?string
  {
    if ($this->check()) {
      // Get the number of arguments provided.
      $num = \count($codes);

      // If no arguments are provided, return null.
      if (!$num) {
        return null;
      }

      if ($codes[0] === false) {
        return $this->getRoot();
      }

      // If the first argument is a valid UID, check if it's an existing option ID or proceed with further checks.
      if (Str::isUid($codes[0])) {
        if ($num > 1) {
          // Perform an extra check to ensure the provided ID corresponds to its parent.
          $parentFromCodes = $this->_fromCode(\array_slice($codes, 1), $real, $depth + 1);
          $parentId = $this->getIdParent($codes[0]);
          if ($parentId !== $parentFromCodes) {
            $parentId = $this->getIdParent($parentId);
            if ($parentId !== $parentFromCodes) {
              return null;
            }
          }
        }

        return $codes[0];
      }

      // Check if the first argument is a valid alphanumeric code.
      if (empty($codes) || (!\is_string($codes[0]) && !is_numeric($codes[0]))) {
        return null;
      }

      // Handle special cases for certain codes, such as 'appui' or 'plugins'.
      
      $lastCode = end($codes);
      // Ensure that the last argument is a valid UID; otherwise, append the default value.
      if (!$depth) {
        if ($lastCode === 'appui') {
          $codes[] = 'plugins';
          $lastCode = 'plugins';
          $num++;
        }

        if (!Str::isUid($lastCode)) {
          if ($lastCode === false) {
            array_pop($codes);
            $codes[] = $this->getRoot();
          }
          else {
            if ($lastCode === null) {
              array_pop($codes);
              $num--;
            }

            if (!in_array($lastCode, ['options', 'plugins', 'permissions'])) {
              $codes[] = 'options';
              $num++;
            }

            $codes[] = $this->getDefault();
            $num++;
          }
        }
      }

      //X::log($codes, 'codes');
      // At this stage, we need at least one code and one ID to proceed with the query.
      if ($num < 2) {
        return null;
      }

      // Extract the parent ID and true code from the arguments.
      $id_parent = array_pop($codes);
      $true_code = array_pop($codes);
      $enc_code  = $true_code ? base64_encode($true_code . ($real ? '1' : '2')) : 'null';
      // Define the cache name based on the encoded code.
      $cache_name = 'get_code_' . $enc_code;
      // Check if a cached result is available for the given parent ID and cache name.
      if (($tmp = $this->getCache($id_parent, $cache_name))) {
        // If no more arguments are provided, return the cached result directly.
        if (!count($codes)) {
          return $tmp;
        }

        // Otherwise, append the cached result to the remaining arguments and proceed recursively.
        $codes[] = $tmp;
        return $this->_fromCode($codes, $real, $depth + 1);
      }

      // Perform a database query to find an option matching the provided code and parent ID.
      $c = &$this->class_cfg;
      $f = &$this->fields;
      $rightValue = null;
      $done = false;

      /** @var int|false $tmp */
      if ($tmp = $this->db->selectOne(
        $c['table'],
        $f['id'],
        [
          [$f['id_parent'], '=', $id_parent],
          [$f['code'], '=', $true_code]
        ]
      )) {
        $done = true;
        $rightValue = $tmp;
      }
      // If still no match is found, attempt to follow an alias with a matching code.
      elseif (!$real && ($tmp = $this->db->selectOne([
        'table' => $c['table'],
        'fields' => [$c['table'] . '.' . $f['id']],
        'join' => [[
          'table' => $c['table'],
          'alias' => 'o1',
          'on' => [
            [
              'field' => 'o1.' . $f['id'],
              'exp' => $c['table'] . '.' . $f['id_alias']
            ]
          ]
        ]],
        'where' => [
          [$c['table'] . '.' . $f['id_parent'], '=', $id_parent],
          ['o1.' . $f['code'], 'LIKE', $true_code]
        ]
      ]))) {
        $rightValue = $tmp;
      }
      // If no direct match is found, attempt to find a magic code option that bypasses the normal matching logic.
      elseif (!$real && $this->getMagicTemplateId()) {
        if ($tmp2 = $this->db->selectOne(
            $c['table'],
            $f['id'],
            [
              $f['id_parent'] => $id_parent,
              $f['id_alias'] => [$this->getOptionsTemplateId(), $this->getSubOptionsTemplateId()]
            ]
          )
        ) {
          if  ($tmp = $this->db->selectOne(
            $c['table'],
            $f['id'],
            [
              [$f['id_parent'], '=', $tmp2],
              [$f['code'], '=', $true_code]
            ]
          )) {
            $rightValue = $tmp;
          }
          elseif ($tmp = $this->db->selectOne([
            'table' => $c['table'],
            'fields' => [$c['table'] . '.' . $f['id_alias']],
            'join' => [[
              'table' => $c['table'],
              'alias' => 'o1',
              'on' => [
                [
                  'field' => 'o1.' . $f['id'],
                  'exp' => $c['table'] . '.' . $f['id_alias']
                ]
              ]
            ]],
            'where' => [
              $c['table'] . '.' . $f['id_parent'] => $tmp2,
              'o1.' . $f['code'] => $true_code
            ]
          ])) {
            $rightValue = $tmp;
          }
        }
      }

      if (!$rightValue && !$real && !$this->count($id_parent) && ($parent = $this->option($id_parent)) && $parent['id_alias'] && empty($parent['code']) && ($tmp = $this->db->selectOne(
        $c['table'],
        $f['id'],
        [
          [$f['id_parent'], '=', $parent['id_alias']],
          [$f['code'], '=', $true_code]
        ]
      ))) {
        $rightValue = $tmp;
      }


      // If a match is found, return the cached result or proceed recursively with the remaining arguments.
      if ($rightValue) {
        //X::hdump([$depth, $this->nativeOption($rightValue), $this->fullOptions($rightValue)]);

        if (!$real && ($opt = $this->nativeOption($rightValue)) && !empty($opt['id_alias'])) {
          if (in_array($opt['id_alias'], [$this->getPluginTemplateId(), $this->getSubpluginTemplateId()])) {
            if (!\count($codes) || !in_array(end($codes), ['options', 'plugins', 'permissions', 'templates'])) {
              $codes[] = 'options';
            }
          }
          elseif (empty($opt['text'])) {
            $alias = $this->nativeOption($opt['id_alias']);
            if ($alias['id_alias'] && in_array($alias['id_alias'], [$this->getPluginTemplateId(), $this->getSubpluginTemplateId()])) {
              $rightValue = $opt['id_alias'];
              if (!\count($codes) || !in_array(end($codes), ['options', 'plugins', 'permissions', 'templates'])) {
                $codes[] = 'options';
              }
            }
          }
        }

        if (\count($codes)) {
          $codes[] = $rightValue;
          return $this->_fromCode($codes, $real, $depth + 1);
        }

        return $rightValue;
      }
    }

    return null;
  }
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
    // If the input is an array, extract its elements as separate arguments.
    while (isset($codes[0]) && \is_array($codes[0])) {
      $codes = $codes[0];
    }
    
    // Check if we have an option array as a parameter and return its ID directly.
    if (isset($codes[$this->fields['id']])) {
      return $codes[$this->fields['id']];
    }

    return $this->_fromCode($codes);
  }

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
  public function fromRealCode(...$codes): ?string
  {
    // Check if the class is initialized and the database connection is valid.
    // If the input is an array, extract its elements as separate arguments.
    while (isset($codes[0]) && \is_array($codes[0])) {
      $codes = $codes[0];
    }
    
    // Check if we have an option array as a parameter and return its ID directly.
    if (isset($codes[$this->fields['id']])) {
      return $codes[$this->fields['id']];
    }

    return $this->_fromCode($codes, true);
  }


  /**
   * Retrieves an option's ID from its code path, starting from the root.
   *
   * @return string|null
   */
  public function fromRootCode(...$codes): ?string
  {
    // Save the default value and set it to the root for this query.
    if ($this->check()) {
      $def = $this->default;
      // Proceed with the query using the updated default value.

      $codes[] = false;
      $res = $this->fromCode(...$codes);
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
  public function code(string $id, $followAlias = true): ?string
  {
    // Check if a valid ID is provided and the instance is properly initialized.
    if ($this->check() && Str::isUid($id)) {
      $o = $this->nativeOption($id);
      if (!$o['code'] && $followAlias && $o['id_alias']) {
        $o = $this->nativeOption($o['id_alias']);
      }
      // Retrieve the code for the given ID from the database.
      $code = $o['code'] ?? null;

      // If the retrieved code is an integer, cast it to an integer for consistency.
      if (!empty($code) && Str::isInteger($code)) {
        $code = (int)$code;
      }

      return $code;
    }

    return null;
  }

  public function toCodeArray($id): ?array
  {
    $path = $this->toPath($id, '/');
    if (!$path) {
      return null;
    }

    return array_reverse(X::split($path, '/'));
  }
}
