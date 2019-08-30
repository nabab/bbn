<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 31/01/2019
 * Time: 04:17
 *
 * //$encodings = ['UTF-8', 'WINDOWS-1252', 'ISO-8859-1', 'ISO-8859-15'];
 * $enc = mb_detect_encoding($f, $encodings);
 * if ( $enc !== 'UTF-8' ){
 *   $f = html_entity_decode(htmlentities($f, ENT_QUOTES, $enc), ENT_QUOTES , 'UTF-8');
 * }
 *
 */

namespace bbn\file;
use bbn;


/**
 * Class system
 * @package bbn\file
 */
class system2 extends bbn\models\cls\basic
{
  /**
   * @var mixed The connection obj only if it is different from the original connection
   */
  private $cn = '';

  /**
   * @var mixed The connection obj
   */
  public $obj = '';

  /**
   * @var string The connection prefix (with connection infos)
   */
  private $prefix = '';

  /**
   * @var string The mode of connecti0n (ftp, ssh, or local)
   */
  private $mode;

  /**
   * @var string The current directory
   */
  private $current;

  protected $host;

  protected $timeout = 10;
 
  /**
   * Connect to a Nextcloud instance
   * @param array $cfg
   * @return bool
   */
  private function _connect_nextcloud(array $cfg): bool
  {
  
    if ( isset($cfg['host'], $cfg['user'], $cfg['pass']) && class_exists('\\Sabre\\DAV\\Client') ){
      $this->prefix = '/remote.php/webdav/';
      $this->obj = new \Sabre\DAV\Client([
        'baseUri' => 'http'.(isset($cfg['port']) && ($cfg['port'] === 21) ? '' : 's').'://'.$cfg['host'].$this->prefix.$cfg['path'],
        'userName' => $cfg['user'],
        'password' => $cfg['pass']
      ]);
      $this->host = 'http'.(isset($cfg['port']) && ($cfg['port'] === 21) ? '' : 's').'://'.$cfg['host'];
     
      if ( $this->obj->options() ){
        $this->current = '';
        return true;
      }
      $this->error = _('Impossible to connect to the WebDAV host');
    }
    return false;
  }

  /**
   * Connect to FTP
   * @param array $cfg
   * @return bool
   */
  private function _connect_ftp(array $cfg): bool
  {
    if ( isset($cfg['host'], $cfg['user'], $cfg['pass']) ){
      $args = [$cfg['host'], $cfg['port'] ?? 21, $cfg['timeout'] ?? $this->timeout];
      if (
        (
          ($this->obj = @ftp_ssl_connect(...$args)) &&
          @ftp_login($this->obj, $cfg['user'], $cfg['pass'])
        ) || (
          ($this->obj = @ftp_connect(...$args)) &&
          @ftp_login($this->obj, $cfg['user'], $cfg['pass'])
        ) ){
        $this->current = ftp_pwd($this->obj);
        $this->prefix = 'ftp://'.$cfg['user'].':'.$cfg['pass'].'@'.$cfg['host'];
        return true;
      }
      $this->error = _('Impossible to connect to the FTP host');
    }
    return false;
  }

  /**
   * Connects to SSH
   * @param array $cfg
   * @return bool
   */
  private function _connect_ssh(array $cfg): bool
  {
    if ( isset($cfg['host'], $cfg['user'], $cfg['pass']) ){
      $this->cn = @ssh2_connect($cfg['host'], $cfg['port'] ?? 22);
      if ( !$this->cn ){
        $this->error = _("Could not connect through SSH.");
      }
      else if ( @ssh2_auth_password($this->cn, $cfg['user'], $cfg['pass']) ){
        //die(_("Could not authenticate with username and password."));
        $this->obj = @ssh2_sftp($this->cn);
        if ( $this->obj ){
          $this->current = ssh2_sftp_realpath($this->obj, '.');
          $this->prefix = 'ssh2.sftp://'.$this->obj;
          return true;
        }
        $this->error = _("Could not initialize SFTP subsystem.");
      }
      else{
        $this->error = _("Could not authenticate with username and password.");
      }
    }
    return false;
  }

  /**
   * Checks if the given files name ends with the given suffix string
   * @param array|string $item
   * @param callable|string $filter
   * @return bool
   */
  private function _check_filter($item, $filter): bool
  {
    if ( $filter ){
      if ( is_string($filter) ){
        return strtolower(substr(\is_array($item) ? $item['path'] : $item, - strlen($filter))) === strtolower($filter);
      }
      if ( is_callable($filter) ){
        return $filter($item);
      }
    }
    return true;
  }

