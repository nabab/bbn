<?php
namespace bbn;

use Exception;

use bbn\Db\Engines;
use bbn\Db\Query;
use bbn\Db\Languages\Sql;
use bbn\Db\Sub\Actions as subActions;
use bbn\Db\Sub\Engine as subEngine;
use bbn\Db\Sub\Internal as subInternal;
use bbn\Db\Sub\Native as subNative;
use bbn\Db\Sub\Query as subQuery;
use bbn\Db\Sub\Read as subRead;
use bbn\Db\Sub\Shortcuts as subShortcuts;
use bbn\Db\Sub\Structure as subStructure;
use bbn\Db\Sub\Triggers as subTriggers;
use bbn\Db\Sub\Types as subTypes;
use bbn\Db\Sub\Utilities as subUtilities;
use bbn\Db\Sub\Write as subWrite;
use bbn\Db\Models\Itf\Actions as itfActions;
use bbn\Db\Models\Itf\Engine as itfEngine;
use bbn\Db\Models\Itf\Internal as itfInternal;
use bbn\Db\Models\Itf\Native as itfNative;
use bbn\Db\Models\Itf\Query as itfQuery;
use bbn\Db\Models\Itf\Read as itfRead;
use bbn\Db\Models\Itf\Shortcuts as itfShortcuts;
use bbn\Db\Models\Itf\Structure as itfStructure;
use bbn\Db\Models\Itf\Triggers as itfTriggers;
use bbn\Db\Models\Itf\Types as itfTypes;
use bbn\Db\Models\Itf\Utilities as itfUtilities;
use bbn\Db\Models\Itf\Write as itfWrite;
use bbn\Models\Tts\Cache;
use bbn\Models\Tts\Retriever;

/**
 * Half ORM half DB management, the simplest class for data queries.
 *
 * Hello world!
 *
 * @category  Database
 * @package Bbn
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version Release: <package_version>
 * @link https://bbn.io/bbn-php/doc/class/db
 * @since Apr 4, 2011, 23:23:55 +0000
 * @todo Check for the tables and column names legality in _treat_arguments
 */
class Db implements itfActions, itfEngine, itfInternal, itfNative, itfQuery, itfRead, itfShortcuts, itfStructure, itfTriggers, itfTypes, itfUtilities, itfWrite, Db\Actions
{
  use Cache;
  use Retriever;

  /**
   * @var Sql Can be other driver
   */
  protected Sql $language;

  private array $subs = [];

  /**
   * The ODBC engine of this connection
   * @var string $engine
   */
  protected $engine;

  /** @var array The database engines allowed */
  protected static $engines = [
    'mysql' => 'nf nf-dev-mysql',
    'pgsql' => 'nf nf-dev-postgresql',
    'sqlite' => 'nf nf-dev-sqlite'
  ];

  private function subActions(): subActions
  {
    $this->subs['subActions'] ??= new subActions($this, $this->language);
    return $this->subs['subActions'];
  }

  private function subEngine(): subEngine
  {
    $this->subs['subEngine'] ??= new subEngine($this, $this->language);
    return $this->subs['subEngine'];
  }

  private function subInternal(): subInternal
  {
    $this->subs['subInternal'] ??= new subInternal($this, $this->language);
    return $this->subs['subInternal'];
  }

  private function subNative(): subNative
  {
    $this->subs['subNative'] ??= new subNative($this, $this->language);
    return $this->subs['subNative'];
  }

  private function subQuery(): subQuery
  {
    $this->subs['subQuery'] ??= new subQuery($this, $this->language);
    return $this->subs['subQuery'];
  }

  private function subRead(): subRead
  {
    $this->subs['subRead'] ??= new subRead($this, $this->language);
    return $this->subs['subRead'];
  }

  private function subShortcuts(): subShortcuts
  {
    $this->subs['subShortcuts'] ??= new subShortcuts($this, $this->language);
    return $this->subs['subShortcuts'];
  }

  private function subStructure(): subStructure
  {
    $this->subs['subStructure'] ??= new subStructure($this, $this->language);
    return $this->subs['subStructure'];
  }

  private function subTriggers(): subTriggers
  {
    $this->subs['subTriggers'] ??= new subTriggers($this, $this->language);
    return $this->subs['subTriggers'];
  }

  private function subTypes(): subTypes
  {
    $this->subs['subTypes'] ??= new subTypes($this, $this->language);
    return $this->subs['subTypes'];
  }

