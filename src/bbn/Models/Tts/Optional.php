<?php
/**
 * Implements functions for retrieving class-specific options
 *
 * User: BBN
 * Date: 05/11/2016
 * Time: 02:53
 */

namespace bbn\Models\Tts;

use Exception;
use bbn\X;
use bbn\Str;
use bbn\Appui\Option;

trait Optional
{

  /**
   * @var bool Set as true from the moment a first instance has been initiated and has defined the constants.
   */
  protected static $optional_is_init = false;

  /**
   * @var string The ID of the root option
   */
  protected static $option_root_id;

  /**
   * @var Option The Option object
   */
  protected $options;


  /**
   * Returns the option's root ID for the current class based on {@link $option_root_code}
   *
   * @return false|int
   */
  protected static function optionalInit(array|null $path = null)
  {
    if (!self::$optional_is_init) {
      $opt = Option::getInstance();
      $cls = false;
      if (!$opt) {
        throw new Exception(X::_("There is no options object as needed by").' '.__CLASS__);
      }

      if (!$path) {
        $tmp = explode('\\', __CLASS__);
        $cls = strtolower(end($tmp));
        $path = [$cls, 'appui', 'plugins'];
      }

      self::$option_root_id = $opt->fromCode(...$path);
      //X::ddump($path, self::$option_root_id);
      if (!self::$option_root_id) {
        if (empty($cls)) {
          throw new Exception("Impossible to find the option ".json_encode($path)." !!! for ".__CLASS__);
        }

        throw new Exception("Impossible to find the option $cls for ".__CLASS__);
      }

      self::$optional_is_init = true;
    }
  }


  /**
   * Sets only once all the constants used by the class.
   *
   * @param Option $opt
   * @param array             $path
   * @return void
   */
  protected static function initOptionalGlobal(Option $opt, array|null $path = null)
  {
    if (!self::$optional_is_init) {
      if (!$path) {
        $tmp                   = explode('\\', __CLASS__);
        $cls                   = end($tmp);
        $path                  = [$cls, 'appui'];
      }

      self::$option_root_id = $opt->fromCode(...$path);
      if (!self::$option_root_id) {
        X::log("Impossible to find the option $cls for ".__CLASS__, 'errors');
        return;
      }

      self::$optional_is_init = true;
    }
  }


  /**
   * Defines the options prop and launches the static init method.
   *
   * @param array $path
   * @return void
   */
  protected function initOptional(array|null $path = null)
  {
    $this->options = Option::getInstance();
    if (!$this->options) {
      throw new Exception(X::_("There is no options object as needed by").' '.__CLASS__);
    }

    self::initOptionalGlobal($this->options, $path);
  }


  public static function getOptionRoot()
  {
    self::optionalInit();
    return self::$option_root_id;
  }


  public static function getOptionsObject(): Option
  {
    $o = Option::getInstance();
    if (!$o) {
      throw new Exception(X::_("Impossible to get the options object from class").' '.__CLASS__);
    }

    return $o;
  }


  /**
   * Returns The option's ID of a category, i.e. direct children of option's root
   *
   * @param string $code
   * @return int|false
   */
  public static function getOptionId(...$codes)
  {
    $codes[] = self::getOptionRoot();
    return self::getOptionsObject()->fromCode(...$codes);
  }


  /**
   * Undocumented function
   *
   * @todo Check it, it doesn't seem ok
   * @return array
   */
  public static function getOptionsIds(...$codes): array
  {
    $codes[] = self::getOptionRoot();
    return array_flip(
      array_filter(
        self::getOptionsObject()->getCodes(...$codes),
        function ($a) {
          return $a !== null;
        }
      )
    );
  }


  public static function getOptionsTree(...$codes)
  {
    $codes[] = self::getOptionRoot();
    return ($tree = self::getOptionsObject()->fullTree(...$codes)) ? $tree['items'] : [];
  }


  public static function getOptionsTreeRef(...$codes)
  {
    $codes[] = self::getOptionRoot();
    return ($tree = self::getOptionsObject()->fullTreeRef(...$codes)) ? $tree['items'] : [];
  }


  public static function getOptions(...$codes)
  {
    $codes[] = self::getOptionRoot();
    return self::getOptionsObject()->fullOptions(...$codes);
  }


  public static function getSimpleOptions(...$codes)
  {
    $codes[] = self::getOptionRoot();
    return self::getOptionsObject()->options(...$codes);
  }


  public static function getOptionsRef(...$codes)
  {
    $codes[] = self::getOptionRoot();
    return self::getOptionsObject()->fullOptionsRef(...$codes);
  }


  public static function getOption(...$codes)
  {
    $codes[] = self::getOptionRoot();
    return self::getOptionsObject()->option(...$codes);
  }


  public static function getOptionsTextValue(string|array $id, string $text = 'text', string $value = 'value', ...$additionalFields): array
  {
    if (is_string($id) && !Str::isUid($id)) {
      $id = [$id];
    }

    if (is_array($id)) {
      $id[] = self::getOptionRoot();
    }

    return $id ? self::getOptionsObject()->textValueOptions($id, $text, $value, ...$additionalFields) : [];
  }


  public static function getOptionsTextValueRef(string|array $id, string $text = 'text', string $value = 'value', ...$additionalFields): array
  {
    if (is_string($id) && !Str::isUid($id)) {
      $id = self::getOptionId($id);
    }

    return $id ? self::getOptionsObject()->textValueOptionsRef($id, $text, $value, ...$additionalFields) : [];
  }

}
