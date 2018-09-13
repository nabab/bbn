<?php
	/**
		* @package file
		*/
namespace bbn\file;
use bbn;

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
class dir extends bbn\models\cls\basic
{
	/**
		* Replaces backslash with slash and deletes whitespace from the beginning and the end of a directory's path.
		*
		* ```php
		* \bbn\x::dump(\bbn\file\dir::clean("\home\data\test"));
		* // (string) "/home/data/test"
		* ```
		*
		* @param string $dir The directory path.
		* @return string
		*/
  public static function clean(string $dir): string
  {
    $new = trim(str_replace('\\', '/', $dir));
    if ( substr($new, -1) === '/' ){
      $new = substr($new, 0, -1);
    }
    return $new;
  }

	/**
		* Checks if the given file(s) exists in the directory.
		* Accepts unlimited arguments (files name).
		*
		* ```php
		* \bbn\x::dump(\bbn\file\dir::has_file("/home/data/test/file.txt"));
		* // (bool) true
		* \bbn\x::dump(\bbn\file\dir::has_file("/home/data/test", "file.txt", "doc.pdf"));
		* // (bool) true
		* ```
		*
		* @param string $dir The directory's path.
		* @return bool
		*/
	public static function has_file(string $dir): bool
	{
    $dir = self::clean($dir);
    $as = \func_get_args();
    array_shift($as);
    foreach ( $as as $a ){
      if ( !file_exists($dir.'/'.$a) ){
        return false;
      }
    }
		return true;
	}

	/**
		* If the directory's path starts with './' returns the path without './' else returns the complete path.
		*
		* ```php
		* \bbn\x::dump(\bbn\file\dir::cur("./home/data/test/"));
		* // (string) "home/data/test/"
		* \bbn\x::dump(\bbn\file\dir::cur("/home/data/test/"));
		* // (string) "/home/data/test/"
		* ```
		*
		* @param string $dir The directory path.
		* @return string
		*/
  public static function cur(string $dir): string
  {
    return strpos($dir, './') === 0 ? substr($dir, 2) : $dir;
  }

	/**
		* Return an array of directories contained in the given directory.
		* It will return directories' full path.
		* @todo vedere il parametro $hidden non mi funziona
		*
		* ```php
		* \bbn\x::dump(\bbn\file\dir::get_dirs("C:\Docs\Test"));
		* // (array) ['C:\DocsTest\test1', 'C:\DocsTest\test2', 'C:\DocsTest\test3']
		* ```
		*
		* @param string $dir The directory's path.
		* @param bool $hidden If true return the hidden directories' path
		* @return array|false
		*/
	public static function get_dirs($dir, $hidden = false){
    $dir = self::clean($dir);
    clearstatcache();
    if ( $dir === './' ){
      $dir = '.';
    }
    if ( is_dir($dir) && (($dir === '.') || ((strpos(basename($dir), '.') !== 0) || $hidden)) ){
			$dirs = [];
			$fs = scandir($dir, SCANDIR_SORT_ASCENDING );
			foreach ( $fs as $f ){
				if ( $f !== '.' && $f !== '..' && is_dir($dir.'/'.$f) ){
					$dirs[] = self::cur($dir.'/').$f;
        }
			}
      if ( !empty($dirs) ){
        bbn\x::sort($dirs);
      }
			return $dirs;
		}
		return false;
	}