  private function subUtilities(): subUtilities
  {
    $this->subs['subUtilities'] ??= new subUtilities($this, $this->language);
    return $this->subs['subUtilities'];
  }

  private function subWrite(): subWrite
  {
    $this->subs['subWrite'] ??= new subWrite($this, $this->language);
    return $this->subs['subWrite'];
  }

  public function getPDO(): ?\PDO
  {
    if ($this->check()) {
      return $this->language->getPDO();
    }

    return null;
  }


  public function getRow(): ?array
  {
    return $this->subActions()->getRow(...\func_get_args());
  }

  public function getRows(): ?array
  {
    return $this->subActions()->getRows(...\func_get_args());
  }

  public function getIrow(): ?array
  {
    return $this->subActions()->getIrow(...\func_get_args());
  }

  public function getIrows(): ?array
  {
    return $this->subActions()->getIrows(...\func_get_args());
  }

  public function getByColumns(): ?array
  {
    return $this->subActions()->getByColumns(...\func_get_args());
  }

  public function getObj(): ?\stdClass
  {
    return $this->subActions()->getObj(...\func_get_args());
  }

  public function getObject(): ?\stdClass
  {
    return $this->subActions()->getObject(...\func_get_args());
  }

  public function getObjects(): ?array
  {
    return $this->subActions()->getObjects(...\func_get_args());
  }

  public function charsets(): ?array
  {
    return $this->subActions()->charsets();
  }

  public function collations(): ?array
  {
    return $this->subActions()->collations();
  }

  public function createDatabase(string $database): bool
  {
    return $this->subActions()->createDatabase($database);
  }

  public function dropDatabase(string $database): bool
  {
    return $this->subActions()->dropDatabase($database);
  }

  public function renameDatabase(string $oldName, string $newName): bool
  {
    return $this->subActions()->renameDatabase($oldName, $newName);
  }

  public function duplicateDatabase(string $source, string $target, bool $withData = true): bool
  {
    return $this->subActions()->duplicateDatabase($source, $target, $withData);
  }

  public function getDatabaseCharset(string $database): ?string
  {
    return $this->subActions()->getDatabaseCharset($database);
  }

  public function getDatabaseCollation(string $database): ?string
  {
    return $this->subActions()->getDatabaseCollation($database);
  }

  public function tableExists(string $table, string $database = ''): bool
  {
    return $this->subActions()->tableExists($table, $database);
  }

  public function createTable(
    string $table,
    ?array $cfg = null,
    bool $createKeys = true,
    bool $createConstraints = true
  ): bool
  {
    return $this->subActions()->createTable($table, $cfg, $createKeys, $createConstraints);
  }

  public function dropTable(string $table, string $database = ''): bool
  {
    return $this->subActions()->dropTable($table, $database);
  }

  public function duplicateTable(string $source, string $target, bool $withData = true): bool
  {
    return $this->subActions()->duplicateTable($source, $target, $withData);
  }

  public function copyTableTo(string $table, Db $target, bool $withData = true, string $newName = ''): bool
  {
    return $this->subActions()->copyTableTo($table, $target, $withData, $newName);
  }

  public function getTableCharset(string $table): ?string
  {
    return $this->subActions()->getTableCharset($table);
  }

  public function getTableCollation(string $table): ?string
  {
    return $this->subActions()->getTableCollation($table);
  }

  public function createColumn(string $table, string $col, array $cfg): bool
  {
    return $this->subActions()->createColumn($table, $col, $cfg);
  }

  public function dropColumn(string $table, string $col): bool
  {
    return $this->subActions()->dropColumn($table, $col);
  }

  public function alterColumn(string $table, string $col, array $cfg): bool
  {
    return $this->subActions()->alterColumn($table, $col, $cfg);
  }

  public function createConstraints(string $table, ?array $cfg = null): bool
  {
    return $this->subActions()->createConstraints($table, $cfg);
  }

  public function dropConstraint(string $table, string $constraint): bool
  {
    return $this->subActions()->dropConstraint($table, $constraint);
  }

  public function createKeys(string $table, array $cfg): bool
  {
    return $this->subActions()->createKeys($table, $cfg);
  }

  public function dropKey(string $table, string $key): bool
  {
    return $this->subActions()->dropKey($table, $key);
  }

