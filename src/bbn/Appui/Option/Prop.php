<?php

namespace bbn\Appui\Option;

use bbn\X;
use bbn\Str;

trait Prop
{
  /**
   * Get an option's single property
   *
   * ```php
   * X::dump($opt->getProp(12, 'myProperty'));
   * // (int) 78
   * X::dump($opt->setProp(12, ['myProperty' => "78%"]));
   * // (int) 1
   * X::dump($opt->getProp(12, 'myProperty'));
   * // (string) "78%"
   * ```
   *
   * @param string|int    $id   The option from which getting the property
   * @param string $prop The property's name
   * @return mixed|false The property's value, false if not found
   */
  public function getProp($id, string $prop)
  {
    if (!empty($id) && !empty($prop) && ($o = $this->option($id)) && isset($o[$prop])) {
      return $o[$prop];
    }

    return null;
  }

  /**
   * Returns an option's text
   *
   * ```php
   * X::dump($opt->text(12));
   * // (string) BBN's own IDE
   * X::dump($opt->text('bbn_ide'));
   * // (string) BBN's own IDE
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return string|null Text of the option
   */
  public function text($code = null): ?string
  {
    if ($opt = $this->nativeOption(\func_get_args())) {
      return $opt[$this->fields['text']];
    }

    return null;
  }


  public function rawText($code = null): ?string
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      if ($this->cacheHas($id, __FUNCTION__)) {
        return $this->getCache($id, __FUNCTION__);
      }

      $text = $this->db->selectOne(
        $this->class_cfg['table'],
        $this->fields['text'],
        [
          $this->fields['id'] => $id
        ]
      );
      $this->setCache($id, __FUNCTION__, $text);
      return $text;
    }

    return null;
  }


  /**
   * Returns the order of an option. Updates it if a position is given, and cascades
   *
   * ```php
   * X::dump($opt->items(20));
   * // [21, 22, 25, 27]
   * X::dump($opt->order(25));
   * // (int) 3
   * X::dump($opt->order(25, 2));
   * // (int) 2
   * X::dump($opt->items(20));
   * // [21, 25, 22, 27]
   * X::dump($opt->order(25));
   * // (int) 2
   * ```
   *
   * @param int $id  The ID of the option to update
   * @param int|null $pos The new position
   * @return int|null The new or existing order of the option or null if not found or not sortable
   */
  public function order($id, int $pos = null)
  {
    if ($this->check()
        && ($parent = $this->getIdParent($id))
        && $this->isSortable($parent)
    ) {
      $cf  = $this->class_cfg;
      $old = $this->db->selectOne(
        $cf['table'], $this->fields['num'], [
        $this->fields['id'] => $id
        ]
      );
      if ($pos && ($old != $pos)) {
        $its      = $this->items($parent);
        $past_new = false;
        $past_old = false;
        $p        = 1;
        foreach ($its as $id_option){
          $upd = false;
          // Fixing order problem
          if ($past_old && !$past_new) {
            $upd = [$this->fields['num'] => $p - 1];
          }
          elseif (!$past_old && $past_new) {
            $upd = [$this->fields['num'] => $p + 1];
          }

          if ($id === $id_option) {
            $upd      = [$this->fields['num'] => $pos];
            $past_old = 1;
          }
          elseif ($p === $pos) {
            $upd      = [$this->fields['num'] => $p + ($pos > $old ? -1 : 1)];
            $past_new = 1;
          }

          if ($upd) {
            $this->db->update(
              $cf['table'], $upd, [
              $this->fields['id'] => $id_option
              ]
            );
          }

          if ($past_new && $past_old) {
            break;
          }

          $p++;
        }

        $this->deleteCache($parent, true);
        $this->deleteCache($id);
        return $pos;
      }

      return $old;
    }

    return null;
  }
}