	/**
		* Returns an array of files contained in the given directory.
		* Returns the full path of files.
		*
		* ```php
		* \bbn\x::dump(\bbn\file\dir::get_files("/home/Docs/Test"));
		* // (array) ['/home/Docs/Test/file.txt']
		* \bbn\x::dump(\bbn\file\dir::get_files("/home/Docs/Test",0,1));
		* // (array) ['/home/Docs/Test/file.txt', '/home/Docs/Test/.doc.pdf']
		* \bbn\x::dump(\bbn\file\dir::get_files("/home/Docs/Test", 1));
		* // (array) ['/home/Docs/Test/folder', '/home/Docs/Test/file.txt']
		* \bbn\x::dump(\bbn\file\dir::get_files("/home/Docs/Test", 1,1));
		* // (array) ['/home/Docs/Test/folder', '/home/Docs/Test/.folder_test','/home/Docs/Test/file.txt', '/home/Docs/Test/.doc.pdf']
		* ```
		*
		* @param string $dir The directory's path.
		* @param bool $including_dirs If set to true it will also returns the folders contained in the given directory.
		* @param bool $hidden If set to true will also returns the hidden files contained the directory
		* @return array|false
		*/
	public static function get_files($dir, $including_dirs = false, $hidden = false)
	{
    $dir = self::clean($dir);
    clearstatcache();
    if ( $dir === './' ){
      $dir = '.';
    }
    if ( is_dir($dir) && (($dir === '.') || ((strpos(basename($dir), '.') !== 0) || $hidden)) ){
			$files = [];
			$fs = scandir($dir, SCANDIR_SORT_ASCENDING );
      //$encodings = ['UTF-8', 'WINDOWS-1252', 'ISO-8859-1', 'ISO-8859-15'];
			foreach ( $fs as $f ){
				if ( $f !== '.' && $f !== '..' ){
          /*
          $enc = mb_detect_encoding($f, $encodings);
          if ( $enc !== 'UTF-8' ){
            $f = html_entity_decode(htmlentities($f, ENT_QUOTES, $enc), ENT_QUOTES , 'UTF-8');
          }
          */
          if ( $hidden || (strpos(basename($f), '.') !== 0) ){
            if ( $including_dirs ){
              $files[] = self::cur($dir.'/').$f;
            }
            else if ( is_file($dir.'/'.$f) ){
              $files[] = self::cur($dir.'/').$f;
            }
          }
				}
			}
      if ( \count($files) > 0 ){
        bbn\x::sort($files);
      }
			return $files;
		}
		return false;
	}

	/**
		* Deletes the given directory and all its content.
		*
		* ```php
		* \bbn\x::dump(\bbn\file\dir::delete('/home/Docs/Test/')
		* // (bool) true
		* \bbn\x::dump(\bbn\file\dir::delete('/home/Docs/Test', 0);
		* // (bool) false
		* \bbn\x::dump(\bbn\file\dir::delete('/home/Docs/Test/file.txt');
		* // (bool) false
		* ```
		*
		* @param string $dir The directory path's.
		* @param bool $full If set to '0' will delete only the content of the directory. Default: "1".
		* @return bool
 	*/
	public static function delete(string $dir, bool $full = true): bool
	{
    $dir = self::clean($dir);
		if ( is_dir($dir) ){
			$files = self::get_files($dir, 1, 1);
			foreach ( $files as $file ){
        self::delete($file);
			}
			if ( $full ){
				return rmdir($dir);
      }
			return true;
		}
    if ( is_file($dir) ){
      return unlink($dir);
    }
		return false;
	}
	/**
		* Returns an array with all the content of the given directory.
		*
		* @todo check the default value for $hidden
		*
		* ```php
		* \bbn\x::dump(\bbn\file\dir::scan("/home/data/test"));
		* // (array) ["/home/data/test/Folder", "/home/data/test/Folder_test/image.png"]
		* \bbn\x::dump(\bbn\file\dir::scan("/home/data/test", "", true));
		* // (array) ["/home/data/test/Folder", "/home/data/test/Folder_test/image.png", "/home/data/test/.doc.pdf"]
		* \bbn\x::dump(\bbn\file\dir::scan("/home/data/test", "dir"));
		* // (array) ["/home/data/test/Folder", "/home/data/test/Folder_test"]
		* \bbn\x::dump(\bbn\file\dir::scan("/home/data/test", "file"));
		* // (array) ["/home/data/test/Folder_test/image.png"]
		* \bbn\x::dump(\bbn\file\dir::scan("/home/data/test", "file", true));
		* // (array) ["/home/data/test/Folder_test/image.png", "/home/data/test/Folder/.doc.pdf"]
		* ```
		*
		* @param string $dir The directory's path.
		* @param string $type The type or the extension of item to return ('file', 'dir', 'php', default is both)
		* @param bool $hidden If set to true will include the hidden files/directories in the result
		* @return array
		*/
	 public static function scan(string $dir, string $type = null, bool $hidden = false): array
   {
	   $all = [];
	   $dir = self::clean($dir);
	   $dirs = self::get_dirs($dir);
	   if ( \is_array($dirs) ){
	     if ( $type && (strpos($type, 'file') === 0) ){
	       $all = self::get_files($dir, false, $hidden);
	     }
	     else if ( $type && ((strpos($type, 'dir') === 0) || (strpos($type, 'fold') === 0)) ){
	       $all = $dirs;
	     }
	     else if ( $type ){
	       $all = array_filter(self::get_files($dir, false, $hidden), function($a)use($type){
	         $ext = bbn\str::file_ext($a);
	         return strtolower($ext) === strtolower($type);
	       });
	     }
	     else{
	       $files = self::get_files($dir, false, $hidden);
	       if ( \is_array($files) ){
	         $all = array_merge($dirs, $files);
	       }
	     }
	     foreach ( $dirs as $d ){
	       $all = array_merge(\is_array($all) ? $all : [], self::scan($d, $type, $hidden));
	     }
	   }
	   return $all;
	 }

