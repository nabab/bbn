<?php

namespace bbn\Db\Languages\Models\Sql;
use bbn\Str;

trait Formatters {


  /**
   * Returns the SQL statement to create a database.
   * @param string $database The name of the database to create.
   * @param string|null $enc The character set to use, if any.
   * @param string|null $collation The collation to use, if any.
   * @return string The SQL statement to create the database, or an empty string if the parameters are invalid.
   */
  public function getCreateDatabase(string $database, ?string $enc = null, ?string $collation = null): string
  {
    if (Str::checkName($database)
      && (empty($enc) || Str::checkName($enc))
      && (empty($collation) || Str::checkName($collation))
    ) {
      return "CREATE DATABASE IF NOT EXISTS ".$this->escape($database).";";
    }

    return '';
  }


  /**
   * Returns the SQL statement to drop a database.
   * @param string $database The name of the database to drop.
   * @return string The SQL statement to drop the database, or an empty string if the name is invalid.
   */
  public function getDropDatabase(string $database): string
  {
    if (Str::checkName($database)) {
      return "DROP DATABASE IF EXISTS ".$this->escape($database).";";
    }

    return '';
  }


  /**
   * Returns the SQL statement to duplicate a database.
   * @param string $oldName The name of the database to duplicate.
   * @param string $newName The name of the new database.
   * @return string The SQL statement to duplicate the database, or an empty string if the names are invalid.
   */
  public function getDuplicateDatabase(string $source, string $target): string
  {
    if (Str::checkName($source) && Str::checkName($target)) {
      $sql = $this->getCreateDatabase($target).PHP_EOL;
      if ($tables = $this->getTables($source)) {
        foreach ($tables as $table) {
          $sql .= $this->getDuplicateTable("$source.$table", "$target.$table", false).PHP_EOL;
        }

        foreach ($tables as $i => $table) {
          $sql .= ($i ? PHP_EOL : '') . "INSERT INTO " . $this->escape("$target.$table") . " SELECT * FROM " . $this->escape("$source.$table") . ";";
        }
      }

      return $sql;
    }

    return '';
  }


  /**
   * Returns the SQL statement to rename a database.
   * This method first duplicates the old database to the new name and then drops the old database.
   * @param string $oldName The current name of the database.
   * @param string $newName The new name for the database.
   * @return string The SQL statement to rename the database, or an empty string if the names are invalid.
   */
  public function getRenameDatabase(string $oldName, string $newName): string
  {
    if ($sql = $this->getDuplicateDatabase($oldName, $newName)) {
      $sql .= PHP_EOL.$this->getDropDatabase($oldName);
      return $sql;
    }

    return '';
  }


  /**
   * Returns the SQL statement to analyze the current database.
   * This method generates an ANALYZE statement for each table in the database.
   * @return string The SQL statement to analyze the database, or an empty string if there are no tables.
   */
  public function getAnalyzeDatabase(): string
  {
    $sql = '';
    if ($tables = $this->getTables()
    ) {
      foreach ($tables as $i => $table) {
        $sql .= $this->getAnalyzeTable($table);
        if (!empty($tables[$i + 1])) {
          $sql .= PHP_EOL;
        }
      }
    }

    return $sql;
  }


  /**
   * Returns the SQL statement to create a table, including keys and constraints if specified.
   * @param string $table
   * @param array|null $cfg
   * @param bool $createKeys
   * @param bool $createConstraints
   * @return string
   */
  public function getCreateTableRaw(
    string $table,
    ?array $cfg = null,
    bool $createKeys = true,
    bool $createConstraints = true
    ): string
  {
    if ($sql = $this->getCreateTable($table, $cfg)) {
      if ($createKeys) {
        $sql .= PHP_EOL.$this->getCreateKeys($table, $cfg);
      }

      if ($createConstraints) {
        $sql .= PHP_EOL.$this->getCreateConstraints($table, $cfg);
      }

      return $sql;
    }

    return '';
  }


