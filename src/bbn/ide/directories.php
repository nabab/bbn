<?php

namespace bbn\ide;


class directories {

  private static
    /** @var bool|int $path_type */
    $path_type = false,
    /** @var bool|int $path_type */
    $files_pref = false;


  protected
    /** @var \bbn\appui\options $options */
    $options,
    /** @var null|string $last_error The last error recorded by the class */
    $last_error;

  /**
   * Sets the root of the paths' types option
   * @param $id
   */
  private static function set_path_type($id){
    self::$path_type = $id;
  }

  /**
   * Gets the ID of the paths' types option
   * @return int
   */
  private function _path_type(){
    if ( !self::$path_type ){
      if ( $id = $this->options->from_code('PTYPES', 'bbn_ide') ){
        self::set_path_type($id);
      }
    }
    return self::$path_type;
  }

  /**
   * Sets the root of the files' preferences option
   * @param $id
   */
  private static function set_files_pref($id){
    self::$files_pref = $id;
  }

  /**
   * Gets the ID of the paths' types option
   * @return int
   */
  private function _files_pref(){
    if ( !self::$files_pref ){
      if ( $id = $this->options->from_code('files', 'bbn_ide') ){
        self::set_files_pref($id);
      }
    }
    return self::$files_pref;
  }

  /**
   * Sets the last error as the given string
   * @param $st
   */
  protected function error($st){
    \bbn\tools::log($st, "directories");
    $this->last_error = $st;
  }

  /**
   * Returns true if the error function has been called
   * @return bool
   */
  public function has_error(){
    return !empty($this->last_error);
  }

  /**
   * Returns last recorded error, and null if none
   * @return mixed last recorded error, and null if none
   */
  public function get_last_error(){
    return $this->last_error;
  }

  /**
   * directories constructor.
   * @param \bbn\appui\options $options
   */
  public function __construct(\bbn\appui\options $options){
    $this->options = $options;
  }

  public function real_to_url($file){
    $dirs = $this->dirs();
    foreach ( $dirs as $i => $d ){
      $root = $this->get_root_path($i);
      $bits = explode('/', $d['root_path']);
      if ( strpos($file, $root) === 0 ){
        $res = $i.'/';
        $nfile = substr($file, strlen($root));
        //php/adherents/_ctrl
        if ( isset($d['tabs']) ){
          foreach ( $d['tabs'] as $k => $t ){
            if ( strpos($nfile, $t['path']) === 0 ){
              if ( !empty($t['fixed']) ){
                if ( basename($nfile) === $t['fixed'] ){
                  foreach ( $d['tabs'] as $b => $a ){
                    if ( !empty($a['default']) ){
                      $nfile = substr($nfile, strlen($t['path']));
                      $res .= $a['url'].'/';
                      $nfile .= '/'.$t['url'];
                      break;
                    }
                  }
                  break;
                }
              }
              else{
                $res .= $t['url'].'/';
                $nfile = substr($nfile, strlen($t['path']));
                if ( !empty($t['default']) ){
                  $nfile = dirname($nfile).'/'.basename($nfile, '.'.\bbn\str\text::file_ext($file)).'/'.$t['url'];
                }
                break;
              }
            }
          }
        }
        $res .= $nfile;
        return \bbn\str\text::parse_path($res);
      }
    }
    return false;
  }

  public function real_to_id($file){
    $dirs = $this->dirs();
    foreach ( $dirs as $i => $d ){
      $bits = explode('/', $d['root_path']);
      if ( defined($bits[0]) ){
        $d['root_path'] = constant($bits[0]);
        array_shift($bits);
        $d['root_path'] .= implode('/', $bits);
      }
      if ( strpos($file, $d['root_path']) === 0 ){
        return \bbn\str\text::parse_path($i.'/'.substr($file, strlen($d['root_path'])));
      }
    }
    return false;
  }

