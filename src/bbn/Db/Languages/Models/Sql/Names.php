<?php

namespace bbn\Db\Languages\Models\Sql;

use Exception;
use bbn\Str;
use bbn\X;

trait Names
{
  /**
   * Escapes an identifier or dotted identifier (db.table.column) using the dialect quote.
   *
   * @method escape
   * @param string $item Identifier (escaped or not)
   * @return string
   * @throws Exception
   */
  public function escape(string $item): string
  {
    $items = explode('.', str_replace($this->qte, '', $item));
    $r     = [];

    foreach ($items as $m) {
      if (!Str::checkName($m)) {
        throw new Exception(X::_("Illegal name %s for the column", $m));
      }

      $r[] = $this->qte . $m . $this->qte;
    }

    return implode('.', $r);
  }

  /**
   * Returns a table full name (db.table) based on current database if needed.
   *
   * @method tableFullName
   * @param string $table Table name (escaped or not)
   * @param bool $escaped If true, escapes identifiers
   * @return string|null
   */
  public function tableFullName(string $table, bool $escaped = false): ?string
  {
    $bits = explode('.', $table);

    if (count($bits) === 3) {
      $db    = trim($bits[0], ' ' . $this->qte);
      $table = trim($bits[1]);
    } elseif (count($bits) === 2) {
      $db    = trim($bits[0], ' ' . $this->qte);
      $table = trim($bits[1], ' ' . $this->qte);
    } else {
      $db    = $this->getCurrent();
      $table = trim($bits[0], ' ' . $this->qte);
    }

    if (Str::checkName($db) && Str::checkName($table)) {
      return $escaped ? $this->escape("$db.$table") : "$db.$table";
    }

    return null;
  }

  /**
   * Returns a table simple name (table).
   *
   * @method tableSimpleName
   * @param string $table Table name (escaped or not)
   * @param bool $escaped If true, escapes identifier
   * @return string|null
   */
  public function tableSimpleName(string $table, bool $escaped = false): ?string
  {
    if ($table = trim($table)) {
      $bits = explode('.', $table);
      switch (count($bits)) {
        case 1:
          $table = trim($bits[0], ' ' . $this->qte);
          break;
        case 2:
        case 3:
          $table = trim($bits[1], ' ' . $this->qte);
          break;
      }

      if (Str::checkName($table)) {
        return $escaped ? $this->escape($table) : $table;
      }
    }

    return null;
  }

  /**
   * Returns a column full name (table.column).
   *
   * @method colFullName
   * @param string $col Column (escaped or not)
   * @param string|null $table Table (escaped or not)
   * @param bool $escaped If true, escapes identifiers
   * @return string|null
   */
  public function colFullName(string $col, ?string $table = null, bool $escaped = false): ?string
  {
    if ($col = trim($col)) {
      $bits = explode('.', $col);
      $ok   = null;
      $col  = trim(array_pop($bits), ' ' . $this->qte);
      if ($table && ($table = $this->tableSimpleName($table))) {
        $ok = 1;
      } elseif (count($bits)) {
        $table = trim(array_pop($bits), ' ' . $this->qte);
        $ok    = 1;
      }

      if ((null !== $ok) && Str::checkName($table) && Str::checkName($col)) {
        return $escaped ? $this->escape("$table.$col") : "$table.$col";
      }
    }

    return null;
  }

  /**
   * Returns a column simple name (column).
   *
   * @method colSimpleName
   * @param string $col Column (escaped or not)
   * @param bool $escaped If true, escapes identifier
   * @return string|null
   */
  public function colSimpleName(string $col, bool $escaped = false): ?string
  {
    if ($bits = explode('.', $col)) {
      $col = trim(end($bits), ' ' . $this->qte);
      if ($col && Str::checkName($col)) {
        return $escaped ? $this->escape($col) : $col;
      }
    }

    return null;
  }

  /**
   * True if string contains a dot (db.table).
   *
   * @method isTableFullName
   * @param string $table
   * @return bool
   */
  public function isTableFullName(string $table): bool
  {
    return (bool)Str::pos($table, '.');
  }

  /**
   * True if string contains a dot (table.column).
   *
   * @method isColFullName
   * @param string $col
   * @return bool
   */
  public function isColFullName(string $col): bool
  {
    return (bool)Str::pos($col, '.');
  }
}
