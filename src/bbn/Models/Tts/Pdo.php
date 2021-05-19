<?php
declare(strict_types = 1);

namespace bbn\Models\Tts;

use bbn;
use bbn\Str;
use bbn\X;

trait Pdo
{

  /** @var \PDO The PHP Data Object */
  protected $pdo;

  /** @var bbn\Db The connection object */
  protected $db;

  /** @var array The connection's parameters */
  protected $cfg;

  /** @var array $list_queries */
  protected $list_queries = [];

  /** @var int $max_queries */
  protected $max_queries = 50;

  /** @var int $length_queries */
  protected $length_queries = 60;

  /** @var string $last_query */
  protected $last_query;

  /** @var mixed $hash_contour */
  protected $hash_contour = '__BBN__';

  /**
   * @var array The information that will be accessed by Db2\Query as the current statement's options */
  protected $last_params = ['sequences' => false, 'values' => false];

  /** @var string $last_query */
  protected $last_real_query;

  /** @var array The information that will be accessed by Db2\Query as the current statement's options */
  protected $last_real_params = ['sequences' => false, 'values' => false];

  /** @var mixed $last_prepared */
  protected $last_prepared;

  /** @var array $queries */
  protected $queries = [];


  public function query($statement)
  {
    if ($this->pdo) {
      $args = \func_get_args();
      // If fancy is false we just use the regular PDO query function
      if (!$this->_fancy) {
        return $this->pdo->query(...$args);
      }
  
      // The function can be called directly with func_get_args()
      while ((\count($args) === 1) && \is_array($args[0])){
        $args = $args[0];
      }
  
      if (!empty($args[0]) && \is_string($args[0])) {
        // The first argument is the statement
        $statement = trim(array_shift($args));
  
        // Sending a hash as second argument from helper functions will bind it to the saved statement
        if (count($args)
            && \is_string($args[0])
            && isset($this->queries[$args[0]])
        ) {
          $hash      = is_string($this->queries[$args[0]]) ? $this->queries[$args[0]] : $args[0];
          $hash_sent = array_shift($args);
        }
        else {
          $hash = $this->_make_hash($statement);
        }
  
        $driver_options = [];
        if (count($args)
            && \is_array($args[0])
        ) {
          // Case where drivers are arguments
          if (!array_key_exists(0, $args[0])) {
            $driver_options = array_shift($args);
          }
          // Case where values are in a single argument
          elseif (\count($args) === 1) {
            $args = $args[0];
          }
        }
  
        /** @var array $params Will become the property last_params each time a query is executed */
        $params     = [
          'statement' => $statement,
          'values' => [],
          'last' => microtime(true)
        ];
        $num_values = 0;
        foreach ($args as $i => $arg){
          if (!\is_array($arg)) {
            $params['values'][] = $arg;
            $num_values++;
          }
          elseif (isset($arg[2])) {
            $params['values'][] = $arg[2];
            $num_values++;
          }
        }
  
        if (!isset($this->queries[$hash])) {
          /** @var int $placeholders The number of placeholders in the statement */
          $placeholders = 0;
          if ($sequences = $this->parseQuery($statement)) {
            /* Or looking for question marks */
            $sequences = array_keys($sequences);
            preg_match_all('/(\?)/', $statement, $exp);
            $placeholders = isset($exp[1]) && \is_array($exp[1]) ? \count($exp[1]) : 0;
            while ($sequences[0] === 'OPTIONS'){
              array_shift($sequences);
            }
  
            $params['kind']      = $sequences[0];
            $params['union']     = isset($sequences['UNION']);
            $params['write']     = \in_array($params['kind'], self::$write_kinds, true);
            $params['structure'] = \in_array($params['kind'], self::$structure_kinds, true);
          }
          elseif (($this->engine === 'sqlite') && (strpos($statement, 'PRAGMA') === 0)) {
            $params['kind'] = 'PRAGMA';
          }
          else{
            die(\defined('BBN_IS_DEV') && BBN_IS_DEV ? "Impossible to parse the query $statement" : 'Impossible to parse the query');
          }
  
          // This will add to the queries array
          $this->_add_query(
            $hash,
            $statement,
            $params['kind'],
            $placeholders,
            $driver_options
          );
          if (!empty($hash_sent)) {
            $this->queries[$hash_sent] = $hash;
          }
        }
        // The hash of the hash for retrieving a query based on the helper's config's hash
        elseif (\is_string($this->queries[$hash])) {
          $hash = $this->queries[$hash];
        }
  
        $this->_update_query($hash);
        $q =& $this->queries[$hash];
        /* If the number of values is inferior to the number of placeholders we fill the values with the last given value */
        if (!empty($params['values']) && ($num_values < $q['placeholders'])) {
          $params['values'] = array_merge(
            $params['values'],
            array_fill($num_values, $q['placeholders'] - $num_values, end($params['values']))
          );
          $num_values       = \count($params['values']);
        }
  
        /* The number of values must match the number of placeholders to bind */
        if ($num_values !== $q['placeholders']) {
          $this->error(
            'Incorrect arguments count (your values: '.$num_values.', in the statement: '.$q['placeholders'].")\n\n"
            .$statement."\n\n".'start of values'.print_r($params['values'], 1).'Arguments:'
            .print_r(\func_get_args(), true)
            .print_r($q, true)
          );
          exit;
        }
  
        if ($q['exe_time'] === 0) {
          $time = $q['last'];
        }
  
        // That will always contains the parameters of the last query done
  
        $this->addStatement($q['sql'], $params);
        // If the statement is a structure modifier we need to clear the cache
        if ($q['structure']) {
          $tmp                = $q;
          $this->queries      = [$hash => $tmp];
          $this->list_queries = [[
            'hash' => $hash,
            'last' => $tmp['last']
          ]];
          unset($tmp);
          /** @todo Clear the cache */
        }
  
        try{
          // This is a writing statement, it will execute the statement and return the number of affected rows
          if ($q['write']) {
            // A prepared query already exists for the writing
            /** @var Db2\Query */
            if ($q['prepared']) {
              $r = $q['prepared']->init($params['values'])->execute();
            }
            // If there are no values we can assume the statement doesn't need to be prepared and is just executed
            elseif ($num_values === 0) {
              // Native PDO function which returns the number of affected rows
              $r = $this->exec($q['sql']);
            }
            // Preparing the query
            else{
              // Native PDO function which will use Db2\Query as base class
              /** @var Db2\Query */
              $q['prepared'] = $this->prepare($q['sql'], $q['options']);
              $r             = $q['prepared']->execute();
            }
          }
          // This is a reading statement, it will prepare the statement and return a query object
          else{
            if (!$q['prepared']) {
              // Native PDO function which will use Db2\Query as base class
              $q['prepared'] = $this->prepare($q['sql'], $driver_options);
            }
            else{
              // Returns the same Db2\Query object
              $q['prepared']->init($params['values']);
            }
          }

          if (!empty($time) && ($q['exe_time'] === 0)) {
            $q['exe_time'] = microtime(true) - $time;
          }
        }
        catch (\PDOException $e){
          $this->error($e);
        }

        if ($this->check()) {
          // So if read statement returns the query object
          if (!$q['write']) {
            return $q['prepared'];
          }

          // If it is a write statement returns the number of affected rows
          if ($q['prepared'] && $q['write']) {
            $r = $q['prepared']->rowCount();
          }

          // If it is an insert statement we (try to) set the last inserted ID
          if (($q['kind'] === 'INSERT') && $r) {
            $this->setLastInsertId();
          }

          return $r ?? false;
        }
      }
    }
  }


