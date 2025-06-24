<?php

namespace bbn\Appui\Option\Internal;

use Exception;
use bbn\X;

trait Template
{
  protected $templateIds = [];

  protected $magicTemplateId;

  protected $optionsTemplateId;

  protected $permissionsTemplateId;

  protected $pluginTemplateId;

  protected $subpluginTemplateId;

  protected $subOptionsTemplateId;

  protected $subPermissionsTemplateId;

  protected $templateTemplateId;

  protected $appuiTemplateId;

  protected $pluginsTemplateId;


  public function getTemplateId(...$codes): ?string
  {
    if ($this->check() && count($codes)) {
      $code = array_pop($codes);
      if (!isset($this->templateIds[$code])) {
        $this->templateIds[$code] = $this->fromCode($code, $this->getMagicTemplateId()) ?: null;
      }

      if (!isset($this->templateIds[$code])) {
        foreach ($this->getAliasItems($this->getTemplatesTemplateId()) as $it) {
          if ($tmp = $this->fromCode($code, $it)) {
            $this->templateIds[$code] = $tmp;
            break;
          }
        }
      }

      if (isset($this->templateIds[$code])) {
        if (count($codes)) {
          $codes[] = $this->templateIds[$code];
          return $this->fromCode(...$codes);
        }

        return $this->templateIds[$code];
      }
    }

    return null;
  }


  public function templateList() {
    return [
      ...$this->fullOptionsRef($this->getMagicTemplateId()),
      ...$this->fullOptionsRef($this->getTemplatesTemplateId())
    ];
  }

  public function textValueTemplates(): array
  {
    return array_map(function($a) {
      return [
        'text' => $a['text'],
        'value' => $a['id']
      ];
    }, $this->templateList());
  }


  /**
   * Returns the ID of the root templates
   * @return string
   */
  public function getMagicTemplateId(): ?string
  {
    if (!$this->magicTemplateId && $this->check() && ($root = $this->getRoot())) {
      $cfg = $this->getClassCfg();
      $this->magicTemplateId = $this->db->selectOne($cfg['table'], $cfg['arch']['options']['id'], [
        $cfg['arch']['options']['id_parent'] => $root,
        $cfg['arch']['options']['code'] => 'templates',
      ]);
    }

    return $this->magicTemplateId;
  }


  /**
   * Returns the ID of the 'plugin > permissions' template
   * @return string
   */
  public function getPermissionsTemplateId(): ?string
  {
    if ($this->getMagicTemplateId() && !$this->permissionsTemplateId && $this->check()) {
      $this->permissionsTemplateId = $this->fromCode('permissions', $this->getPluginTemplateId());
    }

    return $this->permissionsTemplateId;
  }


  /**
   * Returns the ID of the 'plugin > options' template
   * @return string
   */
  public function getOptionsTemplateId(): ?string
  {
    if ($this->getMagicTemplateId() && !$this->optionsTemplateId && $this->check()) {
      $cfg = $this->getClassCfg();
      $this->optionsTemplateId = $this->db->selectOne($cfg['table'], $cfg['arch']['options']['id'], [
        $cfg['arch']['options']['id_parent'] => $this->getPluginTemplateId(),
        $cfg['arch']['options']['code'] => 'options',
      ]);
    }

    return $this->optionsTemplateId;
  }


  /**
   * Returns the ID of the 'plugin' template
   * @return string
   */
  public function getPluginTemplateId(): ?string
  {
    if ($this->getMagicTemplateId() && !$this->pluginTemplateId && $this->check()) {
      $cfg = $this->getClassCfg();
      $this->pluginTemplateId = $this->db->selectOne($cfg['table'], $cfg['arch']['options']['id'], [
        $cfg['arch']['options']['id_parent'] => $this->getMagicTemplateId(),
        $cfg['arch']['options']['code'] => 'plugin',
      ]);
    }

    return $this->pluginTemplateId;
  }


  /**
   * Returns the ID of the 'subplugin' template i.e. plugins in plugin
   * @return string
   */
  public function getSubpluginTemplateId(): ?string
  {
    if ($this->getMagicTemplateId() && !$this->subpluginTemplateId && $this->check()) {
      $cfg = $this->getClassCfg();
      $this->subpluginTemplateId = $this->db->selectOne($cfg['table'], $cfg['arch']['options']['id'], [
        $cfg['arch']['options']['id_parent'] => $this->getMagicTemplateId(),
        $cfg['arch']['options']['code'] => 'subplugin',
      ]);
    }

    return $this->subpluginTemplateId;
  }