  /**
   * Returns the SQL statement to create a table.
   * @param string $table
   * @param array|null $cfg
   * @return string
   */
  public function getCreateTable(string $table, ?array $cfg = null): string
  {
    if (!$cfg) {
      $cfg = $this->modelize($table);
    }

    $st = 'CREATE TABLE '.$this->escape($table).' ('.PHP_EOL;
    $done = false;
    foreach ($cfg['fields'] as $name => $col) {
      if (!$done) {
        $done = true;
      }
      else {
        $st .= ',' . PHP_EOL;
      }

      $st .= $this->getColumnDefinitionStatement($name, $col);
    }

    $st .= PHP_EOL . ');';
    return $st;
  }


  /**
   * Returns the SQL statement to drop a table.
   * @param string $table The name of the table to drop.
   * @param string|null $database The name of the database, if different from the current one.
   * @return string The SQL statement to drop the table, or an empty string if the parameters are invalid.
   */
  public function getDropTable(string $table, ?string $database = null): string
  {
    if (Str::checkName($table)
      && (empty($database) || Str::checkName($database))
    ) {
      $table = $this->tableFullName((!empty($database) ? "$database." : '').$this->tableSimpleName($table), true);
      return "DROP TABLE IF EXISTS $table;";
    }

    return '';
  }


  /**
   * Returns the SQL statement to duplicate a table.
   * This method generates a CREATE TABLE statement for the target table based on the source table.
   * @param string $source The name of the source table.
   * @param string $target The name of the target table.
   * @param bool $withData Whether to include data in the duplication.
   * @return string The SQL statement to duplicate the table, or an empty string if the parameters are invalid.
   */
  public function getDuplicateTable(string $source, string $target, bool $withData = true): string
  {
    if (Str::checkName($source) && Str::checkName($target)) {
      $sql = $this->getCreateTable($source);
      $sql = str_replace(
        'CREATE TABLE '.$this->escape($source),
        'CREATE TABLE ' . $this->escape($target),
        $sql
      );
      if ($withData) {
        $sql .= PHP_EOL . "INSERT INTO " . $this->escape($target) . " SELECT * FROM " . $this->escape($source) . ";";
      }
die(var_dump($sql));
      return $sql;
    }

    return '';
  }


  /**
   * Returns the SQL statement to analyze a table.
   * This method generates an ANALYZE statement for the specified table.
   * @param string $table The name of the table to analyze.
   * @return string The SQL statement to analyze the table, or an empty string if the table name is invalid.
   */
  public function getAnalyzeTable(string $table): string
  {
    if (Str::checkName($table)) {
      return "ANALYZE " . $this->tableSimpleName($table, true) . ";";
    }

    return '';
  }


  /**
   * Returns the SQL statement to create a column.
   * @param string $table The name of the table.
   * @param string $column The name of the column to create.
   * @param array $columnCfg The configuration for the column.
   * @return string The SQL statement to create the column, or an empty string if the parameters are invalid.
   */
  public function getCreateColumn(string $table, string $column, array $columnCfg): string
  {
    if (($table = $this->tableFullName($table, true))
      && Str::checkName($column)
      && ($columnDefinition = $this->getColumnDefinitionStatement($column, $columnCfg))
    ) {
      return "ALTER TABLE $table ADD $columnDefinition;";
    }

    return '';
  }


  /**
   * Returns the SQL statement to drop a column.
   * @param string $table The name of the table.
   * @param string $column The name of the column to drop.
   * @return string The SQL statement to drop the column, or an empty string if the parameters are invalid.
   */
  public function getDropColumn(string $table, string $column): string
  {
    if (($table = $this->tableFullName($table, true))
      && Str::checkName($column)
    ) {
      return "ALTER TABLE $table DROP COLUMN $column;";
    }

    return '';
  }


  public function getDropKey(string $table, string $key): string
  {
    if (($table = $this->tableFullName($table, true))
      && Str::checkName($key)
    ) {
      return 'ALTER TABLE '.$this->escape($table).' DROP KEY '.$this->escape($key).';';
    }

    return '';
  }


  /**
   * Return the SQL statement to drop a constraint.
   * @param string $table
   * @param string $constraint
   * @return string
   */
  public function getDropConstraint(string $table, string $constraint): string
  {
    if (($table = $this->tableFullName($table, true))
      && Str::checkName($constraint)
    ) {
      return 'ALTER TABLE '.$this->escape($table).PHP_EOL.'  DROP FOREIGN KEY '.$this->escape($constraint).';';
    }

    return '';
  }


}