	/**
		* Returns an array of indexed arrays with the 'name' of the file/folder contained in the given directory, the 'mtime', and the 'date' of creation the file/folder.
		*
		* ```php
		* \bbn\x::dump(\bbn\file\dir::mscan("/home/data/test"));
		* /* (array)
		* [
		*  [
		*    "name"  =>  "/home/data/test/Folder",
		*    "mtime"  =>  1480422173,
		*    "date"  =>  "2016-11-29  13:22:53",
		*  ],
		*	[
		*    "name"  =>  "/home/data/test/Folder_test",
		*    "mtime"  =>  1480422173,
		*    "date"  =>  "2016-11-29  13:22:53",
		*  ],
		*  [
		*	  "name"  =>  "/home/data/test/Folder_test/image.png",
		*    "mtime"  =>  1480418947,
		*    "date"  =>  "2016-11-29  12:29:07",
		*  ]
		* ]
		* \bbn\x::dump(\bbn\file\dir::mscan("/home/data/test", "dir"));
		* /* (array)
		* [
		*  [
		*    "name"  =>  "/home/data/test/Folder",
		*    "mtime"  =>  1480422173,
		*    "date"  =>  "2016-11-29  13:22:53",
		*  ],
		*	[
		*    "name"  =>  "/home/data/test/Folder_test",
		*    "mtime"  =>  1480422173,
		*    "date"  =>  "2016-11-29  13:22:53",
		*  ]
		* ]
		* \bbn\x::dump(\bbn\file\dir::mscan("/home/data/test", "file"));
		* /* (array)
		* [
		*  [
		*	  "name"  =>  "/home/data/test/Folder_test/image.png",
		*    "mtime"  =>  1480418947,
		*    "date"  =>  "2016-11-29  12:29:07",
		*  ]
		* ]
		* \bbn\x::dump(\bbn\file\dir::mscan("/home/data/test", "file",1));
		* /* (array)
		* [
		*  [
		*	  "name"  =>  "/home/data/test/Folder_test/image.png",
		*    "mtime"  =>  1480418947,
		*    "date"  =>  "2016-11-29  12:29:07",
		*  ],
		* 	[
		*	  "name"  =>  "/home/data/test/Folder/.doc.pdf",
		*    "mtime"  =>  1480418947,
		*    "date"  =>  "2016-11-29  12:29:07",
		*  ]
		* ]
		* ```
		*
		* @param string $dir The directory's path
		* @param string $type The type or the extension of item to return ('file', 'dir', 'php', default is both)
		* @param bool $hidden If set to true will also return the hidden files/folders contained in the given directory. Default=false
		* @return array
		*/
	 public static function mscan(string $dir, string $type = null, $hidden = false): array
   {
     $res = [];
	   if ( $all = self::scan($dir, $type, $hidden) ){
	     foreach ($all as $a ){
	       $t = filemtime($a);
	       $res[] = ['name' => $a, 'mtime' => $t, 'date' => date('Y-m-d H:i:s', $t)];
	     }
	   }
     return $res;
   }
	/**
		* Return an array with the tree of the folder's content.
		*
		* ```php
		* \bbn\x::dump(\bbn\file\dir::get_tree("/home/data/test"));
		* /* (array)
		* [
		*  [
		*   "name"  =>  "/home/data/test/Folder",
		*   "type"  =>  "dir",
		*   "num_children"  =>  0,
		*   "items"  =>  [],
		*  ],
		*  [
		*   "name"  =>  "/home/data/test/Folder_test",
		*   "type"  =>  "dir",
		*   "num_children"  =>  1,
		*   "items"  =>  [
		*                  [
		*                    "name"  =>  "/home/data/test/Folder_test/image.png",
		*                    "type"  =>  "file",
		*                    "ext"  =>  "png",
		*                  ],
		*                ],
		*  ],
		* ]
		* \bbn\x::dump(\bbn\file\dir::get_tree("/home/data/test", true) );
		* /* (array)
		* [
		*   [
		*     "name"  =>  "/home/data/test/Folder",
		*     "type"  =>  "dir",
		*     "num_children"  =>  0,
		*     "items"  =>  [],
		*   ],
		*   [
		*     "name"  =>  "/home/data/test/Folder_test",
		*     "type"  =>  "dir",
		*     "num_children"  =>  0,
		*     "items"  =>  [],
		*   ],
		* ]
		* \bbn\x::dump(\bbn\file\dir::get_tree("/home/data/test", false, false, true) );
		* /* (array)
		* [
		*   [
		*     "name"  =>  "/home/data/test/Folder",
		*     "type"  =>  "dir",
		*     "num_children"  =>  1,
		*     "items"  =>  [
		*                    [
		*                      "name"  =>  "/home/data/test/Folder/.doc.pdf",
		*                      "type"  =>  "file",
		*                      "ext"  =>  "pdf",
		*                    ],
		*                  ],
		*      ],
		*      [
		*        "name"  =>  "/home/data/test/Folder_test",
		*        "type"  =>  "dir",
		*        "num_children"  =>  1,
		*        "items"  =>  [
		*                       [
		*                         "name"  =>  "/home/data/test/Folder_test/image.png",
		*                         "type"  =>  "file",
		*                         "ext"  =>  "png",
		*                       ],
		*                     ],
		*    ],
		* ]
		* ```
		*
		* @param string $dir The directory's path.
		* @param bool $only_dir If set to true will just return the folder(s), if false will include in the resulr also the file(s). Default = false.
		* @param callable $filter Filter function
		* @param bool $hidden If set to true will also return the hidden file(s)/folder(s)
		* @return array
		*/
  public static function get_tree(string $dir, bool $only_dir = false, callable $filter = null, bool $hidden = false): array
  {
    $r = [];
    $dir = self::clean($dir);
    $dirs = self::get_dirs($dir, $hidden);
    if ( \is_array($dirs) ){
      foreach ( $dirs as $d ){
        $x = [
          'name' => $d,
          'type' => 'dir',
          'num_children' => 0,
          'items' => self::get_tree($d, $only_dir, $filter, $hidden)
        ];
        $x['num_children'] = \count($x['items']);
        if ( $filter ){
          if ( $filter($x) ){
            $r[] = $x;
          }
        }
        else{
          $r[] = $x;
        }
      }
      if ( !$only_dir ){
        $files = self::get_files($dir, false, $hidden);
        foreach ( $files as $f ){
          $x = [
            'name' => $f,
            'type' => 'file',
            'ext' => bbn\str::file_ext($f)
          ];
          if ( $filter ){
            if ( $filter($x) ){
              $r[] = $x;
            }
          }
          else{
            $r[] = $x;
          }
        }
      }
    }
    return $r;
  }

