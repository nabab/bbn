<?php

/**
 * @package appui
 */

namespace bbn\Appui;

use Exception;
use bbn;
use bbn\X;
use bbn\Str;
use bbn\Db;
use bbn\Mvc;
use bbn\User;
use bbn\User\Permissions;
use bbn\User\Preferences;
use bbn\Appui\Option;
use bbn\Models\Cls\Basic;

class Dashboard extends Basic
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

  /** @var string */
  protected $idWidgets;

  /** @var array */
  protected $nativeWidgetFields = [
    'component',
    'itemComponent',
    'icon',
    'closable',
    'observe',
    'limit',
    'buttonsRight',
    'buttonsLeft',
    'options',
    'cache'
  ];

  /** @var array */
  protected $widgetFields = [];


  /**
   * dashboard constructor.
   */
  public function __construct(string $id = '')
  {
    $this->opt      = Option::getInstance();
    $this->user     = User::getInstance();
    $this->perm     = Permissions::getInstance();
    $this->pref     = Preferences::getInstance();
    $this->db       = Db::getInstance();
    $this->cfgPref  = $this->pref->getClassCfg();
    $this->archOpt  = $this->opt->getClassCfg()['arch']['options'];
    $this->archPref = $this->cfgPref['arch']['user_options'];
    $this->archBits = $this->cfgPref['arch']['user_options_bits'];
    self::optionalInit();
    $this->widgetFields       = \array_merge($this->nativeWidgetFields, \array_values($this->archBits));
    $this->nativeWidgetFields = \array_merge($this->nativeWidgetFields, \array_values($this->archOpt));
    $this->idList             = $this->getOptionId('list');
    if (!Str::isUid($this->idList)) {
      throw new Exception(_("Unable to load the option 'list'"));
    }

    $this->idWidgets = $this->getOptionId('widgets');
    if (!Str::isUid($this->idWidgets)) {
      throw new Exception(_("Unable to load the option 'widgets'"));
    }

    if (!empty($id)) {
      $this->setCurrent($id);
    }
  }


  /**
   * Returns true if the given code corresoponds to an existing 
   *
   * @param [type] $code
   *
   * @return void
   */
  public function exists(string $code)
  {
    return (bool)$this->getId($code);
  }


  /** 
   * Sets the current dashboard by setting code and id properies.
   */
  public function setCurrent($id): bool
  {
    if (!empty($id)) {
      if (Str::isUid($id)) {
        if (!($this->code = $this->getCode($id))) {
          throw new Exception(sprintf(_("Unable to load the dashboard code using identifier: %s"), $id));
        }

        $this->id = $id;
      } else {
        if (!($this->id = $this->getId($id))) {
          throw new Exception(sprintf(_("Unable to load the dashboard using identifier: %s"), $id));
        }

        $this->code = $id;
      }

      return true;
    }

    return false;
  }

  public function getCurrent()
  {
    return $this->id;
  }


  /**
   * Creates a new dashboard
   * @param array $d the dashboard fields
   * @return string|null
   */
  public function insert(array $d): ?string
  {
    if (empty($d['code'])) {
      throw new Exception(_("The dashboard's code is mandatory"));
    }

    if (empty($d[$this->archPref['text']])) {
      throw new Exception(_("The dashboard's text is mandatory"));
    }

    if ($this->db->insert(
      $this->cfgPref['table'],
      [
        $this->archPref['id_option'] => $this->idList,
        $this->archPref['num'] => $d[$this->archPref['num']] ?? null,
        $this->archPref['text'] => $d[$this->archPref['text']],
        $this->archPref['id_link'] => !empty($d[$this->archPref['id_link']]) ? $d[$this->archPref['id_link']] : null,
        $this->archPref['id_alias'] => !empty($d[$this->archPref['id_alias']]) ? $d[$this->archPref['id_alias']] : null,
        $this->archPref['id_user'] => !empty($d[$this->archPref['id_user']]) ? $d[$this->archPref['id_user']] : null,
        $this->archPref['id_group'] => !empty($d[$this->archPref['id_group']]) ? $d[$this->archPref['id_group']] : null,
        $this->archPref['public'] => empty($d[$this->archPref['public']]) ? 0 : 1,
        $this->archPref['cfg'] => ($cfg = $this->pref->getCfg(false, $d)) ? json_encode($cfg) : null
      ]
    )) {
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
    if ($this->_check()) {
      if (empty($d['code'])) {
        throw new Exception(_("The dashboard's code is mandatory"));
      }

      if (empty($d[$this->archPref['text']])) {
        throw new Exception(_("The dashboard's text is mandatory"));
      }

      $t                            = &$this;
      $data                         = \array_filter(
        $d,
        function ($f) use ($t) {
          return \in_array($f, \array_values($t->archPref), true);
        },
        ARRAY_FILTER_USE_KEY
      );
      $data[$this->archPref['cfg']] = ($cfg = $this->pref->getCfg(false, $d)) ? json_encode($cfg) : null;
      unset($data[$this->archPref['id']]);
      return (bool)$this->db->update($this->cfgPref['table'], $data, [$this->archPref['id'] => $this->id]);
    }
  }


  /**
   * Deletes the current dashboard
   * @return bool
   */
  public function delete(): bool
  {
    if ($this->_check()) {
      return (bool)$this->pref->delete($this->id);
    }

    return false;
  }


  /**
   * Adds a widget to the current dashboard
   * @param string $id The native widget's ID
   * @return string|null
   */
  public function addWidget(string $id): ?string
  {
    if ($this->_check()) {
      if (!Str::isUid($id)) {
        throw new Exception(_("The id must be a uuid"));
      }

      if (!($text = $this->opt->text($id))) {
        throw new Exception(sprintf(_("No text for the widget with id %s"), $id));
      }

      return $this->pref->addBit(
        $this->id,
        [
          $this->archBits['id_option'] => $id,
          $this->archBits['text'] => $text,
          $this->archBits['num'] => $this->pref->getMaxBitNum($this->id, null, true) ?: 1
        ]
      );
    }

    return null;
  }


  /**
   * Updates a widget (Bit)
   * @param string $id     The widget's ID (bit)
   * @param array  $widget The widget data
   * @return bool
   */
  public function updateWidget(string $id, array $widget): bool
  {
    if (!Str::isUid($id)) {
      throw new Exception(_("The id must be a uuid"));
    }

    if (!($opt = $this->getWidgetOption($id, true))) {
      throw new Exception(sprintf(_("No option found with this id %s"), $id));
    }

    $d1                              = $this->pref->getBitCfg(null, $this->_prepareWidget($widget));
    $d2                              = $this->pref->getBitCfg(null, $this->_prepareWidget($opt));
    $toSave                          = \array_filter(
      $d1,
      function ($v, $k) use ($d2) {
        return $d2[$k] != $v;
      },
      ARRAY_FILTER_USE_BOTH
    );
    $toSave[$this->archBits['text']] = $widget[$this->archBits['text']];
    return (bool)$this->pref->updateBit($id, $toSave);
  }


  /**
   * Removes a widget (Bit)
   * @param string $id The widget's ID (bit)
   * @return bool
   */
  public function deleteWidget(string $id): bool
  {
    if (!Str::isUid($id)) {
      throw new Exception(_("The id must be a uuid"));
    }

    $res = false;
    if ($this->_check() && $this->pref->deleteBit($id)) {
      $res = true;
      if ($alias = $this->db->selectAll($this->cfgPref['table'], [], [$this->archPref['id_alias'] => $this->id])) {
        foreach ($alias as $a) {
          if (($cfg = \json_decode($a->{$this->archPref['cfg']}, true))
            && isset($cfg['widget'], $cfg['widget'][$id])
          ) {
            unset($cfg['widget'][$id]);
            if (!$this->pref->setCfg($a->${$this->archPref['id']}, $cfg)) {
              $res = false;
            }
          }
        }
      }
    }

    return $res;
  }


  /**
   * Set the widget's order number property
   * @param string $id  The widget's ID (bit)
   * @param int    $num The new order number
   * @return bool
   */
  public function setOrderWidget(string $id, int $num): bool
  {
    if (!Str::isUid($id)) {
      throw new Exception(_("The id must be a uuid"));
    }

    if (!($bit = $this->pref->getBit($id, false))) {
      throw new Exception(sprintf(_("No widget found witheì the id %s"), $id));
    }

    if ((int)$bit[$this->archBits['num']] === $num) {
      return true;
    }

    return (bool)$this->db->update(
      $this->cfgPref['tables']['user_options_bits'],
      [
        $this->archBits['num'] => $num
      ],
      [
        $this->archBits['id'] => $id
      ]
    );
  }


  /**
   * Adds a native widget
   * @param array $widget
   * @return string!null
   */
  public function addNativeWidget(array $widget): ?string
  {
    if (($id = $this->opt->add($this->_prepareNativeWidget($widget)))) {
      $this->perm->createFromId($id);
      return $id;
    }

    return null;
  }


  /**
   * Updates a native widget
   * @param string $id     The native widget's ID
   * @param array  $widget The native widget's data
   * @return bool
   */
  public function updateNativeWidget(string $id, array $widget): bool
  {
    if (!Str::isUid($id)) {
      throw new Exception(_("The id must be a uuid"));
    }

    return (bool)$this->opt->set($id, $this->_prepareNativeWidget($widget));
  }


  /**
   * Deletes a native widget
   * @param string $id The widget's ID
   * @return bool
   */
  public function deleteNativeWidget(string $id): bool
  {
    if (!Str::isUid($id)) {
      throw new Exception(_("The id must be a uuid"));
    }

    $idPerm = $this->perm->optionToPermission($id);
    if ($this->opt->remove($id)) {
      if ($idPerm) {
        $this->opt->remove($idPerm);
      }

      return true;
    }

    return false;
  }


  /**
   * Moves a native widget
   * @param string $id The widget's ID
   * @param string $idParent The new widget's parent
   * @return bool
   */
  public function moveNativeWidget(string $id, string $idParent): bool
  {
    if (!Str::isUid($id)) {
      throw new Exception(_("The id must be a uuid"));
    }
    if (!Str::isUid($idParent)) {
      throw new Exception(_("The parent id must be a uuid"));
    }
    return (bool)$this->opt->move($id, $idParent);
  }


  /**
   * Checks if the given id is an user's private widget
   * @param string $id
   * @return bool
   */
  public function isPvtWidget(string $id): bool
  {
    if (!Str::isUid($id)) {
      throw new Exception(_("The id must be a uuid"));
    }
    return ($bit = $this->pref->getBit($id)) && \is_null($bit[$this->archBits['id_option']]);
  }


  /**
   * Adds an user's private widget
   * @param array $widget
   * @param string $idDashboard
   * @return null|string
   */
  public function addPvtWidget(array $widget, string $idDashboard = ''): ?string
  {
    if (empty($idDashboard) && empty($this->id)) {
      throw new Exception(_('The dashboard ID is mandatory'));
    }
    if (empty($widget[$this->archBits['text']])) {
      throw new Exception(_('The widget name is mandatory'));
    }
    $idDashboard = $idDashboard ?: $this->id;
    $userDash = $this->getUserDashboard($idDashboard);
    if (empty($userDash)) {
      if ($this->pref->shareWithUser($idDashboard, $this->user->getId())) {
        $userDash = $this->getUserDashboard($idDashboard);
      }
    }
    if (!empty($userDash)) {
      $widget[$this->archBits['num']] = $this->pref->getMaxBitNum($idDashboard, null, true) ?: 1;
      if ($this->pref->addBit($userDash['id'], $widget)) {
        return $this->db->lastId();
      }
    }
    return null;
  }


  /**
   * Gets an user's private widget
   * @param string $id
   * @return null|array
   */
  public function getPvtWidget(string $id): ?array
  {
    if ($this->isPvtWidget($id)
      && ($w = $this->pref->getBit($id))
    ) {
      $o = !empty($w['widget']) ? $w['widget'] : [];
      $o = X::mergeArrays($o, [
        $this->archBits['id'] => $w[$this->archBits['id']],
        'key' => $w[$this->archBits['id']],
        $this->archBits['text'] => $w[$this->archBits['text']],
        $this->archBits['num'] => $w[$this->archBits['num']]
      ]);
      // Parse JSON properties
      if (!empty($o['buttonsRight']) && Str::isJson($o['buttonsRight'])) {
        $o['buttonsRight'] = \json_decode($o['buttonsRight'], true);
      }
      if (!empty($o['buttonsLeft']) && Str::isJson($o['buttonsLeft'])) {
        $o['buttonsLeft'] = \json_decode($o['buttonsLeft'], true);
      }
      if (!empty($o['options']) && Str::isJson($o['options'])) {
        $o['options'] = \json_decode($o['options'], true);
      }
      // "hidden" property
      if (!isset($o['hidden'])) {
        $o['hidden'] = false;
      }
      return $o;
    }
    return null;
  }


  /**
   * Deletes an user's private widget
   * @param string $id
   * @return bool
   */
  public function deletePvtWidget(string $id): bool
  {
    return $this->isPvtWidget($id) && $this->pref->deleteBit($id);
  }


  /**
   * Saves the widget configuration
   * @param array $data
   * @return string|bool
   */
  public function save(array $data)
  {
    if (
      !empty($data[$this->archBits['id']])
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
        } elseif ($this->pref->shareWithUser($idDash, $this->user->getId())) {
          $idUsrDash = $this->db->lastId();
          if ($this->pref->setCfg(
            $idUsrDash,
            [
              'widgets' => [
                $idWidget => $cfg
              ]
            ]
          )) {
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
    if (!empty($order) && (bool)$this->id) {
      $changed = 0;
      if ($uDash = $this->getUserDashboard($this->id)) {
        $uCfg = Str::isJson($uDash[$this->archPref['cfg']]) ? json_decode($uDash[$this->archPref['cfg']], true) : [];
        if (!isset($uCfg['widgets'])) {
          $uCfg['widgets'] = [];
        }

        foreach ($order as $i => $k) {
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
      } else {
        $cfg = ['widgets' => []];
        foreach ($order as $i => $k) {
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


  public function get()
  {
    return (bool)$this->id ? $this->pref->get($this->id) : null;
  }


  /**
   * Gets the widgets list of a dashboard and the order list.
   * @param string $url The url to set to the widget's property.
   * @return array
   */
  public function getUserWidgets(string $url = ''): array
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

    foreach ($widgets as $widget) {
      if (
        Str::isInteger($widget[$this->archOpt['num']])
        && !\array_key_exists($widget[$this->archOpt['num']], $ret)
      ) {
        $ret[$widget[$this->archOpt['num']]] = $widget['key'];
      } else {
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
  public function getUserWidgetsCode(string $url = ''): array
  {
    $widgets = $this->_getWidgets($url, true);
    $ret     = [];
    foreach ($widgets as $w) {
      $ret[$w[$this->archOpt['code']]] = $w;
    }

    \ksort($ret);
    return $ret;
  }


  public function getUserDashboards(): ?array
  {
    if ($id_list = $this->getOptionId('list')) {
      return $this->pref->getAll($id_list);
    }

    return null;
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
   * @param string                                    $id The widget ID (bit)
   * @param bool false if you want only the option ID
   * @return array|null|string
   */
  public function getWidgetOption(string $id, bool $full = false)
  {
    if (
      Str::isUid($id)
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
      $this->cfgPref['tables']['user_options'],
      [],
      [
        $this->archPref['id_alias'] => $id,
        $this->archPref['id_user'] => $this->user->getId(),
        $this->archPref['public'] => 0
      ]
    );
  }

  /**
   * Gets the user's default dashboard
   * @return string|null
   */
  public function getDefault(): ?string
  {
    if (($id_opt = $this->getOptionId('default'))
      && ($all = $this->pref->getAll($id_opt))
    ) {
      if ($by_id_user = \array_filter(
        $all,
        function ($a) {
          return !empty($a['id_user']) && !empty($a['id_alias']);
        }
      )) {
        return $by_id_user[0]['id_alias'];
      } elseif ($by_id_group = \array_filter(
        $all,
        function ($a) {
          return !empty($a['id_group']) && !empty($a['id_alias']);
        }
      )) {
        return $by_id_group[0]['id_alias'];
      } elseif ($by_public = \array_filter(
        $all,
        function ($a) {
          return !empty($a['public']) && !empty($a['id_alias']);
        }
      )) {
        return $by_public[0]['id_alias'];
      }
    }
    return null;
  }


  /**
   * Returns the dashboard id by its code
   * @param string $code The dashboard code
   * @return string
   */
  public function getId(string $code): string
  {
    if (empty($code)) {
      throw new Exception(_('A wrong argument value is passed'));
    }

    if (!Str::isUid($code)) {
      return $this->db->selectOne(
        [
          'table' => $this->cfgPref['tables']['user_options'],
          'fields' => [$this->archPref['id']],
          'where' => [
            'conditions' => [[
              'field' => $this->archPref['id_option'],
              'value' => $this->idList
            ], [
              'field' => 'JSON_UNQUOTE(JSON_EXTRACT(cfg, \'$.code\'))',
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
          ]
        ]
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
      throw new Exception(_('A wrong argument value is passed'));
    }

    if ($pref = $this->pref->get($id)) {
      return $pref['code'] ?? null;
    }

    return null;
  }


  /**
   * Returns the dashboard's widgets
   * @param string $url
   * @return array
   */
  public function getWidgets(string $url = ''): array
  {
    /** @var array The final result */
    $res = [];
    if ($this->_check()) {
      // Looking for the widgets
      if ($widgets = $this->pref->getBits($this->id, false)) {
        foreach ($widgets as $w) {
          // Getting the option
          if (
            !empty($w[$this->archBits['id_option']])
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
              $o['url'] = $url . $o[$this->archOpt['code']];
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

    X::sortBy($res, $this->archOpt['num'], 'asc');
    return $res;
  }


  /**
   * Checks if the id property is set
   * @return bool
   */
  private function _check()
  {
    if (!Str::isUid($this->id)) {
      throw new Exception(_("The dashboard's ID is mandatory"));
    }

    return true;
  }


  /**
   * Checks the native widget's properties
   * @param array $widget
   * @return array
   */
  private function _prepareNativeWidget(array $widget): array
  {
    if (empty($widget[$this->archOpt['text']])) {
      throw new Exception(sprintf(_("The widget's '%s' property is mandatory"), $this->archOpt['text']));
    }

    if (empty($widget[$this->archOpt['code']])) {
      throw new Exception(_("The widget's 'code' property is mandatory"));
    }

    if ((empty($widget['component']) && empty($widget['itemComponent']))) {
      throw new Exception(_("The widget's 'component' or 'itemComponent' property is mandatory"));
    }

    $widget[$this->archOpt['id_parent']] = empty($widget[$this->archOpt['id_parent']]) ? $this->idWidgets : $widget[$this->archOpt['id_parent']];
    if (empty($widget[$this->archOpt['id_parent']])) {
      throw new Exception(sprintf(_("The widget's '%s' property is mandatory"), $this->archOpt['id_parent']));
    }

    $widget[$this->archOpt['id_alias']] = $widget[$this->archOpt['id_alias']] ?? null;
    $widget['closable']                 = $widget['closable'] ?? false;
    $widget['observe']                  = $widget['observe'] ?? false;
    $widget['limit']                    = $widget['limit'] ?? 5;
    $widget['buttonsRight']             = $widget['buttonsRight'] ?? [];
    $widget['buttonsLeft']              = $widget['buttonsLeft'] ?? [];
    $widget['options']                  = $widget['options'] ?? new \stdClass();
    $widget['cache']                    = $widget['cache'] ?? 0;
    foreach ($widget as $field => $val) {
      if (!\in_array($field, $this->nativeWidgetFields)) {
        unset($widget[$field]);
      }
    }

    return $widget;
  }


  /**
   * Checks the widget's properties
   * @param array $widget
   * @return array
   */
  private function _prepareWidget(array $widget): array
  {
    if (empty($widget[$this->archBits['text']])) {
      throw new Exception(sprintf(_("The widget's '%s' property is mandatory"), $this->archBits['text']));
    }

    foreach ($widget as $field => $val) {
      if (!\in_array($field, $this->widgetFields)) {
        unset($widget[$field]);
      }
    }

    return $widget;
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
    if ($this->id) {
      // Looking for some preferences if he has some
      $uDash = $this->getUserDashboard($this->id);
      if (!empty($uDash)
        && !empty($uDash[$this->archPref['cfg']])
        && Str::isJson($uDash[$this->archPref['cfg']])
        && ($uDashCfg = json_decode($uDash[$this->archPref['cfg']], true))
        && !empty($uDashCfg['widgets'])
      ) {
        $widgetPrefs = $uDashCfg['widgets'];
      }

      // Looking for the widgets
      if ($widgets = $this->pref->getBits($this->id, false)) {
        $mvc = Mvc::getInstance();
        foreach ($widgets as $w) {
          // Getting the option
          if (
            !empty($w[$this->archBits['id_option']])
            && ($o = $this->opt->option($w[$this->archBits['id_option']]))
          ) {
            if ($id_plugin = $this->opt->getParentPlugin($o[$this->archOpt['id']])) {
              $plugin = $this->opt->option($id_plugin);
              $plugin_name = $this->opt->getPluginName($plugin['id']);
              if (($plugin_name !== constant('BBN_APP_NAME')) && !$mvc->hasPlugin($plugin_name)) {
                continue;
              }
            }
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
                $o['url'] = $url . $o[$this->archOpt['code']];
              }

              // Get the preferences of the single widget
              if (!empty($widgetPrefs[$o['key']])) {
                $o = X::mergeArrays($o, $widgetPrefs[$o['key']]);
              }

              // Parse JSON properties
              if (!empty($o['buttonsRight']) && Str::isJson($o['buttonsRight'])) {
                $o['buttonsRight'] = \json_decode($o['buttonsRight'], true);
              }
              if (!empty($o['buttonsLeft']) && Str::isJson($o['buttonsLeft'])) {
                $o['buttonsLeft'] = \json_decode($o['buttonsLeft'], true);
              }
              if (!empty($o['options']) && Str::isJson($o['options'])) {
                $o['options'] = \json_decode($o['options'], true);
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

      //X::ddump($res);
      if (!empty($uDash)
        && ($pvtWidgets = $this->pref->getBits($uDash[$this->archPref['id']], false))
      ) {
        foreach ($pvtWidgets as $w) {
          if (\is_null($w['id_option'])) {
            $o = !empty($w['widget']) ? $w['widget'] : [];
            $o = X::mergeArrays($o, [
              $this->archBits['id'] => $w[$this->archBits['id']],
              'key' => $w[$this->archBits['id']],
              $this->archBits['text'] => $w[$this->archBits['text']],
              $this->archBits['num'] => $w[$this->archBits['num']]
            ]);
            // Set the widget's url
            if (!empty($o['code'])) {
              $o['url'] = $url . $o['code'];
            }
            // Get the preferences of the single widget
            if (!empty($widgetPrefs[$o['key']])) {
              $o = X::mergeArrays($o, $widgetPrefs[$o['key']]);
            }
            // Parse JSON properties
            if (!empty($o['buttonsRight']) && Str::isJson($o['buttonsRight'])) {
              $o['buttonsRight'] = \json_decode($o['buttonsRight'], true);
            }
            if (!empty($o['buttonsLeft']) && Str::isJson($o['buttonsLeft'])) {
              $o['buttonsLeft'] = \json_decode($o['buttonsLeft'], true);
            }
            if (!empty($o['options']) && Str::isJson($o['options'])) {
              $o['options'] = \json_decode($o['options'], true);
            }
            // "hidden" property
            if (!isset($o['hidden'])) {
              $o['hidden'] = false;
            }
            if (!$with_code) {
              unset($o[$this->archOpt['code']]);
            }
            $res[] = $o;
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
    $oa   = &$this->archOpt;
    $perm = &$this->perm;
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
    if ((bool)$this->id) {
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
              'hidden' => isset($p['hidden']) ? (bool)$p['hidden'] : false
            ],
            $p['widget']
          );
        },
        \array_values(
          \array_filter(
            $prefs,
            function ($p) {
              return !empty($p['widget']);
            }
          )
        )
      );
    }

    return [];
  }
}
