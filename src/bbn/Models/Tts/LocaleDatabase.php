<?php

namespace bbn\Models\Tts;

use Exception;
use bbn\X;
use bbn\Mvc;

trait LocaleDatabase
{

  private function setLocaleDb(): bool
  {
    $structure = true;
    if (!$this->localeDb) {
      $this->localeDb = $this->user->getLocaleDatabase();
      $structure = false;
    }

    if (!$this->localeDb) {
      throw new Exception(X::_("Impossible to get the locale user's database"));
    }

    if (!$structure) {
      $cfgFile = Mvc::getPluginPath($this->class_cfg['ref_plugin']) . 'cfg/databaselocale.json';
      if (is_file($cfgFile)
        && ($cfg = json_decode(file_get_contents($cfgFile), true))
      ) {
        foreach ($cfg as $table => $tableCfg) {
          if (!$this->localeDb->tableExists($table)
            && !$this->localeDb->createTable($table, $tableCfg)
          ) {
            throw new Exception(X::_("Impossible to create the locale table %s", $table));
          }
        }

        $structure = true;
      }
    }

    return $structure;
  }


  private function normalizeToLocale(array $data, $table): array
  {
    $res = [];
    if ($tableIdx = array_search($table, $this->class_cfg['tables'])) {
      $table = $this->class_cfg['locale']['tables'][$tableIdx];
      $fields = $this->class_cfg['locale']['arch'][$tableIdx];
      foreach ($data as $field => $value) {
        if ($fieldIdx = array_search($field, $fields)) {
          if ($fieldIdx === 'id_option') {
            $value = $this->opt->toPath($value);
          }

          $res[$fields[$fieldIdx]] = $value;
        }
      }
    }

    return $res;
  }


  private function normalizeFromLocale(array $data, $table): array
  {
    $res = [];
    if ($tableIdx = array_search($table, $this->class_cfg['locale']['tables'])) {
      $table = $this->class_cfg['tables'][$tableIdx];
      $fields = $this->class_cfg['arch'][$tableIdx];
      foreach ($data as $field => $value) {
        if ($fieldIdx = array_search($field, $fields)) {
          if ($fieldIdx === 'id_option') {
            $value = $this->opt->fromPath($value);
          }

          $res[$fields[$fieldIdx]] = $value;
        }
      }

      foreach ($fields as $i => $f) {
        if (!array_key_exists($f, $res)) {
          $res[$f] = $i === 'public' ? 0 : null;
        }
      }
    }

    return $res;
  }

}
