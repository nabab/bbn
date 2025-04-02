<?php

namespace bbn\Appui\Option;

use Exception;
use bbn\X;
use bbn\Str;

trait Manip
{

  private $isExporting = false;

  /**
   * @param string $id
   * @param string $mode
   * @return array|null
   * @throws Exception
   */
  public function export(string $id, string $mode = 'single'): ?array
  {
    $this->isExporting = true;
    $this->deleteCache();
    $default = $this->getDefault();
    $this->setDefault($this->root);
    $o = null;
    $modes = ['children', 'full', 'sfull', 'schildren', 'simple', 'single'];
    if (!in_array($mode, $modes)) {
      throw new Exception(X::_("The given mode is forbidden"));
    }

    $simple = false;
    switch ($mode) {
      case 'single':
        $o = $this->rawOption($id);
        break;
      case 'simple':
        $o = $this->option($id);
        $simple = true;
        break;
      case 'schildren':
        $o = $this->fullOptions($id);
        $simple = true;
        break;
      case 'children':
        $o = $this->exportDb($id, false, true);
        break;
      case 'full':
        $o = $this->exportDb($id, true, true);
        break;
      case 'sfull':
        $o = $this->fullTree($id);
        $simple = true;
        break;
    }

    if ($o) {
      if ($simple) {
        $opt =& $this;
        $fn  = function ($o) use (&$opt) {

          $o[$opt->fields['text']] = $opt->rawText($o[$opt->fields['id']]);

          $cfg = $opt->getCfg($o[$this->fields['id']]);
          if (!is_array($cfg) || !empty($cfg['inherit_from'])) {
            $cfg = [];
          }
          elseif (!empty($cfg['schema']) && is_string($cfg['schema'])) {
            $cfg['schema'] = json_decode($cfg['schema'], true);
          }

          //if (isset($cfg['id'])) {
            //unset($cfg['id']);
          //}

          if (isset($cfg['scfg'])
              && !empty($cfg['scfg']['schema']) && is_string($cfg['scfg']['schema'])
          ) {
            $cfg['scfg']['schema'] = json_decode($cfg['scfg']['schema'], true);
          }

          if (!empty($cfg['id_root_alias'])) {
            if ($codes = $opt->getCodePath($cfg['id_root_alias'])) {
              $cfg['id_root_alias'] = $codes;
            }
            else {
              unset($cfg['id_root_alias']);
            }
          }

          foreach ($cfg as $n => $v) {
            if (!$v) {
              unset($cfg[$n]);
            }
          }

          if (!empty($cfg)) {
            $o[$this->fields['cfg']] = $cfg;
          }

          unset($o[$this->fields['id']]);
          unset($o[$this->fields['id_parent']]);
          if (isset($o['num_children'])) {
            unset($o['num_children']);
          }

          if (isset($o['alias'])) {
            unset($o['alias']);
          }

          foreach ($o as $n => $v) {
            if (!$v) {
              unset($o[$n]);
            }
          }

          if (!empty($o[$this->fields['id_alias']])
              && ($codes = $opt->getCodePath($o[$this->fields['id_alias']]))
          ) {
            $o[$this->fields['id_alias']] = $codes;
          }
          else {
            unset($o[$this->fields['id_alias']]);
          }

          return $o;
        };

        switch ($mode) {
          case 'simple':
            $o = $fn($o);
            break;
          case 'schildren':
            $o = X::map($fn, $o, 'items');
            break;
          case 'sfull':
            $o = $fn($o);
            $o['items'] = empty($o['items']) ? [] : X::map($fn, $o['items'], 'items');
            break;
        }
      }

    }
    
    $this->setDefault($default);
    $this->isExporting = false;
    $this->deleteCache();
    return $o;
  }


  /**
   * Converts an option or a hierarchy to a multi-level array with JSON values
   * If $return is false the resulting array will be printed
   *
   * ```php
   * ```
   *
   * @todo Example output
   * @param int     $id     The ID of the option to clone
   * @param boolean $deep   If set to true children will be included
   * @param boolean $return If set to true the resulting array will be returned
   * @return array|string|null
   */
  public function exportDb($id, bool $deep = false, bool $return = false, bool $aliases = false)
  {
    if (($ret = $deep ? $this->rawTree($id) : $this->rawOptions($id))) {
      $ret  = $this->analyzeOut($ret);
      $res  = [];
      $done = [];
      $max  = 3;
      foreach ($ret['options'] as $i => $o) {
        if (!$i || in_array($o[$this->fields['id_parent']], $done, true)) {
          if (empty($o[$this->fields['id_alias']])) {
            $res[]  = $o;
            $done[] = $o[$this->fields['id']];
          }
        }
      }

      while ($max && (count($res) < count($ret['options']))) {
        foreach ($ret['options'] as $i => $o) {
          if (!empty($o[$this->fields['id_alias']])
              && !in_array($o[$this->fields['id']], $done, true)
              && in_array($o[$this->fields['id_parent']], $done, true)
              && in_array($o[$this->fields['id_alias']], $done, true)
          ) {
            $res[]  = $o;
            $done[] = $o[$this->fields['id']];
          }
        }

        $max--;
      }

      if (count($res) < count($ret['options'])) {
        foreach ($ret['options'] as $i => $o) {
          if (!in_array($o[$this->fields['id_parent']], $done, true)) {
            $o[$this->fields['id_parent']] = $this->getCodePath($o[$this->fields['id_parent']]);
          }

          if (!empty($o[$this->fields['id_alias']])
              && !in_array($o[$this->fields['id']], $done, true)
          ) {
            if (!in_array($o[$this->fields['id_alias']], $done, true)) {
              $code_path     = $this->getCodePath($o[$this->fields['id_alias']]);
              $o[$this->fields['id_alias']] = $code_path ?: $o[$this->fields['id_alias']];
            }

            $res[]  = $o;
            $done[] = $o[$this->fields['id']];
          }
        }
      }

      return $return ? $res : var_export($res, 1);
    }

    return null;
  }


