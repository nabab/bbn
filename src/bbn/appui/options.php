<?php
/**
 * @package appui
 */
namespace bbn\appui;

use bbn;
use bbn\x;
use PHPMailer\PHPMailer\Exception;
use PhpOffice\PhpWord\Element\PageBreakTest;

/**
 * An all-in-one hierarchical options management system
 *
 * This class allows to:
 * ---------------------
 * * manage a **hierarchical** table of options
 * * retrieve, edit, add, remove options
 * * grab a whole tree
 * * apply functions on group of options
 * * add user-defined properties
 * * set option configuration and applies it to all its children
 * * And many more...
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Oct 28, 2015, 10:23:55 +0000
 * @category Appui x
 * @license http://opensource.org/licenses/MIT MIT
 * @version 0.2
 */


class options extends bbn\models\cls\db
{
  use bbn\models\tts\retriever;
  use bbn\models\tts\cache;
  use bbn\models\tts\dbconfig;

  //protected const root_hex = '962d50c3e07211e781c6000c29703ca2';
  protected const root_hex = 'c88846c3bff511e7b7d5000c29703ca2';

  protected static /** @var array */
    $default_class_cfg = [
      'errors' => [
      ],
      'table' => 'bbn_options',
      'tables' => [
        'options' => 'bbn_options'
      ],
      'arch' => [
        'options' => [
          'id' => 'id',
          'id_parent' => 'id_parent',
          'id_alias' => 'id_alias',
          'num' => 'num',
          'text' => 'text',
          'code' => 'code',
          'value' => 'value',
          'cfg' => 'cfg'
        ]
      ]
    ];

  private $is_init = false;

  /** @var array The fields from the options' table not returned by default*/
  private $non_selected = ['cfg'];

  /** @var array A store for parameters sent to @see from_code */
  private $_local_cache = [];

  /** @var array $class_cfg */
  protected $class_cfg;

  /** @var int The root ID of the options in the table */
  protected $root;

  /** @var int The default ID as parent */
  protected $default;


  private function _set_local_cache($name, $val): void
  {
    $this->_local_cache[$name] = $val;
  }


  private function _get_local_cache($name): ?string
  {
    return isset($this->_local_cache[$name]) ? $this->_local_cache[$name] : null;
  }


  /**
   * Transforms an array of parameters into valid option array
   * @param $it
   * @return bool
   */
  private function _prepare(array &$it): bool
  {
    // The table's columns
    $c =& $this->class_cfg['arch']['options'];

    // If id_parent is undefined it uses the default
    if (!isset($it[$c['id_parent']])) {
      $it[$c['id_parent']] = $this->default;
    }
    elseif (is_array($it[$c['id_parent']])) {
      if ($id_parent = $this->from_code(...$it[$c['id_parent']])) {
        $it[$c['id_parent']] = $id_parent;
      }
      else {
        throw new \Exception(_("Impossible to find the parent"));
      }
    }

    // Text is required and parent exists
    if (!empty($it[$c['id_parent']])
        && (!empty($it[$c['text']]) || !empty($it[$c['id_alias']]))
        && ($parent = $this->option($it[$c['id_parent']]))
    ) {
      // If the id_parent property is a code or a sequence of codes have to set it as uid
      $it[$c['id_parent']] = $parent[$c['id']];

      // If code is empty it MUST be null
      if (empty($it[$c['code']])) {
        $it[$c['code']] = null;
      }

      // If code is empty it MUST be null
      if (empty($it[$c['text']])) {
        $it[$c['text']] = '';
      }

      // Unsetting computed values
      if (isset($it[$c['value']]) && bbn\str::is_json($it[$c['value']])) {
        $this->_set_value($it);
      }

      if (array_key_exists('alias', $it)) {
        unset($it['alias']);
      }

      if (array_key_exists('num_children', $it)) {
        unset($it['num_children']);
      }

      if (array_key_exists('items', $it)) {
        unset($it['items']);
      }

      // Taking care of user-defined properties (contained in value)
      $value = [];
      foreach ($it as $k => $v){
        if (!\in_array($k, $c, true)) {
          $value[$k] = $v;
          unset($it[$k]);
        }
      }

      if (!empty($value)) {
        $it[$c['value']] = json_encode($value);
      }
      else{
        if (empty($it[$c['value']])) {
          $it[$c['value']] = null;
        }
        else{
          if (\is_array($it[$c['value']])) {
            $it[$c['value']] = json_encode($it[$c['value']]);
          }
        }
      }

      // Taking care of the config
      if (isset($it[$c['cfg']])) {
        if (is_array($it[$c['cfg']]) && !empty($it[$c['cfg']])) {
          $it[$c['cfg']] = json_encode($it[$c['cfg']]);
        }

        if (!bbn\str::is_json($it[$c['cfg']]) || in_array($it[$c['cfg']], ['{}', '[]'], true)) {
          $it[$c['cfg']] = null;
        }
      }

      $is_sortable = $this->is_sortable($parent['id']);
      // If parent is sortable and order is not defined we define it as last
      if (isset($it[$c['num']]) && !$is_sortable) {
        unset($it[$c['num']]);
      }
      elseif ($is_sortable && empty($it[$c['num']])) {
        $it[$c['num']] = $parent['num_children'] + 1;
      }

      if (empty($it[$c['id_alias']])) {
        $it[$c['id_alias']] = null;
      }
      elseif (!bbn\str::is_uid($it[$c['id_alias']])) {
        $it[$c['id_alias']] = $this->from_code($it[$c['id_alias']]);
      }

      return true;
    }

    return false;
  }


  /**
   * Gives to option's database row array each of the column value's JSON properties
   * Only if value is an associative array value itself will be unset
   * @param array $opt
   * @return array|bool
   */
  private function _set_value(array &$opt): ?array
  {
    if (!empty($opt[$this->class_cfg['arch']['options']['value']]) && bbn\str::is_json($opt[$this->class_cfg['arch']['options']['value']])) {
      $val = json_decode($opt[$this->class_cfg['arch']['options']['value']], true);
      if (bbn\x::is_assoc($val)) {
        foreach ($val as $k => $v){
          if (!isset($opt[$k])) {
            $opt[$k] = $v;
          }
        }

        unset($opt[$this->class_cfg['arch']['options']['value']]);
      }
      else{
        $opt[$this->class_cfg['arch']['options']['value']] = $val;
      }
    }

    return $opt;
  }


  /**
   * Gets the first row from a result
   * @param $where
   * @return bool
   */
  protected function get_row(array $where): ?array
  {
    if ($res = $this->get_rows($where, 1)) {
      return $res[0];
    }

    return null;
  }


  /**
   * Performs the actual query with a where parameter.
   * Always returns the whole result without limit
   * @param array $where The where config for the database query
   * @param int   $limit Max number of rows
   * @param int   $start Where to start the query (only if limit is > 1)
   * @return array|false An array of rows, empty if not found, false if there is an error in the where config
   */
  protected function get_rows(array $where = [], int $limit = 0, int $start = 0): ?array
  {
    $db  =& $this->db;
    $tab = $this->class_cfg['table'];
    $c   =& $this->class_cfg['arch']['options'];
    /** @todo Checkout */
    $cols = [];
    foreach ($c AS $k => $col){
      // All the columns except cfg
      if (!\in_array($k, $this->non_selected, true)) {
        $cols[] = $db->cfn($col, $tab);
      }
    }

    $cols['num_children'] = 'COUNT('.$db->escape($db->cfn($this->class_cfg['arch']['options']['id'], $tab.'2', true)).')';
    $todo                 = [$c['id'], $c['id_parent'], $c['id_alias']];
    foreach ($todo as $to){
      if (!empty($where[$to]) && !\bbn\str::is_buid($where[$to])) {
        $where[$to] = $where[$to];
      }
      elseif (!empty($where[$this->db->cfn($to, $tab)]) && !\bbn\str::is_buid($where[$this->db->cfn($to, $tab)])) {
        $where[$this->db->cfn($to, $tab)] = $where[$this->db->cfn($to, $tab)];
      }
    }

    return $this->db->rselect_all(
      [
      'tables' => [$tab],
      'fields' => $cols,
      'join' => [
        [
          'type' => 'left',
          'table' => $tab,
          'alias' => $tab.'2',
          'on' => [
            'conditions' => [
              [
                'field' => $db->cfn($c['id_parent'], $tab.'2'),
                'operator' => 'eq',
                'exp' => $db->cfn($c['id'], $tab, true)
              ]
            ],
            'logic' => 'AND'
          ]
        ]
      ],
      'where' => $where,
      'group_by' => [$this->db->cfn($c['id'], $tab)],
      'order' => [
        $this->db->cfn($c['id'], $tab)
      ],
      'limit' => $limit,
      'start' => $start
      ]
    );
  }


  /**
   * Returns the existing instance if there is
   * ```php
   * $opt = bbn\appui\options::get_options();
   * bbn\x::dump($opt);
   * // (options)
   * ```
   * @return options
   */
  public static function get_options(): self
  {
    return self::get_instance();
  }


  /**
   * Constructor
   *
   * ```php
   * $db = new bbn\db();
   * $opt = new bbn\appui\options($db);
   * ```
   *
   * @param bbn\db $db  a database connection object
   * @param array  $cfg configuration array
   */
  public function __construct(bbn\db $db, array $cfg = [])
  {
    parent::__construct($db);
    $this->_init_class_cfg($cfg);
    self::retriever_init($this);
  }


  public function check(): bool
  {
    return $this->init() && $this->db->check();
  }


  public function init(): bool
  {
    if (!$this->is_init) {
      $this->cache_init();
      $t          =& $this;
      $this->root = $this->cache_get_set(
        function () use (&$t) {
          return $t->db->select_one('bbn_options', 'id', ['id_parent' => null, 'code' => 'root']);
        },
        'root',
        'root',
        60
      );
      if (!$this->root) {
        return false;
      }

      if (\defined('BBN_APP_NAME')) {
        $this->default = $this->cache_get_set(
          function () use (&$t) {
            $res = $t->db->select_one(
              'bbn_options',
              'id',
              [
                'id_parent' => $this->root,
                'code' => BBN_APP_NAME
              ]
            );
            if (!$res) {
              $res = $t->root;
            }

            return $res;
          },
          BBN_APP_NAME,
          BBN_APP_NAME,
          60
        );
      }
      else {
        $this->default = $this->root;
      }

      $this->is_init;
    }

    return true;
  }


  /**
   * Deletes the options' cache, specifically for an ID or globally
   * If specific, it will also destroy the cache of the parent
   *
   * ```php
   * $opt->option->delete_cache(25)
   * // This is chainable
   * // ->...
   * ```

   * @param int     $id   The option's ID
   * @param boolean $deep If sets to true, children's cache will also be deleted
   * @param boolean $subs Used internally only for deleting children's cache without their parent
   * @return options
   */
  public function delete_cache($id = null, $deep = false, $subs = false): self
  {
    if ($this->check()) {
      if (bbn\str::is_uid($id)) {
        if (($deep || !$subs) && ($items = $this->items($id))) {
          foreach ($items as $it){
            $this->delete_cache($it, $deep, true);
          }
        }

        $this->cache_delete($id);
        if (!$subs) {
          $this->cache_delete($this->get_id_parent($id));
        }
      }
      elseif (is_null($id)) {
        $this->cache_delete_all();
      }
    }

    return $this;
  }


  /**
   * Returns the configuration array of the class with the table structure
   *
   * ```php
   * bbn\x::dump($opt->get_class_cfg());
   * /*
   * array [
   *   'errors' => [
   *   ],
   *   'table' => 'bbn_options',
   *   'cols' => [
   *     'id' => 'id',
   *     'id_parent' => 'id_parent',
   *     'id_alias' => 'id_alias',
   *     'text' => 'text',
   *     'code' => 'code',
   *     'value' => 'value',
   *     'cfg' => 'cfg'
   *   ]
   * ]
   * ```
   *
   * @return array
   */
  public function get_class_cfg(): array
  {
    return $this->class_cfg;
  }


