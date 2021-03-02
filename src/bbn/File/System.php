<?php
/**
 * @category File
 * @package bbn
 * @author Thomas Nabet <thomas.nabet@gmail.com>
 * @license MIT
 * @link https://php.bbn.io
 *
 * //$encodings = ['UTF-8', 'WINDOWS-1252', 'ISO-8859-1', 'ISO-8859-15'];
 * $enc = mb_detect_encoding($f, $encodings);
 * if ( $enc !== 'UTF-8' ){
 *   $f = html_entity_decode(htmlentities($f, ENT_QUOTES, $enc), ENT_QUOTES , 'UTF-8');
 * }
 *
 */

namespace bbn\File;

use bbn;
use bbn\X;

/**
 * Class system
 * @package bbn\File
 */
class System extends bbn\Models\Cls\Basic
{

  /**
   * @var mixed The connection stream only if it is different from the original connection
   */
  private $cn = '';

  private $error_stream;

  /**
   * @var mixed The connection stream
   */
  private $obj = '';

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
   * system constructor.
   * @param string $type
   * @param array  $cfg
   */
  public function __construct(string $type  = 'local', array $cfg = [])
  {
    switch ($type){
      case 'ssh':
        if ($this->_connect_ssh($cfg)) {
          $this->mode   = 'ssh';
          $this->prefix = 'ssh2.sftp://'.$this->obj;
        }
        break;
      case 'ftp':
        if ($this->_connect_ftp($cfg)) {
          $this->mode   = 'ftp';
          $this->prefix = 'ftp://'.$cfg['user'].':'.$this->_get_password($cfg).'@'.$cfg['host'].'/';
        }
        break;
      case 'nextcloud':
        if (isset($cfg['host'], $cfg['user'], $cfg['pass'])) {
          $this->mode = 'nextcloud';
          $this->obj  = new \bbn\Api\Nextcloud($cfg);
        }
        break;
      case 'local':
        $this->mode    = $type;
        $this->current = getcwd();
        break;
    }

    if (empty($this->mode)) {
      $this->error = X::_("Impossible to connect to the SSH server");
    }
  }


  public function __destruct()
  {
    if ($this->mode === 'ssh') {
      @fclose($this->cn);
    }
  }


  /**
   * @param string $path
   * @return string
   */
  public function cleanPath(string $path): string
  {
    if (($path === '.') || ($path === './')) {
      $path = '';
    }

    while ($path && (substr($path, -1) === '/')){
      $path = substr($path, 0, strlen($path) - 1);
    }

    return $path;
  }


  /**
   * @param string $path
   * @return string
   */
  public function getRealPath(string $path): string
  {
    $path = $this->cleanPath($path);
    if ($this->mode === 'nextcloud') {
      return $this->obj->getRealPath($path);
    }
    else {
      return $this->prefix.(
      strpos($path, '/') === 0 ? $path : (
          ($this->current ? $this->current.($path ? '/' : '') : '').
          (
          substr($path, -1) === '/' ? substr($path, 0, -1) : $path
          )
        )
      );
    }
  }


  /**
   * @param string $file
   * @param bool   $is_absolute
   * @return string
   */
  public function getSystemPath(string $file, bool $is_absolute = true): string
  {
    // The full path without the obj prefix, and if it's not absolute we remove the initial slash
    if ($this->mode === 'nextcloud') {
      $file = $this->obj->getSystemPath($file, $is_absolute);
    }
    else {
      $file = substr($file, strlen($this->prefix) + ($is_absolute ? 0 : 1));
      if (!$is_absolute && $this->current) {
        $file = substr($file, strlen($this->current));
      }
    }

    return $file;
  }


  /**
   * @return null|string
   */
  public function getMode(): ?string
  {
    return $this->mode;
  }


  /**
   * @return null|string
   */
  public function getCurrent(): ?string
  {
    return $this->current;
  }


  /**
   * @return string
   */
  public function getObj()
  {
    return $this->obj;
  }


  /**
   * @param string|null          $path
   * @param bool                 $including_dirs
   * @param bool                 $hidden
   * @param string|callable|null $filter
   * @param string               $detailed
   * @return array|null
   */
  public function getFiles(string $path = null, $including_dirs = false, $hidden = false, $filter = null, string $detailed = ''): ?array
  {
    if ($this->check() && $this->isDir($path)) {
      if ($this->mode !== 'nextcloud') {
        $is_absolute = strpos($path, '/') === 0;
        $fs          =& $this;
        clearstatcache();
        $type = $including_dirs ? 'both' : 'file';
        return array_map(
          function ($a) use ($is_absolute, $fs, $detailed) {
            if ($detailed) {
              $a['name'] = $fs->getSystemPath($a['name'], $is_absolute);
              return $a;
            }

            return $fs->getSystemPath($a, $is_absolute);
          }, $this->_get_items($this->getRealPath($path), $filter ?: $type, $hidden, $detailed)
        );
      }
      else {
        return $this->obj->getFiles($path, $including_dirs, $hidden, $filter, $detailed);
      }
    }

    return null;
  }


