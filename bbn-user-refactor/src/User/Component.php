<?php

declare(strict_types=1);

namespace bbn\User;

use bbn\Db;

/** Base class for internal user components. */
abstract class Component
{
  /**
   * @param array<string, mixed> $cfg
   * @param array<string, string> $fields
   */
  public function __construct(
    protected Db $db,
    protected State $state,
    protected array $cfg,
    protected array $fields,
  ) {
  }
}
