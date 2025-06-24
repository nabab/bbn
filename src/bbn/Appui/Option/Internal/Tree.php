<?php

namespace bbn\Appui\Option\Internal;

use bbn\Str;

trait Tree
{
  /**
   * Returns a flat array of all IDs found in a hierarchical structure (except the top one)
   * The second parameter is private and should be left blank
   *
   * ```php
   * X::dump($opt->treeIds(12));
   * // array [12, 21, 22, 25, 27, 31, 32, 35, 37, 40, 41, 42, 44, 45, 43, 46, 47]
   * ```
   *
   * @param int   $id  The end/target of the path
   * @param array $res The resulting array
   * @return array|null
   */
  public function treeIds($id, &$res = []): ?array
  {
    if ($this->check() && $this->exists($id)) {
      $res[] = $id;
      if ($its = $this->items($id)) {
        foreach ($its as $it){
          $this->treeIds($it, $res);
        }
      }

      return $res;
    }

    return null;
  }


  /**
   * Returns a simple hierarchical structure with just text, id and items
   *
   * ```php
   * X::dump($opt->tree(12));
   * /*
   * array [
   *  ['id' => 1, 'text' => 'Hello', 'items' => [
   *    ['id' => 7, 'text' => 'Hello from inside'],
   *    ['id' => 8, 'text' => 'Hello 2 from inside']
   *  ],
   * [
   *   ['id' => 1, 'text' => 'World']
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null
   */
  public function tree($code = null): ?array
  {
    $id = $this->fromCode(\func_get_args());
    if (Str::isUid($id) && ($text = $this->text($id))) {
      $res = [
        'id' => $id,
        'text' => $text
      ];
      if ($opts = $this->items($id)) {
        $res['items'] = [];
        foreach ($opts as $o){
          if ($t = $this->tree($o)) {
            $res['items'][] = $t;
          }
        }
      }

      return $res;
    }

    return null;
  }


  /**
   * Returns a full hierarchical structure of options from a given option
   *
   * ```php
   * X::dump($opt->fullTree(12));
   * /*
   * array [
   *   'id' => 12,
   *   'code' => "bbn_ide",
   *   'text' => "BBN's own IDE",
   *   'id_alias' => null,
   *   'myProperty' => "My property's value",
   *   'items' => [
   *     [
   *       'id' => 25,
   *       'code' => "test",
   *       'text' => "Test",
   *       'id_alias' => null,
   *       'myProperty' => "My property's value",
   *     ],
   *     [
   *       'id' => 26,
   *       'code' => "test2",
   *       'text' => "Test 2",
   *       'id_alias' => null,
   *       'myProperty' => "My property's value",
   *       'items' => [
   *         [
   *           'id' => 42,
   *           'code' => "test8",
   *           'text' => "Test 8",
   *           'id_alias' => null,
   *           'myProperty' => "My property's value",
   *         ]
   *       ]
   *     ],
   *   ]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null Tree's array or false if the option cannot be found
   */
  public function fullTree($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))
        && ($res = $this->option($id))
    ) {
      if ($opts = $this->items($id)) {
        $res['items'] = [];
        foreach ($opts as $o){
          if ($t = $this->fullTree($o)) {
            $res['items'][] = $t;
          }
        }
      }

      return $res;
    }

    return null;
  }
}
