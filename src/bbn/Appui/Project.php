<?php

/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 14/12/2017
 * Time: 17:34
 */

namespace bbn\Appui;

use Exception;
use bbn\X;
use bbn\Str;
use bbn\Mvc;
use bbn\Db;
use bbn\Models\Tts\Optional;
use bbn\Models\Tts\Cache;
use bbn\Models\Cls\Db as DbCls;
use bbn\Appui\Url;
use bbn\Appui\Option;
use bbn\File\System;
use bbn\File\Dir;
use bbn\User\Preferences;
use bbn\Api\Git;

use function yaml_parse;

class Project extends DbCls
{

  use Optional;
  use Cache;

  private $fullTree;

  protected $id;

  protected $id_langs;

  protected $id_path;

  protected $projectInfo;

  protected $pathInfo;

  protected $appPath;

  protected $name;

  protected $lang;

  protected $code;

  protected $option;

  protected $repositories;

  protected $fs;

  protected $options;

  protected $appui;

  protected static $environments = [];


  /**
   * Construct the class Project
   *
   * @param Db $db
   * @param string $id
   */
  public function __construct(Db $db, string $id = null)
  {
    parent::__construct($db);
    self::optionalInit();
    self::cacheInit();
    $this->options = Option::getInstance();
    $this->fs      = new System();
    if (Str::isUid($id)) {
      $this->id = $id;
    } elseif (\is_string($id)) {
      $this->id = $this->options->fromCode($id, 'list', 'project', 'appui');
    } elseif (defined('BBN_APP_NAME')) {
      $this->id = $this->options->fromCode(BBN_APP_NAME, 'list', 'project', 'appui');
    }

    if (!empty($this->id)) {
      $this->setProjectInfo($this->id);
    }
  }


  /**
   * Gets the potential existing paths from an URL
   *
   * @param string $url The file's URL
   * return array
   */
  public function urlToPaths(string $url) : array
  {
    $cfg = $this->urlToConfig($url, true);
    if (!$cfg) {
      throw new Exception(X::_('Impossible to find a configuration for the URL'));
    }

    $file = array_pop(X::split($cfg['file'], '/'));
    $res = [
      'root' => $cfg['info']['parent_code'],
      'path' => $cfg['path']
    ];

    if (!empty($cfg['typology']['tabs'])) {
      $files = [];
      foreach($cfg['typology']['tabs'] as $tab) {
        if ($cfg['typology']['directories'] === true) {
          $path = $cfg['typology']['code'].'/'.$tab['path'].$file;
        }
        else {
          $path = $cfg['typology']['code'].'/'.$file.'/'.$file;
        }
        $files[$tab['url']] = [
          'path' => $path,
          'extensions' => $tab['extensions']
        ];
      }
      $res['files'] = $files;
    }
    else {
      $res['files'] = $cfg['file'];
      $res['extensions'] = $cfg['extensions'];
    }

    return $res;
  }
  
  public function getGitDiff(string $path) {
    $arr = [
      'ide' => [],
      'elements' => []
    ];
    
    $git = new Git($path);
    $fs = new System();
    
    try {
      $difference_git = $git->diff();
    }
    catch (Exception $e) {
      $difference_git = false;
    }
  
    if ( !empty($difference_git) ){
      $arr['elements'] = array_map(function($a) use($path){
        return [
          'ele' => $path.'/'.(!empty($i = strpos($a['file'], ' -> ')) ? substr($a['file'], $i+4)  : $a['file']),
          'state' => $a['action']
        ];
      }, $difference_git);
    
      $branches = [
        'components' => ['.php','.js','.html', '.less', '.css'],
        'mvc' => [
          'public' => ['.php'],
          'private' => ['.php'],
          'model' => ['.php'],
          'js' => ['.js'],
          'html' => ['.php', '.html'],
          'css' => ['.css', '.less']
        ]
      ];
    
      foreach ( $arr['elements'] as $val ){
        $relative = str_replace($path.'/src/',"",$val['ele']);
        $part = explode('/', $relative);
        $root = array_shift($part);
        if ( $root === 'components' ){
          if ( $fs->isFile($val['ele']) ){
            $part = explode('.', $relative);
            $relative = array_shift($part);
          }
          foreach( $branches['components'] as $ext ){
            $info = [
              'ele' => $path.'/src/'.$relative.$ext,
              'state' => $val['state']
            ];
            if ( array_search($info, $arr['ide']) === false ){
              $arr['ide'][] = $info;
            }
          }
        }
        elseif( $root === 'mvc' ){
          $relative = explode("/", $relative);
          array_shift($relative);
          array_shift($relative);
          $relative_origin = implode('/', $relative);
          foreach ( $branches['mvc'] as $folder => $exts ){
            $element = $path.'/src/'. $root. '/'.$folder.'/';
            if ( $fs->isFile($val['ele']) ){
              $part = explode('.', $relative_origin);
              $relative = array_shift($part);
              foreach ( $exts as $ext ){
                $element = $path.'/src/'. $root. '/'.$folder.'/'.$relative;
                $element .= $ext;
                $info = [
                  'ele' => $element,
                  'state' => $val['state']
                ];
                if ( array_search($info, $arr['ide']) === false ){
                  $arr['ide'][] = $info;
                }
              }
            }
            else{
              $info = [
                'ele' => $element.$relative_origin,
                'state' => $val['state']
              ];
              if ( array_search($info, $arr['ide']) === false ){
                $arr['ide'][] = $info;
              }
            }
          }
        }
        else{
          if ( array_search($val, $arr['ide']) === false ){
            $arr['ide'][] = $val;
          }
        }
      }
    }
    return $arr;
  }

