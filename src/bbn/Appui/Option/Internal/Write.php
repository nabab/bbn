<?php

namespace bbn\Appui\Option\Internal;

use Exception;
use bbn\X;
use bbn\Str;
use bbn\Appui\History;

trait Write
{

  /**
   * Creates a new option or a new hierarchy by adding row(s) in the options' table
   *
   * ```php
   * X::dump($opt->add([
   *   'id_parent' => $opt->fromCode('bbn_ide'),
   *   'text' => 'My new option',
   *   'code' => 'new_opt',
   *   'myProperty' => 'my value'
   * ]));
   * // (int) 49  New ID
   * X::dump($opt->add([
   *   'id_parent' => $opt->fromCode('bbn_ide'),
   *   'text' => 'My new option',
   *   'code' => 'new_opt',
   *   'myProperty' => 'my value'
   *   'items' => [
   *     [
   *       'code' => "test",
   *       'text' => "Test",
   *       'myProperty' => "My property's value",
   *     ],
   *     [
   *       'code' => "test2",
   *       'text' => "Test 2",
   *       'myProperty' => "My property's value",
   *       'items' => [
   *         [
   *           'code' => "test8",
   *           'text' => "Test 8",
   *         ]
   *       ]
   *     ]
   *   ]
   * ], true, true));
   * // (int) 4 Number of inserted/modified rows
   * ```
   *
   * @param array   $it         The option configuration
   * @param boolean $force      Determines if the option should be updated if it already exists
   * @param boolean $return_num If set to true the function will return the number of rows inserted otherwise the ID of the newly created option
   * @param bool $with_id
   * @return int|string|null
   */
  public function add(array $it, $force = false, $return_num = false, $with_id = false)
  {
    if ($this->check()) {
      $res   = $return_num ? 0 : null;
      $items = !empty($it['items']) && \is_array($it['items']) ? $it['items'] : false;
      $id    = null;
      try {
        $this->_prepare($it);
      }
      catch (Exception $e) {
        throw new Exception($e->getMessage());
      }

      if ($it) {

        $c =& $this->fields;
        if ($it[$c['code']]) {
          $id = $this->db->selectOne(
            $this->class_cfg['table'],
            $c['id'],
            [
              $c['id_parent'] => $it[$c['id_parent']],
              $c['code'] => $it[$c['code']]
            ]
          );
        }
        elseif (!empty($it[$c['id']])) {
          $id = $this->db->selectOne(
            $this->class_cfg['table'],
            $c['id'],
            [
              $c['id'] => $it[$c['id']],
              $c['code'] => null
            ]
          );
        }

        if ($id
            && $force
            && (null !== $it[$c['code']])
        ) {
          try {
            $res = (int)$this->db->update(
              $this->class_cfg['table'],
              [
                $c['text'] => $it[$c['text']],
                $c['id_alias'] => $it[$c['id_alias']],
                $c['value'] => $it[$c['value']],
                $c['num'] => $it[$c['num']] ?? null,
                $c['cfg'] => $it[$c['cfg']] ?? null
              ],
              [$c['id'] => $id]
            );
          }
          catch (Exception $e) {
            $this->log([X::_("Impossible to update the option"), $it]);
            throw new Exception(X::_("Impossible to update the option"));
          }
        }

        $values = [
          $c['id_parent'] => $it[$c['id_parent']],
          $c['text'] => $it[$c['text']],
          $c['code'] => empty($it[$c['code']]) ? null : $it[$c['code']],
          $c['id_alias'] => $it[$c['id_alias']],
          $c['value'] => $it[$c['value']],
          $c['num'] => $it[$c['num']] ?? null,
          $c['cfg'] => $it[$c['cfg']] ?? null
        ];

        if (isset($it[$c['id']]) && !$this->exists($it[$c['id']])) {
          $values[$c['id']] = $it[$c['id']];
        }

        if (!empty($it[$c['id']]) && $with_id) {
          $values[$c['id']] = $it[$c['id']];
        }

        if (!$id) {
          try {
            $res = (int)$this->db->insert($this->class_cfg['table'], $values);
          }
          catch (Exception $e) {
            X::log([X::_("Impossible to add the option"), $values], 'OptionAddErrors');
            throw new Exception(
              X::_("Impossible to add the option") . ':' . PHP_EOL . 
              X::getDump($values) . $e->getMessage()
            );
          }

          $id = $this->db->lastId();
        }

        if ($res) {
          $this->deleteCache($id);
        }

        if ($items && Str::isUid($id)) {
          foreach ($items as $item){
            $item[$c['id_parent']] = $id;
            $res              += (int)$this->add($item, $force, $return_num, $with_id);
          }
        }
      }
      else {
        X::log($it, 'OptionAddErrors');
      }

      return $return_num ? $res : $id;
    }

    return null;
  }


