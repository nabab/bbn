<?php
/**
 * @package file
 */
namespace bbn;
/**
 * Perform a single file objectification and manage its manipulation.
 *
 *
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @copyright BBN Solutions
 * @since Apr 4, 2011, 23:23:55 +0000
 * @category Files ressources
 * @package bbn
 * @license \sa elem http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @version 0.2r89
 */
class File extends Models\Cls\Basic
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
   * @var File\System $fs
   */
  protected $fs;

  /**
   * @var string
   */
  protected $hash;

  /**
   * @var string
   */
  public $path;

  /**
   * @var string
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
   * Constructor.
   *
   * ```php
   * $file=new bbn\File('/home/user/Desktop/test.txt');
   * ```
   *
   * @param mixed $file
   * @return $this
   */
  public function __construct($file, File\System $fs = null)
  {
    $this->fs = $fs ?: new File\System();
    if ( \is_array($file) )
    {
      if ( isset($file['name'],$file['tmp_name']) )
      {
        $this->path = '';
        $this->name = $file['name'];
        $this->size = $file['size'];
        $file = $file['tmp_name'];
      }
    }
    else if ( \is_string($file) )
    {
      $file = trim($file);
      if ( strrpos($file,'/') !== false )
      {
        /* The -2 in strrpos means that if there is a final /, it will be kept in the file name */
        $this->name = substr($file,strrpos($file,'/',-2)+1);
        $this->path = substr($file,0,-\strlen($this->name));
        if ( substr($this->path,0,2) == '//' ){
          $this->path = 'http://'.substr($this->path,2);
        }
      }
      else
      {
        $this->name = $file;
        $this->path = './';
      }
    }

    $this->getExtension();
    if ( \is_string($file) && is_file($file) ){
      $this->file = $file;
    }
    else{
      $this->make();
    }
  }

  /**
   * Return the filesize in byte.
   *
   * ```php
   * $file = new bbn\File('C:/Test/file.txt');
   * X::dump($file->getSize());
   * // (int) 314
   * ```
   *
   * @return int
   */
  public function getSize()
  {
    if ( $this->file && $this->size === 0 ){
      $this->size = filesize($this->file);
    }
    return $this->size;
  }

  /**
   * @return Generator
   */
  public function iterateLines()//: Generator
  {
    if ( $this->file ){
      $f = fopen($this->file, 'r');
      try {
        while ($line = fgets($f)) {
          yield $line;
        }
      }
      finally {
        fclose($f);
      }
    }
  }

  /**
   * Return the extension of the file.
   *
   * ```php
   * $file = new File('C:/Test/file.txt');
   * X::dump($file->getExtension());
   * //(string) 'txt'
   * ```
   *
   * @return string|false
   */
  public function getExtension()
  {
    if ( $this->name ){
      if ( !isset($this->ext) ){
        if ( strpos($this->name, '.') !== false ){
          $p = Str::fileExt($this->name, 1);
          $this->ext = $p[1];
          $this->title = $p[0];
        }
        else{
          $this->ext = '';
          $this->title = substr($this->name,-1) === '/' ? substr($this->name,0,-1) : $this->name;
        }
      }
      return $this->ext;
    }
    return false;
  }

  /**
   * Creates a temporary file in tmp directory.
   *
   * @todo of adjusting
   * @return file
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
        if ( file_put_contents($f, $c) ){
          if ( substr($this->name,-1) == '/' ){
            $this->name = substr($this->name,0,-1);
          }
          chmod($f, 0644);
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
   * Downloads the file. At the end of the script the user will be invited to choose the file's destination. If the file doesn't exist return an object with parameter file = null.
   *
   * ```php
   * $f = new \bbn\File('C:/Test/file.png');
   * $f->download();
   * ```
   *
   * @return file
   */
  public function download()
  {
    if ( $this->file ){
      if ( !$this->size ){
        $this->getSize();
      }
      
      if ( $this->size && ($handle = fopen($this->file, 'r')) ){
        header('Content-type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.$this->name.'"');
        while ( !feof($handle) ){
          echo fread($handle, 65536);
        }
        fclose($handle);
      }
      else{
        die('Impossible to read the file '.$this->name);
      }
    }
    return $this;
  }

  /**
   * Return the hash of the file.
   *
   * ```php
   * $file = new File('C:/Test/file.txt');
   * X::dump($file->getHash());
   * // (string) '9a3182g36a83adtd9c9c2l59ap2a719c'
   * ```
   *
   * @return string
   */
  public function getHash()
  {
    if ( $this->file ){
      return md5_file($this->file);
    }
    return '';
  }

  /**
   * Deletes the file.
   *
   * ```php
   * X::hdump( is_file('C:/Test/file.txt') );
   * // (bool) true
   * $file = new File('C:/Test/file.txt');
   * $file->delete();
   * X::hdump( is_file('C:/Test/file.txt') );
   * // (bool) false
   * ```
   *
   * @return file
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
   * That feature saves the file as a parameter, and accepts a string that contains the path where to save.
   *
   * ```php
   *  $file->save('/home/user/desktop/');
   * ```
   *
   * @param string $dest
   * @return file
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
      if ( null !== $_FILES ){
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
