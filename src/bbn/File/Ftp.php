<?php
/**
 * @package file
 */
namespace bbn\File;
use bbn;
use boo\test;

/**
 * File Transfer Protocol Class
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Files ressources
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @version 0.2r89
 */
class Ftp extends bbn\Models\Cls\Basic
{
  public const TIMEOUT = 20;

	/**
	 * @var string
	 */
	private $dir = '';

	/**
	 * @var mixed
	 */
	private $host;

	/**
	 * @var mixed
	 */
	private $login;

	/**
	 * @var mixed
	 */
	private $pass;

	/**
	 * @var mixed
	 */
	private $cn;

	/**
	 * @var mixed
	 */
	public $error;


	/**
	 * @return void 
	 */
	public function __construct($cfg=array())
	{
		if ( \is_array($cfg) )
		{
			$this->dir = $cfg['dir'] ?? '';
			if ( isset($cfg['host']) ){
				$host = $cfg['host'];
			}
			else if ( \defined('BBN_FTP_HOST') ){
				$host = BBN_FTP_HOST;
			}
			if ( isset($cfg['user']) ){
				$login = $cfg['user'];
			}
			else if ( \defined('BBN_FTP_USER') ){
				$login = BBN_FTP_LOGIN;
			}
			if ( isset($cfg['pass']) ){
				// $this->pass = bbn\Util\Enc::decrypt($cfg['pass']);
        $pass = $cfg['pass'];
			}
			else if ( \defined('BBN_FTP_PASS') ){
				$pass = bbn\Util\Enc::decrypt(BBN_FTP_PASS);
			}
			if (
			  isset($this->dir, $host, $login, $pass) &&
				($this->dir = $this->checkPath($this->dir))
      ){
			  if ( empty($cfg['ssl']) ){
          $this->cn = ftp_connect($host, $cfg['port'] ?? 21, $cfg['timeout'] ?? self::TIMEOUT);
        }
        else{
          $this->cn = ftp_ssl_connect($host, $cfg['port'] ?? 21, $cfg['timeout'] ?? self::TIMEOUT);
        }
        if ( $this->cn ){
          if ( ftp_login($this->cn, $login, $pass) )
          {
            if ( @ftp_chdir($this->cn, $this->dir) )
            {
              ftp_pasv($this->cn,TRUE);
              return;
            }
            else {
              $this->error = \defined('BBN_IMPOSSIBLE_TO_FIND_THE_SPECIFIED_FOLDER') ?
                BBN_IMPOSSIBLE_TO_FIND_THE_SPECIFIED_FOLDER : 'Impossible to find the specified folder';
            }
          }
          else {
            $this->cn = false;
            $this->error = \defined('BBN_IMPOSSIBLE_TO_CONNECT_TO_THE_FTP_HOST') ?
              BBN_IMPOSSIBLE_TO_CONNECT_TO_THE_FTP_HOST : 'Impossible to connect to the FTP host';
          }
        }
        else {
          $this->error = \defined('BBN_IMPOSSIBLE_TO_FIND_THE_FTP_HOST') ?
            BBN_IMPOSSIBLE_TO_FIND_THE_FTP_HOST : 'Unable to find the FTP host';
        }
      }
		}
	}

  /**
   * @param string $path
   * @param bool $fast
   * @return array|null
   */
	public function listFiles($path='.', bool $fast = false):? array
	{
    $res = [];
		if ( $this->cn &&
            ftp_chdir($this->cn, $path) &&
            ($files = ftp_nlist($this->cn, $path)) ){
      foreach ( $files as $file )
      {
        $ele = [
          'name' => $file,
          'basename' => basename($file),
        ];
        if ( @ftp_chdir($this->cn, $path.'/'.$ele['basename']) ){
          $num = ftp_nlist($this->cn, '.');
          $ele['num'] = \count($num);
          $ele['type'] = 'dir';
          @ftp_cdup($this->cn);
        }
        else{
          $ele['type'] = bbn\Str::fileExt($file);
        }
        $res[] = $ele;
      }
    }
    return $res;
	}

	/**
	 * Scans all the content from a directory, including the subdirectories
	 *
   * <code>
   * bbn\File\Dir::scan("/home/me");
   * bbn\File\Dir::delete("C:\Documents\Test");
   * </code>
   * 
	 * @param string $dir The directory path.
	 * @param string $type The type of item to return ('file', 'dir', default is both)
   * 
	 * @return array
	 */
	public function scan(string $dir, string $type = null, &$res = [], int $timeout = 0): array
  {
    $res = [];
    if ( $dirs = $this->listFiles($dir) ){
      foreach ( $dirs as $d ){
        if ( $type &&
                (strpos($type, 'file') === 0) &&
                !isset($d['num']) ){
          $res[] = $d['name'];
        }
        else if ( $type &&
                ((strpos($type, 'dir') === 0) || (strpos($type, 'fold') === 0)) &&
                isset($d['num']) ){
          $res[] = $d['name'];
        }
        else{
          $res[] = $d['name'];
        }
        if ( isset($d['num']) ){
          $this->scan($d['name'], $type, $res);
        }
      }
    }
    return $res;
	}

