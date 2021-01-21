<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 14/12/2017
 * Time: 17:34
 */

namespace bbn\appui;

use bbn;
use bbn\x;

class project extends bbn\models\cls\db
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
   * Construct the class project
   *
   * @param bbn\db $db
   * @param string $id
   */
  public function __construct(bbn\db $db, string $id = null)
  {
    parent::__construct($db);
    $this->options = bbn\appui\option::get_instance();
    $this->fs      = new \bbn\file\system();
    if (\bbn\str::is_uid($id)) {
      $this->id = $id;
    }
    elseif (\is_string($id)) {
      $this->id = $this->options->from_code($id, 'list', 'project', 'appui');
    }
    elseif (defined('BBN_APP_NAME')) {
      $this->id = $this->options->from_code(BBN_APP_NAME, 'list', 'project', 'appui');
    }

    if (!empty($this->id)) {
      $this->set_project_info($this->id);
    }
  }


  public function check()
  {
    return parent::check() && !empty($this->id);
  }


  public function get_environment($app_name = null): ?array
  {
    $file_environment = \bbn\mvc::get_app_path().'cfg/environment';
    if ($this->fs->is_file($file_environment.'.json')) {
      $envs = \json_decode($this->fs->get_contents($file_environment.'.json'), true)[0];
    }
    elseif ($this->fs->is_file($file_environment.'.yml')) {
      $envs = \yaml_parse($this->fs->get_contents($file_environment.'.yml'), true)[0];
    }
    return $envs ?? null;
  }


  /**
   * Change the value of the property i18n on the option of the project
   *
   * @param string $lang
   * @return void
   */
  public function change_project_lang(string $lang)
  {
    if ($cfg = $this->options->get_cfg($this->id)) {
      $cfg['i18n'] = $lang;
      $this->lang  = $lang;
      $success     = $this->options->set_cfg($this->id, $cfg);
      $this->options->delete_cache($this->id, true);
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
  public function get_project_info()
  {
    if ($this->id) {
      return [
        'path' => $this->get_paths(),
        'langs' => $this->get_langs_ids(),
        'id' => $this->id,
        'lang' => $this->get_lang(),
        'name' => $this->get_name()
      ];
    }

    return [];
  }


  public function get_id_lang()
  {
    return $this->id_langs;
  }


  public function get_lang()
  {
    return $this->lang;
  }


  public function get_id()
  {
    return $this->id;
  }


  public function get_name()
  {
    return $this->name;
  }


  /**
   * Returns an array containing about the repositories in  the project
   *
   * @return void
   */
  public function get_paths()
  {
    $paths = [];
    if ($this->check() && $this->id_path && !empty($this->repositories)) {
      foreach($this->repositories as $rep){
        $paths[] = [
          'id_option' => $rep['id'],
          'path' => $this->get_root_path($rep['name']),
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
  public function get_langs_ids()
  {
    $ids = [];
    $res = [];
    $this->options->delete_cache($this->id, true);
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
  public function get_primaries_langs()
  {
    $uid_languages = $this->options->from_code('languages', 'i18n', 'appui');
    $languages     = $this->options->full_tree($uid_languages);
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
  public function creates_lang_options(array $langs = [])
  {
    if (empty($langs)) {
      $primaries = $this->get_primaries_langs();
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
   * @return bool|string
   */
  public function get_root_path($rep)
  {
    //if only name else get info repository
    $path       = '';
    $repository = \is_string($rep) ? $this->repositories[$rep] : $rep;
    if ((!empty($repository) && is_array($repository))
        && !empty($repository['path'])
        && !empty($repository['root'])
        && !empty($repository['code'])
    ) {
      switch($repository['root']){
        case 'app':
          $path = $this->get_app_path();
          break;
        /* case 'cdn':
          die(var_dump('cdn'));
        break;*/
        case 'lib':
          $path  = $this->get_lib_path();
          $path .= $repository['path'];
          if ($repository['alias_code'] === 'bbn-project') {
            $path .= '/src/';
          }
          break;
        case 'cdn':
          $path = $this->get_cdn_path();
          break;
      }
    }

    return $path;
  }


  /**
   * Gets the app path
   *
   * @return void
   */
  public function get_app_path()
  {
    if ($this->name === BBN_APP_NAME) {
      return \bbn\mvc::get_app_path();
    }
    else{// case bbn-vue

      if (($envs = $this->get_environment()) && !empty($envs['app_path'])) {
        return $envs['app_path'].'src/';
      }
    }
  }


  /**
   * Gets the app path
   *
   * @return void
   */
  public function get_cdn_path()
  {
    if ($this->name === BBN_APP_NAME) {
      return BBN_CDN_PATH;
    }
    elseif ($content = $this->get_environment()) {
      return $content['app_path'].'src/';
    }
  }


  /**
   * Gets the lib path
   *
   * @return void
   */
  public function get_lib_path()
  {
    if ($this->name === BBN_APP_NAME) {
      return \bbn\mvc::get_lib_path();
    }
    elseif ($content = $this->get_environment()) {
      return $content['lib_path'].'bbn\/';
    }
  }


  /**
   * Gets the data path
   *
   * @return void
   */
  public function get_data_path(string $plugin = null)
  {
    if ($this->name === BBN_APP_NAME) {
      return \bbn\mvc::get_data_path($plugin);
    }
    elseif ($content = $this->get_environment()) {
      $path = $content['data_path'];
      if ($plugin) {
        $path .= 'plugins/'.$plugin.'/';
      }
      return $path;
    }
  }


  /**
   * Gets the data path
   *
   * @return void
   */
  public function get_user_data_path(string $plugin = null)
  {
    if ($this->name === BBN_APP_NAME) {
      return \bbn\mvc::get_user_data_path($plugin);
    }
    elseif ($content = $this->get_environment()) {
      $path = $content['data_path'];
      if ($plugin) {
        $path .= 'plugins/'.$plugin.'/';
      }
      return $path;
    }
  }


   /**
   * Makes the repositories' configurations.
   *
   * @param string $code The repository's name (code)
   * @return array|bool
   */
  public function get_repositories(string $project_name ='')
  {
    $cats         = [];
    $repositories = [];
    if (strlen($project_name) === 0) {
      $project_name = $this->name;
    }

    $projects = $this->options->full_tree($this->options->from_code('path', $project_name, 'list', 'project', 'appui'));
    if (!empty($projects) && !empty($projects['items'])) {
      $projects = $projects['items'];
      foreach ($projects as $i => $project){
        $paths = $this->options->full_tree($project['id']);
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
  public function repository_by_id(string $id)
  {
    $idx = \bbn\x::find($this->repositories, ['id' => $id]) ?: '';
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
  public function real_to_url(string $file)
  {
    foreach ($this->repositories as $i => $d){
      $root = isset($d['root_path']) ? $d['root_path'] : $this->get_root_path($d['name']);
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
        $ext      = \bbn\str::file_ext($fn);
        $fn       = \bbn\str::file_ext($fn, 1)[0];
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

      return \bbn\str::parse_path($res);
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
  public function repository_from_url(string $url, bool $obj = false)
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
    //return $this->projects->repository_from_url($url, $obj);
  }


  /**
   * Defines the variables
   *
   * @param  $id
   * @param string $name
   * @param string $lang
   * @return void
   */
  private function set_project_info(string $id = null)
  {
    if ($o = $this->options->option($id ?: $this->id)) {
      $cfg                = $this->options->get_cfg($id ?: $this->id);
      $this->name         = $o['text'];
      $this->lang         = $cfg['i18n'] ?: '';
      $this->code         = $o['code'];
      $this->option       = $o;
      $this->repositories = $this->get_repositories($o['text']);
      //the id of the child option 'lang' (children of this option are all languages for which the project is configured)
      if (!$this->id_langs = $this->options->from_code('lang', $o['code'], 'project', 'appui')) {
        $this->set_id_langs();
      }

      //the id of the child option 'path'
      $this->id_path = $this->options->from_code('path', $o['code'], 'project', 'appui') ?: null;
      return true;
    }

    return false;
  }


  /**
   * If the child option lang is not yet created it creates the option
   *
   * @return void
   */
  private function set_id_langs()
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