  /**
   * Gets the configuration of an URL
   *
   * @param string $url The file's URL
   * @return array|null
   */
  public function urlToConfig(string $url, bool $force = false) : ?array
  {
    // a typical url : lib/appui-api/js/test/_end_/code
    /** @var array $bits each substring of the url */
    $bits = X::split($url, '/');
    /** @var string $root the first element must correspond to a path retriever function of mvc (app, lib, data, cdn) */
    $root = array_shift($bits);
    /** @var string $path the code of the repository under $root (in the options) */
    $path = array_shift($bits);
    /** @var array $info all the options for this project */
    $info = $this->getProjectInfo();
    /** @var array $path_info full option for the current path */
    $path_info = X::getRow($info['path'], ['parent_code' => $root, 'code' => $path]);

    /** @var string $type the last part of the url after _end_ */
    $type = false;

    if (in_array('_end_', $bits)) {
      $type = array_pop($bits);
      // the url structure with _end_ and $type is mandatory
      if (array_pop($bits) !== '_end_') {
        throw new Exception("Malformed URL $url");
      }
    }
    $mvc = Mvc::getInstance();
    if ($path_info && method_exists($mvc, $root.'Path')) {
      if ($path_info['path'] === '/') {
        $path_info['path'] = '';
      }
      elseif (substr($path_info['path'], -1) !== '/') {
        $path_info['path'] .= '/';
      }
      $res = [
        'root' => $mvc->{$root.'Path'}(true),
        'path' => $path_info['path'],
        'info' => $path_info
      ];
      /** @var string $real the result of this function */
      $real = $res['root'].$res['path'];
      // case of folder is a component or a mvc
      $path_info = $path_info['alias'];
      $source_path = $path_info['sourcePath'] ?? '';
      $real .= $source_path . $path_info['code'] === 'bbn-project' ? 'src/' : '';
      if (!empty($path_info['types'])) {
        /** @var string $path_type type found in the url (mvc, component, lib cli) */
        $path_type = array_shift($bits);
        /** @var array $path_row option corresponding to the type $path_type */
        $path_row = X::getRow($path_info['types'], ['url' => $path_type]);
        if (!$path_row) {
          throw new Exception(X::_('Impossible to find the type %s', $path_type));
        }
        if ($path_type === 'lib') {
          $res['typology'] = $this->getType('cls');
        } else {
          $res['typology'] = $this->getType($path_type);
        }

        $real .= $path_type.'/';
        if ($force && !$type) {
          if (!empty($res['typology']['tabs'])) {
            if ($row = X::getRow($res['typology']['tabs'], ['default' => true])) {
              $type = $row['url'];
            }
          }
        }

        if (!empty($res['typology']['tabs'])) {
          $path_info = X::getRow($res['typology']['tabs'], ['url' => $type]);
        } else {
          $path_info = $res['typology'];
        }
        // add directly what remain in the url

        if (!empty($res['typology']['directories'])) {
          $real .= X::join($bits, '/');
        }
        // add the directory to explore if 'directories' value is true (public, private, html, ...)
        else {
          if ($path_type === 'mvc') {
            $real .= $path_info['path'];
            if (!$this->fs->isDir($real)) {
              throw new Exception(X::_("The directory %s doesn't exist", $real));
            }
          }
          elseif ($path_type === 'component') {
            $real .= array_shift($bits) . '/';
            if (!$this->fs->isDir($real)) {
              throw new Exception(X::_("The directory %s doesn't exist", $real));
            }
          }
          $real .= X::join($bits, '/');
        }
      }
      // case of a simple file
      else {
        $real .= '/'.X::join($bits, '/');
        if ($type !== 'code') {
          $real .= '/';
        }
      }
      $res['file'] = str_replace('//', '/', $real);;
      $res['extensions'] = $path_info['extensions'];
      return $res;
    }
    return null;
  }

  /**
   * Gets the real file's path from an URL
   *
   * @param string $url The file's URL
   * @param bool   $obj
   * @return string
   */
  public function urlToReal(string $url) : ?string
  {
    $res = $this->urlToConfig($url);
    if ($url) {
      foreach($res['extensions'] as $e) {
        $file = $res['file'].'.'.$e['ext'];
        if ($this->fs->exists($file)) {
          return $file;
        }
      }
    }
    return null;
  }

  /**
   * function to get the full option tree of the project
   *
   * @return array
   */
  public function getFullTree(bool $force = false): array
  {
    if (!$force && $this->fullTree) {
      return $this->fullTree;
    }

    if (!$force && $this->cacheHas($this->id, 'full_tree')) {
      $this->fullTree = $this->cacheGet($this->id, 'full_tree');
      return $this->fullTree;
    }

    $res = self::getOptionsObject()->fullTree($this->id);
    foreach($res['items'] as $t) {
      $res[$t['code']] = $t;
    }

    unset($res['items']);
    $this->fullTree = $res;
    $this->cacheSet($this->id, 'full_tree', $res, 3600);
    return $res;
  }

