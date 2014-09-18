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
	 * Replaces backslash with slash and deletes whitespace from the beginning and end of a directory path.
	 *
   * <code>
   * \bbn\file\dir::clean("C:\Documents\Test"); //Returns "C:/Documents/Test"
   * \bbn\file\dir::clean(" ..\Documents\Test "); //Returns "../Documents/Test"
   * </code>
   * 
	 * @param string $dir The directory path.
   * 
	 * @return string 
	 */
  public static function clean($dir){
    $new = trim(str_replace('\\', '/', $dir));
    if ( substr($new, -1) === '/' ){
      $new = substr($new, 0, -1);
    }
    return $new;
  }

	/**
	 * Checks if a file is in a directory.
   * Accepts unlimited arguments.
	 *
   * <code>
   * \bbn\file\dir::has_file("C:\Documents\Test", "test.txt");
   * </code>
   * 
	 * @param string $dir The directory path.
   * 
	 * @return boolean 
	 */
	public static function has_file($dir)
	{
    $dir = self::clean($dir);
    $as = func_get_args();
    array_shift($as);
    foreach ( $as as $a ){
      if ( !file_exists($dir.'/'.$a) ){
        return false;
      }
    }
		return 1;
	}

	/**
	 * If the directory starts with './' returns the path without './' else returns the complete path.
	 *
   * <code>
   * \bbn\file\dir::cur("C:\Documents\Test"); //Returns "C:\Documents\Test"
   * \bbn\file\dir::cur("./testdir"); //Returns "testdir"
   * </code>
   * 
	 * @param string $dir The directory path.
   * 
	 * @return string 
	 */
  public static function cur($dir)
  {
    return strpos($dir, './') === 0 ? substr($dir, 2) : $dir;
  }

	/**
	 * Returns an array of directories in a directory.
	 *
	 * It will return the full path ie including the original directory's path.
	 *
   * <code>
   * \bbn\file\dir::get_dirs("C:\Docs\Test");
   * //Returns ['C:/DocsTest/test1', 'C:/DocsTest/test2', 'C:/DocsTest/test3']
   * </code>
   * 
	 * @param string $dir The directory path.
   * 
	 * @return array|false 
	 */
	public static function get_dirs($dir)
	{
    $dir = self::clean($dir);
		if ( is_dir($dir) ){
			$dirs = [];
			$fs = scandir($dir);
			foreach ( $fs as $f ){
				if ( $f !== '.' && $f !== '..' && is_dir($dir.'/'.$f) ){
					array_push($dirs, self::cur($dir.'/').$f);
        }
			}
      \bbn\tools::sort($dirs);
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
   * <code>
   * \bbn\file\dir::get_files("C:\Docs\Test"); //Returns ['C:/DocsTest/file.txt', 'C:/DocsTest/file.doc']
   * \bbn\file\dir::get_files("C:\Docs\Test", 1); //Returns ['C:/DocsTest/test1', 'C:/DocsTest/test2', 'C:/DocsTest/file.txt', 'C:/DocsTest/file.doc']
   * </code>
   * 
	 * @param string $dir The directory path.
	 * @param boolean $including_dirs If set to true it will also return the folders included in the path.
   * 
	 * @return array|false 
	 */
	public static function get_files($dir, $including_dirs=false)
	{
    $dir = self::clean($dir);
		if ( is_dir($dir) ){
			$files = [];
			$fs = scandir($dir);
			foreach ( $fs as $f ){
				if ( $f !== '.' && $f !== '..' ){
					if ( $including_dirs ){
						array_push($files, self::cur($dir.'/').$f);
          }
					else if ( is_file($dir.'/'.$f) ){
						array_push($files, self::cur($dir.'/').$f);
          }
				}
			}
      \bbn\tools::sort($files);
			return $files;
		}
		return false;
	}

	/**
	 * Deletes all the content from a directory.
	 *
	 * If the $full param is set to true, it will also delete the directory itself.
	 *
   * <code>
   * \bbn\file\dir::delete("C:\Documents\Test"); //Deletes "C:\Documents\Test" and subdirectories
   * \bbn\file\dir::delete("C:\Documents\Test", 0); //Deletes "C:\Documents\Test"
   * </code>
   * 
	 * @param string $dir The directory path.
	 * @param boolean $full If set to true, it will also delete the directory itself. Default: "1".
   * 
	 * @return boolean 
	 */
	public static function delete($dir, $full=1)
	{
    $dir = self::clean($dir);
		if ( is_dir($dir) ){
			$files = scandir($dir);
			foreach ( $files as $file ) 
			{
				if ( $file != "." && $file != ".." ) 
				{
					if ( is_dir($dir.'/'.$file) )
						\bbn\file\dir::delete($dir.'/'.$file);
					else
						unlink($dir.'/'.$file);
				}
			}
			if ( $full === 1 ){
				return rmdir($dir);
      }
			return true;
		}
    else if ( is_file($dir) ){
      return unlink($dir);
    }
		return false;
	}

	/**
	 * Creates all the directories from the path taht don't exist.
	 *
   * <code>
   * \bbn\file\dir::create_path("C:\Documents\Test\New")
   * </code>
   * 
	 * @param string $dir The directory path.
	 * @param int $chmod
   * 
	 * @return boolean
	 */
	public static function create_path($dir, $chmod=false)
	{
    if ( !$dir || !is_string($dir) ){
      return false;
    }
    if ( !is_dir(dirname($dir)) ){
      if ( !self::create_path(dirname($dir), $chmod) ){
        return false;
      }
    }
    if ( $dir && !is_dir($dir) ){
      if ( $chmod ){
        if ( $chmod === 'parent' ){
          $chmod = substr(sprintf('%o', fileperms(dirname($dir))), -4);
        }
        $ok = mkdir($dir, $chmod);
      }
      else{
        $ok = mkdir($dir);
      }
      return $ok;
    }
    return 1;
	}

	/**
	 * Moves a file or directory to a new location
   * 
   * <code>
   * \bbn\file\dir::move("C:\Documents\Test\Old", "C:\Documents\Test\New");
   * </code>
   * 
	 * @param string $orig The file to be moved
   * @param string $dest The full name of the destination (including basename)
   * @param mixed $st If $st === true it will be copied over if the destination already exists, otherwise $st will be used to rename the new file in case of conflict
   * @param int $length The number of characters to use for the revision number; will be zerofilled
   * 
	 * @return string the (new or not) name of the destination or false
	 */
	public static function move($orig, $dest, $st = '_v', $length = 0)
	{
    if ( file_exists($orig) && self::create_path(dirname($dest)) ){
      if ( file_exists($dest) ){
        if ( $st === true ){
          self::delete($dest);
        }
        else{
          $i = 1;
          while ( $i ){
            $dir = dirname($dest).'/';
            $file_name = \bbn\str\text::file_ext($dest, 1);
            $file = $file_name[0].$st;
            if ( $length > 0 ){
              $len = strlen(\bbn\str\text::cast($i));
              if ( $len > $length ){
                return false;
              }
              $file .= str_repeat('0', $length - $len);
            }
            $file .= \bbn\str\text::cast($i);
            if ( !empty($file_name[1]) ){
              $file .= '.'.$file_name[1];
            }
            $i++;
            if ( !file_exists($dir.$file) ){
              $dest = $dir.$file;
              $i = false;
            }
          }
        }
      }
      if ( rename($orig, $dest) ){
        return basename($dest);
      }
    }
    return false;
	}
}