  /**
   * Updates an option's row (without changing cfg)
   *
   * ```php
   * X::dump($opt->set(12, [
   *   'id_parent' => $opt->fromCode('bbn_ide'),
   *   'text' => 'My new option',
   *   'code' => 'new_opt',
   *   'myProperty' => 'my value'
   *   'cfg' => [
   *     'sortable' => true,
   *     'Description' => "I am a cool option"
   *   ]
   * ]);
   * // (int) 1
   * ```
   *
   * @param string   $id
   * @param array $data
   * @return int
   */
  public function set($id, array $data)
  {
    if ($this->check() && $this->_prepare($data)) {
      if (isset($data['id'])) {
        unset($data['id']);
      }

      $c =& $this->fields;
      // id_parent cannot be edited this way
      if ($res = $this->db->update(
        $this->class_cfg['table'],
        [
          $c['text'] => $data[$c['text']],
          $c['code'] => !empty($data[$c['code']]) ? $data[$c['code']] : null,
          $c['id_alias'] => !empty($data[$c['id_alias']]) ? $data[$c['id_alias']] : null,
          $c['value'] => $data[$c['value']]
        ],
        [$c['id'] => $id]
      )
      ) {
        $this->deleteCache($id);
        return $res;
      }

    }

    return 0;
  }


  /**
   * Updates an option's row by merging the data and cfg.
   *
   * ```php
   * X::dump($opt->merge(12, [
   *   'id_parent' => $opt->fromCode('bbn_ide'),
   *   'text' => 'My new option',
   *   'code' => 'new_opt',
   *   'myProperty' => 'my value'
   *   'cfg' => [
   *     'sortable' => true,
   *     'Description' => "I am a cool option"
   *   ]
   * ]);
   * // (int) 1
   * ```
   *
   * @param string $id
   * @param array $data
   * @param array|null $cfg
   * @return int|null
   * @throws Exception
   */
  public function merge(string $id, array $data, array|null $cfg = null)
  {
    if ($this->check()
        && ($o = $this->option($id))
    ) {
      $c =& $this->fields;
      $o[$c['text']] = $this->rawText($o[$c['id']]);
      if (!empty($data)) {
        $data = array_merge($o, $data);
        $this->_prepare($data);
        if (isset($data[$c['id']])) {
          unset($data[$c['id']]);
        }
      }

      if ($cfg) {
        $ocfg        = $this->getRawCfg($id);
        $data[$c['cfg']] = json_encode(array_merge($ocfg ? json_decode($ocfg, true) : [], $cfg));
      }

      // id_parent cannot be edited this way
      if ($res = $this->db->update(
        $this->class_cfg['table'],
        $data,
        [$c['id'] => $id]
      )
      ) {
        $this->deleteCache($id);
        return $res;
      }

      return 0;
    }

    return null;
  }


  /**
   * Deletes a row from the options table, deletes the cache and fix order if needed
   *
   * ```php
   * X::dump($opt->remove(12));
   * // (int) 12 Number of options deleted
   * X::dump($opt->remove(12));
   * // (null) The option doesn't exist anymore
   * ```
   *
   * @param string $code Any option(s) accepted by {@link fromCode()}
   * @return int|null The number of affected rows or null if option not found
   */
  public function remove(...$codes)
  {
    if (Str::isUid($id = $this->fromCode(...$codes))
        && ($id !== $this->default)
        && ($id !== $this->root)
        && Str::isUid($id_parent = $this->getIdParent($id))
    ) {
      $num = 0;
      $this->db->delete(
        $this->class_cfg['table'], [
          $this->fields['id_alias'] => $id,
          $this->fields['text'] => null,
          $this->fields['code'] => null,
        ]
      );

      $this->db->update(
        $this->class_cfg['table'], [
          $this->fields['id_alias'] => null
        ], [
          $this->fields['id_alias'] => $id
        ]
      );
      if ($items = $this->items($id)) {
        foreach ($items as $it){
          $num += (int)$this->remove($it);
        }
      }

      $this->deleteCache($id);
      $this->db->update(
        $this->class_cfg['table'], [
          $this->fields['code'] => null
        ], [
          $this->fields['id'] => $id
        ]
      );
      $num += (int)$this->db->delete(
        $this->class_cfg['table'], [
          $this->fields['id'] => $id
        ]
      );
      if ($this->isSortable($id_parent)) {
        $this->fixOrder($id_parent);
      }

      return $num;
    }

    return null;
  }


