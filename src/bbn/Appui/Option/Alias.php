<?php

namespace bbn\Appui\Option;

use bbn\Str;

/**
 * Trait providing functionality for handling aliases in options.
 */
trait Alias
{
  /**
   * Returns the id_alias relative to the given id_option.
   *
   * @param string $id The ID of the option.
   * @return string|null The id_alias if it exists, otherwise null.
   */
  public function alias(string $id): ?string
  {
    // Check if the class is initialized and the database connection is valid.
    if ($this->check() && Str::isUid($id)) {
      // Query the database to retrieve the id_alias.
      return $this->db->selectOne(
        $this->class_cfg['table'],
        $this->fields['id_alias'],
        [
          $this->fields['id'] => $id
        ]
      );
    }

    // If checks fail, return null.
    return null;
  }

  /**
   * Returns the id_alias for a given code(s).
   *
   * @param string ...$codes The codes to retrieve the id_alias for.
   * @return string|null The id_alias if it exists, otherwise null.
   */
  public function getIdAlias(...$codes): ?string
  {
    // Get the ID from the provided codes.
    if (Str::isUid($id = $this->fromCode($codes))) {
      // Retrieve the class configuration and query the database to get the id_alias.
      $cf = $this->getClassCfg();
      return $this->db->selectOne(
        $cf['table'],
        $this->fields['id_alias'],
        [
          $this->fields['id'] => $id
        ]
      );
    }

    // If checks fail, return null.
    return null;
  }

  /**
   * Retrieves all aliases for a given code(s).
   *
   * @param string ...$codes The codes to retrieve the aliases for.
   * @return array|null An array of aliases if they exist, otherwise null.
   */
  public function getAliases(...$codes): ?array
  {
    // Get the ID from the provided codes.
    if (Str::isUid($id = $this->fromCode($codes))) {
      // Initialize an empty array to store the results.
      $r = [];

      // Retrieve the class configuration and query the database to get all aliases.
      $cf = $this->getClassCfg();
      if ($results = $this->db->rselectAll(
        $cf['table'],
        [],
        [
          $this->fields['id_alias'] => $id
        ]
      )) {
        // Iterate through each result and process the data.
        foreach ($results as $d) {
          // Convert code to integer if it's an integer string.
          if (
            !empty($d[$this->fields['code']])
            && Str::isInteger($d[$this->fields['code']])
          ) {
            $d[$this->fields['code']] = (int)$d[$this->fields['code']];
          }
          // Set the value and retrieve the text for the alias.
          $this->_set_value($d);
          if (!empty($d[$this->fields['text']])) {
            $d[$this->fields['text']] = $this->text($d[$this->fields['id']]);
          }
          // Add the processed data to the results array.
          $r[] = $d;
        }
      }

      // Return the array of aliases.
      return $r;
    }

    // If checks fail, return null.
    return null;
  }

  /**
   * Retrieves all items for an alias based on a given code(s).
   *
   * @param string ...$codes The codes to retrieve the alias items for.
   * @return array|null An array of item IDs if they exist, otherwise null.
   */
  public function getAliasItems(...$codes): ?array
  {
    // Get the ID from the provided codes.
    if (Str::isUid($id = $this->fromCode($codes))) {
      // Check if the result is cached.
      if ($res = $this->getCache($id, __FUNCTION__)) {
        return $res;
      }

      // Retrieve the class configuration and query the database to get all items for the alias.
      $cf  = $this->getClassCfg();
      $f   = $this->getFields();
      $res = $this->db->getColumnValues(
        $cf['table'],
        $f['id'],
        [
          $f['id_alias'] => $id
        ]
      );

      // Cache the result and return it.
      $this->setCache($id, __FUNCTION__, $res);
      return $res;
    }

    // If checks fail, return null.
    return null;
  }

  /**
   * Retrieves all options for an alias based on a given code(s).
   *
   * @param string ...$codes The codes to retrieve the alias options for.
   * @return array|null An array of option IDs and their corresponding text if they exist, otherwise null.
   */
  public function getAliasOptions(...$codes): ?array
  {
    // Get the ID from the provided codes.
    if (Str::isUid($id = $this->fromCode($codes))) {
      // Check if the result is cached.
      if ($r = $this->getCache($id, __FUNCTION__)) {
        return $r;
      }

      // Initialize an empty array to store the results.
      $res = [];

      // Retrieve all items for the alias and process them into options.
      if ($items = $this->getAliasItems($id)) {
        foreach ($items as $it) {
          $res[$it] = $this->text($it);
        }
      }

      // Cache the result and return it.
      $this->setCache($id, __FUNCTION__, $res);
      return $res;
    }

    // If checks fail, return null.
    return null;
  }

  /**
   * Retrieves all full options for an alias based on a given code(s).
   *
   * @param string ...$codes The codes to retrieve the full alias options for.
   * @return array|null An array of full options if they exist, otherwise null.
   */
  public function getAliasFullOptions(...$codes): ?array
  {
    // Get the ID from the provided codes.
    if (Str::isUid($id = $this->fromCode($codes))) {
      // Check if the result is cached.
      if ($r = $this->getCache($id, __FUNCTION__)) {
        return $r;
      }

      // Initialize an empty array to store the results.
      $res = [];

      // Retrieve all items for the alias and process them into full options.
      if ($items = $this->getAliasItems($id)) {
        foreach ($items as $it) {
          $res[] = $this->option($it);
        }
      }

      // Cache the result and return it.
      $this->setCache($id, __FUNCTION__, $res);
      return $res;
    }

    // If checks fail, return null.
    return null;
  }

  /**
   * Retrieves all options based on their id_alias.
   *
   * @param string ...$codes The codes to retrieve the options for.
   * @return array|null An array of options if they exist, otherwise null.
   */
  public function optionsByAlias(...$codes): ?array
  {
    // Get the ID from the provided codes.
    $id_alias = $this->fromCode($codes);

    // Check if the id_alias is a valid UID.
    if (Str::isUid($id_alias)) {
      // Create a where condition for the query.
      $where = [
        $this->fields['id_alias'] => $id_alias
      ];

      // Query the database to retrieve all options based on their id_alias.
      $list  = $this->getRows($where);

      // Check if the result is an array.
      if (\is_array($list)) {
        // Initialize an empty array to store the processed results.
        $res = [];

        // Iterate through each option and process it.
        foreach ($list as $i) {
          $res[] = $this->option($i);
        }

        // Return the array of processed options.
        return $res;
      }
    }

    // If checks fail, return null.
    return null;
  }
}
