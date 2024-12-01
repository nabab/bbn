<?php

namespace bbn\Appui\Option;

use bbn\Str;

trait Ref
{
  /**
   * Returns each individual full option plus the children of options having this as alias.
   *
   * ```php
   * X::dump($opt->fullOptionsRef('type', 'media', 'note', 'appui'));
   * /*
   * array [
   *   ['id' => 21, 'id_parent' => 12, 'title' => "My option 21", 'myProperty' =>  "78%"],
   *   ['id' => 22, 'id_parent' => 12, 'title' => "My option 22", 'myProperty' =>  "26%"],
   *   ['id' => 25, 'id_parent' => 12, 'title' => "My option 25", 'myProperty' =>  "50%"],
   *   ['id' => 27, 'id_parent' => 12, 'title' => "My option 27", 'myProperty' =>  "40%"]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null A list of parent if option not found
   */
  public function fullOptionsRef($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $all = $this->fullOptions($id) ?? [];
      if ($aliases = $this->getAliases($id)) {
        foreach ($aliases as $a) {
          if ($tmp = $this->fullOptions($a[$this->fields['id']])) {
            array_push($all, ...$tmp);
          }
        }
      }

      return $all;
    }

    return null;
  }


  /**
   * Returns each individual option plus the children of options having this as alias.
   *
   * ```php
   * X::dump($opt->optionsRef(12));
   * /*
   * array [
   *   [21 => "My option 21"],
   *   [22 => "My option 22"],
   *   [25 => "My option 25"],
   *   [27 => "My option 27"]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null A list of parent if option not found
   */
  public function optionsRef($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $all = $this->options($id) ?? [];
      if ($aliases = $this->getAliases($id)) {
        foreach ($aliases as $a) {
          if ($tmp = $this->options($a[$this->fields['id']])) {
            $all = array_merge($all, $tmp);
          }
        }
      }

      return $all;
    }

    return null;
  }


  /**
   * Returns each individual item plus the children of items having this as alias.
   *
   * ```php
   * X::dump($opt->itemsRef(12));
   * /*
   * array [
   *   [21],
   *   [22],
   *   [25],
   *   [26]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null A list of parent if option not found
   */
  public function itemsRef($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $all = $this->items($id) ?? [];
      if ($aliases = $this->getAliases($id)) {
        foreach ($aliases as $a) {
          if ($items = $this->items($a)) {
            array_push($all, ...$items);
          }
        }
      }

      return $all;
    }

    return null;
  }



  /**
   * Returns an option's children array of id and text in a user-defined indexed array
   *
   * ```php
   * X::dump($opt->textValueOptions(12, 'title'));
   * /* value comes from the default argument
   * array [
   *   ['title' => "My option 21", 'value' =>  21],
   *   ['title' => "My option 22", 'value' =>  22],
   *   ['title' => "My option 25", 'value' =>  25],
   *   ['title' => "My option 27", 'value' =>  27]
   * ]
   * ```
   *
   * @param int|string $id    The option's ID or its code if it is children of {@link default}
   * @param string     $text  The text field name for text column
   * @param string     $value The value field name for id column
   * @return array Options' list in a text/value indexed array
   */
  public function textValueOptionsRef($id, string $text = 'text', string $value = 'value'): ?array
  {
    $res = [];
    if ($opts = $this->fullOptionsRef($id)) {
      $cfg = $this->getCfg($id) ?: [];
      $i   = 0;
      foreach ($opts as $k => $o) {
        if (!isset($is_array)) {
          $is_array = \is_array($o);
        }

        $res[$i] = [
          $text => $is_array ? $o[$this->fields['text']] : $o,
          $value => $is_array ? $o[$this->fields['id']] : $k
        ];
        if (!empty($cfg['show_code'])) {
          $res[$i][$this->fields['code']] = $o[$this->fields['code']];
        }

        $i++;
      }
    }

    return $res;
  }


  /**
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @returns array|null
   */
  public function fullTreeRef($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))
        && ($res = $this->option($id))
    ) {
      if ($opts = $this->fullOptionsRef($id)) {
        $res['items'] = [];
        foreach ($opts as $o){
          if ($t = $this->fullTreeRef($o)) {
            $res['items'][] = $t;
          }
        }
      }

      return $res;
    }

    return null;
  }
}
