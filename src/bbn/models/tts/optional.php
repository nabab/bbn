<?php
/**
 * Implements functions for retrieving class-specific options
 *
 * User: BBN
 * Date: 05/11/2016
 * Time: 02:53
 */

namespace bbn\models\tts;

use bbn;


trait optional
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
   * @var string The ID of the appui option
   */
  protected static $option_appui_id;

  protected $options;

  /**
   * Returns the option's root ID for the current class based on {@link $option_root_code}
   *
   * @return false|int
   */
  protected static function optional_init(array $path = null){
    if (!self::$optional_is_init) {
      $opt = bbn\appui\options::get_instance();
      if (!$opt) {
        throw new \Exception(_("There is no options object as needed by").' '.__CLASS__);
      }
      if ( !\defined("BBN_APPUI") ){
        \define('BBN_APPUI', $opt->from_code('appui'));
      }
      if ( !\defined("BBN_APPUI_ROOT") ){
        \define('BBN_APPUI_ROOT', $opt->from_root_code('appui'));
      }
      if (!$path) {
        if ( !BBN_APPUI || !BBN_APPUI_ROOT ){
          \bbn\x::log('Impossible to find the option appui for '.__CLASS__, 'errors');
          return;
        }
        $tmp = explode('\\', __CLASS__);
        $cls = end($tmp);
        $path = [$cls, BBN_APPUI];
        self::$option_appui_id = $opt->from_code($cls, BBN_APPUI_ROOT);
      }
      else{
        self::$option_appui_id = null;
      }
      self::$option_root_id = $opt->from_code(...$path);
      //if ( !self::$option_appui_id || !self::$option_root_id ){
      if ( !self::$option_root_id ){
        \bbn\x::log("Impossible to find the option $cls for ".__CLASS__, 'errors');
        return;
      }
      self::$optional_is_init = true;
    }
  }

  /**
   * Sets only once all the constants used by the class.
   *
   * @param bbn\appui\options $opt
   * @param array $path
   * @return void
   */
  protected static function init_optional_global(bbn\appui\options $opt, array $path = null)
  {
    if (!self::$optional_is_init) {
      if ( !\defined("BBN_APPUI") ){
        \define('BBN_APPUI', $opt->from_code('appui'));
      }
      if ( !\defined("BBN_APPUI_ROOT") ){
        \define('BBN_APPUI_ROOT', $opt->from_root_code('appui'));
      }
      if (!$path) {
        if ( !BBN_APPUI || !BBN_APPUI_ROOT ){
          \bbn\x::log('Impossible to find the option appui for '.__CLASS__, 'errors');
          return;
        }
        $tmp = explode('\\', __CLASS__);
        $cls = end($tmp);
        $path = [$cls, BBN_APPUI];
        self::$option_appui_id = $opt->from_code($cls, BBN_APPUI_ROOT);
      }
      else{
        self::$option_appui_id = null;
      }
      self::$option_root_id = $opt->from_code(...$path);
      //if ( !self::$option_appui_id || !self::$option_root_id ){
      if ( !self::$option_root_id ){
        \bbn\x::log("Impossible to find the option $cls for ".__CLASS__, 'errors');
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
  protected function init_optional(array $path = null)
  {
    $this->options = bbn\appui\options::get_instance();
    if (!$this->options) {
      throw new \Exception(_("There is no options object as needed by").' '.__CLASS__);
    }
    self::init_optional_global($this->options, $path);
  }

  public static function get_option_root(){
    self::optional_init();
    return self::$option_root_id;
  }

  public static function get_appui_root(){
    self::optional_init();
    return self::$option_appui_id;
  }

  public static function get_options_object(): bbn\appui\options
  {
    $o = bbn\appui\options::get_instance();
    if (!$o) {
      throw new \Exception(_("Impossible to get the options object from class").' '.__CLASS__);
    }
    return $o;
  }

  /**
   * Returns The option's ID of a category, i.e. direct children of option's root
   *
   * @param string $code
   * @return int|false
   */
  public static function get_option_id(){
    return self::get_options_object()->from_code(...self::_treat_args(func_get_args()));
  }

  public static function get_options_ids(){
    return array_flip(array_filter(self::get_options_object()->get_codes(...self::_treat_args(func_get_args())), function($a){
      return $a !== null;
    }));
  }

  public static function get_options_tree()
  {
    return ($tree = self::get_options_object()->full_tree(...self::_treat_args(func_get_args()))) ?
      $tree['items'] : [];
  }

  public static function get_options()
  {
    return self::get_options_object()->full_options(...self::_treat_args(func_get_args()));
  }

  public static function get_option(){
    return self::get_options_object()->option(...self::_treat_args(func_get_args()));
  }

  public static function get_options_text_value(){
    return ($id = self::get_option_id(...func_get_args())) ?
      self::get_options_object()->text_value_options($id): [];
  }

  /**
   * Returns The option's ID of a category, i.e. direct children of option's root
   *
   * @param string $code
   * @return int|false
   */
  public static function get_appui_option_id(): ?string
  {
    return self::get_options_object()->from_code(...self::_treat_args(func_get_args(), true));
  }

  public static function get_appui_options_ids(): array
  {
    return array_flip(
      array_filter(
        self::get_options_object()->get_codes(
          ...self::_treat_args(func_get_args(), true)
        ),
        function($a){
          return $a !== null;
        }
      )
    );
  }

  public static function get_appui_options_tree(): array
  {
    return ($tree = self::get_options_object()->full_tree(...self::_treat_args(func_get_args(), true)) ) ?
      $tree['items'] : [];
  }

  public static function get_appui_options(): ?array
  {
    return self::get_options_object()->full_options(...self::_treat_args(func_get_args(), true));
  }

  public static function get_appui_option(): ?array
  {
    return self::get_options_object()->option(...self::_treat_args(func_get_args(), true));
  }

  public static function get_appui_options_text_value()
  {
    return ($id = self::get_appui_option_id(...func_get_args())) ?
    self::get_options_object()->text_value_options($id) : [];
  }

  protected static function _treat_args(array $args, $appui = false): array
  {
    if ( (count($args) > 1) || !\bbn\str::is_uid($args[0] ?? null) ){
      self::optional_init();
      $args[] = $appui ? self::$option_appui_id : self::$option_root_id;
    }
    return $args;
  }

}