  /**
   * Deletes an option row with all it's hierarchical structure from the options table and deletes the cache.
   *
   * ```php
   * X::dump($opt->removeFull(12));
   * // (int) 12 Number of options deleted
   * X::dump($opt->removeFull(12));
   * // (null) The option doesn't exist anymore
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()} or the uid
   * @return int|null The number of affected rows or null if option not found
   */
  public function removeFull($code)
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))
        && ($id !== $this->default)
        && ($id !== $this->root)
    ) {
      $res = 0;
      $this->deleteCache($id);
      // All ids below including the one given
      $all = $this->treeIds($id);
      $has_history = History::isEnabled() && History::isLinked($this->class_cfg['table']);
      foreach (array_reverse($all) as $a) {
        $this->db->delete(
          $this->class_cfg['table'], [
            $this->fields['id_alias'] => $a,
            $this->fields['text'] => null,
            $this->fields['code'] => null,
          ]
        );
        $this->db->update(
          $this->class_cfg['table'], [
            $this->fields['id_alias'] => null
          ], [
            $this->fields['id_alias'] => $a
          ]
        );
  
        if ($has_history) {
          $res += (int)$this->db->delete('bbn_history_uids', ['bbn_uid' => $a]);
        }
        else{
          $res += (int)$this->db->delete($this->class_cfg['table'], [$this->fields['id'] => $a]);
        }
      }

      return $res;
    }

    return null;
  }


  /**
   * Sets the alias of an option
   *
   * ```php
   * X::dump($opt->setAlias(26, 32));
   * // (int) 1
   * ```
   *
   * @param string      $id    The ID of the option to be updated
   * @param string|null $alias The alias' option ID
   * @return int The number of affected rows
   */
  public function setAlias($id, $alias = null)
  {
    $res = null;
    if ($this->check()) {
      $res = $this->db->updateIgnore(
        $this->class_cfg['table'], [
        $this->fields['id_alias'] => $alias ?: null
        ], [
        $this->fields['id'] => $id
        ]
      );
      if ($res) {
        $this->deleteCache($id);
      }
    }

    return $res;
  }


  /**
   * Sets the text of an option
   *
   * ```php
   * X::dump($opt->setText(26, "Hello world!"));
   * // (int) 1
   * ```
   *
   * @param int    $id   The ID of the option to be updated
   * @param string $text The new text
   * @return int The number of affected rows
   */
  public function setText($id, ?string $text)
  {
    $res = null;
    if ($this->check()) {
      $res = $this->db->updateIgnore(
        $this->class_cfg['table'], [
        $this->fields['text'] => $text
        ], [
          $this->fields['id'] => $id
        ]
      );
      if ($res) {
        $this->deleteCache($id);
      }
    }

    return $res;
  }


  /**
   * Sets the code of an option
   *
   * ```php
   * X::dump($opt->setCode(26, "HWD"));
   * // (int) 1
   * ```
   *
   * @param int $id The ID of the option to be updated
   * @param string|null $code The new code
   * @return int|null The number of affected rows
   */
  public function setCode($id, string|null $code = null)
  {
    if ($this->check()) {
      return $this->db->updateIgnore(
        $this->class_cfg['table'], [
        $this->fields['code'] => $code ?: null
        ], [
        $this->fields['id'] => $id
        ]
      );
    }

    return null;
  }


  /**
   * @param array $value
   * @param $id
   * @return int|null
   * @throws Exception
   */
  public function setValue(array $value, $id): ?int
  {
    if ($this->check() && $this->dbTraitExists($id)) {
      $c =& $this->class_cfg;
      $f =& $this->fields;
      $this->cacheDelete($id);
      return $this->db->update(
        $c['table'],
        [$f['value'] => json_encode($value)],
        [$f['id'] => $id]
      );
    }

    return null;
  }

  /**
   * Updates option's properties derived from the value column
   *
   * ```php
   * X::dump($opt->setProp(12, 'myProperty', "78%"));
   * // (int) 1
   * X::dump($opt->setProp(12, ['myProperty' => "78%"]));
   * // (int) 0 Already updated, no change done
   * X::dump($opt->setProp(9654, ['myProperty' => "78%"]));
   * // (bool) false Option not found
   * X::dump($opt->setProp(12, ['myProperty' => "78%", 'myProperty2' => "42%"]));
   * // (int) 1
   * X::dump($opt->option(12));
   * /*
   * Before
   * array [
   *   'id' => 12,
   *   'id_parent' => 0,
   *   'code' => 'bbn_ide',
   *   'text' => 'BBN's own IDE',
   *   'myOtherProperty' => "Hello",
   * ]
   * After
   * array [
   *   'id' => 12,
   *   'id_parent' => 0,
   *   'code' => 'bbn_ide',
   *   'text' => 'BBN's own IDE',
   *   'myProperty' => "78%",
   *   'myProperty2' => "42%",
   *   'myOtherProperty' => "Hello",
   * ]
   * ```
   *
   * @param int          $id   The option to update's ID
   * @param array|string $prop An array of properties and values, or a string with the property's name adding as next argument the new value
   * @return int|null the number of affected rows or null if no argument or option not found
   */
  public function setProp($id, $prop)
  {
    if (!empty($id) && !empty($prop) && ($o = $this->optionNoAlias($id))) {
      $args = \func_get_args();
      if (\is_string($prop) && isset($args[2])) {
        $prop = [$prop => $args[2]];
      }

      if (\is_array($prop)) {
        X::log([$o, $prop], "set_prop");
        $change = false;
        foreach ($prop as $k => $v){
          if (!array_key_exists($k, $o) || ($o[$k] !== $v)) {
            $change = true;
            $o[$k]  = $v;
          }

        }

        if ($change) {
          return $this->set($id, $o);
        }
      }

      return 0;
    }

    return null;
  }


  /**
   * Unset option's properties taken from the value column
   *
   * ```php
   * X::dump($opt->unsetProp(12, 'myProperty'));
   * // (int) 1
   * X::dump($opt->unsetProp(12, ['myProperty']));
   * // (int) 0 Already updated, no change done
   * X::dump($opt->unsetProp(9654, ['myProperty']));
   * // (bool) false Option not found
   * X::dump($opt->unsetProp(12, ['myProperty', 'myProperty2']));
   * // (int) 1
   * X::dump($opt->option(12));
   * /*
   * Before
   * array [
   *   'id' => 12,
   *   'id_parent' => 0,
   *   'code' => 'bbn_ide',
   *   'text' => 'BBN's own IDE',
   *   'myProperty' => "78%",
   *   'myProperty2' => "42%",
   *   'myOtherProperty' => "Hello",
   * ]
   * After
   * array [
   *   'id' => 12,
   *   'id_parent' => 0,
   *   'code' => 'bbn_ide',
   *   'text' => 'BBN's own IDE',
   *   'myOtherProperty' => "Hello",
   * ]
   * ```
   *
   * @param string       $id   The option to update's ID
   * @param array|string $prop An array of properties and values, or a string with the property's name adding as next argument the new value
   * @return int|null the number of affected rows or null if no argument or option not found
   */
  public function unsetProp($id, $prop)
  {
    if (!empty($prop) && Str::isUid($id) && ($o = $this->optionNoAlias($id))) {
      if (\is_string($prop)) {
        $prop = [$prop];
      }

      if (\is_array($prop)) {
        $change = false;
        foreach ($prop as $k){
          if (!\in_array($k, $this->fields, true) && array_key_exists($k, $o)) {
            $change = true;
            unset($o[$k]);
          }
        }

        if ($change) {
          return $this->set($id, $o);
        }
      }
    }

    return null;
  }



  /**
   * Sets the cfg column of a given option in the table through an array
   *
   * ```php
   * X::dump($opt->getCfg('bbn_ide'));
   * // array ['sortable' => true]
   * X::dump($opt->setCfg(12, [
   *   'desc' => "I am a cool option",
   *   'sortable' => true
   * ]));
   * // (int) 1
   * X::dump($opt->getCfg('bbn_ide'));
   * // array ['desc' => "I am a cool option", 'sortable' => true];
   * ```
   *
   * @param string|int   $id  The option ID
   * @param array       $cfg The config value
   * @return int|null number of affected rows
   */
  public function setCfg($id, array $cfg, bool $merge = false): ?int
  {
    if ($this->check() && $this->exists($id)) {
      if (isset($cfg['inherited_from'])) {
        unset($cfg['inherited_from']);
      }

      if (isset($cfg['root_alias'])) {
        unset($cfg['root_alias']);
      }

      if (isset($cfg[$this->fields['id']])) {
        unset($cfg[$this->fields['id']]);
      }

      if (isset($cfg['permissions']) && !in_array($cfg['permissions'], ['single', 'cascade', 'all', 'children'])) {
        unset($cfg['permissions']);
      }

      if ($merge && ($old_cfg = $this->getCfg($id))) {
        $cfg = array_merge($old_cfg, $cfg);
      }

      $c =& $this->class_cfg;
      if ($res = $this->db->update(
        $c['table'], [
        $this->fields['cfg'] => $cfg ? json_encode($cfg) : null
        ], [
          $this->fields['id'] => $id
        ]
      )
      ) {
        if ((isset($old_cfg['inheritance'], $cfg['inheritance'])
            && ($old_cfg['inheritance'] !== $cfg['inheritance']))
          || (isset($old_cfg['i18n_inheritance'], $cfg['i18n_inheritance'])
            && ($old_cfg['i18n_inheritance'] !== $cfg['i18n_inheritance']))
        ) {
          $this->deleteCache($id, true);
        }
        else{
          $this->deleteCache($id);
        }

        return $res;
      }

      return 0;
    }

    return null;
  }


  /**
   * Unsets the cfg column (sets it to null)
   *
   * ```php
   * X::dump($opt->getCfg('bbn_ide'));
   * // array ['desc' => "I am a cool option", 'sortable' => true];
   * ```
   *
   * @param string|int $id The option ID
   * @return int|false Number of affected rows or false if not found
   */
  public function unsetCfg($id)
  {
    $res = false;
    if ($this->check() && $this->exists($id)) {
      $res = $this->db->update(
        $this->class_cfg['table'], [
        $this->fields['cfg'] => null
        ], [
        $this->fields['id'] => $id
        ]
      );
      if ($res) {
        $this->deleteCache($id);
      }
    }

    return $res;
  }


  /**
   * Merges an option $src into an existing option $dest
   * Children will change id_parent and references in the same database will be updated
   * The config will remain the one from the destination
   *
   * @todo Finish the example
   * ```php
   * X::dump($opt->option(20), $opt->option(30));
   * X::dump($opt->fusion(30, 20));
   * X::dump($opt->option(20));
   * // (int) 7
   * /* The expression before would have returned
   * array []
   * array []
   * And the resulting option would be
   * array []
   * ```
   *
   * @param int $src  Source option ID, will be
   * @param int $dest Destination option ID, will remain after the fusion
   * @return null|int Number of affected rows
   */
  public function fusion($src, $dest)
  {
    if ($this->check()) {
      $o_src  = $this->option($src);
      $o_dest = $this->option($dest);
      $num    = 0;
      $cf     =& $this->fields;
      if ($o_dest && $o_src) {
        $o_dest[$cf['text']] = $this->rawText($o_dest[$cf['id']]);
        $o_src[$cf['text']] = $this->rawText($o_src[$cf['id']]);
        $o_final = X::mergeArrays($o_src, $o_dest);
        // Order remains the dest one
        $o_final[$cf['num']] = $o_dest[$cf['num']];
        $tables              = $this->db->getForeignKeys($cf['id'], $this->class_cfg['table']);
        foreach ($tables as $table => $cols){
          foreach ($cols as $c){
            $num += (int)$this->db->update($table, [$c => $dest], [$c => $src]);
          }
        }

        if ($opt = $this->options($src)) {
          // Moving children
          foreach ($opt as $id => $text){
            $num += (int)$this->move($id, $dest);
          }
        }

        $num += (int)$this->set($dest, $o_final);
        $num += (int)$this->remove($src);

        $this->deleteCache($o_final[$cf['id_parent']], true);
        $this->deleteCache($o_src[$cf['id_parent']], true);

        if ($this->isSortable($o_src[$cf['id_parent']])) {
          $this->fixOrder($o_src[$cf['id_parent']]);
        }

        if ($this->isSortable($o_final[$cf['id_parent']])) {
          $this->fixOrder($o_final[$cf['id_parent']]);
        }
      }

      return $num;
    }

    return null;
  }


  /**
   * Changes the id_parent of an option
   *
   * ```php
   * X::dump($this->getIdParent(21));
   * // (int) 13
   * X::dump($this->move(21, 12));
   * // (int) 1
   * X::dump($this->getIdParent(21));
   * // (int) 12
   * ```
   *
   * @param int $id        The option's ID
   * @param int $id_parent The new id_parent
   * @return int|null
   */
  public function move($id, $id_parent)
  {
    $res = null;
    if (($o = $this->option($id))
        && ($target = $this->option($id_parent))
    ) {
      $upd = [$this->fields['id_parent'] => $id_parent];
      if ($this->isSortable($id_parent)) {
        $upd[$this->fields['num']] = empty($target['num_children']) ? 1 : $target['num_children'] + 1;
      }

      $res = $this->db->update(
        $this->class_cfg['table'], $upd, [
        $this->fields['id'] => $id
        ]
      );
      $this->deleteCache($id_parent);
      $this->deleteCache($id);
      $this->deleteCache($o[$this->fields['id_parent']]);
    }

    return $res;
  }


  /**
   * Sets the order configuration for each option of a sortable given parent
   *
   * @param int     $id
   * @param boolean $deep
   * @return $this
   */
  public function fixOrder($id, $deep = false)
  {
    if ($this->check() && $this->isSortable($id) && $its = $this->fullOptions($id)) {
      $cf  =& $this->class_cfg;
      $p   = 1;
      foreach ($its as $it) {
        if ($it[$this->fields['num']] !== $p) {
          $this->db->update(
            $cf['table'], [
            $this->fields['num'] => $p
            ], [
            $this->fields['id'] => $it[$this->fields['id']]
            ]
          );
          $this->deleteCache($it[$this->fields['id']]);
        }

        $p++;
        if ($deep) {
          $this->fixOrder($it[$this->fields['id']]);
        }
      }
    }

    return $this;
  }


  /**
   * Transforms an array of parameters into valid option array
   * @param array $it
   * @return bool
   * @throws Exception
   */
  private function _prepare(array &$it): bool
  {
    // The table's columns
    $c =& $this->fields;

    // If id_parent is undefined it uses the default
    if (!isset($it[$c['id_parent']])) {
      $it[$c['id_parent']] = $this->default;
    }
    elseif (is_array($it[$c['id_parent']])) {
      if ($id_parent = $this->fromCode(...$it[$c['id_parent']])) {
        $it[$c['id_parent']] = $id_parent;
      }
      else {
        throw new Exception(X::_("Impossible to find the parent"));
      }
    }
    elseif (!isset($it[$c['id_parent']]) || !$this->exists($it[$c['id_parent']])) {
      throw new Exception(X::_("Impossible to find the parent"));
    }

    if (empty($it[$c['id_alias']])) {
      $it[$c['id_alias']] = null;
    }
    elseif (is_array($it[$c['id_alias']])) {
      if ($id_alias = $this->fromCode(...$it[$c['id_alias']])) {
        $it[$c['id_alias']] = $id_alias;
      }
      else {
        X::log(['Impossible to find the alias', $it[$c['id_alias']]]);
        throw new Exception(X::_("Impossible to find the alias for the path %s", json_encode($it[$c['id_alias']])));
      }
    }
    elseif (!$this->exists($it[$c['id_alias']])) {
      X::log(['The alias does not exist', $it]);
      throw new Exception(X::_("Impossible to find the alias %s", $it[$c['id_alias']]));
    }

    if (array_key_exists($c['id'], $it) && empty($it[$c['id']])) {
      unset($it[$c['id']]);
    }

    if (isset($it[$c['cfg']])) {
      if (!is_array($it[$c['cfg']]) && Str::isJson($it[$c['cfg']])) {
        $it[$c['cfg']] = json_decode($it[$c['cfg']], true);
      }

      $cfg =& $it[$c['cfg']];
      if (is_array($cfg) && !empty($cfg['id_root_alias'])) {
        if (is_array($cfg['id_root_alias'])) {
          if ($id_root_alias = $this->fromCode(...$cfg['id_root_alias'])) {
            $cfg['id_root_alias'] = $id_root_alias;
          }
          else {
            X::log(['No root alias', $cfg['id_root_alias']]);
            throw new Exception(X::_("Impossible to find the root alias"));
          }
        }
        elseif (!$this->exists($cfg['id_root_alias'])) {
          X::log(['The root alias does not exist', $cfg['id_root_alias']]);
          throw new Exception(X::_("Impossible to find the root alias"));
        }
      }
    }
    elseif (array_key_exists('cfg', $it)) {
      // If cfg is not set, it MUST be null
      $it[$c['cfg']] = null;
    }

    // Text is required and parent exists
    if (!empty($it[$c['id_parent']])
        && (!empty($it[$c['text']]) || !empty($it[$c['id_alias']]) || !empty($it[$c['code']]))
        && ($parent = $this->option($it[$c['id_parent']]))
    ) {
      // If the id_parent property is a code or a sequence of codes have to set it as uid
      $it[$c['id_parent']] = $parent[$c['id']];

      // If code is empty it MUST be null
      if (empty($it[$c['code']])) {
        $it[$c['code']] = null;
      }

      // If text is empty it MUST be null
      if (empty($it[$c['text']])) {
        $it[$c['text']] = null;
      }

      $valueIsNull = false;
      // Unsetting computed values
      if (isset($it[$c['value']]) && Str::isJson($it[$c['value']])) {
        $this->_set_value($it);
      }
      elseif (array_key_exists($c['value'], $it) && empty($it[$c['value']]) && !empty($it[$c['id_alias']])) {
        // If value is not a JSON, it must be null
        $it[$c['value']] = null;
        $valueIsNull = true;
      }

      if (array_key_exists('alias', $it)) {
        unset($it['alias']);
      }

      if (array_key_exists('num_children', $it)) {
        unset($it['num_children']);
      }

      if (array_key_exists('items', $it)) {
        unset($it['items']);
      }

      // Taking care of user-defined properties (contained in value)
      $value = [];
      foreach ($it as $k => $v){
        if (!\in_array($k, $c, true)) {
          $value[$k] = $v;
          unset($it[$k]);
        }
      }

      if (!empty($value)) {
        $it[$c['value']] = json_encode($value);
      }
      elseif (!$valueIsNull) {
        if (empty($it[$c['value']])) {
          $it[$c['value']] = null;
        }
        else{
          if (\is_array($it[$c['value']])) {
            $it[$c['value']] = json_encode($it[$c['value']]);
          }
        }
      }

      // Taking care of the config
      if (isset($it[$c['cfg']])) {
        if (is_array($it[$c['cfg']]) && !empty($it[$c['cfg']])) {
          $it[$c['cfg']] = json_encode($it[$c['cfg']]);
        }

        if (!Str::isJson($it[$c['cfg']]) || in_array($it[$c['cfg']], ['{}', '[]'], true)) {
          $it[$c['cfg']] = null;
        }
      }

      $is_sortable = $this->isSortable($parent[$c['id']]);
      // If parent is sortable and order is not defined we define it as last
      if (isset($it[$c['num']]) && !$is_sortable) {
        unset($it[$c['num']]);
      }
      elseif ($is_sortable && empty($it[$c['num']])) {
        $it[$c['num']] = ($parent['num_children'] ?? 0) + 1;
      }

      return true;
    }

    throw new Exception(
      X::_("Impossible to make an option out of it...")
      .PHP_EOL.json_encode($it, JSON_PRETTY_PRINT)
    );
  }


  /**
   * Gives to option's database row array each of the column value's JSON properties
   * Only if value is an associative array value itself will be unset
   * @param array $opt
   * @return array|bool
   */
  private function _set_value(array &$opt): ?array
  {
    if (!empty($opt[$this->fields['value']]) && Str::isJson($opt[$this->fields['value']])) {
      $val = json_decode($opt[$this->fields['value']], true);
      if (X::isAssoc($val)) {
        foreach ($val as $k => $v){
          if (!isset($opt[$k])) {
            $opt[$k] = $v;
          }
        }

        unset($opt[$this->fields['value']]);
      }
      else{
        $opt[$this->fields['value']] = $val;
      }
    }

    return $opt;
  }
}