  /**
   * Gets an option ID from diverse combinations of elements:
   * - A code or a serie of codes from the most specific to a child of the root
   * - A code or a serie of codes and an id_parent where to find the last code
   * - A code alone having $this->default as parent
   *
   * ```php
   * bbn\x::dump($opt->from_code(25));
   * // (int) 25
   * bbn\x::dump($opt->from_code('bbn_ide'));
   * // (int) 25
   * bbn\x::dump($opt->from_code('test', 58));
   * // (int) 42
   * bbn\x::dump($opt->from_code('test', 'users', 'bbn_ide'));
   * // (int) 42
   * ```
   *
   * @param mixed $code
   * @return null|string The ID of the option or false if the row cannot be found
   */
  public function from_code($code = null): ?string
  {
    if ($this->check()) {
      $args = \func_get_args();
      // An array can be used as parameters too
      while (isset($args[0]) && \is_array($args[0])){
        $args = $args[0];
      }

      // If we get an option array as param
      if (isset($args['id'])) {
        return $args['id'];
      }

      $num = \count($args);
      if (!$num) {
        return null;
      }

      // False is accepted as id_parent for root
      if (($num === 1) && ($args[0] === false)) {
        return $this->default;
      }

      if (bbn\str::is_uid($args[0])) {
        if ($num === 1) {
          return $args[0];
        }

        // If there are extra arguments with the ID we check that they correspond to its parent (that would be an extra check)
        if ($this->get_id_parent($args[0]) === $this->from_code(...\array_slice($args, 1))) {
          return $args[0];
        }
      }

      // We can use whatever alphanumeric value for code
      if (empty($args) || (!\is_string($args[0]) && !is_numeric($args[0]))) {
        return null;
      }

      // They must all have the same form at start with an id_parent as last argument
      if (!bbn\str::is_uid(end($args))) {
        $args[] = $this->default;
        $num++;
      }

      // At this stage we need at least one code and one id
      if ($num < 2) {
        return null;
      }

      // So the target has always the same name
      // This is the full name with all the arguments plus the root
      // eg ['c1', 'c2', 'c3', UID]
      // UID-c3-c4-c5
      // UID-c3-c4
      // UID-c3
      // Using the code(s) as argument(s) from now
      $id_parent = array_pop($args);
      $true_code = array_pop($args);
      $enc_code  = base64_encode($true_code);
      // This is the cache name
      // get_code_(base64(first_code))
      $cache_name = 'get_code_'.$enc_code;
      // UID-get_code_(base64(first_code))
      if (($tmp = $this->cache_get($id_parent, $cache_name))) {
        if (!count($args)) {
          return $tmp;
        }

        $args[] = $tmp;
        return $this->from_code(...$args);
      }

      $c =& $this->class_cfg;
      /** @var int|false $tmp */
      if (!$tmp && ($tmp = $this->db->select_one(
        $c['table'], $c['arch']['options']['id'], [
          $c['arch']['options']['id_parent'] => $id_parent,
          $c['arch']['options']['code'] => $true_code
        ]
      ))
      ) {
        $this->cache_set($id_parent, $cache_name, $tmp);
      }

      if ($tmp) {
        if (\count($args)) {
          $args[] = $tmp;
          return $this->from_code(...$args);
        }

        return $tmp;
      }
    }

    return null;
  }


  public function from_root_code(): ?string
  {
    if ($this->check()) {
      $def = $this->default;
      $this->set_default($this->root);
      $res = $this->from_code(...func_get_args());
      $this->set_default($def);
      return $res;
    }

    return null;
  }


  public function set_value(array $value, $id): ?int
  {
    if ($this->check() && $this->exists($id)) {
      $c =& $this->class_cfg;
      $this->cache_delete($id);
      return $this->db->update(
        $c['table'],
        [$c['arch']['options']['value'] => json_encode($value)],
        [$c['arch']['options']['id'] => $id]
      );
    }

    return null;
  }


  /**
   * Returns the ID of the root option - mother of all
   *
   * ```php
   * bbn\x::dump($opt->get_root());
   * // (int)0
   * ```
   *
   * @return int
   */
  public function get_root(): ?string
  {
    if ($this->check()) {
      return $this->root;
    }

    return null;
  }


  /**
   * Returns the ID of the default option ($id_parent used when not provided)
   *
   * ```php
   * bbn\x::dump($opt->get_default());
   * // (int) 0
   * $opt->set_default(5);
   * bbn\x::dump($opt->get_default());
   * // (int) 5
   * $opt->set_default();
   * bbn\x::dump($opt->get_default());
   * // (int) 0
   * ```
   *
   * @return int
   */
  public function get_default(): ?string
  {
    if ($this->check()) {
      return $this->default;
    }

    return null;
  }


  /**
   * Makes an option act as if it was the root option
   * It will be the default $id_parent for options requested by code
   *
   * ```php
   * bbn\x::dump($opt->get_default());
   * // (int) 0
   * // Default root option
   * $new = $opt->from_code('test');
   * // false
   * // Option not found
   * $opt->set_default($new);
   * // Default is now 5
   * bbn\x::dump($opt->get_default());
   * // (int) 5
   * bbn\x::dump($opt->from_code('test));
   * // (int) 24
   * // Returns the ID (24) of a child of option 5 with code 'test'
   * $opt->set_default();
   * // Default is back to root
   * bbn\x::dump($opt->get_default());
   * // (int) 0
   * ```
   *
   * @param string $uid
   * @return options
   */
  public function set_default($uid): self
  {
    if ($this->check() && $this->exists($uid)) {
      $this->default = $uid;
    }

    return $this;
  }


