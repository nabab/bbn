<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 14/12/2017
 * Time: 17:34
 */

namespace bbn\appui;
use bbn;


class project extends bbn\models\cls\db {

  const
    BBN_APPUI = 'appui',
    BBN_PROJECTS = 'projects',
    PROJECTS_ASSETS = 'assets',
    ASSETS_LANG = 'lang',
    ASSETS_PATH = 'path';

  protected static
    $extensions = ['js', 'json', 'php'],
    $id_type_path,
    $id_type_lang;

  protected
    $id,
    $name,
    $lang,
    $assets = [
      'path' => [],
      'langs' => []
    ];

  private
    $options;

  private function get_project_info(string $id = ''){
    if (
      empty($id) ||
      ($id === $this->id)
    ){
      return [
        'id' => $this->id,
        'name' => $this->name,
        'lang' => $this->lang
      ];
    }
    else {
      $where = bbn\str::is_uid($id) ? ['id' => $id] : ['name' => $id];
      if ( $row = $this->db->rselect('bbn_projects', [], $where) ){
        return $row;
      }
    }
    return false;
  }

  private function set_project_info($id, string $name = '', string $lang = ''){
    if ( \is_array($id) ){
      $args = $id;
    }
    else if ( count(func_get_args()) === 1 ){
      $args = $this->get_project_info(func_get_args()[0]);
    }
    $this->id = !empty($args) ? $args['id'] : $id;
    $this->name = !empty($args) ? $args['name'] : $name;
    $this->lang = !empty($args) ? $args['lang'] : $lang;
  }


  public static function get_id_asset_lang(){
    if ( !isset(self::$id_type_lang) ){
      self::$id_type_lang = bbn\appui\options::get_instance()->from_root_code(
        self::ASSETS_LANG,
        self::PROJECTS_ASSETS,
        self::BBN_PROJECTS,
        self::BBN_APPUI
      );
    }
    return self::$id_type_lang;
  }

  public static function get_id_asset_path(){
    if ( !isset(self::$id_type_path) ){

      self::$id_type_path = bbn\appui\options::get_instance()->from_root_code(
        self::ASSETS_PATH,
        self::PROJECTS_ASSETS,
        self::BBN_PROJECTS,
        self::BBN_APPUI
      );
    }

    return self::$id_type_path;
  }

  public function __construct(bbn\db $db, string $id = ''){
    parent::__construct($db);
    $this->options = bbn\appui\options::get_instance();
    if ( empty($id) && defined('BBN_PROJECT') ){
      $id = BBN_PROJECT;
    }
    $this->set_project_info($id);
  }

  public function check(){
    return parent::check() && !empty($this->id);
  }

  public function get_translations_strings(){

  }

  public function get_lang(){
    return $this->lang;
  }

  public function get_id(){
    return $this->id;
  }

  public function get_name(){
    return $this->name;
  }

  public function get_path(){
    if ( $this->check() ){
      $rows = $this->db->get_rows("
        SELECT bbn_projects_assets.id_option,
          bbn_options.text, bbn_options.code
        FROM bbn_projects_assets
          JOIN bbn_options
            ON bbn_projects_assets.id_option = bbn_options.id
        WHERE bbn_projects_assets.id_project = ?
          AND bbn_projects_assets.asset_type = ?",
        hex2bin($this->id),
        hex2bin(self::get_id_asset_path()));
        
      foreach ( $rows as $i => $row ){
        $rows[$i]['constant'] = $this->options->parent($rows[$i]['id_option'])['code'];
        $rows[$i]['language'] = (string)$this->options->get_prop($rows[$i]['id_option'], 'language');
      }
      return $rows;
    }
    return false;
  }

  public function get_path_text($t){
    return $this->options->text($t);
  }

  public function get_langs_id(){
    if ( $this->check() ){
      return $this->db->get_field_values('bbn_projects_assets', 'id_option', [
        'id_project' => $this->id,
        'asset_type' => self::get_id_asset_lang()
      ]);
    }
  }

  public function get_langs_text(){
    if ( $ids = $this->get_langs_id() ){
      return array_map(function($v){
        return $this->options->text($v);
      }, $ids);
    }
  }

  public function get_langs_code(){
    if ( $ids = $this->get_langs_id() ){
      return array_map(function($v){
        return $this->options->code($v);
      }, $ids);
    }
  }

  public function get_langs(){
    $tmp = $this->get_langs_id();
    if ( !empty($tmp) ){
      $res = [];
      $lang = $this->get_lang();
      foreach ( $tmp as $t ){
        $o = $this->options->option($t);
        $res[$o['code']] = [
          'id' => $o['id'],
          'code' => $o['code'],
          'text' => $o['text'],
          'default' => $o['code'] === $lang
        ];
      }
      return $res;
    }
  }


  /************************* FROM IDE **************************/

