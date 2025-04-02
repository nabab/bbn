<?php

namespace bbn\Appui\Option;

use bbn\Str;
use bbn\X;
use bbn\Appui\I18n as I18nCls;

trait I18n
{
  /**
   * Returns translation of an option's text
   *
   * ```php
   * X::dump($opt->itext(12));
   * // Result of X::_("BBN's own IDE") with fr as locale
   * // (string) L'IDE de BBN
   * X::dump($opt->itext('bbn_ide'));
   * // (string) L'IDE de BBN
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return string|null Text of the option
   */
  public function itext($code = null): ?string
  {
    return $this->getTranslation($this->fromCode(\func_get_args()));
  }


  /**
   * Returns an array containing all options that have the property i18n set
   * @param string|null $startFromID
   * @param bool $items
   * @return array
   */
  public function findI18n(?string $startFromID = null, $items = false)
  {
    $res = [];
    if ($this->check()) {
      $where = [[
        'field' => 'JSON_UNQUOTE(JSON_EXTRACT(' . $this->fields['cfg'] . ', "$.i18n"))',
        'operator' => 'isnotnull'
      ], [
        'field' => 'JSON_UNQUOTE(JSON_EXTRACT(' . $this->fields['cfg'] . ', "$.i18n"))',
        'operator' => '!=',
        'value' => ''
      ]];
      if (Str::isUid($startFromID)) {
        $where = [[
          'field' => $this->fields['id'],
          'value' => $startFromID
        ]];
      }
      $opts = $this->db->rselectAll([
        'table' => $this->class_cfg['table'],
        'fields' => [
          $this->fields['id'],
          $this->fields['id_parent'],
          $this->fields['code'],
          $this->fields['text'],
          'language' => 'JSON_UNQUOTE(JSON_EXTRACT(' . $this->fields['cfg'] . ', "$.i18n"))'
        ],
        'where' => $where
      ]);

      if ($opts) {
        foreach ($opts as $opt){
          if (!empty($opt[$this->fields['code']])
            && Str::isInteger($opt[$this->fields['code']])
          ) {
            $opt[$this->fields['code']] = (int)$opt[$this->fields['code']];
          }
          if (\is_null(X::find($res, [$this->fields['id'] => $opt[$this->fields['id']]]))) {
            $cfg = $this->getCfg($opt[$this->fields['id']]);
            if (!empty($cfg['i18n'])) {
              $res[] = $opt;
            }
            if (!empty($cfg['i18n_inheritance'])) {
              $this->findI18nChildren($opt, $res, $cfg['i18n_inheritance'] === 'cascade');
            }
          }
        }
      }
      if (!empty($res) && !empty($items)) {
        $res2 = [];
        foreach ($res as $r) {
          $res2[] = \array_merge($r, [
            'items' => array_values(array_filter($res, function($o) use($r) {
              return $o[$this->fields['id_parent']] === $r[$this->fields['id']];
            }))
          ]);
        }
        return $res2;
      }
    }

    return $res;
  }


  /**
   * returns an array containing the option (having the property i18n set) corresponding to the given id
   *
   * @param $id
   * @param bool $items
   * @return array
   */
  public function findI18nOption($id, $items = true)
  {
    $res = [];
    if ($this->check()) {
      if ($opt = $this->db->rselect(
        $this->class_cfg['table'], [
          $this->fields['id'],
          $this->fields['id_parent'],
          $this->fields['text'],
          $this->fields['cfg']
        ], [$this->fields['id'] => $id]
      )
      ) {
        $cfg  = json_decode($opt[$this->fields['cfg']]);
        if (!empty($cfg->i18n)) {
          $opt['language'] = $cfg->i18n;
        }

        unset($opt[$this->fields['cfg']]);
        if (!empty($items)) {
          $res[] = array_merge($opt, ['items' => $this->fullOptions($id) ?? []]);
        }
        else {
          $res[] = $opt;
        }
      }
    }

    return $res;
  }


  /**
   * Returns an array containing all languages set
   *
   * @return null|array
   */
  public function findI18nLocales(?string $startFromID = null): ?array
  {
    if ($this->check()) {
      if (empty($startFromID)) {
        return \array_unique($this->db->getFieldValues([
          'table' => $this->class_cfg['table'],
          'fields' => [
            'JSON_UNQUOTE(JSON_EXTRACT('.$this->fields['cfg'].', "$.i18n"))'
          ],
          'where' => [[
            'field' => 'JSON_UNQUOTE(JSON_EXTRACT('.$this->fields['cfg'].', "$.i18n"))',
            'operator' => 'isnotnull'
          ], [
            'field' => 'JSON_UNQUOTE(JSON_EXTRACT('.$this->fields['cfg'].', "$.i18n"))',
            'operator' => '!=',
            'value' => ''
          ]]
        ]));
      }
      $res = [];
      $cfg = $this->getCfg($startFromID);
      if (!empty($cfg['i18n'])) {
        $res[] = $cfg['i18n'];
      }
      if ($items = $this->items($startFromID)) {
        foreach ($items as $item) {
          $res = X::mergeArrays($res, $this->findI18n($item));
        }
      }
      return \array_unique($res);
    }
    return null;
  }


