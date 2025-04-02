<?php

namespace bbn\Appui\Option;

use Exception;
use bbn\Str;
use bbn\Appui\I18n;

trait Native
{
  /**
   * Returns an option's row as stored in its original form in the database
   *
   * ```php
   * X::dump($opt->nativeOption(25));
   * /*
   * array [
   *   'id' => 25,
   *   'code' => "bbn_ide",
   *   'text' => "BBN's own IDE",
   *   'id_alias' => null,
   *   'value' => "{\"myProperty\":\"My property's value\"}"
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null Row or null if the option cannot be found
   */
  public function nativeOption($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $originalLocale = $this->findI18nById($id);
      $locale = $this->getTranslatingLocale($id);
      if (
        !empty($locale)
        && ($opt = $this->cacheGetLocale($id, $locale, __FUNCTION__))
      ) {
        return $opt;
      } else if (
        empty($locale)
        && ($opt = $this->getCache($id, __FUNCTION__))
      ) {
        return $opt;
      }
      $tab = $this->db->tsn($this->class_cfg['table']);
      $cfn = $this->db->cfn($this->fields['id'], $tab);
      $opt = $this->getRow([$cfn => $id]);
      if (!empty($opt['code']) && Str::isInteger($opt['code'])) {
        $opt['code'] = (int)$opt['code'];
      }
      if ($opt) {
        if (
          !empty($locale)
          && \class_exists('\bbn\Appui\I18n')
          && !empty($opt[$this->fields['text']])
        ) {
          try {
            $i18nCls = new I18n($this->db);
            if ($trans = $i18nCls->getTranslation($opt[$this->fields['text']], $originalLocale, $locale)) {
              $opt[$this->fields['text']] = $trans;
            }
          }
          catch (Exception $e) {

          }
        }
        if (empty($locale)) {
          $this->setCache($id, __FUNCTION__, $opt);
        } else {
          $this->cacheSetLocale($id, $locale, __FUNCTION__, $opt);
        }
        return $opt;
      }
    }

