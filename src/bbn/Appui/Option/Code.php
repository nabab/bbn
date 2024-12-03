<?php

namespace bbn\Appui\Option;

use bbn\Str;


trait Code
{
  /**
   * Retrieves an option's ID from its "codes path"
   * 
   * Gets an option ID from diverse combinations of elements:
   * - A code or a serie of codes from the most specific to a child of the root
   * - A code or a serie of codes and an id_parent where to find the last code
   * - A code alone having $this->default as parent
   *
   * ```php
   * X::dump($opt->fromCode(25));
   * // (int) 25
   * X::dump($opt->fromCode('bbn_ide'));
   * // (int) 25
   * X::dump($opt->fromCode('test', 58));
   * // (int) 42
   * X::dump($opt->fromCode('test', 'users', 'bbn_ide'));
   * // (int) 42
   * ```
   *
   * @param mixed $code
   * @return null|string The ID of the option or false if the row cannot be found
   */
  public function fromCode($code = null): ?string
  {
    if ($this->check()) {
      $args = \func_get_args();
      // An array can be used as parameters too
      while (isset($args[0]) && \is_array($args[0])){
        $args = $args[0];
      }

      // If we get an option array as param
      if (isset($args[$this->fields['id']])) {
        return $args[$this->fields['id']];
      }

      $num = \count($args);
      if (!$num) {
        return null;
      }

      // False is accepted as id_parent for root
      if (($num === 1) && ($args[0] === false)) {
        return $this->default;
      }

      if (Str::isUid($args[0])) {
        if ($num === 1) {
          return $args[0];
        }

        // If there are extra arguments with the ID we check that they correspond to its parent (that would be an extra check)
        if ($this->getIdParent($args[0]) === $this->fromCode(...\array_slice($args, 1))) {
          return $args[0];
        }
      }

      // We can use whatever alphanumeric value for code
      if (empty($args) || (!\is_string($args[0]) && !is_numeric($args[0]))) {
        return null;
      }

      if (end($args) === 'appui') {
        $args[] = 'plugins';
        $num++;
      }
      // They must all have the same form at start with an id_parent as last argument
      if (!Str::isUid(end($args))) {
        $args[] = $this->default;
        $num++;
      }

      // At this stage we need at least one code and one id
      if ($num < 2) {
        return null;
      }

      // So the target has always the same name
      // This is the full name with all the arguments plus the root
      // eg ['c1', 'c2', 'c3', UID]
      // UID-c3-c4-c5
      // UID-c3-c4
      // UID-c3
      // Using the code(s) as argument(s) from now
      $id_parent = array_pop($args);
      $true_code = array_pop($args);
      $enc_code  = $true_code ? base64_encode($true_code) : 'null';
      // This is the cache name
      // get_codeX::_(base64(first_code))
      $cache_name = 'get_code_'.$enc_code;
      // UID-get_codeX::_(base64(first_code))
      if (($tmp = $this->cacheGet($id_parent, $cache_name))) {
        if (!count($args)) {
          return $tmp;
        }

        $args[] = $tmp;
        return $this->fromCode(...$args);
      }

      $c =& $this->class_cfg;
      $f =& $this->fields;
      /** @var int|false $tmp */
      if ($tmp = $this->db->selectOne(
        $c['table'], $f['id'], [
          [$f['id_parent'], '=', $id_parent],
          [$f['code'], '=', $true_code]
        ]
      )) {
        $this->cacheSet($id_parent, $cache_name, $tmp);
      }
      // Magic code options can be bypassed
      elseif (($tmp2 = $this->db->selectOne(
            $c['table'], $f['id'], [
              $f['id_parent'] => $id_parent,
              $f['id_alias'] => $this->getMagicOptionsTemplateId()
            ]
          ))
          && ($tmp = $this->db->selectOne(
            $c['table'], $f['id'], [
              [$f['id_parent'], '=', $tmp2],
              [$f['code'], '=', $true_code]
            ]
          ))
      ) {
        $this->cacheSet($id_parent, $cache_name, $tmp);
      }
      // Case where we have a full alias (no text) with the right code, we follow it
      else {
        $aliases = $this->db->getColumnValues($c['table'], $f['id_alias'], [
          $f['id_parent'] => $id_parent,
          [$f['id_alias'], 'isnotnull'],
          [$f['text'], 'isnull']
        ]);
        $done = [];
        foreach ($aliases as $a) {
          if ($a && !in_array($a, $done, true)) {
            $done[] = $a;
            if ($this->code($a) === $true_code) {
              $this->cacheSet($id_parent, $cache_name, $tmp);
              break;
            }
          }
        }
      }

      if ($tmp) {
        if (\count($args)) {
          $args[] = $tmp;
          return $this->fromCode(...$args);
        }

        return $tmp;
      }
    }

    return null;
  }


  /**
   * @return string|null
   */
  public function fromRootCode(): ?string
  {
    if ($this->check()) {
      $def = $this->default;
      $this->setDefault($this->root);
      $res = $this->fromCode(...func_get_args());
      $this->setDefault($def);
      return $res;
    }

    return null;
  }






  /**
   * Returns an array of options in the form id => code
   * @todo Add cache
   *
   * ```php
   * X::dump($opt->getCodes());
   * /*
   * array [
   *   21 => "opt21",
   *   22 => "opt22",
   *   25 => "opt25",
   *   27 => "opt27"
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array Options' array
   */
  public function getCodes($code = null): array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $c   =& $this->fields;
      $opt = $this->db->rselectAll($this->class_cfg['table'], [$c['id'], $c['code']], [$c['id_parent'] => $id], [($this->isSortable($id) ? $c['num'] : $c['code']) => 'ASC']);
      $res = [];
      foreach ($opt as $r){
        if (!empty($r[$c['code']]) && Str::isInteger($r[$c['code']])) {
          $r[$c['code']] = (int)$r[$c['code']];
        }
        $res[$r[$c['id']]] = $r[$c['code']];
      }

      return $res;
    }

    return [];
  }


  /**
   * Returns an option's code
   *
   * ```php
   * X::dump($opt->code(12));
   * // (string) bbn_ide
   * ```
   *
   * @param string $id The options' ID
   * @return string|null The code value, null is none, false if option not found
   */
  public function code(string $id): ?string
  {
    if ($this->check() && Str::isUid($id)) {
      $code = $this->db->selectOne(
        $this->class_cfg['table'], $this->fields['code'], [
        $this->fields['id'] => $id
        ]
      );
      if (!empty($code) && Str::isInteger($code)) {
        $code = (int)$code;
      }
      return $code;
    }

    return null;
  }

}
