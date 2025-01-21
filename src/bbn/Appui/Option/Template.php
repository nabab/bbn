<?php

namespace bbn\Appui\Option;

use Exception;
use bbn\X;

trait Template
{
  protected $templateIds = [];

  protected $magicTemplateId;

  protected $magicOptionsTemplateId;

  protected $magicPermissionsTemplateId;

  protected $magicPluginTemplateId;

  protected $magicSubpluginTemplateId;

  protected $magicSubOptionsTemplateId;

  protected $magicSubPermissionsTemplateId;

  protected $magicTemplateTemplateId;

  protected $magicAppuiTemplateId;

  protected $magicPluginsTemplateId;


  public function getTemplateId(...$codes): ?string
  {
    if ($this->check() && count($codes)) {
      $code = array_pop($codes);
      if (!isset($this->templateIds[$code])) {
        $this->templateIds[$code] = $this->fromCode($code, $this->getMagicTemplateId()) ?: null;
      }

      if (!isset($this->templateIds[$code])) {
        foreach ($this->getAliasItems($this->getMagicTemplateTemplateId()) as $it) {
          if ($tmp = $this->fromCode($code, $it['id'])) {
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
    if ($this->getMagicTemplateId() && !$this->magicPermissionsTemplateId && $this->check()) {
      $this->magicPermissionsTemplateId = $this->fromCode('permissions', $this->getMagicPluginTemplateId());
    }

    return $this->magicPermissionsTemplateId;
  }


  /**
   * Returns the ID of the 'plugin > options' template
   * @return string
   */
  public function getMagicOptionsTemplateId(): ?string
  {
    if ($this->getMagicTemplateId() && !$this->magicOptionsTemplateId && $this->check()) {
      $cfg = $this->getClassCfg();
      $this->magicOptionsTemplateId = $this->db->selectOne($cfg['table'], $cfg['arch']['options']['id'], [
        $cfg['arch']['options']['id_parent'] => $this->getMagicPluginTemplateId(),
        $cfg['arch']['options']['code'] => 'options',
      ]);
    }

    return $this->magicOptionsTemplateId;
  }


  /**
   * Returns the ID of the 'plugin' template
   * @return string
   */
  public function getMagicPluginTemplateId(): ?string
  {
    if ($this->getMagicTemplateId() && !$this->magicPluginTemplateId && $this->check()) {
      $cfg = $this->getClassCfg();
      $this->magicPluginTemplateId = $this->db->selectOne($cfg['table'], $cfg['arch']['options']['id'], [
        $cfg['arch']['options']['id_parent'] => $this->getMagicTemplateId(),
        $cfg['arch']['options']['code'] => 'plugin',
      ]);
    }

    return $this->magicPluginTemplateId;
  }


  /**
   * Returns the ID of the 'subplugin' template i.e. plugins in plugin
   * @return string
   */
  public function getMagicSubpluginTemplateId(): ?string
  {
    if ($this->getMagicTemplateId() && !$this->magicSubpluginTemplateId && $this->check()) {
      $cfg = $this->getClassCfg();
      $this->magicSubpluginTemplateId = $this->db->selectOne($cfg['table'], $cfg['arch']['options']['id'], [
        $cfg['arch']['options']['id_parent'] => $this->getMagicTemplateId(),
        $cfg['arch']['options']['code'] => 'subplugin',
      ]);
    }

    return $this->magicSubpluginTemplateId;
  }


  /**
   * Returns the ID of the options template in the 'subplugin' template
   * @return string
   */
  public function getMagicSubOptionsTemplateId(): ?string
  {
    if ($this->getMagicTemplateId() && !$this->magicSubpluginTemplateId && $this->check()) {
      $cfg = $this->getClassCfg();
      $this->magicSubOptionsTemplateId = $this->db->selectOne($cfg['table'], $cfg['arch']['options']['id'], [
        $cfg['arch']['options']['id_parent'] => $this->getMagicSubpluginTemplateId(),
        $cfg['arch']['options']['code'] => 'options',
      ]);
    }

    return $this->magicSubOptionsTemplateId;
  }


  /**
   * Returns the ID of the options template in the 'subplugin' template
   * @return string
   */
  public function getMagicSubPermissionsTemplateId(): ?string
  {
    if ($this->getMagicTemplateId() && !$this->magicSubpluginTemplateId && $this->check()) {
      $this->magicSubpluginTemplateId = $this->fromCode('permissions', $this->getMagicSubpluginTemplateId());
    }

    return $this->magicSubPermissionsTemplateId;
  }


  /**
   * Returns the ID of the 'plugin > template' template
   * @return string
   */
  public function getMagicTemplateTemplateId(): ?string
  {
    if ($this->getMagicTemplateId() && !$this->magicTemplateTemplateId && $this->check()) {
      $this->magicTemplateTemplateId = $this->fromCode('templates', $this->getMagicPluginTemplateId());
    }

    return $this->magicTemplateTemplateId;
  }


  public function getMagicPluginsTemplateId(): ?string
  {
    if ($this->getMagicTemplateId() && !$this->magicPluginsTemplateId && $this->check()) {
      $this->magicPluginsTemplateId = $this->fromCode('plugins', $this->getMagicPluginTemplateId());
    }

    return $this->magicPluginsTemplateId;
  }


  /**
   * Returns the ID of the 'plugin > plugins > appui' template
   * @return string
   */
  public function getMagicAppuiTemplateId(): ?string
  {
    if ($this->getMagicTemplateId() && !$this->magicAppuiTemplateId && $this->check()) {
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

      if ($templateParent['id_alias'] !== $this->getMagicTemplateTemplateId()) {
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
      
      if ($o = X::getRow($foptions, ['code' => $opt['code']])) {
        if ($this->setAlias($o['id'], $idSubtemplate)) {
          $tot++;
        }
      }
      elseif ($id = $this->add([
        'id_parent' => $target,
        'id_alias' => $idSubtemplate
      ])) {
        $o = $this->option($id);
        $tot++;
      }
      else {
        throw new Exception(X::_("Impossible to add the option"));
      }
    }

    if (!isset($o['code']) && !empty($opt['code'])) {
      $this->setCode($o['id'], $opt['code']);
    }

    foreach ($this->items($idSubtemplate) as $tid) {
      $tot += $this->applyChildTemplate($tid, $o['id']);
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
