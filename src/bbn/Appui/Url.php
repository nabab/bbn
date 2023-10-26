<?php

namespace bbn\Appui;

use bbn;
use bbn\X;
use bbn\Str;
use Exception;

class Url extends bbn\Models\Cls\Db
{
  use bbn\Models\Tts\Dbconfig;

  /** @var array */
  protected static $default_class_cfg = [
    'table' => 'bbn_url',
    'tables' => [
      'url' => 'bbn_url'
    ],
    'arch' => [
      'url' => [
        'id' => 'id',
        'url' => 'url',
        'num_calls' => 'num_calls',
        'type_url' => 'type_url',
        'redirect' => 'redirect'
      ]
    ]
  ];


  public function __construct(bbn\Db $db)
  {
    parent::__construct($db);
    $this->_init_class_cfg();
  }


  public static function sanitize(string $url, string $prefix = ''): string
  {
    $url    = trim($url, '/ ');
    $prefix = trim($prefix, '/ ');
    while (strpos($url, '//')) {
      $url = str_replace('//', '/', $url);
    }

    return normalizer_normalize($url . ($prefix ? '/' . $prefix : ''));
  }


  public function set(string $url, string $type_url, string $id_url = null): bool
  {
    if ($id_url && ($url = $this->sanitize($url))) {
      return (bool)$this->update($id_url, [
        'url' => $url
      ]);
    }

    return (bool)$this->add($url, $type_url);
  }

  public function add(string $url, string $type_url, string $prefix = ''): ?string
  {
    if ($url = $this->sanitize($url, $prefix)) {
      return $this->insert([
        $this->fields['url'] => $url,
        $this->fields['type_url'] => $type_url
      ]);
    }

    return null;
  }


  public function addRedirect(string $url, string $id_url): ?string
  {
    if (
        $url = $this->sanitize($url)
        && !$this->rselect([$this->fields['url'] => $url])
        && ($cfg = $this->rselect($id_url))
    ) {
      return $this->insert([
        $this->fields['url'] => $url,
        $this->fields['type_url'] => $cfg['type_url'],
        $this->fields['redirect'] => $cfg['redirect'] ?: $id_url
      ]);
    }

    return null;
  }


  public function getRedirect(string $url): ?string
  {
    if (
        ($url = $this->sanitize($url))
        && ($redirect = $this->selectOne($this->fields['redirect'], [$this->fields['url'] => $url]))
    ) {
      if ($this->selectOne($this->fields['redirect'], [$this->fields['id'] => $redirect])) {
        throw new Exception(X::_("You can't redirect a redirected URL (%s)", $url));
      }
      return $this->selectOne($this->fields['url'], $redirect);
    }

    return null;
  }


  public function getRedirectById(string $id): ?string
  {
    if ($redirect = $this->selectOne($this->fields['redirect'], $id)) {
      if ($this->selectOne($this->fields['redirect'], $redirect)) {
        throw new Exception(X::_("You can't redirect a redirected URL (ID %s)", $id));
      }

      return $redirect;
    }

    return null;
  }


  public function urlExists(string $url): bool
  {
    if ($url = $this->sanitize($url)) {
      return $this->exists([$this->fields['url'] => $url]);
    }

    return false;
  }


  /**
   * Returns the url of the given row
   *
   * @param string $id_url
   * @param bool $full
   * @param bool $followRedirect
   * @return string|array|null
   */
  public function retrieveUrl(string $url, bool $full = false, bool $followRedirect = true): mixed
  {
    if ($url = $this->sanitize($url)) {
      $original = $url;
      if ($followRedirect && ($tmp = $this->getRedirect($url))) {
        $original = $url;
        $url      = $tmp;
      }

      if (!$full) {
        return $this->urlToId($url);
      }

      if ($data = $this->rselect([$this->fields['url'] => $url])) {
        if ($followRedirect) {
          return array_merge($data, ['original' => $original]);
        }

        return $data;
      }
    }

    return null;
  }


  public function urlToId(string $url): ?string
  {
    if ($url = $this->sanitize($url)) {
      return $this->selectOne($this->fields['id'], [$this->fields['url'] => $url]);
    }

    return null;
  }


  /**
   * Returns the url of the given row
   *
   * @param string $id_url
   * @return array|null
   */
  public function getFullUrl(string $id_url, bool $followRedirect = true): ?array
  {
    if ($id_url && $followRedirect && ($tmp = $this->getRedirectById($id_url))) {
      $id_url = $tmp;
    }

    if ($id_url && ($data = $this->rselect($id_url))) {
      return $data;
    }

    return null;
  }


  /**
   * Returns the url of the given row
   *
   * @param string $id_url
   * @return string|null
   */
  public function getUrl(string $id_url, bool $followRedirect = true): ?string
  {
    if ($id_url && $followRedirect && ($tmp = $this->getRedirectById($id_url))) {
      $id_url = $tmp;
    }

    if ($id_url) {
      return $this->selectOne($this->fields['url'], $id_url);
    }

    return null;
  }
}
