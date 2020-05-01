<?php

namespace bbn\models\cls;
use bbn;

abstract class rest extends bbn\models\cls\basic{

  use bbn\models\tts\cache;
  /**
   * the session
   *
   * @var [array]
   */

  protected $cfg;
  private $info;
  protected $_authenticated = false;

  /**
   * instantiate the class infolegale
   *
   */
  public function __construct($cfg = null)
  {
    $this->cache_init();
    $this->cfg = $cfg;
    if ($this->cfg['pass']) {
      $this->authenticate();
    }
    else {
      $this->_authenticated = true;
    }
  }

  public function check(): bool
  {
    return $this->_authenticated;
  }

  protected function authenticate()
  {
    return false;
  }

  public function build_url($url, $alt_param)
  {
    return $this->cfg['url'] ?? null;
  }

}