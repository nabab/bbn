<?php
/**
 * @package appui
 */
namespace bbn\Appui;

use Exception;
use bbn\Db;
use bbn\Str;
use bbn\X;
use bbn\Models\Tts\Retriever;
use bbn\Models\Tts\Cache;
use bbn\Models\Tts\DbActions;
use bbn\Models\Cls\Db as DbCls;
/**
 * An all-in-one hierarchical options management system
 *
 * ## This class allows to:
 * 
 * - manage a **hierarchical** table of options
 * - retrieve, edit, add, remove options
 * - grab a whole tree
 * - apply functions on group of options
 * - add user-defined properties
 * - set option configuration and applies it to all its children
 * - And many more...
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Oct 28, 2015, 10:23:55 +0000
 * @category Appui x
 * @license http://opensource.org/licenses/MIT MIT
 * @version 0.2
 */


class Option extends DbCls
{
  use Retriever;
  use Cache;
  use DbActions;

  //protected const root_hex = '962d50c3e07211e781c6000c29703ca2';
  protected const root_hex = 'c88846c3bff511e7b7d5000c29703ca2';

  protected static $default_class_cfg = [
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


  /**
   * Returns the existing instance if there is
   * ```php
   * $opt = Option::getOptions();
   * X::dump($opt);
   * // (options)
   * ```
   * @return Option
   */
  public static function getOptions(): self
  {
    return self::getInstance();
  }


  /**
   * Constructor
   *
   * ```php
   * $db = new Db();
   * $opt = new Appui\Options($db);
   * ```
   *
   * @param Db $db a database connection object
   * @param array $cfg configuration array
   * @throws Exception
   */
  public function __construct(Db $db, array $cfg = [])
  {
    parent::__construct($db);
    $this->_init_class_cfg($cfg);
    self::retrieverInit($this);
  }


  public function check(): bool
  {
    return $this->init() && $this->db->check();
  }

  public function exists()
  {
    return $this->dbTraitExists(...func_get_args());
  }


  public function init(): bool
  {
    if (!$this->is_init) {
      $this->cacheInit();
      $t          =& $this;
      $this->root = $this->cacheGetSet(
        function () use (&$t) {
          return $t->db->selectOne($t->class_cfg['table'], $t->fields['id'], [
            $t->fields['id_parent'] => null, $t->fields['code'] => 'root']);
        },
        'root',
        'root',
        60
      );
      if (!$this->root) {
        return false;
      }

      if (\defined('BBN_APP_NAME')) {
        $this->default = $this->cacheGetSet(
          function () use (&$t) {
            $res = $t->db->selectOne(
              $t->class_cfg['table'],
              $t->fields['id'],
              [
                $t->fields['id_parent'] => $this->root,
                $t->fields['code'] => BBN_APP_NAME
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

      $this->is_init = true;
    }

    return true;
  }


  /**
   * Sets the cache
   * @param string $id
   * @param string $method
   * @param mixed $data
   * @param string|null $locale
   * @return self
   */
  public function setCache(string $id, string $method, $data, ?string $locale = null)
  {
    if (empty($locale)) {
      $locale = $this->getTranslatingLocale($id);
    }

    if (!empty($locale)) {
      return $this->cacheSetLocale($id, $locale, $method, $data);
    }

    return $this->cacheSet($id, $method, $data);
  }


  /**
   * Gets the cache
   * @param string $id
   * @param string $method
   * @param string|null $locale
   * @return mixed
   */
  public function getCache(string $id, string $method, ?string $locale = null)
  {
    if (empty($locale)) {
      $locale = $this->getTranslatingLocale($id);
    }

    if (!empty($locale)) {
      return $this->cacheGetLocale($id, $locale, $method);
    }

    return $this->cacheGet($id, $method);
  }


  /**
   * Deletes the options' cache, specifically for an ID or globally
   * If specific, it will also destroy the cache of the parent
   *
   * ```php
   * $opt->option->deleteCache(25)
   * // This is chainable
   * // ->...
   * ```
   * @param string|null $id The option's ID
   * @param boolean $deep If sets to true, children's cache will also be deleted
   * @param boolean $subs Used internally only for deleting children's cache without their parent
   * @return Option
   */
  public function deleteCache(string $id = null, $deep = false, $subs = false): self
  {
    if ($this->check()) {
      if (Str::isUid($id)) {
        if (($deep || !$subs) && ($items = $this->items($id))) {
          foreach ($items as $it){
            $this->deleteCache($it, $deep, true);
          }
        }

        if (!$subs && ($id_alias = $this->alias($id))) {
          $this->deleteCache($id_alias, false, true);
        }

        $this->cacheDelete($id);
        if (!$subs) {
          $this->cacheDelete($this->getIdParent($id));
        }
      }
      elseif (is_null($id)) {
        $this->cacheDeleteAll();
      }
    }

    return $this;
  }


  /**
   * Returns the configuration array of the class with the table structure
   *
   * ```php
   * X::dump($opt->getClassCfg());
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
  public function getClassCfg(): array
  {
    return $this->class_cfg;
  }


  /**
   * Retrieves an option's ID from its "codes path"
   * 
   * Gets an option ID from diverse combinations of elements:
   * - A code or a serie of codes from the most specific to a child of the root
   * - A code or a serie of codes and an id_parent where to find the last code
   * - A code alone having $this->default as parent
   *
   * ```php
   * X::dump($opt->fromCode(25));
   * // (int) 25
   * X::dump($opt->fromCode('bbn_ide'));
   * // (int) 25
   * X::dump($opt->fromCode('test', 58));
   * // (int) 42
   * X::dump($opt->fromCode('test', 'users', 'bbn_ide'));
   * // (int) 42
   * ```
   *
   * @param mixed $code
   * @return null|string The ID of the option or false if the row cannot be found
   */
  public function fromCode($code = null): ?string
  {
    if ($this->check()) {
      $args = \func_get_args();
      // An array can be used as parameters too
      while (isset($args[0]) && \is_array($args[0])){
        $args = $args[0];
      }

      // If we get an option array as param
      if (isset($args[$this->fields['id']])) {
        return $args[$this->fields['id']];
      }

      $num = \count($args);
      if (!$num) {
        return null;
      }

      // False is accepted as id_parent for root
      if (($num === 1) && ($args[0] === false)) {
        return $this->default;
      }

      if (Str::isUid($args[0])) {
        if ($num === 1) {
          return $args[0];
        }

        // If there are extra arguments with the ID we check that they correspond to its parent (that would be an extra check)
        if ($this->getIdParent($args[0]) === $this->fromCode(...\array_slice($args, 1))) {
          return $args[0];
        }
      }

      // We can use whatever alphanumeric value for code
      if (empty($args) || (!\is_string($args[0]) && !is_numeric($args[0]))) {
        return null;
      }

      // They must all have the same form at start with an id_parent as last argument
      if (!Str::isUid(end($args))) {
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
      // get_codeX::_(base64(first_code))
      $cache_name = 'get_code_'.$enc_code;
      // UID-get_codeX::_(base64(first_code))
      if (($tmp = $this->cacheGet($id_parent, $cache_name))) {
        if (!count($args)) {
          return $tmp;
        }

        $args[] = $tmp;
        return $this->fromCode(...$args);
      }

      $c =& $this->class_cfg;
      $f =& $this->fields;
      /** @var int|false $tmp */
      if (!$tmp && ($tmp = $this->db->selectOne(
        $c['table'], $f['id'], [
          [$f['id_parent'], '=', $id_parent],
          [$f['code'], '=', $true_code]
        ]
      ))
      ) {
        $this->cacheSet($id_parent, $cache_name, $tmp);
      }

      if ($tmp) {
        if (\count($args)) {
          $args[] = $tmp;
          return $this->fromCode(...$args);
        }

        return $tmp;
      }
    }

    return null;
  }


  /**
   * @return string|null
   */
  public function fromRootCode(): ?string
  {
    if ($this->check()) {
      $def = $this->default;
      $this->setDefault($this->root);
      $res = $this->fromCode(...func_get_args());
      $this->setDefault($def);
      return $res;
    }

    return null;
  }


  /**
   * @param array $value
   * @param $id
   * @return int|null
   * @throws Exception
   */
  public function setValue(array $value, $id): ?int
  {
    if ($this->check() && $this->dbTraitExists($id)) {
      $c =& $this->class_cfg;
      $f =& $this->fields;
      $this->cacheDelete($id);
      return $this->db->update(
        $c['table'],
        [$f['value'] => json_encode($value)],
        [$f['id'] => $id]
      );
    }

    return null;
  }


  /**
   * Returns the ID of the root option - mother of all
   *
   * ```php
   * X::dump($opt->getRoot());
   * // (int)0
   * ```
   *
   * @return string|null
   */
  public function getRoot(): ?string
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
   * X::dump($opt->getDefault());
   * // (int) 0
   * $opt->setDefault(5);
   * X::dump($opt->getDefault());
   * // (int) 5
   * $opt->setDefault();
   * X::dump($opt->getDefault());
   * // (int) 0
   * ```
   *
   * @return string|null
   */
  public function getDefault(): ?string
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
   * X::dump($opt->getDefault());
   * // (int) 0
   * // Default root option
   * $new = $opt->fromCode('test');
   * // false
   * // Option not found
   * $opt->setDefault($new);
   * // Default is now 5
   * X::dump($opt->getDefault());
   * // (int) 5
   * X::dump($opt->fromCode('test));
   * // (int) 24
   * // Returns the ID (24) of a child of option 5 with code 'test'
   * $opt->setDefault();
   * // Default is back to root
   * X::dump($opt->getDefault());
   * // (int) 0
   * ```
   *
   * @param string $uid
   * @return Option
   * @throws Exception
   */
  public function setDefault($uid): self
  {
    if ($this->check() && $this->dbTraitExists($uid)) {
      $this->default = $uid;
    }

    return $this;
  }


  /**
   * Returns an array of the children's IDs of the given option sorted by order or text
   *
   * ```php
   * X::dump($opt->items(12));
   * // array [40, 41, 42, 44, 45, 43, 46, 47]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null array of IDs, sorted or false if option not found
   */
  public function items($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      if (($res = $this->cacheGet($id, __FUNCTION__)) !== false) {
        return $res;
      }

      $cfg = $this->getCfg($id) ?: [];
      if ($cfg || $this->dbTraitExists($id)) {
        // If not sortable returning an array ordered by text
        $order = empty($cfg['sortable']) ? [
            $this->fields['text'] => 'ASC',
            $this->fields['code'] => 'ASC',
            $this->fields['id'] => 'ASC',
          ] : [
            $this->fields['num'] => 'ASC',
            $this->fields['text'] => 'ASC',
            $this->fields['code'] => 'ASC',
            $this->fields['id'] => 'ASC',
          ];
        $res   = $this->db->getColumnValues(
          $this->class_cfg['table'],
          $this->fields['id'], [
          $this->fields['id_parent'] => $id,
          ], $order
        );
        $this->cacheSet($id, __FUNCTION__, $res);
        return $res;
      }
    }

    return null;
  }


  /**
   * Returns an option's row as stored in its original form in the database
   *
   * ```php
   * X::dump($opt->nativeOption(25));
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
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null Row or null if the option cannot be found
   */
  public function nativeOption($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $originalLocale = $this->findI18nById($id);
      $locale = $this->getTranslatingLocale($id);
      if (!empty($locale)
        && ($opt = $this->cacheGetLocale($id, $locale, __FUNCTION__))
      ) {
        return $opt;
      }
      else if (empty($locale)
        && ($opt = $this->cacheGet($id, __FUNCTION__))
      ) {
        return $opt;
      }
      $tab = $this->db->tsn($this->class_cfg['table']);
      $cfn = $this->db->cfn($this->fields['id'], $tab);
      $opt = $this->getRow([$cfn => $id]);
      if (!empty($opt['code']) && Str::isInteger($opt['code'])) {
        $opt['code'] = (int)$opt['code'];
      }
      if ($opt) {
        if (!empty($locale)
          && \class_exists('\bbn\Appui\I18n')
          && !empty($opt[$this->fields['text']])
        ) {
          $i18nCls = new I18n($this->db);
          if ($trans = $i18nCls->getTranslation($opt[$this->fields['text']], $originalLocale, $locale)) {
            $opt[$this->fields['text']] = $trans;
          }
        }
        if (empty($locale)) {
          $this->cacheSet($id, __FUNCTION__, $opt);
        }
        else {
          $this->cacheSetLocale($id, $locale, __FUNCTION__, $opt);
        }
        return $opt;
      }
    }

    return null;
  }


  /**
   * @param null $code
   * @return array|null
   */
  public function nativeOptions($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $res = [];
      if ($its = $this->items($id)) {
        foreach ($its as $it){
          $res[] = $this->nativeOption($it);
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
   * X::dump($opt->rawOption('database', 'appui'));
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
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null Row or false if the option cannot be found
   */
  public function rawOption($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      return $this->db->rselect($this->class_cfg['table'], [], [$this->fields['id'] => $id]);
    }

    return null;
  }


  /**
   * Returns an option's items as stored in its original form in the database, including cfg
   *
   * ```php
   * X::dump($opt->rawOptions('database', 'appui'));
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
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null Row or false if the option cannot be found
   */
  public function rawOptions($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $res = [];
      if ($its = $this->items($id)) {
        foreach ($its as $it) {
          $res[] = $this->db->rselect($this->class_cfg['table'], [], [$this->fields['id'] => $it]);
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
   * X::dump($opt->rawTree('77cea323f0ce11e897fd525400007196'));
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
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null Tree's array or false if the option cannot be found
   */
  public function rawTree($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      if ($res = $this->rawOption($id)) {
        if ($its = $this->items($id)) {
          $res['items'] = [];
          foreach ($its as $it){
            $res['items'][] = $this->rawTree($it);
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
   * X::dump($opt->optionNoAlias(25));
   * X::dump($opt->optionNoAlias('bbn_ide'));
   * X::dump($opt->optionNoAlias('TEST', 58));
   * X::dump($opt->optionNoAlias('test3', 'users', 'bbn_ide'));
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
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null The option array or false if the option cannot be found
   */
  public function optionNoAlias($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))
        && ($opt = $this->nativeOption($id))
    ) {
      $this->_set_value($opt);
      return $opt;
    }

    return null;
  }


  /**
   * @param null $code
   * @return array|null
   */
  public function getValue($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))
        && ($opt = $this->nativeOption($id))
        && !empty($opt[$this->fields['value']])
        && Str::isJson($opt[$this->fields['value']])
    ) {
      return json_decode($opt[$this->fields['value']], true);
    }

    return null;
  }


  /**
   * Returns an option's full content as an array.
   *
   * ```php
   * X::dump($opt->option(25));
   * X::dump($opt->option('bbn_ide'));
   * X::dump($opt->option('TEST', 58));
   * X::dump($opt->option('test', 'users', 'bbn_ide'));
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
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null The option array or false if the option cannot be found
   */
  public function option($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))
        && ($opt = $this->nativeOption($id))
    ) {
      $this->_set_value($opt);
      $c =& $this->fields;
      if (Str::isUid($opt[$c['id_alias']]) && ($alias = $this->nativeOption($opt[$c['id_alias']]))) {
        $opt['alias'] = $alias;
        if ($opt[$c['id_alias']] === $id) {
          throw new Exception(X::_("Impossible to have the same ID as ALIAS, check out ID").' '.$id);
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
   * Returns the merge between an option and its alias as an array.
   *
   * ```php
   * X::dump($opt->option(25));
   * X::dump($opt->option('bbn_ide'));
   * X::dump($opt->option('TEST', 58));
   * X::dump($opt->option('test', 'users', 'bbn_ide'));
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
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null The option array or false if the option cannot be found
   */
  public function opAlias($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))
        && ($opt = $this->nativeOption($id))
    ) {
      $this->_set_value($opt);
      $c =& $this->fields;
      if (Str::isUid($opt[$c['id_alias']]) && ($alias = $this->nativeOption($opt[$c['id_alias']]))) {
        if ($opt[$c['id_alias']] === $id) {
          throw new Exception(X::_("Impossible to have the same ID as ALIAS, check out ID").' '.$id);
        }
        else {
          $this->_set_value($alias);
          foreach ($alias as $n => $a) {
            if (!empty($a)) {
              $opt[$n] = $a;
            }
          }

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
   * X::dump($opt->options(12));
   * /*
   * [
   *   21 => "My option 21",
   *   22 => "My option 22",
   *   25 => "My option 25",
   *   27 => "My option 27"
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null An indexed array of id/text options or false if option not found
   */
  public function options($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $locale = $this->getTranslatingLocale($id);
      if ($r = $this->getCache($id, __FUNCTION__, $locale)) {
        return $r;
      }

      $cf  =& $this->fields;
      $opts = $this->db->rselectAll([
        'tables' => [$this->class_cfg['table']],
        'fields' => [
          $this->db->cfn($cf['id'], $this->class_cfg['table']),
          $this->db->cfn($cf['text'], $this->class_cfg['table']),
          $this->db->cfn($cf['id_alias'], $this->class_cfg['table'])
        ],
        'join' => [
          [
            'table' => $this->class_cfg['table'],
            'alias' => 'alias',
            'type'  => 'LEFT',
            'on'    => [
              [
                'field' => $this->db->cfn($cf['id_alias'], $this->class_cfg['table']),
                'exp'   => 'alias.'.$cf['id']
              ]
            ]
          ]
        ],
        'where' => [$this->db->cfn($cf['id_parent'], $this->class_cfg['table']) => $id],
        'order' => ['text' => 'ASC']
      ]);
      $res = [];
      foreach ($opts as $o) {
        if (\is_null($o[$cf['text']]) && !empty($o[$cf['id_alias']])) {
          $o[$cf['text']] = $this->text($o[$cf['id_alias']]);
        }
        if (!empty($o[$cf['text']])
          && !empty($locale)
          && ($t = $this->getTranslation($o[$cf['id']], $locale))
        ) {
          $o[$cf['text']] = $t;
        }
        $res[$o[$cf['id']]] = $o[$cf['text']];
      }

      \asort($res);
      $this->setCache($id, __FUNCTION__, $res, $locale);
      return $res;
    }

    return null;
  }


  /**
   * Returns an array of children options in the form code => text
   *
   * ```php
   * X::dump($opt->optionsByCode(12));
   * /*
   * array [
   *   'opt21' => "My option 21",
   *   'opt22' => "My option 22",
   *   'opt25' => "My option 25",
   *   'opt27' => "My option 27"
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null An indexed array of code/text options or false if option not found
   */
  public function optionsByCode($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      if ($r = $this->getCache($id, __FUNCTION__)) {
        return $r;
      }

      $opts = $this->nativeOptions($id);
      if ($opts) {
        $res = [];
        foreach ($opts as $o) {
          $res[$o[$this->fields['code']]] = $o[$this->fields['text']];
        }
        \asort($res);
        $opts = $res;
      }

      $this->setCache($id, __FUNCTION__, $opts);
      return $opts;
    }

    return null;
  }


  /**
   * Returns an option's children array of id and text in a user-defined indexed array
   *
   * ```php
   * X::dump($opt->textValueOptions(12, 'title'));
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
  public function textValueOptions($id, string $text = 'text', string $value = 'value'): ?array
  {
    $res = [];
    if ($opts = $this->fullOptions($id)) {
      $cfg = $this->getCfg($id) ?: [];
      $i   = 0;
      foreach ($opts as $k => $o) {
        if (!isset($is_array)) {
          $is_array = \is_array($o);
        }

        $res[$i] = [
          $text => $is_array ? $o[$this->fields['text']] : $o,
          $value => $is_array ? $o[$this->fields['id']] : $k
        ];
        if (!empty($cfg['show_code'])) {
          $res[$i][$this->fields['code']] = $o[$this->fields['code']];
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




  /**
   * Returns an option's children array of id and text in a user-defined indexed array
   *
   * ```php
   * X::dump($opt->textValueOptions(12, 'title'));
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
  public function textValueOptionsRef($id, string $text = 'text', string $value = 'value'): ?array
  {
    $res = [];
    if ($opts = $this->fullOptionsRef($id)) {
      $cfg = $this->getCfg($id) ?: [];
      $i   = 0;
      foreach ($opts as $k => $o) {
        if (!isset($is_array)) {
          $is_array = \is_array($o);
        }

        $res[$i] = [
          $text => $is_array ? $o[$this->fields['text']] : $o,
          $value => $is_array ? $o[$this->fields['id']] : $k
        ];
        if (!empty($cfg['show_code'])) {
          $res[$i][$this->fields['code']] = $o[$this->fields['code']];
        }

        $i++;
      }
    }

    return $res;
  }


  /**
   * @return array|null
   * @throws Exception
   */
  public function siblings(): ?array
  {
    if ($id = $this->fromCode(...func_get_args())) {
      if (($id_parent = $this->getIdParent($id)) && ($full_options = $this->fullOptions($id_parent))) {
        return array_filter(
          $full_options, function ($a) use ($id) {
            return $a[$this->fields['id']] !== $id;
          }
        );
      }
    }

    return null;
  }


  /**
   * Returns an array of full options arrays for a given parent
   *
   * ```php
   * X::dump($opt->fullOptions(12));
   * /*
   * array [
   *   ['id' => 21, 'id_parent' => 12, 'title' => "My option 21", 'myProperty' =>  "78%"],
   *   ['id' => 22, 'id_parent' => 12, 'title' => "My option 22", 'myProperty' =>  "26%"],
   *   ['id' => 25, 'id_parent' => 12, 'title' => "My option 25", 'myProperty' =>  "50%"],
   *   ['id' => 27, 'id_parent' => 12, 'title' => "My option 27", 'myProperty' =>  "40%"]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null A list of parent if option not found
   */
  public function fullOptions($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $list = $this->items($id);
      if (\is_array($list)) {
        $res = [];
        foreach ($list as $i){
          if ($tmp = $this->option($i)) {
            $res[] = $tmp;
          }
          else {
            throw new Exception(X::_("Impossible to find the ID").' '.$i);
          }
        }

        return $res;
      }
    }

    return null;
  }


  /**
   * Returns each individual full option plus the children of options having this as alias.
   *
   * ```php
   * X::dump($opt->fullOptionsRef('type', 'media', 'note', 'appui'));
   * /*
   * array [
   *   ['id' => 21, 'id_parent' => 12, 'title' => "My option 21", 'myProperty' =>  "78%"],
   *   ['id' => 22, 'id_parent' => 12, 'title' => "My option 22", 'myProperty' =>  "26%"],
   *   ['id' => 25, 'id_parent' => 12, 'title' => "My option 25", 'myProperty' =>  "50%"],
   *   ['id' => 27, 'id_parent' => 12, 'title' => "My option 27", 'myProperty' =>  "40%"]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null A list of parent if option not found
   */
  public function fullOptionsRef($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $all = $this->fullOptions($id) ?? [];
      if ($aliases = $this->getAliases($id)) {
        foreach ($aliases as $a) {
          if ($tmp = $this->fullOptions($a[$this->fields['id']])) {
            array_push($all, ...$tmp);
          }
        }
      }

      return $all;
    }

    return null;
  }


  /**
   * Returns each individual option plus the children of options having this as alias.
   *
   * ```php
   * X::dump($opt->optionsRef(12));
   * /*
   * array [
   *   [21 => "My option 21"],
   *   [22 => "My option 22"],
   *   [25 => "My option 25"],
   *   [27 => "My option 27"]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null A list of parent if option not found
   */
  public function optionsRef($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $all = $this->options($id) ?? [];
      if ($aliases = $this->getAliases($id)) {
        foreach ($aliases as $a) {
          if ($tmp = $this->options($a[$this->fields['id']])) {
            $all = array_merge($all, $tmp);
          }
        }
      }

      return $all;
    }

    return null;
  }


  /**
   * Returns each individual item plus the children of items having this as alias.
   *
   * ```php
   * X::dump($opt->itemsRef(12));
   * /*
   * array [
   *   [21],
   *   [22],
   *   [25],
   *   [26]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null A list of parent if option not found
   */
  public function itemsRef($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $all = $this->items($id) ?? [];
      if ($aliases = $this->getAliases($id)) {
        foreach ($aliases as $a) {
          if ($items = $this->items($a)) {
            array_push($all, ...$items);
          }
        }
      }

      return $all;
    }

    return null;
  }


  /**
   * Returns an array of full options arrays for a given parent
   *
   * ```php
   * X::dump($opt->codeOptions(12));
   * /*
   * array [
   *   'code_1' => [
   *       'id' => 21,
   *       'code' => 'code_1',
   *       'text' => 'text_1'
   *   ],
   *   'code_2' => [
   *       'id' => 22,
   *       'code' => 'code_2',
   *       'text' => 'text_2'
   *   ],
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link from_code()}
   * @return array|null A list of parent if option not found
   */
  public function codeOptions($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $list = $this->items($id);
      if (\is_array($list)) {
        $res = [];
        $cfg = $this->getCfg($id) ?: [];
        foreach ($list as $i){
          $o = $this->option($i);
          $res[$o[$this->fields['code']]] = [
            $this->fields['id'] => $o[$this->fields['id']],
            $this->fields['code'] => $o[$this->fields['code']],
            $this->fields['text'] => $o[$this->fields['text']]
          ];

          if ( !empty($cfg['schema']) ){
            if ( \is_string($cfg['schema']) ){
              $cfg['schema'] = json_decode($cfg['schema'], true);
            }
  
            foreach ( $cfg['schema'] as $s ){
              if (!empty($s['field']) && !in_array($s['field'], [$this->fields['id'], $this->fields['code'], $this->fields['text']])) {
                $res[$o[$this->fields['code']]][$s['field']] = $o[$s['field']] ?? null;
              }
            }
          }

        }

        return $res;
      }
    }

    return null;
  }


  /**
   *   * Returns an array of ids for a given parent
   *
   * ```php
   * X::dump($opt->codeIds(12));
   * /*
   * array [
   *   'code_1' => 21,
   *   'code_2' => 22,
   * ]
   * ```
   *
   * @param null $code
   * @return array|null
   */
  public function codeIds($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $list = $this->items($id);
      if (\is_array($list)) {
        $res = [];
        foreach ($list as $i){
          $o               = $this->option($i);
          $res[$o[$this->fields['code']]] = $o[$this->fields['id']];
        }

        return $res;
      }
    }

    return null;
  }


  /**
   * @param null $code
   * @return array|null
   */
  public function getAliases($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $r = [];
      $cf = $this->getClassCfg();
      if ($results = $this->db->rselectAll($cf['table'], [], [$this->fields['id_alias'] => $id])) {
        foreach ($results as $d) {
          if (!empty($d[$this->fields['code']])
            && Str::isInteger($d[$this->fields['code']])
          ) {
            $d[$this->fields['code']] = (int)$d[$this->fields['code']];
          }
          $this->_set_value($d);
          if (!empty($d[$this->fields['text']])) {
            $d[$this->fields['text']] = $this->text($d[$this->fields['id']]);
          }
          $r[] = $d;
        }
      }

      return $r;
    }

    return null;
  }


  /**
   * @param null $code
   * @return array|null
   */
  public function getAliasItems($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      if ($res = $this->cacheGet($id, __FUNCTION__)) {
        return $res;
      }

      $cf  = $this->getClassCfg();
      $f   = $this->getFields();
      $res = $this->db->getColumnValues(
        $cf['table'],
        $f['id'],
        [$f['id_alias'] => $id]
      );

      $this->cacheSet($id, __FUNCTION__, $res);
      return $res;
    }

    return null;
  }


