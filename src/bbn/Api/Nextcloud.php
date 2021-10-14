<?php
//https://medium.com/@cetteup/how-to-access-nextcloud-using-webdav-and-php-2c00a04e35b9
namespace bbn\Api;
use bbn;
use bbn\X;

class Nextcloud extends bbn\Models\Cls\Basic{
  
  private $obj;
  private $path;
  private $_prefix;
  
  private const prefix = '/remote.php/webdav/';
  /**
   * Instantiate the class Nextcloud by connecting the given user to the given url
   *
   * @param array $cfg 
   */
  public function __construct(array $cfg)
  {
    if ( isset($cfg['host'], $cfg['user'], $cfg['pass']) ){
      $this->path = 'https://'.$cfg['host'].self::prefix;
      $this->obj = new \Sabre\DAV\Client([
        'baseUri' => $this->path,
        'userName' => $cfg['user'],
        'password' => $cfg['pass']
      ]);
      $this->_prefix = self::prefix.'files/'.$cfg['user'].'/';
    }

    if ( !$this->obj ){
      $this->error = X::_("Missing parameters");
    }
  }

  /**
   * Returns the size of the given dir or file, if no path is given it returns the size of the root folder
   *
   * @param string $path
   * @return void
   */
  public function getSize($path = ''): ?int
  {
    $tmp = $path;
    $path = $this->getRealPath($path);
    $size = $this->obj->propFind($tmp, array(
      '{http://owncloud.org/ns}size'
    ));
    if ($size) {
      return (int)array_values($size)[0];
    }

    return null;
  }
  
  /**
   * Deletes the given file or folder
   *
   * @param string $file
   * @return Boolean
   */
  public function delete($path)
  {
    $success = false;
    //die(var_dump($path));
    if ( !empty($path) && $this->exists($path) && !empty($this->obj->request('DELETE', $path)) ){
      $success = true;
    }
    return $success;
  }
  
  protected function getProps($path): ?array
  {
    try {
      if ( $this->obj->propFind($path, [
        '{DAV:}resourcetype',
        '{DAV:}getcontenttype'
      ], 0) ){
        return true;
      }
    }
    catch (\Sabre\HTTP\ClientException $e) {
      if (isset($e->getResponse) && is_callable($e->getResponse)) {
        if ( $e->getResponse()->getStatus() !== 404 ){
          $this->error = $e->getResponse()->getStatusText();
        }
      }
      else {
        $this->error = $e->getMessage();
      }
    }

    if ($this->error) {
      throw new \Exception($this->error);
    }

    return false;
  }

  /**
   * Returns true if the given $path exists
   *
   * @param string $path
   * @return Boolean
   */
  public function exists($path)
  {
    try {
      if ( $this->obj->propFind($path, [
        '{DAV:}resourcetype',
        '{DAV:}getcontenttype'
      ], 0) ){
        return true;
      }
    }
    catch (\Sabre\HTTP\ClientException $e) {
      if (isset($e->getResponse) && is_callable($e->getResponse)) {
        if ( $e->getResponse()->getStatus() !== 404 ){
          $this->error = $e->getResponse()->getStatusText();
        }
      }
      else {
        $this->error = $e->getMessage();
      }
    }

    if ($this->error) {
      throw new \Exception($this->error);
    }

    return false;
  }
  
  /**
   * Creates a dir at the given path
   *
   * @param string $dir
   * @return Boolean
   */
  public function mkdir($dir){
    $success = false;
    if ( !$this->exists($dir) && !empty($this->obj->request('MKCOL', $dir)) ){
      $success = true;
    }
    return $success;
  }

