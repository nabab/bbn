<?php

namespace bbn\Db\Languages\Models\Sql;
use bbn\Str;
use bbn\X;
use bbn\Db;
use Exception;
use PDO;
use bbn\Db\Languages\Models\Sql\Formatters;

trait Commands {
  use Formatters;


  protected function emulatePreparesAndQuery(string|array $sql)
  {
    $att = true;
    $emode = null;
    $res = false;
    try {
      $att = $this->pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
    }
    catch (Exception $e) {}

    try {
      $emode = $this->pdo->getAttribute(PDO::ATTR_ERRMODE);
    }
    catch (Exception $e) {}

    if (empty($att)) {
      $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    }

    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    if (is_string($sql)) {
      $res = $this->rawQuery($sql);
    }
    else {
      $res = true;
      foreach ($sql as $s) {
        if (!$this->rawQuery($s)) {
          $res = false;
          break;
        }
      }
    }

    if (empty($att)) {
      $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    if (!is_null($emode)) {
      $this->pdo->setAttribute(PDO::ATTR_ERRMODE, $emode);
    }

    return $res;
  }


  /**
   * Returns the list of charsets available in the database.
   *
   * @return array|null
   */
  public function charsets(): ?array
  {
    if (($sql = $this->getCharsets())
      && ($list = $this->getRows($sql))
    ) {
      $list = array_map(fn($a) => $a['charset'], $list);
      sort($list);
      return $list;
    }

    return null;
  }


  /**
   * Returns the list of collations available in the database.
   *
   * @return array|null
   */
  public function collations(): ?array
  {
    if (($sql = $this->getCollations())
      && ($list = $this->getRows($sql))
    ) {
      $list = array_map(fn($a) => $a['collation'], $list);
      sort($list);
      return $list;
    }

    return null;
  }


  /**
   * Creates a database
   *
   * @param string $database
   * @param string|null $enc
   * @param string|null $collation
   * @return bool
   */
  public function createDatabase(string $database, ?string $enc = null, ?string $collation = null): bool
  {
    if ($sql = $this->getCreateDatabase($database, $enc, $collation)) {
      return (bool)$this->rawQuery($sql);
    }

    return false;
  }


  /**
   * Drops the given database
   *
   * @param string $database
   * @return bool
   * @throws Exception
   */
  public function dropDatabase(string $database): bool
  {
    if ($this->check()) {
      if (!Str::checkName($database)) {
        throw new Exception(X::_("Wrong database name '%s'", $database));
      }

      if ($database === $this->getCurrent()) {
        throw new Exception(X::_('Cannot drop the currently open database!'));
      }

      if ($sql = $this->getDropDatabase($database)) {
        try {
          return (bool)$this->emulatePreparesAndQuery($sql);
        }
        catch (Exception $e) {
          return false;
        }
      }
    }

    return $this->check();
  }


  /**
   * Renames the given database
   *
   * @param string $oldName
   * @param string $newName
   * @return bool
   * @throws Exception
   */
  public function renameDatabase(string $oldName, string $newName): bool
  {
    if ($this->check()) {
      if (!Str::checkName($oldName) || !Str::checkName($newName)) {
        throw new Exception(X::_("Wrong database name '%s' or '%s'", $oldName, $newName));
      }

      if ($oldName === $this->getCurrent()) {
        throw new Exception(X::_('Cannot rename the currently open database!'));
      }

      if ($sql = $this->getRenameDatabase($oldName, $newName)) {
        try {
          return (bool)$this->emulatePreparesAndQuery($sql);
        }
        catch (Exception $e) {
          return false;
        }
      }
    }

    return $this->check();
  }


  /**
   * Duplicates the given database
   *
   * @param string $source
   * @param string $target
   * @param bool $withData
   * @return bool
   * @throws Exception
   */
  public function duplicateDatabase(string $source, string $target, bool $withData): bool
  {
    if ($this->check()) {
      if (!Str::checkName($source) || !Str::checkName($target)) {
        throw new Exception(X::_("Wrong database name '%s' or '%s'", $source, $target));
      }

      if ($sql = $this->getDuplicateDatabase($source, $target, $withData)) {
        try {
          $this->disableKeys();
          $res = (bool)$this->emulatePreparesAndQuery($sql);
          $this->enableKeys();
          return $res;
        }
        catch (Exception $e) {
          return false;
        }
      }
    }

    return $this->check();
  }


  /**
   * Analyzes the given database.
   *
   * @param string $database
   * @return bool
   */
  public function analyzeDatabase(string $database): bool
  {
    if ($this->check()
      && ($sql = $this->getAnalyzeDatabase($database))
    ) {
      return (bool)$this->emulatePreparesAndQuery($sql);
    }

    return false;
  }


  /**
   * Returns the charset of the given database
   *
   * @param string $database
   * @return string|null
   */
  public function getDatabaseCharset(string $database): ?string
  {
    if ($this->check()
      && ($sql = $this->getCharsetDatabase($database))
      && ($r = $this->getRow($sql))
    ) {
      return $r['charset'] ?? $r['encoding'] ?? null;
    }

    return null;
  }


  /**
   * Returns the collation of the given database
   *
   * @param string $database
   * @return string|null
   */
  public function getDatabaseCollation(string $database): ?string
  {
    if ($this->check()
      && method_exists($this, 'getCollationDatabase')
      && ($sql = $this->getCollationDatabase($database))
      && ($r = $this->getRow($sql))
    ) {
      return $r['collation'] ?? null;
    }

    return null;
  }


  /**
   * @param string $table
   * @param array|null $cfg
   * @param bool $createKeys
   * @param bool $createConstraints
   * @return string
   */
  public function createTable(
    string $table,
    ?array $cfg = null,
    bool $createKeys = true,
    bool $createConstraints = true
  ): bool
  {
    if ($sql = $this->getCreateTableRaw($table, $cfg, $createKeys, $createConstraints)) {
      return (bool)$this->emulatePreparesAndQuery($sql);
    }

    return false;
  }


  public function renameTable(string $table, string $newName): bool
  {
    if ($this->check()) {
      if (!Str::checkName($table) || !Str::checkName($newName)) {
        throw new Exception(X::_("Wrong table name '%s' or '%s'", $table, $newName));
      }

      if ($sql = $this->getRenameTable($table, $newName)) {
        return (bool)$this->emulatePreparesAndQuery($sql);
      }
    }

    return false;
  }


  /**
   * Drops a table
   *
   * @param string $table
   * @param string|null $database
   * @return boolean
   */
  public function dropTable(string $table, ?string $database = null): bool
  {
    $tn = (!empty($database) ? $database . '.' : '').$table;
    $tfn = $this->tableFullName($tn, true);
    if (!$tfn) {
      throw new Exception(X::_("Invalid table name to drop '%s'", $tn));
    }

    if (!$this->tableExists($table, $database)) {
      throw new Exception(X::_("The table %s does not exist", $tfn));
    }

    if ($sql = $this->getDropTable($table, $database)) {
      $this->emulatePreparesAndQuery($sql);
    }

    return !$this->tableExists($table, $database);

  }


  /**
   * Duplicates the given table
   *
   * @param string $source
   * @param string $target
   * @param bool $withData
   * @return bool
   * @throws Exception
   */
  public function duplicateTable(string $source, string $target, bool $withData): bool
  {
    if ($this->check()) {
      if (!Str::checkName($source) || !Str::checkName($target)) {
        throw new Exception(X::_("Wrong table name '%s' or '%s'", $source, $target));
      }

      if ($sql = $this->getDuplicateTable($source, $target, $withData)) {
        try {
          $this->disableKeys();
          $res = (bool)$this->emulatePreparesAndQuery($sql);
          $this->enableKeys();
          return $res;
        }
        catch (Exception $e) {
          return false;
        }
      }
    }

    return $this->check();
  }


  /**
   * Copies the given table to the target database.
   *
   * @param string $table The source table name
   * @param Db $target The target database connection
   * @param bool $withData If true, the data will be copied too
   * @return bool True if it succeeded
   */
  public function copyTableTo(string $table, Db $target, bool $withData): bool
  {
    ;
    if ($target->check()
      && ($m = $this->modelize($table, true))
      && ($m = $this->convert($m, $target->getEngine()))
      && !$target->tableExists($table)
      && $target->createTable($table, $m, true, true)
    ) {
      if ($withData) {
        $columns = array_map(
          fn($c) => $this->escape($c),
          array_keys(
            array_filter(
              $this->getColumns($table),
              fn($c) => empty($c['virtual'])
            )
          )
        );
        if ($columns) {
          $q = $this->query("SELECT " . implode(", ", $columns) . " FROM " . $this->escape($table));
          $target->disableKeys();
          while ($row = $q->getRow()) {
            $target->insert($table, $row);
          }

          $target->enableKeys();
        }
      }

      return true;
    }

    return false;
  }


  /**
   * Analyzes the given table.
   *
   * @param string $table
   * @param string|null $database
   * @return bool
   */
  public function analyzeTable(string $table, ?string $database = null): bool
  {
    if ($this->check()
      && ($sql = $this->getAnalyzeTable($table, $database))
    ) {
      return (bool)$this->emulatePreparesAndQuery($sql);
    }

    return false;
  }


  /**
   * Returns the charset of the given table
   *
   * @param string $table
   * @return string|null
   */
  public function getTableCharset(string $table): ?string
  {
    if ($this->check()
      && method_exists($this, 'getCharsetTable')
      && ($sql = $this->getCharsetTable($table))
      && ($r = $this->getRow($sql))
    ) {
      return $r['charset'] ?? $r['encoding'] ?? null;
    }

    return null;
  }


  /**
   * Returns the collation of the given table
   *
   * @param string $table
   * @return string|null
   */
  public function getTableCollation(string $table): ?string
  {
    if ($this->check()
      && method_exists($this, 'getCollationTable')
      && ($sql = $this->getCollationTable($table))
      && ($r = $this->getRow($sql))
    ) {
      return $r['collation'] ?? null;
    }

    return null;
  }


  /**
   * Creates the given column for the given table.
   *
   * @param string $table
   * @param string $column
   * @param array $columnCfg
   * @return bool
   */
  public function createColumn(string $table, string $column, array $columnCfg): bool
  {
    if ($sql = $this->getCreateColumn($table, $column, $columnCfg)) {
      return (bool)$this->emulatePreparesAndQuery($sql);
    }

    return false;
  }


  /**
   * @param string $table
   * @param string $column
   * @return bool
   */
  public function dropColumn(string $table, string $column): bool
  {
    if ($sql = $this->getDropColumn($table, $column)) {
      return (bool)$this->emulatePreparesAndQuery($sql);
    }

    return false;
  }


  /**
   * @param string $table
   * @param array|null $cfg
   * @return bool
   */
  public function createKeys(string $table, ?array $cfg = null): bool
  {
    if ($sql = $this->getCreateKeys($table,  $cfg)) {
      return (bool)$this->emulatePreparesAndQuery($sql);
    }

    return false;
  }


  /**
   * @param string $table
   * @param string $constraint
   * @return bool
   */
  public function dropKey(string $table, string $key): bool
  {
    if ($sql = $this->getDropKey($table, $key)) {
      return (bool)$this->emulatePreparesAndQuery($sql);
    }

    return false;
  }


  /**
   * @param string $table
   * @param array|null $cfg
   * @return bool
   */
  public function createConstraints(string $table, ?array $cfg = null): bool
  {
    if ($sql = $this->getCreateConstraints($table,  $cfg)) {
      return (bool)$this->emulatePreparesAndQuery($sql);
    }

    return false;
  }


  /**
   * @param string $table
   * @param string $constraint
   * @return bool
   */
  public function dropConstraint(string $table, string $constraint): bool
  {
    if ($sql = $this->getDropConstraint($table, $constraint)) {
      return (bool)$this->emulatePreparesAndQuery($sql);
    }

    return false;
  }


}