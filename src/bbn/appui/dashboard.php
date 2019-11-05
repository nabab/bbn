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
    $dashboard;

    /**
   * Gets the widgets list of a dashboard.
   * @param string $url The url to set to the widget's property.
   * @return array
   */
    private function _get_widgets(string $url = '', $with_code = false): array
    {
      $t = $this;
      // Get all widgets options
      if ( $widgets = $this->opt->full_options($this->dashboard) ){
        // Filter the widgets by user's permissions
        $widgets = $this->filter_by_permissions($widgets);
        // Get the personal user's widgets
        $prefs = $this->get_widget_pref();
        if ( \is_array($prefs) ){
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
            // Set the widget's url
            if ( !empty($w[$this->arch_opt['code']]) ){
              $widgets[$i]['url'] = $url.$w[$this->arch_opt['code']];
            }
            // Set the hidden property
            $widgets[$i]['hidden'] = $widget['hidden'] ?? false;
            // Set the widget's key
            $widgets[$i]['key'] = $w[$this->arch_opt['id']];
            unset(
              $widgets[$i][$this->arch_opt['id_alias']],
              $widgets[$i]['num_children'],
              $widgets[$i][$this->arch_opt['id']],
              $widgets[$i][$this->arch_opt['id_parent']]
            );
            if ( !$with_code ){
              unset($widgets[$i][$this->arch_opt['code']]);
            }
          }
          return $widgets;
        }
      }
      return [];
    }

  /**
   * Filters the widgets by the user's permissions
   * @param array $widgets The list of widgets
   * @return array
   */
  private function filter_by_permissions(array $widgets): array
  {
    $t = $this;
    // Filter the widgets by user's permissions
    return \array_values(\array_filter($widgets, function($w) use($t){
      return !empty($w[$t->arch_opt['id_alias']]) && $t->perm->has($w[$t->arch_opt['id_alias']]);
    }));
  }

  /** 
   * Get the personal user's widgets from the preferences
   * @param string The dashboard id
   * @return array
   */
  private function get_widget_pref(): array
  {
    $t = $this;
    // Get the personal user's widgets
    if ( !$prefs = $this->pref->get_all($this->dashboard) ){
      $prefs = [];
    }
    // Fix the widget structure
    return \array_map(function($p) use($t){
      return \array_merge([
        $t->arch_pref['id'] => $p[$t->arch_pref['id']],
        $t->arch_pref['id_option'] => $p[$t->arch_pref['id_option']],
        $t->arch_pref['text'] => $p[$t->arch_pref['text']],
        $t->arch_pref['num'] => $p[$t->arch_pref['num']],
        'hidden' => isset($p['hidden']) ? !!$p['hidden'] : false
      ], $p['widget']);
    }, \array_values(\array_filter($prefs, function($p){
      return !empty($p['widget']);
    })));
  }

  /**
   * dashboard contrusctor.
   */
  public function __construct(string $id){
    $this->opt = bbn\appui\options::get_instance();
    $this->user = bbn\user::get_instance();
    $this->perm = bbn\user\permissions::get_instance();
    $this->pref = bbn\user\preferences::get_instance();
    $ccfg = $this->pref->get_class_cfg();
    $this->arch_opt = $this->opt->get_class_cfg()['arch']['options'];
    $this->arch_pref = $ccfg['arch']['user_options'];
    $this->arch_bits = $ccfg['arch']['user_options_bits'];
    self::optional_init();
    if ( !bbn\str::is_uid($id) ){
      $id = $this->get_option_id($id);
    }
    if ( bbn\str::is_uid($id) ){
      $this->dashboard = $id;
    }
    else {
      die();
    }
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
   * Gets the widgets list of a dashboard and the order list.
   * @param string $url The url to set to the widget's property.
   * @return array
   */
  public function get_widgets(string $url = ''): array
  {
    $widgets = $this->_get_widgets($url);
    return [
      'widgets' => $widgets,
      'order' => $this->get_order($widgets)
    ];
  }

  /**
   * Gets an orderder array of the widgets' keys
   * @param array $widgets The list of widgets
   * @return array
   */
  public function get_order(array $widgets): array
  {
    $ret = [];
    $toend = [];
    if ( bbn\x::is_assoc($widgets) ){
      $widgets = \array_values($widgets);
    }
    foreach ( $widgets as $widget ){
      if ( 
        bbn\str::is_integer($widget[$this->arch_opt['num']]) &&
        !\array_key_exists($widget[$this->arch_opt['num']], $ret) 
      ){
        $ret[$widget[$this->arch_opt['num']]] = $widget['key'];
      }
      else {
        $toend[] = $widget['key'];
      }
    }
    \ksort($ret);
    return \array_values(\array_merge($ret, $toend));
  }

  /**
   * Gets an associative array of widgets by code
   * @param string $url
   * @return array
   */
  public function get_widgets_code(string $url = ''): array
  {
    $widgets = $this->_get_widgets($url, true);
    $ret = [];
    foreach ( $widgets as $w ){
      $ret[$w[$this->arch_opt['code']]] = $w;
    }
    \ksort($ret);
    return $ret;
  }
  
}