	/**
	 * @return void 
	 */
	public function checkPath($path)
	{
    if ( empty($path) ){
      return '/';
    }
		$new = explode('../',$path);
		$nnew = \count($new);
		if ( $nnew > 1 )
		{
			$cur = explode('/',$this->dir);
			$ncur = \count($cur);
			if ( $cur[$ncur-1] == '' )
			{
				array_pop($cur);
				$ncur--;
			}
			for ( $i = 1; $nnew < $i; $i++ )
			{
				if ( $new[$i-1] == '' )
				{
					$ncur--;
					if ( $ncur == 1 )
						return false;
					else
						array_pop($cur);
				}
				else
				{
					$add = $new[$i-1];
					if ( substr($add,-1) != '/' )
						$add .= '/';
					break;
				}
			}
			$new_path = implode('/',$cur).'/';
			if ( isset($add) )
				$new_path .= $add;
			return $new_path;
		}
		else if ( strpos($path,'/') === 0 )
			return $path;
		else if ( $path == '.' )
			return $this->dir;
		else if ( \strlen($path) > 0 )
		{
			if ( substr($path,-1) != '/' )
				$path .= '/';
			$path = $this->dir.$path;
			if ( substr($path,0,1) != '/' )
				$path = '/'.$path;
			return $path;
		}
	}

	/**
	 * @return false|string
	 */
	public function checkFilePath($file){
		$slash = strrpos($file, '/');
		if ( ($slash !== false) &&
                ($dir = $this->checkPath(substr($file, 0, $slash))) ){
      return $dir.substr($file, $slash);
		}
		else if ( $slash === false ){
			return $this->dir.$file;
    }
		return false;
	}

	/**
	 * @return boolean
	 */
	public function cdDir($dir){
		if ( $dir = $this->checkPath($dir) )
		{
			if ( @ftp_chdir($this->cn, $dir) )
			{
				$this->dir = $dir;
				return true;
			}
		}
		return false;
	}

	/**
	 * @return void 
	 */
	public function checkDir($dir, $create=0){
		if ( $dir = $this->checkPath($dir) )
		{
			$path = $this->dir;
			if ( $this->cdDir($dir) ){
				$this->cdDir($path);
				$this->error = \defined('BBN_DIRECTORY_EXISTS') ?
					BBN_DIRECTORY_EXISTS : 'The directory exists';
				return $this->error;
			}
			else if ( $create == 1 && $this->mkDir($dir) ){
				$this->error = \defined('BBN_DIRECTORY_CREATED') ?
					BBN_DIRECTORY_CREATED : 'The directory has been created';
				return $this->error;
			}
		}
		return false;
	}

	/**
	 * @return false|string 
	 */
	public function mkDir($dir){
		if ( $dir = $this->checkPath($dir) ){
			if ( $this->checkDir($dir) ){
				$this->error = \defined('BBN_DIRECTORY_EXISTS') ?
					BBN_DIRECTORY_EXISTS : 'The directory exists';
				return $this->error;
			}
			else if ( ftp_mkdir($this->cn, $dir) ){
				$this->error = \defined('BBN_DIRECTORY_CREATED') ?
					BBN_DIRECTORY_CREATED : 'The directory has been created';
				return $this->error;
			}
		}
		return false;
	}

	/**
   * Deletes a file from the server
   * 
	 * @return boolean
	 */
	public function delete($item){
		self::log('delete:'.$item);
		if ( $this->checkFilePath($item) &&
            ftp_delete($this->cn,$item) ){
      return true;
		}
		return false;
	}

	/**
   * Puts a file on the server
   * 
	 * @return boolean
	 */
	public function put($src, $dest){
		if ( file_exists($src) &&
            ($dest = $this->checkFilePath($dest)) &&
            ftp_put($this->cn,$dest,$src,FTP_BINARY) ){
      return true;
		}
		return false;
	}

	/**
   * Gets a file from the server
   * 
	 * @return boolean
	 */
	public function get($src, $dest){
		if ( $src = $this->checkFilePath($src) &&
            ftp_get($this->cn, $dest, $src, FTP_BINARY) ){
      return true;
		}
		return false;
	}

	/**
	 * @return void 
	 */
	public function close(){
		ftp_close($this->cn);
	}

  /**
   * Moves or renames a file on the server
   *
   * @param string $old The old file full path
   * @param string $new The new file full path
   * @return bool
   */
  public function move(string $old, string $new){
    if (
      !empty($old) &&
      !empty($new) &&
      ($old = $this->checkFilePath($old)) &&
      ftp_rename($this->cn, $old, $new)
    ){
      return true;
    }
    return false;
  }

  /**
   * Checks if a file is present in a folder on the server
   *
   * @param string $path The file or dir relative or full path
   * @return bool
   */
  public function exists(string $path){
    if ( !empty($path) ){
      $dir = dirname($path);
      $ext = \bbn\Str::fileExt($path);
      $file = basename($path);
      if ( \is_array($files = $this->listFiles($dir)) ){
        foreach ( $files as $f ){
          if ( $f['basename'] === $file ){
            return true;
          }
        }
      }
    }
    return false;
  }
}
