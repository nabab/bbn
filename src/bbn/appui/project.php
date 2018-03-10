<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 14/12/2017
 * Time: 17:34
 */

namespace bbn\appui;
use bbn;


class project extends bbn\models\cls\db{

  use
    bbn\models\tts\optional;

  protected static $extensions = ['js', 'json', 'php'];

  protected static $id_type_path,
                   $id_type_lang;

  protected
    $id,
    $name,
    $lang,
    $assets = [
      'path' => [],
      'langs' => []
    ];

  public static function get_id_asset_lang(){
    if ( !isset(self::$id_type_lang) ){
      self::$id_type_lang = options::get_instance()->from_code('lang', 'assets','projects','appui');
    }
    return self::$id_type_lang;
  }

  public static function get_id_asset_path(){
    if ( !isset(self::$id_type_path) ){
      self::$id_type_path = options::get_instance()->from_code('path', 'assets','projects','appui');
    }
    return self::$id_type_path;
  }

  public function __construct(bbn\db $db, string $id){
    parent::__construct($db);
    $where = bbn\str::is_uid($id) ? ['id' => $id] : ['name' => $id];
    if ( $row = $this->db->rselect('bbn_projects', [], $where) ){
      $this->id = $row['id'];
      $this->name = $row['name'];
      $this->lang = $row['lang'];
    }
  }

  public function check(){
    return parent::check() && !empty($this->id);
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
    }
    foreach ( $rows as $i => $row ){
      $rows[$i]['constant'] = options::get_instance()->parent($rows[$i]['id_option'])['code'];
    }
    return $rows;
  }

  public function get_path_text($t){
    return  options::get_instance()->text($t);
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
    if ( !empty($this->get_langs_id() ) ){
      $tmp = [];
      foreach ( $this->get_langs_id()  as $t ){
        $tmp[] = options::get_instance()->text($t);
      }
      return $tmp;
    }
  }

  public function get_langs_code(){
    if ( !empty($this->get_langs_id() ) ){
      $tmp = [];
      foreach ( $this->get_langs_id()  as $t ){
        $tmp[] = options::get_instance()->code($t);
      }
      return $tmp;
    }
  }


  public function get_langs(){
    $tmp = $this->get_langs_id();
    if ( !empty($tmp ) ){
      $res = [];
      $lang = $this->get_lang();
      foreach ( $tmp as $t ){
        $o = options::get_instance()->option($t);
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
	

	
	
}