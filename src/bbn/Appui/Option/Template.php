<?php

namespace bbn\Appui\Option;

use Exception;
use bbn\X;

trait Template
{
  protected $templateIds = [];

  protected $magicTemplateId;

  protected $magicOptionsTemplateId;

  protected $magicPluginTemplateId;

  protected $magicSubpluginTemplateId;

  protected $magicSubOptionsTemplateId;

  protected $magicSubPermissionsTemplateId;

  protected $magicTemplateTemplateId;

  protected $magicAppuiTemplateId;

  protected $magicPluginsTemplateId;


  public function getTemplateId($code): ?string
  {
    if ($this->check()) {
      if (!isset($this->templateIds[$code])) {
        $this->templateIds[$code] = $this->fromCode($code, $this->getMagicTemplateId());
      }

      if (!isset($this->templateIds[$code])) {
        foreach ($this->getAliasItems($this->getMagicTemplateTemplateId()) as $it) {
          if ($this->templateIds[$code] = $this->fromCode($code, $it['id'])) {
            break;
          }
        }
      }

      return $this->templateIds[$code] ?? null;
    }
    return null;
  }


  /**
   * Returns the ID of the root templates
   * @return string
   */
  public function getMagicTemplateId(): string
  {
    if (!$this->magicTemplateId && $this->check()) {
      $this->magicTemplateId = $this->fromCode('templates', $this->getRoot());
    }

    return $this->magicTemplateId;
  }


  /**
   * Returns the ID of the 'plugin > permissions' template
   * @return string
   */
  public function getPermissionsTemplateId()
  {
    if (!$this->magicOptionsTemplateId && $this->check()) {
      $this->magicOptionsTemplateId = $this->fromCode('permissions', $this->getMagicPluginTemplateId());
    }

    return $this->magicOptionsTemplateId;
  }


  /**
   * Returns the ID of the 'plugin > options' template
   * @return string
   */
  public function getMagicOptionsTemplateId()
  {
    if (!$this->magicOptionsTemplateId && $this->check()) {
      $this->magicOptionsTemplateId = $this->fromCode('options', $this->getMagicPluginTemplateId());
    }

    return $this->magicOptionsTemplateId;
  }


  /**
   * Returns the ID of the 'plugin' template
   * @return string
   */
  public function getMagicPluginTemplateId()
  {
    if (!$this->magicPluginTemplateId && $this->check()) {
      $this->magicPluginTemplateId = $this->fromCode('plugin', 'templates', $this->getRoot());
    }

    return $this->magicPluginTemplateId;
  }


  /**
   * Returns the ID of the 'subplugin' template i.e. plugins in plugin
   * @return string
   */
  public function getMagicSubpluginTemplateId()
  {
    if (!$this->magicSubpluginTemplateId && $this->check()) {
      $this->magicSubpluginTemplateId = $this->fromCode('subplugin', 'templates', $this->getRoot());
    }

    return $this->magicSubpluginTemplateId;
  }


  /**
   * Returns the ID of the options template in the 'subplugin' template
   * @return string
   */
  public function getMagicSubOptionsTemplateId()
  {
    if (!$this->magicSubpluginTemplateId && $this->check()) {
      $this->magicSubpluginTemplateId = $this->fromCode('options', $this->getMagicSubpluginTemplateId());
    }

    return $this->magicSubOptionsTemplateId;
  }


  /**
   * Returns the ID of the options template in the 'subplugin' template
   * @return string
   */
  public function getMagicSubPermissionsTemplateId()
  {
    if (!$this->magicSubpluginTemplateId && $this->check()) {
      $this->magicSubpluginTemplateId = $this->fromCode('permissions', $this->getMagicSubpluginTemplateId());
    }

    return $this->magicSubPermissionsTemplateId;
  }


  /**
   * Returns the ID of the 'plugin > template' template
   * @return string
   */
  public function getMagicTemplateTemplateId()
  {
    if (!$this->magicTemplateTemplateId && $this->check()) {
      $this->magicTemplateTemplateId = $this->fromCode('templates', $this->getMagicPluginTemplateId());
    }

    return $this->magicTemplateTemplateId;
  }