  private function _propfind_deep($path, $props, string $deep ){
    $collection = $this->obj->propFind($this->get_real_path($path), $props, $deep);
    $res = [];
    if ( !empty($collection) ){
      foreach ( $collection as $i => $c ){
        $tmp = [
          'path' => $this->get_real_path($i),
          'dir' => $c['{DAV:}resourcetype'] || false
        ];  
        $res[] = $tmp;
      }
      return $res;
    }
    return [];
  }

  /**
   * Raw function returning the elements contained in the given directory
   * @param string $path
   * @param string|callable $type
   * @param bool $hidden
   * @param string $detailed
   * @return array
   */
  //@todo public just to test
  private function _get_items(string $path, $type = 'both', bool $hidden = false, string $detailed = ''): array
  {
    $files = [];
    $dirs = [];
    if ( $this->mode === 'nextcloud' ) {
      if ( empty($path) || ($path === '.') ){
        $path = $this->prefix;
      }
      if ( $this->is_dir($path) ){
        $props = ['{DAV:}getcontenttype'];
        //devo prendere rimandare name , dir e file
        $collection = $this->obj->propFind($path, 
          $props, 1);
        if ( !empty($collection) ){
          array_shift($collection);
          foreach ( $collection as $i => $c ){
            $tmp = [
              'path' => $i,//$path === $this->prefix ? $i : basename($i),
              'dir' => empty($c['{DAV:}getcontenttype']) ? true : false,
              'file' => empty($c['{DAV:}getcontenttype']) ? false : true,
            ];
            //arrayt_shift to remove the parent included in the array
            if ($type === 'both'){
              if ($tmp['dir']) {
                $dirs[] = $detailed ? $tmp : $i;
              }
              else{
                $files[] = $detailed ? $tmp : $i;
              }
            }
            else if ( $tmp['file'] && ($type === 'file') ){
              $files[] = $detailed ? $tmp : $i;
            }
            else if ( $tmp['dir'] ($type === 'dir') ){
              $dirs[] = $detailed ? $tmp : $i;
            }
          }
        }  
      }
    }
    if ( ($this->mode === 'ftp') && ($detailed || ($type !== 'both')) ){
      $fs = ftp_mlsd($this->obj, substr($path, strlen($this->prefix)));
      foreach ( $fs as $f ){
        if ( ($f['name'] !== '.') && ($f['name'] !== '..') && ($hidden || (strpos(basename($f['name']), '.') !== 0)) ){
          $ok = 0;
          if ( $type === 'both' ){
            $ok = 1;
          }
          else if ( $type === 'dir' ){
            $ok = $f['type'] === 'dir';
          }
          else if ( $type === 'file' ){
            $ok = $f['type'] === 'file';
          }
          else if ( !is_string($type) || is_file($path.'/'.$f['name']) ){
            $ok = $this->_check_filter($f['name'], $type);
          }
          if ( $ok ){
            if ( $detailed ){
              if ( !isset($has_type, $has_mod) ){
                $has_type = stripos($detailed, 't') !== false;
                $has_mod = stripos($detailed, 'm') !== false;
              }
              $tmp = [
                'path' => $path.'/'.$f['name']
              ];
              if ( $has_mod ){
                $tmp['mtime'] = mktime(
                  substr($f['modify'], 8, 2),
                  substr($f['modify'], 10, 2),
                  substr($f['modify'], 12, 2),
                  substr($f['modify'], 4, 2),
                  substr($f['modify'], 6, 2),
                  substr($f['modify'], 0, 4)
                );
              }
              if ( $has_type ){
                $tmp['dir'] = $f['type'] === 'dir';
                $tmp['file'] = $f['type'] !== 'dir';
              }
              if ( $has_size ){
                $tmp['size'] = $f['type'] === 'dir' ? 0 : $this->filesize($path.'/'.$f['name']);
              }
              if ($f['type'] === 'dir') {
                $dirs[] = $tmp;
              }
              else{
                $files[] = $tmp;
              }
            }
            else{
              if ($f['type'] === 'dir') {
                $dirs[] = $path.'/'.$f['name'];
              }
              else{
                $files[] = $path.'/'.$f['name'];
              }
            }
          }
        }
      }
    }
    else{
      $fs = scandir($path, SCANDIR_SORT_ASCENDING);
      foreach ( $fs as $f ){
        if ( ($f !== '.') && ($f !== '..') && ($hidden || (strpos(basename($f), '.') !== 0)) ){
          $ok = 0;
          $is_dir = is_dir($path.'/'.$f);
          $is_file = !$is_dir;
          if ( $type === 'both' ){
            $ok = 1;
          }
          else if ( $type === 'dir' ){
            $ok = $is_dir;
          }
          else if ( $type === 'file' ){
            $ok = $is_file;
          }
          else if ( !is_string($type) || $is_file ){
            $ok = $this->_check_filter($f, $type);
          }
          if ( $ok ){
            if ( $detailed ){
              if ( !isset($has_type, $has_mod) ){
                $has_type = stripos($detailed, 't') !== false;
                $has_mod = stripos($detailed, 'm') !== false;
              }
              $tmp = [
                'path' => $path.'/'.$f
              ];
              if ( $has_mod ){
                $tmp['mtime'] = filemtime($path.'/'.$f);
              }
              if ( $has_type ){
                $tmp['dir'] = $is_dir ?? is_dir($path.'/'.$f);
                $tmp['file'] = !$tmp['dir'];
              }
            }
            else {
              $tmp = $path.'/'.$f;
            }
            if ($is_dir) {
              $dirs[] = $tmp;
            }
            else{
              $files[] = $tmp;
            }
          }
        }
      }
    }
    return array_merge($dirs, $files);
  }

