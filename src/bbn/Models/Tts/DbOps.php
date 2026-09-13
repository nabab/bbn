<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 05/11/2016
 * Time: 02:47
 */

namespace bbn\Models\Tts;
/**
 * Database operations trait for regular (non-junction) tables.
 *
 * This trait provides generic CRUD and selection helpers on top of the
 * configuration exposed by the DB-related traits/classes.
 *
 * Expected requirements on the consuming class:
 * - a database connection in `$this->db`
 * - a resolved DB config through `getClassCfg()`
 * - initialized `$this->class_table`, `$this->class_table_index`, `$this->fields`
 * - support methods from `DbTrait`
 * - event dispatching through `emit()`
 *
 * Main features:
 * - CRUD operations
 * - single and multiple row selections
 * - existence and count checks
 * - search filter generation
 * - relation lookup helpers
 * - JSON partial update support for `cfg` columns
 */
trait DbOps
{
  use DbConfig;
  use DbFiltering;
  use DbStructure;
  use DbData;
  use DbSelection;
  use DbWrite;
}
