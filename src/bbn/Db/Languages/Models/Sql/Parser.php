<?php

namespace bbn\Db\Languages\Models\Sql;

use Exception;
use bbn\X;
use PHPSQLParser\PHPSQLParser;

use function count;

trait Parser
{
  /** @var PHPSQLParser|null Parser instance (lazy). */
  private ?PHPSQLParser $_parser = null;

  /**
   * Checks whether the given expression contains an aggregate function.
   *
   * @method isAggregateFunction
   * @param string $f Expression to inspect
   * @return bool
   */
  public static function isAggregateFunction(string $f): bool
  {
    if (isset(static::$aggr_functions) && is_array(static::$aggr_functions)) {
      foreach (static::$aggr_functions as $a) {
        if (preg_match('/' . $a . '\\s*\\(/i', $f)) {
          return true;
        }
      }
    }

    return false;
  }

  /**
   * Parses an SQL statement and returns the PHPSQLParser array representation.
   *
   * Important: for some engine-specific statements (eg. sqlite PRAGMA),
   * parsing can legitimately fail and this method returns null.
   *
   * @method parseQuery
   * @param string $statement SQL statement to parse
   * @return array|null
   */
  public function parseQuery(string $statement): ?array
  {
    if ($this->_parser === null) {
      $this->_parser = new PHPSQLParser();
    }

    $done = false;
    try {
      $r    = $this->_parser->parse($statement);
      $done = true;
    }
    catch (Exception $e){
      $this->log('Error while parsing the query ' . $statement);
    }

    if ($done) {
      if (!$r || !count($r)) {
        if (($this->getEngine() === 'sqlite')
          && str_starts_with($statement, 'PRAGMA')
        ) {
          return null;
        }

        $this->log('Impossible to parse the query ' . $statement);
        return null;
      }

      if (isset($r['BRACKET']) && (count($r) === 1)) {
        // Some bracketed statements are not reliably parsed; keep behavior.
        return null;
      }

      return $r;
    }

    return null;
  }
}
