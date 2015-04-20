<?php
/* @var $this \bbn\mvc */

namespace bbn\ide;


class actions {

  public function __construct(\bbn\db\connection $db){
    $this->db = $db;
  }

  public function save($data){
    if ( isset($data['file'], $data['code']) &&
      (strpos($data['file'], '../') === false) ){
      $args = explode('/', $data['file']);
      // The root directory
      $dir = array_shift($args);
      // The rest of the path
      $path = implode('/', $args);
      // Gives the config array of each directory, indexed on the dir's name
      $directories = new \bbn\ide\directories($this->db);
      $cfg = $directories->dirs();
      if ( isset($cfg[$dir]) ){
        $dirs =& $cfg[$dir];
        // Change the path for the MVC
        if ( $dir === 'controllers'){
          // type of file part of the MVC
          foreach ( $dirs['files'] as $f ){
            if ( $f['url'] === end($args) ){
              if ( $f['url'] === '_ctrl' ){
                $arg = array_slice($args, 0 , count($args)-2);
                $new_path = count($arg) > 0 ? implode("/", $arg).'/_ctrl.'.$f['ext'] : '_ctrl.'.$f['ext'];
              }
              else{
                $arg = array_slice($args, 0 , count($args)-1);
                $new_path = substr(implode("/", $arg), 0 , -3).$f['ext'];
              }
              $new_path = $f['path'].$new_path;
            }
          }
        }
        else {
          foreach ( $dirs['files'] as $f ){
            if ( $f['ext'] === \bbn\str\text::file_ext($path) ){
              $new_path = $f['path'].$path;
            }
          }
        }
        if ( is_file($new_path) ){
          $backup = BBN_DATA_PATH.'users/'.$_SESSION[BBN_SESS_NAME]['user']['id'].'/ide/backup/'.date('Y-m-d His').' - Save/'.$dir.'/'.$path;
          \bbn\file\dir::create_path(dirname($backup));
          rename($new_path, $backup);
        }
        else if ( !is_dir(dirname($new_path)) ){
          \bbn\file\dir::create_path(dirname($new_path));
        }
        file_put_contents($new_path, $data['code']);
        return ['path' => $new_path];
      }
    }
    return $this->error('Error: Save');
  }

  public function delete($data){
    $directories = new \bbn\ide\directories($this->db);
    $cfg = $directories->dirs();
    if ( isset($data['dir'], $data['name'], $data['path'], $data['type'], $cfg[$data['dir']]) &&
      (strpos($data['path'], '../') === false) && \bbn\str\text::check_filename($data['name']) ) {
      $type = $data['type'] === 'file' ? 'file' : 'dir';
      $wtype = $type === 'dir' ? 'directory' : 'file';
      $delete = [];
      if ( $type === 'file' ) {
        if ( $data['dir'] === 'controllers' ) {
          if ( $data['name'] != '_ctrl' ) {
            foreach ( $cfg['controllers']['files'] as $f ) {
              $p = $f['path'] . substr($data['path'], 0, -3) . $f['ext'];
              if ( file_exists($p) && !in_array($p, $delete) ) {
                array_push($delete, $p);
              }
            }
          }
          else {
            $p = $cfg['controllers']['files']['CTRL']['path'].$data['path'];
            if ( file_exists($p) && !in_array($p, $delete) ) {
              array_push($delete, $p);
            }
          }
        }
        else {
          foreach ( $cfg[$data['dir']]['files'] as $f ) {
            if ( $f['ext'] === \bbn\str\text::file_ext($data['path']) ) {
              $p = $f['path'] . $data['path'];
              if ( file_exists($p) && !in_array($p, $delete) ) {
                array_push($delete, $p);
              }
            }
          }
        }
      }
      if ($type === 'dir') {
        if ( $data['dir'] === 'controllers' ) {
          foreach ( $cfg['controllers']['files'] as $f ) {
            $p = $f['path'] . $data['path'];
            if ( is_dir($p) && !in_array($p, $delete) ) {
              array_push($delete, $p);
            }
          }
        }
        else {
          $p = $cfg[$data['dir']]['root_path'].$data['path'];
          if ( is_dir($p) && !in_array($p, $delete) ) {
            array_push($delete, $p);
          }
        }
      }
      foreach ( $delete as $d ){
        $r = $type === 'dir' ? \bbn\file\dir::delete($d) : unlink($d);
        if ( empty($r) ){
          return $this->error("Impossible to delete the $wtype $d");
        }
      }
      return ['path' => $p];
    }
    else {
      return $this->error("There is a problem in the name you entered");
    }
  }

