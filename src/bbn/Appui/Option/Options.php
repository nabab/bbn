<?php

namespace bbn\Appui\Option;

use Exception;
use bbn\X;
use bbn\Str;

trait Options
{
  /**
   * Returns an array of full options arrays for a given parent
   *
   * ```php
   * X::dump($opt->fullOptions(12));
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
  public function fullOptions($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $list = $this->items($id);
      if (\is_array($list)) {
        $res = [];
        foreach ($list as $i){
          if ($tmp = $this->option($i)) {
            $res[] = $tmp;
          }
          else {
            throw new Exception(X::_("Impossible to find the ID").' '.$i);
          }
        }

        return $res;
      }
    }

    return null;
  }

  /**
   * Returns an array of full options with the config in arrays for a given parent
   *
   * ```php
   * X::dump($opt->fullOptionsCfg(12));
   * /*
   * array [
   *   ['id' => 21, 'id_parent' => 12, 'num' => 1, 'title' => "My option 21", 'myProperty' =>  "78%", 'cfg' => ['sortable' => true, 'desc' => "I am a description"]],
   *   ['id' => 22, 'id_parent' => 12, 'num' => 2, 'title' => "My option 22", 'myProperty' =>  "26%", 'cfg' => ['desc' => "I am a description"]],
   *   ['id' => 25, 'id_parent' => 12, 'num' => 3, 'title' => "My option 25", 'myProperty' =>  "50%", 'cfg' => ['desc' => "I am a description"]],
   *   ['id' => 27, 'id_parent' => 12, 'num' => 4, 'title' => "My option 27", 'myProperty' =>  "40%", 'cfg' => ['desc' => "I am a description"]]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null A list of parent if option not found
   */
  public function fullOptionsCfg($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $o =& $this;
      return $this->map(
        function ($a) use ($o) {
          $a[$this->fields['cfg']] = $o->getCfg($a[$this->fields['id']]);
          return $a;
        }, $id
      );
    }

    return null;
  }


  /**
   * Returns an array of options in the form id => text
   *
   * ```php
   * X::dump($opt->options(12));
   * /*
   * [
   *   21 => "My option 21",
   *   22 => "My option 22",
   *   25 => "My option 25",
   *   27 => "My option 27"
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null An indexed array of id/text options or false if option not found
   */
  public function options($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $locale = $this->getTranslatingLocale($id);
      if ($r = $this->getCache($id, __FUNCTION__, $locale)) {
        return $r;
      }

      $cf  =& $this->fields;
      $opts = $this->db->rselectAll([
        'tables' => [$this->class_cfg['table']],
        'fields' => [
          $this->db->cfn($cf['id'], $this->class_cfg['table']),
          $this->db->cfn($cf['text'], $this->class_cfg['table']),
          $this->db->cfn($cf['id_alias'], $this->class_cfg['table'])
        ],
        'join' => [
          [
            'table' => $this->class_cfg['table'],
            'alias' => 'alias',
            'type'  => 'LEFT',
            'on'    => [
              [
                'field' => $this->db->cfn($cf['id_alias'], $this->class_cfg['table']),
                'exp'   => 'alias.'.$cf['id']
              ]
            ]
          ]
        ],
        'where' => [$this->db->cfn($cf['id_parent'], $this->class_cfg['table']) => $id],
        'order' => ['text' => 'ASC']
      ]);
      $res = [];
      foreach ($opts as $o) {
        if (\is_null($o[$cf['text']]) && !empty($o[$cf['id_alias']])) {
          $o[$cf['text']] = $this->text($o[$cf['id_alias']]);
        }
        if (!empty($o[$cf['text']])
          && !empty($locale)
          && ($t = $this->getTranslation($o[$cf['id']], $locale))
        ) {
          $o[$cf['text']] = $t;
        }
        $res[$o[$cf['id']]] = $o[$cf['text']];
      }

      \asort($res);
      $this->setCache($id, __FUNCTION__, $res, $locale);
      return $res;
    }

    return null;
  }


  /**
   * Returns an array of the children's IDs of the given option sorted by order or text
   *
   * ```php
   * X::dump($opt->items(12));
   * // array [40, 41, 42, 44, 45, 43, 46, 47]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null array of IDs, sorted or false if option not found
   */
  public function items($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      if (($res = $this->cacheGet($id, __FUNCTION__)) !== false) {
        return $res;
      }

      $cfg = $this->getCfg($id) ?: [];
      if ($cfg || $this->dbTraitExists($id)) {
        // If not sortable returning an array ordered by text
        $order = empty($cfg['sortable']) ? [
            $this->fields['text'] => 'ASC',
            $this->fields['code'] => 'ASC',
            $this->fields['id'] => 'ASC',
          ] : [
            $this->fields['num'] => 'ASC',
            $this->fields['text'] => 'ASC',
            $this->fields['code'] => 'ASC',
            $this->fields['id'] => 'ASC',
          ];
        $res   = $this->db->getColumnValues(
          $this->class_cfg['table'],
          $this->fields['id'], [
          $this->fields['id_parent'] => $id,
          ], $order
        );
        if (!$this->isExporting && empty($res)) {
          $opt = $this->option($id);
          if (!$opt['text'] && $opt['id_alias']) {
            $res   = $this->db->getColumnValues(
              $this->class_cfg['table'],
              $this->fields['id'], [
              $this->fields['id_parent'] => $opt['id_alias'],
              ], $order
            );
          }
        }

        $this->cacheSet($id, __FUNCTION__, $res);
        return $res;
      }
    }

    return null;
  }


  public function flatOptions($code = null): array
  {
    if (!Str::isUid($id = $this->fromCode(\func_get_args()))) {
      throw new Exception(X::_("Impossible to find the option requested in flatOptions"));
    }

    $res = [];
    if ($ids = $this->treeIds($id)) {
      foreach ($ids as $id) {
        if ($o = $this->nativeOption($id)) {
          $res[] = [
            $this->fields['id'] => $o[$this->fields['id']],
            $this->fields['text'] => $o[$this->fields['text']]
          ];
        }
      }
    }
    X::sortBy($res, $this->class_cfg['arch']['options']['text'], 'asc');
    return $res;
  }
}