  public function enableLast()
  {
    return $this->subActions()->enableLast();
  }

  public function disableLast()
  {
    return $this->subActions()->disableLast();
  }

  public function getRealLastParams(): ?array
  {
    return $this->subActions()->getRealLastParams();
  }

  public function realLast(): ?string
  {
    return $this->subActions()->realLast();
  }

  public function getLastParams(): ?array
  {
    return $this->subActions()->getLastParams();
  }

  public function getLastValues(): ?array
  {
    return $this->subActions()->getLastValues();
  }

  public function getQuery(array $cfg): Query
  {
    return $this->subActions()->getQuery($cfg);
  }

  public function getQueryValues(array $cfg): array
  {
    return $this->subActions()->getQueryValues($cfg);
  }

  public function export4Option($table_name, $database = ''): array
  {
    return $this->subActions()->export4Option($table_name, $database);
  }

  public function parseQuery(string $query): ?array
  {
    return $this->subActions()->parseQuery($query);
  }

  public function analyzeDatabase(string $database): bool
  {
    return $this->subActions()->analyzeDatabase($database);
  }

  public function analyzeTable(string $table): bool
  {
    return $this->subActions()->analyzeTable($table);
  }

  public function postCreation()
  {
    return $this->subEngine()->postCreation();
  }

  public function change(string $db): Db
  {
    return $this->subEngine()->change($db);
  }

  public function escape(string $item): string
  {
    return $this->subEngine()->escape($item);
  }

  public function tableFullName(string $table, bool $escaped = false): ?string
  {
    return $this->subEngine()->tableFullName($table, $escaped);
  }

  public function isTableFullName(string $table): bool
  {
    return $this->subEngine()->isTableFullName($table);
  }

  public function isColFullName(string $col): bool
  {
    return $this->subEngine()->isColFullName($col);
  }

  public function tableSimpleName(string $table, bool $escaped = false): ?string
  {
    return $this->subEngine()->tableSimpleName($table, $escaped);
  }

  public function colFullName(string $col, ?string $table = null, bool $escaped = false): ?string
  {
    return $this->subEngine()->colFullName($col, $table, $escaped);
  }

  public function colSimpleName(string $col, bool $escaped = false): ?string
  {
    return $this->subEngine()->colSimpleName($col, $escaped);
  }

  public function setTimezone(string $tz): static
  {
    return $this->subEngine()->setTimezone($tz);
  }

  public function disableKeys(): static
  {
    return $this->subEngine()->disableKeys();
  }

  public function enableKeys(): static
  {
    return $this->subEngine()->enableKeys();
  }

  public function getDatabases(): ?array
  {
    return $this->subEngine()->getDatabases();
  }

  public function getTables(string $database = ''): ?array
  {
    return $this->subEngine()->getTables($database);
  }

  public function getColumns(string $table): ?array
  {
    return $this->subEngine()->getColumns($table);
  }

  public function getKeys(string $table): ?array
  {
    return $this->subEngine()->getKeys($table);
  }

  public function getConditions(array $conditions, array $cfg = [], bool $is_having = false, int $indent = 0): string
  {
    return $this->subEngine()->getConditions($conditions, $cfg, $is_having, $indent);
  }

  public function getSelect(array $cfg, bool $subCfg = false): string
  {
    return $this->subEngine()->getSelect($cfg);
  }

  public function getUnion(array $cfg): string
  {
    return $this->subEngine()->getUnion($cfg);
  }

  public function getInsert(array $cfg): string
  {
    return $this->subEngine()->getInsert($cfg);
  }

  public function getUpdate(array $cfg): string
  {
    return $this->subEngine()->getUpdate($cfg);
  }

  public function getDelete(array $cfg): string
  {
    return $this->subEngine()->getDelete($cfg);
  }

  public function getJoin(array $cfg, array|null $join = null): string
  {
    return $this->subEngine()->getJoin($cfg, $join);
  }

  public function getWhere(array $cfg): string
  {
    return $this->subEngine()->getWhere($cfg);
  }

  public function getGroupBy(array $cfg): string
  {
    return $this->subEngine()->getGroupBy($cfg);
  }

  public function getHaving(array $cfg): string
  {
    return $this->subEngine()->getHaving($cfg);
  }

  public function getOrder(array $cfg): string
  {
    return $this->subEngine()->getOrder($cfg);
  }

