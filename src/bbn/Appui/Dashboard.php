<?php
/**
 * @package appui
 */
namespace bbn\Appui;

use bbn;
use bbn\X;
use bbn\Str;
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

  /** @var \bbn\Db */
  protected $db;

  /** @var array */
  protected $archOpt;

  /** @var array */
  protected $archPref;

  /** @var array */
  protected $cfgPref;

  /** @var array */
  protected $archBits;

  /** @var string */
  protected $id;

  /** @var string */
  protected $code;

  /** @var string */
  protected $idList;


  /**
   * dashboard constructor.
   */
  public function __construct(string $id = '')
  {
    $this->opt      = bbn\Appui\Option::getInstance();
    $this->user     = bbn\User::getInstance();
    $this->perm     = bbn\User\Permissions::getInstance();
    $this->pref     = bbn\User\Preferences::getInstance();
    $this->db       = bbn\Db::getInstance();
    $this->cfgPref  = $this->pref->getClassCfg();
    $this->archOpt  = $this->opt->getClassCfg()['arch']['options'];
    $this->archPref = $this->cfgPref['arch']['user_options'];
    $this->archBits = $this->cfgPref['arch']['user_options_bits'];
    self::optionalInit();
    $this->idList = $this->getOptionId('list');
    if (!Str::isUid($this->idList)) {
      throw new \Exception(_("Unable to load the option 'list'"));
    }
    if (func_num_args()) {
      $this->id = $this->getId($id);
      if (!Str::isUid($this->id)) {
        throw new \Exception(_("Unable to load the dashboard using identifier: %s", $id));
      }
      if (!($this->code = $this->getCode($id))) {
        throw new \Exception(_("Unable to load the dashboard code using identifier: %s", $id));
      }
    }
  }

  /**
   * Creates a new dashboard
   * @param array $d the dashboard fields
   * @return string|null
   */
  public function insert(array $d): ?string
  {
    if (empty($d['code'])) {
      throw new \Exception(_("The dashboard's code is mandatory"));
    }
    if (empty($d[$this->archPref['text']])) {
      throw new \Exception(_("The dashboard's text is mandatory"));
    }
    if ($this->db->insert(
      $this->cfgPref['table'], [
      $this->archPref['id_option'] => $this->idList,
      $this->archPref['num'] => $d[$this->archPref['num']] ?? null,
      $this->archPref['text'] => $d[$this->archPref['text']],
      $this->archPref['id_link'] => !empty($d[$this->archPref['id_link']]) ? $d[$this->archPref['id_link']] : null,
      $this->archPref['id_alias'] => !empty($d[$this->archPref['id_alias']]) ? $d[$this->archPref['id_alias']] : null,
      $this->archPref['id_user'] => !empty($d[$this->archPref['id_user']]) ? $d[$this->archPref['id_user']] : null,
      $this->archPref['id_group'] => !empty($d[$this->archPref['id_group']]) ? $d[$this->archPref['id_group']] : null,
      $this->archPref['public'] => $d[$this->archPref['public']] ?? 0,
      $this->archPref['cfg'] => ($cfg = $this->pref->getCfg(false, $d)) ? json_encode($cfg) : null
    ])) {
      return $this->db->lastId();
    }
    return null;
  }

  /**
   * Updates the current dashboard
   * @param array $d the dashboard fields
   * @return bool
   */
  public function update(array $d): bool
  {
    if (!Str::isUid($this->id)) {
      throw new \Exception(_("The dashboard's ID is mandatory"));
    }
    if (empty($d['code'])) {
      throw new \Exception(_("The dashboard's code is mandatory"));
    }
    if (empty($d[$this->archPref['text']])) {
      throw new \Exception(_("The dashboard's text is mandatory"));
    }
    $t =& $this;
    $data = \array_filter($d, function($f) use($t){
      return \in_array($f, \array_values($t->archPref), true);
    }, ARRAY_FILTER_USE_KEY);
    $data[$this->archPref['cfg']] = ($cfg = $this->pref->getCfg(false, $d)) ? json_encode($cfg) : null;
    unset($data[$this->archPref['id']]);
    return (bool)$this->db->update($this->cfgPref['table'], $data, [$this->archPref['id'] => $this->id]);
  }

  public function delete(): bool
  {
    if ($this->_check()){
      return (bool)$this->pref->delete($this->id);
    }
    return false;
  }


  public function add(array $widget): ?string
  {
    if (!!$this->id
      && !empty($widget[$this->archOpt['text']])
      && !empty($widget[$this->archOpt['code']])
      && (!empty($widget['component']) || !empty($widget['itemComponent']))
    ) {
      $widget[$this->archOpt['id_parent']] = $this->id;
      $widget[$this->archOpt['id_alias']]  = $widget[$this->archOpt['id_alias']] ?? null;
      $widget['closable']                  = $widget['closable'] ?? false;
      $widget['observe']                   = $widget['observe'] ?? false;
      $widget['limit']                     = $widget['limit'] ?? 5;
      $widget['buttonsRight']              = $widget['buttonsRight'] ?? [];
      $widget['buttonsLeft']               = $widget['buttonsLeft'] ?? [];
      $widget['options']                   = $widget['options'] ?? new \stdClass();
      return $this->opt->add($widget);
    }

    return null;
  }


  /**
   * Saves the widget configuration
   * @param array $data
   * @return string|bool
   */
  public function save(array $data)
  {
    if (!empty($data[$this->archBits['id']])
        && !empty($data[$this->archBits['cfg']])
    ) {
      $idWidget = $data[$this->archBits['id']];
      $cfg      = Str::isJson($data[$this->archBits['cfg']]) ? json_decode(Str::isJson($data[$this->archBits['cfg']]), true) : (is_array($data[$this->archBits['cfg']]) ? $data[$this->archBits['cfg']] : []);
      if ($dash = $this->getDashboardByWidget($idWidget)) {
        $idDash = $dash[$this->archPref['id']];
        if ($uDash = $this->getUserDashboard($idDash)) {
          $uCfg = Str::isJson($uDash[$this->archPref['cfg']]) ? json_decode($uDash[$this->archPref['cfg']], true) : [];
          if (!isset($uCfg['widgets'])) {
            $uCfg['widgets'] = [];
          }

          $uCfg['widgets'][$idWidget] = X::mergeArrays($uCfg['widgets'][$idWidget] ?? [], $cfg);
          return (bool)$this->pref->setCfg($uDash[$this->archPref['id']], $uCfg);
        }
        elseif ($this->pref->shareWithUser($idDash, $this->user->getId())) {
          $idUsrDash = $this->db->lastId();
          if ($this->pref->setCfg(
            $idUsrDash, [
            'widgets' => [
              $idWidget => $cfg
            ]
            ]
          )
          ) {
            return $idUsrDash;
          }
        }
      }
    }

    return false;
  }


  /**
   * Sorts the widgets' order
   * @param array $order The ordered keys list
   * @return int|null
   */
  public function sort(array $order): ?int
  {
    if (!empty($order) && !!$this->id) {
      $changed = 0;
      if ($uDash = $this->getUserDashboard($this->id)) {
        $uCfg = Str::isJson($uDash[$this->archPref['cfg']]) ? json_decode($uDash[$this->archPref['cfg']], true) : [];
        if (!isset($uCfg['widgets'])) {
          $uCfg['widgets'] = [];
        }

        foreach ($order as $i => $k){
          if (!isset($uCfg['widgets'][$k])) {
            $uCfg['widgets'][$k] = [];
          }

          if (!isset($uCfg['widgets'][$k][$this->archBits['num']])) {
            $uCfg['widgets'][$k][$this->archBits['num']] = 0;
          }

          if ($uCfg['widgets'][$k][$this->archBits['num']] !== $i + 1) {
            $uCfg['widgets'][$k][$this->archBits['num']] = $i + 1;
            $changed++;
          }
        }

        if ($changed && $this->pref->setCfg($uDash[$this->archPref['id']], $uCfg)) {
          return $changed;
        }
      }
      else {
        $cfg = ['widgets' => []];
        foreach ($order as $i => $k){
          $cfg['widgets'][$k] = [$this->archBits['num'] => $i + 1];
        }

        if ($this->pref->shareWithUser($this->id, $this->user->getId())) {
          $idUsrDash = $this->db->lastId();
          if ($this->pref->setCfg($idUsrDash, $cfg)) {
            return count($cfg['widgets']);
          }
        }
      }

      return $changed;
    }

    return null;
  }

  public function get(){
    return !!$this->id ? $this->pref->get($this->id) : null;
  }

  /**
   * Gets the widgets list of a dashboard and the order list.
   * @param string $url The url to set to the widget's property.
   * @return array
   */
  public function getWidgets(string $url = ''): array
  {
    $widgets = $this->_getWidgets($url);
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
    $ret   = [];
    $toend = [];
    if (bbn\X::isAssoc($widgets)) {
      $widgets = \array_values($widgets);
    }

    foreach ($widgets as $widget){
      if (Str::isInteger($widget[$this->archOpt['num']])
          && !\array_key_exists($widget[$this->archOpt['num']], $ret)
      ) {
        $ret[$widget[$this->archOpt['num']]] = $widget['key'];
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
    $widgets = $this->_getWidgets($url, true);
    $ret     = [];
    foreach ($widgets as $w){
      $ret[$w[$this->archOpt['code']]] = $w;
    }

    \ksort($ret);
    return $ret;
  }


  /**
   * Gets the dashboard by a widget ID (bit)
   * @param string $id The widget ID (bit)
   * @return array|null
   */
  public function getDashboardByWidget(string $id): ?array
  {
    if (Str::isUid($id)) {
      return $this->pref->getByBit($id);
    }

    return null;
  }


  /**
   * Gets the widget option
   * @param string $id The widget ID (bit)
   * @param bool false if you want only the option ID
   * @return array|null|string
   */
  public function getWidgetOption(string $id, bool $full = false)
  {
    if (Str::isUid($id)
        && ($bit = $this->pref->getBit($id))
        && !empty($bit[$this->archBits['id_option']])
    ) {
      return $full ? $this->opt->option($bit[$this->archBits['id_option']]) : $bit[$this->archBits['id_option']];
    }

    return null;
  }

  /**
   * Gets the user's customized dashborad of the given id
   * @param string $id The dashboard id
   * @return array|null
   */
  public function getUserDashboard(string $id): ?array
  {
    return $this->db->rselect(
      $this->cfgPref['tables']['user_options'], [], [
      $this->archPref['id_alias'] => $id,
      $this->archPref['id_user'] => $this->user->getId(),
      $this->archPref['public'] => 0
      ]
    );
  }

  /**
   * Returns the dashboard id by its code
   * @param string $code The dashboard code
   * @return string
   */
  public function getId(string $code): string
  {
    if (empty($code)) {
      throw new \Exception(_('A wrong argument value is passed'));
    }
    if (!Str::isUid($code)) {
      return $this->db->selectOne([
        'table' => $this->cfgPref['tables']['user_options'],
        'fields' => [$this->archPref['id']],
        'where' => [
          'conditions' => [[
            'field' => $this->archPref['id_option'],
            'value' => $this->idList
          ], [
            'field' => $this->archPref['cfg'] . '->>"$.code"',
            'value' => $code
          ], [
            'logic' => 'OR',
            'conditions' => [[
              'field' => $this->archPref['id_user'],
              'value' => $this->user->getId()
            ], [
              'field' => $this->archPref['id_group'],
              'value' => $this->user->getGroup()
            ], [
              'field' => $this->archPref['public'],
              'value' => 1
            ]]
          ]]
        ]]
      );
    }
    return $code;
  }

  /**
   * Returns the dashboard code
   * @param string $code The dashboard code
   * @return string
   */
  public function getCode(string $id): ?string
  {
    if (empty($id)) {
      throw new \Exception(_('A wrong argument value is passed'));
    }
    if (Str::isUid($id)) {
      return $this->db->selectOne(
        $this->cfgPref['tables']['user_options'],
        $this->archPref['cfg'] . '->>"$.code"',
        [$this->archPref['id'] => $id]
      );
    }
    return $id;
  }

  public function getNativeWidgets(string $url = ''){
    /** @var array The final result */
    $res = [];
    /** @var array The user's own preferences */
    $widgetPrefs = [];
    if (!!$this->id) {
      // Looking for the widgets
      if ($widgets = $this->pref->getBits($this->id, false)) {
        foreach ($widgets as $w) {
          // Getting the option
          if (!empty($w[$this->archBits['id_option']])
            && ($o = $this->opt->option($w[$this->archBits['id_option']]))
          ) {
            // Set "text" property coming from the bit
            $o[$this->archOpt['text']] = $w[$this->archBits['text']];
            // Set "num" property coming from the bit
            $o[$this->archOpt['num']] = $w[$this->archBits['num']];
            // Set "id_option" property coming from the option
            $o[$this->archBits['id_option']] = $o[$this->archOpt['id']];
            // Set "id" property coming from the bit
            $o[$this->archBits['id']] = $w[$this->archBits['id']];
            // Set "cfg" properties coming from the bit
            if ($cfg = $this->pref->getBitCfg($w[$this->archBits['id']])) {
              $o = X::mergeArrays($o, $cfg);
            }
            // Set the widget's url
            if (!empty($o[$this->archOpt['code']])) {
              $o['url'] = $url.$o[$this->archOpt['code']];
            }
            unset(
              $o[$this->archOpt['id_alias']],
              $o['num_children'],
              $o[$this->archOpt['id_parent']]
            );
            $res[] = $o;
          }
        }
      }
    }
    X::sort_by($res, $this->archOpt['num'], 'asc');
    return $res;
  }

  private function _check(){
    if (!Str::isUid($this->id)) {
      throw new \Exception(_("The dashboard's ID is mandatory"));
    }
    return true;
  }


  /**
   * Gets the widgets list of a dashboard.
   *
   * @param string $url       The url to set to the widget's property.
   * @param bool   $with_code If true the code property is also returned.
   * @return array
   */
  private function _getWidgets(string $url = '', bool $with_code = false): array
  {
    /** @var array The final result */
    $res = [];
    /** @var array The user's own preferences */
    $widgetPrefs = [];
    if (!!$this->id) {
      // Looking for some preferences if he has some
      if (($uDash = $this->getUserDashboard($this->id))
          && !empty($uDash[$this->archPref['cfg']])
          && Str::isJson($uDash[$this->archPref['cfg']])
          && ($uDashCfg = json_decode($uDash[$this->archPref['cfg']], true))
          && !empty($uDashCfg['widgets'])
      ) {
        $widgetPrefs = $uDashCfg['widgets'];
      }
      // Looking for the widgets
      if ($widgets = $this->pref->getBits($this->id, false)) {
        foreach ($widgets as $w) {
          // Getting the option
          if (!empty($w[$this->archBits['id_option']])
              && ($o = $this->opt->option($w[$this->archBits['id_option']]))
          ) {
            // Checking the permission
            if (($id_perm = $this->perm->optionToPermission($o[$this->archOpt['id']]))
                && $this->perm->has($id_perm)
            ) {
              // Set "text" property coming from the bit
              $o[$this->archOpt['text']] = $w[$this->archBits['text']];
              // Set "num" property coming from the bit
              $o[$this->archOpt['num']] = $w[$this->archBits['num']];
              // Set "cfg" properties coming from the bit
              if ($cfg = $this->pref->getBitCfg($w[$this->archBits['id']])) {
                $o = X::mergeArrays($o, $cfg);
              }
              // Set the widget's key
              $o['key'] = $w[$this->archBits['id']];
              // Set the widget's url
              if (!empty($o[$this->archOpt['code']])) {
                $o['url'] = $url.$o[$this->archOpt['code']];
              }
              // Get the preferences of the single widget
              if (!empty($widgetPrefs[$o['key']])) {
                $o = X::mergeArrays($o, $widgetPrefs[$o['key']]);
              }
              if (!isset($o['hidden'])) {
                $o['hidden'] = false;
              }
              unset(
                $o[$this->archOpt['id_alias']],
                $o['num_children'],
                $o[$this->archOpt['id']],
                $o[$this->archOpt['id_parent']]
              );
              if (!$with_code) {
                unset($o[$this->archOpt['code']]);
              }
  
              $res[] = $o;
            }
          }
        }
      }
    }
    return $res;
  }


  /**
   * Filters the widgets by the user's permissions.
   * The widget should come as a dashboard's item linked to a widget option.
   *
   * @param array $widgets The list of widgets
   * @return array
   */
  private function _filterByPermissions(array $widgets): array
  {
    $oa   =& $this->archOpt;
    $perm =& $this->perm;
    // Filter the widgets by user's permissions
    return \array_values(
      \array_filter(
        $widgets,
        function ($w) use (&$oa, &$perm) {
          // The alias is the widget itself on which the permission should be set
          return !empty($w[$oa['id_alias']]) &&
              $perm->has($w[$oa['id_alias']]);
        }
      )
    );
  }


  /**
   * Get the personal user's widgets from the preferences.
   *
   * @return array
   */
  private function _getWidgetPref(): array
  {
    if (!!$this->id) {
      $t = $this;
      // Get the personal user's widgets
      if (!$prefs = $this->pref->getAll($this->id)) {
        $prefs = [];
      }
      // Fix the widget structure
      return \array_map(
        function ($p) use ($t) {
          return \array_merge(
            [
            $t->archPref['id'] => $p[$t->archPref['id']],
            $t->archPref['id_option'] => $p[$t->archPref['id_option']],
            $t->archPref['text'] => $p[$t->archPref['text']],
            $t->archPref['num'] => $p[$t->archPref['num']],
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
    return [];
  }

}
