<?php

namespace bbn\Ide;
use bbn;
use bbn\X;

class Directories {

  use bbn\Models\Tts\Optional;

  const IDE_PATH = 'ide',
        DEV_PATH = 'paths',
        PATH_TYPE = 'types',
        FILES_PREF = 'files';

  private static
    /** @var bool|int $appui_path */
    $ide_path = false,
    /** @var bool|int $dev_path */
    $dev_path = false,
    /** @var bool|int $path_type */
    $path_type = false,
    /** @var bool|int $files_pref */
    $files_pref = false;


  protected
    /** @var bbn\Appui\Option $options */
    $options,
    /** @var null|string The last error recorded by the class */
    $last_error,
    /** @var array MVC routes for linking with dirs */
    $routes = [];

  /**
   * Sets the root of the development paths option
   * @param $id
   */
  private static function setIdePath($id){
    self::$ide_path = $id;
  }

  /**
   * Gets the ID of the development paths option
   * @return int
   */
  private function _ide_path(){
    if ( !self::$dev_path ){
      $this->_set_appui();
      if ( $id = $this->options->fromCode(self::IDE_PATH, BBN_APPUI) ){
        self::setIdePath($id);
      }
    }
    return self::$ide_path;
  }

  /**
   * Sets the root of the development paths option
   * @param $id
   */
  private static function setDevPath($id){
    self::$dev_path = $id;
  }

  /**
   * Gets the ID of the development paths option
   * @return int
   */
  private function _dev_path(){
    if ( !self::$dev_path ){
      if ( $id = $this->options->fromCode(self::DEV_PATH, self::IDE_PATH) ){
        self::setDevPath($id);
      }
    }
    return self::$dev_path;
  }

  /**
   * Sets the root of the paths' types option
   * @param $id
   */
  private static function setPathType($id){
    self::$path_type = $id;
  }

  /**
   * Gets the ID of the paths' types option
   * @return int
   */
  private function _path_type(){
    if ( !self::$path_type ){
      if ( $id = $this->options->fromCode(self::PATH_TYPE, self::IDE_PATH) ){
        self::setPathType($id);
      }
    }
    return self::$path_type;
  }

  /**
   * Sets the root of the files' preferences option
   * @param $id
   */
  private static function setFilesPref($id){
    self::$files_pref = $id;
  }

  /**
   * Gets the ID of the paths' types option
   * @return int
   */
  private function _files_pref(){
    if ( !self::$files_pref ){
      if ( $id = $this->options->fromCode(self::FILES_PREF, self::IDE_PATH) ){
        self::setFilesPref($id);
      }
    }
    return self::$files_pref;
  }

  /**
   * Deletes all files' options of a folder and returns an array of these files.
   *
   * @param string $d The folder's path
   * @return array
   */
  private function remDirOpt($d){
    $sub_files = bbn\File\Dir::scan($d);
    $files = [];
    foreach ( $sub_files as $sub ){
      if ( is_file($sub) ){
        // Add it to files to be closed
        array_push($files, $this->realToUrl($sub));
        // Remove file's options
        $this->options->remove($this->options->fromCode($this->realToId($sub), $this->_files_pref()));
      }
      else {
        $f = $this->remDirOpt($sub);
        if ( !empty($f) ){
          $files = array_merge($files, $f);
        }
      }
    }
    return $files;
  }


  /**
   * Checks if the file is a superior super-controller and returns the corrected name and path
   * @param string $tab The tab'name from file's URL
   * @param string $path The file's path from file's URL
   * @return array
   */
  private function superiorSctrl($tab, $path=''){
    if ( ($pos = strpos($tab, '_ctrl')) ){
      // Fix the right path
      $bits = explode('/', $path);
      $count = \strlen(substr($tab, 0, $pos));
      if ( !empty($bits) ){
        while ( $count >= 0 ){
          array_pop($bits);
          $count--;
        }
        $path = implode('/', $bits).(!empty($bits) ? '/' : '');
      }
      // Fix the tab's name
      $tab = '_ctrl';
    }
    return [
      'tab' => $tab,
      'path' => $path
    ];
  }

  /**
   * Sets the last error as the given string.
   *
   * @param string $st
   */
  protected function error($st){
    \bbn\X::log($st, "directories");
    $this->last_error = $st;
  }

  /**
   * Returns true if the error function has been called.
   *
   * @return bool
   */
  public function hasError(){
    return !empty($this->last_error);
  }

  /**
   * Returns last recorded error, and null if none.
   *
   * @return mixed last recorded error, and null if none
   */
  public function getLastError(){
    return $this->last_error;
  }

  /**
   * Constructor.
   *
   * @param bbn\Appui\Option $options
   */
  public function __construct(bbn\Appui\Option $options = null, array $routes = null) {
    if ($options === null) {
      $options = bbn\Appui\Option::getInstance();
    }

    if ($routes === null) {
      $mvc = bbn\Mvc::getInstance();
      if (!$mvc) {
        throw new \Exception("No MVC instance found");
      }

      $routes = $mvc->getRoutes();
    }

    $this->options = $options;
    $this->routes = $routes;
    $this->_ide_path();
  }

  public function addRoutes(array $routes){
    $this->routes = bbn\X::mergeArrays($this->routes, $routes);
    return $this;
  }

  public function mvcDirs(){
    $dirs = $this->dirs();
    $res = [];
    foreach ( $dirs as $i => $d ){
      if ( !empty($d['tabs']) &&
        is_dir(\bbn\Mvc::getAppPath())
      ){
        $d['real_path'] = $this->decipherPath($d['path']);
        $d['prefix'] = strpos($d['real_path'], \bbn\Mvc::getAppPath()) === 0 ? '' : false;
        foreach ( $this->routes as $alias => $route ){
          if ( strpos($d['real_path'], $route) === 0 ){
            $d['prefix'] = $alias.'/';
            break;
          }
        }
        $res[$i] = $d;
      }
    }
    return $res;
  }

  /**
   * Returns the file's URL from the real file's path.
   *
   * @param string $file The real file's path
   * @param bool $mvc If true the function returns the global MVC's URL
   * @return bool|string
   */
  public function realToUrl($file, $mvc = false){
    $dirs = $this->dirs();
    foreach ( $dirs as $i => $d ){
      // Dir's root path (directories)
      $root = $this->getRootPath($i);
      if ( strpos($file, $root) === 0 ){
        $res = $i . '/';
        $bits = explode('/', substr($file, \strlen($root)));
        // MVC
        if ( !empty($d['tabs']) ){
          $tab_path = array_shift($bits);
          $fn = array_pop($bits);
          $ext = bbn\Str::fileExt($fn);
          $fn = bbn\Str::fileExt($fn, 1)[0];
          $res .= implode('/', $bits);
          foreach ( $d['tabs'] as $t ){
            if ( empty($t['fixed']) &&
              ($t['path'] === $tab_path . '/')
            ){
              $res .= '/' . $fn;
              if ( empty($mvc) ){
                $res .= '/' . $t['url'];
              }
              break;
            }
          }
        }
        // Normal file
        else {
          $res .= implode('/', $bits);
        }
        return bbn\Str::parsePath($res);
      }
    }
    return false;
  }