  public function importAll(array $options, $id_parent = null) {
    $res = 0;
    foreach ($this->import($options, $id_parent) as $num) {
      $res += $num;
    }

    return $res;
  }


  /**
   * Insert into the option table an exported array of options
   *
   * ```php
   * ```
   *
   * @todo Usage example
   * @param array    $options   An array of option(s) as export returns it
   * @param array|string|int|null $id_parent The option target, if not specified {@link default}
   * @param bool $no_alias If set to true, aliases values won't be set
   * @param array|null $todo
   * @return iterable|null
   */
  public function import(array $options, null|array|string|int $id_parent = null, $no_alias = false, ?array &$todo = null)
  {
    if (is_array($id_parent)) {
      array_push($id_parent, $this->getRoot());
      $id_parent = $this->fromCode($id_parent);
    }
    elseif (null === $id_parent) {
      $id_parent = $this->getDefault();
    }

    if (empty($todo)) {
      $this->cacheDeleteAll();
    }

    if (!empty($options) && $this->check() && $this->exists($id_parent)) {
      $c       =& $this->fields;
      $is_root = false;
      if ($todo === null) {
        $is_root = true;
        $todo    = [];
      }

      if (X::isAssoc($options)) {
        $options = [$options];
      }

      foreach ($options as $o) {
        $tmp   = [];
        $items = [];
        /** @todo Temp solution */
        if (empty($o) || !is_array($o)) {
          continue;
        }

        if (isset($o[$c['id']])) {
          if ($this->exists($o[$c['id']])) {
            unset($o[$c['id']]);
          }
        }

        $o[$c['id_parent']] = $id_parent ?: $this->default;
        if (isset($o['items'])) {
          $items = $o['items'] ?: null;
          unset($o['items']);
        }


        $hasNoText = empty($o[$c['text']]);
        if (isset($o[$c['id_alias']])) {
          $tmp['id_alias'] = $o[$c['id_alias']];
          if ($hasNoText) {
            $o[$c['text']] = 'waiting for alias';
          }
          unset($o[$c['id_alias']]);
        }

        if (isset($o[$c['cfg']]) && !empty($o[$c['cfg']]['id_root_alias'])) {
          $tmp['id_root_alias'] = $o[$c['cfg']]['id_root_alias'];
          unset($o[$c['cfg']]['id_root_alias']);
        }

        if ($id = $this->add($o, true)) {
          yield 1;
          if (!empty($tmp)) {
            $todo[$id] = $tmp;
          }

          if (!empty($items)) {
            foreach ($this->import($items, $id, $no_alias, $todo) as $success) {
              yield $success;
            }
          }
        }
        else {
          X::log($o);
          throw new Exception(X::_("Error while importing: impossible to add"));
        }
      }

      if (!$no_alias && $is_root && !empty($todo)) {
        foreach ($todo as $id => $td) {
          if (!empty($td['id_alias'])) {
            if ($id_alias = $this->fromCode(...$td['id_alias'])) {
              try {
                $this->setAlias($id, $id_alias);
                if ($hasNoText) {
                  $this->setText($id, null);
                }
              }
              catch (Exception $e) {
                throw new Exception($e->getMessage());
              }
            }
            else {
              X::log($td['id_alias']);
              throw new Exception(
                X::_(
                  "Error while importing: impossible to set the alias %s",
                  json_encode($td, JSON_PRETTY_PRINT)
                )
              );
            }
          }

          if (!empty($td['id_root_alias'])
              && ($id_root_alias = $this->fromCode(...$td['id_root_alias']))
          ) {
            $this->setcfg($id, ['id_root_alias' => $id_root_alias], true);
          }
        }
      }

    }
  }


  /**
   * Copies and insert an option into a target option
   *
   * ```php
   * ```
   *
   * @todo Usage example
   * @param int|string  $id     The source option's ID
   * @param int|string  $target The destination option's ID
   * @param boolean     $deep   If set to true, children will also be duplicated
   * @param boolean     $force  If set to true and option exists it will be merged
   * @return int|null The number of affected rows or null if option not found
   */
  public function duplicate($id, $target, $deep = false, $force = false, $return_num = false)
  {
    $res    = null;
    $target = $this->fromCode($target);
    if (Str::isUid($target)) {
      if ($opt = $this->export($id, $deep ? 'sfull' : 'simple')) {
        foreach ($this->import($opt, $target) as $num) {
          $res += $num;
        }
        $this->deleteCache($target);
      }
    }

    return $res;
  }


