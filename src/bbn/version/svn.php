<?php
/**
 * @package bbn\version
 */
namespace bbn\version;
/**
 * Class for Subversion usage. It will use the PHP SVN functions if available, and will try to use SVN commands through <em>system</em> otherwise.
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Utilities
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 */
class svn 
{
	public
		$url,
		$has_svn = false;
	
	private $user, $pass;
	
  private function auth(){
    svn_auth_set_parameter(PHP_SVN_AUTH_PARAM_IGNORE_SSL_VERIFY_ERRORS, true);
    svn_auth_set_parameter(SVN_AUTH_PARAM_NON_INTERACTIVE, true);
    svn_auth_set_parameter(SVN_AUTH_PARAM_NO_AUTH_CACHE, true);
    if ( $this->user ){
      svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_USERNAME, $this->user);
    }
    if ( $this->pass ){
      svn_auth_set_parameter(SVN_AUTH_PARAM_DEFAULT_PASSWORD, $this->pass);
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
    return $st;
  }
  
  private function parseCMD($st){
    $tmp = explode("\n", $st);
    $res = [];
    foreach ( $tmp as $t ){
      $i = strpos($t, ':');
      if ( $i > 0 ){
        $res[\bbn\str\text::change_case(\bbn\str\text::encode_filename(substr($t, 0, $i)), 'lower')] = trim(substr($t, $i+1));
      }
    }
    return $res;
  }
  
	public function __construct($url, $user=false, $pass=false)
	{
		$this->url = $url;
		$this->user = $user;
		$this->pass = $pass;
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
  
  public function version($path='.')
  {
    if ( $this->has_svn ){
      $this->auth();
      return svn_status($path, SVN_NON_RECURSIVE|SVN_ALL);
    }
    else{
      ob_start();
      system("svn info ".$this->args());
      $st = ob_get_contents();
      ob_end_clean();
      return $this->parseCMD($st);
    }
  }
}
?>