<?php

namespace bbn\Appui\Option\Internal;

use Exception;
use bbn\X;

trait Plugin
{
  /**
   * @return int|null
   */
  public function updatePlugins(): ?int
  {
    if (($pluginAlias = $this->getPluginTemplateId())
      && ($export = $this->export($pluginAlias, 'sfull'))
    ) {
      $res = 0;
      /*
      $codePath = $this->getCodePath($pluginAlias);
      $items = X::map(function($a) use ($pluginAlias) {
        $a['id_alias'] = $pluginAlias;
        return $a;
      }, $export['items'], 'items');
      $idPlugins = $this->getAliasItems($pluginAlias);
      foreach ($idPlugins as $idPlugin) {
        foreach ($this->import($items, $idPlugin) as $num) {
          $res += $num;
        }
      }
      */

      return $res;
    }

    return null;
  }


  /**
   * @return string|null
   */
  public function getParentSubplugin(...$codes): ?string
  {
    if ($id = $this->fromCode(...$codes)) {
      return ($r = $this->getClosest($id, 'subplugin')) ? $r['id'] : null;
    }

    return null;
  }


  /**
   * @return string|null
   */
  public function getParentPlugin(...$codes): ?string
  {
    if ($id = $this->fromCode(...$codes)) {
      return ($r = $this->getClosest($id, 'plugin')) ? $r['id'] : null;
    }

    return null;
  }


  /**
   * @return string|null
   */
  public function getParentApp(...$codes): ?string
  {
    if ($id = $this->fromCode(...$codes)) {
      return ($r = $this->getClosest($id, 'app')) ? $r['id'] : null;
    }

    return null;
  }


  public function getClosest($id, $type): ?array
  {
    $subpluginAlias = $this->getSubpluginTemplateId();
    $pluginAlias = $this->getPluginTemplateId();

    if ($subpluginAlias && $pluginAlias) {
      $ids = $this->parents($id);
      $num = count($ids);
      foreach ($ids as $i => $id) {
        if ($this->getIdAlias($id) === $subpluginAlias) {
          if (!$type || ($type === 'subplugin')) {
            return ['type' => 'subplugin', 'id' => $id];
          }
        }
        elseif ($this->getIdAlias($id) === $pluginAlias) {
          if ($num < 3) {
            if (!$type || ($type === 'app')) {
              return ['type' => 'app', 'id' => $id];
            }

            break;
          }
          else {
            if (!$type || ($type === 'plugin')) {
              return ['type' => 'plugin', 'id' => $id];
            }
          }
        }

        $num--;
      }
    }

    return null;
  }


  public function getPluginName($id): ?string
  {
    $pluginAlias = $this->getPluginTemplateId();
    $pluginsAlias = $this->getPluginsTemplateId();
    $o = $this->option($id);
    if ($pluginAlias && ($o['id_alias'] === $pluginAlias)) {
      $st = '';
      while ($o && ($o['id_alias'] !== $pluginsAlias)) {
        $code = $o['code'] ?: $o['alias']['code'];
        $st = $code . ($st ? '-' . $st : '');
        $o = $o['id_parent'] !== $o['id'] ? $this->option($o['id_parent']) : null;
      }

      return $st;
    }

    return null;
  }

  public function getSubpluginName($id): ?string
  {
    $subpluginAlias = $this->getSubpluginTemplateId();
    $pluginsAlias = $this->getPluginsTemplateId();
    $o = $this->option($id);
    $code = $o['code'] ?: $o['alias']['code'];
    if ($subpluginAlias && ($o['id_alias'] === $subpluginAlias)) {
      $st = '';
      while ($o && ($o['id_alias'] !== $pluginsAlias)) {
        $st = $code . ($st ? '-' . $st : '');
        $o = $o['id_parent'] !== $o['id'] ? $this->option($o['id_parent']) : null;
      }

      return $st;
    }

    return null;
  }

  public function isPlugin($id): bool
  {
    if ($this->alias($id) === $this->getPluginTemplateId()) {
      return true;
    }

    return false;
  }