  /**
   * Applies a function to children of an option and updates the database
   *
   * ```php
   * ```
   *
   * @todo Usage example
   * @param callable  $f    The function to apply (the unique argument will be the option as in {@link option()}
   * @param int|array $id   The options' ID on which children the function should be applied
   * @param boolean   $deep If set to true the function will be applied to all children's levels
   * @param boolean   $force If set to true it will update the row in db without checking with $originals
   * @return int|null The number of affected rows or null if option not found
   */
  public function apply(callable $f, $id, $deep = false, bool $force = false)
  {
    if ($this->check()) {
      $originals = \is_array($id) ? $id : ( $deep ? $this->fullTree($id) : $this->fullOptions($id) );
      if (isset($originals['items'])) {
        $originals = $originals['items'];
      }
      $t = $this;
      $originals = $this->map(function($o) use($t){
        $o[$t->fields['text']] = $t->rawText($o[$this->fields['id']]);
        return $o;
      }, $originals, $deep);
      $opts = $this->map($f, $originals, $deep);
      if (\is_array($opts)) {
        $changes = 0;
        foreach ($opts as $i => $o){
          if ($force || $originals[$i] !== $o) {
            $changes += (int)$this->set($o[$this->fields['id']], $o);
          }

          if ($deep && !empty($o['num_children']) && !empty($o['items'])) {
            $changes += (int)$this->apply($f, $o, 1, true);
          }
        }

        return $changes;
      }
    }

    return null;
  }


  /**
   * Applies a function to children of an option
   *
   * ```php
   * ```
   *
   * @todo Usage example
   * @param callable  $f    The function to apply (the unique argument will be the option as in {@link option()}
   * @param int|array $id   The options' ID on which children the function should be applied
   * @param boolean|int   $deep If set to true the function will be applied to all children's levels
   * @return array The new array with the function applied
   */
  public function map(callable $f, $id, $deep = false)
  {
    $opts = \is_array($id) ? $id : ( $deep ? $this->fullTree($id) : $this->fullOptions($id) );
    $res  = [];
    if (\is_array($opts)) {
      if (isset($opts['items'])) {
        $opts = $opts['items'];
      }

      foreach ($opts as $i => $o){
        $opts[$i] = $f($o);
        if ($deep && $opts[$i] && !empty($opts[$i]['items'])) {
          $opts[$i]['items'] = $this->map($f, $opts[$i]['items'], 1);
        }

        if (\is_array($opts[$i])) {
          $res[] = $opts[$i];
        }
      }
    }

    return $res;
  }


  /**
   * Applies a function to children of an option, with the cfg array included
   *
   * ```php
   * ```
   *
   * @todo Usage example
   * @param callable  $f    The function to apply (the unique argument will be the option as in {@link option()}
   * @param int|array $id   The options'ID on which children the function should be applied
   * @param boolean   $deep If set to true the function will be applied to all children's levels
   * @return array The new array with the function applied
   */
  public function mapCfg(callable $f, $id, $deep = false)
  {
    $opts = \is_array($id) ? $id : ( $deep ? $this->fullTree($id) : $this->fullOptions($id) );
    if (isset($opts['items'])) {
      $opts = $opts['items'];
    }

    $res = [];
    if (\is_array($opts)) {
      foreach ($opts as $i => $o){
        $o[$this->fields['cfg']] = $this->getCfg($o[$this->fields['id']]);
        $opts[$i] = $f($o);
        if ($deep && $opts[$i] && !empty($opts[$i]['items'])) {
          $opts[$i]['items'] = $this->mapCfg($f, $opts[$i]['items'], 1);
        }

        if (\is_array($opts[$i])) {
          $res[] = $opts[$i];
        }
      }
    }

    return $res;
  }


  /**
   * @param array $options
   * @param array $results
   * @return array|null
   */
  public function analyzeOut(array $options, array &$results = [])
  {
    if ($this->check()) {

      if (isset($options[0]) && is_array($options[0])) {
        foreach ($options as $option) {
          $this->analyzeOut($option, $results);
        }
        return $results;
      }

      if (empty($results)) {
        $results['options'] = [];
        $results['ids']     = [];
        $results['aliases'] = [];
      }

      if (!empty($options[$this->fields['id']])) {
        $results['ids'][$options[$this->fields['id']]] = null;
      }

      if (!empty($options[$this->fields['id_alias']])) {
        $results['aliases'][$options[$this->fields['id_alias']]] = [
          'id' => null,
          'codes' => $this->getCodePath($options[$this->fields['id_alias']])
        ];
      }

      $items = false;
      if (!empty($options['items'])) {
        $items = $options['items'];
        unset($options['items']);
      }

      $results['options'][] = $options;
      if ($items) {
        foreach ($items as $it) {
          $this->analyzeOut($it, $results);
        }
      }

      return $results;
    }

    return null;
  }

}