  /**
   * @param null $code
   * @return array|null
   */
  public function getAliasOptions($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      if ($r = $this->getCache($id, __FUNCTION__)) {
        return $r;
      }

      $res = [];
      if ($items = $this->getAliasItems($id)) {
        foreach ($items as $it) {
          $res[$it] = $this->text($it);
        }
      }

      $this->setCache($id, __FUNCTION__, $res);
      return $res;
    }

    return null;
  }


  /**
   * @param null $code
   * @return array|null
   * @throws Exception
   */
  public function getAliasFullOptions($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      if ($r = $this->cacheGet($id, __FUNCTION__)) {
        return $r;
      }

      $res = [];
      if ($items = $this->getAliasItems($id)) {
        foreach ($items as $it) {
          $res[] = $this->option($it);
        }
      }

      $this->cacheSet($id, __FUNCTION__, $res);
      return $res;
    }

    return null;
  }


  /**
   * Returns an id-indexed array of full options arrays for a given parent
   *
   * ```php
   * X::dump($opt->fullOptionsById(12));
   * /*
   * array [
   *   21 => ['id' => 21, 'id_parent' => 12, 'title' => "My option 21", 'myProperty' =>  "78%"],
   *   22 => ['id' => 22, 'id_parent' => 12, 'title' => "My option 22", 'myProperty' =>  "26%"],
   *   25 => ['id' => 25, 'id_parent' => 12, 'title' => "My option 25", 'myProperty' =>  "50%"],
   *   27 => ['id' => 27, 'id_parent' => 12, 'title' => "My option 27", 'myProperty' =>  "40%"]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null A list of parent if option not found
   */
  public function fullOptionsById($code = null): ?array
  {
    $res = [];
    if ($opt = $this->fullOptions(\func_get_args())) {
      $cf = $this->getFields();
      foreach ($opt as $o){
        $res[$o[$cf['id']]] = $o;
      }
    }

    return $opt === null ? $opt : $res;
  }


  /**
   * Returns code-indexed array of full options arrays for a given parent
   *
   * ```php
   * X::dump($opt->fullOptionsByCode(12));
   * /*
   * array [
   *   'code_1' => ['id' => 21, 'id_parent' => 12, 'title' => "My option 21", 'myProperty' =>  "78%"],
   *   'code_2' => ['id' => 22, 'id_parent' => 12, 'title' => "My option 22", 'myProperty' =>  "26%"],
   *   'code_3' => ['id' => 25, 'id_parent' => 12, 'title' => "My option 25", 'myProperty' =>  "50%"],
   *   'code_4' => ['id' => 27, 'id_parent' => 12, 'title' => "My option 27", 'myProperty' =>  "40%"]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null A list of parent if option not found
   */
  public function fullOptionsByCode($code = null): ?array
  {
    $res = [];
    if ($opt = $this->fullOptions(\func_get_args())) {
      $cf = $this->getFields();
      foreach ($opt as $o){
        $res[$o[$cf['code']]] = $o;
      }
    }

    return $opt === null ? $opt : $res;
  }


  /**
   * Returns an array of full options with the config in arrays for a given parent
   *
   * ```php
   * X::dump($opt->fullOptionsCfg(12));
   * /*
   * array [
   *   ['id' => 21, 'id_parent' => 12, 'num' => 1, 'title' => "My option 21", 'myProperty' =>  "78%", 'cfg' => ['sortable' => true, 'desc' => "I am a description"]],
   *   ['id' => 22, 'id_parent' => 12, 'num' => 2, 'title' => "My option 22", 'myProperty' =>  "26%", 'cfg' => ['desc' => "I am a description"]],
   *   ['id' => 25, 'id_parent' => 12, 'num' => 3, 'title' => "My option 25", 'myProperty' =>  "50%", 'cfg' => ['desc' => "I am a description"]],
   *   ['id' => 27, 'id_parent' => 12, 'num' => 4, 'title' => "My option 27", 'myProperty' =>  "40%", 'cfg' => ['desc' => "I am a description"]]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null A list of parent if option not found
   */
  public function fullOptionsCfg($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $o =& $this;
      return $this->map(
        function ($a) use ($o) {
          $a[$this->fields['cfg']] = $o->getCfg($a[$this->fields['id']]);
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
   * X::dump($opt->soptions(12));
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
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null indexed on id/text options or false if parent not found
   */
  public function soptions($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $r = [];
      if ($list = $this->items($id)) {
        foreach ($list as $i){
          $o = $this->options($i);
          if (\is_array($o)) {
            $r = X::mergeArrays($r, $o);
          }
        }
      }

      return $r;
    }

    return null;
  }


  /**
   * Returns an array of full options arrays for a given grandparent
   *
   * ```php
   * X::dump($opt->fullSoptions(12));
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
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null A list of options or false if parent not found
   */
  public function fullSoptions($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $r = [];
      if ($ids = $this->items($id)) {
        foreach ($ids as $id){
          $o = $this->fullOptions($id);
          if (\is_array($o)) {
            $r = X::mergeArrays($r, $o);
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
   * X::dump($opt->treeIds(12));
   * // array [12, 21, 22, 25, 27, 31, 32, 35, 37, 40, 41, 42, 44, 45, 43, 46, 47]
   * ```
   *
   * @param int   $id  The end/target of the path
   * @param array $res The resulting array
   * @return array|null
   */
  public function treeIds($id, &$res = []): ?array
  {
    if ($this->check() && $this->exists($id)) {
      $res[] = $id;
      if ($its = $this->items($id)) {
        foreach ($its as $it){
          $this->treeIds($it, $res);
        }
      }

      return $res;
    }

    return null;
  }


  public function flatOptions($code = null): array
  {
    if (!Str::isUid($id = $this->fromCode(\func_get_args()))) {
      throw new Exception(X::_("Impossible to find the option requested in flatOptions"));
    }

    $res = [];
    if ($ids = $this->treeIds($id)) {
      foreach ($ids as $id) {
        if ($o = $this->nativeOption($id)) {
          $res[] = [
            $this->fields['id'] => $o[$this->fields['id']],
            $this->fields['text'] => $o[$this->fields['text']]
          ];
        }
      }
    }
    X::sortBy($res, $this->class_cfg['arch']['options']['text'], 'asc');
    return $res;
  }


  /**
   * Returns a hierarchical structure as stored in its original form in the database
   *
   * ```php
   * X::dump($opt->nativeTree(12));
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
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null Tree's array or false if the option cannot be found
   */
  public function nativeTree($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      if ($res = $this->nativeOption($id)) {
        $its = $this->items($id);
        if (!empty($its)) {
          $res['items'] = [];
          foreach ($its as $it){
            $res['items'][] = $this->nativeTree($it);
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
   * X::dump($opt->tree(12));
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
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null
   */
  public function tree($code = null): ?array
  {
    $id = $this->fromCode(\func_get_args());
    if (Str::isUid($id) && ($text = $this->text($id))) {
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
   * X::dump($opt->fullTree(12));
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
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null Tree's array or false if the option cannot be found
   */
  public function fullTree($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))
        && ($res = $this->option($id))
    ) {
      if ($opts = $this->items($id)) {
        $res['items'] = [];
        foreach ($opts as $o){
          if ($t = $this->fullTree($o)) {
            $res['items'][] = $t;
          }
        }
      }

      return $res;
    }

    return null;
  }

  /**
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @returns array|null
   */
  public function fullTreeRef($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))
        && ($res = $this->option($id))
    ) {
      if ($opts = $this->fullOptionsRef($id)) {
        $res['items'] = [];
        foreach ($opts as $o){
          if ($t = $this->fullTreeRef($o)) {
            $res['items'][] = $t;
          }
        }
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
   * X::dump($opt->getCfg(25));
   * /*
   * array [
   *   'sortable' => true,
   *   'cascade' => true,
   *   'id_alias' => null,
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null The formatted array or false if the option cannot be found
   */
  public function getCfg($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      if ($tmp = $this->cacheGet($id, __FUNCTION__)) {
        return $tmp;
      }

      $c   =& $this->class_cfg;
      $f   =& $this->fields;
      $cfg = $this->db->selectOne($c['table'], $f['cfg'], [$f['id'] => $id]);
      $cfg = Str::isJson($cfg) ? json_decode($cfg, true) : [];
      $perm = $cfg['permissions'] ?? false;
      // Looking for parent with inheritance
      $parents = array_reverse($this->parents($id));
      $last    = \count($parents) - 1;
      foreach ($parents as $i => $p){
        $parent_cfg = $this->db->selectOne($c['table'], $f['cfg'], [$f['id'] => $p]);
        $parent_cfg = Str::isJson($parent_cfg) ? json_decode($parent_cfg, true) : [];
        if (!empty($parent_cfg['scfg']) && ($i === $last)) {
          $cfg                 = array_merge((array)$cfg, $parent_cfg['scfg']);
          $cfg['inherit_from'] = $p;
          $cfg['frozen']       = 1;
          break;
        }

        if (!empty($parent_cfg['inheritance']) || !empty($parent_cfg['scfg']['inheritance'])) {
          if (
              (($i === $last)
              && (
              (($parent_cfg['inheritance'] ?? null) === 'children')
              || (!empty($parent_cfg['scfg']) && (($parent_cfg['scfg']['inheritance'] ?? null) === 'children')))
              )
              || (
              (($parent_cfg['inheritance'] ?? null) === 'cascade')
              || (!empty($parent_cfg['scfg']) && (($parent_cfg['scfg']['inheritance'] ?? null) === 'cascade'))
            )
          ) {
            // Keeping in the option cfg properties which don't exist in the parent
            $cfg                 = array_merge((array)$cfg, $parent_cfg['scfg'] ?? $parent_cfg);
            $cfg['inherit_from'] = $p;
            $cfg['frozen']       = 1;
            break;
          }
          elseif (!count($cfg)
              && ((($parent_cfg['inheritance'] ?? null) === 'default')
              || (!empty($parent_cfg['scfg']) && (($parent_cfg['scfg']['inheritance'] ?? null) === 'default'))              )
          ) {
            $cfg                 = $parent_cfg['scfg'] ?? $parent_cfg;
            $cfg['inherit_from'] = $p;
          }
        }
      }

      if ($perm) {
        $cfg['permissions'] = $perm;
      }

      $mandatories = ['show_code', 'show_alias', 'show_value', 'show_icon', 'sortable', 'allow_children', 'frozen'];
      foreach ($mandatories as $m){
        $cfg[$m] = empty($cfg[$m]) ? 0 : 1;
      }

      $mandatories = ['desc', 'inheritance', 'permissions', 'i18n', 'i18n_inheritance'];
      foreach ($mandatories as $m){
        $cfg[$m] = empty($cfg[$m]) ? '' : $cfg[$m];
      }

      $mandatories = ['controller', 'schema', 'form', 'default_value'];
      foreach ($mandatories as $m){
        $cfg[$m] = empty($cfg[$m]) ? null : $cfg[$m];
      }

      $this->cacheSet($id, __FUNCTION__, $cfg);
      return $cfg;
    }

    return null;
  }


  /**
   * Returns the raw content of the cfg column for the given option.
   *
   * ```php
   * X::dump($opt->getRawCfg(25));
   * // (string) "{'sortable':true, 'cascade': true}"
   *
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return string|null The formatted array or null if the option cannot be found
   */
  public function getRawCfg($code = null): ?string
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $c =& $this->class_cfg;
      $f =& $this->fields;
      return $this->db->selectOne($c['table'], $f['cfg'], [$f['id'] => $id]);
    }

    return null;
  }


  /**
   * Returns a formatted content of the cfg column as an array from the option's parent
   *
   * ```php
   * X::dump($opt->getParentCfg(42));
   * /*
   * [
   *   'sortable' => true,
   *   'cascade' => true,
   *   'id_alias' => null,
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null config or null if the option cannot be found
   */
  public function getParentCfg($code = null): ?array
  {
    if ($id = $this->fromCode(\func_get_args())) {
      if ($id_parent = $this->getIdParent($id)) {
        return $this->getCfg($id_parent);
      }
    }

    return null;
  }


  /**
   * Returns an array of id_parents from the option selected to root
   *
   * ```php
   * X::dump($opt->parents(48));
   * // array [25, 12, 0]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null The array of parents' ids, an empty array if no parent (root case), and null if it can't find the option
   */
  public function parents($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $res = [];
      while (Str::isUid($id_parent = $this->getIdParent($id))){
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
   * X::dump($opt->parents(48));
   * // array [0, 12, 25, 48]
   * X::dump($opt->parents(48, 12));
   * // array [12, 25, 48]
   * ```
   *
   * @param string      $id_option
   * @param string|null $id_root
   * @return array|null The array of parents' ids, an empty array if no parent (root case), and null if it can't find the option
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
   * X::dump($opt->getIdParent(48));
   * // (int)25
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return string|null The parent's ID, null if no parent or if option cannot be found.
   */
  public function getIdParent($code = null): ?string
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      if ($opt = $this->cacheGet($id, 'nativeOption')) {
        return $opt[$this->fields['id_parent']];
      }
      else {
        return $this->db->selectOne($this->class_table, $this->fields['id_parent'], [$this->fields['id'] => $id]);
      }
    }

    return null;
  }


  /**
   * Returns the parent's option as {@link option()}
   *
   * ```php
   * X::hdump($opt->parent(42));
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
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|false
   */
  public function parent($code = null): ?array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))
        && ($id_parent = $this->getIdParent($id))
    ) {
      return $this->option($id_parent);
    }

    return null;
  }


  /**
   * Return true if row with ID $id_parent is parent at any level of row with ID $id
   *
   * ```php
   * X::dump($opt->isParent(42, 12));
   * // (bool) true
   * X::dump($opt->isParent(42, 13));
   * // (bool) false
   * ```
   *
   * @param $id
   * @param $id_parent
   * @return bool
   */
  public function isParent($id, $id_parent): bool
  {
    // Preventing infinite loop
    $done = [$id];
    if (Str::isUid($id) && Str::isUid($id_parent)) {
      while ($id = $this->getIdParent($id)){
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
   * X::dump($opt->getCodes());
   * /*
   * array [
   *   21 => "opt21",
   *   22 => "opt22",
   *   25 => "opt25",
   *   27 => "opt27"
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array Options' array
   */
  public function getCodes($code = null): array
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $c   =& $this->fields;
      $opt = $this->db->rselectAll($this->class_cfg['table'], [$c['id'], $c['code']], [$c['id_parent'] => $id], [($this->isSortable($id) ? $c['num'] : $c['code']) => 'ASC']);
      $res = [];
      foreach ($opt as $r){
        if (!empty($r[$c['code']]) && Str::isInteger($r[$c['code']])) {
          $r[$c['code']] = (int)$r[$c['code']];
        }
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
   * X::dump($opt->code(12));
   * // (string) bbn_ide
   * ```
   *
   * @param string $id The options' ID
   * @return string|null The code value, null is none, false if option not found
   */
  public function code(string $id): ?string
  {
    if ($this->check() && Str::isUid($id)) {
      $code = $this->db->selectOne(
        $this->class_cfg['table'], $this->fields['code'], [
        $this->fields['id'] => $id
        ]
      );
      if (!empty($code) && Str::isInteger($code)) {
        $code = (int)$code;
      }
      return $code;
    }

    return null;
  }


  /**
   * Returns an option's text
   *
   * ```php
   * X::dump($opt->text(12));
   * // (string) BBN's own IDE
   * X::dump($opt->text('bbn_ide'));
   * // (string) BBN's own IDE
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return string|null Text of the option
   */
  public function text($code = null): ?string
  {
    if ($opt = $this->nativeOption(\func_get_args())) {
      return $opt[$this->fields['text']];
    }

    return null;
  }


  public function rawText($code = null): ?string
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      if ($this->cacheHas($id, __FUNCTION__)) {
        return $this->getCache($id, __FUNCTION__);
      }

      $text = $this->db->selectOne(
        $this->class_cfg['table'],
        $this->fields['text'],
        [
          $this->fields['id'] => $id
        ]
      );
      $this->setCache($id, __FUNCTION__, $text);
      return $text;
    }

    return null;
  }


  /**
   * Returns the id_alias relative to the given id_option
   *
   * @param string $id
   * @return string|null
   */
  public function alias(string $id): ?string
  {
    if ($this->check() && Str::isUid($id)) {
      return $this->db->selectOne(
        $this->class_cfg['table'], $this->fields['id_alias'], [
        $this->fields['id'] => $id
        ]
      );
    }

    return null;
  }


  /**
   * Returns translation of an option's text
   *
   * ```php
   * X::dump($opt->itext(12));
   * // Result of X::_("BBN's own IDE") with fr as locale
   * // (string) L'IDE de BBN
   * X::dump($opt->itext('bbn_ide'));
   * // (string) L'IDE de BBN
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return string|null Text of the option
   */
  public function itext($code = null): ?string
  {
    return $this->getTranslation($this->fromCode(\func_get_args()));
  }


  /**
   * Returns the number of children for a given option
   *
   * ```php
   * X::dump($opt->count('bbn_ide'));
   * // (int) 4
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return int|null The number of children or null if option not found
   */
  public function count($code = null): ?int
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      return $this->db->count($this->class_cfg['table'], [$this->fields['id_parent'] => $id]);
    }

    return null;
  }


  /**
   * Returns an array of options based on their id_alias
   *
   * ```php
   * X::dump($opt->optionsByAlias(36));
   * /*
   * array [
   *   ['id' => 18, 'text' => "My option 1", 'code' => "opt1", 'myProperty' => "50%"],
   *   ['id' => 21, 'text' => "My option 4", 'code' => "opt4", 'myProperty' => "60%"],
   *   ['id' => 23, 'text' => "My option 6", 'code' => "opt6", 'myProperty' => "90%"]
   * ]
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return array|null
   */
  public function optionsByAlias($code = null): ?array
  {
    $id_alias = $this->fromCode(\func_get_args());
    if (Str::isUid($id_alias)) {
      $where = [$this->fields['id_alias'] => $id_alias];
      $list  = $this->getRows($where);
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
   * X::dump($opt->isSortable(12));
   * // (bool) false
   * X::dump($opt->isSortable(21));
   * // (bool) true
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return bool
   */
  public function isSortable($code = null): ?bool
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      $cfg = $this->getCfg($id);
      return !empty($cfg['sortable']);
    }

    return null;
  }


  /**
   * Returns an array of codes for each option between $id and $root without $root's code
   *
   * ```php
   * X::dump($opt->getPathArray(48, 12));
   * // array ["path", "to", "my", "option"]
   * ```
   *
   * @param string $id The end/target of the path
   * @param null $root The start/origin of the path, {@link getDefault()} if is null
   * @return array|null
   */
  public function getPathArray(string $id, $root = null): ?array
  {
    if (!isset($root)) {
      $root = $this->getDefault();
    }

    if ($code = $this->code($id)) {
      $parts = [];
      while ($id && ($id !== $root)){
        array_unshift($parts, $code);
        if (!($id = $this->getIdParent($id))) {
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
   * X::dump("bbn_ide|test1|test8"));
   * // (int) 36
   * ```
   *
   * @param string      $path   The path made of a concatenation of path and $sep until the target
   * @param string      $sep    The separator
   * @param null|string $parent An optional id_parent, {@link fromCode()} otherwise
   * @return null|string
   */
  public function fromPath(string $path, string $sep = '|', $parent = null): ?string
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
        if (!($parent = $this->fromCode($p, $parent))) {
          break;
        }
      }

      return $parent ?: null;
    }

    return null;
  }


  /**
   * Concatenates the codes and separator $sep of a line of options
   *
   * ```php
   * X::dump($opt->toPath(48, '|', 12)
   * // (string) path|to|my|option
   * ```
   *
   * @param string $id The end/target of the path
   * @param string $sep The separator
   * @param string|null $parent The start/origin of the path
   * @return string|null The path concatenated with the separator or null if no path
   */
  public function toPath(string $id, string $sep = '|', string $parent = null): ?string
  {
    if ($this->check() && ($parts = $this->getPathArray($id, $parent))) {
      return implode($sep, $parts);
    }

    return null;
  }


  /**
   * Creates a new option or a new hierarchy by adding row(s) in the options' table
   *
   * ```php
   * X::dump($opt->add([
   *   'id_parent' => $opt->fromCode('bbn_ide'),
   *   'text' => 'My new option',
   *   'code' => 'new_opt',
   *   'myProperty' => 'my value'
   * ]));
   * // (int) 49  New ID
   * X::dump($opt->add([
   *   'id_parent' => $opt->fromCode('bbn_ide'),
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
   * @param bool $with_id
   * @return int|string|null
   */
  public function add(array $it, $force = false, $return_num = false, $with_id = false)
  {
    if ($this->check()) {
      $res   = $return_num ? 0 : null;
      $items = !empty($it['items']) && \is_array($it['items']) ? $it['items'] : false;
      $id    = null;
      try {
        $this->_prepare($it);
      }
      catch (Exception $e) {
        throw new Exception($e->getMessage());
      }

      if ($it) {
        $c =& $this->fields;
        if ($it[$c['code']]) {
          $id = $this->db->selectOne(
            $this->class_cfg['table'],
            $c['id'],
            [
              $c['id_parent'] => $it[$c['id_parent']],
              $c['code'] => $it[$c['code']]
            ]
          );
        }
        elseif (!empty($it[$c['id']])) {
          $id = $this->db->selectOne(
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
          try {
            $res = (int)$this->db->update(
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
          catch (Exception $e) {
            $this->log([X::_("Impossible to update the option"), $it]);
            throw new Exception(X::_("Impossible to update the option"));
          }
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
        if (isset($it[$c['id']]) && !$this->exists($it[$c['id']])) {
          $values[$c['id']] = $it[$c['id']];
        }

        if (!empty($it[$c['id']]) && $with_id) {
          $values[$c['id']] = $it[$c['id']];
        }

        if (!$id) {
          try {
            $res = (int)$this->db->insert($this->class_cfg['table'], $values);
          }
          catch (Exception $e) {
            X::log([X::_("Impossible to add the option"), $values], 'OptionAddErrors');
            throw new Exception(X::_("Impossible to add the option"));
          }

          $id = $this->db->lastId();
        }

        if ($res) {
          $this->deleteCache($id);
        }

        if ($items && Str::isUid($id)) {
          foreach ($items as $item){
            $item[$c['id_parent']] = $id;
            $res              += (int)$this->add($item, $force, $return_num, $with_id);
          }
        }
      }
      else {
        X::log($it, 'OptionAddErrors');
      }

      return $return_num ? $res : $id;
    }

    return null;
  }


  /**
   * Updates an option's row (without changing cfg)
   *
   * ```php
   * X::dump($opt->set(12, [
   *   'id_parent' => $opt->fromCode('bbn_ide'),
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
   * @param string   $id
   * @param array $data
   * @return int
   */
  public function set($id, array $data)
  {
    if ($this->check() && $this->_prepare($data)) {
      if (isset($data['id'])) {
        unset($data['id']);
      }

      $c =& $this->fields;
      // id_parent cannot be edited this way
      if ($res = $this->db->update(
        $this->class_cfg['table'],
        [
          $c['text'] => $data[$c['text']],
          $c['code'] => !empty($data[$c['code']]) ? $data[$c['code']] : null,
          $c['id_alias'] => !empty($data[$c['id_alias']]) ? $data[$c['id_alias']] : null,
          $c['value'] => $data[$c['value']]
        ],
        [$c['id'] => $id]
      )
      ) {
        $this->deleteCache($id);
        return $res;
      }

      return 0;
    }

    return null;
  }


  /**
   * Updates an option's row by merging the data and cfg.
   *
   * ```php
   * X::dump($opt->merge(12, [
   *   'id_parent' => $opt->fromCode('bbn_ide'),
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
   * @param string $id
   * @param array $data
   * @param array|null $cfg
   * @return int|null
   * @throws Exception
   */
  public function merge(string $id, array $data, array $cfg = null)
  {
    if ($this->check()
        && ($o = $this->option($id))
    ) {
      $c =& $this->fields;
      $o[$c['text']] = $this->rawText($o[$c['id']]);
      if (!empty($data)) {
        $data = array_merge($o, $data);
        $this->_prepare($data);
        if (isset($data[$c['id']])) {
          unset($data[$c['id']]);
        }
      }

      if ($cfg) {
        $ocfg        = $this->getRawCfg($id);
        $data[$c['cfg']] = json_encode(array_merge($ocfg ? json_decode($ocfg, true) : [], $cfg));
      }

      // id_parent cannot be edited this way
      if ($res = $this->db->update(
        $this->class_cfg['table'],
        $data,
        [$c['id'] => $id]
      )
      ) {
        $this->deleteCache($id);
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
   * X::dump($opt->remove(12));
   * // (int) 12 Number of options deleted
   * X::dump($opt->remove(12));
   * // (null) The option doesn't exist anymore
   * ```
   *
   * @param string $code Any option(s) accepted by {@link fromCode()}
   * @return int|null The number of affected rows or null if option not found
   */
  public function remove($code)
  {
    if (Str::isUid($id = $this->fromCode(...\func_get_args()))
        && ($id !== $this->default)
        && ($id !== $this->root)
        && Str::isUid($id_parent = $this->getIdParent($id))
    ) {
      $num = 0;
      if ($items = $this->items($id)) {
        foreach ($items as $it){
          $num += (int)$this->remove($it);
        }
      }

      $this->deleteCache($id);
      $num += (int)$this->db->delete(
        $this->class_cfg['table'], [
        $this->fields['id'] => $id
        ]
      );
      if ($this->isSortable($id_parent)) {
        $this->fixOrder($id_parent);
      }

      return $num;
    }

    return null;
  }


  /**
   * Deletes an option row with all it's hierarchical structure from the options table and deletes the cache.
   *
   * ```php
   * X::dump($opt->removeFull(12));
   * // (int) 12 Number of options deleted
   * X::dump($opt->removeFull(12));
   * // (null) The option doesn't exist anymore
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()} or the uid
   * @return int|null The number of affected rows or null if option not found
   */
  public function removeFull($code)
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))
        && ($id !== $this->default)
        && ($id !== $this->root)
    ) {
      $res = 0;
      $this->deleteCache($id);
      $all = $this->treeIds($id);

      $has_history = History::isEnabled() && History::isLinked($this->class_cfg['table']);
      foreach (array_reverse($all) as $a){
        if ($has_history) {
          $res += (int)$this->db->delete('bbn_history_uids', ['bbn_uid' => $a]);
        }
        else{
          $res += (int)$this->db->delete($this->class_cfg['table'], [$this->fields['id'] => $a]);
        }
      }

      return $res;
    }

    return null;
  }


  /**
   * Sets the alias of an option
   *
   * ```php
   * X::dump($opt->setAlias(26, 32));
   * // (int) 1
   * ```
   *
   * @param string      $id    The ID of the option to be updated
   * @param string|null $alias The alias' option ID
   * @return int The number of affected rows
   */
  public function setAlias($id, $alias = null)
  {
    $res = null;
    if ($this->check()) {
      $res = $this->db->updateIgnore(
        $this->class_cfg['table'], [
        $this->fields['id_alias'] => $alias ?: null
        ], [
        $this->fields['id'] => $id
        ]
      );
      if ($res) {
        $this->deleteCache($id);
      }
    }

    return $res;
  }


  /**
   * Sets the text of an option
   *
   * ```php
   * X::dump($opt->setText(26, "Hello world!"));
   * // (int) 1
   * ```
   *
   * @param int    $id   The ID of the option to be updated
   * @param string $text The new text
   * @return int The number of affected rows
   */
  public function setText($id, ?string $text)
  {
    $res = null;
    if ($this->check()) {
      $res = $this->db->updateIgnore(
        $this->class_cfg['table'], [
        $this->fields['text'] => $text
        ], [
          $this->fields['id'] => $id
        ]
      );
      if ($res) {
        $this->deleteCache($id);
      }
    }

    return $res;
  }


  /**
   * Sets the code of an option
   *
   * ```php
   * X::dump($opt->setCode(26, "HWD"));
   * // (int) 1
   * ```
   *
   * @param int $id The ID of the option to be updated
   * @param string|null $code The new code
   * @return int|null The number of affected rows
   */
  public function setCode($id, string $code = null)
  {
    if ($this->check()) {
      return $this->db->updateIgnore(
        $this->class_cfg['table'], [
        $this->fields['code'] => $code ?: null
        ], [
        $this->fields['id'] => $id
        ]
      );
    }

    return null;
  }


  /**
   * Returns the order of an option. Updates it if a position is given, and cascades
   *
   * ```php
   * X::dump($opt->items(20));
   * // [21, 22, 25, 27]
   * X::dump($opt->order(25));
   * // (int) 3
   * X::dump($opt->order(25, 2));
   * // (int) 2
   * X::dump($opt->items(20));
   * // [21, 25, 22, 27]
   * X::dump($opt->order(25));
   * // (int) 2
   * ```
   *
   * @param int $id  The ID of the option to update
   * @param int|null $pos The new position
   * @return int|null The new or existing order of the option or null if not found or not sortable
   */
  public function order($id, int $pos = null)
  {
    if ($this->check()
        && ($parent = $this->getIdParent($id))
        && $this->isSortable($parent)
    ) {
      $cf  = $this->class_cfg;
      $old = $this->db->selectOne(
        $cf['table'], $this->fields['num'], [
        $this->fields['id'] => $id
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
            $upd = [$this->fields['num'] => $p - 1];
          }
          elseif (!$past_old && $past_new) {
            $upd = [$this->fields['num'] => $p + 1];
          }

          if ($id === $id_option) {
            $upd      = [$this->fields['num'] => $pos];
            $past_old = 1;
          }
          elseif ($p === $pos) {
            $upd      = [$this->fields['num'] => $p + ($pos > $old ? -1 : 1)];
            $past_new = 1;
          }

          if ($upd) {
            $this->db->update(
              $cf['table'], $upd, [
              $this->fields['id'] => $id_option
              ]
            );
          }

          if ($past_new && $past_old) {
            break;
          }

          $p++;
        }

        $this->deleteCache($parent, true);
        $this->deleteCache($id);
        return $pos;
      }

      return $old;
    }

    return null;
  }


  /**
   * Updates option's properties derived from the value column
   *
   * ```php
   * X::dump($opt->setProp(12, 'myProperty', "78%"));
   * // (int) 1
   * X::dump($opt->setProp(12, ['myProperty' => "78%"]));
   * // (int) 0 Already updated, no change done
   * X::dump($opt->setProp(9654, ['myProperty' => "78%"]));
   * // (bool) false Option not found
   * X::dump($opt->setProp(12, ['myProperty' => "78%", 'myProperty2' => "42%"]));
   * // (int) 1
   * X::dump($opt->option(12));
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
   * @return int|null the number of affected rows or null if no argument or option not found
   */
  public function setProp($id, $prop)
  {
    if (!empty($id) && !empty($prop) && ($o = $this->optionNoAlias($id))) {
      $args = \func_get_args();
      if (\is_string($prop) && isset($args[2])) {
        $prop = [$prop => $args[2]];
      }

      if (\is_array($prop)) {
        X::log([$o, $prop], "set_prop");
        $change = false;
        foreach ($prop as $k => $v){
          if (!isset($o[$k]) || ($o[$k] !== $v)) {
            $change = true;
            $o[$k]  = $v;
          }

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
   * X::dump($opt->getProp(12, 'myProperty'));
   * // (int) 78
   * X::dump($opt->setProp(12, ['myProperty' => "78%"]));
   * // (int) 1
   * X::dump($opt->getProp(12, 'myProperty'));
   * // (string) "78%"
   * ```
   *
   * @param string|int    $id   The option from which getting the property
   * @param string $prop The property's name
   * @return mixed|false The property's value, false if not found
   */
  public function getProp($id, string $prop)
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
   * X::dump($opt->unsetProp(12, 'myProperty'));
   * // (int) 1
   * X::dump($opt->unsetProp(12, ['myProperty']));
   * // (int) 0 Already updated, no change done
   * X::dump($opt->unsetProp(9654, ['myProperty']));
   * // (bool) false Option not found
   * X::dump($opt->unsetProp(12, ['myProperty', 'myProperty2']));
   * // (int) 1
   * X::dump($opt->option(12));
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
   * @param string       $id   The option to update's ID
   * @param array|string $prop An array of properties and values, or a string with the property's name adding as next argument the new value
   * @return int|null the number of affected rows or null if no argument or option not found
   */
  public function unsetProp($id, $prop)
  {
    if (!empty($prop) && Str::isUid($id) && ($o = $this->optionNoAlias($id))) {
      if (\is_string($prop)) {
        $prop = [$prop];
      }

      if (\is_array($prop)) {
        $change = false;
        foreach ($prop as $k){
          if (!\in_array($k, $this->fields, true) && array_key_exists($k, $o)) {
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
   * X::dump($opt->getCfg('bbn_ide'));
   * // array ['sortable' => true]
   * X::dump($opt->setCfg(12, [
   *   'desc' => "I am a cool option",
   *   'sortable' => true
   * ]));
   * // (int) 1
   * X::dump($opt->getCfg('bbn_ide'));
   * // array ['desc' => "I am a cool option", 'sortable' => true];
   * ```
   *
   * @param string|int   $id  The option ID
   * @param array       $cfg The config value
   * @return int|null number of affected rows
   */
  public function setCfg($id, array $cfg, bool $merge = false): ?int
  {
    if ($this->check() && $this->exists($id)) {
      if (isset($cfg['inherited_from'])) {
        unset($cfg['inherited_from']);
      }

      if (isset($cfg[$this->fields['id']])) {
        unset($cfg[$this->fields['id']]);
      }

      if (isset($cfg['permissions']) && !in_array($cfg['permissions'], ['single', 'cascade', 'all', 'children'])) {
        unset($cfg['permissions']);
      }

      if ($merge && ($old_cfg = $this->getCfg($id))) {
        $cfg = array_merge($old_cfg, $cfg);
      }

      $c =& $this->class_cfg;
      if ($res = $this->db->update(
        $c['table'], [
        $this->fields['cfg'] => $cfg ? json_encode($cfg) : null
        ], [
          $this->fields['id'] => $id
        ]
      )
      ) {
        if ((isset($old_cfg['inheritance'], $cfg['inheritance'])
            && ($old_cfg['inheritance'] !== $cfg['inheritance']))
          || (isset($old_cfg['i18n_inheritance'], $cfg['i18n_inheritance'])
            && ($old_cfg['i18n_inheritance'] !== $cfg['i18n_inheritance']))
        ) {
          $this->deleteCache($id, true);
        }
        else{
          $this->deleteCache($id);
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
   * X::dump($opt->getCfg('bbn_ide'));
   * // array ['desc' => "I am a cool option", 'sortable' => true];
   * ```
   *
   * @param string|int $id The option ID
   * @return int|false Number of affected rows or false if not found
   */
  public function unsetCfg($id)
  {
    $res = false;
    if ($this->check() && $this->exists($id)) {
      $res = $this->db->update(
        $this->class_cfg['table'], [
        $this->fields['cfg'] => null
        ], [
        $this->fields['id'] => $id
        ]
      );
      if ($res) {
        $this->deleteCache($id);
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
   * X::dump($opt->option(20), $opt->option(30));
   * X::dump($opt->fusion(30, 20));
   * X::dump($opt->option(20));
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
      $cf     =& $this->fields;
      if ($o_dest && $o_src) {
        $o_dest[$cf['text']] = $this->rawText($o_dest[$cf['id']]);
        $o_src[$cf['text']] = $this->rawText($o_src[$cf['id']]);
        $o_final = X::mergeArrays($o_src, $o_dest);
        // Order remains the dest one
        $o_final[$cf['num']] = $o_dest[$cf['num']];
        $tables              = $this->db->getForeignKeys($cf['id'], $this->class_cfg['table']);
        foreach ($tables as $table => $cols){
          foreach ($cols as $c){
            $num += (int)$this->db->update($table, [$c => $dest], [$c => $src]);
          }
        }

        if ($opt = $this->options($src)) {
          // Moving children
          foreach ($opt as $id => $text){
            $num += (int)$this->move($id, $dest);
          }
        }

        $num += (int)$this->set($dest, $o_final);
        $num += (int)$this->remove($src);

        $this->deleteCache($o_final[$cf['id_parent']], true);
        $this->deleteCache($o_src[$cf['id_parent']], true);

        if ($this->isSortable($o_src[$cf['id_parent']])) {
          $this->fixOrder($o_src[$cf['id_parent']]);
        }

        if ($this->isSortable($o_final[$cf['id_parent']])) {
          $this->fixOrder($o_final[$cf['id_parent']]);
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
   * X::dump($this->getIdParent(21));
   * // (int) 13
   * X::dump($this->move(21, 12));
   * // (int) 1
   * X::dump($this->getIdParent(21));
   * // (int) 12
   * ```
   *
   * @param int $id        The option's ID
   * @param int $id_parent The new id_parent
   * @return int|null
   */
  public function move($id, $id_parent)
  {
    $res = null;
    if (($o = $this->option($id))
        && ($target = $this->option($id_parent))
    ) {
      $upd = [$this->fields['id_parent'] => $id_parent];
      if ($this->isSortable($id_parent)) {
        $upd[$this->fields['num']] = empty($target['num_children']) ? 1 : $target['num_children'] + 1;
      }

      $res = $this->db->update(
        $this->class_cfg['table'], $upd, [
        $this->fields['id'] => $id
        ]
      );
      $this->deleteCache($id_parent);
      $this->deleteCache($id);
      $this->deleteCache($o[$this->fields['id_parent']]);
    }

    return $res;
  }


  /**
   * Sets the order configuration for each option of a sortable given parent
   *
   * @param int     $id
   * @param boolean $deep
   * @return $this
   */
  public function fixOrder($id, $deep = false)
  {
    if ($this->check() && $this->isSortable($id) && $its = $this->fullOptions($id)) {
      $cf  =& $this->class_cfg;
      $p   = 1;
      foreach ($its as $it) {
        if ($it[$this->fields['num']] !== $p) {
          $this->db->update(
            $cf['table'], [
            $this->fields['num'] => $p
            ], [
            $this->fields['id'] => $it[$this->fields['id']]
            ]
          );
          $this->deleteCache($it[$this->fields['id']]);
        }

        $p++;
        if ($deep) {
          $this->fixOrder($it[$this->fields['id']]);
        }
      }
    }

    return $this;
  }


  /**
   * @param $id
   * @return array|null
   */
  public function getCodePath($id)
  {
    $args = func_get_args();
    $res  = [];
    while ($o = $this->nativeOption(...$args)) {
      if ($o[$this->fields['code']]) {
        $res[] = $o[$this->fields['code']];
        if ($o[$this->fields['id_parent']] === $this->default) {
          break;
        }

        $args = [$o[$this->fields['id_parent']]];
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


  /**
   * @param array $options
   * @param array $results
   * @return array|null
   */
  public function analyzeOut(array $options, array &$results = [])
  {
    if ($this->check()) {

      if (isset($options[0]) && is_array($options[0])) {
        foreach ($options as $option) {
          $this->analyzeOut($option, $results);
        }
        return $results;
      }

      if (empty($results)) {
        $results['options'] = [];
        $results['ids']     = [];
        $results['aliases'] = [];
      }

      if (!empty($options[$this->fields['id']])) {
        $results['ids'][$options[$this->fields['id']]] = null;
      }

      if (!empty($options[$this->fields['id_alias']])) {
        $results['aliases'][$options[$this->fields['id_alias']]] = [
          'id' => null,
          'codes' => $this->getCodePath($options[$this->fields['id_alias']])
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
          $this->analyzeOut($it, $results);
        }
      }

      return $results;
    }

    return null;
  }


  /**
   * @param string $id
   * @param string $mode
   * @return array|null
   * @throws Exception
   */
  public function export(string $id, string $mode = 'single'): ?array
  {
    $modes = ['children', 'full', 'sfull', 'schildren', 'simple', 'single'];
    if (!in_array($mode, $modes)) {
      throw new Exception(X::_("The given mode is forbidden"));
    }

    $simple = false;
    switch ($mode) {
      case 'single':
        $o = $this->rawOption($id);
        break;
      case 'simple':
        $o = $this->option($id);
        $simple = true;
        break;
      case 'schildren':
        $o = $this->fullOptions($id);
        $simple = true;
        break;
      case 'children':
        $o = $this->exportDb($id, false, true);
        break;
      case 'full':
        $o = $this->exportDb($id, true, true);
        break;
      case 'sfull':
        $o = $this->fullTree($id);
        $simple = true;
        break;
    }

    if ($o) {
      if ($simple) {
        $opt =& $this;
        $fn  = function ($o) use (&$opt) {

          $o[$opt->fields['text']] = $opt->rawText($o[$opt->fields['id']]);

          $cfg = $opt->getCfg($o[$this->fields['id']]);
          if (!is_array($cfg) || !empty($cfg['inherit_from'])) {
            $cfg = [];
          }
          elseif (!empty($cfg['schema']) && is_string($cfg['schema'])) {
            $cfg['schema'] = json_decode($cfg['schema'], true);
          }

          if (isset($cfg['id'])) {
            //unset($cfg['id']);
          }

          if (isset($cfg['scfg'])
              && !empty($cfg['scfg']['schema']) && is_string($cfg['scfg']['schema'])
          ) {
            $cfg['scfg']['schema'] = json_decode($cfg['scfg']['schema'], true);
          }

          if (!empty($cfg['id_root_alias'])) {
            if ($codes = $opt->getCodePath($cfg['id_root_alias'])) {
              $cfg['id_root_alias'] = $codes;
            }
            else {
              unset($cfg['id_root_alias']);
            }
          }

          foreach ($cfg as $n => $v) {
            if (!$v) {
              unset($cfg[$n]);
            }
          }

          if (!empty($cfg)) {
            $o[$this->fields['cfg']] = $cfg;
          }

          unset($o[$this->fields['id_parent']]);
          if (isset($o['num_children'])) {
            unset($o['num_children']);
          }

          if (isset($o['alias'])) {
            unset($o['alias']);
          }

          foreach ($o as $n => $v) {
            if (!$v) {
              unset($o[$n]);
            }
          }

          if (!empty($o[$this->fields['id_alias']])
              && ($codes = $opt->getCodePath($o[$this->fields['id_alias']]))
          ) {
            $o[$this->fields['id_alias']] = $codes;
          }
          else {
            unset($o[$this->fields['id_alias']]);
          }

          return $o;
        };

        switch ($mode) {
          case 'simple':
            $o = $fn($o);
            break;
          case 'schildren':
            $o = X::map($fn, $o, 'items');
            break;
          case 'sfull':
            $o = $fn($o);
            $o['items'] = empty($o['items']) ? [] : X::map($fn, $o['items'], 'items');
            break;
        }
      }

      return $o;
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
   * @return array|string|null
   */
  public function exportDb($id, bool $deep = false, bool $return = false, bool $aliases = false)
  {
    if (($ret = $deep ? $this->rawTree($id) : $this->rawOptions($id))) {
      $ret  = $this->analyzeOut($ret);
      $res  = [];
      $done = [];
      $max  = 3;
      foreach ($ret['options'] as $i => $o) {
        if (!$i || in_array($o[$this->fields['id_parent']], $done, true)) {
          if (empty($o[$this->fields['id_alias']])) {
            $res[]  = $o;
            $done[] = $o[$this->fields['id']];
          }
        }
      }

      while ($max && (count($res) < count($ret['options']))) {
        foreach ($ret['options'] as $i => $o) {
          if (!empty($o[$this->fields['id_alias']])
              && !in_array($o[$this->fields['id']], $done, true)
              && in_array($o[$this->fields['id_parent']], $done, true)
              && in_array($o[$this->fields['id_alias']], $done, true)
          ) {
            $res[]  = $o;
            $done[] = $o[$this->fields['id']];
          }
        }

        $max--;
      }

      if (count($res) < count($ret['options'])) {
        foreach ($ret['options'] as $i => $o) {
          if (!in_array($o[$this->fields['id_parent']], $done, true)) {
            $o[$this->fields['id_parent']] = $this->getCodePath($o[$this->fields['id_parent']]);
          }

          if (!empty($o[$this->fields['id_alias']])
              && !in_array($o[$this->fields['id']], $done, true)
          ) {
            if (!in_array($o[$this->fields['id_alias']], $done, true)) {
              $code_path     = $this->getCodePath($o[$this->fields['id_alias']]);
              $o[$this->fields['id_alias']] = $code_path ?: $o[$this->fields['id_alias']];
            }

            $res[]  = $o;
            $done[] = $o[$this->fields['id']];
          }
        }
      }

      return $return ? $res : var_export($res, 1);
    }

    return null;
  }


  public function importAll(array $options, $id_parent = null) {
    $res = 0;
    foreach ($this->import($options, $id_parent) as $num) {
      $res += $num;
    }

    return $res;
  }


  /**
   * Insert into the option table an exported array of options
   *
   * ```php
   * ```
   *
   * @todo Usage example
   * @param array    $options   An array of option(s) as export returns it
   * @param array|string|int|null $id_parent The option target, if not specified {@link default}
   * @param array|null $todo
   * @return iterable|null
   */
  public function import(array $options, $id_parent = null, array &$todo = null)
  {
    if (is_array($id_parent)) {
      $id_parent = $this->fromCode($id_parent);
    }
    elseif (null === $id_parent) {
      $id_parent = $this->default;
    }

    if (!empty($options) && $this->check() && $this->exists($id_parent)) {
      $c       =& $this->fields;
      $num     = 0;
      $is_root = false;
      if ($todo === null) {
        $is_root = true;
        $todo    = [];
      }

      if (!isset($options[0])) {
        $options = [$options];
      }

      if (X::isAssoc($options)) {
        $options = [$options];
      }

      foreach ($options as $o) {
        $tmp   = [];
        $items = [];
        /** @todo Temp solution */
        if (!is_array($o)) {
          continue;
        }
        if (isset($o[$c['id']])) {
          if ($this->exists($o[$c['id']])) {
            unset($o[$c['id']]);
          }
        }

        $o[$c['id_parent']] = $id_parent ?: $this->default;
        if (isset($o['items'])) {
          $items = $o['items'] ?: null;
          unset($o['items']);
        }


        $hasNoText = empty($o[$c['text']]);
        if (isset($o[$c['id_alias']])) {
          $tmp['id_alias'] = $o[$c['id_alias']];
          if ($hasNoText) {
            $o[$c['text']] = 'waiting for alias';
          }
          unset($o[$c['id_alias']]);
        }

        if (isset($o[$c['cfg']]) && !empty($o[$c['cfg']]['id_root_alias'])) {
          $tmp['id_root_alias'] = $o[$c['cfg']]['id_root_alias'];
          unset($o[$c['cfg']]['id_root_alias']);
        }

        if ($id = $this->add($o, true)) {
          yield 1;
          if (!empty($tmp)) {
            $todo[$id] = $tmp;
          }

          if (!empty($items)) {
            foreach ($this->import($items, $id, $todo) as $success) {
              yield $success;
            }
          }
        }
        else {
          X::log($o);
          throw new Exception(X::_("Error while importing: impossible to add"));
        }
      }

      if ($is_root && !empty($todo)) {
        foreach ($todo as $id => $td) {
          if (!empty($td['id_alias'])) {
            if ($id_alias = $this->fromCode(...$td['id_alias'])) {
              try {
                $this->setAlias($id, $id_alias);
                if ($hasNoText) {
                  $this->setText($id, null);
                }
              }
              catch (Exception $e) {
                throw new Exception($e->getMessage());
              }
            }
            else {
              X::log($td['id_alias']);
              throw new \Exception(
                X::_(
                  "Error while importing: impossible to set the alias %s",
                  json_encode($td, JSON_PRETTY_PRINT)
                )
              );
            }
          }

          if (!empty($td['id_root_alias'])
              && ($id_root_alias = $this->fromCode(...$td['id_root_alias']))
          ) {
            $this->setcfg($id, ['id_root_alias' => $id_root_alias], true);
          }
        }
      }

    }

  }


  /**
   * Copies and insert an option into a target option
   *
   * ```php
   * ```
   *
   * @todo Usage example
   * @param int|string  $id     The source option's ID
   * @param int|string  $target The destination option's ID
   * @param boolean     $deep   If set to true, children will also be duplicated
   * @param boolean     $force  If set to true and option exists it will be merged
   * @return int|null The number of affected rows or null if option not found
   */
  public function duplicate($id, $target, $deep = false, $force = false, $return_num = false)
  {
    $res    = null;
    $target = $this->fromCode($target);
    if (Str::isUid($target)) {
      if ($opt = $this->export($id, $deep ? 'sfull' : 'simple')) {
        foreach ($this->import($opt, $target) as $num) {
          $res += $num;
        }
        $this->deleteCache($target);
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
   * @param int|array $id   The options' ID on which children the function should be applied
   * @param boolean   $deep If set to true the function will be applied to all children's levels
   * @param boolean   $force If set to true it will update the row in db without checking with $originals
   * @return int|null The number of affected rows or null if option not found
   */
  public function apply(callable $f, $id, $deep = false, bool $force = false)
  {
    if ($this->check()) {
      $originals = \is_array($id) ? $id : ( $deep ? $this->fullTree($id) : $this->fullOptions($id) );
      if (isset($originals['items'])) {
        $originals = $originals['items'];
      }
      $t = $this;
      $originals = $this->map(function($o) use($t){
        $o[$t->fields['text']] = $t->rawText($o[$this->fields['id']]);
        return $o;
      }, $originals, $deep);
      $opts = $this->map($f, $originals, $deep);
      if (\is_array($opts)) {
        $changes = 0;
        foreach ($opts as $i => $o){
          if ($force || $originals[$i] !== $o) {
            $changes += (int)$this->set($o[$this->fields['id']], $o);
          }

          if ($deep && !empty($o['num_children']) && !empty($o['items'])) {
            $changes += (int)$this->apply($f, $o, 1, true);
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
   * @param int|array $id   The options' ID on which children the function should be applied
   * @param boolean|int   $deep If set to true the function will be applied to all children's levels
   * @return array The new array with the function applied
   */
  public function map(callable $f, $id, $deep = false)
  {
    $opts = \is_array($id) ? $id : ( $deep ? $this->fullTree($id) : $this->fullOptions($id) );
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
   * @return array The new array with the function applied
   */
  public function mapCfg(callable $f, $id, $deep = false)
  {
    $opts = \is_array($id) ? $id : ( $deep ? $this->fullTree($id) : $this->fullOptions($id) );
    if (isset($opts['items'])) {
      $opts = $opts['items'];
    }

    $res = [];
    if (\is_array($opts)) {
      foreach ($opts as $i => $o){
        $o[$this->fields['cfg']] = $this->getCfg($o[$this->fields['id']]);
        $opts[$i] = $f($o);
        if ($deep && $opts[$i] && !empty($opts[$i]['items'])) {
          $opts[$i]['items'] = $this->mapCfg($f, $opts[$i]['items'], 1);
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
  public function textValueCategories()
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
   * @return array Un tableau des caractéristiques de chaque option de la catégorie, indexée sur leur `id`
   */
  public function fullCategories()
  {
    if ($opts = $this->fullOptions(false)) {
      foreach ($opts as $k => $o){
        if (!empty($o['default'])) {
          $opts[$k]['default'] = $this->text($o['default']);
        }
      }
    }

    return $opts ?? [];
  }


  /**
   * Retourne toutes les caractéristiques des options d'une catégorie donnée dans un tableau indexé sur leur `id`
   *
   * @param null $id
   * @return array Un tableau des caractéristiques de chaque option de la catégorie, indexée sur leur `id`
   */
  public function jsCategories($id = null)
  {
    if (!$id) {
      $id = $this->default;
    }

    if ($tmp = $this->getCache($id, __FUNCTION__)) {
      return $tmp;
    }

    $res = [
      'categories' => []
    ];
    if ($cats = $this->fullOptions($id ?: false)) {
      foreach ($cats as $cat){
        if (!empty($cat['tekname'])) {
          $res[$cat['tekname']] = $this->textValueOptions($cat[$this->fields['id']]);
          $res['categories'][$cat[$this->fields['id']]] = $cat['tekname'];
        }
      }
    }

    $this->setCache($id, __FUNCTION__, $res);
    return $res;
  }


  /**
   * Checks whether an option has _permissions_ in its parent cfg
   *
   * ```php
   * X::dump($opt->hasPermission('bbn_ide'));
   * // (bool) true
   * ```
   *
   * @param mixed $code Any option(s) accepted by {@link fromCode()}
   * @return bool
   */
  public function hasPermission($code = null)
  {
    if (Str::isUid($p = $this->getIdParent(\func_get_args()))) {
      $cfg = $this->getCfg($p);
      return !empty($cfg['permissions']);
    }

    return null;
  }


  /**
   * Returns an array of _permissions_ from origin $id
   *
   * ```php
   * X::dump($opt->findPermissions());
   * /* Returns a full tree of permissions for all options
   * array []
   * ```
   *
   * @todo Returned comments to add
   * @param int|null $id   The origin's ID
   * @param boolean  $deep If set to true the children will also be searched
   * @return array|null An array of permissions if there are, null otherwise
   */
  public function findPermissions($id = null, $deep = false)
  {
    if ($this->check()) {
      if (\is_null($id)) {
        $id = $this->default;
      }

      $cfg = $this->getCfg($id);
      if (!empty($cfg['permissions'])) {
        $perms = [];
        if ($opts  = $this->fullOptionsCfg($id)) {
          foreach ($opts as $opt){
            $o = [
              'icon' => $opt[$this->fields['cfg']]['icon'] ?? 'nf nf-fa-cog',
              'text' => $this->getTranslation($opt[$this->fields['id']]) ?: $opt[$this->fields['text']],
              'id' => $opt[$this->fields['id']]
            ];
            if ($deep && !empty($opt[$this->fields['cfg']]['permissions'])) {
              $o['items'] = $this->findPermissions($opt[$this->fields['id']], true);
            }

            $perms[] = $o;
          }
        }

        return $perms;
      }
    }

    return null;
  }


  /**
   * @return int|null
   */
  public function updatePlugins(): ?int
  {
    if (($pluginAlias = $this->fromCode('plugin', 'list', 'templates', 'option', 'appui'))
        && ($export = $this->export($pluginAlias, 'sfull'))) {
      $idPlugins = $this->getAliasItems($pluginAlias);
      $res = 0;
      foreach ($idPlugins as $idPlugin) {
        foreach ($this->import($export['items'], $idPlugin) as $num) {
          $res += $num;
        }
      }

      return $res;
    }

    return null;
  }


  /**
   * @return int|null
   */
  public function getParentPlugin($code = null): ?string
  {
    if ($pluginAlias = $this->fromCode('plugin', 'list', 'templates', 'option', 'appui')) {
      $ids = array_reverse($this->parents(...\func_get_args()));
      foreach ($ids as $id) {
        if ($this->alias($id) === $pluginAlias) {
          return $id;
        }
      }
    }

    return null;
  }


  /**
   * @param string|null $id
   * @return int|null
   */
  public function applyTemplate(string $id = null): ?int
  {
    if (!defined('BBN_APPUI')) {
      throw new Exception(X::_("Impossible to apply a template without appui defined"));
    }

    if (!($idAlias = $this->alias($id))) {
      throw new Exception(X::_("Impossible to apply a template, the option must be aliased"));
    }

    $templateParent = $this->parent($idAlias);
    if (!$templateParent['id_alias']) {
      throw new Exception(X::_("Impossible to apply a template, the template's parent must have an alias"));
    }

    if ($templateParent['id_alias'] !== $this->fromCode('list', 'templates', 'option', 'appui')) {
      throw new Exception(X::_("Impossible to apply a template, the template's parent must be aliased with the templates' list"));
    }

    if (($export = $this->export($idAlias, 'sfull')) && !empty($export['items'])) {
      $res = 0;
      foreach ($this->import($export['items'], $id) as $num) {
        $res += $num;
      }

      return $res;
    }

    return null;
  }


  /**
   * @param string|null $id
   * @return int|null
   */
  public function updateTemplate(string $id = null): ?int
  {
    if (defined('BBN_APPUI') && $this->exists($id)) {
      $res = 0;
      // All the options referring to this template
      $all = $this->getAliases($id);
      if (!empty($all)
          && ($export = $this->export($id, 'sfull'))
          && !empty($export['items'])
      ) {
        foreach ($all as $a) {
          foreach ($this->import($export['items'], $a[$this->fields['id']]) as $num) {
            $res += $num;
          }
        }
      }

      return $res;
    }

    return null;
  }


  /**
   * @return int|null
   */
  public function updateAllTemplates(): ?int
  {
    if (defined('BBN_APPUI')
        && ($id = $this->fromCode('list', 'templates', 'option', 'appui'))
    ) {
      $res = 0;
      foreach ($this->itemsRef($id) ?? [] as $a) {
        $res += (int)$this->updateTemplate($a);
      }

      return $res;
    }

    return null;
  }


  /**
   * Returns an array containing all options that have the property i18n set
   * @param string|null $startFromID
   * @param bool $items
   * @return array
   */
  public function findI18n(?string $startFromID = null, $items = false)
  {
    $res = [];
    if ($this->check()) {
      $where = [[
        'field' => 'JSON_UNQUOTE(JSON_EXTRACT(' . $this->fields['cfg'] . ', "$.i18n"))',
        'operator' => 'isnotnull'
      ], [
        'field' => 'JSON_UNQUOTE(JSON_EXTRACT(' . $this->fields['cfg'] . ', "$.i18n"))',
        'operator' => '!=',
        'value' => ''
      ]];
      if (Str::isUid($startFromID)) {
        $where = [[
          'field' => $this->fields['id'],
          'value' => $startFromID
        ]];
      }
      $opts = $this->db->rselectAll([
        'table' => $this->class_cfg['table'],
        'fields' => [
          $this->fields['id'],
          $this->fields['id_parent'],
          $this->fields['code'],
          $this->fields['text'],
          'language' => 'JSON_UNQUOTE(JSON_EXTRACT(' . $this->fields['cfg'] . ', "$.i18n"))'
        ],
        'where' => $where
      ]);

      if ($opts) {
        foreach ($opts as $opt){
          if (!empty($opt[$this->fields['code']])
            && Str::isInteger($opt[$this->fields['code']])
          ) {
            $opt[$this->fields['code']] = (int)$opt[$this->fields['code']];
          }
          if (\is_null(X::find($res, [$this->fields['id'] => $opt[$this->fields['id']]]))) {
            $cfg = $this->getCfg($opt[$this->fields['id']]);
            if (!empty($cfg['i18n'])) {
              $res[] = $opt;
            }
            if (!empty($cfg['i18n_inheritance'])) {
              $this->findI18nChildren($opt, $res, $cfg['i18n_inheritance'] === 'cascade');
            }
          }
        }
      }
      if (!empty($res) && !empty($items)) {
        $res2 = [];
        foreach ($res as $r) {
          $res2[] = \array_merge($r, [
            'items' => array_values(array_filter($res, function($o) use($r) {
              return $o[$this->fields['id_parent']] === $r[$this->fields['id']];
            }))
          ]);
        }
        return $res2;
      }
    }

    return $res;
  }


  /**
   * returns an array containing the option (having the property i18n set) corresponding to the given id
   *
   * @param $id
   * @param bool $items
   * @return array
   */
  public function findI18nOption($id, $items = true)
  {
    $res = [];
    if ($this->check()) {
      if ($opt = $this->db->rselect(
        $this->class_cfg['table'], [
          $this->fields['id'],
          $this->fields['id_parent'],
          $this->fields['text'],
          $this->fields['cfg']
        ], [$this->fields['id'] => $id]
      )
      ) {
        $cfg  = json_decode($opt[$this->fields['cfg']]);
        if (!empty($cfg->i18n)) {
          $opt['language'] = $cfg->i18n;
        }

        unset($opt[$this->fields['cfg']]);
        if (!empty($items)) {
          $res[] = array_merge($opt, ['items' => $this->fullOptions($id) ?? []]);
        }
        else {
          $res[] = $opt;
        }
      }
    }

    return $res;
  }


  /**
   * Returns an array containing all languages set
   *
   * @return null|array
   */
  public function findI18nLocales(?string $startFromID = null): ?array
  {
    if ($this->check()) {
      if (empty($startFromID)) {
        return \array_unique($this->db->getFieldValues([
          'table' => $this->class_cfg['table'],
          'fields' => [
            'JSON_UNQUOTE(JSON_EXTRACT('.$this->fields['cfg'].', "$.i18n"))'
          ],
          'where' => [[
            'field' => 'JSON_UNQUOTE(JSON_EXTRACT('.$this->fields['cfg'].', "$.i18n"))',
            'operator' => 'isnotnull'
          ], [
            'field' => 'JSON_UNQUOTE(JSON_EXTRACT('.$this->fields['cfg'].', "$.i18n"))',
            'operator' => '!=',
            'value' => ''
          ]]
        ]));
      }
      $res = [];
      $cfg = $this->getCfg($startFromID);
      if (!empty($cfg['i18n'])) {
        $res[] = $cfg['i18n'];
      }
      if ($items = $this->items($startFromID)) {
        foreach ($items as $item) {
          $res = X::mergeArrays($res, $this->findI18n($item));
        }
      }
      return \array_unique($res);
    }
    return null;
  }


  /**
   * Returns an array containing all options that have the property i18n set
   *
   * @param string $locale
   * @param bool $items
   * @return array
   */
  public function findI18nByLocale(string $locale, $items = false): array
  {
    $res = [];
    if ($this->check()) {
      $opts = $this->db->rselectAll([
        'table' => $this->class_cfg['table'],
        'fields' => [
          $this->fields['id'],
          $this->fields['id_parent'],
          $this->fields['code'],
          $this->fields['text'],
          'language' => 'JSON_UNQUOTE(JSON_EXTRACT('.$this->fields['cfg'].', "$.i18n"))'
        ],
        'where' => [
          'JSON_UNQUOTE(JSON_EXTRACT('.$this->fields['cfg'].', "$.i18n"))' => $locale
        ]
      ]) ?: [];
      if ($opts) {
        foreach ($opts as $opt){
          if (!empty($opt[$this->fields['code']])
            && Str::isInteger($opt[$this->fields['code']])
          ) {
            $opt[$this->fields['code']] = (int)$opt[$this->fields['code']];
          }
          if (\is_null(X::find($res, [$this->fields['id'] => $opt[$this->fields['id']]]))) {
            $cfg = $this->getCfg($opt[$this->fields['id']]);
            $res[] = $opt;
            if (!empty($cfg['i18n_inheritance'])) {
              $this->findI18nChildren($opt, $res, $cfg['i18n_inheritance'] === 'cascade');
            }
          }
        }
      }
      if (!empty($res) && !empty($items)) {
        $res2 = [];
        foreach ($res as $r) {
          $res2[] = \array_merge($r, [
            'items' => array_values(array_filter($res, function($o) use($r) {
              return $o[$this->fields['id_parent']] === $r[$this->fields['id']];
            }))
          ]);
        }
        return $res2;
      }
    }
    return $res;
  }


  public function findI18nById(string $id): ?string
  {
    if (Str::isUid($id = $this->fromCode(\func_get_args()))) {
      if ($c = $this->cacheGet($id, __FUNCTION__)) {
        return $c['i18n'];
      }

      $i18n = null;
      if ($cfg = $this->getCfg($id)) {
        if (!empty($cfg['i18n'])) {
          $i18n = $cfg['i18n'];
        }
      }

      if (empty($i18n)
        && ($parents = $this->parents($id))
      ) {
        foreach ($parents as $i => $parent) {
          $pcfg = $this->getCfg($parent);
          if (empty($pcfg)
            || empty($pcfg['i18n'])
          ) {
            continue;
          }

          if (!empty($pcfg['i18n_inheritance'])
            && (($pcfg['i18n_inheritance'] === 'cascade')
              || (($pcfg['i18n_inheritance'] === 'children')
                && ($i === 0)))
          ) {
            $i18n = $pcfg['i18n'];
            break;
          }

          $i18n = null;
          break;
        }
      }

      $this->cacheSet($id, __FUNCTION__, ['i18n' => $i18n]);
      return $i18n;
    }

    return null;
  }


  public function getTranslation(string $id, string $locale = ''): ?string
  {
    if (Str::isUid($id)
      && ($originalLocale = $this->findI18nById($id))
      && ($text = $this->text($id))
    ) {
      if (empty($locale)) {
        $locale = $this->getTranslatingLocale($id);
      }
      if (!empty($locale)) {
        $i18nCls = new I18n($this->db);
        return  $i18nCls->getTranslation($text, $originalLocale, $locale);
      }
    }
    return null;
  }


  /**
   * Gets the first row from a result
   *
   * @param array $where
   * @return array|null
   */
  protected function getRow(array $where): ?array
  {
    if ($res = $this->getRows($where, 1)) {
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
   * @return array|null An array of rows, empty if not found, null if there is an error in the where config
   */
  protected function getRows(array $where = [], int $limit = 0, int $start = 0): ?array
  {
    $db  =& $this->db;
    $tab = $this->class_cfg['table'];
    $c   =& $this->fields;
    /** @todo Checkout */
    $cols = [];
    foreach ($c AS $k => $col){
      // All the columns except cfg
      if (!\in_array($k, $this->non_selected, true)) {
        $cols[] = $db->cfn($col, $tab);
      }
    }

    $cols['num_children'] = 'COUNT('.$db->escape($db->cfn($c['id'], $tab.'2', true)).')';
    $todo                 = [$c['id'], $c['id_parent'], $c['id_alias']];
    foreach ($todo as $to){
      if (!empty($where[$to]) && !Str::isBuid($where[$to])) {
        $where[$to] = $where[$to];
      }
      elseif (!empty($where[$this->db->cfn($to, $tab)]) && !Str::isBuid($where[$this->db->cfn($to, $tab)])) {
        $where[$this->db->cfn($to, $tab)] = $where[$this->db->cfn($to, $tab)];
      }
    }

    $res = $this->db->rselectAll(
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
    if (!empty($res)) {
      foreach ($res as $i => $r) {
        if (!empty($r[$this->fields['code']])
          && Str::isInteger($r[$this->fields['code']])
        ) {
          $res[$i][$this->fields['code']] = (int)$r[$this->fields['code']];
        }
      }
    }
    return $res;
  }


  /**
   * @param $name
   * @param $val
   */
  private function _set_local_cache($name, $val): void
  {
    $this->_local_cache[$name] = $val;
  }


  /**
   * @param $name
   * @return string|null
   */
  private function _get_local_cache($name): ?string
  {
    return isset($this->_local_cache[$name]) ? $this->_local_cache[$name] : null;
  }


  /**
   * Transforms an array of parameters into valid option array
   * @param array $it
   * @return bool
   * @throws Exception
   */
  private function _prepare(array &$it): bool
  {
    // The table's columns
    $c =& $this->fields;

    // If id_parent is undefined it uses the default
    if (!isset($it[$c['id_parent']])) {
      $it[$c['id_parent']] = $this->default;
    }
    elseif (is_array($it[$c['id_parent']])) {
      if ($id_parent = $this->fromCode(...$it[$c['id_parent']])) {
        $it[$c['id_parent']] = $id_parent;
      }
      else {
        throw new Exception(X::_("Impossible to find the parent"));
      }
    }
    elseif (!$this->exists($it[$c['id_parent']])) {
      throw new Exception(X::_("Impossible to find the parent"));
    }

    if (empty($it[$c['id_alias']])) {
      $it[$c['id_alias']] = null;
    }
    elseif (is_array($it[$c['id_alias']])) {
      if ($id_alias = $this->fromCode(...$it[$c['id_alias']])) {
        $it[$c['id_alias']] = $id_alias;
      }
      else {
        throw new Exception(X::_("Impossible to find the alias"));
      }
    }
    elseif (!$this->exists($it[$c['id_alias']])) {
      throw new \Exception(X::_("Impossible to find the alias"));
    }

    if (array_key_exists($c['id'], $it) && empty($it[$c['id']])) {
      unset($it[$c['id']]);
    }

    if (isset($it[$c['cfg']])) {
      if (!is_array($it[$c['cfg']]) && Str::isJson($it[$c['cfg']])) {
        $it[$c['cfg']] = json_decode($it[$c['cfg']], true);
      }

      $cfg =& $it[$c['cfg']];
      if (is_array($cfg) && !empty($cfg['id_root_alias'])) {
        if (is_array($cfg['id_root_alias'])) {
          if ($id_root_alias = $this->fromCode(...$cfg['id_root_alias'])) {
            $cfg['id_root_alias'] = $id_root_alias;
          }
          else {
            throw new \Exception(X::_("Impossible to find the root alias"));
          }
        }
        elseif (!$this->exists($cfg['id_root_alias'])) {
          throw new \Exception(X::_("Impossible to find the root alias"));
        }
      }
    }

    // Text is required and parent exists
    if (!empty($it[$c['id_parent']])
        && (!empty($it[$c['text']]) || !empty($it[$c['id_alias']]) || !empty($it[$c['code']]))
        && ($parent = $this->option($it[$c['id_parent']]))
    ) {
      // If the id_parent property is a code or a sequence of codes have to set it as uid
      $it[$c['id_parent']] = $parent[$c['id']];

      // If code is empty it MUST be null
      if (empty($it[$c['code']])) {
        $it[$c['code']] = null;
      }

      // If text is empty it MUST be null
      if (empty($it[$c['text']])) {
        $it[$c['text']] = null;
      }

      // Unsetting computed values
      if (isset($it[$c['value']]) && Str::isJson($it[$c['value']])) {
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
      else {
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

        if (!Str::isJson($it[$c['cfg']]) || in_array($it[$c['cfg']], ['{}', '[]'], true)) {
          $it[$c['cfg']] = null;
        }
      }

      $is_sortable = $this->isSortable($parent[$c['id']]);
      // If parent is sortable and order is not defined we define it as last
      if (isset($it[$c['num']]) && !$is_sortable) {
        unset($it[$c['num']]);
      }
      elseif ($is_sortable && empty($it[$c['num']])) {
        $it[$c['num']] = ($parent['num_children'] ?? 0) + 1;
      }

      return true;
    }

    throw new Exception(
      X::_("Impossible to make an option out of it...")
      .PHP_EOL.json_encode($it, JSON_PRETTY_PRINT)
    );
  }


  /**
   * Gives to option's database row array each of the column value's JSON properties
   * Only if value is an associative array value itself will be unset
   * @param array $opt
   * @return array|bool
   */
  private function _set_value(array &$opt): ?array
  {
    if (!empty($opt[$this->fields['value']]) && Str::isJson($opt[$this->fields['value']])) {
      $val = json_decode($opt[$this->fields['value']], true);
      if (X::isAssoc($val)) {
        foreach ($val as $k => $v){
          if (!isset($opt[$k])) {
            $opt[$k] = $v;
          }
        }

        unset($opt[$this->fields['value']]);
      }
      else{
        $opt[$this->fields['value']] = $val;
      }
    }

    return $opt;
  }


  private function findI18nChildren(array $opt, array &$res, bool $cascade = false, string $locale = null){
    $fid = $this->fields['id'];
    if ($children = $this->fullOptions($opt[$fid])) {
      foreach ($children as $child) {
        if (\is_null(X::find($res, [$fid => $child[$fid]]))) {
          $cfg = $this->getCfg($child[$fid]);
          $child = [
            $this->fields['id'] => $child[$this->fields['id']],
            $this->fields['id_parent'] => $child[$this->fields['id_parent']],
            $this->fields['code'] => $child[$this->fields['code']],
            $this->fields['text'] => $child[$this->fields['text']],
            'language' => !empty($cfg['i18n']) ? $cfg['i18n'] : $opt['language']
          ];
          if (empty($locale)
            || ($child['language'] === $locale)
          ) {
            $res[] = $child;
          }
          if (!empty($cfg['i18n_inheritance'])
            || (empty($cfg['i18n']) && $cascade)
          ) {
            $c = ($cfg['i18n_inheritance'] === 'cascade')
              || (empty($cfg['i18n']) && $cascade);
            $this->findI18nChildren($child, $res, $c);
          }
        }
      }
    }
    return $res;
  }


  private function getTranslatingLocale(string $id): ?string
  {
    $originalLocale = $this->findI18nById($id);
    $locale = null;
    if (!empty($originalLocale)
      && \defined('BBN_LANG')
      && (BBN_LANG !== $originalLocale)
    ) {
      $locale = BBN_LANG;
    }

    return $locale;
  }


}
