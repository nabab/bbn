<?php
namespace bbn\Appui\Option;

use bbn\Str;

trait Indexed
{
  // Returns an array of full options arrays for a given parent.
  public function codeOptions(...$codes): ?array
  {
    // Get the ID from the provided codes.
    if (Str::isUid($id = $this->fromCode($codes))) {
      // Retrieve the list of items for the given ID.
      $list = $this->items($id);
      
      // If the list is an array, process it to create the result array.
      if (\is_array($list)) {
        $res = [];
        $cfg = $this->getCfg($id) ?: [];
        
        // Iterate over each item in the list and add its details to the result array.
        foreach ($list as $i){
          $o = $this->option($i);
          $res[$o[$this->fields['code']]] = [
            $this->fields['id'] => $o[$this->fields['id']],
            $this->fields['code'] => $o[$this->fields['code']],
            $this->fields['text'] => $o[$this->fields['text']] ?: $o['alias'][$this->fields['text']]
          ];

          // If the configuration has a schema, add its fields to the result array.
          if ( !empty($cfg['schema']) ){
            if ( \is_string($cfg['schema']) ){
              $cfg['schema'] = json_decode($cfg['schema'], true);
            }
  
            foreach ( $cfg['schema'] as $s ){
              if (!empty($s['field']) && !in_array($s['field'], [$this->fields['id'], $this->fields['code'], $this->fields['text']])) {
                $res[$o[$this->fields['code']]][$s['field']] = $o[$s['field']] ?? null;
              }
            }
          }

        }

        return $res;
      }
    }

    return null;
  }


  // Returns an array of IDs for a given parent.
  public function codeIds(...$codes): ?array
  {
    // Get the ID from the provided codes.
    if (Str::isUid($id = $this->fromCode($codes))) {
      // Retrieve the list of items for the given ID.
      $list = $this->items($id);
      
      // If the list is an array, process it to create the result array.
      if (\is_array($list)) {
        $res = [];
        
        // Iterate over each item in the list and add its ID to the result array.
        foreach ($list as $i){
          $o               = $this->option($i);
          $res[$o[$this->fields['code']]] = $o[$this->fields['id']];
        }

        return $res;
      }
    }

    return null;
  }



  // Returns an ID-indexed array of full options arrays for a given parent.
  public function fullOptionsById(...$codes): ?array
  {
    // Initialize the result array.
    $res = [];
    
    // Get the full options for the provided codes.
    if ($opt = $this->fullOptions($codes)) {
      $cf = $this->getFields();
      
      // Iterate over each option and add its details to the result array.
      foreach ($opt as $o){
        $res[$o[$cf['id']]] = $o;
      }
    }

    return $opt === null ? $opt : $res;
  }


  // Returns a code-indexed array of full options arrays for a given parent.
  public function fullOptionsByCode(...$codes): ?array
  {
    // Initialize the result array.
    $res = [];
    
    // Get the full options for the provided codes.
    if ($opt = $this->fullOptions($codes)) {
      $cf = $this->getFields();
      
      // Iterate over each option and add its details to the result array.
      foreach ($opt as $o){
        $res[$o[$cf['code']]] = $o;
      }
    }

    return $opt === null ? $opt : $res;
  }


  // Returns an array of children options in the form code => text.
  public function optionsByCode(...$codes): ?array
  {
    // Get the ID from the provided codes.
    if (Str::isUid($id = $this->fromCode($codes))) {
      // Check if the result is cached.
      if ($r = $this->getCache($id, __FUNCTION__)) {
        return $r;
      }

      // Retrieve the native options for the given ID.
      $opts = $this->fullOptions($id);
      
      // If there are options, create the result array.
      if ($opts) {
        $res = [];
        
        // Iterate over each option and add its code and text to the result array.
        foreach ($opts as $o) {
          $res[$o[$this->fields['code']]] = $o[$this->fields['text']] ?: $o['alias'][$this->fields['text']];
        }
        
        // Sort the result array by value.
        \asort($res);
        $opts = $res;
      }

      // Cache the result and return it.
      $this->setCache($id, __FUNCTION__, $opts);
      return $opts;
    }

    return null;
  }


  /**
   * Returns an option's children array of id and text in a user-defined indexed array.
   *
   * @param int|string      $id    The option's ID or its code if it is children of {@link default}
   * @param string          $text  The text field name for the text column
   * @param string          $value The value field name for the id column
   * @param string          ...$additionalFields Additional fields to include in the result
   *
   * @return array|null Options' list in a text/value indexed array or null if not found
   */
  public function textValueOptions(string $id, string $text = 'text', string $value = 'value', ...$additionalFields): ?array
  {
    // Initialize the result array.
    $res = [];
    
    // Get the full options for the provided codes.
    if ($opts = $this->fullOptions($id)) {
      // Get the configuration for the given code.
      $cfg = $this->getCfg($id) ?: [];
      
      // Initialize a counter.
      $i   = 0;
      
      // Iterate over each option and add it to the result array.
      foreach ($opts as $k => $o) {
        if (!isset($is_array)) {
          $is_array = \is_array($o);
        }
        
        $res[$i] = [
          $text => $is_array ? ($o[$this->fields['text']] ?: $o['alias'][$this->fields['text']]) : $o,
          $value => $is_array ? $o[$this->fields['id']] : $k
        ];
        if (!empty($cfg['show_code'])) {
          $res[$i][$this->fields['code']] = $o[$this->fields['code']];
        }
        foreach ($additionalFields as $f) {
          if (!array_key_exists($f, $res[$i])) {
            $res[$i][$f] = $o[$f] ?? null;
          }
        }
        
        $i++;
      }
    }

    return $res;
  }
}
