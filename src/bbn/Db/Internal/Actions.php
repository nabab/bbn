<?php

namespace bbn\Db\Internal;

use Exception;
use bbn\X;
use bbn\Str;

trait Actions
{
  /****************************************************************
   *                                                              *
   *                                                              *
   *                        ACTIONS INTERFACE                     *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Return the first row resulting from the query as an array indexed with the fields' name.
   *
   * ```php
   * X::dump($db->getRow("SELECT id, name FROM table_users WHERE id > ? ", 2));;
   *
   * /* (array)[
   *        "id" => 3,
   *        "name" => "thomas",
   *        ]
   * ```
   *
   * @param string query.
   * @param int The var ? value.
   * @return array|false
   *
   */
  public function getRow(): ?array
  {
    return $this->language->getRow(...\func_get_args());
  }


  /**
   * Return an array that includes indexed arrays for every row resultant from the query.
   *
   * @param string
   * @param int The var ? value
   * @return array|false
   */
  public function getRows(): ?array
  {
    return $this->language->getRows(...\func_get_args());
  }


  /**
   * Return a row as a numeric indexed array.
   *
   * ```php
   * X::dump($db->getIrow("SELECT id, name, surname FROM table_users WHERE id > ?", 2));
   * /* (array) [
   *              3,
   *              "john",
   *              "brown",
   *             ]
   * ```
   *
   * @param string query
   * @param int The var ? value
   * @return array | false
   */
  public function getIrow(): ?array
  {
    return $this->language->getIrow(...\func_get_args());
  }


  /**
   * Return an array of numeric indexed rows.
   *
   * ```php
   * X::dump($db->getIrows("SELECT id, name FROM table_users WHERE id > ? LIMIT ?", 2, 2));
   * /*
   * (array)[
   *         [
   *          3,
   *         "john"
   *         ],
   *         [
   *         4,
   *         "barbara"
   *        ]
   *       ]
   * ```
   *
   * @return null|array
   */
  public function getIrows(): ?array
  {
    return $this->language->getIrows(...\func_get_args());
  }


  /**
   * Return an array indexed on the searched field's in which there are all the values of the column.
   *
   * ```php
   * X::dump($db->getByColumns("SELECT name, surname FROM table_users WHERE id > 2"));
   * /*
   * (array) [
   *      "name" => [
   *       "John",
   *       "Michael"
   *      ],
   *      "surname" => [
   *        "Brown",
   *        "Smith"
   *      ]
   *     ]
   * ```
   *
   * @param string query
   * @return null|array
   */
  public function getByColumns(): ?array
  {
    return $this->language->getByColumns(...\func_get_args());
  }


  /**
   * Return the first row resulting from the query as an object (similar to {@link getObject()}).
   *
   * ```php
   * X::dump($db->getObj("SELECT surname FROM table_users"));
   * /*
   * (obj){
   *       "name" => "Smith"
   *       }
   * ```
   *
   * @return null|\stdClass
   */
  public function getObj(): ?\stdClass
  {
    return $this->getObject(...\func_get_args());
  }


  /**
   * Return the first row resulting from the query as an object.
   * Synonym of get_obj.
   *
   * ```php
   * X::dump($db->getObject("SELECT name FROM table_users"));
   * /*
   * (obj){
   *       "name" => "John"
   *       }
   * ```
   *
   * @return null|\stdClass
   */
  public function getObject(): ?\stdClass
  {
    return $this->language->getObject(...\func_get_args());
  }


  /**
   * Return an array of stdClass objects.
   *
   * ```php
   * X::dump($db->getObjects("SELECT name FROM table_users"));
   *
   * /*
   * (array) [
   *          Object stdClass: df {
   *            "name" => "John",
   *          },
   *          Object stdClass: df {
   *            "name" => "Michael",
   *          },
   *          Object stdClass: df {
   *            "name" => "Thomas",
   *          },
   *          Object stdClass: df {
   *            "name" => "William",
   *          },
   *          Object stdClass: df {
   *            "name" => "Jake",
   *          },
   *         ]
   * ```
   *
   * @return null|array
   */
  public function getObjects(): ?array
  {
    return $this->language->getObjects(...\func_get_args());
  }


