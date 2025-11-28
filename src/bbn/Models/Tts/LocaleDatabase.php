<?php

namespace bbn\Models\Tts;

use Exception;
use bbn\X;
use bbn\Str;
use bbn\User;
use bbn\Db;
use bbn\Appui\Option;

trait LocaleDatabase
{

  /** @var Db The locale database instance */
  protected $localeDb;

  /** @var string The field in the data that indicates if the record is in the locale database */
  protected $localeField = 'locale';


  /**
   * Returns the database instance depending on whether the record is in the main or locale database
   * @param string $id
   * @param string $table
   * @return Db|null
   */
  public function getRightDb(string $id, string $table): ?Db
  {
    return $this->isLocale($id, $table) ?
      $this->getLocaleDb() :
      $this->db;
  }


  /**
   * Returns the locale database instance
   *
   * @param User|string|null $user
   * @return Db|null
   * @throws Exception
   */
  public function getLocaleDb(User|string|null $user = null): ?Db
  {
    if (!$this->localeDb || !empty($user)) {
      $this->setLocaleDb($user);
    }

    return $this->localeDb ?: null;
  }


  /**
   * Checks if the user has a locale database
   *
   * @param User|string|null $user
   * @return bool
   */
  public function hasLocaleDb(User|string|null $user = null): bool
  {
    $currentUserCls = User::getInstance();
    $userCls = $user instanceof User ? $user : $currentUserCls;
    $userId = is_string($user) && Str::isUid($user) ? $user : $userCls->getId();
    if (isset($this->id_user)
      && ($userId === $this->id_user)
      && !empty($this->localeDb)
    ) {
      return true;
    }

    if ($userId === $currentUserCls->getId()) {
      $userId = null;
    }

    return (bool)$userCls->getLocaleDatabase($userId, false);
  }


  /**
   * Checks if a record is in the locale database
   * @param string $id
   * @param string $table
   * @return bool
   */
  public function isLocale(string $id, string $table): bool
  {
    if (array_search($table, $this->class_cfg['tables'])
      && $this->hasLocaleDb()
      && ($db = $this->getLocaleDb())
      && ($primary = $db->getPrimary($table))
    ) {
      return (bool)$db->count($table, [$primary[0] => $id]);
    }

    return false;
  }


  /**
   * Returns the locale field name
   *
   * @return string
   */
  public function getLocaleField(): string
  {
    return $this->localeField;
  }


  /**
   * Sets the locale database and its structure if needed
   *
   * @param User|string|null $user
   * @return bool
   * @throws Exception
   */
  protected function setLocaleDb(User|string|null $user = null): bool
  {
    $structure = true;
    $currentUserCls = User::getInstance();
    $userCls = $user instanceof User ? $user : $currentUserCls;
    $userId = is_string($user) && Str::isUid($user) ? $user : $userCls->getId();
    if ($userId === $currentUserCls->getId()) {
      $userId = null;
    }

    if (!$this->localeDb || !empty($user)) {
      $this->localeDb = $userCls->getLocaleDatabase($userId);
      $structure = false;
    }

    if (!$this->localeDb) {
      throw new Exception(X::_("Impossible to get the locale user's database"));
    }

    if (!$structure) {
      $optCfg = Option::getInstance()->getClassCfg();
      $usrCfg = $userCls->getClassCfg();
      $tables = [
        $optCfg['table'],
        $usrCfg['table'],
        $usrCfg['tables']['groups']
      ];
      foreach ($this->class_cfg['tables'] as $table) {
        if (!$this->localeDb->tableExists($table)){
          $modelize = $this->db->modelize($table);
          $optionsFields = [];
          foreach ($modelize['keys'] as $k => $v) {
            if (($v['ref_table'] === $optCfg['table'])
              && !in_array($v['columns'][0], $optionsFields)
            ) {
              $optionsFields[] = $v['columns'][0];
            }

            if (in_array($v['ref_table'], $tables)) {
              unset($modelize['keys'][$k]);
            }
          }

          if (isset($modelize['charset'])) {
            unset($modelize['charset']);
          }

          if (isset($modelize['collation'])) {
            unset($modelize['collation']);
          }

          $modelize = $this->db->convert($modelize, $this->localeDb->getEngine());
          if (!empty($optionsFields)) {
            foreach ($modelize['fields'] as $f => &$v) {
              if (in_array($f, $optionsFields)) {
                $v['type'] = 'text';
                unset($v['maxlength']);
              }
            }
          }

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
  protected function normalizeToLocale(array $data, $table): array
  {
    if ($tableIdx = array_search($table, $this->class_cfg['tables'])) {
      $table = $this->class_cfg['tables'][$tableIdx];
      $optCfg = Option::getInstance()->getClassCfg();
      $usrCfg = User::getInstance()->getClassCfg();
      $modelize = $this->db->modelize($table);
      $options = array_values(
        array_map(
          fn($k) => $k['columns'][0],
          array_filter(
            $modelize['keys'],
            fn($k) => !empty($k['columns'])
              && ($k['ref_table'] === $optCfg['table'])
          )
        )
      );
      $toNull = array_values(
        array_map(
          fn($k) => $k['columns'][0],
          array_values(array_filter(
            $modelize['keys'],
            fn($k) => !empty($k['columns'])
              && (($k['ref_table'] === $usrCfg['table'])
                || ($k['ref_table'] === $usrCfg['tables']['groups']))
          )
        )
      ));
      foreach ($data as $field => $value) {
        if (in_array($field, $options) && Str::isUid($value)) {
          $value = $this->opt->toPath($value);
        }

        if (in_array($field, $toNull) && !is_null($value)) {
          $value = null;
        }

        $data[$field] = $value;
      }

      if (isset($data[$this->localeField])) {
        unset($data[$this->localeField]);
      }
    }

    return $data;
  }


  /**
   * Normalizes data fetched from the locale database
   *
   * @param array $data
   * @param string $table
   * @return array
   */
  protected function normalizeFromLocale(array $data, $table): array
  {
    if ($tableIdx = array_search($table, $this->class_cfg['tables'])) {
      $table = $this->class_cfg['tables'][$tableIdx];
      $optCfg = Option::getInstance()->getClassCfg();
      $usrCls = User::getInstance();
      $usrCfg = $usrCls->getClassCfg();
      $modelize = $this->db->modelize($table);
      $options = array_values(
        array_map(
          fn($k) => $k['columns'][0],
          array_filter(
            $modelize['keys'],
            fn($k) => !empty($k['columns'])
              && ($k['ref_table'] === $optCfg['table'])
          )
        )
      );
      $usr = array_values(
        array_map(
          fn($k) => $k['columns'][0],
          array_filter(
            $modelize['keys'],
            fn($k) => !empty($k['columns'])
              && ($k['ref_table'] === $usrCfg['table'])
          )
        )
      );
      foreach ($data as $field => $value) {
        if (in_array($field, $options)
          && !empty($value)
          && !Str::isUid($value)
        ) {
          $value = $this->opt->fromPath($value);
        }

        if (in_array($field, $usr)
          && !Str::isUid($value)
        ) {
          $value = $usrCls->getId();
        }

        $data[$field] = $value;
      }

      $data[$this->localeField] = true;
    }

    return $data;
  }

}
