<?php
/**
 * @package time
 */
namespace bbn;

use bbn\Str;
use Brick\PhoneNumber\PhoneNumber;
use Brick\PhoneNumber\PhoneNumberFormat;
use Brick\PhoneNumber\PhoneNumberParseException;
use Exception;
/**
 * Deals with date manipulation.
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Time and Date
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.2r89
 * @todo Plenty of stuff!
 */
class Phone 
{
  public static function parse(string $phone, ?string $region = null): ?PhoneNumber
  {
    try {
      return PhoneNumber::parse($phone, $region);
    }
    catch (PhoneNumberParseException $e) {
      return null;
    }
  }

  public static function format(string $phone, ?string $region = null): ?string
  {
    $ph = self::parse($phone, $region);
    if ($ph) {
      return $ph->format(PhoneNumberFormat::E164);
    }

    return null;
  }

  public static function isPhone(string $phone, ?string $region = null): bool
  {
    $ph = self::parse($phone, $region ?: (defined('BBN_LOCALE') ? strtoupper(Str::sub(explode('.', BBN_LOCALE)[0], -2)) : null));
    return $ph ? $ph->isPossibleNumber() : false;
  }

  public static function isValid(string $phone, ?string $region = null): bool
  {
    $ph = self::parse($phone, $region ?: (defined('BBN_LOCALE') ? strtoupper(Str::sub(explode('.', BBN_LOCALE)[0], -2)) : null));
    return $ph ? $ph->isValidNumber() : false;
  }

}
