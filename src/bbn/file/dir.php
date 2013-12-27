<?php
/**
 * @package bbn\file
 */
namespace bbn\file;
/**
 * A class for dealing with directories (folders)
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category  Files ressources
 * @license   http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 */
class dir extends \bbn\obj 
{

	/**
	 * Checks if a file is in a directory
   * Accepts unlimited arguments
	 *
	 * Returns false or the first corresponding file
	 *
	 * @param string $dir
	 * @return array|false 
	 */
	public static function has_file($dir)
	{
    $as = func_get_args();
    array_shift($as);
    $files = self::get_files($dir);
    foreach ( $files as $f ){
      foreach ( $as as $a ){
        if ( basename($f) === $a ){
          return $a;
        }
      }
    }
		return false;
	}

	/**
	 * Returns an array of directories in a directory.
	 *
	 * It will return the full path ie including the original directory's path.
	 *
	 * @param string $dir
	 * @return array|false 
	 */
	public static function get_dirs($dir)
	{
		if ( is_string($dir) && is_dir($dir) )
		{
			$dirs = array();
			$fs = scandir($dir);
			foreach ( $fs as $f )
			{
				if ( $f !== '.' && $f !== '..' && is_dir($dir.'/'.$f) )
					array_push($dirs,$dir.'/'.$f);
			}
			return $dirs;
		}
		return false;
	}

	/**
	 * Returns an array of files in a directory.
	 *
	 * It returns the full path ie including the original directory's path.
	 * If including_dirs is set to true it will also return the folders included in the path.
	 *
	 * @param string $dir
	 * @param bool $including_dirs
	 * @return array|false 
	 */
	public static function get_files($dir, $including_dirs=false)
	{
		if ( is_string($dir) && is_dir($dir) )
		{
			if ( substr($dir,-1) === '/' )
				$dir = substr($dir,0,-1);
			$files = array();
			$fs = scandir($dir);
			foreach ( $fs as $f )
			{
				if ( $f !== '.' && $f !== '..' )
				{
					if ( is_file($dir.'/'.$f) )
						array_push($files,$dir.'/'.$f);
					else if ( $including_dirs )
						array_push($files,$dir.'/'.$f);
				}
			}
			return $files;
		}
		return false;
	}

	/**
	 * Deletes all the content from a directory
	 *
	 * If the $full param is set to true, it will also delete the directory itself
	 *
	 * @param string $dir
	 * @param bool $full
	 * @return bool 
	 */
	public static function delete($dir, $full=1)
	{
		if ( is_dir($dir) )
		{
			$files = scandir($dir);
			foreach ( $files as $file ) 
			{
				if ( $file != "." && $file != ".." ) 
				{
					if ( is_dir($dir."/".$file) )
						\bbn\file\dir::delete($dir."/".$file);
					else
						unlink($dir."/".$file);
				}
			}
			if ( $full === 1 )
				rmdir($dir);
			return true;
		}
		return false;
	}

}
?>