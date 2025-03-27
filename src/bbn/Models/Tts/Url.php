<?php

namespace bbn\Models\Tts;

use Exception;
use stdClass;
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
  * The default type for the links, must be set in the construct
   *
   * @var string
   */
  protected $urlType;

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
   * Returns the URL corresponding to the given item's ID
   *
   * @param string $id_item
   * @param boolean $followRedirect
   * @return string|null
   */
  public function getUrls(string $id_item, bool $followRedirect = true): array
  {
    $this->checkUrlCfg();
    $res = [];
    if ($id_urls = $this->db->getColumnValues($this->urlTable, $this->urlFields['id_url'], [
      $this->class_cfg['urlItemField'] => $id_item
    ])) {
      foreach ($id_urls as $id_url) {
        $res[] = $this->url->getUrl($id_url, $followRedirect);
      }
    }

    return $res;
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
   * @return stdClass|null
   */
  public function getFullUrl(string $id_url): ?stdClass
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
   * @return null|string
   */
  public function setUrl(string $id_item, string $url, string|null $type = null): ?string
  {
    $this->checkUrlCfg();
    if (!($url = $this->sanitizeUrl($url))) {
      throw new Exception(X::_("The URL can't be empty"));
    }

    if (!($id_url = $this->url->retrieveUrl($url))
        && (!$id_url = $this->url->add($url, $type ?: $this->urlType))
    ) {
      throw new Exception(X::_("Impossible to add the URL %s", $url));
    }

    if ($checkItem = $this->urlToId($id_url)) {
      if ($checkItem !== $id_item) {
        throw new Exception(X::_("The URL is already in use by another item"));
      }
    }
    elseif (!$this->db->insert($this->urlTable, [
      $this->class_cfg['urlItemField'] => $id_item,
      $this->urlFields['id_url'] => $id_url
    ])) {
      return null;
    }

    return $id_url ?: null;
  }


  /**
   * Creates a new URL for a given item's ID
   *
   * @param string $id_item
   * @param string $url
   * @param string $prefix
   * @param string $type
   * @return null|string
   */
  public function addUrl(string $id_item, string $url, string $prefix = '', string|null $type = null): ?string
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

    return $id_url ?: null;
  }


  /**
   * Returns true if the item is linked to an url.
   *
   * @param string $id_source
   * @param string $id_destination
   * @return bool
   *
   */
  public function redirectUrl(string $id_item, string $url_source, string $url_destination): bool
  {
    $this->checkUrlCfg();
    if ($id_source = $this->getUrlId($url_source)) {
      $url = $this->getFullUrl($id_source);
      if ($url && ($id_destination = $this->setUrl($id_item, $url_destination, $url->type))) {
        $cfg = $this->url->getClassCfg();
        return (bool)$this->db->update(
          $cfg['table'],
          [$cfg['arch']['url']['redirect'] => $id_destination],
          [$cfg['arch']['url']['id'] => $id_source]
        );
      }
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
    $this->checkUrlCfg();
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
    $this->checkUrlCfg();
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


  /**
   * Trims the slashes and removes double slashes if any.
   *
   * @param string $url
   * @return string
   */
  public function sanitizeUrl(string $url): string
  {
    $this->checkUrlCfg();
    return $this->url->sanitize($url);
  }


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
        && $this->class_cfg['urlTypeValue']
    ) {
      $this->urlTableIdx = $this->class_table_index . '_url';
      $this->urlTable    = $this->class_cfg['tables'][$this->urlTableIdx];
      $this->urlFields   = $this->class_cfg['arch'][$this->urlTableIdx];
      $this->urlType     = $this->class_cfg['urlTypeValue'];
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

}