  /**
   * @param $path
   * @return bool
   */
  private function _exists($path): bool
  {
    \bbn\x::log(['_exists' => $path], 'cheSuccede');
    if ( $this->mode === 'nextcloud' ){
      try {
        if ( $this->obj->propFind($path, [
          '{DAV:}resourcetype',
          '{DAV:}getcontenttype'
        ], 0) ){
          return true;
        }
      }
      catch ( \Exception $e ){
        if ( $e->getResponse()->getStatus() === 404 ){
          return false;
        }
        else{
          $this->error = $e->getResponse()->getStatusText();
        }
      }
      return false;
    }
    else{
      $file = $this->get_real_path($path);
      return file_exists($file);
    }
    
  }

  /**
   * @param string $path
   * @param string|callable|null $filter
   * @param bool $hidden
   * @param string $detailed
   * @return array
   */
  private function _scand(string $path = '', $filter = null, bool $hidden = false, string $detailed = ''): array
  {
    $all = [];
    foreach ( $this->_get_items($path, 'dir', $hidden, $detailed) as $it ){
      $p = $detailed ? $it['path'] : $it;
      if ( !$filter || $this->_check_filter($p, $filter) ){
        $all[] = $it;
      }
      foreach ( $this->_scand($p, $filter, $hidden, $detailed) as $t ){
        $all[] = $t;
      }
    }
    return $all;
  }

  /**
   * @param string $path
   * @param string|callable|null $filter
   * @param bool $hidden
   * @param string $detailed
   * @return array
   */
  private function _scan(string $path = '', $filter = null, bool $hidden = false, string $detailed = ''): array
  {
    $all = [];
    foreach ( $this->_get_items($path, 'both', $hidden, $detailed) as $it ){
      $p = $detailed ? $it['path'] : $it;
    
      if ( !$filter || $this->_check_filter($p, $filter) ){
        $all[] = $it;
      }
      if ( is_dir($p) ){
        foreach ( $this->_scan($p, $filter, $hidden, $detailed) as $t ){
          $all[] = $t;
        }
      }
    }
    return $all;
  }

  /**
   * @param string $dir
   * @param int $chmod
   * @param bool $recursive
   * @return bool
   */
  private function _mkdir(string $dir, int $chmod = 0755, $recursive = false): bool
  {
    if ( $this->mode !== 'nextcloud' ){
      return is_dir($dir) || (@mkdir($dir, $chmod, $recursive) || is_dir($dir));
    }
    else {
      return $this->is_dir($dir) || $this->obj->request('MKCOL', $dir);
    }
  }