  public function getDbs(): array
  {
    $res = $this->getFullTree();
    if (isset($res['db']) && !empty($res['db']['items'])) {
      $o = self::getOptionsObject();
      foreach ($res['db']['items'] as $i => $db) {
        if (!empty($db['items'])) {
          $res['db']['items'][$i]['engine'] = $o->code($o->getIdParent($db['alias']['id_parent']));
          foreach ($db['items'] as $j => $conn) {
            $res['db']['items'][$i]['items'][$j]['engine'] = $o->code($o->getIdParent($conn['alias']['id_parent']));
          }
        }
      }

      return $res['db'];
    }

    return [];
  }


  public function check()
  {
    return parent::check() && !empty($this->id);
  }


  public function getEnvironment($appPath = null): ?array
  {
    if (!$appPath) {
      $appPath = $this->getAppPath();
    }

    if (!$appPath) {
      throw new Exception(X::_("No application path given for app %s", $this->name));
    }

    $file_environment = $appPath . 'cfg/environment';
    if ($this->fs->isFile($file_environment . '.json')) {
      $envs = \json_decode($this->fs->getContents($file_environment . '.json'), true);
    }
    elseif ($this->fs->isFile($file_environment . '.yml')) {
      try {
        $envs = yaml_parse($this->fs->getContents($file_environment . '.yml'));
      } catch (Exception $e) {
        throw new Exception(
          "Impossible to parse the file $file_environment.yaml"
            . PHP_EOL . $e->getMessage()
        );
      }
      if ($envs === false) {
        throw new Exception(X::_("Impossible to parse the file $file_environment.yaml"));
      }
    }

    if (!empty($envs)) {
      foreach ($envs as $env) {
        if ($env['app_path'] === X::dirname($appPath) . '/') {
          return $env;
        }
      }
    }

    return null;
  }


  /**
   * Change the value of the property i18n on the option of the project
   *
   * @param string $lang
   * @return bool
   */
  public function changeProjectLang(string $lang)
  {
    if ($cfg = $this->options->getCfg($this->id)) {
      $cfg['i18n'] = $lang;
      $this->lang  = $lang;
      $success     = $this->options->setCfg($this->id, $cfg);
      $this->options->deleteCache($this->id, true);
      return $success;
    }

    return false;
  }


  /**
   * Returns the main infos of the given project
   *
   * @param string $id
   * @return array
   *
   *
   */
  public function getProjectInfo(bool $force = false)
  {
    if ($this->id) {
      if (!$force && $this->projectInfo) {
        return $this->projectInfo;
      }

      if (!$force && $this->cacheHas($this->id, 'project_info')) {
        $this->projectInfo = $this->cacheGet($this->id, 'project_info');
        return $this->projectInfo;
      }

      $info = [
        'id' => $this->id,
        'code' => $this->getCode(),
        'name' => $this->getName(),
        'path' => $this->getPaths(true),
        'langs' => $this->getLangsIds(),
        'lang' => $this->getLang(),
        'db' => $this->getDbs(),
      ];

      $this->cacheSet($this->id, 'project_info', $info, 3600);
      $this->projectInfo = $info;
      return $info;
    }

    return [];
  }
  

  /**
   * function to get difference between local and git version
   *
   * @return array
   */
  private function _getDifferenceGit()
  {
    return [];
  }

  /**
   * function to get git status of the element
   *
   * @param bool $ele  given element to check its status
   * @return bool
   */
  private function _checkGit($ele): bool
  {
    $difference_git = $this->getDifferenceGit();
    $info_git = false;
    if (!empty($difference_git['ide'])) {
      foreach($difference_git['ide'] as $commit){
        $info_git = strpos($commit['ele'], $ele) === 0;
        if (!empty($info_git)) {
          return $info_git;
        }
      }
    }
    return $info_git;
  }

  /**
   * function to get array to fill the tree component
   *
   * @param string $path  given path of the file selected
   * @param string $id_path  given id_path of the directory
   * @param string $type  type given in order to fill the tree
   * @return array
   */
  public function openTree(string $path, string $id_path, string $type = null): array
  {
    return $this->_getTree($path, $id_path, $type);
  }
  
