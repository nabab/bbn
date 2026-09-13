<?php
namespace bbn\Models\Cls;

use bbn\Db as dbClass;

/**
 * Base class for objects requiring a database connection.
 *
 * This class extends {@see Basic} and provides a protected database
 * instance to child classes.
 */
abstract class Db extends Basic
{
  /**
   * Constructor.
   *
   * @param dbClass $db Database connection instance.
   */
  public function __construct(protected dbClass $db)
  {
  }
}