  /**
   * Replaces the constant at the first part of the path with its value.
   *
   * @param string $st
   * @return bool|string
   */
  public static function decipher_path(string $st){
    $st = \bbn\str::parse_path($st);
    $bits = explode('/', $st);
    /** @var string $constant The first part of the path must be a constant */
    $constant = $bits[0];
    /** @var string $path The path that will be returned */
    $path = '';
    if ( \defined($constant) ){
      $path .= constant($constant);
      array_shift($bits);
    }
    $path .= implode('/', $bits);
    return $path;
  }

  /**
   * Gets the real root path from a repository's id as recorded in the options.
   *
   * @param string|array $repository The repository's name (code) or the repository's configuration
   * @return bool|string
   */
  public function get_root_path($repository){
    if ( \is_string($repository) ){
      $repository = $this->repository($repository);
    }
    if ( !empty($repository) && !empty($repository['bbn_path']) ){
      $repository_path = !empty($repository['path']) ? '/' . $repository['path'] : '';
      $path = self::decipher_path($repository['bbn_path'] . $repository_path) . '/';
      return \bbn\str::parse_path($path);
    }
    return false;
  }

  /**
   * Gets a repository's configuration.
   *
   * @param string $code The repository's name (code)
   * @return array|bool
   */
  public function repository(string $code){
    return $this->repositories($code);
  }

  /**
   * Makes the repositories' configurations.
   *
   * @param string $code The repository's name (code)
   * @return array|bool
   */
  public function repositories(string $code=''){
    $all = $this->options->full_soptions($this->options->from_code('PATHS', 'ide', 'appui'));      
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
        else if( !empty($cats[$a['id_alias']]['extensions']) ){
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
   * Returns the repository's name or object from an URL.
   *
   * @param string $url
   * @param bool $obj
   * @return bool|int|string
   */
  public function repository_from_url(string $url, bool $obj = false){
    $repository = '';
    $repositories = $this->repositories();

    foreach ( $repositories as $i => $d ){
      if ( (strpos($url, $i) === 0) &&
        (\strlen($i) > \strlen($repository) )
      ){
        $repository = $i;
      }
    }
    if ( !empty($repository) ){
      return empty($obj) ? $repository : $repositories[$repository];
    }
    return false;
  }

  /**
   * Returns the file's URL from the real file's path.
   *
   * @param string $file The real file's path
   * @return bool|string
   */
  public function real_to_url(string $file){

    foreach ( $this->repositories() as $i => $d ){
      if (
        // Repository's root path
        ($root = $this->get_root_path($d)) &&
        (strpos($file, $root) === 0)
      ){
				$res = $i;
        $bits = explode('/', substr($file, \strlen($root)));

        // MVC
        if ( !empty($d['tabs']) ){
          $tab_path = array_shift($bits);
          $fn = array_pop($bits);
          $ext = \bbn\str::file_ext($fn);
          $fn = \bbn\str::file_ext($fn, 1)[0];
          $res .= implode('/', $bits);
          foreach ( $d['tabs'] as $k => $t ){
            if (
              empty($t['fixed']) &&
              ($t['path'] === $tab_path . '/')
            ){
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
    }
    return false;
  }

    public function real_to_url_i18n(string $file){

    foreach ( $this->repositories() as $i => $d ){

      if (
        // Repository's root path
        ($root = $this->get_root_path($d)) &&
        (strpos($file, $root) === 0)
      ){
				$res = $i;

        if ( !empty( ( $parent_code = $this->options->code($d['id_parent']) )) ){

					$var = str_replace($root, '', $file);
				  $ext = \bbn\str::file_ext($var);


					if ( ( $parent_code === 'BBN_APP_PATH' ) ){
								//eccezione per apst app che punta ancora su mvc
						if ( strpos($res, 'mvc/') ){
							$res = str_replace('mvc/', '', $res );
						}
						else if ( strpos($res, 'plugins/') ){
							$res = $parent_code.'/';
						}

						$var = str_replace(constant($parent_code), '', $file);
					}

					$bits = explode('/', $var);
					$name = str_replace('.'.$ext, '', array_pop($bits));

					if ( (strpos($var, 'mvc') === 0) && ($bits[1] !== 'cli') || ( strpos($var, 'plugins') === 0 ) ){

						$tab_path = $bits[1];
						if( strpos($var, 'plugins') === 0 ){

							$tab_path = $bits[2];
							unset($bits[2]);
						}
						else{
							$tab_path = $bits[1];
							unset($bits[1]);
						}
						if ( $tab_path === 'html' ){
							$ext = 'html';
					  }
						else if ( $tab_path === 'public' ){
							$ext = 'php';
						}
						else if ( $tab_path === 'model' ){
							$ext = 'model';
						}

					}
					else if ( (strpos($var, 'components') === 0) && ($ext !== 'js') ){
						$ext = 'html';
					}

					$res .= implode($bits, '/').'/'.$name.'/'.$ext;


				}


        return \bbn\str::parse_path($res);
      }
    }
    return false;
  }



}
