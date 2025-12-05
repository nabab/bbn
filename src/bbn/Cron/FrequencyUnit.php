<?php
declare(strict_types=1);

namespace bbn\Cron;

/**
 * Units used in the frequency string for CRON tasks.
 *
 * Examples:
 *   y1  => every 1 year
 *   m3  => every 3 months
 *   w1  => every 1 week
 *   d2  => every 2 days
 *   h1  => every hour
 *   i5  => every 5 minutes
 *   s30 => every 30 seconds
 */
enum FrequencyUnit: string
{
  case Year   = 'y';
  case Month  = 'm';
  case Week   = 'w';
  case Day    = 'd';
  case Hour   = 'h';
  case Minute = 'i';
  case Second = 's';

  /**
   * Returns true if the given single-letter code corresponds
   * to a valid frequency unit.
   */
  public static function isValidCode(string $code): bool
  {
    $code = strtolower($code);
    foreach (self::cases() as $unit) {
      if ($unit->value === $code) {
        return true;
      }
    }

    return false;
  }

  /**
   * Attempts to create a FrequencyUnit from a single-letter code.
   * Returns null if invalid.
   */
  public static function tryFromCode(string $code): ?self
  {
    $code = strtolower($code);
    foreach (self::cases() as $unit) {
      if ($unit->value === $code) {
        return $unit;
      }
    }

    return null;
  }
}
