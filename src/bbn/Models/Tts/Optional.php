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
  protected static function optionalInit(array $path = null)
  {
    if (!self::$optional_is_init) {
      $opt = Option::getInstance();
      if (!$opt) {
        throw new Exception(X::_("There is no options object as needed by").' '.__CLASS__);
      }

      if (!\defined("BBN_APPUI")) {
        \define('BBN_APPUI', $opt->fromCode('appui', 'plugins'));
      }

      if (!$path) {
        if (!BBN_APPUI) {
          throw new Exception('Impossible to find the option appui for '.__CLASS__, 'errors');
        }

        $tmp                   = explode('\\', __CLASS__);
        $cls                   = strtolower(end($tmp));
        $path                  = [$cls, 'appui'];
      }

      self::$option_root_id = $opt->fromCode(...$path);
      if (!self::$option_root_id) {
        if (!$cls) {
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
  protected static function initOptionalGlobal(Option $opt, array $path = null)
  {
    if (!self::$optional_is_init) {
      if (!\defined("BBN_APPUI")) {
        \define('BBN_APPUI', $opt->fromCode('appui', 'plugins'));
      }

      if (!$path) {
        if (!BBN_APPUI) {
          X::log('Impossible to find the option appui for '.__CLASS__, 'errors');
          return;
        }

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
  protected function initOptional(array $path = null)
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
  public static function getOptionId()
  {
    return self::getOptionsObject()->fromCode(...self::_treat_args(func_get_args()));
  }


  /**
   * Undocumented function
   *
   * @todo Check it, it doesn't seem ok
   * @return array
   */
  public static function getOptionsIds(): array
  {
    return array_flip(
      array_filter(
        self::getOptionsObject()->getCodes(...self::_treat_args(func_get_args())),
        function ($a) {
          return $a !== null;
        }
      )
    );
  }


  public static function getOptionsTree()
  {
    return ($tree = self::getOptionsObject()->fullTree(...self::_treat_args(func_get_args()))) ? $tree['items'] : [];
  }


  public static function getOptionsTreeRef()
  {
    return ($tree = self::getOptionsObject()->fullTreeRef(...self::_treat_args(func_get_args()))) ? $tree['items'] : [];
  }


  public static function getOptions()
  {
    return self::getOptionsObject()->fullOptions(...self::_treat_args(func_get_args()));
  }


  public static function getOptionsRef()
  {
    return self::getOptionsObject()->fullOptionsRef(...self::_treat_args(func_get_args()));
  }


  public static function getOption()
  {
    return self::getOptionsObject()->option(...self::_treat_args(func_get_args()));
  }


  public static function getOptionsTextValue()
  {
    return ($id = self::getOptionId(...func_get_args())) ? self::getOptionsObject()->textValueOptions($id) : [];
  }


  public static function getOptionsTextValueRef()
  {
    return ($id = self::getOptionId(...func_get_args())) ? self::getOptionsObject()->textValueOptionsRef($id) : [];
  }



  protected static function _treat_args(array $args, $appui = false): array
  {
    $hasUid = Str::isUid(end($args));
    if (count($args) && !$hasUid) {
      self::optionalInit();
      if (!in_array($args[0], ['permissions', 'options', 'plugins'])) {
        $args[] = 'options';
      }
    }

    if (!$hasUid) {
      $args[] = self::$option_root_id;
    }

    return $args;
  }


}
