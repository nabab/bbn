<?php

namespace bbn\Db\Languages\Models\Sql;
use bbn\Str;
use bbn\X;
use Exception;
use bbn\Db\Languages\Models\Sql\Formatters;

trait Commands {
  use Formatters;

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
          $this->rawQuery($sql);
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
          $this->query($sql);
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
   * @return bool
   * @throws Exception
   */
  public function duplicateDatabase(string $source, string $target): bool
  {
    if ($this->check()) {
      if (!Str::checkName($source) || !Str::checkName($target)) {
        throw new Exception(X::_("Wrong database name '%s' or '%s'", $source, $target));
      }

      if ($sql = $this->getDuplicateDatabase($source, $target)) {
        try {
          $this->disableKeys();
          $this->query($sql);
          $this->enableKeys();
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
      return (bool)$this->rawQuery($sql);
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
      return (bool)$this->rawQuery($sql);
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
      $this->rawQuery($sql);
    }

    return !$this->tableExists($table, $database);

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
      return (bool)$this->rawQuery($sql);
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
      return (bool)$this->rawQuery($sql);
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
      return (bool)$this->rawQuery($sql);
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
    if ($str = $this->getCreateKeys($table,  $cfg)) {
      return (bool)$this->rawQuery($str);
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
      return (bool)$this->rawQuery($sql);
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
    if ($str = $this->getCreateConstraints($table,  $cfg)) {
      return (bool)$this->rawQuery($str);
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
      return (bool)$this->rawQuery($sql);
    }

    return false;
  }


}