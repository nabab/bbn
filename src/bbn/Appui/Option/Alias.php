<?php

namespace bbn\Appui\Option;

use bbn\Str;

trait Alias
{
  /**
   * Returns the id_alias relative to the given id_option
   *
   * @param string $id
   * @return string|null
   */
  public function alias(string $id): ?string
  {
    if ($this->check() && Str::isUid($id)) {
      return $this->db->selectOne(
        $this->class_cfg['table'], $this->fields['id_alias'], [
        $this->fields['id'] => $id
        ]
      );
    }

    return null;
  }


  public function getIdAlias($code = null): ?string
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $cf = $this->getClassCfg();
      return $this->db->selectOne($cf['table'], $this->fields['id_alias'], [$this->fields['id'] => $id]);
    }

    return null;
  }


  /**
   * @param string|null $code
   * @return array|null
   */
  public function getAliases($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $r = [];
      $cf = $this->getClassCfg();
      if ($results = $this->db->rselectAll($cf['table'], [], [$this->fields['id_alias'] => $id])) {
        foreach ($results as $d) {
          if (!empty($d[$this->fields['code']])
            && Str::isInteger($d[$this->fields['code']])
          ) {
            $d[$this->fields['code']] = (int)$d[$this->fields['code']];
          }
          $this->_set_value($d);
          if (!empty($d[$this->fields['text']])) {
            $d[$this->fields['text']] = $this->text($d[$this->fields['id']]);
          }
          $r[] = $d;
        }
      }

      return $r;
    }

    return null;
  }


  /**
   * @param string|null $code
   * @return array|null
   */
  public function getAliasItems($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      if ($res = $this->cacheGet($id, __FUNCTION__)) {
        return $res;
      }

      $cf  = $this->getClassCfg();
      $f   = $this->getFields();
      $res = $this->db->getColumnValues(
        $cf['table'],
        $f['id'],
        [$f['id_alias'] => $id]
      );

      $this->cacheSet($id, __FUNCTION__, $res);
      return $res;
    }

    return null;
  }


  /**
   * @param string|null $code
   * @return array|null
   */
  public function getAliasOptions($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      if ($r = $this->getCache($id, __FUNCTION__)) {
        return $r;
      }

      $res = [];
      if ($items = $this->getAliasItems($id)) {
        foreach ($items as $it) {
          $res[$it] = $this->text($it);
        }
      }

      $this->setCache($id, __FUNCTION__, $res);
      return $res;
    }

    return null;
  }


  /**
   * @param string|null $code
   * @return array|null
   * @throws Exception
   */
  public function getAliasFullOptions($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      if ($r = $this->cacheGet($id, __FUNCTION__)) {
        return $r;
      }

      $res = [];
      if ($items = $this->getAliasItems($id)) {
        foreach ($items as $it) {
          $res[] = $this->option($it);
        }
      }

      $this->cacheSet($id, __FUNCTION__, $res);
      return $res;
    }

    return null;
  }


  /**
   * Returns an array of options based on their id_alias
   *
   * ```php
   * X::dump($opt->optionsByAlias(36));
   * /*
   * array [
   *   ['id' => 18, 'text' => "My option 1", 'code' => "opt1", 'myProperty' => "50%"],
   *   ['id' => 21, 'text' => "My option 4", 'code' => "opt4", 'myProperty' => "60%"],
   *   ['id' => 23, 'text' => "My option 6", 'code' => "opt6", 'myProperty' => "90%"]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null
   */
  public function optionsByAlias($code = null): ?array
  {
    $id_alias = $this->fromCode(\func_get_args());
    if (Str::isUid($id_alias)) {
      $where = [$this->fields['id_alias'] => $id_alias];
      $list  = $this->getRows($where);
      if (\is_array($list)) {
        $res = [];
        foreach ($list as $i){
          $res[] = $this->option($i);
        }

        return $res;
      }
    }

    return null;
  }
}
