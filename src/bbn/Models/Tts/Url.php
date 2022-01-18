<?php

namespace bbn\Models\Tts;

use Exception;
use bbn\Appui\Url as urlCls;
use bbn\X;

trait Url
{

  /**
   * Remains false until initUrl is executed for preventing it's executed twice
   *
   * @var boolean
   */
  protected $isInitUrl = false;

  /**
   * The name of the table associating the items from the current class and the URLs
   *
   * @var string
   */
  protected $urlTable;

  /**
   * The name of the index of the table in class_cfg (eg for media it's medias_url)
   *
   * @var string
   */
  protected $urlTableIdx;

  /**
   * The array of fields/columns for the urlTable
   *
   * @var array
   */
  protected $urlFields;


  /**
   * The url object
   *
   * @var bbn\Appui\Url
   */
  protected $url;


  /**
   * initialize the trait's props
   *
   * @return void
   */
  protected function initUrl(): void
  {
    if (!$this->isInitUrl
        && $this->db
        && $this->class_cfg
        && $this->class_table_index
        && $this->class_cfg['tables'][$this->class_table_index . '_url']
        && $this->class_cfg['urlItemField']
    ) {
      $this->urlTableIdx = $this->class_table_index . '_url';
      $this->urlTable    = $this->class_cfg['tables'][$this->urlTableIdx];
      $this->urlFields   = $this->class_cfg['arch'][$this->urlTableIdx];
      if (X::hasProps($this->urlFields, ['id_url', $this->class_cfg['urlItemField']], true)) {
        $this->url = new urlCls($this->db);
      }
      $this->isInitUrl = true;
    }
  }


  /**
   * Checks if the class has been correctly initialized and throws an exception if not
   *
   * @return void
   */
  protected function checkUrlCfg(): void
  {
    $this->initUrl();
    if (!$this->url) {
      throw new Exception(X::_("The class is missing configuration to make use of URL"));
    }
  }



  /**
   * Returns the URL corresponding to the given item's ID
   *
   * @param string $id_item
   * @param boolean $followRedirect
   * @return string|null
   */
  public function getUrl(string $id_item, bool $followRedirect = true): ?string
  {
    $this->checkUrlCfg();
    if ($id_url = $this->db->selectOne($this->urlTable, $this->urlFields['id_url'], [
      $this->class_cfg['urlItemField'] => $id_item
    ])) {
      return $this->url->getUrl($id_url, $followRedirect);
    }

    return null;
  }


  /**
   * @param string $url
   * @return bool
   */
  public function urlExists(string $url): bool
  {
    return (bool)$this->getUrlId($url);
  }


  /**
   * @param string $url
   * @return string|null
   */
  public function urlToId(string $url): ?string
  {
    $this->checkUrlCfg();
    if ($id_url = $this->getUrlId($url)) {
      return $this->db->selectOne($this->urlTable, $this->class_cfg['urlItemField'], [
        $this->urlFields['id_url'] => $id_url
      ]);
    }

    return null;
  }


  /**
   * Returns the ID of the URL for the given item
   * 
   * @param string $id_item
   * @return string|null
   */
  public function idToUrl(string $id_item): ?string
  {
    $this->checkUrlCfg();
    return $this->db->selectOne($this->urlTable, $this->urlFields['id_url'], [
      $this->class_cfg['urlItemField'] => $id_item
    ]);
  }


  /**
   * Returns a URL's id based on its URL
   *
   * @param string $url
   * @return string|null
   */
  public function getUrlId(string $url): ?string
  {
    $this->checkUrlCfg();
    return $this->url->retrieveUrl($url);
  }


  /**
   * Returns the whole content of the URL row based on its ID
   *
   * @param string $id_url
   * @return array|null
   */
  public function getFullUrl(string $id_url): ?array
  {
    $this->checkUrlCfg();
    return $this->url->select($id_url);
  }


  /**
   * Adds or replace a URL for a given item's ID
   *
   * @param string $id_item
   * @param string $url
   * @param string $type
   * @return boolean
   */
  public function setUrl(string $id_item, string $url, string $type = null): bool
  {
    $this->checkUrlCfg();
    if (!($url = $this->sanitize($url))) {
      throw new Exception(X::_("The URL can't be empty"));
    }

    if (!($id_url = $this->url->retrieveUrl($url))
        && (!$id_url = $this->url->add($url, $type))
    ) {
      throw new Exception(X::_("Impossible to add the URL %s", $url));
    }

    if ($checkItem = $this->getUrlItem($id_url)) {
      if ($checkItem !== $id_item) {
        throw new Exception(X::_("The URL is already in use by another item"));
      }
    }
    else {
      return (bool)$this->db->insert($this->urlTable, [
        $this->class_cfg['urlItemField'] => $id_item,
        $this->urlFields['id_url'] => $id_url
      ]);
    }

    return true;
  }


  /**
   * Creates a new URL for a given item's ID
   *
   * @param string $id_item
   * @param string $url
   * @param string $prefix
   * @param string $type
   * @return boolean
   */
  public function addUrl(string $id_item, string $url, string $prefix = '', string $type = null): bool
  {
    $this->checkUrlCfg();

    if (!$type && !$this->urlType) {
      throw new Exception(X::_("You have no type set and no default type for the class %s"), __CLASS__);
    }

    if ($id_url = $this->url->add($url, $type ?: $this->urlType, $prefix)) {
      $this->db->delete($this->urlTable, [
        $this->class_cfg['urlItemField'] => $id_item
      ]);
      return (bool)$this->db->insert($this->urlTable, [
        $this->class_cfg['urlItemField'] => $id_item,
        $this->urlFields['id_url'] => $id_url
      ]);
    }

    return false;
  }


  /**
   * Returns true if the item is linked to an url.
   *
   * @param string $id_item
   * @return bool
   *
   */
  public function hasUrl(string $id_item): bool
  {
    return (bool)$this->db->count(
      $this->urlTable,
      [$this->class_cfg['urlItemField'] => $id_item]
    );
  }


  /**
   * Deletes url for the given note.
   *
   * @param string $id_item
   * @return int|null
   */
  public function deleteUrl(string $id_item)
  {

    $this->db->count(
      $this->urlTable,
      [$this->class_cfg['urlItemField'] => $id_item]
    );
    $id_url = $this->db->selectOne(
      $this->urlTable,
      $this->urlFields['id_url'],
      [$this->class_cfg['urlItemField'] => $id_item]
    );

    if ($id_url) {
      $this->db->delete(
        $this->urlTable,
        [$this->class_cfg['urlItemField'] => $id_item]
      );
      return (bool)$this->url->delete($id_url);
    }

    throw new Exception(X::_("Impossible to retrieve the URL for item %s", $id_item));
  }



}