  /**
   * @param string $path
   * @param bool $full
   * @return bool
   */
  private function _delete(string $path, bool $full = true): bool
  { 
    if ( $this->is_dir($path) ){
      $files = $this->_get_items($path, 'both', true);
      
      if ( !empty($files) ){
        foreach ( $files as $file ){
          $this->_delete($file);
        }
      }
      if ( $full ){
        if ( $this->mode === 'ssh' ){
          return @ssh2_sftp_rmdir($this->obj, substr($path, strlen($this->prefix)));
        }
        if ( $this->mode === 'ftp' ){
          return @ftp_rmdir($this->obj, substr($path, strlen($this->prefix)));
        }
        else if ( $this->mode === 'nextcloud' ){
          if ( $this->obj->request('DELETE', $path) ){
            return true;
          }
        }
        else{
          return rmdir($path);
        }
        return false;
      }
      return true;
    }
    if ( $this->is_file($path) ){
      if ( $this->mode === 'ssh' ){
        return ssh2_sftp_unlink($this->obj, substr($path, strlen($this->prefix)));
      }
      if ( $this->mode === 'ftp' ){
        return ftp_delete($this->obj, substr($path, strlen($this->prefix)));
      }
      else if ( $this->mode === 'nextcloud' ){
        if ( $this->obj->request('DELETE', $path) ){
          return true;
        }
      }
      return unlink($path);
    }
    return false;
  }

  /**
   * @param string $source
   * @param string $dest
   * @return bool
   */
  private function _copy(string $source, string $dest): bool
  {
    if ( $this->is_file($source) ){
      if ( $this->mode === 'nextcloud' ){
        return (bool)$this->obj->request('COPY', $source, null, [
          'Destination' => $dest
        ]);
      }
      return copy($source, $dest);
    }
    else if ( $this->is_dir($source)  && $this->_mkdir($dest) ){
      
      if ( ( $this->mode !== 'nextcloud' ) ){
        foreach ( $this->_get_items($source, 'both', true) as $it ){
          $this->_copy($it, $dest.'/'.basename($it));
        }
        return true;
      }
      else if ( $this->mode === 'nextcloud' ){
        foreach ( $this->_get_items($source, 'both', true) as $it ){
          $this->_copy($it, $dest.'/'.basename($it));
        }
        return true;
      }
    }
    return false;
  }

  /**
   * @param $source
   * @param $dest
   * @return bool
   */
  private function _rename($source, $dest): bool
  {
    $file1 = substr($source, strlen($this->prefix));
    $file2 = substr($dest, strlen($this->prefix));
    
    if ( $this->mode === 'ssh' ){
      return ssh2_sftp_rename($this->obj, $file1, $file2);
    }
    else if ( $this->mode === 'ftp' ){
      return ftp_rename($this->obj, $file1, $file2);
    }
    if ( $this->mode === 'nextcloud' ){
      return (bool)$this->obj->request('MOVE', $source, null, [
        'Destination' => $dest
      ]);
    }
    return rename($file1, $file2);
  }

  private function _get_empty_dirs($path, bool $hidden_is_empty = false): array
  {
    $res = [];
    bbn\x::log($path, 'infolegale');
    $all = $this->_get_items($path, 'both', !$hidden_is_empty);
    foreach ( $all as $dir ){
      if ( is_dir($dir) ){
        if ( !count($files = $this->_get_items($dir, 'file', !$hidden_is_empty)) ){
          $dirs = $this->_get_items($dir, 'dir', !$hidden_is_empty);
          $tot = count($dirs);
          $empty_dirs = $this->_get_empty_dirs($dir, !$hidden_is_empty);
          if ( $tot && count($empty_dirs) ){
            foreach ( $dirs as $d ){
              if ( in_array($d, $empty_dirs, true) ){
                $tot--;
              }
            }
          }
          foreach ( $empty_dirs as $e ){
            $res[] = $e;
          }
          if ( !$tot ){
            $res[] = $dir;
          }
        }
      }
    }
    return $res;
  }

  private function _delete_empty_dirs($path, bool $hidden_is_empty = false): int
  {
    $num = 0;
    $all = $this->_get_items($path, 'both', !$hidden_is_empty);
    $tot = count($all);
    foreach ( $all as $dir ){
      if ( is_dir($dir) ){
        $num += $this->_delete_empty_dirs($dir, $hidden_is_empty);
      }
      if ( $num && !is_dir($dir) ){
        $tot--;
      }
    }
    if ( !$tot ){
      $this->_delete($path);
      $num++;
    }
    return $num;
  }