  public function getLimit(array $cfg): string
  {
    return $this->subEngine()->getLimit($cfg);
  }

  public function getCreate(string $table, array|null $model = null): string
  {
    return $this->subEngine()->getCreate($table, $model);
  }

  public function getCreateTable(string $table, ?array $cfg = null): string
  {
    return $this->subEngine()->getCreateTable($table, $cfg);
  }

  public function getCreateTableRaw(
    string $table,
    ?array $cfg = null,
    $createKeys = true,
    $createConstraints = true
  ): string
  {
    return $this->subEngine()->getCreateTableRaw($table, $cfg, $createKeys, $createConstraints);
  }

  public function getCreateKeys(string $table, array|null $model = null): string
  {
    return $this->subEngine()->getCreateKeys($table, $model);
  }

  public function getCreateConstraints(string $table, array|null $model = null): string
  {
    return $this->subEngine()->getCreateConstraints($table, $model);
  }

  public function createIndex(string $table, $column, bool $unique = false, ?int $length = null): bool
  {
    return $this->subEngine()->createIndex($table, $column, $unique, $length);
  }

  public function deleteIndex(string $table, string $key): bool
  {
    return $this->subEngine()->deleteIndex($table, $key);
  }

  public function getAlterTable(string $table, array $cfg): string
  {
    return $this->subEngine()->getAlterTable($table, $cfg);
  }

  public function getAlterColumn(string $table, array $cfg): string
  {
    return $this->subEngine()->getAlterColumn($table, $cfg);
  }

  public function getAlterKey(string $table, array $cfg): string
  {
    return $this->subEngine()->getAlterKey($table, $cfg);
  }

  public function alter(string $table, array $cfg): int
  {
    return $this->subEngine()->alter($table, $cfg);
  }

  public function moveColumn(string $table, string $column, array $cfg, string|null $after = null): int
  {
    return $this->subEngine()->moveColumn($table, $column, $cfg, $after);
  }

  public function createUser(string|null $user = null, string|null $pass = null, string|null $db = null): bool
  {
    return $this->subEngine()->createUser($user, $pass, $db);
  }

  public function deleteUser(string $user): bool
  {
    return $this->subEngine()->deleteUser($user);
  }

  public function getUsers(string $user = '', string $host = ''): ?array
  {
    return $this->subEngine()->getUsers($user, $host);
  }

  public function renameTable(string $table, string $newName): bool
  {
    return $this->subEngine()->renameTable($table, $newName);
  }

  public function getTableComment(string $table): string
  {
    return $this->subEngine()->getTableComment($table);
  }

  public function dbSize(string $database = '', string $type = ''): int
  {
    return $this->subEngine()->dbSize($database, $type);
  }

  public function tableSize(string $table, string $type = ''): int
  {
    return $this->subEngine()->tableSize($table, $type);
  }

  public function status(string $table = '', string $database = '')
  {
    return $this->subEngine()->status($table, $database);
  }

  public function getUid(): ?string
  {
    return $this->subEngine()->getUid();
  }

  public function getHash(): string
  {
    return $this->subInternal()->getHash();
  }

  public function replaceTableInConditions(array $conditions, $old_name, $new_name): array
  {
    return $this->subInternal()->replaceTableInConditions($conditions, $old_name, $new_name);
  }

  public function treatConditions(array $where, bool $full = true)
  {
    return $this->subInternal()->treatConditions($where, $full);
  }

  public function reprocessCfg(array $cfg): ?array
  {
    return $this->subInternal()->reprocessCfg($cfg);
  }

  public function processCfg(array $args, bool $force = false): ?array
  {
    return $this->subInternal()->processCfg($args, $force);
  }

  public function check(): bool
  {
    return $this->subInternal()->check();
  }

  public function log($st): static
  {
    return $this->subInternal()->log(...\func_get_args());
  }

  public function setErrorMode(string $mode): static
  {
    return $this->subInternal()->setErrorMode($mode);
  }

  public function getErrorMode(): string
  {
    return $this->subInternal()->getErrorMode();
  }

  public function clearCache(string $item, string $mode): static
  {
    return $this->subInternal()->clearCache($item, $mode);
  }

  public function clearAllCache(): static
  {
    return $this->subInternal()->clearAllCache();
  }

  public function stopFancyStuff(): static
  {
    return $this->subInternal()->stopFancyStuff();
  }

