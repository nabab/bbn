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
    $cfg = [
      'table' => 'libraries',
      'fields' => [
        'libraries.name', 'libraries.fname', 'libraries.title',
        'libraries.latest', 'libraries.website', 'libraries.last_update',
        'libraries.last_check'
      ],
      'where' => [
        'libraries.name' => $lib
      ]
    ];
    if ( isset($params[0]) && ($params[0] !== 'latest') ){
      $cfg['fields']['id'] = 'IFNULL(v1.id, v2.id)';
      $cfg['fields']['version'] = 'IFNULL(v1.name, v2.name)';
      $cfg['fields']['content'] = 'IFNULL(v1.content, v2.content)';
      $cfg['fields']['internal'] = 'IFNULL(v1.internal, v2.internal)';
      $cfg['join'] =
      [
        [
          'table' => 'versions',
          'alias' => 'v1',
          'type' => 'left',
          'on' => [
            'conditions' => [
              [
                'field' => 'v1.library',
                'operator' => '=',
                'exp' => 'libraries.name'
              ], [
                'field' => 'v1.name',
                'operator' => 'LIKE',
                'value' => $lib
              ]
            ]
          ]
        ], [
          'table' => 'versions',
          'alias' => 'v2',
          'type' => 'left',
          'on' => [
            'conditions' => [
              [
                'field' => 'v2.library',
                'operator' => '=',
                'exp' => 'libraries.name'
              ], [
                'field' => 'v2.name',
                'operator' => '=',
                'exp' => 'libraries.latest'
              ]
            ]
          ]
        ]
      ];
    }
    else{
      $cfg['fields'][] = 'versions.id';
      $cfg['fields']['version'] = 'versions.name';
      $cfg['fields'][] = 'versions.content';
      $cfg['fields'][] = 'versions.internal';
      $cfg['join'] = [
        [
          'table' => 'versions',
          'type' => 'left',
          'on' => [
            'conditions' => [
              [
                'field' => 'versions.library',
                'operator' => '=',
                'exp' => 'libraries.name'
              ], [
                'field' => 'versions.name',
                'operator' => '=',
                'exp' => 'libraries.latest'
              ]
            ]
          ]
        ]
      ];
    }
    // We take all the info from the database
    if ( $info = $this->db->rselect($cfg) ){
      // Most of the info is in the JSON field, content
      $info['content'] = json_decode($info['content']);
      // The files from which the content will be prepended to corresponding files
      $info['prepend'] = [];
      // If there are theme files we want to add them to the list of files
      if ( !empty($info['content']->theme_files) && isset($info['content']->files) ){
        // Parameters of the library sent through the URL
        if ( !empty($params[1]) ){
          $ths = explode('!', $params[1]);
        }
        else if ( isset($info['content']->default_theme) ){
          $ths = [$info['content']->default_theme];
        }
        if ( !empty($ths) ){
          foreach ( $ths as $th ){
            if ( !empty($info['content']->theme_prepend) ){
              foreach ( array_reverse($info['content']->theme_files) as $tf ){
                foreach ( $info['content']->files as $f ){
                  /** @todo Remove!!! */
                  if ( substr($f, -4) === 'less' ){
                    $info['prepend'][$f] = sprintf(str_replace('%s', '%1$s', $tf), $th);
                  }
                }
              }
            }
            else{
              foreach ( $info['content']->theme_files as $tf ){
                $info['content']->files[] = sprintf(str_replace('%s', '%1$s', $tf), $th);
              }
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
        $last = $this->db->last();
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
            'prepend' => [],
            'files' => []
        ];
        $files =& $this->libs[$info['name']][$info['internal']]['files'];
        $prepend =& $this->libs[$info['name']][$info['internal']]['prepend'];
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
            if ( isset($info['prepend'][$f]) ){
              $prepend[$path.$f] = $path.$info['prepend'][$f];
            }
            $files[] = $path.$f;
          }
        }
        else{
          die(\bbn\x::dump("Error!", $info, $last));
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
      'libraries' => [],
      'prepend' => []
    ];
    foreach ( $this->libs as $lib_name => $lib ){
      ksort($lib);
      $lib = current($lib);
      $res['libraries'][$lib_name] = (string) $lib['version'];
      if ( isset($lib['prepend']) ){
        $res['prepend'] = array_merge($res['prepend'], $lib['prepend']);
      }
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
