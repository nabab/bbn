<?php

namespace bbn\Appui\Option;

trait Path
{
  /**
   * Returns an array of codes for each option between $id and $root without $root's code
   *
   * ```php
   * X::dump($opt->getPathArray(48, 12));
   * // array ["path", "to", "my", "option"]
   * ```
   *
   * @param string $id The end/target of the path
   * @param null|string $root The start/origin of the path, {@link getDefault()} if is null
   * @return array|null
   */
  public function getPathArray(string $id, $root = null): ?array
  {
    if (!isset($root)) {
      $root = $this->getDefault();
    }

    if ($code = $this->code($id)) {
      $parts = [];
      while ($id && ($id !== $root)){
        array_unshift($parts, $code);
        if (!($id = $this->getIdParent($id))) {
          return null;
        }

        $code = $this->code($id);
      }

      return $parts;
    }

    return null;
  }


  /**
   * Returns the closest ID option from a _path_ of codes, with separator and optional id_parent
   *
   * ```php
   * X::dump("bbn_ide|test1|test8"));
   * // (int) 36
   * ```
   *
   * @param string      $path   The path made of a concatenation of path and $sep until the target
   * @param string      $sep    The separator
   * @param null|string $parent An optional id_parent, {@link fromCode()} otherwise
   * @return null|string
   */
  public function fromPath(string $path, string $sep = '|', $parent = null): ?string
  {
    if ($this->check()) {
      if (!empty($sep)) {
        $parts = explode($sep, $path);
      }
      else{
        $parts = [$path];
      }

      if (null === $parent) {
        $parent = $this->default;
      }

      foreach ($parts as $p){
        if (!($parent = $this->fromCode($p, $parent))) {
          break;
        }
      }

      return $parent ?: null;
    }

    return null;
  }


  /**
   * Concatenates the codes and separator $sep of a line of options
   *
   * ```php
   * X::dump($opt->toPath(48, '|', 12)
   * // (string) path|to|my|option
   * ```
   *
   * @param string $id The end/target of the path
   * @param string $sep The separator
   * @param string|null $parent The start/origin of the path
   * @return string|null The path concatenated with the separator or null if no path
   */
  public function toPath(string $id, string $sep = '|', string $parent = null): ?string
  {
    if ($this->check() && ($parts = $this->getPathArray($id, $parent))) {
      return implode($sep, $parts);
    }

    return null;
  }


  /**
   * @param $id
   * @return array|null
   */
  public function getCodePath($id, $fromRoot = false)
  {
    $res  = [];
    while ($o = $this->nativeOption($id)) {
      if ($o[$this->fields['code']]) {
        $res[] = $o[$this->fields['code']];
        if ($o[$this->fields['id_parent']] === ($fromRoot ? $this->root : $this->default)) {
          break;
        }

        $id = $o[$this->fields['id_parent']];
      }
      elseif ($o[$this->fields['id_alias']] && ($code = $this->code($o[$this->fields['id_alias']]))) {
        $res[] = $code;
        if ($o[$this->fields['id_parent']] === ($fromRoot ? $this->root : $this->default)) {
          break;
        }

        $id = $o[$this->fields['id_parent']];
      }
      else {
        return null;
      }
    }

    if (end($res) === 'root') {
      array_pop($res);
    }

    return count($res) ? $res : null;
  }
}