  public function startFancyStuff(): static
  {
    return $this->subInternal()->startFancyStuff();
  }

  public function fetch(string $query, ...$additionalArgs)
  {
    return $this->subNative()->fetch($query, ...$additionalArgs);
  }

  public function fetchAll(string $query, ...$additionalArgs)
  {
    return $this->subNative()->fetchAll($query, ...$additionalArgs);
  }

  public function fetchColumn(string $query, int $num = 0, ...$additionalArgs)
  {
    return $this->subNative()->fetchColumn($query, $num, ...$additionalArgs);
  }

  public function fetchObject(string $query, ...$additionalArgs)
  {
    return $this->subNative()->fetchObject($query, ...$additionalArgs);
  }

  public function query(string $statement, ...$additionalArgs)
  {
    return $this->subNative()->query($statement, ...$additionalArgs);
  }

  public function executeStatement(string $statement)
  {
    return $this->subNative()->executeStatement($statement);
  }

  public function rawQuery(string $st)
  {
    return $this->subNative()->rawQuery($st);
  }

  public function getOne()
  {
    return $this->subQuery()->getOne(...\func_get_args());
  }

  public function getVar()
  {
    return $this->subQuery()->getVar(...\func_get_args());
  }

  public function getKeyVal(): ?array
  {
    return $this->subQuery()->getKeyVal(...\func_get_args());
  }

  public function getColArray(): array
  {
    return $this->subQuery()->getColArray(...\func_get_args());
  }

  public function select($table, $fields = [], array $where = [], string|array $order= [], int $start = 0): ?\stdClass
  {
    return $this->subRead()->select($table, $fields, $where, $order, $start);
  }

  public function selectAll($table, $fields = [], array $where = [], string|array $order= [], int $limit = 0, int $start = 0): ?array
  {
    return $this->subRead()->selectAll($table, $fields, $where, $order, $limit, $start);
  }

  public function iselect($table, $fields = [], array $where = [], string|array $order= [], int $start = 0): ?array
  {
    return $this->subRead()->iselect($table, $fields, $where, $order, $start);
  }

  public function iselectAll($table, $fields = [], array $where = [], string|array $order= [], int $limit = 0, int $start = 0): ?array
  {
    return $this->subRead()->iselectAll($table, $fields, $where, $order, $limit, $start);
  }

  public function rselect($table, $fields = [], array $where = [], string|array $order= [], int $start = 0): ?array
  {
    return $this->subRead()->rselect($table, $fields, $where, $order, $start);
  }

  public function rselectAll($table, $fields = [], array $where = [], string|array $order= [], $limit = 0, $start = 0): ?array
  {
    return $this->subRead()->rselectAll($table, $fields, $where, $order, $limit, $start);
  }

  public function countUnion(array $union, array $where = []): ?int
  {
    return $this->subRead()->countUnion($union, $where);
  }

  public function selectUnion(array $union, $fields = [], array $where = [], string|array $order= [], $limit = 0, $start = 0): ?array
  {
    return $this->subRead()->selectUnion($union, $fields, $where, $order, $limit, $start);
  }

  public function rselectUnion(array $union, $fields = [], array $where = [], string|array $order= [], $limit = 0, $start = 0): ?array
  {
    return $this->subRead()->rselectUnion($union, $fields, $where, $order, $limit, $start);
  }

  public function iselectUnion(array $union, $fields = [], array $where = [], string|array $order= [], $limit = 0, $start = 0): ?array
  {
    return $this->subRead()->iselectUnion($union, $fields, $where, $order, $limit, $start);
  }

  public function selectOne($table, $field = null, array $where = [], string|array $order= [], int $start = 0)
  {
    return $this->subRead()->selectOne($table, $field, $where, $order, $start);
  }

  public function count($table, array $where = []): ?int
  {
    return $this->subRead()->count($table, $where);
  }

  public function selectAllByKeys($table, array $fields = [], array $where = [], string|array $order= [], int $limit = 0, int $start = 0): ?array
  {
    return $this->subRead()->selectAllByKeys($table, $fields, $where, $order, $limit, $start);
  }

  public function stat(string $table, string $column, array $where = [], string|array $order= []): ?array
  {
    return $this->subRead()->stat($table, $column, $where, $order);
  }

  public function getFieldValues($table, string|null $field = null, array $where = [], string|array $order= []): ?array
  {
    return $this->subRead()->getFieldValues($table, $field, $where, $order);
  }