  public function url_to_real($file){
    $bits = explode('/', $file);
    if ( isset($bits[0]) ){
      if ( $dir = $this->dir($bits[0]) ){
        $res = $this->get_root_path($bits[0]);
        array_shift($bits);
        if ( !empty($dir['tabs']) && isset($bits[0]) ){
          foreach ( $dir['tabs'] as $t ){
            if ( $t['url'] === $bits[0] ){
              array_shift($bits);
              //main_mvc/php/model/test/home/php
              if ( !empty($t['default']) ){
                $last = end($bits);
                foreach ( $dir['tabs'] as $a ){
                  if ( !empty($a['fixed']) && ($a['fixed'] === $last.'.'.$a['extensions'][0]['ext']) ){
                    $res .= $a['path'];
                    array_pop($bits);
                    $bits[count($bits)-1] = $a['fixed'];
                    break;
                  }
                  else if ( $last === $a['url'] ){
                    $res .= $a['path'];
                    array_pop($bits);
                    $bits[count($bits)-1] .= '.'.$a['extensions'][0]['ext'];
                  }
                }
              }
              else{
                $res .= $t['path'];
              }
              break;
            }
          }
        }
        $res .= implode('/', $bits);
        return \bbn\str\text::parse_path($res);
      }
    }
    return false;
  }

  public function id_to_real($file){
    $bits = explode('/', $file);
    if ( $dir = $this->dir($bits[0]) ){
      $res = $this->get_root_path($bits[0]);
      array_shift($bits);
      if ( !empty($dir['tabs']) ){
        foreach ( $dir['tabs'] as $t ){
          if ( $t['path'] === $bits[0].'/' ){
            array_shift($bits);
            $res .= $t['path'];
          }
        }
      }
      $res .= implode('/', $bits);
      return \bbn\str\text::parse_path($res);
    }
  }

  public function url_to_id($file){
    if ( $file = $this->url_to_real($file) ){
      return $this->real_to_id($file);
    }
    return false;
  }

  public function id_to_url($file){
    if ( $file = $this->id_to_real($file) ){
      return $this->real_to_url($file);
    }
    return false;
  }

  /**
   * Get the real root path from a directory's alias as recorded in the options
   * @param $st
   * @return bool|string
   */
  public function get_root_path($st, $full = false){

    $st = \bbn\str\text::parse_path($st);
    /** @var array $bits Each part of the path */
    $bits = explode('/', $st);
    /** @var string $root */
    $root = array_shift($bits);

    /** @var array $dir The directory configuration */
    $dir = $this->dir($root);

    if ( $dir && !empty($dir['root_path']) ){

      /** @var string $real_root */
      $real_root = \bbn\str\text::parse_path($dir['root_path']);
      /** @var array $bits2 Each part of the path */
      $bits2 = explode('/', $real_root);
      /** @var string $constant The first path of the path which might be a constant */
      $constant = $bits2[0];

      /** @var string $path The path that will be returned */
      $path = '';
      if ( defined($constant) ){
        $path .= constant($constant);
        array_shift($bits2);
      }
      $path .= implode('/', $bits2).'/';
      if ( isset($dir['tabs'], $bits[0], $dir['tabs'][$bits[0]]) ){
        $path .= $dir['tabs'][$bits[0]]['path'].'/';
        array_shift($bits);
      }
      $r = \bbn\str\text::parse_path($path.'/');
      if ( $full ){
        $r .= implode('/', $bits);
      }
      return $r;
    }
    return false;
  }

