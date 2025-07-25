<?php

namespace bbn\Db\Internal;

use Exception;

trait Engine
{
  /****************************************************************
   *                                                              *
   *                                                              *
   *                       ENGINE INTERFACE                       *
   *                                                              *
   *                                                              *
   ****************************************************************/

  /**
   * Actions to do once the PDO object has been created
   *
   * ```php
   * X::hdump($ctrl->db->postCreation()); // null 
   * ```
   * 
   * @return void
   */
  public function postCreation()
  {
    if ($this->language && !$this->engine) {
      $this->language->postCreation();
    }
  }


  /**
   * Changes the database used to the given one.
   *
   * ```php
   * $db = new Db();
   * X::dump($db->change('db_example'));
   * // (db)
   * ```
   *
   * @param string $db The database's name
   * @return self
   */
  public function change(string $db): self
  {
    $this->language->change($db);

    return $this;
  }


  /**
   * Escapes names with the appropriate quotes (db, tables, columns, keys...)
   *
   * ```php
   * X::dump($db->escape("table_users"));
   * // (string) `table_users`
   * ```
   *
   * @param string $item The name to escape.
   * @return string
   */
  public function escape(string $item): string
  {
    return $this->language->escape($item);
  }


  /**
   * Return table's full name.
   *
   * ```php
   * X::dump($db->tableFullName("table_users"));
   * // (String) db_example.table_users
   * X::dump($db->tableFullName("table_users", true));
   * // (String) `db_example`.`table_users`
   * ```
   *
   * @param string $table   The table's name (escaped or not).
   * @param bool   $escaped If set to true the returned string will be escaped.
   * @return string | false
   */
  public function tableFullName(string $table, bool $escaped = false): ?string
  {
    return $this->language->tableFullName($table, $escaped);
  }


  /**
   * Returns true if the given string is the full name of a table ('database.table').
   *
   * ```php
   * X::hdump($ctrl->db->isTableFullName("table_users")); // true or false
   * ```
   * 
   * @param string $table The table's name
   * @return bool
   */
  public function isTableFullName(string $table): bool
  {
    return $this->language->isTableFullName($table);
  }


  /**
   * Returns true if the given string is the full name of a column ('table.column').
   *
   * ```php
   * X::hdump($ctrl->db->isColFullName("column_users")); // true or false
   * ```
   * 
   * @param string $col
   * @return bool
   */
  public function isColFullName(string $col): bool
  {
    return $this->language->isColFullName($col);
  }


  /**
   * Return table's simple name.
   *
   * ```php
   * X::dump($db->tableSimpleName("example_db.table_users"));
   * // (string) table_users
   * X::dump($db->tableSimpleName("example.table_users", true));
   * // (string) `table_users`
   * ```
   *
   * @param string $table   The table's name (escaped or not)
   * @param bool   $escaped If set to true the returned string will be escaped
   * @return string | false
   */
  public function tableSimpleName(string $table, bool $escaped = false): ?string
  {
    return $this->language->tableSimpleName($table, $escaped);
  }


  /**
   * Return column's full name.
   *
   * ```php
   * X::dump($db->colFullName("name", "table_users"));
   * // (string)  table_users.name Hello Ohohoho!!
   * X::dump($db->colFullName("name", "table_users", true));
   * // (string) \`table_users\`.\`name\`
   * ```
   *
   * @param string $col The column's name (escaped or not)
   * @param string|null $table The table's name (escaped or not)
   * @param bool $escaped If set to true the returned string will be escaped
   * @return string | false
   */
  public function colFullName(string $col, ?string $table = null, bool $escaped = false): ?string
  {
    return $this->language->colFullName($col, $table, $escaped);
  }


  /**
   * Return the column's simple name.
   *
   * ```php
   * X::dump($db->colSimpleName("table_users.name"));
   * // (string) name
   * X::dump($db->colSimpleName("table_users.name", true));
   * // (string) `name`
   * ```
   *
   * @param string $col     The column's complete name (escaped or not).
   * @param bool   $escaped If set to true the returned string will be escaped.
   * @return string | false
   */
  public function colSimpleName(string $col, bool $escaped = false): ?string
  {
    return $this->language->colSimpleName($col, $escaped);
  }


