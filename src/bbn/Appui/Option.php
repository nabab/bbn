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
  use Option\Alias;
  use Option\Cache;
  use Option\Cat;
  use Option\Cfg;
  use Option\Code;
  use Option\I18n;
  use Option\Indexed;
  use Option\Manip;
  use Option\Native;
  use Option\Options;
  use Option\Parents;
  use Option\Path;
  use Option\Permission;
  use Option\Plugin;
  use Option\Prop;
  use Option\Ref;
  use Option\Root;
  use Option\Single;
  use Option\Sub;
  use Option\Template;
  use Option\Tree;
  use Option\Write;

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


  /** @var array $class_cfg */
  protected $class_cfg;


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
    $this->initClassCfg($cfg);
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
}