  /**
   * Clear.
   *
   * ```php
   * $db->clear()
   * // (void)
   * ```
   *
   * @return db
   */
  public function clear(): self
  {
    $this->queries      = [];
    $this->list_queries = [];
    return $this;
  }


  /**
   * Return an object with all the properties of the statement and where it is carried out.
   *
   * ```php
   * X::dump($db->addStatement('SELECT name FROM table_users'));
   * // (db)
   * ```
   *
   * @param string $statement
   * @return db
   */
  public function addStatement($statement, $params): self
  {
    $this->last_real_query  = $statement;
    $this->last_real_params = $params;
    if ($this->_last_enabled) {
      $this->last_query  = $statement;
      $this->last_params = $params;
    }

    return $this;
  }
  

  /*****************************************************************************************************************
   *                                                                                                                *
   *                                                                                                                *
   *                                               ENGINES INTERFACE                                                *
   *                                                                                                                *
   *                                                                                                                *
   *****************************************************************************************************************/


  /**
   * @return array The connection parameters (except the password)
   */
  public function getCfg(): array
  {
    return $this->cfg;
  }


  /**
   * @return self The connection parameters (except the password)
   */
  public function startFancyStuff()
  {
    //$this->pdo->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [Db2\Query::class, [$this]]);
    $this->_fancy = 1;
    return $this;
  }


  /**
   * Actions to do once the PDO object has been created
   *
   * @return void
   */
  public function postCreation()
  {
    return;
  }


  /**
   * Changes the current database to the given one.
   * @param string $db The database name or file
   * @return bool
   */
  public function change(string $db): bool
  {
    if (($this->db->getCurrent() !== $db) && bbn\Str::checkName($db)) {
      $this->db->rawQuery("USE `$db`");
      return true;
    }

    return false;
  }


