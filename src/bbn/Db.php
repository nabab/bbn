<?php
namespace bbn;

use Exception;

use bbn\Db\Engines;
use bbn\Db\Query;
use bbn\Db\Languages\Sql;
use bbn\Db\Internal\Actions as ttActions;
use bbn\Db\Internal\Engine as ttEngine;
use bbn\Db\Internal\Internal as ttInternal;
use bbn\Db\Internal\Native as ttNative;
use bbn\Db\Internal\Query as ttQuery;
use bbn\Db\Internal\Read as ttRead;
use bbn\Db\Internal\Shortcuts as ttShortcuts;
use bbn\Db\Internal\Structure as ttStructure;
use bbn\Db\Internal\Triggers as ttTriggers;
use bbn\Db\Internal\Types as ttTypes;
use bbn\Db\Internal\Utilities as ttUtilities;
use bbn\Db\Internal\Write as ttWrite;
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
class Db implements Db\Actions
{
  use ttActions;
  use ttEngine;
  use ttInternal;
  use ttNative;
  use ttQuery;
  use ttRead;
  use ttShortcuts;
  use ttStructure;
  use ttTriggers;
  use ttTypes;
  use ttUtilities;
  use ttWrite;
  use Cache;
  use Retriever;

  /**
   * @var Sql Can be other driver
   */
  protected Sql $language;

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
      $this->cacheInit();

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
    if ($this->language) {
      $this->language->close();
      $this->setErrorMode('continue');
    }
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
    return $this->language->getCfg();
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
   * X::adump($ctrl->db->getConnection()); 
   * ```
   * 
   * @param array $cfg The user's options
   * @return array|null The final configuration
   */
  public function getConnection(array $cfg = []): ?array
  {
    return $this->language->getConnection($cfg);
  }

  private function ensureLanguageMethodExists(string $method)
  {
    if (!method_exists($this->language, $method)) {
      throw new Exception(X::_('Method %s not found on the language %s class!', $method, $this->engine));
    }
  }
}