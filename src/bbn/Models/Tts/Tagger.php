<?php
/**
 * Created by PhpStorm.
 * User: BBN
 * Date: 16/09/2021
 * Time: 19:35
 */

namespace bbn\Models\Tts;

use Exception;
use bbn\X;
use bbn\Appui\Tag;

/**
 * Gives static props and methods to register an instance of an object and be able to retrieve the last registered one.
 */
trait Tagger
{
  /**
   * @var bool False while initTagger has not been called.
   */
  private $taggerIsInit = false;

  /**
   * @var array The name of the table where to insert tags relations.
   */
  private $taggerTable;

  /**
   * @var array The names of the columns where to insert tags relations (must have a id_tag and a id_element indexes).
   */
  private $taggerCols;

  /**
   * @var Tag The Tag object.
   */
  protected $taggerObject;


  public function getTags(string $id_element, bool $full = false, bool $force = false): array
  {
    $this->taggerInit();
    $res = [];
    $ids = $this->db->getColumnValues(
      $this->taggerTable,
      $this->taggerCols['id_tag'],
      [$this->taggerCols['id_element'] => $id_element]
    );
    foreach ($ids as $id) {
      if ($tmp = $this->taggerObject->getById($id, $full)) {
        $res[] = $tmp;
      }
      elseif (!$force) {
        X::log([$ids, $this->db->last()]);
        throw new Exception(X::_("Impossible to find the tag %s", $id));
      }
    }
   
    return $res;
  }


  /**
   * Undocumented function
   *
   * @param string $id_element The ID of the element to which attach the tags
   * @param array  $tags A list of tags which will be retrieved or added
   * @return int 
   */
  public function setTags(string $id_element, array $tags, string $lang = ''): int
  {
    $this->taggerInit();
    $lang = $this->taggerGetLang($lang);
    if (!$this->exists($id_element)) {
      throw new Exception(X::_("Impossible to find the element in %s", __CLASS__));
    }

    foreach ($this->getTags($id_element, true) as $tag) {
      $idx = X::indexOf($tags, $tag['tag']);
      if ($idx > -1) {
        array_splice($tags, $idx, 1);
      }
      else {
        $this->removeTag($id_element, $tag['id']);
      }
    }

    $num = 0;
    foreach ($tags as $tag) {
      if ($this->addTag($id_element, $tag, $lang)) {
        $num++;
      }
    }

    return $num;
  }


  public function removeTag(string $id_element, string $id_tag): int
  {
    $this->taggerInit();
    return $this->db->delete(
      $this->taggerTable,
      [
        $this->taggerCols['id_element'] => $id_element,
        $this->taggerCols['id_tag'] => $id_tag
      ]
    );
  }


  public function removeTags(string $id_element): int
  {
    $this->taggerInit();
    return $this->db->delete(
      $this->taggerTable,
      [
        $this->taggerCols['id_element'] => $id_element,
      ]
    );
  }


  public function addTag(string $id_element, string $tag, string $lang = null, string $description = ''): int
  {
    $this->taggerInit();
    $lang = $this->taggerGetLang($lang);
    if (!($id_tag = $this->taggerObject->get($tag, $lang))) {
      $id_tag = $this->taggerObject->add($tag, $lang, $description);
    }

    if (!$id_tag) {
      throw new Exception(X::_("Impossible to create the tag %s", $tag));
    }

    return $this->db->insert(
      $this->taggerTable,
      [
        $this->taggerCols['id_element'] => $id_element,
        $this->taggerCols['id_tag'] => $id_tag
      ]
    );
  }


  protected function taggerGetLang(string $lang = '')
  {
    if ($lang) {
      return $lang;
    }

    if (method_exists($this, 'getLang')) {
      return $this->getLang();
    }

    if (defined('BBN_LANG')) {
      return BBN_LANG;
    }

    return 'en';
  }


  protected function taggerInit(string $table = null, array $columns = null)
  {
    if (!$this->taggerIsInit) {
      if (!$this->db) {
        throw new Exception(X::_("Impossible to init the tagger if there is no Db property"));
      }

      if (!$this->class_cfg) {
        throw new Exception(X::_("Impossible to init the tagger if the class hasn't the trait Dbconfig"));
      }

      if (empty($table) || empty($columns)) {
        throw new Exception(X::_("Impossible to init the tagger without a table name and 2 columns defined"));
      }

      if (empty($columns['id_tag'])) {
        throw new Exception(X::_("Impossible to init the tagger without an id_tag column"));
      }

      if (empty($columns['id_element'])) {
        throw new Exception(X::_("Impossible to init the tagger without an id_element column"));
      }

      $this->taggerObject = new Tag($this->db);
      $this->taggerTable  = $table;
      $this->taggerCols   = $columns;
      $this->taggerIsInit = true;
    }

    return $this->taggerIsInit;
  }


}