  /**
   * function to get the tree array to fill tree component
   *
   * @param string $path  given path of the file selected
   * @param string $id_path  given id_path of the directory
   * @param string $type  type given in order to fill the tree
   * @param bool $onlydirs  get files AND folders if it is true
   * @param string $color  color given in order to set icon color
   * @param string $tab  extension of the file
   * @param array $types  types given in order to fill tree array
   * @return array
   */
  private function _getTree(string $path, string $id_path, string $type = null, bool $onlydirs = false): array
  {
    // get info of the current path selected in first dropdown
    $currentPathArray = $this->getPath($id_path, true);
    if (!$currentPathArray || !$currentPathArray['path'] || !$currentPathArray['id_alias']) {
      throw new Exception('Invalid Path');
    }

    $path = Url::sanitize($path);

    $o = self::getOptionsObject();
    // get current path type options
    $typePath = $o->option($currentPathArray['id_alias']);
    // finalPath is the parameter for the getFiles function
    $finalPath = $currentPathArray['parent'].$currentPathArray['path'].($typePath['code'] === 'bbn-project' ? '/src' : '');
    $isBbnProject = false;
    $difference_git = [];//$this->getGitDiff($currentPathArray['parent'].$currentPathArray['path']);
  
    $todo = [];
    if (!empty($typePath['types'])) {
      // do if the path is a bbn-project
      $isBbnProject = true;
      // check the type between mvc, component, classes and cli
      $currentType = X::getRow($typePath['types'], ['type' => $type]);
      if (!$currentType) {
        throw new Exception('Invalid Type');
      }
      // concatenate finalPath with the path of the type
      $finalPath .= '/'.$currentType['path'];

    }
    else {
      $currentType = $typePath;
      $finalPath .= '/';
    }
    $currentPathArray['id_path'] = $id_path;
    $currentPathArray['type'] = $currentType;
    $currentPathArray['publicPath'] = $path.'/';

    // fill $todo with MVC files / folders
    if ($currentType['type'] === 'mvc') {
      $todo = $this->retrieveMvcFiles($finalPath, $path, $onlydirs);
    }
    // fill $todo with Components files / folders
    elseif ($currentType['type'] === 'components') {
      // check if path is not empty
      $todo = $this->retrieveComponentFiles($finalPath, $path, $onlydirs);
    }
    // fill $todo with all files / folders
    else {
      $todo = $this->retrieveAllFiles($finalPath.($path ?: ''), $onlydirs);
    }
  
    $check_git = function ($ele) use ($difference_git) {
      $info_git = false;
      if (!empty($difference_git['ide'])) {
        foreach($difference_git['ide'] as $commit){
          $info_git = str_starts_with($commit['ele'], $ele);
          if (!empty($info_git)) {
            return true;
          }
        }
      }
    
      return $info_git;
    };
    
    if (is_array($todo)) {
      //we browse the element
      $fs = new System();
      $files = [];
      $filtered = array_values(array_filter(
        $todo,
        function($a) use (&$files, &$fs, $check_git){
          // get name and extension of each files
          $ext  = Str::fileExt($a['name']);
          $name = Str::fileExt($a['name'], 1)[0];
          if ($fs->isDir($a['name'])) {
            $name = '0' . $name;
          } else {
            $name = '1' . $name;
          }
          if (!isset($files[$name])) {
            $files[$name] = true;
            return true;
          }
          return false;
        }
      ));
      $that =& $this;
      $files = [];
      $folders = [];
      // launch _getNode on all path of $currentPathArray to get array of nodes
      $fn = function($a) use (&$currentPathArray, $that, &$files,  &$folders, &$fs, $check_git) {
        
        $tmp = $that->_getNode($a, $currentPathArray);
        if ($fs->isFile($a['name'])) {
          $tmp['git'] = $check_git($a['name']);
          $files['1' . $tmp['name']] = $tmp;
        }
        else {
          $tmp['git'] = $check_git($a['name']);
          $folders['0' . $tmp['name']] = $tmp;
        }
        return $tmp;
      };
      array_map($fn, $filtered);
      if (ksort($folders, SORT_STRING | SORT_FLAG_CASE) && ksort($files, SORT_STRING | SORT_FLAG_CASE)) {
        //return merge of file and folder create in function get
        //X::ddump($folders, $files);
        $tot = [...array_values($folders), ...array_values($files)];
        return $tot;
      }
    }
  }