  public function countFieldValues($table, string|null $field = null,  array $where = [], string|array $order= []): ?array
  {
    return $this->subRead()->countFieldValues($table, $field, $where, $order);
  }

  public function getColumnValues($table, string|null $field = null,  array $where = [], string|array $order= [], int $limit = 0, int $start = 0): ?array
  {
    return $this->subRead()->getColumnValues($table, $field, $where, $order, $limit, $start);
  }

  public function getValuesCount($table, string|null $field = null, array $where = [], string|array $order= []): array
  {
    return $this->subRead()->getValuesCount($table, $field, $where, $order);
  }

  public function tfn(string $table, bool $escaped = false): ?string
  {
    return $this->subShortcuts()->tfn($table, $escaped);
  }

  public function tsn(string $table, bool $escaped = false): ?string
  {
    return $this->subShortcuts()->tsn($table, $escaped);
  }

  public function cfn(string $col, ?string $table = null, bool $escaped = false): ?string
  {
    return $this->subShortcuts()->cfn($col, $table, $escaped);
  }

  public function csn(string $col, bool $escaped = false): ?string
  {
    return $this->subShortcuts()->csn($col, $escaped);
  }

  public function getFieldsList($tables): array
  {
    return $this->subStructure()->getFieldsList($tables);
  }

  public function getForeignKeys(string $col, string $table, string|null $db = null): array
  {
    return $this->subStructure()->getForeignKeys($col, $table, $db);
  }

  public function hasIdIncrement(string $table): bool
  {
    return $this->subStructure()->hasIdIncrement($table);
  }

  public function modelize($table = null, bool $force = false): ?array
  {
    return $this->subStructure()->modelize($table, $force);
  }

  public function convert(array $cfg, string $engine): array
  {
    return $this->subStructure()->convert($cfg, $engine);
  }

  public function getColMaxLength(string $column, string|null $table = null): ?int
  {
    return $this->subStructure()->getColMaxLength($column, $table);
  }

  public function fmodelize(string $table = '', bool $force = false): ?array
  {
    return $this->subStructure()->fmodelize($table, $force);
  }

  public function findReferences($column, string $db = ''): array
  {
    return $this->subStructure()->findReferences($column, $db);
  }

  public function findRelations($column, string $db = ''): ?array
  {
    return $this->subStructure()->findRelations($column, $db);
  }

  public function getPrimary(string $table): array
  {
    return $this->subStructure()->getPrimary($table);
  }

  public function getSinglePrimary(string $table): ?string
  {
    return $this->subStructure()->getSinglePrimary($table);
  }

  public function getUniquePrimary(string $table): ?string
  {
    return $this->subStructure()->getUniquePrimary($table);
  }

  public function getUniqueKeys(string $table): array
  {
    return $this->subStructure()->getUniqueKeys($table);
  }

  public function setDatabaseCharset(string $database, string $charset, string $collation): bool
  {
    return $this->subStructure()->setDatabaseCharset($database, $charset, $collation);
  }

  public function setTableCharset(string $table, string $charset, string $collation): bool
  {
    return $this->subStructure()->setTableCharset($table, $charset, $collation);
  }

  public function setColumnCharset(string $table, string $column, string $charset, string $collation): bool
  {
    return $this->subStructure()->setColumnCharset($table, $column, $charset, $collation);
  }

  public function enableTrigger(): static
  {
    return $this->subTriggers()->enableTrigger();
  }

  public function disableTrigger(): static
  {
    return $this->subTriggers()->disableTrigger();
  }

  public function isTriggerEnabled(): bool
  {
    return $this->subTriggers()->isTriggerEnabled();
  }

  public function isTriggerDisabled(): bool
  {
    return $this->subTriggers()->isTriggerDisabled();
  }

  public function setTrigger(callable $function, $kind = null, $moment = null, $tables = '*' ): static
  {
    return $this->subTriggers()->setTrigger($function, $kind, $moment, $tables);
  }

  public function setTriggers(array $triggers): static
  {
    return $this->subTriggers()->setTriggers($triggers);
  }

  public function getTriggers(): array
  {
    return $this->subTriggers()->getTriggers();
  }

  public function getDateTypes(): array
  {
    return $this->subTypes()->getDateTypes();
  }

