<?php
/**
 * @package appui
 */
namespace bbn\appui;

use bbn;

class dashboard {

  use bbn\models\tts\optional;

  protected 
    /** @var \bbn\appui\options */
    $opt,
    /** @var \bbn\user */
    $user,
    /** @var \bbn\user\permissions */
    $perm,
    /** @var \bbn\user\preferences */
    $pref,
    /** @var array */
    $arch_opt,
    /** @var array */
    $arch_pref,
    /** @var array */
    $arch_bits,
    /** @var string */
    $id_widgets;


  private function filter_by_permissions(array $widgets): array
  {
    $t = $this;
    // Filter the widgets by user's permissions
    return \array_values(\array_filter($widgets, function($w) use($t){
      return !empty($w[$t->arch_opt['id_alias']]) && $t->perm->has($w[$t->arch_opt['id_alias']]);
    }));
  }

  private function get_widget_pref($id_opt){
    $t = $this;
    // Get the personal user's widgets
    if ( !$prefs = $this->pref->get_all($id_opt) ){
      $prefs = [];
    }
    // Fix the widget structure
    return \array_map(function($p) use($t){
      return \array_merge([
        $t->arch_pref['id'] => $p[$t->arch_pref['id']],
        $t->arch_pref['id_option'] => $p[$t->arch_pref['id_option']],
        $t->arch_pref['text'] => $p[$t->arch_pref['text']],
        $t->arch_pref['num'] => $p[$t->arch_pref['num']]
      ], $p['widget']);
    }, \array_values(\array_filter($prefs, function($p){
      return !empty($p['widget']);
    })));
  }

  /**
   * dashboard contrusctor.
   */
  public function __construct(){
    $this->opt = bbn\appui\options::get_instance();
    $this->user = bbn\user::get_instance();
    $this->perm = bbn\user\permissions::get_instance();
    $this->pref = bbn\user\preferences::get_instance();
    $ccfg = $this->pref->get_class_cfg();
    $this->arch_opt = $this->opt->get_class_cfg()['arch']['options'];
    $this->arch_pref = $ccfg['arch']['user_options'];
    $this->arch_bits = $ccfg['arch']['user_options_bits'];
    self::optional_init();
    $this->id_widgets = $this->get_option_id('widgets');
  }

  /**
   * Saves the widget configuration
   * @param array $data
   * @return string|int
   */
  public function save(array $data){
    if ( !empty($data[$this->arch_pref['id']]) ){
      // Current option's id or preference's id
      $id = $data[$this->arch_pref['id']];
      // Normal widget
      if ( $cfg = $this->pref->get_by_option($id) ){
        return $this->pref->set_by_option($id, \bbn\x::merge_arrays($cfg, $data[$this->arch_pref['cfg']]));
      }
      // User's personal widget
      else if ( $old = $this->pref->get($id, false) ){
        if ( ($cfg = json_decode($old[$this->arch_pref['cfg']], true)) && !empty($cfg['widget']) ){
          $cfg['widget'] = \bbn\x::merge_arrays($cfg['widget'], $data[$this->arch_pref['cfg']]);  
          return $this->pref->set_cfg($id, $cfg);  
        }
      }
      else {
        return $this->pref->add($id, $data[$this->arch_pref['cfg']]);  
      }
    }
  }

  /**
   * Sorts the widgets' order
   * @param array $order The ordered keys list
   * @return int|null
   */
  public function sort(array $order): ?int
  {
    if ( !empty($order) ){
      $changed = 0;
      foreach ( $order as $i => $k ){
        // Normal widget
        if ( $opt = $this->opt->option($k) ){
          // Existing preference
          if ( $pref = $this->pref->get_by_option($k) ){
            if ( $pref[$this->arch_pref['num']] !== ($i + 1) ){
              $pref[$this->arch_pref['num']] = $i + 1;
              $this->pref->update($pref[$this->arch_pref['id']], $pref);
              $changed++;
            }
          }
          // Add new preference
          else if ( $opt[$this->arch_opt['num']] !== ($i +1) ){
            $this->pref->add($k, [$this->arch_pref['num'] => $i + 1]);
            $changed++;
          }
        }
        // User's personal widget
        else if ( 
          ($pref = $this->pref->get($k)) &&
          ($pref[$this->arch_pref['num']] !== ($i + 1))
        ){
          $pref[$this->arch_pref['num']] = $i + 1;
          $this->pref->update($pref[$this->arch_pref['id']], $pref);
          $changed++;
        }
      }
      return $changed;
    }
    return null;
  }

  /**
   * Gets the widgets list of a dashboard.
   * @param string $id_opt The dashboard option ID.
   * @param string $url The url to set to the widget's property.
   * @return array|null
   */
  public function get_widgets(string $id_opt, string $url = ''): ?array
  {
    if ( bbn\str::is_uid($id_opt) ){
      $t = $this;
      // Get all widgets options
      if ( $widgets = $this->opt->full_options($id_opt) ){
        // Filter the widgets by user's permissions
        $widgets = $this->filter_by_permissions($widgets);
        // Get the personal user's widgets
        $prefs = $this->get_widget_pref($id_opt);
        // Merge the personal widgets with the globals;
        $widgets = \array_merge($widgets, $prefs);
        
        foreach ( $widgets as $i => $w ){
          // Get the preferences of the single widget
          if ( 
            (\bbn\x::find($prefs, [$this->arch_pref['id'] => $w[$this->arch_pref['id']]]) === false) &&
            ($p = $this->pref->get_by_option($w[$this->arch_pref['id']], false))
          ){
            if ( !empty($p[$this->arch_pref['cfg']]) ){
              $widgets[$i] = \array_merge($widgets[$i], json_decode($p[$this->arch_pref['cfg']], true));
            }
            if ( !is_null($p[$this->arch_pref['num']]) ){
              $widgets[$i][$this->arch_opt['num']] = $p[$this->arch_pref['num']];
            }
          }
          // set the widget's url
          if ( !empty($w[$this->arch_opt['code']]) ){
            $widgets[$i]['url'] = $url.$w[$this->arch_opt['code']];
          }
          // set the widget's key
          $widgets[$i]['key'] = $w[$this->arch_opt['id']];
          unset(
            $widgets[$i][$this->arch_opt['id_alias']],
            $widgets[$i][$this->arch_opt['code']],
            $widgets[$i]['num_children'],
            $widgets[$i][$this->arch_opt['id']],
            $widgets[$i][$this->arch_opt['id_parent']]);
        }
        return $widgets;
      }
      return [];
    }
    return null;
  }

  
  public function get_order(array $widgets): array
  {
    $ret = [];
    foreach ( $widgets as $widget ){
      if ( !\array_key_exists($widget[$this->arch_opt['num']], $ret) ){
        $ret[$widget[$this->arch_opt['num']]] = $widget['key'];
      }
      else {
        $ret[] = $widget['key'];
      }
    }
    \ksort($ret);
    return \array_values($ret);
  }
  
}