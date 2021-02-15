<?php
namespace bbn;

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
class Db extends \PDO implements Db\Actions, Db\Api, Db\Engines
{
  use Models\Tts\Cache;
  use Models\Tts\Retriever;

  /**
   * The error configuration for continuing after an error occurred
   */
  protected const E_CONTINUE = 'continue';

  /**
   * The error configuration for dying after an error occurred
   */
  protected const E_DIE = 'die';

  /**
   * The error configuration for stopping all requests on all connections after an error occurred
   */
  protected const E_STOP_ALL = 'stop_all';

  /**
   * The error configuration for stopping requests on the current connection after an error occurred
   */
  protected const E_STOP = 'stop';

  /**
   * An elegant separator
   */
  protected const LINE = '---------------------------------------------------------------------------------';

  /**
   * When set to true last_query will be filled with the latest statement.
   * @var bool
   */
  private $_last_enabled = true;

  /**
   * A PHPSQLParser object
   * @var \PHPSQLParser\PHPSQLParser
   */
  private $_parser;

  /**
   * @var mixed $cache
   */
  private $_cache = [];

  /**
   * If set to false, Query will return a regular PDOStatement
   * Use stop_fancy_stuff() to set it to false
   * And use start_fancy_stuff to set it back to true
   * @var int $fancy
   */
  private $_fancy = 1;

  /**
   * Error state of the current connection
   * @var bool $has_error
   */
  private $_has_error = false;

  /**
   * An array of functions for launching triggers on actions
   * @var array
   */
  private $_triggers = [
    'SELECT' => [
      'before' => [],
      'after' => []
    ],
    'INSERT' => [
      'before' => [],
      'after' => []
    ],
    'UPDATE' => [
      'before' => [],
      'after' => []
    ],
    'DELETE' => [
      'before' => [],
      'after' => []
    ]
  ];

  /**
   * @var bool
   */
  private $_triggers_disabled = false;

  /** @var string The connection code as it would be stored in option */
  protected $connection_code;

  /**
   * @todo is bool or string??
   * Unique string identifier for current connection
   * @var string
   */
  protected $hash;

  /**
   * @var Db\Languages\Mysql Can be other driver
   */
  protected $language = false;

  /**
   * @var integer $cache_renewal
   */
  protected $cache_renewal = 3600;

  /**
   * @var array $list_queries
   */
  protected $list_queries = [];

  /**
   * @var int $max_queries
   */
  protected $max_queries = 50;

  /**
   * @var int $length_queries
   */
  protected $length_queries = 60;

  /**
   * @var mixed $last_insert_id
   */
  protected $last_insert_id;

  /**
   * @var mixed $id_just_inserted
   */
  protected $id_just_inserted;

  /**
   * @var mixed $hash_contour
   */
  protected $hash_contour = '__BBN__';

  /**
   * @var string \$last_query
   */
  protected $last_query;

  /**
   * The information that will be accessed by Db\Query as the current statement's options
   * @var array $last_params
   */
  protected $last_params = ['sequences' => false, 'values' => false];

  /**
   * @var string \$last_query
   */
  protected $last_real_query;

  /**
   * The information that will be accessed by Db\Query as the current statement's options
   * @var array $last_params
   */
  protected $last_real_params = ['sequences' => false, 'values' => false];

  /**
   * @var array $last_cfg
   */
  protected $last_cfg;

  /**
   * @var mixed $last_prepared
   */
  protected $last_prepared;

  /**
   * @var array $queries
   */
  protected $queries = [];

  /**
   * @var array $cfgs The configs recorded for helpers functions
   */
  protected $cfgs = [];

  /**
   * @var string $qte The quote character for table and column names.
   */
  protected $qte;

  /**
   * @var string $last_error
   */
  protected $last_error = false;

  /**
   * The ODBC engine of this connection
   * @var string $engine
   */
  protected $engine;

  /**
   * The host of this connection
   * @var string $host
   */
  protected $host;

  /**
   * The host of this connection
   * @var string $host
   */
  protected $username;

  /**
   * The currently selected database
   * @var mixed $current
   */
  protected $current;

  /**
   * @var string $on_error
   * Possible values:
   * *    stop: the script will go on but no further database query will be executed
   * *    die: the script will die with the error
   * *    continue: the script and further queries will be executed
   */
  protected $on_error = self::E_STOP_ALL;

  /** @var array The 'kinds' of writing statement */
  protected static $write_kinds = ['INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE'];

  /** @var array The 'kinds' of structure alteration statement */
  protected static $structure_kinds = ['DROP', 'ALTER', 'CREATE'];

  /** @var array The database engines allowed */
  protected static $engines = [
    'mysql' => 'nf nf-dev-mysql',
    'pgsql' => 'nf nf-dev-postgresql',
    'sqlite' => 'nf nf-dev-sqllite'
  ];

  /**
   * Error state of the current connection
   * @var bool
   */
  private static $_has_error_all = false;


  /**
   * Says if the given database engine is supported or not
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
   * @param string $engine
   *
   * @return string|null
   */
  public static function getEngineIcon(string $engine): ?string
  {
    return self::$engines[$engine] ?? null;
  }


  /**
   * test
   */
  public static function createDatabaseSqlite(string $database)
  {
    if (!is_file($database)) {
      file_put_contents($database,'');
      if (is_file($database)) {
        return [
          'engine' => 'sqlite',
          'db' => $database
        ];
      }
    }

    return false;
  }


  /**
   * Returns a string with the given text in the middle of a "line" of logs.
   *
   * @param string $text The text to write
   *
   * @return void
   */
  public static function getLogLine(string $text = '')
  {
    if ($text) {
      $text = ' '.$text.' ';
    }

    $tot  = \strlen(self::LINE) - \strlen($text);
    $char = \substr(self::LINE, 0, 1);
    return \str_repeat($char, floor($tot / 2)).$text.\str_repeat($char, ceil($tot / 2));
  }


  /**
   * Constructor
   *
   * ```php
   * $dbtest = new bbn\Db(['db_user' => 'test','db_engine' => 'mysql','db_host' => 'host','db_pass' => 't6pZDwRdfp4IM']);
   *  // (void)
   * ```
   * @param null|array $cfg Mandatory db_user db_engine db_host db_pass
   */
  public function __construct(array $cfg = [])
  {
    if (\defined('BBN_DB_ENGINE') && !isset($cfg['engine'])) {
      $cfg['engine'] = BBN_DB_ENGINE;
    }

    if (isset($cfg['engine'])) {
      $engine = $cfg['engine'];
      $db     = $cfg['db'] ?? (defined('BBN_DATABASE') ? BBN_DATABASE : '?');
      $cls    = '\\bbn\\Db\\Languages\\'.ucwords($engine);
      if (!class_exists($cls)) {
        die("Sorry the engine class $engine does not exist");
      }

      self::retrieverInit($this);
      $this->cacheInit();
      $this->language = new $cls($this);
      if (isset($cfg['on_error'])) {
        $this->on_error = $cfg['on_error'];
      }

      if ($cfg = $this->getConnection($cfg)) {
        $this->qte = $this->language->qte;
        try{
          parent::__construct(...($cfg['args'] ?: []));
        }
        catch (\PDOException $e){
          $err = dgettext(X::tDom(), "Impossible to create the connection")." $engine/$db "
                 .dgettext(X::tDom(), "with the following error").$e->getMessage();
          throw new \Exception($err);
        }

        $this->language->postCreation();
        $this->current  = $cfg['db'] ?? null;
        $this->engine   = $cfg['engine'];
        $this->host     = $cfg['host'] ?? '127.0.0.1';
        $this->username = $cfg['user'] ?? null;
        $this->connection_code = $cfg['code_host'];
        $this->hash     = $this->_make_hash($cfg['args']);
        $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        if (!empty($cfg['cache_length'])) {
          $this->cache_renewal = (int)$cfg['cache_length'];
        }

        $this->startFancyStuff();
        if (!empty($cfg['error_mode'])) {
          $this->setErrorMode($cfg['error_mode']);
        }
      }
    }

    if (!$this->engine) {
      $connection  = $cfg['engine'] ?? 'No engine';
      $connection .= '/'.($cfg['db'] ?? 'No DB');
      $this->log(dgettext(X::tDom(), "Impossible to create the connection for").' '.$connection);
      throw new \Exception(dgettext(X::tDom(), "Impossible to create the connection for").' '.$connection);
    }
  }


  /**
   * Returns the engine used by the current connection.
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
   * @return string|null
   */
  public function getHost(): ?string
  {
    return $this->host;
  }


  /**
   * Returns the current database selected by the current connection.
   *
   * @return string|null
   */
  public function getCurrent(): ?string
  {
    return $this->current;
  }


  /**
   * Returns the last error.
   *
   * @return string|null
   */
  public function getLastError(): ?string
  {
    return $this->last_error;
  }


  /**
   * Returns true if the column name is an aggregate function
   *
   * @param string $f The string to check
   * @return bool
   */
  public function isAggregateFunction(string $f): bool
  {
    $cls = '\\bbn\\Db\\languages\\'.$this->engine;
    return $cls::isAggregateFunction($f);
  }


  /**
   * Makes that echoing the connection shows its engin and host.
   *
   * @return string
   */
  public function __toString()
  {
    return "Connection {$this->engine} to {$this->host}";
  }


  public function getConnectionCode()
  {
    return $this->connection_code;
  }


  /****************************************************************
   *                                                              *
   *                                                              *
   *                      INTERNAL METHODS                        *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Gets the last hash created.
   *
   * ```php
   * X::dump($db->getHash());
   * // (string) 3819056v431b210daf45f9b5dc2
   * ```
   *
   * @todo chiedere e thomas se deve diventare private e se va bene la descrizione
   * @return string
   */
  public function getHash()
  {
    return $this->hash;
  }


  public function replaceTableInConditions(array $conditions, $old_name, $new_name)
  {
    return X::map(
      function ($a) use ($old_name, $new_name) {
        if (!empty($a['field'])) {
          $a['field'] = preg_replace("/(\\W|^)$old_name([\\`\\']*\\s*)\\./", '$1'.$new_name.'$2.', $a['field']);
        }

        if (!empty($a['exp'])) {
          $a['exp'] = preg_replace("/(\\W|^)$old_name([\\`\\']*\\s*)\\./", '$1'.$new_name.'$2.', $a['exp']);
        }

        return $a;
      }, $conditions, 'conditions'
    );
  }


  /**
   * Retrieves a query array based on its hash.
   * @param string $hash
   * @return array|null
   */
  public function retrieveQuery(string $hash): ?array
  {
    if (isset($this->queries[$hash])) {
      if (\is_string($this->queries[$hash])) {
        $hash = $this->queries[$hash];
      }

      return $this->queries[$hash];
    }

    return null;
  }


  /**
   * Retrieves a configuration array based on its hash.
   * @param string $hash
   * @return array|null
   */
  public function retrieveCfg(string $hash): ?array
  {
    return $this->cfgs[$hash] ?? null;
  }


  /**
   * @param array $where
   * @param bool  $full
   * @return array|bool
   */
  public function treatConditions(array $where, $full = true)
  {
    if (!isset($where['conditions'])) {
      $where['conditions'] = $where;
    }

    if (isset($where['conditions']) && \is_array($where['conditions'])) {
      if (!isset($where['logic']) || (strtoupper($where['logic']) !== 'OR')) {
        $where['logic'] = 'AND';
      }

      $res = [
        'conditions' => [],
        'logic' => $where['logic']
      ];
      foreach ($where['conditions'] as $key => $f){
        $is_array = \is_array($f);
        if ($is_array
            && array_key_exists('conditions', $f)
            && \is_array($f['conditions'])
        ) {
          $res['conditions'][] = $this->treatConditions($f, false);
        }
        else {
          if (\is_string($key)) {
            // 'id_user' => [1, 2] Will do OR
            if (!$is_array) {
              if (null === $f) {
                $f = [
                  'field' => $key,
                  'operator' => 'isnull'
                ];
              }
              else{
                $f = [
                  'field' => $key,
                  'operator' => is_string($f) && !Str::isUid($f) ? 'LIKE' : '=',
                  'value' => $f
                ];
              }
            }
            elseif (isset($f[0])) {
              $tmp = [
                'conditions' => [],
                'logic' => 'OR'
              ];
              foreach ($f as $v){
                if (null === $v) {
                  $tmp['conditions'][] = [
                    'field' => $key,
                    'operator' => 'isnull'
                  ];
                }
                else{
                  $tmp['conditions'][] = [
                    'field' => $key,
                    'operator' => is_string($f) && !Str::isUid($f) ? 'LIKE' : '=',
                    'value' => $v
                  ];
                }
              }

              $res['conditions'][] = $tmp;
            }
          }
          elseif ($is_array && !X::isAssoc($f) && count($f) >= 2) {
            $tmp = [
              'field' => $f[0],
              'operator' => $f[1]
            ];
            if (isset($f[3])) {
              $tmp['exp'] = $f[3];
            }
            elseif (array_key_exists(2, $f)) {
              if (is_array($f[2])) {
                $tmp = [
                  'conditions' => [],
                  'logic' => 'AND'
                ];
                foreach ($f[2] as $v){
                  if (null === $v) {
                    $tmp['conditions'][] = [
                      'field' => $f[0],
                      'operator' => 'isnotnull'
                    ];
                  }
                  else{
                    $tmp['conditions'][] = [
                      'field' => $f[0],
                      'operator' => $f[1],
                      'value' => $v
                    ];
                  }
                }

                $res['conditions'][] = $tmp;
              }
              elseif ($f[2] === null) {
                $tmp['operator'] = $f[2] === '!=' ? 'isnotnull' : 'isnull';
              }
              else{
                $tmp['value'] = $f[2];
              }
            }

            $f = $tmp;
          }

          if (isset($f['field'])) {
            if (!isset($f['operator'])) {
              $f['operator'] = 'eq';
            }

            $res['conditions'][] = $f;
          }
        }
      }

      if ($full) {
        $tmp = $this->_remove_conditions_value($res);
        $res = [
          'hashed' => $tmp['hashed'],
          'values' => $tmp['values'],
          'where' => $res
        ];
      }

      return $res;
    }

    return false;
  }


