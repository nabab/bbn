<?php

namespace bbn\Appui\Option;

use Exception;
use bbn\X;
use bbn\Str;

trait Single
{
  /**
   * Returns an option's full content as an array without its values changed by id_alias
   *
   * ```php
   * X::dump($opt->optionNoAlias(25));
   * X::dump($opt->optionNoAlias('bbn_ide'));
   * X::dump($opt->optionNoAlias('TEST', 58));
   * X::dump($opt->optionNoAlias('test3', 'users', 'bbn_ide'));
   * /* Each would return an array of this form
   * array [
   *   'id' => 31,
   *   'code' => "bbn_ide",
   *   'text' => "This is BBN's IDE",
   *   'id_alias' => 16,
   *   'myIntProperty' => 56854,
   *   'myTextProperty' => "<h1>Hello\nWorld</h1>",
   *   'myArrayProperty' => ['value1' => 1, 'value2' => 2]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null The option array or false if the option cannot be found
   */
  public function optionNoAlias($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))
        && ($opt = $this->nativeOption($id))
    ) {
      $this->_set_value($opt);
      return $opt;
    }

    return null;
  }


  /**
   * @param string|null $code
   * @return array|null
   */
  public function getValue($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))
        && ($opt = $this->nativeOption($id))
        && !empty($opt[$this->fields['value']])
        && Str::isJson($opt[$this->fields['value']])
    ) {
      return json_decode($opt[$this->fields['value']], true);
    }

    return null;
  }


  /**
   * Returns an option's full content as an array.
   *
   * ```php
   * X::dump($opt->option(25));
   * X::dump($opt->option('bbn_ide'));
   * X::dump($opt->option('TEST', 58));
   * X::dump($opt->option('test', 'users', 'bbn_ide'));
   * /* Each would return an array of this form
   * array [
   *   'id' => 25,
   *   'code' => "bbn_ide",
   *   'text' => "This is BBN's IDE",
   *   'myIntProperty' => 56854,
   *   'myTextProperty' => "<h1>Hello\nWorld</h1>",
   *   'myArrayProperty' => ['value1' => 1, 'value2' => 2]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null The option array or false if the option cannot be found
   */
  public function option($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))
        && ($opt = $this->nativeOption($id))
    ) {
      $this->_set_value($opt);
      $c =& $this->fields;
      if (Str::isUid($opt[$c['id_alias']]) && ($alias = $this->nativeOption($opt[$c['id_alias']]))) {
        $opt['alias'] = $alias;
        if ($opt[$c['id_alias']] === $id) {
          X::log([$id, $opt, \func_get_args()], 'optionsAlias');
          throw new Exception(X::_("Impossible to have the same ID as ALIAS, check out ID").' '.$id);
        }
        else {
          $this->_set_value($opt['alias']);
        }
      }

      if ($schema = $this->getSchema($id)) {
        foreach ($schema as $s) {
          if (isset($s['field']) && !isset($opt[$s['field']])) {
            $opt[$s['field']] = $s['default'] ?? null;
          }
        }
      }

      return $opt;
    }

    return null;
  }




  /**
   * Returns the merge between an option and its alias as an array.
   *
   * ```php
   * X::dump($opt->option(25));
   * X::dump($opt->option('bbn_ide'));
   * X::dump($opt->option('TEST', 58));
   * X::dump($opt->option('test', 'users', 'bbn_ide'));
   * /* Each would return an array of this form
   * array [
   *   'id' => 25,
   *   'code' => "bbn_ide",
   *   'text' => "This is BBN's IDE",
   *   'myIntProperty' => 56854,
   *   'myTextProperty' => "<h1>Hello\nWorld</h1>",
   *   'myArrayProperty' => ['value1' => 1, 'value2' => 2]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null The option array or false if the option cannot be found
   */
  public function opAlias($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))
        && ($opt = $this->nativeOption($id))
    ) {
      $this->_set_value($opt);
      $c =& $this->fields;
      if (Str::isUid($opt[$c['id_alias']]) && ($alias = $this->nativeOption($opt[$c['id_alias']]))) {
        if ($opt[$c['id_alias']] === $id) {
          throw new Exception(X::_("Impossible to have the same ID as ALIAS, check out ID").' '.$id);
        }
        else {
          $this->_set_value($alias);
          foreach ($alias as $n => $a) {
            if (!empty($a)) {
              $opt[$n] = $a;
            }
          }

        }
      }

      return $opt;
    }

    return null;
  }



}
