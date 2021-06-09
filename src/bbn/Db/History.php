<?php

namespace bbn\Db;

use bbn\Appui\Database;
use bbn\Db;
use bbn\Models\Tts\Dbconfig;
use bbn\Str;
use bbn\X;

class History
{

  use Dbconfig;

  /** @var array */
  protected static $default_class_cfg = [
    'table' => 'bbn_history',
    'tables' => [
      'history'      => 'bbn_history',
      'history_uids' => 'bbn_history_uids'
    ],
    'arch' => [
      'history' => [
        'opr' => 'opr',
        'uid' => 'uid',
        'col' => 'col',
        'val' => 'val',
        'ref' => 'ref',
        'tst' => 'tst',
        'usr' => 'usr',
        'dt' => 'dt'
      ],
      'history_uids' => [
        'bbn_uid'    => 'bbn_uid',
        'bbn_table'  => 'bbn_table',
        'bbn_active' => 'bbn_active'
      ]
    ],

    /**
     * Additional conditions when querying the history table
     * @var array
     */
    'conditions' => [],
  ];

  /** @var Db The DB connection */
  protected Db $db;

  /** @var database The database class which collects the columns IDs */
  private $database_obj;

  /** @var boolean Setting it to false avoid execution of history triggers */
  private $enabled = true;

  /** @var float The current date can be overwritten if this variable is set */
  private $date;

  /** @var string|null User's ID  */
  private ?string $user;


  /**
   * History constructor.
   * @param Db            $db
   * @param array         $cfg
   * @param string|null   $user
   * @param Database|null $database_obj
   * @throws \Exception
   */
  public function __construct(Db $db, array $cfg = [], ?string $user = null, ?Database $database_obj = null)
  {
    $this->db           = $db;
    $this->database_obj = $database_obj ?? new Database($this->db);
    $this->user         = $user;

    // Setting up the class configuration
    $this->_init_class_cfg($cfg);
  }


  /**
   * Returns the database connection object.
   *
   * @return Db
   */
  private function _get_db(): ?Db
  {
    return $this->db;
  }


  /**
   * Returns an instance of the Appui\Database class.
   *
   * @return database
   */
  private function _get_database(): ?database
  {
    return $this->database_obj;
  }


  /**
   * Adds a row in the history table.
   *
   * @param array $cfg
   * @return int
   */
  private function _insert(array $cfg): int
  {
    $this->ensureUserIsSet();

    if (isset($cfg['column'], $cfg['line'], $cfg['chrono'])) {
      // Recording the last ID
      $id = $this->db->lastId();
      $this->db->disableLast();
      $this->disable();
      if (!array_key_exists('old', $cfg)) {
        $cfg['ref'] = null;
        $cfg['val'] = null;
      }
      elseif (Str::isUid($cfg['old'])
          && $this->db->count(
            $this->getHistoryUidsTableName(),
            [$this->getHistoryUidsColumnName('bbn_uid') => $cfg['old']]
          )
      ) {
        $cfg['ref'] = $cfg['old'];
        $cfg['val'] = null;
      }
      else{
        $cfg['ref'] = null;
        $cfg['val'] = $cfg['old'];
      }

      // New row in the history table
      if ($res = $this->db->insert(
        $this->getHistoryUidsTableName(), [
        $this->getHistoryTableColumnName('opr') => $cfg['operation'],
        $this->getHistoryTableColumnName('uid') => $cfg['line'],
        $this->getHistoryTableColumnName('col') => $cfg['column'],
        $this->getHistoryTableColumnName('val') => $cfg['val'],
        $this->getHistoryTableColumnName('ref') => $cfg['ref'],
        $this->getHistoryTableColumnName('tst') => $this->date ?: $cfg['chrono'],
        $this->getHistoryTableColumnName('usr') => $this->user
        ]
      )
      ) {
        // Set back the original last ID
        $this->db->setLastInsertId($id);
      }

      $this->db->enableLast();
      $this->enable();
      return $res;
    }

    return 0;
  }


  /**
   * Get a string for the WHERE in the query with all the columns selection.
   * @param string $table
   * @return string|null
   */
  private function _get_table_where(string $table): ?string
  {
    if (Str::checkName($table)
        && ($model = $this->database_obj->modelize($table))
    ) {
      $col      = $this->db->escape('col');
      $where_ar = [];
      foreach ($model['fields'] as $k => $f){
        if (!empty($f['id_option'])) {
          $where_ar[] = $col.' = UNHEX("'.$this->db->escapeValue($f['id_option']).'")';
        }
      }

      if (\count($where_ar)) {
        return implode(' OR ', $where_ar);
      }
    }

    return null;
  }


  /**
   * Returns the column's corresponding option's ID
   * @param $column string
   * @param $table  string
   * @return null|string
   */
  public function getIdColumn(string $column, string $table): ?string
  {
    if ($full_table = $this->db->tfn($table)) {
      [$database, $table] = explode('.', $full_table);
      return $this->database_obj->columnId($column, $table, $database, $this->db->getHost());
    }

    return false;
  }


  /**
   * @return void
   */
  public function disable(): void
  {
    $this->enabled = false;
  }


  /**
   * @return void
   */
  public function enable(): void
  {
    $this->enabled = true;
  }


  /**
   * @return bool
   */
  public function isEnabled(): bool
  {
    return $this->enabled === true;
  }


  /**
   * @param $d
   * @return null|float
   */
  public function validDate($d): ?float
  {
    if (!Str::isNumber($d)) {
      $d = strtotime($d);
    }

    if (($d > 0) && Str::isNumber($d)) {
      return (float)$d;
    }

    return null;
  }