  public function getBinaryTypes(): array
  {
    return $this->subTypes()->getBinaryTypes();
  }

  public function getTextTypes(): array
  {
    return $this->subTypes()->getTextTypes();
  }

  public function isBinaryType(string $type): bool
  {
    return $this->subTypes()->isBinaryType($type);
  }

  public function isNumericType(string $type): bool
  {
    return $this->subTypes()->isNumericType($type);
  }

  public function isDateType(string $type): bool
  {
    return $this->subTypes()->isDateType($type);
  }

  public function isTextType(string $type): bool
  {
    return $this->subTypes()->isTextType($type);
  }

  public function escapeValue(string $value, $esc = "'"): string
  {
    return $this->subUtilities()->escapeValue($value, $esc);
  }

  public function setLastInsertId($id = ''): static
  {
    return $this->subUtilities()->setLastInsertId($id);
  }

  public function last(): ?string
  {
    return $this->subUtilities()->last();
  }

  public function lastId()
  {
    return $this->subUtilities()->lastId();
  }

  public function flush(): int
  {
    return $this->subUtilities()->flush();
  }

  public function newId($table, int $min = 1)
  {
    return $this->subUtilities()->newId($table, $min);
  }

  public function randomValue($col, $table)
  {
    return $this->subUtilities()->randomValue($col, $table);
  }

  public function countQueries(): int
  {
    return $this->subUtilities()->countQueries();
  }

  public function insert($table, array|null $values = null, bool $ignore = false): ?int
  {
    return $this->subWrite()->insert($table, $values, $ignore);
  }

  public function insertUpdate($table, array|null $values = null): ?int
  {
    return $this->subWrite()->insertUpdate($table, $values);
  }

  public function update($table, array|null $values = null, array|null $where = null, bool $ignore = false): ?int
  {
    return $this->subWrite()->update($table, $values, $where, $ignore);
  }

  public function updateIgnore($table, array|null $values = null, array|null $where = null): ?int
  {
    return $this->subWrite()->updateIgnore($table, $values, $where);
  }

  public function delete($table, array|null $where = null, bool $ignore = false): ?int
  {
    return $this->subWrite()->delete($table, $where, $ignore);
  }

  public function deleteIgnore($table, array|null $where = null): ?int
  {
    return $this->subWrite()->deleteIgnore($table, $where);
  }

  public function insertIgnore($table, array|null $values = null): ?int
  {
    return $this->subWrite()->insertIgnore($table, $values);
  }

  public function truncate($table): ?int
  {
    return $this->subWrite()->truncate($table);
  }

  /**
   * Constructor
   *
   * ```php
   * $dbtest = new bbn\Db(['db_user' => 'test','db_engine' => 'mysql','db_host' => 'host','db_pass' => 't6pZDwRdfp4IM']);
   *  // (void)
   * ```
   * @param null|array $cfg Mandatory db_user db_engine db_host db_pass
   * @throws Exception
   */
  public function __construct(array $cfg = [])
  {
    if (!isset($cfg['engine']) && \defined('BBN_DB_ENGINE')) {
      $cfg['engine'] = constant('BBN_DB_ENGINE');
    }

    if (isset($cfg['engine'])) {
      if ($cfg['engine'] instanceof Engines) {
        $this->language = $cfg['engine'];
      }
      else {
        $engine = $cfg['engine'];
        $cls    = '\\bbn\\Db\\Languages\\'.ucwords($engine);

        if (!class_exists($cls)) {
          throw new Exception(X::_("The database engine %s is not recognized", $engine));
        }

        $this->language = new $cls($cfg);
      }

      self::retrieverInit($this);

      if ($cfg = $this->getCfg()) {
        $this->postCreation();
        $this->engine = (string)$cfg['engine'];
        $this->startFancyStuff();
      }
    }

    if (!$this->engine) {
      $connection  = $cfg['engine'] ?? 'No engine';
      $connection .= '/'.($cfg['db'] ?? 'No DB');
      $this->log(X::_("Impossible to create the connection for").' '.$connection);
      throw new Exception(X::_("Impossible to create the connection for").' '.$connection);
    }
  }


  /**
   * Closes the connection making the object unusable.
   *
   * @return void
   */
  public function close(): void
  {
    if (isset($this->language)) {
      $this->language->close();
      $this->setErrorMode('continue');
      unset($this->language);
    }

    self::retrieverRemove($this);
  }


