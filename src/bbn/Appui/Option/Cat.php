<?php

namespace bbn\Appui\Option;

trait Cat
{
  /**
   * Retourne la liste des catégories sous forme de tableau indexé sur son `id`
   *
   * @return array Liste des catégories
   */
  public function categories(): array
  {
    return $this->options(false);
  }


  /**
   * Retourne la liste des catégories indexée sur leur `id` sous la forme d'un tableau text/value
   *
   * @return null|array La liste des catégories dans un tableau text/value
   */
  public function textValueCategories(): ?array
  {
    if ($rs = $this->options(false)) {
      $res = [];
      foreach ($rs as $val => $text){
        $res[] = ['text' => $text, 'value' => $val];
      }

      return $res;
    }

    return null;
  }


  /**
   * Retourne toutes les caractéristiques des options d'une catégorie donnée dans un tableau indexé sur leur `id`
   *
   * @return array Un tableau des caractéristiques de chaque option de la catégorie, indexée sur leur `id`
   */
  public function fullCategories(): array
  {
    if ($opts = $this->fullOptions(false)) {
      foreach ($opts as $k => $o){
        if (!empty($o['default'])) {
          $opts[$k]['default'] = $this->text($o['default']);
        }
      }
    }

    return $opts ?? [];
  }

  /**
   * Retourne toutes les caractéristiques des options d'une catégorie donnée dans un tableau indexé sur leur `id`
   *
   * @param string|null $id
   * @return array Un tableau des caractéristiques de chaque option de la catégorie, indexée sur leur `id`
   */
  public function jsCategories($id = null)
  {
    if (!$id) {
      $id = $this->fromCode('options', $this->default);
    }

    if ($tmp = $this->getCache($id, __FUNCTION__)) {
      return $tmp;
    }

    $res = [
      'categories' => []
    ];
    if ($cats = $this->fullOptions($id ?: false)) {
      foreach ($cats as $cat){
        if (!empty($cat['tekname'])) {
          $additional = [];
          if ($schema = $this->getSchema($cat[$this->fields['id']])) {
            array_push($additional, ...array_map(function($a) {
              return $a['field'];
            }, $schema));
          }
          $res[$cat['tekname']] = $this->textValueOptions($cat[$this->fields['id']], 'text', 'value', ...$additional);
          $res['categories'][$cat[$this->fields['id']]] = $cat['tekname'];
        }
      }
    }

    $this->setCache($id, __FUNCTION__, $res);
    return $res;
  }
}
