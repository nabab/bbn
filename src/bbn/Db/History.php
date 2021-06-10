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

  /** @var null|string Name of the database where the history table is */
  private ?string $admin_db = '';

  /** @var boolean Setting it to false avoid execution of history triggers */
  private $enabled = true;

  /** @var float The current date can be overwritten if this variable is set */
  private $date;

  /** @var string|null User's ID  */
  private ?string $user;

  /** @var array A collection of DB connections  */
  private array $structures = [];

  /** @var array The foregin links atytached to history UIDs' table */
  private array $links;

  /** @var array A collection of DB connections  */
  private static array $instances = [];

  /** @var string Object hash based on configuration  */
  private string $hash;


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

    $this->admin_db = $this->db->getCurrent();

    $this->links = $this->db->getForeignKeys(
      $this->getHistoryUidsColumnName('bbn_uid'),
      $this->getHistoryUidsTableName(), $this->admin_db
    );

    $this->db->setTrigger('\\bbn\\Appui\\History::trigger');

    if (!in_array($this->hash = $this->makeHash(), self::$instances)) {
      self::$instances[$this->hash] = $this;
    }
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
   * Checks if all history parameters are set in order to read and write into history
   * @return bool
   */
  public function check(): bool
  {
    return isset($this->user);
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
   * @param string $table
   * @param string $id
   * @param $from_when
   * @param null   $column
   * @return null|array
   * @throws \Exception
   */
  public function getNextUpdate(string $table, string $id, $from_when, $column = null)
  {
    /** @todo To be redo totally with all the fields' IDs instead of the history column */
    if (Str::checkName($table)
        && ($date = $this->validDate($from_when))
        && ($id_table = $this->database_obj->tableId($table))
    ) {
      $this->disable();
      $tab      = $this->db->escape($this->getHistoryTableName());
      $tab_uids = $this->db->escape($this->getHistoryUidsTableName());
      $uid      = $this->db->cfn($this->getHistoryUidsColumnName('bbn_uid'), $this->getHistoryUidsTableName());
      $id_tab   = $this->db->cfn($this->getHistoryUidsColumnName('bbn_table'),$this->getHistoryUidsTableName());
      $id_col   = $this->db->cfn($this->getHistoryTableColumnName('col'), $this->getHistoryTableName());
      $line     = $this->db->cfn($this->getHistoryTableColumnName('uid'), $this->getHistoryTableName());
      $usr      = $this->db->cfn($this->getHistoryTableColumnName('usr'), $this->getHistoryTableName());
      $chrono   = $this->db->cfn($this->getHistoryTableColumnName('tst'), $this->getHistoryTableName());
      $where    = [
        $uid => $id,
        $id_tab => $id_table,
        [$chrono, '>', $date]
      ];
      if ($column) {
        $where[$id_col] = Str::isUid($column) ? $column : $this->database_obj->columnId($column, $id_table);
      }
      else {
        $w = $this->_get_table_where($table);
        //$where = $id_col." != UNHEX('$id_column') " . ($w ?: '');
      }

      $res = $this->db->rselect(
        [
        'tables' => [$tab_uids],
        'fields' => [
          $line,
          $id_col,
          $chrono,
          'val' => 'IFNULL('.$this->getHistoryTableColumnName('val').', '.$this->getHistoryTableColumnName('ref').')',
          $usr
        ],
        'join' => [[
          'table' => $tab,
          'on' => [
            'logic' => 'AND',
            'conditions' => [[
              'field' => $uid,
              'operator' => '=',
              'exp' => $line
            ]]
          ]]
        ],
        'where' => $where,
        'order' => [$chrono => 'ASC']
        ]
      );
      $this->enable();
      return $res;
    }

    return null;
  }


  /**
   * @param string $table
   * @param $from_when
   * @param $id
   * @param null   $column
   * @return null|array
   */
  public function getPrevUpdate(string $table, string $id, $from_when, $column = null): ?array
  {
    if (Str::checkName($table) && $date = $this->validDate($from_when)) {
      $tab       = $this->db->escape($this->getHistoryTableName());
      $line      = $this->db->escape($this->getHistoryTableColumnName('uid'));
      $operation = $this->db->escape($this->getHistoryTableColumnName('opr'));
      $chrono    = $this->db->escape($this->getHistoryTableColumnName('tst'));
      if ($column) {
        $where = $this->db->escape($this->getHistoryTableColumnName('col')).
          ' = UNHEX("'.$this->db->escapeValue(
            Str::isUid($column) ? $column : $this->database_obj->columnId($column, $table)
          ).'")';
      }
      else{
        $where = $this->_get_table_where($table);
      }

      $sql = <<< MYSQL
SELECT *
FROM $tab
WHERE $line = ?
AND ($where)
AND $operation LIKE 'UPDATE'
AND $chrono < ?
ORDER BY $chrono DESC
LIMIT 1
MYSQL;
      return $this->db->getRow($sql, hex2bin($id), $date);
    }

    return null;
  }


  /**
   * @param string $table
   * @param $from_when
   * @param string $id
   * @param $column
   * @return bool|mixed
   */
  public function getNextValue(string $table, string $id, $from_when, $column)
  {
    if ($r = $this->getNextUpdate($table, $id, $from_when, $column)) {
      return $r[$this->getHistoryTableColumnName('ref')] ?: $r[$this->getHistoryTableColumnName('val')];
    }

    return false;
  }


  /**
   * @param string $table
   * @param string $id
   * @param $from_when
   * @param $column
   * @return bool|mixed
   */
  public function getPrevValue(string $table, string $id, $from_when, $column)
  {
    if ($r = $this->getPrevUpdate($table, $id, $from_when, $column)) {
      return $r[$this->getHistoryTableColumnName('ref')] ?: $r[$this->getHistoryTableColumnName('val')];
    }

    return false;
  }


  /**
   * @param string $table
   * @param string $id
   * @param $when
   * @param array  $columns
   * @return array|null
   */
  public function getRowBack(string $table, string $id, $when, array $columns = []): ?array
  {
    if (!($when = $this->validDate($when))) {
      $this->_report_error("The date $when is incorrect", __CLASS__, __LINE__);
    }
    elseif (($model = $this->database_obj->modelize($table)) && ($cfg = $this->getTableCfg($table))
    ) {
      // Time is after last modification: the current is given
      $this->disable();
      if ($when >= time()) {
        $r = $this->db->rselect(
          $table, $columns, [
          $cfg['primary'] => $id
          ]
        ) ?: null;
      }
      // Time is before creation: null is given
      elseif ($when < $this->getCreationDate($table, $id)) {
        $r = null;
      }
      else {
        // No columns = All columns
        if (\count($columns) === 0) {
          $columns = array_keys($model['fields']);
        }

        $r = [];
        //die(var_dump($columns, $model['fields']));
        foreach ($columns as $col){
          $tmp = null;
          if (isset($model['fields'][$col]['id_option'])) {
            if ($tmp = $this->db->rselect(
              $this->getHistoryTableName(),
              [
                $this->getHistoryTableColumnName('val'),
                $this->getHistoryTableColumnName('ref')]
              , [
              $this->getHistoryTableColumnName('uid') => $id,
              $this->getHistoryTableColumnName('col') => $model['fields'][$col]['id_option'],
              $this->getHistoryTableColumnName('opr') => 'UPDATE',
              [$this->getHistoryTableColumnName('tst'), '>', $when]
              ]
            )
            ) {
              $r[$col] = $tmp[$this->getHistoryTableColumnName('ref')] ?: $tmp[$this->getHistoryTableColumnName('val')];
            }
          }

          if (!$tmp) {
            $r[$col] = $this->db->selectOne(
              $table, $col, [
              $cfg['primary'] => $id
              ]
            );
          }
        }
      }

      $this->enable();
      return $r;
    }

    return null;
  }


  /**
   * @param $table
   * @param $id
   * @param $when
   * @param $column
   * @return bool|mixed
   */
  public function getValBack(string $table, string $id, $when, $column)
  {
    if ($row = $this->getRowBack($table, $id, $when, [$column])) {
      return $row[$column];
    }

    return false;
  }


  public function getCreationDate(string $table, string $id): ?float
  {
    if ($res = $this->getCreation($table, $id)) {
      return $res['date'];
    }

    return null;
  }


  /**
   * @param $table
   * @param $id
   * @return array|null
   */
  public function getCreation(string $table, string $id): ?array
  {
    if (($cfg = $this->getTableCfg($table)) && ($id_col = $this->getIdColumn($cfg['primary'], $table))) {
      $this->disable();
      if ($r = $this->db->rselect(
        $this->getHistoryTableName(),
        [
          'date' => $this->getHistoryTableColumnName('tst'),
          'user' => $this->getHistoryTableColumnName('usr')
        ], [
        $this->getHistoryTableColumnName('uid') => $id,
        $this->getHistoryTableColumnName('col') => $id_col,
        $this->getHistoryTableColumnName('opr') => 'INSERT'
        ], [
        $this->getHistoryTableColumnName('tst') => 'DESC'
        ]
      )
      ) {
        $this->enable();
        return $r;
      }

      $this->enable();
    }

    return null;
  }


  /**
   * @param string $table
   * @param string $id
   * @param null   $column
   * @return float|null
   */
  public function getLastDate(string $table, string $id, $column = null): ?float
  {
    if ($column && ($id_col = $this->getIdColumn($column, $table))) {
      return $this->db->selectOne(
        $this->getHistoryTableName(), $this->getHistoryTableColumnName('tst'), [
        $this->getHistoryTableColumnName('uid') => $id,
        $this->getHistoryTableColumnName('col') => $id_col
        ], [
        $this->getHistoryTableColumnName('tst') => 'DESC'
        ]
      );
    }
    elseif (!$column && ($where = $this->_get_table_where($table))) {
      $tab    = $this->db->escape($this->getHistoryTableName());
      $chrono = $this->db->escape($this->getHistoryTableColumnName('tst'));
      $line   = $this->db->escape($this->getHistoryTableColumnName('uid'));
      $sql    = <<< MYSQL
SELECT $chrono
FROM $tab
WHERE $line = ?
AND ($where)
ORDER BY $chrono DESC
MYSQL;
      return $this->db->getOne($sql, hex2bin($id));
    }

    return null;
  }


  /**
   * @param string $table
   * @param string $id
   * @param string $col
   * @return array
   */
  public function getHistory(string $table, string $id, string $col = '')
  {
    if ($this->check($table) && ($modelize = $this->getTableCfg($table))) {
      $pat    = [
        'ins' => 'INSERT',
        'upd' => 'UPDATE',
        'res' => 'RESTORE',
        'del' => 'DELETE'
      ];
      $r      = [];
      $fields = [
        'date' => $this->getHistoryTableColumnName('tst'),
        'user' => $this->getHistoryTableColumnName('usr'),
        $this->getHistoryTableColumnName('col')
      ];
      $where  = [
        $this->getHistoryTableColumnName('uid') => $id
      ];

      if (!empty($col)) {
        if (!Str::isUid($col)) {
          $fields[] = $modelize['fields'][$col]['type'] === 'binary' ? $this->getHistoryTableColumnName($this->getHistoryTableColumnName('ref')) : $this->getHistoryTableColumnName($this->getHistoryTableColumnName('val'));

          $col = $this->database_obj->columnId($col, $table);
        }
        else {
          $idx = X::find($modelize['fields'], ['id_option' => strtolower($col)]);
          if (null === $idx) {
            throw new \Error(X::_("Impossible to find the option $col"));
          }

          $fields[] = $modelize['fields'][$idx]['type'] === 'binary' ? $this->getHistoryTableColumnName('ref') : $this->getHistoryTableColumnName('val');
        }

        $where[$this->getHistoryTableColumnName('col')] = $col;
      }
      else {
        $fields[] = $this->getHistoryTableColumnName('val');
        $fields[] = $this->getHistoryTableColumnName('ref');
      }

      foreach ($pat as $k => $p){
        $where[$this->getHistoryTableColumnName('opr')] = $p;
        if ($q = $this->db->rselectAll(
          [
          'table' => $this->getHistoryTableName(),
          'fields' => $fields,
          'where' => [
            'conditions' => $where
          ],
          'order' => [[
            'field' => $this->getHistoryTableColumnName('tst'),
            'dir' => 'desc'
          ]]
          ]
        )
        ) {
          $r[$k] = $q;
        }
      }

      return $r;
    }
  }


  /**
   * @param string $table
   * @param string $id
   * @return array
   */
  public function getFullHistory(string $table, string $id): array
  {
    $r = [];
    if ($where = $this->_get_table_where($table)) {
      $tab    = $this->db->escape($this->getHistoryTableName());
      $line   = $this->db->escape($this->getHistoryTableColumnName('uid'));
      $chrono = $this->db->escape($this->getHistoryTableColumnName('tst'));
      $sql    = <<< MYSQL
SELECT *
FROM $tab
WHERE $line = ?
AND ($where)
ORDER BY $chrono ASC
MYSQL;
      $r      = $this->db->getRows($sql, hex2bin($id));
    }

    return $r;
  }


  public function getColumnHistory(string $table, string $id, string $column)
  {
    if ($this->check()
        && ($primary = $this->db->getPrimary($table))
        && ($modelize = $this->getTableCfg($table))
    ) {
      if (Str::isUid($column)) {
        $column = X::find($modelize['fields'], ['id_option' => strtolower($column)]);
      }

      $current = $this->db->selectOne(
        $table, $column, [
        $primary[0] => $id
        ]
      );
      $val     = $modelize['fields'][$column] === 'binary' ? $this->getHistoryTableColumnName('ref') : $this->getHistoryTableColumnName('val');

      $hist = $this->getHistory($table, $id, $column);
      $r    = [];
      if ($crea = $this->getCreation($table, $id)) {
        if (!empty($hist['upd'])) {
          $hist['upd'] = array_reverse($hist['upd']);
          foreach ($hist['upd'] as $i => $h){
            if ($i === 0) {
              $r[] = [
                'date' => $crea['date'],
                $val => $h[$val],
                'user' => $crea['user']
              ];
            }
            else{
              $r[] = [
                'date' => $hist['upd'][$i - 1]['date'],
                $val => $h[$val],
                'user' => $hist['upd'][$i - 1]['user']
              ];
            }
          }

          $r[] = [
            'date' => $hist['upd'][$i]['date'],
            $val => $current,
            'user' => $hist['upd'][$i]['user']
          ];
        }
        elseif (!empty($hist['ins'])) {
          $r[0] = [
            'date' => $hist['ins'][0]['date'],
            $val => $current,
            'user' => $hist['ins'][0]['user']
          ];
        }
      }

      return $r;
    }
  }


  /**
   * Gets all information about a given table
   * @param string $table
   * @param bool   $force
   * @return null|array Table's full name
   */
  public function getTableCfg(string $table, bool $force = false): ?array
  {
    // Check history is enabled and table's name correct
    if (($table = $this->db->tfn($table))) {
      if ($force || !isset($this->$structures[$table])) {
        if ($model = $this->database_obj->modelize($table)) {
          $this->structures[$table] = [
            'history' => false,
            'primary' => false,
            'primary_type' => null,
            'primary_length' => 0,
            'auto_increment' => false,
            'id' => null,
            'fields' => []
          ];
          if ($this->isLinked($table)
              && isset($model['keys']['PRIMARY'])
              && (\count($model['keys']['PRIMARY']['columns']) === 1)
              && ($primary = $model['keys']['PRIMARY']['columns'][0])
              && !empty($model['fields'][$primary])
          ) {
            // Looking for the config of the table
            $this->structures[$table]['history']        = 1;
            $this->structures[$table]['primary']        = $primary;
            $this->structures[$table]['primary_type']   = $model['fields'][$primary]['type'];
            $this->structures[$table]['primary_length'] = $model['fields'][$primary]['maxlength'];
            $this->structures[$table]['auto_increment'] = isset($model['fields'][$primary]['extra']) && ($model['fields'][$primary]['extra'] === 'auto_increment');
            $this->structures[$table]['id']             = $this->database_obj->tableId($this->db->tsn($table), $this->db->getCurrent());
            $this->structures[$table]['fields']         = array_filter(
              $model['fields'], function ($a) {
                return isset($a['id_option']);
              }
            );
          }
        }
      }

      // The table exists and has history
      if (isset($this->structures[$table]) && !empty($this->structures[$table]['history'])) {
        return $this->structures[$table];
      }
    }

    return null;
  }


  /**
   * @param string|null $db
   * @param bool        $force
   * @return array
   */
  public function getDbCfg(string $db = null, bool $force = false): array
  {
    $res    = [];
    $tables = $this->db->getTables($db);
    if ($tables && count($tables)) {
      foreach ($tables as $t) {
        if ($tmp = $this->getTableCfg($t, $force)) {
          $res[$t] = $tmp;
        }
      }
    }

    return $res;
  }


  /**
   * @param string $table
   * @return bool
   */
  public function isLinked(string $table): bool
  {
    return ($ftable = $this->db->tfn($table)) && isset($this->$links[$ftable]);
  }


  public function getLinks()
  {
    return $this->links;
  }


  /**
   * The function used by the db trigger
   * This will basically execute the history query if it's configured for.
   *
   * @param array $cfg
   * @internal param string "table" The table for which the history is called
   * @internal param string "kind" The type of action: select|update|insert|delete
   * @internal param string "moment" The moment according to the db action: before|after
   * @internal param array "values" key/value array of fields names and fields values selected/inserted/updated
   * @internal param array "where" key/value array of fields names and fields values identifying the row
   * @return array The $cfg array, modified or not
   *
   */
  public function trigger(array $cfg): array
  {
    if (!$this->isEnabled()) {
      return $cfg;
    }

    $tables = $cfg['tables'] ?? (array)$cfg['table'];
    // Will return false if disabled, the table doesn't exist, or doesn't have history
    if (($cfg['kind'] === 'SELECT')
        && ($cfg['moment'] === 'before')
        && !empty($cfg['tables'])
        && !in_array($this->db->tfn($this->getHistoryTableName()), $cfg['tables_full'], true)
        && !in_array($this->db->tfn($this->getHistoryUidsTableName()), $cfg['tables_full'], true)
    ) {
      $change = 0;
      if (!isset($cfg['history'])) {
        $cfg['history'] = [];
        $new_join       = [];
        foreach ($cfg['join'] as $i => $t){
          $post_join = false;
          $model     = $this->db->modelize($t['table']);
          if (isset($model['keys']['PRIMARY'])
              && ($model['keys']['PRIMARY']['ref_table'] === $this->db->csn($this->getHistoryUidsTableName()))
          ) {
            $change++;
            if ($t['type'] !== 'left') {
              $post_join = [
                'table' => $this->db->tsn($this->getHistoryUidsTableName()),
                'alias' => $this->db->tsn($this->getHistoryUidsTableName()).$change,
                'type' => $t['type'] ?? 'right',
                'on' => [
                  'conditions' => [
                    [
                      'field' => $this->db->cfn(
                        $this->getHistoryUidsColumnName('bbn_uid'),
                        $this->getHistoryUidsTableName().$change
                      ),
                      'operator' => 'eq',
                      'exp' => $this->db->cfn(
                        $model['keys']['PRIMARY']['columns'][0],
                        !empty($t['alias']) ? $t['alias'] : $t['table'],
                        true
                      )
                    ], [
                      'field' => $this->db->cfn(
                        $this->getHistoryUidsColumnName('bbn_active'),
                        $this->getHistoryUidsTableName().$change
                      ),
                      'operator' => '=',
                      'exp' => '1'
                    ]
                  ],
                  'logic' => 'AND'
                ]
              ];
            }
            else{
              $join_alias                     = $t;
              $alias                          = strtolower(Str::genpwd());
              $join_alias['alias']            = $alias;
              $join_alias['on']['conditions'] = $this->db->replaceTableInConditions($join_alias['on']['conditions'], !empty($t['alias']) ? $t['alias'] : $t['table'], $alias);
              $new_join[]                     = $join_alias;
              $t['on']                        = [
                'conditions' => [
                  [
                    'field' => $this->db->cfn(
                      $this->getHistoryUidsColumnName('bbn_uid'),
                      $this->getHistoryUidsTableName().$change
                    ),
                    'operator' => 'eq',
                    'exp' => $this->db->cfn($model['keys']['PRIMARY']['columns'][0], !empty($t['alias']) ? $t['alias'] : $t['table'], true)
                  ], [
                    'field' => $this->db->cfn(
                      $this->getHistoryUidsColumnName('bbn_active'),
                      $this->getHistoryUidsTableName().$change
                    ),
                    'operator' => '=',
                    'exp' => '1'
                  ]
                ],
                'logic' => 'AND'
              ];
              $new_join[]                     = [
                'table' => $this->db->tsn($this->getHistoryUidsTableName()),
                'alias' => $this->db->tsn($this->getHistoryUidsTableName()).$change,
                'type' => 'left',
                'on' => [
                  'conditions' => [
                    [
                      'field' => $this->db->cfn(
                        $this->getHistoryUidsColumnName('bbn_uid'),
                        $this->getHistoryUidsTableName().$change
                      ),
                      'operator' => 'eq',
                      'exp' => $this->db->cfn($model['keys']['PRIMARY']['columns'][0], $alias, true)
                    ]
                  ],
                  'logic' => 'AND'
                ]
              ];
            }
          }

          $new_join[] = $t;
          if ($post_join) {
            $new_join[] = $post_join;
          }
        }

        foreach ($cfg['tables'] as $alias => $table){
          $model = $this->db->modelize($table);
          if (isset($model['keys']['PRIMARY']['ref_table'])
              && ($this->db->tfn($model['keys']['PRIMARY']['ref_db'].'.'.$model['keys']['PRIMARY']['ref_table']) === $this->getHistoryUidsTableName())
          ) {
            $change++;
            $new_join[] = [
              'table' => $this->getHistoryUidsTableName(),
              'alias' => $this->db->tsn($this->getHistoryUidsTableName()).$change,
              'on' => [
                'conditions' => [
                  [
                    'field' => $this->db->cfn(
                      $this->getHistoryUidsTableName().$change.'.'.$this->getHistoryUidsColumnName('bbn_uid')
                    ),
                    'operator' => 'eq',
                    'exp' => $this->db->cfn($model['keys']['PRIMARY']['columns'][0], \is_string($alias) ? $alias : $table, true)
                  ], [
                    'field' => $this->db->cfn(
                      $this->getHistoryUidsTableName().$change.'.'.$this->getHistoryUidsColumnName('bbn_active')
                    ),
                    'operator' => '=',
                    'exp' => '1'
                  ]
                ],
                'logic' => 'AND'
              ]
            ];
          }
        }

        if ($change) {
          $cfg['join']  = $new_join;
          $cfg['where'] = $cfg['filters'];
          $cfg          = $this->db->reprocessCfg($cfg);
        }
      }
    }

    if ($cfg['write']
        && ($table = $this->db->tfn(current($tables)))
        && ($s = $this->getTableCfg($table))
    ) {
      // This happens before the query is executed
      if ($cfg['moment'] === 'before') {
        $primary_where   = false;
        $primary_defined = false;
        $primary_value   = false;
        $idx1            = X::find($cfg['values_desc'], ['primary' => true]);
        if ($idx1 !== null) {
          $primary_where = $cfg['values'][$idx1];
        }

        $idx = array_search($s['primary'], $cfg['fields'], true);
        if (($idx !== false) && isset($cfg['values'][$idx])) {
          $primary_defined = $cfg['generate_id'] ? false : true;
          $primary_value   = $cfg['values'][$idx];
        }

        switch ($cfg['kind']){
          case 'INSERT':
            // If the primary is specified and already exists in a row in deleted state
            // (if it exists in active state, DB will return its standard error but it's not this class' problem)
            if (!$primary_defined) {
              // Checks if there is a unique value (non based on UID)
              $modelize = $this->db->modelize($table);
              $keys     = $modelize['keys'];
              unset($keys['PRIMARY']);
              foreach ($keys as $key){
                if (!empty($key['unique']) && !empty($key['columns'])) {
                  $fields = [];
                  $exit   = false;
                  foreach ($key['columns'] as $col){
                    $col_idx = array_search($col, $cfg['fields'], true);
                    if (($col_idx === false) || \is_null($cfg['values'][$col_idx])) {
                      $exit = true;
                      break;
                    }
                    else {
                      $fields[] = [
                        'field' => $col,
                        'operator' => 'eq',
                        'value' => $cfg['values'][$col_idx]
                      ];
                    }
                  }

                  if ($exit) {
                    continue;
                  }

                  $this->disable();
                  if ($tmp = $this->db->selectOne(
                    [
                    'tables' => [$table],
                    'fields' => [$s['primary']],
                    'join' => [[
                      'table' => $this->getHistoryUidsTableName(),
                      'on' => [[
                        'field' => $this->db->cfn(
                          $this->getHistoryUidsColumnName('bbn_uid'),
                          $this->getHistoryUidsTableName()
                        ),
                        'operator' => 'eq',
                        'exp' => $this->db->cfn($s['primary'], $table, true)
                      ]]
                    ]],
                    'where' => [
                      'conditions' => $fields,
                      'logic' => 'AND'
                    ]
                    ]
                  )
                  ) {
                    $primary_value   = $tmp;
                    $primary_defined = true;
                    $this->enable();
                    break;
                  }

                  $this->enable();
                }
              }
            }

            if ($primary_defined
                && ($this->db->selectOne(
                  $this->getHistoryUidsTableName(),
                  $this->getColumn(),
                  [$this->getHistoryUidsColumnName('bbn_uid') => $primary_value]
                ) === 0)
                
                && ($all = $this->db->rselect(
                  [
                  'table' => $table,
                  'fields' => $cfg['fields'],
                  'join' => [[
                  'table' => $this->getHistoryUidsTableName(),
                  'on' => [
                    'conditions' => [[
                      'field' => $s['primary'],
                      'exp' => 'bbn_uid'
                    ], [
                      'field' => $this->getColumn(),
                      'value' => 0
                    ]]
                  ]
                  ]],
                  'where' => [
                  'conditions' => [[
                    'field' => $s['primary'],
                    'value' => $primary_value
                  ]]
                  ]
                  ]
                ))
            ) {
              // We won't execute the after trigger
              $cfg['trig'] = false;
              // Real query's execution will be prevented
              $cfg['run']   = false;
              $cfg['value'] = 0;
              /** @var array $update The values to be updated */
              $update = [];
              // We update each element which needs to (the new ones different from the old, and the old ones different from the default)
              foreach ($all as $k => $v){
                if ($k !== $s['primary']) {
                  $idx = array_search($k, $cfg['fields'], true);
                  if ($idx !== false) {
                    if ($v !== $cfg['values'][$idx]) {
                      $update[$k] = $cfg['values'][$idx];
                    }
                  }
                  elseif ($v !== $s['fields'][$k]['default']) {
                    $update[$k] = $s['fields'][$k]['default'];
                  }
                }
              }

              $this->disable();
              if ($cfg['value'] = $this->db->update(
                $this->getHistoryUidsTableName(), [$this->getHistoryUidsColumnName('bbn_active') => 1], [
                [$this->getHistoryUidsColumnName('bbn_uid'), '=', $primary_value]
                ]
              )
              ) {
                // Without this the record won't be write in bbn_history. Added by Mirko
                $cfg['trig'] = true;
                // --------
                if (\count($update) > 0) {
                  $this->enable();
                  $this->db->update(
                    $table, $update, [
                    $s['primary'] => $primary_value
                    ]
                  );
                }

                $cfg['history'][] = [
                  'operation' => 'RESTORE',
                  'column' => $s['fields'][$s['primary']]['id_option'],
                  'line' => $primary_value,
                  'chrono' => microtime(true)
                ];
              }

              $this->enable();
            }
            else {
              $this->disable();
              if ($primary_defined && !$this->db->count($table, [$s['primary'] => $primary_value])) {
                $primary_defined = false;
              }

              if (!$primary_defined && $this->db->insert(
                $this->getHistoryUidsTableName(), [
                  $this->getHistoryUidsColumnName('bbn_uid') => $primary_value,
                  $this->getHistoryUidsColumnName('bbn_table') => $s['id']
                ]
              )
              ) {
                $cfg['history'][] = [
                  'operation' => 'INSERT',
                  'column' => isset($s['fields'][$s['primary']]) ? $s['fields'][$s['primary']]['id_option'] : null,
                  'line' => $primary_value,
                  'chrono' => microtime(true)
                ];
                $this->db->setLastInsertId($primary_value);
              }

              $this->enable();
            }
            break;
          case 'UPDATE':
            // ********** CHANGED BY MIRKO *************
            if ($primary_where
                && ($row = $this->db->rselect($table, $cfg['fields'], [$s['primary'] => $primary_where]))
            ) {
              $time = microtime(true);
              foreach ($cfg['fields'] as $i => $idx){
                $csn = $this->db->csn($idx);
                if (array_key_exists($csn, $s['fields'])
                    && ($row[$csn] !== $cfg['values'][$i])
                ) {
                  $cfg['history'][] = [
                    'operation' => 'UPDATE',
                    'column' => $s['fields'][$csn]['id_option'],
                    'line' => $primary_where,
                    'old' => $row[$csn],
                    'chrono' => $time
                  ];
                }
              }
            }
            // Case where the primary is not defined, we'll update each primary instead
            elseif ($ids = $this->db->getColumnValues($table, $s['primary'], $cfg['filters'])) {
              // We won't execute the after trigger
              $cfg['trig'] = false;
              // Real query's execution will be prevented
              $cfg['run']   = false;
              $cfg['value'] = 0;

              $tmp = [];
              foreach ($cfg['fields'] as $i => $f){
                $tmp[$f] = $cfg['values'][$i];
              }

              foreach ($ids as $id){
                $cfg['value'] += $this->db->update($table, $tmp, [$s['primary'] => $id]);
              }

              // ****************************************
            }
            break;

          // Nothing is really deleted, the hcol is just set to 0
          case 'DELETE':
            // We won't execute the after trigger
            $cfg['trig'] = false;
            // Real query's execution will be prevented
            $cfg['run']   = false;
            $cfg['value'] = 0;
            // Case where the primary is not defined, we'll delete based on each primary instead
            if (!$primary_where) {
              $ids = $this->db->getColumnValues($table, $s['primary'], $cfg['filters']);
              foreach ($ids as $id){
                $cfg['value'] += $this->db->delete($table, [$s['primary'] => $id]);
              }
            }
            else {
              $this->disable();
              $cfg['value'] = $this->db->update(
                $this->getHistoryUidsTableName(), [
                $this->getHistoryUidsColumnName('bbn_active') => 0
                ], [
                $this->getHistoryUidsColumnName('bbn_uid') => $primary_where
                ]
              );
              //var_dump("HIST", $primary_where);
              $this->enable();
              if ($cfg['value']) {
                $cfg['trig'] = 1;
                // And we insert into the history table
                $cfg['history'][] = [
                  'operation' => 'DELETE',
                  'column' => $s['fields'][$s['primary']]['id_option'],
                  'line' => $primary_where,
                  'old' => null,
                  'chrono' => microtime(true)
                ];
              }
            }
            break;
        }
      }
      elseif (($cfg['moment'] === 'after')
          && isset($cfg['history'])
      ) {
        foreach ($cfg['history'] as $h){
          $this->_insert($h);
        }

        unset($cfg['history']);
      }
    }

    return $cfg;
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

  /**
   * Makes a string that will be the id of the request.
   *
   * @return string
   *
   */
  private function makeHash(): string
  {
    $args = $this->class_cfg;
    if ((\count($args) === 1) && \is_array($args[0])) {
      $args = $args[0];
    }

    $st = '';
    foreach ($args as $a){
      $st .= \is_array($a) ? serialize($a) : '--'.$a.'--';
    }

    return md5($st);
  }

  /**
   * Returns the hash of the object.
   *
   * @return string
   */
  public function getHash()
  {
    return $this->hash;
  }

  /**
   * Returns an instance of registered history by it's hash.
   *
   * @param string $hash
   * @return History|null
   */
  public static function getInstanceFromHash(string $hash): ?History
  {
    if (isset(self::$instances[$hash]) && self::$instances[$hash] instanceof History) {
      return self::$instances[$hash];
    }

    return null;
  }

}
