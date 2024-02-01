<?php
/**
 * @package version
 */
namespace bbn\Version;

use bbn\Str;
/**
 * Class for Subversion usage. It will use the PHP SVN functions if available, and will try to use SVN commands through <em>system</em> otherwise.
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Utilities
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.2r89
 */
class Svn 
{
	public
		$url,
		$has_svn = false;
	
	private
    $hash,
    $auth = false,
    $user,
    $pass;

  private static $current = '';

  private static function setCurrent($path){
    self::$current = $path;
  }
	
  private function auth(){
    if ( !$this->auth || (self::$current !== $this->hash) ){
      svn_auth_set_parameter(PHP_SVN_AUTH_PARAM_IGNORE_SSL_VERIFY_ERRORS, true);
      svn_auth_set_parameter(SVN_AUTH_PARAM_NON_INTERACTIVE, true);
      svn_auth_set_parameter(SVN_AUTH_PARAM_NO_AUTH_CACHE, true);
      if ($this->user){
        svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_USERNAME, $this->user);
      }
      if ($this->pass){
        svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_PASSWORD, $this->pass);
      }
      self::setCurrent($this->hash);
    }
  }
  
  private function args(){
    $st = $this->url;
    if ( $this->user ){
      $st .= ' --username '.$this->user;
    }
    if ( $this->pass ){
      $st .= ' --password '.$this->pass;
    }
    return $st.' --xml 2>&1';
  }
  
  private function parseCMD($st){
    if ( !mb_detect_encoding($st) ){
      $st = Str::toUtf8($st);
    }

    $tmp = explode("\n", $st);
    $res = [];
    foreach ( $tmp as $t ){
      $i = strpos($t, ':');
      if ( $i > 0 ){
        $res[Str::changeCase(Str::encodeFilename(substr($t, 0, $i)), 'lower')] = trim(substr($t, $i+1));
      }
    }
    return $res;
  }
  
	public function __construct($url, $user=false, $pass=false)
	{
		$this->url = $url;
		$this->user = $user;
		$this->pass = $pass;
    $this->hash = md5($url.(string)$user.(string)$pass);
		if ( function_exists('svn_export') ){
			$this->has_svn = 1;
		}
	}
  
	public function export($to, $rev='')
	{
		if ( is_dir($to) ){
			if ( $this->has_svn ){
        $this->auth();
				return svn_export($this->url, $to, false);
			}
			else{
        ob_start();
				system("svn export ".$this->args()." $to --force");
        $st = ob_get_contents();
        ob_end_clean();
        return $st;
			}
		}
	}
  
  public function info($path='/')
  {
    if ( $this->has_svn ){
      $this->auth();
      return svn_status($this->url.$path, SVN_NON_RECURSIVE|SVN_ALL);
    }
    else{
      ob_start();
      system("svn info ".$this->args());
      $st = ob_get_contents();
      ob_end_clean();
      bbn\X::hdump($st);
      return $this->parseCMD($st);
    }
  }
  
  public function last($path='/')
  {
    if ( $this->has_svn ){
      $this->auth();
      return svn_status($this->url.$path, SVN_NON_RECURSIVE|SVN_ALL);
    }
    else{
      ob_start();
      header('Content-Type: text/plain; charset=UTF-8');
      print(shell_exec("svn info --xml ".$this->args()));
      $st = ob_get_contents();
      ob_end_clean();
      $log = new \SimpleXMLElement($st);
      if ( isset($log->entry['revision']) ){
        return (int)$log->entry['revision'];
      }
    }
    
  }
  
  public function log($path='/', $num = 5)
  {
    if ( $this->has_svn ){
      $this->auth();
      return svn_log($this->url.$path);
      //return svn_status($path, SVN_NON_RECURSIVE|SVN_ALL);
    }
    else{
      if ( !$num ){
        $num = $this->last($this->url.$path);
      }
      ob_start();
      header('Content-Type: text/plain; charset=UTF-8');
      print(shell_exec("svn log -l $num ".$this->args()));
      $st = ob_get_contents();
      ob_end_clean();
      $log = new \SimpleXMLElement($st);
      $r = [];
      //bbn\X::hdump($st);
      foreach ( $log->logentry as $l ){
        $r[(int)$l['revision']] = [
          'author' => (string)$l->author,
          'date' => date('Y-m-d H:i:s', strtotime($l->date)),
          'msg' => (string)$l->msg
        ];
      }
      return $r;
    }
  }
}
?>