  public function getPlugins($root = null, bool $full = false, bool $withSubs = false): ?array
  {
    $pluginAlias = $this->getPluginTemplateId();
    $pluginsAlias = $this->getPluginsTemplateId();
    $plugins = $this->fromCode('plugins', $root ?: $this->getDefault());
    $res = [];
    if ($pluginAlias && $pluginsAlias && $plugins) {
      foreach ($this->fullOptions($plugins) as $p) {
        $originalId = $p['id'];
        $code = $p['code'];
        $pluginId = null;
        if ($p['id_alias'] === $pluginAlias) {
          $pluginId = $p['id'];
          $pluginText = $p['text'];
          $pluginIcon = $p['icon'] ?? '';
        }
        elseif (!$p['text'] && !empty($p['alias']) && ($p['alias']['id_alias'] === $pluginAlias)) {
          $pluginId = $p['id_alias'];
          $pluginText = $p['alias']['text'];
          $pluginIcon = $p['alias']['icon'];
          $code = $p['alias']['code'] ?? '';
        }

        if ($pluginId) {
          if (empty($code)) {
            throw new Exception(X::_("The plugin option must have a code"));
          }

          $item = [
            'id' => $originalId,
            'code' => $code,
            'text' => $pluginText,
            'icon' => $pluginIcon,
          ];
          if ($full) {
            $item = array_merge($item, [
              'rootPlugins' => $this->fromCode('plugins', $pluginId),
              'rootOptions' => $this->fromCode('options', $pluginId),
              'rootTemplates' => $this->fromCode('templates', $pluginId),
              'rootPermissions' => $this->fromCode('permissions', $pluginId)
            ]);
          }

          if ($withSubs) {
            $item['subplugins'] = $this->getSubplugins($pluginId);
          }

          $res[] = $item;
        }
        else {
          if (empty($code) && !empty($p['alias']['code'])) {
            $code = $p['alias']['code'];
          }
          if (empty($code)) {
            throw new Exception(X::_("The plugin alias option must have a code"));
          }
          
          foreach ($this->fullOptions($p['id']) as $p2) {
            $code2 = $p2['code'];
            $pluginId = null;
            if ($p2['id_alias'] === $pluginAlias) {
              $pluginId = $p2['id'];
              $pluginText = $p2['text'];
              $pluginIcon = $p2['icon'] ?? '';
            }
            elseif (!$p2['text'] && !empty($p2['alias']) && ($p2['alias']['id_alias'] === $pluginAlias)) {
              $pluginId = $p2['id_alias'];
              $pluginText = $p2['alias']['text'];
              $pluginIcon = $p2['alias']['icon'];
              $code2 = $p2['alias']['code'] ?? '';
            }
    
            if ($pluginId) {
              if (empty($code2)) {
                throw new Exception(X::_("The plugin option must have a code"));
              }
    
              $item = [
                'id' => $p2['id'],
                'code' => $code . '-' . $code2,
                'text' => $pluginText,
                'icon' => $pluginIcon,
              ];
              if ($full) {
                $item = array_merge($item, [
                  'rootPlugins' => $this->fromCode('plugins', $pluginId),
                  'rootOptions' => $this->fromCode('options', $pluginId),
                  'rootTemplates' => $this->fromCode('templates', $pluginId),
                  'rootPermissions' => $this->fromCode('permissions', $pluginId)
                ]);
              }
    
              if ($withSubs) {
                $item['subplugins'] = $this->getSubplugins($pluginId);
              }
    
              $res[] = $item;
            }
          }
        }
      }

      return $res;
    }

    return null;
  }


  public function getSubplugins(string $id_plugin): ?array
  {
    $subpluginAlias = $this->getSubpluginTemplateId();
    $pluginAlias = $this->getPluginTemplateId();
    $pluginsAlias = $this->getPluginsTemplateId();
    $plugins = $this->fromCode('plugins', $id_plugin);
    $res = [];
    if ($pluginAlias && $pluginsAlias && $plugins) {
      foreach ($this->fullOptions($plugins) as $p) {
        $code = $p['code'] ?: $p['alias']['code'];
        if (empty($code)) {
          throw new Exception(X::_("The plugin option must have a code"));
        }

        if ($p['id_alias'] === $subpluginAlias) {
          $res[] = [
            'id' => $p['id'],
            'code' => $code,
            'text' => $p['text'],
            'icon' => $p['icon'] ?? '',
            'rootOptions' => $this->fromCode('options', $p['id']),
            'rootPermissions' => $this->fromCode('permissions', $p['id'])
          ];
        } else {
          foreach ($this->fullOptions($p['id']) as $p2) {
            $code2 = $p2['code'] ?: $p2['alias']['code'];
            if (empty($code2)) {
              throw new Exception(X::_("The plugin option must have a code"));
            }

            if ($p2['id_alias'] === $subpluginAlias) {
              $res[] = [
                'id' => $p2['id'],
                'code' => "$code-$code2",
                'text' => $p2['text'] ?: "$code-$code2",
                'icon' => $p2['icon'] ?? '',
                'rootOptions' => $this->fromCode('options', $p2['id']),
                'rootPermissions' => $this->fromCode('permissions', $p2['id'])
              ];
            }
          }
        }
      }

      return $res;
    }

    return null;
  }
}
