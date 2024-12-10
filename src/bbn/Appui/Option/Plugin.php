<?php

namespace bbn\Appui\Option;

use Exception;
use bbn\X;

trait Plugin
{
  /**
   * @return int|null
   */
  public function updatePlugins(): ?int
  {
    if (($pluginAlias = $this->getMagicPluginTemplateId())
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
   * @return int|null
   */
  public function getParentPlugin($code = null): ?string
  {
    if ($pluginAlias = $this->getMagicPluginTemplateId()) {
      $ids = $this->parents(...\func_get_args());
      $num = count($ids);
      foreach ($ids as $i => $id) {
        if ($this->alias($id) === $pluginAlias) {
          return $id;
        }

        // Roots are plugin too, don't return them
        if ($num - $i < 4) {
          break;
        }
      }
    }

    return null;
  }


  public function getPluginName($id): ?string
  {
    $pluginAlias = $this->getMagicPluginTemplateId();
    $pluginsAlias = $this->getMagicPluginsTemplateId();
    $o = $this->option($id);
    if ($pluginAlias && ($o['id_alias'] === $pluginAlias)) {
      $st = '';
      while ($o && ($o['id_alias'] !== $pluginsAlias)) {
        $st .= $o['code'] . ($st ? '-' . $st : '');
        $o = $this->option($o['id_parent']);
      }

      return $st;
    }

    return null;
  }

  public function isPlugin($id): bool
  {
    if ($this->alias($id) === $this->getMagicPluginTemplateId()) {
      return true;
    }

    return false;
  }


  public function getPlugins($root = null): ?array
  {
    $pluginAlias = $this->getMagicPluginTemplateId();
    $pluginsAlias = $this->getMagicPluginsTemplateId();
    $plugins = $this->fromCode('plugins', $root ?: $this->getDefault());
    $res = [];
    if ($pluginAlias && $pluginsAlias && $plugins) {
      foreach ($this->fullOptions($plugins) as $p) {
        if (empty($p['code'])) {
          throw new Exception(X::_("The plugin option must have a code"));
        }

        $code = $p['code'];
        if ($p['id_alias'] === $pluginAlias) {
          $res[] = [
            'id' => $p['id'],
            'code' => $code,
            'text' => $p['text'],
            'icon' => $p['icon']
          ];
        } else {
          foreach ($this->fullOptions($p['id']) as $p2) {
            if (empty($p2['code'])) {
              throw new Exception(X::_("The plugin option must have a code"));
            }

            if ($p2['id_alias'] === $pluginAlias) {
              $res[] = [
                'id' => $p2['id'],
                'code' => $code . '-' . $p2['code'],
                'text' => $p2['text'],
                'icon' => $p2['icon']
              ];
            }
          }
        }
      }

      return $res;
    }

    if ($pluginAlias = $this->getMagicPluginTemplateId()) {

      $all = $this->optionsByAlias($pluginAlias);
      foreach ($all as &$a) {
        $a['name'] = $this->getPluginName($a['id']);
      }

      unset($a);
      return $all;
    }

    return null;
  }


  public function getSubplugins(string $id_plugin): ?array
  {
    $pluginAlias = $this->getMagicPluginTemplateId();
    $pluginsAlias = $this->getMagicPluginsTemplateId();
    $plugins = $this->fromCode('plugins', $id_plugin);
    $res = [];
    if ($pluginAlias && $pluginsAlias && $plugins) {
      foreach ($this->fullOptions($plugins) as $p) {
        if (empty($p['code'])) {
          throw new Exception(X::_("The plugin option must have a code"));
        }

        $code = $p['code'];
        if ($p['id_alias'] === $pluginAlias) {
          $res[] = [
            'id' => $p['id'],
            'code' => $code,
            'text' => $p['text'],
            'icon' => $p['icon']
          ];
        } else {
          foreach ($this->fullOptions($p['id']) as $p2) {
            if (empty($p2['code'])) {
              throw new Exception(X::_("The plugin option must have a code"));
            }

            if ($p2['id_alias'] === $pluginAlias) {
              $res[] = [
                'id' => $p2['id'],
                'code' => $code . '-' . $p2['code'],
                'text' => $p2['text'],
                'icon' => $p2['icon']
              ];
            }
          }
        }
      }

      return $res;
    }

    if ($pluginAlias = $this->getMagicPluginTemplateId()) {

      $all = $this->optionsByAlias($pluginAlias);
      foreach ($all as &$a) {
        $a['name'] = $this->getPluginName($a['id']);
      }

      unset($a);
      return $all;
    }

    return null;
  }
}