  /**
   * function to get node by info of the selected file/folder
   *
   * @param array $t  info of the file/folder selected
   * @param array $cfg  config of the file/folder to create result for tree
   * @return array
   */
  private function _getNode(array $t, array $cfg): ?array {

    if (!isset($t) || empty($t['name'])) {
      return null;
    }

    $component = false;
    $is_vue    = false;
    $name      = $t['basename'];
    //if is type and is components
    if ($cfg['type']['type'] === 'components') {
      //if is the component, with no subfolders
      if(empty($this->fs->getDirs($t['name'])) && !empty($cnt = $this->fs->getFiles($t['name']))) {
        $component = true;
        $num       = 0;
        $folder    = false;
        if (is_array($cnt)) {
          foreach($cnt as $f){
            $item = explode(".", basename($f))[0];
            if ($item === basename($t['name'])) {
              $arr[]  = Str::fileExt($f);
              $is_vue = true;
            }
          }
        }
      }
      elseif (empty($this->fs->getFiles($t['name'], true))) {
        $num       = 0;
        $folder    = true;
      }
      //else is folder
      elseif (($cnt = $this->fs->getFiles($t['name'], true, true))) {
        $excludeds = [
          'public' => ['_super.php']
        ];
        $num       = \count($cnt);
        $folder    = true;
        $arr       = [];
        $component = false;
        $num_check = 0;

        if (is_array($cnt)) {
          $num_check = 0;
          foreach($cnt as $f){
            //$name = explode(".", basename($f))[0];
            $ele  = explode(".", basename($f));
            $item = $ele[0];
            $ext  = isset($ele[1]) ? $ele[1] : false;
            //if is folder and component
            if ($item === basename($t['name'])) {
              $folder    = false;
              $arr[]     = Str::fileExt($f);
              $is_vue    = true;
              $component = true;
              if (!empty($ext) && (in_array($ext, $excludeds) === false)) {
                $num_check++;
              }
            }
          }

          if($num > 0) {
            //for component in case file with name different or folder hidden
            $element_exluded = 0;
            if($num_check < $num) {
              foreach($cnt as $f){
                $ele  = explode(".", basename($f));
                $item = $ele[0];
                $ext  = isset($ele[1]) ? $ele[1] : false;
                if (($this->fs->isDir($f) && (strpos(basename($f), '.') === 0))
                    || ($this->fs->isFile($f) && (($item !== basename($t['name'])) || (!empty($ext) && (in_array($ext, $excludeds) === true))))
                   ) {
                  $element_exluded++;
                }
              }
            }

            //check if the files of the component + those that have a different name or have hidden folders is the same as all the content, leaving only the possibility in case of folders not hidden
            $num = $num - ($num_check + $element_exluded);
          }
        }

        //in this block check check if there is the file with the extension 'js' otherwise take the first from the list and if it is php then let's say that we are in the html
        if (count($arr) > 0) {
          if(array_search('js',$arr, true) !== false) {
            $tab = 'js';
          }
          else{
            $tab = $arr[0] === 'php' ? 'html' : $arr[0];
          }
        }
      }
    }

    //on the basis of various checks, set the icon
    //case file but no component
    if (!empty($t['file']) && empty($component)) {
      //x::ddump($t, $cfg, $tab);
      //if (isset($cfg['alias']['types']) && ($row = X::getRow($cfg['alias']['types'], ))
      if (!empty($t['tab'])) {
        switch ($t['tab']) {
          case 'php':
            $icon = "nf nf-md-language_php";
            break;
          case 'private':
            $icon = "nf nf-md-language_php";
            break;
          case 'model':
            $icon = "nf nf-md-database";
            break;
          case 'html':
            $icon = "nf nf-md-language_html5";
            break;
          case 'js':
            $icon = "nf nf-md-language_javascript";
            break;
          case 'css':
            $icon = "nf nf-md-language_css3";
            break;
        }
      }
      elseif ($t['ext'] === 'js') {
        $icon = "nf nf-md-language_javascript";
      }
      elseif ($t['ext'] === 'less') {
        $icon = 'nf nf-dev-less';
      }
      else{
        $icon = "icon-$t[ext]";
      }
    }
    //case component o folder who contain other component
    elseif (!empty($component) && !empty($is_vue)) {
      $icon = "nf nf-md-vuejs";
    }
    //case folder
    else {
      $icon = "nf nf-fa-folder";
    }

    $public_path = $cfg['publicPath'];

    //object return of a single node
    $uid = $component === true ? $public_path.$name.'/'.$name : $public_path.$name . ($t['dir'] ? '/' : '');

    $res = [
      'text' => $name,
      'name' => $name,
      //'git' => $check_git($t),
      //Previously the 'uid' property was called 'path'
      /** @todo check that it is working for directories */
      // uid of the file depends to his type
      'uid' => $uid,
      'has_index' => !$t['file'] && Dir::hasFile($t['name'], 'index.php', 'index.html', 'index.htm'),
      'is_svg' => $t['file'] && ($t['ext'] === 'svg'),
      // $is_vue not use
      'is_vue' => $is_vue,
      'icon' => $icon,
      'bcolor' => $cfg['bcolor'],
      'folder' => $t['dir'],
      'lazy' => $t['dir'] && ((empty($onlydirs) && $t['num']) || (!empty($onlydirs) && $this->fs->getDirs($t['name']))),
      'numChildren' => $num ?? ($t['num'] ?? 0),
      'type' => $cfg['type']['type'],
      'id_path' => $cfg['id_path'],
      'tab' => $tab ?? ($t['tab'] ?? null),
      'ext' => $t['file'] ? $t['ext'] : false
    ];


    /*if(!empty($tree_popup)) {
      $cfg['tree_popup'] = !empty($tree_popup);
    }*/

    //based on various checks, we set the type by adding it to the cfg
    /*if ($cfg['type'] && !empty($types)) {
      $res['type'] = !empty($types[$name]) ? $types[$name] : false;
    }
    elseif (!empty($type) && empty($types)) {
      $cfg['type']['type'] = $type;
    }
    elseif (empty($type) && empty($types)) {
      $cfg['type']['type'] = false;
    }*/

    //add to the list of folders or files so that we traced them for the next cycle
    return $res;
  }


/**
   * function to get a type by a code
   *
   * @param string $code  code given to retrieve its type
   * @return array
   */
  public function getType(string $code): array
  {
    $o = self::getOptionsObject();
    return $o->option($code, 'types', 'ide', 'appui');
  }

  /**
   * function to get a icon of a type
   *
   * @param string $code  code given to retrieve its icon
   * @return string
   */
  public function getIcon(string $code): string
  {
    $type = $this->getType($code);
    if (!empty($type['icon'])) {
      return $type['icon'];
    }
    return '';
  }

  /**
   * function to get all files by a path
   *
   * @param string $path  path given to search files
   * @param bool $onlydirs  get files AND folders if it is true
   * @return array
   */
  private function retrieveAllFiles(string $path, bool $onlydirs = false): array
  {
    if (!$this->fs->isDir($path)) {
      throw new Exception(X::_('Invalid Path %s', $path));
    }
    return !empty($onlydirs) ? $this->getDirs($path, false, 'tmce') : $this->fs->getFiles($path, true, false, false, 'tmce');
  }

  /**
   * function to get files refer to a component by a path
   *
   * @param string $path  path given to search files
   * @param bool $onlydirs  get files AND folders if it is true
   * @return array
   */
  private function retrieveComponentFiles(string $root, string $path, bool $onlydirs = false): array
  {
    return $this->fs->getDirs($root.$path, false, 'tmce');
  }

