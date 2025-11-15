<?php

namespace bbn\Db\Internal;

use bbn\Str;

trait Utilities
{
  /****************************************************************
   *                                                              *
   *                                                              *
   *                           UTILITIES                          *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Return a string with quotes and percent escaped.
   *
   * ```php
   * X::dump($db->escapeValue("My father's job is interesting");
   * // (string) My  father\'s  job  is  interesting
   * ```
   *
   * @param string $value The string to escape.
   * @param string $esc
   * @return string
   *
   */
  public function escapeValue(string $value, $esc = "'"): string
  {
    return str_replace(
      '%', '\\%', $esc === '"' ? Str::escapeDquotes($value) : Str::escapeSquotes($value)
    );
  }


  /**
   * Changes the value of last_insert_id (used by history).
   * @todo this function should be private
   *
   * ```php
   * X::dump($db->setLastInsertId());
   * // (db)
   * ```
   * @param mixed $id The last inserted id
   * @return self
   */
  public function setLastInsertId($id = ''): self
  {
    $this->language->setLastInsertId($id);

    return $this;
  }

  /**
   * Return the last query for this connection.
   *
   * ```php
   * X::dump($db->last());
   * // (string) INSERT INTO `db_example.table_user` (`name`) VALUES (?)
   * ```
   *
   * @return string
   */
  public function last(): ?string
  {
    return $this->language->last();
  }

  /**
   * Return the last inserted ID.
   *
   * ```php
   * X::dump($db->lastId());
   * // (int) 26
   * ```
   *
   * @return mixed
   */
  public function lastId()
  {
    return $this->language->lastId();
  }


  /**
   * Deletes all the queries recorded and returns their (ex) number.
   *
   * ```php
   * X::hdump($ctrl->db->flush()); // 9
   * ```
   * @return int
   */
  public function flush(): int
  {
    return $this->language->flush();
  }

  /**
   * Generate a new casual id based on the max number of characters of id's column structure in the given table
   *
   * ```php
   * X::dump($db->newId('table_users', 18));
   * // (int) 69991701
   * ```
   *
   * @todo Either get rid of th efunction or include the UID types
   * TODO-testing is this needed?
   * @param null|string $table The table's name.
   * @param int         $min
   * @return mixed
   */
  public function newId($table, int $min = 1)
  {
    $tab = $this->modelize($table);
    if (\count($tab['keys']['PRIMARY']['columns']) !== 1) {
      die("Error! Unique numeric primary key doesn't exist");
    }

    if (($id_field = $tab['keys']['PRIMARY']['columns'][0])
        && ($maxlength = $tab['fields'][$id_field]['maxlength'] )
        && ($maxlength > 1)
    ) {
      $max = (10 ** $maxlength) - 1;
      if ($max >= mt_getrandmax()) {
        $max = mt_getrandmax();
      }

      if (($max > $min) && ($table = $this->tfn($table, true))) {
        $i = 0;
        do {
          $id = random_int($min, $max);
          /** @todo */
          /*
          if ( Str::pos($tab['fields'][$id_field]['type'], 'char') !== false ){
            $id = Str::sub(md5('bbn'.$id), 0, random_int(1, 10 ** $maxlength));
          }
          */
          $i++;
        }
        while (($i < 100) && $this->select($table, [$id_field], [$id_field => $id]));
        return $id;
      }
    }

    return null;
  }

  /**
   * Returns a random value fitting the requested column's type
   *
   * @todo This great function has to be done properly
   * TODO is this used?
   * @param $col
   * @param $table
   * @return mixed
   */
  public function randomValue($col, $table)
  {
    $val = null;
    if (($tab = $this->modelize($table)) && isset($tab['fields'][$col])) {
      foreach ($tab['keys'] as $cfg){
        if ($cfg['unique']
            && !empty($cfg['ref_column'])
            && (\count($cfg['columns']) === 1)
            && ($col === $cfg['columns'][0])
        ) {
          return ($num = $this->count($cfg['ref_column'])) ? $this->selectOne(
            [
            'tables' [$cfg['ref_table']],
            'fields' => [$cfg['ref_column']],
            'start' => random_int(0, $num - 1)
            ]
          ) : null;
        }
      }

      switch ($tab['fields'][$col]['type']){
        case 'int':
          if (($tab['fields'][$col]['maxlength'] === 1) && !$tab['fields'][$col]['signed']) {
            $val = microtime(true) % 2 === 0 ? 1 : 0;
          }
          else {
            $max = 10 ** $tab['fields'][$col]['maxlength'] - 1;
            if ($max > mt_getrandmax()) {
              $max = mt_getrandmax();
            }

            if ($tab['fields'][$col]['signed']) {
              $max /= 2;
            }

            $min = $tab['fields'][$col]['signed'] ? -$max : 0;
            $val = random_int($min, $max);
          }
          break;
        case 'float':
        case 'double':
        case 'decimal':
          break;
        case 'varchar':
          break;
        case 'text':
          break;
        case 'date':
          break;
        case 'datetime':
          break;
        case 'timestamp':
          break;
        case 'time':
          break;
        case 'year':
          break;
        case 'blob':
          break;
        case 'binary':
          break;
        case 'varbinary':
          break;
        case 'enum':
          break;
      }
    }

    return $val;
  }


  /** Returns the number of queries 
   * 
   * ```php
   * X::hdump($ctrl->db->countQueries()); // 10
   * ```
   * 
   * @return int
   */
  public function countQueries(): int
  {
    return $this->language->countQueries();
  }


}
