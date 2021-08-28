<?php

namespace bbn\Db\Enums;

class Errors
{
  /**
   * The error configuration for continuing after an error occurred
   */
  public const E_CONTINUE = 'continue';

  /**
   * The error configuration for dying after an error occurred
   */
  public const E_DIE = 'die';

  /**
   * The error configuration for stopping all requests on all connections after an error occurred
   */
  public const E_STOP_ALL = 'stop_all';

  /**
   * The error configuration for stopping requests on the current connection after an error occurred
   */
  public const E_STOP = 'stop';
}