  /**
   * Says if the given database engine is supported or not
   * 
   * ```php
   * X::adump(
   *   $db->isEngineSupported("mysql"), // true
   *   $db->isEngineSupported("postgre"), // false
   *   $db->isEngineSupported("sqlite"), // true
   *   $db->isEngineSupported("mssql"), // false
   *   $db->isEngineSupported("test") // false
   * );
   * ```
   * 
   * @param string $engine
   *
   * @return bool
   */
  public static function isEngineSupported(string $engine): bool
  {
    return isset(self::$engines[$engine]);
  }


  /**
   * Returns the icon (CSS class from nerd fonts) for the given db engine
   * 
   * ```php
   * echo '<i class="'.$ctrl->db->getEngineIcon("mysql").'"></i>'; // nf nf-dev-mysql
   * ```
   * 
   * @param string $engine Name of the engine
   * 
   * @return string|null
   */
  public static function getEngineIcon(string $engine): ?string
  {
    return self::$engines[$engine] ?? null;
  }

  /**
   * Return the config of the language
   * 
   * ```php
   * adump($ctrl->db->getCfg("mysql"));
   * ```
   *
   * @return array
   */
  public function getCfg(): array
  {
    $cfg = $this->language->getCfg();
    unset($cfg['pass']);
    return $cfg;
  }

  /**
   * Returns the engine used by the current connection.
   * 
   * ```php
   * X::adump($ctrl->db->getEngine()); // mysql
   * ```
   * 
   * @return string|null
   */
  public function getEngine(): ?string
  {
    return $this->engine;
  }


  /**
   * Returns the host of the current connection.
   * 
   * ```php
   * X::adump($ctrl->db->getHost()); // db.m3l.co
   * ```
   * 
   * @return string|null
   */
  public function getHost(): ?string
  {
    return $this->language->getHost();
  }


  /**
   * Returns the current database selected by the current connection.
   *
   * ```php
   * X::adump($ctrl->db->getCurrent()); // dev_mk
   * ```
   * 
   * @return string|null
   */
  public function getCurrent(): ?string
  {
    return $this->language->getCurrent();
  }


  /**
   * Returns the last error, return null if there is no last error.
   *
   * ```php
   * X::adump($ctrl->db->getLastError()); // null
   * ```
   * 
   * @return string|null
   */
  public function getLastError(): ?string
  {
    return $this->language->getLastError();
  }

  /**
   * Returns true if the column name is an aggregate function
   * 
   * ```php
   * X::adump($ctrl->db->isAggregateFunction("name")); // false
   * X::adump($ctrl->db->isAggregateFunction("ID")); // true
   * ```
   * 
   * @param string $f The string to check
   * 
   * @return bool
   */
  public function isAggregateFunction(string $f): bool
  {
    $cls = '\\bbn\\Db\\languages\\'.$this->engine;
    return $cls::isAggregateFunction($f);
  }


  /**
   * Makes that echoing the connection shows its engine and host.
   * 
   * ```php
   * X::adump($ctrl->db->__toString()); // Connection mysql to db.m3l.co
   * ```
   * 
   * @return string
   */
  public function __toString()
  {
    return "Connection {$this->engine} to " . $this->getHost();
  }


  /**
   * Returns the connection code
   * 
   * ```php
   * X::adump($ctrl->db->getConnectionCode()); // dev_mk@db.m3l.co
   * ```
   * 
   * @return string
   */
  public function getConnectionCode()
  {
    return $this->language->getConnectionCode();
  }

  /**
   * Returns the last config for this connection.
   *
   * ```php
   * X::dump($db->getLastCfg());
   * // (array) INSERT INTO `db_example.table_user` (`name`) VALUES (?)
   * ```
   *
   * @return array|null
   */
  public function getLastCfg(): ?array
  {
    return $this->language->getLastCfg();
  }

  /**
   * 
   * ```php
   * X::adump($ctrl->db->getConnectionParams()); 
   * ```
   * 
   * @param array $cfg The user's options
   * @return array|null The final configuration
   */
  public function getConnectionParams(array $cfg = []): ?array
  {
    return $this->language->getConnectionParams($cfg);
  }

  private function ensureLanguageMethodExists(string $method)
  {
    if (!method_exists($this->language, $method)) {
      throw new Exception(X::_('Method %s not found on the language %s class!', $method, $this->engine));
    }
  }
}