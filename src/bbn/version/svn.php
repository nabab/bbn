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
	
	public function __construct($url,$user=false,$pass=false)
	{
		$this->url = $url;
		$this->user = $user;
		$this->pass = $pass;
		if ( function_exists('svn_export') ){
			$this->has_svn = 1;
		}
	}
	public function export($to,$rev='')
	{
		if ( is_dir($to) ){
			if ( $this->has_svn ){
				svn_auth_set_parameter(PHP_SVN_AUTH_PARAM_IGNORE_SSL_VERIFY_ERRORS, true);
				svn_auth_set_parameter(SVN_AUTH_PARAM_NON_INTERACTIVE, true);
				svn_auth_set_parameter(SVN_AUTH_PARAM_NO_AUTH_CACHE, true);
				var_dump(svn_export($this->url, $to, false));
			}
			else{
				var_dump(system("svn export $this->url $to --force"));
			}
		}
	}
}
?>