<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 14/12/2017
 * Time: 17:34
 */

namespace bbn\Appui;

use bbn;
use bbn\X;

class Project extends bbn\Models\Cls\Db
{

  protected $id;

  protected $id_langs;

  protected $id_path;

  protected $name;

  protected $lang;

  protected $code;

  protected $option;

  protected $repositories;

  protected $fs;

  protected $appui;

  protected static $environments = [];


  /**
   * Construct the class Project
   *
   * @param bbn\Db $db
   * @param string $id
   */
  public function __construct(bbn\Db $db, string $id = null)
  {
    parent::__construct($db);
    $this->options = bbn\Appui\Option::getInstance();
    $this->fs      = new \bbn\File\System();
    if (\bbn\Str::isUid($id)) {
      $this->id = $id;
    }
    elseif (\is_string($id)) {
      $this->id = $this->options->fromCode($id, 'list', 'project', 'appui');
    }
    elseif (defined('BBN_APP_NAME')) {
      $this->id = $this->options->fromCode(BBN_APP_NAME, 'list', 'project', 'appui');
    }

    if (!empty($this->id)) {
      $this->setProjectInfo($this->id);
    }
  }


  public function check()
  {
    return parent::check() && !empty($this->id);
  }


  public function getEnvironment($app_name = null): ?array
  {
    $file_environment = \bbn\Mvc::getAppPath().'cfg/environment';
    if ($this->fs->isFile($file_environment.'.json')) {
      $envs = \json_decode($this->fs->getContents($file_environment.'.json'), true);
    }
    elseif ($this->fs->isFile($file_environment.'.yml')) {
      try {
        $envs = \yaml_parse($this->fs->getContents($file_environment.'.yml'), true);
      }
      catch (\Exception $e) {
        throw new \Exception(
          "Impossible to parse the file $file_environment"
          .PHP_EOL.$e->getMessage()
        );
      }
    }
    return $envs ? $envs[0] : null;
  }


