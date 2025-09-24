<?php

namespace bbn\Models\Tts;

use Exception;
use bbn\X;
use bbn\Mvc;
use bbn\User;

trait LocaleDatabase
{

  /** @var Db The locale database instance */
  protected $localeDb;

  /**
   * Sets the locale database and its structure if needed
   *
   * @return bool
   * @throws Exception
   */
  private function setLocaleDb(?User $user = null): bool
  {
    $structure = true;
    $user = $user ?: User::getInstance();
    if (!$this->localeDb) {
      $this->localeDb = $user->getLocaleDatabase();
      $structure = false;
    }

    if (!$this->localeDb) {
      throw new Exception(X::_("Impossible to get the locale user's database"));
    }

    if (!$structure) {
      foreach ($this->class_cfg['tables'] as $table) {
        if (!$this->localeDb->tableExists($table)){
          $modelize = $this->db->modelize($table);
          $modelize['keys'] = array_filter(
            $modelize['keys'],
            fn($k) => !in_array($k['ref_table'], ['bbn_options', 'bbn_users', 'bbn_users_groups'])
          );
          unset($modelize['charset'], $modelize['collation']);
          $modelize = $this->db->convert($modelize, $this->localeDb->getEngine());
          if (!$this->localeDb->createTable($table, $modelize)) {
            throw new Exception(X::_("Impossible to create the locale table %s", $table));
          }
        }
      }

      $structure = true;
    }

    return $structure;
  }

  /**
   * Normalizes data to be inserted in the locale database
   *
   * @param array $data
   * @param string $table
   * @return array
   */
  private function normalizeToLocale(array $data, $table): array
  {
    $res = [];
    if ($tableIdx = array_search($table, $this->class_cfg['tables'])) {
      $table = $this->class_cfg['tables'][$tableIdx];
      $fields = $this->class_cfg['arch'][$tableIdx];
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

  /**
   * Normalizes data fetched from the locale database
   *
   * @param array $data
   * @param string $table
   * @return array
   */
  private function normalizeFromLocale(array $data, $table): array
  {
    $res = [];
    if ($tableIdx = array_search($table, $this->class_cfg['tables'])) {
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