  /**
   * function to get files refer to a mvc by a path
   *
   * @param string $root  adds
   * @param string $path  path given to search files
   * @param bool $onlydirs  get files AND folders if it is true
   * @return array
   */
  private function retrieveMvcFiles(string $root, string $path, bool $onlydirs = false): array
  {
    $currentTabs = $this->getType('mvc');
    $todo = [];
    if (!$this->fs->isDir($root)) {
      throw new Exception(X::_('Invalid Root'));
    }
    if (!empty($currentTabs['tabs'])) {
      foreach($currentTabs['tabs'] as $tab) {
        if (empty($tab['fixed'])) {
          $tmp = $root.$tab['path'].($path ? $path : '');
          array_push(
            $todo,
            ...array_map(function($a) use ($tab) {
              $a['tab'] = $tab['url'];
              return $a;
            }, !empty($onlydirs) ? $this->getDirs($tmp, false, 'tmce') : $this->fs->getFiles($tmp, true, false, false, 'tmce'))
          );
        }
      }
    }
    return $todo;
  }

  public function getIdLang()
  {
    return $this->id_langs;
  }


  public function getLang()
  {
    return $this->lang;
  }


  public function getId()
  {
    return $this->id;
  }


  public function getCode()
  {
    return $this->code;
  }


  public function getName()
  {
    return $this->name;
  }


  /**
   * function to get all path of the project and format each path
   *
   * @param bool $withPath  adds the full path to the results
   * @param bool $force  force update $this->pathInfo
   * @return array
   */
  public function getPaths(bool $withPath = false, bool $force = false): array
  {
    if ($force || !$this->pathInfo) {
      $tree = $this->getFullTree();
      $roots = $tree['path']['items'] ?: [];
      $res = [];
      foreach($roots as $root) {
        if (!empty($root['items']) && defined("BBN_".strtoupper($root['code'])."_PATH")) {
          $path = constant("BBN_".strtoupper($root['code'])."_PATH");
          foreach($root['items'] as $option) {
            if (!isset($option['path'])) {
              //continue;
              X::log(["Project no path", $option]);
              throw new Exception(X::_("No path in option for project for %s", $option['code']));
            }

            $tmp = [
              'id' => $option['id'],
              'id_alias' => $option['id_alias'],
              'parent_code' => $root['code'],
              'text' => $option['text'],
              'code' => $option['code'],
              'bcolor' => $option['bcolor'] ?? null,
              'fcolor' => $option['fcolor'] ?? null,
              'language' => $option['language'] ?? BBN_LANG,
              'alias' => $option['alias'],
              'parent' => $path,
              'path' => $option['path'] === '/' ? '/' : $option['path'],
              'id_option' => $option['id']
            ];

            $res[] = $tmp;
          }
        }
      }

      $this->pathInfo = $res;
    }

    if (!$withPath) {
      $res = $this->pathInfo;
      foreach($res as &$option) {
        unset($option['parent']);
        unset($option['path']);
      }

      unset($option);
      return $res;
    }

    return $this->pathInfo;
  }

  /**
   * function to get a path by id
   *
   * @param string $id  id of the path to get
   * @param bool $withPath  adds the full path to the results
   * @param bool $force  force update $this->pathInfo
   * @return array
   */
  public function getPath(string $id, bool $withPath = false, bool $force = false): ?array
  {
    $paths = $this->getPaths($withPath, $force);
    $row = X::getRow($paths, ['id' => $id]);
    return $row ?: null;
  }


  /**
   * Returns an array including the ids of the languages for which the project is configured or creates the options for the configured languages
   * ( the arraay contains the id_alias of the options corresponding to the real option of the language)
   * @return array
   */
  public function getLangsIds()
  {
    $ids = [];
    $res = [];
    $this->options->deleteCache($this->id, true);
    if ($this->check() && isset($this->id_langs)) {
      if ($ids = array_keys($this->options->options($this->id_langs))) {
        foreach ($ids as $i) {
          if (!empty($this->options->alias($i))) {
            $res[] = $this->options->alias($i);
          }
        }

        return $res;
      }
    }

    return $res;
  }


  /**
   * Returns languages that have primary in cfg
   *
   * @return void
   */
  public function getPrimariesLangs()
  {
    $uid_languages = $this->options->fromCode('languages', 'i18n', 'appui');
    $languages     = $this->options->fullTree($uid_languages);
    $primaries     = array_values(
      array_filter(
        $languages['items'],
        function ($v) {
          return !empty($v['primary']);
        }
      )
    );
    return $primaries ?: [];
  }


  /**
   * Creates the children of the option lang, if no arguments is given it uses the array of primaries languages
   *
   * @param array $langs
   * @return void
   */
  public function createsLangOptions(array $langs = [])
  {
    if (empty($langs)) {
      $primaries = $this->getPrimariesLangs();
      $langs     = $primaries;
    }
    if (!empty($langs)) {
      $res       = [];
      foreach ($langs as $l) {
        if ($id_opt = $this->options->add(
          [
            'text' => $l['text'],
            'code' => $l['code'],
            'id_parent' => $this->id_langs,
            'id_alias' => $l['id'],
          ]
        )) {
          $l['id'] = $id_opt;
          $res[] = $l;
        }
      }

      return $res;
    }
  }