  public function duplicate($data){
    if ( isset($data['dir'], $data['path'], $data['src'], $data['name']) &&
      (strpos($data['src'], '../') === false) &&
      (strpos($data['path'], '../') === false) &&
      \bbn\str\text::check_filename($data['name']) ){
      $directories = new \bbn\ide\directories($this->db);
      $dirs = $directories->dirs();
      if ( isset($dirs[$data['dir']]) ){
        $cfg =& $dirs[$data['dir']];
        $src = $data['src'];
        $type = is_dir($cfg['files'][0]['path'].$src) ? 'dir' : 'file';
        $dir_src = dirname($src).'/';
        if ( $dir_src === './' ){
          $dir_src = '';
        }
        $name = \bbn\str\text::file_ext($src, 1)[0];
        $ext = \bbn\str\text::file_ext($src);
        $src_file = $dir_src.$name;
        $dest_file = $data['path'].'/'.$data['name'];
        $todo = [];
        if ( $data['dir'] === 'controllers' ){
          foreach ( $cfg['files'] as $f ){
            if ( $f != 'CTRL' ){
              $src = $f['path'].$src_file;
              if ( $type === 'file' ){
                $src .= '.'.$f['ext'];
              }
              $is_dir = ($type === 'dir') && is_dir($src);
              $is_file = ($type === 'dir') || $is_dir ? false : is_file($src);
              if ( $is_dir || $is_file ){
                $dest = $f['path'].$dest_file;
                if ( $type === 'file' ){
                  $dest .= '.'.$f['ext'];
                }
                if ( file_exists($dest) ){
                  return $this->error("Un fichier du meme nom existe déjà $dest");
                }
                else{
                  $todo[$src] = $dest;
                }
              }
            }
          }
        }
        else {
          $src = $cfg['root_path'].$src_file.($type === 'file' ? '.'.$ext : '');
          $is_dir = ($type === 'dir') && is_dir($src);
          $is_file = ($type === 'dir') || $is_dir ? false : is_file($src);
          if ( $is_dir || $is_file ){
            $dest = $cfg['root_path'].$dest_file.($type === 'file' ? '.'.$ext : '');
            if ( file_exists($dest) ){
              return $this->error("Un fichier du meme nom existe déjà $dest");
            }
            else{
              $todo[$src] = $dest;
            }
          }
        }
        foreach ( $todo as $src => $dest ){
          if ( !\bbn\file\dir::copy($src, $dest) ){
            return $this->error("Impossible de déplacer le fichier $src");
          }
        }
        return 1;
      }
    }
    return $this->error();
  }