  /**
   * Returns the file's ID from the real file's path.
   *
   * @param string $file The real file's path
   * @todo Fix the slowness!
   * @return bool|string
   */
  public function realToId($file){
    $timer = new bbn\Util\Timer();
    $timer->start('real_to_id');
    $url = self::realToUrl($file);
    $dir = self::dir(self::dirFromUrl($url));
    if ( !empty($dir) &&
      \defined($dir['bbn_path'])
    ){
      $bbn_p = $dir['bbn_path'] === 'BBN_APP_PATH' ? \bbn\Mvc::getAppPath() : constant($dir['bbn_path']); 
      if ( strpos($file, $bbn_p) === 0 ){
        $f = substr($file, \strlen($bbn_p));
        $timer->stop('real_to_id');
        bbn\X::log($timer->results(), "directories");
        return bbn\Str::parsePath($dir['bbn_path'].'/'.$f);
      }
    }

    // OLD VERSION
    /*
    $dirs = $this->dirs();
    $len = 0;
    $bbn_path = '';
    $f = '';
    foreach ( $dirs as $i => $d ){
      if ( !empty($d['bbn_path']) ){
        $bbn_p = constant($d['bbn_path']);
        if ( strpos($file, $bbn_p) === 0 ){
          $p = substr($file, \strlen($bbn_p));
          if ( strpos($p, $d['code']) === 0 ){
            die(var_dump($file, $bbn_p, $p));
            $len_tmp = \count(explode('/', $d['code']));
            if ( $len_tmp > $len ){
              $len = $len_tmp;
              $bbn_path = $d['bbn_path'];
              $f = $p;
            }
          }
        }
      }
    }
    return bbn\Str::parsePath($bbn_path.'/'.$f);
    */
  }

  /**
   * Gets the real file's path from an URL
   *
   * @param string $url The file's URL
   * @return bool|string
   */
  public function urlToReal($url){
    if ( ($dn = $this->dirFromUrl($url)) &&
      ($dir = $this->dir($dn)) &&
      ($res = $this->getRootPath($dn))
    ){
      $bits = explode('/', substr($url, \strlen($dn), \strlen($url)));
      if ( !empty($dir['tabs']) && !empty($bits) ){
        // Tab's nane
        $tab = array_pop($bits);
        // File's name
        $fn = array_pop($bits);
        // File's path
        $fp = implode('/', $bits).'/';
        // Check if the file is a superior super-controller
        $ssc = $this->superiorSctrl($tab, $fp);
        $tab = $ssc['tab'];
        $fp = $ssc['path'];
        if ( !empty($dir['tabs'][$tab]) ){
          $tab = $dir['tabs'][$tab];
          $res .= $tab['path'];
          if ( !empty($tab['fixed']) ){
            $res .= $fp . $tab['fixed'];
          }
          else {
            $res .= $fp . $fn;
            $ext_ok = false;
            foreach ( $tab['extensions'] as $e ){
              $ext = '.' . $e['ext'];
              if ( is_file($res . $ext) ){
                $res .= $ext;
                $ext_ok = true;
                break;
              }
            }
            if ( empty($ext_ok) ){
              $res .= '.' . $tab['extensions'][0]['ext'];
            }
          }
        }
        else {
          return false;
        }
      }
      else {
        // Remove the last element of the path if it's 'code' (it's the tab's URL in a non MVC architecture)
        if ( end($bits) === 'code' ){
          array_pop($bits);
        }
        $res .= implode('/', $bits);
      }
      return bbn\Str::parsePath($res);
    }
    return false;
  }

  /**
   * Returns the dir's name from an URL
   *
   * @param string $url
   * @return bool|int|string
   */
  public function dirFromUrl($url){
    $dir = false;
    foreach ( $this->dirs() as $i => $d ){
      if ( (strpos($url, $i) === 0) &&
        (\strlen($i) > \strlen($dir) )
      ){
        $dir = $i;
        break;
      }
    }
    return $dir;
  }

  /**
   * Returns the real file's path from its ID
   *
   * @param string $id The file's ID
   * @return string
   */
  public function idToReal($id){
    return $this->decipherPath($id);
  }

  /**
   * Returns the file's ID from its URL
   *
   * @param string $url The file's URL
   * @return bool|string
   */
  public function urlToId($url){
    if ( $file = $this->urlToReal($url) ){
      return $this->realToId($file);
    }
    return false;
  }

  /**
   * Returns the file's URL from its ID
   *
   * @param string $id The file's ID
   * @return bool|string
   */
  public function idToUrl($id){
    if ( $file = $this->idToReal($id) ){
      return $this->realToUrl($file);
    }
    return false;
  }

  /**
   * Gets the real root path from a directory's id as recorded in the options.
   *
   * @param string $code The dir's name (code)
   * @return bool|string
   */
  public function getRootPath($code){
    /** @var array $dir The directory configuration */
    $dir = $this->dir($code);
    if ( $dir ){
      $path = $this->decipherPath(bbn\Str::parsePath($dir['bbn_path'].(!empty($dir['path']) ? '/' . $dir['path'] : '')));

      $r = bbn\Str::parsePath($path.'/');
      return $r;
    }
    return false;
  }

  /**
   *
   *
   * @param string $st
   * @return bool|string
   */
  public function decipherPath($st){

    $st = bbn\Str::parsePath($st);
    $bits = explode('/', $st);
    /** @var string $constant The first path of the path which might be a constant */
    $constant = $bits[0];
    /** @var string $path The path that will be returned */
    $path = '';
    if ( \defined($constant) ){      
      $path .= $constant === 'BBN_APP_PATH' ? \bbn\Mvc::getAppPath() : constant($constant);
      array_shift($bits);
    }
    $path .= implode('/', $bits);
    return $path;
  }