    return null;
  }


  /**
   * @param string|null $code
   * @return array|null
   */
  public function nativeOptions($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $res = [];
      if ($its = $this->items($id)) {
        foreach ($its as $it) {
          $res[] = $this->nativeOption($it);
        }
      }

      return $res;
    }

    return null;
  }


  /**
   * Returns an option's row as stored in its original form in the database, including cfg
   *
   * ```php
   * X::dump($opt->rawOption('database', 'appui'));
   * /*
   * array [
   *   'id' => "77cea323f0ce11e897fd525400007196",
   *   'code' => "bbn_ide",
   *   'text' => "BBN's own IDE",
   *   'cfg' => null,
   *   'id_alias' => null,
   *   'value' => "{\"num\":1}"
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null Row or false if the option cannot be found
   */
  public function rawOption($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      return $this->db->rselect($this->class_cfg['table'], [], [$this->fields['id'] => $id]);
    }

    return null;
  }

  /**
   * Returns an option's items as stored in its original form in the database, including cfg
   *
   * ```php
   * X::dump($opt->rawOptions('database', 'appui'));
   * /*
   * [
   *   [
   *      'id' => "77cea323f0ce11e897fd525400007196",
   *      'code' => "bbn_ide",
   *      'text' => "BBN's own IDE",
   *      'cfg' => null,
   *      'id_alias' => null,
   *      'value' => "{\"num\":1}"
   *    ], [
   *      'id' => "77cea323f0ce11e897fd525400007196",
   *      'code' => "bbn_ide",
   *      'text' => "BBN's own IDE",
   *      'cfg' => null,
   *      'id_alias' => null,
   *      'value' => "{\"num\":1}"
   *    ]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null Row or false if the option cannot be found
   */
  public function rawOptions($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $res = [];
      if ($its = $this->items($id)) {
        foreach ($its as $it) {
          $res[] = $this->db->rselect($this->class_cfg['table'], [], [$this->fields['id'] => $it]);
        }
      }

      return $res;
    }

    return null;
  }


  /**
   * Returns a hierarchical structure as stored in its original form in the database
   *
   * ```php
   * X::dump($opt->rawTree('77cea323f0ce11e897fd525400007196'));
   * /*
   * array [
   *   'id' => 12,
   *   'code' => "bbn_ide",
   *   'text' => "BBN's own IDE",
   *   'id_alias' => null,
   *   'value' => "{\"myProperty\":\"My property's value\"}",
   *   'items' => [
   *     [
   *       'id' => 25,
   *       'code' => "test",
   *       'text' => "Test",
   *       'id_alias' => null,
   *       'value' => "{\"myProperty\":\"My property's value\"}",
   *     ],
   *     [
   *       'id' => 26,
   *       'code' => "test2",
   *       'text' => "Test 2",
   *       'id_alias' => null,
   *       'value' => "{\"myProperty\":\"My property's value\"}",
   *       'items' => [
   *         [
   *           'id' => 42,
   *           'code' => "test8",
   *           'text' => "Test 8",
   *           'id_alias' => null,
   *           'value' => "{\"myProperty\":\"My property's value\"}",
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
  public function rawTree($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      if ($res = $this->rawOption($id)) {
        if ($its = $this->items($id)) {
          $res['items'] = [];
          foreach ($its as $it){
            $res['items'][] = $this->rawTree($it);
          }
        }

        return $res;
      }
    }

    return null;
  }


  /**
   * Returns a hierarchical structure as stored in its original form in the database
   *
   * ```php
   * X::dump($opt->nativeTree(12));
   * /*
   * array [
   *   'id' => 12,
   *   'code' => "bbn_ide",
   *   'text' => "BBN's own IDE",
   *   'id_alias' => null,
   *   'value' => "{\"myProperty\":\"My property's value\"}",
   *   'items' => [
   *     [
   *       'id' => 25,
   *       'code' => "test",
   *       'text' => "Test",
   *       'id_alias' => null,
   *       'value' => "{\"myProperty\":\"My property's value\"}",
   *     ],
   *     [
   *       'id' => 26,
   *       'code' => "test2",
   *       'text' => "Test 2",
   *       'id_alias' => null,
   *       'value' => "{\"myProperty\":\"My property's value\"}",
   *       'items' => [
   *         [
   *           'id' => 42,
   *           'code' => "test8",
   *           'text' => "Test 8",
   *           'id_alias' => null,
   *           'value' => "{\"myProperty\":\"My property's value\"}",
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
  public function nativeTree($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      if ($res = $this->nativeOption($id)) {
        $its = $this->items($id);
        if (!empty($its)) {
          $res['items'] = [];
          foreach ($its as $it){
            $res['items'][] = $this->nativeTree($it);
          }
        }

        return $res;
      }
    }

    return null;
  }


  /**
   * Gets the first row from a result
   *
   * @param array $where
   * @return array|null
   */
  protected function getRow(array $where): ?array
  {
    if ($res = $this->getRows($where, 1)) {
      return $res[0];
    }

    return null;
  }


  /**
   * Performs the actual query with a where parameter.
   * Always returns the whole result without limit
   * @param array $where The where config for the database query
   * @param int   $limit Max number of rows
   * @param int   $start Where to start the query (only if limit is > 1)
   * @return array|null An array of rows, empty if not found, null if there is an error in the where config
   */
  protected function getRows(array $where = [], int $limit = 0, int $start = 0): ?array
  {
    $db  =& $this->db;
    $tab = $this->class_cfg['table'];
    $c   =& $this->fields;
    /** @todo Checkout */
    $cols = [];
    foreach ($c AS $k => $col){
      // All the columns except cfg
      if (!\in_array($k, $this->non_selected, true)) {
        $cols[] = $db->cfn($col, $tab);
      }
    }

    $cols['num_children'] = 'COUNT('.$db->escape($db->cfn($c['id'], $tab.'2', true)).')';
    $res = $this->db->rselectAll(
      [
      'tables' => [$tab],
      'fields' => $cols,
      'join' => [
        [
          'type' => 'left',
          'table' => $tab,
          'alias' => $tab.'2',
          'on' => [
            'conditions' => [
              [
                'field' => $db->cfn($c['id_parent'], $tab.'2'),
                'operator' => 'eq',
                'exp' => $db->cfn($c['id'], $tab, true)
              ]
            ],
            'logic' => 'AND'
          ]
        ]
      ],
      'where' => $where,
      'group_by' => [$this->db->cfn($c['id'], $tab)],
      'order' => [
        $this->db->cfn($c['id'], $tab)
      ],
      'limit' => $limit,
      'start' => $start
      ]
    );

    if (!empty($res)) {
      foreach ($res as $i => $r) {
        if (!empty($r[$this->fields['code']])
          && Str::isInteger($r[$this->fields['code']])
        ) {
          $res[$i][$this->fields['code']] = (int)$r[$this->fields['code']];
        }
      }
    }
    return $res;
  }


}
