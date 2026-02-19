<?php

namespace bbn\Db\Languages\Models\Sql;

use Exception;
use PDO;
use PDOException;
use PDOStatement;
use bbn\X;
use bbn\Str;
use bbn\Db\Query;

trait Connection
{
  /** @var array Connection config (normalized by getConnection()). */
  protected array $cfg;

  /** @var string|null Connection code as stored in option (code_host). */
  protected $connection_code;

  /** @var string|null Connection host. */
  protected $host;

  /** @var string|null Connection username. */
  protected $username;

  /** @var PDO|null Underlying PDO connection (null when closed). */
  protected ?PDO $pdo = null;

  /** @var mixed Currently selected database name/file. */
  protected $current;

  /** @var string Unique hash for current connection. */
  protected string $hash;

  /** @var string Hash contour used by makeHash(). */
  protected $hash_contour = '__BBN__';

  /** @var int Fancy mode flag (Query wrapper enabled/disabled). */
  protected int $_fancy = 1;

  /** @var bool If true last_query/last_params are updated. */
  protected bool $_last_enabled = true;

  /** @var int Cache renewal duration (seconds). */
  protected int $cache_renewal = 3600;

  /**
   * Constructor (creates PDO connection + initializes cache and connection metadata).
   *
   * @method __construct
   * @param array $cfg Connection configuration
   * @return void
   * @throws Exception
   */
  public function __construct(array $cfg)
  {
    if (!extension_loaded('pdo_mysql')) {
      throw new Exception(X::_("The MySQL driver for PDO is not installed..."));
    }

    $cfg = $this->getConnection($cfg);

    try {
      $this->cacheInit();
      $this->current = $cfg['db'] ?? null;
      $this->host = $cfg['host'] ?? null;
      $this->username = $cfg['user'] ?? null;
      $this->connection_code = $cfg['code_host'];

      $this->pdo = new PDO(...$cfg['args']);
      $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
      $this->pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);

      $this->cfg = $cfg;
      $this->setHash($cfg['args']);

      if (!empty($cfg['cache_length'])) {
        $this->cache_renewal = (int)$cfg['cache_length'];
      }

      if (isset($cfg['on_error'])) {
        $this->on_error = $cfg['on_error'];
      }

      unset($cfg['pass']);
    }
    catch (PDOException $e) {
      $err = X::_("Impossible to create the connection") .
        " $cfg[engine] ".X::_("to")." {$this->host} "
        . X::_("with the following error") . " " . $e->getMessage();
      X::log($cfg);
      throw new Exception($err);
    }
  }

  /**
   * Destructor; closing the PDO connection makes the object unusable.
   *
   * @method __destruct
   * @return void
   */
  public function __destruct()
  {
    $this->close();
  }

  /**
   * Closes the underlying PDO connection (definitive).
   *
   * @method close
   * @return void
   */
  public function close(): void
  {
    if ($this->pdo) {
      $this->pdo = null;
    }
  }

  /**
   * Executes the original PDO::query bypassing “fancy” Query wrapper if needed.
   *
   * @method rawQuery
   * @return false|PDOStatement
   */
  public function rawQuery()
  {
    if ($this->_fancy) {
      $this->stopFancyStuff();
      $switch_to_fancy = true;
    }

    $result = $this->pdo->query(...func_get_args());

    if (!empty($switch_to_fancy)) {
      $this->startFancyStuff();
    }

    return $result;
  }

  /**
   * Executes a statement through PDO::exec.
   *
   * @method executeStatement
   * @param string $statement SQL statement
   * @return int|false
   */
  public function executeStatement($statement)
  {
    return $this->pdo->exec($statement);
  }

  /**
   * Enables fancy mode (PDOStatement class replaced by bbn\Db\Query).
   *
   * @method startFancyStuff
   * @return self
   */
  public function startFancyStuff(): self
  {
    $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [Query::class, [$this]]);
    $this->_fancy = 1;

    return $this;
  }

  /**
   * Disables fancy mode (PDOStatement is returned directly).
   *
   * @method stopFancyStuff
   * @return self
   */
  public function stopFancyStuff(): self
  {
    $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [PDOStatement::class]);
    $this->_fancy = 0;

    return $this;
  }

  /**
   * Returns the current database selected by the connection.
   *
   * @method getCurrent
   * @return string|null
   */
  public function getCurrent(): ?string
  {
    return $this->current;
  }

  /**
   * Changes the current database (USE db).
   *
   * @method change
   * @param string $db Database name/file
   * @return bool
   */
  public function change(string $db): bool
  {
    if (($this->getCurrent() !== $db) && Str::checkName($db)) {
      $this->rawQuery("USE `$db`");
      $this->current = $db;
      return true;
    }

    return false;
  }

  /**
   * Returns the engine identifier (lowercase basename of concrete class).
   *
   * @method getEngine
   * @return string
   */
  public function getEngine()
  {
    $class = static::class;
    return strtolower(X::basename(str_replace('\\', '/', $class)));
  }

  /**
   * Builds a request/statement hash.
   *
   * @method makeHash
   * @return string
   */
  protected function makeHash(): string
  {
    $args = func_get_args();
    if ((count($args) === 1) && is_array($args[0])) {
      $args = $args[0];
    }

    array_unshift($args, $this->getCurrent());
    $st = '';
    foreach ($args as $a){
      $st .= is_array($a) ? serialize($a) : '--'.$a.'--';
    }

    return $this->hash_contour.md5($st).$this->hash_contour;
  }

  /**
   * Computes and sets the connection hash.
   *
   * @method setHash
   * @return void
   */
  protected function setHash()
  {
    $this->hash = $this->makeHash(...func_get_args());
  }

  /**
   * Returns the connection hash.
   *
   * @method getHash
   * @return string
   */
  public function getHash(): string
  {
    return $this->hash;
  }
}