  /**
   * Gets the real root path from a repository's id as recorded in the options.
   *
   * @param string|array $repository The repository's name (code) or the repository's configuration
   * @return string
   */
  public function getRootPath($rep): string
  {
    //if only name else get info repository
    $path       = '';
    $repository = \is_string($rep) ? $this->repositories[$rep] : $rep;
    if ((!empty($repository)
        && is_array($repository))
      && !empty($repository['root'])
      && X::hasProps($repository, ['path', 'root', 'code'], true)
    ) {
      if (strpos($repository['root'], '/') === 0) {
        $path = $repository['root'];
      } else {
        switch ($repository['root']) {
          case 'app':
            $path = $this->getAppPath();
            break;
          case 'lib':
            $path  = $this->getLibPath();
            $path .= $repository['path'];
            if ($repository['alias_code'] === 'bbn-project') {
              $path .= '/src/';
            }
            break;
          case 'home':
            if (!defined('BBN_HOME_PATH')) {
              throw new Exception(X::_("BBN_HOME_PATH is not defined"));
            }

            $path  = constant('BBN_HOME_PATH');
            $path .= $repository['path'];
            if ($repository['alias_code'] === 'bbn-project') {
              $path .= '/src/';
            }
            break;
            case 'cdn':
            $path = $this->getCdnPath();
            $path .= $repository['path'];
            break;
          case 'data':
            $path = $this->getDataPath();
            $path .= $repository['path'];
            break;
        }
      }
    }

    if (!is_string($path)) {
      throw new Exception(X::_("Impossible to determine the path for %s (root: %s -> %s)", $rep, $repository['root'] ?? X::_('Unknown'), $this->getAppPath()));
    }

    if ($path && substr($path, -1) !== '/') {
      $path .= '/';
    }

    return $path;
  }


  /**
   * Gets the app path
   *
   * @return string
   */
  public function getAppPath(): ?string
  {
    if (!$this->appPath) {
      // Current project
      if ($this->name === constant('BBN_APP_NAME')) {
        $this->appPath = Mvc::getAppPath();
      } else {
        $envs = $this->options->fullOptions('env', $this->id);
        if (empty($envs)) {
          throw new Exception(X::_("Impossible to find environments for option %s", $this->id));
        }

        if ($env = X::getRow($envs, ['type' => constant('BBN_ENV')])) {
          $this->appPath = $env['text'];
          if (substr($this->appPath, -4) !== 'src/') {
            $this->appPath .= 'src/';
          }
        }
      }
    }

    return $this->appPath;
  }


  /**
   * Gets the CDN path
   *
   * @return string
   */
  public function getCdnPath(): string
  {
    if ($this->name === BBN_APP_NAME) {
      if (defined('BBN_CDN_PATH')) {
        return constant('BBN_CDN_PATH');
      }
    } elseif ($content = $this->getEnvironment()) {
      return $content['app_path'] . 'src/';
    }

    throw new \Exception(X::_("Impossible to find the CDN path for %s", $this->name));
  }


  /**
   * Gets the lib path
   *
   * @return string
   */
  public function getLibPath(): string
  {
    if ($this->name === BBN_APP_NAME) {
      return Mvc::getLibPath();
    } elseif ($content = $this->getEnvironment()) {
      return $content['lib_path'] . 'bbn\/';
    }

    throw new Exception(X::_("Impossible to find the libraries path for %s", $this->name));
  }


  /**
   * Gets the data path
   *
   * @return string
   */
  public function getDataPath(string $plugin = null): string
  {
    if ($this->name === BBN_APP_NAME) {
      return Mvc::getDataPath($plugin);
    } elseif ($content = $this->getEnvironment()) {
      $path = $content['data_path'];
      if ($plugin) {
        $path .= 'plugins/' . $plugin . '/';
      }
      return $path;
    }

    throw new \Exception(X::_("Impossible to find the data path for %s", $this->name));
  }


  /**
   * Gets the data path
   *
   * @return string
   */
  public function getUserDataPath(string $plugin = null): string
  {
    if ($this->name === BBN_APP_NAME) {
      return Mvc::getUserDataPath($plugin);
    } elseif ($content = $this->getEnvironment()) {
      $path = $content['data_path'];
      if ($plugin) {
        $path .= 'plugins/' . $plugin . '/';
      }
      return $path;
    }

    throw new \Exception(X::_("Impossible to find the user path for %s", $this->name));
  }