	/**
		* Creates a folder with the given path.
		*
		* ```php
		* \bbn\x::dump(\bbn\file\dir::create_path("/home/data/test/New"));
		* \\ (string) "/home/data/test/New"
		* ```
		*
		* @param string $dir The new directory's path.
		* @param bool $chmod If set to true the user won't have the permissions to view the content of the folder created
		* @return string|false
		*/
	public static function create_path(string $dir, $chmod=false){
    if ( !$dir || !\is_string($dir) ){
      return false;
    }
    clearstatcache();
    if ( !is_dir($dir) ){
      $bits = explode('/', $dir);
      $path = empty($bits[0]) ? '/' : '';
      foreach ( $bits as $i => $b ){
        if ( !empty($b) ){
          $path .= $b;
          if ( !mkdir($path) && !is_dir($path) ){
            return false;
          }
          if ( $chmod ){
            @chmod($path, $chmod);
          }
          $path .= '/';
        }
      }
    }
    return $dir;
	}

	/**
		* Moves a file or directory to a new location
		*
		* ```php
		* \bbn\x::dump(\bbn\file\dir::move("/home/data/test/Folder/image.png","/home/data/test/Folder_test/image.png"));
		* \\ (string) "image.png"
		* \bbn\x::dump(\bbn\file\dir::move("/home/data/test/Folder/image.png","/home/data/test/Folder_test/Intro/image.png"));
		* \\ (string) "image.png"
		* \bbn\x::dump(\bbn\file\dir::move("/home/data/test/Folder","/home/data/test/Folder_test", true));
		* \\ (string) "Folder_test"
		* \bbn\x::dump(\bbn\file\dir::move("/home/data/test/Folder","/home/data/test/Folder_test", "_n", 3));
		* \\ (string) "Folder_test_n001"
		* ```
		*
		* @param string $orig The path of the file to move
		* @param string $dest The full name of the destination (including basename)
		* @param string | true $st If in the destination folder alredy exists a file with the same name of the file to move it will rename the file adding '_v' (default). If 'string' will change the file name with the given string. If $st=true it will overwrite the file/folder.
		* @param int $length The number of characters to use for the revision number; will be zerofilled
		* @return bool Success
		*/
	public static function move($orig, $dest, $st = '_v', $length = 0): bool
	{
    if ( file_exists($orig) && self::create_path(\dirname($dest)) ){
      if ( file_exists($dest) ){
        if ( $st === true ){
          self::delete($dest);
        }
        else{
          $i = 1;
          while ( $i ){
            $dir = \dirname($dest).'/';
            $file_name = bbn\str::file_ext($dest, 1);
            $file = $file_name[0].$st;
            if ( $length > 0 ){
              $len = \strlen(bbn\str::cast($i));
              if ( $len > $length ){
                return false;
              }
              $file .= str_repeat('0', $length - $len);
            }
            $file .= bbn\str::cast($i);
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
        return true;
      }
    }
    return false;
	}
	/**
		* Will move the content of the given folder to a new destination. Doesn't move the hidden files.
		*
		* ```php
		* \bbn\x::dump(\bbn\file\dir::copy("/home/data/test/Folder","/home/data/test/Folder_test"));
		* \\ (bool) 1
		* ```
		*
		* @param string $src The path of the files to move
		* @param string $dst The new destination of files
		* @return bool
		*/
  public static function copy($src, $dst): bool
  {
    if ( is_file($src) ){
      return copy($src, $dst);
    }
    if ( is_dir($src) && self::create_path($dst) ){
      $files = self::get_files($src);
      $dirs = self::get_dirs($src);
      foreach ( $files as $f ){
        copy($f, $dst.'/'.basename($f));
      }
      foreach ( $dirs as $f ){
        self::copy($f, $dst.'/'.basename($f));
      }
      return true;
    }
    else{
      return false;
    }
  }
}
