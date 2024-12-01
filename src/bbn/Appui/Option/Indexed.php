<?php

namespace bbn\Appui\Option;

use bbn\Str;

trait Indexed
{
  /**
   * Returns an array of full options arrays for a given parent
   *
   * ```php
   * X::dump($opt->codeOptions(12));
   * /*
   * array [
   *   'code_1' => [
   *       'id' => 21,
   *       'code' => 'code_1',
   *       'text' => 'text_1'
   *   ],
   *   'code_2' => [
   *       'id' => 22,
   *       'code' => 'code_2',
   *       'text' => 'text_2'
   *   ],
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|null A list of parent if option not found
   */
  public function codeOptions($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $list = $this->items($id);
      if (\is_array($list)) {
        $res = [];
        $cfg = $this->getCfg($id) ?: [];
        foreach ($list as $i){
          $o = $this->option($i);
          $res[$o[$this->fields['code']]] = [
            $this->fields['id'] => $o[$this->fields['id']],
            $this->fields['code'] => $o[$this->fields['code']],
            $this->fields['text'] => $o[$this->fields['text']]
          ];

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


  /**
   *   * Returns an array of ids for a given parent
   *
   * ```php
   * X::dump($opt->codeIds(12));
   * /*
   * array [
   *   'code_1' => 21,
   *   'code_2' => 22,
   * ]
   * ```
   *
   * @param string|null $code
   * @return array|null
   */
  public function codeIds($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $list = $this->items($id);
      if (\is_array($list)) {
        $res = [];
        foreach ($list as $i){
          $o               = $this->option($i);
          $res[$o[$this->fields['code']]] = $o[$this->fields['id']];
        }

        return $res;
      }
    }

    return null;
  }



  /**
   * Returns an id-indexed array of full options arrays for a given parent
   *
   * ```php
   * X::dump($opt->fullOptionsById(12));
   * /*
   * array [
   *   21 => ['id' => 21, 'id_parent' => 12, 'title' => "My option 21", 'myProperty' =>  "78%"],
   *   22 => ['id' => 22, 'id_parent' => 12, 'title' => "My option 22", 'myProperty' =>  "26%"],
   *   25 => ['id' => 25, 'id_parent' => 12, 'title' => "My option 25", 'myProperty' =>  "50%"],
   *   27 => ['id' => 27, 'id_parent' => 12, 'title' => "My option 27", 'myProperty' =>  "40%"]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null A list of parent if option not found
   */
  public function fullOptionsById($code = null): ?array
  {
    $res = [];
    if ($opt = $this->fullOptions(\func_get_args())) {
      $cf = $this->getFields();
      foreach ($opt as $o){
        $res[$o[$cf['id']]] = $o;
      }
    }

    return $opt === null ? $opt : $res;
  }


  /**
   * Returns code-indexed array of full options arrays for a given parent
   *
   * ```php
   * X::dump($opt->fullOptionsByCode(12));
   * /*
   * array [
   *   'code_1' => ['id' => 21, 'id_parent' => 12, 'title' => "My option 21", 'myProperty' =>  "78%"],
   *   'code_2' => ['id' => 22, 'id_parent' => 12, 'title' => "My option 22", 'myProperty' =>  "26%"],
   *   'code_3' => ['id' => 25, 'id_parent' => 12, 'title' => "My option 25", 'myProperty' =>  "50%"],
   *   'code_4' => ['id' => 27, 'id_parent' => 12, 'title' => "My option 27", 'myProperty' =>  "40%"]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null A list of parent if option not found
   */
  public function fullOptionsByCode($code = null): ?array
  {
    $res = [];
    if ($opt = $this->fullOptions(\func_get_args())) {
      $cf = $this->getFields();
      foreach ($opt as $o){
        $res[$o[$cf['code']]] = $o;
      }
    }

    return $opt === null ? $opt : $res;
  }


  /**
   * Returns an array of children options in the form code => text
   *
   * ```php
   * X::dump($opt->optionsByCode(12));
   * /*
   * array [
   *   'opt21' => "My option 21",
   *   'opt22' => "My option 22",
   *   'opt25' => "My option 25",
   *   'opt27' => "My option 27"
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null An indexed array of code/text options or false if option not found
   */
  public function optionsByCode($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      if ($r = $this->getCache($id, __FUNCTION__)) {
        return $r;
      }

      $opts = $this->nativeOptions($id);
      if ($opts) {
        $res = [];
        foreach ($opts as $o) {
          $res[$o[$this->fields['code']]] = $o[$this->fields['text']];
        }
        \asort($res);
        $opts = $res;
      }

      $this->setCache($id, __FUNCTION__, $opts);
      return $opts;
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
  public function textValueOptions($id, string $text = 'text', string $value = 'value', string ...$additionalFields): ?array
  {
    $res = [];
    if ($opts = $this->fullOptions($id)) {
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
        foreach ($additionalFields as $f) {
          if (!array_key_exists($f, $res[$i])) {
            $res[$i][$f] = $o[$f] ?? null;
          }
        }

        /*
        if ( !empty($cfg['schema']) ){
          if ( \is_string($cfg['schema']) ){
            $cfg['schema'] = json_decode($cfg['schema'], true);
          }
          foreach ( $cfg['schema'] as $s ){
            if ( !empty($s['field']) ){
              $res[$i][$s['field']] = $o[$s['field']] ?? null;
            }
          }
        }
        */
        $i++;
      }
    }

    return $res;
  }
}