  /**
   * @param $data
   * @return array
   */
  public function add($data){
    $data['position'] = $this->db->getOne('
      SELECT MAX(position) AS pos
      FROM bbn_ide_directories') + 1;
    if ( $this->db->insert('bbn_ide_directories', [
      'name' => $data['name'],
      'path' => bbn\Str::parsePath($data['path']),
      'fcolor' => $data['fcolor'],
      'bcolor' => $data['bcolor'],
      'outputs' => \strlen($data['outputs']) ? $data['outputs'] : NULL,
      'files' => $data['files'],
      'position' => $data['position']
    ]) ){
      $data['id'] = $this->db->lastId();
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
      'path' => bbn\Str::parsePath($data['path']),
      'fcolor' => $data['fcolor'],
      'bcolor' => $data['bcolor'],
      'outputs' => \strlen($data['outputs']) ? $data['outputs'] : NULL,
      'files' => $data['files'],
      'position' => $data['position']
    ], ['id' => $data['id']]) ){
      return 1;
    }
    return $this->error('Error: Edit.');
  }

  /**
   * @param string $name
   * @return array
   */
  public function get($name=''){
    $all = $this->options->fullOptions(self::_path_type());
    die(\bbn\X::dump($all));
    if ( empty($name) ){
      return $all;
    }
    else{
      return isset($all[$name]) ? $all[$name] : false;
    }
  }

  /**
   * Make dirs' configurations
   *
   * @param string|bool $code The dir's name (code)
   * @return array|bool
   */
  public function dirs($code=false){
    $all = $this->options->fullSoptions(self::_dev_path());
    $cats = [];
    $r = [];
    foreach ( $all as $a ){
      if ( \defined($a['bbn_path']) ){
        $k = $a['bbn_path'] . '/' . ($a['code'] === '/' ? '' : $a['code']);
        if ( !isset($cats[$a['id_alias']]) ){
          unset($a['alias']['cfg']);
          $cats[$a['id_alias']] = $a['alias'];
        }
        unset($a['cfg']);
        unset($a['alias']);
        $r[$k] = $a;
        $r[$k]['title'] = $r[$k]['text'];
        $r[$k]['alias_code'] = $cats[$a['id_alias']]['code'];
        if ( !empty($cats[$a['id_alias']]['tabs']) ){
          $r[$k]['tabs'] = $cats[$a['id_alias']]['tabs'];
        }
        else{
          $r[$k]['extensions'] = $cats[$a['id_alias']]['extensions'];
        }
        unset($r[$k]['alias']);
      }
    }
    if ( $code ){
      return isset($r[$code]) ? $r[$code] : false;
    }
    return $r;
  }

  /**
   * Gets a dir's configuration
   *
   * @param string $code The dir's name (code)
   * @return array|bool
   */
  public function dir($code){
    return $this->dirs($code);
  }

  public function optionId($file_id){
    return $this->options->get_id_or_create($file_id, $this->_files_pref());
  }

  public function hasOption($file_id){
    return $this->options->has_id($file_id, $this->_files_pref());
  }

  /**
   * Creates a a new file or a new directory.
   *
   * @param string $dir The source's name
   * @param string|false $tab The tab's name (MVC)
   * @param string $path The file/directory's path
   * @param string $name The file/directory's name
   * @param string $type If it's a file or a directory (file|dir)
   * @return string|void
   */
  public function create($dir, $tab, $path, $name, $type){
    if ( ($cfg = $this->dir($dir)) &&
      ($root = $this->getRootPath($dir))
    ){
      $path = $path === './' ? '' : $path . '/';
      $ext = bbn\Str::fileExt($name);
      $default = '';

      // MVC
      if ( !empty($cfg['tabs']) &&
        !empty($tab)
      ){
        $cfg = $cfg['tabs'][$tab];
        $root = $root . $cfg['path'];
      }
      // New file
      if ( $type === 'file' ){
        if ( !empty($ext) ){
          $ext_ok = array_filter($cfg['extensions'], function($e) use ($ext){
            return ( $e['ext'] === $ext );
          });
          if ( !empty($ext_ok) ){
            $default = array_values($ext_ok)[0]['default'];
          }
        }
        if ( empty($ext) ||
          (!empty($ext) && empty($ext_ok))
        ){
          $ext = $cfg['extensions'][0]['ext'];
          $default = $cfg['extensions'][0]['default'];
        }
        $file = $path . bbn\Str::fileExt($name, 1)[0] . '.' . $ext;
        $real = $root . $file;
        if ( is_file($real) ){
          return $this->error("The file already exists");
        }
        if ( !bbn\File\Dir::createPath(X::dirname($real)) ){
          return $this->error("Impossible to create the container directory");
        }
        if ( !file_put_contents($real, $default) ){
          return $this->error("Impossible to create the file");
        }
        // Add item to options table for permissions
        if ( $tab === 'php' ){
          if ( !$this->createPermByReal($real) ){
            return $this->error("Impossible to create the option");
          }
        }
      }
      //New directory
      else if ( $type === 'dir' ){
        $file = $path . '/' . $name;
        $real = $root . $file;
        if ( is_dir($real) ){
          return $this->error("The directory already exists");
        }
        if ( !bbn\File\Dir::createPath($real) ){
          return $this->error("Impossible to create the directory");
        }
      }
      return $file;
    }
    return $this->error("There is a problem in the name (dir) you entered");
  }

  /**
   * Loads a file.
   *
   * @param string $file
   * @param string|array $dir
   * @param string $tab
   * @param bbn\User\Preferences|null $pref
   * @return array|bool
   */
  public function load($file, $dir, $tab, bbn\User\Preferences $pref = null){
    /** @var boolean|array $res */
    $res = false;
    $file = bbn\Str::parsePath($file);

    if ( $file && $dir ){
      /** @var array $dir_cfg The directory configuration from DB */
      if ( \is_array($dir) ){
        $dir_cfg = $dir;
        $dir = $dir_cfg['value'] ?? $dir_cfg['path'];
      }
      else{
        $dir_cfg = $this->dir($dir);
      }
      if ( !\is_array($dir_cfg) ){
        die(\bbn\X::dump("Problem with the function Directories::dir with argument ".$dir));
      }
      $res = $this->getFile($file, $dir, $tab, $dir_cfg, $pref);
    }
    return $res;
  }

  protected function getTab(string $url, string $title, array $cfg){
    return [
      'url' => bbn\Str::parsePath($url),
      'title' => $title,
      'load' => 1,
      'bcolor' => $cfg['bcolor'],
      'fcolor' => $cfg['fcolor']
    ];
  }

  /**
   * Gets a file
   *
   * @param string $file
   * @param string $dir
   * @param string $tab
   * @param array $cfg
   * @param bbn\User\Preferences|null $pref
   * @return array
   */
  protected function getFile($file, $dir, $tab, array $cfg, bbn\User\Preferences $pref = null){
    if ( isset($cfg['title'], $cfg['bcolor'], $cfg['fcolor']) ){
      /** @var string $name The file's name - without path and extension */
      $name = bbn\Str::fileExt($file, 1)[0];
      /** @var string $ext The file's extension */
      $ext = bbn\Str::fileExt($file);
      /** @var string $path The file's path without file's name  */
      $path = X::dirname($file) !== '.' ? X::dirname($file) . '/' : '';
      $url = $dir . $path . $name;

      $r = $this->getTab($url, $cfg['title'], $cfg);
      $timer = new bbn\Util\Timer();
      /** @var string $root_path The real/actual path to the root directory */
      $root_path = $this->getRootPath($dir);
      $is_file = true;
      // Normal Tab
      if ( empty($tab) && empty($cfg['url']) ){
        $real_file = $root_path . $file;
        $r['url'] = $dir . $file;
        $r['title'] = $file;
        $r['list'] = [[
          'bcolor' => $r['bcolor'],
          'fcolor' => $r['fcolor'],
          'title' => 'Code',
          'url' => 'code',
          'static' => 1,
          'default' => 1,
          'file' => $real_file
        ]];
        //$r['file'] = $real_file;
        foreach ( $cfg['extensions'] as $e ){
          if ( $e['ext'] === $ext ){
            $mode = $e['mode'];
          }
        }
        if ( !is_file($real_file) ){
          $is_file = false;
          $this->error('Impossible to find the file ' . $real_file);
          return false;
        }
      }
      // MVC's Tab
      else {
        $r['url'] = $cfg['url'];
        $r['static'] = 1;
        if ( empty($tab) ){
          $r['default'] = !empty($cfg['default']) ? true : false;
        }
        else {
          $r['default'] = ( $cfg['url'] === $tab ) ? true : false;
        }
        // _CTRL
        if ( !empty($cfg['fixed']) ){
          $ext = bbn\Str::fileExt($cfg['fixed']);
          foreach ( $cfg['extensions'] as $e ){
            if ( $e['ext'] === $ext ){
              $file = X::dirname($file) . '/' . $cfg['fixed'];
              $real_file = $root_path . $file;
              $mode = $e['mode'];
              $r['file'] = $real_file;
              if ( !is_file($real_file) ){
                $is_file = false;
                $value = $e['default'];
              }
              break;
            }
          }
        }
        else {
          foreach ( $cfg['extensions'] as $e ){
            $ext = $e['ext'];
            /** @var string $real_file The absolute full path to the file */
            $real_file = $root_path . $file . '.' . $ext;
            if ( is_file($real_file) ){
              $r['file'] = $real_file;
              $mode = $e['mode'];
              // Permissions
              if ( ($id_opt = $this->realToPerm($real_file)) &&
                ($opt = $this->options->option($id_opt))
              ){
                $r['perm_id'] = $opt['id'];
                $r['perm_code'] = $opt['code'];
                $r['perm_text'] = $opt['text'];
                if ( isset($opt['help']) ){
                  $r['perm_help'] = $opt['help'];
                }
                $sopt = $this->options->fullOptions($opt['id']);
                $perm_chi = [];
                foreach ( $sopt as $so ){
                    array_push($perm_chi, [
                      'perm_code' => $so['code'],
                      'perm_text' => $so['text']
                    ]);
                }
                $r['perm_children'] = $perm_chi;
              }
              break;
            }
          }
          if ( empty($mode) ){
            $value = $cfg['extensions'][0]['default'];
          }
        }
      }

      // Timing problem is here, check out timer below and logs
      // Do we need to have a single preference for each tab?
      // Guilty: real_to_id takes 0.15 sec and is called 10+ times
      $timer->start();
      // User's preferences
      if ( $is_file &&
        $pref &&
        ($id_option = $this->options->fromCode($this->realToId($real_file), $this->_files_pref()))
      ){
        $o = $pref->get($id_option);
      }
      $timer->stop();
      if ( empty($tab) && empty($cfg['url']) ){
        $r['list'][0]['id_script'] = $this->realToId($real_file);
        $r['list'][0]['cfg'] = [
          'mode' => !empty($mode) ? $mode : $cfg['extensions'][0]['mode'],
          'value' => empty($value) ? file_get_contents($real_file) : $value,
          'selections' => !empty($o['selections']) ? $o['selections'] : [],
          'marks' => !empty($o['marks']) ? $o['marks'] : []
        ];
      }
      else {
        $r['id_script'] = $this->realToId($real_file);
        $r['cfg'] = [
          'mode' => !empty($mode) ? $mode : $cfg['extensions'][0]['mode'],
          'value' => empty($value) ? file_get_contents($real_file) : $value,
          'selections' => !empty($o['selections']) ? $o['selections'] : [],
          'marks' => !empty($o['marks']) ? $o['marks'] : []
        ];
      }
      bbn\X::log($timer->results(), "directories");
      return $r;
    }
  }

  /**
   * Saves a file.
   *
   * @param string $file The file's URL
   * @param string $code The file's content
   * @param array|null $cfg The user preferences
   * @param bbn\User\Preferences|null $pref
   * @return array|void
   */
  public function save($file, $code, array $cfg = null, bbn\User\Preferences $pref = null){
    die(var_dump($file, $code, $cfg ));
    if ( ($file = bbn\Str::parsePath($file)) &&
      ($real = $this->urlToReal($file)) &&
      ($dir = $this->dir($this->dirFromUrl($file))) &&
      \defined('BBN_USER_PATH')
    ){
      $id_file = $this->realToId($real);
      $ext = bbn\Str::fileExt($real, 1);
      $id_user = false;
      if ( $session = bbn\User\Session::getInstance() ){
        $id_user = $session->get('user', 'id');
      }
      // We delete the file if code is empty and we aren't in a _ctrl file
      if ( empty($code) ){
        $bits = explode('/', $file);
        if ( !empty($dir['tabs']) && !empty($bits) ){
          $tab = $this->superiorSctrl(array_pop($bits))['tab'];
          if ( !empty($dir['tabs'][$tab]) &&
            empty($dir['tabs'][$tab]['fixed'])
          ){
            if ( @unlink($real) ){
              // Remove permissions
              $this->deletePerm($real);
              if ( $id_file ){
                // Remove file's options
                $this->options->remove($this->options->fromCode($id_file, $this->_files_pref()));
                // Remove ide backups
                bbn\File\Dir::delete(X::dirname(BBN_USER_PATH."ide/backup/$id_file")."/$ext[0]/", 1);
              }
              return [
                'deleted' => 1
              ];
            }
          }
        }
      }
      if ( is_file($real) && $id_file ){
        $filename = empty($dir['tabs']) ? $ext[0].'.'.$ext[1] : $ext[0];
        $backup = X::dirname(BBN_USER_PATH."ide/backup/".$id_file).'/'.$filename.'/'.date('Y-m-d His').'.'.$ext[1];
        bbn\File\Dir::createPath(X::dirname($backup));
        rename($real, $backup);
      }
      else if ( !is_dir(X::dirname($real)) ){
        bbn\File\Dir::createPath(X::dirname($real));
      }
      file_put_contents($real, $code);
      if ( $pref && $id_user ){
        $this->setPreferences($id_user, $id_file, md5($code), $cfg, $pref);
      }
      return [
        'success' => 1,
        'path' => $real
      ];
    }
    return $this->error('Error: Save');
  }

  /**
   * Sets user's preferences for a file.
   *
   * @param string $id_user The user's id
   * @param string $id_file The file's id
   * @param string $md5 The file's md5
   * @param array|null $cfg
   * @param bbn\User\Preferences|null $pref
   * @return bool
   */
  public function setPreferences($id_user, $id_file, $md5, array $cfg = null, bbn\User\Preferences $pref = null){
    if ( !empty($id_user) && !empty($id_file) && !empty($pref) ){
      $change['md5'] = $md5;
      if ( !empty($cfg['selections']) ){
        $change['selections'] = $cfg['selections'];
      }
      if ( isset($cfg, $cfg['marks']) ){
        $change['marks'] = $cfg['marks'];
      }
      if ( !empty($change) ){
        $id_option = $this->optionId($id_file);
        if ( $pref->set($id_option, $change, $id_user) ){
          return true;
        }
      }
    }
    return false;
  }

  /**
   * Duplicates a file or a directory, MVC or not.
   *
   * @param string $dir The source's name
   * @param string $path The new file's path
   * @param string $name The new filename
   * @param string $type file|dir
   * @param string $file The existing file path and name
   * @return bool|string
   * @todo Duplicate the users' permissions when duplicating a controller file
   */
  public function copy($dir, $path, $name, $type, $file){
    if ( ($cfg = $this->dir($dir)) &&
      ($root = $this->getRootPath($dir)) &&
      bbn\Str::checkFilename($name)
    ){
      $is_file = $type === 'file';
      $wtype = $is_file ? 'file' : 'directory';
      $path = $path === './' ? '' : $path . '/';
      $bits = explode('/', $file);
      // File cfg
      $file_cfg =  bbn\Str::fileExt(array_pop($bits), 1);
      // Existing filename without its extension
      $fn = $file_cfg[0];
      // Existing file's extension
      $fe = $file_cfg[1];
      // Existing file's path
      $fp = implode('/', $bits);
      $files = [];
      $ext = false;
      // MVC
      if ( !empty($cfg['tabs']) ){
        foreach ( $cfg['tabs'] as $t ){
          if (empty($t['fixed']) ){
            if ( $is_file ){
              // Check all extensions
              foreach ( $t['extensions'] as $e ){
                $real = $root . $t['path'] . $fp . '/' . $fn . '.' . $e['ext'];
                $real_new = $root. $t['path'] . $path . $name . '.' . $e['ext'];
                if( file_exists($real) ){
                  if ( !file_exists($real_new) ){
                    $files[$real] = $real_new;
                    $ext = empty($ext) ? $e['ext'] : $ext;
                    if ( $t['url'] === 'php' ){
                      $perms = $real_new;
                    }
                  }
                  else {
                    $this->error("The file $real_new is already exists.");
                    return false;
                  }
                }
              }
            }
            else {
              $real = $root . $t['path'] . $fp . '/' . $fn;
              $real_new = $root. $t['path'] . $path . $name;
              if ( file_exists($real) ){
                if ( !file_exists($real_new) ){
                  $files[$real] = $real_new;
                  if ( $t['url'] === 'php' ){
                    $perms = $real_new;
                  }
                }
                else {
                  $this->error("The directory $real_new is already exists.");
                  return false;
                }
              }
            }
          }
        }
      }
      else {
        $real = $root . $file;
        $real_new = $root. $path . $name . '.' . $fe;
        if ( file_exists($real) ){
          if ( !file_exists($real_new) ){
            $files[$real] = $real_new;
          }
          else {
            $this->error("The $wtype $real_new is already exists.");
            return false;
          }
        }
      }
      foreach ($files as $s => $d ){
        if ( !file_exists(X::dirname($d)) ){
          if ( !bbn\File\Dir::createPath(X::dirname($d)) ){
            $this->error("Impossible to create the path $d");
            return false;
          }
        }
        if ( !bbn\File\Dir::copy($s, $d) ){
          $this->error("Impossible to duplicate the $wtype: $s -> $d");
          return false;
        }
      }

      // Create permissions
      if ( !empty($perms) ){
        if ( $is_file ){
          self::createPermByReal($perms);
        }
        else {
          $dir_perms = function($fd) use(&$dir_perms){
            foreach ( $fd as $f ){
              if ( is_file($f) &&
                (X::basename($f) !== '_ctrl.php')
              ){
                self::createPermByReal($f);
              }
              else if ( is_dir($f) ){
                $dir_perms(bbn\File\Dir::getFiles($f, 1));
              }
            }
          };
          $dir_perms(bbn\File\Dir::getFiles($perms, 1));
        }
      }

      if ( $is_file ){
        return (!empty($path) ? $path : '') . $name . '.' . (!empty($cfg['tabs']) ? $ext : $fe);
      }
      return true;
    }
    return false;
  }

  /**
   * Deletes a file or a directory.
   *
   * @param string $dir The source's name
   * @param string $path The file|directory's path
   * @param string $name The file|directory's name
   * @param string $type The type (file|dir)
   * @return array|bool
   */
  public function delete($dir, $path, $name, $type = 'file'){
    if ( ($cfg = $this->dir($dir)) &&
      ($root = $this->getRootPath($dir))
    ){
      $is_file = $type === 'file';
      $wtype = $is_file ? 'file' : 'directory';
      $delete = [];
      if ( !empty($cfg['tabs']) ){
        foreach ( $cfg['tabs'] as $t ){
          if ( empty($t['fixed']) ){
            $real = $root . $t['path'];
            if ( X::dirname($path) !== '.' ){
              $real .= X::dirname($path) . '/';
            }
            if ( $is_file ){
              foreach ( $t['extensions'] as $e ){
                $tmp = $real . $name . '.' . $e['ext'];
                if ( file_exists($tmp) && !\in_array($tmp, $delete) ){
                  array_push($delete, $tmp);
                  if ( $t['url'] === 'php' ){
                    $del_perm = $tmp;
                  }
                }
              }
            }
            else {
              $real .= $name;
              if ( file_exists($real) && !\in_array($real, $delete) ){
                array_push($delete, $real);
                if ( $t['url'] === 'php' ){
                  $del_perm = $real;
                }
              }
            }
          }
        }
      }
      else {
        $real = $root . $path;
        if ( file_exists($real) ){
          array_push($delete, $real);
        }
      }
      $files = [];
      // Remove permissions
      if ( !empty($del_perm) ){
        $this->deletePerm($del_perm, $type);
      }
      foreach ( $delete as $d ){
        if ( $is_file ){
          // Add it to files to be closed
          array_push($files, $this->realToUrl($d));
          // Delete file
          if ( !unlink($d) ){
            $this->error("Impossible to delete the file $d");
            return false;
          }
          // Remove file's options
          $this->options->remove($this->options->fromCode($this->realToId($d), $this->_files_pref()));
        }
        else {
          $f = $this->remDirOpt($d);
          $files = array_merge($files, $f);
          // Delete directory
          if ( !bbn\File\Dir::delete($d) ){
            $this->error("Impossible to delete the directory $d");
            return false;
          }
        }
      }
      return ['files' => $files];
    }
    return false;
  }

  /**
   * Exports a file or a directory, normal or MVC.
   *
   * @param string $dir The source's name
   * @param string $path The file|directory's path
   * @param string $name The file|directory's name
   * @param string $type file|dir
   * @return bool
   */
  public function export($dir, $path, $name, $type = 'file'){
    if ( ($cfg = $this->dir($dir)) &&
      ($root = $this->getRootPath($dir)) &&
      \defined('BBN_USER_PATH')
    ){
      $is_file = $type === 'file';
      $wtype = $is_file ? 'file' : 'directory';
      $rnd = bbn\Str::genpwd();
      $root_dest = BBN_USER_PATH . 'tmp/' . $rnd . '/';
      $files = [];
      if ( !empty($cfg['tabs']) ){
        $root_dest_mvc = $root_dest . $name . '/mvc/';
        foreach ( $cfg['tabs'] as $t ){
          if ( empty($t['fixed']) ){
            $real = $t['path'];
            if ( X::dirname($path) !== '.' ){
              $real .= X::dirname($path) . '/';
            }
            if ( $is_file ){
              foreach ( $t['extensions'] as $e ){
                if ( is_file($root . $real . $name . '.' . $e['ext']) ){
                  $real .= $name . '.' . $e['ext'];
                  array_push($files, [
                    'src' => $root . $real,
                    'dest' => $root_dest_mvc . $real,
                    'is_file' => $is_file
                  ]);
                  break;
                }
              }
            }
            else {
              $real .= $name;
              if ( is_dir($root . $real) ){
                array_push($files, [
                  'src' =>$root . $real,
                  'dest' => $root_dest_mvc . $real,
                  'is_file' => $is_file
                ]);
              }
            }
          }
        }
      }
      else {
        if ( file_exists($root . $path) ){
          array_push($files, [
            'src' => $root . $path,
            'dest' => $root_dest . $path,
            'is_file' => $is_file
          ]);
        }
      }
      foreach ( $files as $f ){
        if ( $f['is_file'] ){
          if ( !bbn\File\Dir::createPath(X::dirname($f['dest'])) ){
            $this->error("Impossible to create the path " . X::dirname($f['dest']));
            return false;
          }
        }
        if ( !bbn\File\Dir::copy($f['src'], $f['dest']) ){
          $this->error('Impossible to export the ' . $wtype . ' ' . $f['src']);
          return false;
        }
      }

      if ( class_exists('\\ZipArchive') ){
        $filezip = BBN_USER_PATH.'tmp/'.$name.'.zip';
        $zip = new \ZipArchive();
        if ( $err = $zip->open($filezip, \ZipArchive::OVERWRITE) ){
          if ( file_exists($root_dest) ){
            if ( (!$is_file) || !empty($cfg['tabs']) ){
              // Create recursive directory iterator
              $files = bbn\File\Dir::scan($root_dest);
              foreach ($files as $file){
                $tmp_dest = str_replace(
                  $root_dest . (empty($cfg['tabs']) ? '/' : ''),
                  (!empty($cfg['tabs']) ? 'mvc/' : ''),
                  $file
                );
                // Add current file to archive
                if ( ($file !== $root_dest.$name) &&
                  is_file($file) &&
                  !$zip->addFile($file, $tmp_dest)
                ){
                  $this->error("Impossible to add $file");
                  return false;
                }
              }
            }
            else {
              if ( !$zip->addFile($root_dest, $path) ){
                $this->error("Impossible to add $root_dest");
                return false;
              }
            }
            if ( $zip->close() ){
              if ( !bbn\File\Dir::delete(BBN_USER_PATH . 'tmp/' . $rnd, 1) ){
                $this->error("Impossible to delete the directory " . BBN_USER_PATH . 'tmp/' . $rnd);
                return false;
              }
              return $filezip;
            }
            $this->error("Impossible to close the zip file $filezip");
            return false;
          }
          $this->error("The path does not exist: $root_dest");
          return false;
        }
        $this->error("Impossible to create $filezip ($err)");
        return false;
      }

      $this->error("ZipArchive class non-existent");
      return false;
    }
  }

  /**
   * Renames a file or a directory.
   *
   * @param string $dir The source's name
   * @param string $path The file|directory's old path (included filename and its extension)
   * @param string $new The new file's name
   * @param string $type file|dir
   * @return array|bool
   */
  public function rename($dir, $path, $new, $type = 'file'){
    if ( ($cfg = $this->dir($dir)) &&
      ($root = $this->getRootPath($dir)) &&
      bbn\Str::checkFilename($new)
    ){
      $is_file = $type === 'file';
      $wtype = $is_file ? 'file' : 'directory';
      $pi = X::pathinfo($path);
      $files = [];
      if ( $pi['filename'] !== $new ){
        if ( !empty($cfg['tabs']) ){
          $ext = false;
          foreach ( $cfg['tabs'] as $t ){
            if ( empty($t['fixed']) ){
              // MVC tab's path
              $real = $root . $t['path'];
              if ( $pi['dirname'] !== '.' ){
                $real .= $pi['dirname'] . '/';
              }
              if ( $is_file ){
                foreach ( $t['extensions'] as $e ){
                  $real_new = $real . $new . '.' . $e['ext'];
                  $real_ext = $real . $pi['filename'] . '.' . $e['ext'];
                  if ( file_exists($real_ext) ){
                    if ( !file_exists($real_new) ){
                      $ext = empty($ext) ? $e['ext'] : $ext;
                      $files[$real_ext] = $real_new;
                      if ( $t['url'] === 'php' ){
                        $change_perm = [
                          'old' => $real_ext,
                          'new' => $real_new,
                          'type' => 'file'
                        ];
                      }
                    }
                    else {
                      $this->error("The file $real_new is already exists.");
                      return false;
                    }
                  }
                }
                if ( !empty($t['default']) ){
                  $file_url = $this->realToUrl($real_ext);
                  $file_new_url = $this->realToUrl($real_new);
                }
              }
              else {
                $real_new = $real . $new;
                $real .= $pi['filename'];
                if ( file_exists($real) ){
                  if ( !file_exists($real_new) ){
                    $files[$real] = $real_new;
                    if ( $t['url'] === 'php' ){
                      $change_perm = [
                        'old' => $real,
                        'new' => $real_new,
                        'type' => 'dir'
                      ];
                    }
                  }
                  else {
                    $this->error("The directory $real_new is already exists.");
                    return false;
                  }
                }
              }
              if ( !empty($t['default']) ){
                $file_new = (($pi['dirname'] !== '.') ? $pi['dirname'] . '/' : '') . $new;
                $file_new_name = (($pi['dirname'] !== '.') ? $pi['dirname'] . '/' : '') . $new;
              }
            }
          }
        }
        else {
          $real = $root . $path;
          $real_new = $root . $new . ($is_file ?  '.' . $pi['extension'] : '');
          if ( file_exists($real) ){
            if ( !file_exists($real_new) ){
              $files[$real] = $real_new;
            }
            else {
              $this->error("The $wtype $real_new is already exists.");
              return false;
            }
            if ( $is_file ){
              $file_url = $this->realToUrl($real);
              $file_new_url = $this->realToUrl($real_new);
              $file_new = (($pi['dirname'] !== '.') ? $pi['dirname'] . '/' : '') . $new . '.' . $pi['extension'];
              $file_new_name = (($pi['dirname'] !== '.') ? $pi['dirname'] . '/' : '') . $new . '.' . $pi['extension'];
            }
            else {
              $file_new = (($pi['dirname'] !== '.') ? $pi['dirname'] . '/' : '') . $new;
              $file_new_name = (($pi['dirname'] !== '.') ? $pi['dirname'] . '/' : '') . $new;
            }
          }
        }

        foreach ( $files as $s => $d ){
          if ( !rename($s, $d) ){
            $this->error("Impossible to rename the $wtype: $s -> $d");
            return false;
          }
          if ( is_file($s) ){
            // Remove file's options
            $this->options->remove($this->options->fromCode($this->realToId($s), $this->_files_pref()));
          }
          else {
            $this->remDirOpt($s);
          }
        }

        // Change permission
        if ( !empty($change_perm) ){
          $this->changePermByReal($change_perm['old'], $change_perm['new'], $change_perm['type']);
        }

        return [
          'file_url' => $file_url,
          'file_new_url' => $file_new_url,
          'file_new' => $file_new,
          'file_new_name' => $file_new_name,
          'file_new_ext' => $ext
        ];
      }
      $this->error("The old name and the new name are identical.");
      return false;
    }
  }

  /**
   * Moves a file or a directory.
   *
   * @param string $dir The source's name
   * @param string $src The file|directory's old path (included filename and its extension)
   * @param string $dest The destination path
   * @param string $type file|dir
   * @return array|bool
   */
  public function move($dir, $src, $dest, $type = 'file'){
    if ( ($cfg = $this->dir($dir)) &&
      ($root = $this->getRootPath($dir))
    ){
      $is_file = $type === 'file';
      $wtype = $is_file ? 'file' : 'directory';
      $pi = X::pathinfo($src);
      $pi['dirname'] = $pi['dirname'] === '.' ? '' : $pi['dirname'];
      $files = [];
      if ( $pi['dirname'] !== $dest ){
        if ( !empty($cfg['tabs']) ){
          $ext = false;
          foreach ( $cfg['tabs'] as $t ){
            if ( empty($t['fixed']) ){
              // MVC tab's path
              $real = $root . $t['path'];
              if ( $is_file ){
                foreach ( $t['extensions'] as $e ){
                  $real_new = $real . $dest . '/' . $pi['filename']. '.' . $e['ext'];
                  $real_ext = $real . $pi['dirname'] . '/' . $pi['filename'] . '.' . $e['ext'];
                  if ( file_exists($real_ext) ){
                    if ( !file_exists($real_new) ){
                      $ext = empty($ext) ? $e['ext'] : $ext;
                      $files[$real_ext] = $real_new;
                      if ( $t['url'] === 'php' ){
                        $change_perm = [
                          'old' => $real_ext,
                          'new' => $real_new,
                          'type' => 'file'
                        ];
                      }
                    }
                    else {
                      $this->error("The file $real_new is already exists.");
                      return false;
                    }
                  }
                }
                if ( !empty($t['default']) ){
                  $file_url = $this->realToUrl($real_ext);
                  $file_new_url = $this->realToUrl($real_new);
                }
              }
              else {
                $real_new = $real . $dest . '/' . $pi['basename'];
                $real .= $src;
                if ( file_exists($real) ){
                  if ( !file_exists($real_new) ){
                    $files[$real] = $real_new;
                    if ( $t['url'] === 'php' ){
                      $change_perm = [
                        'old' => $real,
                        'new' => $real_new,
                        'type' => 'dir'
                      ];
                    }
                  }
                  else {
                    $this->error("The directory $real_new is already exists.");
                    return false;
                  }
                }
              }
              if ( !empty($t['default']) ){
                $file_new = $dest . '/' . $pi['basename'];
              }
            }
          }
        }
        else {
          $real = $root . $src;
          $real_new = $root . $dest . '/' . $pi['basename'];
          if ( file_exists($real) ){
            if ( !file_exists($real_new) ){
              $files[$real] = $real_new;
            }
            else {
              $this->error("The $wtype $real_new is already exists.");
              return false;
            }
            if ( $is_file ){
              $file_url = $this->realToUrl($real);
              $file_new_url = $this->realToUrl($real_new);
            }
            $file_new = $dest . '/' . $pi['basename'];
          }
        }

        foreach ( $files as $s => $d ){
          if ( !bbn\File\Dir::move($s, $d) ){
            $this->error("Impossible to rename the $wtype: $s -> $d");
            return false;
          }
          if ( is_file($s) ){
            // Remove file's options (preferences)
            $this->options->remove($this->options->fromCode($this->realToId($s), $this->_files_pref()));
          }
          else {
            // Remove dir's options (preferences)
            $this->remDirOpt($s);
          }
        }

        // Change permission
        if ( !empty($change_perm) ){
          $this->changePermByReal($change_perm['old'], $change_perm['new'], $change_perm['type']);
        }

        return [
          'file_url' => $file_url,
          'file_new_url' => $file_new_url,
          'file_new' => $file_new,
        ];
      }
      $this->error("The old name and the new name are identical.");
      return false;
    }
  }

  /**
   * Changes the extension to a file.
   *
   * @param string $ext The new extension
   * @param string $file The file to change
   * @return array
   */
  public function changeExt($ext, $file){
    if ( !empty($ext) &&
      !empty($file) &&
      file_exists($file)
    ){
      $pi = X::pathinfo($file);
      $new = $pi['dirname'].'/'.$pi['filename'].'.'.$ext;
      bbn\File\Dir::move($file, $new, true);
      return [
        'file' => $new,
        'file_url' => $this->realToUrl($new)
      ];
    }
    $this->error("Error.");
  }

  /**
   * Returns the permission's id from a real file/dir's path
   *
   * @param string $file The real file/dir's path
   * @param string $type The type (file/dir)
   * @return bool|int
   */
  public function realToPerm($file, $type='file'){
    if ( !empty($file) &&
      is_dir(\bbn\Mvc::getAppPath()) &&
      // It must be a controller
      (strpos($file, '/mvc/public/') !== false)
    ){
      $is_file = $type === 'file';
      // Check if it's an external route
      foreach ( $this->routes as $i => $r ){
        if ( strpos($file, $r) === 0 ){
          // Remove route
          $f = substr($file, \strlen($r), \strlen($file));
          // Remove /mvc/public
          $f = substr($f, \strlen('/mvc/public'), \strlen($f));
          // Add the route's name to path
          $f = $i . $f;
          break;
        }
      }
      // Internal route
      if ( empty($f) ){
        $root_path = \bbn\Mvc::getAppPath().'mvc/public/';
        if ( strpos($file, $root_path) === 0 ){
          // Remove root path
          $f = substr($file, \strlen($root_path), \strlen($file));
        }
      }
      $id_parent = $this->options->fromCode('page', 'bbn_permissions');
      if ( !empty($f) ){
        $bits = bbn\X::removeEmpty(explode('/', $f));
        $code = $is_file ? bbn\Str::fileExt(array_pop($bits), 1)[0] : array_pop($bits).'/';
        foreach ( $bits as $b ){
          $id_parent = $this->options->fromCode($b.'/', $id_parent);
        }

        return $this->options->fromCode($code, $id_parent);
      }
    }
    return false;
  }

  /**
   * Creates a permission option from a real file/dir's path
   *
   * @param string $file The real file/dir's path
   * @param string $type The type of real (file/dir)
   * @return bool
   */
  public function createPermByReal($file, $type='file'){
    if ( !empty($file) &&
      is_dir(\bbn\Mvc::getAppPath()) &&
      file_exists($file) &&
      // It must be a controller
      (strpos($file, '/mvc/public/') !== false)
    ){
      $is_file = $type === 'file';
      // Check if it's an external route
      foreach ( $this->routes as $i => $r ){
        if ( strpos($file, $r) === 0 ){
          // Remove route
          $f = substr($file, \strlen($r), \strlen($file));
          // Remove /mvc/public
          $f = substr($f, \strlen('/mvc/public'), \strlen($f));
          // Add the route's name to path
          $f = $i . $f;
        }
      }
      // Internal route
      if ( empty($f) ){
        $root_path = \bbn\Mvc::getAppPath().'mvc/public/';
        if ( strpos($file, $root_path) === 0 ){
          // Remove root path
          $f = substr($file, \strlen($root_path), \strlen($file));
        }
      }
      if ( !empty($f) ){
        $bits = bbn\X::removeEmpty(explode('/', $f));
        $code = $is_file ? bbn\Str::fileExt(array_pop($bits), 1)[0] : array_pop($bits).'/';
        $id_parent = $this->options->fromCode('page', 'bbn_permissions');
        foreach ( $bits as $b ){
          if ( !$this->options->fromCode($b.'/', $id_parent) ){
            $this->options->add([
              'id_parent' => $id_parent,
              'code' => $b.'/',
              'text' => $b
            ]);
          }
          $id_parent = $this->options->fromCode($b.'/', $id_parent);
        }
        if ( !$this->options->fromCode($code, $id_parent) ){
          $this->options->add([
            'id_parent' => $id_parent,
            'code' => $code,
            'text' => $code
          ]);
        }
        return $this->options->fromCode($code, $id_parent);
      }
      else if ( !$is_file ){
        return $this->options->fromCode('page', 'bbn_permissions');
      }
      return true;
    }
    return false;
  }

  /**
   * Changes permissions to a file/dir from the old and new real file/dir's path
   *
   * @param string $file The old real file/dir's path
   * @param string $file_new The new real file/dir's path
   * @param string $type The type (file/dir)
   * @return bool
   */
  public function changePermByReal($file, $file_new, $type='file'){
    if ( !empty($file) &&
      !empty($file_new) &&
      file_exists($file_new) &&
      ($id_opt = $this->realToPerm($file, $type)) &&
      !$this->realToPerm($file_new, $type)
    ){
      $is_file = $type === 'file';
      $code = $is_file ? bbn\Str::fileExt(X::basename($file_new), 1)[0] : X::basename($file_new).'/';
      if ( ($id_parent = $this->createPermByReal(X::dirname($file_new).'/', 'dir'))
      ){
        $this->options->setProp($id_opt, ['code' => $code]);
        $this->options->move($id_opt, $id_parent);
        return true;
      }
    }
    return false;
  }

  /**
   * Deletes permission from a real file/dir's path
   *
   * @param string $file The real file/dir's path
   * @param string $type The type (file/dir)
   * @return bool
   */
  public function deletePerm($file, $type='file'){
    if ( !empty($file) &&
      ($id_opt = $this->realToPerm($file, $type)) &&
      $this->options->remove($id_opt)
    ){
      return true;
    }
    return false;
  }

  /**
   * @param $path
   * @param $cfg
   * @param $all
   * @param bool $mvc
   * @return array|bool
   */
  private function getHistory($path, $cfg, $all, $mvc=false ){
    if ( !empty($path) &&
      !empty($cfg) &&
      \is_array($all)
    ){
      // Get all files in the path
      if ( $files = bbn\File\Dir::getFiles($path) ){
        // Get the creation date and time of each backups and insert them into result array
        foreach ( $files as $f ){
          $mode = false;
          $ext = bbn\Str::fileExt($f, 1);
          foreach ( $cfg['extensions'] as $e ){
            if ( $e['ext'] === $ext[1] ){
              $mode = $e['mode'];
            }
          }
          $moment = strtotime($ext[0]);
          $date = date('d/m/Y', $moment);
          $time = date('H:i:s', $moment);
          if ( !isset($all[$date]) ){
            $all[$date] = [];
          }
          // MVC
          if ( $mvc ){
            if ( !isset($all[$date][$cfg['url']]) ){
              $all[$date][$cfg['url']] = [];
            }
            if ( !empty($mode) ){
              array_push($all[$date][$cfg['url']], [
                'text' => $time,
                'code' => file_get_contents($f),
                'mode' => $mode,
                'tab' => $cfg['url']
              ]);
            }
          }
          // Normal Tab
          else{
            if ( !isset($all[$date]) ){
              $all[$date] = [];
            }
            if ( !empty($mode) ){
              array_push($all[$date], [
                'text' => $time,
                'code' => file_get_contents($f),
                'mode' => $mode,
                'tab' => 'code'
              ]);
            }
          }
        }
      }
      return $all;
    }
    return false;
  }

  /**
   * Returns all backup history of a file.
   *
   * @param string $url The file's URL
   * @return array|bool
   */
  public function history($url){
    if ( !empty($url) &&
      ( $dir = $this->dirFromUrl($url) ) &&
      ( $dir_cfg = $this->dir($dir) ) &&
      \defined('BBN_USER_PATH')
    ){
      $res = [];
      $all = [];
      // IDE backup path
      $path = BBN_USER_PATH."ide/backup/$dir";
      // Remove dir name from url
      $file = substr($url, \strlen($dir), \strlen($url));
      // MVC
      if ( !empty($dir_cfg['tabs']) ){
        foreach ( $dir_cfg['tabs'] as $t ){
          if ( empty($t['fixed']) ){
            // The file's backup path of the MVC's tab
            $p = $path . $t['path'] . $file . '/';
            // Get history
            $all = self::getHistory($p, $t, $all, true);
          }
        }
      }
      else {
        // The file's backup path of the MVC's tab
        $p = $path . $file . '/';
        // Get history
        $all = self::getHistory($p, $dir_cfg, $all);
      }
      if ( !empty($all) ){
        foreach ( $all as $i => $a ){
          if ( !empty($dir_cfg['tabs']) ){
            $tmp = [];
            foreach ( $a as $k => $b ){
              array_push($tmp, [
                'text' => $k,
                'items' => $b
              ]);
            }
          }
          array_push($res, [
            'text' => $i,
            'items' => !empty($tmp) ? $tmp : $a
          ]);
        }
      }
      return ['list' => $res];
    }
  }

  public function historyClear($url=''){
    if ( \defined('BBN_USER_PATH') ){
      $path = BBN_USER_PATH.'ide/backup/';
    }
    if ( !empty($url) &&
      ( $dir = $this->dirFromUrl($url) ) &&
      ( $dir_cfg = $this->dir($dir) )
    ){
      // Remove dir name from url
      $file = substr($url, \strlen($dir), \strlen($url));
      $path .= $dir . $file;
    }
    if ( is_dir($path) &&
      bbn\File\Dir::delete($path, !empty($url))
    ){
      return ['success' => 1];
    }
    $this->error('Error to delete the backup directory');
    return false;
  }

  /**
   * Returns
   * @return array
   */
  public function modes($type = false){
    if ( \defined('BBN_DATA_PATH') ){
      $r = [
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
      return $type ? ( isset($r[$type]) ? $r[$type] : false ) : $r;
    }
    return false;
  }
}