  /**
   * @param $data
   * @return array
   */
  public function add($data){
    $data['position'] = $this->db->get_one('
      SELECT MAX(position) AS pos
      FROM bbn_ide_directories') + 1;
    if ( $this->db->insert('bbn_ide_directories', [
      'name' => $data['name'],
      'root_path' => \bbn\str\text::parse_path($data['root_path']),
      'fcolor' => $data['fcolor'],
      'bcolor' => $data['bcolor'],
      'outputs' => strlen($data['outputs']) ? $data['outputs'] : NULL,
      'files' => $data['files'],
      'position' => $data['position']
    ]) ){
      $data['id'] = $this->db->last_id();
      return $data;
    }
    return $this->error('Error: Add.');
  }

  /**
   * @param $data
   * @return array|int
   */
  public function edit($data){
    if ( $this->db->update('bbn_ide_directories', [
      'name' => $data['name'],
      'root_path' => \bbn\str\text::parse_path($data['root_path']),
      'fcolor' => $data['fcolor'],
      'bcolor' => $data['bcolor'],
      'outputs' => strlen($data['outputs']) ? $data['outputs'] : NULL,
      'files' => $data['files'],
      'position' => $data['position']
    ], ['id' => $data['id']]) ){
      return 1;
    }
    return $this->error('Error: Edit.');
  }

  /**
   * @param $data
   * @return array|int
   */
  public function delete($data){
    if ( $this->db->delete('bbn_ide_directories', ['id' => $data['id']]) ){
      return 1;
    }
    return $this->error('Error: Delete.');
  }

  /**
   * @param string $name
   * @return array
   */
  public function get($name=''){
    $all = $this->options->full_soptions(self::_path_type());
    if ( empty($name) ){
      return $all;
    }
    else{

    }
    return empty($name) ?
      $this->db->rselect_all('bbn_ide_directories', [], [], ['position' => 'ASC']) :
      $this->db->rselect_all('bbn_ide_directories', [], ['name' => $name]);
  }

  /**
   * @param string $name
   * @return array|bool
   */
  public function dirs($name=''){
    if ( $name ){
      $name = explode('/', $name)[0];
    }
    $all = $this->options->full_soptions(self::_path_type());
    $cats = [];
    $r = [];
    foreach ( $all as $a ){
      $k = $a['code'];
      if ( !isset($cats[$a['id_parent']]) ){
        $cats[$a['id_parent']] = $this->options->option($a['id_parent']);
      }
      $r[$k] = $a;
      $r[$k]['title'] = $r[$k]['text'];
      $r[$k]['is_mvc'] = ($cats[$a['id_parent']]['code'] === 'mvc');
      if ( $cats[$a['id_parent']]['code'] === 'mvc' ){
        $r[$k]['tabs'] = $cats[$a['id_parent']]['tabs'];
      }
      else{
        $r[$k]['extensions'] = $cats[$a['id_parent']]['extensions'];
      }
    }
    if ( $name ){
      return isset($r[$name]) ? $r[$name] : false;
    }
    return $r;
  }

  public function dir($name){
    return $this->dirs($name);
  }

  public function option_id($file_id){
    return $this->options->get_id($file_id, $this->_files_pref());
  }

  public function has_option($file_id){
    return $this->options->has_id($file_id, $this->_files_pref());
  }

  public function create($dir, $path, $name, $type = 'file'){
    //die(\bbn\tools::dump($dir, $path, $name, $type));
    if ( ($cfg = $this->dir($dir)) &&
      ($root = $this->get_root_path($dir)) &&
      ($url = \bbn\str\text::parse_path($dir.'/'.$path.'/'.$name))
    ){
      $ext = false;
      $type = $type === 'file' ? 'file' : 'dir';
      $wtype = $type === 'dir' ? 'directory' : 'file';
      $bits = explode('/', $dir);
      if ( !empty($cfg['is_mvc']) && isset($bits[1]) ){
        foreach ( $cfg['tabs'] as $t ){
          if ( $t['url'] === $bits[1] ){
            if ( !empty($t['default']) ){
              $ext = $t['extensions'][0]['ext'];
              if ( $type === 'file' ){
                $url = dirname($url).'/'.basename($url, '.'.$ext);
              }
              $url .= '/'.$bits[1];
              $default = $t['extensions'][0]['default'];
            }
            else if ( $type === 'file' ){
              $ext = \bbn\str\text::file_ext($name);
              foreach ( $t['extensions'] as $e ){
                if ( $e['ext'] === $ext ){
                  $default = $e['default'];
                }
              }
            }
            break;
          }
        }
      }
      $real = $this->url_to_real($url);
      //die($real);
      if ( ($type === 'file') && !$ext ){
        $ext = \bbn\str\text::file_ext($real);
        foreach ( $cfg['extensions'] as $e ){
          if ( $e['ext'] === $ext ){
            $default = $e['default'];
          }
        }
      }
      if ( ($type === 'dir') && $ext ){
        $real = dirname($real).'/'.basename($real, '.'.$ext);
      }
      if ( $type === 'dir' ){
        if ( is_dir($real) ){
          return $this->error("The directory already exists");
        }
        if ( !\bbn\file\dir::create_path($real) ){
          return $this->error("Impossible to create the directory");
        }
      }
      else{
        if ( is_file($real) ){
          return $this->error("The file already exists");
        }
        if ( !\bbn\file\dir::create_path(dirname($real)) ){
          return $this->error("Impossible to create the container directory");
        }
        if ( !file_put_contents($real, $default) ){
          return $this->error("Impossible to create the file");
        }
      }
      return 1;
    }
    return $this->error("There is a problem in the name you entered");
  }

  /**
   * @param string $file The path and name of the file
   * @param string $dir The alias name of the root directory
   * @return array|bool
   */
  public function load($file, $dir, \bbn\user\preferences $pref = null){

    /** @var boolean|array $res */
    $res = false;
    $file = \bbn\str\text::parse_path($file);
    $dir = \bbn\str\text::parse_path($dir);

    if ( $file && $dir ){

      /** @var array $bits $bits[0] represents the root, and for MVC $bits[1] is the tab */
      $bits = explode('/', $dir);

      /** @var array $dir_cfg The directory configuration from DB */
      $dir_cfg = $this->dir($bits[0]);

      if ( !empty($bits[1]) ){
        $file = \bbn\str\text::parse_path($bits[1].'/'.$file);
      }

      $res = $this->get_file($file, $bits[0], $dir_cfg, $pref);
    }
    return $res;
  }

  /**
   * @param array $cfg
   * @return array|bool
   */
  protected function get_file($file, $dir, array $cfg, \bbn\user\preferences $pref = null){
    if ( isset($cfg['title'], $cfg['bcolor'], $cfg['fcolor']) ){
      $r = [
        'bcolor' => $cfg['bcolor'],
        'fcolor' => $cfg['fcolor']
      ];
      /** @var array $bits */
      $bits = explode("/", $file);
      while ( !empty($bits) && empty($sd) ){
        $sd = array_shift($bits);
      }

      /** @var string $name The file's name - without path and extension */
      $name = \bbn\str\text::file_ext($file, 1)[0];
      /** @var string $ext The file's extension */
      $ext = \bbn\str\text::file_ext($file);

      /** @var string $real_dir The real/actual path to the root directory */
      $real_dir = $this->get_root_path($dir);
      /** @var string $real_dir The real/actual path to the root directory */
      $real_file = $real_dir.$file;

      // If we are asked for an MVC but it's not the controller which is called, we only give the selected file
      if ( isset($cfg['tabs'], $cfg['tabs'][$sd]) && empty($cfg['tabs'][$sd]['default']) ){
        $t =& $cfg['tabs'][$sd];
        if ( isset($t['bcolor']) ){
          $r['bcolor'] = $t['bcolor'];
        }
        if ( isset($t['fcolor']) ){
          $r['fcolor'] = $t['fcolor'];
        }
        $r['title'] = $file;
        foreach ( $t['extensions'] as $e ){
          if ( $e['ext'] === $ext ){
            $mode = $e['mode'];
            break;
          }
        }
        unset($cfg['tabs']);
      }
      if ( isset($cfg['tabs'], $cfg['tabs'][$sd]) && !empty($cfg['tabs'][$sd]['default']) ){
        /** @var string $real_file The absolute full path to the file */
        $real_file = $real_dir.$cfg['tabs'][$sd]['path'].implode('/', $bits);
        $r['list'] = [];
        $tmp = dirname(implode('/', $bits)).'/';
        if ( $tmp === './' ){
          $tmp = '';
        }
        $r['url'] = \bbn\str\text::parse_path($dir.'/'.$cfg['tabs'][$sd]['url'].'/'.$tmp.$name);
        $r['title'] = $tmp.basename(end($bits), '.php');
        $r['def'] = '';

        foreach ( $cfg['tabs'] as $k => $t ){
          if ( !empty($t['default']) ){
            $r['def'] = $k;
          }
          $file = dirname($t['path'].implode('/', $bits)).'/'.$name;
          //die(\bbn\tools::dump("TEST", $file, $real_file, $real_dir, $ext, $bits));
          $info = $this->get_file($file, $dir, $t, $pref);
          if ( !$info ){
            $this->error("Impossible to get a tab's configuration: DIR: $dir - FILE: $file - CFG: ".\bbn\tools::get_dump($t));
            die($this->get_last_error());
          }
          else{
            array_push($r['list'], $info);
          }
          $file = dirname($file);
          if ( !empty($t['fixed']) && !empty($t['recursive']) ){
            $index = count($r['list']) - 1;
            while ( $file && ($file.'/' !== $t['path']) ){
              $file = dirname($file).'/'.$t['fixed'];
              $info = $this->get_file($file, $dir, $t, $pref);
              if ( !$info ){
                $this->error("Impossible to get a supra-controller's configuration: DIR: $dir - FILE: $file - CFG: ".\bbn\tools::get_dump($t));
                die($this->get_last_error());
              }
              else{
                array_unshift($r['list'], $info);
              }
              $file = dirname($file);
              $index++;
            }
            for ( $i = 0; $i <= $index; $i++ ){
              $r['list'][$i]['title'] .= ' '.($i+1);
              $r['list'][$i]['url'] = str_repeat('_', $index-$i).$r['list'][$i]['url'];
            }
          }
        }
      }
      else{
        $is_file = 1;
        // We are in a tab
        if ( isset($sd) && (empty($ext) || !empty($cfg['fixed'])) ){
          $r['static'] = 1;
          $r['title'] = $cfg['title'];
          $new_file = false;
          if ( !empty($cfg['fixed']) ){
            $ext = \bbn\str\text::file_ext($cfg['fixed']);
            foreach ( $cfg['extensions'] as $e ){
              if ( $e['ext'] === $ext ){
                $new_file = dirname($file).'/'.$cfg['fixed'];
                $file = $new_file;
                $real_file = $real_dir.$new_file;
                $mode = $e['mode'];
                if ( !is_file($real_file) ){
                  $content = $e['default'];
                }
                break;
              }
            }
          }
          else{
            foreach ( $cfg['extensions'] as $e ){
              if ( is_file($real_dir.$file.'.'.$e['ext']) ){
                $new_file = $file.'.'.$e['ext'];
                $ext = $e['ext'];
                $mode = $e['mode'];
                $real_file = $real_dir.$new_file;
                break;
              }
            }
          }
          // File doesn't exist, we set a default content
          if ( !$new_file ){
            $is_file = false;
            $content = $cfg['extensions'][0]['default'];
            $new_file = empty($ext) ? $file.'.'.$cfg['extensions'][0]['ext'] : $file;
            $real_file = $real_dir.$new_file;
            $ext = $cfg['extensions'][0]['ext'];
            $mode = $cfg['extensions'][0]['mode'];
          }
        }
        else if ( !is_file($real_file) ){
          $msg = 'Impossible to find the file '.$real_file;
          $this->error($msg);
          return false;
        }
        else if ( is_array($cfg['extensions']) ){
          foreach ( $cfg['extensions'] as $e ){
            if ( $ext === $e['ext'] ){
              $mode = $e['mode'];
              break;
            }
          }
        }

        if ( !isset($content) ){
          $content = file_get_contents($real_file);
        }



        if ( !isset($cfg['tabs']) ){
          unset($sd);
        }
        if ( !isset($r['title']) ){
          $r['title'] = $file;
        }
        $r['url'] = isset($cfg['url']) ? $cfg['url'] : $dir.'/'.$file;

        if ( empty($o) ){
          $o = [];
        }

        $r['id_script'] = \bbn\str\text::parse_path($dir.'/'.$file);
        if ( !empty($r['static']) && (empty($cfg['fixed']) || (basename($file) !== $cfg['fixed'])) ){
          $r['id_script'] .= '.'.$ext;
        }

        if ( $is_file &&
          $pref &&
          ($id_option = $this->options->get_id($r['id_script'], BBN_ID_SCRIPT))
        ){
          $o = $pref->get($id_option);
          if ( md5($content) !== $o['md5'] ){
            $pref->delete($id_option);
            $o = [];
          }
        }

        if ( $real_file ){
          $r['file'] = $real_file;
        }
        $r['cfg'] = [
          'mode' => $mode,
          'value' => $content,
          'selections' => !empty($o['selections']) ? $o['selections'] : [],
          'marks' => !empty($o['marks']) ? $o['marks'] : []
        ];
      }
      return $r;
    }
    return false;
  }

  public function save($file, $code, array $cfg = null, \bbn\user\preferences $pref = null){
    if ( ($file = \bbn\str\text::parse_path($file)) && ($real = $this->url_to_real($file)) ){
      $bits = explode('/', $file);
      // We delete if code is empty and we're in a non mandatory file of tabs' set
      if ( empty($code) && ($dir = $this->dir($bits[0])) ){
        array_shift($bits);
        if ( !empty($dir['tabs']) && isset($bits[0]) ){
          foreach ( $dir['tabs'] as $t ){
            if ( $t['url'] === $bits[0] ){
              if ( !empty($t['default']) ){
                $url = end($bits);
                foreach ( $dir['tabs'] as $a ){
                  if ( $a['url'] === $url ){
                    if ( empty($a['fixed']) && empty($a['default']) ){
                      if ( @unlink($real) ){
                        return 1;
                      }
                    }
                    break;
                  }
                }
              }
              break;
            }
          }
        }
      }
      $id_file = $this->real_to_id($real);
      $id_user = false;
      if ( $session = \bbn\user\session::get_current() ){
        $id_user = $session->get('user', 'id');
      }
      if ( is_file($real) ){
        if ( $id_user ){
          $ext = \bbn\str\text::file_ext($real, 1);
          $backup = dirname(BBN_DATA_PATH."users/$id_user/ide/backup/".$id_file).'/'.$ext[0].'/'.date('Y-m-d His').'.'.$ext[1];
          \bbn\file\dir::create_path(dirname($backup));
          rename($real, $backup);
        }
      }
      else if ( !is_dir(dirname($real)) ){
        \bbn\file\dir::create_path(dirname($real));
      }
      file_put_contents($real, $code);
      if ( $pref && $id_user ){
        $change = [
          'md5' => md5($code),
        ];
        if ( isset($cfg, $cfg['selections']) ){
          $change['selections'] = $cfg['selections'];
        }
        if ( isset($cfg, $cfg['marks']) ){
          $change['marks'] = $cfg['marks'];
        }
        $id_option = $this->option_id($id_file);
        $pref->set($id_option, $change);
      }
      return ['path' => $real];
    }
    return $this->error('Error: Save');
  }

  public function close($dir, $file, array $cfg = null, \bbn\user\preferences $pref = null){
    if ( $dirs = $this->dirs($dir) ){
      if ( !empty($cfg) ){
        foreach ( $cfg as $c ){
          $change = [
            'md5' => $c['md5']
          ];
          if ( isset($c['selections']) ){
            $change['selections'] = $c['selections'];
          }
          if ( isset($c['marks']) ){
            $change['marks'] = $c['marks'];
          }
          if ( $pref && !empty($change) ){
            $id_option = $this->option_id($c['name']);
            $pref->set($id_option, $change);
          }
        }
      }
      /*
      $data['file'] = $this->is_mvc($dirs) && (\bbn\str\text::file_ext($data['file']) !== 'php') ?
        substr($data['file'], 0, strrpos($data['file'], "/")) : $data['file'];
      unset($data['act']);
      return 1;
      */
    }
    /*
    if ( isset($_SESSION[BBN_SESS_NAME]['ide']) &&
      in_array($data, $_SESSION[BBN_SESS_NAME]['ide']['list']) ){
      unset($_SESSION[BBN_SESS_NAME]['ide']['list'][array_search($data, $_SESSION[BBN_SESS_NAME]['ide']['list'])]);
      return 1;
    }
    return ['data' => "Tab is not in session."];
    */
  }

  public function copy($dir, $src, $dest){
    if ( isset($data['dir'], $data['path'], $data['src'], $data['name']) &&
      (strpos($data['src'], '../') === false) &&
      (strpos($data['path'], '../') === false) &&
      \bbn\str\text::check_filename($data['name']) ){
      $directories = new directories($this->db);
      $dirs = $directories->dirs();
      if ( isset($dirs[$data['dir']]) ){
        $cfg =& $dirs[$data['dir']];
        $src = $data['src'];
        $type = is_dir($cfg['files'][0]['fpath'].$src) ? 'dir' : 'file';
        $dir_src = dirname($src).'/';
        if ( $dir_src === './' ){
          $dir_src = '';
        }
        $name = \bbn\str\text::file_ext($src, 1)[0];
        $ext = \bbn\str\text::file_ext($src);
        $src_file = $dir_src.$name;
        $dest_file = $data['path'].'/'.$data['name'];
        $todo = [];
        if ( $this->is_mvc($cfg) ){
          foreach ( $cfg['files'] as $f ){
            if ( $f != 'CTRL' ){
              $src = $f['fpath'].$src_file;
              if ( $type === 'file' ){
                $src .= '.'.$f['ext'];
              }
              $is_dir = ($type === 'dir') && is_dir($src);
              $is_file = ($type === 'dir') || $is_dir ? false : is_file($src);
              if ( $is_dir || $is_file ){
                $dest = $f['fpath'].$dest_file;
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

  public function rename($dir, $file, $new){
    if ( isset($data['dir'], $data['name'], $data['path']) &&
      (strpos($data['path'], '../') === false) &&
      \bbn\str\text::check_filename($data['name'])
    ){
      $dirs = $this->dir();
      if ( isset($dirs[$data['dir']]) ){
        $cfg =& $dirs[$data['dir']];
        $path = $data['path'];
        if ( $this->is_mvc($cfg) ){
          $type = is_dir($cfg['files']['Controller']['fpath'].$path) ? 'dir' : 'file';
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
        if ( $this->is_mvc($cfg) ){
          foreach ( $cfg['files'] as $f ){
            if ( $f != 'CTRL' ){
              $src = $f['fpath'].$src_file;
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

  /**
   * Returns
   * @return array
   */
  public function modes(){
    return [
      'html' => [
        'name' => 'HTML',
        'mode' => 'htmlmixed',
        'code' => is_file(BBN_DATA_PATH.'ide/defaults/default.html') ? file_get_contents(BBN_DATA_PATH.'ide/defaults/default.html') : ''
      ],
      'xml' => [
        'name' => 'XML',
        'mode' => 'text/xml',
        'code' => is_file(BBN_DATA_PATH.'ide/defaults/default.xml') ? file_get_contents(BBN_DATA_PATH.'ide/defaults/default.xml') : ''
      ],
      'js' => [
        'name' => 'JavaScript',
        'mode' => 'javascript',
        'code' => is_file(BBN_DATA_PATH.'ide/defaults/default.js') ? file_get_contents(BBN_DATA_PATH.'ide/defaults/default.js') : ''
      ],
      'svg' => [
        'name' => 'SVG',
        'mode' => 'text/xml',
        'code' => is_file(BBN_DATA_PATH.'ide/defaults/default.svg') ? file_get_contents(BBN_DATA_PATH.'ide/defaults/default.svg') : ''
      ],
      'php' => [
        'name' => 'PHP',
        'mode' => 'application/x-httpd-php',
        'code' => is_file(BBN_DATA_PATH.'ide/defaults/default.php') ? file_get_contents(BBN_DATA_PATH.'ide/defaults/default.php') : ''
      ],
      'css' => [
        'name' => 'CSS',
        'mode' => 'text/css',
        'code' => is_file(BBN_DATA_PATH.'ide/defaults/default.css') ? file_get_contents(BBN_DATA_PATH.'ide/defaults/default.css') : ''
      ],
      'less' => [
        'name' => 'LESS',
        'mode' => 'text/x-less',
        'code' => is_file(BBN_DATA_PATH.'ide/defaults/default.css') ? file_get_contents(BBN_DATA_PATH.'ide/defaults/default.css') : ''
      ],
      'sql' => [
        'name' => 'SQL',
        'mode' => 'text/x-sql',
        'code' => is_file(BBN_DATA_PATH.'ide/defaults/default.sql') ? file_get_contents(BBN_DATA_PATH.'ide/defaults/default.sql') : ''
      ],
      'def' => [
        'mode' => 'application/x-httpd-php',
        'code' => is_file(BBN_DATA_PATH.'ide/defaults/default.php') ? file_get_contents(BBN_DATA_PATH.'ide/defaults/default.php') : ''
      ]
    ];
  }
}