  /**
   * Effectively deletes a row (deletes the row, the history row and the ID row)
   *
   * @param string $id
   * @return bool
   */
  public function delete(string $id): bool
  {
    if ($id) {
      return $this->db->delete(
        $this->getHistoryUidsTableName(),
        [$this->getHistoryUidsColumnName('bbn_uid') => $id]
      );
    }

    return false;
  }


  /**
   * Sets the "active" column name
   *
   * @param string $column
   * @return void
   */
  public function setColumn(string $column): void
  {
    if (Str::checkName($column)) {
      $this->class_cfg['arch']['history_uids']['bbn_active'] = $column;
    }
  }


  /**
   * Gets the "active" column name
   *
   * @return string|null the "active" column name
   */
  public function getColumn(): ?string
  {
    return $this->getHistoryUidsColumnName('bbn_active');
  }


  /**
   * @param $date
   * @return void
   */
  public function setDate($date): void
  {
    // Sets the current date
    if (Str::isNumber($date) && !($date = strtotime($date))) {
      return;
    }

    $t = time();
    // Impossible to write history in the future
    if ($date > $t) {
      $date = $t;
    }

    $this->date = $date;
  }


  /**
   * @return float
   */
  public function getDate(): ?float
  {
    return $this->date;
  }


  /**
   * @return void
   */
  public function unsetDate(): void
  {
    $this->date = null;
  }


  /**
   * Sets the user ID that will be used to fill the user_id field
   * @param $user
   * @return void
   */
  public function setUser($user): void
  {
    // Sets the history table name
    if (Str::isUid($user)) {
      $this->user = $user;
    }
  }


  /**
   * Gets the user ID that is being used to fill the user_id field
   * @return null|string
   */
  public function getUser(): ?string
  {
    return $this->user;
  }


  /**
   * @param string      $table
   * @param int         $start
   * @param int         $limit
   * @param string|null $dir
   * @return array
   */
  public function getAllHistory(string $table, int $start = 0, int $limit = 20, string $dir = null): array
  {
    if ($id_table = $this->database_obj->tableId($table, $this->db->getCurrent())) {
      $tab      = $this->db->escape($this->getHistoryTableName());
      $tab_uids = $this->db->escape($this->getHistoryUidsTableName());
      $uid      = $this->db->cfn($this->getHistoryUidsColumnName('bbn_uid'), $this->getHistoryUidsTableName(), true);
      $id_tab   = $this->db->cfn($this->getHistoryUidsColumnName('bbn_table'), $this->getHistoryUidsTableName(), true);
      $uid2     = $this->db->cfn($this->getHistoryTableColumnName('uid'), $this->getHistoryTableName(), true);
      $chrono   = $this->db->cfn($this->getHistoryTableColumnName('tst'), $this->getHistoryTableName(), true);
      $order    = $dir && (Str::changeCase($dir, 'lower') === 'asc') ? 'ASC' : 'DESC';
      $sql      = <<< MYSQL
SELECT DISTINCT($uid)
FROM $tab_uids
  JOIN $tab
    ON $uid = $uid2
WHERE $id_tab = ? 
ORDER BY $chrono $order
LIMIT $start, $limit
MYSQL;
      return $this->db->getColArray($sql, hex2bin($id_table));
    }

    return [];
  }


  /**
   * @param $table
   * @param int $start
   * @param int $limit
   * @return array
   */
  public function getLastModifiedLines(string $table, int $start = 0, int $limit = 20): array
  {
    $r = [];
    if ($id_table = $this->database_obj->tableId($table, $this->db->getCurrent())) {
      $tab      = $this->db->escape($this->getHistoryTableName());
      $tab_uids = $this->db->escape($this->getHistoryUidsTableName());
      $uid      = $this->db->cfn($this->getHistoryUidsColumnName('bbn_uid'), $this->getHistoryUidsTableName(), true);
      $active   = $this->db->cfn($this->getHistoryUidsColumnName('bbn_active'), $this->getHistoryUidsTableName(), true);
      $id_tab   = $this->db->cfn($this->getHistoryUidsColumnName('bbn_table'), $this->getHistoryUidsTableName(), true);
      $line     = $this->db->escape($this->getHistoryTableColumnName('uid'));
      $chrono   = $this->db->escape($this->getHistoryTableColumnName('tst'));
      $sql      = <<< MYSQL
SELECT DISTINCT($line)
FROM $tab_uids
  JOIN $tab
    ON $uid = $line
WHERE $id_tab = ? 
AND $active = 1
ORDER BY $chrono
LIMIT $start, $limit
MYSQL;
      $r        = $this->db->getColArray($sql, hex2bin($id_table));
    }

    return $r;
  }


  /**
   * @return string
   */
  private function getHistoryTableName(): string
  {
    return $this->class_cfg['table'];
  }


  /**
   * @return array
   */
  private function getHistoryTableColumns(): array
  {
    return $this->class_cfg['arch']['history'];
  }


  /**
   * @param string $field
   * @return string|null
   */
  private function getHistoryTableColumnName(string $field): ?string
  {
    return $this->getHistoryTableColumns()[$field] ?? null;
  }


  /**
   * @return string
   */
  private function getHistoryUidsTableName(): string
  {
    return $this->class_cfg['tables']['history_uids'];
  }


  /**
   * @return array
   */
  private function getHistoryUidsColumns(): array
  {
    return $this->class_cfg['arch']['history_uids'];
  }


  /**
   * @param string $column
   * @return string|null
   */
  private function getHistoryUidsColumnName(string $column): ?string
  {
    return $this->getHistoryUidsColumns()[$column] ?? null;
  }


  private function ensureUserIsSet()
  {
    if (!$this->user) {
      throw new \Exception(X::_('User id is not set!'));
    }
  }


}
