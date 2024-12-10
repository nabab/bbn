<?php

namespace bbn\Appui\Option;

/**
 * Trait Cat provides methods to manage categories of options.
 */
trait Cat
{
  // Returns a list of categories as an indexed array on their 'id'.
  /**
   * Returns a list of categories.
   *
   * @return array The list of categories.
   */
  public function categories(): array
  {
    return $this->options(false);
  }

  // Retrieves the list of categories indexed by their 'id' in the form of a text/value array.
  /**
   * Returns the list of categories as a text/value array.
   *
   * @return null|array The list of categories as a text/value array, or null if no categories are found.
   */
  public function textValueCategories(): ?array
  {
    // Check if categories exist and retrieve them in the form of an indexed array on their 'id'.
    if ($rs = $this->options(false)) {
      // Initialize an empty array to store the list of categories as a text/value array.
      $res = [];

      // Iterate over each category and create a corresponding text/value entry.
      foreach ($rs as $val => $text) {
        $res[] = ['text' => $text, 'value' => $val];
      }

      return $res;
    }

    return null;
  }

  // Returns all characteristics of the options in a given category as an indexed array on their 'id'.
  /**
   * Returns all characteristics of the options in a given category.
   *
   * @return array An array of characteristics for each option in the category, indexed by their 'id'.
   */
  public function fullCategories(): array
  {
    // Retrieve the full options and iterate over them to modify their default values.
    if ($opts = $this->fullOptions(false)) {
      foreach ($opts as $k => $o) {
        // If a default value exists, replace it with its corresponding text representation.
        if (!empty($o['default'])) {
          $opts[$k]['default'] = $this->text($o['default']);
        }
      }
    }

    return $opts ?? [];
  }

  /**
   * Returns all characteristics of the options in a given category as an indexed array on their 'id'.
   *
   * @param string|null $id The ID of the category.
   * @return array An array of characteristics for each option in the category, indexed by their 'id'.
   */
  public function jsCategories($id = null)
  {
    // If no ID is provided, use the default code to determine it.
    if (!$id) {
      $id = $this->fromCode('options', $this->default);
    }

    // Check the cache for an existing result before calculating it.
    if ($tmp = $this->getCache($id, __FUNCTION__)) {
      return $tmp;
    }

    // Initialize a response array to store category information.
    $res = [
      'categories' => []
    ];

    // Retrieve the full options for the given ID (or all categories if no ID is provided).
    if ($cats = $this->fullOptions($id ?: false)) {
      foreach ($cats as $cat) {
        // If a 'tekname' exists, retrieve additional information and create text/value entries.
        if (!empty($cat['tekname'])) {
          $additional = [];
          // Retrieve the schema for the current category's ID.
          if ($schema = $this->getSchema($cat[$this->fields['id']])) {
            // Add fields from the schema to the list of additional information.
            array_push($additional, ...array_map(function ($a) {
              return $a['field'];
            }, $schema));
          }
          // Create text/value entries for the current category and its options.
          $res[$cat['tekname']] = $this->textValueOptions($cat[$this->fields['id']], 'text', 'value', ...$additional);
          $res['categories'][$cat[$this->fields['id']]] = $cat['tekname'];
        }
      }
    }

    // Store the result in the cache and return it.
    $this->setCache($id, __FUNCTION__, $res);
    return $res;
  }
}