  /**
   * Disables foreign keys constraints.
   *
   * ```php
   * X::dump($db->disableKeys());
   * // (self)
   * ```
   *
   * @return self
   */
  public function disableKeys(): self
  {
    $this->language->disableKeys();
    return $this;
  }


  /**
   * Enables foreign keys constraints.
   *
   * ```php
   * X::dump($db->enableKeys());
   * // (db)
   * ```
   *
   * @return self
   */
  public function enableKeys(): self
  {
    $this->language->enableKeys();
    return $this;
  }


  /**
   * Return databases' names as an array.
   *
   * ```php
   * X::dump($db->getDatabases());
   * /*
   * (array)[
   *      "db_customers",
   *      "db_clients",
   *      "db_empty",
   *      "db_example",
   *      "db_mail"
   *      ]
   * ```
   * @return null|array
   */
  public function getDatabases(): ?array
  {
    return $this->language->getDatabases();
  }


  /**
   * Return tables' names of a database as an array.
   *
   * ```php
   * X::dump($db->getTables('db_example'));
   * /*
   * (array) [
   *        "clients",
   *        "columns",
   *        "cron",
   *        "journal",
   *        "dbs",
   *        "examples",
   *        "history",
   *        "hosts",
   *        "keys",
   *        "mails",
   *        "medias",
   *        "notes",
   *        "medias",
   *        "versions"
   *        ]
   * ```
   *
   * @param string $database Database name
   * @return null|array
   */
  public function getTables(string $database = ''): ?array
  {
    return $this->language->getTables($database);
  }


  /**
   * Return columns' structure of a table as an array indexed with the fields names.
   *
   * * ```php
   * X::dump($db->getColumns('table_users'));
   * /* (array)[
   *            "id" => [
   *              "position" => 1,
   *              "null" => 0,
   *              "key" => "PRI",
   *              "default" => null,
   *              "extra" => "auto_increment",
   *              "signed" => 0,
   *              "maxlength" => "8",
   *              "type" => "int",
   *            ],
   *           "name" => [
   *              "position" => 2,
   *              "null" => 0,
   *              "key" => null,
   *              "default" => null,
   *              "extra" => "",
   *              "signed" => 0,
   *              "maxlength" => "30",
   *              "type" => "varchar",
   *            ],
   *            "surname" => [
   *              "position" => 3,
   *              "null" => 0,
   *              "key" => null,
   *              "default" => null,
   *              "extra" => "",
   *              "signed" => 0,
   *              "maxlength" => "30",
   *              "type" => "varchar",
   *            ],
   *            "address" => [
   *              "position" => 4,
   *              "null" => 0,
   *              "key" => "UNI",
   *              "default" => null,
   *              "extra" => "",
   *              "signed" => 0,
   *              "maxlength" => "30",
   *              "type" => "varchar",
   *            ],
   *          ]
   * ```
   *
   * @param string $table The table's name
   * @return null|array
   */
  public function getColumns(string $table): ?array
  {
    return $this->language->getColumns($table);
  }


  /**
   * Return the table's keys as an array indexed with the fields names.
   *
   * ```php
   * X::dump($db->getKeys("table_users"));
   * /*
   * (array)[
   *      "keys" => [
   *        "PRIMARY" => [
   *          "columns" => [
   *            "id",
   *          ],
   *          "ref_db" => null,
   *          "ref_table" => null,
   *          "ref_column" => null,
   *          "unique" => 1,
   *        ],
   *        "number" => [
   *          "columns" => [
   *            "number",
   *          ],
   *          "ref_db" => null,
   *          "ref_table" => null,
   *          "ref_column" => null,
   *         "unique" => 1,
   *        ],
   *      ],
   *      "cols" => [
   *        "id" => [
   *          "PRIMARY",
   *        ],
   *        "number" => [
   *          "number",
   *        ],
   *      ],
   * ]
   * ```
   *
   * @param string $table The table's name
   * @return null|array
   */
  public function getKeys(string $table): ?array
  {
    return $this->language->getKeys($table);
  }