  private function _is_file(string $path)
  {
    if ( $this->mode === 'nextcloud' ){
      if ( !empty( $this->obj->propFind($this->get_real_path($path), ['{DAV:}getcontenttype'], 0) ) ){
        return true;
      }
      else {
        return false;
      }
    }
    else {
      return is_file($path);
    }
  }

  private function _is_dir(string $path)
  {
    if ( strpos($path, '/') !== 0 ){
      $path = $this->get_real_path($path);
    } 
    if ( $this->mode === 'nextcloud' ){
      if ( $this->exists($path) ){
        $tmp = $this->obj->propFind($path, ['{DAV:}getcontenttype'], 0 );

        if ( empty($tmp) ){
          return true;
        }
        else if ( !empty($tmp) ){
          return false;
        }
      }
      else {
        return false;
      }
    }
    else {
      return is_dir($path);
    }
  }

  /**
   * system constructor.
   * @param string $type
   * @param array $cfg
   */
  public function __construct(string $type  = 'local', array $cfg = [])
  {
   
    switch ( $type ){
      case 'ssh':
        if ( $this->_connect_ssh($cfg) ){
          $this->mode = 'ssh';
        }
        break;
      case 'ftp':
        if ( $this->_connect_ftp($cfg) ){
          $this->mode = 'ftp';
        }
        break;
      case 'nextcloud':
        if ( $this->_connect_nextcloud($cfg) ){
          $this->mode = 'nextcloud';
        }
        break;
      case 'local':
        $this->mode = 'local';
        $this->current = getcwd();
        break;
    }
  }

  /**
   * @param string $path
   * @return string
   */
  public function clean_path(string $path): string
  {
    if ( ($path === '.') || ($path === './') ){
      $path = '';
    }
    while ( $path && (substr($path, -1) === '/') ){
      $path = substr($path, 0, strlen($path) - 1);
    }
    return $path;
  }

  private function _get_real_path(string $path): string
  {
    $path = $this->clean_path($path);
    if ( $this->mode === 'nextcloud' ){
      if (  ( '/'.$path.'/' === $this->prefix ) ){
        return $this->prefix; 
      }
      else if ( strpos($path, $this->prefix) === 0 ) {
        return $path;
      }
      else {
        //die(var_dump($this->mode));
        return $this->prefix.$path;
      }
    }
    else {
      return $this->prefix.(
      strpos($path, '/') === 0 ?
        $path :
        (
          ($this->current ? $this->current.($path ? '/' : '') : '').
          (
          substr($path, -1) === '/' ?
            substr($path, 0, -1) :
            $path
          )
        )
      );
    }
  }

  /**
   * @param string $path
   * @return string
   */
  public function get_real_path(string $path): string
  {
    return $this->_get_real_path($path);
  }

  /**
   * @param string $file
   * @param bool $is_absolute
   * @return string
   */
  public function get_system_path(string $file, bool $is_absolute = true): string
  {
    // The full path without the obj prefix, and if it's not absolute we remove the initial slash
    
    if ( $this->mode ===  'nextcloud' ){
      return $file = substr($file, strlen($this->prefix) + ($is_absolute ? 0 : 1) -1 );  
    }
    $file = substr($file, strlen($this->prefix) + ($is_absolute ? 0 : 1));
    
    if ( !$is_absolute && isset($this->current) ){
      $file = substr($file, strlen($this->current));
    }
    return $file;
  }

  /**
   * @return null|string
   */
  public function get_mode(): ?string
  {
    return $this->mode;
  }

  /**
   * @return null|string
   */
  public function get_current(): ?string
  {
    return $this->current;
  }

  /**
   * @return string
   */
  public function get_obj(){
    return $this->obj;
  }

  /**
   * @param string|null $path
   * @param bool $including_dirs
   * @param bool $hidden
   * @param string|callable|null $filter
   * @param string $detailed
   * @return array|null
   */
  public function get_files(string $path = null, $including_dirs = false, $hidden = false, $filter = null, string $detailed = ''): ?array
  {
    if ( $this->check() && $this->is_dir($path) ){
      //die(var_dump($path));
      $is_absolute = strpos($path, '/') === 0;
      $fs =& $this;
      clearstatcache();
      $type = $including_dirs ? 'both' : 'file';
      return array_map(function($a)use($is_absolute, $fs, $detailed){
        if ( $detailed ){
          $a['path'] = $fs->get_system_path($a['path'], $is_absolute);
          return $a;
        }
        return $fs->get_system_path($a, $is_absolute);
      }, $this->_get_items($this->get_real_path($path), $filter ?: $type, $hidden, $detailed));
    }
    return null;
  }

