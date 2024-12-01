<?php

namespace bbn\Appui\Option;

use bbn\X;
use bbn\Str;

trait Sub
{
  /**
   * Returns an id-indexed array of options in the form id => text for a given grandparent
   *
   * ```php
   * X::dump($opt->soptions(12));
   * /*
   * [
   *   21 => "My option 21",
   *   22 => "My option 22",
   *   25 => "My option 25",
   *   27 => "My option 27",
   *   31 => "My option 31",
   *   32 => "My option 32",
   *   35 => "My option 35",
   *   37 => "My option 37"
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null indexed on id/text options or false if parent not found
   */
  public function soptions($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $r = [];
      if ($list = $this->items($id)) {
        foreach ($list as $i){
          $o = $this->options($i);
          if (\is_array($o)) {
            $r = X::mergeArrays($r, $o);
          }
        }
      }

      return $r;
    }

    return null;
  }


  /**
   * Returns an array of full options arrays for a given grandparent
   *
   * ```php
   * X::dump($opt->fullSoptions(12));
   * /*
   * array [
   *   ['id' => 21, 'id_parent' => 20, 'title' => "My option 21", 'myProperty' =>  "78%"],
   *   ['id' => 22, 'id_parent' => 20, 'title' => "My option 22", 'myProperty' =>  "26%"],
   *   ['id' => 25, 'id_parent' => 20, 'title' => "My option 25", 'myProperty' =>  "50%"],
   *   ['id' => 27, 'id_parent' => 20, 'title' => "My option 27", 'myProperty' =>  "40%"],
   *   ['id' => 31, 'id_parent' => 30, 'title' => "My option 31", 'myProperty' =>  "88%"],
   *   ['id' => 32, 'id_parent' => 30, 'title' => "My option 32", 'myProperty' =>  "97%"],
   *   ['id' => 35, 'id_parent' => 30, 'title' => "My option 35", 'myProperty' =>  "12%"],
   *   ['id' => 37, 'id_parent' => 30, 'title' => "My option 37", 'myProperty' =>  "4%"]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null A list of options or false if parent not found
   */
  public function fullSoptions($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $r = [];
      if ($ids = $this->items($id)) {
        foreach ($ids as $id){
          $o = $this->fullOptions($id);
          if (\is_array($o)) {
            $r = X::mergeArrays($r, $o);
          }
        }
      }

      return $r;
    }

    return null;
  }

}