  /**
   * Copies the given file or folder to the given destination, if the given destination already exists throws an error.
   * @param string $source
   * @param string $dest
   * @return Boolean
   */
  public function copy(string $source, string $dest): bool
  {
    
    if ( $this->exists($source) ){
      
      if ( !empty($dest) ){
        if ( !$this->exists($dest) ){
          return (bool)$this->obj->request('COPY', $source, null, [
            'Destination' => self::prefix.$dest
          ]);
        }
        else {
          $this->error = X::_("The given destination already exists");
          return false;
        }
      }
    }
  }  
  /**
   * Renames files or folder from the $old name to the $new name-
   * @param string $old
   * @param string $new
   * @return Boolean
   */
  public function rename(string $old, string $new): bool
  {
    if ( $this->exists($old) ){
      if ( !$this->exists($new) ){
        return (bool)$this->obj->request('MOVE', $old, null, [
          'Destination' => $new
        ]);
      }
      else {
        $this->error = X::_("The new name given already exists");
        return false;
      }
    }
    else {
      $this->error = X::_("The given path does not correspond to a file or a directory");
      return false;
    }
  }
  
  /**
   * Returns true if the given $path corresponds to a file.
   * @param string $path
   * @return Boolean
   */
  public function isFile(string $path): bool
  {
    return !empty(
      $this->obj->propFind(
        $path,
        ['{DAV:}getcontenttype'],
        0
      )
    );
  }
  
  /**
   * Returns true if the given $path corresponds to a directory.
   * @param string $path
   * @return Boolean
   */
  public function isDir(string $path): bool
  {

    return $this->exists($path) &&
        empty(
          $this->obj->propFind(
            $path,
            ['{DAV:}getcontenttype'],
            0
          )
        );
  }
  
  /**
   * Returns the date of last modification of the given path
   * @param string $path
   */
  public function filemtime(string $path)
  {
    if ( $this->exists($path) ){
      $mtime = $this->obj->propFind($path, [
        '{DAV:}getlastmodified'
      ]);
      if ( !empty($mtime['{DAV:}getlastmodified']) ){
        return $mtime['{DAV:}getlastmodified'];
      }
      else {
        $this->error = X::_("The last modification date cannot be retrieved");
        return null;
      }  
    }
    else {
       $this->error = X::_("The given path doesn't exist");
    }
  }
  
  public function getFile(string $file): ?bbn\File
  {
    if ( $this->isFile($file) ){
      return new \bbn\File(\bbn\Mvc::getTmpPath().X::basename($file));
    }
  }

  /**
   * Download the given file
   * @param string $file
   */
  public function download(string $file): void
  {
    if ($this->isFile($file)) {
      //the tmp file destination
      $dest = \bbn\Mvc::getTmpPath().X::basename($file);
      //gets the content of the file
      $res = $this->obj->request('GET', $this->_prefix.self::fixURL($this->getSystemPath($file)));
      if (!empty($res) && !empty($res['body'])) {
        // the tmp file created
        if (file_put_contents($dest, $res['body'])) {
          // instantiates the new file to the class \bbn\File
          $tmp = new \bbn\File($dest);
          $tmp->download();
          //unlink($dest);
        } 
      }
    }
  }
  
  /**
   * Returns an array of items contained in the given path, if no path is given it returns the root content, if the argument $detailed is given includes details of size and last modification time in the item
   *
   * @param string $path
   * @param string $type (both, Files, Dirs)
   * @param boolean $hidden
   * @param string $detailed
   * @return array
   */
  public function getItems(string $path = '', $type = 'both', bool $hidden = false, string $detailed = ''): array
  {
    if ( empty($path) || ($path === '.') ){
      $path = self::prefix;
    }
   // $path = $this->getSystemPath($path);
    if ( $this->exists($path) && $this->isDir($path) ){
      $props = ['{DAV:}getcontenttype'];
      $collection = $this->obj->propFind($path, $props, 1);
      if ( !empty($collection) ){
        //arrayt_shift to remove the parent included in the array
        $dirs = [];
        $files = [];
        $has_dir = in_array($type, ['both', 'dir']);
        $has_file = in_array($type, ['both', 'file']);
        $num = 0;
        foreach ( $collection as $i => $c ){
          $num++;
          // The 2 first child are .. and .
          if ($num < 3) {
            continue;
          }

          $npath = $name = urldecode(str_replace(self::prefix, '', $i));
          if (empty($c['{DAV:}getcontenttype'])) {
            $name = substr($npath, 0, -1);
          }

          $tmp = [
            'path' => $npath,
            'dir' => empty($c['{DAV:}getcontenttype']) ? true : false,
            'file' => empty($c['{DAV:}getcontenttype']) ? false : true,
            'name' => X::basename($name),
          ];
          //if details has to be included on the item
          if ( !empty($detailed) ){
            $tmp['mtime'] = $this->filemtime($i);
            $tmp['size'] = $this->getSize($i);
          }
          if ($has_dir && $tmp['dir']) {
            $dirs[] = $tmp;
          }
          else if ($has_file && $tmp['file']) {
            $files[] = $tmp ;
          }
        }
        
        if ($type === 'dir') {
          return $dirs;
        }
        
        if ($type === 'file') {
          return $files;
        }

        // both
        X::log([...$dirs, ...$files]);
        return [...$dirs, ...$files];
      }  
    }
    else {
      $this->error = X::_("The path doesn't exists or it's not a directory");
    }
  }