  /**
   * @param string $path
   * @param bool $hidden
   * @param string $detailed
   * @return array|null
   */
  public function get_dirs(string $path = '', bool $hidden = false, string $detailed = ''): ?array
  {
    if ( $this->check() && $this->is_dir($path) ){
      $is_absolute = strpos($path, '/') === 0;
      $fs =& $this;
      clearstatcache();
      return array_map(function ($a) use ($is_absolute, $fs, $detailed){
        if ( $detailed ){
          $a['path'] = $fs->get_system_path($a['path'], $is_absolute);
          return $a;
        }
        return $fs->get_system_path($a, $is_absolute);
      }, $this->_get_items($this->get_real_path($path), 'dir', $hidden, $detailed));
    }
    return null;
  }

  /**
   * @param string $path
   * @return bool
   */
  /*public function cd(string $path): bool
  {
    if (
      $this->check() &&
      ($p = $this->get_real_path($path)) &&
      \is_dir($p)
    ){
      $this->current = $this->clean_path($path);
      return true;
    }
    return false;
  }*/
    public function cd(string $path): bool
  {
    if ($this->check()) {
      while ( strpos($path, '../') ===  0 ){
        $tmp = dirname($this->current);
        if ( $tmp !== $this->current ){
          $path = substr($path, 3);
        }
        else {
          break;
        }
      }
      if ( isset($tmp) ){
        $path = $tmp.$path;
      }
      if (($p = $this->get_real_path($path)) && \is_dir($p)) {
        $this->current = $this->clean_path($path);
        return true;
      }
    }
    return false;
  }


  /**
   * @param string $path
   * @return bool
   */
  public function exists(string $path): bool
  {
    if ( $this->check() ){
      clearstatcache();
      $file = $this->get_real_path($path);
      if ( $file ){
        return $this->_exists($file);
      }
    }
    return false;
  }

  /**
   * @param string $path
   * @return bool
   */
  public function is_file(string $path): bool
  {
    clearstatcache();
    return $this->check() && $this->_is_file($path);
  }

  /**
   * @param string $path
   * @return bool
   */
  public function is_dir(string $path): bool
  {
    clearstatcache();
    return $this->check() && $this->_is_dir($path);
  }

  /**
   * @param string $path
   * @param bool $hidden
   * @return array|null
   */
  public function scand(string $path, bool $hidden = false, string $detailed = ''): ?array
  {
    if ( $this->check() && $this->is_dir($path) ){
      clearstatcache();
      $is_absolute = strpos($path, '/') === 0;
      $fs =& $this;
      return array_map(function($a)use($is_absolute, $fs, $detailed){
        if ( $detailed ){
          $a['path'] = $fs->get_system_path($a['path'], $is_absolute);
          return $a;
        }
        return $fs->get_system_path($a, $is_absolute);
      }, $this->_scand($this->get_real_path($path), $hidden, $detailed));
    }
    return null;
  }

  /**
   * @param string $path
   * @param string|callable|null $filter
   * @param bool $hidden
   * @param string $detailed
   * @return array|null
   */
  public function scan(string $path = '', $filter = null, bool $hidden = false, string $detailed = ''): ?array
  {
    if ( $this->check() && $this->is_dir($path) ){
      clearstatcache();
      $is_absolute = strpos($path, '/') === 0;
      $fs =& $this;
      return array_map(function($a)use($is_absolute, $fs, $detailed){
        if ( $detailed ){
          $a['path'] = $fs->get_system_path($a['path'], $is_absolute);
          return $a;
        }
        return $fs->get_system_path($a, $is_absolute);
      }, $this->_scan($this->get_real_path($path), $filter, $hidden, $detailed));
    }
    return null;
  }

  /**
   * @param string $dir
   * @param int $chmod
   * @return bool|null
   */
  public function create_path(string $dir, int $chmod = 0755): ?bool
  {
    if ( $this->check() ){
      if ( !($real = $this->get_real_path($dir)) ){
        return false;
      }
      clearstatcache();
      return $this->_mkdir($real, $chmod, true);
    }
    return null;
  }