  /**
   * Returns the ID of the options template in the 'subplugin' template
   * @return string
   */
  public function getSubOptionsTemplateId(): ?string
  {
    if ($this->getMagicTemplateId() && !$this->subpluginTemplateId && $this->check()) {
      $cfg = $this->getClassCfg();
      $this->subOptionsTemplateId = $this->db->selectOne($cfg['table'], $cfg['arch']['options']['id'], [
        $cfg['arch']['options']['id_parent'] => $this->getSubpluginTemplateId(),
        $cfg['arch']['options']['code'] => 'options',
      ]);
    }

    return $this->subOptionsTemplateId;
  }


  /**
   * Returns the ID of the options template in the 'subplugin' template
   * @return string
   */
  public function getSubPermissionsTemplateId(): ?string
  {
    if ($this->getMagicTemplateId() && !$this->subpluginTemplateId && $this->check()) {
      $this->subpluginTemplateId = $this->fromCode('permissions', $this->getSubpluginTemplateId());
    }

    return $this->subPermissionsTemplateId;
  }


  /**
   * Returns the ID of the 'plugin > template' template
   * @return string
   */
  public function getTemplatesTemplateId(): ?string
  {
    if ($this->getMagicTemplateId() && !$this->templateTemplateId && $this->check()) {
      $this->templateTemplateId = $this->fromCode('templates', $this->getPluginTemplateId());
    }

    return $this->templateTemplateId;
  }


  public function getPluginsTemplateId(): ?string
  {
    if ($this->getMagicTemplateId() && !$this->pluginsTemplateId && $this->check()) {
      $this->pluginsTemplateId = $this->fromCode('plugins', $this->getPluginTemplateId());
    }

    return $this->pluginsTemplateId;
  }


  /**
   * Returns the ID of the 'plugin > plugins > appui' template
   * @return string
   */
  public function getAppuiTemplateId(): ?string
  {
    if ($this->getMagicTemplateId() && !$this->appuiTemplateId && $this->check()) {
      $this->appuiTemplateId = $this->fromCode('appui', $this->getPluginsTemplateId());
    }

    return $this->appuiTemplateId;
  }


  public function applyAllTemplates(): ?int
  {
    $tot = 0;
    $tids = $this->items($this->getMagicTemplateId());
    foreach ($tids as $tid) {
      foreach ($this->getAliasItems($tid) as $id) {
        $tot += $this->applyTemplate($id);
      }
    }

    $tids = $this->getAliasItems($this->getTemplatesTemplateId());
    foreach ($tids as $tid) {
      foreach ($this->getAliasItems($tid) as $id) {
        $tot += $this->applyTemplate($id);
      }
    }

    return $tot;
  }


  /**
   * @param string|null $id
   * @return int|null
   */
  public function updateTemplate(string|null $id = null): ?int
  {
    if ($this->getMagicTemplateId() && $this->exists($id)) {
      $res = 0;
      // All the options referring to this template
      $all = $this->getAliases($id);
      if (
        !empty($all)
        && ($export = $this->export($id, 'sfull'))
        && !empty($export['items'])
      ) {
        foreach ($all as $a) {
          foreach ($this->import($export['items'], $a[$this->fields['id']]) as $num) {
            $res += $num;
          }
        }
      }

      return $res;
    }

    return null;
  }


  /**
   * @return int|null
   */
  public function updateAllTemplates(): ?int
  {
    if ($id = $this->fromCode('list', 'templates')) {
      $res = 0;
      foreach ($this->itemsRef($id) ?? [] as $a) {
        $res += (int)$this->updateTemplate($a);
      }

      return $res;
    }

    return null;
  }


  /**
   * @param string $id
   * @param bool $rootAlso
   * @return int|null
   */
  public function applyTemplate(string $id, bool $rootAlso = false): ?int
  {
    if (!$this->getMagicTemplateId()) {
      return null;
    }

    if (!($idAlias = $this->alias($id))) {
      throw new Exception(X::_("Impossible to apply a template, the option must be aliased"));
    }

    $tot = 0;
    $templateParent = $this->parent($idAlias);
    if ($templateParent['id'] !== $this->getMagicTemplateId()) {
      if (!$templateParent['id_alias']) {
        throw new Exception(X::_("Impossible to apply a template, the template's parent must have an alias"));
      }

      if ($templateParent['id_alias'] !== $this->getTemplatesTemplateId()) {
        throw new Exception(X::_("Impossible to apply a template, the template's parent must be aliased with the templates' list"));
      }
    }

    /*
    if ($rootAlso) {
      $opt = $this->option($id);
      $topt = $this->option($idAlias);
      unset($opt['id_alias'], $opt['alias']);
      if ((json_encode($opt) !== json_encode($topt)) && $this->set($id, $topt)) {
        $tot++;
      }
    }
    */

    $this->unsetCfg($id);

    foreach ($this->items($idAlias) as $tid) {
      $tot += $this->applyChildTemplate($tid, $id);
    }

    return $tot;
  }

