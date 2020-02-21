<?php
namespace bbn\api;

class rest extends \bbn\models\cls\basic{
  /**
   * the session
   *
   * @var [array]
   */
  private $session;
  protected $cfg;
  private $info;

  /**
   * instantiate the class infolegale
   *
   * @param [type] $session ($session->get('infolegale'))
   */
  public function __construct($cfg = null, $force = false)
  {
    $res = \bbn\x::curl($this->cfg['url'].'login_json', [
      '_username' => $this->cfg['user'],
      '_password' => $this->cfg['pass']
    ]);

    if ( $res && ($json = json_decode($res, true)) && isset($json['token']) ){
      $this->info = $json;
    }

    if ( !$this->info ){
      throw new \Exception(_("Pas de connexion!"));
    }
  }

  protected function build_url($url, $country)
  {
    return $this->cfg['url'].'companies/'.$country.'/'.$url,
  }
  
  private function _get_auth($meth = 'get'){
    if ( $this->check() && $this->info['token'] ){
      return [
        $meth => 1,
        'HTTPHEADER' => [
          'Authorization: Bearer '.$this->info['token']
        ]
      ];
    }
    return false;
  }

  private function _get_url($url, $country, $post = []){
    if (
      $this->check() &&
      ($res = \bbn\x::curl(
        $this->build_url($url, $country),
        $post,
        $this->_get_auth(count($post) ? 'post' : 'get')
      ))
    ){
      return strpos($res, '{') === 0 ? json_decode($res, true) : $res;
    }
    return null;
  }
}