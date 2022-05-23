<?php

/**
 * @package appui
 */

namespace bbn\Appui;

use bbn;
use bbn\X;
use bbn\Str;
use Exception;

class Dashboard
{

  use bbn\Models\Tts\Optional;

  /** @var \bbn\Appui\Option */
  protected $opt;

  /** @var \bbn\User */
  protected $user;

  /** @var \bbn\User\Permissions */
  protected $perm;

  /** @var \bbn\User\Preferences */
  protected $pref;

  /** @var \bbn\Db */
  protected $db;



  /**
   * dashboard constructor.
   */
  public function __construct(string $id = '')
  {
    $this->opt      = bbn\Appui\Option::getInstance();
    $this->user     = bbn\User::getInstance();
    $this->perm     = bbn\User\Permissions::getInstance();
    $this->pref     = bbn\User\Preferences::getInstance();
    $this->db       = bbn\Db::getInstance();
    $this->cfgPref  = $this->pref->getClassCfg();
    $this->archOpt  = $this->opt->getClassCfg()['arch']['options'];
    $this->archPref = $this->cfgPref['arch']['user_options'];
    $this->archBits = $this->cfgPref['arch']['user_options_bits'];
    self::optionalInit();
    $this->widgetFields       = \array_merge($this->nativeWidgetFields, \array_values($this->archBits));
    $this->nativeWidgetFields = \array_merge($this->nativeWidgetFields, \array_values($this->archOpt));
    $this->idList             = $this->getOptionId('list');
    if (!Str::isUid($this->idList)) {
      throw new \Exception(_("Unable to load the option 'list'"));
    }

    $this->idWidgets = $this->getOptionId('widgets');
    if (!Str::isUid($this->idWidgets)) {
      throw new \Exception(_("Unable to load the option 'widgets'"));
    }

    if (!empty($id)) {
      $this->setCurrent($id);
    }
  }


}
