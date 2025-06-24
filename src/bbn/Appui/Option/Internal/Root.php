<?php

namespace bbn\Appui\Option\Internal;

use Exception;
use bbn\Appui\Option;

trait Root
{
  /** @var int The root ID of the options in the table */
  protected $root;

  /** @var int The default ID as parent */
  protected $default;

  /**
   * Returns the ID of the root option - mother of all
   *
   * ```php
   * X::dump($opt->getRoot());
   * // (int)0
   * ```
   *
   * @return string|null
   */
  public function getRoot(): ?string
  {
    if ($this->check()) {
      return $this->root;
    }

    return null;
  }


  /**
   * Returns the ID of the default option ($id_parent used when not provided)
   *
   * ```php
   * X::dump($opt->getDefault());
   * // (int) 0
   * $opt->setDefault(5);
   * X::dump($opt->getDefault());
   * // (int) 5
   * $opt->setDefault();
   * X::dump($opt->getDefault());
   * // (int) 0
   * ```
   *
   * @return string|null
   */
  public function getDefault(): ?string
  {
    if ($this->check()) {
      return $this->default;
    }

    return null;
  }


  /**
   * Makes an option act as if it was the root option
   * It will be the default $id_parent for options requested by code
   *
   * ```php
   * X::dump($opt->getDefault());
   * // (int) 0
   * // Default root option
   * $new = $opt->fromCode('test');
   * // false
   * // Option not found
   * $opt->setDefault($new);
   * // Default is now 5
   * X::dump($opt->getDefault());
   * // (int) 5
   * X::dump($opt->fromCode('test));
   * // (int) 24
   * // Returns the ID (24) of a child of option 5 with code 'test'
   * $opt->setDefault();
   * // Default is back to root
   * X::dump($opt->getDefault());
   * // (int) 0
   * ```
   *
   * @param string $uid
   * @return Option
   * @throws Exception
   */
  public function setDefault($uid): self
  {
    if ($this->check() && $this->exists($uid)) {
      $this->default = $uid;
    }

    return $this;
  }


  public function getDefaults(): array
  {
    if ($this->check()) {
      return array_filter($this->fullOptions($this->root), function($a) {
        return $a['code'] !== 'templates';
      });
    }

    return [];
  }


  public function init(): bool
  {
    if (!$this->is_init) {
      $this->cacheInit();
      $t          =& $this;
      $this->root = $this->cacheGetSet(
        function () use (&$t) {
          return $t->db->selectOne($t->class_cfg['table'], $t->fields['id'], [
            ['field' => $t->fields['id_parent'], 'exp'  => $t->fields['id']], ['field' => $t->fields['code'], 'value' => 'root']
          ]);
        },
        'root',
        'root',
        60
      );
      if (!$this->root) {
        return false;
      }

      if (\defined('BBN_APP_NAME')) {
        $this->default = $this->cacheGetSet(
          function () use (&$t) {
            $res = $t->db->selectOne(
              $t->class_cfg['table'],
              $t->fields['id'],
              [
                $t->fields['id_parent'] => $this->root,
                $t->fields['code'] => BBN_APP_NAME
              ]
            );
            if (!$res) {
              $res = $t->root;
            }

            return $res;
          },
          BBN_APP_NAME,
          BBN_APP_NAME,
          60
        );
      }
      else {
        $this->default = $this->root;
      }

      $this->is_init = true;
    }

    return true;
  }
}