  /**
   * Returns a database item expression escaped like database, table, column, key names
   *
   * @param string $item The item's name (escaped or not)
   * @return string
   */
  public function escape(string $item): string
  {
    $items = explode('.', str_replace($this->qte, '', $item));
    $r     = [];
    foreach ($items as $m) {
      if (!bbn\Str::checkName($m)) {
        return false;
      }

      $r[] = $this->qte . $m . $this->qte;
    }

    return implode('.', $r);
  }


  /**
   * Returns a table's full name i.e. database.table
   *
   * @param string $table   The table's name (escaped or not)
   * @param bool   $escaped If set to true the returned string will be escaped
   * @return null|string
   */
  public function tableFullName(string $table, bool $escaped = false): ?string
  {
    $bits = explode('.', $table);
    if (\count($bits) === 3) {
      $db    = trim($bits[0], ' ' . $this->qte);
      $table = trim($bits[1]);
    } elseif (\count($bits) === 2) {
      $db    = trim($bits[0], ' ' . $this->qte);
      $table = trim($bits[1], ' ' . $this->qte);
    } else {
      $db    = $this->db->getCurrent();
      $table = trim($bits[0], ' ' . $this->qte);
    }

    if (bbn\Str::checkName($db, $table)) {
      return $escaped ? $this->qte . $db . $this->qte . '.' . $this->qte . $table . $this->qte : $db . '.' . $table;
    }

    return null;
  }


  /**
   * Returns a table's simple name i.e. table
   *
   * @param string $table   The table's name (escaped or not)
   * @param bool   $escaped If set to true the returned string will be escaped
   * @return null|string
   */
  public function tableSimpleName(string $table, bool $escaped = false): ?string
  {
    if ($table = trim($table)) {
      $bits = explode('.', $table);
      switch (\count($bits)) {
        case 1:
          $table = trim($bits[0], ' ' . $this->qte);
          break;
        case 2:
          $table = trim($bits[1], ' ' . $this->qte);
          break;
        case 3:
          $table = trim($bits[1], ' ' . $this->qte);
          break;
      }

      if (bbn\Str::checkName($table)) {
        return $escaped ? $this->qte . $table . $this->qte : $table;
      }
    }

    return null;
  }


  /**
   * Returns a column's full name i.e. table.column
   *
   * @param string      $col     The column's name (escaped or not)
   * @param null|string $table   The table's name (escaped or not)
   * @param bool        $escaped If set to true the returned string will be escaped
   * @return string | false
   */
  public function colFullName(string $col, $table = null, $escaped = false): ?string
  {
    if ($col = trim($col)) {
      $bits = explode('.', $col);
      $ok   = null;
      $col  = trim(array_pop($bits), ' ' . $this->qte);
      if ($table && ($table = $this->tableSimpleName($table))) {
        $ok = 1;
      } elseif (\count($bits)) {
        $table = trim(array_pop($bits), ' ' . $this->qte);
        $ok    = 1;
      }

      if ((null !== $ok) && bbn\Str::checkName($table, $col)) {
        return $escaped ? $this->qte . $table . $this->qte . '.' . $this->qte . $col . $this->qte : $table . '.' . $col;
      }
    }

    return null;
  }


  /**
   * Returns a column's simple name i.e. column
   *
   * @param string $col     The column's name (escaped or not)
   * @param bool   $escaped If set to true the returned string will be escaped
   * @return null|string
   */
  public function colSimpleName(string $col, bool $escaped = false): ?string
  {
    if ($bits = explode('.', $col)) {
      $col = trim(end($bits), ' ' . $this->qte);
      if (bbn\Str::checkName($col)) {
        return $escaped ? $this->qte . $col . $this->qte : $col;
      }
    }

    return null;
  }


  /**
   * Returns true if the given string is the full name of a table ('database.table').
   * @param string $table
   * @return bool
   */
  public function isTableFullName(string $table): bool
  {
    return strpos($table, '.') ? true : false;
  }


  /**
   * Returns true if the given string is the full name of a column ('table.column').
   * @param string $col
   * @return bool
   */
  public function isColFullName(string $col): bool
  {
    return (bool)strpos($col, '.');
  }


  /**
   * Makes a string that will be the id of the request.
   *
   * @return string
   *
   */
  private function _make_hash(): string
  {
    $args = \func_get_args();
    if ((\count($args) === 1) && \is_array($args[0])) {
      $args = $args[0];
    }

    $st = '';
    foreach ($args as $a){
      $st .= \is_array($a) ? serialize($a) : '--'.$a.'--';
    }

    return $this->hash_contour.md5($st).$this->hash_contour;
  }


}
