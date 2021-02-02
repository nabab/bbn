<?php

namespace bbn\Models\Cls;
use bbn;

abstract class Rest extends bbn\Models\Cls\Basic{

  use bbn\Models\Tts\Cache;
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
    $this->cacheInit();
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

  public function buildUrl($url, $alt_param)
  {
    return $this->cfg['url'] ?? null;
  }

}