<?php

namespace bbn\Models\Tts;

use Exception;
use StdClass;
use bbn\X;

use function array_key_exists;

trait DbData
{
  /**
   * Prepares data before inserting or updating in the database.
   *
   * @param array $data The data to be prepared.
   *
   * @return array The prepared data.
   * @throws Exception If the class config has not been initialized or is incorrect.
   */
  protected function dbTraitPrepare(array $data): array
  {
    // Ensure that the class configuration is initialized
    $this->dbConfigCheck();
    $ccfg = $this->getClassCfg();
    // Get the table index from the class configuration
    $table_index = array_flip($ccfg['tables'])[$ccfg['table']];
    if (!$table_index) {
      throw new Exception(X::_("The class config is not correct as the main table doesn't have an arch"));
    }

    $f = $ccfg['arch'][$table_index];
    $res = [];

    // Handle 'cfg' field if present in the table configuration
    if (!empty($ccfg['cfg'])) {
      if (array_key_exists($f['cfg'], $data)) {
        $res[$f['cfg']] = is_string($data[$f['cfg']]) ? json_decode($data[$f['cfg']], true) : $data[$f['cfg']];
        unset($data[$f['cfg']]);
      }
      else {
        $cfg = [];
        foreach ($ccfg['cfg'] as $v) {
          if (array_key_exists($v['field'], $data)) {
            $cfg[$v['field']] = $data[$v['field']] ?? null;
            unset($data[$v['field']]);
          }
        }
        if (!empty($cfg)) {
          $res[$f['cfg']] = $cfg;
        }
      }
    }

    $structure = $this->dbTraitGetStructure();
    foreach ($data as $k => $v) {
      if (in_array($k, $f)) {
        // Set the value to null if it's empty and not 0 and the field allows null
        if (empty($v)
          && ($v !== 0)
          && isset($structure['fields'][$k])
          && $structure['fields'][$k]['null']
        ) {
          $v = null;
        }

        $res[$k] = $v;
      }
    }

    return $res;
  }

  protected function dbTraitTreat(array ...$rows): array
  {
    // Ensure that the class configuration is initialized
    $this->dbConfigCheck();
    $ccfg = $this->getClassCfg();
    // Get the table index from the class configuration
    $table_index = array_flip($ccfg['tables'])[$ccfg['table']];
    if (!$table_index) {
      throw new Exception(X::_("The class config is not correct as the main table doesn't have an arch"));
    }

    $f = $ccfg['arch'][$table_index];
    $res = [];
    // Handle 'cfg' field if present in the table configuration
    if (empty($f['cfg'])) {
      return [...$rows];
    }
    foreach ($rows as &$data) {
      if (array_key_exists($f['cfg'], $data)) {
        $data[$f['cfg']] = is_string($data[$f['cfg']]) ? json_decode($data[$f['cfg']], true) : $data[$f['cfg']];
        if (!empty($ccfg['cfg'])) {
          foreach ($ccfg['cfg'] as $v) {
            if (isset($v['field']) 
                && array_key_exists($v['field'], $data[$f['cfg']])
                && !array_key_exists($v['field'], $data)) {
              $data[$v['field']] = $data[$f['cfg']][$v['field']];
            }
          }
          unset($data[$f['cfg']]);
        }
      }

      $res[] = $data;
    }

    unset($data);
    return $res;
  }

  protected function dbTraitTransformData(array|stdClass $res): array | stdClass
  {
    return self::transformData($res, $this);
  }


  public static function transformData(array $res, $obj): array | stdClass
  {
    $classCfg = $obj->getClassCfg();
    $f = $classCfg['arch'][$obj->getClassTableIndex()];
    if (!empty($f['cfg'])) {
      foreach ($res as &$r) {
        if (\is_array($res)) {
          if (!empty($r[$f['cfg']])) {
            if (is_string($r[$f['cfg']])) {
              $r[$f['cfg']] = json_decode($r[$f['cfg']], true);
            }

            if (!empty($classCfg['cfg'])) {
              foreach ($classCfg['cfg'] as $v) {
                if (isset($v['field'])
                  && !array_key_exists($v['field'], $r)
                ) {
                  $r[$v['field']] = !empty($r[$f['cfg']])
                    ? ($r[$f['cfg']][$v['field']] ?? null)
                    : null;
                }
              }

              unset($r[$f['cfg']]);
            }

          }
        }
        elseif (!empty($res->{$f['cfg']})) {
          $res->{$f['cfg']} = json_decode($res->{$f['cfg']});
          if (!empty($classCfg['cfg'])) {
            foreach ($classCfg['cfg'] as $v) {
              if (isset($v['field'])
                && !property_exists($res, $v['field'])
              ) {
                $res->{$v['field']} = !empty($res->{$f['cfg']})
                  ? ($res->{$f['cfg']}->{$v['field']} ?? null)
                  : null;
              }
            }

            unset($res->{$f['cfg']});
          }
        }

        unset($r);
      }
    }

    return $res;
  }


}