  /**
   * Makes the repositories' configurations.
   *
   * @param string $code The repository's name (code)
   * @return array
   */
  public function getRepositories(string $project_name = ''): array
  {
    $cats         = [];
    $repositories = [];
    if (strlen($project_name) === 0) {
      $project_name = $this->name;
    }

    $roots = $this->options->fullTree($this->options->fromCode('path', $project_name, 'list', 'project', 'appui'));
    if (!empty($roots) && !empty($roots['items'])) {
      $roots = $roots['items'];
      foreach ($roots as $root) {
        $paths = $this->options->fullTree($root['id']);
        if (isset($paths['items']) && count($paths['items'])) {
          foreach ($paths['items'] as $repository) {
            if (empty($repository['id_alias'])) {
              $this->log(['No id alias for repo', $repository, $project_name]);
              continue;
            }

            $name = $paths['code'] . '/' . $repository['code'];
            if (!isset($cats[$repository['id_alias']])) {
              if (isset($repository['alias'])) {
                unset($repository['alias']['cfg']);
                $cats[$repository['id_alias']] = $repository['alias'];
              }
            }

            unset($repository['cfg']);
            unset($repository['alias']);
            $repositories[$name]               = $repository;
            $repositories[$name]['title']      = $repository['text'];
            $repositories[$name]['root']       = $paths['code'];
            $repositories[$name]['name']       = $name;
            $repositories[$name]['alias_code'] = $cats[$repository['id_alias']]['code'];
            if (!empty($cats[$repository['id_alias']]['tabs'])) {
              $repositories[$name]['tabs'] = $cats[$repository['id_alias']]['tabs'];
            } elseif (!empty($cats[$repository['id_alias']]['extensions'])) {
              $repositories[$name]['extensions'] = $cats[$repository['id_alias']]['extensions'];
            } elseif (!empty($cats[$repository['id_alias']]['types'])) {
              $repositories[$name]['types'] = $cats[$repository['id_alias']]['types'];
            }

            unset($repositories[$name]['alias']);
          }
        }
      }
    }

    return $repositories;
  }


  /**
   * Returns the repository object basing on the given id
   *
   * @param string $id
   * @return void
   */
  public function repositoryById(string $id)
  {
    $idx = X::find($this->repositories, ['id' => $id]) ?: null;
    if ($idx !== null) {
      return $this->repositories[$idx];
    }

    return [];
  }


  /**
   * Gets a repository's configuration.
   *
   * @param string $code The repository's name (code)
   * @return array|bool
   */
  public function repository($name)
  {
    if (
      !empty($this->repositories)
      && is_array($this->repositories)
      && !empty(array_key_exists($name, $this->repositories))
    ) {
      return $this->repositories[$name];
    }

    return false;
  }


  /**
   * Returns the file's URL from the real file's path.
   *
   * @param string $file The real file's path
   * @return bool|string
   */
  public function realToUrl(string $file)
  {
    foreach ($this->repositories as $i => $d) {
      $root = isset($d['root_path']) ? $d['root_path'] : $this->getRootPath($d['name']);
      if (
        $root
        && (strpos($file, $root) === 0)
      ) {
        $rep = $i;
        break;
      }
    }


    if (isset($rep)) {
      $res = $rep . '/';
      $bits = explode('/', substr($file, \strlen($root)));
      $filename  = array_pop($bits);
      $extension = Str::fileExt($filename);
      $basename  = Str::fileExt($filename, 1)[0];
      // MVC
      if (!empty($d['tabs'])) {
        // URL is interverted
        if ($d['type'] === 'components') {
          foreach ($d['tabs'] as $tab) {
            foreach ($tab['extensions'] as $ext) {
              if ($extension === $ext['ext']) {
                $tab_path = $tab['url'];
                break;
              }
            }

            if (isset($tab_path)) {
              break;
            }
          }
        }
        else {
          $tab_path = array_shift($bits);
        }

        $res     .= implode('/', $bits);
        foreach ($d['tabs'] as $t) {
          if (
            empty($t['fixed'])
            && ($t['path'] === $tab_path . '/')
          ) {
            $res .= "/$filename";
            break;
          }
        }
      }
      // Normal file
      else {
        $res .= implode('/', $bits) . '/' . $basename . '.' . $extension;
      }

      return Str::parsePath($res);
    }

    return false;
  }



  /**
   * Returns the repository's name or object from an URL.
   *
   * @param string $url
   * @param bool   $obj
   * @return bool|int|string
   */
  public function repositoryFromUrl(string $url, bool $obj = false)
  {
    //search repository
    if (
      is_array($this->repositories)
      && count($this->repositories)
    ) { //if in url name of repository break loop
      foreach ($this->repositories as $i => $d) {
        if ((strpos($url, $i) === 0)) {
          $repository = $i;
          break;
        }
      }

      //if there is name repository or total info
      if (!empty($repository)) {
        return empty($obj) ? $repository : $this->repositories[$repository];
      }
    }

    return false;
    //return $this->projects->repositoryFromUrl($url, $obj);
  }


  /**
   * Defines the variables
   *
   * @param  $id
   * @param string $name
   * @param string $lang
   * @return void
   */
  private function setProjectInfo(string $id = null)
  {
    if ($o = $this->options->option($id ?: $this->id)) {
      $cfg                = $this->options->getCfg($id ?: $this->id);
      $this->name         = $o['text'];
      $this->lang         = $cfg['i18n'] ?? '';
      $this->code         = $o['code'];
      $this->option       = $o;
      $this->repositories = $this->getRepositories($o['code']);
      //$this->repositories = $this->getRepositories($o['text']);

      //the id of the child option 'lang' (children of this option are all languages for which the project is configured)
      if (!$this->id_langs = $this->options->fromCode('lang', $id)) {
        $this->setIdLangs();
      }

      //the id of the child option 'path'
      $this->id_path = $this->options->fromCode('path', $id) ?: null;
      return true;
    }

    return false;
  }


  /**
   * If the child option lang is not yet created it creates the option
   *
   * @return void
   */
  private function setIdLangs()
  {
    if (empty($this->id_langs)) {
      $this->id_langs = $this->options->add(
        [
          'text' => 'Languages',
          'code' => 'lang',
          'id_parent' => $this->id,
        ]
      );
    }
  }
}