  public function getMagicPluginsTemplateId()
  {
    if (!$this->magicPluginsTemplateId && $this->check()) {
      $this->magicPluginsTemplateId = $this->fromCode('plugins', $this->getMagicPluginTemplateId());
    }

    return $this->magicPluginsTemplateId;
  }


  /**
   * Returns the ID of the 'plugin > plugins > appui' template
   * @return string
   */
  public function getMagicAppuiTemplateId()
  {
    if (!$this->magicAppuiTemplateId && $this->check()) {
      $this->magicAppuiTemplateId = $this->fromCode('appui', $this->getMagicPluginsTemplateId());
    }

    return $this->magicAppuiTemplateId;
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

    $tids = $this->getAliasItems($this->getMagicTemplateTemplateId());
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
  public function updateTemplate(string $id = null): ?int
  {
    if ($this->exists($id)) {
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
    if (!($idAlias = $this->alias($id))) {
      X::ddump($this->option($id));
      throw new Exception(X::_("Impossible to apply a template, the option must be aliased"));
    }

    $tot = 0;
    $templateParent = $this->parent($idAlias);
    if ($templateParent['id'] !== $this->getMagicTemplateId()) {
      if (!$templateParent['id_alias']) {
        throw new Exception(X::_("Impossible to apply a template, the template's parent must have an alias"));
      }

      if ($templateParent['id_alias'] !== $this->getMagicTemplateTemplateId()) {
        throw new Exception(X::_("Impossible to apply a template, the template's parent must be aliased with the templates' list"));
      }
    }

    if ($rootAlso) {
      $opt = $this->option($id);
      $topt = $this->option($idAlias);
      unset($opt['id_alias'], $opt['alias']);
      if ((json_encode($opt) !== json_encode($topt)) && $this->set($id, $topt)) {
        $tot++;
      }
    }

    foreach ($this->items($idAlias) as $tid) {
      $tot += $this->applyChildTemplate($tid, $id);
    }

    return $tot;
  }

  public function applyChildTemplate($idSubtemplate, $target): int
  {
    $tot = 0;
    $opt = $this->option($idSubtemplate);
    $foptions = $this->fullOptions($target);
    if (!($o = X::getRow($foptions, ['id_alias' => $idSubtemplate]))) {
      if ($opt['code']) {
        $o = X::getRow($foptions, ['code' => $opt['code']]);
      } else {
        $o = X::getRow($foptions, ['text' => $opt['text']]);
      }

      if ($o) {
        $o['id_alias'] = $idSubtemplate;
      }
    }

    $id = null;
    $cfg = $this->getCfg($idSubtemplate);
    if ($o) {
      $id = $o['id'];
      unset($opt['alias']);
      $opt['id_alias'] = $idSubtemplate;
      $ocfg = $this->getCfg($id);
      $totDone = false;
      if ((json_encode($opt) !== json_encode($o)) && $this->set($id, $opt)) {
        $tot++;
        $totDone = true;
      }
      if ((json_encode($cfg) !== json_encode($ocfg)) && $this->setCfg($id, $cfg) && !$totDone) {
        $tot++;
      }
    } else {
      $opt['id_parent'] = $target;
      $opt['id_alias'] = $idSubtemplate;
      if ($id = $this->add($opt)) {
        $this->setCfg($id, $cfg);
        $tot++;
      }
    }

    foreach ($this->items($idSubtemplate) as $tid) {
      $tot += $this->applyChildTemplate($tid, $id);
    }

    return $tot;
  }

  public function parentTemplate(string $id): ?string
  {
    if ($this->exists($id) && ($idAlias = $this->getIdAlias($id))) {
      $idTemplate = $this->getMagicTemplateId();
      $idParent = $idAlias;
      while ($idParent) {
        $id = $idParent;
        if ($idParent === $idTemplate) {
          return $id;
        } else if ($this->getIdAlias($idParent) === $idTemplate) {
          return $id;
        }
        $idParent = $this->getIdParent($idParent);
      }
    }

    return null;
  }

  public function hasTemplate(string $id): bool
  {
    return (bool)$this->parentTemplate($id);
  }


  public function isInTemplate(string $id): bool
  {
    $templateId = $this->getMagicTemplateId();
    while ($idParent = $this->getIdParent($id)) {
      if ($idParent === $templateId) {
        return true;
      }
      if ($this->getIdAlias($idParent) === $templateId) {
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