  /**
   * Returns a list of charsets available for the current language.
   *
   * @return array|null
   * @throws Exception
   */
  public function charsets(): ?array
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->charsets();
  }


  /**
   * Returns a list of collations available for the current language.
   *
   * @return array|null
   * @throws Exception
   */
  public function collations(): ?array
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->collations();
  }


  /**
   * Creates a database
   *
   * @param string $database
   * @return bool
   */
  public function createDatabase(string $database): bool
  {
    return $this->language->createDatabase(...\func_get_args());
  }


  /**
   * Drops the given database
   *
   * @param string $database
   * @return bool
   */
  public function dropDatabase(string $database): bool
  {
    return $this->language->dropDatabase($database);
  }


  /**
   * Renames the given database to the new given name.
   *
   * @param string $oldName The current database's name
   * @param string $newName The new name.
   * @return bool True if it succeeded
   */
  public function renameDatabase(string $oldName, string $newName): bool
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);
    return $this->language->renameDatabase($oldName, $newName);
  }


  /**
   * Duplicates a database
   *
   * @param string $source The source database name
   * @param string $target The target database name
   * @param bool $withData If true, the data will be copied too
   * @return bool True if it succeeded
   */
  public function duplicateDatabase(string $source, string $target, bool $withData = true): bool
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);
    return $this->language->duplicateDatabase($source, $target, $withData);
  }


  /**
   * Returns the charset of the given database
   *
   * @param string $database
   * @return string|null
   */
  public function getDatabaseCharset(string $database): ?string
  {
    return $this->language->getDatabaseCharset($database);
  }


  /**
   * Returns the collation of the given database
   *
   * @param string $database
   * @return string|null
   */
  public function getDatabaseCollation(string $database): ?string
  {
    return $this->language->getDatabaseCollation($database);
  }


  /**
   * Returns true if the given table exists
   *
   * @param string $table
   * @param string $database. or currently selected if none
   * @return boolean
   */
  public function tableExists(string $table, string $database = ''): bool
  {
    return $this->language->tableExists($table, $database);
  }


  /**
   * Creates a table
   *
   * @param string $table
   * @param array|null $cfg
   * @param bool $createKeys
   * @param bool $createConstraints
   * @return bool
   */
  public function createTable(
    string $table,
    ?array $cfg = null,
    bool $createKeys = true,
    bool $createConstraints = true
  ): bool
  {
    return $this->language->createTable($table, $cfg, $createKeys, $createConstraints);
  }


  /**
   * Drops the given table, in the current database if none given
   *
   * @param string $database
   * @return bool
   */
  public function dropTable(string $table, string $database = ''): bool
  {
    return $this->language->dropTable($table, $database);
  }


  /**
   * Duplicates a table
   *
   * @param string $source The source table name
   * @param string $target The target table name
   * @param bool $withData If true, the data will be copied too
   * @return bool True if it succeeded
   */
  public function duplicateTable(string $source, string $target, bool $withData = true): bool
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);
    return $this->language->duplicateTable($source, $target, $withData);
  }


  /**
   * Copies a table to another database
   *
   * @param string $table The source table name
   * @param self $target The target database connection
   * @param bool $withData If true, the data will be copied too
   * @return bool True if it succeeded
   */
  public function copyTableTo(string $table, self $target, bool $withData = true): bool
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);
    return $this->language->copyTableTo($table, $target, $withData);
  }


  /**
   * Returns the charset of the given table
   *
   * @param string $table
   * @return string|null
   */
  public function getTableCharset(string $table): ?string
  {
    return $this->language->getTableCharset($table);
  }


  /**
   * Returns the collation of the given table
   *
   * @param string $table
   * @return string|null
   */
  public function getTableCollation(string $table): ?string
  {
    return $this->language->getTableCollation($table);
  }


  /**
   * Creates a column in the given table with the given configuration.
   * @param string $table The table's name
   * @param string $col The column's name
   * @param array $cfg The configuration of the column
   * @return bool
   */
  public function createColumn(string $table, string $col, array $cfg): bool
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);
    return $this->language->createColumn($table, $col, $cfg);
  }


  /**
   * Drops the given column from the given table.
   * @param string $table
   * @param string $column
   * @return bool
   */
  public function dropColumn(string $table, string $col): bool
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);
    return $this->language->dropColumn($table, $col);
  }


  /**
   * Alters the given column in the given table with the given configuration.
   * @param string $table The table's name
   * @param string $col The column's name
   * @param array $cfg The configuration of the column
   * @return bool
   */
  public function alterColumn(string $table, string $col, array $cfg): bool
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);
    return $this->language->alterColumn($table, $col, $cfg);
  }


  /**
   * Creates the constraints for the given table
   *
   * @param string $table The table's name
   * @param array|null $cfg The configuration
   * @return bool True if it succeeded
   * @throws Exception
   */
  public function createConstraints(string $table, ?array $cfg = null): bool
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);
    return $this->language->createConstraints($table, $cfg);
  }


  /**
   * Drops the given constraint from the table
   *
   * @param string $table The table's name
   * @param string $constraint The constraint's name
   * @return bool True if it succeeded
   * @throws Exception
   */
  public function dropConstraint(string $table, string $constraint): bool
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);
    return $this->language->dropConstraint($table, $constraint);
  }


  /**
   * @return void
   */
  public function enableLast()
  {
    if (method_exists($this->language, __FUNCTION__)) {
      $this->language->enableLast();
    }
  }


  /**
   * @return void
   */
  public function disableLast()
  {
    if (method_exists($this->language, __FUNCTION__)) {
      $this->language->disableLast();
    }
  }

  /**
   * @return array|null
   * @throws Exception
   */
  public function getRealLastParams(): ?array
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getRealLastParams();
  }


  /**
   * @return string|null
   * @throws Exception
   */
  public function realLast(): ?string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->realLast();
  }


  /**
   * @return array|null
   * @throws Exception
   */
  public function getLastParams(): ?array
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getLastParams();
  }


  /**
   * @return array|null
   * @throws Exception
   */
  public function getLastValues(): ?array
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getLastValues();
  }


  public function getQuery(array $cfg): Query
  {
    if (!isset($cfg['kind'])) {
      $cfg['kind'] = 'SELECT';
    }

    if ($cfg = $this->processCfg($cfg)) {
      return $this->language->query($cfg['sql'], ...array_map(function($a) {
        return Str::isUid($a) ? hex2bin($a) : $a;
      }, $cfg['values']));
    }

    throw new Exception(X::_("Impossible to make a query"));
  }


  /**
   * @param array $cfg
   * @return array
   * @throws Exception
   */
  public function getQueryValues(array $cfg): array
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getQueryValues($cfg);
  }


  /**
   * Creates a simplified array for options from a table model
   *
   * @param [type] $table_name
   * @param string $database
   * @return array
   */
  public function export4Option($table_name, $database = ''): array
  {
    if ($database) {
      $table_name = $database . '.' . $table_name; 
    }

    $structure = $this->modelize($table_name);
    foreach ($structure['keys'] as $k => &$m) {
      unset($m['ref_db'], $m['constraint']);
      if (empty($m['ref_table'])) {
        unset($m['ref_table'], $m['ref_column'], $m['delete'], $m['update']);
      }
    }
    foreach ($structure['fields'] as $k => &$f) {
      unset($f['position']);
      if (!in_array($f['type'], ['decimal', 'float', 'double'])) {
        unset($f['decimals']);
      }
      if (!$this->isNumericType($f['type'])) {
        unset($f['signed']);
      }
      if (empty($f['defaultExpression']) && is_null($f['default'])) {
        unset($f['default'], $f['defaultExpression']);
      }
      if (empty($f['extra'])) {
        unset($f['extra']);
      }
      if (empty($f['key'])) {
        unset($f['key']);
      }
      if (empty($f['virtual'])) {
        unset($f['virtual']);
      }
      if (empty($f['generation'])) {
        unset($f['generation']);
      }
    }

    return $structure;
  }

  public function parseQuery(string $query): ?array
  {
    return $this->language->parseQuery($query);
  }

  /**
   * Analyzes the given database.
   *
   * @param string $database The database's name
   * @return bool True if it succeeded
   * @throws Exception
   */
  public function analyzeDatabase(string $database): bool
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);
    return $this->language->analyzeDatabase($database);
  }


  /**
   * Analyzes the given table.
   *
   * @param string $table The table's name
   * @return bool True if it succeeded
   * @throws Exception
   */
  public function analyzeTable(string $table): bool
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);
    return $this->language->analyzeTable($table);
  }


  /**
   * Throws ans exception if language class method does not exist.
   *
   * @param string $method
   * @throws Exception
   */

}