  public function extractFields(array $cfg, array $conditions, array &$res = null)
  {
    if (null === $res) {
      $res = [];
    }

    if (isset($conditions['conditions'])) {
      $conditions = $conditions['conditions'];
    }

    foreach ($conditions as $c) {
      if (isset($c['conditions'])) {
        $this->extractFields($cfg, $c['conditions'], $res);
      }
      else {
        if (isset($c['field'], $cfg['available_fields'][$c['field']])) {
          $res[] = $cfg['available_fields'][$c['field']] ? $this->cfn($c['field'], $cfg['available_fields'][$c['field']]) : $c['field'];
        }

        if (isset($c['exp'])) {
          $res[] = $cfg['available_fields'][$c['exp']] ? $this->cfn($c['exp'], $cfg['available_fields'][$c['exp']]) : $c['exp'];
        }
      }
    }

    return $res;
  }


  /**
   * Retrieve an array of specific filters among the existing ones.
   *
   * @param array $cfg
   * @param $field
   * @param null  $operator
   * @return array|null
   */
  public function filterFilters(array $cfg, $field, $operator = null): ?array
  {
    if (isset($cfg['filters'])) {
      $f = function ($cond, &$res = []) use (&$f, $field, $operator) {
        foreach ($cond as $c){
          if (isset($c['conditions'])) {
            $f($c['conditions'], $res);
          }
          elseif (($c['field'] === $field) && (!$operator || ($operator === $c['operator']))) {
            $res[] = $c;
          }
        }

        return $res;
      };
      return isset($cfg['filters']['conditions']) ? $f($cfg['filters']['conditions']) : [];
    }

    return null;
  }


  /**
   * @param array $where
   * @param array $cfg
   * @return array
   */
  public function getValuesDesc(array $where, array $cfg, &$others = []): array
  {
    if (!empty($where['conditions'])) {
      foreach ($where['conditions'] as &$f){
        if (isset($f['logic'], $f['conditions']) && \is_array($f['conditions'])) {
          $this->getValuesDesc($f, $cfg, $others);
        }
        elseif (array_key_exists('value', $f)) {
          $desc = [
            'primary' => false,
            'type' => null,
            'maxlength' => null,
            'operator' => $f['operator'] ?? null
          ];
          if (isset($cfg['models'], $f['field'], $cfg['available_fields'][$f['field']])) {
            $t = $cfg['available_fields'][$f['field']];
            if (isset($cfg['models'], $f['field'], $cfg['tables_full'][$t], $cfg['models'][$cfg['tables_full'][$t]])
                && ($model = $cfg['models'][$cfg['tables_full'][$t]])
                && ($fname = $this->csn($f['field']))
            ) {
              if (!empty($model['fields'][$fname]['type'])) {
                $desc = [
                  'type' => $model['fields'][$fname]['type'],
                  'maxlength' => $model['fields'][$fname]['maxlength'] ?? null,
                  'operator' => $f['operator'] ?? null
                ];
              }
              // Fixing filters using alias
              elseif (isset($cfg['fields'][$f['field']])
                  && ($fname = $this->csn($cfg['fields'][$f['field']]))
                  && !empty($model['fields'][$fname]['type'])
              ) {
                $desc = [
                  'type' => $model[$fname]['type'],
                  'maxlength' => $model[$fname]['maxlength'] ?? null,
                  'operator' => $f['operator'] ?? null
                ];
              }

              if (!empty($desc['type'])
                  && isset($model['keys']['PRIMARY'])
                  && (count($model['keys']['PRIMARY']['columns']) === 1)
                  && ($model['keys']['PRIMARY']['columns'][0] === $fname)
              ) {
                $desc['primary'] = true;
              }
            }
          }

          $others[] = $desc;
        }
      }
    }

    return $others;
  }


  public function arrangeConditions(array &$conditions, array $cfg): void
  {
    if (!empty($cfg['available_fields']) && isset($conditions['conditions'])) {
      foreach ($conditions['conditions'] as &$c){
        if (array_key_exists('conditions', $c) && \is_array($c['conditions'])) {
          $this->arrangeConditions($c, $cfg);
        }
        elseif (isset($c['field']) && empty($cfg['available_fields'][$c['field']]) && !$this->isColFullName($c['field'])) {
          foreach ($cfg['tables'] as $t => $o){
            if (isset($cfg['available_fields'][$this->colFullName($c['field'], $t)])) {
              $c['field'] = $this->colFullName($c['field'], $t);
              break;
            }
          }
        }
      }
    }
  }


  /**
   * @param array $cfg
   * @return array|null
   */
  public function reprocessCfg(array $cfg): ?array
  {
    unset($cfg['bbn_db_processed']);
    unset($cfg['bbn_db_treated']);
    unset($this->cfgs[$cfg['hash']]);
    $tmp = $this->processCfg($cfg, true);
    if (!empty($cfg['values']) && (count($cfg['values']) === count($tmp['values']))) {
      $tmp = array_merge($tmp, ['values' => $cfg['values']]);
    }

    return $tmp;
  }


  /**
   *
   * @param array $args
   * @return array|null
   */
  public function processCfg(array $args, $force = false): ?array
  {
    // Avoid confusion when
    while (\is_array($args) && isset($args[0]) && \is_array($args[0])){
      $args = $args[0];
    }

    if (\is_array($args) && !empty($args['bbn_db_processed'])) {
      return $args;
    }

    if (empty($args['bbn_db_treated'])) {
      $args = $this->_treat_arguments($args);
    }

    //var_dump("UPD0", $args);
    if (isset($args['hash'])) {
      if (isset($this->cfgs[$args['hash']])) {
        return array_merge(
          $this->cfgs[$args['hash']], [
          'values' => $args['values'] ?: [],
          'where' => $args['where'] ?: [],
          'filters' => $args['filters'] ?: []
          ]
        );
      }

      /** @var array $tables_full  Each of the tables' full name. */
      $tables_full = [];
      $res         = array_merge(
        $args, [
        'tables' => [],
        'values_desc' => [],
        'bbn_db_processed' => true,
        'available_fields' => [],
        'generate_id' => false
        ]
      );
      $models      = [];

      foreach ($args['tables'] as $key => $tab) {
        if (empty($tab)) {
          $this->log(\debug_backtrace());
          throw new \Exception("$key is not defined");
        }

        $tfn = $this->tfn($tab);

        // 2 tables in the same statement can't have the same idx
        $idx = \is_string($key) ? $key : $tfn;
        // Error if they do
        if (isset($tables_full[$idx])) {
          $this->error('You cannot use twice the same table with the same alias'.PHP_EOL.X::getDump($args['tables']));
          return null;
        }

        $tables_full[$idx]   = $tfn;
        $res['tables'][$idx] = $tfn;
        if (!isset($models[$tfn]) && ($model = $this->modelize($tfn))) {
          $models[$tfn] = $model;
        }
      }

      if ((\count($res['tables']) === 1)
          && ($tfn = array_values($res['tables'])[0])
          && isset($models[$tfn]['keys']['PRIMARY'])
          && (\count($models[$tfn]['keys']['PRIMARY']['columns']) === 1)
          && ($res['primary'] = $models[$tfn]['keys']['PRIMARY']['columns'][0])
      ) {
        $p                     = $models[$tfn]['fields'][$res['primary']];
        $res['auto_increment'] = isset($p['extra']) && ($p['extra'] === 'auto_increment');
        $res['primary_length'] = $p['maxlength'];
        $res['primary_type']   = $p['type'];
        if (($res['kind'] === 'INSERT')
            && !$res['auto_increment']
            && !\in_array($this->csn($res['primary']), $res['fields'], true)
        ) {
          $res['generate_id'] = true;
          $res['fields'][]    = $res['primary'];
        }
      }

      foreach ($args['join'] as $key => $join){
        if (!empty($join['table']) && !empty($join['on'])) {
          $tfn = $this->tfn($join['table']);
          if (!isset($models[$tfn]) && ($model = $this->modelize($tfn))) {
            $models[$tfn] = $model;
          }

          $idx               = $join['alias'] ?? $tfn;
          $tables_full[$idx] = $tfn;
        }
        else{
          $this->error('Error! The join array must have on and table defined'.PHP_EOL.X::getDump($join));
        }
      }

      foreach ($tables_full as $idx => $tfn){
        foreach ($models[$tfn]['fields'] as $col => $cfg){
          $res['available_fields'][$this->cfn($col, $idx)] = $idx;
          $csn                                             = $this->csn($col);
          if (!isset($res['available_fields'][$csn])) {
            /*
            $res['available_fields'][$csn] = false;
            }
            else{
            */
            $res['available_fields'][$csn] = $idx;
          }
        }
      }

      foreach ($res['fields'] as $idx => &$col){
        if (strpos($col, '(')
            || strpos($col, '-')
            || strpos($col, "+")
            || strpos($col, '*')
            || strpos($col, "/")
            /*
          strpos($col, '->"$.')  ||
          strpos($col, "->'$.") ||
          strpos($col, '->>"$.')  ||
          strpos($col, "->>'$.") ||
          */
            // string as value
            || preg_match('/^[\\\'\"]{1}[^\\\'\"]*[\\\'\"]{1}$/', $col)
        ) {
          $res['available_fields'][$col] = false;
        }

        if (\is_string($idx)) {
          if (!isset($res['available_fields'][$col])) {
            //$this->log($res);
            $this->error("Impossible to find the column $col");
            $this->error(json_encode($res['available_fields'], JSON_PRETTY_PRINT));
            return null;
          }

          $res['available_fields'][$idx] = $res['available_fields'][$col];
        }
      }

      // From here the available fields are defined
      if (!empty($res['filters'])) {
        $this->arrangeConditions($res['filters'], $res);
      }

      unset($col);
      $res['models']      = $models;
      $res['tables_full'] = $tables_full;
      switch ($res['kind']){
        case 'SELECT':
          if (empty($res['fields'])) {
            foreach (array_keys($res['available_fields']) as $f){
              if ($this->isColFullName($f)) {
                $res['fields'][] = $f;
              }
            }
          }

          //X::log($res, 'sql');
          if ($res['select_st'] = $this->language->getSelect($res)) {
            $res['sql'] = $res['select_st'];
          }
          break;
        case 'INSERT':
          $res = $this->removeVirtual($res);
          if ($res['insert_st'] = $this->language->getInsert($res)) {
            $res['sql'] = $res['insert_st'];
          }

          //var_dump($res);
          break;
        case 'UPDATE':
          $res = $this->removeVirtual($res);
          if ($res['update_st'] = $this->getUpdate($res)) {
            $res['sql'] = $res['update_st'];
          }
          break;
        case 'DELETE':
          if ($res['delete_st'] = $this->getDelete($res)) {
            $res['sql'] = $res['delete_st'];
          }
          break;
      }

      $res['join_st']   = $this->language->getJoin($res);
      $res['where_st']  = $this->language->getWhere($res);
      $res['group_st']  = $this->language->getGroupBy($res);
      $res['having_st'] = $this->language->getHaving($res);
      $cls              = '\\bbn\\Db\\languages\\'.$this->engine;
      if (empty($res['count'])
          && (count($res['fields']) === 1)
          && ($cls::isAggregateFunction(reset($res['fields'])))
      ) {
        $res['order_st'] = '';
        $res['limit_st'] = '';
      }
      else {
        $res['order_st'] = $res['count'] ? '' : $this->language->getOrder($res);
        $res['limit_st'] = $res['count'] ? '' : $this->language->getLimit($res);
      }

      if (!empty($res['sql'])) {
        $res['sql'] .= $res['join_st'].$res['where_st'].$res['group_st'];
        if ($res['count'] && $res['group_by']) {
          $res['sql'] .= ') AS t '.PHP_EOL;
        }

        $res['sql']           .= $res['having_st'].$res['order_st'].$res['limit_st'];
        $res['statement_hash'] = $this->_make_hash($res['sql']);

        foreach ($res['join'] as $r){
          $this->getValuesDesc($r['on'], $res, $res['values_desc']);
        }

        if (($res['kind'] === 'INSERT') || ($res['kind'] === 'UPDATE')) {
          foreach ($res['fields'] as $name){
            $desc = [];
            if (isset($res['models'], $res['available_fields'][$name])) {
              $t = $res['available_fields'][$name];
              if (isset($tables_full[$t])
                  && ($model = $res['models'][$tables_full[$t]]['fields'])
                  && ($fname = $this->csn($name))
                  && !empty($model[$fname]['type'])
              ) {
                $desc['type']      = $model[$fname]['type'];
                $desc['maxlength'] = $model[$fname]['maxlength'] ?? null;
              }
            }

            $res['values_desc'][] = $desc;
          }
        }

        $this->getValuesDesc($res['filters'], $res, $res['values_desc']);
        $this->getValuesDesc($res['having'], $res, $res['values_desc']);
        $this->cfgs[$res['hash']] = $res;
      }

      return $res;
    }

    $this->error('Impossible to process the config (no hash)'.PHP_EOL.print_r($args, true));
    return null;
  }


  public function removeVirtual(array $res): array
  {
    if (isset($res['fields'])) {
      $to_remove = [];
      foreach ($res['fields'] as $i => $f){
        if (!empty($res['available_fields'][$f])
            && isset($res['models'][$res['available_fields'][$f]]['fields'][$this->csn($f)])
            && $res['models'][$res['available_fields'][$f]]['fields'][$this->csn($f)]['virtual']
        ) {
          array_unshift($to_remove, $i);
        }
      }

      foreach ($to_remove as $i) {
        array_splice($res['fields'], $i, 1);
        array_splice($res['values'], $i, 1);
      }
    }

    return $res;
  }


  /**
   * Set an error and acts appropriately based oon the error mode
   *
   * @param $e
   * @return void
   */
  public function error($e): void
  {
    $this->_has_error = true;
    self::_set_has_error_all();
    $msg = [
      self::LINE,
      self::getLogLine('ERROR DB!'),
      self::LINE
    ];
    if (\is_string($e)) {
      $msg[] = self::getLogLine('USER MESSAGE');
      $msg[] = $e;
    }
    elseif (method_exists($e, 'getMessage')) {
      $msg[] = self::getLogLine('DB MESSAGE');
      $msg[] = $e->getMessage();
    }

    $this->last_error = end($msg);
    $msg[]            = self::getLogLine('QUERY');
    $msg[]            = $this->last();
    if ($this->last_real_params['values']) {
      $msg[] = self::getLogLine('VALUES');
      foreach ($this->last_real_params['values'] as $v){
        if ($v === null) {
          $msg[] = 'NULL';
        }
        elseif (\is_bool($v)) {
          $msg[] = $v ? 'TRUE' : 'FALSE';
        }
        elseif (\is_string($v)) {
          $msg[] = Str::isBuid($v) ? bin2hex($v) : Str::cut($v, 30);
        }
        else{
          $msg[] = $v;
        }
      }
    }

    $msg[] = self::getLogLine('BACKTRACE');
    $dbt   = array_reverse(debug_backtrace());
    array_walk(
      $dbt, function ($a, $i) use (&$msg) {
        $msg[] = str_repeat(' ', $i).($i ? '->' : '')."{$a['function']}  (".basename(dirname($a['file'])).'/'.basename($a['file']).":{$a['line']})";
      }
    );
    $this->log(implode(PHP_EOL, $msg));
    if ($this->on_error === self::E_DIE) {
      die(\defined('BBN_IS_DEV') && BBN_IS_DEV ? '<pre>'.PHP_EOL.implode(PHP_EOL, $msg).PHP_EOL.'</pre>' : 'Database error');
    }
  }


