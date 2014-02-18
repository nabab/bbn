<?php
/**
 * @package bbn\file
 */
namespace bbn\file;
/**
 * A class for dealing with files
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Files ressources
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 */
class file extends \bbn\obj 
{
	/**
	 * @var int
	 */
	protected
    $size=0,
	/**
	 * @var mixed
	 */
    $ext;

	/**
	 * @var mixed
	 */
	protected $hash;

	/**
	 * @var mixed
	 */
	public $path;

	/**
	 * @var mixed
	 */
	public $name;

	/**
	 * @var mixed
	 */
	public $file;

	/**
	 * @var mixed
	 */
	public $title;

	/**
	 * @var int
	 */
	public $uploaded=0;


	/**
   * @todo Fairew la doc!!
   * @param
	 * @return void 
	 */
	public function __construct($file)
	{
		if ( is_array($file) )
		{
			if ( isset($file['name'],$file['tmp_name']) )
			{
				$this->path = '';
				$this->name = $file['name'];
				$this->size = $file['size'];
				$file = $file['tmp_name'];
			}
		}
		else if ( is_string($file) )
		{
			$file = trim($file);
			if ( strrpos($file,'/') !== false )
			{
				/* The -2 in strrpos means that if there is a final /, it will be kept in the file name */
				$this->name = substr($file,strrpos($file,'/',-2)+1);
				$this->path = substr($file,0,-strlen($this->name));
				if ( substr($this->path,0,2) == '//' )
					$this->path = 'http://'.substr($this->path,2);
			}
			else
			{
				$this->name = $file;
				$this->path = './';
			}
		}
		$this->get_extension();
		if ( is_string($file) && is_file($file) ){
      $this->file = $file;
    }
		else{
			$this->make();
    }
		return $this;
	}

	/**
	 * @return void 
	 */
	public function get_size()
	{
		if ( $this->file && $this->size === 0 )
			$this->size = filesize($this->file);
		return $this->size;
	}

	/**
	 * @return void 
	 */
	public function get_extension()
	{
		if ( $this->name )
		{
			if ( !isset($this->ext) )
			{
				if ( strpos($this->name,'.') !== false )
				{
					$p = strrpos($this->name,'.');
					$this->ext = strtolower(substr($this->name,$p+1));
					$this->title = substr($this->name,0,-(strlen($this->ext)+1));
				}
				else
				{
					$this->ext = '';
					$this->title = substr($this->name,-1) == '/' ? substr($this->name,0,-1) : $this->name;
				}
			}
			return $this->ext;
		}
		return false;
	}

	/**
	 * @return void 
	 */
	protected function make()
	{
		if ( !$this->file && strpos($this->path,'http://') === 0 ){
			$d = getcwd();
			chdir(__DIR__);
			chdir('../tmp');
			$f = tempnam('.','image');
			try{
				$c = file_get_contents($this->path.$this->name);
				if ( file_put_contents($f,$c) ){
					if ( substr($this->name,-1) == '/' ){
						$this->name = substr($this->name,0,-1);
          }
					chmod($f,0777);
					$this->file = $f;
					$this->path = getcwd();
				}
				else{
					$this->error = 'Impossible to get the file '.$this->path.$this->name;
        }
			}
			catch ( Error $e )
				{ $this->error = 'Impossible to get the file '.$this->path.$this->name; }
			chdir($d);
		}
		return $this;
	}

	/**
	 * @return void 
	 */
	public function download()
	{
		if ( $this->file ){
      if ( !$this->size ){
        $this->get_size();
      }
			if ( $this->size && ($handle = fopen($this->file, "r")) ){
        header("Content-type: application/octet-stream");
				header('Content-Disposition: attachment; filename="'.$this->name.'"');
        while ( !feof($handle) ){
          echo fread($handle, 65536);
        }
        fclose($handle);
			}
      else{
        die("Impossible to read the file ".$this->name);
      }
		}
		return $this;
	}

	/**
	 * @return void 
	 */
	public function get_hash()
	{
		if ( $this->file ){
			return md5_file($this->file);
    }
		return '';
	}

	/**
	 * @return void 
	 */
	public function delete()
	{
		if ( $this->file ){
			unlink($this->file);
    }
		$this->file = false;
		return $this;
	}

	/**
	 * @return void 
	 */
	public function save($dest='./')
	{
		$new_name = false;
		if ( substr($dest,-1) === '/' ){
			if ( is_dir($dest) ){
				$new_name = 0;
      }
		}
		else if ( is_dir($dest) ){
			$dest .= '/';
			$new_name = 0;
		}
		else if ( is_dir(substr($dest,0,strrpos($dest,'/'))) ){
			$new_name = 1;
    }
		if ( $new_name !== false ){
			if ( $new_name === 0 ){
				$dest .= $this->name;
      }
			if ( isset($_FILES) ){
				move_uploaded_file($this->file,$dest);
				$this->file = $dest;
				$this->uploaded = 1;
			}
			else{
				copy($this->file, $dest);
      }
		}
		return $this;
	}

}
?>