  /**
   * Returns an array containing all options that have the property i18n set
   *
   * @param string $locale
   * @param bool $items
   * @return array
   */
  public function findI18nByLocale(string $locale, $items = false): array
  {
    $res = [];
    if ($this->check()) {
      $opts = $this->db->rselectAll([
        'table' => $this->class_cfg['table'],
        'fields' => [
          $this->fields['id'],
          $this->fields['id_parent'],
          $this->fields['code'],
          $this->fields['text'],
          'language' => 'JSON_UNQUOTE(JSON_EXTRACT('.$this->fields['cfg'].', "$.i18n"))'
        ],
        'where' => [
          'JSON_UNQUOTE(JSON_EXTRACT('.$this->fields['cfg'].', "$.i18n"))' => $locale
        ]
      ]) ?: [];
      if ($opts) {
        foreach ($opts as $opt){
          if (!empty($opt[$this->fields['code']])
            && Str::isInteger($opt[$this->fields['code']])
          ) {
            $opt[$this->fields['code']] = (int)$opt[$this->fields['code']];
          }
          if (\is_null(X::find($res, [$this->fields['id'] => $opt[$this->fields['id']]]))) {
            $cfg = $this->getCfg($opt[$this->fields['id']]);
            $res[] = $opt;
            if (!empty($cfg['i18n_inheritance'])) {
              $this->findI18nChildren($opt, $res, $cfg['i18n_inheritance'] === 'cascade');
            }
          }
        }
      }
      if (!empty($res) && !empty($items)) {
        $res2 = [];
        foreach ($res as $r) {
          $res2[] = \array_merge($r, [
            'items' => array_values(array_filter($res, function($o) use($r) {
              return $o[$this->fields['id_parent']] === $r[$this->fields['id']];
            }))
          ]);
        }
        return $res2;
      }
    }
    return $res;
  }


  public function findI18nById(string $id): ?string
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      if ($c = $this->getCache($id, __FUNCTION__)) {
        return $c['i18n'];
      }

      $i18n = null;
      if ($cfg = $this->getCfg($id)) {
        if (!empty($cfg['i18n'])) {
          $i18n = $cfg['i18n'];
        }
      }

      if (empty($i18n)
        && ($parents = $this->parents($id))
      ) {
        foreach ($parents as $i => $parent) {
          $pcfg = $this->getCfg($parent);
          if (empty($pcfg)
            || empty($pcfg['i18n'])
          ) {
            continue;
          }

          if (!empty($pcfg['i18n_inheritance'])
            && (($pcfg['i18n_inheritance'] === 'cascade')
              || (($pcfg['i18n_inheritance'] === 'children')
                && ($i === 0)))
          ) {
            $i18n = $pcfg['i18n'];
            break;
          }

          $i18n = null;
          break;
        }
      }

      $this->setCache($id, __FUNCTION__, ['i18n' => $i18n]);
      return $i18n;
    }

    return null;
  }


  public function getTranslation(string $id, string $locale = ''): ?string
  {
    if (Str::isUid($id)
      && ($originalLocale = $this->findI18nById($id))
      && ($text = $this->text($id))
    ) {
      if (empty($locale)) {
        $locale = $this->getTranslatingLocale($id);
      }
      if (!empty($locale)) {
        $i18nCls = new I18nCls($this->db);
        return  $i18nCls->getTranslation($text, $originalLocale, $locale);
      }
    }
    return null;
  }


  private function findI18nChildren(array $opt, array &$res, bool $cascade = false, string|null $locale = null){
    $fid = $this->fields['id'];
    if ($children = $this->fullOptions($opt[$fid])) {
      foreach ($children as $child) {
        if (\is_null(X::find($res, [$fid => $child[$fid]]))) {
          $cfg = $this->getCfg($child[$fid]);
          $child = [
            $this->fields['id'] => $child[$this->fields['id']],
            $this->fields['id_parent'] => $child[$this->fields['id_parent']],
            $this->fields['code'] => $child[$this->fields['code']],
            $this->fields['text'] => $child[$this->fields['text']],
            'language' => !empty($cfg['i18n']) ? $cfg['i18n'] : $opt['language']
          ];
          if (empty($locale)
            || ($child['language'] === $locale)
          ) {
            $res[] = $child;
          }
          if (!empty($cfg['i18n_inheritance'])
            || (empty($cfg['i18n']) && $cascade)
          ) {
            $c = ($cfg['i18n_inheritance'] === 'cascade')
              || (empty($cfg['i18n']) && $cascade);
            $this->findI18nChildren($child, $res, $c);
          }
        }
      }
    }
    return $res;
  }


  private function getTranslatingLocale(string $id): ?string
  {
    $originalLocale = $this->findI18nById($id);
    $locale = null;
    if (!empty($originalLocale)
      && \defined('BBN_LANG')
      && (BBN_LANG !== $originalLocale)
    ) {
      $locale = BBN_LANG;
    }

    return $locale;
  }
}
