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
      $directories = new directories($this->db);
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
    $directories = new directories($this->db);
    $cfg = $directories->dirs();
    if ( isset($data['dir'], $data['name'], $data['path'], $data['type'], $cfg[$data['dir']]) &&
      (strpos($data['path'], '../') === false) && \bbn\str\text::check_filename($data['name']) ) {
      $type = $data['type'] === 'file' ? 'file' : 'dir';
      $wtype = $type === 'dir' ? 'directory' : 'file';
      $delete = [];
      if ( $type === 'file' ) {
        if ( $data['dir'] === 'controllers' ) {
          $tab_url_mvc = $data['dir'] . '/' . $data['path'];
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
        $p_mvc = false;
        $p_mvc2 = false;
        if ( $data['dir'] === 'controllers' ) {
          foreach ( $cfg['controllers']['files'] as $f ) {
            $p = $f['path'] . $data['path'];
            if ( $f['title'] === 'Controller' ){
              $p_mvc = $f['path'] . $data['path'];
              $p_mvc2 = $f['path'];
            }
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
        // Files and directories to check if they're opened
        $sub_files = \bbn\file\dir::scan(empty($p_mvc) ? $p : $p_mvc);
        foreach ( $sub_files as $i => $sub ){
          if ( is_file($sub) ){
            $sub_files[$i] = str_replace((empty($p_mvc2) ? $cfg[$data['dir']]['root_path'] : $p_mvc2), $data['dir'] . '/', $sub);
          }
          else {
            unset($sub_files[$i]);
          }
        }
      }
      foreach ( $delete as $d ){
        $r = $type === 'dir' ? \bbn\file\dir::delete($d) : unlink($d);
        if ( empty($r) ){
          return $this->error("Impossible to delete the $wtype $d");
        }
      }
      $ret = [
        'path' => empty($p_mvc) ? $p : $p_mvc,
        'sub_files' => empty($sub_files) ? false : array_values($sub_files)
      ];
      if ( empty($ret['sub_files']) ){
        $ret['tab_url'] = [empty($tab_url_mvc) ? str_replace($cfg[$data['dir']]['root_path'], $data['dir'].'/', $ret['path']) : $tab_url_mvc];
      }
      return $ret;
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
      $directories = new directories($this->db);
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
    $directories = new directories($this->db);
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
      $directories = new directories($this->db);
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
      $directories = new directories($this->db);
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
          $ext = \bbn\str\text::file_ext($data['path']);
          $src = $cfg['root_path'].$src_file.($type === 'file' ? '.'.$ext : '');
          $dest = dirname($src).'/'.\bbn\str\text::file_ext($data['name'], 1)[0].($type === 'file' ? '.'.$ext : '');
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
        if ( isset($_SESSION[BBN_SESS_NAME]['ide']['list']) ){
          $sess = [
            'dir' => $data['dir'],
            'file' => $data['path']
          ];
          if ( in_array($sess, $_SESSION[BBN_SESS_NAME]['ide']['list']) ){
            unset($_SESSION[BBN_SESS_NAME]['ide']['list'][array_search($sess, $_SESSION[BBN_SESS_NAME]['ide']['list'])]);
            array_push($_SESSION[BBN_SESS_NAME]['ide']['list'], [
              'dir' => $data['dir'],
              'file' => $dest_file.( empty($ext) ? '.php' : '.'.$ext )
            ]);
          }
        }
        return [
          'new_file' => $dest_file,
          'new_file_ext' => empty($ext) ? '' : $ext
        ];
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
      unset($_SESSION[BBN_SESS_NAME]['ide']['list'][array_search($data, $_SESSION[BBN_SESS_NAME]['ide']['list'])]);
    }
    return ['data' => "Tab is not in session."];
  }
  
  public function export($data){
    if ( isset($data['dir'], $data['name'], $data['path'], $data['type']) ){
      $directories = new directories($this->db);
      $dirs = $directories->dirs();
      $root_dest = BBN_USER_PATH.'tmp/'.\bbn\str\text::genpwd().'/';
      if ( isset($dirs[$data['dir']]) ){
        if ( $data['dir'] === 'controllers' ){
          foreach ( $dirs['controllers']['files'] as $f ) {
            $dest = $root_dest.$data['name'].'/'.str_replace(BBN_APP_PATH, '', $f['path']);
            if ( $data['type'] === 'file' ) {
              $ext = \bbn\str\text::file_ext($data['path']);
              $path = substr($data['path'], 0, strrpos($data['path'], $ext));
              $file = $f['path'].$path.$f['ext'];
              if ( file_exists($file) ){
                if ( !\bbn\file\dir::create_path($dest.dirname($data['path'])) ){
                  return $this->error("Impossible to create the path ".$dest.dirname($data['path']));
                }
                if ( !\bbn\file\dir::copy($file, $dest.$path.$f['ext']) ){
                  return $this->error('Impossible to export the file '.$path.$f['ext']);
                }
              }
            }
            else {
              $dir = $f['path'].$data['path'];
              if ( file_exists($dir) ){
                if ( !\bbn\file\dir::copy($dir, $dest.$data['path']) ){
                  return $this->error('Impossible to export the folder '.$data['path']);
                }
              }
            }
          }
        }
        else {
          $ext = \bbn\str\text::file_ext($data['path']);
          $dir = false;
          foreach ( $dirs[$data['dir']]['files'] as $f ){
            if ( $ext === $f['ext'] ){
              $dir = $f['path'];
            }
          }
          if ( !$dir ){
            $dir = $dirs[$data['dir']]['files'][0]['path'];
          }
          $dest = $root_dest.$data['name'].'/'.$data['path'];
          if ( $data['type'] === 'file' ) {
            if ( !\bbn\file\dir::create_path(substr($dest, 0, strrpos($dest, '/') + 1)) ){
              return $this->error('Impossible to create the path ' . substr($dest, 0, strrpos($dest, '/') + 1));
            }
          }
          if ( !\bbn\file\dir::copy($dir.$data['path'], $dest) ){
            return $this->error('Impossible to export the file or folder '.$data['name']);
          }
        }
        // Create zip file
        if ( class_exists('\\ZipArchive') ) {
          $dest = ( $data['dir'] === 'controllers' ) ? $root_dest.$data['name'].'/mvc/' : $dest;
          $filezip = BBN_USER_PATH.'tmp/'.$data['name'].'.zip';
          $zip = new \ZipArchive();
          if ( $err = $zip->open($filezip, \ZipArchive::OVERWRITE) ) {
            if ( file_exists($dest) ){
              if ( ($data['type'] === 'dir') || ($data['dir'] === 'controllers') ){
                // Create recursive directory iterator
                $files = \bbn\file\dir::scan($dest);
                foreach ($files as $file) {
                  // Add current file to archive
                  if ( ($file !== $root_dest.$data['name']) &&
                    is_file($file) &&
                    !$zip->addFile($file, str_replace($root_dest.$data['name'].'/', '', $file))
                  ){
                    return $this->error("Impossible to add $file");
                  }
                }
              }
              else {
                if ( !$zip->addFile($dest, $data['path']) ){
                  return $this->error("Impossible to add $dest");
                }
              }
              if ( $zip->close() ) {
                if ( !\bbn\file\dir::delete($root_dest, 1) ) {
                  return $this->error("Impossible to delete the directory $root_dest");
                }
                return $filezip;
              }
              return $this->error("Impossible to close the zip file $filezip");
            }
            return $this->error("The path does not exist: $dest");
          }
          return $this->error("Impossible to create $filezip ($err)");
        }
        return $this->error("ZipArchive class non-existent");
      }
    }
    return $this->error();
  }

  // Error
  private function error($msg = "Error."){
    return ["error" => $msg];
  }
}