  /**
   * Returns a string with the conditions for any filter clause.
   *
   * @param array $conditions
   * @param array $cfg
   * @param bool $is_having
   * @param int $indent
   * @return string
   */
  public function getConditions(array $conditions, array $cfg = [], bool $is_having = false, int $indent = 0): string
  {
    return $this->language->getConditions($conditions, $cfg, $is_having, $indent);
  }


  /**
   * Return SQL code for row(s) SELECT.
   *
   * ```php
   * X::dump($db->getSelect(['tables' => ['users'],'fields' => ['id', 'name']]));
   * /*
   * (string)
   *   SELECT
   *    `table_users`.`name`,
   *    `table_users`.`surname`
   * ```
   *
   * @param array $cfg The configuration array
   * @return string
   * @throws Exception
   */
  public function getSelect(array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getSelect($cfg);
  }

  public function getUnion(array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getUnion($cfg);
  }


  /**
   * Returns the SQL code for an INSERT statement.
   *
   * ```php
   * X::dump($db->getInsert([
   *   'tables' => ['table_users'],
   *   'fields' => ['name','surname']
   * ]));
   * /*
   * (string)
   *  INSERT INTO `db_example`.`table_users` (
   *              `name`, `surname`)
   *              VALUES (?, ?)
   * ```
   *
   * @param array $cfg The configuration array
   * @return string
   * @throws Exception
   */
  public function getInsert(array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    $cfg['kind'] = 'INSERT';
    return $this->language->getInsert($this->processCfg($cfg));
  }


  /**
   * Returns the SQL code for an UPDATE statement.
   *
   * ```php
   * X::dump($db->getUpdate([
   *   'tables' => ['table_users'],
   *   'fields' => ['name','surname']
   * ]));
   * /*
   * (string)
   *    UPDATE `db_example`.`table_users`
   *    SET `table_users`.`name` = ?,
   *        `table_users`.`surname` = ?
   * ```
   *
   * @param array $cfg The configuration array
   * @return string
   * @throws Exception
   */
  public function getUpdate(array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    $cfg['kind'] = 'UPDATE';
    return $this->language->getUpdate($this->processCfg($cfg));
  }


  /**
   * Returns the SQL code for a DELETE statement.
   *
   * ```php
   * X::dump($db->getDelete(['tables' => ['table_users']]));
   * // (string) DELETE FROM `db_example`.`table_users` * WHERE 1 AND `table_users`.`id` = ?
   * ```
   *
   * @param array $cfg The configuration array
   * @return string
   * @throws Exception
   */
  public function getDelete(array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    $cfg['kind'] = 'DELETE';
    return $this->language->getDelete($this->processCfg($cfg));
  }