  /**
   * @param string $dir
   * @param int $chmod
   * @param bool $recursive
   * @return bool|null
   */
  public function mkdir(string $dir, int $chmod = 0755, $recursive = false): ?bool
  {
    if ( $this->check() ){
      if ( !$dir ){
        return false;
      }
      clearstatcache();
      $real = $this->get_real_path($dir);
      return $this->_mkdir($real, $chmod, $recursive);
    }
    return null;
  }

  /**
   * @param string $file
   * @param string $content
   * @param bool $append
   * @return bool
   */
  public function put_contents(string $file, string $content, bool $append = false): bool
  {
    $path = dirname($file);
    if ( $this->check() && $this->is_dir($path) ){
      $real = $this->get_real_path($path).'/';
      if ( $append && $this->exists($file) ){
        return (bool)file_put_contents($real.basename($file), $content, FILE_APPEND);
      }
      else{
        return (bool)file_put_contents($real.basename($file), $content);
      }
    }
    return false;
  }

  private function _get_contents(string $file):? string
  {
     if ( $this->check() && $this->exists($file) ){
      $real = $this->get_real_path($file);
      if ( $this->mode === 'nextcloud' ){
        if ( $res = $this->obj->request('GET') ){
          return $res['body'];
        }
      }
      return file_get_contents($real);
    }
    return null;
  }
  /**
   * @param string $file
   * @return null|string
   */
  public function get_contents(string $file):? string
  {
    return $this->_get_contents($file);
  }

  /**
   * @param string $file
   * @param bool $full
   * @return bool
   */
  public function delete(string $file, bool $full = true): bool
  {
    if ( $this->check() && $this->exists($file) ){
      return $this->_delete($this->get_real_path($file), $full);
    }
    return false;
  }

  /**
   * @param string $source
   * @param string $dest
   * @param bool $overwrite
   * @param system|null $fs
   * @return bool
   */
  public function copy(string $source, string $dest, bool $overwrite = false, system $fs = null): bool
  {
    if ( $this->check() ){
      $nfs =& $this;
      if ( $fs ){
        if ( !$fs->check() ){
          return false;
        }
        $nfs =& $fs;
      }
     
      if ( $this->exists($source) && $nfs->exists(dirname($dest)) ){
        if ( $nfs->exists($dest) ){
          $dest_is_dir = $nfs->is_dir($dest);
          if ( $dest_is_dir && $this->is_file($source) ){
            $dest .= '/'.basename($source);
          }
          else if (
            (!$dest_is_dir && !$overwrite) ||
            ($dest_is_dir && (count($nfs->get_files($dest, true, true)) > 0) && !$overwrite)
          ){
            return false;
          }
          else{
            $nfs->delete($dest);
          }
        }
        return $this->_copy($this->get_real_path($source), $nfs->get_real_path($dest));
      }
    }
    return false;
  }

  /**
   * @param string $file
   * @param $name
   * @param bool $overwrite
   * @return bool
   */
  public function rename(string $file, $name, bool $overwrite = false): bool
  {
    if ( $this->exists($file) && (strpos($name, '/') === false) ){
      $path = $this->get_real_path(dirname($file));
      if ( $this->_exists($path.'/'.$name) && ( !$overwrite || !$this->_delete($path.'/'.$name)) ){
        return false;
      }
      return $this->_rename($path.'/'.basename($file), $path.'/'.$name);
    }
    return false;
  }

  /**
   * @param string $source
   * @param string $dest
   * @param bool $overwrite
   * @param system|null $fs
   * @return bool
   */
  public function move(string $source, string $dest, bool $overwrite = false, system $fs = null): bool
  {
    if ( $this->check() && $this->exists($source) ){
      $name = basename($source);
      if ( $fs ){
        if (
          $fs->check() &&
          $fs->is_dir($dest) &&
          $this->copy($source, $dest.'/'.$name, $overwrite, $fs) &&
          $this->delete($source)
        ){
          return true;
        }
      }
      else if ( $this->is_dir($dest) ){
        if ( $this->exists($dest.'/'.$name) && (!$overwrite || !$this->delete($dest.'/'.$name)) ){
          return false;
        }
        return $this->_rename($this->get_real_path($source), $this->get_real_path($dest.'/'.$name));
      }
    }
    return false;
  }

  /**
   * @param string $file
   * @return bbn\file|null
   */
  public function get_file(string $file): ?bbn\file
  {
    if ( $this->check() && $this->is_file($file) ){
      return new bbn\file($this->get_real_path($file));
    }
    return null;
  }

