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
 * This class allows to manage a hierarchical table of options, including retrieving, editing, adding, and removing options.
 * It also provides functionality for grabbing a whole tree, applying functions on groups of options, and adding user-defined properties.
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
  // Traits used in this class
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

  // Default class configuration
  protected static $default_class_cfg = [
    'errors' => [],
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

  // Flag to check if the class is initialized
  private $is_init = false;

  // Fields from the options table that are not returned by default
  private $non_selected = ['cfg'];

  // Class configuration array
  protected $class_cfg;

  /**
   * Returns the existing instance of the Option class.
   *
   * @return self
   */
  public static function getOptions(): self
  {
    return self::getInstance();
  }

  /**
   * Constructor for the Option class.
   *
   * Initializes the class with a database connection object and an optional configuration array.
   *
   * @param Db $db A database connection object
   * @param array $cfg An optional configuration array
   * @throws Exception If there is an error initializing the class
   */
  public function __construct(Db $db, array $cfg = [])
  {
    // Initialize the parent class with the database connection object
    parent::__construct($db);

    // Initialize the class configuration
    $this->initClassCfg($cfg);

    // Initialize the retriever
    self::retrieverInit($this);
  }

  /**
   * Checks if the class is initialized and the database connection is valid.
   *
   * @return bool True if the class is initialized and the database connection is valid, false otherwise
   */
  public function check(): bool
  {
    // Check if the class is initialized and the database connection is valid
    return $this->init() && $this->db->check();
  }

  /**
   * Checks if an option exists in the database.
   *
   * @param string ...$codes The codes of the options to check
   * @return bool True if the option exists, false otherwise
   */
  public function exists(...$codes): bool
  {
    // Check if the option exists using the dbTraitExists method
    return $this->dbTraitExists(...$codes);
  }

  /**
   * Returns the configuration array of the class with the table structure.
   *
   * @return array The configuration array
   */
  public function getClassCfg(): array
  {
    // Return the class configuration array
    return $this->class_cfg;
  }

  /**
   * Returns the number of children for a given option.
   *
   * @param string ...$codes Any options accepted by fromCode()
   * @return int|null The number of children or null if the option is not found
   */
  public function count(...$codes): ?int
  {
    // Get the ID of the option using the fromCode method
    $id = $this->fromCode($codes);

    // Check if the ID is a valid UID
    if (Str::isUid($id)) {
      // Return the number of children for the given option
      return $this->db->count($this->class_cfg['table'], [$this->fields['id_parent'] => $id]);
    }

    // Return null if the option is not found
    return null;
  }
}