  /**
   * Change the value of the property i18n on the option of the project
   *
   * @param string $lang
   * @return void
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
   * @return void
   */
  public function getProjectInfo()
  {
    if ($this->id) {
      return [
        'path' => $this->getPaths(),
        'langs' => $this->getLangsIds(),
        'id' => $this->id,
        'lang' => $this->getLang(),
        'name' => $this->getName()
      ];
    }

    return [];
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


  public function getName()
  {
    return $this->name;
  }


  /**
   * Returns an array containing about the repositories in  the project
   *
   * @return void
   */
  public function getPaths()
  {
    $paths = [];
    if ($this->check() && $this->id_path && !empty($this->repositories)) {
      foreach($this->repositories as $rep){
        $paths[] = [
          'id_option' => $rep['id'],
          'path' => $this->getRootPath($rep['name']),
          'code' => $rep['code'],
          'language' => $rep['language'] ?? '',
        ];
      }
    }

    return $paths;
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
        foreach($ids as $i){
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
        $languages['items'], function ($v) {
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
      $id_langs  = $this->id_langs;
      $res       = [];
      foreach($langs as $l){
        if ($id_opt = $this->options->add(
          [
            'text' => $l['text'],
            'code' => $l['code'],
            'id_parent' => $this->id_langs,
            'id_alias' => $l['id'],
          ]
        )
        ) {
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
      }
      else {
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
          case 'cdn':
            $path = $this->getCdnPath();
            break;
          case 'data':
            $path = $this->getDataPath();
            $path .= $repository['path'];
            break;
        }
      }
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
  public function getAppPath(): string
  {
    // Current project
    if ($this->name === BBN_APP_NAME) {
      return \bbn\Mvc::getAppPath();
    }
    else {
      // Other project
      if (($envs = $this->getEnvironment()) && !empty($envs['app_path'])) {
        return $envs['app_path'].'src/';
      }
      throw new \Exception(X::_("Impossible to find the application path for %s", $this->name));
    }
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
        return BBN_CDN_PATH;
      }
    }
    elseif ($content = $this->getEnvironment()) {
      return $content['app_path'].'src/';
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
      return \bbn\Mvc::getLibPath();
    }
    elseif ($content = $this->getEnvironment()) {
      return $content['lib_path'].'bbn\/';
    }

    throw new \Exception(X::_("Impossible to find the libraries path for %s", $this->name));
  }


  /**
   * Gets the data path
   *
   * @return string
   */
  public function getDataPath(string $plugin = null): string
  {
    if ($this->name === BBN_APP_NAME) {
      return \bbn\Mvc::getDataPath($plugin);
    }
    elseif ($content = $this->getEnvironment()) {
      $path = $content['data_path'];
      if ($plugin) {
        $path .= 'plugins/'.$plugin.'/';
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
      return \bbn\Mvc::getUserDataPath($plugin);
    }
    elseif ($content = $this->getEnvironment()) {
      $path = $content['data_path'];
      if ($plugin) {
        $path .= 'plugins/'.$plugin.'/';
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
  public function getRepositories(string $project_name =''): array
  {
    $cats         = [];
    $repositories = [];
    if (strlen($project_name) === 0) {
      $project_name = $this->name;
    }

    $projects = $this->options->fullTree($this->options->fromCode('path', $project_name, 'list', 'project', 'appui'));
    if (!empty($projects) && !empty($projects['items'])) {
      $projects = $projects['items'];
      foreach ($projects as $i => $project){
        $paths = $this->options->fullTree($project['id']);
        if (isset($paths['items']) && count($paths['items'])) {
          foreach ($paths['items'] as $repository){
              $name = $paths['code'] . '/' . $repository['code'];
            if (!isset($cats[$repository['id_alias']])) {
              unset($repository['alias']['cfg']);
              $cats[$repository['id_alias']] = $repository['alias'];
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
            }
            elseif (!empty($cats[$repository['id_alias']]['extensions'])) {
              $repositories[$name]['extensions'] = $cats[$repository['id_alias']]['extensions'];
            }
            elseif (!empty($cats[$repository['id_alias']]['types'])) {
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
    $idx = \bbn\X::find($this->repositories, ['id' => $id]) ?: '';
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
    if (!empty($this->repositories)
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
    foreach ($this->repositories as $i => $d){
      $root = isset($d['root_path']) ? $d['root_path'] : $this->getRootPath($d['name']);
      if ($root
          && (strpos($file, $root) === 0)
      ) {
        $rep = $i;
        break;
      }
    }

    if (isset($rep)) {
      $res = $rep.'/src/';

      $bits = explode('/', substr($file, \strlen($root)));
      // MVC
      if (!empty($d['tabs'])) {
        $tab_path = array_shift($bits);
        $fn       = array_pop($bits);
        $ext      = \bbn\Str::fileExt($fn);
        $fn       = \bbn\Str::fileExt($fn, 1)[0];
        $res     .= implode('/', $bits);
        foreach ($d['tabs'] as $k => $t){
          if (empty($t['fixed'])
              && ($t['path'] === $tab_path . '/')
          ) {
            $res .= "/$fn/$t[url]";
            break;
          }
        }
      }
      // Normal file
      else {
        $res .= implode('/', $bits);
      }

      return \bbn\Str::parsePath($res);
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
    if (is_array($this->repositories)
        && count($this->repositories)
    ) {//if in url name of repository break loop
      foreach ($this->repositories as $i => $d){
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
      $this->repositories = $this->getRepositories($o['text']);
      //the id of the child option 'lang' (children of this option are all languages for which the project is configured)
      if (!$this->id_langs = $this->options->fromCode('lang', $o['code'], 'list', 'project', 'appui')) {
        $this->setIdLangs();
      }

      //the id of the child option 'path'
      $this->id_path = $this->options->fromCode('path', $o['code'], 'list', 'project', 'appui') ?: null;
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