  public function applyChildTemplate($idSubtemplate, $target): int
  {
    $tot = 0;
    if ($idSubtemplate === $target) {
      throw new Exception("WTF Chiara!!!");
    }

    $opt = $this->nativeOption($idSubtemplate);
    $foptions = $this->rawOptions($target);
    $update = true;
    if (!($o = X::getRow($foptions, ['id_alias' => $idSubtemplate]))) {
      if ($o = X::getRow($foptions, ['code' => $opt['code'], 'id_alias' => null])) {
        if ($o['id'] === $idSubtemplate) {
          X::log([
            $idSubtemplate,
            $target,
            $this->option($target),
            $opt,
            $foptions,
            $this->items($target),
            $this->db->rselectAll('bbn_options', [], ['id_parent' => $target]), $foptions
          ], 'optionsFail');
        }
        if ($this->setAlias($o['id'], $idSubtemplate)) {
          $tot++;
        }
      }
      elseif ($id = $this->add([
        'id_parent' => $target,
        'id_alias' => $idSubtemplate
      ])) {
        $o = $this->nativeOption($id);
        $tot++;
        $update = false;
      }
      else {
        throw new Exception(X::_("Impossible to add the option"));
      }
    }
    if ($update && !empty($o)) {
      $upd = [];
      $f =& $this->class_cfg['arch']['options'];
      if (!empty($o[$f['code']])) {
        $upd[$f['code']] = null;
      }
      if (!empty($o[$f['text']])) {
        $upd[$f['text']] = null;
      }
      if (!empty($o[$f['value']])) {
        $upd[$f['value']] = null;
      }
      if (!empty($o[$f['cfg']])) {
        $upd[$f['cfg']] = null;
      }

      if (!empty($upd)) {
        if ($this->db->update($this->class_cfg['table'], $upd, [$f['id'] => $o['id']])) {
          $tot++;
        }
      }
    }

    foreach ($this->items($idSubtemplate) as $tid) {
      $tot += $this->applyChildTemplate($tid, $o['id']);
    }


    return $tot;
  }

  public function getParentTemplateId(string $id): ?string
  {
    $templateId1 = $this->getTemplatesTemplateId();
    $templateId2 = $this->getMagicTemplateId();
    while ($idParent = $this->getIdParent($id)) {
      // root
      if ($idParent === $id) {
        return null;
      }

      if (in_array($idParent, [$templateId1, $templateId2], true) || in_array($this->getIdAlias($idParent), [$templateId1, $templateId2], true)) {
        return $id;
      }

      $id = $idParent;
    }

    return null;
  }

  public function usedTemplate(string $id): ?string
  {
    if ($this->exists($id) && ($idAlias = $this->getIdAlias($id))) {
      $templateId1 = $this->getMagicTemplateId();
      $templateId2 = $this->getTemplatesTemplateId();
      $id = $idAlias;
      while ($idParent = $this->getIdParent($id)) {
        // root
        if ($idParent === $id) {
          return null;
        }

        if (($idParent === $templateId1) || ($this->getIdAlias($idParent) === $templateId2)) {
          return $id;
        }

        $id = $idParent;
      }
    }

    return null;
  }

  public function hasTemplate(string $id): bool
  {
    return (bool)$this->usedTemplate($id);
  }


  public function isPartOfTemplates($id): bool
  {
    $templateId1 = $this->getTemplatesTemplateId();
    $templateId2 = $this->getMagicTemplateId();
    if (in_array($this->getIdAlias($id), [$templateId1, $templateId2], true)) {
      return true;
    }

    return $this->isInTemplate($id);
  }


  public function isTemplate(string $id): bool
  {
    $templateId1 = $this->getTemplatesTemplateId();
    $templateId2 = $this->getMagicTemplateId();
    if ($idParent = $this->getIdParent($id)) {
      // root
      if ($idParent === $id) {
        return false;
      }

      if (in_array($idParent, [$templateId1, $templateId2], true) || in_array($this->getIdAlias($idParent), [$templateId1, $templateId2], true)) {
        return true;
      }
    }

    return false;
  }


  public function isInTemplate(string $id): bool
  {
    $templateId1 = $this->getTemplatesTemplateId();
    $templateId2 = $this->getMagicTemplateId();
    while ($idParent = $this->getIdParent($id)) {
      // root
      if ($idParent === $id) {
        return false;
      }

      if (in_array($idParent, [$templateId1, $templateId2], true) || in_array($this->getIdAlias($idParent), [$templateId1, $templateId2], true)) {
        return true;
      }

      $id = $idParent;
    }

    return false;
  }


  public function getOptionTemplate(string $id): ?array
  {
    if ($this->isInTemplate($id)) {
      return $this->option($this->alias($id));
    }

    return null;
  }


  public function isApp(string $id): bool
  {
    return $this->isPlugin($id) && ($this->getIdParent($id) === $this->root);
  }

}
