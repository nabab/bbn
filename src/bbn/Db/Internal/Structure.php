<?php

namespace bbn\Db\Internal;

trait Structure
{
  /****************************************************************
   *                                                              *
   *                                                              *
   *                       STRUCTURE HELPERS                      *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * @param $tables
   * @return array
   * @throws Exception
   */
  public function getFieldsList($tables): array
  {
    return $this->language->getFieldsList($tables);
  }


  /**
   * Return an array with tables and fields related to the searched foreign key.
   *
   * ```php
   * X::dump($db->getForeignKeys('id', 'table_users', 'db_example'));
   * // (Array)
   * ```
   *
   * @param string $col The column's name
   * @param string $table The table's name
   * @param string|null $db The database name if different from the current one
   * @return array with tables and fields related to the searched foreign key
   */
  public function getForeignKeys(string $col, string $table, string|null $db = null): array
  {
    return $this->language->getForeignKeys($col, $table, $db);
  }


  /**
   * Return true if in the table there are fields with auto-increment.
   * Working only on mysql.
   *
   * ```php
   * X::dump($db->hasIdIncrement('table_users'));
   * // (bool) 1
   * ```
   *
   * @param string $table The table's name
   * @return bool
   */
  public function hasIdIncrement(string $table): bool
  {
    if (method_exists($this->language, 'hasIdIncrement')) {
      return $this->language->hasIdIncrement($table);
    }

    return false;
  }


  /**
   * Return the table's structure as an indexed array.
   * 
   * X::hdump($ctrl->db->modelize('my_date_2')); /*    
   * "fields": {
   *     "ID": {
   *         "position": 1,
   *         "type": "int",
   *         "null": 0,
   *         "key": null,
   *         "extra": "",
   *         "signed": true,
   *         "virtual": false,
   *         "generation": "",
   *         "maxlength": 10,
   *     },
   * 
   *
   * @param null|array|string $table The table's name
   * @param bool              $force If set to true will force the modernization to re-perform even if the cache exists
   * @return null|array
   */
  public function modelize($table = null, bool $force = false): ?array
  {
    return $this->language->modelize($table, $force);
  }


  /**
   * Converts the given configuration (modelize) to the given engine.
   * @param array $cfg The configuration to convert
   * @param string $engine The engine to convert to
   * @return array
   */
  public function convert(array $cfg, string $engine): array
  {
    return $this->language->convert($cfg, $engine);
  }


  /**
   * 
   */
  public function getColMaxLength(string $column, string|null $table = null): ?int
  {
    return $this->language->getColMaxLength($column, $table);
  } 

  /** 
   * Return the table's structure as an indexed array.
   * 
   * ```php
   * X::hdump($ctrl->db->fmodelize('my_date_2'));
   * ```
   * 
   * @param string $table
   * @param bool   $force
   * @return null|array
   */
  public function fmodelize(string $table = '', bool $force = false): ?array
  {
    if (method_exists($this->language, 'fmodelize')) {
      return $this->language->fmodelize($table, $force);
    }

    return null;
  }


  /**
   * find_references
   *
   * @param $column
   * @param string $db
   * @return array|bool
   *
   */
  public function findReferences($column, string $db = ''): array
  {
    if (method_exists($this->language, 'findReferences')) {
      return $this->language->findReferences($column, $db);
    }

    return [];
  }


  /**
   * find_relations
   *
   * @param $column
   * @param string $db
   * @return array|bool
   */
  public function findRelations($column, string $db = ''): ?array
  {
    return $this->language->findRelations($column, $db);
  }


  /**
   * Return primary keys of a table as a numeric array.
   *
   * ```php
   * X::dump($db-> get_primary('table_users'));
   * // (array) ["id"]
   * ```
   *
   * @param string $table The table's name
   * @return array
   */
  public function getPrimary(string $table): array
  {
    return $this->language->getPrimary($table);
  }


  /**
   * Return primary keys of a table as a string if there is a single-column unique key.
   *
   * ```php
   * X::dump($db-> getSinglePrimary('table_users'));
   * // (string) "id"
   * ```
   *
   * @param string $table The table's name
   * @return array
   */
  public function getSinglePrimary(string $table): ?string
  {
    $primaries = $this->language->getPrimary($table);
    if (count($primaries) === 1) {
      return $primaries[0];
    }

    return null;
  }


  /**
   * Return the unique primary key of the given table.
   *
   * ```php
   * X::dump($db->getUniquePrimary('table_users'));
   * // (string) id
   * ```
   *
   * @param string $table The table's name
   * @return null|string
   */
  public function getUniquePrimary(string $table): ?string
  {
    if (method_exists($this->language, 'getUniquePrimary')) {
      return $this->language->getUniquePrimary($table);
    }

    return null;
  }


  /**
   * Return the unique keys of a table as a numeric array.
   *
   * ```php
   * X::dump($db->getUniqueKeys('table_users'));
   * // (array) ["userid", "userdataid"]
   * ```
   *
   * @param string $table The table's name
   * @return array
   */
  public function getUniqueKeys(string $table): array
  {
    if (method_exists($this->language, 'getUniqueKeys')) {
      return $this->language->getUniqueKeys($table);
    }

    return [];
  }


  /**
   * Changes the charset to the given database
   * @param string $database The database's name
   * @param string $charset The charset to set
   * @param string $collation The collation to set
   */
  public function setDatabaseCharset(string $database, string $charset, string $collation): bool
  {
    if (method_exists($this->language, 'setDatabaseCharset')) {
      return $this->language->setDatabaseCharset($database, $charset, $collation);
    }
    return false;
  }


  /**
   * Changes the charset to the given table
   * @param string $table The table's name
   * @param string $charset The charset to set
   * @param string $collation The collation to set
   */
  public function setTableCharset(string $table, string $charset, string $collation): bool
  {
    if (method_exists($this->language, 'setTableCharset')) {
      return $this->language->setTableCharset($table, $charset, $collation);
    }
    return false;
  }


  /**
   * Changes the charset to the given column
   * @param string $table The table's name
   * @param string $column The column's name
   * @param string $charset The charset to set
   * @param string $collation The collation to set
   */
  public function setColumnCharset(string $table, string $column, string $charset, string $collation): bool
  {
    if (method_exists($this->language, 'setColumnCharset')) {
      return $this->language->setColumnCharset($table, $column, $charset, $collation);
    }
    return false;
  }

}
