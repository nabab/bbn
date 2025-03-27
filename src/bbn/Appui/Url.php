<?php

namespace bbn\Appui;

use bbn\Db;
use bbn\X;
use bbn\Models\Tts\DbActions;
use bbn\Models\Cls\Db as DbCls;
use Exception;

class Url extends DbCls
{
  use DbActions;

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


  public function __construct(Db $db)
  {
    parent::__construct($db);
    $this->initClassCfg();
  }


  public function select() {
    return $this->dbTraitSelect(...func_get_args());
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


  public function set(string $url, string $type_url, string|null $id_url = null): bool
  {
    if ($id_url && ($url = $this->sanitize($url))) {
      return (bool)$this->dbTraitUpdate($id_url, ['url' => $url]);
    }

    return (bool)$this->add($url, $type_url);
  }

  public function add(string $url, string $type_url, string $prefix = ''): ?string
  {
    if ($url = $this->sanitize($url, $prefix)) {
      return $this->dbTraitInsert([
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
        && !$this->dbTraitRselect([$this->fields['url'] => $url])
        && ($cfg = $this->dbTraitRselect($id_url))
    ) {
      return $this->dbTraitInsert([
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
        && ($redirect = $this->dbTraitSelectOne($this->fields['redirect'], [$this->fields['url'] => $url]))
    ) {
      if ($this->dbTraitSelectOne($this->fields['redirect'], [$this->fields['id'] => $redirect])) {
        throw new Exception(X::_("You can't redirect a redirected URL (%s)", $url));
      }
      return $this->dbTraitSelectOne($this->fields['url'], $redirect);
    }

    return null;
  }


  public function getRedirectById(string $id): ?string
  {
    if ($redirect = $this->dbTraitSelectOne($this->fields['redirect'], $id)) {
      if ($this->dbTraitSelectOne($this->fields['redirect'], $redirect)) {
        throw new Exception(X::_("You can't redirect a redirected URL (ID %s)", $id));
      }

      return $redirect;
    }

    return null;
  }


  public function urlExists(string $url): bool
  {
    if ($url = $this->sanitize($url)) {
      return $this->dbTraitExists([$this->fields['url'] => $url]);
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

      if ($data = $this->dbTraitRselect([$this->fields['url'] => $url])) {
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
      return $this->dbTraitSelectOne($this->fields['id'], [$this->fields['url'] => $url]);
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

    if ($id_url && ($data = $this->dbTraitRselect($id_url))) {
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
      return $this->dbTraitSelectOne($this->fields['url'], $id_url);
    }

    return null;
  }
}
