<?php
/**
 * @package appui
 */
namespace bbn\Appui;

use bbn;
use Exception;

class Dashboard
{

  use bbn\Models\Tts\Optional;

  /** @var \bbn\Appui\Option */
  protected $opt;

  /** @var \bbn\User */
  protected $user;
  /** @var \bbn\User\Permissions */
  protected $perm;
  /** @var \bbn\User\Preferences */
  protected $pref;
  /** @var array */
  protected $arch_opt;
  /** @var array */
  protected $arch_pref;
  /** @var array */
  protected $arch_bits;
  /** @var string */
  protected $dashboard;

  /**
   * Gets the widgets list of a dashboard.
   * 
   * @param string $url The url to set to the widget's property.
   * @param bool $with_code If true the code property is also returned.
   * @return array
   */
  private function _get_widgets(string $url = '', bool $with_code = false): array
  {
    $t = $this;
    // Get all widgets options
    if ($widgets = $this->opt->fullOptions($this->dashboard)) {
      // Filter the widgets by user's permissions
      $widgets = $this->filterByPermissions($widgets);
      // Get the personal user's widgets
      $prefs = $this->getWidgetPref();
      if (\is_array($prefs)) {
        // Merge the personal widgets with the globals;
        $widgets = \array_merge($widgets, $prefs);
        foreach ($widgets as $i => $w){
          // Get the preferences of the single widget
          if ((\bbn\X::find($prefs, [$this->arch_pref['id'] => $w[$this->arch_pref['id']]]) === null) 
              && ($p = $this->pref->getByOption($w[$this->arch_pref['id']], false))
          ) {
            if (!empty($p[$this->arch_pref['cfg']])) {
              $widgets[$i] = \array_merge($widgets[$i], Json_decode($p[$this->arch_pref['cfg']], true));
            }
            if (!is_null($p[$this->arch_pref['num']])) {
              $widgets[$i][$this->arch_opt['num']] = $p[$this->arch_pref['num']];
            }
          }
          // Set the widget's url
          if (!empty($w[$this->arch_opt['code']])) {
            $widgets[$i]['url'] = $url.$w[$this->arch_opt['code']];
          }
          // Set the hidden property
          $widgets[$i]['hidden'] = $widgets[$i]['hidden'] ?? false;
          // Set the widget's key
          $widgets[$i]['key'] = $w[$this->arch_opt['id']];
          unset(
            $widgets[$i][$this->arch_opt['id_alias']],
            $widgets[$i]['num_children'],
            $widgets[$i][$this->arch_opt['id']],
            $widgets[$i][$this->arch_opt['id_parent']]
          );
          if (!$with_code) {
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
  private function filterByPermissions(array $widgets): array
  {
    $t = $this;
    // Filter the widgets by user's permissions
    return \array_values(
      \array_filter(
        $widgets, function ($w) use ($t) {
          return !empty($w[$t->arch_opt['id_alias']]) && $t->perm->has($w[$t->arch_opt['id_alias']]);
        }
      )
    );
  }

  /** 
   * Get the personal user's widgets from the preferences
   * @param string The dashboard id
   * @return array
   */
  private function getWidgetPref(): array
  {
    $t = $this;
    // Get the personal user's widgets
    if (!$prefs = $this->pref->getAll($this->dashboard)) {
      $prefs = [];
    }
    // Fix the widget structure
    return \array_map(
      function ($p) use ($t) {
        return \array_merge(
          [
          $t->arch_pref['id'] => $p[$t->arch_pref['id']],
          $t->arch_pref['id_option'] => $p[$t->arch_pref['id_option']],
          $t->arch_pref['text'] => $p[$t->arch_pref['text']],
          $t->arch_pref['num'] => $p[$t->arch_pref['num']],
          'hidden' => isset($p['hidden']) ? !!$p['hidden'] : false
          ], $p['widget']
        );
      }, \array_values(
        \array_filter(
          $prefs, function ($p) {
            return !empty($p['widget']);
          }
        )
      )
    );
  }

  /**
   * dashboard constructor.
   */
  public function __construct(string $id)
  {
    $oid = $id;
    $this->opt = bbn\Appui\Option::getInstance();
    $this->user = bbn\User::getInstance();
    $this->perm = bbn\User\Permissions::getInstance();
    $this->pref = bbn\User\Preferences::getInstance();
    $ccfg = $this->pref->getClassCfg();
    $this->arch_opt = $this->opt->getClassCfg()['arch']['options'];
    $this->arch_pref = $ccfg['arch']['user_options'];
    $this->arch_bits = $ccfg['arch']['user_options_bits'];
    self::optionalInit();
    if (!bbn\Str::isUid($id)) {
      $id = $this->getOptionId($id);
    }

    if (bbn\Str::isUid($id)) {
      $this->dashboard = $id;
    }
    else {
      throw new \Exception("Unable to load the dashboard using identifier: $oid");
    }
  }

  public function add(array $widget): ?string
  {
    if (!empty($widget[$this->arch_opt['text']]) 
        && !empty($widget[$this->arch_opt['code']]) 
        && (!empty($widget['component']) || !empty($widget['itemComponent']))
    ) {
      $widget[$this->arch_opt['id_parent']] = $this->dashboard;
      $widget[$this->arch_opt['id_alias']] = $widget[$this->arch_opt['id_alias']] ?? null;
      $widget['closable'] = $widget['closable'] ?? false;
      $widget['observe'] = $widget['observe'] ?? false;
      $widget['limit'] = $widget['limit'] ?? 5;
      $widget['buttonsRight'] = $widget['buttonsRight'] ?? [];
      $widget['buttonsLeft'] = $widget['buttonsLeft'] ?? [];
      $widget['options'] = $widget['options'] ?? new \stdClass();
      return $this->opt->add($widget);
    }
    return null;
  }

  /**
   * Saves the widget configuration
   * @param array $data
   * @return string|int
   */
  public function save(array $data)
  {
    if (!empty($data[$this->arch_pref['id']])) {
      // Current option's id or preference's id
      $id = $data[$this->arch_pref['id']];
      // Normal widget
      if ($cfg = $this->pref->getByOption($id)) {
        return $this->pref->setByOption($id, \bbn\X::mergeArrays($cfg, $data[$this->arch_pref['cfg']]));
      }
      // User's personal widget
      elseif ($old = $this->pref->get($id, false)) {
        if (($cfg = json_decode($old[$this->arch_pref['cfg']], true)) && !empty($cfg['widget'])) {
          $cfg['widget'] = \bbn\X::mergeArrays($cfg['widget'], $data[$this->arch_pref['cfg']]);  
          return $this->pref->setCfg($id, $cfg);  
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
    if (!empty($order)) {
      $changed = 0;
      foreach ($order as $i => $k){
        // Normal widget
        if ($opt = $this->opt->option($k)) {
          // Existing preference
          if ($pref = $this->pref->getByOption($k)) {
            if ($pref[$this->arch_pref['num']] !== ($i + 1)) {
              $pref[$this->arch_pref['num']] = $i + 1;
              $this->pref->update($pref[$this->arch_pref['id']], $pref);
              $changed++;
            }
          }
          // Add new preference
          elseif ($opt[$this->arch_opt['num']] !== ($i +1)) {
            $this->pref->add($k, [$this->arch_pref['num'] => $i + 1]);
            $changed++;
          }
        }
        // User's personal widget
        elseif (($pref = $this->pref->get($k)) 
            && ($pref[$this->arch_pref['num']] !== ($i + 1))
        ) {
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
  public function getWidgets(string $url = ''): array
  {
    $widgets = $this->_get_widgets($url);
    return [
      'widgets' => $widgets,
      'order' => $this->getOrder($widgets)
    ];
  }

  /**
   * Gets an orderder array of the widgets' keys
   * @param array $widgets The list of widgets
   * @return array
   */
  public function getOrder(array $widgets): array
  {
    $ret = [];
    $toend = [];
    if (bbn\X::isAssoc($widgets)) {
      $widgets = \array_values($widgets);
    }
    foreach ($widgets as $widget){
      if (bbn\Str::isInteger($widget[$this->arch_opt['num']]) 
          && !\array_key_exists($widget[$this->arch_opt['num']], $ret) 
      ) {
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
  public function getWidgetsCode(string $url = ''): array
  {
    $widgets = $this->_get_widgets($url, true);
    $ret = [];
    foreach ($widgets as $w){
      $ret[$w[$this->arch_opt['code']]] = $w;
    }
    \ksort($ret);
    return $ret;
  }
}