  /**
   * Checks if the database is ready to process a query.
   *
   * ```php
   * X::dump($db->check());
   * // (bool)
   * ```
   * @return bool
   */
  public function check(): bool
  {
    if ($this->current !== null) {
      // if $on_error is set to E_CONTINUE returns true
      if ($this->on_error === self::E_CONTINUE) {
        return true;
      }

      // If any connection has an error with mode E_STOP_ALL
      if (self::$_has_error_all && ($this->on_error !== self::E_STOP_ALL)) {
        return false;
      }

      // If this connection has an error with mode E_STOP
      if ($this->_has_error && ($this->on_error !== self::E_STOP)) {
        return false;
      }

      return true;
    }

    return false;
  }


  /**
   * Writes in data/logs/db.log.
   *
   * ```php
   * $db->$db->log('test');
   * ```
   * @param mixed $st
   * @return db
   */
  public function log($st): self
  {
    $args = \func_get_args();
    foreach ($args as $a){
      X::log($a, 'db');
    }

    return $this;
  }


  /**
   * Sets the error mode.
   *
   * ```php
   * $db->setErrorMode('continue'|'die'|'stop_all|'stop');
   * // (void)
   * ```
   *
   * @param string $mode The error mode: "continue", "die", "stop", "stop_all".
   * @return db
   */
  public function setErrorMode($mode): self
  {
    $this->on_error = $mode;
    return $this;
  }


  /**
   * Gets the error mode.
   *
   * ```php
   * X::dump($db->getErrorMode());
   * // (string) stop_all
   * ```
   * @return string
   */
  public function getErrorMode(): string
  {
    return $this->on_error;
  }


  /**
   * Deletes a specific item from the cache.
   *
   * ```php
   * X::dump($db->clearCache('db_example','tables'));
   * // (db)
   * ```
   *
   * @param string $item 'db_name' or 'table_name'
   * @param string $mode 'columns','tables' or 'databases'
   * @return self
   */
  public function clearCache($item, $mode): self
  {
    $cache_name = $this->_cache_name($item, $mode);
    if ($this->cacheHas($cache_name)) {
      $this->cacheDelete($cache_name);
    }

    return $this;
  }


  /**
   * Clears the cache.
   *
   * ```php
   * X::dump($db->clearAllCache());
   * // (db)
   * ```
   *
   * @return self
   */
  public function clearAllCache(): self
  {
    $this->cacheDeleteAll();
    return $this;
  }