  /**
   * Returns a string with the JOIN part of the query if there is, empty otherwise
   *
   * @param array $cfg
   * @return string
   * @throws Exception
   */
  public function getJoin(array $cfg, array|null $join = null): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getJoin($cfg, $join);
  }


  /**
   * Return a string with 'where' conditions.
   *
   * ```php
   * X::dump($db->getWhere(['id' => 9], 'table_users'));
   * // (string) WHERE 1 AND `table_users`.`id` = ?
   * ```
   *
   * @param array $cfg
   * @return string
   * @throws Exception
   */
  public function getWhere(array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getWhere($cfg);
  }


  /**
   * Returns a string with the GROUP BY part of the query if there is, empty otherwise
   *
   * @param array $cfg
   * @return string
   * @throws Exception
   */
  public function getGroupBy(array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getGroupBy($cfg);
  }


  /**
   * Returns a string with the HAVING part of the query if there is, empty otherwise
   *
   * @param array $cfg
   * @return string
   * @throws Exception
   */
  public function getHaving(array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getHaving($cfg);
  }


  /**
   * Get a string starting with ORDER BY with corresponding parameters to $order.
   *
   * ```php
   * X::dump($db->getOrder(['name' => 'DESC' ],'table_users'));
   * // (string) ORDER BY `name` DESC
   * ```
   *
   * @param array $cfg
   * @return string
   * @throws Exception
   */
  public function getOrder(array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getOrder($cfg);
  }


  /**
   * Get a string starting with LIMIT with corresponding parameters to $limit.
   *
   * ```php
   * X::dump($db->getLimit(['limit' => 3, 'start'  => 1]));
   * // (string) LIMIT 1, 3
   * ```
   *
   * @param array $cfg
   * @return string
   * @throws Exception
   */
  public function getLimit(array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getLimit($cfg);
  }


  /**
   * Return SQL code for table creation.
   *
   * ```php
   * X::dump($db->getCreate("table_users"));
   * /*
   * (string)
   *    CREATE TABLE `table_users` (
   *      `userid` int(11) NOT NULL,
   *      `userdataid` int(11) NOT NULL,
   *      `info` char(200) DEFAULT NULL,
   *       PRIMARY KEY (`userid`,`userdataid`),
   *       KEY `table_users_userId_userdataId_info` (`userid`,`userdataid`,`info`)
   *    ) ENGINE=InnoDB DEFAULT CHARSET=utf8
   *
   * ```
   * 
   * @param string $table The table's name
   * @return string | false
   * @throws Exception
   */
  public function getCreate(string $table, array|null $model = null): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getCreate($table, $model);
  }


  /**
   * @param string $table
   * @param array|null $cfg
   * @return string
   * @throws Exception
   */
  public function getCreateTable(string $table, ?array $cfg = null): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);
    return $this->language->getCreateTable($table, $cfg);
  }


  public function getCreateTableRaw(
    string $table,
    ?array $cfg = null,
    $createKeys = true,
    $createConstraints = true
    ): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);
    return $this->language->getCreateTableRaw($table, $cfg, $createKeys, $createConstraints);
  }


  /**
   * @param string $table
   * @param array|null $model
   * @return string
   * @throws Exception
   */
  public function getCreateKeys(string $table, array|null $model = null): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getCreateKeys($table, $model);
  }


  /**
   * @param string $table
   * @param array|null $model
   * @return string
   * @throws Exception
   */
  public function getCreateConstraints(string $table, array|null $model = null): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getCreateConstraints($table, $model);
  }

  /**
   * Creates an index on one or more column(s) of the table
   *
   * @param string $table
   * @param string|array $column
   * @param bool $unique
   * @param null|int $length
   * @return bool
   * @throws Exception
   * @todo return data
   *
   * ```php
   * X::dump($db->createIndex('table_users','id_group'));
   * // (bool) true
   * ```
   *
   */
  public function createIndex(string $table, $column, bool $unique = false, ?int $length = null): bool
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->createIndex($table, $column, $unique, $length);
  }


  /**
   * Deletes index on a column of the table.
   *
   * @param string $table The table's name.
   * @param string $key The key's name.
   * @return bool
   * @throws Exception
   * @todo far vedere a thomas perchè non funziona/return data
   *
   * ```php
   * X::dump($db->deleteIndex('table_users','id_group'));
   * // (bool) true
   * ```
   *
   */
  public function deleteIndex(string $table, string $key): bool
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->deleteIndex($table, $key);
  }


  /**
   * @param string $table
   * @param array $cfg
   * @return string
   * @throws Exception
   */
  public function getAlterTable(string $table, array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getAlterTable($table, $cfg);
  }

  public function createColumn(string $table, string $col, array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->createColumn($table, $col, $cfg);
  }


  /**
   * @param string $table
   * @param array $cfg
   * @return string
   * @throws Exception
   */
  public function getAlterColumn(string $table, array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getAlterColumn($table, $cfg);
  }


  /**
   * @param string $table
   * @param array $cfg
   * @return string
   * @throws Exception
   */
  public function getAlterKey(string $table, array $cfg): string
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getAlterKey($table, $cfg);
  }


  /**
   * @param string $table
   * @param array $cfg
   * @return int
   * @throws Exception
   */
  public function alter(string $table, array $cfg): int
  {
    if (method_exists($this->language, 'alter')) {
      return $this->language->alter($table, $cfg);
    }

    if ($st = $this->language->getAlterTable($table, $cfg)) {
      return (int)$this->language->rawQuery($st);
    }

    return 0;
  }


  /**
   * Moves the given column's position within a table.
   *
   * @param string $table
   * @param string $column
   * @param array $cfg
   * @param string|null $after
   * @return integer
   */
  public function moveColumn(string $table, string $column, array $cfg, string|null $after = null): int
  {
    $this->ensureLanguageMethodExists('getMoveColumn');

    if ($st = $this->language->getMoveColumn($table, $column, $cfg, $after)) {
      return (int)$this->language->rawQuery($st);
    }

    return 0;
  }


  /**
   * Creates a user for a specific db.
   * @todo return data
   *
   * ```php
   * X::dump($db->createUser('Michael','22101980','db_example'));
   * // (bool) true
   * ```
   *
   * @param string|null $user
   * @param string|null $pass
   * @param string|null $db
   * @return bool
   * @throws Exception
   */
  public function createUser(string|null $user = null, string|null $pass = null, string|null $db = null): bool
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->createUser($user, $pass, $db);
  }


  /**
   * Deletes a db user.
   *
   * @todo non mi funziona ma forse per una questione di permessi/ return data
   *
   * ```php
   * X::dump($db->deleteUser('Michael'));
   * // (bool) true
   * ```
   *
   * @param string $user
   * @return bool
   * @throws Exception
   */
  public function deleteUser(string $user): bool
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->deleteUser($user);
  }


  /**
   * Return an array including privileges of a specific db_user or all db_users.
   * @param string $user . The user's name, without params will return all privileges of all db_users
   * @param string $host . The host
   * @return array
   * @throws Exception
   * @todo far vedere  a th la descrizione
   *
   * ```php
   * X::dump($db->getUsers('Michael'));
   * /* (array) [
   *      "GRANT USAGE ON *.* TO 'Michael'@''",
   *       GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER ON `db_example`.* TO 'Michael'@''"
   *    ]
   * ```
   *
   */
  public function getUsers(string $user = '', string $host = ''): ?array
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);

    return $this->language->getUsers($user, $host);
  }


  /**
   * Renames the given table to the new given name.
   *
   * @param string $table   The current table's name
   * @param string $newName The new name.
   * @return bool  True if it succeeded
   */
  public function renameTable(string $table, string $newName): bool
  {
    $this->ensureLanguageMethodExists(__FUNCTION__);
    return $this->language->renameTable($table, $newName);
  }

  /**
   * Returns the comment (or an empty string if none) for a given table.
   *
   * @param string $table The table's name
   *
   * @return string The table's comment
   */
  public function getTableComment(string $table): string
  {
    return $this->language->getTableComment($table);
  }

  /**
   * Gets the size of a database
   *
   * @param string $database
   * @param string $type
   * @return int
   */
  public function dbSize(string $database = '', string $type = ''): int
  {
    return $this->language->dbSize($database, $type);
  }


  /**
   * Gets the size of a table
   *
   * @param string $table
   * @param string $type
   * @return int
   */
  public function tableSize(string $table, string $type = ''): int
  {
    return $this->language->tableSize($table, $type);
  }


  /**
   * Gets the status of a table
   *
   * @param string $table
   * @param string $database
   * @return mixed
   */
  public function status(string $table = '', string $database = '')
  {
    return $this->language->status($table, $database);
  }


  /**
   * Returns a UUID
   *
   * @return string|null
   */
  public function getUid(): ?string
  {
    return $this->language->getUid();
  }

}