  private function _download($file, $dest = '', $f = false )
  {
    if ( $this->mode === 'nextcloud' ){
      /** if  is a file*/
      if ( $f === true ){
        if ( $res = $this->obj->request('GET') ){
          tempnam(sys_get_temp_dir());
          if ( empty($this->exists($dest)) ){
            mkdir($dest, 0755, false);
          }
          $f = fopen($dest.$file, 'w');
          if ( fwrite($f, $res['body']) ){
            return fclose($f);  
          }
        }
      }
      /** if is a folder */
      else {
        $items = $this->_propfind_deep($file, ['{DAV:}getcontenttype','{DAV:}resourcetype'], 'infinity' );
        $files = array_filter($items, function($a){
          return $a['dir'] === false;
        });
        $dirs = array_filter($items, function($a){
          return $a['dir'] === true;
        });
        if ( empty($this->exists($dest)) ){
          mkdir($dest, 0755, false);
          foreach ( $dirs as $i => $d ){
            $dirs[$i]['path'] = str_replace($this->prefix, '', $d['path']);
            if( empty($this->exists($dest.$dirs[$i]['path'])) ){
              mkdir($dest.$dirs[$i]['path'], 0755, false);
            }
          }
          foreach ( $files as $i => $d ){
            $files[$i]['path'] = str_replace($this->prefix, '', $d['path']);
            if ( empty($this->exists($dest.$files[$i]['path'])) ){
              if ( $res = $this->obj->request('GET', $d['path']) ){
                $f = fopen($dest.$files[$i]['path'], 'w');
                if ( fwrite($f, $res['body']) ){
                  fclose($f);  
                }  
              }
            }
          }
        }
      }
      /*
      to delete the downloaded file
      $dav->request('DELETE', $file);
      */
    }
    else {
      if ( ($f = $this->get_file($file)) && $f->check() ){
        $f->download();
      }
    }
  }

  /**
   * @param $file
   */
  public function download($file, $dest = '', $f = false)
  {
    return $this->_download($file, $dest, $f);
  }

  public function filesize($path): ?int
  {
    if ( $this->is_file($path) ){
      return $this->_filesize($this->get_real_path($path));
    }
    return null;
  }
  
  private function _filesize($path):? int
  {
    if ( $this->mode === 'nextcloud' ){
      $size = $this->obj->propFind($path, [
        '{DAV:}getcontentlength'
      ]);
      if ( isset($size['{DAV:}getcontentlength']) ){
        return $size['{DAV:}getcontentlength'];
      }
    }
    else {
      return filesize($path);
    }
    return null;
  }
  
  public function filemtime($path)
  {
    return $this->_filemtime($this->get_real_path($path));
  }

  private function _filemtime($path)
  {
    if ( $this->mode === 'nextcloud' ){
      $mtime = $this->obj->propFind($path, [
        '{DAV:}getlastmodified'
      ]);
      if ( !empty($mtime['{DAV:}getlastmodified']) ){
        return $mtime['{DAV:}getlastmodified'];
      }
    }
    else {
      return filemtime($path);
    }
  }
 
  private function _dirsize($path): int
  {
    $tot = 0;
    foreach ( $this->_get_items($path, 'file', true) as $f ){
      $tot += $this->filesize($f);
    }
    foreach ( $this->_get_items($path, 'dir', true) as $d ){
      $tot += $this->_dirsize($d);
    }
    return $tot;
  }

  public function dirsize($path): ?int
  {
    if ( $this->check() ){
      $rpath = $this->get_real_path($path);
      if ( $this->is_dir($rpath) ){
        return $this->_dirsize($rpath);
      }
    }
    return null;
  }

  public function get_empty_dirs($path, bool $hidden_is_empty = false): array
  {
    $res = [];
    if ( $this->is_dir($path) ){
      foreach ( $this->get_dirs($path) as $d ){
        if ( $rs = $this->_get_empty_dirs($this->get_real_path($d), $hidden_is_empty) ){
          foreach ( $rs as $r ){
            $res[] = $this->get_system_path($r);
          }
        }
      }
    }
    return $res;
  }

  public function delete_empty_dirs($path, bool $hidden_is_empty = false): int
  {
    $num = 0;
    if ( $this->is_dir($path) ){
      foreach ( $this->get_dirs($path) as $d ){
        $num += $this->_delete_empty_dirs($this->get_real_path($d), $hidden_is_empty);
      }
    }
    return $num;
  }
}