  /**
   * Stops fancy stuff.
   *
   * ```php
   *  $db->stopFancyStuff();
   * // (void)
   * ```
   *
   * @return db
   */
  public function stopFancyStuff(): self
  {
    $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [\PDOStatement::class]);
    $this->_fancy = false;
    return $this;
  }


  /**
   * Starts fancy stuff.
   *
   * ```php
   * $db->startFancyStuff();
   * // (void)
   * ```
   * @return db
   */
  public function startFancyStuff(): self
  {
    $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [Db\Query::class, [$this]]);
    $this->_fancy = 1;
    return $this;
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


  /****************************************************************
   *                                                              *
   *                                                              *
   *                          TRIGGERS                            *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Enable the triggers' functions
   *
   * @return db
   */
  public function enableTrigger(): self
  {
    $this->_triggers_disabled = false;
    return $this;
  }


  /**
   * Disable the triggers' functions
   *
   * @return db
   */
  public function disableTrigger(): self
  {
    $this->_triggers_disabled = true;
    return $this;
  }


  public function isTriggerEnabled(): bool
  {
    return !$this->_triggers_disabled;
  }


  public function isTriggerDisabled(): bool
  {
    return $this->_triggers_disabled;
  }


  /**
   * Apply a function each time the methods $kind are used
   *
   * @param callable          $function
   * @param array|string      $kind     select|insert|update|delete
   * @param array|string      $moment   before|after
   * @param null|string|array $tables   database's table(s) name(s)
   * @return db
   */
  public function setTrigger(callable $function, $kind = null, $moment = null, $tables = '*' ): self
  {
    $kinds   = ['SELECT', 'INSERT', 'UPDATE', 'DELETE'];
    $moments = ['before', 'after'];
    if (empty($kind)) {
      $kind = $kinds;
    }
    elseif (!\is_array($kind)) {
      $kind = (array)strtoupper($kind);
    }
    else{
      $kind = array_map('strtoupper', $kind);
    }

    if (empty($moment)) {
      $moment = $moments;
    }
    else {
      $moment = !\is_array($moment) ? (array)strtolower($moment) : array_map('strtolower', $moment);
    }

    foreach ($kind as $k){
      if (\in_array($k, $kinds, true)) {
        foreach ($moment as $m){
          if (array_key_exists($m, $this->_triggers[$k]) && \in_array($m, $moments, true)) {
            if ($tables === '*') {
              $tables = $this->getTables();
            }
            elseif (Str::checkName($tables)) {
              $tables = [$tables];
            }

            if (\is_array($tables)) {
              foreach ($tables as $table){
                $t = $this->tfn($table);
                if (!isset($this->_triggers[$k][$m][$t])) {
                  $this->_triggers[$k][$m][$t] = [];
                }

                $this->_triggers[$k][$m][$t][] = $function;
              }
            }
          }
        }
      }
    }

    return $this;
  }


  /**
   * @return array
   */
  public function getTriggers(): array
  {
    return $this->_triggers;
  }


  /****************************************************************
   *                                                              *
   *                                                              *
   *                       STRUCTURE HELPERS                      *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * @param $tables
   * @return array
   */
  public function getFieldsList($tables): array
  {
    $res = [];
    if (!\is_array($tables)) {
      $tables = [$tables];
    }

    foreach ($tables as $t){
      if (!($model = $this->getColumns($t))) {
        $this->error('Impossible to find the table '.$t);
        die('Impossible to find the table '.$t);
      }

      foreach (array_keys($model) as $f){
        $res[] = $this->cfn($f, $t);
      }
    }

    return $res;
  }


  /**
   * Return an array with tables and fields related to the searched foreign key.
   *
   * ```php
   * X::dump($db->getForeignKeys('id', 'table_users', 'db_example'));
   * // (Array)
   * ```
   *
   * @param string $col   The column's name
   * @param string $table The table's name
   * @param string $db    The database name if different from the current one
   * @return array with tables and fields related to the searched foreign key
   */
  public function getForeignKeys(string $col, string $table, string $db = null): array
  {
    if (!$db) {
      $db = $this->current;
    }

    $res   = [];
    $model = $this->modelize();
    foreach ($model as $tn => $m){
      foreach ($m['keys'] as $k => $t){
        if (($t['ref_table'] === $table)
            && ($t['ref_column'] === $col)
            && ($t['ref_db'] === $db)
            && (\count($t['columns']) === 1)
        ) {
          if (!isset($res[$tn])) {
            $res[$tn] = [$t['columns'][0]];
          }
          else{
            $res[$tn][] = $t['columns'][0];
          }
        }
      }
    }

    return $res;
  }


  /**
   * Return true if in the table there are fields with auto-increment.
   * Working only on mysql.
   *
   * ```php
   * X::dump($db->hasIdIncrement('table_users'));
   * // (bool) 1
   * ```
   *
   * @param string $table The table's name
   * @return bool
   */
  public function hasIdIncrement($table): bool
  {
    return ($model = $this->modelize($table)) &&
      isset($model['keys']['PRIMARY']) &&
      (\count($model['keys']['PRIMARY']['columns']) === 1) &&
      ($model['fields'][$model['keys']['PRIMARY']['columns'][0]]['extra'] === 'auto_increment');
  }


  /**
   * Return the table's structure as an indexed array.
   *
   * ```php
   * X::dump($db->modelize("table_users"));
   * // (array) [keys] => Array ( [PRIMARY] => Array ( [columns] => Array ( [0] => userid [1] => userdataid ) [ref_db] => [ref_table] => [ref_column] => [unique] => 1 )     [table_users_userId_userdataId_info] => Array ( [columns] => Array ( [0] => userid [1] => userdataid [2] => info ) [ref_db] => [ref_table] => [ref_column] =>     [unique] => 0 ) ) [cols] => Array ( [userid] => Array ( [0] => PRIMARY [1] => table_users_userId_userdataId_info ) [userdataid] => Array ( [0] => PRIMARY [1] => table_users_userId_userdataId_info ) [info] => Array ( [0] => table_users_userId_userdataId_info ) ) [fields] => Array ( [userid] => Array ( [position] => 1 [null] => 0 [key] => PRI [default] => [extra] => [signed] => 1 [maxlength] => 11 [type] => int ) [userdataid] => Array ( [position] => 2 [null] => 0 [key] => PRI [default] => [extra] => [signed] => 1 [maxlength] => 11 [type] => int ) [info] => Array ( [position] => 3 [null] => 1 [key] => [default] => NULL [extra] => [signed] => 0 [maxlength] => 200 [type] => varchar ) )
   * ```
   *
   * @param null|array|string $table The table's name
   * @param bool              $force If set to true will force the modelization to reperform even if the cache exists
   * @return null|array
   */
  public function modelize($table = null, bool $force = false): ?array
  {
    $r      = [];
    $tables = false;
    if (empty($table) || ($table === '*')) {
      $tables = $this->getTables($this->current);
    }
    elseif (\is_string($table)) {
      $tables = [$table];
    }
    elseif (\is_array($table)) {
      $tables = $table;
    }

    if (\is_array($tables)) {
      foreach ($tables as $t) {
        if ($full = $this->tfn($t)) {
          $r[$full] = $this->_get_cache($full, 'columns', $force);
        }
      }

      if (\count($r) === 1) {
        return end($r);
      }

      return $r;
    }

    return null;
  }


  /**
   * @param string $table
   * @param bool   $force
   * @return null|array
   */
  public function fmodelize($table = '', $force = false): ?array
  {
    if ($res = $this->modelize(...\func_get_args())) {
      foreach ($res['fields'] as $n => $f){
        $res['fields'][$n]['name'] = $n;
        $res['fields'][$n]['keys'] = [];
        if (isset($res['cols'][$n])) {
          foreach ($res['cols'][$n] as $key){
            $res['fields'][$n]['keys'][$key] = $res['keys'][$key];
          }
        }
      }

      return $res['fields'];
    }

    return null;
  }


  /**
   * find_references
   *
   * @param $column
   * @param string $db
   * @return array|bool
   *
   */
  public function findReferences($column, $db = ''): array
  {
    $changed = false;
    if ($db && ($db !== $this->current)) {
      $changed = $this->current;
      $this->change($db);
    }

    $column = $this->cfn($column);
    $bits   = explode('.', $column);
    if (\count($bits) === 2) {
      array_unshift($bits, $this->current);
    }

    if (\count($bits) !== 3) {
      return false;
    }

    $refs   = [];
    $schema = $this->modelize();
    $test   = function ($key) use ($bits) {
      return ($key['ref_db'] === $bits[0]) && ($key['ref_table'] === $bits[1]) && ($key['ref_column'] === $bits[2]);
    };
    foreach ($schema as $table => $cfg){
      foreach ($cfg['keys'] as $k){
        if ($test($k)) {
          $refs[] = $table.'.'.$k['columns'][0];
        }
      }
    }

    if ($changed) {
      $this->change($changed);
    }

    return $refs;
  }


  /**
   * find_relations
   *
   * @param $column
   * @param string $db
   * @return array|bool
   */
  public function findRelations($column, $db = ''): ?array
  {
    $changed = false;
    if ($db && ($db !== $this->current)) {
      $changed = $this->current;
      $this->change($db);
    }

    $column = $this->cfn($column);
    $bits   = explode('.', $column);
    if (\count($bits) === 2) {
      array_unshift($bits, $db ?: $this->current);
    }

    if (\count($bits) !== 3) {
      return null;
    }

    $table  = $bits[1];
    $refs   = [];
    $schema = $this->modelize();
    $test   = function ($key) use ($bits) {
      return ($key['ref_db'] === $bits[0]) && ($key['ref_table'] === $bits[1]) && ($key['ref_column'] === $bits[2]);
    };
    foreach ($schema as $tf => $cfg){
      $t = $this->tsn($tf);
      if ($t !== $table) {
        foreach ($cfg['keys'] as $k){
          if ($test($k)) {
            foreach ($cfg['keys'] as $k2){
              // Is not the same table
              if (!$test($k2)
                  // Has a reference
                  && !empty($k2['ref_column'])
                  // and refers to a single column
                  && (\count($k['columns']) === 1)
                  // A unique reference
                  && (\count($k2['columns']) === 1)
                  // To a table with a primary
                  && isset($schema[$this->tfn($k2['ref_table'])]['cols'][$k2['ref_column']])
                  // which is a sole column
                  && (\count($schema[$this->tfn($k2['ref_table'])]['cols'][$k2['ref_column']]) === 1)
                  // We retrieve the key name
                  && ($key_name = $schema[$this->tfn($k2['ref_table'])]['cols'][$k2['ref_column']][0])
                  // which is unique
                  && !empty($schema[$this->tfn($k2['ref_table'])]['keys'][$key_name]['unique'])
              ) {
                if (!isset($refs[$t])) {
                  $refs[$t] = ['column' => $k['columns'][0], 'refs' => []];
                }

                $refs[$t]['refs'][$k2['columns'][0]] = $k2['ref_table'].'.'.$k2['ref_column'];
              }
            }
          }
        }
      }
    }

    if ($changed) {
      $this->change($changed);
    }

    return $refs;
  }


  /**
   * Return primary keys of a table as a numeric array.
   *
   * ```php
   * X::dump($db-> get_primary('table_users'));
   * // (array) ["id"]
   * ```
   *
   * @param string $table The table's name
   * @return array
   */
  public function getPrimary($table): array
  {
    if (($keys = $this->getKeys($table)) && isset($keys['keys']['PRIMARY'])) {
      return $keys['keys']['PRIMARY']['columns'];
    }

    return [];
  }


  /**
   * Return the unique primary key of the given table.
   *
   * ```php
   * X::dump($db->getUniquePrimary('table_users'));
   * // (string) id
   * ```
   *
   * @param string $table The table's name
   * @return null|string
   */
  public function getUniquePrimary($table): ?string
  {
    if (($keys = $this->getKeys($table))
        && isset($keys['keys']['PRIMARY'])
        && (\count($keys['keys']['PRIMARY']['columns']) === 1)
    ) {
      return $keys['keys']['PRIMARY']['columns'][0];
    }

    return null;
  }


  /**
   * Return the unique keys of a table as a numeric array.
   *
   * ```php
   * X::dump($db->getUniqueKeys('table_users'));
   * // (array) ["userid", "userdataid"]
   * ```
   *
   * @param string $table The table's name
   * @return array
   */
  public function getUniqueKeys($table): array
  {
    $fields = [[]];
    if ($ks = $this->getKeys($table)) {
      foreach ($ks['keys'] as $k){
        if ($k['unique']) {
          return $k['columns'];
        }
      }
    }

    return [];
  }


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
  public function setLastInsertId($id=''): self
  {
    if ($id === '') {
      if ($this->id_just_inserted) {
        $id                     = $this->id_just_inserted;
        $this->id_just_inserted = null;
      }
      else{
        $id = $this->lastInsertId();
        if (\is_string($id) && Str::isInteger($id) && ((int)$id != PHP_INT_MAX)) {
          $id = (int)$id;
        }
      }
    }
    else{
      $this->id_just_inserted = $id;
    }

    $this->last_insert_id = $id;
    return $this;
  }


  /**
   * Parses a SQL query and return an array.
   *
   * @param string $statement
   * @return null|array
   */
  public function parseQuery(string $statement): ?array
  {
    if ($this->_parser === null) {
      $this->_parser = new \PHPSQLParser\PHPSQLParser();
    }

    $done = false;
    try {
      $r    = $this->_parser->parse($statement);
      $done = 1;
    }
    catch (\Exception $e){
      $this->log('Error while parsing the query '.$statement);
    }

    if ($done) {
      if (!$r || !count($r)) {
        $this->log('Impossible to parse the query '.$statement);
        return null;
      }

      if (isset($r['BRACKET']) && (\count($r) === 1)) {
        /** @todo Is it impossible to parse queries with brackets ? */
        //throw new \Exception('Bracket in the query '.$statement);
        return null;
      }

      return $r;
    }

    return null;
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
    return $this->last_query;
  }


  /**
   * Return the last config for this connection.
   *
   * ```php
   * X::dump($db->getLastCfg());
   * // (array) INSERT INTO `db_example.table_user` (`name`) VALUES (?)
   * ```
   *
   * @return string
   */
  public function getLastCfg(): ?array
  {
    return $this->last_cfg;
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
    if ($this->last_insert_id) {
      return Str::isBuid($this->last_insert_id) ? bin2hex($this->last_insert_id) : $this->last_insert_id;
    }

    return false;
  }


  /**
   * Deletes all the queries recorded and returns their (ex) number.
   *
   * @return int
   */
  public function flush(): int
  {
    $num                = \count($this->queries);
    $this->queries      = [];
    $this->list_queries = [];
    return $num;
  }


  /**
   * Executes the original PDO query function
   *
   * ```php
   * X::dump($db->rawQuery());
   * // (bool)
   * ```
   * @return bool|\PDOStatement
   */
  public function rawQuery()
  {
    return parent::query(...\func_get_args());
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
          if ( strpos($tab['fields'][$id_field]['type'], 'char') !== false ){
            $id = substr(md5('bbn'.$id), 0, random_int(1, 10 ** $maxlength));
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


  public function rselectRandom($table, array $fields = [], array $where = []):? array
  {
    if ($this->check() && ($num = $this->count($table, $where))) {
      $args = $this->_add_kind($this->_set_start($this->_set_limit_1(\func_get_args()), random_int(0, $num - 1)));
      if ($r = $this->_exec(...$args)) {
        return $r->getRow();
      }
    }

    return null;
  }


  public function selectRandom($table, array $fields = [], array $where = []):? \stdClass
  {
    if ($this->check() && ($num = $this->count($table, $where))) {
      $args = $this->_add_kind($this->_set_start($this->_set_limit_1(\func_get_args()), random_int(0, $num - 1)));
      if ($r = $this->_exec(...$args)) {
        return $r->getObj();
      }
    }

    return null;
  }


  /**
   * Returns a random value fitting the requested column's type
   *
   * @todo This great function has to be done properly
   * @param $col
   * @param $table
   * @return mixed
   */
  public function randomValue($col, $table)
  {
    $val = null;
    if (($tab = $this->modelize($table)) && isset($tab['fields'][$col])) {
      foreach ($tab['keys'] as $key => $cfg){
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


  /**
   * @return int
   */
  public function countQueries(): int
  {
    return \count($this->queries);
  }


  /****************************************************************
   *                                                              *
   *                                                              *
   *                       QUERY HELPERS                          *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Executes the given query with given vars, and extracts the first cell's result.
   *
   * ```php
   * X::dump($db->getOne("SELECT name FROM table_users WHERE id>?", 138));
   * // (string) John
   * ```
   *
   * @param string query
   * @param mixed values
   * @return mixed
   */
  public function getOne()
  {
    /** @var Db\Query $r */
    if ($r = $this->query(...\func_get_args())) {
      return $r->fetchColumn(0);
    }

    return false;
  }


  /**
   * Execute the given query with given vars, and extract the first cell's result.
   * (similar to {@link get_one()})
   *
   * ```php
   * X::dump($db->getVar("SELECT telephone FROM table_users WHERE id>?", 1));
   * // (int) 123554154
   * ```
   *
   * @param string query
   * @param mixed values
   * @return mixed
   */
  public function getVar()
  {
    return $this->getOne(...\func_get_args());
  }


  /**
   * Return an array indexed on the first field of the request.
   * The value will be an array if the request has more than two fields.
   *
   * ```php
   * X::dump($db->getKeyVal("SELECT name,id_group FROM table_users"));
   * /*
   * (array)[
   *      "John" => 1,
   *      "Michael" => 1,
   *      "Barbara" => 1
   *        ]
   *
   * X::dump($db->getKeyVal("SELECT name, surname, id FROM table_users WHERE id > 2 "));
   * /*
   * (array)[
   *         "John" => [
   *          "surname" => "Brown",
   *          "id" => 3
   *         ],
   *         "Michael" => [
   *          "surname" => "Smith",
   *          "id" => 4
   *         ]
   *        ]
   * ```
   *
   * @param string query
   * @param mixed values
   * @return null|array
   */
  public function getKeyVal(): ?array
  {
    if ($r = $this->query(...\func_get_args())) {
      if ($rows = $r->getRows()) {
        return X::indexByFirstVal($rows);
      }

      return [];
    }

    return null;
  }


  /**
   * Return an array with the values of single field resulting from the query.
   *
   * ```php
   * X::dump($db->getColArray("SELECT id FROM table_users"));
   * /*
   * (array)[1, 2, 3, 4]
   * ```
   *
   * @param string query
   * @param mixed values
   * @return array
   */
  public function getColArray(): array
  {
    if ($r = $this->getByColumns(...\func_get_args())) {
      return array_values(current($r));
    }

    return [];
  }


  /****************************************************************
   *                                                              *
   *                                                              *
   *                 READ HELPERS WITH TRIGGERS                   *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Returns the first row resulting from the query as an object.
   *
   * ```php
   * X::dump($db->select('table_users', ['name', 'surname'], [['id','>','2']]));
   * /*
   * (object){
   *   "name": "John",
   *   "surname": "Smith",
   * }
   * ```
   *
   * @param string|array    $table  The table's name or a configuration array
   * @param string|array    $fields The fields' name
   * @param array           $where  The "where" condition
   * @param array | boolean $order  The "order" condition, default: false
   * @param int             $start  The "start" condition, default: 0
   * @return null|\stdClass
   */
  public function select($table, $fields = [], array $where = [], array $order = [], int $start = 0): ?\stdClass
  {
    $args = $this->_add_kind($this->_set_limit_1(\func_get_args()));
    if ($r = $this->_exec(...$args)) {
      if (!is_object($r)) {
        $this->log([$args, $this->processCfg($args)]);
      }
      else{
        return $r->getObject();
      }
    }

    return null;
  }


  /**
   * Return table's rows resulting from the query as an array of objects.
   *
   * ```php
   * X::dump($db->selectAll("tab_users", ["id", "name", "surname"],[["id", ">", 1]], ["id" => "ASC"], 2));
   * /*
   * (array)[
   *        Object stdClass: df {
   *          "id" => 2,
   *          "name" => "John",
   *          "surname" => "Smith",
   *          },
   *        Object stdClass: df {
   *          "id" => 3,
   *          "name" => "Thomas",
   *          "surname" => "Jones",
   *         }
   *        ]
   * ```
   *
   * @param string|array    $table  The table's name or a configuration array
   * @param string|array    $fields The fields' name
   * @param array           $where  The "where" condition
   * @param array | boolean $order  The "order" condition, default: false
   * @param int             $limit  The "limit" condition, default: 0
   * @param int             $start  The "start" condition, default: 0
   * @return null|array
   */
  public function selectAll($table, $fields = [], array $where = [], array $order = [], int $limit = 0, int $start = 0): ?array
  {
    if ($r = $this->_exec(...$this->_add_kind(\func_get_args()))) {
      return $r->getObjects();
    }

    return null;
  }


  /**
   * Return the first row resulting from the query as a numeric array.
   *
   * ```php
   * X::dump($db->iselect("tab_users", ["id", "name", "surname"], [["id", ">", 1]], ["id" => "ASC"], 2));
   * /*
   * (array)[
   *          4,
   *         "Jack",
   *          "Stewart"
   *        ]
   * ```
   *
   * @param string|array    $table  The table's name or a configuration array
   * @param string|array    $fields The fields' name
   * @param array           $where  The "where" condition
   * @param array | boolean $order  The "order" condition, default: false
   * @param int             $start  The "start" condition, default: 0
   * @return array
   */
  public function iselect($table, $fields = [], array $where = [], array $order = [], int $start = 0): ?array
  {
    if ($r = $this->_exec(...$this->_add_kind($this->_set_limit_1(\func_get_args())))) {
      return $r->getIrow();
    }

    return null;
  }


  /**
   * Return the searched rows as an array of numeric arrays.
   *
   * ```php
   * X::dump($db->iselectAll("tab_users", ["id", "name", "surname"], [["id", ">", 1]],["id" => "ASC"],2));
   * /*
   * (array)[
   *          [
   *            2,
   *            "John",
   *            "Smith",
   *          ],
   *          [
   *            3,
   *            "Thomas",
   *            "Jones",
   *          ]
   *        ]
   * ```
   *
   * @param string|array                                          $table  The table's name or a configuration array
   * @param string|array                                          $fields The fields's name
   * @param array                                                 $where  The "where" condition
   * @param array | boolean The "order" condition, default: false
   * @param int                                                   $limit  The "limit" condition, default: 0
   * @param int                                                   $start  The "start" condition, default: 0
   * @return array
   */
  public function iselectAll($table, $fields = [], array $where = [], array $order = [], int $limit = 0, int $start = 0): ?array
  {
    if ($r = $this->_exec(...$this->_add_kind(\func_get_args()))) {
      return $r->getIrows();
    }

    return null;
  }


  /**
   * Return the first row resulting from the query as an indexed array.
   *
   * ```php
   * X::dump($db->rselect("tab_users", ["id", "name", "surname"], ["id", ">", 1], ["id" => "ASC"], 2));
   * /*
   * (array) [
   *          "id" => 4,
   *          "name" => "John",
   *          "surname" => "Smith"
   *         ]
   * ```
   *
   * @param string|array  $table  The table's name or a configuration array
   * @param string|array  $fields The fields' name
   * @param array         $where  The "where" condition
   * @param array|boolean $order  The "order" condition, default: false
   * @param int           $start  The "start" condition, default: 0
   * @return null|array
   */
  public function rselect($table, $fields = [], array $where = [], array $order = [], int $start = 0): ?array
  {
    if ($r = $this->_exec(...$this->_add_kind($this->_set_limit_1(\func_get_args())))) {
      return $r->getRow();
    }

    return null;
  }


  /**
   * Return table's rows as an array of indexed arrays.
   *
   * ```php
   * X::dump($db->rselectAll("tab_users", ["id", "name", "surname"], [["id", ">", 1]], ["id" => "ASC"], 2));
   * /*
   * (array) [
   *          [
   *          "id" => 2,
   *          "name" => "John",
   *          "surname" => "Smith",
   *          ],
   *          [
   *          "id" => 3,
   *          "name" => "Thomas",
   *          "surname" => "Jones",
   *          ]
   *        ]
   * ```
   *
   * @param string|array    $table  The table's name or a configuration array
   * @param string|array    $fields The fields' name
   * @param array           $where  The "where" condition
   * @param array | boolean $order  condition, default: false
   * @param int             $limit  The "limit" condition, default: 0
   * @param int             $start  The "start" condition, default: 0
   * @return null|array
   */
  public function rselectAll($table, $fields = [], array $where = [], array $order = [], $limit = 0, $start = 0): ?array
  {
    if ($r = $this->_exec(...$this->_add_kind(\func_get_args()))) {
      if (method_exists($r, 'getRows')) {
        return $r->getRows();
      }

      $this->log('ERROR IN RSELECT_ALL', $r);
    }

    return [];
  }


  /**
   * Return a single value
   *
   * ```php
   * X::dump($db->selectOne("tab_users", "name", [["id", ">", 1]], ["id" => "DESC"], 2));
   *  (string) 'Michael'
   * ```
   *
   * @param string|array    $table The table's name or a configuration array
   * @param string          $field The field's name
   * @param array           $where The "where" condition
   * @param array | boolean $order The "order" condition, default: false
   * @param int             $start The "start" condition, default: 0
   * @return mixed
   */
  public function selectOne($table, $field = null, array $where = [], array $order = [], int $start = 0)
  {
    if ($r = $this->_exec(...$this->_add_kind($this->_set_limit_1(\func_get_args())))) {
      if (method_exists($r, 'getIrow')) {
        return ($a = $r->getIrow()) ? $a[0] : false;
      }

      $this->log('ERROR IN SELECT_ONE', $this->getLastCfg(), $r, $this->_add_kind($this->_set_limit_1(\func_get_args())));
    }

    return false;
  }


  public function selectUnion(array $union, array $fields = [], array $where = [], array $order = [], int $start = 0):? array
  {
    $cfgs = [];
    $sql  = 'SELECT ';
    if (empty($fields)) {
      $sql .= '* ';
    }
    else{
      foreach ($fields as $i => $f){
        if ($i) {
          $sql .= ', ';
        }

        $sql .= $this->csn($f, true);
      }
    }

    $sql .= ' FROM (('.PHP_EOL;
    $vals = [];
    $i    = 0;
    foreach ($union as $u){
      $cfg = $this->processCfg($this->_add_kind([$u]));
      if ($cfg && $cfg['sql']) {
        /** @todo From here needs to analyze the where array to the light of the tables' config */
        if (!empty($where)) {
          if (empty($fields)) {
            $fields = $cfg['fields'];
          }

          foreach ($fields as $k => $f){
            if (isset($cfg['available_fields'][$f])) {
              if ($cfg['available_fields'][$f] && ($t = $cfg['models'][$cfg['available_fields'][$f]])
              ) {
                throw new \Exception("Impossible to create the where in union for the following request: ".PHP_EOL.$cfg['sql']);
                //die(var_dump($t['fields'][$cfg['fields'][$f] ?? $this->csn($f)]));
              }
            }
          }
        }

        if ($i) {
          $sql .= PHP_EOL.') UNION ('.PHP_EOL;
        }

        $sql .= $cfg['sql'];
        foreach ($cfg['values'] as $v){
          $vals[] = $v;
        }

        $i++;
      }
    }

    $sql .= PHP_EOL.')) AS t';
    return $this->getRows($sql, ...$vals);
    //echo nl2br($sql);
    return [];
  }


  /**
   * Return the number of records in the table corresponding to the $where condition (non mandatory).
   *
   * ```php
   * X::dump($db->count('table_users', ['name' => 'John']));
   * // (int) 2
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param array        $where The "where" condition
   * @return int
   */
  public function count($table, array $where = []): ?int
  {
    $args          = \is_array($table) && (isset($table['tables']) || isset($table['table'])) ? $table : [
      'tables' => [$table],
      'where' => $where
    ];
    $args['count'] = true;
    if (!empty($args['bbn_db_processed'])) {
      unset($args['bbn_db_processed']);
    }

    if (\is_object($r = $this->_exec($args))) {
      $a = $r->getIrow();
      return $a ? (int)$a[0] : null;
    }

    return null;
  }


  /**
   * Return an array indexed on the first field of the request.
   * The value will be an array if the request has more than two fields.
   * Return the same value as "get_key_val".
   *
   * ```php
   * X::dump($db->selectAllByKeys("table_users", ["name","id","surname"], [["id", ">", "1"]], ["id" => "ASC"]);
   * /*
   * (array)[
   *        "John" => [
   *          "surname" => "Brown",
   *          "id" => 3
   *          ],
   *        "Michael" => [
   *          "surname" => "Smith",
   *          "id" => 4
   *        ]
   *      ]
   * ```
   *
   * @param string|array  $table  The table's name or a configuration array
   * @param array         $fields The fields's name
   * @param array         $where  The "where" condition
   * @param array|boolean $order  The "order" condition
   * @param int           $limit  The $limit condition, default: 0
   * @param int           $start  The $limit condition, default: 0
   * @return array|false
   */
  public function selectAllByKeys($table, array $fields = [], array $where = [], array $order = [], int $limit = 0, int $start = 0): ?array
  {
    if ($rows = $this->rselectAll($table, $fields, $where, $order, $limit, $start)) {
      return X::indexByFirstVal($rows);
    }

    return $this->check() ? [] : null;
  }


  /**
   * Return an array with the count of values corresponding to the where conditions.
   *
   * ```php
   * X::dump($db->stat('table_user', 'name', ['name' => '%n']));
   * /* (array)
   * [
   *  [
   *      "num" => 1,
   *      "name" => "alan",
   *  ], [
   *      "num" => 1,
   *      "name" => "karen",
   *  ],
   * ]
   * ```
   *
   * @param string|array $table  The table's name or a configuration array.
   * @param string       $column The field's name.
   * @param array        $where  The "where" condition.
   * @param array        $order  The "order" condition.
   * @return array
   */
  public function stat(string $table, string $column, array $where = [], array $order = []): ?array
  {
    if ($this->check()) {
      return $this->rselectAll(
        [
        'tables' => [$table],
        'fields' => [
          $column,
          'num' => 'COUNT(*)'
        ],
        'where' => $where,
        'order' => $order,
        'group_by' => [$column]
        ]
      );
    }

    return null;
  }


  /**
   * Return the unique values of a column of a table as a numeric indexed array.
   *
   * ```php
   * X::dump($db->getFieldValues("table_users", "surname", [['id', '>', '2']], 1, 1));
   * // (array) ["Smiths", "White"]
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param string       $field The field's name
   * @param array        $where The "where" condition
   * @param array        $order The "order" condition
   * @return array | false
   */
  public function getFieldValues($table, $field = null,  array $where = [], array $order = []): ?array
  {
    return $this->getColumnValues($table, $field, $where, $order);
  }


  /**
   * Return a count of identical values in a field as array, Reporting a structure type 'num' - 'val'.
   *
   * ```php
   * X::dump($db->countFieldValues('table_users','surname',[['name','=','John']]));
   * // (array) ["num" => 2, "val" => "John"]
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param null|string  $field The field's name
   * @param array        $where The "where" condition
   * @param array        $order The "order" condition
   * @return array | false
   */
  public function countFieldValues($table, string $field = null,  array $where = [], array $order = [])
  {
    if (\is_array($table) && \is_array($table['fields']) && count($table['fields'])) {
      $args  = $table;
      $field = array_values($table['fields'])[0];
    }
    else{
      $args = [
        'tables' => [$table],
        'where' => $where,
        'order' => $order
      ];
    }

    $args = array_merge(
      $args, [
      'kind' => 'SELECT',
      'fields' => [
        'val' => $field,
        'num' => 'COUNT(*)'
      ],
      'group_by' => [$field]
      ]
    );
    return $this->rselectAll($args);
  }


  /**
   * Return a numeric indexed array with the values of the unique column ($field) from the selected $table
   *
   * ```php
   * X::dump($db->getColumnValues('table_users','surname',['id','>',1]));
   * /*
   * array [
   *    "Smith",
   *    "Jones",
   *    "Williams",
   *    "Taylor"
   * ]
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param string       $field The field's name
   * @param array        $where The "where" condition
   * @param array        $order The "order" condition
   * @return array
   */
  public function getColumnValues($table, string $field = null,  array $where = [], array $order = [], int $limit = 0, int $start = 0): ?array
  {
    $res = null;
    if ($this->check()) {
      $res = [];
      if (\is_array($table) && isset($table['fields']) && \is_array($table['fields']) && !empty($table['fields'][0])) {
        array_splice($table['fields'], 0, 1, 'DISTINCT '.(string)$table['fields'][0]);
      }
      elseif (\is_string($table) && \is_string($field) && (stripos($field, 'DISTINCT') !== 0)) {
        $field = 'DISTINCT '.$field;
      }

      if ($rows = $this->iselectAll($table, $field, $where, $order, $limit, $start)) {
        foreach ($rows as $row){
          $res[] = $row[0];
        }
      }
    }

    return $res;
  }


  /**
   * Return a string with the sql query to count equal values in a field of the table.
   *
   * ```php
   * X::dump($db->getValuesCount('table_users','name',['surname','=','smith']));
   * /*
   * (string)
   *   SELECT COUNT(*) AS num, `name` AS val FROM `db_example`.`table_users`
   *     GROUP BY `name`
   *     ORDER BY `name`
   * ```
   *
   * @param string|array $table The table's name or a configuration array
   * @param string       $field The field's name
   * @param array        $where The "where" condition
   * @param array        $order The "order" condition
   * @return array
   */
  public function getValuesCount($table, string $field = null, array $where = [], $order): array
  {
    return $this->countFieldValues($table, $field, $where, $order);
  }


  /****************************************************************
   *                                                              *
   *                                                              *
   *                 WRITE HELPERS WITH TRIGGERS                  *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Inserts row(s) in a table.
   *
   * <code>
   * $db->insert("table_users", [
   *    ["name" => "Ted"],
   *    ["surname" => "McLow"]
   *  ]);
   * </code>
   *
   * <code>
   * $db->insert("table_users", [
   *    ["name" => "July"],
   *    ["surname" => "O'neill"]
   *  ], [
   *    ["name" => "Peter"],
   *    ["surname" => "Griffin"]
   *  ], [
   *    ["name" => "Marge"],
   *    ["surname" => "Simpson"]
   *  ]);
   * </code>
   *
   * @param string|array $table  The table name or the configuration array.
   * @param array        $values The values to insert.
   * @param bool         $ignore If true, controls if the row is already existing and ignores it.
   *
   * @return int Number affected rows.
   */
  public function insert($table, array $values = null, $ignore = false): ?int
  {
    if (\is_array($table) && isset($table['values'])) {
      $values = $table['values'];
    }

    // Array of arrays
    if (\is_array($values)
        && count($values)
        && !X::isAssoc($values)
        && \is_array($values[0])
    ) {
      $res = 0;

      foreach ($values as $v){
        $res += $this->insert($table, $v, $ignore);
      }

      return $res;
    }

    $cfg         = \is_array($table) ? $table : [
      'tables' => [$table],
      'fields' => $values,
      'ignore' => $ignore
    ];
    $cfg['kind'] = 'INSERT';
    return $this->_exec($cfg);
  }


  /**
   * If not exist inserts row(s) in a table, else update.
   *
   * <code>
   * $db->insertUpdate(
   *  "table_users",
   *  [
   *    'id' => '12',
   *    'name' => 'Frank'
   *  ]
   * );
   * </code>
   *
   * @param string|array $table  The table name or the configuration array.
   * @param array        $values The values to insert.
   *
   * @return int The number of rows inserted or updated.
   */
  public function insertUpdate($table, array $values = null): ?int
  {
    // Twice the arguments
    if (\is_array($table) && isset($table['values'])) {
      $values = $table['values'];
    }

    if (!X::isAssoc($values)) {
      $res = 0;
      foreach ($values as $v){
        $res += $this->insertUpdate($table, $v);
      }

      return $res;
    }

    $keys   = $this->getKeys($table);
    $unique = [];
    foreach ($keys['keys'] as $k){
      // Checking each unique key
      if ($k['unique']) {
        $i = 0;
        foreach ($k['columns'] as $c){
          if (isset($values[$c])) {
            $unique[$c] = $values[$c];
            $i++;
          }
        }

        // Only if the number of known field values matches the number of columns
        // which are parts of the unique key
        // If a value is null it won't pass isset and so won't be used
        if (($i === \count($k['columns'])) && $this->count($table, $unique)) {
          // Removing unique matching fields from the values (as it is the where)
          foreach ($unique as $f => $v){
            unset($values[$f]);
          }

          // For updating
          return $this->update($table, $values, $unique);
        }
      }
    }

    // No need to update, inserting
    return $this->insert($table, $values);
  }


  /**
   * Updates row(s) in a table.
   *
   * <code>
   * $db->update(
   *  "table_users",
   *  [
   *    ['name' => 'Frank'],
   *    ['surname' => 'Red']
   *  ],
   *  ['id' => '127']
   * );
   * </code>
   *
   * @param string|array $table  The table name or the configuration array.
   * @param array        $values The new value(s).
   * @param array        $where  The "where" condition.
   * @param boolean      $ignore If IGNORE should be added to the statement
   *
   * @return int The number of rows updated.
   */
  public function update($table, array $values = null, array $where = null, bool $ignore = false): ?int
  {
    $cfg         = \is_array($table) ? $table : [
      'tables' => [$table],
      'where' => $where,
      'fields' => $values,
      'ignore' => $ignore
    ];
    $cfg['kind'] = 'UPDATE';
    return $this->_exec($cfg);
  }


  /**
   * If exist updates row(s) in a table, else ignore.
   *
   * <code>
   * $db->updateIgnore(
   *   "table_users",
   *   [
   *     ['name' => 'Frank'],
   *     ['surname' => 'Red']
   *   ],
   *   ['id' => '20']
   * );
   * </code>
   *
   * @param string|array $table  The table name or the configuration array.
   * @param array        $values
   * @param array        $where  The "where" condition.
   *
   * @return int The number of rows deleted.
   */
  public function updateIgnore($table, array $values = null, array $where = null): ?int
  {
    return $this->update($table, $values, $where, true);
  }


  /**
   * Deletes row(s) in a table.
   *
   * <code>
   * $db->delete("table_users", ['id' => '32']);
   * </code>
   *
   * @param string|array $table  The table name or the configuration array.
   * @param array        $where  The "where" condition.
   * @param bool         $ignore default: false.
   *
   * @return int The number of rows deleted.
   */
  public function delete($table, array $where = null, bool $ignore = false): ?int
  {
    $cfg         = \is_array($table) ? $table : [
      'tables' => [$table],
      'where' => $where,
      'ignore' => $ignore
    ];
    $cfg['kind'] = 'DELETE';
    return $this->_exec($cfg);
  }


  /**
   * If exist deletess row(s) in a table, else ignore.
   *
   * <code>
   * $db->deleteIgnore(
   *  "table_users",
   *  ['id' => '20']
   * );
   * </code>
   *
   * @param string|array $table The table name or the configuration array.
   * @param array        $where The "where" condition.
   *
   * @return int The number of rows deleted.
   */
  public function deleteIgnore($table, array $where = null): ?int
  {
    return $this->delete(\is_array($table) ? array_merge($table, ['ignore' => true]) : $table, $where, true);
  }


  /**
   * If not exist inserts row(s) in a table, else ignore.
   *
   * <code>
   * $db->insertIgnore(
   *  "table_users",
   *  [
   *    ['id' => '19', 'name' => 'Frank'],
   *    ['id' => '20', 'name' => 'Ted'],
   *  ]
   * );
   * </code>
   *
   * @param string|array $table  The table name or the configuration array.
   * @param array        $values The row(s) values.
   *
   * @return int The number of rows inserted.
   */
  public function insertIgnore($table, array $values = null): ?int
  {
    return $this->insert(\is_array($table) ? array_merge($table, ['ignore' => true]) : $table, $values, true);
  }


  /**
   * @param $table
   * @return int|null
   */
  public function truncate($table): ?int
  {
    return $this->delete($table, []);
  }


  /****************************************************************
   *                                                              *
   *                                                              *
   *                      NATIVE FUNCTIONS                        *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Return an indexed array with the first result of the query or false if there are no results.
   *
   * ```php
   * X::dump($db->fetch("SELECT name FROM users WHERE id = 10"));
   * /* (array)
   * [
   *  "name" => "john",
   *  0 => "john",
   * ]
   * ```
   *
   * @param string $query
   * @return array | false
   */
  public function fetch($query)
  {
    if ($r = $this->query(...\func_get_args())) {
      return $r->fetch();
    }

    return false;
  }


  /**
   * Return an array of indexed array with all results of the query or false if there are no results.
   *
   * ```php
   * X::dump($db->fetchAll("SELECT 'surname', 'name', 'id' FROM users WHERE name = 'john'"));
   * /* (array)
   *  [
   *    [
   *    "surname" => "White",
   *    0 => "White",
   *    "name" => "Michael",
   *    1 => "Michael",
   *    "id"  => 1,
   *    2 => 1,
   *    ],
   *    [
   *    "surname" => "Smith",
   *    0 => "Smith",
   *    "name" => "John",
   *    1  =>  "John",
   *    "id" => 2,
   *    2 => 2,
   *    ],
   *  ]
   * ```
   *
   * @param string $query
   * @return array | false
   */
  public function fetchAll($query)
  {
    if ($r = $this->query(...\func_get_args())) {
      return $r->fetchAll();
    }

    return false;
  }


  /**
   * Transposition of the original fetchColumn method, but with the query included. Return an arra or false if no result
   * @todo confusion between result's index and this->query arguments(IMPORTANT). Missing the example because the function doesn't work
   *
   * @param $query
   * @param int   $num
   * @return mixed
   */
  public function fetchColumn($query, int $num = 0)
  {
    if ($r = $this->query(...\func_get_args())) {
      return $r->fetchColumn($num);
    }

    return false;
  }


  /**
   * Return an array with stdClass object or false if no result.
   *
   * ```php
   * X::dump($db->fetchObject("SELECT * FROM table_users WHERE name = 'john'"));
   * // stdClass Object {
   *                    "id"  =>  1,
   *                    "name"  =>  "John",
   *                    "surname"  =>  "Smith",
   *                    }
   * ```
   *
   * @param string $query
   * @return bool|\stdClass
   */
  public function fetchObject($query)
  {
    if ($r = $this->query(...\func_get_args())) {
      return $r->fetchObject();
    }

    return false;
  }


  /**
   * Executes a writing statement and return the number of affected rows or return a query object for the reading * statement
   * @todo far vedere a thomams perche non funziona in lettura
   *
   * ```php
   * X::dump($db->query("DELETE FROM table_users WHERE name LIKE '%lucy%'"));
   * // (int) 3
   * X::dump($db->query("SELECT * FROM table_users WHERE name = 'John"));
   * // (bbn\Db\Query) Object
   * ```
   *
   * @param array|string $statement
   * @return int|Db\Query
   */
  public function query($statement)
  {
    if ($this->check()) {
      $args = \func_get_args();
      // If fancy is false we just use the regular PDO query function
      if (!$this->_fancy) {
        return parent::query(...$args);
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
            /** @var Db\Query */
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
              // Native PDO function which will use Db\Query as base class
              /** @var Db\Query */
              $q['prepared'] = $this->prepare($q['sql'], $q['options']);
              $r             = $q['prepared']->execute();
            }
          }
          // This is a reading statement, it will prepare the statement and return a query object
          else{
            if (!$q['prepared']) {
              // Native PDO function which will use Db\Query as base class
              $q['prepared'] = $this->prepare($q['sql'], $driver_options);
            }
            else{
              // Returns the same Db\Query object
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

    return false;
  }


  /****************************************************************
   *                                                              *
   *                                                              *
   *                          SHORTCUTS                           *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Return table's simple name.
   * (similar to {@link table_simple_name()})
   *
   * ```php
   * X::dump($db->tsn("work_db.table_users"));
   * // (string) table_users
   * X::dump($db->tsn("work_db.table_users", true));
   * // (string) `table_users`
   * ```
   *
   * @param string $table   The table's name
   * @param bool   $escaped If set to true the returned string will be escaped.
   * @return null|string
   */
  public function tfn(string $table, bool $escaped = false): ?string
  {
    return $this->tableFullName($table, $escaped);
  }


  /**
   * Return table's simple name.
   * (similar to {@link table_simple_name()})
   *
   * ```php
   * X::dump($db->tsn("work_db.table_users"));
   * // (string) table_users
   * X::dump($db->tsn("work_db.table_users", true));
   * // (string) `table_users`
   * ```
   *
   * @param string $table   The table's name
   * @param bool   $escaped If set to true the returned string will be escaped.
   * @return null|string
   */
  public function tsn(string $table, bool $escaped = false): ?string
  {
    return $this->tableSimpleName($table, $escaped);
  }


  /**
   * Return column's full name.
   * (similar to {@link col_full_name()})
   *
   * ```php
   * X::dump($db->cfn("name", "table_users"));
   * // (string)  table_users.name
   * X::dump($db->cfn("name", "table_users", true));
   * // (string) \`table_users\`.\`name\`
   * ```
   *
   * @param string $col     The column's name (escaped or not).
   * @param string $table   The table's name (escaped or not).
   * @param bool   $escaped If set to true the returned string will be escaped.
   * @return null|string
   */
  public function cfn(string $col, $table = null, bool $escaped = false): ?string
  {
    return $this->colFullName($col, $table, $escaped);
  }


  /**
   * Return the column's simple name.
   * (similar to {@link col_simple_name()})
   *
   * ```php
   * X::dump($db->csn("table_users.name"));
   * // (string) name
   * X::dump($db->csn("table_users.name", true));
   * // (string) `name`
   * ```
   *
   * @param string $col     The column's complete name (escaped or not)
   * @param bool   $escaped If set to true the returned string will be escaped.
   * @return null|string
   */
  public function csn(string $col, bool $escaped = false): ?string
  {
    return $this->colSimpleName($col, $escaped);
  }


  /****************************************************************
   *                                                              *
   *                                                              *
   *                       ENGINE INTERFACE                       *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * @param array $cfg The user's options
   * @return array|null The final configuration
   */
  public function getConnection(array $cfg = []): ?array
  {
    if ($this->language) {
      return $this->language->getConnection($cfg);
    }

    return null;
  }


  /**
   * Actions to do once the PDO object has been created
   *
   * @return void
   */
  public function postCreation()
  {
    // Obliged to do that  if we want to use foreign keys with SQLite
    if ($this->language && !$this->engine) {
      $this->language->postCreation();
    }

    return;
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
    if ($this->language->change($db)) {
      $this->current = $db;
    }

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
   * Returns true if the string corresponds to the tipology of a table full name.
   * (similar to {@link table_full_name()})
   *
   * ```php
   * X::dump($db->tfn("table_users"));
   * // (String) db_example.table_users
   * X::dump($db->tfn("table_users", true));
   * // (String) `db_example`.`table_users`
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
   * // (string)  table_users.name
   * X::dump($db->colFullName("name", "table_users", true));
   * // (string) \`table_users\`.\`name\`
   * ```
   *
   * @param string $col     The column's name (escaped or not)
   * @param string $table   The table's name (escaped or not)
   * @param bool   $escaped If set to true the returned string will be escaped
   * @return string | false
   */
  public function colFullName(string $col, $table = '', $escaped = false): ?string
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
   * // (db)
   * ```
   *
   * @return db
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
   * @return db
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
   *
   * @return null|array
   */
  public function getDatabases(): ?array
  {
    return $this->_get_cache('', 'databases');
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
  public function getTables(string $database=''): ?array
  {
    if (empty($database)) {
      $database = $this->current;
    }

    return $this->_get_cache($database, 'tables');
  }


  /**
   * Return colums' structure of a table as an array indexed with the fields names.
   *
   * ```php
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
    if ($tmp = $this->_get_cache($table)) {
      return $tmp['fields'];
    }

    return null;
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
    if ($tmp = $this->_get_cache($table)) {
      return [
        'keys' => $tmp['keys'],
        'cols' => $tmp['cols']
      ];
    }

    return null;
  }


  /**
   * @param array $conditions
   * @param array $cfg
   * @param bool  $is_having
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
   * X::dump($db->getSelect('table_users',['name','surname']));
   * /*
   * (string)
   *   SELECT
   *    `table_users`.`name`,
   *    `table_users`.`surname`
   * ```
   *
   * @param array $cfg The configuration array
   * @return string
   */
  public function getSelect(array $cfg): string
  {
    return $this->language->getSelect(...$this->_add_kind(\func_get_args()));
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
   */
  public function getInsert(array $cfg): string
  {
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
   */
  public function getUpdate(array $cfg): string
  {
    $cfg['kind'] = 'UPDATE';
    return $this->language->getUpdate($this->processCfg($cfg));
  }


  /**
   * Returns the SQL code for a DELETE statement.
   *
   * ```php
   * X::dump($db->getDelete('table_users',['id'=>1]));
   * // (string) DELETE FROM `db_example`.`table_users` * WHERE 1 AND `table_users`.`id` = ?
   * ```
   *
   * @param array $cfg The configuration array
   * @return string
   */
  public function getDelete(array $cfg): string
  {
    $cfg['kind'] = 'DELETE';
    return $this->language->getDelete($this->processCfg($cfg));
  }


  /**
   * Returns a string with the JOIN part of the query if there is, empty otherwise
   *
   * @param array $cfg
   * @return string
   */
  public function getJoin(array $cfg): string
  {
    return $this->language->getJoin($cfg);
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
   */
  public function getWhere(array $cfg): string
  {
    return $this->language->getWhere($cfg);
  }


  /**
   * Returns a string with the GROUP BY part of the query if there is, empty otherwise
   *
   * @param array $cfg
   * @return string
   */
  public function getGroupBy(array $cfg): string
  {
    return $this->language->getGroupBy($cfg);
  }


  /**
   * Returns a string with the HAVING part of the query if there is, empty otherwise
   *
   * @param array $cfg
   * @return string
   */
  public function getHaving(array $cfg): string
  {
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
   */
  public function getOrder(array $cfg): string
  {
    return $this->language->getOrder($cfg);
  }


  /**
   * Get a string starting with LIMIT with corresponding parameters to $limit.
   *
   * ```php
   * X::dump($db->getLimit(3,1));
   * // (string) LIMIT 1, 3
   * ```
   *
   * @param array $cfg
   * @return string
   */
  public function getLimit(array $cfg): string
  {
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
   * @param string $table The table's name
   * @return string | false
   */
  public function getCreate(string $table, array $model = null): string
  {
    return $this->language->getCreate($table, $model);
  }


  public function getCreateTable(string $table, array $model = null): string
  {
    return $this->language->getCreateTable($table, $model);
  }


  public function getCreateKeys(string $table, array $model = null): string
  {
    return $this->language->getCreateKeys($table, $model);
  }


  public function getCreateConstraints(string $table, array $model = null): string
  {
    return $this->language->getCreateConstraints($table, $model);
  }


  public function createConstraintsSqlite(string $table, array $model = null)
  {
    return $this->language->createConstraintsSqlite(...\func_get_args());
  }


  /**
   * Creates an index on one or more column(s) of the table
   *
   * @todo return data
   *
   * ```php
   * X::dump($db->create_db_index('table_users','id_group'));
   * // (void)
   * ```
   *
   * @param string       $table
   * @param string|array $column
   * @param bool         $unique
   * @param null         $length
   * @return bool
   */
  public function createIndex(string $table, $column, bool $unique = false, $length = null): bool
  {
    return $this->language->createIndex($table, $column, $unique);
  }


  /**
   * Deletes index on a column of the table.
   *
   * @todo far vedere a thomas perchè non funziona/return data
   *
   * ```php
   * X::dump($db->delete_db_index('table_users','id_group'));
   * // (void)
   * ```
   *
   * @param string $table The table's name.
   * @param string $key   The key's name.
   * @return bool
   */
  public function deleteIndex(string $table, string $key): bool
  {
    return $this->language->deleteIndex($table, $key);
  }


  /**
   * Creates an user for a specific db.
   * @todo return data
   *
   * ```php
   * X::dump($db->create_db_user('Michael','22101980','db_example'));
   * // (void)
   * ```
   *
   * @param string $user
   * @param string $pass
   * @param string $db
   * @return bool
   */
  public function createUser(string $user = null, string $pass = null, string $db = null): bool
  {
    return $this->language->createUser($user, $pass, $db);
  }


  /**
   * Deletes a db user.
   *
   * @todo non mi funziona ma forse per una questione di permessi/ return data
   *
   * ```php
   * X::dump($db->delete_db_user('Michael'));
   * // (void)
   * ```
   *
   * @param string $user
   * @return bool
   */
  public function deleteUser(string $user = null): bool
  {
    return $this->language->deleteUser($user);
  }


  /**
   * Return an array including privileges of a specific db_user or all db_users.
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
   * @param string $user. The user's name, without params will return all privileges of all db_users
   * @param string $host. The host
   * @return array
   */
  public function getUsers(string $user = '', string $host = ''): ?array
  {
    return $this->language->getUsers($user, $host);
  }


  /**
   * @param string $database
   * @param string $type
   * @return int
   */
  public function dbSize(string $database = '', string $type = ''): int
  {
    return $this->language->dbSize($database, $type);
  }


  /**
   * @param string $table
   * @param string $type
   * @return int
   */
  public function tableSize(string $table, string $type = ''): int
  {
    return $this->language->tableSize($table, $type);
  }


  /**
   * @param string $table
   * @param string $database
   * @return array|false|mixed
   */
  public function status(string $table = '', string $database = '')
  {
    return $this->language->status($table, $database);
  }


  /**
   * @return string
   */
  public function getUid(): string
  {
    //return hex2bin(str_replace('-', '', X::makeUid()));
    return $this->language->getUid();
  }


  /****************************************************************
   *                                                              *
   *                                                              *
   *                        ACTIONS INTERFACE                     *
   *                                                              *
   *                                                              *
   ****************************************************************/


  /**
   * Return the first row resulting from the query as an array indexed with the fields' name.
   *
   * ```php
   * X::dump($db->getRow("SELECT id, name FROM table_users WHERE id > ? ", 2));;
   *
   * /* (array)[
   *        "id" => 3,
   *        "name" => "thomas",
   *        ]
   * ```
   *
   * @param string query.
   * @param int The var ? value.
   * @return array | false
   *
   */
  public function getRow(): ?array
  {
    if ($r = $this->query(...\func_get_args())) {
      return $r->getRow();
    }

    return null;
  }


  /**
   * Return an array that includes indexed arrays for every row resultant from the query.
   *
   * ```php
   * X::dump($db->getRows("SELECT id, name FROM table_users WHERE id > ? LIMIT ?", 2));
   * /* (array)[
   *            [
   *            "id" => 3,
   *            "name" => "john",
   *            ],
   *            [
   *            "id" => 4,
   *            "name" => "barbara",
   *            ],
   *          ]
   * ```
   *
   * @param string
   * @param int The var ? value
   * @return array | false
   */
  public function getRows(): ?array
  {
    if ($r = $this->query(...\func_get_args())) {
      return $r->getRows();
    }

    return null;
  }


  /**
   * Return a row as a numeric indexed array.
   *
   * ```php
   * X::dump($db->getIrow("SELECT id, name, surname FROM table_users WHERE id > ?", 2));
   * /* (array) [
   *              3,
   *              "john",
   *              "brown",
   *             ]
   * ```
   *
   * @param string query
   * @param int The var ? value
   * @return array | false
   */
  public function getIrow(): ?array
  {
    if ($r = $this->query(...\func_get_args())) {
      return $r->getIrow();
    }

    return null;
  }


  /**
   * Return an array of numeric indexed rows.
   *
   * ```php
   * X::dump($db->getIrows("SELECT id, name FROM table_users WHERE id > ? LIMIT ?", 2, 2));
   * /*
   * (array)[
   *         [
   *          3,
   *         "john"
   *         ],
   *         [
   *         4,
   *         "barbara"
   *        ]
   *       ]
   * ```
   *
   * @return null|array
   */
  public function getIrows(): ?array
  {
    if ($r = $this->query(...\func_get_args())) {
      return $r->getIrows();
    }

    return null;
  }


  /**
   * Return an array indexed on the searched field's in which there are all the values of the column.
   *
   * ```php
   * X::dump($db->getByColumns("SELECT name, surname FROM table_users WHERE id > 2"));
   * /*
   * (array) [
   *      "name" => [
   *       "John",
   *       "Michael"
   *      ],
   *      "surname" => [
   *        "Brown",
   *        "Smith"
   *      ]
   *     ]
   * ```
   *
   * @param string query
   * @return null|array
   */
  public function getByColumns(): ?array
  {
    if ($r = $this->query(...\func_get_args())) {
      return $r->getByColumns();
    }

    return null;
  }


  /**
   * Return the first row resulting from the query as an object (similar to {@link get_object()}).
   *
   * ```php
   * X::dump($db->getObj("SELECT surname FROM table_users"));
   * /*
   * (obj){
   *       "name" => "Smith"
   *       }
   * ```
   *
   * @return null|\stdClass
   */
  public function getObj(): ?\stdClass
  {
    return $this->getObject(\func_get_args());
  }


  /**
   * Return the first row resulting from the query as an object.
   * Synonym of get_obj.
   *
   * ```php
   * X::dump($db->getObject("SELECT name FROM table_users"));
   * /*
   * (obj){
   *       "name" => "John"
   *       }
   * ```
   *
   * @return null|\stdClass
   */
  public function getObject(): ?\stdClass
  {
    if ($r = $this->query(...\func_get_args())) {
      return $r->getObject();
    }

    return null;
  }


  /**
   * Return an array of stdClass objects.
   *
   * ```php
   * X::dump($db->getObjects("SELECT name FROM table_users"));
   *
   * /*
   * (array) [
   *          Object stdClass: df {
   *            "name" => "John",
   *          },
   *          Object stdClass: df {
   *            "name" => "Michael",
   *          },
   *          Object stdClass: df {
   *            "name" => "Thomas",
   *          },
   *          Object stdClass: df {
   *            "name" => "William",
   *          },
   *          Object stdClass: df {
   *            "name" => "Jake",
   *          },
   *         ]
   * ```
   *
   * @return null|array
   */
  public function getObjects(): ?array
  {
    if ($r = $this->query(...\func_get_args())) {
      return $r->getObjects();
    }

    return [];
  }


  public function createTable()
  {
    return $this->language->createTable(...\func_get_args());
  }


  public function createTableSqlite()
  {
    return $this->language->createTableSqlite(...\func_get_args());
  }


  public function createDatabase(string $database): bool
  {
    return $this->language->createDatabase(...\func_get_args());
  }


  /**
   * Drops the given database
   *
   * @param string $database
   * @return bool
   */
  public function dropDatabase(string $database): bool
  {
    return $this->language->dropDatabase($database);
  }


  public function enableLast()
  {
    $this->_last_enabled = true;
  }


  public function disableLast()
  {
    $this->_last_enabled = false;
  }


  public function getRealLastParams(): ?array
  {
    return $this->last_real_params;
  }


  public function realLast(): ?string
  {
    return $this->last_real_query;
  }


  public function getLastParams(): ?array
  {
    return $this->last_params;
  }


  public function getLastValues(): ?array
  {
    return $this->last_params ? $this->last_params['values'] : null;
  }


  public function getQueryValues(array $cfg): array
  {
    $res = [];
    if (!empty($cfg['values'])) {
      foreach ($cfg['values'] as $i => $v) {
        // Transforming the values if needed
        if (($cfg['values_desc'][$i]['type'] === 'binary')
            && ($cfg['values_desc'][$i]['maxlength'] === 16)
            && Str::isUid($v)
        ) {
          $res[] = hex2bin($v);
        }
        elseif (\is_string($v) && ((            ($cfg['values_desc'][$i]['type'] === 'date')
            && (\strlen($v) < 10)) || (            ($cfg['values_desc'][$i]['type'] === 'time')
            && (\strlen($v) < 8)) || (            ($cfg['values_desc'][$i]['type'] === 'datetime')
            && (\strlen($v) < 19))            )
        ) {
          $res[] = $v.'%';
        }
        elseif (!empty($cfg['values_desc'][$i]['operator'])) {
          switch ($cfg['values_desc'][$i]['operator']){
            case 'contains':
            case 'doesnotcontain':
              $res[] = '%'.$v.'%';
              break;
            case 'startswith':
              $res[] = $v.'%';
              break;
            case 'endswith':
              $res[] = '%'.$v;
              break;
            default:
              $res[] = $v;
          }
        }
        else{
          $res[] = $v;
        }
      }
    }

    return $res;
  }


  /**
   * Sets the has_error_all variable to true.
   *
   * @return void
   */
  private static function _set_has_error_all(): void
  {
    self::$_has_error_all = true;
  }


  /**
   * Gets the cache name of a database structure or part.
   *
   * @param string $item 'db_name' or 'table'
   * @param string $mode 'columns','tables' or 'databases'
   *
   * @return bool|string
   */
  private function _db_cache_name(string $item, string $mode)
  {
    $r = false;
    if ($this->engine === 'sqlite') {
      $h = md5($this->host.dirname($this->current));
    }
    else {
      $h = str_replace('/', '-', $this->getConnectionCode());
    }

    switch ($mode){
      case 'columns':
        $r = $this->engine.'/'.$h.'/'.str_replace('.', '/', $this->tfn($item));
        break;
      case 'tables':
        $r = $this->engine.'/'.$h.'/'.($item ?: $this->current);
        break;
      case 'databases':
        $r = $this->engine.'/'.$h.'/_bbn-database';
        break;
    }

    return $r;
  }


  /**
   * Returns the table's structure's array, either from the cache or from _modelize().
   *
   * @param string $item  The item to get
   * @param string $mode  The type of item to get (columns, rables, Databases)
   * @param bool   $force If true the cache is recreated even if it exists
   * @return array|null
   */
  private function _get_cache($item, $mode = 'columns', $force = false): ?array
  {
    $cache_name = $this->_db_cache_name($item, $mode);
    if ($force && isset($this->cache[$cache_name])) {
      unset($this->cache[$cache_name]);
    }

    if (!isset($this->cache[$cache_name])) {
      if ($force || !($tmp = $this->cacheGet($cache_name))) {
        switch ($mode){
          case 'columns':
            $keys = $this->language->getKeys($item);
            $cols = $this->language->getColumns($item);
            if (\is_array($keys) && \is_array($cols)) {
              $tmp = [
                'keys' => $keys['keys'],
                'cols' => $keys['cols'],
                'fields' => $cols
              ];
            }
            break;
          case 'tables':
            $tmp = $this->language->getTables($item);
            break;
          case 'databases':
            $tmp = $this->language->getDatabases();
            break;
        }

        if (!\is_array($tmp)) {
          $st = "Error while creating the cache for the table $item in mode $mode";
          $this->log($st);
          throw new \Exception($st);
        }

        $this->cacheSet($cache_name, '', $tmp, $this->cache_renewal);
      }

      if ($tmp) {
        $this->cache[$cache_name] = $tmp;
      }
    }

    return $this->cache[$cache_name] ?? null;
  }


  /**
   * Removes values from the given conditions array and returns an array with values and hashed.
   *
   * @param array $where  Conditions
   * @param array $values Values
   * @return array
   */
  private function _remove_conditions_value(array $where, array &$values = []): array
  {
    if (isset($where['conditions'])) {
      foreach ($where['conditions'] as &$f){
        ksort($f);
        if (isset($f['logic'], $f['conditions']) && \is_array($f['conditions'])) {
          $tmp = $this->_remove_conditions_value($f, $values);
          $f   = $tmp['hashed'];
        }
        elseif (array_key_exists('value', $f)) {
          $values[] = $f['value'];
          unset($f['value']);
        }
      }
    }

    return [
      'hashed' => $where,
      'values' => $values
    ];
  }


  /**
   * Adds the specs of a query to the $queries object.
   *
   * @param string $hash         The hash of the statement.
   * @param string $statement    The SQL full statement.
   * @param string $kind         The type of statement.
   * @param int    $placeholders The number of placeholders.
   * @param array  $options      The driver options.
   */
  private function _add_query(string $hash, string $statement, string $kind, int $placeholders, array $options)
  {
    $now                  = microtime(true);
    $this->queries[$hash] = [
      'sql' => $statement,
      'kind' => $kind,
      'write' => \in_array($kind, self::$write_kinds, true),
      'structure' => \in_array($kind, self::$structure_kinds, true),
      'placeholders' => $placeholders,
      'options' => $options,
      'num' => 0,
      'exe_time' => 0,
      'first' => $now,
      'last' => 0,
      'prepared' => false
    ];
    $this->list_queries[] = [
      'hash' => $hash,
      'last' => $now
    ];
    $num                  = count($this->list_queries);
    while ($num > $this->max_queries) {
      $num--;
      $this->_remove_query($this->list_queries[0]['hash']);
      array_shift($this->list_queries);
    }
  }


  private function _remove_query(string $hash): void
  {
    if (X::hasProp($this->queries, $hash)) {
      unset($this->queries[$hash]);
      while ($idx = \array_search($hash, $this->queries, true)) {
        unset($this->queries[$idx]);
      }
    }
  }


  private function _update_query($hash)
  {
    if (isset($this->queries[$hash]) && \is_array(($this->queries[$hash]))) {
      $last_index                   = count($this->list_queries) - 1;
      $now                          = \microtime(true);
      $this->queries[$hash]['last'] = $now;
      $this->queries[$hash]['num']++;
      if ($this->list_queries[$last_index]['hash'] !== $hash) {
        if (($idx = X::find($this->list_queries, ['hash' => $hash])) !== null) {
          $this->list_queries[$idx]['last'] = $now;
          X::move($this->list_queries, $idx, $last_index);
        }
        else {
          throw new \Exception(dgettext(X::tDom(), "Impossible to find the corresponding hash"));
        }
      }
      else {
        $this->list_queries[$last_index]['last'] = $now;
      }

      $num = count($this->list_queries) - 1;
      while (($num > 0)
          && ($now > ($this->list_queries[0]['last'] + $this->length_queries))
      ) {
        $num--;
        if (!is_string($this->list_queries[0]['hash'])) {
          X::log($this->list_queries);
          X::log(count($this->list_queries));
        }

        $this->_remove_query($this->list_queries[0]['hash']);
        array_shift($this->list_queries);
      }

      if (empty($this->queries)) {
        $debug = debug_backtrace();
        X::log($debug, 'db_explained');
        throw new \Exception(dgettext(X::tDom(), "The queries object is empty!"));
      }
    }
    else {
      throw new \Exception(dgettext(X::tDom(), "Impossible to find the query corresponding to this hash"));
    }

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


  /**
   * Launches a function before or after
   *
   * @param array $cfg
   * @return array
   */
  private function _trigger(array $cfg): array
  {
    if ($this->_triggers_disabled) {
      if ($cfg['moment'] === 'after') {
        return $cfg;
      }

      $cfg['run']  = 1;
      $cfg['trig'] = 1;
      return $cfg;
    }

    if (!isset($cfg['trig'])) {
      $cfg['trig'] = 1;
    }

    if (!isset($cfg['run'])) {
      $cfg['run'] = 1;
    }

    if (!empty($cfg['tables']) && !empty($this->_triggers[$cfg['kind']][$cfg['moment']])) {
      $table = $this->tfn(\is_array($cfg['tables']) ? current($cfg['tables']) : $cfg['tables']);
      // Specific to a table
      if (isset($this->_triggers[$cfg['kind']][$cfg['moment']][$table])) {
        foreach ($this->_triggers[$cfg['kind']][$cfg['moment']][$table] as $i => $f){
          if ($f && \is_callable($f)) {
            if (!($tmp = $f($cfg))) {
              $cfg['run']  = false;
              $cfg['trig'] = false;
            }
            else{
              $cfg = $tmp;
            }
          }
        }

        //echo X::makeTree($trig);
        //echo X::makeTree($cfg);
      }
    }

    return $cfg;
  }


  /**
   * @param array  $args
   * @param string $kind
   * @return array
   */
  private function _add_kind(array $args, string $kind = 'SELECT'): ?array
  {
    $kind = strtoupper($kind);
    if (!isset($args[0])) {
      return null;
    }

    if (!\is_array($args[0])) {
      array_unshift($args, $kind);
    }
    else {
      $args[0]['kind'] = $kind;
    }

    return $args;
  }


  /**
   * Adds a random primary value when it is absent from the set and present in the fields
   * @param array $cfg
   */
  private function _add_primary(array &$cfg): void
  {
    // Inserting a row without primary when primary is needed and no auto-increment
    if (!empty($cfg['primary'])
        && empty($cfg['auto_increment'])
        && (($idx = array_search($cfg['primary'], $cfg['fields'], true)) > -1)
        && (count($cfg['values']) === (count($cfg['fields']) - 1))
    ) {
      $val = false;
      switch ($cfg['primary_type']){
        case 'int':
          $val = random_int(
            ceil(10 ** ($cfg['primary_length'] > 3 ? $cfg['primary_length'] - 3 : 1) / 2),
            ceil(10 ** ($cfg['primary_length'] > 3 ? $cfg['primary_length'] : 1) / 2)
          );
          break;
        case 'binary':
          if ($cfg['primary_length'] === 16) {
            $val = $this->getUid();
          }
          break;
      }

      if ($val) {
        array_splice($cfg['values'], $idx, 0, $val);
        $this->setLastInsertId($val);
      }
    }
  }


  /**
   * @returns null|Db\Query|int A selection query or the number of affected rows by a writing query
   */
  private function _exec()
  {
    if ($this->check()
        && ($cfg = $this->processCfg(\func_get_args()))
        && !empty($cfg['sql'])
    ) {
      //die(var_dump('0exec cfg', $cfg, \func_get_args()));
      $cfg['moment'] = 'before';
      $cfg['trig']   = null;
      if ($cfg['kind'] === 'INSERT') {
        // Add generated primary when inserting a row without primary when primary is needed and no auto-increment
        $this->_add_primary($cfg);
      }

      if (count($cfg['values']) !== count($cfg['values_desc'])) {
        X::dump($cfg);
        die('Database error in values count');
      }

      // Launching the trigger BEFORE execution
      if ($cfg = $this->_trigger($cfg)) {
        if (!empty($cfg['run'])) {
          //$this->log(["TRIGGER OK", $cfg['run'], $cfg['fields']]);
          // Executing the query
          /** @todo Put hash back! */
          //$cfg['run'] = $this->query($cfg['sql'], $cfg['hash'], $cfg['values'] ?? []);
          /** @var \bbn\Db\Query */
          $cfg['run'] = $this->query($cfg['sql'], $this->getQueryValues($cfg));
        }

        if (!empty($cfg['force'])) {
          $cfg['trig'] = 1;
        }
        elseif (null === $cfg['trig']) {
          $cfg['trig'] = (bool)$cfg['run'];
        }

        if ($cfg['trig']) {
          $cfg['moment'] = 'after';
          $cfg           = $this->_trigger($cfg);
        }

        $this->last_cfg = $cfg;
        if (!\in_array($cfg['kind'], self::$write_kinds, true)) {
          return $cfg['run'] ?? null;
        }

        if (isset($cfg['value'])) {
          return $cfg['value'];
        }

        if (isset($cfg['run'])) {
          return $cfg['run'];
        }
      }
    }

    return null;
  }


  /**
   * Normalizes arguments by making it a uniform array.
   *
   * <ul><h3>The array will have the following indexes:</h3>
   * <li>fields</li>
   * <li>where</li>
   * <li>filters</li>
   * <li>order</li>
   * <li>limit</li>
   * <li>start</li>
   * <li>join</li>
   * <li>group_by</li>
   * <li>having</li>
   * <li>values</li>
   * <li>hashed_join</li>
   * <li>hashed_where</li>
   * <li>hashed_having</li>
   * <li>php</li>
   * <li>done</li>
   * </ul>
   *
   * @todo Check for the tables and column names legality!
   *
   * @param $cfg
   * @return array
   */
  private function _treat_arguments($cfg): array
  {
    while (isset($cfg[0]) && \is_array($cfg[0])){
      $cfg = $cfg[0];
    }

    if (\is_array($cfg)
        && array_key_exists('tables', $cfg)
        && array_key_exists('bbn_db_treated', $cfg)
        && ($cfg['bbn_db_treated'] === true)
    ) {
      return $cfg;
    }

    $res = [
      'kind' => 'SELECT',
      'fields' => [],
      'where' => [],
      'order' => [],
      'limit' => 0,
      'start' => 0,
      'group_by' => [],
      'having' => [],
    ];
    if (X::isAssoc($cfg)) {
      if (isset($cfg['table']) && !isset($cfg['tables'])) {
        $cfg['tables'] = $cfg['table'];
        unset($cfg['table']);
      }

      $res = array_merge($res, $cfg);
    }
    elseif (count($cfg) > 1) {
      $res['kind']   = strtoupper($cfg[0]);
      $res['tables'] = $cfg[1];
      if (isset($cfg[2])) {
        $res['fields'] = $cfg[2];
      }

      if (isset($cfg[3])) {
        $res['where'] = $cfg[3];
      }

      if (isset($cfg[4])) {
        $res['order'] = \is_string($cfg[4]) ? [$cfg[4] => 'ASC'] : $cfg[4];
      }

      if (isset($cfg[5]) && Str::isInteger($cfg[5])) {
        $res['limit'] = $cfg[5];
      }

      if (isset($cfg[6]) && !empty($res['limit'])) {
        $res['start'] = $cfg[6];
      }
    }

    $res           = array_merge(
      $res, [
      'aliases' => [],
      'values' => [],
      'filters' => [],
      'join' => [],
      'hashed_join' => [],
      'hashed_where' => [],
      'hashed_having' => [],
      'bbn_db_treated' => true
      ]
    );
    $res['kind']   = strtoupper($res['kind']);
    $res['write']  = \in_array($res['kind'], self::$write_kinds, true);
    $res['ignore'] = $res['write'] && !empty($res['ignore']);
    $res['count']  = !$res['write'] && !empty($res['count']);
    if (!\is_array($res['tables'])) {
      $res['tables'] = \is_string($res['tables']) ? [$res['tables']] : [];
    }

    if (!empty($res['tables'])) {
      foreach ($res['tables'] as $i => $t){
        if (!is_string($t)) {
          X::log([$cfg, debug_backtrace()], 'db_explained');
          throw new \Exception("Impossible to identify the tables, check the log");
        }

        $res['tables'][$i] = $this->tfn($t);
      }
    }
    else{
      throw new \Error(dgettext(X::tDom(), 'No table given'));
      return [];
    }

    if (!empty($res['fields'])) {
      if (\is_string($res['fields'])) {
        $res['fields'] = [$res['fields']];
      }
    }
    elseif (!empty($res['columns'])) {
      $res['fields'] = (array)$res['columns'];
    }

    if (!empty($res['fields'])) {
      if ($res['kind'] === 'SELECT') {
        foreach ($res['fields'] as $k => $col) {
          if (\is_string($k)) {
            $res['aliases'][$col] = $k;
          }
        }
      }
      elseif ((($res['kind'] === 'INSERT') || ($res['kind'] === 'UPDATE'))
          && \is_string(array_keys($res['fields'])[0])
      ) {
        $res['values'] = array_values($res['fields']);
        $res['fields'] = array_keys($res['fields']);
      }
    }

    if (!\is_array($res['group_by'])) {
      $res['group_by'] = empty($res['group_by']) ? [] : [$res['group_by']];
    }

    if (!\is_array($res['where'])) {
      $res['where'] = [];
    }

    if (!\is_array($res['order'])) {
      $res['order'] = \is_string($res['order']) ? [$res['order'] => 'ASC'] : [];
    }

    if (!Str::isInteger($res['limit'])) {
      unset($res['limit']);
    }

    if (!Str::isInteger($res['start'])) {
      unset($res['start']);
    }

    if (!empty($cfg['join'])) {
      foreach ($cfg['join'] as $k => $join){
        if (\is_array($join)) {
          if (\is_string($k)) {
            if (empty($join['table'])) {
              $join['table'] = $k;
            }
            elseif (empty($join['alias'])) {
              $join['alias'] = $k;
            }
          }

          if (isset($join['table'], $join['on']) && ($tmp = $this->treatConditions($join['on'], false))) {
            if (!isset($join['type'])) {
              $join['type'] = 'right';
            }

            $res['join'][] = array_merge($join, ['on' => $tmp]);
          }
        }
      }
    }

    if ($tmp = $this->treatConditions($res['where'], false)) {
      $res['filters'] = $tmp;
    }

    if (!empty($res['having']) && ($tmp = $this->treatConditions($res['having'], false))) {
      $res['having'] = $tmp;
    }

    if (!empty($res['group_by'])) {
      $this->_adapt_filters($res);
    }

    if (!empty($res['join'])) {
      $new_join = [];
      foreach ($res['join'] as $k => $join){
        if ($tmp = $this->treatConditions($join['on'])) {
          $new_item             = $join;
          $new_item['on']       = $tmp['where'];
          $res['hashed_join'][] = $tmp['hashed'];
          if (!empty($tmp['values'])) {
            foreach ($tmp['values'] as $v){
              $res['values'][] = $v;
            }
          }

          $new_join[] = $new_item;
        }
      }

      $res['join'] = $new_join;
    }

    if (!empty($res['filters']) && ($tmp = $this->treatConditions($res['filters']))) {
      $res['filters']      = $tmp['where'];
      $res['hashed_where'] = $tmp['hashed'];
      if (\is_array($tmp) && isset($tmp['values'])) {
        foreach ($tmp['values'] as $v){
          $res['values'][] = $v;
        }
      }
    }

    if (!empty($res['having']) && ($tmp = $this->treatConditions($res['having']))) {
      $res['having']        = $tmp['where'];
      $res['hashed_having'] = $tmp['hashed'];
      foreach ($tmp['values'] as $v){
        $res['values'][] = $v;
      }
    }

    $res['hash'] = $cfg['hash'] ?? $this->_make_hash(
      $res['kind'],
      $res['ignore'],
      $res['count'],
      $res['tables'],
      $res['fields'],
      $res['hashed_join'],
      $res['hashed_where'],
      $res['hashed_having'],
      $res['group_by'],
      $res['order'],
      $res['limit'] ?? 0,
      $res['start'] ?? 0
    );
    return $res;
  }


  private function _adapt_filters(&$cfg): void
  {
    if (!empty($cfg['filters'])) {
      [$cfg['filters'], $having] = $this->_adapt_bit($cfg, $cfg['filters']);
      if (empty($cfg['having']['conditions'])) {
        $cfg['having'] = $having;
      }
      else {
        $cfg['having'] = [
          'logic' => 'AND',
          'conditions' => [
            $cfg['having'],
            $having
          ]
        ];
      }
    }
  }


  private function _adapt_bit($cfg, $where, $having = [])
  {
    if (X::hasProps($where, ['logic', 'conditions'])) {
      $new = [
        'logic' => $where['logic'],
        'conditions' => []
      ];
      foreach ($where['conditions'] as $c) {
        $is_aggregate = false;
        if (isset($c['field'])) {
          $is_aggregate = $this->isAggregateFunction($c['field']);
          if (!$is_aggregate && isset($cfg['fields'][$c['field']])) {
            $is_aggregate = $this->isAggregateFunction($cfg['fields'][$c['field']]);
          }
        }

        if (!$is_aggregate && isset($c['exp'])) {
          $is_aggregate = $this->isAggregateFunction($c['exp']);
          if (!$is_aggregate && isset($cfg['fields'][$c['exp']])) {
            $is_aggregate = $this->isAggregateFunction($cfg['fields'][$c['exp']]);
          }
        }

        if (!$is_aggregate) {
          if (X::hasProps($c, ['conditions', 'logic'])) {
            $tmp = $this->_adapt_bit($cfg, $c, $having);
            if (!empty($tmp[0]['conditions'])) {
              $new['conditions'][] = $c;
            }

            if (!empty($tmp[1]['conditions'])) {
              $having = $tmp[1];
            }
          }
          else {
            $new['conditions'][] = $c;
          }
        }
        else {
          if (!isset($having['conditions'])) {
            $having = [
              'logic' => $where['logic'],
              'conditions' => []
            ];
          }

          if (isset($cfg['aliases'][$c['field']])) {
            $c['field'] = $cfg['aliases'][$c['field']];
          }
          elseif (isset($c['exp'], $cfg['aliases'][$c['exp']])) {
            $c['exp'] = $cfg['aliases'][$c['exp']];
          }

          $having['conditions'][] = $c;
        }
      }

      return [$new, $having];
    }
  }


  /**
   * @param array $args
   * @return array
   */
  private function _set_limit_1(array $args): array
  {
    if (\is_array($args[0])
        && (isset($args[0]['tables']) || isset($args[0]['table']))
    ) {
      $args[0]['limit'] = 1;
    }
    else {
      $start = $args[4] ?? 0;
      $num   = count($args);
      // Adding fields
      if ($num === 1) {
        $args[] = [];
        $num++;
      }

      // Adding where
      if ($num === 2) {
        $args[] = [];
        $num++;
      }

      // Adding order
      if ($num === 3) {
        $args[] = [];
        $num++;
      }

      if ($num === 4) {
        $args[] = 1;
        $num++;
      }

      $args   = array_slice($args, 0, 5);
      $args[] = $start;
    }

    return $args;
  }


  /**
   * @param array $args
   * @return array
   */
  private function _set_start(array $args, int $start): array
  {
    if (\is_array($args[0])
        && (isset($args[0]['tables']) || isset($args[0]['table']))
    ) {
      $args[0]['start'] = $start;
    }
    else {
      if (isset($args[5])) {
        $args[5] = $start;
      }
      else{
        while (count($args) < 6){
          switch (count($args)){
            case 1:
            case 2:
            case 3:
              $args[] = [];
              break;
            case 4:
              $args[] = 1;
              break;
            case 5:
              $args[] = $start;
              break;
          }
        }
      }
    }

    return $args;
  }


}