  /**
   * Returns the real path. 
   * @param string $path
   * @return String
   */
  public function getRealPath(string $path): string
  {
    if ( strpos($path, self::prefix) !== 0 ){
      return self::prefix.$path;
    }
    else {
      return $path;
    }
  }


  /**
   * Returns the system path. 
   * @param string $path
   * @param Boolean $is_absolute
   * @return String
   */
  public function getSystemPath(string $file, bool $is_absolute = true): string
  {
    if ( strpos($file, self::prefix) === 0 ){
      return substr($file, strlen(self::prefix) + ($is_absolute ? 0 : 1) -1 );
    }
    else {
      return $path;
    }
  }

  /**
   * Returns the content of the given file
   *
   * @param string $file
   * @return String
   */
  public function getContents($file): string
  {
    if ( $this->exists($file) && $this->isFile($file) ){
    //gets the content of the file
      $res = $this->obj->request('GET', $this->getRealPath($file));
      if ( !empty($res) && !empty($res['body']) ){
        return $res['body'];
      }
    }
  }

  /**
   * Returns the fies contained in the given $path
   *
   * @param string $path
   * @param boolean $including_dirs
   * @param boolean $hidden
   * @param string $filter
   * @param string $detailed
   * @return array|null
   */
  public function getFiles(string $path = null, $including_dirs = false, $hidden = false, $filter = null, string $detailed = ''): ?array
  {
    //exists and is_dir is checked $path in the function get_items
    $is_absolute = strpos($path, '/') === 0;
    $type = $including_dirs ? 'both' : 'file';
    //die(var_dump($path, $filter , $type, $hidden, $detailed))//die(var_dump($this->getItems('.', 'both', true, 't')));
    return $this->getItems($path, $filter ?: $type, $hidden, $detailed);
  }

  public function upload(array $files, string $path): bool
  {
    $success = false;
    if ( !empty($files) && !empty($path) ){
      if ( strpos($path, '.') === 0){
        $path = '';
      }
      foreach ( $files as $f ){
        if ( is_file($f['tmp_name']) && ($content = file_get_contents($f['tmp_name'])) ){
          // wanted to put '%' instead of ' ' in the filename but not accepted
          $full_name =  $path . (($path !== '') ? '/' : '' ) . str_replace(' ', '_',$f['name']);
          if (!$this->exists($full_name) ){
            if ( $this->obj->request('PUT', $full_name, $content) ){
              return $success = true;
            }
          }
        }
      }
    }
    return $success;
  }

  private function _propFind(string $path, array $props, int $deep = 0): array
  {
    return $this->obj->propFind(self::fixURL($path), $props, $deep);
  }

  private static function fixURL(string $path): string
  {
    $fpath = '';
    $bits = X::split($path, '/');
    $num = count($bits);
    foreach ($bits as $i => $bit) {
      if (!$bit) {
        $fpath .= '/';
      }
      else {
        $fpath .= rawurlencode($bit);
        if ($i < $num -1) {
          $fpath .= '/';          
        }
      }
    }

    return $fpath;

  }
}



