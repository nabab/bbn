<?php
namespace bbn\Appui;

use Exception;
use bbn\X;
use bbn\Mvc;
use bbn\Db;
use bbn\Models\Cls\Db as modelDb;
use bbn\Models\Tts\Optional;
use bbn\Appui\Url;
use bbn\File\System;
use bbn\User\Preferences;

class Ide2 extends modelDb {

  use Optional;

  public $id;
  public $fs;
  private static $excluded = [
    'public' => ['_super.php']
  ];
  protected $projectInfo;
  protected $pathInfo;

  /**
   * constructor of the classe Project
   *
   * @param Db $db
   * @param string $id
   */
  public function __construct(Db $db, string $id)
  {
    parent::__construct($db);
    self::optionalInit();
    $this->id = $id;
    $this->fs = new System();
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
    $file = array_pop(X::split($cfg['file'], '/'));
    $res = [
      'root' => $cfg['info']['parent_code'],
      'path' => $cfg['path'],
      'files' => (!empty($cfg['typology']['tabs'])) ? $files : $cfg['file'],
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

  /**
   * Gets the configuration of an URL
   *
   * @param string $url The file's URL
   * @return array
   */
  public function urlToConfig(string $url, bool $force = false) : array
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
        throw new Exception('Malformed URL');
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
      $real .= $source_path;
      if (!empty($path_info['types'])) {
        /** @var string $path_type type found in the url (mvc, component, lib cli) */
        $path_type = array_shift($bits);
        /** @var array $path_row option corresponding to the type $path_type */
        $path_row = X::getRow($path_info['types'], ['type' => $path_type]);
        if (!$path_row) {
          throw new Exception(X::_('Impossible to find the type %s', $path_type));
        }
        $res['typology'] = $this->getType($path_type);
        $real .= $path_type.'/';
        if ($force && !$type) {
          if (!empty($res['typology']['tabs'])) {
            if ($row = X::getRow($res['typology']['tabs'], ['default' => true])) {
              $type = $row['url'];
            }
          }
        }
        $path_info = X::getRow($res['typology']['tabs'], ['url' => $type]);
        // add directly what remain in the url
        if (empty($res['typology']['directories'])) {
          $real .= X::join($bits, '/');
        }
        // add the directory to explore if 'directories' value is true (public, private, html, ...)
        else {
          $real .= $path_info['path'];
          if (!$this->fs->isDir($real)) {
            throw new Exception(X::_("The directory %s doesn't exist", $real));
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
      $res['file'] = $real;
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
   * Gets the file's URL from the real file's path.
   *
   * @param string $file The real file's path
   * @return bool|string
   */
  public function realToUrl(string $file) : array
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
      X::log([561, $file, $rep, $root], 'real');
      $res = $rep . '/';
      $bits = explode('/', substr($file, \strlen($root)));
      $filename  = array_pop($bits);
      $extension = \bbn\Str::fileExt($filename);
      $basename  = \bbn\Str::fileExt($filename, 1)[0];
      // MVC or Component
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
          X::log([$tab_path, $bits], 'real');
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
      return \bbn\Str::parsePath($res);
    }
    return false;
  }

  /**
   * function to get the full option tree of the project
   *
   * @return array
   */
  public function getFullTree(): array
  {
    $res = self::getOptionsObject()->fullTree($this->id);
    foreach($res['items'] as $t) {
      $res[$t['code']] = $t;
    }
    unset($res['items']);
    return $res;
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
      $roots = $tree['path']['items'];
      $res = [];
      foreach($roots as $root) {
        if (defined("BBN_".strtoupper($root['code'])."_PATH")) {
          $path = constant("BBN_".strtoupper($root['code'])."_PATH");
          foreach($root['items'] as $option) {
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
              'path' => $option['path'] === '/' ? '/' : $option['path']
            ];
            $res[] = $tmp;
          }
        }
      }
      $this->pathInfo = $res;
    }
    if (!$withPath) {
      foreach($roots as $root) {
        if (defined("BBN_".strtoupper($root['code'])."_PATH")) {
          foreach($root['items'] as &$option) {
            unset($option['parent']);
            unset($option['path']);
          }
        }
      }
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
   * function to get all path of the project and format each path
   *
   * @param bool $force  force update $this->projectInfo
   * @return array
   */
  public function getProjectInfo(bool $force = false): array
  {
    if ($force || !$this->projectInfo) {
      $info = $this->getFullTree();
      $info['path'] = $this->getPaths();
      $this->projectInfo = $info;
    }
    return $this->projectInfo;
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
    $currentPathArray = $this->getPath($id_path);
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
    $currentPathArray['type'] = $currentType;
    $currentPathArray['publicPath'] = $path.'/';
    // fill $todo with MVC files / folders
    if ($currentType['type'] === 'mvc') {
      $todo = $this->retrieveMvcFiles($finalPath, $path, $onlydirs);
    }
    // fill $todo with Components files / folders
    elseif ($currentType['type'] === 'components') {
      $todo = $this->retrieveComponentFiles($finalPath, $path, $onlydirs);
    }
    // fill $todo with all files / folders
    else {
      $todo = $this->retrieveAllFiles($finalPath.($path ?: ''), $onlydirs);
    }
    if (is_array($todo)) {
      //we browse the element
      $files = [];
      $filtered = array_values(array_filter(
        $todo,
        function($a) use (&$files) {
          // get name and extension of each files
          $ext  = \bbn\Str::fileExt($a['name']);
          $name = \bbn\Str::fileExt($a['name'], 1)[0];
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
      $fn = function($a) use (&$currentPathArray, $that, &$files,  &$folders) {
        $tmp = $that->_getNode($a, $currentPathArray);
        if ($tmp['file']) {
          $files[$tmp['name']] = $tmp;
        }
        else {
          $folders[$tmp['name']] = $tmp;
        }
        return $tmp;
      };
      array_map($fn, $filtered);
      if (ksort($folders, SORT_STRING | SORT_FLAG_CASE) && ksort($files, SORT_STRING | SORT_FLAG_CASE)) {
        //return merge of file and folder create in function get
        $tot = array_merge(array_values($folders), array_values($files));
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
  private function _getNode(array $t, array $cfg): array {
    $component = false;
    $is_vue    = false;
    $name      = $t['basename'];
    //if is type and is components
    if ($cfg['type']['type'] === 'components') {
      //if is the component
      /*if(empty($this->fs->getDirs($t['name'])) && !empty($cnt = $this->fs->getFiles($t))) {
        $component = true;
        $num       = 0;
        $folder    = false;
        if (is_array($cnt)) {
          foreach($cnt as $f){
            $item = explode(".", basename($f))[0];
            if ($item === basename($t)) {
              $arr[]  = \bbn\Str::fileExt($f);
              $is_vue = true;
            }
          }
        }
      }
      elseif (empty($model->inc->fs->getFiles($t, true))) {
        $component = false;
        $num       = 0;
        $folder    = true;
      }
      //else is folder
      elseif (($cnt = $model->inc->fs->getFiles($t, true, true))) {
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
            if ($item === basename($t)) {
              $folder    = false;
              $arr[]     = \bbn\Str::fileExt($f);
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
                if (($model->inc->fs->isDir($f) && (strpos(basename($f), '.') === 0))
                    || ($model->inc->fs->isFile($f) && (($item !== basename($t)) || (!empty($ext) && (in_array($ext, $excludeds) === true))))
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
      }*/
    }

    //on the basis of various checks, set the icon
    //case file but no component
    if (!empty($t['file']) && empty($component)) {
      if ($t['ext'] === 'js') {
        $icon = "icon-javascript";
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
      $icon = "nf nf-mdi-vuejs";
    }
    //case folder
    else {
      $icon = "nf nf-fa-folder";
    }

    //object return of a single node
    $res = [
      'text' => $name,
      'name' => $name,
      //'git' => $check_git($t),
      //Previously the 'uid' property was called 'path'
      /** @todo check that it is working for directories */
      // uid of the file depends to his type
      'uid' => $component === true ? $cfg['publicPath'].$name.'/'.$name : $cfg['publicPath'].$name,
      'has_index' => !$t['file'] && \bbn\File\Dir::hasFile($t['name'], 'index.php', 'index.html', 'index.htm'),
      'is_svg' => $t['file'] && ($t['ext'] === 'svg'),
      // $is_vue not use
      'is_vue' => $is_vue,
      'icon' => $icon,
      'bcolor' => $cfg['bcolor'],
      'folder' => $t['dir'],
      'lazy' => $t['dir'] && ((empty($onlydirs) && $t['num']) || (!empty($onlydirs) && $this->fs->getDirs($t['name']))),
      'numChildren' => $t['num'],
      'tab' => $t['tab'],
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
}