  public function insert($data){
    $directories = new \bbn\ide\directories($this->db);
    $dirs = $directories->dirs();
    if ( isset($data['dir'], $data['name'], $data['path'], $data['type'], $dirs[$data['dir']]) &&
      (strpos($data['path'], '../') === false) &&
      \bbn\str\text::check_filename($data['name']) ){
      $cfg =& $dirs[$data['dir']];
      $type = $data['type'] === 'file' ? 'file' : 'dir';
      $wtype = $type === 'dir' ? 'directory' : 'file';
      $dir = $data['dir'] === 'controllers' ? $cfg['files']['Controller']['path'] : $cfg['root_path'];
      $ext = $data['dir'] === 'controllers' ? $cfg['files']['Controller']['ext'] : $data['ext'];
      if ( ($type === 'file') && ($dir != 'controllers') && !empty($ext) ) {
        foreach ( $cfg['files'] as $f ) {
          if ( $ext === $f['ext'] ) {
            $dir = $f['path'];
            break;
          }
        }
      }
      $path = '';
      if ( ($data['path'] !== './') ){
        if ( is_dir($dir.$data['path']) ){
          $path = $data['path'].'/';
        }
        else{
          return $this->error("The container directory doesn't exist");
        }
      }
      $path .= $type === 'file' ? $data['name'].'.'.$ext : $data['name'];
      if ( file_exists($dir.$path) ){
        return $this->error("The $wtype already exists");
      }
      if ( $type === 'dir' ){
        if ( !mkdir($dir.$path) ){
          return $this->error("Impossible to create the $wtype");
        }
      }
      else if ( $ext ){
        $modes = $directories->modes();
        if ( !file_put_contents($dir.$path, isset($modes[$ext]['code']) ? $modes[$ext]['code'] : ' ') ){
          return $this->error("Impossible to create the $wtype");
        }
      }
      return 1;
    }
    return $this->error("There is a problem in the name you entered");
  }

  public function move($data){
    if ( isset($data['dir'], $data['spath'], $data['dpath']) &&
      (strpos($data['dpath'], '../') === false) &&
      (strpos($data['spath'], '../') === false) ){
      $directories = new \bbn\ide\directories($this->db);
      $dirs = $directories->dirs();
      if ( isset($dirs[$data['dir']]) ){
        $cfg =& $dirs[$data['dir']];
        $spath = $data['spath'];
        $dpath = $data['dpath'];
        if ( $data['dir'] === 'controllers' ){
          $type = is_dir($cfg['files']['Controller']['path'].$spath) ? 'dir' : 'file';
        }
        else {
          $type = is_dir($cfg['root_path'].$spath) ? 'dir' : 'file';
        }
        $dir = dirname($spath).'/';
        if ( $dir === './' ){
          $dir = '';
        }
        $name = \bbn\str\text::file_ext($spath, 1)[0];
        $ext = \bbn\str\text::file_ext($spath);
        $todo = [];
        if ( $data['dir'] === 'controllers' ){
          foreach ( $cfg['files'] as $f ){
            if ( $f != 'CTRL' ){
              $src = $f['path'].$dir.$name;
              if ( $type === 'file' ){
                $src .= '.'.$f['ext'];
              }
              $is_dir = ($type === 'dir') && is_dir($src);
              $is_file = ($type === 'dir') || $is_dir ? false : is_file($src);
              if ( $is_dir || $is_file ){
                \bbn\file\dir::create_path($f['path'].$dpath);
                $dest = $f['path'].$dpath.'/'.$name;
                if ( $type === 'file' ){
                  $dest .= '.'.$f['ext'];
                }
                if ( file_exists($dest) ){
                  return $this->error("Un fichier du meme nom existe déjà $dest");
                }
                else{
                  $todo[$src] = $dest;
                }
              }
            }
          }
        }
        else {
          $src = $cfg['root_path'].$dir.$name.($type === 'file' ? '.'.$ext : '');
          $is_dir = ($type === 'dir') && is_dir($src);
          $is_file = ($type === 'dir') || $is_dir ? false : is_file($src);
          if ( $is_dir || $is_file ){
            \bbn\file\dir::create_path($cfg['root_path'].$dpath);
            $dest = $cfg['root_path'].$dpath.'/'.$name.($type === 'file' ? '.'.$ext : '');
            if ( file_exists($dest) ){
              return $this->error("Un fichier du meme nom existe déjà $dest");
            }
            else{
              $todo[$src] = $dest;
            }
          }
        }
        foreach ( $todo as $src => $dest ){
          if ( !rename($src, $dest) ){
            return $this->error("Impossible de déplacer le fichier $src");
          }
        }
        return 1;
      }
    }
    return $this->error();
  }

