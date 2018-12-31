<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
namespace bbn\cdn;
use bbn;

class library
{
  use common;
  
  private $latest = 1;
  protected $db;
  public
          $libs = [],
          $js = [],
          $css = [],
          $lang = 'en',
          $theme = false,
          $vars = [];
  
  public function __construct(bbn\db $db, $lang = 'en', $latest = false) {
    $this->db = $db;
    $this->lang = $lang;
    if ( $latest !== false ){
      $this->latest = 1;
    }
  }

  private function get_dependencies($id_version, &$res = []){
    $deps = $this->db->get_col_array('
      SELECT "dependencies"."id_master"
      FROM "main"."dependencies"
      JOIN "main"."versions"
        ON "main"."dependencies"."id_master" = "main"."versions"."id"
      WHERE "dependencies"."id_slave" = ?
      GROUP BY "versions"."library"
      ORDER BY "versions"."internal" DESC, "dependencies"."id_slave" ASC',
      $id_version
    );
    if ( !empty($deps) ){
      foreach ( $deps as $dep ){
        $d = $this->db->get_one('
        SELECT "library" || "|" || "name"
        FROM "main"."versions"
        WHERE "versions"."id" = ?',
          $dep
        );
        if ( ($dep !== $id_version) && !in_array($d, $res) ){
          $this->get_dependencies($dep, $res);
          array_push($res, $d);
        }
      }
    }
    return $res;
  }

  public function info($library){
    $params = explode('|', $library);
    $lib = array_shift($params);
    $sql = 'SELECT "libraries"."name", "libraries"."fname", "libraries"."title",
      "libraries"."latest", "libraries"."website",
      "libraries"."last_update", "libraries"."last_check",';
    $args = [];
    if ( isset($params[0]) && $params[0] !== 'latest' ){
      $sql .= '
      IFNULL("v1"."id", "v2"."id") AS "id",
      IFNULL("v1"."name", "v2"."name") AS "version",
      IFNULL("v1"."content", "v2"."content") AS "content",
      IFNULL("v1"."internal", "v2"."internal") AS "internal"
      FROM "main"."libraries", "main"."versions"
        LEFT JOIN "main"."versions" AS "v1"
          ON "v1"."library" = "libraries"."name"
          AND "v1"."name" LIKE ?
        LEFT JOIN "main"."versions" AS "v2"
          ON "v2"."library" = "libraries"."name"
          AND "v2"."status" LIKE \'stable\' ';
      array_push($args, $params[0]);
    }
    else{
      $sql .= '
      "versions"."id", "versions"."name" AS "version", "versions"."content" , "versions"."internal" 
      FROM "main"."libraries"
        LEFT JOIN "main"."versions"
          ON "versions"."library" = "libraries"."name"
          AND "versions"."name" LIKE "libraries"."latest" ';
    }
    $sql .= '
      WHERE "libraries"."name" LIKE ? 
      GROUP BY "libraries"."name"
      ORDER BY "versions"."internal" DESC';
    $args[] =  $lib;
    if ( $info = $this->db->get_row($sql, $args) ){
      $info['content'] = json_decode($info['content']);
      if ( !empty($info['content']->theme_files) && !empty($info['content']->files) ){
        if ( !empty($params[1]) ){
          $ths = explode('!', $params[1]);
        }
        else if ( isset($info['content']->default_theme) ){
          $ths = [$info['content']->default_theme];
        }
        if ( isset($ths) ){
          foreach ( $ths as $th ){
            foreach ( $info['content']->theme_files as $tf ){
              array_push($info['content']->files, sprintf(str_replace('%s', '%1$s', $tf), $th));
            }
          }
        }
      }
      if ( !isset($ths) && isset($info['content']->themes) ){
        if ( isset($params[1], $info['content']->themes->$params[1]) ){
          $info['theme'] = $params[1];
        }
        else if ( isset($info['content']->default_theme) ){
          $info['theme'] = $info['content']->default_theme;
        }
        if ( isset($info['theme'], $info['content']->themes->{$info['theme']}) ){
          if ( !is_array($info['content']->themes->{$info['theme']}) ){
            $info['content']->themes->$info['theme'] = [$info['content']->themes->$info['theme']];
          }
          $info['content']->files = array_merge($info['content']->files, $info['content']->themes->$info['theme']);
        }
      }
      
      return $info;
    }
    return false;
  }
  
  public function add($library, $has_dep = 1){
    if ( $info = $this->info($library) ){
  
      if ( !isset($this->libs[$info['name']][$info['internal']]) ){
        
        if ( $has_dep ){
          // Adding dependencies
          $dependencies = $this->get_dependencies($info['id']);
          //bbn\x::dump($dependencies, $info);
          if ( !empty($dependencies) ){
            foreach ( $dependencies as $dep ){
              $this->add($dep);
            }
          }
        }

        if ( !isset($this->libs[$info['name']]) ){
          $this->libs[$info['name']] = [];
        }

        $this->libs[$info['name']][$info['internal']] = [
            'version' => $info['version'],
            'files' => []
        ];
        $files =& $this->libs[$info['name']][$info['internal']]['files'];
        
        $path = 'lib/'.$info['name'].'/'.$info['version'].'/';
        
        
        // From here, adding files (no matter the type) to $this->libs array for each library
        // Adding language files if they must be prepent
        if ( ($this->lang !== 'en') && isset($info['content']->lang, $info['content']->prepend_lang) ){
          foreach ( $info['content']->lang as $lang ){
            $files[] = sprintf($path.$lang, $this->lang);
          }
        }

        if ( isset($info['content']->files) && is_array($info['content']->files) ){
          // Adding each files - no matter the type
          foreach ( $info['content']->files as $f ){
            if ( isset($this->info['theme']) && strpos($f, '%s') ){
              $f = sprintf($f, $this->info['theme']);
            }
            $files[] = $path.$f;
          }
        }
        else{
          die(bbn\x::dump("Error!", $info));
        }
        
        // Adding language files at the end (default way)
        if ( ($this->lang !== 'en') && isset($info['content']->lang) && !isset($info['content']->prepend_lang) ){
          if ( is_string($info['content']->lang) ){
            $info['content']->lang = [$info['content']->lang];
          }
          if ( is_array($info['content']->lang) ){
            foreach ( $info['content']->lang as $lang ){
              array_push($files, sprintf($path.$lang, $this->lang));
            }
          }
          else{
            die("Problem with the language file for $info[name]");
          }
        }

        
      }
    }
    return $this;
  }
  
  public function get_config(){
    $res = [
        "libraries" => []
    ];
    foreach ( $this->libs as $lib_name => $lib ){
      ksort($lib);
      $lib = current($lib);
      $res['libraries'][$lib_name] = (string) $lib['version'];
      foreach ( $lib['files'] as $f ){
        $ext = bbn\str::file_ext($f);
        foreach ( self::$types as $type => $extensions  ){
          if ( in_array($ext, $extensions) ){
            if ( !isset($res[$type]) ){
              $res[$type] = [];
            }
            if ( !in_array($f, $res[$type]) ){
              $res[$type][] = $f;
            }
          }
        }
      }
    }
    return $res;
  }
}
?>