  /**
   * @param string $path
   * @param bool   $hidden
   * @param string $detailed
   * @return array|null
   */
  public function getDirs(string $path = '', bool $hidden = false, string $detailed = ''): ?array
  {
    if ($this->check() && $this->isDir($path)) {
      $is_absolute = strpos($path, '/') === 0;
      $fs          =& $this;
      clearstatcache();
      return array_map(
        function ($a) use ($is_absolute, $fs, $detailed) {
          if ($detailed) {
            $a['name'] = $fs->getSystemPath($a['name'], $is_absolute);
            return $a;
          }

          return $fs->getSystemPath($a, $is_absolute);
        }, $this->_get_items($this->getRealPath($path), 'dir', $hidden, $detailed)
      );
    }

    return null;
  }


  /**
   * @todo Nextcloud
   * @param string $path
   * @return bool
   */
  public function cd(string $path): bool
  {
    if ($this->check()) {
      while (strpos($path, '../') === 0){
        $tmp = dirname($this->current);
        if ($tmp !== $this->current) {
          $path = substr($path, 3);
        }
        else {
          break;
        }
      }

      if (isset($tmp)) {
        $path = $tmp.$path;
      }

      if (($p = $this->getRealPath($path)) && \is_dir($p)) {
        $this->current = $this->cleanPath($path);
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
    if ($this->check()) {
      clearstatcache();
      $file = $this->getRealPath($path);
      if ($file) {
        return $this->_exists($file);
      }
    }

    return false;
  }


  /**
   * @param string $path
   * @return bool
   */
  public function isFile(string $path): bool
  {
    return $this->check() && $this->_is_file($this->getRealPath($path));
  }


  /**
   * @param string $path
   * @return bool
   */
  public function isDir(string $path): bool
  {
    return $this->check() && $this->_is_dir($this->getRealPath($path));
  }


  /**
   * @param string $path
   * @param bool   $hidden
   * @return array|null
   */
  public function scand(string $path, bool $hidden = false, string $detailed = ''): ?array
  {
    if ($this->check() && $this->isDir($path)) {
      clearstatcache();
      $is_absolute = strpos($path, '/') === 0;
      $fs          =& $this;
      return array_map(
        function ($a) use ($is_absolute, $fs, $detailed) {
          if ($detailed) {
            $a['name'] = $fs->getSystemPath($a['name'], $is_absolute);
            return $a;
          }

          return $fs->getSystemPath($a, $is_absolute);
        }, $this->_scand($this->getRealPath($path), null, $hidden, $detailed)
      );
    }

    return null;
  }


  /**
   * @param string               $path
   * @param string|callable|null $filter
   * @param bool                 $hidden
   * @param string               $detailed
   * @return array|null
   */
  public function scan(string $path = '', $filter = null, bool $hidden = false, string $detailed = ''): ?array
  {
    if ($this->check() && $this->isDir($path)) {
      clearstatcache();
      $is_absolute = strpos($path, '/') === 0;
      $fs          =& $this;
      return array_map(
        function ($a) use ($is_absolute, $fs, $detailed) {
          if ($detailed) {
            $a['name'] = $fs->getSystemPath($a['name'], $is_absolute);
            return $a;
          }

          return $fs->getSystemPath($a, $is_absolute);
        }, $this->_scan($this->getRealPath($path), $filter, $hidden, $detailed)
      );
    }

    return null;
  }


  /**
   * @param string               $path
   * @param string|callable|null $filter
   * @param bool                 $hidden
   * @param string               $detailed
   * @return array|null
   */
  public function rscan(string $path = '', $filter = null, bool $hidden = false, string $detailed = ''): ?array
  {
    if ($this->check() && $this->isDir($path)) {
      clearstatcache();
      $is_absolute = strpos($path, '/') === 0;
      $fs          =& $this;
      return array_map(
        function ($a) use ($is_absolute, $fs, $detailed) {
          if ($detailed) {
            $a['name'] = $fs->getSystemPath($a['name'], $is_absolute);
            return $a;
          }

          return $fs->getSystemPath($a, $is_absolute);
        }, $this->_rscan($this->getRealPath($path), $filter, $hidden, $detailed)
      );
    }

    return null;
  }


  /**
   * @param string $dir
   * @param int    $chmod
   * @return bool|null
   */
  public function createPath(string $dir, int $chmod = 0755): ?string
  {
    if ($this->check()) {
      if (!($real = $this->getRealPath($dir))) {
        return false;
      }

      clearstatcache();
      if ($this->_mkdir($real, $chmod, true) || $this->_is_dir($real)) {
        return $this->getSystemPath($real);
      }
    }

    return null;
  }


  /**
   * @param string $dir
   * @param int    $chmod
   * @param bool   $recursive
   * @return bool|null
   */
  public function mkdir(string $dir, int $chmod = 0755, $recursive = false): ?bool
  {
    if ($this->mode === 'nextcloud') {
      return $this->obj->mkdir($dir);
    }

    if ($this->check()) {
      if (!$dir) {
        return false;
      }

      clearstatcache();
      $real = $this->getRealPath($dir);
      return $this->_mkdir($real, $chmod, $recursive);
    }

    return null;
  }


  /**
   * @todo Nextcloud
   * @param string $file
   * @param string $content
   * @param bool   $append
   * @return bool
   */
  public function putContents(string $file, string $content, bool $append = false): bool
  {
    $path = dirname($file);
    if ($this->check() && $this->isDir($path)) {
      $real = $this->getRealPath($path).'/';
      if ($append && $this->exists($file)) {
        return (bool)file_put_contents($real.basename($file), $content, FILE_APPEND);
      }
      else{
        return (bool)file_put_contents($real.basename($file), $content);
      }
    }

    return false;
  }


  /**
   * @param string $file
   * @return null|string
   */
  public function getContents(string $file):? string
  {
    if ($this->check() && $this->exists($file)) {
      if ($this->mode === 'nextcloud') {
        return $this->obj->getContents($file);
      }
      else{
        $real = $this->getRealPath($file);
        return file_get_contents($real);
      }
    }

    return null;
  }


  /**
   * @param string $file
   * @return null|string
   */
  public function decodeContents(string $file, $decoder = null, $as_array = false)
  {
    if ($c = $this->getContents($file)) {
      if (is_callable($decoder)) {
        return $decoder($c);
      }
      else {
        $encoding = false;
        if (!$decoder) {
          $encoding = bbn\Str::fileExt($file);
        }
        elseif (is_string($decoder)) {
          $encoding = $decoder;
        }

        switch ($encoding) {
          case 'json':
            return json_decode($c, $as_array);
          case 'yml':
          case 'yaml':
            return yaml_parse($c);
          default:
            return unserialize($c);
        }
      }
    }

    return null;
  }


  /**
   * @param string $file
   * @param bool   $full
   * @return bool
   */
  public function delete(string $file, bool $full = true): bool
  {
    if ($this->check() && $this->exists($file)) {
      return $this->_delete($this->getRealPath($file), $full);
    }

    return false;
  }


  /**
   * @param string      $source
   * @param string      $dest
   * @param bool        $overwrite
   * @param system|null $fs
   * @return bool
   */
  public function copy(string $source, string $dest, bool $overwrite = false, System $fs = null): bool
  {
    if ($this->check()) {
      if ($this->mode !== 'nextcloud') {
        $nfs =& $this;
        if ($fs) {
          if (!$fs->check()) {
            return false;
          }

          $nfs =& $fs;
        }

        if ($this->exists($source) && $nfs->exists(dirname($dest))) {
          if ($nfs->exists($dest)) {
            $dest_is_dir = $nfs->isDir($dest);
            if ($dest_is_dir && $this->isFile($source)) {
              $dest .= '/'.basename($source);
            }
            elseif ((!$dest_is_dir && !$overwrite)
                || ($dest_is_dir && (count($nfs->getFiles($dest, true, true)) > 0) && !$overwrite)
            ) {
              return false;
            }
            else{
              $nfs->delete($dest);
            }
          }

          return $this->_copy($this->getRealPath($source), $nfs->getRealPath($dest));
        }
      }
      else {
        $this->obj->copy($source, $dest);
      }
    }

    return false;
  }


  /**
   * @param string $file
   * @param $name
   * @param bool   $overwrite
   * @return bool
   */
  public function rename(string $file, $name, bool $overwrite = false): bool
  {
    if ($this->exists($file) && (strpos($name, '/') === false)) {
      $path = $this->getRealPath(dirname($file));
      if ($this->_exists($path.'/'.$name) && (!$overwrite || !$this->_delete($path.'/'.$name))) {
        return false;
      }

      return $this->_rename($path.'/'.basename($file), $path.'/'.$name);
    }

    return false;
  }


  /**
   *
   * @param string      $source
   * @param string      $dest
   * @param bool        $overwrite
   * @param system|null $fs
   * @return bool
   */
  public function move(string $source, string $dest, bool $overwrite = false, System $fs = null): bool
  {
    if ($this->check() && $this->exists($source)) {
      $name = basename($source);
      if ($fs) {
        if ($fs->check()
            && $fs->isDir($dest)
            && $this->copy($source, $dest.'/'.$name, $overwrite, $fs)
            && $this->delete($source)
        ) {
          return true;
        }
      }
      elseif ($this->isDir($dest)) {
        if ($this->exists($dest.'/'.$name) && (!$overwrite || !$this->delete($dest.'/'.$name))) {
          return false;
        }

        return $this->_rename($this->getRealPath($source), $this->getRealPath($dest.'/'.$name));
      }
    }

    return false;
  }


  /**
   * @param string $file
   * @return bbn\File|null
   */
  public function getFile(string $file): ?bbn\File
  {
    if ($this->check()) {
      if ($this->mode === 'nextcloud') {
        return $this->obj->getFile($file);
      }

      if ($this->isFile($file)) {
        return new bbn\File($this->getRealPath($file));
      }
    }

    return null;
  }


  public function download($file)
  {
    return $this->_download($this->getRealPath($file));
  }


  public function filemtime($path)
  {
    return $this->_filemtime($this->getRealPath($path));
  }


  public function filesize($path): ?int
  {
    return $this->_filesize($this->getRealPath($path));
  }


  private function _dirsize($path): int
  {
    $tot = 0;
    foreach ($this->_get_items($path, 'file', true) as $f){
      $tot += $this->filesize($f);
    }

    foreach ($this->_get_items($path, 'dir', true) as $d){
      $tot += $this->_dirsize($d);
    }

    return $tot;
  }


  public function dirsize($path): ?int
  {
    if ($this->check()) {
      $rpath = $this->getRealPath($path);

      if ($this->mode !== 'nextcloud') {
        if ($this->_is_dir($rpath)) {
          return $this->_dirsize($rpath);
        }
      }
      else{
        return $this->obj->getSize($rpath);
      }
    }
  }


  public function getEmptyDirs($path, bool $hidden_is_empty = false): array
  {
    $res = [];
    if ($this->isDir($path)) {
      foreach ($this->getDirs($path) as $d){
        if ($rs = $this->_get_empty_dirs($this->getRealPath($d), $hidden_is_empty)) {
          foreach ($rs as $r){
            $res[] = $this->getSystemPath($r);
          }
        }
      }
    }

    return $res;
  }

  public function deleteEmptyDirs($path, bool $hidden_is_empty = false): int
  {
    $num = 0;
    if ($this->isDir($path)) {
      foreach ($this->getDirs($path) as $d){
        $num += $this->_delete_empty_dirs($this->getRealPath($d), $hidden_is_empty);
      }
    }

    return $num;
  }


  /**
   * @todo nextcloud
   *
   * @param string  $search
   * @param string  $path
   * @param boolean $deep
   * @param boolean $hidden
   * @param string  $filter
   * @return array|null
   */
  public function searchContents($search, $path, $deep = false, $hidden = false, $filter = 'file'): ?array
  {
    $res = [];
    if ($this->isDir($path)) {
      $files = $deep ? $this->scan($path, $filter) : $this->getFiles($path, false, $hidden, $filter);
      foreach ($files as $f) {
        $r = $this->searchContents($search, $f);
        if (count($r)) {
          if (is_array($search)) {
            foreach ($r as $s => $found) {
              if (count($found)) {
                if (!isset($res[$s])) {
                  $res[$s] = [];
                }

                $res[$s][$f] = $found;
              }
            }
          }
          else {
            $res[$f] = $r;
          }
        }
      }

      return $res;
    }
    elseif ($this->isFile($path)) {
      $content = $this->getContents($path);
      if (is_array($search)) {
        foreach ($search as $s) {
          $idx     = 0;
          $res[$s] = [];
          while (($n = X::indexOf($content, $s, $idx)) > -1) {
            $res[$s][] = $n;
            $idx       = $n + strlen($s);
          }
        }
      }
      else {
        $idx = 0;
        while (($n = X::indexOf($content, $search, $idx)) > -1) {
          $res[] = $n;
          $idx   = $n + 1;
        }
      }

      return $res;
    }

    return null;

  }


  /**
   * Replaces search with replace in the given content.
   * @todo nextcloud
   *
   * @param string|array $search
   * @param string|array $replace
   * @param string|array $path
   * @param boolean      $deep
   * @param boolean      $hidden
   * @param string       $filter
   * @return array|null
   */
  public function replaceContents($search, $replace, $path, $deep = false, $hidden = false, $filter = 'file'): int
  {
    if (\is_array($replace)) {
      if (!\is_array($search) || (count($replace) !== count($search))) {
        throw new \Exception(X::_("If replace is an array, search must be an array of equal length"));
      }

      $replace_array = true;
    }
    $res = 0;
    if ($this->isDir($path)) {
      $files = $deep ? $this->scan($path, $filter) : $this->getFiles($path, false, $hidden, $filter);
      foreach ($files as $f) {
        $res += $this->replaceContents($search, $replace, $f);
      }

      return $res;
    }
    elseif ($this->isFile($path)) {
      $content = $this->getContents($path);
      $changed = false;
      if (is_array($search)) {
        foreach ($search as $idx => $s) {
          if (X::indexOf($content, $s) === -1) {
            continue;
          }

          $changed = true;
          $content = str_replace($s, $replace_array ? $replace[$idx] : $replace, $content);
        }
      }
      elseif (X::indexOf($content, $search) > -1) {
        $changed = true;
        $content = str_replace($search, $replace, $content);
      }
      if ($changed) {
        $this->putContents($path, $content);
        return 1;
      }

      return $res;
    }

    return 0;

  }


  public function getNumFiles($path)
  {
    if (($s = $this->scan($path))) {
      return count($s);
    }

    return 0;
  }


  public function upload(array $files,string $path) :bool
  {
    $success = false;
    if ($this->mode === 'nextcloud') {
      return $this->obj->upload($files, $path);
    }
    elseif ($this->mode === 'local') {
      return $this->_upload($files, $path);
    }
    else {
      $success = false;
    }

    return $success;
  }


  public function getTree(string $dir,  string $exclude = '', $only_dir = false,  $filter = null,  $hidden = false)
  {
    $r    = [];
    $dirs = self::getDirs($dir, $hidden);
    if (\is_array($dirs)) {
      foreach ($dirs as $d){
        if (basename($d) !== $exclude) {
          $x        = [
            'name' => $d,
            'type' => 'dir',
            'num' => 0,
            'items' => self::getTree($d, $exclude, $only_dir, $filter, $hidden)
          ];
          $x['num'] = \count($x['items']);
          if (empty($x['items'])) {
            unset($x['items']);
          }
        }

        if (!empty($x['items']) || $this->_check_filter($x, $filter)) {
          $r[] = $x;
        }
      }

      if (!$only_dir) {
        $files = self::getFiles($dir, false, $hidden);

        foreach ($files as $f){
          $x = [
            'name' => $f,
            'type' => 'file',
            'ext' => bbn\Str::fileExt($f)
          ];
          if ($this->_check_filter($x, $filter)) {
            $r[] = $x;
          }
        }
      }
    }

    return $r;
  }


  private function _get_password(array $cfg)
  {
    if (isset($cfg['pass'])) {
      if (!empty($cfg['encrypted'])) {
        if ($tmp = base64_decode($cfg['pass'])) {
          return \bbn\Util\Enc::decrypt($tmp, $cfg['encryption_key'] ?? '');
        }
      }

      return $cfg['pass'];
    }

    return null;
  }


  /**
   * Connect to a Nextcloud instance
   * @param array $cfg
   * @return bool
   */
  private function _connect_nextcloud(array $cfg): bool
  {
    if (isset($cfg['host'], $cfg['user'], $cfg['pass']) && class_exists('\\Sabre\\DAV\\Client')) {
      $this->prefix = '/remote.php/webdav/';
      $this->obj    = new \Sabre\DAV\Client(
        [
        'baseUri' => 'http'.(isset($cfg['port']) && ($cfg['port'] === 21) ? '' : 's').'://'.$cfg['host'].$this->prefix.$cfg['name'],
        'userName' => $cfg['user'],
        'password' => $this->_get_password($cfg)
        ]
      );
      $this->host   = 'http'.(isset($cfg['port']) && ($cfg['port'] === 21) ? '' : 's').'://'.$cfg['host'];
      if ($this->obj->options()) {
        $this->current = '';
        return true;
      }

      $this->error = X::_('Impossible to connect to the WebDAV host');
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
    if (isset($cfg['host'], $cfg['user'], $cfg['pass'])) {
      $args = [$cfg['host'], $cfg['port'] ?? 21, $cfg['timeout'] ?? $this->timeout];
      try {
        $this->obj = ftp_ssl_connect(...$args);
      }
      catch (\Exception $e){
        $this->error  = X::_('Impossible to connect to the FTP host through SSL');
        $this->error .= PHP_EOL.$e->getMessage();
      }

      if (!$this->obj) {
        try {
          $this->obj = ftp_connect(...$args);
        }
        catch (\Exception $e){
          $this->error  = X::_('Impossible to connect to the FTP host');
          $this->error .= PHP_EOL.$e->getMessage();
        }
      }

      if ($this->obj) {
        if (!@ftp_login($this->obj, $cfg['user'], $this->_get_password($cfg))) {
          $this->error  = X::_('Impossible to login to the FTP host');
          $this->error .= PHP_EOL.error_get_last()['message'];
        }
        else{
          $this->current = ftp_pwd($this->obj);
          if (!empty($cfg['passive'])
              || (defined('BBN_SERVER_NAME') && !@fsockopen(BBN_SERVER_NAME, $args[1]))
          ) {
            ftp_pasv($this->obj, true);
            stream_set_chunk_size($this->obj, 1024 * 1024);
          }

          return true;
        }
      }
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
    if (isset($cfg['host'])) {
      $param = [];
      // Keys as parans
      if (isset($cfg['public'], $cfg['private'])) {
        $param['hostkey'] = 'ssh-rsa';
      }

      $this->cn = @ssh2_connect(
        $cfg['host'], $cfg['port'] ?? 22, $param/*, [
        'debug' => function($message, $language, $always_display){
        X::log([$message, $language, $always_display], 'connect_ssh_debug');
        },
        'disconnect' => function($reason, $message, $language){
        X::log([$reason, $message, $language], 'connect_ssh_disconnect');
        }
        ]*/
      );
      if (!$this->cn) {
        $this->error = X::_("Could not connect through SSH.");
      }
      elseif (X::hasProps($cfg, ['user', 'public', 'private'], true)) {
        stream_set_blocking($this->cn, true);
        stream_set_chunk_size($this->cn, 1024 * 1024);
        /*
        $fingerprint = ssh2_fingerprint($this->cn, SSH2_FINGERPRINT_MD5 | SSH2_FINGERPRINT_HEX);
        if ( strcmp($this->ssh_server_fp, $fingerprint) !== 0 ){
          $this->error = X::_('Unable to verify server identity!');
        }
        */
        if (!ssh2_auth_pubkey_file($this->cn, $cfg['user'], $cfg['public'], $cfg['private'], $this->_get_password($cfg))) {
          $this->error = X::_('Authentication rejected by server');
        }
        else {
          try {
            $this->obj = ssh2_sftp($this->cn);
          }
          catch (\Exception $e) {
            $this->error = X::_("Could not connect through SFTP.");
          }

          if ($this->obj) {
            $this->current = ssh2_sftp_realpath($this->obj, '.');
            return true;
          }
        }
      }
      elseif (X::hasProps($cfg, ['user', 'pass'], true)) {
        try {
          ssh2_auth_password($this->cn, $cfg['user'], $this->_get_password($cfg));
        }
        catch (\Exception $e) {
          $this->error = X::_("Could not authenticate with username and password.");
        }
        if (!$this->error) {
          try {
            $this->obj = @ssh2_sftp($this->cn);
          }
          catch (\Exception $e) {
            $this->error = X::_("Could not initialize SFTP subsystem.");
          }

          if ($this->obj) {
            $this->current = ssh2_sftp_realpath($this->obj, '.');
            return true;
          }
        }
      }
    }

    return false;
  }


  /**
   * Checks if the given files name ends with the given suffix string
   *
   * @todo Nextcloud
   * @param array|string    $item
   * @param callable|string $filter
   * @return bool
   */
  private function _check_filter($item, $filter): bool
  {
    if ($filter && $item) {
      $name = \is_array($item) ? ($item['name'] ?? null) : $item;

      if (empty($name)) {
        throw new \Exception(X::_("There is no item to chek the filter against"));
      }

      if (is_string($filter)) {
        if ($filter === 'file') {
          return $this->_is_file($name);
        }

        if ($filter === 'dir') {
          return $this->_is_dir($name);
        }

        if ($filter === 'both') {
          return true;
        }

        $extensions = array_map(
          function ($a) {
            if (substr($a, 0, 1) !== '.') {
              $a = '.'.$a;
            }

            return strtolower($a);
          }, X::split($filter, '|')
        );
        foreach ($extensions as $ext) {
          if (strtolower(substr($name, - strlen($ext))) === $ext) {
            return true;
          }
        }

        return false;
      }
      elseif (is_callable($filter)) {
        return $filter($item);
      }
    }

    return true;
  }


  /**
   * Raw function returning the elements contained in the given directory
   * @param string          $path
   * @param string|callable $type
   * @param bool            $hidden
   * @param string          $detailed
   * @return array
   */
  private function _get_items(string $path, $type = 'both', bool $hidden = false, string $detailed = ''): array
  {
    if ($this->mode !== 'nextcloud') {
      $files        = [];
      $dirs         = [];
      $has_size     = stripos((string)$detailed, 's') !== false;
      $has_type     = stripos((string)$detailed, 't') !== false;
      $has_mod      = stripos((string)$detailed, 'm') !== false;
      $has_children = stripos((string)$detailed, 'c') !== false;
      $has_ext      = stripos((string)$detailed, 'e') !== false;
      if (($this->mode === 'ftp') && ($detailed || ($type !== 'both'))) {
        if ($fs = ftp_mlsd($this->obj, substr($path, strlen($this->prefix)))) {
          foreach ($fs as $f){
            if (($f['name'] !== '.') && ($f['name'] !== '..') && ($hidden || (strpos(basename($f['name']), '.') !== 0))) {
              if ($this->_check_filter($f['name'], $type)) {
                if ($detailed) {
                  $tmp = [
                    'name' => $path.'/'.$f['name']
                  ];
                  if ($has_mod) {
                    $tmp['mtime'] = mktime(
                      substr($f['modify'], 8, 2),
                      substr($f['modify'], 10, 2),
                      substr($f['modify'], 12, 2),
                      substr($f['modify'], 4, 2),
                      substr($f['modify'], 6, 2),
                      substr($f['modify'], 0, 4)
                    );
                  }

                  if ($has_type) {
                    $tmp['dir']  = $f['type'] === 'dir';
                    $tmp['file'] = $f['type'] !== 'dir';
                  }

                  if ($has_extension) {
                    $tmp['ext'] = $f['type'] === 'dir' ? '' : Str::fileExt($f['name']);
                  }

                  if ($has_size) {
                    $tmp['size'] = $f['type'] === 'dir' ? 0 : $this->filesize($path.'/'.$f['name']);
                  }

                  if ($has_children && ($f['type'] === 'dir')) {
                    $tmp['num'] = count($this->getFiles($path.'/'.$f['name'], true, $hidden));
                  }
                }
                else{
                  $tmp = $path.'/'.$f['name'];
                }

                if ($f['type'] === 'dir') {
                  $dirs[] = $tmp;
                }
                else {
                  $files[] = $tmp;
                }
              }
            }
          }
        }
        else{
          X::log(error_get_last(), 'filesystem');
        }
      }
      else {
        $fs = scandir($path, SCANDIR_SORT_ASCENDING);
        foreach ($fs as $f){
          if (($f !== '.') && ($f !== '..') && ($hidden || (strpos(basename($f), '.') !== 0))) {
            $file = $path.'/'.$f;
            if ($this->_check_filter($file, $type)) {
              $is_dir = is_dir($path.'/'.$f);
              if ($detailed) {
                $tmp = [
                  'name' => $file
                ];
                if ($has_mod) {
                  $tmp['mtime'] = filemtime($path.'/'.$f);
                }

                if ($has_type) {
                  $tmp['dir']  = $is_dir;
                  $tmp['file'] = !$tmp['dir'];
                }

                if ($has_size) {
                  $tmp['size'] = $is_dir ? 0 : $this->filesize($path.'/'.$f);
                }

                if ($has_children && $is_dir) {
                  $tmp['num'] = count($this->getFiles($path.'/'.$f, true, $hidden));
                }
              }
              else {
                $tmp = $file;
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
    else {
      return $this->obj->getItems($path, $type, $hidden, $detailed);
    }
  }


  /**
   * @param $path
   * @return bool
   */
  private function _exists($path): bool
  {
    if ($this->mode === 'nextcloud') {
      return $this->obj->exists($path);
    }
    else {
      return file_exists($path);
    }
  }


  /**
   * @param string               $path
   * @param string|callable|null $filter
   * @param bool                 $hidden
   * @param string               $detailed
   * @return array
   */
  private function _scand(string $path = '', $filter = null, bool $hidden = false, string $detailed = ''): array
  {
    $all = [];
    foreach ($this->_get_items($path, 'dir', $hidden, $detailed) as $it){
      $p = $detailed ? $it['name'] : $it;
      if (!$filter || $this->_check_filter($p, $filter)) {
        $all[] = $it;
      }

      foreach ($this->_scand($p, $filter, $hidden, $detailed) as $t){
        $all[] = $t;
      }
    }

    return $all;
  }


  /**
   * @param string               $path
   * @param string|callable|null $filter
   * @param bool                 $hidden
   * @param string               $detailed
   * @return array
   */
  private function _scan(string $path = '', $filter = null, bool $hidden = false, string $detailed = ''): array
  {
    $all = [];

    foreach ($this->_get_items($path, 'both', $hidden, $detailed) as $it){
      $p = $detailed ? $it['name'] : $it;
      if (!$filter || $this->_check_filter($p, $filter)) {
        $all[] = $it;
      }

      if ($this->_is_dir($p)) {
        foreach ($this->_scan($p, $filter, $hidden, $detailed) as $t){
          $all[] = $t;
        }
      }
    }

    return $all;
  }


  /**
   * @param string               $path
   * @param string|callable|null $filter
   * @param bool                 $hidden
   * @param string               $detailed
   * @return array
   */
  private function _rscan(string $path = '', $filter = null, bool $hidden = false, string $detailed = ''): array
  {
    $all = [];

    foreach ($this->_get_items($path, 'both', $hidden, $detailed) as $it){
      $p = $detailed ? $it['name'] : $it;
      if ($this->_is_dir($p)) {
        foreach ($this->_scan($p, $filter, $hidden, $detailed) as $t){
          $all[] = $t;
        }
      }

      if (!$filter || $this->_check_filter($p, $filter)) {
        $all[] = $it;
      }

    }

    return $all;
  }


  /**
   * @param string $dir
   * @param int    $chmod
   * @param bool   $recursive
   * @return bool
   */
  private function _mkdir(string $dir, int $chmod = 0755, $recursive = false): bool
  {
    if (empty($this->_is_dir($dir))) {
      return mkdir($dir, $chmod, $recursive);
    }

    return true;
  }


  /**
   * @param string $path
   * @param bool   $full
   * @return bool
   */
  private function _delete(string $path, bool $full = true): bool
  {
    $res = false;
    if ($this->mode === 'nextcloud') {
      $res = $this->obj->delete($path);
    }
    else {
      if ($this->_is_dir($path)) {
        $files = $this->_get_items($path, 'both', true);
        if (!empty($files)) {
          foreach ($files as $file) {
            $this->_delete($file);
          }
        }

        if ($full) {
          if ($this->mode === 'ssh') {
            try {
              $res = @ssh2_sftp_rmdir($this->obj, substr($path, strlen($this->prefix)));
            }
            catch (\Exception $e) {
              $this->log(X::_('Error in _delete').': '.$e->getMessage().' ('.$e->getLine().')');
            }
          }
          elseif ($this->mode === 'ftp') {
            try {
              $res = @ftp_rmdir($this->obj, substr($path, strlen($this->prefix)));
            }
            catch (\Exception $e) {
              $this->log(X::_('Error in _delete').': '.$e->getMessage().' ('.$e->getLine().')');
            }
          }
          else{
            try {
              $res = rmdir($path);
            }
            catch (\Exception $e) {
              $this->log(X::_('Error in _delete').': '.$e->getMessage().' ('.$e->getLine().')');
            }
          }
        }
        else {
          $res = true;
        }
      }
      elseif ($this->_is_file($path)) {
        if ($this->mode === 'ssh') {
          try {
            $res = ssh2_sftp_unlink($this->obj, substr($path, strlen($this->prefix)));
          }
          catch (\Exception $e) {
            $this->log(X::_('Error in _delete').': '.$e->getMessage().' ('.$e->getLine().')');
          }
        }
        elseif ($this->mode === 'ftp') {
          try {
            $res = ftp_delete($this->obj, substr($path, strlen($this->prefix)));
          }
          catch (\Exception $e) {
            $this->log(X::_('Error in _delete').': '.$e->getMessage().' ('.$e->getLine().')');
          }
        }
        else {
          try {
            $res = unlink($path);
          }
          catch (\Exception $e) {
            $this->log(X::_('Error in _delete').': '.$e->getMessage().' ('.$e->getLine().')');
          }
        }
      }
    }

    return $res;
  }


  /**
   * Copy either the file to the new path or the ocntent of the dir inside the new dir
   * @param string $source
   * @param string $dest
   * @return bool
   */
  private function _copy(string $source, string $dest): bool
  {
    if ($this->mode !== 'nextcloud') {
      if ($this->_is_file($source)) {
        return copy($source, $dest);
      }
      elseif ($this->_is_dir($source) && $this->_mkdir($dest)) {
        foreach ($this->_get_items($source, 'both', true) as $it){
          $this->_copy($it, $dest.'/'.basename($it));
        }

        return true;
      }

      return false;
    }
  }


  /**
   * @param $source
   * @param $dest
   * @return bool
   */
  private function _rename($source, $dest): bool
  {
    if ($this->mode !== 'nextcloud') {
      $file1 = substr($source, strlen($this->prefix));
      $file2 = substr($dest, strlen($this->prefix));
      if ($this->mode === 'ssh') {
        return ssh2_sftp_rename($this->obj, $file1, $file2);
      }

      if ($this->mode === 'ftp') {
        return ftp_rename($this->obj, $file1, $file2);
      }

      return rename($file1, $file2);
    }
    else{
      return $this->obj->rename($source, $dest);
    }
  }


  private function _get_empty_dirs($path, bool $hidden_is_empty = false): array
  {
    $res = [];
    $all = $this->_get_items($path, 'both', !$hidden_is_empty);
    $tot = count($all);
    // This directory will be added to the result if it is empty or if each of its items is itself an empty directory
    foreach ($all as $dir){
      if (is_dir($dir)) {
        $empty_dirs = $this->_get_empty_dirs($dir, !$hidden_is_empty);
        if (in_array($dir, $empty_dirs, true)) {
          $tot--;
        }

        // Each empty subdirectory will be added to the result
        foreach ($empty_dirs as $e){
          $res[] = $e;
        }
      }
    }

    if (!$tot) {
      $res[] = $path;
    }

    return $res;
  }


  private function _delete_empty_dirs($path, bool $hidden_is_empty = false): int
  {
    $num = 0;
    $all = $this->_get_items($path, 'both', !$hidden_is_empty);
    $tot = count($all);
    foreach ($all as $dir){
      if (is_dir($dir)) {
        $num += $this->_delete_empty_dirs($dir, $hidden_is_empty);
        if (!is_dir($dir)) {
          $tot--;
        }
      }

    }

    if (!$tot) {
      $this->_delete($path);
      $num++;
    }

    return $num;
  }


  private function _is_file(string $path)
  {
    if ($this->mode === 'nextcloud') {
      return $this->obj->isFile($path);
    }
    else {
      return is_file($path);
    }
  }


  private function _is_dir(string $path)
  {
    if ($this->mode === 'nextcloud') {
      return $this->obj->isDir($path);
    }
    else {
      return is_dir($path);
    }
  }


  private function _filemtime($path)
  {
    if ($this->mode === 'nextcloud') {
      return $this->obj->filemtime($path);
    }
    else {
      return filemtime($path);
    }
  }


  private function _filesize($path):? int
  {
    if ($this->mode === 'nextcloud') {
      return $this->obj->getSize($path);
    }
    else {
      if ($this->_is_file($path)) {
        return filesize($path);
      }

      return null;
    }
  }


  /**
   * @param $file
   */
  private function _download($file): String
  {
    if ($this->mode === 'nextcloud') {
      return $this->obj->download($file);
    }
    else {
      if (($f = $this->getFile($file)) && $f->check()) {
        return $file;
        /*die(var_dump($file));
        $f->download();*/
      }
    }
  }


  private function _upload(array $files, string $path): bool
  {
    $success = false;
    if (!empty($files) && !empty($path)) {
      foreach($files as $f){
        if (is_file($f['tmp_name'])) {
          //replace ' ' with '_' like in nextcloud
          $full_name = $this->getRealPath($path) . '/' . str_replace(' ', '_',$f['name']);
          if (!$this->exists($full_name)) {
            if (move_uploaded_file($f['tmp_name'], $full_name)) {
              return $success = true;
            }
          }
        }
      }
    }

    return $success;
  }

}