  public function rename($data){
    if ( isset($data['dir'], $data['name'], $data['path']) &&
      (strpos($data['path'], '../') === false) &&
      \bbn\str\text::check_filename($data['name']) ){
      $directories = new \bbn\ide\directories($this->db);
      $dirs = $directories->dirs();
      if ( isset($dirs[$data['dir']]) ){
        $cfg =& $dirs[$data['dir']];
        $path = $data['path'];
        if ( $data['dir'] === 'controllers' ){
          $type = is_dir($cfg['files']['Controller']['path'].$path) ? 'dir' : 'file';
        }
        else {
          $type = is_dir($cfg['root_path'].$path) ? 'dir' : 'file';
        }
        $dir = dirname($path).'/';
        if ( $dir === './' ){
          $dir = '';
        }
        $name = \bbn\str\text::file_ext($path, 1)[0];
        $src_file = $dir.$name;
        $dest_file = $dir.$data['name'];
        $todo = [];
        if ( $data['dir'] === 'controllers' ){
          foreach ( $cfg['files'] as $f ){
            if ( $f != 'CTRL' ){
              $src = $f['path'].$src_file;
              $dest = dirname($src).'/'.$data['name'];
              if ( $type === 'file' ){
                $src .= '.'.$f['ext'];
                $dest .= '.'.$f['ext'];
              }
              $is_dir = ($type === 'dir') && is_dir($src);
              $is_file = ($type === 'dir') || $is_dir ? false : is_file($src);
              if ( $is_dir || $is_file ){
                if ( file_exists($dest) ){
                  return $this->error("Un fichier du meme nom existe déjà $dest");
                }
                else{
                  $todo[$src] = $dest;
                }
              }
            }
          }
        }
        else {
          $dest_file= $dir.\bbn\str\text::file_ext($data['name'], 1)[0];
          $src = $cfg['root_path'].$src_file.($type === 'file' ? '.'.\bbn\str\text::file_ext($path) : '');
          $dest = dirname($src).'/'.\bbn\str\text::file_ext($data['name'], 1)[0].($type === 'file' ? '.'.\bbn\str\text::file_ext($data['path']) : '');
          $is_dir = ($type === 'dir') && is_dir($src);
          $is_file = ($type === 'dir') || $is_dir ? false : is_file($src);
          if ( $is_dir || $is_file ){
            if ( file_exists($dest) ){
              return $this->error("Un fichier du meme nom existe déjà $dest");
            }
            else{
              $todo[$src] = $dest;
            }
          }
        }
        foreach ( $todo as $src => $dest ){
          if ( !rename($src, $dest) ){
            return $this->error("Impossible de déplacer le fichier $src");
          }
        }
        return ['new_file' => $dest_file];
      }
    }
    return $this->error();
  }

  public function close($data){
    if ( isset($data['dir'], $data['file']) ){
      $data['file'] = ($data['dir'] === 'controllers') && (\bbn\str\text::file_ext($data['file']) !== 'php') ?
        substr($data['file'], 0, strrpos($data['file'], "/")) : $data['file'];
      unset($data['act']);
    }
    if ( isset($_SESSION[BBN_SESS_NAME]['ide']) &&
      in_array($data, $_SESSION[BBN_SESS_NAME]['ide']['list']) ){
      foreach ( $_SESSION[BBN_SESS_NAME]['ide']['list'] as $i => $v ){
        if ( ($v['dir'] === $data['dir']) && ($v['file'] === $data['file']) ){
          unset($_SESSION[BBN_SESS_NAME]['ide']['list'][$i]);
          return 1;
        }
      }
    }
    return ['data' => "Tab is not in session."];
  }

  // Error
  private function error($msg = "Error."){
    return ["error" => $msg];
  }
}