  /**
   * Returns an array of the children's IDs of the given option sorted by order or text
   *
   * ```php
   * bbn\x::dump($opt->tree_ids(12));
   * // array [40, 41, 42, 44, 45, 43, 46, 47]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false array of IDs, sorted or false if option not found
   */
  public function items($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))) {
      if (($res = $this->cache_get($id, __FUNCTION__)) !== false) {
        return $res;
      }

      $cfg = $this->get_cfg($id) ?: [];
      if ($cfg || $this->exists($id)) {
        // If not sortable returning an array ordered by text
        $order = empty($cfg['sortable']) ? [
            $this->class_cfg['arch']['options']['text'] => 'ASC',
            $this->class_cfg['arch']['options']['code'] => 'ASC',
            $this->class_cfg['arch']['options']['id'] => 'ASC',
          ] : [
            $this->class_cfg['arch']['options']['num'] => 'ASC',
            $this->class_cfg['arch']['options']['text'] => 'ASC',
            $this->class_cfg['arch']['options']['code'] => 'ASC',
            $this->class_cfg['arch']['options']['id'] => 'ASC',
          ];
        $res   = $this->db->get_column_values(
          $this->class_cfg['table'],
          $this->class_cfg['arch']['options']['id'], [
          $this->class_cfg['arch']['options']['id_parent'] => $id,
          ], $order
        );
        $this->cache_set($id, __FUNCTION__, $res);
        return $res;
      }
    }

    return null;
  }


  /**
   * Returns an option's row as stored in its original form in the database
   *
   * ```php
   * bbn\x::dump($opt->native_option(25));
   * /*
   * array [
   *   'id' => 25,
   *   'code' => "bbn_ide",
   *   'text' => "BBN's own IDE",
   *   'id_alias' => null,
   *   'value' => "{\"myProperty\":\"My property's value\"}"
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false Row or false if the option cannot be found
   */
  public function native_option($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))) {
      if ($opt = $this->cache_get($id, __FUNCTION__)) {
        return $opt;
      }

      $tab = $this->db->tsn($this->class_cfg['table']);
      $cfn = $this->db->cfn($this->class_cfg['arch']['options']['id'], $tab);
      $opt = $this->get_row([$cfn => $id]);
      if ($opt) {
        $this->cache_set($id, __FUNCTION__, $opt);
        return $opt;
      }
    }

    return null;
  }


  public function native_options($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))) {
      $res = [];
      if ($its = $this->items($id)) {
        foreach ($its as $it){
          $res[] = $this->native_option($it);
        }
      }

      return $res;
    }

    return null;
  }


  /**
   * Returns an option's row as stored in its original form in the database, including cfg
   *
   * ```php
   * bbn\x::dump($opt->raw_option('databases', 'appui'));
   * /*
   * array [
   *   'id' => "77cea323f0ce11e897fd525400007196",
   *   'code' => "bbn_ide",
   *   'text' => "BBN's own IDE",
   *   'cfg' => null,
   *   'id_alias' => null,
   *   'value' => "{\"num\":1}"
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false Row or false if the option cannot be found
   */
  public function raw_option($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))) {
      return $this->db->rselect($this->class_cfg['table'], [], [$this->class_cfg['arch']['options']['id'] => $id]);
    }

    return null;
  }


  /**
   * Returns an option's items  as stored in its original form in the database, including cfg
   *
   * ```php
   * bbn\x::dump($opt->raw_options('databases', 'appui'));
   * /*
   * [
   *   [
   *      'id' => "77cea323f0ce11e897fd525400007196",
   *      'code' => "bbn_ide",
   *      'text' => "BBN's own IDE",
   *      'cfg' => null,
   *      'id_alias' => null,
   *      'value' => "{\"num\":1}"
   *    ], [
   *      'id' => "77cea323f0ce11e897fd525400007196",
   *      'code' => "bbn_ide",
   *      'text' => "BBN's own IDE",
   *      'cfg' => null,
   *      'id_alias' => null,
   *      'value' => "{\"num\":1}"
   *    ]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false Row or false if the option cannot be found
   */
  public function raw_options($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))) {
      $res = [];
      if ($its = $this->items($id)) {
        foreach ($its as $it) {
          $res[] = $this->db->rselect($this->class_cfg['table'], [], [$this->class_cfg['arch']['options']['id'] => $it]);
        }
      }

      return $res;
    }

    return null;
  }


  /**
   * Returns a hierarchical structure as stored in its original form in the database
   *
   * ```php
   * bbn\x::dump($opt->native_raw_tree('77cea323f0ce11e897fd525400007196'));
   * /*
   * array [
   *   'id' => 12,
   *   'code' => "bbn_ide",
   *   'text' => "BBN's own IDE",
   *   'id_alias' => null,
   *   'value' => "{\"myProperty\":\"My property's value\"}",
   *   'items' => [
   *     [
   *       'id' => 25,
   *       'code' => "test",
   *       'text' => "Test",
   *       'id_alias' => null,
   *       'value' => "{\"myProperty\":\"My property's value\"}",
   *     ],
   *     [
   *       'id' => 26,
   *       'code' => "test2",
   *       'text' => "Test 2",
   *       'id_alias' => null,
   *       'value' => "{\"myProperty\":\"My property's value\"}",
   *       'items' => [
   *         [
   *           'id' => 42,
   *           'code' => "test8",
   *           'text' => "Test 8",
   *           'id_alias' => null,
   *           'value' => "{\"myProperty\":\"My property's value\"}",
   *         ]
   *       ]
   *     ],
   *   ]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false Tree's array or false if the option cannot be found
   */
  public function raw_tree($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))) {
      if ($res = $this->raw_option($id)) {
        $its = $this->items($id);
        if (\count($its)) {
          $res['items'] = [];
          foreach ($its as $it){
            $res['items'][] = $this->raw_tree($it);
          }
        }

        return $res;
      }
    }

    return null;
  }


  /**
   * Returns an option's full content as an array without its values changed by id_alias
   *
   * ```php
   * bbn\x::dump($opt->option(25));
   * bbn\x::dump($opt->option('bbn_ide'));
   * bbn\x::dump($opt->option('TEST', 58));
   * bbn\x::dump($opt->option('test3', 'users', 'bbn_ide'));
   * /* Each would return an array of this form
   * array [
   *   'id' => 31,
   *   'code' => "bbn_ide",
   *   'text' => "This is BBN's IDE",
   *   'id_alias' => 16,
   *   'myIntProperty' => 56854,
   *   'myTextProperty' => "<h1>Hello\nWorld</h1>",
   *   'myArrayProperty' => ['value1' => 1, 'value2' => 2]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false The option array or false if the option cannot be found
   */
  public function option_no_alias($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))
        && ($opt = $this->native_option($id))
    ) {
      $this->_set_value($opt);
      return $opt;
    }

    return null;
  }


  public function get_value($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))
        && ($opt = $this->native_option($id))
        && !empty($opt[$this->class_cfg['arch']['options']['value']])
        && bbn\str::is_json($opt[$this->class_cfg['arch']['options']['value']])
    ) {
      return json_decode($opt[$this->class_cfg['arch']['options']['value']], true);
    }

    return null;
  }


  /**
   * Returns an option's full content as an array
   *
   * ```php
   * bbn\x::dump($opt->option(25));
   * bbn\x::dump($opt->option('bbn_ide'));
   * bbn\x::dump($opt->option('TEST', 58));
   * bbn\x::dump($opt->option('test', 'users', 'bbn_ide'));
   * /* Each would return an array of this form
   * array [
   *   'id' => 25,
   *   'code' => "bbn_ide",
   *   'text' => "This is BBN's IDE",
   *   'myIntProperty' => 56854,
   *   'myTextProperty' => "<h1>Hello\nWorld</h1>",
   *   'myArrayProperty' => ['value1' => 1, 'value2' => 2]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false The option array or false if the option cannot be found
   */
  public function option($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))
        && ($opt = $this->native_option($id))
    ) {
      $this->_set_value($opt);
      $c =& $this->class_cfg['arch']['options'];
      if (bbn\str::is_uid($opt[$c['id_alias']]) && ($opt['alias'] = $this->native_option($opt[$c['id_alias']]))) {
        if ($opt[$c['id_alias']] === $id) {
          throw new \Exception(_("Impossible to have the same ID as ALIAS, check out ID").' '.$id);
        }
        else {
          $this->_set_value($opt['alias']);
        }
      }

      return $opt;
    }

    return null;
  }


  /**
   * Returns an array of options in the form id => text
   *
   * ```php
   * bbn\x::dump($opt->options(12));
   * /*
   * [
   *   21 => "My option 21",
   *   22 => "My option 22",
   *   25 => "My option 25",
   *   27 => "My option 27"
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false An indexed array of id/text options or false if option not found
   */
  public function options($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))) {
      //var_dump("MY ID: $id");
      if ($r = $this->cache_get($id, __FUNCTION__)) {
        return $r;
      }

      $opt = $this->db->rselect_all(
        $this->class_cfg['table'],
        [$this->class_cfg['arch']['options']['id'], $this->class_cfg['arch']['options']['text']],
        [$this->class_cfg['arch']['options']['id_parent'] => $id],
        [$this->class_cfg['arch']['options']['text'] => 'ASC']
      );
      $res = [];
      foreach ($opt as $r){
        $res[$r[$this->class_cfg['arch']['options']['id']]] = $r[$this->class_cfg['arch']['options']['text']];
      }

      $this->cache_set($id, __FUNCTION__, $res);
      return $res;
    }

    return null;
  }


  /**
   * Returns an array of children options in the form code => text
   *
   * ```php
   * bbn\x::dump($opt->options_by_code(12));
   * /*
   * array [
   *   'opt21' => "My option 21",
   *   'opt22' => "My option 22",
   *   'opt25' => "My option 25",
   *   'opt27' => "My option 27"
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false An indexed array of code/text options or false if option not found
   */
  public function options_by_code($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))) {
      if ($r = $this->cache_get($id, __FUNCTION__)) {
        return $r;
      }

      $opt = $this->db->select_all_by_keys(
        $this->class_cfg['table'],
        [$this->class_cfg['arch']['options']['code'], $this->class_cfg['arch']['options']['text']],
        [$this->class_cfg['arch']['options']['id_parent'] => $id],
        [$this->class_cfg['arch']['options']['text'] => 'ASC']
      );
      $this->cache_set($id, __FUNCTION__, $opt);
      return $opt;
    }

    return null;
  }


  /**
   * Returns an option's children array of id and text in a user-defined indexed array
   *
   * ```php
   * bbn\x::dump($opt->text_value_option(12, 'title'));
   * /* value comes from the default argument
   * array [
   *   ['title' => "My option 21", 'value' =>  21],
   *   ['title' => "My option 22", 'value' =>  22],
   *   ['title' => "My option 25", 'value' =>  25],
   *   ['title' => "My option 27", 'value' =>  27]
   * ]
   * ```
   *
   * @param int|string $id    The option's ID or its code if it is children of {@link default}
   * @param string     $text  The text field name for text column
   * @param string     $value The value field name for id column
   * @return array Options' list in a text/value indexed array
   */
  public function text_value_options($id, string $text = 'text', string $value = 'value'): ?array
  {
    $res = [];
    if ($opts = $this->full_options($id)) {
      $cfg = $this->get_cfg($id) ?: [];
      $i   = 0;
      foreach ($opts as $k => $o) {
        if (!isset($is_array)) {
          $is_array = \is_array($o);
        }

        $res[$i] = [
          'text' => $is_array ? $o['text'] : $o,
          'value' => $is_array ? $o['id'] : $k
        ];
        if (!empty($cfg['show_code'])) {
          $res[$i]['code'] = $o['code'];
        }

        /*
        if ( !empty($cfg['schema']) ){
          if ( \is_string($cfg['schema']) ){
            $cfg['schema'] = json_decode($cfg['schema'], true);
          }
          foreach ( $cfg['schema'] as $s ){
            if ( !empty($s['field']) ){
              $res[$i][$s['field']] = $o[$s['field']] ?? null;
            }
          }
        }
        */
        $i++;
      }
    }

    return $res;
  }


  public function siblings(): ?array
  {
    if ($id = $this->from_code(...func_get_args())) {
      return array_filter(
        $this->full_options($this->get_id_parent($id)), function ($a) use ($id) {
          return $a['id'] !== $id;
        }
      );
    }

    return null;
  }


  /**
   * Returns an array of full options arrays for a given parent
   *
   * ```php
   * bbn\x::dump($opt->full_options(12));
   * /*
   * array [
   *   ['id' => 21, 'id_parent' => 12, 'title' => "My option 21", 'myProperty' =>  "78%"],
   *   ['id' => 22, 'id_parent' => 12, 'title' => "My option 22", 'myProperty' =>  "26%"],
   *   ['id' => 25, 'id_parent' => 12, 'title' => "My option 25", 'myProperty' =>  "50%"],
   *   ['id' => 27, 'id_parent' => 12, 'title' => "My option 27", 'myProperty' =>  "40%"]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false A list of parent if option not found
   */
  public function full_options($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))) {
      $list = $this->items($id);
      if (\is_array($list)) {
        $res = [];
        foreach ($list as $i){
          if ($tmp = $this->option($i)) {
            $res[] = $tmp;
          }
          else {
            throw new \Exception(_("Impossible to find the ID").' '.$i);
          }
        }

        return $res;
      }
    }

    return null;
  }


  /**
   * Returns an array of full options arrays for a given parent
   *
   * ```php
   * bbn\x::dump($opt->full_options(12));
   * /*
   * array [
   *   ['id' => 21, 'id_parent' => 12, 'title' => "My option 21", 'myProperty' =>  "78%"],
   *   ['id' => 22, 'id_parent' => 12, 'title' => "My option 22", 'myProperty' =>  "26%"],
   *   ['id' => 25, 'id_parent' => 12, 'title' => "My option 25", 'myProperty' =>  "50%"],
   *   ['id' => 27, 'id_parent' => 12, 'title' => "My option 27", 'myProperty' =>  "40%"]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false A list of parent if option not found
   */
  public function code_options($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))) {
      $list = $this->items($id);
      if (\is_array($list)) {
        $res = [];
        foreach ($list as $i){
          $o               = $this->option($i);
          $res[$o['code']] = [
            'id' => $o['id'],
            'code' => $o['code'],
            'text' => $o['text']
          ];
        }

        return $res;
      }
    }

    return null;
  }


  public function code_ids($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))) {
      $list = $this->items($id);
      if (\is_array($list)) {
        $res = [];
        foreach ($list as $i){
          $o               = $this->option($i);
          $res[$o['code']] = $o['id'];
        }

        return $res;
      }
    }

    return null;
  }


  public function get_aliases($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))) {
      $r = [];
      foreach ($this->db->rselect_all('bbn_options', [], ['id_alias' => $id]) as $d){
        $this->_set_value($d);
        $r[] = $d;
      }

      return $r;
    }

    return null;
  }


  /**
   * Returns an id-indexed array of full options arrays for a given parent
   *
   * ```php
   * bbn\x::dump($opt->full_options(12));
   * /*
   * array [
   *   21 => ['id' => 21, 'id_parent' => 12, 'title' => "My option 21", 'myProperty' =>  "78%"],
   *   22 => ['id' => 22, 'id_parent' => 12, 'title' => "My option 22", 'myProperty' =>  "26%"],
   *   25 => ['id' => 25, 'id_parent' => 12, 'title' => "My option 25", 'myProperty' =>  "50%"],
   *   27 => ['id' => 27, 'id_parent' => 12, 'title' => "My option 27", 'myProperty' =>  "40%"]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false A list of parent if option not found
   */
  public function full_options_by_id($code = null): ?array
  {
    $res = [];
    if ($opt = $this->full_options(\func_get_args())) {
      $cf = $this->get_class_cfg();
      foreach ($opt as $o){
        $res[$o[$cf['arch']['options']['id']]] = $o;
      }
    }

    return $opt === null ? $opt : $res;
  }


  /**
   * Returns an id-indexed array of full options arrays for a given parent
   *
   * ```php
   * bbn\x::dump($opt->full_options(12));
   * /*
   * array [
   *   21 => ['id' => 21, 'id_parent' => 12, 'title' => "My option 21", 'myProperty' =>  "78%"],
   *   22 => ['id' => 22, 'id_parent' => 12, 'title' => "My option 22", 'myProperty' =>  "26%"],
   *   25 => ['id' => 25, 'id_parent' => 12, 'title' => "My option 25", 'myProperty' =>  "50%"],
   *   27 => ['id' => 27, 'id_parent' => 12, 'title' => "My option 27", 'myProperty' =>  "40%"]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false A list of parent if option not found
   */
  public function full_options_by_code($code = null): ?array
  {
    $res = [];
    if ($opt = $this->full_options(\func_get_args())) {
      $cf = $this->get_class_cfg();
      foreach ($opt as $o){
        $res[$o[$cf['arch']['options']['code']]] = $o;
      }
    }

    return $opt === null ?: $res;
  }


  /**
   * Returns an id-indexed array of full options with the config in arrays for a given parent
   *
   * ```php
   * bbn\x::dump($opt->full_options_cfg(12));
   * /*
   * array [
   *   ['id' => 21, 'id_parent' => 12, 'num' => 1, 'title' => "My option 21", 'myProperty' =>  "78%", 'cfg' => ['sortable' => true, 'desc' => "I am a description"]],
   *   ['id' => 22, 'id_parent' => 12, 'num' => 2, 'title' => "My option 22", 'myProperty' =>  "26%", 'cfg' => ['desc' => "I am a description"]],
   *   ['id' => 25, 'id_parent' => 12, 'num' => 3, 'title' => "My option 25", 'myProperty' =>  "50%", 'cfg' => ['desc' => "I am a description"]],
   *   ['id' => 27, 'id_parent' => 12, 'num' => 4, 'title' => "My option 27", 'myProperty' =>  "40%", 'cfg' => ['desc' => "I am a description"]]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false A list of parent if option not found
   */
  public function full_options_cfg($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))) {
      $o =& $this;
      return $this->map(
        function ($a) use ($o) {
          $a['cfg'] = $o->get_cfg($a['id']);
          return $a;
        }, $id
      );
    }

    return null;
  }


  /**
   * Returns an id-indexed array of options in the form id => text for a given grandparent
   *
   * ```php
   * bbn\x::dump($opt->soptions(12));
   * /*
   * [
   *   21 => "My option 21",
   *   22 => "My option 22",
   *   25 => "My option 25",
   *   27 => "My option 27",
   *   31 => "My option 31",
   *   32 => "My option 32",
   *   35 => "My option 35",
   *   37 => "My option 37"
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false indexed on id/text options or false if parent not found
   */
  public function soptions($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))) {
      $r = [];
      if ($ids = $this->items($id)) {
        foreach ($ids as $i => $txt){
          $o = $this->options($i);
          if (\is_array($o)) {
            $r = bbn\x::merge_arrays($r, $o);
          }
        }
      }

      return $r;
    }

    return null;
  }


  /**
   * Returns an id-indexed array of full options arrays for a given parent
   *
   * ```php
   * bbn\x::dump($opt->full_soptions(12));
   * /*
   * array [
   *   ['id' => 21, 'id_parent' => 20, 'title' => "My option 21", 'myProperty' =>  "78%"],
   *   ['id' => 22, 'id_parent' => 20, 'title' => "My option 22", 'myProperty' =>  "26%"],
   *   ['id' => 25, 'id_parent' => 20, 'title' => "My option 25", 'myProperty' =>  "50%"],
   *   ['id' => 27, 'id_parent' => 20, 'title' => "My option 27", 'myProperty' =>  "40%"],
   *   ['id' => 31, 'id_parent' => 30, 'title' => "My option 31", 'myProperty' =>  "88%"],
   *   ['id' => 32, 'id_parent' => 30, 'title' => "My option 32", 'myProperty' =>  "97%"],
   *   ['id' => 35, 'id_parent' => 30, 'title' => "My option 35", 'myProperty' =>  "12%"],
   *   ['id' => 37, 'id_parent' => 30, 'title' => "My option 37", 'myProperty' =>  "4%"]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false A list of options or false if parent not found
   */
  public function full_soptions($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code($code))) {
      $r = [];
      if ($ids = $this->items($id)) {
        foreach ($ids as $id){
          $o = $this->full_options($id);
          if (\is_array($o)) {
            $r = bbn\x::merge_arrays($r, $o);
          }
        }
      }

      return $r;
    }

    return null;
  }


  /**
   * Returns a flat array of all IDs found in a hierarchical structure (except the top one)
   * The second parameter is private and should be left blank
   *
   * ```php
   * bbn\x::dump($opt->tree_ids(12));
   * // array [12, 21, 22, 25, 27, 31, 32, 35, 37, 40, 41, 42, 44, 45, 43, 46, 47]
   * ```
   *
   * @param int   $id  The end/target of the path
   * @param array $res The resulting array
   * @return array|bool
   */
  public function tree_ids($id, &$res = []): ?array
  {
    if ($this->check() && $this->exists($id)) {
      if ($its = $this->items($id)) {
        foreach ($its as $it){
          $this->tree_ids($it, $res);
        }
      }

      $res[] = $id;
      return $res;
    }

    return null;
  }


  /**
   * Returns a hierarchical structure as stored in its original form in the database
   *
   * ```php
   * bbn\x::dump($opt->native_tree(12));
   * /*
   * array [
   *   'id' => 12,
   *   'code' => "bbn_ide",
   *   'text' => "BBN's own IDE",
   *   'id_alias' => null,
   *   'value' => "{\"myProperty\":\"My property's value\"}",
   *   'items' => [
   *     [
   *       'id' => 25,
   *       'code' => "test",
   *       'text' => "Test",
   *       'id_alias' => null,
   *       'value' => "{\"myProperty\":\"My property's value\"}",
   *     ],
   *     [
   *       'id' => 26,
   *       'code' => "test2",
   *       'text' => "Test 2",
   *       'id_alias' => null,
   *       'value' => "{\"myProperty\":\"My property's value\"}",
   *       'items' => [
   *         [
   *           'id' => 42,
   *           'code' => "test8",
   *           'text' => "Test 8",
   *           'id_alias' => null,
   *           'value' => "{\"myProperty\":\"My property's value\"}",
   *         ]
   *       ]
   *     ],
   *   ]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false Tree's array or false if the option cannot be found
   */
  public function native_tree($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))) {
      if ($res = $this->native_option($id)) {
        $its = $this->items($id);
        if (\count($its)) {
          $res['items'] = [];
          foreach ($its as $it){
            $res['items'][] = $this->native_tree($it);
          }
        }

        return $res;
      }
    }

    return null;
  }


  /**
   * Returns a simple hierarchical structure with just text, id and items
   *
   * ```php
   * bbn\x::dump($opt->tree(12));
   * /*
   * array [
   *  ['id' => 1, 'text' => 'Hello', 'items' => [
   *    ['id' => 7, 'text' => 'Hello from inside'],
   *    ['id' => 8, 'text' => 'Hello 2 from inside']
   *  ],
   * [
   *   ['id' => 1, 'text' => 'World']
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|bool
   */
  public function tree($code = null): ?array
  {
    $id = $this->from_code(\func_get_args());
    if (bbn\str::is_uid($id) && ($text = $this->text($id))) {
      $res = [
        'id' => $id,
        'text' => $text
      ];
      if ($opts = $this->items($id)) {
        $res['items'] = [];
        foreach ($opts as $o){
          if ($t = $this->tree($o)) {
            $res['items'][] = $t;
          }
        }
      }

      return $res;
    }

    return null;
  }


  /**
   * Returns a full hierarchical structure of options from a given option
   *
   * ```php
   * bbn\x::dump($opt->full_tree(12));
   * /*
   * array [
   *   'id' => 12,
   *   'code' => "bbn_ide",
   *   'text' => "BBN's own IDE",
   *   'id_alias' => null,
   *   'myProperty' => "My property's value",
   *   'items' => [
   *     [
   *       'id' => 25,
   *       'code' => "test",
   *       'text' => "Test",
   *       'id_alias' => null,
   *       'myProperty' => "My property's value",
   *     ],
   *     [
   *       'id' => 26,
   *       'code' => "test2",
   *       'text' => "Test 2",
   *       'id_alias' => null,
   *       'myProperty' => "My property's value",
   *       'items' => [
   *         [
   *           'id' => 42,
   *           'code' => "test8",
   *           'text' => "Test 8",
   *           'id_alias' => null,
   *           'myProperty' => "My property's value",
   *         ]
   *       ]
   *     ],
   *   ]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false Tree's array or false if the option cannot be found
   */
  public function full_tree($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))
        && ($res = $this->option($id))
    ) {
      $res['items'] = [];
      if ($opts = $this->items($id)) {
        foreach ($opts as $o){
          if ($t = $this->full_tree($o)) {
            $res['items'][] = $t;
          }
        }
      }
      else{
        unset($res['items']);
      }

      return $res;
    }

    return null;
  }


  /**
   * Returns a formatted content of the cfg column as an array
   * Checks if the parent option has inheritance and sets array accordingly
   * Parent rules will be applied if with the following inheritance values:
   * - 'children': if the option is the direct parent
   * - 'cascade': any level of parenthood
   *
   * ```php
   * bbn\x::dump($opt->get_cfg(25));
   * /*
   * array [
   *   'sortable' => true,
   *   'cascade' => true,
   *   'id_alias' => null,
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false The formatted array or false if the option cannot be found
   */
  public function get_cfg($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))) {
      if ($tmp = $this->cache_get($id, __FUNCTION__)) {
        return $tmp;
      }

      $c   =& $this->class_cfg;
      $cfg = $this->db->select_one($c['table'], $c['arch']['options']['cfg'], [$c['arch']['options']['id'] => $id]);
      $cfg = bbn\str::is_json($cfg) ? json_decode($cfg, true) : [];
      // Looking for parent with inheritance
      $parents = array_reverse($this->parents($id));
      $last    = \count($parents) - 1;
      foreach ($parents as $i => $p){
        $parent_cfg = $this->db->select_one($c['table'], $c['arch']['options']['cfg'], [$c['arch']['options']['id'] => $p]);
        $parent_cfg = bbn\str::is_json($parent_cfg) ? json_decode($parent_cfg, true) : [];
        if (!empty($parent_cfg['scfg']) && ($i === $last)) {
          $cfg                 = array_merge((array)$cfg, $parent_cfg['scfg']);
          $cfg['inherit_from'] = $p;
          $cfg['frozen']       = 1;
          break;
        }

        if (!empty($parent_cfg['inheritance']) || !empty($parent_cfg['scfg']['inheritance'])) {
          if ((($i === $last)
              && (              ($parent_cfg['inheritance'] === 'children')
              || (!empty($parent_cfg['scfg']) && ($parent_cfg['scfg']['inheritance'] === 'children')))              )
              || (($parent_cfg['inheritance'] === 'cascade')
              || (!empty($parent_cfg['scfg']) && ($parent_cfg['scfg']['inheritance'] === 'cascade'))              )
          ) {
            // Keeping in the option cfg properties which don't exist in the parent
            $cfg                 = array_merge((array)$cfg, $parent_cfg['scfg'] ?? $parent_cfg);
            $cfg['inherit_from'] = $p;
            $cfg['frozen']       = 1;
            break;
          }
          elseif (!count($cfg)
              && (($parent_cfg['inheritance'] === 'default')
              || (!empty($parent_cfg['scfg']) && ($parent_cfg['scfg']['inheritance'] === 'default'))              )
          ) {
            $cfg                 = $parent_cfg['scfg'] ?? $parent_cfg;
            $cfg['inherit_from'] = $p;
          }
        }
      }

      $mandatories = ['show_code', 'show_alias', 'show_value', 'show_icon', 'sortable', 'allow_children', 'frozen'];
      foreach ($mandatories as $m){
        $cfg[$m] = empty($cfg[$m]) ? 0 : 1;
      }

      $cfg['id']   = $id;
      $mandatories = ['desc', 'inheritance'];
      foreach ($mandatories as $m){
        $cfg[$m] = empty($cfg[$m]) ? '' : $cfg[$m];
      }

      $mandatories = ['controller', 'schema', 'form', 'default_value'];
      foreach ($mandatories as $m){
        $cfg[$m] = empty($cfg[$m]) ? null : $cfg[$m];
      }

      $this->cache_set($id, __FUNCTION__, $cfg);
      return $cfg;
    }

    return null;
  }


  /**
   * Returns the raw content of the cfg column for the given option.
   * 
   * ```php
   * bbn\x::dump($opt->get_cfg(25));
   * /*
   * array [
   *   'sortable' => true,
   *   'cascade' => true,
   *   'id_alias' => null,
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false The formatted array or false if the option cannot be found
   */
  public function get_raw_cfg($code = null): ?string
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))) {
      $c   =& $this->class_cfg;
      return $this->db->select_one($c['table'], $c['arch']['options']['cfg'], [$c['arch']['options']['id'] => $id]);
    }

    return null;
  }


  /**
   * Returns a formatted content of the cfg column as an array from the option's parent
   *
   * ```php
   * bbn\x::dump($opt->get_parent_cfg(42));
   * /*
   * [
   *   'sortable' => true,
   *   'cascade' => true,
   *   'id_alias' => null,
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false config or false if the option cannot be found
   */
  public function get_parent_cfg($code = null): ?array
  {
    $id        = $this->from_code(\func_get_args());
    $id_parent = $this->get_id_parent($id);
    if ($id_parent !== false) {
      return $this->get_cfg($id_parent);
    }

    return null;
  }


  /**
   * Returns an array of id_parents from the option selected to root
   *
   * ```php
   * bbn\x::dump($opt->parents(48));
   * // array [25, 12, 0]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false The array of parents' ids, an empty array if no parent (root case), and false if it can't find the option
   */
  public function parents($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))) {
      $res = [];
      while (bbn\str::is_uid($id_parent = $this->get_id_parent($id))){
        if (\in_array($id_parent, $res, true)) {
          break;
        }
        else{
          if ($id === $id_parent) {
            break;
          }
          else{
            $res[] = $id_parent;
            $id    = $id_parent;
          }
        }
      }

      return $res;
    }

    return null;
  }


  /**
   * Returns an array of id_parents from the selected root to the given id_option
   *
   * ```php
   * bbn\x::dump($opt->parents(48));
   * // array [0, 12, 25, 48]
   * bbn\x::dump($opt->parents(48, 12));
   * // array [12, 25, 48]
   * ```
   *
   * @param string      $id_option
   * @param string|null $id_root
   * @return array|null The array of parents' ids, an empty array if no parent (root case), and false if it can't find the option
   */
  public function sequence(string $id_option, string $id_root = null): ?array
  {
    if (null === $id_root) {
      $id_root = self::root_hex;
    }

    if ($this->exists($id_root) && ($parents = $this->parents($id_option))) {
      $res = [$id_option];
      foreach ($parents as $p){
        array_unshift($res, $p);
        if ($p === $id_root) {
          return $res;
        }
      }
    }

    return null;
  }


  /**
   * Returns the parent option's ID
   *
   * ```php
   * bbn\x::dump($opt->get_id_parent(48));
   * // (int)25
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return int|false The parent's ID, null if no parent, or false if option cannot be found
   */
  public function get_id_parent($code = null): ?string
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args())) && ($o = $this->native_option($id))) {
      return $o['id_parent'];
    }

    return null;
  }


  /**
   * Returns the parent's option as {@link option()}
   *
   * ```php
   * bbn\x::hdump($opt->parent(42));
   * /*
   * array [
   *   'id' => 25,
   *   'code' => "bbn_ide",
   *   'text' => "This is BBN's IDE",
   *   'myIntProperty' => 56854,
   *   'myTextProperty' => "<h1>Hello\nWorld</h1>",
   *   'myArrayProperty' => ['value1' => 1, 'value2' => 2]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false
   */
  public function parent($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))
        && ($id_parent = $this->get_id_parent($id))
    ) {
      return $this->option($id_parent);
    }

    return null;
  }


  /**
   * Return true if row with ID $id_parent is parent at any level of row with ID $id
   *
   * ```php
   * bbn\x::dump($opt->is_parent(42, 12));
   * // (bool) true
   * bbn\x::dump($opt->is_parent(42, 13));
   * // (bool) false
   * ```
   *
   * @param $id
   * @param $id_parent
   * @return bool
   */
  public function is_parent($id, $id_parent): bool
  {
    // Preventing infinite loop
    $done = [$id];
    if (bbn\str::is_uid($id, $id_parent)) {
      while ($id = $this->get_id_parent($id)){
        if ($id === $id_parent) {
          return true;
        }

        if (\in_array($id, $done, true)) {
          break;
        }

        $done[] = $id;
      }
    }

    return false;
  }


  /**
   * Returns an array of options in the form id => code
   * @todo Add cache
   *
   * ```php
   * bbn\x::dump($opt->get_codes());
   * /*
   * array [
   *   21 => "opt21",
   *   22 => "opt22",
   *   25 => "opt25",
   *   27 => "opt27"
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|false Options' array
   */
  public function get_codes($code = null): ?array
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))) {
      $c   =& $this->class_cfg['arch']['options'];
      $opt = $this->db->rselect_all($this->class_cfg['table'], [$c['id'], $c['code']], [$c['id_parent'] => $id], [($this->is_sortable($id) ? $c['num'] : $c['code']) => 'ASC']);
      $res = [];
      foreach ($opt as $r){
        $res[$r[$c['id']]] = $r[$c['code']];
      }

      return $res;
    }

    return [];
  }


  /**
   * Returns an option's code
   *
   * ```php
   * bbn\x::dump($opt->code(12));
   * // (string) bbn_ide
   * ```
   *
   * @param string $id The options' ID
   * @return string|null|false The code value, null is none, false if option not found
   */
  public function code(string $id): ?string
  {
    if ($this->check() && bbn\str::is_uid($id)) {
      return $this->db->select_one(
        $this->class_cfg['table'], $this->class_cfg['arch']['options']['code'], [
        $this->class_cfg['arch']['options']['id'] => $id
        ]
      );
    }

    return null;
  }


  /**
   * Returns an option's text
   *
   * ```php
   * bbn\x::dump($opt->text(12));
   * // (string) BBN's own IDE
   * bbn\x::dump($opt->text('bbn_ide'));
   * // (string) BBN's own IDE
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return string Text of the option
   */
  public function text($code = null): ?string
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))) {
      return $this->db->select_one(
        $this->class_cfg['table'], $this->class_cfg['arch']['options']['text'], [
        $this->class_cfg['arch']['options']['id'] => $id
        ]
      );
    }

    return null;
  }


  /**
   * Returns the id_alias relative to the given id_option
   *
   * @param string $uid
   * @return string|null
   */
  public function alias(string $id): ?string
  {
    if ($this->check() && bbn\str::is_uid($id)) {
      return $this->db->select_one(
        $this->class_cfg['table'], $this->class_cfg['arch']['options']['id_alias'], [
        $this->class_cfg['arch']['options']['id'] => $id
        ]
      );
    }

    return null;
  }


  /**
   * Returns translation of an option's text
   *
   * ```php
   * bbn\x::dump($opt->itext(12));
   * // Result of _("BBN's own IDE") with fr as locale
   * // (string) L'IDE de BBN
   * bbn\x::dump($opt->itext('bbn_ide'));
   * // (string) L'IDE de BBN
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return string Text of the option
   */
  public function itext($code = null): ?string
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))) {
      $val = $this->db->select_one(
        $this->class_cfg['table'], $this->class_cfg['arch']['options']['text'], [
        $this->class_cfg['arch']['options']['id'] => $id
        ]
      );
      if ($val) {
        return _($val);
      }
    }

    return null;
  }


  /**
   * Returns the number of children for a given option
   *
   * ```php
   * bbn\x::dump($opt->count('bbn_ide'));
   * // (int) 4
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return int|false The number of children or false if option not found
   */
  public function count($code = null): ?int
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))) {
      return $this->db->count($this->class_cfg['table'], [$this->class_cfg['arch']['options']['id_parent'] => $id]);
    }

    return null;
  }


  /**
   * Returns an array of options based on their id_alias
   *
   * ```php
   * bbn\x::dump($opt->options_by_alias(36));
   * /*
   * array [
   *   ['id' => 18, 'text' => "My option 1", 'code' => "opt1", 'myProperty' => "50%"],
   *   ['id' => 21, 'text' => "My option 4", 'code' => "opt4", 'myProperty' => "60%"],
   *   ['id' => 23, 'text' => "My option 6", 'code' => "opt6", 'myProperty' => "90%"]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|bool
   */
  public function options_by_alias($code = null): ?array
  {
    $id_alias = $this->from_code(\func_get_args());
    if (bbn\str::is_uid($id_alias)) {
      $where = [$this->class_cfg['arch']['options']['id_alias'] => $id_alias];
      $list  = $this->get_rows($where);
      if (\is_array($list)) {
        $res = [];
        foreach ($list as $i){
          $res[] = $this->option($i);
        }

        return $res;
      }
    }

    return null;
  }


  /**
   * Tells if an option has its config set as sortable or no
   *
   * ```php
   * bbn\x::dump($opt->is_sortable(12));
   * // (bool) false
   * bbn\x::dump($opt->is_sortable(21));
   * // (bool) true
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return bool
   */
  public function is_sortable($code = null): ?bool
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))) {
      $cfg = $this->get_cfg($id);
      return empty($cfg['sortable']) ? false : true;
    }

    return null;
  }


  /**
   * Returns an array of codes for each option between $id and $root without $root's code
   *
   * ```php
   * bbn\x::dump($opt->get_path_array(48, 12));
   * // array ["path", "to", "my", "option"]
   * ```
   *
   * @param int $id   The end/target of the path
   * @param int $root The start/origin of the path, {@link get_default()} if is null
   * @return array|bool
   */
  public function get_path_array($id, $root = null): ?array
  {
    if (!isset($root)) {
      $root = $this->get_default();
    }

    if ($code = $this->code($id)) {
      $parts = [];
      while ($id && ($id !== $root)){
        array_unshift($parts, $code);
        if (!($id = $this->get_id_parent($id))) {
          return null;
        }

        $code = $this->code($id);
      }

      return $parts;
    }

    return null;
  }


  /**
   * Returns the closest ID option from a _path_ of codes, with separator and optional id_parent
   *
   * ```php
   * bbn\x::dump("bbn_ide|test1|test8"));
   * // (int) 36
   * ```
   *
   * @param string      $path   The path made of a concatenation of path and $sep until the target
   * @param string      $sep    The separator
   * @param null|string $parent An optional id_parent, {@link get_default()} otherwise
   * @return null|string
   */
  public function from_path(string $path, string $sep = '|', $parent = null): ?string
  {
    if ($this->check()) {
      if (!empty($sep)) {
        $parts = explode($sep, $path);
      }
      else{
        $parts = [$path];
      }

      if (null === $parent) {
        $parent = $this->default;
      }

      foreach ($parts as $p){
        if (!($parent = $this->from_code($p, $parent))) {
          break;
        }
      }

      return $parent ?: null;
    }

    return null;
  }


  /**
   * Concatenates the codes and separator $sep of a a line of options
   *
   * ```php
   * bbn\x::dump($opt->to_path(48, '|', 12)
   * // (string) path|to|my|option
   * ```
   *
   * @param int    $id     The end/target of the path
   * @param string $sep    The separator
   * @param int    $parent The start/origin of the path
   * @return string|false The path concatenated with the separator or false if no path
   */
  public function to_path($id, $sep = '|', $parent = null): ?string
  {
    if ($this->check() && ($parts = $this->get_path_array($id, $parent))) {
      return implode($sep, $parts);
    }

    return null;
  }


  /**
   * Creates a new option or a new hierarchy by adding row(s) in the options' table
   *
   * ```php
   * bbn\x::dump($opt->add([
   *   'id_parent' => $opt->from_code('bbn_ide'),
   *   'text' => 'My new option',
   *   'code' => 'new_opt',
   *   'myProperty' => 'my value'
   * ]));
   * // (int) 49  New ID
   * bbn\x::dump($opt->add([
   *   'id_parent' => $opt->from_code('bbn_ide'),
   *   'text' => 'My new option',
   *   'code' => 'new_opt',
   *   'myProperty' => 'my value'
   *   'items' => [
   *     [
   *       'code' => "test",
   *       'text' => "Test",
   *       'myProperty' => "My property's value",
   *     ],
   *     [
   *       'code' => "test2",
   *       'text' => "Test 2",
   *       'myProperty' => "My property's value",
   *       'items' => [
   *         [
   *           'code' => "test8",
   *           'text' => "Test 8",
   *         ]
   *       ]
   *     ]
   *   ]
   * ], true, true));
   * // (int) 4 Number of inserted/modified rows
   * ```
   *
   * @param array   $it         The option configuration
   * @param boolean $force      Determines if the option should be updated if it already exists
   * @param boolean $return_num If set to true the function will return the number of rows inserted otherwise the ID of the newly created option
   * @return int|string|false
   */
  public function add(array $it, $force = false, $return_num = false)
  {
    if ($this->check()) {
      $res   = null;
      $items = !empty($it['items']) && \is_array($it['items']) ? $it['items'] : false;
      $id    = null;
      if ($this->_prepare($it)) {
        $c =& $this->class_cfg['arch']['options'];
        if ($it[$c['code']]) {
          $id = $this->db->select_one(
            $this->class_cfg['table'],
            $c['id'],
            [
              $c['id_parent'] => $it[$c['id_parent']],
              $c['code'] => $it[$c['code']]
            ]
          );
        }
        elseif (!empty($it[$c['id']])) {
          $id = $this->db->select_one(
            $this->class_cfg['table'],
            $c['id'],
            [
              $c['id'] => $it[$c['id']],
              $c['code'] => null
            ]
          );
        }

        if ($id
            && $force
            && (null !== $it[$c['code']])
        ) {
          $res = $this->db->update(
            $this->class_cfg['table'],
            [
              $c['text'] => $it[$c['text']],
              $c['id_alias'] => $it[$c['id_alias']],
              $c['value'] => $it[$c['value']],
              $c['num'] => $it[$c['num']] ?? null,
              $c['cfg'] => $it[$c['cfg']] ?? null
            ],
            [$c['id'] => $id]
          );
        }

        $values = [
          $c['id_parent'] => $it[$c['id_parent']],
          $c['text'] => $it[$c['text']],
          $c['code'] => empty($it[$c['code']]) ? null : $it[$c['code']],
          $c['id_alias'] => $it[$c['id_alias']],
          $c['value'] => $it[$c['value']],
          $c['num'] => $it[$c['num']] ?? null,
          $c['cfg'] => $it[$c['cfg']] ?? null
        ];
        if (!empty($it['id'])) {
          $values['id'] = $it['id'];
        }

        if (!$id
            && ($res = $this->db->insert($this->class_cfg['table'], $values))
        ) {
          $id = $this->db->last_id();
        }

        if ($res) {
          $this->delete_cache($id);
        }

        if ($items && bbn\str::is_uid($id)) {
          foreach ($items as $item){
            $item['id_parent'] = $id;
            $tmp               = (int)$this->add($item, $force, $return_num);
            if ($return_num && $tmp) {
              $res += $tmp;
            }
          }
        }
      }

      return $return_num ? $res : $id;
    }

    return null;
  }


  /**
   * Updates an option's row (without changing cfg)
   *
   * ```php
   * bbn\x::dump($opt->set(12, [
   *   'id_parent' => $opt->from_code('bbn_ide'),
   *   'text' => 'My new option',
   *   'code' => 'new_opt',
   *   'myProperty' => 'my value'
   *   'cfg' => [
   *     'sortable' => true,
   *     'Description' => "I am a cool option"
   *   ]
   * ]);
   * // (int) 1
   * ```
   *
   * @param int   $id
   * @param array $data
   * @return bool|int
   */
  public function set($id, array $data)
  {
    if ($this->check() && $this->_prepare($data)) {
      if (isset($data['id'])) {
        unset($data['id']);
      }

      $c =& $this->class_cfg['arch']['options'];
      // id_parent cannot be edited this way
      if ($res = $this->db->update(
        $this->class_data['table'],
        [
          $c['text'] => $data[$c['text']],
          $c['code'] => !empty($data[$c['code']]) ? $data[$c['code']] : null,
          $c['id_alias'] => !empty($data[$c['id_alias']]) ? $data[$c['id_alias']] : null,
          $c['value'] => $data[$c['value']]
        ],
        [$c['id'] => $id]
      )
      ) {
        $this->delete_cache($id);
        return $res;
      }

      return 0;
    }

    return null;
  }


  /**
   * Updates an option's row (without changing cfg)
   *
   * ```php
   * bbn\x::dump($opt->set(12, [
   *   'id_parent' => $opt->from_code('bbn_ide'),
   *   'text' => 'My new option',
   *   'code' => 'new_opt',
   *   'myProperty' => 'my value'
   *   'cfg' => [
   *     'sortable' => true,
   *     'Description' => "I am a cool option"
   *   ]
   * ]);
   * // (int) 1
   * ```
   *
   * @param int   $id
   * @param array $data
   * @return bool|int
   */
  public function merge(string $id, array $data, array $cfg = null)
  {
    if ($this->check()
        && ($o = $this->option($id))
    ) {
      if (!empty($data)) {
        $data = array_merge($o, $data);
        $c    =& $this->class_cfg['arch']['options'];
        $this->_prepare($data);
        if (isset($data[$c['id']])) {
          unset($data[$c['id']]);
        }
      }

      if ($cfg) {
        $ocfg = $this->get_raw_cfg($id);
        $data['cfg'] = json_encode(array_merge($ocfg ? json_decode($ocfg, true) : [], $cfg));
      }

      // id_parent cannot be edited this way
      if ($res = $this->db->update(
        $this->class_cfg['table'],
        $data,
        [$c['id'] => $id]
      )
      ) {
        $this->delete_cache($id);
        return $res;
      }

      return 0;
    }

    return null;
  }


  /**
   * Deletes a row from the options table, deletes the cache and fix order if needed
   *
   * ```php
   * bbn\x::dump($opt->remove(12));
   * // (int) 12 Number of options deleted
   * bbn\x::dump($opt->remove(12));
   * // (bool) false The option doesn't exist anymore
   * ```
   *
   * @param string $code Any option(s) accepted by {@link from_code()}
   * @return bool|int The number of affected rows or false if option not found
   */
  public function remove($code)
  {
    if (bbn\str::is_uid($id = $this->from_code(...\func_get_args()))
        && ($id !== $this->default)
        && ($id !== $this->root)
        && bbn\str::is_uid($id_parent = $this->get_id_parent($id))
    ) {
      $num = 0;
      if ($items = $this->items($id)) {
        foreach ($items as $it){
          $num += (int)$this->remove($it);
        }
      }

      $this->delete_cache($id);
      $num += (int)$this->db->delete(
        $this->class_cfg['table'], [
        $this->class_cfg['arch']['options']['id'] => $id
        ]
      );
      if ($this->is_sortable($id_parent)) {
        $this->fix_order($id_parent);
      }

      return $num;
    }

    return null;
  }


  /**
   * Deletes a row from the options table, deletes the cache and fix order if needed
   *
   * ```php
   * bbn\x::dump($opt->remove(12));
   * // (int) 12 Number of options deleted
   * bbn\x::dump($opt->remove(12));
   * // (bool) false The option doesn't exist anymore
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()} or the uid
   * @return bool|int The number of affected rows or false if option not found
   */
  public function remove_full($code)
  {
    if (bbn\str::is_uid($id = $this->from_code(\func_get_args()))
        && ($id !== $this->default)
        && ($id !== $this->root)
    ) {
      $res = 0;
      $this->delete_cache($id);
      $all = $this->tree_ids($id);

      $has_history = history::is_enabled() && history::is_linked($this->class_cfg['table']);
      foreach (array_reverse($all) as $a){
        if ($has_history) {
          $res += (int)$this->db->delete('bbn_history_uids', ['bbn_uid' => $a]);
        }
        else{
          $res += (int)$this->db->delete($this->class_cfg['table'], [$this->class_cfg['arch']['options']['id'] => $a]);
        }
      }

      $this->delete_cache($id);
      return $res;
    }

    return null;
  }


  /**
   * Sets the alias of an option
   *
   * ```php
   * bbn\x::dump($opt->set_alias(26, 32));
   * // (int) 1
   * ```
   *
   * @param int      $id    The ID of the option to be updated
   * @param int|null $alias The alias' option ID
   * @return int The number of affected rows
   */
  public function set_alias($id, $alias = null)
  {
    $res = null;
    if ($this->check()) {
      $res = $this->db->update_ignore(
        $this->class_cfg['table'], [
        $this->class_cfg['arch']['options']['id_alias'] => $alias ?: null
        ], [
        $this->class_cfg['arch']['options']['id'] => $id
        ]
      );
      if ($res) {
        $this->delete_cache($id);
      }
    }

    return $res;
  }


  /**
   * Sets the text of an option
   *
   * ```php
   * bbn\x::dump($opt->set_text(26, "Hello world!"));
   * // (int) 1
   * ```
   *
   * @param int    $id   The ID of the option to be updated
   * @param string $text The new text
   * @return int The number of affected rows
   */
  public function set_text($id, string $text)
  {
    $res = null;
    if ($this->check()) {
      $res = $this->db->update_ignore(
        $this->class_cfg['table'], [
        $this->class_cfg['arch']['options']['text'] => $text
        ], [
          $this->class_cfg['arch']['options']['id'] => $id
        ]
      );
      if ($res) {
        $this->delete_cache($id);
      }
    }

    return $res;
  }


  /**
   * Sets the code of an option
   *
   * ```php
   * bbn\x::dump($opt->set_code(26, "HWD"));
   * // (int) 1
   * ```
   *
   * @param int    $id   The ID of the option to be updated
   * @param string $code The new code
   * @return int The number of affected rows
   */
  public function set_code($id, string $code = null)
  {
    if ($this->check()) {
      return $this->db->update_ignore(
        $this->class_cfg['table'], [
        $this->class_cfg['arch']['options']['code'] => $code ?: null
        ], [
        $this->class_cfg['arch']['options']['id'] => $id
        ]
      );
    }

    return null;
  }


  /**
   * Returns the order of an option. Updates it if a position is given, and cascades
   *
   * ```php
   * bbn\x::dump($opt->items(20));
   * // [21, 22, 25, 27]
   * bbn\x::dump($opt->order(25));
   * // (int) 3
   * bbn\x::dump($opt->order(25, 2));
   * // (int) 2
   * bbn\x::dump($opt->items(20));
   * // [21, 25, 22, 27]
   * bbn\x::dump($opt->order(25));
   * // (int) 2
   * ```
   *
   * @param int $id  The ID of the option to update
   * @param int $pos The new position
   * @return int|false The new or existing order of the option or false if not found or not sortable
   */
  public function order($id, int $pos = null)
  {
    if ($this->check()
        && ($parent = $this->get_id_parent($id))
        && $this->is_sortable($parent)
    ) {
      $cf  = $this->class_cfg;
      $old = $this->db->select_one(
        $cf['table'], $cf['arch']['options']['num'], [
        $cf['arch']['options']['id'] => $id
        ]
      );
      if ($pos && ($old != $pos)) {
        $its      = $this->items($parent);
        $past_new = false;
        $past_old = false;
        $p        = 1;
        foreach ($its as $id_option){
          $upd = false;
          // Fixing order problem
          if ($past_old && !$past_new) {
            $upd = [$cf['arch']['options']['num'] => $p - 1];
          }
          elseif (!$past_old && $past_new) {
            $upd = [$cf['arch']['options']['num'] => $p + 1];
          }

          if ($id === $id_option) {
            $upd      = [$cf['arch']['options']['num'] => $pos];
            $past_old = 1;
          }
          elseif ($p === $pos) {
            $upd      = [$cf['arch']['options']['num'] => $p + ($pos > $old ? -1 : 1)];
            $past_new = 1;
          }

          if ($upd) {
            $this->db->update(
              $cf['table'], $upd, [
              $cf['arch']['options']['id'] => $id_option
              ]
            );
          }

          if ($past_new && $past_old) {
            break;
          }

          $p++;
        }

        $this->delete_cache($parent, true);
        $this->delete_cache($id);
        return $pos;
      }

      return $old;
    }

    return null;
  }


  /**
   * Updates option's properties derivated from the value column
   *
   * ```php
   * bbn\x::dump($opt->set_prop(12, 'myProperty', "78%"));
   * // (int) 1
   * bbn\x::dump($opt->set_prop(12, ['myProperty' => "78%"]));
   * // (int) 0 Already updated, no change done
   * bbn\x::dump($opt->set_prop(9654, ['myProperty' => "78%"]));
   * // (bool) false Option not found
   * bbn\x::dump($opt->set_prop(12, ['myProperty' => "78%", 'myProperty2' => "42%"]));
   * // (int) 1
   * bbn\x::dump($opt->option(12));
   * /*
   * Before
   * array [
   *   'id' => 12,
   *   'id_parent' => 0,
   *   'code' => 'bbn_ide',
   *   'text' => 'BBN's own IDE',
   *   'myOtherProperty' => "Hello",
   * ]
   * After
   * array [
   *   'id' => 12,
   *   'id_parent' => 0,
   *   'code' => 'bbn_ide',
   *   'text' => 'BBN's own IDE',
   *   'myProperty' => "78%",
   *   'myProperty2' => "42%",
   *   'myOtherProperty' => "Hello",
   * ]
   * ```
   *
   * @param int          $id   The option to update's ID
   * @param array|string $prop An array of properties and values, or a string with the property's name adding as next argument the new value
   * @return int|false the number of affected rows or false if no argument or option not found
   */
  public function set_prop($id, $prop)
  {
    if (!empty($id) && !empty($prop) && ($o = $this->option_no_alias($id))) {
      $args = \func_get_args();
      if (\is_string($prop) && isset($args[2])) {
        $prop = [$prop => $args[2]];
      }

      if (\is_array($prop)) {
        \bbn\x::log([$o, $prop], "set_prop");
        $change = false;
        foreach ($prop as $k => $v){
          //if ( !\in_array($k, $this->class_cfg['arch']['options']) ){
          if (!isset($o[$k]) || ($o[$k] !== $v)) {
            $change = true;
            $o[$k]  = $v;
          }

          //}
        }

        if ($change) {
          return $this->set($id, $o);
        }
      }

      return 0;
    }

    return null;
  }


  /**
   * Get an option's single property
   *
   * ```php
   * bbn\x::dump($opt->get_prop(12, 'myProperty'));
   * // (int) 78
   * bbn\x::dump($opt->set_prop(12, ['myProperty' => "78%"]));
   * // (int) 1
   * bbn\x::dump($opt->get_prop(12, 'myProperty'));
   * // (string) "78%"
   * ```
   *
   * @param int    $id   The option from which getting the property
   * @param string $prop The property's name
   * @return mixed|false The property's value, false if not found
   */
  public function get_prop($id, string $prop)
  {
    if (!empty($id) && !empty($prop) && ($o = $this->option($id)) && isset($o[$prop])) {
      return $o[$prop];
    }

    return null;
  }


  /**
   * Unset option's properties taken from the value column
   *
   * ```php
   * bbn\x::dump($opt->unset_prop(12, 'myProperty'));
   * // (int) 1
   * bbn\x::dump($opt->unset_prop(12, ['myProperty']));
   * // (int) 0 Already updated, no change done
   * bbn\x::dump($opt->unset_prop(9654, ['myProperty']));
   * // (bool) false Option not found
   * bbn\x::dump($opt->unset_prop(12, ['myProperty', 'myProperty2']));
   * // (int) 1
   * bbn\x::dump($opt->option(12));
   * /*
   * Before
   * array [
   *   'id' => 12,
   *   'id_parent' => 0,
   *   'code' => 'bbn_ide',
   *   'text' => 'BBN's own IDE',
   *   'myProperty' => "78%",
   *   'myProperty2' => "42%",
   *   'myOtherProperty' => "Hello",
   * ]
   * After
   * array [
   *   'id' => 12,
   *   'id_parent' => 0,
   *   'code' => 'bbn_ide',
   *   'text' => 'BBN's own IDE',
   *   'myOtherProperty' => "Hello",
   * ]
   * ```
   *
   * @param int          $id   The option to update's ID
   * @param array|string $prop An array of properties and values, or a string with the property's name adding as next argument the new value
   * @return int|false the number of affected rows or false if no argument or option not found
   */
  public function unset_prop($id, $prop)
  {
    if (!empty($prop) && bbn\str::is_uid($id) && ($o = $this->option_no_alias($id))) {
      if (\is_string($prop)) {
        $prop = [$prop];
      }

      if (\is_array($prop)) {
        $change = false;
        foreach ($prop as $k){
          if (!\in_array($k, $this->class_cfg['arch']['options'], true)) {
            $change = true;
            unset($o[$k]);
          }
        }

        if ($change) {
          return $this->set($id, $o);
        }
      }
    }

    return null;
  }


  /**
   * Sets the cfg column of a given option in the table through an array
   *
   * ```php
   * bbn\x::dump($opt->get_cfg('bbn_ide'));
   * // array ['sortable' => true]
   * bbn\x::dump($opt->set_cfg(12, [
   *   'desc' => "I am a cool option",
   *   'sortable' => true
   * ]));
   * // (int) 1
   * bbn\x::dump($opt->get_cfg('bbn_ide'));
   * // array ['desc' => "I am a cool option", 'sortable' => true];
   * ```
   *
   * @param int   $id  The option ID
   * @param array $cfg The config value
   * @return int|false number of affected rows
   */
  public function set_cfg($id, array $cfg)
  {
    if ($this->check() && $this->exists($id)) {
      if (isset($cfg['inherited_from'])) {
        unset($cfg['inherited_from']);
      }

      $old_cfg = $this->get_cfg($id);
      $c       =& $this->class_cfg;
      if ($res = $this->db->update(
        $c['table'], [
        $c['arch']['options']['cfg'] => $cfg ? json_encode($cfg) : null
        ], [
        $c['arch']['options']['id'] => $id
        ]
      )
      ) {
        if (($old_cfg['inheritance'] ?? null) !== ($cfg['inheritance'] ?? null)) {
          $this->delete_cache($id, true);
        }
        else{
          $this->delete_cache($id);
        }

        return $res;
      }

      return 0;
    }

    return null;
  }


  /**
   * Unsets the cfg column (sets it to null)
   *
   * ```php
   * bbn\x::dump($opt->get_cfg('bbn_ide'));
   * // array ['desc' => "I am a cool option", 'sortable' => true];
   * ```
   *
   * @param int $id The option ID
   * @return int|boolean Number of affected rows or false if not found
   */
  public function unset_cfg($id)
  {
    $res = false;
    if ($this->check() && $this->exists($id)) {
      $res = $this->db->update(
        $this->class_cfg['table'], [
        $this->class_cfg['arch']['options']['cfg'] => null
        ], [
        $this->class_cfg['arch']['options']['id'] => $id
        ]
      );
      if ($res) {
        $this->delete_cache($id);
      }
    }

    return $res;
  }


  /**
   * Merges an option $src into an existing option $dest
   * Children will change id_parent and references in the same database will be updated
   * The config will remain the one from the destination
   *
   * @todo Finish the example
   * ```php
   * bbn\x::dump($opt->option(20), $opt->option(30));
   * bbn\x::dump($opt->fusion(30, 20));
   * bbn\x::dump($opt->option(20));
   * // (int) 7
   * /* The expression before would have returned
   * array []
   * array []
   * And the resulting option would be
   * array []
   * ```
   *
   * @param int $src  Source option ID, will be
   * @param int $dest Destination option ID, will remain after the fusion
   * @return int Number of affected rows
   */
  public function fusion($src, $dest)
  {
    if ($this->check()) {
      $o_src  = $this->option($src);
      $o_dest = $this->option($dest);
      $num    = 0;
      $cf     =& $this->class_cfg['arch']['options'];
      if ($o_dest && $o_src) {
        $o_final = bbn\x::merge_arrays($o_src, $o_dest);
        // Order remains the dest one
        $o_final[$cf['num']] = $o_dest[$cf['num']];
        $tables              = $this->db->get_foreign_keys($this->class_cfg['arch']['options']['id'], $this->class_cfg['table']);
        foreach ($tables as $table => $cols){
          foreach ($cols as $c){
            $num += (int)$this->db->update($table, [$c => $dest], [$c => $src]);
          }
        }

        $opt = $this->options($src);
        // Moving children
        foreach ($opt as $id => $text){
          $num += (int)$this->move($id, $dest);
        }

        $num += (int)$this->set($dest, $o_final);
        $num += (int)$this->remove($src);

        $this->delete_cache($o_final['id_parent'], true);
        $this->delete_cache($o_src['id_parent'], true);

        if ($this->is_sortable($o_src['id_parent'])) {
          $this->fix_order($o_src['id_parent']);
        }

        if ($this->is_sortable($o_final['id_parent'])) {
          $this->fix_order($o_final['id_parent']);
        }
      }

      return $num;
    }

    return null;
  }


  /**
   * Changes the id_parent of an option
   *
   * ```php
   * bbn\x::dump($this->get_id_parent(21));
   * // (int) 13
   * bbn\x::dump($this->move(21, 12));
   * // (int) 1
   * bbn\x::dump($this->get_id_parent(21));
   * // (int) 12
   * ```
   *
   * @param int $id        The option's ID
   * @param int $id_parent The new id_parent
   * @return int|false
   */
  public function move($id, $id_parent)
  {
    $res = null;
    if (($o = $this->option($id))
        && ($target = $this->option($id_parent))
    ) {
      $upd = [$this->class_cfg['arch']['options']['id_parent'] => $id_parent];
      if ($this->is_sortable($id_parent)) {
        $upd[$this->class_cfg['arch']['options']['num']] = empty($target['num_children']) ? 1 : $target['num_children'] + 1;
      }

      $res = $this->db->update(
        $this->class_cfg['table'], $upd, [
        'id' => $id
        ]
      );
      $this->delete_cache($id_parent);
      $this->delete_cache($id);
      $this->delete_cache($o['id_parent']);
    }

    return $res;
  }


  /**
   * Sets the order configuration for each options of a sortable given parent
   *
   * ```php
   * bbn\x::dump($opt->items(12));
   * // array [20, 22, 25, 27]
   * bbn\x::dump($opt->fix_order(12)->items(12));
   * // array [25, 22, 27, 20]
   * ```
   *
   * @param int     $id
   * @param boolean $deep
   * @return $this
   */
  public function fix_order($id, $deep = false)
  {
    if ($this->check() && $this->is_sortable($id)) {
      $cf  =& $this->class_cfg;
      $its = $this->full_options($id);
      $p   = 1;
      foreach ($its as $it){
        if ($it['num'] !== $p) {
          $this->db->update(
            $cf['table'], [
            $cf['arch']['options']['num'] => $p
            ], [
            $cf['arch']['options']['id'] => $it[$cf['arch']['options']['id']]
            ]
          );
          $this->delete_cache($it[$cf['arch']['options']['id']]);
        }

        $p++;
        if ($deep) {
          $this->fix_order($it[$cf['arch']['options']['id']]);
        }
      }
    }

    return $this;
  }


  public function get_code_path($id)
  {
    $args = func_get_args();
    $res  = [];
    while ($o = $this->native_option(...$args)) {
      if ($o['code']) {
        $res[] = $o['code'];
        if ($o['id_parent'] === $this->default) {
          break;
        }

        $args = [$o['id_parent']];
      }
      else {
        return null;
      }
    }

    if (end($res) === 'root') {
      array_pop($res);
    }

    return count($res) ? $res : null;
  }


  public function analyze_out(array $options, array &$results = [])
  {
    if ($this->check()) {
      if (empty($results)) {
        $results['options'] = [];
        $results['ids']     = [];
        $results['aliases'] = [];
      }

      if (!empty($options['id'])) {
        $results['ids'][$options['id']] = null;
      }

      if (!empty($options['id_alias'])) {
        $results['aliases'][$options['id_alias']] = [
          'id' => null,
          'codes' => $this->get_code_path($options['id_alias'])
        ];
      }

      $items = false;
      if (!empty($options['items'])) {
        $items = $options['items'];
        unset($options['items']);
      }

      $results['options'][] = $options;
      if ($items) {
        foreach ($items as $it) {
          $this->analyze_out($it, $results);
        }
      }

      return $results;
    }

    return null;
  }


  /**
   * Converts an option or a hierarchy to a multi-level array with JSON values
   * If $return is false the resulting array will be printed
   *
   * ```php
   * ```
   *
   * @todo Example output
   * @param int     $id     The ID of the option to clone
   * @param boolean $deep   If set to true children will be included
   * @param boolean $return If set to true the resulting array will be returned
   * @return array|false
   */
  public function export($id, bool $deep = false, bool $return = false, bool $aliases = false)
  {
    if (($ret = $deep ? $this->raw_tree($id) : $this->raw_options($id))) {
      $ret  = $this->analyze_out($ret);
      $res  = [];
      $done = [];
      $max  = 3;
      foreach ($ret['options'] as $i => $o) {
        if (!$i || in_array($o['id_parent'], $done, true)) {
          if (empty($o['id_alias'])) {
            $res[]  = $o;
            $done[] = $o['id'];
          }
        }
      }

      while ($max && (count($res) < count($ret['options']))) {
        foreach ($ret['options'] as $i => $o) {
          if (!empty($o['id_alias'])
              && !in_array($o['id'], $done, true)
              && in_array($o['id_parent'], $done, true)
              && in_array($o['id_alias'], $done, true)
          ) {
            $res[]  = $o;
            $done[] = $o['id'];
          }
        }

        $max--;
      }

      if (count($res) < count($ret['options'])) {
        foreach ($ret['options'] as $i => $o) {
          if (!in_array($o['id_parent'], $done, true)) {
            $o['id_parent'] = $this->get_code_path($o['id_parent']);
          }

          if (!empty($o['id_alias'])
              && !in_array($o['id'], $done, true)
          ) {
            if (!in_array($o['id_alias'], $done, true)) {
              $code_path     = $this->get_code_path($o['id_alias']);
              $o['id_alias'] = $code_path ?: $o['id_alias'];
            }

            $res[]  = $o;
            $done[] = $o['id'];
          }
        }

        $max--;
      }

      return $return ? $res : var_export($res, 1);
    }

    return null;
  }


  /**
   * Insert into the option table an exported array of options
   *
   * ```php
   * ```
   *
   * @todo Usage example
   * @param array    $options   An array of option(s) as export returns it
   * @param int|null $id_parent The option target, if not specified {@link default}
   * @return int The number of affected rows
   */
  public function import(array $options, $id_parent = null, $return_num = false)
  {
    if ($this->check()) {
      $ids     = [];
      $num     = 0;
      $root_id = false;
      foreach ($options as $i => $o) {
        if (!$i) {
          $ids[$o['id_parent']] = $id_parent ?: $this->default;
          $o['id_parent'] = $id_parent ?: $this->default;
          if ($o['id_alias']) {
            $o['id_alias'] = is_array($o['id_alias']) ? $this->from_code(...$o['id_alias']) : null;
          }
        }
        elseif (isset($ids[$o['id_parent']])) {
          $o['id_parent'] = $ids[$o['id_parent']];
          if (!empty($o['id_alias'])) {
            if (is_array($o['id_alias'])) {
              $o['id_alias'] = $this->from_code(...$o['id_alias']);
            }
            elseif (isset($ids[$o['id_alias']])) {
              $o['id_alias'] = $ids[$o['id_alias']];
            }
            elseif (!$this->option($o['id_alias'])) {
              $o['id_alias'] = null;
            }
          }
        }
        else {
          throw new \Exception(_("Error while importing: no parent"));
        }

        if ($id = $this->add($o, true)) {

          $ids[$o['id']] = $id;
          $num++;
        }
        elseif ($i) {
          $this->remove_full($root_id);
          throw new \Exception(_("Error while importing: impossible to add"));
        }
      }

      return $return_num ? $num : $id;
    }

    return null;
  }


  /**
   * Copies and insert an option into a target option
   *
   * ```php
   * ```
   *
   * @todo Usage example
   * @param int     $id     The source option's ID
   * @param int     $target The destination option's ID
   * @param boolean $deep   If set to true, children will also be duplicated
   * @param boolean $force  If set to true and option exists it will be merged
   * @return bool|int The number of affected rows or false if option not found
   */
  public function duplicate($id, $target, $deep = false, $force = false, $return_num = false)
  {
    $res    = null;
    $target = $this->from_code($target);
    if (bbn\str::is_uid($target)) {
      if ($opt = $this->export($id, $deep, 1)) {
        $res = $this->import($opt, $target, $force, $return_num);
        $this->delete_cache($target);
      }
    }

    return $res;
  }


  /**
   * Applies a function to children of an option and updates the database
   *
   * ```php
   * ```
   *
   * @todo Usage example
   * @param callable  $f    The function to apply (the unique argument will be the option as in {@link option()}
   * @param int|array $id   The options'ID on which children the function should be applied
   * @param boolean   $deep If set to true the function will be applied to all children's levels
   * @return bool|int The number of affected rows or false if option not found
   */
  public function apply(callable $f, $id, $deep = false)
  {
    if ($this->check()) {
      $originals = \is_array($id) ? $id : ( $deep ? $this->full_tree($id) : $this->full_options($id) );
      if (isset($originals['items'])) {
        $originals = $originals['items'];
      }

      $opts = $this->map($f, $originals, $deep);
      if (\is_array($opts)) {
        $changes = 0;
        foreach ($opts as $i => $o){
          if ($originals[$i] !== $o) {
            $changes += (int)$this->set($o['id'], $o);
          }

          if ($deep && $o['num_children']) {
            $this->apply($f, $o, 1);
          }
        }

        return $changes;
      }
    }

    return null;
  }


  /**
   * Applies a function to children of an option
   *
   * ```php
   * ```
   *
   * @todo Usage example
   * @param callable  $f    The function to apply (the unique argument will be the option as in {@link option()}
   * @param int|array $id   The options'ID on which children the function should be applied
   * @param boolean   $deep If set to true the function will be applied to all children's levels
   * @return array|int The new array with the function applied
   */
  public function map(callable $f, $id, $deep = false)
  {
    $opts = \is_array($id) ? $id : ( $deep ? $this->full_tree($id) : $this->full_options($id) );
    $res  = [];
    if (\is_array($opts)) {
      if (isset($opts['items'])) {
        $opts = $opts['items'];
      }

      foreach ($opts as $i => $o){
        $opts[$i] = $f($o);
        if ($deep && $opts[$i] && !empty($opts[$i]['items'])) {
          $opts[$i]['items'] = $this->map($f, $opts[$i]['items'], 1);
        }

        if (\is_array($opts[$i])) {
          $res[] = $opts[$i];
        }
      }
    }

    return $res;
  }


  /**
   * Applies a function to children of an option, with the cfg array included
   *
   * ```php
   * ```
   *
   * @todo Usage example
   * @param callable  $f    The function to apply (the unique argument will be the option as in {@link option()}
   * @param int|array $id   The options'ID on which children the function should be applied
   * @param boolean   $deep If set to true the function will be applied to all children's levels
   * @return array|int The new array with the function applied
   */
  public function map_cfg(callable $f, $id, $deep = false)
  {
    $opts = \is_array($id) ? $id : ( $deep ? $this->full_tree($id) : $this->full_options($id) );
    if (isset($opts['items'])) {
      $opts = $opts['items'];
    }

    $res = [];
    if (\is_array($opts)) {
      foreach ($opts as $i => $o){
        $o['cfg'] = $this->get_cfg($o['id']);
        $opts[$i] = $f($o);
        if ($deep && $opts[$i] && !empty($opts[$i]['items'])) {
          $opts[$i]['items'] = $this->map($f, $opts[$i]['items'], 1);
        }

        if (\is_array($opts[$i])) {
          $res[] = $opts[$i];
        }
      }
    }

    return $res;
  }


  /**
   * Retourne la liste des catégories sous forme de tableau indexé sur son `id`
   *
   * @return array Liste des catégories
   */
  public function categories()
  {
    return $this->options(false);
  }


  /**
   * Retourne la liste des catégories indexée sur leur `id` sous la forme d'un tableau text/value
   *
   * @return array La liste des catégories dans un tableau text/value
   */
  public function text_value_categories()
  {
    if ($rs = $this->options(false)) {
      $res = [];
      foreach ($rs as $val => $text){
        $res[] = ['text' => $text, 'value' => $val];
      }

      return $res;
    }

    return null;
  }


  /**
   * Retourne toutes les caractéristiques des options d'une catégorie donnée dans un tableau indexé sur leur `id`
   *
   * @param string|int $cat La catégorie, sous la forme de son `id`, ou de son nom
   * @return array Un tableau des caractéristiques de chaque option de la catégorie, indexée sur leur `id`
   */
  public function full_categories()
  {
    $opts = $this->full_options(false);
    foreach ($opts as $k => $o){
      if (!empty($o['default'])) {
        $opts[$k]['fdefault'] = $this->text($o['default']);
      }
    }

    return $opts;
  }


  /**
   * Retourne toutes les caractéristiques des options d'une catégorie donnée dans un tableau indexé sur leur `id`
   *
   * @param string|int $cat La catégorie, sous la forme de son `id`, ou de son nom
   * @return array Un tableau des caractéristiques de chaque option de la catégorie, indexée sur leur `id`
   */
  public function js_categories($id = null)
  {
    if ($tmp = $this->cache_get($id, __FUNCTION__)) {
      return $tmp;
    }

    $res = [
      'categories' => []
    ];
    if ($cats = $this->full_options($id ?: false)) {
      foreach ($cats as $cat){
        if (!empty($cat['tekname'])) {
          $res[$cat['tekname']]          = $this->text_value_options($cat['id']);
          $res['categories'][$cat['id']] = $cat['tekname'];
        }
      }
    }

    $this->cache_set($id, __FUNCTION__, $res);
    return $res;
  }


  /**
   * Checks whether an option has _permissions_ in its parent cfg
   *
   * ```php
   * bbn\x::dump($opt->has_permission('bbn_ide'));
   * // (bool) true
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return bool
   */
  public function has_permission($code = null)
  {
    if (bbn\str::is_uid($p = $this->get_id_parent(\func_get_args()))) {
      $cfg = $this->get_cfg($p);
      return !empty($cfg['permissions']);
    }

    return null;
  }


  /**
   * Returns an array of _permissions_ from origin $id
   *
   * ```php
   * bbn\x::dump($opt->find_permissions());
   * /* Returns a full treeof permissions for all options
   * array []
   * ```
   *
   * @todo Returned comments to add
   * @param int|null $id   The origin's ID
   * @param boolean  $deep If set to true the children will also be searched
   * @return array|boolean An array of permissions if there are, false otherwise
   */
  public function find_permissions($id = null, $deep = false)
  {
    if ($this->check()) {
      if (\is_null($id)) {
        $id = $this->default;
      }

      $cfg = $this->get_cfg($id);
      if (!empty($cfg['permissions'])) {
        $perms = [];
        $opts  = $this->full_options_cfg($id);
        foreach ($opts as $opt){
          $o = [
            'icon' => $opt['icon'] ?? 'nf nf-fa-cog',
            'text' => $opt['text'],
            'id' => $opt['id']
          ];
          if ($deep && !empty($opt['cfg']['permissions'])) {
            $o['items'] = $this->find_permissions($opt['id'], true);
          }

          $perms[] = $o;
        }

        return $perms;
      }
    }

    return null;
  }


  /**
   * returns an array containing all options that have the property i18n setted
   */
  public function find_i18n($id = null, $items = true)
  {
    $res = [];
    if ($this->check()) {
      $opts = $this->db->rselect_all(
        $this->class_cfg['table'], [
        $this->class_cfg['arch']['options']['id'],
        $this->class_cfg['arch']['options']['id_parent'],
        $this->class_cfg['arch']['options']['text'],
        $this->class_cfg['arch']['options']['cfg']
        ]
      );
      foreach ($opts as $opt){
        $cfg = json_decode($opt['cfg'], true);
        if (!empty($cfg['i18n'])) {
          $opt['language'] = $cfg['i18n'];
          unset($opt['cfg']);
          if (!empty($items)) {
            $res[] = array_merge(
              $opt, ['items' => array_values(
                array_filter(
                  $opts, function ($o) use ($opt) {
                    return $o['id_parent'] === $opt['id'];
                  }
                )
              )]
            );
          }
          else {
            $res[] = $opt;
          }
        }
      }
    }

    return $res;
  }


  /**
   * returns an array containing the option (having the property i18n setted) corresponding to the given id
   */
  public function find_i18n_option($id, $items = true)
  {
    $res = [];
    if ($this->check()) {
      if ($opt = $this->db->rselect(
        $this->class_cfg['table'], [
          $this->class_cfg['arch']['options']['id'],
          $this->class_cfg['arch']['options']['id_parent'],
          $this->class_cfg['arch']['options']['text'],
          $this->class_cfg['arch']['options']['cfg']
        ], [$this->class_cfg['arch']['options']['id'] => $id]
      )
      ) {
        $opts = $this->full_options($id);
        $cfg  = json_decode($opt['cfg']);
        if (!empty($cfg->i18n)) {
          $opt['language'] = $cfg->i18n;
        }

        unset($opt['cfg']);
        if (!empty($items)) {
          $res[] = array_merge($opt, $opts);
        }
        else {
          $res[] = $opt;
        }
      }
    }

    return $res;
  }


}
